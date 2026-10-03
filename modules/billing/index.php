<?php
/**
 * THIWASCO MIS - Invoices List & Management
 */
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';

Auth::requirePermission('billing');

$pageTitle = 'Customer Invoices & Billing';
$activePage = 'billing_invoices';
$activeSection = 'billing';

// Filters
$billingMonth = clean($_GET['billing_month'] ?? '');
$zoneId = !empty($_GET['zone_id']) ? (int)$_GET['zone_id'] : '';
$status = clean($_GET['status'] ?? '');
$search = clean($_GET['search'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;

// Zones for filter
$zones = db()->fetchAll("SELECT * FROM zones WHERE is_active = 1 ORDER BY zone_name ASC");

// Build query
$where = ["1=1"];
$params = [];

if (!empty($billingMonth)) {
    $where[] = "i.billing_month = ?";
    $params[] = $billingMonth;
}
if (!empty($zoneId)) {
    $where[] = "c.zone_id = ?";
    $params[] = $zoneId;
}
if (!empty($status)) {
    $where[] = "i.status = ?";
    $params[] = $status;
}
if (!empty($search)) {
    $where[] = "(i.invoice_no LIKE ? OR c.account_no LIKE ? OR c.full_name LIKE ? OR m.meter_serial LIKE ?)";
    $like = "%{$search}%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$whereSql = implode(" AND ", $where);

// Count
$countSql = "SELECT COUNT(*) as total 
             FROM invoices i 
             JOIN customers c ON i.customer_id = c.customer_id 
             JOIN meters m ON i.meter_id = m.meter_id 
             WHERE {$whereSql}";
$totalRows = (int)db()->fetch($countSql, $params)['total'];

// Pagination
$totalPages = max(1, ceil($totalRows / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

// Invoices data
$sql = "SELECT i.*, c.account_no, c.full_name, c.phone, z.zone_code, z.zone_name, m.meter_serial
        FROM invoices i
        JOIN customers c ON i.customer_id = c.customer_id
        JOIN zones z ON c.zone_id = z.zone_id
        JOIN meters m ON i.meter_id = m.meter_id
        WHERE {$whereSql}
        ORDER BY i.invoice_id DESC
        LIMIT {$perPage} OFFSET {$offset}";
$invoices = db()->fetchAll($sql, $params);

// Aggregate stats for current filter
$statSql = "SELECT 
                COUNT(*) as total_invoices,
                COALESCE(SUM(i.total_bill), 0) as sum_total_bill,
                COALESCE(SUM(i.total_payable), 0) as sum_total_payable,
                COALESCE(SUM(i.amount_paid), 0) as sum_amount_paid,
                COALESCE(SUM(i.consumption_m3), 0) as sum_volume
            FROM invoices i
            JOIN customers c ON i.customer_id = c.customer_id
            JOIN meters m ON i.meter_id = m.meter_id
            WHERE {$whereSql}";
$stats = db()->fetch($statSql, $params);
$collectionRate = $stats['sum_total_payable'] > 0 ? round(($stats['sum_amount_paid'] / $stats['sum_total_payable']) * 100, 1) : 0;

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
  <div class="page-title-wrap">
    <h1><i class="fa-solid fa-file-invoice" style="color:var(--gold);"></i> Invoices & Water Bills</h1>
    <p>Manage monthly customer billing records, tariffs, meter consumptions and payment settlements</p>
  </div>
  <div class="page-actions">
    <a href="<?= APP_URL ?>/modules/billing/generate.php" class="btn btn-primary">
      <i class="fa-solid fa-bolt"></i> Generate Monthly Bills
    </a>
    <a href="<?= APP_URL ?>/modules/billing/tariffs.php" class="btn btn-secondary">
      <i class="fa-solid fa-tags"></i> Tariffs
    </a>
  </div>
</div>

<!-- STATS CARDS -->
<div class="stats-grid mb-4">
  <div class="stat-card">
    <div class="stat-icon" style="background:rgba(30,58,138,0.1);color:var(--royal-blue);"><i class="fa-solid fa-file-invoice-dollar"></i></div>
    <div class="stat-info">
      <span class="stat-value"><?= number_format($stats['total_invoices']) ?></span>
      <span class="stat-label">Total Invoices</span>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:rgba(234,179,8,0.1);color:var(--gold);"><i class="fa-solid fa-droplet"></i></div>
    <div class="stat-info">
      <span class="stat-value"><?= number_format($stats['sum_volume'], 1) ?> m³</span>
      <span class="stat-label">Billed Volume</span>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:rgba(21,128,61,0.1);color:var(--success);"><i class="fa-solid fa-coins"></i></div>
    <div class="stat-info">
      <span class="stat-value"><?= formatCurrency($stats['sum_total_payable']) ?></span>
      <span class="stat-label">Total Payable (with Arrears)</span>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:rgba(14,165,233,0.1);color:var(--info);"><i class="fa-solid fa-circle-check"></i></div>
    <div class="stat-info">
      <span class="stat-value"><?= formatCurrency($stats['sum_amount_paid']) ?></span>
      <span class="stat-label">Collected (<?= $collectionRate ?>%)</span>
    </div>
  </div>
</div>

<!-- FILTER CARD -->
<div class="card mb-4">
  <div class="card-body" style="padding:1rem 1.25rem;">
    <form method="GET" action="" style="display:flex;flex-wrap:wrap;gap:1rem;align-items:flex-end;">
      <div style="flex:1;min-width:200px;">
        <label class="form-label" style="font-size:0.8rem;">Search Invoices / Customer / Meter</label>
        <div style="position:relative;">
          <input type="text" name="search" class="form-control" placeholder="Invoice #, Acc #, Name, Meter Serial..." value="<?= htmlspecialchars($search) ?>">
        </div>
      </div>

      <div style="min-width:140px;">
        <label class="form-label" style="font-size:0.8rem;">Billing Month</label>
        <input type="month" name="billing_month" class="form-control" value="<?= htmlspecialchars($billingMonth) ?>">
      </div>

      <div style="min-width:160px;">
        <label class="form-label" style="font-size:0.8rem;">Zone / DMA</label>
        <select name="zone_id" class="form-control">
          <option value="">All Zones</option>
          <?php foreach ($zones as $z): ?>
            <option value="<?= $z['zone_id'] ?>" <?= $zoneId == $z['zone_id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($z['zone_code'] . ' - ' . $z['zone_name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div style="min-width:140px;">
        <label class="form-label" style="font-size:0.8rem;">Payment Status</label>
        <select name="status" class="form-control">
          <option value="">All Statuses</option>
          <option value="Unpaid" <?= $status === 'Unpaid' ? 'selected' : '' ?>>Unpaid</option>
          <option value="Partial" <?= $status === 'Partial' ? 'selected' : '' ?>>Partial</option>
          <option value="Paid" <?= $status === 'Paid' ? 'selected' : '' ?>>Paid</option>
          <option value="Cancelled" <?= $status === 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
        </select>
      </div>

      <div style="display:flex;gap:0.5rem;">
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-filter"></i> Filter</button>
        <a href="<?= APP_URL ?>/modules/billing/index.php" class="btn btn-secondary"><i class="fa-solid fa-rotate-left"></i> Reset</a>
      </div>
    </form>
  </div>
</div>

<!-- INVOICES TABLE -->
<div class="card">
  <div class="card-header">
    <h3><i class="fa-solid fa-list-check" style="color:var(--royal-blue);"></i> Invoices Register</h3>
    <span class="badge badge-light">Showing <?= count($invoices) ?> of <?= number_format($totalRows) ?> records</span>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table">
        <thead>
          <tr>
            <th>Invoice #</th>
            <th>Customer & Account</th>
            <th>Zone</th>
            <th>Month</th>
            <th>Readings & m³</th>
            <th>Water Fee</th>
            <th>Sewer & Base</th>
            <th>Total Bill</th>
            <th>Payable</th>
            <th>Paid</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($invoices)): ?>
            <tr>
              <td colspan="12" class="text-center py-4" style="color:var(--text-muted);">
                <i class="fa-regular fa-folder-open" style="font-size:2rem;margin-bottom:0.5rem;display:block;"></i>
                No invoices found matching your criteria.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($invoices as $inv): 
              $balanceDue = max(0, (float)$inv['total_payable'] - (float)$inv['amount_paid']);
              $statusBadge = match($inv['status']) {
                'Paid' => 'badge-success',
                'Partial' => 'badge-warning',
                'Unpaid' => 'badge-danger',
                default => 'badge-light'
              };
            ?>
              <tr>
                <td>
                  <a href="<?= APP_URL ?>/modules/billing/invoice_view.php?id=<?= $inv['invoice_id'] ?>" style="font-weight:700;color:var(--royal-blue);">
                    <?= htmlspecialchars($inv['invoice_no']) ?>
                  </a>
                  <div style="font-size:0.75rem;color:var(--text-muted);"><?= formatDate($inv['invoice_date']) ?></div>
                </td>
                <td>
                  <strong><?= htmlspecialchars($inv['full_name']) ?></strong>
                  <div style="font-size:0.8rem;color:var(--text-muted);">
                    <i class="fa-solid fa-id-card"></i> <?= htmlspecialchars($inv['account_no']) ?> | 
                    <i class="fa-solid fa-gauge"></i> <?= htmlspecialchars($inv['meter_serial']) ?>
                  </div>
                </td>
                <td><span class="badge badge-light"><?= htmlspecialchars($inv['zone_code']) ?></span></td>
                <td><strong><?= htmlspecialchars($inv['billing_month']) ?></strong></td>
                <td>
                  <span style="font-weight:600;color:var(--navy);"><?= number_format($inv['consumption_m3'], 1) ?> m³</span>
                  <div style="font-size:0.75rem;color:var(--text-muted);">
                    <?= number_format($inv['previous_reading']) ?> &rarr; <?= number_format($inv['current_reading']) ?>
                  </div>
                </td>
                <td><?= formatCurrency($inv['water_charge']) ?></td>
                <td>
                  <div style="font-size:0.8rem;">
                    S: <?= formatCurrency($inv['sewer_charge']) ?><br>
                    B: <?= formatCurrency($inv['base_charge']) ?>
                  </div>
                </td>
                <td><strong><?= formatCurrency($inv['total_bill']) ?></strong></td>
                <td>
                  <strong style="color:var(--royal-blue);"><?= formatCurrency($inv['total_payable']) ?></strong>
                  <?php if ($inv['arrears_brought_forward'] > 0): ?>
                    <div style="font-size:0.75rem;color:var(--danger);">Arrears: <?= formatCurrency($inv['arrears_brought_forward']) ?></div>
                  <?php endif; ?>
                </td>
                <td style="color:var(--success);font-weight:600;"><?= formatCurrency($inv['amount_paid']) ?></td>
                <td><span class="badge <?= $statusBadge ?>"><?= htmlspecialchars($inv['status']) ?></span></td>
                <td>
                  <div style="display:flex;gap:0.4rem;">
                    <a href="<?= APP_URL ?>/modules/billing/invoice_view.php?id=<?= $inv['invoice_id'] ?>" class="btn btn-secondary btn-sm" title="View / Print Bill">
                      <i class="fa-solid fa-print"></i>
                    </a>
                    <?php if ($inv['status'] !== 'Paid'): ?>
                      <a href="<?= APP_URL ?>/modules/payments/add.php?customer_id=<?= $inv['customer_id'] ?>&invoice_id=<?= $inv['invoice_id'] ?>" class="btn btn-success btn-sm" title="Post Payment">
                        <i class="fa-solid fa-money-bill-wave"></i>
                      </a>
                    <?php endif; ?>
                  </div>
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
            <a href="?page=<?= $page - 1 ?>&billing_month=<?= urlencode($billingMonth) ?>&zone_id=<?= $zoneId ?>&status=<?= urlencode($status) ?>&search=<?= urlencode($search) ?>" class="page-link">&laquo; Prev</a>
          <?php endif; ?>
          <?php 
          $startPage = max(1, $page - 2);
          $endPage = min($totalPages, $page + 2);
          for ($p = $startPage; $p <= $endPage; $p++): 
          ?>
            <a href="?page=<?= $p ?>&billing_month=<?= urlencode($billingMonth) ?>&zone_id=<?= $zoneId ?>&status=<?= urlencode($status) ?>&search=<?= urlencode($search) ?>" class="page-link <?= $p == $page ? 'active' : '' ?>"><?= $p ?></a>
          <?php endfor; ?>
          <?php if ($page < $totalPages): ?>
            <a href="?page=<?= $page + 1 ?>&billing_month=<?= urlencode($billingMonth) ?>&zone_id=<?= $zoneId ?>&status=<?= urlencode($status) ?>&search=<?= urlencode($search) ?>" class="page-link">Next &raquo;</a>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
