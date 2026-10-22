-- Run once for an existing Tailor Shop database.
USE tailorshop;

ALTER TABLE customers
  ADD COLUMN email VARCHAR(254) NULL AFTER mobile_number;

CREATE TABLE IF NOT EXISTS email_logs (
  email_log_id     INT          NOT NULL AUTO_INCREMENT,
  recipient_email  VARCHAR(254) NOT NULL,
  recipient_name   VARCHAR(100) NULL,
  subject          VARCHAR(255) NOT NULL,
  message_content  TEXT         NOT NULL,
  trigger_type     VARCHAR(80)  NOT NULL,
  reference_id     INT          NULL,
  reference_type   VARCHAR(50)  NULL,
  status           ENUM('Sent','Failed') NOT NULL DEFAULT 'Failed',
  failure_reason   VARCHAR(255) NULL,
  timestamp        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (email_log_id),
  KEY idx_email_logs_reference (reference_type, reference_id, trigger_type, timestamp),
  KEY idx_email_logs_recipient (recipient_email, timestamp)
) ENGINE=InnoDB;
