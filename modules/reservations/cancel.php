<?php
// ============================================================
// modules/reservations/cancel.php
// Cancel a reservation — records reason, restores garment
// ============================================================

require_once __DIR__ . '/../../../includes/auth-guard.php';
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../helpers/audit-helper.php';
require_once __DIR__ . '/../../../helpers/inventory-helper.php';

$pageTitle  = 'Cancel Reservation';
$activePage = 'reservations';

$resId = (int)($_GET['id'] ?? 0);
if ($resId === 0) {
    header('Location: /tailorshop/modules/reservations/index.php');
    exit;
}

$stmt = $pdo->prepare('
    SELECT res.*,
           c.full_name   AS customer_name,
           g.garment_name, g.garment_id
    FROM reservations res
    JOIN customers     c ON res.customer_id = c.customer_id
    JOIN garment_items g ON res.garment_id  = g.garment_id
    WHERE res.reservation_id = ?
');
$stmt->execute([$resId]);
$res = $stmt->fetch();

if (!$res) {
    $_SESSION['error'] = 'Reservation not found.';
    header('Location: /tailorshop/modules/reservations/index.php');
    exit;
}

if (!in_array($res['status'], ['Pending','Confirmed'])) {
    $_SESSION['error'] = 'Only Pending or Confirmed reservations can be cancelled.';
    header('Location: /tailorshop/modules/reservations/view.php?id=' . $resId);
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
            UPDATE reservations
            SET status = "Cancelled", cancellation_reason = ?
            WHERE reservation_id = ?
        ')->execute([$reason, $resId]);

        // Restore garment to Available if no other active rentals/reservations
        $otherActive = $pdo->prepare('
            SELECT COUNT(*) FROM reservations
            WHERE garment_id = ? AND status IN ("Pending","Confirmed")
              AND reservation_id != ?
        ');
        $otherActive->execute([$res['garment_id'], $resId]);

        $activeRentals = $pdo->prepare('
            SELECT COUNT(*) FROM rentals
            WHERE garment_id = ? AND status IN ("Active","Due Today","Overdue")
        ');
        $activeRentals->execute([$res['garment_id']]);

        if ($otherActive->fetchColumn() == 0 && $activeRentals->fetchColumn() == 0) {
            updateGarmentStatus($res['garment_id'], 'Available', $_SESSION['user_id']);
        }

        auditStatusChange(
            $_SESSION['user_id'], 'Reservations',
            'reservations', $resId, 'Confirmed', 'Cancelled'
        );

        $_SESSION['success'] = 'Reservation #' .
            str_pad($resId, 4, '0', STR_PAD_LEFT) . ' has been cancelled.';
        header('Location: /tailorshop/modules/reservations/view.php?id=' . $resId);
        exit;
    }
}

require_once __DIR__ . '/../../../includes/header.php';
require_once __DIR__ . '/../../../includes/sidebar.php';
?>

<div class="main-content">
  <div class="topbar">
    <span class="topbar-title">Cancel Reservation</span>
  </div>

  <div class="page-body">

    <nav class="breadcrumb-nav mb-3">
      <a href="/tailorshop/modules/reservations/index.php">
        <i class="bi bi-calendar-check me-1"></i>Reservations
      </a>
      <span class="mx-2">/</span>
      <a href="/tailorshop/modules/reservations/view.php?id=<?= $resId ?>">
        #<?= str_pad($resId, 4, '0', STR_PAD_LEFT) ?>
      </a>
      <span class="mx-2">/</span><span>Cancel</span>
    </nav>

    <div class="row justify-content-center">
      <div class="col-lg-6">

        <!-- Summary -->
        <div class="alert alert-warning d-flex gap-2 align-items-start mb-3">
          <i class="bi bi-exclamation-triangle-fill flex-shrink-0 mt-1"></i>
          <div style="font-size:13.5px">
            You are about to cancel the reservation for
            <strong><?= htmlspecialchars($res['customer_name']) ?></strong>
            — <strong><?= htmlspecialchars($res['garment_name']) ?></strong>.
            This action cannot be undone, but the record will be preserved.
          </div>
        </div>

        <div class="card">
          <div class="card-header">
            <i class="bi bi-x-circle me-2 text-danger"></i>Cancel Reservation
          </div>
          <div class="card-body p-4">
            <form method="POST" novalidate>

              <div class="mb-4">
                <label class="form-label">
                  Cancellation Reason <span class="text-danger">*</span>
                </label>
                <textarea name="cancellation_reason" rows="3"
                          class="form-control <?= isset($errors['reason']) ? 'is-invalid' : '' ?>"
                          placeholder="Enter the reason for cancelling this reservation…"
                          required><?= htmlspecialchars($reason) ?></textarea>
                <?php if (isset($errors['reason'])): ?>
                  <div class="invalid-feedback"><?= $errors['reason'] ?></div>
                <?php endif; ?>
              </div>

              <div class="d-flex gap-2">
                <button type="submit" class="btn btn-danger px-4">
                  <i class="bi bi-x-circle me-1"></i> Confirm Cancellation
                </button>
                <a href="/tailorshop/modules/reservations/view.php?id=<?= $resId ?>"
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