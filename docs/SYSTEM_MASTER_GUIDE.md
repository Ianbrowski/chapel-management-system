# IFI Cathedral & Chapel Management System
## Complete Developer & Operational Master Guide

> **Parish**: Cathedral of the Holy Child — Diocese of Rizal and Pampanga, Iglesia Filipina Independiente (Angono, Rizal)  
> **Environment**: Windows / XAMPP (Apache 2.4, PHP 8.2+, MySQL / MariaDB)  
> **Architecture**: Custom MVC with Service Layer (Zero external framework dependencies)

---

## 1. System Identity & High-Level Architecture

The system is a centralized web platform operating across a primary Cathedral and multiple affiliated satellite chapels. It provides two distinct environments:

1. **Public Web Portal (`index.php`)**: A public-facing site for parishioners and visitors to view mass schedules, liturgical announcements, parish history, buy religious merchandise, submit mass intentions, and apply for sacraments.
2. **Secured Administrative Dashboard (`dashboard.php`)**: A role-based ERP used by clergy, administrative staff, chapel coordinators, and treasurers to manage liturgical scheduling, sacramental books, finances, inventory, public inquiries, and system backups.

```
                           Incoming Web Request
                                    │
                    ┌───────────────┴───────────────┐
                    ▼                               ▼
            Public Visitors                 Staff & Clergy
          (index.php / forms)               (dashboard.php)
                    │                               │
                    ▼                               ▼
             HomeController                 DashboardController
                    │                               │
        ┌───────────┴───────────┐       ┌───────────┴───────────┐
        ▼                       ▼       ▼           ▼           ▼
  Public Portal           Online Intake Auth & RBAC Sub-Modules System Maint.
  (Mass, Bulletins)       (Inquiries)  (Sessions)  (Events, $)  (Backups)
```

---

## 2. Directory Structure & Anatomy

```
ORIGINAL/
├── .htaccess                      # Apache security rules & endpoint whitelisting
├── config/
│   ├── config.php                 # Core bootstrap (DB, sessions, mail/SMS, security headers)
│   ├── config.local.php           # Environment-specific overrides (DB credentials)
│   └── config.local.example.php   # Template for local config
├── app/
│   ├── controllers/               # Business controllers & endpoints
│   │   ├── BaseController.php     # Session, RBAC, CSRF, and chapel filtering foundation
│   │   ├── DashboardController.php# Master router for the admin dashboard
│   │   ├── AuthController.php     # Login / Logout workflows
│   │   ├── EventController.php    # Events, calendar, and priest conflict checks
│   │   ├── SacramentalController.php # Baptisms, Confirmations, Marriages, Funerals
│   │   ├── FinanceController.php  # Donations, tithes, and merchandise sales
│   │   ├── InquiryController.php  # Public inquiry triage and messaging
│   │   ├── AdminController.php    # User management, chapel profiles, parish settings
│   │   ├── TicketController.php   # System issue / support ticket tracking
│   │   └── PortalController.php   # Public merchandise reservations and requests
│   ├── models/                    # Database data access objects (mysqli)
│   │   ├── User.php               # Accounts, roles, password hashes
│   │   ├── Chapel.php             # Cathedral and satellite chapel records
│   │   ├── Event.php              # Calendar events and requests
│   │   ├── SacramentalRecord.php  # Church sacrament registries
│   │   ├── Donation.php           # Financial donations and offerings
│   │   ├── Merchandise.php        # Shop inventory and sales items
│   │   ├── Inquiry.php            # Parishioner inquiries and mass intentions
│   │   ├── Ticket.php             # Maintenance tickets
│   │   ├── Bulletin.php           # News announcements and notices
│   │   └── AuditLogger.php        # Security and operational audit trail
│   └── services/                  # Complex domain & calculation logic
│       ├── DashboardSectionDataService.php # Aggregates payload per dashboard section
│       ├── EventConflictService.php        # Real-time venue and priest booking collision detection
│       ├── EventAnalyticsService.php       # Charts, trends, and liturgical statistics
│       ├── SacramentalCertificateService.php # Canonical certificate renderer
│       ├── SacramentalReportingService.php   # Sacramental metrics
│       ├── DashboardExportService.php      # CSV generation for audits, ledgers, and records
│       ├── SmsService.php                  # Semaphore SMS API gateway
│       └── BackupService.php               # Database dump creation and integrity checks
├── views/
│   ├── pages/                     # Full standalone templates & printable documents
│   │   ├── index.php              # Public homepage (monolithic view)
│   │   ├── dashboard.php          # Admin dashboard shell & navigation
│   │   ├── service_register_print.php # Canonical Register of Services (Service Book)
│   │   ├── finance_report_print.php   # Detailed financial audit report
│   │   ├── donation_receipt.php   # Official donation receipt
│   │   └── merchandise_receipt.php# POS sales slip
│   └── partials/                  # Modular view sections loaded via AJAX
│       ├── dashboard/             # Overview, events, statistics
│       ├── pastoral/              # Calendar, priest availability
│       ├── records/               # Sacramental registry tables
│       ├── finance/               # Donations, merchandise, reports, insights
│       ├── inquiries/             # Inquiries, online mass intention requests
│       ├── admin/                 # User CRUD, chapel setup, parish info, audit trail
│       └── maintenance/           # Tickets, database backups
├── assets/
│   ├── css/                       # UI stylesheets (dashboard.css, index.css, layout.css)
│   └── js/                        # Frontend controllers (dashboard-*.js, chart.js)
├── tools/                         # Maintenance CLI & web tools (backup.php, restore_web.php)
└── storage/                       # Protected file storage (logs, receipts, uploads)
```

---

## 3. Deep-Dive: System Modules Explained

### 3.1 Authentication, RBAC & Multi-Chapel Isolation
* **Primary Files**: `app/controllers/AuthController.php`, `app/controllers/BaseController.php`, `app/models/User.php`
* **Session Management**: Session cookies are configured with `HttpOnly`, `SameSite=Lax`, and `Secure` (when HTTPS is present). Sessions expire upon browser close or explicit logout.
* **Role Hierarchy**:
  1. `Admin` / `Cathedral Admin`: Unrestricted system access across the Cathedral and all satellite chapels.
  2. `Chapel Admin`: Full administrative control **strictly restricted** to their assigned `chapel_id`.
  3. `Priest` / `Pastor`: Liturgical schedule viewing, Mass intention approvals, Service Register access.
  4. `Staff`: Data entry (issuing receipts, encoding records), read-only on administrative configs.
  5. `Member`: Read-only access for verified parishioners.
* **Data Isolation Mechanism**:
  Multi-tenancy is enforced in `BaseController::checkChapelAccess()` and propagated to models:
  ```php
  // Non-admins only receive records matching their assigned chapel
  $chapelFilter = !in_array($this->role, ['Admin', 'Cathedral Admin']) ? $this->chapelId : null;
  $records = $this->model->getAll($chapelFilter);
  ```

---

### 3.2 Pastoral Scheduling & Event Management
* **Primary Files**: `EventController.php`, `Event.php`, `EventConflictService.php`, `EventAnalyticsService.php`
* **Workflow**:
  1. **Draft / Request**: An event (Wedding, Baptism, Sunday Mass, Feast Day) is submitted either from the public portal or encoded directly by staff.
  2. **Conflict Evaluation**: Before approval, `EventConflictService` verifies:
     - **Venue Overlap**: Does the chapel/altar have an overlapping service?
     - **Priest Overlap**: Is the assigned officiant already officiating elsewhere?
     - **Travel Buffer**: Flags a warning if a priest is scheduled at different chapels with less than 30 minutes in between.
  3. **Approval & Calendar Locking**: The event is approved and displayed on the interactive calendar.
  4. **Post-Event Archival**: When marked completed, it links into sacramental logging or historical records.

---

### 3.3 Sacramental Records & Canonical Registry
* **Primary Files**: `SacramentalController.php`, `SacramentalRecord.php`, `SacramentalCertificateService.php`
* **Supported Sacraments**:
  - **Baptism**: Child names, parents, godparents/sponsors, minister, registry book & folio/page.
  - **Confirmation**: Candidate, sponsor, confirming Bishop/Priest, book & folio.
  - **Marriage**: Bride, Groom, parents, witnesses, solemnizing priest, marriage license number.
  - **Burial / Funeral**: Deceased details, cause of death, cemetery/interment location.
* **Intake Forms**: Standalone public submission forms exist for applicants (`baptism_form.php`, `marriage_form.php`, `confirmation_form.php`).
* **Canonical Certificate Printing**: Generates formal sacramental certificates with official parish headers, seal placements, and signature lines.
* **Register of Services (`views/pages/service_register_print.php`)**:
  Generates the official liturgical logbook of all services conducted. Features real-time toggleable columns (Attendance, Communicants, Offerings, Officiants, Signatures) and supports printing blank sacristy ledger sheets.

---

### 3.4 Financial Management, Shop & Reporting
* **Primary Files**: `FinanceController.php`, `Donation.php`, `Merchandise.php`, `views/pages/finance_report_print.php`
* **Donations & Tithes**:
  - Supports multiple payment channels: Cash (In-Person), GCash, Maya, Bank Transfer.
  - Tracks specific funds: General Offering, Tithes, Mass Intentions, Building / Restoration Fund.
  - Printable official donation receipts via `views/pages/donation_receipt.php`.
* **Parish Merchandise (POS)**:
  - Inventory tracking for candles, bibles, scapulars, and liturgical supplies.
  - Stock warning thresholds and automatic stock decrements upon sale.
  - POS sales slip printing via `views/pages/merchandise_receipt.php`.
* **Financial Reports & Audit**:
  - Real-time income calculation across all streams.
  - Filterable by date range and chapel.
  - Comprehensive printout and CSV export capabilities.

---

### 3.5 Inquiries, Mass Intentions & Public Messaging
* **Primary Files**: `InquiryController.php`, `Inquiry.php`, `PortalController.php`, `SmsService.php`
* **Public Communication Pipeline**:
  - Visitors send general questions or Mass intentions (Thanksgiving, Soul/Memorial, Healing) through the public website.
  - Inquiries are categorized and assigned to staff in the dashboard (`Pending` → `In Progress` → `Resolved`).
* **Automated Notifications**:
  - **SMS via Semaphore**: Integrates with the Semaphore API to send text messages to parishioners (e.g., notifying them that a requested baptismal certificate is ready for pick-up).
  - **Email via SMTP / PHPMailer**: Sends confirmation emails for official inquiries.

---

### 3.6 Administration, Audit Logs & System Backups
* **Primary Files**: `AdminController.php`, `AuditLogger.php`, `BackupService.php`, `tools/backup.php`, `tools/restore_web.php`
* **User & Chapel Profiles**:
  - Super-admins create staff/priest credentials, assign passwords, and bind users to specific chapels.
  - Chapel profile editor updates mass times, priest-in-charge, and contact information.
* **Immutable Audit Trail (`audit_logs`)**:
  - Logs critical operations (login attempts, record creations, deletions, financial transactions) along with the user ID, timestamp, and IP address.
* **Database Backup & Recovery**:
  - Automated and manual SQL dumps saved under `backups/`.
  - Browser-accessible web restoration tool (`tools/restore_web.php`) and downloadable SQL archives.

---

## 4. Database Schema Summary

| Table | Function | Important Relations |
|---|---|---|
| `users` | User accounts, credentials, and roles | `chapel_id` → `chapels.chapel_id` |
| `chapels` | Cathedral and satellite chapel records | Referenced by events, records, users |
| `events` | Liturgical and sacramental calendar events | `pastor_id` → `users.user_id`, `chapel_id` |
| `sacramental_records` | Baptism, confirmation, marriage, and death records | `chapel_id`, `created_by` → `users.user_id` |
| `donations` | Financial tithes, mass offerings, donations | `chapel_id`, `recorded_by` → `users.user_id` |
| `merchandise` | Store items, unit prices, and available stock | Referenced by `merchandise_sales` |
| `merchandise_sales` | Merchandise transaction receipts | `item_id` → `merchandise.id` |
| `inquiries` | Public messages, prayer requests, intentions | `assigned_to` → `users.user_id` |
| `tickets` | IT / system bug reports and maintenance issues | `user_id` → `users.user_id` |
| `audit_logs` | System activity ledger for security compliance | `user_id` → `users.user_id` |
| `bulletins` | Cathedral announcements and news posts | `author_id` → `users.user_id` |
| `contact_info` | Parish name, address, and diocesan metadata | Global singleton record |

---

## 5. Security Architecture & Apache Firewall (`.htaccess`)

### Internal Directory Lockdown
The root `.htaccess` file prevents unauthorized access to internal source files. Folders like `/app/`, `/views/`, `/config/`, `/tools/`, and `/storage/` reject all web requests (`403 Forbidden`) by default.

### Standalone Endpoint Whitelisting
Pages intended to open directly in browser tabs or print dialogs must be explicitly permitted in `.htaccess`:

```apache
# Whitelisted standalone endpoints:
RewriteRule ^app/controllers/authenticate\.php$ - [L]
RewriteRule ^app/controllers/get_[^/]+\.php$ - [L]
RewriteRule ^app/controllers/notifications\.php$ - [L]
RewriteRule ^views/pages/error_500\.php$ - [L]
RewriteRule ^views/pages/(donation_receipt|merchandise_receipt|finance_report_print|service_register_print)\.php$ - [L]
RewriteRule ^tools/(backup|backup_download|restore_web)\.php$ - [L,NC]

# Blanket block for internal directories:
RewriteRule ^(config|tools|tmp|backups|app|views|src|scratch)/ - [F,NC]
```

> **Notice**: Whenever a new printable report or standalone page is created inside `views/pages/`, its name must be added to the whitelist regex above, otherwise Apache will block it with HTTP `403 Forbidden`.

---

## 6. Developer Guidelines & Extension Cheatsheet

### Adding a New Section to the Admin Dashboard
1. **Sidebar Navigation**: Add a navigation link in `views/pages/dashboard.php`:
   ```html
   <a data-section="my_section" href="dashboard.php?section=my_section">
       <i class="fa-solid fa-folder"></i> My Section
   </a>
   ```
2. **Controller Routing**: In `app/controllers/DashboardController.php`, add a new case in `loadViewData()`:
   ```php
   case 'my_section':
       $this->viewData = $this->sectionDataService->loadMySectionData($this->chapelId);
       break;
   ```
3. **Data Loading**: In `app/services/DashboardSectionDataService.php`, implement `loadMySectionData($chapelId)`.
4. **View Partial**: Create the markup file under `views/partials/dashboard/my_section.php`.
5. **AJAX Switcher**: In `views/partials/dashboard/ajax_renderer.php`, include your partial under `'my_section'`.

### Querying Data with Chapel Isolation
Always inject `$chapelFilter` into models to respect user permissions:
```php
public function getRecords(?int $chapelId = null): array {
    $sql = "SELECT * FROM records WHERE is_archived = 0";
    if ($chapelId !== null) {
        $sql .= " AND chapel_id = " . (int)$chapelId;
    }
    return $this->conn->query($sql)->fetch_all(MYSQLI_ASSOC);
}
```

### Logging User Operations
Call `AuditLogger` on any state-changing POST action:
```php
AuditLogger::logFromSession(
    $this->conn,
    'ACTION_NAME',
    'table_or_module_name',
    'Human readable description of the change'
);
```
