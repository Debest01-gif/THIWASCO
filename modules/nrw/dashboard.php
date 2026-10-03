<?php
$pageTitle = 'NRW Dashboard — Water Loss Analysis';
$activePage = 'nrw_dashboard';
$activeSection = 'nrw';

require_once __DIR__ . '/../../includes/header.php';

// ─── Period Selection ──────────────────────────────────────────────────────
$selectedMonth = clean($_GET['month'] ?? date('Y-m'));
$selectedZone  = (int)($_GET['zone'] ?? 0);

// ─── Data Queries ──────────────────────────────────────────────────────────
try {
    $zones = db()->fetchAll("SELECT * FROM zones WHERE is_active=1 ORDER BY zone_name");

    // Production for selected period
    $production = db()->fetch(
        "SELECT COALESCE(SUM(volume_m3),0) as total_production
         FROM water_production WHERE DATE_FORMAT(record_date,'%Y-%m') = ?",
        [$selectedMonth]
    );
    $totalProduction = (float)($production['total_production'] ?? 0);

    // Billed consumption for selected period
    $billed = db()->fetch(
        "SELECT COALESCE(SUM(consumption_m3),0) as total_billed
         FROM invoices WHERE billing_month = ?",
        [$selectedMonth]
    );
    $totalBilled = (float)($billed['total_billed'] ?? 0);

    $totalNRW = $totalProduction > 0 ? $totalProduction - $totalBilled : 0;
    $nrwPercent = $totalProduction > 0 ? round(($totalNRW / $totalProduction) * 100, 2) : 0;

    // Revenue water (billed & collected)
    $revenueWater = db()->fetch(
        "SELECT COALESCE(SUM(amount),0) as collected
         FROM payments WHERE DATE_FORMAT(payment_date,'%Y-%m') = ? AND is_reversed=0",
        [$selectedMonth]
    );
    $collected = (float)($revenueWater['collected'] ?? 0);

    // Zone-wise NRW
    $zoneNrw = db()->fetchAll(
        "SELECT z.zone_id, z.zone_name,
                COALESCE(SUM(bmr.current_reading - bmr.previous_reading),0) as zone_input,
                COALESCE(SUM(i.consumption_m3),0) as zone_billed
         FROM zones z
         LEFT JOIN bulk_meters bm ON bm.zone_id = z.zone_id
         LEFT JOIN bulk_meter_readings bmr ON bmr.bulk_meter_id = bm.bulk_meter_id
             AND DATE_FORMAT(bmr.reading_date,'%Y-%m') = ?
         LEFT JOIN customers c ON c.zone_id = z.zone_id
         LEFT JOIN invoices i ON i.customer_id = c.customer_id AND i.billing_month = ?
         WHERE z.is_active = 1
         GROUP BY z.zone_id, z.zone_name",
        [$selectedMonth, $selectedMonth]
    );

    // Monthly trend (last 12 months)
    $trend = db()->fetchAll(
        "SELECT report_period,
                total_production_m3, total_billed_m3, nrw_percent,
                real_losses_m3, apparent_losses_m3
         FROM nrw_reports
         ORDER BY report_period DESC LIMIT 12"
    );
    $trend = array_reverse($trend);

    // Water balance IWA components (latest)
    $iwaNrw = db()->fetch(
        "SELECT * FROM nrw_reports ORDER BY report_period DESC LIMIT 1"
    );

    // Production by source
    $sourceBreakdown = db()->fetchAll(
        "SELECT source_name, source_type, SUM(volume_m3) as total
         FROM water_production
         WHERE DATE_FORMAT(record_date,'%Y-%m') = ?
         GROUP BY source_name, source_type
         ORDER BY total DESC",
        [$selectedMonth]
    );

    // Meter anomalies count
    $anomalies = db()->fetch(
        "SELECT COUNT(*) as cnt FROM meter_readings WHERE anomaly_flag=1 AND billing_month = ?",
        [$selectedMonth]
    );
    $anomalyCount = $anomalies['cnt'] ?? 0;

} catch (Exception $e) {
    $zones = []; $totalProduction = 0; $totalBilled = 0; $totalNRW = 0;
    $nrwPercent = 0; $collected = 0; $zoneNrw = []; $trend = [];
    $iwaNrw = null; $sourceBreakdown = []; $anomalyCount = 0;
}

$nrwTarget = (float) getSetting('nrw_target_percent', 20);
$nrwStatus = $nrwPercent <= $nrwTarget ? 'good' : ($nrwPercent <= 40 ? 'warning' : 'danger');
$nrwStatusColors = ['good' => '#22a05a', 'warning' => '#e09200', 'danger' => '#dc2626'];

// IWA Water Balance values
$unbilledAuth  = (float)($iwaNrw['unbilled_authorised_m3'] ?? 0);
$apparentLoss  = (float)($iwaNrw['apparent_losses_m3'] ?? 0);
$realLoss      = (float)($iwaNrw['real_losses_m3'] ?? 0);
$commercialLoss = (float)($iwaNrw['commercial_losses_m3'] ?? 0);
?>

<!-- Page Header -->
<div class="page-header">
  <div class="page-header-left">
    <h2>Water Loss (NRW) Dashboard</h2>
    <p>Non-Revenue Water analysis, IWA water balance and zone performance</p>
  </div>
  <div class="page-header-actions">
    <form method="GET" style="display:flex;gap:10px;align-items:center;">
      <input type="month" name="month" value="<?= $selectedMonth ?>"
             class="form-control" style="width:160px;" onchange="this.form.submit()">
      <select name="zone" class="filter-select" onchange="this.form.submit()">
        <option value="">All Zones</option>
        <?php foreach ($zones as $z): ?>
        <option value="<?= $z['zone_id'] ?>" <?= $selectedZone == $z['zone_id'] ? 'selected' : '' ?>>
          <?= htmlspecialchars($z['zone_name']) ?>
        </option>
        <?php endforeach; ?>
      </select>
    </form>
    <a href="<?= APP_URL ?>/modules/nrw/production.php" class="btn btn-primary">
      <i class="fa-solid fa-faucet"></i> Record Production
    </a>
    <a href="<?= APP_URL ?>/modules/reports/nrw.php" class="btn btn-outline">
      <i class="fa-solid fa-download"></i> Export Report
    </a>
  </div>
</div>

<!-- ===== NRW KPI CARDS ===== -->
<div class="stats-grid">

  <div class="stat-card blue">
    <div class="stat-card-icon"><i class="fa-solid fa-faucet"></i></div>
    <div class="stat-card-label">Water Produced (m³)</div>
    <div class="stat-card-value"><?= number_format($totalProduction, 0) ?></div>
    <div class="stat-card-meta">Total for <?= $selectedMonth ?></div>
  </div>

  <div class="stat-card green">
    <div class="stat-card-icon"><i class="fa-solid fa-file-invoice"></i></div>
    <div class="stat-card-label">Water Billed (m³)</div>
    <div class="stat-card-value"><?= number_format($totalBilled, 0) ?></div>
    <div class="stat-card-meta">Revenue-generating water</div>
  </div>

  <div class="stat-card <?= $nrwStatus === 'good' ? 'green' : ($nrwStatus === 'warning' ? 'gold' : 'red') ?>">
    <div class="stat-card-icon"><i class="fa-solid fa-droplet-slash"></i></div>
    <div class="stat-card-label">Total NRW (m³)</div>
    <div class="stat-card-value"><?= number_format($totalNRW, 0) ?></div>
    <div class="stat-card-meta">
      <div class="progress-bar-container">
        <div class="progress-bar-fill <?= $nrwStatus ?>" style="width:<?= min($nrwPercent,100) ?>%"></div>
      </div>
    </div>
  </div>

  <div class="stat-card <?= $nrwStatus === 'good' ? 'green' : ($nrwStatus === 'warning' ? 'gold' : 'red') ?>">
    <div class="stat-card-icon"><i class="fa-solid fa-percent"></i></div>
    <div class="stat-card-label">NRW %</div>
    <div class="stat-card-value" style="color:<?= $nrwStatusColors[$nrwStatus] ?>;">
      <?= number_format($nrwPercent, 1) ?>%
    </div>
    <div class="stat-card-meta">Target: ≤ <?= $nrwTarget ?>%
      <?php if ($nrwPercent > $nrwTarget): ?>
        <span class="down" style="margin-left:6px;"><i class="fa-solid fa-arrow-up"></i> <?= number_format($nrwPercent - $nrwTarget, 1) ?>% above target</span>
      <?php else: ?>
        <span class="up" style="margin-left:6px;"><i class="fa-solid fa-check"></i> On Target</span>
      <?php endif; ?>
    </div>
  </div>

  <div class="stat-card blue">
    <div class="stat-card-icon"><i class="fa-solid fa-money-bill-wave"></i></div>
    <div class="stat-card-label">Revenue Water Value</div>
    <div class="stat-card-value" style="font-size:1.2rem;"><?= formatCurrency($collected) ?></div>
    <div class="stat-card-meta">Collected revenue</div>
  </div>

  <div class="stat-card purple" style="--purple-val:#7c3aed;">
    <div class="stat-card-icon" style="background:#f5f3ff;color:#7c3aed;"><i class="fa-solid fa-circle-exclamation"></i></div>
    <div class="stat-card-label">Meter Anomalies</div>
    <div class="stat-card-value"><?= number_format($anomalyCount) ?></div>
    <div class="stat-card-meta">
      <a href="<?= APP_URL ?>/modules/meters/anomalies.php" style="color:var(--primary-400);">Review anomalies →</a>
    </div>
  </div>

</div>

<!-- ===== IWA WATER BALANCE ===== -->
<div class="card" style="margin-bottom:24px;">
  <div class="card-header">
    <div class="card-title"><span class="icon">⚖️</span> IWA Standard Water Balance — <?= $selectedMonth ?></div>
    <a href="<?= APP_URL ?>/modules/nrw/water_balance.php" class="btn btn-outline btn-sm">Full Balance</a>
  </div>
  <div class="card-body">
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:0;border:1px solid var(--gray-200);border-radius:10px;overflow:hidden;font-size:0.85rem;">

      <!-- Row headers -->
      <div style="background:var(--primary-700);color:var(--white);padding:12px 16px;font-weight:700;display:flex;align-items:center;">
        System Input Volume
      </div>
      <div style="background:var(--primary-600);color:var(--white);padding:12px 16px;font-weight:700;display:flex;align-items:center;">
        Authorised Consumption
      </div>
      <div style="background:var(--success-600);color:var(--white);padding:12px 16px;font-weight:700;display:flex;align-items:center;">
        Revenue Water
      </div>

      <div style="background:var(--gray-50);padding:12px 16px;font-size:1.1rem;font-weight:700;color:var(--primary-800);border-right:1px solid var(--gray-200);" rowspan="4">
        <?= number_format($totalProduction, 0) ?> m³
      </div>
      <div style="padding:12px 16px;border-right:1px solid var(--gray-200);">
        <div style="color:var(--gray-600);font-size:0.78rem;">Billed authorised</div>
        <div style="font-weight:700;"><?= number_format($totalBilled, 0) ?> m³</div>
      </div>
      <div style="padding:12px 16px;background:var(--success-50);">
        <div style="color:var(--success-600);font-size:0.78rem;">Billed metered consumption</div>
        <div style="font-weight:700;color:var(--success-700);"><?= number_format($totalBilled, 0) ?> m³</div>
      </div>

      <div style="background:var(--warning-50);padding:12px 16px;border-right:1px solid var(--gray-200);">
        <div style="color:var(--gray-600);font-size:0.78rem;">Unbilled authorised</div>
        <div style="font-weight:700;color:var(--warning-600);"><?= number_format($unbilledAuth, 0) ?> m³</div>
        <div style="font-size:0.73rem;color:var(--gray-500);">Firefighting, flushing, etc.</div>
      </div>
      <div style="padding:12px 16px;background:var(--warning-50);">
        <div style="color:var(--warning-600);font-size:0.78rem;">Unbilled metered</div>
        <div style="font-weight:700;color:var(--warning-600);"><?= number_format($unbilledAuth, 0) ?> m³</div>
      </div>

      <!-- NRW row -->
      <div style="background:var(--primary-600);color:var(--white);padding:12px 16px;font-weight:700;">
        Water Losses (NRW)
      </div>
      <div style="background:var(--danger-50);padding:12px 16px;border-right:1px solid var(--gray-200);">
        <div style="color:var(--gray-600);font-size:0.78rem;">Apparent Losses</div>
        <div style="font-weight:700;color:var(--danger-600);"><?= number_format($apparentLoss, 0) ?> m³</div>
        <div style="font-size:0.73rem;color:var(--gray-500);">Theft, meter errors, data errors</div>
      </div>
      <div style="background:var(--danger-50);padding:12px 16px;">
        <div style="color:var(--gray-600);font-size:0.78rem;">Real Losses</div>
        <div style="font-weight:700;color:var(--danger-600);"><?= number_format($realLoss, 0) ?> m³</div>
        <div style="font-size:0.73rem;color:var(--gray-500);">Pipe bursts, leakage</div>
      </div>

    </div>

    <!-- NRW breakdown bar -->
    <?php if ($totalProduction > 0): ?>
    <div style="margin-top:20px;">
      <div style="display:flex;justify-content:space-between;font-size:0.78rem;color:var(--gray-500);margin-bottom:6px;">
        <span>Production = Revenue Water + Unbilled Auth + Water Losses</span>
        <span>100% = <?= number_format($totalProduction,0) ?> m³</span>
      </div>
      <div style="height:28px;border-radius:8px;overflow:hidden;display:flex;box-shadow:var(--shadow-sm);">
        <?php
        $billedPct  = $totalProduction > 0 ? ($totalBilled / $totalProduction * 100) : 0;
        $unbilledPct = $totalProduction > 0 ? ($unbilledAuth / $totalProduction * 100) : 0;
        $lossPct    = $totalProduction > 0 ? ($totalNRW / $totalProduction * 100) : 0;
        ?>
        <div style="width:<?= $billedPct ?>%;background:#22a05a;display:flex;align-items:center;justify-content:center;color:white;font-size:0.73rem;font-weight:700;">
          <?php if ($billedPct > 8): ?>Revenue <?= number_format($billedPct,1) ?>%<?php endif; ?>
        </div>
        <div style="width:<?= $unbilledPct ?>%;background:#e09200;display:flex;align-items:center;justify-content:center;color:white;font-size:0.73rem;font-weight:700;">
          <?php if ($unbilledPct > 4): ?><?= number_format($unbilledPct,1) ?>%<?php endif; ?>
        </div>
        <div style="width:<?= min($lossPct,100-$billedPct-$unbilledPct) ?>%;background:#dc2626;display:flex;align-items:center;justify-content:center;color:white;font-size:0.73rem;font-weight:700;">
          <?php if ($lossPct > 4): ?>NRW <?= number_format($lossPct,1) ?>%<?php endif; ?>
        </div>
      </div>
      <div style="display:flex;gap:16px;margin-top:8px;font-size:0.78rem;">
        <span style="display:flex;align-items:center;gap:4px;"><span style="width:12px;height:12px;background:#22a05a;border-radius:3px;display:inline-block;"></span> Revenue Water</span>
        <span style="display:flex;align-items:center;gap:4px;"><span style="width:12px;height:12px;background:#e09200;border-radius:3px;display:inline-block;"></span> Unbilled Authorised</span>
        <span style="display:flex;align-items:center;gap:4px;"><span style="width:12px;height:12px;background:#dc2626;border-radius:3px;display:inline-block;"></span> NRW / Water Losses</span>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- ===== ZONE NRW TABLE + CHART ===== -->
<div class="charts-grid" style="margin-bottom:24px;">

  <!-- Zone-wise NRW Table -->
  <div class="card">
    <div class="card-header">
      <div class="card-title"><span class="icon">🗺️</span> Zone-wise NRW Analysis</div>
    </div>
    <div class="table-wrapper">
      <table class="data-table">
        <thead>
          <tr>
            <th>Zone</th>
            <th style="text-align:right;">Input (m³)</th>
            <th style="text-align:right;">Billed (m³)</th>
            <th style="text-align:right;">Loss (m³)</th>
            <th>NRW %</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($zoneNrw as $z):
          $zInput = (float)$z['zone_input'];
          $zBilled = (float)$z['zone_billed'];
          $zLoss   = $zInput > 0 ? $zInput - $zBilled : 0;
          $zNrw    = $zInput > 0 ? round($zLoss / $zInput * 100, 1) : 0;
          $zClass  = $zNrw <= $nrwTarget ? 'good' : ($zNrw <= 40 ? 'warning' : 'danger');
          $badgeClass = ['good'=>'success','warning'=>'warning','danger'=>'danger'][$zClass];
        ?>
          <tr>
            <td><strong><?= htmlspecialchars($z['zone_name']) ?></strong></td>
            <td style="text-align:right;"><?= number_format($zInput, 0) ?></td>
            <td style="text-align:right;"><?= number_format($zBilled, 0) ?></td>
            <td style="text-align:right;color:var(--danger-600);font-weight:600;"><?= number_format($zLoss, 0) ?></td>
            <td>
              <div class="progress-bar-container" style="margin-bottom:3px;">
                <div class="progress-bar-fill <?= $zClass ?>" style="width:<?= min($zNrw,100) ?>%"></div>
              </div>
              <span style="font-size:0.78rem;"><?= $zNrw ?>%</span>
            </td>
            <td><span class="badge badge-<?= $badgeClass ?>"><?= $zClass === 'good' ? 'On Target' : ($zClass === 'warning' ? 'High' : 'Critical') ?></span></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- NRW 12-Month Trend Chart -->
  <div class="card">
    <div class="card-header">
      <div class="card-title"><span class="icon">📉</span> NRW 12-Month Trend</div>
    </div>
    <div class="card-body">
      <canvas id="nrwTrendChart" style="height:280px;"></canvas>
    </div>
  </div>

</div>

<!-- ===== PRODUCTION SOURCES + ACTIONS ===== -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:24px;">

  <!-- Production by Source -->
  <div class="card">
    <div class="card-header">
      <div class="card-title"><span class="icon">🏭</span> Production by Source — <?= $selectedMonth ?></div>
      <a href="<?= APP_URL ?>/modules/nrw/production.php" class="btn btn-primary btn-sm">+ Record</a>
    </div>
    <div class="card-body">
      <?php if ($sourceBreakdown): ?>
      <?php foreach ($sourceBreakdown as $src):
        $pct = $totalProduction > 0 ? round($src['total'] / $totalProduction * 100, 1) : 0;
      ?>
      <div style="margin-bottom:14px;">
        <div style="display:flex;justify-content:space-between;font-size:0.85rem;margin-bottom:5px;">
          <span><strong><?= htmlspecialchars($src['source_name']) ?></strong>
            <span class="badge badge-primary" style="margin-left:6px;"><?= $src['source_type'] ?></span>
          </span>
          <span><?= number_format($src['total'],0) ?> m³ (<?= $pct ?>%)</span>
        </div>
        <div class="progress-bar-container">
          <div class="progress-bar-fill good" style="width:<?= $pct ?>%"></div>
        </div>
      </div>
      <?php endforeach; ?>
      <div style="border-top:1px solid var(--gray-200);padding-top:12px;margin-top:12px;display:flex;justify-content:space-between;">
        <strong>Total Production</strong>
        <strong><?= number_format($totalProduction,0) ?> m³</strong>
      </div>
      <?php else: ?>
      <div class="empty-state" style="padding:30px;">
        <div class="icon">🏭</div>
        <p>No production records for <?= $selectedMonth ?>.</p>
        <a href="<?= APP_URL ?>/modules/nrw/production.php" class="btn btn-primary btn-sm">Record Production</a>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Recommendations -->
  <div class="card">
    <div class="card-header">
      <div class="card-title"><span class="icon">💡</span> NRW Recommendations</div>
    </div>
    <div class="card-body">
      <?php
      $recommendations = [];
      if ($nrwPercent > 40) {
        $recommendations[] = ['icon'=>'🔴','text'=>'CRITICAL: NRW exceeds 40%. Immediate pipe network audit and pressure management required.','class'=>'danger'];
      } elseif ($nrwPercent > $nrwTarget) {
        $recommendations[] = ['icon'=>'🟡','text'=>"NRW ({$nrwPercent}%) exceeds WASREB target ({$nrwTarget}%). Review meter data and reduce real losses.",'class'=>'warning'];
      } else {
        $recommendations[] = ['icon'=>'🟢','text'=>"NRW ({$nrwPercent}%) is within target. Continue monitoring.",'class'=>'success'];
      }
      if ($anomalyCount > 10) {
        $recommendations[] = ['icon'=>'⚠️','text'=>"{$anomalyCount} meter anomalies detected. Field verification recommended.",'class'=>'warning'];
      }
      if ($commercialLoss > $totalProduction * 0.05) {
        $recommendations[] = ['icon'=>'🛡️','text'=>'High commercial losses detected. Increase revenue protection field activities.','class'=>'warning'];
      }
      $recommendations[] = ['icon'=>'📊','text'=>'Conduct monthly IWA Water Balance assessment per zone for targeted intervention.','class'=>'info'];
      $recommendations[] = ['icon'=>'🔧','text'=>'Implement pressure management and night flow monitoring to reduce real losses.','class'=>'info'];
      ?>
      <?php foreach ($recommendations as $rec): ?>
      <div class="alert alert-<?= $rec['class'] ?>" style="margin-bottom:10px;">
        <span class="alert-icon"><?= $rec['icon'] ?></span>
        <span><?= htmlspecialchars($rec['text']) ?></span>
      </div>
      <?php endforeach; ?>

      <div style="margin-top:16px;display:grid;gap:8px;">
        <a href="<?= APP_URL ?>/modules/revenue_protection/inspections.php" class="btn btn-outline btn-sm">
          <i class="fa-solid fa-clipboard-check"></i> Log Field Inspection
        </a>
        <a href="<?= APP_URL ?>/modules/nrw/water_balance.php" class="btn btn-outline btn-sm">
          <i class="fa-solid fa-scale-balanced"></i> Full IWA Water Balance
        </a>
        <a href="<?= APP_URL ?>/modules/reports/nrw.php" class="btn btn-primary btn-sm">
          <i class="fa-solid fa-file-chart-column"></i> Generate NRW Report
        </a>
      </div>
    </div>
  </div>

</div>

<script>
// NRW Trend Chart
const nrwTrendCtx = document.getElementById('nrwTrendChart');
if (nrwTrendCtx) {
  const labels = <?= json_encode(array_column($trend,'report_period')) ?>;
  const production = <?= json_encode(array_map('floatval', array_column($trend,'total_production_m3'))) ?>;
  const billed = <?= json_encode(array_map('floatval', array_column($trend,'total_billed_m3'))) ?>;
  const nrwPct = <?= json_encode(array_map('floatval', array_column($trend,'nrw_percent'))) ?>;

  new Chart(nrwTrendCtx, {
    type: 'line',
    data: {
      labels,
      datasets: [
        {
          label: 'Production (m³)',
          data: production,
          borderColor: '#1a407e',
          backgroundColor: 'rgba(26,64,126,0.06)',
          fill: true, tension: 0.4, yAxisID: 'y',
        },
        {
          label: 'Billed (m³)',
          data: billed,
          borderColor: '#22a05a',
          backgroundColor: 'rgba(34,160,90,0.06)',
          fill: true, tension: 0.4, yAxisID: 'y',
        },
        {
          label: 'NRW %',
          data: nrwPct,
          borderColor: '#dc2626',
          borderDash: [5,3],
          tension: 0.4, yAxisID: 'y1',
          pointRadius: 4,
        },
        {
          label: 'Target (<?= $nrwTarget ?>%)',
          data: Array(labels.length).fill(<?= $nrwTarget ?>),
          borderColor: '#22a05a',
          borderDash: [8,4], borderWidth:1.5,
          pointRadius: 0, yAxisID: 'y1',
        }
      ]
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      interaction: { mode: 'index', intersect: false },
      plugins: { legend: { position:'bottom', labels:{boxWidth:12} } },
      scales: {
        y: {
          type:'linear', position:'left',
          ticks: { callback: v => v.toLocaleString() + ' m³' },
          grid: { color:'rgba(0,0,0,0.04)' }
        },
        y1: {
          type:'linear', position:'right',
          min:0, max:100,
          ticks: { callback: v => v + '%' },
          grid: { drawOnChartArea:false }
        },
        x: { grid:{display:false} }
      }
    }
  });
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
