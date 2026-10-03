<?php
/**
 * THIWASCO MIS - Printable Customer Water Bill / Invoice
 */
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';

Auth::requirePermission('billing');

$invoiceId = (int)($_GET['id'] ?? 0);
if ($invoiceId <= 0) {
    header('Location: ' . APP_URL . '/modules/billing/index.php');
    exit;
}

$sql = "SELECT i.*, 
               c.account_no, c.full_name, c.phone, c.email, c.physical_address, c.plot_no, c.road_street,
               z.zone_code, z.zone_name,
               m.meter_serial, m.meter_size, m.meter_make, m.meter_type,
               t.tariff_code, t.tariff_name, t.sewer_percent, t.base_charge as tariff_base, t.tiered_rates,
               r.reading_date, r.reading_type
        FROM invoices i
        JOIN customers c ON i.customer_id = c.customer_id
        JOIN zones z ON c.zone_id = z.zone_id
        JOIN meters m ON i.meter_id = m.meter_id
        JOIN tariff_categories t ON c.tariff_id = t.tariff_id
        LEFT JOIN meter_readings r ON i.reading_id = r.reading_id
        WHERE i.invoice_id = ?";
$invoice = db()->fetch($sql, [$invoiceId]);

if (!$invoice) {
    setFlash('danger', 'Invoice not found.');
    header('Location: ' . APP_URL . '/modules/billing/index.php');
    exit;
}

// Payment allocations for this invoice
$payments = db()->fetchAll("SELECT p.receipt_no, p.payment_date, pa.amount_allocated, pm.method_name, p.mpesa_ref
                            FROM payment_allocations pa
                            JOIN payments p ON pa.payment_id = p.payment_id
                            JOIN payment_methods pm ON p.payment_method_id = pm.method_id
                            WHERE pa.invoice_id = ?
                            ORDER BY p.payment_date DESC", [$invoiceId]);

$balanceDue = max(0, (float)$invoice['total_payable'] - (float)$invoice['amount_paid']);

$pageTitle = 'Invoice ' . $invoice['invoice_no'];
$activePage = 'billing_invoices';
$activeSection = 'billing';

include __DIR__ . '/../../includes/header.php';
?>

<div class="no-print">
  <div class="page-header">
    <div class="page-title-wrap">
      <h1><i class="fa-solid fa-file-invoice" style="color:var(--gold);"></i> Water Bill: <?= htmlspecialchars($invoice['invoice_no']) ?></h1>
      <p>Billing Cycle: <?= htmlspecialchars($invoice['billing_month']) ?> | Customer: <?= htmlspecialchars($invoice['full_name']) ?></p>
    </div>
    <div class="page-actions">
      <button onclick="window.print()" class="btn btn-primary">
        <i class="fa-solid fa-print"></i> Print Water Bill
      </button>
      <?php if ($balanceDue > 0): ?>
        <a href="<?= APP_URL ?>/modules/payments/add.php?customer_id=<?= $invoice['customer_id'] ?>&invoice_id=<?= $invoice['invoice_id'] ?>" class="btn btn-success">
          <i class="fa-solid fa-money-bill-wave"></i> Post Payment
        </a>
      <?php endif; ?>
      <a href="<?= APP_URL ?>/modules/billing/index.php" class="btn btn-secondary">
        <i class="fa-solid fa-arrow-left"></i> Back to Invoices
      </a>
    </div>
  </div>
</div>

<!-- PRINTABLE BILL CONTAINER -->
<div class="card" style="max-width: 850px; margin: 0 auto; background: #ffffff; box-shadow: 0 4px 20px rgba(0,0,0,0.08); border: 1px solid var(--border-color); border-radius: 8px; overflow: hidden;">
  
  <!-- BILL HEADER -->
  <div style="background: linear-gradient(135deg, var(--navy) 0%, var(--royal-blue) 100%); color: #ffffff; padding: 2rem; position: relative;">
    <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:1.5rem;">
      <div style="display:flex; align-items:center; gap:1rem;">
        <div style="width:65px; height:65px; border-radius:12px; background:rgba(255,255,255,0.15); display:flex; align-items:center; justify-content:center; border:2px solid var(--gold); font-size:1.8rem; color:var(--gold);">
          <i class="fa-solid fa-droplet"></i>
        </div>
        <div>
          <h2 style="font-size:1.4rem; font-weight:800; color:#ffffff; margin:0; letter-spacing:0.5px;">THIKA WATER & SEWERAGE CO. LTD</h2>
          <div style="color:var(--gold); font-size:0.85rem; font-weight:600; text-transform:uppercase; letter-spacing:1px; margin-top:2px;">(THIWASCO)</div>
          <div style="font-size:0.8rem; opacity:0.85; margin-top:4px;">
            P.O. Box 6103 - 01000, Thika, Kenya | Tel: +254 720 123 456<br>
            Email: info@thiwasco.co.ke | Website: www.thiwasco.co.ke
          </div>
        </div>
      </div>

      <div style="text-align:right;">
        <div style="background:rgba(255,255,255,0.1); padding:0.5rem 1rem; border-radius:6px; border:1px solid rgba(255,255,255,0.2);">
          <span style="font-size:0.75rem; text-transform:uppercase; letter-spacing:1px; display:block; color:var(--gold); font-weight:700;">WATER & SEWERAGE BILL</span>
          <span style="font-size:1.25rem; font-weight:800; letter-spacing:0.5px;"><?= htmlspecialchars($invoice['invoice_no']) ?></span>
        </div>
        <div style="font-size:0.8rem; margin-top:6px; opacity:0.9;">
          <strong>Date:</strong> <?= formatDate($invoice['invoice_date']) ?><br>
          <strong style="color:#fca5a5;">Due Date:</strong> <?= formatDate($invoice['due_date']) ?>
        </div>
      </div>
    </div>
  </div>

  <div class="card-body" style="padding: 2rem;">

    <!-- CUSTOMER & METER DETAILS GRID -->
    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1.5rem; margin-bottom: 2rem; background: var(--bg-light); padding: 1.25rem; border-radius: 8px; border-left: 4px solid var(--royal-blue);">
      <div>
        <h4 style="font-size:0.85rem; text-transform:uppercase; color:var(--royal-blue); margin-bottom:0.75rem; font-weight:700;">
          <i class="fa-solid fa-user"></i> Customer Particulars
        </h4>
        <div style="font-size:0.95rem; font-weight:700; color:var(--navy);"><?= htmlspecialchars($invoice['full_name']) ?></div>
        <div style="font-size:0.85rem; color:var(--text-muted); margin-top:3px;">
          <strong>Account No:</strong> <span style="color:var(--navy); font-weight:600;"><?= htmlspecialchars($invoice['account_no']) ?></span><br>
          <strong>Phone:</strong> <?= htmlspecialchars($invoice['phone']) ?><br>
          <strong>Plot / Location:</strong> <?= htmlspecialchars($invoice['plot_no'] ?? '-') ?>, <?= htmlspecialchars($invoice['road_street'] ?? '-') ?><br>
          <strong>Zone / DMA:</strong> <?= htmlspecialchars($invoice['zone_code'] . ' - ' . $invoice['zone_name']) ?>
        </div>
      </div>

      <div>
        <h4 style="font-size:0.85rem; text-transform:uppercase; color:var(--royal-blue); margin-bottom:0.75rem; font-weight:700;">
          <i class="fa-solid fa-gauge"></i> Meter & Tariff Details
        </h4>
        <div style="font-size:0.85rem; color:var(--text-muted);">
          <strong>Meter Serial:</strong> <span style="color:var(--navy); font-weight:600;"><?= htmlspecialchars($invoice['meter_serial']) ?></span><br>
          <strong>Meter Size / Type:</strong> <?= htmlspecialchars($invoice['meter_size'] ?? '1/2"') ?> (<?= htmlspecialchars($invoice['meter_type']) ?>)<br>
          <strong>Tariff Scheme:</strong> <?= htmlspecialchars($invoice['tariff_name']) ?> (<?= htmlspecialchars($invoice['tariff_code']) ?>)<br>
          <strong>Billing Cycle:</strong> <span style="font-weight:700; color:var(--royal-blue);"><?= htmlspecialchars($invoice['billing_month']) ?></span><br>
          <strong>Reading Type:</strong> <?= htmlspecialchars($invoice['reading_type'] ?? 'Actual') ?>
        </div>
      </div>
    </div>

    <!-- METER CONSUMPTION INDEX TABLE -->
    <div style="margin-bottom: 2rem;">
      <h4 style="font-size:0.9rem; text-transform:uppercase; color:var(--navy); margin-bottom:0.5rem; font-weight:700;">
        <i class="fa-solid fa-faucet-drip"></i> Meter Reading & Consumption
      </h4>
      <table class="table" style="border:1px solid var(--border-color); text-align:center;">
        <thead style="background:var(--bg-light);">
          <tr>
            <th>Previous Reading</th>
            <th>Current Reading</th>
            <th>Multiplier</th>
            <th>Consumption (m³)</th>
            <th>Daily Avg (m³)</th>
          </tr>
        </thead>
        <tbody>
          <tr style="font-size:1.05rem;">
            <td><strong><?= number_format($invoice['previous_reading'], 1) ?></strong></td>
            <td><strong><?= number_format($invoice['current_reading'], 1) ?></strong></td>
            <td>1.0</td>
            <td>
              <span class="badge badge-info" style="font-size:1rem; padding:0.35rem 0.75rem;">
                <?= number_format($invoice['consumption_m3'], 1) ?> m³
              </span>
            </td>
            <td><?= number_format($invoice['consumption_m3'] / 30, 2) ?></td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- CHARGES BREAKDOWN TABLE -->
    <div style="margin-bottom: 2rem;">
      <h4 style="font-size:0.9rem; text-transform:uppercase; color:var(--navy); margin-bottom:0.5rem; font-weight:700;">
        <i class="fa-solid fa-receipt"></i> Statement of Charges
      </h4>
      <table class="table" style="border:1px solid var(--border-color);">
        <thead style="background:var(--bg-light);">
          <tr>
            <th>Description</th>
            <th>Tariff / Basis</th>
            <th class="text-right" style="text-align:right;">Amount (KES)</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td>
              <strong>Water Consumption Charge</strong>
              <div style="font-size:0.75rem; color:var(--text-muted);">
                Calculated on <?= number_format($invoice['consumption_m3'], 1) ?> m³ via graduated tiered tariff
              </div>
            </td>
            <td>Graduated Tiers</td>
            <td style="text-align:right; font-weight:600;"><?= number_format($invoice['water_charge'], 2) ?></td>
          </tr>
          <tr>
            <td>
              <strong>Sewerage Service Fee</strong>
              <div style="font-size:0.75rem; color:var(--text-muted);">
                <?= number_format($invoice['sewer_percent'], 0) ?>% of water consumption charge
              </div>
            </td>
            <td><?= number_format($invoice['sewer_percent'], 0) ?>% of Water</td>
            <td style="text-align:right; font-weight:600;"><?= number_format($invoice['sewer_charge'], 2) ?></td>
          </tr>
          <tr>
            <td>
              <strong>Meter Rent & Fixed Infrastructure Fee</strong>
              <div style="font-size:0.75rem; color:var(--text-muted);">Standard monthly base facility charge</div>
            </td>
            <td>Fixed Monthly</td>
            <td style="text-align:right; font-weight:600;"><?= number_format($invoice['base_charge'], 2) ?></td>
          </tr>
          <?php if ($invoice['penalties'] > 0): ?>
          <tr>
            <td style="color:var(--danger);"><strong>Late Payment Penalty</strong></td>
            <td>Statutory</td>
            <td style="text-align:right; color:var(--danger); font-weight:600;"><?= number_format($invoice['penalties'], 2) ?></td>
          </tr>
          <?php endif; ?>

          <tr style="background:rgba(30,58,138,0.04); border-top:2px solid var(--border-color);">
            <td colspan="2"><strong>TOTAL CURRENT BILL</strong></td>
            <td style="text-align:right; font-weight:700; color:var(--navy); font-size:1.05rem;">
              KES <?= number_format($invoice['total_bill'], 2) ?>
            </td>
          </tr>

          <tr>
            <td colspan="2" style="color:var(--danger);">
              <strong>Arrears Brought Forward (Past Unpaid Balances)</strong>
            </td>
            <td style="text-align:right; color:var(--danger); font-weight:600;">
              KES <?= number_format($invoice['arrears_brought_forward'], 2) ?>
            </td>
          </tr>

          <?php if ($invoice['amount_paid'] > 0): ?>
          <tr>
            <td colspan="2" style="color:var(--success);">
              <strong>Less Payments Credited to this Bill</strong>
            </td>
            <td style="text-align:right; color:var(--success); font-weight:600;">
              - KES <?= number_format($invoice['amount_paid'], 2) ?>
            </td>
          </tr>
          <?php endif; ?>

          <tr style="background:var(--navy); color:#ffffff; font-size:1.15rem;">
            <td colspan="2" style="color:#ffffff; font-weight:800; padding:0.85rem 1rem;">
              NET TOTAL AMOUNT PAYABLE
            </td>
            <td style="text-align:right; font-weight:800; color:var(--gold); padding:0.85rem 1rem;">
              KES <?= number_format($balanceDue, 2) ?>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- PAYMENT INSTRUCTIONS & FOOTER -->
    <div style="background:rgba(234,179,8,0.08); border:1px solid rgba(234,179,8,0.3); border-radius:8px; padding:1.25rem; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem;">
      <div>
        <div style="font-weight:700; color:var(--navy); font-size:0.95rem; margin-bottom:4px;">
          <i class="fa-solid fa-mobile-screen-button" style="color:var(--gold);"></i> How to Pay via M-Pesa:
        </div>
        <ol style="margin:0; padding-left:18px; font-size:0.85rem; color:var(--text-color); line-height:1.5;">
          <li>Go to Lipa na M-Pesa &rarr; <strong>Paybill</strong></li>
          <li>Enter Business No: <strong style="color:var(--navy);">888000</strong></li>
          <li>Account No: <strong style="color:var(--royal-blue);"><?= htmlspecialchars($invoice['account_no']) ?></strong></li>
          <li>Enter Amount: <strong><?= number_format($balanceDue, 2) ?></strong> and PIN</li>
        </ol>
      </div>

      <div style="text-align:center; min-width:180px;">
        <div style="font-size:0.75rem; text-transform:uppercase; color:var(--text-muted); font-weight:600;">Account Status</div>
        <div style="margin-top:4px;">
          <?php if ($balanceDue <= 0): ?>
            <span class="badge badge-success" style="font-size:1.1rem; padding:0.4rem 1rem;">PAID IN FULL</span>
          <?php else: ?>
            <span class="badge badge-danger" style="font-size:1.1rem; padding:0.4rem 1rem;">DUE FOR PAYMENT</span>
          <?php endif; ?>
        </div>
        <div style="font-size:0.75rem; color:var(--danger); margin-top:6px; font-weight:600;">
          Prompt payment avoids KES 1,000 reconnection fee.
        </div>
      </div>
    </div>

    <?php if (!empty($payments)): ?>
      <div style="margin-top:1.5rem;">
        <h5 style="font-size:0.85rem; text-transform:uppercase; color:var(--text-muted); font-weight:700; margin-bottom:0.5rem;">
          Payment Receipts on this Invoice
        </h5>
        <table class="table" style="font-size:0.85rem;">
          <thead>
            <tr>
              <th>Receipt #</th>
              <th>Date</th>
              <th>Method</th>
              <th>Reference</th>
              <th style="text-align:right;">Amount Allocated</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($payments as $p): ?>
              <tr>
                <td><strong><?= htmlspecialchars($p['receipt_no']) ?></strong></td>
                <td><?= formatDate($p['payment_date']) ?></td>
                <td><?= htmlspecialchars($p['method_name']) ?></td>
                <td><?= htmlspecialchars($p['mpesa_ref'] ?? '-') ?></td>
                <td style="text-align:right; font-weight:600; color:var(--success);"><?= formatCurrency($p['amount_allocated']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>

  </div> <!-- /.card-body -->
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
