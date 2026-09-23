<?php
// ============================================================
// modules/rentals/view.php
// Full rental details with penalty preview and actions
// ============================================================

require_once __DIR__ . '/../../../includes/auth-guard.php';
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../helpers/rental-helper.php';

$pageTitle  = 'Rental Details';
$activePage = 'rentals';
$role       = $_SESSION['user_role'];

syncRentalStatuses();

$rentalId = (int)($_GET['id'] ?? 0);
if ($rentalId === 0) {
    header('Location: /tailorshop/modules/rentals/index.php');
    exit;
}

$stmt = $pdo->prepare('
    SELECT r.*,
           c.full_name   AS customer_name,
           c.mobile_number,
           c.customer_id,
           g.garment_name, g.garment_code,
           g.garment_id,
           u.full_name   AS processed_by_name,
           DATEDIFF(CURDATE(), r.expected_return_date) AS days_overdue
    FROM rentals r
    JOIN customers     c ON r.customer_id = c.customer_id
    JOIN garment_items g ON r.garment_id  = g.garment_id
    JOIN users         u ON r.processed_by = u.user_id
    WHERE r.rental_id = ?
');
$stmt->execute([$rentalId]);
$rental = $stmt->fetch();

if (!$rental) {
    $_SESSION['error'] = 'Rental not found.';
    header('Location: /tailorshop/modules/rentals/index.php');
    exit;
}

// Fetch condition report if returned
$condReport = null;
if ($rental['status'] === 'Returned') {
    $cr = $pdo->prepare('
        SELECT rcr.*, u.full_name AS recorded_by_name
        FROM rental_condition_reports rcr
        JOIN users u ON rcr.recorded_by = u.user_id
        WHERE rcr.rental_id = ?
    ');
    $cr->execute([$rentalId]);
    $condReport = $cr->fetch();
}

// Live penalty preview
$penalty = computePenalty(
    $rental['expected_return_date'],
    $rental['penalty_rate_per_day']
);

$success = $_SESSION['success'] ?? '';
$error   = $_SESSION['error']   ?? '';
unset($_SESSION['success'], $_SESSION['error']);

$isActive = in_array($rental['status'], ['Active','Due Today','Overdue']);

require_once __DIR__ . '/../../../includes/header.php';
require_once __DIR__ . '/../../../includes/sidebar.php';
?>

<div class="main-content">
  <div class="topbar">
    <span class="topbar-title">
      Rental #<?= str_pad($rentalId, 4, '0', STR_PAD_LEFT) ?>
    </span>
  </div>

  <div class="page-body">

    <nav class="breadcrumb-nav mb-3">
      <a href="/tailorshop/modules/rentals/index.php">
        <i class="bi bi-handbag me-1"></i>Rentals
      </a>
      <span class="mx-2">/</span>
      <span>#<?= str_pad($rentalId, 4, '0', STR_PAD_LEFT) ?></span>
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

    <?php if ($rental['status'] === 'Overdue'): ?>
      <div class="alert alert-danger d-flex align-items-center gap-2 mb-3">
        <i class="bi bi-exclamation-circle-fill"></i>
        <strong>Overdue <?= $rental['days_overdue'] ?> day<?= $rental['days_overdue'] > 1 ? 's' : '' ?>.</strong>
        &nbsp;Accrued penalty: ₱<?= number_format($penalty['total_penalty'], 2) ?>
      </div>
    <?php endif; ?>

    <div class="row g-3">

      <!-- Details card -->
      <div class="col-lg-8">
        <div class="card mb-3">
          <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="bi bi-handbag me-2 text-warning"></i>Rental Details</span>
            <?= rentalStatusBadge($rental['status']) ?>
          </div>
          <div class="card-body p-4">
            <div class="row g-3">

              <div class="col-sm-6">
                <div class="text-muted small mb-1">Customer</div>
                <div class="fw-semibold">
                  <a href="/tailorshop/modules/customers/view.php?id=<?= $rental['customer_id'] ?>"
                     class="text-decoration-none">
                    <?= htmlspecialchars($rental['customer_name']) ?>
                  </a>
                </div>
                <div class="text-muted" style="font-size:12px">
                  <i class="bi bi-phone me-1"></i><?= htmlspecialchars($rental['mobile_number']) ?>
                </div>
              </div>
              <div class="col-sm-6">
                <div class="text-muted small mb-1">Garment</div>
                <div class="fw-semibold">
                  <a href="/tailorshop/modules/inventory/garments/view.php?id=<?= $rental['garment_id'] ?>"
                     class="text-decoration-none">
                    <?= htmlspecialchars($rental['garment_name']) ?>
                  </a>
                </div>
                <div class="text-muted" style="font-size:12px;font-family:monospace">
                  <?= htmlspecialchars($rental['garment_code']) ?>
                </div>
              </div>

              <div class="col-sm-6">
                <div class="text-muted small mb-1">Rental Date</div>
                <div><?= date('F j, Y', strtotime($rental['rental_date'])) ?></div>
              </div>
              <div class="col-sm-6">
                <div class="text-muted small mb-1">Expected Return Date</div>
                <div class="fw-semibold <?= $rental['status'] === 'Overdue' ? 'text-danger' : '' ?>">
                  <?= date('F j, Y', strtotime($rental['expected_return_date'])) ?>
                </div>
              </div>

              <?php if ($rental['actual_return_date']): ?>
                <div class="col-sm-6">
                  <div class="text-muted small mb-1">Actual Return Date</div>
                  <div class="fw-semibold text-success">
                    <?= date('F j, Y', strtotime($rental['actual_return_date'])) ?>
                  </div>
                </div>
              <?php endif; ?>

              <div class="col-sm-4">
                <div class="text-muted small mb-1">Rental Fee</div>
                <div class="fw-semibold text-success">
                  ₱<?= number_format($rental['rental_fee'], 2) ?>
                </div>
              </div>
              <div class="col-sm-4">
                <div class="text-muted small mb-1">Deposit</div>
                <div class="fw-semibold">
                  ₱<?= number_format($rental['deposit_amount'], 2) ?>
                </div>
              </div>
              <div class="col-sm-4">
                <div class="text-muted small mb-1">Penalty Rate/Day</div>
                <div class="fw-semibold">
                  ₱<?= number_format($rental['penalty_rate_per_day'], 2) ?>
                </div>
              </div>

              <?php if ((float)$rental['total_penalty'] > 0): ?>
                <div class="col-sm-6">
                  <div class="text-muted small mb-1">Total Penalty Applied</div>
                  <div class="fw-semibold text-danger">
                    ₱<?= number_format($rental['total_penalty'], 2) ?>
                  </div>
                </div>
              <?php endif; ?>

              <div class="col-sm-6">
                <div class="text-muted small mb-1">Payment Method</div>
                <div>
                  <?= $rental['payment_method']
                      ? htmlspecialchars($rental['payment_method'] === 'Other'
                          ? $rental['payment_other']
                          : $rental['payment_method'])
                      : '—' ?>
                </div>
              </div>

              <div class="col-sm-6">
                <div class="text-muted small mb-1">Processed By</div>
                <div><?= htmlspecialchars($rental['processed_by_name']) ?></div>
              </div>
              <div class="col-sm-6">
                <div class="text-muted small mb-1">Created</div>
                <div><?= date('F j, Y g:i A', strtotime($rental['created_at'])) ?></div>
              </div>

              <?php if ($rental['reservation_id']): ?>
                <div class="col-12">
                  <div class="text-muted small mb-1">Converted From Reservation</div>
                  <a href="/tailorshop/modules/reservations/view.php?id=<?= $rental['reservation_id'] ?>"
                     class="text-decoration-none">
                    #<?= str_pad($rental['reservation_id'], 4, '0', STR_PAD_LEFT) ?>
                  </a>
                </div>
              <?php endif; ?>

            </div>
          </div>
        </div>

        <!-- Condition report -->
        <?php if ($condReport): ?>
          <div class="card">
            <div class="card-header">
              <i class="bi bi-clipboard-check me-2 text-success"></i>Return Condition Report
            </div>
            <div class="card-body p-4">
              <div class="row g-3">
                <div class="col-sm-6">
                  <div class="text-muted small mb-1">Condition on Return</div>
                  <?php
                  $condColors = [
                      'Good'           => 'success',
                      'Needs Cleaning' => 'warning',
                      'Needs Repair'   => 'warning',
                      'Damaged'        => 'danger',
                  ];
                  $cc = $condColors[$condReport['condition_on_return']] ?? 'secondary';
                  ?>
                  <span class="badge bg-<?= $cc ?> bg-opacity-15 text-<?= $cc ?> rounded-pill">
                    <?= htmlspecialchars($condReport['condition_on_return']) ?>
                  </span>
                </div>
                <div class="col-sm-6">
                  <div class="text-muted small mb-1">Deposit Status</div>
                  <div class="fw-medium"><?= htmlspecialchars($condReport['deposit_status']) ?></div>
                </div>
                <?php if ($condReport['deposit_deducted'] > 0): ?>
                  <div class="col-sm-6">
                    <div class="text-muted small mb-1">Deposit Deducted</div>
                    <div class="fw-semibold text-danger">
                      ₱<?= number_format($condReport['deposit_deducted'], 2) ?>
                    </div>
                  </div>
                <?php endif; ?>
                <?php if ($condReport['damage_notes']): ?>
                  <div class="col-12">
                    <div class="text-muted small mb-1">Damage Notes</div>
                    <div class="p-3 rounded" style="background:#fff5f5;font-size:13.5px">
                      <?= nl2br(htmlspecialchars($condReport['damage_notes'])) ?>
                    </div>
                  </div>
                <?php endif; ?>
                <div class="col-sm-6">
                  <div class="text-muted small mb-1">Recorded By</div>
                  <div><?= htmlspecialchars($condReport['recorded_by_name']) ?></div>
                </div>
                <div class="col-sm-6">
                  <div class="text-muted small mb-1">Recorded At</div>
                  <div><?= date('F j, Y g:i A', strtotime($condReport['recorded_at'])) ?></div>
                </div>
              </div>
            </div>
          </div>
        <?php endif; ?>
      </div>

      <!-- Actions panel -->
      <div class="col-lg-4">
        <div class="card mb-3">
          <div class="card-header">
            <i class="bi bi-lightning me-2 text-warning"></i>Actions
          </div>
          <div class="card-body p-3">
            <?php if ($isActive): ?>
              <div class="d-grid mb-2">
                <a href="/tailorshop/modules/rentals/return.php?id=<?= $rentalId ?>"
                   class="btn btn-success">
                  <i class="bi bi-box-arrow-in-left me-1"></i> Process Return
                </a>
              </div>
              <div class="d-grid mb-2">
                <a href="/tailorshop/modules/rentals/agreement.php?id=<?= $rentalId ?>"
                   class="btn btn-outline-secondary" target="_blank">
                  <i class="bi bi-printer me-1"></i> Print Agreement
                </a>
              </div>
            <?php else: ?>
              <p class="text-muted small text-center py-2 mb-2">
                This rental is <?= strtolower($rental['status']) ?>.
              </p>
              <?php if ($rental['status'] === 'Returned'): ?>
                <div class="d-grid mb-2">
                  <a href="/tailorshop/modules/rentals/agreement.php?id=<?= $rentalId ?>"
                     class="btn btn-outline-secondary btn-sm" target="_blank">
                    <i class="bi bi-printer me-1"></i> Print Agreement
                  </a>
                </div>
              <?php endif; ?>
            <?php endif; ?>

            <?php if ($isActive && $penalty['days_overdue'] > 0): ?>
              <div class="alert alert-danger py-2 small mt-2 mb-0">
                <strong>Accrued penalty:</strong>
                ₱<?= number_format($penalty['total_penalty'], 2) ?>
                (<?= $penalty['days_overdue'] ?> day<?= $penalty['days_overdue'] > 1 ? 's' : '' ?>
                × ₱<?= number_format($rental['penalty_rate_per_day'], 2) ?>)
              </div>
            <?php endif; ?>

            <hr>
            <div class="d-grid">
              <a href="/tailorshop/modules/customers/view.php?id=<?= $rental['customer_id'] ?>"
                 class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-person me-1"></i> View Customer
              </a>
            </div>
            <div class="d-grid mt-2">
              <a href="/tailorshop/modules/inventory/garments/view.php?id=<?= $rental['garment_id'] ?>"
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