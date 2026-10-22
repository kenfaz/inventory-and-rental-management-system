<?php
// ============================================================
// config/constants.php
// System-wide constants — update these before deployment
// ============================================================

function loadLocalEnvironment(string $file): void
{
    if (!is_file($file)) {
        return;
    }

    $lines = file($file, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        throw new RuntimeException('Unable to read the project .env file.');
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        if (!preg_match('/^([A-Za-z_][A-Za-z0-9_]*)\s*=\s*(.*)$/', $line, $matches)) {
            throw new RuntimeException('Invalid setting in the project .env file.');
        }

        $name = $matches[1];
        $value = trim($matches[2]);
        if (
            strlen($value) >= 2 &&
            (($value[0] === '"' && substr($value, -1) === '"') ||
             ($value[0] === "'" && substr($value, -1) === "'"))
        ) {
            $value = substr($value, 1, -1);
        }

        if (getenv($name) === false) {
            putenv($name . '=' . $value);
            $_ENV[$name] = $value;
        }
    }
}

loadLocalEnvironment(dirname(__DIR__, 2) . '/.env');

// ── Application ─────────────────────────────────────────────
define('APP_NAME',    "Desire's Tailor Shop");
define('APP_VERSION', '1.0.0');
define('APP_URL',     'http://localhost/tailorshop');

// ── UniSMS API ───────────────────────────────────────────────
// Configure these values from your UniSMS account.
define('UNISMS_SECRET_KEY', getenv('UNISMS_SECRET_KEY') ?: 'YOUR_UNISMS_SECRET_KEY_HERE');
define('UNISMS_SENDER_ID', getenv('UNISMS_SENDER_ID') ?: 'YOUR_UNISMS_SENDER_ID_HERE');
define('UNISMS_API_URL', getenv('UNISMS_API_URL') ?: 'https://unismsapi.com/api/sms');

// ── Local email delivery (Mailpit) ───────────────────────────
define('MAIL_SMTP_HOST', getenv('MAIL_SMTP_HOST') ?: '127.0.0.1');
define('MAIL_SMTP_PORT', (int)(getenv('MAIL_SMTP_PORT') ?: 1025));
define('MAIL_FROM_ADDRESS', getenv('MAIL_FROM_ADDRESS') ?: 'alerts@tailorshop.local');
define('MAIL_FROM_NAME', getenv('MAIL_FROM_NAME') ?: APP_NAME);

// ── SMS Notification Settings ────────────────────────────────
// Admin/owner mobile number — receives low stock and overdue alerts
define('ADMIN_SMS_NUMBER',     '09397271618');

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
define('LOGIN_MAX_ATTEMPTS',     5);
define('LOGIN_ATTEMPT_WINDOW_MINUTES', 15);
define('LOGIN_COOLDOWN_MINUTES', 5);

// ── Rental Agreement ─────────────────────────────────────────
define('SHOP_NAME',    "Desire's Tailor Shop");
define('SHOP_ADDRESS', 'Chipeco Avenue, Calamba City, Laguna');
define('SHOP_CONTACT', '09397271618');

// ── Fix Path ─────────────────────────────────────────────────
define('BASE_PATH', realpath(__DIR__ . '/..'));