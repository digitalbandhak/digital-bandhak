-- ═══════════════════════════════════════════════════════════════
-- Digital Bandhak - Database Setup
-- Import karne ka tarika:
--   1. phpMyAdmin mein "digitalbandhak" naam ka database banao
--   2. Us database ko select karo
--   3. Yeh file import karo (CREATE DATABASE wali line hata do agar error aaye)
-- ═══════════════════════════════════════════════════════════════

-- Step 1: Database banao (agar pehle se nahi hai)
CREATE DATABASE IF NOT EXISTS digitalbandhak
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE digitalbandhak;

-- ─── SHOPS TABLE ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS shops (
  id VARCHAR(10) PRIMARY KEY,
  name VARCHAR(200) NOT NULL,
  owner_name VARCHAR(200) NOT NULL,
  email VARCHAR(200),
  mobile VARCHAR(15) NOT NULL,
  password VARCHAR(255) NOT NULL,
  address TEXT,
  city VARCHAR(100),
  state VARCHAR(100) DEFAULT 'Bihar',
  pincode VARCHAR(10),
  gst VARCHAR(20),
  licence VARCHAR(50),
  logo VARCHAR(255),
  photo VARCHAR(255) DEFAULT NULL,
  status ENUM('active','inactive','suspended') DEFAULT 'inactive',
  subscription ENUM('Trial','Standard','Premium','Expired') DEFAULT 'Trial',
  sub_start DATE,
  sub_expiry DATE,
  balance DECIMAL(10,2) DEFAULT 0,
  default_interest DECIMAL(5,2) DEFAULT 2.00,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ─── ADMIN TABLE ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS admin (
  id INT PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(200) DEFAULT 'Super Admin',
  email VARCHAR(200) DEFAULT 'digitalbandhak@gmail.com',
  mobile VARCHAR(15) DEFAULT '9900000001',
  password VARCHAR(255) NOT NULL,
  photo VARCHAR(255) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Purane version se upgrade ke liye (ignore if already exists)
ALTER TABLE admin ADD COLUMN IF NOT EXISTS photo VARCHAR(255) DEFAULT NULL;

-- ─── PAWNS (BANDHAK) TABLE ────────────────────────────────────
CREATE TABLE IF NOT EXISTS pawns (
  id VARCHAR(20) PRIMARY KEY,
  shop_id VARCHAR(10) NOT NULL,
  customer_name VARCHAR(200) NOT NULL,
  customer_mobile VARCHAR(15) NOT NULL,
  customer_aadhaar VARCHAR(20),
  customer_father VARCHAR(200),
  customer_address TEXT,
  item_category VARCHAR(100),
  item_description VARCHAR(500),
  item_weight VARCHAR(50),
  item_condition ENUM('Excellent','Good','Fair','Poor') DEFAULT 'Good',
  market_value DECIMAL(12,2),
  item_photos TEXT,
  item_photo VARCHAR(255),
  loan_amount DECIMAL(12,2) NOT NULL,
  interest_rate DECIMAL(5,2) DEFAULT 2.00,
  loan_date DATE NOT NULL,
  return_date DATE,
  duration VARCHAR(50),
  payment_mode ENUM('Cash','UPI') DEFAULT 'Cash',
  status ENUM('active','closed','redeemed') DEFAULT 'active',
  total_paid DECIMAL(12,2) DEFAULT 0,
  total_remaining DECIMAL(12,2),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (shop_id) REFERENCES shops(id) ON DELETE CASCADE
);

-- ─── PAYMENTS TABLE ──────────────────────────────────────────
CREATE TABLE IF NOT EXISTS payments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  pawn_id VARCHAR(20) NOT NULL,
  shop_id VARCHAR(10) NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  payment_mode ENUM('Cash','UPI','NEFT','Cheque') DEFAULT 'Cash',
  note TEXT,
  payment_date DATE NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (pawn_id) REFERENCES pawns(id) ON DELETE CASCADE
);

-- ─── CHAT MESSAGES TABLE ─────────────────────────────────────
CREATE TABLE IF NOT EXISTS chat_messages (
  id INT AUTO_INCREMENT PRIMARY KEY,
  shop_id VARCHAR(10) NOT NULL,
  sender ENUM('admin','shop') NOT NULL,
  message TEXT,
  image_path VARCHAR(255),
  is_read TINYINT DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ─── AUDIT LOGS TABLE ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS audit_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  shop_id VARCHAR(10),
  user_name VARCHAR(200),
  user_role ENUM('Admin','Shop Owner','Customer'),
  action VARCHAR(200),
  target VARCHAR(100),
  ip_address VARCHAR(50),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ─── NOTIFICATIONS TABLE ──────────────────────────────────────
CREATE TABLE IF NOT EXISTS notifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  shop_id VARCHAR(10),
  icon VARCHAR(10),
  title VARCHAR(200),
  body TEXT,
  type ENUM('success','warn','info') DEFAULT 'info',
  is_read TINYINT DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ─── OTP TABLE ────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS otp_codes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  mobile VARCHAR(15) NOT NULL,
  shop_id VARCHAR(10),
  otp VARCHAR(6) NOT NULL,
  purpose ENUM('login','reset') DEFAULT 'login',
  is_used TINYINT DEFAULT 0,
  expires_at TIMESTAMP,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ─── CUSTOMER ACCOUNTS ────────────────────────────────────────
CREATE TABLE IF NOT EXISTS customer_accounts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(200) NOT NULL,
  mobile VARCHAR(15) NOT NULL UNIQUE,
  address TEXT,
  aadhaar VARCHAR(20),
  shop_id VARCHAR(10),
  status ENUM('pending','active','blocked') DEFAULT 'pending',
  registered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  activated_at TIMESTAMP NULL,
  activated_by VARCHAR(200)
);

-- ─── ADMIN DATA ────────────────────────────────────────────────
-- IMPORTANT: Pehle import karo, phir localhost/db_v25/setup_admin.php chalao
-- setup_admin.php password hash set karega aur phir khud delete ho jaega
INSERT INTO admin (name, email, mobile, password)
SELECT 'Om', 'enjoylight.0408@gmail.com', '6206869543', 'SETUP_REQUIRED'
WHERE NOT EXISTS (SELECT 1 FROM admin WHERE email = 'enjoylight.0408@gmail.com');
