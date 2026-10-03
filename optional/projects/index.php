<?php
/**
 * THIWASCO MIS - Optional Module: Capital Works & Network Expansion Projects
 */
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';

Auth::check();

$pageTitle = 'Capital Works & Projects';
$activePage = 'projects_list';
$activeSection = 'projects';

$zones = db()->fetchAll("SELECT * FROM zones WHERE is_active = 1 ORDER BY zone_name ASC");

// Handle Add Project POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_project') {
    $code = strtoupper(clean($_POST['project_code'] ?? ''));
    $name = clean($_POST['project_name'] ?? '');
    $zoneId = (int)$_POST['zone_id'];
    $budget = (float)($_POST['budget'] ?? 0);
    $startDate = clean($_POST['start_date'] ?? date('Y-m-d'));
    $endDate = clean($_POST['expected_end'] ?? date('Y-m-d', strtotime('+6 months')));
    $contractor = clean($_POST['contractor'] ?? '');
    $completion = (float)($_POST['progress_percent'] ?? 0);
    $status = clean($_POST['status'] ?? 'Active');

    if (!empty($code) && !empty($name)) {
        db()->query("INSERT INTO projects (project_code, project_name, zone_id, budget, start_date, expected_end, contractor, progress_percent, status, created_by)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                     [$code, $name, $zoneId, $budget, $startDate, $endDate, $contractor, $completion, $status, Auth::id()]);
        Auth::logAction('Added Project', 'projects', "Added capital project {$code}: {$name}");
        setFlash('success', "Project {$code} created successfully.");
        header('Location: ' . APP_URL . '/optional/projects/index.php');
        exit;
    }
}

// Handle Edit Project POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_project') {
    $projId = (int)($_POST['project_id'] ?? 0);
    $code = strtoupper(clean($_POST['project_code'] ?? ''));
    $name = clean($_POST['project_name'] ?? '');
    $zoneId = (int)$_POST['zone_id'];
    $budget = (float)($_POST['budget'] ?? 0);
    $startDate = clean($_POST['start_date'] ?? date('Y-m-d'));
    $endDate = clean($_POST['expected_end'] ?? date('Y-m-d', strtotime('+6 months')));
    $contractor = clean($_POST['contractor'] ?? '');
    $completion = (float)($_POST['progress_percent'] ?? 0);
    $status = clean($_POST['status'] ?? 'Active');

    if ($projId > 0 && !empty($code) && !empty($name)) {
        db()->query("UPDATE projects SET project_code = ?, project_name = ?, zone_id = ?, budget = ?, start_date = ?, expected_end = ?, contractor = ?, progress_percent = ?, status = ? WHERE project_id = ?",
                     [$code, $name, $zoneId, $budget, $startDate, $endDate, $contractor, $completion, $status, $projId]);
        Auth::logAction('Updated Project', 'projects', "Updated project {$code}");
        setFlash('success', "Project {$code} updated successfully.");
        header('Location: ' . APP_URL . '/optional/projects/index.php');
        exit;
    }
}

// Handle Delete Project POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_project') {
    $projId = (int)($_POST['project_id'] ?? 0);
    if ($projId > 0) {
        $p = db()->fetch("SELECT project_code FROM projects WHERE project_id = ?", [$projId]);
        if ($p) {
            db()->query("DELETE FROM projects WHERE project_id = ?", [$projId]);
            Auth::logAction('Deleted Project', 'projects', "Deleted project {$p['project_code']}");
            setFlash('success', "Project {$p['project_code']} was deleted.");
        }
        header('Location: ' . APP_URL . '/optional/projects/index.php');
        exit;
    }
}

$projects = db()->fetchAll("SELECT p.*, z.zone_code, z.zone_name 
                            FROM projects p 
                            JOIN zones z ON p.zone_id = z.zone_id 
                            ORDER BY p.start_date DESC");

$totalBudget = array_sum(array_column($projects, 'budget'));
$activeProjects = count(array_filter($projects, fn($x) => $x['status'] === 'Active'));

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
  <div class="page-title-wrap">
    <h1><i class="fa-solid fa-helmet-safety" style="color:var(--gold);"></i> Capital Works & Pipeline Projects</h1>
    <p>Track network extensions, main line rehabilitation, reservoir constructions and contractor milestones</p>
  </div>
  <div class="page-actions">
    <button onclick="openModal('addProjModal')" class="btn btn-primary">
      <i class="fa-solid fa-plus-circle"></i> New Capital Project
    </button>
  </div>
</div>

<div class="stats-grid mb-4">
  <div class="stat-card">
    <div class="stat-icon" style="background:rgba(30,58,138,0.1);color:var(--royal-blue);"><i class="fa-solid fa-list-check"></i></div>
    <div class="stat-info">
      <span class="stat-value"><?= count($projects) ?></span>
      <span class="stat-label">Total Pipeline Projects</span>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:rgba(21,128,61,0.1);color:var(--success);"><i class="fa-solid fa-person-digging"></i></div>
    <div class="stat-info">
      <span class="stat-value"><?= $activeProjects ?></span>
      <span class="stat-label">Active Under Construction</span>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:rgba(234,179,8,0.1);color:var(--gold);"><i class="fa-solid fa-vault"></i></div>
    <div class="stat-info">
      <span class="stat-value"><?= formatCurrency($totalBudget) ?></span>
      <span class="stat-label">Total Capital Commitment</span>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <h3><i class="fa-solid fa-trowel-bricks" style="color:var(--royal-blue);"></i> Works Portfolio</h3>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table">
        <thead>
          <tr>
            <th>Project Code</th>
            <th>Project Description</th>
            <th>Zone</th>
            <th style="text-align:right;">Budget (KES)</th>
            <th>Contractor</th>
            <th>Timeline</th>
            <th>Progress %</th>
            <th>Status</th>
            <th class="text-right">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($projects)): ?>
            <tr><td colspan="9" class="text-center py-4 text-muted">No capital works logged yet.</td></tr>
          <?php else: ?>
            <?php foreach ($projects as $p): ?>
              <tr>
                <td><strong style="color:var(--royal-blue);"><?= htmlspecialchars($p['project_code']) ?></strong></td>
                <td><strong><?= htmlspecialchars($p['project_name']) ?></strong></td>
                <td><span class="badge badge-light"><?= htmlspecialchars($p['zone_code']) ?></span></td>
                <td style="text-align:right;font-weight:700;color:var(--navy);"><?= formatCurrency($p['budget']) ?></td>
                <td><?= htmlspecialchars($p['contractor'] ?? 'In-House Teams') ?></td>
                <td><small><?= formatDate($p['start_date']) ?> &rarr; <?= formatDate($p['expected_end']) ?></small></td>
                <td style="width:160px;">
                  <div style="display:flex;align-items:center;gap:6px;">
                    <div class="progress" style="height:8px;flex:1;background:var(--bg-light);border-radius:4px;overflow:hidden;">
                      <div style="width:<?= $p['progress_percent'] ?>%;background:var(--success);height:100%;"></div>
                    </div>
                    <small style="font-weight:700;"><?= $p['progress_percent'] ?>%</small>
                  </div>
                </td>
                <td><span class="badge badge-success"><?= htmlspecialchars($p['status']) ?></span></td>
                <td class="text-right">
                  <div style="display:inline-flex;gap:4px;">
                    <button type="button" class="btn btn-secondary btn-sm" onclick='openEditProjModal(<?= json_encode($p) ?>)' title="Edit Project">
                      <i class="fa-solid fa-pen-to-square"></i>
                    </button>
                    <form method="POST" action="" style="display:inline;" onsubmit="return confirm('Delete project <?= htmlspecialchars(addslashes($p['project_code'])) ?>?');">
                      <input type="hidden" name="action" value="delete_project">
                      <input type="hidden" name="project_id" value="<?= $p['project_id'] ?>">
                      <button type="submit" class="btn btn-danger btn-sm" title="Delete Project">
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

<!-- ADD PROJECT MODAL -->
<div id="addProjModal" class="modal-overlay">
  <div class="modal" style="max-width: 550px;">
    <div class="modal-header">
      <h3><i class="fa-solid fa-helmet-safety" style="color:var(--gold-300);"></i> New Capital Project</h3>
      <button class="modal-close" onclick="closeModal('addProjModal')">&times;</button>
    </div>
    <form method="POST" action="">
      <input type="hidden" name="action" value="add_project">
      <div class="modal-body">
        <div class="row" style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
          <div class="form-group mb-3">
            <label class="form-label">Project Code <span class="text-danger">*</span></label>
            <input type="text" name="project_code" class="form-control" placeholder="e.g. PRJ-ZN03-EXT" required>
          </div>
          <div class="form-group mb-3">
            <label class="form-label">Distribution Zone <span class="text-danger">*</span></label>
            <select name="zone_id" class="form-control" required>
              <?php foreach ($zones as $z): ?>
                <option value="<?= $z['zone_id'] ?>"><?= htmlspecialchars($z['zone_code'] . ' - ' . $z['zone_name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="form-group mb-3">
          <label class="form-label">Project Title <span class="text-danger">*</span></label>
          <input type="text" name="project_name" class="form-control" placeholder="e.g. Section 9 to Kiganjo 160mm HDPE Water Main Extension" required>
        </div>
        <div class="row" style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
          <div class="form-group mb-3">
            <label class="form-label">Approved Budget (KES) <span class="text-danger">*</span></label>
            <input type="number" step="0.01" name="budget" class="form-control" placeholder="e.g. 5000000" required>
          </div>
          <div class="form-group mb-3">
            <label class="form-label">Contractor Name</label>
            <input type="text" name="contractor" class="form-control" placeholder="e.g. In-House / Water Works Ltd">
          </div>
        </div>
        <div class="row" style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
          <div class="form-group mb-3">
            <label class="form-label">Start Date</label>
            <input type="date" name="start_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
          </div>
          <div class="form-group mb-3">
            <label class="form-label">Target Completion</label>
            <input type="date" name="expected_end" class="form-control" value="<?= date('Y-m-d', strtotime('+6 months')) ?>" required>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('addProjModal')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> Save Project</button>
      </div>
    </form>
  </div>
</div>

<!-- EDIT PROJECT MODAL -->
<div id="editProjModal" class="modal-overlay">
  <div class="modal" style="max-width: 550px;">
    <div class="modal-header">
      <h3><i class="fa-solid fa-pen-to-square" style="color:var(--gold-300);"></i> Edit Capital Project</h3>
      <button class="modal-close" onclick="closeModal('editProjModal')">&times;</button>
    </div>
    <form method="POST" action="">
      <input type="hidden" name="action" value="edit_project">
      <input type="hidden" name="project_id" id="edit_project_id">
      <div class="modal-body">
        <div class="row" style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
          <div class="form-group mb-3">
            <label class="form-label">Project Code <span class="text-danger">*</span></label>
            <input type="text" name="project_code" id="edit_project_code" class="form-control" required>
          </div>
          <div class="form-group mb-3">
            <label class="form-label">Distribution Zone <span class="text-danger">*</span></label>
            <select name="zone_id" id="edit_project_zone_id" class="form-control" required>
              <?php foreach ($zones as $z): ?>
                <option value="<?= $z['zone_id'] ?>"><?= htmlspecialchars($z['zone_code'] . ' - ' . $z['zone_name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="form-group mb-3">
          <label class="form-label">Project Title <span class="text-danger">*</span></label>
          <input type="text" name="project_name" id="edit_project_name" class="form-control" required>
        </div>
        <div class="row" style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
          <div class="form-group mb-3">
            <label class="form-label">Approved Budget (KES) <span class="text-danger">*</span></label>
            <input type="number" step="0.01" name="budget" id="edit_project_budget" class="form-control" required>
          </div>
          <div class="form-group mb-3">
            <label class="form-label">Contractor Name</label>
            <input type="text" name="contractor" id="edit_project_contractor" class="form-control">
          </div>
        </div>
        <div class="row" style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem;">
          <div class="form-group mb-3">
            <label class="form-label">Start Date</label>
            <input type="date" name="start_date" id="edit_project_start_date" class="form-control" required>
          </div>
          <div class="form-group mb-3">
            <label class="form-label">Expected End</label>
            <input type="date" name="expected_end" id="edit_project_expected_end" class="form-control" required>
          </div>
          <div class="form-group mb-3">
            <label class="form-label">Progress (%)</label>
            <input type="number" min="0" max="100" step="1" name="progress_percent" id="edit_project_progress" class="form-control">
          </div>
        </div>
        <div class="form-group mb-3">
          <label class="form-label">Project Status</label>
          <select name="status" id="edit_project_status" class="form-control">
            <option value="Active">Active</option>
            <option value="Completed">Completed</option>
            <option value="On Hold">On Hold</option>
            <option value="Cancelled">Cancelled</option>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('editProjModal')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check"></i> Save Changes</button>
      </div>
    </form>
  </div>
</div>

<script>
function openEditProjModal(p) {
  document.getElementById('edit_project_id').value = p.project_id;
  document.getElementById('edit_project_code').value = p.project_code;
  document.getElementById('edit_project_zone_id').value = p.zone_id;
  document.getElementById('edit_project_name').value = p.project_name;
  document.getElementById('edit_project_budget').value = p.budget;
  document.getElementById('edit_project_contractor').value = p.contractor || '';
  document.getElementById('edit_project_start_date').value = p.start_date;
  document.getElementById('edit_project_expected_end').value = p.expected_end;
  document.getElementById('edit_project_progress').value = p.progress_percent;
  document.getElementById('edit_project_status').value = p.status;
  openModal('editProjModal');
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
