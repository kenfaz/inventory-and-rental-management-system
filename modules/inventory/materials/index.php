<?php
// ============================================================
// modules/inventory/materials/index.php
// List all material items with search and category filter
// ============================================================

require_once __DIR__ . '/../../../../includes/auth-guard.php';
require_once __DIR__ . '/../../../../config/db.php';
require_once __DIR__ . '/../../../../config/constants.php';

$pageTitle  = 'Materials Inventory';
$activePage = 'inventory-materials';

// ── Filters ────────────────────────────────────────────────
$search     = trim($_GET['q']        ?? '');
$categoryId = (int)($_GET['category'] ?? 0);

$where  = [];
$params = [];

if ($search !== '') {
    $where[]  = '(m.item_name LIKE ? OR m.brand LIKE ?)';
    $like     = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
}
if ($categoryId > 0) {
    $where[]  = 'm.category_id = ?';
    $params[] = $categoryId;
}

$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = $pdo->prepare("
    SELECT m.*, mc.category_name
    FROM material_items m
    JOIN material_categories mc ON m.category_id = mc.category_id
    $whereSQL
    ORDER BY mc.category_name ASC, m.item_name ASC
");
$stmt->execute($params);
$items = $stmt->fetchAll();

// Fetch categories for filter dropdown
$categories = $pdo->query('SELECT * FROM material_categories ORDER BY category_name ASC')->fetchAll();

// Flash messages
$success = $_SESSION['success'] ?? '';
$error   = $_SESSION['error']   ?? '';
unset($_SESSION['success'], $_SESSION['error']);

$role = $_SESSION['user_role'];

require_once __DIR__ . '/../../../../includes/header.php';
require_once __DIR__ . '/../../../../includes/sidebar.php';
?>

<div class="main-content">
  <div class="topbar">
    <span class="topbar-title">Materials Inventory</span>
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
        <h5>Materials Inventory</h5>
        <p class="text-muted small mb-0">
          <?= count($items) ?> item<?= count($items) !== 1 ? 's' : '' ?> found
        </p>
      </div>
      <?php if ($role === 'admin'): ?>
        <div class="d-flex gap-2">
          <a href="/tailorshop/modules/inventory/materials/categories.php"
             class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-tags me-1"></i> Categories
          </a>
          <a href="/tailorshop/modules/inventory/materials/add.php"
             class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i> Add Item
          </a>
        </div>
      <?php endif; ?>
    </div>

    <!-- Search and filter -->
    <div class="card mb-3">
      <div class="card-body py-2 px-3">
        <form method="GET" class="d-flex gap-2 align-items-center flex-wrap">
          <i class="bi bi-search text-muted"></i>
          <input
            type="text" name="q"
            class="form-control border-0 shadow-none ps-0"
            placeholder="Search by name or brand…"
            value="<?= htmlspecialchars($search) ?>"
            style="font-size:13.5px;min-width:200px;flex:1"
          >
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
          <button class="btn btn-sm btn-primary">Search</button>
          <?php if ($search || $categoryId): ?>
            <a href="/tailorshop/modules/inventory/materials/index.php"
               class="btn btn-sm btn-outline-secondary">Clear</a>
          <?php endif; ?>
        </form>
      </div>
    </div>

    <!-- Materials table -->
    <div class="card">
      <div class="table-responsive">
        <table class="table mb-0">
          <thead>
            <tr>
              <th>Item Name</th>
              <th>Brand</th>
              <th>Category</th>
              <th>Quantity</th>
              <th>Unit</th>
              <th>Threshold</th>
              <th>Status</th>
              <?php if ($role === 'admin'): ?>
                <th></th>
              <?php endif; ?>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($items)): ?>
              <tr>
                <td colspan="<?= $role === 'admin' ? 8 : 7 ?>"
                    class="text-center text-muted py-5">
                  <i class="bi bi-box-seam fs-3 d-block mb-2"></i>
                  <?= $search || $categoryId ? 'No items matched your search.' : 'No material items yet.' ?>
                  <?php if ($role === 'admin' && !$search && !$categoryId): ?>
                    <br>
                    <a href="/tailorshop/modules/inventory/materials/add.php"
                       class="btn btn-sm btn-primary mt-2">Add First Item</a>
                  <?php endif; ?>
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($items as $item):
                $isOut = $item['quantity'] <= 0;
                $isLow = !$isOut && $item['quantity'] <= $item['low_stock_threshold'];
              ?>
                <tr>
                  <td class="fw-medium"><?= htmlspecialchars($item['item_name']) ?></td>
                  <td class="text-muted"><?= htmlspecialchars($item['brand'] ?? '—') ?></td>
                  <td><?= htmlspecialchars($item['category_name']) ?></td>
                  <td>
                    <span class="fw-semibold <?= $isOut ? 'text-danger' : ($isLow ? 'text-warning' : 'text-success') ?>">
                      <?= rtrim(rtrim(number_format($item['quantity'], 2), '0'), '.') ?>
                    </span>
                  </td>
                  <td><?= htmlspecialchars($item['unit']) ?></td>
                  <td class="text-muted">
                    <?= rtrim(rtrim(number_format($item['low_stock_threshold'], 2), '0'), '.') ?>
                    <?= htmlspecialchars($item['unit']) ?>
                  </td>
                  <td>
                    <?php if ($isOut): ?>
                      <span class="badge bg-danger rounded-pill">Out of Stock</span>
                    <?php elseif ($isLow): ?>
                      <span class="badge-status badge-due-today">
                        <i class="bi bi-exclamation-triangle-fill" style="font-size:8px"></i>
                        Low Stock
                      </span>
                    <?php else: ?>
                      <span class="badge-status badge-available">
                        <i class="bi bi-check-circle-fill" style="font-size:8px"></i>
                        In Stock
                      </span>
                    <?php endif; ?>
                  </td>
                  <?php if ($role === 'admin'): ?>
                    <td>
                      <div class="d-flex gap-1">
                        <a href="/tailorshop/modules/inventory/materials/edit.php?id=<?= $item['item_id'] ?>"
                           class="btn btn-sm btn-outline-secondary">
                          <i class="bi bi-pencil"></i>
                        </a>
                        <a href="/tailorshop/modules/inventory/materials/delete.php?id=<?= $item['item_id'] ?>"
                           class="btn btn-sm btn-outline-danger"
                           data-confirm="Delete &quot;<?= htmlspecialchars($item['item_name']) ?>&quot;? This cannot be undone.">
                          <i class="bi bi-trash"></i>
                        </a>
                      </div>
                    </td>
                  <?php endif; ?>
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