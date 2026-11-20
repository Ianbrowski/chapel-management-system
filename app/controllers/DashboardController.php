<?php
require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/FinanceController.php';
require_once __DIR__ . '/AdminController.php';
require_once __DIR__ . '/InquiryController.php';
require_once __DIR__ . '/SacramentalController.php';
require_once __DIR__ . '/../services/DashboardExportService.php';
require_once __DIR__ . '/../services/DashboardSectionDataService.php';

require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Chapel.php';
require_once __DIR__ . '/../models/Donation.php';
require_once __DIR__ . '/../models/Event.php';
require_once __DIR__ . '/../models/SacramentalRecord.php';
require_once __DIR__ . '/../models/Inquiry.php';
require_once __DIR__ . '/../models/Bulletin.php';
require_once __DIR__ . '/../models/AuditLogger.php';
require_once __DIR__ . '/../models/AuditLogSection.php';
require_once __DIR__ . '/../models/InsightPortfolio.php';
require_once __DIR__ . '/../models/ParishInfo.php';
require_once __DIR__ . '/../models/Merchandise.php';
require_once __DIR__ . '/EventController.php';
require_once __DIR__ . '/TicketController.php';
require_once __DIR__ . '/../mail/DocumentReadyMailer.php';
require_once __DIR__ . '/../services/SmsService.php';
require_once __DIR__ . '/../models/Ticket.php';

/**
 * DashboardController — The 'Traffic Controller' for the dashboard.
 * Orchestrates multiple sub-controllers and compiles view data.
 */
class DashboardController extends BaseController
{

    // Sub-controllers for specialized tasks
    private $finance;
    private $admin;
    private $inquiry;
    private $sacramental;
    private $event;

    // Models for overview stats
    private $userM;
    private $chapelM;
    private $donationM;
    private $eventM;
    private $recordM;
    private $bulletinM;
    private $auditM;
    private $inquiryM;
    private $parishInfoM;
    private $insightPortfolioM;
    private $exportService;
    private $sectionDataService;
    private $isMember;
    private $isGuest;

    public function __construct($conn)
    {
        parent::__construct($conn);
        $this->isMember = ($this->role === 'Member');
        $this->isGuest  = ($this->role === 'Guest');

        $this->finance = new FinanceController($conn);
        $this->admin = new AdminController($conn);
        $this->inquiry = new InquiryController($conn);
        $this->sacramental = new SacramentalController($conn);
        $this->event = new EventController($conn);

        $this->userM = new User($conn);
        $this->chapelM = new Chapel($conn);
        $this->donationM = new Donation($conn);
        $this->eventM = new Event($conn);
        $this->recordM = new SacramentalRecord($conn);
        $this->bulletinM = new Bulletin($conn);
        $this->auditM = new AuditLogSection($conn);
        $this->inquiryM = new Inquiry($conn);
        $this->parishInfoM = new ParishInfo($conn);
        $this->insightPortfolioM = new InsightPortfolio();
        $this->exportService = new DashboardExportService($conn);
        $this->sectionDataService = new DashboardSectionDataService(
            $this->userM,
            $this->chapelM,
            $this->donationM,
            $this->eventM,
            $this->recordM,
            $this->auditM,
            $this->parishInfoM,
            $this->insightPortfolioM,
            $this->exportService
        );
    }

    /**
     * Entry point for all POST requests in the dashboard.
     * Routes the action to the correct sub-controller.
     */
    public function handleRequest()
    {
        // Intercept GET requests for non-UI actions like Exports or Quick Approvals
        $reqMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if ($reqMethod === 'GET' && isset($_GET['action'])) {
            if ($this->isGuest) {
                $this->flash('error', 'Guests cannot perform administrative actions.');
                $this->redirect('dashboard.php?section=events');
                return;
            }
            $action = $_GET['action'];
            if ($action === 'export_donations') {
                $this->finance->exportDonations();
            } elseif ($action === 'export_sales') {
                $this->finance->exportSales();
            } elseif ($action === 'export_report') {
                $this->exportReportCsv();
            }
        }

        if ($reqMethod !== 'POST')
            return;
        $section = $_POST['form_section'] ?? '';

        // Strict access control for Guests: Guests are strictly spectators and cannot create or submit requests
        if ($this->isGuest) {
            $this->flash('error', 'Guest accounts are spectator-only. Please sign in or register as a member to submit requests.');
            $this->redirect('dashboard.php?section=request_certificate&auth_prompt=1');
            return;
        }

        // Routing to specialized controllers
        if (in_array($section, ['donations', 'merchandise'])) {
            $this->finance->handleRequest();
        } elseif (in_array($section, ['users', 'chapels', 'parish_info', 'my_profile'])) {
            $this->admin->handleRequest();
        } elseif (str_starts_with($section, 'record') || str_ends_with($section, '_form')) {
            $this->sacramental->handleRequest();
        } elseif (in_array($section, ['public_inquiries'])) {
            $this->inquiry->handleRequest();
        } elseif (in_array($section, ['events', 'event_request', 'certificate_request', 'review_request', 'priest_prefs', 'pastor_prefs', 'schedule_audit'])) {
            $this->event->handleRequest();
        } elseif (in_array($section, ['services'])) {
            require_once __DIR__ . '/ServiceController.php';
            $srvCtrl = new ServiceController($this->conn);
            $srvCtrl->handleAdminActions();
        } elseif ($section === 'notify_pickup') {
            $this->enforceCsrf();
            $this->handlePickupNotification();
        }

        // Bulletin and Profile remain here for now or until further split
        $this->handleCoreSectionActions($section);
    }

    private function handleCoreSectionActions($section)
    {
        $action = $_POST['action'] ?? '';

        if (in_array($section, ['my_profile', 'bulletin'], true)) {
            $this->enforceCsrf();
        }

        switch ($section) {
            case 'bulletin':
                $this->handleBulletinActions($action);
                break;
        }
    }


    private function handleBulletinActions($action)
    {
        if (empty($this->permissions['can_post_bulletin'])) {
            $this->flash('error', 'Unauthorized: You do not have permission to manage bulletin announcements.');
            $this->redirect('dashboard.php?section=bulletin');
            return;
        }

        $id = (int) ($_POST['id'] ?? 0);
        $title = trim((string) ($_POST['title'] ?? ''));
        $subtitle = trim((string) ($_POST['subtitle'] ?? ''));
        $color = $_POST['dot_color'] ?? '#c9a84c';
        $rawChapelId = (!empty($_POST['chapel_id']) && (int)$_POST['chapel_id'] > 0) ? (int)$_POST['chapel_id'] : null;
        $chapelId = in_array($this->role, ['Admin', 'Cathedral Admin', 'Priest', 'Pastor'], true)
            ? $rawChapelId
            : $this->getAppliedChapelId($rawChapelId);

        if (in_array($action, ['update_post', 'toggle_post', 'reorder_post', 'delete_post'], true)) {
            $existing = $this->bulletinM->getById($id);
            if (!$existing) {
                $this->flash('error', 'Announcement not found.');
                $this->redirect('dashboard.php?section=bulletin');
                return;
            }
            if (!in_array($this->role, ['Admin', 'Cathedral Admin', 'Priest', 'Pastor'], true) && !$this->checkChapelAccess($existing['chapel_id'] ?? null)) {
                $this->flash('error', 'Unauthorized: You can only modify announcements belonging to your assigned chapel.');
                $this->redirect('dashboard.php?section=bulletin');
                return;
            }
        }

        switch ($action) {
            case 'create_post':
                if ($title === '') {
                    $this->flash('error', 'Announcement title is required.');
                    break;
                }
                if ($this->bulletinM->create($title, $subtitle, $color, $this->userId, $chapelId)) {
                    AuditLogger::logFromSession($this->conn, 'created', 'Bulletin', "Posted announcement: {$title}");
                    $this->flash('success', 'Announcement posted.');
                } else {
                    $this->flash('error', 'Failed to post announcement.');
                }
                break;
            case 'update_post':
                if ($title === '') {
                    $this->flash('error', 'Announcement title is required.');
                    break;
                }
                if ($this->bulletinM->update($id, $title, $subtitle, $color, $chapelId)) {
                    AuditLogger::logFromSession($this->conn, 'updated', 'Bulletin', "Updated post ID: {$id}");
                    $this->flash('success', 'Announcement updated.');
                } else {
                    $this->flash('error', 'Failed to update announcement.');
                }
                break;
            case 'toggle_post':
                if ($this->bulletinM->toggle($id)) {
                    AuditLogger::logFromSession($this->conn, 'updated', 'Bulletin', "Toggled status for post ID: {$id}");
                    $this->flash('success', 'Post status updated.');
                } else {
                    $this->flash('error', 'Failed to update post status.');
                }
                break;
            case 'reorder_post':
                $dir = $_POST['direction'] ?? '';
                if ($this->bulletinM->reorder($id, $dir)) {
                    AuditLogger::logFromSession($this->conn, 'reordered', 'Bulletin', "Moved post ID: {$id} {$dir}");
                } else {
                    $this->flash('error', 'Failed to reorder announcement.');
                }
                break;
            case 'delete_post':
                if ($this->bulletinM->delete($id)) {
                    AuditLogger::logFromSession($this->conn, 'deleted', 'Bulletin', "Deleted post ID: {$id}");
                    $this->flash('success', 'Announcement removed.');
                } else {
                    $this->flash('error', 'Failed to remove announcement.');
                }
                break;
        }
        $this->redirect('dashboard.php?section=bulletin');
    }

    /**
     * Compiles all data needed to render the current section.
     */
    public function loadViewData($activeSection = null): array
    {
        if (!$activeSection) {
            $activeSection = 'events';
        }

        // Enforce section restrictions for Guests
        if ($this->isGuest) {
            $guestAllowed = ['events', 'overview', 'bulletin', 'request_certificate', 'request_event'];
            if (!in_array($activeSection, $guestAllowed, true)) {
                $this->flash('error', 'This feature is reserved for registered members and parish staff. Please register or sign in.');
                $this->redirect('dashboard.php?section=events&auth_prompt=1');
                $activeSection = 'events';
            }
        }

        // Enforce section restrictions for Members: allow calendar, bulletin, services, requests, merchandise, inquiries, profile
        if ($this->isMember) {
            $memberAllowed = ['events', 'overview', 'bulletin', 'request_certificate', 'request_event', 'merchandise', 'public_inquiries', 'my_profile'];
            if (!in_array($activeSection, $memberAllowed, true)) {
                $this->flash('info', 'Parishioners can view announcements and liturgical notices here.');
                $this->redirect('dashboard.php?section=events');
                $activeSection = 'events';
            }
        }

        $p = $this->permissions;
        $data = [
            'p' => $p,
            'permissions' => $p,
            'role' => $this->role,
            'isMember' => ($this->role === 'Member'),
            'isGuest' => $this->isGuest,
            'user_id' => $this->userId,
            'chapel_id' => $this->chapelId,
            'active_section' => $activeSection,
            'csrf' => $this->generateCsrf(),
            'flash' => $_SESSION['flash'] ?? null,
            'flash_type' => $_SESSION['flash_type'] ?? 'success',

            // Dashboard & Sidebars
            'full_name' => 'User',
            'username' => $_SESSION['username'] ?? 'User',
            'profile_pic' => null,
            'pending_count' => 0,
            'overdue_request_count' => 0,
            'pending_inquiries' => 0,

            // Overview & Common
            'stats' => ['total_events' => 0, 'pending' => 0, 'confirmed' => 0, 'total_records' => 0, 'chapels_count' => 0, 'users_count' => 0],
            'upcoming' => [],
            'ovEvents' => [],
            'pastor_schedule' => [],
            'pastor_locations' => [],

            // Bulletin
            'bulletin_posts' => [],

            // Finance
            'financial_summary' => ['grand_total' => 0, 'total_donations' => 0, 'total_merch' => 0],
            'merchandise_items' => [],
            'merchandise_sales' => [],
            'donation_stats' => ['total_amount' => 0, 'total_count' => 0, 'this_month' => 0],
            'donations' => [],
            'new_donation_id' => $_SESSION['new_donation_id'] ?? null,

            // Records
            'records' => [],
            'total_records' => 0,
            'total_pages' => 0,
            'current_page' => 1,
            'rec_filters' => ['scope' => '', 'search' => '', 'record_type' => ''],
            'record_stats' => [],

            // Admin, Users, Chapels
            'users' => [],
            'chapels' => [],
            'audit_logs' => [],

            // Inquiries & Requests
            'inquiries' => [],
            'inbox_tab' => 'active',
            'inq_total' => 0,
            'inq_pages' => 1,
            'inq_page' => 1,
            'inq_active_count' => 0,
            'inq_archive_count' => 0,
            'pending_requests' => [],
            'my_requests' => [],
            'events' => [],
            'cathedral_events' => [],
            'chapel_events' => [],
            'priests' => [],
            'priest_prefs' => [],
            'tickets' => [],
            'stale_count' => 0,
            'schedule_conflicts' => [],
            'schedule_misplacements' => [],
            'chart' => [
                'event_types' => [],
                'event_status' => [],
                'events_monthly' => [],
                'record_types' => []
            ],
            'intelligence' => [
                'venue_demand' => [],
                'pastor_load' => [],
                'growth_ratio' => [],
                'retention_stats' => [],
                'usage_stats' => []
            ],
            'my_event_types' => [],
            'rfrom' => '',
            'rto' => ''
        ];

        // Clear flashes & temp session data
        unset($_SESSION['flash'], $_SESSION['flash_type'], $_SESSION['new_donation_id']);

        // Merge permissions directly for easier access in views
        $data = array_merge($data, $p);

        // User identity display
        if ($this->isGuest) {
            $data['full_name'] = 'Guest Visitor';
            $data['profile_pic'] = null;
        } else {
            $me = $this->userM->getById($this->userId);
            if (is_array($me)) {
                $data['full_name'] = !empty($me['full_name']) ? $me['full_name'] : ($_SESSION['username'] ?? 'User');
                $data['profile_pic'] = $me['profile_pic'] ?? null;
            } else {
                $data['full_name'] = $_SESSION['username'] ?? 'User';
                $data['profile_pic'] = null;
            }
        }
        $allChapels = $this->chapelM->getAll();
        if (!in_array($this->role, ['Admin', 'Cathedral Admin', 'Priest', 'Pastor', 'Member', 'Guest']) && $this->chapelId) {
            $data['chapels'] = array_values(array_filter($allChapels, fn($c) => $c['chapel_id'] == $this->chapelId));
        } elseif ($this->role === 'Staff' && empty($this->chapelId)) {
            $data['chapels'] = [];
        } else {
            $data['chapels'] = $allChapels;
        }
        $data['priests'] = $this->userM->getByRole('Priest');
        $data['pastors'] = $data['priests'];

        // Liturgical Service & Event Catalog: load globally for uniform modals and forms
        require_once __DIR__ . '/../models/ServiceModel.php';
        $srvModel = new ServiceModel($this->conn);
        $data['services_catalog'] = $srvModel->getFullCatalog(true);

        // Badges: always load these for the sidebar (zeroed out for guests and members)
        if ($this->isGuest || $this->isMember) {
            $data['pending_count'] = 0;
            $data['overdue_request_count'] = 0;
            $data['audit_issue_count'] = 0;
            $data['stale_count'] = 0;
            $data['pending_inquiries'] = 0;
        } else {
            if (in_array($this->role, ['Admin', 'Cathedral Admin', 'Priest', 'Pastor'], true)) {
                $chapelFilter = null;
            } elseif (!empty($this->chapelId)) {
                $chapelFilter = (int)$this->chapelId;
            } else {
                $chapelFilter = 'cathedral';
            }
            $data['pending_count'] = $this->eventM->getCountByStatus('Pending', $chapelFilter);
            $data['overdue_request_count'] = count($this->eventM->getOverduePendingRequests($chapelFilter));
            $data['audit_issue_count'] = $data['overdue_request_count'];
            if ($p['can_audit_health']) {
                $auditScope = in_array($this->role, ['Admin', 'Cathedral Admin', 'Priest', 'Pastor']) ? null : 'chapel';
                $data['stale_count'] = count($this->eventM->getStaleConfirmedEvents($auditScope, $chapelFilter));
                $data['audit_issue_count'] += $data['stale_count'];
            }
            $inqFilter = is_numeric($chapelFilter) ? (int)$chapelFilter : null;
            $data['pending_inquiries'] = $this->inquiryM->getActiveCount($inqFilter);
        }

        // Load Section-Specific Data (Performance optimization: only load what's needed)
        switch ($activeSection) {
            case 'events':
            case 'overview':
                $ev_page = (int) ($_GET['ev_page'] ?? 1);
                $ev_tab  = $_GET['ev_tab'] ?? 'active';
                $ev_filters = [
                    'search'               => $_GET['ev_search'] ?? ($_GET['search'] ?? ''),
                    'event_type'           => $_GET['ev_type'] ?? ($_GET['event_type'] ?? ''),
                    'sort'                 => $_GET['ev_sort'] ?? ($_GET['sort'] ?? 'date_desc'),
                    'is_archived'          => ($ev_tab === 'archived' ? 1 : 0),
                    'exclude_certificates' => true,
                ];
                if (!empty($_GET['ev_pastor']) || !empty($_GET['pastor_id']) || !empty($_GET['ev_pastor_id'])) {
                    $ev_filters['pastor_id'] = (int)($_GET['ev_pastor'] ?? ($_GET['ev_pastor_id'] ?? $_GET['pastor_id']));
                }
                if (!empty($_GET['ev_timeframe']) || !empty($_GET['timeframe'])) {
                    $ev_filters['timeframe'] = trim($_GET['ev_timeframe'] ?? $_GET['timeframe']);
                }
                $venueParam = $_GET['ev_venue'] ?? ($_GET['chapel_id'] ?? '');
                
                // Enforce venue scoping based on role and assignment
                if (in_array($this->role, ['Admin', 'Cathedral Admin', 'Priest', 'Pastor', 'Member', 'Guest'], true)) {
                    // Global view across all parishes by default; filterable by requested venue
                    $chapelFilter = null;
                    if (!empty($venueParam) && $venueParam !== 'all') {
                        if ($venueParam === 'cathedral' || $venueParam === '0') {
                            $chapelFilter = 'cathedral';
                            $ev_filters['venue_scope'] = 'cathedral';
                            $ev_filters['venue'] = 'cathedral';
                        } else {
                            $chapelFilter = (int)$venueParam;
                            $ev_filters['chapel_id'] = $chapelFilter;
                            $ev_filters['venue'] = (string)$chapelFilter;
                        }
                    }
                } elseif (!empty($this->chapelId)) {
                    // Chapel Admin or Chapel Staff: Strictly scoped to their assigned chapel branch
                    $chapelFilter = (int)$this->chapelId;
                    $ev_filters['chapel_id'] = $chapelFilter;
                    $ev_filters['venue'] = (string)$chapelFilter;
                } else {
                    // Cathedral Staff: Strictly scoped to Main Cathedral
                    $chapelFilter = 'cathedral';
                    $ev_filters['venue_scope'] = 'cathedral';
                    $ev_filters['venue'] = 'cathedral';
                }
                $limit = 15;
                $res = $this->eventM->search($ev_filters, $ev_page, $limit);
                $data['events']       = $this->sanitizeCalendarEventsForRole($res['records']);
                $data['total_pages']  = $res['pages'];
                $data['current_page'] = $res['page'];
                $data['total_events'] = $res['total'];
                $data['limit']        = $limit;
                $data['ev_filters']   = $ev_filters;
                $data['ev_tab']       = $ev_tab;

                // Load all active calendar events for the interactive calendar & timeslot cards
                // Strictly sanitized on server side to preserve guest privacy & member data boundaries
                $rawCalendarEvents = $this->eventM->getAll(350, $chapelFilter);
                $data['all_calendar_events'] = $this->sanitizeCalendarEventsForRole($rawCalendarEvents);

                // Precompute accurate status counters
                $data['event_stats'] = [
                    'total'     => $this->eventM->getCount($chapelFilter),
                    'archived'  => $this->eventM->getArchivedCount($chapelFilter),
                    'confirmed' => $this->eventM->getCountByStatus('Confirmed', $chapelFilter),
                    'pending'   => $this->eventM->getCountByStatus('Pending', $chapelFilter),
                    'completed' => $this->eventM->getCountByStatus('Completed', $chapelFilter),
                    'cancelled' => $this->eventM->getCountByStatus('Cancelled', $chapelFilter),
                ];
                break;

            case 'donations':
                // ── Delegated to FinanceController::getFinanceViewData() ──
                if (!$p['can_donations'])
                    return $this->loadViewData('events');
                $data = array_merge($data, $this->finance->getFinanceViewData());
                break;

            case 'merchandise':
                // ── Delegated to FinanceController::getFinanceViewData() ──
                if (!$p['can_donations'] && !$this->isMember)
                    return $this->loadViewData('events');
                $data = array_merge($data, $this->finance->getFinanceViewData());
                break;

            case 'records_cathedral':
                // ── Delegated to SacramentalController::getRecordsViewData() ──
                if (!$p['can_cathedral_rec'])
                    return $this->loadViewData('events');
                if (($_GET['export'] ?? '') === 'records_csv') {
                    $this->exportRecordsCsv('cathedral');
                }
                $chapelFilter = !in_array($this->role, ['Admin', 'Cathedral Admin']) ? $this->chapelId : null;
                $data = array_merge($data, $this->sacramental->getRecordsViewData('cathedral', $chapelFilter));
                break;

            case 'records_chapel':
                // ── Delegated to SacramentalController::getRecordsViewData() ──
                if (!$p['can_chapel_rec'])
                    return $this->loadViewData('events');
                if (($_GET['export'] ?? '') === 'records_csv') {
                    $this->exportRecordsCsv('chapel');
                }
                if (in_array($this->role, ['Admin', 'Cathedral Admin'])) {
                    $chapelFilter = !empty($_GET['chapel_id']) ? (int)$_GET['chapel_id'] : (!empty($_GET['chapel']) ? (int)$_GET['chapel'] : null);
                } else {
                    $chapelFilter = $this->chapelId ? (int)$this->chapelId : null;
                }
                $data = array_merge($data, $this->sacramental->getRecordsViewData('chapel', $chapelFilter));
                $data['selected_chapel_id'] = $chapelFilter;
                break;

            case 'bulletin':
                $filterChapel = null;
                if (isset($_GET['bulletin_chapel']) && $_GET['bulletin_chapel'] !== 'all' && $_GET['bulletin_chapel'] !== '') {
                    $filterChapel = ($_GET['bulletin_chapel'] === 'parish_wide' || $_GET['bulletin_chapel'] === '0') ? 0 : (int)$_GET['bulletin_chapel'];
                } elseif (!in_array($this->role, ['Admin', 'Cathedral Admin']) && $this->chapelId) {
                    $filterChapel = (int)$this->chapelId;
                }
                $data['bulletin_filter'] = $_GET['bulletin_chapel'] ?? 'all';
                $data['bulletin_posts'] = $this->bulletinM->getAll($filterChapel);
                break;

            case 'settings':
                $requestedTab = $_GET['tab'] ?? null;
                $activeTab = $requestedTab;
                if (!$activeTab) {
                    if ($p['can_manage_users']) {
                        $activeTab = 'users';
                    } elseif ($p['can_manage_chapels']) {
                        $activeTab = 'chapels';
                    } elseif ($p['can_manage_services']) {
                        $activeTab = 'services';
                    } elseif ($p['can_parish_info']) {
                        $activeTab = 'parish_info';
                    } elseif ($this->role === 'Admin') {
                        $activeTab = 'backups';
                    } elseif ($p['can_reports'] || $p['can_finance_reports']) {
                        $activeTab = 'reports';
                    } else {
                        return $this->loadViewData('events');
                    }
                }
                $data['settings_tab'] = $activeTab;

                if ($activeTab === 'users') {
                    if (!$p['can_manage_users']) return $this->loadViewData('events');
                    $data = array_merge($data, $this->admin->getAdminViewData('users'));
                } elseif ($activeTab === 'chapels') {
                    if (!$p['can_manage_chapels']) return $this->loadViewData('events');
                    $data = array_merge($data, $this->admin->getAdminViewData('chapels'));
                } elseif ($activeTab === 'services') {
                    if (!$p['can_manage_services']) return $this->loadViewData('events');
                    require_once __DIR__ . '/../models/ServiceModel.php';
                    $srvModel = new ServiceModel($this->conn);
                    $data['services_catalog'] = $srvModel->getFullCatalog(false);
                    $data['certificate_types'] = $srvModel->getCertificateTypes();
                } elseif ($activeTab === 'parish_info') {
                    if (!$p['can_parish_info']) return $this->loadViewData('events');
                    $data = $this->sectionDataService->loadParishInfoSectionData($data);
                } elseif ($activeTab === 'backups') {
                    if ($this->role !== 'Admin') return $this->loadViewData('events');
                } elseif ($activeTab === 'reports') {
                    if (!$p['can_reports'] && !$p['can_finance_reports']) return $this->loadViewData('events');
                    $chapelFilter = !in_array($this->role, ['Admin', 'Cathedral Admin']) ? $this->chapelId : null;
                    try {
                        $data = $this->sectionDataService->loadReportsSectionData($data, $chapelFilter);
                    } catch (InvalidArgumentException | RuntimeException $e) {
                        $this->flash('error', $e->getMessage());
                    }
                } else {
                    return $this->loadViewData('events');
                }
                break;

            case 'users':
                if (!$p['can_manage_users'])
                    return $this->loadViewData('events');
                $data['settings_tab'] = 'users';
                $data = array_merge($data, $this->admin->getAdminViewData('users'));
                break;

            case 'chapels':
                if (!$p['can_manage_chapels'])
                    return $this->loadViewData('events');
                $data['settings_tab'] = 'chapels';
                $data = array_merge($data, $this->admin->getAdminViewData('chapels'));
                break;

            case 'event_requests':
                if (!$p['can_approve_events'])
                    return $this->loadViewData('events');
                $chapelFilter = !in_array($this->role, ['Admin', 'Cathedral Admin']) ? $this->chapelId : null;
                $page = max(1, (int)($_GET['page'] ?? 1));
                $limit = 50;

                $reqFilters = [
                    'status'     => $_GET['status'] ?? 'all',
                    'search'     => trim($_GET['search'] ?? ''),
                    'event_type' => trim($_GET['event_type'] ?? ''),
                    'timeframe'  => trim($_GET['timeframe'] ?? ''),
                    'sort'       => trim($_GET['sort'] ?? 'date_asc')
                ];

                $resReq = $this->eventM->getPendingRequests($chapelFilter, $page, $limit, $reqFilters);
                $data['pending_requests'] = $resReq['records'];
                $data['event_paid_map'] = $this->eventM->getPaidAmountsMap(array_column($data['pending_requests'], 'event_id'));
                $data['limit'] = $limit;
                $data['total_pages'] = $resReq['pages'] ?? 1;
                $data['current_page'] = $resReq['page'] ?? 1;
                $data['total_count'] = $resReq['total'] ?? count($resReq['records']);
                $data['queue_counts'] = $this->eventM->getAdminRequestQueueCounts($chapelFilter);
                $data['pending_count'] = $data['queue_counts']['pending'];
                $data['overdue_requests'] = $this->eventM->getOverduePendingRequests($chapelFilter);
                $data['overdue_request_count'] = count($data['overdue_requests'] ?? []);
                break;

            case 'public_inquiries':
                if (!$p['can_manage_inquiries'] && !$this->isMember)
                    return $this->loadViewData('events');
                $data = array_merge($data, $this->inquiry->getInquiryViewData('public_inquiries'));
                break;

            case 'services':
                if (!$p['can_manage_services'])
                    return $this->loadViewData('events');
                $data['settings_tab'] = 'services';
                require_once __DIR__ . '/../models/ServiceModel.php';
                $srvModel = new ServiceModel($this->conn);
                $data['services_catalog'] = $srvModel->getFullCatalog(false);
                $data['certificate_types'] = $srvModel->getCertificateTypes();
                break;

            case 'request_event':
                $data = $this->sectionDataService->loadRequestEventSectionData($data, $this->userId);
                require_once __DIR__ . '/../models/ServiceModel.php';
                $srvModel = new ServiceModel($this->conn);
                $data['services_catalog'] = $srvModel->getFullCatalog(true);
                break;

            case 'request_certificate':
                $data = $this->sectionDataService->loadRequestCertificateSectionData($data, $this->userId);
                require_once __DIR__ . '/../models/ServiceModel.php';
                $srvModel = new ServiceModel($this->conn);
                $data['services_catalog'] = $srvModel->getFullCatalog(true);
                $data['certificate_types'] = $srvModel->getCertificateTypes();
                break;

            case 'priest_prefs':
            case 'pastor_prefs':
                if (!$p['can_pastor_prefs'] && !($p['can_priest_prefs'] ?? false))
                    return $this->loadViewData('events');
                $data = $this->sectionDataService->loadPriestPrefsSectionData($data, $this->userId);
                break;

            case 'priest_schedule':
            case 'pastor_schedule':
                $data = $this->sectionDataService->loadPriestScheduleSectionData($data, $this->userId);
                break;

            case 'reports':
                if (!$p['can_reports'] && !$p['can_finance_reports'])
                    return $this->loadViewData('events');
                $data['settings_tab'] = 'reports';
                $chapelFilter = !in_array($this->role, ['Admin', 'Cathedral Admin']) ? $this->chapelId : null;
                try {
                    $data = $this->sectionDataService->loadReportsSectionData($data, $chapelFilter);
                } catch (InvalidArgumentException | RuntimeException $e) {
                    $this->flash('error', $e->getMessage());
                    $this->redirect('dashboard.php?section=reports');
                }
                break;

            case 'parish_info':
                if (!$p['can_parish_info'])
                    return $this->loadViewData('events');
                $data['settings_tab'] = 'parish_info';
                $data = $this->sectionDataService->loadParishInfoSectionData($data);
                break;

            case 'backups':
                if ($this->role !== 'Admin')
                    return $this->loadViewData('events');
                $data['settings_tab'] = 'backups';
                break;

            case 'insights':
                return $this->loadViewData('reports');

            case 'schedule_audit':
                if (!$p['can_audit_health'])
                    return $this->loadViewData('events');
                $data = $this->loadViewData('events');
                $data['active_section'] = 'schedule_audit';
                $auditScope = in_array($this->role, ['Admin', 'Cathedral Admin', 'Priest']) ? null : 'chapel';
                $roleFilter = !in_array($this->role, ['Admin', 'Cathedral Admin']) ? $this->chapelId : null;
                $data['stale_events'] = $this->eventM->getStaleConfirmedEvents($auditScope, $roleFilter);
                $data['blocked_requests'] = $this->eventM->getOverduePendingRequests($roleFilter);
                $data['schedule_conflicts'] = $this->eventM->getScheduleConflicts($roleFilter);
                $data['schedule_misplacements'] = $this->eventM->getScheduleMisplacements($roleFilter);
                $data['audit_issue_count'] = count($data['stale_events']) + count($data['blocked_requests']) + count($data['schedule_conflicts']) + count($data['schedule_misplacements']);
                break;

            case 'cathedral':
            case 'schedule_cathedral':
                if (!$p['can_cathedral_sched'])
                    return $this->loadViewData('events');
                $data = $this->sectionDataService->loadScheduleSectionData($data, 'cathedral');
                require_once __DIR__ . '/../models/ServiceModel.php';
                $srvModel = new ServiceModel($this->conn);
                $data['services_catalog'] = $srvModel->getFullCatalog(true);
                break;

            case 'chapel':
            case 'schedule_chapel':
                if (!$p['can_chapel_sched'])
                    return $this->loadViewData('events');
                $chapelFilter = !in_array($this->role, ['Admin', 'Cathedral Admin']) ? $this->chapelId : null;
                $data = $this->sectionDataService->loadScheduleSectionData($data, 'chapel', $chapelFilter);
                require_once __DIR__ . '/../models/ServiceModel.php';
                $srvModel = new ServiceModel($this->conn);
                $data['services_catalog'] = $srvModel->getFullCatalog(true);
                break;
        }

        return $data;
    }




    private function exportReportCsv()
    {
        try {
            $chapelFilter = in_array($this->role, ['Admin', 'Cathedral Admin']) ? null : $this->chapelId;
            $this->exportService->streamReportCsv($_GET['from'] ?? '', $_GET['to'] ?? '', $chapelFilter);
        } catch (InvalidArgumentException | RuntimeException $e) {
            $this->flash('error', $e->getMessage());
            $this->redirect('dashboard.php?section=reports');
        }
    }

    private function exportAuditCsv(array $filters)
    {
        $this->exportService->streamAuditCsv($this->auditM->exportFiltered($filters));
    }

    private function exportRecordsCsv(string $scope)
    {
        $chapelFilter = in_array($this->role, ['Admin', 'Cathedral Admin']) ? null : $this->chapelId;
        $this->exportService->streamRecordsCsv($scope, $this->recordM->exportByScope($scope, $chapelFilter));
    }

    private function handlePickupNotification(): void
    {
        $returnSection = $_POST['return_section'] ?? 'overview';
        $redirectUrl = 'dashboard.php?section=' . urlencode($returnSection);

        if (!in_array($this->role, ['Admin', 'Cathedral Admin', 'Chapel Admin', 'Staff'], true)) {
            $this->flash('error', 'Unauthorized: Only parish staff and administrators can send pickup notifications.');
            $this->redirect($redirectUrl);
            return;
        }

        $email = trim((string) ($_POST['email'] ?? ''));
        $name = trim((string) ($_POST['name'] ?? ''));
        $documentType = trim((string) ($_POST['document_type'] ?? ''));
        $pickupDate = trim((string) ($_POST['pickup_date'] ?? ''));

        if ($email === '' || $name === '' || $documentType === '' || $pickupDate === '') {
            $this->flash('error', 'All pickup notification fields are required.');
            $this->redirect($redirectUrl);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->flash('error', 'Please enter a valid requester email address.');
            $this->redirect($redirectUrl);
        }

        $sent = DocumentReadyMailer::sendPickupNotification($email, $name, $documentType, $pickupDate);
        
        $phone = trim((string) ($_POST['phone'] ?? ''));
        $smsSent = false;
        if (!empty($phone)) {
            $smsSent = SmsService::sendPickupNotice($phone, $name, $documentType);
        }

        if ($sent || $smsSent) {
            AuditLogger::logFromSession(
                $this->conn,
                'updated',
                'Records',
                "Sent pickup notification for {$documentType} to {$email}" . ($phone ? " and {$phone}" : "")
            );
            $msg = "Pickup notification sent (Email: " . ($sent ? '✅' : '❌') . ", SMS: " . ($smsSent ? '✅' : '❌') . ")";
            $this->flash('success', $msg);
        } else {
            $this->flash('error', 'Notification could not be sent. Please verify settings.');
        }

        $this->redirect($redirectUrl);
    }


    private function normalizeNullableInt($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $intValue = (int) $value;
        return $intValue > 0 ? $intValue : null;
    }

    /**
     * Strictly enforces Guest and Member privacy on the server side:
     * - Guests: Confirmed events only, exact start times stripped/masked to broad liturgy windows, all personal/financial fields deleted.
     * - Members: Confirmed events + own requests only; other users' personal and financial data stripped.
     */
    public function sanitizeCalendarEventsForRole(array $events): array
    {
        return self::sanitizeEvents($events, $this->role, $this->userId);
    }

    public static function sanitizeEvents(array $events, string $role = 'Guest', int $userId = 0): array
    {
        $sanitized = [];
        $isGuest = ($role === 'Guest');
        $isMember = ($role === 'Member');

        foreach ($events as $ev) {
            $status = $ev['status'] ?? 'Confirmed';
            $reqBy = (int)($ev['requested_by'] ?? ($ev['user_id'] ?? 0));
            $isOwnEvent = ($reqBy === (int)$userId);

            if (empty($ev['pastor_assigned']) && !empty($ev['pastor_name'])) {
                $ev['pastor_assigned'] = $ev['pastor_name'];
            }

            // 1. Guest Restrictions: Public liturgical view only
            if ($isGuest) {
                // Guests must never see unconfirmed, pending, rejected, or cancelled requests
                if ($status !== 'Confirmed') {
                    continue;
                }

                // Mask exact start time: Broad liturgy window without revealing exact hours/minutes
                $startTime = $ev['start_time'] ?? '';
                $hr = !empty($startTime) ? (int)date('H', strtotime($startTime)) : 9;
                $dateOnly = !empty($startTime) ? substr($startTime, 0, 10) : date('Y-m-d');

                if ($hr < 12) {
                    $ev['start_time'] = $dateOnly . ' 09:00:00';
                    $ev['time_window'] = 'Morning Liturgy (9:00 AM - 12:00 PM)';
                } else {
                    $ev['start_time'] = $dateOnly . ' 13:00:00';
                    $ev['time_window'] = 'Afternoon Liturgy (1:00 PM - 5:00 PM)';
                }
                $ev['is_time_masked'] = true;
                $ev['title'] = 'Event';

                // Scrub all private personal, contact, financial, and admin fields
                unset(
                    $ev['requester_email'],
                    $ev['requester_phone'],
                    $ev['requester_name'],
                    $ev['service_fee'],
                    $ev['addons_fee'],
                    $ev['total_fee'],
                    $ev['admin_notes'],
                    $ev['rejection_reason'],
                    $ev['requested_by'],
                    $ev['encoded_by_name'],
                    $ev['remarks']
                );

                $sanitized[] = $ev;
                continue;
            }

            // 2. Member Restrictions: Public parish schedule + Member's own private requests
            if ($isMember) {
                // Members can only see Confirmed events OR their own private requests
                if ($status !== 'Confirmed' && !$isOwnEvent) {
                    continue;
                }

                // If not their own event, mask title to sacrament rite and strip other parishioners' personal/financial details
                if (!$isOwnEvent) {
                    $ev['requester_name'] = 'Parishioner';
                    $ev['title'] = !empty($ev['event_type']) ? $ev['event_type'] : 'Event';
                    unset(
                        $ev['requester_email'],
                        $ev['requester_phone'],
                        $ev['service_fee'],
                        $ev['addons_fee'],
                        $ev['total_fee'],
                        $ev['admin_notes'],
                        $ev['rejection_reason'],
                        $ev['encoded_by_name'],
                        $ev['remarks']
                    );
                }

                $sanitized[] = $ev;
                continue;
            }

            // 3. Authorized Clergy / Staff / Administrators
            $sanitized[] = $ev;
        }

        return $sanitized;
    }
}
