<?php
// ============================================================
// modules/inventory/garments/delete.php
// Delete garment item — admin only
// Blocks if garment has active rentals or reservations
// ============================================================

require_once __DIR__ . '/../../../../includes/auth-guard.php';
require_once __DIR__ . '/../../../../includes/role-guard.php';
require_once __DIR__ . '/../../../../config/db.php';
require_once __DIR__ . '/../../../../helpers/audit-helper.php';

$garmentId = (int)($_GET['id'] ?? 0);
if ($garmentId === 0) {
    header('Location: /tailorshop/modules/inventory/garments/index.php');
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM garment_items WHERE garment_id = ?');
$stmt->execute([$garmentId]);
$garment = $stmt->fetch();

if (!$garment) {
    $_SESSION['error'] = 'Garment not found.';
    header('Location: /tailorshop/modules/inventory/garments/index.php');
    exit;
}

// Block if active rentals exist
$activeRentals = $pdo->prepare('
    SELECT COUNT(*) FROM rentals
    WHERE garment_id = ? AND status IN ("Active","Due Today","Overdue")
');
$activeRentals->execute([$garmentId]);
if ($activeRentals->fetchColumn() > 0) {
    $_SESSION['error'] = '"' . $garment['garment_name'] .
        '" cannot be deleted — it has active rentals.';
    header('Location: /tailorshop/modules/inventory/garments/index.php');
    exit;
}

// Block if confirmed reservations exist
$activeReservations = $pdo->prepare('
    SELECT COUNT(*) FROM reservations
    WHERE garment_id = ? AND status IN ("Pending","Confirmed")
');
$activeReservations->execute([$garmentId]);
if ($activeReservations->fetchColumn() > 0) {
    $_SESSION['error'] = '"' . $garment['garment_name'] .
        '" cannot be deleted — it has active reservations.';
    header('Location: /tailorshop/modules/inventory/garments/index.php');
    exit;
}

// Safe to delete
auditDelete(
    $_SESSION['user_id'], 'Inventory',
    'garment_items', $garmentId, [
        'garment_name'        => $garment['garment_name'],
        'garment_code'        => $garment['garment_code'],
        'availability_status' => $garment['availability_status'],
    ]
);

$pdo->prepare('DELETE FROM garment_items WHERE garment_id = ?')
    ->execute([$garmentId]);

$_SESSION['success'] = '"' . $garment['garment_name'] . '" has been deleted.';
header('Location: /tailorshop/modules/inventory/garments/index.php');
exit;