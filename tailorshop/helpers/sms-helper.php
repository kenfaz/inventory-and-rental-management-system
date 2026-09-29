<?php
// ============================================================
// helpers/sms-helper.php
// Sends SMS via Semaphore API and logs delivery result
// ============================================================

require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/db.php';

/**
 * Send an SMS via Semaphore API
 *
 * @param string $number        Recipient mobile number (e.g. 09171234567)
 * @param string $message       Message content
 * @param string $triggerType   One of the ENUM values in sms_logs.trigger_type
 * @param int|null $referenceId ID of the related record (rental_id, order_id, etc.)
 * @param string|null $referenceType Name of the related table
 * @return bool True if sent successfully, false otherwise
 */
function sendSMS(
    string $number,
    string $message,
    string $triggerType,
    ?int   $referenceId   = null,
    ?string $referenceType = null
): bool {
    global $pdo;

    $status        = 'Failed';
    $failureReason = null;

    try {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => SEMAPHORE_API_URL,
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_POSTFIELDS     => http_build_query([
                'apikey'      => SEMAPHORE_API_KEY,
                'number'      => $number,
                'message'     => $message,
                'sendername'  => SEMAPHORE_SENDER_NAME,
            ]),
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            $failureReason = 'cURL error: ' . $curlError;
        } elseif ($httpCode === 200) {
            $data = json_decode($response, true);
            // Semaphore returns array of message objects on success
            if (is_array($data) && !empty($data)) {
                $status = 'Sent';
            } else {
                $failureReason = 'Unexpected API response: ' . $response;
            }
        } else {
            $failureReason = 'HTTP ' . $httpCode . ': ' . $response;
        }

    } catch (Exception $e) {
        $failureReason = 'Exception: ' . $e->getMessage();
    }

    // Log the SMS attempt
    try {
        $pdo->prepare('
            INSERT INTO sms_logs
                (recipient_number, message_content, trigger_type,
                 reference_id, reference_type, status, failure_reason)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ')->execute([
            $number,
            $message,
            $triggerType,
            $referenceId,
            $referenceType,
            $status,
            $failureReason,
        ]);
    } catch (Exception $e) {
        error_log('SMS log error: ' . $e->getMessage());
    }

    return $status === 'Sent';
}

/**
 * Build reservation confirmation SMS message
 */
function buildReservationConfirmSMS(array $reservation): string {
    return sprintf(
        "Hi %s! Your reservation for %s at %s is confirmed. " .
        "Pickup date: %s. Return date: %s. " .
        "Please arrive on your pickup date. Thank you!",
        $reservation['customer_name'],
        $reservation['garment_name'],
        SHOP_NAME,
        date('F j, Y', strtotime($reservation['pickup_date'])),
        date('F j, Y', strtotime($reservation['return_date']))
    );
}

/**
 * Build reservation reminder SMS message (3 days before pickup)
 */
function buildReservationReminderSMS(array $reservation): string {
    return sprintf(
        "Hi %s! Reminder: Your reserved %s at %s is due for pickup on %s. " .
        "Please come on time. Contact us: %s.",
        $reservation['customer_name'],
        $reservation['garment_name'],
        SHOP_NAME,
        date('F j, Y', strtotime($reservation['pickup_date'])),
        SHOP_CONTACT
    );
}

/**
 * Build rental return reminder SMS (3 days before due date)
 */
function buildRentalReminderSMS(array $rental): string {
    return sprintf(
        "Hi %s! Reminder: Your rented %s from %s is due for return on %s. " .
        "Late returns are charged ₱%.2f/day. Thank you!",
        $rental['customer_name'],
        $rental['garment_name'],
        SHOP_NAME,
        date('F j, Y', strtotime($rental['expected_return_date'])),
        $rental['penalty_rate_per_day']
    );
}

/**
 * Build overdue rental alert SMS — sent to customer
 */
function buildOverdueCustomerSMS(array $rental): string {
    $daysLate = (int) floor(
        (time() - strtotime($rental['expected_return_date'])) / 86400
    );
    return sprintf(
        "Hi %s! Your rented %s from %s is %d day(s) overdue. " .
        "Please return it immediately to avoid further charges. " .
        "Contact us: %s.",
        $rental['customer_name'],
        $rental['garment_name'],
        SHOP_NAME,
        $daysLate,
        SHOP_CONTACT
    );
}

/**
 * Build overdue rental alert SMS — sent to owner
 */
function buildOverdueOwnerSMS(array $rental): string {
    $daysLate = (int) floor(
        (time() - strtotime($rental['expected_return_date'])) / 86400
    );
    return sprintf(
        "OVERDUE ALERT: %s's rental of %s is %d day(s) overdue. " .
        "Expected return was %s. Mobile: %s.",
        $rental['customer_name'],
        $rental['garment_name'],
        $daysLate,
        date('F j, Y', strtotime($rental['expected_return_date'])),
        $rental['mobile_number']
    );
}

/**
 * Build low stock alert SMS — sent to owner
 */
function buildLowStockSMS(array $item): string {
    return sprintf(
        "LOW STOCK ALERT [%s]: %s (%s) is low. " .
        "Current stock: %s %s. Please restock soon.",
        SHOP_NAME,
        $item['item_name'],
        $item['brand'] ?? 'No brand',
        rtrim(rtrim(number_format($item['quantity'], 2), '0'), '.'),
        $item['unit']
    );
}

/**
 * Build tailoring order ready SMS — sent to customer
 */
function buildOrderReadySMS(array $order): string {
    return sprintf(
        "Hi %s! Your tailoring order \"%s\" at %s is ready for pickup. " .
        "Remaining balance: ₱%.2f. Please visit us at your earliest convenience. " .
        "Contact us: %s.",
        $order['customer_name'],
        $order['order_name'],
        SHOP_NAME,
        $order['balance'],
        SHOP_CONTACT
    );
}