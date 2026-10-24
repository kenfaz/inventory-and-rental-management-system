<?php
// ============================================================
// helpers/sms-scheduler.php
// Checks scheduled notification triggers and dispatches SMS and email
// Call this via cron or from api/run-scheduler.php
// ============================================================

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/sms-helper.php';
require_once __DIR__ . '/email-helper.php';
require_once __DIR__ . '/rental-helper.php';

/**
 * Run all scheduled SMS and email checks
 * Returns a summary of what was processed
 */
function runSMSScheduler(): array {
    $log = [];
    $adminRecipients = getActiveAdminEmailRecipients();
    if (!$adminRecipients) {
        error_log('Notification scheduler: no active admin email recipients are configured.');
        $log[] = 'No active admin email recipients configured.';
    }

    // 1. Sync rental statuses first
    syncRentalStatuses();
    $log[] = 'Rental statuses synced.';

    // 2. Rental return reminders (3 days before due)
    $log[] = '-- Rental reminders --';
    $rentalsForReminder = fetchRentalsForReminder(RENTAL_REMINDER_DAYS);
    foreach ($rentalsForReminder as $rental) {
        $trigger = 'Rental Return Reminder';
        $rentalId = (int)$rental['rental_id'];

        if (!smsAttemptedToday($rental['mobile_number'], $trigger, $rentalId, 'rentals')) {
            $sent = sendSMS(
                $rental['mobile_number'],
                buildRentalReminderSMS($rental),
                $trigger,
                $rentalId,
                'rentals'
            );
            $log[] = ($sent ? (SMS_MOCK_MODE ? 'SMS MOCKED' : 'SMS SENT') : 'SMS FAILED') .
                     ' — Customer reminder for rental #' . $rentalId;
        }
        if (!emailAttemptedToday($rental['email'] ?? '', $trigger, $rentalId, 'rentals')) {
            if (!empty($rental['email'])) {
                $message = buildRentalReminderEmail($rental);
                $sent = sendEmail(
                    $rental['email'],
                    $rental['customer_name'],
                    $message['subject'],
                    $message['message'],
                    $trigger,
                    $rentalId,
                    'rentals'
                );
                $log[] = ($sent ? 'EMAIL SENT' : 'EMAIL FAILED') .
                         ' — Customer reminder for rental #' . $rentalId;
            } else {
                error_log('Rental reminder email skipped: customer email missing for rental #' . $rentalId);
                $log[] = 'EMAIL SKIPPED — Customer email missing for rental #' . $rentalId;
            }
        }

        if (!smsAttemptedToday(ADMIN_SMS_NUMBER, $trigger, $rentalId, 'rentals')) {
            $sent = sendSMS(
                ADMIN_SMS_NUMBER,
                buildRentalOwnerReminderSMS($rental),
                $trigger,
                $rentalId,
                'rentals'
            );
            $log[] = ($sent ? (SMS_MOCK_MODE ? 'SMS MOCKED' : 'SMS SENT') : 'SMS FAILED') .
                     ' — Admin reminder for rental #' . $rentalId;
        }
        foreach ($adminRecipients as $admin) {
            if (emailAttemptedToday($admin['email'], $trigger, $rentalId, 'rentals')) {
                continue;
            }
            $message = buildRentalReminderAdminEmail($rental);
            $sent = sendEmail(
                $admin['email'],
                $admin['full_name'],
                $message['subject'],
                $message['message'],
                $trigger,
                $rentalId,
                'rentals'
            );
            $log[] = ($sent ? 'EMAIL SENT' : 'EMAIL FAILED') .
                     ' — Admin reminder for rental #' . $rentalId;
        }
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
        $log[] = ($sent ? (SMS_MOCK_MODE ? 'MOCKED' : 'SENT') : 'FAILED') .
                 ' — Pickup reminder to ' . $res['customer_name'] .
                 ' for reservation #' . $res['reservation_id'];
    }

    // 4. Overdue rental alerts (send every OVERDUE_ESCALATION_DAYS days)
    $log[] = '-- Overdue alerts --';
    $overdueRentals = fetchOverdueRentals();
    foreach ($overdueRentals as $rental) {
        // Check if escalation is due today
        if (shouldSendEscalation($rental['rental_id'])) {
            $rentalId = (int)$rental['rental_id'];
            $customerTrigger = 'Rental Overdue Escalation';
            $adminTrigger = 'Rental Overdue Alert';

            if (!smsAttemptedToday($rental['mobile_number'], $customerTrigger, $rentalId, 'rentals')) {
                $sent = sendSMS(
                    $rental['mobile_number'],
                    buildOverdueCustomerSMS($rental),
                    $customerTrigger,
                    $rentalId,
                    'rentals'
                );
                $log[] = ($sent ? (SMS_MOCK_MODE ? 'SMS MOCKED' : 'SMS SENT') : 'SMS FAILED') .
                         ' — Overdue notice to customer #' . $rentalId;
            }
            if (!empty($rental['email']) &&
                !emailAttemptedToday($rental['email'], $customerTrigger, $rentalId, 'rentals')) {
                $message = buildOverdueCustomerEmail($rental);
                $sent = sendEmail(
                    $rental['email'],
                    $rental['customer_name'],
                    $message['subject'],
                    $message['message'],
                    $customerTrigger,
                    $rentalId,
                    'rentals'
                );
                $log[] = ($sent ? 'EMAIL SENT' : 'EMAIL FAILED') . ' — Overdue notice to customer #' . $rentalId;
            } elseif (empty($rental['email'])) {
                error_log('Overdue email skipped: customer email missing for rental #' . $rentalId);
                $log[] = 'EMAIL SKIPPED — Customer email missing for rental #' . $rentalId;
            }

            if (!smsAttemptedToday(ADMIN_SMS_NUMBER, $adminTrigger, $rentalId, 'rentals')) {
                $sent = sendSMS(
                    ADMIN_SMS_NUMBER,
                    buildOverdueOwnerSMS($rental),
                    $adminTrigger,
                    $rentalId,
                    'rentals'
                );
                $log[] = ($sent ? (SMS_MOCK_MODE ? 'SMS MOCKED' : 'SMS SENT') : 'SMS FAILED') .
                         ' — Overdue alert to admin #' . $rentalId;
            }
            foreach ($adminRecipients as $admin) {
                if (emailAttemptedToday($admin['email'], $adminTrigger, $rentalId, 'rentals')) {
                    continue;
                }
                $message = buildOverdueAdminEmail($rental);
                $sent = sendEmail(
                    $admin['email'],
                    $admin['full_name'],
                    $message['subject'],
                    $message['message'],
                    $adminTrigger,
                    $rentalId,
                    'rentals'
                );
                $log[] = ($sent ? 'EMAIL SENT' : 'EMAIL FAILED') . ' — Overdue alert to admin #' . $rentalId;
            }
        }
    }

    // 5. Low/depleted material stock alerts to admins by SMS and email
    $log[] = '-- Low stock alerts --';
    global $pdo;
    $lowStockItems = $pdo->query('
        SELECT item_id, item_name, brand, quantity, unit, low_stock_threshold
        FROM material_items
        WHERE quantity <= low_stock_threshold
        ORDER BY item_name
    ')->fetchAll();

    $trigger = 'Low Stock Alert';
    if (
        $lowStockItems &&
        !smsAttemptedToday(LOW_STOCK_SMS_NUMBER, $trigger, 0, 'material_items_summary')
    ) {
        try {
            $sent = sendSMS(
                LOW_STOCK_SMS_NUMBER,
                buildLowStockSMS($lowStockItems),
                $trigger,
                0,
                'material_items_summary'
            );
            $log[] = ($sent ? (SMS_MOCK_MODE ? 'SMS MOCKED' : 'SMS SENT') : 'SMS FAILED') .
                     ' — Combined low stock alert for ' . count($lowStockItems) . ' item(s)';
        } catch (LengthException $e) {
            error_log('Combined low-stock SMS skipped: ' . $e->getMessage());
            $log[] = 'SMS SKIPPED — Combined low-stock alert exceeds UniSMS 670-character limit';
        }
    }

    foreach ($lowStockItems as $item) {
        $itemId = (int)$item['item_id'];

        $message = buildLowStockEmail($item);
        foreach ($adminRecipients as $admin) {
            if (emailAttemptedToday($admin['email'], $trigger, $itemId, 'material_items')) {
                continue;
            }
            $sent = sendEmail(
                $admin['email'],
                $admin['full_name'],
                $message['subject'],
                $message['message'],
                $trigger,
                $itemId,
                'material_items'
            );
            $log[] = ($sent ? 'EMAIL SENT' : 'EMAIL FAILED') . ' — Low stock alert for ' . $item['item_name'];
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