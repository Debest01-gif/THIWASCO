<?php
/**
 * THIWASCO MIS - Payments History & Receipts Register
 */
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';

Auth::requirePermission('payments');

$pageTitle = 'Payment Receipts & Transactions';
$activePage = 'payments_list';
$activeSection = 'payments';

// Filters
$search = clean($_GET['search'] ?? '');
$methodId = !empty($_GET['method_id']) ? (int)$_GET['method_id'] : '';
$dateFrom = clean($_GET['date_from'] ?? '');
$dateTo = clean($_GET['date_to'] ?? '');
$highlightId = (int)($_GET['highlight'] ?? 0);
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;

$paymentMethods = db()->fetchAll("SELECT * FROM payment_methods ORDER BY method_name ASC");

// Handle reversal (Admin only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reverse_payment') {
    Auth::requirePermission('admin');
    $payId = (int)$_POST['payment_id'];
    $reason = clean($_POST['reversal_reason'] ?? 'Reversal requested');

    try {
        $pdo = Database::getInstance()->getConnection();
        $pdo->beginTransaction();

        $stmtPay = $pdo->prepare("SELECT * FROM payments WHERE payment_id = ? AND is_reversed = 0 FOR UPDATE");
        $stmtPay->execute([$payId]);
        $pay = $stmtPay->fetch();

        if ($pay) {
            // Restore customer balance
            $pdo->prepare("UPDATE customers SET balance = balance + ? WHERE customer_id = ?")->execute([$pay['amount'], $pay['customer_id']]);

            // Reverse allocations
            $allocs = $pdo->prepare("SELECT * FROM payment_allocations WHERE payment_id = ?");
            $allocs->execute([$payId]);
            $rows = $allocs->fetchAll();

            foreach ($rows as $al) {
                $pdo->prepare("UPDATE invoices SET amount_paid = GREATEST(0, amount_paid - ?), status = IF(amount_paid - ? <= 0, 'Unpaid', 'Partial') WHERE invoice_id = ?")
                    ->execute([$al['amount_allocated'], $al['amount_allocated'], $al['invoice_id']]);
            }

            // Mark payment as reversed
            $pdo->prepare("UPDATE payments SET is_reversed = 1, reversal_reason = ?, reversed_by = ?, reversed_at = NOW() WHERE payment_id = ?")
                ->execute([$reason, Auth::id(), $payId]);

            $pdo->commit();
            Auth::logAction('Reversed Payment', 'payments', "Reversed receipt {$pay['receipt_no']} of KES {$pay['amount']}");
            setFlash('success', "Payment receipt {$pay['receipt_no']} was successfully reversed.");
        }
    } catch (Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
        setFlash('danger', "Reversal failed: " . $e->getMessage());
    }
    header('Location: ' . APP_URL . '/modules/payments/index.php');
    exit;
}

// Build query
$where = ["1=1"];
$params = [];

if (!empty($search)) {
    $where[] = "(p.receipt_no LIKE ? OR c.account_no LIKE ? OR c.full_name LIKE ? OR p.mpesa_ref LIKE ? OR p.bank_ref LIKE ?)";
    $like = "%{$search}%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}
if (!empty($methodId)) {
    $where[] = "p.payment_method_id = ?";
    $params[] = $methodId;
}
if (!empty($dateFrom)) {
    $where[] = "p.payment_date >= ?";
    $params[] = $dateFrom;
}
if (!empty($dateTo)) {
    $where[] = "p.payment_date <= ?";
    $params[] = $dateTo;
}

$whereSql = implode(" AND ", $where);

// Count
$totalRows = (int)db()->fetch("SELECT COUNT(*) as total FROM payments p JOIN customers c ON p.customer_id = c.customer_id WHERE {$whereSql}", $params)['total'];

$totalPages = max(1, ceil($totalRows / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

// Payments list
$sql = "SELECT p.*, c.account_no, c.full_name, c.phone, pm.method_name, pm.method_code, u.full_name as receiver_name
        FROM payments p
        JOIN customers c ON p.customer_id = c.customer_id
        JOIN payment_methods pm ON p.payment_method_id = pm.method_id
        JOIN users u ON p.received_by = u.user_id
        WHERE {$whereSql}
        ORDER BY p.payment_id DESC
        LIMIT {$perPage} OFFSET {$offset}";
$payments = db()->fetchAll($sql, $params);

// Stats
$todayDate = date('Y-m-d');
$monthStart = date('Y-m-01');
$statToday = db()->fetch("SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE payment_date = ? AND is_reversed = 0", [$todayDate])['total'];
$statMonth = db()->fetch("SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE payment_date >= ? AND is_reversed = 0", [$monthStart])['total'];
$statAll = db()->fetch("SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE is_reversed = 0")['total'];

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
  <div class="page-title-wrap">
    <h1><i class="fa-solid fa-receipt" style="color:var(--gold);"></i> Payment Receipts & Collections</h1>
    <p>Real-time register of all customer payments, M-Pesa transactions, bank slips, and revenue receipts</p>
  </div>
  <div class="page-actions">
    <a href="<?= APP_URL ?>/modules/payments/add.php" class="btn btn-primary">
      <i class="fa-solid fa-plus-circle"></i> Post Payment
    </a>
    <a href="<?= APP_URL ?>/modules/payments/mpesa.php" class="btn btn-secondary">
      <i class="fa-brands fa-cc-mastercard"></i> M-Pesa Import
    </a>
  </div>
</div>

<!-- STATS -->
<div class="stats-grid mb-4">
  <div class="stat-card">
    <div class="stat-icon" style="background:rgba(21,128,61,0.1);color:var(--success);"><i class="fa-solid fa-calendar-day"></i></div>
    <div class="stat-info">
      <span class="stat-value"><?= formatCurrency($statToday) ?></span>
      <span class="stat-label">Collected Today</span>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:rgba(30,58,138,0.1);color:var(--royal-blue);"><i class="fa-solid fa-calendar-week"></i></div>
    <div class="stat-info">
      <span class="stat-value"><?= formatCurrency($statMonth) ?></span>
      <span class="stat-label">This Month (<?= date('M Y') ?>)</span>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:rgba(234,179,8,0.1);color:var(--gold);"><i class="fa-solid fa-vault"></i></div>
    <div class="stat-info">
      <span class="stat-value"><?= formatCurrency($statAll) ?></span>
      <span class="stat-label">Total Cumulative Revenue</span>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:rgba(14,165,233,0.1);color:var(--info);"><i class="fa-solid fa-receipt"></i></div>
    <div class="stat-info">
      <span class="stat-value"><?= number_format($totalRows) ?></span>
      <span class="stat-label">Total Receipts</span>
    </div>
  </div>
</div>

<!-- FILTERS -->
<div class="card mb-4">
  <div class="card-body" style="padding:1rem 1.25rem;">
    <form method="GET" action="" style="display:flex;flex-wrap:wrap;gap:1rem;align-items:flex-end;">
      <div style="flex:1;min-width:200px;">
        <label class="form-label" style="font-size:0.8rem;">Search Receipts</label>
        <input type="text" name="search" class="form-control" placeholder="Receipt #, Acc #, Customer, M-Pesa Ref..." value="<?= htmlspecialchars($search) ?>">
      </div>

      <div style="min-width:140px;">
        <label class="form-label" style="font-size:0.8rem;">Method</label>
        <select name="method_id" class="form-control">
          <option value="">All Methods</option>
          <?php foreach ($paymentMethods as $pm): ?>
            <option value="<?= $pm['method_id'] ?>" <?= $methodId == $pm['method_id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($pm['method_name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div style="min-width:130px;">
        <label class="form-label" style="font-size:0.8rem;">Date From</label>
        <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($dateFrom) ?>">
      </div>

      <div style="min-width:130px;">
        <label class="form-label" style="font-size:0.8rem;">Date To</label>
        <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($dateTo) ?>">
      </div>

      <div style="display:flex;gap:0.5rem;">
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-filter"></i> Filter</button>
        <a href="<?= APP_URL ?>/modules/payments/index.php" class="btn btn-secondary"><i class="fa-solid fa-rotate-left"></i> Reset</a>
      </div>
    </form>
  </div>
</div>

<!-- TABLE -->
<div class="card">
  <div class="card-header">
    <h3><i class="fa-solid fa-list-check" style="color:var(--royal-blue);"></i> Receipt Register</h3>
    <span class="badge badge-light">Showing <?= count($payments) ?> of <?= number_format($totalRows) ?> receipts</span>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table">
        <thead>
          <tr>
            <th>Receipt #</th>
            <th>Date</th>
            <th>Customer & Account</th>
            <th>Method</th>
            <th>Reference</th>
            <th style="text-align:right;">Amount (KES)</th>
            <th>Received By</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($payments)): ?>
            <tr>
              <td colspan="9" class="text-center py-4 text-muted">
                <i class="fa-regular fa-folder-open" style="font-size:2rem;margin-bottom:0.5rem;display:block;"></i>
                No payments recorded yet.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($payments as $p): 
              $isHighlighted = ($highlightId === (int)$p['payment_id']);
            ?>
              <tr style="<?= $isHighlighted ? 'background:rgba(234,179,8,0.15);font-weight:600;' : '' ?>">
                <td>
                  <strong style="color:var(--royal-blue);"><?= htmlspecialchars($p['receipt_no']) ?></strong>
                </td>
                <td><?= formatDate($p['payment_date']) ?></td>
                <td>
                  <strong><?= htmlspecialchars($p['full_name']) ?></strong>
                  <div style="font-size:0.8rem;color:var(--text-muted);"><i class="fa-solid fa-id-card"></i> <?= htmlspecialchars($p['account_no']) ?></div>
                </td>
                <td>
                  <span class="badge badge-light" style="border:1px solid #cbd5e1;">
                    <i class="fa-solid <?= $p['method_code'] === 'MPESA' ? 'fa-mobile-screen' : 'fa-money-bill' ?>"></i>
                    <?= htmlspecialchars($p['method_name']) ?>
                  </span>
                </td>
                <td>
                  <?php if ($p['mpesa_ref']): ?>
                    <code style="font-weight:700;color:var(--navy);"><?= htmlspecialchars($p['mpesa_ref']) ?></code>
                  <?php elseif ($p['bank_ref']): ?>
                    <span><?= htmlspecialchars($p['bank_ref']) ?></span>
                  <?php elseif ($p['cheque_no']): ?>
                    <span>Chq: <?= htmlspecialchars($p['cheque_no']) ?></span>
                  <?php else: ?>
                    <span class="text-muted">-</span>
                  <?php endif; ?>
                </td>
                <td style="text-align:right;font-weight:700;color:var(--success);font-size:1.05rem;">
                  <?= number_format($p['amount'], 2) ?>
                </td>
                <td><small><?= htmlspecialchars($p['receiver_name']) ?></small></td>
                <td>
                  <?php if ($p['is_reversed']): ?>
                    <span class="badge badge-danger">Reversed</span>
                  <?php else: ?>
                    <span class="badge badge-success">Completed</span>
                  <?php endif; ?>
                </td>
                <td>
                  <button type="button" class="btn btn-secondary btn-sm" onclick="showReceipt(<?= htmlspecialchars(json_encode($p)) ?>)" title="Print Receipt">
                    <i class="fa-solid fa-print"></i>
                  </button>
                  <?php if (!$p['is_reversed'] && Auth::can('admin')): ?>
                    <button type="button" class="btn btn-danger btn-sm" onclick="confirmReversal(<?= $p['payment_id'] ?>, '<?= $p['receipt_no'] ?>')" title="Reverse Payment">
                      <i class="fa-solid fa-rotate-left"></i>
                    </button>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- PAGINATION -->
    <?php if ($totalPages > 1): ?>
      <div class="pagination-wrap" style="padding:1rem;display:flex;justify-content:space-between;align-items:center;">
        <span class="text-muted" style="font-size:0.85rem;">Page <?= $page ?> of <?= $totalPages ?> (Total: <?= number_format($totalRows) ?>)</span>
        <div class="pagination">
          <?php if ($page > 1): ?>
            <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&method_id=<?= $methodId ?>&date_from=<?= $dateFrom ?>&date_to=<?= $dateTo ?>" class="page-link">&laquo; Prev</a>
          <?php endif; ?>
          <?php 
          $startPage = max(1, $page - 2);
          $endPage = min($totalPages, $page + 2);
          for ($p = $startPage; $p <= $endPage; $p++): 
          ?>
            <a href="?page=<?= $p ?>&search=<?= urlencode($search) ?>&method_id=<?= $methodId ?>&date_from=<?= $dateFrom ?>&date_to=<?= $dateTo ?>" class="page-link <?= $p == $page ? 'active' : '' ?>"><?= $p ?></a>
          <?php endfor; ?>
          <?php if ($page < $totalPages): ?>
            <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&method_id=<?= $methodId ?>&date_from=<?= $dateFrom ?>&date_to=<?= $dateTo ?>" class="page-link">Next &raquo;</a>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- RECEIPT PRINT MODAL -->
<div id="receiptModal" class="modal-backdrop">
  <div class="modal" style="max-width: 480px;">
    <div class="modal-header no-print">
      <h3><i class="fa-solid fa-receipt" style="color:var(--gold);"></i> Official Receipt</h3>
      <button class="modal-close" onclick="closeModal('receiptModal')">&times;</button>
    </div>
    <div class="modal-body" id="receiptPrintArea" style="font-family:monospace;padding:1.5rem;background:#fff;">
      <div style="text-align:center;border-bottom:1px dashed #000;padding-bottom:1rem;margin-bottom:1rem;">
        <h4 style="margin:0;font-weight:800;font-size:1.1rem;">THIKA WATER & SEWERAGE CO.</h4>
        <div style="font-size:0.8rem;">THIWASCO LTD</div>
        <div style="font-size:0.75rem;">P.O. Box 6103 - 01000, Thika</div>
        <div style="font-size:0.75rem;">Tel: +254 720 123 456</div>
        <div style="margin-top:0.5rem;font-weight:700;font-size:0.95rem;text-transform:uppercase;">OFFICIAL PAYMENT RECEIPT</div>
      </div>

      <div style="font-size:0.85rem;line-height:1.6;margin-bottom:1rem;">
        <div><strong>Receipt No:</strong> <span id="rcptNo"></span></div>
        <div><strong>Date:</strong> <span id="rcptDate"></span></div>
        <div><strong>Account No:</strong> <span id="rcptAccount"></span></div>
        <div><strong>Customer:</strong> <span id="rcptCustomer"></span></div>
        <div><strong>Payment Method:</strong> <span id="rcptMethod"></span></div>
        <div><strong>Reference:</strong> <span id="rcptRef"></span></div>
        <div><strong>Cashier:</strong> <span id="rcptCashier"></span></div>
      </div>

      <div style="border-top:1px dashed #000;border-bottom:1px dashed #000;padding:0.75rem 0;margin-bottom:1rem;display:flex;justify-content:space-between;font-size:1.1rem;font-weight:800;">
        <span>AMOUNT RECEIVED:</span>
        <span id="rcptAmount"></span>
      </div>

      <div style="text-align:center;font-size:0.75rem;margin-top:1rem;">
        <div>Thank you for paying your water bill!</div>
        <div>Keep water flowing - report leaks & illegal connections to 0720 123 456</div>
      </div>
    </div>
    <div class="modal-footer no-print">
      <button type="button" class="btn btn-secondary" onclick="closeModal('receiptModal')">Close</button>
      <button type="button" class="btn btn-primary" onclick="printReceipt()"><i class="fa-solid fa-print"></i> Print</button>
    </div>
  </div>
</div>

<!-- REVERSAL MODAL -->
<div id="reversalModal" class="modal-backdrop">
  <div class="modal" style="max-width: 450px;">
    <div class="modal-header">
      <h3 style="color:var(--danger);"><i class="fa-solid fa-triangle-exclamation"></i> Confirm Payment Reversal</h3>
      <button class="modal-close" onclick="closeModal('reversalModal')">&times;</button>
    </div>
    <form method="POST" action="">
      <input type="hidden" name="action" value="reverse_payment">
      <input type="hidden" name="payment_id" id="revPaymentId" value="">
      <div class="modal-body">
        <p>Are you sure you want to reverse receipt <strong id="revReceiptNo"></strong>?</p>
        <p style="font-size:0.85rem;color:var(--text-muted);">This will reinstate the customer's outstanding balance and set allocated invoices back to Unpaid/Partial.</p>
        <div class="form-group mb-3">
          <label class="form-label">Reason for Reversal <span class="text-danger">*</span></label>
          <textarea name="reversal_reason" class="form-control" rows="2" placeholder="e.g. Wrong customer account credited" required></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('reversalModal')">Cancel</button>
        <button type="submit" class="btn btn-danger"><i class="fa-solid fa-rotate-left"></i> Confirm Reversal</button>
      </div>
    </form>
  </div>
</div>

<script>
function showReceipt(p) {
  document.getElementById('rcptNo').textContent = p.receipt_no;
  document.getElementById('rcptDate').textContent = p.payment_date;
  document.getElementById('rcptAccount').textContent = p.account_no;
  document.getElementById('rcptCustomer').textContent = p.full_name;
  document.getElementById('rcptMethod').textContent = p.method_name;
  document.getElementById('rcptRef').textContent = p.mpesa_ref || p.bank_ref || p.cheque_no || 'CASH';
  document.getElementById('rcptCashier').textContent = p.receiver_name;
  document.getElementById('rcptAmount').textContent = 'KES ' + parseFloat(p.amount).toLocaleString(undefined, {minimumFractionDigits: 2});
  openModal('receiptModal');
}

function printReceipt() {
  const content = document.getElementById('receiptPrintArea').innerHTML;
  const printWindow = window.open('', '', 'width=450,height=600');
  printWindow.document.write('<html><head><title>Receipt</title><style>body{font-family:monospace;padding:20px;font-size:12px;}</style></head><body>' + content + '</body></html>');
  printWindow.document.close();
  printWindow.focus();
  printWindow.print();
  printWindow.close();
}

function confirmReversal(id, ref) {
  document.getElementById('revPaymentId').value = id;
  document.getElementById('revReceiptNo').textContent = ref;
  openModal('reversalModal');
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
