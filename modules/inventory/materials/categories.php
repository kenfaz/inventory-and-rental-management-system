<?php
// ============================================================
// modules/inventory/materials/categories.php
// Manage material categories — admin only
// ============================================================

require_once __DIR__ . '/../../../../includes/auth-guard.php';
require_once __DIR__ . '/../../../../includes/role-guard.php';
require_once __DIR__ . '/../../../../config/db.php';
require_once __DIR__ . '/../../../../helpers/audit-helper.php';

$pageTitle  = 'Material Categories';
$activePage = 'inventory-materials';

$errors  = [];
$success = $_SESSION['success'] ?? '';
$error   = $_SESSION['error']   ?? '';
unset($_SESSION['success'], $_SESSION['error']);

// ── Handle POST actions ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Add category
    if ($action === 'add') {
        $name = trim($_POST['category_name'] ?? '');
        $unit = trim($_POST['default_unit']  ?? 'piece');
        $desc = trim($_POST['description']   ?? '');

        if ($name === '') {
            $errors['name'] = 'Category name is required.';
        } else {
            // Check duplicate
            $dup = $pdo->prepare('SELECT COUNT(*) FROM material_categories WHERE category_name = ?');
            $dup->execute([$name]);
            if ($dup->fetchColumn() > 0) {
                $errors['name'] = 'A category with this name already exists.';
            }
        }

        if (empty($errors)) {
            $pdo->prepare('
                INSERT INTO material_categories (category_name, default_unit, description)
                VALUES (?, ?, ?)
            ')->execute([$name, $unit, $desc ?: null]);

            $newId = (int)$pdo->lastInsertId();
            auditCreate($_SESSION['user_id'], 'Inventory', 'material_categories', $newId, [
                'category_name' => $name,
                'default_unit'  => $unit,
            ]);

            $_SESSION['success'] = 'Category "' . $name . '" added.';
            header('Location: /tailorshop/modules/inventory/materials/categories.php');
            exit;
        }
    }

    // Delete category
    if ($action === 'delete') {
        $catId = (int)($_POST['category_id'] ?? 0);

        // Block if items exist in this category
        $inUse = $pdo->prepare('SELECT COUNT(*) FROM material_items WHERE category_id = ?');
        $inUse->execute([$catId]);
        if ($inUse->fetchColumn() > 0) {
            $_SESSION['error'] = 'Cannot delete this category — it has items assigned to it.';
        } else {
            $stmt = $pdo->prepare('SELECT category_name FROM material_categories WHERE category_id = ?');
            $stmt->execute([$catId]);
            $cat = $stmt->fetch();
            if ($cat) {
                auditDelete($_SESSION['user_id'], 'Inventory', 'material_categories', $catId, $cat);
                $pdo->prepare('DELETE FROM material_categories WHERE category_id = ?')->execute([$catId]);
                $_SESSION['success'] = 'Category "' . $cat['category_name'] . '" deleted.';
            }
        }
        header('Location: /tailorshop/modules/inventory/materials/categories.php');
        exit;
    }
}

// Fetch categories with item count
$categories = $pdo->query('
    SELECT mc.*, COUNT(mi.item_id) AS item_count
    FROM material_categories mc
    LEFT JOIN material_items mi ON mc.category_id = mi.category_id
    GROUP BY mc.category_id
    ORDER BY mc.category_name ASC
')->fetchAll();

$units = ['piece','meter','yard','spool','roll','pack','box','set','gram','liter'];

require_once __DIR__ . '/../../../../includes/header.php';
require_once __DIR__ . '/../../../../includes/sidebar.php';
?>

<div class="main-content">
  <div class="topbar">
    <span class="topbar-title">Material Categories</span>
  </div>

  <div class="page-body">

    <nav class="breadcrumb-nav mb-3">
      <a href="/tailorshop/modules/inventory/materials/index.php">
        <i class="bi bi-box-seam me-1"></i>Materials
      </a>
      <span class="mx-2">/</span>
      <span>Categories</span>
    </nav>

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

    <div class="row g-3">

      <!-- Add category form -->
      <div class="col-lg-4">
        <div class="card">
          <div class="card-header">
            <i class="bi bi-plus-circle me-2 text-primary"></i>Add Category
          </div>
          <div class="card-body p-4">
            <form method="POST" novalidate>
              <input type="hidden" name="action" value="add">

              <div class="mb-3">
                <label class="form-label">
                  Category Name <span class="text-danger">*</span>
                </label>
                <input type="text" name="category_name"
                       class="form-control <?= isset($errors['name']) ? 'is-invalid' : '' ?>"
                       placeholder="e.g. Fabrics"
                       value="<?= htmlspecialchars($_POST['category_name'] ?? '') ?>">
                <?php if (isset($errors['name'])): ?>
                  <div class="invalid-feedback"><?= $errors['name'] ?></div>
                <?php endif; ?>
              </div>

              <div class="mb-3">
                <label class="form-label">Default Unit</label>
                <select name="default_unit" class="form-select">
                  <?php foreach ($units as $u): ?>
                    <option value="<?= $u ?>"
                      <?= ($_POST['default_unit'] ?? 'piece') === $u ? 'selected' : '' ?>>
                      <?= ucfirst($u) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
                <div class="form-text">Used as the suggested unit when adding items.</div>
              </div>

              <div class="mb-4">
                <label class="form-label">Description</label>
                <textarea name="description" class="form-control" rows="2"
                          placeholder="Optional…"
                          ><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
              </div>

              <button type="submit" class="btn btn-primary w-100">
                <i class="bi bi-plus-lg me-1"></i> Add Category
              </button>
            </form>
          </div>
        </div>
      </div>

      <!-- Category list -->
      <div class="col-lg-8">
        <div class="card">
          <div class="card-header">
            <i class="bi bi-tags me-2 text-primary"></i>
            All Categories
            <span class="badge bg-secondary ms-1"><?= count($categories) ?></span>
          </div>
          <div class="table-responsive">
            <table class="table mb-0">
              <thead>
                <tr>
                  <th>Category Name</th>
                  <th>Default Unit</th>
                  <th>Description</th>
                  <th>Items</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($categories)): ?>
                  <tr>
                    <td colspan="5" class="text-center text-muted py-4">
                      No categories yet.
                    </td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($categories as $cat): ?>
                    <tr>
                      <td class="fw-medium">
                        <?= htmlspecialchars($cat['category_name']) ?>
                      </td>
                      <td><?= htmlspecialchars($cat['default_unit']) ?></td>
                      <td class="text-muted" style="font-size:12.5px">
                        <?= htmlspecialchars($cat['description'] ?? '—') ?>
                      </td>
                      <td>
                        <span class="badge bg-secondary rounded-pill">
                          <?= $cat['item_count'] ?>
                        </span>
                      </td>
                      <td>
                        <?php if ($cat['item_count'] == 0): ?>
                          <form method="POST" class="d-inline">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="category_id"
                                   value="<?= $cat['category_id'] ?>">
                            <button type="submit"
                                    class="btn btn-sm btn-outline-danger"
                                    data-confirm="Delete &quot;<?= htmlspecialchars($cat['category_name']) ?>&quot;?">
                              <i class="bi bi-trash"></i>
                            </button>
                          </form>
                        <?php else: ?>
                          <span class="text-muted small">In use</span>
                        <?php endif; ?>
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
  </div>
</div>

<?php require_once __DIR__ . '/../../../../includes/footer.php'; ?>