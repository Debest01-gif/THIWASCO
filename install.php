<?php
/**
 * THIWASCO - Database Installer
 * Run this once to set up the database via browser
 * Access: http://localhost/THIWASCO/install.php
 */

// Load unified config (sets DB_* constants from environment)
require_once __DIR__ . '/includes/config.php';

$step = (int)($_GET['step'] ?? 0);
$messages = [];
$errors = [];

if (isset($_POST['install'])) {
    try {
        // Step 1: Create DB
        $pdo = new PDO("mysql:host=" . DB_HOST . ";charset=utf8mb4", DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `" . DB_NAME . "`");
        $messages[] = "✅ Database <strong>" . DB_NAME . "</strong> created successfully.";

        // Step 2: Run SQL file
        $sql = file_get_contents(__DIR__ . '/database/thiwasco.sql');

        // Remove CREATE DATABASE and USE statements (already done)
        $sql = preg_replace('/^CREATE\s+DATABASE.*?;/im', '', $sql);
        $sql = preg_replace('/^USE\s+.*?;/im', '', $sql);

        // Split into statements
        $statements = array_filter(array_map('trim', explode(';', $sql)));

        $tableCount = 0;
        foreach ($statements as $stmt) {
            if (!empty($stmt) && !preg_match('/^--/', $stmt) && !preg_match('/^\/\*/', $stmt)) {
                $pdo->exec($stmt);
                if (stripos($stmt, 'CREATE TABLE') !== false) $tableCount++;
            }
        }
        $messages[] = "✅ Created <strong>{$tableCount} tables</strong> with all initial data.";

        // Create uploads directories
        $dirs = ['uploads/meter_photos','uploads/inspection_photos','uploads/profile_photos'];
        foreach ($dirs as $dir) {
            if (!is_dir(__DIR__ . '/' . $dir)) {
                mkdir(__DIR__ . '/' . $dir, 0755, true);
                $messages[] = "✅ Created upload directory: <code>{$dir}</code>";
            }
        }

        // Create .htaccess for uploads
        file_put_contents(__DIR__ . '/uploads/.htaccess', "Options -Indexes\n");

        $messages[] = "🎉 <strong>Installation complete!</strong> THIWASCO is ready.";
        $success = true;

    } catch (PDOException $e) {
        $errors[] = "❌ Database error: " . htmlspecialchars($e->getMessage());
    } catch (Exception $e) {
        $errors[] = "❌ Error: " . htmlspecialchars($e->getMessage());
    }
}

// Pre-flight checks
$checks = [
    'PHP Version (≥ 7.4)' => version_compare(PHP_VERSION, '7.4.0', '>='),
    'PDO Extension'        => extension_loaded('pdo'),
    'PDO MySQL Extension'  => extension_loaded('pdo_mysql'),
    'SQL File Exists'      => file_exists(__DIR__ . '/database/thiwasco.sql'),
    'Uploads Writable'     => is_writable(__DIR__),
];

$allChecksPassed = !in_array(false, $checks);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>THIWASCO — System Installer</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Outfit:wght@600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    *{box-sizing:border-box;margin:0;padding:0}
    body{font-family:'Inter',sans-serif;background:linear-gradient(135deg,#0d1f3c,#1a407e);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
    .installer{background:#fff;border-radius:20px;box-shadow:0 20px 60px rgba(0,0,0,0.3);max-width:640px;width:100%;overflow:hidden}
    .installer-header{background:linear-gradient(135deg,#0d1f3c,#163568);padding:32px;text-align:center;color:#fff}
    .installer-header h1{font-family:'Outfit',sans-serif;font-size:1.7rem;font-weight:700;margin-bottom:6px}
    .installer-header p{color:rgba(255,255,255,0.6);font-size:0.9rem}
    .installer-body{padding:32px}
    .check-item{display:flex;align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid #f3f4f6;font-size:0.9rem}
    .check-ok{color:#22a05a;font-size:1.1rem}
    .check-fail{color:#dc2626;font-size:1.1rem}
    .btn-install{width:100%;padding:14px;background:linear-gradient(135deg,#1a407e,#163568);color:#fff;border:none;border-radius:10px;font-size:1rem;font-weight:700;cursor:pointer;margin-top:24px;font-family:'Outfit',sans-serif;transition:all 0.2s}
    .btn-install:hover{transform:translateY(-2px);box-shadow:0 6px 20px rgba(26,64,126,0.4)}
    .btn-install:disabled{opacity:0.5;cursor:not-allowed;transform:none}
    .msg{padding:10px 14px;border-radius:8px;font-size:0.88rem;margin-bottom:8px}
    .msg-ok{background:#edfaf2;border:1px solid #d4f0e1;color:#1a7a4a}
    .msg-err{background:#fff5f5;border:1px solid #fee2e2;color:#b91c1c}
    .go-btn{display:block;width:100%;padding:14px;background:linear-gradient(135deg,#c9890a,#f0b429);color:#0a1628;border:none;border-radius:10px;font-size:1rem;font-weight:700;cursor:pointer;margin-top:16px;text-align:center;text-decoration:none;font-family:'Outfit',sans-serif}
    .note{background:#fdf8ec;border:1px solid #f5cc6b;border-radius:8px;padding:12px 14px;font-size:0.83rem;color:#8b5e00;margin-top:16px}
  </style>
</head>
<body>
<div class="installer">
  <div class="installer-header">
    <div style="font-size:2.5rem;margin-bottom:12px;">💧</div>
    <h1>THIWASCO Water MIS</h1>
    <p>System Installer — Version 1.0</p>
  </div>
  <div class="installer-body">

    <?php if (!empty($messages) && !empty($success ?? false)): ?>
      <?php foreach ($messages as $m): ?>
      <div class="msg msg-ok"><?= $m ?></div>
      <?php endforeach; ?>
      <div class="note">
        <strong>⚠️ Security Notice:</strong> Delete or rename <code>install.php</code> after installation to prevent unauthorized re-installation.
        <br><br>
        <strong>Default Login:</strong><br>
        Username: <code>admin</code> &nbsp;|&nbsp; Password: <code>password</code>
        <br><em>Change the password immediately after first login.</em>
      </div>
      <a href="/THIWASCO/login.php" class="go-btn">🚀 Launch THIWASCO MIS →</a>

    <?php elseif (!empty($errors)): ?>
      <?php foreach ($errors as $e): ?>
      <div class="msg msg-err"><?= $e ?></div>
      <?php endforeach; ?>
      <p style="color:#6b7280;font-size:0.88rem;margin-top:12px;">Please fix the errors above and try again.</p>
      <form method="POST">
        <button type="submit" name="install" class="btn-install">Retry Installation</button>
      </form>

    <?php else: ?>
      <h3 style="color:#0d1f3c;margin-bottom:16px;font-family:'Outfit',sans-serif;">Pre-Installation Checks</h3>

      <?php foreach ($checks as $label => $pass): ?>
      <div class="check-item">
        <span class="<?= $pass ? 'check-ok' : 'check-fail' ?>">
          <?= $pass ? '✅' : '❌' ?>
        </span>
        <span style="<?= $pass ? '' : 'color:#dc2626;font-weight:600;' ?>"><?= $label ?></span>
      </div>
      <?php endforeach; ?>

      <div style="background:#e8f1fb;border-radius:10px;padding:16px;margin-top:20px;">
        <h4 style="color:#1a407e;margin-bottom:8px;font-size:0.9rem;">Installation will:</h4>
        <ul style="padding-left:20px;font-size:0.85rem;color:#374151;line-height:1.8;">
          <li>Create MySQL database: <code><?= DB_NAME ?></code></li>
          <li>Create all system tables (20+ tables)</li>
          <li>Load initial data (tariffs, zones, roles, admin user)</li>
          <li>Create file upload directories</li>
        </ul>
      </div>

      <form method="POST">
        <button type="submit" name="install" class="btn-install" <?= !$allChecksPassed ? 'disabled' : '' ?>>
          <i class="fa-solid fa-database"></i>
          <?= $allChecksPassed ? 'Install THIWASCO Database' : 'Fix Errors Before Installing' ?>
        </button>
      </form>

      <?php if (!$allChecksPassed): ?>
      <p style="color:#dc2626;font-size:0.85rem;margin-top:12px;text-align:center;">
        Please resolve the failed checks above before proceeding.
      </p>
      <?php endif; ?>
    <?php endif; ?>

  </div>
</div>
</body>
</html>
