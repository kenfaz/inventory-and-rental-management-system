<?php
// ============================================================
// modules/reservations/view.php
// View full reservation details with convert/cancel actions
// ============================================================

require_once __DIR__ . '/../../../includes/auth-guard.php';
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../helpers/rental-helper.php';

$pageTitle  = 'Reservation Details';
$activePage = 'reservations';
$role       = $_SESSION['user_role'];

$resId = (int)($_GET['id'] ?? 0);
if ($resId === 0) {
    header('Location: /tailorshop/modules/reservations/index.php');
    exit;
}

$stmt = $pdo->prepare('
    SELECT res.*,
           c.full_name    AS customer_name,
           c.mobile_number,
           c.customer_id,
           g.garment_name, g.garment_code, g.rental_price,
           g.garment_id,
           u.full_name    AS processed_by_name
    FROM reservations res
    JOIN customers     c ON res.customer_id = c.customer_id
    JOIN garment_items g ON res.garment_id  = g.garment_id
    JOIN users         u ON res.processed_by = u.user_id
    WHERE res.reservation_id = ?
');
$stmt->execute([$resId]);
$res = $stmt->fetch();

if (!$res) {
    $_SESSION['error'] = 'Reservation not found.';
    header('Location: /tailorshop/modules/reservations/index.php');
    exit;
}

$success = $_SESSION['success'] ?? '';
$error   = $_SESSION['error']   ?? '';
unset($_SESSION['success'], $_SESSION['error']);

$isActive   = in_array($res['status'], ['Pending','Confirmed']);
$daysToPickup = (int)floor(
    (strtotime($res['pickup_date']) - time()) / 86400
);

require_once __DIR__ . '/../../../includes/header.php';
require_once __DIR__ . '/../../../includes/sidebar.php';
?>

<div class="main-content">
  <div class="topbar">
    <span class="topbar-title">
      Reservation #<?= str_pad($resId, 4, '0', STR_PAD_LEFT) ?>
    </span>
  </div>

  <div class="page-body">

    <nav class="breadcrumb-nav mb-3">
      <a href="/tailorshop/modules/reservations/index.php">
        <i class="bi bi-calendar-check me-1"></i>Reservations
      </a>
      <span class="mx-2">/</span>
      <span>#<?= str_pad($resId, 4, '0', STR_PAD_LEFT) ?></span>
    </nav>

    <?php if ($success): ?>
      <div class="alert alert-success alert-dismissible fade show d-flex align-items-center gap-2">
        <i class="bi bi-check-circle-fill"></i><?= htmlspecialchars($success) ?>
        <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
      </div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center gap-2">
        <i class="bi bi-exclamation-circle-fill"></i><?= htmlspecialchars($error) ?>
        <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
      </div>
    <?php endif; ?>

    <div class="row g-3">

      <!-- Details -->
      <div class="col-lg-8">
        <div class="card">
          <div class="card-header d-flex justify-content-between align-items-center">
            <span>
              <i class="bi bi-calendar-check me-2 text-primary"></i>
              Reservation Details
            </span>
            <?= reservationStatusBadge($res['status']) ?>
          </div>
          <div class="card-body p-4">
            <div class="row g-3">

              <div class="col-sm-6">
                <div class="text-muted small mb-1">Customer</div>
                <div class="fw-semibold">
                  <a href="/tailorshop/modules/customers/view.php?id=<?= $res['customer_id'] ?>"
                     class="text-decoration-none">
                    <?= htmlspecialchars($res['customer_name']) ?>
                  </a>
                </div>
              </div>
              <div class="col-sm-6">
                <div class="text-muted small mb-1">Mobile Number</div>
                <div class="fw-semibold">
                  <i class="bi bi-phone me-1 text-muted"></i>
                  <?= htmlspecialchars($res['mobile_number']) ?>
                </div>
              </div>

              <div class="col-sm-6">
                <div class="text-muted small mb-1">Garment</div>
                <div class="fw-semibold">
                  <a href="/tailorshop/modules/inventory/garments/view.php?id=<?= $res['garment_id'] ?>"
                     class="text-decoration-none">
                    <?= htmlspecialchars($res['garment_name']) ?>
                  </a>
                </div>
                <div class="text-muted" style="font-size:11px;font-family:monospace">
                  <?= htmlspecialchars($res['garment_code']) ?>
                </div>
              </div>
              <div class="col-sm-6">
                <div class="text-muted small mb-1">Rental Price</div>
                <div class="fw-semibold text-success">
                  <?= $res['rental_price'] !== null
                      ? '₱' . number_format($res['rental_price'], 2)
                      : '—' ?>
                </div>
              </div>

              <div class="col-sm-6">
                <div class="text-muted small mb-1">Pickup Date</div>
                <div class="fw-semibold">
                  <?= date('F j, Y', strtotime($res['pickup_date'])) ?>
                  <?php if ($isActive && $daysToPickup >= 0): ?>
                    <span class="badge <?= $daysToPickup <= 3 ? 'bg-warning text-dark' : 'bg-secondary' ?> ms-1"
                          style="font-size:10px">
                      <?= $daysToPickup === 0 ? 'Today' : 'in ' . $daysToPickup . 'd' ?>
                    </span>
                  <?php endif; ?>
                </div>
              </div>
              <div class="col-sm-6">
                <div class="text-muted small mb-1">Return Date</div>
                <div class="fw-semibold">
                  <?= date('F j, Y', strtotime($res['return_date'])) ?>
                </div>
              </div>

              <div class="col-sm-6">
                <div class="text-muted small mb-1">Processed By</div>
                <div><?= htmlspecialchars($res['processed_by_name']) ?></div>
              </div>
              <div class="col-sm-6">
                <div class="text-muted small mb-1">Created</div>
                <div><?= date('F j, Y g:i A', strtotime($res['created_at'])) ?></div>
              </div>

              <?php if ($res['notes']): ?>
                <div class="col-12">
                  <div class="text-muted small mb-1">Notes</div>
                  <div class="p-3 rounded" style="background:#f9fafb;font-size:13.5px">
                    <?= nl2br(htmlspecialchars($res['notes'])) ?>
                  </div>
                </div>
              <?php endif; ?>

              <?php if ($res['cancellation_reason']): ?>
                <div class="col-12">
                  <div class="text-muted small mb-1">Cancellation Reason</div>
                  <div class="alert alert-danger py-2 mb-0" style="font-size:13.5px">
                    <?= htmlspecialchars($res['cancellation_reason']) ?>
                  </div>
                </div>
              <?php endif; ?>

            </div>
          </div>
        </div>
      </div>

      <!-- Actions -->
      <div class="col-lg-4">
        <div class="card">
          <div class="card-header">
            <i class="bi bi-lightning me-2 text-warning"></i>Actions
          </div>
          <div class="card-body p-3">

            <?php if ($isActive): ?>
              <!-- Convert to rental -->
              <div class="d-grid mb-2">
                <a href="/tailorshop/modules/reservations/convert.php?id=<?= $resId ?>"
                   class="btn btn-success">
                  <i class="bi bi-arrow-right-circle me-1"></i>
                  Convert to Rental
                </a>
              </div>
              <!-- Cancel -->
              <div class="d-grid mb-2">
                <a href="/tailorshop/modules/reservations/cancel.php?id=<?= $resId ?>"
                   class="btn btn-outline-danger">
                  <i class="bi bi-x-circle me-1"></i> Cancel Reservation
                </a>
              </div>
            <?php else: ?>
              <p class="text-muted small text-center py-2 mb-0">
                This reservation is <?= strtolower($res['status']) ?> and has no further actions.
              </p>
            <?php endif; ?>

            <hr>
            <div class="d-grid">
              <a href="/tailorshop/modules/customers/view.php?id=<?= $res['customer_id'] ?>"
                 class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-person me-1"></i> View Customer Profile
              </a>
            </div>
            <div class="d-grid mt-2">
              <a href="/tailorshop/modules/inventory/garments/view.php?id=<?= $res['garment_id'] ?>"
                 class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-bag-heart me-1"></i> View Garment
              </a>
            </div>

          </div>
        </div>
      </div>

    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../../../includes/footer.php'; ?>