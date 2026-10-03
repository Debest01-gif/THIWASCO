<?php
/**
 * THIWASCO MIS - M-Pesa Paybill C2B Simulator & Statement Reconciler
 */
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';

Auth::requirePermission('payments');

$pageTitle = 'M-Pesa Paybill Reconciliation';
$activePage = 'payments_mpesa';
$activeSection = 'payments';

$errors = [];
$successMessage = '';

// Handle manual M-Pesa transaction simulation or import
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'simulate_mpesa') {
    $mpesaCode = strtoupper(clean($_POST['mpesa_code'] ?? ''));
    $phone = clean($_POST['phone'] ?? '');
    $accountRef = clean($_POST['account_ref'] ?? '');
    $amount = (float)($_POST['amount'] ?? 0);
    $senderName = clean($_POST['sender_name'] ?? '');

    if (empty($mpesaCode) || strlen($mpesaCode) < 6) {
        $errors[] = 'A valid M-Pesa transaction code is required (e.g. QKH789123).';
    }
    if ($amount <= 0) {
        $errors[] = 'Amount must be greater than zero.';
    }
    if (empty($accountRef)) {
        $errors[] = 'Account number / Bill reference is required.';
    }

    // Check duplicate M-Pesa ref
    $existing = db()->fetch("SELECT payment_id FROM payments WHERE mpesa_ref = ?", [$mpesaCode]);
    if ($existing) {
        $errors[] = "Transaction code {$mpesaCode} has already been recorded and processed.";
    }

    if (empty($errors)) {
        try {
            $pdo = Database::getInstance()->getConnection();
            $pdo->beginTransaction();

            // Match customer by account_no or phone
            $stmtCust = $pdo->prepare("SELECT customer_id, balance, account_no, full_name 
                                       FROM customers 
                                       WHERE account_no = ? OR phone = ? OR phone_alt = ? 
                                       LIMIT 1 FOR UPDATE");
            $stmtCust->execute([$accountRef, $phone, $phone]);
            $cust = $stmtCust->fetch();

            if (!$cust) {
                // If not found directly, try partial match or fail
                $stmtCust2 = $pdo->prepare("SELECT customer_id, balance, account_no, full_name FROM customers WHERE account_no LIKE ? LIMIT 1 FOR UPDATE");
                $stmtCust2->execute(["%{$accountRef}%"]);
                $cust = $stmtCust2->fetch();
            }

            if (!$cust) {
                throw new Exception("No active customer found matching account reference '{$accountRef}' or phone '{$phone}'.");
            }

            // Get MPESA method ID
            $mpesaMethod = db()->fetch("SELECT method_id FROM payment_methods WHERE method_code = 'MPESA'");
            $methodId = $mpesaMethod ? $mpesaMethod['method_id'] : 2;

            // Generate receipt number
            $currentYearMonth = date('Ym');
            $refCounter = 1;
            $lastRcpt = db()->fetch("SELECT MAX(receipt_no) as last_ref FROM payments WHERE receipt_no LIKE ?", ["RCP{$currentYearMonth}%"]);
            if ($lastRcpt && $lastRcpt['last_ref']) {
                $refCounter = (int)substr($lastRcpt['last_ref'], -4) + 1;
            }
            $receiptNo = 'RCP' . $currentYearMonth . str_pad($refCounter, 4, '0', STR_PAD_LEFT);

            // Insert payment
            $stmtPay = $pdo->prepare("INSERT INTO payments 
                (receipt_no, customer_id, received_by, payment_method_id, payment_date, amount, mpesa_ref, notes)
                VALUES (?, ?, ?, ?, CURDATE(), ?, ?, ?)");
            $stmtPay->execute([
                $receiptNo,
                $cust['customer_id'],
                Auth::id(),
                $methodId,
                $amount,
                $mpesaCode,
                "M-Pesa Paybill from {$senderName} ({$phone})"
            ]);
            $paymentId = $pdo->lastInsertId();

            // Allocate to oldest unpaid invoices
            $stmtUnpaid = $pdo->prepare("SELECT invoice_id, total_payable, amount_paid 
                                         FROM invoices 
                                         WHERE customer_id = ? AND status IN ('Unpaid', 'Partial') 
                                         ORDER BY invoice_date ASC, invoice_id ASC FOR UPDATE");
            $stmtUnpaid->execute([$cust['customer_id']]);
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

            // Update balance
            $pdo->prepare("UPDATE customers SET balance = balance - ? WHERE customer_id = ?")->execute([$amount, $cust['customer_id']]);

            $pdo->commit();

            Auth::logAction('M-Pesa Reconciled', 'payments', "M-Pesa {$mpesaCode} KES {$amount} settled for {$cust['account_no']}");
            $successMessage = "M-Pesa payment <strong>{$mpesaCode}</strong> (KES " . number_format($amount, 2) . ") successfully matched & reconciled to customer <strong>{$cust['full_name']}</strong> ({$cust['account_no']}) with receipt <strong>{$receiptNo}</strong>.";

        } catch (Exception $e) {
            if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
            $errors[] = $e->getMessage();
        }
    }
}

// Recent M-Pesa transactions
$recentMpesa = db()->fetchAll("SELECT p.*, c.account_no, c.full_name, c.phone 
                               FROM payments p 
                               JOIN customers c ON p.customer_id = c.customer_id 
                               JOIN payment_methods pm ON p.payment_method_id = pm.method_id 
                               WHERE pm.method_code = 'MPESA' 
                               ORDER BY p.payment_id DESC LIMIT 20");

// Paybill settings
$paybill = getSetting('mpesa_paybill', '888000');

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
  <div class="page-title-wrap">
    <h1><i class="fa-brands fa-cc-mastercard" style="color:var(--gold);"></i> M-Pesa Paybill Reconciliation</h1>
    <p>Manage Safaricom Daraja API C2B callbacks, instant payment reconciliation and customer ledger updates</p>
  </div>
  <div class="page-actions">
    <a href="<?= APP_URL ?>/modules/payments/index.php" class="btn btn-secondary">
      <i class="fa-solid fa-receipt"></i> Payment Register
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

<?php if ($successMessage): ?>
  <div class="alert alert-success">
    <i class="fa-solid fa-circle-check"></i>
    <div><?= $successMessage ?></div>
  </div>
<?php endif; ?>

<div class="row" style="display:grid;grid-template-columns: 1.2fr 1fr; gap:1.5rem;">
  
  <!-- MPESA RECONCILER SIMULATOR -->
  <div class="card">
    <div class="card-header">
      <h3><i class="fa-solid fa-mobile-screen" style="color:var(--royal-blue);"></i> Process Incoming M-Pesa Transaction</h3>
      <span class="badge badge-success">C2B Paybill: <?= htmlspecialchars($paybill) ?></span>
    </div>
    <div class="card-body">
      <form method="POST" action="">
        <input type="hidden" name="action" value="simulate_mpesa">

        <div class="row" style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
          <div class="form-group mb-3">
            <label class="form-label">M-Pesa Receipt Code <span class="text-danger">*</span></label>
            <input type="text" name="mpesa_code" class="form-control" placeholder="e.g. QKH4891ZXD" style="text-transform:uppercase;font-weight:700;" required>
          </div>
          <div class="form-group mb-3">
            <label class="form-label">Amount (KES) <span class="text-danger">*</span></label>
            <input type="number" step="0.01" name="amount" class="form-control" placeholder="0.00" style="font-weight:700;color:var(--navy);" required>
          </div>
        </div>

        <div class="row" style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
          <div class="form-group mb-3">
            <label class="form-label">Account No (Bill Reference) <span class="text-danger">*</span></label>
            <input type="text" name="account_ref" class="form-control" placeholder="e.g. THIWASCO-ZN001-0001" required>
            <small class="text-muted">Customer enters this on Lipa na M-Pesa</small>
          </div>
          <div class="form-group mb-3">
            <label class="form-label">Customer Mobile Phone</label>
            <input type="text" name="phone" class="form-control" placeholder="07XXXXXXXX">
          </div>
        </div>

        <div class="form-group mb-4">
          <label class="form-label">Customer Name (as on M-Pesa)</label>
          <input type="text" name="sender_name" class="form-control" placeholder="e.g. JOHN MAINA NDUNG'U">
        </div>

        <div class="form-actions" style="display:flex;justify-content:flex-end;">
          <button type="submit" class="btn btn-success" style="padding:0.75rem 1.5rem;">
            <i class="fa-solid fa-bolt"></i> Reconcile & Settle Invoices
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- API SETTINGS INFO -->
  <div>
    <div class="card mb-3">
      <div class="card-header">
        <h3><i class="fa-solid fa-plug" style="color:var(--gold);"></i> Safaricom Daraja API Config</h3>
        <span class="badge badge-info">Production Ready</span>
      </div>
      <div class="card-body" style="font-size:0.85rem;line-height:1.7;">
        <div><strong>Shortcode / Paybill:</strong> <code style="color:var(--navy);font-weight:700;"><?= htmlspecialchars($paybill) ?></code></div>
        <div><strong>Validation URL:</strong> <code><?= APP_URL ?>/api/mpesa/validation.php</code></div>
        <div><strong>Confirmation URL:</strong> <code><?= APP_URL ?>/api/mpesa/confirmation.php</code></div>
        <div><strong>Response Type:</strong> <code>Completed</code> (Immediate allocation)</div>

        <div class="alert alert-info mt-3 mb-0" style="font-size:0.8rem;">
          <i class="fa-solid fa-circle-info"></i>
          <span>Transactions sent to Paybill <strong><?= htmlspecialchars($paybill) ?></strong> with customer account number in BillRefNumber automatically match the customer, allocate to their oldest open water bill, reduce customer balance, and log an audit trail.</span>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- RECENT MPESA TRANSACTIONS -->
<div class="card mt-4">
  <div class="card-header">
    <h3><i class="fa-solid fa-clock-rotate-left" style="color:var(--royal-blue);"></i> Recent Reconciled M-Pesa Transactions</h3>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table">
        <thead>
          <tr>
            <th>Receipt #</th>
            <th>M-Pesa Code</th>
            <th>Date</th>
            <th>Customer</th>
            <th>Account No</th>
            <th style="text-align:right;">Amount (KES)</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($recentMpesa)): ?>
            <tr>
              <td colspan="7" class="text-center py-4 text-muted">No M-Pesa transactions processed yet.</td>
            </tr>
          <?php else: ?>
            <?php foreach ($recentMpesa as $m): ?>
              <tr>
                <td><strong><?= htmlspecialchars($m['receipt_no']) ?></strong></td>
                <td><code style="font-weight:700;color:var(--navy);"><?= htmlspecialchars($m['mpesa_ref']) ?></code></td>
                <td><?= formatDate($m['payment_date']) ?></td>
                <td><?= htmlspecialchars($m['full_name']) ?></td>
                <td><?= htmlspecialchars($m['account_no']) ?></td>
                <td style="text-align:right;font-weight:700;color:var(--success);"><?= number_format($m['amount'], 2) ?></td>
                <td><span class="badge badge-success"><i class="fa-solid fa-check"></i> Reconciled</span></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
