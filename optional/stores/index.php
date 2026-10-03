<?php
/**
 * THIWASCO MIS - Optional Module: Stores & Inventory Management
 */
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';

Auth::check();

$pageTitle = 'Stores & Inventory';
$activePage = 'stores_inventory';
$activeSection = 'stores';

// Handle Add Item POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_item') {
    $code = strtoupper(clean($_POST['item_code'] ?? ''));
    $name = clean($_POST['item_name'] ?? '');
    $category = clean($_POST['category'] ?? 'Pipes');
    $uom = clean($_POST['unit_of_measure'] ?? 'Pieces');
    $stock = (float)($_POST['current_stock'] ?? 0);
    $reorder = (float)($_POST['reorder_level'] ?? 10);
    $unitCost = (float)($_POST['unit_cost'] ?? 0);
    $loc = clean($_POST['location'] ?? 'Main Store');

    if (!empty($code) && !empty($name)) {
        db()->query("INSERT INTO store_items (item_code, item_name, category, unit_of_measure, current_stock, reorder_level, unit_cost, location)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                     [$code, $name, $category, $uom, $stock, $reorder, $unitCost, $loc]);
        Auth::logAction('Added Store Item', 'stores', "Added item {$code}: {$name}");
        setFlash('success', "Item {$code} added to inventory.");
        header('Location: ' . APP_URL . '/optional/stores/index.php');
        exit;
    }
}

// Handle Edit Item POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_item') {
    $itemId = (int)($_POST['item_id'] ?? 0);
    $code = strtoupper(clean($_POST['item_code'] ?? ''));
    $name = clean($_POST['item_name'] ?? '');
    $category = clean($_POST['category'] ?? 'Pipes');
    $uom = clean($_POST['unit_of_measure'] ?? 'Pieces');
    $reorder = (float)($_POST['reorder_level'] ?? 10);
    $unitCost = (float)($_POST['unit_cost'] ?? 0);
    $loc = clean($_POST['location'] ?? 'Main Store');

    if ($itemId > 0 && !empty($code) && !empty($name)) {
        db()->query("UPDATE store_items SET item_code = ?, item_name = ?, category = ?, unit_of_measure = ?, reorder_level = ?, unit_cost = ?, location = ? WHERE item_id = ?",
                     [$code, $name, $category, $uom, $reorder, $unitCost, $loc, $itemId]);
        Auth::logAction('Updated Store Item', 'stores', "Updated item {$code}");
        setFlash('success', "Item {$code} updated successfully.");
        header('Location: ' . APP_URL . '/optional/stores/index.php');
        exit;
    }
}

// Handle Delete Item POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_item') {
    $itemId = (int)($_POST['item_id'] ?? 0);
    if ($itemId > 0) {
        $it = db()->fetch("SELECT item_code FROM store_items WHERE item_id = ?", [$itemId]);
        if ($it) {
            try {
                db()->query("DELETE FROM store_items WHERE item_id = ?", [$itemId]);
                Auth::logAction('Deleted Store Item', 'stores', "Deleted item {$it['item_code']}");
                setFlash('success', "Item {$it['item_code']} deleted from catalog.");
            } catch (Exception $e) {
                db()->query("UPDATE store_items SET is_active = 0 WHERE item_id = ?", [$itemId]);
                setFlash('warning', "Item has linked stock ledger records and was deactivated instead of deleted.");
            }
        }
        header('Location: ' . APP_URL . '/optional/stores/index.php');
        exit;
    }
}

// Handle Stock Transaction (Issue / Receive)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'stock_txn') {
    $itemId = (int)$_POST['item_id'];
    $txnType = clean($_POST['transaction_type'] ?? 'Issued');
    $qty = (float)($_POST['quantity'] ?? 0);
    $purpose = clean($_POST['purpose'] ?? '');
    $issuedTo = clean($_POST['issued_to'] ?? '');

    $item = db()->fetch("SELECT * FROM store_items WHERE item_id = ?", [$itemId]);
    if ($item && $qty > 0) {
        $newBal = ($txnType === 'Received' || $txnType === 'Returned') ? ($item['current_stock'] + $qty) : ($item['current_stock'] - $qty);
        if ($newBal < 0) {
            setFlash('danger', 'Insufficient stock available.');
        } else {
            db()->query("INSERT INTO stock_transactions (item_id, transaction_type, quantity, balance_after, unit_cost, purpose, issued_to, transacted_by, transaction_date)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, CURDATE())",
                         [$itemId, $txnType, $qty, $newBal, $item['unit_cost'], $purpose, $issuedTo, Auth::id()]);
            db()->query("UPDATE store_items SET current_stock = ? WHERE item_id = ?", [$newBal, $itemId]);
            Auth::logAction('Stock Transaction', 'stores', "{$txnType} {$qty} of {$item['item_code']}");
            setFlash('success', "Stock transaction completed. New balance: {$newBal} {$item['unit_of_measure']}.");
            header('Location: ' . APP_URL . '/optional/stores/index.php');
            exit;
        }
    }
}

$items = db()->fetchAll("SELECT * FROM store_items WHERE is_active = 1 ORDER BY category ASC, item_name ASC");

$totalItems = count($items);
$totalVal = array_sum(array_map(fn($x) => $x['current_stock'] * $x['unit_cost'], $items));
$lowStock = count(array_filter($items, fn($x) => $x['current_stock'] <= $x['reorder_level']));

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
  <div class="page-title-wrap">
    <h1><i class="fa-solid fa-warehouse" style="color:var(--gold);"></i> Stores & Materials Inventory</h1>
    <p>Manage water distribution pipes, HDPE fittings, customer meters, chemicals and maintenance materials</p>
  </div>
  <div class="page-actions">
    <button onclick="openModal('addItemModal')" class="btn btn-primary">
      <i class="fa-solid fa-plus-circle"></i> Add Inventory Item
    </button>
  </div>
</div>

<!-- STATS -->
<div class="stats-grid mb-4">
  <div class="stat-card">
    <div class="stat-icon" style="background:rgba(30,58,138,0.1);color:var(--royal-blue);"><i class="fa-solid fa-boxes-stacked"></i></div>
    <div class="stat-info">
      <span class="stat-value"><?= number_format($totalItems) ?></span>
      <span class="stat-label">Cataloged Material Items</span>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:rgba(21,128,61,0.1);color:var(--success);"><i class="fa-solid fa-vault"></i></div>
    <div class="stat-info">
      <span class="stat-value"><?= formatCurrency($totalVal) ?></span>
      <span class="stat-label">Total Store Stock Valuation</span>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:rgba(239,68,68,0.1);color:var(--danger);"><i class="fa-solid fa-bell"></i></div>
    <div class="stat-info">
      <span class="stat-value"><?= number_format($lowStock) ?></span>
      <span class="stat-label">Items Below Reorder Level</span>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <h3><i class="fa-solid fa-list-check" style="color:var(--royal-blue);"></i> Stock Master Register</h3>
    <span class="badge badge-light"><?= $totalItems ?> Items</span>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table">
        <thead>
          <tr>
            <th>Item Code</th>
            <th>Description</th>
            <th>Category</th>
            <th>Unit</th>
            <th style="text-align:right;">Current Stock</th>
            <th style="text-align:right;">Reorder Level</th>
            <th style="text-align:right;">Unit Cost</th>
            <th style="text-align:right;">Total Value</th>
            <th>Location</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($items)): ?>
            <tr><td colspan="10" class="text-center py-4 text-muted">No stock items found in store catalog.</td></tr>
          <?php else: ?>
            <?php foreach ($items as $it): 
              $isLow = ($it['current_stock'] <= $it['reorder_level']);
              $val = $it['current_stock'] * $it['unit_cost'];
            ?>
              <tr>
                <td><strong style="color:var(--royal-blue);"><?= htmlspecialchars($it['item_code']) ?></strong></td>
                <td><strong><?= htmlspecialchars($it['item_name']) ?></strong></td>
                <td><span class="badge badge-light"><?= htmlspecialchars($it['category']) ?></span></td>
                <td><?= htmlspecialchars($it['unit_of_measure']) ?></td>
                <td style="text-align:right;font-weight:700;color:<?= $isLow ? 'var(--danger)' : 'var(--navy)' ?>;">
                  <?= number_format($it['current_stock'], 1) ?>
                  <?php if ($isLow): ?>
                    <i class="fa-solid fa-circle-exclamation" title="Low Stock Alert" style="color:var(--danger);margin-left:4px;"></i>
                  <?php endif; ?>
                </td>
                <td style="text-align:right;color:var(--text-muted);"><?= number_format($it['reorder_level'], 1) ?></td>
                <td style="text-align:right;"><?= number_format($it['unit_cost'], 2) ?></td>
                <td style="text-align:right;font-weight:600;"><?= formatCurrency($val) ?></td>
                <td><small><?= htmlspecialchars($it['location'] ?? 'Main Store') ?></small></td>
                <td>
                  <div style="display:inline-flex;gap:4px;">
                    <a href="<?= APP_URL ?>/optional/stores/ledger.php?item_id=<?= $it['item_id'] ?>" class="btn btn-secondary btn-sm" title="Ledger">
                      <i class="fa-solid fa-book"></i>
                    </a>
                    <button type="button" class="btn btn-primary btn-sm" onclick="openTxnModal(<?= $it['item_id'] ?>, '<?= htmlspecialchars($it['item_code']) ?>', '<?= htmlspecialchars($it['item_name']) ?>', <?= $it['current_stock'] ?>)" title="Issue / Receive">
                      <i class="fa-solid fa-dolly"></i>
                    </button>
                    <button type="button" class="btn btn-secondary btn-sm" onclick='openEditItemModal(<?= json_encode($it) ?>)' title="Edit Item">
                      <i class="fa-solid fa-pen-to-square"></i>
                    </button>
                    <form method="POST" action="" style="display:inline;" onsubmit="return confirm('Delete item <?= htmlspecialchars(addslashes($it['item_code'])) ?> from catalog?');">
                      <input type="hidden" name="action" value="delete_item">
                      <input type="hidden" name="item_id" value="<?= $it['item_id'] ?>">
                      <button type="submit" class="btn btn-danger btn-sm" title="Delete Item">
                        <i class="fa-solid fa-trash"></i>
                      </button>
                    </form>
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

<!-- ADD ITEM MODAL -->
<div id="addItemModal" class="modal-overlay">
  <div class="modal" style="max-width: 550px;">
    <div class="modal-header">
      <h3><i class="fa-solid fa-boxes-stacked" style="color:var(--gold-300);"></i> Add Store Material Item</h3>
      <button class="modal-close" onclick="closeModal('addItemModal')">&times;</button>
    </div>
    <form method="POST" action="">
      <input type="hidden" name="action" value="add_item">
      <div class="modal-body">
        <div class="row" style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
          <div class="form-group mb-3">
            <label class="form-label">Item Code <span class="text-danger">*</span></label>
            <input type="text" name="item_code" class="form-control" placeholder="e.g. PIP-HDPE-025" required>
          </div>
          <div class="form-group mb-3">
            <label class="form-label">Category <span class="text-danger">*</span></label>
            <select name="category" class="form-control" required>
              <option value="Pipes">Pipes (HDPE/PPR/GI)</option>
              <option value="Fittings">Fittings & Valves</option>
              <option value="Meters">Water Meters</option>
              <option value="Chemicals">Water Treatment Chemicals</option>
              <option value="Hardware">Hardware & Tools</option>
              <option value="Stationery">Office & Stationery</option>
            </select>
          </div>
        </div>
        <div class="form-group mb-3">
          <label class="form-label">Item Name / Description <span class="text-danger">*</span></label>
          <input type="text" name="item_name" class="form-control" placeholder="e.g. 25mm PN16 HDPE Water Pipe (100m Roll)" required>
        </div>
        <div class="row" style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem;">
          <div class="form-group mb-3">
            <label class="form-label">Unit of Measure</label>
            <input type="text" name="unit_of_measure" class="form-control" value="Rolls" required>
          </div>
          <div class="form-group mb-3">
            <label class="form-label">Initial Stock</label>
            <input type="number" step="0.1" name="current_stock" class="form-control" value="10">
          </div>
          <div class="form-group mb-3">
            <label class="form-label">Reorder Level</label>
            <input type="number" step="0.1" name="reorder_level" class="form-control" value="5">
          </div>
        </div>
        <div class="row" style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
          <div class="form-group mb-3">
            <label class="form-label">Unit Cost (KES)</label>
            <input type="number" step="0.01" name="unit_cost" class="form-control" value="4500.00">
          </div>
          <div class="form-group mb-3">
            <label class="form-label">Bin / Store Location</label>
            <input type="text" name="location" class="form-control" value="Main Yard - Rack 4">
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('addItemModal')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> Save Item</button>
      </div>
    </form>
  </div>
</div>

<!-- EDIT ITEM MODAL -->
<div id="editItemModal" class="modal-overlay">
  <div class="modal" style="max-width: 550px;">
    <div class="modal-header">
      <h3><i class="fa-solid fa-pen-to-square" style="color:var(--gold-300);"></i> Edit Material Item</h3>
      <button class="modal-close" onclick="closeModal('editItemModal')">&times;</button>
    </div>
    <form method="POST" action="">
      <input type="hidden" name="action" value="edit_item">
      <input type="hidden" name="item_id" id="edit_item_id">
      <div class="modal-body">
        <div class="row" style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
          <div class="form-group mb-3">
            <label class="form-label">Item Code <span class="text-danger">*</span></label>
            <input type="text" name="item_code" id="edit_item_code" class="form-control" required>
          </div>
          <div class="form-group mb-3">
            <label class="form-label">Category <span class="text-danger">*</span></label>
            <select name="category" id="edit_category" class="form-control" required>
              <option value="Pipes">Pipes (HDPE/PPR/GI)</option>
              <option value="Fittings">Fittings & Valves</option>
              <option value="Meters">Water Meters</option>
              <option value="Chemicals">Water Treatment Chemicals</option>
              <option value="Hardware">Hardware & Tools</option>
              <option value="Stationery">Office & Stationery</option>
            </select>
          </div>
        </div>
        <div class="form-group mb-3">
          <label class="form-label">Item Name / Description <span class="text-danger">*</span></label>
          <input type="text" name="item_name" id="edit_item_name" class="form-control" required>
        </div>
        <div class="row" style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
          <div class="form-group mb-3">
            <label class="form-label">Unit of Measure <span class="text-danger">*</span></label>
            <input type="text" name="unit_of_measure" id="edit_unit_of_measure" class="form-control" required>
          </div>
          <div class="form-group mb-3">
            <label class="form-label">Reorder Level Threshold</label>
            <input type="number" step="0.1" name="reorder_level" id="edit_reorder_level" class="form-control">
          </div>
        </div>
        <div class="row" style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
          <div class="form-group mb-3">
            <label class="form-label">Unit Cost (KES)</label>
            <input type="number" step="0.01" name="unit_cost" id="edit_unit_cost" class="form-control">
          </div>
          <div class="form-group mb-3">
            <label class="form-label">Storage Bin / Location</label>
            <input type="text" name="location" id="edit_location" class="form-control">
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('editItemModal')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check"></i> Save Changes</button>
      </div>
    </form>
  </div>
</div>

<!-- STOCK TRANSACTION MODAL -->
<div id="txnModal" class="modal-overlay">
  <div class="modal" style="max-width: 480px;">
    <div class="modal-header">
      <h3><i class="fa-solid fa-dolly" style="color:var(--gold-300);"></i> Record Stock Movement</h3>
      <button class="modal-close" onclick="closeModal('txnModal')">&times;</button>
    </div>
    <form method="POST" action="">
      <input type="hidden" name="action" value="stock_txn">
      <input type="hidden" name="item_id" id="txItemId">
      <div class="modal-body">
        <p>Item: <strong id="txItemName"></strong> (<span id="txItemCode"></span>)</p>
        <div class="alert alert-info mb-3">Available Current Stock: <strong id="txCurrentStock"></strong></div>

        <div class="form-group mb-3">
          <label class="form-label">Movement Type <span class="text-danger">*</span></label>
          <select name="transaction_type" class="form-control" required>
            <option value="Issued">Issue Out to Field / Plumber</option>
            <option value="Received">Receive from Supplier</option>
            <option value="Returned">Return from Field</option>
            <option value="Adjusted">Stock Take Adjustment</option>
          </select>
        </div>

        <div class="form-group mb-3">
          <label class="form-label">Quantity <span class="text-danger">*</span></label>
          <input type="number" step="0.1" name="quantity" class="form-control" required>
        </div>

        <div class="form-group mb-3">
          <label class="form-label">Issued To / Field Plumber</label>
          <input type="text" name="issued_to" class="form-control" placeholder="e.g. Peter Mwangi (Technician)">
        </div>

        <div class="form-group mb-3">
          <label class="form-label">Purpose / Job Card / Project</label>
          <input type="text" name="purpose" class="form-control" placeholder="e.g. Repair burst on Section 9 line">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('txnModal')">Cancel</button>
        <button type="submit" class="btn btn-success"><i class="fa-solid fa-check"></i> Post Movement</button>
      </div>
    </form>
  </div>
</div>

<script>
function openTxnModal(id, code, name, cur) {
  document.getElementById('txItemId').value = id;
  document.getElementById('txItemCode').textContent = code;
  document.getElementById('txItemName').textContent = name;
  document.getElementById('txCurrentStock').textContent = cur;
  openModal('txnModal');
}

function openEditItemModal(it) {
  document.getElementById('edit_item_id').value = it.item_id;
  document.getElementById('edit_item_code').value = it.item_code;
  document.getElementById('edit_item_name').value = it.item_name;
  document.getElementById('edit_category').value = it.category;
  document.getElementById('edit_unit_of_measure').value = it.unit_of_measure;
  document.getElementById('edit_reorder_level').value = it.reorder_level;
  document.getElementById('edit_unit_cost').value = it.unit_cost;
  document.getElementById('edit_location').value = it.location || '';
  openModal('editItemModal');
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
