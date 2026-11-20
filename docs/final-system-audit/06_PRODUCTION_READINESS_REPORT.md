# 06 — Production Readiness Report

**System:** Cathedral Management System (`C:\xampp\htdocs\IFILOCAL`)  
**Evaluation Date:** 2026-11-20  
**Overall Verdict:** **CONDITIONALLY READY FOR PRODUCTION** *(All code defects resolved & 36/36 automated checks pass; complete the 4 operational pre-launch hygiene items below during cutover)*

---

## 1. Readiness Assessment by Pillar

| Domain | Status | Evidence Summary |
|---|---|---|
| **Code Compilation & Syntax** | **READY** | `119/119` PHP files lint cleanly (`php -l`: 0 errors) |
| **Role-Based Access & Chapel Isolation** | **READY** | All 7 roles enforced cleanly; Cathedral inquiry/record/report/bulletin/staff isolation leaks patched and verified |
| **Event Scheduling & Service Requests** | **READY** | Sanctuary & cross-venue priest locking (`FOR UPDATE`), Mass Intention/Certificate exemptions, and server-side fee recomputation verified |
| **Sacramental Registry & Certificates** | **READY** | Centralized Cathedral search + strict Chapel write isolation, duplicate check fix, escaped printable canonical certificates |
| **Finance, Receipts & Reporting** | **READY** | `audit_logs.performed_by_id` crash fixed in PDF/Print & CSV exports; method whitelist, atomic merch stock, and voiding verified |
| **Web Security Hardening** | **READY** | CSRF on forms & AJAX (`inbox_preview.php` fixed), IP/user rate limiting, `https://` map URL guard, escaped `.innerHTML` sinks, `health_check.php` gated |

---

## 2. Pre-Deployment Cutover Checklist (Operations)

Complete these operational steps when deploying to the live production server / container:

1. **Remove Non-Production Root Artifacts on Live Bare-Metal Web Root:**  
   Verify `cookies.txt`, `guest_ajax.html`, `guest_page.html`, and `if0_43226821_cathedral.sql` are deleted from the live public document root (note: `.dockerignore` already excludes `.sql`, `cookies.txt`, `guest_*.html`, `scratch/`, and `docs/` from Docker container builds).
2. **Production Environment Secrets & Debug Flag:**  
   Ensure `config/config.local.php` (or container env vars) uses dedicated least-privilege MySQL credentials (not `root` with blank password) and **`APP_DEBUG` is unset or `false`** so `dev_login.php` remains blocked at `403 Forbidden`.
3. **Database Migration on Live DB & Legacy Row Cleanup:**  
   - Run `php tools/migrate_fk_and_indexes.php` during deployment cutover to apply the same **44 indexes** and **35 InnoDB foreign keys** on the production MySQL database (`--dry-run` supported first).
   - Review the 7 historical `rejected` QA user rows (`qa_staff@test.local`), 10 legacy empty `event_type` rows, 4 past-due `Pending` events, and the 1 existing liturgical collision group flagged by `health_check.php` (`165/167` checks passed; `98.8%`).
4. **Runtime PHP Extensions (`gd`), SMTP & SMS Live Credentials Test:**  
   Verify `extension=gd` is enabled in production `php.ini` / container image (noted missing in local XAMPP CLI `health_check.php`), and after setting production SMTP (`parish_contact_info` / per-chapel `chapels` overrides) and Semaphore/iProgTech keys (`SmsService`), run a single live confirmation email/SMS test from the Settings hub.

---

## 3. Audit Artifact Suite Index

1. [`01_SYSTEM_INVENTORY.md`](./01_SYSTEM_INVENTORY.md)
2. [`02_COMPREHENSIVE_AUDIT_REPORT.md`](./02_COMPREHENSIVE_AUDIT_REPORT.md)
3. [`03_EDGE_CASE_AND_SCENARIO_MATRIX.md`](./03_EDGE_CASE_AND_SCENARIO_MATRIX.md)
4. [`04_FIXES_AND_PATCH_LOG.md`](./04_FIXES_AND_PATCH_LOG.md)
5. [`05_REGRESSION_TEST_RESULTS.md`](./05_REGRESSION_TEST_RESULTS.md)
6. [`06_PRODUCTION_READINESS_REPORT.md`](./06_PRODUCTION_READINESS_REPORT.md)
