<?php
/**
 * THIWASCO MIS - Procurement: LPO / Purchase Order Detail View
 */
require_once __DIR__ . "/../../includes/config.php";
require_once __DIR__ . "/../../includes/auth.php";
Auth::check();

$poId = (int)($_GET["id"] ?? 0);
if (!$poId) { header("Location: " . APP_URL . "/optional/procurement/index.php"); exit; }

$po = db()->fetch("SELECT po.*, s.company_name, s.contact_person, s.phone, s.email, s.pin_no,
                           u.full_name as created_by_name
                    FROM purchase_orders po
                    JOIN suppliers s ON po.supplier_id = s.supplier_id
                    JOIN users u ON po.created_by = u.user_id
                    WHERE po.po_id = ?", [$poId]);
if (!$po) { header("Location: " . APP_URL . "/optional/procurement/index.php"); exit; }

$items = db()->fetchAll("SELECT pi.*, si.item_code, si.item_name as catalog_name
                          FROM po_items pi
                          LEFT JOIN store_items si ON pi.item_id = si.item_id
                          WHERE pi.po_id = ?", [$poId]);

// Handle: mark as delivered
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action"]) && $_POST["action"] === "mark_delivered") {
    db()->query("UPDATE purchase_orders SET status = ? WHERE po_id = ?",
                [clean($_POST["new_status"] ?? "Delivered"), $poId]);
    Auth::logAction("Updated PO Status", "procurement", "LPO {$po["lpo_no"]} status updated");
    setFlash("success", "LPO " . $po["lpo_no"] . " status updated.");
    header("Location: ?id=" . $poId);
    exit;
}

// Handle: add LPO line item
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action"]) && $_POST["action"] === "add_item") {
    $desc   = clean($_POST["description"] ?? "");
    $qty    = (float)($_POST["quantity"] ?? 0);
    $unit   = clean($_POST["unit"] ?? "Pieces");
    $price  = (float)($_POST["unit_price"] ?? 0);
    if ($desc && $qty > 0) {
        db()->query("INSERT INTO po_items (po_id, description, quantity, unit, unit_price) VALUES (?, ?, ?, ?, ?)",
                    [$poId, $desc, $qty, $unit, $price]);
        $totalLine = $qty * $price;
        db()->query("UPDATE purchase_orders SET total_amount = total_amount + ? WHERE po_id = ?", [$totalLine, $poId]);
        setFlash("success", "Line item added to LPO.");
        header("Location: ?id=" . $poId);
        exit;
    }
}

$pageTitle = "LPO: " . $po["lpo_no"];
$activePage = "procurement";
$activeSection = "procurement";
include __DIR__ . "/../../includes/header.php";

$statusBadge = match($po["status"]) {
    "Approved","Delivered" => "badge-success",
    "Submitted" => "badge-warning",
    "Cancelled" => "badge-danger",
    default => "badge-secondary"
};
?>

<div class="page-header">
  <div class="page-title-wrap">
    <h1><i class="fa-solid fa-file-invoice" style="color:var(--gold);"></i> Local Purchase Order: <?= htmlspecialchars($po["lpo_no"]) ?></h1>
    <p>Procurement document for supplier: <strong><?= htmlspecialchars($po["company_name"]) ?></strong></p>
  </div>
  <div class="page-actions">
    <a href="<?= APP_URL ?>/optional/procurement/index.php" class="btn btn-secondary"><i class="fa-solid fa-arrow-left"></i> Back to Procurement</a>
    <button onclick="window.print()" class="btn btn-primary"><i class="fa-solid fa-print"></i> Print LPO</button>
  </div>
</div>

<div style="display:grid;grid-template-columns:2fr 1fr;gap:1.5rem;margin-bottom:1.5rem;">
  <!-- LPO Header -->
  <div class="card">
    <div class="card-header"><h3><i class="fa-solid fa-file-invoice" style="color:var(--royal-blue);"></i> LPO Details</h3></div>
    <div class="card-body">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
        <div><label class="form-label text-muted" style="font-size:0.75rem;">LPO Number</label><p><strong><?= htmlspecialchars($po["lpo_no"]) ?></strong></p></div>
        <div><label class="form-label text-muted" style="font-size:0.75rem;">Status</label><p><span class="badge <?= $statusBadge ?>"><?= htmlspecialchars($po["status"]) ?></span></p></div>
        <div><label class="form-label text-muted" style="font-size:0.75rem;">Order Date</label><p><?= formatDate($po["order_date"]) ?></p></div>
        <div><label class="form-label text-muted" style="font-size:0.75rem;">Expected Delivery</label><p><?= $po["expected_delivery"] ? formatDate($po["expected_delivery"]) : "Not Set" ?></p></div>
        <div><label class="form-label text-muted" style="font-size:0.75rem;">Total Amount</label><p><strong style="color:var(--navy);font-size:1.2rem;"><?= formatCurrency($po["total_amount"]) ?></strong></p></div>
        <div><label class="form-label text-muted" style="font-size:0.75rem;">Created By</label><p><?= htmlspecialchars($po["created_by_name"]) ?></p></div>
      </div>
      <?php if ($po["notes"]): ?>
        <div class="alert alert-info mt-3"><strong>Notes:</strong> <?= htmlspecialchars($po["notes"]) ?></div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Supplier Info -->
  <div class="card">
    <div class="card-header"><h3><i class="fa-solid fa-truck" style="color:var(--royal-blue);"></i> Supplier</h3></div>
    <div class="card-body">
      <h4 style="margin:0 0 0.5rem;color:var(--navy);"><?= htmlspecialchars($po["company_name"]) ?></h4>
      <p style="margin:0.25rem 0;"><i class="fa-solid fa-user text-muted"></i> <?= htmlspecialchars($po["contact_person"] ?? "-") ?></p>
      <p style="margin:0.25rem 0;"><i class="fa-solid fa-phone text-muted"></i> <?= htmlspecialchars($po["phone"] ?? "-") ?></p>
      <p style="margin:0.25rem 0;"><i class="fa-solid fa-envelope text-muted"></i> <?= htmlspecialchars($po["email"] ?? "-") ?></p>
      <p style="margin:0.25rem 0;"><i class="fa-solid fa-hashtag text-muted"></i> PIN: <?= htmlspecialchars($po["pin_no"] ?? "-") ?></p>

      <!-- Update Status -->
      <?php if (!in_array($po["status"], ["Delivered","Cancelled"])): ?>
      <form method="POST" action="" style="margin-top:1rem;">
        <input type="hidden" name="action" value="mark_delivered">
        <div class="form-group mb-2">
          <select name="new_status" class="form-control">
            <option value="Approved" <?= $po["status"]==="Approved"?"selected":"" ?>>Approved</option>
            <option value="Delivered" <?= $po["status"]==="Delivered"?"selected":"" ?>>Fully Delivered</option>
            <option value="Partial">Partially Delivered</option>
            <option value="Cancelled">Cancel LPO</option>
          </select>
        </div>
        <button type="submit" class="btn btn-primary" style="width:100%;"><i class="fa-solid fa-check-circle"></i> Update Status</button>
      </form>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- LPO Line Items -->
<div class="card mb-4">
  <div class="card-header">
    <h3><i class="fa-solid fa-list-check" style="color:var(--royal-blue);"></i> LPO Line Items</h3>
    <?php if (!in_array($po["status"], ["Delivered","Cancelled"])): ?>
    <button onclick="openModal(\"addItemModal\")" class="btn btn-primary btn-sm"><i class="fa-solid fa-plus"></i> Add Line Item</button>
    <?php endif; ?>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table">
        <thead>
          <tr>
            <th>#</th>
            <th>Item Code</th>
            <th>Description</th>
            <th style="text-align:right;">Qty Ordered</th>
            <th>Unit</th>
            <th style="text-align:right;">Unit Price (KES)</th>
            <th style="text-align:right;">Total (KES)</th>
            <th style="text-align:right;">Qty Received</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($items)): ?>
            <tr><td colspan="8" class="text-center py-4 text-muted">No line items added to this LPO yet.</td></tr>
          <?php else: ?>
            <?php $grandTotal = 0; $n = 1; foreach ($items as $li):
              $lineTotal = $li["quantity"] * $li["unit_price"];
              $grandTotal += $lineTotal;
            ?>
              <tr>
                <td><?= $n++ ?></td>
                <td><?= $li["item_code"] ? "<strong style=\"color:var(--royal-blue);\">" . htmlspecialchars($li["item_code"]) . "</strong>" : "<span class=\"text-muted\">—</span>" ?></td>
                <td><?= htmlspecialchars($li["description"]) ?></td>
                <td style="text-align:right;font-weight:700;"><?= number_format($li["quantity"], 2) ?></td>
                <td><?= htmlspecialchars($li["unit"] ?? "Pcs") ?></td>
                <td style="text-align:right;"><?= number_format($li["unit_price"], 2) ?></td>
                <td style="text-align:right;font-weight:700;color:var(--navy);"><?= formatCurrency($lineTotal) ?></td>
                <td style="text-align:right;color:<?= $li["quantity_received"] >= $li["quantity"] ? "var(--success)" : "var(--warning)" ?>;">
                  <?= number_format($li["quantity_received"], 2) ?>
                </td>
              </tr>
            <?php endforeach; ?>
            <tr style="background:var(--bg-light);font-weight:700;">
              <td colspan="6" style="text-align:right;padding:0.75rem 1rem;">Grand Total</td>
              <td style="text-align:right;color:var(--navy);font-size:1.1rem;"><?= formatCurrency($grandTotal) ?></td>
              <td></td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Add Item Modal -->
<div id="addItemModal" class="modal-backdrop">
  <div class="modal" style="max-width:480px;">
    <div class="modal-header">
      <h3><i class="fa-solid fa-plus-circle" style="color:var(--gold);"></i> Add LPO Line Item</h3>
      <button class="modal-close" onclick="closeModal(\"addItemModal\")">&times;</button>
    </div>
    <form method="POST" action="">
      <input type="hidden" name="action" value="add_item">
      <div class="modal-body">
        <div class="form-group mb-3">
          <label class="form-label">Description <span class="text-danger">*</span></label>
          <input type="text" name="description" class="form-control" placeholder="e.g. 15mm (1/2\") Elster Kent Water Meter" required>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem;">
          <div class="form-group mb-3">
            <label class="form-label">Quantity</label>
            <input type="number" step="0.01" name="quantity" class="form-control" value="1" required>
          </div>
          <div class="form-group mb-3">
            <label class="form-label">Unit</label>
            <input type="text" name="unit" class="form-control" value="Pieces">
          </div>
          <div class="form-group mb-3">
            <label class="form-label">Unit Price (KES)</label>
            <input type="number" step="0.01" name="unit_price" class="form-control" value="0" required>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal(\"addItemModal\")">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> Add Item</button>
      </div>
    </form>
  </div>
</div>

<?php include __DIR__ . "/../../includes/footer.php"; ?>
