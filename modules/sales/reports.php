<?php
// ============================================================
// modules/sales/reports.php
// Income reports — daily, weekly, monthly with chart data
// Admin only
// ============================================================

require_once __DIR__ . '/../../../includes/auth-guard.php';
require_once __DIR__ . '/../../../includes/role-guard.php';
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../helpers/sales-helper.php';

$pageTitle  = 'Income Report';
$activePage = 'reports';

$period    = trim($_GET['period']     ?? 'monthly');
$dateFrom  = trim($_GET['date_from']  ?? '');
$dateTo    = trim($_GET['date_to']    ?? '');

$validPeriods = ['daily','weekly','monthly'];
if (!in_array($period, $validPeriods)) $period = 'monthly';

// Get report data
$report = getIncomeReport(
    $period,
    $dateFrom ?: null,
    $dateTo   ?: null
);

// Build daily breakdown for chart (last 30 days for monthly, 7 for weekly, 1 for daily)
$chartDays = $period === 'daily' ? 1 : ($period === 'weekly' ? 7 : 30);
$chartData = [];
for ($i = $chartDays - 1; $i >= 0; $i--) {
    $day = date('Y-m-d', strtotime("-{$i} days"));
    $chartData[$day] = 0;
}

foreach ($report['records'] as $rec) {
    $day = date('Y-m-d', strtotime($rec['date_recorded']));
    if (isset($chartData[$day])) {
        $chartData[$day] += $rec['amount'];
    }
}

require_once __DIR__ . '/../../../includes/header.php';
require_once __DIR__ . '/../../../includes/sidebar.php';
?>

<div class="main-content">
  <div class="topbar">
    <span class="topbar-title">Income Report</span>
    <span class="text-muted small" id="current-date"></span>
  </div>

  <div class="page-body">

    <!-- Page header -->
    <div class="page-header">
      <div>
        <h5>Income Report</h5>
        <p class="text-muted small mb-0">
          <?= date('F j, Y', strtotime($report['from'])) ?>
          – <?= date('F j, Y', strtotime($report['to'])) ?>
        </p>
      </div>
      <a href="/tailorshop/modules/sales/export.php?period=<?= $period ?>&date_from=<?= $dateFrom ?>&date_to=<?= $dateTo ?>"
         class="btn btn-outline-success">
        <i class="bi bi-download me-1"></i> Export
      </a>
    </div>

    <!-- Period selector -->
    <div class="card mb-4">
      <div class="card-body py-2 px-3">
        <form method="GET" class="d-flex gap-2 align-items-center flex-wrap">

          <div class="btn-group btn-group-sm me-2">
            <?php foreach (['daily'=>'Today','weekly'=>'This Week','monthly'=>'This Month'] as $p => $label): ?>
              <a href="?period=<?= $p ?>"
                 class="btn <?= $period === $p ? 'btn-primary' : 'btn-outline-secondary' ?>">
                <?= $label ?>
              </a>
            <?php endforeach; ?>
          </div>

          <span class="text-muted small">or custom range:</span>

          <input type="hidden" name="period" value="<?= $period ?>">
          <div class="d-flex align-items-center gap-1" style="font-size:12.5px">
            <input type="date" name="date_from" class="form-control form-control-sm"
                   value="<?= htmlspecialchars($dateFrom) ?>" style="width:auto">
            <span class="text-muted">to</span>
            <input type="date" name="date_to" class="form-control form-control-sm"
                   value="<?= htmlspecialchars($dateTo) ?>" style="width:auto">
          </div>
          <button class="btn btn-sm btn-primary">Apply</button>

        </form>
      </div>
    </div>

    <!-- Summary cards -->
    <div class="row g-3 mb-4">
      <div class="col-sm-6 col-lg-3">
        <div class="stat-card" style="border-left:3px solid #10B981">
          <div class="stat-icon" style="background:#F0FDF4;color:#166534">
            <i class="bi bi-cash-stack"></i>
          </div>
          <div>
            <div class="stat-label">Total Revenue</div>
            <div class="stat-value" style="font-size:20px;color:#166534">
              ₱<?= number_format($report['total'], 2) ?>
            </div>
          </div>
        </div>
      </div>
      <div class="col-sm-6 col-lg-3">
        <div class="stat-card" style="border-left:3px solid #92400E">
          <div class="stat-icon" style="background:#FEF3C7;color:#92400E">
            <i class="bi bi-bag-heart"></i>
          </div>
          <div>
            <div class="stat-label">Garment Sales</div>
            <div class="stat-value" style="font-size:20px;color:#92400E">
              ₱<?= number_format(
                    array_sum(array_map(
                        fn($r) => $r['income_type'] === 'Garment Sale' ? $r['amount'] : 0,
                        $report['records']
                    )), 2) ?>
            </div>
          </div>
        </div>
      </div>
      <div class="col-sm-6 col-lg-3">
        <div class="stat-card" style="border-left:3px solid #1D4ED8">
          <div class="stat-icon" style="background:#EFF6FF;color:#1D4ED8">
            <i class="bi bi-handbag"></i>
          </div>
          <div>
            <div class="stat-label">Rental Fees</div>
            <div class="stat-value" style="font-size:20px;color:#1D4ED8">
              ₱<?= number_format(
                    array_sum(array_map(
                        fn($r) => $r['income_type'] === 'Rental Fee' ? $r['amount'] : 0,
                        $report['records']
                    )), 2) ?>
            </div>
          </div>
        </div>
      </div>
      <div class="col-sm-6 col-lg-3">
        <div class="stat-card" style="border-left:3px solid #5B21B6">
          <div class="stat-icon" style="background:#EDE9FE;color:#5B21B6">
            <i class="bi bi-scissors"></i>
          </div>
          <div>
            <div class="stat-label">Tailoring</div>
            <div class="stat-value" style="font-size:20px;color:#5B21B6">
              ₱<?= number_format(
                    array_sum(array_map(
                        fn($r) => $r['income_type'] === 'Tailoring Payment' ? $r['amount'] : 0,
                        $report['records']
                    )), 2) ?>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="row g-3 mb-4">

      <!-- Revenue chart -->
      <div class="col-lg-8">
        <div class="card h-100">
          <div class="card-header">
            <i class="bi bi-bar-chart-line me-2 text-primary"></i>
            Revenue Trend
          </div>
          <div class="card-body p-3">
            <canvas id="revenue-chart" height="<?= $period === 'daily' ? '80' : '140' ?>"></canvas>
          </div>
        </div>
      </div>

      <!-- By type breakdown -->
      <div class="col-lg-4">
        <div class="card h-100">
          <div class="card-header">
            <i class="bi bi-pie-chart me-2 text-warning"></i>
            By Income Type
          </div>
          <div class="card-body p-3">
            <?php if (empty($report['by_type'])): ?>
              <p class="text-muted small text-center mt-4">No data for this period.</p>
            <?php else: ?>
              <canvas id="type-chart"></canvas>
            <?php endif; ?>
          </div>
        </div>
      </div>

    </div>

    <div class="row g-3 mb-4">

      <!-- By income type table -->
      <div class="col-lg-6">
        <div class="card">
          <div class="card-header">
            <i class="bi bi-list-ul me-2 text-primary"></i>Breakdown by Type
          </div>
          <div class="table-responsive">
            <table class="table mb-0">
              <thead>
                <tr><th>Income Type</th><th>Transactions</th><th class="text-end">Amount</th></tr>
              </thead>
              <tbody>
                <?php if (empty($report['by_type'])): ?>
                  <tr>
                    <td colspan="3" class="text-center text-muted py-3">
                      No data for this period.
                    </td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($report['by_type'] as $t): ?>
                    <tr>
                      <td class="fw-medium"><?= htmlspecialchars($t['income_type']) ?></td>
                      <td class="text-muted"><?= $t['count'] ?></td>
                      <td class="text-end fw-semibold text-success">
                        ₱<?= number_format($t['subtotal'], 2) ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
              <?php if (!empty($report['by_type'])): ?>
                <tfoot>
                  <tr style="background:#f9fafb">
                    <td class="fw-bold">Total</td>
                    <td class="text-muted">
                      <?= array_sum(array_column($report['by_type'], 'count')) ?>
                    </td>
                    <td class="text-end fw-bold text-success">
                      ₱<?= number_format($report['total'], 2) ?>
                    </td>
                  </tr>
                </tfoot>
              <?php endif; ?>
            </table>
          </div>
        </div>
      </div>

      <!-- By payment method table -->
      <div class="col-lg-6">
        <div class="card">
          <div class="card-header">
            <i class="bi bi-credit-card me-2 text-success"></i>Breakdown by Payment Method
          </div>
          <div class="table-responsive">
            <table class="table mb-0">
              <thead>
                <tr><th>Method</th><th>Transactions</th><th class="text-end">Amount</th></tr>
              </thead>
              <tbody>
                <?php if (empty($report['by_method'])): ?>
                  <tr>
                    <td colspan="3" class="text-center text-muted py-3">
                      No data for this period.
                    </td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($report['by_method'] as $m): ?>
                    <tr>
                      <td class="fw-medium"><?= htmlspecialchars($m['payment_method']) ?></td>
                      <td class="text-muted"><?= $m['count'] ?></td>
                      <td class="text-end fw-semibold text-success">
                        ₱<?= number_format($m['subtotal'], 2) ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

    </div>

    <!-- Recent transactions -->
    <div class="card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span>
          <i class="bi bi-clock-history me-2 text-muted"></i>
          Recent Transactions
        </span>
        <a href="/tailorshop/modules/sales/index.php" class="btn btn-sm btn-outline-secondary">
          View All Records
        </a>
      </div>
      <div class="table-responsive">
        <table class="table mb-0">
          <thead>
            <tr>
              <th>Date</th>
              <th>Customer</th>
              <th>Type</th>
              <th>Method</th>
              <th class="text-end">Amount</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($report['records'])): ?>
              <tr>
                <td colspan="5" class="text-center text-muted py-4">
                  No transactions in this period.
                </td>
              </tr>
            <?php else: ?>
              <?php foreach (array_slice($report['records'], 0, 10) as $rec): ?>
                <tr>
                  <td style="font-size:12.5px">
                    <?= date('M j, Y g:i A', strtotime($rec['date_recorded'])) ?>
                  </td>
                  <td>
                    <a href="/tailorshop/modules/customers/view.php?id=<?= $rec['customer_id'] ?? '' ?>"
                       class="text-decoration-none">
                      <?= htmlspecialchars($rec['customer_name']) ?>
                    </a>
                  </td>
                  <td>
                    <span style="font-size:11.5px;font-weight:500">
                      <?= htmlspecialchars($rec['income_type']) ?>
                    </span>
                  </td>
                  <td>
                    <span class="badge bg-secondary bg-opacity-10 text-secondary rounded-pill"
                          style="font-size:11px">
                      <?= htmlspecialchars($rec['payment_method']) ?>
                    </span>
                  </td>
                  <td class="text-end fw-semibold text-success">
                    ₱<?= number_format($rec['amount'], 2) ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>
<script>
// Revenue trend chart
const chartLabels = <?= json_encode(array_map(
    fn($d) => date('M j', strtotime($d)),
    array_keys($chartData)
)) ?>;
const chartValues = <?= json_encode(array_values($chartData)) ?>;

const revenueCtx = document.getElementById('revenue-chart');
if (revenueCtx) {
    new Chart(revenueCtx, {
        type: 'bar',
        data: {
            labels: chartLabels,
            datasets: [{
                label: 'Revenue (₱)',
                data: chartValues,
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
                    ticks: {
                        callback: v => '₱' + v.toLocaleString('en-PH')
                    }
                }
            }
        }
    });
}

// Income type donut chart
<?php if (!empty($report['by_type'])): ?>
const typeCtx = document.getElementById('type-chart');
if (typeCtx) {
    new Chart(typeCtx, {
        type: 'doughnut',
        data: {
            labels: <?= json_encode(array_column($report['by_type'], 'income_type')) ?>,
            datasets: [{
                data: <?= json_encode(array_column($report['by_type'], 'subtotal')) ?>,
                backgroundColor: ['#FEF3C7','#EFF6FF','#EDE9FE'],
                borderColor:     ['#D97706','#1D4ED8','#5B21B6'],
                borderWidth: 2,
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { position: 'bottom' },
                tooltip: {
                    callbacks: {
                        label: ctx => ctx.label + ': ₱' +
                               ctx.parsed.toLocaleString('en-PH', {
                                   minimumFractionDigits: 2
                               })
                    }
                }
            }
        }
    });
}
<?php endif; ?>
</script>

<?php require_once __DIR__ . '/../../../includes/footer.php'; ?>