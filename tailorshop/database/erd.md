# Desire's Tailor Shop Database ERD

This entity-relationship diagram is based on the tables and declared foreign
keys in `tailorshop.sql`.

```mermaid
erDiagram
    users {
        INT user_id PK
        VARCHAR full_name
        VARCHAR email UK
        VARCHAR password
        ENUM role
        ENUM status
        DATETIME created_at
        DATETIME last_login
    }

    password_resets {
        INT id PK
        VARCHAR email
        VARCHAR token UK
        DATETIME expires_at
        TINYINT used
        DATETIME created_at
    }

    material_categories {
        INT category_id PK
        VARCHAR category_name UK
        VARCHAR default_unit
        VARCHAR description
    }

    garment_categories {
        INT category_id PK
        VARCHAR category_name UK
        VARCHAR description
    }

    customers {
        INT customer_id PK
        VARCHAR full_name
        VARCHAR mobile_number
        VARCHAR email
        VARCHAR address
        DATETIME created_at
    }

    material_items {
        INT item_id PK
        INT category_id FK
        VARCHAR item_name
        VARCHAR brand
        DECIMAL quantity
        DECIMAL sale_price
        VARCHAR unit
        DECIMAL low_stock_threshold
        VARCHAR description
        DATETIME created_at
        DATETIME updated_at
    }

    garment_items {
        INT garment_id PK
        INT category_id FK
        VARCHAR garment_name
        VARCHAR garment_code UK
        VARCHAR size
        VARCHAR color
        TEXT description
        ENUM condition_status
        ENUM availability_status
        DECIMAL rental_price
        DECIMAL sale_price
        DATETIME created_at
        DATETIME updated_at
    }

    reservations {
        INT reservation_id PK
        INT customer_id FK
        INT garment_id FK
        INT processed_by FK
        DATE pickup_date
        DATE return_date
        ENUM status
        VARCHAR cancellation_reason
        TEXT notes
        DATETIME created_at
    }

    rentals {
        INT rental_id PK
        INT customer_id FK
        INT garment_id FK
        INT processed_by FK
        INT reservation_id FK
        DATE rental_date
        DATE expected_return_date
        DATE actual_return_date
        DECIMAL rental_fee
        DECIMAL deposit_amount
        DECIMAL penalty_rate_per_day
        DECIMAL total_penalty
        ENUM payment_method
        VARCHAR payment_other
        ENUM status
        VARCHAR cancellation_reason
        DATETIME created_at
    }

    rental_condition_reports {
        INT report_id PK
        INT rental_id FK,UK
        INT recorded_by FK
        ENUM condition_on_return
        TEXT damage_notes
        ENUM deposit_status
        DECIMAL deposit_deducted
        DATETIME recorded_at
    }

    tailoring_orders {
        INT order_id PK
        INT customer_id FK
        INT assigned_staff_id FK
        VARCHAR order_name
        TEXT garment_description
        DECIMAL price
        DECIMAL down_payment
        DECIMAL balance
        ENUM payment_method
        VARCHAR payment_other
        ENUM status
        DATE due_date
        DATE completion_date
        VARCHAR cancellation_reason
        TEXT notes
        DATETIME created_at
        DATETIME updated_at
    }

    order_measurements {
        INT measurement_id PK
        INT order_id FK,UK
        DECIMAL bust
        DECIMAL waist
        DECIMAL hips
        DECIMAL length
        DECIMAL shoulder
        DECIMAL sleeve
        TEXT notes
    }

    order_materials_used {
        INT record_id PK
        INT order_id FK
        INT item_id FK
        DECIMAL quantity_used
        VARCHAR unit
    }

    sales_records {
        INT sale_id PK
        INT customer_id FK
        INT processed_by FK
        INT garment_id FK
        INT material_id FK
        INT rental_id FK
        INT order_id FK
        ENUM sale_type
        DECIMAL amount
        ENUM payment_method
        VARCHAR payment_other
        TEXT notes
        DATETIME sale_date
    }

    income_records {
        INT income_id PK
        INT sale_id FK,UK
        ENUM income_type
        DECIMAL amount
        ENUM payment_method
        DATETIME date_recorded
    }

    sessions {
        INT session_id PK
        INT user_id FK
        VARCHAR ip_address
        DATETIME login_at
        DATETIME logout_at
        ENUM status
    }

    login_logs {
        INT log_id PK
        INT user_id FK
        ENUM action
        VARCHAR ip_address
        DATETIME timestamp
    }

    login_attempts {
        CHAR attempt_key PK
        TINYINT failed_attempts
        DATETIME last_failed_at
        DATETIME locked_until
    }

    sms_logs {
        INT sms_log_id PK
        VARCHAR recipient_number
        TEXT message_content
        ENUM trigger_type
        INT reference_id
        VARCHAR reference_type
        ENUM status
        VARCHAR failure_reason
        DATETIME timestamp
    }

    email_logs {
        INT email_log_id PK
        VARCHAR recipient_email
        VARCHAR recipient_name
        VARCHAR subject
        TEXT message_content
        VARCHAR trigger_type
        INT reference_id
        VARCHAR reference_type
        ENUM status
        VARCHAR failure_reason
        DATETIME timestamp
    }

    audit_logs {
        INT audit_id PK
        INT user_id FK
        VARCHAR action_type
        VARCHAR module
        VARCHAR affected_table
        INT affected_record_id
        TEXT previous_value
        TEXT new_value
        VARCHAR ip_address
        DATETIME timestamp
    }

    material_categories ||--o{ material_items : categorizes
    garment_categories ||--o{ garment_items : categorizes

    customers ||--o{ reservations : makes
    garment_items ||--o{ reservations : reserved_for
    users ||--o{ reservations : processes

    customers ||--o{ rentals : rents
    garment_items ||--o{ rentals : rented_as
    users ||--o{ rentals : processes
    reservations o|--o{ rentals : converts_to
    rentals ||--o| rental_condition_reports : has_return_report
    users ||--o{ rental_condition_reports : records

    customers ||--o{ tailoring_orders : places
    users ||--o{ tailoring_orders : assigned_to
    tailoring_orders ||--o| order_measurements : has_measurements
    tailoring_orders ||--o{ order_materials_used : consumes
    material_items ||--o{ order_materials_used : used_in

    customers ||--o{ sales_records : makes
    users ||--o{ sales_records : processes
    garment_items o|--o{ sales_records : sold_in
    material_items o|--o{ sales_records : sold_in
    rentals o|--o{ sales_records : charged_in
    tailoring_orders o|--o{ sales_records : paid_in
    sales_records ||--o| income_records : creates

    users ||--o{ sessions : has
    users ||--o{ login_logs : generates
    users ||--o{ audit_logs : performs
```

Notes:
- `balance` in `tailoring_orders` is a generated column (`price - down_payment`).
- `password_resets.email` is not declared as a foreign key to `users.email`.
- `login_attempts` and notification-log reference IDs are not declared with
  foreign-key constraints in the SQL schema, so no ER relationships are drawn
  for them.
- Optional foreign keys and one-to-one relationships are shown according to
  nullability and uniqueness in the schema.
