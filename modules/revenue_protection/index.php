<?php
$pageTitle = 'Revenue Protection Dashboard';
$activePage = 'rp_dashboard';
$activeSection = 'revenue';

require_once __DIR__ . '/../../includes/header.php';

// ─── Data ─────────────────────────────────────────────────────────────────
try {
    // Summary by status
    $statusSummary = db()->fetchAll(
        "SELECT status, COUNT(*) as count,
                COALESCE(SUM(estimated_revenue_loss),0) as total_loss,
                COALESCE(SUM(penalty_amount),0) as penalties
         FROM field_inspections GROUP BY status"
    );
    $byStatus = [];
    foreach ($statusSummary as $s) $byStatus[$s['status']] = $s;

    // Summary by type
    $typeSummary = db()->fetchAll(
        "SELECT it.type_name, COUNT(fi.inspection_id) as count,
                COALESCE(SUM(fi.estimated_revenue_loss),0) as total_loss
         FROM inspection_types it
         LEFT JOIN field_inspections fi ON fi.inspection_type_id = it.type_id
         GROUP BY it.type_id, it.type_name ORDER BY count DESC"
    );

    // Total metrics
    $totals = db()->fetch(
        "SELECT COUNT(*) as total_cases,
                COALESCE(SUM(estimated_revenue_loss),0) as total_revenue_loss,
                COALESCE(SUM(penalty_amount),0) as total_penalties,
                COALESCE(SUM(CASE WHEN penalty_invoiced=1 THEN penalty_amount ELSE 0 END),0) as penalties_invoiced,
                COALESCE(SUM(estimated_loss_m3),0) as total_loss_m3
         FROM field_inspections"
    );

    // Recent inspections
    $recentInspections = db()->fetchAll(
        "SELECT fi.*, it.type_name, z.zone_name,
                c.full_name, c.account_no,
                u.full_name as inspector_name
         FROM field_inspections fi
         JOIN inspection_types it ON fi.inspection_type_id = it.type_id
         JOIN zones z ON fi.zone_id = z.zone_id
         LEFT JOIN customers c ON fi.customer_id = c.customer_id
         JOIN users u ON fi.inspected_by = u.user_id
         ORDER BY fi.created_at DESC LIMIT 10"
    );

    // Zone-wise cases
    $zoneCases = db()->fetchAll(
        "SELECT z.zone_name, COUNT(fi.inspection_id) as cases,
                COUNT(CASE WHEN fi.status='Open' THEN 1 END) as open_cases
         FROM zones z
         LEFT JOIN field_inspections fi ON fi.zone_id = z.zone_id
         WHERE z.is_active=1
         GROUP BY z.zone_id, z.zone_name ORDER BY cases DESC"
    );

    // Monthly trend (last 6 months)
    $monthlyTrend = db()->fetchAll(
        "SELECT DATE_FORMAT(inspection_date,'%Y-%m') as month, COUNT(*) as cases
         FROM field_inspections
         WHERE inspection_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
         GROUP BY month ORDER BY month"
    );

    $zones = db()->fetchAll("SELECT zone_id, zone_name FROM zones WHERE is_active=1");
    $inspTypes = db()->fetchAll("SELECT * FROM inspection_types");

} catch (Exception $e) {
    $byStatus = []; $typeSummary = []; $recentInspections = [];
    $totals = ['total_cases'=>0,'total_revenue_loss'=>0,'total_penalties'=>0,'penalties_invoiced'=>0,'total_loss_m3'=>0];
    $zoneCases = []; $monthlyTrend = []; $zones = []; $inspTypes = [];
}

$statusConfig = [
    'Open'               => ['icon'=>'🔴','badge'=>'danger','label'=>'Open Cases'],
    'Under Investigation'=> ['icon'=>'🟡','badge'=>'warning','label'=>'Investigating'],
    'Resolved'           => ['icon'=>'🟢','badge'=>'success','label'=>'Resolved'],
    'Closed'             => ['icon'=>'⚫','badge'=>'gray','label'=>'Closed'],
    'Referred'           => ['icon'=>'🔵','badge'=>'info','label'=>'Referred'],
];
?>

<div class="page-header">
  <div class="page-header-left">
    <h2>Revenue Protection Dashboard</h2>
    <p>Monitor illegal connections, meter tampering, and enforcement actions</p>
  </div>
  <div class="page-header-actions">
    <a href="<?= APP_URL ?>/modules/revenue_protection/inspections.php" class="btn btn-primary">
      <i class="fa-solid fa-clipboard-check"></i> Log Inspection
    </a>
    <a href="<?= APP_URL ?>/modules/revenue_protection/disconnections.php" class="btn btn-outline">
      <i class="fa-solid fa-link-slash"></i> Disconnections
    </a>
    <a href="<?= APP_URL ?>/modules/reports/nrw.php" class="btn btn-outline">
      <i class="fa-solid fa-file-export"></i> Export
    </a>
  </div>
</div>

<!-- ===== METRIC CARDS ===== -->
<div class="stats-grid" style="margin-bottom:20px;">

  <div class="stat-card red">
    <div class="stat-card-icon"><i class="fa-solid fa-folder-open"></i></div>
    <div class="stat-card-label">Total Cases</div>
    <div class="stat-card-value"><?= number_format($totals['total_cases']) ?></div>
    <div class="stat-card-meta"><?= number_format($byStatus['Open']['count'] ?? 0) ?> open</div>
  </div>

  <div class="stat-card gold">
    <div class="stat-card-icon"><i class="fa-solid fa-coins"></i></div>
    <div class="stat-card-label">Est. Revenue Loss</div>
    <div class="stat-card-value" style="font-size:1.2rem;"><?= formatCurrency($totals['total_revenue_loss']) ?></div>
    <div class="stat-card-meta">Cumulative loss detected</div>
  </div>

  <div class="stat-card green">
    <div class="stat-card-icon"><i class="fa-solid fa-gavel"></i></div>
    <div class="stat-card-label">Total Penalties</div>
    <div class="stat-card-value" style="font-size:1.2rem;"><?= formatCurrency($totals['total_penalties']) ?></div>
    <div class="stat-card-meta"><?= formatCurrency($totals['penalties_invoiced']) ?> invoiced</div>
  </div>

  <div class="stat-card blue">
    <div class="stat-card-icon"><i class="fa-solid fa-droplet-slash"></i></div>
    <div class="stat-card-label">Est. Water Loss (m³)</div>
    <div class="stat-card-value"><?= number_format($totals['total_loss_m3'], 0) ?></div>
    <div class="stat-card-meta">From all cases detected</div>
  </div>

</div>

<!-- STATUS BREAKDOWN -->
<div style="display:grid;grid-template-columns:repeat(5,1fr);gap:12px;margin-bottom:24px;">
  <?php foreach ($statusConfig as $status => $cfg): ?>
  <div style="background:var(--white);border-radius:10px;padding:14px 16px;border:1px solid var(--gray-200);text-align:center;cursor:pointer;transition:var(--transition);"
       onclick="window.location='<?= APP_URL ?>/modules/revenue_protection/inspections.php?status=<?= urlencode($status) ?>'">
    <div style="font-size:1.6rem;margin-bottom:8px;"><?= $cfg['icon'] ?></div>
    <div style="font-size:1.5rem;font-weight:800;color:var(--primary-800);font-family:'Outfit',sans-serif;">
      <?= $byStatus[$status]['count'] ?? 0 ?>
    </div>
    <div style="font-size:0.75rem;font-weight:600;color:var(--gray-500);margin-top:4px;"><?= $cfg['label'] ?></div>
  </div>
  <?php endforeach; ?>
</div>

<!-- ===== MIDDLE ROW: Type Summary + Zone Cases ===== -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:24px;">

  <!-- By Violation Type -->
  <div class="card">
    <div class="card-header">
      <div class="card-title"><span class="icon">📋</span> Cases by Violation Type</div>
      <a href="<?= APP_URL ?>/modules/revenue_protection/inspections.php" class="btn btn-outline btn-sm">View All</a>
    </div>
    <div class="table-wrapper">
      <table class="data-table">
        <thead><tr><th>Type</th><th>Cases</th><th>Est. Revenue Loss</th></tr></thead>
        <tbody>
        <?php foreach ($typeSummary as $t): ?>
          <tr>
            <td><strong><?= htmlspecialchars($t['type_name']) ?></strong></td>
            <td>
              <span class="badge badge-<?= $t['count'] > 10 ? 'danger' : ($t['count'] > 5 ? 'warning' : 'info') ?>">
                <?= $t['count'] ?>
              </span>
            </td>
            <td style="color:<?= $t['total_loss'] > 0 ? 'var(--danger-600)' : 'var(--gray-500)' ?>;font-weight:600;">
              <?= formatCurrency($t['total_loss']) ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Zone Cases -->
  <div class="card">
    <div class="card-header">
      <div class="card-title"><span class="icon">🗺️</span> Cases by Zone</div>
    </div>
    <div class="card-body">
      <?php foreach ($zoneCases as $z):
        $maxCases = max(array_column($zoneCases,'cases') ?: [1]);
        $pct = $maxCases > 0 ? round($z['cases'] / $maxCases * 100) : 0;
      ?>
      <div style="margin-bottom:12px;">
        <div style="display:flex;justify-content:space-between;font-size:0.83rem;margin-bottom:4px;">
          <span><?= htmlspecialchars($z['zone_name']) ?></span>
          <span>
            <strong><?= $z['cases'] ?></strong> cases
            <?php if ($z['open_cases'] > 0): ?>
              <span class="badge badge-danger" style="margin-left:4px;"><?= $z['open_cases'] ?> open</span>
            <?php endif; ?>
          </span>
        </div>
        <div class="progress-bar-container">
          <div class="progress-bar-fill <?= $z['open_cases'] > 3 ? 'danger' : ($z['cases'] > 5 ? 'warning' : 'good') ?>"
               style="width:<?= $pct ?>%"></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

</div>

<!-- ===== RECENT INSPECTIONS TABLE ===== -->
<div class="card">
  <div class="card-header">
    <div class="card-title"><span class="icon">🔍</span> Recent Inspections</div>
    <div style="display:flex;gap:8px;">
      <a href="<?= APP_URL ?>/modules/revenue_protection/inspections.php" class="btn btn-primary btn-sm">
        <i class="fa-solid fa-plus"></i> New Inspection
      </a>
      <a href="<?= APP_URL ?>/modules/revenue_protection/inspections.php" class="btn btn-outline btn-sm">View All</a>
    </div>
  </div>
  <div class="table-wrapper">
    <?php if ($recentInspections): ?>
    <table class="data-table">
      <thead>
        <tr>
          <th>Ref #</th>
          <th>Date</th>
          <th>Type</th>
          <th>Customer</th>
          <th>Zone</th>
          <th>Inspector</th>
          <th>Est. Loss</th>
          <th>Penalty</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($recentInspections as $insp): ?>
        <tr>
          <td><strong style="color:var(--primary-600);"><?= htmlspecialchars($insp['inspection_ref']) ?></strong></td>
          <td style="font-size:0.82rem;"><?= formatDate($insp['inspection_date']) ?></td>
          <td>
            <span class="badge badge-warning" style="font-size:0.72rem;">
              <?= htmlspecialchars($insp['type_name']) ?>
            </span>
          </td>
          <td>
            <?php if ($insp['full_name']): ?>
            <div style="font-size:0.82rem;"><?= htmlspecialchars($insp['full_name']) ?></div>
            <div style="font-size:0.73rem;color:var(--gray-500);"><?= htmlspecialchars($insp['account_no'] ?? '') ?></div>
            <?php else: ?>
            <span style="color:var(--gray-400);font-size:0.82rem;">Unknown / Illegal</span>
            <?php endif; ?>
          </td>
          <td style="font-size:0.82rem;"><?= htmlspecialchars($insp['zone_name']) ?></td>
          <td style="font-size:0.82rem;"><?= htmlspecialchars($insp['inspector_name']) ?></td>
          <td style="font-size:0.82rem;color:var(--danger-600);font-weight:600;">
            <?= $insp['estimated_revenue_loss'] ? formatCurrency($insp['estimated_revenue_loss']) : '-' ?>
          </td>
          <td style="font-size:0.82rem;font-weight:600;">
            <?= $insp['penalty_amount'] > 0 ? formatCurrency($insp['penalty_amount']) : '-' ?>
            <?php if ($insp['penalty_invoiced']): ?>
              <span class="badge badge-success" style="font-size:0.68rem;">Invoiced</span>
            <?php endif; ?>
          </td>
          <td>
            <span class="badge badge-<?= $statusConfig[$insp['status']]['badge'] ?? 'gray' ?>">
              <?= htmlspecialchars($insp['status']) ?>
            </span>
          </td>
          <td>
            <a href="<?= APP_URL ?>/modules/revenue_protection/view.php?id=<?= $insp['inspection_id'] ?>"
               class="table-action-btn view" title="View">
              <i class="fa-solid fa-eye"></i>
            </a>
            <a href="<?= APP_URL ?>/modules/revenue_protection/edit.php?id=<?= $insp['inspection_id'] ?>"
               class="table-action-btn edit" title="Update">
              <i class="fa-solid fa-pen"></i>
            </a>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php else: ?>
    <div class="empty-state" style="padding:50px;">
      <div class="icon"><i class="fa-solid fa-shield-halved"></i></div>
      <h3>No Inspections Recorded</h3>
      <p>Start logging field inspections to track revenue protection activities.</p>
      <a href="<?= APP_URL ?>/modules/revenue_protection/inspections.php" class="btn btn-primary">
        <i class="fa-solid fa-clipboard-check"></i> Log First Inspection
      </a>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
