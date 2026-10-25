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
        if (smsAttemptedToday(
            $res['mobile_number'],
            'Reservation Reminder',
            (int)$res['reservation_id'],
            'reservations'
        )) {
            continue;
        }

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

    // 4. Overdue rental alerts
    $log[] = '-- Overdue alerts --';
    $overdueRentals = fetchOverdueRentals();
    foreach ($overdueRentals as $rental) {
        if (!isOverdueEscalationDue($rental)) {
            continue;
        }

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
            $log[] = ($sent ? 'EMAIL SENT' : 'EMAIL FAILED') . ' — Overdue alert for rental #' . $rentalId;
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
 * Build scheduled notifications that are due but have not been attempted today.
 *
 * @return array{notifications: array, warnings: array}
 */
function getPendingScheduledNotifications(): array {
    global $pdo;

    $notifications = [];
    $warnings = [];
    $adminRecipients = getActiveAdminEmailRecipients();

    foreach (fetchRentalsForReminder(RENTAL_REMINDER_DAYS) as $rental) {
        $rentalId = (int)$rental['rental_id'];
        $trigger = 'Rental Return Reminder';

        if (!smsAttemptedToday($rental['mobile_number'], $trigger, $rentalId, 'rentals')) {
            $notifications[] = pendingNotification(
                'SMS',
                $rental['mobile_number'],
                $rental['customer_name'],
                $trigger,
                $rentalId,
                'rentals',
                'Rental return reminder for customer',
                buildRentalReminderSMS($rental)
            );
        }
        if (!empty($rental['email']) &&
            !emailAttemptedToday($rental['email'], $trigger, $rentalId, 'rentals')) {
            $message = buildRentalReminderEmail($rental);
            $notifications[] = pendingNotification(
                'Email',
                $rental['email'],
                $rental['customer_name'],
                $trigger,
                $rentalId,
                'rentals',
                'Rental return reminder for customer',
                $message['message'],
                $message['subject']
            );
        }

        if (!smsAttemptedToday(ADMIN_SMS_NUMBER, $trigger, $rentalId, 'rentals')) {
            $notifications[] = pendingNotification(
                'SMS',
                ADMIN_SMS_NUMBER,
                'Shop admin',
                $trigger,
                $rentalId,
                'rentals',
                'Rental return reminder for admin',
                buildRentalOwnerReminderSMS($rental)
            );
        }
        foreach ($adminRecipients as $admin) {
            if (emailAttemptedToday($admin['email'], $trigger, $rentalId, 'rentals')) {
                continue;
            }
            $message = buildRentalReminderAdminEmail($rental);
            $notifications[] = pendingNotification(
                'Email',
                $admin['email'],
                $admin['full_name'],
                $trigger,
                $rentalId,
                'rentals',
                'Rental return reminder for admin',
                $message['message'],
                $message['subject']
            );
        }
    }

    foreach (fetchReservationsForReminder(RESERVATION_REMINDER_DAYS) as $reservation) {
        $reservationId = (int)$reservation['reservation_id'];
        $trigger = 'Reservation Reminder';
        if (smsAttemptedToday(
            $reservation['mobile_number'],
            $trigger,
            $reservationId,
            'reservations'
        )) {
            continue;
        }

        $notifications[] = pendingNotification(
            'SMS',
            $reservation['mobile_number'],
            $reservation['customer_name'],
            $trigger,
            $reservationId,
            'reservations',
            'Reservation pickup reminder',
            buildReservationReminderSMS($reservation)
        );
    }

    foreach (fetchOverdueRentals() as $rental) {
        if (!isOverdueEscalationDue($rental)) {
            continue;
        }

        $rentalId = (int)$rental['rental_id'];
        $customerTrigger = 'Rental Overdue Escalation';
        $adminTrigger = 'Rental Overdue Alert';
        if (!smsAttemptedToday($rental['mobile_number'], $customerTrigger, $rentalId, 'rentals')) {
            $notifications[] = pendingNotification(
                'SMS',
                $rental['mobile_number'],
                $rental['customer_name'],
                $customerTrigger,
                $rentalId,
                'rentals',
                'Overdue rental notice for customer',
                buildOverdueCustomerSMS($rental)
            );
        }
        if (!empty($rental['email']) &&
            !emailAttemptedToday($rental['email'], $customerTrigger, $rentalId, 'rentals')) {
            $message = buildOverdueCustomerEmail($rental);
            $notifications[] = pendingNotification(
                'Email',
                $rental['email'],
                $rental['customer_name'],
                $customerTrigger,
                $rentalId,
                'rentals',
                'Overdue rental notice for customer',
                $message['message'],
                $message['subject']
            );
        }

        if (!smsAttemptedToday(ADMIN_SMS_NUMBER, $adminTrigger, $rentalId, 'rentals')) {
            $notifications[] = pendingNotification(
                'SMS',
                ADMIN_SMS_NUMBER,
                'Shop admin',
                $adminTrigger,
                $rentalId,
                'rentals',
                'Overdue rental alert for admin',
                buildOverdueOwnerSMS($rental)
            );
        }
        foreach ($adminRecipients as $admin) {
            if (emailAttemptedToday($admin['email'], $adminTrigger, $rentalId, 'rentals')) {
                continue;
            }
            $message = buildOverdueAdminEmail($rental);
            $notifications[] = pendingNotification(
                'Email',
                $admin['email'],
                $admin['full_name'],
                $adminTrigger,
                $rentalId,
                'rentals',
                'Overdue rental alert for admin',
                $message['message'],
                $message['subject']
            );
        }
    }

    $lowStockItems = $pdo->query('
        SELECT item_id, item_name, brand, quantity, unit, low_stock_threshold
        FROM material_items
        WHERE quantity <= low_stock_threshold
        ORDER BY item_name
    ')->fetchAll();

    if (
        $lowStockItems &&
        !liveSmsAttemptedToday(LOW_STOCK_SMS_NUMBER, 'Low Stock Alert', 0, 'material_items_summary')
    ) {
        try {
            $notifications[] = pendingNotification(
                'SMS',
                LOW_STOCK_SMS_NUMBER,
                'Low-stock alert recipient',
                'Low Stock Alert',
                0,
                'material_items_summary',
                'Combined low-stock alert (' . count($lowStockItems) . ' items)',
                buildLowStockSMS($lowStockItems)
            );
        } catch (LengthException $e) {
            error_log('Combined low-stock SMS skipped: ' . $e->getMessage());
            $warnings[] = 'Combined low-stock SMS exceeds the UniSMS 670-character limit and cannot be sent.';
        }
    }

    foreach ($lowStockItems as $item) {
        $itemId = (int)$item['item_id'];
        $message = buildLowStockEmail($item);
        foreach ($adminRecipients as $admin) {
            if (emailAttemptedToday($admin['email'], 'Low Stock Alert', $itemId, 'material_items')) {
                continue;
            }
            $notifications[] = pendingNotification(
                'Email',
                $admin['email'],
                $admin['full_name'],
                'Low Stock Alert',
                $itemId,
                'material_items',
                'Low-stock alert for ' . $item['item_name'],
                $message['message'],
                $message['subject']
            );
        }
    }

    return ['notifications' => $notifications, 'warnings' => $warnings];
}

function pendingNotification(
    string $channel,
    string $recipient,
    string $recipientName,
    string $trigger,
    int $referenceId,
    string $referenceType,
    string $description,
    string $message,
    ?string $subject = null
): array {
    $identity = implode("\0", [
        $channel,
        $recipient,
        $trigger,
        (string)$referenceId,
        $referenceType,
        $subject ?? '',
        $message,
    ]);

    return [
        'id' => hash('sha256', $identity),
        'channel' => $channel,
        'recipient' => $recipient,
        'recipient_name' => $recipientName,
        'trigger_type' => $trigger,
        'reference_id' => $referenceId,
        'reference_type' => $referenceType,
        'description' => $description,
        'message' => $message,
        'subject' => $subject,
    ];
}

function isOverdueEscalationDue(array $rental): bool {
    $expectedReturn = new DateTimeImmutable($rental['expected_return_date']);
    $today = new DateTimeImmutable('today');
    if ($expectedReturn >= $today) {
        return false;
    }

    $daysOverdue = (int)$expectedReturn->diff($today)->days;
    $interval = max(1, OVERDUE_ESCALATION_DAYS);

    return ($daysOverdue - 1) % $interval === 0;
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
