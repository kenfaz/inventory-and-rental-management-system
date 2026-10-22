<?php
// ============================================================
// modules/logs/email-logs.php
// Outbound email delivery attempts — admin only
// ============================================================

require_once __DIR__ . '/../../includes/auth-guard.php';
require_once __DIR__ . '/../../includes/role-guard.php';
require_once __DIR__ . '/../../config/db.php';

$pageTitle = 'Email Logs';
$activePage = 'email-logs';
$search = trim($_GET['q'] ?? '');
$status = trim($_GET['status'] ?? '');
$from = trim($_GET['from'] ?? date('Y-m-d', strtotime('-7 days')));
$to = trim($_GET['to'] ?? date('Y-m-d'));

$where = ['timestamp BETWEEN ? AND ?'];
$params = [$from . ' 00:00:00', $to . ' 23:59:59'];

if (in_array($status, ['Sent', 'Failed'], true)) {
    $where[] = 'status = ?';
    $params[] = $status;
} else {
    $status = '';
}
if ($search !== '') {
    $where[] = '(recipient_email LIKE ? OR subject LIKE ? OR message_content LIKE ?)';
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like);
}

$stmt = $pdo->prepare('
    SELECT *
    FROM email_logs
    WHERE ' . implode(' AND ', $where) . '
    ORDER BY timestamp DESC
    LIMIT 200
');
$stmt->execute($params);
$logs = $stmt->fetchAll();
$sentCount = count(array_filter($logs, static fn($row) => $row['status'] === 'Sent'));
$failedCount = count(array_filter($logs, static fn($row) => $row['status'] === 'Failed'));

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="main-content">
  <div class="topbar"><span class="topbar-title">Email Logs</span></div>
  <div class="page-body">
    <div class="page-header">
      <div>
        <h5>Email Logs</h5>
        <p class="text-muted small mb-0">
          <?= count($logs) ?> records ·
          <span class="text-success"><?= $sentCount ?> sent</span> ·
          <span class="text-danger"><?= $failedCount ?> failed</span>
        </p>
      </div>
    </div>

    <div class="card mb-3">
      <div class="card-body py-2 px-3">
        <form method="GET" class="d-flex gap-2 align-items-center flex-wrap">
          <input type="search" name="q" class="form-control form-control-sm"
                 placeholder="Search email, subject, or message"
                 value="<?= htmlspecialchars($search) ?>" style="min-width:220px;flex:1">
          <select name="status" class="form-select form-select-sm" style="width:auto">
            <option value="">All statuses</option>
            <option value="Sent" <?= $status === 'Sent' ? 'selected' : '' ?>>Sent</option>
            <option value="Failed" <?= $status === 'Failed' ? 'selected' : '' ?>>Failed</option>
          </select>
          <input type="date" name="from" class="form-control form-control-sm"
                 value="<?= htmlspecialchars($from) ?>">
          <input type="date" name="to" class="form-control form-control-sm"
                 value="<?= htmlspecialchars($to) ?>">
          <button class="btn btn-sm btn-primary">Filter</button>
        </form>
      </div>
    </div>

    <div class="card">
      <div class="table-responsive">
        <table class="table mb-0">
          <thead>
            <tr>
              <th>Date</th><th>Recipient</th><th>Subject</th><th>Trigger</th>
              <th>Status</th><th>Details</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$logs): ?>
              <tr><td colspan="6" class="text-center text-muted py-5">No email attempts for these filters.</td></tr>
            <?php else: ?>
              <?php foreach ($logs as $row): ?>
                <tr>
                  <td><?= htmlspecialchars($row['timestamp']) ?></td>
                  <td>
                    <?= htmlspecialchars($row['recipient_name'] ?: $row['recipient_email']) ?>
                    <?php if ($row['recipient_name']): ?>
                      <div class="small text-muted"><?= htmlspecialchars($row['recipient_email']) ?></div>
                    <?php endif; ?>
                  </td>
                  <td><?= htmlspecialchars($row['subject']) ?></td>
                  <td>
                    <?= htmlspecialchars($row['trigger_type']) ?>
                    <?php if ($row['reference_type'] && $row['reference_id']): ?>
                      <div class="small text-muted">
                        <?= htmlspecialchars($row['reference_type']) ?> #<?= (int)$row['reference_id'] ?>
                      </div>
                    <?php endif; ?>
                  </td>
                  <td>
                    <span class="badge <?= $row['status'] === 'Sent' ? 'bg-success' : 'bg-danger' ?>">
                      <?= htmlspecialchars($row['status']) ?>
                    </span>
                  </td>
                  <td class="small text-muted">
                    <?= htmlspecialchars($row['failure_reason'] ?: $row['message_content']) ?>
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
