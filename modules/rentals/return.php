<?php
// ============================================================
// modules/rentals/return.php
// Process rental return with condition report and penalty
// ============================================================

require_once __DIR__ . '/../../../includes/auth-guard.php';
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../helpers/audit-helper.php';
require_once __DIR__ . '/../../../helpers/rental-helper.php';
require_once __DIR__ . '/../../../helpers/inventory-helper.php';
require_once __DIR__ . '/../../../helpers/sales-helper.php';

$pageTitle  = 'Process Return';
$activePage = 'rentals';

$rentalId = (int)($_GET['id'] ?? 0);
if ($rentalId === 0) {
    header('Location: /tailorshop/modules/rentals/index.php');
    exit;
}

$stmt = $pdo->prepare('
    SELECT r.*,
           c.full_name   AS customer_name,
           c.mobile_number,
           c.customer_id,
           g.garment_name, g.garment_code,
           g.garment_id
    FROM rentals r
    JOIN customers     c ON r.customer_id = c.customer_id
    JOIN garment_items g ON r.garment_id  = g.garment_id
    WHERE r.rental_id = ?
');
$stmt->execute([$rentalId]);
$rental = $stmt->fetch();

if (!$rental || !in_array($rental['status'], ['Active','Due Today','Overdue'])) {
    $_SESSION['error'] = 'This rental cannot be processed for return.';
    header('Location: /tailorshop/modules/rentals/index.php');
    exit;
}

// Compute current penalty
$penalty = computePenalty(
    $rental['expected_return_date'],
    $rental['penalty_rate_per_day']
);

$errors = [];
$values = [
    'actual_return_date'  => date('Y-m-d'),
    'condition_on_return' => 'Good',
    'damage_notes'        => '',
    'deposit_status'      => 'Returned',
    'deposit_deducted'    => '0',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $values = [
        'actual_return_date'  => trim($_POST['actual_return_date']  ?? date('Y-m-d')),
        'condition_on_return' => trim($_POST['condition_on_return'] ?? 'Good'),
        'damage_notes'        => trim($_POST['damage_notes']        ?? ''),
        'deposit_status'      => trim($_POST['deposit_status']      ?? 'Returned'),
        'deposit_deducted'    => trim($_POST['deposit_deducted']    ?? '0'),
    ];

    if ($values['actual_return_date'] < $rental['rental_date'])
        $errors['actual_return_date'] = 'Return date cannot be before rental date.';
    if (!is_numeric($values['deposit_deducted']) || (float)$values['deposit_deducted'] < 0)
        $errors['deposit_deducted'] = 'Enter a valid deduction amount.';
    if ((float)$values['deposit_deducted'] > $rental['deposit_amount'])
        $errors['deposit_deducted'] = 'Deduction cannot exceed deposit of ₱' .
                                       number_format($rental['deposit_amount'], 2) . '.';

    if (empty($errors)) {
        // Compute actual penalty based on real return date
        $finalPenalty = computePenalty(
            $rental['expected_return_date'],
            $rental['penalty_rate_per_day'],
            $values['actual_return_date']
        );

        $pdo->beginTransaction();
        try {
            // Update rental
            $pdo->prepare('
                UPDATE rentals
                SET status = "Returned",
                    actual_return_date = ?,
                    total_penalty      = ?
                WHERE rental_id = ?
            ')->execute([
                $values['actual_return_date'],
                $finalPenalty['total_penalty'],
                $rentalId,
            ]);

            // Insert condition report
            $pdo->prepare('
                INSERT INTO rental_condition_reports
                    (rental_id, recorded_by, condition_on_return,
                     damage_notes, deposit_status, deposit_deducted)
                VALUES (?, ?, ?, ?, ?, ?)
            ')->execute([
                $rentalId,
                $_SESSION['user_id'],
                $values['condition_on_return'],
                $values['damage_notes'] ?: null,
                $values['deposit_status'],
                (float)$values['deposit_deducted'],
            ]);

            // Update garment status
            $garmentCondition = $values['condition_on_return'] === 'Damaged'
                ? 'Damaged'
                : ($values['condition_on_return'] === 'Needs Repair'
                    ? 'Needs Repair'
                    : ($values['condition_on_return'] === 'Needs Cleaning'
                        ? 'Needs Cleaning'
                        : 'Good'));

            $garmentAvailability = in_array($garmentCondition, ['Damaged','Needs Repair'])
                ? ($garmentCondition === 'Damaged' ? 'Damaged' : 'Under Repair')
                : 'Available';

            updateGarmentStatus(
                $rental['garment_id'],
                $garmentAvailability,
                $_SESSION['user_id'],
                $garmentCondition
            );

            // Record rental fee as income
            recordSale(
                $rental['customer_id'],
                $_SESSION['user_id'],
                'Rental Fee',
                $rental['rental_fee'] + $finalPenalty['total_penalty'],
                $rental['payment_method'] ?? 'Cash',
                null,
                $rentalId,
                null,
                $rental['payment_other'] ?? null,
                $finalPenalty['total_penalty'] > 0
                    ? 'Includes penalty of ₱' . number_format($finalPenalty['total_penalty'], 2)
                    : null
            );

            $pdo->commit();

            auditStatusChange(
                $_SESSION['user_id'], 'Rentals',
                'rentals', $rentalId, $rental['status'], 'Returned'
            );

            $_SESSION['success'] = 'Return processed for Rental #' .
                str_pad($rentalId, 4, '0', STR_PAD_LEFT) . '. ' .
                ($finalPenalty['total_penalty'] > 0
                    ? 'Penalty applied: ₱' . number_format($finalPenalty['total_penalty'], 2) . '.'
                    : 'No penalty applied.');

            header('Location: /tailorshop/modules/rentals/view.php?id=' . $rentalId);
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            $errors['general'] = 'Something went wrong. Please try again.';
        }
    }
}

$conditions    = ['Good','Needs Cleaning','Needs Repair','Damaged'];
$depositStatuses = ['Returned','Partially Returned','Forfeited'];

require_once __DIR__ . '/../../../includes/header.php';
require_once __DIR__ . '/../../../includes/sidebar.php';
?>

<div class="main-content">
  <div class="topbar"><span class="topbar-title">Process Return</span></div>

  <div class="page-body">

    <nav class="breadcrumb-nav mb-3">
      <a href="/tailorshop/modules/rentals/index.php">
        <i class="bi bi-handbag me-1"></i>Rentals
      </a>
      <span class="mx-2">/</span>
      <a href="/tailorshop/modules/rentals/view.php?id=<?= $rentalId ?>">
        #<?= str_pad($rentalId, 4, '0', STR_PAD_LEFT) ?>
      </a>
      <span class="mx-2">/</span><span>Process Return</span>
    </nav>

    <!-- Rental summary -->
    <div class="alert alert-secondary d-flex gap-2 align-items-start mb-3">
      <i class="bi bi-info-circle text-primary flex-shrink-0 mt-1"></i>
      <div style="font-size:13.5px">
        Processing return for <strong><?= htmlspecialchars($rental['customer_name']) ?></strong>
        — <strong><?= htmlspecialchars($rental['garment_name']) ?></strong>
        <span style="font-family:monospace">(<?= htmlspecialchars($rental['garment_code']) ?>)</span>.
        Expected return: <strong><?= date('F j, Y', strtotime($rental['expected_return_date'])) ?></strong>.
        <?php if ($penalty['days_overdue'] > 0): ?>
          <span class="text-danger fw-semibold">
            Currently <?= $penalty['days_overdue'] ?> day<?= $penalty['days_overdue'] > 1 ? 's' : '' ?> overdue.
            Estimated penalty: ₱<?= number_format($penalty['total_penalty'], 2) ?>.
          </span>
        <?php endif; ?>
      </div>
    </div>

    <?php if (isset($errors['general'])): ?>
      <div class="alert alert-danger"><?= $errors['general'] ?></div>
    <?php endif; ?>

    <div class="row justify-content-center">
      <div class="col-lg-7">
        <div class="card">
          <div class="card-header">
            <i class="bi bi-box-arrow-in-left me-2 text-success"></i>Return Details
          </div>
          <div class="card-body p-4">
            <form method="POST" novalidate>

              <p class="form-section-label">Return Information</p>

              <div class="mb-3">
                <label class="form-label">
                  Actual Return Date <span class="text-danger">*</span>
                </label>
                <input type="date" name="actual_return_date"
                       class="form-control <?= isset($errors['actual_return_date']) ? 'is-invalid' : '' ?>"
                       value="<?= htmlspecialchars($values['actual_return_date']) ?>"
                       min="<?= $rental['rental_date'] ?>"
                       max="<?= date('Y-m-d') ?>"
                       id="return-date-input"
                       required>
                <?php if (isset($errors['actual_return_date'])): ?>
                  <div class="invalid-feedback"><?= $errors['actual_return_date'] ?></div>
                <?php endif; ?>
              </div>

              <!-- Live penalty preview -->
              <div class="alert alert-warning py-2 small mb-4" id="penalty-preview"
                   style="display:<?= $penalty['days_overdue'] > 0 ? 'block' : 'none' ?>">
                <i class="bi bi-exclamation-triangle me-1"></i>
                <span id="penalty-text">
                  <?= $penalty['days_overdue'] ?> day<?= $penalty['days_overdue'] > 1 ? 's' : '' ?> overdue
                  — Penalty: ₱<?= number_format($penalty['total_penalty'], 2) ?>
                </span>
              </div>

              <hr class="my-4">
              <p class="form-section-label">Garment Condition</p>

              <div class="mb-3">
                <label class="form-label">Condition on Return <span class="text-danger">*</span></label>
                <select name="condition_on_return" class="form-select">
                  <?php foreach ($conditions as $c): ?>
                    <option value="<?= $c ?>"
                      <?= $values['condition_on_return'] === $c ? 'selected' : '' ?>>
                      <?= $c ?>
                    </option>
                  <?php endforeach; ?>
                </select>
                <div class="form-text">
                  Damaged or Needs Repair will mark the garment as unavailable for re-rental.
                </div>
              </div>

              <div class="mb-4">
                <label class="form-label">Damage Notes</label>
                <textarea name="damage_notes" class="form-control" rows="2"
                          placeholder="Describe any damage found on return… (optional)"
                          ><?= htmlspecialchars($values['damage_notes']) ?></textarea>
              </div>

              <hr class="my-4">
              <p class="form-section-label">Deposit Resolution</p>

              <div class="row g-3 mb-4">
                <div class="col-sm-6">
                  <label class="form-label">Deposit Status</label>
                  <select name="deposit_status" class="form-select">
                    <?php foreach ($depositStatuses as $ds): ?>
                      <option value="<?= $ds ?>"
                        <?= $values['deposit_status'] === $ds ? 'selected' : '' ?>>
                        <?= $ds ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-sm-6">
                  <label class="form-label">
                    Deposit Deducted (₱)
                    <span class="text-muted small">
                      (max ₱<?= number_format($rental['deposit_amount'], 2) ?>)
                    </span>
                  </label>
                  <input type="number" name="deposit_deducted"
                         step="0.01" min="0"
                         max="<?= $rental['deposit_amount'] ?>"
                         class="form-control <?= isset($errors['deposit_deducted']) ? 'is-invalid' : '' ?>"
                         value="<?= htmlspecialchars($values['deposit_deducted']) ?>">
                  <?php if (isset($errors['deposit_deducted'])): ?>
                    <div class="invalid-feedback"><?= $errors['deposit_deducted'] ?></div>
                  <?php endif; ?>
                </div>
              </div>

              <div class="d-flex gap-2">
                <button type="submit" class="btn btn-success px-4">
                  <i class="bi bi-check-lg me-1"></i> Confirm Return
                </button>
                <a href="/tailorshop/modules/rentals/view.php?id=<?= $rentalId ?>"
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
// Live penalty preview on date change
const returnDateInput  = document.getElementById('return-date-input');
const penaltyPreview   = document.getElementById('penalty-preview');
const penaltyText      = document.getElementById('penalty-text');
const expectedReturn   = '<?= $rental['expected_return_date'] ?>';
const penaltyRate      = <?= $rental['penalty_rate_per_day'] ?>;

if (returnDateInput) {
    returnDateInput.addEventListener('change', () => {
        const returnDate = new Date(returnDateInput.value);
        const expected   = new Date(expectedReturn);
        const diffMs     = returnDate - expected;
        const diffDays   = Math.max(0, Math.floor(diffMs / 86400000));
        const penalty    = diffDays * penaltyRate;

        if (diffDays > 0) {
            penaltyText.textContent = diffDays + ' day' + (diffDays > 1 ? 's' : '') +
                ' overdue — Penalty: ₱' + penalty.toLocaleString('en-PH', {
                    minimumFractionDigits: 2, maximumFractionDigits: 2
                });
            penaltyPreview.style.display = 'block';
        } else {
            penaltyPreview.style.display = 'none';
        }
    });
}
</script>

<?php require_once __DIR__ . '/../../../includes/footer.php'; ?>