# 🏅 Digital Bandhak — Complete Setup Guide

## Stack
- **Frontend**: HTML5, CSS3, JavaScript (Vanilla)
- **Backend**: PHP 8.x
- **Database**: MySQL 8.x
- **Server**: XAMPP / WAMP / Apache

---

## 📁 Folder Structure

```
digital-bandhak/
├── index.php                  ← Login page (all 3 roles)
├── register_shop.php          ← New shop self-registration
├── forgot_password.php        ← Password reset
├── customer_dashboard.php     ← Customer view (OTP login)
├── terms.php                  ← Terms & Conditions
├── .htaccess                  ← Security rules
│
├── css/
│   └── style.css              ← Complete styling
│
├── js/
│   └── app.js                 ← All JS functions
│
├── includes/
│   └── config.php             ← DB config + helpers
│
├── php/                       ← AJAX / API endpoints
│   ├── logout.php
│   ├── chat_send.php
│   ├── chat_fetch.php
│   ├── pawn_delete.php
│   ├── otp_request.php
│   ├── otp_verify.php
│   └── receipt_print.php
│
├── shop/                      ← Shop Owner / Staff
│   ├── dashboard.php
│   ├── pawn_add.php
│   ├── pawn_view.php
│   ├── payments.php
│   ├── reports.php
│   ├── subscription.php
│   └── staff_panel.php
│
├── admin/                     ← Super Admin
│   ├── dashboard.php
│   ├── shop_add.php
│   └── subscription_add.php
│
├── uploads/                   ← Uploaded files
│   ├── products/              ← Item photos
│   └── receipts/              ← Generated receipts
│
└── database.sql               ← Full database schema
```

---

## 🚀 Installation Steps

### Step 1: XAMPP Setup
1. XAMPP install karo (xampp.apachefriends.org)
2. Apache + MySQL start karo
3. Folder `digital-bandhak` ko `C:/xampp/htdocs/` mein daalo

### Step 2: Database Setup
1. Browser mein `http://localhost/phpmyadmin` kholo
2. New database banao: **digital_bandhak**
3. `database.sql` file import karo
4. Default admin password: `password` (change karo!)

### Step 3: Config Edit
`includes/config.php` mein apna DB password daalo:
```php
define('DB_USER', 'root');
define('DB_PASS', '');  // apna password
```

### Step 4: Folder Permissions
```
uploads/          → writable (chmod 755)
uploads/products/ → writable
uploads/receipts/ → writable
```

### Step 5: Open in Browser
```
http://localhost/digital-bandhak/
```

---

## 🔐 Default Login Credentials

| Role | Credential | Password |
|------|-----------|---------|
| Super Admin | Email: `admin@bandhak.in` | `password` |
| Shop Owner | Shop ID diya jayega | Set at registration |
| Customer | Bandhak ID + Mobile + OTP | OTP (dev mode mein console mein dikhega) |

⚠️ **Production mein default password turant change karo!**

---

## 📋 Features Checklist

- ✅ Super Admin Login (email + password)
- ✅ Shop Owner Login (Shop ID + password)
- ✅ Staff Login (Shop ID + staff password, limited access)
- ✅ Customer OTP Login (Bandhak ID + mobile)
- ✅ New Shop Registration (self + admin approval)
- ✅ Forgot Password (shop owner + admin)
- ✅ Super Admin Dashboard (stats, shops, subs, audit, chat)
- ✅ Shop Owner Dashboard (stats, tabs, quick actions)
- ✅ Add Pawn Entry (form → preview → owner password → save → print)
- ✅ Payment Recording (cash/online/upi, owner confirm, auto calculate)
- ✅ Payment History per pawn
- ✅ Soft Delete with audit log + owner password
- ✅ Subscription Management (trial/monthly/halfyearly/annual)
- ✅ Reports (monthly/yearly/custom, PDF print, filter by status/date)
- ✅ Receipt Print (with product photo, masked Aadhaar, shop logo)
- ✅ Duplicate Receipt (with watermark)
- ✅ Private Chat (AJAX real-time, per shop thread, read receipts)
- ✅ Staff Panel (new entry + bandhak list only)
- ✅ Customer Dashboard (own items, payment history, masked Aadhaar)
- ✅ Audit Logs (all critical actions)
- ✅ Terms & Conditions (with accept checkbox)
- ✅ Mobile Responsive
- ✅ Security (.htaccess, PDO prepared statements, password_hash)

---

## 📱 SMS Gateway Integration (OTP)
`php/otp_request.php` mein apna SMS API daalo:

```php
// Fast2SMS example:
$url = "https://www.fast2sms.com/dev/bulkV2?authorization=YOUR_API_KEY&route=otp&variables_values=$otp&flash=0&numbers=$mobile";
file_get_contents($url);
```

---

## 💳 Online Payment Integration
`shop/payments.php` mein Razorpay/PayU integration karo:
- Webhook endpoint: `php/payment_webhook.php` (banani hogi)
- QR generate karo apne UPI ID se

---

## 🔧 Production Checklist
- [ ] `config.php` mein strong password
- [ ] `.htaccess` mein HTTPS redirect uncomment karo
- [ ] `display_errors = Off` confirm karo
- [ ] Default admin password change karo
- [ ] SMS API key set karo
- [ ] `dev_otp` field `otp_request.php` se remove karo
- [ ] SSL certificate install karo
- [ ] Regular database backup setup karo

---

## 📞 Support
Admin Chat feature use karo ya `admin@digitalbandhak.in` par contact karo.
