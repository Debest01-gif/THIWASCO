<?php
/**
 * THIWASCO MIS - Add New User
 */
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';

Auth::requirePermission('users');

$pageTitle = 'Add User';
$activePage = 'users_add';
$activeSection = 'users';

$roles = db()->fetchAll("SELECT * FROM roles ORDER BY role_id ASC");
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = strtolower(clean($_POST['username'] ?? ''));
    $fullName = clean($_POST['full_name'] ?? '');
    $email = clean($_POST['email'] ?? '');
    $phone = clean($_POST['phone'] ?? '');
    $roleId = (int)($_POST['role_id'] ?? 0);
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($fullName) || empty($password) || $roleId <= 0) {
        $errors[] = 'Please fill in all required fields.';
    } else {
        $exists = db()->fetch("SELECT user_id FROM users WHERE username = ?", [$username]);
        if ($exists) {
            $errors[] = "Username '{$username}' is already in use.";
        } else {
            $hashed = password_hash($password, PASSWORD_BCRYPT);
            db()->query("INSERT INTO users (role_id, username, password_hash, full_name, email, phone, is_active)
                         VALUES (?, ?, ?, ?, ?, ?, 1)",
                         [$roleId, $username, $hashed, $fullName, $email ?: null, $phone ?: null]);
            Auth::logAction('Created User', 'users', "Created staff account for {$username}");
            setFlash('success', "Staff user <strong>{$username}</strong> created successfully.");
            header('Location: ' . APP_URL . '/modules/users/index.php');
            exit;
        }
    }
}

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
  <div class="page-title-wrap">
    <h1><i class="fa-solid fa-user-plus" style="color:var(--gold);"></i> Add New Staff User</h1>
    <p>Create credentials and assign departmental role permissions</p>
  </div>
  <div class="page-actions">
    <a href="<?= APP_URL ?>/modules/users/index.php" class="btn btn-secondary">
      <i class="fa-solid fa-arrow-left"></i> Staff Directory
    </a>
  </div>
</div>

<?php if (!empty($errors)): ?>
  <div class="alert alert-danger">
    <i class="fa-solid fa-circle-exclamation"></i>
    <div>
      <?php foreach ($errors as $e): ?>
        <div><?= htmlspecialchars($e) ?></div>
      <?php endforeach; ?>
    </div>
  </div>
<?php endif; ?>

<div class="card" style="max-width:600px;margin:0 auto;">
  <div class="card-header">
    <h3><i class="fa-solid fa-user-gear" style="color:var(--royal-blue);"></i> User Credentials</h3>
  </div>
  <div class="card-body">
    <form method="POST" action="">
      <div class="form-group mb-3">
        <label class="form-label">Full Name <span class="text-danger">*</span></label>
        <input type="text" name="full_name" class="form-control" placeholder="e.g. Grace Njeri Gitonga" required>
      </div>

      <div class="row" style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
        <div class="form-group mb-3">
          <label class="form-label">Username <span class="text-danger">*</span></label>
          <input type="text" name="username" class="form-control" placeholder="e.g. gnjeri" required>
        </div>
        <div class="form-group mb-3">
          <label class="form-label">Role <span class="text-danger">*</span></label>
          <select name="role_id" class="form-control" required>
            <?php foreach ($roles as $r): ?>
              <option value="<?= $r['role_id'] ?>"><?= htmlspecialchars($r['role_name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="row" style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
        <div class="form-group mb-3">
          <label class="form-label">Mobile Phone</label>
          <input type="tel" name="phone" class="form-control" placeholder="07XXXXXXXX">
        </div>
        <div class="form-group mb-3">
          <label class="form-label">Email</label>
          <input type="email" name="email" class="form-control" placeholder="gnjeri@thiwasco.co.ke">
        </div>
      </div>

      <div class="form-group mb-4">
        <label class="form-label">Initial Password <span class="text-danger">*</span></label>
        <input type="password" name="password" class="form-control" required>
      </div>

      <div class="form-actions" style="display:flex;justify-content:flex-end;gap:1rem;">
        <a href="<?= APP_URL ?>/modules/users/index.php" class="btn btn-secondary">Cancel</a>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> Create Staff Account</button>
      </div>
    </form>
  </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
