-- ============================================================
-- Desire's Tailor Shop — Seed Data
-- Run this AFTER tailorshop.sql
-- ============================================================

USE tailorshop;

-- ============================================================
-- DEFAULT USER ACCOUNTS
-- ============================================================

-- Admin account
-- Email   : admin@tailorshop.com
-- Password: Admin@1234
INSERT INTO users (full_name, email, password, role, status) VALUES
(
  'Shop Admin',
  'admin@tailorshop.com',
  '$2y$10$Y5A.CZ5GNQKbZm/5Sp0N0OsZMxE9c1PmVFfV3e0LMBNnbKEXsn3dC',
  'admin',
  'active'
);

-- Sample staff account
-- Email   : staff@tailorshop.com
-- Password: Staff@1234
INSERT INTO users (full_name, email, password, role, status) VALUES
(
  'Maria Reyes',
  'staff@tailorshop.com',
  '$2y$10$9K1V6KlFnkGAGPRPW9HvF.oWIr7LHmBqHlvxIz4fjc3MxMTFCM8XS',
  'staff',
  'active'
);

-- ============================================================
-- MATERIAL CATEGORIES
-- ============================================================
INSERT INTO material_categories (category_name, default_unit, description) VALUES
('Fabrics',                  'meter',  'All types of fabric material used in garment making'),
('Threads and Yarns',        'spool',  'Sewing threads, embroidery floss, and yarn'),
('Lining and Interfacing',   'meter',  'Lining fabric and fusible or sew-in interfacing'),
('Notions and Fasteners',    'piece',  'Buttons, zippers, hooks, snaps, and velcro'),
('Trimmings and Embellishments', 'meter', 'Ribbons, lace trim, beads, sequins, and rhinestones'),
('Elastic and Tapes',        'meter',  'Elastic bands, bias tape, and seam binding'),
('Consumables',              'piece',  'Needles, pins, chalk, seam rippers, and scissors');

-- ============================================================
-- GARMENT CATEGORIES
-- ============================================================
INSERT INTO garment_categories (category_name, description) VALUES
('Wedding Gowns',           'Bridal gowns and wedding veils'),
('Debut Gowns',             'Ball gowns and princess gowns for debut celebrations'),
('Prom and Formal Dresses', 'Prom dresses and evening gowns'),
('Casual and Cocktail Dresses', 'Midi dresses and cocktail dresses'),
('Barong and Suits',        'Barong Tagalog, suits, and blazers'),
('Costumes',                'Theme costumes and cultural attire'),
('Children\'s Formal Wear', 'Flower girl dresses and ring bearer suits'),
('Custom-made Garments',    'Items made to order for clients');

-- ============================================================
-- SAMPLE MATERIAL ITEMS
-- ============================================================
INSERT INTO material_items (category_id, item_name, brand, quantity, unit, low_stock_threshold) VALUES
(1, 'White Satin Fabric',        'Generic',  50.00, 'meter', 10.00),
(1, 'Ivory Lace Fabric',         'Generic',  30.00, 'meter', 8.00),
(1, 'Royal Blue Chiffon',        'Generic',  25.00, 'meter', 5.00),
(1, 'Black Velvet Fabric',       'Generic',  20.00, 'meter', 5.00),
(2, 'White Polyester Thread',    'Coats',   100.00, 'spool', 10.00),
(2, 'Black Polyester Thread',    'Coats',    80.00, 'spool', 10.00),
(2, 'Gold Embroidery Floss',     'DMC',      50.00, 'spool', 5.00),
(3, 'White Lining Fabric',       'Generic',  40.00, 'meter', 8.00),
(3, 'Fusible Interfacing',       'Pellon',   20.00, 'meter', 5.00),
(4, 'Invisible Zipper 20cm',     'YKK',     100.00, 'piece', 15.00),
(4, 'Pearl Buttons Set',         'Generic',  200.00,'piece', 30.00),
(4, 'Metal Hook and Eye Set',    'Generic',  150.00,'piece', 20.00),
(5, 'White Lace Trim',           'Generic',  30.00, 'meter', 5.00),
(5, 'Gold Ribbon 1.5cm',         'Generic',  25.00, 'meter', 5.00),
(6, 'Elastic Band 2cm',          'Generic',  20.00, 'meter', 5.00),
(6, 'White Bias Tape',           'Generic',  15.00, 'meter', 3.00),
(7, 'Sewing Needles Pack',       'Singer',   30.00, 'pack',  5.00),
(7, 'Tailor\'s Chalk White',     'Generic',  20.00, 'piece', 5.00),
(7, 'Glass Head Pins Box',       'Generic',  15.00, 'box',   3.00);

-- ============================================================
-- SAMPLE GARMENT ITEMS
-- ============================================================
INSERT INTO garment_items
  (category_id, garment_name, garment_code, size, color,
   condition_status, availability_status, rental_price, sale_price) VALUES
(1, 'Classic White Ball Gown',    'WG-001', 'S',   'White',     'Good', 'Available', 2500.00, 18000.00),
(1, 'Romantic Lace Wedding Gown', 'WG-002', 'M',   'Ivory',     'Good', 'Available', 3000.00, 22000.00),
(1, 'Modern Sleek Bridal Gown',   'WG-003', 'L',   'White',     'Good', 'Available', 2800.00, 20000.00),
(2, 'Princess Pink Debut Gown',   'DG-001', 'S',   'Pink',      'Good', 'Available', 2000.00, 15000.00),
(2, 'Royal Blue Ball Gown',       'DG-002', 'M',   'Royal Blue','Good', 'Available', 2200.00, 16000.00),
(3, 'Elegant Red Evening Gown',   'PD-001', 'S',   'Red',       'Good', 'Available', 1500.00, 10000.00),
(3, 'Midnight Blue Prom Dress',   'PD-002', 'M',   'Navy Blue', 'Good', 'Available', 1500.00, 10000.00),
(4, 'Black Cocktail Dress',       'CD-001', 'S',   'Black',     'Good', 'Available', 1000.00,  7000.00),
(5, 'Traditional Barong Tagalog', 'BT-001', 'L',   'White',     'Good', 'Available', 800.00,   5000.00),
(7, 'Flower Girl Dress Pink',     'CF-001', '4T',  'Pink',      'Good', 'Available', 600.00,   3500.00);

-- ============================================================
-- SAMPLE CUSTOMERS
-- ============================================================
INSERT INTO customers (full_name, mobile_number, address) VALUES
('Ana Santos',    '09171234567', 'Blk 1 Lot 2 Sampaguita St., Calamba City'),
('Pedro Reyes',   '09281234568', '123 Rizal Ave., Los Baños, Laguna'),
('Maria Cruz',    '09351234569', '456 Aguinaldo St., San Pablo City'),
('Jose Lim',      '09461234570', '789 Mabini St., Santa Rosa, Laguna'),
('Rosa Garcia',   '09181234571', '321 Bonifacio St., Cabuyao, Laguna');

-- ============================================================
-- SAMPLE RESERVATIONS
-- ============================================================
INSERT INTO reservations
  (customer_id, garment_id, processed_by, pickup_date, return_date, status) VALUES
(1, 4, 2, DATE_ADD(CURDATE(), INTERVAL 5 DAY),  DATE_ADD(CURDATE(), INTERVAL 8 DAY),  'Confirmed'),
(2, 6, 2, DATE_ADD(CURDATE(), INTERVAL 10 DAY), DATE_ADD(CURDATE(), INTERVAL 12 DAY), 'Confirmed');

-- ============================================================
-- SAMPLE RENTALS
-- ============================================================
INSERT INTO rentals
  (customer_id, garment_id, processed_by, rental_date,
   expected_return_date, rental_fee, deposit_amount,
   penalty_rate_per_day, payment_method, status) VALUES
(3, 1, 2, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 3 DAY), 2500.00, 1000.00, 200.00, 'Cash',  'Active'),
(4, 7, 2, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 5 DAY), 1500.00,  500.00, 150.00, 'GCash', 'Active');

-- ============================================================
-- SAMPLE TAILORING ORDERS
-- ============================================================
INSERT INTO tailoring_orders
  (customer_id, assigned_staff_id, order_name, garment_description,
   price, down_payment, payment_method, status, due_date) VALUES
(5, 2, 'Rosa\'s Wedding Gown',
 'Custom-made A-line wedding gown with lace overlay and cathedral train',
 15000.00, 7500.00, 'Cash', 'In Progress',
 DATE_ADD(CURDATE(), INTERVAL 14 DAY)),
(1, 2, 'Ana\'s Cocktail Dress',
 'Knee-length black cocktail dress with beaded neckline',
 5000.00, 2500.00, 'GCash', 'Pending',
 DATE_ADD(CURDATE(), INTERVAL 7 DAY));

-- ============================================================
-- SAMPLE ORDER MEASUREMENTS
-- ============================================================
INSERT INTO order_measurements
  (order_id, bust, waist, hips, length, shoulder, sleeve) VALUES
(1, 36.00, 27.00, 38.00, 58.00, 14.00, 24.00),
(2, 34.00, 26.00, 36.00, 42.00, 13.50, 22.00);