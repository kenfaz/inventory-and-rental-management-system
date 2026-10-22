<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/sms-helper.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Admin access is required.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['error' => 'Use POST to create a demo SMS.']);
    exit;
}

$csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (
    empty($_SESSION['sms_demo_csrf']) ||
    !hash_equals($_SESSION['sms_demo_csrf'], $csrfToken)
) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid request token. Refresh the page and try again.']);
    exit;
}

if (!SMS_MOCK_MODE) {
    http_response_code(409);
    echo json_encode(['error' => 'The demo is disabled while live SMS mode is enabled.']);
    exit;
}

$sent = sendSMS(
    '09170000000',
    'This is a demo SMS from Desire\'s Tailor Shop. No message was sent to a real phone.',
    'Reservation Confirmation'
);

if (!$sent) {
    http_response_code(500);
    echo json_encode(['error' => 'Could not record the demo SMS. Check the SMS logs.']);
    exit;
}

echo json_encode(['success' => true]);
