<?php
require_once __DIR__ . "/../includes/config.php";
$pdo = Database::getInstance()->getConnection();
$pdo->exec("SET FOREIGN_KEY_CHECKS=0");
$tables = ["payment_allocations","payments","invoices","meter_readings","meters","field_inspections","disconnections","nrw_reports","water_production","bulk_meter_readings","bulk_meters","customers","store_items","stock_transactions","assets","maintenance_records","projects","purchase_orders","po_items","suppliers"];
foreach ($tables as $t) { $pdo->exec("TRUNCATE TABLE `$t`"); echo "Cleared $t\n"; }
$pdo->exec("SET FOREIGN_KEY_CHECKS=1");
echo "All tables cleared.\n";
