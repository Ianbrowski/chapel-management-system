<?php
/**
 * Sacramental Records Section - Server-side rendered
 */
$section_id = "sec-records_" . $scope;
$title = ($scope === 'cathedral' ? 'Cathedral' : 'Chapel') . ' Sacramental Records';
$local_stats = $record_stats ?? [];

$can_edit = ($scope === 'cathedral') 
    ? (in_array($role, ['Admin', 'Cathedral Admin']) || ($role === 'Staff' && ($chapel_id === null || (int)$chapel_id === 0)))
    : in_array($role, ['Admin', 'Cathedral Admin', 'Chapel Admin', 'Staff']);

$can_delete = ($scope === 'cathedral') 
    ? in_array($role, ['Admin', 'Cathedral Admin']) 
    : in_array($role, ['Admin', 'Cathedral Admin', 'Chapel Admin']);

if (!function_exists('renderSacramentBadge')) {
    function renderSacramentBadge(string $type): string {
        $iconMap = [
            'Baptism' => 'fa-water',
            'Confirmation' => 'fa-dove',
            'Wedding' => 'fa-ring',
            'Funeral' => 'fa-cross',
            'Communion' => 'fa-wine-glass',
        ];
        $icon = $iconMap[$type] ?? 'fa-scroll';
        return '<span class="sacrament-badge" style="background: var(--bg3, #F4F4F5); color: var(--text, #111111); border: 1px solid var(--border, #E5E5E5); font-weight: 600; font-size: 11px; letter-spacing: 0.04em; padding: 3px 10px; border-radius: 6px; display: inline-flex; align-items: center; justify-content: center; gap: 5px; text-transform: uppercase; white-space: nowrap;"><i class="fa-solid ' . $icon . '" style="font-size: 9.5px; opacity: 0.65;"></i> ' . htmlspecialchars($type) . '</span>';
    }
}
?>

<?php
$curType = $rec_filters['record_type'] ?? '';
$searchQuery = $rec_filters['search'] ?? '';
$selected_chapel_id = $selected_chapel_id ?? (isset($_GET['chapel_id']) && $_GET['chapel_id'] !== '' ? (int)$_GET['chapel_id'] : null);
$curSort = $rec_filters['sort'] ?? ($_GET['rec_sort'] ?? ($_GET['sort'] ?? 'date_desc'));
$curTimeframe = $rec_filters['timeframe'] ?? ($_GET['timeframe'] ?? '');
$curPriest = $rec_filters['officiating_priest'] ?? ($_GET['priest'] ?? '');

$chapelParam = ($selected_chapel_id !== null && $selected_chapel_id !== '') ? '&chapel_id=' . (int)$selected_chapel_id : '';
$searchParam = !empty($searchQuery) ? '&search=' . urlencode($searchQuery) : '';
$sortParam = !empty($curSort) ? '&rec_sort=' . urlencode($curSort) : '';
$timeParam = !empty($curTimeframe) ? '&timeframe=' . urlencode($curTimeframe) : '';
$priestParam = !empty($curPriest) ? '&priest=' . urlencode($curPriest) : '';
$combinedParams = $searchParam . $chapelParam . $sortParam . $timeParam . $priestParam;

$typesList = [
    'Baptism' => ['label' => 'Baptisms', 'val' => $local_stats['baptisms'] ?? 0],
    'Confirmation' => ['label' => 'Confirmations', 'val' => $local_stats['confirmations'] ?? 0],
    'Wedding' => ['label' => 'Weddings', 'val' => $local_stats['weddings'] ?? 0],
    'Communion' => ['label' => 'Communions', 'val' => $local_stats['communions'] ?? 0],
    'Funeral' => ['label' => 'Funerals', 'val' => $local_stats['funerals'] ?? 0],
];
$isAllActive = empty($curType);
?>

<style>
.records-tab-link {
    border: 1px solid transparent;
    background: transparent;
    padding: 6px 14px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 500;
    color: var(--text2);
    text-decoration: none;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: all 0.18s ease;
    line-height: 1.2;
    min-height: 32px;
    height: 32px;
    box-sizing: border-box;
    white-space: nowrap;
}
.records-tab-link:hover {
    color: var(--text);
    background: var(--hover-bg, rgba(0,0,0,0.04));
}
.records-tab-link.active {
    background: #111111 !important;
    color: #FFFFFF !important;
    border-color: #111111 !important;
    font-weight: 700;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.14);
}
[data-theme="dark"] .records-tabs-group {
    background: #18181b !important;
    border-color: rgba(255, 255, 255, 0.12) !important;
}
[data-theme="dark"] .records-tab-link {
    color: #cbd5e1;
}
[data-theme="dark"] .records-tab-link:hover {
    color: #ffffff;
    background: rgba(255, 255, 255, 0.08);
}
[data-theme="dark"] .records-tab-link.active {
    background: #FFFFFF !important;
    color: #111111 !important;
    border-color: #FFFFFF !important;
    box-shadow: 0 2px 8px rgba(0,0,0,0.3);
}

[data-theme="dark"] .records-chapel-select {
    background: #18181b !important;
    border-color: rgba(255, 255, 255, 0.12) !important;
    color: #cbd5e1 !important;
}
[data-theme="dark"] .records-chapel-select:focus {
    border-color: var(--accent) !important;
}

/* Unified clean sacrament badge styling */
.sacrament-badge {
    background: var(--bg3, #F4F4F5) !important;
    color: var(--text, #111111) !important;
    border: 1px solid var(--border, #E5E5E5) !important;
}
[data-theme="dark"] .sacrament-badge {
    background: rgba(255, 255, 255, 0.06) !important;
    color: #e2e8f0 !important;
    border-color: rgba(255, 255, 255, 0.12) !important;
}

/* Fluid workspace & Responsive split panel */
.records-workspace {
    width: 100% !important;
    max-width: 1680px !important;
    margin: 0 auto !important;
    box-sizing: border-box;
    padding: 14px clamp(16px, 1.8vw, 24px) 24px !important;
    display: flex;
    flex-direction: column;
    gap: 14px !important;
    height: 100%;
    min-height: 0;
}
.records-split-panel {
    display: flex;
    gap: 14px;
    width: 100%;
    flex: 1;
    min-height: 0;
    overflow: hidden;
}
.records-split-panel .panel {
    padding: 0 !important;
    margin-bottom: 0 !important;
}
.records-split-panel .panel-header {
    padding: 11px 16px !important;
}
.records-split-right .panel-body {
    padding: 12px 14px !important;
}
.records-filter-toolbar select {
    width: auto !important;
    flex: 0 0 auto;
}
.records-split-left {
    flex: 1 1 64%;
    min-width: 0;
    height: 100%;
    display: flex;
    flex-direction: column;
    min-height: 0;
}
.records-split-right {
    flex: 0 0 36%;
    max-width: 480px;
    min-width: 340px;
    height: 100%;
    display: flex;
    flex-direction: column;
    min-height: 0;
    position: sticky;
    top: 14px;
}

@media (max-width: 1100px) {
    .records-split-panel {
        flex-direction: column;
        overflow-y: auto;
    }
    .records-split-left,
    .records-split-right {
        flex: 1 1 100%;
        max-width: 100%;
        min-width: 100%;
        height: auto;
    }
}
</style>

<div class="workspace workspace-fluid records-workspace">
    <!-- ── 1. UNIFIED COMMAND HEADER: TITLE + STATS + ACTION BUTTONS ── -->
    <div class="page-header-bar" style="display: flex; justify-content: space-between; align-items: center; padding-bottom: 8px; border-bottom: 1px solid var(--border); flex-wrap: wrap; gap: 10px; margin-bottom: 6px;">
        <div style="display: flex; align-items: center; gap: 14px;">
            <div class="page-header-icon" style="width: 38px; height: 38px; border-radius: 9px; background: rgba(201,168,76,0.14); color: var(--accent); display: flex; align-items: center; justify-content: center; font-size: 17px; flex-shrink: 0;">
                <i class="fa-solid fa-book-bible"></i>
            </div>
            <div>
                <h1 class="page-title" style="font-family: var(--font-family, 'Inter', sans-serif); font-size: var(--font-page-title, 20px); font-weight: 700; color: var(--text); margin: 0; line-height: 1.25; word-break: break-word;">
                    <?php 
                    if ($scope === 'cathedral') {
                        echo 'Records';
                    } else {
                        $chapelHeading = $assigned_chapel_name ? trim($assigned_chapel_name) : 'Chapel';
                        if (!str_ends_with(strtolower($chapelHeading), 'records')) {
                            $chapelHeading .= ' Records';
                        }
                        echo htmlspecialchars($chapelHeading);
                    }
                    ?>
                </h1>
                <p class="page-subtitle" style="font-size: var(--font-page-subtitle, 14px); color: var(--text2); margin: 3px 0 0 0;">
                    Sacramental registries, certifications, and parish records
                </p>
            </div>
        </div>

        <!-- Right Side: Stat Badges (always top-right) -->
        <div style="display: flex; align-items: center; gap: 6px;">
            <span class="badge" style="background: var(--bg3, #F4F4F5); border: 1px solid var(--border); color: var(--text2); font-size: 10.5px; font-weight: 600; padding: 2px 10px; border-radius: 999px;">
                <?php echo number_format($local_stats['total'] ?? $total_records ?? 0); ?> Records
            </span>
        </div>
    </div>

    <!-- ── 2. SUB-ROW: SEGMENTED SACRAMENT TABS + NEW RECORD ACTION ── -->
    <div class="records-subnav-bar" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; margin-bottom: 8px;">
        <!-- Left: Segmented Sacrament Filter Tabs -->
        <div class="records-tabs-group" style="display: inline-flex; align-items: center; gap: 4px; background: var(--bg3, #F4F4F5); padding: 3px; border-radius: 999px; border: 1px solid var(--border); flex-wrap: wrap;">
            <a href="dashboard.php?section=<?php echo $active_section . $combinedParams; ?>" 
               class="records-tab-link <?php echo $isAllActive ? 'active' : ''; ?>">
                All (<?php echo number_format($local_stats['total'] ?? $total_records ?? 0); ?>)
            </a>
            <?php foreach ($typesList as $tKey => $tData): 
                $isActive = ($curType === $tKey);
                if ($tData['val'] > 0 || $isActive):
            ?>
            <a href="dashboard.php?section=<?php echo $active_section; ?>&record_type=<?php echo $tKey . $combinedParams; ?>" 
               class="records-tab-link <?php echo $isActive ? 'active' : ''; ?>">
                <?php echo $tData['label']; ?> (<?php echo $tData['val']; ?>)
            </a>
            <?php endif; endforeach; ?>
        </div>

        <!-- Right: Primary Actions -->
        <div style="display: flex; align-items: center; gap: 8px;">
            <?php 
            $canSched = ($scope === 'cathedral') ? ($can_cathedral_sched ?? false) : ($can_chapel_sched ?? false);
            $schedSec = ($scope === 'cathedral') ? 'schedule_cathedral' : 'schedule_chapel';
            if ($canSched): 
            ?>
            <a href="dashboard.php?section=<?php echo $schedSec; ?>" onclick="navigateToSection('<?php echo $schedSec; ?>'); return false;" class="btn btn-ghost btn-sm" style="border: 1px solid var(--border); background: var(--surface, #FFFFFF); border-radius: 999px; height: 34px; padding: 0 14px; font-size: 12px; font-weight: 600; display: inline-flex; align-items: center; gap: 6px; text-decoration: none;" title="Switch to Liturgical Schedules">
                <i class="fa-solid fa-calendar-check" style="color: var(--accent);"></i> Schedules
            </a>
            <?php endif; ?>

            <button class="btn btn-primary btn-sm" 
                    data-scope="<?php echo htmlspecialchars($scope, ENT_QUOTES, 'UTF-8'); ?>" 
                    data-type="<?php echo htmlspecialchars($rec_filters['record_type'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                    onclick="handleNewRecordAction(this)" 
                    style="height: 34px; font-size: 12px; font-weight: 600; display: inline-flex; align-items: center; gap: 6px; background: #111111; color: #FFFFFF; border: 1px solid #111111; border-radius: 999px; padding: 0 16px; cursor: pointer; box-shadow: 0 1px 3px rgba(0,0,0,0.12);">
                <i class="fa-solid fa-plus text-gold" style="font-size: 11px;"></i> New Record
            </button>
        </div>
    </div>

    <!-- ── 3. MAIN CONTENT: LIST + INSPECTOR SPLIT ── -->
    <div class="records-split-panel">
        <!-- Left: Records Table Panel -->
        <div class="records-split-left">
            <div class="panel" style="flex: 1; display: flex; flex-direction: column; min-height: 0; border-radius: 12px; overflow: hidden;">
                <!-- Panel Header: Title + Result Counts -->
                <div class="panel-header" style="padding: 10px 16px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border); background: var(--bg2);">
                    <div class="panel-title" style="display: flex; align-items: center; gap: 8px;">
                        <i class="fa-solid fa-list-check" style="color: var(--accent, #B8913A);"></i>
                        <span style="font-weight: 700;">Sacramental Registry</span>
                    </div>
                    <span style="font-size: 12px; color: var(--text2); font-weight: 500;">
                        Showing page <?php echo $current_page; ?> of <?php echo max(1, $total_pages); ?> (<?php echo number_format($total_records ?? 0); ?> records)
                    </span>
                </div>

                <!-- Dedicated Integrated Filter Toolbar -->
                <div class="records-filter-toolbar" style="padding: 9px 14px; background: var(--surface); border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; gap: 8px; flex-wrap: wrap;">
                    <!-- Left: Search Box -->
                    <form method="GET" style="display: flex; align-items: center; gap: 8px; flex: 1 1 180px; min-width: 160px; margin: 0;" id="records-filter-form">
                        <input type="hidden" name="section" value="<?php echo $active_section; ?>">
                        <?php if (!empty($curType)): ?>
                        <input type="hidden" name="record_type" value="<?php echo htmlspecialchars($curType); ?>">
                        <?php endif; ?>
                        <?php if ($selected_chapel_id !== null && $selected_chapel_id !== ''): ?>
                        <input type="hidden" name="chapel_id" value="<?php echo (int)$selected_chapel_id; ?>">
                        <?php endif; ?>
                        <input type="hidden" name="timeframe" id="filter-timeframe-val" value="<?php echo htmlspecialchars($curTimeframe); ?>">
                        <input type="hidden" name="priest" id="filter-priest-val" value="<?php echo htmlspecialchars($curPriest); ?>">
                        <input type="hidden" name="rec_sort" id="sort-records-val" value="<?php echo htmlspecialchars($curSort); ?>">

                        <div style="position: relative; width: 100%;">
                            <i class="fa-solid fa-magnifying-glass" style="position: absolute; left: 11px; top: 50%; transform: translateY(-50%); font-size: 12px; color: var(--text2); pointer-events: none; z-index: 2;"></i>
                            <input type="text" name="search" data-ac-mode="server" data-ac-scope="<?php echo ($scope === 'cathedral') ? 'records_cathedral' : 'records_chapel'; ?>" data-ac-record-type="<?php echo htmlspecialchars($curType); ?>" placeholder="Search name, parents, ref ID..." 
                                   value="<?php echo htmlspecialchars($searchQuery); ?>"
                                   style="width: 100%; height: 34px; padding-left: 32px !important; padding-right: <?php echo !empty($searchQuery) ? '26px' : '10px'; ?>; background: var(--bg2); border: 1px solid var(--border); border-radius: 8px; color: var(--text); font-size: 12.5px; box-sizing: border-box; outline: none; transition: border-color 0.15s ease;">
                            <?php if (!empty($searchQuery)): ?>
                            <a href="dashboard.php?section=<?php echo $active_section; ?><?php echo !empty($curType) ? '&record_type=' . urlencode($curType) : ''; ?><?php echo ($selected_chapel_id !== null && $selected_chapel_id !== '') ? '&chapel_id=' . (int)$selected_chapel_id : ''; ?><?php echo !empty($curSort) ? '&rec_sort=' . urlencode($curSort) : ''; ?><?php echo !empty($curTimeframe) ? '&timeframe=' . urlencode($curTimeframe) : ''; ?><?php echo !empty($curPriest) ? '&priest=' . urlencode($curPriest) : ''; ?>" 
                               title="Clear search" 
                               style="position: absolute; right: 8px; top: 50%; transform: translateY(-50%); color: var(--text2); text-decoration: none; font-size: 14px; line-height: 1; padding: 2px;">&times;</a>
                            <?php endif; ?>
                        </div>
                    </form>

                    <!-- Right: Dropdown Filters & Sort -->
                    <div style="display: flex; align-items: center; gap: 6px; flex-wrap: wrap; flex: 0 0 auto;">
                        <?php if (in_array($role, ['Admin', 'Cathedral Admin']) && !empty($chapels)): ?>
                        <select id="chapel-records-filter-select"
                                onchange="window.location.href='dashboard.php?section=<?php echo $active_section; ?>' + (this.value !== '' ? '&chapel_id=' + this.value : '') + '<?php echo !empty($curType) ? '&record_type=' . urlencode($curType) : ''; ?><?php echo !empty($searchQuery) ? '&search=' . urlencode($searchQuery) : ''; ?><?php echo !empty($curSort) ? '&rec_sort=' . urlencode($curSort) : ''; ?><?php echo !empty($curTimeframe) ? '&timeframe=' . urlencode($curTimeframe) : ''; ?><?php echo !empty($curPriest) ? '&priest=' . urlencode($curPriest) : ''; ?>'"
                                class="records-chapel-select"
                                style="height: 34px; padding: 0 10px; background: var(--bg2); border: 1px solid var(--border); border-radius: 8px; color: var(--text); font-size: 12px; font-weight: 500; cursor: pointer; outline: none;">
                            <?php if ($scope === 'cathedral'): ?>
                            <option value="">All Branches (Centralized)</option>
                            <option value="0" <?php echo ($selected_chapel_id === 0) ? 'selected' : ''; ?>>Main Cathedral Only</option>
                            <?php else: ?>
                            <option value="">All Chapels (<?php echo count($chapels); ?>)</option>
                            <?php endif; ?>
                            <?php foreach ($chapels as $c): ?>
                            <option value="<?php echo (int)$c['chapel_id']; ?>" <?php echo ($selected_chapel_id !== null && (int)$selected_chapel_id === (int)$c['chapel_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($c['chapel_name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <?php endif; ?>

                        <!-- Timeframe Filter -->
                        <select onchange="document.getElementById('filter-timeframe-val').value = this.value; document.getElementById('records-filter-form').submit();"
                                style="height: 34px; padding: 0 10px; background: var(--bg2); border: 1px solid var(--border); border-radius: 8px; color: var(--text); font-size: 12px; font-weight: 500; cursor: pointer; outline: none; width: auto !important; max-width: 145px;"
                                title="Filter records by date">
                            <option value="">All Dates</option>
                            <option value="recently_added" <?php echo ($curTimeframe === 'recently_added') ? 'selected' : ''; ?>>Recently Added (30 Days)</option>
                            <option value="recently_added_7" <?php echo ($curTimeframe === 'recently_added_7') ? 'selected' : ''; ?>>Recently Added (7 Days)</option>
                            <option value="recently_modified" <?php echo ($curTimeframe === 'recently_modified') ? 'selected' : ''; ?>>Recently Modified (30 Days)</option>
                            <option value="this_week" <?php echo ($curTimeframe === 'this_week') ? 'selected' : ''; ?>>This Week</option>
                            <option value="next_week" <?php echo ($curTimeframe === 'next_week') ? 'selected' : ''; ?>>Next Week</option>
                            <option value="this_month" <?php echo ($curTimeframe === 'this_month') ? 'selected' : ''; ?>>This Month</option>
                            <option value="this_year" <?php echo ($curTimeframe === 'this_year') ? 'selected' : ''; ?>>This Year</option>
                        </select>

                        <!-- Pastor Filter -->
                        <select onchange="document.getElementById('filter-priest-val').value = this.value; document.getElementById('records-filter-form').submit();"
                                style="height: 34px; padding: 0 10px; background: var(--bg2); border: 1px solid var(--border); border-radius: 8px; color: var(--text); font-size: 12px; font-weight: 500; cursor: pointer; outline: none; max-width: 135px;"
                                title="Filter records by officiating priest">
                            <option value="">All Priests</option>
                            <?php foreach (($priests ?? []) as $pr): 
                                $pName = !empty($pr['full_name']) ? $pr['full_name'] : $pr['user'];
                                $shortName = preg_replace('/^(Fr\.|Father|Rev\.|Pastor|Msgr\.)\s+/i', '', $pName);
                            ?>
                            <option value="<?php echo htmlspecialchars($shortName); ?>" <?php echo (strtolower(trim($curPriest)) === strtolower(trim($shortName))) ? 'selected' : ''; ?>>
                                Fr. <?php echo htmlspecialchars($shortName); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>

                        <!-- Sort Toggle Button -->
                        <button type="button" class="btn-sort-toggle" onclick="toggleSortDirection(this, 'sort-records-val')"
                                title="<?php echo ($curSort === 'date_asc') ? 'Sorted: Oldest First (Click for Newest)' : 'Sorted: Newest First (Click for Oldest)'; ?>"
                                style="height: 34px; width: 34px; min-width: 34px; padding: 0; display: inline-flex; align-items: center; justify-content: center; border-radius: 8px; border: 1px solid var(--border); background: var(--bg2); color: var(--text); cursor: pointer; transition: all 0.15s ease;" aria-label="Toggle sort order">
                            <i class="fa-solid <?php echo ($curSort === 'date_asc') ? 'fa-arrow-up-wide-short' : 'fa-arrow-down-wide-short'; ?>" style="font-size: 12px;"></i>
                        </button>

                        <!-- Reset Filters Button -->
                        <?php 
                        $hasActiveFilters = !empty($searchQuery) || !empty($curTimeframe) || !empty($curPriest) || ($selected_chapel_id !== null && $selected_chapel_id !== '') || ($curSort !== 'date_desc');
                        if ($hasActiveFilters): 
                        ?>
                        <a href="dashboard.php?section=<?php echo $active_section; ?><?php echo !empty($curType) ? '&record_type=' . urlencode($curType) : ''; ?>" 
                           class="btn btn-ghost btn-sm" 
                           title="Reset all filters"
                           style="height: 34px; padding: 0 10px; font-size: 11.5px; border-radius: 8px; border: 1px solid var(--border); background: var(--bg2); color: var(--text2); display: inline-flex; align-items: center; gap: 5px; text-decoration: none;">
                            <i class="fa-solid fa-rotate-left"></i> Reset
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="panel-body" style="padding: 0 !important; overflow-y: auto; flex: 1; min-height: 0;">
                    <?php if (empty($records)): ?>
                        <div class="table-empty-state" style="padding: 56px 20px; text-align: center; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 10px;">
                            <div style="width: 48px; height: 48px; border-radius: 50%; background: var(--bg3); display: flex; align-items: center; justify-content: center; color: var(--accent); font-size: 18px; border: 1px solid var(--border);">
                                <i class="fa-solid fa-folder-open"></i>
                            </div>
                            <div>
                                <div style="font-size: 15px; font-weight: 600; color: var(--text);">No Records Found</div>
                                <div style="font-size: 12.5px; color: var(--text2); margin-top: 4px; max-width: 320px;">
                                    <?php echo $hasActiveFilters ? 'No records match your active filters or search term.' : 'There are no sacramental records in this category yet.'; ?>
                                </div>
                            </div>
                            <?php if ($hasActiveFilters): ?>
                            <a href="dashboard.php?section=<?php echo $active_section; ?><?php echo !empty($curType) ? '&record_type=' . urlencode($curType) : ''; ?>" class="btn btn-ghost btn-sm" style="margin-top: 4px; height: 32px; padding: 0 14px; font-size: 12px; border-radius: 8px; border: 1px solid var(--border); display: inline-flex; align-items: center; gap: 6px; text-decoration: none;">
                                <i class="fa-solid fa-rotate-left"></i> Reset Filters
                            </a>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                    <div class="table-container" style="border: none; margin: 0; width: 100%;">
                        <table class="data-table" style="width: 100%; border-collapse: collapse;">
                            <thead>
                                <tr style="background: var(--bg3, #F8FAFC); border-bottom: 1px solid var(--border);">
                                    <th style="min-width: 170px; text-align: left; padding: 10px 16px; background: var(--bg3, #F8FAFC);">Parishioner</th>
                                    <th style="width: 130px; text-align: center; padding: 10px 8px; background: var(--bg3, #F8FAFC);">Sacrament</th>
                                    <th style="width: 110px; text-align: left; padding: 10px 12px; background: var(--bg3, #F8FAFC);">Date</th>
                                    <th style="min-width: 220px; text-align: left; padding: 10px 14px; background: var(--bg3, #F8FAFC);">Officiating & Venue</th>
                                    <th style="width: 70px; text-align: center; padding: 10px 10px; background: var(--bg3, #F8FAFC);">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($records as $index => $rec): ?>
                                <tr onclick="showRecordDetails(this)" 
                                    data-record="<?php echo htmlspecialchars(json_encode($rec, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>"
                                    style="cursor:pointer;" 
                                    class="record-row" 
                                    id="rec-row-<?php echo $rec['record_id']; ?>"
                                    title="Click to inspect record">
                                    <td style="padding: 11px 16px; white-space: normal;">
                                        <div style="font-weight: 600; font-size: 13.5px; color: var(--text); line-height: 1.3; overflow-wrap: break-word;">
                                            <?php echo htmlspecialchars($rec['person_name']); ?>
                                        </div>
                                        <div style="font-size: 11px; color: var(--text2); margin-top: 3px; font-family: var(--font-mono, monospace); display: flex; align-items: center; gap: 4px;">
                                            <span style="color: var(--accent); font-weight: 700;">REF:</span> <?php echo htmlspecialchars($rec['reference_id']); ?>
                                        </div>
                                    </td>
                                    <td style="text-align: center; white-space: nowrap; padding: 11px 8px;">
                                        <?php echo renderSacramentBadge($rec['record_type']); ?>
                                    </td>
                                    <td style="font-size: 12.5px; color: var(--text); white-space: nowrap; padding: 11px 12px;">
                                        <i class="fa-regular fa-calendar" style="color: var(--accent); margin-right: 5px;"></i>
                                        <?php echo date('M d, Y', strtotime($rec['event_date'])); ?>
                                    </td>
                                    <td style="padding: 11px 14px; white-space: normal;">
                                        <div style="font-size: 12.5px; color: var(--text); line-height: 1.3; font-weight: 500; overflow-wrap: break-word;">
                                            <?php 
                                             $pName = trim($rec['officiating_priest'] ?? '');
                                            if (!empty($pName)) {
                                                echo preg_match('/^(Fr\.|Rev\.|Father|Msgr\.)/i', $pName) ? htmlspecialchars($pName) : 'Fr. ' . htmlspecialchars($pName);
                                            } else {
                                                echo '<span style="color: #737373;">—</span>';
                                            }
                                            ?>
                                        </div>
                                        <?php if (!empty($rec['chapel_name']) && $rec['chapel_name'] !== 'Main Cathedral'): ?>
                                        <div style="font-size: 11.5px; color: var(--text2); margin-top: 3px; word-break: break-word; line-height: 1.35;">
                                            <i class="fa-solid fa-place-of-worship" style="font-size: 10px; color: var(--accent); margin-right: 3px;"></i>
                                            <span><?php echo htmlspecialchars($rec['chapel_name']); ?></span>
                                        </div>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align: center; padding: 12px 10px;" onclick="event.stopPropagation();">
                                        <?php if ($can_edit): ?>
                                        <button type="button" 
                                                class="bulletin-btn-edit" 
                                                style="width: 32px; height: 32px; border-radius: 8px; border: 1px solid var(--border); background: transparent; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; transition: all 0.15s ease;"
                                                data-record="<?php echo htmlspecialchars(json_encode($rec, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>"
                                                onclick="openOfficialRecordEdit(this, '<?php echo $scope; ?>')" 
                                                title="Edit Record">
                                            <i class="fa-solid fa-pen text-gold"></i>
                                        </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>

                <?php if ($total_pages > 1): ?>
                <div class="panel-footer" style="display: flex; justify-content: space-between; align-items: center; padding: 8px 16px; border-top: 1px solid var(--border);">
                    <div>
                        <?php if ($current_page > 1): ?>
                        <a href="dashboard.php?section=<?php echo $active_section; ?>&rec_page=<?php echo $current_page - 1; ?><?php echo $combinedParams; ?>&record_type=<?php echo urlencode($rec_filters['record_type'] ?? ''); ?>" 
                           class="btn-sm btn-ghost" style="padding: 4px 10px; border-radius: 6px; border: 1px solid var(--border); text-decoration: none; color: var(--text);">
                            <i class="fa-solid fa-arrow-left"></i> Prev
                        </a>
                        <?php endif; ?>
                    </div>
                    <span style="font-size: 12px; color: var(--text2);">Page <?php echo $current_page; ?> of <?php echo $total_pages; ?></span>
                    <div>
                        <?php if ($current_page < $total_pages): ?>
                        <a href="dashboard.php?section=<?php echo $active_section; ?>&rec_page=<?php echo $current_page + 1; ?><?php echo $combinedParams; ?>&record_type=<?php echo urlencode($rec_filters['record_type'] ?? ''); ?>" 
                           class="btn-sm btn-ghost" style="padding: 4px 10px; border-radius: 6px; border: 1px solid var(--border); text-decoration: none; color: var(--text);">
                            Next <i class="fa-solid fa-arrow-right"></i>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Right: Inspector Side Panel -->
        <div class="records-split-right">
            <div class="panel" style="flex: 1; display: flex; flex-direction: column; min-height: 0; border-radius: 12px;">
                <div class="panel-header" style="padding: 10px 16px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center;">
                    <div class="panel-title" style="display: flex; align-items: center; gap: 8px;">
                        <i class="fa-solid fa-file-invoice" style="color: var(--accent, #B8913A);"></i>
                        <span style="font-weight: 700;">Record Inspector</span>
                    </div>
                    <span style="font-size: 11px; color: var(--text2); font-weight: 500;">
                        Parish Registry
                    </span>
                </div>
                <div class="panel-body" style="padding: 14px !important; display: flex; flex-direction: column; gap: 12px; flex: 1; overflow-y: auto;" id="workspace-record-inspector">
                    <div class="empty-state" style="padding: 40px 16px; text-align: center;">
                        <i class="fa-solid fa-book-bible" style="font-size: 36px; opacity: 0.25; margin-bottom: 12px; display: block; color: var(--accent);"></i>
                        <h3 style="font-size: 0.95rem; font-weight: 600; color: var(--text); margin-bottom: 4px;">Select a Record to Inspect</h3>
                        <p style="font-size: 0.8rem; color: var(--text2); margin: 0 auto; max-width: 260px;">Click on any record in the registry to view details, print certificates, or edit data.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Table Row Highlighting & Interactive Styling */
.record-row {
    transition: background 0.15s ease, border-color 0.15s ease;
    border-left: 3px solid transparent;
}
.record-row:hover {
    background: var(--hover-bg, #F6F6F6) !important;
}
.record-row.active-row {
    background: var(--hover-bg, #F6F6F6) !important;
    border-left: 3px solid var(--accent, #111111) !important;
}
[data-theme="dark"] .record-row:hover,
[data-theme="dark"] .record-row.active-row {
    background: #1e293b !important;
    border-left-color: var(--accent) !important;
}
.btn-table-action {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    border-radius: 6px;
    background: var(--panel-bg, #FFFFFF);
    border: 1px solid var(--border, #E5E5E5);
    color: var(--text2, #737373);
    cursor: pointer;
    transition: all 0.15s ease;
    font-size: 11px;
}
.btn-table-action:hover {
    background: var(--accent, #111111);
    color: #111111;
    border-color: var(--accent, #111111);
}
.btn-table-action.btn-edit-action:hover {
    background: var(--accent, #111111);
    color: #111111;
    border-color: var(--accent, #111111);
}
[data-theme="dark"] .btn-table-action {
    background: #1e293b !important;
    border-color: var(--border) !important;
    color: var(--text) !important;
}
[data-theme="dark"] .btn-table-action:hover {
    background: var(--accent) !important;
    color: #111111 !important;
    border-color: var(--accent) !important;
}

/* Inspector Card Styling */
.inspector-card {
    background: var(--panel-bg, #FFFFFF);
    border: 1px solid var(--border, #E5E5E5);
    border-radius: 10px;
    padding: 16px;
    display: flex;
    flex-direction: column;
    gap: 12px;
    box-shadow: none;
    position: relative;
    color: var(--text, #111111);
}
.inspector-header {
    border-bottom: 1px solid var(--border, #E5E5E5);
    padding-bottom: 8px;
}
.inspector-seal {
    font-family: var(--font-family);
    font-size: 0.72rem;
    font-weight: 600;
    color: var(--text2, #737373);
    letter-spacing: 0.08em;
    text-transform: uppercase;
}
.inspector-title {
    font-family: var(--font-family);
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--text, #111111);
    margin: 4px 0 6px 0;
}
.inspector-ref-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 10.5px;
    color: var(--text2, #737373);
    font-family: var(--font-family);
    background: var(--hover-bg, #F6F6F6);
    padding: 2px 10px;
    border-radius: 6px;
    border: 1px solid var(--border, #E5E5E5);
}
.inspector-hero {
    background: var(--hover-bg, #F6F6F6);
    border: 1px solid var(--border, #E5E5E5);
    border-radius: 8px;
    padding: 12px 14px;
}
.inspector-hero-name {
    font-size: 1.15rem;
    font-weight: 600;
    color: var(--text, #111111);
    margin-bottom: 6px;
    line-height: 1.3;
    word-break: break-word;
}
.inspector-meta-row {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    font-size: 0.8rem;
    color: var(--text2, #737373);
}
.inspector-meta-item {
    display: inline-flex;
    align-items: center;
    gap: 5px;
}
.inspector-grid-2 {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px;
}
.inspector-mini-card {
    background: var(--panel-bg, #FFFFFF);
    border: 1px solid var(--border, #E5E5E5);
    border-radius: 6px;
    padding: 8px 10px;
}
.inspector-mini-label {
    font-size: 0.65rem;
    font-weight: 600;
    text-transform: uppercase;
    color: var(--text2, #737373);
    letter-spacing: 0.04em;
    margin-bottom: 2px;
}
.inspector-mini-val {
    font-size: 0.88rem;
    font-weight: 600;
    color: var(--text, #111111);
    word-break: break-word;
}
.inspector-section-title {
    font-size: 0.72rem;
    font-weight: 600;
    text-transform: uppercase;
    color: var(--text, #111111);
    letter-spacing: 0.04em;
    margin: 4px 0 2px 0;
    display: flex;
    align-items: center;
    gap: 6px;
}
.inspector-field-row {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    font-size: 0.8rem;
    padding: 5px 0;
    border-bottom: 1px solid var(--border, #E5E5E5);
}
.inspector-field-label {
    color: var(--text2, #737373);
    font-size: 0.72rem;
    font-weight: 500;
    text-transform: uppercase;
}
.inspector-field-val {
    color: #111111;
    font-weight: 500;
    text-align: right;
    max-width: 65%;
    word-break: break-word;
}
.inspector-notes-box {
    background: #F6F6F6;
    border: 1px solid #E5E5E5;
    border-radius: 6px;
    padding: 8px 12px;
    font-size: 0.8rem;
    color: #111111;
    line-height: 1.4;
    word-break: break-word;
}
.inspector-actions {
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin-top: 6px;
}
.btn-edit-primary {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    width: 100%;
    padding: 9px 14px;
    background: #111111;
    color: #FFFFFF;
    font-weight: 600;
    font-size: 12px;
    border: 1px solid #111111;
    border-radius: 6px;
    cursor: pointer;
    transition: opacity 0.15s ease;
}
.btn-edit-primary:hover {
    opacity: 0.9;
}
.canonical-intake-card {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 12px 14px;
    border-radius: 8px;
    border: 1px solid var(--border, #E5E5E5);
    background: var(--bg-surface, var(--card-bg, #FFFFFF));
    text-decoration: none;
    color: var(--text, #111111);
    cursor: pointer;
    transition: all 0.15s ease;
}
.canonical-intake-card:hover {
    background: var(--hover-bg, #F6F6F6) !important;
    border-color: var(--accent, #C5A059) !important;
    transform: translateX(2px);
}
.canonical-intake-card .intake-icon {
    width: 36px;
    height: 36px;
    border-radius: 6px;
    background: var(--hover-bg, #F6F6F6);
    color: var(--text, #111111);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 15px;
    flex-shrink: 0;
    border: 1px solid var(--border, #E5E5E5);
    transition: all 0.15s ease;
}
.canonical-intake-card:hover .intake-icon {
    background: var(--accent-glow, rgba(197, 160, 89, 0.15));
    color: var(--accent, #C5A059);
    border-color: var(--accent, #C5A059);
}
.canonical-intake-card .intake-title {
    font-weight: 600;
    font-size: 13px;
    color: var(--text, #111111);
}
.canonical-intake-card .intake-desc {
    font-size: 11px;
    color: var(--text2, #737373);
    margin-top: 2px;
}
.canonical-intake-card .intake-arrow {
    color: var(--text2, #737373);
    font-size: 11px;
    transition: all 0.15s ease;
}
.canonical-intake-card:hover .intake-arrow {
    color: var(--accent, #C5A059);
    transform: translateX(3px);
}
</style>

<!-- Modal: Select Official Intake Form -->
<div class="modal-overlay" id="modal-select-official-form">
    <div class="modal" style="max-width: 600px; border-radius: 12px; overflow: hidden;">
        <div class="modal-header" style="padding: 16px 20px;">
            <div style="display: flex; align-items: center; gap: 12px;">
                <div style="width: 36px; height: 36px; border-radius: 6px; background: var(--hover-bg, #F6F6F6); color: var(--text, #111111); display: flex; align-items: center; justify-content: center; font-size: 16px; border: 1px solid var(--border, #E5E5E5);">
                    <i class="fa-solid fa-scroll"></i>
                </div>
                <div>
                    <div class="modal-title" style="font-size: 15px; font-weight: 600; font-family: var(--font-family); color: var(--text, #111111); margin: 0; line-height: 1.2;">New Record</div>
                    <div style="font-size: 11px; color: var(--text2, #737373); font-weight: 400; margin-top: 2px;">Select an intake form to record directly into parish archives</div>
                </div>
            </div>
            <button class="modal-close" onclick="closeModal('modal-select-official-form')">×</button>
        </div>
        <div class="modal-body" style="padding: 18px 20px; display: flex; flex-direction: column; gap: 10px;">
            <!-- Baptismal Form Card -->
            <a href="baptism_form.php?scope=<?php echo $scope; ?><?php echo !empty($selected_chapel_id) ? '&chapel_id=' . (int)$selected_chapel_id : ''; ?>" class="canonical-intake-card">
                <div class="intake-icon">
                    <i class="fa-solid fa-water"></i>
                </div>
                <div style="flex: 1;">
                    <div class="intake-title">Baptismal Register Form</div>
                    <div class="intake-desc">Sacrament of Holy Baptism · Parentage, filiation, sponsors &amp; live certificate preview</div>
                </div>
                <i class="fa-solid fa-arrow-right intake-arrow"></i>
            </a>

            <!-- Confirmation Form Card -->
            <a href="confirmation_form.php?scope=<?php echo $scope; ?><?php echo !empty($selected_chapel_id) ? '&chapel_id=' . (int)$selected_chapel_id : ''; ?>" class="canonical-intake-card">
                <div class="intake-icon">
                    <i class="fa-solid fa-dove"></i>
                </div>
                <div style="flex: 1;">
                    <div class="intake-title">Confirmation Register Form</div>
                    <div class="intake-desc">Confirmation Registry · Sponsors, baptismal reference &amp; registry folio</div>
                </div>
                <i class="fa-solid fa-arrow-right intake-arrow"></i>
            </a>

            <!-- Marriage Form Card -->
            <a href="marriage_form.php?scope=<?php echo $scope; ?><?php echo !empty($selected_chapel_id) ? '&chapel_id=' . (int)$selected_chapel_id : ''; ?>" class="canonical-intake-card">
                <div class="intake-icon">
                    <i class="fa-solid fa-ring"></i>
                </div>
                <div style="flex: 1;">
                    <div class="intake-title">Marriage Register Application (Form 90)</div>
                    <div class="intake-desc">Municipal Form 90 · Contracting parties, parental consents &amp; marriage register</div>
                </div>
                <i class="fa-solid fa-arrow-right intake-arrow"></i>
            </a>

            <!-- Funeral Form Card -->
            <a href="funeral_form.php?scope=<?php echo $scope; ?><?php echo !empty($selected_chapel_id) ? '&chapel_id=' . (int)$selected_chapel_id : ''; ?>" class="canonical-intake-card">
                <div class="intake-icon">
                    <i class="fa-solid fa-cross"></i>
                </div>
                <div style="flex: 1;">
                    <div class="intake-title">Funeral &amp; Burial Register Form</div>
                    <div class="intake-desc">Christian Burial Archives · Deceased details, interment &amp; live certificate preview</div>
                </div>
                <i class="fa-solid fa-arrow-right intake-arrow"></i>
            </a>

            <!-- First Communion Modal Card -->
            <a href="javascript:void(0)" onclick="closeModal('modal-select-official-form'); openCommunionAddModal('<?php echo $scope; ?>');" class="canonical-intake-card">
                <div class="intake-icon">
                    <i class="fa-solid fa-wine-glass"></i>
                </div>
                <div style="flex: 1;">
                    <div class="intake-title">First Holy Communion Record</div>
                    <div class="intake-desc">Communion Registry · Communicant details, parents, officiating priest &amp; registry folio</div>
                </div>
                <i class="fa-solid fa-arrow-right intake-arrow"></i>
            </a>
        </div>
        <div class="modal-footer" style="padding: 12px 20px; display: flex; justify-content: flex-end;">
            <button type="button" class="btn btn-sm btn-ghost" onclick="closeModal('modal-select-official-form')">Cancel</button>
        </div>
    </div>
</div>

<script>
window.sacramentalCsrf = '<?php echo $csrf; ?>';
window.userRolePermissions = {
    can_delete: <?php echo $can_delete ? 'true' : 'false'; ?>,
    can_edit: <?php echo $can_edit ? 'true' : 'false'; ?>
};
window.recordsScope = '<?php echo $scope; ?>';
var currentSelectedRecord = null;

function escapeHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function getSacramentBadgeHtml(type) {
    const iconMap = {
        'Baptism': 'fa-water',
        'Confirmation': 'fa-dove',
        'Wedding': 'fa-ring',
        'Funeral': 'fa-cross',
        'Communion': 'fa-wine-glass'
    };
    const icon = iconMap[type] || 'fa-scroll';
    return `<span class="sacrament-badge" style="background: var(--bg3, #F4F4F5); color: var(--text, #111111); border: 1px solid var(--border, #E5E5E5); font-weight: 600; font-size: 11px; letter-spacing: 0.04em; padding: 3px 10px; border-radius: 6px; display: inline-flex; align-items: center; justify-content: center; gap: 5px; text-transform: uppercase; white-space: nowrap;"><i class="fa-solid ${icon}" style="font-size: 9.5px; opacity: 0.65;"></i> ${escapeHtml(type)}</span>`;
}

function showRecordDetails(rec, rowElement) {
    if (rec instanceof HTMLElement) {
        rowElement = rec;
        const raw = rowElement.getAttribute('data-record');
        if (raw) {
            try { rec = JSON.parse(raw); } catch (e) { console.error('Error parsing record data:', e); return; }
        }
    }
    currentSelectedRecord = rec;
    
    // Highlight selected row in table
    document.querySelectorAll('.record-row').forEach(row => row.classList.remove('active-row'));
    if (rowElement) {
        rowElement.classList.add('active-row');
    }

    const inspector = document.getElementById('workspace-record-inspector');
    if (!inspector) return;

    // Date formatting helper
    const formatDate = (dateStr) => {
        if (!dateStr) return '—';
        const d = new Date(dateStr.replace(/-/g, '/'));
        return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
    };

    const certUrl = `certificates/sacramental_certificate.php?record_id=${rec.record_id}&preview=1`;

    // Action: Edit Record
    let editBtn = '';
    if (userRolePermissions.can_edit) {
        editBtn = `
            <button type="button" class="btn-edit-primary" onclick="openOfficialRecordEdit(currentSelectedRecord, recordsScope)" style="height: 36px; padding: 0 10px; font-size: 12px; font-weight: 700; display: inline-flex; align-items: center; justify-content: center; gap: 6px; background: var(--accent, #B8913A); color: #111116; border: none; border-radius: 8px; cursor: pointer; text-decoration: none; box-shadow: 0 2px 6px rgba(184, 145, 58, 0.25);">
                <i class="fa-solid fa-pen-to-square"></i> Edit Record
            </button>
        `;
    }

    // Action: Delete Record
    let deleteBtn = '';
    if (userRolePermissions.can_delete) {
        deleteBtn = `
            <form method="POST" style="margin: 4px 0 0; width: 100%; text-align: center;" onsubmit="return confirm('Are you sure you want to permanently delete this record?')">
                <input type="hidden" name="form_section" value="records_${recordsScope}">
                <input type="hidden" name="action" value="delete_record">
                <input type="hidden" name="id" value="${rec.record_id}">
                <input type="hidden" name="csrf" value="${sacramentalCsrf}">
                <button type="submit" style="background: rgba(239,68,68,0.08); border: 1px solid rgba(239,68,68,0.22); color: #EF4444; font-size: 11.5px; font-weight: 600; cursor: pointer; padding: 7px 10px; border-radius: 8px; width: 100%; display: inline-flex; align-items: center; justify-content: center; gap: 6px; transition: all 0.15s ease;">
                    <i class="fa-solid fa-trash-can" style="font-size: 11px;"></i> Delete Record
                </button>
            </form>
        `;
    }

    // Dynamic Sacrament-specific fields
    let specificHtml = '';
    if (rec.record_type === 'Baptism') {
        specificHtml = `
            <div class="inspector-section-title">
                <i class="fa-solid fa-users" style="font-size: 11px; color: #737373;"></i> Parents &amp; Sponsors
            </div>
            <div class="inspector-field-row">
                <span class="inspector-field-label">Father's Name</span>
                <span class="inspector-field-val">${escapeHtml(rec.father_name) || '—'}</span>
            </div>
            <div class="inspector-field-row">
                <span class="inspector-field-label">Mother's Name</span>
                <span class="inspector-field-val">${escapeHtml(rec.mother_name) || '—'}</span>
            </div>
            <div class="inspector-field-row">
                <span class="inspector-field-label">Godparents / Sponsors</span>
                <span class="inspector-field-val">${escapeHtml(rec.sponsor_names || rec.godfather || rec.godmother) || '—'}</span>
            </div>
            ${rec.date_of_birth ? `
            <div class="inspector-field-row">
                <span class="inspector-field-label">Date of Birth</span>
                <span class="inspector-field-val">${formatDate(rec.date_of_birth)}</span>
            </div>` : ''}
            ${rec.place_of_birth ? `
            <div class="inspector-field-row">
                <span class="inspector-field-label">Place of Birth</span>
                <span class="inspector-field-val">${escapeHtml(rec.place_of_birth)}</span>
            </div>` : ''}
        `;
    } else if (rec.record_type === 'Wedding') {
        specificHtml = `
            <div class="inspector-section-title">
                <i class="fa-solid fa-heart" style="font-size: 11px; color: #737373;"></i> Marriage Details &amp; Witnesses
            </div>
            <div class="inspector-field-row">
                <span class="inspector-field-label">Groom</span>
                <span class="inspector-field-val">${escapeHtml(rec.groom_name) || '—'}</span>
            </div>
            <div class="inspector-field-row">
                <span class="inspector-field-label">Bride</span>
                <span class="inspector-field-val">${escapeHtml(rec.bride_name) || '—'}</span>
            </div>
            <div class="inspector-field-row">
                <span class="inspector-field-label">Principal Witnesses</span>
                <span class="inspector-field-val">${escapeHtml(rec.sponsor_names || rec.witness_1 || rec.witness_2) || '—'}</span>
            </div>
        `;
    } else if (rec.record_type === 'Funeral') {
        specificHtml = `
            <div class="inspector-section-title">
                <i class="fa-solid fa-cross" style="font-size: 11px; color: #737373;"></i> Funeral &amp; Burial Details
            </div>
            <div class="inspector-field-row">
                <span class="inspector-field-label">Date of Death</span>
                <span class="inspector-field-val">${formatDate(rec.date_of_death)}</span>
            </div>
            <div class="inspector-field-row">
                <span class="inspector-field-label">Age at Death</span>
                <span class="inspector-field-val">${escapeHtml(rec.age_at_death) ? rec.age_at_death + ' years old' : '—'}</span>
            </div>
            <div class="inspector-field-row">
                <span class="inspector-field-label">Place of Burial</span>
                <span class="inspector-field-val">${escapeHtml(rec.place_of_burial) || '—'}</span>
            </div>
        `;
    } else if (rec.record_type === 'Confirmation') {
        let confDateBaptism = '';
        let confPlaceBaptism = escapeHtml(rec.confirmation_place_of_baptism || '').trim();
        
        let parsedNotesObj = null;
        try {
            if ((rec.notes || '').trim().startsWith('{')) {
                parsedNotesObj = JSON.parse(rec.notes);
            }
        } catch(e) {}

        if (parsedNotesObj && parsedNotesObj.date_of_baptism) {
            confDateBaptism = formatDate(parsedNotesObj.date_of_baptism);
        }

        if (/^\d{4}-\d{2}-\d{2}$/.test(confPlaceBaptism)) {
            if (!confDateBaptism) confDateBaptism = formatDate(confPlaceBaptism);
            confPlaceBaptism = (parsedNotesObj && parsedNotesObj.place_of_baptism) ? escapeHtml(parsedNotesObj.place_of_baptism) : '';
        }

        specificHtml = `
            <div class="inspector-section-title">
                <i class="fa-solid fa-dove" style="font-size: 11px; color: #737373;"></i> Confirmation Details
            </div>
            ${confDateBaptism ? `
            <div class="inspector-field-row">
                <span class="inspector-field-label">Date of Baptism</span>
                <span class="inspector-field-val">${confDateBaptism}</span>
            </div>` : ''}
            <div class="inspector-field-row">
                <span class="inspector-field-label">Place of Baptism</span>
                <span class="inspector-field-val">${confPlaceBaptism || '—'}</span>
            </div>
            <div class="inspector-field-row">
                <span class="inspector-field-label">Sponsors</span>
                <span class="inspector-field-val">${escapeHtml(rec.sponsor_names || rec.godfather || rec.godmother) || '—'}</span>
            </div>
        `;
    } else if (rec.record_type === 'Communion') {
        specificHtml = `
            <div class="inspector-section-title">
                <i class="fa-solid fa-wine-glass" style="font-size: 11px; color: #737373;"></i> First Communion Details
            </div>
            <div class="inspector-field-row">
                <span class="inspector-field-label">Parents</span>
                <span class="inspector-field-val">${[escapeHtml(rec.father_name), escapeHtml(rec.mother_name)].filter(Boolean).join(' &amp; ') || '—'}</span>
            </div>
        `;
    }

    // Notes section
    let notesHtml = '';
    let displayRemarks = (rec.notes || '').trim();
    if (displayRemarks.startsWith('{') && displayRemarks.endsWith('}')) {
        try {
            const parsedNotes = JSON.parse(displayRemarks);
            displayRemarks = (parsedNotes.marginal_notes || parsedNotes.remarks || '').trim();
        } catch(e) {
            displayRemarks = '';
        }
    }
    if (displayRemarks !== '') {
        notesHtml = `
            <div class="inspector-section-title">
                <i class="fa-solid fa-note-sticky" style="font-size: 11px; color: #737373;"></i> Marginal Remarks
            </div>
            <div class="inspector-notes-box">
                ${escapeHtml(displayRemarks)}
            </div>
        `;
    }

    const rawPriest = (rec.officiating_priest || '').trim();
    let displayPriest = '—';
    if (rawPriest) {
        displayPriest = /^(Fr\.|Rev\.|Father|Msgr\.)/i.test(rawPriest) ? escapeHtml(rawPriest) : `Fr. ${escapeHtml(rawPriest)}`;
    }

    inspector.innerHTML = `
        <div class="inspector-card">
            <div class="inspector-header" style="display: flex; justify-content: space-between; align-items: center; padding-bottom: 8px; border-bottom: 1px solid var(--border);">
                ${getSacramentBadgeHtml(rec.record_type)}
                <span class="inspector-ref-badge" style="font-family: var(--font-mono, monospace); font-size: 11px; padding: 2px 8px; border-radius: 6px; background: var(--bg3); border: 1px solid var(--border); color: var(--text);">
                    REF: <strong>${escapeHtml(rec.reference_id)}</strong>
                </span>
            </div>

            <!-- Subject Hero & Key Registry Data -->
            <div class="inspector-hero" style="background: var(--bg3); border: 1px solid var(--border); border-radius: 8px; padding: 12px 14px;">
                <div class="inspector-hero-name" style="font-size: 1.15rem; font-weight: 700; color: var(--text); margin-bottom: 8px; line-height: 1.3; word-break: break-word;">
                    ${escapeHtml(rec.person_name)}
                </div>
                <div style="display: flex; flex-direction: column; gap: 8px; font-size: 11.5px; color: var(--text2);">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px;">
                        <div style="display: flex; align-items: center; gap: 6px;">
                            <i class="fa-regular fa-calendar" style="color: var(--accent); flex-shrink: 0;"></i>
                            <span style="color: var(--text); font-weight: 600;">${formatDate(rec.event_date)}</span>
                        </div>
                        <div style="display: flex; align-items: center; gap: 6px;">
                            <i class="fa-solid fa-book" style="color: var(--accent); flex-shrink: 0;"></i>
                            <span style="color: var(--text);">${escapeHtml(rec.book_number) ? 'Bk ' + escapeHtml(rec.book_number) : '—'}, ${escapeHtml(rec.page_number) ? 'p. ' + escapeHtml(rec.page_number) : '—'}</span>
                        </div>
                    </div>
                    <div style="display: flex; align-items: flex-start; gap: 6px; word-break: break-word;">
                        <i class="fa-solid fa-place-of-worship" style="color: var(--accent); margin-top: 2px; flex-shrink: 0;"></i>
                        <span style="color: var(--text); line-height: 1.35;">${escapeHtml(rec.chapel_name || 'Main Cathedral')}</span>
                    </div>
                    <div style="display: flex; align-items: flex-start; gap: 6px; word-break: break-word;">
                        <i class="fa-solid fa-user-tie" style="color: var(--accent); margin-top: 2px; flex-shrink: 0;"></i>
                        <span style="color: var(--text); line-height: 1.35;">${displayPriest}</span>
                    </div>
                </div>
            </div>

            <!-- Canonical Specifics -->
            ${specificHtml}

            <!-- Remarks -->
            ${notesHtml}

            <!-- Action Toolbar -->
            <div class="inspector-actions" style="margin-top: 4px; display: flex; flex-direction: column; gap: 8px;">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px;">
                    <button type="button" onclick="openCertModal(currentSelectedRecord, recordsScope)" class="btn btn-primary" style="height: 36px; padding: 0 10px; font-size: 12px; font-weight: 600; display: inline-flex; align-items: center; justify-content: center; gap: 6px; border-radius: 8px; border: none; cursor: pointer;">
                        <i class="fa-solid fa-certificate"></i> Certificate
                    </button>
                    ${editBtn}
                </div>
                ${deleteBtn}
            </div>
        </div>
    `;
}

function openCommunionAddModal(scope) {
    const targetScope = scope || window.recordsScope || 'cathedral';
    const uid = (targetScope === 'cathedral') ? 'add-cath' : 'add-chap';
    const modalId = (targetScope === 'cathedral') ? 'modal-add-cathedral-record' : 'modal-add-chapel-record';
    if (typeof selectSacramentPill === 'function') {
        selectSacramentPill(uid, 'Communion');
    }
    if (typeof openModal === 'function') {
        openModal(modalId);
    }
}

// Official Form Handlers
function handleNewRecordAction(scopeOrEl, currentType) {
    let scope = scopeOrEl;
    if (scopeOrEl instanceof HTMLElement) {
        scope = scopeOrEl.getAttribute('data-scope');
        currentType = scopeOrEl.getAttribute('data-type');
    }
    scope = scope || window.recordsScope || 'cathedral';
    const chapelParam = '<?php echo !empty($selected_chapel_id) ? '&chapel_id=' . (int)$selected_chapel_id : ''; ?>';
    if (currentType === 'Baptism') {
        window.location.href = 'baptism_form.php?scope=' + scope + chapelParam;
    } else if (currentType === 'Confirmation') {
        window.location.href = 'confirmation_form.php?scope=' + scope + chapelParam;
    } else if (currentType === 'Wedding') {
        window.location.href = 'marriage_form.php?scope=' + scope + chapelParam;
    } else if (currentType === 'Funeral') {
        window.location.href = 'funeral_form.php?scope=' + scope + chapelParam;
    } else if (currentType === 'Communion') {
        openCommunionAddModal(scope);
    } else {
        openModal('modal-select-official-form');
    }
}

function openOfficialRecordEdit(rec, scope) {
    if (rec instanceof HTMLElement) {
        const raw = rec.getAttribute('data-record') || (rec.closest('tr') && rec.closest('tr').getAttribute('data-record'));
        if (raw) {
            try { rec = JSON.parse(raw); } catch (e) { console.error('Error parsing record data:', e); return; }
        }
    }
    if (!rec) return;
    const targetScope = scope || window.recordsScope || 'cathedral';
    if (rec.record_type === 'Baptism') {
        window.location.href = 'baptism_form.php?record_id=' + rec.record_id + '&scope=' + targetScope;
    } else if (rec.record_type === 'Confirmation') {
        window.location.href = 'confirmation_form.php?record_id=' + rec.record_id + '&scope=' + targetScope;
    } else if (rec.record_type === 'Wedding') {
        window.location.href = 'marriage_form.php?record_id=' + rec.record_id + '&scope=' + targetScope;
    } else if (rec.record_type === 'Funeral') {
        window.location.href = 'funeral_form.php?record_id=' + rec.record_id + '&scope=' + targetScope;
    } else {
        if (typeof openEditRecordModal === 'function') {
            openEditRecordModal(rec, targetScope);
        }
    }
}

// Explicitly bind to window for inline onclick accessibility
window.escapeHtml = escapeHtml;
window.getSacramentBadgeHtml = getSacramentBadgeHtml;
window.showRecordDetails = showRecordDetails;
window.handleNewRecordAction = handleNewRecordAction;
window.openOfficialRecordEdit = openOfficialRecordEdit;

// Auto-select first record immediately or on DOMContentLoaded
(function autoSelectFirstRecord() {
    const run = () => {
        const firstRow = document.querySelector('.record-row');
        <?php if (!empty($records)): ?>
        const firstRec = <?php echo json_encode($records[0], JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        if (firstRow && firstRec && typeof showRecordDetails === 'function') {
            showRecordDetails(firstRec, firstRow);
        }
        <?php endif; ?>
    };
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', run);
    } else {
        run();
    }
})();
</script>