<?php
// ============================================================
// modules/settings/index.php
// System settings — admin only
// Reads/writes to a JSON settings file (no DB table needed)
// ============================================================

require_once __DIR__ . '/../../includes/auth-guard.php';
require_once __DIR__ . '/../../includes/role-guard.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../helpers/audit-helper.php';

$pageTitle  = 'System Settings';
$activePage = 'settings';

// ── Settings are stored in constants.php ──────────────────
// For a production system these would be stored in a DB table.
// For this system we display the current values and explain
// how to change each one.

$success = $_SESSION['success'] ?? '';
$error   = $_SESSION['error']   ?? '';
unset($_SESSION['success'], $_SESSION['error']);

// Fetch current stats for display
$totalUsers     = $pdo->query('SELECT COUNT(*) FROM users WHERE status = "active"')->fetchColumn();
$totalCustomers = $pdo->query('SELECT COUNT(*) FROM customers')->fetchColumn();
$totalRentals   = $pdo->query('SELECT COUNT(*) FROM rentals')->fetchColumn();
$totalOrders    = $pdo->query('SELECT COUNT(*) FROM tailoring_orders')->fetchColumn();
$totalSMSSent   = $pdo->query('SELECT COUNT(*) FROM sms_logs WHERE status = "Sent"')->fetchColumn();
$totalRevenue   = $pdo->query('SELECT COALESCE(SUM(amount),0) FROM income_records')->fetchColumn();

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="main-content">
  <div class="topbar"><span class="topbar-title">System Settings</span></div>

  <div class="page-body">

    <?php if ($success): ?>
      <div class="alert alert-success alert-dismissible fade show d-flex align-items-center gap-2">
        <i class="bi bi-check-circle-fill"></i><?= htmlspecialchars($success) ?>
        <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
      </div>
    <?php endif; ?>

    <div class="page-header">
      <div>
        <h5>System Settings</h5>
        <p class="text-muted small mb-0">
          Configuration reference and system overview for <?= APP_NAME ?>
        </p>
      </div>
    </div>

    <!-- System overview stats -->
    <div class="row g-3 mb-4">
      <?php
      $overviewCards = [
          ['label'=>'Active Users',   'value'=>$totalUsers,     'icon'=>'bi-people',       'color'=>'#7C3AED','bg'=>'#EDE9FE'],
          ['label'=>'Customers',      'value'=>$totalCustomers, 'icon'=>'bi-person-heart',  'color'=>'#1D4ED8','bg'=>'#EFF6FF'],
          ['label'=>'Total Rentals',  'value'=>$totalRentals,   'icon'=>'bi-handbag',       'color'=>'#D97706','bg'=>'#FEF3C7'],
          ['label'=>'Orders Made',    'value'=>$totalOrders,    'icon'=>'bi-scissors',      'color'=>'#059669','bg'=>'#F0FDF4'],
          ['label'=>'SMS Sent',       'value'=>$totalSMSSent,   'icon'=>'bi-chat-dots',     'color'=>'#0891B2','bg'=>'#ECFEFF'],
          ['label'=>'Total Revenue',  'value'=>'₱'.number_format($totalRevenue,2),'icon'=>'bi-cash-stack','color'=>'#16A34A','bg'=>'#F0FDF4'],
      ];
      foreach ($overviewCards as $card):
      ?>
        <div class="col-sm-6 col-lg-4 col-xl-2">
          <div class="stat-card" style="border-left:3px solid <?= $card['color'] ?>">
            <div class="stat-icon"
                 style="background:<?= $card['bg'] ?>;color:<?= $card['color'] ?>">
              <i class="bi <?= $card['icon'] ?>"></i>
            </div>
            <div>
              <div class="stat-label"><?= $card['label'] ?></div>
              <div class="stat-value" style="font-size:18px;color:<?= $card['color'] ?>">
                <?= $card['value'] ?>
              </div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="row g-3">

      <!-- SMS Configuration -->
      <div class="col-lg-6">
        <div class="card mb-3">
          <div class="card-header">
            <i class="bi bi-chat-dots me-2 text-primary"></i>SMS Configuration
          </div>
          <div class="card-body p-4">
            <p class="text-muted small mb-3">
              These settings are configured in
              <code>config/constants.php</code>.
              Edit that file directly to change them.
            </p>
            <dl class="row mb-0" style="font-size:13.5px">
              <dt class="col-6 text-muted fw-normal">Semaphore API Key</dt>
              <dd class="col-6 fw-medium" style="font-family:monospace">
                <?= defined('SEMAPHORE_API_KEY') && SEMAPHORE_API_KEY !== 'YOUR_SEMAPHORE_API_KEY_HERE'
                    ? '••••••••' . substr(SEMAPHORE_API_KEY, -6)
                    : '<span class="text-danger">Not configured</span>' ?>
              </dd>
              <dt class="col-6 text-muted fw-normal">Sender Name</dt>
              <dd class="col-6 fw-medium">
                <?= defined('SEMAPHORE_SENDER_NAME')
                    ? htmlspecialchars(SEMAPHORE_SENDER_NAME)
                    : '<span class="text-danger">Not configured</span>' ?>
              </dd>
              <dt class="col-6 text-muted fw-normal">Admin SMS Number</dt>
              <dd class="col-6 fw-medium">
                <?= defined('ADMIN_SMS_NUMBER')
                    ? htmlspecialchars(ADMIN_SMS_NUMBER)
                    : '<span class="text-danger">Not configured</span>' ?>
              </dd>
              <dt class="col-6 text-muted fw-normal">Rental Reminder</dt>
              <dd class="col-6"><?= RENTAL_REMINDER_DAYS ?> days before return</dd>
              <dt class="col-6 text-muted fw-normal">Reservation Reminder</dt>
              <dd class="col-6"><?= RESERVATION_REMINDER_DAYS ?> days before pickup</dd>
              <dt class="col-6 text-muted fw-normal">Escalation Interval</dt>
              <dd class="col-6">Every <?= OVERDUE_ESCALATION_DAYS ?> days if overdue</dd>
            </dl>

            <hr class="my-3">
            <a href="/tailorshop/api/run-scheduler.php"
               class="btn btn-outline-primary btn-sm">
              <i class="bi bi-play-circle me-1"></i> Run SMS Scheduler Now
            </a>
            <span class="text-muted small ms-2">
              Sends pending reminders and overdue alerts.
            </span>
          </div>
        </div>
      </div>

      <!-- Financial defaults -->
      <div class="col-lg-6">
        <div class="card mb-3">
          <div class="card-header">
            <i class="bi bi-cash me-2 text-success"></i>Financial Defaults
          </div>
          <div class="card-body p-4">
            <p class="text-muted small mb-3">
              Configured in <code>config/constants.php</code>.
            </p>
            <dl class="row mb-0" style="font-size:13.5px">
              <dt class="col-7 text-muted fw-normal">Default Penalty Rate</dt>
              <dd class="col-5 fw-semibold text-danger">
                ₱<?= number_format(DEFAULT_PENALTY_RATE, 2) ?>/day
              </dd>
              <dt class="col-7 text-muted fw-normal">Default Low Stock Threshold</dt>
              <dd class="col-5 fw-semibold">
                <?= DEFAULT_LOW_STOCK_THRESHOLD ?> units
              </dd>
              <dt class="col-7 text-muted fw-normal">Records Per Page</dt>
              <dd class="col-5"><?= RECORDS_PER_PAGE ?></dd>
              <dt class="col-7 text-muted fw-normal">App Timezone</dt>
              <dd class="col-5"><?= APP_TIMEZONE ?></dd>
              <dt class="col-7 text-muted fw-normal">Session Timeout</dt>
              <dd class="col-5"><?= SESSION_TIMEOUT / 60 ?> minutes</dd>
              <dt class="col-7 text-muted fw-normal">Reset Token Expiry</dt>
              <dd class="col-5"><?= RESET_TOKEN_EXPIRY_MIN ?> minutes</dd>
            </dl>
          </div>
        </div>
      </div>

      <!-- Shop information -->
      <div class="col-lg-6">
        <div class="card mb-3">
          <div class="card-header">
            <i class="bi bi-shop me-2 text-warning"></i>Shop Information
          </div>
          <div class="card-body p-4">
            <p class="text-muted small mb-3">
              Appears on printed rental agreements and SMS messages.
              Edit in <code>config/constants.php</code>.
            </p>
            <dl class="row mb-0" style="font-size:13.5px">
              <dt class="col-5 text-muted fw-normal">Shop Name</dt>
              <dd class="col-7 fw-semibold"><?= htmlspecialchars(SHOP_NAME) ?></dd>
              <dt class="col-5 text-muted fw-normal">Address</dt>
              <dd class="col-7"><?= htmlspecialchars(SHOP_ADDRESS) ?></dd>
              <dt class="col-5 text-muted fw-normal">Contact Number</dt>
              <dd class="col-7"><?= htmlspecialchars(SHOP_CONTACT) ?></dd>
              <dt class="col-5 text-muted fw-normal">App Version</dt>
              <dd class="col-7"><?= APP_VERSION ?></dd>
            </dl>
          </div>
        </div>
      </div>

      <!-- Database quick links -->
      <div class="col-lg-6">
        <div class="card">
          <div class="card-header">
            <i class="bi bi-database me-2 text-secondary"></i>Database Tables
          </div>
          <div class="card-body p-4">
            <p class="text-muted small mb-3">
              Quick record counts for all system tables.
            </p>
            <?php
            $tables = [
                'users','customers','material_items','garment_items',
                'reservations','rentals','tailoring_orders',
                'sales_records','income_records','sms_logs','audit_logs','login_logs',
            ];
            ?>
            <div class="row g-2">
              <?php foreach ($tables as $t): ?>
                <?php $count = $pdo->query("SELECT COUNT(*) FROM `{$t}`")->fetchColumn(); ?>
                <div class="col-6">
                  <div class="d-flex justify-content-between align-items-center
                              px-2 py-1 rounded" style="background:#f9fafb">
                    <span class="text-muted" style="font-size:12px;font-family:monospace">
                      <?= $t ?>
                    </span>
                    <span class="badge bg-secondary rounded-pill" style="font-size:11px">
                      <?= number_format($count) ?>
                    </span>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      </div>

    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>