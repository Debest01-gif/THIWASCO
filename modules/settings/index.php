<?php
/**
 * THIWASCO MIS - Global System Settings & Optional Modules Configuration
 */
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';

Auth::requirePermission('admin');

$pageTitle = 'System Settings';
$activePage = 'settings';
$activeSection = 'settings';

// Handle Save Settings POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_settings') {
    $settingsToUpdate = [
        'company_name' => clean($_POST['company_name'] ?? ''),
        'company_short' => clean($_POST['company_short'] ?? ''),
        'phone_hotline' => clean($_POST['phone_hotline'] ?? ''),
        'company_email' => clean($_POST['company_email'] ?? ''),
        'postal_address' => clean($_POST['postal_address'] ?? ''),
        'mpesa_paybill' => clean($_POST['mpesa_paybill'] ?? ''),
        'nrw_target_percent' => clean($_POST['nrw_target_percent'] ?? '20.0'),
        'reconnection_fee' => clean($_POST['reconnection_fee'] ?? '1000.00'),
        'disconnection_days_grace' => clean($_POST['disconnection_days_grace'] ?? '14'),
        'currency_code' => clean($_POST['currency_code'] ?? 'KES')
    ];

    // Optional modules enabled
    $mods = $_POST['modules_enabled'] ?? [];
    $settingsToUpdate['modules_enabled'] = implode(',', $mods);

    try {
        $stmt = Database::getInstance()->getConnection()->prepare("INSERT INTO system_settings (setting_key, setting_value) 
                                                                   VALUES (?, ?) 
                                                                   ON DUPLICATE KEY UPDATE setting_value = ?");
        foreach ($settingsToUpdate as $key => $val) {
            $stmt->execute([$key, $val, $val]);
        }

        Auth::logAction('Updated System Settings', 'settings', 'Updated system preferences & module toggles');
        setFlash('success', 'System settings saved successfully.');
        header('Location: ' . APP_URL . '/modules/settings/index.php');
        exit;
    } catch (Exception $e) {
        setFlash('danger', 'Failed to save settings: ' . $e->getMessage());
    }
}

// Read current settings
$currentSettings = [];
$rows = db()->fetchAll("SELECT setting_key, setting_value FROM system_settings");
foreach ($rows as $r) {
    $currentSettings[$r['setting_key']] = $r['setting_value'];
}

$enabledMods = explode(',', $currentSettings['modules_enabled'] ?? 'stores,procurement,assets,projects');

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
  <div class="page-title-wrap">
    <h1><i class="fa-solid fa-gear" style="color:var(--gold);"></i> System Settings & Configuration</h1>
    <p>Corporate identity, M-Pesa paybill gateway, WASREB NRW compliance thresholds and optional enterprise modules</p>
  </div>
</div>

<form method="POST" action="">
  <input type="hidden" name="action" value="save_settings">

  <div class="row" style="display:grid;grid-template-columns:1.5fr 1fr;gap:1.5rem;">
    
    <!-- LEFT: CORPORATE & BILLING PARAMETERS -->
    <div>
      <div class="card mb-4">
        <div class="card-header">
          <h3><i class="fa-solid fa-building" style="color:var(--royal-blue);"></i> Corporate Entity Particulars</h3>
        </div>
        <div class="card-body">
          <div class="row" style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
            <div class="form-group mb-3">
              <label class="form-label">Company Official Name</label>
              <input type="text" name="company_name" class="form-control" value="<?= htmlspecialchars($currentSettings['company_name'] ?? 'Thika Water and Sewerage Company Limited') ?>" required>
            </div>
            <div class="form-group mb-3">
              <label class="form-label">Short Acronym</label>
              <input type="text" name="company_short" class="form-control" value="<?= htmlspecialchars($currentSettings['company_short'] ?? 'THIWASCO') ?>" required>
            </div>
          </div>

          <div class="row" style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
            <div class="form-group mb-3">
              <label class="form-label">Customer Care Hotline</label>
              <input type="text" name="phone_hotline" class="form-control" value="<?= htmlspecialchars($currentSettings['phone_hotline'] ?? '+254 720 123 456') ?>">
            </div>
            <div class="form-group mb-3">
              <label class="form-label">Corporate Email</label>
              <input type="email" name="company_email" class="form-control" value="<?= htmlspecialchars($currentSettings['company_email'] ?? 'info@thiwasco.co.ke') ?>">
            </div>
          </div>

          <div class="form-group mb-3">
            <label class="form-label">Postal Address</label>
            <input type="text" name="postal_address" class="form-control" value="<?= htmlspecialchars($currentSettings['postal_address'] ?? 'P.O. Box 6103 - 01000, Thika, Kenya') ?>">
          </div>
        </div>
      </div>

      <!-- BILLING & WATER LOSS PARAMETERS -->
      <div class="card mb-4">
        <div class="card-header">
          <h3><i class="fa-solid fa-scale-balanced" style="color:var(--gold);"></i> NRW & Operational Parameters</h3>
        </div>
        <div class="card-body">
          <div class="row" style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
            <div class="form-group mb-3">
              <label class="form-label">Safaricom M-Pesa Paybill</label>
              <input type="text" name="mpesa_paybill" class="form-control" value="<?= htmlspecialchars($currentSettings['mpesa_paybill'] ?? '888000') ?>" required>
            </div>
            <div class="form-group mb-3">
              <label class="form-label">WASREB NRW Target (%)</label>
              <input type="number" step="0.1" name="nrw_target_percent" class="form-control" value="<?= htmlspecialchars($currentSettings['nrw_target_percent'] ?? '20.0') ?>" required>
            </div>
          </div>

          <div class="row" style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
            <div class="form-group mb-3">
              <label class="form-label">Reconnection Fee (KES)</label>
              <input type="number" step="0.01" name="reconnection_fee" class="form-control" value="<?= htmlspecialchars($currentSettings['reconnection_fee'] ?? '1000.00') ?>" required>
            </div>
            <div class="form-group mb-3">
              <label class="form-label">Disconnection Grace Period (Days)</label>
              <input type="number" name="disconnection_days_grace" class="form-control" value="<?= htmlspecialchars($currentSettings['disconnection_days_grace'] ?? '14') ?>" required>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- RIGHT: OPTIONAL INTEGRATED MODULES TOGGLE -->
    <div>
      <div class="card mb-4" style="border-top:4px solid var(--gold);">
        <div class="card-header">
          <h3><i class="fa-solid fa-cubes" style="color:var(--gold);"></i> Optional Enterprise Modules</h3>
          <span class="badge badge-warning">Modular</span>
        </div>
        <div class="card-body" style="font-size:0.9rem;">
          <p class="text-muted" style="margin-bottom:1.25rem;">
            Enable or disable optional secondary departments. Disabled modules will be hidden from the navigation sidebar.
          </p>

          <div class="form-group mb-3" style="background:var(--bg-light);padding:0.85rem;border-radius:6px;">
            <label style="display:flex;align-items:center;gap:0.75rem;cursor:pointer;font-weight:700;">
              <input type="checkbox" name="modules_enabled[]" value="stores" <?= in_array('stores', $enabledMods) ? 'checked' : '' ?> style="width:18px;height:18px;">
              <span><i class="fa-solid fa-warehouse"></i> Stores & Inventory Management</span>
            </label>
            <small class="text-muted" style="display:block;margin-left:28px;">Pipes, fittings, meter stocks and material requisitions</small>
          </div>

          <div class="form-group mb-3" style="background:var(--bg-light);padding:0.85rem;border-radius:6px;">
            <label style="display:flex;align-items:center;gap:0.75rem;cursor:pointer;font-weight:700;">
              <input type="checkbox" name="modules_enabled[]" value="procurement" <?= in_array('procurement', $enabledMods) ? 'checked' : '' ?> style="width:18px;height:18px;">
              <span><i class="fa-solid fa-cart-shopping"></i> Procurement Management</span>
            </label>
            <small class="text-muted" style="display:block;margin-left:28px;">Suppliers, Local Purchase Orders (LPOs) and tender awards</small>
          </div>

          <div class="form-group mb-3" style="background:var(--bg-light);padding:0.85rem;border-radius:6px;">
            <label style="display:flex;align-items:center;gap:0.75rem;cursor:pointer;font-weight:700;">
              <input type="checkbox" name="modules_enabled[]" value="assets" <?= in_array('assets', $enabledMods) ? 'checked' : '' ?> style="width:18px;height:18px;">
              <span><i class="fa-solid fa-building-columns"></i> Asset & Plant Management</span>
            </label>
            <small class="text-muted" style="display:block;margin-left:28px;">Pumps, storage tanks, boreholes, vehicles and maintenance</small>
          </div>

          <div class="form-group mb-4" style="background:var(--bg-light);padding:0.85rem;border-radius:6px;">
            <label style="display:flex;align-items:center;gap:0.75rem;cursor:pointer;font-weight:700;">
              <input type="checkbox" name="modules_enabled[]" value="projects" <?= in_array('projects', $enabledMods) ? 'checked' : '' ?> style="width:18px;height:18px;">
              <span><i class="fa-solid fa-helmet-safety"></i> Capital Works & Projects</span>
            </label>
            <small class="text-muted" style="display:block;margin-left:28px;">Network extensions, pipe laying projects, budgets and milestones</small>
          </div>

          <button type="submit" class="btn btn-primary" style="width:100%;padding:0.85rem;font-size:1.05rem;">
            <i class="fa-solid fa-save"></i> Save System Configuration
          </button>
        </div>
      </div>
    </div>

  </div>
</form>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
