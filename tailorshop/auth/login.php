<?php
// ============================================================
// auth/login.php
// Handles GET display and POST authentication
// ============================================================

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/constants.php';

// Already logged in — redirect to dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: /tailorshop/index.php');
    exit;
}

$error   = '';
$email   = '';
$timeout = isset($_GET['reason']) && $_GET['reason'] === 'timeout';
$reset   = isset($_GET['reason']) && $_GET['reason'] === 'reset';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($email) || empty($password)) {
        $error = 'Please enter your email address and password.';
    } else {
        $stmt = $pdo->prepare('
            SELECT * FROM users
            WHERE email = ? AND status = "active"
            LIMIT 1
        ');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            // Regenerate session ID to prevent session fixation
            session_regenerate_id(true);

            $_SESSION['user_id']       = $user['user_id'];
            $_SESSION['user_name']     = $user['full_name'];
            $_SESSION['user_role']     = $user['role'];
            $_SESSION['user_email']    = $user['email'];
            $_SESSION['last_activity'] = time();

            // Update last login timestamp
            $pdo->prepare('UPDATE users SET last_login = NOW() WHERE user_id = ?')
                ->execute([$user['user_id']]);

            // Record login log
            $pdo->prepare('
                INSERT INTO login_logs (user_id, action, ip_address)
                VALUES (?, "Login", ?)
            ')->execute([
                $user['user_id'],
                $_SERVER['REMOTE_ADDR'] ?? null,
            ]);

            // Route by role
            if ($user['role'] === 'admin') {
                header('Location: /tailorshop/modules/dashboard/admin.php');
            } else {
                header('Location: /tailorshop/modules/dashboard/employee.php');
            }
            exit;

        } else {
            $error = 'Invalid email or password. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Log In — <?= APP_NAME ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="/tailorshop/assets/css/main.css" rel="stylesheet">
  <style>
    body {
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      background: linear-gradient(135deg, #1E1030 0%, #3B1F6E 100%);
    }
    .login-card {
      width: 100%; max-width: 420px;
      border: none; border-radius: 16px;
      box-shadow: 0 20px 60px rgba(0,0,0,0.4);
    }
    .login-logo {
      width: 60px; height: 60px;
      background: #7C3AED; border-radius: 16px;
      display: flex; align-items: center; justify-content: center;
      margin: 0 auto 1rem; font-size: 28px; color: #fff;
    }
    .btn-login {
      background: #7C3AED; border: none; color: #fff;
      font-weight: 500; padding: 10px;
    }
    .btn-login:hover { background: #6D28D9; color: #fff; }
    .form-control:focus {
      border-color: #7C3AED;
      box-shadow: 0 0 0 3px rgba(124,58,237,.15);
    }
  </style>
</head>
<body>

<div class="card login-card p-4">
  <div class="card-body">

    <!-- Logo and title -->
    <div class="text-center mb-4">
      <div class="login-logo"><i class="bi bi-scissors"></i></div>
      <h4 class="fw-bold mb-1"><?= APP_NAME ?></h4>
      <p class="text-muted small mb-0">Inventory and Sales Management System</p>
    </div>

    <!-- Notices -->
    <?php if ($timeout): ?>
      <div class="alert alert-warning d-flex align-items-center gap-2 py-2 small">
        <i class="bi bi-clock-history"></i>
        Your session expired due to inactivity. Please log in again.
      </div>
    <?php endif; ?>

    <?php if ($reset): ?>
      <div class="alert alert-success d-flex align-items-center gap-2 py-2 small">
        <i class="bi bi-check-circle"></i>
        Your password has been reset. You can now log in.
      </div>
    <?php endif; ?>

    <?php if ($error): ?>
      <div class="alert alert-danger d-flex align-items-center gap-2 py-2 small">
        <i class="bi bi-exclamation-circle"></i>
        <?= htmlspecialchars($error) ?>
      </div>
    <?php endif; ?>

    <!-- Login form -->
    <form method="POST" action="" novalidate>

      <div class="mb-3">
        <label for="email" class="form-label">Email address</label>
        <div class="input-group">
          <span class="input-group-text"><i class="bi bi-envelope"></i></span>
          <input
            type="email" id="email" name="email"
            class="form-control"
            placeholder="you@tailorshop.com"
            value="<?= htmlspecialchars($email) ?>"
            required autofocus
          >
        </div>
      </div>

      <div class="mb-4">
        <div class="d-flex justify-content-between align-items-center mb-1">
          <label for="password" class="form-label mb-0">Password</label>
          <a href="/tailorshop/auth/forgot-password.php"
             class="small text-decoration-none" style="color:#7C3AED">
            Forgot password?
          </a>
        </div>
        <div class="input-group">
          <span class="input-group-text"><i class="bi bi-lock"></i></span>
          <input
            type="password" id="password" name="password"
            class="form-control"
            placeholder="Enter your password"
            required
          >
          <button type="button" class="btn btn-outline-secondary"
                  data-toggle-password="password" tabindex="-1">
            <i class="bi bi-eye"></i>
          </button>
        </div>
      </div>

      <div class="d-grid">
        <button type="submit" class="btn btn-login">
          <i class="bi bi-box-arrow-in-right me-1"></i> Log In
        </button>
      </div>

    </form>

    <p class="text-center text-muted small mt-4 mb-0">
      Contact your administrator if you cannot log in.
    </p>

  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="/tailorshop/assets/js/main.js"></script>
</body>
</html>