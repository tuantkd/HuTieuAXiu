CREATE DATABASE IF NOT EXISTS so_ban_hang_a_xiu CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE so_ban_hang_a_xiu;

DROP TABLE IF EXISTS order_items;
DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS transactions;
DROP TABLE IF EXISTS settings;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS users;

CREATE TABLE categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  slug VARCHAR(100) NOT NULL UNIQUE,
  sort_order INT DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE products (
  id INT AUTO_INCREMENT PRIMARY KEY,
  category_id INT NOT NULL,
  name VARCHAR(150) NOT NULL,
  price INT NOT NULL DEFAULT 0,
  unit VARCHAR(30) NOT NULL DEFAULT 'phần',
  image_url VARCHAR(255) DEFAULT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(80) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  full_name VARCHAR(120) NOT NULL,
  role ENUM('admin', 'staff') NOT NULL DEFAULT 'staff',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE orders (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL,
  order_code VARCHAR(30) NOT NULL UNIQUE,
  order_type ENUM('dine_in', 'takeaway') NOT NULL,
  total_amount INT NOT NULL DEFAULT 0,
  note VARCHAR(255) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_orders_user_id (user_id),
  CONSTRAINT fk_orders_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE order_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT NOT NULL,
  product_id INT NOT NULL,
  product_name VARCHAR(150) NOT NULL,
  price INT NOT NULL,
  quantity INT NOT NULL,
  subtotal INT NOT NULL,
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE transactions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  type ENUM('income', 'expense') NOT NULL,
  category VARCHAR(100) NOT NULL,
  amount INT NOT NULL DEFAULT 0,
  note VARCHAR(255) DEFAULT NULL,
  transaction_date DATE NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE settings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  shop_name VARCHAR(150) NOT NULL,
  phone VARCHAR(50) NOT NULL,
  address VARCHAR(255) NOT NULL,
  open_hours VARCHAR(100) NOT NULL,
  logo_url VARCHAR(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO categories (name, slug, sort_order) VALUES
('Món ăn', 'mon-an', 1),
('Nước uống', 'nuoc-uong', 2);

INSERT INTO products (category_id, name, price, unit, image_url, is_active) VALUES
(1, 'Hủ tiếu sườn', 40000, 'tô', 'assets/img/hu-tieu.svg', 1),
(1, 'Cháo thịt', 30000, 'phần', 'assets/img/chao.svg', 1),
(2, 'Trà đá', 5000, 'ly', 'assets/img/tra-da.svg', 1),
(2, 'Nước suối', 10000, 'chai', 'assets/img/nuoc-suoi.svg', 1),
(2, 'Nước ngọt', 15000, 'lon', 'assets/img/nuoc-ngot.svg', 1);

INSERT INTO users (username, password_hash, full_name, role) VALUES
('admin', '$2y$10$6AnW3mMkbLG642BXxdSmGuqgVk8eOG4lKexZwY5F/1WnoAlqlun6q', 'Quản trị viên', 'admin'),
('nhanvien', '$2y$10$6AnW3mMkbLG642BXxdSmGuqgVk8eOG4lKexZwY5F/1WnoAlqlun6q', 'Nhân viên bán hàng', 'staff');

INSERT INTO settings (shop_name, phone, address, open_hours, logo_url) VALUES
('Quán Hủ Tiếu A Xíu', '0912 345 678', '123 Đường Ẩm Thực, Quận 1, TP.HCM', '06:00 - 21:30', 'assets/img/logo-placeholder.png');

INSERT INTO transactions (type, category, amount, note, transaction_date) VALUES
('income', 'Bán hàng', 400000, 'Doanh thu bán trực tiếp', CURDATE()),
('expense', 'Nguyên liệu', 120000, 'Mua rau và thịt', CURDATE()),
('expense', 'Tiền điện nước', 50000, 'Chi phí vận hành', CURDATE());

INSERT INTO orders (user_id, order_code, order_type, total_amount, note, created_at) VALUES
(1, 'DH0001', 'dine_in', 80000, 'Khách ăn tại quán', NOW() - INTERVAL 1 DAY),
(2, 'DH0002', 'takeaway', 55000, 'Khách mang đi', NOW() - INTERVAL 2 HOUR),
(NULL, 'DH0003', 'dine_in', 65000, 'Gọi thêm trà đá', NOW() - INTERVAL 3 HOUR);

INSERT INTO order_items (order_id, product_id, product_name, price, quantity, subtotal) VALUES
(1, 1, 'Hủ tiếu sườn', 40000, 1, 40000),
(1, 3, 'Trà đá', 5000, 8, 40000),
(2, 4, 'Nước suối', 10000, 1, 10000),
(2, 5, 'Nước ngọt', 15000, 1, 15000),
(2, 2, 'Cháo thịt', 30000, 1, 30000),
(3, 1, 'Hủ tiếu sườn', 40000, 1, 40000),
(3, 3, 'Trà đá', 5000, 5, 25000);
