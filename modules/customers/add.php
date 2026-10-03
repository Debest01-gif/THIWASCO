<?php
/**
 * THIWASCO MIS - Register New Customer & Meter
 */
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';

Auth::requirePermission('customers');

$pageTitle = 'Register New Customer';
$activePage = 'customers_add';
$activeSection = 'customers';

$errors = [];
$successMessage = '';

// Zones & Tariffs
$zones = db()->fetchAll("SELECT * FROM zones WHERE is_active = 1 ORDER BY zone_name ASC");
$tariffs = db()->fetchAll("SELECT * FROM tariff_categories WHERE is_active = 1 ORDER BY tariff_type ASC, tariff_code ASC");

// Handle Customer Registration POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_customer') {
    $fullName = clean($_POST['full_name'] ?? '');
    $customerType = clean($_POST['customer_type'] ?? 'Individual');
    $idNumber = clean($_POST['id_number'] ?? '');
    $phone = clean($_POST['phone'] ?? '');
    $phoneAlt = clean($_POST['phone_alt'] ?? '');
    $email = clean($_POST['email'] ?? '');
    $zoneId = (int)($_POST['zone_id'] ?? 0);
    $tariffId = (int)($_POST['tariff_id'] ?? 0);
    $physicalAddress = clean($_POST['physical_address'] ?? '');
    $plotNo = clean($_POST['plot_no'] ?? '');
    $roadStreet = clean($_POST['road_street'] ?? '');
    $connectionDate = clean($_POST['connection_date'] ?? date('Y-m-d'));
    $connectionSize = clean($_POST['connection_size'] ?? '1/2"');
    $depositPaid = (float)($_POST['deposit_paid'] ?? 0);
    $gpsLat = !empty($_POST['gps_lat']) ? (float)$_POST['gps_lat'] : null;
    $gpsLng = !empty($_POST['gps_lng']) ? (float)$_POST['gps_lng'] : null;
    $notes = clean($_POST['notes'] ?? '');

    // Meter details (optional initial meter)
    $meterSerial = clean($_POST['meter_serial'] ?? '');
    $meterMake = clean($_POST['meter_make'] ?? '');
    $meterModel = clean($_POST['meter_model'] ?? '');
    $meterSize = clean($_POST['meter_size'] ?? '1/2"');
    $meterType = clean($_POST['meter_type'] ?? 'Analogue');
    $initialReading = (float)($_POST['initial_reading'] ?? 0);
    $sealNo = clean($_POST['seal_no'] ?? '');

    if (empty($fullName)) {
        $errors[] = 'Full Customer Name is required.';
    }
    if (empty($phone)) {
        $errors[] = 'Primary Phone Number is required.';
    }
    if ($zoneId <= 0) {
        $errors[] = 'Please select a Distribution Zone / DMA.';
    }
    if ($tariffId <= 0) {
        $errors[] = 'Please select an Applicable Tariff.';
    }

    // Check duplicate meter serial if provided
    if (!empty($meterSerial)) {
        $mExists = db()->fetch("SELECT meter_id FROM meters WHERE meter_serial = ?", [$meterSerial]);
        if ($mExists) {
            $errors[] = "Meter Serial {$meterSerial} is already allocated to another customer.";
        }
    }

    if (empty($errors)) {
        try {
            $pdo = Database::getInstance()->getConnection();
            $pdo->beginTransaction();

            // Auto-generate Account Number: THIWASCO-ZN001-XXXX
            $selectedZone = db()->fetch("SELECT zone_code FROM zones WHERE zone_id = ?", [$zoneId]);
            $zCode = $selectedZone ? $selectedZone['zone_code'] : 'ZN-001';
            
            $prefix = 'THI-' . str_replace('-', '', $zCode) . '-';
            $lastAcc = db()->fetch("SELECT MAX(account_no) as last_ref FROM customers WHERE account_no LIKE ?", ["{$prefix}%"]);
            $seq = 1;
            if ($lastAcc && $lastAcc['last_ref']) {
                $seq = (int)substr($lastAcc['last_ref'], -4) + 1;
            }
            $accountNo = $prefix . str_pad($seq, 4, '0', STR_PAD_LEFT);

            // Insert Customer
            $stmtCust = $pdo->prepare("INSERT INTO customers 
                (account_no, zone_id, tariff_id, customer_type, full_name, id_number, phone, phone_alt, email,
                 physical_address, plot_no, road_street, connection_date, connection_size, gps_lat, gps_lng,
                 status, balance, deposit_paid, notes, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Active', 0.00, ?, ?, ?)");
            $stmtCust->execute([
                $accountNo, $zoneId, $tariffId, $customerType, $fullName, $idNumber ?: null, $phone, $phoneAlt ?: null, $email ?: null,
                $physicalAddress ?: null, $plotNo ?: null, $roadStreet ?: null, $connectionDate, $connectionSize, $gpsLat, $gpsLng,
                $depositPaid, $notes ?: null, Auth::id()
            ]);
            $customerId = $pdo->lastInsertId();

            // Insert Meter if serial provided
            if (!empty($meterSerial)) {
                $stmtMeter = $pdo->prepare("INSERT INTO meters 
                    (customer_id, meter_serial, meter_make, meter_model, meter_size, meter_type,
                     installation_date, seal_no, initial_reading, current_reading, status, installed_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Active', ?)");
                $stmtMeter->execute([
                    $customerId, $meterSerial, $meterMake ?: null, $meterModel ?: null, $meterSize, $meterType,
                    $connectionDate, $sealNo ?: null, $initialReading, $initialReading, Auth::id()
                ]);
            }

            $pdo->commit();

            Auth::logAction('Registered Customer', 'customers', "Registered customer {$fullName} with Account {$accountNo}");
            setFlash('success', "Customer <strong>{$fullName}</strong> successfully registered with Account No: <strong>{$accountNo}</strong>!");

            header('Location: ' . APP_URL . '/modules/customers/view.php?id=' . $customerId);
            exit;

        } catch (Exception $e) {
            if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
            $errors[] = 'Registration failed: ' . $e->getMessage();
        }
    }
}

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
  <div class="page-title-wrap">
    <h1><i class="fa-solid fa-user-plus" style="color:var(--gold);"></i> Register New Customer</h1>
    <p>Onboard a new water connection, assign account reference, zone, tariff, and install initial water meter</p>
  </div>
  <div class="page-actions">
    <a href="<?= APP_URL ?>/modules/customers/index.php" class="btn btn-secondary">
      <i class="fa-solid fa-arrow-left"></i> Customer Directory
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
  <input type="hidden" name="action" value="add_customer">

  <div class="row" style="display:grid;grid-template-columns: 1.5fr 1fr; gap:1.5rem;">
    
    <!-- LEFT: CUSTOMER PARTICULARS -->
    <div>
      <div class="card mb-4">
        <div class="card-header">
          <h3><i class="fa-solid fa-id-card" style="color:var(--royal-blue);"></i> Customer Information</h3>
          <span class="badge badge-primary">Account Particulars</span>
        </div>
        <div class="card-body">
          <div class="row" style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
            <div class="form-group mb-3">
              <label class="form-label">Customer Type <span class="text-danger">*</span></label>
              <select name="customer_type" class="form-control" required>
                <option value="Individual">Individual / Residential</option>
                <option value="Company">Commercial Company / Business</option>
                <option value="Institution">School / Hospital / Government</option>
                <option value="Kiosk">Water Kiosk / Standpipe</option>
              </select>
            </div>
            <div class="form-group mb-3">
              <label class="form-label">Full Name / Business Entity <span class="text-danger">*</span></label>
              <input type="text" name="full_name" class="form-control" placeholder="e.g. Samuel Kamau Kariuki" value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>" required>
            </div>
          </div>

          <div class="row" style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem;">
            <div class="form-group mb-3">
              <label class="form-label">National ID / Reg No</label>
              <input type="text" name="id_number" class="form-control" placeholder="e.g. 28491023" value="<?= htmlspecialchars($_POST['id_number'] ?? '') ?>">
            </div>
            <div class="form-group mb-3">
              <label class="form-label">Primary Mobile Phone <span class="text-danger">*</span></label>
              <input type="tel" name="phone" class="form-control" placeholder="07XXXXXXXX" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>" required>
            </div>
            <div class="form-group mb-3">
              <label class="form-label">Alternative Phone</label>
              <input type="tel" name="phone_alt" class="form-control" placeholder="Optional" value="<?= htmlspecialchars($_POST['phone_alt'] ?? '') ?>">
            </div>
          </div>

          <div class="form-group mb-3">
            <label class="form-label">Email Address</label>
            <input type="email" name="email" class="form-control" placeholder="customer@example.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
          </div>
        </div>
      </div>

      <!-- LOCATION & ADDRESS -->
      <div class="card mb-4">
        <div class="card-header">
          <h3><i class="fa-solid fa-location-dot" style="color:var(--royal-blue);"></i> Location & Connection Point</h3>
        </div>
        <div class="card-body">
          <div class="row" style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
            <div class="form-group mb-3">
              <label class="form-label">Distribution Zone / DMA <span class="text-danger">*</span></label>
              <select name="zone_id" class="form-control" required>
                <option value="">-- Select Zone --</option>
                <?php foreach ($zones as $z): ?>
                  <option value="<?= $z['zone_id'] ?>" <?= (isset($_POST['zone_id']) && $_POST['zone_id'] == $z['zone_id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($z['zone_code'] . ' - ' . $z['zone_name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group mb-3">
              <label class="form-label">Applicable Tariff <span class="text-danger">*</span></label>
              <select name="tariff_id" class="form-control" required>
                <option value="">-- Select Tariff --</option>
                <?php foreach ($tariffs as $t): ?>
                  <option value="<?= $t['tariff_id'] ?>" <?= (isset($_POST['tariff_id']) && $_POST['tariff_id'] == $t['tariff_id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($t['tariff_code'] . ' - ' . $t['tariff_name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <div class="row" style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
            <div class="form-group mb-3">
              <label class="form-label">Plot Number / House No</label>
              <input type="text" name="plot_no" class="form-control" placeholder="e.g. Plot 45/B Section 9" value="<?= htmlspecialchars($_POST['plot_no'] ?? '') ?>">
            </div>
            <div class="form-group mb-3">
              <label class="form-label">Road / Street / Landmark</label>
              <input type="text" name="road_street" class="form-control" placeholder="e.g. Kenyatta Highway near BAT" value="<?= htmlspecialchars($_POST['road_street'] ?? '') ?>">
            </div>
          </div>

          <div class="row" style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
            <div class="form-group mb-3">
              <label class="form-label">GPS Latitude</label>
              <input type="number" step="0.000001" name="gps_lat" class="form-control" placeholder="-1.038290" value="<?= htmlspecialchars($_POST['gps_lat'] ?? '') ?>">
            </div>
            <div class="form-group mb-3">
              <label class="form-label">GPS Longitude</label>
              <input type="number" step="0.000001" name="gps_lng" class="form-control" placeholder="37.073420" value="<?= htmlspecialchars($_POST['gps_lng'] ?? '') ?>">
            </div>
          </div>

          <div class="form-group mb-3">
            <label class="form-label">Physical Address / Directions</label>
            <textarea name="physical_address" class="form-control" rows="2" placeholder="Full descriptive address..."><?= htmlspecialchars($_POST['physical_address'] ?? '') ?></textarea>
          </div>
        </div>
      </div>
    </div>

    <!-- RIGHT: METER ASSIGNMENT & ACCOUNT METRICS -->
    <div>
      <div class="card mb-4" style="border:1px solid rgba(234,179,8,0.4);">
        <div class="card-header" style="background:linear-gradient(135deg, var(--navy), var(--royal-blue)); color:#fff;">
          <h3 style="color:var(--gold);"><i class="fa-solid fa-gauge-high"></i> Initial Meter Assignment</h3>
          <span class="badge badge-warning">Optional</span>
        </div>
        <div class="card-body">
          <div class="form-group mb-3">
            <label class="form-label">Meter Serial Number</label>
            <input type="text" name="meter_serial" class="form-control" placeholder="e.g. THW-M-89104" value="<?= htmlspecialchars($_POST['meter_serial'] ?? '') ?>" style="font-weight:700;">
            <small class="text-muted">Unique serial stamped on meter casing</small>
          </div>

          <div class="row" style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
            <div class="form-group mb-3">
              <label class="form-label">Meter Make / Brand</label>
              <input type="text" name="meter_make" class="form-control" placeholder="e.g. Kent, Elster" value="<?= htmlspecialchars($_POST['meter_make'] ?? '') ?>">
            </div>
            <div class="form-group mb-3">
              <label class="form-label">Model</label>
              <input type="text" name="meter_model" class="form-control" placeholder="e.g. V100" value="<?= htmlspecialchars($_POST['meter_model'] ?? '') ?>">
            </div>
          </div>

          <div class="row" style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
            <div class="form-group mb-3">
              <label class="form-label">Meter Size</label>
              <select name="meter_size" class="form-control">
                <option value='1/2"'>1/2" (15mm) Standard</option>
                <option value='3/4"'>3/4" (20mm)</option>
                <option value='1"'>1" (25mm)</option>
                <option value='1.5"'>1.5" (40mm)</option>
                <option value='2"'>2" (50mm)</option>
                <option value='3"'>3" (80mm) Bulk</option>
                <option value='4"'>4" (100mm) Bulk</option>
              </select>
            </div>
            <div class="form-group mb-3">
              <label class="form-label">Meter Type</label>
              <select name="meter_type" class="form-control">
                <option value="Analogue">Analogue Mechanical</option>
                <option value="Digital">Digital Pulse</option>
                <option value="Smart">Smart IoT / AMR</option>
                <option value="Pre-paid">Pre-paid Smart Token</option>
              </select>
            </div>
          </div>

          <div class="row" style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
            <div class="form-group mb-3">
              <label class="form-label">Initial Index (m³)</label>
              <input type="number" step="0.01" name="initial_reading" class="form-control" value="0.00">
            </div>
            <div class="form-group mb-3">
              <label class="form-label">Anti-Tamper Seal No</label>
              <input type="text" name="seal_no" class="form-control" placeholder="e.g. SL-90412" value="<?= htmlspecialchars($_POST['seal_no'] ?? '') ?>">
            </div>
          </div>
        </div>
      </div>

      <div class="card mb-4">
        <div class="card-header">
          <h3><i class="fa-solid fa-file-invoice-dollar" style="color:var(--royal-blue);"></i> Connection & Deposit</h3>
        </div>
        <div class="card-body">
          <div class="form-group mb-3">
            <label class="form-label">Connection Date <span class="text-danger">*</span></label>
            <input type="date" name="connection_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
          </div>
          <div class="form-group mb-3">
            <label class="form-label">Connection Pipe Size</label>
            <input type="text" name="connection_size" class="form-control" value='1/2"'>
          </div>
          <div class="form-group mb-3">
            <label class="form-label">Security Deposit Paid (KES)</label>
            <input type="number" step="0.01" name="deposit_paid" class="form-control" value="2500.00">
          </div>
          <div class="form-group mb-3">
            <label class="form-label">Staff Notes / Special Instructions</label>
            <textarea name="notes" class="form-control" rows="2" placeholder="Optional internal notes..."></textarea>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card-body">
          <button type="submit" class="btn btn-primary" style="width:100%;padding:0.85rem;font-size:1.05rem;">
            <i class="fa-solid fa-user-plus"></i> Complete Customer Registration
          </button>
        </div>
      </div>

    </div>
  </div>
</form>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
