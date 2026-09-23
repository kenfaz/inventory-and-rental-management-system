<?php
// ============================================================
// api/check-conflict.php
// Returns JSON whether a garment is available for a date range
// Called by conflict-checker.js on reservation create page
// ============================================================

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/inventory-helper.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$garmentId  = (int)($_GET['garment_id']  ?? 0);
$pickupDate = trim($_GET['pickup_date']  ?? '');
$returnDate = trim($_GET['return_date']  ?? '');
$excludeId  = (int)($_GET['exclude_id'] ?? 0) ?: null;

if (!$garmentId || !$pickupDate || !$returnDate) {
    echo json_encode(['conflict' => false]);
    exit;
}

$available = isGarmentAvailable($garmentId, $pickupDate, $returnDate, $excludeId);

if ($available) {
    echo json_encode(['conflict' => false]);
} else {
    // Get conflicting details for a helpful message
    $stmt = $pdo->prepare('
        SELECT "Reservation" AS type, pickup_date AS start_date, return_date AS end_date,
               c.full_name AS customer_name
        FROM reservations res
        JOIN customers c ON res.customer_id = c.customer_id
        WHERE res.garment_id = ?
          AND res.status IN ("Pending","Confirmed")
          AND res.pickup_date <= ? AND res.return_date >= ?
        UNION
        SELECT "Rental" AS type, rental_date AS start_date,
               expected_return_date AS end_date,
               c.full_name AS customer_name
        FROM rentals r
        JOIN customers c ON r.customer_id = c.customer_id
        WHERE r.garment_id = ?
          AND r.status IN ("Active","Due Today","Overdue")
          AND r.rental_date <= ? AND r.expected_return_date >= ?
        LIMIT 1
    ');
    $stmt->execute([
        $garmentId, $returnDate, $pickupDate,
        $garmentId, $returnDate, $pickupDate,
    ]);
    $conflict = $stmt->fetch();

    $msg = 'This garment is not available for the selected dates.';
    if ($conflict) {
        $msg = 'Conflict: ' . $conflict['type'] . ' for ' .
               $conflict['customer_name'] . ' from ' .
               date('M j', strtotime($conflict['start_date'])) . ' to ' .
               date('M j, Y', strtotime($conflict['end_date'])) . '.';
    }

    echo json_encode(['conflict' => true, 'message' => $msg]);
}