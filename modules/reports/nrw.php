<?php
/**
 * THIWASCO MIS - Non-Revenue Water (NRW) Performance Report
 */
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';

Auth::requirePermission('reports');

$pageTitle = 'NRW Performance Audit Report';
$activePage = 'report_nrw';
$activeSection = 'reports';

$year = clean($_GET['year'] ?? date('Y'));
$wasrebTarget = (float)getSetting('nrw_target_percent', 20.0);

// 12-month NRW trend
$monthlyTrend = [];
for ($m = 1; $m <= 12; $m++) {
    $ym = $year . '-' . str_pad($m, 2, '0', STR_PAD_LEFT);
    $p = db()->fetch("SELECT COALESCE(SUM(volume_m3), 0) as prod FROM water_production WHERE record_date LIKE ?", ["{$ym}%"]);
    $b = db()->fetch("SELECT COALESCE(SUM(consumption_m3), 0) as billed FROM invoices WHERE billing_month = ?", [$ym]);

    $prodVol = (float)$p['prod'];
    $billVol = (float)$b['billed'];
    $losses = max(0, $prodVol - $billVol);
    $nrwPct = $prodVol > 0 ? round(($losses / $prodVol) * 100, 1) : 0;

    $monthlyTrend[] = [
        'month' => date('M Y', strtotime($ym . '-01')),
        'production' => $prodVol,
        'billed' => $billVol,
        'losses' => $losses,
        'nrw_percent' => $nrwPct
    ];
}

// Annual summary
$annProd = array_sum(array_column($monthlyTrend, 'production'));
$annBilled = array_sum(array_column($monthlyTrend, 'billed'));
$annLosses = array_sum(array_column($monthlyTrend, 'losses'));
$annNrwPct = $annProd > 0 ? round(($annLosses / $annProd) * 100, 1) : 0;

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header no-print">
  <div class="page-title-wrap">
    <h1><i class="fa-solid fa-water" style="color:var(--gold);"></i> Water Loss & NRW Audit Report</h1>
    <p>Official WASREB performance indicators, systemic water balance verification, and loss reduction audit</p>
  </div>
  <div class="page-actions">
    <button onclick="window.print()" class="btn btn-primary">
      <i class="fa-solid fa-print"></i> Print NRW Report
    </button>
  </div>
</div>

<div class="card mb-4 no-print">
  <div class="card-body" style="padding:1rem 1.25rem;">
    <form method="GET" action="" style="display:flex;align-items:center;gap:1rem;">
      <label class="form-label mb-0" style="font-weight:700;">Audit Year:</label>
      <select name="year" class="form-control" style="width:140px;" onchange="this.form.submit()">
        <?php for ($y = date('Y'); $y >= 2024; $y--): ?>
          <option value="<?= $y ?>" <?= $year == $y ? 'selected' : '' ?>><?= $y ?></option>
        <?php endfor; ?>
      </select>
    </form>
  </div>
</div>

<div class="printable-report">
  <div class="stats-grid mb-4">
    <div class="stat-card">
      <div class="stat-info">
        <span class="stat-label">Total Water Abstracted / SIV</span>
        <span class="stat-value" style="color:var(--royal-blue);"><?= number_format($annProd, 1) ?> m³</span>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-info">
        <span class="stat-label">Billed Authorised Volume</span>
        <span class="stat-value" style="color:var(--success);"><?= number_format($annBilled, 1) ?> m³</span>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-info">
        <span class="stat-label">System Water Losses</span>
        <span class="stat-value" style="color:var(--danger);"><?= number_format($annLosses, 1) ?> m³</span>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-info">
        <span class="stat-label">Annual NRW Level</span>
        <span class="stat-value" style="color:<?= $annNrwPct > $wasrebTarget ? 'var(--danger)' : 'var(--success)' ?>;">
          <?= $annNrwPct ?>%
        </span>
        <small class="text-muted">Target: &le;<?= $wasrebTarget ?>%</small>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-header">
      <h3><i class="fa-solid fa-table-list" style="color:var(--royal-blue);"></i> Monthly Water Balance Breakdown — <?= htmlspecialchars($year) ?></h3>
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table">
          <thead>
            <tr>
              <th>Month</th>
              <th style="text-align:right;">System Input Volume (m³)</th>
              <th style="text-align:right;">Billed Consumption (m³)</th>
              <th style="text-align:right;">Water Losses (m³)</th>
              <th style="text-align:right;">NRW %</th>
              <th>Compliance Status</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($monthlyTrend as $t): 
              $compliant = ($t['nrw_percent'] <= $wasrebTarget && $t['nrw_percent'] > 0);
            ?>
              <tr>
                <td><strong><?= htmlspecialchars($t['month']) ?></strong></td>
                <td style="text-align:right;"><?= number_format($t['production'], 1) ?></td>
                <td style="text-align:right;color:var(--success);font-weight:600;"><?= number_format($t['billed'], 1) ?></td>
                <td style="text-align:right;color:var(--danger);font-weight:700;"><?= number_format($t['losses'], 1) ?></td>
                <td style="text-align:right;font-weight:800;font-size:1.05rem;color:<?= $t['nrw_percent'] > $wasrebTarget ? 'var(--danger)' : 'var(--success)' ?>;">
                  <?= $t['nrw_percent'] > 0 ? $t['nrw_percent'] . '%' : '-' ?>
                </td>
                <td>
                  <?php if ($t['nrw_percent'] == 0): ?>
                    <span class="badge badge-light">No Data</span>
                  <?php elseif ($compliant): ?>
                    <span class="badge badge-success"><i class="fa-solid fa-check"></i> Within Benchmark</span>
                  <?php else: ?>
                    <span class="badge badge-danger"><i class="fa-solid fa-triangle-exclamation"></i> Above Target</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
