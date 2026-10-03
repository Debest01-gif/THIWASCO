<?php
$pages = [
    'login.php',
    'index.php',
    // Customers
    'modules/customers/index.php',
    'modules/customers/add.php',
    'modules/customers/disconnected.php',
    'modules/customers/defaulters.php',
    'modules/customers/view.php?id=1',
    // Meters
    'modules/meters/index.php',
    'modules/meters/readings.php',
    'modules/meters/bulk_meters.php',
    'modules/meters/anomalies.php',
    // Billing & Payments
    'modules/billing/index.php',
    'modules/billing/generate.php',
    'modules/billing/tariffs.php',
    'modules/payments/index.php',
    'modules/payments/add.php',
    'modules/payments/mpesa.php',
    // Water Losses / NRW
    'modules/nrw/dashboard.php',
    'modules/nrw/production.php',
    'modules/nrw/analysis.php',
    'modules/nrw/water_balance.php',
    // Revenue Protection
    'modules/revenue_protection/index.php',
    'modules/revenue_protection/inspections.php',
    'modules/revenue_protection/illegal.php',
    'modules/revenue_protection/disconnections.php',
    'modules/revenue_protection/view.php?id=1',
    // Reports
    'modules/reports/executive.php',
    'modules/reports/revenue.php',
    'modules/reports/nrw.php',
    'modules/reports/billing.php',
    'modules/reports/defaulters.php',
    // Settings & Users
    'modules/settings/index.php',
    'modules/users/index.php',
    // Optional Modules
    'optional/stores/index.php',
    'optional/stores/ledger.php',
    'optional/assets/index.php',
    'optional/assets/maintenance.php',
    'optional/procurement/index.php',
    'optional/procurement/lpo_view.php?id=1',
    'optional/projects/index.php',
];
foreach ($pages as $page) {
    $url = 'http://localhost/THIWASCO/' . $page;
    $ctx = stream_context_create(['http'=>['method'=>'GET','timeout'=>8,'ignore_errors'=>true]]);
    $body = @file_get_contents($url, false, $ctx);
    if ($body === false) {
        echo "  ERROR: $page\n";
    } else {
        $code = '';
        foreach ($http_response_header as $h) {
            if (preg_match('/HTTP\/\d\.\d (\d+)/', $h, $m)) $code = $m[1];
        }
        $err = (strpos($body,'Fatal error')!==false || strpos($body,'Parse error')!==false);
        echo ($code==200?'OK ':('HTTP-'.$code.' ')).($err?'[PHP-ERR]':'[CLEAN]')."  $page\n";
    }
}
