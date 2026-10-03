<?php
/**
 * THIWASCO MIS - Water Production & Source Monitoring
 */
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';

Auth::requirePermission('nrw');

$pageTitle = 'Water Production Monitoring';
$activePage = 'nrw_production';
$activeSection = 'nrw';

$month = clean($_GET['month'] ?? date('Y-m'));

// Handle Add/Edit/Delete Production Record POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_production') {
        $sourceName = clean($_POST['source_name'] ?? '');
        $sourceType = clean($_POST['source_type'] ?? 'River');
        $recordDate = clean($_POST['record_date'] ?? date('Y-m-d'));
        $volume = (float)($_POST['volume_m3'] ?? 0);
        $hours = !empty($_POST['pumping_hours']) ? (float)$_POST['pumping_hours'] : null;
        $energy = !empty($_POST['energy_kwh']) ? (float)$_POST['energy_kwh'] : null;
        $notes = clean($_POST['notes'] ?? '');

        if (!empty($sourceName) && $volume > 0) {
            db()->query("INSERT INTO water_production (source_name, source_type, record_date, volume_m3, pumping_hours, energy_kwh, recorded_by, notes)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                         [$sourceName, $sourceType, $recordDate, $volume, $hours, $energy, Auth::id(), $notes]);
            Auth::logAction('Logged Production', 'nrw', "Recorded {$volume} m³ from {$sourceName}");
            setFlash('success', "Production entry of <strong>" . number_format($volume, 1) . " m³</strong> from <strong>{$sourceName}</strong> saved.");
            header('Location: ' . APP_URL . '/modules/nrw/production.php?month=' . substr($recordDate, 0, 7));
            exit;
        } else {
            setFlash('danger', 'Please provide a valid source name and production volume.');
        }
    }

    if ($_POST['action'] === 'edit_production') {
        $prodId = (int)($_POST['production_id'] ?? 0);
        $sourceName = clean($_POST['source_name'] ?? '');
        $sourceType = clean($_POST['source_type'] ?? 'River');
        $recordDate = clean($_POST['record_date'] ?? date('Y-m-d'));
        $volume = (float)($_POST['volume_m3'] ?? 0);
        $hours = !empty($_POST['pumping_hours']) ? (float)$_POST['pumping_hours'] : null;
        $energy = !empty($_POST['energy_kwh']) ? (float)$_POST['energy_kwh'] : null;
        $notes = clean($_POST['notes'] ?? '');

        if ($prodId > 0 && !empty($sourceName) && $volume > 0) {
            db()->query("UPDATE water_production SET source_name = ?, source_type = ?, record_date = ?, volume_m3 = ?, pumping_hours = ?, energy_kwh = ?, notes = ? WHERE production_id = ?",
                         [$sourceName, $sourceType, $recordDate, $volume, $hours, $energy, $notes, $prodId]);
            Auth::logAction('Updated Production', 'nrw', "Updated production record #{$prodId}");
            setFlash('success', "Production record for <strong>{$sourceName}</strong> updated successfully.");
            header('Location: ' . APP_URL . '/modules/nrw/production.php?month=' . substr($recordDate, 0, 7));
            exit;
        } else {
            setFlash('danger', 'Please provide a valid source name and production volume.');
        }
    }

    if ($_POST['action'] === 'delete_production') {
        $prodId = (int)($_POST['production_id'] ?? 0);
        if ($prodId > 0) {
            db()->query("DELETE FROM water_production WHERE production_id = ?", [$prodId]);
            Auth::logAction('Deleted Production', 'nrw', "Deleted production record #{$prodId}");
            setFlash('success', "Production entry deleted successfully.");
            header('Location: ' . APP_URL . '/modules/nrw/production.php?month=' . htmlspecialchars($month));
            exit;
        }
    }
}

// Fetch monthly records
$sql = "SELECT p.*, u.full_name as recorder_name 
        FROM water_production p 
        JOIN users u ON p.recorded_by = u.user_id 
        WHERE p.record_date LIKE ? 
        ORDER BY p.record_date DESC, p.production_id DESC";
$records = db()->fetchAll($sql, ["{$month}%"]);

// Aggregate stats
$stats = db()->fetch("SELECT 
                        COALESCE(SUM(volume_m3), 0) as total_volume,
                        COALESCE(AVG(volume_m3), 0) as avg_volume,
                        COALESCE(SUM(energy_kwh), 0) as total_energy,
                        COALESCE(SUM(pumping_hours), 0) as total_hours
                      FROM water_production 
                      WHERE record_date LIKE ?", ["{$month}%"]);

// Breakdown by source
$bySource = db()->fetchAll("SELECT source_name, source_type, SUM(volume_m3) as source_volume, COUNT(*) as logs
                            FROM water_production
                            WHERE record_date LIKE ?
                            GROUP BY source_name, source_type
                            ORDER BY source_volume DESC", ["{$month}%"]);

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
  <div class="page-title-wrap">
    <h1><i class="fa-solid fa-faucet" style="color:var(--gold);"></i> Bulk Water Production</h1>
    <p>Track abstraction, water treatment works discharge, borehole pumping hours and energy consumption</p>
  </div>
  <div class="page-actions">
    <button onclick="openModal('addProdModal')" class="btn btn-primary">
      <i class="fa-solid fa-plus-circle"></i> Log Daily Production
    </button>
    <a href="<?= APP_URL ?>/modules/nrw/water_balance.php" class="btn btn-secondary">
      <i class="fa-solid fa-scale-balanced"></i> Water Balance
    </a>
  </div>
</div>

<!-- STATS -->
<div class="stats-grid mb-4">
  <div class="stat-card">
    <div class="stat-icon" style="background:rgba(30,58,138,0.1);color:var(--royal-blue);"><i class="fa-solid fa-water"></i></div>
    <div class="stat-info">
      <span class="stat-value"><?= number_format($stats['total_volume'], 1) ?> m³</span>
      <span class="stat-label">Total Produced (<?= date('F Y', strtotime($month . '-01')) ?>)</span>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:rgba(21,128,61,0.1);color:var(--success);"><i class="fa-solid fa-gauge"></i></div>
    <div class="stat-info">
      <span class="stat-value"><?= number_format($stats['avg_volume'], 1) ?> m³</span>
      <span class="stat-label">Daily Average Production</span>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:rgba(234,179,8,0.1);color:var(--gold);"><i class="fa-solid fa-bolt"></i></div>
    <div class="stat-info">
      <span class="stat-value"><?= number_format($stats['total_energy'], 1) ?> kWh</span>
      <span class="stat-label">Pumping Energy Consumed</span>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:rgba(14,165,233,0.1);color:var(--info);"><i class="fa-solid fa-clock"></i></div>
    <div class="stat-info">
      <span class="stat-value"><?= number_format($stats['total_hours'], 1) ?> hrs</span>
      <span class="stat-label">Pump Operating Hours</span>
    </div>
  </div>
</div>

<!-- MONTH FILTER & SOURCE SUMMARY -->
<div class="row mb-4" style="display:grid;grid-template-columns: 1fr 1.5fr; gap:1.5rem;">
  <div class="card">
    <div class="card-header">
      <h3><i class="fa-solid fa-calendar" style="color:var(--royal-blue);"></i> Select Period</h3>
    </div>
    <div class="card-body">
      <form method="GET" action="">
        <div class="form-group mb-3">
          <label class="form-label">Production Month</label>
          <input type="month" name="month" class="form-control" value="<?= htmlspecialchars($month) ?>" onchange="this.form.submit()">
        </div>
      </form>
      <div style="font-size:0.85rem;color:var(--text-muted);line-height:1.6;">
        Water produced in this period forms the <strong>System Input Volume (SIV)</strong> for the monthly IWA Non-Revenue Water calculation.
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-header">
      <h3><i class="fa-solid fa-chart-pie" style="color:var(--gold);"></i> Production by Intake Source</h3>
    </div>
    <div class="card-body p-0">
      <table class="table" style="font-size:0.85rem;">
        <thead>
          <tr>
            <th>Source</th>
            <th>Type</th>
            <th style="text-align:right;">Volume (m³)</th>
            <th style="text-align:right;">Share %</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($bySource)): ?>
            <tr><td colspan="4" class="text-center py-3 text-muted">No source records for this month.</td></tr>
          <?php else: ?>
            <?php foreach ($bySource as $bs): 
              $pct = $stats['total_volume'] > 0 ? round(($bs['source_volume'] / $stats['total_volume']) * 100, 1) : 0;
            ?>
              <tr>
                <td><strong><?= htmlspecialchars($bs['source_name']) ?></strong></td>
                <td><span class="badge badge-light"><?= htmlspecialchars($bs['source_type']) ?></span></td>
                <td style="text-align:right;font-weight:700;color:var(--navy);"><?= number_format($bs['source_volume'], 1) ?></td>
                <td style="text-align:right;"><strong><?= $pct ?>%</strong></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- DAILY RECORDS TABLE -->
<div class="card">
  <div class="card-header">
    <h3><i class="fa-solid fa-list" style="color:var(--royal-blue);"></i> Daily Production Log</h3>
    <span class="badge badge-light"><?= count($records) ?> Logs</span>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table">
        <thead>
          <tr>
            <th>Date</th>
            <th>Source Name</th>
            <th>Source Type</th>
            <th style="text-align:right;">Discharge Volume (m³)</th>
            <th style="text-align:right;">Pumping Hours</th>
            <th style="text-align:right;">Energy (kWh)</th>
            <th>Recorded By</th>
            <th>Notes</th>
            <th class="text-right">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($records)): ?>
            <tr><td colspan="9" class="text-center py-4 text-muted">No production logged for <?= htmlspecialchars($month) ?>.</td></tr>
          <?php else: ?>
            <?php foreach ($records as $r): ?>
              <tr>
                <td><strong><?= formatDate($r['record_date']) ?></strong></td>
                <td><strong style="color:var(--navy);"><?= htmlspecialchars($r['source_name']) ?></strong></td>
                <td><span class="badge badge-info"><?= htmlspecialchars($r['source_type']) ?></span></td>
                <td style="text-align:right;font-weight:800;color:var(--royal-blue);font-size:1.05rem;">
                  <?= number_format($r['volume_m3'], 1) ?>
                </td>
                <td style="text-align:right;"><?= $r['pumping_hours'] ? number_format($r['pumping_hours'], 1) . ' hrs' : '-' ?></td>
                <td style="text-align:right;"><?= $r['energy_kwh'] ? number_format($r['energy_kwh'], 1) . ' kWh' : '-' ?></td>
                <td><small><?= htmlspecialchars($r['recorder_name']) ?></small></td>
                <td><small class="text-muted"><?= htmlspecialchars($r['notes'] ?? '-') ?></small></td>
                <td class="text-right">
                  <div style="display:inline-flex;gap:4px;">
                    <button type="button" class="btn btn-secondary btn-sm" onclick='editProd(<?= json_encode($r) ?>)' title="Edit Log">
                      <i class="fa-solid fa-pen-to-square"></i>
                    </button>
                    <form method="POST" action="" style="display:inline;" onsubmit="return confirm('Delete this production log entry?');">
                      <input type="hidden" name="action" value="delete_production">
                      <input type="hidden" name="production_id" value="<?= $r['production_id'] ?>">
                      <button type="submit" class="btn btn-danger btn-sm" title="Delete Log">
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

<!-- ADD PRODUCTION MODAL -->
<div id="addProdModal" class="modal-overlay">
  <div class="modal" style="max-width: 500px;">
    <div class="modal-header">
      <h3><i class="fa-solid fa-faucet" style="color:var(--gold-300);"></i> Log Water Production</h3>
      <button class="modal-close" onclick="closeModal('addProdModal')">&times;</button>
    </div>
    <form method="POST" action="">
      <input type="hidden" name="action" value="add_production">
      <div class="modal-body">
        <div class="form-group mb-3">
          <label class="form-label">Water Source Name <span class="text-danger">*</span></label>
          <input type="text" name="source_name" class="form-control" placeholder="e.g. Chania River Intake / Treatment Plant 1" required>
        </div>
        <div class="row" style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
          <div class="form-group mb-3">
            <label class="form-label">Source Type</label>
            <select name="source_type" class="form-control">
              <option value="River">River Intake</option>
              <option value="Dam">Dam Reservoir</option>
              <option value="Borehole">Deep Borehole</option>
              <option value="Spring">Natural Spring</option>
              <option value="Purchased">Bulk Purchased</option>
            </select>
          </div>
          <div class="form-group mb-3">
            <label class="form-label">Record Date <span class="text-danger">*</span></label>
            <input type="date" name="record_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
          </div>
        </div>
        <div class="form-group mb-3">
          <label class="form-label">Produced Volume (m³) <span class="text-danger">*</span></label>
          <input type="number" step="0.1" name="volume_m3" class="form-control" style="font-size:1.2rem;font-weight:700;color:var(--navy);" placeholder="e.g. 1500.0" required>
        </div>
        <div class="row" style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
          <div class="form-group mb-3">
            <label class="form-label">Pumping Hours</label>
            <input type="number" step="0.1" name="pumping_hours" class="form-control" placeholder="e.g. 18.5">
          </div>
          <div class="form-group mb-3">
            <label class="form-label">Energy Consumed (kWh)</label>
            <input type="number" step="0.1" name="energy_kwh" class="form-control" placeholder="e.g. 450.0">
          </div>
        </div>
        <div class="form-group mb-3">
          <label class="form-label">Operational Notes</label>
          <textarea name="notes" class="form-control" rows="2" placeholder="Turbidity, pump maintenance, power outages..."></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('addProdModal')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> Save Production</button>
      </div>
    </form>
  </div>
</div>

<!-- EDIT PRODUCTION MODAL -->
<div id="editProdModal" class="modal-overlay">
  <div class="modal" style="max-width: 500px;">
    <div class="modal-header">
      <h3><i class="fa-solid fa-pen-to-square" style="color:var(--gold-300);"></i> Edit Production Record</h3>
      <button class="modal-close" onclick="closeModal('editProdModal')">&times;</button>
    </div>
    <form method="POST" action="">
      <input type="hidden" name="action" value="edit_production">
      <input type="hidden" name="production_id" id="edit_prod_id">
      <div class="modal-body">
        <div class="form-group mb-3">
          <label class="form-label">Water Source Name <span class="text-danger">*</span></label>
          <input type="text" name="source_name" id="edit_source_name" class="form-control" required>
        </div>
        <div class="row" style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
          <div class="form-group mb-3">
            <label class="form-label">Source Type</label>
            <select name="source_type" id="edit_source_type" class="form-control">
              <option value="River">River Intake</option>
              <option value="Dam">Dam Reservoir</option>
              <option value="Borehole">Deep Borehole</option>
              <option value="Spring">Natural Spring</option>
              <option value="Purchased">Bulk Purchased</option>
            </select>
          </div>
          <div class="form-group mb-3">
            <label class="form-label">Record Date <span class="text-danger">*</span></label>
            <input type="date" name="record_date" id="edit_record_date" class="form-control" required>
          </div>
        </div>
        <div class="form-group mb-3">
          <label class="form-label">Produced Volume (m³) <span class="text-danger">*</span></label>
          <input type="number" step="0.1" name="volume_m3" id="edit_volume_m3" class="form-control" style="font-size:1.2rem;font-weight:700;color:var(--navy);" required>
        </div>
        <div class="row" style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
          <div class="form-group mb-3">
            <label class="form-label">Pumping Hours</label>
            <input type="number" step="0.1" name="pumping_hours" id="edit_pumping_hours" class="form-control">
          </div>
          <div class="form-group mb-3">
            <label class="form-label">Energy Consumed (kWh)</label>
            <input type="number" step="0.1" name="energy_kwh" id="edit_energy_kwh" class="form-control">
          </div>
        </div>
        <div class="form-group mb-3">
          <label class="form-label">Operational Notes</label>
          <textarea name="notes" id="edit_notes" class="form-control" rows="2"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('editProdModal')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check"></i> Update Record</button>
      </div>
    </form>
  </div>
</div>

<script>
function editProd(r) {
  document.getElementById('edit_prod_id').value = r.production_id;
  document.getElementById('edit_source_name').value = r.source_name;
  document.getElementById('edit_source_type').value = r.source_type;
  document.getElementById('edit_record_date').value = r.record_date;
  document.getElementById('edit_volume_m3').value = r.volume_m3;
  document.getElementById('edit_pumping_hours').value = r.pumping_hours || '';
  document.getElementById('edit_energy_kwh').value = r.energy_kwh || '';
  document.getElementById('edit_notes').value = r.notes || '';
  openModal('editProdModal');
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

