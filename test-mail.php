<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$autoload = __DIR__ . '/vendor/autoload.php';
if (!is_file($autoload)) {
    fwrite(STDERR, "Composer autoloader not found. Install PHPMailer first.\n");
    exit(1);
}

require $autoload;

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

try {
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = '127.0.0.1';
    $mail->Port = 1025;
    $mail->SMTPAuth = false;
    $mail->SMTPSecure = false;
    $mail->CharSet = PHPMailer::CHARSET_UTF8;

    $mail->setFrom('alerts@tailorshop.local', "Desire's Tailor Shop");
    $mail->addAddress('test@example.com', 'Mailpit Test Recipient');
    $mail->Subject = 'Mailpit connection test';
    $mail->Body = 'PHPMailer successfully sent this test message to the local Mailpit inbox.';

    $mail->send();
    fwrite(STDOUT, "Test email sent to Mailpit. Open http://127.0.0.1:8025 to view it.\n");
} catch (Exception $e) {
    fwrite(STDERR, "Could not send test email to Mailpit: {$mail->ErrorInfo}\n");
    exit(1);
}
