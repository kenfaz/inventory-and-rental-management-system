-- Run once for an existing Tailor Shop database.
USE tailorshop;

ALTER TABLE sms_logs
  MODIFY COLUMN status ENUM('Sent','Failed','Mocked') NOT NULL DEFAULT 'Sent';
