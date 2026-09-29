<?php
// ============================================================
// config/db.php
// PDO database connection — included by every module
// ============================================================

define('DB_HOST',    'localhost');
define('DB_NAME',    'tailorshop');
define('DB_USER',    'root');
define('DB_PASS',    '');
define('DB_CHARSET', 'utf8mb4');

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET,
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    // Never expose raw error in production
    error_log('DB Connection Error: ' . $e->getMessage());
    die(json_encode([
        'error' => 'Database connection failed. Please contact the administrator.'
    ]));
}