-- Marketplace schema repair / seed script
-- Run this on the live AkkuApps database if marketplace master creation or inserts still fail.

USE akkuapps_maui;

-- Keep AUTO_INCREMENT pointers in sync with existing imported data.
SET @next_brand_id = (SELECT COALESCE(MAX(brand_id), 0) + 1 FROM cs_brands);
SET @sql = CONCAT('ALTER TABLE cs_brands AUTO_INCREMENT = ', @next_brand_id);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @next_category_id = (SELECT COALESCE(MAX(category_id), 0) + 1 FROM cs_categories);
SET @sql = CONCAT('ALTER TABLE cs_categories AUTO_INCREMENT = ', @next_category_id);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @next_customer_id = (SELECT COALESCE(MAX(customer_id), 0) + 1 FROM cs_customers);
SET @sql = CONCAT('ALTER TABLE cs_customers AUTO_INCREMENT = ', @next_customer_id);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @next_cart_id = (SELECT COALESCE(MAX(cart_id), 0) + 1 FROM cs_carts);
SET @sql = CONCAT('ALTER TABLE cs_carts AUTO_INCREMENT = ', @next_cart_id);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @next_cart_item_id = (SELECT COALESCE(MAX(item_id), 0) + 1 FROM cs_cart_items);
SET @sql = CONCAT('ALTER TABLE cs_cart_items AUTO_INCREMENT = ', @next_cart_item_id);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @next_product_id = (SELECT COALESCE(MAX(product_id), 0) + 1 FROM cs_products);
SET @sql = CONCAT('ALTER TABLE cs_products AUTO_INCREMENT = ', @next_product_id);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @next_image_id = (SELECT COALESCE(MAX(image_id), 0) + 1 FROM cs_product_images);
SET @sql = CONCAT('ALTER TABLE cs_product_images AUTO_INCREMENT = ', @next_image_id);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @next_movement_id = (SELECT COALESCE(MAX(movement_id), 0) + 1 FROM cs_inventory_movements);
SET @sql = CONCAT('ALTER TABLE cs_inventory_movements AUTO_INCREMENT = ', @next_movement_id);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @next_invoice_id = (SELECT COALESCE(MAX(invoice_id), 0) + 1 FROM cs_invoices);
SET @sql = CONCAT('ALTER TABLE cs_invoices AUTO_INCREMENT = ', @next_invoice_id);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @next_invoice_item_id = (SELECT COALESCE(MAX(item_id), 0) + 1 FROM cs_invoice_items);
SET @sql = CONCAT('ALTER TABLE cs_invoice_items AUTO_INCREMENT = ', @next_invoice_item_id);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @next_payment_id = (SELECT COALESCE(MAX(payment_id), 0) + 1 FROM cs_payments);
SET @sql = CONCAT('ALTER TABLE cs_payments AUTO_INCREMENT = ', @next_payment_id);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @next_ticket_id = (SELECT COALESCE(MAX(ticket_id), 0) + 1 FROM cs_service_tickets);
SET @sql = CONCAT('ALTER TABLE cs_service_tickets AUTO_INCREMENT = ', @next_ticket_id);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Optional master seed: only inserts records if they do not already exist.
INSERT INTO cs_brands (brand_id, name, slug, description, website, is_active, created_at, updated_at)
SELECT 1001, 'HP', 'hp', 'Hewlett-Packard laptops and desktops', 'https://www.hp.com/in-en/home.html', 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM cs_brands WHERE slug = 'hp');

INSERT INTO cs_brands (brand_id, name, slug, description, website, is_active, created_at, updated_at)
SELECT 1002, 'Dell', 'dell', 'Dell Technologies systems and accessories', 'https://www.dell.com/en-in', 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM cs_brands WHERE slug = 'dell');

INSERT INTO cs_brands (brand_id, name, slug, description, website, is_active, created_at, updated_at)
SELECT 1003, 'Lenovo', 'lenovo', 'Lenovo laptops, desktops and accessories', 'https://www.lenovo.com/in/en/', 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM cs_brands WHERE slug = 'lenovo');

INSERT INTO cs_brands (brand_id, name, slug, description, website, is_active, created_at, updated_at)
SELECT 1004, 'Asus', 'asus', 'ASUS laptops, components and gaming systems', 'https://www.asus.com/in/', 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM cs_brands WHERE slug = 'asus');

INSERT INTO cs_brands (brand_id, name, slug, description, website, is_active, created_at, updated_at)
SELECT 1005, 'Acer', 'acer', 'Acer laptops, desktops and displays', 'https://www.acer.com/in-en', 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM cs_brands WHERE slug = 'acer');

INSERT INTO cs_brands (brand_id, name, slug, description, website, is_active, created_at, updated_at)
SELECT 1006, 'MSI', 'msi', 'MSI gaming laptops and components', 'https://in.msi.com/', 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM cs_brands WHERE slug = 'msi');

INSERT INTO cs_brands (brand_id, name, slug, description, website, is_active, created_at, updated_at)
SELECT 1007, 'Custom Build', 'custom-build', 'Custom assembled PCs', '', 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM cs_brands WHERE slug = 'custom-build');

INSERT INTO cs_categories (category_id, parent_id, name, slug, description, sort_order, is_active, created_at, updated_at)
SELECT 2001, NULL, 'Laptops', 'laptops', 'Notebooks and ultrabooks', 1, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM cs_categories WHERE slug = 'laptops');

INSERT INTO cs_categories (category_id, parent_id, name, slug, description, sort_order, is_active, created_at, updated_at)
SELECT 2002, NULL, 'Desktops', 'desktops', 'Desktop computers and towers', 2, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM cs_categories WHERE slug = 'desktops');

INSERT INTO cs_categories (category_id, parent_id, name, slug, description, sort_order, is_active, created_at, updated_at)
SELECT 2003, NULL, 'Components', 'components', 'PC components and parts', 3, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM cs_categories WHERE slug = 'components');

INSERT INTO cs_categories (category_id, parent_id, name, slug, description, sort_order, is_active, created_at, updated_at)
SELECT 2004, NULL, 'Monitors', 'monitors', 'Display screens', 4, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM cs_categories WHERE slug = 'monitors');

INSERT INTO cs_categories (category_id, parent_id, name, slug, description, sort_order, is_active, created_at, updated_at)
SELECT 2005, NULL, 'Accessories', 'accessories', 'Keyboards, mice, bags and accessories', 5, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM cs_categories WHERE slug = 'accessories');

INSERT INTO cs_categories (category_id, parent_id, name, slug, description, sort_order, is_active, created_at, updated_at)
SELECT 2006, (SELECT category_id FROM cs_categories WHERE slug = 'components' LIMIT 1), 'Processors', 'processors', 'CPU processors', 1, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM cs_categories WHERE slug = 'processors');

INSERT INTO cs_categories (category_id, parent_id, name, slug, description, sort_order, is_active, created_at, updated_at)
SELECT 2007, (SELECT category_id FROM cs_categories WHERE slug = 'components' LIMIT 1), 'Motherboards', 'motherboards', 'Mainboards', 2, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM cs_categories WHERE slug = 'motherboards');

INSERT INTO cs_categories (category_id, parent_id, name, slug, description, sort_order, is_active, created_at, updated_at)
SELECT 2008, (SELECT category_id FROM cs_categories WHERE slug = 'components' LIMIT 1), 'RAM', 'ram', 'Memory modules', 3, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM cs_categories WHERE slug = 'ram');

INSERT INTO cs_categories (category_id, parent_id, name, slug, description, sort_order, is_active, created_at, updated_at)
SELECT 2009, (SELECT category_id FROM cs_categories WHERE slug = 'components' LIMIT 1), 'Storage', 'storage', 'SSD and HDD drives', 4, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM cs_categories WHERE slug = 'storage');

INSERT INTO cs_categories (category_id, parent_id, name, slug, description, sort_order, is_active, created_at, updated_at)
SELECT 2010, (SELECT category_id FROM cs_categories WHERE slug = 'components' LIMIT 1), 'Graphics Cards', 'graphics-cards', 'GPU cards', 5, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM cs_categories WHERE slug = 'graphics-cards');
