<?php
// ============================================================
// modules/tailoring/index.php
// List all tailoring orders with status filter
// ============================================================

require_once __DIR__ . '/../../includes/auth-guard.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../helpers/rental-helper.php';

$pageTitle  = 'Tailoring Orders';
$activePage = 'tailoring';
$role       = $_SESSION['user_role'];

$statusFilter = trim($_GET['status'] ?? '');
$search       = trim($_GET['q']      ?? '');

$where  = [];
$params = [];

if ($statusFilter !== '') {
    $where[]  = 'o.status = ?';
    $params[] = $statusFilter;
}
if ($search !== '') {
    $where[]  = '(c.full_name LIKE ? OR o.order_name LIKE ? OR o.garment_description LIKE ?)';
    $like     = '%' . $search . '%';
    $params   = array_merge($params, [$like, $like, $like]);
}

$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = $pdo->prepare("
    SELECT o.*,
           c.full_name  AS customer_name,
           c.mobile_number,
           c.customer_id,
           u.full_name  AS staff_name,
           DATEDIFF(o.due_date, CURDATE()) AS days_until_due
    FROM tailoring_orders o
    JOIN customers c ON o.customer_id      = c.customer_id
    JOIN users     u ON o.assigned_staff_id = u.user_id
    $whereSQL
    ORDER BY
        FIELD(o.status,'In Progress','Pending','Ready for Pickup','Completed','Cancelled'),
        o.due_date ASC
");
$stmt->execute($params);
$orders = $stmt->fetchAll();

// Status counts
$counts = $pdo->query("
    SELECT
        SUM(status = 'Pending')          AS pending,
        SUM(status = 'In Progress')      AS in_progress,
        SUM(status = 'Ready for Pickup') AS ready,
        SUM(status = 'Completed')        AS completed
    FROM tailoring_orders
")->fetch();

$statuses = ['Pending','In Progress','Ready for Pickup','Completed','Cancelled'];

$success = $_SESSION['success'] ?? '';
$error   = $_SESSION['error']   ?? '';
unset($_SESSION['success'], $_SESSION['error']);

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="main-content">
  <div class="topbar">
    <span class="topbar-title">Tailoring Orders</span>
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
        <h5>Tailoring Orders</h5>
        <p class="text-muted small mb-0">
          <?= count($orders) ?> order<?= count($orders) !== 1 ? 's' : '' ?> found
        </p>
      </div>
      <a href="/tailorshop/modules/tailoring/create.php" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i> New Order
      </a>
    </div>

    <!-- Summary cards -->
    <div class="row g-3 mb-4">
      <?php
      $cards = [
          ['label'=>'Pending',        'count'=>$counts['pending'],     'color'=>'#854D0E', 'bg'=>'#FEF9C3', 'icon'=>'bi-hourglass',    'filter'=>'Pending'],
          ['label'=>'In Progress',    'count'=>$counts['in_progress'], 'color'=>'#C2410C', 'bg'=>'#FFF7ED', 'icon'=>'bi-scissors',     'filter'=>'In Progress'],
          ['label'=>'Ready',          'count'=>$counts['ready'],       'color'=>'#15803D', 'bg'=>'#F0FDF4', 'icon'=>'bi-bag-check',    'filter'=>'Ready for Pickup'],
          ['label'=>'Completed',      'count'=>$counts['completed'],   'color'=>'#5B21B6', 'bg'=>'#EDE9FE', 'icon'=>'bi-check-circle', 'filter'=>'Completed'],
      ];
      foreach ($cards as $card):
      ?>
        <div class="col-sm-6 col-lg-3">
          <a href="?status=<?= urlencode($card['filter']) ?>" class="text-decoration-none">
            <div class="stat-card" style="border-left:3px solid <?= $card['color'] ?>">
              <div class="stat-icon"
                   style="background:<?= $card['bg'] ?>;color:<?= $card['color'] ?>">
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
                 placeholder="Search by customer or order name…"
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
            <a href="/tailorshop/modules/tailoring/index.php"
               class="btn btn-sm btn-outline-secondary">Clear</a>
          <?php endif; ?>
        </form>
      </div>
    </div>

    <!-- Orders table -->
    <div class="card">
      <div class="table-responsive">
        <table class="table mb-0">
          <thead>
            <tr>
              <th>#</th>
              <th>Customer</th>
              <th>Order Name</th>
              <th>Staff</th>
              <th>Price</th>
              <th>Balance</th>
              <th>Due Date</th>
              <th>Status</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($orders)): ?>
              <tr>
                <td colspan="9" class="text-center text-muted py-5">
                  <i class="bi bi-scissors fs-3 d-block mb-2"></i>
                  <?= $search || $statusFilter
                      ? 'No orders matched your search.'
                      : 'No tailoring orders yet.' ?>
                  <br>
                  <a href="/tailorshop/modules/tailoring/create.php"
                     class="btn btn-sm btn-primary mt-2">Create First Order</a>
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($orders as $o):
                $dueSoon = $o['days_until_due'] !== null
                           && $o['days_until_due'] <= 3
                           && $o['days_until_due'] >= 0
                           && in_array($o['status'], ['Pending','In Progress']);
                $overdue = $o['days_until_due'] !== null
                           && $o['days_until_due'] < 0
                           && in_array($o['status'], ['Pending','In Progress']);
              ?>
                <tr class="<?= $overdue ? 'table-danger' : ($dueSoon ? 'table-warning' : '') ?>">
                  <td>
                    <span class="fw-medium text-primary">
                      #<?= str_pad($o['order_id'], 4, '0', STR_PAD_LEFT) ?>
                    </span>
                  </td>
                  <td>
                    <div class="fw-medium"><?= htmlspecialchars($o['customer_name']) ?></div>
                    <div class="text-muted" style="font-size:11.5px">
                      <?= htmlspecialchars($o['mobile_number']) ?>
                    </div>
                  </td>
                  <td><?= htmlspecialchars($o['order_name']) ?></td>
                  <td class="text-muted"><?= htmlspecialchars($o['staff_name']) ?></td>
                  <td>₱<?= number_format($o['price'], 2) ?></td>
                  <td>
                    <?php if ((float)$o['balance'] > 0): ?>
                      <span class="text-danger fw-semibold">
                        ₱<?= number_format($o['balance'], 2) ?>
                      </span>
                    <?php else: ?>
                      <span class="text-success">Paid</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <?= date('M j, Y', strtotime($o['due_date'])) ?>
                    <?php if ($overdue): ?>
                      <div class="text-danger" style="font-size:11px;font-weight:600">
                        <?= abs($o['days_until_due']) ?> day<?= abs($o['days_until_due']) > 1 ? 's' : '' ?> overdue
                      </div>
                    <?php elseif ($dueSoon): ?>
                      <span class="badge bg-warning text-dark ms-1" style="font-size:10px">
                        Due soon
                      </span>
                    <?php endif; ?>
                  </td>
                  <td><?= orderStatusBadge($o['status']) ?></td>
                  <td>
                    <a href="/tailorshop/modules/tailoring/view.php?id=<?= $o['order_id'] ?>"
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

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>