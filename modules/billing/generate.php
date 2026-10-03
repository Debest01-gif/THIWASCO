<?php
/**
 * THIWASCO MIS - Bulk Bill Generation Engine
 */
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';

Auth::requirePermission('billing');

$pageTitle = 'Generate Monthly Bills';
$activePage = 'billing_generate';
$activeSection = 'billing';

$errors = [];
$successMessage = '';
$generationResults = null;

// Available zones
$zones = db()->fetchAll("SELECT * FROM zones WHERE is_active = 1 ORDER BY zone_name ASC");

// Handle bill generation POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'generate_bills') {
    $billingMonth = clean($_POST['billing_month'] ?? '');
    $zoneId = !empty($_POST['zone_id']) ? (int)$_POST['zone_id'] : null;
    $dueDate = clean($_POST['due_date'] ?? '');
    $invoiceDate = clean($_POST['invoice_date'] ?? date('Y-m-d'));
    $estimateMissing = isset($_POST['estimate_missing']) && $_POST['estimate_missing'] == '1';

    if (empty($billingMonth) || !preg_match('/^\d{4}-\d{2}$/', $billingMonth)) {
        $errors[] = 'Please select a valid billing month (YYYY-MM).';
    }
    if (empty($dueDate)) {
        $errors[] = 'Due date is required.';
    }

    if (empty($errors)) {
        try {
            $pdo = Database::getInstance()->getConnection();
            $pdo->beginTransaction();

            // Fetch active customers matching zone
            $custSql = "SELECT c.*, m.meter_id, m.meter_serial, m.current_reading as last_known_reading,
                               t.tariff_code, t.tariff_name, t.base_charge, t.rate_per_m3, 
                               t.sewer_percent, t.min_charge, t.tiered_rates
                        FROM customers c
                        JOIN meters m ON c.customer_id = m.customer_id AND m.status = 'Active'
                        JOIN tariff_categories t ON c.tariff_id = t.tariff_id
                        WHERE c.status IN ('Active', 'Defaulter')";
            $params = [];
            if ($zoneId) {
                $custSql .= " AND c.zone_id = ?";
                $params[] = $zoneId;
            }
            $customers = db()->fetchAll($custSql, $params);

            $billedCount = 0;
            $skippedCount = 0;
            $estimatedCount = 0;
            $totalVolume = 0;
            $totalBilledAmount = 0;
            $totalSewerAmount = 0;
            $totalBaseAmount = 0;
            $totalArrears = 0;

            // Prepare statements
            $stmtCheckInv = $pdo->prepare("SELECT invoice_id FROM invoices WHERE customer_id = ? AND billing_month = ?");
            $stmtReading = $pdo->prepare("SELECT * FROM meter_readings WHERE meter_id = ? AND billing_month = ?");
            $stmtAvgReading = $pdo->prepare("SELECT AVG(current_reading - previous_reading) as avg_consumption 
                                             FROM meter_readings 
                                             WHERE meter_id = ? AND billing_month < ? 
                                             ORDER BY billing_month DESC LIMIT 3");
            $stmtInsertReading = $pdo->prepare("INSERT INTO meter_readings 
                (meter_id, customer_id, read_by, billing_month, reading_date, previous_reading, current_reading, reading_type, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'Estimated', 'Auto-estimated during bulk billing')");
            $stmtInsertInv = $pdo->prepare("INSERT INTO invoices 
                (invoice_no, customer_id, meter_id, reading_id, billing_month, invoice_date, due_date,
                 previous_reading, current_reading, consumption_m3, water_charge, sewer_charge,
                 base_charge, penalties, other_charges, vat_amount, total_bill, arrears_brought_forward,
                 total_payable, amount_paid, status, generated_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0.00, 0.00, 0.00, ?, ?, ?, 0.00, 'Unpaid', ?)");
            $stmtUpdateCust = $pdo->prepare("UPDATE customers SET balance = balance + ? WHERE customer_id = ?");
            $stmtUpdateMeter = $pdo->prepare("UPDATE meters SET current_reading = ?, last_read_date = ? WHERE meter_id = ?");

            $currentYearMonth = date('Ym');
            $refCounter = 1;
            // Get max sequence for current month
            $lastInv = db()->fetch("SELECT MAX(invoice_no) as last_ref FROM invoices WHERE invoice_no LIKE ?", ["INV{$currentYearMonth}%"]);
            if ($lastInv && $lastInv['last_ref']) {
                $refCounter = (int)substr($lastInv['last_ref'], -4) + 1;
            }

            foreach ($customers as $cust) {
                // Check if already billed this month
                $stmtCheckInv->execute([$cust['customer_id'], $billingMonth]);
                if ($stmtCheckInv->fetch()) {
                    $skippedCount++;
                    continue;
                }

                // Check for meter reading
                $stmtReading->execute([$cust['meter_id'], $billingMonth]);
                $reading = $stmtReading->fetch();
                $readingId = null;
                $prevReading = (float)$cust['last_known_reading'];
                $currReading = $prevReading;
                $consumption = 0;

                if ($reading) {
                    $readingId = $reading['reading_id'];
                    $prevReading = (float)$reading['previous_reading'];
                    $currReading = (float)$reading['current_reading'];
                    $consumption = max(0, $currReading - $prevReading);
                } elseif ($estimateMissing) {
                    // Estimate reading based on 3-month average
                    $stmtAvgReading->execute([$cust['meter_id'], $billingMonth]);
                    $avgRow = $stmtAvgReading->fetch();
                    $avgCons = ($avgRow && $avgRow['avg_consumption'] !== null) ? max(1, round((float)$avgRow['avg_consumption'], 1)) : 10.0;
                    
                    $currReading = $prevReading + $avgCons;
                    $consumption = $avgCons;

                    $stmtInsertReading->execute([
                        $cust['meter_id'],
                        $cust['customer_id'],
                        Auth::id(),
                        $billingMonth,
                        $invoiceDate,
                        $prevReading,
                        $currReading
                    ]);
                    $readingId = $pdo->lastInsertId();
                    $stmtUpdateMeter->execute([$currReading, $invoiceDate, $cust['meter_id']]);
                    $estimatedCount++;
                } else {
                    // Skip customer with missing reading
                    $skippedCount++;
                    continue;
                }

                // Tiered Water Charge Calculation
                $waterCharge = 0.00;
                $tieredRates = json_decode($cust['tiered_rates'], true);

                if (!empty($tieredRates) && is_array($tieredRates)) {
                    $remConsumption = $consumption;
                    foreach ($tieredRates as $tier) {
                        $from = (float)$tier['from'];
                        $to = (float)$tier['to'];
                        $rate = (float)$tier['rate'];
                        $tierSpan = ($to >= 999999) ? 999999 : ($to - $from + 1);

                        if ($remConsumption > 0) {
                            $unitsInTier = min($remConsumption, $tierSpan);
                            $waterCharge += ($unitsInTier * $rate);
                            $remConsumption -= $unitsInTier;
                        }
                    }
                } else {
                    $waterCharge = $consumption * (float)$cust['rate_per_m3'];
                }

                // Apply minimum charge if applicable
                $minCharge = (float)$cust['min_charge'];
                if ($waterCharge < $minCharge && $consumption > 0) {
                    $waterCharge = $minCharge;
                }

                // Sewer charge
                $sewerPercent = (float)$cust['sewer_percent'];
                $sewerCharge = round(($waterCharge * $sewerPercent) / 100, 2);

                // Base / Meter rent charge
                $baseCharge = (float)$cust['base_charge'];

                // Total current bill
                $totalBill = $waterCharge + $sewerCharge + $baseCharge;
                $arrearsBroughtForward = (float)$cust['balance'];
                $totalPayable = $totalBill + $arrearsBroughtForward;

                // Unique invoice number
                $invoiceNo = 'INV' . $currentYearMonth . str_pad($refCounter, 4, '0', STR_PAD_LEFT);
                $refCounter++;

                // Insert Invoice
                $stmtInsertInv->execute([
                    $invoiceNo,
                    $cust['customer_id'],
                    $cust['meter_id'],
                    $readingId,
                    $billingMonth,
                    $invoiceDate,
                    $dueDate,
                    $prevReading,
                    $currReading,
                    $consumption,
                    $waterCharge,
                    $sewerCharge,
                    $baseCharge,
                    $totalBill,
                    $arrearsBroughtForward,
                    $totalPayable,
                    Auth::id()
                ]);

                // Update Customer balance
                $stmtUpdateCust->execute([$totalBill, $cust['customer_id']]);

                // Accumulate totals
                $billedCount++;
                $totalVolume += $consumption;
                $totalBilledAmount += $totalBill;
                $totalSewerAmount += $sewerCharge;
                $totalBaseAmount += $baseCharge;
                $totalArrears += $arrearsBroughtForward;
            }

            $pdo->commit();

            Auth::logAction('Generated Bills', 'billing', "Billed {$billedCount} accounts for {$billingMonth}. Total KES {$totalBilledAmount}");

            $generationResults = [
                'billing_month' => $billingMonth,
                'billed_count' => $billedCount,
                'skipped_count' => $skippedCount,
                'estimated_count' => $estimatedCount,
                'total_volume' => $totalVolume,
                'total_billed_amount' => $totalBilledAmount,
                'total_sewer_amount' => $totalSewerAmount,
                'total_base_amount' => $totalBaseAmount,
                'total_arrears' => $totalArrears
            ];

            $successMessage = "Billing generation completed successfully for <strong>{$billingMonth}</strong>!";

        } catch (Exception $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = 'Bill generation failed: ' . $e->getMessage();
        }
    }
}

// Current month default
$defaultMonth = date('Y-m');
$defaultDueDate = date('Y-m-15', strtotime('+1 month'));

// Get summary of existing invoices for current month
$existingCount = db()->fetch("SELECT COUNT(*) as cnt, COALESCE(SUM(total_bill), 0) as total FROM invoices WHERE billing_month = ?", [$defaultMonth]);

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
  <div class="page-title-wrap">
    <h1><i class="fa-solid fa-bolt" style="color:var(--gold);"></i> Bulk Bill Generation</h1>
    <p>Calculate consumption, apply tiered tariffs, compute sewerage & base fees, and post monthly customer invoices</p>
  </div>
  <div class="page-actions">
    <a href="<?= APP_URL ?>/modules/billing/index.php" class="btn btn-secondary">
      <i class="fa-solid fa-file-invoice"></i> View Invoices
    </a>
    <a href="<?= APP_URL ?>/modules/billing/tariffs.php" class="btn btn-secondary">
      <i class="fa-solid fa-tags"></i> Tariff Rates
    </a>
  </div>
</div>

<?php if (!empty($errors)): ?>
  <div class="alert alert-danger">
    <i class="fa-solid fa-circle-exclamation"></i>
    <div>
      <strong>Errors Encountered:</strong>
      <ul style="margin:4px 0 0 16px;padding:0;">
        <?php foreach ($errors as $err): ?>
          <li><?= htmlspecialchars($err) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  </div>
<?php endif; ?>

<?php if ($successMessage && $generationResults): ?>
  <div class="alert alert-success">
    <i class="fa-solid fa-circle-check"></i>
    <div><?= $successMessage ?></div>
  </div>

  <!-- GENERATION RESULTS SUMMARY CARD -->
  <div class="card mb-4" style="border: 2px solid var(--gold);">
    <div class="card-header" style="background: linear-gradient(135deg, var(--navy), var(--royal-blue)); color: white;">
      <h3 style="color:var(--gold);"><i class="fa-solid fa-chart-line"></i> Billing Execution Summary — Period: <?= htmlspecialchars($generationResults['billing_month']) ?></h3>
      <span class="badge badge-success"><i class="fa-solid fa-check"></i> Success</span>
    </div>
    <div class="card-body">
      <div class="stats-grid mb-3">
        <div class="stat-card">
          <div class="stat-icon" style="background:rgba(21,128,61,0.1);color:var(--success);"><i class="fa-solid fa-file-circle-check"></i></div>
          <div class="stat-info">
            <span class="stat-value"><?= number_format($generationResults['billed_count']) ?></span>
            <span class="stat-label">Invoices Created</span>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon" style="background:rgba(234,179,8,0.1);color:var(--gold);"><i class="fa-solid fa-droplet"></i></div>
          <div class="stat-info">
            <span class="stat-value"><?= number_format($generationResults['total_volume'], 1) ?> m³</span>
            <span class="stat-label">Billed Consumption</span>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon" style="background:rgba(30,58,138,0.1);color:var(--royal-blue);"><i class="fa-solid fa-coins"></i></div>
          <div class="stat-info">
            <span class="stat-value"><?= formatCurrency($generationResults['total_billed_amount']) ?></span>
            <span class="stat-label">Total Billed Revenue</span>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon" style="background:rgba(239,68,68,0.1);color:var(--danger);"><i class="fa-solid fa-clock-rotate-left"></i></div>
          <div class="stat-info">
            <span class="stat-value"><?= formatCurrency($generationResults['total_arrears']) ?></span>
            <span class="stat-label">Arrears B/Forward</span>
          </div>
        </div>
      </div>

      <div style="display:flex;gap:1.5rem;flex-wrap:wrap;background:var(--bg-light);padding:1rem;border-radius:8px;">
        <div><strong>Base Charges:</strong> <?= formatCurrency($generationResults['total_base_amount']) ?></div>
        <div><strong>Sewerage Charges:</strong> <?= formatCurrency($generationResults['total_sewer_amount']) ?></div>
        <div><strong>Auto-Estimated Readings:</strong> <?= number_format($generationResults['estimated_count']) ?></div>
        <div><strong>Skipped (Already billed / No meter):</strong> <?= number_format($generationResults['skipped_count']) ?></div>
      </div>

      <div style="margin-top:1.25rem;display:flex;gap:1rem;">
        <a href="<?= APP_URL ?>/modules/billing/index.php?billing_month=<?= urlencode($generationResults['billing_month']) ?>" class="btn btn-primary">
          <i class="fa-solid fa-list-check"></i> View Generated Invoices
        </a>
        <a href="<?= APP_URL ?>/modules/reports/billing.php?billing_month=<?= urlencode($generationResults['billing_month']) ?>" class="btn btn-secondary">
          <i class="fa-solid fa-print"></i> Monthly Billing Report
        </a>
      </div>
    </div>
  </div>
<?php endif; ?>

<div class="row" style="display:grid;grid-template-columns: 1.5fr 1fr;gap:1.5rem;">
  <!-- LEFT: GENERATION FORM -->
  <div class="card">
    <div class="card-header">
      <h3><i class="fa-solid fa-gears" style="color:var(--royal-blue);"></i> Bill Generation Parameters</h3>
      <span class="badge badge-info">Automated Tier Engine</span>
    </div>
    <div class="card-body">
      <form method="POST" action="" id="generateBillsForm" onsubmit="return confirm('Are you sure you want to generate bills for the selected period? This will post invoices and update customer balances.');">
        <input type="hidden" name="action" value="generate_bills">

        <div class="form-group mb-3">
          <label class="form-label">Billing Month <span class="text-danger">*</span></label>
          <input type="month" name="billing_month" class="form-control" value="<?= htmlspecialchars($_POST['billing_month'] ?? $defaultMonth) ?>" required>
          <small class="text-muted">Target billing cycle (e.g., 2026-10 for October 2026)</small>
        </div>

        <div class="form-group mb-3">
          <label class="form-label">Target Distribution Zone</label>
          <select name="zone_id" class="form-control">
            <option value="">-- All Zones (Company Wide) --</option>
            <?php foreach ($zones as $z): ?>
              <option value="<?= $z['zone_id'] ?>" <?= (isset($_POST['zone_id']) && $_POST['zone_id'] == $z['zone_id']) ? 'selected' : '' ?>>
                <?= htmlspecialchars($z['zone_code'] . ' - ' . $z['zone_name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <small class="text-muted">Optionally isolate billing to an individual DMA or zone</small>
        </div>

        <div class="row" style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
          <div class="form-group mb-3">
            <label class="form-label">Invoice Date <span class="text-danger">*</span></label>
            <input type="date" name="invoice_date" class="form-control" value="<?= htmlspecialchars($_POST['invoice_date'] ?? date('Y-m-d')) ?>" required>
          </div>
          <div class="form-group mb-3">
            <label class="form-label">Payment Due Date <span class="text-danger">*</span></label>
            <input type="date" name="due_date" class="form-control" value="<?= htmlspecialchars($_POST['due_date'] ?? $defaultDueDate) ?>" required>
          </div>
        </div>

        <div class="form-group mb-4" style="background:var(--bg-light);padding:1rem;border-radius:6px;border-left:3px solid var(--royal-blue);">
          <label style="display:flex;align-items:center;gap:0.5rem;font-weight:600;cursor:pointer;">
            <input type="checkbox" name="estimate_missing" value="1" checked style="width:18px;height:18px;">
            Estimate consumption for unread meters (3-month historical rolling average)
          </label>
          <small class="text-muted" style="display:block;margin-left:26px;margin-top:4px;">
            If unchecked, customers without actual meter readings for this month will be skipped.
          </small>
        </div>

        <div class="form-actions" style="display:flex;justify-content:flex-end;gap:1rem;">
          <button type="reset" class="btn btn-secondary"><i class="fa-solid fa-rotate-left"></i> Reset</button>
          <button type="submit" class="btn btn-primary" style="padding:0.75rem 1.75rem;font-size:1rem;">
            <i class="fa-solid fa-bolt"></i> Execute Bill Generation
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- RIGHT: BILLING INFO & CHECKS -->
  <div>
    <div class="card mb-3">
      <div class="card-header">
        <h3><i class="fa-solid fa-circle-info" style="color:var(--royal-blue);"></i> Billing Cycle Status</h3>
      </div>
      <div class="card-body">
        <div style="margin-bottom:1rem;">
          <div style="font-size:0.85rem;color:var(--text-muted);text-transform:uppercase;font-weight:600;">Current Cycle</div>
          <div style="font-size:1.3rem;font-weight:700;color:var(--navy);"><?= date('F Y') ?> (<?= $defaultMonth ?>)</div>
        </div>
        <div style="margin-bottom:1rem;">
          <div style="font-size:0.85rem;color:var(--text-muted);text-transform:uppercase;font-weight:600;">Invoices Already Generated</div>
          <div style="font-size:1.1rem;font-weight:700;color:var(--royal-blue);">
            <?= number_format($existingCount['cnt']) ?> invoices (<?= formatCurrency($existingCount['total']) ?>)
          </div>
        </div>
        <div class="alert alert-warning" style="margin-bottom:0;font-size:0.85rem;">
          <i class="fa-solid fa-triangle-exclamation"></i>
          <span>Generating bills twice for the same account in the same month is automatically skipped to prevent double billing.</span>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-header">
        <h3><i class="fa-solid fa-calculator" style="color:var(--gold);"></i> Active Tariff Structure</h3>
      </div>
      <div class="card-body p-0">
        <table class="table" style="font-size:0.85rem;">
          <thead>
            <tr>
              <th>Tariff</th>
              <th>Base Fee</th>
              <th>Sewer %</th>
              <th>Tiers</th>
            </tr>
          </thead>
          <tbody>
            <?php 
            $activeTariffs = db()->fetchAll("SELECT * FROM tariff_categories WHERE is_active = 1");
            foreach ($activeTariffs as $t): 
            ?>
              <tr>
                <td><strong><?= htmlspecialchars($t['tariff_code']) ?></strong><br><small class="text-muted"><?= htmlspecialchars($t['tariff_name']) ?></small></td>
                <td><?= formatCurrency($t['base_charge']) ?></td>
                <td><?= number_format($t['sewer_percent'], 0) ?>%</td>
                <td>
                  <?php 
                  $tiers = json_decode($t['tiered_rates'], true);
                  if (!empty($tiers)) {
                    foreach ($tiers as $tr) {
                      $toLabel = $tr['to'] >= 999999 ? '∞' : $tr['to'];
                      echo "<span class='badge badge-light' style='margin:1px;'>{$tr['from']}-{$toLabel}m³: @{$tr['rate']}</span> ";
                    }
                  } else {
                    echo "@" . number_format($t['rate_per_m3'], 2);
                  }
                  ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
