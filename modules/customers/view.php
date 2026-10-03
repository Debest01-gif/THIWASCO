<?php
/**
 * THIWASCO MIS - Customer 360 Account Statement & Profile
 */
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';

Auth::requirePermission('customers');

$customerId = (int)($_GET['id'] ?? 0);
if ($customerId <= 0) {
    header('Location: ' . APP_URL . '/modules/customers/index.php');
    exit;
}

// Handle POST actions on customer view
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'delete_customer') {
        $cascade = isset($_POST['cascade_delete']) && $_POST['cascade_delete'] == '1';
        try {
            $pdo = Database::getInstance()->getConnection();
            $pdo->beginTransaction();

            if ($cascade) {
                $pdo->prepare("DELETE FROM payment_allocations WHERE invoice_id IN (SELECT invoice_id FROM invoices WHERE customer_id = ?)")->execute([$customerId]);
                $pdo->prepare("DELETE FROM payment_allocations WHERE payment_id IN (SELECT payment_id FROM payments WHERE customer_id = ?)")->execute([$customerId]);
                $pdo->prepare("DELETE FROM payments WHERE customer_id = ?")->execute([$customerId]);
                $pdo->prepare("DELETE FROM invoices WHERE customer_id = ?")->execute([$customerId]);
                $pdo->prepare("DELETE FROM meter_readings WHERE customer_id = ?")->execute([$customerId]);
                $pdo->prepare("DELETE FROM disconnections WHERE customer_id = ?")->execute([$customerId]);
                $pdo->prepare("DELETE FROM field_inspections WHERE customer_id = ?")->execute([$customerId]);
                $pdo->prepare("DELETE FROM meters WHERE customer_id = ?")->execute([$customerId]);
                $pdo->prepare("DELETE FROM customers WHERE customer_id = ?")->execute([$customerId]);
            } else {
                $pdo->prepare("DELETE FROM disconnections WHERE customer_id = ?")->execute([$customerId]);
                $pdo->prepare("DELETE FROM field_inspections WHERE customer_id = ?")->execute([$customerId]);
                $pdo->prepare("DELETE FROM customers WHERE customer_id = ?")->execute([$customerId]);
            }

            $pdo->commit();
            Auth::logAction('Deleted Customer', 'customers', "Deleted customer ID {$customerId}");
            setFlash('success', "Customer account permanently deleted.");
            header('Location: ' . APP_URL . '/modules/customers/index.php');
            exit;
        } catch (Exception $e) {
            if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
            setFlash('danger', 'Delete failed: ' . $e->getMessage());
        }
    }
}

// Fetch customer
$custSql = "SELECT c.*, z.zone_code, z.zone_name, t.tariff_code, t.tariff_name, t.rate_per_m3, t.sewer_percent
            FROM customers c
            JOIN zones z ON c.zone_id = z.zone_id
            JOIN tariff_categories t ON c.tariff_id = t.tariff_id
            WHERE c.customer_id = ?";
$customer = db()->fetch($custSql, [$customerId]);

if (!$customer) {
    setFlash('danger', 'Customer account not found.');
    header('Location: ' . APP_URL . '/modules/customers/index.php');
    exit;
}

// Active and historical meters
$meters = db()->fetchAll("SELECT * FROM meters WHERE customer_id = ? ORDER BY meter_id DESC", [$customerId]);
$activeMeter = null;
foreach ($meters as $m) {
    if ($m['status'] === 'Active') {
        $activeMeter = $m;
        break;
    }
}

// Invoices
$invoices = db()->fetchAll("SELECT * FROM invoices WHERE customer_id = ? ORDER BY invoice_date DESC, invoice_id DESC", [$customerId]);

// Payments
$payments = db()->fetchAll("SELECT p.*, pm.method_name, u.full_name as receiver_name 
                            FROM payments p 
                            JOIN payment_methods pm ON p.payment_method_id = pm.method_id 
                            JOIN users u ON p.received_by = u.user_id 
                            WHERE p.customer_id = ? 
                            ORDER BY p.payment_date DESC, p.payment_id DESC", [$customerId]);

// Recent readings
$readings = db()->fetchAll("SELECT r.*, u.full_name as reader_name 
                            FROM meter_readings r 
                            JOIN users u ON r.read_by = u.user_id 
                            WHERE r.customer_id = ? 
                            ORDER BY r.reading_date DESC LIMIT 12", [$customerId]);

// Inspections
$inspections = db()->fetchAll("SELECT i.*, it.type_name, u.full_name as inspector_name 
                               FROM field_inspections i 
                               JOIN inspection_types it ON i.inspection_type_id = it.type_id 
                               JOIN users u ON i.inspected_by = u.user_id 
                               WHERE i.customer_id = ? 
                               ORDER BY i.inspection_date DESC", [$customerId]);

// Disconnections
$disconnections = db()->fetchAll("SELECT d.*, u1.full_name as disconnector, u2.full_name as reconnecter 
                                  FROM disconnections d 
                                  JOIN users u1 ON d.disconnected_by = u1.user_id 
                                  LEFT JOIN users u2 ON d.reconnected_by = u2.user_id 
                                  WHERE d.customer_id = ? 
                                  ORDER BY d.disconnect_date DESC", [$customerId]);

$pageTitle = 'Account: ' . $customer['account_no'];
$activePage = 'customers_list';
$activeSection = 'customers';

include __DIR__ . '/../../includes/header.php';
?>

<!-- CUSTOMER PROFILE HEADER -->
<div class="card mb-4" style="background:linear-gradient(135deg, var(--navy) 0%, var(--royal-blue) 100%); color:#fff; border-radius:12px; overflow:hidden;">
  <div class="card-body" style="padding:1.75rem 2rem;">
    <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:1.5rem;">
      
      <div style="display:flex; align-items:center; gap:1.25rem;">
        <div style="width:70px; height:70px; border-radius:50%; background:rgba(255,255,255,0.15); border:3px solid var(--gold); display:flex; align-items:center; justify-content:center; font-size:2rem; color:var(--gold); font-weight:800;">
          <?= strtoupper(substr($customer['full_name'], 0, 1)) ?>
        </div>
        <div>
          <div style="display:flex; align-items:center; gap:0.75rem; flex-wrap:wrap;">
            <h2 style="margin:0; font-size:1.6rem; color:#fff; font-weight:800;"><?= htmlspecialchars($customer['full_name']) ?></h2>
            <?php 
            $statusClass = match($customer['status']) {
              'Active' => 'badge-success',
              'Disconnected' => 'badge-danger',
              'Defaulter' => 'badge-warning',
              default => 'badge-light'
            };
            ?>
            <span class="badge <?= $statusClass ?>" style="font-size:0.85rem; padding:0.35rem 0.75rem;">
              <?= htmlspecialchars($customer['status']) ?>
            </span>
          </div>
          <div style="color:rgba(255,255,255,0.85); font-size:0.9rem; margin-top:4px;">
            <i class="fa-solid fa-id-card" style="color:var(--gold);"></i> <strong><?= htmlspecialchars($customer['account_no']) ?></strong> | 
            <i class="fa-solid fa-phone"></i> <?= htmlspecialchars($customer['phone']) ?> | 
            <i class="fa-solid fa-location-dot"></i> <?= htmlspecialchars($customer['zone_name']) ?> (<?= htmlspecialchars($customer['zone_code']) ?>) |
            <i class="fa-solid fa-tags"></i> <?= htmlspecialchars($customer['tariff_name']) ?>
          </div>
        </div>
      </div>

      <!-- BALANCE CALLOUT -->
      <div style="background:rgba(255,255,255,0.1); border:1px solid rgba(255,255,255,0.2); padding:1rem 1.5rem; border-radius:8px; text-align:right;">
        <div style="font-size:0.75rem; text-transform:uppercase; letter-spacing:1px; color:var(--gold); font-weight:700;">Outstanding Balance</div>
        <div style="font-size:1.8rem; font-weight:800; color:<?= $customer['balance'] > 0 ? '#fca5a5' : '#86efac' ?>;">
          <?= formatCurrency($customer['balance']) ?>
        </div>
        <div style="font-size:0.75rem; opacity:0.85;">Deposit: <?= formatCurrency($customer['deposit_paid']) ?></div>
      </div>

    </div>

    <!-- QUICK ACTION BUTTONS -->
    <div style="margin-top:1.5rem; padding-top:1rem; border-top:1px solid rgba(255,255,255,0.15); display:flex; gap:0.75rem; flex-wrap:wrap; align-items:center;">
      <a href="<?= APP_URL ?>/modules/payments/add.php?customer_id=<?= $customer['customer_id'] ?>" class="btn btn-success" style="padding:0.5rem 1rem;">
        <i class="fa-solid fa-money-bill-wave"></i> Post Payment
      </a>
      <a href="<?= APP_URL ?>/modules/meters/readings.php?customer_id=<?= $customer['customer_id'] ?>" class="btn btn-primary" style="padding:0.5rem 1rem;">
        <i class="fa-solid fa-pencil"></i> Enter Reading
      </a>
      <a href="<?= APP_URL ?>/modules/revenue_protection/inspections.php?customer_id=<?= $customer['customer_id'] ?>" class="btn btn-warning" style="padding:0.5rem 1rem;">
        <i class="fa-solid fa-shield-halved"></i> Log Inspection
      </a>
      <a href="<?= APP_URL ?>/modules/customers/edit.php?id=<?= $customer['customer_id'] ?>" class="btn btn-secondary" style="padding:0.5rem 1rem;">
        <i class="fa-solid fa-user-pen"></i> Edit Profile
      </a>
      <button type="button" class="btn btn-danger" style="padding:0.5rem 1rem; margin-left:auto;" onclick="openModal('deleteCustomerModal')">
        <i class="fa-solid fa-trash-can"></i> Delete Account
      </button>
    </div>

  </div>
</div>

<!-- ACCOUNT DETAILS & METER SUMMARY -->
<div class="row mb-4" style="display:grid; grid-template-columns: 1fr 1fr; gap:1.5rem;">
  <!-- CUSTOMER PARTICULARS -->
  <div class="card">
    <div class="card-header">
      <h3><i class="fa-solid fa-circle-info" style="color:var(--royal-blue);"></i> Premises & Connection Info</h3>
    </div>
    <div class="card-body" style="font-size:0.9rem; line-height:1.7;">
      <div><strong>Customer Type:</strong> <?= htmlspecialchars($customer['customer_type']) ?></div>
      <div><strong>National ID / Reg No:</strong> <?= htmlspecialchars($customer['id_number'] ?? '-') ?></div>
      <div><strong>Email:</strong> <?= htmlspecialchars($customer['email'] ?? '-') ?></div>
      <div><strong>Plot Number:</strong> <?= htmlspecialchars($customer['plot_no'] ?? '-') ?></div>
      <div><strong>Road / Street:</strong> <?= htmlspecialchars($customer['road_street'] ?? '-') ?></div>
      <div><strong>Physical Address:</strong> <?= htmlspecialchars($customer['physical_address'] ?? '-') ?></div>
      <div><strong>Connected Since:</strong> <?= formatDate($customer['connection_date']) ?></div>
      <div><strong>Connection Size:</strong> <?= htmlspecialchars($customer['connection_size'] ?? '-') ?></div>
      <?php if ($customer['gps_lat'] && $customer['gps_lng']): ?>
        <div><strong>GPS Coordinates:</strong> <code><?= $customer['gps_lat'] ?>, <?= $customer['gps_lng'] ?></code></div>
      <?php endif; ?>
    </div>
  </div>

  <!-- ACTIVE METER -->
  <div class="card">
    <div class="card-header">
      <h3><i class="fa-solid fa-gauge" style="color:var(--gold);"></i> Assigned Water Meter</h3>
      <?php if ($activeMeter): ?>
        <span class="badge badge-success">Active Meter</span>
      <?php else: ?>
        <span class="badge badge-danger">No Active Meter</span>
      <?php endif; ?>
    </div>
    <div class="card-body" style="font-size:0.9rem; line-height:1.7;">
      <?php if ($activeMeter): ?>
        <div><strong>Meter Serial:</strong> <code style="font-size:1.1rem; font-weight:700; color:var(--navy);"><?= htmlspecialchars($activeMeter['meter_serial']) ?></code></div>
        <div><strong>Make & Model:</strong> <?= htmlspecialchars($activeMeter['meter_make'] ?? '-') ?> <?= htmlspecialchars($activeMeter['meter_model'] ?? '') ?></div>
        <div><strong>Size & Type:</strong> <?= htmlspecialchars($activeMeter['meter_size']) ?> (<?= htmlspecialchars($activeMeter['meter_type']) ?>)</div>
        <div><strong>Installed On:</strong> <?= formatDate($activeMeter['installation_date']) ?></div>
        <div><strong>Seal Number:</strong> <?= htmlspecialchars($activeMeter['seal_no'] ?? 'None') ?></div>
        <div><strong>Current Reading:</strong> <strong style="color:var(--royal-blue); font-size:1.1rem;"><?= number_format($activeMeter['current_reading'], 1) ?> m³</strong></div>
        <div><strong>Last Read Date:</strong> <?= formatDate($activeMeter['last_read_date']) ?></div>
      <?php else: ?>
        <p class="text-muted">This customer does not have an active water meter assigned.</p>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- TABS SECTION -->
<div class="card">
  <div class="card-header" style="padding-bottom:0; border-bottom:none;">
    <div class="tabs" style="margin-bottom:0;">
      <button class="tab-btn active" onclick="openTab(event, 'tabInvoices')"><i class="fa-solid fa-file-invoice"></i> Invoices & Bills (<?= count($invoices) ?>)</button>
      <button class="tab-btn" onclick="openTab(event, 'tabPayments')"><i class="fa-solid fa-receipt"></i> Receipts & Payments (<?= count($payments) ?>)</button>
      <button class="tab-btn" onclick="openTab(event, 'tabReadings')"><i class="fa-solid fa-chart-line"></i> Meter Readings (<?= count($readings) ?>)</button>
      <button class="tab-btn" onclick="openTab(event, 'tabInspections')"><i class="fa-solid fa-shield-halved"></i> Inspections (<?= count($inspections) ?>)</button>
    </div>
  </div>
  <div class="card-body p-0">
    
    <!-- INVOICES TAB -->
    <div id="tabInvoices" class="tab-content" style="display:block;">
      <div class="table-responsive">
        <table class="table">
          <thead>
            <tr>
              <th>Invoice #</th>
              <th>Month</th>
              <th>Date</th>
              <th>Due Date</th>
              <th>Consumption (m³)</th>
              <th>Total Bill</th>
              <th>Payable</th>
              <th>Paid</th>
              <th>Status</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($invoices)): ?>
              <tr><td colspan="10" class="text-center py-4 text-muted">No invoices generated yet for this customer.</td></tr>
            <?php else: ?>
              <?php foreach ($invoices as $inv): 
                $badge = match($inv['status']) {
                  'Paid' => 'badge-success',
                  'Partial' => 'badge-warning',
                  'Unpaid' => 'badge-danger',
                  default => 'badge-light'
                };
              ?>
                <tr>
                  <td><strong><?= htmlspecialchars($inv['invoice_no']) ?></strong></td>
                  <td><?= htmlspecialchars($inv['billing_month']) ?></td>
                  <td><?= formatDate($inv['invoice_date']) ?></td>
                  <td><?= formatDate($inv['due_date']) ?></td>
                  <td><?= number_format($inv['consumption_m3'], 1) ?> m³</td>
                  <td><?= formatCurrency($inv['total_bill']) ?></td>
                  <td><strong><?= formatCurrency($inv['total_payable']) ?></strong></td>
                  <td style="color:var(--success);"><?= formatCurrency($inv['amount_paid']) ?></td>
                  <td><span class="badge <?= $badge ?>"><?= htmlspecialchars($inv['status']) ?></span></td>
                  <td>
                    <a href="<?= APP_URL ?>/modules/billing/invoice_view.php?id=<?= $inv['invoice_id'] ?>" class="btn btn-secondary btn-sm" title="View / Print">
                      <i class="fa-solid fa-print"></i>
                    </a>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- PAYMENTS TAB -->
    <div id="tabPayments" class="tab-content" style="display:none;">
      <div class="table-responsive">
        <table class="table">
          <thead>
            <tr>
              <th>Receipt #</th>
              <th>Date</th>
              <th>Method</th>
              <th>Reference Code</th>
              <th style="text-align:right;">Amount (KES)</th>
              <th>Cashier</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($payments)): ?>
              <tr><td colspan="7" class="text-center py-4 text-muted">No payments recorded for this account.</td></tr>
            <?php else: ?>
              <?php foreach ($payments as $p): ?>
                <tr>
                  <td><strong style="color:var(--royal-blue);"><?= htmlspecialchars($p['receipt_no']) ?></strong></td>
                  <td><?= formatDate($p['payment_date']) ?></td>
                  <td><?= htmlspecialchars($p['method_name']) ?></td>
                  <td><code><?= htmlspecialchars($p['mpesa_ref'] ?? $p['bank_ref'] ?? $p['cheque_no'] ?? '-') ?></code></td>
                  <td style="text-align:right; font-weight:700; color:var(--success);"><?= number_format($p['amount'], 2) ?></td>
                  <td><small><?= htmlspecialchars($p['receiver_name']) ?></small></td>
                  <td>
                    <?= $p['is_reversed'] ? '<span class="badge badge-danger">Reversed</span>' : '<span class="badge badge-success">Completed</span>' ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- READINGS TAB -->
    <div id="tabReadings" class="tab-content" style="display:none;">
      <div class="table-responsive">
        <table class="table">
          <thead>
            <tr>
              <th>Month</th>
              <th>Date Read</th>
              <th>Previous Index</th>
              <th>Current Index</th>
              <th>Consumption (m³)</th>
              <th>Type</th>
              <th>Read By</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($readings)): ?>
              <tr><td colspan="7" class="text-center py-4 text-muted">No meter reading history available.</td></tr>
            <?php else: ?>
              <?php foreach ($readings as $r): 
                $cons = max(0, (float)$r['current_reading'] - (float)$r['previous_reading']);
              ?>
                <tr>
                  <td><strong><?= htmlspecialchars($r['billing_month']) ?></strong></td>
                  <td><?= formatDate($r['reading_date']) ?></td>
                  <td><?= number_format($r['previous_reading'], 1) ?></td>
                  <td><strong><?= number_format($r['current_reading'], 1) ?></strong></td>
                  <td><span class="badge badge-info"><?= number_format($cons, 1) ?> m³</span></td>
                  <td><?= htmlspecialchars($r['reading_type']) ?></td>
                  <td><small><?= htmlspecialchars($r['reader_name']) ?></small></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- INSPECTIONS TAB -->
    <div id="tabInspections" class="tab-content" style="display:none;">
      <div class="table-responsive">
        <table class="table">
          <thead>
            <tr>
              <th>Ref #</th>
              <th>Date</th>
              <th>Violation Type</th>
              <th>Findings</th>
              <th>Est. Loss (KES)</th>
              <th>Penalty</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($inspections)): ?>
              <tr><td colspan="7" class="text-center py-4 text-muted">No revenue protection inspections recorded for this customer.</td></tr>
            <?php else: ?>
              <?php foreach ($inspections as $ins): ?>
                <tr>
                  <td><strong><?= htmlspecialchars($ins['inspection_ref']) ?></strong></td>
                  <td><?= formatDate($ins['inspection_date']) ?></td>
                  <td><span class="badge badge-warning"><?= htmlspecialchars($ins['type_name']) ?></span></td>
                  <td><small><?= htmlspecialchars(substr($ins['findings'], 0, 80)) ?>...</small></td>
                  <td><?= formatCurrency($ins['estimated_revenue_loss']) ?></td>
                  <td><?= formatCurrency($ins['penalty_amount']) ?></td>
                  <td><span class="badge badge-light"><?= htmlspecialchars($ins['status']) ?></span></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  </div>
</div>

<!-- DELETE CUSTOMER CONFIRMATION MODAL -->
<div id="deleteCustomerModal" class="modal-overlay">
  <div class="modal" style="max-width:480px;">
    <div class="modal-header">
      <div class="modal-title" style="color:var(--danger-600);"><i class="fa-solid fa-triangle-exclamation"></i> Delete Customer Account</div>
      <button class="modal-close" onclick="closeModal('deleteCustomerModal')">&times;</button>
    </div>
    <form method="POST" action="">
      <input type="hidden" name="action" value="delete_customer">
      <div class="modal-body">
        <p>Are you sure you want to permanently delete account <strong><?= htmlspecialchars($customer['account_no']) ?> (<?= htmlspecialchars($customer['full_name']) ?>)</strong>?</p>
        <div style="background:var(--danger-50);border:1px solid var(--danger-100);border-radius:8px;padding:12px;margin:12px 0;">
          <label style="display:flex;align-items:flex-start;gap:8px;font-size:0.85rem;color:var(--danger-700);cursor:pointer;">
            <input type="checkbox" name="cascade_delete" value="1" checked style="margin-top:3px;">
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
function openTab(evt, tabName) {
  var tabContents = document.getElementsByClassName("tab-content");
  for (var i = 0; i < tabContents.length; i++) {
    tabContents[i].style.display = "none";
  }
  var tabLinks = document.getElementsByClassName("tab-btn");
  for (var i = 0; i < tabLinks.length; i++) {
    tabLinks[i].className = tabLinks[i].className.replace(" active", "");
  }
  document.getElementById(tabName).style.display = "block";
  evt.currentTarget.className += " active";
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
