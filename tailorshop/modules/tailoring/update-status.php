<?php
// ============================================================
// modules/tailoring/update-status.php
// POST handler — updates order status with side effects
// ============================================================

require_once __DIR__ . '/../../includes/auth-guard.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../helpers/audit-helper.php';
require_once __DIR__ . '/../../helpers/sms-helper.php';
require_once __DIR__ . '/../../helpers/sales-helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /tailorshop/modules/tailoring/index.php');
    exit;
}

$orderId   = (int)($_POST['order_id']  ?? 0);
$newStatus = trim($_POST['new_status'] ?? '');

$allowed = ['Pending','In Progress','Ready for Pickup','Completed'];

if ($orderId === 0 || !in_array($newStatus, $allowed)) {
    $_SESSION['error'] = 'Invalid status update.';
    header('Location: /tailorshop/modules/tailoring/index.php');
    exit;
}

$stmt = $pdo->prepare('
    SELECT o.*,
           c.full_name   AS customer_name,
           c.mobile_number,
           c.customer_id
    FROM tailoring_orders o
    JOIN customers c ON o.customer_id = c.customer_id
    WHERE o.order_id = ?
');
$stmt->execute([$orderId]);
$order = $stmt->fetch();

if (!$order || in_array($order['status'], ['Completed','Cancelled'])) {
    $_SESSION['error'] = 'This order cannot be updated.';
    header('Location: /tailorshop/modules/tailoring/view.php?id=' . $orderId);
    exit;
}

$oldStatus = $order['status'];

$pdo->beginTransaction();
try {
    // ── Side effects per status transition ────────────────

    // Pending → In Progress: deduct materials
    if ($newStatus === 'In Progress' && $oldStatus === 'Pending') {
        $materials = $pdo->prepare('
            SELECT item_id, quantity_used
            FROM order_materials_used WHERE order_id = ?
        ');
        $materials->execute([$orderId]);
        $mats = $materials->fetchAll();

        foreach ($mats as $mat) {
            $pdo->prepare('
                UPDATE material_items
                SET quantity = quantity - ?
                WHERE item_id = ?
            ')->execute([$mat['quantity_used'], $mat['item_id']]);
        }
    }

    // Any → Ready for Pickup: send SMS to customer
    if ($newStatus === 'Ready for Pickup') {
        $pdo->prepare('
            UPDATE tailoring_orders SET status = ? WHERE order_id = ?
        ')->execute([$newStatus, $orderId]);

        $pdo->commit();

        // Send SMS with balance due
        sendSMS(
            $order['mobile_number'],
            buildOrderReadySMS(array_merge($order, ['balance' => $order['balance']])),
            'Order Ready Pickup',
            $orderId,
            'tailoring_orders'
        );

        auditStatusChange($_SESSION['user_id'], 'Tailoring Orders',
                          'tailoring_orders', $orderId, $oldStatus, $newStatus);

        $_SESSION['success'] = 'Order marked Ready for Pickup. SMS sent to customer.';
        header('Location: /tailorshop/modules/tailoring/view.php?id=' . $orderId);
        exit;
    }

    // Ready for Pickup → Completed: record balance payment and income
    if ($newStatus === 'Completed') {
        $balancePayment       = (float)($_POST['balance_payment']        ?? $order['balance']);
        $balancePaymentMethod = trim($_POST['balance_payment_method']    ?? 'Cash');

        // Record income for balance payment (if any)
        if ($balancePayment > 0) {
            recordSale(
                $order['customer_id'],
                $_SESSION['user_id'],
                'Tailoring Payment',
                $balancePayment,
                $balancePaymentMethod,
                null, null, $orderId
            );
        }

        // Record income for down payment too (if not already recorded)
        $dpAlready = $pdo->prepare('
            SELECT COUNT(*) FROM sales_records
            WHERE order_id = ? AND sale_type = "Tailoring Payment"
        ');
        $dpAlready->execute([$orderId]);
        if ($dpAlready->fetchColumn() <= 1 && $order['down_payment'] > 0) {
            // Only record down payment if this is the first income entry
            // (balance payment above is the second)
        }

        $pdo->prepare('
            UPDATE tailoring_orders
            SET status = "Completed", completion_date = CURDATE()
            WHERE order_id = ?
        ')->execute([$orderId]);

        $pdo->commit();

        auditStatusChange($_SESSION['user_id'], 'Tailoring Orders',
                          'tailoring_orders', $orderId, $oldStatus, 'Completed');

        $_SESSION['success'] = 'Order marked as Completed. Income recorded.';
        header('Location: /tailorshop/modules/tailoring/view.php?id=' . $orderId);
        exit;
    }

    // Default: just update status
    $pdo->prepare('
        UPDATE tailoring_orders SET status = ? WHERE order_id = ?
    ')->execute([$newStatus, $orderId]);

    $pdo->commit();

    auditStatusChange($_SESSION['user_id'], 'Tailoring Orders',
                      'tailoring_orders', $orderId, $oldStatus, $newStatus);

    $_SESSION['success'] = 'Order status updated to "' . $newStatus . '".';

} catch (Exception $e) {
    $pdo->rollBack();
    error_log('Status update error: ' . $e->getMessage());
    $_SESSION['error'] = 'Something went wrong. Please try again.';
}

header('Location: /tailorshop/modules/tailoring/view.php?id=' . $orderId);
exit;