<?php
$pageTitle = 'Customer Management';
$activePage = 'customers_list';
$activeSection = 'customers';

require_once __DIR__ . '/../../includes/header.php';

// ─── Handle Actions ────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'delete_customer') {
        $delId = (int)($_POST['customer_id'] ?? 0);
        $cascade = isset($_POST['cascade_delete']) && $_POST['cascade_delete'] == '1';
        
        $cust = db()->fetch("SELECT * FROM customers WHERE customer_id = ?", [$delId]);
        if ($cust) {
            try {
                $pdo = Database::getInstance()->getConnection();
                $pdo->beginTransaction();

                if ($cascade) {
                    // Delete in proper dependency order
                    $pdo->prepare("DELETE FROM payment_allocations WHERE invoice_id IN (SELECT invoice_id FROM invoices WHERE customer_id = ?)")->execute([$delId]);
                    $pdo->prepare("DELETE FROM payment_allocations WHERE payment_id IN (SELECT payment_id FROM payments WHERE customer_id = ?)")->execute([$delId]);
                    $pdo->prepare("DELETE FROM payments WHERE customer_id = ?")->execute([$delId]);
                    $pdo->prepare("DELETE FROM invoices WHERE customer_id = ?")->execute([$delId]);
                    $pdo->prepare("DELETE FROM meter_readings WHERE customer_id = ?")->execute([$delId]);
                    $pdo->prepare("DELETE FROM disconnections WHERE customer_id = ?")->execute([$delId]);
                    $pdo->prepare("DELETE FROM field_inspections WHERE customer_id = ?")->execute([$delId]);
                    $pdo->prepare("DELETE FROM meters WHERE customer_id = ?")->execute([$delId]);
                    $pdo->prepare("DELETE FROM customers WHERE customer_id = ?")->execute([$delId]);
                } else {
                    // Check if foreign keys exist
                    $hasInvoices = db()->fetch("SELECT COUNT(*) as c FROM invoices WHERE customer_id = ?", [$delId])['c'] ?? 0;
                    $hasPayments = db()->fetch("SELECT COUNT(*) as c FROM payments WHERE customer_id = ?", [$delId])['c'] ?? 0;
                    $hasMeters = db()->fetch("SELECT COUNT(*) as c FROM meters WHERE customer_id = ?", [$delId])['c'] ?? 0;

                    if ($hasInvoices > 0 || $hasPayments > 0 || $hasMeters > 0) {
                        throw new Exception("Cannot delete customer with active records ({$hasInvoices} invoices, {$hasPayments} payments, {$hasMeters} meters). Please select 'Cascade Delete' in the confirmation prompt if you wish to remove all associated records.");
                    }

                    $pdo->prepare("DELETE FROM disconnections WHERE customer_id = ?")->execute([$delId]);
                    $pdo->prepare("DELETE FROM field_inspections WHERE customer_id = ?")->execute([$delId]);
                    $pdo->prepare("DELETE FROM customers WHERE customer_id = ?")->execute([$delId]);
                }

                $pdo->commit();
                Auth::logAction('Deleted Customer', 'customers', "Deleted customer {$cust['account_no']} - {$cust['full_name']}");
                setFlash('success', "Customer <strong>{$cust['account_no']} ({$cust['full_name']})</strong> was permanently deleted.");
            } catch (Exception $e) {
                if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
                setFlash('danger', 'Delete failed: ' . $e->getMessage());
            }
        }
        header('Location: ' . APP_URL . '/modules/customers/index.php');
        exit;
    }

    if ($_POST['action'] === 'update_status') {
        $cId = (int)$_POST['customer_id'];
        $newStatus = clean($_POST['new_status'] ?? 'Active');
        db()->query("UPDATE customers SET status = ? WHERE customer_id = ?", [$newStatus, $cId]);
        Auth::logAction('Updated Customer Status', 'customers', "Changed status of customer ID {$cId} to {$newStatus}");
        setFlash('success', "Customer status updated to <strong>{$newStatus}</strong>.");
        header('Location: ' . APP_URL . '/modules/customers/index.php');
        exit;
    }
}

// ─── Filters & Pagination ─────────────────────────────────────────────────
$search  = clean($_GET['search'] ?? '');
$zone    = (int)($_GET['zone'] ?? 0);
$status  = clean($_GET['status'] ?? '');
$tariff  = (int)($_GET['tariff'] ?? 0);
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;

$where = ['1=1'];
$params = [];

if ($search) {
    $where[] = "(c.account_no LIKE ? OR c.full_name LIKE ? OR c.phone LIKE ? OR c.id_number LIKE ?)";
    $s = "%$search%";
    $params = array_merge($params, [$s, $s, $s, $s]);
}
if ($zone) { $where[] = "c.zone_id = ?"; $params[] = $zone; }
if ($status) { $where[] = "c.status = ?"; $params[] = $status; }
if ($tariff) { $where[] = "c.tariff_id = ?"; $params[] = $tariff; }

$whereSQL = implode(' AND ', $where);

$total = db()->fetch("SELECT COUNT(*) as cnt FROM customers c WHERE $whereSQL", $params)['cnt'] ?? 0;
$pg    = paginate($total, $page, $perPage);

$customers = db()->fetchAll(
    "SELECT c.*, z.zone_name, t.tariff_name, t.tariff_type
     FROM customers c
     JOIN zones z ON c.zone_id = z.zone_id
     JOIN tariff_categories t ON c.tariff_id = t.tariff_id
     WHERE $whereSQL
     ORDER BY c.created_at DESC
     LIMIT {$pg['perPage']} OFFSET {$pg['offset']}",
    $params
);

$zones   = db()->fetchAll("SELECT zone_id, zone_name FROM zones WHERE is_active=1 ORDER BY zone_name");
$tariffs = db()->fetchAll("SELECT tariff_id, tariff_name FROM tariff_categories WHERE is_active=1");

$statusColors = [
    'Active'       => 'success',
    'Inactive'     => 'gray',
    'Disconnected' => 'danger',
    'Suspended'    => 'warning',
    'Defaulter'    => 'danger',
];
?>

<div class="page-header">
  <div class="page-header-left">
    <h2>Customer Management</h2>
    <p>Manage water service connections and customer accounts</p>
  </div>
  <div class="page-header-actions">
    <a href="<?= APP_URL ?>/modules/customers/add.php" class="btn btn-primary">
      <i class="fa-solid fa-user-plus"></i> Add Customer
    </a>
    <button class="btn btn-outline" onclick="window.print()">
      <i class="fa-solid fa-print"></i> Print
    </button>
    <a href="?<?= http_build_query(array_merge($_GET, ['export'=>'csv'])) ?>" class="btn btn-outline">
      <i class="fa-solid fa-download"></i> Export CSV
    </a>
  </div>
</div>

<!-- QUICK STATS -->
<div class="stats-grid" style="margin-bottom:20px;">
  <?php
  $qStats = [
    ['label'=>'Total','key'=>'','status'=>'','icon'=>'fa-users','class'=>'blue'],
    ['label'=>'Active','key'=>'Active','icon'=>'fa-circle-check','class'=>'green'],
    ['label'=>'Disconnected','key'=>'Disconnected','icon'=>'fa-link-slash','class'=>'red'],
    ['label'=>'Defaulters','key'=>'Defaulter','icon'=>'fa-triangle-exclamation','class'=>'gold'],
  ];
  foreach ($qStats as $qs):
    $cnt = $qs['status'] ?? '';
    $n = db()->fetch("SELECT COUNT(*) as c FROM customers" . ($cnt ? " WHERE status=?" : ""), $cnt ? [$cnt] : [])['c'] ?? 0;
  ?>
  <div class="stat-card <?= $qs['class'] ?>" style="padding:14px 18px;">
    <div class="stat-card-icon" style="width:36px;height:36px;font-size:0.9rem;margin-bottom:10px;">
      <i class="fa-solid <?= $qs['icon'] ?>"></i>
    </div>
    <div class="stat-card-label"><?= $qs['label'] ?></div>
    <div class="stat-card-value" style="font-size:1.4rem;"><?= number_format($n) ?></div>
  </div>
  <?php endforeach; ?>
</div>

<!-- FILTER BAR -->
<div class="card">
  <div class="card-body" style="padding:14px 16px;">
    <form method="GET" id="filterForm">
      <div class="filter-bar">
        <div class="search-box" style="flex:2;">
          <span class="search-icon"><i class="fa-solid fa-search"></i></span>
          <input type="text" name="search" id="searchInput" placeholder="Search by account, name, phone, ID..."
                 value="<?= htmlspecialchars($search) ?>" onchange="this.form.submit()">
        </div>
        <select name="zone" class="filter-select" onchange="this.form.submit()">
          <option value="">All Zones</option>
          <?php foreach ($zones as $z): ?>
          <option value="<?= $z['zone_id'] ?>" <?= $zone == $z['zone_id'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($z['zone_name']) ?>
          </option>
          <?php endforeach; ?>
        </select>
        <select name="status" class="filter-select" onchange="this.form.submit()">
          <option value="">All Status</option>
          <?php foreach (['Active','Inactive','Disconnected','Suspended','Defaulter'] as $s): ?>
          <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= $s ?></option>
          <?php endforeach; ?>
        </select>
        <select name="tariff" class="filter-select" onchange="this.form.submit()">
          <option value="">All Tariffs</option>
          <?php foreach ($tariffs as $t): ?>
          <option value="<?= $t['tariff_id'] ?>" <?= $tariff == $t['tariff_id'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($t['tariff_name']) ?>
          </option>
          <?php endforeach; ?>
        </select>
        <?php if ($search || $zone || $status || $tariff): ?>
        <a href="?" class="btn btn-outline btn-sm"><i class="fa-solid fa-times"></i> Clear</a>
        <?php endif; ?>
      </div>
    </form>
  </div>

  <!-- TABLE -->
  <div class="table-wrapper">
    <?php if ($customers): ?>
    <table class="data-table" id="customersTable">
      <thead>
        <tr>
          <th>Account No.</th>
          <th>Customer Name</th>
          <th>Phone</th>
          <th>Zone</th>
          <th>Tariff</th>
          <th>Status</th>
          <th style="text-align:right;">Balance (KES)</th>
          <th>Since</th>
          <th style="text-align:center;">Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($customers as $c): ?>
        <tr>
          <td>
            <a href="<?= APP_URL ?>/modules/customers/view.php?id=<?= $c['customer_id'] ?>"
               style="color:var(--primary-600);font-weight:700;">
              <?= htmlspecialchars($c['account_no']) ?>
            </a>
          </td>
          <td>
            <div style="font-weight:600;"><?= htmlspecialchars($c['full_name']) ?></div>
            <div style="font-size:0.75rem;color:var(--gray-500);"><?= htmlspecialchars($c['plot_no'] ?? $c['physical_address'] ?? '') ?></div>
          </td>
          <td style="font-size:0.85rem;"><?= htmlspecialchars($c['phone']) ?></td>
          <td style="font-size:0.82rem;"><?= htmlspecialchars($c['zone_name']) ?></td>
          <td>
            <span class="badge badge-primary" style="font-size:0.7rem;">
              <?= htmlspecialchars($c['tariff_type']) ?>
            </span>
          </td>
          <td>
            <span class="badge badge-<?= $statusColors[$c['status']] ?? 'gray' ?>">
              <?= htmlspecialchars($c['status']) ?>
            </span>
          </td>
          <td style="text-align:right;font-weight:600;color:<?= $c['balance'] > 0 ? 'var(--danger-600)' : 'var(--success-600)' ?>;">
            <?= formatCurrency($c['balance']) ?>
          </td>
          <td style="font-size:0.8rem;"><?= formatDate($c['connection_date']) ?></td>
          <td style="text-align:center;white-space:nowrap;">
            <a href="<?= APP_URL ?>/modules/customers/view.php?id=<?= $c['customer_id'] ?>"
               class="table-action-btn view" title="View Profile" data-tooltip="View">
              <i class="fa-solid fa-eye"></i>
            </a>
            <a href="<?= APP_URL ?>/modules/customers/edit.php?id=<?= $c['customer_id'] ?>"
               class="table-action-btn edit" title="Edit Customer" data-tooltip="Edit">
              <i class="fa-solid fa-pen-to-square"></i>
            </a>
            <a href="<?= APP_URL ?>/modules/billing/invoice_view.php?customer_id=<?= $c['customer_id'] ?>"
               class="table-action-btn view" title="Invoices" data-tooltip="Invoices"
               style="background:var(--gold-100);color:var(--gold-700);">
              <i class="fa-solid fa-file-invoice"></i>
            </a>
            <a href="<?= APP_URL ?>/modules/payments/add.php?customer_id=<?= $c['customer_id'] ?>"
               class="table-action-btn" title="Post Payment" data-tooltip="Post Payment"
               style="background:var(--success-100);color:var(--success-600);">
              <i class="fa-solid fa-money-bill"></i>
            </a>
            <button type="button" class="table-action-btn delete" title="Delete Customer" data-tooltip="Delete"
                    onclick="promptDeleteCustomer(<?= $c['customer_id'] ?>, '<?= addslashes($c['account_no']) ?>', '<?= addslashes($c['full_name']) ?>')">
              <i class="fa-solid fa-trash-can"></i>
            </button>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php else: ?>
    <div class="empty-state">
      <div class="icon"><i class="fa-solid fa-users"></i></div>
      <h3>No Customers Found</h3>
      <p>No customers match your search criteria. Try adjusting the filters or add a new customer.</p>
      <a href="<?= APP_URL ?>/modules/customers/add.php" class="btn btn-primary">
        <i class="fa-solid fa-user-plus"></i> Add First Customer
      </a>
    </div>
    <?php endif; ?>
  </div>

  <!-- PAGINATION -->
  <?php if ($pg['totalPages'] > 1): ?>
  <div class="card-footer">
    <div style="display:flex;align-items:center;justify-content:space-between;">
      <span style="font-size:0.82rem;color:var(--gray-500);">
        Showing <?= number_format($pg['offset'] + 1) ?>–<?= number_format(min($pg['offset'] + $pg['perPage'], $total)) ?>
        of <?= number_format($total) ?> customers
      </span>
      <div class="pagination">
        <?php if ($pg['hasPrev']): ?>
        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $pg['page'] - 1])) ?>" class="pagination-btn">
          <i class="fa-solid fa-chevron-left"></i>
        </a>
        <?php endif; ?>

        <?php for ($i = max(1, $pg['page']-2); $i <= min($pg['totalPages'], $pg['page']+2); $i++): ?>
        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"
           class="pagination-btn <?= $i === $pg['page'] ? 'active' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>

        <?php if ($pg['hasNext']): ?>
        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $pg['page'] + 1])) ?>" class="pagination-btn">
          <i class="fa-solid fa-chevron-right"></i>
        </a>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <?php endif; ?>

</div><!-- /.card -->

<!-- DELETE CUSTOMER CONFIRMATION MODAL -->
<div id="deleteCustomerModal" class="modal-overlay">
  <div class="modal" style="max-width:480px;">
    <div class="modal-header">
      <div class="modal-title" style="color:var(--danger-600);"><i class="fa-solid fa-triangle-exclamation"></i> Delete Customer Account</div>
      <button class="modal-close" onclick="closeModal('deleteCustomerModal')">&times;</button>
    </div>
    <form method="POST" action="">
      <input type="hidden" name="action" value="delete_customer">
      <input type="hidden" name="customer_id" id="deleteCustomerId" value="">
      <div class="modal-body">
        <p>Are you sure you want to delete customer account <strong id="deleteCustomerName"></strong>?</p>
        <div style="background:var(--danger-50);border:1px solid var(--danger-100);border-radius:8px;padding:12px;margin:12px 0;">
          <label style="display:flex;align-items:flex-start;gap:8px;font-size:0.85rem;color:var(--danger-700);cursor:pointer;">
            <input type="checkbox" name="cascade_delete" value="1" style="margin-top:3px;">
            <span><strong>Cascade Delete:</strong> Also remove all attached water meters, readings, invoices, payments, and inspections for this customer.</span>
          </label>
        </div>
        <p style="font-size:0.8rem;color:var(--gray-500);margin:0;">This action cannot be undone.</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('deleteCustomerModal')">Cancel</button>
        <button type="submit" class="btn btn-danger"><i class="fa-solid fa-trash-can"></i> Confirm Delete</button>
      </div>
    </form>
  </div>
</div>

<script>
function promptDeleteCustomer(id, acc, name) {
  document.getElementById('deleteCustomerId').value = id;
  document.getElementById('deleteCustomerName').textContent = acc + ' - ' + name;
  openModal('deleteCustomerModal');
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

