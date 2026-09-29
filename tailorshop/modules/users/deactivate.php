<?php
// ============================================================
// modules/users/deactivate.php
// Toggle user account status between active and inactive
// Cannot deactivate your own account
// ============================================================

require_once __DIR__ . '/../../includes/auth-guard.php';
require_once __DIR__ . '/../../includes/role-guard.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../helpers/audit-helper.php';

$userId = (int)($_GET['id'] ?? 0);
if ($userId === 0) {
    header('Location: /tailorshop/modules/users/index.php');
    exit;
}

if ($userId === $_SESSION['user_id']) {
    $_SESSION['error'] = 'You cannot deactivate your own account.';
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

$newStatus = $user['status'] === 'active' ? 'inactive' : 'active';

$pdo->prepare('UPDATE users SET status = ? WHERE user_id = ?')
    ->execute([$newStatus, $userId]);

auditUpdate(
    $_SESSION['user_id'], 'Users', 'users', $userId,
    ['status' => $user['status']],
    ['status' => $newStatus]
);

$_SESSION['success'] = '"' . $user['full_name'] . '" has been ' .
    ($newStatus === 'active' ? 'reactivated' : 'deactivated') . '.';

header('Location: /tailorshop/modules/users/index.php');
exit;