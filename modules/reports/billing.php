<?php
/**
 * THIWASCO MIS - Monthly Billing & Tariff Distribution Report
 */
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';

Auth::requirePermission('reports');

$pageTitle = 'Monthly Billing Report';
$activePage = 'report_billing';
$activeSection = 'reports';

$month = clean($_GET['billing_month'] ?? date('Y-m'));

// Aggregate billing stats for this month
$totRow = db()->fetch("SELECT 
                        COUNT(*) as invoice_count,
                        COALESCE(SUM(consumption_m3), 0) as total_volume,
                        COALESCE(SUM(water_charge), 0) as total_water,
                        COALESCE(SUM(sewer_charge), 0) as total_sewer,
                        COALESCE(SUM(base_charge), 0) as total_base,
                        COALESCE(SUM(total_bill), 0) as total_bill,
                        COALESCE(SUM(total_payable), 0) as total_payable,
                        COALESCE(SUM(amount_paid), 0) as total_paid
                       FROM invoices WHERE billing_month = ?", [$month]);

// Breakdown by Tariff Category
$tariffBreakdown = db()->fetchAll("SELECT t.tariff_code, t.tariff_name, t.tariff_type,
                                          COUNT(i.invoice_id) as customer_count,
                                          COALESCE(SUM(i.consumption_m3), 0) as billed_m3,
                                          COALESCE(SUM(i.water_charge), 0) as water_charge,
                                          COALESCE(SUM(i.sewer_charge), 0) as sewer_charge,
                                          COALESCE(SUM(i.total_bill), 0) as total_bill
                                   FROM tariff_categories t
                                   JOIN customers c ON t.tariff_id = c.tariff_id
                                   JOIN invoices i ON c.customer_id = i.customer_id AND i.billing_month = ?
                                   GROUP BY t.tariff_id
                                   ORDER BY total_bill DESC", [$month]);

// Breakdown by Zone
$zoneBreakdown = db()->fetchAll("SELECT z.zone_code, z.zone_name,
                                        COUNT(i.invoice_id) as customer_count,
                                        COALESCE(SUM(i.consumption_m3), 0) as billed_m3,
                                        COALESCE(SUM(i.total_bill), 0) as total_bill,
                                        COALESCE(SUM(i.amount_paid), 0) as amount_paid
                                 FROM zones z
                                 JOIN customers c ON z.zone_id = c.zone_id
                                 JOIN invoices i ON c.customer_id = i.customer_id AND i.billing_month = ?
                                 GROUP BY z.zone_id
                                 ORDER BY total_bill DESC", [$month]);

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header no-print">
  <div class="page-title-wrap">
    <h1><i class="fa-solid fa-file-invoice-dollar" style="color:var(--gold);"></i> Monthly Billing & Tariff Report</h1>
    <p>Comprehensive breakdown of water and sewerage billing charges across customer categories and supply zones</p>
  </div>
  <div class="page-actions">
    <button onclick="window.print()" class="btn btn-primary">
      <i class="fa-solid fa-print"></i> Print Billing Report
    </button>
  </div>
</div>

<div class="card mb-4 no-print">
  <div class="card-body" style="padding:1rem 1.25rem;">
    <form method="GET" action="" style="display:flex;align-items:center;gap:1rem;">
      <label class="form-label mb-0" style="font-weight:700;">Billing Month:</label>
      <input type="month" name="billing_month" class="form-control" value="<?= htmlspecialchars($month) ?>" style="width:160px;" onchange="this.form.submit()">
    </form>
  </div>
</div>

<div class="printable-report">
  <!-- TOTALS -->
  <div class="stats-grid mb-4">
    <div class="stat-card">
      <div class="stat-info">
        <span class="stat-label">Total Current Bill</span>
        <span class="stat-value" style="color:var(--royal-blue);"><?= formatCurrency($totRow['total_bill']) ?></span>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-info">
        <span class="stat-label">Billed Volume</span>
        <span class="stat-value" style="color:var(--navy);"><?= number_format($totRow['total_volume'], 1) ?> m³</span>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-info">
        <span class="stat-label">Sewerage Surcharge</span>
        <span class="stat-value" style="color:var(--gold);"><?= formatCurrency($totRow['total_sewer']) ?></span>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-info">
        <span class="stat-label">Customer Accounts Billed</span>
        <span class="stat-value" style="color:var(--success);"><?= number_format($totRow['invoice_count']) ?></span>
      </div>
    </div>
  </div>

  <!-- TARIFF PERFORMANCE TABLE -->
  <div class="card mb-4">
    <div class="card-header">
      <h3><i class="fa-solid fa-tags" style="color:var(--royal-blue);"></i> Billing Distribution by Tariff Category</h3>
    </div>
    <div class="card-body p-0">
      <table class="table" style="font-size:0.9rem;">
        <thead>
          <tr>
            <th>Tariff Code & Name</th>
            <th>Type</th>
            <th style="text-align:right;">Active Accounts</th>
            <th style="text-align:right;">Volume (m³)</th>
            <th style="text-align:right;">Water Charge (KES)</th>
            <th style="text-align:right;">Sewer Charge (KES)</th>
            <th style="text-align:right;">Total Bill (KES)</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($tariffBreakdown as $t): ?>
            <tr>
              <td><strong><?= htmlspecialchars($t['tariff_code']) ?></strong> - <?= htmlspecialchars($t['tariff_name']) ?></td>
              <td><span class="badge badge-light"><?= htmlspecialchars($t['tariff_type']) ?></span></td>
              <td style="text-align:right;"><?= number_format($t['customer_count']) ?></td>
              <td style="text-align:right;"><?= number_format($t['billed_m3'], 1) ?></td>
              <td style="text-align:right;"><?= number_format($t['water_charge'], 2) ?></td>
              <td style="text-align:right;"><?= number_format($t['sewer_charge'], 2) ?></td>
              <td style="text-align:right;font-weight:700;color:var(--royal-blue);"><?= formatCurrency($t['total_bill']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- ZONE BREAKDOWN TABLE -->
  <div class="card">
    <div class="card-header">
      <h3><i class="fa-solid fa-map-location-dot" style="color:var(--gold);"></i> Billing & Collection by Zone</h3>
    </div>
    <div class="card-body p-0">
      <table class="table" style="font-size:0.9rem;">
        <thead>
          <tr>
            <th>Zone</th>
            <th style="text-align:right;">Billed Accounts</th>
            <th style="text-align:right;">Volume (m³)</th>
            <th style="text-align:right;">Total Billed (KES)</th>
            <th style="text-align:right;">Total Paid (KES)</th>
            <th style="text-align:right;">Recovery %</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($zoneBreakdown as $z): 
            $rec = $z['total_bill'] > 0 ? round(($z['amount_paid'] / $z['total_bill']) * 100, 1) : 0;
          ?>
            <tr>
              <td><strong><?= htmlspecialchars($z['zone_code']) ?></strong> - <?= htmlspecialchars($z['zone_name']) ?></td>
              <td style="text-align:right;"><?= number_format($z['customer_count']) ?></td>
              <td style="text-align:right;"><?= number_format($z['billed_m3'], 1) ?></td>
              <td style="text-align:right;font-weight:600;"><?= formatCurrency($z['total_bill']) ?></td>
              <td style="text-align:right;color:var(--success);font-weight:700;"><?= formatCurrency($z['amount_paid']) ?></td>
              <td style="text-align:right;"><strong><?= $rec ?>%</strong></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
