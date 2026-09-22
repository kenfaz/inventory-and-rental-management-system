<?php
// ============================================================
// modules/inventory/materials/edit.php
// Edit existing material item — admin only
// ============================================================

require_once __DIR__ . '/../../../../includes/auth-guard.php';
require_once __DIR__ . '/../../../../includes/role-guard.php';
require_once __DIR__ . '/../../../../config/db.php';
require_once __DIR__ . '/../../../../config/constants.php';
require_once __DIR__ . '/../../../../helpers/audit-helper.php';

$pageTitle  = 'Edit Material Item';
$activePage = 'inventory-materials';

$itemId = (int)($_GET['id'] ?? 0);
if ($itemId === 0) {
    header('Location: /tailorshop/modules/inventory/materials/index.php');
    exit;
}

// Fetch item
$stmt = $pdo->prepare('
    SELECT m.*, mc.category_name
    FROM material_items m
    JOIN material_categories mc ON m.category_id = mc.category_id
    WHERE m.item_id = ?
');
$stmt->execute([$itemId]);
$item = $stmt->fetch();

if (!$item) {
    $_SESSION['error'] = 'Item not found.';
    header('Location: /tailorshop/modules/inventory/materials/index.php');
    exit;
}

// Fetch categories
$categories = $pdo->query('SELECT * FROM material_categories ORDER BY category_name ASC')->fetchAll();

$errors = [];
$values = [
    'category_id'         => $item['category_id'],
    'item_name'           => $item['item_name'],
    'brand'               => $item['brand'] ?? '',
    'quantity'            => $item['quantity'],
    'unit'                => $item['unit'],
    'low_stock_threshold' => $item['low_stock_threshold'],
    'description'         => $item['description'] ?? '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $values = [
        'category_id'         => trim($_POST['category_id']         ?? ''),
        'item_name'           => trim($_POST['item_name']           ?? ''),
        'brand'               => trim($_POST['brand']               ?? ''),
        'quantity'            => trim($_POST['quantity']            ?? ''),
        'unit'                => trim($_POST['unit']                ?? ''),
        'low_stock_threshold' => trim($_POST['low_stock_threshold'] ?? ''),
        'description'         => trim($_POST['description']         ?? ''),
    ];

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
        $old = [
            'item_name'           => $item['item_name'],
            'quantity'            => $item['quantity'],
            'unit'                => $item['unit'],
            'low_stock_threshold' => $item['low_stock_threshold'],
        ];

        $pdo->prepare('
            UPDATE material_items
            SET category_id = ?, item_name = ?, brand = ?,
                quantity = ?, unit = ?,
                low_stock_threshold = ?, description = ?
            WHERE item_id = ?
        ')->execute([
            (int)$values['category_id'],
            $values['item_name'],
            $values['brand']       ?: null,
            (float)$values['quantity'],
            $values['unit'],
            (float)$values['low_stock_threshold'],
            $values['description'] ?: null,
            $itemId,
        ]);

        auditUpdate(
            $_SESSION['user_id'], 'Inventory',
            'material_items', $itemId, $old, $values
        );

        $_SESSION['success'] = 'Item "' . $values['item_name'] . '" updated successfully.';
        header('Location: /tailorshop/modules/inventory/materials/index.php');
        exit;
    }
}

require_once __DIR__ . '/../../../../includes/header.php';
require_once __DIR__ . '/../../../../includes/sidebar.php';
?>

<div class="main-content">
  <div class="topbar">
    <span class="topbar-title">Edit Material Item</span>
  </div>

  <div class="page-body">

    <nav class="breadcrumb-nav mb-3">
      <a href="/tailorshop/modules/inventory/materials/index.php">
        <i class="bi bi-box-seam me-1"></i>Materials
      </a>
      <span class="mx-2">/</span>
      <span>Edit Item</span>
    </nav>

    <div class="row justify-content-center">
      <div class="col-lg-7">
        <div class="card">
          <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="bi bi-pencil me-2 text-primary"></i>Edit Item</span>
            <span class="text-muted small">ID #<?= $itemId ?></span>
          </div>
          <div class="card-body p-4">
            <form method="POST" novalidate>

              <div class="mb-3">
                <label class="form-label">Category <span class="text-danger">*</span></label>
                <select name="category_id"
                        class="form-select <?= isset($errors['category_id']) ? 'is-invalid' : '' ?>">
                  <option value="">— Select category —</option>
                  <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat['category_id'] ?>"
                      <?= $values['category_id'] == $cat['category_id'] ? 'selected' : '' ?>>
                      <?= htmlspecialchars($cat['category_name']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
                <?php if (isset($errors['category_id'])): ?>
                  <div class="invalid-feedback"><?= $errors['category_id'] ?></div>
                <?php endif; ?>
              </div>

              <div class="mb-3">
                <label class="form-label">Item Name <span class="text-danger">*</span></label>
                <input type="text" name="item_name"
                       class="form-control <?= isset($errors['item_name']) ? 'is-invalid' : '' ?>"
                       value="<?= htmlspecialchars($values['item_name']) ?>" required>
                <?php if (isset($errors['item_name'])): ?>
                  <div class="invalid-feedback"><?= $errors['item_name'] ?></div>
                <?php endif; ?>
              </div>

              <div class="mb-3">
                <label class="form-label">Brand</label>
                <input type="text" name="brand" class="form-control"
                       value="<?= htmlspecialchars($values['brand']) ?>">
              </div>

              <div class="row g-3 mb-3">
                <div class="col-sm-6">
                  <label class="form-label">Quantity <span class="text-danger">*</span></label>
                  <input type="number" name="quantity" step="0.01" min="0"
                         class="form-control <?= isset($errors['quantity']) ? 'is-invalid' : '' ?>"
                         value="<?= htmlspecialchars($values['quantity']) ?>" required>
                  <?php if (isset($errors['quantity'])): ?>
                    <div class="invalid-feedback"><?= $errors['quantity'] ?></div>
                  <?php endif; ?>
                </div>
                <div class="col-sm-6">
                  <label class="form-label">Unit <span class="text-danger">*</span></label>
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

              <div class="mb-3">
                <label class="form-label">
                  Low Stock Threshold <span class="text-danger">*</span>
                </label>
                <input type="number" name="low_stock_threshold"
                       step="0.01" min="0"
                       class="form-control <?= isset($errors['low_stock_threshold']) ? 'is-invalid' : '' ?>"
                       value="<?= htmlspecialchars($values['low_stock_threshold']) ?>" required>
                <div class="form-text">SMS alert is sent to owner when stock reaches this level.</div>
                <?php if (isset($errors['low_stock_threshold'])): ?>
                  <div class="invalid-feedback"><?= $errors['low_stock_threshold'] ?></div>
                <?php endif; ?>
              </div>

              <div class="mb-4">
                <label class="form-label">Description</label>
                <textarea name="description" class="form-control" rows="2"
                          ><?= htmlspecialchars($values['description']) ?></textarea>
              </div>

              <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary px-4">
                  <i class="bi bi-check-lg me-1"></i> Save Changes
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