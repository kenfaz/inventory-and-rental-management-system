<?php
// ============================================================
// config/session.php
// Starts session and enforces inactivity timeout
// Included at the top of every protected page
// ============================================================

// Session timeout — 15 minutes of inactivity
define('SESSION_TIMEOUT', 900);

if (session_status() === PHP_SESSION_NONE) {
    // Secure session cookie settings
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => false,   // Set to true if using HTTPS
        'httponly' => true,    // Prevent JavaScript access to session cookie
        'samesite' => 'Strict',
    ]);
    session_start();
}

// Enforce inactivity timeout
if (isset($_SESSION['last_activity'])) {
    if (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT) {
        // Session expired — clean up and redirect
        session_unset();
        session_destroy();
        header('Location: /tailorshop/auth/login.php?reason=timeout');
        exit;
    }
}

// Refresh activity timestamp on every valid request
$_SESSION['last_activity'] = time();