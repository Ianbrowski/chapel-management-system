<?php
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Chapel.php';
require_once __DIR__ . '/../models/Donation.php';
require_once __DIR__ . '/../models/Event.php';
require_once __DIR__ . '/../models/SacramentalRecord.php';
require_once __DIR__ . '/../models/AuditLogSection.php';
require_once __DIR__ . '/../models/ParishInfo.php';
require_once __DIR__ . '/../models/InsightPortfolio.php';
require_once __DIR__ . '/DashboardExportService.php';

class DashboardSectionDataService
{
    private User $userM;
    private Chapel $chapelM;
    private Donation $donationM;
    private Event $eventM;
    private SacramentalRecord $recordM;
    private AuditLogSection $auditM;
    private ParishInfo $parishInfoM;
    private InsightPortfolio $insightPortfolioM;
    private DashboardExportService $exportService;

    public function __construct(
        User $userM,
        Chapel $chapelM,
        Donation $donationM,
        Event $eventM,
        SacramentalRecord $recordM,
        AuditLogSection $auditM,
        ParishInfo $parishInfoM,
        InsightPortfolio $insightPortfolioM,
        DashboardExportService $exportService
    ) {
        $this->userM = $userM;
        $this->chapelM = $chapelM;
        $this->donationM = $donationM;
        $this->eventM = $eventM;
        $this->recordM = $recordM;
        $this->auditM = $auditM;
        $this->parishInfoM = $parishInfoM;
        $this->insightPortfolioM = $insightPortfolioM;
        $this->exportService = $exportService;
    }

    public function loadOverviewSectionData(array $data, ?int $chapelId = null): array
    {
        $data['stats'] = $this->buildDashboardStats((int) ($data['pending_count'] ?? 0), $chapelId);
        $data['upcoming'] = $this->eventM->getUpcoming(5, $chapelId);
        $data['upcoming_fiestas'] = $this->getUpcomingFiestas(5);

        $ovEvents = $this->eventM->getAll(200, $chapelId);
        foreach ($data['upcoming_fiestas'] as $fiesta) {
            $ovEvents[] = [
                'title' => 'FIESTA: ' . $fiesta['chapel_name'],
                'start_time' => $fiesta['next_occurrence'] . ' 00:00:00',
                'event_type' => 'Fiesta',
                'status' => 'Confirmed',
                'chapel_location' => $fiesta['fiesta_name'],
                'chapel_id' => $fiesta['chapel_id'] ?? null,
            ];
        }

        // Apply chapel_id filtering to overview events if restricted
        if ($chapelId !== null) {
            $ovEvents = array_filter($ovEvents, function($evt) use ($chapelId) {
                return isset($evt['chapel_id']) && (int)$evt['chapel_id'] === $chapelId;
            });
            $ovEvents = array_values($ovEvents);
        }

        $data['ovEvents'] = $ovEvents;
        
        // Add chart data for the new Overview visual summary
        $data['chart'] = [
            'event_types' => $this->eventM->getChartTypes($chapelId)
        ];

        return $data;
    }

    public function loadAuditLogSectionData(array $data, ?int $chapelId = null): array
    {
        $auditFilters = [
            'user' => $_GET['af_user'] ?? '',
            'module' => $_GET['af_module'] ?? '',
            'action' => $_GET['af_action'] ?? '',
            'date_from' => $_GET['af_from'] ?? '',
            'date_to' => $_GET['af_to'] ?? '',
        ];

        if ($chapelId !== null) {
            $auditFilters['chapel_id'] = $chapelId;
        }

        if (($_GET['export'] ?? '') === 'audit_csv') {
            $this->exportService->streamAuditCsv($this->auditM->exportFiltered($auditFilters));
        }

        $logPage = (int) ($_GET['audit_page'] ?? 1);
        $data['audit_logs'] = $this->auditM->getFiltered($auditFilters, $logPage);
        $data['audit_filters'] = $this->auditM->getFilters();
        $data['audit_filter_options'] = $this->auditM->getFilterOptions();
        $data['audit_current_page'] = $this->auditM->getCurrentPage();
        $data['audit_pages'] = $this->auditM->getTotalPages();
        $data['audit_total'] = $this->auditM->getTotalEntries();

        return $data;
    }

    public function loadRequestEventSectionData(array $data, int $userId): array
    {
        $data['chapels'] = $this->chapelM->getAll();
        
        $filters = [
            'requested_by'         => $userId,
            'sort'                 => $_GET['sort'] ?? $_GET['ev_sort'] ?? 'date_desc',
            'search'               => $_GET['search'] ?? $_GET['ev_search'] ?? '',
            'event_type'           => $_GET['event_type'] ?? $_GET['ev_type'] ?? '',
            'status'               => $_GET['status'] ?? $_GET['ev_status'] ?? '',
            'exclude_certificates' => true,
        ];
        if (!empty($_GET['timeframe'])) {
            $filters['timeframe'] = trim($_GET['timeframe']);
        }
        
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = 10; // Number of requests per page
        
        $res = $this->eventM->search($filters, $page, $limit);
        
        $data['my_requests'] = $res['records'];
        $data['limit'] = $limit;
        $data['ev_filters'] = $filters;
        $data['current_page'] = $res['page'];
        $data['total_pages'] = $res['pages'];
        $data['total_count'] = $res['total'];
        $data['req_counts'] = $this->eventM->getRequestCountsByUser($userId);
        
        return $data;
    }

    public function loadRequestCertificateSectionData(array $data, int $userId): array
    {
        $data['chapels'] = $this->chapelM->getAll();
        $role = $data['role'] ?? ($_SESSION['role'] ?? '');
        $userChapelId = $data['chapel_id'] ?? ($_SESSION['chapel_id'] ?? null);
        $isGlobalAdmin = in_array($role, ['Admin', 'Cathedral Admin'], true);
        $isChapelAdmin = ($role === 'Chapel Admin') || (!empty($data['can_approve_events']) && !$isGlobalAdmin);
        $isAdmin = $isGlobalAdmin || $isChapelAdmin;
        
        $scopeView = $_GET['scope_view'] ?? ($isAdmin ? 'all' : 'mine');
        $filters = [
            'sort'           => $_GET['sort'] ?? $_GET['ev_sort'] ?? 'date_desc',
            'search'         => $_GET['search'] ?? $_GET['ev_search'] ?? '',
            'status'         => $_GET['status'] ?? $_GET['ev_status'] ?? '',
            'is_certificate' => true,
        ];
        if (!empty($_GET['timeframe'])) {
            $filters['timeframe'] = trim($_GET['timeframe']);
        }

        $chapelScopeForCounts = null;
        if (!$isAdmin || $scopeView === 'mine') {
            $filters['requested_by'] = $userId;
        } elseif (!$isGlobalAdmin && !empty($userChapelId)) {
            $filters['chapel_id'] = (int)$userChapelId;
            $chapelScopeForCounts = (int)$userChapelId;
        }
        
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = 25;
        
        $res = $this->eventM->search($filters, $page, $limit);
        
        $data['my_requests'] = $res['records'];
        $data['limit'] = $limit;
        $data['ev_filters'] = $filters;
        $data['current_page'] = $res['page'];
        $data['total_pages'] = $res['pages'];
        $data['total_count'] = $res['total'];
        $data['cert_counts'] = $this->eventM->getCertificateRequestCounts((!$isAdmin || $scopeView === 'mine') ? $userId : null, $chapelScopeForCounts);
        $data['is_admin_view'] = $isAdmin;
        $data['scope_view'] = $scopeView;
        
        return $data;
    }

    public function loadPriestPrefsSectionData(array $data, int $userId): array
    {
        $role = $data['role'] ?? ($_SESSION['role'] ?? '');
        $isAdminView = in_array($role, ['Admin', 'Cathedral Admin']);
        $allPriests = $this->userM->getPriestsWithPrefs();

        if ($isAdminView) {
            $reqPriestId = (int)($_GET['priest_id'] ?? 0);
            if ($reqPriestId > 0) {
                $targetPriestId = $reqPriestId;
            } elseif (!empty($allPriests)) {
                $targetPriestId = (int)$allPriests[0]['id'];
            } else {
                $targetPriestId = $userId;
            }
        } else {
            $targetPriestId = $userId;
        }

        $targetUser = $this->userM->getById($targetPriestId);
        $data['my_event_types'] = $this->userM->getPriestPrefs($targetPriestId);
        $data['my_chapel_coverage'] = !empty($targetUser['chapel_coverage']) ? array_filter(array_map('trim', explode(',', $targetUser['chapel_coverage']))) : [];
        $data['chapels'] = $this->chapelM->getAll();
        $data['all_priests'] = $allPriests;
        $data['selected_priest_id'] = $targetPriestId;
        $data['target_priest'] = $targetUser;
        $data['is_admin_view'] = $isAdminView;
        return $data;
    }

    public function loadPastorPrefsSectionData(array $data, int $userId): array
    {
        return $this->loadPriestPrefsSectionData($data, $userId);
    }

    public function loadPriestScheduleSectionData(array $data, int $userId): array
    {
        $role = $data['role'] ?? ($_SESSION['role'] ?? '');
        $isAdminView = in_array($role, ['Admin', 'Cathedral Admin']);
        $allPriests = $this->userM->getPriestsWithPrefs();

        $selectedPriestId = null;
        $targetPriest = null;
        $filters = [
            'sort' => $_GET['sort'] ?? $_GET['ev_sort'] ?? 'date_desc',
            'search' => $_GET['search'] ?? $_GET['ev_search'] ?? ''
        ];
        if (!empty($_GET['status'])) {
            $filters['status'] = trim($_GET['status']);
        }
        if (!empty($_GET['event_type'])) {
            $filters['event_type'] = trim($_GET['event_type']);
        }
        if (!empty($_GET['timeframe'])) {
            $filters['timeframe'] = trim($_GET['timeframe']);
        }

        if ($isAdminView) {
            $reqPriestId = $_GET['priest_id'] ?? null;
            if ($reqPriestId === 'all') {
                $selectedPriestId = 'all';
                $filters['has_pastor'] = true;
                $data['priest_locations'] = [];
            } elseif (!empty($reqPriestId) && is_numeric($reqPriestId)) {
                $selectedPriestId = (int)$reqPriestId;
                $filters['pastor_id'] = $selectedPriestId;
                $targetPriest = $this->userM->getById($selectedPriestId);
            } else {
                if (!empty($allPriests)) {
                    $selectedPriestId = (int)$allPriests[0]['id'];
                    $filters['pastor_id'] = $selectedPriestId;
                    $targetPriest = $this->userM->getById($selectedPriestId);
                } else {
                    $selectedPriestId = 'all';
                    $filters['has_pastor'] = true;
                }
            }
        } else {
            $selectedPriestId = $userId;
            $filters['pastor_id'] = $userId;
            $targetPriest = $this->userM->getById($userId);
        }

        $limit = 200;
        $res = $this->eventM->search($filters, 1, $limit);
        $data['priest_schedule'] = $res['records'];
        $data['pastor_schedule'] = $res['records'];
        $data['limit'] = $limit;
        $data['ev_filters'] = $filters;

        if ($targetPriest) {
            $username = $targetPriest['user'] ?? '';
            $locations = $this->eventM->getPriestLocations($username);
            $covList = array_filter(array_map('trim', explode(',', $targetPriest['chapel_coverage'] ?? '')));
            $data['priest_locations'] = array_values(array_unique(array_merge($locations, $covList)));
            $data['priest_prefs'] = $this->userM->getPriestPrefs((int)$targetPriest['id']);
        } else {
            $allLocations = [];
            foreach ($allPriests as $p) {
                $covList = array_filter(array_map('trim', explode(',', $p['chapel_coverage'] ?? '')));
                $allLocations = array_merge($allLocations, $covList);
            }
            $data['priest_locations'] = array_values(array_unique($allLocations));
            $data['priest_prefs'] = [];
        }

        $data['pastor_locations'] = $data['priest_locations'];
        $data['all_priests'] = $allPriests;
        $data['selected_priest_id'] = $selectedPriestId;
        $data['target_priest'] = $targetPriest;
        $data['is_admin_view'] = $isAdminView;

        // Authorizations & preferences data for unified tab
        $targetPrefId = ($targetPriest && is_numeric($selectedPriestId)) ? (int)$selectedPriestId : (!empty($allPriests) ? (int)$allPriests[0]['id'] : $userId);
        $prefUser = ($targetPriest && is_numeric($selectedPriestId)) ? $targetPriest : $this->userM->getById($targetPrefId);
        $data['my_event_types'] = $this->userM->getPriestPrefs($targetPrefId);
        $data['my_chapel_coverage'] = !empty($prefUser['chapel_coverage']) ? array_filter(array_map('trim', explode(',', $prefUser['chapel_coverage']))) : [];
        $data['chapels'] = $this->chapelM->getAll();
        if (!$data['target_priest']) {
            $data['target_priest'] = $prefUser;
        }

        return $data;
    }

    public function loadPastorScheduleSectionData(array $data, int $userId): array
    {
        return $this->loadPriestScheduleSectionData($data, $userId);
    }

    public function loadReportsSectionData(array $data, ?int $chapelId = null): array
    {
        if (($_GET['export'] ?? '') === 'csv') {
            $this->exportService->streamReportCsv($_GET['from'] ?? '', $_GET['to'] ?? '', $chapelId);
        }

        $filters = [
            'from' => $_GET['from'] ?? date('Y-m-01'),
            'to' => $_GET['to'] ?? date('Y-m-t'),
        ];

        $data['rfrom'] = $filters['from'];
        $data['rto'] = $filters['to'];
        $data['stats'] = $this->buildDashboardStats((int) ($data['pending_count'] ?? 0), $chapelId);
        $data['rep_events'] = $this->eventM->exportCsv($filters['from'], $filters['to'], $chapelId);
        $data['chart'] = [
            'event_types' => $this->eventM->getChartTypes($chapelId),
            'event_status' => $this->eventM->getChartStatus($chapelId),
            'events_monthly' => $this->eventM->getChartMonthly($chapelId),
            'record_types' => $this->recordM->getChartTypes($chapelId),
            'venue_demand' => $this->eventM->getVenueDemand($chapelId),
        ];

        return $data;
    }

    public function loadParishInfoSectionData(array $data): array
    {
        $data['contact_info'] = $this->parishInfoM->getContactInfo();
        $data['mass_schedules'] = $this->parishInfoM->getMassSchedules();
        $data['history_intro'] = $this->parishInfoM->getHistoryIntro();
        $data['history_items'] = $this->parishInfoM->getHistoryItems();
        $data['leaders'] = $this->parishInfoM->getAllLeaders();
        $data['leadership_intro'] = $this->parishInfoM->getLeadershipIntro();
        $data['active_tab'] = $_GET['tab'] ?? 'contact';
        return $data;
    }

    public function loadInsightsSectionData(array $data, ?int $chapelId = null): array
    {
        $scopeName = 'System-Wide';
        if ($chapelId) {
            $ch = $this->chapelM->getById($chapelId);
            if ($ch) $scopeName = $ch['chapel_name'];
        }

        $data['intelligence'] = [
            'scope_name' => $scopeName,
            'venue_demand' => (array) $this->eventM->getVenueDemand($chapelId),
            'pastor_load' => (array) $this->eventM->getPriestWorkload($chapelId),
            'growth_ratio' => (array) $this->recordM->getBaptismFuneralRatio($chapelId),
            'retention_stats' => (array) $this->recordM->getRetentionStats($chapelId),
            'usage_stats' => (array) $this->eventM->getUsageStatsByVenue($chapelId),
            'response_time' => (float) $this->eventM->getResponseTimeStats($chapelId),
            'user_distribution' => (array) $this->userM->getRoleDistribution($chapelId),
            'member_stats' => (array) $this->userM->getMemberStats($chapelId),
            'account_growth' => (array) $this->userM->getGrowthFromLogs($chapelId),
            'member_detailed' => (array) $this->userM->getDetailedMemberInsights($chapelId),
        ];
        $data['donation_stats'] = $this->donationM->getStats($chapelId);
        $data['insight_portfolio'] = $this->insightPortfolioM->build($data['intelligence'], $data['donation_stats']);
        return $data;
    }

    public function loadScheduleSectionData(array $data, string $scope, ?int $chapelId = null): array
    {
        $evPage = (int) ($_GET['page'] ?? $_GET['ev_page'] ?? 1);
        $filters = [
            'venue_scope' => $scope,
            'sort' => $_GET['sort'] ?? $_GET['ev_sort'] ?? 'date_desc',
            'exclude_certificates' => true,
        ];
        if (!empty($_GET['search'])) {
            $filters['search'] = trim($_GET['search']);
        }
        if (!empty($_GET['event_type'])) {
            $filters['event_type'] = trim($_GET['event_type']);
        }
        if (!empty($_GET['status'])) {
            $filters['status'] = trim($_GET['status']);
        }
        if (!empty($_GET['pastor_id'])) {
            $filters['pastor_id'] = (int)$_GET['pastor_id'];
        }
        if (!empty($_GET['timeframe'])) {
            $filters['timeframe'] = trim($_GET['timeframe']);
        }
        if ($chapelId !== null) {
            $filters['chapel_id'] = $chapelId;
        }
        $limit = 15;
        $res = $this->eventM->search($filters, $evPage, $limit);
        $data['events'] = $res['records'];
        $data['total_pages'] = $res['pages'];
        $data['current_page'] = $res['page'];
        $data['total_events'] = $res['total'];
        $data['limit'] = $limit;
        $data['ev_filters'] = $filters;
        return $data;
    }

    private function buildDashboardStats(int $pendingCount, ?int $chapelId = null): array
    {
        return [
            'total_events' => $this->eventM->getCount($chapelId),
            'pending' => $this->eventM->getCountByStatus('Pending', $chapelId),
            'confirmed' => $this->eventM->getCountByStatus('Confirmed', $chapelId),
            'total_records' => $this->recordM->getCount($chapelId),
            'chapels_count' => $this->chapelM->countAll(),
            'users_count' => $this->userM->countAll($chapelId),
        ];
    }

    private function getUpcomingFiestas(int $limit = 3): array
    {
        $all = $this->chapelM->getAll();
        $upcoming = [];
        $tz = new DateTimeZone('Asia/Manila');
        $today = new DateTime('now', $tz);
        $today->setTime(0, 0, 0);
        $currYear = $today->format('Y');

        foreach ($all as $chapel) {
            if (empty($chapel['raw_fiestas'])) {
                continue;
            }

            foreach (explode('===', $chapel['raw_fiestas']) as $pair) {
                $parts = explode('|||', $pair);
                if (count($parts) !== 2) {
                    continue;
                }

                $name = $parts[0];
                $dateStr = $parts[1];
                $next = DateTime::createFromFormat('M d', $dateStr)
                    ?: DateTime::createFromFormat('F d', $dateStr)
                    ?: @date_create($dateStr . ' ' . $currYear);

                if (!$next) {
                    continue;
                }

                $next->setTime(0, 0, 0);
                if ($next < $today) {
                    $next->modify('+1 year');
                }

                $upcoming[] = [
                    'chapel_name' => $chapel['chapel_name'],
                    'fiesta_name' => $name,
                    'date_display' => $dateStr,
                    'next_occurrence' => $next->format('Y-m-d'),
                    'days_left' => (int) $today->diff($next)->format('%r%a'),
                ];
            }
        }

        usort($upcoming, fn($a, $b) => $a['days_left'] <=> $b['days_left']);
        return array_slice($upcoming, 0, $limit);
    }
}
