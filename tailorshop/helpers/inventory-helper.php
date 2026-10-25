<?php
// ============================================================
// helpers/inventory-helper.php
// Shared inventory operations
// ============================================================

require_once __DIR__ . '/audit-helper.php';

function isGarmentAvailable(
    int $garmentId,
    string $startDate,
    string $endDate,
    ?int $excludeReservationId = null
): bool {
    global $pdo;

    $stmt = $pdo->prepare('
        SELECT availability_status
        FROM garment_items
        WHERE garment_id = ?
    ');
    $stmt->execute([$garmentId]);
    $availability = $stmt->fetchColumn();

    if (!in_array($availability, ['Available', 'Reserved'], true)) {
        return false;
    }

    $reservationSql = '
        SELECT COUNT(*)
        FROM reservations
        WHERE garment_id = ?
          AND status IN ("Pending", "Confirmed")
          AND pickup_date <= ?
          AND return_date >= ?
    ';
    $reservationParams = [$garmentId, $endDate, $startDate];

    if ($excludeReservationId !== null) {
        $reservationSql .= ' AND reservation_id != ?';
        $reservationParams[] = $excludeReservationId;
    }

    $stmt = $pdo->prepare($reservationSql);
    $stmt->execute($reservationParams);
    if ((int)$stmt->fetchColumn() > 0) {
        return false;
    }

    $stmt = $pdo->prepare('
        SELECT COUNT(*)
        FROM rentals
        WHERE garment_id = ?
          AND status IN ("Active", "Due Today", "Overdue")
          AND rental_date <= ?
          AND expected_return_date >= ?
    ');
    $stmt->execute([$garmentId, $endDate, $startDate]);

    return (int)$stmt->fetchColumn() === 0;
}

function updateGarmentStatus(
    int $garmentId,
    string $availabilityStatus,
    int $userId,
    ?string $conditionStatus = null
): void {
    global $pdo;

    $validAvailabilityStatuses = [
        'Available',
        'Reserved',
        'Rented Out',
        'Under Repair',
        'Damaged',
        'Sold',
    ];
    $validConditionStatuses = ['Good', 'Needs Cleaning', 'Needs Repair', 'Damaged'];

    if (!in_array($availabilityStatus, $validAvailabilityStatuses, true)) {
        throw new InvalidArgumentException('Invalid garment availability status.');
    }
    if (
        $conditionStatus !== null &&
        !in_array($conditionStatus, $validConditionStatuses, true)
    ) {
        throw new InvalidArgumentException('Invalid garment condition status.');
    }

    $stmt = $pdo->prepare('
        SELECT availability_status, condition_status
        FROM garment_items
        WHERE garment_id = ?
    ');
    $stmt->execute([$garmentId]);
    $garment = $stmt->fetch();

    if (!$garment) {
        throw new RuntimeException('Garment not found.');
    }

    $oldValues = ['availability_status' => $garment['availability_status']];
    $newValues = ['availability_status' => $availabilityStatus];
    $setParts = ['availability_status = ?'];
    $params = [$availabilityStatus];

    if ($conditionStatus !== null) {
        $oldValues['condition_status'] = $garment['condition_status'];
        $newValues['condition_status'] = $conditionStatus;
        $setParts[] = 'condition_status = ?';
        $params[] = $conditionStatus;
    }

    if ($oldValues === $newValues) {
        return;
    }

    $params[] = $garmentId;
    $pdo->prepare(
        'UPDATE garment_items SET ' . implode(', ', $setParts) . ' WHERE garment_id = ?'
    )->execute($params);

    auditUpdate($userId, 'Inventory', 'garment_items', $garmentId, $oldValues, $newValues);
}

function deductMaterial(int $itemId, float $quantity, int $userId): array
{
    global $pdo;

    if ($quantity <= 0) {
        return ['success' => false, 'message' => 'Quantity must be greater than zero.'];
    }

    $stmt = $pdo->prepare('
        SELECT item_name, quantity
        FROM material_items
        WHERE item_id = ?
    ');
    $stmt->execute([$itemId]);
    $item = $stmt->fetch();

    if (!$item) {
        return ['success' => false, 'message' => 'Material item not found.'];
    }
    if ((float)$item['quantity'] < $quantity) {
        return [
            'success' => false,
            'message' => '"' . $item['item_name'] . '" does not have enough stock.',
        ];
    }

    $newQuantity = (float)$item['quantity'] - $quantity;
    $stmt = $pdo->prepare('
        UPDATE material_items
        SET quantity = quantity - ?
        WHERE item_id = ? AND quantity >= ?
    ');
    $stmt->execute([$quantity, $itemId, $quantity]);

    if ($stmt->rowCount() !== 1) {
        return [
            'success' => false,
            'message' => '"' . $item['item_name'] . '" does not have enough stock.',
        ];
    }

    auditUpdate(
        $userId,
        'Inventory',
        'material_items',
        $itemId,
        ['quantity' => (float)$item['quantity']],
        ['quantity' => $newQuantity, 'reason' => 'Material used for tailoring']
    );

    return ['success' => true, 'message' => 'Material stock updated.'];
}
