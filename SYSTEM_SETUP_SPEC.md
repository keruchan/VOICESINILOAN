# VOICE2 — Account Verification (Email OTP) Setup Specification

This is a complete, exact record of how community account verification was
changed from **manual barangay-officer approval** to **self-service email
verification** (a one-time verification token/link, emailed to the resident)
— so the same process can be reproduced on another machine using the
**same domain and Hostinger account**.

**The only thing intentionally left out is the email mailbox password** —
never store that in a plain file. Everything else here is copy-paste-exact.

---

## 1. What changed — manual approval → email verification

**Before:** a resident self-registers → account created with `is_active = 0`
→ a barangay officer or superadmin must manually click "Approve"/"Activate"
in their portal before the resident can log in.

**After:** a resident self-registers → account created with `is_active = 0`
→ the system immediately emails them a one-time verification link → clicking
it **activates the account instantly**, no staff action required. Officers
can still manually activate as a fallback (e.g. if an email never arrives),
but it's no longer the primary/expected path.

Only the **community** role goes through this. Barangay officers and
superadmin accounts are still created directly (by an admin) with
`is_active = 1` and never touch this flow.

---

## 2. Database changes

Three columns were added to the `users` table:

```sql
ALTER TABLE `users`
  ADD COLUMN `email_verified_at`    DATETIME DEFAULT NULL
      COMMENT 'Set when the user clicks the emailed verification link',
  ADD COLUMN `email_verify_token`   VARCHAR(64) DEFAULT NULL
      COMMENT 'SHA-256 hash of the pending verification token',
  ADD COLUMN `email_verify_expires` DATETIME DEFAULT NULL;

ALTER TABLE `users`
  ADD KEY `idx_email_verify_token` (`email_verify_token`);
```

**Important security detail:** the database stores only a SHA-256 **hash** of
the token — the raw token only ever exists inside the emailed link itself,
the same principle as a password-reset token. If the `users` table is ever
exposed, no valid verification links can be reconstructed from it.

This is already included in `connection/voice2_db.sql` (the canonical DB
dump) — importing that file gives you these columns automatically. If
applying to an existing database instead, run the `ALTER TABLE` above.

---

## 3. Files created / modified for this feature

### New files

| File | Purpose |
|---|---|
| `connection/mail_config.php` | SMTP credentials (git-ignored — see §5) |
| `connection/mailer.php` | `sendAppMail()` — thin wrapper around PHPMailer |
| `connection/email_verification.php` | `issueVerificationEmail()`, `appBaseUrl()`, `appBasePath()` — token generation + the emailed HTML |
| `connection/verify_email.php` | **The OTP-link landing page** — where the emailed link points; validates the token and activates the account |
| `connection/resend_verification.php` | Re-issues a fresh token + email when a link expires or a user needs another one |
| `connection/phpmailer/src/*.php` | Vendored PHPMailer library (`Exception.php`, `PHPMailer.php`, `SMTP.php`) — no Composer, just required by path |

### Modified files

| File | Change |
|---|---|
| `connection/register.php` | On successful signup, calls `issueVerificationEmail()` and shows a "Check your email" screen instead of just "pending approval" |
| `connection/login.php` | Distinguishes **unverified** (needs to check email / resend link) from **deactivated by staff** in its error messaging, and offers a resend action |
| `.gitignore` | Added `connection/mail_config.php` so the mailbox password is never committed |
| `superadmin-portal/index.php` | Notification bell copy: *"pending approval"* → *"unverified"* |
| `superadmin-portal/pages/dashboard.php` | Alert banner: *"New registrations need your review"* → *"Awaiting email verification — you can activate manually"*; sidebar card *"Pending Approvals"* → *"Unverified Accounts"*; button *"Approve"* → *"Activate"* |

> `superadmin-portal/pages/users.php`'s generic "Pending Approval" KPI/filter
> was deliberately **left unchanged** — that count mixes community *and*
> barangay-officer accounts, and officers never go through email
> verification, so relabeling it "Unverified" there would be inaccurate.

---

## 4. The verification landing page (`connection/verify_email.php`)

This is the page created specifically for this process — where the emailed
link points, and where the actual token check + activation happens.

**Logic:**
1. Read `?token=` from the URL, SHA-256 hash it, look up a `users` row with
   a matching `email_verify_token`
2. If none found → show **"Invalid verification link"** state (with a resend
   form)
3. If found but `email_verify_expires` is in the past → show **"Link
   expired"** state (with a resend form, prefilled with their email)
4. If found and still valid → set `is_active = 1`, `email_verified_at = NOW()`,
   clear the token/expiry columns (single-use), log the action to
   `activity_log`, show **"Email verified!"** with a Go to Login button

**Design:** branded to match `login.php`/`register.php` — a small "V" logo
lockup above a centered white card, DM Serif Display headings, color-coded
status icon per state (green check / amber clock / rose X), a resend form
with proper labels/focus states, and a subtle fade-in animation
(`prefers-reduced-motion`-aware). Tested down to 320px mobile width.

Token lifetime: **24 hours** (`EMAIL_VERIFY_TTL_HOURS` constant in
`email_verification.php`).

---

## 5. Hostinger SMTP setup — domain, DNS, and credentials

### 5.1 Domain — do not confuse these two

> ⚠️ **The real domain is `ccsbsis.com` — one word, no dot.**
> `ccs.bsis.com` (a subdomain with a dot) is a **different, unrelated
> domain** whose MX points to Microsoft/Outlook, not Hostinger. Mixing these
> up produces an identical `535 authentication failed` error to an actually
> wrong password — cost significant debugging time before being caught via
> DNS lookup. Always double-check the exact spelling before pasting it
> anywhere.

Confirmed via public DNS (`nslookup -type=MX ccsbsis.com 8.8.8.8`):
```
ccsbsis.com   MX preference = 5,  mail exchanger = mx1.hostinger.com
ccsbsis.com   MX preference = 10, mail exchanger = mx2.hostinger.com
```

### 5.2 SMTP settings (non-secret — safe to copy)

| Setting | Value |
|---|---|
| SMTP Host | `smtp.hostinger.com` |
| Port | `465` |
| Encryption | `SSL` |
| Username | `adminsolutions@ccsbsis.com` |
| From email | `adminsolutions@ccsbsis.com` |
| From name | `VOICE — Siniloan Barangay System` |

### 5.3 `connection/mail_config.php` — create manually on the new machine

Git-ignored, does not exist in the repo. Create it fresh with this exact
content, filling in only the password:

```php
<?php
define('SMTP_HOST',       'smtp.hostinger.com');
define('SMTP_PORT',       465);
define('SMTP_ENCRYPTION', 'ssl');
define('SMTP_USERNAME',   'adminsolutions@ccsbsis.com');
define('SMTP_PASSWORD',   'PASTE_THE_MAILBOX_PASSWORD_HERE');   // ← only manual step
define('SMTP_FROM_EMAIL', 'adminsolutions@ccsbsis.com');
define('SMTP_FROM_NAME',  'VOICE — Siniloan Barangay System');
define('SMTP_DEBUG', false);
```

**Where to get the password:** hPanel → Emails → `adminsolutions@ccsbsis.com`
→ **⋮ → Change Password** if unknown. The mailbox itself is already correctly
configured on Hostinger's side (verified working) — this is purely "what
password do I type locally," not a hosting setup step. If reusing the exact
same mailbox as the original machine, its existing password already works.

---

## 6. Subfolder-path auto-detection (don't break this)

Verification links must work whether the app is served from a domain root
(production) or a subfolder (e.g. XAMPP's `htdocs/VOICE2` on localhost).
`appBasePath()` in `email_verification.php` detects this automatically from
`$_SERVER['SCRIPT_NAME']`:

```php
function appBasePath(): string {
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $base   = dirname(dirname($script));
    return $base === '/' || $base === '\\' ? '' : $base;
}
```

This is why links resolve correctly as
`http://localhost/VOICE2/connection/verify_email.php?token=...` locally, and
would automatically become `https://yourdomain.com/connection/verify_email.php?token=...`
at a domain root — no manual URL configuration needed. Don't hardcode a path
anywhere else in this flow, or subfolder deployments will 404.

---

## 7. End-to-end test checklist

1. Confirm `connection/phpmailer/src/` is present
2. Create `connection/mail_config.php` per §5.3
3. Go to `http://localhost/VOICE2/connection/register.php`, fill out the form
   with a **real email you can check**
4. Should show "Check your email"
5. Check that inbox — email should arrive from `adminsolutions@ccsbsis.com`
   with subject "Verify your VOICE account"
6. Click the link → should land on the branded "Email verified!" page →
   account activated (`is_active` flips to `1`)
7. Log in with that account — should succeed immediately, no staff approval
8. Separately test the failure states:
   - Visit `verify_email.php?token=anything-bogus` → "Invalid verification
     link" + resend form
   - Manually expire a token (`UPDATE users SET email_verify_expires =
     NOW() - INTERVAL 1 HOUR WHERE email = '...'`) and revisit its link →
     "Link expired" + resend form prefilled with the email
   - Submit the resend form → new email arrives, old link now invalid

If step 5 fails with `535 authentication failed`, it's almost always the
password in `mail_config.php` — re-verify it directly against hPanel, not
from memory (and double-check the domain spelling per §5.1).
