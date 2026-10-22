<?php
use PHPMailer\PHPMailer\Exception as MailerException;
use PHPMailer\PHPMailer\PHPMailer;

// ============================================================
// helpers/email-helper.php
// Sends email through the configured local SMTP server and logs results
// ============================================================

require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/db.php';

$mailAutoload = dirname(__DIR__, 2) . '/vendor/autoload.php';
if (is_file($mailAutoload)) {
    require_once $mailAutoload;
}

function sendEmail(
    string $address,
    string $recipientName,
    string $subject,
    string $message,
    string $triggerType,
    ?int $referenceId = null,
    ?string $referenceType = null
): bool {
    global $pdo;

    $status = 'Failed';
    $failureReason = null;
    $mail = null;

    try {
        if (!class_exists(PHPMailer::class)) {
            throw new RuntimeException('PHPMailer is not installed. Run Composer install in the project root.');
        }
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = MAIL_SMTP_HOST;
        $mail->Port = MAIL_SMTP_PORT;
        $mail->SMTPAuth = false;
        $mail->SMTPAutoTLS = false;
        $mail->SMTPSecure = '';
        $mail->CharSet = PHPMailer::CHARSET_UTF8;
        $mail->setFrom(MAIL_FROM_ADDRESS, MAIL_FROM_NAME);
        $mail->addAddress($address, $recipientName);
        $mail->Subject = $subject;
        $mail->Body = $message;
        $mail->isHTML(false);
        $mail->send();
        $status = 'Sent';
    } catch (MailerException $e) {
        $failureReason = ($mail instanceof PHPMailer && $mail->ErrorInfo !== '')
            ? $mail->ErrorInfo
            : $e->getMessage();
    } catch (Throwable $e) {
        $failureReason = $e->getMessage();
    }
    if ($failureReason !== null) {
        $failureReason = substr($failureReason, 0, 255);
    }

    try {
        $pdo->prepare('
            INSERT INTO email_logs
                (recipient_email, recipient_name, subject, message_content,
                 trigger_type, reference_id, reference_type, status, failure_reason)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ')->execute([
            $address,
            $recipientName,
            $subject,
            $message,
            $triggerType,
            $referenceId,
            $referenceType,
            $status,
            $failureReason,
        ]);
    } catch (Throwable $e) {
        error_log('Email log error: ' . $e->getMessage());
    }

    if ($status === 'Failed') {
        error_log('Email delivery failed for ' . $address . ': ' . $failureReason);
    }

    return $status === 'Sent';
}

function emailAttemptedToday(
    string $address,
    string $triggerType,
    int $referenceId,
    string $referenceType
): bool {
    global $pdo;

    $stmt = $pdo->prepare('
        SELECT COUNT(*)
        FROM email_logs
        WHERE recipient_email = ?
          AND trigger_type = ?
          AND reference_id = ?
          AND reference_type = ?
          AND DATE(timestamp) = CURDATE()
    ');
    $stmt->execute([$address, $triggerType, $referenceId, $referenceType]);

    return (int)$stmt->fetchColumn() > 0;
}

function getActiveAdminEmailRecipients(): array
{
    global $pdo;

    $stmt = $pdo->query('
        SELECT email, full_name
        FROM users
        WHERE role = "admin" AND status = "active" AND email <> ""
        ORDER BY user_id
    ');

    return $stmt->fetchAll();
}

function buildRentalReminderEmail(array $rental): array
{
    return [
        'subject' => 'Rental return reminder',
        'message' => sprintf(
            "Hello %s,\n\nThis is a reminder that your rented %s from %s is due for return on %s.\n" .
            "Late returns are charged ₱%.2f per day.\n\nThank you,\n%s\n%s",
            $rental['customer_name'],
            $rental['garment_name'],
            SHOP_NAME,
            date('F j, Y', strtotime($rental['expected_return_date'])),
            $rental['penalty_rate_per_day'],
            SHOP_NAME,
            SHOP_CONTACT
        ),
    ];
}

function buildRentalReminderAdminEmail(array $rental): array
{
    return [
        'subject' => 'Rental return due soon: ' . $rental['customer_name'],
        'message' => sprintf(
            "Rental return reminder:\n\nCustomer: %s\nGarment: %s\nDue date: %s\n" .
            "Customer phone: %s\nCustomer email: %s\nRental ID: %d",
            $rental['customer_name'],
            $rental['garment_name'],
            date('F j, Y', strtotime($rental['expected_return_date'])),
            $rental['mobile_number'],
            $rental['email'] ?: 'Not provided',
            $rental['rental_id']
        ),
    ];
}

function buildOverdueCustomerEmail(array $rental): array
{
    $daysLate = max(1, (int)floor(
        (time() - strtotime($rental['expected_return_date'])) / 86400
    ));

    return [
        'subject' => 'Overdue rental notice',
        'message' => sprintf(
            "Hello %s,\n\nYour rented %s from %s is %d day(s) overdue. " .
            "Please return it as soon as possible to avoid further charges.\n\n" .
            "Contact us: %s\n%s",
            $rental['customer_name'],
            $rental['garment_name'],
            SHOP_NAME,
            $daysLate,
            SHOP_CONTACT,
            SHOP_NAME
        ),
    ];
}

function buildOverdueAdminEmail(array $rental): array
{
    $daysLate = max(1, (int)floor(
        (time() - strtotime($rental['expected_return_date'])) / 86400
    ));

    return [
        'subject' => 'Overdue rental alert: ' . $rental['customer_name'],
        'message' => sprintf(
            "OVERDUE RENTAL ALERT\n\nCustomer: %s\nGarment: %s\nDays overdue: %d\n" .
            "Expected return: %s\nCustomer phone: %s\nCustomer email: %s\nRental ID: %d",
            $rental['customer_name'],
            $rental['garment_name'],
            $daysLate,
            date('F j, Y', strtotime($rental['expected_return_date'])),
            $rental['mobile_number'],
            $rental['email'] ?: 'Not provided',
            $rental['rental_id']
        ),
    ];
}

function buildLowStockEmail(array $item): array
{
    return [
        'subject' => 'Low stock alert: ' . $item['item_name'],
        'message' => sprintf(
            "LOW STOCK ALERT — %s\n\nItem: %s\nBrand: %s\nCurrent stock: %s %s\n" .
            "Low-stock threshold: %s %s\n\nPlease restock this item.",
            SHOP_NAME,
            $item['item_name'],
            $item['brand'] ?: 'No brand',
            rtrim(rtrim(number_format((float)$item['quantity'], 2), '0'), '.'),
            $item['unit'],
            rtrim(rtrim(number_format((float)$item['low_stock_threshold'], 2), '0'), '.'),
            $item['unit']
        ),
    ];
}
