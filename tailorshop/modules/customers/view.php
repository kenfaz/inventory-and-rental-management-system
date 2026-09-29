<?php
// ============================================================
// modules/customers/view.php
// Customer profile and full transaction history
// ============================================================

require_once __DIR__ . '/../../../includes/auth-guard.php';
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../helpers/rental-helper.php';

$pageTitle  = 'Customer Profile';
$activePage = 'customers';
$role       = $_SESSION['user_role'];

$customerId = (int)($_GET['id'] ?? 0);
if ($customerId === 0) {
    header('Location: /tailorshop/modules/customers/index.php');
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM customers WHERE customer_id = ?');
$stmt->execute([$customerId]);
$customer = $stmt->fetch();

if (!$customer) {
    $_SESSION['error'] = 'Customer not found.';
    header('Location: /tailorshop/modules/customers/index.php');
    exit;
}

// Rentals
$rentals = $pdo->prepare('
    SELECT r.*, g.garment_name, g.garment_code
    FROM rentals r
    JOIN garment_items g ON r.garment_id = g.garment_id
    WHERE r.customer_id = ?
    ORDER BY r.created_at DESC
');
$rentals->execute([$customerId]);
$rentals = $rentals->fetchAll();

// Reservations
$reservations = $pdo->prepare('
    SELECT res.*, g.garment_name, g.garment_code
    FROM reservations res
    JOIN garment_items g ON res.garment_id = g.garment_id
    WHERE res.customer_id = ?
    ORDER BY res.created_at DESC
');
$reservations->execute([$customerId]);
$reservations = $reservations->fetchAll();

// Tailoring orders
$orders = $pdo->prepare('
    SELECT o.*, u.full_name AS staff_name
    FROM tailoring_orders o
    JOIN users u ON o.assigned_staff_id = u.user_id
    WHERE o.customer_id = ?
    ORDER BY o.created_at DESC
');
$orders->execute([$customerId]);
$orders = $orders->fetchAll();

// Sales records
$sales = $pdo->prepare('
    SELECT s.*, u.full_name AS processed_by_name
    FROM sales_records s
    JOIN users u ON s.processed_by = u.user_id
    WHERE s.customer_id = ?
    ORDER BY s.sale_date DESC
    LIMIT 10
');
$sales->execute([$customerId]);
$sales = $sales->fetchAll();

// Totals
$totalSpent = array_sum(array_column($sales, 'amount'));

$success = $_SESSION['success'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);

require_once __DIR__ . '/../../../includes/header.php';
require_once __DIR__ . '/../../../includes/sidebar.php';
?>

<div class="main-content">
  <div class="topbar">
    <span class="topbar-title"><?= htmlspecialchars($customer['full_name']) ?></span>
  </div>

  <div class="page-body">

    <nav class="breadcrumb-nav mb-3">
      <a href="/tailorshop/modules/customers/index.php">
        <i class="bi bi-people me-1"></i>Customers
      </a>
      <span class="mx-2">/</span>
      <span><?= htmlspecialchars($customer['full_name']) ?></span>
    </nav>

    <?php if ($success): ?>
      <div class="alert alert-success alert-dismissible fade show d-flex align-items-center gap-2">
        <i class="bi bi-check-circle-fill"></i><?= htmlspecialchars($success) ?>
        <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
      </div>
    <?php endif; ?>

    <div class="row g-3">

      <!-- Profile card -->
      <div class="col-lg-4">
        <div class="card mb-3">
          <div class="card-body p-4 text-center">
            <?php
            $initials = '';
            foreach (explode(' ', $customer['full_name']) as $w)
                if ($w) $initials .= strtoupper($w[0]);
            $initials = substr($initials, 0, 2);
            ?>
            <div style="width:64px;height:64px;border-radius:50%;
                        background:#EDE9FE;color:#7C3AED;margin:0 auto 12px;
                        display:flex;align-items:center;justify-content:center;
                        font-size:24px;font-weight:700">
              <?= htmlspecialchars($initials) ?>
            </div>
            <h5 class="fw-bold mb-1"><?= htmlspecialchars($customer['full_name']) ?></h5>
            <p class="text-muted mb-0" style="font-size:13.5px">
              <i class="bi bi-phone me-1"></i>
              <?= htmlspecialchars($customer['mobile_number']) ?>
            </p>
            <?php if ($customer['address']): ?>
              <p class="text-muted mt-1 mb-0" style="font-size:12.5px">
                <i class="bi bi-geo-alt me-1"></i>
                <?= htmlspecialchars($customer['address']) ?>
              </p>
            <?php endif; ?>
            <p class="text-muted mt-1 mb-0" style="font-size:12px">
              Customer since <?= date('F Y', strtotime($customer['created_at'])) ?>
            </p>
          </div>
          <div class="card-footer d-flex gap-2 py-2 px-3">
            <a href="/tailorshop/modules/customers/edit.php?id=<?= $customerId ?>"
               class="btn btn-outline-secondary btn-sm flex-fill">
              <i class="bi bi-pencil me-1"></i> Edit
            </a>
            <a href="/tailorshop/modules/reservations/create.php?customer_id=<?= $customerId ?>"
               class="btn btn-outline-primary btn-sm flex-fill">
              <i class="bi bi-calendar-plus me-1"></i> Reservation
            </a>
          </div>
        </div>

        <!-- Summary stats -->
        <div class="card">
          <div class="card-body p-3">
            <div class="row g-2 text-center">
              <div class="col-4">
                <div class="fw-bold fs-5 text-warning"><?= count($rentals) ?></div>
                <div class="text-muted" style="font-size:11px">Rentals</div>
              </div>
              <div class="col-4">
                <div class="fw-bold fs-5 text-primary"><?= count($reservations) ?></div>
                <div class="text-muted" style="font-size:11px">Reservations</div>
              </div>
              <div class="col-4">
                <div class="fw-bold fs-5 text-success"><?= count($orders) ?></div>
                <div class="text-muted" style="font-size:11px">Orders</div>
              </div>
            </div>
            <?php if ($totalSpent > 0): ?>
              <hr class="my-2">
              <div class="text-center">
                <div class="text-muted" style="font-size:11px">Total Spent</div>
                <div class="fw-bold text-success">
                  ₱<?= number_format($totalSpent, 2) ?>
                </div>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- Transaction history -->
      <div class="col-lg-8">

        <!-- Rentals -->
        <div class="card mb-3">
          <div class="card-header">
            <i class="bi bi-handbag me-2 text-warning"></i>
            Rental History
            <span class="badge bg-secondary ms-1"><?= count($rentals) ?></span>
          </div>
          <div class="table-responsive">
            <table class="table mb-0">
              <thead>
                <tr>
                  <th>#</th>
                  <th>Garment</th>
                  <th>Rental Date</th>
                  <th>Return Date</th>
                  <th>Fee</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($rentals)): ?>
                  <tr>
                    <td colspan="6" class="text-center text-muted py-3">
                      No rentals yet.
                    </td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($rentals as $r): ?>
                    <tr>
                      <td>
                        <a href="/tailorshop/modules/rentals/view.php?id=<?= $r['rental_id'] ?>"
                           class="text-primary text-decoration-none fw-medium">
                          #<?= str_pad($r['rental_id'], 4, '0', STR_PAD_LEFT) ?>
                        </a>
                      </td>
                      <td>
                        <div><?= htmlspecialchars($r['garment_name']) ?></div>
                        <div class="text-muted" style="font-size:11px;font-family:monospace">
                          <?= htmlspecialchars($r['garment_code']) ?>
                        </div>
                      </td>
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

        <!-- Reservations -->
        <div class="card mb-3">
          <div class="card-header">
            <i class="bi bi-calendar-check me-2 text-primary"></i>
            Reservations
            <span class="badge bg-secondary ms-1"><?= count($reservations) ?></span>
          </div>
          <div class="table-responsive">
            <table class="table mb-0">
              <thead>
                <tr>
                  <th>#</th>
                  <th>Garment</th>
                  <th>Pickup Date</th>
                  <th>Return Date</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($reservations)): ?>
                  <tr>
                    <td colspan="5" class="text-center text-muted py-3">
                      No reservations yet.
                    </td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($reservations as $res): ?>
                    <tr>
                      <td>
                        <a href="/tailorshop/modules/reservations/view.php?id=<?= $res['reservation_id'] ?>"
                           class="text-primary text-decoration-none fw-medium">
                          #<?= str_pad($res['reservation_id'], 4, '0', STR_PAD_LEFT) ?>
                        </a>
                      </td>
                      <td>
                        <div><?= htmlspecialchars($res['garment_name']) ?></div>
                        <div class="text-muted" style="font-size:11px;font-family:monospace">
                          <?= htmlspecialchars($res['garment_code']) ?>
                        </div>
                      </td>
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

        <!-- Tailoring orders -->
        <div class="card">
          <div class="card-header">
            <i class="bi bi-scissors me-2 text-success"></i>
            Tailoring Orders
            <span class="badge bg-secondary ms-1"><?= count($orders) ?></span>
          </div>
          <div class="table-responsive">
            <table class="table mb-0">
              <thead>
                <tr>
                  <th>#</th>
                  <th>Order Name</th>
                  <th>Price</th>
                  <th>Balance</th>
                  <th>Due Date</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($orders)): ?>
                  <tr>
                    <td colspan="6" class="text-center text-muted py-3">
                      No tailoring orders yet.
                    </td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($orders as $o): ?>
                    <tr>
                      <td>
                        <a href="/tailorshop/modules/tailoring/view.php?id=<?= $o['order_id'] ?>"
                           class="text-primary text-decoration-none fw-medium">
                          #<?= str_pad($o['order_id'], 4, '0', STR_PAD_LEFT) ?>
                        </a>
                      </td>
                      <td><?= htmlspecialchars($o['order_name']) ?></td>
                      <td>₱<?= number_format($o['price'], 2) ?></td>
                      <td>
                        <?php if ($o['balance'] > 0): ?>
                          <span class="text-danger fw-medium">
                            ₱<?= number_format($o['balance'], 2) ?>
                          </span>
                        <?php else: ?>
                          <span class="text-success">Paid</span>
                        <?php endif; ?>
                      </td>
                      <td><?= date('M j, Y', strtotime($o['due_date'])) ?></td>
                      <td><?= orderStatusBadge($o['status']) ?></td>
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

<?php require_once __DIR__ . '/../../../includes/footer.php'; ?>