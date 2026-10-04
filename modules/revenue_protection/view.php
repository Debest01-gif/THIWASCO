<?php
/**
 * THIWASCO MIS - Revenue Protection Single Case View
 */
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';

Auth::requirePermission('revenue');

$inspectionId = (int)($_GET['id'] ?? 0);
if ($inspectionId <= 0) {
    header('Location: ' . APP_URL . '/modules/revenue_protection/inspections.php');
    exit;
}

// Fetch inspection
$sql = "SELECT i.*, it.type_name, z.zone_code, z.zone_name,
               c.account_no, c.full_name, c.phone, c.physical_address, c.balance,
               m.meter_serial, m.current_reading,
               u1.full_name as inspector_name, u2.full_name as closer_name
        FROM field_inspections i
        JOIN inspection_types it ON i.inspection_type_id = it.type_id
        JOIN zones z ON i.zone_id = z.zone_id
        LEFT JOIN customers c ON i.customer_id = c.customer_id
        LEFT JOIN meters m ON i.meter_id = m.meter_id
        JOIN users u1 ON i.inspected_by = u1.user_id
        LEFT JOIN users u2 ON i.closed_by = u2.user_id
        WHERE i.inspection_id = ?";
$case = db()->fetch($sql, [$inspectionId]);

if (!$case) {
    setFlash('danger', 'Inspection case file not found.');
    header('Location: ' . APP_URL . '/modules/revenue_protection/inspections.php');
    exit;
}

// Handle Status & Action Update POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_case') {
    $newStatus = clean($_POST['status'] ?? 'Open');
    $actionTaken = clean($_POST['action_taken'] ?? '');
    $penaltyAmount = (float)($_POST['penalty_amount'] ?? 0);
    $invoicePenalty = isset($_POST['invoice_penalty']) && $_POST['invoice_penalty'] == '1';

    try {
        $pdo = Database::getInstance()->getConnection();
        $pdo->beginTransaction();

        $closedBy = in_array($newStatus, ['Resolved', 'Closed']) ? Auth::id() : null;
        $closedAt = in_array($newStatus, ['Resolved', 'Closed']) ? date('Y-m-d H:i:s') : null;

        $stmt = $pdo->prepare("UPDATE field_inspections SET 
            status = ?, action_taken = ?, penalty_amount = ?, closed_by = ?, closed_at = ?
            WHERE inspection_id = ?");
        $stmt->execute([$newStatus, $actionTaken, $penaltyAmount, $closedBy, $closedAt, $inspectionId]);

        // If penalty invoiced to customer account
        if ($invoicePenalty && $case['customer_id'] && !$case['penalty_invoiced'] && $penaltyAmount > 0) {
            $currentYM = date('Ym');
            $invRef = generateRef('INV', 'invoices', 'invoice_no');
            $today = date('Y-m-d');
            $dueDate = date('Y-m-d', strtotime('+14 days'));
            $pdo->prepare("INSERT INTO invoices 
                (invoice_no, customer_id, meter_id, billing_month, invoice_date, due_date, penalties, total_bill, arrears_brought_forward, total_payable, status, generated_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Unpaid', ?)")
                ->execute([
                    $invRef,
                    $case['customer_id'],
                    $case['meter_id'] ?: 1,
                    date('Y-m'),
                    $today,
                    $dueDate,
                    $penaltyAmount,
                    $penaltyAmount,
                    $case['balance'],
                    $case['balance'] + $penaltyAmount,
                    Auth::id()
                ]);
            $pdo->prepare("UPDATE customers SET balance = balance + ? WHERE customer_id = ?")->execute([$penaltyAmount, $case['customer_id']]);
            $pdo->prepare("UPDATE field_inspections SET penalty_invoiced = 1 WHERE inspection_id = ?")->execute([$inspectionId]);
        }

        $pdo->commit();
        Auth::logAction('Updated Case File', 'revenue', "Updated inspection {$case['inspection_ref']}: status {$newStatus}");
        setFlash('success', "Case file {$case['inspection_ref']} updated successfully.");
        header('Location: ' . APP_URL . '/modules/revenue_protection/view.php?id=' . $inspectionId);
        exit;
    } catch (Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
        setFlash('danger', 'Update failed: ' . $e->getMessage());
    }
}

$pageTitle = 'Case File: ' . $case['inspection_ref'];
$activePage = 'rp_inspections';
$activeSection = 'revenue';

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
  <div class="page-title-wrap">
    <h1><i class="fa-solid fa-shield-halved" style="color:var(--gold);"></i> Case File: <?= htmlspecialchars($case['inspection_ref']) ?></h1>
    <p>Violation: <?= htmlspecialchars($case['type_name']) ?> | Location: <?= htmlspecialchars($case['zone_name']) ?></p>
  </div>
  <div class="page-actions">
    <a href="<?= APP_URL ?>/modules/revenue_protection/inspections.php" class="btn btn-secondary">
      <i class="fa-solid fa-arrow-left"></i> All Inspections
    </a>
  </div>
</div>

<div class="row" style="display:grid;grid-template-columns: 1.4fr 1fr; gap:1.5rem;">
  
  <!-- LEFT: CASE PARTICULARS & EVIDENCE -->
  <div>
    <div class="card mb-4">
      <div class="card-header" style="background:var(--navy);color:#fff;">
        <h3 style="color:var(--gold);"><i class="fa-solid fa-file-contract"></i> Incident Report Summary</h3>
        <span class="badge badge-warning" style="font-size:0.9rem;"><?= htmlspecialchars($case['status']) ?></span>
      </div>
      <div class="card-body">
        <div style="font-size:0.95rem;line-height:1.7;margin-bottom:1.5rem;">
          <div><strong>Case Reference:</strong> <code style="font-size:1.1rem;color:var(--royal-blue);"><?= htmlspecialchars($case['inspection_ref']) ?></code></div>
          <div><strong>Inspection Date:</strong> <?= formatDate($case['inspection_date']) ?></div>
          <div><strong>Violation Type:</strong> <span class="badge badge-danger"><?= htmlspecialchars($case['type_name']) ?></span></div>
          <div><strong>Distribution Zone:</strong> <?= htmlspecialchars($case['zone_code'] . ' - ' . $case['zone_name']) ?></div>
          <div><strong>Lead Inspector:</strong> <?= htmlspecialchars($case['inspector_name']) ?></div>
          <?php if ($case['gps_lat'] && $case['gps_lng']): ?>
            <div><strong>GPS Coordinates:</strong> <code><?= $case['gps_lat'] ?>, <?= $case['gps_lng'] ?></code></div>
          <?php endif; ?>
        </div>

        <div style="background:var(--bg-light);padding:1.25rem;border-radius:8px;border-left:4px solid var(--danger);margin-bottom:1.5rem;">
          <h4 style="margin:0 0 6px 0;color:var(--navy);font-size:1rem;"><i class="fa-solid fa-magnifying-glass"></i> Inspection Findings</h4>
          <p style="margin:0;font-size:0.9rem;line-height:1.6;color:var(--text-color);white-space:pre-wrap;"><?= htmlspecialchars($case['findings']) ?></p>
        </div>

        <?php if ($case['action_taken']): ?>
          <div style="background:rgba(21,128,61,0.06);padding:1.25rem;border-radius:8px;border-left:4px solid var(--success);margin-bottom:1.5rem;">
            <h4 style="margin:0 0 6px 0;color:var(--success);font-size:1rem;"><i class="fa-solid fa-check"></i> Action Taken on Site</h4>
            <p style="margin:0;font-size:0.9rem;line-height:1.6;color:var(--text-color);"><?= htmlspecialchars($case['action_taken']) ?></p>
          </div>
        <?php endif; ?>

        <!-- EVIDENCE PHOTO -->
        <?php if ($case['evidence_photo1']): ?>
          <div style="margin-top:1.5rem;">
            <h4 style="font-size:0.95rem;color:var(--navy);margin-bottom:0.75rem;"><i class="fa-solid fa-camera"></i> Photographic Evidence</h4>
            <div style="border:1px solid var(--border-color);border-radius:8px;overflow:hidden;max-width:400px;">
              <img src="<?= UPLOAD_URL ?>inspection_photos/<?= htmlspecialchars($case['evidence_photo1']) ?>" alt="Evidence" style="width:100%;height:auto;display:block;">
            </div>
          </div>
        <?php endif; ?>

      </div>
    </div>
  </div>

  <!-- RIGHT: FINANCIAL LOSS & CASE ENFORCEMENT -->
  <div>
    <!-- LOSS QUANTIFICATION -->
    <div class="card mb-4" style="border-left:4px solid var(--gold);">
      <div class="card-header">
        <h3><i class="fa-solid fa-scale-balanced" style="color:var(--gold);"></i> Loss Assessment & Penalty</h3>
      </div>
      <div class="card-body">
        <div style="margin-bottom:1rem;">
          <span style="font-size:0.8rem;text-transform:uppercase;color:var(--text-muted);font-weight:600;">Estimated Water Stolen</span>
          <div style="font-size:1.4rem;font-weight:800;color:var(--navy);"><?= number_format($case['estimated_loss_m3'], 1) ?> m³</div>
        </div>
        <div style="margin-bottom:1rem;">
          <span style="font-size:0.8rem;text-transform:uppercase;color:var(--text-muted);font-weight:600;">Calculated Revenue Leakage</span>
          <div style="font-size:1.4rem;font-weight:800;color:var(--danger);"><?= formatCurrency($case['estimated_revenue_loss']) ?></div>
        </div>
        <div>
          <span style="font-size:0.8rem;text-transform:uppercase;color:var(--text-muted);font-weight:600;">Statutory Penalty Assessed</span>
          <div style="font-size:1.6rem;font-weight:800;color:var(--gold);"><?= formatCurrency($case['penalty_amount']) ?></div>
          <?php if ($case['penalty_invoiced']): ?>
            <span class="badge badge-success mt-1"><i class="fa-solid fa-check"></i> Penalty Invoiced to Account</span>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- ASSOCIATED CUSTOMER -->
    <?php if ($case['customer_id']): ?>
      <div class="card mb-4">
        <div class="card-header">
          <h3><i class="fa-solid fa-user" style="color:var(--royal-blue);"></i> Implicated Customer</h3>
        </div>
        <div class="card-body" style="font-size:0.9rem;line-height:1.7;">
          <div style="font-weight:700;color:var(--navy);font-size:1.05rem;"><?= htmlspecialchars($case['full_name']) ?></div>
          <div><strong>Account:</strong> <a href="<?= APP_URL ?>/modules/customers/view.php?id=<?= $case['customer_id'] ?>" style="color:var(--royal-blue);"><?= htmlspecialchars($case['account_no']) ?></a></div>
          <div><strong>Phone:</strong> <?= htmlspecialchars($case['phone']) ?></div>
          <div><strong>Meter:</strong> <?= htmlspecialchars($case['meter_serial'] ?? 'None') ?></div>
          <div><strong>Account Balance:</strong> <strong style="color:var(--danger);"><?= formatCurrency($case['balance']) ?></strong></div>
        </div>
      </div>
    <?php endif; ?>

    <!-- UPDATE / RESOLVE CASE -->
    <div class="card">
      <div class="card-header">
        <h3><i class="fa-solid fa-gavel" style="color:var(--royal-blue);"></i> Update Case Status</h3>
      </div>
      <div class="card-body">
        <form method="POST" action="">
          <input type="hidden" name="action" value="update_case">

          <div class="form-group mb-3">
            <label class="form-label">Case Status</label>
            <select name="status" class="form-control" required>
              <option value="Open" <?= $case['status'] === 'Open' ? 'selected' : '' ?>>Open</option>
              <option value="Under Investigation" <?= $case['status'] === 'Under Investigation' ? 'selected' : '' ?>>Under Investigation</option>
              <option value="Resolved" <?= $case['status'] === 'Resolved' ? 'selected' : '' ?>>Resolved</option>
              <option value="Closed" <?= $case['status'] === 'Closed' ? 'selected' : '' ?>>Closed</option>
              <option value="Referred" <?= $case['status'] === 'Referred' ? 'selected' : '' ?>>Referred to Legal</option>
            </select>
          </div>

          <div class="form-group mb-3">
            <label class="form-label">Adjust Penalty Amount (KES)</label>
            <input type="number" step="0.01" name="penalty_amount" class="form-control" value="<?= $case['penalty_amount'] ?>">
          </div>

          <?php if ($case['customer_id'] && !$case['penalty_invoiced']): ?>
            <div class="form-group mb-3" style="background:var(--bg-light);padding:0.75rem;border-radius:6px;">
              <label style="display:flex;align-items:center;gap:0.5rem;cursor:pointer;font-weight:600;font-size:0.85rem;">
                <input type="checkbox" name="invoice_penalty" value="1">
                Post penalty directly to customer billing invoice
              </label>
            </div>
          <?php endif; ?>

          <div class="form-group mb-3">
            <label class="form-label">Action Taken / Resolution Summary</label>
            <textarea name="action_taken" class="form-control" rows="2"><?= htmlspecialchars($case['action_taken'] ?? '') ?></textarea>
          </div>

          <button type="submit" class="btn btn-primary" style="width:100%;">
            <i class="fa-solid fa-save"></i> Update Case File
          </button>
        </form>
      </div>
    </div>

  </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
