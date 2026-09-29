-- ============================================================
-- Desire's Tailor Shop Management System
-- Database Schema — Full System Build
-- ============================================================

CREATE DATABASE IF NOT EXISTS tailorshop
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE tailorshop;

-- ============================================================
-- TABLE 1: users
-- Stores admin and staff accounts
-- ============================================================
CREATE TABLE users (
  user_id     INT            NOT NULL AUTO_INCREMENT,
  full_name   VARCHAR(100)   NOT NULL,
  email       VARCHAR(150)   NOT NULL UNIQUE,
  password    VARCHAR(255)   NOT NULL,
  role        ENUM('admin','staff') NOT NULL DEFAULT 'staff',
  status      ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at  DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_login  DATETIME       NULL,
  PRIMARY KEY (user_id)
) ENGINE=InnoDB;

-- ============================================================
-- TABLE 2: password_resets
-- Stores time-limited password reset tokens
-- ============================================================
CREATE TABLE password_resets (
  id         INT          NOT NULL AUTO_INCREMENT,
  email      VARCHAR(150) NOT NULL,
  token      VARCHAR(255) NOT NULL UNIQUE,
  expires_at DATETIME     NOT NULL,
  used       TINYINT(1)   NOT NULL DEFAULT 0,
  created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB;

-- ============================================================
-- TABLE 3: material_categories
-- Categories for raw materials and supplies
-- ============================================================
CREATE TABLE material_categories (
  category_id   INT          NOT NULL AUTO_INCREMENT,
  category_name VARCHAR(100) NOT NULL UNIQUE,
  default_unit  VARCHAR(30)  NOT NULL DEFAULT 'piece',
  description   VARCHAR(255) NULL,
  PRIMARY KEY (category_id)
) ENGINE=InnoDB;

-- ============================================================
-- TABLE 4: garment_categories
-- Categories for garments available for sale or rent
-- ============================================================
CREATE TABLE garment_categories (
  category_id   INT          NOT NULL AUTO_INCREMENT,
  category_name VARCHAR(100) NOT NULL UNIQUE,
  description   VARCHAR(255) NULL,
  PRIMARY KEY (category_id)
) ENGINE=InnoDB;

-- ============================================================
-- TABLE 5: customers
-- Stores customer contact and address information
-- ============================================================
CREATE TABLE customers (
  customer_id   INT          NOT NULL AUTO_INCREMENT,
  full_name     VARCHAR(100) NOT NULL,
  mobile_number VARCHAR(20)  NOT NULL,
  address       VARCHAR(255) NULL,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (customer_id)
) ENGINE=InnoDB;

-- ============================================================
-- TABLE 6: material_items
-- Raw materials and supplies inventory
-- ============================================================
CREATE TABLE material_items (
  item_id             INT           NOT NULL AUTO_INCREMENT,
  category_id         INT           NOT NULL,
  item_name           VARCHAR(100)  NOT NULL,
  brand               VARCHAR(100)  NULL,
  quantity            DECIMAL(10,2) NOT NULL DEFAULT 0,
  unit                VARCHAR(30)   NOT NULL DEFAULT 'piece',
  low_stock_threshold DECIMAL(10,2) NOT NULL DEFAULT 5,
  description         VARCHAR(255)  NULL,
  created_at          DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP
                                    ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (item_id),
  CONSTRAINT fk_material_category
    FOREIGN KEY (category_id)
    REFERENCES material_categories (category_id)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ============================================================
-- TABLE 7: garment_items
-- Garments available for sale or rental
-- ============================================================
CREATE TABLE garment_items (
  garment_id          INT             NOT NULL AUTO_INCREMENT,
  category_id         INT             NOT NULL,
  garment_name        VARCHAR(100)    NOT NULL,
  garment_code        VARCHAR(50)     NOT NULL UNIQUE,
  size                VARCHAR(30)     NULL,
  color               VARCHAR(50)     NULL,
  description         TEXT            NULL,
  condition_status    ENUM(
                        'Good',
                        'Needs Cleaning',
                        'Needs Repair',
                        'Damaged'
                      ) NOT NULL DEFAULT 'Good',
  availability_status ENUM(
                        'Available',
                        'Reserved',
                        'Rented Out',
                        'Under Repair',
                        'Damaged',
                        'Sold'
                      ) NOT NULL DEFAULT 'Available',
  rental_price        DECIMAL(10,2)   NULL,
  sale_price          DECIMAL(10,2)   NULL,
  created_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP
                                      ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (garment_id),
  CONSTRAINT fk_garment_category
    FOREIGN KEY (category_id)
    REFERENCES garment_categories (category_id)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ============================================================
-- TABLE 8: reservations
-- Garment reservations before rental pickup
-- ============================================================
CREATE TABLE reservations (
  reservation_id      INT          NOT NULL AUTO_INCREMENT,
  customer_id         INT          NOT NULL,
  garment_id          INT          NOT NULL,
  processed_by        INT          NOT NULL,
  pickup_date         DATE         NOT NULL,
  return_date         DATE         NOT NULL,
  status              ENUM(
                        'Pending',
                        'Confirmed',
                        'Converted',
                        'Cancelled'
                      ) NOT NULL DEFAULT 'Confirmed',
  cancellation_reason VARCHAR(255) NULL,
  notes               TEXT         NULL,
  created_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (reservation_id),
  CONSTRAINT fk_reservation_customer
    FOREIGN KEY (customer_id)
    REFERENCES customers (customer_id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_reservation_garment
    FOREIGN KEY (garment_id)
    REFERENCES garment_items (garment_id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_reservation_user
    FOREIGN KEY (processed_by)
    REFERENCES users (user_id)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ============================================================
-- TABLE 9: rentals
-- Rental transactions per garment per customer
-- ============================================================
CREATE TABLE rentals (
  rental_id             INT           NOT NULL AUTO_INCREMENT,
  customer_id           INT           NOT NULL,
  garment_id            INT           NOT NULL,
  processed_by          INT           NOT NULL,
  reservation_id        INT           NULL,
  rental_date           DATE          NOT NULL,
  expected_return_date  DATE          NOT NULL,
  actual_return_date    DATE          NULL,
  rental_fee            DECIMAL(10,2) NOT NULL DEFAULT 0,
  deposit_amount        DECIMAL(10,2) NOT NULL DEFAULT 0,
  penalty_rate_per_day  DECIMAL(10,2) NOT NULL DEFAULT 0,
  total_penalty         DECIMAL(10,2) NOT NULL DEFAULT 0,
  payment_method        ENUM('Cash','GCash','Card','Other') NULL,
  payment_other         VARCHAR(100)  NULL,
  status                ENUM(
                          'Active',
                          'Due Today',
                          'Overdue',
                          'Returned',
                          'Cancelled'
                        ) NOT NULL DEFAULT 'Active',
  cancellation_reason   VARCHAR(255)  NULL,
  created_at            DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (rental_id),
  CONSTRAINT fk_rental_customer
    FOREIGN KEY (customer_id)
    REFERENCES customers (customer_id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_rental_garment
    FOREIGN KEY (garment_id)
    REFERENCES garment_items (garment_id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_rental_user
    FOREIGN KEY (processed_by)
    REFERENCES users (user_id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_rental_reservation
    FOREIGN KEY (reservation_id)
    REFERENCES reservations (reservation_id)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
-- TABLE 10: rental_condition_reports
-- Garment condition recorded on return
-- ============================================================
CREATE TABLE rental_condition_reports (
  report_id          INT          NOT NULL AUTO_INCREMENT,
  rental_id          INT          NOT NULL UNIQUE,
  recorded_by        INT          NOT NULL,
  condition_on_return ENUM(
                        'Good',
                        'Needs Cleaning',
                        'Needs Repair',
                        'Damaged'
                      ) NOT NULL DEFAULT 'Good',
  damage_notes       TEXT         NULL,
  deposit_status     ENUM(
                        'Returned',
                        'Partially Returned',
                        'Forfeited'
                      ) NOT NULL DEFAULT 'Returned',
  deposit_deducted   DECIMAL(10,2) NOT NULL DEFAULT 0,
  recorded_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (report_id),
  CONSTRAINT fk_condition_rental
    FOREIGN KEY (rental_id)
    REFERENCES rentals (rental_id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_condition_user
    FOREIGN KEY (recorded_by)
    REFERENCES users (user_id)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ============================================================
-- TABLE 11: tailoring_orders
-- Custom garment tailoring orders
-- ============================================================
CREATE TABLE tailoring_orders (
  order_id            INT           NOT NULL AUTO_INCREMENT,
  customer_id         INT           NOT NULL,
  assigned_staff_id   INT           NOT NULL,
  order_name          VARCHAR(150)  NOT NULL,
  garment_description TEXT          NOT NULL,
  price               DECIMAL(10,2) NOT NULL DEFAULT 0,
  down_payment        DECIMAL(10,2) NOT NULL DEFAULT 0,
  balance             DECIMAL(10,2) GENERATED ALWAYS AS (price - down_payment) STORED,
  payment_method      ENUM('Cash','GCash','Card','Other') NULL,
  payment_other       VARCHAR(100)  NULL,
  status              ENUM(
                        'Pending',
                        'In Progress',
                        'Ready for Pickup',
                        'Completed',
                        'Cancelled'
                      ) NOT NULL DEFAULT 'Pending',
  due_date            DATE          NOT NULL,
  completion_date     DATE          NULL,
  cancellation_reason VARCHAR(255)  NULL,
  notes               TEXT          NULL,
  created_at          DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP
                                    ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (order_id),
  CONSTRAINT fk_order_customer
    FOREIGN KEY (customer_id)
    REFERENCES customers (customer_id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_order_staff
    FOREIGN KEY (assigned_staff_id)
    REFERENCES users (user_id)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ============================================================
-- TABLE 12: order_measurements
-- Customer measurements stored per tailoring order
-- ============================================================
CREATE TABLE order_measurements (
  measurement_id INT           NOT NULL AUTO_INCREMENT,
  order_id       INT           NOT NULL UNIQUE,
  bust           DECIMAL(5,2)  NULL,
  waist          DECIMAL(5,2)  NULL,
  hips           DECIMAL(5,2)  NULL,
  length         DECIMAL(5,2)  NULL,
  shoulder       DECIMAL(5,2)  NULL,
  sleeve         DECIMAL(5,2)  NULL,
  notes          TEXT          NULL,
  PRIMARY KEY (measurement_id),
  CONSTRAINT fk_measurement_order
    FOREIGN KEY (order_id)
    REFERENCES tailoring_orders (order_id)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- TABLE 13: order_materials_used
-- Materials consumed per tailoring order
-- ============================================================
CREATE TABLE order_materials_used (
  record_id     INT           NOT NULL AUTO_INCREMENT,
  order_id      INT           NOT NULL,
  item_id       INT           NOT NULL,
  quantity_used DECIMAL(10,2) NOT NULL DEFAULT 1,
  unit          VARCHAR(30)   NOT NULL DEFAULT 'piece',
  PRIMARY KEY (record_id),
  CONSTRAINT fk_om_order
    FOREIGN KEY (order_id)
    REFERENCES tailoring_orders (order_id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_om_item
    FOREIGN KEY (item_id)
    REFERENCES material_items (item_id)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ============================================================
-- TABLE 14: sales_records
-- All completed financial transactions
-- ============================================================
CREATE TABLE sales_records (
  sale_id        INT           NOT NULL AUTO_INCREMENT,
  customer_id    INT           NOT NULL,
  processed_by   INT           NOT NULL,
  garment_id     INT           NULL,
  rental_id      INT           NULL,
  order_id       INT           NULL,
  sale_type      ENUM(
                   'Garment Sale',
                   'Rental Fee',
                   'Tailoring Payment'
                 ) NOT NULL,
  amount         DECIMAL(10,2) NOT NULL DEFAULT 0,
  payment_method ENUM('Cash','GCash','Card','Other') NOT NULL,
  payment_other  VARCHAR(100)  NULL,
  notes          TEXT          NULL,
  sale_date      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (sale_id),
  CONSTRAINT fk_sale_customer
    FOREIGN KEY (customer_id)
    REFERENCES customers (customer_id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_sale_user
    FOREIGN KEY (processed_by)
    REFERENCES users (user_id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_sale_garment
    FOREIGN KEY (garment_id)
    REFERENCES garment_items (garment_id)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT fk_sale_rental
    FOREIGN KEY (rental_id)
    REFERENCES rentals (rental_id)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT fk_sale_order
    FOREIGN KEY (order_id)
    REFERENCES tailoring_orders (order_id)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
-- TABLE 15: income_records
-- Aggregated income entries for sales monitoring
-- ============================================================
CREATE TABLE income_records (
  income_id      INT           NOT NULL AUTO_INCREMENT,
  sale_id        INT           NOT NULL UNIQUE,
  income_type    ENUM(
                   'Garment Sale',
                   'Rental Fee',
                   'Tailoring Payment'
                 ) NOT NULL,
  amount         DECIMAL(10,2) NOT NULL DEFAULT 0,
  payment_method ENUM('Cash','GCash','Card','Other') NOT NULL,
  date_recorded  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (income_id),
  CONSTRAINT fk_income_sale
    FOREIGN KEY (sale_id)
    REFERENCES sales_records (sale_id)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- TABLE 16: sessions
-- Active user sessions
-- ============================================================
CREATE TABLE sessions (
  session_id  INT          NOT NULL AUTO_INCREMENT,
  user_id     INT          NOT NULL,
  ip_address  VARCHAR(45)  NULL,
  login_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  logout_at   DATETIME     NULL,
  status      ENUM('Active','Expired') NOT NULL DEFAULT 'Active',
  PRIMARY KEY (session_id),
  CONSTRAINT fk_session_user
    FOREIGN KEY (user_id)
    REFERENCES users (user_id)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- TABLE 17: login_logs
-- Permanent record of every login and logout event
-- ============================================================
CREATE TABLE login_logs (
  log_id     INT         NOT NULL AUTO_INCREMENT,
  user_id    INT         NOT NULL,
  action     ENUM('Login','Logout') NOT NULL,
  ip_address VARCHAR(45) NULL,
  timestamp  DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (log_id),
  CONSTRAINT fk_loginlog_user
    FOREIGN KEY (user_id)
    REFERENCES users (user_id)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- TABLE 18: sms_logs
-- Record of every outbound SMS attempt
-- ============================================================
CREATE TABLE sms_logs (
  sms_log_id     INT          NOT NULL AUTO_INCREMENT,
  recipient_number VARCHAR(20) NOT NULL,
  message_content TEXT        NOT NULL,
  trigger_type   ENUM(
                   'Reservation Confirmation',
                   'Reservation Reminder',
                   'Rental Return Reminder',
                   'Rental Overdue Alert',
                   'Rental Overdue Escalation',
                   'Low Stock Alert',
                   'Order Ready Pickup'
                 ) NOT NULL,
  reference_id   INT          NULL,
  reference_type VARCHAR(50)  NULL,
  status         ENUM('Sent','Failed') NOT NULL DEFAULT 'Sent',
  failure_reason VARCHAR(255) NULL,
  timestamp      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (sms_log_id)
) ENGINE=InnoDB;

-- ============================================================
-- TABLE 19: audit_logs
-- Permanent record of all sensitive system actions
-- ============================================================
CREATE TABLE audit_logs (
  audit_id         INT          NOT NULL AUTO_INCREMENT,
  user_id          INT          NOT NULL,
  action_type      VARCHAR(100) NOT NULL,
  module           VARCHAR(100) NOT NULL,
  affected_table   VARCHAR(100) NULL,
  affected_record_id INT        NULL,
  previous_value   TEXT         NULL,
  new_value        TEXT         NULL,
  ip_address       VARCHAR(45)  NULL,
  timestamp        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (audit_id),
  CONSTRAINT fk_audit_user
    FOREIGN KEY (user_id)
    REFERENCES users (user_id)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;