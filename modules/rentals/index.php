<?php
// ============================================================
// modules/rentals/index.php
// List all rentals with status filter — dedicated tracker view
// ============================================================

require_once __DIR__ . '/../../../includes/auth-guard.php';
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../helpers/rental-helper.php';

$pageTitle  = 'Rentals';
$activePage = 'rentals';
$role       = $_SESSION['user_role'];

// Sync statuses on every load
syncRentalStatuses();

$statusFilter = trim($_GET['status'] ?? '');
$search       = trim($_GET['q']      ?? '');

$where  = [];
$params = [];

if ($statusFilter !== '') {
    $where[]  = 'r.status = ?';
    $params[] = $statusFilter;
}
if ($search !== '') {
    $where[]  = '(c.full_name LIKE ? OR g.garment_name LIKE ? OR g.garment_code LIKE ? OR c.mobile_number LIKE ?)';
    $like     = '%' . $search . '%';
    $params   = array_merge($params, [$like, $like, $like, $like]);
}

$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = $pdo->prepare("
    SELECT r.*,
           c.full_name    AS customer_name,
           c.mobile_number,
           c.customer_id,
           g.garment_name, g.garment_code,
           g.garment_id,
           u.full_name    AS processed_by_name,
           DATEDIFF(CURDATE(), r.expected_return_date) AS days_overdue
    FROM rentals r
    JOIN customers     c ON r.customer_id = c.customer_id
    JOIN garment_items g ON r.garment_id  = g.garment_id
    JOIN users         u ON r.processed_by = u.user_id
    $whereSQL
    ORDER BY
        FIELD(r.status,'Overdue','Due Today','Active','Returned','Cancelled'),
        r.expected_return_date ASC
");
$stmt->execute($params);
$rentals = $stmt->fetchAll();

// Summary counts for tracker cards
$counts = $pdo->query("
    SELECT
        SUM(status = 'Active')    AS active,
        SUM(status = 'Due Today') AS due_today,
        SUM(status = 'Overdue')   AS overdue,
        SUM(status = 'Returned')  AS returned
    FROM rentals
")->fetch();

$statuses = ['Active','Due Today','Overdue','Returned','Cancelled'];

$success = $_SESSION['success'] ?? '';
$error   = $_SESSION['error']   ?? '';
unset($_SESSION['success'], $_SESSION['error']);

require_once __DIR__ . '/../../../includes/header.php';
require_once __DIR__ . '/../../../includes/sidebar.php';
?>

<div class="main-content">
  <div class="topbar">
    <span class="topbar-title">Rental Tracker</span>
    <span class="text-muted small" id="current-date"></span>
  </div>

  <div class="page-body">

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

    <!-- Page header -->
    <div class="page-header">
      <div>
        <h5>Rental Tracker</h5>
        <p class="text-muted small mb-0">
          <?= count($rentals) ?> record<?= count($rentals) !== 1 ? 's' : '' ?> found
        </p>
      </div>
      <a href="/tailorshop/modules/rentals/create.php" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i> New Rental
      </a>
    </div>

    <!-- Tracker summary cards -->
    <div class="row g-3 mb-4">
      <?php
      $cards = [
          ['label'=>'Active Rentals', 'count'=>$counts['active'],    'color'=>'#1D4ED8', 'bg'=>'#EFF6FF', 'icon'=>'bi-handbag',               'filter'=>'Active'],
          ['label'=>'Due Today',      'count'=>$counts['due_today'], 'color'=>'#92400E', 'bg'=>'#FEF3C7', 'icon'=>'bi-clock',                 'filter'=>'Due Today'],
          ['label'=>'Overdue',        'count'=>$counts['overdue'],   'color'=>'#991B1B', 'bg'=>'#FEF2F2', 'icon'=>'bi-exclamation-circle',    'filter'=>'Overdue'],
          ['label'=>'Returned',       'count'=>$counts['returned'],  'color'=>'#166534', 'bg'=>'#F0FDF4', 'icon'=>'bi-check-circle',          'filter'=>'Returned'],
      ];
      foreach ($cards as $card):
      ?>
        <div class="col-sm-6 col-lg-3">
          <a href="?status=<?= urlencode($card['filter']) ?>"
             class="text-decoration-none">
            <div class="stat-card" style="border-left:3px solid <?= $card['color'] ?>">
              <div class="stat-icon" style="background:<?= $card['bg'] ?>;color:<?= $card['color'] ?>">
                <i class="bi <?= $card['icon'] ?>"></i>
              </div>
              <div>
                <div class="stat-label"><?= $card['label'] ?></div>
                <div class="stat-value" style="color:<?= $card['color'] ?>">
                  <?= (int)$card['count'] ?>
                </div>
              </div>
            </div>
          </a>
        </div>
      <?php endforeach; ?>
    </div>

    <!-- Search + filter -->
    <div class="card mb-3">
      <div class="card-body py-2 px-3">
        <form method="GET" class="d-flex gap-2 align-items-center flex-wrap">
          <i class="bi bi-search text-muted"></i>
          <input type="text" name="q"
                 class="form-control border-0 shadow-none ps-0"
                 placeholder="Search by customer, garment, or mobile…"
                 value="<?= htmlspecialchars($search) ?>"
                 style="font-size:13.5px;min-width:180px;flex:1">
          <select name="status" class="form-select form-select-sm"
                  style="width:auto" onchange="this.form.submit()">
            <option value="">All Statuses</option>
            <?php foreach ($statuses as $s): ?>
              <option value="<?= $s ?>"
                <?= $statusFilter === $s ? 'selected' : '' ?>>
                <?= $s ?>
              </option>
            <?php endforeach; ?>
          </select>
          <button class="btn btn-sm btn-primary">Search</button>
          <?php if ($search || $statusFilter): ?>
            <a href="/tailorshop/modules/rentals/index.php"
               class="btn btn-sm btn-outline-secondary">Clear</a>
          <?php endif; ?>
        </form>
      </div>
    </div>

    <!-- Rentals table -->
    <div class="card">
      <div class="table-responsive">
        <table class="table mb-0">
          <thead>
            <tr>
              <th>#</th>
              <th>Customer</th>
              <th>Garment</th>
              <th>Rental Date</th>
              <th>Return Date</th>
              <th>Fee</th>
              <th>Deposit</th>
              <th>Status</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($rentals)): ?>
              <tr>
                <td colspan="9" class="text-center text-muted py-5">
                  <i class="bi bi-handbag fs-3 d-block mb-2"></i>
                  <?= $search || $statusFilter
                      ? 'No rentals matched your search.'
                      : 'No rental transactions yet.' ?>
                  <br>
                  <a href="/tailorshop/modules/rentals/create.php"
                     class="btn btn-sm btn-primary mt-2">Create First Rental</a>
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($rentals as $r): ?>
                <tr class="<?= $r['status'] === 'Overdue' ? 'table-danger' :
                              ($r['status'] === 'Due Today' ? 'table-warning' : '') ?>">
                  <td>
                    <span class="fw-medium text-primary">
                      #<?= str_pad($r['rental_id'], 4, '0', STR_PAD_LEFT) ?>
                    </span>
                  </td>
                  <td>
                    <div class="fw-medium"><?= htmlspecialchars($r['customer_name']) ?></div>
                    <div class="text-muted" style="font-size:11.5px">
                      <?= htmlspecialchars($r['mobile_number']) ?>
                    </div>
                  </td>
                  <td>
                    <div><?= htmlspecialchars($r['garment_name']) ?></div>
                    <div class="text-muted" style="font-size:11px;font-family:monospace">
                      <?= htmlspecialchars($r['garment_code']) ?>
                    </div>
                  </td>
                  <td><?= date('M j, Y', strtotime($r['rental_date'])) ?></td>
                  <td>
                    <?= date('M j, Y', strtotime($r['expected_return_date'])) ?>
                    <?php if ($r['status'] === 'Overdue' && $r['days_overdue'] > 0): ?>
                      <div class="text-danger" style="font-size:11px;font-weight:600">
                        <?= $r['days_overdue'] ?> day<?= $r['days_overdue'] > 1 ? 's' : '' ?> overdue
                      </div>
                    <?php endif; ?>
                  </td>
                  <td>₱<?= number_format($r['rental_fee'], 2) ?></td>
                  <td>₱<?= number_format($r['deposit_amount'], 2) ?></td>
                  <td><?= rentalStatusBadge($r['status']) ?></td>
                  <td>
                    <a href="/tailorshop/modules/rentals/view.php?id=<?= $r['rental_id'] ?>"
                       class="btn btn-sm btn-outline-secondary">
                      <i class="bi bi-eye"></i> View
                    </a>
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

<?php require_once __DIR__ . '/../../../includes/footer.php'; ?>