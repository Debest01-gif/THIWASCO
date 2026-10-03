<?php
/**
 * THIWASCO MIS - Revenue Collection Report
 */
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';

Auth::requirePermission('reports');

$pageTitle = 'Revenue Collection Report';
$activePage = 'report_revenue';
$activeSection = 'reports';

$dateFrom = clean($_GET['date_from'] ?? date('Y-m-01'));
$dateTo = clean($_GET['date_to'] ?? date('Y-m-d'));
$methodId = !empty($_GET['method_id']) ? (int)$_GET['method_id'] : '';

$paymentMethods = db()->fetchAll("SELECT * FROM payment_methods ORDER BY method_name ASC");

$where = ["p.payment_date >= ?", "p.payment_date <= ?", "p.is_reversed = 0"];
$params = [$dateFrom, $dateTo];

if ($methodId) {
    $where[] = "p.payment_method_id = ?";
    $params[] = $methodId;
}

$whereSql = implode(" AND ", $where);

// Aggregates by payment channel
$byChannel = db()->fetchAll("SELECT pm.method_name, pm.method_code, COUNT(p.payment_id) as txn_count, COALESCE(SUM(p.amount), 0) as channel_total
                             FROM payments p
                             JOIN payment_methods pm ON p.payment_method_id = pm.method_id
                             WHERE {$whereSql}
                             GROUP BY pm.method_id
                             ORDER BY channel_total DESC", $params);

$totalCollected = array_sum(array_column($byChannel, 'channel_total'));
$totalTransactions = array_sum(array_column($byChannel, 'txn_count'));

// Daily collection trend
$dailyTrend = db()->fetchAll("SELECT payment_date, COUNT(*) as count, SUM(amount) as daily_total
                              FROM payments p
                              WHERE {$whereSql}
                              GROUP BY payment_date
                              ORDER BY payment_date ASC", $params);

// Detailed transactions
$transactions = db()->fetchAll("SELECT p.*, c.account_no, c.full_name, pm.method_name, u.full_name as cashier
                                FROM payments p
                                JOIN customers c ON p.customer_id = c.customer_id
                                JOIN payment_methods pm ON p.payment_method_id = pm.method_id
                                JOIN users u ON p.received_by = u.user_id
                                WHERE {$whereSql}
                                ORDER BY p.payment_date DESC, p.payment_id DESC LIMIT 100", $params);

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header no-print">
  <div class="page-title-wrap">
    <h1><i class="fa-solid fa-coins" style="color:var(--gold);"></i> Revenue Collection Report</h1>
    <p>Financial audit of customer payments across M-Pesa, bank transfers, cheques and cash receipts</p>
  </div>
  <div class="page-actions">
    <button onclick="window.print()" class="btn btn-primary">
      <i class="fa-solid fa-print"></i> Print Revenue Report
    </button>
  </div>
</div>

<!-- FILTER -->
<div class="card mb-4 no-print">
  <div class="card-body" style="padding:1rem 1.25rem;">
    <form method="GET" action="" style="display:flex;flex-wrap:wrap;gap:1rem;align-items:flex-end;">
      <div>
        <label class="form-label" style="font-size:0.8rem;">Date From</label>
        <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($dateFrom) ?>">
      </div>
      <div>
        <label class="form-label" style="font-size:0.8rem;">Date To</label>
        <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($dateTo) ?>">
      </div>
      <div style="min-width:160px;">
        <label class="form-label" style="font-size:0.8rem;">Channel / Method</label>
        <select name="method_id" class="form-control">
          <option value="">All Payment Methods</option>
          <?php foreach ($paymentMethods as $pm): ?>
            <option value="<?= $pm['method_id'] ?>" <?= $methodId == $pm['method_id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($pm['method_name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-filter"></i> Run Report</button>
      </div>
    </form>
  </div>
</div>

<div class="printable-report">
  <!-- TOTALS -->
  <div class="stats-grid mb-4">
    <div class="stat-card">
      <div class="stat-icon" style="background:rgba(21,128,61,0.1);color:var(--success);"><i class="fa-solid fa-vault"></i></div>
      <div class="stat-info">
        <span class="stat-value"><?= formatCurrency($totalCollected) ?></span>
        <span class="stat-label">Total Revenue Collected</span>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon" style="background:rgba(30,58,138,0.1);color:var(--royal-blue);"><i class="fa-solid fa-receipt"></i></div>
      <div class="stat-info">
        <span class="stat-value"><?= number_format($totalTransactions) ?></span>
        <span class="stat-label">Receipts Processed</span>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon" style="background:rgba(234,179,8,0.1);color:var(--gold);"><i class="fa-solid fa-calculator"></i></div>
      <div class="stat-info">
        <span class="stat-value"><?= $totalTransactions > 0 ? formatCurrency($totalCollected / $totalTransactions) : 'KES 0' ?></span>
        <span class="stat-label">Average Ticket Size</span>
      </div>
    </div>
  </div>

  <!-- BREAKDOWN BY CHANNEL -->
  <div class="card mb-4">
    <div class="card-header">
      <h3><i class="fa-solid fa-credit-card" style="color:var(--royal-blue);"></i> Collection by Payment Gateway</h3>
    </div>
    <div class="card-body p-0">
      <table class="table">
        <thead>
          <tr>
            <th>Payment Gateway / Method</th>
            <th style="text-align:right;">Transactions</th>
            <th style="text-align:right;">Total Amount (KES)</th>
            <th style="text-align:right;">Percentage Share</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($byChannel as $c): 
            $share = $totalCollected > 0 ? round(($c['channel_total'] / $totalCollected) * 100, 1) : 0;
          ?>
            <tr>
              <td><strong><?= htmlspecialchars($c['method_name']) ?></strong></td>
              <td style="text-align:right;"><?= number_format($c['txn_count']) ?></td>
              <td style="text-align:right;font-weight:700;color:var(--success);"><?= formatCurrency($c['channel_total']) ?></td>
              <td style="text-align:right;"><strong><?= $share ?>%</strong></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- RECENT TRANSACTIONS -->
  <div class="card">
    <div class="card-header">
      <h3><i class="fa-solid fa-list-check" style="color:var(--royal-blue);"></i> Detailed Payment Entries</h3>
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table" style="font-size:0.85rem;">
          <thead>
            <tr>
              <th>Receipt #</th>
              <th>Date</th>
              <th>Customer</th>
              <th>Account #</th>
              <th>Method</th>
              <th>Reference Code</th>
              <th style="text-align:right;">Amount (KES)</th>
              <th>Cashier</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($transactions as $t): ?>
              <tr>
                <td><strong><?= htmlspecialchars($t['receipt_no']) ?></strong></td>
                <td><?= formatDate($t['payment_date']) ?></td>
                <td><?= htmlspecialchars($t['full_name']) ?></td>
                <td><?= htmlspecialchars($t['account_no']) ?></td>
                <td><span class="badge badge-light"><?= htmlspecialchars($t['method_name']) ?></span></td>
                <td><code><?= htmlspecialchars($t['mpesa_ref'] ?? $t['bank_ref'] ?? $t['cheque_no'] ?? 'CASH') ?></code></td>
                <td style="text-align:right;font-weight:700;color:var(--success);"><?= number_format($t['amount'], 2) ?></td>
                <td><small><?= htmlspecialchars($t['cashier']) ?></small></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
