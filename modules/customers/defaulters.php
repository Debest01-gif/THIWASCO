<?php
/**
 * THIWASCO MIS - Defaulters & Debt Aging Management
 */
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';

Auth::requirePermission('customers');

$pageTitle = 'Defaulters & Arrears Register';
$activePage = 'customers_defaulters';
$activeSection = 'customers';

$zoneId = !empty($_GET['zone_id']) ? (int)$_GET['zone_id'] : '';
$minBalance = !empty($_GET['min_balance']) ? (float)$_GET['min_balance'] : 0;
$search = clean($_GET['search'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;

$zones = db()->fetchAll("SELECT * FROM zones WHERE is_active = 1 ORDER BY zone_name ASC");

// Handle 1-click Disconnection action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'disconnect_customer') {
    Auth::requirePermission('revenue');
    $custId = (int)$_POST['customer_id'];
    $reason = clean($_POST['reason'] ?? 'Non-Payment');
    $notes = clean($_POST['notes'] ?? 'Disconnected due to non-payment of arrears');

    try {
        $c = db()->fetch("SELECT c.*, m.meter_id FROM customers c LEFT JOIN meters m ON c.customer_id = m.customer_id AND m.status = 'Active' WHERE c.customer_id = ?", [$custId]);
        if ($c) {
            $pdo = Database::getInstance()->getConnection();
            $pdo->beginTransaction();

            $pdo->prepare("UPDATE customers SET status = 'Disconnected' WHERE customer_id = ?")->execute([$custId]);
            if ($c['meter_id']) {
                $pdo->prepare("INSERT INTO disconnections (customer_id, meter_id, disconnect_date, reason, arrears_at_disconnect, disconnected_by, notes) VALUES (?, ?, CURDATE(), ?, ?, ?, ?)")
                    ->execute([$custId, $c['meter_id'], $reason, $c['balance'], Auth::id(), $notes]);
            }
            $pdo->commit();
            Auth::logAction('Disconnected Customer', 'revenue', "Disconnected {$c['account_no']} with balance KES {$c['balance']}");
            setFlash('success', "Customer {$c['account_no']} successfully marked as Disconnected.");
        }
    } catch (Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
        setFlash('danger', 'Disconnection failed: ' . $e->getMessage());
    }
    header('Location: ' . APP_URL . '/modules/customers/defaulters.php');
    exit;
}

// Build query
$where = ["c.balance > 0", "c.status != 'Disconnected'"];
$params = [];

if ($zoneId) {
    $where[] = "c.zone_id = ?";
    $params[] = $zoneId;
}
if ($minBalance > 0) {
    $where[] = "c.balance >= ?";
    $params[] = $minBalance;
}
if ($search) {
    $where[] = "(c.account_no LIKE ? OR c.full_name LIKE ? OR c.phone LIKE ?)";
    $like = "%{$search}%";
    $params[] = $like; $params[] = $like; $params[] = $like;
}

$whereSql = implode(" AND ", $where);

// Count & stats
$statRow = db()->fetch("SELECT COUNT(*) as total_defaulters, COALESCE(SUM(c.balance), 0) as total_debt, COALESCE(AVG(c.balance), 0) as avg_debt FROM customers c WHERE {$whereSql}", $params);
$totalRows = (int)$statRow['total_defaulters'];

$totalPages = max(1, ceil($totalRows / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$sql = "SELECT c.*, z.zone_code, z.zone_name, m.meter_serial,
               (SELECT MAX(payment_date) FROM payments WHERE customer_id = c.customer_id) as last_payment_date
        FROM customers c
        JOIN zones z ON c.zone_id = z.zone_id
        LEFT JOIN meters m ON c.customer_id = m.customer_id AND m.status = 'Active'
        WHERE {$whereSql}
        ORDER BY c.balance DESC
        LIMIT {$perPage} OFFSET {$offset}";
$defaulters = db()->fetchAll($sql, $params);

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
  <div class="page-title-wrap">
    <h1><i class="fa-solid fa-triangle-exclamation" style="color:var(--danger);"></i> Defaulters & Arrears Management</h1>
    <p>Monitor overdue customer accounts, target debt recovery, dispatch demand notices and manage field disconnections</p>
  </div>
  <div class="page-actions">
    <a href="<?= APP_URL ?>/modules/reports/defaulters.php" class="btn btn-secondary">
      <i class="fa-solid fa-chart-pie"></i> Aging Debt Report
    </a>
    <a href="<?= APP_URL ?>/modules/customers/disconnected.php" class="btn btn-secondary">
      <i class="fa-solid fa-user-slash"></i> Disconnected Accounts
    </a>
  </div>
</div>

<!-- STATS -->
<div class="stats-grid mb-4">
  <div class="stat-card">
    <div class="stat-icon" style="background:rgba(239,68,68,0.1);color:var(--danger);"><i class="fa-solid fa-user-xmark"></i></div>
    <div class="stat-info">
      <span class="stat-value"><?= number_format($statRow['total_defaulters']) ?></span>
      <span class="stat-label">Active Defaulter Accounts</span>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:rgba(185,28,28,0.1);color:#b91c1c;"><i class="fa-solid fa-money-bill-transfer"></i></div>
    <div class="stat-info">
      <span class="stat-value"><?= formatCurrency($statRow['total_debt']) ?></span>
      <span class="stat-label">Total Outstanding Debt</span>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:rgba(234,179,8,0.1);color:var(--gold);"><i class="fa-solid fa-calculator"></i></div>
    <div class="stat-info">
      <span class="stat-value"><?= formatCurrency($statRow['avg_debt']) ?></span>
      <span class="stat-label">Average Balance / Defaulter</span>
    </div>
  </div>
</div>

<!-- FILTER -->
<div class="card mb-4">
  <div class="card-body" style="padding:1rem 1.25rem;">
    <form method="GET" action="" style="display:flex;flex-wrap:wrap;gap:1rem;align-items:flex-end;">
      <div style="flex:1;min-width:200px;">
        <label class="form-label" style="font-size:0.8rem;">Search Customer</label>
        <input type="text" name="search" class="form-control" placeholder="Acc #, Name, Phone..." value="<?= htmlspecialchars($search) ?>">
      </div>
      <div style="min-width:160px;">
        <label class="form-label" style="font-size:0.8rem;">Zone / DMA</label>
        <select name="zone_id" class="form-control">
          <option value="">All Zones</option>
          <?php foreach ($zones as $z): ?>
            <option value="<?= $z['zone_id'] ?>" <?= $zoneId == $z['zone_id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($z['zone_code'] . ' - ' . $z['zone_name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div style="min-width:140px;">
        <label class="form-label" style="font-size:0.8rem;">Min Balance (KES)</label>
        <input type="number" name="min_balance" class="form-control" placeholder="e.g. 1000" value="<?= $minBalance ?: '' ?>">
      </div>
      <div style="display:flex;gap:0.5rem;">
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-filter"></i> Filter</button>
        <a href="<?= APP_URL ?>/modules/customers/defaulters.php" class="btn btn-secondary"><i class="fa-solid fa-rotate-left"></i> Reset</a>
      </div>
    </form>
  </div>
</div>

<!-- DEFAULTERS TABLE -->
<div class="card">
  <div class="card-header">
    <h3><i class="fa-solid fa-list-ul" style="color:var(--royal-blue);"></i> Overdue Accounts Queue</h3>
    <span class="badge badge-danger"><?= number_format($totalRows) ?> Accounts</span>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table">
        <thead>
          <tr>
            <th>Customer & Account</th>
            <th>Zone</th>
            <th>Contact Phone</th>
            <th>Active Meter</th>
            <th>Last Payment</th>
            <th style="text-align:right;">Outstanding Balance (KES)</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($defaulters)): ?>
            <tr>
              <td colspan="7" class="text-center py-4 text-muted">
                <i class="fa-solid fa-circle-check" style="font-size:2rem;color:var(--success);margin-bottom:0.5rem;display:block;"></i>
                No defaulting accounts found matching your filter criteria.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($defaulters as $d): ?>
              <tr>
                <td>
                  <a href="<?= APP_URL ?>/modules/customers/view.php?id=<?= $d['customer_id'] ?>" style="font-weight:700;color:var(--navy);">
                    <?= htmlspecialchars($d['full_name']) ?>
                  </a>
                  <div style="font-size:0.8rem;color:var(--text-muted);"><i class="fa-solid fa-id-card"></i> <?= htmlspecialchars($d['account_no']) ?></div>
                </td>
                <td><span class="badge badge-light"><?= htmlspecialchars($d['zone_code']) ?></span></td>
                <td><a href="tel:<?= $d['phone'] ?>"><i class="fa-solid fa-phone"></i> <?= htmlspecialchars($d['phone']) ?></a></td>
                <td><?= htmlspecialchars($d['meter_serial'] ?? 'None') ?></td>
                <td><?= formatDate($d['last_payment_date']) ?></td>
                <td style="text-align:right;font-weight:800;font-size:1.1rem;color:var(--danger);">
                  <?= number_format($d['balance'], 2) ?>
                </td>
                <td>
                  <div style="display:flex;gap:0.4rem;">
                    <a href="<?= APP_URL ?>/modules/payments/add.php?customer_id=<?= $d['customer_id'] ?>" class="btn btn-success btn-sm" title="Post Payment">
                      <i class="fa-solid fa-money-bill-wave"></i> Pay
                    </a>
                    <button type="button" class="btn btn-danger btn-sm" onclick="openDisconnectModal(<?= $d['customer_id'] ?>, '<?= htmlspecialchars($d['account_no']) ?>', '<?= htmlspecialchars($d['full_name']) ?>', <?= $d['balance'] ?>)" title="Issue Disconnection">
                      <i class="fa-solid fa-scissors"></i> Disconnect
                    </button>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- PAGINATION -->
    <?php if ($totalPages > 1): ?>
      <div class="pagination-wrap" style="padding:1rem;display:flex;justify-content:space-between;align-items:center;">
        <span class="text-muted" style="font-size:0.85rem;">Page <?= $page ?> of <?= $totalPages ?> (Total: <?= number_format($totalRows) ?>)</span>
        <div class="pagination">
          <?php if ($page > 1): ?>
            <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&zone_id=<?= $zoneId ?>&min_balance=<?= $minBalance ?>" class="page-link">&laquo; Prev</a>
          <?php endif; ?>
          <?php for ($p = max(1, $page - 2); $p <= min($totalPages, $page + 2); $p++): ?>
            <a href="?page=<?= $p ?>&search=<?= urlencode($search) ?>&zone_id=<?= $zoneId ?>&min_balance=<?= $minBalance ?>" class="page-link <?= $p == $page ? 'active' : '' ?>"><?= $p ?></a>
          <?php endfor; ?>
          <?php if ($page < $totalPages): ?>
            <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&zone_id=<?= $zoneId ?>&min_balance=<?= $minBalance ?>" class="page-link">Next &raquo;</a>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- DISCONNECTION MODAL -->
<div id="disconnectModal" class="modal-backdrop">
  <div class="modal" style="max-width: 480px;">
    <div class="modal-header">
      <h3 style="color:var(--danger);"><i class="fa-solid fa-scissors"></i> Order Water Disconnection</h3>
      <button class="modal-close" onclick="closeModal('disconnectModal')">&times;</button>
    </div>
    <form method="POST" action="">
      <input type="hidden" name="action" value="disconnect_customer">
      <input type="hidden" name="customer_id" id="discCustId" value="">
      <div class="modal-body">
        <p>Confirm disconnection of supply for <strong id="discCustName"></strong> (<span id="discAccNo"></span>)?</p>
        <div class="alert alert-danger" style="margin-bottom:1rem;">
          Outstanding Balance: <strong id="discBalance"></strong>
        </div>

        <div class="form-group mb-3">
          <label class="form-label">Disconnection Reason</label>
          <select name="reason" class="form-control" required>
            <option value="Non-Payment">Non-Payment of Water Bills</option>
            <option value="Tampering">Meter Tampering / Fraud</option>
            <option value="Illegal Connection">Unauthorized Branching</option>
            <option value="Application">Customer Voluntary Request</option>
          </select>
        </div>

        <div class="form-group mb-3">
          <label class="form-label">Field Execution Notes</label>
          <textarea name="notes" class="form-control" rows="2" placeholder="Instructions for field plumber..."></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('disconnectModal')">Cancel</button>
        <button type="submit" class="btn btn-danger"><i class="fa-solid fa-scissors"></i> Confirm Disconnection</button>
      </div>
    </form>
  </div>
</div>

<script>
function openDisconnectModal(id, acc, name, bal) {
  document.getElementById('discCustId').value = id;
  document.getElementById('discAccNo').textContent = acc;
  document.getElementById('discCustName').textContent = name;
  document.getElementById('discBalance').textContent = 'KES ' + parseFloat(bal).toLocaleString(undefined, {minimumFractionDigits: 2});
  openModal('disconnectModal');
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
