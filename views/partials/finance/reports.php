<?php
// ═══════════════════════════════════════════════════════════════
// block html dis -> REPORTS (System Reports & Activity Metrics)
// ═══════════════════════════════════════════════════════════════
if (empty($can_reports) && empty($can_finance_reports)) return;

$js_event_types  = json_encode($chart['event_types']   ?? [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
$js_event_status = json_encode($chart['event_status']  ?? [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
$js_monthly      = json_encode($chart['events_monthly']?? [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
$js_rec_types    = json_encode($chart['record_types']  ?? [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
$js_venue_demand = json_encode($chart['venue_demand']  ?? [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);

// Category badge color map (server-side)
$categoryColors = [
    'Mass'               => ['bg' => 'rgba(197,160,89,0.12)', 'color' => '#c5a059', 'border' => 'rgba(197,160,89,0.3)'],
    'Wedding'            => ['bg' => 'rgba(197,160,89,0.12)', 'color' => '#c5a059', 'border' => 'rgba(197,160,89,0.3)'],
    'Baptism'            => ['bg' => 'rgba(22,163,74,0.12)', 'color' => '#16a34a', 'border' => 'rgba(22,163,74,0.3)'],
    'Funeral'            => ['bg' => 'rgba(100,116,139,0.12)', 'color' => '#64748b', 'border' => 'rgba(100,116,139,0.3)'],
    'Confirmation'       => ['bg' => 'rgba(197,160,89,0.12)', 'color' => '#c5a059', 'border' => 'rgba(197,160,89,0.3)'],
    'First Communion'    => ['bg' => 'rgba(197,160,89,0.12)', 'color' => '#c5a059', 'border' => 'rgba(197,160,89,0.3)'],
    'Anointing of Sick'  => ['bg' => 'rgba(22,163,74,0.12)', 'color' => '#16a34a', 'border' => 'rgba(22,163,74,0.3)'],
    'Holy Orders'        => ['bg' => 'rgba(197,160,89,0.12)', 'color' => '#c5a059', 'border' => 'rgba(197,160,89,0.3)'],
];
?>
<link rel="stylesheet" href="assets/css/workspace.css?v=2.5">
<!-- chartjs-plugin-datalabels for in-chart count/% labels -->
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.2.0/dist/chartjs-plugin-datalabels.min.js"></script>

<style>
/* ── System Reports local enhancements ── */
#sec-reports {
    height: auto !important;
    min-height: 100% !important;
    flex: none !important;
}
#sec-reports .reports-table-panel {
    flex: none !important;
    min-height: auto !important;
    margin-bottom: 24px !important;
}
#sec-reports .stat-label {
    /* was: 0.7rem */ font-size: var(--font-size-xs);
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--text2);
}
#sec-reports .stat-value {
    /* was: 1.55rem */ font-size: var(--font-size-xl);
    font-weight: 700;
    margin-top: 2px;
    font-family: var(--font-family-base, 'Inter', sans-serif);
    line-height: 1;
}
/* Filter toolbar */
#sec-reports .reports-filter-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    flex-wrap: wrap;
    padding: 8px 12px;
    background: var(--panel-bg);
    border: var(--panel-border);
    border-radius: var(--panel-radius);
}
/* Quick-range preset chips */
#sec-reports .range-chips {
    display: flex;
    align-items: center;
    gap: 5px;
    flex-wrap: wrap;
}
#sec-reports .range-chip {
    padding: 4px 12px;
    font-size: 11px;
    font-weight: 600;
    border-radius: 999px;
    background: #F6F6F6;
    border: 1px solid var(--border);
    color: var(--text, #111111);
    cursor: pointer;
    transition: all 0.18s ease;
    white-space: nowrap;
    line-height: 1.2;
    height: 28px;
    box-sizing: border-box;
}
#sec-reports .range-chip:hover {
    background: #111111;
    color: #FFFFFF;
    border-color: #111111;
}
[data-theme="dark"] #sec-reports .range-chip {
    background: rgba(255, 255, 255, 0.05);
    border-color: rgba(255, 255, 255, 0.14);
    color: #cbd5e1;
}
[data-theme="dark"] #sec-reports .range-chip:hover {
    background: #FFFFFF;
    color: #111111;
    border-color: #FFFFFF;
}
/* Chart container & Analytics Grid */
#sec-reports .chart-container {
    position: relative;
    width: 100%;
}
#sec-reports .reports-analytics-grid {
    display: grid;
    grid-template-columns: 1.15fr 0.85fr;
    gap: 12px;
}
@media (max-width: 1024px) {
    #sec-reports .reports-analytics-grid {
        grid-template-columns: 1fr !important;
    }
}
#sec-reports #circle-metric-select {
    padding: 4px 26px 4px 10px;
    border-radius: 6px;
    border: 1px solid var(--border);
    background: var(--bg);
    color: var(--text);
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    outline: none;
    transition: border-color 0.15s, box-shadow 0.15s;
}
#sec-reports #circle-metric-select:focus {
    border-color: var(--accent);
    box-shadow: 0 0 0 2px rgba(201, 168, 76, 0.2);
}
/* Table row stripe */
#sec-reports .data-table tbody tr:nth-child(even) {
    /* was: rgba(255,255,255,0.018) */ background: var(--color-surface-hover);
}
#sec-reports .data-table tbody tr:hover {
    background: rgba(201,168,76,0.055) !important;
}
/* Inline legend list for doughnut charts */
#sec-reports .chart-legend-list {
    list-style: none;
    padding: 0;
    margin: 0;
    display: flex;
    flex-direction: column;
    gap: 7px;
    min-width: 130px;
}
#sec-reports .chart-legend-list li {
    display: flex;
    align-items: center;
    gap: 8px;
    /* was: 12px */ font-size: var(--font-size-sm);
    color: var(--text);
}
#sec-reports .legend-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    flex-shrink: 0;
}
#sec-reports .legend-count {
    margin-left: auto;
    font-weight: 700;
    font-size: 12px;
    color: var(--text);
}
#sec-reports .legend-label {
    flex: 1;
    color: var(--text2);
    font-size: 11.5px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
/* Row inspect action */
#sec-reports .row-inspect-btn {
    background: transparent;
    border: none;
    color: var(--text2);
    cursor: pointer;
    padding: 4px 6px;
    border-radius: 6px;
    transition: color 0.15s, background 0.15s;
    /* was: 13px */ font-size: var(--font-size-sm);
}
#sec-reports .row-inspect-btn:hover {
    color: var(--accent, #c9a84c);
    background: rgba(201,168,76,0.1);
}
/* Category badge */
#sec-reports .cat-badge {
    display: inline-flex;
    align-items: center;
    padding: 2px 9px;
    border-radius: 999px;
    font-size: 10.5px;
    font-weight: 700;
    border: 1px solid;
    white-space: nowrap;
}
/* DOW chip */
#sec-reports .dow-chip {
    display: inline-block;
    font-size: 10px;
    font-weight: 600;
    color: var(--accent, #c9a84c);
    background: rgba(201,168,76,0.09);
    border-radius: 4px;
    padding: 1px 5px;
    margin-left: 4px;
    vertical-align: middle;
}
</style>

<div class="workspace workspace-fluid" id="sec-reports" style="display: flex; flex-direction: column; gap: 14px; height: auto !important; min-height: 100%; width: 100%; max-width: 1680px; margin: 0 auto; box-sizing: border-box; padding: 14px clamp(16px, 1.8vw, 24px) 60px !important;">

    <!-- ── Streamlined Header & Summary Bar ── -->
    <div class="page-header-bar" style="display: flex; justify-content: space-between; align-items: center; padding-bottom: 8px; border-bottom: 1px solid var(--border); flex-wrap: wrap; gap: 10px; margin-bottom: 6px;">
        <div style="display: flex; align-items: center; gap: 12px;">
            <div class="page-header-icon" style="width: 38px; height: 38px; border-radius: 9px; background: rgba(201,168,76,0.14); color: var(--accent); display: flex; align-items: center; justify-content: center; font-size: 17px; flex-shrink: 0;">
                <i class="fa-solid fa-chart-pie"></i>
            </div>
            <div>
                <h1 class="page-title" style="font-family: var(--font-family, 'Inter', sans-serif); font-size: var(--font-page-title, 20px); font-weight: 700; color: var(--text); margin: 0; line-height: 1.2;">
                    Reports
                </h1>
                <p class="page-subtitle" style="font-size: var(--font-page-subtitle, 14px); color: var(--text2); margin: 3px 0 0 0;">Event statistics, service numbers, and parish activity reports</p>
            </div>
        </div>

        <!-- Right Side: Clean Total Badge -->
        <div style="display: flex; align-items: center; gap: 8px;">
            <span class="badge" style="background: var(--bg3, #F4F4F5); border: 1px solid var(--border); color: var(--text2); font-size: 11.5px; font-weight: 600; padding: 4px 12px; border-radius: 999px;">
                <?php echo number_format($stats['total_events'] ?? 0); ?> Events
            </span>
            <?php if (!empty($stats['pending'])): ?>
            <span class="badge badge-warning" style="font-size: 11.5px; font-weight: 600; padding: 4px 10px; border-radius: 999px;">
                <?php echo number_format($stats['pending']); ?> Pending
            </span>
            <?php endif; ?>
        </div>
    </div>

    <!-- ── Filter & Action Toolbar ── -->
    <div class="reports-filter-bar">
        <div style="display: flex; align-items: center; gap: 14px; flex-wrap: wrap; flex: 1;">

            <!-- Quick range chips -->
            <div class="range-chips">
                <span style="font-size: 10.5px; font-weight: 700; color: var(--text2); text-transform: uppercase; letter-spacing: 0.05em;">Quick:</span>
                <button type="button" class="range-chip" data-range="week">This Week</button>
                <button type="button" class="range-chip" data-range="month">This Month</button>
                <button type="button" class="range-chip" data-range="3months">Last 3 Mo</button>
                <button type="button" class="range-chip" data-range="year">This Year</button>
            </div>

            <div style="width: 1px; height: 22px; background: var(--border); flex-shrink: 0;"></div>

            <!-- Date range form -->
            <form method="GET" id="reports-filter-form" style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap; margin: 0;">
                <input type="hidden" name="section" value="reports">

                <div style="display: flex; align-items: center; gap: 6px;">
                    <label style="font-size: 0.78rem; font-weight: 600; color: var(--text2);">From:</label>
                    <input type="date" name="from" id="rpt-from" max="2099-12-31"
                           value="<?php echo htmlspecialchars($rfrom); ?>"
                           style="padding: 5px 10px; font-size: 0.83rem; border-radius: 6px; border: 1px solid var(--border); background: var(--bg); color: var(--text);">
                </div>

                <div style="display: flex; align-items: center; gap: 6px;">
                    <label style="font-size: 0.78rem; font-weight: 600; color: var(--text2);">To:</label>
                    <input type="date" name="to" id="rpt-to" max="2099-12-31"
                           value="<?php echo htmlspecialchars($rto); ?>"
                           style="padding: 5px 10px; font-size: 0.83rem; border-radius: 6px; border: 1px solid var(--border); background: var(--bg); color: var(--text);">
                </div>

                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="fa-solid fa-magnifying-glass"></i> Generate
                </button>
            </form>
        </div>

        <!-- Export actions -->
        <div style="display: flex; align-items: center; gap: 6px; flex-shrink: 0;">
            <a href="?section=reports&from=<?php echo urlencode($rfrom); ?>&to=<?php echo urlencode($rto); ?>&export=csv"
               class="btn btn-ghost btn-sm" style="border: 1px solid var(--border); /* was: #10b981 */ color: var(--color-success);" title="Download raw event data CSV">
                <i class="fa-solid fa-file-csv"></i> CSV
            </a>
            <a href="views/pages/finance_report_print.php?from=<?php echo urlencode($rfrom); ?>&to=<?php echo urlencode($rto); ?>&hide_ledgers=1"
               target="_blank"
               class="btn btn-ghost btn-sm" style="border: 1px solid var(--border); /* was: #3b82f6 */ color: var(--color-primary);" title="Print summary report">
                <i class="fa-solid fa-file-contract"></i> Summary
            </a>
            <a href="views/pages/finance_report_print.php?from=<?php echo urlencode($rfrom); ?>&to=<?php echo urlencode($rto); ?>"
               target="_blank"
               class="btn btn-ghost btn-sm" style="border: 1px solid var(--border); color: var(--color-accent);" title="Custom print / PDF">
                <i class="fa-solid fa-print"></i> PDF
            </a>
            <a href="views/pages/service_register_print.php?from=<?php echo urlencode($rfrom); ?>&to=<?php echo urlencode($rto); ?>"
               target="_blank"
               class="btn btn-secondary btn-sm" style="background: rgba(201,168,76,0.15); border: 1px solid var(--accent); color: var(--accent); font-weight: 600;" title="Canonical Register of Services (Service Book) with customizable print columns">
                <i class="fa-solid fa-book-bible"></i> Service Register
            </a>
        </div>
    </div>

    <!-- ── Unified Analytics Section ── -->
    <div class="reports-analytics-grid" style="margin-bottom: 12px;">

        <!-- Left Column: Volume Trends (Monthly Trend + Sacramental Records) -->
        <div style="display: flex; flex-direction: column; gap: 12px;">
            <!-- 1. Monthly Trend (bar) -->
            <div class="panel" style="flex: 1; display: flex; flex-direction: column;">
                <div class="panel-header">
                    <div class="panel-title">
                        <i class="fa-solid fa-chart-bar" style="color: var(--accent);"></i>
                        <span>Events Trend <span style="font-weight: 400; color: var(--text2); font-size: 11px;">(Last 12 Months)</span></span>
                    </div>
                </div>
                <div class="panel-body" style="padding: 14px; flex: 1;">
                    <div class="chart-container" style="height: 180px;">
                        <canvas id="chart-monthly"></canvas>
                    </div>
                </div>
            </div>

            <!-- 2. Sacramental Records Volume (horizontal bar) -->
            <div class="panel" style="flex: 1; display: flex; flex-direction: column;">
                <div class="panel-header">
                    <div class="panel-title">
                        <i class="fa-solid fa-book-bible" style="color: var(--accent);"></i>
                        <span>Sacramental Records Volume</span>
                    </div>
                </div>
                <div class="panel-body" style="padding: 14px; flex: 1;">
                    <div class="chart-container" style="height: 160px;">
                        <canvas id="chart-records"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column: Merged Interactive Circle Graph with Dropdown Selector -->
        <div class="panel" style="display: flex; flex-direction: column; height: 100%;">
            <div class="panel-header" style="display: flex; justify-content: space-between; align-items: center; gap: 10px; flex-wrap: wrap;">
                <div class="panel-title" style="display: flex; align-items: center; gap: 8px;">
                    <i id="circle-card-icon" class="fa-solid fa-chart-pie" style="color: var(--accent);"></i>
                    <span id="circle-card-title">Events by Category</span>
                </div>
                <div style="display: flex; align-items: center; gap: 6px;">
                    <label for="circle-metric-select" style="font-size: 11px; font-weight: 600; color: var(--text2); text-transform: uppercase; letter-spacing: 0.04em;">View:</label>
                    <select id="circle-metric-select" onchange="updateCircleChart(this.value); localStorage.setItem('reports_circle_metric', this.value);" style="font-size: 12px; font-weight: 600; padding: 4px 26px 4px 10px; border-radius: 6px; border: 1px solid var(--border); background: var(--bg); color: var(--text); cursor: pointer; height: 30px;">
                        <option value="category">Events by Category</option>
                        <option value="status">Events by Status</option>
                        <option value="venue">Venue &amp; Chapel Demand</option>
                        <option value="records">Sacramental Records</option>
                    </select>
                </div>
            </div>
            <div class="panel-body" style="padding: 16px; display: flex; flex-direction: column; gap: 12px; flex: 1; box-sizing: border-box; min-height: 0;">
                <!-- Donut Chart with Center Total -->
                <div style="position: relative; height: 190px; width: 100%; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                    <canvas id="chart-unified-circle"></canvas>
                    <div id="circle-center-stat" style="position: absolute; pointer-events: none; text-align: center; display: flex; flex-direction: column; align-items: center; justify-content: center;">
                        <span id="circle-center-value" style="font-family: var(--font-display, 'Inter', sans-serif); font-size: 1.55rem; font-weight: 800; color: var(--text); line-height: 1;">0</span>
                        <span id="circle-center-label" style="font-size: 10px; text-transform: uppercase; letter-spacing: 0.06em; font-weight: 600; color: var(--text2); margin-top: 3px;">TOTAL</span>
                    </div>
                </div>
                <!-- Interactive Breakdown Legend List -->
                <div style="flex: 1; min-height: 0; overflow-y: auto; border-top: 1px solid var(--border); padding-top: 10px;">
                    <ul class="chart-legend-list" id="unified-circle-legend" style="display: flex; flex-direction: column; gap: 7px;"></ul>
                </div>
            </div>
        </div>

    </div><!-- /reports-analytics-grid -->

    <!-- ── Event Results Table Panel ── -->
    <div class="panel reports-table-panel" style="flex: none !important; min-height: auto !important; margin-bottom: 30px;">
        <div class="panel-header" style="padding: 12px 18px !important;">
            <div class="panel-title" style="font-size: 0.92rem !important;">
                <i class="fa-solid fa-table" style="color: var(--accent);"></i>
                <span>Filtered Event Records</span>
                <span style="font-size: 0.76rem; font-weight: 400; color: var(--text2); margin-left: 6px;">
                    <?php echo date('M d, Y', strtotime($rfrom)); ?> – <?php echo date('M d, Y', strtotime($rto)); ?>
                </span>
            </div>
            <?php if (!empty($rep_events)): ?>
                <span class="badge" style="background: rgba(201,168,76,0.12); color: var(--accent); border: 1px solid rgba(201,168,76,0.25); font-size: 11px;">
                    <?php echo count($rep_events); ?> events found
                </span>
            <?php endif; ?>
        </div>

        <div class="panel-body" style="padding: 0; overflow-x: auto; max-height: 440px; overflow-y: auto;">
            <?php if (!empty($rep_events)): ?>
            <table class="data-table" style="width: 100%; min-width: 780px;">
                <thead style="position: sticky; top: 0; background: var(--panel-header-bg, var(--bg3)); z-index: 5;">
                    <tr>
                        <th style="width: 150px;">Date &amp; Time</th>
                        <th>Title</th>
                        <th style="width: 130px;">Category</th>
                        <th style="width: 150px;"><i class="fa-solid fa-location-dot" style="opacity:0.5; font-size:10px;"></i> Location</th>
                        <th style="width: 140px;"><i class="fa-solid fa-user-tie" style="opacity:0.5; font-size:10px;"></i> Officiant</th>
                        <th style="width: 110px; text-align: center;">Status</th>
                        <th style="width: 40px;"></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rep_events as $ev):
                    $dow = date('D', strtotime($ev['start_time']));
                    // Color for category badge
                    $evType = $ev['event_type'] ?? '';
                    $catStyle = $categoryColors[$evType] ?? ['bg' => 'rgba(148,163,184,0.1)', 'color' => '#94a3b8', 'border' => 'rgba(148,163,184,0.25)'];
                    // Color for status badge
                    $statusColors = [
                        'Pending'   => '#c5a059',
                        'Confirmed' => '#16a34a',
                        'Completed' => '#16a34a',
                        'Cancelled' => '#dc2626',
                    ];
                    $sc = $statusColors[$ev['status'] ?? ''] ?? '#94a3b8';
                    $evJson = htmlspecialchars(json_encode($ev), ENT_QUOTES, 'UTF-8');
                ?>
                <tr style="cursor: pointer; transition: background 0.12s;"
                    title="Click row to inspect"
                    onclick="if(typeof viewEventDetails==='function') viewEventDetails(<?php echo $evJson; ?>, true);">
                    <td>
                        <div style="font-weight: 600; /* was: 13px */ font-size: var(--font-size-sm); color: var(--text);">
                            <?php echo date('M d, Y', strtotime($ev['start_time'])); ?>
                            <span class="dow-chip"><?php echo $dow; ?></span>
                        </div>
                        <div style="font-size: 11px; color: var(--text2); margin-top: 2px;">
                            <?php echo date('h:i A', strtotime($ev['start_time'])); ?>
                        </div>
                    </td>
                    <td>
                        <div style="font-weight: 600; /* was: 13px */ font-size: var(--font-size-sm); color: var(--text);"><?php echo htmlspecialchars($ev['title']); ?></div>
                    </td>
                    <td>
                        <span class="cat-badge"
                              style="background: <?php echo $catStyle['bg']; ?>; color: <?php echo $catStyle['color']; ?>; border-color: <?php echo $catStyle['border']; ?>;">
                            <?php echo htmlspecialchars($evType ?: 'Other'); ?>
                        </span>
                    </td>
                    <td style="font-size: 12px; color: var(--text);">
                        <?php echo htmlspecialchars($ev['chapel_location'] ?: 'Main Cathedral'); ?>
                    </td>
                    <td style="font-size: 12px; color: var(--text);">
                        <?php echo htmlspecialchars($ev['pastor_assigned'] ?: 'Unassigned'); ?>
                    </td>
                    <td style="text-align: center;">
                        <span class="badge" style="background: <?php echo $sc; ?>15; color: <?php echo $sc; ?>; border: 1px solid <?php echo $sc; ?>30; font-size: 11px; padding: 3px 10px;">
                            <?php echo htmlspecialchars($ev['status']); ?>
                        </span>
                    </td>
                    <td style="text-align: center; padding-right: 10px;">
                        <button type="button" class="row-inspect-btn"
                                title="Inspect event"
                                onclick="event.stopPropagation(); if(typeof viewEventDetails==='function') viewEventDetails(<?php echo $evJson; ?>, true);">
                            <i class="fa-solid fa-eye"></i>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div style="padding: 52px; text-align: center; color: var(--text2);">
                <i class="fa-solid fa-calendar-xmark" style="font-size: 42px; opacity: 0.2; margin-bottom: 14px; display: block; color: var(--accent);"></i>
                <div style="font-size: 15px; font-weight: 600; color: var(--text); margin-bottom: 6px;">No Matching Events</div>
                <div style="font-size: 13px;">No events recorded for <strong><?php echo htmlspecialchars($rfrom); ?></strong> to <strong><?php echo htmlspecialchars($rto); ?></strong>.</div>
                <div style="margin-top: 14px;">
                    <span style="font-size: 12px; color: var(--text2);">Try a different date range using the filter above.</span>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

</div><!-- /.workspace -->

<script>
(function(){
    // ── Quick range chips ──
    (function() {
        const fromEl = document.getElementById('rpt-from');
        const toEl   = document.getElementById('rpt-to');
        const form   = document.getElementById('reports-filter-form');

        function fmt(d) {
            return d.toISOString().split('T')[0];
        }

        document.querySelectorAll('#sec-reports .range-chip').forEach(chip => {
            chip.addEventListener('click', () => {
                const now   = new Date();
                const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
                let from, to = fmt(today);

                switch (chip.dataset.range) {
                    case 'week':
                        const dow = today.getDay(); // 0=Sun
                        const diff = (dow === 0) ? 6 : dow - 1; // Mon start
                        const mon = new Date(today);
                        mon.setDate(today.getDate() - diff);
                        from = fmt(mon);
                        break;
                    case 'month':
                        from = fmt(new Date(now.getFullYear(), now.getMonth(), 1));
                        break;
                    case '3months':
                        const m3 = new Date(today);
                        m3.setMonth(m3.getMonth() - 3);
                        from = fmt(m3);
                        break;
                    case 'year':
                        from = fmt(new Date(now.getFullYear(), 0, 1));
                        break;
                }
                if (fromEl) fromEl.value = from;
                if (toEl)   toEl.value   = to;
                if (form)   form.submit();
            });
        });
    })();

    // ── Charts ──
    if (typeof Chart === 'undefined') return;

    // Register datalabels if available
    if (typeof ChartDataLabels !== 'undefined') {
        Chart.register(ChartDataLabels);
    }

    // Palette tokens
    const gold = '#c9a84c', green = '#16a34a', red = '#dc2626', dark = '#0b0f19', slate = '#64748b';

    Chart.defaults.font.family = "var(--font-family, 'Inter', sans-serif)";
    Chart.defaults.font.size   = 11;
    Chart.defaults.color       = '#94a3b8';
    Chart.defaults.plugins.tooltip.backgroundColor = 'rgba(15, 23, 42, 0.94)';
    Chart.defaults.plugins.tooltip.padding = 10;
    Chart.defaults.plugins.tooltip.cornerRadius = 8;
    Chart.defaults.plugins.tooltip.titleFont = { weight: '700', size: 12 };
    Chart.defaults.plugins.datalabels = { display: false }; // off by default

    const palette = [gold, green, red, dark, slate, '#d8b56f', '#15803d', '#334155'];

    // ── 1. Events Monthly Trend (bar + count labels) ──
    const monthlyRaw  = <?php echo $js_monthly; ?>;
    const monthlyKeys = Object.keys(monthlyRaw);
    const monthLabels = monthlyKeys.map(ym => {
        const [y, m] = ym.split('-');
        return new Date(y, m - 1).toLocaleString('default', { month: 'short', year: '2-digit' });
    });
    const monthlyData = Object.values(monthlyRaw);
    const maxMonth    = Math.max(...(monthlyData.length ? monthlyData : [1]));

    (function(){ const el = document.getElementById('chart-monthly'); if (el) { const ex = Chart.getChart(el); if (ex) ex.destroy(); } })();
    new Chart(document.getElementById('chart-monthly'), {
        type: 'bar',
        plugins: typeof ChartDataLabels !== 'undefined' ? [ChartDataLabels] : [],
        data: {
            labels: monthLabels.length ? monthLabels : ['No Data'],
            datasets: [{
                label: 'Events',
                data: monthlyData.length ? monthlyData : [0],
                backgroundColor: monthlyData.map((v, i) =>
                    v === maxMonth ? 'rgba(201,168,76,0.55)' : 'rgba(201,168,76,0.22)'
                ),
                borderColor: gold,
                borderWidth: 1.5,
                borderRadius: 5,
                hoverBackgroundColor: gold
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                datalabels: {
                    display: (ctx) => ctx.dataset.data[ctx.dataIndex] > 0,
                    color: gold,
                    font: { weight: '700', size: 10 },
                    anchor: 'end',
                    align: 'top',
                    offset: 2,
                    formatter: v => v
                }
            },
            scales: {
                x: {
                    grid: { display: false },
                    ticks: { color: '#94a3b8', maxRotation: 45 }
                },
                y: {
                    grid: { color: 'rgba(255,255,255,0.04)' },
                    beginAtZero: true,
                    ticks: { stepSize: 1, color: '#64748b', precision: 0 }
                }
            }
        }
    });

    // ── 2. Unified Interactive Circle Graph Controller with Metric Dropdown ──
    const typeRaw    = <?php echo $js_event_types; ?>;
    const typeKeys   = Object.keys(typeRaw);
    const typeValues = Object.values(typeRaw);
    const typeTotal  = typeValues.reduce((a, b) => a + b, 0);

    const statusRaw    = <?php echo $js_event_status; ?>;
    const statusKeys   = Object.keys(statusRaw);
    const statusValues = Object.values(statusRaw);
    const statusTotal  = statusValues.reduce((a, b) => a + b, 0);
    const statusColorMap = { Pending: gold, Confirmed: green, Completed: green, Cancelled: red };

    const venueRaw    = <?php echo $js_venue_demand; ?>;
    const hasVenue    = Array.isArray(venueRaw) && venueRaw.length > 0;
    const venueLabels = hasVenue ? venueRaw.map(d => d.label || 'Unknown') : [];
    const venueValues = hasVenue ? venueRaw.map(d => Number(d.value) || 0) : [];
    const venueTotal  = hasVenue ? venueValues.reduce((a, b) => a + b, 0) : 0;
    const venueColors = [gold, green, red, dark, slate, '#d8b56f', '#15803d', '#334155'];

    const recRaw    = <?php echo $js_rec_types; ?>;
    const recKeys   = Object.keys(recRaw);
    const recValues = Object.values(recRaw);
    const recTotal  = recValues.reduce((a, b) => a + b, 0);

    const circleDatasets = {
        category: {
            title: 'Events by Category',
            icon: 'fa-layer-group',
            unit: 'EVENTS',
            labels: typeKeys,
            values: typeValues,
            colors: palette.slice(0, typeKeys.length),
            total: typeTotal
        },
        status: {
            title: 'Events by Status',
            icon: 'fa-circle-half-stroke',
            unit: 'EVENTS',
            labels: statusKeys,
            values: statusValues,
            colors: statusKeys.map(k => statusColorMap[k] || '#6b7280'),
            total: statusTotal
        },
        venue: {
            title: 'Venue & Chapel Demand',
            icon: 'fa-map-location-dot',
            unit: 'EVENTS',
            labels: venueLabels,
            values: venueValues,
            colors: venueLabels.map((_, i) => venueColors[i % venueColors.length]),
            total: venueTotal
        },
        records: {
            title: 'Sacramental Records Volume',
            icon: 'fa-book-bible',
            unit: 'RECORDS',
            labels: recKeys,
            values: recValues,
            colors: palette.slice(0, recKeys.length),
            total: recTotal
        }
    };

    let unifiedCircleChart = null;
    const circleCanvas = document.getElementById('chart-unified-circle');

    function updateCircleChart(metricKey) {
        const ds = circleDatasets[metricKey] || circleDatasets['category'];
        if (!ds) return;

        // Update Card Title and Icon
        const titleEl = document.getElementById('circle-card-title');
        const iconEl  = document.getElementById('circle-card-icon');
        if (titleEl) titleEl.textContent = ds.title;
        if (iconEl)  iconEl.className = 'fa-solid ' + ds.icon;

        // Update Center Stat
        const centerValEl = document.getElementById('circle-center-value');
        const centerLblEl = document.getElementById('circle-center-label');
        if (centerValEl) centerValEl.textContent = Number(ds.total).toLocaleString();
        if (centerLblEl) centerLblEl.textContent = ds.unit;

        // Check if there is data
        const hasData = ds.labels.length > 0 && ds.values.some(v => v > 0);
        const chartLabels = hasData ? ds.labels : ['No Data Recorded'];
        const chartValues = hasData ? ds.values : [1];
        const chartColors = hasData ? ds.colors : ['rgba(255,255,255,0.08)'];

        if (unifiedCircleChart) {
            unifiedCircleChart.data.labels = chartLabels;
            unifiedCircleChart.data.datasets[0].data = chartValues;
            unifiedCircleChart.data.datasets[0].backgroundColor = chartColors;
            unifiedCircleChart.data.datasets[0].hoverOffset = hasData ? 8 : 0;
            unifiedCircleChart.options.plugins.tooltip.enabled = hasData;
            unifiedCircleChart.update();
        } else if (circleCanvas) {
            const existing = Chart.getChart(circleCanvas);
            if (existing) existing.destroy();
            unifiedCircleChart = new Chart(circleCanvas, {
                type: 'doughnut',
                data: {
                    labels: chartLabels,
                    datasets: [{
                        data: chartValues,
                        backgroundColor: chartColors,
                        borderWidth: 0,
                        hoverOffset: hasData ? 8 : 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            enabled: hasData,
                            callbacks: {
                                label: (ctx) => ` ${ctx.label}: ${ctx.raw} (${ds.total ? Math.round(ctx.raw / ds.total * 100) : 0}%)`
                            }
                        }
                    },
                    cutout: '72%'
                }
            });
        }

        // Build Custom Legend
        const legendEl = document.getElementById('unified-circle-legend');
        if (legendEl) {
            legendEl.innerHTML = '';
            if (hasData) {
                ds.labels.forEach((label, i) => {
                    const count = ds.values[i];
                    const pct = ds.total ? Math.round(count / ds.total * 100) : 0;
                    const color = ds.colors[i % ds.colors.length];
                    legendEl.innerHTML += `
                        <li style="display: flex; align-items: center; gap: 8px; font-size: 12px; padding: 2px 0;">
                            <span class="legend-dot" style="background:${color}; width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0;"></span>
                            <span class="legend-label" title="${label}" style="flex: 1; color: var(--text); font-weight: 500; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">${label}</span>
                            <span class="legend-count" style="font-weight: 700; color: var(--text); font-size: 12px;">${count}</span>
                            <span style="font-size: 11px; color: var(--text2); min-width: 36px; text-align: right; font-weight: 600;">${pct}%</span>
                        </li>`;
                });
            } else {
                legendEl.innerHTML = '<li style="color: var(--text2); font-size: 12px; font-style: italic; padding: 8px 0; text-align: center;">No activity recorded for this period</li>';
            }
        }
    }
    window.updateCircleChart = updateCircleChart;

    // Dropdown switch listener
    const selectEl = document.getElementById('circle-metric-select');
    if (selectEl) {
        const savedMetric = localStorage.getItem('reports_circle_metric') || 'category';
        if (circleDatasets[savedMetric]) {
            selectEl.value = savedMetric;
        }
        selectEl.addEventListener('change', function(e) {
            const val = e.target.value;
            localStorage.setItem('reports_circle_metric', val);
            updateCircleChart(val);
        });
        updateCircleChart(selectEl.value);
    }

    // ── 5. Sacramental Records Volume (horizontal bar) ──
    const hasRec    = recKeys.length > 0;

    (function(){ const el = document.getElementById('chart-records'); if (el) { const ex = Chart.getChart(el); if (ex) ex.destroy(); } })();
    new Chart(document.getElementById('chart-records'), {
        type: 'bar',
        plugins: typeof ChartDataLabels !== 'undefined' ? [ChartDataLabels] : [],
        data: {
            labels: hasRec ? recKeys : ['No Data'],
            datasets: [{
                label: 'Records',
                data: hasRec ? recValues : [0],
                backgroundColor: recKeys.map((_, i) => [
                    'rgba(201, 168, 76, 0.35)',
                    'rgba(22, 163, 74, 0.35)',
                    'rgba(216, 181, 111, 0.35)',
                    'rgba(100, 116, 139, 0.35)',
                    'rgba(11, 15, 25, 0.35)'
                ][i % 5]),
                borderColor: recKeys.map((_, i) => [gold, green, '#d8b56f', slate, dark][i % 5]),
                borderWidth: 1.5,
                borderRadius: 4
            }]
        },
        options: {
            indexAxis: 'y',  // horizontal bar
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                datalabels: {
                    display: (ctx) => ctx.dataset.data[ctx.dataIndex] > 0,
                    color: '#94a3b8',
                    font: { weight: '600', size: 10 },
                    anchor: 'end',
                    align: 'right',
                    offset: 4,
                    formatter: v => v
                }
            },
            scales: {
                x: {
                    grid: { color: 'rgba(255,255,255,0.04)' },
                    beginAtZero: true,
                    ticks: { precision: 0, color: '#64748b' }
                },
                y: {
                    grid: { display: false },
                    ticks: { color: '#94a3b8', font: { size: 11 } }
                }
            }
        }
    });
})();
</script>
