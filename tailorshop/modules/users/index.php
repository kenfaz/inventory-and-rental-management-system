<?php
// ============================================================
// modules/users/index.php
// List all user accounts — admin only
// ============================================================

require_once __DIR__ . '/../../includes/auth-guard.php';
require_once __DIR__ . '/../../includes/role-guard.php';
require_once __DIR__ . '/../../config/db.php';

$pageTitle  = 'Staff Accounts';
$activePage = 'users';

$users = $pdo->query("
    SELECT u.*,
           (SELECT COUNT(*) FROM login_logs ll
            WHERE ll.user_id = u.user_id AND ll.action = 'Login') AS login_count
    FROM users u
    ORDER BY u.role ASC, u.full_name ASC
")->fetchAll();

$success = $_SESSION['success'] ?? '';
$error   = $_SESSION['error']   ?? '';
unset($_SESSION['success'], $_SESSION['error']);

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="main-content">
  <div class="topbar">
    <span class="topbar-title">Staff Accounts</span>
  </div>

  <div class="page-body">

    <?php if ($success): ?>
      <div class="alert alert-success alert-dismissible fade show d-flex align-items-center gap-2">
        <i class="bi bi-check-circle-fill"></i><?= htmlspecialchars($success) ?>
        <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
      </div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center gap-2">
        <i class="bi bi-exclamation-circle-fill"></i><?= htmlspecialchars($error) ?>
        <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
      </div>
    <?php endif; ?>

    <div class="page-header">
      <div>
        <h5>Staff Accounts</h5>
        <p class="text-muted small mb-0">
          <?= count($users) ?> account<?= count($users) !== 1 ? 's' : '' ?>
        </p>
      </div>
      <a href="/tailorshop/modules/users/create.php" class="btn btn-primary">
        <i class="bi bi-person-plus me-1"></i> Add Staff Account
      </a>
    </div>

    <div class="card">
      <div class="table-responsive">
        <table class="table mb-0">
          <thead>
            <tr>
              <th>Name</th>
              <th>Email</th>
              <th>Role</th>
              <th>Status</th>
              <th>Last Login</th>
              <th>Total Logins</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($users as $u):
              $isCurrentUser = $u['user_id'] === $_SESSION['user_id'];
              $initials = '';
              foreach (explode(' ', $u['full_name']) as $w)
                  if ($w) $initials .= strtoupper($w[0]);
              $initials = substr($initials, 0, 2);
            ?>
              <tr>
                <td>
                  <div class="d-flex align-items-center gap-2">
                    <div style="width:34px;height:34px;border-radius:50%;
                                background:<?= $u['role'] === 'admin' ? '#EDE9FE' : '#EFF6FF' ?>;
                                color:<?= $u['role'] === 'admin' ? '#7C3AED' : '#1D4ED8' ?>;
                                display:flex;align-items:center;justify-content:center;
                                font-size:12px;font-weight:600;flex-shrink:0">
                      <?= htmlspecialchars($initials) ?>
                    </div>
                    <div>
                      <div class="fw-medium"><?= htmlspecialchars($u['full_name']) ?></div>
                      <?php if ($isCurrentUser): ?>
                        <div class="text-muted" style="font-size:11px">You</div>
                      <?php endif; ?>
                    </div>
                  </div>
                </td>
                <td class="text-muted"><?= htmlspecialchars($u['email']) ?></td>
                <td>
                  <span class="badge <?= $u['role'] === 'admin'
                      ? 'bg-purple bg-opacity-10 text-purple'
                      : 'bg-primary bg-opacity-10 text-primary' ?> rounded-pill"
                        style="font-size:11.5px;background:<?= $u['role'] === 'admin'
                            ? '#EDE9FE' : '#EFF6FF' ?>;color:<?= $u['role'] === 'admin'
                            ? '#6D28D9' : '#1D4ED8' ?>">
                    <?= ucfirst($u['role']) ?>
                  </span>
                </td>
                <td>
                  <?php if ($u['status'] === 'active'): ?>
                    <span class="badge-status badge-active">
                      <i class="bi bi-circle-fill" style="font-size:8px"></i> Active
                    </span>
                  <?php else: ?>
                    <span class="badge-status badge-cancelled">
                      <i class="bi bi-circle" style="font-size:8px"></i> Inactive
                    </span>
                  <?php endif; ?>
                </td>
                <td class="text-muted" style="font-size:12.5px">
                  <?= $u['last_login']
                      ? date('M j, Y g:i A', strtotime($u['last_login']))
                      : 'Never' ?>
                </td>
                <td class="text-muted"><?= $u['login_count'] ?></td>
                <td>
                  <div class="d-flex gap-1">
                    <a href="/tailorshop/modules/users/edit.php?id=<?= $u['user_id'] ?>"
                       class="btn btn-sm btn-outline-secondary">
                      <i class="bi bi-pencil"></i>
                    </a>
                    <?php if (!$isCurrentUser): ?>
                      <a href="/tailorshop/modules/users/deactivate.php?id=<?= $u['user_id'] ?>"
                         class="btn btn-sm <?= $u['status'] === 'active'
                             ? 'btn-outline-danger' : 'btn-outline-success' ?>"
                         data-confirm="<?= $u['status'] === 'active'
                             ? 'Deactivate this account?' : 'Reactivate this account?' ?>">
                        <i class="bi <?= $u['status'] === 'active'
                            ? 'bi-person-x' : 'bi-person-check' ?>"></i>
                      </a>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

  </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>