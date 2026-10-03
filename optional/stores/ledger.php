<?php
/**
 * THIWASCO MIS - Stores: Stock Movement Ledger
 */
require_once __DIR__ . "/../../includes/config.php";
require_once __DIR__ . "/../../includes/auth.php";
Auth::check();

$pageTitle = "Stock Movement Ledger";
$activePage = "stores";
$activeSection = "stores";

$itemId = (int)($_GET["item_id"] ?? 0);
$item = $itemId ? db()->fetch("SELECT * FROM store_items WHERE item_id = ?", [$itemId]) : null;

$filter = clean($_GET["type"] ?? "");
$sql = "SELECT st.*, si.item_name, si.item_code, si.unit_of_measure, u.full_name
        FROM stock_transactions st
        JOIN store_items si ON st.item_id = si.item_id
        JOIN users u ON st.transacted_by = u.user_id";
$params = [];
if ($itemId) {
    $sql .= " WHERE st.item_id = ?";
    $params[] = $itemId;
    if ($filter) { $sql .= " AND st.transaction_type = ?"; $params[] = $filter; }
} elseif ($filter) {
    $sql .= " WHERE st.transaction_type = ?";
    $params[] = $filter;
}
$sql .= " ORDER BY st.created_at DESC LIMIT 200";
$txns = db()->fetchAll($sql, $params);

$totalOut = array_sum(array_map(fn($t) => in_array($t["transaction_type"], ["Issued","Written-Off"]) ? $t["quantity"] : 0, $txns));
$totalIn  = array_sum(array_map(fn($t) => in_array($t["transaction_type"], ["Received","Returned"]) ? $t["quantity"] : 0, $txns));

include __DIR__ . "/../../includes/header.php";
?>

<div class="page-header">
  <div class="page-title-wrap">
    <h1><i class="fa-solid fa-book" style="color:var(--gold);"></i> Stock Movement Ledger</h1>
    <p><?= $item ? "Movements for: <strong>" . htmlspecialchars($item["item_name"]) . "</strong>" : "All material movements across all store items" ?></p>
  </div>
  <div class="page-actions">
    <a href="<?= APP_URL ?>/optional/stores/index.php" class="btn btn-secondary"><i class="fa-solid fa-arrow-left"></i> Back to Inventory</a>
  </div>
</div>

<!-- Filter Bar -->
<div class="card mb-4">
  <div class="card-body" style="padding:1rem 1.5rem;">
    <form method="GET" action="" style="display:flex;gap:1rem;align-items:flex-end;flex-wrap:wrap;">
      <?php if ($itemId): ?><input type="hidden" name="item_id" value="<?= $itemId ?>"><?php endif; ?>
      <div class="form-group" style="min-width:180px;margin:0;">
        <label class="form-label" style="margin-bottom:4px;">Movement Type</label>
        <select name="type" class="form-control">
          <option value="">All Movements</option>
          <option value="Issued" <?= $filter==="Issued"?"selected":"" ?>>Issued to Field</option>
          <option value="Received" <?= $filter==="Received"?"selected":"" ?>>Received from Supplier</option>
          <option value="Returned" <?= $filter==="Returned"?"selected":"" ?>>Returned from Field</option>
          <option value="Adjusted" <?= $filter==="Adjusted"?"selected":"" ?>>Stock Adjustment</option>
          <option value="Written-Off" <?= $filter==="Written-Off"?"selected":"" ?>>Written Off</option>
        </select>
      </div>
      <button type="submit" class="btn btn-primary"><i class="fa-solid fa-filter"></i> Filter</button>
      <a href="?<?= $itemId?"item_id=$itemId":""?>" class="btn btn-secondary">Clear</a>
    </form>
  </div>
</div>

<!-- Summary Stats -->
<div class="stats-grid mb-4" style="grid-template-columns:repeat(3,1fr);">
  <div class="stat-card">
    <div class="stat-icon" style="background:rgba(30,58,138,0.1);color:var(--royal-blue);"><i class="fa-solid fa-list-ol"></i></div>
    <div class="stat-info"><span class="stat-value"><?= count($txns) ?></span><span class="stat-label">Movements Found</span></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:rgba(21,128,61,0.1);color:var(--success);"><i class="fa-solid fa-arrow-down"></i></div>
    <div class="stat-info"><span class="stat-value"><?= number_format($totalIn,1) ?></span><span class="stat-label">Total Received / Returned</span></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:rgba(239,68,68,0.1);color:var(--danger);"><i class="fa-solid fa-arrow-up"></i></div>
    <div class="stat-info"><span class="stat-value"><?= number_format($totalOut,1) ?></span><span class="stat-label">Total Issued / Written Off</span></div>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <h3><i class="fa-solid fa-timeline" style="color:var(--royal-blue);"></i> Transaction Ledger</h3>
    <span class="badge badge-light"><?= count($txns) ?> Records</span>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table">
        <thead>
          <tr>
            <th>Date</th>
            <th>Item Code</th>
            <th>Description</th>
            <th>Movement Type</th>
            <th style="text-align:right;">Qty</th>
            <th>Unit</th>
            <th style="text-align:right;">Balance After</th>
            <th>Issued To</th>
            <th>Purpose / Job</th>
            <th>Posted By</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($txns)): ?>
            <tr><td colspan="10" class="text-center py-4 text-muted">No stock movements found for the selected filter.</td></tr>
          <?php else: ?>
            <?php foreach ($txns as $t):
              $isOut = in_array($t["transaction_type"], ["Issued","Written-Off"]);
              $badge = match($t["transaction_type"]) {
                "Received","Returned" => "badge-success",
                "Issued" => "badge-warning",
                "Written-Off","Adjusted" => "badge-secondary",
                default => "badge-light"
              };
            ?>
              <tr>
                <td><small><?= formatDate($t["transaction_date"]) ?></small></td>
                <td><strong style="color:var(--royal-blue);"><?= htmlspecialchars($t["item_code"]) ?></strong></td>
                <td><?= htmlspecialchars($t["item_name"]) ?></td>
                <td><span class="badge <?= $badge ?>"><?= htmlspecialchars($t["transaction_type"]) ?></span></td>
                <td style="text-align:right;font-weight:700;color:<?= $isOut ? "var(--danger)" : "var(--success)" ?>;">
                  <?= $isOut ? "-" : "+" ?><?= number_format($t["quantity"], 1) ?>
                </td>
                <td><small><?= htmlspecialchars($t["unit_of_measure"]) ?></small></td>
                <td style="text-align:right;font-weight:600;"><?= number_format($t["balance_after"], 1) ?></td>
                <td><?= htmlspecialchars($t["issued_to"] ?? "-") ?></td>
                <td><small><?= htmlspecialchars($t["purpose"] ?? "-") ?></small></td>
                <td><small><?= htmlspecialchars($t["full_name"]) ?></small></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include __DIR__ . "/../../includes/footer.php"; ?>
