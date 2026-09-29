<?php
// ============================================================
// modules/dashboard/admin.php
// Admin dashboard — summary cards, tracker, low stock, revenue
// ============================================================

require_once __DIR__ . '/../../includes/auth-guard.php';
require_once __DIR__ . '/../../includes/role-guard.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../helpers/rental-helper.php';
require_once __DIR__ . '/../../helpers/sales-helper.php';

$pageTitle  = 'Dashboard';
$activePage = 'dashboard';

// Sync rental statuses on dashboard load
syncRentalStatuses();

// ── Rental tracker counts ──────────────────────────────────
$rentalCounts = $pdo->query("
    SELECT
        SUM(status = 'Active')    AS active,
        SUM(status = 'Due Today') AS due_today,
        SUM(status = 'Overdue')   AS overdue
    FROM rentals
    WHERE status IN ('Active','Due Today','Overdue')
")->fetch();

// ── Active reservations ────────────────────────────────────
$reservationCount = $pdo->query("
    SELECT COUNT(*) FROM reservations
    WHERE status IN ('Pending','Confirmed')
")->fetchColumn();

// ── Pending + in progress tailoring orders ─────────────────
$orderCount = $pdo->query("
    SELECT COUNT(*) FROM tailoring_orders
    WHERE status IN ('Pending','In Progress','Ready for Pickup')
")->fetchColumn();

// ── Low stock materials ────────────────────────────────────
$lowStock = $pdo->query("
    SELECT mi.*, mc.category_name
    FROM material_items mi
    JOIN material_categories mc ON mi.category_id = mc.category_id
    WHERE mi.quantity <= mi.low_stock_threshold
    ORDER BY mi.quantity ASC
    LIMIT 8
")->fetchAll();

// ── Today's revenue ────────────────────────────────────────
$todayRevenue = $pdo->query("
    SELECT COALESCE(SUM(amount), 0) FROM income_records
    WHERE DATE(date_recorded) = CURDATE()
")->fetchColumn();

// ── This month's revenue ───────────────────────────────────
$monthRevenue = $pdo->query("
    SELECT COALESCE(SUM(amount), 0) FROM income_records
    WHERE MONTH(date_recorded) = MONTH(CURDATE())
      AND YEAR(date_recorded)  = YEAR(CURDATE())
")->fetchColumn();

// ── Overdue rentals detail ─────────────────────────────────
$overdueRentals = $pdo->query("
    SELECT r.*,
           c.full_name AS customer_name, c.mobile_number,
           g.garment_name, g.garment_code,
           DATEDIFF(CURDATE(), r.expected_return_date) AS days_overdue
    FROM rentals r
    JOIN customers     c ON r.customer_id = c.customer_id
    JOIN garment_items g ON r.garment_id  = g.garment_id
    WHERE r.status = 'Overdue'
    ORDER BY days_overdue DESC
    LIMIT 5
")->fetchAll();

// ── Due today rentals ──────────────────────────────────────
$dueTodayRentals = $pdo->query("
    SELECT r.*,
           c.full_name AS customer_name,
           g.garment_name
    FROM rentals r
    JOIN customers     c ON r.customer_id = c.customer_id
    JOIN garment_items g ON r.garment_id  = g.garment_id
    WHERE r.status = 'Due Today'
    ORDER BY r.expected_return_date ASC
    LIMIT 5
")->fetchAll();

// ── Upcoming reservations (next 7 days) ────────────────────
$upcomingReservations = $pdo->query("
    SELECT res.*,
           c.full_name AS customer_name,
           g.garment_name
    FROM reservations res
    JOIN customers     c ON res.customer_id = c.customer_id
    JOIN garment_items g ON res.garment_id  = g.garment_id
    WHERE res.status = 'Confirmed'
      AND res.pickup_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
    ORDER BY res.pickup_date ASC
    LIMIT 5
")->fetchAll();

// ── Orders ready for pickup ────────────────────────────────
$readyOrders = $pdo->query("
    SELECT o.*, c.full_name AS customer_name, c.mobile_number
    FROM tailoring_orders o
    JOIN customers c ON o.customer_id = c.customer_id
    WHERE o.status = 'Ready for Pickup'
    ORDER BY o.due_date ASC
    LIMIT 5
")->fetchAll();

// ── Revenue last 7 days for sparkline ─────────────────────
$revenueChart = [];
for ($i = 6; $i >= 0; $i--) {
    $day = date('Y-m-d', strtotime("-{$i} days"));
    $amt = $pdo->prepare("
        SELECT COALESCE(SUM(amount), 0) FROM income_records
        WHERE DATE(date_recorded) = ?
    ");
    $amt->execute([$day]);
    $revenueChart[] = [
        'label'  => date('D', strtotime($day)),
        'amount' => (float)$amt->fetchColumn(),
    ];
}

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
        Here's what's happening at <?= APP_NAME ?> today.
      </p>
    </div>

    <!-- Summary cards row -->
    <div class="row g-3 mb-4">

      <!-- Active rentals -->
      <div class="col-sm-6 col-lg-3">
        <a href="/tailorshop/modules/rentals/index.php?status=Active"
           class="text-decoration-none">
          <div class="stat-card" style="border-left:3px solid #1D4ED8">
            <div class="stat-icon" style="background:#EFF6FF;color:#1D4ED8">
              <i class="bi bi-handbag"></i>
            </div>
            <div>
              <div class="stat-label">Active Rentals</div>
              <div class="stat-value" style="color:#1D4ED8">
                <?= (int)$rentalCounts['active'] ?>
              </div>
            </div>
          </div>
        </a>
      </div>

      <!-- Due today -->
      <div class="col-sm-6 col-lg-3">
        <a href="/tailorshop/modules/rentals/index.php?status=Due+Today"
           class="text-decoration-none">
          <div class="stat-card" style="border-left:3px solid #D97706">
            <div class="stat-icon" style="background:#FEF3C7;color:#92400E">
              <i class="bi bi-clock-history"></i>
            </div>
            <div>
              <div class="stat-label">Due Today</div>
              <div class="stat-value" style="color:#92400E">
                <?= (int)$rentalCounts['due_today'] ?>
              </div>
            </div>
          </div>
        </a>
      </div>

      <!-- Overdue -->
      <div class="col-sm-6 col-lg-3">
        <a href="/tailorshop/modules/rentals/index.php?status=Overdue"
           class="text-decoration-none">
          <div class="stat-card" style="border-left:3px solid #DC2626">
            <div class="stat-icon" style="background:#FEF2F2;color:#991B1B">
              <i class="bi bi-exclamation-circle"></i>
            </div>
            <div>
              <div class="stat-label">Overdue</div>
              <div class="stat-value" style="color:#991B1B">
                <?= (int)$rentalCounts['overdue'] ?>
              </div>
            </div>
          </div>
        </a>
      </div>

      <!-- Today's revenue -->
      <div class="col-sm-6 col-lg-3">
        <a href="/tailorshop/modules/sales/reports.php?period=daily"
           class="text-decoration-none">
          <div class="stat-card" style="border-left:3px solid #059669">
            <div class="stat-icon" style="background:#F0FDF4;color:#166534">
              <i class="bi bi-cash-stack"></i>
            </div>
            <div>
              <div class="stat-label">Today's Revenue</div>
              <div class="stat-value" style="font-size:18px;color:#166534">
                ₱<?= number_format($todayRevenue, 2) ?>
              </div>
            </div>
          </div>
        </a>
      </div>

    </div>

    <!-- Second row: reservations, orders, month revenue -->
    <div class="row g-3 mb-4">

      <div class="col-sm-4">
        <a href="/tailorshop/modules/reservations/index.php?status=Confirmed"
           class="text-decoration-none">
          <div class="stat-card" style="border-left:3px solid #7C3AED">
            <div class="stat-icon" style="background:#EDE9FE;color:#6D28D9">
              <i class="bi bi-calendar-check"></i>
            </div>
            <div>
              <div class="stat-label">Active Reservations</div>
              <div class="stat-value" style="color:#6D28D9">
                <?= (int)$reservationCount ?>
              </div>
            </div>
          </div>
        </a>
      </div>

      <div class="col-sm-4">
        <a href="/tailorshop/modules/tailoring/index.php"
           class="text-decoration-none">
          <div class="stat-card" style="border-left:3px solid #C2410C">
            <div class="stat-icon" style="background:#FFF7ED;color:#C2410C">
              <i class="bi bi-scissors"></i>
            </div>
            <div>
              <div class="stat-label">Active Orders</div>
              <div class="stat-value" style="color:#C2410C">
                <?= (int)$orderCount ?>
              </div>
            </div>
          </div>
        </a>
      </div>

      <div class="col-sm-4">
        <a href="/tailorshop/modules/sales/reports.php?period=monthly"
           class="text-decoration-none">
          <div class="stat-card" style="border-left:3px solid #0891B2">
            <div class="stat-icon" style="background:#ECFEFF;color:#0E7490">
              <i class="bi bi-graph-up"></i>
            </div>
            <div>
              <div class="stat-label">This Month</div>
              <div class="stat-value" style="font-size:18px;color:#0E7490">
                ₱<?= number_format($monthRevenue, 2) ?>
              </div>
            </div>
          </div>
        </a>
      </div>

    </div>

    <!-- Main content grid -->
    <div class="row g-3">

      <!-- Left column -->
      <div class="col-lg-8">

        <!-- Revenue last 7 days -->
        <div class="card mb-3">
          <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="bi bi-bar-chart-line me-2 text-primary"></i>Revenue — Last 7 Days</span>
            <a href="/tailorshop/modules/sales/reports.php"
               class="btn btn-sm btn-outline-secondary">Full Report</a>
          </div>
          <div class="card-body p-3">
            <canvas id="revenue-7d" height="100"></canvas>
          </div>
        </div>

        <!-- Overdue rentals -->
        <?php if (!empty($overdueRentals)): ?>
          <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
              <span>
                <i class="bi bi-exclamation-circle me-2 text-danger"></i>
                Overdue Rentals
                <span class="badge bg-danger ms-1"><?= count($overdueRentals) ?></span>
              </span>
              <a href="/tailorshop/modules/rentals/index.php?status=Overdue"
                 class="btn btn-sm btn-outline-danger">View All</a>
            </div>
            <div class="table-responsive">
              <table class="table mb-0">
                <thead>
                  <tr>
                    <th>Customer</th>
                    <th>Garment</th>
                    <th>Days Overdue</th>
                    <th>Mobile</th>
                    <th></th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($overdueRentals as $r): ?>
                    <tr class="table-danger">
                      <td class="fw-medium"><?= htmlspecialchars($r['customer_name']) ?></td>
                      <td>
                        <div><?= htmlspecialchars($r['garment_name']) ?></div>
                        <div class="text-muted" style="font-size:11px;font-family:monospace">
                          <?= htmlspecialchars($r['garment_code']) ?>
                        </div>
                      </td>
                      <td>
                        <span class="badge bg-danger rounded-pill">
                          <?= $r['days_overdue'] ?> day<?= $r['days_overdue'] > 1 ? 's' : '' ?>
                        </span>
                      </td>
                      <td class="text-muted" style="font-size:12.5px">
                        <?= htmlspecialchars($r['mobile_number']) ?>
                      </td>
                      <td>
                        <a href="/tailorshop/modules/rentals/view.php?id=<?= $r['rental_id'] ?>"
                           class="btn btn-sm btn-outline-secondary">View</a>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        <?php endif; ?>

        <!-- Due today -->
        <?php if (!empty($dueTodayRentals)): ?>
          <div class="card mb-3">
            <div class="card-header">
              <i class="bi bi-clock me-2 text-warning"></i>
              Due for Return Today
              <span class="badge bg-warning text-dark ms-1"><?= count($dueTodayRentals) ?></span>
            </div>
            <div class="table-responsive">
              <table class="table mb-0">
                <thead>
                  <tr><th>Customer</th><th>Garment</th><th></th></tr>
                </thead>
                <tbody>
                  <?php foreach ($dueTodayRentals as $r): ?>
                    <tr class="table-warning">
                      <td class="fw-medium"><?= htmlspecialchars($r['customer_name']) ?></td>
                      <td><?= htmlspecialchars($r['garment_name']) ?></td>
                      <td>
                        <a href="/tailorshop/modules/rentals/view.php?id=<?= $r['rental_id'] ?>"
                           class="btn btn-sm btn-outline-secondary">View</a>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        <?php endif; ?>

        <!-- Upcoming pickups -->
        <?php if (!empty($upcomingReservations)): ?>
          <div class="card">
            <div class="card-header">
              <i class="bi bi-calendar-event me-2 text-primary"></i>
              Upcoming Pickups (Next 7 Days)
              <span class="badge bg-primary ms-1"><?= count($upcomingReservations) ?></span>
            </div>
            <div class="table-responsive">
              <table class="table mb-0">
                <thead>
                  <tr><th>Customer</th><th>Garment</th><th>Pickup Date</th><th></th></tr>
                </thead>
                <tbody>
                  <?php foreach ($upcomingReservations as $res): ?>
                    <tr>
                      <td class="fw-medium"><?= htmlspecialchars($res['customer_name']) ?></td>
                      <td><?= htmlspecialchars($res['garment_name']) ?></td>
                      <td><?= date('M j, Y', strtotime($res['pickup_date'])) ?></td>
                      <td>
                        <a href="/tailorshop/modules/reservations/view.php?id=<?= $res['reservation_id'] ?>"
                           class="btn btn-sm btn-outline-secondary">View</a>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        <?php endif; ?>

      </div>

      <!-- Right column -->
      <div class="col-lg-4">

        <!-- Low stock alert -->
        <?php if (!empty($lowStock)): ?>
          <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
              <span>
                <i class="bi bi-exclamation-triangle me-2 text-warning"></i>
                Low Stock
                <span class="badge bg-warning text-dark ms-1"><?= count($lowStock) ?></span>
              </span>
              <a href="/tailorshop/modules/inventory/materials/index.php"
                 class="btn btn-sm btn-outline-secondary btn-sm">View All</a>
            </div>
            <div class="card-body p-0">
              <?php foreach ($lowStock as $item): ?>
                <div class="d-flex justify-content-between align-items-center
                            px-3 py-2 border-bottom">
                  <div>
                    <div class="fw-medium" style="font-size:13px">
                      <?= htmlspecialchars($item['item_name']) ?>
                    </div>
                    <div class="text-muted" style="font-size:11px">
                      <?= htmlspecialchars($item['category_name']) ?>
                    </div>
                  </div>
                  <div class="text-end">
                    <div class="<?= $item['quantity'] <= 0 ? 'text-danger' : 'text-warning' ?> fw-bold"
                         style="font-size:13px">
                      <?= rtrim(rtrim(number_format($item['quantity'], 2), '0'), '.') ?>
                      <?= htmlspecialchars($item['unit']) ?>
                    </div>
                    <div class="text-muted" style="font-size:10px">
                      threshold: <?= rtrim(rtrim(number_format($item['low_stock_threshold'], 2), '0'), '.') ?>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>

        <!-- Orders ready for pickup -->
        <?php if (!empty($readyOrders)): ?>
          <div class="card">
            <div class="card-header">
              <i class="bi bi-bag-check me-2 text-success"></i>
              Ready for Pickup
              <span class="badge bg-success ms-1"><?= count($readyOrders) ?></span>
            </div>
            <div class="card-body p-0">
              <?php foreach ($readyOrders as $o): ?>
                <div class="d-flex justify-content-between align-items-center
                            px-3 py-2 border-bottom">
                  <div>
                    <div class="fw-medium" style="font-size:13px">
                      <?= htmlspecialchars($o['order_name']) ?>
                    </div>
                    <div class="text-muted" style="font-size:11px">
                      <?= htmlspecialchars($o['customer_name']) ?>
                      &nbsp;·&nbsp;<?= htmlspecialchars($o['mobile_number']) ?>
                    </div>
                  </div>
                  <a href="/tailorshop/modules/tailoring/view.php?id=<?= $o['order_id'] ?>"
                     class="btn btn-sm btn-outline-success ms-2">View</a>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>

        <!-- Quick links -->
        <div class="card mt-3">
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
              <a href="/tailorshop/api/run-scheduler.php"
                 class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-chat-dots me-1"></i> Run SMS Scheduler
              </a>
            </div>
          </div>
        </div>

      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>
<script>
const ctx7d = document.getElementById('revenue-7d');
if (ctx7d) {
    new Chart(ctx7d, {
        type: 'bar',
        data: {
            labels: <?= json_encode(array_column($revenueChart, 'label')) ?>,
            datasets: [{
                label: 'Revenue (₱)',
                data:  <?= json_encode(array_column($revenueChart, 'amount')) ?>,
                backgroundColor: 'rgba(124,58,237,0.15)',
                borderColor: 'rgba(124,58,237,0.8)',
                borderWidth: 2,
                borderRadius: 6,
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: ctx => '₱' + ctx.parsed.y.toLocaleString('en-PH', {
                            minimumFractionDigits: 2
                        })
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { callback: v => '₱' + v.toLocaleString('en-PH') }
                }
            }
        }
    });
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>