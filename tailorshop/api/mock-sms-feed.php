<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Admin access is required.']);
    exit;
}

$afterId = filter_input(INPUT_GET, 'after_id', FILTER_VALIDATE_INT);
if ($afterId === false || $afterId === null || $afterId < 0) {
    http_response_code(400);
    echo json_encode(['error' => 'A valid after_id is required.']);
    exit;
}

try {
    if ($afterId === 0) {
        $stmt = $pdo->prepare('
            SELECT sms_log_id, recipient_number, message_content, trigger_type, timestamp
            FROM sms_logs
            WHERE status = "Mocked"
            ORDER BY sms_log_id DESC
            LIMIT 8
        ');
    } else {
        $stmt = $pdo->prepare('
            SELECT sms_log_id, recipient_number, message_content, trigger_type, timestamp
            FROM sms_logs
            WHERE status = "Mocked" AND sms_log_id > ?
            ORDER BY sms_log_id ASC
            LIMIT 30
        ');
        $stmt->execute([$afterId]);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['messages' => $messages], JSON_THROW_ON_ERROR);
        exit;
    }

    $stmt->execute();
    $messages = array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));
    echo json_encode(['messages' => $messages], JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    error_log('Mock SMS feed error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Could not load the mock SMS feed.']);
}
