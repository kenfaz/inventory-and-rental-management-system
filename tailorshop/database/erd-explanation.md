# ERD Explanation for the Research Paper and Presentation

This document explains the database entity-relationship diagram (ERD) for
Desire's Tailor Shop Inventory and Rental Management System. The explanation
follows the tables and foreign-key constraints in `tailorshop.sql`. The
diagram itself is in [erd.md](./erd.md).

## 1. Research-paper context

### Database design description

The system uses a MySQL relational database to organize the information
required for user access, customer records, garment and material inventory,
reservations, rentals, tailoring orders, sales, income, and system activity
logs. The ERD presents the database entities, their key attributes, and the
relationships enforced by primary-key and foreign-key constraints.

Each entity represents a category of information maintained by the system.
Primary keys uniquely identify records, while foreign keys connect related
records and help preserve referential integrity. For example, a rental record
references a customer, a garment, and the staff user who processed it. This
allows the system to associate a rental with the relevant customer and
inventory item without repeating all of their details in the rental record.

The database separates operational records from supporting records. Customer,
garment, and material details are stored separately from reservation, rental,
tailoring, and sales transactions. Separate records also support garment
return-condition reporting, tailoring measurements and material usage, income
reporting, authentication history, notifications, and audit tracking. The
`order_materials_used` entity connects tailoring orders with material items
and records the quantity used for each order-material combination.

The ERD is based on the implemented database schema. It describes stored data
and declared relationships; it is not a process-flow diagram and does not
represent every business rule enforced in application code. For example,
availability changes, rental date checks, and notification scheduling are
application behaviors and are not themselves database entities.

### Suggested paragraph for the paper

> The Entity-Relationship Diagram (ERD) illustrates the structure of the
> relational database used by the Desire's Tailor Shop Inventory and Rental
> Management System. The database stores user accounts, customer information,
> garment and material inventory, reservations, rentals, tailoring orders,
> sales and income records, and supporting logs. Primary keys uniquely
> identify records, while foreign keys connect transactions to the customers,
> inventory items, and staff members involved. The design also includes
> supporting entities for rental return-condition reports, tailoring
> measurements, materials used in orders, and income entries associated with
> sales. This organization supports the retrieval and maintenance of related
> operational records while reducing the need to duplicate customer, product,
> or user details across transactions. The ERD reflects the relationships
> declared in the database schema; application-level rules and external
> notification services are outside its scope.

## 2. How to read the diagram

- **Entity/table:** A named box in the ERD, such as `customers` or `rentals`.
- **Attribute:** A field listed inside an entity, such as `full_name` or
  `rental_date`.
- **PK (primary key):** The field that uniquely identifies a row in a table.
- **FK (foreign key):** A field that references a key in another table.
- **UK (unique key):** A field, or combination of fields, whose value must be
  unique in the table.
- **Crow's-foot relationship:** The three-pronged end represents “many.”
  A circle means zero is allowed; a bar means one is required.

The diagram shows the schema's declared cardinalities. For example,
`customers ||--o{ rentals` means that each rental must reference one customer,
while a customer may have no rentals or multiple rentals.

## 3. Entity groups and their roles

### User and access records

- **`users`** stores admin and staff accounts. Other records refer to users
  when the system needs to identify who processed or recorded an action.
- **`password_resets`** stores reset-token information and its expiry/use
  status. Its `email` field is not declared as a foreign key to `users`.
- **`sessions`** is a database table associated with users in the schema.
- **`login_logs`** records login and logout events associated with users.
- **`login_attempts`** stores failed-login counts and lockout timestamps. It
  has no declared relationship to `users`.

### Customer and inventory records

- **`customers`** stores customer names and contact details. Reservations,
  rentals, tailoring orders, and sales records refer to customers.
- **`garment_categories`** groups garments; **`garment_items`** stores
  individual garments, including code, condition, availability, and prices.
- **`material_categories`** groups materials; **`material_items`** stores
  material stock, unit, sale price, and low-stock threshold.

### Reservation and rental records

- **`reservations`** records a customer's planned garment pickup and return,
  along with the garment, processing staff member, dates, and status.
- **`rentals`** records the active or completed rental, including dates, fees,
  deposit, payment method, penalty information, and status. A reservation
  reference is optional, allowing a rental to exist without a linked
  reservation.
- **`rental_condition_reports`** records the garment's condition and deposit
  handling when a rental is returned. Its unique rental foreign key allows at
  most one report per rental; a rental may have no report yet.

### Tailoring records

- **`tailoring_orders`** stores the customer, assigned staff member, order
  description, price, down payment, due date, and status. The `balance` field
  is generated from price minus down payment.
- **`order_measurements`** stores measurements for an order. Its unique
  `order_id` allows at most one measurement record per order; a tailoring
  order may have no measurement record.
- **`order_materials_used`** links tailoring orders to material items and
  records the quantity consumed. This is the associative entity for the
  many-to-many relationship between orders and materials: an order can use
  multiple materials, and a material item can be used in multiple orders.

### Financial and monitoring records

- **`sales_records`** records a financial transaction, including its customer,
  processing user, type, amount, and payment method. Optional foreign keys
  associate a sale with a garment, material, rental, or tailoring order.
- **`income_records`** stores the income entry for a sale. Its required,
  unique `sale_id` means each income entry belongs to exactly one sale and a
  sale can have at most one income entry. The schema does not require every
  sales record to have an income record.
- **`sms_logs`** and **`email_logs`** store outbound notification content,
  recipients, results, and timestamps. Their `reference_id` and
  `reference_type` fields are not declared as foreign keys, so the ERD does
  not draw direct relationships from these logs to a specific business table.
- **`audit_logs`** records user-linked system actions, including the affected
  module/record and previous or new values. The user foreign key identifies
  the user associated with the audit entry.

## 4. Main relationships to explain

1. **Categories and inventory:** Each garment or material item belongs to one
   category; a category may contain zero or many items.
2. **Customers and transactions:** A customer may have multiple reservations,
   rentals, tailoring orders, and sales. Each such transaction references one
   customer.
3. **Users and work records:** A user may process multiple reservations,
   rentals, and sales, or be assigned multiple tailoring orders. User links
   make staff actions and assignments traceable.
4. **Reservations and rentals:** A rental may optionally reference a
   reservation. The schema does not make the reservation reference unique, so
   the ERD reflects the database constraint rather than assuming only one
   rental can ever reference a reservation.
5. **Rentals and return reports:** A return report references one rental.
   Because the rental ID is unique in the report table, a rental can have at
   most one return report.
6. **Tailoring orders and details:** An order can have one optional
   measurement record and multiple material-usage records.
7. **Sales and income:** A sale may have zero or one income record; every
   income record must belong to one sale.

## 5. Short presentation script

> This ERD shows how the system stores and connects the data used in daily
> shop operations. The main records cover customers, staff, garment and
> material inventory, reservations, rentals, tailoring orders, and sales.
> Transactions connect to the people and items involved through foreign keys.
> Supporting tables hold details such as rental return condition, tailoring
> measurements, materials used, and income records. The relationship symbols
> indicate whether a record is required, optional, or can occur multiple
> times. For example, a customer can have multiple rentals, while each rental
> refers to one customer. The diagram represents the implemented database
> structure; operational rules such as checking availability are handled by
> the application.

## 6. Panelist-friendly questions and answers

### Why use an ERD?

The ERD provides a visual summary of the database structure. It helps explain
what information is stored, how records are linked, and which relationships
are enforced by keys.

### Why are customers, garments, and transactions in separate tables?

They represent different kinds of data. A customer's contact details or a
garment's description can be stored once and referenced by multiple
transactions. This avoids copying those details into every reservation,
rental, or sale.

### What is the purpose of primary and foreign keys?

A primary key identifies one row uniquely. A foreign key links a row to a
related row in another table and helps prevent references to records that do
not exist.

### Why is `order_materials_used` a separate entity?

A tailoring order can use several material items, and one material item can
be used in several orders. The separate table represents this many-to-many
relationship and stores the quantity used for each pairing.

### Why can a rental exist without a reservation?

The `reservation_id` in `rentals` is optional in the schema. This allows a
rental to be recorded even when it did not originate from a reservation.

### Why can a sale link to different types of items?

The sales table has optional references for a garment, material, rental, or
tailoring order, while `sale_type` identifies the kind of transaction. These
references are nullable because not every sale involves every type of
business record.

### Does the ERD include every system process?

No. The ERD focuses on data entities and relationships. User workflows,
validation rules, status transitions, calculations, and integrations with
SMS or email services are application processes and are not fully represented
by this diagram.

### Does every table have a relationship in the ERD?

No. Some tables have no declared foreign key in the schema. For example,
`password_resets.email` is not constrained to a user record, and notification
logs use generic reference fields rather than foreign keys to individual
business tables. The ERD avoids drawing relationships that the database does
not enforce.

## 7. Scope and interpretation notes

- The ERD documents the database structure, not the complete application
  architecture or data-flow process.
- Payment methods are recorded in the database, but this ERD does not imply
  an external payment gateway.
- Notification log references are generic and do not enforce referential
  integrity to a particular business table.
- The schema includes a `sessions` table, but its presence in the schema alone
  does not establish that every PHP session is stored in that table.
- If the paper describes normalization, verify the actual design against the
  required normal form and institutional guidelines; the presence of an ERD
  alone is not proof of a particular normalization level.
- Add citations to the paper's sources on relational database design and ER
  modeling as required by the institution. The project schema is the source
  for the system-specific details in this explanation.
