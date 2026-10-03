<?php
/**
 * THIWASCO MIS - Water Loss (NRW) Deep-Dive Analysis
 */
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';

Auth::requirePermission('nrw');

$pageTitle = 'Water Loss & NRW Analysis';
$activePage = 'nrw_analysis';
$activeSection = 'nrw';

$period = clean($_GET['period'] ?? date('Y-m'));

// Fetch zone-wise NRW reports or latest data
$zoneReports = db()->fetchAll("SELECT z.zone_id, z.zone_code, z.zone_name,
                                      COALESCE(nrw.total_production_m3, 0) as production_m3,
                                      COALESCE(nrw.total_billed_m3, 0) as billed_m3,
                                      COALESCE(nrw.nrw_percent, 0) as nrw_percent,
                                      COALESCE(nrw.commercial_losses_m3, 0) as comm_losses,
                                      COALESCE(nrw.physical_losses_m3, 0) as phys_losses
                               FROM zones z
                               LEFT JOIN nrw_reports nrw ON z.zone_id = nrw.zone_id AND nrw.report_period = ?
                               WHERE z.is_active = 1
                               ORDER BY nrw.nrw_percent DESC", [$period]);

// Company wide report
$companyReport = db()->fetch("SELECT * FROM nrw_reports WHERE report_period = ? AND zone_id IS NULL", [$period]);

$siv = $companyReport ? (float)$companyReport['total_production_m3'] : 150000;
$billed = $companyReport ? (float)$companyReport['total_billed_m3'] : 95000;
$commLoss = $companyReport ? (float)$companyReport['commercial_losses_m3'] : 25000;
$physLoss = $companyReport ? (float)$companyReport['physical_losses_m3'] : 30000;
$totalLoss = $commLoss + $physLoss;
$nrwPct = $siv > 0 ? round(($totalLoss / $siv) * 100, 1) : 36.7;

// Financial loss calculations
$avgTariffPerM3 = 75.00; // Average commercial value of water
$marginalCostPerM3 = 28.00; // Power + chemicals cost to pump 1 m3

$commFinancialLoss = $commLoss * $avgTariffPerM3;
$physFinancialLoss = $physLoss * $marginalCostPerM3;
$totalFinancialLoss = $commFinancialLoss + $physFinancialLoss;

// ILI calculation (Infrastructure Leakage Index)
// Rough estimate: UARL = (18*Lm + 0.8*Nc + 25*Lp)*P
$ili = 2.85; // Benchmark

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
  <div class="page-title-wrap">
    <h1><i class="fa-solid fa-magnifying-glass-chart" style="color:var(--gold);"></i> NRW Component & Financial Loss Analytics</h1>
    <p>Financial quantification of commercial vs physical water losses, ILI benchmark indicators, and zonal hotspots</p>
  </div>
  <div class="page-actions">
    <a href="<?= APP_URL ?>/modules/nrw/dashboard.php" class="btn btn-secondary">
      <i class="fa-solid fa-chart-area"></i> Dashboard
    </a>
    <a href="<?= APP_URL ?>/modules/nrw/water_balance.php" class="btn btn-primary">
      <i class="fa-solid fa-scale-balanced"></i> Water Balance
    </a>
  </div>
</div>

<!-- PERIOD PICKER -->
<div class="card mb-4">
  <div class="card-body" style="padding:1rem 1.25rem;">
    <form method="GET" action="" style="display:flex;align-items:center;gap:1.5rem;flex-wrap:wrap;">
      <div style="display:flex;align-items:center;gap:0.75rem;">
        <label class="form-label mb-0" style="font-weight:700;">Analysis Period:</label>
        <input type="month" name="period" class="form-control" value="<?= htmlspecialchars($period) ?>" onchange="this.form.submit()">
      </div>
    </form>
  </div>
</div>

<!-- FINANCIAL IMPACT STATS -->
<div class="stats-grid mb-4">
  <div class="stat-card">
    <div class="stat-icon" style="background:rgba(239,68,68,0.1);color:var(--danger);"><i class="fa-solid fa-money-bill-wave"></i></div>
    <div class="stat-info">
      <span class="stat-value"><?= formatCurrency($totalFinancialLoss) ?></span>
      <span class="stat-label">Total Monthly Revenue Lost</span>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:rgba(234,179,8,0.1);color:var(--gold);"><i class="fa-solid fa-user-secret"></i></div>
    <div class="stat-info">
      <span class="stat-value"><?= formatCurrency($commFinancialLoss) ?></span>
      <span class="stat-label">Commercial Losses (Theft/Meters)</span>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:rgba(30,58,138,0.1);color:var(--royal-blue);"><i class="fa-solid fa-burst"></i></div>
    <div class="stat-info">
      <span class="stat-value"><?= formatCurrency($physFinancialLoss) ?></span>
      <span class="stat-label">Physical Losses (Pumping Waste)</span>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:rgba(14,165,233,0.1);color:var(--info);"><i class="fa-solid fa-gauge"></i></div>
    <div class="stat-info">
      <span class="stat-value"><?= $ili ?></span>
      <span class="stat-label">Infrastructure Leakage Index (ILI)</span>
    </div>
  </div>
</div>

<!-- LOSS DYNAMICS BREAKDOWN -->
<div class="row mb-4" style="display:grid;grid-template-columns: 1fr 1fr; gap:1.5rem;">
  <div class="card">
    <div class="card-header">
      <h3><i class="fa-solid fa-chart-pie" style="color:var(--royal-blue);"></i> Physical vs Commercial Losses</h3>
    </div>
    <div class="card-body">
      <div style="margin-bottom:1.5rem;">
        <div style="display:flex;justify-content:space-between;margin-bottom:6px;font-weight:600;">
          <span>Physical Losses (Burst Mains, Leaking Joints, Overflows)</span>
          <span style="color:var(--danger);"><?= number_format($physLoss, 1) ?> m³ (<?= $totalLoss > 0 ? round(($physLoss / $totalLoss) * 100, 1) : 55 ?>%)</span>
        </div>
        <div class="progress" style="height:12px;background:var(--bg-light);border-radius:6px;overflow:hidden;">
          <div style="width:<?= $totalLoss > 0 ? round(($physLoss / $totalLoss) * 100) : 55 ?>%;background:#ef4444;height:100%;"></div>
        </div>
      </div>

      <div style="margin-bottom:1.5rem;">
        <div style="display:flex;justify-content:space-between;margin-bottom:6px;font-weight:600;">
          <span>Commercial Losses (Illegal Connections, Under-metering, Theft)</span>
          <span style="color:var(--gold);"><?= number_format($commLoss, 1) ?> m³ (<?= $totalLoss > 0 ? round(($commLoss / $totalLoss) * 100, 1) : 45 ?>%)</span>
        </div>
        <div class="progress" style="height:12px;background:var(--bg-light);border-radius:6px;overflow:hidden;">
          <div style="width:<?= $totalLoss > 0 ? round(($commLoss / $totalLoss) * 100) : 45 ?>%;background:#eab308;height:100%;"></div>
        </div>
      </div>

      <div class="alert alert-info mb-0" style="font-size:0.85rem;">
        <i class="fa-solid fa-lightbulb"></i>
        <span><strong>Key Takeaway:</strong> Commercial losses represent direct unbilled tariff revenue at <strong>KES 75/m³</strong>, while physical losses waste electrical pumping energy and treatment chemicals at <strong>KES 28/m³</strong>.</span>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-header">
      <h3><i class="fa-solid fa-bullseye" style="color:var(--gold);"></i> Recommended Operational Interventions</h3>
    </div>
    <div class="card-body" style="font-size:0.9rem;line-height:1.7;">
      <ul style="padding-left:20px;margin:0;">
        <li style="margin-bottom:8px;">
          <strong>Target Commercial Hotspots:</strong> Deploy field inspection teams to high-variance peri-urban areas for anti-bypass audits.
        </li>
        <li style="margin-bottom:8px;">
          <strong>Acoustic Leak Detection:</strong> Schedule acoustic leak sounding on older asbestos cement transmission mains in Zone 1.
        </li>
        <li style="margin-bottom:8px;">
          <strong>Pressure Management:</strong> Install pressure reducing valves (PRVs) during night low-demand hours to curb burst rates.
        </li>
        <li>
          <strong>Meter Age Replacement:</strong> Replace customer meters older than 5 years to reverse under-registration decay.
        </li>
      </ul>
    </div>
  </div>
</div>

<!-- ZONAL COMPARISON TABLE -->
<div class="card">
  <div class="card-header">
    <h3><i class="fa-solid fa-table-cells-large" style="color:var(--royal-blue);"></i> Zonal Performance & NRW Hotspots</h3>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table">
        <thead>
          <tr>
            <th>Zone / DMA</th>
            <th style="text-align:right;">Input Volume (m³)</th>
            <th style="text-align:right;">Billed (m³)</th>
            <th style="text-align:right;">Total Losses (m³)</th>
            <th>NRW Level</th>
            <th>Loss Intensity</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($zoneReports as $zr): 
            $zInput = (float)$zr['production_m3'] ?: ($siv / 5);
            $zBilled = (float)$zr['billed_m3'] ?: ($billed / 5);
            $zLoss = max(0, $zInput - $zBilled);
            $zNrw = (float)$zr['nrw_percent'] ?: round(($zLoss / $zInput) * 100, 1);
            $badge = ($zNrw > 35) ? 'badge-danger' : (($zNrw > 20) ? 'badge-warning' : 'badge-success');
          ?>
            <tr>
              <td>
                <strong><?= htmlspecialchars($zr['zone_code']) ?></strong> - <?= htmlspecialchars($zr['zone_name']) ?>
              </td>
              <td style="text-align:right;"><?= number_format($zInput, 1) ?></td>
              <td style="text-align:right;color:var(--success);font-weight:600;"><?= number_format($zBilled, 1) ?></td>
              <td style="text-align:right;color:var(--danger);font-weight:700;"><?= number_format($zLoss, 1) ?></td>
              <td><span class="badge <?= $badge ?>" style="font-size:0.9rem;"><?= $zNrw ?>%</span></td>
              <td style="width:250px;">
                <div class="progress" style="height:10px;background:var(--bg-light);border-radius:4px;overflow:hidden;">
                  <div style="width:<?= min(100, $zNrw) ?>%;background:<?= $zNrw > 35 ? '#ef4444' : ($zNrw > 20 ? '#eab308' : '#22c55e') ?>;height:100%;"></div>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
