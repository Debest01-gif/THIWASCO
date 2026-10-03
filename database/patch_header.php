<?php
$file = 'includes/header.php';
$content = file_get_contents($file);

$old = <<<'OLDBLOCK'
      <?php if (in_array('stores', $modulesEnabled)): ?>
      <div class="nav-item">
        <a href="<?= APP_URL ?>/optional/stores/index.php" class="nav-link <?= $activeSection === 'stores' ? 'active' : '' ?>">
          <span class="nav-icon"><i class="fa-solid fa-warehouse"></i></span>
          <span class="nav-label">Stores & Inventory</span>
          <span class="module-badge">MOD</span>
        </a>
      </div>
      <?php endif; ?>

      <?php if (in_array('procurement', $modulesEnabled)): ?>
      <div class="nav-item">
        <a href="<?= APP_URL ?>/optional/procurement/index.php" class="nav-link <?= $activeSection === 'procurement' ? 'active' : '' ?>">
          <span class="nav-icon"><i class="fa-solid fa-cart-shopping"></i></span>
          <span class="nav-label">Procurement</span>
          <span class="module-badge">MOD</span>
        </a>
      </div>
      <?php endif; ?>

      <?php if (in_array('assets', $modulesEnabled)): ?>
      <div class="nav-item">
        <a href="<?= APP_URL ?>/optional/assets/index.php" class="nav-link <?= $activeSection === 'assets' ? 'active' : '' ?>">
          <span class="nav-icon"><i class="fa-solid fa-building-columns"></i></span>
          <span class="nav-label">Asset Management</span>
          <span class="module-badge">MOD</span>
        </a>
      </div>
      <?php endif; ?>

      <?php if (in_array('projects', $modulesEnabled)): ?>
      <div class="nav-item">
        <a href="<?= APP_URL ?>/optional/projects/index.php" class="nav-link <?= $activeSection === 'projects' ? 'active' : '' ?>">
          <span class="nav-icon"><i class="fa-solid fa-helmet-safety"></i></span>
          <span class="nav-label">Projects / Capital Works</span>
          <span class="module-badge">MOD</span>
        </a>
      </div>
      <?php endif; ?>
      <?php endif; ?>
OLDBLOCK;

$new = <<<'NEWBLOCK'
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
NEWBLOCK;

if (strpos($content, $old) !== false) {
    $content = str_replace($old, $new, $content);
    file_put_contents($file, $content);
    echo "SUCCESS: Header nav updated.\n";
} else {
    echo "NOT FOUND: trying line-by-line...\n";
    // Check what's actually on line 272
    $lines = explode("\n", $content);
    for ($i = 270; $i < 312; $i++) {
        echo ($i+1) . ": " . $lines[$i] . "\n";
    }
}
