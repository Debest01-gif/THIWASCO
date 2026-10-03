<?php
/**
 * THIWASCO MIS - Comprehensive Demonstration Sample Data Seeder
 * Run once to populate realistic production-grade data for all modules
 */
require_once __DIR__ . '/../includes/config.php';

try {
    $pdo = Database::getInstance()->getConnection();
    $pdo->beginTransaction();

    echo "Seeding THIWASCO demonstration sample data...\n";

    // 1. ZONES & TARIFFS check
    $zones = $pdo->query("SELECT zone_id, zone_code FROM zones ORDER BY zone_id ASC")->fetchAll(PDO::FETCH_KEY_PAIR);
    $tariffs = $pdo->query("SELECT tariff_id, tariff_code FROM tariff_categories ORDER BY tariff_id ASC")->fetchAll(PDO::FETCH_KEY_PAIR);

    $z1 = array_search('ZN-001', $zones) ?: 1;
    $z2 = array_search('ZN-002', $zones) ?: 2;
    $z3 = array_search('ZN-003', $zones) ?: 3;
    $z4 = array_search('ZN-004', $zones) ?: 4;
    $z5 = array_search('ZN-005', $zones) ?: 5;

    $tDom = array_search('DOM-01', $tariffs) ?: 1;
    $tCom = array_search('COM-01', $tariffs) ?: 2;
    $tInd = array_search('IND-01', $tariffs) ?: 3;
    $tInst = array_search('INST-01', $tariffs) ?: 4;
    $tKiosk = array_search('KIOSK-01', $tariffs) ?: 5;

    // 2. CUSTOMERS
    $sampleCustomers = [
        ['THI-ZN001-0001', $z1, $tDom, 'Individual', 'David Mwangi Kariuki', '24189012', '0722100201', 'david.mwangi@gmail.com', 'Section 9 Plot 45', 'Plot 45', 'Commercial Street', 1850.00, 'Active'],
        ['THI-ZN001-0002', $z1, $tCom, 'Company', 'Cravers Grill & Hotel Ltd', 'CPR/2018/891', '0720445566', 'accounts@cravers.co.ke', 'Uhuru Street, Central Business District', 'Plot 12/B', 'Uhuru Street', 12400.00, 'Active'],
        ['THI-ZN001-0003', $z1, $tDom, 'Individual', 'Grace Njeri Kamau', '18902341', '0723891044', 'gnjeri@yahoo.com', 'Section 2, House 14', 'Plot 14', 'Haile Selassie Rd', 0.00, 'Active'],
        ['THI-ZN002-0001', $z2, $tInd, 'Company', 'Bidco Africa Limited', 'CPR/1991/001', '0700112233', 'utilities@bidco-africa.com', 'Industrial Area, Garissa Road', 'LR 4953/12', 'Garissa Road', 85000.00, 'Active'],
        ['THI-ZN002-0002', $z2, $tInd, 'Company', 'Bakex Millers Limited', 'CPR/1984/204', '0722334455', 'maintenance@bakex.co.ke', 'Station Road, Industrial Zone', 'Plot 88', 'Station Road', 42000.00, 'Active'],
        ['THI-ZN002-0003', $z2, $tCom, 'Company', 'TotalEnergies Thika Service Station', 'CPR/2005/712', '0721998877', 'dealer.thika@totalenergies.ke', 'Garissa Roundabout', 'Plot 5', 'Garissa Road', 4500.00, 'Active'],
        ['THI-ZN003-0001', $z3, $tDom, 'Individual', 'Peter Njoroge Githinji', '29481023', '0711445566', 'peter.githinji@outlook.com', 'Landless Estate, Phase 2, Court 4', 'Plot 204', 'Acacia Avenue', 2100.00, 'Active'],
        ['THI-ZN003-0002', $z3, $tInst, 'Institution', 'Chania High School', 'SCH/1964/01', '0722778899', 'chaniahigh@education.go.ke', 'Chania River Bank off Workshop Rd', 'LR 209/4', 'Workshop Road', 28500.00, 'Active'],
        ['THI-ZN003-0003', $z3, $tDom, 'Individual', 'Mercy Wambui Mwaura', '31209845', '0724556677', 'mercy.wambui@gmail.com', 'Kiganjo Corner House 3', 'Plot 67', 'Kiganjo Road', 850.00, 'Active'],
        ['THI-ZN004-0001', $z4, $tDom, 'Individual', 'Joseph Ochieng Otieno', '22415678', '0733889900', 'jochieng@gmail.com', 'Makongeni Phase 4, House B12', 'Plot 310', 'Posta Road', 3400.00, 'Defaulter'],
        ['THI-ZN004-0002', $z4, $tInst, 'Institution', 'Mount Kenya University Main Campus', 'MKU/2008/001', '0709153000', 'facilities@mku.ac.ke', 'General Kago Road', 'LR 502/10', 'General Kago Road', 145000.00, 'Active'],
        ['THI-ZN004-0003', $z4, $tDom, 'Individual', 'Jane Muthoni Gitau', '27891234', '0720123987', 'jmuthoni@gmail.com', 'Hospital Ward Estate, Block C', 'Plot 15', 'Hospital Road', 6200.00, 'Disconnected'],
        ['THI-ZN005-0001', $z5, $tKiosk, 'Kiosk', 'Kiandutu Community Water Kiosk 1', 'CBO/2016/44', '0710223344', 'kiandutukiosk1@gmail.com', 'Kiandutu Informal Settlement Center', 'Site K-1', 'Main Pathway', 1200.00, 'Active'],
        ['THI-ZN005-0002', $z5, $tKiosk, 'Kiosk', 'Gatuanyaga Water Dispensary', 'CBO/2019/12', '0728990011', 'gatuanyagawater@gmail.com', 'Gatuanyaga Market Junction', 'Site K-2', 'Market Road', 450.00, 'Active'],
        ['THI-ZN005-0003', $z5, $tDom, 'Individual', 'Stephen Ndung\'u Kimani', '33491029', '0726112233', 'skimani@gmail.com', 'Munyu Sub-location Farm 12', 'Plot 120', 'Munyu Link', 8900.00, 'Defaulter']
    ];

    $custIds = [];
    $stmtC = $pdo->prepare("INSERT INTO customers (account_no, zone_id, tariff_id, customer_type, full_name, id_number, phone, email, physical_address, plot_no, road_street, balance, status, connection_date, connection_size, deposit_paid, created_by)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, '2024-01-15', '1/2\"', 2500.00, 1)");

    foreach ($sampleCustomers as $sc) {
        $stmtC->execute([$sc[0], $sc[1], $sc[2], $sc[3], $sc[4], $sc[5], $sc[6], $sc[7], $sc[8], $sc[9], $sc[10], $sc[11], $sc[12]]);
        $custIds[$sc[0]] = $pdo->lastInsertId();
    }
    echo "Seeded " . count($sampleCustomers) . " customers.\n";

    // 3. METERS & READINGS
    $stmtM = $pdo->prepare("INSERT INTO meters (customer_id, meter_serial, meter_make, meter_model, meter_size, meter_type, installation_date, seal_no, initial_reading, current_reading, status, installed_by)
                            VALUES (?, ?, ?, ?, ?, ?, '2024-01-15', ?, ?, ?, 'Active', 1)");
    $stmtR = $pdo->prepare("INSERT INTO meter_readings (meter_id, customer_id, read_by, billing_month, reading_date, previous_reading, current_reading, reading_type, anomaly_flag, anomaly_reason)
                            VALUES (?, ?, 1, ?, ?, ?, ?, 'Actual', ?, ?)");

    $meterIds = [];
    $serialIndex = 1001;

    foreach ($custIds as $accNo => $cId) {
        $serial = 'THW-M-' . $serialIndex;
        $seal = 'SL-' . ($serialIndex + 4000);
        $init = rand(50, 200);
        $m1 = $init + rand(15, 30);
        $m2 = $m1 + rand(15, 35);
        $m3 = $m2 + rand(12, 40);

        // One anomaly
        $anomalyFlag = ($serialIndex === 1010) ? 1 : 0;
        $anomalyReason = ($serialIndex === 1010) ? 'Spike >3x average consumption' : null;
        if ($serialIndex === 1010) $m3 = $m2 + 180; // spike!

        $stmtM->execute([$cId, $serial, 'Elster Kent', 'V100', '1/2"', 'Analogue', $seal, $init, $m3]);
        $mId = $pdo->lastInsertId();
        $meterIds[$cId] = ['meter_id' => $mId, 'curr' => $m3, 'm2' => $m2, 'm1' => $m1, 'init' => $init];

        // Readings for August, Sept, Oct 2026
        $stmtR->execute([$mId, $cId, '2026-08', '2026-08-28', $init, $m1, 0, null]);
        $stmtR->execute([$mId, $cId, '2026-09', '2026-09-28', $m1, $m2, 0, null]);
        $stmtR->execute([$mId, $cId, '2026-10', '2026-10-02', $m2, $m3, $anomalyFlag, $anomalyReason]);

        $serialIndex++;
    }
    echo "Seeded meters and 3-month historical meter readings.\n";

    // 4. INVOICES
    $stmtI = $pdo->prepare("INSERT INTO invoices (invoice_no, customer_id, meter_id, billing_month, invoice_date, due_date, previous_reading, current_reading, consumption_m3, water_charge, sewer_charge, base_charge, total_bill, arrears_brought_forward, total_payable, amount_paid, status, generated_by)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");

    $invCounter = 1001;
    foreach ($custIds as $accNo => $cId) {
        $mInfo = $meterIds[$cId];
        $cons = $mInfo['m2'] - $mInfo['m1'];
        $water = $cons * 58.00;
        $sewer = round($water * 0.6, 2);
        $base = 150.00;
        $totalBill = $water + $sewer + $base;
        $payable = $totalBill;
        $paid = ($invCounter % 3 === 0) ? $payable : (($invCounter % 3 === 1) ? round($payable * 0.5, 2) : 0);
        $status = ($paid >= $payable) ? 'Paid' : (($paid > 0) ? 'Partial' : 'Unpaid');

        $invNo = 'INV202609' . str_pad($invCounter, 4, '0', STR_PAD_LEFT);
        $stmtI->execute([$invNo, $cId, $mInfo['meter_id'], '2026-09', '2026-09-30', '2026-10-15', $mInfo['m1'], $mInfo['m2'], $cons, $water, $sewer, $base, $totalBill, 0.00, $payable, $paid, $status]);
        $invCounter++;
    }
    echo "Seeded monthly customer invoices.\n";

    // 5. PAYMENTS & RECEIPTS
    $stmtP = $pdo->prepare("INSERT INTO payments (receipt_no, customer_id, received_by, payment_method_id, payment_date, amount, mpesa_ref, notes)
                            VALUES (?, ?, 1, 2, '2026-10-01', ?, ?, 'M-Pesa Paybill instant settlement')");
    $rcptCounter = 101;
    foreach ($custIds as $accNo => $cId) {
        if ($rcptCounter % 2 === 0) {
            $rcptNo = 'RCP202610' . str_pad($rcptCounter, 4, '0', STR_PAD_LEFT);
            $mpesaCode = 'QKH' . rand(1000000, 9999999);
            $amount = rand(1000, 3500);
            $stmtP->execute([$rcptNo, $cId, $amount, $mpesaCode]);
        }
        $rcptCounter++;
    }
    echo "Seeded payment receipts.\n";

    // 6. WATER PRODUCTION
    $prodSources = [
        ['Chania River Water Treatment Works (Phase 1)', 'River', 45000.0, 720.0, 18500.0],
        ['Thika Dam Main Water Treatment Plant', 'Dam', 65000.0, 720.0, 24000.0],
        ['Maryhill High-Yield Borehole 1', 'Borehole', 12000.0, 480.0, 6800.0],
        ['Garissa Road Industrial Borehole 2', 'Borehole', 15000.0, 520.0, 8200.0],
        ['Ndula Natural Springs Intake', 'Spring', 8000.0, 0.0, 0.0]
    ];
    $stmtPr = $pdo->prepare("INSERT INTO water_production (source_name, source_type, record_date, volume_m3, pumping_hours, energy_kwh, recorded_by, notes)
                             VALUES (?, ?, '2026-09-30', ?, ?, ?, 1, 'Monthly verified abstraction log')");
    foreach ($prodSources as $ps) {
        $stmtPr->execute([$ps[0], $ps[1], $ps[2], $ps[3], $ps[4]]);
    }
    echo "Seeded water abstraction & production.\n";

    // 7. NRW REPORTS
    $pdo->prepare("INSERT INTO nrw_reports (zone_id, report_period, total_production_m3, total_billed_m3, nrw_percent, commercial_losses_m3, physical_losses_m3, real_losses_m3, apparent_losses_m3, unbilled_authorised_m3, notes, generated_by)
                   VALUES (NULL, '2026-09', 145000.00, 95500.00, 34.14, 21500.00, 28000.00, 28000.00, 21500.00, 2900.00, 'Audited company-wide September 2026 Water Balance', 1)")
        ->execute();

    // 8. FIELD INSPECTIONS — use actual seeded IDs
    $custIdList = array_values($custIds);
    $cid1 = $custIdList[0] ?? null;
    $cid10 = $custIdList[9] ?? null;
    $mid1 = isset($meterIds[$cid1]) ? $meterIds[$cid1]['meter_id'] : null;
    $mid10 = isset($meterIds[$cid10]) ? $meterIds[$cid10]['meter_id'] : null;

    $sampleInspections = [
        ['INSP2026100001', $cid1, $mid1, $z1, 1, '2026-10-01', 'Meter serial THW-M-1001 anti-tamper seal SL-5001 found cut with magnets placed on casing dial to retard counting mechanism.', 65.0, 4875.00, 'Open', 'Meter confiscated for laboratory bench testing, temporary straight connector clamped', 30000.00],
        ['INSP2026100002', $cid10, $mid10, $z4, 3, '2026-10-02', 'Underground 25mm PVC pipe tee-off tapped before the water meter directing unmetered flow to swimming pool and irrigation tank.', 240.0, 18000.00, 'Under Investigation', 'Excavated illicit junction, severed unauthorized line, served owner notice to show cause', 50000.00],
        ['INSP2026100003', null, null, $z5, 2, '2026-10-02', 'Direct illegal abstraction from 110mm distribution main in Kiandutu informal settlement using non-standard saddle clamp and leaking hose.', 120.0, 9000.00, 'Resolved', 'Confiscated 150m black polythene pipe and removed unapproved saddle clamp from main', 15000.00]
    ];
    $stmtInsp = $pdo->prepare("INSERT INTO field_inspections (inspection_ref, customer_id, meter_id, zone_id, inspection_type_id, inspected_by, inspection_date, findings, estimated_loss_m3, estimated_revenue_loss, status, action_taken, penalty_amount)
                               VALUES (?, ?, ?, ?, ?, 1, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($sampleInspections as $si) {
        $stmtInsp->execute($si);
    }
    echo "Seeded revenue protection inspections.\n";

    // 9. STORES ITEMS
    $sampleItems = [
        ['PIP-HDPE-025', '25mm PN16 HDPE Water Pipe (100m Roll)', 'Pipes', 'Rolls', 45, 10, 4800.00, 'Main Yard Rack A'],
        ['PIP-HDPE-050', '50mm PN16 HDPE Water Pipe (100m Roll)', 'Pipes', 'Rolls', 18, 5, 14500.00, 'Main Yard Rack B'],
        ['MTR-DOM-015', '15mm (1/2") Volumetric Rotary Piston Water Meter', 'Meters', 'Pieces', 120, 25, 2200.00, 'Stores Bay 3'],
        ['MTR-BULK-080', '80mm (3") Flanged Electromagnetic Bulk Meter', 'Meters', 'Pieces', 4, 2, 85000.00, 'High Value Secure Store'],
        ['VLV-GATE-050', '50mm Cast Iron Resilient Seated Gate Valve', 'Fittings', 'Pieces', 24, 6, 6800.00, 'Rack C1'],
        ['CHM-CHL-001', 'Granular Chlorine 65% (45kg Drum)', 'Chemicals', 'Drums', 8, 12, 18500.00, 'Chemical Shed']
    ];
    $stmtSt = $pdo->prepare("INSERT INTO store_items (item_code, item_name, category, unit_of_measure, current_stock, reorder_level, unit_cost, location)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($sampleItems as $sit) {
        $stmtSt->execute($sit);
    }
    echo "Seeded store inventory catalog.\n";

    // 10. FIXED ASSETS
    $sampleAssets = [
        ['PMP-CH-01', 'Chania Plant 110kW High Lift Centrifugal Pump 1', 'Pump', 'Chania Treatment Works', 2800000.00, 2400000.00, 'Good', 'Active'],
        ['PMP-CH-02', 'Chania Plant 110kW High Lift Centrifugal Pump 2', 'Pump', 'Chania Treatment Works', 2800000.00, 2550000.00, 'Excellent', 'Active'],
        ['RES-SECT9-01', 'Section 9 Elevated Ground Storage Reservoir (5,000 m³)', 'Tank', 'Section 9 Tank Farm', 45000000.00, 42000000.00, 'Good', 'Active'],
        ['BOR-MARY-01', 'Maryhill Deep Aquifer Production Borehole & Submersible', 'Borehole', 'Maryhill Compound', 4500000.00, 3900000.00, 'Good', 'Active'],
        ['VEH-KDH-890A', 'Isuzu D-Max 4x4 Quick Response Leak Repair Vehicle', 'Vehicle', 'Transport Pool Yard', 3600000.00, 2800000.00, 'Good', 'Active']
    ];
    $stmtAst = $pdo->prepare("INSERT INTO assets (asset_code, asset_name, asset_type, location, purchase_cost, current_value, `condition`, status, purchase_date)
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?, '2023-06-15')");
    foreach ($sampleAssets as $ast) {
        $stmtAst->execute($ast);
    }
    echo "Seeded engineering fixed assets.\n";

    // 11. PROJECTS
    $sampleProjects = [
        ['PRJ-ZN03-EXT', 'Section 9 to Kiganjo 160mm HDPE Water Main Expansion', $z3, 8500000.00, '2026-06-01', '2026-11-30', 'Athi Water Works JV', 68.0, 'Active'],
        ['PRJ-DMA-CENT', 'Town Centre Zone 1 DMA Smart Metering & PRV Chambers', $z1, 5200000.00, '2026-07-15', '2026-10-31', 'In-House Technical Teams', 85.0, 'Active'],
        ['PRJ-KIAND-MET', 'Kiandutu Informal Settlement Prepaid Kiosk Network Expansion', $z5, 3400000.00, '2026-08-01', '2026-12-15', 'Water & Sanitation for Urban Poor (WSUP)', 42.0, 'Active']
    ];
    $stmtPrj = $pdo->prepare("INSERT INTO projects (project_code, project_name, zone_id, budget, start_date, expected_end, contractor, progress_percent, status, created_by)
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");
    foreach ($sampleProjects as $prj) {
        $stmtPrj->execute($prj);
    }
    echo "Seeded capital projects.\n";

    // 12. SUPPLIERS & LPO
    $sampleSuppliers = [
        ['SUP-001', 'Davis & Shirtliff Limited', 'Daniel Ndegwa', '0711079000', 'headoffice@dayliff.com', 'P000600123Z'],
        ['SUP-002', 'Doshi & Company Hardware Ltd', 'Rajesh Patel', '0722204567', 'sales@doshi.com', 'P051189445A'],
        ['SUP-003', 'Eslon Plastics Kenya Ltd', 'Martin Wekesa', '0720334411', 'orders@eslon.co.ke', 'P051299887B']
    ];
    $stmtSup = $pdo->prepare("INSERT INTO suppliers (supplier_code, company_name, contact_person, phone, email, pin_no)
                             VALUES (?, ?, ?, ?, ?, ?)");
    foreach ($sampleSuppliers as $sup) {
        $stmtSup->execute($sup);
    }
    $pdo->prepare("INSERT INTO purchase_orders (lpo_no, supplier_id, order_date, expected_delivery, total_amount, status, created_by, notes)
                   VALUES ('LPO2026090001', 1, '2026-09-18', '2026-10-05', 485000.00, 'Approved', 1, 'Emergency supply of 15mm Kent meters and non-return valves')")
        ->execute();
    echo "Seeded suppliers and purchase orders.\n";

    $pdo->commit();
    echo "\n=== ALL SAMPLE DATA SEEDED SUCCESSFULLY! ===\n";

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    echo "SEEDING ERROR: " . $e->getMessage() . "\n";
}
