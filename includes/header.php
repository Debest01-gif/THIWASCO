<?php
/**
 * THIWASCO - Header / Layout Template
 * Include at top of every authenticated page
 *
 * Variables available to set before including:
 * $pageTitle - Page title string
 * $activePage - Active sidebar item key
 * $activeSection - Active sidebar section key
 */
require_once __DIR__ . '/auth.php';
Auth::check();

$user = Auth::user();
$companyName  = getSetting('company_name', 'THIWASCO');
$companyShort = getSetting('company_short_name', 'THIWASCO');
$modulesRaw   = getSetting('modules_enabled', 'stores,procurement,assets,projects');
if (is_string($modulesRaw)) {
    $decoded = json_decode($modulesRaw, true);
    if (is_array($decoded)) {
        $modulesEnabled = $decoded;
    } else {
        $modulesEnabled = array_map('trim', explode(',', $modulesRaw));
    }
} else {
    $modulesEnabled = ['stores', 'procurement', 'assets', 'projects'];
}

$pageTitle   = $pageTitle ?? 'Dashboard';
$activePage  = $activePage ?? 'dashboard';
$activeSection = $activeSection ?? '';

// Get flash message
$flash = getFlash();

// Quick stats for header badges
$pendingInspections = 0;
try {
    $res = db()->fetch("SELECT COUNT(*) as cnt FROM field_inspections WHERE status = 'Open'");
    $pendingInspections = $res['cnt'] ?? 0;
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="THIWASCO Water Management Information System - Water Loss, Meters & Revenue Protection">
  <title><?= htmlspecialchars($pageTitle) ?> | <?= $companyShort ?> MIS</title>

  <!-- Fonts & Icons -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Outfit:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">

  <!-- Chart.js -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js" defer></script>
</head>
<body>
<div class="app-wrapper">

  <!-- ===== SIDEBAR ===== -->
  <nav class="sidebar" id="sidebar">
    <div class="sidebar-header">
      <div class="sidebar-logo">💧</div>
      <div class="sidebar-brand">
        <h3><?= $companyShort ?></h3>
        <p>Water MIS v1.0</p>
      </div>
    </div>

    <div class="sidebar-nav">

      <!-- CORE NAVIGATION -->
      <span class="nav-section-title">Core System</span>

      <div class="nav-item">
        <a href="<?= APP_URL ?>/index.php" class="nav-link <?= $activePage === 'dashboard' ? 'active' : '' ?>">
          <span class="nav-icon"><i class="fa-solid fa-gauge-high"></i></span>
          <span class="nav-label">Dashboard</span>
        </a>
      </div>

      <!-- CUSTOMERS -->
      <div class="nav-item <?= $activeSection === 'customers' ? 'open' : '' ?>">
        <div class="nav-link <?= $activeSection === 'customers' ? 'active' : '' ?>" onclick="toggleNav(this)">
          <span class="nav-icon"><i class="fa-solid fa-users"></i></span>
          <span class="nav-label">Customers</span>
          <span class="nav-arrow"><i class="fa-solid fa-chevron-right"></i></span>
        </div>
        <div class="nav-submenu <?= $activeSection === 'customers' ? 'open' : '' ?>">
          <a href="<?= APP_URL ?>/modules/customers/index.php" class="nav-link <?= $activePage === 'customers_list' ? 'active' : '' ?>">
            <span class="nav-icon"><i class="fa-regular fa-list-alt"></i></span>
            <span class="nav-label">All Customers</span>
          </a>
          <a href="<?= APP_URL ?>/modules/customers/add.php" class="nav-link <?= $activePage === 'customers_add' ? 'active' : '' ?>">
            <span class="nav-icon"><i class="fa-solid fa-user-plus"></i></span>
            <span class="nav-label">Add Customer</span>
          </a>
          <a href="<?= APP_URL ?>/modules/customers/disconnected.php" class="nav-link <?= $activePage === 'customers_disconnected' ? 'active' : '' ?>">
            <span class="nav-icon"><i class="fa-solid fa-user-slash"></i></span>
            <span class="nav-label">Disconnected</span>
          </a>
          <a href="<?= APP_URL ?>/modules/customers/defaulters.php" class="nav-link <?= $activePage === 'customers_defaulters' ? 'active' : '' ?>">
            <span class="nav-icon"><i class="fa-solid fa-triangle-exclamation"></i></span>
            <span class="nav-label">Defaulters</span>
          </a>
        </div>
      </div>

      <!-- METERS -->
      <div class="nav-item <?= $activeSection === 'meters' ? 'open' : '' ?>">
        <div class="nav-link <?= $activeSection === 'meters' ? 'active' : '' ?>" onclick="toggleNav(this)">
          <span class="nav-icon"><i class="fa-solid fa-gauge"></i></span>
          <span class="nav-label">Meters</span>
          <span class="nav-arrow"><i class="fa-solid fa-chevron-right"></i></span>
        </div>
        <div class="nav-submenu <?= $activeSection === 'meters' ? 'open' : '' ?>">
          <a href="<?= APP_URL ?>/modules/meters/index.php" class="nav-link <?= $activePage === 'meters_list' ? 'active' : '' ?>">
            <span class="nav-icon"><i class="fa-regular fa-list-alt"></i></span>
            <span class="nav-label">All Meters</span>
          </a>
          <a href="<?= APP_URL ?>/modules/meters/readings.php" class="nav-link <?= $activePage === 'meter_readings' ? 'active' : '' ?>">
            <span class="nav-icon"><i class="fa-solid fa-pencil"></i></span>
            <span class="nav-label">Enter Readings</span>
          </a>
          <a href="<?= APP_URL ?>/modules/meters/bulk_meters.php" class="nav-link <?= $activePage === 'bulk_meters' ? 'active' : '' ?>">
            <span class="nav-icon"><i class="fa-solid fa-circle-nodes"></i></span>
            <span class="nav-label">Bulk Meters / DMA</span>
          </a>
          <a href="<?= APP_URL ?>/modules/meters/anomalies.php" class="nav-link <?= $activePage === 'meter_anomalies' ? 'active' : '' ?>">
            <span class="nav-icon"><i class="fa-solid fa-circle-exclamation"></i></span>
            <span class="nav-label">Anomalies</span>
          </a>
        </div>
      </div>

      <!-- BILLING -->
      <div class="nav-item <?= $activeSection === 'billing' ? 'open' : '' ?>">
        <div class="nav-link <?= $activeSection === 'billing' ? 'active' : '' ?>" onclick="toggleNav(this)">
          <span class="nav-icon"><i class="fa-solid fa-file-invoice"></i></span>
          <span class="nav-label">Billing</span>
          <span class="nav-arrow"><i class="fa-solid fa-chevron-right"></i></span>
        </div>
        <div class="nav-submenu <?= $activeSection === 'billing' ? 'open' : '' ?>">
          <a href="<?= APP_URL ?>/modules/billing/index.php" class="nav-link <?= $activePage === 'billing_invoices' ? 'active' : '' ?>">
            <span class="nav-icon"><i class="fa-regular fa-file-lines"></i></span>
            <span class="nav-label">Invoices</span>
          </a>
          <a href="<?= APP_URL ?>/modules/billing/generate.php" class="nav-link <?= $activePage === 'billing_generate' ? 'active' : '' ?>">
            <span class="nav-icon"><i class="fa-solid fa-bolt"></i></span>
            <span class="nav-label">Generate Bills</span>
          </a>
          <a href="<?= APP_URL ?>/modules/billing/tariffs.php" class="nav-link <?= $activePage === 'billing_tariffs' ? 'active' : '' ?>">
            <span class="nav-icon"><i class="fa-solid fa-tags"></i></span>
            <span class="nav-label">Tariffs</span>
          </a>
        </div>
      </div>

      <!-- PAYMENTS -->
      <div class="nav-item <?= $activeSection === 'payments' ? 'open' : '' ?>">
        <div class="nav-link <?= $activeSection === 'payments' ? 'active' : '' ?>" onclick="toggleNav(this)">
          <span class="nav-icon"><i class="fa-solid fa-money-bill-wave"></i></span>
          <span class="nav-label">Payments</span>
          <span class="nav-arrow"><i class="fa-solid fa-chevron-right"></i></span>
        </div>
        <div class="nav-submenu <?= $activeSection === 'payments' ? 'open' : '' ?>">
          <a href="<?= APP_URL ?>/modules/payments/index.php" class="nav-link <?= $activePage === 'payments_list' ? 'active' : '' ?>">
            <span class="nav-icon"><i class="fa-solid fa-receipt"></i></span>
            <span class="nav-label">Payment History</span>
          </a>
          <a href="<?= APP_URL ?>/modules/payments/add.php" class="nav-link <?= $activePage === 'payments_add' ? 'active' : '' ?>">
            <span class="nav-icon"><i class="fa-solid fa-plus-circle"></i></span>
            <span class="nav-label">Post Payment</span>
          </a>
          <a href="<?= APP_URL ?>/modules/payments/mpesa.php" class="nav-link <?= $activePage === 'payments_mpesa' ? 'active' : '' ?>">
            <span class="nav-icon"><i class="fa-brands fa-cc-mastercard"></i></span>
            <span class="nav-label">M-Pesa Import</span>
          </a>
        </div>
      </div>

      <div class="nav-divider"></div>
      <span class="nav-section-title">NRW & Revenue</span>

      <!-- WATER LOSS / NRW -->
      <div class="nav-item <?= $activeSection === 'nrw' ? 'open' : '' ?>">
        <div class="nav-link <?= $activeSection === 'nrw' ? 'active' : '' ?>" onclick="toggleNav(this)">
          <span class="nav-icon"><i class="fa-solid fa-droplet-slash"></i></span>
          <span class="nav-label">Water Loss (NRW)</span>
          <span class="nav-arrow"><i class="fa-solid fa-chevron-right"></i></span>
        </div>
        <div class="nav-submenu <?= $activeSection === 'nrw' ? 'open' : '' ?>">
          <a href="<?= APP_URL ?>/modules/nrw/dashboard.php" class="nav-link <?= $activePage === 'nrw_dashboard' ? 'active' : '' ?>">
            <span class="nav-icon"><i class="fa-solid fa-chart-area"></i></span>
            <span class="nav-label">NRW Dashboard</span>
          </a>
          <a href="<?= APP_URL ?>/modules/nrw/production.php" class="nav-link <?= $activePage === 'nrw_production' ? 'active' : '' ?>">
            <span class="nav-icon"><i class="fa-solid fa-faucet"></i></span>
            <span class="nav-label">Water Production</span>
          </a>
          <a href="<?= APP_URL ?>/modules/nrw/analysis.php" class="nav-link <?= $activePage === 'nrw_analysis' ? 'active' : '' ?>">
            <span class="nav-icon"><i class="fa-solid fa-magnifying-glass-chart"></i></span>
            <span class="nav-label">Loss Analysis</span>
          </a>
          <a href="<?= APP_URL ?>/modules/nrw/water_balance.php" class="nav-link <?= $activePage === 'nrw_balance' ? 'active' : '' ?>">
            <span class="nav-icon"><i class="fa-solid fa-scale-balanced"></i></span>
            <span class="nav-label">Water Balance (IWA)</span>
          </a>
        </div>
      </div>

      <!-- REVENUE PROTECTION -->
      <div class="nav-item <?= $activeSection === 'revenue' ? 'open' : '' ?>">
        <div class="nav-link <?= $activeSection === 'revenue' ? 'active' : '' ?>" onclick="toggleNav(this)">
          <span class="nav-icon"><i class="fa-solid fa-shield-halved"></i></span>
          <span class="nav-label">Revenue Protection</span>
          <?php if ($pendingInspections > 0): ?>
            <span class="nav-badge"><?= $pendingInspections ?></span>
          <?php endif; ?>
          <span class="nav-arrow"><i class="fa-solid fa-chevron-right"></i></span>
        </div>
        <div class="nav-submenu <?= $activeSection === 'revenue' ? 'open' : '' ?>">
          <a href="<?= APP_URL ?>/modules/revenue_protection/index.php" class="nav-link <?= $activePage === 'rp_dashboard' ? 'active' : '' ?>">
            <span class="nav-icon"><i class="fa-solid fa-chart-pie"></i></span>
            <span class="nav-label">RP Dashboard</span>
          </a>
          <a href="<?= APP_URL ?>/modules/revenue_protection/inspections.php" class="nav-link <?= $activePage === 'rp_inspections' ? 'active' : '' ?>">
            <span class="nav-icon"><i class="fa-solid fa-clipboard-check"></i></span>
            <span class="nav-label">Inspections</span>
          </a>
          <a href="<?= APP_URL ?>/modules/revenue_protection/illegal.php" class="nav-link <?= $activePage === 'rp_illegal' ? 'active' : '' ?>">
            <span class="nav-icon"><i class="fa-solid fa-ban"></i></span>
            <span class="nav-label">Illegal Connections</span>
          </a>
          <a href="<?= APP_URL ?>/modules/revenue_protection/disconnections.php" class="nav-link <?= $activePage === 'rp_disconnections' ? 'active' : '' ?>">
            <span class="nav-icon"><i class="fa-solid fa-link-slash"></i></span>
            <span class="nav-label">Disconnections</span>
          </a>
        </div>
      </div>

      <!-- REPORTS -->
      <div class="nav-item <?= $activeSection === 'reports' ? 'open' : '' ?>">
        <div class="nav-link <?= $activeSection === 'reports' ? 'active' : '' ?>" onclick="toggleNav(this)">
          <span class="nav-icon"><i class="fa-solid fa-chart-bar"></i></span>
          <span class="nav-label">Reports</span>
          <span class="nav-arrow"><i class="fa-solid fa-chevron-right"></i></span>
        </div>
        <div class="nav-submenu <?= $activeSection === 'reports' ? 'open' : '' ?>">
          <a href="<?= APP_URL ?>/modules/reports/executive.php" class="nav-link <?= $activePage === 'report_executive' ? 'active' : '' ?>">
            <span class="nav-icon"><i class="fa-solid fa-briefcase"></i></span>
            <span class="nav-label">Executive Summary</span>
          </a>
          <a href="<?= APP_URL ?>/modules/reports/revenue.php" class="nav-link <?= $activePage === 'report_revenue' ? 'active' : '' ?>">
            <span class="nav-icon"><i class="fa-solid fa-coins"></i></span>
            <span class="nav-label">Revenue Report</span>
          </a>
          <a href="<?= APP_URL ?>/modules/reports/nrw.php" class="nav-link <?= $activePage === 'report_nrw' ? 'active' : '' ?>">
            <span class="nav-icon"><i class="fa-solid fa-water"></i></span>
            <span class="nav-label">NRW Report</span>
          </a>
          <a href="<?= APP_URL ?>/modules/reports/billing.php" class="nav-link <?= $activePage === 'report_billing' ? 'active' : '' ?>">
            <span class="nav-icon"><i class="fa-solid fa-file-invoice-dollar"></i></span>
            <span class="nav-label">Billing Report</span>
          </a>
          <a href="<?= APP_URL ?>/modules/reports/defaulters.php" class="nav-link <?= $activePage === 'report_defaulters' ? 'active' : '' ?>">
            <span class="nav-icon"><i class="fa-solid fa-user-xmark"></i></span>
            <span class="nav-label">Defaulters Report</span>
          </a>
        </div>
      </div>

      <?php if (in_array('stores', $modulesEnabled) || in_array('procurement', $modulesEnabled) || in_array('assets', $modulesEnabled) || in_array('projects', $modulesEnabled)): ?>
      <div class="nav-divider"></div>
      <span class="nav-section-title">Optional Modules</span>

      <?php if (in_array('stores', $modulesEnabled)): ?>
      <div class="nav-item <?= $activeSection === 'stores' ? 'open' : '' ?>">
        <div class="nav-link <?= $activeSection === 'stores' ? 'active' : '' ?>" onclick="toggleNav(this)">
          <span class="nav-icon"><i class="fa-solid fa-warehouse"></i></span>
          <span class="nav-label">Stores & Inventory</span>
          <span class="module-badge">MOD</span>
          <span class="nav-arrow"><i class="fa-solid fa-chevron-right"></i></span>
        </div>
        <div class="nav-submenu <?= $activeSection === 'stores' ? 'open' : '' ?>">
          <a href="<?= APP_URL ?>/optional/stores/index.php" class="nav-link <?= $activePage === 'stores_inventory' ? 'active' : '' ?>">
            <span class="nav-icon"><i class="fa-solid fa-boxes-stacked"></i></span>
            <span class="nav-label">Stock Inventory</span>
          </a>
          <a href="<?= APP_URL ?>/optional/stores/ledger.php" class="nav-link <?= $activePage === 'stores_ledger' ? 'active' : '' ?>">
            <span class="nav-icon"><i class="fa-solid fa-book"></i></span>
            <span class="nav-label">Stock Ledger</span>
          </a>
        </div>
      </div>
      <?php endif; ?>

      <?php if (in_array('procurement', $modulesEnabled)): ?>
      <div class="nav-item <?= $activeSection === 'procurement' ? 'open' : '' ?>">
        <div class="nav-link <?= $activeSection === 'procurement' ? 'active' : '' ?>" onclick="toggleNav(this)">
          <span class="nav-icon"><i class="fa-solid fa-cart-shopping"></i></span>
          <span class="nav-label">Procurement</span>
          <span class="module-badge">MOD</span>
          <span class="nav-arrow"><i class="fa-solid fa-chevron-right"></i></span>
        </div>
        <div class="nav-submenu <?= $activeSection === 'procurement' ? 'open' : '' ?>">
          <a href="<?= APP_URL ?>/optional/procurement/index.php" class="nav-link <?= $activePage === 'procurement_lpos' ? 'active' : '' ?>">
            <span class="nav-icon"><i class="fa-solid fa-file-invoice"></i></span>
            <span class="nav-label">Purchase Orders (LPOs)</span>
          </a>
        </div>
      </div>
      <?php endif; ?>

      <?php if (in_array('assets', $modulesEnabled)): ?>
      <div class="nav-item <?= $activeSection === 'assets' ? 'open' : '' ?>">
        <div class="nav-link <?= $activeSection === 'assets' ? 'active' : '' ?>" onclick="toggleNav(this)">
          <span class="nav-icon"><i class="fa-solid fa-building-columns"></i></span>
          <span class="nav-label">Asset Management</span>
          <span class="module-badge">MOD</span>
          <span class="nav-arrow"><i class="fa-solid fa-chevron-right"></i></span>
        </div>
        <div class="nav-submenu <?= $activeSection === 'assets' ? 'open' : '' ?>">
          <a href="<?= APP_URL ?>/optional/assets/index.php" class="nav-link <?= $activePage === 'assets_register' ? 'active' : '' ?>">
            <span class="nav-icon"><i class="fa-solid fa-list-check"></i></span>
            <span class="nav-label">Asset Register</span>
          </a>
          <a href="<?= APP_URL ?>/optional/assets/maintenance.php" class="nav-link <?= $activePage === 'assets_maintenance' ? 'active' : '' ?>">
            <span class="nav-icon"><i class="fa-solid fa-screwdriver-wrench"></i></span>
            <span class="nav-label">Maintenance Log</span>
          </a>
        </div>
      </div>
      <?php endif; ?>

      <?php if (in_array('projects', $modulesEnabled)): ?>
      <div class="nav-item <?= $activeSection === 'projects' ? 'open' : '' ?>">
        <div class="nav-link <?= $activeSection === 'projects' ? 'active' : '' ?>" onclick="toggleNav(this)">
          <span class="nav-icon"><i class="fa-solid fa-helmet-safety"></i></span>
          <span class="nav-label">Projects / Capital Works</span>
          <span class="module-badge">MOD</span>
          <span class="nav-arrow"><i class="fa-solid fa-chevron-right"></i></span>
        </div>
        <div class="nav-submenu <?= $activeSection === 'projects' ? 'open' : '' ?>">
          <a href="<?= APP_URL ?>/optional/projects/index.php" class="nav-link <?= $activePage === 'projects_list' ? 'active' : '' ?>">
            <span class="nav-icon"><i class="fa-solid fa-trowel-bricks"></i></span>
            <span class="nav-label">Works Portfolio</span>
          </a>
        </div>
      </div>
      <?php endif; ?>
      <?php endif; ?>

      <div class="nav-divider"></div>
      <span class="nav-section-title">Administration</span>

      <?php if (Auth::can('users')): ?>
      <div class="nav-item">
        <a href="<?= APP_URL ?>/modules/users/index.php" class="nav-link <?= $activeSection === 'users' ? 'active' : '' ?>">
          <span class="nav-icon"><i class="fa-solid fa-user-gear"></i></span>
          <span class="nav-label">User Management</span>
        </a>
      </div>
      <?php endif; ?>

      <div class="nav-item">
        <a href="<?= APP_URL ?>/modules/settings/index.php" class="nav-link <?= $activeSection === 'settings' ? 'active' : '' ?>">
          <span class="nav-icon"><i class="fa-solid fa-gear"></i></span>
          <span class="nav-label">System Settings</span>
        </a>
      </div>

    </div> <!-- /.sidebar-nav -->

    <div class="sidebar-footer">
      <div class="sidebar-user" onclick="window.location='<?= APP_URL ?>/modules/users/profile.php'">
        <div class="sidebar-avatar"><?= strtoupper(substr($user['full_name'], 0, 1)) ?></div>
        <div class="sidebar-user-info">
          <strong><?= htmlspecialchars($user['full_name']) ?></strong>
          <span><?= htmlspecialchars($user['role_name']) ?></span>
        </div>
        <i class="fa-solid fa-ellipsis-vertical" style="color:rgba(255,255,255,0.3);font-size:0.8rem;"></i>
      </div>
    </div>
  </nav>
  <!-- ===== /SIDEBAR ===== -->

  <!-- ===== MAIN CONTENT ===== -->
  <div class="main-content">

    <!-- TOP HEADER -->
    <header class="top-header">
      <div class="header-left">
        <button class="header-btn" id="sidebarToggle" onclick="toggleSidebar()" title="Toggle Sidebar">
          <i class="fa-solid fa-bars"></i>
        </button>
        <div>
          <div class="page-title"><?= htmlspecialchars($pageTitle) ?></div>
        </div>
      </div>
      <div class="header-right">
        <!-- Notifications -->
        <button class="header-btn" title="Alerts">
          <i class="fa-solid fa-bell"></i>
          <?php if ($pendingInspections > 0): ?><span class="header-badge"></span><?php endif; ?>
        </button>
        <!-- Quick search -->
        <button class="header-btn" title="Search" onclick="document.getElementById('globalSearch').focus()">
          <i class="fa-solid fa-search"></i>
        </button>
        <!-- User menu -->
        <div class="header-user" id="userMenuTrigger">
          <div class="header-avatar"><?= strtoupper(substr($user['full_name'], 0, 1)) ?></div>
          <div class="header-user-info">
            <strong><?= htmlspecialchars(explode(' ', $user['full_name'])[0]) ?></strong>
            <span><?= htmlspecialchars($user['role_name']) ?></span>
          </div>
          <i class="fa-solid fa-chevron-down" style="font-size:0.7rem;color:var(--gray-400)"></i>
        </div>
        <!-- Logout -->
        <a href="<?= APP_URL ?>/logout.php" class="header-btn" title="Logout" style="color:var(--danger-500)">
          <i class="fa-solid fa-right-from-bracket"></i>
        </a>
      </div>
    </header>

    <!-- FLASH MESSAGES -->
    <div style="padding: 0 24px; margin-top:8px;">
    <?php if ($flash): ?>
      <div class="alert alert-<?= $flash['type'] ?>" id="flashAlert">
        <span class="alert-icon">
          <?php if ($flash['type'] === 'success') echo '✅';
                elseif ($flash['type'] === 'danger')  echo '❌';
                elseif ($flash['type'] === 'warning') echo '⚠️';
                else echo 'ℹ️'; ?>
        </span>
        <?= htmlspecialchars($flash['message']) ?>
        <button class="alert-close" onclick="this.closest('.alert').remove()">✕</button>
      </div>
    <?php endif; ?>
    </div>

    <!-- PAGE CONTENT STARTS HERE -->
    <main class="page-content animate-fade">
