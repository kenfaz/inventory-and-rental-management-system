<?php
// ============================================================
// modules/users/edit.php
// Edit staff account name, email, role, and optionally password
// Admin only
// ============================================================

require_once __DIR__ . '/../../../includes/auth-guard.php';
require_once __DIR__ . '/../../../includes/role-guard.php';
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../config/constants.php';
require_once __DIR__ . '/../../../helpers/audit-helper.php';

$pageTitle  = 'Edit Account';
$activePage = 'users';

$userId = (int)($_GET['id'] ?? 0);
if ($userId === 0) {
    header('Location: /tailorshop/modules/users/index.php');
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM users WHERE user_id = ?');
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    $_SESSION['error'] = 'User not found.';
    header('Location: /tailorshop/modules/users/index.php');
    exit;
}

$errors = [];
$values = [
    'full_name' => $user['full_name'],
    'email'     => $user['email'],
    'role'      => $user['role'],
    'password'  => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $values = [
        'full_name' => trim($_POST['full_name'] ?? ''),
        'email'     => trim($_POST['email']     ?? ''),
        'role'      => trim($_POST['role']      ?? 'staff'),
        'password'  => trim($_POST['password']  ?? ''),
    ];

    if ($values['full_name'] === '')
        $errors['full_name'] = 'Full name is required.';
    if ($values['email'] === '' || !filter_var($values['email'], FILTER_VALIDATE_EMAIL))
        $errors['email'] = 'A valid email address is required.';
    if (!in_array($values['role'], ['admin','staff']))
        $errors['role'] = 'Invalid role.';

    // Only validate password if one was entered
    if ($values['password'] !== '') {
        if (strlen($values['password']) < PASSWORD_MIN_LENGTH)
            $errors['password'] = 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters.';
        elseif (!preg_match('/[A-Z]/', $values['password']))
            $errors['password'] = 'Password must include an uppercase letter.';
        elseif (!preg_match('/[0-9]/', $values['password']))
            $errors['password'] = 'Password must include a number.';
    }

    if (empty($errors['email'])) {
        $dup = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = ? AND user_id != ?');
        $dup->execute([$values['email'], $userId]);
        if ($dup->fetchColumn() > 0)
            $errors['email'] = 'Another account already uses this email.';
    }

    if (empty($errors)) {
        $old = ['full_name'=>$user['full_name'],'email'=>$user['email'],'role'=>$user['role']];

        if ($values['password'] !== '') {
            $pdo->prepare('
                UPDATE users SET full_name=?, email=?, role=?, password=?
                WHERE user_id=?
            ')->execute([
                $values['full_name'],
                $values['email'],
                $values['role'],
                password_hash($values['password'], PASSWORD_BCRYPT),
                $userId,
            ]);
        } else {
            $pdo->prepare('
                UPDATE users SET full_name=?, email=?, role=?
                WHERE user_id=?
            ')->execute([
                $values['full_name'],
                $values['email'],
                $values['role'],
                $userId,
            ]);
        }

        auditUpdate($_SESSION['user_id'], 'Users', 'users', $userId, $old, [
            'full_name' => $values['full_name'],
            'email'     => $values['email'],
            'role'      => $values['role'],
        ]);

        $_SESSION['success'] = 'Account updated successfully.';
        header('Location: /tailorshop/modules/users/index.php');
        exit;
    }
}

require_once __DIR__ . '/../../../includes/header.php';
require_once __DIR__ . '/../../../includes/sidebar.php';
?>

<div class="main-content">
  <div class="topbar"><span class="topbar-title">Edit Account</span></div>
  <div class="page-body">

    <nav class="breadcrumb-nav mb-3">
      <a href="/tailorshop/modules/users/index.php">
        <i class="bi bi-person-gear me-1"></i>Staff Accounts
      </a>
      <span class="mx-2">/</span><span>Edit</span>
    </nav>

    <div class="row justify-content-center">
      <div class="col-lg-6">
        <div class="card">
          <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="bi bi-pencil me-2 text-primary"></i>Edit Account</span>
            <span class="text-muted small">ID #<?= $userId ?></span>
          </div>
          <div class="card-body p-4">
            <form method="POST" novalidate>

              <div class="mb-3">
                <label class="form-label">Full Name <span class="text-danger">*</span></label>
                <input type="text" name="full_name"
                       class="form-control <?= isset($errors['full_name']) ? 'is-invalid':'' ?>"
                       value="<?= htmlspecialchars($values['full_name']) ?>" required>
                <?php if (isset($errors['full_name'])): ?>
                  <div class="invalid-feedback"><?= $errors['full_name'] ?></div>
                <?php endif; ?>
              </div>

              <div class="mb-3">
                <label class="form-label">Email Address <span class="text-danger">*</span></label>
                <input type="email" name="email"
                       class="form-control <?= isset($errors['email']) ? 'is-invalid':'' ?>"
                       value="<?= htmlspecialchars($values['email']) ?>" required>
                <?php if (isset($errors['email'])): ?>
                  <div class="invalid-feedback"><?= $errors['email'] ?></div>
                <?php endif; ?>
              </div>

              <div class="mb-3">
                <label class="form-label">Role</label>
                <select name="role" class="form-select">
                  <option value="staff"
                    <?= $values['role'] === 'staff' ? 'selected':'' ?>>Staff</option>
                  <option value="admin"
                    <?= $values['role'] === 'admin' ? 'selected':'' ?>>Admin</option>
                </select>
              </div>

              <div class="mb-4">
                <label class="form-label">New Password</label>
                <div class="input-group">
                  <input type="password" name="password" id="password"
                         class="form-control <?= isset($errors['password']) ? 'is-invalid':'' ?>"
                         placeholder="Leave blank to keep current password">
                  <button type="button" class="btn btn-outline-secondary"
                          data-toggle-password="password" tabindex="-1">
                    <i class="bi bi-eye"></i>
                  </button>
                  <?php if (isset($errors['password'])): ?>
                    <div class="invalid-feedback"><?= $errors['password'] ?></div>
                  <?php endif; ?>
                </div>
                <div class="form-text">
                  Leave blank to keep the current password unchanged.
                </div>
              </div>

              <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary px-4">
                  <i class="bi bi-check-lg me-1"></i> Save Changes
                </button>
                <a href="/tailorshop/modules/users/index.php"
                   class="btn btn-outline-secondary px-4">Cancel</a>
              </div>

            </form>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../../../includes/footer.php'; ?>