<?php
// ============================================================
// modules/inventory/materials/add.php
// Add new material item — admin only
// ============================================================

require_once __DIR__ . '/../../../../includes/auth-guard.php';
require_once __DIR__ . '/../../../../includes/role-guard.php';
require_once __DIR__ . '/../../../../config/db.php';
require_once __DIR__ . '/../../../../config/constants.php';
require_once __DIR__ . '/../../../../helpers/audit-helper.php';

$pageTitle  = 'Add Material Item';
$activePage = 'inventory-materials';

// Fetch categories for dropdown
$categories = $pdo->query('SELECT * FROM material_categories ORDER BY category_name ASC')->fetchAll();

$errors = [];
$values = [
    'category_id'         => '',
    'item_name'           => '',
    'brand'               => '',
    'quantity'            => '',
    'unit'                => 'piece',
    'low_stock_threshold' => DEFAULT_LOW_STOCK_THRESHOLD,
    'description'         => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $values = [
        'category_id'         => trim($_POST['category_id']         ?? ''),
        'item_name'           => trim($_POST['item_name']           ?? ''),
        'brand'               => trim($_POST['brand']               ?? ''),
        'quantity'            => trim($_POST['quantity']            ?? ''),
        'unit'                => trim($_POST['unit']                ?? 'piece'),
        'low_stock_threshold' => trim($_POST['low_stock_threshold'] ?? DEFAULT_LOW_STOCK_THRESHOLD),
        'description'         => trim($_POST['description']         ?? ''),
    ];

    // Validate
    if (!$values['category_id'])
        $errors['category_id'] = 'Please select a category.';
    if ($values['item_name'] === '')
        $errors['item_name'] = 'Item name is required.';
    if (!is_numeric($values['quantity']) || (float)$values['quantity'] < 0)
        $errors['quantity'] = 'Quantity must be 0 or more.';
    if ($values['unit'] === '')
        $errors['unit'] = 'Unit is required.';
    if (!is_numeric($values['low_stock_threshold']) || (float)$values['low_stock_threshold'] < 0)
        $errors['low_stock_threshold'] = 'Threshold must be 0 or more.';

    if (empty($errors)) {
        $pdo->prepare('
            INSERT INTO material_items
                (category_id, item_name, brand, quantity,
                 unit, low_stock_threshold, description)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ')->execute([
            (int)$values['category_id'],
            $values['item_name'],
            $values['brand']       ?: null,
            (float)$values['quantity'],
            $values['unit'],
            (float)$values['low_stock_threshold'],
            $values['description'] ?: null,
        ]);

        $newId = (int)$pdo->lastInsertId();
        auditCreate($_SESSION['user_id'], 'Inventory', 'material_items', $newId, $values);

        $_SESSION['success'] = 'Item "' . $values['item_name'] . '" added successfully.';
        header('Location: /tailorshop/modules/inventory/materials/index.php');
        exit;
    }
}

require_once __DIR__ . '/../../../../includes/header.php';
require_once __DIR__ . '/../../../../includes/sidebar.php';
?>

<div class="main-content">
  <div class="topbar">
    <span class="topbar-title">Add Material Item</span>
  </div>

  <div class="page-body">

    <!-- Breadcrumb -->
    <nav class="breadcrumb-nav mb-3">
      <a href="/tailorshop/modules/inventory/materials/index.php">
        <i class="bi bi-box-seam me-1"></i>Materials
      </a>
      <span class="mx-2">/</span>
      <span>Add Item</span>
    </nav>

    <div class="row justify-content-center">
      <div class="col-lg-7">
        <div class="card">
          <div class="card-header">
            <i class="bi bi-plus-circle me-2 text-primary"></i>New Material Item
          </div>
          <div class="card-body p-4">
            <form method="POST" novalidate>

              <!-- Category -->
              <div class="mb-3">
                <label class="form-label">
                  Category <span class="text-danger">*</span>
                </label>
                <select name="category_id"
                        class="form-select <?= isset($errors['category_id']) ? 'is-invalid' : '' ?>"
                        required>
                  <option value="">— Select category —</option>
                  <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat['category_id'] ?>"
                      <?= $values['category_id'] == $cat['category_id'] ? 'selected' : '' ?>>
                      <?= htmlspecialchars($cat['category_name']) ?>
                      (<?= htmlspecialchars($cat['default_unit']) ?>)
                    </option>
                  <?php endforeach; ?>
                </select>
                <?php if (isset($errors['category_id'])): ?>
                  <div class="invalid-feedback"><?= $errors['category_id'] ?></div>
                <?php endif; ?>
              </div>

              <!-- Item name -->
              <div class="mb-3">
                <label class="form-label">
                  Item Name <span class="text-danger">*</span>
                </label>
                <input type="text" name="item_name"
                       class="form-control <?= isset($errors['item_name']) ? 'is-invalid' : '' ?>"
                       placeholder="e.g. White Satin Fabric"
                       value="<?= htmlspecialchars($values['item_name']) ?>"
                       required>
                <?php if (isset($errors['item_name'])): ?>
                  <div class="invalid-feedback"><?= $errors['item_name'] ?></div>
                <?php endif; ?>
              </div>

              <!-- Brand -->
              <div class="mb-3">
                <label class="form-label">Brand</label>
                <input type="text" name="brand" class="form-control"
                       placeholder="e.g. Coats (optional)"
                       value="<?= htmlspecialchars($values['brand']) ?>">
              </div>

              <!-- Quantity + Unit -->
              <div class="row g-3 mb-3">
                <div class="col-sm-6">
                  <label class="form-label">
                    Initial Quantity <span class="text-danger">*</span>
                  </label>
                  <input type="number" name="quantity" step="0.01" min="0"
                         class="form-control <?= isset($errors['quantity']) ? 'is-invalid' : '' ?>"
                         placeholder="0"
                         value="<?= htmlspecialchars($values['quantity']) ?>"
                         required>
                  <?php if (isset($errors['quantity'])): ?>
                    <div class="invalid-feedback"><?= $errors['quantity'] ?></div>
                  <?php endif; ?>
                </div>
                <div class="col-sm-6">
                  <label class="form-label">
                    Unit <span class="text-danger">*</span>
                  </label>
                  <select name="unit"
                          class="form-select <?= isset($errors['unit']) ? 'is-invalid' : '' ?>">
                    <?php
                    $units = ['piece','meter','yard','spool','roll','pack','box','set','gram','liter'];
                    foreach ($units as $u):
                    ?>
                      <option value="<?= $u ?>"
                        <?= $values['unit'] === $u ? 'selected' : '' ?>>
                        <?= ucfirst($u) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                  <?php if (isset($errors['unit'])): ?>
                    <div class="invalid-feedback"><?= $errors['unit'] ?></div>
                  <?php endif; ?>
                </div>
              </div>

              <!-- Low stock threshold -->
              <div class="mb-3">
                <label class="form-label">
                  Low Stock Threshold <span class="text-danger">*</span>
                </label>
                <input type="number" name="low_stock_threshold"
                       step="0.01" min="0"
                       class="form-control <?= isset($errors['low_stock_threshold']) ? 'is-invalid' : '' ?>"
                       placeholder="<?= DEFAULT_LOW_STOCK_THRESHOLD ?>"
                       value="<?= htmlspecialchars($values['low_stock_threshold']) ?>"
                       required>
                <div class="form-text">
                  An SMS alert will be sent to the owner when stock
                  reaches this level. Default is <?= DEFAULT_LOW_STOCK_THRESHOLD ?>.
                </div>
                <?php if (isset($errors['low_stock_threshold'])): ?>
                  <div class="invalid-feedback"><?= $errors['low_stock_threshold'] ?></div>
                <?php endif; ?>
              </div>

              <!-- Description -->
              <div class="mb-4">
                <label class="form-label">Description</label>
                <textarea name="description" class="form-control" rows="2"
                          placeholder="Optional notes about this item…"
                          ><?= htmlspecialchars($values['description']) ?></textarea>
              </div>

              <!-- Actions -->
              <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary px-4">
                  <i class="bi bi-plus-circle me-1"></i> Add Item
                </button>
                <a href="/tailorshop/modules/inventory/materials/index.php"
                   class="btn btn-outline-secondary px-4">Cancel</a>
              </div>

            </form>
          </div>
        </div>
      </div>
    </div>

  </div>
</div>

<?php require_once __DIR__ . '/../../../../includes/footer.php'; ?>