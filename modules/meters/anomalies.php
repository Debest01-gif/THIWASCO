<?php
/**
 * THIWASCO MIS - Meter Reading Anomalies Review & Action
 */
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';

Auth::requirePermission('meters');

$pageTitle = 'Reading Anomalies';
$activePage = 'meter_anomalies';
$activeSection = 'meters';

// Handle Clear Anomaly POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'clear_anomaly') {
    $readingId = (int)$_POST['reading_id'];
    db()->query("UPDATE meter_readings SET anomaly_flag = 0, anomaly_reason = CONCAT(COALESCE(anomaly_reason,''), ' [Verified & Cleared by User]') WHERE reading_id = ?", [$readingId]);
    Auth::logAction('Cleared Anomaly', 'meters', "Cleared anomaly on reading ID {$readingId}");
    setFlash('success', 'Anomaly verified and cleared.');
    header('Location: ' . APP_URL . '/modules/meters/anomalies.php');
    exit;
}

// Handle Correct Reading POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'correct_reading') {
    $readingId = (int)$_POST['reading_id'];
    $newReading = (float)$_POST['current_reading'];
    $meterId = (int)$_POST['meter_id'];

    $r = db()->fetch("SELECT * FROM meter_readings WHERE reading_id = ?", [$readingId]);
    if ($r) {
        $prev = (float)$r['previous_reading'];
        $diff = $newReading - $prev;
        $flag = ($diff < 0) ? 1 : 0;
        $reason = ($diff < 0) ? 'Negative consumption' : null;

        db()->query("UPDATE meter_readings SET current_reading = ?, anomaly_flag = ?, anomaly_reason = ?, notes = CONCAT(COALESCE(notes,''), ' [Corrected]') WHERE reading_id = ?", [$newReading, $flag, $reason, $readingId]);
        db()->query("UPDATE meters SET current_reading = ? WHERE meter_id = ?", [$newReading, $meterId]);

        Auth::logAction('Corrected Reading', 'meters', "Reading ID {$readingId} corrected to {$newReading}");
        setFlash('success', 'Meter reading corrected successfully.');
        header('Location: ' . APP_URL . '/modules/meters/anomalies.php');
        exit;
    }
}

// Fetch anomalous readings
$sql = "SELECT r.*, m.meter_serial, m.meter_size, c.customer_id, c.account_no, c.full_name, c.phone, z.zone_code, z.zone_name, u.full_name as reader_name
        FROM meter_readings r
        JOIN meters m ON r.meter_id = m.meter_id
        JOIN customers c ON r.customer_id = c.customer_id
        JOIN zones z ON c.zone_id = z.zone_id
        JOIN users u ON r.read_by = u.user_id
        WHERE r.anomaly_flag = 1
        ORDER BY r.reading_date DESC, r.reading_id DESC";
$anomalies = db()->fetchAll($sql);

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
  <div class="page-title-wrap">
    <h1><i class="fa-solid fa-circle-exclamation" style="color:var(--danger);"></i> Meter Reading Anomalies</h1>
    <p>Algorithmic anomaly detection queue: consumption spikes, negative meter indices, and stuck meters</p>
  </div>
  <div class="page-actions">
    <a href="<?= APP_URL ?>/modules/meters/readings.php" class="btn btn-secondary">
      <i class="fa-solid fa-pencil"></i> Enter Readings
    </a>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <h3><i class="fa-solid fa-triangle-exclamation" style="color:var(--danger);"></i> Flagged Consumption Anomalies</h3>
    <span class="badge badge-danger"><?= count($anomalies) ?> Anomalies Requiring Action</span>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table">
        <thead>
          <tr>
            <th>Customer & Account</th>
            <th>Meter Serial</th>
            <th>Zone</th>
            <th>Month</th>
            <th>Prev Reading</th>
            <th>Entered Reading</th>
            <th>Variance / m³</th>
            <th>Detected Issue</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($anomalies)): ?>
            <tr>
              <td colspan="9" class="text-center py-4 text-muted">
                <i class="fa-solid fa-circle-check" style="font-size:2rem;color:var(--success);margin-bottom:0.5rem;display:block;"></i>
                All meter readings are verified! No anomalies in the queue.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($anomalies as $a): 
              $variance = (float)$a['current_reading'] - (float)$a['previous_reading'];
            ?>
              <tr>
                <td>
                  <a href="<?= APP_URL ?>/modules/customers/view.php?id=<?= $a['customer_id'] ?>" style="font-weight:700;color:var(--navy);">
                    <?= htmlspecialchars($a['full_name']) ?>
                  </a>
                  <div style="font-size:0.8rem;color:var(--text-muted);"><i class="fa-solid fa-id-card"></i> <?= htmlspecialchars($a['account_no']) ?></div>
                </td>
                <td><code style="font-weight:700;color:var(--navy);"><?= htmlspecialchars($a['meter_serial']) ?></code></td>
                <td><span class="badge badge-light"><?= htmlspecialchars($a['zone_code']) ?></span></td>
                <td><strong><?= htmlspecialchars($a['billing_month']) ?></strong></td>
                <td><?= number_format($a['previous_reading'], 1) ?></td>
                <td><strong style="color:var(--royal-blue);"><?= number_format($a['current_reading'], 1) ?></strong></td>
                <td style="font-weight:700;color:<?= $variance < 0 ? 'var(--danger)' : 'var(--warning)' ?>;">
                  <?= number_format($variance, 1) ?> m³
                </td>
                <td>
                  <span class="badge badge-danger" style="white-space:normal;display:inline-block;max-width:280px;text-align:left;">
                    <?= htmlspecialchars($a['anomaly_reason'] ?? 'Unspecified anomaly') ?>
                  </span>
                </td>
                <td>
                  <div style="display:flex;gap:0.4rem;">
                    <button type="button" class="btn btn-secondary btn-sm" onclick="openCorrectModal(<?= $a['reading_id'] ?>, <?= $a['meter_id'] ?>, <?= $a['previous_reading'] ?>, <?= $a['current_reading'] ?>, '<?= htmlspecialchars($a['meter_serial']) ?>')" title="Correct Reading">
                      <i class="fa-solid fa-pen"></i>
                    </button>
                    <form method="POST" action="" onsubmit="return confirm('Confirm this reading is genuine and clear anomaly?');" style="display:inline;">
                      <input type="hidden" name="action" value="clear_anomaly">
                      <input type="hidden" name="reading_id" value="<?= $a['reading_id'] ?>">
                      <button type="submit" class="btn btn-success btn-sm" title="Clear Anomaly">
                        <i class="fa-solid fa-check"></i>
                      </button>
                    </form>
                    <a href="<?= APP_URL ?>/modules/revenue_protection/inspections.php?customer_id=<?= $a['customer_id'] ?>&meter_id=<?= $a['meter_id'] ?>" class="btn btn-warning btn-sm" title="Dispatch Field Inspection">
                      <i class="fa-solid fa-shield-halved"></i>
                    </a>
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

<!-- CORRECT READING MODAL -->
<div id="correctModal" class="modal-backdrop">
  <div class="modal" style="max-width: 450px;">
    <div class="modal-header">
      <h3><i class="fa-solid fa-pen" style="color:var(--gold);"></i> Correct Meter Reading</h3>
      <button class="modal-close" onclick="closeModal('correctModal')">&times;</button>
    </div>
    <form method="POST" action="">
      <input type="hidden" name="action" value="correct_reading">
      <input type="hidden" name="reading_id" id="corReadingId">
      <input type="hidden" name="meter_id" id="corMeterId">
      <div class="modal-body">
        <p>Correcting reading for meter: <strong id="corSerial"></strong></p>
        <div style="background:var(--bg-light);padding:0.75rem;border-radius:6px;margin-bottom:1rem;">
          <small class="text-muted">Previous Meter Index:</small>
          <div style="font-size:1.2rem;font-weight:700;" id="corPrev"></div>
        </div>
        <div class="form-group mb-3">
          <label class="form-label">Corrected Current Reading (m³) <span class="text-danger">*</span></label>
          <input type="number" step="0.1" name="current_reading" id="corCurr" class="form-control" style="font-size:1.2rem;font-weight:700;color:var(--royal-blue);" required>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('correctModal')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> Save Correction</button>
      </div>
    </form>
  </div>
</div>

<script>
function openCorrectModal(readingId, meterId, prev, curr, serial) {
  document.getElementById('corReadingId').value = readingId;
  document.getElementById('corMeterId').value = meterId;
  document.getElementById('corPrev').textContent = prev + ' m³';
  document.getElementById('corCurr').value = curr;
  document.getElementById('corSerial').textContent = serial;
  openModal('correctModal');
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
