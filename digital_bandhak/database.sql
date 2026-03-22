-- ============================================
-- DIGITAL BANDHAK - Database Schema
-- Run this in phpMyAdmin or MySQL CLI
-- ============================================

CREATE DATABASE IF NOT EXISTS digital_bandhak CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE digital_bandhak;

-- ============================================
-- SUPER ADMIN
-- ============================================
CREATE TABLE IF NOT EXISTS super_admin (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(100) NOT NULL UNIQUE,
  email VARCHAR(150) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Default admin: admin@bandhak.in / Admin@1234
INSERT INTO super_admin (username, email, password) VALUES
('superadmin', 'admin@bandhak.in', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');

-- ============================================
-- SHOPS
-- ============================================
CREATE TABLE IF NOT EXISTS shops (
  id INT AUTO_INCREMENT PRIMARY KEY,
  shop_id VARCHAR(30) NOT NULL UNIQUE,
  shop_name VARCHAR(200) NOT NULL,
  owner_name VARCHAR(150) NOT NULL,
  owner_email VARCHAR(150),
  owner_mobile VARCHAR(15) NOT NULL,
  address TEXT,
  city VARCHAR(100),
  state VARCHAR(100),
  pincode VARCHAR(10),
  logo VARCHAR(255),
  password VARCHAR(255) NOT NULL,
  status ENUM('active','inactive','suspended') DEFAULT 'inactive',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ============================================
-- STAFF (under shop owner)
-- ============================================
CREATE TABLE IF NOT EXISTS staff (
  id INT AUTO_INCREMENT PRIMARY KEY,
  shop_id VARCHAR(30) NOT NULL,
  staff_name VARCHAR(150) NOT NULL,
  mobile VARCHAR(15),
  password VARCHAR(255) NOT NULL,
  status ENUM('active','inactive') DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (shop_id) REFERENCES shops(shop_id) ON DELETE CASCADE
);

-- ============================================
-- SUBSCRIPTIONS
-- ============================================
CREATE TABLE IF NOT EXISTS subscriptions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  shop_id VARCHAR(30) NOT NULL,
  plan_type ENUM('trial','monthly','halfyearly','annual') NOT NULL,
  start_date DATE NOT NULL,
  end_date DATE NOT NULL,
  amount DECIMAL(10,2) DEFAULT 0.00,
  payment_mode ENUM('cash','online','free') DEFAULT 'free',
  status ENUM('active','expired','cancelled') DEFAULT 'active',
  extended_by INT DEFAULT NULL,
  notes TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (shop_id) REFERENCES shops(shop_id) ON DELETE CASCADE
);

-- ============================================
-- CUSTOMERS
-- ============================================
CREATE TABLE IF NOT EXISTS customers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  shop_id VARCHAR(30) NOT NULL,
  bandhak_id VARCHAR(30) NOT NULL UNIQUE,
  full_name VARCHAR(200) NOT NULL,
  mobile VARCHAR(15) NOT NULL,
  address TEXT,
  aadhaar_masked VARCHAR(20),
  aadhaar_hash VARCHAR(255),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (shop_id) REFERENCES shops(shop_id) ON DELETE CASCADE
);

-- ============================================
-- PAWN ENTRIES (Bandhak)
-- ============================================
CREATE TABLE IF NOT EXISTS pawn_entries (
  id INT AUTO_INCREMENT PRIMARY KEY,
  bandhak_id VARCHAR(30) NOT NULL,
  shop_id VARCHAR(30) NOT NULL,
  customer_id INT NOT NULL,
  item_type VARCHAR(100) NOT NULL,
  item_description TEXT NOT NULL,
  item_photo VARCHAR(255),
  loan_amount DECIMAL(12,2) NOT NULL,
  interest_rate DECIMAL(5,2) NOT NULL,
  duration_months INT DEFAULT 6,
  pawn_date DATE NOT NULL,
  due_date DATE,
  principal_paid DECIMAL(12,2) DEFAULT 0.00,
  interest_paid DECIMAL(12,2) DEFAULT 0.00,
  total_paid DECIMAL(12,2) DEFAULT 0.00,
  remaining_amount DECIMAL(12,2),
  status ENUM('active','closed','deleted','overdue') DEFAULT 'active',
  receipt_number VARCHAR(50) UNIQUE,
  created_by ENUM('owner','staff') DEFAULT 'owner',
  created_by_id INT,
  deleted_at TIMESTAMP NULL,
  deleted_by INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (shop_id) REFERENCES shops(shop_id) ON DELETE CASCADE,
  FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
);

-- ============================================
-- PAYMENT HISTORY
-- ============================================
CREATE TABLE IF NOT EXISTS payments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  pawn_id INT NOT NULL,
  bandhak_id VARCHAR(30) NOT NULL,
  shop_id VARCHAR(30) NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  principal_component DECIMAL(12,2) DEFAULT 0.00,
  interest_component DECIMAL(12,2) DEFAULT 0.00,
  payment_mode ENUM('cash','online','upi','qr') DEFAULT 'cash',
  payment_date DATE NOT NULL,
  transaction_ref VARCHAR(100),
  remaining_after DECIMAL(12,2),
  confirmed_by INT,
  sms_sent TINYINT(1) DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (pawn_id) REFERENCES pawn_entries(id) ON DELETE CASCADE
);

-- ============================================
-- AUDIT LOGS
-- ============================================
CREATE TABLE IF NOT EXISTS audit_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  shop_id VARCHAR(30),
  action_type VARCHAR(100) NOT NULL,
  action_description TEXT NOT NULL,
  performed_by_type ENUM('super_admin','owner','staff') NOT NULL,
  performed_by_id INT,
  performed_by_name VARCHAR(150),
  ip_address VARCHAR(45),
  reference_id VARCHAR(50),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================
-- PRIVATE CHAT
-- ============================================
CREATE TABLE IF NOT EXISTS admin_chat_messages (
  id INT AUTO_INCREMENT PRIMARY KEY,
  shop_id VARCHAR(30) NOT NULL,
  sender_type ENUM('admin','owner') NOT NULL,
  sender_id INT NOT NULL,
  message TEXT NOT NULL,
  is_read TINYINT(1) DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (shop_id) REFERENCES shops(shop_id) ON DELETE CASCADE
);

-- ============================================
-- OTP TABLE (Customer login)
-- ============================================
CREATE TABLE IF NOT EXISTS customer_otps (
  id INT AUTO_INCREMENT PRIMARY KEY,
  bandhak_id VARCHAR(30) NOT NULL,
  mobile VARCHAR(15) NOT NULL,
  otp VARCHAR(10) NOT NULL,
  expires_at DATETIME NOT NULL,
  is_used TINYINT(1) DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================
-- INDEXES for performance
-- ============================================
ALTER TABLE pawn_entries ADD INDEX idx_shop_status (shop_id, status);
ALTER TABLE pawn_entries ADD INDEX idx_bandhak_id (bandhak_id);
ALTER TABLE payments ADD INDEX idx_pawn_id (pawn_id);
ALTER TABLE audit_logs ADD INDEX idx_shop_created (shop_id, created_at);
ALTER TABLE admin_chat_messages ADD INDEX idx_shop_read (shop_id, is_read);

-- ============================================
-- SITE SETTINGS (logo, name, etc)
-- ============================================
CREATE TABLE IF NOT EXISTS site_settings (
  `key` VARCHAR(100) PRIMARY KEY,
  `value` TEXT,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Default settings
INSERT IGNORE INTO site_settings (`key`,`value`) VALUES
('site_name', 'Digital Bandhak'),
('site_tagline', 'Aapki Girvee, Hamaari Zimmedaari'),
('whatsapp_number', ''),
('support_email', '');

-- ============================================
-- V4 UPDATES
-- ============================================

-- Add GST and License to shops
ALTER TABLE shops 
  ADD COLUMN IF NOT EXISTS gst_number VARCHAR(20) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS license_number VARCHAR(50) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS terms_accepted TINYINT(1) DEFAULT 0,
  ADD COLUMN IF NOT EXISTS blocked TINYINT(1) DEFAULT 0;

-- Chat file/photo sharing
ALTER TABLE admin_chat_messages 
  ADD COLUMN IF NOT EXISTS file_path VARCHAR(255) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS file_type VARCHAR(50) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS file_name VARCHAR(200) DEFAULT NULL;

-- Update admin credentials
UPDATE super_admin SET 
  email='digitalbandhak@gmail.com',
  password='$2y$10$8K1p/a0dVmGFXqpkZqGXWO3Pw5.R8Z.VwNp7gK9Y5bXkJqQGhP0pS'
WHERE id=1;
-- Note: above hash = Digitalbandhak@2026# (will be set via PHP)


-- ============================================
-- V5 ADDITIONS — Run these if upgrading
-- ============================================

-- Short sequential Bandhak counter
CREATE TABLE IF NOT EXISTS bandhak_counter (
    shop_id   VARCHAR(30) NOT NULL,
    year_code VARCHAR(4)  NOT NULL,
    last_num  INT DEFAULT 0,
    PRIMARY KEY (shop_id, year_code)
);

-- Email OTP table
CREATE TABLE IF NOT EXISTS email_otps (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    email      VARCHAR(150) NOT NULL,
    otp        VARCHAR(10)  NOT NULL,
    purpose    VARCHAR(50)  DEFAULT 'forgot_password',
    expires_at DATETIME     NOT NULL,
    is_used    TINYINT(1)   DEFAULT 0,
    created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
);

-- site_settings (if not exists from V3)
CREATE TABLE IF NOT EXISTS site_settings (
    `key`      VARCHAR(100) PRIMARY KEY,
    `value`    TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

INSERT IGNORE INTO site_settings (`key`,`value`) VALUES
    ('site_name',   'Digital Bandhak'),
    ('site_tagline','Aapki Girvee, Hamaari Zimmedaari'),
    ('whatsapp_number',''),
    ('support_email','digitalbandhak@gmail.com');

-- New admin (update credentials)
-- Run run_once/update_admin.php instead of this SQL


-- Pawn photos (multiple photos per entry)
CREATE TABLE IF NOT EXISTS pawn_photos (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    pawn_id    INT NOT NULL,
    photo_path VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (pawn_id) REFERENCES pawn_entries(id) ON DELETE CASCADE
);

-- Subscription Requests (shop owner sends request to admin)
CREATE TABLE IF NOT EXISTS subscription_requests (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    shop_id     VARCHAR(30) NOT NULL,
    plan_type   VARCHAR(20) NOT NULL DEFAULT 'monthly',
    message     TEXT,
    status      ENUM('pending','approved','rejected') DEFAULT 'pending',
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX(shop_id), INDEX(status)
);
