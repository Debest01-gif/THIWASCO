<?php
/**
 * THIWASCO MIS - Meter Readings Entry & Automated Anomaly Detection Engine
 */
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';

Auth::requirePermission('meters');

$pageTitle = 'Enter Meter Readings';
$activePage = 'meter_readings';
$activeSection = 'meters';

$billingMonth = clean($_GET['billing_month'] ?? date('Y-m'));
$zoneId = !empty($_GET['zone_id']) ? (int)$_GET['zone_id'] : '';
$preselectedMeterId = (int)($_GET['meter_id'] ?? 0);

$zones = db()->fetchAll("SELECT * FROM zones WHERE is_active = 1 ORDER BY zone_name ASC");

// Handle Batch Save POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_batch_readings') {
    $readingsData = $_POST['readings'] ?? [];
    $postMonth = clean($_POST['billing_month'] ?? $billingMonth);
    $readingDate = clean($_POST['reading_date'] ?? date('Y-m-d'));

    $savedCount = 0;
    $anomaliesCount = 0;

    try {
        $pdo = Database::getInstance()->getConnection();
        $pdo->beginTransaction();

        $stmtCheck = $pdo->prepare("SELECT reading_id FROM meter_readings WHERE meter_id = ? AND billing_month = ?");
        $stmtInsert = $pdo->prepare("INSERT INTO meter_readings 
            (meter_id, customer_id, read_by, billing_month, reading_date, previous_reading, current_reading, reading_type, anomaly_flag, anomaly_reason, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'Actual', ?, ?, ?)");
        $stmtUpdate = $pdo->prepare("UPDATE meter_readings 
            SET current_reading = ?, reading_date = ?, anomaly_flag = ?, anomaly_reason = ?, notes = ?
            WHERE reading_id = ?");
        $stmtMeter = $pdo->prepare("UPDATE meters SET current_reading = ?, last_read_date = ? WHERE meter_id = ?");

        foreach ($readingsData as $meterId => $row) {
            $currStr = trim($row['current_reading'] ?? '');
            if ($currStr === '') continue; // Skip un-entered rows

            $currReading = (float)$currStr;
            $prevReading = (float)($row['previous_reading'] ?? 0);
            $custId = (int)$row['customer_id'];
            $avgCons = (float)($row['avg_consumption'] ?? 15);
            $notes = clean($row['notes'] ?? '');

            // Anomaly detection rules
            $anomalyFlag = 0;
            $anomalyReason = null;
            $consumption = $currReading - $prevReading;

            if ($consumption < 0) {
                $anomalyFlag = 1;
                $anomalyReason = "Negative consumption ({$consumption} m³). Meter may have run backwards or been replaced.";
                $anomaliesCount++;
            } elseif ($avgCons > 0 && $consumption > ($avgCons * 3) && $consumption > 30) {
                $anomalyFlag = 1;
                $anomalyReason = "Abnormally high consumption ({$consumption} m³ vs {$avgCons} m³ avg). Potential leak or misread.";
                $anomaliesCount++;
            } elseif ($consumption == 0 && $prevReading > 0) {
                $anomalyFlag = 1;
                $anomalyReason = "Zero consumption recorded. Verify if meter is stuck or premises vacant.";
                $anomaliesCount++;
            }

            // Check if exists
            $stmtCheck->execute([$meterId, $postMonth]);
            $existing = $stmtCheck->fetch();

            if ($existing) {
                $stmtUpdate->execute([$currReading, $readingDate, $anomalyFlag, $anomalyReason, $notes, $existing['reading_id']]);
            } else {
                $stmtInsert->execute([$meterId, $custId, Auth::id(), $postMonth, $readingDate, $prevReading, $currReading, $anomalyFlag, $anomalyReason, $notes]);
            }

            $stmtMeter->execute([$currReading, $readingDate, $meterId]);
            $savedCount++;
        }

        $pdo->commit();

        Auth::logAction('Saved Meter Readings', 'meters', "Captured {$savedCount} meter readings for {$postMonth}. Anomalies: {$anomaliesCount}");
        setFlash('success', "Successfully recorded <strong>{$savedCount}</strong> meter readings for <strong>{$postMonth}</strong>! (" . ($anomaliesCount > 0 ? "<strong>{$anomaliesCount} flagged for review</strong>" : "No anomalies") . ")");

        header("Location: " . APP_URL . "/modules/meters/readings.php?billing_month={$postMonth}&zone_id={$zoneId}");
        exit;

    } catch (Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
        setFlash('danger', 'Failed to save readings: ' . $e->getMessage());
    }
}

// Fetch meters in selected zone for reading sheet
$where = ["m.status = 'Active'", "c.status IN ('Active', 'Defaulter')"];
$params = [];

if ($zoneId) {
    $where[] = "c.zone_id = ?";
    $params[] = $zoneId;
}
if ($preselectedMeterId) {
    $where[] = "m.meter_id = ?";
    $params[] = $preselectedMeterId;
}

$whereSql = implode(" AND ", $where);

$sql = "SELECT m.meter_id, m.meter_serial, m.current_reading, m.last_read_date, m.meter_size,
               c.customer_id, c.account_no, c.full_name, c.phone, z.zone_code, z.zone_name,
               r.reading_id, r.current_reading as entered_reading, r.reading_date as entered_date,
               r.anomaly_flag, r.anomaly_reason,
               (SELECT AVG(current_reading - previous_reading) FROM meter_readings WHERE meter_id = m.meter_id AND billing_month < '{$billingMonth}' LIMIT 3) as avg_consumption
        FROM meters m
        JOIN customers c ON m.customer_id = c.customer_id
        JOIN zones z ON c.zone_id = z.zone_id
        LEFT JOIN meter_readings r ON m.meter_id = r.meter_id AND r.billing_month = ?
        WHERE {$whereSql}
        ORDER BY z.zone_code ASC, c.account_no ASC LIMIT 150";

array_unshift($params, $billingMonth);
$meterList = db()->fetchAll($sql, $params);

// Counts
$totalSheetMeters = count($meterList);
$readMeters = count(array_filter($meterList, fn($x) => $x['reading_id'] !== null));

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
  <div class="page-title-wrap">
    <h1><i class="fa-solid fa-pencil" style="color:var(--gold);"></i> Enter Meter Readings</h1>
    <p>Bulk capture monthly customer meter indices with real-time consumption calculations and anomaly detection</p>
  </div>
  <div class="page-actions">
    <a href="<?= APP_URL ?>/modules/meters/anomalies.php" class="btn btn-warning">
      <i class="fa-solid fa-triangle-exclamation"></i> View Anomalies
    </a>
    <a href="<?= APP_URL ?>/modules/billing/generate.php" class="btn btn-primary">
      <i class="fa-solid fa-bolt"></i> Generate Bills
    </a>
  </div>
</div>

<!-- FILTERS / SHEET SELECTOR -->
<div class="card mb-4">
  <div class="card-body" style="padding:1rem 1.25rem;">
    <form method="GET" action="" style="display:flex;flex-wrap:wrap;gap:1.25rem;align-items:flex-end;">
      <div style="min-width:160px;">
        <label class="form-label" style="font-size:0.8rem;">Billing Month <span class="text-danger">*</span></label>
        <input type="month" name="billing_month" class="form-control" value="<?= htmlspecialchars($billingMonth) ?>" required>
      </div>

      <div style="min-width:200px;">
        <label class="form-label" style="font-size:0.8rem;">Distribution Zone / Route</label>
        <select name="zone_id" class="form-control" onchange="this.form.submit()">
          <option value="">-- All Zones (Company Wide) --</option>
          <?php foreach ($zones as $z): ?>
            <option value="<?= $z['zone_id'] ?>" <?= $zoneId == $z['zone_id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($z['zone_code'] . ' - ' . $z['zone_name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div style="display:flex;gap:0.5rem;">
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-rotate"></i> Load Sheet</button>
      </div>

      <div style="margin-left:auto;text-align:right;">
        <span style="font-size:0.85rem;color:var(--text-muted);display:block;">Cycle Progress:</span>
        <strong style="color:var(--navy);font-size:1.1rem;"><?= $readMeters ?> / <?= $totalSheetMeters ?> Read (<?= $totalSheetMeters > 0 ? round(($readMeters / $totalSheetMeters) * 100) : 0 ?>%)</strong>
      </div>
    </form>
  </div>
</div>

<!-- BATCH READING FORM -->
<form method="POST" action="" id="batchReadingsForm">
  <input type="hidden" name="action" value="save_batch_readings">
  <input type="hidden" name="billing_month" value="<?= htmlspecialchars($billingMonth) ?>">

  <div class="card">
    <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;">
      <h3><i class="fa-solid fa-list-check" style="color:var(--royal-blue);"></i> Reading Sheet: <?= htmlspecialchars($billingMonth) ?></h3>
      <div style="display:flex;align-items:center;gap:1rem;">
        <div>
          <label style="font-size:0.85rem;margin-right:4px;">Date Read:</label>
          <input type="date" name="reading_date" value="<?= date('Y-m-d') ?>" class="form-control form-control-sm" style="display:inline-block;width:auto;" required>
        </div>
        <button type="submit" class="btn btn-success"><i class="fa-solid fa-save"></i> Save All Readings</button>
      </div>
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table">
          <thead>
            <tr>
              <th>Account & Customer</th>
              <th>Meter Serial</th>
              <th>Zone</th>
              <th>Previous Reading (m³)</th>
              <th style="width:160px;">Current Reading (m³) <span class="text-danger">*</span></th>
              <th>Consumption</th>
              <th>Avg History</th>
              <th>Anomaly Status</th>
              <th>Notes</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($meterList)): ?>
              <tr>
                <td colspan="9" class="text-center py-4 text-muted">
                  No active meters found for the selected zone.
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($meterList as $idx => $m): 
                $prevReading = (float)$m['current_reading'];
                $val = $m['entered_reading'] !== null ? (float)$m['entered_reading'] : '';
                $avg = $m['avg_consumption'] ? round((float)$m['avg_consumption'], 1) : 12.0;
                $rowCons = ($val !== '') ? max(0, $val - $prevReading) : '-';
              ?>
                <tr>
                  <td>
                    <strong><?= htmlspecialchars($m['full_name']) ?></strong>
                    <div style="font-size:0.8rem;color:var(--text-muted);"><i class="fa-solid fa-id-card"></i> <?= htmlspecialchars($m['account_no']) ?></div>
                    <input type="hidden" name="readings[<?= $m['meter_id'] ?>][customer_id]" value="<?= $m['customer_id'] ?>">
                    <input type="hidden" name="readings[<?= $m['meter_id'] ?>][previous_reading]" value="<?= $prevReading ?>">
                    <input type="hidden" name="readings[<?= $m['meter_id'] ?>][avg_consumption]" value="<?= $avg ?>">
                  </td>
                  <td>
                    <code style="font-weight:700;color:var(--navy);"><?= htmlspecialchars($m['meter_serial']) ?></code>
                    <div style="font-size:0.75rem;color:var(--text-muted);"><?= htmlspecialchars($m['meter_size']) ?></div>
                  </td>
                  <td><span class="badge badge-light"><?= htmlspecialchars($m['zone_code']) ?></span></td>
                  <td><strong style="color:var(--text-muted);font-size:1.05rem;"><?= number_format($prevReading, 1) ?></strong></td>
                  <td>
                    <input type="number" step="0.1" name="readings[<?= $m['meter_id'] ?>][current_reading]" 
                           id="reading_<?= $m['meter_id'] ?>"
                           class="form-control form-control-sm" 
                           value="<?= $val !== '' ? $val : '' ?>" 
                           style="font-size:1.05rem;font-weight:700;color:var(--royal-blue);"
                           oninput="calculateRowConsumption(<?= $m['meter_id'] ?>, <?= $prevReading ?>, <?= $avg ?>)">
                  </td>
                  <td>
                    <span id="cons_<?= $m['meter_id'] ?>" style="font-size:1.05rem;font-weight:700;color:var(--navy);">
                      <?= is_numeric($rowCons) ? number_format($rowCons, 1) . ' m³' : '-' ?>
                    </span>
                  </td>
                  <td><small class="text-muted"><?= $avg ?> m³/mo</small></td>
                  <td>
                    <div id="status_<?= $m['meter_id'] ?>">
                      <?php if ($m['anomaly_flag']): ?>
                        <span class="badge badge-danger" title="<?= htmlspecialchars($m['anomaly_reason']) ?>"><i class="fa-solid fa-triangle-exclamation"></i> Flagged</span>
                      <?php elseif ($m['entered_reading'] !== null): ?>
                        <span class="badge badge-success"><i class="fa-solid fa-check"></i> Recorded</span>
                      <?php else: ?>
                        <span class="badge badge-light">Pending</span>
                      <?php endif; ?>
                    </div>
                  </td>
                  <td>
                    <input type="text" name="readings[<?= $m['meter_id'] ?>][notes]" class="form-control form-control-sm" placeholder="e.g. Dog in yard" style="font-size:0.8rem;">
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
      <div style="padding:1.25rem;text-align:right;border-top:1px solid var(--border-color);">
        <button type="submit" class="btn btn-success" style="padding:0.75rem 2rem;font-size:1rem;">
          <i class="fa-solid fa-save"></i> Save All Readings & Detect Anomalies
        </button>
      </div>
    </div>
  </div>
</form>

<script>
function calculateRowConsumption(meterId, prev, avg) {
  const input = document.getElementById('reading_' + meterId);
  const consSpan = document.getElementById('cons_' + meterId);
  const statusDiv = document.getElementById('status_' + meterId);

  if (input.value === '') {
    consSpan.textContent = '-';
    statusDiv.innerHTML = '<span class="badge badge-light">Pending</span>';
    return;
  }

  const curr = parseFloat(input.value);
  const diff = curr - prev;

  consSpan.textContent = diff.toFixed(1) + ' m³';

  if (diff < 0) {
    consSpan.style.color = 'var(--danger)';
    statusDiv.innerHTML = '<span class="badge badge-danger"><i class="fa-solid fa-triangle-exclamation"></i> Negative</span>';
  } else if (avg > 0 && diff > (avg * 3) && diff > 30) {
    consSpan.style.color = 'var(--warning)';
    statusDiv.innerHTML = '<span class="badge badge-warning"><i class="fa-solid fa-arrow-trend-up"></i> Spike (>3x)</span>';
  } else if (diff === 0 && prev > 0) {
    consSpan.style.color = 'var(--danger)';
    statusDiv.innerHTML = '<span class="badge badge-warning"><i class="fa-solid fa-pause"></i> Zero Cons</span>';
  } else {
    consSpan.style.color = 'var(--success)';
    statusDiv.innerHTML = '<span class="badge badge-success"><i class="fa-solid fa-check"></i> Normal</span>';
  }
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
