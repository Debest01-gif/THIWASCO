<?php
// THIWASCO - Executive Dashboard
$pageTitle = 'Executive Dashboard';
$activePage = 'dashboard';

require_once __DIR__ . '/includes/header.php';

// ─── Dashboard Data Queries ───────────────────────────────────────────────
try {
    // KPI Stats
    $stats = [];

    // Total active customers
    $r = db()->fetch("SELECT COUNT(*) as cnt FROM customers WHERE status = 'Active'");
    $stats['active_customers'] = $r['cnt'] ?? 0;

    $r = db()->fetch("SELECT COUNT(*) as cnt FROM customers");
    $stats['total_customers'] = $r['cnt'] ?? 0;

    // Active meters
    $r = db()->fetch("SELECT COUNT(*) as cnt FROM meters WHERE status = 'Active'");
    $stats['active_meters'] = $r['cnt'] ?? 0;

    // Total billed this month
    $thisMonth = date('Y-m');
    $r = db()->fetch("SELECT COALESCE(SUM(total_payable),0) as total FROM invoices WHERE billing_month = ?", [$thisMonth]);
    $stats['billed_this_month'] = $r['total'] ?? 0;

    // Total collected this month
    $r = db()->fetch("SELECT COALESCE(SUM(amount),0) as total FROM payments WHERE DATE_FORMAT(payment_date,'%Y-%m') = ? AND is_reversed = 0", [$thisMonth]);
    $stats['collected_this_month'] = $r['total'] ?? 0;

    // Total outstanding arrears
    $r = db()->fetch("SELECT COALESCE(SUM(balance),0) as total FROM customers WHERE balance > 0");
    $stats['total_arrears'] = $r['total'] ?? 0;

    // Defaulters count
    $r = db()->fetch("SELECT COUNT(*) as cnt FROM customers WHERE balance > 0 AND status IN ('Active','Defaulter')");
    $stats['defaulters'] = $r['cnt'] ?? 0;

    // NRW Stats (latest month)
    $nrwData = db()->fetch("SELECT * FROM nrw_reports ORDER BY report_period DESC LIMIT 1");

    // Open inspections
    $r = db()->fetch("SELECT COUNT(*) as cnt FROM field_inspections WHERE status = 'Open'");
    $stats['open_inspections'] = $r['cnt'] ?? 0;

    // Revenue last 6 months (for chart)
    $revenueChart = db()->fetchAll(
        "SELECT DATE_FORMAT(payment_date,'%Y-%m') as month,
                COALESCE(SUM(amount),0) as collected
         FROM payments
         WHERE is_reversed=0 AND payment_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
         GROUP BY month ORDER BY month ASC"
    );

    // NRW trend last 6 months
    $nrwTrend = db()->fetchAll(
        "SELECT report_period, nrw_percent, total_production_m3, total_billed_m3
         FROM nrw_reports
         ORDER BY report_period DESC LIMIT 6"
    );
    $nrwTrend = array_reverse($nrwTrend);

    // Zone-wise customer count
    $zoneStats = db()->fetchAll(
        "SELECT z.zone_name, COUNT(c.customer_id) as customers,
                COALESCE(SUM(c.balance),0) as arrears
         FROM zones z
         LEFT JOIN customers c ON c.zone_id = z.zone_id AND c.status != 'Inactive'
         WHERE z.is_active = 1
         GROUP BY z.zone_id, z.zone_name"
    );

    // Recent payments
    $recentPayments = db()->fetchAll(
        "SELECT p.receipt_no, p.amount, p.payment_date, p.created_at,
                c.full_name, c.account_no,
                pm.method_name
         FROM payments p
         JOIN customers c ON p.customer_id = c.customer_id
         JOIN payment_methods pm ON p.payment_method_id = pm.method_id
         WHERE p.is_reversed = 0
         ORDER BY p.created_at DESC LIMIT 8"
    );

    // Top defaulters
    $topDefaulters = db()->fetchAll(
        "SELECT account_no, full_name, phone, balance, zone_id
         FROM customers WHERE balance > 0
         ORDER BY balance DESC LIMIT 8"
    );

    // Revenue protection summary
    $rpStats = db()->fetchAll(
        "SELECT status, COUNT(*) as cnt FROM field_inspections GROUP BY status"
    );
    $rpByStatus = array_column($rpStats, 'cnt', 'status');

} catch (Exception $e) {
    $stats = array_fill_keys(['active_customers','total_customers','active_meters','billed_this_month','collected_this_month','total_arrears','defaulters','open_inspections'], 0);
    $revenueChart = []; $nrwTrend = []; $zoneStats = []; $recentPayments = []; $topDefaulters = [];
    $nrwData = null; $rpByStatus = [];
}

$collectionRate = $stats['billed_this_month'] > 0
    ? round(($stats['collected_this_month'] / $stats['billed_this_month']) * 100, 1)
    : 0;

$currentNrw = $nrwData ? $nrwData['nrw_percent'] : null;
$nrwTarget   = (float) getSetting('nrw_target_percent', 20);

// Chart data
$chartMonths    = array_column($revenueChart, 'month');
$chartCollected = array_column($revenueChart, 'collected');
$nrwMonths  = array_column($nrwTrend, 'report_period');
$nrwPercents = array_column($nrwTrend, 'nrw_percent');
?>

<!-- ===== KPI STAT CARDS ===== -->
<div class="stats-grid">

  <div class="stat-card blue">
    <div class="stat-card-icon"><i class="fa-solid fa-users"></i></div>
    <div class="stat-card-label">Active Customers</div>
    <div class="stat-card-value"><?= number_format($stats['active_customers']) ?></div>
    <div class="stat-card-meta">
      <span><?= number_format($stats['total_customers']) ?> total registered</span>
    </div>
  </div>

  <div class="stat-card gold">
    <div class="stat-card-icon"><i class="fa-solid fa-file-invoice-dollar"></i></div>
    <div class="stat-card-label">Billed This Month</div>
    <div class="stat-card-value" style="font-size:1.3rem;"><?= formatCurrency($stats['billed_this_month']) ?></div>
    <div class="stat-card-meta"><?= date('F Y') ?></div>
  </div>

  <div class="stat-card green">
    <div class="stat-card-icon"><i class="fa-solid fa-money-bill-wave"></i></div>
    <div class="stat-card-label">Collected This Month</div>
    <div class="stat-card-value" style="font-size:1.3rem;"><?= formatCurrency($stats['collected_this_month']) ?></div>
    <div class="stat-card-meta">
      <span class="<?= $collectionRate >= 80 ? 'up' : 'down' ?>">
        <i class="fa-solid fa-circle-check"></i> <?= $collectionRate ?>% collection rate
      </span>
    </div>
  </div>

  <div class="stat-card red">
    <div class="stat-card-icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
    <div class="stat-card-label">Total Arrears</div>
    <div class="stat-card-value" style="font-size:1.2rem;"><?= formatCurrency($stats['total_arrears']) ?></div>
    <div class="stat-card-meta">
      <span class="down"><i class="fa-solid fa-user-xmark"></i> <?= number_format($stats['defaulters']) ?> defaulters</span>
    </div>
  </div>

  <div class="stat-card blue">
    <div class="stat-card-icon"><i class="fa-solid fa-gauge"></i></div>
    <div class="stat-card-label">Active Meters</div>
    <div class="stat-card-value"><?= number_format($stats['active_meters']) ?></div>
    <div class="stat-card-meta">Meters in service</div>
  </div>

  <?php
  $nrwClass = 'green';
  if ($currentNrw !== null) {
    if ($currentNrw > 40) $nrwClass = 'red';
    elseif ($currentNrw > $nrwTarget) $nrwClass = 'gold';
  }
  ?>
  <div class="stat-card <?= $nrwClass ?>">
    <div class="stat-card-icon"><i class="fa-solid fa-droplet-slash"></i></div>
    <div class="stat-card-label">Current NRW %</div>
    <div class="stat-card-value"><?= $currentNrw !== null ? number_format($currentNrw, 1) . '%' : 'N/A' ?></div>
    <div class="stat-card-meta">Target: ≤ <?= $nrwTarget ?>%</div>
  </div>

  <div class="stat-card purple">
    <div class="stat-card-icon"><i class="fa-solid fa-shield-halved"></i></div>
    <div class="stat-card-label">Open Inspections</div>
    <div class="stat-card-value"><?= number_format($stats['open_inspections']) ?></div>
    <div class="stat-card-meta">
      <a href="<?= APP_URL ?>/modules/revenue_protection/inspections.php" style="color:var(--primary-400);">View all →</a>
    </div>
  </div>

  <div class="stat-card gold">
    <div class="stat-card-icon"><i class="fa-solid fa-percent"></i></div>
    <div class="stat-card-label">Collection Rate</div>
    <div class="stat-card-value"><?= $collectionRate ?>%</div>
    <div class="stat-card-meta">
      <div class="progress-bar-container" style="margin:4px 0 0;">
        <div class="progress-bar-fill <?= $collectionRate >= 80 ? 'good' : ($collectionRate >= 60 ? 'warning' : 'danger') ?>"
             style="width:<?= min($collectionRate,100) ?>%"></div>
      </div>
    </div>
  </div>

</div>

<!-- ===== CHARTS ROW ===== -->
<div class="charts-grid">

  <!-- Revenue Trend Chart -->
  <div class="card">
    <div class="card-header">
      <div class="card-title"><span class="icon">📈</span> Revenue Collection Trend</div>
      <a href="<?= APP_URL ?>/modules/reports/revenue.php" class="btn btn-outline btn-sm">Full Report</a>
    </div>
    <div class="card-body">
      <div class="chart-container" style="height:260px;">
        <canvas id="revenueChart"></canvas>
      </div>
    </div>
  </div>

  <!-- NRW Trend Chart -->
  <div class="card">
    <div class="card-header">
      <div class="card-title"><span class="icon">💧</span> NRW Trend</div>
      <a href="<?= APP_URL ?>/modules/nrw/dashboard.php" class="btn btn-outline btn-sm">NRW Analysis</a>
    </div>
    <div class="card-body">
      <div class="chart-container" style="height:260px;">
        <canvas id="nrwChart"></canvas>
      </div>
    </div>
  </div>

</div>

<!-- ===== MIDDLE ROW ===== -->
<div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;margin-bottom:24px;">

  <!-- Revenue Protection Status -->
  <div class="card">
    <div class="card-header">
      <div class="card-title"><span class="icon">🛡️</span> Revenue Protection</div>
      <a href="<?= APP_URL ?>/modules/revenue_protection/index.php" class="btn btn-outline btn-sm">View</a>
    </div>
    <div class="card-body" style="padding:14px;">
      <?php
      $rpItems = [
        'Open'               => ['icon'=>'🔴', 'class'=>'danger'],
        'Under Investigation'=> ['icon'=>'🟡', 'class'=>'warning'],
        'Resolved'           => ['icon'=>'🟢', 'class'=>'success'],
        'Closed'             => ['icon'=>'⚫', 'class'=>'gray'],
      ];
      foreach ($rpItems as $status => $info): ?>
      <div style="display:flex;align-items:center;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--gray-100);">
        <span style="font-size:0.875rem;"><?= $info['icon'] ?> <?= $status ?></span>
        <span class="badge badge-<?= $info['class'] ?>"><?= $rpByStatus[$status] ?? 0 ?></span>
      </div>
      <?php endforeach; ?>
      <div style="margin-top:14px;">
        <a href="<?= APP_URL ?>/modules/revenue_protection/inspections.php?status=Open" class="btn btn-primary btn-sm btn-block">
          <i class="fa-solid fa-clipboard-check"></i> New Inspection
        </a>
      </div>
    </div>
  </div>

  <!-- Zone Summary -->
  <div class="card">
    <div class="card-header">
      <div class="card-title"><span class="icon">🗺️</span> Zone Summary</div>
    </div>
    <div class="card-body" style="padding:0;">
      <?php if ($zoneStats): ?>
      <div class="table-wrapper" style="max-height:260px;overflow-y:auto;">
        <table class="data-table" style="font-size:0.82rem;">
          <thead><tr><th>Zone</th><th>Customers</th><th>Arrears</th></tr></thead>
          <tbody>
          <?php foreach ($zoneStats as $z): ?>
            <tr>
              <td><strong><?= htmlspecialchars($z['zone_name']) ?></strong></td>
              <td><?= number_format($z['customers']) ?></td>
              <td style="color:<?= $z['arrears'] > 0 ? 'var(--danger-600)' : 'var(--gray-500)' ?>;">
                <?= formatCurrency($z['arrears']) ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php else: ?>
      <div class="empty-state" style="padding:30px;">
        <div class="icon">🗺️</div>
        <p>No zone data yet</p>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Quick Actions -->
  <div class="card">
    <div class="card-header">
      <div class="card-title"><span class="icon">⚡</span> Quick Actions</div>
    </div>
    <div class="card-body" style="padding:14px;">
      <div style="display:grid;gap:8px;">
        <a href="<?= APP_URL ?>/modules/customers/add.php" class="btn btn-outline btn-sm" style="justify-content:flex-start;gap:10px;">
          <i class="fa-solid fa-user-plus"></i> Register New Customer
        </a>
        <a href="<?= APP_URL ?>/modules/meters/readings.php" class="btn btn-outline btn-sm" style="justify-content:flex-start;gap:10px;">
          <i class="fa-solid fa-pencil"></i> Enter Meter Readings
        </a>
        <a href="<?= APP_URL ?>/modules/payments/add.php" class="btn btn-outline btn-sm" style="justify-content:flex-start;gap:10px;">
          <i class="fa-solid fa-money-bill"></i> Post Payment
        </a>
        <a href="<?= APP_URL ?>/modules/billing/generate.php" class="btn btn-gold btn-sm" style="justify-content:flex-start;gap:10px;">
          <i class="fa-solid fa-bolt"></i> Generate Bills
        </a>
        <a href="<?= APP_URL ?>/modules/nrw/production.php" class="btn btn-outline btn-sm" style="justify-content:flex-start;gap:10px;">
          <i class="fa-solid fa-faucet"></i> Record Water Production
        </a>
        <a href="<?= APP_URL ?>/modules/revenue_protection/inspections.php" class="btn btn-outline btn-sm" style="justify-content:flex-start;gap:10px;">
          <i class="fa-solid fa-clipboard-check"></i> Log Inspection
        </a>
        <a href="<?= APP_URL ?>/modules/reports/executive.php" class="btn btn-primary btn-sm" style="justify-content:flex-start;gap:10px;">
          <i class="fa-solid fa-chart-bar"></i> Executive Report
        </a>
      </div>
    </div>
  </div>

</div>

<!-- ===== BOTTOM ROW ===== -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:24px;">

  <!-- Recent Payments -->
  <div class="card">
    <div class="card-header">
      <div class="card-title"><span class="icon">💳</span> Recent Payments</div>
      <a href="<?= APP_URL ?>/modules/payments/index.php" class="btn btn-outline btn-sm">View All</a>
    </div>
    <div class="table-wrapper">
      <?php if ($recentPayments): ?>
      <table class="data-table">
        <thead><tr><th>Receipt</th><th>Customer</th><th>Method</th><th>Amount</th><th>Date</th></tr></thead>
        <tbody>
        <?php foreach ($recentPayments as $p): ?>
          <tr>
            <td><strong><?= htmlspecialchars($p['receipt_no']) ?></strong></td>
            <td>
              <div style="font-size:0.82rem;"><?= htmlspecialchars($p['full_name']) ?></div>
              <div style="font-size:0.73rem;color:var(--gray-500);"><?= htmlspecialchars($p['account_no']) ?></div>
            </td>
            <td><span class="badge badge-info"><?= htmlspecialchars($p['method_name']) ?></span></td>
            <td><strong style="color:var(--success-600);"><?= formatCurrency($p['amount']) ?></strong></td>
            <td style="font-size:0.82rem;"><?= formatDate($p['payment_date']) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <?php else: ?>
      <div class="empty-state" style="padding:40px;">
        <div class="icon">💳</div>
        <p>No payments recorded yet</p>
        <a href="<?= APP_URL ?>/modules/payments/add.php" class="btn btn-primary btn-sm">Post First Payment</a>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Top Defaulters -->
  <div class="card">
    <div class="card-header">
      <div class="card-title"><span class="icon">⚠️</span> Top Defaulters</div>
      <a href="<?= APP_URL ?>/modules/customers/defaulters.php" class="btn btn-outline btn-sm">Full List</a>
    </div>
    <div class="table-wrapper">
      <?php if ($topDefaulters): ?>
      <table class="data-table">
        <thead><tr><th>Account</th><th>Customer</th><th>Phone</th><th>Arrears</th></tr></thead>
        <tbody>
        <?php foreach ($topDefaulters as $d): ?>
          <tr>
            <td><strong><?= htmlspecialchars($d['account_no']) ?></strong></td>
            <td style="font-size:0.82rem;"><?= htmlspecialchars($d['full_name']) ?></td>
            <td style="font-size:0.82rem;"><?= htmlspecialchars($d['phone']) ?></td>
            <td><strong style="color:var(--danger-600);"><?= formatCurrency($d['balance']) ?></strong></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <?php else: ?>
      <div class="empty-state" style="padding:40px;">
        <div class="icon">✅</div>
        <h3>No Defaulters</h3>
        <p>All accounts are up to date!</p>
      </div>
      <?php endif; ?>
    </div>
  </div>

</div>

<!-- ===== CHARTS JAVASCRIPT ===== -->
<script>
Chart.defaults.font.family = 'Inter, sans-serif';
Chart.defaults.color = '#6b7280';

// Revenue Chart
const revenueCtx = document.getElementById('revenueChart');
if (revenueCtx) {
  new Chart(revenueCtx, {
    type: 'bar',
    data: {
      labels: <?= json_encode($chartMonths) ?>,
      datasets: [{
        label: 'KES Collected',
        data: <?= json_encode(array_map('floatval', $chartCollected)) ?>,
        backgroundColor: 'rgba(26,64,126,0.15)',
        borderColor: '#1a407e',
        borderWidth: 2,
        borderRadius: 6,
        fill: true,
      }]
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      plugins: {
        legend: { display: false },
        tooltip: {
          callbacks: {
            label: ctx => 'KES ' + ctx.raw.toLocaleString('en-KE', {minimumFractionDigits:2})
          }
        }
      },
      scales: {
        y: {
          beginAtZero: true,
          grid: { color: 'rgba(0,0,0,0.04)' },
          ticks: { callback: v => 'KES ' + (v/1000).toFixed(0) + 'K' }
        },
        x: { grid: { display: false } }
      }
    }
  });
}

// NRW Chart
const nrwCtx = document.getElementById('nrwChart');
if (nrwCtx) {
  const nrwTarget = <?= $nrwTarget ?>;
  new Chart(nrwCtx, {
    type: 'line',
    data: {
      labels: <?= json_encode($nrwMonths) ?>,
      datasets: [
        {
          label: 'NRW %',
          data: <?= json_encode(array_map('floatval', $nrwPercents)) ?>,
          borderColor: '#dc2626',
          backgroundColor: 'rgba(220,38,38,0.08)',
          borderWidth: 2.5,
          pointRadius: 5,
          pointBackgroundColor: '#dc2626',
          tension: 0.4, fill: true,
        },
        {
          label: 'Target (' + nrwTarget + '%)',
          data: Array(<?= count($nrwMonths) ?>).fill(nrwTarget),
          borderColor: '#22a05a',
          borderDash: [6, 3],
          borderWidth: 1.5,
          pointRadius: 0, fill: false,
        }
      ]
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      plugins: { legend: { position: 'bottom' } },
      scales: {
        y: {
          beginAtZero: true, max: 100,
          grid: { color: 'rgba(0,0,0,0.04)' },
          ticks: { callback: v => v + '%' }
        },
        x: { grid: { display: false } }
      }
    }
  });
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
