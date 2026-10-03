<?php
/**
 * THIWASCO MIS - Bulk Meters & DMA Inflow Monitoring
 */
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';

Auth::requirePermission('meters');

$pageTitle = 'Bulk Meters & DMA Network';
$activePage = 'bulk_meters';
$activeSection = 'meters';

$zones = db()->fetchAll("SELECT * FROM zones WHERE is_active = 1 ORDER BY zone_name ASC");

// Handle Add Bulk Meter POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_bulk_meter') {
    $serial = clean($_POST['meter_serial'] ?? '');
    $name = clean($_POST['meter_name'] ?? '');
    $zoneId = (int)($_POST['zone_id'] ?? 0);
    $size = clean($_POST['meter_size'] ?? '3"');
    $location = clean($_POST['location_description'] ?? '');
    $installDate = clean($_POST['installation_date'] ?? date('Y-m-d'));

    if (!empty($serial) && $zoneId > 0) {
        db()->query("INSERT INTO bulk_meters (zone_id, meter_serial, meter_name, meter_size, installation_date, location_description, is_active)
                     VALUES (?, ?, ?, ?, ?, ?, 1)",
                     [$zoneId, $serial, $name, $size, $installDate, $location]);
        Auth::logAction('Added Bulk Meter', 'meters', "Added bulk meter {$serial} ({$name})");
        setFlash('success', "Bulk meter {$serial} registered successfully.");
        header('Location: ' . APP_URL . '/modules/meters/bulk_meters.php');
        exit;
    }
}

// Handle Add Bulk Reading POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_bulk_reading') {
    $bulkMeterId = (int)$_POST['bulk_meter_id'];
    $readingDate = clean($_POST['reading_date'] ?? date('Y-m-d'));
    $prevReading = (float)$_POST['previous_reading'];
    $currReading = (float)$_POST['current_reading'];
    $notes = clean($_POST['notes'] ?? '');

    if ($currReading >= $prevReading) {
        db()->query("INSERT INTO bulk_meter_readings (bulk_meter_id, read_by, reading_date, previous_reading, current_reading, notes)
                     VALUES (?, ?, ?, ?, ?, ?)",
                     [$bulkMeterId, Auth::id(), $readingDate, $prevReading, $currReading, $notes]);
        Auth::logAction('Bulk Reading', 'meters', "Bulk meter ID {$bulkMeterId} read: {$currReading} m³");
        setFlash('success', 'Bulk meter reading logged successfully.');
        header('Location: ' . APP_URL . '/modules/meters/bulk_meters.php');
        exit;
    } else {
        setFlash('danger', 'Current reading cannot be less than previous reading.');
    }
}

// Fetch bulk meters with latest reading
$bulkMeters = db()->fetchAll("SELECT b.*, z.zone_code, z.zone_name,
                                     (SELECT current_reading FROM bulk_meter_readings WHERE bulk_meter_id = b.bulk_meter_id ORDER BY reading_date DESC LIMIT 1) as latest_reading,
                                     (SELECT reading_date FROM bulk_meter_readings WHERE bulk_meter_id = b.bulk_meter_id ORDER BY reading_date DESC LIMIT 1) as last_read_date
                              FROM bulk_meters b
                              JOIN zones z ON b.zone_id = z.zone_id
                              ORDER BY z.zone_code ASC, b.meter_serial ASC");

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
  <div class="page-title-wrap">
    <h1><i class="fa-solid fa-circle-nodes" style="color:var(--gold);"></i> Bulk Meters & DMA Monitoring</h1>
    <p>Track district metered area (DMA) transmission inflows, zonal master meters and network inlet volume</p>
  </div>
  <div class="page-actions">
    <button onclick="openModal('addBulkMeterModal')" class="btn btn-primary">
      <i class="fa-solid fa-plus-circle"></i> Add Bulk Meter
    </button>
    <a href="<?= APP_URL ?>/modules/nrw/dashboard.php" class="btn btn-secondary">
      <i class="fa-solid fa-chart-area"></i> NRW Dashboard
    </a>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <h3><i class="fa-solid fa-gauge-simple-high" style="color:var(--royal-blue);"></i> Active Zonal Bulk Inflow Meters</h3>
    <span class="badge badge-info"><?= count($bulkMeters) ?> Transmission Meters</span>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table">
        <thead>
          <tr>
            <th>Serial & Name</th>
            <th>Zone / DMA</th>
            <th>Diameter</th>
            <th>Location Description</th>
            <th style="text-align:right;">Latest Index (m³)</th>
            <th>Last Read Date</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($bulkMeters)): ?>
            <tr><td colspan="8" class="text-center py-4 text-muted">No bulk meters configured.</td></tr>
          <?php else: ?>
            <?php foreach ($bulkMeters as $b): 
              $readingVal = (float)($b['latest_reading'] ?? 0);
            ?>
              <tr>
                <td>
                  <strong style="color:var(--royal-blue);"><?= htmlspecialchars($b['meter_serial']) ?></strong>
                  <div style="font-size:0.85rem;color:var(--text-color);"><?= htmlspecialchars($b['meter_name']) ?></div>
                </td>
                <td>
                  <span class="badge badge-primary"><?= htmlspecialchars($b['zone_code']) ?></span>
                  <div style="font-size:0.8rem;color:var(--text-muted);"><?= htmlspecialchars($b['zone_name']) ?></div>
                </td>
                <td><strong><?= htmlspecialchars($b['meter_size']) ?></strong></td>
                <td><small><?= htmlspecialchars($b['location_description'] ?? '-') ?></small></td>
                <td style="text-align:right;font-weight:800;font-size:1.15rem;color:var(--navy);">
                  <?= number_format($readingVal, 1) ?>
                </td>
                <td><?= formatDate($b['last_read_date']) ?></td>
                <td><span class="badge badge-success">Active</span></td>
                <td>
                  <button type="button" class="btn btn-primary btn-sm" onclick="openBulkReadingModal(<?= $b['bulk_meter_id'] ?>, '<?= htmlspecialchars($b['meter_serial']) ?>', '<?= htmlspecialchars($b['meter_name']) ?>', <?= $readingVal ?>)">
                    <i class="fa-solid fa-pencil"></i> Log Inflow
                  </button>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- ADD BULK METER MODAL -->
<div id="addBulkMeterModal" class="modal-backdrop">
  <div class="modal" style="max-width: 500px;">
    <div class="modal-header">
      <h3><i class="fa-solid fa-circle-nodes" style="color:var(--gold);"></i> Add Bulk Meter</h3>
      <button class="modal-close" onclick="closeModal('addBulkMeterModal')">&times;</button>
    </div>
    <form method="POST" action="">
      <input type="hidden" name="action" value="add_bulk_meter">
      <div class="modal-body">
        <div class="form-group mb-3">
          <label class="form-label">Meter Serial <span class="text-danger">*</span></label>
          <input type="text" name="meter_serial" class="form-control" placeholder="e.g. BLK-ZN01-INLET" required>
        </div>
        <div class="form-group mb-3">
          <label class="form-label">Meter Name / Identifier <span class="text-danger">*</span></label>
          <input type="text" name="meter_name" class="form-control" placeholder="e.g. Town Centre Main DMA Feed" required>
        </div>
        <div class="row" style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
          <div class="form-group mb-3">
            <label class="form-label">Zone / DMA <span class="text-danger">*</span></label>
            <select name="zone_id" class="form-control" required>
              <?php foreach ($zones as $z): ?>
                <option value="<?= $z['zone_id'] ?>"><?= htmlspecialchars($z['zone_code'] . ' - ' . $z['zone_name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group mb-3">
            <label class="form-label">Diameter Size</label>
            <select name="meter_size" class="form-control">
              <option value='3" (80mm)'>3" (80mm)</option>
              <option value='4" (100mm)'>4" (100mm)</option>
              <option value='6" (150mm)'>6" (150mm)</option>
              <option value='8" (200mm)'>8" (200mm)</option>
            </select>
          </div>
        </div>
        <div class="form-group mb-3">
          <label class="form-label">Physical Location / Chamber</label>
          <textarea name="location_description" class="form-control" rows="2" placeholder="e.g. Chamber next to Thika Primary School gate"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('addBulkMeterModal')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> Save Bulk Meter</button>
      </div>
    </form>
  </div>
</div>

<!-- ENTER BULK READING MODAL -->
<div id="bulkReadingModal" class="modal-backdrop">
  <div class="modal" style="max-width: 480px;">
    <div class="modal-header">
      <h3><i class="fa-solid fa-pencil" style="color:var(--gold);"></i> Log Bulk Meter Reading</h3>
      <button class="modal-close" onclick="closeModal('bulkReadingModal')">&times;</button>
    </div>
    <form method="POST" action="">
      <input type="hidden" name="action" value="add_bulk_reading">
      <input type="hidden" name="bulk_meter_id" id="bReadId" value="">
      <input type="hidden" name="previous_reading" id="bPrevReading" value="0">
      <div class="modal-body">
        <div class="alert alert-info mb-3">
          Logging reading for <strong id="bMeterName"></strong> (<span id="bMeterSerial"></span>)
        </div>
        <div style="background:var(--bg-light);padding:0.75rem;border-radius:6px;margin-bottom:1rem;">
          <small class="text-muted">Previous Meter Index:</small>
          <div style="font-size:1.3rem;font-weight:700;color:var(--navy);" id="bPrevDisp">0.0 m³</div>
        </div>
        <div class="form-group mb-3">
          <label class="form-label">Current Meter Index (m³) <span class="text-danger">*</span></label>
          <input type="number" step="0.1" name="current_reading" id="bCurrInput" class="form-control" style="font-size:1.2rem;font-weight:700;color:var(--royal-blue);" required oninput="calcBulkDelivered()">
        </div>
        <div class="alert alert-success mb-3" id="bDiffBox" style="display:none;">
          Calculated Inflow: <strong id="bDiffDisp">0.0 m³</strong>
        </div>
        <div class="form-group mb-3">
          <label class="form-label">Date Read <span class="text-danger">*</span></label>
          <input type="date" name="reading_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
        </div>
        <div class="form-group mb-3">
          <label class="form-label">Notes</label>
          <textarea name="notes" class="form-control" rows="2" placeholder="Pressure notes, chamber status..."></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('bulkReadingModal')">Cancel</button>
        <button type="submit" class="btn btn-success"><i class="fa-solid fa-save"></i> Save Reading</button>
      </div>
    </form>
  </div>
</div>

<script>
let curPrev = 0;
function openBulkReadingModal(id, serial, name, prev) {
  curPrev = prev;
  document.getElementById('bReadId').value = id;
  document.getElementById('bMeterSerial').textContent = serial;
  document.getElementById('bMeterName').textContent = name;
  document.getElementById('bPrevReading').value = prev;
  document.getElementById('bPrevDisp').textContent = prev.toLocaleString() + ' m³';
  document.getElementById('bCurrInput').value = '';
  document.getElementById('bDiffBox').style.display = 'none';
  openModal('bulkReadingModal');
}

function calcBulkDelivered() {
  const curr = parseFloat(document.getElementById('bCurrInput').value);
  if (!isNaN(curr)) {
    const diff = curr - curPrev;
    document.getElementById('bDiffBox').style.display = 'block';
    document.getElementById('bDiffDisp').textContent = diff.toLocaleString(undefined, {minimumFractionDigits: 1}) + ' m³';
  }
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
