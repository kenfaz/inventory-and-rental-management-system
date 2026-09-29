<?php
// ============================================================
// modules/logs/sms-logs.php
// All outbound SMS delivery records — admin only
// ============================================================

require_once __DIR__ . '/../../../includes/auth-guard.php';
require_once __DIR__ . '/../../../includes/role-guard.php';
require_once __DIR__ . '/../../../config/db.php';

$pageTitle  = 'SMS Logs';
$activePage = 'sms-logs';

$search      = trim($_GET['q']       ?? '');
$statusFilter= trim($_GET['status']  ?? '');
$triggerFilter=trim($_GET['trigger'] ?? '');
$dateFrom    = trim($_GET['from']    ?? date('Y-m-d', strtotime('-7 days')));
$dateTo      = trim($_GET['to']      ?? date('Y-m-d'));

$where  = ['timestamp BETWEEN ? AND ?'];
$params = [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'];

if ($statusFilter !== '') {
    $where[]  = 'status = ?';
    $params[] = $statusFilter;
}
if ($triggerFilter !== '') {
    $where[]  = 'trigger_type = ?';
    $params[] = $triggerFilter;
}
if ($search !== '') {
    $where[]  = '(recipient_number LIKE ? OR message_content LIKE ?)';
    $like     = '%' . $search . '%';
    $params   = array_merge($params, [$like, $like]);
}

$whereSQL = 'WHERE ' . implode(' AND ', $where);

$stmt = $pdo->prepare("
    SELECT * FROM sms_logs
    $whereSQL
    ORDER BY timestamp DESC
    LIMIT 200
");
$stmt->execute($params);
$logs = $stmt->fetchAll();

$sentCount   = count(array_filter($logs, fn($l) => $l['status'] === 'Sent'));
$failedCount = count(array_filter($logs, fn($l) => $l['status'] === 'Failed'));

$triggerTypes = [
    'Reservation Confirmation',
    'Reservation Reminder',
    'Rental Return Reminder',
    'Rental Overdue Alert',
    'Rental Overdue Escalation',
    'Low Stock Alert',
    'Order Ready Pickup',
];

require_once __DIR__ . '/../../../includes/header.php';
require_once __DIR__ . '/../../../includes/sidebar.php';
?>

<div class="main-content">
  <div class="topbar"><span class="topbar-title">SMS Logs</span></div>

  <div class="page-body">

    <div class="page-header">
      <div>
        <h5>SMS Logs</h5>
        <p class="text-muted small mb-0">
          <?= count($logs) ?> records &nbsp;·&nbsp;
          <span class="text-success fw-medium"><?= $sentCount ?> sent</span>
          &nbsp;·&nbsp;
          <span class="text-danger fw-medium"><?= $failedCount ?> failed</span>
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
                 placeholder="Search by number or message…"
                 value="<?= htmlspecialchars($search) ?>"
                 style="font-size:13.5px;min-width:150px;flex:1">
          <select name="trigger" class="form-select form-select-sm"
                  style="width:auto" onchange="this.form.submit()">
            <option value="">All Triggers</option>
            <?php foreach ($triggerTypes as $t): ?>
              <option value="<?= htmlspecialchars($t) ?>"
                <?= $triggerFilter === $t ? 'selected' : '' ?>>
                <?= htmlspecialchars($t) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <select name="status" class="form-select form-select-sm"
                  style="width:auto" onchange="this.form.submit()">
            <option value="">All Statuses</option>
            <option value="Sent"   <?= $statusFilter === 'Sent'   ? 'selected' : '' ?>>Sent</option>
            <option value="Failed" <?= $statusFilter === 'Failed' ? 'selected' : '' ?>>Failed</option>
          </select>
          <div class="d-flex align-items-center gap-1" style="font-size:12.5px">
            <input type="date" name="from" class="form-control form-control-sm"
                   value="<?= htmlspecialchars($dateFrom) ?>" style="width:auto">
            <span class="text-muted">to</span>
            <input type="date" name="to" class="form-control form-control-sm"
                   value="<?= htmlspecialchars($dateTo) ?>" style="width:auto">
          </div>
          <button class="btn btn-sm btn-primary">Filter</button>
          <a href="/tailorshop/modules/logs/sms-logs.php"
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
              <th>Recipient</th>
              <th>Trigger</th>
              <th>Message</th>
              <th>Status</th>
              <th>Failure Reason</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($logs)): ?>
              <tr>
                <td colspan="6" class="text-center text-muted py-5">
                  <i class="bi bi-chat-dots fs-3 d-block mb-2"></i>
                  No SMS logs found for this period.
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
                  <td style="font-family:monospace;font-size:13px">
                    <?= htmlspecialchars($log['recipient_number']) ?>
                  </td>
                  <td>
                    <span class="badge bg-secondary bg-opacity-10 text-secondary"
                          style="font-size:11px;white-space:normal">
                      <?= htmlspecialchars($log['trigger_type']) ?>
                    </span>
                  </td>
                  <td style="max-width:220px">
                    <div style="font-size:12px;color:#374151;
                                white-space:nowrap;overflow:hidden;text-overflow:ellipsis"
                         title="<?= htmlspecialchars($log['message_content']) ?>">
                      <?= htmlspecialchars(substr($log['message_content'], 0, 70)) ?>
                      <?= strlen($log['message_content']) > 70 ? '…' : '' ?>
                    </div>
                  </td>
                  <td>
                    <?php if ($log['status'] === 'Sent'): ?>
                      <span class="badge-status badge-active">
                        <i class="bi bi-check-circle-fill" style="font-size:8px"></i> Sent
                      </span>
                    <?php else: ?>
                      <span class="badge-status badge-overdue">
                        <i class="bi bi-x-circle-fill" style="font-size:8px"></i> Failed
                      </span>
                    <?php endif; ?>
                  </td>
                  <td class="text-muted" style="font-size:12px;max-width:160px">
                    <?= htmlspecialchars($log['failure_reason'] ?? '—') ?>
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

<?php require_once __DIR__ . '/../../../includes/footer.php'; ?>