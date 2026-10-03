<?php
/**
 * THIWASCO - SQLite Database Initializer
 * Run by docker-entrypoint.sh on first boot to create tables and seed data.
 * This converts the MySQL schema to SQLite-compatible DDL.
 */

$sqliteFile = __DIR__ . '/thiwasco.sqlite';

if (file_exists($sqliteFile)) {
    echo "SQLite database already exists, skipping init.\n";
    exit(0);
}

echo "Creating SQLite database at: $sqliteFile\n";

try {
    $pdo = new PDO('sqlite:' . $sqliteFile, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
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
    description TEXT,
    dma_type TEXT DEFAULT 'DMA',
    parent_zone_id INTEGER,
    bulk_meter_id INTEGER,
    is_active INTEGER DEFAULT 1,
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS customers (
    customer_id INTEGER PRIMARY KEY AUTOINCREMENT,
    account_no TEXT NOT NULL UNIQUE,
    full_name TEXT NOT NULL,
    id_no TEXT,
    phone TEXT,
    email TEXT,
    physical_address TEXT,
    zone_id INTEGER,
    meter_serial TEXT,
    meter_id INTEGER,
    tariff_id INTEGER,
    connection_type TEXT DEFAULT 'Domestic',
    connection_date TEXT,
    disconnection_date TEXT,
    status TEXT DEFAULT 'Active',
    balance REAL DEFAULT 0,
    last_reading REAL DEFAULT 0,
    last_reading_date TEXT,
    profile_photo TEXT,
    gps_lat REAL,
    gps_lng REAL,
    notes TEXT,
    created_by INTEGER,
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at TEXT NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY (zone_id) REFERENCES zones(zone_id)
);

CREATE TABLE IF NOT EXISTS tariffs (
    tariff_id INTEGER PRIMARY KEY AUTOINCREMENT,
    tariff_name TEXT NOT NULL,
    tariff_code TEXT NOT NULL UNIQUE,
    category TEXT DEFAULT 'Domestic',
    fixed_charge REAL DEFAULT 0,
    min_units INTEGER DEFAULT 0,
    blocks TEXT,
    standing_charge REAL DEFAULT 0,
    sewer_rate REAL DEFAULT 0,
    vat_applicable INTEGER DEFAULT 1,
    is_active INTEGER DEFAULT 1,
    effective_date TEXT,
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS meters (
    meter_id INTEGER PRIMARY KEY AUTOINCREMENT,
    meter_serial TEXT NOT NULL UNIQUE,
    meter_type TEXT DEFAULT 'Analog',
    meter_size TEXT DEFAULT '15mm',
    brand TEXT,
    customer_id INTEGER,
    zone_id INTEGER,
    installation_date TEXT,
    last_service_date TEXT,
    reading_cycle TEXT DEFAULT 'Monthly',
    initial_reading REAL DEFAULT 0,
    current_reading REAL DEFAULT 0,
    status TEXT DEFAULT 'Active',
    is_bulk INTEGER DEFAULT 0,
    gps_lat REAL,
    gps_lng REAL,
    photo TEXT,
    notes TEXT,
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at TEXT NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY (customer_id) REFERENCES customers(customer_id)
);

CREATE TABLE IF NOT EXISTS meter_readings (
    reading_id INTEGER PRIMARY KEY AUTOINCREMENT,
    meter_id INTEGER NOT NULL,
    customer_id INTEGER,
    previous_reading REAL DEFAULT 0,
    current_reading REAL NOT NULL,
    consumption REAL DEFAULT 0,
    reading_date TEXT NOT NULL,
    reading_type TEXT DEFAULT 'Actual',
    read_by INTEGER,
    photo TEXT,
    notes TEXT,
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY (meter_id) REFERENCES meters(meter_id)
);

CREATE TABLE IF NOT EXISTS invoices (
    invoice_id INTEGER PRIMARY KEY AUTOINCREMENT,
    invoice_no TEXT NOT NULL UNIQUE,
    customer_id INTEGER NOT NULL,
    meter_id INTEGER,
    reading_id INTEGER,
    billing_month TEXT NOT NULL,
    prev_reading REAL DEFAULT 0,
    curr_reading REAL DEFAULT 0,
    consumption REAL DEFAULT 0,
    tariff_id INTEGER,
    water_charges REAL DEFAULT 0,
    sewer_charges REAL DEFAULT 0,
    fixed_charges REAL DEFAULT 0,
    penalties REAL DEFAULT 0,
    vat_amount REAL DEFAULT 0,
    total_charges REAL DEFAULT 0,
    previous_balance REAL DEFAULT 0,
    total_payable REAL DEFAULT 0,
    amount_paid REAL DEFAULT 0,
    balance REAL DEFAULT 0,
    due_date TEXT,
    status TEXT DEFAULT 'Unpaid',
    notes TEXT,
    generated_by INTEGER,
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY (customer_id) REFERENCES customers(customer_id)
);

CREATE TABLE IF NOT EXISTS payment_methods (
    method_id INTEGER PRIMARY KEY AUTOINCREMENT,
    method_name TEXT NOT NULL UNIQUE,
    method_code TEXT NOT NULL UNIQUE,
    description TEXT,
    is_active INTEGER DEFAULT 1
);

CREATE TABLE IF NOT EXISTS payments (
    payment_id INTEGER PRIMARY KEY AUTOINCREMENT,
    receipt_no TEXT NOT NULL UNIQUE,
    customer_id INTEGER NOT NULL,
    invoice_id INTEGER,
    payment_method_id INTEGER,
    amount REAL NOT NULL,
    payment_date TEXT NOT NULL,
    reference_no TEXT,
    mpesa_code TEXT,
    bank_ref TEXT,
    cashier_id INTEGER,
    is_reversed INTEGER DEFAULT 0,
    reversed_by INTEGER,
    reversed_at TEXT,
    reversal_reason TEXT,
    notes TEXT,
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY (customer_id) REFERENCES customers(customer_id)
);

CREATE TABLE IF NOT EXISTS water_production (
    production_id INTEGER PRIMARY KEY AUTOINCREMENT,
    source_name TEXT NOT NULL,
    production_period TEXT NOT NULL,
    volume_m3 REAL NOT NULL,
    notes TEXT,
    recorded_by INTEGER,
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS nrw_reports (
    report_id INTEGER PRIMARY KEY AUTOINCREMENT,
    report_period TEXT NOT NULL UNIQUE,
    total_production_m3 REAL DEFAULT 0,
    total_billed_m3 REAL DEFAULT 0,
    nrw_m3 REAL DEFAULT 0,
    nrw_percent REAL DEFAULT 0,
    notes TEXT,
    created_by INTEGER,
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS field_inspections (
    inspection_id INTEGER PRIMARY KEY AUTOINCREMENT,
    inspection_ref TEXT NOT NULL UNIQUE,
    customer_id INTEGER,
    account_no TEXT,
    inspection_type TEXT NOT NULL,
    findings TEXT,
    status TEXT DEFAULT 'Open',
    action_taken TEXT,
    assigned_to INTEGER,
    inspection_date TEXT,
    closed_date TEXT,
    photos TEXT,
    created_by INTEGER,
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS illegal_connections (
    illegal_id INTEGER PRIMARY KEY AUTOINCREMENT,
    detection_ref TEXT NOT NULL UNIQUE,
    location TEXT NOT NULL,
    zone_id INTEGER,
    description TEXT,
    estimated_loss_m3 REAL DEFAULT 0,
    status TEXT DEFAULT 'Open',
    action_taken TEXT,
    detected_by INTEGER,
    detection_date TEXT,
    resolved_date TEXT,
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS disconnections (
    disconnection_id INTEGER PRIMARY KEY AUTOINCREMENT,
    customer_id INTEGER NOT NULL,
    reason TEXT NOT NULL,
    disconnection_date TEXT,
    reconnection_date TEXT,
    disconnected_by INTEGER,
    reconnected_by INTEGER,
    amount_owed REAL DEFAULT 0,
    reconnection_fee REAL DEFAULT 0,
    status TEXT DEFAULT 'Disconnected',
    notes TEXT,
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY (customer_id) REFERENCES customers(customer_id)
);

CREATE TABLE IF NOT EXISTS stock_items (
    item_id INTEGER PRIMARY KEY AUTOINCREMENT,
    item_code TEXT NOT NULL UNIQUE,
    item_name TEXT NOT NULL,
    category TEXT,
    unit TEXT DEFAULT 'Pieces',
    current_stock REAL DEFAULT 0,
    reorder_level REAL DEFAULT 10,
    unit_cost REAL DEFAULT 0,
    notes TEXT,
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS stock_ledger (
    ledger_id INTEGER PRIMARY KEY AUTOINCREMENT,
    item_id INTEGER NOT NULL,
    transaction_type TEXT NOT NULL,
    quantity REAL NOT NULL,
    balance REAL DEFAULT 0,
    reference TEXT,
    notes TEXT,
    created_by INTEGER,
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY (item_id) REFERENCES stock_items(item_id)
);

CREATE TABLE IF NOT EXISTS purchase_orders (
    po_id INTEGER PRIMARY KEY AUTOINCREMENT,
    po_number TEXT NOT NULL UNIQUE,
    supplier TEXT NOT NULL,
    description TEXT,
    total_amount REAL DEFAULT 0,
    status TEXT DEFAULT 'Draft',
    ordered_date TEXT,
    received_date TEXT,
    created_by INTEGER,
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS assets (
    asset_id INTEGER PRIMARY KEY AUTOINCREMENT,
    asset_code TEXT NOT NULL UNIQUE,
    asset_name TEXT NOT NULL,
    category TEXT,
    location TEXT,
    purchase_date TEXT,
    purchase_cost REAL DEFAULT 0,
    current_value REAL DEFAULT 0,
    status TEXT DEFAULT 'Active',
    notes TEXT,
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS asset_maintenance (
    maintenance_id INTEGER PRIMARY KEY AUTOINCREMENT,
    asset_id INTEGER NOT NULL,
    maintenance_type TEXT,
    description TEXT,
    cost REAL DEFAULT 0,
    maintenance_date TEXT,
    next_maintenance_date TEXT,
    performed_by TEXT,
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY (asset_id) REFERENCES assets(asset_id)
);

CREATE TABLE IF NOT EXISTS projects (
    project_id INTEGER PRIMARY KEY AUTOINCREMENT,
    project_code TEXT NOT NULL UNIQUE,
    project_name TEXT NOT NULL,
    description TEXT,
    expected_start TEXT,
    expected_end TEXT,
    actual_end TEXT,
    budget REAL DEFAULT 0,
    spent REAL DEFAULT 0,
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

    // Execute schema
    $pdo->exec($schema);
    echo "✅ Tables created.\n";

    // Seed roles
    $pdo->exec("INSERT OR IGNORE INTO roles (role_name, role_description, permissions) VALUES
        ('Super Admin', 'Full system access', '[\"*\"]'),
        ('Admin', 'Administrative access', '[\"customers\",\"meters\",\"billing\",\"payments\",\"reports\",\"users\"]'),
        ('Meter Reader', 'Field meter reading', '[\"meters.read\",\"readings.create\",\"inspections.create\"]'),
        ('Cashier', 'Payment processing', '[\"payments.create\",\"payments.view\",\"receipts\"]'),
        ('Supervisor', 'Supervisory oversight', '[\"customers.view\",\"meters.view\",\"billing.view\",\"reports\"]'),
        ('Revenue Officer', 'Revenue protection', '[\"revenue_protection\",\"inspections\",\"enforcement\"]'),
        ('Accounts', 'Financial management', '[\"payments\",\"billing\",\"reports.financial\"]')
    ");
    echo "✅ Roles seeded.\n";

    // Seed admin user (password: Admin@2026)
    $pdo->exec("INSERT OR IGNORE INTO users (role_id, employee_no, full_name, email, phone, username, password_hash) VALUES
        (1, 'EMP001', 'System Administrator', 'admin@thiwasco.co.ke', '+254700000001', 'admin', '\$2y\$12\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi')
    ");
    echo "✅ Admin user seeded.\n";

    // Seed payment methods
    $pdo->exec("INSERT OR IGNORE INTO payment_methods (method_name, method_code, description) VALUES
        ('Cash', 'CASH', 'Cash payment at office'),
        ('M-Pesa', 'MPESA', 'M-Pesa mobile payment'),
        ('Bank Transfer', 'BANK', 'Bank wire transfer'),
        ('Cheque', 'CHQ', 'Cheque payment'),
        ('Credit Card', 'CARD', 'Credit/Debit card')
    ");
    echo "✅ Payment methods seeded.\n";

    // Seed zones
    $pdo->exec("INSERT OR IGNORE INTO zones (zone_code, zone_name, description) VALUES
        ('Z001', 'Thika Town', 'Thika Town Central Zone'),
        ('Z002', 'Makongeni', 'Makongeni Zone'),
        ('Z003', 'Kiandutu', 'Kiandutu Zone'),
        ('Z004', 'Landless', 'Landless Estate Zone'),
        ('Z005', 'Mangu', 'Mangu Zone')
    ");
    echo "✅ Zones seeded.\n";

    // Seed tariffs
    $pdo->exec("INSERT OR IGNORE INTO tariffs (tariff_name, tariff_code, category, fixed_charge, min_units, standing_charge, sewer_rate, vat_applicable) VALUES
        ('Domestic Standard', 'DOM-STD', 'Domestic', 150.00, 0, 50.00, 0.30, 1),
        ('Commercial', 'COM-STD', 'Commercial', 500.00, 0, 100.00, 0.40, 1),
        ('Industrial', 'IND-STD', 'Industrial', 1000.00, 0, 200.00, 0.50, 1),
        ('Institutional', 'INST-STD', 'Institutional', 300.00, 0, 75.00, 0.35, 1)
    ");
    echo "✅ Tariffs seeded.\n";

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
        ['nrw_target_percent', '20', 'nrw', 'NRW target percentage'],
        ['billing_day', '1', 'billing', 'Day of month billing runs'],
        ['penalty_rate', '5', 'billing', 'Late payment penalty rate %'],
        ['grace_period_days', '30', 'billing', 'Grace period before penalty applies'],
        ['vat_rate', '16', 'billing', 'VAT rate percentage'],
        ['currency', 'KES', 'general', 'Default currency'],
        ['mpesa_paybill', '888300', 'payments', 'M-Pesa paybill number'],
        ['modules_enabled', '["core","stores","procurement","assets","projects"]', 'modules', 'Enabled optional modules'],
    ];
    $stmt = $pdo->prepare("INSERT OR IGNORE INTO system_settings (setting_key, setting_value, setting_group, description) VALUES (?, ?, ?, ?)");
    foreach ($settings as $s) {
        $stmt->execute($s);
    }
    echo "✅ System settings seeded.\n";

    $pdo->exec("PRAGMA foreign_keys = ON;");
    echo "🎉 SQLite database initialized successfully!\n";
    exit(0);

} catch (Exception $e) {
    echo "❌ ERROR initializing SQLite: " . $e->getMessage() . "\n";
    exit(1);
}
