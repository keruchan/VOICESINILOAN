# VOICE2 — connection/ folder

This folder contains all shared backend files for the VOICE2 Barangay Blotter System.

---

## File Overview

| File | Purpose |
|---|---|
| `connect.php` | PDO database connection + session init + helper functions |
| `auth.php` | Role-based page guard — include in protected pages |
| `login.php` | Login page (all roles) |
| `register.php` | Community user self-registration |
| `logout.php` | Destroys session and redirects to login |
| `schema.sql` | MySQL database schema — run once to set up tables |

---

## Folder Structure

```
VOICE2/
├── index.html                  ← Your landing page
├── connection/
│   ├── connect.php             ← DB connection (include everywhere)
│   ├── auth.php                ← Page guards
│   ├── login.php               ← Login page
│   ├── register.php            ← Community registration
│   ├── logout.php              ← Logout handler
│   └── schema.sql              ← Database schema + seed data
├── community-portal/
│   └── index.html
├── barangay-portal/
│   └── index.html
└── superadmin-portal/
    └── index.html  (empty for now)
```

---

## Setup Steps

### 1. Create the database
```bash
mysql -u root -p < connection/schema.sql
```

### 2. Update credentials in `connect.php`
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'voice2_db');
define('DB_USER', 'root');
define('DB_PASS', 'your_password');
```

### 3. Run with a local PHP server
```bash
# From the VOICE2/ root folder:
php -S localhost:8000
```
Then open: `http://localhost:8000`

---

## How to Protect a Page

Add this at the very top of any PHP page that requires login:

```php
<?php
require_once '../connection/auth.php';
guardRole('community');   // or 'barangay' or 'superadmin'
?>
```

For pages that allow multiple roles:
```php
guardRole(['barangay', 'superadmin']);
```

---

## Logout Link

Add this anywhere in your portal HTML/PHP:
```html
<a href="../connection/logout.php">Logout</a>
```

---

## Helper Functions (from connect.php)

| Function | Description |
|---|---|
| `isLoggedIn()` | Returns `true` if a user session is active |
| `currentUser()` | Returns array with `id`, `name`, `role`, `barangay_id` |
| `requireLogin()` | Redirects to login if not logged in |
| `requireRole('barangay')` | Redirects if role doesn't match |
| `redirect($url)` | Short redirect + exit |
| `e($string)` | HTML-safe output (prevents XSS) |
| `jsonResponse(true, 'OK', $data)` | Send JSON response for AJAX |

---

## User Roles

| Role | Portal | Notes |
|---|---|---|
| `community` | community-portal/ | Self-registers, pending barangay approval |
| `barangay` | barangay-portal/ | Created by admin/superadmin |
| `superadmin` | superadmin-portal/ | Created manually in DB |

---

## Generate a Password Hash

When creating barangay/superadmin accounts manually in the DB:
```bash
php -r "echo password_hash('YourPassword', PASSWORD_BCRYPT);"
```
