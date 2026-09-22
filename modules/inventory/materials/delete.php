<?php
// ============================================================
// modules/inventory/materials/delete.php
// Delete material item — admin only
// Blocks deletion if item is used in any tailoring order
// ============================================================

require_once __DIR__ . '/../../../../includes/auth-guard.php';
require_once __DIR__ . '/../../../../includes/role-guard.php';
require_once __DIR__ . '/../../../../config/db.php';
require_once __DIR__ . '/../../../../helpers/audit-helper.php';

$itemId = (int)($_GET['id'] ?? 0);

if ($itemId === 0) {
    header('Location: /tailorshop/modules/inventory/materials/index.php');
    exit;
}

// Fetch item
$stmt = $pdo->prepare('SELECT * FROM material_items WHERE item_id = ?');
$stmt->execute([$itemId]);
$item = $stmt->fetch();

if (!$item) {
    $_SESSION['error'] = 'Item not found.';
    header('Location: /tailorshop/modules/inventory/materials/index.php');
    exit;
}

// Block deletion if item is referenced in order_materials_used
$inUse = $pdo->prepare('
    SELECT COUNT(*) FROM order_materials_used WHERE item_id = ?
');
$inUse->execute([$itemId]);
if ($inUse->fetchColumn() > 0) {
    $_SESSION['error'] = '"' . $item['item_name'] .
        '" cannot be deleted because it is used in one or more tailoring orders.';
    header('Location: /tailorshop/modules/inventory/materials/index.php');
    exit;
}

// Safe to delete
auditDelete(
    $_SESSION['user_id'], 'Inventory',
    'material_items', $itemId, [
        'item_name' => $item['item_name'],
        'quantity'  => $item['quantity'],
        'unit'      => $item['unit'],
    ]
);

$pdo->prepare('DELETE FROM material_items WHERE item_id = ?')
    ->execute([$itemId]);

$_SESSION['success'] = '"' . $item['item_name'] . '" has been deleted.';
header('Location: /tailorshop/modules/inventory/materials/index.php');
exit;