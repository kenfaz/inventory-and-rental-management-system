<?php
// ============================================================
// includes/role-guard.php
// Include at the top of any admin-only page
// Requires auth-guard to have run first
// ============================================================

require_once __DIR__ . '/auth-guard.php';

if ($_SESSION['user_role'] !== 'admin') {
    // Redirect staff to their dashboard instead of showing error
    header('Location: /tailorshop/modules/dashboard/employee.php');
    exit;
}