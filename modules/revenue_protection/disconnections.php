<?php
/**
 * THIWASCO MIS - Disconnections & Enforcement Orders Register
 */
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';

Auth::requirePermission('revenue');

$pageTitle = 'Disconnection Orders Register';
$activePage = 'rp_disconnections';
$activeSection = 'revenue';

$statusFilter = clean($_GET['status'] ?? '');

$where = ["1=1"];
$params = [];

if ($statusFilter === 'active') {
    $where[] = "d.reconnect_date IS NULL";
} elseif ($statusFilter === 'reconnected') {
    $where[] = "d.reconnect_date IS NOT NULL";
}

$whereSql = implode(" AND ", $where);

$sql = "SELECT d.*, c.account_no, c.full_name, c.phone, z.zone_code, m.meter_serial,
               u1.full_name as disconnector, u2.full_name as reconnecter
        FROM disconnections d
        JOIN customers c ON d.customer_id = c.customer_id
        JOIN zones z ON c.zone_id = z.zone_id
        LEFT JOIN meters m ON d.meter_id = m.meter_id
        JOIN users u1 ON d.disconnected_by = u1.user_id
        LEFT JOIN users u2 ON d.reconnected_by = u2.user_id
        WHERE {$whereSql}
        ORDER BY d.disconnect_date DESC";
$records = db()->fetchAll($sql, $params);

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
  <div class="page-title-wrap">
    <h1><i class="fa-solid fa-link-slash" style="color:var(--danger);"></i> Disconnections & Reconnection Orders</h1>
    <p>Official registry of physical service cut-offs, isolation seals, arrears at disconnection and reconnection fee collections</p>
  </div>
  <div class="page-actions">
    <a href="<?= APP_URL ?>/modules/customers/defaulters.php" class="btn btn-secondary">
      <i class="fa-solid fa-triangle-exclamation"></i> Defaulters Queue
    </a>
  </div>
</div>

<!-- FILTER BAR -->
<div class="card mb-4">
  <div class="card-body" style="padding:1rem 1.25rem;">
    <form method="GET" action="" style="display:flex;gap:1rem;align-items:center;">
      <label class="form-label mb-0" style="font-weight:600;">Filter Status:</label>
      <select name="status" class="form-control" style="width:200px;" onchange="this.form.submit()">
        <option value="">All Enforcement Records</option>
        <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Currently Disconnected</option>
        <option value="reconnected" <?= $statusFilter === 'reconnected' ? 'selected' : '' ?>>Restored / Reconnected</option>
      </select>
    </form>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <h3><i class="fa-solid fa-scissors" style="color:var(--danger);"></i> Disconnection Ledger</h3>
    <span class="badge badge-light"><?= count($records) ?> Records</span>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table">
        <thead>
          <tr>
            <th>Customer & Account</th>
            <th>Zone</th>
            <th>Meter</th>
            <th>Cut-off Date</th>
            <th>Reason</th>
            <th style="text-align:right;">Arrears at Cut</th>
            <th>Enforced By</th>
            <th>Restoration Date</th>
            <th>Recon Fee</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($records)): ?>
            <tr><td colspan="10" class="text-center py-4 text-muted">No disconnection records found.</td></tr>
          <?php else: ?>
            <?php foreach ($records as $r): 
              $isRestored = ($r['reconnect_date'] !== null);
            ?>
              <tr>
                <td>
                  <a href="<?= APP_URL ?>/modules/customers/view.php?id=<?= $r['customer_id'] ?>" style="font-weight:700;color:var(--navy);">
                    <?= htmlspecialchars($r['full_name']) ?>
                  </a>
                  <div style="font-size:0.8rem;color:var(--text-muted);"><?= htmlspecialchars($r['account_no']) ?></div>
                </td>
                <td><span class="badge badge-light"><?= htmlspecialchars($r['zone_code']) ?></span></td>
                <td><code><?= htmlspecialchars($r['meter_serial'] ?? 'None') ?></code></td>
                <td><strong><?= formatDate($r['disconnect_date']) ?></strong></td>
                <td><span class="badge badge-warning"><?= htmlspecialchars($r['reason']) ?></span></td>
                <td style="text-align:right;font-weight:700;color:var(--danger);"><?= formatCurrency($r['arrears_at_disconnect']) ?></td>
                <td><small><?= htmlspecialchars($r['disconnector']) ?></small></td>
                <td><?= formatDate($r['reconnect_date']) ?></td>
                <td><?= $r['reconnect_fee'] > 0 ? formatCurrency($r['reconnect_fee']) : '-' ?></td>
                <td>
                  <?= $isRestored ? '<span class="badge badge-success">Restored</span>' : '<span class="badge badge-danger">Disconnected</span>' ?>
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
