<?php
/**
 * THIWASCO MIS - Post Customer Payment
 */
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';

Auth::requirePermission('payments');

$pageTitle = 'Post Customer Payment';
$activePage = 'payments_add';
$activeSection = 'payments';

$customerId = (int)($_GET['customer_id'] ?? 0);
$preselectedInvoiceId = (int)($_GET['invoice_id'] ?? 0);
$errors = [];
$successReceiptId = null;

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'post_payment') {
    $customerId = (int)($_POST['customer_id'] ?? 0);
    $amount = (float)($_POST['amount'] ?? 0);
    $paymentMethodId = (int)($_POST['payment_method_id'] ?? 0);
    $paymentDate = clean($_POST['payment_date'] ?? date('Y-m-d'));
    $mpesaRef = strtoupper(clean($_POST['mpesa_ref'] ?? ''));
    $bankRef = clean($_POST['bank_ref'] ?? '');
    $chequeNo = clean($_POST['cheque_no'] ?? '');
    $bankName = clean($_POST['bank_name'] ?? '');
    $notes = clean($_POST['notes'] ?? '');

    if ($customerId <= 0) {
        $errors[] = 'Please select a valid customer.';
    }
    if ($amount <= 0) {
        $errors[] = 'Payment amount must be greater than zero.';
    }
    if ($paymentMethodId <= 0) {
        $errors[] = 'Please select a payment method.';
    }

    // Verify payment method specific requirements
    $method = db()->fetch("SELECT * FROM payment_methods WHERE method_id = ?", [$paymentMethodId]);
    if ($method && $method['method_code'] === 'MPESA' && empty($mpesaRef)) {
        $errors[] = 'M-Pesa transaction reference (e.g. QKH7129XYZ) is required for M-Pesa payments.';
    }

    if (empty($errors)) {
        try {
            $pdo = Database::getInstance()->getConnection();
            $pdo->beginTransaction();

            // Lock customer row
            $stmtCust = $pdo->prepare("SELECT customer_id, balance, full_name, account_no FROM customers WHERE customer_id = ? FOR UPDATE");
            $stmtCust->execute([$customerId]);
            $cust = $stmtCust->fetch();

            if (!$cust) {
                throw new Exception('Customer not found.');
            }

            // Generate receipt number
            $currentYearMonth = date('Ym');
            $refCounter = 1;
            $lastRcpt = db()->fetch("SELECT MAX(receipt_no) as last_ref FROM payments WHERE receipt_no LIKE ?", ["RCP{$currentYearMonth}%"]);
            if ($lastRcpt && $lastRcpt['last_ref']) {
                $refCounter = (int)substr($lastRcpt['last_ref'], -4) + 1;
            }
            $receiptNo = 'RCP' . $currentYearMonth . str_pad($refCounter, 4, '0', STR_PAD_LEFT);

            // Insert into payments
            $stmtPay = $pdo->prepare("INSERT INTO payments 
                (receipt_no, customer_id, received_by, payment_method_id, payment_date, amount, mpesa_ref, bank_ref, cheque_no, bank_name, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmtPay->execute([
                $receiptNo,
                $customerId,
                Auth::id(),
                $paymentMethodId,
                $paymentDate,
                $amount,
                $mpesaRef ?: null,
                $bankRef ?: null,
                $chequeNo ?: null,
                $bankName ?: null,
                $notes ?: null
            ]);
            $paymentId = $pdo->lastInsertId();

            // Allocate payment to unpaid/partial invoices from oldest to newest
            $stmtUnpaid = $pdo->prepare("SELECT invoice_id, total_payable, amount_paid 
                                         FROM invoices 
                                         WHERE customer_id = ? AND status IN ('Unpaid', 'Partial') 
                                         ORDER BY invoice_date ASC, invoice_id ASC FOR UPDATE");
            $stmtUnpaid->execute([$customerId]);
            $unpaidInvoices = $stmtUnpaid->fetchAll();

            $remainingAmount = $amount;
            $stmtAlloc = $pdo->prepare("INSERT INTO payment_allocations (payment_id, invoice_id, amount_allocated) VALUES (?, ?, ?)");
            $stmtUpdateInv = $pdo->prepare("UPDATE invoices SET amount_paid = amount_paid + ?, status = ? WHERE invoice_id = ?");

            foreach ($unpaidInvoices as $inv) {
                if ($remainingAmount <= 0) break;

                $dueOnInvoice = (float)$inv['total_payable'] - (float)$inv['amount_paid'];
                if ($dueOnInvoice <= 0) continue;

                $allocAmount = min($remainingAmount, $dueOnInvoice);
                $newAmountPaid = (float)$inv['amount_paid'] + $allocAmount;
                $newStatus = ($newAmountPaid >= (float)$inv['total_payable']) ? 'Paid' : 'Partial';

                $stmtAlloc->execute([$paymentId, $inv['invoice_id'], $allocAmount]);
                $stmtUpdateInv->execute([$allocAmount, $newStatus, $inv['invoice_id']]);

                $remainingAmount -= $allocAmount;
            }

            // Update customer balance: reduce by the payment amount
            $stmtUpdateCust = $pdo->prepare("UPDATE customers SET balance = balance - ? WHERE customer_id = ?");
            $stmtUpdateCust->execute([$amount, $customerId]);

            $pdo->commit();

            Auth::logAction('Posted Payment', 'payments', "Receipt {$receiptNo}: Received KES {$amount} for {$cust['account_no']} ({$cust['full_name']})");
            setFlash('success', "Payment receipt <strong>{$receiptNo}</strong> of KES " . number_format($amount, 2) . " posted successfully!");

            header('Location: ' . APP_URL . '/modules/payments/index.php?highlight=' . $paymentId);
            exit;

        } catch (Exception $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = 'Payment processing failed: ' . $e->getMessage();
        }
    }
}

// Payment Methods
$paymentMethods = db()->fetchAll("SELECT * FROM payment_methods WHERE is_active = 1");

// If customer is selected, get customer info and unpaid invoices
$selectedCustomer = null;
$unpaidInvoices = [];
if ($customerId > 0) {
    $selectedCustomer = db()->fetch("SELECT c.*, z.zone_name, m.meter_serial 
                                     FROM customers c 
                                     JOIN zones z ON c.zone_id = z.zone_id 
                                     LEFT JOIN meters m ON c.customer_id = m.customer_id AND m.status = 'Active'
                                     WHERE c.customer_id = ?", [$customerId]);
    if ($selectedCustomer) {
        $unpaidInvoices = db()->fetchAll("SELECT * FROM invoices 
                                          WHERE customer_id = ? AND status IN ('Unpaid', 'Partial') 
                                          ORDER BY invoice_date ASC", [$customerId]);
    }
}

// Customer search list for dropdown
$allCustomers = db()->fetchAll("SELECT customer_id, account_no, full_name, phone, balance 
                                FROM customers 
                                ORDER BY full_name ASC LIMIT 500");

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
  <div class="page-title-wrap">
    <h1><i class="fa-solid fa-money-bill-wave" style="color:var(--gold);"></i> Post Customer Payment</h1>
    <p>Receive cash, M-Pesa, bank transfer, or cheque payments and automatically settle open invoices</p>
  </div>
  <div class="page-actions">
    <a href="<?= APP_URL ?>/modules/payments/index.php" class="btn btn-secondary">
      <i class="fa-solid fa-receipt"></i> Payment History
    </a>
    <a href="<?= APP_URL ?>/modules/payments/mpesa.php" class="btn btn-secondary">
      <i class="fa-brands fa-cc-mastercard"></i> M-Pesa Import
    </a>
  </div>
</div>

<?php if (!empty($errors)): ?>
  <div class="alert alert-danger">
    <i class="fa-solid fa-circle-exclamation"></i>
    <div>
      <?php foreach ($errors as $err): ?>
        <div><?= htmlspecialchars($err) ?></div>
      <?php endforeach; ?>
    </div>
  </div>
<?php endif; ?>

<div class="row" style="display:grid;grid-template-columns: 1.4fr 1fr; gap:1.5rem;">
  <!-- PAYMENT ENTRY FORM -->
  <div class="card">
    <div class="card-header">
      <h3><i class="fa-solid fa-circle-plus" style="color:var(--royal-blue);"></i> Payment Details</h3>
    </div>
    <div class="card-body">
      <form method="POST" action="" id="paymentForm">
        <input type="hidden" name="action" value="post_payment">

        <!-- CUSTOMER SELECT -->
        <div class="form-group mb-3">
          <label class="form-label">Select Customer Account <span class="text-danger">*</span></label>
          <select name="customer_id" id="customerSelect" class="form-control" required onchange="location.href='?customer_id=' + this.value;">
            <option value="">-- Search & Select Customer --</option>
            <?php foreach ($allCustomers as $c): ?>
              <option value="<?= $c['customer_id'] ?>" <?= $customerId == $c['customer_id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($c['account_no']) ?> - <?= htmlspecialchars($c['full_name']) ?> (Bal: <?= formatCurrency($c['balance']) ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="row" style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
          <div class="form-group mb-3">
            <label class="form-label">Payment Amount (KES) <span class="text-danger">*</span></label>
            <input type="number" step="0.01" name="amount" id="payAmount" class="form-control" style="font-size:1.15rem;font-weight:700;color:var(--navy);" 
                   value="<?= $selectedCustomer ? max(0, (float)$selectedCustomer['balance']) : '' ?>" placeholder="0.00" required>
          </div>

          <div class="form-group mb-3">
            <label class="form-label">Payment Method <span class="text-danger">*</span></label>
            <select name="payment_method_id" id="paymentMethodSelect" class="form-control" required onchange="toggleMethodFields(this.value)">
              <?php foreach ($paymentMethods as $pm): ?>
                <option value="<?= $pm['method_id'] ?>" data-code="<?= $pm['method_code'] ?>" <?= $pm['method_code'] === 'MPESA' ? 'selected' : '' ?>>
                  <?= htmlspecialchars($pm['method_name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="form-group mb-3">
          <label class="form-label">Payment Date <span class="text-danger">*</span></label>
          <input type="date" name="payment_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
        </div>

        <!-- CONDITIONAL FIELDS -->
        <div id="mpesaFields" class="form-group mb-3">
          <label class="form-label">M-Pesa Transaction Ref / Receipt Code <span class="text-danger">*</span></label>
          <input type="text" name="mpesa_ref" class="form-control" placeholder="e.g. QKH4891ZXD" style="text-transform:uppercase;">
          <small class="text-muted">Unique 10-character Safaricom M-Pesa transaction confirmation code</small>
        </div>

        <div id="bankFields" class="row" style="display:none;grid-template-columns:1fr 1fr;gap:1rem;">
          <div class="form-group mb-3">
            <label class="form-label">Bank Reference / Slip No</label>
            <input type="text" name="bank_ref" class="form-control" placeholder="Slip or transfer ref">
          </div>
          <div class="form-group mb-3">
            <label class="form-label">Bank Name</label>
            <input type="text" name="bank_name" class="form-control" placeholder="e.g. KCB, Equity">
          </div>
        </div>

        <div id="chequeFields" class="row" style="display:none;grid-template-columns:1fr 1fr;gap:1rem;">
          <div class="form-group mb-3">
            <label class="form-label">Cheque Number</label>
            <input type="text" name="cheque_no" class="form-control" placeholder="e.g. 000412">
          </div>
        </div>

        <div class="form-group mb-4">
          <label class="form-label">Notes / Remarks</label>
          <textarea name="notes" class="form-control" rows="2" placeholder="Optional comments..."></textarea>
        </div>

        <div class="form-actions" style="display:flex;justify-content:flex-end;gap:1rem;">
          <button type="reset" class="btn btn-secondary">Clear</button>
          <button type="submit" class="btn btn-success" style="padding:0.75rem 1.75rem;font-size:1rem;">
            <i class="fa-solid fa-check-double"></i> Process Payment & Print Receipt
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- RIGHT: CUSTOMER STATUS & UNPAID BILLS -->
  <div>
    <?php if ($selectedCustomer): ?>
      <div class="card mb-3" style="border-left:4px solid var(--royal-blue);">
        <div class="card-header">
          <h3><i class="fa-solid fa-user-check" style="color:var(--royal-blue);"></i> Customer Summary</h3>
          <span class="badge <?= $selectedCustomer['balance'] > 0 ? 'badge-danger' : 'badge-success' ?>">
            <?= $selectedCustomer['balance'] > 0 ? 'Outstanding Debt' : 'Fully Cleared' ?>
          </span>
        </div>
        <div class="card-body">
          <h4 style="margin:0 0 6px 0;color:var(--navy);font-size:1.15rem;"><?= htmlspecialchars($selectedCustomer['full_name']) ?></h4>
          <div style="font-size:0.85rem;color:var(--text-muted);line-height:1.6;">
            <strong>Account:</strong> <span style="color:var(--royal-blue);font-weight:700;"><?= htmlspecialchars($selectedCustomer['account_no']) ?></span><br>
            <strong>Phone:</strong> <?= htmlspecialchars($selectedCustomer['phone']) ?><br>
            <strong>Zone:</strong> <?= htmlspecialchars($selectedCustomer['zone_name']) ?><br>
            <strong>Active Meter:</strong> <?= htmlspecialchars($selectedCustomer['meter_serial'] ?? 'None') ?>
          </div>

          <div style="margin-top:1rem;background:var(--bg-light);padding:0.85rem;border-radius:6px;">
            <div style="font-size:0.8rem;text-transform:uppercase;color:var(--text-muted);font-weight:600;">Current Balance Due</div>
            <div style="font-size:1.6rem;font-weight:800;color:var(--danger);">
              <?= formatCurrency($selectedCustomer['balance']) ?>
            </div>
          </div>
        </div>
      </div>

      <!-- UNPAID INVOICES QUEUE -->
      <div class="card">
        <div class="card-header">
          <h3><i class="fa-solid fa-clock" style="color:var(--gold);"></i> Unpaid Invoices Queue</h3>
          <span class="badge badge-warning"><?= count($unpaidInvoices) ?> Pending</span>
        </div>
        <div class="card-body p-0">
          <?php if (empty($unpaidInvoices)): ?>
            <div class="p-3 text-center text-muted" style="font-size:0.85rem;">
              No open invoices for this customer. Payment will be credited directly to account balance.
            </div>
          <?php else: ?>
            <table class="table" style="font-size:0.85rem;">
              <thead>
                <tr>
                  <th>Month</th>
                  <th>Inv #</th>
                  <th>Total Due</th>
                  <th>Paid</th>
                  <th>Balance</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($unpaidInvoices as $u): 
                  $due = max(0, (float)$u['total_payable'] - (float)$u['amount_paid']);
                ?>
                  <tr>
                    <td><strong><?= htmlspecialchars($u['billing_month']) ?></strong></td>
                    <td><?= htmlspecialchars($u['invoice_no']) ?></td>
                    <td><?= formatCurrency($u['total_payable']) ?></td>
                    <td style="color:var(--success);"><?= formatCurrency($u['amount_paid']) ?></td>
                    <td style="font-weight:700;color:var(--danger);"><?= formatCurrency($due) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php endif; ?>
        </div>
      </div>

    <?php else: ?>
      <div class="card">
        <div class="card-body text-center py-5" style="color:var(--text-muted);">
          <i class="fa-solid fa-user-tag" style="font-size:2.5rem;color:var(--gold);margin-bottom:1rem;display:block;"></i>
          <h4>Select a Customer to Proceed</h4>
          <p style="font-size:0.85rem;">Use the dropdown on the left to look up a customer by account number or name. Their open invoices and balance will appear here automatically.</p>
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>

<script>
function toggleMethodFields(val) {
  const select = document.getElementById('paymentMethodSelect');
  const selectedOpt = select.options[select.selectedIndex];
  const code = selectedOpt.getAttribute('data-code');

  document.getElementById('mpesaFields').style.display = (code === 'MPESA') ? 'block' : 'none';
  document.getElementById('bankFields').style.display = (code === 'BANK' || code === 'RTGS' || code === 'CARD') ? 'grid' : 'none';
  document.getElementById('chequeFields').style.display = (code === 'CHEQUE') ? 'grid' : 'none';
}
// Trigger on page load
document.addEventListener('DOMContentLoaded', function() {
  toggleMethodFields(document.getElementById('paymentMethodSelect').value);
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
