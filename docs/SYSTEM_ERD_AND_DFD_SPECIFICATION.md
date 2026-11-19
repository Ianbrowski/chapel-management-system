# Cathedral Management System — Complete ERD & DFD Technical Specification

> **System**: Iglesia Filipina Independiente (IFI) — Cathedral of St. Michael the Archangel  
> **Database**: MariaDB / MySQL (38 Core Tables, 7 Functional Clusters)  
> **Purpose**: Complete Data Flow Diagram (DFD) and Entity-Relationship Diagram (ERD) Reference Guide for developers, auditors, and AI models.

---

## Table of Contents
1. [DFD Level 0: Context Diagram](#1-dfd-level-0-context-diagram)
2. [DFD Level 1: System Decomposition Diagram](#2-dfd-level-1-system-decomposition-diagram)
3. [DFD Level 2: Detailed Subsystem Workflows](#3-dfd-level-2-detailed-subsystem-workflows)
   - [3.1 Sacramental Record & Certificate Issuance](#31-sacramental-record--certificate-issuance-workflow)
   - [3.2 Event Booking & Priest Assignment](#32-event-booking--priest-assignment-workflow)
   - [3.3 Financial Donations & Store Operations](#33-financial-donations--store-operations-workflow)
4. [DFD Data Dictionary: Entities, Stores & Flows](#4-dfd-data-dictionary)
5. [Master Cross-Module ERD Architecture](#5-master-cross-module-erd-architecture)
6. [Optimized ERDs by Section Module (All 38 Tables)](#6-optimized-erds-by-section-module)
   - [Module 1: Users & Chapels (`users`, `chapels`, `my_profile`)](#module-1-users--chapels)
   - [Module 2: Services (`services`, `settings?tab=services`)](#module-2-services)
   - [Module 3: Calendar, Event Requests & Priest Duty (`events`, `event_requests`, `priest_schedule`)](#module-3-calendar-event-requests--priest-duty)
   - [Module 4: Church Records & Certificates (`records_cathedral`, `records_chapel`, `request_certificate`)](#module-4-church-records--certificates)
   - [Module 5: Giving & Store — Donations & Parish Store (`donations`, `merchandise`, `reports`)](#module-5-giving--store--donations--parish-store)
   - [Module 6: Inquiries, Tickets & Parish Chat (`public_inquiries`, `tickets`, `chat`)](#module-6-inquiries-tickets--parish-chat)
   - [Module 7: Bulletin & Parish Profile (`bulletin`, `parish_info`, `index.php`)](#module-7-bulletin--parish-profile)
   - [Module 8: Backups & Audit Logs (`audit_logs`, `backups`, `system_settings`)](#module-8-backups--audit-logs)
7. [Database Optimization & Indexing Blueprint](#7-database-optimization--indexing-blueprint)
8. [Physical Database Schema Reference (38 Tables)](#8-physical-database-schema-reference)

---

## 1. DFD Level 0: Context Diagram

The Context Diagram defines the system boundary, external actors (entities), and high-level data flows into and out of the single central process.

```mermaid
flowchart TD
    %% External Entities
    E1["👤 E1: Parishioner / Public Visitor"]
    E2["⛪ E2: Parish Staff & Admin"]
    E3["✝️ E3: Clergy & Parish Priests"]
    E4["🏛️ E4: Diocesan / Chapel Officers"]
    E5["💳 E5: Digital Payment Gateways (GCash / Maya / Bank)"]

    %% Central Process
    P0(("0.0<br/>Cathedral Management<br/>System"))

    %% Flows to/from Parishioner
    E1 -->|"F1.1: Registration / Login Credentials"| P0
    E1 -->|"F1.2: Event & Mass Booking Requests"| P0
    E1 -->|"F1.3: Certificate Issuance Requests"| P0
    E1 -->|"F1.4: Inquiries, Tickets & Store Orders"| P0
    P0 -->|"F1.5: Booking Confirmations & Status Updates"| E1
    P0 -->|"F1.6: Official Certificates (PDF / Print)"| E1
    P0 -->|"F1.7: Public Notices & Mass Schedules"| E1

    %% Flows to/from Admin & Staff
    E2 -->|"F2.1: Sacramental Record Entries & Edits"| P0
    E2 -->|"F2.2: Event Approvals & Priest Assignments"| P0
    E2 -->|"F2.3: Manual Donation & Expense Logging"| P0
    E2 -->|"F2.4: System Backups & Settings Configuration"| P0
    P0 -->|"F2.5: Financial Statements & Audit Logs"| E2
    P0 -->|"F2.6: Member Verification & Request Dockets"| E2

    %% Flows to/from Clergy
    E3 -->|"F3.1: Schedule Availability & Shift Confirmations"| P0
    E3 -->|"F3.2: Liturgical Notes & Pastoral Signoffs"| P0
    P0 -->|"F3.3: Priest Duty Rosters & Mass Itineraries"| E3
    P0 -->|"F3.4: Sacramental Dossiers (Weddings/Baptisms)"| E3

    %% Flows to/from Diocesan Officers
    E4 -->|"F4.1: Chapel Profile Updates & Fiesta Schedules"| P0
    P0 -->|"F4.2: Diocesan Clergy Directory & Chapel Statistics"| E4

    %% Flows to/from Payment Gateways
    E1 -.->|"F5.1: Digital Payment Submission"| E5
    E5 -->|"F5.2: Webhook / Reference Verification"| P0
    P0 -->|"F5.3: Official Donation / Merchandise Receipt"| E1
```

---

## 2. DFD Level 1: System Decomposition Diagram

Level 1 breaks the central system into **7 Core Processes** connected to **7 Canonical Data Stores (D1–D7)**.

```mermaid
flowchart TD
    %% Entities
    E1["E1: Parishioner"]
    E2["E2: Parish Admin / Staff"]
    E3["E3: Clergy / Priest"]
    E5["E5: Payment Gateway"]

    %% Data Stores
    D1[("D1: Users & Chapels")]
    D2[("D2: Chapels & Fiestas")]
    D3[("D3: Church Records & Certificates")]
    D4[("D4: Services & Calendar Events")]
    D5[("D5: Donations & Parish Store")]
    D6[("D6: Inquiries, Tickets & Parish Chat")]
    D7[("D7: Bulletin, Parish Profile & Audit Logs")]

    %% Process 1.0: Users & Access Control
    P1(("1.0<br/>Users &<br/>Access Control"))
    E1 -->|"Credentials / Self-Reg"| P1
    E2 -->|"Staff Auth"| P1
    E3 -->|"Clergy Auth"| P1
    P1 <-->|"Validate Credentials / Role Scoping"| D1
    P1 -->|"Security Audit Trail"| D7

    %% Process 2.0: Church Records & Certificates
    P2(("2.0<br/>Church Records &<br/>Certificates"))
    E2 -->|"Encode Sacrament (Book/Page/Ref)"| P2
    P2 <-->|"Query Canonical Books / Verify Person"| D3
    P2 <-->|"Scope Records to Parish/Chapel"| D2
    E1 -->|"Request Certificate"| P2
    P2 -->|"Log Issuance Serial & Print Voucher"| D3
    P2 -->|"Record Mutation Audit"| D7
    P2 -->|"Generate Certificate (Baptism/Confirmation/Marriage)"| E1

    %% Process 3.0: Services & Events (Calendar, Event Requests & Priest Duty)
    P3(("3.0<br/>Services, Calendar &<br/>Event Requests"))
    E1 -->|"Submit Booking Request"| P3
    P3 <-->|"Fetch Rates, Add-ons & Document Checklist"| D4
    E2 -->|"Approve Request / Set Fee"| P3
    E3 -->|"Priest Duty Confirmation"| P3
    P3 <-->|"Assign Priest, Time Slot & Chapel"| D4
    P3 <-->|"Verify Chapel Capacity"| D2
    P3 -->|"Dispatch Booking Schedule Notification"| E1
    P3 -->|"Push Calendar Itinerary"| E3

    %% Process 4.0: Giving & Store (Donations & Parish Store)
    P4(("4.0<br/>Giving & Store<br/>(Donations & Store)"))
    E1 -->|"Donate (GCash/Maya/Bank/Cash)"| P4
    E5 -->|"Transaction Ref & Verification"| P4
    E1 -->|"Reserve / Buy Keepsake Item"| P4
    E2 -->|"Log Manual Offering / Void Entry"| P4
    P4 <-->|"Update Cash Ledger & Item Stock"| D5
    P4 -->|"Financial Audit Entry"| D7
    P4 -->|"Generate Donation / Merch Receipt"| E1
    P4 -->|"Generate Financial Reports"| E2

    %% Process 5.0: Inquiries, Tickets & Parish Chat
    P5(("5.0<br/>Inquiries, Tickets<br/>& Parish Chat"))
    E1 -->|"Submit Inquiry / Support Ticket"| P5
    E2 -->|"Assign Ticket / Post Comment"| P5
    E1 <-->|"Chat Messages & Uploads"| P5
    E3 <-->|"Priest Group Chat"| P5
    P5 <-->|"Store Tickets, Comments & Messages"| D6
    P5 <-->|"Resolve User/Chapel Reference"| D1

    %% Process 6.0: Chapels
    P6(("6.0<br/>Chapels &<br/>Fiesta Schedules"))
    E2 -->|"Update Chapel Coordinators & Fiestas"| P6
    P6 <-->|"Sync Chapel Registry & Schedules"| D2
    P6 -->|"Publish Mass Schedules"| D7

    %% Process 7.0: Bulletin & Parish Profile
    P7(("7.0<br/>Bulletin &<br/>Parish Profile"))
    E2 -->|"Publish Parish Bulletin / History Intro"| P7
    P7 <-->|"Read/Write Bulletin Posts & History"| D7
    P7 -->|"Render Public Landing Page"| E1
```

---

## 3. DFD Level 2: Detailed Subsystem Workflows

### 3.1 Sacramental Record & Certificate Issuance Workflow

```mermaid
flowchart TD
    E1["Parishioner / Requester"]
    E2["Parish Record Encoder / Staff"]
    D3[("D3: Sacramental Registry")]
    D1[("D1: Users Store")]
    D7[("D7: Audit Logs Store")]

    P2_1(("2.1<br/>Canonical Data<br/>Validation"))
    P2_2(("2.2<br/>Record Encoding<br/>& Indexing"))
    P2_3(("2.3<br/>Registry Search<br/>& Verification"))
    P2_4(("2.4<br/>Certificate Number<br/>Generation"))
    P2_5(("2.5<br/>Issuance Logging<br/>& Print Release"))

    %% Encode Workflow
    E2 -->|"Physical Parish Register (Book, Page, Entry No)"| P2_1
    P2_1 -->|"Validated Sacramental Details"| P2_2
    P2_2 -->|"Insert Record (Person, Parents, Minister, Sponsors)"| D3
    P2_2 -->|"Log Record Encoding Action"| D7

    %% Certificate Request Workflow
    E1 -->|"Certificate Request (Name, Date, Sacrament)"| P2_3
    D3 -->|"Matching Canonical Record"| P2_3
    P2_3 -->|"Verified Record ID"| P2_4
    P2_4 -->|"Generate Unique Serial (e.g. CERT-2026-0042)"| P2_5
    P2_5 -->|"Insert issuance_id, purpose, issued_to, issued_by"| D3
    D1 -->|"Fetch Signoff Priest/Officer Details"| P2_5
    P2_5 -->|"Print-Ready Official Certificate"| E1
```

### 3.2 Event Booking & Priest Assignment Workflow

```mermaid
flowchart TD
    E1["Parishioner"]
    E2["Parish Office Admin"]
    E3["Assigned Priest"]
    D2[("D2: Chapels Network")]
    D4[("D4: Services & Events")]

    P3_1(("3.1<br/>Intake & Document<br/>Prerequisites"))
    P3_2(("3.2<br/>Conflict Checking<br/>& Slot Hold"))
    P3_3(("3.3<br/>Priest Assignment<br/>& Rate Calculation"))
    P3_4(("3.4<br/>Booking Confirmation<br/>& Calendar Commit"))

    E1 -->|"Service Choice, Date & Attached Docs"| P3_1
    P3_1 <-->|"Verify Requirements (Birth/Baptismal Certs)"| D4
    P3_1 -->|"Tentative Booking"| P3_2
    P3_2 <-->|"Check Chapel Availability"| D2
    P3_2 <-->|"Check Priest Schedule Availability"| D4
    P3_2 -->|"Clear Slot Docket"| E2
    E2 -->|"Assign Officiating Priest & Add-on Fees"| P3_3
    P3_3 -->|"Duty Notification"| E3
    E3 -->|"Acknowledge & Confirm Roster"| P3_3
    P3_3 -->|"Committed Event Details"| P3_4
    P3_4 -->|"Persist to events & service_request_addons"| D4
    P3_4 -->|"Official Booking Confirmation Notice"| E1
```

### 3.3 Financial Donations & Store Operations Workflow

```mermaid
flowchart TD
    E1["Parishioner / Donor"]
    E2["Parish Treasurer / Staff"]
    E5["Payment Rails (GCash / Maya / Bank)"]
    D5[("D5: Financial Ledgers & Store")]
    D7[("D7: Audit Trail")]

    P4_1(("4.1<br/>Payment Intake<br/>& Channel Routing"))
    P4_2(("4.2<br/>Reference Validation<br/>& Verification"))
    P4_3(("4.3<br/>Ledger Posting<br/>& Inventory Update"))
    P4_4(("4.4<br/>Receipt Issuance<br/>& Financial Reporting"))

    E1 -->|"Select Offering Type (Tithe, Mass Stipend, Keepsake)"| P4_1
    P4_1 -->|"Digital Gateway Redirect"| E5
    E5 -->|"Transaction Ref & Approval"| P4_2
    E2 -->|"Manual Cash Envelope Input"| P4_2
    P4_2 -->|"Verified Transaction Payload"| P4_3
    P4_3 -->|"Insert donations / Deduct merchandise_items stock"| D5
    P4_3 -->|"Log Transaction Entry"| D7
    P4_3 -->|"Generate Printable Payment Receipt"| P4_4
    P4_4 -->|"Official PDF/Printed Receipt"| E1
    P4_4 -->|"Aggregated Income Reports"| E2
```

---

## 4. DFD Data Dictionary

### Data Stores (D1–D7)

| Store ID | Name | Physical Tables | Primary Purpose |
| :--- | :--- | :--- | :--- |
| **D1** | **Users & Chapels** | `users`, `system_notifications`, `notifications` | Stores credentials, user profiles, role-based authorization levels (`Admin`, `Cathedral Admin`, `Chapel Admin`, `Priest`, `Staff`, `Member`), and user/SMS alerts. |
| **D2** | **Chapels & Fiestas** | `chapels`, `chapel_fiestas` | Stores chapel directory, geographic locations, patron saints, seating capacities, and annual patronal fiesta schedules. |
| **D3** | **Church Records & Certificates** | `sacramental_records`, `certificate_types`, `certificate_issuances`, `sacramental_audit_log` | Stores church records (Baptism, Confirmation, Wedding, Funeral, Communion), register indexes, certificate issuance serials, and historical edits. |
| **D4** | **Services & Calendar Events** | `service_categories`, `services`, `service_variants`, `service_requirements`, `service_additional_charges`, `service_schedules`, `events`, `service_request_addons`, `schedules`, `mass_schedules`, `pastor_event_types` | Stores service rates, requirements, add-on charges, calendar bookings, event requests, priest duty schedules, and mass schedules. |
| **D5** | **Donations & Parish Store** | `donations`, `merchandise_items`, `merchandise_sales` | Stores donations, tithes, payment references, void tracking, and Parish Store items & sales. |
| **D6** | **Inquiries, Tickets & Parish Chat** | `public_inquiries`, `tickets`, `ticket_comments`, `parish_chat_rooms`, `parish_chat_participants`, `parish_chat_messages` | Stores public inquiries, support tickets, ticket comments, and Parish Chat rooms, participants, and messages. |
| **D7** | **Bulletin, Parish Profile & Audit Logs** | `bulletin_posts`, `parish_contact_info`, `parish_history`, `parish_history_intro`, `parish_leadership`, `parish_leadership_intro`, `audit_logs`, `backup_logs`, `system_settings` | Stores bulletin posts, Parish Profile contact info, parish history, leadership directory, audit logs, backup logs, and system settings. |

### External Entities (E1–E5)

| Entity ID | Entity Name | Description | Key Interactions |
| :--- | :--- | :--- | :--- |
| **E1** | **Parishioner / Public Visitor** | Cathedral members, visitors, and sacramental applicants. | Books services, requests certificates, donates, orders items, submits inquiries. |
| **E2** | **Parish Staff & Admin** | Cathedral office administrators, secretaries, and encoders. | Encodes registry books, manages bookings, approves members, logs finances, triggers backups. |
| **E3** | **Clergy & Parish Priests** | Cathedral Dean, parish priests, and visiting clergy. | Receives duty rosters, reviews sacramental dossiers, confirms Mass availability. |
| **E4** | **Diocesan / Chapel Officers** | Chapel coordinators, parish council heads. | Synchronizes chapel profiles, coordinates fiesta masses, reviews diocesan reports. |
| **E5** | **Payment Gateways** | External digital rails (GCash, Maya, Bank transfers). | Verifies payment references, provides settlement verification for donations and merchandise. |

---

## 5. Master Cross-Module ERD Architecture

The system database comprises **38 physical tables** organized across **8 Dashboard & Portal Section Modules**. Every tenant-scoped operational table connects back to two central anchor entities:
- **`chapels` (`chapel_id`)**: Enforces multi-chapel data isolation (`NULL` = Main Cathedral scope, `>0` = Satellite Chapel branch).
- **`users` (`id`)**: Tracks actor accountability (`requested_by`, `pastor_id`, `encoded_by`, `logged_by`, `issued_by`, `assigned_to`, `performed_by_id`).

```mermaid
flowchart TB
    subgraph M1["🏛️ Module 1: Users & Chapels"]
        CHAPELS["chapels (PK: chapel_id)"]
        FIESTAS["chapel_fiestas (PK: fiesta_id)"]
        USERS["users (PK: id)"]
        SYSNOTIF["system_notifications (PK: id)"]
        NOTIF["notifications (PK: notif_id)"]
    end

    subgraph M2["📜 Module 2: Services"]
        SRVCAT["service_categories (PK: id)"]
        SRV["services (PK: id)"]
        SRVVAR["service_variants (PK: id)"]
        SRVREQ["service_requirements (PK: id)"]
        SRVCHG["service_additional_charges (PK: id)"]
        SRVSCH["service_schedules (PK: id)"]
        CERTTYP["certificate_types (PK: id)"]
    end

    subgraph M3["📅 Module 3: Calendar, Event Requests & Priest Duty"]
        EVENTS["events (PK: event_id)"]
        ADDONS["service_request_addons (PK: id)"]
        SCHED["schedules (PK: schedule_id)"]
        PASTEV["pastor_event_types (PK: id)"]
        MASSSCH["mass_schedules (PK: schedule_id)"]
    end

    subgraph M4["🕊️ Module 4: Church Records & Certificates"]
        SACREC["sacramental_records (PK: record_id)"]
        CERTISS["certificate_issuances (PK: issuance_id)"]
        SACAUD["sacramental_audit_log (PK: audit_id)"]
    end

    subgraph M5["💰 Module 5: Giving & Store (Donations & Parish Store)"]
        DON["donations (PK: donation_id)"]
        MITEM["merchandise_items (PK: id)"]
        MSALE["merchandise_sales (PK: id)"]
    end

    subgraph M6["💬 Module 6: Inquiries, Tickets & Parish Chat"]
        INQ["public_inquiries (PK: inquiry_id)"]
        TCK["tickets (PK: ticket_id)"]
        TCKCOM["ticket_comments (PK: comment_id)"]
        CROOM["parish_chat_rooms (PK: room_id)"]
        CPART["parish_chat_participants (PK: room_id, user_id)"]
        CMSG["parish_chat_messages (PK: message_id)"]
    end

    subgraph M7["🌐 Module 7: Bulletin & Parish Profile"]
        BULL["bulletin_posts (PK: post_id)"]
        PINFO["parish_contact_info (PK: info_id)"]
        PHIST["parish_history / intro"]
        PLEAD["parish_leadership / intro"]
    end

    subgraph M8["🛡️ Module 8: Backups & Audit Logs"]
        AUDLOG["audit_logs (PK: log_id)"]
        BKLOG["backup_logs (PK: id)"]
        SYSSET["system_settings (PK: setting_key)"]
    end

    %% Core Hub Connections
    CHAPELS --> USERS
    CHAPELS --> FIESTAS
    CHAPELS --> NOTIF
    CHAPELS --> EVENTS
    CHAPELS --> SCHED
    CHAPELS --> SACREC
    CHAPELS --> DON
    CHAPELS --> INQ
    CHAPELS --> TCK
    CHAPELS --> CROOM
    CHAPELS --> BULL
    CHAPELS --> AUDLOG

    USERS --> SYSNOTIF
    USERS --> EVENTS
    USERS --> SCHED
    USERS --> PASTEV
    USERS --> MASSSCH
    USERS --> SACREC
    USERS --> CERTISS
    USERS --> SACAUD
    USERS --> DON
    USERS --> MSALE
    USERS --> TCK
    USERS --> TCKCOM
    USERS --> CROOM
    USERS --> CPART
    USERS --> CMSG
    USERS --> BULL
    USERS --> PINFO
    USERS --> PHIST
    USERS --> PLEAD
    USERS --> AUDLOG
    USERS --> BKLOG
    USERS --> SYSSET

    %% Intra-Module Flows
    SRVCAT --> SRV
    SRV --> SRVVAR
    SRV --> SRVREQ
    SRV --> SRVCHG
    SRV --> SRVSCH
    SRV --> CERTTYP
    SRV --> EVENTS
    SRVVAR --> EVENTS
    SRVCHG --> ADDONS
    EVENTS --> ADDONS
    SACREC --> CERTISS
    SACREC --> SACAUD
    MITEM --> MSALE
    MITEM --> INQ
    TCK --> TCKCOM
    CROOM --> CPART
    CROOM --> CMSG
```

---

## 6. Optimized ERDs by Section Module

> **Notation Key**:
> - `PK` = Primary Key | `FK` = Foreign Key | `UK` = Unique Key
> - Solid lines (`||--o{`, `|o--o{`) represent **Database-Enforced Foreign Key Constraints** (`56 Enforced InnoDB Foreign Keys • 0 Floating Tables` with `ON DELETE SET NULL` / `ON DELETE CASCADE` / `ON DELETE RESTRICT`).
> - Dotted lines (`|o..o{`) represent **Intentional Soft/Audit References** (`sacramental_audit_log.record_id` to preserve deleted audit history, and `parish_chat_participants.last_read_message_id` watermark).

---

### Module 1: Users & Chapels
* **Dashboard Sections**: `section=users`, `section=chapels`, `section=my_profile`, `section=settings&tab=users|chapels`
* **Controllers & Models**: `AuthController`, `AdminController`, `User`, `Chapel`, `SmsService`
* **Optimization Design**: `chapels` acts as the multi-tenant root entity. Every branch can override SMTP credentials (`smtp_host`, `from_email`) and visual branding (`theme_color`, `branch_color`). `users` stores role-scoped access (`Admin`, `Cathedral Admin`, `Chapel Admin`, `Priest`, `Pastor`, `Staff`, `Member`) plus brute-force lockout counters (`login_attempts`, `locked_until`) and priest duty windows (`duty_start_time`, `duty_end_time`).

```mermaid
erDiagram
    chapels |o--o{ users : "assigns branch (fk_users_chapel)"
    chapels ||--o{ chapel_fiestas : "celebrates patronal fiestas"
    chapels |o--o{ notifications : "scopes SMS/email dispatches"
    users ||--o{ system_notifications : "receives in-app alerts"

    chapels {
        int chapel_id PK "Auto-increment branch ID"
        varchar chapel_name "Branch or Cathedral name"
        enum branch_type "chapel | cathedral"
        varchar location "Geographic address"
        varchar patron_saint "Titular patron saint"
        int seating_capacity "Venue capacity (default 50)"
        varchar coordinator_name "Lay coordinator"
        varchar coordinator_phone "Coordinator contact"
        enum status "Active | Maintenance"
        varchar sunday_service_time "Default Sunday Mass label"
        varchar theme_color "Hex accent color"
        varchar branch_color "Calendar badge color"
        varchar chapel_image "Thumbnail asset path"
        varchar branch_banner "Hero banner path"
        varchar smtp_host "Per-chapel SMTP override"
        int smtp_port "SMTP port"
        varchar smtp_user "SMTP username"
        varchar smtp_pass "SMTP secret"
        varchar smtp_secure "tls | ssl"
        varchar from_email "Sender email address"
        varchar from_name "Sender display name"
    }

    chapel_fiestas {
        int fiesta_id PK
        int chapel_id FK "References chapels.chapel_id"
        varchar fiesta_name "Feast title"
        varchar fiesta_date "Annual celebration date"
        tinyint is_patronal "1 = Principal patronal feast"
        timestamp created_at
    }

    users {
        int id PK "Auto-increment user ID"
        varchar user "Login username"
        varchar password "Bcrypt password hash"
        varchar role "Admin | Cathedral Admin | Chapel Admin | Priest | Staff | Member"
        varchar status "active | inactive | suspended"
        int chapel_id FK "NULL = Cathedral / Global, >0 = Chapel"
        varchar full_name "Display name"
        varchar email "Contact & recovery email"
        varchar phone "Mobile number for SMS"
        varchar profile_pic "Avatar path in storage/"
        text chapel_coverage "JSON/CSV multi-chapel coverage"
        time duty_start_time "Priest shift start (08:00:00)"
        time duty_end_time "Priest shift end (17:00:00)"
        int login_attempts "Brute-force counter"
        datetime locked_until "Account lockout expiry"
        datetime created_at
    }

    system_notifications {
        int id PK
        int user_id FK "References users.id"
        varchar title "Alert heading"
        text message "Notification body"
        varchar type "event | inquiry | record | system"
        int reference_id "Polymorphic target ID"
        tinyint is_read "0 = Unread, 1 = Read"
        timestamp created_at
    }

    notifications {
        int notif_id PK
        int chapel_id FK "References chapels.chapel_id"
        varchar recipient_name "Parishioner name"
        varchar contact_info "Phone or email target"
        enum message_type "Reminder | Confirmation | Announcement"
        enum status "Pending | Sent | Failed"
        datetime sent_at
    }
```

---

### Module 2: Services
* **Dashboard Sections**: `section=services`, `section=settings&tab=services`
* **Controllers & Models**: `ServiceController`, `ServiceModel`
* **Optimization Design**: Normalizes the Cathedral Synod tariff schedule (effective since the December 16, 2012 Synod) into a 3NF hierarchy: `service_categories` → `services` → (`service_variants`, `service_requirements`, `service_additional_charges`, `service_schedules`). Historical tariff versioning is supported via `effective_from`, `effective_until`, and `is_current` on `service_variants`.

```mermaid
erDiagram
    service_categories ||--o{ services : "groups by liturgical category"
    services ||--o{ service_variants : "defines pricing tiers"
    services ||--o{ service_requirements : "mandates documentary checklist"
    services ||--o{ service_additional_charges : "configures optional add-ons"
    services ||--o{ service_schedules : "restricts allowed time windows"
    services |o--o{ certificate_types : "defines certificate copy rates"

    service_categories {
        int id PK
        varchar code UK "Unique category slug"
        varchar name "Category display title"
        text description
        int display_order "UI sort sequence"
        enum status "active | inactive"
        timestamp created_at
    }

    services {
        int id PK
        int category_id FK "References service_categories.id"
        varchar code UK "Canonical service code"
        varchar name "Sacrament or rite name"
        text description
        varchar reference_source "Synod tariff authority"
        date effective_date "Tariff effective date"
        tinyint is_reference_only "1 = Informational, 0 = Bookable"
        enum status "active | inactive"
        timestamp created_at
        timestamp updated_at
    }

    service_variants {
        int id PK
        int service_id FK "References services.id"
        varchar name "Tier (Standard, Solemn, Special)"
        varchar description
        decimal price "Base tariff amount DECIMAL(10,2)"
        varchar schedule_type "Standard | Special | Custom"
        int included_sponsors "Free principal sponsors count"
        date effective_from "Rate start date"
        date effective_until "Rate expiration date"
        tinyint is_current "1 = Active rate version"
        enum status "active | inactive"
        timestamp created_at
        timestamp updated_at
    }

    service_requirements {
        int id PK
        int service_id FK "References services.id"
        varchar requirement "Document name (e.g. PSA Birth Cert)"
        tinyint is_required "1 = Mandatory, 0 = Conditional"
        text description "Submission instructions"
        enum status "active | inactive"
        timestamp created_at
    }

    service_additional_charges {
        int id PK
        int service_id FK "References services.id"
        varchar name "Add-on label (Extra Sponsor, Choir)"
        varchar description
        decimal price "Unit fee DECIMAL(10,2)"
        tinyint is_optional "1 = Optional, 0 = Mandatory surcharge"
        enum status "active | inactive"
        timestamp created_at
    }

    service_schedules {
        int id PK
        int service_id FK "References services.id"
        varchar schedule_type "Standard | Mass | Exclusive"
        varchar day_of_week "Sundays | Tue-Sat | Daily"
        time start_time "Window opening time"
        time end_time "Window closing time"
        varchar description
        enum status "active | inactive"
        timestamp created_at
    }

    certificate_types {
        int id PK
        int service_id FK "References services.id (Ecclesiastical Certificates)"
        varchar name UK "Baptismal | Confirmation | Marriage | Burial"
        decimal price_per_copy "Fee per copy DECIMAL(10,2)"
        enum status "active | inactive"
        timestamp created_at
        timestamp updated_at
    }
```

---

### Module 3: Calendar, Event Requests & Priest Duty
* **Dashboard Sections**: `section=events`, `section=overview`, `section=event_requests`, `section=request_event`, `section=priest_schedule`, `section=priest_prefs`, `section=schedule_audit`, `section=schedule_cathedral`, `section=schedule_chapel`
* **Controllers, Models & Services**: `EventController`, `Event`, `EventConflictService`, `EventAnalyticsService`
* **Optimization Design**: `events` is the operational heart of liturgical scheduling. It links catalog pricing (`service_id`, `variant_id`) and line-item extras (`service_request_addons`) with venue capacity (`chapel_id`) and clergy availability (`pastor_id`). `EventConflictService` cross-checks `events`, `schedules`, and `users` (`duty_start_time`/`duty_end_time`) to prevent double-booking and enforce a 30-minute inter-chapel travel buffer.

```mermaid
erDiagram
    chapels |o--o{ events : "hosts liturgy (fk_events_chapel_id)"
    users |o--o{ events : "officiates as priest (fk_events_pastor_id)"
    users |o--o{ events : "submits booking (fk_events_requested_by_id)"
    services |o--o{ events : "defines rite template"
    service_variants |o--o{ events : "sets base tariff tier"
    events ||--o{ service_request_addons : "itemizes selected add-ons"
    service_additional_charges ||--o{ service_request_addons : "sources unit price"
    chapels |o--o{ schedules : "allocates auxiliary slot"
    users |o--o{ schedules : "blocks priest duty shift"
    users ||--o{ pastor_event_types : "declares allowed rites"
    users |o--o{ mass_schedules : "updates public mass times"

    events {
        int event_id PK
        varchar title "Liturgical event title"
        enum event_type "Mass | Wedding | Baptism | Funeral | Other"
        int service_id FK "References services.id"
        int variant_id FK "References service_variants.id"
        datetime start_time "Scheduled date and start time"
        int custom_duration "Duration override in minutes"
        int chapel_id FK "NULL = Cathedral, >0 = Chapel branch"
        varchar chapel_location "Fallback/external location label"
        enum location_type "Church | Outside"
        varchar event_scope "cathedral | chapel"
        int pastor_id FK "Assigned officiating priest (users.id)"
        varchar pastor_assigned "Snapshot of priest name"
        decimal service_fee "Snapshot base fee DECIMAL(10,2)"
        decimal addons_fee "Sum of add-ons DECIMAL(10,2)"
        decimal total_fee "Computed grand total DECIMAL(10,2)"
        enum status "Pending | Confirmed | Completed | Cancelled | Rejected"
        int approval_level "0 = Intake, 1 = Staff Reviewed, 2 = Final"
        int requested_by FK "Applicant user ID (users.id)"
        varchar requester_email "Applicant email"
        varchar requester_phone "Applicant phone for SMS"
        varchar rejection_note "Reason if Rejected/Cancelled"
        timestamp approved_at "Confirmation timestamp"
        tinyint is_archived "0 = Active, 1 = Archived"
        timestamp created_at
        timestamp updated_at
    }

    service_request_addons {
        int id PK
        int event_id FK "References events.event_id"
        int addon_id FK "References service_additional_charges.id"
        varchar addon_name "Snapshot add-on name"
        decimal unit_price "Snapshot unit price DECIMAL(10,2)"
        int quantity "Selected quantity"
        decimal subtotal "unit_price * quantity DECIMAL(10,2)"
        timestamp created_at
    }

    schedules {
        int schedule_id PK
        int chapel_id FK "References chapels.chapel_id"
        int pastor_id FK "References users.id"
        varchar event_title "Duty block or liturgy title"
        datetime start_time "Shift start datetime"
        datetime end_time "Shift end datetime"
        enum status "Pending | Confirmed | Cancelled"
    }

    pastor_event_types {
        int id PK
        int pastor_id FK "References users.id"
        varchar event_type "Rite specialization (e.g. Wedding, Baptism)"
    }

    mass_schedules {
        int schedule_id PK
        varchar day_type "Sunday | Weekday | Saturday | Holy Day"
        varchar schedule_time "Formatted time slot (e.g. 6:00 AM - 7:00 AM)"
        int sort_order "Display sequence"
        tinyint is_active "1 = Visible on portal"
        int updated_by FK "References users.id"
        timestamp updated_at
    }
```

---

### Module 4: Church Records & Certificates
* **Dashboard Sections**: `section=records_cathedral`, `section=records_chapel`, `section=request_certificate`
* **Controllers, Models & Services**: `SacramentalController`, `SacramentalRecord`, `SacramentalCertificateService`, `SacramentalReportingService`
* **Optimization Design**: Uses **Single-Table Inheritance (STI)** in `sacramental_records` (`record_type` discriminator: `Baptism`, `Wedding`, `Funeral`, `Confirmation`, `Communion`) so that unified full-text searches, canonical Book/Page indexing (`book_number`, `page_number`, `reference_id`), certificate serial generation (`certificate_issuances`), and row-level JSON snapshot auditing (`sacramental_audit_log`) operate across a single indexed table without expensive multi-table `UNION` queries.

```mermaid
erDiagram
    chapels |o--o{ sacramental_records : "scopes canonical book (fk_records_chapel)"
    users |o--o{ sacramental_records : "encodes entry (fk_records_encoder)"
    users |o--o{ sacramental_records : "last updated by"
    sacramental_records ||--o{ certificate_issuances : "issues official copy (fk_cert_issuances_record)"
    users |o--o{ certificate_issuances : "signs/releases (fk_cert_issuances_user)"
    sacramental_records ||..o{ sacramental_audit_log : "logs immutable JSON diff"
    users |o--o{ sacramental_audit_log : "triggers audit event"

    sacramental_records {
        int record_id PK
        varchar reference_id UK "Unique canonical ref (e.g. BAP-2026-0001)"
        enum scope "cathedral | chapel"
        int chapel_id FK "NULL = Cathedral, >0 = Chapel branch"
        enum record_type "Baptism | Wedding | Funeral | Confirmation | Communion"
        varchar sacrament_type "Detailed rite classification"
        varchar person_name "Primary indexed subject name"
        date event_date "Date sacrament was administered"
        varchar book_number "Canonical Registry Book No."
        varchar page_number "Canonical Folio / Page No."
        varchar officiating_priest "Minister who administered sacrament"
        varchar child_name "Baptism: Child full name"
        date date_of_birth "Baptism: Birth date"
        varchar place_of_birth "Baptism: Birthplace"
        varchar father_name "Baptism/Confirmation: Father"
        varchar mother_name "Baptism/Confirmation: Mother"
        varchar baptism_parents_residence "Baptism: Parents address"
        varchar baptism_legitimacy "Baptism: Civil/canonical status"
        varchar godfather "Baptism: Principal male sponsor"
        varchar godmother "Baptism: Principal female sponsor"
        varchar confirmation_name "Confirmation: Confirmand name"
        varchar confirmation_sponsor "Confirmation: Sponsor name"
        varchar confirmation_place_of_baptism "Confirmation: Parish of baptism"
        varchar groom_name "Wedding: Groom full name"
        varchar bride_name "Wedding: Bride full name"
        varchar wedding_groom_parents "Wedding: Groom parents"
        varchar wedding_bride_parents "Wedding: Bride parents"
        date date_of_marriage "Wedding: Marriage date"
        varchar place_of_marriage "Wedding: Sanctuary/Venue"
        varchar witness_1 "Wedding: Principal witness 1"
        varchar witness_2 "Wedding: Principal witness 2"
        varchar deceased_name "Funeral: Deceased full name"
        date date_of_death "Funeral: Date of death"
        int age_at_death "Funeral: Age in years"
        varchar funeral_cause_of_death "Funeral: Cause of death"
        varchar funeral_occupation "Funeral: Occupation"
        varchar funeral_marital_status "Funeral: Civil status"
        date date_of_funeral "Funeral: Necrological rite date"
        varchar place_of_burial "Funeral: Cemetery/Columbarium"
        text notes "Marginal annotations (e.g. subsequent marriage)"
        tinyint is_archived "0 = Active, 1 = Archived"
        int encoded_by FK "Encoder user ID (users.id)"
        timestamp date_added
        int updated_by FK "Editor user ID (users.id)"
        timestamp updated_at
    }

    certificate_issuances {
        int issuance_id PK
        int record_id FK "References sacramental_records.record_id (CASCADE)"
        varchar certificate_no "Incrementing serial number"
        varchar issued_to "Name of requester"
        varchar purpose "Civil registry, Marriage, School, Passport"
        int issued_by FK "Issuing staff user ID (users.id)"
        timestamp issued_at
    }

    sacramental_audit_log {
        int audit_id PK
        int record_id FK "References sacramental_records.record_id"
        enum action "created | updated | deleted | printed | viewed"
        int performed_by FK "Actor user ID (users.id)"
        timestamp performed_at
        longtext_json old_snapshot "JSON row state before mutation"
        longtext_json new_snapshot "JSON row state after mutation"
        varchar ip_address "Client IPv4/IPv6"
        varchar user_agent "Browser signature"
    }
```

---

### Module 5: Giving & Store (Donations & Parish Store)
* **Dashboard Sections**: `section=donations`, `section=merchandise`, `section=reports`, `section=insights`
* **Controllers, Models & Views**: `FinanceController`, `Donation`, `Merchandise`, `donation_receipt.php`, `merchandise_receipt.php`, `finance_report_print.php`
* **Optimization Design**: Enforces strict financial accounting rules—`donations` never hard-deletes posted entries; instead it uses non-destructive voiding (`is_archived`, `void_reason`, `voided_by`, `voided_at`) and a `UNIQUE` index on `reference_id` (`idx_unique_ref`) to prevent duplicate receipt posting. `merchandise_items` and `merchandise_sales` power the Parish Store POS and online keepsake reservations.

```mermaid
erDiagram
    chapels |o--o{ donations : "credits branch ledger (fk_donations_chapel)"
    users |o--o{ donations : "records offering (fk_donations_user)"
    users |o--o{ donations : "authorizes void"
    merchandise_items ||--o{ merchandise_sales : "deducts inventory stock"
    users |o--o{ merchandise_sales : "processes POS checkout"

    donations {
        int donation_id PK
        varchar reference_id UK "Official Receipt / Txn Ref (idx_unique_ref)"
        varchar donor_ref_id "External GCash/Maya/Bank ref no."
        int chapel_id FK "NULL = Cathedral, >0 = Chapel branch"
        decimal amount "Offering amount DECIMAL(10,2)"
        varchar purpose "Tithe | Mass Intention | Building Fund | Stole Fee"
        varchar donor_name "Donor name or Anonymous"
        enum payment_method "Cash | GCash | Bank Transfer | Maya | Other"
        date date_received "Value date of contribution"
        int logged_by FK "Cashier/Treasurer user ID (users.id)"
        tinyint is_archived "0 = Valid, 1 = Voided/Archived"
        varchar void_reason "Mandatory explanation when voided"
        int voided_by FK "Auditor/Admin user ID (users.id)"
        datetime voided_at "Timestamp of void action"
    }

    merchandise_items {
        int id PK
        varchar name "Keepsake / religious item name"
        text description "Item details & specifications"
        decimal price "Unit retail price DECIMAL(10,2)"
        int stock_quantity "Available inventory count"
        varchar photo_path "Product image path"
        tinyint is_archived "0 = Active in store, 1 = Discontinued"
        timestamp created_at
        timestamp updated_at
    }

    merchandise_sales {
        int id PK
        int item_id FK "References merchandise_items.id"
        varchar buyer_name "Customer name"
        int quantity "Units purchased"
        decimal total_price "quantity * unit_price DECIMAL(10,2)"
        int processed_by FK "Staff cashier user ID (users.id)"
        timestamp sale_date
    }
```

---

### Module 6: Inquiries, Tickets & Parish Chat
* **Dashboard Sections**: `section=public_inquiries`, `TicketController`, `ParishChat` widget
* **Controllers & Models**: `InquiryController`, `TicketController`, `PortalController`, `Inquiry`, `Ticket`
* **Optimization Design**: Separates external parishioner intake (`public_inquiries` for online Mass intentions, general questions, and store item reservations via `item_id`), structured issue resolution (`tickets` + `ticket_comments` with `is_internal` staff notes), and real-time internal clergy/staff messaging (`parish_chat_rooms`, composite-key `parish_chat_participants`, and `parish_chat_messages` with read-receipt watermarks `last_read_message_id`).

```mermaid
erDiagram
    chapels |o--o{ public_inquiries : "routes inquiry to branch (fk_inquiries_chapel)"
    merchandise_items |o--o{ public_inquiries : "reserves store item"
    chapels |o--o{ tickets : "scopes issue (fk_tickets_chapel)"
    users |o--o{ tickets : "assigned staff (fk_tickets_assigned)"
    users |o--o{ tickets : "opened by requester"
    tickets ||--o{ ticket_comments : "contains thread replies"
    users |o--o{ ticket_comments : "authors comment"
    chapels |o--o{ parish_chat_rooms : "scopes channel"
    users |o--o{ parish_chat_rooms : "creates room"
    parish_chat_rooms ||--|{ parish_chat_participants : "enrolls members"
    users ||--o{ parish_chat_participants : "participates in"
    parish_chat_rooms ||--o{ parish_chat_messages : "streams messages"
    users ||--o{ parish_chat_messages : "sends message"

    public_inquiries {
        int inquiry_id PK
        int chapel_id FK "Target chapel (chapels.chapel_id)"
        varchar full_name "Sender full name"
        varchar email "Sender email for reply"
        varchar phone "Sender mobile for SMS"
        varchar request_type "Inquiry | Mass Intention | Store Reservation | Sacrament"
        varchar service_category "Specific rite or intention category"
        date target_date "Requested date of intention/pickup"
        int item_id FK "Optional store item (merchandise_items.id)"
        int quantity "Reserved item quantity"
        text details "Message body or intention names"
        varchar status "Pending | In Progress | Resolved | Closed"
        text admin_note "Staff resolution remarks"
        tinyint is_archived "0 = Active inbox, 1 = Archived"
        timestamp created_at
        timestamp updated_at
    }

    tickets {
        int ticket_id PK
        varchar ticket_no UK "Formatted docket (e.g. TCK-2026-001)"
        varchar subject "Issue summary"
        text description "Detailed report"
        varchar category "General | Schedule | Record | Technical"
        enum priority "Low | Medium | High | Urgent"
        enum status "Open | In Progress | Resolved | Closed"
        int requester_id FK "Optional member user ID (users.id)"
        varchar requester_name "Requester display name"
        varchar requester_email "Requester email"
        varchar requester_phone "Requester phone"
        int chapel_id FK "Branch scope (chapels.chapel_id)"
        int assigned_to FK "Assigned handler (users.id)"
        datetime resolved_at
        datetime created_at
        datetime updated_at
    }

    ticket_comments {
        int comment_id PK
        int ticket_id FK "References tickets.ticket_id"
        int user_id FK "Author user ID (users.id)"
        text comment_text "Reply content"
        tinyint is_internal "1 = Staff-only note, 0 = Public reply"
        datetime created_at
    }

    parish_chat_rooms {
        int room_id PK
        enum room_type "channel | direct"
        varchar name "Channel title"
        varchar description "Channel topic"
        varchar icon "FontAwesome icon class"
        int chapel_id FK "Optional branch scope (chapels.chapel_id)"
        int created_by FK "Creator user ID (users.id)"
        datetime created_at
    }

    parish_chat_participants {
        int room_id PK,FK "Composite PK -> parish_chat_rooms.room_id"
        int user_id PK,FK "Composite PK -> users.id"
        int last_read_message_id FK "Watermark -> parish_chat_messages.message_id"
        datetime last_read_at "Read receipt timestamp"
        tinyint is_muted "1 = Notifications muted"
        datetime joined_at
    }

    parish_chat_messages {
        int message_id PK
        int room_id FK "References parish_chat_rooms.room_id"
        int sender_id FK "References users.id"
        text message_text "Chat message body"
        varchar attachment_path "Uploaded file/image path"
        datetime created_at
    }
```

---

### Module 7: Bulletin & Parish Profile
* **Dashboard Sections**: `section=bulletin`, `section=parish_info`, `section=settings&tab=parish_info`, Public Portal (`index.php`)
* **Controllers & Models**: `DashboardController` (Bulletin actions), `AdminController`, `Bulletin`, `ParishInfo`
* **Optimization Design**: Powers the public landing page (`index.php`) and dashboard announcement board. `bulletin_posts` supports both parish-wide (`chapel_id IS NULL`) and chapel-specific notices with custom ordering (`sort_order`). Historical milestones (`parish_history` + `parish_history_intro`) and clergy/lay governance directories (`parish_leadership` + `parish_leadership_intro`) are decoupled for fast, cache-friendly reads on the public portal.

```mermaid
erDiagram
    chapels |o--o{ bulletin_posts : "scopes announcement"
    users |o--o{ bulletin_posts : "publishes notice"
    users |o--o{ parish_contact_info : "updates profile"
    users |o--o{ parish_history : "authors milestone"
    users |o--o{ parish_history_intro : "edits history intro"
    users |o--o{ parish_leadership : "manages directory"
    users |o--o{ parish_leadership_intro : "edits leadership intro"

    bulletin_posts {
        int post_id PK
        varchar title "Headline announcement"
        varchar subtitle "Supporting details"
        varchar dot_color "Hex badge color (#c9a84c)"
        int chapel_id FK "NULL = Parish-wide, >0 = Specific chapel"
        int sort_order "Manual display priority"
        tinyint is_active "1 = Published, 0 = Hidden"
        int created_by FK "Author user ID (users.id)"
        timestamp created_at
        timestamp updated_at
    }

    parish_contact_info {
        int info_id PK "Singleton row (info_id = 1)"
        varchar parish_name "Cathedral official title"
        varchar patron_saint "Cathedral titular patron"
        varchar address "Physical street address"
        varchar phone "Office telephone"
        varchar email "Official parish email"
        varchar office_hours "Operating schedule text"
        text map_embed_url "Google Maps iframe URL"
        varchar cathedral_image "Hero photo path"
        varchar coordinator_name "Cathedral office head"
        varchar coordinator_phone "Office mobile"
        varchar smtp_host "Default system SMTP host"
        int smtp_port "Default SMTP port"
        varchar smtp_user "Default SMTP user"
        varchar smtp_pass "Default SMTP password"
        varchar smtp_secure "tls | ssl"
        varchar from_email "Default sender email"
        varchar from_name "Default sender name"
        int updated_by FK "References users.id"
        timestamp updated_at
    }

    parish_history {
        int history_id PK
        varchar year "Milestone year (e.g. 1902)"
        varchar title "Historical event title"
        text description "Narrative text"
        int sort_order "Chronological display order"
        tinyint is_active "1 = Visible"
        int created_by FK "References users.id"
        timestamp created_at
        timestamp updated_at
    }

    parish_history_intro {
        int intro_id PK "Singleton intro row"
        text intro_text "Section lead paragraph"
        int updated_by FK "References users.id"
        timestamp updated_at
    }

    parish_leadership {
        int leader_id PK
        enum position_type "bishop | parish_priest | assistant | deacon | council | sectoral | executive | coordinator"
        varchar name "Clergy or officer full name"
        varchar title "Canonical or council title"
        varchar subtitle "Secondary role / assignment"
        varchar email "Contact email"
        varchar photo_path "Portrait path"
        varchar image_path "Alternate portrait path"
        int sort_order "Hierarchy display order"
        tinyint is_active "1 = Active officer"
        int created_by FK "References users.id"
        timestamp created_at
        timestamp updated_at
    }

    parish_leadership_intro {
        int intro_id PK "Singleton intro row"
        text intro_text "Leadership section lead text"
        int updated_by FK "References users.id"
        timestamp updated_at
    }
```

---

### Module 8: Backups & Audit Logs
* **Dashboard Sections**: `section=settings&tab=backups`, `section=settings&tab=reports`, `AuditLogger`, `BackupService`
* **Controllers, Models & Services**: `AdminController`, `AuditLogger`, `AuditLogSection`, `BackupService`, `tools/backup.php`, `tools/restore_web.php`
* **Optimization Design**: `audit_logs` uses `BIGINT UNSIGNED` (`log_id`) and denormalizes actor identity snapshots (`performed_by_name`, `performed_by_role`) alongside `performed_by_id` so the security ledger remains intact and human-readable even if a staff account is later deleted. `backup_logs` tracks automated and manual SQL dump snapshots, while `system_settings` provides a lightweight key-value configuration table.

```mermaid
erDiagram
    users |o--o{ audit_logs : "performs audited action"
    chapels |o--o{ audit_logs : "scopes audit trail"
    users |o--o{ backup_logs : "initiates SQL dump"
    users |o--o{ system_settings : "updates global config"

    audit_logs {
        bigint log_id PK "Unsigned 64-bit auto-increment"
        varchar action "created | updated | deleted | login | exported"
        varchar module "Auth | Events | Records | Finance | Bulletin | Admin"
        int performed_by_id FK "Actor user ID (users.id)"
        varchar performed_by_name "Immutable snapshot of actor name"
        varchar performed_by_role "Immutable snapshot of actor role"
        int chapel_id FK "Branch context (chapels.chapel_id)"
        varchar target_description "Human-readable summary of mutation"
        mediumtext old_values "Serialized/JSON state prior to change"
        mediumtext new_values "Serialized/JSON state after change"
        varchar ip_address "Client IPv4/IPv6 address"
        timestamp created_at
    }

    backup_logs {
        int id PK
        varchar source "manual | scheduled | pre_restore"
        varchar status "running | success | failed"
        varchar backup_file "Archive filename in backups/"
        bigint backup_size "File size in bytes"
        int initiated_by FK "Admin user ID (users.id)"
        varchar initiated_by_name "Snapshot of admin name"
        text notes "Execution remarks or error trace"
        datetime created_at
        datetime updated_at
    }

    system_settings {
        varchar setting_key PK "Unique config key"
        text setting_value "Serialized/string config value"
        int updated_by FK "Admin user ID (users.id)"
        timestamp updated_at
    }
```

---

## 7. Database Optimization & Indexing Blueprint

To achieve peak query performance across all 8 Section Modules, the `cathedral` MySQL/MariaDB database actively enforces the following composite indexes and InnoDB Foreign Key constraints:

### 7.1 Active Composite & Unique Indexes by Module

| Section Module | Table | Active Index | Query Optimization Purpose |
| :--- | :--- | :--- | :--- |
| **M1: Users & Chapels** | `users` | `uk_users_username (user)` & `idx_users_role_status (role, status, chapel_id)` | Enforces unique usernames and accelerates Priest/Pastor roster lookups and branch-scoped staff listings. |
| **M1: Users & Chapels** | `system_notifications` | `idx_user_unread (user_id, is_read)` & `idx_created_at (created_at)` | Accelerates topbar unread notification badge queries. |
| **M2: Services** | `service_variants` | `idx_variants_service_active (service_id, status, is_current, price)` | Speeds up `ServiceModel::getFullCatalog()` join when loading active booking rates. |
| **M3: Calendar & Events** | `events` | `idx_events_cal_filter (is_archived, chapel_id, status, start_time)` | Optimizes the main dashboard Calendar (`Event::search()` and `Event::getAll()`) and status counter aggregations. |
| **M3: Calendar & Events** | `events` | `idx_events_pastor_sched (pastor_id, is_archived, status, start_time)` | Accelerates `EventConflictService` priest double-booking and travel buffer checks. |
| **M4: Church Records** | `sacramental_records` | `idx_records_composite (is_archived, scope, chapel_id, record_type, event_date)` | Powers instant filtering in `Cathedral Records` and `Chapel Records` pagination. |
| **M4: Church Records** | `sacramental_records` | `idx_records_book_page (record_type, book_number, page_number)` | Speeds up canonical registry book/page verification prior to certificate issuance. |
| **M5: Giving & Store** | `donations` | `idx_donations_ledger (is_archived, chapel_id, date_received, purpose)` | Accelerates financial summary aggregations (`FinanceController`) and date-range reports. |
| **M5: Giving & Store** | `merchandise_items` | `idx_merch_archived_name (is_archived, name)` | Speeds up active Parish Store inventory catalog queries. |
| **M6: Inquiries & Chat** | `public_inquiries` | `idx_inquiries_chapel_status (chapel_id, status, is_archived, created_at)` | Optimizes the topbar inquiries inbox and pending badge count. |
| **M7: Bulletin & Profile** | `bulletin_posts` | `idx_bulletin_active_order (is_active, chapel_id, sort_order, created_at)` | Speeds up Bulletin board and public landing page queries. |
| **M8: Backups & Audit** | `audit_logs` | `idx_module_action (module, action)` & `idx_chapel_created (chapel_id, created_at)` | Accelerates filtered audit log exports and activity timelines. |

### 7.2 Referential Integrity & Collation Standardization (Phase 2 Enforced)
All **56 Foreign Key constraints** across the 38 InnoDB tables are **actively enforced** at the database engine level (`post_phase2_optimization_20261104.sql`) with **0 floating tables**, and all 38 tables are standardized to `CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci`:
1. **Services Cascade**: `services.category_id -> service_categories.id (RESTRICT)`, and `ON DELETE CASCADE` from `services.id` to `service_variants`, `service_requirements`, `service_additional_charges`, `service_schedules`, and `certificate_types`.
2. **Event & Add-ons Integrity**: `events` enforces `ON DELETE SET NULL` to `chapels`, `users` (`pastor_id`, `requested_by`), `services`, and `service_variants`, while `service_request_addons` enforces `ON DELETE CASCADE` to `events`.
3. **Chat, Tickets & Notification Cascade**: `ticket_comments` cascades from `tickets`, `parish_chat_participants` and `parish_chat_messages` cascade from `parish_chat_rooms` and `users`, and `system_notifications` cascades from `users`.
4. **Immutable Audit Trail Preservation**: `sacramental_audit_log.record_id` is intentionally indexed without a cascade delete constraint so deleted sacramental records preserve their historical JSON snapshot trail (`action = 'deleted'`), while actor references (`performed_by`, `performed_by_id`, `initiated_by`, `voided_by`, `updated_by`) use `ON DELETE SET NULL`.

---

## 8. Physical Database Schema Reference (38 Tables • 56 Enforced FKs • 0 Floating Tables)

| # | Table Name | Section Module | Primary Key | Enforced Foreign Keys (56 Total) | Soft / Audit Refs | Key Indexed / Domain Columns |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| 1 | `chapels` | M1: Users & Chapels | `chapel_id` | — *(Parent Hub)* | — | `chapel_name`, `branch_type`, `status`, `seating_capacity` |
| 2 | `chapel_fiestas` | M1: Users & Chapels | `fiesta_id` | `chapel_id` (`CASCADE`) | — | `fiesta_name`, `fiesta_date`, `is_patronal` |
| 3 | `users` | M1: Users & Chapels | `id` | `chapel_id` (`SET NULL`) | — | `user` (UK), `role`, `status`, `duty_start_time`, `duty_end_time` |
| 4 | `system_notifications` | M1: Users & Chapels | `id` | `user_id` (`CASCADE`) | `reference_id` | `title`, `type`, `is_read`, `created_at` |
| 5 | `notifications` | M1: Users & Chapels | `notif_id` | `chapel_id` (`SET NULL`) | — | `recipient_name`, `message_type`, `status`, `sent_at` |
| 6 | `service_categories` | M2: Services | `id` | — *(Parent Hub)* | — | `code` (UK), `name`, `display_order`, `status` |
| 7 | `services` | M2: Services | `id` | `category_id` (`RESTRICT`) | — | `code` (UK), `name`, `effective_date`, `status` |
| 8 | `service_variants` | M2: Services | `id` | `service_id` (`CASCADE`) | — | `name`, `price`, `included_sponsors`, `is_current` |
| 9 | `service_requirements` | M2: Services | `id` | `service_id` (`CASCADE`) | — | `requirement`, `is_required`, `status` |
| 10 | `service_additional_charges` | M2: Services | `id` | `service_id` (`CASCADE`) | — | `name`, `price`, `is_optional`, `status` |
| 11 | `service_schedules` | M2: Services | `id` | `service_id` (`CASCADE`) | — | `schedule_type`, `day_of_week`, `start_time`, `end_time` |
| 12 | `certificate_types` | M2: Services | `id` | `service_id` (`CASCADE`) | — | `name` (UK), `price_per_copy`, `status` |
| 13 | `events` | M3: Calendar & Events | `event_id` | `chapel_id`, `pastor_id`, `requested_by`, `service_id`, `variant_id` (`SET NULL`) | — | `title`, `event_type`, `start_time`, `total_fee`, `status`, `approval_level` |
| 14 | `service_request_addons` | M3: Calendar & Events | `id` | `event_id` (`CASCADE`), `addon_id` (`RESTRICT`) | — | `addon_name`, `unit_price`, `quantity`, `subtotal` |
| 15 | `schedules` | M3: Calendar & Events | `schedule_id` | `chapel_id`, `pastor_id` (`SET NULL`) | — | `event_title`, `start_time`, `end_time`, `status` |
| 16 | `pastor_event_types` | M3: Calendar & Events | `id` | `pastor_id` (`CASCADE`) | — | `event_type` |
| 17 | `mass_schedules` | M3: Calendar & Events | `schedule_id` | `updated_by` (`SET NULL`) | — | `day_type`, `schedule_time`, `sort_order`, `is_active` |
| 18 | `sacramental_records` | M4: Church Records | `record_id` | `chapel_id`, `encoded_by`, `updated_by` (`SET NULL`) | — | `reference_id` (UK), `scope`, `record_type`, `person_name`, `event_date`, `book_number`, `page_number` |
| 19 | `certificate_issuances` | M4: Church Records | `issuance_id` | `record_id` (`CASCADE`), `issued_by` (`SET NULL`) | — | `certificate_no`, `issued_to`, `purpose`, `issued_at` |
| 20 | `sacramental_audit_log` | M4: Church Records | `audit_id` | `performed_by` (`SET NULL`) | `record_id` (Audit) | `action`, `old_snapshot` (JSON), `new_snapshot` (JSON) |
| 21 | `donations` | M5: Giving & Store | `donation_id` | `chapel_id`, `logged_by`, `voided_by` (`SET NULL`) | — | `reference_id` (UK), `amount`, `purpose`, `payment_method`, `date_received`, `is_archived` |
| 22 | `merchandise_items` | M5: Giving & Store | `id` | — *(Parent Hub)* | — | `name`, `price`, `stock_quantity`, `is_archived` |
| 23 | `merchandise_sales` | M5: Giving & Store | `id` | `item_id` (`RESTRICT`), `processed_by` (`SET NULL`) | — | `buyer_name`, `quantity`, `total_price`, `sale_date` |
| 24 | `public_inquiries` | M6: Inquiries & Chat | `inquiry_id` | `chapel_id`, `item_id` (`SET NULL`) | — | `full_name`, `request_type`, `status`, `is_archived` |
| 25 | `tickets` | M6: Inquiries & Chat | `ticket_id` | `chapel_id`, `assigned_to`, `requester_id` (`SET NULL`) | — | `ticket_no` (UK), `subject`, `priority`, `status` |
| 26 | `ticket_comments` | M6: Inquiries & Chat | `comment_id` | `ticket_id` (`CASCADE`), `user_id` (`SET NULL`) | — | `comment_text`, `is_internal`, `created_at` |
| 27 | `parish_chat_rooms` | M6: Inquiries & Chat | `room_id` | `chapel_id`, `created_by` (`SET NULL`) | — | `room_type`, `name`, `created_at` |
| 28 | `parish_chat_participants` | M6: Inquiries & Chat | `room_id`, `user_id` | `room_id`, `user_id` (`CASCADE`) | `last_read_message_id` | `last_read_at`, `is_muted`, `joined_at` |
| 29 | `parish_chat_messages` | M6: Inquiries & Chat | `message_id` | `room_id`, `sender_id` (`CASCADE`) | — | `message_text`, `attachment_path`, `created_at` |
| 30 | `bulletin_posts` | M7: Bulletin & Profile | `post_id` | `chapel_id`, `created_by` (`SET NULL`) | — | `title`, `dot_color`, `sort_order`, `is_active` |
| 31 | `parish_contact_info` | M7: Bulletin & Profile | `info_id` | `updated_by` (`SET NULL`) | — | `parish_name`, `patron_saint`, `address`, `phone`, `email` |
| 32 | `parish_history` | M7: Bulletin & Profile | `history_id` | `created_by` (`SET NULL`) | — | `year`, `title`, `sort_order`, `is_active` |
| 33 | `parish_history_intro` | M7: Bulletin & Profile | `intro_id` | `updated_by` (`SET NULL`) | — | `intro_text` |
| 34 | `parish_leadership` | M7: Bulletin & Profile | `leader_id` | `created_by` (`SET NULL`) | — | `position_type`, `name`, `title`, `sort_order`, `is_active` |
| 35 | `parish_leadership_intro` | M7: Bulletin & Profile | `intro_id` | `updated_by` (`SET NULL`) | — | `intro_text` |
| 36 | `audit_logs` | M8: Backups & Audit | `log_id` | `performed_by_id`, `chapel_id` (`SET NULL`) | — | `action`, `module`, `old_values`, `new_values`, `ip_address` |
| 37 | `backup_logs` | M8: Backups & Audit | `id` | `initiated_by` (`SET NULL`) | — | `source`, `status`, `backup_file`, `backup_size` |
| 38 | `system_settings` | M8: Backups & Audit | `setting_key` | `updated_by` (`SET NULL`) | — | `setting_value`, `updated_at` |

