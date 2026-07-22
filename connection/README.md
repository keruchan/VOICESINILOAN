# VOICE2 — connection/ folder

This folder contains all shared backend files for the **VOICE2 Barangay Blotter System**,
serving the **Municipality of Siniloan, Province of Laguna** (20 barangays).

---

## File Overview

| File | Purpose |
|---|---|
| `connect.php` | PDO database connection + session init + helper functions |
| `auth.php` | Role-based page guard — include in protected pages |
| `login.php` | Login page (all roles) |
| `register.php` | Community user self-registration — emails a verification link |
| `verify_email.php` | Verification-link landing page — activates the account |
| `resend_verification.php` | Re-sends a fresh verification link |
| `email_verification.php` | Shared token generation + email-sending helper |
| `mailer.php` | PHPMailer/SMTP wrapper — `sendAppMail()` |
| `mail_config.php` | **Your SMTP credentials** (not committed — see below) |
| `phpmailer/` | Vendored PHPMailer library (no Composer needed) |
| `logout.php` | Destroys session and redirects to login |
| `voice2_db.sql` | MySQL schema **and** seed data (Siniloan-aligned) — import once to set up the `voice2_db` database |

---

## Folder Structure

```
VOICE2/
├── index.php                   ← Public landing page (Siniloan, Laguna)
├── connection/
│   ├── connect.php             ← DB connection (include everywhere)
│   ├── auth.php                ← Page guards
│   ├── login.php               ← Login page
│   ├── register.php            ← Community registration
│   ├── logout.php              ← Logout handler
│   └── voice2_db.sql           ← Database schema + Siniloan seed data
├── community-portal/
│   └── index.php
├── barangay-portal/
│   └── index.php
└── superadmin-portal/
    └── index.php
```

---

## Setup Steps

### 1. Create the database
The dump is self-contained — it drops and recreates the `voice2_db` database, so you
can import it from a terminal **or** through phpMyAdmin (Import tab).

```bash
# XAMPP (Windows), from the VOICE2/ root folder:
"C:/xampp/mysql/bin/mysql.exe" -u root < connection/voice2_db.sql

# or on a standard shell:
mysql -u root -p < connection/voice2_db.sql
```

Seeded barangays are the **20 official barangays of Siniloan, Laguna**.

### 2. Update credentials in `connect.php`
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'voice2_db');
define('DB_USER', 'root');
define('DB_PASS', 'your_password');
```

### 3. Configure outgoing email (account verification)
Community self-registration sends a verification link by email instead of
waiting for barangay approval — the account activates the instant the
resident clicks it. This uses your **Hostinger email mailbox** over SMTP
(via the vendored PHPMailer library, no Composer required).

Open `connection/mail_config.php` and fill in your mailbox details
(find them in Hostinger hPanel → **Emails** → your mailbox →
**Configure email client**):

```php
define('SMTP_HOST',       'smtp.hostinger.com');
define('SMTP_PORT',       465);       // 465 = SSL (default below), 587 = STARTTLS
define('SMTP_ENCRYPTION', 'ssl');     // 'ssl' or 'tls' — must match the port
define('SMTP_USERNAME',   'your-mailbox@yourdomain.com');
define('SMTP_PASSWORD',   'your-mailbox-password');
define('SMTP_FROM_EMAIL', 'your-mailbox@yourdomain.com');
define('SMTP_FROM_NAME',  'VOICE — Siniloan Barangay System');
```

This file is **git-ignored** (`.gitignore`) — your real password never gets
committed. Set `SMTP_DEBUG` to `true` temporarily if a send fails and you
need to see the raw SMTP conversation in the PHP error log.

**How the flow works:**
1. Resident registers → account is created with `is_active = 0`.
2. A verification email is sent with a link to `verify_email.php?token=...`
   (the token is single-use and expires after 24 hours; only its SHA-256
   hash is stored in the database).
3. Clicking the link sets `is_active = 1` and `email_verified_at = NOW()` —
   **no barangay/superadmin approval step**. They can log in immediately.
4. If the email never arrives, "Resend Verification Email" (shown on the
   registration success screen, the login error, and the verify-link error
   page) generates a new token and re-sends it.
5. Barangay/superadmin "Activate" buttons still work as a manual fallback
   if a resident's email is ever undeliverable.

### 4. Run with a local PHP server
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
| `community` | community-portal/ | Self-registers, activates via emailed verification link |
| `barangay` | barangay-portal/ | Created by admin/superadmin |
| `superadmin` | superadmin-portal/ | Created manually in DB |

---

## Demo Accounts

Seeded by `voice2_db.sql` — **password for all three is `demo1234`**.
They are wired to **Barangay 5 (G. Redor, Pob.)**, which ships with 15 demo
blotters covering every case status (pending, active, mediation, resolved,
dismissed, CFA, escalated, deliberation, repudiated, transferred, closed) plus
matching mediation schedules, notifications, penalties, and activity logs.

| Role | Email | Password |
|---|---|---|
| Superadmin | `superadmin@demo.test` | `demo1234` |
| Barangay officer | `barangay@demo.test` | `demo1234` |
| Community resident | `community@demo.test` | `demo1234` |

The community resident (`community@demo.test`) is the complainant on several
cases and the respondent on two, so both "My Blotters" and "Cases Against You"
have data. A pending resident (`pedro.demo@demo.test`) is left inactive to demo
the superadmin/barangay approval flow.

---

## Generate a Password Hash

When creating barangay/superadmin accounts manually in the DB:
```bash
php -r "echo password_hash('YourPassword', PASSWORD_BCRYPT);"
```
