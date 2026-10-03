<?php
/**
 * THIWASCO MIS - Field Inspections & Revenue Protection Audits
 */
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';

Auth::requirePermission('revenue');

$pageTitle = 'Field Inspections';
$activePage = 'rp_inspections';
$activeSection = 'revenue';

$zones = db()->fetchAll("SELECT * FROM zones WHERE is_active = 1 ORDER BY zone_name ASC");
$types = db()->fetchAll("SELECT * FROM inspection_types ORDER BY type_name ASC");

// Filters
$status = clean($_GET['status'] ?? '');
$typeId = !empty($_GET['type_id']) ? (int)$_GET['type_id'] : '';
$zoneId = !empty($_GET['zone_id']) ? (int)$_GET['zone_id'] : '';
$search = clean($_GET['search'] ?? '');
$prefillCustId = (int)($_GET['customer_id'] ?? 0);
$prefillMeterId = (int)($_GET['meter_id'] ?? 0);

// Handle Add Inspection POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_inspection') {
    $custId = !empty($_POST['customer_id']) ? (int)$_POST['customer_id'] : null;
    $meterId = !empty($_POST['meter_id']) ? (int)$_POST['meter_id'] : null;
    $zoneId = (int)$_POST['zone_id'];
    $typeId = (int)$_POST['inspection_type_id'];
    $inspDate = clean($_POST['inspection_date'] ?? date('Y-m-d'));
    $gpsLat = !empty($_POST['gps_lat']) ? (float)$_POST['gps_lat'] : null;
    $gpsLng = !empty($_POST['gps_lng']) ? (float)$_POST['gps_lng'] : null;
    $findings = clean($_POST['findings'] ?? '');
    $lossM3 = !empty($_POST['estimated_loss_m3']) ? (float)$_POST['estimated_loss_m3'] : 0;
    $lossKes = !empty($_POST['estimated_revenue_loss']) ? (float)$_POST['estimated_revenue_loss'] : 0;
    $penalty = !empty($_POST['penalty_amount']) ? (float)$_POST['penalty_amount'] : 0;
    $actionTaken = clean($_POST['action_taken'] ?? '');

    // Photo uploads handling
    $photo1 = null;
    if (isset($_FILES['photo1']) && $_FILES['photo1']['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($_FILES['photo1']['name'], PATHINFO_EXTENSION);
        $fileName = 'insp_' . time() . '_1.' . $ext;
        $dest = UPLOAD_PATH . 'inspection_photos/' . $fileName;
        if (move_uploaded_file($_FILES['photo1']['tmp_name'], $dest)) {
            $photo1 = $fileName;
        }
    }

    if ($zoneId > 0 && $typeId > 0 && !empty($findings)) {
        // Generate Inspection Reference
        $ref = generateRef('INSP', 'field_inspections', 'inspection_ref');

        db()->query("INSERT INTO field_inspections 
            (inspection_ref, customer_id, meter_id, zone_id, inspection_type_id, inspected_by, inspection_date, gps_lat, gps_lng, findings, evidence_photo1, estimated_loss_m3, estimated_revenue_loss, status, action_taken, penalty_amount)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Open', ?, ?)",
            [$ref, $custId, $meterId, $zoneId, $typeId, Auth::id(), $inspDate, $gpsLat, $gpsLng, $findings, $photo1, $lossM3, $lossKes, $actionTaken, $penalty]);

        Auth::logAction('Logged Field Inspection', 'revenue', "Created inspection {$ref}");
        setFlash('success', "Field Inspection <strong>{$ref}</strong> successfully created.");
        header('Location: ' . APP_URL . '/modules/revenue_protection/inspections.php');
        exit;
    } else {
        setFlash('danger', 'Please provide zone, violation type and inspection findings.');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_inspection') {
    $inspId = (int)($_POST['inspection_id'] ?? 0);
    if ($inspId > 0) {
        $insp = db()->fetch("SELECT inspection_ref FROM field_inspections WHERE inspection_id = ?", [$inspId]);
        if ($insp) {
            db()->query("DELETE FROM field_inspections WHERE inspection_id = ?", [$inspId]);
            Auth::logAction('Deleted Field Inspection', 'revenue', "Deleted inspection {$insp['inspection_ref']}");
            setFlash('success', "Inspection record <strong>{$insp['inspection_ref']}</strong> was deleted.");
        }
        header('Location: ' . APP_URL . '/modules/revenue_protection/inspections.php');
        exit;
    }
}

// Build query
$where = ["1=1"];
$params = [];

if ($status) {
    $where[] = "i.status = ?";
    $params[] = $status;
}
if ($typeId) {
    $where[] = "i.inspection_type_id = ?";
    $params[] = $typeId;
}
if ($zoneId) {
    $where[] = "i.zone_id = ?";
    $params[] = $zoneId;
}
if ($search) {
    $where[] = "(i.inspection_ref LIKE ? OR c.account_no LIKE ? OR c.full_name LIKE ? OR m.meter_serial LIKE ?)";
    $like = "%{$search}%";
    $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like;
}

$whereSql = implode(" AND ", $where);

$sql = "SELECT i.*, it.type_name, z.zone_code, z.zone_name, c.account_no, c.full_name, c.phone, m.meter_serial, u.full_name as inspector_name
        FROM field_inspections i
        JOIN inspection_types it ON i.inspection_type_id = it.type_id
        JOIN zones z ON i.zone_id = z.zone_id
        LEFT JOIN customers c ON i.customer_id = c.customer_id
        LEFT JOIN meters m ON i.meter_id = m.meter_id
        JOIN users u ON i.inspected_by = u.user_id
        WHERE {$whereSql}
        ORDER BY i.inspection_date DESC, i.inspection_id DESC";
$inspections = db()->fetchAll($sql, $params);

// Stats
$openCases = db()->fetch("SELECT COUNT(*) as cnt FROM field_inspections WHERE status = 'Open'")['cnt'];
$resolvedCases = db()->fetch("SELECT COUNT(*) as cnt FROM field_inspections WHERE status = 'Resolved'")['cnt'];
$penaltiesTotal = db()->fetch("SELECT COALESCE(SUM(penalty_amount), 0) as total FROM field_inspections")['total'];

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
  <div class="page-title-wrap">
    <h1><i class="fa-solid fa-clipboard-check" style="color:var(--gold);"></i> Revenue Protection Field Inspections</h1>
    <p>Document field investigations: meter tampering, bypasses, illegal abstractions, fines, and evidence records</p>
  </div>
  <div class="page-actions">
    <button onclick="openModal('addInspectionModal')" class="btn btn-primary">
      <i class="fa-solid fa-plus-circle"></i> Log Field Inspection
    </button>
    <a href="<?= APP_URL ?>/modules/revenue_protection/illegal.php" class="btn btn-secondary">
      <i class="fa-solid fa-ban"></i> Illegal Connections
    </a>
  </div>
</div>

<!-- STATS -->
<div class="stats-grid mb-4">
  <div class="stat-card">
    <div class="stat-icon" style="background:rgba(239,68,68,0.1);color:var(--danger);"><i class="fa-solid fa-folder-open"></i></div>
    <div class="stat-info">
      <span class="stat-value"><?= number_format($openCases) ?></span>
      <span class="stat-label">Open Active Investigations</span>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:rgba(21,128,61,0.1);color:var(--success);"><i class="fa-solid fa-circle-check"></i></div>
    <div class="stat-info">
      <span class="stat-value"><?= number_format($resolvedCases) ?></span>
      <span class="stat-label">Resolved / Recovered</span>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:rgba(234,179,8,0.1);color:var(--gold);"><i class="fa-solid fa-gavel"></i></div>
    <div class="stat-info">
      <span class="stat-value"><?= formatCurrency($penaltiesTotal) ?></span>
      <span class="stat-label">Penalties & Fines Assessed</span>
    </div>
  </div>
</div>

<!-- FILTER -->
<div class="card mb-4">
  <div class="card-body" style="padding:1rem 1.25rem;">
    <form method="GET" action="" style="display:flex;flex-wrap:wrap;gap:1rem;align-items:flex-end;">
      <div style="flex:1;min-width:200px;">
        <label class="form-label" style="font-size:0.8rem;">Search</label>
        <input type="text" name="search" class="form-control" placeholder="Ref #, Acc #, Customer, Meter..." value="<?= htmlspecialchars($search) ?>">
      </div>
      <div style="min-width:140px;">
        <label class="form-label" style="font-size:0.8rem;">Status</label>
        <select name="status" class="form-control">
          <option value="">All Statuses</option>
          <option value="Open" <?= $status === 'Open' ? 'selected' : '' ?>>Open</option>
          <option value="Under Investigation" <?= $status === 'Under Investigation' ? 'selected' : '' ?>>Under Investigation</option>
          <option value="Resolved" <?= $status === 'Resolved' ? 'selected' : '' ?>>Resolved</option>
          <option value="Closed" <?= $status === 'Closed' ? 'selected' : '' ?>>Closed</option>
        </select>
      </div>
      <div style="min-width:180px;">
        <label class="form-label" style="font-size:0.8rem;">Violation Type</label>
        <select name="type_id" class="form-control">
          <option value="">All Types</option>
          <?php foreach ($types as $t): ?>
            <option value="<?= $t['type_id'] ?>" <?= $typeId == $t['type_id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($t['type_name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div style="display:flex;gap:0.5rem;">
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-filter"></i> Filter</button>
        <a href="<?= APP_URL ?>/modules/revenue_protection/inspections.php" class="btn btn-secondary"><i class="fa-solid fa-rotate-left"></i> Reset</a>
      </div>
    </form>
  </div>
</div>

<!-- INSPECTIONS TABLE -->
<div class="card">
  <div class="card-header">
    <h3><i class="fa-solid fa-shield-halved" style="color:var(--royal-blue);"></i> Inspection Case Files</h3>
    <span class="badge badge-light"><?= count($inspections) ?> Cases</span>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table">
        <thead>
          <tr>
            <th>Ref #</th>
            <th>Date</th>
            <th>Customer & Premises</th>
            <th>Zone</th>
            <th>Violation Type</th>
            <th>Est. Loss (KES)</th>
            <th>Penalty (KES)</th>
            <th>Inspector</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($inspections)): ?>
            <tr><td colspan="10" class="text-center py-4 text-muted">No inspection records found.</td></tr>
          <?php else: ?>
            <?php foreach ($inspections as $ins): 
              $badge = match($ins['status']) {
                'Open' => 'badge-danger',
                'Under Investigation' => 'badge-warning',
                'Resolved' => 'badge-success',
                default => 'badge-light'
              };
            ?>
              <tr>
                <td>
                  <a href="<?= APP_URL ?>/modules/revenue_protection/view.php?id=<?= $ins['inspection_id'] ?>" style="font-weight:700;color:var(--royal-blue);">
                    <?= htmlspecialchars($ins['inspection_ref']) ?>
                  </a>
                </td>
                <td><?= formatDate($ins['inspection_date']) ?></td>
                <td>
                  <?php if ($ins['customer_id']): ?>
                    <strong><?= htmlspecialchars($ins['full_name']) ?></strong>
                    <div style="font-size:0.75rem;color:var(--text-muted);"><i class="fa-solid fa-id-card"></i> <?= htmlspecialchars($ins['account_no']) ?></div>
                  <?php else: ?>
                    <span class="badge badge-warning">Unregistered Premises</span>
                  <?php endif; ?>
                </td>
                <td><span class="badge badge-light"><?= htmlspecialchars($ins['zone_code']) ?></span></td>
                <td><strong><?= htmlspecialchars($ins['type_name']) ?></strong></td>
                <td style="color:var(--danger);font-weight:600;"><?= formatCurrency($ins['estimated_revenue_loss']) ?></td>
                <td style="color:var(--gold);font-weight:700;"><?= formatCurrency($ins['penalty_amount']) ?></td>
                <td><small><?= htmlspecialchars($ins['inspector_name']) ?></small></td>
                <td><span class="badge <?= $badge ?>"><?= htmlspecialchars($ins['status']) ?></span></td>
                <td>
                  <div style="display:inline-flex;gap:4px;">
                    <a href="<?= APP_URL ?>/modules/revenue_protection/view.php?id=<?= $ins['inspection_id'] ?>" class="btn btn-secondary btn-sm" title="View Case File">
                      <i class="fa-solid fa-eye"></i> View
                    </a>
                    <form method="POST" action="" style="display:inline;" onsubmit="return confirm('Delete inspection <?= htmlspecialchars(addslashes($ins['inspection_ref'])) ?>?');">
                      <input type="hidden" name="action" value="delete_inspection">
                      <input type="hidden" name="inspection_id" value="<?= $ins['inspection_id'] ?>">
                      <button type="submit" class="btn btn-danger btn-sm" title="Delete Inspection">
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

<!-- ADD INSPECTION MODAL -->
<div id="addInspectionModal" class="modal-overlay">
  <div class="modal" style="max-width: 650px;">
    <div class="modal-header">
      <h3><i class="fa-solid fa-shield-halved" style="color:var(--gold);"></i> Log Field Inspection Report</h3>
      <button class="modal-close" onclick="closeModal('addInspectionModal')">&times;</button>
    </div>
    <form method="POST" action="" enctype="multipart/form-data">
      <input type="hidden" name="action" value="add_inspection">
      <div class="modal-body">
        
        <div class="row" style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
          <div class="form-group mb-3">
            <label class="form-label">Customer Account (Optional if illegal)</label>
            <select name="customer_id" class="form-control">
              <option value="">-- Anonymous / Direct Off-take --</option>
              <?php 
              $custList = db()->fetchAll("SELECT customer_id, account_no, full_name FROM customers ORDER BY full_name ASC LIMIT 200");
              foreach ($custList as $c): 
              ?>
                <option value="<?= $c['customer_id'] ?>" <?= $prefillCustId == $c['customer_id'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars($c['account_no'] . ' - ' . $c['full_name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group mb-3">
            <label class="form-label">Zone / Area <span class="text-danger">*</span></label>
            <select name="zone_id" class="form-control" required>
              <?php foreach ($zones as $z): ?>
                <option value="<?= $z['zone_id'] ?>"><?= htmlspecialchars($z['zone_code'] . ' - ' . $z['zone_name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="row" style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
          <div class="form-group mb-3">
            <label class="form-label">Violation Category <span class="text-danger">*</span></label>
            <select name="inspection_type_id" class="form-control" required>
              <?php foreach ($types as $t): ?>
                <option value="<?= $t['type_id'] ?>"><?= htmlspecialchars($t['type_name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group mb-3">
            <label class="form-label">Inspection Date <span class="text-danger">*</span></label>
            <input type="date" name="inspection_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
          </div>
        </div>

        <div class="form-group mb-3">
          <label class="form-label">Findings & Observations <span class="text-danger">*</span></label>
          <textarea name="findings" class="form-control" rows="3" placeholder="Describe violation, pipe size, meter condition, bypass setup..." required></textarea>
        </div>

        <div class="row" style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem;">
          <div class="form-group mb-3">
            <label class="form-label">Estimated Loss (m³)</label>
            <input type="number" step="0.1" name="estimated_loss_m3" class="form-control" placeholder="e.g. 50">
          </div>
          <div class="form-group mb-3">
            <label class="form-label">Est. Revenue Loss (KES)</label>
            <input type="number" step="0.01" name="estimated_revenue_loss" class="form-control" placeholder="e.g. 3750">
          </div>
          <div class="form-group mb-3">
            <label class="form-label">Penalty Assessed (KES)</label>
            <input type="number" step="0.01" name="penalty_amount" class="form-control" placeholder="e.g. 30000">
          </div>
        </div>

        <div class="row" style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
          <div class="form-group mb-3">
            <label class="form-label">Action Taken</label>
            <input type="text" name="action_taken" class="form-control" placeholder="e.g. Disconnected at main, meter confiscated">
          </div>
          <div class="form-group mb-3">
            <label class="form-label">Evidence Photo</label>
            <input type="file" name="photo1" class="form-control" accept="image/*">
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('addInspectionModal')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> Save Inspection</button>
      </div>
    </form>
  </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
