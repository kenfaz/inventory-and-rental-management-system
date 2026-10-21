-- ============================================================
-- Desire's Tailor Shop — Sample Sales and Income Transactions
-- Run this AFTER tailorshop.sql and seed.sql
-- Safe to run more than once; demo transactions are identified by notes.
-- ============================================================

USE tailorshop;

-- Add representative transactions for each report category. The date offsets
-- are capped at the start of the current month so the default monthly report
-- always includes them.
INSERT INTO sales_records
  (customer_id, processed_by, garment_id, rental_id, order_id,
   sale_type, amount, payment_method, notes, sale_date)
SELECT
  c.customer_id,
  u.user_id,
  CASE WHEN demo.sale_type = 'Garment Sale' THEN g.garment_id ELSE NULL END,
  r.rental_id,
  o.order_id,
  demo.sale_type,
  demo.amount,
  demo.payment_method,
  CONCAT('[DEMO-SEED] ', demo.demo_key),
  DATE_ADD(
    DATE_SUB(CURDATE(), INTERVAL LEAST(DAY(CURDATE()) - 1, demo.days_ago) DAY),
    INTERVAL 12 HOUR
  )
FROM (
  SELECT 'garment-ana' AS demo_key, '09171234567' AS customer_mobile,
         'BT-001' AS garment_code, NULL AS rental_garment_code,
         NULL AS order_name, 'Garment Sale' AS sale_type,
         5000.00 AS amount, 'Cash' AS payment_method, 0 AS days_ago
  UNION ALL
  SELECT 'garment-pedro', '09281234568', 'DG-001', NULL, NULL,
         'Garment Sale', 15000.00, 'GCash', 3
  UNION ALL
  SELECT 'garment-maria', '09351234569', 'PD-001', NULL, NULL,
         'Garment Sale', 10000.00, 'Card', 14
  UNION ALL
  SELECT 'rental-maria', '09351234569', NULL, 'WG-001', NULL,
         'Rental Fee', 2500.00, 'Cash', 0
  UNION ALL
  SELECT 'rental-jose', '09461234570', NULL, 'PD-002', NULL,
         'Rental Fee', 1500.00, 'GCash', 5
  UNION ALL
  SELECT 'tailoring-rosa', '09181234571', NULL, NULL, 'Rosa''s Wedding Gown',
         'Tailoring Payment', 7500.00, 'Cash', 0
  UNION ALL
  SELECT 'tailoring-ana', '09171234567', NULL, NULL, 'Ana''s Cocktail Dress',
         'Tailoring Payment', 2500.00, 'GCash', 8
) AS demo
JOIN customers c
  ON c.mobile_number = demo.customer_mobile
JOIN users u
  ON u.email = 'staff@tailorshop.com'
LEFT JOIN garment_items g
  ON g.garment_code = COALESCE(demo.garment_code, demo.rental_garment_code)
LEFT JOIN rentals r
  ON r.customer_id = c.customer_id
 AND r.garment_id = g.garment_id
 AND demo.sale_type = 'Rental Fee'
LEFT JOIN tailoring_orders o
  ON o.customer_id = c.customer_id
 AND o.order_name = demo.order_name
WHERE NOT EXISTS (
  SELECT 1
  FROM sales_records existing
  WHERE existing.notes = CONCAT('[DEMO-SEED] ', demo.demo_key)
);

-- Keep the inventory status consistent with the seeded garment sales.
UPDATE garment_items g
JOIN sales_records s ON s.garment_id = g.garment_id
SET g.availability_status = 'Sold'
WHERE s.sale_type = 'Garment Sale'
  AND s.notes LIKE '[DEMO-SEED] garment-%';

-- Reports read from income_records, so create one matching income entry for
-- each demo sale while preserving its transaction date.
INSERT INTO income_records (sale_id, income_type, amount, payment_method, date_recorded)
SELECT s.sale_id, s.sale_type, s.amount, s.payment_method, s.sale_date
FROM sales_records s
WHERE s.notes LIKE '[DEMO-SEED] %'
  AND NOT EXISTS (
    SELECT 1
    FROM income_records existing
    WHERE existing.sale_id = s.sale_id
  );
