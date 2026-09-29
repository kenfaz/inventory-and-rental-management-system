<?php
// ============================================================
// modules/tailoring/edit.php
// Edit tailoring order details — admin only, active orders only
// ============================================================

require_once __DIR__ . '/../../includes/auth-guard.php';
require_once __DIR__ . '/../../includes/role-guard.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../helpers/audit-helper.php';

$pageTitle  = 'Edit Order';
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

if (!$order || in_array($order['status'], ['Completed','Cancelled'])) {
    $_SESSION['error'] = 'This order cannot be edited.';
    header('Location: /tailorshop/modules/tailoring/view.php?id=' . $orderId);
    exit;
}

// Fetch measurements
$meas = $pdo->prepare('SELECT * FROM order_measurements WHERE order_id = ?');
$meas->execute([$orderId]);
$measurements = $meas->fetch();

$staff = $pdo->query("
    SELECT user_id, full_name FROM users
    WHERE status = 'active' ORDER BY full_name ASC
")->fetchAll();

$errors = [];
$values = [
    'assigned_staff_id'   => $order['assigned_staff_id'],
    'order_name'          => $order['order_name'],
    'garment_description' => $order['garment_description'],
    'price'               => $order['price'],
    'down_payment'        => $order['down_payment'],
    'payment_method'      => $order['payment_method'] ?? '',
    'payment_other'       => $order['payment_other']  ?? '',
    'due_date'            => $order['due_date'],
    'notes'               => $order['notes']          ?? '',
    'bust'                => $measurements['bust']     ?? '',
    'waist'               => $measurements['waist']    ?? '',
    'hips'                => $measurements['hips']     ?? '',
    'length'              => $measurements['length']   ?? '',
    'shoulder'            => $measurements['shoulder'] ?? '',
    'sleeve'              => $measurements['sleeve']   ?? '',
    'meas_notes'          => $measurements['notes']    ?? '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $values = [
        'assigned_staff_id'   => trim($_POST['assigned_staff_id']   ?? ''),
        'order_name'          => trim($_POST['order_name']          ?? ''),
        'garment_description' => trim($_POST['garment_description'] ?? ''),
        'price'               => trim($_POST['price']               ?? ''),
        'down_payment'        => trim($_POST['down_payment']        ?? '0'),
        'payment_method'      => trim($_POST['payment_method']      ?? ''),
        'payment_other'       => trim($_POST['payment_other']       ?? ''),
        'due_date'            => trim($_POST['due_date']            ?? ''),
        'notes'               => trim($_POST['notes']               ?? ''),
        'bust'                => trim($_POST['bust']                ?? ''),
        'waist'               => trim($_POST['waist']               ?? ''),
        'hips'                => trim($_POST['hips']                ?? ''),
        'length'              => trim($_POST['length']              ?? ''),
        'shoulder'            => trim($_POST['shoulder']            ?? ''),
        'sleeve'              => trim($_POST['sleeve']              ?? ''),
        'meas_notes'          => trim($_POST['meas_notes']          ?? ''),
    ];

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
    if ($values['due_date'] === '')
        $errors['due_date'] = 'Due date is required.';

    if (empty($errors)) {
        $old = [
            'order_name'  => $order['order_name'],
            'price'       => $order['price'],
            'down_payment'=> $order['down_payment'],
            'due_date'    => $order['due_date'],
        ];

        $pdo->prepare('
            UPDATE tailoring_orders
            SET assigned_staff_id   = ?,
                order_name          = ?,
                garment_description = ?,
                price               = ?,
                down_payment        = ?,
                payment_method      = ?,
                payment_other       = ?,
                due_date            = ?,
                notes               = ?
            WHERE order_id = ?
        ')->execute([
            (int)$values['assigned_staff_id'],
            $values['order_name'],
            $values['garment_description'],
            (float)$values['price'],
            (float)$values['down_payment'],
            $values['payment_method'] ?: null,
            $values['payment_other']  ?: null,
            $values['due_date'],
            $values['notes'] ?: null,
            $orderId,
        ]);

        // Update or insert measurements
        $measExists = $pdo->prepare('SELECT COUNT(*) FROM order_measurements WHERE order_id = ?');
        $measExists->execute([$orderId]);

        $measData = [
            $values['bust']     !== '' ? (float)$values['bust']     : null,
            $values['waist']    !== '' ? (float)$values['waist']    : null,
            $values['hips']     !== '' ? (float)$values['hips']     : null,
            $values['length']   !== '' ? (float)$values['length']   : null,
            $values['shoulder'] !== '' ? (float)$values['shoulder'] : null,
            $values['sleeve']   !== '' ? (float)$values['sleeve']   : null,
            $values['meas_notes'] ?: null,
        ];

        if ($measExists->fetchColumn() > 0) {
            $pdo->prepare('
                UPDATE order_measurements
                SET bust = ?, waist = ?, hips = ?, length = ?,
                    shoulder = ?, sleeve = ?, notes = ?
                WHERE order_id = ?
            ')->execute(array_merge($measData, [$orderId]));
        } else {
            $pdo->prepare('
                INSERT INTO order_measurements
                    (order_id, bust, waist, hips, length, shoulder, sleeve, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ')->execute(array_merge([$orderId], $measData));
        }

        auditUpdate($_SESSION['user_id'], 'Tailoring Orders',
                    'tailoring_orders', $orderId, $old, $values);

        $_SESSION['success'] = 'Order updated successfully.';
        header('Location: /tailorshop/modules/tailoring/view.php?id=' . $orderId);
        exit;
    }
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="main-content">
  <div class="topbar"><span class="topbar-title">Edit Order</span></div>
  <div class="page-body">

    <nav class="breadcrumb-nav mb-3">
      <a href="/tailorshop/modules/tailoring/index.php">
        <i class="bi bi-scissors me-1"></i>Tailoring Orders
      </a>
      <span class="mx-2">/</span>
      <a href="/tailorshop/modules/tailoring/view.php?id=<?= $orderId ?>">
        #<?= str_pad($orderId, 4, '0', STR_PAD_LEFT) ?>
      </a>
      <span class="mx-2">/</span><span>Edit</span>
    </nav>

    <div class="row justify-content-center">
      <div class="col-lg-8">
        <div class="card">
          <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="bi bi-pencil me-2 text-primary"></i>Edit Order</span>
            <span class="text-muted small">
              <?= htmlspecialchars($order['customer_name']) ?>
            </span>
          </div>
          <div class="card-body p-4">
            <form method="POST" novalidate>

              <p class="form-section-label">Assignment</p>
              <div class="mb-4">
                <label class="form-label">Assigned Staff <span class="text-danger">*</span></label>
                <select name="assigned_staff_id"
                        class="form-select <?= isset($errors['assigned_staff_id']) ? 'is-invalid':'' ?>">
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

              <hr class="my-4">
              <p class="form-section-label">Order Details</p>

              <div class="mb-3">
                <label class="form-label">Order Name <span class="text-danger">*</span></label>
                <input type="text" name="order_name"
                       class="form-control <?= isset($errors['order_name']) ? 'is-invalid':'' ?>"
                       value="<?= htmlspecialchars($values['order_name']) ?>" required>
                <?php if (isset($errors['order_name'])): ?>
                  <div class="invalid-feedback"><?= $errors['order_name'] ?></div>
                <?php endif; ?>
              </div>

              <div class="mb-3">
                <label class="form-label">Garment Description <span class="text-danger">*</span></label>
                <textarea name="garment_description" rows="3"
                          class="form-control <?= isset($errors['garment_description']) ? 'is-invalid':'' ?>"
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
                         value="<?= htmlspecialchars($values['due_date']) ?>">
                  <?php if (isset($errors['due_date'])): ?>
                    <div class="invalid-feedback"><?= $errors['due_date'] ?></div>
                  <?php endif; ?>
                </div>
                <div class="col-sm-6">
                  <label class="form-label">Notes</label>
                  <input type="text" name="notes" class="form-control"
                         value="<?= htmlspecialchars($values['notes']) ?>">
                </div>
              </div>

              <hr class="my-4">
              <p class="form-section-label">Pricing</p>

              <div class="row g-3 mb-3">
                <div class="col-sm-6">
                  <label class="form-label">Total Price (₱) <span class="text-danger">*</span></label>
                  <input type="number" name="price" id="price-input"
                         step="0.01" min="0.01"
                         class="form-control <?= isset($errors['price']) ? 'is-invalid':'' ?>"
                         value="<?= htmlspecialchars($values['price']) ?>">
                  <?php if (isset($errors['price'])): ?>
                    <div class="invalid-feedback"><?= $errors['price'] ?></div>
                  <?php endif; ?>
                </div>
                <div class="col-sm-6">
                  <label class="form-label">Down Payment (₱)</label>
                  <input type="number" name="down_payment" id="dp-input"
                         step="0.01" min="0"
                         class="form-control <?= isset($errors['down_payment']) ? 'is-invalid':'' ?>"
                         value="<?= htmlspecialchars($values['down_payment']) ?>">
                  <?php if (isset($errors['down_payment'])): ?>
                    <div class="invalid-feedback"><?= $errors['down_payment'] ?></div>
                  <?php endif; ?>
                </div>
              </div>

              <div class="alert alert-secondary py-2 small mb-3">
                Balance remaining: <strong id="balance-display">
                  ₱<?= number_format($order['price'] - $order['down_payment'], 2) ?>
                </strong>
              </div>

              <div class="row g-3 mb-4">
                <div class="col-sm-6">
                  <label class="form-label">Payment Method</label>
                  <select name="payment_method" id="payment_method" class="form-select">
                    <option value="">— Select —</option>
                    <?php foreach (['Cash','GCash','Card','Other'] as $pm): ?>
                      <option value="<?= $pm ?>"
                        <?= $values['payment_method'] === $pm ? 'selected':'' ?>><?= $pm ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-sm-6" id="payment-other-field"
                     style="display:<?= $values['payment_method'] === 'Other' ? 'block':'none' ?>">
                  <label class="form-label">Specify</label>
                  <input type="text" name="payment_other" class="form-control"
                         value="<?= htmlspecialchars($values['payment_other']) ?>">
                </div>
              </div>

              <hr class="my-4">
              <p class="form-section-label">Measurements (inches)</p>

              <div class="row g-3 mb-4">
                <?php
                $measFields = [
                    'bust'=>'Bust','waist'=>'Waist','hips'=>'Hips',
                    'length'=>'Length','shoulder'=>'Shoulder','sleeve'=>'Sleeve'
                ];
                foreach ($measFields as $key => $label):
                ?>
                  <div class="col-sm-4">
                    <label class="form-label"><?= $label ?></label>
                    <div class="input-group">
                      <input type="number" name="<?= $key ?>" step="0.5" min="0"
                             class="form-control" placeholder="0.0"
                             value="<?= htmlspecialchars($values[$key]) ?>">
                      <span class="input-group-text">"</span>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>

              <div class="mb-4">
                <label class="form-label">Measurement Notes</label>
                <textarea name="meas_notes" class="form-control" rows="2"
                          ><?= htmlspecialchars($values['meas_notes']) ?></textarea>
              </div>

              <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary px-4">
                  <i class="bi bi-check-lg me-1"></i> Save Changes
                </button>
                <a href="/tailorshop/modules/tailoring/view.php?id=<?= $orderId ?>"
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
const priceInput = document.getElementById('price-input');
const dpInput    = document.getElementById('dp-input');
const balDisplay = document.getElementById('balance-display');
function updateBalance() {
    const price = parseFloat(priceInput.value) || 0;
    const dp    = parseFloat(dpInput.value)    || 0;
    balDisplay.textContent = '₱' + Math.max(0, price - dp).toLocaleString('en-PH', {
        minimumFractionDigits: 2, maximumFractionDigits: 2
    });
}
if (priceInput) priceInput.addEventListener('input', updateBalance);
if (dpInput)    dpInput.addEventListener('input', updateBalance);
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>