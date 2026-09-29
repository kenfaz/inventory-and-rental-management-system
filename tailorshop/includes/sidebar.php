<?php
// ============================================================
// includes/sidebar.php
// Role-aware sidebar navigation
// Requires $activePage to be set by the including file
// e.g. $activePage = 'inventory-materials';
// ============================================================

$activePage = $activePage ?? '';
$role       = $_SESSION['user_role']  ?? 'staff';
$userName   = $_SESSION['user_name']  ?? 'User';

// Generate avatar initials from full name
$words    = explode(' ', trim($userName));
$initials = '';
foreach ($words as $w) {
    if ($w !== '') $initials .= strtoupper($w[0]);
}
$initials = substr($initials, 0, 2);

$dashLink = $role === 'admin'
    ? '/tailorshop/modules/dashboard/admin.php'
    : '/tailorshop/modules/dashboard/employee.php';

// Helper: returns 'active' class if page matches
function navActive(string $page, string $current): string {
    return $page === $current ? 'active' : '';
}
?>

<aside class="sidebar">

  <!-- Brand -->
  <a href="<?= $dashLink ?>" class="sidebar-brand">
    <div class="sidebar-brand-icon">
      <i class="bi bi-scissors"></i>
    </div>
    <div>
      <div class="sidebar-brand-name">Desire's</div>
      <div class="sidebar-brand-sub">Tailor Shop</div>
    </div>
  </a>

  <!-- Main navigation -->
  <div class="sidebar-section">Main</div>
  <ul class="sidebar-nav">
    <li>
      <a href="<?= $dashLink ?>" class="<?= navActive('dashboard', $activePage) ?>">
        <i class="bi bi-speedometer2"></i> Dashboard
      </a>
    </li>
  </ul>

  <!-- Inventory -->
  <div class="sidebar-section">Inventory</div>
  <ul class="sidebar-nav">
    <li>
      <a href="/tailorshop/modules/inventory/materials/index.php"
         class="<?= navActive('inventory-materials', $activePage) ?>">
        <i class="bi bi-box-seam"></i> Materials
      </a>
    </li>
    <li>
      <a href="/tailorshop/modules/inventory/garments/index.php"
         class="<?= navActive('inventory-garments', $activePage) ?>">
        <i class="bi bi-bag-heart"></i> Garments
      </a>
    </li>
  </ul>

  <!-- Operations -->
  <div class="sidebar-section">Operations</div>
  <ul class="sidebar-nav">
    <li>
      <a href="/tailorshop/modules/customers/index.php"
         class="<?= navActive('customers', $activePage) ?>">
        <i class="bi bi-people"></i> Customers
      </a>
    </li>
    <li>
      <a href="/tailorshop/modules/reservations/index.php"
         class="<?= navActive('reservations', $activePage) ?>">
        <i class="bi bi-calendar-check"></i> Reservations
      </a>
    </li>
    <li>
      <a href="/tailorshop/modules/rentals/index.php"
         class="<?= navActive('rentals', $activePage) ?>">
        <i class="bi bi-handbag"></i> Rentals
      </a>
    </li>
    <li>
      <a href="/tailorshop/modules/tailoring/index.php"
         class="<?= navActive('tailoring', $activePage) ?>">
        <i class="bi bi-thread"></i> Tailoring Orders
      </a>
    </li>
  </ul>

  <!-- Admin-only sections -->
  <?php if ($role === 'admin'): ?>
    <div class="sidebar-section">Reports</div>
    <ul class="sidebar-nav">
      <li>
        <a href="/tailorshop/modules/sales/index.php"
           class="<?= navActive('sales', $activePage) ?>">
          <i class="bi bi-cash-stack"></i> Sales Records
        </a>
      </li>
      <li>
        <a href="/tailorshop/modules/sales/reports.php"
           class="<?= navActive('reports', $activePage) ?>">
          <i class="bi bi-bar-chart-line"></i> Income Reports
        </a>
      </li>
    </ul>

    <div class="sidebar-section">Admin</div>
    <ul class="sidebar-nav">
      <li>
        <a href="/tailorshop/modules/users/index.php"
           class="<?= navActive('users', $activePage) ?>">
          <i class="bi bi-person-gear"></i> Staff Accounts
        </a>
      </li>
      <li>
        <a href="/tailorshop/modules/logs/audit-logs.php"
           class="<?= navActive('logs', $activePage) ?>">
          <i class="bi bi-journal-text"></i> Audit Logs
        </a>
      </li>
      <li>
        <a href="/tailorshop/modules/logs/sms-logs.php"
           class="<?= navActive('sms-logs', $activePage) ?>">
          <i class="bi bi-chat-dots"></i> SMS Logs
        </a>
      </li>
      <li>
        <a href="/tailorshop/modules/settings/index.php"
           class="<?= navActive('settings', $activePage) ?>">
          <i class="bi bi-gear"></i> Settings
        </a>
      </li>
    </ul>
  <?php endif; ?>

  <!-- Sidebar footer: user info + logout -->
  <div class="sidebar-footer">
    <div class="sidebar-user">
      <div class="sidebar-user-avatar"><?= htmlspecialchars($initials) ?></div>
      <div style="overflow:hidden">
        <div class="sidebar-user-name"><?= htmlspecialchars($userName) ?></div>
        <div class="sidebar-user-role"><?= ucfirst($role) ?></div>
      </div>
    </div>
    <ul class="sidebar-nav" style="margin-top:4px">
      <li>
        <a href="/tailorshop/auth/logout.php" style="color:#fca5a5">
          <i class="bi bi-box-arrow-left"></i> Log Out
        </a>
      </li>
    </ul>
  </div>

</aside>