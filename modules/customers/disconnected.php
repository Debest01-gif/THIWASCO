<?php
/**
 * THIWASCO MIS - Disconnected Customers & Reconnection Management
 */
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';

Auth::requirePermission('customers');

$pageTitle = 'Disconnected Accounts & Recon';
$activePage = 'customers_disconnected';
$activeSection = 'customers';

// Handle Reconnection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reconnect_customer') {
    Auth::requirePermission('revenue');
    $discId = (int)$_POST['disconnection_id'];
    $reconnectFee = (float)($_POST['reconnect_fee'] ?? 1000.00);

    try {
        $pdo = Database::getInstance()->getConnection();
        $pdo->beginTransaction();

        $disc = $pdo->prepare("SELECT * FROM disconnections WHERE disconnection_id = ? FOR UPDATE");
        $disc->execute([$discId]);
        $row = $disc->fetch();

        if ($row) {
            // Update disconnection record
            $pdo->prepare("UPDATE disconnections SET reconnect_date = CURDATE(), reconnect_fee = ?, reconnected_by = ? WHERE disconnection_id = ?")
                ->execute([$reconnectFee, Auth::id(), $discId]);

            // Reactivate customer
            $pdo->prepare("UPDATE customers SET status = 'Active' WHERE customer_id = ?")
                ->execute([$row['customer_id']]);

            $pdo->commit();
            Auth::logAction('Reconnected Customer', 'revenue', "Reconnected customer ID {$row['customer_id']} with fee KES {$reconnectFee}");
            setFlash('success', 'Customer account reconnected successfully.');
        }
    } catch (Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
        setFlash('danger', 'Reconnection failed: ' . $e->getMessage());
    }
    header('Location: ' . APP_URL . '/modules/customers/disconnected.php');
    exit;
}

$sql = "SELECT c.*, z.zone_code, z.zone_name, m.meter_serial,
               d.disconnection_id, d.disconnect_date, d.reason, d.arrears_at_disconnect, d.reconnect_date,
               u.full_name as disconnected_by_user
        FROM customers c
        JOIN zones z ON c.zone_id = z.zone_id
        LEFT JOIN meters m ON c.customer_id = m.customer_id
        JOIN disconnections d ON c.customer_id = d.customer_id AND d.reconnect_date IS NULL
        LEFT JOIN users u ON d.disconnected_by = u.user_id
        WHERE c.status = 'Disconnected'
        ORDER BY d.disconnect_date DESC";
$disconnected = db()->fetchAll($sql);

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
  <div class="page-title-wrap">
    <h1><i class="fa-solid fa-user-slash" style="color:var(--danger);"></i> Disconnected Accounts & Reconnections</h1>
    <p>Monitor physically isolated accounts, track disconnection notices, and clear accounts for water supply restoration</p>
  </div>
  <div class="page-actions">
    <a href="<?= APP_URL ?>/modules/customers/defaulters.php" class="btn btn-secondary">
      <i class="fa-solid fa-triangle-exclamation"></i> Defaulters List
    </a>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <h3><i class="fa-solid fa-ban" style="color:var(--danger);"></i> Physically Disconnected Accounts</h3>
    <span class="badge badge-danger"><?= count($disconnected) ?> Accounts Isolated</span>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table">
        <thead>
          <tr>
            <th>Customer & Account</th>
            <th>Zone</th>
            <th>Disconnected Date</th>
            <th>Reason</th>
            <th>Disconnected By</th>
            <th style="text-align:right;">Outstanding Balance</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($disconnected)): ?>
            <tr>
              <td colspan="7" class="text-center py-4 text-muted">
                <i class="fa-solid fa-circle-check" style="font-size:2rem;color:var(--success);margin-bottom:0.5rem;display:block;"></i>
                No disconnected accounts currently on record.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($disconnected as $d): ?>
              <tr>
                <td>
                  <a href="<?= APP_URL ?>/modules/customers/view.php?id=<?= $d['customer_id'] ?>" style="font-weight:700;color:var(--navy);">
                    <?= htmlspecialchars($d['full_name']) ?>
                  </a>
                  <div style="font-size:0.8rem;color:var(--text-muted);"><i class="fa-solid fa-id-card"></i> <?= htmlspecialchars($d['account_no']) ?></div>
                </td>
                <td><span class="badge badge-light"><?= htmlspecialchars($d['zone_code']) ?></span></td>
                <td><?= formatDate($d['disconnect_date']) ?></td>
                <td><span class="badge badge-warning"><?= htmlspecialchars($d['reason']) ?></span></td>
                <td><small><?= htmlspecialchars($d['disconnected_by_user']) ?></small></td>
                <td style="text-align:right;font-weight:800;color:var(--danger);">
                  <?= formatCurrency($d['balance']) ?>
                </td>
                <td>
                  <div style="display:flex;gap:0.4rem;">
                    <a href="<?= APP_URL ?>/modules/payments/add.php?customer_id=<?= $d['customer_id'] ?>" class="btn btn-success btn-sm" title="Clear Bill">
                      <i class="fa-solid fa-money-bill-wave"></i> Pay
                    </a>
                    <button type="button" class="btn btn-primary btn-sm" onclick="openReconnectModal(<?= $d['disconnection_id'] ?>, '<?= htmlspecialchars($d['account_no']) ?>', '<?= htmlspecialchars($d['full_name']) ?>', <?= $d['balance'] ?>)">
                      <i class="fa-solid fa-link"></i> Reconnect
                    </button>
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

<!-- RECONNECTION MODAL -->
<div id="reconnectModal" class="modal-backdrop">
  <div class="modal" style="max-width: 480px;">
    <div class="modal-header">
      <h3 style="color:var(--success);"><i class="fa-solid fa-link"></i> Authorize Water Reconnection</h3>
      <button class="modal-close" onclick="closeModal('reconnectModal')">&times;</button>
    </div>
    <form method="POST" action="">
      <input type="hidden" name="action" value="reconnect_customer">
      <input type="hidden" name="disconnection_id" id="reconDiscId" value="">
      <div class="modal-body">
        <p>Authorize reconnection of supply for <strong id="reconCustName"></strong> (<span id="reconAccNo"></span>)?</p>
        <div id="reconBalWarning" class="alert alert-warning" style="margin-bottom:1rem;">
          Customer balance: <strong id="reconBalance"></strong>
        </div>

        <div class="form-group mb-3">
          <label class="form-label">Statutory Reconnection Fee (KES)</label>
          <input type="number" step="0.01" name="reconnect_fee" class="form-control" value="1000.00" required>
          <small class="text-muted">Standard WASREB approved reconnection fee</small>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('reconnectModal')">Cancel</button>
        <button type="submit" class="btn btn-success"><i class="fa-solid fa-link"></i> Restore Water Supply</button>
      </div>
    </form>
  </div>
</div>

<script>
function openReconnectModal(id, acc, name, bal) {
  document.getElementById('reconDiscId').value = id;
  document.getElementById('reconAccNo').textContent = acc;
  document.getElementById('reconCustName').textContent = name;
  document.getElementById('reconBalance').textContent = 'KES ' + parseFloat(bal).toLocaleString(undefined, {minimumFractionDigits: 2});
  openModal('reconnectModal');
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
