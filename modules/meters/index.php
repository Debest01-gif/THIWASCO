<?php
/**
 * THIWASCO MIS - Water Meters Register
 */
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';

Auth::requirePermission('meters');

$pageTitle = 'Water Meters Inventory';
$activePage = 'meters_list';
$activeSection = 'meters';

// Filters
$search = clean($_GET['search'] ?? '');
$status = clean($_GET['status'] ?? '');
$zoneId = !empty($_GET['zone_id']) ? (int)$_GET['zone_id'] : '';
$meterType = clean($_GET['meter_type'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;

$zones = db()->fetchAll("SELECT * FROM zones WHERE is_active = 1 ORDER BY zone_name ASC");

// Handle Add Meter Modal POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_meter') {
        $serial = clean($_POST['meter_serial'] ?? '');
        $customerId = (int)($_POST['customer_id'] ?? 0);
        $make = clean($_POST['meter_make'] ?? '');
        $model = clean($_POST['meter_model'] ?? '');
        $size = clean($_POST['meter_size'] ?? '1/2"');
        $type = clean($_POST['meter_type'] ?? 'Analogue');
        $sealNo = clean($_POST['seal_no'] ?? '');
        $initReading = (float)($_POST['initial_reading'] ?? 0);
        $installDate = clean($_POST['installation_date'] ?? date('Y-m-d'));

        if (empty($serial)) {
            setFlash('danger', 'Meter serial number is required.');
        } else {
            $exists = db()->fetch("SELECT meter_id FROM meters WHERE meter_serial = ?", [$serial]);
            if ($exists) {
                setFlash('danger', "Meter serial {$serial} is already registered.");
            } else {
                db()->query("INSERT INTO meters (customer_id, meter_serial, meter_make, meter_model, meter_size, meter_type, seal_no, initial_reading, current_reading, installation_date, status, installed_by)
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Active', ?)",
                             [$customerId, $serial, $make, $model, $size, $type, $sealNo, $initReading, $initReading, $installDate, Auth::id()]);
                Auth::logAction('Added Meter', 'meters', "Added meter {$serial}");
                setFlash('success', "Meter {$serial} successfully added.");
                header('Location: ' . APP_URL . '/modules/meters/index.php');
                exit;
            }
        }
    }

    if ($_POST['action'] === 'update_meter') {
        $meterId = (int)($_POST['meter_id'] ?? 0);
        $serial = clean($_POST['meter_serial'] ?? '');
        $customerId = (int)($_POST['customer_id'] ?? 0);
        $make = clean($_POST['meter_make'] ?? '');
        $model = clean($_POST['meter_model'] ?? '');
        $size = clean($_POST['meter_size'] ?? '1/2"');
        $type = clean($_POST['meter_type'] ?? 'Analogue');
        $sealNo = clean($_POST['seal_no'] ?? '');
        $currentReading = (float)($_POST['current_reading'] ?? 0);
        $status = clean($_POST['status'] ?? 'Active');
        $installDate = clean($_POST['installation_date'] ?? date('Y-m-d'));

        if (empty($serial) || $meterId <= 0) {
            setFlash('danger', 'Meter serial number and ID are required.');
        } else {
            $exists = db()->fetch("SELECT meter_id FROM meters WHERE meter_serial = ? AND meter_id != ?", [$serial, $meterId]);
            if ($exists) {
                setFlash('danger', "Meter serial {$serial} is already used by another meter.");
            } else {
                db()->query("UPDATE meters SET customer_id = ?, meter_serial = ?, meter_make = ?, meter_model = ?, 
                             meter_size = ?, meter_type = ?, seal_no = ?, current_reading = ?, status = ?, installation_date = ? 
                             WHERE meter_id = ?",
                             [$customerId, $serial, $make, $model, $size, $type, $sealNo, $currentReading, $status, $installDate, $meterId]);
                Auth::logAction('Updated Meter', 'meters', "Updated meter {$serial}");
                setFlash('success', "Meter <strong>{$serial}</strong> updated successfully.");
                header('Location: ' . APP_URL . '/modules/meters/index.php');
                exit;
            }
        }
    }

    if ($_POST['action'] === 'delete_meter') {
        $meterId = (int)($_POST['meter_id'] ?? 0);
        $cascade = isset($_POST['cascade_delete']) && $_POST['cascade_delete'] == '1';

        $meter = db()->fetch("SELECT * FROM meters WHERE meter_id = ?", [$meterId]);
        if ($meter) {
            try {
                $pdo = Database::getInstance()->getConnection();
                $pdo->beginTransaction();

                if ($cascade) {
                    $pdo->prepare("DELETE FROM payment_allocations WHERE invoice_id IN (SELECT invoice_id FROM invoices WHERE meter_id = ?)")->execute([$meterId]);
                    $pdo->prepare("DELETE FROM invoices WHERE meter_id = ?")->execute([$meterId]);
                    $pdo->prepare("DELETE FROM meter_readings WHERE meter_id = ?")->execute([$meterId]);
                    $pdo->prepare("DELETE FROM disconnections WHERE meter_id = ?")->execute([$meterId]);
                    $pdo->prepare("DELETE FROM field_inspections WHERE meter_id = ?")->execute([$meterId]);
                    $pdo->prepare("DELETE FROM meters WHERE meter_id = ?")->execute([$meterId]);
                } else {
                    $hasReadings = db()->fetch("SELECT COUNT(*) as c FROM meter_readings WHERE meter_id = ?", [$meterId])['c'] ?? 0;
                    $hasInvoices = db()->fetch("SELECT COUNT(*) as c FROM invoices WHERE meter_id = ?", [$meterId])['c'] ?? 0;
                    if ($hasReadings > 0 || $hasInvoices > 0) {
                        throw new Exception("This meter has {$hasReadings} readings and {$hasInvoices} invoices attached. Check 'Cascade Delete' in the delete confirmation to remove all related records.");
                    }
                    $pdo->prepare("DELETE FROM meters WHERE meter_id = ?")->execute([$meterId]);
                }

                $pdo->commit();
                Auth::logAction('Deleted Meter', 'meters', "Deleted meter {$meter['meter_serial']}");
                setFlash('success', "Meter <strong>{$meter['meter_serial']}</strong> successfully removed.");
            } catch (Exception $e) {
                if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
                setFlash('danger', 'Delete failed: ' . $e->getMessage());
            }
        }
        header('Location: ' . APP_URL . '/modules/meters/index.php');
        exit;
    }
}

// Build query
$where = ["1=1"];
$params = [];

if ($search) {
    $where[] = "(m.meter_serial LIKE ? OR c.account_no LIKE ? OR c.full_name LIKE ? OR m.seal_no LIKE ?)";
    $like = "%{$search}%";
    $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like;
}
if ($status) {
    $where[] = "m.status = ?";
    $params[] = $status;
}
if ($zoneId) {
    $where[] = "c.zone_id = ?";
    $params[] = $zoneId;
}
if ($meterType) {
    $where[] = "m.meter_type = ?";
    $params[] = $meterType;
}

$whereSql = implode(" AND ", $where);

// Count
$totalRows = (int)db()->fetch("SELECT COUNT(*) as total FROM meters m JOIN customers c ON m.customer_id = c.customer_id WHERE {$whereSql}", $params)['total'];
$totalPages = max(1, ceil($totalRows / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$sql = "SELECT m.*, c.account_no, c.full_name, c.phone, z.zone_code, z.zone_name
        FROM meters m
        JOIN customers c ON m.customer_id = c.customer_id
        JOIN zones z ON c.zone_id = z.zone_id
        WHERE {$whereSql}
        ORDER BY m.meter_id DESC
        LIMIT {$perPage} OFFSET {$offset}";
$meters = db()->fetchAll($sql, $params);

// Stats
$totalMeters = db()->fetch("SELECT COUNT(*) as cnt FROM meters")['cnt'];
$activeMeters = db()->fetch("SELECT COUNT(*) as cnt FROM meters WHERE status = 'Active'")['cnt'];
$faultyMeters = db()->fetch("SELECT COUNT(*) as cnt FROM meters WHERE status IN ('Faulty', 'Bypassed', 'Stolen')")['cnt'];

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
  <div class="page-title-wrap">
    <h1><i class="fa-solid fa-gauge" style="color:var(--gold);"></i> Water Meters Register</h1>
    <p>Complete lifecycle management of all customer water meters, calibrations, anti-tamper seals and current indices</p>
  </div>
  <div class="page-actions">
    <button onclick="openModal('addMeterModal')" class="btn btn-primary">
      <i class="fa-solid fa-plus-circle"></i> Register Meter
    </button>
    <a href="<?= APP_URL ?>/modules/meters/readings.php" class="btn btn-secondary">
      <i class="fa-solid fa-pencil"></i> Enter Readings
    </a>
    <a href="<?= APP_URL ?>/modules/meters/bulk_meters.php" class="btn btn-secondary">
      <i class="fa-solid fa-circle-nodes"></i> Bulk Meters / DMA
    </a>
  </div>
</div>

<!-- STATS -->
<div class="stats-grid mb-4">
  <div class="stat-card">
    <div class="stat-icon" style="background:rgba(30,58,138,0.1);color:var(--royal-blue);"><i class="fa-solid fa-gauge-high"></i></div>
    <div class="stat-info">
      <span class="stat-value"><?= number_format($totalMeters) ?></span>
      <span class="stat-label">Total Installed Meters</span>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:rgba(21,128,61,0.1);color:var(--success);"><i class="fa-solid fa-circle-check"></i></div>
    <div class="stat-info">
      <span class="stat-value"><?= number_format($activeMeters) ?></span>
      <span class="stat-label">Active & Metering</span>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:rgba(239,68,68,0.1);color:var(--danger);"><i class="fa-solid fa-triangle-exclamation"></i></div>
    <div class="stat-info">
      <span class="stat-value"><?= number_format($faultyMeters) ?></span>
      <span class="stat-label">Faulty / Bypassed / Stolen</span>
    </div>
  </div>
</div>

<!-- FILTER -->
<div class="card mb-4">
  <div class="card-body" style="padding:1rem 1.25rem;">
    <form method="GET" action="" style="display:flex;flex-wrap:wrap;gap:1rem;align-items:flex-end;">
      <div style="flex:1;min-width:200px;">
        <label class="form-label" style="font-size:0.8rem;">Search Meters</label>
        <input type="text" name="search" class="form-control" placeholder="Serial, Acc #, Customer, Seal No..." value="<?= htmlspecialchars($search) ?>">
      </div>
      <div style="min-width:140px;">
        <label class="form-label" style="font-size:0.8rem;">Status</label>
        <select name="status" class="form-control">
          <option value="">All Statuses</option>
          <option value="Active" <?= $status === 'Active' ? 'selected' : '' ?>>Active</option>
          <option value="Faulty" <?= $status === 'Faulty' ? 'selected' : '' ?>>Faulty</option>
          <option value="Bypassed" <?= $status === 'Bypassed' ? 'selected' : '' ?>>Bypassed</option>
          <option value="Removed" <?= $status === 'Removed' ? 'selected' : '' ?>>Removed</option>
          <option value="Stolen" <?= $status === 'Stolen' ? 'selected' : '' ?>>Stolen</option>
        </select>
      </div>
      <div style="min-width:160px;">
        <label class="form-label" style="font-size:0.8rem;">Zone</label>
        <select name="zone_id" class="form-control">
          <option value="">All Zones</option>
          <?php foreach ($zones as $z): ?>
            <option value="<?= $z['zone_id'] ?>" <?= $zoneId == $z['zone_id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($z['zone_code'] . ' - ' . $z['zone_name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div style="min-width:140px;">
        <label class="form-label" style="font-size:0.8rem;">Meter Type</label>
        <select name="meter_type" class="form-control">
          <option value="">All Types</option>
          <option value="Analogue" <?= $meterType === 'Analogue' ? 'selected' : '' ?>>Analogue</option>
          <option value="Digital" <?= $meterType === 'Digital' ? 'selected' : '' ?>>Digital</option>
          <option value="Smart" <?= $meterType === 'Smart' ? 'selected' : '' ?>>Smart</option>
          <option value="Pre-paid" <?= $meterType === 'Pre-paid' ? 'selected' : '' ?>>Pre-paid</option>
        </select>
      </div>
      <div style="display:flex;gap:0.5rem;">
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-filter"></i> Filter</button>
        <a href="<?= APP_URL ?>/modules/meters/index.php" class="btn btn-secondary"><i class="fa-solid fa-rotate-left"></i> Reset</a>
      </div>
    </form>
  </div>
</div>

<!-- METERS TABLE -->
<div class="card">
  <div class="card-header">
    <h3><i class="fa-solid fa-table" style="color:var(--royal-blue);"></i> Meter Inventory</h3>
    <span class="badge badge-light">Showing <?= count($meters) ?> of <?= number_format($totalRows) ?> meters</span>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table">
        <thead>
          <tr>
            <th>Meter Serial</th>
            <th>Customer & Account</th>
            <th>Zone</th>
            <th>Make / Model / Size</th>
            <th>Type</th>
            <th>Seal No</th>
            <th style="text-align:right;">Current Reading (m³)</th>
            <th>Last Read</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($meters)): ?>
            <tr><td colspan="10" class="text-center py-4 text-muted">No meters found.</td></tr>
          <?php else: ?>
            <?php foreach ($meters as $m): 
              $badge = match($m['status']) {
                'Active' => 'badge-success',
                'Faulty' => 'badge-danger',
                'Bypassed' => 'badge-danger',
                'Stolen' => 'badge-warning',
                default => 'badge-light'
              };
            ?>
              <tr>
                <td><strong style="color:var(--royal-blue);"><?= htmlspecialchars($m['meter_serial']) ?></strong></td>
                <td>
                  <a href="<?= APP_URL ?>/modules/customers/view.php?id=<?= $m['customer_id'] ?>" style="font-weight:600;color:var(--navy);">
                    <?= htmlspecialchars($m['full_name']) ?>
                  </a>
                  <div style="font-size:0.8rem;color:var(--text-muted);"><i class="fa-solid fa-id-card"></i> <?= htmlspecialchars($m['account_no']) ?></div>
                </td>
                <td><span class="badge badge-light"><?= htmlspecialchars($m['zone_code']) ?></span></td>
                <td><?= htmlspecialchars($m['meter_make'] ?? '-') ?> <?= htmlspecialchars($m['meter_model'] ?? '') ?> (<?= htmlspecialchars($m['meter_size']) ?>)</td>
                <td><?= htmlspecialchars($m['meter_type']) ?></td>
                <td><code><?= htmlspecialchars($m['seal_no'] ?? '-') ?></code></td>
                <td style="text-align:right;font-weight:700;color:var(--navy);font-size:1.05rem;">
                  <?= number_format($m['current_reading'], 1) ?>
                </td>
                <td><?= formatDate($m['last_read_date']) ?></td>
                <td><span class="badge <?= $badge ?>"><?= htmlspecialchars($m['status']) ?></span></td>
                <td>
                  <div style="display:flex;gap:0.35rem;align-items:center;">
                    <a href="<?= APP_URL ?>/modules/meters/readings.php?meter_id=<?= $m['meter_id'] ?>" class="btn btn-secondary btn-sm" title="Enter Reading" data-tooltip="Reading">
                      <i class="fa-solid fa-pencil"></i>
                    </a>
                    <button type="button" class="btn btn-primary btn-sm" title="Edit Meter" data-tooltip="Edit"
                            onclick='openEditMeter(<?= json_encode($m) ?>)'>
                      <i class="fa-solid fa-pen-to-square"></i>
                    </button>
                    <button type="button" class="btn btn-danger btn-sm" title="Delete Meter" data-tooltip="Delete"
                            onclick="promptDeleteMeter(<?= $m['meter_id'] ?>, '<?= addslashes($m['meter_serial']) ?>')">
                      <i class="fa-solid fa-trash-can"></i>
                    </button>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- PAGINATION -->
    <?php if ($totalPages > 1): ?>
      <div class="pagination-wrap" style="padding:1rem;display:flex;justify-content:space-between;align-items:center;">
        <span class="text-muted" style="font-size:0.85rem;">Page <?= $page ?> of <?= $totalPages ?> (Total: <?= number_format($totalRows) ?>)</span>
        <div class="pagination">
          <?php if ($page > 1): ?>
            <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>&zone_id=<?= $zoneId ?>" class="page-link">&laquo; Prev</a>
          <?php endif; ?>
          <?php for ($p = max(1, $page - 2); $p <= min($totalPages, $page + 2); $p++): ?>
            <a href="?page=<?= $p ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>&zone_id=<?= $zoneId ?>" class="page-link <?= $p == $page ? 'active' : '' ?>"><?= $p ?></a>
          <?php endfor; ?>
          <?php if ($page < $totalPages): ?>
            <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>&zone_id=<?= $zoneId ?>" class="page-link">Next &raquo;</a>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- ADD METER MODAL -->
<div id="addMeterModal" class="modal-backdrop">
  <div class="modal" style="max-width: 580px;">
    <div class="modal-header">
      <h3><i class="fa-solid fa-gauge" style="color:var(--gold);"></i> Register New Water Meter</h3>
      <button class="modal-close" onclick="closeModal('addMeterModal')">&times;</button>
    </div>
    <form method="POST" action="">
      <input type="hidden" name="action" value="add_meter">
      <div class="modal-body">
        <div class="row" style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
          <div class="form-group mb-3">
            <label class="form-label">Meter Serial <span class="text-danger">*</span></label>
            <input type="text" name="meter_serial" class="form-control" placeholder="e.g. THW-M-90123" required>
          </div>
          <div class="form-group mb-3">
            <label class="form-label">Assign Customer <span class="text-danger">*</span></label>
            <select name="customer_id" class="form-control" required>
              <option value="">-- Select Customer --</option>
              <?php 
              $allCustomers = db()->fetchAll("SELECT customer_id, account_no, full_name FROM customers ORDER BY full_name ASC");
              foreach ($allCustomers as $u): 
              ?>
                <option value="<?= $u['customer_id'] ?>"><?= htmlspecialchars($u['account_no'] . ' - ' . $u['full_name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="row" style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
          <div class="form-group mb-3">
            <label class="form-label">Make / Brand</label>
            <input type="text" name="meter_make" class="form-control" placeholder="e.g. Kent, Elster">
          </div>
          <div class="form-group mb-3">
            <label class="form-label">Model</label>
            <input type="text" name="meter_model" class="form-control" placeholder="e.g. V100">
          </div>
        </div>

        <div class="row" style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem;">
          <div class="form-group mb-3">
            <label class="form-label">Size</label>
            <select name="meter_size" class="form-control">
              <option value='1/2"'>1/2" (15mm)</option>
              <option value='3/4"'>3/4" (20mm)</option>
              <option value='1"'>1" (25mm)</option>
              <option value='2"'>2" (50mm)</option>
            </select>
          </div>
          <div class="form-group mb-3">
            <label class="form-label">Type</label>
            <select name="meter_type" class="form-control">
              <option value="Analogue">Analogue</option>
              <option value="Digital">Digital</option>
              <option value="Smart">Smart</option>
              <option value="Pre-paid">Pre-paid</option>
            </select>
          </div>
          <div class="form-group mb-3">
            <label class="form-label">Initial Index (m³)</label>
            <input type="number" step="0.01" name="initial_reading" class="form-control" value="0.00">
          </div>
        </div>

        <div class="row" style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
          <div class="form-group mb-3">
            <label class="form-label">Anti-Tamper Seal No</label>
            <input type="text" name="seal_no" class="form-control" placeholder="e.g. SL-9014">
          </div>
          <div class="form-group mb-3">
            <label class="form-label">Installation Date</label>
            <input type="date" name="installation_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('addMeterModal')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> Register Meter</button>
      </div>
    </form>
  </div>
</div>

<!-- EDIT METER MODAL -->
<div id="editMeterModal" class="modal-backdrop">
  <div class="modal" style="max-width: 580px;">
    <div class="modal-header">
      <h3><i class="fa-solid fa-pen-to-square" style="color:var(--gold);"></i> Edit Water Meter</h3>
      <button class="modal-close" onclick="closeModal('editMeterModal')">&times;</button>
    </div>
    <form method="POST" action="">
      <input type="hidden" name="action" value="update_meter">
      <input type="hidden" name="meter_id" id="editMeterId" value="">
      <div class="modal-body">
        <div class="row" style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
          <div class="form-group mb-3">
            <label class="form-label">Meter Serial <span class="text-danger">*</span></label>
            <input type="text" name="meter_serial" id="editMeterSerial" class="form-control" required>
          </div>
          <div class="form-group mb-3">
            <label class="form-label">Assign Customer <span class="text-danger">*</span></label>
            <select name="customer_id" id="editMeterCustomerId" class="form-control" required>
              <?php foreach ($allCustomers as $u): ?>
                <option value="<?= $u['customer_id'] ?>"><?= htmlspecialchars($u['account_no'] . ' - ' . $u['full_name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="row" style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
          <div class="form-group mb-3">
            <label class="form-label">Make / Brand</label>
            <input type="text" name="meter_make" id="editMeterMake" class="form-control">
          </div>
          <div class="form-group mb-3">
            <label class="form-label">Model</label>
            <input type="text" name="meter_model" id="editMeterModel" class="form-control">
          </div>
        </div>

        <div class="row" style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem;">
          <div class="form-group mb-3">
            <label class="form-label">Size</label>
            <select name="meter_size" id="editMeterSize" class="form-control">
              <option value='1/2"'>1/2" (15mm)</option>
              <option value='3/4"'>3/4" (20mm)</option>
              <option value='1"'>1" (25mm)</option>
              <option value='2"'>2" (50mm)</option>
            </select>
          </div>
          <div class="form-group mb-3">
            <label class="form-label">Type</label>
            <select name="meter_type" id="editMeterType" class="form-control">
              <option value="Analogue">Analogue</option>
              <option value="Digital">Digital</option>
              <option value="Smart">Smart</option>
              <option value="Pre-paid">Pre-paid</option>
            </select>
          </div>
          <div class="form-group mb-3">
            <label class="form-label">Status</label>
            <select name="status" id="editMeterStatus" class="form-control">
              <option value="Active">Active</option>
              <option value="Faulty">Faulty</option>
              <option value="Bypassed">Bypassed</option>
              <option value="Removed">Removed</option>
              <option value="Stolen">Stolen</option>
              <option value="Condemned">Condemned</option>
            </select>
          </div>
        </div>

        <div class="row" style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem;">
          <div class="form-group mb-3">
            <label class="form-label">Current Index (m³)</label>
            <input type="number" step="0.1" name="current_reading" id="editMeterReading" class="form-control">
          </div>
          <div class="form-group mb-3">
            <label class="form-label">Seal No</label>
            <input type="text" name="seal_no" id="editMeterSeal" class="form-control">
          </div>
          <div class="form-group mb-3">
            <label class="form-label">Install Date</label>
            <input type="date" name="installation_date" id="editMeterDate" class="form-control">
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('editMeterModal')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> Save Changes</button>
      </div>
    </form>
  </div>
</div>

<!-- DELETE METER MODAL -->
<div id="deleteMeterModal" class="modal-backdrop">
  <div class="modal" style="max-width: 480px;">
    <div class="modal-header">
      <h3 style="color:var(--danger);"><i class="fa-solid fa-triangle-exclamation"></i> Delete Meter</h3>
      <button class="modal-close" onclick="closeModal('deleteMeterModal')">&times;</button>
    </div>
    <form method="POST" action="">
      <input type="hidden" name="action" value="delete_meter">
      <input type="hidden" name="meter_id" id="deleteMeterId" value="">
      <div class="modal-body">
        <p>Are you sure you want to permanently delete meter <strong id="deleteMeterSerial"></strong>?</p>
        <div style="background:var(--danger-50, #fef2f2);border:1px solid var(--danger-100, #fee2e2);border-radius:8px;padding:12px;margin:12px 0;">
          <label style="display:flex;align-items:flex-start;gap:8px;font-size:0.85rem;color:var(--danger);cursor:pointer;">
            <input type="checkbox" name="cascade_delete" value="1" style="margin-top:3px;">
            <span><strong>Cascade Delete:</strong> Remove all readings, invoices and records associated with this meter.</span>
          </label>
        </div>
        <p style="font-size:0.8rem;color:var(--text-muted);margin:0;">This action cannot be undone.</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('deleteMeterModal')">Cancel</button>
        <button type="submit" class="btn btn-danger"><i class="fa-solid fa-trash-can"></i> Confirm Delete</button>
      </div>
    </form>
  </div>
</div>

<script>
function openEditMeter(m) {
  document.getElementById('editMeterId').value = m.meter_id;
  document.getElementById('editMeterSerial').value = m.meter_serial || '';
  document.getElementById('editMeterCustomerId').value = m.customer_id;
  document.getElementById('editMeterMake').value = m.meter_make || '';
  document.getElementById('editMeterModel').value = m.meter_model || '';
  document.getElementById('editMeterSize').value = m.meter_size || '1/2"';
  document.getElementById('editMeterType').value = m.meter_type || 'Analogue';
  document.getElementById('editMeterStatus').value = m.status || 'Active';
  document.getElementById('editMeterReading').value = m.current_reading || 0;
  document.getElementById('editMeterSeal').value = m.seal_no || '';
  document.getElementById('editMeterDate').value = m.installation_date || '';
  openModal('editMeterModal');
}

function promptDeleteMeter(id, serial) {
  document.getElementById('deleteMeterId').value = id;
  document.getElementById('deleteMeterSerial').textContent = serial;
  openModal('deleteMeterModal');
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
