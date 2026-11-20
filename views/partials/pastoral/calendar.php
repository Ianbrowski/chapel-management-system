<!-- ═══════════════════════════════════════════════════════════════ -->
<!-- LITURGICAL CALENDAR & SCHEDULES — Streamlined Operations View      -->
<!-- Cathedral of St. Michael the Archangel • IFI                      -->
<!-- ═══════════════════════════════════════════════════════════════ -->
<?php
// Statistics calculation from precomputed data or active list
$stTotal     = $event_stats['total'] ?? $total_events ?? count($events ?? []);
$stArchived  = $event_stats['archived'] ?? 0;
$stConfirmed = $event_stats['confirmed'] ?? 0;
$stPending   = $event_stats['pending'] ?? 0;
$stCompleted = $event_stats['completed'] ?? 0;
$stCancelled = $event_stats['cancelled'] ?? 0;

if (empty($event_stats)) {
    foreach (($all_calendar_events ?? $events ?? []) as $ev) {
        $st = strtolower($ev['status'] ?? '');
        if ($st === 'confirmed') $stConfirmed++;
        elseif ($st === 'pending') $stPending++;
        elseif ($st === 'completed') $stCompleted++;
        elseif ($st === 'cancelled') $stCancelled++;
    }
}

$typeColorsMap = [
    'Mass'         => '#000000',
    'Wedding'      => '#000000',
    'Baptism'      => '#000000',
    'Funeral'      => '#71717a',
    'Confirmation' => '#000000',
    'Fiesta'       => '#000000',
    'Other'        => '#71717a'
];

$canCreateEvent = (($can_cathedral_sched || $can_chapel_sched) && $role !== 'Staff');
$canEditEvents  = in_array($role, ['Admin', 'Cathedral Admin', 'Chapel Admin']);
$defaultMode    = (!empty($ev_filters['search']) || !empty($ev_filters['event_type']) || !empty($ev_filters['venue']) || isset($_GET['ev_page']) || (isset($_GET['sched_mode']) && $_GET['sched_mode'] === 'table') || ($ev_tab === 'archived')) ? 'table' : '';

$isScopedToChapel = !in_array($role, ['Admin', 'Cathedral Admin', 'Priest', 'Pastor'], true) && !empty($chapel_id);
$isCathedralStaff = ($role === 'Staff' && empty($chapel_id));
?>

<div class="section <?php echo ($active_section === 'events' || !$active_section) ? 'active' : ''; ?>" 
     id="sec-events">

    <style>
        #sec-events {
            padding: 14px clamp(16px, 1.8vw, 24px) 24px !important;
            width: 100%;
            max-width: 1680px;
            margin: 0 auto;
            box-sizing: border-box;
            display: none;
            flex-direction: column;
            gap: 14px !important;
            font-family: var(--font-family);
            background: var(--bg);
            color: var(--text);
            min-height: 100%;
            height: auto;
        }
        #sec-events.active {
            display: flex !important;
        }

        /* ── Minimal Toolbar ───────────────────────────────────────── */
        .sched-top-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            background: var(--bg);
            border-bottom: 1px solid var(--border);
            padding-bottom: 8px;
            margin-bottom: 2px;
            flex-wrap: wrap;
            flex-shrink: 0;
        }
        .sched-top-left {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        .sched-title {
            font-family: var(--font-family);
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--text);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
            letter-spacing: -0.01em;
        }
        .sched-top-right {
            display: flex;
            align-items: center;
            gap: 14px;
            flex-wrap: wrap;
        }

        /* Venue Select (Simple Outlined Dropdown) */
        .sched-venue-wrap {
            display: inline-flex;
            align-items: center;
            background: var(--panel-bg);
            border: 1px solid var(--border);
            border-radius: 6px;
            padding: 0 8px;
            height: 28px;
            box-sizing: border-box;
        }
        .sched-venue-select {
            font-family: var(--font-family);
            font-size: 12px;
            font-weight: 400;
            color: var(--text);
            background: transparent;
            border: none;
            outline: none;
            cursor: pointer;
            padding: 2px 0;
        }

        /* Modern Segmented Pill Tabs */
        .sched-tabs-group {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            flex-wrap: wrap;
        }
        .sched-tab-link {
            background: transparent;
            border: 1px solid transparent;
            color: var(--text2);
            font-family: var(--font-family);
            font-size: 12.5px;
            font-weight: 500;
            padding: 7px 16px !important;
            border-radius: 999px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 7px;
            line-height: 1.2;
            min-height: 34px;
            box-sizing: border-box;
            white-space: nowrap;
            transition: all 0.18s ease;
        }
        .sched-tab-link i {
            font-size: 12px;
            transition: color 0.18s ease;
        }
        .sched-tab-link:hover {
            color: var(--text);
            background: var(--hover-bg, rgba(0,0,0,0.04));
        }
        .sched-tab-link.active {
            background: #111111 !important;
            color: #FFFFFF !important;
            border-color: #111111 !important;
            font-weight: 700;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.14);
        }
        .sched-tab-link.active i {
            color: #FFFFFF !important;
        }
        [data-theme="dark"] .sched-tabs-group {
            background: #18181b !important;
            border-color: rgba(255, 255, 255, 0.12) !important;
        }
        [data-theme="dark"] .sched-tab-link {
            color: #cbd5e1;
        }
        [data-theme="dark"] .sched-tab-link:hover {
            color: #ffffff;
            background: rgba(255, 255, 255, 0.08);
        }
        [data-theme="dark"] .sched-tab-link.active {
            background: #FFFFFF !important;
            color: #111111 !important;
            border-color: #FFFFFF !important;
            box-shadow: 0 2px 8px rgba(0,0,0,0.3);
        }
        [data-theme="dark"] .sched-tab-link.active i {
            color: #111111 !important;
        }
        .sched-tab-badge {
            background: rgba(17, 17, 17, 0.1);
            color: inherit;
            font-weight: 700;
            font-size: 10.5px;
            padding: 1px 7px;
            border-radius: 999px;
            margin-left: 2px;
            display: inline-flex;
            align-items: center;
        }
        [data-theme="dark"] .sched-tab-badge {
            background: rgba(255, 255, 255, 0.15);
            color: #ffffff;
        }
        [data-theme="dark"] .sched-tab-link.active .sched-tab-badge {
            background: rgba(0, 0, 0, 0.15) !important;
            color: #111111 !important;
        }

        /* The ONE Primary Button */
        .sched-btn-primary {
            background: #111111;
            color: #FFFFFF;
            border: none;
            border-radius: 6px;
            font-family: var(--font-family);
            font-size: 12px;
            font-weight: 600;
            padding: 6px 14px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            cursor: pointer;
            transition: opacity 0.15s ease;
        }
        .sched-btn-primary:hover {
            opacity: 0.9;
        }
        [data-theme="dark"] .sched-btn-primary {
            background: var(--accent);
            color: #111111;
        }

        /* ── Workspace 2-Column Grid ───────────────────────────────── */
        .sched-workspace-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.85fr) minmax(320px, 1fr);
            gap: 16px;
            align-items: stretch;
        }
        @media (max-width: 1024px) {
            .sched-workspace-grid {
                grid-template-columns: 1fr;
            }
        }

        /* ── Cards: 1px Border, 12px Radius ── */
        .sched-cal-card,
        .sched-slots-card {
            background: var(--panel-bg);
            border: 1px solid var(--border);
            border-radius: 12px;
            box-shadow: 0 4px 18px -2px rgba(0, 0, 0, 0.04);
            padding: 16px 18px;
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
            min-height: 600px;
            height: 100%;
        }

        /* ── Calendar Card Header Bar ──────────────────────────────── */
        .sched-cal-header-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 10px;
            flex-wrap: wrap;
            flex-shrink: 0;
        }
        .sched-month-nav {
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .cal-month-heading {
            font-family: var(--font-family, 'Inter', sans-serif);
            font-size: 1.15rem;
            font-weight: 700;
            color: var(--text);
            min-width: 125px;
            text-align: center;
            letter-spacing: -0.01em;
        }
        .cal-nav-btn {
            width: 28px;
            height: 28px;
            border: 1px solid var(--border);
            border-radius: 6px;
            background: var(--panel-bg);
            color: var(--text);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 11px;
            transition: background 0.15s ease;
        }
        .cal-nav-btn:hover {
            background: var(--hover-bg);
        }
        .cal-today-btn {
            height: 28px;
            padding: 0 10px;
            border: 1px solid var(--border);
            border-radius: 6px;
            background: var(--panel-bg);
            color: var(--text);
            font-size: 11.5px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.15s ease;
        }
        .cal-today-btn:hover {
            background: var(--hover-bg);
        }

        .sched-cal-controls {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }
        .cal-venue-dropdown-wrap {
            position: relative;
            display: inline-flex;
            align-items: center;
        }
        .cal-venue-select-styled {
            height: 32px !important;
            min-height: 32px !important;
            line-height: 32px !important;
            padding: 0 30px 0 10px !important;
            border: 1px solid var(--border) !important;
            border-radius: 6px !important;
            background-color: var(--bg2, #FAFAFA) !important;
            color: var(--text) !important;
            font-size: 12px !important;
            font-weight: 500 !important;
            cursor: pointer;
            outline: none !important;
            box-shadow: none !important;
            transition: border-color 0.15s ease, box-shadow 0.15s ease;
            width: auto !important;
        }
        .cal-venue-select-styled:hover,
        .cal-venue-select-styled:focus {
            border-color: var(--accent, #C5A059) !important;
        }
        .cal-venue-select-styled:disabled {
            background-image: none !important;
            opacity: 0.9;
            cursor: default;
        }
        .cal-venue-lock-icon {
            position: absolute;
            right: 9px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 10px;
            color: var(--text2);
            pointer-events: none;
            opacity: 0.7;
        }

        .sched-legend-dots {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            font-size: 11.5px;
            color: var(--text2);
            font-weight: 500;
        }
        .legend-dot-item {
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .legend-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            display: inline-block;
        }
        .legend-dot.green { background: #16a34a; }
        .legend-dot.amber { background: var(--accent, #c5a059); }
        .legend-dot.red   { background: #dc2626; }

        .btn-new-event-gold {
            background: var(--accent, #C5A059);
            color: #FFFFFF;
            border: none;
            border-radius: 6px;
            padding: 6px 14px;
            font-size: 12px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            cursor: pointer;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
            transition: opacity 0.15s ease, transform 0.15s ease;
        }
        .btn-new-event-gold:hover {
            opacity: 0.92;
            transform: translateY(-1px);
        }

        /* ── Venue Filter Dropdown in Monthly Calendar ───────────────── */
        .cal-venue-picker-wrap {
            display: inline-flex;
            align-items: center;
            height: 28px;
            padding: 0 4px 0 8px;
            max-width: 200px;
            box-sizing: border-box;
            background: var(--panel-bg);
            border: 1px solid var(--border);
            border-radius: 6px;
            transition: border-color 0.15s ease;
        }
        .cal-venue-picker-wrap:hover {
            border-color: var(--accent, #D4AF37);
        }
        .cal-venue-select-picker {
            font-family: var(--font-family);
            font-weight: 500;
            font-size: 11.5px;
            color: var(--text) !important;
            background: transparent !important;
            background-color: transparent !important;
            border: none !important;
            border-radius: 0 !important;
            cursor: pointer;
            outline: none !important;
            box-shadow: none !important;
            padding: 0 16px 0 4px !important;
            margin: 0 !important;
            height: 26px !important;
            line-height: 26px !important;
            max-width: 165px;
            text-overflow: ellipsis;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%23888888'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E") !important;
            background-repeat: no-repeat !important;
            background-position: right 2px center !important;
            background-size: 11px !important;
            -webkit-appearance: none !important;
            -moz-appearance: none !important;
            appearance: none !important;
        }
        .cal-venue-select-picker:focus {
            border: none !important;
            outline: none !important;
            box-shadow: none !important;
        }
        .cal-venue-select-picker option {
            background: var(--panel-bg);
            color: var(--text);
        }

        /* Calendar Month Nav Controls */
        .sched-cal-nav {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .cal-picker-wrap {
            display: inline-flex;
            align-items: center;
            gap: 2px;
            background: var(--panel-bg);
            border: 1px solid var(--border);
            border-radius: 6px;
            padding: 0 4px;
            height: 28px;
            box-sizing: border-box;
            flex-shrink: 0;
            transition: border-color 0.15s ease;
        }
        .cal-picker-wrap:hover {
            border-color: var(--accent, #D4AF37);
        }
        .cal-picker-divider {
            width: 1px;
            height: 12px;
            background: var(--border);
            flex-shrink: 0;
            margin: 0 1px;
        }
        .cal-select-picker {
            font-family: var(--font-family);
            font-weight: 600;
            font-size: 11.5px;
            color: var(--text);
            background: transparent !important;
            border: none !important;
            cursor: pointer;
            outline: none !important;
            box-shadow: none !important;
        }
        #cal-month-select,
        #cal-year-select {
            appearance: none !important;
            -webkit-appearance: none !important;
            -moz-appearance: none !important;
            background: transparent !important;
            background-image: none !important;
            border: none !important;
            outline: none !important;
            box-shadow: none !important;
            text-align: center;
            padding: 0 3px !important;
            margin: 0 !important;
            height: 26px !important;
            line-height: 26px !important;
            color: var(--text);
            font-weight: 600;
            font-size: 11.5px;
            cursor: pointer;
            box-sizing: border-box;
        }
        #cal-month-select:hover,
        #cal-year-select:hover {
            color: var(--accent, #D4AF37);
        }
        #cal-month-select {
            width: 42px;
            min-width: 42px;
        }
        #cal-year-select {
            width: 46px;
            min-width: 46px;
        }
        #cal-month-select option,
        #cal-year-select option {
            background: var(--panel-bg);
            color: var(--text);
            font-size: 12px;
        }

        /* ── Calendar Cells (Hairline grid, sleek 80% view proportions) ── */
        .sched-grid-wrap {
            padding: 0;
            background: var(--panel-bg);
            flex: 1;
            display: flex;
            flex-direction: column;
            min-height: 510px;
        }
        .cal-grid-modern {
            display: grid;
            grid-template-columns: repeat(7, minmax(0, 1fr));
            gap: 1px;
            background: var(--border);
            border: 1px solid var(--border);
            border-radius: 8px;
            overflow: hidden;
            width: 100%;
            flex: 1;
            min-height: 510px;
            box-sizing: border-box;
        }
        .ov-cal-day-hdr {
            background: var(--bg2, var(--bg3));
            text-align: center;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--text2);
            height: 28px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        [data-theme="dark"] .ov-cal-day-hdr {
            background: rgba(255, 255, 255, 0.04);
        }
        .ov-cal-day {
            background: var(--panel-bg) !important;
            min-height: 86px;
            height: 100%;
            padding: 5px 6px;
            display: flex;
            flex-direction: column;
            align-items: stretch;
            cursor: pointer;
            transition: background 0.12s ease;
            position: relative;
            box-sizing: border-box;
            user-select: none;
            overflow: hidden;
            gap: 2px;
        }
        .ov-cal-day:hover {
            background: var(--hover-bg) !important;
        }
        .ov-cal-day.ov-selected-day {
            background: rgba(197, 160, 89, 0.08) !important;
            box-shadow: inset 0 0 0 1.5px rgba(197, 160, 89, 0.5) !important;
        }
        .ov-cal-day.ov-today {
            background: var(--panel-bg) !important;
        }
        .ov-cal-day-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1px;
            line-height: 1;
        }
        .ov-cal-day-num {
            font-size: 11.5px;
            font-weight: 600;
            color: var(--text);
            width: 22px;
            height: 22px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            line-height: 1;
        }
        /* Selected Day: Gold filled circle behind date number matching mockup */
        .ov-cal-day.ov-selected-day .ov-cal-day-num {
            background: var(--accent, #C5A059) !important;
            color: #FFFFFF !important;
            font-weight: 700 !important;
        }
        /* Today (if not selected): Outline ring */
        .ov-cal-day.ov-today:not(.ov-selected-day) .ov-cal-day-num {
            border: 1.5px solid var(--accent, #C5A059) !important;
            color: var(--text) !important;
            font-weight: 700 !important;
        }
        .ov-cal-day-count-badge {
            font-size: 10px;
            font-weight: 700;
            padding: 1px 5px;
            border-radius: 6px;
            background: var(--bg2);
            color: var(--text2);
            border: 1px solid var(--border);
            line-height: 1.1;
        }
        .ov-cal-day-count-badge.has-conflict {
            background: rgba(214, 69, 69, 0.12);
            color: #D64545;
            border-color: rgba(214, 69, 69, 0.3);
        }
        .ov-cal-day-other-month .ov-cal-day-num,
        .ov-cal-day-past .ov-cal-day-num {
            color: var(--text2);
        }
        .ov-cal-day-other-month {
            opacity: 0.5;
        }

        /* ── Event Chips inside Calendar Day Cell (Matching Mockup) ──── */
        .ov-cal-events-list {
            display: flex;
            flex-direction: column;
            gap: 2px;
            width: 100%;
            min-width: 0;
            flex: 1;
            overflow: hidden;
        }
        .cal-chip {
            border-radius: 4px;
            padding: 2px 5px;
            cursor: pointer;
            display: flex;
            flex-direction: column;
            gap: 0px;
            box-sizing: border-box;
            transition: transform 0.1s ease, box-shadow 0.1s ease;
            border: 1px solid transparent;
            text-align: left;
            min-height: 22px;
            line-height: 1.2;
        }
        .cal-chip:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.06);
        }
        .cal-chip-header {
            display: flex;
            align-items: center;
            gap: 4px;
            min-width: 0;
        }
        .cal-chip-dot {
            width: 5px;
            height: 5px;
            border-radius: 50%;
            flex-shrink: 0;
        }
        .cal-chip-dot.green { background: #16a34a; }
        .cal-chip-dot.amber { background: var(--accent, #c5a059); }
        .cal-chip-dot.red   { background: #dc2626; }
        .cal-chip-dot.gray  { background: #71717a; }
        .cal-chip-title {
            font-size: 11px;
            font-weight: 600;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            line-height: 1.25;
            flex: 1;
        }
        .cal-chip-time {
            font-size: 10px;
            opacity: 0.85;
            font-weight: 500;
            padding-left: 9px;
            line-height: 1.15;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .cal-chip-confirmed {
            background: #eaf7ed;
            color: #15803d;
            border-color: rgba(22, 163, 74, 0.2);
        }
        .cal-chip-pending {
            background: rgba(197, 160, 89, 0.14);
            color: #8C6A18;
            border-color: rgba(197, 160, 89, 0.3);
        }
        .cal-chip-cancelled {
            background: #fef2f2;
            color: #991b1b;
            border-color: rgba(239, 68, 68, 0.2);
        }
        .cal-chip-completed {
            background: #f4f4f5;
            color: #52525b;
            border-color: rgba(113, 113, 122, 0.2);
        }

        [data-theme="dark"] .cal-chip-confirmed {
            background: rgba(34, 197, 94, 0.16);
            color: #4ade80;
            border-color: rgba(34, 197, 94, 0.35);
        }
        [data-theme="dark"] .cal-chip-pending {
            background: rgba(197, 160, 89, 0.16);
            color: #D8B56F;
            border-color: rgba(197, 160, 89, 0.4);
        }
        [data-theme="dark"] .cal-chip-cancelled {
            background: rgba(239, 68, 68, 0.16);
            color: #f87171;
            border-color: rgba(239, 68, 68, 0.35);
        }
        [data-theme="dark"] .cal-chip-completed {
            background: rgba(113, 113, 122, 0.16);
            color: #d4d4d8;
            border-color: rgba(113, 113, 122, 0.35);
        }

        .cal-day-overflow-pill {
            font-size: 10.5px;
            font-weight: 600;
            color: var(--accent);
            padding: 1px 6px;
            border-radius: 4px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 2px;
            margin-top: 1px;
            background: var(--bg2);
            border: 1px solid var(--border);
            transition: background 0.1s ease;
            width: fit-content;
        }
            color: var(--accent);
            padding: 1px 5px;
            border-radius: 4px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 2px;
            margin-top: 1px;
            transition: background 0.1s ease;
            width: fit-content;
        }
        .cal-day-overflow-pill:hover {
            background: var(--hover-bg);
            text-decoration: underline;
        }

        /* ── Right Column: Day Schedule & Time Slots (Matching Mockup) ── */
        .day-card-header {
            margin-bottom: 8px;
            flex-shrink: 0;
        }
        .day-card-date {
            font-family: var(--font-family-base, 'Inter', sans-serif);
            font-size: var(--font-size-lg, 17px);
            font-weight: 600;
            color: var(--text);
            margin: 0 0 1px 0;
            letter-spacing: -0.01em;
            line-height: 1.35;
        }
        .day-card-weekday {
            font-size: 12.5px;
            color: var(--text2);
            font-weight: 400;
            margin: 0;
            line-height: 1.4;
        }
        .day-card-content {
            display: flex;
            flex-direction: column;
            flex: 1;
            min-height: 0;
            overflow-y: auto;
            padding-right: 2px;
        }

        .day-section-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 6px;
            margin-top: 8px;
            flex-shrink: 0;
        }
        .day-section-head:first-child {
            margin-top: 0;
        }
        .day-section-title {
            font-size: 12.5px;
            font-weight: 700;
            color: var(--text);
            margin: 0;
        }
        .day-section-count {
            font-size: 11px;
            color: var(--text2);
            font-weight: 500;
        }
        .day-events-stack {
            display: flex;
            flex-direction: column;
            gap: 6px;
            margin-bottom: 6px;
        }
        .day-sched-card {
            background: var(--panel-bg);
            border: 1px solid var(--border);
            border-left: 4px solid #16a34a;
            border-radius: 10px;
            padding: 12px 14px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            cursor: pointer;
            transition: transform 0.15s ease, box-shadow 0.15s ease, border-color 0.15s ease;
        }
        .day-sched-card:hover {
            transform: translateX(2px);
            border-color: var(--accent, #c9a84c) !important;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.06);
        }
        .day-sched-card-left {
            display: flex;
            flex-direction: column;
            gap: 1px;
            min-width: 0;
        }
        .day-sched-time {
            font-size: 11.5px;
            color: var(--text2);
            font-weight: 600;
        }
        .day-sched-title-wrap {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .day-sched-dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            flex-shrink: 0;
        }
        .day-sched-title {
            font-size: 12.5px;
            font-weight: 700;
            color: var(--text);
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .day-sched-venue {
            font-size: 11.5px;
            color: var(--text2);
        }
        .day-sched-card-right {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-shrink: 0;
        }
        .day-sched-card.accent-green { border-left-color: #16a34a; }
        .day-sched-card.accent-amber { border-left-color: var(--accent, #c5a059); }
        .day-sched-card.accent-red   { border-left-color: #dc2626; }
        .day-sched-dot.green { background: #16a34a; }
        .day-sched-dot.amber { background: var(--accent, #c5a059); }
        .day-sched-dot.red   { background: #dc2626; }
        .day-sched-status-badge {
            font-size: 11px;
            font-weight: 600;
            padding: 2px 6px;
            border-radius: 4px;
            border: 1px solid transparent;
            text-transform: capitalize;
        }
        .day-sched-status-badge.status-confirmed {
            background: #eaf7ed;
            color: #15803d;
            border-color: rgba(22, 163, 74, 0.25);
        }
        .day-sched-status-badge.status-pending {
            background: rgba(197, 160, 89, 0.14);
            color: #8C6A18;
            border-color: rgba(197, 160, 89, 0.3);
        }
        .day-sched-status-badge.status-cancelled {
            background: #fef2f2;
            color: #991b1b;
            border-color: rgba(239, 68, 68, 0.25);
        }
        [data-theme="dark"] .day-sched-status-badge.status-confirmed {
            background: rgba(34, 197, 94, 0.16);
            color: #4ade80;
            border-color: rgba(34, 197, 94, 0.35);
        }
        [data-theme="dark"] .day-sched-status-badge.status-pending {
            background: rgba(197, 160, 89, 0.16);
            color: #D8B56F;
            border-color: rgba(197, 160, 89, 0.4);
        }
        [data-theme="dark"] .day-sched-status-badge.status-cancelled {
            background: rgba(239, 68, 68, 0.16);
            color: #f87171;
            border-color: rgba(239, 68, 68, 0.35);
        }
        .day-sched-arrow {
            font-size: 10px;
            color: var(--text2);
        }

        .day-empty-state {
            padding: 24px 16px;
            text-align: center;
            color: var(--text2);
            background: var(--bg2);
            border: 1px dashed var(--border);
            border-radius: 10px;
            margin-bottom: 8px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }
        .empty-state-icon-wrap {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: rgba(197, 160, 89, 0.12);
            color: var(--accent);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 15px;
            margin-bottom: 2px;
        }
        .empty-state-title {
            font-weight: 600;
            font-size: 13px;
            color: var(--text);
        }
        .empty-state-subtitle {
            font-size: 11.5px;
            color: var(--text2);
            max-width: 240px;
            line-height: 1.4;
        }
        .btn-empty-action {
            margin-top: 8px;
            background: transparent;
            color: var(--accent);
            border: 1px solid var(--accent);
            padding: 5px 14px;
            border-radius: 999px;
            font-size: 11.5px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: all 0.15s ease;
        }
        .btn-empty-action:hover {
            background: var(--accent);
            color: #111111;
        }

        .day-slots-stack {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .day-slot-card {
            background: var(--panel-bg);
            border: 1px solid var(--border);
            border-radius: 6px;
            padding: 7px 10px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
        }
        .day-slot-left {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .day-slot-icon {
            width: 28px;
            height: 28px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
        }
        .day-slot-icon.sun {
            background: rgba(197, 160, 89, 0.12);
            color: var(--accent, #C5A059);
        }
        .day-slot-icon.moon {
            background: rgba(197, 160, 89, 0.12);
            color: var(--accent, #C5A059);
        }
        .day-slot-name {
            font-size: 12px;
            font-weight: 700;
            color: var(--text);
        }
        .day-slot-hours {
            font-size: 11px;
            color: var(--text2);
        }
        .btn-book-slot-gold {
            border: 1px solid var(--accent, #C5A059);
            color: var(--accent, #C5A059);
            background: transparent;
            padding: 4px 10px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 11px;
            cursor: pointer;
            transition: all 0.15s ease;
        }
        .btn-book-slot-gold:hover {
            background: var(--accent, #C5A059);
            color: #FFFFFF;
        }

        /* ── Unified Modern Table Action Buttons ── */
        .tbl-action-btn {
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            width: 30px !important;
            height: 30px !important;
            min-width: 30px !important;
            min-height: 30px !important;
            padding: 0 !important;
            margin: 0 !important;
            border-radius: 7px !important;
            border: 1px solid var(--border, #e4e4e7) !important;
            background: var(--panel-bg, #ffffff) !important;
            color: var(--text2, #71717a) !important;
            font-size: 12px !important;
            line-height: 1 !important;
            cursor: pointer !important;
            transition: all 0.18s cubic-bezier(0.4, 0, 0.2, 1) !important;
            box-sizing: border-box !important;
            text-decoration: none !important;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04) !important;
        }
        .tbl-action-btn i {
            font-size: 12px !important;
            line-height: 1 !important;
            transition: transform 0.15s ease, color 0.15s ease !important;
        }
        .tbl-action-btn:hover {
            transform: translateY(-1px) !important;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.09) !important;
        }
        .tbl-action-btn.tbl-action-view:hover {
            background: var(--bg2, #f4f4f5) !important;
            color: var(--text, #18181b) !important;
            border-color: #a1a1aa !important;
        }
        .tbl-action-btn.tbl-action-edit:hover {
            background: rgba(197, 160, 89, 0.12) !important;
            color: var(--accent) !important;
            border-color: rgba(197, 160, 89, 0.4) !important;
        }
        .tbl-action-btn.tbl-action-archive:hover {
            background: rgba(100, 116, 139, 0.12) !important;
            color: #64748b !important;
            border-color: rgba(100, 116, 139, 0.4) !important;
        }
        .tbl-action-btn.tbl-action-delete {
            color: #dc2626 !important;
            background: rgba(239, 68, 68, 0.04) !important;
            border-color: rgba(239, 68, 68, 0.22) !important;
        }
        .tbl-action-btn.tbl-action-delete:hover {
            background: #ef4444 !important;
            color: #ffffff !important;
            border-color: #ef4444 !important;
            box-shadow: 0 2px 8px rgba(239, 68, 68, 0.3) !important;
        }
        .tbl-action-btn.tbl-action-restore {
            color: #059669 !important;
            background: rgba(16, 185, 129, 0.04) !important;
            border-color: rgba(16, 185, 129, 0.25) !important;
        }
        .tbl-action-btn.tbl-action-restore:hover {
            background: #10b981 !important;
            color: #ffffff !important;
            border-color: #10b981 !important;
            box-shadow: 0 2px 8px rgba(16, 185, 129, 0.3) !important;
        }

        /* Dark Mode Table Actions */
        [data-theme="dark"] .tbl-action-btn {
            background: #1e293b !important;
            border-color: rgba(255, 255, 255, 0.12) !important;
            color: #94a3b8 !important;
        }
        [data-theme="dark"] .tbl-action-btn.tbl-action-view:hover {
            background: #334155 !important;
            color: #ffffff !important;
            border-color: #64748b !important;
        }
        [data-theme="dark"] .tbl-action-btn.tbl-action-edit:hover {
            background: rgba(197, 160, 89, 0.18) !important;
            color: var(--accent, #c5a059) !important;
            border-color: rgba(197, 160, 89, 0.45) !important;
        }
        [data-theme="dark"] .tbl-action-btn.tbl-action-archive:hover {
            background: rgba(148, 163, 184, 0.18) !important;
            color: #cbd5e1 !important;
            border-color: rgba(148, 163, 184, 0.45) !important;
        }
        [data-theme="dark"] .tbl-action-btn.tbl-action-delete {
            background: rgba(239, 68, 68, 0.12) !important;
            color: #f87171 !important;
            border-color: rgba(239, 68, 68, 0.3) !important;
        }
        [data-theme="dark"] .tbl-action-btn.tbl-action-delete:hover {
            background: #dc2626 !important;
            color: #ffffff !important;
            border-color: #dc2626 !important;
        }
        [data-theme="dark"] .tbl-action-btn.tbl-action-restore {
            background: rgba(16, 185, 129, 0.12) !important;
            color: #34d399 !important;
            border-color: rgba(16, 185, 129, 0.3) !important;
        }
        [data-theme="dark"] .tbl-action-btn.tbl-action-restore:hover {
            background: #059669 !important;
            color: #ffffff !important;
            border-color: #059669 !important;
        }

        /* ── Segmented Subtabs Control (Active vs Archived) ── */
        .sched-subtabs-segmented {
            display: inline-flex;
            align-items: center;
            background: var(--bg2, #f4f4f5);
            border: 1px solid var(--border, #e4e4e7);
            border-radius: 8px;
            padding: 3px;
            gap: 3px;
        }
        .sched-subtab-btn {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 6px 14px;
            font-size: 12.5px;
            font-weight: 500;
            color: var(--text2, #71717a);
            text-decoration: none;
            border-radius: 6px;
            transition: all 0.18s ease;
            border: 1px solid transparent;
            cursor: pointer;
            line-height: 1.2;
            white-space: nowrap;
        }
        .sched-subtab-btn i {
            font-size: 12px;
            opacity: 0.85;
            transition: color 0.18s ease;
        }
        .sched-subtab-btn:hover {
            color: var(--text, #18181b);
            background: rgba(0, 0, 0, 0.03);
        }
        .sched-subtab-btn.active {
            background: var(--panel-bg, #ffffff) !important;
            color: var(--text, #18181b) !important;
            font-weight: 600;
            border-color: rgba(0, 0, 0, 0.06) !important;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
        }
        .sched-subtab-badge {
            font-size: 11px;
            font-weight: 600;
            padding: 1px 7px;
            border-radius: 999px;
            background: var(--bg3, #e4e4e7);
            color: inherit;
            transition: all 0.18s ease;
        }
        .sched-subtab-btn.active .sched-subtab-badge {
            background: var(--ink, #18181b);
            color: #ffffff;
        }
        [data-theme="dark"] .sched-subtabs-segmented {
            background: #18181b;
            border-color: rgba(255, 255, 255, 0.12);
        }
        [data-theme="dark"] .sched-subtab-btn {
            color: #a1a1aa;
        }
        [data-theme="dark"] .sched-subtab-btn:hover {
            color: #ffffff;
            background: rgba(255, 255, 255, 0.05);
        }
        [data-theme="dark"] .sched-subtab-btn.active {
            background: #27272a !important;
            color: #ffffff !important;
            border-color: rgba(255, 255, 255, 0.12) !important;
            box-shadow: 0 1px 4px rgba(0, 0, 0, 0.3);
        }
        [data-theme="dark"] .sched-subtab-btn.active .sched-subtab-badge {
            background: #3f3f46;
            color: #ffffff;
        }

        /* ── Search & Filter Controls ── */
        .filter-search-submit {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            border-radius: 6px;
            background: var(--ink, #18181b);
            color: #ffffff;
            border: 1px solid var(--ink, #18181b);
            cursor: pointer;
            transition: all 0.15s ease;
            font-size: 12px;
            flex-shrink: 0;
            padding: 0;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.08);
        }
        .filter-search-submit:hover {
            opacity: 0.9;
            transform: translateY(-1px);
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.16);
        }
        .filter-clear-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            border-radius: 6px;
            background: var(--bg2, #f4f4f5);
            color: var(--text2, #71717a);
            border: 1px solid var(--border, #e4e4e7);
            text-decoration: none;
            font-size: 12px;
            transition: all 0.15s ease;
            flex-shrink: 0;
        }
        .filter-clear-link:hover {
            background: rgba(239, 68, 68, 0.08);
            color: #dc2626;
            border-color: rgba(239, 68, 68, 0.3);
            transform: translateY(-1px);
        }
        [data-theme="dark"] .filter-search-submit {
            background: #27272a;
            border-color: #3f3f46;
            color: #ffffff;
        }
        [data-theme="dark"] .filter-clear-link {
            background: #1e293b;
            border-color: rgba(255, 255, 255, 0.12);
            color: #94a3b8;
        }

        /* ── Modern + New Event Button ── */
        .btn-new-event-gold {
            background: #111111 !important;
            color: #ffffff !important;
            border: 1px solid #111111 !important;
            border-radius: 999px !important;
            padding: 7px 18px !important;
            font-size: 13px !important;
            font-weight: 600 !important;
            display: inline-flex !important;
            align-items: center !important;
            gap: 7px !important;
            cursor: pointer !important;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.14) !important;
            transition: all 0.18s ease !important;
            text-decoration: none !important;
            min-height: 34px !important;
            box-sizing: border-box !important;
        }
        .btn-new-event-gold i {
            color: var(--accent, #c5a059) !important;
            font-size: 12px !important;
            transition: transform 0.2s ease !important;
        }
        .btn-new-event-gold:hover {
            background: #27272a !important;
            border-color: #27272a !important;
            transform: translateY(-1px) !important;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.22) !important;
        }
        .btn-new-event-gold:hover i {
            transform: rotate(90deg) !important;
        }
        [data-theme="dark"] .btn-new-event-gold {
            background: var(--accent, #c5a059) !important;
            color: #0b0f19 !important;
            border-color: var(--accent, #c5a059) !important;
        }
        [data-theme="dark"] .btn-new-event-gold i {
            color: #0b0f19 !important;
        }
        [data-theme="dark"] .btn-new-event-gold:hover {
            background: var(--accent-hover, #d8b56f) !important;
            border-color: var(--accent-hover, #d8b56f) !important;
            box-shadow: 0 4px 14px rgba(197, 160, 89, 0.35) !important;
        }

        /* ── MOBILE CALENDAR COMPLETE RESPONSIVE ENGINE (<= 768px) ── */
        .ov-cal-day-hdr .day-hdr-short {
            display: none;
        }
        .ov-cal-day-hdr .day-hdr-full {
            display: inline;
        }

        @media (max-width: 768px) {
            #sec-events {
                padding: 6px 4px 18px !important;
                gap: 8px !important;
            }
            .sched-workspace-grid {
                grid-template-columns: 1fr !important;
                gap: 10px !important;
            }
            .sched-cal-card,
            .sched-slots-card {
                padding: 10px 8px !important;
                border-radius: 10px !important;
            }

            /* Header section: title and badges */
            .cal-view-header {
                flex-direction: column !important;
                align-items: flex-start !important;
                gap: 8px !important;
                padding-bottom: 8px !important;
            }
            .cal-view-header > div:first-child {
                display: flex !important;
                align-items: center !important;
                gap: 10px !important;
                width: 100% !important;
                min-width: 0 !important;
            }
            .cal-view-header > div:first-child > div:last-child {
                flex: 1 !important;
                min-width: 0 !important;
            }
            .cal-main-title {
                font-size: 1.18rem !important;
                line-height: 1.25 !important;
                word-break: break-word !important;
                white-space: normal !important;
            }
            .cal-main-subtitle {
                font-size: 11px !important;
                line-height: 1.2 !important;
            }

            /* Month Navigation & Controls Bar */
            .sched-cal-header-bar {
                flex-direction: column !important;
                align-items: stretch !important;
                gap: 8px !important;
                margin-bottom: 8px !important;
            }
            .sched-month-nav {
                display: flex !important;
                align-items: center !important;
                justify-content: space-between !important;
                width: 100% !important;
                gap: 4px !important;
            }
            .cal-month-heading {
                font-size: 1rem !important;
                min-width: 0 !important;
                flex: 1 !important;
                text-align: center !important;
                white-space: nowrap !important;
                overflow: hidden !important;
                text-overflow: ellipsis !important;
            }
            .cal-nav-btn {
                width: 30px !important;
                height: 30px !important;
                flex-shrink: 0 !important;
            }
            .cal-today-btn {
                height: 30px !important;
                padding: 0 8px !important;
                font-size: 11px !important;
                flex-shrink: 0 !important;
            }
            .sched-cal-controls {
                display: flex !important;
                align-items: center !important;
                justify-content: space-between !important;
                width: 100% !important;
                gap: 6px !important;
                flex-wrap: nowrap !important;
            }
            .cal-venue-dropdown-wrap {
                flex: 1 1 auto !important;
                min-width: 0 !important;
                max-width: 140px !important;
            }
            .cal-venue-select-styled {
                width: 100% !important;
                max-width: 100% !important;
                font-size: 11px !important;
                padding: 0 20px 0 6px !important;
            }
            .sched-legend-dots {
                font-size: 9.5px !important;
                gap: 5px !important;
                white-space: nowrap !important;
                flex-shrink: 0 !important;
            }

            /* ── The 7-Day Grid on Mobile ── */
            .sched-grid-wrap {
                width: 100% !important;
                overflow: hidden !important;
            }
            .cal-grid-modern {
                grid-template-columns: repeat(7, minmax(0, 1fr)) !important;
                width: 100% !important;
                border-radius: 8px !important;
                overflow: hidden !important;
            }
            .ov-cal-day-hdr {
                height: 22px !important;
                font-size: 10.5px !important;
                letter-spacing: 0 !important;
            }
            .ov-cal-day-hdr .day-hdr-full {
                display: none !important;
            }
            .ov-cal-day-hdr .day-hdr-short {
                display: inline !important;
                font-weight: 700 !important;
            }

            /* Day Cells */
            .ov-cal-day {
                min-height: 44px !important;
                min-width: 0 !important;
                padding: 3px 1px 4px !important;
                align-items: center !important;
                box-sizing: border-box !important;
                gap: 2px !important;
            }
            .ov-cal-day-top {
                width: 100% !important;
                justify-content: center !important;
                margin-bottom: 0 !important;
            }
            .ov-cal-day-num {
                width: 20px !important;
                height: 20px !important;
                font-size: 11px !important;
                font-weight: 600 !important;
            }
            /* Hide the text badge "+1" inside cell on mobile to prevent squishing */
            .ov-cal-day-count-badge {
                display: none !important;
            }

            /* Events List as Sleek Indicator Dots */
            .ov-cal-events-list {
                flex-direction: row !important;
                justify-content: center !important;
                align-items: center !important;
                gap: 2.5px !important;
                margin-top: 1px !important;
                width: 100% !important;
                min-width: 0 !important;
                flex-wrap: wrap !important;
                max-height: 16px !important;
                overflow: hidden !important;
            }
            .cal-chip {
                padding: 0 !important;
                margin: 0 !important;
                border: none !important;
                background: transparent !important;
                box-shadow: none !important;
                width: auto !important;
                min-width: 0 !important;
                height: auto !important;
            }
            .cal-chip-header {
                gap: 0 !important;
                min-width: 0 !important;
            }
            .cal-chip-title,
            .cal-chip-time {
                display: none !important; /* Hide clipped text inside 40px monthly cell */
            }
            .cal-chip-dot {
                width: 5px !important;
                height: 5px !important;
                border-radius: 50% !important;
                box-shadow: 0 0 2px rgba(0, 0, 0, 0.3) !important;
            }
            .cal-day-overflow-pill {
                display: none !important;
            }

            /* Day Schedule & Slots Card (Below calendar on mobile) */
            .sched-slots-card {
                margin-top: 2px !important;
            }
            .day-card-header {
                margin-bottom: 6px !important;
            }
            .day-card-date {
                font-size: 1.15rem !important;
            }

            /* Segmented Tabs on Mobile */
            .sched-subnav-bar {
                margin-bottom: 6px !important;
            }
            .sched-tabs-group {
                width: 100% !important;
                display: flex !important;
            }
            .sched-tab-link {
                flex: 1 !important;
                justify-content: center !important;
                padding: 6px 12px !important;
                font-size: 12px !important;
            }
        }
    </style>

    <!-- ─── 1. TOP HEADER BAR: TITLE + STAT BADGES (PINNED TOP-RIGHT) ─── -->
    <div class="cal-view-header page-header-bar" style="display: flex; justify-content: space-between; align-items: center; padding-bottom: 8px; margin-bottom: 6px; border-bottom: 1px solid var(--border); flex-wrap: wrap; gap: 10px;">
        <!-- Left Side: Icon + Title + Subtitle -->
        <div style="display: flex; align-items: center; gap: 14px;">
            <div class="page-header-icon" style="width: 38px; height: 38px; border-radius: 9px; background: rgba(201,168,76,0.14); color: var(--accent); display: flex; align-items: center; justify-content: center; font-size: 17px; flex-shrink: 0;">
                <i class="fa-solid fa-calendar-days"></i>
            </div>
            <div>
                <h1 class="cal-main-title page-title" style="font-family: var(--font-family, 'Inter', sans-serif); font-size: var(--font-page-title, 20px); font-weight: 700; color: var(--text); margin: 0; line-height: 1.2;">
                    Calendar
                </h1>
                <p class="cal-main-subtitle page-subtitle" style="font-size: var(--font-page-subtitle, 14px); color: var(--text2); margin: 3px 0 0 0;">
                    Liturgical events, celebrations, and chapel schedules
                </p>
            </div>
        </div>

        <!-- Right Side: Stat Badges Pinned Top-Right -->
        <div style="display: flex; align-items: center; gap: 6px;">
            <span class="badge" style="background: var(--bg3, #F4F4F5); border: 1px solid var(--border); color: var(--text2); font-size: 11px; font-weight: 600; padding: 3px 11px; border-radius: 999px;">
                <?php echo (int)$stTotal; ?> Events
            </span>
            <?php if ($stPending > 0): ?>
            <span class="badge badge-warning" style="font-size: 11px; font-weight: 600; padding: 3px 10px; border-radius: 999px;" title="Events Awaiting Approval">
                <?php echo (int)$stPending; ?> Pending
            </span>
            <?php endif; ?>
        </div>
    </div>

    <!-- ─── 2. SUBNAV ROW: VIEW SWITCHER TABS LEFT + NEW EVENT RIGHT ─── -->
    <div class="cal-subnav-bar" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 6px; margin-bottom: 4px;">
        <!-- Left: View Switcher (Calendar / Table List) -->
        <div class="sched-tabs-group" style="display: inline-flex; align-items: center; gap: 3px; background: var(--bg3, #F4F4F5); padding: 2px; border-radius: 999px; border: 1px solid var(--border);">
            <button type="button" id="btn-toggle-cal" onclick="setScheduleMode('cal')" class="sched-tab-link active" style="min-height: 27px; height: 27px; padding: 3px 12px; font-size: 11.5px; border-radius: 999px;">
                <i class="fa-solid fa-calendar"></i> Calendar
            </button>
            <button type="button" id="btn-toggle-table" onclick="setScheduleMode('table')" class="sched-tab-link" style="min-height: 27px; height: 27px; padding: 3px 12px; font-size: 11.5px; border-radius: 999px;">
                <i class="fa-solid fa-table-list"></i> Table List
            </button>
        </div>

        <!-- Right: Primary Action Button -->
        <div style="display: flex; align-items: center; gap: 6px;">
            <?php if ($canCreateEvent): ?>
                <button type="button" class="btn-new-event-gold" onclick="openModal('modal-add-event')" style="height: 28px; min-height: 28px; font-size: 11.5px; padding: 3px 13px; border-radius: 999px; background: #111111; color: #FFFFFF; border: 1px solid #111111; box-shadow: 0 1px 3px rgba(0,0,0,0.12); display: inline-flex; align-items: center; gap: 5px; font-weight: 600; cursor: pointer;">
                    <i class="fa-solid fa-plus text-gold" style="font-size: 10.5px;"></i> New Event
                </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- ─── CALENDAR + DAILY SCHEDULE WORKSPACE ─── -->
    <div id="sched-view-cal" class="sched-workspace-grid">

        <!-- LEFT: Monthly Interactive Calendar -->
        <div class="sched-cal-card" id="sched-cal-card">
            <div class="sched-cal-header-bar">
                <!-- Navigation (Left) -->
                <div class="sched-month-nav">
                    <button type="button" class="cal-nav-btn" onclick="calPrev()" title="Previous Month">
                        <i class="fa-solid fa-chevron-left"></i>
                    </button>
                    <div id="cal-month-label" class="cal-month-heading">October 2026</div>
                    <button type="button" class="cal-nav-btn" onclick="calNext()" title="Next Month">
                        <i class="fa-solid fa-chevron-right"></i>
                    </button>
                </div>

                <!-- Controls & Legend (Right) -->
                <div class="sched-cal-controls" style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                    <!-- Venue Selector Dropdown -->
                    <?php if ($isScopedToChapel): ?>
                        <div style="position: relative; display: inline-flex; align-items: center;">
                            <select id="cal-venue-select" class="cal-venue-select-styled" disabled style="padding-right: 28px !important;">
                                <?php foreach (($chapels ?? []) as $ch): ?>
                                <option value="<?php echo $ch['chapel_id']; ?>" selected><?php echo htmlspecialchars($ch['chapel_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <i class="fa-solid fa-lock cal-venue-lock-icon"></i>
                        </div>
                    <?php elseif ($isCathedralStaff): ?>
                        <div style="position: relative; display: inline-flex; align-items: center;">
                            <select id="cal-venue-select" class="cal-venue-select-styled" disabled style="padding-right: 28px !important;">
                                <option value="cathedral" selected>Main Cathedral</option>
                            </select>
                            <i class="fa-solid fa-lock cal-venue-lock-icon"></i>
                        </div>
                    <?php else: ?>
                        <select id="cal-venue-select" class="cal-venue-select-styled" onchange="selectChapelFilter(this.value)">
                            <option value="all">All Venues</option>
                            <option value="cathedral">Main Cathedral</option>
                            <?php foreach (($chapels ?? []) as $ch): ?>
                            <option value="<?php echo $ch['chapel_id']; ?>"><?php echo htmlspecialchars($ch['chapel_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    <?php endif; ?>

                    <!-- Event Type Filter -->
                    <select id="cal-type-filter" class="cal-venue-select-styled" onchange="selectTypeFilter(this.value)" title="Filter by Event / Rite Type">
                        <option value="all">All Rites</option>
                        <option value="Mass">Mass</option>
                        <option value="Wedding">Wedding</option>
                        <option value="Baptism">Baptism</option>
                        <option value="Funeral">Funeral</option>
                        <option value="Confirmation">Confirmation</option>
                        <option value="Fiesta">Fiesta</option>
                        <option value="Other">Other</option>
                    </select>

                    <!-- Timeframe / Week Filter -->
                    <select id="cal-timeframe-filter" class="cal-venue-select-styled" onchange="selectTimeframeFilter(this.value)" title="Filter by Timeframe / Week">
                        <option value="all">All Dates</option>
                        <option value="this_week">This Week</option>
                        <option value="next_week">Next Week</option>
                        <option value="this_month">This Month</option>
                        <option value="upcoming">Upcoming</option>
                        <option value="past">Past</option>
                    </select>

                    <!-- Status Legend -->
                    <div class="sched-legend-dots">
                        <span class="legend-dot-item"><span class="legend-dot green"></span> Confirmed</span>
                        <span class="legend-dot-item"><span class="legend-dot amber"></span> Pending</span>
                        <span class="legend-dot-item"><span class="legend-dot red"></span> Cancelled</span>
                    </div>
                </div>
            </div>

            <div class="sched-grid-wrap">
                <div id="cal-grid" class="cal-grid-modern"></div>
            </div>
        </div>

        <!-- RIGHT: Daily Schedule & Available Time Slots -->
        <div class="sched-slots-card" id="sched-day-detail-card">
            <!-- Day Detail Heading -->
            <div class="day-card-header" style="margin-bottom: 8px;">
                <h2 id="day-detail-date-title" class="day-card-date" style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                    <span><?php echo date('l, M j'); ?></span>
                </h2>
                <div id="day-detail-day-subtitle" class="day-card-weekday">Daily Schedule &amp; Available Slots</div>
            </div>

            <input type="hidden" id="timeline-date" value="<?php echo date('Y-m-d'); ?>">

            <!-- Body (Today's Schedule & Available Slots) -->
            <div id="timeline-container" class="day-card-content"></div>
        </div>
    </div>

    <!-- ─── MASTER SCHEDULE TABLE CARD (TOGGLEABLE) ─── -->
    <div id="sched-view-table" style="display: none;">
        <div class="card" style="padding: 0; overflow: hidden; border: 1px solid var(--border); border-radius: 8px;">
            <div class="card-title" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; padding: 10px 14px; background: var(--bg3); border-bottom: 1px solid var(--border);">
                <!-- Modern Segmented Subtabs -->
                <div class="sched-subtabs-segmented">
                    <a href="dashboard.php?section=events&amp;sched_mode=table" 
                       class="sched-subtab-btn <?php echo ($ev_tab !== 'archived') ? 'active' : ''; ?>">
                        <i class="fa-solid fa-calendar-check"></i>
                        <span>Active Liturgies</span>
                        <span class="sched-subtab-badge"><?php echo (int)($event_stats['total'] ?? $stTotal); ?></span>
                    </a>
                    <a href="dashboard.php?section=events&amp;ev_tab=archived&amp;sched_mode=table" 
                       class="sched-subtab-btn <?php echo ($ev_tab === 'archived') ? 'active' : ''; ?>">
                        <i class="fa-solid fa-box-archive"></i>
                        <span>Archived History</span>
                        <span class="sched-subtab-badge"><?php echo (int)($event_stats['archived'] ?? 0); ?></span>
                    </a>
                </div>

                <!-- Integrated Filter Toolbar -->
                <form method="GET" style="display: flex; gap: 6px; align-items: center; flex-wrap: wrap; margin: 0;">
                    <input type="hidden" name="section" value="events">
                    <input type="hidden" name="ev_tab" value="<?php echo htmlspecialchars($ev_tab); ?>">
                    <input type="hidden" name="sched_mode" value="table">

                    <?php if ($isScopedToChapel): ?>
                        <select name="ev_venue" id="table-venue-select" style="padding: 5px 8px; font-size: 12.5px; height: 32px; width: 145px; border-radius: 6px; background: var(--bg2); border: 1px solid var(--border); color: var(--text); opacity: 0.95;" disabled>
                            <?php foreach (($chapels ?? []) as $ch): ?>
                            <option value="<?php echo $ch['chapel_id']; ?>" selected><?php echo htmlspecialchars($ch['chapel_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    <?php elseif ($isCathedralStaff): ?>
                        <select name="ev_venue" id="table-venue-select" style="padding: 5px 8px; font-size: 12.5px; height: 32px; width: 145px; border-radius: 6px; background: var(--bg2); border: 1px solid var(--border); color: var(--text); opacity: 0.95;" disabled>
                            <option value="cathedral" selected>Main Cathedral</option>
                        </select>
                    <?php else: ?>
                        <select name="ev_venue" id="table-venue-select" style="padding: 5px 8px; font-size: 12.5px; height: 32px; width: 135px; border-radius: 6px; background: var(--bg2); border: 1px solid var(--border); color: var(--text);" onchange="selectChapelFilter(this.value); this.form.submit();">
                            <option value="all">All Venues</option>
                            <option value="cathedral" <?php echo (($ev_filters['venue'] ?? '') === 'cathedral' || (isset($_GET['chapel_id']) && $_GET['chapel_id'] === 'cathedral')) ? 'selected' : ''; ?>>Main Cathedral</option>
                            <?php foreach (($chapels ?? []) as $ch): ?>
                            <option value="<?php echo $ch['chapel_id']; ?>" <?php echo ((string)($ev_filters['venue'] ?? ($ev_filters['chapel_id'] ?? ($_GET['chapel_id'] ?? ''))) === (string)$ch['chapel_id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($ch['chapel_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    <?php endif; ?>

                    <div style="position: relative; display: inline-flex; align-items: center;">
                        <i class="fa-solid fa-magnifying-glass" style="position: absolute; left: 11px; font-size: 11px; color: var(--text2); pointer-events: none; z-index: 2;"></i>
                        <input type="text" name="ev_search" data-ac-mode="server" data-ac-scope="calendar_events" placeholder="Search events..." 
                               style="padding: 5px 10px 5px 32px !important; font-size: 12.5px; height: 32px; width: 180px; border-radius: 6px; background: var(--bg2); border: 1px solid var(--border); color: var(--text); box-sizing: border-box;" 
                               value="<?php echo htmlspecialchars($ev_filters['search'] ?? ''); ?>">
                    </div>

                    <!-- Timeframe Filter Dropdown -->
                    <select name="ev_timeframe" style="height: 32px !important; min-width: 110px; max-width: 140px; font-size: 12px; border-radius: 6px; background: var(--bg2); border: 1px solid var(--border); color: var(--text);" onchange="this.form.submit()" title="Filter by timeframe">
                        <option value="">All Dates</option>
                        <option value="this_week" <?php echo (($ev_filters['timeframe'] ?? ($_GET['ev_timeframe'] ?? '')) === 'this_week') ? 'selected' : ''; ?>>This Week</option>
                        <option value="next_week" <?php echo (($ev_filters['timeframe'] ?? ($_GET['ev_timeframe'] ?? '')) === 'next_week') ? 'selected' : ''; ?>>Next Week</option>
                        <option value="this_month" <?php echo (($ev_filters['timeframe'] ?? ($_GET['ev_timeframe'] ?? '')) === 'this_month') ? 'selected' : ''; ?>>This Month</option>
                        <option value="upcoming" <?php echo (($ev_filters['timeframe'] ?? ($_GET['ev_timeframe'] ?? '')) === 'upcoming') ? 'selected' : ''; ?>>Upcoming</option>
                        <option value="past" <?php echo (($ev_filters['timeframe'] ?? ($_GET['ev_timeframe'] ?? '')) === 'past') ? 'selected' : ''; ?>>Past</option>
                    </select>

                    <!-- Selective Pastors Filter Dropdown -->
                    <select name="ev_pastor_id" style="height: 32px !important; min-width: 120px; max-width: 160px; font-size: 12px; border-radius: 6px; background: var(--bg2); border: 1px solid var(--border); color: var(--text);" onchange="this.form.submit()" title="Filter by selective pastor">
                        <option value="">All Pastors</option>
                        <?php foreach (($priests ?? []) as $pr): 
                            $pId = (int)$pr['id'];
                            $pName = !empty($pr['full_name']) ? $pr['full_name'] : $pr['user'];
                            if (!preg_match('/^(Fr\.|Father|Rev\.|Pastor|Msgr\.)/i', $pName)) {
                                $pName = 'Fr. ' . $pName;
                            }
                            $curPastorEv = (string)($ev_filters['pastor_id'] ?? ($_GET['ev_pastor_id'] ?? ''));
                        ?>
                        <option value="<?php echo $pId; ?>" <?php echo ($curPastorEv === (string)$pId) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($pName); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>

                    <select name="ev_type" style="height: 32px !important; min-width: 120px; max-width: 180px; font-size: 12px; border-radius: 6px; background: var(--bg2); border: 1px solid var(--border); color: var(--text);" onchange="this.form.submit()">
                        <?php echo render_event_type_options($ev_filters['event_type'] ?? '', 'All Types'); ?>
                    </select>

                    <!-- Sort Toggle Icon (Up / Down) -->
                    <?php $curEvSort = $ev_filters['sort'] ?? ($_GET['ev_sort'] ?? 'date_desc'); ?>
                    <input type="hidden" name="ev_sort" id="sort-calendar-table" value="<?php echo htmlspecialchars($curEvSort); ?>">
                    <button type="button" class="btn-sort-toggle" onclick="toggleSortDirection(this, 'sort-calendar-table')"
                            title="<?php echo ($curEvSort === 'date_asc') ? 'Sorted: Oldest First (Click to sort Newest first)' : 'Sorted: Newest First (Click to sort Oldest first)'; ?>"
                            style="height: 32px; width: 34px; min-width: 34px; padding: 0; display: inline-flex; align-items: center; justify-content: center; border-radius: 6px; border: 1px solid var(--border); background: var(--bg2); color: var(--text); cursor: pointer; transition: all 0.15s ease; flex-shrink: 0;" aria-label="Toggle sort order">
                        <i class="fa-solid <?php echo ($curEvSort === 'date_asc') ? 'fa-arrow-up-wide-short' : 'fa-arrow-down-wide-short'; ?>" style="font-size: 13px;"></i>
                    </button>

                    <button type="submit" class="filter-search-submit" title="Search"><i class="fa-solid fa-magnifying-glass"></i></button>

                    <?php if (!empty($ev_filters['search']) || !empty($ev_filters['event_type']) || !empty($ev_filters['venue']) || !empty($ev_filters['timeframe']) || !empty($ev_filters['pastor_id']) || ($ev_filters['sort'] ?? 'date_desc') !== 'date_desc'): ?>
                        <a href="dashboard.php?section=events<?php echo ($ev_tab === 'archived') ? '&amp;ev_tab=archived' : ''; ?>&amp;sched_mode=table" class="filter-clear-link" title="Clear Filters"><i class="fa-solid fa-xmark"></i></a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Table Listing -->
            <div class="table-wrap" style="overflow-x: auto; max-height: 520px; overflow-y: auto;">
                <table style="width: 100%; min-width: 720px; border-collapse: collapse;">
                    <thead style="position: sticky; top: 0; background: var(--panel-header-bg, var(--bg3)); z-index: 5;">
                        <tr style="border-bottom: 1px solid var(--border);">
                            <th style="width: 140px; padding: 10px 14px; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em;">Schedule</th>
                            <th style="padding: 10px 14px; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em;">Event &amp; Liturgy</th>
                            <th style="width: 220px; padding: 10px 14px; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em;">Celebrant &amp; Venue</th>
                            <th style="width: 100px; text-align: center; padding: 10px 12px; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em;">Status</th>
                            <th style="width: 120px; text-align: right; padding: 10px 14px; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em;">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="events-master-tbody">
                    <tr id="events-filter-empty-row" style="display: none;">
                        <td colspan="5" style="text-align: center; color: var(--text2); padding: 30px 16px;">
                            <i class="fa-solid fa-filter-circle-xmark" style="font-size: 26px; opacity: 0.35; margin-bottom: 6px; display: block;"></i>
                            <div style="font-size: 13px; font-weight: 700; color: var(--text);">No events for this chapel.</div>
                            <div style="font-size: 11.5px; margin-top: 2px;">Select "All Venues" to view parish-wide schedules.</div>
                        </td>
                    </tr>
                    <?php if (empty($events)): ?>
                        <tr>
                            <td colspan="5" style="text-align: center; color: var(--text2); padding: 30px 16px;">
                                <i class="fa-solid fa-calendar-xmark" style="font-size: 28px; opacity: 0.3; margin-bottom: 6px; display: block;"></i>
                                <div style="font-size: 13px; font-weight: 700; color: var(--text);">No events found.</div>
                                <div style="font-size: 11.5px; margin-top: 2px;">
                                    <?php echo ($ev_tab === 'archived') ? 'No archived schedules in this view.' : 'Try adjusting your filters or create a new event.'; ?>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php 
                        foreach ($events as $idx => $ev):
                            $ev_status = $ev['status'] ?? 'Pending';
                            $statusBadgeClass = 'badge-' . strtolower($ev_status);
                            $typeColor = $typeColorsMap[$ev['event_type'] ?? 'Other'] ?? 'var(--accent, #c5a059)';
                            $evJson = htmlspecialchars(json_encode($ev), ENT_QUOTES, 'UTF-8');
                            $isArchived = ($ev_tab === 'archived');
                        ?>
                        <tr style="<?php echo $isArchived ? 'opacity: 0.7;' : ''; ?> border-bottom: 1px solid var(--border); transition: background 0.15s ease;" data-chapel-id="<?php echo htmlspecialchars($ev['chapel_id'] ?? ''); ?>">
                            <!-- Column 1: Schedule -->
                            <td style="padding: 10px 14px; vertical-align: middle;">
                                <div style="font-weight: 700; font-size: 13px; color: var(--text); line-height: 1.2;">
                                    <?php echo date('M d, Y', strtotime($ev['start_time'])); ?>
                                </div>
                                <div style="font-size: 12px; color: var(--text2); margin-top: 2px; line-height: 1.2;">
                                    <?php echo date('h:i A', strtotime($ev['start_time'])); ?>
                                </div>
                            </td>

                            <!-- Column 2: Event & Sacrament Type -->
                            <td style="padding: 10px 14px; vertical-align: middle;">
                                <div onclick="viewEventDetails(<?php echo $evJson; ?>)" style="cursor: pointer; font-weight: 700; font-size: 14px; color: var(--text); line-height: 1.3;" title="Click to view details">
                                    <?php echo htmlspecialchars($ev['title']); ?>
                                </div>
                                <div style="display: inline-flex; align-items: center; gap: 5px; font-size: 12px; font-weight: 600; color: var(--text2); margin-top: 2px;">
                                    <span style="display: inline-block; width: 7px; height: 7px; border-radius: 50%; background: <?php echo $typeColor; ?>;"></span>
                                    <span><?php echo htmlspecialchars($ev['event_type']); ?></span>
                                </div>
                            </td>

                            <!-- Column 3: Celebrant & Venue -->
                            <td style="padding: 10px 14px; vertical-align: middle;">
                                <div style="font-size: 13px; font-weight: 600; color: var(--text); line-height: 1.2;">
                                    <?php echo htmlspecialchars($ev['pastor_assigned'] ?: 'Unassigned Celebrant'); ?>
                                </div>
                                <div style="font-size: 12px; color: var(--text2); margin-top: 2px; line-height: 1.2;">
                                    <i class="fa-solid fa-church" style="font-size: 11px; opacity: 0.65; margin-right: 3px;"></i><?php echo htmlspecialchars($ev['chapel_location'] ?: 'Main Cathedral'); ?>
                                </div>
                            </td>

                            <!-- Column 4: Status -->
                            <td style="text-align: center; padding: 10px 12px; vertical-align: middle;">
                                <span class="badge <?php echo $statusBadgeClass; ?>" style="font-size: 11.5px; padding: 3px 8px; text-transform: uppercase; letter-spacing: 0.02em;">
                                    <?php echo htmlspecialchars($ev_status); ?>
                                </span>
                            </td>

                            <!-- Column 5: Actions -->
                            <td style="text-align: right; padding: 10px 14px; vertical-align: middle;">
                                <div style="display: inline-flex; gap: 5px; align-items: center; justify-content: flex-end;">
                                    <!-- View Details Action -->
                                    <button type="button" class="tbl-action-btn tbl-action-view" onclick="viewEventDetails(<?php echo $evJson; ?>)" title="View Details">
                                        <i class="fa-regular fa-eye"></i>
                                    </button>

                                    <?php if ($isArchived): ?>
                                        <!-- Restore Action -->
                                        <form method="POST" style="margin: 0; padding: 0; display: inline-flex;" onsubmit="return confirm('Restore this event to the active schedule?')">
                                            <input type="hidden" name="form_section" value="events">
                                            <input type="hidden" name="action" value="restore_event">
                                            <input type="hidden" name="id" value="<?php echo $ev['event_id']; ?>">
                                            <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
                                            <input type="hidden" name="return_section" value="events&ev_tab=archived&sched_mode=table">
                                            <button type="submit" class="tbl-action-btn tbl-action-restore" title="Restore Event">
                                                <i class="fa-solid fa-arrow-rotate-left"></i>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <!-- Edit Action -->
                                        <?php if ($canEditEvents): ?>
                                            <button type="button" class="tbl-action-btn tbl-action-edit" onclick="openEditEventModal(<?php echo $evJson; ?>)" title="Edit Event">
                                                <i class="fa-solid fa-pen-to-square"></i>
                                            </button>
                                        <?php endif; ?>

                                        <!-- Archive or Delete Action -->
                                        <?php if ($canEditEvents && in_array($ev_status, ['Confirmed', 'Completed'])): ?>
                                            <form method="POST" style="margin: 0; padding: 0; display: inline-flex;" onsubmit="return confirm('Archive this event?')">
                                                <input type="hidden" name="form_section" value="events">
                                                <input type="hidden" name="action" value="archive_event">
                                                <input type="hidden" name="id" value="<?php echo $ev['event_id']; ?>">
                                                <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
                                                <input type="hidden" name="return_section" value="events&sched_mode=table">
                                                <button type="submit" class="tbl-action-btn tbl-action-archive" title="Archive Event">
                                                    <i class="fa-solid fa-box-archive"></i>
                                                </button>
                                            </form>
                                        <?php elseif ($canEditEvents): ?>
                                            <form method="POST" style="margin: 0; padding: 0; display: inline-flex;" onsubmit="return confirm('Delete this event?')">
                                                <input type="hidden" name="form_section" value="events">
                                                <input type="hidden" name="action" value="delete_event">
                                                <input type="hidden" name="id" value="<?php echo $ev['event_id']; ?>">
                                                <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
                                                <input type="hidden" name="return_section" value="events&sched_mode=table">
                                                <button type="submit" class="tbl-action-btn tbl-action-delete" title="Delete Event">
                                                    <i class="fa-regular fa-trash-can"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination Controls -->
            <?php if ($total_pages > 1): ?>
            <div style="display: flex; justify-content: center; align-items: center; gap: 4px; padding: 8px 12px; background: var(--bg3); border-top: 1px solid var(--border);">
                <?php 
                $paginationParams = ['section' => 'events', 'sched_mode' => 'table'];
                if ($ev_tab === 'archived') $paginationParams['ev_tab'] = 'archived';
                if (!empty($ev_filters['search'])) $paginationParams['ev_search'] = $ev_filters['search'];
                if (!empty($ev_filters['event_type'])) $paginationParams['ev_type'] = $ev_filters['event_type'];
                if (!empty($ev_filters['timeframe'])) $paginationParams['ev_timeframe'] = $ev_filters['timeframe'];
                if (!empty($ev_filters['pastor_id'])) $paginationParams['ev_pastor_id'] = $ev_filters['pastor_id'];
                if (!empty($ev_filters['venue']) && $ev_filters['venue'] !== 'all') $paginationParams['ev_venue'] = $ev_filters['venue'];
                if (!empty($ev_filters['sort']) && $ev_filters['sort'] !== 'date_desc') $paginationParams['ev_sort'] = $ev_filters['sort'];
                $qStr = http_build_query($paginationParams, '', '&amp;');
                $cp = max(1, (int)$current_page);
                $tp = max(1, (int)$total_pages);
                ?>

                <?php if ($cp > 1): ?>
                    <a href="dashboard.php?<?php echo $qStr; ?>&amp;ev_page=<?php echo $cp - 1; ?>" class="btn btn-ghost btn-sm" style="padding: 4px 8px; font-size: 12px; height: 28px; border-radius: 5px;">
                        <i class="fa-solid fa-arrow-left"></i>
                    </a>
                <?php endif; ?>

                <?php
                $startPage = max(1, $cp - 2);
                $endPage   = min($tp, $cp + 2);
                for ($i = $startPage; $i <= $endPage; $i++):
                ?>
                    <a href="dashboard.php?<?php echo $qStr; ?>&amp;ev_page=<?php echo $i; ?>" 
                       class="btn btn-sm <?php echo ($i === $cp) ? 'btn-primary' : 'btn-ghost'; ?>" 
                       style="min-width: 28px; height: 28px; padding: 0 6px; font-size: 12px; font-weight: 700; display: inline-flex; align-items: center; justify-content: center; border-radius: 5px;">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>

                <?php if ($cp < $tp): ?>
                    <a href="dashboard.php?<?php echo $qStr; ?>&amp;ev_page=<?php echo $cp + 1; ?>" class="btn btn-ghost btn-sm" style="padding: 4px 8px; font-size: 12px; height: 28px; border-radius: 5px;">
                        <i class="fa-solid fa-arrow-right"></i>
                    </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div> <!-- Close #sched-view-table -->
</div>

<script>
    window.priestEvents = <?php echo json_encode($all_calendar_events ?? $events ?? []); ?>;
    window.pastorEvents = window.priestEvents;
    window.canCreateEvent = <?php echo $canCreateEvent ? 'true' : 'false'; ?>;
    window.canEditEvents  = <?php echo $canEditEvents ? 'true' : 'false'; ?>;

    window.chapelColorMap = {
        'all': '#000000',
        'cathedral': '#000000',
        '0': '#000000',
        <?php foreach (($chapels ?? []) as $ch): ?>
        '<?php echo $ch['chapel_id']; ?>': '<?php echo $ch['theme_color'] ?: '#000000'; ?>',
        <?php endforeach; ?>
    };

    window.chapelNameMap = {
        'all': 'All Venues',
        'cathedral': 'Main Cathedral',
        '0': 'Main Cathedral',
        <?php foreach (($chapels ?? []) as $ch): ?>
        '<?php echo $ch['chapel_id']; ?>': <?php echo json_encode($ch['chapel_name']); ?>,
        <?php endforeach; ?>
    };

    function setScheduleMode(mode) {
        var calView = document.getElementById('sched-view-cal');
        var tblView = document.getElementById('sched-view-table');
        var btnCal  = document.getElementById('btn-toggle-cal');
        var btnTbl  = document.getElementById('btn-toggle-table');
        var topVenueWrap = document.getElementById('sched-top-venue-wrap');

        if (!calView || !tblView) return;

        if (mode === 'table') {
            calView.style.display = 'none';
            tblView.style.display = 'block';
            if (btnCal) btnCal.classList.remove('active');
            if (btnTbl) btnTbl.classList.add('active');
            if (topVenueWrap) topVenueWrap.style.display = 'none';
            try { localStorage.setItem('parish_sched_mode', 'table'); } catch(e){}
        } else {
            calView.style.display = 'grid';
            tblView.style.display = 'none';
            if (btnCal) btnCal.classList.add('active');
            if (btnTbl) btnTbl.classList.remove('active');
            if (topVenueWrap) topVenueWrap.style.display = '';
            try { localStorage.setItem('parish_sched_mode', 'cal'); } catch(e){}
            if (typeof renderCalendar === 'function') {
                renderCalendar(calYear, calMonth);
            }
        }
    }

    // Auto-detect mode based on URL or previous user choice
    var requestedMode = <?php echo json_encode($defaultMode); ?>;

    if (!requestedMode) {
        try {
            var stored = localStorage.getItem('parish_sched_mode');
            if (stored === 'table') requestedMode = 'table';
        } catch(e){}
    }
    setScheduleMode(requestedMode === 'table' ? 'table' : 'cal');

    var initialChapel = <?php echo json_encode($ev_filters['venue'] ?? $_GET['chapel_id'] ?? $_GET['ev_venue'] ?? ($isScopedToChapel ? (string)$chapel_id : ($isCathedralStaff ? 'cathedral' : 'all'))); ?>;
    if (typeof selectChapelFilter === 'function' && initialChapel && initialChapel !== 'all') {
        selectChapelFilter(initialChapel);
    } else {
        if (typeof selectChapelFilter === 'function') {
            selectChapelFilter(<?php echo json_encode($isScopedToChapel ? (string)$chapel_id : ($isCathedralStaff ? 'cathedral' : 'all')); ?>);
        } else {
            if (typeof renderCalendar === 'function') {
                renderCalendar(calYear, calMonth);
            }
            if (typeof renderTimeline === 'function') {
                var dInput = document.getElementById('timeline-date');
                var curDate = dInput ? dInput.value : (new Date().toISOString().substring(0, 10));
                renderTimeline(curDate);
            }
        }
    }
</script>