<?php
/**
 * THIWASCO MIS - Aging Debt & Defaulters Ledger Report
 */
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';

Auth::requirePermission('reports');

$pageTitle = 'Debt Aging & Defaulters Report';
$activePage = 'report_defaulters';
$activeSection = 'reports';

$zoneId = !empty($_GET['zone_id']) ? (int)$_GET['zone_id'] : '';
$zones = db()->fetchAll("SELECT * FROM zones WHERE is_active = 1 ORDER BY zone_name ASC");

// Total Debt Stats
$totDebtRow = db()->fetch("SELECT 
                            COUNT(*) as defaulters_count,
                            COALESCE(SUM(balance), 0) as total_debt,
                            COALESCE(AVG(balance), 0) as avg_debt,
                            COALESCE(MAX(balance), 0) as max_debt
                           FROM customers WHERE balance > 0");

// Top 20 Defaulters
$where = ["c.balance > 0"];
$params = [];
if ($zoneId) {
    $where[] = "c.zone_id = ?";
    $params[] = $zoneId;
}
$whereSql = implode(" AND ", $where);

$topDefaulters = db()->fetchAll("SELECT c.*, z.zone_code, z.zone_name,
                                        (SELECT MAX(payment_date) FROM payments WHERE customer_id = c.customer_id) as last_payment_date
                                 FROM customers c
                                 JOIN zones z ON c.zone_id = z.zone_id
                                 WHERE {$whereSql}
                                 ORDER BY c.balance DESC LIMIT 25", $params);

// Zonal Debt Aging
$zonalDebt = db()->fetchAll("SELECT z.zone_code, z.zone_name,
                                    COUNT(c.customer_id) as debtors_count,
                                    COALESCE(SUM(c.balance), 0) as zone_debt
                             FROM zones z
                             LEFT JOIN customers c ON z.zone_id = c.zone_id AND c.balance > 0
                             GROUP BY z.zone_id
                             ORDER BY zone_debt DESC");

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header no-print">
  <div class="page-title-wrap">
    <h1><i class="fa-solid fa-user-xmark" style="color:var(--danger);"></i> Debt Aging & Defaulters Ledger</h1>
    <p>Institutional debt recovery matrix, aging arrears distribution, and top 25 high-exposure accounts</p>
  </div>
  <div class="page-actions">
    <button onclick="window.print()" class="btn btn-primary">
      <i class="fa-solid fa-print"></i> Print Debt Ledger
    </button>
  </div>
</div>

<div class="card mb-4 no-print">
  <div class="card-body" style="padding:1rem 1.25rem;">
    <form method="GET" action="" style="display:flex;align-items:center;gap:1rem;">
      <label class="form-label mb-0" style="font-weight:700;">Filter Zone:</label>
      <select name="zone_id" class="form-control" style="width:200px;" onchange="this.form.submit()">
        <option value="">All Zones</option>
        <?php foreach ($zones as $z): ?>
          <option value="<?= $z['zone_id'] ?>" <?= $zoneId == $z['zone_id'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($z['zone_code'] . ' - ' . $z['zone_name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </form>
  </div>
</div>

<div class="printable-report">
  <!-- TOTAL METRICS -->
  <div class="stats-grid mb-4">
    <div class="stat-card">
      <div class="stat-info">
        <span class="stat-label">Total Outstanding Arrears</span>
        <span class="stat-value" style="color:var(--danger);"><?= formatCurrency($totDebtRow['total_debt']) ?></span>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-info">
        <span class="stat-label">Defaulting Accounts</span>
        <span class="stat-value" style="color:var(--navy);"><?= number_format($totDebtRow['defaulters_count']) ?></span>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-info">
        <span class="stat-label">Average Balance / Defaulter</span>
        <span class="stat-value" style="color:var(--gold);"><?= formatCurrency($totDebtRow['avg_debt']) ?></span>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-info">
        <span class="stat-label">Highest Single Balance</span>
        <span class="stat-value" style="color:#b91c1c;"><?= formatCurrency($totDebtRow['max_debt']) ?></span>
      </div>
    </div>
  </div>

  <!-- ZONAL DEBT DISTRIBUTION -->
  <div class="card mb-4">
    <div class="card-header">
      <h3><i class="fa-solid fa-map" style="color:var(--royal-blue);"></i> Arrears Distribution by Distribution Zone</h3>
    </div>
    <div class="card-body p-0">
      <table class="table" style="font-size:0.9rem;">
        <thead>
          <tr>
            <th>Zone</th>
            <th>Debtors Count</th>
            <th style="text-align:right;">Outstanding Debt (KES)</th>
            <th style="text-align:right;">Debt Share %</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($zonalDebt as $zd): 
            $pct = $totDebtRow['total_debt'] > 0 ? round(($zd['zone_debt'] / $totDebtRow['total_debt']) * 100, 1) : 0;
          ?>
            <tr>
              <td><strong><?= htmlspecialchars($zd['zone_code']) ?></strong> - <?= htmlspecialchars($zd['zone_name']) ?></td>
              <td><?= number_format($zd['debtors_count']) ?></td>
              <td style="text-align:right;font-weight:700;color:var(--danger);"><?= formatCurrency($zd['zone_debt']) ?></td>
              <td style="text-align:right;"><strong><?= $pct ?>%</strong></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- TOP 25 DEBTORS TABLE -->
  <div class="card">
    <div class="card-header">
      <h3><i class="fa-solid fa-list-ol" style="color:var(--danger);"></i> Top 25 Highest Outstanding Debtor Accounts</h3>
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table" style="font-size:0.85rem;">
          <thead>
            <tr>
              <th>Rank</th>
              <th>Customer Name</th>
              <th>Account #</th>
              <th>Phone</th>
              <th>Zone</th>
              <th>Status</th>
              <th>Last Payment</th>
              <th style="text-align:right;">Arrears (KES)</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($topDefaulters as $idx => $d): ?>
              <tr>
                <td><strong>#<?= $idx + 1 ?></strong></td>
                <td>
                  <a href="<?= APP_URL ?>/modules/customers/view.php?id=<?= $d['customer_id'] ?>" style="font-weight:700;color:var(--navy);">
                    <?= htmlspecialchars($d['full_name']) ?>
                  </a>
                </td>
                <td><code><?= htmlspecialchars($d['account_no']) ?></code></td>
                <td><?= htmlspecialchars($d['phone']) ?></td>
                <td><span class="badge badge-light"><?= htmlspecialchars($d['zone_code']) ?></span></td>
                <td><span class="badge badge-warning"><?= htmlspecialchars($d['status']) ?></span></td>
                <td><?= formatDate($d['last_payment_date']) ?></td>
                <td style="text-align:right;font-weight:800;font-size:1.05rem;color:var(--danger);">
                  <?= number_format($d['balance'], 2) ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
