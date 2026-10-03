<?php
/**
 * THIWASCO MIS - Staff User Profile & Password Change
 */
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';

Auth::check();

$pageTitle = 'My Profile';
$activePage = 'profile';
$activeSection = '';

$userId = Auth::id();
$user = Auth::user();
$errors = [];

// Handle Profile Update POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_profile') {
        $fullName = clean($_POST['full_name'] ?? '');
        $email = clean($_POST['email'] ?? '');
        $phone = clean($_POST['phone'] ?? '');

        if (!empty($fullName)) {
            db()->query("UPDATE users SET full_name = ?, email = ?, phone = ? WHERE user_id = ?", [$fullName, $email ?: null, $phone ?: null, $userId]);
            $_SESSION['user']['full_name'] = $fullName;
            $_SESSION['user']['email'] = $email;
            $_SESSION['user']['phone'] = $phone;
            setFlash('success', 'Profile updated successfully.');
            header('Location: ' . APP_URL . '/modules/users/profile.php');
            exit;
        }
    } elseif ($_POST['action'] === 'change_password') {
        $curPass = $_POST['current_password'] ?? '';
        $newPass = $_POST['new_password'] ?? '';
        $cfmPass = $_POST['confirm_password'] ?? '';

        $dbUser = db()->fetch("SELECT password_hash FROM users WHERE user_id = ?", [$userId]);
        if (!password_verify($curPass, $dbUser['password_hash'])) {
            $errors[] = 'Current password is incorrect.';
        } elseif (strlen($newPass) < 6) {
            $errors[] = 'New password must be at least 6 characters.';
        } elseif ($newPass !== $cfmPass) {
            $errors[] = 'New passwords do not match.';
        } else {
            $hashed = password_hash($newPass, PASSWORD_BCRYPT);
            db()->query("UPDATE users SET password_hash = ? WHERE user_id = ?", [$hashed, $userId]);
            Auth::logAction('Changed Password', 'users', 'User updated account password');
            setFlash('success', 'Password successfully changed.');
            header('Location: ' . APP_URL . '/modules/users/profile.php');
            exit;
        }
    }
}

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
  <div class="page-title-wrap">
    <h1><i class="fa-solid fa-user-circle" style="color:var(--gold);"></i> My Profile & Account Settings</h1>
    <p>Manage your account credentials, contact information and system preferences</p>
  </div>
</div>

<?php if (!empty($errors)): ?>
  <div class="alert alert-danger">
    <i class="fa-solid fa-circle-exclamation"></i>
    <div>
      <?php foreach ($errors as $err): ?>
        <div><?= htmlspecialchars($err) ?></div>
      <?php endforeach; ?>
    </div>
  </div>
<?php endif; ?>

<div class="row" style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;">
  <!-- PROFILE FORM -->
  <div class="card">
    <div class="card-header">
      <h3><i class="fa-solid fa-user" style="color:var(--royal-blue);"></i> Personal Particulars</h3>
    </div>
    <div class="card-body">
      <form method="POST" action="">
        <input type="hidden" name="action" value="update_profile">

        <div class="form-group mb-3">
          <label class="form-label">Username</label>
          <input type="text" class="form-control" value="<?= htmlspecialchars($user['username']) ?>" disabled>
        </div>

        <div class="form-group mb-3">
          <label class="form-label">Full Name <span class="text-danger">*</span></label>
          <input type="text" name="full_name" class="form-control" value="<?= htmlspecialchars($user['full_name']) ?>" required>
        </div>

        <div class="form-group mb-3">
          <label class="form-label">Email Address</label>
          <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email'] ?? '') ?>">
        </div>

        <div class="form-group mb-4">
          <label class="form-label">Phone Number</label>
          <input type="tel" name="phone" class="form-control" value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
        </div>

        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> Update Profile</button>
      </form>
    </div>
  </div>

  <!-- PASSWORD FORM -->
  <div class="card">
    <div class="card-header">
      <h3><i class="fa-solid fa-lock" style="color:var(--gold);"></i> Change Password</h3>
    </div>
    <div class="card-body">
      <form method="POST" action="">
        <input type="hidden" name="action" value="change_password">

        <div class="form-group mb-3">
          <label class="form-label">Current Password <span class="text-danger">*</span></label>
          <input type="password" name="current_password" class="form-control" required>
        </div>

        <div class="form-group mb-3">
          <label class="form-label">New Password <span class="text-danger">*</span></label>
          <input type="password" name="new_password" class="form-control" required>
        </div>

        <div class="form-group mb-4">
          <label class="form-label">Confirm New Password <span class="text-danger">*</span></label>
          <input type="password" name="confirm_password" class="form-control" required>
        </div>

        <button type="submit" class="btn btn-warning"><i class="fa-solid fa-key"></i> Update Password</button>
      </form>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
