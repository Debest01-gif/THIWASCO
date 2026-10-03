<?php
/**
 * THIWASCO MIS - Edit Customer Account
 */
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';

Auth::requirePermission('customers');

$customerId = (int)($_GET['id'] ?? 0);
if ($customerId <= 0) {
    header('Location: ' . APP_URL . '/modules/customers/index.php');
    exit;
}

$customer = db()->fetch("SELECT * FROM customers WHERE customer_id = ?", [$customerId]);
if (!$customer) {
    setFlash('danger', 'Customer not found.');
    header('Location: ' . APP_URL . '/modules/customers/index.php');
    exit;
}

$pageTitle = 'Edit Customer: ' . $customer['account_no'];
$activePage = 'customers_list';
$activeSection = 'customers';

$errors = [];
$zones = db()->fetchAll("SELECT * FROM zones WHERE is_active = 1 ORDER BY zone_name ASC");
$tariffs = db()->fetchAll("SELECT * FROM tariff_categories WHERE is_active = 1 ORDER BY tariff_type ASC, tariff_code ASC");

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_customer') {
    $fullName = clean($_POST['full_name'] ?? '');
    $customerType = clean($_POST['customer_type'] ?? 'Individual');
    $idNumber = clean($_POST['id_number'] ?? '');
    $phone = clean($_POST['phone'] ?? '');
    $phoneAlt = clean($_POST['phone_alt'] ?? '');
    $email = clean($_POST['email'] ?? '');
    $zoneId = (int)($_POST['zone_id'] ?? 0);
    $tariffId = (int)($_POST['tariff_id'] ?? 0);
    $status = clean($_POST['status'] ?? 'Active');
    $physicalAddress = clean($_POST['physical_address'] ?? '');
    $plotNo = clean($_POST['plot_no'] ?? '');
    $roadStreet = clean($_POST['road_street'] ?? '');
    $connectionSize = clean($_POST['connection_size'] ?? '1/2"');
    $gpsLat = !empty($_POST['gps_lat']) ? (float)$_POST['gps_lat'] : null;
    $gpsLng = !empty($_POST['gps_lng']) ? (float)$_POST['gps_lng'] : null;
    $notes = clean($_POST['notes'] ?? '');

    if (empty($fullName)) $errors[] = 'Customer full name is required.';
    if (empty($phone)) $errors[] = 'Phone number is required.';
    if ($zoneId <= 0) $errors[] = 'Distribution zone is required.';
    if ($tariffId <= 0) $errors[] = 'Tariff category is required.';

    if (empty($errors)) {
        try {
            $sql = "UPDATE customers SET 
                    full_name = ?, customer_type = ?, id_number = ?, phone = ?, phone_alt = ?, email = ?,
                    zone_id = ?, tariff_id = ?, status = ?, physical_address = ?, plot_no = ?, road_street = ?,
                    connection_size = ?, gps_lat = ?, gps_lng = ?, notes = ?
                    WHERE customer_id = ?";
            db()->query($sql, [
                $fullName, $customerType, $idNumber ?: null, $phone, $phoneAlt ?: null, $email ?: null,
                $zoneId, $tariffId, $status, $physicalAddress ?: null, $plotNo ?: null, $roadStreet ?: null,
                $connectionSize, $gpsLat, $gpsLng, $notes ?: null, $customerId
            ]);

            Auth::logAction('Updated Customer', 'customers', "Updated customer details for {$customer['account_no']} ({$fullName})");
            setFlash('success', 'Customer profile updated successfully.');
            header('Location: ' . APP_URL . '/modules/customers/view.php?id=' . $customerId);
            exit;
        } catch (Exception $e) {
            $errors[] = 'Update failed: ' . $e->getMessage();
        }
    }
}

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
  <div class="page-title-wrap">
    <h1><i class="fa-solid fa-user-pen" style="color:var(--gold);"></i> Edit Customer: <?= htmlspecialchars($customer['account_no']) ?></h1>
    <p>Update customer profile, contact information, premises address and account parameters</p>
  </div>
  <div class="page-actions">
    <a href="<?= APP_URL ?>/modules/customers/view.php?id=<?= $customerId ?>" class="btn btn-secondary">
      <i class="fa-solid fa-arrow-left"></i> View Account
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

<form method="POST" action="">
  <input type="hidden" name="action" value="update_customer">

  <div class="card mb-4">
    <div class="card-header">
      <h3><i class="fa-solid fa-user" style="color:var(--royal-blue);"></i> Primary Details</h3>
      <span class="badge badge-info">Acc: <?= htmlspecialchars($customer['account_no']) ?></span>
    </div>
    <div class="card-body">
      <div class="row" style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem;">
        <div class="form-group mb-3">
          <label class="form-label">Full Name <span class="text-danger">*</span></label>
          <input type="text" name="full_name" class="form-control" value="<?= htmlspecialchars($customer['full_name']) ?>" required>
        </div>
        <div class="form-group mb-3">
          <label class="form-label">Customer Type</label>
          <select name="customer_type" class="form-control">
            <option value="Individual" <?= $customer['customer_type'] === 'Individual' ? 'selected' : '' ?>>Individual</option>
            <option value="Company" <?= $customer['customer_type'] === 'Company' ? 'selected' : '' ?>>Company</option>
            <option value="Institution" <?= $customer['customer_type'] === 'Institution' ? 'selected' : '' ?>>Institution</option>
            <option value="Kiosk" <?= $customer['customer_type'] === 'Kiosk' ? 'selected' : '' ?>>Kiosk</option>
          </select>
        </div>
        <div class="form-group mb-3">
          <label class="form-label">Account Status <span class="text-danger">*</span></label>
          <select name="status" class="form-control" required>
            <option value="Active" <?= $customer['status'] === 'Active' ? 'selected' : '' ?>>Active</option>
            <option value="Inactive" <?= $customer['status'] === 'Inactive' ? 'selected' : '' ?>>Inactive</option>
            <option value="Disconnected" <?= $customer['status'] === 'Disconnected' ? 'selected' : '' ?>>Disconnected</option>
            <option value="Suspended" <?= $customer['status'] === 'Suspended' ? 'selected' : '' ?>>Suspended</option>
            <option value="Defaulter" <?= $customer['status'] === 'Defaulter' ? 'selected' : '' ?>>Defaulter</option>
          </select>
        </div>
      </div>

      <div class="row" style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem;">
        <div class="form-group mb-3">
          <label class="form-label">National ID / Reg No</label>
          <input type="text" name="id_number" class="form-control" value="<?= htmlspecialchars($customer['id_number'] ?? '') ?>">
        </div>
        <div class="form-group mb-3">
          <label class="form-label">Mobile Phone <span class="text-danger">*</span></label>
          <input type="tel" name="phone" class="form-control" value="<?= htmlspecialchars($customer['phone']) ?>" required>
        </div>
        <div class="form-group mb-3">
          <label class="form-label">Alt Phone</label>
          <input type="tel" name="phone_alt" class="form-control" value="<?= htmlspecialchars($customer['phone_alt'] ?? '') ?>">
        </div>
      </div>

      <div class="form-group mb-3">
        <label class="form-label">Email Address</label>
        <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($customer['email'] ?? '') ?>">
      </div>
    </div>
  </div>

  <div class="card mb-4">
    <div class="card-header">
      <h3><i class="fa-solid fa-location-dot" style="color:var(--royal-blue);"></i> Zone, Tariff & Address</h3>
    </div>
    <div class="card-body">
      <div class="row" style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
        <div class="form-group mb-3">
          <label class="form-label">Distribution Zone / DMA <span class="text-danger">*</span></label>
          <select name="zone_id" class="form-control" required>
            <?php foreach ($zones as $z): ?>
              <option value="<?= $z['zone_id'] ?>" <?= $customer['zone_id'] == $z['zone_id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($z['zone_code'] . ' - ' . $z['zone_name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group mb-3">
          <label class="form-label">Applicable Tariff <span class="text-danger">*</span></label>
          <select name="tariff_id" class="form-control" required>
            <?php foreach ($tariffs as $t): ?>
              <option value="<?= $t['tariff_id'] ?>" <?= $customer['tariff_id'] == $t['tariff_id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($t['tariff_code'] . ' - ' . $t['tariff_name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="row" style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
        <div class="form-group mb-3">
          <label class="form-label">Plot Number</label>
          <input type="text" name="plot_no" class="form-control" value="<?= htmlspecialchars($customer['plot_no'] ?? '') ?>">
        </div>
        <div class="form-group mb-3">
          <label class="form-label">Road / Street</label>
          <input type="text" name="road_street" class="form-control" value="<?= htmlspecialchars($customer['road_street'] ?? '') ?>">
        </div>
      </div>

      <div class="row" style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
        <div class="form-group mb-3">
          <label class="form-label">GPS Latitude</label>
          <input type="number" step="0.000001" name="gps_lat" class="form-control" value="<?= htmlspecialchars($customer['gps_lat'] ?? '') ?>">
        </div>
        <div class="form-group mb-3">
          <label class="form-label">GPS Longitude</label>
          <input type="number" step="0.000001" name="gps_lng" class="form-control" value="<?= htmlspecialchars($customer['gps_lng'] ?? '') ?>">
        </div>
      </div>

      <div class="form-group mb-3">
        <label class="form-label">Physical Address</label>
        <textarea name="physical_address" class="form-control" rows="2"><?= htmlspecialchars($customer['physical_address'] ?? '') ?></textarea>
      </div>

      <div class="form-group mb-4">
        <label class="form-label">Notes</label>
        <textarea name="notes" class="form-control" rows="2"><?= htmlspecialchars($customer['notes'] ?? '') ?></textarea>
      </div>

      <div class="form-actions" style="display:flex;justify-content:flex-end;gap:1rem;">
        <a href="<?= APP_URL ?>/modules/customers/view.php?id=<?= $customerId ?>" class="btn btn-secondary">Cancel</a>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> Save Changes</button>
      </div>
    </div>
  </div>
</form>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
