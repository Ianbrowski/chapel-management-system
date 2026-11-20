# 05 — Regression Test Results

**Execution Tool:** Non-Destructive PHP CLI Regression & Security Suite (`regression_suite.php`) running against live MySQL `cathedral` DB (`127.0.0.1:3307`) inside rolled-back transactions (plus explicit temp-row cleanup for standalone `recordSale` lock testing).  
**Execution Status After Final Code Edit:** `EXIT CODE 0` — **36 Passed / 0 Failed / 0 Skipped**

---

## Summary Metrics

| Category | Checks Run | Passed | Failed | Skipped |
|---|---|---|---|---|
| **A. Syntax, Environment, Schema & Security Guards** | 11 | 11 | 0 | 0 |
| **B. 7-Role RBAC Matrix, CSRF & RateLimiter** | 9 | 9 | 0 | 0 |
| **C. Inquiry Isolation & Atomic Merchandise Stock** | 4 | 4 | 0 | 0 |
| **D. Event Scheduling, Conflict Locks, Services & Finance** | 10 | 10 | 0 | 0 |
| **E. Sacramental Record Centralization & Duplicate Safety** | 2 | 2 | 0 | 0 |
| **Total** | **36** | **36** | **0** | **0** |

---

## Complete Test Case Ledger

| Test ID | Group | Description | Result | Observed Evidence |
|---|---|---|---|---|
| `A1` | Env/Syntax | Zero PHP syntax errors across entire codebase | **PASS** | `119 files linted; errs=` (exit code 0 on all files) |
| `A2` | Env/TZ | Default timezone set to `Asia/Manila` | **PASS** | `Asia/Manila` |
| `A3` | Env/DB | Database connection active and UTF-8 charset | **PASS** | `utf8mb4` |
| `A4` | Schema | `audit_logs` user-id column verification | **PASS** | `performed_by_id` confirmed in schema |
| `A5` | Schema | `donations` schema includes voiding and reference columns | **PASS** | `void_reason`, `reference_id`, `donor_ref_id`, `date_received=date` |
| `A6` | Deploy | `.dockerignore` excludes SQL dumps, cookies, guest html, scratch, docs | **PASS** | Verified patterns present in `.dockerignore` |
| `A7` | Deploy/Sec | `health_check.php` blocks non-CLI non-Admin HTTP access | **PASS** | `PHP_SAPI !== 'cli'` & `403` guard verified |
| `A8` | Security | `PortalController` sanitizes redirect target against basename whitelist | **PASS** | `allowedTargets` + `basename()` verified |
| `A9` | Security | `ChatController` suppresses raw `$e->getMessage()` from HTTP JSON error | **PASS** | Server-side `error_log` active; raw error absent |
| `A10` | Security | `AdminController` enforces role whitelist, email uniqueness & HTTPS map URL | **PASS** | `$allowedRoles`, `LOWER(TRIM(email))`, `!== 'https'` |
| `A11` | Schema/SQL | `finance_report_print.php` & `DashboardExportService.php` use `audit_logs.performed_by_id` | **PASS** | Zero invalid `al.performed_by ` references remain |
| `B_Admin` | RBAC | Permission matrix strictly enforced for role `Admin` | **PASS** | All capability flags exact match |
| `B_Cathedral_Admin` | RBAC | Permission matrix strictly enforced for role `Cathedral Admin` | **PASS** | All capability flags exact match |
| `B_Chapel_Admin` | RBAC | Permission matrix strictly enforced for role `Chapel Admin` | **PASS** | Scoped approval/records/donations/chapel sched |
| `B_Staff` | RBAC | Permission matrix strictly enforced for role `Staff` | **PASS** | Maker-checker boundaries verified |
| `B_Priest` | RBAC | Permission matrix strictly enforced for role `Priest` | **PASS** | Clergy schedule/bulletin access isolated |
| `B_Member` | RBAC | Permission matrix strictly enforced for role `Member` | **PASS** | Limited portal capabilities verified |
| `B_Guest` | RBAC | Permission matrix strictly enforced for role `Guest` | **PASS** | All write/admin flags strictly `false` |
| `B8` | Security/CSRF | CSRF accepts valid token and rejects missing/wrong tokens | **PASS** | `hash_equals` check passed & forged tokens rejected |
| `B9` | Security/RateLimit | `RateLimiter` triggers at threshold and clears cleanly | **PASS** | Threshold locked at 3 attempts, unlocked after `clear()` |
| `C1` | Inquiries | Create isolated Cathedral and Chapel 3 inquiries inside transaction | **PASS** | Both records inserted cleanly inside rolled-back tx |
| `C2` | Isolation/Inquiry | Chapel 3 inquiry query returns Chapel 3 record and excludes Cathedral record | **PASS** | `catInqId` absent from Chapel 3 query results |
| `C3` | Isolation/Inquiry | Cathedral inquiry query returns Cathedral record and excludes Chapel 3 record | **PASS** | `ch3InqId` absent from Cathedral query results |
| `C4b` | Merchandise | Atomic stock guard (`FOR UPDATE`) blocks `qty > stock` & `qty <= 0`, deducts valid sale | **PASS** | Initial 3 -> valid sale 2 -> `remStock=1` (temp rows cleaned) |
| `D1` | Scheduling | Create Cathedral Morning Baptism for conflict baseline | **PASS** | `event_id=229` created inside rolled-back tx |
| `D2` | Scheduling | Same venue overlapping Morning time detects conflict | **PASS** | `conflict=true` (`Venue`) |
| `D3` | Scheduling | Cathedral Afternoon slot remains open when Morning is booked | **PASS** | `conflict=false` |
| `D4` | Scheduling | Chapel 3 Morning remains open when Cathedral Morning is booked | **PASS** | `conflict=false` |
| `D5` | Scheduling/Pastor | Same priest double-booked across Cathedral & Chapel 3 triggers conflict | **PASS** | `{"conflict":true,"type":"Priest",...}` |
| `D6` | Scheduling | Editing same event excludes itself via `excludeId` | **PASS** | `evCatMorning=229 {"conflict":false}` |
| `D7` | Scheduling/Exempt | Mass Intentions (`Thanksgiving`) and Certificates bypass sanctuary lock | **PASS** | `conflict=false` for both |
| `D8` | Services | Active liturgical service catalog loads with categories & services | **PASS** | `categories=4, services=8` |
| `D9` | Finance/Events | `Event::getPaidAmountsMap` accurately sums `EV-xxxx` donation payments | **PASS** | `500.00` mapped accurately |
| `D10` | Finance/Void | Voided donation excluded from active paid amounts map | **PASS** | Paid sum resets to `0.00` upon void |
| `E1` | Records/Central | Cathedral search sees Cathedral + Chapel records; Chapel search isolates Chapel 3 | **PASS** | Dual-visibility vs isolation verified |
| `E2` | Records/Dup | `SacramentalRecord::checkDuplicate` detects matching Baptism record | **PASS** | Duplicate detected cleanly inside tx |

*Note:* All database mutations created during `C1–C3`, `D1–D10`, `E1–E2`, and `C4b` were rolled back or explicitly deleted immediately upon test completion, leaving production/staging tables untouched.

---

## Built-in Diagnostics Suite (`php health_check.php`) Verification

Executed immediately after final patches & regression suite:
- **Overall Score:** `98.8% (165/167 Checks Passed)` across 112 scanned files, 20 tables, 90 dynamic queries, 5 integrity checks, 11 core classes, and 6 writable directories (`WARNINGS: 0`).
- **2 Flagged Items:**
  1. `Liturgical Slot Collision Check`: 1 pre-existing overlapping collision group detected in the live `events` table (surfaced for staff review via Schedule Audit).
  2. `PHP Extension 'gd': Missing` in local XAMPP PHP CLI runtime (verify `extension=gd` is enabled in production PHP runtime / `Dockerfile`).
