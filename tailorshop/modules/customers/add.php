<?php
// ============================================================
// modules/customers/add.php
// Add new customer record
// ============================================================

require_once __DIR__ . '/../../../includes/auth-guard.php';
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../helpers/audit-helper.php';

$pageTitle  = 'Add Customer';
$activePage = 'customers';

$errors = [];
$values = [
    'full_name'     => '',
    'mobile_number' => '',
    'address'       => '',
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
        $dup = $pdo->prepare('SELECT COUNT(*) FROM customers WHERE mobile_number = ?');
        $dup->execute([$values['mobile_number']]);
        if ($dup->fetchColumn() > 0)
            $errors['mobile_number'] = 'A customer with this mobile number already exists.';
    }

    if (empty($errors)) {
        $pdo->prepare('
            INSERT INTO customers (full_name, mobile_number, address)
            VALUES (?, ?, ?)
        ')->execute([
            $values['full_name'],
            $values['mobile_number'],
            $values['address'] ?: null,
        ]);

        $newId = (int)$pdo->lastInsertId();
        auditCreate($_SESSION['user_id'], 'Customers', 'customers', $newId, $values);

        $_SESSION['success'] = 'Customer "' . $values['full_name'] . '" added successfully.';
        header('Location: /tailorshop/modules/customers/view.php?id=' . $newId);
        exit;
    }
}

require_once __DIR__ . '/../../../includes/header.php';
require_once __DIR__ . '/../../../includes/sidebar.php';
?>

<div class="main-content">
  <div class="topbar"><span class="topbar-title">Add Customer</span></div>

  <div class="page-body">

    <nav class="breadcrumb-nav mb-3">
      <a href="/tailorshop/modules/customers/index.php">
        <i class="bi bi-people me-1"></i>Customers
      </a>
      <span class="mx-2">/</span><span>Add Customer</span>
    </nav>

    <div class="row justify-content-center">
      <div class="col-lg-6">
        <div class="card">
          <div class="card-header">
            <i class="bi bi-person-plus me-2 text-primary"></i>New Customer
          </div>
          <div class="card-body p-4">
            <form method="POST" novalidate>

              <div class="mb-3">
                <label class="form-label">
                  Full Name <span class="text-danger">*</span>
                </label>
                <input type="text" name="full_name"
                       class="form-control <?= isset($errors['full_name']) ? 'is-invalid' : '' ?>"
                       placeholder="e.g. Maria Santos"
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
                         placeholder="09XXXXXXXXX"
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
                          placeholder="Street, Barangay, City… (optional)"
                          ><?= htmlspecialchars($values['address']) ?></textarea>
              </div>

              <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary px-4">
                  <i class="bi bi-person-plus me-1"></i> Add Customer
                </button>
                <a href="/tailorshop/modules/customers/index.php"
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