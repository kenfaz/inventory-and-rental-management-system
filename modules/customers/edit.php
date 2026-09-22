<?php
// ============================================================
// modules/customers/edit.php
// Edit customer record
// ============================================================

require_once __DIR__ . '/../../../includes/auth-guard.php';
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../helpers/audit-helper.php';

$pageTitle  = 'Edit Customer';
$activePage = 'customers';

$customerId = (int)($_GET['id'] ?? 0);
if ($customerId === 0) {
    header('Location: /tailorshop/modules/customers/index.php');
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM customers WHERE customer_id = ?');
$stmt->execute([$customerId]);
$customer = $stmt->fetch();

if (!$customer) {
    $_SESSION['error'] = 'Customer not found.';
    header('Location: /tailorshop/modules/customers/index.php');
    exit;
}

$errors = [];
$values = [
    'full_name'     => $customer['full_name'],
    'mobile_number' => $customer['mobile_number'],
    'address'       => $customer['address'] ?? '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $values = [
        'full_name'     => trim($_POST['full_name']     ?? ''),
        'mobile_number' => trim($_POST['mobile_number'] ?? ''),
        'address'       => trim($_POST['address']       ?? ''),
    ];

    if ($values['full_name'] === '')
        $errors['full_name'] = 'Full name is required.';

    if ($values['mobile_number'] === '') {
        $errors['mobile_number'] = 'Mobile number is required.';
    } elseif (!preg_match('/^(09|\+639)\d{9}$/', $values['mobile_number'])) {
        $errors['mobile_number'] = 'Enter a valid PH mobile number (e.g. 09171234567).';
    } else {
        $dup = $pdo->prepare('
            SELECT COUNT(*) FROM customers
            WHERE mobile_number = ? AND customer_id != ?
        ');
        $dup->execute([$values['mobile_number'], $customerId]);
        if ($dup->fetchColumn() > 0)
            $errors['mobile_number'] = 'Another customer already has this mobile number.';
    }

    if (empty($errors)) {
        $old = [
            'full_name'     => $customer['full_name'],
            'mobile_number' => $customer['mobile_number'],
            'address'       => $customer['address'],
        ];

        $pdo->prepare('
            UPDATE customers
            SET full_name = ?, mobile_number = ?, address = ?
            WHERE customer_id = ?
        ')->execute([
            $values['full_name'],
            $values['mobile_number'],
            $values['address'] ?: null,
            $customerId,
        ]);

        auditUpdate(
            $_SESSION['user_id'], 'Customers',
            'customers', $customerId, $old, $values
        );

        $_SESSION['success'] = 'Customer updated successfully.';
        header('Location: /tailorshop/modules/customers/view.php?id=' . $customerId);
        exit;
    }
}

require_once __DIR__ . '/../../../includes/header.php';
require_once __DIR__ . '/../../../includes/sidebar.php';
?>

<div class="main-content">
  <div class="topbar"><span class="topbar-title">Edit Customer</span></div>

  <div class="page-body">

    <nav class="breadcrumb-nav mb-3">
      <a href="/tailorshop/modules/customers/index.php">
        <i class="bi bi-people me-1"></i>Customers
      </a>
      <span class="mx-2">/</span>
      <a href="/tailorshop/modules/customers/view.php?id=<?= $customerId ?>">
        <?= htmlspecialchars($customer['full_name']) ?>
      </a>
      <span class="mx-2">/</span><span>Edit</span>
    </nav>

    <div class="row justify-content-center">
      <div class="col-lg-6">
        <div class="card">
          <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="bi bi-pencil me-2 text-primary"></i>Edit Customer</span>
            <span class="text-muted small">ID #<?= $customerId ?></span>
          </div>
          <div class="card-body p-4">
            <form method="POST" novalidate>

              <div class="mb-3">
                <label class="form-label">
                  Full Name <span class="text-danger">*</span>
                </label>
                <input type="text" name="full_name"
                       class="form-control <?= isset($errors['full_name']) ? 'is-invalid' : '' ?>"
                       value="<?= htmlspecialchars($values['full_name']) ?>"
                       required autofocus>
                <?php if (isset($errors['full_name'])): ?>
                  <div class="invalid-feedback"><?= $errors['full_name'] ?></div>
                <?php endif; ?>
              </div>

              <div class="mb-3">
                <label class="form-label">
                  Mobile Number <span class="text-danger">*</span>
                </label>
                <div class="input-group">
                  <span class="input-group-text"><i class="bi bi-phone"></i></span>
                  <input type="text" name="mobile_number"
                         class="form-control <?= isset($errors['mobile_number']) ? 'is-invalid' : '' ?>"
                         value="<?= htmlspecialchars($values['mobile_number']) ?>"
                         required>
                  <?php if (isset($errors['mobile_number'])): ?>
                    <div class="invalid-feedback"><?= $errors['mobile_number'] ?></div>
                  <?php endif; ?>
                </div>
              </div>

              <div class="mb-4">
                <label class="form-label">Address</label>
                <textarea name="address" class="form-control" rows="2"
                          ><?= htmlspecialchars($values['address']) ?></textarea>
              </div>

              <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary px-4">
                  <i class="bi bi-check-lg me-1"></i> Save Changes
                </button>
                <a href="/tailorshop/modules/customers/view.php?id=<?= $customerId ?>"
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