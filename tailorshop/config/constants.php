<?php
// ============================================================
// config/constants.php
// System-wide constants — update these before deployment
// ============================================================

// ── Application ─────────────────────────────────────────────
define('APP_NAME',    "Desire's Tailor Shop");
define('APP_VERSION', '1.0.0');
define('APP_URL',     'http://localhost/tailorshop');

// ── Semaphore SMS API ────────────────────────────────────────
// Get your API key from https://semaphore.co
define('SEMAPHORE_API_KEY',    'YOUR_SEMAPHORE_API_KEY_HERE');
define('SEMAPHORE_SENDER_NAME','YOUR_SENDER_NAME_HERE');
define('SEMAPHORE_API_URL',    'https://api.semaphore.co/api/v4/messages');

// ── SMS Notification Settings ────────────────────────────────
// Admin/owner mobile number — receives low stock and overdue alerts
define('ADMIN_SMS_NUMBER',     '09XXXXXXXXX');

// Days before rental return to send reminder SMS
define('RENTAL_REMINDER_DAYS', 3);

// Days before reservation pickup to send reminder SMS
define('RESERVATION_REMINDER_DAYS', 3);

// Days between overdue escalation SMS messages
define('OVERDUE_ESCALATION_DAYS', 2);

// ── Inventory Settings ───────────────────────────────────────
// Default low stock threshold if not set per item
define('DEFAULT_LOW_STOCK_THRESHOLD', 5);

// ── Financial Settings ───────────────────────────────────────
// Default penalty rate per day for overdue rentals (in PHP peso)
define('DEFAULT_PENALTY_RATE', 200.00);

// ── Pagination ───────────────────────────────────────────────
define('RECORDS_PER_PAGE', 15);

// ── Date and Time ────────────────────────────────────────────
// Always use Philippine Standard Time
define('APP_TIMEZONE', 'Asia/Manila');
date_default_timezone_set(APP_TIMEZONE);

// ── Password Policy ──────────────────────────────────────────
define('PASSWORD_MIN_LENGTH',    8);
define('RESET_TOKEN_EXPIRY_MIN', 30); // minutes

// ── Rental Agreement ─────────────────────────────────────────
define('SHOP_NAME',    "Desire's Tailor Shop");
define('SHOP_ADDRESS', 'Your Shop Address Here');
define('SHOP_CONTACT', '09XXXXXXXXX');

// ── Fix Path ─────────────────────────────────────────────────
define('BASE_PATH', realpath(__DIR__ . '/..'));