<?php
// ============================================================
// modules/tailoring/view.php
// Full order details with measurements, materials, and actions
// ============================================================

require_once __DIR__ . '/../../../includes/auth-guard.php';
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../helpers/rental-helper.php';

$pageTitle  = 'Order Details';
$activePage = 'tailoring';
$role       = $_SESSION['user_role'];

$orderId = (int)($_GET['id'] ?? 0);
if ($orderId === 0) {
    header('Location: /tailorshop/modules/tailoring/index.php');
    exit;
}

$stmt = $pdo->prepare('
    SELECT o.*,
           c.full_name    AS customer_name,
           c.mobile_number,
           c.customer_id,
           u.full_name    AS staff_name,
           DATEDIFF(o.due_date, CURDATE()) AS days_until_due
    FROM tailoring_orders o
    JOIN customers c ON o.customer_id       = c.customer_id
    JOIN users     u ON o.assigned_staff_id = u.user_id
    WHERE o.order_id = ?
');
$stmt->execute([$orderId]);
$order = $stmt->fetch();

if (!$order) {
    $_SESSION['error'] = 'Order not found.';
    header('Location: /tailorshop/modules/tailoring/index.php');
    exit;
}

// Measurements
$meas = $pdo->prepare('SELECT * FROM order_measurements WHERE order_id = ?');
$meas->execute([$orderId]);
$measurements = $meas->fetch();

// Materials used
$mats = $pdo->prepare('
    SELECT omu.*, mi.item_name, mi.brand, mi.unit
    FROM order_materials_used omu
    JOIN material_items mi ON omu.item_id = mi.item_id
    WHERE omu.order_id = ?
');
$mats->execute([$orderId]);
$materials = $mats->fetchAll();

$success = $_SESSION['success'] ?? '';
$error   = $_SESSION['error']   ?? '';
unset($_SESSION['success'], $_SESSION['error']);

$isActive    = in_array($order['status'], ['Pending','In Progress']);
$isReady     = $order['status'] === 'Ready for Pickup';
$isCompleted = $order['status'] === 'Completed';
$isCancelled = $order['status'] === 'Cancelled';
$isLocked    = $isCompleted || $isCancelled;

require_once __DIR__ . '/../../../includes/header.php';
require_once __DIR__ . '/../../../includes/sidebar.php';
?>

<div class="main-content">
  <div class="topbar">
    <span class="topbar-title">
      Order #<?= str_pad($orderId, 4, '0', STR_PAD_LEFT) ?>
    </span>
  </div>

  <div class="page-body">

    <nav class="breadcrumb-nav mb-3">
      <a href="/tailorshop/modules/tailoring/index.php">
        <i class="bi bi-scissors me-1"></i>Tailoring Orders
      </a>
      <span class="mx-2">/</span>
      <span>#<?= str_pad($orderId, 4, '0', STR_PAD_LEFT) ?></span>
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

    <?php if ($isActive && $order['days_until_due'] !== null && $order['days_until_due'] < 0): ?>
      <div class="alert alert-danger d-flex align-items-center gap-2 mb-3">
        <i class="bi bi-exclamation-circle-fill"></i>
        <strong>This order is <?= abs($order['days_until_due']) ?> day<?= abs($order['days_until_due']) > 1 ? 's' : '' ?> past its due date.</strong>
      </div>
    <?php endif; ?>

    <div class="row g-3">

      <!-- Main details -->
      <div class="col-lg-8">

        <!-- Order info card -->
        <div class="card mb-3">
          <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="bi bi-scissors me-2 text-primary"></i>Order Details</span>
            <div class="d-flex align-items-center gap-2">
              <?= orderStatusBadge($order['status']) ?>
              <?php if ($role === 'admin' && $isActive): ?>
                <a href="/tailorshop/modules/tailoring/edit.php?id=<?= $orderId ?>"
                   class="btn btn-sm btn-outline-secondary">
                  <i class="bi bi-pencil"></i>
                </a>
              <?php endif; ?>
            </div>
          </div>
          <div class="card-body p-4">
            <div class="row g-3">

              <div class="col-sm-6">
                <div class="text-muted small mb-1">Customer</div>
                <div class="fw-semibold">
                  <a href="/tailorshop/modules/customers/view.php?id=<?= $order['customer_id'] ?>"
                     class="text-decoration-none">
                    <?= htmlspecialchars($order['customer_name']) ?>
                  </a>
                </div>
                <div class="text-muted" style="font-size:12px">
                  <i class="bi bi-phone me-1"></i><?= htmlspecialchars($order['mobile_number']) ?>
                </div>
              </div>
              <div class="col-sm-6">
                <div class="text-muted small mb-1">Assigned Staff</div>
                <div class="fw-semibold"><?= htmlspecialchars($order['staff_name']) ?></div>
              </div>

              <div class="col-12">
                <div class="text-muted small mb-1">Order Name</div>
                <div class="fw-semibold fs-6"><?= htmlspecialchars($order['order_name']) ?></div>
              </div>

              <div class="col-12">
                <div class="text-muted small mb-1">Garment Description</div>
                <div class="p-3 rounded" style="background:#f9fafb;font-size:13.5px;line-height:1.6">
                  <?= nl2br(htmlspecialchars($order['garment_description'])) ?>
                </div>
              </div>

              <div class="col-sm-6">
                <div class="text-muted small mb-1">Due Date</div>
                <div class="fw-semibold <?= ($order['days_until_due'] ?? 1) < 0 && $isActive ? 'text-danger' : '' ?>">
                  <?= date('F j, Y', strtotime($order['due_date'])) ?>
                  <?php if ($isActive && $order['days_until_due'] !== null): ?>
                    <span class="badge <?= $order['days_until_due'] < 0 ? 'bg-danger' :
                                          ($order['days_until_due'] <= 3 ? 'bg-warning text-dark' : 'bg-secondary') ?>
                                ms-1" style="font-size:10px">
                      <?= $order['days_until_due'] < 0
                          ? abs($order['days_until_due']) . 'd overdue'
                          : ($order['days_until_due'] === 0 ? 'Today' : 'in ' . $order['days_until_due'] . 'd') ?>
                    </span>
                  <?php endif; ?>
                </div>
              </div>
              <?php if ($order['completion_date']): ?>
                <div class="col-sm-6">
                  <div class="text-muted small mb-1">Completion Date</div>
                  <div><?= date('F j, Y', strtotime($order['completion_date'])) ?></div>
                </div>
              <?php endif; ?>

              <?php if ($order['notes']): ?>
                <div class="col-12">
                  <div class="text-muted small mb-1">Notes</div>
                  <div><?= htmlspecialchars($order['notes']) ?></div>
                </div>
              <?php endif; ?>

              <?php if ($order['cancellation_reason']): ?>
                <div class="col-12">
                  <div class="text-muted small mb-1">Cancellation Reason</div>
                  <div class="alert alert-danger py-2 mb-0" style="font-size:13.5px">
                    <?= htmlspecialchars($order['cancellation_reason']) ?>
                  </div>
                </div>
              <?php endif; ?>

            </div>
          </div>
        </div>

        <!-- Payment card -->
        <div class="card mb-3">
          <div class="card-header">
            <i class="bi bi-cash-stack me-2 text-success"></i>Payment Summary
          </div>
          <div class="card-body p-4">
            <div class="row g-3">
              <div class="col-sm-4 text-center">
                <div class="text-muted small mb-1">Total Price</div>
                <div class="fw-bold fs-5">₱<?= number_format($order['price'], 2) ?></div>
              </div>
              <div class="col-sm-4 text-center">
                <div class="text-muted small mb-1">Down Payment</div>
                <div class="fw-bold fs-5 text-success">₱<?= number_format($order['down_payment'], 2) ?></div>
              </div>
              <div class="col-sm-4 text-center">
                <div class="text-muted small mb-1">Balance Due</div>
                <div class="fw-bold fs-5 <?= (float)$order['balance'] > 0 ? 'text-danger' : 'text-success' ?>">
                  <?= (float)$order['balance'] > 0
                      ? '₱' . number_format($order['balance'], 2)
                      : 'Fully Paid' ?>
                </div>
              </div>
              <?php if ($order['payment_method']): ?>
                <div class="col-sm-6">
                  <div class="text-muted small mb-1">Payment Method</div>
                  <div><?= htmlspecialchars($order['payment_method'] === 'Other'
                            ? $order['payment_other'] : $order['payment_method']) ?></div>
                </div>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <!-- Measurements card -->
        <?php if ($measurements): ?>
          <div class="card mb-3">
            <div class="card-header">
              <i class="bi bi-rulers me-2 text-primary"></i>Customer Measurements
            </div>
            <div class="card-body p-4">
              <div class="row g-3">
                <?php
                $measDisplay = [
                    'bust'     => 'Bust',
                    'waist'    => 'Waist',
                    'hips'     => 'Hips',
                    'length'   => 'Length',
                    'shoulder' => 'Shoulder',
                    'sleeve'   => 'Sleeve',
                ];
                foreach ($measDisplay as $key => $label):
                  if ($measurements[$key] !== null):
                ?>
                  <div class="col-sm-4 col-6">
                    <div class="text-muted small mb-1"><?= $label ?></div>
                    <div class="fw-semibold"><?= $measurements[$key] ?>"</div>
                  </div>
                <?php endif; endforeach; ?>
                <?php if ($measurements['notes']): ?>
                  <div class="col-12">
                    <div class="text-muted small mb-1">Measurement Notes</div>
                    <div><?= htmlspecialchars($measurements['notes']) ?></div>
                  </div>
                <?php endif; ?>
              </div>
            </div>
          </div>
        <?php endif; ?>

        <!-- Materials card -->
        <div class="card">
          <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="bi bi-box-seam me-2 text-warning"></i>Materials Used</span>
            <?php if (!$isLocked && in_array($order['status'], ['In Progress'])): ?>
              <a href="/tailorshop/modules/tailoring/materials.php?id=<?= $orderId ?>"
                 class="btn btn-sm btn-outline-warning">
                <i class="bi bi-plus-lg me-1"></i> Add Materials
              </a>
            <?php endif; ?>
          </div>
          <div class="table-responsive">
            <table class="table mb-0">
              <thead>
                <tr><th>Item</th><th>Brand</th><th>Qty Used</th><th>Unit</th></tr>
              </thead>
              <tbody>
                <?php if (empty($materials)): ?>
                  <tr>
                    <td colspan="4" class="text-center text-muted py-3">
                      No materials logged yet.
                      <?php if ($order['status'] === 'Pending'): ?>
                        Materials can be logged once the order is In Progress.
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($materials as $m): ?>
                    <tr>
                      <td class="fw-medium"><?= htmlspecialchars($m['item_name']) ?></td>
                      <td><?= htmlspecialchars($m['brand'] ?? '—') ?></td>
                      <td><?= rtrim(rtrim(number_format($m['quantity_used'], 2), '0'), '.') ?></td>
                      <td><?= htmlspecialchars($m['unit']) ?></td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

      </div>

      <!-- Actions panel -->
      <div class="col-lg-4">
        <div class="card">
          <div class="card-header">
            <i class="bi bi-lightning me-2 text-warning"></i>Update Status
          </div>
          <div class="card-body p-3">
            <?php if (!$isLocked): ?>
              <form method="POST"
                    action="/tailorshop/modules/tailoring/update-status.php">
                <input type="hidden" name="order_id" value="<?= $orderId ?>">

                <div class="mb-3">
                  <label class="form-label fw-medium">Change Status</label>
                  <select name="new_status" class="form-select form-select-sm">
                    <?php
                    $statusFlow = ['Pending','In Progress','Ready for Pickup','Completed'];
                    foreach ($statusFlow as $s):
                    ?>
                      <option value="<?= $s ?>"
                        <?= $order['status'] === $s ? 'selected' : '' ?>>
                        <?= $s ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>

                <!-- Balance payment on Completed -->
                <div id="balance-payment-section"
                     style="display:<?= $order['status'] === 'Ready for Pickup' ? 'block':'none' ?>">
                  <div class="mb-2">
                    <label class="form-label fw-medium small">Balance Payment (₱)</label>
                    <input type="number" name="balance_payment"
                           step="0.01" min="0"
                           class="form-control form-control-sm"
                           placeholder="<?= number_format($order['balance'], 2) ?>"
                           value="<?= number_format($order['balance'], 2) ?>">
                  </div>
                  <div class="mb-3">
                    <label class="form-label fw-medium small">Payment Method</label>
                    <select name="balance_payment_method" class="form-select form-select-sm">
                      <?php foreach (['Cash','GCash','Card','Other'] as $pm): ?>
                        <option value="<?= $pm ?>"><?= $pm ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                </div>

                <div class="d-grid mb-2">
                  <button type="submit" class="btn btn-primary btn-sm">
                    <i class="bi bi-arrow-right-circle me-1"></i> Update Status
                  </button>
                </div>
              </form>

              <hr>

              <?php if (!$isCancelled): ?>
                <div class="d-grid">
                  <a href="/tailorshop/modules/tailoring/cancel.php?id=<?= $orderId ?>"
                     class="btn btn-outline-danger btn-sm">
                    <i class="bi bi-x-circle me-1"></i> Cancel Order
                  </a>
                </div>
              <?php endif; ?>

            <?php else: ?>
              <p class="text-muted small text-center py-2 mb-0">
                This order is <?= strtolower($order['status']) ?>.
                No further actions available.
              </p>
            <?php endif; ?>

            <hr>
            <div class="d-grid">
              <a href="/tailorshop/modules/customers/view.php?id=<?= $order['customer_id'] ?>"
                 class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-person me-1"></i> View Customer
              </a>
            </div>
          </div>
        </div>
      </div>

    </div>
  </div>
</div>

<script>
const statusSelect = document.querySelector('select[name="new_status"]');
const balSection   = document.getElementById('balance-payment-section');
if (statusSelect && balSection) {
    statusSelect.addEventListener('change', () => {
        balSection.style.display =
            statusSelect.value === 'Completed' ? 'block' : 'none';
    });
}
</script>

<?php require_once __DIR__ . '/../../../includes/footer.php'; ?>