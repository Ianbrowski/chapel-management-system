<?php
if (!in_array($role, ['Admin', 'Cathedral Admin'])) {
    $_SESSION['flash'] = 'Access denied.';
    $_SESSION['flash_type'] = 'error';
    header('Location: dashboard.php');
    exit;
}

$totalEvents = count($events ?? []);
$todayEvents = 0;
$pendingCount = 0;
$confirmedCount = 0;

foreach ($events ?? [] as $ev) {
    $eventDate = date('Y-m-d', strtotime($ev['start_time']));
    if ($eventDate === date('Y-m-d')) {
        $todayEvents++;
    }
    if ($ev['status'] === 'Pending') $pendingCount++;
    if ($ev['status'] === 'Confirmed') $confirmedCount++;
}

if (!function_exists('renderEventTypeBadge')) {
    function renderEventTypeBadge(string $type): string {
        return '<span class="badge" style="background: #f6f6f6; color: var(--ink); border: 1px solid var(--line); font-weight: 500; font-size: 11px; padding: 3px 10px; border-radius: 999px; display: inline-flex; align-items: center; gap: 4px;">' . htmlspecialchars($type) . '</span>';
    }
}

if (!function_exists('renderEventStatusBadge')) {
    function renderEventStatusBadge(string $status): string {
        if ($status === 'Confirmed') {
            return '<span class="badge badge-confirmed" style="font-weight: 500; font-size: 11px; padding: 3px 10px; border-radius: 999px; display: inline-flex; align-items: center; gap: 4px;"><i class="fa-solid fa-circle" style="font-size: 6px;"></i> Confirmed</span>';
        } elseif ($status === 'Cancelled') {
            return '<span class="badge badge-cancelled" style="font-weight: 500; font-size: 11px; padding: 3px 10px; border-radius: 999px; display: inline-flex; align-items: center; gap: 4px;"><i class="fa-solid fa-circle" style="font-size: 6px;"></i> Cancelled</span>';
        } else {
            return '<span class="badge badge-pending" style="font-weight: 500; font-size: 11px; padding: 3px 10px; border-radius: 999px; display: inline-flex; align-items: center; gap: 4px;"><i class="fa-solid fa-circle" style="font-size: 6px;"></i> ' . htmlspecialchars($status) . '</span>';
        }
    }
}

$activeStatus = $ev_filters['status'] ?? '';
?>

<div class="workspace" style="max-width: 1680px; margin: 0 auto; padding: 14px clamp(16px, 1.8vw, 24px) 24px; display: flex; flex-direction: column; gap: 14px; box-sizing: border-box;">

    <!-- ── Simplistic Top Bar ── -->
    <!-- ── 1. UNIFIED COMMAND HEADER: TITLE, BADGES, TOGGLE, AND ACTIONS ── -->
    <div class="page-header-bar" style="display: flex; justify-content: space-between; align-items: center; padding-bottom: 8px; border-bottom: 1px solid var(--border); flex-wrap: wrap; gap: 10px; margin-bottom: 6px;">
        <div style="display: flex; align-items: center; gap: 14px; flex-wrap: wrap;">
            <div class="page-header-icon" style="width: 38px; height: 38px; border-radius: 9px; background: rgba(201,168,76,0.14); color: var(--accent); display: flex; align-items: center; justify-content: center; font-size: 17px; flex-shrink: 0;">
                <i class="fa-solid fa-calendar-check"></i>
            </div>
            <div>
                <h1 class="page-title" style="font-family: var(--font-family, 'Inter', sans-serif); font-size: var(--font-page-title, 20px); font-weight: 700; color: var(--text); margin: 0; line-height: 1.2;">
                    Cathedral Schedules
                </h1>
                <p class="page-subtitle" style="font-size: var(--font-page-subtitle, 14px); color: var(--text2); margin: 3px 0 0 0;">
                    Liturgies, sacramental celebrations, and cathedral pastoral bookings
                </p>
            </div>
        </div>

        <!-- Right Side: Stat Badges (always top-right) -->
        <div style="display: flex; align-items: center; gap: 6px;">
            <span class="badge" style="background: var(--bg3, #F4F4F5); border: 1px solid var(--border); color: var(--text2); font-size: 10.5px; font-weight: 600; padding: 2px 10px; border-radius: 999px;">
                <?php echo $total_events ?? $totalEvents; ?> Events
            </span>
        </div>
    </div>

    <!-- ── 2. SUB-ROW: TOGGLE LEFT + ACTIONS RIGHT ── -->
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 6px;">
        <div>
            <?php if ($can_cathedral_rec ?? false): ?>
            <div class="sched-rec-toggle-group">
                <a href="dashboard.php?section=schedule_cathedral" onclick="navigateToSection('schedule_cathedral'); return false;" class="sched-rec-toggle-btn active" style="height: 28px; font-size: 11px; padding: 0 12px;">
                    <i class="fa-solid fa-calendar-check"></i> Schedules
                </a>
                <a href="dashboard.php?section=records_cathedral" onclick="navigateToSection('records_cathedral'); return false;" class="sched-rec-toggle-btn" style="height: 28px; font-size: 11px; padding: 0 12px;">
                    <i class="fa-solid fa-book-bible"></i> Records
                </a>
            </div>
            <?php endif; ?>
        </div>

        <div style="display: flex; gap: 6px; align-items: center;">
            <a href="dashboard.php?section=events" class="ps-tab-link" style="text-decoration: none; font-size: 11px; height: 28px; padding: 0 12px; border-radius: 999px; display: inline-flex; align-items: center; gap: 5px; border: 1px solid var(--border); background: var(--surface); color: var(--text);">
                <i class="fa-solid fa-calendar-days" style="color: var(--accent); font-size: 11px;"></i> Calendar
            </a>
            <button type="button" class="btn btn-primary btn-sm" onclick="openEventModal()" style="font-size: 11.5px; height: 28px; font-weight: 600; display: inline-flex; align-items: center; gap: 5px; background: #111111; color: #FFFFFF; border: 1px solid #111111; border-radius: 999px; padding: 0 13px; cursor: pointer; box-shadow: 0 1px 3px rgba(0,0,0,0.12);">
                <i class="fa-solid fa-plus text-gold" style="font-size: 10.5px;"></i> New Event
            </button>
        </div>
    </div>

    <!-- ── Unified Filter Toolbar ── -->
    <div style="background: var(--surface); border: 1px solid var(--line); border-radius: 10px; padding: 8px 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
            
            <!-- Left: Status Filter Pills -->
            <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                <?php 
                $curStatus = $ev_filters['status'] ?? '';
                $curType = $ev_filters['event_type'] ?? '';
                $curSort = $ev_filters['sort'] ?? 'date_desc';
                $curPastor = $ev_filters['pastor_id'] ?? '';
                $curTimeframe = $ev_filters['timeframe'] ?? '';
                $searchQuery = $ev_filters['search'] ?? '';
                $baseQuery = '&event_type=' . urlencode($curType) . '&sort=' . urlencode($curSort) 
                    . (!empty($curPastor) ? '&pastor_id=' . urlencode((string)$curPastor) : '')
                    . (!empty($curTimeframe) ? '&timeframe=' . urlencode($curTimeframe) : '')
                    . (!empty($searchQuery) ? '&search=' . urlencode($searchQuery) : '');
                $isAllStatus = empty($curStatus);
                ?>
                <a href="dashboard.php?section=schedule_cathedral<?php echo $baseQuery; ?>" 
                   class="ps-tab-link <?php echo $isAllStatus ? 'active' : ''; ?>">
                    All (<?php echo $total_events ?? $totalEvents; ?>)
                </a>
                <a href="dashboard.php?section=schedule_cathedral&status=Confirmed<?php echo $baseQuery; ?>" 
                   class="ps-tab-link <?php echo ($curStatus === 'Confirmed') ? 'active' : ''; ?>">
                    Confirmed <?php if ($confirmedCount > 0): ?>(<?php echo $confirmedCount; ?>)<?php endif; ?>
                </a>
                <a href="dashboard.php?section=schedule_cathedral&status=Pending<?php echo $baseQuery; ?>" 
                   class="ps-tab-link <?php echo ($curStatus === 'Pending') ? 'active' : ''; ?>">
                    Pending <?php if ($pendingCount > 0): ?>(<?php echo $pendingCount; ?>)<?php endif; ?>
                </a>
                <?php if ($curStatus === 'Cancelled'): ?>
                <a href="dashboard.php?section=schedule_cathedral&status=Cancelled<?php echo $baseQuery; ?>" 
                   class="ps-tab-link active">
                    Cancelled
                </a>
                <?php endif; ?>
            </div>

            <!-- Right: Timeframe, Selective Pastor, Type Dropdown, Up/Down Sort Icon & Search -->
            <form method="GET" style="display: flex; align-items: center; gap: 6px; margin: 0; flex: 1 1 auto; justify-content: flex-end; flex-wrap: wrap;">
                <input type="hidden" name="section" value="schedule_cathedral">
                <?php if (!empty($curStatus)): ?>
                <input type="hidden" name="status" value="<?php echo htmlspecialchars($curStatus); ?>">
                <?php endif; ?>

                <!-- Timeframe Filter Dropdown (This Week, Next Week, etc.) -->
                <select name="timeframe" onchange="this.form.submit()" style="height: 32px !important; font-size: 12px; font-weight: 500; min-width: 110px; max-width: 155px;" title="Filter by timeframe">
                    <option value="">All Dates</option>
                    <option value="recently_added" <?php echo ($curTimeframe === 'recently_added') ? 'selected' : ''; ?>>⚡ Recently Added (30 Days)</option>
                    <option value="recently_added_7" <?php echo ($curTimeframe === 'recently_added_7') ? 'selected' : ''; ?>>⚡ Recently Added (7 Days)</option>
                    <option value="this_week" <?php echo ($curTimeframe === 'this_week') ? 'selected' : ''; ?>>This Week</option>
                    <option value="next_week" <?php echo ($curTimeframe === 'next_week') ? 'selected' : ''; ?>>Next Week</option>
                    <option value="this_month" <?php echo ($curTimeframe === 'this_month') ? 'selected' : ''; ?>>This Month</option>
                    <option value="upcoming" <?php echo ($curTimeframe === 'upcoming') ? 'selected' : ''; ?>>Upcoming</option>
                    <option value="past" <?php echo ($curTimeframe === 'past') ? 'selected' : ''; ?>>Past</option>
                </select>

                <!-- Selective Pastors Filter Dropdown -->
                <select name="pastor_id" onchange="this.form.submit()" style="height: 32px !important; font-size: 12px; font-weight: 500; min-width: 120px; max-width: 165px;" title="Filter by selective pastor">
                    <option value="">All Pastors</option>
                    <?php foreach (($priests ?? []) as $pr): 
                        $pId = (int)$pr['id'];
                        $pName = !empty($pr['full_name']) ? $pr['full_name'] : $pr['user'];
                        if (!preg_match('/^(Fr\.|Father|Rev\.|Pastor|Msgr\.)/i', $pName)) {
                            $pName = 'Fr. ' . $pName;
                        }
                    ?>
                    <option value="<?php echo $pId; ?>" <?php echo ((string)$curPastor === (string)$pId) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($pName); ?>
                    </option>
                    <?php endforeach; ?>
                </select>

                <!-- Type Filter Dropdown -->
                <select name="event_type" onchange="this.form.submit()" style="height: 32px !important; font-size: 12px; font-weight: 500; min-width: 120px; max-width: 165px;" title="Filter by event type">
                    <?php echo render_event_type_options($curType, 'All Types'); ?>
                </select>

                <!-- Sort Toggle Icon (Up / Down) -->
                <input type="hidden" name="sort" id="sort-cathedral-events" value="<?php echo htmlspecialchars($curSort); ?>">
                <button type="button" class="btn-sort-toggle" onclick="toggleSortDirection(this, 'sort-cathedral-events')" 
                        title="<?php echo ($curSort === 'date_asc') ? 'Sorted: Oldest First (Click to sort Newest first)' : 'Sorted: Newest First (Click to sort Oldest first)'; ?>"
                        style="height: 32px; width: 34px; min-width: 34px; padding: 0; display: inline-flex; align-items: center; justify-content: center; border-radius: 6px; border: 1px solid var(--border); background: var(--bg2, #FFFFFF); color: var(--text); cursor: pointer; transition: all 0.15s ease; flex-shrink: 0;" aria-label="Toggle sort order">
                    <i class="fa-solid <?php echo ($curSort === 'date_asc') ? 'fa-arrow-up-wide-short' : 'fa-arrow-down-wide-short'; ?>" style="font-size: 13px;"></i>
                </button>

                <!-- Search Input with Clear -->
                <div style="position: relative; min-width: 160px; max-width: 220px; flex: 1 1 160px;">
                    <i class="fa-solid fa-magnifying-glass" style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); font-size: 11px; color: var(--text2, #737373); pointer-events: none; z-index: 2;"></i>
                    <input type="text" name="search" placeholder="Search event or priest..." 
                           value="<?php echo htmlspecialchars($searchQuery); ?>"
                           style="width: 100%; height: 32px; padding-left: 32px !important; padding-right: <?php echo !empty($searchQuery) ? '24px' : '8px'; ?> !important; font-size: 12px; background: var(--bg2, #FFFFFF); color: var(--text); border: 1px solid var(--border); border-radius: 6px; box-sizing: border-box;">
                    <?php if (!empty($searchQuery)): ?>
                    <a href="dashboard.php?section=schedule_cathedral<?php echo !empty($curStatus) ? '&status=' . urlencode($curStatus) : ''; ?><?php echo !empty($curType) ? '&event_type=' . urlencode($curType) : ''; ?><?php echo !empty($curPastor) ? '&pastor_id=' . urlencode((string)$curPastor) : ''; ?><?php echo !empty($curTimeframe) ? '&timeframe=' . urlencode($curTimeframe) : ''; ?>&sort=<?php echo urlencode($curSort); ?>" 
                       title="Clear search" 
                       style="position: absolute; right: 7px; top: 50%; transform: translateY(-50%); color: #737373; text-decoration: none; font-size: 13px; line-height: 1; padding: 2px;">×</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- ── Full-Width Schedule Table ── -->
    <div style="background: var(--panel-bg); border: 1px solid var(--border); border-radius: 12px; overflow: hidden; display: flex; flex-direction: column;">
        
        <?php if (empty($events)): ?>
            <div style="padding: 48px 20px; text-align: center; color: var(--text2);">
                <i class="fa-solid fa-calendar-xmark" style="font-size: 40px; opacity: 0.3; margin-bottom: 10px; display: block; color: #737373;"></i>
                <div style="font-size: 15px; font-weight: 600; color: var(--text); margin-bottom: 4px;">No Events Found</div>
                <div style="font-size: 12.5px; margin-bottom: 14px;">No cathedral events match your current criteria.</div>
                <button class="btn btn-primary btn-sm" onclick="openEventModal()" style="background: #111111; color: #FFFFFF; border: 1px solid #111111; border-radius: 6px;">
                    <i class="fa-solid fa-plus"></i> Schedule New Event
                </button>
            </div>
        <?php else: ?>
        <div style="overflow-x: auto;">
            <table class="data-table" style="width: 100%; border-collapse: collapse; text-align: left;">
                <thead>
                    <tr style="background: #F6F6F6; border-bottom: 1px solid #E5E5E5;">
                        <th style="padding: 10px 14px; font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: #737373; width: 135px; font-weight: 600;">Date &amp; Time</th>
                        <th style="padding: 10px 14px; font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: #737373; font-weight: 600;">Event</th>
                        <th style="padding: 10px 14px; font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: #737373; width: 110px; text-align: center; font-weight: 600;">Type</th>
                        <th style="padding: 10px 14px; font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: #737373; width: 180px; font-weight: 600;">Presiding Priest</th>
                        <th style="padding: 10px 14px; font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: #737373; width: 110px; text-align: center; font-weight: 600;">Status</th>
                        <th style="padding: 10px 14px; font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: #737373; width: 70px; text-align: center; font-weight: 600;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($events as $ev): ?>
                    <tr class="sched-row" 
                        data-event="<?php echo htmlspecialchars(json_encode($ev, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>"
                        onclick="showEventDetails(this)" 
                        style="cursor: pointer; border-bottom: 1px solid #E5E5E5; transition: background 0.15s ease;"
                        title="Click to view event details">
                        
                        <!-- Date & Time -->
                        <td style="padding: 10px 14px; white-space: nowrap;">
                            <div style="font-weight: 600; font-size: 12px; color: var(--text);">
                                <?php echo date('M d, Y', strtotime($ev['start_time'])); ?>
                            </div>
                            <div style="font-size: 11px; color: var(--text2); margin-top: 2px;">
                                <i class="fa-regular fa-clock" style="font-size: 10px; margin-right: 2px; color: #737373;"></i>
                                <?php echo date('h:i A', strtotime($ev['start_time'])); ?>
                            </div>
                        </td>

                        <!-- Event & Details (Omit repetitive Main Cathedral) -->
                        <td style="padding: 10px 14px;">
                            <div style="font-weight: 600; font-size: 13px; color: var(--text); line-height: 1.3;">
                                <?php echo htmlspecialchars($ev['title']); ?>
                            </div>
                            <?php 
                            $hasCustomChapel = !empty($ev['chapel_name']) && $ev['chapel_name'] !== 'Main Cathedral';
                            $hasDuration = !empty($ev['custom_duration']);
                            if ($hasCustomChapel || $hasDuration):
                            ?>
                            <div style="font-size: 11px; color: var(--text2); margin-top: 2px; display: flex; align-items: center; gap: 6px;">
                                <?php if ($hasCustomChapel): ?>
                                    <span><i class="fa-solid fa-place-of-worship" style="font-size: 10px; color: #737373; margin-right: 3px;"></i><?php echo htmlspecialchars($ev['chapel_name']); ?></span>
                                <?php endif; ?>
                                <?php if ($hasDuration): ?>
                                    <span><i class="fa-regular fa-clock" style="font-size: 10px; color: #737373; margin-right: 2px;"></i><?php echo (int)$ev['custom_duration']; ?> mins</span>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                        </td>

                        <!-- Type -->
                        <td style="padding: 10px 14px; text-align: center;">
                            <?php echo renderEventTypeBadge($ev['event_type']); ?>
                        </td>

                        <!-- Priest -->
                        <td style="padding: 10px 14px; font-size: 12px; color: var(--text);">
                            <?php 
                            $pName = trim($ev['pastor_assigned'] ?? '');
                            if (!empty($pName)) {
                                $displayPriestName = preg_match('/^(Fr\.|Rev\.|Father|Msgr\.)/i', $pName) ? $pName : 'Fr. ' . $pName;
                                echo '<span style="display: inline-flex; align-items: center; gap: 5px; font-weight: 500;"><i class="fa-solid fa-user-tie" style="font-size: 11px; color: #737373;"></i> ' . htmlspecialchars($displayPriestName) . '</span>';
                            } else {
                                echo '<span style="color: var(--text2); opacity: 0.6;">— Unassigned —</span>';
                            }
                            ?>
                        </td>

                        <!-- Status -->
                        <td style="padding: 10px 14px; text-align: center;">
                            <?php echo renderEventStatusBadge($ev['status']); ?>
                        </td>

                        <!-- Actions (Edit & Delete; View handled by row click) -->
                        <td style="padding: 10px 14px; text-align: center;" onclick="event.stopPropagation();">
                            <div style="display: flex; gap: 4px; justify-content: center; align-items: center;">
                                <button type="button" 
                                        class="btn-action-icon" 
                                        data-event="<?php echo htmlspecialchars(json_encode($ev, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>"
                                        onclick="editEvent(this)" 
                                        title="Edit Event">
                                    <i class="fa-solid fa-pen"></i>
                                </button>
                                <button type="button" 
                                        class="btn-action-icon btn-action-danger" 
                                        data-id="<?php echo (int)$ev['event_id']; ?>"
                                        data-title="<?php echo htmlspecialchars($ev['title'], ENT_QUOTES, 'UTF-8'); ?>"
                                        onclick="deleteEvent(this)" 
                                        title="Delete Event">
                                    <i class="fa-solid fa-trash-can"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <!-- Pagination -->
        <?php if ($total_pages > 1): 
            $pgArr = [];
            if (!empty($ev_filters['search'])) $pgArr['search'] = $ev_filters['search'];
            if (!empty($ev_filters['event_type'])) $pgArr['event_type'] = $ev_filters['event_type'];
            if (!empty($ev_filters['status'])) $pgArr['status'] = $ev_filters['status'];
            if (!empty($ev_filters['sort']) && $ev_filters['sort'] !== 'date_desc') $pgArr['sort'] = $ev_filters['sort'];
            if (!empty($curPastor)) $pgArr['pastor_id'] = $curPastor;
            if (!empty($curTimeframe)) $pgArr['timeframe'] = $curTimeframe;
            $pgParams = !empty($pgArr) ? '&' . http_build_query($pgArr) : '';
        ?>
        <div style="display: flex; justify-content: space-between; align-items: center; padding: 8px 14px; border-top: 1px solid var(--border); background: rgba(0,0,0,0.05);">
            <div>
                <?php if ($current_page > 1): ?>
                <a href="?section=schedule_cathedral&page=<?php echo $current_page - 1; ?><?php echo $pgParams; ?>" 
                   class="btn btn-ghost btn-sm" style="padding: 3px 8px; font-size: 11.5px; text-decoration: none;">
                    &larr; Prev
                </a>
                <?php endif; ?>
            </div>
            <span style="font-size: 11.5px; color: var(--text2);">
                Page <?php echo $current_page; ?> of <?php echo $total_pages; ?> (<?php echo $total_events ?? $totalEvents; ?> total)
            </span>
            <div>
                <?php if ($current_page < $total_pages): ?>
                <a href="?section=schedule_cathedral&page=<?php echo $current_page + 1; ?><?php echo $pgParams; ?>" 
                   class="btn btn-ghost btn-sm" style="padding: 3px 8px; font-size: 11.5px; text-decoration: none;">
                    Next &rarr;
                </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

</div>



<!-- Canonical Event Modals (#modal-add-event, #modal-edit-event, #modal-view-event-details) are loaded globally via dashboard_modals.php -->

<!-- ── Delete Confirmation Modal ── -->
<div class="modal-overlay" id="delete-modal">
    <div class="modal" style="max-width: 400px;">
        <div class="modal-header">
            <div class="modal-title" style="color: #ef4444;">
                <i class="fa-solid fa-trash-can"></i> Delete Event
            </div>
            <button class="modal-close" onclick="closeDeleteModal()">×</button>
        </div>
        <div class="modal-body" style="text-align: center; padding: 16px;">
            <p style="font-size: 13.5px; color: var(--text); margin-bottom: 6px;">
                Are you sure you want to permanently delete:
            </p>
            <strong style="color: var(--text); font-size: 14.5px; display: block; margin-bottom: 10px;" id="delete-event-name"></strong>
            <p style="font-size: 11.5px; color: var(--text2); margin: 0;">This liturgical entry will be removed from the schedule.</p>
        </div>
        <div class="modal-footer" style="display: flex; gap: 8px;">
            <form id="delete-form" method="POST" action="dashboard.php?section=schedule_cathedral" style="flex: 1; margin: 0;">
                <input type="hidden" name="form_section" value="events">
                <input type="hidden" name="action" value="delete_event">
                <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
                <input type="hidden" name="return_section" value="schedule_cathedral">
                <input type="hidden" name="id" id="delete-event-id" value="">
                <button type="submit" class="btn btn-danger" style="width: 100%; justify-content: center;">
                    <i class="fa-solid fa-trash-can"></i> Confirm Delete
                </button>
            </form>
            <button type="button" class="btn btn-gray" onclick="closeDeleteModal()" style="flex: 1;">Cancel</button>
        </div>
    </div>
</div>

<!-- ── Cancel Confirmation Modal ── -->
<div class="modal-overlay" id="cancel-modal">
    <div class="modal" style="max-width: 400px;">
        <div class="modal-header">
            <div class="modal-title" style="color: #dc2626;">
                <i class="fa-solid fa-ban"></i> Cancel Event
            </div>
            <button class="modal-close" onclick="closeCancelModal()">×</button>
        </div>
        <div class="modal-body" style="text-align: center; padding: 16px;">
            <p style="font-size: 13.5px; color: var(--text); margin-bottom: 6px;">
                Mark this event as Cancelled?
            </p>
            <strong style="color: var(--text); font-size: 14.5px; display: block; margin-bottom: 10px;" id="cancel-event-name"></strong>
            <p style="font-size: 11.5px; color: var(--text2); margin: 0;">Status will be updated to Cancelled and retained in records.</p>
        </div>
        <div class="modal-footer" style="display: flex; gap: 8px;">
            <form id="cancel-form" method="POST" action="dashboard.php?section=schedule_cathedral" style="flex: 1; margin: 0;">
                <input type="hidden" name="form_section" value="events">
                <input type="hidden" name="action" value="cancel_event">
                <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
                <input type="hidden" name="return_section" value="schedule_cathedral">
                <input type="hidden" name="id" id="cancel-event-id" value="">
                <button type="submit" class="btn btn-danger btn-red" style="width: 100%; justify-content: center; background: #dc2626 !important; border-color: #dc2626 !important; color: #fff !important;">
                    <i class="fa-solid fa-ban"></i> Confirm Cancel
                </button>
            </form>
            <button type="button" class="btn btn-gray" onclick="closeCancelModal()" style="flex: 1;">Keep Active</button>
        </div>
    </div>
</div>

<style>
.sched-row {
    transition: background-color 0.15s ease;
}
.sched-row:hover td {
    background: var(--hover-bg, #F6F6F6) !important;
}
[data-theme="dark"] .sched-row:hover td {
    background: #1e293b !important;
}
.btn-action-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 26px;
    height: 26px;
    border-radius: 6px;
    background: var(--bg3, rgba(255, 255, 255, 0.05));
    border: 1px solid var(--border);
    color: var(--text2);
    cursor: pointer;
    transition: all 0.15s ease;
    font-size: 11px;
}
.btn-action-icon:hover {
    background: var(--accent);
    color: #111111;
    border-color: var(--accent);
    transform: translateY(-1px);
}
.btn-action-icon.btn-action-danger:hover {
    background: #ef4444;
    color: #fff;
    border-color: #ef4444;
}
</style>

<script>
let currentSelectedEvent = null;

function escapeHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function getEventTypeBadgeHtml(type) {
    const badges = {
        'Baptism':      { bg: 'rgba(197, 160, 89, 0.15)', color: 'var(--accent, #c5a059)', border: 'rgba(197, 160, 89, 0.3)', icon: 'fa-water' },
        'Wedding':      { bg: 'rgba(197, 160, 89, 0.15)', color: 'var(--accent, #c5a059)', border: 'rgba(197, 160, 89, 0.3)', icon: 'fa-ring' },
        'Mass':         { bg: 'rgba(197, 160, 89, 0.15)', color: 'var(--accent, #c5a059)', border: 'rgba(197, 160, 89, 0.3)', icon: 'fa-church' },
        'Funeral':      { bg: 'rgba(148, 163, 184, 0.12)', color: '#cbd5e1', border: 'rgba(148, 163, 184, 0.25)', icon: 'fa-cross' },
        'Confirmation': { bg: 'rgba(197, 160, 89, 0.15)', color: 'var(--accent, #c5a059)', border: 'rgba(197, 160, 89, 0.3)', icon: 'fa-dove' },
    };
    const b = badges[type] || { bg: 'rgba(255, 255, 255, 0.06)', color: 'var(--text2)', border: 'var(--border)', icon: 'fa-calendar' };
    return `<span class="badge" style="background: ${b.bg}; color: ${b.color}; border: 1px solid ${b.border}; font-weight: 600; font-size: 11px; padding: 2px 8px; border-radius: 6px; display: inline-flex; align-items: center; gap: 4px;"><i class="fa-solid ${b.icon}" style="font-size: 9px;"></i> ${escapeHtml(type)}</span>`;
}

function getEventStatusBadgeHtml(status) {
    const badges = {
        'Confirmed': { bg: 'rgba(22, 163, 74, 0.12)', color: '#22c55e', border: 'rgba(22, 163, 74, 0.25)', icon: 'fa-circle-check' },
        'Pending':   { bg: 'rgba(197, 160, 89, 0.15)', color: 'var(--accent, #c5a059)', border: 'rgba(197, 160, 89, 0.3)', icon: 'fa-clock' },
        'Completed': { bg: 'rgba(22, 163, 74, 0.12)', color: '#22c55e', border: 'rgba(22, 163, 74, 0.25)', icon: 'fa-flag-checkered' },
        'Cancelled': { bg: 'rgba(220, 38, 38, 0.12)', color: '#ef4444', border: 'rgba(220, 38, 38, 0.25)', icon: 'fa-ban' },
    };
    const b = badges[status] || { bg: 'rgba(255, 255, 255, 0.06)', color: 'var(--text2)', border: 'var(--border)', icon: 'fa-circle' };
    return `<span class="badge" style="background: ${b.bg}; color: ${b.color}; border: 1px solid ${b.border}; font-weight: 600; font-size: 11px; padding: 2px 8px; border-radius: 6px; display: inline-flex; align-items: center; gap: 4px;"><i class="fa-solid ${b.icon}" style="font-size: 8px;"></i> ${escapeHtml(status)}</span>`;
}

function showEventDetails(input) {
    let ev = input;
    if (input instanceof HTMLElement) {
        const raw = input.getAttribute('data-event') || input.getAttribute('data-req');
        if (raw) {
            try { ev = JSON.parse(raw); } catch (e) { console.error('Failed to parse event:', e); return; }
        }
    } else if (typeof input === 'string') {
        try { ev = JSON.parse(input); } catch (e) { console.error('Failed to parse event:', e); return; }
    }
    if (!ev || typeof ev !== 'object') return;
    if (typeof viewEventDetails === 'function') {
        viewEventDetails(ev);
    }
}
window.showEventDetails = showEventDetails;

function openEventModal() {
    const venueSelect = document.getElementById('field-venue-modal');
    if (venueSelect) {
        venueSelect.value = '0';
    }
    openModal('modal-add-event');
}

function closeEventModal() {
    closeModal('modal-add-event');
}

function editEvent(input) {
    let ev = input;
    if (input instanceof HTMLElement) {
        const raw = input.getAttribute('data-event') || input.getAttribute('data-req');
        if (raw) {
            try { ev = JSON.parse(raw); } catch (e) { console.error('Failed to parse event:', e); return; }
        }
    } else if (typeof input === 'string') {
        try { ev = JSON.parse(input); } catch (e) { console.error('Failed to parse event:', e); return; }
    }
    if (!ev || typeof ev !== 'object') return;
    if (typeof openEditEventModal === 'function') {
        openEditEventModal(ev);
    }
}
window.editEvent = editEvent;

function duplicateEvent(eventId, ev) {
    if (!ev) {
        window.location.href = '?section=schedule_cathedral&action=duplicate&id=' + eventId;
        return;
    }
    if (typeof openEditEventModal === 'function') {
        openEditEventModal(ev);
        const idInput = document.getElementById('edit-event-id');
        if (idInput) idInput.value = '';
        const titleInput = document.getElementById('edit-event-title');
        if (titleInput) titleInput.value = (ev.title || '') + ' (Copy)';
    }
}

function deleteEvent(eventIdOrEl, eventName) {
    let eventId = eventIdOrEl;
    if (eventIdOrEl instanceof HTMLElement) {
        eventId = eventIdOrEl.getAttribute('data-id');
        eventName = eventIdOrEl.getAttribute('data-title');
    }
    const idInput = document.getElementById('delete-event-id');
    if (idInput) idInput.value = eventId || '';
    const nameSpan = document.getElementById('delete-event-name');
    if (nameSpan) nameSpan.textContent = eventName || '';
    openModal('delete-modal');
}
function closeDeleteModal() {
    closeModal('delete-modal');
}

function cancelEvent(eventIdOrEl, eventName) {
    let eventId = eventIdOrEl;
    if (eventIdOrEl instanceof HTMLElement) {
        eventId = eventIdOrEl.getAttribute('data-id');
        eventName = eventIdOrEl.getAttribute('data-title');
    }
    const idInput = document.getElementById('cancel-event-id');
    if (idInput) idInput.value = eventId || '';
    const nameSpan = document.getElementById('cancel-event-name');
    if (nameSpan) nameSpan.textContent = eventName || '';
    openModal('cancel-modal');
}
function closeCancelModal() {
    closeModal('cancel-modal');
}

window.showEventDetails = showEventDetails;
window.editEvent = editEvent;
window.deleteEvent = deleteEvent;
window.closeDeleteModal = closeDeleteModal;
window.cancelEvent = cancelEvent;
window.closeCancelModal = closeCancelModal;
window.openEventModal = openEventModal;
window.closeEventModal = closeEventModal;
window.cancelEvent = cancelEvent;
window.deleteEvent = deleteEvent;
window.duplicateEvent = duplicateEvent;

if (window.history.replaceState) {
    window.history.replaceState(null, null, window.location.href);
}
</script>