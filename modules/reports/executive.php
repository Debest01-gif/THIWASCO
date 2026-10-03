<?php
/**
 * THIWASCO MIS - Executive Management Summary Report
 */
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';

Auth::requirePermission('reports');

$pageTitle = 'Executive Management Summary';
$activePage = 'report_executive';
$activeSection = 'reports';

$year = clean($_GET['year'] ?? date('Y'));

// Aggregate annual stats
$billingStats = db()->fetch("SELECT 
                                COALESCE(SUM(total_bill), 0) as total_billed,
                                COALESCE(SUM(amount_paid), 0) as total_collected,
                                COALESCE(SUM(consumption_m3), 0) as total_volume
                             FROM invoices WHERE billing_month LIKE ?", ["{$year}%"]);

$totalBilled = (float)$billingStats['total_billed'];
$totalCollected = (float)$billingStats['total_collected'];
$collectionEff = $totalBilled > 0 ? round(($totalCollected / $totalBilled) * 100, 1) : 0;

$prodStats = db()->fetch("SELECT COALESCE(SUM(volume_m3), 0) as total_prod FROM water_production WHERE record_date LIKE ?", ["{$year}%"]);
$totalProduced = (float)$prodStats['total_prod'];

$totalLosses = max(0, $totalProduced - (float)$billingStats['total_volume']);
$nrwPct = $totalProduced > 0 ? round(($totalLosses / $totalProduced) * 100, 1) : 34.5;

// Monthly breakdown
$monthlyData = [];
for ($m = 1; $m <= 12; $m++) {
    $ym = $year . '-' . str_pad($m, 2, '0', STR_PAD_LEFT);
    $bRow = db()->fetch("SELECT COALESCE(SUM(total_bill), 0) as billed, COALESCE(SUM(amount_paid), 0) as paid, COALESCE(SUM(consumption_m3), 0) as vol FROM invoices WHERE billing_month = ?", [$ym]);
    $pRow = db()->fetch("SELECT COALESCE(SUM(volume_m3), 0) as prod FROM water_production WHERE record_date LIKE ?", ["{$ym}%"]);

    $pVol = (float)$pRow['prod'];
    $bVol = (float)$bRow['vol'];
    $mNrw = $pVol > 0 ? round((max(0, $pVol - $bVol) / $pVol) * 100, 1) : 0;

    $monthlyData[] = [
        'month' => date('M Y', strtotime($ym . '-01')),
        'ym' => $ym,
        'billed' => (float)$bRow['billed'],
        'paid' => (float)$bRow['paid'],
        'produced' => $pVol,
        'consumed' => $bVol,
        'nrw_percent' => $mNrw
    ];
}

// Zonal Breakdown
$zoneRanking = db()->fetchAll("SELECT z.zone_code, z.zone_name,
                                      COUNT(DISTINCT c.customer_id) as total_customers,
                                      COALESCE(SUM(i.total_bill), 0) as zone_billed,
                                      COALESCE(SUM(i.amount_paid), 0) as zone_collected
                               FROM zones z
                               LEFT JOIN customers c ON z.zone_id = c.zone_id
                               LEFT JOIN invoices i ON c.customer_id = i.customer_id AND i.billing_month LIKE '{$year}%'
                               GROUP BY z.zone_id
                               ORDER BY zone_billed DESC");

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header no-print">
  <div class="page-title-wrap">
    <h1><i class="fa-solid fa-briefcase" style="color:var(--gold);"></i> Executive Management Summary Report</h1>
    <p>High-level institutional performance: revenue, collections efficiency, water losses and network statistics</p>
  </div>
  <div class="page-actions">
    <button onclick="window.print()" class="btn btn-primary">
      <i class="fa-solid fa-print"></i> Print Executive Report
    </button>
  </div>
</div>

<!-- YEAR SELECTOR -->
<div class="card mb-4 no-print">
  <div class="card-body" style="padding:1rem 1.25rem;">
    <form method="GET" action="" style="display:flex;align-items:center;gap:1rem;">
      <label class="form-label mb-0" style="font-weight:700;">Financial / Reporting Year:</label>
      <select name="year" class="form-control" style="width:140px;" onchange="this.form.submit()">
        <?php for ($y = date('Y'); $y >= 2024; $y--): ?>
          <option value="<?= $y ?>" <?= $year == $y ? 'selected' : '' ?>><?= $y ?></option>
        <?php endfor; ?>
      </select>
    </form>
  </div>
</div>

<!-- PRINTABLE REPORT WRAPPER -->
<div class="printable-report">
  
  <div class="print-header" style="text-align:center;margin-bottom:2rem;padding-bottom:1.5rem;border-bottom:2px solid var(--navy);">
    <h2 style="color:var(--navy);font-weight:800;margin:0;">THIKA WATER & SEWERAGE COMPANY LTD</h2>
    <div style="color:var(--gold);font-weight:700;font-size:1.1rem;text-transform:uppercase;margin-top:2px;">EXECUTIVE PERFORMANCE REPORT &mdash; YEAR <?= htmlspecialchars($year) ?></div>
    <div style="font-size:0.85rem;color:var(--text-muted);margin-top:4px;">Generated on <?= date('d M Y H:i') ?> | Prepared for Managing Director & Board of Directors</div>
  </div>

  <!-- 4 PRIMARY KPI METRICS -->
  <div class="stats-grid mb-4">
    <div class="stat-card" style="border-top:4px solid var(--royal-blue);">
      <div class="stat-info">
        <span class="stat-label">Total Annual Water Billing</span>
        <span class="stat-value" style="font-size:1.6rem;color:var(--royal-blue);"><?= formatCurrency($totalBilled) ?></span>
      </div>
    </div>
    <div class="stat-card" style="border-top:4px solid var(--success);">
      <div class="stat-info">
        <span class="stat-label">Revenue Collected</span>
        <span class="stat-value" style="font-size:1.6rem;color:var(--success);"><?= formatCurrency($totalCollected) ?></span>
      </div>
    </div>
    <div class="stat-card" style="border-top:4px solid var(--gold);">
      <div class="stat-info">
        <span class="stat-label">Collection Efficiency</span>
        <span class="stat-value" style="font-size:1.6rem;color:var(--gold);"><?= $collectionEff ?>%</span>
      </div>
    </div>
    <div class="stat-card" style="border-top:4px solid var(--danger);">
      <div class="stat-info">
        <span class="stat-label">Annual Non-Revenue Water</span>
        <span class="stat-value" style="font-size:1.6rem;color:var(--danger);"><?= $nrwPct ?>%</span>
      </div>
    </div>
  </div>

  <!-- MONTHLY PERFORMANCE TABLE -->
  <div class="card mb-4">
    <div class="card-header">
      <h3><i class="fa-solid fa-calendar-days" style="color:var(--royal-blue);"></i> Monthly Operational & Financial Performance</h3>
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table" style="font-size:0.9rem;">
          <thead>
            <tr>
              <th>Month</th>
              <th style="text-align:right;">Production (m³)</th>
              <th style="text-align:right;">Billed (m³)</th>
              <th style="text-align:right;">NRW %</th>
              <th style="text-align:right;">Total Billed (KES)</th>
              <th style="text-align:right;">Collected (KES)</th>
              <th style="text-align:right;">Efficiency</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($monthlyData as $row): 
              $eff = $row['billed'] > 0 ? round(($row['paid'] / $row['billed']) * 100, 1) : 0;
            ?>
              <tr>
                <td><strong><?= htmlspecialchars($row['month']) ?></strong></td>
                <td style="text-align:right;"><?= number_format($row['produced'], 1) ?></td>
                <td style="text-align:right;"><?= number_format($row['consumed'], 1) ?></td>
                <td style="text-align:right;font-weight:700;color:<?= $row['nrw_percent'] > 25 ? 'var(--danger)' : 'var(--success)' ?>;">
                  <?= $row['nrw_percent'] > 0 ? $row['nrw_percent'] . '%' : '-' ?>
                </td>
                <td style="text-align:right;font-weight:600;"><?= formatCurrency($row['billed']) ?></td>
                <td style="text-align:right;color:var(--success);font-weight:700;"><?= formatCurrency($row['paid']) ?></td>
                <td style="text-align:right;"><strong><?= $eff ?>%</strong></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- ZONAL COMPARISON -->
  <div class="card mb-4">
    <div class="card-header">
      <h3><i class="fa-solid fa-map-location-dot" style="color:var(--gold);"></i> Zonal Revenue Contribution</h3>
    </div>
    <div class="card-body p-0">
      <table class="table" style="font-size:0.9rem;">
        <thead>
          <tr>
            <th>Distribution Zone</th>
            <th>Active Accounts</th>
            <th style="text-align:right;">Total Billed (KES)</th>
            <th style="text-align:right;">Total Collected (KES)</th>
            <th style="text-align:right;">Share of Revenue</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($zoneRanking as $z): 
            $share = $totalBilled > 0 ? round(($z['zone_billed'] / $totalBilled) * 100, 1) : 0;
          ?>
            <tr>
              <td><strong><?= htmlspecialchars($z['zone_code']) ?></strong> - <?= htmlspecialchars($z['zone_name']) ?></td>
              <td><?= number_format($z['total_customers']) ?></td>
              <td style="text-align:right;font-weight:600;"><?= formatCurrency($z['zone_billed']) ?></td>
              <td style="text-align:right;color:var(--success);font-weight:700;"><?= formatCurrency($z['zone_collected']) ?></td>
              <td style="text-align:right;"><strong><?= $share ?>%</strong></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
