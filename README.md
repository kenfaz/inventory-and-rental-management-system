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
- Copy the root `.env.example` to `.env` and add your UniSMS secret key and
  sender ID. The local `.env` file is ignored by Git; operating-system
  environment variables take precedence over values in that file.
- SMS mock mode is enabled by default (`SMS_MOCK_MODE=true`): messages are
  generated and recorded in SMS Logs as “Mocked — not sent,” without contacting
  UniSMS or consuming credits. Set `SMS_MOCK_MODE=false` to enable live SMS.
  Existing databases must run `tailorshop/database/migrate-sms-mock-status.sql`
  once to add the “Mocked” log status.
- Admins can open **Admin → SMS Simulator** to preview mocked messages as
  phone notifications. The page polls for new mock messages and includes a
  demo button that records a sample without contacting UniSMS.
- Existing databases must run `tailorshop/database/migrate-email-notifications.sql`
  once before using email notifications.
- New customers must have a valid email address. Legacy customer records may
  keep an empty email; those customers continue to receive SMS only.
- SMS uses UniSMS. Configure `UNISMS_SECRET_KEY` and `UNISMS_SENDER_ID` in
  `.env` and set the admin phone number in `tailorshop/config/constants.php`.
  Live SMS requests can incur charges; email remains local to Mailpit with
  the default configuration.
