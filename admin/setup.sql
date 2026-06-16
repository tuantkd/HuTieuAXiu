-- File nay khong con la schema chinh.
-- Nguon su that duy nhat cua database la file `database.sql` tai thu muc goc du an.
-- Neu cai moi: hay import `database.sql` trong phpMyAdmin.
-- File nay chi dung de cap nhat database cu theo huong tuong thich voi khu vuc admin.

USE so_ban_hang_a_xiu;

CREATE TABLE IF NOT EXISTS categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  slug VARCHAR(100) NOT NULL UNIQUE,
  sort_order INT DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO categories (name, slug, sort_order) VALUES
('Món ăn', 'mon-an', 1),
('Nước uống', 'nuoc-uong', 2)
ON DUPLICATE KEY UPDATE
  name = VALUES(name),
  sort_order = VALUES(sort_order);

ALTER TABLE users MODIFY role ENUM('admin', 'staff', 'nhanvien') NOT NULL DEFAULT 'staff';
UPDATE users SET role = 'staff' WHERE role = 'nhanvien';
ALTER TABLE users MODIFY role ENUM('admin', 'staff') NOT NULL DEFAULT 'staff';

SET @has_category_id := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'products'
      AND COLUMN_NAME = 'category_id'
);
SET @sql_category_id := IF(@has_category_id = 0, 'ALTER TABLE products ADD COLUMN category_id INT NULL AFTER id', 'SELECT 1');
PREPARE stmt_category_id FROM @sql_category_id;
EXECUTE stmt_category_id;
DEALLOCATE PREPARE stmt_category_id;

UPDATE products p
LEFT JOIN categories c ON c.id = p.category_id
SET p.category_id = CASE
    WHEN p.unit IN ('ly', 'chai', 'lon') THEN (SELECT id FROM categories WHERE slug = 'nuoc-uong' LIMIT 1)
    ELSE (SELECT id FROM categories WHERE slug = 'mon-an' LIMIT 1)
END
WHERE p.category_id IS NULL OR p.category_id = 0 OR c.id IS NULL;

SET @has_product_fk := (
    SELECT COUNT(*)
    FROM information_schema.REFERENTIAL_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND CONSTRAINT_NAME = 'products_ibfk_1'
);
SET @sql_product_fk := IF(@has_product_fk = 0, 'ALTER TABLE products ADD CONSTRAINT products_ibfk_1 FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE', 'SELECT 1');
PREPARE stmt_product_fk FROM @sql_product_fk;
EXECUTE stmt_product_fk;
DEALLOCATE PREPARE stmt_product_fk;

SET @has_user_id := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'orders'
      AND COLUMN_NAME = 'user_id'
);
SET @sql_user_id := IF(@has_user_id = 0, 'ALTER TABLE orders ADD COLUMN user_id INT NULL AFTER id', 'SELECT 1');
PREPARE stmt_user_id FROM @sql_user_id;
EXECUTE stmt_user_id;
DEALLOCATE PREPARE stmt_user_id;

ALTER TABLE orders MODIFY user_id INT NULL;

SET @has_order_code := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'orders'
      AND COLUMN_NAME = 'order_code'
);
SET @sql_order_code := IF(@has_order_code = 0, 'ALTER TABLE orders ADD COLUMN order_code VARCHAR(30) NULL AFTER user_id', 'SELECT 1');
PREPARE stmt_order_code FROM @sql_order_code;
EXECUTE stmt_order_code;
DEALLOCATE PREPARE stmt_order_code;

SET @has_order_note := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'orders'
      AND COLUMN_NAME = 'note'
);
SET @sql_order_note := IF(@has_order_note = 0, 'ALTER TABLE orders ADD COLUMN note VARCHAR(255) NULL AFTER total_amount', 'SELECT 1');
PREPARE stmt_order_note FROM @sql_order_note;
EXECUTE stmt_order_note;
DEALLOCATE PREPARE stmt_order_note;

UPDATE orders
SET order_code = CONCAT('AX', LPAD(id, 6, '0'))
WHERE order_code IS NULL OR order_code = '';

ALTER TABLE orders MODIFY order_code VARCHAR(30) NOT NULL;

UPDATE orders o
LEFT JOIN users u ON u.id = o.user_id
SET o.user_id = NULL
WHERE o.user_id IS NOT NULL AND u.id IS NULL;

SET @has_order_code_index := (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'orders'
      AND INDEX_NAME = 'order_code'
);
SET @sql_order_code_index := IF(@has_order_code_index = 0, 'ALTER TABLE orders ADD UNIQUE KEY order_code (order_code)', 'SELECT 1');
PREPARE stmt_order_code_index FROM @sql_order_code_index;
EXECUTE stmt_order_code_index;
DEALLOCATE PREPARE stmt_order_code_index;

SET @has_order_index := (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'orders'
      AND INDEX_NAME = 'idx_orders_user_id'
);
SET @sql_order_index := IF(@has_order_index = 0, 'ALTER TABLE orders ADD INDEX idx_orders_user_id (user_id)', 'SELECT 1');
PREPARE stmt_order_index FROM @sql_order_index;
EXECUTE stmt_order_index;
DEALLOCATE PREPARE stmt_order_index;

SET @has_order_fk := (
    SELECT COUNT(*)
    FROM information_schema.REFERENTIAL_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND CONSTRAINT_NAME = 'fk_orders_user'
);
SET @sql_drop_order_fk := IF(@has_order_fk > 0, 'ALTER TABLE orders DROP FOREIGN KEY fk_orders_user', 'SELECT 1');
PREPARE stmt_drop_order_fk FROM @sql_drop_order_fk;
EXECUTE stmt_drop_order_fk;
DEALLOCATE PREPARE stmt_drop_order_fk;

ALTER TABLE orders ADD CONSTRAINT fk_orders_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE;

SET @has_price := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'order_items'
      AND COLUMN_NAME = 'price'
);
SET @sql_price := IF(@has_price = 0, 'ALTER TABLE order_items ADD COLUMN price INT NOT NULL DEFAULT 0 AFTER product_name', 'SELECT 1');
PREPARE stmt_price FROM @sql_price;
EXECUTE stmt_price;
DEALLOCATE PREPARE stmt_price;

SET @has_subtotal := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'order_items'
      AND COLUMN_NAME = 'subtotal'
);
SET @sql_subtotal := IF(@has_subtotal = 0, 'ALTER TABLE order_items ADD COLUMN subtotal INT NOT NULL DEFAULT 0 AFTER quantity', 'SELECT 1');
PREPARE stmt_subtotal FROM @sql_subtotal;
EXECUTE stmt_subtotal;
DEALLOCATE PREPARE stmt_subtotal;

SET @has_unit_price := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'order_items'
      AND COLUMN_NAME = 'unit_price'
);
SET @sql_copy_price := IF(@has_unit_price > 0, 'UPDATE order_items SET price = IF(price = 0, unit_price, price)', 'SELECT 1');
PREPARE stmt_copy_price FROM @sql_copy_price;
EXECUTE stmt_copy_price;
DEALLOCATE PREPARE stmt_copy_price;

SET @has_total_price := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'order_items'
      AND COLUMN_NAME = 'total_price'
);
SET @sql_copy_subtotal := IF(@has_total_price > 0, 'UPDATE order_items SET subtotal = IF(subtotal = 0, total_price, subtotal)', 'SELECT 1');
PREPARE stmt_copy_subtotal FROM @sql_copy_subtotal;
EXECUTE stmt_copy_subtotal;
DEALLOCATE PREPARE stmt_copy_subtotal;

SET @has_shop_name := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'settings'
      AND COLUMN_NAME = 'shop_name'
);
SET @sql_shop_name := IF(@has_shop_name = 0, 'ALTER TABLE settings ADD COLUMN shop_name VARCHAR(150) NULL AFTER id', 'SELECT 1');
PREPARE stmt_shop_name FROM @sql_shop_name;
EXECUTE stmt_shop_name;
DEALLOCATE PREPARE stmt_shop_name;

SET @has_phone := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'settings'
      AND COLUMN_NAME = 'phone'
);
SET @sql_phone := IF(@has_phone = 0, 'ALTER TABLE settings ADD COLUMN phone VARCHAR(50) NULL AFTER shop_name', 'SELECT 1');
PREPARE stmt_phone FROM @sql_phone;
EXECUTE stmt_phone;
DEALLOCATE PREPARE stmt_phone;

SET @has_address := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'settings'
      AND COLUMN_NAME = 'address'
);
SET @sql_address := IF(@has_address = 0, 'ALTER TABLE settings ADD COLUMN address VARCHAR(255) NULL AFTER phone', 'SELECT 1');
PREPARE stmt_address FROM @sql_address;
EXECUTE stmt_address;
DEALLOCATE PREPARE stmt_address;

SET @has_open_hours := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'settings'
      AND COLUMN_NAME = 'open_hours'
);
SET @sql_open_hours := IF(@has_open_hours = 0, 'ALTER TABLE settings ADD COLUMN open_hours VARCHAR(100) NULL AFTER address', 'SELECT 1');
PREPARE stmt_open_hours FROM @sql_open_hours;
EXECUTE stmt_open_hours;
DEALLOCATE PREPARE stmt_open_hours;

SET @has_logo_url := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'settings'
      AND COLUMN_NAME = 'logo_url'
);
SET @sql_logo_url := IF(@has_logo_url = 0, 'ALTER TABLE settings ADD COLUMN logo_url VARCHAR(255) NULL AFTER open_hours', 'SELECT 1');
PREPARE stmt_logo_url FROM @sql_logo_url;
EXECUTE stmt_logo_url;
DEALLOCATE PREPARE stmt_logo_url;

SET @has_store_name := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'settings'
      AND COLUMN_NAME = 'store_name'
);
SET @sql_copy_shop_name := IF(@has_store_name > 0, 'UPDATE settings SET shop_name = IFNULL(shop_name, store_name)', 'SELECT 1');
PREPARE stmt_copy_shop_name FROM @sql_copy_shop_name;
EXECUTE stmt_copy_shop_name;
DEALLOCATE PREPARE stmt_copy_shop_name;

SET @has_hotline := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'settings'
      AND COLUMN_NAME = 'hotline'
);
SET @sql_copy_phone := IF(@has_hotline > 0, 'UPDATE settings SET phone = IFNULL(phone, hotline)', 'SELECT 1');
PREPARE stmt_copy_phone FROM @sql_copy_phone;
EXECUTE stmt_copy_phone;
DEALLOCATE PREPARE stmt_copy_phone;

SET @has_store_address := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'settings'
      AND COLUMN_NAME = 'store_address'
);
SET @sql_copy_address := IF(@has_store_address > 0, 'UPDATE settings SET address = IFNULL(address, store_address)', 'SELECT 1');
PREPARE stmt_copy_address FROM @sql_copy_address;
EXECUTE stmt_copy_address;
DEALLOCATE PREPARE stmt_copy_address;

UPDATE settings
SET shop_name = IFNULL(shop_name, 'Quán Hủ Tiếu A Xíu'),
    phone = IFNULL(phone, '0912 345 678'),
    address = IFNULL(address, '123 Đường Ẩm Thực, Quận 1, TP.HCM'),
    open_hours = IFNULL(open_hours, '06:00 - 21:30');

INSERT INTO users (username, password_hash, full_name, role)
VALUES
  ('admin', '$2y$10$6AnW3mMkbLG642BXxdSmGuqgVk8eOG4lKexZwY5F/1WnoAlqlun6q', 'Quản trị viên', 'admin'),
  ('nhanvien', '$2y$10$6AnW3mMkbLG642BXxdSmGuqgVk8eOG4lKexZwY5F/1WnoAlqlun6q', 'Nhân viên bán hàng', 'staff')
ON DUPLICATE KEY UPDATE
  password_hash = VALUES(password_hash),
  full_name = VALUES(full_name),
  role = VALUES(role);
