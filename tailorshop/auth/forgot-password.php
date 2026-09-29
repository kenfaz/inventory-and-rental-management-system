<?php
// ============================================================
// auth/reset-password.php
// Validates reset token and updates password
// ============================================================

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/constants.php';

if (isset($_SESSION['user_id'])) {
    header('Location: /tailorshop/index.php');
    exit;
}

$token  = trim($_GET['token'] ?? '');
$error  = '';
$valid  = false;
$record = null;

if (empty($token)) {
    $error = 'Invalid or missing reset token.';
} else {
    $stmt = $pdo->prepare('
        SELECT * FROM password_resets
        WHERE token = ? AND used = 0 AND expires_at > NOW()
        LIMIT 1
    ');
    $stmt->execute([$token]);
    $record = $stmt->fetch();

    if ($record) {
        $valid = true;
    } else {
        $error = 'This reset link is invalid or has expired. Please request a new one.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $valid) {
    $password        = trim($_POST['password']         ?? '');
    $passwordConfirm = trim($_POST['password_confirm'] ?? '');

    if (strlen($password) < PASSWORD_MIN_LENGTH) {
        $error = 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters.';
    } elseif (!preg_match('/[A-Z]/', $password)) {
        $error = 'Password must contain at least one uppercase letter.';
    } elseif (!preg_match('/[0-9]/', $password)) {
        $error = 'Password must contain at least one number.';
    } elseif ($password !== $passwordConfirm) {
        $error = 'Passwords do not match.';
    } else {
        $hashed = password_hash($password, PASSWORD_BCRYPT);

        // Update user password
        $pdo->prepare('UPDATE users SET password = ? WHERE email = ?')
            ->execute([$hashed, $record['email']]);

        // Mark token as used
        $pdo->prepare('UPDATE password_resets SET used = 1 WHERE token = ?')
            ->execute([$token]);

        header('Location: /tailorshop/auth/login.php?reason=reset');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Reset Password — <?= APP_NAME ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="/tailorshop/assets/css/main.css" rel="stylesheet">
  <style>
    body {
      min-height:100vh; display:flex; align-items:center; justify-content:center;
      background: linear-gradient(135deg,#1E1030 0%,#3B1F6E 100%);
    }
    .card { width:100%; max-width:420px; border:none; border-radius:16px; box-shadow:0 20px 60px rgba(0,0,0,.4); }
    .form-control:focus { border-color:#7C3AED; box-shadow:0 0 0 3px rgba(124,58,237,.15); }
    #strength-bar { height:5px; border-radius:4px; transition:width .3s,background .3s; }
  </style>
</head>
<body>
<div class="card p-4">
  <div class="card-body">

    <div class="text-center mb-4">
      <div style="width:52px;height:52px;background:#7C3AED;border-radius:12px;display:flex;align-items:center;justify-content:center;margin:0 auto 12px;font-size:24px;color:#fff">
        <i class="bi bi-shield-lock"></i>
      </div>
      <h5 class="fw-bold mb-1">Reset Password</h5>
      <p class="text-muted small mb-0">Enter your new password below.</p>
    </div>

    <?php if ($error): ?>
      <div class="alert alert-danger small"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($valid): ?>
      <form method="POST" novalidate>

        <div class="mb-3">
          <label class="form-label">New Password</label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-lock"></i></span>
            <input type="password" id="password" name="password"
                   class="form-control" placeholder="Minimum 8 characters" required>
            <button type="button" class="btn btn-outline-secondary"
                    data-toggle-password="password" tabindex="-1">
              <i class="bi bi-eye"></i>
            </button>
          </div>
          <div class="mt-2" id="strength-wrapper" style="display:none">
            <div class="progress" style="height:5px;border-radius:4px">
              <div id="strength-bar" class="progress-bar" style="width:0%"></div>
            </div>
            <div class="d-flex justify-content-between mt-1">
              <small class="text-muted">Strength</small>
              <small id="strength-label" class="fw-medium"></small>
            </div>
          </div>
          <div class="form-text">Min 8 characters, one uppercase letter, one number.</div>
        </div>

        <div class="mb-4">
          <label class="form-label">Confirm New Password</label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
            <input type="password" id="password_confirm" name="password_confirm"
                   class="form-control" placeholder="Re-enter password" required>
            <button type="button" class="btn btn-outline-secondary"
                    data-toggle-password="password_confirm" tabindex="-1">
              <i class="bi bi-eye"></i>
            </button>
          </div>
        </div>

        <div class="d-grid mb-3">
          <button type="submit" class="btn btn-primary">
            <i class="bi bi-check-lg me-1"></i> Reset Password
          </button>
        </div>

      </form>
    <?php else: ?>
      <div class="text-center mt-2">
        <a href="/tailorshop/auth/forgot-password.php" class="btn btn-outline-primary btn-sm">
          Request a New Link
        </a>
      </div>
    <?php endif; ?>

    <div class="text-center mt-3">
      <a href="/tailorshop/auth/login.php" class="small text-decoration-none" style="color:#7C3AED">
        <i class="bi bi-arrow-left me-1"></i> Back to Login
      </a>
    </div>

  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="/tailorshop/assets/js/main.js"></script>
<script>
const pwInput     = document.getElementById('password');
const confirmInput = document.getElementById('password_confirm');
const strengthBar = document.getElementById('strength-bar');
const strengthLabel = document.getElementById('strength-label');
const strengthWrap  = document.getElementById('strength-wrapper');

if (pwInput) {
  pwInput.addEventListener('input', () => {
    const val = pwInput.value;
    strengthWrap.style.display = val.length ? 'block' : 'none';
    let score = 0;
    if (val.length >= 8)            score++;
    if (/[A-Z]/.test(val))         score++;
    if (/[0-9]/.test(val))         score++;
    if (/[^A-Za-z0-9]/.test(val))  score++;
    const levels = [
      { pct:'25%', color:'#EF4444', label:'Weak'   },
      { pct:'50%', color:'#F59E0B', label:'Fair'   },
      { pct:'75%', color:'#3B82F6', label:'Good'   },
      { pct:'100%',color:'#10B981', label:'Strong' },
    ];
    const l = levels[score - 1] || levels[0];
    strengthBar.style.width           = l.pct;
    strengthBar.style.backgroundColor = l.color;
    strengthLabel.textContent         = l.label;
    strengthLabel.style.color         = l.color;
  });
}

if (confirmInput) {
  confirmInput.addEventListener('input', () => {
    const match = confirmInput.value === pwInput.value;
    confirmInput.classList.toggle('is-invalid', confirmInput.value && !match);
    confirmInput.classList.toggle('is-valid',   confirmInput.value && match);
  });
}
</script>
</body>
</html>