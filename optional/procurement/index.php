<?php
/**
 * THIWASCO MIS - Optional Module: Procurement & Supply Chain
 */
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';

Auth::check();

$pageTitle = 'Procurement & Suppliers';
$activePage = 'procurement_lpos';
$activeSection = 'procurement';

// Handle Add Supplier POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_supplier') {
    $code = strtoupper(clean($_POST['supplier_code'] ?? ''));
    $name = clean($_POST['company_name'] ?? '');
    $contact = clean($_POST['contact_person'] ?? '');
    $phone = clean($_POST['phone'] ?? '');
    $email = clean($_POST['email'] ?? '');
    $pin = clean($_POST['pin_no'] ?? '');

    if (!empty($code) && !empty($name)) {
        db()->query("INSERT INTO suppliers (supplier_code, company_name, contact_person, phone, email, pin_no)
                     VALUES (?, ?, ?, ?, ?, ?)",
                     [$code, $name, $contact, $phone, $email, $pin]);
        Auth::logAction('Added Supplier', 'procurement', "Added supplier {$name}");
        setFlash('success', "Supplier {$name} registered successfully.");
        header('Location: ' . APP_URL . '/optional/procurement/index.php');
        exit;
    }
}

// Handle Add Purchase Order POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_po') {
    $suppId = (int)$_POST['supplier_id'];
    $orderDate = clean($_POST['order_date'] ?? date('Y-m-d'));
    $deliveryDate = clean($_POST['expected_delivery'] ?? date('Y-m-d', strtotime('+14 days')));
    $amount = (float)($_POST['total_amount'] ?? 0);
    $notes = clean($_POST['notes'] ?? '');

    if ($suppId > 0 && $amount > 0) {
        $lpoNo = generateRef('LPO', 'purchase_orders', 'lpo_no');
        db()->query("INSERT INTO purchase_orders (lpo_no, supplier_id, order_date, expected_delivery, total_amount, status, created_by, notes)
                     VALUES (?, ?, ?, ?, ?, 'Approved', ?, ?)",
                     [$lpoNo, $suppId, $orderDate, $deliveryDate, $amount, Auth::id(), $notes]);
        Auth::logAction('Created LPO', 'procurement', "Raised LPO {$lpoNo} for KES {$amount}");
        setFlash('success', "Local Purchase Order <strong>{$lpoNo}</strong> created successfully.");
        header('Location: ' . APP_URL . '/optional/procurement/index.php');
        exit;
    }
}

$suppliers = db()->fetchAll("SELECT * FROM suppliers WHERE is_active = 1 ORDER BY company_name ASC");
$pos = db()->fetchAll("SELECT p.*, s.company_name, u.full_name as author_name 
                       FROM purchase_orders p 
                       JOIN suppliers s ON p.supplier_id = s.supplier_id 
                       JOIN users u ON p.created_by = u.user_id 
                       ORDER BY p.order_date DESC");

$totalPOAmount = array_sum(array_column($pos, 'total_amount'));

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
  <div class="page-title-wrap">
    <h1><i class="fa-solid fa-cart-shopping" style="color:var(--gold);"></i> Procurement & Supply Chain</h1>
    <p>Prequalified suppliers, vendor contracts, Local Purchase Orders (LPOs) and delivery fulfillment</p>
  </div>
  <div class="page-actions">
    <button onclick="openModal('addSuppModal')" class="btn btn-secondary">
      <i class="fa-solid fa-user-plus"></i> Add Vendor
    </button>
    <button onclick="openModal('addPOModal')" class="btn btn-primary">
      <i class="fa-solid fa-plus-circle"></i> Create Purchase Order
    </button>
  </div>
</div>

<div class="stats-grid mb-4">
  <div class="stat-card">
    <div class="stat-icon" style="background:rgba(30,58,138,0.1);color:var(--royal-blue);"><i class="fa-solid fa-truck"></i></div>
    <div class="stat-info">
      <span class="stat-value"><?= count($suppliers) ?></span>
      <span class="stat-label">Prequalified Vendors</span>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:rgba(21,128,61,0.1);color:var(--success);"><i class="fa-solid fa-file-invoice"></i></div>
    <div class="stat-info">
      <span class="stat-value"><?= count($pos) ?></span>
      <span class="stat-label">Purchase Orders Raised</span>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:rgba(234,179,8,0.1);color:var(--gold);"><i class="fa-solid fa-vault"></i></div>
    <div class="stat-info">
      <span class="stat-value"><?= formatCurrency($totalPOAmount) ?></span>
      <span class="stat-label">Total Procurement Value</span>
    </div>
  </div>
</div>

<div class="card mb-4">
  <div class="card-header">
    <h3><i class="fa-solid fa-file-contract" style="color:var(--royal-blue);"></i> Purchase Orders (LPOs)</h3>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table">
        <thead>
          <tr>
            <th>LPO #</th>
            <th>Vendor / Supplier</th>
            <th>Order Date</th>
            <th>Expected Delivery</th>
            <th style="text-align:right;">Order Amount (KES)</th>
            <th>Status</th>
            <th>Raised By</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($pos)): ?>
            <tr><td colspan="7" class="text-center py-4 text-muted">No purchase orders raised yet.</td></tr>
          <?php else: ?>
            <?php foreach ($pos as $po): ?>
              <tr>
                <td><strong style="color:var(--royal-blue);"><?= htmlspecialchars($po['lpo_no']) ?></strong></td>
                <td><strong><?= htmlspecialchars($po['company_name']) ?></strong></td>
                <td><?= formatDate($po['order_date']) ?></td>
                <td><?= formatDate($po['expected_delivery']) ?></td>
                <td style="text-align:right;font-weight:700;color:var(--navy);"><?= number_format($po['total_amount'], 2) ?></td>
                <td><span class="badge badge-success"><?= htmlspecialchars($po['status']) ?></span></td>
                <td><small><?= htmlspecialchars($po['author_name']) ?></small></td>
                <td>
                  <a href="<?= APP_URL ?>/optional/procurement/lpo_view.php?id=<?= $po['po_id'] ?>" class="btn btn-secondary btn-sm">
                    <i class="fa-solid fa-eye"></i> View LPO
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

<!-- SUPPLIERS TABLE -->
<div class="card">
  <div class="card-header">
    <h3><i class="fa-solid fa-truck" style="color:var(--gold);"></i> Approved Suppliers Directory</h3>
  </div>
  <div class="card-body p-0">
    <table class="table" style="font-size:0.9rem;">
      <thead>
        <tr>
          <th>Code</th>
          <th>Company Name</th>
          <th>Contact Person</th>
          <th>Phone</th>
          <th>Email</th>
          <th>KRA PIN</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($suppliers as $s): ?>
          <tr>
            <td><strong><?= htmlspecialchars($s['supplier_code']) ?></strong></td>
            <td><strong><?= htmlspecialchars($s['company_name']) ?></strong></td>
            <td><?= htmlspecialchars($s['contact_person'] ?? '-') ?></td>
            <td><?= htmlspecialchars($s['phone'] ?? '-') ?></td>
            <td><?= htmlspecialchars($s['email'] ?? '-') ?></td>
            <td><code><?= htmlspecialchars($s['pin_no'] ?? '-') ?></code></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- MODALS -->
<div id="addSuppModal" class="modal-backdrop">
  <div class="modal" style="max-width: 500px;">
    <div class="modal-header">
      <h3><i class="fa-solid fa-truck" style="color:var(--gold);"></i> Add Vendor</h3>
      <button class="modal-close" onclick="closeModal('addSuppModal')">&times;</button>
    </div>
    <form method="POST" action="">
      <input type="hidden" name="action" value="add_supplier">
      <div class="modal-body">
        <div class="form-group mb-3">
          <label class="form-label">Vendor Code <span class="text-danger">*</span></label>
          <input type="text" name="supplier_code" class="form-control" placeholder="e.g. SUP-001" required>
        </div>
        <div class="form-group mb-3">
          <label class="form-label">Company Name <span class="text-danger">*</span></label>
          <input type="text" name="company_name" class="form-control" placeholder="e.g. Davis & Shirtliff Ltd" required>
        </div>
        <div class="row" style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
          <div class="form-group mb-3">
            <label class="form-label">Contact Person</label>
            <input type="text" name="contact_person" class="form-control" placeholder="e.g. Sales Director">
          </div>
          <div class="form-group mb-3">
            <label class="form-label">Phone</label>
            <input type="tel" name="phone" class="form-control" placeholder="07XXXXXXXX">
          </div>
        </div>
        <div class="row" style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
          <div class="form-group mb-3">
            <label class="form-label">Email</label>
            <input type="email" name="email" class="form-control" placeholder="sales@vendor.com">
          </div>
          <div class="form-group mb-3">
            <label class="form-label">KRA PIN</label>
            <input type="text" name="pin_no" class="form-control" placeholder="P051XXXXXXX">
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('addSuppModal')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> Save Vendor</button>
      </div>
    </form>
  </div>
</div>

<div id="addPOModal" class="modal-backdrop">
  <div class="modal" style="max-width: 520px;">
    <div class="modal-header">
      <h3><i class="fa-solid fa-cart-shopping" style="color:var(--gold);"></i> Issue Local Purchase Order (LPO)</h3>
      <button class="modal-close" onclick="closeModal('addPOModal')">&times;</button>
    </div>
    <form method="POST" action="">
      <input type="hidden" name="action" value="create_po">
      <div class="modal-body">
        <div class="form-group mb-3">
          <label class="form-label">Select Vendor <span class="text-danger">*</span></label>
          <select name="supplier_id" class="form-control" required>
            <?php foreach ($suppliers as $s): ?>
              <option value="<?= $s['supplier_id'] ?>"><?= htmlspecialchars($s['company_name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="row" style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
          <div class="form-group mb-3">
            <label class="form-label">Order Date</label>
            <input type="date" name="order_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
          </div>
          <div class="form-group mb-3">
            <label class="form-label">Delivery By</label>
            <input type="date" name="expected_delivery" class="form-control" value="<?= date('Y-m-d', strtotime('+14 days')) ?>" required>
          </div>
        </div>
        <div class="form-group mb-3">
          <label class="form-label">Total Amount (KES) <span class="text-danger">*</span></label>
          <input type="number" step="0.01" name="total_amount" class="form-control" style="font-size:1.15rem;font-weight:700;color:var(--navy);" required>
        </div>
        <div class="form-group mb-3">
          <label class="form-label">Order Scope & Notes</label>
          <textarea name="notes" class="form-control" rows="2" placeholder="Supply of 15mm water meters and brass ball valves..."></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('addPOModal')">Cancel</button>
        <button type="submit" class="btn btn-success"><i class="fa-solid fa-file-signature"></i> Approve & Issue LPO</button>
      </div>
    </form>
  </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
