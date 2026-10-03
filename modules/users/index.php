<?php
/**
 * THIWASCO MIS - System Users & Access Control
 */
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';

Auth::requirePermission('users');

$pageTitle = 'User Management';
$activePage = 'users_list';
$activeSection = 'users';

// Fetch roles
$roles = db()->fetchAll("SELECT * FROM roles ORDER BY role_id ASC");

// Handle Actions POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    // 1. ADD USER
    if ($_POST['action'] === 'add_user') {
        $username = strtolower(clean($_POST['username'] ?? ''));
        $fullName = clean($_POST['full_name'] ?? '');
        $email = clean($_POST['email'] ?? '');
        $phone = clean($_POST['phone'] ?? '');
        $roleId = (int)($_POST['role_id'] ?? 0);
        $password = $_POST['password'] ?? '';

        if (empty($username) || empty($fullName) || empty($password) || $roleId <= 0) {
            setFlash('danger', 'Username, full name, role, and default password are required.');
        } else {
            $exists = db()->fetch("SELECT user_id FROM users WHERE username = ?", [$username]);
            if ($exists) {
                setFlash('danger', "Username '{$username}' already taken.");
            } else {
                $hashed = password_hash($password, PASSWORD_BCRYPT);
                db()->query("INSERT INTO users (role_id, username, password_hash, full_name, email, phone, is_active)
                             VALUES (?, ?, ?, ?, ?, ?, 1)",
                             [$roleId, $username, $hashed, $fullName, $email ?: null, $phone ?: null]);
                Auth::logAction('Created User', 'users', "Created staff account for {$username}");
                setFlash('success', "Staff user <strong>{$username}</strong> created successfully.");
            }
        }
        header('Location: ' . APP_URL . '/modules/users/index.php');
        exit;
    }

    // 2. UPDATE USER
    if ($_POST['action'] === 'update_user') {
        $userId = (int)($_POST['user_id'] ?? 0);
        $fullName = clean($_POST['full_name'] ?? '');
        $email = clean($_POST['email'] ?? '');
        $phone = clean($_POST['phone'] ?? '');
        $roleId = (int)($_POST['role_id'] ?? 0);
        $isActive = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 1;
        $newPassword = trim($_POST['password'] ?? '');

        if ($userId <= 0 || empty($fullName) || $roleId <= 0) {
            setFlash('danger', 'Invalid user details.');
        } else {
            if (!empty($newPassword)) {
                $hashed = password_hash($newPassword, PASSWORD_BCRYPT);
                db()->query("UPDATE users SET full_name = ?, email = ?, phone = ?, role_id = ?, is_active = ?, password_hash = ? WHERE user_id = ?",
                    [$fullName, $email ?: null, $phone ?: null, $roleId, $isActive, $hashed, $userId]);
            } else {
                db()->query("UPDATE users SET full_name = ?, email = ?, phone = ?, role_id = ?, is_active = ? WHERE user_id = ?",
                    [$fullName, $email ?: null, $phone ?: null, $roleId, $isActive, $userId]);
            }
            Auth::logAction('Updated User', 'users', "Updated details for staff ID {$userId}");
            setFlash('success', "User account for <strong>{$fullName}</strong> updated successfully.");
        }
        header('Location: ' . APP_URL . '/modules/users/index.php');
        exit;
    }

    // 3. TOGGLE STATUS
    if ($_POST['action'] === 'toggle_status') {
        $userId = (int)($_POST['user_id'] ?? 0);
        $targetStatus = (int)($_POST['status'] ?? 0);

        if ($userId === (int)Auth::id()) {
            setFlash('danger', 'You cannot disable your own active account.');
        } elseif ($userId === 1) {
            setFlash('danger', 'Primary administrator account cannot be disabled.');
        } else {
            db()->query("UPDATE users SET is_active = ? WHERE user_id = ?", [$targetStatus, $userId]);
            Auth::logAction('Changed User Status', 'users', "Changed status of user #{$userId} to " . ($targetStatus ? 'Active' : 'Disabled'));
            setFlash('success', "User account status updated.");
        }
        header('Location: ' . APP_URL . '/modules/users/index.php');
        exit;
    }

    // 4. DELETE USER
    if ($_POST['action'] === 'delete_user') {
        $userId = (int)($_POST['user_id'] ?? 0);

        if ($userId === (int)Auth::id()) {
            setFlash('danger', 'You cannot delete your own logged-in account.');
        } elseif ($userId === 1) {
            setFlash('danger', 'The super administrator account cannot be deleted.');
        } else {
            $user = db()->fetch("SELECT username FROM users WHERE user_id = ?", [$userId]);
            if ($user) {
                try {
                    db()->query("DELETE FROM users WHERE user_id = ?", [$userId]);
                    Auth::logAction('Deleted User', 'users', "Deleted user {$user['username']}");
                    setFlash('success', "Staff account <strong>{$user['username']}</strong> permanently deleted.");
                } catch (Exception $e) {
                    // If foreign keys prevent deletion, deactivate instead
                    db()->query("UPDATE users SET is_active = 0 WHERE user_id = ?", [$userId]);
                    setFlash('warning', "User has related activity records and was deactivated instead of deleted.");
                }
            }
        }
        header('Location: ' . APP_URL . '/modules/users/index.php');
        exit;
    }
}

// Search and Role Filters
$search = clean($_GET['search'] ?? '');
$roleFilter = (int)($_GET['role_id'] ?? 0);

$where = ["1=1"];
$params = [];

if (!empty($search)) {
    $where[] = "(u.username LIKE ? OR u.full_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
    $like = "%{$search}%";
    $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like;
}
if ($roleFilter > 0) {
    $where[] = "u.role_id = ?";
    $params[] = $roleFilter;
}

$whereSql = implode(" AND ", $where);

// Fetch users
$users = db()->fetchAll("SELECT u.*, r.role_name 
                         FROM users u 
                         JOIN roles r ON u.role_id = r.role_id 
                         WHERE {$whereSql}
                         ORDER BY u.user_id ASC", $params);

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
  <div class="page-title-wrap">
    <h1><i class="fa-solid fa-user-gear" style="color:var(--gold-500);"></i> User Accounts & Access Control</h1>
    <p>Manage system operators, meter readers, billing cashiers, inspectors and role-based permissions</p>
  </div>
  <div class="page-actions">
    <button onclick="openModal('addUserModal')" class="btn btn-primary">
      <i class="fa-solid fa-user-plus"></i> Add New User
    </button>
  </div>
</div>

<!-- FILTER BAR -->
<div class="card mb-4">
  <div class="card-body">
    <form method="GET" action="" class="filter-bar" style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
      <div style="flex:1;min-width:200px;">
        <input type="text" name="search" class="form-control" placeholder="Search by name, username, email, phone..." value="<?= htmlspecialchars($search) ?>">
      </div>
      <div style="min-width:180px;">
        <select name="role_id" class="form-control">
          <option value="">-- All Roles --</option>
          <?php foreach ($roles as $r): ?>
            <option value="<?= $r['role_id'] ?>" <?= $roleFilter == $r['role_id'] ? 'selected' : '' ?>><?= htmlspecialchars($r['role_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <button type="submit" class="btn btn-primary"><i class="fa-solid fa-filter"></i> Filter</button>
      <?php if (!empty($search) || $roleFilter > 0): ?>
        <a href="<?= APP_URL ?>/modules/users/index.php" class="btn btn-secondary"><i class="fa-solid fa-rotate-left"></i> Reset</a>
      <?php endif; ?>
    </form>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <h3><i class="fa-solid fa-users" style="color:var(--primary-500);"></i> Staff Directory</h3>
    <span class="badge badge-info"><?= count($users) ?> Staff Registered</span>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table">
        <thead>
          <tr>
            <th>Staff Name</th>
            <th>Username</th>
            <th>Role</th>
            <th>Email</th>
            <th>Phone</th>
            <th>Last Login</th>
            <th>Status</th>
            <th class="text-right">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($users)): ?>
            <tr>
              <td colspan="8" class="text-center" style="padding:40px;color:var(--gray-500);">
                <i class="fa-solid fa-user-slash" style="font-size:2rem;margin-bottom:10px;display:block;"></i>
                No user accounts found matching your criteria.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($users as $u): ?>
              <tr>
                <td>
                  <div style="display:flex;align-items:center;gap:0.75rem;">
                    <div style="width:36px;height:36px;border-radius:50%;background:var(--primary-800);color:var(--gold-300);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:0.9rem;">
                      <?= strtoupper(substr($u['full_name'], 0, 1)) ?>
                    </div>
                    <div>
                      <strong><?= htmlspecialchars($u['full_name']) ?></strong>
                      <?php if ($u['user_id'] == Auth::id()): ?>
                        <span class="badge badge-warning" style="font-size:0.7rem;margin-left:4px;">You</span>
                      <?php endif; ?>
                    </div>
                  </div>
                </td>
                <td><code><?= htmlspecialchars($u['username']) ?></code></td>
                <td><span class="badge badge-primary"><?= htmlspecialchars($u['role_name']) ?></span></td>
                <td><?= htmlspecialchars($u['email'] ?? '-') ?></td>
                <td><?= htmlspecialchars($u['phone'] ?? '-') ?></td>
                <td><?= formatDate($u['last_login'], 'd/m/Y H:i') ?></td>
                <td>
                  <?= $u['is_active'] ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-danger">Disabled</span>' ?>
                </td>
                <td class="text-right">
                  <div style="display:inline-flex;gap:6px;">
                    <button class="btn btn-secondary btn-sm" onclick='openEditModal(<?= json_encode($u) ?>)' title="Edit User">
                      <i class="fa-solid fa-pen-to-square"></i> Edit
                    </button>
                    <?php if ($u['user_id'] != 1 && $u['user_id'] != Auth::id()): ?>
                      <form method="POST" action="" style="display:inline;" onsubmit="return confirm('Are you sure you want to permanently delete user <?= htmlspecialchars(addslashes($u['username'])) ?>?');">
                        <input type="hidden" name="action" value="delete_user">
                        <input type="hidden" name="user_id" value="<?= $u['user_id'] ?>">
                        <button type="submit" class="btn btn-danger btn-sm" title="Delete User">
                          <i class="fa-solid fa-trash"></i>
                        </button>
                      </form>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- ADD USER MODAL -->
<div id="addUserModal" class="modal-overlay">
  <div class="modal" style="max-width: 520px;">
    <div class="modal-header">
      <h3><i class="fa-solid fa-user-plus" style="color:var(--gold-300);"></i> Create New Staff Account</h3>
      <button class="modal-close" onclick="closeModal('addUserModal')">&times;</button>
    </div>
    <form method="POST" action="">
      <input type="hidden" name="action" value="add_user">
      <div class="modal-body">
        <div class="form-group mb-3">
          <label class="form-label">Full Name <span class="text-danger">*</span></label>
          <input type="text" name="full_name" class="form-control" placeholder="e.g. Mary Wanjiku Njoroge" required>
        </div>
        <div class="row" style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
          <div class="form-group mb-3">
            <label class="form-label">Username <span class="text-danger">*</span></label>
            <input type="text" name="username" class="form-control" placeholder="e.g. mwanjiku" required>
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
            <label class="form-label">Phone</label>
            <input type="tel" name="phone" class="form-control" placeholder="07XXXXXXXX">
          </div>
          <div class="form-group mb-3">
            <label class="form-label">Email</label>
            <input type="email" name="email" class="form-control" placeholder="staff@thiwasco.co.ke">
          </div>
        </div>
        <div class="form-group mb-3">
          <label class="form-label">Default Password <span class="text-danger">*</span></label>
          <input type="password" name="password" class="form-control" placeholder="Create a secure temporary password" required>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('addUserModal')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> Create Account</button>
      </div>
    </form>
  </div>
</div>

<!-- EDIT USER MODAL -->
<div id="editUserModal" class="modal-overlay">
  <div class="modal" style="max-width: 520px;">
    <div class="modal-header">
      <h3><i class="fa-solid fa-user-pen" style="color:var(--gold-300);"></i> Edit Staff Account</h3>
      <button class="modal-close" onclick="closeModal('editUserModal')">&times;</button>
    </div>
    <form method="POST" action="">
      <input type="hidden" name="action" value="update_user">
      <input type="hidden" name="user_id" id="edit_user_id">
      <div class="modal-body">
        <div class="form-group mb-3">
          <label class="form-label">Username</label>
          <input type="text" id="edit_username" class="form-control" readonly style="background:var(--gray-100);">
        </div>
        <div class="form-group mb-3">
          <label class="form-label">Full Name <span class="text-danger">*</span></label>
          <input type="text" name="full_name" id="edit_full_name" class="form-control" required>
        </div>
        <div class="row" style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
          <div class="form-group mb-3">
            <label class="form-label">Role <span class="text-danger">*</span></label>
            <select name="role_id" id="edit_role_id" class="form-control" required>
              <?php foreach ($roles as $r): ?>
                <option value="<?= $r['role_id'] ?>"><?= htmlspecialchars($r['role_name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group mb-3">
            <label class="form-label">Status <span class="text-danger">*</span></label>
            <select name="is_active" id="edit_is_active" class="form-control" required>
              <option value="1">Active</option>
              <option value="0">Disabled</option>
            </select>
          </div>
        </div>
        <div class="row" style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
          <div class="form-group mb-3">
            <label class="form-label">Phone</label>
            <input type="tel" name="phone" id="edit_phone" class="form-control">
          </div>
          <div class="form-group mb-3">
            <label class="form-label">Email</label>
            <input type="email" name="email" id="edit_email" class="form-control">
          </div>
        </div>
        <div class="form-group mb-3">
          <label class="form-label">Reset Password <small style="color:var(--gray-500);">(Leave blank to keep current)</small></label>
          <input type="password" name="password" class="form-control" placeholder="Enter new password to reset">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('editUserModal')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check"></i> Save Changes</button>
      </div>
    </form>
  </div>
</div>

<script>
function openEditModal(user) {
  document.getElementById('edit_user_id').value = user.user_id;
  document.getElementById('edit_username').value = user.username;
  document.getElementById('edit_full_name').value = user.full_name;
  document.getElementById('edit_role_id').value = user.role_id;
  document.getElementById('edit_is_active').value = user.is_active;
  document.getElementById('edit_phone').value = user.phone || '';
  document.getElementById('edit_email').value = user.email || '';
  openModal('editUserModal');
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
