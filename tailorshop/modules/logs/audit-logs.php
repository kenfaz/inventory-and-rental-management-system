<?php
// ============================================================
// modules/logs/audit-logs.php
// All sensitive system actions — admin only, never deleted
// ============================================================

require_once __DIR__ . '/../../includes/auth-guard.php';
require_once __DIR__ . '/../../includes/role-guard.php';
require_once __DIR__ . '/../../config/db.php';

$pageTitle  = 'Audit Logs';
$activePage = 'logs';

$search     = trim($_GET['q']      ?? '');
$module     = trim($_GET['module'] ?? '');
$dateFrom   = trim($_GET['from']   ?? date('Y-m-d', strtotime('-7 days')));
$dateTo     = trim($_GET['to']     ?? date('Y-m-d'));

$where  = ['al.timestamp BETWEEN ? AND ?'];
$params = [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'];

if ($module !== '') {
    $where[]  = 'al.module = ?';
    $params[] = $module;
}
if ($search !== '') {
    $where[]  = '(u.full_name LIKE ? OR al.action_type LIKE ? OR al.affected_table LIKE ?)';
    $like     = '%' . $search . '%';
    $params   = array_merge($params, [$like, $like, $like]);
}

$whereSQL = 'WHERE ' . implode(' AND ', $where);

$stmt = $pdo->prepare("
    SELECT al.*, u.full_name, u.role
    FROM audit_logs al
    JOIN users u ON al.user_id = u.user_id
    $whereSQL
    ORDER BY al.timestamp DESC
    LIMIT 200
");
$stmt->execute($params);
$logs = $stmt->fetchAll();

$modules = $pdo->query("
    SELECT DISTINCT module FROM audit_logs ORDER BY module ASC
")->fetchAll(PDO::FETCH_COLUMN);

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="main-content">
  <div class="topbar"><span class="topbar-title">Audit Logs</span></div>

  <div class="page-body">

    <div class="page-header">
      <div>
        <h5>Audit Logs</h5>
        <p class="text-muted small mb-0">
          Showing <?= count($logs) ?> of the most recent records. Audit logs are never deleted.
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
                 placeholder="Search by user, action, or table…"
                 value="<?= htmlspecialchars($search) ?>"
                 style="font-size:13.5px;min-width:160px;flex:1">
          <select name="module" class="form-select form-select-sm"
                  style="width:auto" onchange="this.form.submit()">
            <option value="">All Modules</option>
            <?php foreach ($modules as $m): ?>
              <option value="<?= htmlspecialchars($m) ?>"
                <?= $module === $m ? 'selected' : '' ?>>
                <?= htmlspecialchars($m) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <div class="d-flex align-items-center gap-1" style="font-size:12.5px">
            <input type="date" name="from" class="form-control form-control-sm"
                   value="<?= htmlspecialchars($dateFrom) ?>" style="width:auto">
            <span class="text-muted">to</span>
            <input type="date" name="to" class="form-control form-control-sm"
                   value="<?= htmlspecialchars($dateTo) ?>" style="width:auto">
          </div>
          <button class="btn btn-sm btn-primary">Filter</button>
          <a href="/tailorshop/modules/logs/audit-logs.php"
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
              <th>Module</th>
              <th>Action</th>
              <th>Table</th>
              <th>Record ID</th>
              <th>Details</th>
              <th>IP</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($logs)): ?>
              <tr>
                <td colspan="8" class="text-center text-muted py-5">
                  <i class="bi bi-journal-text fs-3 d-block mb-2"></i>
                  No audit logs found for this period.
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
                    <div class="fw-medium" style="font-size:13px">
                      <?= htmlspecialchars($log['full_name']) ?>
                    </div>
                    <div style="font-size:11px;color:<?= $log['role'] === 'admin'
                        ? '#6D28D9' : '#1D4ED8' ?>">
                      <?= ucfirst($log['role']) ?>
                    </div>
                  </td>
                  <td>
                    <span class="badge bg-secondary bg-opacity-10 text-secondary"
                          style="font-size:11px">
                      <?= htmlspecialchars($log['module']) ?>
                    </span>
                  </td>
                  <td>
                    <?php
                    $actionColors = [
                        'Created'        => '#166534',
                        'Updated'        => '#1D4ED8',
                        'Deleted'        => '#991B1B',
                        'Status Changed' => '#92400E',
                    ];
                    $ac = $actionColors[$log['action_type']] ?? '#374151';
                    ?>
                    <span style="font-size:12px;font-weight:600;color:<?= $ac ?>">
                      <?= htmlspecialchars($log['action_type']) ?>
                    </span>
                  </td>
                  <td class="text-muted" style="font-size:12px;font-family:monospace">
                    <?= htmlspecialchars($log['affected_table'] ?? '—') ?>
                  </td>
                  <td class="text-muted" style="font-size:12.5px">
                    <?= $log['affected_record_id'] ?? '—' ?>
                  </td>
                  <td style="max-width:200px">
                    <?php if ($log['new_value']): ?>
                      <div style="font-size:11px;color:#374151;
                                  white-space:nowrap;overflow:hidden;text-overflow:ellipsis"
                           title="<?= htmlspecialchars($log['new_value']) ?>">
                        <?= htmlspecialchars(substr($log['new_value'], 0, 60)) ?>
                        <?= strlen($log['new_value']) > 60 ? '…' : '' ?>
                      </div>
                    <?php else: ?>
                      <span class="text-muted" style="font-size:12px">—</span>
                    <?php endif; ?>
                  </td>
                  <td class="text-muted" style="font-size:11.5px;font-family:monospace">
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