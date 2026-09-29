<?php
// ============================================================
// index.php — Entry point
// Checks session and routes to correct dashboard by role
// ============================================================
// define('BASE_PATH', __DIR__);
// require_once BASE_PATH . 'tailorshop/config/session.php';
// require_once BASE_PATH . 'tailorshop/config/db.php';

require_once __DIR__ . '/tailorshop/config/session.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /tailorshop/auth/login.php');
    exit;
}

if ($_SESSION['user_role'] === 'admin') {
    header('Location: /tailorshop/modules/dashboard/admin.php');
} else {
    header('Location: /tailorshop/modules/dashboard/employee.php');
}
exit;