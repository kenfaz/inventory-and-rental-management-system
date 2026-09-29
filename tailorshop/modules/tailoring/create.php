<?php
// ============================================================
// modules/tailoring/create.php
// Create tailoring order with measurements and down payment
// ============================================================

require_once __DIR__ . '/../../includes/auth-guard.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../helpers/audit-helper.php';

$pageTitle  = 'New Tailoring Order';
$activePage = 'tailoring';

$preCustomerId = (int)($_GET['customer_id'] ?? 0);

$customers = $pdo->query('
    SELECT customer_id, full_name, mobile_number
    FROM customers ORDER BY full_name ASC
')->fetchAll();

$staff = $pdo->query("
    SELECT user_id, full_name FROM users
    WHERE status = 'active' ORDER BY full_name ASC
")->fetchAll();

$errors = [];
$values = [
    'customer_id'       => $preCustomerId ?: '',
    'assigned_staff_id' => $_SESSION['user_role'] !== 'admin' ? $_SESSION['user_id'] : '',
    'order_name'        => '',
    'garment_description'=> '',
    'price'             => '',
    'down_payment'      => '',
    'payment_method'    => '',
    'payment_other'     => '',
    'due_date'          => '',
    'notes'             => '',
    // Measurements
    'bust'    => '', 'waist'  => '', 'hips'   => '',
    'length'  => '', 'shoulder'=> '', 'sleeve' => '',
    'meas_notes' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $values = [
        'customer_id'        => trim($_POST['customer_id']        ?? ''),
        'assigned_staff_id'  => trim($_POST['assigned_staff_id']  ?? ''),
        'order_name'         => trim($_POST['order_name']         ?? ''),
        'garment_description'=> trim($_POST['garment_description']?? ''),
        'price'              => trim($_POST['price']              ?? ''),
        'down_payment'       => trim($_POST['down_payment']       ?? '0'),
        'payment_method'     => trim($_POST['payment_method']     ?? ''),
        'payment_other'      => trim($_POST['payment_other']      ?? ''),
        'due_date'           => trim($_POST['due_date']           ?? ''),
        'notes'              => trim($_POST['notes']              ?? ''),
        'bust'               => trim($_POST['bust']               ?? ''),
        'waist'              => trim($_POST['waist']              ?? ''),
        'hips'               => trim($_POST['hips']               ?? ''),
        'length'             => trim($_POST['length']             ?? ''),
        'shoulder'           => trim($_POST['shoulder']           ?? ''),
        'sleeve'             => trim($_POST['sleeve']             ?? ''),
        'meas_notes'         => trim($_POST['meas_notes']         ?? ''),
    ];

    if (!$values['customer_id'])
        $errors['customer_id'] = 'Please select a customer.';
    if (!$values['assigned_staff_id'])
        $errors['assigned_staff_id'] = 'Please assign a staff member.';
    if ($values['order_name'] === '')
        $errors['order_name'] = 'Order name is required.';
    if ($values['garment_description'] === '')
        $errors['garment_description'] = 'Garment description is required.';
    if (!is_numeric($values['price']) || (float)$values['price'] <= 0)
        $errors['price'] = 'Price must be greater than zero.';
    if (!is_numeric($values['down_payment']) || (float)$values['down_payment'] < 0)
        $errors['down_payment'] = 'Enter a valid down payment amount.';
    if (is_numeric($values['price']) && is_numeric($values['down_payment'])
        && (float)$values['down_payment'] > (float)$values['price'])
        $errors['down_payment'] = 'Down payment cannot exceed the total price.';
    if ($values['payment_method'] === '' && (float)$values['down_payment'] > 0)
        $errors['payment_method'] = 'Please select a payment method for the down payment.';
    if ($values['due_date'] === '')
        $errors['due_date'] = 'Due date is required.';
    elseif ($values['due_date'] <= date('Y-m-d'))
        $errors['due_date'] = 'Due date must be a future date.';

    if (empty($errors)) {
        $pdo->beginTransaction();
        try {
            $pdo->prepare('
                INSERT INTO tailoring_orders
                    (customer_id, assigned_staff_id, order_name,
                     garment_description, price, down_payment,
                     payment_method, payment_other,
                     status, due_date, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, "Pending", ?, ?)
            ')->execute([
                (int)$values['customer_id'],
                (int)$values['assigned_staff_id'],
                $values['order_name'],
                $values['garment_description'],
                (float)$values['price'],
                (float)$values['down_payment'],
                $values['payment_method'] ?: null,
                $values['payment_other']  ?: null,
                $values['due_date'],
                $values['notes'] ?: null,
            ]);
            $orderId = (int)$pdo->lastInsertId();

            // Insert measurements
            $pdo->prepare('
                INSERT INTO order_measurements
                    (order_id, bust, waist, hips, length, shoulder, sleeve, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ')->execute([
                $orderId,
                $values['bust']    !== '' ? (float)$values['bust']    : null,
                $values['waist']   !== '' ? (float)$values['waist']   : null,
                $values['hips']    !== '' ? (float)$values['hips']    : null,
                $values['length']  !== '' ? (float)$values['length']  : null,
                $values['shoulder']!== '' ? (float)$values['shoulder']: null,
                $values['sleeve']  !== '' ? (float)$values['sleeve']  : null,
                $values['meas_notes'] ?: null,
            ]);

            $pdo->commit();
            auditCreate($_SESSION['user_id'], 'Tailoring Orders', 'tailoring_orders', $orderId, [
                'order_name'  => $values['order_name'],
                'customer_id' => $values['customer_id'],
            ]);

            $_SESSION['success'] = 'Order #' . str_pad($orderId, 4, '0', STR_PAD_LEFT) .
                                   ' created successfully.';
            header('Location: /tailorshop/modules/tailoring/view.php?id=' . $orderId);
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            $errors['general'] = 'Something went wrong. Please try again.';
        }
    }
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="main-content">
  <div class="topbar"><span class="topbar-title">New Tailoring Order</span></div>

  <div class="page-body">

    <nav class="breadcrumb-nav mb-3">
      <a href="/tailorshop/modules/tailoring/index.php">
        <i class="bi bi-scissors me-1"></i>Tailoring Orders
      </a>
      <span class="mx-2">/</span><span>New Order</span>
    </nav>

    <?php if (isset($errors['general'])): ?>
      <div class="alert alert-danger"><?= $errors['general'] ?></div>
    <?php endif; ?>

    <div class="row justify-content-center">
      <div class="col-lg-8">
        <div class="card">
          <div class="card-header">
            <i class="bi bi-scissors me-2 text-primary"></i>New Tailoring Order
          </div>
          <div class="card-body p-4">
            <form method="POST" novalidate>

              <!-- Section: Customer and Staff -->
              <p class="form-section-label">Customer and Assignment</p>
              <div class="row g-3 mb-4">
                <div class="col-sm-6">
                  <label class="form-label">Customer <span class="text-danger">*</span></label>
                  <select name="customer_id"
                          class="form-select <?= isset($errors['customer_id']) ? 'is-invalid':'' ?>">
                    <option value="">— Select customer —</option>
                    <?php foreach ($customers as $c): ?>
                      <option value="<?= $c['customer_id'] ?>"
                        <?= $values['customer_id'] == $c['customer_id'] ? 'selected':'' ?>>
                        <?= htmlspecialchars($c['full_name']) ?>
                        (<?= htmlspecialchars($c['mobile_number']) ?>)
                      </option>
                    <?php endforeach; ?>
                  </select>
                  <?php if (isset($errors['customer_id'])): ?>
                    <div class="invalid-feedback"><?= $errors['customer_id'] ?></div>
                  <?php endif; ?>
                </div>
                <div class="col-sm-6">
                  <label class="form-label">Assigned Staff <span class="text-danger">*</span></label>
                  <select name="assigned_staff_id"
                          class="form-select <?= isset($errors['assigned_staff_id']) ? 'is-invalid':'' ?>">
                    <option value="">— Select staff —</option>
                    <?php foreach ($staff as $s): ?>
                      <option value="<?= $s['user_id'] ?>"
                        <?= $values['assigned_staff_id'] == $s['user_id'] ? 'selected':'' ?>>
                        <?= htmlspecialchars($s['full_name']) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                  <?php if (isset($errors['assigned_staff_id'])): ?>
                    <div class="invalid-feedback"><?= $errors['assigned_staff_id'] ?></div>
                  <?php endif; ?>
                </div>
              </div>

              <hr class="my-4">

              <!-- Section: Order details -->
              <p class="form-section-label">Order Details</p>
              <div class="mb-3">
                <label class="form-label">Order Name <span class="text-danger">*</span></label>
                <input type="text" name="order_name"
                       class="form-control <?= isset($errors['order_name']) ? 'is-invalid':'' ?>"
                       placeholder="e.g. Maria's Wedding Gown"
                       value="<?= htmlspecialchars($values['order_name']) ?>" required>
                <?php if (isset($errors['order_name'])): ?>
                  <div class="invalid-feedback"><?= $errors['order_name'] ?></div>
                <?php endif; ?>
              </div>

              <div class="mb-3">
                <label class="form-label">Garment Description <span class="text-danger">*</span></label>
                <textarea name="garment_description" rows="3"
                          class="form-control <?= isset($errors['garment_description']) ? 'is-invalid':'' ?>"
                          placeholder="Describe the garment to be made in detail…"
                          ><?= htmlspecialchars($values['garment_description']) ?></textarea>
                <?php if (isset($errors['garment_description'])): ?>
                  <div class="invalid-feedback"><?= $errors['garment_description'] ?></div>
                <?php endif; ?>
              </div>

              <div class="row g-3 mb-4">
                <div class="col-sm-6">
                  <label class="form-label">Due Date <span class="text-danger">*</span></label>
                  <input type="date" name="due_date"
                         class="form-control <?= isset($errors['due_date']) ? 'is-invalid':'' ?>"
                         min="<?= date('Y-m-d', strtotime('+1 day')) ?>"
                         value="<?= htmlspecialchars($values['due_date']) ?>" required>
                  <?php if (isset($errors['due_date'])): ?>
                    <div class="invalid-feedback"><?= $errors['due_date'] ?></div>
                  <?php endif; ?>
                </div>
                <div class="col-sm-6">
                  <label class="form-label">Additional Notes</label>
                  <input type="text" name="notes" class="form-control"
                         placeholder="Optional…"
                         value="<?= htmlspecialchars($values['notes']) ?>">
                </div>
              </div>

              <hr class="my-4">

              <!-- Section: Pricing -->
              <p class="form-section-label">Pricing and Payment</p>
              <div class="row g-3 mb-4">
                <div class="col-sm-6">
                  <label class="form-label">Total Price (₱) <span class="text-danger">*</span></label>
                  <input type="number" name="price" step="0.01" min="0.01"
                         class="form-control <?= isset($errors['price']) ? 'is-invalid':'' ?>"
                         id="price-input"
                         value="<?= htmlspecialchars($values['price']) ?>" required>
                  <?php if (isset($errors['price'])): ?>
                    <div class="invalid-feedback"><?= $errors['price'] ?></div>
                  <?php endif; ?>
                </div>
                <div class="col-sm-6">
                  <label class="form-label">Down Payment (₱)</label>
                  <input type="number" name="down_payment" step="0.01" min="0"
                         class="form-control <?= isset($errors['down_payment']) ? 'is-invalid':'' ?>"
                         id="dp-input"
                         value="<?= htmlspecialchars($values['down_payment']) ?>">
                  <?php if (isset($errors['down_payment'])): ?>
                    <div class="invalid-feedback"><?= $errors['down_payment'] ?></div>
                  <?php endif; ?>
                </div>
              </div>

              <!-- Balance preview -->
              <div class="alert alert-secondary py-2 small mb-3" id="balance-preview">
                Balance remaining: <strong id="balance-display">
                  ₱<?= is_numeric($values['price']) && is_numeric($values['down_payment'])
                      ? number_format((float)$values['price'] - (float)$values['down_payment'], 2)
                      : '0.00' ?>
                </strong>
              </div>

              <div class="row g-3 mb-4">
                <div class="col-sm-6">
                  <label class="form-label">Payment Method</label>
                  <select name="payment_method" id="payment_method" class="form-select
                          <?= isset($errors['payment_method']) ? 'is-invalid':'' ?>">
                    <option value="">— Select method —</option>
                    <?php foreach (['Cash','GCash','Card','Other'] as $pm): ?>
                      <option value="<?= $pm ?>"
                        <?= $values['payment_method'] === $pm ? 'selected':'' ?>>
                        <?= $pm ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                  <?php if (isset($errors['payment_method'])): ?>
                    <div class="invalid-feedback"><?= $errors['payment_method'] ?></div>
                  <?php endif; ?>
                  <div class="form-text">Required if a down payment is entered.</div>
                </div>
                <div class="col-sm-6" id="payment-other-field"
                     style="display:<?= $values['payment_method'] === 'Other' ? 'block':'none' ?>">
                  <label class="form-label">Specify Payment Method</label>
                  <input type="text" name="payment_other" class="form-control"
                         placeholder="e.g. Bank Transfer"
                         value="<?= htmlspecialchars($values['payment_other']) ?>">
                </div>
              </div>

              <hr class="my-4">

              <!-- Section: Measurements -->
              <p class="form-section-label">Customer Measurements <span class="text-muted" style="font-weight:400;text-transform:none;letter-spacing:0">(in inches — optional)</span></p>
              <div class="row g-3 mb-3">
                <?php
                $measFields = [
                    'bust'     => 'Bust',
                    'waist'    => 'Waist',
                    'hips'     => 'Hips',
                    'length'   => 'Length',
                    'shoulder' => 'Shoulder',
                    'sleeve'   => 'Sleeve',
                ];
                foreach ($measFields as $key => $label):
                ?>
                  <div class="col-sm-4">
                    <label class="form-label"><?= $label ?></label>
                    <div class="input-group">
                      <input type="number" name="<?= $key ?>" step="0.5" min="0"
                             class="form-control"
                             placeholder="0.0"
                             value="<?= htmlspecialchars($values[$key]) ?>">
                      <span class="input-group-text">"</span>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>

              <div class="mb-4">
                <label class="form-label">Measurement Notes</label>
                <textarea name="meas_notes" class="form-control" rows="2"
                          placeholder="Any special fitting notes…"
                          ><?= htmlspecialchars($values['meas_notes']) ?></textarea>
              </div>

              <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary px-4">
                  <i class="bi bi-check-lg me-1"></i> Create Order
                </button>
                <a href="/tailorshop/modules/tailoring/index.php"
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
// Live balance calculation
const priceInput = document.getElementById('price-input');
const dpInput    = document.getElementById('dp-input');
const balDisplay = document.getElementById('balance-display');

function updateBalance() {
    const price = parseFloat(priceInput.value) || 0;
    const dp    = parseFloat(dpInput.value)    || 0;
    const bal   = Math.max(0, price - dp);
    balDisplay.textContent = '₱' + bal.toLocaleString('en-PH', {
        minimumFractionDigits: 2, maximumFractionDigits: 2
    });
}
if (priceInput) priceInput.addEventListener('input', updateBalance);
if (dpInput)    dpInput.addEventListener('input', updateBalance);
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>