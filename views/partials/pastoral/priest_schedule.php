<?php
/**
 * PRIEST SCHEDULE & CLERGY AUTHORIZATIONS — Unified Pastoral Module
 * Clean 2-tab interface:
 *  Tab 1: Duty Schedule (Liturgical duty roster, celebrant filter, conflict checks, duty detail modal)
 *  Tab 2: Clergy Authorizations & Shifts (Sacraments, chapel coverage, shift hours, auto-save on switch)
 */

// Active tab selection
$active_tab = $_GET['tab'] ?? (in_array($active_section ?? '', ['priest_prefs', 'pastor_prefs'], true) ? 'authorizations' : 'schedule');
if (!in_array($active_tab, ['schedule', 'authorizations'], true)) {
    $active_tab = 'schedule';
}

// Stat counts for Duty Schedule
$ps_total       = count($pastor_schedule ?? []);
$ps_upcoming    = count(array_filter($pastor_schedule ?? [], fn($e) => strtotime($e['start_time'] ?? '') >= time()));
$ps_today       = count(array_filter($pastor_schedule ?? [], fn($e) => date('Y-m-d', strtotime($e['start_time'] ?? '')) === date('Y-m-d')));
$confirmedCount = count(array_filter($pastor_schedule ?? [], fn($e) => ($e['status'] ?? '') === 'Confirmed'));
$pendingCount   = count(array_filter($pastor_schedule ?? [], fn($e) => ($e['status'] ?? '') === 'Pending'));

$eventsOverlap = static function (array $a, array $b): bool {
    $statusA = $a['status'] ?? '';
    $statusB = $b['status'] ?? '';
    if (in_array($statusA, ['Cancelled', 'Declined', 'Archived'], true) || 
        in_array($statusB, ['Cancelled', 'Declined', 'Archived'], true)) {
        return false;
    }
    
    $startA = strtotime($a['start_time'] ?? '');
    $startB = strtotime($b['start_time'] ?? '');
    if (!$startA || !$startB) return false;

    $endA = !empty($a['end_time']) ? strtotime($a['end_time']) : ($startA + 3600);
    $endB = !empty($b['end_time']) ? strtotime($b['end_time']) : ($startB + 3600);
    if ($endA <= $startA) $endA = $startA + 3600;
    if ($endB <= $startB) $endB = $startB + 3600;

    return max($startA, $startB) < min($endA, $endB);
};

$hasPriestConflict = static function (array $event, int $index) use ($pastor_schedule, $eventsOverlap): bool {
    if (in_array($event['status'] ?? '', ['Cancelled', 'Declined', 'Archived'], true)) {
        return false;
    }
    if (empty($event['pastor_id'])) {
        return false;
    }
    foreach ($pastor_schedule ?? [] as $otherIndex => $otherEvent) {
        if ($index === $otherIndex) continue;
        if (empty($otherEvent['pastor_id']) || (int)$event['pastor_id'] !== (int)$otherEvent['pastor_id']) {
            continue;
        }
        if ($eventsOverlap($event, $otherEvent)) return true;
    }
    return false;
};

$ps_conflict = 0;
foreach ($pastor_schedule ?? [] as $idx => $event) {
    if ($hasPriestConflict($event, $idx)) $ps_conflict++;
}

if (!function_exists('renderPriestTypeBadge')) {
    function renderPriestTypeBadge(string $type): string {
        return '<span class="badge" style="background: #F6F6F6; color: #111111; border: 1px solid #E5E5E5; font-weight: 600; font-size: 11.5px; padding: 3px 9px; border-radius: 6px;">' . htmlspecialchars($type) . '</span>';
    }
}

if (!function_exists('renderPriestStatusBadge')) {
    function renderPriestStatusBadge(string $status): string {
        if ($status === 'Confirmed') {
            return '<span class="badge" style="background: #FFFFFF; color: #2E9E5B; border: 1px solid #2E9E5B; font-weight: 600; font-size: 11.5px; padding: 3px 9px; border-radius: 6px; display: inline-flex; align-items: center; gap: 5px;"><i class="fa-solid fa-circle-check" style="font-size: 10px;"></i> Confirmed</span>';
        } elseif ($status === 'Cancelled') {
            return '<span class="badge" style="background: #FFFFFF; color: #D64545; border: 1px solid #D64545; font-weight: 600; font-size: 11.5px; padding: 3px 9px; border-radius: 6px; display: inline-flex; align-items: center; gap: 5px;"><i class="fa-solid fa-ban" style="font-size: 10px;"></i> Cancelled</span>';
        } else {
            return '<span class="badge" style="background: #F6F6F6; color: #737373; border: 1px solid #E5E5E5; font-weight: 600; font-size: 11.5px; padding: 3px 9px; border-radius: 6px;">' . htmlspecialchars($status) . '</span>';
        }
    }
}

// Filter variables for Duty Schedule
$curStatus   = $ev_filters['status'] ?? '';
$curType     = $ev_filters['event_type'] ?? '';
$curSort     = $ev_filters['sort'] ?? 'date_desc';
$curTimeframe = $ev_filters['timeframe'] ?? '';
$searchQuery = $ev_filters['search'] ?? '';
$priestParam = ($is_admin_view && !empty($selected_priest_id)) ? '&priest_id=' . urlencode((string)$selected_priest_id) : '';
$baseQuery   = '&tab=schedule' . $priestParam . '&event_type=' . urlencode($curType) . '&sort=' . urlencode($curSort) . (!empty($curTimeframe) ? '&timeframe=' . urlencode($curTimeframe) : '') . (!empty($searchQuery) ? '&search=' . urlencode($searchQuery) : '');
$isAllStatus = empty($curStatus);

// Preference / Authorization variables
$all_types = [
    'Mass'         => ['icon' => 'fa-solid fa-church'],
    'Wedding'      => ['icon' => 'fa-solid fa-ring'],
    'Baptism'      => ['icon' => 'fa-solid fa-droplet'],
    'Funeral'      => ['icon' => 'fa-solid fa-cross'],
    'Confirmation' => ['icon' => 'fa-solid fa-dove'],
    'Other'        => ['icon' => 'fa-solid fa-hands-praying'],
];
$cov = $my_chapel_coverage ?? [];

if (empty($target_priest) && !empty($all_priests)) {
    if (!empty($selected_priest_id) && $selected_priest_id !== 'all') {
        foreach ($all_priests as $p) {
            if ((int)$p['id'] === (int)$selected_priest_id) {
                $target_priest = $p;
                break;
            }
        }
    }
    if (empty($target_priest)) {
        $target_priest = $all_priests[0];
    }
}
$targetName = !empty($target_priest['full_name']) ? $target_priest['full_name'] : (!empty($target_priest['user']) ? $target_priest['user'] : 'Priest');
$prefTargetId = ($target_priest && !empty($target_priest['id'])) ? (int)$target_priest['id'] : ((!empty($selected_priest_id) && $selected_priest_id !== 'all') ? (int)$selected_priest_id : (!empty($all_priests) ? (int)$all_priests[0]['id'] : (int)($user_id ?? 0)));

if (!function_exists('getPriestInitials')) {
    function getPriestInitials(?string $name = null): string {
        if (empty($name)) return 'PR';
        $clean = preg_replace('/^(Fr\.|Father|Rev\.|Pastor|Msgr\.)\s+/i', '', trim($name));
        $parts = preg_split('/\s+/', $clean);
        if (count($parts) >= 2) {
            return strtoupper(substr($parts[0], 0, 1) . substr($parts[count($parts) - 1], 0, 1));
        }
        return strtoupper(substr($clean, 0, 2));
    }
}

$riteMetadata = [
    'Mass' => [
        'name' => 'Holy Mass & Liturgies',
        'desc' => 'Eucharistic liturgies, Sunday masses & fiesta celebrations',
        'icon' => 'fa-solid fa-church'
    ],
    'Wedding' => [
        'name' => 'Holy Matrimony',
        'desc' => 'Marriage ceremonies, nuptial blessings & anniversary rites',
        'icon' => 'fa-solid fa-ring'
    ],
    'Baptism' => [
        'name' => 'Sacrament of Baptism',
        'desc' => 'Infant christenings, adult baptisms & reception rites',
        'icon' => 'fa-solid fa-droplet'
    ],
    'Funeral' => [
        'name' => 'Funeral & Requiem',
        'desc' => 'Burial rites, funeral blessings & memorial masses',
        'icon' => 'fa-solid fa-cross'
    ],
    'Confirmation' => [
        'name' => 'Confirmation Rite',
        'desc' => 'Sacrament of Holy Confirmation & episcopal anointings',
        'icon' => 'fa-solid fa-dove'
    ],
    'Other' => [
        'name' => 'Blessings & Pastoral Rites',
        'desc' => 'House blessings, vehicle dedications, sick calls & pastoral visits',
        'icon' => 'fa-solid fa-hands-praying'
    ],
];
?>

<div class="section <?php echo in_array($active_section, ['priest_schedule', 'pastor_schedule', 'priest_prefs', 'pastor_prefs']) ? 'active' : ''; ?>" 
     id="sec-priest_schedule">

    <style>
        #sec-priest_schedule {
            --ink: #111827;
            --muted: #64748b;
            --line: #e2e8f0;
            --surface: #ffffff;
            --accent: #b8963e;
            --accent-soft: rgba(184, 150, 62, 0.08);

            padding: 14px clamp(16px, 1.8vw, 24px) 32px;
            max-width: 1680px;
            margin: 0 auto;
            width: 100%;
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
            gap: 14px;
            font-family: var(--font-family, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif);
            color: var(--ink);
        }

        [data-theme="dark"] #sec-priest_schedule {
            --ink: #f1f5f9;
            --muted: #94a3b8;
            --line: rgba(255, 255, 255, 0.09);
            --surface: #182234;
            --accent: #c9a84c;
            --accent-soft: rgba(201, 168, 76, 0.12);
        }

        /* ── Header Styling ── */
        .ps-header-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--line);
            flex-wrap: wrap;
            gap: 14px;
        }
        .ps-title {
            font-family: var(--font-family, 'Inter', sans-serif);
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--ink);
            margin: 0;
            line-height: 1.25;
            letter-spacing: -0.01em;
        }
        .ps-subtitle {
            font-size: 12.5px;
            color: var(--muted);
            margin: 3px 0 0 0;
        }

        /* ── Tabs Navigation ── */
        .ps-tabs-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 4px;
            flex-wrap: wrap;
        }
        .ps-tabs-list {
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .ps-tab-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 18px;
            font-size: 13.5px;
            font-weight: 600;
            text-decoration: none;
            border-radius: 999px;
            color: var(--muted);
            background: transparent;
            border: 1px solid var(--line);
            transition: all 0.15s ease;
            line-height: 1.2;
            min-height: 38px;
            box-sizing: border-box;
            white-space: nowrap;
        }
        .ps-tab-link i {
            font-size: 14px;
            transition: color 0.15s ease;
        }
        .ps-tab-link:hover {
            color: var(--ink);
            border-color: #d1d5db;
            background: rgba(0,0,0,0.02);
        }
        .ps-tab-link.active {
            background: var(--ink);
            color: #ffffff;
            border-color: var(--ink);
            font-weight: 600;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }
        .ps-tab-link.active i {
            color: #ffffff !important;
        }
        [data-theme="dark"] .ps-tab-link {
            color: #cbd5e1;
            background: rgba(255, 255, 255, 0.04);
            border-color: rgba(255, 255, 255, 0.12);
        }
        [data-theme="dark"] .ps-tab-link:hover {
            color: #ffffff;
            background: rgba(255, 255, 255, 0.08);
            border-color: rgba(255, 255, 255, 0.22);
        }
        [data-theme="dark"] .ps-tab-link.active {
            background: #ffffff;
            color: #111111;
            border-color: #ffffff;
            font-weight: 700;
            box-shadow: 0 1px 4px rgba(0, 0, 0, 0.3);
        }
        [data-theme="dark"] .ps-tab-link.active i {
            color: #111111 !important;
        }
        .ps-tab-badge {
            font-size: 12px;
            padding: 2px 8px;
            border-radius: 999px;
            font-weight: 700;
            background: #f1f5f9;
            color: var(--muted);
            margin-left: 3px;
            display: inline-flex;
            align-items: center;
        }
        .ps-tab-link.active .ps-tab-badge {
            background: rgba(255,255,255,0.25);
            color: #ffffff;
        }
        [data-theme="dark"] .ps-tab-badge {
            background: rgba(255,255,255,0.12);
            color: #ffffff;
        }
        [data-theme="dark"] .ps-tab-link.active .ps-tab-badge {
            background: rgba(0,0,0,0.18);
            color: #111111;
        }

        /* ── Action Buttons ── */
        .btn-gold-action {
            background: var(--ink);
            color: #FFFFFF;
            border: 1px solid var(--ink);
            border-radius: 999px;
            padding: 8px 20px;
            font-size: 13.5px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
            transition: all 0.15s ease;
            text-decoration: none;
            min-height: 38px;
            box-sizing: border-box;
        }
        .btn-gold-action:hover {
            opacity: 0.92;
            transform: translateY(-1px);
        }
        .btn-gold-action i {
            color: var(--accent);
            font-size: 13px;
        }
        .btn-gold-action.btn-secondary-outline {
            background: transparent;
            color: var(--ink);
            border: 1px solid var(--line);
            border-radius: 999px;
            padding: 7px 16px;
            font-size: 13px;
            font-weight: 500;
            box-shadow: none;
            min-height: 38px;
        }
        .btn-gold-action.btn-secondary-outline:hover {
            border-color: #d1d5db;
            background: rgba(0,0,0,0.03);
            transform: none;
        }
        [data-theme="dark"] .btn-gold-action.btn-secondary-outline:hover {
            background: rgba(255,255,255,0.05);
        }

        /* ── Workspace 2-Column Grid (Tab 2) ── */
        .clergy-workspace-grid {
            display: grid;
            grid-template-columns: 280px minmax(0, 1fr);
            gap: 14px;
            align-items: stretch;
        }
        @media (max-width: 992px) {
            .clergy-workspace-grid {
                grid-template-columns: 1fr;
            }
        }

        /* ── Left Column: Clergy Directory ── */
        .clergy-dir-card {
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: 10px;
            padding: 12px 14px;
            display: flex;
            flex-direction: column;
            gap: 10px;
            box-sizing: border-box;
            height: 100%;
        }
        .clergy-dir-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-bottom: 8px;
            border-bottom: 1px solid var(--line);
        }
        .clergy-dir-title {
            font-size: 13px;
            font-weight: 700;
            color: var(--ink);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .clergy-dir-title i {
            color: var(--muted);
            font-size: 12px;
        }
        .clergy-dir-count {
            font-size: 11px;
            font-weight: 600;
            padding: 2px 8px;
            border-radius: 999px;
            background: #f1f5f9;
            color: var(--muted);
            border: none;
        }
        [data-theme="dark"] .clergy-dir-count {
            background: rgba(255,255,255,0.06);
        }
        .clergy-list-stack {
            display: flex;
            flex-direction: column;
            gap: 6px;
            overflow-y: auto;
            padding-right: 2px;
        }
        .clergy-nav-card {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 12px;
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: 8px;
            text-decoration: none;
            color: var(--ink);
            cursor: pointer;
            transition: all 0.15s ease;
            position: relative;
        }
        .clergy-nav-card:hover {
            border-color: #d1d5db;
            background: rgba(0,0,0,0.02);
        }
        [data-theme="dark"] .clergy-nav-card:hover {
            border-color: rgba(255,255,255,0.18);
            background: rgba(255,255,255,0.03);
        }
        .clergy-nav-card.active {
            background: var(--accent-soft);
            border-color: transparent;
            box-shadow: inset 3px 0 0 var(--accent);
        }
        .clergy-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: #f1f5f9;
            color: var(--ink);
            font-weight: 700;
            font-size: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: none;
            flex-shrink: 0;
            transition: all 0.15s ease;
        }
        [data-theme="dark"] .clergy-avatar {
            background: rgba(255,255,255,0.08);
            color: var(--ink);
        }
        .clergy-nav-card.active .clergy-avatar {
            background: var(--ink);
            color: #ffffff;
        }
        [data-theme="dark"] .clergy-nav-card.active .clergy-avatar {
            background: #ffffff;
            color: #111111;
        }
        .clergy-nav-info {
            display: flex;
            flex-direction: column;
            gap: 1px;
            min-width: 0;
            flex: 1;
        }
        .clergy-nav-name {
            font-size: 13px;
            font-weight: 600;
            color: var(--ink);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            line-height: 1.25;
        }
        .clergy-nav-role {
            font-size: 11px;
            color: var(--muted);
        }
        .clergy-nav-meta {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 2px;
            font-size: 11px;
            color: var(--muted);
            flex-wrap: wrap;
        }
        .clergy-meta-tag {
            display: inline-flex;
            align-items: center;
            gap: 3px;
            background: transparent;
            padding: 0;
            font-weight: 400;
            color: var(--muted);
            font-size: 11px;
        }
        .clergy-meta-tag i {
            color: var(--muted);
            font-size: 9.5px;
        }
        .clergy-active-indicator {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 13px;
            color: var(--accent);
            display: none;
        }
        .clergy-nav-card.active .clergy-active-indicator {
            display: block;
        }

        /* ── Right Column: Configuration Studio ── */
        .clergy-config-card {
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: 10px;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            box-sizing: border-box;
            height: 100%;
        }

        /* Hero Banner */
        .clergy-hero-banner {
            padding: 12px 18px;
            background: var(--surface);
            border-bottom: 1px solid var(--line);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
        }
        .clergy-hero-left {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .clergy-hero-avatar {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background: var(--ink);
            color: #FFFFFF;
            font-family: var(--font-family, sans-serif);
            font-size: 14px;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1.5px solid var(--accent);
            flex-shrink: 0;
        }
        [data-theme="dark"] .clergy-hero-avatar {
            background: #27272a;
            border-color: var(--accent);
        }
        .clergy-hero-name {
            font-family: var(--font-family, sans-serif);
            font-size: 15px;
            font-weight: 700;
            color: var(--ink);
            margin: 0;
            line-height: 1.25;
        }
        .clergy-hero-title {
            font-size: 12px;
            color: var(--muted);
            margin: 2px 0 0 0;
        }
        .clergy-hero-stats {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        .clergy-hero-pill {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: #f8fafc;
            border: 1px solid var(--line);
            border-radius: 999px;
            padding: 4px 10px;
            font-size: 11.5px;
            font-weight: 500;
            color: var(--ink);
        }
        [data-theme="dark"] .clergy-hero-pill {
            background: rgba(255,255,255,0.05);
            border-color: rgba(255,255,255,0.08);
        }
        .clergy-hero-pill i {
            color: var(--accent);
            font-size: 10px;
        }

        /* Form Sections */
        .clergy-section {
            padding: 14px 18px;
            border-bottom: 1px solid var(--line);
        }
        .clergy-section:last-child {
            border-bottom: none;
        }
        .clergy-sec-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
            gap: 8px;
            flex-wrap: wrap;
        }
        .clergy-sec-title {
            font-size: 13.5px;
            font-weight: 700;
            color: var(--ink);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 7px;
        }
        .clergy-sec-title i {
            color: var(--accent);
            font-size: 13px;
        }
        .clergy-sec-sub {
            font-size: 12px;
            color: var(--muted);
            margin: 2px 0 0 0;
            line-height: 1.35;
        }
        .clergy-sec-actions {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 11.5px;
        }
        .clergy-sec-actions a {
            color: var(--muted);
            text-decoration: none;
            font-weight: 500;
            font-size: 11.5px;
            transition: color 0.15s ease;
        }
        .clergy-sec-actions a:hover {
            color: var(--accent);
            text-decoration: none;
        }
        .clergy-sec-actions span {
            color: var(--line) !important;
        }

        /* Grid Cards — Clean, compact 3-column grid for Sacraments */
        .auth-cards-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 8px;
        }
        @media (max-width: 1100px) {
            .auth-cards-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }
        @media (max-width: 600px) {
            .auth-cards-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Grid Cards — Clean, compact 2-column grid for Chapels */
        .chapel-cards-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 8px;
        }
        @media (max-width: 768px) {
            .chapel-cards-grid {
                grid-template-columns: 1fr;
            }
        }

        .auth-check-card {
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: 7px;
            padding: 7px 12px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            cursor: pointer;
            transition: border-color 0.15s ease, background-color 0.15s ease;
            user-select: none;
            box-sizing: border-box;
            box-shadow: none;
            min-height: 40px;
        }
        .auth-check-card:hover {
            border-color: #d1d5db;
        }
        [data-theme="dark"] .auth-check-card:hover {
            border-color: rgba(255,255,255,0.22);
        }
        .auth-check-card.is-checked,
        .auth-check-card.selected,
        .auth-check-card:has(input:checked) {
            border-color: var(--accent);
            background: var(--accent-soft);
        }
        [data-theme="dark"] .auth-check-card.is-checked,
        [data-theme="dark"] .auth-check-card.selected,
        [data-theme="dark"] .auth-check-card:has(input:checked) {
            border-color: var(--accent);
            background: var(--accent-soft);
        }
        .auth-card-left {
            display: flex;
            align-items: center;
            gap: 10px;
            min-width: 0;
            flex: 1;
        }
        .auth-card-icon {
            width: 26px;
            height: 26px;
            border-radius: 6px;
            background: #f1f5f9;
            color: var(--ink);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11.5px;
            flex-shrink: 0;
            border: none;
            transition: background-color 0.15s ease, color 0.15s ease;
        }
        [data-theme="dark"] .auth-card-icon {
            background: rgba(255,255,255,0.06);
            color: var(--ink);
        }
        .auth-check-card.is-checked .auth-card-icon,
        .auth-check-card.selected .auth-card-icon,
        .auth-check-card:has(input:checked) .auth-card-icon {
            background: var(--ink);
            color: #ffffff;
        }
        [data-theme="dark"] .auth-check-card.is-checked .auth-card-icon,
        [data-theme="dark"] .auth-check-card.selected .auth-card-icon,
        [data-theme="dark"] .auth-check-card:has(input:checked) .auth-card-icon {
            background: #ffffff;
            color: #111111;
        }
        .auth-card-text {
            display: flex;
            flex-direction: column;
            gap: 2px;
            min-width: 0;
        }
        .auth-card-name {
            font-size: 13px;
            font-weight: 600;
            color: var(--ink);
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            line-height: 1.25;
        }
        .auth-card-desc {
            font-size: 11px;
            color: var(--muted);
            line-height: 1.35;
            display: -webkit-box;
            -webkit-line-clamp: 1;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .auth-checkbox-styled {
            appearance: none;
            -webkit-appearance: none;
            width: 18px;
            height: 18px;
            border: 1.5px solid #cbd5e1;
            border-radius: 50%;
            background: var(--surface);
            cursor: pointer;
            display: inline-grid;
            place-content: center;
            margin: 0;
            flex-shrink: 0;
            transition: all 0.15s ease;
            outline: none;
        }
        .auth-checkbox-styled:checked {
            background-color: var(--accent);
            border-color: var(--accent);
            background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 16 16' fill='none' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M12.2 4.8L6.5 10.5L3.8 7.8' stroke='%23ffffff' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
            background-size: 11px 11px;
            background-position: center;
            background-repeat: no-repeat;
        }

        /* Shift Hours Section */
        .shift-ctrl-row {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            background: transparent;
            border: 1px solid var(--line);
            border-radius: 7px;
            padding: 8px 14px;
        }
        .shift-input-group {
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .shift-label {
            font-size: 12.5px;
            font-weight: 600;
            color: var(--muted);
        }
        .shift-time-picker {
            padding: 0 8px;
            height: 32px;
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: 6px;
            color: var(--ink);
            font-size: 12.5px;
            font-weight: 600;
            outline: none;
            transition: border-color 0.15s ease;
            box-sizing: border-box;
        }
        .shift-time-picker:focus {
            border-color: var(--accent);
        }
        .shift-presets-stack {
            display: inline-flex;
            align-items: center;
            border: 1px solid var(--line);
            border-radius: 6px;
            background: #f8fafc;
            padding: 2px;
            gap: 0;
            margin-left: auto;
        }
        [data-theme="dark"] .shift-presets-stack {
            background: rgba(255,255,255,0.06);
        }
        .btn-shift-preset {
            font-size: 11.5px;
            font-weight: 500;
            padding: 4px 10px;
            border: none;
            border-radius: 5px;
            background: transparent;
            color: var(--muted);
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: all 0.15s ease;
            line-height: 1.2;
            text-decoration: none;
        }
        .btn-shift-preset i {
            font-size: 10px;
            color: inherit;
        }
        .btn-shift-preset:hover {
            color: var(--ink);
        }
        .btn-shift-preset.active {
            background: var(--surface);
            color: var(--ink);
            font-weight: 600;
            box-shadow: 0 1px 2px rgba(0,0,0,0.06);
        }
        [data-theme="dark"] .btn-shift-preset.active {
            background: rgba(255,255,255,0.15);
            color: #ffffff;
        }

        /* Sticky Panel Footer */
        .clergy-card-footer {
            position: sticky;
            bottom: 0;
            z-index: 10;
            background: var(--surface);
            border-top: 1px solid var(--line);
            padding: 10px 18px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
            box-shadow: 0 -2px 6px rgba(0,0,0,0.02);
        }
        .clergy-footer-hint {
            font-size: 12.5px;
            color: var(--muted);
            display: flex;
            align-items: center;
            gap: 7px;
        }
        .clergy-footer-hint i {
            font-size: 12px;
            color: var(--muted);
        }
        [data-theme="dark"] .ps-sort-arrows-group {
            border-color: rgba(255,255,255,0.15) !important;
            background: #18181b !important;
        }
        [data-theme="dark"] .ps-sort-arrow-btn.active {
            background: #ffffff !important;
            color: #111111 !important;
        }
        [data-theme="dark"] .ps-sort-arrow-btn:not(.active) {
            color: #94a3b8 !important;
        }
    </style>

    <!-- ─── 1. UNIFIED COMMAND HEADER: TITLE, BADGES, TABS, AND ACTIONS ─── -->
    <div class="page-header-bar" style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border); padding-bottom: 8px; margin-bottom: 6px; flex-wrap: wrap; gap: 10px;">
        <div style="display: flex; align-items: center; gap: 14px; flex-wrap: wrap;">
            <div class="page-header-icon" style="width: 38px; height: 38px; border-radius: 9px; background: rgba(201,168,76,0.14); color: var(--accent); display: flex; align-items: center; justify-content: center; font-size: 17px; flex-shrink: 0;">
                <i class="fa-solid fa-user-tie"></i>
            </div>
            <div>
                <h1 class="page-title" style="font-family: var(--font-family, 'Inter', sans-serif); font-size: var(--font-page-title, 20px); font-weight: 700; color: var(--text); margin: 0; line-height: 1.2;">
                    <?php echo $is_admin_view ? 'Priest Duty' : 'Pastoral Duty'; ?>
                </h1>
                <p class="page-subtitle" style="font-size: var(--font-page-subtitle, 14px); color: var(--text2); margin: 3px 0 0 0;">
                    Pastoral liturgies, sacramental duties, chapel coverage, and daily shift hours
                </p>
            </div>
        </div>

        <!-- Right Side: Stat Badges (always top-right) -->
        <div style="display: flex; align-items: center; gap: 6px;">
            <span class="badge" style="background: var(--bg3, #F4F4F5); border: 1px solid var(--border); color: var(--text2); font-size: 11.5px; font-weight: 600; padding: 4px 12px; border-radius: 999px;">
                <?php echo (int)$ps_total; ?> Duties
            </span>
            <?php if ($ps_conflict > 0): ?>
            <span class="badge badge-warning" style="font-size: 11.5px; padding: 4px 10px; font-weight: 600; border-radius: 999px;" title="<?php echo $ps_conflict; ?> duty schedule conflict(s) detected">
                <i class="fa-solid fa-triangle-exclamation" style="font-size: 10px; margin-right: 4px;"></i> <?php echo $ps_conflict; ?> Conflicts
            </span>
            <?php endif; ?>
        </div>
    </div>

    <!-- ─── 2. SUB-ROW: TABS LEFT + ACTIONS RIGHT ─── -->
    <div class="ps-tabs-bar" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; margin-bottom: 4px;">
        <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
            <div class="ps-tabs-group" style="display: inline-flex; align-items: center; gap: 4px; background: var(--bg3, #F4F4F5); padding: 3px; border-radius: 999px; border: 1px solid var(--border); flex-wrap: wrap;">
                <a href="dashboard.php?section=priest_schedule&amp;tab=schedule<?php echo ($is_admin_view && !empty($selected_priest_id) && $selected_priest_id !== 'all') ? '&amp;priest_id=' . (int)$selected_priest_id : ''; ?>"
                   class="ps-tab-link <?php echo ($active_tab === 'schedule') ? 'active' : ''; ?>" style="font-size: 12px; height: 32px; padding: 0 14px; border-radius: 999px;">
                    <i class="fa-solid fa-calendar-check"></i>
                    <span>Duty Schedule</span>
                    <span class="ps-tab-badge"><?php echo (int)$ps_total; ?></span>
                </a>
                <a href="dashboard.php?section=priest_schedule&amp;tab=authorizations<?php echo ($is_admin_view && !empty($selected_priest_id) && $selected_priest_id !== 'all') ? '&amp;priest_id=' . (int)$selected_priest_id : ''; ?>"
                   class="ps-tab-link <?php echo ($active_tab === 'authorizations') ? 'active' : ''; ?>" style="font-size: 12px; height: 32px; padding: 0 14px; border-radius: 999px;">
                    <i class="fa-solid fa-sliders"></i>
                    <span>Clergy Shifts</span>
                </a>
            </div>
        </div>

        <div style="display: flex; align-items: center; gap: 8px;">
            <a href="dashboard.php?section=events" class="ps-tab-link" style="text-decoration: none; font-size: 12px; height: 34px; padding: 0 14px; border-radius: 999px; display: inline-flex; align-items: center; gap: 6px; border: 1px solid var(--border); background: var(--surface); color: var(--text);" title="View Calendar">
                <i class="fa-solid fa-calendar-days" style="color: var(--accent);"></i> Calendar
            </a>

            <?php if ($active_tab === 'authorizations'): ?>
                <?php if ($is_admin_view): ?>
                    <button type="button" onclick="document.getElementById('form-priest-prefs').submit()" 
                            class="btn btn-primary btn-sm" style="font-size: 12px; height: 34px; font-weight: 600; display: inline-flex; align-items: center; gap: 6px; background: #111111; color: #FFFFFF; border: 1px solid #111111; border-radius: 999px; padding: 0 16px; cursor: pointer; box-shadow: 0 1px 3px rgba(0,0,0,0.12);">
                        <i class="fa-solid fa-floppy-disk"></i> Save Changes
                    </button>
                <?php else: ?>
                    <span class="badge" style="padding: 6px 12px; font-size: 11.5px; border-radius: 999px; background: var(--bg3); border: 1px solid var(--border); color: var(--text2);">
                        <i class="fa-solid fa-lock"></i> Read-Only View
                    </span>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════ -->
    <!-- TAB 1: DUTY SCHEDULE                                       -->
    <!-- ══════════════════════════════════════════════════════════ -->
    <?php if ($active_tab === 'schedule'): ?>

        <!-- Unified Table Toolbar (Matching Universal Pattern in Calendar & Records) -->
        <div style="background: var(--surface); border: 1px solid var(--line); border-radius: 8px; padding: 8px 12px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px; margin-bottom: 8px; box-shadow: 0 1px 2px rgba(0,0,0,0.02);">
            
            <!-- Left: Segmented Status Filter Tabs -->
            <div class="ps-status-tabs-group" style="display: inline-flex; align-items: center; gap: 4px; background: var(--bg3, #F4F4F5); padding: 3px; border-radius: 999px; border: 1px solid var(--border);">
                <a href="dashboard.php?section=priest_schedule<?php echo $baseQuery; ?>" 
                   class="ps-tab-link <?php echo $isAllStatus ? 'active' : ''; ?>" 
                   style="padding: 4px 14px; font-size: 12px; min-height: 28px; border-radius: 999px; text-decoration: none; border: 1px solid <?php echo $isAllStatus ? 'var(--ink)' : 'transparent'; ?>; background: <?php echo $isAllStatus ? 'var(--ink)' : 'transparent'; ?>; color: <?php echo $isAllStatus ? '#ffffff' : 'var(--muted)'; ?>;">
                    All (<?php echo $ps_total; ?>)
                </a>
                <a href="dashboard.php?section=priest_schedule&amp;status=Confirmed<?php echo $baseQuery; ?>" 
                   class="ps-tab-link <?php echo ($curStatus === 'Confirmed') ? 'active' : ''; ?>" 
                   style="padding: 4px 14px; font-size: 12px; min-height: 28px; border-radius: 999px; text-decoration: none; border: 1px solid <?php echo ($curStatus === 'Confirmed') ? 'var(--ink)' : 'transparent'; ?>; background: <?php echo ($curStatus === 'Confirmed') ? 'var(--ink)' : 'transparent'; ?>; color: <?php echo ($curStatus === 'Confirmed') ? '#ffffff' : 'var(--muted)'; ?>;">
                    Confirmed (<?php echo $confirmedCount; ?>)
                </a>
                <a href="dashboard.php?section=priest_schedule&amp;status=Pending<?php echo $baseQuery; ?>" 
                   class="ps-tab-link <?php echo ($curStatus === 'Pending') ? 'active' : ''; ?>" 
                   style="padding: 4px 14px; font-size: 12px; min-height: 28px; border-radius: 999px; text-decoration: none; border: 1px solid <?php echo ($curStatus === 'Pending') ? 'var(--ink)' : 'transparent'; ?>; background: <?php echo ($curStatus === 'Pending') ? 'var(--ink)' : 'transparent'; ?>; color: <?php echo ($curStatus === 'Pending') ? '#ffffff' : 'var(--muted)'; ?>;">
                    Pending (<?php echo $pendingCount; ?>)
                </a>
            </div>

            <!-- Right: Integrated Form Filters (Priest Select, Timeframe, Type, Universal Sort Toggle, Search) -->
            <form method="GET" style="display: inline-flex; align-items: center; gap: 8px; margin: 0; flex-wrap: wrap;">
                <input type="hidden" name="section" value="priest_schedule">
                <input type="hidden" name="tab" value="schedule">
                <?php if (!empty($curStatus)): ?>
                <input type="hidden" name="status" value="<?php echo htmlspecialchars($curStatus); ?>">
                <?php endif; ?>

                <!-- Priest Selector Dropdown (Universal dropdown matching Calendar's Pastor filter) -->
                <?php if ($is_admin_view && !empty($all_priests)): ?>
                <select name="priest_id" onchange="this.form.submit()" 
                        style="height: 32px !important; min-width: 140px; max-width: 180px; font-size: 12px; border-radius: 6px; padding: 0 8px; cursor: pointer; border: 1px solid var(--border); background: var(--bg2, var(--surface)); color: var(--text);"
                        title="Filter by priest">
                    <option value="all" <?php echo ($selected_priest_id === 'all' || empty($selected_priest_id)) ? 'selected' : ''; ?>>All Priests</option>
                    <?php foreach ($all_priests as $p): 
                        $pId = (string)$p['id'];
                        $pName = !empty($p['full_name']) ? $p['full_name'] : $p['user'];
                        if (!preg_match('/^(Fr\.|Father|Rev\.|Pastor|Msgr\.)/i', $pName)) {
                            $pName = 'Fr. ' . $pName;
                        }
                    ?>
                    <option value="<?php echo $pId; ?>" <?php echo ((string)$selected_priest_id === $pId) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($pName); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <?php endif; ?>

                <!-- Timeframe Filter Dropdown -->
                <select name="timeframe" onchange="this.form.submit()" 
                        style="height: 32px !important; min-width: 110px; max-width: 130px; font-size: 12px; border-radius: 6px; padding: 0 8px; cursor: pointer; border: 1px solid var(--border); background: var(--bg2, var(--surface)); color: var(--text);"
                        title="Filter by timeframe">
                    <option value="">All Dates</option>
                    <option value="this_week" <?php echo ($curTimeframe === 'this_week') ? 'selected' : ''; ?>>This Week</option>
                    <option value="next_week" <?php echo ($curTimeframe === 'next_week') ? 'selected' : ''; ?>>Next Week</option>
                    <option value="this_month" <?php echo ($curTimeframe === 'this_month') ? 'selected' : ''; ?>>This Month</option>
                    <option value="upcoming" <?php echo ($curTimeframe === 'upcoming') ? 'selected' : ''; ?>>Upcoming</option>
                    <option value="past" <?php echo ($curTimeframe === 'past') ? 'selected' : ''; ?>>Past</option>
                </select>

                <!-- Type Filter Dropdown -->
                <select name="event_type" onchange="this.form.submit()" 
                        style="height: 32px !important; min-width: 115px; max-width: 150px; font-size: 12px; border-radius: 6px; padding: 0 8px; cursor: pointer; border: 1px solid var(--border); background: var(--bg2, var(--surface)); color: var(--text);">
                    <?php echo render_event_type_options($curType, 'All Rites'); ?>
                </select>

                <!-- Universal Sort Toggle Button (Matching Calendar, Records, Schedules) -->
                <input type="hidden" name="sort" id="sort-priest-duty" value="<?php echo htmlspecialchars($curSort); ?>">
                <button type="button" class="btn-sort-toggle" onclick="toggleSortDirection(this, 'sort-priest-duty')"
                        title="<?php echo ($curSort === 'date_asc') ? 'Sorted: Oldest First (Click to sort Newest first)' : 'Sorted: Newest First (Click to sort Oldest first)'; ?>"
                        style="height: 32px; width: 34px; min-width: 34px; padding: 0; display: inline-flex; align-items: center; justify-content: center; border-radius: 6px; border: 1px solid var(--border); background: var(--bg2, var(--surface)); color: var(--text); cursor: pointer; transition: all 0.15s ease; flex-shrink: 0;" aria-label="Toggle sort order">
                    <i class="fa-solid <?php echo ($curSort === 'date_asc') ? 'fa-arrow-up-wide-short' : 'fa-arrow-down-wide-short'; ?>" style="font-size: 13px;"></i>
                </button>

                <!-- Search Input -->
                <div style="position: relative; width: 170px;">
                    <i class="fa-solid fa-magnifying-glass" style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: var(--muted); font-size: 11.5px; pointer-events: none; z-index: 2;"></i>
                    <input type="text" name="search" value="<?php echo htmlspecialchars($searchQuery); ?>" placeholder="Search duty..." 
                            style="width: 100%; height: 32px; padding-left: 32px !important; padding-right: <?php echo !empty($searchQuery) ? '24px' : '8px'; ?> !important; background: var(--bg2, var(--surface)); border: 1px solid var(--border); border-radius: 6px; color: var(--text); font-size: 12px; box-sizing: border-box;">
                    <?php if (!empty($searchQuery)): ?>
                    <a href="dashboard.php?section=priest_schedule&amp;tab=schedule<?php echo !empty($selected_priest_id) ? '&amp;priest_id=' . urlencode((string)$selected_priest_id) : ''; ?><?php echo !empty($curStatus) ? '&amp;status=' . urlencode($curStatus) : ''; ?><?php echo !empty($curType) ? '&amp;event_type=' . urlencode($curType) : ''; ?><?php echo !empty($curTimeframe) ? '&amp;timeframe=' . urlencode($curTimeframe) : ''; ?>&amp;sort=<?php echo urlencode($curSort); ?>" 
                       title="Clear search" 
                       style="position: absolute; right: 7px; top: 50%; transform: translateY(-50%); color: var(--muted); text-decoration: none; font-size: 13px; line-height: 1; padding: 2px;">×</a>
                    <?php endif; ?>
                </div>

                <?php if (!empty($searchQuery) || !empty($curType) || !empty($curTimeframe) || (!empty($selected_priest_id) && $selected_priest_id !== 'all') || (!empty($curStatus)) || $curSort === 'date_asc'): ?>
                    <a href="dashboard.php?section=priest_schedule&amp;tab=schedule" class="filter-clear-link" title="Clear Filters" style="display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; border-radius: 6px; background: var(--bg2, var(--surface)); border: 1px solid var(--border); color: var(--text2); text-decoration: none; font-size: 11px;">
                        <i class="fa-solid fa-xmark"></i>
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Liturgical Duty Table Card -->
        <div style="background: var(--surface); border: 1px solid var(--line); border-radius: 8px; overflow: hidden; display: flex; flex-direction: column; box-shadow: 0 1px 2px rgba(0,0,0,0.02);">
            <?php if (empty($pastor_schedule)): ?>
                <div style="padding: 40px 16px; text-align: center; color: var(--muted);">
                    <i class="fa-solid fa-clipboard-check" style="font-size: 32px; opacity: 0.3; margin-bottom: 10px; display: block;"></i>
                    <div style="font-size: 14px; font-weight: 700; color: var(--ink); margin-bottom: 3px;">No Pastoral Duties Found</div>
                    <div style="font-size: 11.5px; margin-bottom: 12px;">There are no scheduled liturgies matching the selected filter.</div>
                    <a href="dashboard.php?section=events" class="btn-gold-action" style="font-size: 11.5px; padding: 5px 12px;">
                        <i class="fa-solid fa-calendar-days"></i> Calendar
                    </a>
                </div>
            <?php else: ?>
            <div style="overflow-x: auto;">
                <table class="data-table" style="width: 100%; border-collapse: collapse; text-align: left; min-width: 680px;">
                    <thead>
                        <tr style="background: #fbfbfb; border-bottom: 1px solid var(--line);">
                            <th style="padding: 10px 14px; font-size: 11.5px; text-transform: uppercase; letter-spacing: 0.05em; color: var(--muted); width: 140px; font-weight: 700; border-bottom: 1px solid var(--line); background: #fbfbfb;">Date &amp; Time</th>
                            <th style="padding: 10px 14px; font-size: 11.5px; text-transform: uppercase; letter-spacing: 0.05em; color: var(--muted); font-weight: 700; border-bottom: 1px solid var(--line); background: #fbfbfb;">Assigned Liturgy</th>
                            <th style="padding: 10px 14px; font-size: 11.5px; text-transform: uppercase; letter-spacing: 0.05em; color: var(--muted); width: 100px; text-align: center; font-weight: 700; border-bottom: 1px solid var(--line); background: #fbfbfb;">Type</th>
                            <?php if ($is_admin_view): ?>
                            <th style="padding: 10px 14px; font-size: 11.5px; text-transform: uppercase; letter-spacing: 0.05em; color: var(--muted); width: 160px; font-weight: 700; border-bottom: 1px solid var(--line); background: #fbfbfb;">Priest</th>
                            <?php endif; ?>
                            <th style="padding: 10px 14px; font-size: 11.5px; text-transform: uppercase; letter-spacing: 0.05em; color: var(--muted); width: 160px; font-weight: 700; border-bottom: 1px solid var(--line); background: #fbfbfb;">Location</th>
                            <th style="padding: 10px 14px; font-size: 11.5px; text-transform: uppercase; letter-spacing: 0.05em; color: var(--muted); width: 100px; text-align: center; font-weight: 700; border-bottom: 1px solid var(--line); background: #fbfbfb;">Status</th>
                            <th style="padding: 10px 14px; font-size: 11.5px; text-transform: uppercase; letter-spacing: 0.05em; color: var(--muted); width: 75px; text-align: center; font-weight: 700; border-bottom: 1px solid var(--line); background: #fbfbfb;">Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        foreach ($pastor_schedule as $idx => $ev): 
                            $has_conflict = $hasPriestConflict($ev, $idx);
                        ?>
                        <tr style="border-bottom: 1px solid var(--line); transition: background 0.12s ease; <?php echo $has_conflict ? 'background: rgba(214,69,69,0.04);' : ''; ?>">
                            
                            <!-- Date & Time -->
                            <td style="padding: 12px 14px; white-space: nowrap; border-bottom: 1px solid var(--line);">
                                <div style="font-weight: 600; font-size: 13.5px; color: var(--text);">
                                    <?php echo date('M d, Y', strtotime($ev['start_time'])); ?>
                                </div>
                                <div style="font-size: 12px; color: var(--text2); margin-top: 2px;">
                                    <i class="fa-regular fa-clock" style="font-size: 10px; margin-right: 4px;"></i>
                                    <?php echo date('h:i A', strtotime($ev['start_time'])); ?>
                                </div>
                            </td>

                            <!-- Liturgy Title -->
                            <td style="padding: 12px 14px;">
                                <div style="font-weight: 600; font-size: 14px; color: var(--text); line-height: 1.3;">
                                    <?php echo htmlspecialchars($ev['title']); ?>
                                </div>
                                <?php if ($has_conflict): ?>
                                <div style="font-size: 11.5px; color: #D64545; font-weight: 600; margin-top: 3px;">
                                    <i class="fa-solid fa-triangle-exclamation"></i> Overlapping slot
                                </div>
                                <?php endif; ?>
                            </td>

                            <!-- Type -->
                            <td style="padding: 12px 14px; text-align: center;">
                                <?php echo renderPriestTypeBadge($ev['event_type'] ?? 'Other'); ?>
                            </td>

                            <!-- Celebrant (Admin) -->
                            <?php if ($is_admin_view): ?>
                            <td style="padding: 12px 14px; font-size: 13px; color: var(--text);">
                                <?php 
                                $pName = trim($ev['pastor_assigned'] ?? '');
                                if (!empty($pName)) {
                                    $displayName = preg_match('/^(Fr\.|Rev\.|Father|Msgr\.)/i', $pName) ? $pName : 'Fr. ' . $pName;
                                    echo '<span style="font-weight: 500;"><i class="fa-solid fa-user-tie" style="font-size: 11px; color: var(--text2); margin-right: 5px;"></i>' . htmlspecialchars($displayName) . '</span>';
                                } else {
                                    echo '<span style="color: var(--text2); opacity: 0.6;">— Unassigned —</span>';
                                }
                                ?>
                            </td>
                            <?php endif; ?>

                            <!-- Location -->
                            <td style="padding: 12px 14px; font-size: 13px; color: var(--text);">
                                <span style="display: inline-flex; align-items: center; gap: 5px;">
                                    <i class="fa-solid fa-place-of-worship" style="font-size: 11px; color: var(--text2);"></i>
                                    <?php echo htmlspecialchars($ev['chapel_location'] ?? ($ev['chapel_name'] ?? 'Main Cathedral')); ?>
                                </span>
                            </td>

                            <!-- Status -->
                            <td style="padding: 12px 14px; text-align: center;">
                                <?php echo renderPriestStatusBadge($ev['status'] ?? 'Confirmed'); ?>
                            </td>

                            <!-- Details Button -->
                            <td style="padding: 12px 14px; text-align: center;">
                                <button type="button" 
                                        class="ps-tab-link" 
                                        data-event="<?php echo htmlspecialchars(json_encode($ev, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>"
                                        onclick="viewEventDetails(this)"
                                        title="View duty details"
                                        style="padding: 5px 10px; font-size: 12px; min-height: 28px; cursor: pointer;">
                                    <i class="fa-regular fa-eye"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

    <!-- ══════════════════════════════════════════════════════════ -->
    <!-- TAB 2: CLERGY AUTHORIZATIONS & SHIFTS                       -->
    <!-- ══════════════════════════════════════════════════════════ -->
    <?php else: ?>

        <?php if ($is_admin_view): ?>
        <form id="form-priest-prefs" method="POST" action="dashboard.php?section=priest_schedule&amp;tab=authorizations<?php echo (!empty($prefTargetId)) ? '&amp;priest_id=' . (int)$prefTargetId : ''; ?>">
            <input type="hidden" name="form_section" value="priest_prefs">
            <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
            <input type="hidden" name="next_priest_id" id="field-next-priest-id" value="">
            <?php if (!empty($prefTargetId)): ?>
                <input type="hidden" name="priest_id" value="<?php echo (int)$prefTargetId; ?>">
            <?php endif; ?>

            <div class="clergy-workspace-grid">
                
                <!-- ─── LEFT COLUMN: CLERGY DIRECTORY ─── -->
                <div class="clergy-dir-card">
                    <div class="clergy-dir-header">
                        <h2 class="clergy-dir-title">
                            <i class="fa-solid fa-users-line"></i>
                            Clergy Directory
                        </h2>
                        <span class="clergy-dir-count"><?php echo count($all_priests ?? []); ?> Priests</span>
                    </div>

                    <div class="clergy-list-stack">
                        <?php foreach (($all_priests ?? []) as $p): 
                            $isActive = ((string)$prefTargetId === (string)$p['id']);
                            $pFullName = !empty($p['full_name']) ? $p['full_name'] : $p['user'];
                            $pInitials = getPriestInitials($pFullName);
                            $pTypes = !empty($p['event_types']) ? array_filter(explode(',', $p['event_types'])) : [];
                            $pCov   = !empty($p['chapel_coverage']) ? array_filter(array_map('trim', explode(',', $p['chapel_coverage']))) : [];
                            $pShift = '';
                            if (!empty($p['duty_start_time']) && !empty($p['duty_end_time'])) {
                                $pShift = date('g:i A', strtotime($p['duty_start_time'])) . ' – ' . date('g:i A', strtotime($p['duty_end_time']));
                            } else {
                                $pShift = '8:00 AM – 5:00 PM';
                            }
                        ?>
                        <a href="dashboard.php?section=priest_schedule&amp;tab=authorizations&amp;priest_id=<?php echo $p['id']; ?>" 
                           onclick="return switchPriest(event, <?php echo $p['id']; ?>);"
                           class="clergy-nav-card <?php echo $isActive ? 'active' : ''; ?>">
                            <div class="clergy-avatar">
                                <?php echo htmlspecialchars($pInitials); ?>
                            </div>
                            <div class="clergy-nav-info">
                                <span class="clergy-nav-name"><?php echo htmlspecialchars($pFullName); ?></span>
                                <span class="clergy-nav-role">Priest</span>
                                <div class="clergy-nav-meta">
                                    <span class="clergy-meta-tag"><i class="fa-solid fa-cross" style="font-size:9px;"></i> <?php echo count($pTypes); ?> Rites</span>
                                    <span class="clergy-meta-tag"><i class="fa-solid fa-place-of-worship" style="font-size:9px;"></i> <?php echo count($pCov); ?> Chapels</span>
                                </div>
                            </div>
                            <div class="clergy-active-indicator">
                                <i class="fa-solid fa-circle-check"></i>
                            </div>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- ─── RIGHT COLUMN: PRIEST CONFIGURATION STUDIO ─── -->
                <div class="clergy-config-card">
                    
                    <!-- 1. Hero / Profile Header -->
                    <div class="clergy-hero-banner">
                        <div class="clergy-hero-left">
                            <div class="clergy-hero-avatar">
                                <?php echo htmlspecialchars(getPriestInitials($targetName)); ?>
                            </div>
                            <div>
                                <h2 class="clergy-hero-name"><?php echo htmlspecialchars($targetName); ?></h2>
                                <p class="clergy-hero-title">Diocesan Priest &middot; St. Michael Parish</p>
                            </div>
                        </div>

                        <div class="clergy-hero-stats">
                            <div class="clergy-hero-pill">
                                <i class="fa-solid fa-cross"></i>
                                <span><?php echo count($my_event_types ?? []); ?>/6 Rites</span>
                            </div>
                            <div class="clergy-hero-pill">
                                <i class="fa-solid fa-place-of-worship"></i>
                                <span><?php echo count($cov); ?> Chapels</span>
                            </div>
                            <div class="clergy-hero-pill">
                                <i class="fa-solid fa-clock"></i>
                                <span>
                                    <?php 
                                    $stFmt = !empty($target_priest['duty_start_time']) ? date('g:i A', strtotime($target_priest['duty_start_time'])) : '8:00 AM';
                                    $endFmt = !empty($target_priest['duty_end_time']) ? date('g:i A', strtotime($target_priest['duty_end_time'])) : '5:00 PM';
                                    echo "{$stFmt} – {$endFmt}";
                                    ?>
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- 2. Section: Sacramental Authorizations -->
                    <div class="clergy-section">
                        <div class="clergy-sec-head">
                            <h3 class="clergy-sec-title">
                                <i class="fa-solid fa-cross"></i>
                                Officiated Sacramental Rites
                            </h3>
                            <div class="clergy-sec-actions">
                                <a href="javascript:void(0)" onclick="setAllRites(true)">Select All</a>
                                <span style="color: var(--border);">|</span>
                                <a href="javascript:void(0)" onclick="setAllRites(false)">Clear</a>
                            </div>
                        </div>

                        <div class="auth-cards-grid">
                            <?php foreach ($all_types as $type => $meta):
                                $checked = in_array($type, $my_event_types ?? []);
                                $riteInfo = $riteMetadata[$type] ?? [
                                    'name' => $type,
                                    'desc' => '',
                                    'icon' => $meta['icon']
                                ];
                            ?>
                            <label class="auth-check-card <?php echo $checked ? 'is-checked selected' : ''; ?>" title="<?php echo htmlspecialchars($riteInfo['name']); ?>">
                                <div class="auth-card-left">
                                    <div class="auth-card-icon">
                                        <i class="<?php echo $riteInfo['icon']; ?>"></i>
                                    </div>
                                    <span class="auth-card-name"><?php echo htmlspecialchars($riteInfo['name']); ?></span>
                                </div>
                                <input type="checkbox" name="event_types[]" value="<?php echo $type; ?>" <?php echo $checked ? 'checked' : ''; ?> 
                                       class="auth-checkbox-styled"
                                       onchange="this.closest('label').classList.toggle('is-checked', this.checked); this.closest('label').classList.toggle('selected', this.checked); markDirty();">
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- 3. Section: Chapel & Location Jurisdiction -->
                    <div class="clergy-section">
                        <div class="clergy-sec-head">
                            <h3 class="clergy-sec-title">
                                <i class="fa-solid fa-map-location-dot"></i>
                                Chapel Jurisdiction
                            </h3>
                            <div class="clergy-sec-actions">
                                <a href="javascript:void(0)" onclick="setAllChapels(true)">Select All</a>
                                <span style="color: var(--border);">|</span>
                                <a href="javascript:void(0)" onclick="setAllChapels(false)">Clear</a>
                            </div>
                        </div>

                        <div class="chapel-cards-grid">
                            <?php 
                            $cathedChecked = in_array('Main Cathedral', $cov);
                            ?>
                            <label class="auth-check-card <?php echo $cathedChecked ? 'is-checked selected' : ''; ?>" title="Main Cathedral">
                                <div class="auth-card-left">
                                    <div class="auth-card-icon">
                                        <i class="fa-solid fa-church"></i>
                                    </div>
                                    <span class="auth-card-name">Main Cathedral</span>
                                </div>
                                <input type="checkbox" name="chapel_coverage[]" value="Main Cathedral" <?php echo $cathedChecked ? 'checked' : ''; ?>
                                       class="auth-checkbox-styled"
                                       onchange="this.closest('label').classList.toggle('is-checked', this.checked); this.closest('label').classList.toggle('selected', this.checked); markDirty();">
                            </label>

                            <?php foreach ($chapels as $ch): 
                                $chChecked = in_array($ch['chapel_name'], $cov);
                            ?>
                            <label class="auth-check-card <?php echo $chChecked ? 'is-checked selected' : ''; ?>" title="<?php echo htmlspecialchars($ch['location'] ?? $ch['chapel_name']); ?>">
                                <div class="auth-card-left">
                                    <div class="auth-card-icon">
                                        <i class="fa-solid fa-place-of-worship"></i>
                                    </div>
                                    <span class="auth-card-name"><?php echo htmlspecialchars($ch['chapel_name']); ?></span>
                                </div>
                                <input type="checkbox" name="chapel_coverage[]" value="<?php echo htmlspecialchars($ch['chapel_name']); ?>" <?php echo $chChecked ? 'checked' : ''; ?>
                                       class="auth-checkbox-styled"
                                       onchange="this.closest('label').classList.toggle('is-checked', this.checked); this.closest('label').classList.toggle('selected', this.checked); markDirty();">
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- 4. Section: Daily Duty Shift & Availability Hours -->
                    <div class="clergy-section">
                        <div class="clergy-sec-head">
                            <h3 class="clergy-sec-title">
                                <i class="fa-solid fa-clock"></i>
                                Daily Pastoral Duty Shift
                            </h3>
                        </div>

                        <div class="shift-ctrl-row">
                            <div class="shift-input-group">
                                <span class="shift-label">From:</span>
                                <input type="time" name="duty_start_time" id="priest-duty-start" 
                                       value="<?php echo !empty($target_priest['duty_start_time']) ? substr($target_priest['duty_start_time'], 0, 5) : '08:00'; ?>" 
                                       required class="shift-time-picker">
                            </div>

                            <div class="shift-input-group">
                                <span class="shift-label">To:</span>
                                <input type="time" name="duty_end_time" id="priest-duty-end" 
                                       value="<?php echo !empty($target_priest['duty_end_time']) ? substr($target_priest['duty_end_time'], 0, 5) : '17:00'; ?>" 
                                       required class="shift-time-picker">
                            </div>

                            <div class="shift-presets-stack" id="shift-presets-ctrl" style="margin-left: auto;">
                                <button type="button" class="btn-shift-preset" data-preset="morning" onclick="setDutyShift('08:00', '12:00')">
                                    <i class="fa-regular fa-sun"></i> Morning (8–12)
                                </button>
                                <button type="button" class="btn-shift-preset" data-preset="afternoon" onclick="setDutyShift('13:00', '17:00')">
                                    <i class="fa-solid fa-sun"></i> Afternoon (1–5)
                                </button>
                                <button type="button" class="btn-shift-preset" data-preset="fullday" onclick="setDutyShift('08:00', '17:00')">
                                    <i class="fa-regular fa-clock"></i> Full Day (8–5)
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- 5. Footer Actions -->
                    <div class="clergy-card-footer">
                        <div class="clergy-footer-hint">
                            <i class="fa-solid fa-circle-info"></i>
                            <span>Auto-saves when switching priests</span>
                        </div>

                        <button type="submit" id="btn-save-auth-main" class="btn-gold-action">
                            <i class="fa-solid fa-floppy-disk"></i> Save Changes
                        </button>
                    </div>

                </div>

            </div>
        </form>
        <?php else: ?>
        <!-- ─── READ-ONLY VIEW FOR INDIVIDUAL PRIESTS ─── -->
        <div class="clergy-config-card">
            <div class="clergy-hero-banner">
                <div class="clergy-hero-left">
                    <div class="clergy-hero-avatar">
                        <?php echo htmlspecialchars(getPriestInitials($targetName)); ?>
                    </div>
                    <div>
                        <h2 class="clergy-hero-name"><?php echo htmlspecialchars($targetName); ?></h2>
                        <p class="clergy-hero-title">Diocesan Priest &middot; Pastoral Commission</p>
                    </div>
                </div>

                <div class="clergy-hero-stats">
                    <div class="clergy-hero-pill">
                        <i class="fa-solid fa-cross"></i>
                        <span><?php echo count($my_event_types ?? []); ?>/6 Rites</span>
                    </div>
                    <div class="clergy-hero-pill">
                        <i class="fa-solid fa-place-of-worship"></i>
                        <span><?php echo count($cov); ?> Chapels</span>
                    </div>
                </div>
            </div>

            <div class="clergy-section">
                <h3 class="clergy-sec-title"><i class="fa-solid fa-cross" style="color: var(--accent, #C5A059);"></i> My Officiated Sacraments</h3>
                <div class="auth-cards-grid" style="margin-top: 10px;">
                    <?php foreach ($all_types as $type => $meta):
                        $isAssigned = in_array($type, $my_event_types ?? []);
                        $riteInfo = $riteMetadata[$type] ?? ['name' => $type, 'desc' => '', 'icon' => $meta['icon']];
                    ?>
                    <div class="auth-check-card <?php echo $isAssigned ? 'is-checked selected' : ''; ?>" style="cursor: default;" title="<?php echo htmlspecialchars($riteInfo['name']); ?>">
                        <div class="auth-card-left">
                            <div class="auth-card-icon"><i class="<?php echo $riteInfo['icon']; ?>"></i></div>
                            <span class="auth-card-name"><?php echo htmlspecialchars($riteInfo['name']); ?></span>
                        </div>
                        <?php if ($isAssigned): ?>
                            <i class="fa-solid fa-circle-check" style="color: var(--accent, #C5A059); font-size: 14px;"></i>
                        <?php else: ?>
                            <i class="fa-solid fa-circle-xmark" style="color: var(--text2); opacity: 0.3; font-size: 14px;"></i>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="clergy-section">
                <h3 class="clergy-sec-title"><i class="fa-solid fa-map-location-dot" style="color: var(--accent, #C5A059);"></i> Assigned Chapels</h3>
                <div class="chapel-cards-grid" style="margin-top: 10px;">
                    <?php if (in_array('Main Cathedral', $cov)): ?>
                    <div class="auth-check-card is-checked selected" style="cursor: default;" title="Main Cathedral">
                        <div class="auth-card-left">
                            <div class="auth-card-icon"><i class="fa-solid fa-church"></i></div>
                            <span class="auth-card-name">Main Cathedral</span>
                        </div>
                        <i class="fa-solid fa-circle-check" style="color: var(--accent, #C5A059); font-size: 14px;"></i>
                    </div>
                    <?php endif; ?>
                    <?php foreach ($chapels as $ch): if (in_array($ch['chapel_name'], $cov)): ?>
                    <div class="auth-check-card is-checked selected" style="cursor: default;" title="<?php echo htmlspecialchars($ch['chapel_name']); ?>">
                        <div class="auth-card-left">
                            <div class="auth-card-icon"><i class="fa-solid fa-place-of-worship"></i></div>
                            <span class="auth-card-name"><?php echo htmlspecialchars($ch['chapel_name']); ?></span>
                        </div>
                        <i class="fa-solid fa-circle-check" style="color: var(--accent, #C5A059); font-size: 14px;"></i>
                    </div>
                    <?php endif; endforeach; ?>
                </div>
            </div>

            <div class="clergy-card-footer">
                <div style="font-size: 13px; color: var(--text);">
                    Official Shift Hours: <strong><?php echo (!empty($target_priest['duty_start_time']) ? date('g:i A', strtotime($target_priest['duty_start_time'])) : '8:00 AM') . ' – ' . (!empty($target_priest['duty_end_time']) ? date('g:i A', strtotime($target_priest['duty_end_time'])) : '5:00 PM'); ?></strong>
                </div>
                <a href="dashboard.php?section=priest_schedule&amp;tab=schedule" class="btn-gold-action" style="font-size: 12px; padding: 6px 14px;">
                    <i class="fa-solid fa-calendar-check"></i> View My Schedule
                </a>
            </div>
        </div>
        <?php endif; ?>

    <?php endif; ?>
</div>

<script>
window.pastorEvents = <?php echo json_encode($pastor_schedule ?? []); ?>;

let isFormDirty = false;

function markDirty() {
    isFormDirty = true;
}

function switchPriest(event, targetPriestId) {
    if (isFormDirty) {
        event.preventDefault();
        const nextInput = document.getElementById('field-next-priest-id');
        const form = document.getElementById('form-priest-prefs');
        if (nextInput && form) {
            nextInput.value = targetPriestId;
            form.submit();
            return false;
        }
    }
    return true;
}

function updateShiftPresetHighlight() {
    const s = document.getElementById('priest-duty-start');
    const e = document.getElementById('priest-duty-end');
    if (!s || !e) return;
    const sVal = (s.value || '').substring(0, 5);
    const eVal = (e.value || '').substring(0, 5);
    
    const morningBtn = document.querySelector('.btn-shift-preset[data-preset="morning"]');
    const afternoonBtn = document.querySelector('.btn-shift-preset[data-preset="afternoon"]');
    const fulldayBtn = document.querySelector('.btn-shift-preset[data-preset="fullday"]');

    if (morningBtn) morningBtn.classList.toggle('active', (sVal === '08:00' && eVal === '12:00'));
    if (afternoonBtn) afternoonBtn.classList.toggle('active', (sVal === '13:00' && eVal === '17:00'));
    if (fulldayBtn) fulldayBtn.classList.toggle('active', (sVal === '08:00' && eVal === '17:00'));
}

function setDutyShift(start, end) {
    const s = document.getElementById('priest-duty-start');
    const e = document.getElementById('priest-duty-end');
    if (s) s.value = start;
    if (e) e.value = end;
    updateShiftPresetHighlight();
    markDirty();
}

function setAllChapels(selectVal) {
    const inputs = document.querySelectorAll('input[name="chapel_coverage[]"]');
    inputs.forEach(input => {
        input.checked = selectVal;
        const parent = input.closest('label');
        if (parent) {
            parent.classList.toggle('is-checked', selectVal);
            parent.classList.toggle('selected', selectVal);
        }
    });
    markDirty();
}

function setAllRites(selectVal) {
    const inputs = document.querySelectorAll('input[name="event_types[]"]');
    inputs.forEach(input => {
        input.checked = selectVal;
        const parent = input.closest('label');
        if (parent) {
            parent.classList.toggle('is-checked', selectVal);
            parent.classList.toggle('selected', selectVal);
        }
    });
    markDirty();
}

document.addEventListener('DOMContentLoaded', function() {
    updateShiftPresetHighlight();

    const s = document.getElementById('priest-duty-start');
    const e = document.getElementById('priest-duty-end');
    if (s) {
        s.addEventListener('input', updateShiftPresetHighlight);
        s.addEventListener('change', updateShiftPresetHighlight);
    }
    if (e) {
        e.addEventListener('input', updateShiftPresetHighlight);
        e.addEventListener('change', updateShiftPresetHighlight);
    }

    const form = document.getElementById('form-priest-prefs');
    if (form) {
        form.querySelectorAll('input').forEach(el => {
            el.addEventListener('change', function() {
                if (el.type === 'checkbox') {
                    const parent = el.closest('label');
                    if (parent) {
                        parent.classList.toggle('is-checked', el.checked);
                        parent.classList.toggle('selected', el.checked);
                    }
                }
                markDirty();
            });
            el.addEventListener('input', markDirty);
        });
    }
});
</script>
