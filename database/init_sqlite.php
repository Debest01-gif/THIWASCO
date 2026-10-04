<?php
/**
 * THIWASCO - Complete SQLite Database Initializer & Seeder
 * Converts MySQL schema to SQLite DDL with complete sample data.
 */

function initSqliteDatabase(?PDO $pdo = null) {
    $sqliteFile = __DIR__ . '/thiwasco.sqlite';
    
    if (!$pdo) {
        $pdo = new PDO('sqlite:' . $sqliteFile, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
    }

    $pdo->exec("PRAGMA foreign_keys = OFF;");
    $pdo->exec("PRAGMA journal_mode = WAL;");

    $schema = <<<'SQL'
CREATE TABLE IF NOT EXISTS roles (
    role_id INTEGER PRIMARY KEY AUTOINCREMENT,
    role_name TEXT NOT NULL UNIQUE,
    role_description TEXT,
    permissions TEXT,
    is_active INTEGER DEFAULT 1,
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS users (
    user_id INTEGER PRIMARY KEY AUTOINCREMENT,
    role_id INTEGER NOT NULL,
    employee_no TEXT,
    full_name TEXT NOT NULL,
    email TEXT NOT NULL UNIQUE,
    phone TEXT,
    username TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    profile_photo TEXT,
    zone_assigned TEXT,
    is_active INTEGER DEFAULT 1,
    last_login TEXT,
    login_attempts INTEGER DEFAULT 0,
    locked_until TEXT,
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at TEXT NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY (role_id) REFERENCES roles(role_id)
);

CREATE TABLE IF NOT EXISTS audit_logs (
    log_id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER,
    action TEXT NOT NULL,
    module TEXT NOT NULL,
    record_id INTEGER,
    old_values TEXT,
    new_values TEXT,
    ip_address TEXT,
    user_agent TEXT,
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS zones (
    zone_id INTEGER PRIMARY KEY AUTOINCREMENT,
    zone_code TEXT NOT NULL UNIQUE,
    zone_name TEXT NOT NULL,
    zone_type TEXT DEFAULT 'Zone',
    parent_zone_id INTEGER,
    area_description TEXT,
    gps_boundary TEXT,
    expected_connections INTEGER DEFAULT 0,
    is_active INTEGER DEFAULT 1,
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS bulk_meters (
    bulk_meter_id INTEGER PRIMARY KEY AUTOINCREMENT,
    zone_id INTEGER NOT NULL,
    meter_serial TEXT NOT NULL,
    meter_name TEXT NOT NULL,
    meter_size TEXT,
    installation_date TEXT,
    location_description TEXT,
    gps_lat REAL,
    gps_lng REAL,
    is_active INTEGER DEFAULT 1,
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY (zone_id) REFERENCES zones(zone_id)
);

CREATE TABLE IF NOT EXISTS bulk_meter_readings (
    reading_id INTEGER PRIMARY KEY AUTOINCREMENT,
    bulk_meter_id INTEGER NOT NULL,
    read_by INTEGER NOT NULL,
    reading_date TEXT NOT NULL,
    previous_reading REAL DEFAULT 0.00,
    current_reading REAL NOT NULL,
    notes TEXT,
    photo TEXT,
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY (bulk_meter_id) REFERENCES bulk_meters(bulk_meter_id),
    FOREIGN KEY (read_by) REFERENCES users(user_id)
);

CREATE TABLE IF NOT EXISTS tariff_categories (
    tariff_id INTEGER PRIMARY KEY AUTOINCREMENT,
    tariff_code TEXT NOT NULL UNIQUE,
    tariff_name TEXT NOT NULL,
    tariff_type TEXT DEFAULT 'Domestic',
    base_charge REAL DEFAULT 0.00,
    rate_per_m3 REAL DEFAULT 0.00,
    sewer_percent REAL DEFAULT 0.00,
    min_charge REAL DEFAULT 0.00,
    tiered_rates TEXT,
    is_active INTEGER DEFAULT 1,
    effective_from TEXT NOT NULL,
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS customers (
    customer_id INTEGER PRIMARY KEY AUTOINCREMENT,
    account_no TEXT NOT NULL UNIQUE,
    zone_id INTEGER NOT NULL,
    tariff_id INTEGER NOT NULL,
    customer_type TEXT DEFAULT 'Individual',
    full_name TEXT NOT NULL,
    id_number TEXT,
    phone TEXT NOT NULL,
    phone_alt TEXT,
    email TEXT,
    physical_address TEXT,
    plot_no TEXT,
    road_street TEXT,
    connection_date TEXT,
    connection_size TEXT,
    gps_lat REAL,
    gps_lng REAL,
    status TEXT DEFAULT 'Active',
    balance REAL DEFAULT 0.00,
    deposit_paid REAL DEFAULT 0.00,
    notes TEXT,
    created_by INTEGER,
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at TEXT NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY (zone_id) REFERENCES zones(zone_id),
    FOREIGN KEY (tariff_id) REFERENCES tariff_categories(tariff_id)
);

CREATE TABLE IF NOT EXISTS meters (
    meter_id INTEGER PRIMARY KEY AUTOINCREMENT,
    customer_id INTEGER NOT NULL,
    meter_serial TEXT NOT NULL UNIQUE,
    meter_make TEXT,
    meter_model TEXT,
    meter_size TEXT,
    meter_type TEXT DEFAULT 'Analogue',
    installation_date TEXT,
    seal_no TEXT,
    initial_reading REAL DEFAULT 0.00,
    current_reading REAL DEFAULT 0.00,
    gps_lat REAL,
    gps_lng REAL,
    meter_photo TEXT,
    status TEXT DEFAULT 'Active',
    last_read_date TEXT,
    installed_by INTEGER,
    removed_date TEXT,
    removal_reason TEXT,
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at TEXT NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY (customer_id) REFERENCES customers(customer_id)
);

CREATE TABLE IF NOT EXISTS meter_readings (
    reading_id INTEGER PRIMARY KEY AUTOINCREMENT,
    meter_id INTEGER NOT NULL,
    customer_id INTEGER NOT NULL,
    read_by INTEGER NOT NULL,
    billing_month TEXT NOT NULL,
    reading_date TEXT NOT NULL,
    previous_reading REAL NOT NULL DEFAULT 0.00,
    current_reading REAL NOT NULL,
    reading_type TEXT DEFAULT 'Actual',
    anomaly_flag INTEGER DEFAULT 0,
    anomaly_reason TEXT,
    photo TEXT,
    notes TEXT,
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    UNIQUE (meter_id, billing_month),
    FOREIGN KEY (meter_id) REFERENCES meters(meter_id),
    FOREIGN KEY (customer_id) REFERENCES customers(customer_id),
    FOREIGN KEY (read_by) REFERENCES users(user_id)
);

CREATE TABLE IF NOT EXISTS invoices (
    invoice_id INTEGER PRIMARY KEY AUTOINCREMENT,
    invoice_no TEXT NOT NULL UNIQUE,
    customer_id INTEGER NOT NULL,
    meter_id INTEGER NOT NULL,
    reading_id INTEGER,
    billing_month TEXT NOT NULL,
    invoice_date TEXT NOT NULL,
    due_date TEXT NOT NULL,
    previous_reading REAL DEFAULT 0.00,
    current_reading REAL DEFAULT 0.00,
    consumption_m3 REAL DEFAULT 0.00,
    water_charge REAL DEFAULT 0.00,
    sewer_charge REAL DEFAULT 0.00,
    base_charge REAL DEFAULT 0.00,
    penalties REAL DEFAULT 0.00,
    other_charges REAL DEFAULT 0.00,
    vat_amount REAL DEFAULT 0.00,
    total_bill REAL NOT NULL,
    arrears_brought_forward REAL DEFAULT 0.00,
    total_payable REAL NOT NULL,
    amount_paid REAL DEFAULT 0.00,
    status TEXT DEFAULT 'Unpaid',
    generated_by INTEGER,
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at TEXT NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY (customer_id) REFERENCES customers(customer_id),
    FOREIGN KEY (meter_id) REFERENCES meters(meter_id)
);

CREATE TABLE IF NOT EXISTS payment_methods (
    method_id INTEGER PRIMARY KEY AUTOINCREMENT,
    method_name TEXT NOT NULL,
    method_code TEXT NOT NULL,
    is_active INTEGER DEFAULT 1
);

CREATE TABLE IF NOT EXISTS payments (
    payment_id INTEGER PRIMARY KEY AUTOINCREMENT,
    receipt_no TEXT NOT NULL UNIQUE,
    customer_id INTEGER NOT NULL,
    received_by INTEGER NOT NULL,
    payment_method_id INTEGER NOT NULL,
    payment_date TEXT NOT NULL,
    amount REAL NOT NULL,
    mpesa_ref TEXT,
    bank_ref TEXT,
    cheque_no TEXT,
    bank_name TEXT,
    notes TEXT,
    is_reversed INTEGER DEFAULT 0,
    reversal_reason TEXT,
    reversed_by INTEGER,
    reversed_at TEXT,
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY (customer_id) REFERENCES customers(customer_id),
    FOREIGN KEY (received_by) REFERENCES users(user_id),
    FOREIGN KEY (payment_method_id) REFERENCES payment_methods(method_id)
);

CREATE TABLE IF NOT EXISTS payment_allocations (
    allocation_id INTEGER PRIMARY KEY AUTOINCREMENT,
    payment_id INTEGER NOT NULL,
    invoice_id INTEGER NOT NULL,
    amount_allocated REAL NOT NULL,
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY (payment_id) REFERENCES payments(payment_id),
    FOREIGN KEY (invoice_id) REFERENCES invoices(invoice_id)
);

CREATE TABLE IF NOT EXISTS water_production (
    production_id INTEGER PRIMARY KEY AUTOINCREMENT,
    source_name TEXT NOT NULL,
    source_type TEXT DEFAULT 'Borehole',
    record_date TEXT NOT NULL,
    volume_m3 REAL NOT NULL,
    pumping_hours REAL,
    energy_kwh REAL,
    recorded_by INTEGER NOT NULL,
    notes TEXT,
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY (recorded_by) REFERENCES users(user_id)
);

CREATE TABLE IF NOT EXISTS nrw_reports (
    nrw_id INTEGER PRIMARY KEY AUTOINCREMENT,
    zone_id INTEGER,
    report_period TEXT NOT NULL,
    total_production_m3 REAL DEFAULT 0.00,
    total_billed_m3 REAL DEFAULT 0.00,
    nrw_percent REAL,
    commercial_losses_m3 REAL DEFAULT 0.00,
    physical_losses_m3 REAL DEFAULT 0.00,
    real_losses_m3 REAL DEFAULT 0.00,
    apparent_losses_m3 REAL DEFAULT 0.00,
    unbilled_authorised_m3 REAL DEFAULT 0.00,
    ifi REAL,
    notes TEXT,
    generated_by INTEGER,
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY (zone_id) REFERENCES zones(zone_id)
);

CREATE TABLE IF NOT EXISTS inspection_types (
    type_id INTEGER PRIMARY KEY AUTOINCREMENT,
    type_name TEXT NOT NULL,
    description TEXT
);

CREATE TABLE IF NOT EXISTS field_inspections (
    inspection_id INTEGER PRIMARY KEY AUTOINCREMENT,
    inspection_ref TEXT NOT NULL UNIQUE,
    customer_id INTEGER,
    meter_id INTEGER,
    zone_id INTEGER NOT NULL,
    inspection_type_id INTEGER NOT NULL,
    inspected_by INTEGER NOT NULL,
    inspection_date TEXT NOT NULL,
    gps_lat REAL,
    gps_lng REAL,
    findings TEXT NOT NULL,
    evidence_photo1 TEXT,
    evidence_photo2 TEXT,
    evidence_photo3 TEXT,
    estimated_loss_m3 REAL,
    estimated_revenue_loss REAL,
    status TEXT DEFAULT 'Open',
    action_taken TEXT,
    penalty_amount REAL DEFAULT 0.00,
    penalty_invoiced INTEGER DEFAULT 0,
    closed_by INTEGER,
    closed_at TEXT,
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at TEXT NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY (customer_id) REFERENCES customers(customer_id),
    FOREIGN KEY (meter_id) REFERENCES meters(meter_id),
    FOREIGN KEY (zone_id) REFERENCES zones(zone_id),
    FOREIGN KEY (inspection_type_id) REFERENCES inspection_types(type_id),
    FOREIGN KEY (inspected_by) REFERENCES users(user_id)
);

CREATE TABLE IF NOT EXISTS disconnections (
    disconnection_id INTEGER PRIMARY KEY AUTOINCREMENT,
    customer_id INTEGER NOT NULL,
    meter_id INTEGER NOT NULL,
    disconnect_date TEXT NOT NULL,
    reason TEXT NOT NULL,
    arrears_at_disconnect REAL DEFAULT 0.00,
    disconnected_by INTEGER NOT NULL,
    reconnect_date TEXT,
    reconnect_fee REAL DEFAULT 0.00,
    reconnected_by INTEGER,
    notes TEXT,
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY (customer_id) REFERENCES customers(customer_id),
    FOREIGN KEY (meter_id) REFERENCES meters(meter_id),
    FOREIGN KEY (disconnected_by) REFERENCES users(user_id)
);

CREATE TABLE IF NOT EXISTS store_items (
    item_id INTEGER PRIMARY KEY AUTOINCREMENT,
    item_code TEXT NOT NULL UNIQUE,
    item_name TEXT NOT NULL,
    category TEXT NOT NULL,
    unit_of_measure TEXT NOT NULL,
    current_stock REAL DEFAULT 0.00,
    reorder_level REAL DEFAULT 0.00,
    unit_cost REAL DEFAULT 0.00,
    location TEXT,
    description TEXT,
    is_active INTEGER DEFAULT 1,
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS stock_transactions (
    transaction_id INTEGER PRIMARY KEY AUTOINCREMENT,
    item_id INTEGER NOT NULL,
    transaction_type TEXT NOT NULL,
    quantity REAL NOT NULL,
    balance_after REAL NOT NULL,
    unit_cost REAL DEFAULT 0.00,
    reference_no TEXT,
    purpose TEXT,
    issued_to TEXT,
    project_ref TEXT,
    transacted_by INTEGER NOT NULL,
    transaction_date TEXT NOT NULL,
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY (item_id) REFERENCES store_items(item_id),
    FOREIGN KEY (transacted_by) REFERENCES users(user_id)
);

CREATE TABLE IF NOT EXISTS suppliers (
    supplier_id INTEGER PRIMARY KEY AUTOINCREMENT,
    supplier_code TEXT NOT NULL UNIQUE,
    company_name TEXT NOT NULL,
    contact_person TEXT,
    phone TEXT,
    email TEXT,
    pin_no TEXT,
    address TEXT,
    is_active INTEGER DEFAULT 1,
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS purchase_orders (
    po_id INTEGER PRIMARY KEY AUTOINCREMENT,
    lpo_no TEXT NOT NULL UNIQUE,
    supplier_id INTEGER NOT NULL,
    order_date TEXT NOT NULL,
    expected_delivery TEXT,
    total_amount REAL DEFAULT 0.00,
    vat_amount REAL DEFAULT 0.00,
    status TEXT DEFAULT 'Draft',
    approved_by INTEGER,
    created_by INTEGER NOT NULL,
    notes TEXT,
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY (supplier_id) REFERENCES suppliers(supplier_id),
    FOREIGN KEY (created_by) REFERENCES users(user_id)
);

CREATE TABLE IF NOT EXISTS po_items (
    po_item_id INTEGER PRIMARY KEY AUTOINCREMENT,
    po_id INTEGER NOT NULL,
    item_id INTEGER,
    description TEXT NOT NULL,
    quantity REAL NOT NULL,
    unit TEXT,
    unit_price REAL NOT NULL,
    quantity_received REAL DEFAULT 0.00,
    FOREIGN KEY (po_id) REFERENCES purchase_orders(po_id)
);

CREATE TABLE IF NOT EXISTS assets (
    asset_id INTEGER PRIMARY KEY AUTOINCREMENT,
    asset_code TEXT NOT NULL UNIQUE,
    asset_name TEXT NOT NULL,
    asset_type TEXT NOT NULL,
    description TEXT,
    location TEXT,
    gps_lat REAL,
    gps_lng REAL,
    purchase_date TEXT,
    purchase_cost REAL DEFAULT 0.00,
    current_value REAL DEFAULT 0.00,
    depreciation_rate REAL DEFAULT 0.00,
    warranty_expiry TEXT,
    condition TEXT DEFAULT 'Good',
    status TEXT DEFAULT 'Active',
    last_maintenance TEXT,
    next_maintenance TEXT,
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS maintenance_records (
    maintenance_id INTEGER PRIMARY KEY AUTOINCREMENT,
    asset_id INTEGER NOT NULL,
    maintenance_type TEXT NOT NULL,
    maintenance_date TEXT NOT NULL,
    description TEXT NOT NULL,
    cost REAL DEFAULT 0.00,
    performed_by TEXT,
    next_maintenance TEXT,
    status TEXT DEFAULT 'Completed',
    created_by INTEGER,
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY (asset_id) REFERENCES assets(asset_id)
);

CREATE TABLE IF NOT EXISTS projects (
    project_id INTEGER PRIMARY KEY AUTOINCREMENT,
    project_code TEXT NOT NULL UNIQUE,
    project_name TEXT NOT NULL,
    project_type TEXT NOT NULL,
    description TEXT,
    zone_id INTEGER,
    start_date TEXT,
    expected_end TEXT,
    actual_end TEXT,
    budget REAL DEFAULT 0.00,
    spent REAL DEFAULT 0.00,
    status TEXT DEFAULT 'Planning',
    progress_percent INTEGER DEFAULT 0,
    project_manager TEXT,
    contractor TEXT,
    funder TEXT,
    created_by INTEGER,
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS system_settings (
    setting_id INTEGER PRIMARY KEY AUTOINCREMENT,
    setting_key TEXT NOT NULL UNIQUE,
    setting_value TEXT,
    setting_group TEXT DEFAULT 'general',
    description TEXT,
    updated_at TEXT NOT NULL DEFAULT (datetime('now'))
);
SQL;

    $pdo->exec($schema);

    // Seed roles
    $pdo->exec("INSERT OR IGNORE INTO roles (role_id, role_name, role_description, permissions) VALUES
        (1, 'Super Admin', 'Full system access', '[\"*\"]'),
        (2, 'Admin', 'Administrative access', '[\"customers\",\"meters\",\"billing\",\"payments\",\"reports\",\"users\"]'),
        (3, 'Meter Reader', 'Field meter reading', '[\"meters.read\",\"readings.create\",\"inspections.create\"]'),
        (4, 'Cashier', 'Payment processing', '[\"payments.create\",\"payments.view\",\"receipts\"]'),
        (5, 'Supervisor', 'Supervisory oversight', '[\"customers.view\",\"meters.view\",\"billing.view\",\"reports\"]'),
        (6, 'Revenue Officer', 'Revenue protection', '[\"revenue_protection\",\"inspections\",\"enforcement\"]'),
        (7, 'Accounts', 'Financial management', '[\"payments\",\"billing\",\"reports.financial\"]')
    ");

    // Seed admin user (password: Admin@2026)
    $pdo->exec("INSERT OR IGNORE INTO users (user_id, role_id, employee_no, full_name, email, phone, username, password_hash) VALUES
        (1, 1, 'EMP001', 'System Administrator', 'admin@thiwasco.co.ke', '+254700000001', 'admin', '\$2y\$12\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi')
    ");

    // Seed payment methods
    $pdo->exec("INSERT OR IGNORE INTO payment_methods (method_id, method_name, method_code) VALUES
        (1, 'Cash', 'CASH'),
        (2, 'M-Pesa', 'MPESA'),
        (3, 'Bank Transfer', 'BANK'),
        (4, 'Cheque', 'CHEQUE'),
        (5, 'Credit/Debit Card', 'CARD'),
        (6, 'RTGS', 'RTGS')
    ");

    // Seed zones
    $pdo->exec("INSERT OR IGNORE INTO zones (zone_id, zone_code, zone_name, zone_type, area_description) VALUES
        (1, 'ZN-001', 'Zone 1 - Town Centre', 'Zone', 'Main town centre distribution zone'),
        (2, 'ZN-002', 'Zone 2 - Industrial Area', 'Zone', 'Industrial and commercial zone'),
        (3, 'ZN-003', 'Zone 3 - Residential North', 'Zone', 'Northern residential estates'),
        (4, 'ZN-004', 'Zone 4 - Residential South', 'Zone', 'Southern residential estates'),
        (5, 'ZN-005', 'Zone 5 - Peri-Urban', 'Zone', 'Peri-urban and informal settlements')
    ");

    // Seed tariffs
    $pdo->exec("INSERT OR IGNORE INTO tariff_categories (tariff_id, tariff_code, tariff_name, tariff_type, base_charge, rate_per_m3, sewer_percent, min_charge, tiered_rates, effective_from) VALUES
        (1, 'DOM-01', 'Domestic - Standard', 'Domestic', 150.00, 52.00, 60.00, 300.00, '[{\"from\":0,\"to\":6,\"rate\":52},{\"from\":7,\"to\":20,\"rate\":62},{\"from\":21,\"to\":50,\"rate\":75},{\"from\":51,\"to\":999999,\"rate\":95}]', '2024-01-01'),
        (2, 'COM-01', 'Commercial - Standard', 'Commercial', 300.00, 85.00, 60.00, 500.00, '[{\"from\":0,\"to\":10,\"rate\":85},{\"from\":11,\"to\":50,\"rate\":98},{\"from\":51,\"to\":999999,\"rate\":115}]', '2024-01-01'),
        (3, 'IND-01', 'Industrial - Standard', 'Industrial', 500.00, 120.00, 60.00, 800.00, '[{\"from\":0,\"to\":999999,\"rate\":120}]', '2024-01-01'),
        (4, 'INST-01', 'Institution - Standard', 'Institution', 300.00, 72.00, 60.00, 500.00, '[{\"from\":0,\"to\":20,\"rate\":72},{\"from\":21,\"to\":999999,\"rate\":88}]', '2024-01-01'),
        (5, 'KIOSK-01', 'Water Kiosk', 'Kiosk', 100.00, 35.00, 0.00, 200.00, '[{\"from\":0,\"to\":999999,\"rate\":35}]', '2024-01-01')
    ");

    // Seed inspection types
    $pdo->exec("INSERT OR IGNORE INTO inspection_types (type_id, type_name, description) VALUES
        (1, 'Meter Tampering', 'Meter has been physically tampered with'),
        (2, 'Illegal Connection', 'Unauthorized connection to water network'),
        (3, 'Meter Bypass', 'Water flowing bypassing the meter'),
        (4, 'Stolen Meter', 'Meter stolen from premises'),
        (5, 'Revenue Leakage', 'Water consumed but not billed'),
        (6, 'Meter Fast/Slow', 'Meter running abnormally fast or slow'),
        (7, 'Broken Seal', 'Meter seal broken indicating tampering'),
        (8, 'Meter Reading Fraud', 'Fraudulent meter reading captured'),
        (9, 'Illegal Reconnection', 'Self-reconnection after disconnection')
    ");

    // Seed system settings
    $settings = [
        ['company_name', 'Thika Water and Sewerage Company (THIWASCO)', 'general', 'Company full name'],
        ['company_short_name', 'THIWASCO', 'general', 'Company short name'],
        ['company_address', 'P.O. Box 4 - 01000, Thika', 'general', 'Company postal address'],
        ['company_phone', '+254 67 20010', 'general', 'Company phone'],
        ['company_email', 'info@thiwasco.co.ke', 'general', 'Company email'],
        ['company_website', 'www.thiwasco.co.ke', 'general', 'Company website'],
        ['invoice_prefix', 'INV', 'billing', 'Invoice number prefix'],
        ['receipt_prefix', 'RCP', 'billing', 'Receipt number prefix'],
        ['nrw_target_percent', '20', 'nrw', 'NRW target percentage (WASREB target)'],
        ['billing_day', '1', 'billing', 'Day of month billing runs'],
        ['penalty_rate', '5', 'billing', 'Late payment penalty rate %'],
        ['grace_period_days', '30', 'billing', 'Grace period before penalty applies'],
        ['vat_rate', '16', 'billing', 'VAT rate percentage'],
        ['currency', 'KES', 'general', 'Default currency'],
        ['mpesa_paybill', '888300', 'payments', 'M-Pesa paybill number'],
        ['modules_enabled', '["core","stores","procurement","assets","projects"]', 'modules', 'Enabled optional modules'],
    ];
    $stmtSet = $pdo->prepare("INSERT OR IGNORE INTO system_settings (setting_key, setting_value, setting_group, description) VALUES (?, ?, ?, ?)");
    foreach ($settings as $s) {
        $stmtSet->execute($s);
    }

    // Seed sample customers & operational data if customers table is empty
    $custCount = $pdo->query("SELECT COUNT(*) FROM customers")->fetchColumn();
    if ($custCount == 0) {
        $sampleCustomers = [
            ['THI-ZN001-0001', 1, 1, 'Individual', 'David Mwangi Kariuki', '24189012', '0722100201', 'david.mwangi@gmail.com', 'Section 9 Plot 45', 'Plot 45', 'Commercial Street', 1850.00, 'Active'],
            ['THI-ZN001-0002', 1, 2, 'Company', 'Cravers Grill & Hotel Ltd', 'CPR/2018/891', '0720445566', 'accounts@cravers.co.ke', 'Uhuru Street, Central Business District', 'Plot 12/B', 'Uhuru Street', 12400.00, 'Active'],
            ['THI-ZN001-0003', 1, 1, 'Individual', 'Grace Njeri Kamau', '18902341', '0723891044', 'gnjeri@yahoo.com', 'Section 2, House 14', 'Plot 14', 'Haile Selassie Rd', 0.00, 'Active'],
            ['THI-ZN002-0001', 2, 3, 'Company', 'Bidco Africa Limited', 'CPR/1991/001', '0700112233', 'utilities@bidco-africa.com', 'Industrial Area, Garissa Road', 'LR 4953/12', 'Garissa Road', 85000.00, 'Active'],
            ['THI-ZN002-0002', 2, 3, 'Company', 'Bakex Millers Limited', 'CPR/1984/204', '0722334455', 'maintenance@bakex.co.ke', 'Station Road, Industrial Zone', 'Plot 88', 'Station Road', 42000.00, 'Active'],
            ['THI-ZN002-0003', 2, 2, 'Company', 'TotalEnergies Thika Service Station', 'CPR/2005/712', '0721998877', 'dealer.thika@totalenergies.ke', 'Garissa Roundabout', 'Plot 5', 'Garissa Road', 4500.00, 'Active'],
            ['THI-ZN003-0001', 3, 1, 'Individual', 'Peter Njoroge Githinji', '29481023', '0711445566', 'peter.githinji@outlook.com', 'Landless Estate, Phase 2, Court 4', 'Plot 204', 'Acacia Avenue', 2100.00, 'Active'],
            ['THI-ZN003-0002', 3, 4, 'Institution', 'Chania High School', 'SCH/1964/01', '0722778899', 'chaniahigh@education.go.ke', 'Chania River Bank off Workshop Rd', 'LR 209/4', 'Workshop Road', 28500.00, 'Active'],
            ['THI-ZN003-0003', 3, 1, 'Individual', 'Mercy Wambui Mwaura', '31209845', '0724556677', 'mercy.wambui@gmail.com', 'Kiganjo Corner House 3', 'Plot 67', 'Kiganjo Road', 850.00, 'Active'],
            ['THI-ZN004-0001', 4, 1, 'Individual', 'Joseph Ochieng Otieno', '22415678', '0733889900', 'jochieng@gmail.com', 'Makongeni Phase 4, House B12', 'Plot 310', 'Posta Road', 3400.00, 'Defaulter'],
            ['THI-ZN004-0002', 4, 4, 'Institution', 'Mount Kenya University Main Campus', 'MKU/2008/001', '0709153000', 'facilities@mku.ac.ke', 'General Kago Road', 'LR 502/10', 'General Kago Road', 145000.00, 'Active'],
            ['THI-ZN004-0003', 4, 1, 'Individual', 'Jane Muthoni Gitau', '27891234', '0720123987', 'jmuthoni@gmail.com', 'Hospital Ward Estate, Block C', 'Plot 15', 'Hospital Road', 6200.00, 'Disconnected'],
            ['THI-ZN005-0001', 5, 5, 'Kiosk', 'Kiandutu Community Water Kiosk 1', 'CBO/2016/44', '0710223344', 'kiandutukiosk1@gmail.com', 'Kiandutu Informal Settlement Center', 'Site K-1', 'Main Pathway', 1200.00, 'Active'],
            ['THI-ZN005-0002', 5, 5, 'Kiosk', 'Gatuanyaga Water Dispensary', 'CBO/2019/12', '0728990011', 'gatuanyagawater@gmail.com', 'Gatuanyaga Market Junction', 'Site K-2', 'Market Road', 450.00, 'Active'],
            ['THI-ZN005-0003', 5, 1, 'Individual', 'Stephen Ndung\'u Kimani', '33491029', '0726112233', 'skimani@gmail.com', 'Munyu Sub-location Farm 12', 'Plot 120', 'Munyu Link', 8900.00, 'Defaulter']
        ];

        $stmtC = $pdo->prepare("INSERT INTO customers (account_no, zone_id, tariff_id, customer_type, full_name, id_number, phone, email, physical_address, plot_no, road_street, balance, status, connection_date, connection_size, deposit_paid, created_by)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, '2024-01-15', '1/2\"', 2500.00, 1)");
        $custIds = [];
        foreach ($sampleCustomers as $sc) {
            $stmtC->execute($sc);
            $custIds[$sc[0]] = $pdo->lastInsertId();
        }

        // Meters & Readings
        $stmtM = $pdo->prepare("INSERT INTO meters (customer_id, meter_serial, meter_make, meter_model, meter_size, meter_type, installation_date, seal_no, initial_reading, current_reading, status, installed_by)
                                VALUES (?, ?, 'Elster Kent', 'V100', '1/2\"', 'Analogue', '2024-01-15', ?, ?, ?, 'Active', 1)");
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

            $anomalyFlag = ($serialIndex === 1010) ? 1 : 0;
            $anomalyReason = ($serialIndex === 1010) ? 'Spike >3x average consumption' : null;
            if ($serialIndex === 1010) $m3 = $m2 + 180;

            $stmtM->execute([$cId, $serial, $seal, $init, $m3]);
            $mId = $pdo->lastInsertId();
            $meterIds[$cId] = ['meter_id' => $mId, 'curr' => $m3, 'm2' => $m2, 'm1' => $m1, 'init' => $init];

            $stmtR->execute([$mId, $cId, '2026-08', '2026-08-28', $init, $m1, 0, null]);
            $stmtR->execute([$mId, $cId, '2026-09', '2026-09-28', $m1, $m2, 0, null]);
            $stmtR->execute([$mId, $cId, '2026-10', '2026-10-02', $m2, $m3, $anomalyFlag, $anomalyReason]);
            $serialIndex++;
        }

        // Invoices
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

        // Payments
        $stmtP = $pdo->prepare("INSERT INTO payments (receipt_no, customer_id, received_by, payment_method_id, payment_date, amount, mpesa_ref, notes)
                                VALUES (?, ?, 1, 2, '2026-10-01', ?, ?, 'M-Pesa Paybill instant settlement')");
        $rcptCounter = 101;
        foreach ($custIds as $accNo => $cId) {
            if ($rcptCounter % 2 === 0) {
                $rcptNo = 'RCP202610' . str_pad($rcptCounter, 4, '0', STR_PAD_LEFT);
                $mpesaRef = 'QKF' . rand(100000, 999999) . 'KT';
                $stmtP->execute([$rcptNo, $cId, rand(1500, 5000), $mpesaRef]);
            }
            $rcptCounter++;
        }

        // Bulk meters & production
        $pdo->exec("INSERT OR IGNORE INTO bulk_meters (bulk_meter_id, zone_id, meter_serial, meter_name, meter_size, installation_date, location_description, is_active) VALUES
            (1, 1, 'BLK-001', 'Town Centre Inflow Master', '150mm', '2023-01-10', 'Town Reservoir Outlet Chamber', 1),
            (2, 2, 'BLK-002', 'Industrial Trunk Meter', '200mm', '2023-01-10', 'Garissa Road Distribution Valve Chamber', 1),
            (3, 3, 'BLK-003', 'Residential North Zone Meter', '100mm', '2023-02-15', 'Chania Intake Branch', 1)
        ");

        $pdo->exec("INSERT OR IGNORE INTO bulk_meter_readings (bulk_meter_id, read_by, reading_date, previous_reading, current_reading, notes) VALUES
            (1, 1, '2026-09-30', 450000, 482000, 'End month master read'),
            (2, 1, '2026-09-30', 320000, 358000, 'End month industrial read'),
            (3, 1, '2026-09-30', 180000, 199000, 'End month north read')
        ");

        $pdo->exec("INSERT OR IGNORE INTO water_production (source_name, source_type, record_date, volume_m3, pumping_hours, energy_kwh, recorded_by, notes) VALUES
            ('Chania River Water Treatment Plant', 'River', '2026-09-30', 125000.00, 720.00, 45000.00, 1, 'Full capacity operation'),
            ('Thika Dam Raw Water Gravity Main', 'Dam', '2026-09-30', 95000.00, 720.00, 12000.00, 1, 'Gravity intake'),
            ('Makongeni High Yield Borehole 1', 'Borehole', '2026-09-30', 18500.00, 480.00, 8900.00, 1, 'Supplemental production')
        ");

        $pdo->exec("INSERT OR IGNORE INTO nrw_reports (zone_id, report_period, total_production_m3, total_billed_m3, nrw_percent, commercial_losses_m3, physical_losses_m3, real_losses_m3, apparent_losses_m3, unbilled_authorised_m3, generated_by) VALUES
            (NULL, '2026-09', 238500.00, 162180.00, 32.00, 28620.00, 47700.00, 47700.00, 28620.00, 2500.00, 1),
            (1, '2026-09', 32000.00, 24640.00, 23.00, 2880.00, 4480.00, 4480.00, 2880.00, 400.00, 1),
            (2, '2026-09', 38000.00, 30400.00, 20.00, 3040.00, 4560.00, 4560.00, 3040.00, 500.00, 1)
        ");

        // Field inspections
        $pdo->exec("INSERT OR IGNORE INTO field_inspections (inspection_ref, customer_id, meter_id, zone_id, inspection_type_id, inspected_by, inspection_date, findings, status, penalty_amount, penalty_invoiced) VALUES
            ('INS-202610-0001', 1, 1, 1, 1, 1, '2026-10-01', 'Meter dial slowed with magnet. Evidence collected and seal replaced.', 'Under Investigation', 15000.00, 0),
            ('INS-202610-0002', 4, 4, 2, 3, 1, '2026-10-02', 'Underground bypass tee detected prior to meter inlet valve.', 'Resolved', 50000.00, 1),
            ('INS-202610-0003', NULL, NULL, 5, 2, 1, '2026-10-03', 'Direct unmetered tapping on 63mm distribution main in Kiandutu.', 'Open', 25000.00, 0)
        ");

        // Stores & Inventory
        $pdo->exec("INSERT OR IGNORE INTO store_items (item_code, item_name, category, unit_of_measure, current_stock, reorder_level, unit_cost, location) VALUES
            ('MTR-15-DOM', 'Elster Kent V100 1/2\" Domestic Water Meter', 'Meters', 'Pieces', 350.00, 50.00, 3200.00, 'Warehouse Shelf A-1'),
            ('MTR-50-COM', 'Flanged Woltmann 2\" Bulk Water Meter', 'Meters', 'Pieces', 12.00, 5.00, 28500.00, 'Warehouse Shelf A-4'),
            ('PIP-PPR-20', 'PPR Pipe Class 20 - 20mm x 4m', 'Pipes & Fittings', 'Lengths', 480.00, 100.00, 650.00, 'Yard Rack 2'),
            ('VLV-GT-15', 'Brass Gate Valve 1/2\" Heavy Duty', 'Valves', 'Pieces', 240.00, 40.00, 450.00, 'Warehouse Bin B-12'),
            ('CHM-ALUM', 'Aluminium Sulphate (Coagulant) 50kg bag', 'Water Treatment', 'Bags', 180.00, 50.00, 3800.00, 'Chemical Store Bay 1'),
            ('CHM-CHLOR', 'Calcium Hypochlorite 65% (HTH) 45kg drum', 'Water Treatment', 'Drums', 65.00, 20.00, 14500.00, 'Chemical Store Bay 2')
        ");

        // Suppliers & Procurement
        $pdo->exec("INSERT OR IGNORE INTO suppliers (supplier_code, company_name, contact_person, phone, email, pin_no, address) VALUES
            ('SUP-001', 'Davis & Shirtliff Ltd', 'John Mutua', '0711079000', 'sales@dayliff.com', 'P051100223A', 'D&S Building, Dundori Rd, Nairobi'),
            ('SUP-002', 'Doshi Water Solutions Ltd', 'Ramesh Patel', '0722203040', 'water@doshi.co.ke', 'P000600123K', 'Doshi Complex, Mombasa Rd, Nairobi'),
            ('SUP-003', 'BOC Kenya Chemicals Ltd', 'Mary Atieno', '0733600100', 'orders@boc.co.ke', 'P051189912Z', 'Kitui Road, Industrial Area, Nairobi')
        ");

        $pdo->exec("INSERT OR IGNORE INTO purchase_orders (lpo_no, supplier_id, order_date, expected_delivery, total_amount, vat_amount, status, created_by) VALUES
            ('LPO-2026-001', 1, '2026-09-15', '2026-10-10', 485000.00, 77600.00, 'Approved', 1),
            ('LPO-2026-002', 2, '2026-09-20', '2026-10-05', 312000.00, 49920.00, 'Delivered', 1),
            ('LPO-2026-003', 3, '2026-09-25', '2026-10-15', 725000.00, 116000.00, 'Submitted', 1)
        ");

        // Assets
        $pdo->exec("INSERT OR IGNORE INTO assets (asset_code, asset_name, asset_type, location, purchase_date, purchase_cost, current_value, condition, status) VALUES
            ('AST-PMP-001', 'Flygt Submersible Raw Water Pump 45kW', 'Pump', 'Chania River Raw Water Intake Chamber 1', '2022-03-10', 2400000.00, 1850000.00, 'Good', 'Active'),
            ('AST-TNK-001', 'Kimathi High Level Concrete Reservoir 5,000m3', 'Tank', 'Section 9 Kimathi Hill Top', '2018-06-01', 45000000.00, 38000000.00, 'Excellent', 'Active'),
            ('AST-VEH-001', 'Isuzu D-Max 4x4 Emergency Repair Double Cabin (KDG 412A)', 'Vehicle', 'Head Office Fleet Yard', '2023-11-20', 4800000.00, 4100000.00, 'Good', 'Active')
        ");

        // Projects
        $pdo->exec("INSERT OR IGNORE INTO projects (project_code, project_name, project_type, zone_id, start_date, expected_end, budget, spent, status, progress_percent, project_manager, contractor) VALUES
            ('PRJ-2026-001', 'Kiandutu Informal Settlement Water Extension & DMA Zoning', 'Infrastructure', 5, '2026-01-10', '2026-12-31', 35000000.00, 18200000.00, 'Active', 55, 'Eng. Samuel Gichuru', 'Apex Water Contractors Ltd'),
            ('PRJ-2026-002', 'Makongeni Smart Meter Replacement & Automated Billing Rollout', 'Rehabilitation', 4, '2026-03-01', '2026-09-30', 14500000.00, 13800000.00, 'Completed', 100, 'Faith Wangari', 'In-house Technical Team'),
            ('PRJ-2026-003', 'Chania River Intake Siltation Barrier & Desanding Basin', 'Infrastructure', 1, '2026-06-01', '2027-02-28', 28000000.00, 8400000.00, 'Active', 30, 'Eng. Benson Mwangi', 'Hydratech Civil Works Ltd')
        ");
    }

    $pdo->exec("PRAGMA foreign_keys = ON;");
    return true;
}

// If invoked from CLI directly
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    echo "Running SQLite database initialization...\n";
    try {
        initSqliteDatabase();
        echo "✅ SQLite database initialized and seeded successfully!\n";
    } catch (Exception $e) {
        echo "❌ Error: " . $e->getMessage() . "\n";
        exit(1);
    }
}
