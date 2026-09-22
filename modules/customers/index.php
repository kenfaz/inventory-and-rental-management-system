<?php
// ============================================================
// modules/customers/index.php
// List all customers with search
// ============================================================

require_once __DIR__ . '/../../../includes/auth-guard.php';
require_once __DIR__ . '/../../../config/db.php';

$pageTitle  = 'Customers';
$activePage = 'customers';
$role       = $_SESSION['user_role'];

$search = trim($_GET['q'] ?? '');

$where  = [];
$params = [];
if ($search !== '') {
    $where[]  = '(c.full_name LIKE ? OR c.mobile_number LIKE ? OR c.address LIKE ?)';
    $like     = '%' . $search . '%';
    $params   = [$like, $like, $like];
}
$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = $pdo->prepare("
    SELECT c.*,
           COUNT(DISTINCT r.rental_id)      AS total_rentals,
           COUNT(DISTINCT res.reservation_id) AS total_reservations,
           COUNT(DISTINCT o.order_id)       AS total_orders
    FROM customers c
    LEFT JOIN rentals      r   ON r.customer_id   = c.customer_id
    LEFT JOIN reservations res ON res.customer_id = c.customer_id
    LEFT JOIN tailoring_orders o ON o.customer_id = c.customer_id
    $whereSQL
    GROUP BY c.customer_id
    ORDER BY c.full_name ASC
");
$stmt->execute($params);
$customers = $stmt->fetchAll();

$success = $_SESSION['success'] ?? '';
$error   = $_SESSION['error']   ?? '';
unset($_SESSION['success'], $_SESSION['error']);

require_once __DIR__ . '/../../../includes/header.php';
require_once __DIR__ . '/../../../includes/sidebar.php';
?>

<div class="main-content">
  <div class="topbar">
    <span class="topbar-title">Customers</span>
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
        <h5>Customer Records</h5>
        <p class="text-muted small mb-0">
          <?= count($customers) ?> customer<?= count($customers) !== 1 ? 's' : '' ?> found
        </p>
      </div>
      <a href="/tailorshop/modules/customers/add.php" class="btn btn-primary">
        <i class="bi bi-person-plus me-1"></i> Add Customer
      </a>
    </div>

    <!-- Search bar -->
    <div class="card mb-3">
      <div class="card-body py-2 px-3">
        <form method="GET" class="d-flex gap-2 align-items-center">
          <i class="bi bi-search text-muted"></i>
          <input type="text" name="q"
                 class="form-control border-0 shadow-none ps-0"
                 placeholder="Search by name, mobile, or address…"
                 value="<?= htmlspecialchars($search) ?>"
                 style="font-size:13.5px;flex:1">
          <button class="btn btn-sm btn-primary">Search</button>
          <?php if ($search): ?>
            <a href="/tailorshop/modules/customers/index.php"
               class="btn btn-sm btn-outline-secondary">Clear</a>
          <?php endif; ?>
        </form>
      </div>
    </div>

    <!-- Customers table -->
    <div class="card">
      <div class="table-responsive">
        <table class="table mb-0">
          <thead>
            <tr>
              <th>Name</th>
              <th>Mobile Number</th>
              <th>Address</th>
              <th>Rentals</th>
              <th>Reservations</th>
              <th>Orders</th>
              <th>Date Added</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($customers)): ?>
              <tr>
                <td colspan="8" class="text-center text-muted py-5">
                  <i class="bi bi-people fs-3 d-block mb-2"></i>
                  <?= $search ? 'No customers matched your search.' : 'No customers yet.' ?>
                  <?php if (!$search): ?>
                    <br>
                    <a href="/tailorshop/modules/customers/add.php"
                       class="btn btn-sm btn-primary mt-2">Add First Customer</a>
                  <?php endif; ?>
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($customers as $c):
                $initials = '';
                foreach (explode(' ', $c['full_name']) as $w)
                    if ($w) $initials .= strtoupper($w[0]);
                $initials = substr($initials, 0, 2);
              ?>
                <tr>
                  <td>
                    <div class="d-flex align-items-center gap-2">
                      <div style="width:34px;height:34px;border-radius:50%;
                                  background:#EDE9FE;color:#7C3AED;
                                  display:flex;align-items:center;justify-content:center;
                                  font-size:12px;font-weight:600;flex-shrink:0">
                        <?= htmlspecialchars($initials) ?>
                      </div>
                      <span class="fw-medium"><?= htmlspecialchars($c['full_name']) ?></span>
                    </div>
                  </td>
                  <td>
                    <i class="bi bi-phone text-muted me-1"></i>
                    <?= htmlspecialchars($c['mobile_number']) ?>
                  </td>
                  <td class="text-muted" style="font-size:12.5px;max-width:180px;
                              white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
                    <?= htmlspecialchars($c['address'] ?? '—') ?>
                  </td>
                  <td>
                    <span class="badge bg-warning bg-opacity-15 text-warning rounded-pill">
                      <?= $c['total_rentals'] ?>
                    </span>
                  </td>
                  <td>
                    <span class="badge bg-primary bg-opacity-15 text-primary rounded-pill">
                      <?= $c['total_reservations'] ?>
                    </span>
                  </td>
                  <td>
                    <span class="badge bg-secondary bg-opacity-15 text-secondary rounded-pill">
                      <?= $c['total_orders'] ?>
                    </span>
                  </td>
                  <td class="text-muted" style="font-size:12.5px">
                    <?= date('M j, Y', strtotime($c['created_at'])) ?>
                  </td>
                  <td>
                    <div class="d-flex gap-1">
                      <a href="/tailorshop/modules/customers/view.php?id=<?= $c['customer_id'] ?>"
                         class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-eye"></i>
                      </a>
                      <a href="/tailorshop/modules/customers/edit.php?id=<?= $c['customer_id'] ?>"
                         class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-pencil"></i>
                      </a>
                    </div>
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