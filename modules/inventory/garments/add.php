<?php
// ============================================================
// modules/inventory/garments/add.php
// Add new garment item — admin only
// ============================================================

require_once __DIR__ . '/../../../../includes/auth-guard.php';
require_once __DIR__ . '/../../../../includes/role-guard.php';
require_once __DIR__ . '/../../../../config/db.php';
require_once __DIR__ . '/../../../../helpers/audit-helper.php';

$pageTitle  = 'Add Garment';
$activePage = 'inventory-garments';

$categories = $pdo->query('
    SELECT * FROM garment_categories ORDER BY category_name ASC
')->fetchAll();

$errors = [];
$values = [
    'category_id'         => '',
    'garment_name'        => '',
    'garment_code'        => '',
    'size'                => '',
    'color'               => '',
    'description'         => '',
    'condition_status'    => 'Good',
    'availability_status' => 'Available',
    'rental_price'        => '',
    'sale_price'          => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $values = [
        'category_id'         => trim($_POST['category_id']         ?? ''),
        'garment_name'        => trim($_POST['garment_name']        ?? ''),
        'garment_code'        => strtoupper(trim($_POST['garment_code'] ?? '')),
        'size'                => trim($_POST['size']                ?? ''),
        'color'               => trim($_POST['color']               ?? ''),
        'description'         => trim($_POST['description']         ?? ''),
        'condition_status'    => trim($_POST['condition_status']    ?? 'Good'),
        'availability_status' => trim($_POST['availability_status'] ?? 'Available'),
        'rental_price'        => trim($_POST['rental_price']        ?? ''),
        'sale_price'          => trim($_POST['sale_price']          ?? ''),
    ];

    // Validate
    if (!$values['category_id'])
        $errors['category_id'] = 'Please select a category.';
    if ($values['garment_name'] === '')
        $errors['garment_name'] = 'Garment name is required.';
    if ($values['garment_code'] === '')
        $errors['garment_code'] = 'Garment code is required.';
    else {
        $dup = $pdo->prepare('SELECT COUNT(*) FROM garment_items WHERE garment_code = ?');
        $dup->execute([$values['garment_code']]);
        if ($dup->fetchColumn() > 0)
            $errors['garment_code'] = 'This garment code is already taken.';
    }
    if ($values['rental_price'] !== '' && (!is_numeric($values['rental_price']) || (float)$values['rental_price'] < 0))
        $errors['rental_price'] = 'Rental price must be a valid amount.';
    if ($values['sale_price'] !== '' && (!is_numeric($values['sale_price']) || (float)$values['sale_price'] < 0))
        $errors['sale_price'] = 'Sale price must be a valid amount.';

    if (empty($errors)) {
        $pdo->prepare('
            INSERT INTO garment_items
                (category_id, garment_name, garment_code, size, color,
                 description, condition_status, availability_status,
                 rental_price, sale_price)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ')->execute([
            (int)$values['category_id'],
            $values['garment_name'],
            $values['garment_code'],
            $values['size']        ?: null,
            $values['color']       ?: null,
            $values['description'] ?: null,
            $values['condition_status'],
            $values['availability_status'],
            $values['rental_price'] !== '' ? (float)$values['rental_price'] : null,
            $values['sale_price']   !== '' ? (float)$values['sale_price']   : null,
        ]);

        $newId = (int)$pdo->lastInsertId();
        auditCreate($_SESSION['user_id'], 'Inventory', 'garment_items', $newId, $values);

        $_SESSION['success'] = 'Garment "' . $values['garment_name'] . '" added successfully.';
        header('Location: /tailorshop/modules/inventory/garments/index.php');
        exit;
    }
}

$conditions    = ['Good','Needs Cleaning','Needs Repair','Damaged'];
$availStatuses = ['Available','Reserved','Rented Out','Under Repair','Damaged','Sold'];

require_once __DIR__ . '/../../../../includes/header.php';
require_once __DIR__ . '/../../../../includes/sidebar.php';
?>

<div class="main-content">
  <div class="topbar"><span class="topbar-title">Add Garment</span></div>

  <div class="page-body">

    <nav class="breadcrumb-nav mb-3">
      <a href="/tailorshop/modules/inventory/garments/index.php">
        <i class="bi bi-bag-heart me-1"></i>Garments
      </a>
      <span class="mx-2">/</span><span>Add Garment</span>
    </nav>

    <div class="row justify-content-center">
      <div class="col-lg-8">
        <div class="card">
          <div class="card-header">
            <i class="bi bi-plus-circle me-2 text-primary"></i>New Garment Item
          </div>
          <div class="card-body p-4">
            <form method="POST" novalidate>

              <!-- Section: Basic info -->
              <p class="form-section-label">Garment Information</p>
              <div class="row g-3 mb-3">
                <div class="col-sm-6">
                  <label class="form-label">Category <span class="text-danger">*</span></label>
                  <select name="category_id"
                          class="form-select <?= isset($errors['category_id']) ? 'is-invalid':'' ?>">
                    <option value="">— Select category —</option>
                    <?php foreach ($categories as $cat): ?>
                      <option value="<?= $cat['category_id'] ?>"
                        <?= $values['category_id'] == $cat['category_id'] ? 'selected':'' ?>>
                        <?= htmlspecialchars($cat['category_name']) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                  <?php if (isset($errors['category_id'])): ?>
                    <div class="invalid-feedback"><?= $errors['category_id'] ?></div>
                  <?php endif; ?>
                </div>
                <div class="col-sm-6">
                  <label class="form-label">Garment Code <span class="text-danger">*</span></label>
                  <input type="text" name="garment_code"
                         class="form-control <?= isset($errors['garment_code']) ? 'is-invalid':'' ?>"
                         placeholder="e.g. WG-001"
                         value="<?= htmlspecialchars($values['garment_code']) ?>"
                         style="font-family:monospace" required>
                  <?php if (isset($errors['garment_code'])): ?>
                    <div class="invalid-feedback"><?= $errors['garment_code'] ?></div>
                  <?php endif; ?>
                  <div class="form-text">Unique identifier. Auto-uppercased.</div>
                </div>
              </div>

              <div class="mb-3">
                <label class="form-label">Garment Name <span class="text-danger">*</span></label>
                <input type="text" name="garment_name"
                       class="form-control <?= isset($errors['garment_name']) ? 'is-invalid':'' ?>"
                       placeholder="e.g. Classic White Ball Gown"
                       value="<?= htmlspecialchars($values['garment_name']) ?>" required>
                <?php if (isset($errors['garment_name'])): ?>
                  <div class="invalid-feedback"><?= $errors['garment_name'] ?></div>
                <?php endif; ?>
              </div>

              <div class="row g-3 mb-3">
                <div class="col-sm-6">
                  <label class="form-label">Size</label>
                  <input type="text" name="size" class="form-control"
                         placeholder="e.g. S, M, L, 4T"
                         value="<?= htmlspecialchars($values['size']) ?>">
                </div>
                <div class="col-sm-6">
                  <label class="form-label">Color</label>
                  <input type="text" name="color" class="form-control"
                         placeholder="e.g. White, Ivory"
                         value="<?= htmlspecialchars($values['color']) ?>">
                </div>
              </div>

              <div class="mb-4">
                <label class="form-label">Description</label>
                <textarea name="description" class="form-control" rows="2"
                          placeholder="Optional details about the garment…"
                          ><?= htmlspecialchars($values['description']) ?></textarea>
              </div>

              <hr class="my-4">

              <!-- Section: Pricing -->
              <p class="form-section-label">Pricing</p>
              <div class="row g-3 mb-4">
                <div class="col-sm-6">
                  <label class="form-label">Rental Price (₱)</label>
                  <input type="number" name="rental_price" step="0.01" min="0"
                         class="form-control <?= isset($errors['rental_price']) ? 'is-invalid':'' ?>"
                         placeholder="0.00"
                         value="<?= htmlspecialchars($values['rental_price']) ?>">
                  <?php if (isset($errors['rental_price'])): ?>
                    <div class="invalid-feedback"><?= $errors['rental_price'] ?></div>
                  <?php endif; ?>
                </div>
                <div class="col-sm-6">
                  <label class="form-label">Sale Price (₱)</label>
                  <input type="number" name="sale_price" step="0.01" min="0"
                         class="form-control <?= isset($errors['sale_price']) ? 'is-invalid':'' ?>"
                         placeholder="0.00"
                         value="<?= htmlspecialchars($values['sale_price']) ?>">
                  <?php if (isset($errors['sale_price'])): ?>
                    <div class="invalid-feedback"><?= $errors['sale_price'] ?></div>
                  <?php endif; ?>
                </div>
              </div>

              <hr class="my-4">

              <!-- Section: Status -->
              <p class="form-section-label">Status</p>
              <div class="row g-3 mb-4">
                <div class="col-sm-6">
                  <label class="form-label">Condition</label>
                  <select name="condition_status" class="form-select">
                    <?php foreach ($conditions as $c): ?>
                      <option value="<?= $c ?>"
                        <?= $values['condition_status'] === $c ? 'selected':'' ?>>
                        <?= $c ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-sm-6">
                  <label class="form-label">Availability</label>
                  <select name="availability_status" class="form-select">
                    <?php foreach ($availStatuses as $a): ?>
                      <option value="<?= $a ?>"
                        <?= $values['availability_status'] === $a ? 'selected':'' ?>>
                        <?= $a ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>

              <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary px-4">
                  <i class="bi bi-plus-circle me-1"></i> Add Garment
                </button>
                <a href="/tailorshop/modules/inventory/garments/index.php"
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