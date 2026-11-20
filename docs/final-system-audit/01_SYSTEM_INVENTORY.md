# 01 — Complete System Inventory

**System:** Cathedral Management System (IFI Cathedral of St. Michael the Archangel & Subsidiary Chapels)  
**Active Repository / Web Root:** `C:\xampp\htdocs\IFILOCAL`  
**Runtime Stack:** PHP 8.2 (Custom MVC, `mysqli`), MySQL / MariaDB (`cathedral` schema), Apache / Docker (`php:8.2-apache`), Vanilla JS + Chart.js  
**Database Verified Scope:** 38 Tables + 1 View (`v_sacramental_records`), 0 Declared Foreign Key Constraints (`FOREIGN KEYS: none`)

---

## 1. Root Web Entry Points & Deployment Files

| File / Path | Purpose | Auth / Access Control |
|---|---|---|
| `index.php` | Public parish portal, mass schedules, history, leadership, login entry | Public |
| `login.php` | General user login handler / entry point | Public (blocks `Admin` role) |
| `admin_login.php` | Dedicated Super Admin login gateway (`noindex`, `DENY`, CSRF + double token) | Public gateway for `Admin` only |
| `register.php` | Public parishioner (`Member`) self-registration (pending approval) | Public + CSRF + RateLimiter (`5/300s`) |
| `logout.php` | Session destruction via `AuthController::logout()` + redirect whitelist | Authenticated / Session |
| `dashboard.php` | Main workspace SPA/multi-section router & POST dispatcher | Authenticated (`Guest` allowed limited read sections) |
| `guest_access.php` | Guest session initializer (`role='Guest'`) | Public |
| `public_requests.php` | Public / Member service inquiry submission portal | Public + CSRF + RateLimiter |
| `services.php` | Standalone public services catalog entry | Public |
| `baptism_form.php`, `confirmation_form.php`, `marriage_form.php`, `funeral_form.php` | Canonical sacramental intake form wrappers | Authenticated (`can_create_records` / staff & admins) |
| `dev_login.php` | Development quick role switcher | Restricted: `127.0.0.1`/`::1` AND `APP_DEBUG=true` (`403` otherwise) |
| `health_check.php` | Full 8-part diagnostics & integrity suite | Hardened: CLI SAPI OR authenticated `Admin` session (`403` otherwise) |
| `Dockerfile`, `.htaccess`, `.dockerignore` | Container build, security headers/rewrites, build exclusions | Build / Web Server Config |
| Stray workspace/dev artifacts in root | `cookies.txt`, `guest_ajax.html`, `guest_page.html`, `if0_43226821_cathedral.sql` | `.sql` blocked by `.htaccess`; excluded from Docker image via `.dockerignore` |

---

## 2. Backend MVC Layers (`app/`)

### 2.1 Controllers & JSON Endpoints (`app/controllers/` — 22 files)

| File | Responsibility | Notes / Key Guards |
|---|---|---|
| `BaseController.php` | Session bootstrap, CSRF generation/validation, RBAC `$permissions`, chapel access helpers | 7 roles: `Admin`, `Cathedral Admin`, `Chapel Admin`, `Staff`, `Priest`, `Member`, `Guest` |
| `AuthController.php` | Login, lockout, fingerprint, safe `?redirect=`, logout, password reset helpers | `makeFingerprint()`, `RateLimiter` lockout, role portal separation |
| `DashboardController.php` | Central POST router & GET `loadViewData($section)` loader | Dispatches to domain controllers, handles Bulletin, CSV report export |
| `AdminController.php` | User CRUD, Chapel CRUD & SMTP overrides, Parish Info & Mass Schedules | Hardened: role whitelist, active email uniqueness, HTTPS `map_embed_url` |
| `EventController.php` | Event creation, request submission, approval/rejection, payment stamping, calendar JSON | Conflict detection, server-side fee recalculation, rollback on error |
| `SacramentalController.php` | Canonical Baptism, Wedding, Funeral, Confirmation, Communion records + certificates | Chapel isolation for non-global roles (`Admin`/`Cathedral Admin` only global) |
| `FinanceController.php` | Donation ledger/voiding/receipts, Merchandise catalog & POS sales | Method whitelist + string sanitization on `logDonation()`, Admin-only `void` |
| `InquiryController.php` | Public inquiry triage, status updates, archive, one-click merch reservation fulfillment | Fixed cross-chapel Cathedral `null` bypass & duplicate merch fulfillment guard |
| `ServiceController.php` | Admin CRUD for service categories, services, variants, add-ons, cert types | `can_manage_services` permission required |
| `TicketController.php` | Legacy internal support ticket POST handler | View partial `case 'tickets'` removed in `ajax_renderer.php` (#14) |
| `ChatController.php` | Internal staff/clergy channels & direct messaging AJAX API | Room access checks on read/send/poll/mark_read; no exception leaks |
| `PortalController.php` | Public inquiry portal submission + sanitized redirect | CSRF + RateLimiter + whitelisted redirect basename |
| `HomeController.php` | Public landing view loader (`parish_contact_info`, mass schedules, chapels) | Read-only public data |
| `authenticate.php`, `chat.php`, `inbox_preview.php`, `notifications.php` | HTTP endpoint wrappers / notification & inbox preview JSON dispatchers | `inbox_preview.php` hardened (`POST` + CSRF on `mark_read`, Cathedral Staff scope) |
| `get_day_schedule.php`, `get_priest_availability.php`, `get_venue_availability.php` | Live scheduling slot & clergy availability AJAX endpoints | Guest/Member privacy title masking; `service_id IN (4,7,8)` exemptions |
| `get_search_suggestions.php`, `get_cert_record_suggestions.php` | Autocomplete / certificate record search JSON endpoints | Authenticated endpoints |

### 2.2 Models (`app/models/` — 16 files)

| File | Primary Table(s) | Key Domain Behaviors |
|---|---|---|
| `User.php` | `users`, `pastor_event_types` | Auth lookup, CRUD, password updates, priest `pastor_assigned` cache resync |
| `Chapel.php` | `chapels`, `chapel_fiestas` | Chapel CRUD, transactional fiesta storage, `chapel_location` resync |
| `Event.php` | `events`, `donations` | Event & request CRUD, `getPendingRequests`, `getPaidAmountsMap`, search & analytics wrapper |
| `SacramentalRecord.php` | `sacramental_records`, `sacramental_audit_log` | Centralized Cathedral read search (`scope='cathedral'`) vs strict branch isolation (`scope='chapel'`), duplicate check |
| `ServiceModel.php` | `service_categories`, `services`, `service_variants`, `service_additional_charges`, `certificate_types` | Hierarchical liturgical catalog & server-side pricing snapshot |
| `Donation.php` | `donations` | Sequential yearly `DON-YYYY-NNNNN` generator, `log()`, `void()`, paginated filters |
| `Merchandise.php` | `merchandise_items`, `merchandise_sales` | Transactional `FOR UPDATE` stock locking in `recordSale()`, category catalog |
| `Inquiry.php` | `public_inquiries` | Structured `item_id`/`quantity` support, fixed strict chapel vs `'cathedral'` scoping |
| `Bulletin.php` | `bulletin_posts` | Ordered bulletin announcements (`sort_order`, `is_active`, optional `chapel_id`) |
| `Chat.php` | `parish_chat_rooms`, `parish_chat_participants`, `parish_chat_messages` | Role-filtered staff directory, DM room upsert, unread read cursors |
| `Ticket.php` | `tickets`, `ticket_comments` | Legacy ticket storage (UI section disconnected) |
| `ParishInfo.php` | `parish_contact_info`, `mass_schedules`, `parish_history*`, `parish_leadership*` | Public homepage content, SMTP fallback config |
| `SystemNotification.php` | `system_notifications` | In-app notification store & read state |
| `AuditLogger.php` & `AuditLogSection.php` | `audit_logs` | Immutable action audit snapshots + filtered pagination/CSV export |
| `InsightPortfolio.php` | Derived analytics | Strategic insights synthesis |

### 2.3 Services, Mailers & Helpers

- **`app/services/` (8 files):** `EventConflictService.php`, `EventAnalyticsService.php`, `DashboardSectionDataService.php`, `DashboardExportService.php`, `SacramentalCertificateService.php`, `SacramentalReportingService.php`, `BackupService.php`, `SmsService.php` (Mock file log / Semaphore / iProgTech).
- **`app/mail/` (4 files):** `mailer.php` (PHPMailer SMTP with DB & chapel overrides), `EventRequestMailer.php`, `DocumentReadyMailer.php`, `InquiryMailer.php`.
- **`app/helpers/` (2 files):** `RateLimiter.php` (file-backed sliding window in `storage/rate_limits/`), `error_debug.php`.

---

## 3. Views & Client Modules

- **Pages (`views/pages/` — 13 files):** `index.php`, `dashboard.php`, `portal_request.php`, `error_500.php`, `login_standalone_backup.php`, 4 canonical sacramental intake forms (`baptism_form.php`, `confirmation_form.php`, `marriage_form.php`, `funeral_form.php`), 4 print/receipt studios (`donation_receipt.php`, `merchandise_receipt.php`, `finance_report_print.php`, `service_register_print.php`), plus `certificates/sacramental_certificate.php`.
- **Dashboard Partials (`views/partials/`):** Organized across `admin/`, `chat/`, `dashboard/` (`ajax_renderer.php`, `bulletin.php`, `overview.php`, `profile.php`, `view_context.php`), `finance/`, `inquiries/`, `maintenance/`, `management/`, `modals/`, `pastoral/`, `records/`.
- **Client JS (`assets/js/` — 17 files):** `dashboard-core.js`, `dashboard-modals.js`, `dashboard-availability.js`, `dashboard-calendar.js`, `dashboard-records.js`, `dashboard-nav.js`, `dashboard-profile.js`, `inbox-dropdown.js`, `liquid-sidebar.js`, `notifications.js`, `parish-chat.js`, `ph-geo-data.js`, `search-autocomplete.js`, `skeleton-loader.js`, `theme.js`, `chart.umd.min.js`, `three.min.js`.

---

## 4. Configuration, CLI Utilities & Database Inventory

- **Config (`config/`):** `config.php`, `config.local.php`, `config.local.example.php` (Asia/Manila TZ, `utf8mb4` mysqli connection, cookie/session hardening).
- **Tools (`tools/` — 8 files):** `backup.php`, `restore.php`, `backup_download.php`, `restore_web.php`, `health_check.php`, `migrate_system_notifications.php`, `backup_daily.bat`, `backup_weekly.bat`.
- **Database (`cathedral` schema — 38 tables + 1 view):**
  - Core entities: `users` (23), `chapels` (3), `chapel_fiestas` (1), `pastor_event_types` (30)
  - Scheduling & Services: `events` (46), `mass_schedules` (4), `schedules` (0), `service_categories` (4), `services` (8), `service_variants` (21), `service_additional_charges` (4), `service_requirements` (11), `service_schedules` (4), `service_request_addons` (0), `certificate_types` (3)
  - Sacramental Records: `sacramental_records` (34), `certificate_issuances` (1), `sacramental_audit_log` (131), view `v_sacramental_records` (18)
  - Finance & Store: `donations` (8), `merchandise_items` (5), `merchandise_sales` (23)
  - Inquiries, Bulletin, Chat & System: `public_inquiries` (9), `bulletin_posts` (8), `parish_chat_rooms` (11), `parish_chat_participants` (25), `parish_chat_messages` (16), `system_notifications` (25), `notifications` (0), `tickets` (24), `ticket_comments` (0), `audit_logs` (954), `backup_logs` (39), `system_settings` (2), `parish_contact_info` (1), `parish_history` (4), `parish_history_intro` (1), `parish_leadership` (25), `parish_leadership_intro` (1).
- **Disconnected / Legacy Schema & Code Observations:**
  - `tickets` (24 rows), `ticket_comments` (0), `notifications` (0), `schedules` (0) are unused/superseded tables (`TicketController.php` & `BaseController` `'can_tickets'` still present while `ajax_renderer.php` line 19 disabled `case 'tickets'`; `system_notifications` replaced `notifications`).
  - Zero SQL `FOREIGN KEY` constraints across the schema; only `audit_logs` defines secondary indexes (no index on `events.start_time`, `users.email`, `sacramental_records.event_date`, or `donations.date_received`).
