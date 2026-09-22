<?php
// ============================================================
// modules/inventory/garments/index.php
// List all garment items with search and filter
// ============================================================

require_once __DIR__ . '/../../../../includes/auth-guard.php';
require_once __DIR__ . '/../../../../config/db.php';
require_once __DIR__ . '/../../../../helpers/rental-helper.php';

$pageTitle  = 'Garments Inventory';
$activePage = 'inventory-garments';
$role       = $_SESSION['user_role'];

// ── Filters ────────────────────────────────────────────────
$search       = trim($_GET['q']            ?? '');
$categoryId   = (int)($_GET['category']    ?? 0);
$availability = trim($_GET['availability'] ?? '');

$where  = [];
$params = [];

if ($search !== '') {
    $where[]  = '(g.garment_name LIKE ? OR g.garment_code LIKE ? OR g.color LIKE ?)';
    $like     = '%' . $search . '%';
    $params   = array_merge($params, [$like, $like, $like]);
}
if ($categoryId > 0) {
    $where[]  = 'g.category_id = ?';
    $params[] = $categoryId;
}
if ($availability !== '') {
    $where[]  = 'g.availability_status = ?';
    $params[] = $availability;
}

$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = $pdo->prepare("
    SELECT g.*, gc.category_name
    FROM garment_items g
    JOIN garment_categories gc ON g.category_id = gc.category_id
    $whereSQL
    ORDER BY gc.category_name ASC, g.garment_name ASC
");
$stmt->execute($params);
$garments = $stmt->fetchAll();

$categories = $pdo->query('
    SELECT * FROM garment_categories ORDER BY category_name ASC
')->fetchAll();

$availabilityOptions = [
    'Available','Reserved','Rented Out','Under Repair','Damaged','Sold'
];

$success = $_SESSION['success'] ?? '';
$error   = $_SESSION['error']   ?? '';
unset($_SESSION['success'], $_SESSION['error']);

require_once __DIR__ . '/../../../../includes/header.php';
require_once __DIR__ . '/../../../../includes/sidebar.php';
?>

<div class="main-content">
  <div class="topbar">
    <span class="topbar-title">Garments Inventory</span>
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
        <h5>Garments Inventory</h5>
        <p class="text-muted small mb-0">
          <?= count($garments) ?> garment<?= count($garments) !== 1 ? 's' : '' ?> found
        </p>
      </div>
      <?php if ($role === 'admin'): ?>
        <div class="d-flex gap-2">
          <a href="/tailorshop/modules/inventory/garments/categories.php"
             class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-tags me-1"></i> Categories
          </a>
          <a href="/tailorshop/modules/inventory/garments/add.php"
             class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i> Add Garment
          </a>
        </div>
      <?php endif; ?>
    </div>

    <!-- Search and filter bar -->
    <div class="card mb-3">
      <div class="card-body py-2 px-3">
        <form method="GET" class="d-flex gap-2 align-items-center flex-wrap">
          <i class="bi bi-search text-muted"></i>
          <input type="text" name="q"
                 class="form-control border-0 shadow-none ps-0"
                 placeholder="Search by name, code, or color…"
                 value="<?= htmlspecialchars($search) ?>"
                 style="font-size:13.5px;min-width:180px;flex:1">
          <select name="category" class="form-select form-select-sm"
                  style="width:auto" onchange="this.form.submit()">
            <option value="0">All Categories</option>
            <?php foreach ($categories as $cat): ?>
              <option value="<?= $cat['category_id'] ?>"
                <?= $categoryId === (int)$cat['category_id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($cat['category_name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <select name="availability" class="form-select form-select-sm"
                  style="width:auto" onchange="this.form.submit()">
            <option value="">All Statuses</option>
            <?php foreach ($availabilityOptions as $opt): ?>
              <option value="<?= $opt ?>"
                <?= $availability === $opt ? 'selected' : '' ?>>
                <?= $opt ?>
              </option>
            <?php endforeach; ?>
          </select>
          <button class="btn btn-sm btn-primary">Search</button>
          <?php if ($search || $categoryId || $availability): ?>
            <a href="/tailorshop/modules/inventory/garments/index.php"
               class="btn btn-sm btn-outline-secondary">Clear</a>
          <?php endif; ?>
        </form>
      </div>
    </div>

    <!-- Garments table -->
    <div class="card">
      <div class="table-responsive">
        <table class="table mb-0">
          <thead>
            <tr>
              <th>Code</th>
              <th>Garment Name</th>
              <th>Category</th>
              <th>Size</th>
              <th>Color</th>
              <th>Rental Price</th>
              <th>Sale Price</th>
              <th>Condition</th>
              <th>Availability</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($garments)): ?>
              <tr>
                <td colspan="10" class="text-center text-muted py-5">
                  <i class="bi bi-bag-heart fs-3 d-block mb-2"></i>
                  <?= $search || $categoryId || $availability
                      ? 'No garments matched your search.'
                      : 'No garments yet.' ?>
                  <?php if ($role === 'admin' && !$search && !$categoryId && !$availability): ?>
                    <br>
                    <a href="/tailorshop/modules/inventory/garments/add.php"
                       class="btn btn-sm btn-primary mt-2">Add First Garment</a>
                  <?php endif; ?>
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($garments as $g): ?>
                <tr>
                  <td>
                    <span class="fw-medium text-primary" style="font-family:monospace">
                      <?= htmlspecialchars($g['garment_code']) ?>
                    </span>
                  </td>
                  <td class="fw-medium"><?= htmlspecialchars($g['garment_name']) ?></td>
                  <td><?= htmlspecialchars($g['category_name']) ?></td>
                  <td><?= htmlspecialchars($g['size'] ?? '—') ?></td>
                  <td><?= htmlspecialchars($g['color'] ?? '—') ?></td>
                  <td>
                    <?= $g['rental_price'] !== null
                        ? '₱' . number_format($g['rental_price'], 2)
                        : '<span class="text-muted">—</span>' ?>
                  </td>
                  <td>
                    <?= $g['sale_price'] !== null
                        ? '₱' . number_format($g['sale_price'], 2)
                        : '<span class="text-muted">—</span>' ?>
                  </td>
                  <td>
                    <?php
                    $condColors = [
                        'Good'          => 'success',
                        'Needs Cleaning'=> 'warning',
                        'Needs Repair'  => 'warning',
                        'Damaged'       => 'danger',
                    ];
                    $condColor = $condColors[$g['condition_status']] ?? 'secondary';
                    ?>
                    <span class="badge bg-<?= $condColor ?> bg-opacity-10
                                  text-<?= $condColor ?> rounded-pill"
                          style="font-size:11px">
                      <?= htmlspecialchars($g['condition_status']) ?>
                    </span>
                  </td>
                  <td><?= garmentStatusBadge($g['availability_status']) ?></td>
                  <td>
                    <div class="d-flex gap-1">
                      <a href="/tailorshop/modules/inventory/garments/view.php?id=<?= $g['garment_id'] ?>"
                         class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-eye"></i>
                      </a>
                      <?php if ($role === 'admin'): ?>
                        <a href="/tailorshop/modules/inventory/garments/edit.php?id=<?= $g['garment_id'] ?>"
                           class="btn btn-sm btn-outline-secondary">
                          <i class="bi bi-pencil"></i>
                        </a>
                        <a href="/tailorshop/modules/inventory/garments/delete.php?id=<?= $g['garment_id'] ?>"
                           class="btn btn-sm btn-outline-danger"
                           data-confirm="Delete &quot;<?= htmlspecialchars($g['garment_name']) ?>&quot;?">
                          <i class="bi bi-trash"></i>
                        </a>
                      <?php endif; ?>
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

<?php require_once __DIR__ . '/../../../../includes/footer.php'; ?>