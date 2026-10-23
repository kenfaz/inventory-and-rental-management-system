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
// ── Income interpretation ───────────────────────────────────
$previousMonthRevenue = $pdo->query("
    SELECT COALESCE(SUM(amount), 0) FROM income_records
    WHERE date_recorded >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 1 MONTH), '%Y-%m-01')
      AND date_recorded < DATE_FORMAT(CURDATE(), '%Y-%m-01')
")->fetchColumn();

$topIncomeType = $pdo->query("
    SELECT income_type, COALESCE(SUM(amount), 0) AS total
    FROM income_records
    WHERE MONTH(date_recorded) = MONTH(CURDATE())
      AND YEAR(date_recorded) = YEAR(CURDATE())
    GROUP BY income_type
    ORDER BY total DESC
    LIMIT 1
")->fetch();

if ((float)$previousMonthRevenue > 0) {
    $incomeChangePercent = ((float)$monthRevenue - (float)$previousMonthRevenue)
        / (float)$previousMonthRevenue * 100;
} else {
    $incomeChangePercent = (float)$monthRevenue > 0 ? 100.0 : 0.0;
}

if ((float)$monthRevenue <= 0) {
    $incomeInsight = 'No income has been recorded for this month yet.';
    $incomeInsightTone = 'text-muted';
} elseif (abs($incomeChangePercent) < 0.05) {
    $incomeInsight = 'Income is steady this month compared with last month.';
    $incomeInsightTone = 'text-info';
} elseif ($incomeChangePercent > 0) {
    $incomeInsight = sprintf(
        'Income increased %.1f%% compared with last month, totaling %s.',
        $incomeChangePercent,
        peso((float)$monthRevenue)
    );
    $incomeInsightTone = 'text-success';
} else {
    $incomeInsight = sprintf(
        'Income decreased %.1f%% compared with last month, totaling %s.',
        abs($incomeChangePercent),
        peso((float)$monthRevenue)
    );
    $incomeInsightTone = 'text-danger';
}

$topIncomeShare = (!empty($topIncomeType) && (float)$monthRevenue > 0)
    ? ((float)$topIncomeType['total'] / (float)$monthRevenue * 100)
    : 0.0;

if (!empty($topIncomeType) && (float)$topIncomeType['total'] > 0) {
    $incomeInsight .= sprintf(
        ' %s is the leading income source at %s.',
        $topIncomeType['income_type'],
        peso((float)$topIncomeType['total'])
    );
}
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

// ── Monthly revenue chart ──────────────────────────────────
$revenueChart = [];
$monthLabels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
$sampleMonthlyRevenue = [42000, 36000, 48000, 57000, 52000, 65000, 61000, 72000, 68000, 81000, 76000, 89000];
$currentYear = (int)date('Y');

foreach ($monthLabels as $index => $label) {
    $month = $index + 1;
    $amt = $pdo->prepare("
        SELECT COALESCE(SUM(amount), 0) FROM income_records
        WHERE YEAR(date_recorded) = ? AND MONTH(date_recorded) = ?
    ");
    $amt->execute([$currentYear, $month]);
    $databaseAmount = (float)$amt->fetchColumn();

    $revenueChart[] = [
        'label'  => $label,
        'amount' => $databaseAmount > 0 ? $databaseAmount : $sampleMonthlyRevenue[$index],
    ];
}

$schedulerSuccess = $_SESSION['success'] ?? '';
$schedulerError = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="main-content">
  <div class="topbar">
    <span class="topbar-title">Dashboard</span>
    <span class="text-muted small" id="current-date"></span>
  </div>

  <div class="page-body">

    <?php if ($schedulerSuccess || $schedulerError): ?>
      <?php $schedulerSucceeded = $schedulerSuccess !== ''; ?>
      <div class="modal fade" id="schedulerResultModal" tabindex="-1"
           aria-labelledby="schedulerResultTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content">
            <div class="modal-header <?= $schedulerSucceeded ? 'border-success' : 'border-danger' ?>">
              <h5 class="modal-title" id="schedulerResultTitle">
                <i class="bi <?= $schedulerSucceeded ? 'bi-check-circle-fill text-success' : 'bi-exclamation-triangle-fill text-danger' ?> me-2"></i>
                <?= $schedulerSucceeded ? 'Scheduler Completed' : 'Scheduler Failed' ?>
              </h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
              <?= htmlspecialchars($schedulerSucceeded ? $schedulerSuccess : $schedulerError) ?>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-primary" data-bs-dismiss="modal">OK</button>
            </div>
          </div>
        </div>
      </div>
    <?php endif; ?>

    <!-- Summary cards row -->
    <div class="row g-3 mb-4">

      <!-- Active rentals -->
      <div class="col-sm-6 col-lg-4">
        <a href="/tailorshop/modules/rentals/index.php?status=Active"
           class="text-decoration-none">
          <div class="stat-card dashboard-stat-card dashboard-rental-active">
            <div class="stat-icon">
              <i class="bi bi-handbag"></i>
            </div>
            <div>
              <div class="stat-label">Active Rentals</div>
              <div class="stat-value">
                <?= (int)$rentalCounts['active'] ?>
              </div>
            </div>
          </div>
        </a>
      </div>

      <!-- Due today -->
      <div class="col-sm-6 col-lg-4">
        <a href="/tailorshop/modules/rentals/index.php?status=Due+Today"
           class="text-decoration-none">
          <div class="stat-card dashboard-stat-card dashboard-rental-due">
            <div class="stat-icon">
              <i class="bi bi-clock-history"></i>
            </div>
            <div>
              <div class="stat-label">Due Today</div>
              <div class="stat-value">
                <?= (int)$rentalCounts['due_today'] ?>
              </div>
            </div>
          </div>
        </a>
      </div>

      <!-- Overdue -->
      <div class="col-sm-6 col-lg-4">
        <a href="/tailorshop/modules/rentals/index.php?status=Overdue"
           class="text-decoration-none">
          <div class="stat-card dashboard-stat-card dashboard-rental-overdue">
            <div class="stat-icon">
              <i class="bi bi-exclamation-circle"></i>
            </div>
            <div>
              <div class="stat-label">Overdue</div>
              <div class="stat-value">
                <?= (int)$rentalCounts['overdue'] ?>
              </div>
            </div>
          </div>
        </a>
      </div>

    </div>

    <!-- Bookings and orders -->
    <div class="card dashboard-section mb-4">
      <div class="card-header">
        <i class="bi bi-calendar3 me-2 text-primary"></i>Bookings &amp; Orders
      </div>
      <div class="card-body dashboard-section-body">
        <div class="row g-0">
          <div class="col-sm-6 dashboard-section-item">
            <a href="/tailorshop/modules/reservations/index.php?status=Confirmed"
               class="text-decoration-none">
              <div class="dashboard-section-label">Active Reservations</div>
              <div class="dashboard-section-value text-primary">
                <?= (int)$reservationCount ?>
              </div>
            </a>
          </div>
          <div class="col-sm-6 dashboard-section-item dashboard-section-item-last">
            <a href="/tailorshop/modules/tailoring/index.php"
               class="text-decoration-none">
              <div class="dashboard-section-label">Active Orders</div>
              <div class="dashboard-section-value" style="color:#C2410C">
                <?= (int)$orderCount ?>
              </div>
            </a>
          </div>
        </div>
      </div>
    </div>

    <div class="card income-insights-card mb-4">
      <div class="income-insights-header">
        <div class="d-flex align-items-center gap-3">
          <div class="income-insights-icon"><i class="bi bi-bar-chart-line"></i></div>
          <div>
            <h5 class="mb-1">Income Insights</h5>
            <div class="income-insights-subtitle">Monthly performance analysis</div>
          </div>
        </div>
        <div class="income-change-badge <?= $incomeChangePercent >= 0 ? 'positive' : 'negative' ?>">
          <?= $incomeChangePercent >= 0 ? '▲' : '▼' ?> <?= number_format(abs($incomeChangePercent), 0) ?>%
        </div>
      </div>

      <div class="income-insights-body">
        <div class="income-total-label">Total income this month</div>
        <div class="income-total-value">₱<?= number_format((float)$monthRevenue, 2) ?></div>
        <div class="income-comparison <?= $incomeChangePercent >= 0 ? 'text-success' : 'text-danger' ?>">
          <?= $incomeChangePercent >= 0 ? '▲' : '▼' ?> <?= number_format(abs($incomeChangePercent), 0) ?>% compared with last month
        </div>

        <?php $source = $topIncomeType ?: ['income_type' => 'No income recorded', 'total' => 0]; ?>
        <div class="income-source-block">
          <div class="income-source-main">
            <div class="income-source-icon"><i class="bi bi-trophy"></i></div>
            <div>
              <div class="income-source-label">TOP INCOME SOURCE</div>
              <div class="income-source-name"><?= htmlspecialchars($source['income_type']) ?></div>
            </div>
          </div>
          <div class="income-source-amount">
            ₱<?= number_format((float)$source['total'], 0) ?>
            <div><?= number_format($topIncomeShare, 1) ?>% of total</div>
          </div>
        </div>
        <div class="income-source-progress">
          <div style="width:<?= min(100, max(0, $topIncomeShare)) ?>%"></div>
        </div>

        <div class="income-insights-summary">
          <i class="bi bi-info-circle"></i>
          <span><?= htmlspecialchars($incomeInsight) ?></span>
        </div>
      </div>
    </div>

    <!-- Main content grid -->
    <div class="row g-3">

      <!-- Left column -->
      <div class="col-lg-8">

        <!-- Monthly revenue -->
        <div class="card mb-3">
          <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="bi bi-graph-up me-2 text-primary"></i>Revenue</span>
            <a href="/tailorshop/modules/sales/reports.php"
               class="btn btn-sm btn-outline-secondary">Full Report</a>
          </div>
          <div class="card-body p-3" style="background:#123D2D;border-radius:0 0 12px 12px;">
            <canvas id="revenue-7d" height="140"></canvas>
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
                <i class="bi bi-bell me-1"></i> Run Notification Scheduler
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
    const chartGradient = ctx7d.getContext('2d').createLinearGradient(0, 0, 0, ctx7d.height);
    chartGradient.addColorStop(0, 'rgba(226, 232, 240, 0.32)');
    chartGradient.addColorStop(1, 'rgba(226, 232, 240, 0.02)');

    new Chart(ctx7d, {
        type: 'line',
        data: {
            labels: <?= json_encode(array_column($revenueChart, 'label')) ?>,
            datasets: [{
                label: 'Revenue',
                data: <?= json_encode(array_column($revenueChart, 'amount')) ?>,
                borderColor: '#D1D5DB',
                backgroundColor: chartGradient,
                borderWidth: 2.5,
                pointRadius: 0,
                pointHoverRadius: 4,
                pointBackgroundColor: '#D1D5DB',
                fill: true,
                tension: 0.38,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { intersect: false, mode: 'index' },
            plugins: {
                legend: {
                    display: true,
                    position: 'top',
                    align: 'start',
                    labels: { color: '#DCEAFE', boxWidth: 18, boxHeight: 2, usePointStyle: false }
                },
                tooltip: {
                    backgroundColor: '#111C3A',
                    borderColor: '#D1D5DB',
                    borderWidth: 1,
                    callbacks: {
                        label: ctx => ' Revenue: ₱' + ctx.parsed.y.toLocaleString('en-PH', {
                            minimumFractionDigits: 2
                        })
                    }
                }
            },
            scales: {
                x: {
                    grid: { display: false },
                    border: { display: false },
                    ticks: { color: '#9FB3D1', font: { size: 11 } }
                },
                y: {
                    beginAtZero: true,
                    grid: { color: 'rgba(148, 163, 184, 0.12)', drawTicks: false },
                    border: { display: false },
                    ticks: {
                        color: '#9FB3D1',
                        padding: 10,
                        callback: v => '₱' + Number(v / 1000).toLocaleString('en-PH') + 'k'
                    }
                }
            }
        }
    });
}

window.addEventListener('DOMContentLoaded', () => {
    const schedulerModal = document.getElementById('schedulerResultModal');
    if (schedulerModal && window.bootstrap) {
        bootstrap.Modal.getOrCreateInstance(schedulerModal).show();
    }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>