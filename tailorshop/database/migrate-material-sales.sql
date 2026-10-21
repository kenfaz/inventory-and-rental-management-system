-- Run once for an existing Tailor Shop database.
USE tailorshop;

ALTER TABLE material_items
  ADD COLUMN sale_price DECIMAL(10,2) NULL AFTER quantity;

ALTER TABLE sales_records
  ADD COLUMN material_id INT NULL AFTER garment_id,
  MODIFY COLUMN sale_type ENUM(
    'Garment Sale',
    'Material Sale',
    'Rental Fee',
    'Tailoring Payment'
  ) NOT NULL,
  ADD CONSTRAINT fk_sale_material
    FOREIGN KEY (material_id)
    REFERENCES material_items (item_id)
    ON UPDATE CASCADE ON DELETE SET NULL;

ALTER TABLE income_records
  MODIFY COLUMN income_type ENUM(
    'Garment Sale',
    'Material Sale',
    'Rental Fee',
    'Tailoring Payment'
  ) NOT NULL;
