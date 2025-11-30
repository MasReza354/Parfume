-- =============================================
-- Database: arde_lux
-- Aplikasi E-Commerce Parfum Premium
-- =============================================

-- Create Database
CREATE DATABASE IF NOT EXISTS arde_lux CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE arde_lux;

-- =============================================
-- Table: users
-- Menyimpan data pengguna (customer, admin, partnership, superadmin)
-- =============================================
CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  email VARCHAR(100) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  full_name VARCHAR(100) NOT NULL,
  phone VARCHAR(20) DEFAULT NULL,
  address TEXT DEFAULT NULL,
  role ENUM('customer', 'admin', 'partnership', 'superadmin') DEFAULT 'customer',
  status ENUM('active', 'inactive', 'banned') DEFAULT 'active',
  profile_image VARCHAR(255) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_username (username),
  INDEX idx_email (email),
  INDEX idx_role (role),
  INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- Table: products
-- Menyimpan data produk parfum
-- =============================================
CREATE TABLE IF NOT EXISTS products (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(200) NOT NULL,
  type VARCHAR(100) NOT NULL COMMENT 'Eau de Parfum, Eau de Toilette, Eau de Cologne',
  scent VARCHAR(100) NOT NULL COMMENT 'Floral, Citrus, Woody, Marine, Gourmand, Spicy',
  price DECIMAL(10, 2) NOT NULL,
  description TEXT DEFAULT NULL,
  image VARCHAR(255) DEFAULT 'images/perfume.png',
  stock INT DEFAULT 0,
  status ENUM('active', 'inactive') DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_type (type),
  INDEX idx_scent (scent),
  INDEX idx_status (status),
  INDEX idx_price (price)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- Table: favorites
-- Menyimpan produk favorit pengguna
-- =============================================
CREATE TABLE IF NOT EXISTS favorites (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  product_id INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  UNIQUE KEY unique_favorite (user_id, product_id),
  INDEX idx_user_id (user_id),
  INDEX idx_product_id (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- Table: orders
-- Menyimpan data pesanan
-- =============================================
CREATE TABLE IF NOT EXISTS orders (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  order_number VARCHAR(50) NOT NULL UNIQUE,
  total_amount DECIMAL(10, 2) NOT NULL,
  shipping_fee DECIMAL(10, 2) DEFAULT 0,
  payment_method VARCHAR(50) NOT NULL COMMENT 'bank_transfer, cod, e-wallet',
  shipping_address TEXT NOT NULL,
  notes TEXT DEFAULT NULL,
  payment_status ENUM('pending', 'paid', 'failed', 'refunded') DEFAULT 'pending',
  order_status ENUM('pending', 'processing', 'shipped', 'delivered', 'cancelled') DEFAULT 'pending',
  payment_proof VARCHAR(255) DEFAULT NULL,
  tracking_number VARCHAR(100) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_user_id (user_id),
  INDEX idx_order_number (order_number),
  INDEX idx_payment_status (payment_status),
  INDEX idx_order_status (order_status),
  INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- Table: order_items
-- Menyimpan detail item pesanan
-- =============================================
CREATE TABLE IF NOT EXISTS order_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT NOT NULL,
  product_id INT NOT NULL,
  product_name VARCHAR(200) NOT NULL,
  product_price DECIMAL(10, 2) NOT NULL,
  quantity INT NOT NULL,
  subtotal DECIMAL(10, 2) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT,
  INDEX idx_order_id (order_id),
  INDEX idx_product_id (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- Insert Default Data
-- =============================================

-- Insert Super Admin (password: admin123)
INSERT INTO users (username, email, password, full_name, role, status) VALUES
('superadmin', 'superadmin@parfumelux.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Super Administrator', 'superadmin', 'active');

-- Insert Admin (password: admin123)
INSERT INTO users (username, email, password, full_name, role, status) VALUES
('admin', 'admin@parfumelux.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrator', 'admin', 'active');

-- Insert Partnership (password: partner123)
INSERT INTO users (username, email, password, full_name, role, status) VALUES
('partnership', 'partnership@parfumelux.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Partnership Manager', 'partnership', 'active');

-- Insert Sample Customer (password: customer123)
INSERT INTO users (username, email, password, full_name, phone, address, role, status) VALUES
('customer1', 'customer@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'John Doe', '081234567890', 'Jl. Sudirman No. 123, Jakarta Pusat', 'customer', 'active');

-- Insert Sample Products
INSERT INTO products (name, type, scent, price, description, image, stock, status) VALUES
('Viktor & Rolf Flowerbomb', 'Eau de Parfum', 'Floral', 1500000, 'Aroma bunga yang mewah dan elegan dengan sentuhan oriental', 'images/perfume.png', 50, 'active'),
('Chanel No. 5', 'Eau de Parfum', 'Floral', 2000000, 'Parfum ikonik dengan aroma floral aldehyde yang timeless', 'images/perfume.png', 30, 'active'),
('Dior Sauvage', 'Eau de Toilette', 'Woody', 1800000, 'Aroma maskulin dengan sentuhan woody dan spicy', 'images/perfume.png', 45, 'active'),
('Tom Ford Black Orchid', 'Eau de Parfum', 'Gourmand', 2500000, 'Aroma misterius dengan sentuhan dark chocolate dan vanilla', 'images/perfume.png', 25, 'active'),
('Acqua di Gio', 'Eau de Toilette', 'Marine', 1200000, 'Aroma segar marine dengan sentuhan citrus', 'images/perfume.png', 60, 'active'),
('Yves Saint Laurent Black Opium', 'Eau de Parfum', 'Gourmand', 1700000, 'Aroma manis dengan coffee dan vanilla yang sensual', 'images/perfume.png', 40, 'active'),
('Versace Eros', 'Eau de Toilette', 'Woody', 1400000, 'Aroma maskulin dengan mint, lemon, dan vanilla', 'images/perfume.png', 55, 'active'),
('Gucci Bloom', 'Eau de Parfum', 'Floral', 1600000, 'Aroma bunga putih yang natural dan feminin', 'images/perfume.png', 35, 'active'),
('Paco Rabanne 1 Million', 'Eau de Toilette', 'Spicy', 1300000, 'Aroma spicy dengan cinnamon dan leather', 'images/perfume.png', 50, 'active'),
('Lancôme La Vie Est Belle', 'Eau de Parfum', 'Gourmand', 1900000, 'Aroma manis dengan iris, patchouli, dan praline', 'images/perfume.png', 30, 'active'),
('Burberry Brit', 'Eau de Toilette', 'Floral', 1100000, 'Aroma floral fruity yang fresh dan playful', 'images/perfume.png', 45, 'active'),
('Calvin Klein CK One', 'Eau de Toilette', 'Citrus', 800000, 'Aroma unisex yang fresh dan clean', 'images/perfume.png', 70, 'active'),
('Hermès Terre d\'Hermès', 'Eau de Toilette', 'Woody', 2200000, 'Aroma earthy dengan woody dan citrus', 'images/perfume.png', 20, 'active'),
('Marc Jacobs Daisy', 'Eau de Toilette', 'Floral', 1250000, 'Aroma floral fruity yang youthful dan vibrant', 'images/perfume.png', 40, 'active'),
('Giorgio Armani Acqua di Gioia', 'Eau de Parfum', 'Marine', 1550000, 'Aroma aquatic dengan mint dan jasmine', 'images/perfume.png', 35, 'active');

-- Insert Sample Order
INSERT INTO orders (user_id, order_number, total_amount, shipping_fee, payment_method, shipping_address, notes, payment_status, order_status) VALUES
(4, 'ORD-20241130-0001', 3015000, 15000, 'bank_transfer', 'Jl. Sudirman No. 123, Jakarta Pusat, 10110', 'Mohon kirim dengan bubble wrap', 'paid', 'processing');

-- Insert Sample Order Items
INSERT INTO order_items (order_id, product_id, product_name, product_price, quantity, subtotal) VALUES
(1, 1, 'Viktor & Rolf Flowerbomb', 1500000, 2, 3000000);

-- Insert Sample Favorites
INSERT INTO favorites (user_id, product_id) VALUES
(4, 1),
(4, 2),
(4, 5);

-- =============================================
-- Views untuk Reporting
-- =============================================

-- View: Total Sales per Product
CREATE OR REPLACE VIEW v_product_sales AS
SELECT 
  p.id,
  p.name,
  p.type,
  p.scent,
  p.price,
  p.stock,
  COALESCE(SUM(oi.quantity), 0) AS total_sold,
  COALESCE(SUM(oi.subtotal), 0) AS total_revenue
FROM products p
LEFT JOIN order_items oi ON p.id = oi.product_id
LEFT JOIN orders o ON oi.order_id = o.id AND o.payment_status = 'paid'
GROUP BY p.id, p.name, p.type, p.scent, p.price, p.stock;

-- View: User Order Summary
CREATE OR REPLACE VIEW v_user_orders AS
SELECT 
  u.id AS user_id,
  u.full_name,
  u.email,
  COUNT(o.id) AS total_orders,
  COALESCE(SUM(CASE WHEN o.payment_status = 'paid' THEN o.total_amount ELSE 0 END), 0) AS total_spent,
  MAX(o.created_at) AS last_order_date
FROM users u
LEFT JOIN orders o ON u.id = o.user_id
WHERE u.role = 'customer'
GROUP BY u.id, u.full_name, u.email;

-- View: Daily Sales Report
CREATE OR REPLACE VIEW v_daily_sales AS
SELECT 
  DATE(o.created_at) AS order_date,
  COUNT(o.id) AS total_orders,
  SUM(o.total_amount) AS total_revenue,
  AVG(o.total_amount) AS avg_order_value
FROM orders o
WHERE o.payment_status = 'paid'
GROUP BY DATE(o.created_at)
ORDER BY order_date DESC;

-- =============================================
-- Stored Procedures
-- =============================================

DELIMITER //

-- Procedure: Get Low Stock Products
CREATE PROCEDURE sp_get_low_stock_products(IN threshold INT)
BEGIN
  SELECT 
    id,
    name,
    type,
    scent,
    price,
    stock,
    status
  FROM products
  WHERE stock <= threshold AND status = 'active'
  ORDER BY stock ASC;
END //

-- Procedure: Get User Order History
CREATE PROCEDURE sp_get_user_order_history(IN p_user_id INT)
BEGIN
  SELECT 
    o.id,
    o.order_number,
    o.total_amount,
    o.shipping_fee,
    o.payment_method,
    o.payment_status,
    o.order_status,
    o.created_at,
    COUNT(oi.id) AS total_items
  FROM orders o
  LEFT JOIN order_items oi ON o.id = oi.order_id
  WHERE o.user_id = p_user_id
  GROUP BY o.id
  ORDER BY o.created_at DESC;
END //

-- Procedure: Update Order Status
CREATE PROCEDURE sp_update_order_status(
  IN p_order_id INT,
  IN p_order_status VARCHAR(50),
  IN p_tracking_number VARCHAR(100)
)
BEGIN
  UPDATE orders 
  SET 
    order_status = p_order_status,
    tracking_number = COALESCE(p_tracking_number, tracking_number),
    updated_at = CURRENT_TIMESTAMP
  WHERE id = p_order_id;
END //

DELIMITER ;

-- =============================================
-- Triggers
-- =============================================

DELIMITER //

-- Trigger: Validate Stock Before Order
CREATE TRIGGER trg_validate_stock_before_order
BEFORE INSERT ON order_items
FOR EACH ROW
BEGIN
  DECLARE current_stock INT;
  
  SELECT stock INTO current_stock
  FROM products
  WHERE id = NEW.product_id;
  
  IF current_stock < NEW.quantity THEN
    SIGNAL SQLSTATE '45000'
    SET MESSAGE_TEXT = 'Insufficient stock for this product';
  END IF;
END //

-- Trigger: Update Product Updated_at
CREATE TRIGGER trg_update_product_timestamp
BEFORE UPDATE ON products
FOR EACH ROW
BEGIN
  SET NEW.updated_at = CURRENT_TIMESTAMP;
END //

DELIMITER ;

-- =============================================
-- Indexes untuk Performance
-- =============================================

-- Additional indexes for better query performance
CREATE INDEX idx_orders_user_payment ON orders(user_id, payment_status);
CREATE INDEX idx_orders_created_payment ON orders(created_at, payment_status);
CREATE INDEX idx_products_status_stock ON products(status, stock);
CREATE INDEX idx_order_items_order_product ON order_items(order_id, product_id);

-- =============================================
-- Grant Permissions (Optional)
-- =============================================

-- Create application user (uncomment if needed)
-- CREATE USER IF NOT EXISTS 'arde_app'@'localhost' IDENTIFIED BY 'your_secure_password';
-- GRANT SELECT, INSERT, UPDATE, DELETE ON arde_lux.* TO 'arde_app'@'localhost';
-- FLUSH PRIVILEGES;

-- =============================================
-- End of SQL Script
-- =============================================
