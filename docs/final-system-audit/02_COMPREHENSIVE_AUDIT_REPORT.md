# 02 — Comprehensive System Audit Report

**Audit Scope:** Full-stack PHP 8.2 / MySQL (`cathedral`) / Vanilla JS review of `C:\xampp\htdocs\IFILOCAL` across architecture, logic, RBAC, chapel isolation, workflows, database integrity, XSS/CSRF/IDOR security, and deployment readiness.

---

## 1. Executive Summary of Audit Findings

The Cathedral Management System has a solid custom MVC structure (`BaseController`, centralized `DashboardController`, parameterized `mysqli` queries across almost all data layers, `RateLimiter` protection on auth/public portals, and `EventConflictService` sanctuary/priest conflict checking).

However, deep code inspection and schema cross-checking uncovered **11 confirmed defects** across **Critical, High, Medium, and Deployment** severities — most notably a fatal SQL column mismatch (`al.performed_by` vs `al.performed_by_id`) on chapel-scoped reports, several multi-chapel isolation bypasses (`InquiryController`, `Inquiry`, `SacramentalController`, `DashboardController` bulletin actions, `DashboardExportService`), missing admin input validations (`AdminController`), a GET-based state change without CSRF (`inbox_preview.php`), unauthenticated HTTP exposure on `health_check.php`, and minor DOM `.innerHTML` XSS vectors.

All **11 actionable defects** were patched directly and verified. Remaining structural recommendations (database FK constraints, performance indexes, cleanup of legacy `tickets` / root `.sql`/`.html` artifacts) are documented below for release hygiene.

---

## 2. Detailed Findings Register

| ID | Severity | Area | Confirmed Defect | Root Cause & Impact | Disposition |
|---|---|---|---|---|---|
| **AUD-01** | **CRITICAL** | Reporting / Schema | Fatal SQL error (`Unknown column 'al.performed_by'`) when Chapel Admins open Print Financial Reports or export CSV | `audit_logs` stores `performed_by_id`, whereas `finance_report_print.php:77` & `DashboardExportService.php` joined on `al.performed_by = u.id` | **Fixed** (`al.performed_by_id = u.id`) |
| **AUD-02** | **HIGH** | Inquiries / Isolation | Mother Cathedral inquiries (`chapel_id IS NULL`) accessible & modifiable by branch Chapel Admins/Staff | `InquiryController.php:50` allowed `$inquiry['chapel_id'] === null`; `Inquiry.php` appended `OR chapel_id IS NULL` for branch queries | **Fixed** (strict chapel match vs `'cathedral'` isolation) |
| **AUD-03** | **HIGH** | Inquiries / Merch | Duplicate `fulfill_merch_reservation` clicks or replay can deduct stock twice | `InquiryController::fulfillMerchReservation()` lacked `!Completed` / `!is_archived` check and parsed `details` via regex before checking columns | **Fixed** (idempotency guard + `item_id`/`quantity` column priority) |
| **AUD-04** | **HIGH** | Sacramental / Isolation | Branch Chapel Staff could submit forged `scope=cathedral` or foreign `chapel_id` on sacramental POST/intake | `SacramentalController.php:77,549` only locked `Chapel Admin`, omitting `Staff` | **Fixed** (enforced for all non-global roles `!in_array(role, ['Admin','Cathedral Admin'])`) |
| **AUD-05** | **HIGH** | Bulletin / Authorization | `Priest`/`Pastor` treated as global Admin in `handleBulletinActions()`, permitting cross-chapel edits/deletions | `DashboardController.php:182,193` included `'Priest','Pastor'` in global bypass list | **Fixed** (restricted global bypass to `'Admin','Cathedral Admin'`) |
| **AUD-06** | **HIGH** | Admin / Validation & XSS | User CRUD lacked role whitelist and active duplicate email check; Parish Contact Info accepted unvalidated `map_embed_url` (`javascript:` URI possible) | `AdminController.php:54-160,377` skipped `$allowedRoles`, email uniqueness (explaining historical `qa_staff@test.local` duplicates), and HTTPS scheme verification | **Fixed** (role whitelist, active email check, `https://` + host check) |
| **AUD-07** | **HIGH** | Deploy / Info Leak | `/health_check.php` ran full DB, path, PHP & table diagnostics over HTTP without authentication | Root `health_check.php` had no `PHP_SAPI` or session role check | **Fixed** (gated to CLI or authenticated `Admin`, returning `403` otherwise) |
| **AUD-08** | **MEDIUM** | Reporting CSV | `DashboardExportService::streamReportCsv()` leaked Cathedral `merchandise_sales`, unscoped `certificate_issuances`, `tickets`, and `audit_logs` to Chapel Admins | Export queries omitted `$chapelId` predicates in Sections 1, 3, 9, 11, 12, 14 | **Fixed** (aligned strictly with `finance_report_print.php` scoping) |
| **AUD-09** | **MEDIUM** | Notifications / CSRF | `inbox_preview.php?action=mark_read` mutated DB state via `GET` without CSRF; Cathedral Staff got unfiltered `null` scope | `inbox_preview.php:88,156` & `inbox-dropdown.js:286` used GET query param without CSRF token | **Fixed** (`POST` + CSRF check backend/JS + `'cathedral'` Staff scope) |
| **AUD-10** | **MEDIUM** | Finance, Chat & Portal | `logDonation()` lacked method whitelist/length limits; `ChatController` exposed raw `$e->getMessage()` and missed `canAccessRoom` on `mark_read`; `PortalController` used raw `$_SERVER['PHP_SELF']` | Missing input/output boundary hardening in `FinanceController.php`, `ChatController.php`, `PortalController.php` | **Fixed** (method whitelist, `error_log` suppression, room check, `basename()` allowlist) |
| **AUD-11** | **MEDIUM** | Client DOM XSS & Docker | `dashboard-availability.js` & `dashboard-profile.js` interpolated event titles/filenames raw into `.innerHTML`; `.dockerignore` omitted `guest_*.html`, `tests/`, `screenshots/` | Template literal interpolation into `.innerHTML` + loose Docker build context exclusions | **Fixed** (`escapeAvailHtml()` / filename escaping + updated `.dockerignore`) |

---

## 3. Structural & Data Integrity Notes (Non-Blocking / Advisory)

1. **Zero SQL Foreign Keys & Minimal Secondary Indexes:** Across all 38 database tables, 0 foreign key constraints are declared, and only `audit_logs` carries secondary indexes (`PRIMARY` keys only on the remaining 37 tables). Referential integrity currently passes (`0` orphan `pastor_id`, `0` orphan `certificate_issuances`, `0` orphan `sacramental_records`), enforced at the PHP application layer. Adding composite indexes (`events(start_time, chapel_id, is_archived)`, `users(email)`, `donations(reference_id, is_archived)`) is recommended before high-volume scaling.
2. **Legacy Tables & Historical Data Hygiene:**
   - **Legacy Data Observed in `cathedral` DB:** `users` contains 7 historical `rejected` QA accounts sharing `qa_staff@test.local`; `events` contains 10 legacy rows (`event_id` 174–222) with empty `event_type`; 1 `sacramental_records` row has empty `reference_id`; 4 past `Pending` events exist (`start_time < NOW()` — accurately surfaced by Schedule Audit).
   - **Legacy Disconnected Module:** `views/partials/dashboard/ajax_renderer.php` removed `case 'tickets'` per revision #14, though `TicketController.php`, `Ticket.php`, and `BaseController` permission `'can_tickets'` still remain in the codebase.
   - **Root Working Directory Artifacts:** Dev test files (`cookies.txt`, `guest_ajax.html`, `guest_page.html`, `if0_43226821_cathedral.sql`) exist in `C:\xampp\htdocs\IFILOCAL`. `.htaccess` already blocks `.sql` files and `.dockerignore` excludes `.sql`, `cookies.txt`, and `guest_*.html` from Docker builds; deleting stray `.html`/`.sql`/`cookies.txt` files from web root before bare-metal Apache deployment is advised.
3. **Production Runtime Check (`config/config.php`):** Ensure production deployments override DB credentials via `config/config.local.php` or environment variables and keep `APP_DEBUG` disabled (`false`) so `dev_login.php` stays locked at `403`.
