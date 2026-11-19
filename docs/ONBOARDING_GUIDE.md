# IFI Cathedral Management System — Developer Onboarding Guide

## Project Overview

A PHP-based chapel management system for the Iglesia Filipina Independiente (IFI) Cathedral. It serves two distinct user interfaces:
1. **Public Portal** — informational website with mass schedules, history, merchandise, and donation options
2. **Admin Dashboard** — role-based management system for events, sacramental records, finances, inquiries, and chapel operations

**Stack:** PHP 8+, MySQL (mysqli), vanilla JavaScript, Chart.js, Font Awesome  
**Architecture:** Custom MVC with service layer, no external framework  
**Location:** `c:\xampp\htdocs\sukaniggadih\ORIGINAL`

---

## 1. Entry Points

| File | Purpose | Target Audience |
|------|---------|-----------------|
| `index.php` | Public portal homepage | Visitors, members |
| `dashboard.php` | Main admin dashboard | Staff, clergy, admins |
| `public_requests.php` | Redirects to merchandise section | Public users |
| `logout.php` | Session termination | Authenticated users |

**How they work:**
- `index.php` → includes `views/pages/index.php` → loads `HomeController` for public data
- `dashboard.php` → includes `views/pages/dashboard.php` → loads `DashboardController` for authenticated sections
- All entry points bootstrap via `config/config.php` (DB connection, session, security headers)

---

## 2. Configuration & Bootstrap

### `config/config.php`
The system bootstrap file. Every request loads this first.

**Key responsibilities:**
- Session security (HttpOnly, SameSite, Secure flags)
- Security headers (X-Frame-Options, HSTS, nosniff)
- Database connection (mysqli with utf8mb4)
- Mail/SMS configuration (SMTP, Semaphore SMS API)
- Timezone setup (`Asia/Manila` default)
- Defines `BASE_PATH` and `BASE_URL` constants

**Local overrides:** `config/config.local.php` (gitignored) for environment-specific credentials.

### `config/config.local.example.php`
Template for local config. Copy to `config.local.php` and fill in your DB/SMTP/SMS credentials.

---

## 3. MVC Architecture

### Controllers (`app/controllers/`)

**BaseController.php** — Foundation for all authenticated controllers.
- Session validation
- CSRF token generation/validation
- RBAC (Role-Based Access Control) with 6 roles:
  - `Admin` / `Cathedral Admin` — full system access
  - `Chapel Admin` — full control within assigned chapel
  - `Pastor` — schedule/view access
  - `Staff` — create/view records, limited update (own records only)
  - `Member` — read-only public access
- Chapel-based data isolation (users see only their chapel's data unless admin)

**DashboardController.php** — The "traffic controller" for the dashboard.
- Instantiates all sub-controllers (Finance, Admin, Inquiry, Sacramental, Event, Ticket)
- Routes POST requests by `form_section` parameter
- Loads view data based on `?section=` GET parameter
- Handles exports (CSV) and pickup notifications (email/SMS)

**Specialized Controllers:**
| Controller | Responsibility |
|------------|---------------|
| `AuthController.php` | Login/logout logic |
| `authenticate.php` | Login form handler |
| `EventController.php` | Event CRUD, approval workflows, schedule conflicts |
| `FinanceController.php` | Donations, merchandise sales, financial reports |
| `InquiryController.php` | Public inquiry/request management |
| `SacramentalController.php` | Baptismal/marriage/funeral records |
| `AdminController.php` | User management, chapel profiles, parish settings |
| `TicketController.php` | Support ticket system |
| `PortalController.php` | Public-facing merchandise reservations |
| `HomeController.php` | Public homepage data (bulletins, schedules, history) |

**AJAX Endpoints:**
- `get_day_schedule.php` — calendar availability
- `get_pastor_availability.php` — pastor scheduling
- `get_venue_availability.php` — venue booking checks
- `notifications.php` — notification system

### Models (`app/models/`)

Data access layer. Each model wraps database operations for a specific entity.

| Model | Purpose |
|-------|---------|
| `User.php` | User accounts, roles, authentication |
| `Chapel.php` | Chapel locations, schedules, assignments |
| `Event.php` | Events, requests, approvals, conflicts |
| `Donation.php` | Donation records, statistics |
| `Merchandise.php` | Parish shop items, inventory |
| `SacramentalRecord.php` | Baptismal, marriage, funeral records |
| `Inquiry.php` | Public inquiries and messages |
| `Ticket.php` | Support tickets |
| `Bulletin.php` | Parish announcements |
| `AuditLogger.php` | System audit trail |
| `AuditLogSection.php` | Audit log queries and exports |
| `InsightPortfolio.php` | Analytics data aggregation |
| `ParishInfo.php` | Cathedral contact details |

### Services (`app/services/`)

Business logic layer for complex operations.

| Service | Purpose |
|---------|---------|
| `DashboardSectionDataService.php` | Loads data for each dashboard section (orchestrates models) |
| `DashboardExportService.php` | CSV export generation for reports, records, audits |
| `EventAnalyticsService.php` | Event type analysis, monthly trends |
| `EventConflictService.php` | Detects scheduling conflicts and misplacements |
| `SacramentalCertificateService.php` | Generates sacramental certificates |
| `SacramentalReportingService.php` | Sacramental record analytics |
| `SmsService.php` | SMS notification dispatch (Semaphore API) |
| `BackupService.php` | Database backup operations |

---

## 4. Views & Frontend

### Structure

```
views/
├── pages/              # Full page templates
│   ├── index.php       # Public portal (930 lines, monolithic)
│   └── dashboard.php   # Admin dashboard shell (399 lines)
├── partials/           # Modular components
│   ├── dashboard/      # Dashboard sections (overview, events, records, etc.)
│   ├── admin/          # User/chapel management
│   ├── finance/        # Donations, merchandise, insights
│   ├── inquiries/      # Event requests, messages
│   ├── records/        # Sacramental records
│   ├── pastoral/       # Calendar, scheduling
│   ├── maintenance/    # Tickets, health audits
│   ├── modals/         # Shared modal dialogs
│   └── public/         # Public-facing components
├── certificates/       # Certificate templates
└── modals/             # Dashboard and audit modals
```

### How Views Connect

**Dashboard Flow:**
1. `dashboard.php` loads `DashboardController`
2. Controller reads `?section=` parameter (default: `overview`)
3. `loadViewData()` delegates to `DashboardSectionDataService` or sub-controllers
4. View data is passed to `views/partials/dashboard/ajax_renderer.php`
5. AJAX navigation loads partials dynamically without full page reload

**Public Portal Flow:**
1. `index.php` loads `HomeController`
2. Controller fetches bulletins, schedules, history, leadership, merchandise
3. View renders monolithic `views/pages/index.php` with inline PHP/HTML

### Frontend Assets

```
assets/
├── css/
│   ├── index.css       # Public portal styles
│   ├── dashboard.css   # Dashboard styles
│   ├── layout.css      # Shared layout
│   ├── components.css  # UI components
│   └── utilities.css   # Helper classes
├── js/
│   ├── func.js         # Public portal scripts
│   ├── dashboard-*.js  # Modular dashboard scripts
│   └── chart.umd.min.js # Chart.js library
└── img/                # Images and logos
```

---

## 5. How Modules Connect

### Request Flow Diagram

```
User Request
    ↓
Entry Point (index.php / dashboard.php)
    ↓
config/config.php (bootstrap, DB, session)
    ↓
Controller (BaseController → specialized controller)
    ↓
├── Model (data access)
├── Service (business logic)
└── View (presentation)
    ↓
Response (HTML / JSON / CSV)
```

### Dashboard Section Routing

```
dashboard.php?section=overview
    ↓
DashboardController::loadViewData('overview')
    ↓
DashboardSectionDataService::loadOverviewSectionData()
    ↓
views/partials/dashboard/overview.php
```

### Data Isolation Pattern

The system enforces **chapel-based data isolation**:
- Non-admin users are scoped to their `chapel_id` (stored in session)
- Models accept `$chapelFilter` parameter to limit queries
- `BaseController::checkChapelAccess()` validates access to records
- Admins/Cathedral Admins bypass isolation (see all chapels)

### RBAC Implementation

Permissions are initialized in `BaseController::initPermissions()`:
- Stored as boolean flags (e.g., `can_manage_users`, `can_donations`)
- Checked in controllers before loading section data
- Used in views to show/hide UI elements

---

## 6. Key Business Modules

### Events & Scheduling
- **Controllers:** `EventController`, `DashboardController` (routing)
- **Models:** `Event`, `Chapel`
- **Services:** `EventConflictService`, `EventAnalyticsService`
- **Views:** `views/partials/pastoral/calendar.php`, `views/partials/dashboard/events.php`
- **Features:** Event creation, approval workflow, schedule conflicts, pastor availability

### Sacramental Records
- **Controllers:** `SacramentalController`
- **Models:** `SacramentalRecord`
- **Services:** `SacramentalCertificateService`, `SacramentalReportingService`
- **Views:** `views/partials/records/`
- **Features:** Baptismal/marriage/funeral records, certificate generation, CSV export

### Finance & Donations
- **Controllers:** `FinanceController`
- **Models:** `Donation`, `Merchandise`
- **Services:** `DashboardExportService`
- **Views:** `views/partials/finance/`
- **Features:** Donation tracking, merchandise sales, financial reports, GCash/Maya/Bank offering display

### Inquiries & Support
- **Controllers:** `InquiryController`, `TicketController`
- **Models:** `Inquiry`, `Ticket`
- **Views:** `views/partials/inquiries/`, `views/partials/maintenance/`
- **Features:** Public inquiry management, support ticket system

### Admin & Users
- **Controllers:** `AdminController`
- **Models:** `User`, `Chapel`, `ParishInfo`, `Bulletin`
- **Views:** `views/partials/admin/`
- **Features:** User CRUD, chapel management, parish settings, bulletin board

### Audit & Compliance
- **Models:** `AuditLogger`, `AuditLogSection`
- **Views:** `views/partials/admin/audit_logs.php`
- **Features:** Action logging, audit trail export, health audits (stale events, overdue requests)

---

## 7. Database & Storage

### Database
- **Name:** `cathedral_system2` (default)
- **Connection:** mysqli with utf8mb4 charset
- **Config:** `config/config.php` lines 70-110

### File Storage
```
storage/
├── logs/           # PHP error logs
├── merchandise/    # Product images
├── uploads/        # User uploads (profile pics, documents)
└── .htaccess       # Prevents direct access
```

### Backups
- **Tools:** `tools/backup.php`, `tools/restore.php`
- **Scripts:** `tools/backup_daily.bat`, `tools/backup_weekly.bat`
- **Backups stored in:** `backups/` directory

---

## 8. Security Features

1. **Session Security:** HttpOnly, Secure, SameSite cookies
2. **CSRF Protection:** Token-based validation on all POST forms
3. **RBAC:** 6 roles with granular permissions
4. **Chapel Isolation:** Users see only their chapel's data
5. **Security Headers:** X-Frame-Options, HSTS, nosniff
6. **Input Sanitization:** `htmlspecialchars()` in views, prepared statements in models
7. **Audit Logging:** All critical actions logged via `AuditLogger`

---

## 9. Development Workflow

### Adding a New Dashboard Section

1. **Add route logic** in `DashboardController::loadViewData()` (switch case)
2. **Create data loader** in `DashboardSectionDataService` or a sub-controller
3. **Create view partial** in `views/partials/dashboard/your_section.php`
4. **Add nav item** in `views/pages/dashboard.php` sidebar
5. **Add JS module** if needed in `assets/js/dashboard-your_section.js`

### Adding a New Model

1. Create class in `app/models/YourModel.php`
2. Extend base pattern: constructor accepts `$conn`, methods return arrays
3. Use chapel filtering: `->getAll($chapelFilter)`
4. Register in `DashboardController` if needed for overview stats

### Adding a New Service

1. Create class in `app/services/YourService.php`
2. Inject dependencies via constructor
3. Use for complex business logic (not simple CRUD)

---

## 10. Important Patterns & Conventions

### Flash Messages
```php
// Set in controller
$this->flash('success', 'Operation completed.');
$this->flash('error', 'Something went wrong.');

// Display in view
<?php if ($flash): ?>
    <div class="flash flash-<?php echo $flash_type; ?>">
        <?php echo $flash; ?>
    </div>
<?php endif; ?>
```

### CSRF Tokens
```php
// Generate
$csrf = $this->generateCsrf();

// Validate
$this->enforceCsrf();

// In form
<input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
```

### Chapel Filtering
```php
// Non-admins get their chapel ID, admins get null (all chapels)
$chapelFilter = !in_array($this->role, ['Admin', 'Cathedral Admin']) 
    ? $this->chapelId 
    : null;

// Pass to model
$records = $this->model->getAll($chapelFilter);
```

### AJAX Navigation
```php
// In dashboard.php
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    include __DIR__ . '/../../views/partials/dashboard/ajax_renderer.php';
    exit();
}
```

---

## 11. Common Tasks

### Run the Application
1. Start XAMPP (Apache + MySQL)
2. Import database: `Iantejada2303!!!.sql` or use `tools/migrate_events.php`
3. Configure `config/config.local.php` with DB credentials
4. Visit `http://localhost/sukaniggadih/ORIGINAL/`

### Default Login
Check `app/models/User.php` or database for seed data. Typical roles:
- Admin (full access)
- Chapel Admin (chapel-level access)
- Pastor (schedule/view)
- Staff (create/view records)
- Member (read-only)

### Debug Mode
Set environment variable: `APP_DEBUG=true`  
Or edit `config/config.php` line 32.

### View Logs
- PHP errors: `storage/logs/php_errors.log`
- Database: Check MySQL error log in XAMPP

---

## 12. Tech Debt & Notes

- **Monolithic views:** `views/pages/index.php` is 930 lines of mixed PHP/HTML/CSS/JS
- **No autoloader:** Manual `require_once` statements in controllers
- **Inline styles:** Heavy use of inline CSS in views (hard to maintain)
- **Session-based auth:** No JWT or OAuth (fine for internal use)
- **No unit tests:** Test via browser or CLI scripts in `scratch/`
- **Legacy code:** `../newtypeshichap2/` contains older version (reference only)

---

## Quick Reference

| Need to... | Look at... |
|------------|-----------|
| Change login logic | `app/controllers/AuthController.php` |
| Add a dashboard section | `DashboardController::loadViewData()` |
| Modify user permissions | `BaseController::initPermissions()` |
| Add a new report | `app/services/DashboardExportService.php` |
| Change public homepage | `views/pages/index.php` |
| Add SMS notifications | `app/services/SmsService.php` |
| Modify chapel logic | `app/models/Chapel.php` |
| Add audit logging | `AuditLogger::logFromSession($conn, $action, $entity, $description)` |

---

## Getting Help

- **Master System & Architecture Guide:** `SYSTEM_MASTER_GUIDE.md`
- **System docs:** `docs/technical_architecture_blueprint.md`
- **Audit report:** `docs/system_audit_report.md`
- **Wireframes:** `docs/wireframes.zip`
- **Backup guide:** `tools/BACKUP_README.md`

---

*Last updated: 2025-06-28*  
*Maintained by: Cathedral Development Team*