<?php
// ============================================================
// modules/reservations/convert.php
// Convert a confirmed reservation into a rental transaction
// ============================================================

require_once __DIR__ . '/../../../includes/auth-guard.php';
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../config/constants.php';
require_once __DIR__ . '/../../../helpers/audit-helper.php';
require_once __DIR__ . '/../../../helpers/sms-helper.php';

$pageTitle  = 'Convert to Rental';
$activePage = 'reservations';

$resId = (int)($_GET['id'] ?? 0);
if ($resId === 0) {
    header('Location: /tailorshop/modules/reservations/index.php');
    exit;
}

$stmt = $pdo->prepare('
    SELECT res.*,
           c.full_name    AS customer_name,
           c.mobile_number,
           c.customer_id,
           g.garment_name, g.garment_code,
           g.garment_id,  g.rental_price
    FROM reservations res
    JOIN customers     c ON res.customer_id = c.customer_id
    JOIN garment_items g ON res.garment_id  = g.garment_id
    WHERE res.reservation_id = ?
');
$stmt->execute([$resId]);
$res = $stmt->fetch();

if (!$res || !in_array($res['status'], ['Pending','Confirmed'])) {
    $_SESSION['error'] = 'This reservation cannot be converted.';
    header('Location: /tailorshop/modules/reservations/index.php');
    exit;
}

$errors = [];
$values = [
    'rental_fee'           => $res['rental_price'] ?? '',
    'deposit_amount'       => '',
    'penalty_rate_per_day' => DEFAULT_PENALTY_RATE,
    'payment_method'       => '',
    'payment_other'        => '',
    'rental_date'          => date('Y-m-d'),
    'expected_return_date' => $res['return_date'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $values = [
        'rental_fee'           => trim($_POST['rental_fee']           ?? ''),
        'deposit_amount'       => trim($_POST['deposit_amount']       ?? '0'),
        'penalty_rate_per_day' => trim($_POST['penalty_rate_per_day'] ?? DEFAULT_PENALTY_RATE),
        'payment_method'       => trim($_POST['payment_method']       ?? ''),
        'payment_other'        => trim($_POST['payment_other']        ?? ''),
        'rental_date'          => trim($_POST['rental_date']          ?? date('Y-m-d')),
        'expected_return_date' => trim($_POST['expected_return_date'] ?? ''),
    ];

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
    if ($values['expected_return_date'] <= $values['rental_date'])
        $errors['expected_return_date'] = 'Return date must be after rental date.';

    if (empty($errors)) {
        $pdo->beginTransaction();
        try {
            // Create rental
            $pdo->prepare('
                INSERT INTO rentals
                    (customer_id, garment_id, processed_by, reservation_id,
                     rental_date, expected_return_date,
                     rental_fee, deposit_amount, penalty_rate_per_day,
                     payment_method, payment_other, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "Active")
            ')->execute([
                $res['customer_id'],
                $res['garment_id'],
                $_SESSION['user_id'],
                $resId,
                $values['rental_date'],
                $values['expected_return_date'],
                (float)$values['rental_fee'],
                (float)$values['deposit_amount'],
                (float)$values['penalty_rate_per_day'],
                $values['payment_method'],
                $values['payment_other'] ?: null,
            ]);
            $rentalId = (int)$pdo->lastInsertId();

            // Mark reservation as Converted
            $pdo->prepare('
                UPDATE reservations SET status = "Converted"
                WHERE reservation_id = ?
            ')->execute([$resId]);

            // Mark garment as Rented Out
            $pdo->prepare("
                UPDATE garment_items SET availability_status = 'Rented Out'
                WHERE garment_id = ?
            ")->execute([$res['garment_id']]);

            $pdo->commit();

            auditCreate($_SESSION['user_id'], 'Rentals', 'rentals', $rentalId, [
                'converted_from_reservation' => $resId,
                'customer'                   => $res['customer_name'],
            ]);
            auditStatusChange(
                $_SESSION['user_id'], 'Reservations',
                'reservations', $resId, 'Confirmed', 'Converted'
            );

            $_SESSION['success'] = 'Reservation converted to Rental #' .
                str_pad($rentalId, 4, '0', STR_PAD_LEFT) . ' successfully.';
            header('Location: /tailorshop/modules/rentals/view.php?id=' . $rentalId);
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            $errors['general'] = 'Something went wrong. Please try again.';
        }
    }
}

require_once __DIR__ . '/../../../includes/header.php';
require_once __DIR__ . '/../../../includes/sidebar.php';
?>

<div class="main-content">
  <div class="topbar"><span class="topbar-title">Convert Reservation to Rental</span></div>

  <div class="page-body">

    <nav class="breadcrumb-nav mb-3">
      <a href="/tailorshop/modules/reservations/index.php">
        <i class="bi bi-calendar-check me-1"></i>Reservations
      </a>
      <span class="mx-2">/</span>
      <a href="/tailorshop/modules/reservations/view.php?id=<?= $resId ?>">
        #<?= str_pad($resId, 4, '0', STR_PAD_LEFT) ?>
      </a>
      <span class="mx-2">/</span><span>Convert to Rental</span>
    </nav>

    <div class="row justify-content-center">
      <div class="col-lg-7">

        <!-- Summary banner -->
        <div class="alert alert-secondary d-flex gap-2 align-items-start mb-3">
          <i class="bi bi-arrow-right-circle text-success flex-shrink-0 mt-1"></i>
          <div style="font-size:13.5px">
            Converting reservation for
            <strong><?= htmlspecialchars($res['customer_name']) ?></strong>
            — <strong><?= htmlspecialchars($res['garment_name']) ?></strong>
            <span style="font-family:monospace">(<?= htmlspecialchars($res['garment_code']) ?>)</span>
            into a rental transaction.
          </div>
        </div>

        <?php if (isset($errors['general'])): ?>
          <div class="alert alert-danger"><?= $errors['general'] ?></div>
        <?php endif; ?>

        <div class="card">
          <div class="card-header">
            <i class="bi bi-handbag me-2 text-warning"></i>Rental Details
          </div>
          <div class="card-body p-4">
            <form method="POST" novalidate>

              <p class="form-section-label">Dates</p>
              <div class="row g-3 mb-4">
                <div class="col-sm-6">
                  <label class="form-label">Rental Date <span class="text-danger">*</span></label>
                  <input type="date" name="rental_date" class="form-control"
                         value="<?= htmlspecialchars($values['rental_date']) ?>"
                         max="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="col-sm-6">
                  <label class="form-label">Expected Return Date <span class="text-danger">*</span></label>
                  <input type="date" name="expected_return_date"
                         class="form-control <?= isset($errors['expected_return_date']) ? 'is-invalid' : '' ?>"
                         value="<?= htmlspecialchars($values['expected_return_date']) ?>"
                         required>
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
                  <input type="number" name="rental_fee" step="0.01" min="0"
                         class="form-control <?= isset($errors['rental_fee']) ? 'is-invalid' : '' ?>"
                         value="<?= htmlspecialchars($values['rental_fee']) ?>"
                         required>
                  <?php if (isset($errors['rental_fee'])): ?>
                    <div class="invalid-feedback"><?= $errors['rental_fee'] ?></div>
                  <?php endif; ?>
                </div>
                <div class="col-sm-6">
                  <label class="form-label">Deposit Amount (₱)</label>
                  <input type="number" name="deposit_amount" step="0.01" min="0"
                         class="form-control <?= isset($errors['deposit_amount']) ? 'is-invalid' : '' ?>"
                         value="<?= htmlspecialchars($values['deposit_amount']) ?>">
                  <?php if (isset($errors['deposit_amount'])): ?>
                    <div class="invalid-feedback"><?= $errors['deposit_amount'] ?></div>
                  <?php endif; ?>
                </div>
              </div>

              <div class="row g-3 mb-4">
                <div class="col-sm-6">
                  <label class="form-label">Penalty Rate/Day (₱)</label>
                  <input type="number" name="penalty_rate_per_day" step="0.01" min="0"
                         class="form-control <?= isset($errors['penalty_rate_per_day']) ? 'is-invalid' : '' ?>"
                         value="<?= htmlspecialchars($values['penalty_rate_per_day']) ?>">
                  <div class="form-text">Applied per day if returned late.</div>
                  <?php if (isset($errors['penalty_rate_per_day'])): ?>
                    <div class="invalid-feedback"><?= $errors['penalty_rate_per_day'] ?></div>
                  <?php endif; ?>
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
                   style="display:<?= $values['payment_method'] === 'Other' ? 'block' : 'none' ?>" class="mb-4">
                <label class="form-label">Specify Payment Method</label>
                <input type="text" name="payment_other" class="form-control"
                       placeholder="e.g. Bank Transfer"
                       value="<?= htmlspecialchars($values['payment_other']) ?>">
              </div>

              <div class="d-flex gap-2">
                <button type="submit" class="btn btn-success px-4">
                  <i class="bi bi-arrow-right-circle me-1"></i> Convert to Rental
                </button>
                <a href="/tailorshop/modules/reservations/view.php?id=<?= $resId ?>"
                   class="btn btn-outline-secondary px-4">Cancel</a>
              </div>

            </form>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../../../includes/footer.php'; ?>