# IFI Cathedral Management System — Production Deployment Guide

This guide details the step-by-step procedure for deploying the **Cathedral Management System** to a live production server.

---

## 1. System Requirements

| Component | Minimum Specification | Recommended Specification |
| :--- | :--- | :--- |
| **Operating System** | Windows Server 2019+ / Ubuntu 22.04 LTS | Windows Server 2022 / Ubuntu 24.04 LTS |
| **Web Server** | Apache 2.4 (`mod_rewrite`, `mod_headers`, `mod_expires`) | Apache 2.4 with HTTP/2 and SSL/TLS |
| **PHP Engine** | PHP 8.1+ | PHP 8.2 or 8.3 |
| **PHP Extensions** | `mysqli`, `mbstring`, `openssl`, `gd`, `zip`, `fileinfo` | All standard extensions enabled |
| **Database** | MariaDB 10.4+ or MySQL 8.0+ (InnoDB Engine) | MariaDB 10.6+ LTS |

---

## 2. Pre-Deployment Configuration

### A. Environment Configuration (`config/config.local.php`)
Copy `config/config.local.example.php` to `config/config.local.php` and supply production credentials:

```php
<?php
return [
    // Database Connection
    'host' => '127.0.0.1',
    'port' => 3306,
    'user' => 'cathedral_admin',
    'pass' => 'StrongProductionPasswordHere!#',
    'db'   => 'cathedral',

    // Timezone
    'timezone' => 'Asia/Manila',

    // Production Mailer (Cathedral Office SMTP)
    'smtp_host'      => 'smtp.gmail.com', // Or parish mail server
    'smtp_user'      => 'cathedral.office@example.org',
    'smtp_pass'      => 'your-app-specific-password',
    'smtp_secure'    => 'tls',
    'smtp_port'      => 587,
    'smtp_from_name' => 'IFI Cathedral Administration',

    // SMS Gateway (Semaphore PH)
    'sms_provider'    => 'semaphore', // Set to 'mock' for local dev
    'sms_api_key'     => 'your_semaphore_api_key',
    'sms_sender_name' => 'SEMAPHORE',
    'sms_base_url'    => 'https://api.semaphore.co/api/v4/messages',
    'sms_country'     => 'PH',
];
```

### B. Production Debugging & Error Suppression
In production, ensure environment variables or `.htaccess` disable error display:
* Set `APP_DEBUG=false` in the server environment.
* `config.php` automatically suppresses `display_errors` and logs all exceptions to `storage/logs/php_errors.log`.

---

## 3. Database Deployment & Initialization

1. Create the database:
   ```sql
   CREATE DATABASE `cathedral` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
   ```
2. Import the clean, audited, foreign-key verified baseline snapshot:
   ```bash
   mysql -u cathedral_admin -p cathedral < backups/post_phase1_fk_snapshot_20261031.sql
   ```
3. Verify that the 14 InnoDB foreign key constraints are active:
   * `tickets` (`chapel_id` -> `chapels`, `assigned_to` -> `users`)
   * `certificate_issuances` (`record_id` -> `sacramental_records`, `issued_by` -> `users`)
   * `events` (`chapel_id` -> `chapels`, `pastor_id` -> `users`, `requested_by` -> `users`)
   * `donations` (`chapel_id` -> `chapels`, `logged_by` -> `users`)
   * `sacramental_records` (`chapel_id` -> `chapels`, `encoded_by` -> `users`)
   * `users` (`chapel_id` -> `chapels`)
   * `public_inquiries` (`chapel_id` -> `chapels`)

---

## 4. Web Server Hardening & Directory Permissions

### A. Directory Permissions
Ensure the web server process has write access only to designated writable directories:
* `backups/` — Read/Write (Database archive downloads and ZIP storage)
* `storage/logs/` — Read/Write (PHP error logs and audit dumps)
* `assets/uploads/` — Read/Write (Chapel banners and profile pictures)
* All PHP source code directories (`app/`, `config/`, `views/`) should be **read-only** for the web server user.

### B. Apache `.htaccess` Verification
Confirm that [`.htaccess`](file:///c:/xampp/htdocs/sukaniggadih/ORIGINAL/.htaccess) contains:
* Web-root blocking for development scripts:
  ```apache
  RewriteRule ^(dev_login|test_archive|health_check|debug_event|check_schema)\.php$ - [F,NC]
  ```
* Direct directory access blocking:
  ```apache
  RewriteRule ^(config|tools|tmp|backups|app|views|src|scratch)/ - [F,NC]
  ```
* HTTP Security Headers active:
  ```apache
  Header always set X-Content-Type-Options "nosniff"
  Header always set X-Frame-Options "SAMEORIGIN"
  Header always set Referrer-Policy "strict-origin-when-cross-origin"
  ```

---

## 5. Automated Backup Schedule (Cron / Windows Task Scheduler)

### Windows Server (Task Scheduler)
Schedule a daily task running at 01:00 AM:
```powershell
php C:\xampp\htdocs\sukaniggadih\ORIGINAL\tools\backup.php daily
```

### Linux Server (Crontab)
```cron
# Run daily database snapshot at 1:00 AM
0 1 * * * php /var/www/cathedral/tools/backup.php daily > /dev/null 2>&1

# Run weekly full recovery image on Sunday at 2:00 AM
0 2 * * 0 php /var/www/cathedral/tools/backup.php weekly > /dev/null 2>&1
```

---

## 6. Post-Deployment Smoke Verification

Run the automated test harnesses directly from the server CLI to verify end-to-end operational health:

```bash
# 1. Verify security and bug regressions (23 assertions)
php scratch/verify_fixes.php

# 2. Verify all cross-module workflows (19 assertions)
php scratch/test_phase2_workflows.php
```

Both suites should report **100% PASS** before opening the platform to parish staff and the public.

