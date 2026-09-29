<?php
// ============================================================
// helpers/sms-scheduler.php
// Checks all pending SMS triggers and dispatches messages
// Call this via cron or from api/run-scheduler.php
// ============================================================

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/sms-helper.php';
require_once __DIR__ . '/rental-helper.php';

/**
 * Run all scheduled SMS checks
 * Returns a summary of what was processed
 */
function runSMSScheduler(): array {
    $log = [];

    // 1. Sync rental statuses first
    syncRentalStatuses();
    $log[] = 'Rental statuses synced.';

    // 2. Rental return reminders (3 days before due)
    $log[] = '-- Rental reminders --';
    $rentalsForReminder = fetchRentalsForReminder(RENTAL_REMINDER_DAYS);
    foreach ($rentalsForReminder as $rental) {
        $sent = sendSMS(
            $rental['mobile_number'],
            buildRentalReminderSMS($rental),
            'Rental Return Reminder',
            $rental['rental_id'],
            'rentals'
        );
        $log[] = ($sent ? 'SENT' : 'FAILED') .
                 ' — Reminder to ' . $rental['customer_name'] .
                 ' for rental #' . $rental['rental_id'];
    }

    // 3. Reservation pickup reminders (3 days before pickup)
    $log[] = '-- Reservation reminders --';
    $reservationsForReminder = fetchReservationsForReminder(RESERVATION_REMINDER_DAYS);
    foreach ($reservationsForReminder as $res) {
        $sent = sendSMS(
            $res['mobile_number'],
            buildReservationReminderSMS($res),
            'Reservation Reminder',
            $res['reservation_id'],
            'reservations'
        );
        $log[] = ($sent ? 'SENT' : 'FAILED') .
                 ' — Pickup reminder to ' . $res['customer_name'] .
                 ' for reservation #' . $res['reservation_id'];
    }

    // 4. Overdue rental alerts (send every OVERDUE_ESCALATION_DAYS days)
    $log[] = '-- Overdue alerts --';
    $overdueRentals = fetchOverdueRentals();
    foreach ($overdueRentals as $rental) {
        // Check if escalation is due today
        if (shouldSendEscalation($rental['rental_id'])) {
            // SMS to customer
            sendSMS(
                $rental['mobile_number'],
                buildOverdueCustomerSMS($rental),
                'Rental Overdue Escalation',
                $rental['rental_id'],
                'rentals'
            );
            // SMS to owner
            sendSMS(
                ADMIN_SMS_NUMBER,
                buildOverdueOwnerSMS($rental),
                'Rental Overdue Alert',
                $rental['rental_id'],
                'rentals'
            );
            $log[] = 'Overdue escalation sent for rental #' . $rental['rental_id'];
        }
    }

    return $log;
}

/**
 * Fetch reservations nearing pickup date
 */
function fetchReservationsForReminder(int $daysAhead = 3): array {
    global $pdo;

    $targetDate = date('Y-m-d', strtotime("+{$daysAhead} days"));

    $stmt = $pdo->prepare('
        SELECT r.reservation_id, r.pickup_date, r.return_date,
               c.full_name AS customer_name, c.mobile_number,
               g.garment_name
        FROM reservations r
        JOIN customers     c ON r.customer_id = c.customer_id
        JOIN garment_items g ON r.garment_id  = g.garment_id
        WHERE r.pickup_date = ?
          AND r.status = "Confirmed"
    ');
    $stmt->execute([$targetDate]);
    return $stmt->fetchAll();
}

/**
 * Determine if an overdue rental should receive an escalation SMS today
 * Sends every OVERDUE_ESCALATION_DAYS days after the first overdue day
 */
function shouldSendEscalation(int $rentalId): bool {
    global $pdo;

    // Count previous escalation SMS for this rental
    $stmt = $pdo->prepare('
        SELECT COUNT(*) FROM sms_logs
        WHERE reference_id   = ?
          AND reference_type = "rentals"
          AND trigger_type   IN ("Rental Overdue Alert","Rental Overdue Escalation")
          AND DATE(timestamp) = CURDATE()
    ');
    $stmt->execute([$rentalId]);

    // Don't send more than once per day
    return $stmt->fetchColumn() == 0;
}