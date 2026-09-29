<?php
// ============================================================
// modules/dashboard/employee.php
// Staff dashboard — assigned orders, active rentals, tasks
// ============================================================

require_once __DIR__ . '/../../includes/auth-guard.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../helpers/rental-helper.php';

$pageTitle  = 'Dashboard';
$activePage = 'dashboard';

syncRentalStatuses();

$staffId = $_SESSION['user_id'];

// ── My assigned tailoring orders ───────────────────────────
$myOrders = $pdo->prepare("
    SELECT o.*, c.full_name AS customer_name, c.mobile_number,
           DATEDIFF(o.due_date, CURDATE()) AS days_until_due
    FROM tailoring_orders o
    JOIN customers c ON o.customer_id = c.customer_id
    WHERE o.assigned_staff_id = ?
      AND o.status IN ('Pending','In Progress','Ready for Pickup')
    ORDER BY
        FIELD(o.status,'In Progress','Pending','Ready for Pickup'),
        o.due_date ASC
");
$myOrders->execute([$staffId]);
$myOrders = $myOrders->fetchAll();

// ── All active rentals (staff can see all) ─────────────────
$activeRentals = $pdo->query("
    SELECT r.*,
           c.full_name AS customer_name,
           g.garment_name, g.garment_code,
           DATEDIFF(CURDATE(), r.expected_return_date) AS days_overdue
    FROM rentals r
    JOIN customers     c ON r.customer_id = c.customer_id
    JOIN garment_items g ON r.garment_id  = g.garment_id
    WHERE r.status IN ('Active','Due Today','Overdue')
    ORDER BY
        FIELD(r.status,'Overdue','Due Today','Active'),
        r.expected_return_date ASC
    LIMIT 8
")->fetchAll();

// ── Today's reservations for pickup ───────────────────────
$todayPickups = $pdo->query("
    SELECT res.*,
           c.full_name AS customer_name, c.mobile_number,
           g.garment_name, g.garment_code
    FROM reservations res
    JOIN customers     c ON res.customer_id = c.customer_id
    JOIN garment_items g ON res.garment_id  = g.garment_id
    WHERE res.status = 'Confirmed'
      AND res.pickup_date = CURDATE()
")->fetchAll();

// ── Summary counts ─────────────────────────────────────────
$myOrderCount   = count($myOrders);
$activeRentalCount = $pdo->query("
    SELECT COUNT(*) FROM rentals
    WHERE status IN ('Active','Due Today','Overdue')
")->fetchColumn();
$overdueCount   = $pdo->query("
    SELECT COUNT(*) FROM rentals WHERE status = 'Overdue'
")->fetchColumn();
$pickupCount    = count($todayPickups);

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="main-content">
  <div class="topbar">
    <span class="topbar-title">Dashboard</span>
    <span class="text-muted small" id="current-date"></span>
  </div>

  <div class="page-body">

    <!-- Welcome -->
    <div class="mb-4">
      <h5 class="fw-bold mb-1">
        Good <?= date('H') < 12 ? 'morning' : (date('H') < 17 ? 'afternoon' : 'evening') ?>,
        <?= htmlspecialchars(explode(' ', $_SESSION['user_name'])[0]) ?>! 👋
      </h5>
      <p class="text-muted small mb-0">
        Here's your task summary for today at <?= APP_NAME ?>.
      </p>
    </div>

    <!-- Summary cards -->
    <div class="row g-3 mb-4">

      <div class="col-sm-6 col-lg-3">
        <div class="stat-card" style="border-left:3px solid #7C3AED">
          <div class="stat-icon" style="background:#EDE9FE;color:#6D28D9">
            <i class="bi bi-scissors"></i>
          </div>
          <div>
            <div class="stat-label">My Active Orders</div>
            <div class="stat-value" style="color:#6D28D9"><?= $myOrderCount ?></div>
          </div>
        </div>
      </div>

      <div class="col-sm-6 col-lg-3">
        <div class="stat-card" style="border-left:3px solid #1D4ED8">
          <div class="stat-icon" style="background:#EFF6FF;color:#1D4ED8">
            <i class="bi bi-handbag"></i>
          </div>
          <div>
            <div class="stat-label">Active Rentals</div>
            <div class="stat-value" style="color:#1D4ED8"><?= (int)$activeRentalCount ?></div>
          </div>
        </div>
      </div>

      <div class="col-sm-6 col-lg-3">
        <div class="stat-card"
             style="border-left:3px solid <?= $overdueCount > 0 ? '#DC2626' : '#9CA3AF' ?>">
          <div class="stat-icon"
               style="background:<?= $overdueCount > 0 ? '#FEF2F2' : '#F3F4F6' ?>;
                      color:<?= $overdueCount > 0 ? '#991B1B' : '#6B7280' ?>">
            <i class="bi bi-exclamation-circle"></i>
          </div>
          <div>
            <div class="stat-label">Overdue Rentals</div>
            <div class="stat-value"
                 style="color:<?= $overdueCount > 0 ? '#991B1B' : '#6B7280' ?>">
              <?= (int)$overdueCount ?>
            </div>
          </div>
        </div>
      </div>

      <div class="col-sm-6 col-lg-3">
        <div class="stat-card"
             style="border-left:3px solid <?= $pickupCount > 0 ? '#059669' : '#9CA3AF' ?>">
          <div class="stat-icon"
               style="background:<?= $pickupCount > 0 ? '#F0FDF4' : '#F3F4F6' ?>;
                      color:<?= $pickupCount > 0 ? '#166534' : '#6B7280' ?>">
            <i class="bi bi-calendar-check"></i>
          </div>
          <div>
            <div class="stat-label">Today's Pickups</div>
            <div class="stat-value"
                 style="color:<?= $pickupCount > 0 ? '#166534' : '#6B7280' ?>">
              <?= $pickupCount ?>
            </div>
          </div>
        </div>
      </div>

    </div>

    <div class="row g-3">

      <!-- Left column -->
      <div class="col-lg-8">

        <!-- My assigned tailoring orders -->
        <div class="card mb-3">
          <div class="card-header d-flex justify-content-between align-items-center">
            <span>
              <i class="bi bi-scissors me-2 text-primary"></i>
              My Tailoring Orders
              <?php if ($myOrderCount > 0): ?>
                <span class="badge bg-primary ms-1"><?= $myOrderCount ?></span>
              <?php endif; ?>
            </span>
            <a href="/tailorshop/modules/tailoring/index.php"
               class="btn btn-sm btn-outline-secondary">View All</a>
          </div>

          <?php if (empty($myOrders)): ?>
            <div class="card-body text-center text-muted py-5">
              <i class="bi bi-check-circle fs-3 d-block mb-2 text-success"></i>
              No active orders assigned to you.
            </div>
          <?php else: ?>
            <div class="table-responsive">
              <table class="table mb-0">
                <thead>
                  <tr>
                    <th>#</th>
                    <th>Order</th>
                    <th>Customer</th>
                    <th>Due Date</th>
                    <th>Balance</th>
                    <th>Status</th>
                    <th></th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($myOrders as $o):
                    $overdue  = $o['days_until_due'] !== null && $o['days_until_due'] < 0
                                && $o['status'] !== 'Ready for Pickup';
                    $dueSoon  = $o['days_until_due'] !== null
                                && $o['days_until_due'] >= 0
                                && $o['days_until_due'] <= 3
                                && $o['status'] !== 'Ready for Pickup';
                  ?>
                    <tr class="<?= $overdue ? 'table-danger' : ($dueSoon ? 'table-warning' : '') ?>">
                      <td>
                        <span class="text-primary fw-medium">
                          #<?= str_pad($o['order_id'], 4, '0', STR_PAD_LEFT) ?>
                        </span>
                      </td>
                      <td>
                        <div class="fw-medium"><?= htmlspecialchars($o['order_name']) ?></div>
                        <div class="text-muted" style="font-size:11.5px">
                          <?= htmlspecialchars(substr($o['garment_description'], 0, 40)) ?>…
                        </div>
                      </td>
                      <td>
                        <div><?= htmlspecialchars($o['customer_name']) ?></div>
                        <div class="text-muted" style="font-size:11.5px">
                          <?= htmlspecialchars($o['mobile_number']) ?>
                        </div>
                      </td>
                      <td>
                        <?= date('M j, Y', strtotime($o['due_date'])) ?>
                        <?php if ($overdue): ?>
                          <div class="text-danger" style="font-size:11px;font-weight:600">
                            <?= abs($o['days_until_due']) ?>d overdue
                          </div>
                        <?php elseif ($dueSoon && $o['days_until_due'] > 0): ?>
                          <div class="text-warning" style="font-size:11px;font-weight:600">
                            in <?= $o['days_until_due'] ?> day<?= $o['days_until_due'] > 1 ? 's' : '' ?>
                          </div>
                        <?php elseif ($o['days_until_due'] === 0): ?>
                          <div class="text-danger" style="font-size:11px;font-weight:600">Due today</div>
                        <?php endif; ?>
                      </td>
                      <td>
                        <?php if ((float)$o['balance'] > 0): ?>
                          <span class="text-danger fw-semibold">
                            ₱<?= number_format($o['balance'], 2) ?>
                          </span>
                        <?php else: ?>
                          <span class="text-success">Paid</span>
                        <?php endif; ?>
                      </td>
                      <td><?= orderStatusBadge($o['status']) ?></td>
                      <td>
                        <a href="/tailorshop/modules/tailoring/view.php?id=<?= $o['order_id'] ?>"
                           class="btn btn-sm btn-outline-secondary">View</a>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>

        <!-- Active rentals -->
        <div class="card">
          <div class="card-header d-flex justify-content-between align-items-center">
            <span>
              <i class="bi bi-handbag me-2 text-warning"></i>
              Active Rentals
            </span>
            <a href="/tailorshop/modules/rentals/index.php"
               class="btn btn-sm btn-outline-secondary">View All</a>
          </div>

          <?php if (empty($activeRentals)): ?>
            <div class="card-body text-center text-muted py-4">
              <i class="bi bi-check-circle fs-3 d-block mb-2 text-success"></i>
              No active rentals at the moment.
            </div>
          <?php else: ?>
            <div class="table-responsive">
              <table class="table mb-0">
                <thead>
                  <tr>
                    <th>Customer</th>
                    <th>Garment</th>
                    <th>Return Date</th>
                    <th>Status</th>
                    <th></th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($activeRentals as $r): ?>
                    <tr class="<?= $r['status'] === 'Overdue' ? 'table-danger' :
                                  ($r['status'] === 'Due Today' ? 'table-warning' : '') ?>">
                      <td class="fw-medium"><?= htmlspecialchars($r['customer_name']) ?></td>
                      <td>
                        <div><?= htmlspecialchars($r['garment_name']) ?></div>
                        <div class="text-muted" style="font-size:11px;font-family:monospace">
                          <?= htmlspecialchars($r['garment_code']) ?>
                        </div>
                      </td>
                      <td>
                        <?= date('M j, Y', strtotime($r['expected_return_date'])) ?>
                        <?php if ($r['status'] === 'Overdue'): ?>
                          <div class="text-danger" style="font-size:11px;font-weight:600">
                            <?= $r['days_overdue'] ?>d overdue
                          </div>
                        <?php endif; ?>
                      </td>
                      <td><?= rentalStatusBadge($r['status']) ?></td>
                      <td>
                        <a href="/tailorshop/modules/rentals/view.php?id=<?= $r['rental_id'] ?>"
                           class="btn btn-sm btn-outline-secondary">View</a>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>

      </div>

      <!-- Right column -->
      <div class="col-lg-4">

        <!-- Today's pickups -->
        <div class="card mb-3">
          <div class="card-header">
            <i class="bi bi-calendar-event me-2 text-success"></i>
            Today's Pickups
            <?php if ($pickupCount > 0): ?>
              <span class="badge bg-success ms-1"><?= $pickupCount ?></span>
            <?php endif; ?>
          </div>
          <?php if (empty($todayPickups)): ?>
            <div class="card-body text-center text-muted py-4" style="font-size:13.5px">
              <i class="bi bi-calendar-x d-block fs-4 mb-2"></i>
              No pickups scheduled today.
            </div>
          <?php else: ?>
            <div class="card-body p-0">
              <?php foreach ($todayPickups as $res): ?>
                <div class="px-3 py-2 border-bottom">
                  <div class="d-flex justify-content-between align-items-start">
                    <div>
                      <div class="fw-medium" style="font-size:13px">
                        <?= htmlspecialchars($res['customer_name']) ?>
                      </div>
                      <div class="text-muted" style="font-size:11px">
                        <?= htmlspecialchars($res['garment_name']) ?>
                        · <?= htmlspecialchars($res['mobile_number']) ?>
                      </div>
                    </div>
                    <a href="/tailorshop/modules/reservations/view.php?id=<?= $res['reservation_id'] ?>"
                       class="btn btn-sm btn-outline-success ms-2">View</a>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>

        <!-- Quick actions -->
        <div class="card">
          <div class="card-header">
            <i class="bi bi-lightning me-2 text-warning"></i>Quick Actions
          </div>
          <div class="card-body p-3">
            <div class="d-grid gap-2">
              <a href="/tailorshop/modules/reservations/create.php"
                 class="btn btn-outline-primary btn-sm">
                <i class="bi bi-calendar-plus me-1"></i> New Reservation
              </a>
              <a href="/tailorshop/modules/rentals/create.php"
                 class="btn btn-outline-warning btn-sm">
                <i class="bi bi-handbag me-1"></i> New Rental
              </a>
              <a href="/tailorshop/modules/tailoring/create.php"
                 class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-scissors me-1"></i> New Tailoring Order
              </a>
              <a href="/tailorshop/modules/customers/add.php"
                 class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-person-plus me-1"></i> Add Customer
              </a>
            </div>
          </div>
        </div>

      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>