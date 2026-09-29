<?php
// ============================================================
// modules/sales/index.php
// Income records with filter by type, payment method, date
// Admin only
// ============================================================

require_once __DIR__ . '/../../../includes/auth-guard.php';
require_once __DIR__ . '/../../../includes/role-guard.php';
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../helpers/sales-helper.php';

$pageTitle  = 'Sales Records';
$activePage = 'sales';

// ── Filters ────────────────────────────────────────────────
$typeFilter   = trim($_GET['type']       ?? '');
$methodFilter = trim($_GET['method']     ?? '');
$dateFrom     = trim($_GET['date_from']  ?? date('Y-m-01'));
$dateTo       = trim($_GET['date_to']    ?? date('Y-m-d'));
$search       = trim($_GET['q']          ?? '');

$where  = ['ir.date_recorded BETWEEN ? AND ?'];
$params = [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'];

if ($typeFilter !== '') {
    $where[]  = 'ir.income_type = ?';
    $params[] = $typeFilter;
}
if ($methodFilter !== '') {
    $where[]  = 'ir.payment_method = ?';
    $params[] = $methodFilter;
}
if ($search !== '') {
    $where[]  = 'c.full_name LIKE ?';
    $params[] = '%' . $search . '%';
}

$whereSQL = 'WHERE ' . implode(' AND ', $where);

$stmt = $pdo->prepare("
    SELECT ir.*,
           sr.sale_type, sr.notes,
           c.full_name    AS customer_name,
           c.customer_id,
           u.full_name    AS processed_by_name
    FROM income_records ir
    JOIN sales_records sr ON ir.sale_id     = sr.sale_id
    JOIN customers     c  ON sr.customer_id = c.customer_id
    JOIN users         u  ON sr.processed_by = u.user_id
    $whereSQL
    ORDER BY ir.date_recorded DESC
");
$stmt->execute($params);
$records = $stmt->fetchAll();

// Totals for this filtered view
$total        = array_sum(array_column($records, 'amount'));
$totalByType  = [];
$totalByMethod = [];
foreach ($records as $r) {
    $totalByType[$r['income_type']]      = ($totalByType[$r['income_type']] ?? 0) + $r['amount'];
    $totalByMethod[$r['payment_method']] = ($totalByMethod[$r['payment_method']] ?? 0) + $r['amount'];
}

$types   = ['Garment Sale','Rental Fee','Tailoring Payment'];
$methods = ['Cash','GCash','Card','Other'];

$success = $_SESSION['success'] ?? '';
$error   = $_SESSION['error']   ?? '';
unset($_SESSION['success'], $_SESSION['error']);

require_once __DIR__ . '/../../../includes/header.php';
require_once __DIR__ . '/../../../includes/sidebar.php';
?>

<div class="main-content">
  <div class="topbar">
    <span class="topbar-title">Sales Records</span>
    <span class="text-muted small" id="current-date"></span>
  </div>

  <div class="page-body">

    <?php if ($success): ?>
      <div class="alert alert-success alert-dismissible fade show d-flex align-items-center gap-2">
        <i class="bi bi-check-circle-fill"></i><?= htmlspecialchars($success) ?>
        <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
      </div>
    <?php endif; ?>

    <!-- Page header -->
    <div class="page-header">
      <div>
        <h5>Sales Records</h5>
        <p class="text-muted small mb-0">
          <?= count($records) ?> record<?= count($records) !== 1 ? 's' : '' ?> found
          &nbsp;·&nbsp;
          Total: <strong class="text-success">₱<?= number_format($total, 2) ?></strong>
        </p>
      </div>
      <div class="d-flex gap-2">
        <a href="/tailorshop/modules/sales/reports.php" class="btn btn-outline-secondary">
          <i class="bi bi-bar-chart-line me-1"></i> Income Report
        </a>
        <a href="/tailorshop/modules/sales/export.php?<?= http_build_query($_GET) ?>"
           class="btn btn-outline-success">
          <i class="bi bi-download me-1"></i> Export
        </a>
      </div>
    </div>

    <!-- Summary pills -->
    <?php if (!empty($totalByType)): ?>
      <div class="d-flex flex-wrap gap-2 mb-3">
        <?php
        $typeColors = [
            'Garment Sale'      => ['bg'=>'#FEF3C7','color'=>'#92400E'],
            'Rental Fee'        => ['bg'=>'#EFF6FF','color'=>'#1D4ED8'],
            'Tailoring Payment' => ['bg'=>'#EDE9FE','color'=>'#5B21B6'],
        ];
        foreach ($totalByType as $type => $amt):
            $tc = $typeColors[$type] ?? ['bg'=>'#F3F4F6','color'=>'#374151'];
        ?>
          <div style="background:<?= $tc['bg'] ?>;color:<?= $tc['color'] ?>;
                      padding:4px 12px;border-radius:20px;font-size:12.5px;font-weight:500">
            <?= htmlspecialchars($type) ?>: ₱<?= number_format($amt, 2) ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <!-- Filters -->
    <div class="card mb-3">
      <div class="card-body py-2 px-3">
        <form method="GET" class="d-flex gap-2 align-items-center flex-wrap">
          <i class="bi bi-search text-muted"></i>
          <input type="text" name="q"
                 class="form-control border-0 shadow-none ps-0"
                 placeholder="Search by customer name…"
                 value="<?= htmlspecialchars($search) ?>"
                 style="font-size:13.5px;min-width:150px;flex:1">

          <select name="type" class="form-select form-select-sm" style="width:auto"
                  onchange="this.form.submit()">
            <option value="">All Types</option>
            <?php foreach ($types as $t): ?>
              <option value="<?= $t ?>"
                <?= $typeFilter === $t ? 'selected' : '' ?>><?= $t ?></option>
            <?php endforeach; ?>
          </select>

          <select name="method" class="form-select form-select-sm" style="width:auto"
                  onchange="this.form.submit()">
            <option value="">All Methods</option>
            <?php foreach ($methods as $m): ?>
              <option value="<?= $m ?>"
                <?= $methodFilter === $m ? 'selected' : '' ?>><?= $m ?></option>
            <?php endforeach; ?>
          </select>

          <div class="d-flex align-items-center gap-1" style="font-size:12.5px">
            <input type="date" name="date_from" class="form-control form-control-sm"
                   value="<?= htmlspecialchars($dateFrom) ?>" style="width:auto">
            <span class="text-muted">to</span>
            <input type="date" name="date_to" class="form-control form-control-sm"
                   value="<?= htmlspecialchars($dateTo) ?>" style="width:auto">
          </div>

          <button class="btn btn-sm btn-primary">Filter</button>
          <a href="/tailorshop/modules/sales/index.php"
             class="btn btn-sm btn-outline-secondary">Reset</a>
        </form>
      </div>
    </div>

    <!-- Records table -->
    <div class="card">
      <div class="table-responsive">
        <table class="table mb-0">
          <thead>
            <tr>
              <th>#</th>
              <th>Date</th>
              <th>Customer</th>
              <th>Type</th>
              <th>Payment Method</th>
              <th>Amount</th>
              <th>Processed By</th>
              <th>Notes</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($records)): ?>
              <tr>
                <td colspan="8" class="text-center text-muted py-5">
                  <i class="bi bi-cash-stack fs-3 d-block mb-2"></i>
                  No sales records found for the selected filters.
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($records as $r):
                $typeColors = [
                    'Garment Sale'      => 'bg-warning bg-opacity-10 text-warning',
                    'Rental Fee'        => 'bg-primary bg-opacity-10 text-primary',
                    'Tailoring Payment' => 'bg-purple bg-opacity-10',
                ];
              ?>
                <tr>
                  <td class="text-muted" style="font-size:12px">
                    #<?= str_pad($r['income_id'], 4, '0', STR_PAD_LEFT) ?>
                  </td>
                  <td style="font-size:12.5px">
                    <?= date('M j, Y', strtotime($r['date_recorded'])) ?>
                    <div class="text-muted" style="font-size:11px">
                      <?= date('g:i A', strtotime($r['date_recorded'])) ?>
                    </div>
                  </td>
                  <td>
                    <a href="/tailorshop/modules/customers/view.php?id=<?= $r['customer_id'] ?>"
                       class="text-decoration-none fw-medium">
                      <?= htmlspecialchars($r['customer_name']) ?>
                    </a>
                  </td>
                  <td>
                    <?php
                    $typeBadge = [
                        'Garment Sale'      => ['bg'=>'#FEF3C7','color'=>'#92400E'],
                        'Rental Fee'        => ['bg'=>'#EFF6FF','color'=>'#1D4ED8'],
                        'Tailoring Payment' => ['bg'=>'#EDE9FE','color'=>'#5B21B6'],
                    ];
                    $tb = $typeBadge[$r['income_type']] ?? ['bg'=>'#F3F4F6','color'=>'#374151'];
                    ?>
                    <span style="background:<?= $tb['bg'] ?>;color:<?= $tb['color'] ?>;
                                 padding:2px 10px;border-radius:20px;font-size:11.5px;font-weight:500">
                      <?= htmlspecialchars($r['income_type']) ?>
                    </span>
                  </td>
                  <td>
                    <span class="badge bg-secondary bg-opacity-10 text-secondary rounded-pill"
                          style="font-size:11.5px">
                      <?= htmlspecialchars($r['payment_method']) ?>
                    </span>
                  </td>
                  <td class="fw-semibold text-success">
                    ₱<?= number_format($r['amount'], 2) ?>
                  </td>
                  <td class="text-muted" style="font-size:12.5px">
                    <?= htmlspecialchars($r['processed_by_name']) ?>
                  </td>
                  <td class="text-muted" style="font-size:12px;max-width:160px;
                              white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
                    <?= htmlspecialchars($r['notes'] ?? '—') ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
          <?php if (!empty($records)): ?>
            <tfoot>
              <tr style="background:#f9fafb">
                <td colspan="5" class="text-end fw-semibold" style="font-size:13.5px">
                  Total
                </td>
                <td class="fw-bold text-success">
                  ₱<?= number_format($total, 2) ?>
                </td>
                <td colspan="2"></td>
              </tr>
            </tfoot>
          <?php endif; ?>
        </table>
      </div>
    </div>

  </div>
</div>

<?php require_once __DIR__ . '/../../../includes/footer.php'; ?>