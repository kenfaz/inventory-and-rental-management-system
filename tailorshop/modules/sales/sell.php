<?php
// ============================================================
// modules/sales/sell.php
// Sell garment and material inventory
// ============================================================

require_once __DIR__ . '/../../includes/auth-guard.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../helpers/sales-helper.php';

$pageTitle  = 'Sell Items';
$activePage = 'sell-items';

$errors = [];
$values = [
    'customer_id'   => '',
    'product'       => '',
    'quantity'      => '1',
    'payment_method'=> 'Cash',
    'payment_other' => '',
    'notes'         => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($values as $key => $default) {
        $values[$key] = trim($_POST[$key] ?? $default);
    }

    $customerId = filter_var($values['customer_id'], FILTER_VALIDATE_INT);
    $quantity = filter_var($values['quantity'], FILTER_VALIDATE_FLOAT);
    $productParts = explode(':', $values['product'], 2);
    $productType = $productParts[0] ?? '';
    $productId = isset($productParts[1]) ? filter_var($productParts[1], FILTER_VALIDATE_INT) : false;
    $validPayments = ['Cash', 'GCash', 'Card', 'Other'];

    if (!$customerId || $customerId < 1) {
        $errors['customer_id'] = 'Select a customer.';
    }
    if (!in_array($productType, ['garment', 'material'], true) || !$productId || $productId < 1) {
        $errors['product'] = 'Select a garment or material item.';
    }
    if (!preg_match('/^\d+(?:\.\d{1,2})?$/', $values['quantity']) ||
        $quantity === false || $quantity <= 0) {
        $errors['quantity'] = 'Enter a quantity greater than zero, using up to 2 decimal places.';
    }
    if (!in_array($values['payment_method'], $validPayments, true)) {
        $errors['payment_method'] = 'Select a valid payment method.';
    }
    if ($values['payment_method'] === 'Other' && $values['payment_other'] === '') {
        $errors['payment_other'] = 'Specify the payment method.';
    }

    if (!$errors) {
        $customerStmt = $pdo->prepare('SELECT customer_id FROM customers WHERE customer_id = ?');
        $customerStmt->execute([$customerId]);
        if (!$customerStmt->fetchColumn()) {
            $errors['customer_id'] = 'The selected customer was not found.';
        }
    }

    if (!$errors) {
        try {
            $pdo->beginTransaction();

            if ($productType === 'garment') {
                if ((float)$quantity !== 1.0) {
                    throw new DomainException('Garments are sold one item at a time.');
                }

                $productStmt = $pdo->prepare('
                    SELECT garment_id, garment_name, garment_code, size, color,
                           availability_status, sale_price
                    FROM garment_items
                    WHERE garment_id = ?
                    FOR UPDATE
                ');
                $productStmt->execute([$productId]);
                $product = $productStmt->fetch();

                if (!$product || $product['availability_status'] !== 'Available') {
                    throw new DomainException('This garment is no longer available for sale.');
                }
                if ($product['sale_price'] === null || (float)$product['sale_price'] <= 0) {
                    throw new DomainException('Set a sale price for this garment before selling it.');
                }

                $saleType = 'Garment Sale';
                $amount = (float)$product['sale_price'];
                $description = $product['garment_name'] . ' (' . $product['garment_code'] . ')';
                if ($product['size'] !== null && $product['size'] !== '') {
                    $description .= ', size ' . $product['size'];
                }

                $updateStock = $pdo->prepare("
                    UPDATE garment_items
                    SET availability_status = 'Sold'
                    WHERE garment_id = ? AND availability_status = 'Available'
                ");
                $updateStock->execute([$productId]);
                if ($updateStock->rowCount() !== 1) {
                    throw new DomainException('This garment is no longer available for sale.');
                }

                $garmentId = (int)$productId;
                $materialId = null;
            } else {
                $productStmt = $pdo->prepare('
                    SELECT item_id, item_name, brand, quantity, unit, sale_price
                    FROM material_items
                    WHERE item_id = ?
                    FOR UPDATE
                ');
                $productStmt->execute([$productId]);
                $product = $productStmt->fetch();

                if (!$product || (float)$product['quantity'] < $quantity) {
                    throw new DomainException('There is not enough material stock for this sale.');
                }
                if ($product['sale_price'] === null || (float)$product['sale_price'] <= 0) {
                    throw new DomainException('Set a sale price for this material before selling it.');
                }

                $saleType = 'Material Sale';
                $amount = round((float)$product['sale_price'] * $quantity, 2);
                if ($amount > 99999999.99) {
                    throw new DomainException('The sale total exceeds the supported amount.');
                }
                $description = $product['item_name'] . ' × ' .
                    rtrim(rtrim(number_format($quantity, 2), '0'), '.') . ' ' . $product['unit'];
                if ($product['brand']) {
                    $description .= ' (' . $product['brand'] . ')';
                }

                $updateStock = $pdo->prepare('
                    UPDATE material_items
                    SET quantity = quantity - ?
                    WHERE item_id = ? AND quantity >= ?
                ');
                $updateStock->execute([$quantity, $productId, $quantity]);
                if ($updateStock->rowCount() !== 1) {
                    throw new DomainException('There is not enough material stock for this sale.');
                }

                $garmentId = null;
                $materialId = (int)$productId;
            }

            $notes = $description;
            if ($values['notes'] !== '') {
                $notes .= ' — ' . $values['notes'];
            }

            recordSale(
                (int)$customerId,
                (int)$_SESSION['user_id'],
                $saleType,
                $amount,
                $values['payment_method'],
                $garmentId,
                null,
                null,
                $values['payment_method'] === 'Other' ? $values['payment_other'] : null,
                $notes,
                $materialId
            );

            $pdo->commit();
            $_SESSION['success'] = 'Sale recorded successfully.';
            header('Location: /tailorshop/modules/sales/sell.php');
            exit;
        } catch (DomainException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors['general'] = $e->getMessage();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Inventory sale error: ' . $e->getMessage());
            $errors['general'] = 'The sale could not be completed. Please try again.';
        }
    }
}

$customers = $pdo->query('
    SELECT customer_id, full_name, mobile_number
    FROM customers
    ORDER BY full_name
')->fetchAll();
$garments = $pdo->query("
    SELECT garment_id, garment_name, garment_code, size, color, sale_price
    FROM garment_items
    WHERE availability_status = 'Available' AND sale_price > 0
    ORDER BY garment_name
")->fetchAll();
$materials = $pdo->query('
    SELECT item_id, item_name, brand, quantity, unit, sale_price
    FROM material_items
    WHERE quantity > 0 AND sale_price > 0
    ORDER BY item_name
')->fetchAll();
$success = $_SESSION['success'] ?? '';
unset($_SESSION['success']);

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="main-content">
  <div class="topbar">
    <span class="topbar-title">Sell Items</span>
    <span class="text-muted small" id="current-date"></span>
  </div>

  <div class="page-body">
    <?php if ($success): ?>
      <div class="alert alert-success alert-dismissible fade show d-flex align-items-center gap-2">
        <i class="bi bi-check-circle-fill"></i><?= htmlspecialchars($success) ?>
        <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
      </div>
    <?php endif; ?>

    <?php if (isset($errors['general'])): ?>
      <div class="alert alert-danger d-flex align-items-center gap-2">
        <i class="bi bi-exclamation-circle-fill"></i>
        <?= htmlspecialchars($errors['general']) ?>
      </div>
    <?php endif; ?>

    <div class="page-header">
      <div>
        <h5>Sell Garments or Materials</h5>
        <p class="text-muted small mb-0">Complete a sale using the catalog price; inventory and income reports update automatically.</p>
      </div>
      <?php if (($_SESSION['user_role'] ?? '') === 'admin'): ?>
        <div class="d-flex gap-2">
          <a href="/tailorshop/modules/inventory/garments/index.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-bag-heart me-1"></i> Garment Prices
          </a>
          <a href="/tailorshop/modules/inventory/materials/index.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-box-seam me-1"></i> Material Prices
          </a>
        </div>
      <?php endif; ?>
    </div>

    <div class="row justify-content-center">
      <div class="col-lg-8">
        <div class="card">
          <div class="card-header"><i class="bi bi-cart-plus me-2 text-primary"></i>Sale Details</div>
          <div class="card-body p-4">
            <form method="POST" novalidate>
              <div class="mb-3">
                <label class="form-label" for="customer_id">Customer <span class="text-danger">*</span></label>
                <select class="form-select <?= isset($errors['customer_id']) ? 'is-invalid' : '' ?>"
                        id="customer_id" name="customer_id" required>
                  <option value="">Select a customer</option>
                  <?php foreach ($customers as $customer): ?>
                    <option value="<?= $customer['customer_id'] ?>"
                      <?= $values['customer_id'] === (string)$customer['customer_id'] ? 'selected' : '' ?>>
                      <?= htmlspecialchars($customer['full_name'] . ' — ' . $customer['mobile_number']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
                <?php if (isset($errors['customer_id'])): ?>
                  <div class="invalid-feedback"><?= htmlspecialchars($errors['customer_id']) ?></div>
                <?php endif; ?>
              </div>

              <div class="mb-3">
                <label class="form-label" for="product">Garment or Material <span class="text-danger">*</span></label>
                <select class="form-select <?= isset($errors['product']) ? 'is-invalid' : '' ?>"
                        id="product" name="product" required>
                  <option value="">Select an item</option>
                  <?php if ($garments): ?>
                    <optgroup label="Garments">
                      <?php foreach ($garments as $garment):
                        $productValue = 'garment:' . $garment['garment_id'];
                        $details = $garment['garment_code'] . ' · ₱' . number_format((float)$garment['sale_price'], 2);
                        if ($garment['size']) $details .= ' · Size ' . $garment['size'];
                      ?>
                        <option value="<?= $productValue ?>" data-type="garment" data-price="<?= htmlspecialchars((string)$garment['sale_price']) ?>"
                          <?= $values['product'] === $productValue ? 'selected' : '' ?>>
                          <?= htmlspecialchars($garment['garment_name'] . ' — ' . $details) ?>
                        </option>
                      <?php endforeach; ?>
                    </optgroup>
                  <?php endif; ?>
                  <?php if ($materials): ?>
                    <optgroup label="Materials">
                      <?php foreach ($materials as $material):
                        $productValue = 'material:' . $material['item_id'];
                        $details = rtrim(rtrim(number_format((float)$material['quantity'], 2), '0'), '.') .
                            ' ' . $material['unit'] . ' available · ₱' . number_format((float)$material['sale_price'], 2) . '/' . $material['unit'];
                      ?>
                        <option value="<?= $productValue ?>" data-type="material" data-price="<?= htmlspecialchars((string)$material['sale_price']) ?>"
                          <?= $values['product'] === $productValue ? 'selected' : '' ?>>
                          <?= htmlspecialchars($material['item_name'] . ' — ' . $details) ?>
                        </option>
                      <?php endforeach; ?>
                    </optgroup>
                  <?php endif; ?>
                </select>
                <?php if (isset($errors['product'])): ?>
                  <div class="invalid-feedback"><?= htmlspecialchars($errors['product']) ?></div>
                <?php endif; ?>
                <?php if (!$garments && !$materials): ?>
                  <div class="form-text">No available items have a sale price. Admins can set prices in inventory.</div>
                <?php endif; ?>
              </div>

              <div class="row g-3 mb-3">
                <div class="col-sm-6">
                  <label class="form-label" for="quantity">Quantity <span class="text-danger">*</span></label>
                  <input type="number" class="form-control <?= isset($errors['quantity']) ? 'is-invalid' : '' ?>"
                         id="quantity" name="quantity" min="0.01" step="0.01"
                         value="<?= htmlspecialchars($values['quantity']) ?>" required>
                  <?php if (isset($errors['quantity'])): ?>
                    <div class="invalid-feedback"><?= htmlspecialchars($errors['quantity']) ?></div>
                  <?php endif; ?>
                  <div class="form-text">Garments are sold one at a time.</div>
                </div>
                <div class="col-sm-6">
                  <label class="form-label" for="payment_method">Payment Method <span class="text-danger">*</span></label>
                  <select class="form-select" id="payment_method" name="payment_method" required>
                    <?php foreach (['Cash', 'GCash', 'Card', 'Other'] as $method): ?>
                      <option value="<?= $method ?>" <?= $values['payment_method'] === $method ? 'selected' : '' ?>>
                        <?= $method ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>

              <div class="mb-3" id="payment-other-field" style="display:<?= $values['payment_method'] === 'Other' ? 'block' : 'none' ?>">
                <label class="form-label" for="payment_other">Other payment method</label>
                <input type="text" class="form-control <?= isset($errors['payment_other']) ? 'is-invalid' : '' ?>"
                       id="payment_other" name="payment_other" maxlength="100"
                       value="<?= htmlspecialchars($values['payment_other']) ?>">
                <?php if (isset($errors['payment_other'])): ?>
                  <div class="invalid-feedback"><?= htmlspecialchars($errors['payment_other']) ?></div>
                <?php endif; ?>
              </div>

              <div class="mb-4">
                <label class="form-label" for="notes">Notes</label>
                <textarea class="form-control" id="notes" name="notes" rows="2"
                          maxlength="500"><?= htmlspecialchars($values['notes']) ?></textarea>
              </div>

              <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap">
                <div class="fw-semibold">Total: <span id="sale-total">₱0.00</span></div>
                <button type="submit" class="btn btn-primary px-4">
                  <i class="bi bi-check-lg me-1"></i> Complete Sale
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const product = document.getElementById('product');
  const quantity = document.getElementById('quantity');
  const total = document.getElementById('sale-total');
  const updateTotal = () => {
    const selected = product.options[product.selectedIndex];
    const unitPrice = selected ? Number(selected.dataset.price || 0) : 0;
    const count = selected && selected.dataset.type === 'garment' ? 1 : Number(quantity.value || 0);
    total.textContent = formatPeso(unitPrice * count);
    if (selected && selected.dataset.type === 'garment') {
      quantity.value = '1';
      quantity.readOnly = true;
    } else {
      quantity.readOnly = false;
    }
  };
  product.addEventListener('change', updateTotal);
  quantity.addEventListener('input', updateTotal);
  updateTotal();
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
