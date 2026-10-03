<?php
/**
 * THIWASCO MIS - Water & Sewerage Tariff Management
 */
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';

Auth::requirePermission('billing');

$pageTitle = 'Tariff Categories';
$activePage = 'billing_tariffs';
$activeSection = 'billing';

$errors = [];
$successMessage = '';

// Handle Add/Edit Tariff
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $tariffCode = strtoupper(clean($_POST['tariff_code'] ?? ''));
    $tariffName = clean($_POST['tariff_name'] ?? '');
    $tariffType = clean($_POST['tariff_type'] ?? 'Domestic');
    $baseCharge = (float)($_POST['base_charge'] ?? 0);
    $ratePerM3 = (float)($_POST['rate_per_m3'] ?? 0);
    $sewerPercent = (float)($_POST['sewer_percent'] ?? 0);
    $minCharge = (float)($_POST['min_charge'] ?? 0);
    $effectiveFrom = clean($_POST['effective_from'] ?? date('Y-m-d'));
    $tieredRatesJson = trim($_POST['tiered_rates'] ?? '');

    if (empty($tariffCode) || empty($tariffName)) {
        $errors[] = 'Tariff Code and Tariff Name are required.';
    }

    // Validate JSON if provided
    if (!empty($tieredRatesJson)) {
        $decoded = json_decode($tieredRatesJson, true);
        if ($decoded === null) {
            $errors[] = 'Tiered Rates must be valid JSON format (e.g. [{"from":0,"to":6,"rate":52}]).';
        }
    } else {
        $tieredRatesJson = null;
    }

    if (empty($errors)) {
        try {
            if ($action === 'create') {
                $sql = "INSERT INTO tariff_categories 
                        (tariff_code, tariff_name, tariff_type, base_charge, rate_per_m3, sewer_percent, min_charge, tiered_rates, effective_from)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                db()->query($sql, [$tariffCode, $tariffName, $tariffType, $baseCharge, $ratePerM3, $sewerPercent, $minCharge, $tieredRatesJson, $effectiveFrom]);
                Auth::logAction('Created Tariff', 'billing', "Created tariff {$tariffCode} - {$tariffName}");
                setFlash('success', "Tariff category {$tariffCode} created successfully.");
            } elseif ($action === 'update') {
                $tariffId = (int)$_POST['tariff_id'];
                $sql = "UPDATE tariff_categories SET 
                        tariff_code = ?, tariff_name = ?, tariff_type = ?, base_charge = ?, 
                        rate_per_m3 = ?, sewer_percent = ?, min_charge = ?, tiered_rates = ?, effective_from = ?
                        WHERE tariff_id = ?";
                db()->query($sql, [$tariffCode, $tariffName, $tariffType, $baseCharge, $ratePerM3, $sewerPercent, $minCharge, $tieredRatesJson, $effectiveFrom, $tariffId]);
                Auth::logAction('Updated Tariff', 'billing', "Updated tariff {$tariffCode}");
                setFlash('success', "Tariff category {$tariffCode} updated successfully.");
            } elseif ($action === 'delete') {
                $tariffId = (int)$_POST['tariff_id'];
                $custCount = db()->fetch("SELECT COUNT(*) as cnt FROM customers WHERE tariff_id = ?", [$tariffId])['cnt'] ?? 0;
                if ($custCount > 0) {
                    setFlash('danger', "Cannot delete tariff: {$custCount} customers are currently assigned to this tariff category.");
                } else {
                    $t = db()->fetch("SELECT tariff_code FROM tariff_categories WHERE tariff_id = ?", [$tariffId]);
                    if ($t) {
                        db()->query("DELETE FROM tariff_categories WHERE tariff_id = ?", [$tariffId]);
                        Auth::logAction('Deleted Tariff', 'billing', "Deleted tariff {$t['tariff_code']}");
                        setFlash('success', "Tariff category <strong>{$t['tariff_code']}</strong> was permanently deleted.");
                    }
                }
            }
            header('Location: ' . APP_URL . '/modules/billing/tariffs.php');
            exit;
        } catch (Exception $e) {
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }
}

// Fetch all tariffs
$tariffs = db()->fetchAll("SELECT t.*, COUNT(c.customer_id) as total_customers 
                           FROM tariff_categories t 
                           LEFT JOIN customers c ON t.tariff_id = c.tariff_id 
                           GROUP BY t.tariff_id 
                           ORDER BY t.tariff_type ASC, t.tariff_code ASC");

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
  <div class="page-title-wrap">
    <h1><i class="fa-solid fa-tags" style="color:var(--gold);"></i> Water & Sewerage Tariffs</h1>
    <p>Approved WASREB tariff schedules, progressive consumption brackets, and sewerage percentage rates</p>
  </div>
  <div class="page-actions">
    <button onclick="openModal('addTariffModal')" class="btn btn-primary">
      <i class="fa-solid fa-plus-circle"></i> Add New Tariff
    </button>
    <a href="<?= APP_URL ?>/modules/billing/generate.php" class="btn btn-secondary">
      <i class="fa-solid fa-bolt"></i> Generate Bills
    </a>
  </div>
</div>

<?php if (!empty($errors)): ?>
  <div class="alert alert-danger">
    <i class="fa-solid fa-circle-exclamation"></i>
    <div>
      <?php foreach ($errors as $err): ?>
        <div><?= htmlspecialchars($err) ?></div>
      <?php endforeach; ?>
    </div>
  </div>
<?php endif; ?>

<div class="card">
  <div class="card-header">
    <h3><i class="fa-solid fa-table-list" style="color:var(--royal-blue);"></i> Configured Tariff Schedules</h3>
    <span class="badge badge-info"><?= count($tariffs) ?> Active Tariffs</span>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table">
        <thead>
          <tr>
            <th>Code</th>
            <th>Name & Type</th>
            <th>Base Charge</th>
            <th>Sewer %</th>
            <th>Min Charge</th>
            <th>Consumption Tiers (KES / m³)</th>
            <th>Customers</th>
            <th>Effective</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($tariffs as $t): 
            $tiers = json_decode($t['tiered_rates'], true);
          ?>
            <tr>
              <td><strong style="color:var(--royal-blue);"><?= htmlspecialchars($t['tariff_code']) ?></strong></td>
              <td>
                <strong><?= htmlspecialchars($t['tariff_name']) ?></strong>
                <div style="font-size:0.75rem;color:var(--text-muted);"><?= htmlspecialchars($t['tariff_type']) ?></div>
              </td>
              <td><?= formatCurrency($t['base_charge']) ?></td>
              <td><span class="badge badge-info"><?= number_format($t['sewer_percent'], 0) ?>%</span></td>
              <td><?= formatCurrency($t['min_charge']) ?></td>
              <td>
                <?php if (!empty($tiers)): ?>
                  <div style="display:flex;flex-wrap:wrap;gap:4px;">
                    <?php foreach ($tiers as $tier): 
                      $to = $tier['to'] >= 999999 ? 'Over ' . ($tier['from'] - 1) . 'm³' : "{$tier['from']}-{$tier['to']}m³";
                    ?>
                      <span class="badge badge-light" style="border:1px solid #cbd5e1;font-size:0.8rem;">
                        <?= $to ?>: <strong style="color:var(--royal-blue);"><?= formatCurrency($tier['rate']) ?></strong>
                      </span>
                    <?php endforeach; ?>
                  </div>
                <?php else: ?>
                  <strong><?= formatCurrency($t['rate_per_m3']) ?> / m³</strong>
                <?php endif; ?>
              </td>
              <td>
                <span class="badge badge-light" style="font-size:0.85rem;">
                  <i class="fa-solid fa-users"></i> <?= number_format($t['total_customers']) ?>
                </span>
              </td>
              <td style="font-size:0.8rem;"><?= formatDate($t['effective_from']) ?></td>
              <td>
                <div style="display:inline-flex;gap:4px;">
                  <button type="button" class="btn btn-secondary btn-sm" onclick='editTariff(<?= json_encode($t) ?>)'>
                    <i class="fa-solid fa-pen-to-square"></i> Edit
                  </button>
                  <?php if ($t['total_customers'] == 0): ?>
                    <form method="POST" action="" style="display:inline;" onsubmit="return confirm('Permanently delete tariff <?= htmlspecialchars(addslashes($t['tariff_code'])) ?>?');">
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="tariff_id" value="<?= $t['tariff_id'] ?>">
                      <button type="submit" class="btn btn-danger btn-sm" title="Delete Tariff">
                        <i class="fa-solid fa-trash"></i>
                      </button>
                    </form>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- ADD / EDIT TARIFF MODAL -->
<div id="addTariffModal" class="modal-backdrop">
  <div class="modal" style="max-width: 650px;">
    <div class="modal-header">
      <h3 id="modalTitle"><i class="fa-solid fa-tags" style="color:var(--gold);"></i> Add New Tariff</h3>
      <button class="modal-close" onclick="closeModal('addTariffModal')">&times;</button>
    </div>
    <form method="POST" action="">
      <input type="hidden" name="action" id="formAction" value="create">
      <input type="hidden" name="tariff_id" id="tariffId" value="">

      <div class="modal-body">
        <div class="row" style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
          <div class="form-group mb-3">
            <label class="form-label">Tariff Code <span class="text-danger">*</span></label>
            <input type="text" name="tariff_code" id="tariffCode" class="form-control" placeholder="e.g. DOM-01" required>
          </div>
          <div class="form-group mb-3">
            <label class="form-label">Category Type <span class="text-danger">*</span></label>
            <select name="tariff_type" id="tariffType" class="form-control" required>
              <option value="Domestic">Domestic</option>
              <option value="Commercial">Commercial</option>
              <option value="Industrial">Industrial</option>
              <option value="Institution">Institution</option>
              <option value="Standpipe">Standpipe</option>
              <option value="Kiosk">Water Kiosk</option>
            </select>
          </div>
        </div>

        <div class="form-group mb-3">
          <label class="form-label">Tariff Description Name <span class="text-danger">*</span></label>
          <input type="text" name="tariff_name" id="tariffName" class="form-control" placeholder="e.g. Domestic - Standard Residential" required>
        </div>

        <div class="row" style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem;">
          <div class="form-group mb-3">
            <label class="form-label">Base Fee (KES)</label>
            <input type="number" step="0.01" name="base_charge" id="baseCharge" class="form-control" value="150.00">
          </div>
          <div class="form-group mb-3">
            <label class="form-label">Sewerage (%)</label>
            <input type="number" step="0.01" name="sewer_percent" id="sewerPercent" class="form-control" value="60.00">
          </div>
          <div class="form-group mb-3">
            <label class="form-label">Min Charge (KES)</label>
            <input type="number" step="0.01" name="min_charge" id="minCharge" class="form-control" value="300.00">
          </div>
        </div>

        <div class="form-group mb-3">
          <label class="form-label">Flat Rate per m³ (if no tiers configured)</label>
          <input type="number" step="0.01" name="rate_per_m3" id="ratePerM3" class="form-control" value="52.00">
        </div>

        <div class="form-group mb-3">
          <label class="form-label">Tiered Rates (JSON Array)</label>
          <textarea name="tiered_rates" id="tieredRates" class="form-control" rows="4" style="font-family:monospace;font-size:0.85rem;" placeholder='[{"from":0,"to":6,"rate":52},{"from":7,"to":20,"rate":62},{"from":21,"to":50,"rate":75},{"from":51,"to":999999,"rate":95}]'></textarea>
          <small class="text-muted">Enter valid JSON brackets for progressive consumption billing.</small>
        </div>

        <div class="form-group mb-3">
          <label class="form-label">Effective From Date</label>
          <input type="date" name="effective_from" id="effectiveFrom" class="form-control" value="<?= date('Y-01-01') ?>" required>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('addTariffModal')">Cancel</button>
        <button type="submit" class="btn btn-primary" id="modalSubmitBtn"><i class="fa-solid fa-save"></i> Save Tariff</button>
      </div>
    </form>
  </div>
</div>

<script>
function editTariff(t) {
  document.getElementById('modalTitle').innerHTML = '<i class="fa-solid fa-tags" style="color:var(--gold);"></i> Edit Tariff: ' + t.tariff_code;
  document.getElementById('formAction').value = 'update';
  document.getElementById('tariffId').value = t.tariff_id;
  document.getElementById('tariffCode').value = t.tariff_code;
  document.getElementById('tariffName').value = t.tariff_name;
  document.getElementById('tariffType').value = t.tariff_type;
  document.getElementById('baseCharge').value = t.base_charge;
  document.getElementById('sewerPercent').value = t.sewer_percent;
  document.getElementById('minCharge').value = t.min_charge;
  document.getElementById('ratePerM3').value = t.rate_per_m3;
  document.getElementById('tieredRates').value = t.tiered_rates || '';
  document.getElementById('effectiveFrom').value = t.effective_from;
  document.getElementById('modalSubmitBtn').innerHTML = '<i class="fa-solid fa-save"></i> Update Tariff';
  openModal('addTariffModal');
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
