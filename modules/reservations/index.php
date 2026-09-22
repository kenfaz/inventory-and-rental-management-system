<?php
// ============================================================
// modules/reservations/index.php
// List all reservations with status filter
// ============================================================

require_once __DIR__ . '/../../../includes/auth-guard.php';
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../helpers/rental-helper.php';

$pageTitle  = 'Reservations';
$activePage = 'reservations';
$role       = $_SESSION['user_role'];

$statusFilter = trim($_GET['status'] ?? '');
$search       = trim($_GET['q']      ?? '');

$where  = [];
$params = [];

if ($statusFilter !== '') {
    $where[]  = 'res.status = ?';
    $params[] = $statusFilter;
}
if ($search !== '') {
    $where[]  = '(c.full_name LIKE ? OR g.garment_name LIKE ? OR g.garment_code LIKE ?)';
    $like     = '%' . $search . '%';
    $params   = array_merge($params, [$like, $like, $like]);
}

$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = $pdo->prepare("
    SELECT res.*,
           c.full_name   AS customer_name,
           c.mobile_number,
           g.garment_name, g.garment_code,
           u.full_name   AS processed_by_name
    FROM reservations res
    JOIN customers     c ON res.customer_id = c.customer_id
    JOIN garment_items g ON res.garment_id  = g.garment_id
    JOIN users         u ON res.processed_by = u.user_id
    $whereSQL
    ORDER BY res.created_at DESC
");
$stmt->execute($params);
$reservations = $stmt->fetchAll();

$statuses = ['Pending','Confirmed','Converted','Cancelled'];

$success = $_SESSION['success'] ?? '';
$error   = $_SESSION['error']   ?? '';
unset($_SESSION['success'], $_SESSION['error']);

require_once __DIR__ . '/../../../includes/header.php';
require_once __DIR__ . '/../../../includes/sidebar.php';
?>

<div class="main-content">
  <div class="topbar">
    <span class="topbar-title">Reservations</span>
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
        <h5>Reservations</h5>
        <p class="text-muted small mb-0">
          <?= count($reservations) ?> record<?= count($reservations) !== 1 ? 's' : '' ?> found
        </p>
      </div>
      <a href="/tailorshop/modules/reservations/create.php" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i> New Reservation
      </a>
    </div>

    <!-- Search + filter -->
    <div class="card mb-3">
      <div class="card-body py-2 px-3">
        <form method="GET" class="d-flex gap-2 align-items-center flex-wrap">
          <i class="bi bi-search text-muted"></i>
          <input type="text" name="q"
                 class="form-control border-0 shadow-none ps-0"
                 placeholder="Search by customer or garment…"
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
            <a href="/tailorshop/modules/reservations/index.php"
               class="btn btn-sm btn-outline-secondary">Clear</a>
          <?php endif; ?>
        </form>
      </div>
    </div>

    <!-- Reservations table -->
    <div class="card">
      <div class="table-responsive">
        <table class="table mb-0">
          <thead>
            <tr>
              <th>#</th>
              <th>Customer</th>
              <th>Garment</th>
              <th>Pickup Date</th>
              <th>Return Date</th>
              <th>Status</th>
              <th>Created</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($reservations)): ?>
              <tr>
                <td colspan="8" class="text-center text-muted py-5">
                  <i class="bi bi-calendar-x fs-3 d-block mb-2"></i>
                  <?= $search || $statusFilter
                      ? 'No reservations matched your search.'
                      : 'No reservations yet.' ?>
                  <br>
                  <a href="/tailorshop/modules/reservations/create.php"
                     class="btn btn-sm btn-primary mt-2">
                    Create First Reservation
                  </a>
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($reservations as $res):
                $pickupSoon = strtotime($res['pickup_date'])
                              <= strtotime('+3 days')
                              && $res['status'] === 'Confirmed';
              ?>
                <tr class="<?= $pickupSoon ? 'table-warning' : '' ?>">
                  <td>
                    <span class="fw-medium text-primary">
                      #<?= str_pad($res['reservation_id'], 4, '0', STR_PAD_LEFT) ?>
                    </span>
                  </td>
                  <td>
                    <div class="fw-medium"><?= htmlspecialchars($res['customer_name']) ?></div>
                    <div class="text-muted" style="font-size:11.5px">
                      <?= htmlspecialchars($res['mobile_number']) ?>
                    </div>
                  </td>
                  <td>
                    <div><?= htmlspecialchars($res['garment_name']) ?></div>
                    <div class="text-muted" style="font-size:11px;font-family:monospace">
                      <?= htmlspecialchars($res['garment_code']) ?>
                    </div>
                  </td>
                  <td>
                    <?= date('M j, Y', strtotime($res['pickup_date'])) ?>
                    <?php if ($pickupSoon): ?>
                      <span class="badge bg-warning text-dark ms-1" style="font-size:10px">
                        Soon
                      </span>
                    <?php endif; ?>
                  </td>
                  <td><?= date('M j, Y', strtotime($res['return_date'])) ?></td>
                  <td><?= reservationStatusBadge($res['status']) ?></td>
                  <td class="text-muted" style="font-size:12px">
                    <?= date('M j, Y', strtotime($res['created_at'])) ?>
                  </td>
                  <td>
                    <a href="/tailorshop/modules/reservations/view.php?id=<?= $res['reservation_id'] ?>"
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