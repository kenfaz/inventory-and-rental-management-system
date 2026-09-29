<?php
// ============================================================
// helpers/rental-helper.php
// Penalty computation, status sync, and overdue detection
// ============================================================

require_once __DIR__ . '/sms-helper.php';
require_once __DIR__ . '/audit-helper.php';

/**
 * Compute penalty for an overdue rental
 *
 * @param string $expectedReturn  Y-m-d
 * @param float  $penaltyRate     Penalty per day in PHP peso
 * @param string|null $actualReturn  Y-m-d, defaults to today
 * @return array ['days_overdue' => int, 'total_penalty' => float]
 */
function computePenalty(
    string $expectedReturn,
    float  $penaltyRate,
    ?string $actualReturn = null
): array {
    $returnDate   = new DateTime($actualReturn ?? date('Y-m-d'));
    $expectedDate = new DateTime($expectedReturn);

    if ($returnDate <= $expectedDate) {
        return ['days_overdue' => 0, 'total_penalty' => 0.00];
    }

    $daysOverdue  = (int) $expectedDate->diff($returnDate)->days;
    $totalPenalty = $daysOverdue * $penaltyRate;

    return [
        'days_overdue'  => $daysOverdue,
        'total_penalty' => $totalPenalty,
    ];
}

/**
 * Sync rental statuses based on today's date
 * Updates Active → Due Today or Overdue as needed
 * Called on dashboard load and by scheduler
 */
function syncRentalStatuses(): void {
    global $pdo;

    $today = date('Y-m-d');

    // Mark as Due Today
    $pdo->prepare('
        UPDATE rentals
        SET status = "Due Today"
        WHERE status = "Active"
          AND expected_return_date = ?
    ')->execute([$today]);

    // Mark as Overdue
    $pdo->prepare('
        UPDATE rentals
        SET status = "Overdue"
        WHERE status IN ("Active","Due Today")
          AND expected_return_date < ?
    ')->execute([$today]);
}

/**
 * Get all active rentals with upcoming return dates
 * Used by SMS scheduler for reminder detection
 *
 * @param int $daysAhead Number of days ahead to check
 * @return array
 */
function getRentalsNearingReturn(int $daysAhead = 3): array {
    global $pdo;

    $targetDate = date('Y-m-d', strtotime("+{$daysAhead} days"));

    return $pdo->prepare('
        SELECT r.*, c.full_name AS customer_name, c.mobile_number,
               g.garment_name
        FROM rentals r
        JOIN customers     c ON r.customer_id = c.customer_id
        JOIN garment_items g ON r.garment_id  = g.garment_id
        WHERE r.expected_return_date = ?
          AND r.status IN ("Active","Due Today")
    ')->execute([$targetDate])
      ? $pdo->query('SELECT * FROM rentals WHERE 1=0')->fetchAll() // placeholder
      : [];
}

/**
 * Fetch rentals for SMS scheduler with join
 */
function fetchRentalsForReminder(int $daysAhead = 3): array {
    global $pdo;

    $targetDate = date('Y-m-d', strtotime("+{$daysAhead} days"));

    $stmt = $pdo->prepare('
        SELECT r.rental_id, r.expected_return_date, r.penalty_rate_per_day,
               c.full_name AS customer_name, c.mobile_number,
               g.garment_name
        FROM rentals r
        JOIN customers     c ON r.customer_id = c.customer_id
        JOIN garment_items g ON r.garment_id  = g.garment_id
        WHERE r.expected_return_date = ?
          AND r.status IN ("Active","Due Today")
    ');
    $stmt->execute([$targetDate]);
    return $stmt->fetchAll();
}

/**
 * Fetch all overdue rentals for escalating SMS
 */
function fetchOverdueRentals(): array {
    global $pdo;

    $stmt = $pdo->query('
        SELECT r.rental_id, r.expected_return_date, r.penalty_rate_per_day,
               c.full_name AS customer_name, c.mobile_number,
               g.garment_name
        FROM rentals r
        JOIN customers     c ON r.customer_id = c.customer_id
        JOIN garment_items g ON r.garment_id  = g.garment_id
        WHERE r.status = "Overdue"
    ');
    return $stmt->fetchAll();
}

/**
 * Get a status badge HTML for a rental status
 */
function rentalStatusBadge(string $status): string {
    $map = [
        'Active'    => ['class' => 'badge-active',    'icon' => 'bi-circle-fill'],
        'Due Today' => ['class' => 'badge-due-today',  'icon' => 'bi-exclamation-circle-fill'],
        'Overdue'   => ['class' => 'badge-overdue',    'icon' => 'bi-x-circle-fill'],
        'Returned'  => ['class' => 'badge-returned',   'icon' => 'bi-check-circle-fill'],
        'Cancelled' => ['class' => 'badge-cancelled',  'icon' => 'bi-dash-circle'],
    ];
    $s = $map[$status] ?? ['class' => 'badge-active', 'icon' => 'bi-circle'];
    return '<span class="badge-status ' . $s['class'] . '">
              <i class="bi ' . $s['icon'] . '" style="font-size:8px"></i>
              ' . htmlspecialchars($status) . '
            </span>';
}

/**
 * Get a status badge HTML for a reservation status
 */
function reservationStatusBadge(string $status): string {
    $map = [
        'Pending'   => ['class' => 'badge-pending',   'icon' => 'bi-hourglass'],
        'Confirmed' => ['class' => 'badge-confirmed',  'icon' => 'bi-check-circle'],
        'Converted' => ['class' => 'badge-completed',  'icon' => 'bi-arrow-right-circle'],
        'Cancelled' => ['class' => 'badge-cancelled',  'icon' => 'bi-x-circle'],
    ];
    $s = $map[$status] ?? ['class' => 'badge-pending', 'icon' => 'bi-circle'];
    return '<span class="badge-status ' . $s['class'] . '">
              <i class="bi ' . $s['icon'] . '" style="font-size:8px"></i>
              ' . htmlspecialchars($status) . '
            </span>';
}

/**
 * Get a status badge HTML for a garment availability status
 */
function garmentStatusBadge(string $status): string {
    $map = [
        'Available'   => ['class' => 'badge-available',  'icon' => 'bi-check-circle'],
        'Reserved'    => ['class' => 'badge-reserved',   'icon' => 'bi-bookmark'],
        'Rented Out'  => ['class' => 'badge-rented-out', 'icon' => 'bi-bag'],
        'Under Repair'=> ['class' => 'badge-due-today',  'icon' => 'bi-tools'],
        'Damaged'     => ['class' => 'badge-overdue',    'icon' => 'bi-exclamation-triangle'],
        'Sold'        => ['class' => 'badge-sold',       'icon' => 'bi-tag'],
    ];
    $s = $map[$status] ?? ['class' => 'badge-available', 'icon' => 'bi-circle'];
    return '<span class="badge-status ' . $s['class'] . '">
              <i class="bi ' . $s['icon'] . '" style="font-size:8px"></i>
              ' . htmlspecialchars($status) . '
            </span>';
}

/**
 * Get a status badge HTML for a tailoring order status
 */
function orderStatusBadge(string $status): string {
    $map = [
        'Pending'          => ['class' => 'badge-pending',     'icon' => 'bi-hourglass'],
        'In Progress'      => ['class' => 'badge-in-progress', 'icon' => 'bi-scissors'],
        'Ready for Pickup' => ['class' => 'badge-ready',       'icon' => 'bi-bag-check'],
        'Completed'        => ['class' => 'badge-completed',   'icon' => 'bi-check-circle'],
        'Cancelled'        => ['class' => 'badge-cancelled',   'icon' => 'bi-x-circle'],
    ];
    $s = $map[$status] ?? ['class' => 'badge-pending', 'icon' => 'bi-circle'];
    return '<span class="badge-status ' . $s['class'] . '">
              <i class="bi ' . $s['icon'] . '" style="font-size:8px"></i>
              ' . htmlspecialchars($status) . '
            </span>';
}