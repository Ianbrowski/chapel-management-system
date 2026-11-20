<?php
$role = $viewData['role'] ?? '';
$isMember = $viewData['isMember'] ?? false;
$isGuest = $viewData['isGuest'] ?? ($role === 'Guest');
$user_id = $viewData['user_id'] ?? null;
$chapel_id = $viewData['chapel_id'] ?? ($_SESSION['chapel_id'] ?? null);
$chapelId = $chapel_id; // Backward-compatible alias used by older modal markup.
$assigned_chapel_id = $chapel_id;
$active_section = $viewData['active_section'] ?? ($active_section ?? 'overview');
$csrf = $viewData['csrf'] ?? '';
$flash = $viewData['flash'] ?? null;
$flash_type = $viewData['flash_type'] ?? 'success';
$full_name = $viewData['full_name'] ?? 'User';
$username = $viewData['username'] ?? 'User';
$profile_pic = $viewData['profile_pic'] ?? null;
$pending_count = $viewData['pending_count'] ?? 0;
$overdue_request_count = $viewData['overdue_request_count'] ?? 0;
$pending_inquiries = $viewData['pending_inquiries'] ?? 0;
$stats = $viewData['stats'] ?? [];
$upcoming = $viewData['upcoming'] ?? [];
$ovEvents = $viewData['ovEvents'] ?? [];
$pastor_schedule = $viewData['pastor_schedule'] ?? ($viewData['priest_schedule'] ?? []);
$priest_schedule = $viewData['priest_schedule'] ?? $pastor_schedule;
$pastor_locations = $viewData['pastor_locations'] ?? ($viewData['priest_locations'] ?? []);
$priest_locations = $viewData['priest_locations'] ?? $pastor_locations;
$all_priests = $viewData['all_priests'] ?? [];
$selected_priest_id = $viewData['selected_priest_id'] ?? null;
$target_priest = $viewData['target_priest'] ?? null;
$is_admin_view = $viewData['is_admin_view'] ?? false;
$bulletin_posts = $viewData['bulletin_posts'] ?? [];
$bulletin_filter = $viewData['bulletin_filter'] ?? ($_GET['bulletin_chapel'] ?? 'all');
$financial_summary = $viewData['financial_summary'] ?? [];
$merchandise_items = $viewData['merchandise_items'] ?? [];
$archived_merchandise_items = $viewData['archived_merchandise_items'] ?? [];
$merchandise_sales = $viewData['merchandise_sales'] ?? [];
$donation_stats = $viewData['donation_stats'] ?? [];
$donations = $viewData['donations'] ?? [];
$voided_donations = $viewData['voided_donations'] ?? [];
$new_donation_id = $viewData['new_donation_id'] ?? null;
$records = $viewData['records'] ?? [];
$total_records = $viewData['total_records'] ?? 0;
$total_pages = $viewData['total_pages'] ?? 0;
$current_page = $viewData['current_page'] ?? 1;
$rec_filters = $viewData['rec_filters'] ?? [];
$record_stats = $viewData['record_stats'] ?? [];
$users = $viewData['users'] ?? [];
$user_stats = $viewData['user_stats'] ?? [];
$pending_count = $viewData['pending_count'] ?? ($viewData['user_stats']['pending_count'] ?? 0);
$chapels = $viewData['chapels'] ?? [];
$assigned_chapel_name  = (count($chapels) === 1 && !in_array($role, ['Admin', 'Cathedral Admin'])) ? ($chapels[0]['chapel_name'] ?? 'My Chapel') : null;
$assigned_chapel_color = (count($chapels) === 1 && !in_array($role, ['Admin', 'Cathedral Admin'])) ? ($chapels[0]['theme_color'] ?? '#4f46e5') : '#4f46e5';
$audit_logs = $viewData['audit_logs'] ?? [];
$inquiries = $viewData['inquiries'] ?? [];
$inbox_tab = $viewData['inbox_tab'] ?? 'active';
$inq_total = $viewData['inq_total'] ?? 0;
$inq_pages = $viewData['inq_pages'] ?? 1;
$inq_page = $viewData['inq_page'] ?? 1;
$inq_active_count = $viewData['inq_active_count'] ?? 0;
$inq_archive_count = $viewData['inq_archive_count'] ?? 0;
$pending_requests = $viewData['pending_requests'] ?? [];
$my_requests = $viewData['my_requests'] ?? [];
$scope_view = $viewData['scope_view'] ?? ($_GET['scope_view'] ?? null);
$events = $viewData['events'] ?? [];
$all_calendar_events = $viewData['all_calendar_events'] ?? [];
$event_stats = $viewData['event_stats'] ?? [];
$ev_tab = $viewData['ev_tab'] ?? ($_GET['ev_tab'] ?? 'active');
$cathedral_events = $viewData['cathedral_events'] ?? [];
$chapel_events = $viewData['chapel_events'] ?? [];
$priests = $viewData['priests'] ?? ($viewData['pastors'] ?? []);
$pastors = $priests;
$priest_prefs = $viewData['priest_prefs'] ?? ($viewData['pastor_prefs'] ?? []);
$pastor_prefs = $priest_prefs;
$tickets = $viewData['tickets'] ?? [];
$status_counts = $viewData['status_counts'] ?? ['Open' => 0, 'In Progress' => 0, 'Resolved' => 0];
$stale_count = $viewData['stale_count'] ?? 0;
$audit_issue_count = $viewData['audit_issue_count'] ?? $stale_count;
$chart = $viewData['chart'] ?? [];
$intelligence = $viewData['intelligence'] ?? [];
$my_event_types = $viewData['my_event_types'] ?? [];
$rfrom = $viewData['rfrom'] ?? '';
$rto = $viewData['rto'] ?? '';
$can_manage_users = $viewData['can_manage_users'] ?? false;
$can_manage_chapels = $viewData['can_manage_chapels'] ?? false;
$can_audit_log = $viewData['can_audit_log'] ?? false;
$can_cathedral_rec = $viewData['can_cathedral_rec'] ?? false;
$can_chapel_rec = $viewData['can_chapel_rec'] ?? false;
$can_delete_records = $viewData['can_delete_records'] ?? false;
$can_view_sched = $viewData['can_view_sched'] ?? false;
$can_cathedral_sched = $viewData['can_cathedral_sched'] ?? false;
$can_chapel_sched = $viewData['can_chapel_sched'] ?? false;
$can_pastor_prefs = $viewData['can_pastor_prefs'] ?? ($viewData['can_priest_prefs'] ?? false);
$can_priest_prefs = $viewData['can_priest_prefs'] ?? $can_pastor_prefs;
$can_donations = $viewData['can_donations'] ?? false;
// can_manage_donations is the canonical key; can_donations_admin is the alias used in views and FinanceController
$can_manage_donations  = $viewData['can_manage_donations'] ?? false;
$can_donations_admin   = $can_manage_donations; // alias — do not change usages in views
// New granular permissions
$can_approve_events    = $viewData['can_approve_events'] ?? in_array($role, ['Admin', 'Cathedral Admin', 'Chapel Admin'], true);
$can_manage_inquiries  = $viewData['can_manage_inquiries'] ?? in_array($role, ['Admin', 'Cathedral Admin', 'Chapel Admin', 'Staff'], true);
// Backward-compat alias — views still using $can_review_requests will still work
$can_review_requests   = $can_approve_events;
$can_request_event = $viewData['can_request_event'] ?? false;
$can_insights = $viewData['can_insights'] ?? false;
$can_reports = $viewData['can_reports'] ?? false;
$can_bulletin = $viewData['can_bulletin'] ?? false;
$can_post_bulletin = $viewData['can_post_bulletin'] ?? false;
$can_update_events = $viewData['can_update_events'] ?? false;
$can_delete_events = $viewData['can_delete_events'] ?? false;
$can_finance_reports = $viewData['can_finance_reports'] ?? false;
$can_parish_info = $viewData['can_parish_info'] ?? false;
$can_manage_services = $viewData['can_manage_services'] ?? false;
$can_view_services = $viewData['can_view_services'] ?? true;
$can_pastor_dss = $viewData['can_pastor_dss'] ?? ($viewData['can_priest_dss'] ?? false);
$can_priest_dss = $viewData['can_priest_dss'] ?? $can_pastor_dss;
$can_audit_health = $viewData['can_audit_health'] ?? false;
$can_tickets = $viewData['can_tickets'] ?? false;
$audit_filters = $viewData['audit_filters'] ?? [];
$audit_filter_options = $viewData['audit_filter_options'] ?? [];
$audit_current_page = $viewData['audit_current_page'] ?? 1;
$audit_pages = $viewData['audit_pages'] ?? 0;
$audit_total = $viewData['audit_total'] ?? 0;
$rep_events = $viewData['rep_events'] ?? [];
$contact_info = $viewData['contact_info'] ?? [];
$mass_schedules = $viewData['mass_schedules'] ?? [];
$history_intro = $viewData['history_intro'] ?? '';
$history_items = $viewData['history_items'] ?? [];
$leaders = $viewData['leaders'] ?? [];
$leadership_intro = $viewData['leadership_intro'] ?? '';
$active_tab = $viewData['active_tab'] ?? 'contact';
$settings_tab = $viewData['settings_tab'] ?? ($_GET['tab'] ?? null);
$insight_portfolio = $viewData['insight_portfolio'] ?? [];
$stale_events = $viewData['stale_events'] ?? [];
$stale_confirmed = $viewData['stale_events'] ?? ($viewData['stale_confirmed'] ?? []);
$blocked_requests = $viewData['blocked_requests'] ?? [];
$schedule_conflicts = $viewData['schedule_conflicts'] ?? [];
$schedule_misplacements = $viewData['schedule_misplacements'] ?? ($viewData['misplaced_events'] ?? []);
$misplaced_events = $schedule_misplacements;
$my_chapel_coverage = $viewData['my_chapel_coverage'] ?? [];
$ev_filters = $viewData['ev_filters'] ?? [];
$total_events = $viewData['total_events'] ?? 0;
$upcoming_fiestas = $viewData['upcoming_fiestas'] ?? [];
$services_catalog = $viewData['services_catalog'] ?? [];
if (empty($services_catalog) && isset($conn) && $conn instanceof mysqli) {
    require_once BASE_PATH . '/app/models/ServiceModel.php';
    $srvModel = new ServiceModel($conn);
    $services_catalog = $srvModel->getFullCatalog(true);
}
$certificate_types = $viewData['certificate_types'] ?? [];

if (!function_exists('render_event_type_options')) {
    /**
     * Renders liturgical, sacramental, or parish event type options tailored to context:
     * - 'service_request': Active Parish Services Catalog only (for service requests/pricing)
     * - 'event_schedule':  Parish scheduling modals (catalog rites + General Mass, Fiesta, Other)
     * - 'filter':          Ledger/calendar filters (includes General Mass, Fiesta, Other + visible legacy labels)
     */
    function render_event_type_options($selected = '', $emptyLabel = 'Select Service / Rite', $catalog = null, string $mode = 'event_schedule'): string {
        global $services_catalog;
        $catList = $catalog ?? ($services_catalog ?? []);
        $html = '';
        if ($emptyLabel !== null && $emptyLabel !== '') {
            $html .= '<option value="">' . htmlspecialchars($emptyLabel) . '</option>';
        }

        if (!empty($catList)) {
            foreach ($catList as $cat) {
                if (strtolower($cat['code'] ?? '') === 'certificates' || strtolower($cat['name'] ?? '') === 'certificates') continue;
                $html .= '<optgroup label="' . htmlspecialchars($cat['name']) . '">';
                $hasWedding = false;
                $hasFuneral = false;
                foreach ($cat['services'] as $srv) {
                    $sName = $srv['name'];
                    if (strcasecmp($sName, 'Wedding') === 0 || strcasecmp($sName, 'Wedding (Holy Matrimony)') === 0) $hasWedding = true;
                    if (strcasecmp($sName, 'Funeral') === 0 || strcasecmp($sName, 'Burial Mass') === 0) $hasFuneral = true;
                    $sel = (strcasecmp($selected, $sName) === 0) ? ' selected' : '';
                    $html .= '<option value="' . htmlspecialchars($sName) . '" data-service-id="' . (int)($srv['id'] ?? 0) . '" data-service-code="' . htmlspecialchars($srv['code'] ?? '') . '"' . $sel . '>' . htmlspecialchars($sName) . '</option>';
                }
                if ($mode === 'event_schedule') {
                    if (stripos($cat['name'], 'sacrament') !== false && !$hasWedding) {
                        $selWed = (strcasecmp($selected, 'Wedding') === 0) ? ' selected' : '';
                        $html .= '<option value="Wedding"' . $selWed . ' style="display:none;">Wedding</option>';
                    }
                    if (stripos($cat['name'], 'liturgical') !== false && !$hasFuneral) {
                        $selFun = (strcasecmp($selected, 'Funeral') === 0) ? ' selected' : '';
                        $html .= '<option value="Funeral"' . $selFun . ' style="display:none;">Funeral</option>';
                    }
                }
                $html .= '</optgroup>';
            }
        } else {
            $html .= '<optgroup label="Sacraments">';
            $html .= '<option value="Baptism"' . (strcasecmp($selected, 'Baptism') === 0 ? ' selected' : '') . '>Baptism</option>';
            $html .= '<option value="Confirmation"' . (strcasecmp($selected, 'Confirmation') === 0 ? ' selected' : '') . '>Confirmation</option>';
            $html .= '<option value="Wedding (Holy Matrimony)"' . (strcasecmp($selected, 'Wedding (Holy Matrimony)') === 0 ? ' selected' : '') . '>Wedding (Holy Matrimony)</option>';
            $html .= '</optgroup>';

            $html .= '<optgroup label="Liturgical Services">';
            $html .= '<option value="Thanksgiving Mass"' . (strcasecmp($selected, 'Thanksgiving Mass') === 0 ? ' selected' : '') . '>Thanksgiving Mass</option>';
            $html .= '<option value="Burial Mass"' . (strcasecmp($selected, 'Burial Mass') === 0 ? ' selected' : '') . '>Burial Mass</option>';
            $html .= '<option value="Requiem Mass"' . (strcasecmp($selected, 'Requiem Mass') === 0 ? ' selected' : '') . '>Requiem Mass</option>';
            $html .= '</optgroup>';

            $html .= '<optgroup label="Blessings">';
            $html .= '<option value="Blessings"' . (strcasecmp($selected, 'Blessings') === 0 ? ' selected' : '') . '>Blessings</option>';
            $html .= '</optgroup>';
        }

        if ($mode !== 'service_request') {
            $html .= '<optgroup label="Parish &amp; Calendar Events">';
            $selMass   = (strcasecmp($selected, 'Mass') === 0) ? ' selected' : '';
            $selFiesta = (strcasecmp($selected, 'Fiesta') === 0) ? ' selected' : '';
            $selOther  = (strcasecmp($selected, 'Other') === 0) ? ' selected' : '';
            $html .= '<option value="Mass"' . $selMass . '>General Mass</option>';
            $html .= '<option value="Fiesta"' . $selFiesta . '>Fiesta</option>';
            $html .= '<option value="Other"' . $selOther . '>Other Event</option>';
            if ($mode === 'filter') {
                $selWed = (strcasecmp($selected, 'Wedding') === 0) ? ' selected' : '';
                $selFun = (strcasecmp($selected, 'Funeral') === 0) ? ' selected' : '';
                $html .= '<option value="Wedding"' . $selWed . '>Wedding (Legacy)</option>';
                $html .= '<option value="Funeral"' . $selFun . '>Funeral (Legacy)</option>';
            }
            $html .= '</optgroup>';
        }

        return $html;
    }
}
