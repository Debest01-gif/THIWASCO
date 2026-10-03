<?php
/**
 * THIWASCO MIS - Assets: Maintenance Records Log
 */
require_once __DIR__ . "/../../includes/config.php";
require_once __DIR__ . "/../../includes/auth.php";
Auth::check();

$pageTitle = "Asset Maintenance Log";
$activePage = "assets";
$activeSection = "assets";

$assetId = (int)($_GET["asset_id"] ?? 0);
$asset = $assetId ? db()->fetch("SELECT * FROM assets WHERE asset_id = ?", [$assetId]) : null;

// Handle: add maintenance record
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action"]) && $_POST["action"] === "add_maint") {
    $aId = (int)$_POST["asset_id"];
    $mType = clean($_POST["maintenance_type"] ?? "Preventive");
    $mDate = clean($_POST["maintenance_date"] ?? date("Y-m-d"));
    $desc = clean($_POST["description"] ?? "");
    $cost = (float)($_POST["cost"] ?? 0);
    $performedBy = clean($_POST["performed_by"] ?? "");
    $nextDate = clean($_POST["next_maintenance"] ?? "");
    $status = clean($_POST["status"] ?? "Completed");

    if ($aId && $desc) {
        db()->query("INSERT INTO maintenance_records (asset_id, maintenance_type, maintenance_date, description, cost, performed_by, next_maintenance, status, created_by)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                     [$aId, $mType, $mDate, $desc, $cost, $performedBy, $nextDate ?: null, $status, Auth::id()]);
        if ($status === "Completed") {
            db()->query("UPDATE assets SET last_maintenance = ?, next_maintenance = ?, status = IF(status = \"Under Maintenance\", \"Active\", status) WHERE asset_id = ?",
                        [$mDate, $nextDate ?: null, $aId]);
        } elseif ($status === "In Progress") {
            db()->query("UPDATE assets SET status = \"Under Maintenance\" WHERE asset_id = ?", [$aId]);
        }
        Auth::logAction("Logged Maintenance", "assets", "Maintenance on asset ID $aId");
        setFlash("success", "Maintenance record saved.");
        header("Location: " . APP_URL . "/optional/assets/maintenance.php" . ($assetId ? "?asset_id=$assetId" : ""));
        exit;
    }
}

$assets = db()->fetchAll("SELECT * FROM assets ORDER BY asset_name ASC");

$sql = "SELECT mr.*, a.asset_code, a.asset_name, a.asset_type FROM maintenance_records mr JOIN assets a ON mr.asset_id = a.asset_id";
$params = [];
if ($assetId) { $sql .= " WHERE mr.asset_id = ?"; $params[] = $assetId; }
$sql .= " ORDER BY mr.maintenance_date DESC LIMIT 200";
$records = db()->fetchAll($sql, $params);

$pending = count(array_filter($records, fn($r) => $r["status"] === "Pending"));
$inProg  = count(array_filter($records, fn($r) => $r["status"] === "In Progress"));
$totalCost = array_sum(array_column($records, "cost"));

include __DIR__ . "/../../includes/header.php";
?>

<div class="page-header">
  <div class="page-title-wrap">
    <h1><i class="fa-solid fa-screwdriver-wrench" style="color:var(--gold);"></i> Asset Maintenance Log</h1>
    <p><?= $asset ? "Maintenance history for: <strong>" . htmlspecialchars($asset["asset_name"]) . "</strong>" : "All maintenance records for water infrastructure and fleet" ?></p>
  </div>
  <div class="page-actions">
    <a href="<?= APP_URL ?>/optional/assets/index.php" class="btn btn-secondary"><i class="fa-solid fa-arrow-left"></i> Asset Register</a>
    <button onclick="openModal(\"addMaintModal\")" class="btn btn-primary"><i class="fa-solid fa-plus-circle"></i> Log Maintenance</button>
  </div>
</div>

<div class="stats-grid mb-4" style="grid-template-columns:repeat(4,1fr);">
  <div class="stat-card">
    <div class="stat-icon" style="background:rgba(30,58,138,0.1);color:var(--royal-blue);"><i class="fa-solid fa-clipboard-list"></i></div>
    <div class="stat-info"><span class="stat-value"><?= count($records) ?></span><span class="stat-label">Total Records</span></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:rgba(239,68,68,0.1);color:var(--danger);"><i class="fa-solid fa-clock"></i></div>
    <div class="stat-info"><span class="stat-value"><?= $pending ?></span><span class="stat-label">Pending Actions</span></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:rgba(234,179,8,0.1);color:var(--gold);"><i class="fa-solid fa-wrench"></i></div>
    <div class="stat-info"><span class="stat-value"><?= $inProg ?></span><span class="stat-label">In Progress</span></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:rgba(21,128,61,0.1);color:var(--success);"><i class="fa-solid fa-sack-dollar"></i></div>
    <div class="stat-info"><span class="stat-value"><?= formatCurrency($totalCost) ?></span><span class="stat-label">Total Maintenance Cost</span></div>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <h3><i class="fa-solid fa-timeline" style="color:var(--royal-blue);"></i> Maintenance History</h3>
    <span class="badge badge-light"><?= count($records) ?> Records</span>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table">
        <thead>
          <tr>
            <th>Date</th>
            <th>Asset</th>
            <th>Type</th>
            <th>Type</th>
            <th>Description</th>
            <th>Performed By</th>
            <th style="text-align:right;">Cost (KES)</th>
            <th>Next Due</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($records)): ?>
            <tr><td colspan="9" class="text-center py-4 text-muted">No maintenance records found.</td></tr>
          <?php else: ?>
            <?php foreach ($records as $r):
              $sBadge = match($r["status"]) {
                "Completed" => "badge-success",
                "In Progress" => "badge-warning",
                "Pending" => "badge-danger",
                default => "badge-secondary"
              };
              $tBadge = match($r["maintenance_type"]) {
                "Preventive" => "badge-success",
                "Corrective" => "badge-warning",
                "Emergency" => "badge-danger",
                default => "badge-light"
              };
            ?>
              <tr>
                <td><small><?= formatDate($r["maintenance_date"]) ?></small></td>
                <td>
                  <strong style="color:var(--royal-blue);"><?= htmlspecialchars($r["asset_code"]) ?></strong><br>
                  <small><?= htmlspecialchars($r["asset_name"]) ?></small>
                </td>
                <td><span class="badge badge-light"><?= htmlspecialchars($r["asset_type"]) ?></span></td>
                <td><span class="badge <?= $tBadge ?>"><?= htmlspecialchars($r["maintenance_type"]) ?></span></td>
                <td><?= htmlspecialchars($r["description"]) ?></td>
                <td><?= htmlspecialchars($r["performed_by"] ?? "-") ?></td>
                <td style="text-align:right;font-weight:700;"><?= number_format($r["cost"], 2) ?></td>
                <td><small><?= $r["next_maintenance"] ? formatDate($r["next_maintenance"]) : "—" ?></small></td>
                <td><span class="badge <?= $sBadge ?>"><?= htmlspecialchars($r["status"]) ?></span></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Add Maintenance Modal -->
<div id="addMaintModal" class="modal-backdrop">
  <div class="modal" style="max-width:550px;">
    <div class="modal-header">
      <h3><i class="fa-solid fa-wrench" style="color:var(--gold);"></i> Log Maintenance Activity</h3>
      <button class="modal-close" onclick="closeModal(\"addMaintModal\")">&times;</button>
    </div>
    <form method="POST" action="">
      <input type="hidden" name="action" value="add_maint">
      <div class="modal-body">
        <div class="form-group mb-3">
          <label class="form-label">Asset <span class="text-danger">*</span></label>
          <select name="asset_id" class="form-control" required>
            <?php foreach ($assets as $a): ?>
              <option value="<?= $a["asset_id"] ?>" <?= $assetId === (int)$a["asset_id"] ? "selected" : "" ?>>
                <?= htmlspecialchars($a["asset_code"] . " — " . $a["asset_name"]) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem;">
          <div class="form-group mb-3">
            <label class="form-label">Maintenance Type</label>
            <select name="maintenance_type" class="form-control">
              <option value="Preventive">Preventive</option>
              <option value="Corrective">Corrective</option>
              <option value="Emergency">Emergency</option>
            </select>
          </div>
          <div class="form-group mb-3">
            <label class="form-label">Date</label>
            <input type="date" name="maintenance_date" class="form-control" value="<?= date("Y-m-d") ?>" required>
          </div>
          <div class="form-group mb-3">
            <label class="form-label">Cost (KES)</label>
            <input type="number" step="0.01" name="cost" class="form-control" value="0">
          </div>
        </div>
        <div class="form-group mb-3">
          <label class="form-label">Description / Work Done <span class="text-danger">*</span></label>
          <textarea name="description" class="form-control" rows="3" required placeholder="e.g. Replaced worn impeller bearing, greased shaft seals, checked motor windings..."></textarea>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem;">
          <div class="form-group mb-3">
            <label class="form-label">Performed By</label>
            <input type="text" name="performed_by" class="form-control" placeholder="Engineer / Contractor name">
          </div>
          <div class="form-group mb-3">
            <label class="form-label">Next Due Date</label>
            <input type="date" name="next_maintenance" class="form-control">
          </div>
          <div class="form-group mb-3">
            <label class="form-label">Status</label>
            <select name="status" class="form-control">
              <option value="Completed">Completed</option>
              <option value="In Progress">In Progress</option>
              <option value="Pending">Pending</option>
            </select>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal(\"addMaintModal\")">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> Save Record</button>
      </div>
    </form>
  </div>
</div>

<?php include __DIR__ . "/../../includes/footer.php"; ?>
