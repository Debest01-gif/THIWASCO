<?php
/**
 * THIWASCO MIS - Optional Module: Fixed Assets & Infrastructure Management
 */
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';

Auth::check();

$pageTitle = 'Fixed Asset Management';
$activePage = 'assets_register';
$activeSection = 'assets';

// Handle Add Asset POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_asset') {
    $code = strtoupper(clean($_POST['asset_code'] ?? ''));
    $name = clean($_POST['asset_name'] ?? '');
    $type = clean($_POST['asset_type'] ?? 'Pump');
    $loc = clean($_POST['location'] ?? '');
    $cost = (float)($_POST['purchase_cost'] ?? 0);
    $cond = clean($_POST['condition'] ?? 'Good');
    $status = clean($_POST['status'] ?? 'Active');
    $purchaseDate = clean($_POST['purchase_date'] ?? date('Y-m-d'));

    if (!empty($code) && !empty($name)) {
        db()->query("INSERT INTO assets (asset_code, asset_name, asset_type, location, purchase_cost, current_value, `condition`, status, purchase_date)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                     [$code, $name, $type, $loc, $cost, $cost, $cond, $status, $purchaseDate]);
        Auth::logAction('Added Asset', 'assets', "Added asset {$code}: {$name}");
        setFlash('success', "Asset {$code} registered successfully.");
        header('Location: ' . APP_URL . '/optional/assets/index.php');
        exit;
    }
}

// Handle Edit Asset POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_asset') {
    $assetId = (int)($_POST['asset_id'] ?? 0);
    $code = strtoupper(clean($_POST['asset_code'] ?? ''));
    $name = clean($_POST['asset_name'] ?? '');
    $type = clean($_POST['asset_type'] ?? 'Pump');
    $loc = clean($_POST['location'] ?? '');
    $cost = (float)($_POST['current_value'] ?? 0);
    $cond = clean($_POST['condition'] ?? 'Good');
    $status = clean($_POST['status'] ?? 'Active');

    if ($assetId > 0 && !empty($code) && !empty($name)) {
        db()->query("UPDATE assets SET asset_code = ?, asset_name = ?, asset_type = ?, location = ?, current_value = ?, `condition` = ?, status = ? WHERE asset_id = ?",
                     [$code, $name, $type, $loc, $cost, $cond, $status, $assetId]);
        Auth::logAction('Updated Asset', 'assets', "Updated asset {$code}");
        setFlash('success', "Asset {$code} updated successfully.");
        header('Location: ' . APP_URL . '/optional/assets/index.php');
        exit;
    }
}

// Handle Delete Asset POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_asset') {
    $assetId = (int)($_POST['asset_id'] ?? 0);
    if ($assetId > 0) {
        $a = db()->fetch("SELECT asset_code FROM assets WHERE asset_id = ?", [$assetId]);
        if ($a) {
            try {
                db()->query("DELETE FROM asset_maintenance WHERE asset_id = ?", [$assetId]);
                db()->query("DELETE FROM assets WHERE asset_id = ?", [$assetId]);
                Auth::logAction('Deleted Asset', 'assets', "Deleted asset {$a['asset_code']}");
                setFlash('success', "Asset {$a['asset_code']} was deleted.");
            } catch (Exception $e) {
                setFlash('danger', 'Failed to delete asset: ' . $e->getMessage());
            }
        }
        header('Location: ' . APP_URL . '/optional/assets/index.php');
        exit;
    }
}

$assets = db()->fetchAll("SELECT * FROM assets ORDER BY asset_type ASC, asset_name ASC");

$totalAssets = count($assets);
$portfolioValue = array_sum(array_column($assets, 'current_value'));
$underMaintenance = count(array_filter($assets, fn($x) => $x['status'] === 'Under Maintenance'));

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
  <div class="page-title-wrap">
    <h1><i class="fa-solid fa-building-columns" style="color:var(--gold);"></i> Fixed Assets & Engineering Infrastructure</h1>
    <p>Plant register for treatment works, high-lift pumps, reservoirs, distribution pipelines, boreholes and fleet</p>
  </div>
  <div class="page-actions">
    <button onclick="openModal('addAssetModal')" class="btn btn-primary">
      <i class="fa-solid fa-plus-circle"></i> Add Infrastructure Asset
    </button>
  </div>
</div>

<div class="stats-grid mb-4">
  <div class="stat-card">
    <div class="stat-icon" style="background:rgba(30,58,138,0.1);color:var(--royal-blue);"><i class="fa-solid fa-industry"></i></div>
    <div class="stat-info">
      <span class="stat-value"><?= number_format($totalAssets) ?></span>
      <span class="stat-label">Registered Fixed Assets</span>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:rgba(21,128,61,0.1);color:var(--success);"><i class="fa-solid fa-vault"></i></div>
    <div class="stat-info">
      <span class="stat-value"><?= formatCurrency($portfolioValue) ?></span>
      <span class="stat-label">Total Asset Capital Valuation</span>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:rgba(234,179,8,0.1);color:var(--gold);"><i class="fa-solid fa-wrench"></i></div>
    <div class="stat-info">
      <span class="stat-value"><?= number_format($underMaintenance) ?></span>
      <span class="stat-label">Under Active Maintenance</span>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <h3><i class="fa-solid fa-table-list" style="color:var(--royal-blue);"></i> Engineering Asset Register</h3>
    <span class="badge badge-light"><?= $totalAssets ?> Assets</span>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table">
        <thead>
          <tr>
            <th>Asset Code</th>
            <th>Asset Name</th>
            <th>Type</th>
            <th>Location</th>
            <th style="text-align:right;">Book Value (KES)</th>
            <th>Condition</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($assets)): ?>
            <tr><td colspan="7" class="text-center py-4 text-muted">No infrastructure assets logged yet.</td></tr>
          <?php else: ?>
            <?php foreach ($assets as $a): 
              $cBadge = match($a['condition']) {
                'Excellent', 'Good' => 'badge-success',
                'Fair' => 'badge-warning',
                default => 'badge-danger'
              };
            ?>
              <tr>
                <td><strong style="color:var(--royal-blue);"><?= htmlspecialchars($a['asset_code']) ?></strong></td>
                <td><strong><?= htmlspecialchars($a['asset_name']) ?></strong></td>
                <td><span class="badge badge-light"><?= htmlspecialchars($a['asset_type']) ?></span></td>
                <td><?= htmlspecialchars($a['location'] ?? '-') ?></td>
                <td style="text-align:right;font-weight:700;color:var(--navy);"><?= formatCurrency($a['current_value']) ?></td>
                <td><span class="badge <?= $cBadge ?>"><?= htmlspecialchars($a['condition']) ?></span></td>
                <td>
                  <span class="badge <?= $a['status'] === 'Active' ? 'badge-success' : 'badge-warning' ?>">
                    <?= htmlspecialchars($a['status']) ?>
                  </span>
                </td>
                <td>
                  <div style="display:inline-flex;gap:4px;">
                    <a href="<?= APP_URL ?>/optional/assets/maintenance.php?asset_id=<?= $a['asset_id'] ?>" class="btn btn-secondary btn-sm" title="Maintenance Log">
                      <i class="fa-solid fa-wrench"></i>
                    </a>
                    <button type="button" class="btn btn-secondary btn-sm" onclick='openEditAssetModal(<?= json_encode($a) ?>)' title="Edit Asset">
                      <i class="fa-solid fa-pen-to-square"></i>
                    </button>
                    <form method="POST" action="" style="display:inline;" onsubmit="return confirm('Delete infrastructure asset <?= htmlspecialchars(addslashes($a['asset_code'])) ?>?');">
                      <input type="hidden" name="action" value="delete_asset">
                      <input type="hidden" name="asset_id" value="<?= $a['asset_id'] ?>">
                      <button type="submit" class="btn btn-danger btn-sm" title="Delete Asset">
                        <i class="fa-solid fa-trash"></i>
                      </button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- ADD ASSET MODAL -->
<div id="addAssetModal" class="modal-overlay">
  <div class="modal" style="max-width: 550px;">
    <div class="modal-header">
      <h3><i class="fa-solid fa-building-columns" style="color:var(--gold-300);"></i> Add Infrastructure Asset</h3>
      <button class="modal-close" onclick="closeModal('addAssetModal')">&times;</button>
    </div>
    <form method="POST" action="">
      <input type="hidden" name="action" value="add_asset">
      <div class="modal-body">
        <div class="row" style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
          <div class="form-group mb-3">
            <label class="form-label">Asset Code <span class="text-danger">*</span></label>
            <input type="text" name="asset_code" class="form-control" placeholder="e.g. PMP-CH-01" required>
          </div>
          <div class="form-group mb-3">
            <label class="form-label">Asset Category</label>
            <select name="asset_type" class="form-control" required>
              <option value="Pump">Submersible / Centrifugal Pump</option>
              <option value="Pipe">Distribution Pipeline</option>
              <option value="Tank">Storage Reservoir / Tank</option>
              <option value="Borehole">Borehole & Wellhead</option>
              <option value="Plant">Water Treatment Plant</option>
              <option value="Vehicle">Maintenance Fleet & Truck</option>
              <option value="Building">Substation / Office Building</option>
            </select>
          </div>
        </div>
        <div class="form-group mb-3">
          <label class="form-label">Asset Name <span class="text-danger">*</span></label>
          <input type="text" name="asset_name" class="form-control" placeholder="e.g. Chania Plant 110kW High Lift Pump 1" required>
        </div>
        <div class="row" style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
          <div class="form-group mb-3">
            <label class="form-label">Location / Sub-Station</label>
            <input type="text" name="location" class="form-control" placeholder="e.g. Chania Treatment Works">
          </div>
          <div class="form-group mb-3">
            <label class="form-label">Acquisition / Cost (KES)</label>
            <input type="number" step="0.01" name="purchase_cost" class="form-control" placeholder="e.g. 2400000">
          </div>
        </div>
        <div class="row" style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
          <div class="form-group mb-3">
            <label class="form-label">Operating Condition</label>
            <select name="condition" class="form-control">
              <option value="Excellent">Excellent</option>
              <option value="Good" selected>Good</option>
              <option value="Fair">Fair</option>
              <option value="Poor">Poor</option>
            </select>
          </div>
          <div class="form-group mb-3">
            <label class="form-label">Status</label>
            <select name="status" class="form-control">
              <option value="Active" selected>Active & Operational</option>
              <option value="Under Maintenance">Under Maintenance</option>
              <option value="Standby">Standby Backup</option>
            </select>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('addAssetModal')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> Save Asset</button>
      </div>
    </form>
  </div>
</div>

<!-- EDIT ASSET MODAL -->
<div id="editAssetModal" class="modal-overlay">
  <div class="modal" style="max-width: 550px;">
    <div class="modal-header">
      <h3><i class="fa-solid fa-pen-to-square" style="color:var(--gold-300);"></i> Edit Infrastructure Asset</h3>
      <button class="modal-close" onclick="closeModal('editAssetModal')">&times;</button>
    </div>
    <form method="POST" action="">
      <input type="hidden" name="action" value="edit_asset">
      <input type="hidden" name="asset_id" id="edit_asset_id">
      <div class="modal-body">
        <div class="row" style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
          <div class="form-group mb-3">
            <label class="form-label">Asset Code <span class="text-danger">*</span></label>
            <input type="text" name="asset_code" id="edit_asset_code" class="form-control" required>
          </div>
          <div class="form-group mb-3">
            <label class="form-label">Asset Category</label>
            <select name="asset_type" id="edit_asset_type" class="form-control" required>
              <option value="Pump">Submersible / Centrifugal Pump</option>
              <option value="Pipe">Distribution Pipeline</option>
              <option value="Tank">Storage Reservoir / Tank</option>
              <option value="Borehole">Borehole & Wellhead</option>
              <option value="Plant">Water Treatment Plant</option>
              <option value="Vehicle">Maintenance Fleet & Truck</option>
              <option value="Building">Substation / Office Building</option>
            </select>
          </div>
        </div>
        <div class="form-group mb-3">
          <label class="form-label">Asset Name <span class="text-danger">*</span></label>
          <input type="text" name="asset_name" id="edit_asset_name" class="form-control" required>
        </div>
        <div class="row" style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
          <div class="form-group mb-3">
            <label class="form-label">Location / Sub-Station</label>
            <input type="text" name="location" id="edit_location" class="form-control">
          </div>
          <div class="form-group mb-3">
            <label class="form-label">Current Value (KES)</label>
            <input type="number" step="0.01" name="current_value" id="edit_current_value" class="form-control">
          </div>
        </div>
        <div class="row" style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
          <div class="form-group mb-3">
            <label class="form-label">Operating Condition</label>
            <select name="condition" id="edit_condition" class="form-control">
              <option value="Excellent">Excellent</option>
              <option value="Good">Good</option>
              <option value="Fair">Fair</option>
              <option value="Poor">Poor</option>
            </select>
          </div>
          <div class="form-group mb-3">
            <label class="form-label">Status</label>
            <select name="status" id="edit_status" class="form-control">
              <option value="Active">Active & Operational</option>
              <option value="Under Maintenance">Under Maintenance</option>
              <option value="Standby">Standby Backup</option>
            </select>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('editAssetModal')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check"></i> Save Changes</button>
      </div>
    </form>
  </div>
</div>

<script>
function openEditAssetModal(a) {
  document.getElementById('edit_asset_id').value = a.asset_id;
  document.getElementById('edit_asset_code').value = a.asset_code;
  document.getElementById('edit_asset_name').value = a.asset_name;
  document.getElementById('edit_asset_type').value = a.asset_type;
  document.getElementById('edit_location').value = a.location || '';
  document.getElementById('edit_current_value').value = a.current_value;
  document.getElementById('edit_condition').value = a.condition;
  document.getElementById('edit_status').value = a.status;
  openModal('editAssetModal');
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
