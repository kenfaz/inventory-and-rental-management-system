<?php
// ============================================================
// modules/tailoring/cancel.php
// Cancel a tailoring order — records reason, preserves record
// ============================================================

require_once __DIR__ . '/../../../includes/auth-guard.php';
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../helpers/audit-helper.php';

$pageTitle  = 'Cancel Order';
$activePage = 'tailoring';

$orderId = (int)($_GET['id'] ?? 0);
if ($orderId === 0) {
    header('Location: /tailorshop/modules/tailoring/index.php');
    exit;
}

$stmt = $pdo->prepare('
    SELECT o.*, c.full_name AS customer_name
    FROM tailoring_orders o
    JOIN customers c ON o.customer_id = c.customer_id
    WHERE o.order_id = ?
');
$stmt->execute([$orderId]);
$order = $stmt->fetch();

if (!$order || in_array($order['status'], ['Completed','Cancelled'])) {
    $_SESSION['error'] = 'This order cannot be cancelled.';
    header('Location: /tailorshop/modules/tailoring/view.php?id=' . $orderId);
    exit;
}

$errors = [];
$reason = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reason = trim($_POST['cancellation_reason'] ?? '');

    if ($reason === '')
        $errors['reason'] = 'Please provide a reason for cancellation.';

    if (empty($errors)) {
        $pdo->prepare('
            UPDATE tailoring_orders
            SET status = "Cancelled", cancellation_reason = ?
            WHERE order_id = ?
        ')->execute([$reason, $orderId]);

        auditStatusChange(
            $_SESSION['user_id'], 'Tailoring Orders',
            'tailoring_orders', $orderId,
            $order['status'], 'Cancelled'
        );

        $_SESSION['success'] = 'Order #' .
            str_pad($orderId, 4, '0', STR_PAD_LEFT) . ' has been cancelled.';
        header('Location: /tailorshop/modules/tailoring/view.php?id=' . $orderId);
        exit;
    }
}

require_once __DIR__ . '/../../../includes/header.php';
require_once __DIR__ . '/../../../includes/sidebar.php';
?>

<div class="main-content">
  <div class="topbar"><span class="topbar-title">Cancel Order</span></div>

  <div class="page-body">

    <nav class="breadcrumb-nav mb-3">
      <a href="/tailorshop/modules/tailoring/index.php">
        <i class="bi bi-scissors me-1"></i>Tailoring Orders
      </a>
      <span class="mx-2">/</span>
      <a href="/tailorshop/modules/tailoring/view.php?id=<?= $orderId ?>">
        #<?= str_pad($orderId, 4, '0', STR_PAD_LEFT) ?>
      </a>
      <span class="mx-2">/</span><span>Cancel</span>
    </nav>

    <div class="row justify-content-center">
      <div class="col-lg-6">

        <div class="alert alert-warning d-flex gap-2 align-items-start mb-3">
          <i class="bi bi-exclamation-triangle-fill flex-shrink-0 mt-1"></i>
          <div style="font-size:13.5px">
            You are about to cancel the order
            <strong><?= htmlspecialchars($order['order_name']) ?></strong>
            for <strong><?= htmlspecialchars($order['customer_name']) ?></strong>.
            This action cannot be undone, but the record will be preserved.
          </div>
        </div>

        <div class="card">
          <div class="card-header">
            <i class="bi bi-x-circle me-2 text-danger"></i>Cancel Order
          </div>
          <div class="card-body p-4">
            <form method="POST" novalidate>

              <div class="mb-4">
                <label class="form-label">
                  Cancellation Reason <span class="text-danger">*</span>
                </label>
                <textarea name="cancellation_reason" rows="3"
                          class="form-control <?= isset($errors['reason']) ? 'is-invalid' : '' ?>"
                          placeholder="Enter the reason for cancelling this order…"
                          required><?= htmlspecialchars($reason) ?></textarea>
                <?php if (isset($errors['reason'])): ?>
                  <div class="invalid-feedback"><?= $errors['reason'] ?></div>
                <?php endif; ?>
              </div>

              <div class="d-flex gap-2">
                <button type="submit" class="btn btn-danger px-4">
                  <i class="bi bi-x-circle me-1"></i> Confirm Cancellation
                </button>
                <a href="/tailorshop/modules/tailoring/view.php?id=<?= $orderId ?>"
                   class="btn btn-outline-secondary px-4">Go Back</a>
              </div>

            </form>
          </div>
        </div>

      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../../../includes/footer.php'; ?>