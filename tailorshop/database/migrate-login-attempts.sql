-- Run once for an existing Tailor Shop database.
USE tailorshop;

CREATE TABLE IF NOT EXISTS login_attempts (
  attempt_key     CHAR(64)          NOT NULL,
  failed_attempts TINYINT UNSIGNED  NOT NULL DEFAULT 0,
  last_failed_at  DATETIME          NULL,
  locked_until    DATETIME          NULL,
  PRIMARY KEY (attempt_key),
  KEY idx_login_attempts_last_failed_at (last_failed_at),
  KEY idx_login_attempts_locked_until (locked_until)
) ENGINE=InnoDB;
