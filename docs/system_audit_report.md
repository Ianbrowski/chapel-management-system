# Comprehensive Codebase Audit & Architectural Analysis: Cathedral Management System (CMS)

Audit date: 2026-05-08

## 1. Complete Architectural Assessment

The Cathedral Management System (CMS) is built on a custom MVC-style architecture using native PHP 8.x, vanilla JavaScript, and MySQL. It intentionally avoids application frameworks such as Laravel or Symfony, while bundling direct library code where needed, such as PHPMailer under `src/`.

### Strengths of the Architecture

- **Centralized RBAC and chapel checks**: `app/controllers/BaseController.php` defines role capabilities for Admin, Cathedral Admin, Chapel Admin, Pastor, Staff, and Member, and exposes reusable permission helpers.
- **Chapel-scoped data isolation**: Operational controllers such as `EventController` force non-admin schedule writes into the logged-in user's chapel and prevent cross-chapel management of existing chapel records.
- **Prepared statements on core write paths**: Main CRUD paths use `mysqli->prepare()` and `bind_param()`, which materially reduces SQL injection exposure.
- **Audit trail coverage for important operations**: Controllers and models call `AuditLogger` for event, bulletin, sacramental record, and request lifecycle actions.
- **Database-level pagination**: Sacramental record search and list-oriented model methods use `LIMIT` and `OFFSET` instead of loading entire tables.
- **Safer dashboard partial rendering**: `views/partials/dashboard/ajax_renderer.php` uses allowlisted section rendering rather than directly including paths from request input.

### Architectural Weaknesses and Technical Debt

- **DashboardController remains an orchestration god object**: `DashboardController` delegates to sub-controllers, but still owns request routing, common section actions, model construction, and section data orchestration.
- **Tight coupling to `mysqli` and direct construction**: Controllers and models instantiate collaborators directly in constructors, making isolated unit tests difficult.
- **Mixed service boundaries**: Some domain behavior lives in services (`EventConflictService`, `DashboardSectionDataService`), while related routing and workflow decisions remain in controllers.
- **Procedural entry points and session reliance**: Files such as `dashboard.php`, `index.php`, and several controller helpers depend on globals and `$_SESSION`, which is expected in this codebase but limits testability.

---

## 2. Map of System Relationships and Data Flow

**Frontend (Client) -> Backend (Server) -> Database**

1. **Routing Entry Points**
   - Public: `index.php` -> `views/pages/index.php` -> `HomeController.php`
   - Authenticated: `dashboard.php` -> `views/pages/dashboard.php` -> `DashboardController.php`
   - Public request portal: `public_requests.php` -> portal request flow

2. **Dashboard Request Flow**
   - User navigates to `dashboard.php?section={section_name}` through dashboard navigation or page load.
   - `DashboardController::handleRequest()` handles POST actions and delegates to `FinanceController`, `AdminController`, `InquiryController`, `SacramentalController`, `EventController`, or `TicketController`.
   - `DashboardController::loadViewData($section)` builds common defaults, then uses `DashboardSectionDataService` for section-specific data.
   - `views/partials/dashboard/ajax_renderer.php` renders only known partials.

3. **Database and Domain Layer**
   - `SacramentalRecord.php`: CRUD, reporting integration, reference ID generation, certificate service integration.
   - `Event.php`: Event CRUD, event request lifecycle, stale status resolution, analytics delegation, display-field synchronization.
   - `EventConflictService.php`: Venue and pastor overlap detection with a fixed 15-minute buffer.
   - `User.php`: Authentication-adjacent user data, profile updates, pastor preferences.
   - `Chapel.php`: Chapel CRUD, fiesta management, dependency checks before deletion.

---

## 3. Detected Issues Categorized by Severity

### High Severity

- **Hardcoded database ENUMs in events**: `schema_output.txt` confirms `events.event_type` is `enum('Mass','Wedding','Baptism','Funeral','Other')`, and `events.status` is also an ENUM. Adding Confirmation, Communion, Anointing of the Sick, or new workflow statuses requires a schema migration and coordinated code changes.
- **Local secret config is present in the working tree**: `config/config.local.php` exists and contains live-looking SMTP credentials. `.gitignore` correctly excludes it, but if this folder has ever been copied, zipped, backed up, or committed elsewhere, those credentials should be rotated.
- **Public root debug/test scripts are web reachable by default**: Root files such as `debug_event.php`, `test_request.php`, `test_schema.php`, `test_columns.php`, `test_event_model.php`, `test_raw_insert.php`, `check_schema.php`, and `add_image_col.php` are not blocked by the root `.htaccess`. Several expose schema details, force `display_errors`, write test rows, or run `ALTER TABLE`.
- **Certificate endpoint lacks role and chapel authorization**: `certificates/sacramental_certificate.php` only checks for `$_SESSION['user_id']`, then loads any `record_id`. It does not enforce role permissions, chapel ownership, `is_archived = 0`, or issuance ownership.
- **Printable receipt endpoints lack chapel isolation**: `views/pages/donation_receipt.php` and `views/pages/merchandise_receipt.php` require a privileged-ish role, but they load by numeric id without enforcing the user's chapel scope. Donation receipts also do not exclude archived donations.
- **Archived events leak into analytics/reporting**: Several `EventAnalyticsService` methods do not filter `is_archived = 0`, including upcoming events, CSV export, chart types, chart status, monthly counts, pastor workload, venue demand, usage stats, and response time stats. `views/pages/finance_report_print.php` also counts completed events without excluding archived rows.
- **`Event::getById()` returns archived records**: `app/models/Event.php` fetches by `event_id` without `e.is_archived = 0`. This is sometimes useful for audit/admin history, but update/review/delete flows call it as if it returns active records, which increases the chance of operating on archived events unless downstream update queries block it.

### Medium Severity

- **Status downgrade guard blocks Chapel Admin correction**: `EventController::handleEventActions()` prevents `Confirmed -> Pending` for anyone except Admin and Cathedral Admin. That is intentional integrity protection, but it means Chapel Admins cannot undo accidental confirmation within their chapel without escalation.
- **Availability AJAX endpoints expose broad schedule metadata**: `get_pastor_availability.php` and `get_venue_availability.php` require login but do not enforce role-specific chapel scope before returning pastor/venue availability. `get_day_schedule.php` has a chapel guard, but the other availability endpoints can still reveal cross-chapel operational availability to any authenticated user.
- **Cached event display fields can become stale**: `events.chapel_location` and `events.pastor_assigned` are synchronized when an event is created or updated, but `User::updateProfile()`, `User::update()`, and `Chapel::update()` do not resync existing future events after a pastor name or chapel name changes.
- **Main Cathedral representation is inconsistent**: `BaseController::checkChapelAccess()` treats `null`, empty string, string `'0'`, and integer `0` as Main Cathedral/global access. Some queries also check `(chapel_id IS NULL OR chapel_id = 0)`, while event conflict detection uses `chapel_id IS NULL AND event_scope = 'cathedral'`. Prefer a single DB representation, ideally `NULL`.
- **Runtime schema mutation is mixed into request paths**: `AuthController`, `Donation`, and `ParishInfo` can issue `ALTER TABLE` from normal application execution. This reduces migration friction but increases production risk and makes deployments harder to reason about.
- **Backup/restore is powerful and stored under the web root**: Tool endpoints are guarded by Admin/Cathedral Admin checks and CSRF where state-changing, but backup archives live under `backups/` inside the web tree. The `.htaccess` protects direct access on Apache; a different server or misconfiguration would make this a serious data exposure.
- **Conflict locking can increase contention**: `EventConflictService` uses `FOR UPDATE` on same-day event rows for both venue and pastor checks. This is reasonable for correctness, but may serialize scheduling under busy usage.

### Low Severity

- **Mixed query styles remain**: The code is mostly prepared-statement based, but some model methods still use direct `$conn->query()` with cast integers or internally built SQL. These are lower risk where values are cast, but the style is inconsistent.
- **Public portal rate limiting is session-local**: `PortalController` uses CSRF, a 60-second session cooldown, and duplicate checks. That helps accidental repeats, but it is not a strong anti-spam control because a new session bypasses it.
- **External font dependencies affect offline/local behavior**: Several print pages load Google Fonts directly. This is not a security flaw by itself, but it creates a privacy and availability dependency for printable receipts/certificates.
- **Long data maps in sacramental records**: `SacramentalRecord::createClean()` now accepts an array rather than 30+ positional parameters, but still binds a very large column set manually. A DTO or record-specific mapper would reduce accidental field drift.
- **Encoding artifacts in comments**: Several PHP comments contain mojibake characters, which does not affect runtime behavior but makes maintenance harder.

---

## 4. Security and Performance Concerns

**Security**

- **SQL injection posture is strong on main paths**: Most user-controlled values are bound through prepared statements.
- **CSRF protection is active on state-changing dashboard actions**: `BaseController::enforceCsrf()` is called by dashboard sub-controllers for event, admin, finance, records, and related POST sections.
- **XSS posture is mostly good**: Views commonly use `htmlspecialchars()`, though any newly added partials should keep that convention.
- **Local file inclusion risk is low in dashboard partials**: The renderer is allowlist based.
- **Storage execution protection exists**: `storage/.htaccess` denies direct access to script-like extensions such as `.php`, `.phtml`, `.pl`, `.py`, `.jsp`, `.asp`, `.sh`, and `.cgi`, and disables directory listing.
- **Root web hardening is incomplete**: `.htaccess` blocks `config`, `tools`, `tmp`, `backups`, `app`, `views`, `src`, and `scratch`, but root-level debug/test/migration PHP scripts remain directly addressable unless removed or explicitly blocked.
- **Error display is disabled by default**: `config/config.php` sets `display_errors` off outside `APP_DEBUG=true` and logs to `php_errors.log`. This is a good default, but bootstrap exceptions thrown before a friendly error page is selected should still be reviewed in deployment.

**Performance**

- **JOIN usage is generally healthy**: Event and sacramental record lists usually join related chapel/user data instead of querying inside view loops.
- **Analytics queries need archive filters and indexes**: Once `is_archived = 0` is added consistently, add or verify indexes on event columns commonly filtered together, such as `(is_archived, start_time)`, `(is_archived, chapel_id, start_time)`, `(is_archived, status)`, and `(is_archived, pastor_id, start_time)`.
- **Reference generation uses locking**: `SacramentalRecord::generateReferenceId()` counts records with `FOR UPDATE`; this can become a bottleneck under concurrent entry. A sequence table or unique retry loop would scale better.
- **Backups shell out to MySQL tools**: `BackupService` executes `mysqldump`, `mysql`, and a PowerShell zip fallback. Arguments are mostly escaped, but this path should be restricted to trusted admins and monitored because it has database-wide impact.

---

## 5. Inconsistencies Between Modules

- **Approval workflow differs by domain**: Event requests have explicit Pending -> Confirmed/Cancelled handling, while sacramental records are direct CRUD records with certificate issuance and audit logging but no equivalent approval workflow.
- **Soft delete is not uniformly enforced**: Sacramental search and most event list methods filter archived rows, but analytics/export/print reporting paths are inconsistent.
- **Chapel admin semantics differ by action**: Chapel Admins can approve event requests for their chapel, but cannot downgrade a confirmed event to pending.
- **Receipt/certificate authorization differs from dashboard authorization**: Dashboard sections enforce role and chapel checks through controllers, but direct printable pages implement their own thinner guards.
- **Tooling scripts bypass the MVC security model**: Root debug/test scripts and migration scripts use direct config/model access and should not be part of the deployed web root.

---

## 6. Missing Implementations or Unstable Logic

- **Travel-aware scheduling is not implemented**: `EventConflictService` enforces time overlap plus a fixed 15-minute buffer. It does not calculate chapel-to-chapel travel time or apply different buffers by venue distance.
- **Archived-row handling needs a policy**: Decide which methods are active-only by default and which are audit/history methods. Then name them explicitly, for example `getActiveById()` versus `getAnyById()`.
- **Display-field resync hooks are missing**: Pastor and chapel rename/update operations should refresh cached event display fields, at least for active future events.
- **Receipt/certificate direct-object access needs a shared guard**: Printable pages should call the same chapel/role authorization logic used by controllers before rendering sensitive records.
- **Schema changes need a migration boundary**: Move runtime `ALTER TABLE` calls into versioned migrations or an admin-only migration runner.

---

## 7. Recommendations for Refactoring and Optimization

1. **Remove or block deployment-only scripts**: Delete root debug/test/migration PHP scripts from the web root or add explicit deny rules for them.
2. **Rotate exposed local credentials**: Treat the SMTP credential in `config/config.local.php` as exposed if this directory has been shared, backed up, or committed anywhere.
3. **Fix direct-object authorization**: Add shared role, chapel, and archive checks to certificates, donation receipts, merchandise receipts, and finance print reports.
4. **Fix archived-event leakage**: Add `is_archived = 0` to event analytics, exports, printable reports, stale-event reads, and active lookup methods.
5. **Normalize configurable types**: Replace `events.event_type` ENUM with an `event_types` reference table or a `VARCHAR` plus validation table. Do the same for statuses if workflows are expected to expand.
6. **Clarify active versus historical model methods**: Introduce explicit method names for active-only reads and audit/history reads.
7. **Add display-field resync hooks**: Update `Event` cached strings when `User` or `Chapel` names change, or stop storing cached names where joins can supply fresh display values.
8. **Extract dashboard routing**: Move section/action routing from `DashboardController` into a small dispatcher or route map.
9. **Introduce dependency injection gradually**: Start by injecting services/models into controllers in tests or factory functions before adding a full container.
10. **Refactor sacramental record mapping**: Keep `createClean(array $d, ...)`, but isolate field definitions in one column map or DTO so create/update/export cannot drift.

---

## 8. Prioritized Debugging and Improvement Roadmap

### Phase 1: Stability & Security (Immediate)

- [x] Add `is_archived = 0` filters to `EventAnalyticsService` and `views/pages/finance_report_print.php`.
- [x] Remove or explicitly block root debug/test scripts: `debug_event.php`, `test_*.php`, `check_schema.php`, and `add_image_col.php`.
- [ ] Rotate the SMTP credential currently stored in `config/config.local.php` if it has left the machine or entered any shared artifact.
- [x] Add role, chapel, and archive checks to `certificates/sacramental_certificate.php`, `views/pages/donation_receipt.php`, and `views/pages/merchandise_receipt.php`.
- [x] Add chapel-scope guards to `get_pastor_availability.php` and `get_venue_availability.php`.
- [x] Make `Event::getById()` active-only for current controller workflows.
- [x] Confirm storage script execution protection exists in `storage/.htaccess`.
- [x] Add event display-field resync after `User::updateProfile()`, `User::update()`, and `Chapel::update()`.
- [x] Standardize Main Cathedral storage to `chapel_id IS NULL` and migrate any `chapel_id = 0` rows (verified 0 residual rows across all tables).

### Phase 2: Refactoring (Short Term)

- [ ] Replace event type ENUM with a lookup table or flexible validated string.
- [x] Eliminate runtime `ALTER TABLE` executions during normal request handling (removed redundant schema mutations from ParishInfo and cached in Donation).
- [ ] Move backup archives outside the web root, or enforce equivalent deny rules in every target web server.
- [ ] Extract dashboard section/action routing into a dedicated dispatcher.
- [ ] Add explicit active/history method naming to event and reporting models.
- [ ] Centralize sacramental record column mapping for create/update/reporting.

### Phase 3: Architecture Modernization (Long Term)

- [ ] Introduce a small service factory or container for controllers and models.
- [ ] Add automated tests around chapel isolation, event review permissions, archive filtering, and conflict detection.
- [ ] Implement travel-time-aware scheduling buffers for pastors moving between chapels.

---

## 9. Simulation and Smoke-Test Pass

Run date: 2026-05-08

### What Was Simulated

- PHP syntax check across every `.php` file in the repository.
- Read-only database connectivity and required table checks.
- Required-column checks for core tables: `users`, `events`, `donations`, `sacramental_records`, `merchandise_items`, and `tickets`.
- Cross-role dashboard section loading for Admin, Cathedral Admin, Chapel Admin, Staff, Pastor, and Member sample users.
- Full dashboard render smoke test for an Admin sample user.
- Form/action/controller mapping scan for common POST workflows.
- Existing error log review for recurring warnings and fatal errors.

### Smoke-Test Result

- [x] All PHP files pass `php -l`.
- [x] Database connection works locally.
- [x] Core tables exist locally.
- [x] Core checked columns exist locally.
- [x] Cross-role dashboard section loading passes after fixes below.
- [x] Admin dashboard render passes after fixes below.

### Bugs Fixed During Simulation

- [x] `InquiryController::getInquiryViewData()` now defines the `$filters` array before calling `Inquiry::getAll()` / `Inquiry::getArchived()`. This fixed the public inquiries inbox for Admin, Cathedral Admin, Chapel Admin, and Staff.
- [x] `views/partials/dashboard/view_context.php` now defines `$chapelId` as a backward-compatible alias for `$chapel_id`, fixing repeated modal warnings in `dashboard_modals.php`.
- [x] Root development/debug scripts are blocked by `.htaccess`.
- [x] Certificate and donation receipt pages now enforce active-record and chapel/role checks; merchandise receipts are role-restricted, but branch-level restriction needs a `chapel_id` or equivalent sale ownership column on `merchandise_sales`.
- [x] Event analytics, stale-event reads, active event detail reads, and finance print event counts now exclude archived events.
- [x] Pastor and venue availability AJAX endpoints now enforce chapel scope for non-admin users.

### Still Not Complete

The system is not fully production-clean yet. The simulation confirms it can load and render key paths locally, but the unresolved Phase 1 security and logic items above still matter, especially direct-object authorization, root debug scripts, archived-event reporting leaks, and exposed local credentials.
