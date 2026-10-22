# Business background
# Features & Functionalities

## Local notification setup

Rental return reminders, overdue rental alerts, and low-stock alerts send SMS
and email independently. Reservation pickup reminders remain SMS-only. Email
uses PHPMailer and the local Mailpit SMTP service by default:

- Run `composer install` from the project root to install PHPMailer.
- Mailpit's SMTP endpoint defaults to `127.0.0.1:1025`; its web inbox is at
  `http://127.0.0.1:8025`.
- Override `MAIL_SMTP_HOST`, `MAIL_SMTP_PORT`, `MAIL_FROM_ADDRESS`, and
  `MAIL_FROM_NAME` in the environment to use a different SMTP server.
- Existing databases must run `tailorshop/database/migrate-email-notifications.sql`
  once before using email notifications.
- New customers must have a valid email address. Legacy customer records may
  keep an empty email; those customers continue to receive SMS only.
- SMS still requires valid Semaphore credentials and an admin phone number in
  `tailorshop/config/constants.php`. Running the scheduler can make real SMS
  requests; email remains local to Mailpit with the default configuration.
