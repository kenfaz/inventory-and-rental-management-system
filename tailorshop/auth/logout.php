<?php
// ============================================================
// auth/logout.php
// Records logout log entry, destroys session, redirects
// ============================================================

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/db.php';

if (isset($_SESSION['user_id'])) {
    // Record logout in login_logs
    try {
        $pdo->prepare('
            INSERT INTO login_logs (user_id, action, ip_address)
            VALUES (?, "Logout", ?)
        ')->execute([
            $_SESSION['user_id'],
            $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
    } catch (Exception $e) {
        // Non-critical — log and continue
        error_log('Logout log error: ' . $e->getMessage());
    }
}

session_unset();
session_destroy();

header('Location: /tailorshop/auth/login.php');
exit;