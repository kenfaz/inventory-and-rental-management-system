<?php
// ============================================================
// modules/logs/login-logs.php
// Login and logout history — admin only
// ============================================================

require_once __DIR__ . '/../../includes/auth-guard.php';
require_once __DIR__ . '/../../includes/role-guard.php';
require_once __DIR__ . '/../../config/db.php';

$pageTitle  = 'Login Logs';
$activePage = 'logs';

$search   = trim($_GET['q']    ?? '');
$dateFrom = trim($_GET['from'] ?? date('Y-m-d', strtotime('-7 days')));
$dateTo   = trim($_GET['to']   ?? date('Y-m-d'));

$where  = ['ll.timestamp BETWEEN ? AND ?'];
$params = [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'];

if ($search !== '') {
    $where[]  = '(u.full_name LIKE ? OR u.email LIKE ? OR ll.ip_address LIKE ?)';
    $like     = '%' . $search . '%';
    $params   = array_merge($params, [$like, $like, $like]);
}

$whereSQL = 'WHERE ' . implode(' AND ', $where);

$stmt = $pdo->prepare("
    SELECT ll.*, u.full_name, u.email, u.role
    FROM login_logs ll
    JOIN users u ON ll.user_id = u.user_id
    $whereSQL
    ORDER BY ll.timestamp DESC
    LIMIT 200
");
$stmt->execute($params);
$logs = $stmt->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="main-content">
  <div class="topbar"><span class="topbar-title">Login Logs</span></div>

  <div class="page-body">

    <div class="page-header">
      <div>
        <h5>Login Logs</h5>
        <p class="text-muted small mb-0">
          Showing <?= count($logs) ?> of the most recent records
        </p>
      </div>
    </div>

    <!-- Filter -->
    <div class="card mb-3">
      <div class="card-body py-2 px-3">
        <form method="GET" class="d-flex gap-2 align-items-center flex-wrap">
          <i class="bi bi-search text-muted"></i>
          <input type="text" name="q"
                 class="form-control border-0 shadow-none ps-0"
                 placeholder="Search by name, email, or IP…"
                 value="<?= htmlspecialchars($search) ?>"
                 style="font-size:13.5px;min-width:180px;flex:1">
          <div class="d-flex align-items-center gap-1" style="font-size:12.5px">
            <input type="date" name="from" class="form-control form-control-sm"
                   value="<?= htmlspecialchars($dateFrom) ?>" style="width:auto">
            <span class="text-muted">to</span>
            <input type="date" name="to" class="form-control form-control-sm"
                   value="<?= htmlspecialchars($dateTo) ?>" style="width:auto">
          </div>
          <button class="btn btn-sm btn-primary">Filter</button>
          <a href="/tailorshop/modules/logs/login-logs.php"
             class="btn btn-sm btn-outline-secondary">Reset</a>
        </form>
      </div>
    </div>

    <div class="card">
      <div class="table-responsive">
        <table class="table mb-0">
          <thead>
            <tr>
              <th>Timestamp</th>
              <th>User</th>
              <th>Role</th>
              <th>Action</th>
              <th>IP Address</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($logs)): ?>
              <tr>
                <td colspan="5" class="text-center text-muted py-5">
                  <i class="bi bi-journal-text fs-3 d-block mb-2"></i>
                  No login logs found for this period.
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($logs as $log): ?>
                <tr>
                  <td style="font-size:12.5px">
                    <?= date('M j, Y', strtotime($log['timestamp'])) ?>
                    <div class="text-muted" style="font-size:11px">
                      <?= date('g:i:s A', strtotime($log['timestamp'])) ?>
                    </div>
                  </td>
                  <td>
                    <div class="fw-medium"><?= htmlspecialchars($log['full_name']) ?></div>
                    <div class="text-muted" style="font-size:11.5px">
                      <?= htmlspecialchars($log['email']) ?>
                    </div>
                  </td>
                  <td>
                    <span style="font-size:11.5px;font-weight:500;
                                 color:<?= $log['role'] === 'admin' ? '#6D28D9' : '#1D4ED8' ?>">
                      <?= ucfirst($log['role']) ?>
                    </span>
                  </td>
                  <td>
                    <span class="badge-status <?= $log['action'] === 'Login'
                        ? 'badge-active' : 'badge-cancelled' ?>">
                      <i class="bi <?= $log['action'] === 'Login'
                          ? 'bi-box-arrow-in-right' : 'bi-box-arrow-left' ?>"
                         style="font-size:8px"></i>
                      <?= $log['action'] ?>
                    </span>
                  </td>
                  <td class="text-muted" style="font-size:12.5px;font-family:monospace">
                    <?= htmlspecialchars($log['ip_address'] ?? '—') ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>