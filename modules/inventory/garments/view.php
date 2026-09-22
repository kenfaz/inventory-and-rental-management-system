<?php
// ============================================================
// modules/inventory/garments/view.php
// View full garment details and rental/reservation history
// ============================================================

require_once __DIR__ . '/../../../../includes/auth-guard.php';
require_once __DIR__ . '/../../../../config/db.php';
require_once __DIR__ . '/../../../../helpers/rental-helper.php';

$pageTitle  = 'Garment Details';
$activePage = 'inventory-garments';
$role       = $_SESSION['user_role'];

$garmentId = (int)($_GET['id'] ?? 0);
if ($garmentId === 0) {
    header('Location: /tailorshop/modules/inventory/garments/index.php');
    exit;
}

$stmt = $pdo->prepare('
    SELECT g.*, gc.category_name
    FROM garment_items g
    JOIN garment_categories gc ON g.category_id = gc.category_id
    WHERE g.garment_id = ?
');
$stmt->execute([$garmentId]);
$garment = $stmt->fetch();

if (!$garment) {
    $_SESSION['error'] = 'Garment not found.';
    header('Location: /tailorshop/modules/inventory/garments/index.php');
    exit;
}

// Rental history
$rentals = $pdo->prepare('
    SELECT r.*, c.full_name AS customer_name, u.full_name AS processed_by_name
    FROM rentals r
    JOIN customers c ON r.customer_id = c.customer_id
    JOIN users     u ON r.processed_by = u.user_id
    WHERE r.garment_id = ?
    ORDER BY r.created_at DESC
');
$rentals->execute([$garmentId]);
$rentals = $rentals->fetchAll();

// Reservation history
$reservations = $pdo->prepare('
    SELECT res.*, c.full_name AS customer_name, u.full_name AS processed_by_name
    FROM reservations res
    JOIN customers c ON res.customer_id = c.customer_id
    JOIN users     u ON res.processed_by = u.user_id
    WHERE res.garment_id = ?
    ORDER BY res.created_at DESC
');
$reservations->execute([$garmentId]);
$reservations = $reservations->fetchAll();

$success = $_SESSION['success'] ?? '';
$error   = $_SESSION['error']   ?? '';
unset($_SESSION['success'], $_SESSION['error']);

require_once __DIR__ . '/../../../../includes/header.php';
require_once __DIR__ . '/../../../../includes/sidebar.php';
?>

<div class="main-content">
  <div class="topbar">
    <span class="topbar-title">
      <?= htmlspecialchars($garment['garment_code']) ?> —
      <?= htmlspecialchars($garment['garment_name']) ?>
    </span>
  </div>

  <div class="page-body">

    <nav class="breadcrumb-nav mb-3">
      <a href="/tailorshop/modules/inventory/garments/index.php">
        <i class="bi bi-bag-heart me-1"></i>Garments
      </a>
      <span class="mx-2">/</span>
      <span><?= htmlspecialchars($garment['garment_code']) ?></span>
    </nav>

    <?php if ($success): ?>
      <div class="alert alert-success alert-dismissible fade show d-flex align-items-center gap-2">
        <i class="bi bi-check-circle-fill"></i><?= htmlspecialchars($success) ?>
        <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
      </div>
    <?php endif; ?>

    <div class="row g-3">

      <!-- Garment details -->
      <div class="col-lg-4">
        <div class="card mb-3">
          <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="bi bi-bag-heart me-2 text-primary"></i>Garment Details</span>
            <?php if ($role === 'admin'): ?>
              <a href="/tailorshop/modules/inventory/garments/edit.php?id=<?= $garmentId ?>"
                 class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-pencil"></i> Edit
              </a>
            <?php endif; ?>
          </div>
          <div class="card-body p-4">
            <dl class="row mb-0" style="font-size:13.5px">

              <dt class="col-5 text-muted fw-normal">Code</dt>
              <dd class="col-7 fw-semibold" style="font-family:monospace">
                <?= htmlspecialchars($garment['garment_code']) ?>
              </dd>

              <dt class="col-5 text-muted fw-normal">Category</dt>
              <dd class="col-7"><?= htmlspecialchars($garment['category_name']) ?></dd>

              <dt class="col-5 text-muted fw-normal">Size</dt>
              <dd class="col-7"><?= htmlspecialchars($garment['size'] ?? '—') ?></dd>

              <dt class="col-5 text-muted fw-normal">Color</dt>
              <dd class="col-7"><?= htmlspecialchars($garment['color'] ?? '—') ?></dd>

              <dt class="col-5 text-muted fw-normal">Rental Price</dt>
              <dd class="col-7 text-success fw-semibold">
                <?= $garment['rental_price'] !== null
                    ? '₱' . number_format($garment['rental_price'], 2)
                    : '—' ?>
              </dd>

              <dt class="col-5 text-muted fw-normal">Sale Price</dt>
              <dd class="col-7 fw-semibold">
                <?= $garment['sale_price'] !== null
                    ? '₱' . number_format($garment['sale_price'], 2)
                    : '—' ?>
              </dd>

              <dt class="col-5 text-muted fw-normal">Condition</dt>
              <dd class="col-7">
                <?php
                $condColors = [
                    'Good'          => 'success',
                    'Needs Cleaning'=> 'warning',
                    'Needs Repair'  => 'warning',
                    'Damaged'       => 'danger',
                ];
                $cc = $condColors[$garment['condition_status']] ?? 'secondary';
                ?>
                <span class="badge bg-<?= $cc ?> bg-opacity-10
                             text-<?= $cc ?> rounded-pill">
                  <?= htmlspecialchars($garment['condition_status']) ?>
                </span>
              </dd>

              <dt class="col-5 text-muted fw-normal">Availability</dt>
              <dd class="col-7">
                <?= garmentStatusBadge($garment['availability_status']) ?>
              </dd>

              <?php if ($garment['description']): ?>
                <dt class="col-5 text-muted fw-normal">Description</dt>
                <dd class="col-7"><?= htmlspecialchars($garment['description']) ?></dd>
              <?php endif; ?>

              <dt class="col-5 text-muted fw-normal">Added</dt>
              <dd class="col-7">
                <?= date('M j, Y', strtotime($garment['created_at'])) ?>
              </dd>

            </dl>
          </div>
        </div>

        <!-- Quick actions -->
        <?php if ($garment['availability_status'] === 'Available'): ?>
          <div class="card">
            <div class="card-body p-3">
              <div class="d-grid gap-2">
                <a href="/tailorshop/modules/reservations/create.php?garment_id=<?= $garmentId ?>"
                   class="btn btn-outline-primary btn-sm">
                  <i class="bi bi-calendar-check me-1"></i> Create Reservation
                </a>
                <a href="/tailorshop/modules/rentals/create.php?garment_id=<?= $garmentId ?>"
                   class="btn btn-outline-warning btn-sm">
                  <i class="bi bi-handbag me-1"></i> Create Rental
                </a>
              </div>
            </div>
          </div>
        <?php endif; ?>
      </div>

      <!-- History -->
      <div class="col-lg-8">

        <!-- Rental history -->
        <div class="card mb-3">
          <div class="card-header">
            <i class="bi bi-clock-history me-2 text-warning"></i>
            Rental History
            <span class="badge bg-secondary ms-1"><?= count($rentals) ?></span>
          </div>
          <div class="table-responsive">
            <table class="table mb-0">
              <thead>
                <tr>
                  <th>#</th>
                  <th>Customer</th>
                  <th>Rental Date</th>
                  <th>Return Date</th>
                  <th>Fee</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($rentals)): ?>
                  <tr>
                    <td colspan="6" class="text-center text-muted py-4">
                      No rental history yet.
                    </td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($rentals as $r): ?>
                    <tr>
                      <td>
                        <a href="/tailorshop/modules/rentals/view.php?id=<?= $r['rental_id'] ?>"
                           class="fw-medium text-decoration-none text-primary">
                          #<?= str_pad($r['rental_id'], 4, '0', STR_PAD_LEFT) ?>
                        </a>
                      </td>
                      <td><?= htmlspecialchars($r['customer_name']) ?></td>
                      <td><?= date('M j, Y', strtotime($r['rental_date'])) ?></td>
                      <td><?= date('M j, Y', strtotime($r['expected_return_date'])) ?></td>
                      <td>₱<?= number_format($r['rental_fee'], 2) ?></td>
                      <td><?= rentalStatusBadge($r['status']) ?></td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Reservation history -->
        <div class="card">
          <div class="card-header">
            <i class="bi bi-calendar-check me-2 text-primary"></i>
            Reservation History
            <span class="badge bg-secondary ms-1"><?= count($reservations) ?></span>
          </div>
          <div class="table-responsive">
            <table class="table mb-0">
              <thead>
                <tr>
                  <th>#</th>
                  <th>Customer</th>
                  <th>Pickup Date</th>
                  <th>Return Date</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($reservations)): ?>
                  <tr>
                    <td colspan="5" class="text-center text-muted py-4">
                      No reservation history yet.
                    </td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($reservations as $res): ?>
                    <tr>
                      <td>
                        <a href="/tailorshop/modules/reservations/view.php?id=<?= $res['reservation_id'] ?>"
                           class="fw-medium text-decoration-none text-primary">
                          #<?= str_pad($res['reservation_id'], 4, '0', STR_PAD_LEFT) ?>
                        </a>
                      </td>
                      <td><?= htmlspecialchars($res['customer_name']) ?></td>
                      <td><?= date('M j, Y', strtotime($res['pickup_date'])) ?></td>
                      <td><?= date('M j, Y', strtotime($res['return_date'])) ?></td>
                      <td><?= reservationStatusBadge($res['status']) ?></td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../../../../includes/footer.php'; ?>