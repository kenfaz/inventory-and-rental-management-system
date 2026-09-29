<?php
// ============================================================
// modules/tailoring/materials.php
// Log materials used in a tailoring order and deduct from stock
// Only available when order is In Progress
// ============================================================

require_once __DIR__ . '/../../includes/auth-guard.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../helpers/audit-helper.php';
require_once __DIR__ . '/../../helpers/inventory-helper.php';

$pageTitle  = 'Log Materials Used';
$activePage = 'tailoring';

$orderId = (int)($_GET['id'] ?? 0);
if ($orderId === 0) {
    header('Location: /tailorshop/modules/tailoring/index.php');
    exit;
}

$stmt = $pdo->prepare('
    SELECT o.*, c.full_name AS customer_name
    FROM tailoring_orders o
    JOIN customers c ON o.customer_id = c.customer_id
    WHERE o.order_id = ?
');
$stmt->execute([$orderId]);
$order = $stmt->fetch();

if (!$order || $order['status'] !== 'In Progress') {
    $_SESSION['error'] = 'Materials can only be logged for orders that are In Progress.';
    header('Location: /tailorshop/modules/tailoring/view.php?id=' . $orderId);
    exit;
}

// Fetch in-stock materials for dropdown
$inventory = $pdo->query("
    SELECT mi.item_id, mi.item_name, mi.brand, mi.quantity, mi.unit,
           mc.category_name
    FROM material_items mi
    JOIN material_categories mc ON mi.category_id = mc.category_id
    WHERE mi.quantity > 0
    ORDER BY mc.category_name ASC, mi.item_name ASC
")->fetchAll();

// Fetch already logged materials for this order
$logged = $pdo->prepare('
    SELECT omu.*, mi.item_name, mi.brand, mi.unit
    FROM order_materials_used omu
    JOIN material_items mi ON omu.item_id = mi.item_id
    WHERE omu.order_id = ?
');
$logged->execute([$orderId]);
$loggedMaterials = $logged->fetchAll();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $itemIds   = $_POST['item_id']       ?? [];
    $quantities = $_POST['quantity_used'] ?? [];

    if (empty($itemIds)) {
        $errors[] = 'Please add at least one material.';
    }

    $rows = [];
    foreach ($itemIds as $i => $itemId) {
        $itemId = (int)$itemId;
        $qty    = (float)($quantities[$i] ?? 0);

        if ($itemId === 0 || $qty <= 0) {
            $errors[] = 'Each row must have a valid item and quantity greater than zero.';
            break;
        }

        $check = $pdo->prepare('SELECT quantity, item_name, unit FROM material_items WHERE item_id = ?');
        $check->execute([$itemId]);
        $invItem = $check->fetch();

        if (!$invItem) {
            $errors[] = 'One of the selected items does not exist.';
            break;
        }
        if ($qty > $invItem['quantity']) {
            $errors[] = '"' . $invItem['item_name'] . '" only has ' .
                        rtrim(rtrim(number_format($invItem['quantity'], 2), '0'), '.') .
                        ' ' . $invItem['unit'] . '(s) available.';
            break;
        }
        $rows[] = ['item_id' => $itemId, 'qty' => $qty, 'unit' => $invItem['unit']];
    }

    if (empty($errors)) {
        $pdo->beginTransaction();
        try {
            foreach ($rows as $row) {
                // Insert materials used record
                $pdo->prepare('
                    INSERT INTO order_materials_used
                        (order_id, item_id, quantity_used, unit)
                    VALUES (?, ?, ?, ?)
                ')->execute([$orderId, $row['item_id'], $row['qty'], $row['unit']]);

                // Deduct from inventory
                $result = deductMaterial($row['item_id'], $row['qty'], $_SESSION['user_id']);
                if (!$result['success']) {
                    throw new Exception($result['message']);
                }
            }
            $pdo->commit();

            auditCreate($_SESSION['user_id'], 'Tailoring Orders',
                        'order_materials_used', $orderId, [
                            'materials_logged' => count($rows)
                        ]);

            $_SESSION['success'] = count($rows) . ' material(s) logged and inventory updated.';
            header('Location: /tailorshop/modules/tailoring/view.php?id=' . $orderId);
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = $e->getMessage();
        }
    }
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="main-content">
  <div class="topbar"><span class="topbar-title">Log Materials Used</span></div>

  <div class="page-body">

    <nav class="breadcrumb-nav mb-3">
      <a href="/tailorshop/modules/tailoring/index.php">
        <i class="bi bi-scissors me-1"></i>Tailoring Orders
      </a>
      <span class="mx-2">/</span>
      <a href="/tailorshop/modules/tailoring/view.php?id=<?= $orderId ?>">
        #<?= str_pad($orderId, 4, '0', STR_PAD_LEFT) ?>
      </a>
      <span class="mx-2">/</span><span>Log Materials</span>
    </nav>

    <!-- Order summary -->
    <div class="alert alert-secondary d-flex gap-2 align-items-center mb-3 py-2">
      <i class="bi bi-info-circle text-primary"></i>
      <div style="font-size:13.5px">
        <strong><?= htmlspecialchars($order['order_name']) ?></strong>
        — <?= htmlspecialchars($order['customer_name']) ?>
      </div>
    </div>

    <?php if (!empty($errors)): ?>
      <div class="alert alert-danger">
        <ul class="mb-0 ps-3">
          <?php foreach ($errors as $e): ?>
            <li><?= htmlspecialchars($e) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <div class="row g-3">
      <div class="col-lg-7">

        <!-- Add materials form -->
        <div class="card mb-3">
          <div class="card-header">
            <i class="bi bi-plus-circle me-2 text-warning"></i>Add Materials
          </div>
          <div class="card-body p-4">
            <form method="POST" id="materials-form" novalidate>

              <div id="materials-container">
                <div class="material-row row g-2 align-items-end mb-3">
                  <div class="col-sm-7">
                    <label class="form-label">Material <span class="text-danger">*</span></label>
                    <select name="item_id[]" class="form-select item-select" required>
                      <option value="">— Select material —</option>
                      <?php foreach ($inventory as $inv): ?>
                        <option value="<?= $inv['item_id'] ?>"
                                data-qty="<?= $inv['quantity'] ?>"
                                data-unit="<?= htmlspecialchars($inv['unit']) ?>">
                          <?= htmlspecialchars($inv['item_name']) ?>
                          <?= $inv['brand'] ? '(' . htmlspecialchars($inv['brand']) . ')' : '' ?>
                          — <?= rtrim(rtrim(number_format($inv['quantity'], 2), '0'), '.') ?>
                          <?= $inv['unit'] ?>(s)
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div class="col-sm-3">
                    <label class="form-label">Quantity <span class="text-danger">*</span></label>
                    <input type="number" name="quantity_used[]"
                           class="form-control qty-input"
                           step="0.5" min="0.5" placeholder="0" required>
                  </div>
                  <div class="col-sm-2">
                    <button type="button"
                            class="btn btn-outline-danger btn-sm w-100 remove-row" disabled>
                      <i class="bi bi-trash"></i>
                    </button>
                  </div>
                </div>
              </div>

              <button type="button" id="add-row"
                      class="btn btn-outline-secondary btn-sm mb-4">
                <i class="bi bi-plus-lg me-1"></i> Add Another Material
              </button>

              <div class="d-flex gap-2">
                <button type="submit" class="btn btn-warning px-4 fw-medium">
                  <i class="bi bi-check-lg me-1"></i> Save and Deduct Inventory
                </button>
                <a href="/tailorshop/modules/tailoring/view.php?id=<?= $orderId ?>"
                   class="btn btn-outline-secondary px-4">Cancel</a>
              </div>

            </form>
          </div>
        </div>

        <!-- Already logged -->
        <?php if (!empty($loggedMaterials)): ?>
          <div class="card">
            <div class="card-header">
              <i class="bi bi-list-check me-2 text-success"></i>
              Already Logged
              <span class="badge bg-secondary ms-1"><?= count($loggedMaterials) ?></span>
            </div>
            <div class="table-responsive">
              <table class="table mb-0">
                <thead>
                  <tr><th>Item</th><th>Brand</th><th>Qty Used</th><th>Unit</th></tr>
                </thead>
                <tbody>
                  <?php foreach ($loggedMaterials as $lm): ?>
                    <tr>
                      <td class="fw-medium"><?= htmlspecialchars($lm['item_name']) ?></td>
                      <td><?= htmlspecialchars($lm['brand'] ?? '—') ?></td>
                      <td><?= rtrim(rtrim(number_format($lm['quantity_used'], 2), '0'), '.') ?></td>
                      <td><?= htmlspecialchars($lm['unit']) ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        <?php endif; ?>

      </div>

      <!-- Stock reference -->
      <div class="col-lg-5">
        <div class="card">
          <div class="card-header">
            <i class="bi bi-box-seam me-2 text-muted"></i>Stock Reference
          </div>
          <div class="table-responsive">
            <table class="table mb-0">
              <thead>
                <tr><th>Item</th><th>In Stock</th><th>Unit</th></tr>
              </thead>
              <tbody>
                <?php foreach ($inventory as $inv): ?>
                  <tr>
                    <td style="font-size:12.5px"><?= htmlspecialchars($inv['item_name']) ?></td>
                    <td>
                      <span class="<?= $inv['quantity'] <= 5
                          ? 'text-warning fw-semibold'
                          : 'text-success fw-semibold' ?>">
                        <?= rtrim(rtrim(number_format($inv['quantity'], 2), '0'), '.') ?>
                      </span>
                    </td>
                    <td class="text-muted"><?= htmlspecialchars($inv['unit']) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

    </div>
  </div>
</div>

<script src="/tailorshop/assets/js/materials-selector.js"></script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>