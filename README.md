# 🏦 Digital Bandhak
### Modern Pawn Shop Management Platform

A complete PHP + MySQL web application for managing pawn shop operations.

---

## 🚀 Deploy on Railway (Free)

[![Deploy on Railway](https://railway.app/button.svg)](https://railway.app)

### Steps:
1. Fork this repo on GitHub
2. Go to [railway.app](https://railway.app) → New Project
3. **Deploy from GitHub repo** → select this repo
4. Add **MySQL** plugin from Railway dashboard
5. Railway auto-sets `MYSQLHOST`, `MYSQLUSER`, `MYSQLPASSWORD`, `MYSQLDATABASE`
6. Visit your Railway URL + `/setup_admin.php` to set admin password
7. **Delete setup_admin.php** after setup ⚠️

---

## ✨ Features

- 👨‍💼 **Super Admin Panel** — Manage all shops, subscriptions, reports
- 🏪 **Shop Owner Dashboard** — Add/manage pawns, payments, customers  
- 💬 **Real-time Chat** — Admin ↔ Shop communication with image support
- 📊 **Reports & Analytics** — Revenue, audit logs, export PDF
- 🌐 **Hindi/English** — Full bilingual support
- 🌙 **Dark/Light Mode** — Theme toggle
- 📱 **Mobile Responsive** — Works on all screen sizes

---

## 🗄️ Database Setup

Import `sql/fresh_install.sql` in phpMyAdmin or Railway MySQL console.

---

## ⚙️ Environment Variables (Railway)

| Variable | Description |
|----------|-------------|
| `MYSQLHOST` | Auto-set by Railway |
| `MYSQLUSER` | Auto-set by Railway |
| `MYSQLPASSWORD` | Auto-set by Railway |
| `MYSQLDATABASE` | Auto-set by Railway |
| `MYSQLPORT` | Auto-set by Railway |

---

## 🔐 Default Login

After running `setup_admin.php`:
- **Admin:** your email + password you set
- **Shop:** Shop ID + password (set during registration)

---

*Built with PHP, MySQL, Vanilla JS — No frameworks needed*
