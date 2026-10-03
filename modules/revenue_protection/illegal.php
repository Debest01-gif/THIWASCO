<?php
/**
 * THIWASCO MIS - Illegal Connections & Anti-Theft Enforcement
 */
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';

Auth::requirePermission('revenue');

$pageTitle = 'Illegal Connections Register';
$activePage = 'rp_illegal';
$activeSection = 'revenue';

// Fetch inspections categorized as Illegal Connection or Meter Bypass
$sql = "SELECT i.*, it.type_name, z.zone_code, z.zone_name, c.account_no, c.full_name, c.phone, u.full_name as inspector_name
        FROM field_inspections i
        JOIN inspection_types it ON i.inspection_type_id = it.type_id
        JOIN zones z ON i.zone_id = z.zone_id
        LEFT JOIN customers c ON i.customer_id = c.customer_id
        JOIN users u ON i.inspected_by = u.user_id
        WHERE it.type_name IN ('Illegal Connection', 'Meter Bypass', 'Illegal Reconnection')
        ORDER BY i.inspection_date DESC";
$illegalCases = db()->fetchAll($sql);

$totalIllegal = count($illegalCases);
$totalRecoveredLoss = array_sum(array_column($illegalCases, 'estimated_revenue_loss'));
$totalPenalties = array_sum(array_column($illegalCases, 'penalty_amount'));

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
  <div class="page-title-wrap">
    <h1><i class="fa-solid fa-ban" style="color:var(--danger);"></i> Illegal Connections & Anti-Theft</h1>
    <p>Targeted enforcement on unauthorized main branchings, clandestine bypasses, and unmetered commercial water theft</p>
  </div>
  <div class="page-actions">
    <a href="<?= APP_URL ?>/modules/revenue_protection/inspections.php" class="btn btn-primary">
      <i class="fa-solid fa-plus-circle"></i> Log New Case
    </a>
  </div>
</div>

<!-- STATS -->
<div class="stats-grid mb-4">
  <div class="stat-card">
    <div class="stat-icon" style="background:rgba(239,68,68,0.1);color:var(--danger);"><i class="fa-solid fa-triangle-exclamation"></i></div>
    <div class="stat-info">
      <span class="stat-value"><?= number_format($totalIllegal) ?></span>
      <span class="stat-label">Reported Theft Incidents</span>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:rgba(234,179,8,0.1);color:var(--gold);"><i class="fa-solid fa-coins"></i></div>
    <div class="stat-info">
      <span class="stat-value"><?= formatCurrency($totalRecoveredLoss) ?></span>
      <span class="stat-label">Estimated Revenue Leakage</span>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:rgba(21,128,61,0.1);color:var(--success);"><i class="fa-solid fa-gavel"></i></div>
    <div class="stat-info">
      <span class="stat-value"><?= formatCurrency($totalPenalties) ?></span>
      <span class="stat-label">Total Statutory Penalties</span>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <h3><i class="fa-solid fa-list-check" style="color:var(--danger);"></i> Unlawful Off-Take & Bypass Register</h3>
    <span class="badge badge-danger"><?= $totalIllegal ?> Violations</span>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table">
        <thead>
          <tr>
            <th>Case Ref</th>
            <th>Date</th>
            <th>Type</th>
            <th>Zone / Location</th>
            <th>Suspect / Customer</th>
            <th>Findings Summary</th>
            <th style="text-align:right;">Estimated Theft</th>
            <th style="text-align:right;">Fine</th>
            <th>Status</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($illegalCases)): ?>
            <tr><td colspan="10" class="text-center py-4 text-muted">No illegal connections on record.</td></tr>
          <?php else: ?>
            <?php foreach ($illegalCases as $c): ?>
              <tr>
                <td><strong style="color:var(--royal-blue);"><?= htmlspecialchars($c['inspection_ref']) ?></strong></td>
                <td><?= formatDate($c['inspection_date']) ?></td>
                <td><span class="badge badge-danger"><?= htmlspecialchars($c['type_name']) ?></span></td>
                <td><span class="badge badge-light"><?= htmlspecialchars($c['zone_code']) ?></span></td>
                <td>
                  <?php if ($c['customer_id']): ?>
                    <strong><?= htmlspecialchars($c['full_name']) ?></strong><br>
                    <small class="text-muted"><?= htmlspecialchars($c['account_no']) ?></small>
                  <?php else: ?>
                    <span class="text-muted">Unregistered Perpetrator</span>
                  <?php endif; ?>
                </td>
                <td><small><?= htmlspecialchars(substr($c['findings'], 0, 75)) ?>...</small></td>
                <td style="text-align:right;color:var(--danger);font-weight:700;"><?= formatCurrency($c['estimated_revenue_loss']) ?></td>
                <td style="text-align:right;color:var(--gold);font-weight:700;"><?= formatCurrency($c['penalty_amount']) ?></td>
                <td><span class="badge badge-warning"><?= htmlspecialchars($c['status']) ?></span></td>
                <td>
                  <a href="<?= APP_URL ?>/modules/revenue_protection/view.php?id=<?= $c['inspection_id'] ?>" class="btn btn-secondary btn-sm">
                    <i class="fa-solid fa-eye"></i> View Case
                  </a>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
