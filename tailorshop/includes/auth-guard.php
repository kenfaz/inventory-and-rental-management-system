<?php
// ============================================================
// includes/auth-guard.php
// Include at the top of every protected page
// Redirects to login if no valid session exists
// ============================================================

require_once __DIR__ . '/../config/session.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /tailorshop/auth/login.php');
    exit;
}