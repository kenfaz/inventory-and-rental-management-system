<?php
// ============================================================
// modules/reservations/create.php
// Create reservation with conflict detection and SMS trigger
// ============================================================

require_once __DIR__ . '/../../../includes/auth-guard.php';
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../config/constants.php';
require_once __DIR__ . '/../../../helpers/audit-helper.php';
require_once __DIR__ . '/../../../helpers/inventory-helper.php';
require_once __DIR__ . '/../../../helpers/sms-helper.php';

$pageTitle  = 'New Reservation';
$activePage = 'reservations';

// Pre-fill from query string if coming from garment/customer view
$preGarmentId  = (int)($_GET['garment_id']  ?? 0);
$preCustomerId = (int)($_GET['customer_id'] ?? 0);

// Fetch all active customers and available garments for dropdowns
$customers = $pdo->query('
    SELECT customer_id, full_name, mobile_number
    FROM customers ORDER BY full_name ASC
')->fetchAll();

$garments = $pdo->query("
    SELECT garment_id, garment_name, garment_code, rental_price, availability_status
    FROM garment_items
    WHERE availability_status IN ('Available','Reserved')
    ORDER BY garment_name ASC
")->fetchAll();

$errors = [];
$values = [
    'customer_id'  => $preCustomerId ?: '',
    'garment_id'   => $preGarmentId  ?: '',
    'pickup_date'  => '',
    'return_date'  => '',
    'notes'        => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $values = [
        'customer_id'  => trim($_POST['customer_id']  ?? ''),
        'garment_id'   => trim($_POST['garment_id']   ?? ''),
        'pickup_date'  => trim($_POST['pickup_date']  ?? ''),
        'return_date'  => trim($_POST['return_date']  ?? ''),
        'notes'        => trim($_POST['notes']        ?? ''),
    ];

    // Validate
    if (!$values['customer_id'])
        $errors['customer_id'] = 'Please select a customer.';
    if (!$values['garment_id'])
        $errors['garment_id'] = 'Please select a garment.';
    if ($values['pickup_date'] === '')
        $errors['pickup_date'] = 'Pickup date is required.';
    if ($values['return_date'] === '')
        $errors['return_date'] = 'Return date is required.';

    if (empty($errors['pickup_date']) && empty($errors['return_date'])) {
        if ($values['return_date'] <= $values['pickup_date'])
            $errors['return_date'] = 'Return date must be after pickup date.';
        if ($values['pickup_date'] < date('Y-m-d'))
            $errors['pickup_date'] = 'Pickup date cannot be in the past.';
    }

    // Conflict detection
    if (empty($errors) && $values['garment_id']) {
        $available = isGarmentAvailable(
            (int)$values['garment_id'],
            $values['pickup_date'],
            $values['return_date']
        );
        if (!$available) {
            $errors['garment_id'] = 'This garment is not available for the selected dates.
                                     It has a conflicting reservation or active rental.';
        }
    }

    if (empty($errors)) {
        // Save reservation
        $pdo->prepare('
            INSERT INTO reservations
                (customer_id, garment_id, processed_by,
                 pickup_date, return_date, status, notes)
            VALUES (?, ?, ?, ?, ?, "Confirmed", ?)
        ')->execute([
            (int)$values['customer_id'],
            (int)$values['garment_id'],
            $_SESSION['user_id'],
            $values['pickup_date'],
            $values['return_date'],
            $values['notes'] ?: null,
        ]);

        $resId = (int)$pdo->lastInsertId();

        // Update garment status to Reserved
        $pdo->prepare("
            UPDATE garment_items SET availability_status = 'Reserved'
            WHERE garment_id = ?
        ")->execute([(int)$values['garment_id']]);

        auditCreate($_SESSION['user_id'], 'Reservations', 'reservations', $resId, $values);

        // Fetch full details for SMS
        $smsData = $pdo->prepare('
            SELECT res.*, c.full_name AS customer_name, c.mobile_number,
                   g.garment_name
            FROM reservations res
            JOIN customers     c ON res.customer_id = c.customer_id
            JOIN garment_items g ON res.garment_id  = g.garment_id
            WHERE res.reservation_id = ?
        ');
        $smsData->execute([$resId]);
        $smsRecord = $smsData->fetch();

        // Send confirmation SMS
        sendSMS(
            $smsRecord['mobile_number'],
            buildReservationConfirmSMS($smsRecord),
            'Reservation Confirmation',
            $resId,
            'reservations'
        );

        $_SESSION['success'] = 'Reservation #' . str_pad($resId, 4, '0', STR_PAD_LEFT) .
                               ' created and confirmation SMS sent.';
        header('Location: /tailorshop/modules/reservations/view.php?id=' . $resId);
        exit;
    }
}

require_once __DIR__ . '/../../../includes/header.php';
require_once __DIR__ . '/../../../includes/sidebar.php';
?>

<div class="main-content">
  <div class="topbar"><span class="topbar-title">New Reservation</span></div>

  <div class="page-body">

    <nav class="breadcrumb-nav mb-3">
      <a href="/tailorshop/modules/reservations/index.php">
        <i class="bi bi-calendar-check me-1"></i>Reservations
      </a>
      <span class="mx-2">/</span><span>New Reservation</span>
    </nav>

    <div class="row justify-content-center">
      <div class="col-lg-7">
        <div class="card">
          <div class="card-header">
            <i class="bi bi-calendar-plus me-2 text-primary"></i>Create Reservation
          </div>
          <div class="card-body p-4">
            <form method="POST" novalidate id="reservation-form">

              <p class="form-section-label">Customer and Garment</p>

              <!-- Customer -->
              <div class="mb-3">
                <label class="form-label">
                  Customer <span class="text-danger">*</span>
                </label>
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
                <div class="form-text">
                  Customer not in list?
                  <a href="/tailorshop/modules/customers/add.php" target="_blank">
                    Add new customer
                  </a>
                </div>
              </div>

              <!-- Garment -->
              <div class="mb-4">
                <label class="form-label">
                  Garment <span class="text-danger">*</span>
                </label>
                <select name="garment_id" id="garment-select"
                        class="form-select <?= isset($errors['garment_id']) ? 'is-invalid' : '' ?>">
                  <option value="">— Select garment —</option>
                  <?php foreach ($garments as $g): ?>
                    <option value="<?= $g['garment_id'] ?>"
                            data-price="<?= $g['rental_price'] ?>"
                            data-status="<?= $g['availability_status'] ?>"
                      <?= $values['garment_id'] == $g['garment_id'] ? 'selected' : '' ?>>
                      <?= htmlspecialchars($g['garment_name']) ?>
                      (<?= htmlspecialchars($g['garment_code']) ?>)
                      <?= $g['rental_price'] ? ' — ₱' . number_format($g['rental_price'], 2) : '' ?>
                    </option>
                  <?php endforeach; ?>
                </select>
                <?php if (isset($errors['garment_id'])): ?>
                  <div class="invalid-feedback d-block">
                    <?= $errors['garment_id'] ?>
                  </div>
                <?php endif; ?>
                <!-- Conflict alert placeholder -->
                <div id="conflict-alert" class="alert alert-warning mt-2 py-2 small d-none">
                  <i class="bi bi-exclamation-triangle me-1"></i>
                  <span id="conflict-msg"></span>
                </div>
              </div>

              <hr class="my-4">
              <p class="form-section-label">Reservation Dates</p>

              <div class="row g-3 mb-4">
                <div class="col-sm-6">
                  <label class="form-label">
                    Pickup Date <span class="text-danger">*</span>
                  </label>
                  <input type="date" name="pickup_date" id="pickup-date"
                         class="form-control <?= isset($errors['pickup_date']) ? 'is-invalid' : '' ?>"
                         min="<?= date('Y-m-d') ?>"
                         value="<?= htmlspecialchars($values['pickup_date']) ?>"
                         required>
                  <?php if (isset($errors['pickup_date'])): ?>
                    <div class="invalid-feedback"><?= $errors['pickup_date'] ?></div>
                  <?php endif; ?>
                </div>
                <div class="col-sm-6">
                  <label class="form-label">
                    Return Date <span class="text-danger">*</span>
                  </label>
                  <input type="date" name="return_date" id="return-date"
                         class="form-control <?= isset($errors['return_date']) ? 'is-invalid' : '' ?>"
                         min="<?= date('Y-m-d', strtotime('+1 day')) ?>"
                         value="<?= htmlspecialchars($values['return_date']) ?>"
                         required>
                  <?php if (isset($errors['return_date'])): ?>
                    <div class="invalid-feedback"><?= $errors['return_date'] ?></div>
                  <?php endif; ?>
                </div>
              </div>

              <!-- Notes -->
              <div class="mb-4">
                <label class="form-label">Notes</label>
                <textarea name="notes" class="form-control" rows="2"
                          placeholder="Optional notes about this reservation…"
                          ><?= htmlspecialchars($values['notes']) ?></textarea>
              </div>

              <!-- SMS notice -->
              <div class="alert alert-secondary d-flex gap-2 align-items-start py-2 small mb-4">
                <i class="bi bi-chat-dots text-primary mt-1 flex-shrink-0"></i>
                <div>
                  An SMS confirmation will be sent to the customer's registered
                  mobile number immediately after saving.
                  A reminder SMS will also be sent
                  <?= RESERVATION_REMINDER_DAYS ?> days before the pickup date.
                </div>
              </div>

              <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary px-4">
                  <i class="bi bi-check-lg me-1"></i> Confirm Reservation
                </button>
                <a href="/tailorshop/modules/reservations/index.php"
                   class="btn btn-outline-secondary px-4">Cancel</a>
              </div>

            </form>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="/tailorshop/assets/js/conflict-checker.js"></script>
<?php require_once __DIR__ . '/../../../includes/footer.php'; ?>