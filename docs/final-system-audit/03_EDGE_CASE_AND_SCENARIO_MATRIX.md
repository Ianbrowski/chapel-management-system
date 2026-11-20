# 03 — Edge-Case, Workflow & Scenario Matrix

This matrix documents end-to-end functional scenarios, failure modes, RBAC/isolation checks, and boundary conditions verified across modules in `C:\xampp\htdocs\IFILOCAL`.

---

## 1. Authentication, Session & Security Scenarios

| ID | Scenario / Edge Case | Expected Behavior | Verified Outcome | Module / File |
|---|---|---|---|---|
| SEC-01 | Admin logs into public `login.php` | Rejected cleanly; must use dedicated `admin_login.php` gateway | Enforced (`Admin` blocked on standard portal) | `AuthController.php`, `admin_login.php` |
| SEC-02 | Brute-force login / registration flood | Blocked by sliding-window IP/user rate limiter with retry wait time | `RateLimiter::tooManyAttempts` locks cleanly | `AuthController.php`, `register.php`, `RateLimiter.php` |
| SEC-03 | Account with `pending`, `rejected`, or `disabled` status tries to sign in | Denied with appropriate status message | Enforced before session establishment | `AuthController.php` |
| SEC-04 | CSRF token omitted, empty, or forged on POST forms / AJAX | Rejected with `403`/redirect + security expiration flash | `hash_equals` check enforced across controllers & `inbox_preview.php` (fixed) | `BaseController.php`, `inbox_preview.php` |
| SEC-05 | Cross-site open redirect via `?redirect=` after login | Only allowlisted internal sections (`request_event`, `request_certificate`, etc.) honored | Safe internal whitelist enforced | `AuthController.php`, `logout.php` |
| SEC-06 | Unauthenticated HTTP access to `/health_check.php` | Blocked with `403 Forbidden` (CLI or `Admin` session only) | Fixed in hardening pass | `health_check.php` |
| SEC-07 | Arbitrary role string or duplicate active email submitted on User Create/Update | Rejected via role whitelist and case-insensitive active email check | Fixed in `AdminController::handleUserActions` | `AdminController.php` |
| SEC-08 | `javascript:` URI entered into Parish `map_embed_url` | Rejected unless `https://` scheme with valid host | Fixed in `AdminController::handleParishInfoActions` | `AdminController.php` |
| SEC-09 | `dev_login.php` accessed on non-localhost or `APP_DEBUG != 'true'` | Returns `403 Forbidden` immediately | Verified gate (`127.0.0.1`/`::1` + `APP_DEBUG`) | `dev_login.php` |

---

## 2. Role-Based Access & Cross-Chapel Isolation Matrix

| Role | Users / Chapels | Cathedral Sacraments | Branch Chapels | Inquiries Inbox | Event Approvals | Donations & Merch | Bulletin Management |
|---|---|---|---|---|---|---|---|
| **Admin** | Full CRUD | Read / Write / Delete | All Chapels | Global | Global Approve/Reject | Full + Void | Global + Reorder |
| **Cathedral Admin** | Non-Admin CRUD | Read / Write / Delete | All Chapels | Global | Global Approve/Reject | Full + Void | Global + Reorder |
| **Chapel Admin** | Denied (except assigned chapel edit) | **Denied** (cannot read/edit/certify) | Own Chapel Only | Own Chapel Only *(Fixed)* | Own Chapel Only | Own Chapel Donations (No Void) | Own Chapel Only |
| **Staff (Chapel)** | Denied | **Denied** *(Fixed POST bypass)* | Own Chapel Create/Edit (No Delete) | Own Chapel Only *(Fixed)* | Denied (`events` calendar scoped) | Log Own Chapel Donations (No Void) | Denied |
| **Staff (Cathedral)** | Denied | Cathedral Scope Only | **Denied** | Cathedral Only *(Fixed)* | Denied (`events` calendar scoped) | Log Donations (No Void) | Denied |
| **Priest / Pastor** | Denied | View Only / Schedules | View Assigned / Global Events Read | Denied | Denied | Denied | Own Scope Only *(Fixed bypass)* |
| **Member** | Own Profile Only | Denied | Request Submission Only | Own Email Inquiries Only | Submit Request Only | View Catalog / Reserve | View Only |
| **Guest** | Denied | Denied | Denied | Denied | Preview Schedule Only (masked titles) | Denied | View Only |

---

## 3. Scheduling, Services & Conflict Locking Edge Cases

| ID | Scenario / Edge Case | Expected Behavior | Verified Outcome | Module / File |
|---|---|---|---|---|
| SCH-01 | Morning Baptism (`09:00`) & Afternoon Baptism (`14:00`) on same date/venue | Allowed — two separate slots per day | Verified via `EventConflictService` & `get_day_schedule.php` | `EventConflictService.php`, `get_day_schedule.php` |
| SCH-02 | Double-booking same venue within overlapping time window | Rejected (`has_conflict = true`); slot card disabled in UI | Verified backend + UI block | `EventConflictService.php`, `dashboard-availability.js` |
| SCH-03 | Assigning same Priest to concurrent events at Cathedral and Chapel 3 | Rejected (`pastor_conflict = true`) even across different venues | Verified cross-venue clergy lock | `EventConflictService.php` |
| SCH-04 | Editing an existing event at its current date/time/priest | Excludes self (`excludeId`) — no false self-conflict | Verified | `EventConflictService.php` |
| SCH-05 | Submitting Mass Intention (`service_id=4`), Thanksgiving (`7`), or Certificate (`8`) on a booked day | Bypass sanctuary lock — unlimited / parallel processing allowed | Verified exemption in both `EventConflictService` & `get_day_schedule.php` | `EventConflictService.php`, `get_day_schedule.php` |
| SCH-06 | Guest or Member queries `get_day_schedule.php` for availability | Receives slot/status (`Booked`/`Pending`) with anonymized title (`Reserved Sanctuary Slot`) | Private parishioner titles masked cleanly | `get_day_schedule.php` |
| SCH-07 | Tampering client-side `total_fee` / `service_fee` POST inputs on request submission | Server recalculates fee strictly from `services` + `service_variants` + `service_additional_charges`; rolls back transaction on validation error | Transactional fee integrity preserved | `EventController.php`, `ServiceModel.php` |
| SCH-08 | Stored HTML/script payload inside Event Title when rendered in Availability Inspector | Escaped via `escapeAvailHtml()` before `.innerHTML` assignment | Fixed DOM XSS vector | `dashboard-availability.js` |

---

## 4. Sacramental Records & Certificate Centralization

| ID | Scenario / Edge Case | Expected Behavior | Verified Outcome | Module / File |
|---|---|---|---|---|
| REC-01 | Chapel 3 encodes Baptism record; Cathedral searches records | Cathedral `scope='cathedral'` centralized search finds Cathedral + all branch chapels | Verified (`search(['scope'=>'cathedral'])` returns both) | `SacramentalRecord.php` |
| REC-02 | Chapel 1 / Chapel 3 searches or opens certificate preview for Cathedral record | Strictly blocked (`scope='chapel'` filtered; `sacramental_certificate.php` checks chapel match) | Enforced in search, `canAccessExistingRecord()`, & certificate viewer | `SacramentalRecord.php`, `SacramentalController.php`, `sacramental_certificate.php` |
| REC-03 | Chapel Staff submits tampered `scope=cathedral` or `chapel_id=2` on POST | Server overwrites scope/chapel to authenticated user's assigned chapel | Fixed (`!$isGlobalAdmin` instead of `Chapel Admin` only) | `SacramentalController.php` |
| REC-04 | Duplicate Baptism/Sacrament entry (same type, name, date) | `checkDuplicate()` warns/prevents unintended duplicate archival entry | Verified | `SacramentalRecord.php` |

---

## 5. Finance, Donations & Merchandise Store Edge Cases

| ID | Scenario / Edge Case | Expected Behavior | Verified Outcome | Module / File |
|---|---|---|---|---|
| FIN-01 | Logging donation with `<= 0` amount, future date, or invalid payment method | Rejected (`amount > 0`, `date <= today`, method whitelisted) | Fixed method whitelist + string clamping | `FinanceController.php` |
| FIN-02 | Voiding a donation without mandatory reason or as non-Admin | Rejected (`Admin`/`Cathedral Admin` only; empty reason blocked; excluded from `getPaidAmountsMap`) | Verified | `FinanceController.php`, `Donation.php`, `Event.php` |
| FIN-03 | Purchasing / fulfilling merchandise where `quantity > stock_quantity` or `<= 0` | Transaction aborted with stock error; stock unchanged | `SELECT ... FOR UPDATE` inside DB transaction | `Merchandise.php` |
| FIN-04 | Rapid double-click or replay of `fulfill_merch_reservation` on an inquiry | Second attempt rejected (`status === 'Completed' || is_archived`), preventing double stock deduction | Fixed in `InquiryController::fulfillMerchReservation` | `InquiryController.php` |
| FIN-05 | Chapel Admin exports CSV detailed report (`export=report_csv`) | Sees only their chapel's donations, records, events, certificates, tickets, audit activity; global merch ledger excluded | Fixed in `DashboardExportService::streamReportCsv` | `DashboardExportService.php` |

---

## 6. Communications (Inquiries, Chat, Email/SMS)

| ID | Scenario / Edge Case | Expected Behavior | Verified Outcome | Module / File |
|---|---|---|---|---|
| COM-01 | Chapel Admin/Staff attempts to modify, archive, or fulfill a Mother Cathedral (`chapel_id IS NULL`) inquiry | Blocked (`Unauthorized`) & hidden from chapel inbox lists/badges | Fixed in `InquiryController` and `Inquiry` model | `InquiryController.php`, `Inquiry.php` |
| COM-02 | Staff triggers internal chat `mark_read` for a room they are not a participant of | Guarded by `canAccessRoom($roomId, $userId)` | Fixed in `ChatController::ajaxMarkRead` | `ChatController.php` |
| COM-03 | Cross-site image/GET trigger against `inbox_preview.php?action=mark_read` | Blocked (`405` on GET, `403` without CSRF POST token) | Fixed across backend & `inbox-dropdown.js` | `inbox_preview.php`, `inbox-dropdown.js` |
