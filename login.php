<?php
require_once __DIR__ . '/includes/auth.php';

// Redirect if already logged in
if (Auth::isLoggedIn()) {
    header('Location: ' . APP_URL . '/index.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } else {
        try {
            $result = Auth::login($username, $password);
            if ($result['success']) {
                $redirect = $_GET['redirect'] ?? (APP_URL . '/index.php');
                header('Location: ' . $redirect);
                exit;
            } else {
                $error = $result['message'];
            }
        } catch (Throwable $e) {
            $error = 'Login error: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="THIWASCO Water Management Information System Login">
  <title>Login | THIWASCO Water MIS</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Outfit:wght@400;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
</head>
<body>
<div class="login-page">

  <!-- LEFT BANNER -->
  <div class="login-banner">
    <div class="login-banner-logo">💧</div>
    <h1>THIWASCO Water MIS</h1>
    <p>Thika Water and Sewerage Company<br>Management Information System</p>

    <ul class="login-banner-features">
      <li>
        <span class="icon"><i class="fa-solid fa-droplet-slash"></i></span>
        Water Loss (NRW) Monitoring & Analysis
      </li>
      <li>
        <span class="icon"><i class="fa-solid fa-gauge"></i></span>
        Meter Management & Billing Automation
      </li>
      <li>
        <span class="icon"><i class="fa-solid fa-shield-halved"></i></span>
        Revenue Protection & Field Inspections
      </li>
      <li>
        <span class="icon"><i class="fa-solid fa-money-bill-wave"></i></span>
        Payments, M-Pesa & Receipting
      </li>
      <li>
        <span class="icon"><i class="fa-solid fa-chart-bar"></i></span>
        Executive Reports & Dashboards
      </li>
      <li>
        <span class="icon"><i class="fa-solid fa-puzzle-piece"></i></span>
        Modular: Stores, Procurement, Assets
      </li>
    </ul>

    <div style="margin-top:40px;padding:14px 20px;background:rgba(255,255,255,0.06);border-radius:10px;border:1px solid rgba(201,137,10,0.25);text-align:center;position:relative;z-index:1;max-width:340px;">
      <p style="font-size:0.82rem;color:rgba(255,255,255,0.5);margin-bottom:4px;">Licensed To</p>
      <strong style="color:var(--gold-300);font-family:'Outfit',sans-serif;font-size:0.95rem;">Thika Water &amp; Sewerage Company</strong>
      <p style="font-size:0.75rem;color:rgba(255,255,255,0.35);margin-top:4px;">WASREB Regulated | Version 1.0 | 2026</p>
    </div>
  </div>

  <!-- RIGHT FORM -->
  <div class="login-form-side">
    <div class="login-form-container animate-fade">
      <div style="display:flex;align-items:center;gap:12px;margin-bottom:28px;">
        <div style="width:44px;height:44px;background:linear-gradient(135deg,var(--primary-500),var(--primary-700));border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1.3rem;box-shadow:0 4px 12px rgba(26,64,126,0.3);">💧</div>
        <div>
          <div style="font-weight:700;color:var(--primary-800);font-size:1rem;">THIWASCO MIS</div>
          <div style="font-size:0.75rem;color:var(--gray-500);">Secure System Login</div>
        </div>
      </div>

      <h2>Welcome Back</h2>
      <p class="subtitle">Sign in to access the Water Management System</p>

      <?php if ($error): ?>
      <div class="alert alert-danger">
        <span class="alert-icon"><i class="fa-solid fa-circle-exclamation"></i></span>
        <?= htmlspecialchars($error) ?>
      </div>
      <?php endif; ?>

      <form method="POST" id="loginForm" autocomplete="off">
        <div class="form-group">
          <label for="username">Username or Email</label>
          <div class="input-icon-wrapper">
            <span class="input-icon"><i class="fa-solid fa-user"></i></span>
            <input
              type="text" id="username" name="username"
              class="form-control"
              placeholder="Enter username or email"
              value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
              required autofocus>
          </div>
        </div>

        <div class="form-group">
          <label for="password">Password</label>
          <div class="input-icon-wrapper" style="position:relative;">
            <span class="input-icon"><i class="fa-solid fa-lock"></i></span>
            <input
              type="password" id="password" name="password"
              class="form-control"
              placeholder="Enter your password"
              required>
            <button type="button" onclick="togglePassword()" id="togglePwdBtn"
              style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--gray-400);font-size:0.9rem;">
              <i class="fa-regular fa-eye" id="pwdEyeIcon"></i>
            </button>
          </div>
        </div>

        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;">
          <label style="display:flex;align-items:center;gap:8px;font-size:0.85rem;color:var(--gray-600);cursor:pointer;">
            <input type="checkbox" name="remember" style="width:16px;height:16px;accent-color:var(--primary-500);">
            Remember me
          </label>
          <a href="#" style="font-size:0.85rem;color:var(--primary-500);font-weight:500;">Forgot password?</a>
        </div>

        <button type="submit" class="btn btn-primary btn-block btn-lg" id="loginBtn">
          <i class="fa-solid fa-right-to-bracket"></i>
          Sign In
        </button>
      </form>

      <div style="margin-top:32px;padding-top:24px;border-top:1px solid var(--gray-200);">
        <p style="text-align:center;font-size:0.82rem;color:var(--gray-500);">
          Need help? Contact your system administrator
        </p>
        <p style="text-align:center;font-size:0.78rem;color:var(--gray-400);margin-top:6px;">
          <i class="fa-solid fa-phone"></i> +254 67 20010 &nbsp;|&nbsp;
          <i class="fa-solid fa-envelope"></i> info@thiwasco.co.ke
        </p>
      </div>
    </div>
  </div>
</div>

<script>
function togglePassword() {
  const pwd = document.getElementById('password');
  const icon = document.getElementById('pwdEyeIcon');
  if (pwd.type === 'password') {
    pwd.type = 'text';
    icon.classList.replace('fa-eye', 'fa-eye-slash');
  } else {
    pwd.type = 'password';
    icon.classList.replace('fa-eye-slash', 'fa-eye');
  }
}
document.getElementById('loginForm').addEventListener('submit', function() {
  const btn = document.getElementById('loginBtn');
  btn.disabled = true;
  btn.innerHTML = '<i class="fa-solid fa-spinner spinner"></i> Signing in...';
});
</script>
</body>
</html>
