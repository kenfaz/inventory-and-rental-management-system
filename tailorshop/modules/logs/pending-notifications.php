<?php
require_once __DIR__ . '/../../includes/auth-guard.php';
require_once __DIR__ . '/../../includes/role-guard.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../helpers/sms-scheduler.php';

$pageTitle = 'Pending Notifications';
$activePage = 'pending-notifications';
$_SESSION['notification_csrf'] = $_SESSION['notification_csrf'] ?? bin2hex(random_bytes(32));

function notificationPageRedirect(): void {
    header('Location: /tailorshop/modules/logs/pending-notifications.php');
    exit;
}

function getLatestFailedSms(int $logId): ?array {
    global $pdo;

    $stmt = $pdo->prepare('
        SELECT s.*
        FROM sms_logs s
        WHERE s.sms_log_id = ? AND s.status = "Failed"
          AND NOT EXISTS (
              SELECT 1
              FROM sms_logs newer
              WHERE newer.recipient_number = s.recipient_number
                AND newer.trigger_type = s.trigger_type
                AND newer.reference_id <=> s.reference_id
                AND newer.reference_type <=> s.reference_type
                AND (
                    newer.timestamp > s.timestamp
                    OR (newer.timestamp = s.timestamp AND newer.sms_log_id > s.sms_log_id)
                )
          )
    ');
    $stmt->execute([$logId]);
    $row = $stmt->fetch();

    return $row ?: null;
}

function getLatestFailedEmail(int $logId): ?array {
    global $pdo;

    $stmt = $pdo->prepare('
        SELECT e.*
        FROM email_logs e
        WHERE e.email_log_id = ? AND e.status = "Failed"
          AND NOT EXISTS (
              SELECT 1
              FROM email_logs newer
              WHERE newer.recipient_email = e.recipient_email
                AND newer.trigger_type = e.trigger_type
                AND newer.reference_id <=> e.reference_id
                AND newer.reference_type <=> e.reference_type
                AND (
                    newer.timestamp > e.timestamp
                    OR (newer.timestamp = e.timestamp AND newer.email_log_id > e.email_log_id)
                )
          )
    ');
    $stmt->execute([$logId]);
    $row = $stmt->fetch();

    return $row ?: null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedToken = $_POST['csrf_token'] ?? '';
    if (
        !is_string($postedToken) ||
        !hash_equals($_SESSION['notification_csrf'], $postedToken)
    ) {
        $_SESSION['notification_flash'] = [
            'type' => 'danger',
            'message' => 'The request could not be verified. Refresh the page and try again.',
        ];
        notificationPageRedirect();
    }

    try {
        $action = $_POST['action'] ?? '';

        if ($action === 'send_due') {
            $notificationId = $_POST['notification_id'] ?? '';
            if (!is_string($notificationId) || !preg_match('/^[a-f0-9]{64}$/', $notificationId)) {
                throw new InvalidArgumentException('Select a valid pending notification.');
            }

            syncRentalStatuses();
            $pending = getPendingScheduledNotifications()['notifications'];
            $notification = null;
            foreach ($pending as $candidate) {
                if (hash_equals($candidate['id'], $notificationId)) {
                    $notification = $candidate;
                    break;
                }
            }
            if ($notification === null) {
                throw new RuntimeException('This notification is no longer due. Refresh the list.');
            }

            if ($notification['channel'] === 'SMS') {
                $isLowStockSms = $notification['trigger_type'] === 'Low Stock Alert' &&
                    $notification['reference_type'] === 'material_items_summary';
                if (SMS_MOCK_MODE && !$isLowStockSms) {
                    throw new RuntimeException(
                        'SMS mock mode is enabled. No live SMS was sent; disable mock mode to send it.'
                    );
                }
                $sent = sendSMS(
                    $notification['recipient'],
                    $notification['message'],
                    $notification['trigger_type'],
                    $notification['reference_id'],
                    $notification['reference_type'],
                    $isLowStockSms
                );
            } else {
                $sent = sendEmail(
                    $notification['recipient'],
                    $notification['recipient_name'],
                    $notification['subject'] ?? '',
                    $notification['message'],
                    $notification['trigger_type'],
                    $notification['reference_id'],
                    $notification['reference_type']
                );
            }

            $_SESSION['notification_flash'] = [
                'type' => $sent ? 'success' : 'warning',
                'message' => $sent
                    ? $notification['channel'] . ' sent to ' . $notification['recipient'] . '.'
                    : $notification['channel'] . ' attempt failed for ' . $notification['recipient'] .
                      '. See the failed message below.',
            ];
        } elseif ($action === 'retry_sms') {
            $logId = filter_var($_POST['log_id'] ?? null, FILTER_VALIDATE_INT);
            $row = $logId ? getLatestFailedSms((int)$logId) : null;
            if (!$row) {
                throw new RuntimeException('This SMS is no longer the latest failed attempt.');
            }
            $isLowStockSms = $row['trigger_type'] === 'Low Stock Alert' &&
                $row['reference_type'] === 'material_items_summary';
            if (SMS_MOCK_MODE && !$isLowStockSms) {
                throw new RuntimeException(
                    'SMS mock mode is enabled. No live SMS was sent; disable mock mode to retry it.'
                );
            }

            $sent = sendSMS(
                $row['recipient_number'],
                $row['message_content'],
                $row['trigger_type'],
                $row['reference_id'] !== null ? (int)$row['reference_id'] : null,
                $row['reference_type'],
                $isLowStockSms
            );
            $_SESSION['notification_flash'] = [
                'type' => $sent ? 'success' : 'warning',
                'message' => $sent
                    ? 'SMS retry sent to ' . $row['recipient_number'] . '.'
                    : 'SMS retry failed for ' . $row['recipient_number'] . '.',
            ];
        } elseif ($action === 'retry_email') {
            $logId = filter_var($_POST['log_id'] ?? null, FILTER_VALIDATE_INT);
            $row = $logId ? getLatestFailedEmail((int)$logId) : null;
            if (!$row) {
                throw new RuntimeException('This email is no longer the latest failed attempt.');
            }

            $sent = sendEmail(
                $row['recipient_email'],
                $row['recipient_name'] ?? '',
                $row['subject'],
                $row['message_content'],
                $row['trigger_type'],
                $row['reference_id'] !== null ? (int)$row['reference_id'] : null,
                $row['reference_type']
            );
            $_SESSION['notification_flash'] = [
                'type' => $sent ? 'success' : 'warning',
                'message' => $sent
                    ? 'Email retry sent to ' . $row['recipient_email'] . '.'
                    : 'Email retry failed for ' . $row['recipient_email'] . '.',
            ];
        } else {
            throw new InvalidArgumentException('Unknown notification action.');
        }
    } catch (InvalidArgumentException | RuntimeException $e) {
        $_SESSION['notification_flash'] = ['type' => 'danger', 'message' => $e->getMessage()];
    } catch (Throwable $e) {
        error_log('Pending notification action failed: ' . $e->getMessage());
        $_SESSION['notification_flash'] = [
            'type' => 'danger',
            'message' => 'The notification action failed. Check the application log for details.',
        ];
    }

    notificationPageRedirect();
}

syncRentalStatuses();
$pendingResult = getPendingScheduledNotifications();
$pendingNotifications = $pendingResult['notifications'];
$warnings = $pendingResult['warnings'];

$failedSms = $pdo->query('
    SELECT s.*
    FROM sms_logs s
    WHERE s.status = "Failed"
      AND NOT EXISTS (
          SELECT 1
          FROM sms_logs newer
          WHERE newer.recipient_number = s.recipient_number
            AND newer.trigger_type = s.trigger_type
            AND newer.reference_id <=> s.reference_id
            AND newer.reference_type <=> s.reference_type
            AND (
                newer.timestamp > s.timestamp
                OR (newer.timestamp = s.timestamp AND newer.sms_log_id > s.sms_log_id)
            )
      )
    ORDER BY s.timestamp DESC, s.sms_log_id DESC
    LIMIT 100
')->fetchAll();

$failedEmails = $pdo->query('
    SELECT e.*
    FROM email_logs e
    WHERE e.status = "Failed"
      AND NOT EXISTS (
          SELECT 1
          FROM email_logs newer
          WHERE newer.recipient_email = e.recipient_email
            AND newer.trigger_type = e.trigger_type
            AND newer.reference_id <=> e.reference_id
            AND newer.reference_type <=> e.reference_type
            AND (
                newer.timestamp > e.timestamp
                OR (newer.timestamp = e.timestamp AND newer.email_log_id > e.email_log_id)
            )
      )
    ORDER BY e.timestamp DESC, e.email_log_id DESC
    LIMIT 100
')->fetchAll();

$flash = $_SESSION['notification_flash'] ?? null;
unset($_SESSION['notification_flash']);

function notificationEscape(mixed $value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="main-content">
  <div class="topbar"><span class="topbar-title">Pending Notifications</span></div>
  <div class="page-body">
    <div class="page-header">
      <div>
        <h5>Pending SMS and Email</h5>
        <p class="text-muted small mb-0">
          Scheduled notifications due now and the latest failed attempts.
          Mocked SMS entries are excluded.
        </p>
      </div>
    </div>

    <?php if ($flash): ?>
      <div class="alert alert-<?= notificationEscape($flash['type']) ?> alert-dismissible fade show" role="alert">
        <?= notificationEscape($flash['message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>
    <?php endif; ?>

    <?php if (SMS_MOCK_MODE): ?>
      <div class="alert alert-warning" role="alert">
        SMS mock mode is enabled. Other SMS actions remain mocked. Combined low-stock SMS can be sent live
        from this page only; live delivery may incur charges.
      </div>
    <?php endif; ?>

    <?php foreach ($warnings as $warning): ?>
      <div class="alert alert-warning" role="alert"><?= notificationEscape($warning) ?></div>
    <?php endforeach; ?>

    <div class="card mb-4">
      <div class="card-header d-flex justify-content-between align-items-center">
        <strong>Due scheduled notifications (<?= count($pendingNotifications) ?>)</strong>
        <span class="small text-muted">Calculated from current records</span>
      </div>
      <div class="table-responsive">
        <table class="table align-middle mb-0">
          <thead>
            <tr><th>Type</th><th>Recipient</th><th>Notification</th><th>Message preview</th><th>Action</th></tr>
          </thead>
          <tbody>
            <?php if (!$pendingNotifications): ?>
              <tr><td colspan="5" class="text-center text-muted py-5">No scheduled notifications are currently due.</td></tr>
            <?php else: ?>
              <?php foreach ($pendingNotifications as $notification): ?>
                <tr>
                  <td><span class="badge <?= $notification['channel'] === 'SMS' ? 'bg-info text-dark' : 'bg-primary' ?>">
                    <?= notificationEscape($notification['channel']) ?>
                  </span></td>
                  <td>
                    <?= notificationEscape($notification['recipient_name']) ?>
                    <div class="small text-muted"><?= notificationEscape($notification['recipient']) ?></div>
                  </td>
                  <td>
                    <?= notificationEscape($notification['description']) ?>
                    <div class="small text-muted"><?= notificationEscape($notification['trigger_type']) ?></div>
                  </td>
                  <td style="min-width:220px">
                    <?php if ($notification['subject'] !== null): ?>
                      <div class="small fw-semibold"><?= notificationEscape($notification['subject']) ?></div>
                    <?php endif; ?>
                    <details>
                      <summary class="small">View message</summary>
                      <pre class="small text-wrap mt-2 mb-0"><?= notificationEscape($notification['message']) ?></pre>
                    </details>
                  </td>
                  <td>
                    <form method="POST"
                          <?= $notification['channel'] === 'SMS' &&
                              (!SMS_MOCK_MODE ||
                               ($notification['trigger_type'] === 'Low Stock Alert' &&
                                $notification['reference_type'] === 'material_items_summary'))
                              ? 'onsubmit="return confirm(\'Send this live SMS? Messaging charges may apply.\')"' : '' ?>>
                      <input type="hidden" name="csrf_token" value="<?= notificationEscape($_SESSION['notification_csrf']) ?>">
                      <input type="hidden" name="action" value="send_due">
                      <input type="hidden" name="notification_id" value="<?= notificationEscape($notification['id']) ?>">
                      <button class="btn btn-sm btn-primary"
                              <?= SMS_MOCK_MODE && $notification['channel'] === 'SMS' &&
                                  !($notification['trigger_type'] === 'Low Stock Alert' &&
                                    $notification['reference_type'] === 'material_items_summary')
                                      ? 'disabled' : '' ?>>
                        <i class="bi bi-send me-1"></i>Send now
                      </button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="card">
      <div class="card-header">
        <strong>Latest failed attempts (<?= count($failedSms) + count($failedEmails) ?>)</strong>
        <div class="small text-muted">A newer attempt for the same recipient and trigger replaces the older failure in this list.</div>
      </div>
      <div class="table-responsive">
        <table class="table align-middle mb-0">
          <thead>
            <tr><th>Type</th><th>Last attempt</th><th>Recipient</th><th>Trigger</th><th>Failure / message</th><th>Action</th></tr>
          </thead>
          <tbody>
            <?php if (!$failedSms && !$failedEmails): ?>
              <tr><td colspan="6" class="text-center text-muted py-5">No latest failed attempts. Mocked SMS is not included.</td></tr>
            <?php endif; ?>
            <?php foreach ($failedSms as $row): ?>
              <tr>
                <td><span class="badge bg-info text-dark">SMS</span></td>
                <td><?= notificationEscape($row['timestamp']) ?></td>
                <td><?= notificationEscape($row['recipient_number']) ?></td>
                <td><?= notificationEscape($row['trigger_type']) ?></td>
                <td style="min-width:220px">
                  <div class="small text-danger"><?= notificationEscape($row['failure_reason'] ?: 'Delivery failed') ?></div>
                  <details><summary class="small">View message</summary>
                    <pre class="small text-wrap mt-2 mb-0"><?= notificationEscape($row['message_content']) ?></pre>
                  </details>
                </td>
                <td>
                  <form method="POST" <?= !SMS_MOCK_MODE ||
                      ($row['trigger_type'] === 'Low Stock Alert' &&
                       $row['reference_type'] === 'material_items_summary')
                      ? 'onsubmit="return confirm(\'Retry this live SMS? Messaging charges may apply.\')"' : '' ?>>
                    <input type="hidden" name="csrf_token" value="<?= notificationEscape($_SESSION['notification_csrf']) ?>">
                    <input type="hidden" name="action" value="retry_sms">
                    <input type="hidden" name="log_id" value="<?= (int)$row['sms_log_id'] ?>">
                    <button class="btn btn-sm btn-outline-primary"
                            <?= SMS_MOCK_MODE &&
                                !($row['trigger_type'] === 'Low Stock Alert' &&
                                  $row['reference_type'] === 'material_items_summary')
                                    ? 'disabled' : '' ?>>
                      <i class="bi bi-arrow-repeat me-1"></i>Retry
                    </button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
            <?php foreach ($failedEmails as $row): ?>
              <tr>
                <td><span class="badge bg-primary">Email</span></td>
                <td><?= notificationEscape($row['timestamp']) ?></td>
                <td>
                  <?= notificationEscape($row['recipient_name'] ?: $row['recipient_email']) ?>
                  <?php if ($row['recipient_name']): ?>
                    <div class="small text-muted"><?= notificationEscape($row['recipient_email']) ?></div>
                  <?php endif; ?>
                </td>
                <td><?= notificationEscape($row['trigger_type']) ?></td>
                <td style="min-width:220px">
                  <div class="small text-danger"><?= notificationEscape($row['failure_reason'] ?: 'Delivery failed') ?></div>
                  <div class="small fw-semibold"><?= notificationEscape($row['subject']) ?></div>
                  <details><summary class="small">View message</summary>
                    <pre class="small text-wrap mt-2 mb-0"><?= notificationEscape($row['message_content']) ?></pre>
                  </details>
                </td>
                <td>
                  <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= notificationEscape($_SESSION['notification_csrf']) ?>">
                    <input type="hidden" name="action" value="retry_email">
                    <input type="hidden" name="log_id" value="<?= (int)$row['email_log_id'] ?>">
                    <button class="btn btn-sm btn-outline-primary">
                      <i class="bi bi-arrow-repeat me-1"></i>Retry
                    </button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <p class="small text-muted mt-3 mb-0">
      Due scheduled notifications are calculated when this page loads; they are not stored in a separate queue.
      Retrying a failed message creates a new delivery attempt. Live SMS delivery may incur charges.
    </p>
  </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
