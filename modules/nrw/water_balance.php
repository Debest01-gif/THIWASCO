<?php
/**
 * THIWASCO MIS - IWA Standard Water Balance & Loss Accounting
 */
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';

Auth::requirePermission('nrw');

$pageTitle = 'IWA Standard Water Balance';
$activePage = 'nrw_balance';
$activeSection = 'nrw';

$reportPeriod = clean($_GET['period'] ?? date('Y-m'));

// Fetch actual production for this period
$prodData = db()->fetch("SELECT COALESCE(SUM(volume_m3), 0) as total_prod FROM water_production WHERE record_date LIKE ?", ["{$reportPeriod}%"]);
$systemInputVolume = (float)$prodData['total_prod'];

// Fetch actual billed volume from invoices
$billData = db()->fetch("SELECT COALESCE(SUM(consumption_m3), 0) as billed_vol, COALESCE(SUM(water_charge), 0) as billed_val 
                         FROM invoices WHERE billing_month = ?", [$reportPeriod]);
$billedMetered = (float)$billData['billed_vol'];

// Default estimates if not yet entered
$unbilledAuthorized = round($systemInputVolume * 0.02, 1); // 2% operational / fire fighting
$theftCommercial = round($systemInputVolume * 0.12, 1);    // 12% illegal / meter inaccuracy
$pipeBurstsReal = max(0, $systemInputVolume - $billedMetered - $unbilledAuthorized - $theftCommercial);

// Check if an existing report was saved
$existingReport = db()->fetch("SELECT * FROM nrw_reports WHERE report_period = ? AND zone_id IS NULL", [$reportPeriod]);
if ($existingReport) {
    $systemInputVolume = (float)$existingReport['total_production_m3'];
    $billedMetered = (float)$existingReport['total_billed_m3'];
    $unbilledAuthorized = (float)$existingReport['unbilled_authorised_m3'];
    $theftCommercial = (float)$existingReport['commercial_losses_m3'];
    $pipeBurstsReal = (float)$existingReport['physical_losses_m3'];
}

// Handle Save Water Balance POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_water_balance') {
    $siv = (float)$_POST['system_input'];
    $billed = (float)$_POST['billed_metered'];
    $unbilled = (float)$_POST['unbilled_authorized'];
    $commercial = (float)$_POST['commercial_losses'];
    $physical = (float)$_POST['physical_losses'];
    $notes = clean($_POST['notes'] ?? '');

    $totalLosses = $commercial + $physical;
    $nrwPercent = $siv > 0 ? round(($totalLosses / $siv) * 100, 2) : 0;

    try {
        if ($existingReport) {
            db()->query("UPDATE nrw_reports SET 
                         total_production_m3 = ?, total_billed_m3 = ?, nrw_percent = ?,
                         commercial_losses_m3 = ?, physical_losses_m3 = ?, real_losses_m3 = ?,
                         apparent_losses_m3 = ?, unbilled_authorised_m3 = ?, notes = ?
                         WHERE nrw_id = ?",
                         [$siv, $billed, $nrwPercent, $commercial, $physical, $physical, $commercial, $unbilled, $notes, $existingReport['nrw_id']]);
        } else {
            db()->query("INSERT INTO nrw_reports 
                         (zone_id, report_period, total_production_m3, total_billed_m3, nrw_percent,
                          commercial_losses_m3, physical_losses_m3, real_losses_m3, apparent_losses_m3,
                          unbilled_authorised_m3, notes, generated_by)
                         VALUES (NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                         [$reportPeriod, $siv, $billed, $nrwPercent, $commercial, $physical, $physical, $commercial, $unbilled, $notes, Auth::id()]);
        }

        Auth::logAction('Saved Water Balance', 'nrw', "Saved IWA Water Balance for {$reportPeriod}: NRW {$nrwPercent}%");
        setFlash('success', "IWA Water Balance for <strong>{$reportPeriod}</strong> successfully recorded! Overall NRW: <strong>{$nrwPercent}%</strong>");
        header('Location: ' . APP_URL . '/modules/nrw/water_balance.php?period=' . $reportPeriod);
        exit;
    } catch (Exception $e) {
        setFlash('danger', 'Failed to save water balance: ' . $e->getMessage());
    }
}

$totalLossesCalc = $theftCommercial + $pipeBurstsReal;
$nrwPctCalc = $systemInputVolume > 0 ? round(($totalLossesCalc / $systemInputVolume) * 100, 1) : 0;
$wasrebTarget = (float)getSetting('nrw_target_percent', 20.0);

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
  <div class="page-title-wrap">
    <h1><i class="fa-solid fa-scale-balanced" style="color:var(--gold);"></i> IWA Standard Water Balance</h1>
    <p>International Water Association (IWA) water audit matrix: System Input, Authorised Consumption, Commercial & Physical Losses</p>
  </div>
  <div class="page-actions">
    <a href="<?= APP_URL ?>/modules/nrw/dashboard.php" class="btn btn-primary">
      <i class="fa-solid fa-chart-area"></i> NRW Dashboard
    </a>
    <a href="<?= APP_URL ?>/modules/nrw/analysis.php" class="btn btn-secondary">
      <i class="fa-solid fa-magnifying-glass-chart"></i> Loss Analytics
    </a>
  </div>
</div>

<!-- PERIOD PICKER -->
<div class="card mb-4">
  <div class="card-body" style="padding:1rem 1.25rem;">
    <form method="GET" action="" style="display:flex;align-items:center;gap:1.5rem;flex-wrap:wrap;">
      <div style="display:flex;align-items:center;gap:0.75rem;">
        <label class="form-label mb-0" style="font-weight:700;">Audit Period:</label>
        <input type="month" name="period" class="form-control" value="<?= htmlspecialchars($reportPeriod) ?>" onchange="this.form.submit()">
      </div>
      <div style="font-size:0.85rem;color:var(--text-muted);">
        System automatically pulls production from Water Production logs and billed consumption from Customer Invoices.
      </div>
    </form>
  </div>
</div>

<!-- TOP METRIC CARDS -->
<div class="stats-grid mb-4">
  <div class="stat-card">
    <div class="stat-icon" style="background:rgba(30,58,138,0.1);color:var(--royal-blue);"><i class="fa-solid fa-faucet"></i></div>
    <div class="stat-info">
      <span class="stat-value"><?= number_format($systemInputVolume, 1) ?> m³</span>
      <span class="stat-label">System Input Volume (SIV)</span>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:rgba(21,128,61,0.1);color:var(--success);"><i class="fa-solid fa-receipt"></i></div>
    <div class="stat-info">
      <span class="stat-value"><?= number_format($billedMetered, 1) ?> m³</span>
      <span class="stat-label">Billed Authorized (Revenue Water)</span>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:rgba(239,68,68,0.1);color:var(--danger);"><i class="fa-solid fa-droplet-slash"></i></div>
    <div class="stat-info">
      <span class="stat-value"><?= number_format($totalLossesCalc, 1) ?> m³</span>
      <span class="stat-label">Total Water Losses (NRW)</span>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:rgba(234,179,8,0.1);color:var(--gold);"><i class="fa-solid fa-percent"></i></div>
    <div class="stat-info">
      <span class="stat-value"><?= $nrwPctCalc ?>%</span>
      <span class="stat-label">NRW Level (Target: &le;<?= $wasrebTarget ?>%)</span>
    </div>
  </div>
</div>

<!-- IWA TABLE PRESENTATION -->
<div class="card mb-4">
  <div class="card-header" style="background:var(--navy);color:#fff;">
    <h3 style="color:var(--gold);"><i class="fa-solid fa-sitemap"></i> IWA Water Balance Matrix (m³) — Period: <?= htmlspecialchars($reportPeriod) ?></h3>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table" style="text-align:center;font-size:0.9rem;border-collapse:collapse;">
        <tbody>
          <!-- LEVEL 1 -->
          <tr>
            <td rowspan="6" style="background:rgba(30,58,138,0.1);font-weight:800;font-size:1.15rem;color:var(--royal-blue);width:25%;border:2px solid #cbd5e1;vertical-align:middle;">
              System Input Volume (SIV)<br>
              <span style="font-size:1.4rem;color:var(--navy);"><?= number_format($systemInputVolume, 1) ?> m³</span><br>
              <small style="color:var(--text-muted);font-weight:500;">(100%)</small>
            </td>

            <td rowspan="2" style="background:rgba(21,128,61,0.1);font-weight:700;color:var(--success);width:25%;border:2px solid #cbd5e1;vertical-align:middle;">
              Authorised Consumption<br>
              <span style="font-size:1.15rem;"><?= number_format($billedMetered + $unbilledAuthorized, 1) ?> m³</span>
            </td>

            <td style="background:rgba(21,128,61,0.05);border:1px solid #cbd5e1;text-align:left;padding:1rem;">
              <strong>Billed Authorised Consumption</strong><br>
              <small class="text-muted">Billed metered & unmetered customers</small>
            </td>
            <td style="background:rgba(21,128,61,0.05);border:1px solid #cbd5e1;font-weight:700;color:var(--success);font-size:1.1rem;text-align:right;padding:1rem;">
              <?= number_format($billedMetered, 1) ?> m³
            </td>
            <td rowspan="1" style="background:rgba(21,128,61,0.15);font-weight:800;color:var(--success);border:2px solid #cbd5e1;vertical-align:middle;width:20%;">
              REVENUE WATER<br>
              <span style="font-size:1.2rem;"><?= number_format($billedMetered, 1) ?> m³</span>
            </td>
          </tr>

          <!-- LEVEL 2 -->
          <tr>
            <td style="background:rgba(234,179,8,0.05);border:1px solid #cbd5e1;text-align:left;padding:1rem;">
              <strong>Unbilled Authorised Consumption</strong><br>
              <small class="text-muted">Fire fighting, mains flushing, public standpipes</small>
            </td>
            <td style="background:rgba(234,179,8,0.05);border:1px solid #cbd5e1;font-weight:700;color:var(--gold);font-size:1.1rem;text-align:right;padding:1rem;">
              <?= number_format($unbilledAuthorized, 1) ?> m³
            </td>
            <td rowspan="5" style="background:rgba(239,68,68,0.15);font-weight:800;color:var(--danger);border:2px solid #cbd5e1;vertical-align:middle;">
              NON-REVENUE WATER (NRW)<br>
              <span style="font-size:1.4rem;"><?= number_format($totalLossesCalc + $unbilledAuthorized, 1) ?> m³</span><br>
              <span style="font-size:1.1rem;color:var(--danger);"><?= $nrwPctCalc ?>%</span>
            </td>
          </tr>

          <!-- LEVEL 3: COMMERCIAL LOSSES -->
          <tr>
            <td rowspan="4" style="background:rgba(239,68,68,0.1);font-weight:700;color:var(--danger);border:2px solid #cbd5e1;vertical-align:middle;">
              Water Losses<br>
              <span style="font-size:1.15rem;"><?= number_format($totalLossesCalc, 1) ?> m³</span>
            </td>

            <td style="background:rgba(239,68,68,0.05);border:1px solid #cbd5e1;text-align:left;padding:1rem;">
              <strong>Commercial / Apparent Losses</strong><br>
              <small class="text-muted">Illegal connections, bypasses, meter under-registration</small>
            </td>
            <td style="background:rgba(239,68,68,0.05);border:1px solid #cbd5e1;font-weight:700;color:var(--danger);font-size:1.1rem;text-align:right;padding:1rem;">
              <?= number_format($theftCommercial, 1) ?> m³
            </td>
          </tr>

          <!-- LEVEL 4: PHYSICAL LOSSES -->
          <tr>
            <td style="background:rgba(185,28,28,0.05);border:1px solid #cbd5e1;text-align:left;padding:1rem;">
              <strong>Physical / Real Losses</strong><br>
              <small class="text-muted">Leaks on mains, burst pipes, tank overflows</small>
            </td>
            <td style="background:rgba(185,28,28,0.05);border:1px solid #cbd5e1;font-weight:700;color:#b91c1c;font-size:1.1rem;text-align:right;padding:1rem;">
              <?= number_format($pipeBurstsReal, 1) ?> m³
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- AUDIT DATA ENTRY FORM -->
<div class="card">
  <div class="card-header">
    <h3><i class="fa-solid fa-sliders" style="color:var(--royal-blue);"></i> Water Balance Component Adjustments</h3>
  </div>
  <div class="card-body">
    <form method="POST" action="">
      <input type="hidden" name="action" value="save_water_balance">

      <div class="row" style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;">
        <div>
          <div class="form-group mb-3">
            <label class="form-label">System Input Volume (SIV in m³) <span class="text-danger">*</span></label>
            <input type="number" step="0.1" name="system_input" id="inSiv" class="form-control" value="<?= $systemInputVolume ?>" required oninput="recalcNRW()">
            <small class="text-muted">Pulled from water production records</small>
          </div>

          <div class="form-group mb-3">
            <label class="form-label">Billed Authorised Consumption (m³) <span class="text-danger">*</span></label>
            <input type="number" step="0.1" name="billed_metered" id="inBilled" class="form-control" value="<?= $billedMetered ?>" required oninput="recalcNRW()">
            <small class="text-muted">Pulled from customer water billing invoices</small>
          </div>

          <div class="form-group mb-3">
            <label class="form-label">Unbilled Authorised (m³)</label>
            <input type="number" step="0.1" name="unbilled_authorized" id="inUnbilled" class="form-control" value="<?= $unbilledAuthorized ?>" oninput="recalcNRW()">
          </div>
        </div>

        <div>
          <div class="form-group mb-3">
            <label class="form-label">Commercial / Apparent Losses (m³)</label>
            <input type="number" step="0.1" name="commercial_losses" id="inComm" class="form-control" value="<?= $theftCommercial ?>" oninput="recalcNRW()">
            <small class="text-muted">Estimated from revenue protection field audits</small>
          </div>

          <div class="form-group mb-3">
            <label class="form-label">Physical / Real Losses (m³)</label>
            <input type="number" step="0.1" name="physical_losses" id="inPhys" class="form-control" value="<?= $pipeBurstsReal ?>" oninput="recalcNRW()">
            <small class="text-muted">Pipe bursts, joint weeping and tank overflows</small>
          </div>

          <div class="form-group mb-3">
            <label class="form-label">Engineering / Audit Remarks</label>
            <textarea name="notes" class="form-control" rows="2" placeholder="e.g. Ruiru main pipe burst repaired on 14th..."><?= htmlspecialchars($existingReport['notes'] ?? '') ?></textarea>
          </div>
        </div>
      </div>

      <div class="form-actions" style="display:flex;justify-content:space-between;align-items:center;margin-top:1.5rem;padding-top:1rem;border-top:1px solid var(--border-color);">
        <div style="font-size:1.1rem;font-weight:700;">
          Calculated NRW: <span id="dynNRW" style="color:var(--danger);"><?= $nrwPctCalc ?>%</span>
        </div>
        <button type="submit" class="btn btn-primary" style="padding:0.75rem 2rem;">
          <i class="fa-solid fa-save"></i> Save Official Water Balance Report
        </button>
      </div>
    </form>
  </div>
</div>

<script>
function recalcNRW() {
  const siv = parseFloat(document.getElementById('inSiv').value) || 0;
  const billed = parseFloat(document.getElementById('inBilled').value) || 0;
  const comm = parseFloat(document.getElementById('inComm').value) || 0;
  const phys = parseFloat(document.getElementById('inPhys').value) || 0;
  
  if (siv > 0) {
    const losses = comm + phys;
    const pct = ((losses / siv) * 100).toFixed(1);
    document.getElementById('dynNRW').textContent = pct + '%';
  }
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
