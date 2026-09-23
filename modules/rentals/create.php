<?php
// ============================================================
// modules/rentals/create.php
// Create a new rental transaction directly (no reservation)
// ============================================================

require_once __DIR__ . '/../../../includes/auth-guard.php';
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../config/constants.php';
require_once __DIR__ . '/../../../helpers/audit-helper.php';
require_once __DIR__ . '/../../../helpers/inventory-helper.php';

$pageTitle  = 'New Rental';
$activePage = 'rentals';

$preGarmentId  = (int)($_GET['garment_id']  ?? 0);
$preCustomerId = (int)($_GET['customer_id'] ?? 0);

$customers = $pdo->query('
    SELECT customer_id, full_name, mobile_number
    FROM customers ORDER BY full_name ASC
')->fetchAll();

$garments = $pdo->query("
    SELECT garment_id, garment_name, garment_code, rental_price
    FROM garment_items
    WHERE availability_status = 'Available'
    ORDER BY garment_name ASC
")->fetchAll();

$errors = [];
$values = [
    'customer_id'          => $preCustomerId ?: '',
    'garment_id'           => $preGarmentId  ?: '',
    'rental_date'          => date('Y-m-d'),
    'expected_return_date' => '',
    'rental_fee'           => '',
    'deposit_amount'       => '',
    'penalty_rate_per_day' => DEFAULT_PENALTY_RATE,
    'payment_method'       => '',
    'payment_other'        => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $values = [
        'customer_id'          => trim($_POST['customer_id']          ?? ''),
        'garment_id'           => trim($_POST['garment_id']           ?? ''),
        'rental_date'          => trim($_POST['rental_date']          ?? date('Y-m-d')),
        'expected_return_date' => trim($_POST['expected_return_date'] ?? ''),
        'rental_fee'           => trim($_POST['rental_fee']           ?? ''),
        'deposit_amount'       => trim($_POST['deposit_amount']       ?? '0'),
        'penalty_rate_per_day' => trim($_POST['penalty_rate_per_day'] ?? DEFAULT_PENALTY_RATE),
        'payment_method'       => trim($_POST['payment_method']       ?? ''),
        'payment_other'        => trim($_POST['payment_other']        ?? ''),
    ];

    if (!$values['customer_id'])
        $errors['customer_id'] = 'Please select a customer.';
    if (!$values['garment_id'])
        $errors['garment_id'] = 'Please select a garment.';
    if (!is_numeric($values['rental_fee']) || (float)$values['rental_fee'] < 0)
        $errors['rental_fee'] = 'Enter a valid rental fee.';
    if (!is_numeric($values['deposit_amount']) || (float)$values['deposit_amount'] < 0)
        $errors['deposit_amount'] = 'Enter a valid deposit amount.';
    if (!is_numeric($values['penalty_rate_per_day']) || (float)$values['penalty_rate_per_day'] < 0)
        $errors['penalty_rate_per_day'] = 'Enter a valid penalty rate.';
    if ($values['payment_method'] === '')
        $errors['payment_method'] = 'Please select a payment method.';
    if ($values['expected_return_date'] === '')
        $errors['expected_return_date'] = 'Return date is required.';
    elseif ($values['expected_return_date'] <= $values['rental_date'])
        $errors['expected_return_date'] = 'Return date must be after rental date.';

    // Conflict check
    if (empty($errors) && $values['garment_id']) {
        if (!isGarmentAvailable(
            (int)$values['garment_id'],
            $values['rental_date'],
            $values['expected_return_date']
        )) {
            $errors['garment_id'] = 'This garment is not available for the selected dates.';
        }
    }

    if (empty($errors)) {
        $pdo->prepare('
            INSERT INTO rentals
                (customer_id, garment_id, processed_by,
                 rental_date, expected_return_date,
                 rental_fee, deposit_amount, penalty_rate_per_day,
                 payment_method, payment_other, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "Active")
        ')->execute([
            (int)$values['customer_id'],
            (int)$values['garment_id'],
            $_SESSION['user_id'],
            $values['rental_date'],
            $values['expected_return_date'],
            (float)$values['rental_fee'],
            (float)$values['deposit_amount'],
            (float)$values['penalty_rate_per_day'],
            $values['payment_method'],
            $values['payment_other'] ?: null,
        ]);
        $rentalId = (int)$pdo->lastInsertId();

        // Mark garment Rented Out
        $pdo->prepare("
            UPDATE garment_items SET availability_status = 'Rented Out'
            WHERE garment_id = ?
        ")->execute([(int)$values['garment_id']]);

        auditCreate($_SESSION['user_id'], 'Rentals', 'rentals', $rentalId, $values);

        $_SESSION['success'] = 'Rental #' . str_pad($rentalId, 4, '0', STR_PAD_LEFT) .
                               ' created successfully.';
        header('Location: /tailorshop/modules/rentals/view.php?id=' . $rentalId);
        exit;
    }
}

require_once __DIR__ . '/../../../includes/header.php';
require_once __DIR__ . '/../../../includes/sidebar.php';
?>

<div class="main-content">
  <div class="topbar"><span class="topbar-title">New Rental</span></div>

  <div class="page-body">

    <nav class="breadcrumb-nav mb-3">
      <a href="/tailorshop/modules/rentals/index.php">
        <i class="bi bi-handbag me-1"></i>Rentals
      </a>
      <span class="mx-2">/</span><span>New Rental</span>
    </nav>

    <div class="row justify-content-center">
      <div class="col-lg-7">
        <div class="card">
          <div class="card-header">
            <i class="bi bi-plus-circle me-2 text-primary"></i>New Rental Transaction
          </div>
          <div class="card-body p-4">
            <form method="POST" novalidate>

              <p class="form-section-label">Customer and Garment</p>

              <div class="mb-3">
                <label class="form-label">Customer <span class="text-danger">*</span></label>
                <select name="customer_id"
                        class="form-select <?= isset($errors['customer_id']) ? 'is-invalid' : '' ?>">
                  <option value="">— Select customer —</option>
                  <?php foreach ($customers as $c): ?>
                    <option value="<?= $c['customer_id'] ?>"
                      <?= $values['customer_id'] == $c['customer_id'] ? 'selected' : '' ?>>
                      <?= htmlspecialchars($c['full_name']) ?>
                      (<?= htmlspecialchars($c['mobile_number']) ?>)
                    </option>
                  <?php endforeach; ?>
                </select>
                <?php if (isset($errors['customer_id'])): ?>
                  <div class="invalid-feedback"><?= $errors['customer_id'] ?></div>
                <?php endif; ?>
              </div>

              <div class="mb-4">
                <label class="form-label">Garment <span class="text-danger">*</span></label>
                <select name="garment_id"
                        class="form-select <?= isset($errors['garment_id']) ? 'is-invalid' : '' ?>"
                        onchange="prefillRentalFee(this)">
                  <option value="">— Select available garment —</option>
                  <?php foreach ($garments as $g): ?>
                    <option value="<?= $g['garment_id'] ?>"
                            data-price="<?= $g['rental_price'] ?>"
                      <?= $values['garment_id'] == $g['garment_id'] ? 'selected' : '' ?>>
                      <?= htmlspecialchars($g['garment_name']) ?>
                      (<?= htmlspecialchars($g['garment_code']) ?>)
                      <?= $g['rental_price'] ? ' — ₱' . number_format($g['rental_price'], 2) : '' ?>
                    </option>
                  <?php endforeach; ?>
                </select>
                <?php if (isset($errors['garment_id'])): ?>
                  <div class="invalid-feedback"><?= $errors['garment_id'] ?></div>
                <?php endif; ?>
              </div>

              <hr class="my-4">
              <p class="form-section-label">Dates</p>

              <div class="row g-3 mb-4">
                <div class="col-sm-6">
                  <label class="form-label">Rental Date <span class="text-danger">*</span></label>
                  <input type="date" name="rental_date" class="form-control"
                         value="<?= htmlspecialchars($values['rental_date']) ?>"
                         max="<?= date('Y-m-d') ?>">
                </div>
                <div class="col-sm-6">
                  <label class="form-label">Return Date <span class="text-danger">*</span></label>
                  <input type="date" name="expected_return_date"
                         class="form-control <?= isset($errors['expected_return_date']) ? 'is-invalid' : '' ?>"
                         value="<?= htmlspecialchars($values['expected_return_date']) ?>"
                         min="<?= date('Y-m-d', strtotime('+1 day')) ?>">
                  <?php if (isset($errors['expected_return_date'])): ?>
                    <div class="invalid-feedback"><?= $errors['expected_return_date'] ?></div>
                  <?php endif; ?>
                </div>
              </div>

              <hr class="my-4">
              <p class="form-section-label">Payment</p>

              <div class="row g-3 mb-3">
                <div class="col-sm-6">
                  <label class="form-label">Rental Fee (₱) <span class="text-danger">*</span></label>
                  <input type="number" name="rental_fee" id="rental-fee"
                         step="0.01" min="0"
                         class="form-control <?= isset($errors['rental_fee']) ? 'is-invalid' : '' ?>"
                         value="<?= htmlspecialchars($values['rental_fee']) ?>">
                  <?php if (isset($errors['rental_fee'])): ?>
                    <div class="invalid-feedback"><?= $errors['rental_fee'] ?></div>
                  <?php endif; ?>
                </div>
                <div class="col-sm-6">
                  <label class="form-label">Deposit Amount (₱)</label>
                  <input type="number" name="deposit_amount"
                         step="0.01" min="0" class="form-control"
                         value="<?= htmlspecialchars($values['deposit_amount']) ?>">
                </div>
              </div>

              <div class="row g-3 mb-4">
                <div class="col-sm-6">
                  <label class="form-label">Penalty Rate/Day (₱)</label>
                  <input type="number" name="penalty_rate_per_day"
                         step="0.01" min="0" class="form-control"
                         value="<?= htmlspecialchars($values['penalty_rate_per_day']) ?>">
                  <div class="form-text">Default: ₱<?= number_format(DEFAULT_PENALTY_RATE, 2) ?>/day</div>
                </div>
                <div class="col-sm-6">
                  <label class="form-label">Payment Method <span class="text-danger">*</span></label>
                  <select name="payment_method" id="payment_method"
                          class="form-select <?= isset($errors['payment_method']) ? 'is-invalid' : '' ?>">
                    <option value="">— Select method —</option>
                    <?php foreach (['Cash','GCash','Card','Other'] as $pm): ?>
                      <option value="<?= $pm ?>"
                        <?= $values['payment_method'] === $pm ? 'selected' : '' ?>>
                        <?= $pm ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                  <?php if (isset($errors['payment_method'])): ?>
                    <div class="invalid-feedback"><?= $errors['payment_method'] ?></div>
                  <?php endif; ?>
                </div>
              </div>

              <div id="payment-other-field"
                   style="display:<?= $values['payment_method'] === 'Other' ? 'block':'none' ?>"
                   class="mb-4">
                <label class="form-label">Specify Payment Method</label>
                <input type="text" name="payment_other" class="form-control"
                       placeholder="e.g. Bank Transfer"
                       value="<?= htmlspecialchars($values['payment_other']) ?>">
              </div>

              <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary px-4">
                  <i class="bi bi-check-lg me-1"></i> Create Rental
                </button>
                <a href="/tailorshop/modules/rentals/index.php"
                   class="btn btn-outline-secondary px-4">Cancel</a>
              </div>

            </form>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
function prefillRentalFee(select) {
    const opt   = select.options[select.selectedIndex];
    const price = opt.dataset.price;
    const field = document.getElementById('rental-fee');
    if (price && field && !field.value) {
        field.value = parseFloat(price).toFixed(2);
    }
}
</script>
<?php require_once __DIR__ . '/../../../includes/footer.php'; ?>