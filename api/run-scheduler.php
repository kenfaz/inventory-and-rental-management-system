<?php
// ============================================================
// api/run-scheduler.php
// Manually triggers the SMS scheduler
// Can be called from the admin dashboard Quick Actions
// ============================================================

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../helpers/sms-scheduler.php';
require_once __DIR__ . '/../helpers/rental-helper.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: /tailorshop/auth/login.php');
    exit;
}

$log = runSMSScheduler();

$_SESSION['success'] = 'SMS scheduler ran successfully. ' . count($log) . ' checks completed.';
header('Location: /tailorshop/modules/dashboard/admin.php');
exit;