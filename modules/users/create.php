<?php
// ============================================================
// modules/users/create.php
// Create new staff account — admin only
// ============================================================

require_once __DIR__ . '/../../../includes/auth-guard.php';
require_once __DIR__ . '/../../../includes/role-guard.php';
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../config/constants.php';
require_once __DIR__ . '/../../../helpers/audit-helper.php';

$pageTitle  = 'Add Staff Account';
$activePage = 'users';

$errors = [];
$values = ['full_name'=>'','email'=>'','role'=>'staff','password'=>''];

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
        $errors['role'] = 'Invalid role selected.';
    if (strlen($values['password']) < PASSWORD_MIN_LENGTH)
        $errors['password'] = 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters.';
    elseif (!preg_match('/[A-Z]/', $values['password']))
        $errors['password'] = 'Password must contain at least one uppercase letter.';
    elseif (!preg_match('/[0-9]/', $values['password']))
        $errors['password'] = 'Password must contain at least one number.';

    if (empty($errors['email'])) {
        $dup = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = ?');
        $dup->execute([$values['email']]);
        if ($dup->fetchColumn() > 0)
            $errors['email'] = 'An account with this email already exists.';
    }

    if (empty($errors)) {
        $pdo->prepare('
            INSERT INTO users (full_name, email, password, role, status)
            VALUES (?, ?, ?, ?, "active")
        ')->execute([
            $values['full_name'],
            $values['email'],
            password_hash($values['password'], PASSWORD_BCRYPT),
            $values['role'],
        ]);

        $newId = (int)$pdo->lastInsertId();
        auditCreate($_SESSION['user_id'], 'Users', 'users', $newId, [
            'full_name' => $values['full_name'],
            'email'     => $values['email'],
            'role'      => $values['role'],
        ]);

        $_SESSION['success'] = 'Account for "' . $values['full_name'] . '" created successfully.';
        header('Location: /tailorshop/modules/users/index.php');
        exit;
    }
}

require_once __DIR__ . '/../../../includes/header.php';
require_once __DIR__ . '/../../../includes/sidebar.php';
?>

<div class="main-content">
  <div class="topbar"><span class="topbar-title">Add Staff Account</span></div>
  <div class="page-body">

    <nav class="breadcrumb-nav mb-3">
      <a href="/tailorshop/modules/users/index.php">
        <i class="bi bi-person-gear me-1"></i>Staff Accounts
      </a>
      <span class="mx-2">/</span><span>Add Account</span>
    </nav>

    <div class="row justify-content-center">
      <div class="col-lg-6">
        <div class="card">
          <div class="card-header">
            <i class="bi bi-person-plus me-2 text-primary"></i>New Staff Account
          </div>
          <div class="card-body p-4">
            <form method="POST" novalidate>

              <div class="mb-3">
                <label class="form-label">Full Name <span class="text-danger">*</span></label>
                <input type="text" name="full_name"
                       class="form-control <?= isset($errors['full_name']) ? 'is-invalid':'' ?>"
                       value="<?= htmlspecialchars($values['full_name']) ?>"
                       placeholder="e.g. Maria Santos" required autofocus>
                <?php if (isset($errors['full_name'])): ?>
                  <div class="invalid-feedback"><?= $errors['full_name'] ?></div>
                <?php endif; ?>
              </div>

              <div class="mb-3">
                <label class="form-label">Email Address <span class="text-danger">*</span></label>
                <input type="email" name="email"
                       class="form-control <?= isset($errors['email']) ? 'is-invalid':'' ?>"
                       value="<?= htmlspecialchars($values['email']) ?>"
                       placeholder="staff@tailorshop.com" required>
                <?php if (isset($errors['email'])): ?>
                  <div class="invalid-feedback"><?= $errors['email'] ?></div>
                <?php endif; ?>
              </div>

              <div class="mb-3">
                <label class="form-label">Role <span class="text-danger">*</span></label>
                <select name="role"
                        class="form-select <?= isset($errors['role']) ? 'is-invalid':'' ?>">
                  <option value="staff"
                    <?= $values['role'] === 'staff' ? 'selected':'' ?>>
                    Staff — Can manage transactions but not reports or settings
                  </option>
                  <option value="admin"
                    <?= $values['role'] === 'admin' ? 'selected':'' ?>>
                    Admin — Full access to all modules
                  </option>
                </select>
                <?php if (isset($errors['role'])): ?>
                  <div class="invalid-feedback"><?= $errors['role'] ?></div>
                <?php endif; ?>
              </div>

              <div class="mb-4">
                <label class="form-label">Password <span class="text-danger">*</span></label>
                <div class="input-group">
                  <input type="password" name="password" id="password"
                         class="form-control <?= isset($errors['password']) ? 'is-invalid':'' ?>"
                         placeholder="Min <?= PASSWORD_MIN_LENGTH ?> chars, 1 uppercase, 1 number"
                         required>
                  <button type="button" class="btn btn-outline-secondary"
                          data-toggle-password="password" tabindex="-1">
                    <i class="bi bi-eye"></i>
                  </button>
                  <?php if (isset($errors['password'])): ?>
                    <div class="invalid-feedback"><?= $errors['password'] ?></div>
                  <?php endif; ?>
                </div>
                <div class="form-text">
                  Minimum <?= PASSWORD_MIN_LENGTH ?> characters, must include an uppercase
                  letter and a number.
                </div>
              </div>

              <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary px-4">
                  <i class="bi bi-person-plus me-1"></i> Create Account
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