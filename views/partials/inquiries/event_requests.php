<?php
if (!$can_approve_events) {
    $_SESSION['flash'] = 'Access denied.';
    $_SESSION['flash_type'] = 'error';
    header('Location: dashboard.php');
    exit;
}

$curStatus = $_GET['status'] ?? 'pending';
$curType = $_GET['event_type'] ?? '';
$curSort = $_GET['sort'] ?? 'date_asc';
$curTimeframe = $_GET['timeframe'] ?? '';
$searchQuery = $_GET['search'] ?? '';
$typeParam = !empty($curType) ? '&amp;event_type=' . urlencode($curType) : '';
$sortParam = ($curSort !== 'date_asc') ? '&amp;sort=' . urlencode($curSort) : '';
$timeParam = !empty($curTimeframe) ? '&amp;timeframe=' . urlencode($curTimeframe) : '';
$qCounts = $queue_counts ?? [];
$totalCount = (int)($qCounts['total'] ?? $total_count ?? count($pending_requests ?? []));
$pCount = (int)($qCounts['pending'] ?? $pending_count ?? count($pending_requests ?? []));
$urgentCount = (int)($qCounts['urgent'] ?? 0);
$confirmedCount = (int)($qCounts['confirmed'] ?? 0);
$rejectedCount = (int)($qCounts['rejected'] ?? 0);
$overdueCount = (int)($overdue_request_count ?? 0);
$cp = (int)($current_page ?? 1);
$tp = (int)($total_pages ?? 1);
?>

<div class="workspace" style="display: flex; flex-direction: column; gap: 14px; padding: 14px clamp(16px, 1.8vw, 24px) 24px; width: 100%; max-width: 1680px; margin: 0 auto; box-sizing: border-box;">

    <!-- ─── 1. UNIFIED COMMAND HEADER: TITLE, BADGES, TABS, AND ACTIONS ─── -->
    <div class="page-header-bar" style="display: flex; align-items: center; justify-content: space-between; padding-bottom: 8px; border-bottom: 1px solid var(--border); flex-wrap: wrap; gap: 10px; margin-bottom: 6px;">
        <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
            <div class="page-header-icon" style="width: 38px; height: 38px; border-radius: 9px; background: rgba(201,168,76,0.14); color: var(--accent); display: flex; align-items: center; justify-content: center; font-size: 17px; flex-shrink: 0;">
                <i class="fa-solid fa-inbox"></i>
            </div>
            <div>
                <h1 class="page-title" style="font-family: var(--font-family, 'Inter', sans-serif); font-size: var(--font-page-title, 20px); font-weight: 700; color: var(--text); margin: 0; line-height: 1.2;">
                    Event Requests
                </h1>
                <p class="page-subtitle" style="font-size: var(--font-page-subtitle, 14px); color: var(--text2); margin: 3px 0 0 0;">
                    Review and approve baptism, wedding, and blessing requests from members
                </p>
            </div>
        </div>

        <!-- Right Side: Stat Badges (always top-right) -->
        <div style="display: flex; align-items: center; gap: 6px;">
            <span class="badge" style="background: var(--bg3, #F4F4F5); border: 1px solid var(--border); color: var(--text2); font-size: 11.5px; font-weight: 600; padding: 4px 12px; border-radius: 999px;">
                <?php echo (int)$pCount; ?> Pending
            </span>
            <?php if ($overdueCount > 0): ?>
            <span class="badge badge-danger" style="font-size: 11.5px; font-weight: 600; padding: 4px 10px; border-radius: 999px;" title="<?php echo (int)$overdueCount; ?> requests past due date">
                <?php echo (int)$overdueCount; ?> Overdue
            </span>
            <?php endif; ?>
        </div>
    </div>

    <!-- ─── 2. SUB-ROW: TABS LEFT + ACTIONS RIGHT ─── -->
    <div class="requests-subnav-bar" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; margin-bottom: 8px;">
        <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
            <div class="requests-tabs-group" style="display: inline-flex; align-items: center; gap: 4px; background: var(--bg3, #F4F4F5); padding: 3px; border-radius: 999px; border: 1px solid var(--border); flex-wrap: wrap;">
                <a href="dashboard.php?section=event_requests&amp;status=pending<?php echo !empty($searchQuery) ? '&amp;search='.urlencode($searchQuery) : ''; ?><?php echo $timeParam . $sortParam; ?>" 
                   class="req-tab-btn <?php echo ($curStatus === 'pending') ? 'active' : ''; ?>">
                    Pending (<?php echo (int)$pCount; ?>)
                </a>
                <a href="dashboard.php?section=event_requests&amp;status=urgent<?php echo !empty($searchQuery) ? '&amp;search='.urlencode($searchQuery) : ''; ?><?php echo $timeParam . $sortParam; ?>" 
                   class="req-tab-btn <?php echo ($curStatus === 'urgent') ? 'active' : ''; ?>">
                    Urgent<?php if ($urgentCount > 0): ?> (<?php echo (int)$urgentCount; ?>)<?php endif; ?>
                </a>
                <a href="dashboard.php?section=event_requests&amp;status=confirmed<?php echo !empty($searchQuery) ? '&amp;search='.urlencode($searchQuery) : ''; ?><?php echo $timeParam . $sortParam; ?>" 
                   class="req-tab-btn <?php echo ($curStatus === 'confirmed') ? 'active' : ''; ?>">
                    Approved<?php if ($confirmedCount > 0): ?> (<?php echo (int)$confirmedCount; ?>)<?php endif; ?>
                </a>
                <a href="dashboard.php?section=event_requests&amp;status=rejected<?php echo !empty($searchQuery) ? '&amp;search='.urlencode($searchQuery) : ''; ?><?php echo $timeParam . $sortParam; ?>" 
                   class="req-tab-btn <?php echo ($curStatus === 'rejected') ? 'active' : ''; ?>">
                    Rejected<?php if ($rejectedCount > 0): ?> (<?php echo (int)$rejectedCount; ?>)<?php endif; ?>
                </a>
                <a href="dashboard.php?section=event_requests&amp;status=all<?php echo !empty($searchQuery) ? '&amp;search='.urlencode($searchQuery) : ''; ?><?php echo $timeParam . $sortParam; ?>" 
                   class="req-tab-btn <?php echo ($curStatus === 'all') ? 'active' : ''; ?>">
                    All (<?php echo (int)$totalCount; ?>)
                </a>
            </div>
        </div>

        <div style="display: flex; align-items: center; gap: 8px;">
            <a href="dashboard.php?section=events" class="btn btn-ghost btn-sm" 
               style="height: 34px; font-size: 12px; font-weight: 600; display: inline-flex; align-items: center; gap: 6px; background: var(--surface, #FFFFFF); border: 1px solid var(--border); color: var(--text); border-radius: 999px; padding: 0 14px; text-decoration: none;">
                <i class="fa-solid fa-calendar-days" style="color: var(--accent);"></i> Calendar
            </a>
            <?php if ($can_request_event): ?>
            <button type="button" class="btn btn-primary btn-sm" onclick="openModal('modal-add-event')" 
                    style="height: 34px; font-weight: 600; font-size: 12px; display: inline-flex; align-items: center; gap: 6px; background: #111111; color: #FFFFFF; border: 1px solid #111111; border-radius: 999px; padding: 0 16px; cursor: pointer; box-shadow: 0 1px 3px rgba(0,0,0,0.12);">
                <i class="fa-solid fa-plus"></i> New Reservation
            </button>
            <?php endif; ?>
        </div>
    </div>

    <style>
    .req-tab-btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 7px 16px;
        border-radius: 999px;
        font-size: 12.5px;
        font-weight: 500;
        color: var(--text2);
        background: transparent;
        border: 1px solid transparent;
        cursor: pointer;
        text-decoration: none;
        transition: all 0.18s ease;
        line-height: 1.2;
        min-height: 34px;
        box-sizing: border-box;
        white-space: nowrap;
    }
    .req-tab-btn:hover {
        color: var(--text);
        background: var(--hover-bg, rgba(0,0,0,0.04));
    }
    .req-tab-btn.active {
        background: #111111 !important;
        color: #FFFFFF !important;
        border-color: #111111 !important;
        font-weight: 700;
        box-shadow: 0 1px 3px rgba(0,0,0,0.14);
    }
    [data-theme="dark"] .requests-tabs-group {
        background: #18181b !important;
        border-color: rgba(255, 255, 255, 0.12) !important;
    }
    [data-theme="dark"] .req-tab-btn {
        color: #cbd5e1;
    }
    [data-theme="dark"] .req-tab-btn:hover {
        background: rgba(255,255,255,0.08);
        color: #ffffff;
    }
    [data-theme="dark"] .req-tab-btn.active {
        background: #FFFFFF !important;
        color: #111111 !important;
        border-color: #FFFFFF !important;
        box-shadow: 0 2px 8px rgba(0,0,0,0.3);
    }
    </style>

    <!-- ─── MASTER TABLE CARD WITH INTEGRATED TOOLBAR ─── -->
    <div class="card" style="background: var(--surface, #FFFFFF); border: 1px solid var(--border, #E5E5E5); border-radius: 12px; box-shadow: none; overflow: hidden; display: flex; flex-direction: column;">
        
        <!-- Filter Toolbar -->
        <div style="padding: 12px 16px; background: var(--surface, #FFFFFF); border-bottom: 1px solid var(--border, #E5E5E5); display: flex; align-items: center; justify-content: space-between; gap: 10px; flex-wrap: wrap;">
            <div style="display: flex; align-items: center; gap: 8px;">
                <span style="font-family: var(--font-family, 'Inter', sans-serif); font-size: 0.95rem; font-weight: 600; color: var(--text, #111111); display: flex; align-items: center; gap: 6px;">
                    <i class="fa-solid fa-list-check" style="color: var(--accent);"></i> Request Queue
                </span>
                <span class="badge" style="background: var(--bg2, #F6F6F6); border: 1px solid var(--border, #E5E5E5); font-size: 11px; padding: 2px 7px; color: var(--text2, #737373); border-radius: 6px;">
                    <?php echo count($pending_requests ?? []); ?> Listed &bull; Page <?php echo $cp; ?> of <?php echo max(1, $tp); ?>
                </span>
            </div>

            <!-- Search & Filter Form (Universal Table Toolbar) -->
            <form method="GET" style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin: 0;">
                <input type="hidden" name="section" value="event_requests">
                <?php if ($curStatus !== 'all'): ?>
                <input type="hidden" name="status" value="<?php echo htmlspecialchars($curStatus); ?>">
                <?php endif; ?>

                <!-- Type / Sacrament Filter Dropdown -->
                <select name="event_type" onchange="this.form.submit()" style="padding: 0 10px; font-size: 12px; height: 34px; border-radius: 8px; background: var(--bg2); border: 1px solid var(--border); color: var(--text); cursor: pointer; min-width: 120px; outline: none;">
                    <?php echo render_event_type_options($curType, 'All Rites'); ?>
                </select>

                <!-- Timeframe / Recently Added Filter Dropdown -->
                <select name="timeframe" onchange="this.form.submit()" style="padding: 0 10px; font-size: 12px; height: 34px; border-radius: 8px; background: var(--bg2); border: 1px solid var(--border); color: var(--text); cursor: pointer; min-width: 130px; outline: none;" title="Filter requests by submission timeframe">
                    <option value="">All Request Dates</option>
                    <option value="recently_added" <?php echo ($curTimeframe === 'recently_added') ? 'selected' : ''; ?>>⚡ Recently Added (30 Days)</option>
                    <option value="recently_added_7" <?php echo ($curTimeframe === 'recently_added_7') ? 'selected' : ''; ?>>⚡ Recently Added (7 Days)</option>
                </select>

                <!-- Sort Dropdown (Event Date & Recently Submitted) -->
                <select name="sort" onchange="this.form.submit()" style="padding: 0 10px; font-size: 12px; height: 34px; border-radius: 8px; background: var(--bg2); border: 1px solid var(--border); color: var(--text); cursor: pointer; min-width: 140px; outline: none;" title="Sort order">
                    <option value="date_asc" <?php echo ($curSort === 'date_asc') ? 'selected' : ''; ?>>📅 Event Date (Soonest)</option>
                    <option value="date_desc" <?php echo ($curSort === 'date_desc') ? 'selected' : ''; ?>>📅 Event Date (Latest)</option>
                    <option value="recently_added" <?php echo ($curSort === 'recently_added' || $curSort === 'created_desc') ? 'selected' : ''; ?>>⚡ Recently Submitted</option>
                </select>

                <!-- Search Input with Clear '×' Button -->
                <div style="position: relative; width: 230px;">
                    <i class="fa-solid fa-magnifying-glass" style="position: absolute; left: 11px; top: 50%; transform: translateY(-50%); font-size: 11.5px; color: var(--text2); pointer-events: none; z-index: 2;"></i>
                    <input type="text" name="search" placeholder="Search title or requester..." 
                           value="<?php echo htmlspecialchars($searchQuery); ?>"
                           style="height: 34px; font-size: 12.5px; width: 100%; background: var(--bg2); border: 1px solid var(--border); color: var(--text); padding-left: 32px !important; padding-right: <?php echo !empty($searchQuery) ? '26px' : '10px'; ?>; border-radius: 8px; box-sizing: border-box; outline: none; transition: border-color 0.15s ease;">
                    <?php if (!empty($searchQuery)): ?>
                    <a href="dashboard.php?section=event_requests<?php echo ($curStatus !== 'all') ? '&amp;status='.urlencode($curStatus) : ''; ?><?php echo $typeParam . $sortParam . $timeParam; ?>" 
                       title="Clear search" 
                       style="position: absolute; right: 8px; top: 50%; transform: translateY(-50%); color: var(--text2); text-decoration: none; font-size: 14px; line-height: 1; padding: 2px;">&times;</a>
                    <?php endif; ?>
                </div>

                <?php if (!empty($searchQuery) || $curStatus !== 'all' || !empty($curType) || !empty($curTimeframe) || $curSort !== 'date_asc'): ?>
                    <a href="dashboard.php?section=event_requests" class="btn btn-ghost btn-sm" title="Clear Filters" style="height: 34px; padding: 0 10px; font-size: 11.5px; color: var(--text2); background: var(--bg2); border: 1px solid var(--border); border-radius: 8px; display: inline-flex; align-items: center; justify-content: center; gap: 4px; text-decoration: none;">
                        <i class="fa-solid fa-rotate-left"></i> Reset
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Table Listing -->
        <?php if (empty($pending_requests)): ?>
        <div style="padding: 50px 20px; text-align: center; color: var(--text2); display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 8px;">
            <div style="width: 44px; height: 44px; border-radius: 50%; background: var(--bg2); display: inline-flex; align-items: center; justify-content: center; border: 1px solid var(--border);">
                <i class="fa-solid <?php echo (!empty($searchQuery) || $curStatus !== 'all' || !empty($curType)) ? 'fa-filter-circle-xmark' : 'fa-circle-check'; ?>" style="font-size: 18px; color: var(--accent);"></i>
            </div>
            <div>
                <h3 style="margin: 0 0 4px; font-size: 15px; color: var(--text); font-weight: 600;">
                    <?php echo (!empty($searchQuery) || $curStatus !== 'all' || !empty($curType)) ? 'No Matching Requests' : 'All Caught Up'; ?>
                </h3>
                <p style="margin: 0; font-size: 12.5px; color: var(--text2); max-width: 320px;">
                    <?php echo (!empty($searchQuery) || $curStatus !== 'all' || !empty($curType)) ? 'No requests match your search or filter criteria.' : 'No pending reservations awaiting administrative review.'; ?>
                </p>
            </div>
            <?php if (!empty($searchQuery) || $curStatus !== 'all' || !empty($curType) || $curSort !== 'date_asc'): ?>
            <a href="dashboard.php?section=event_requests" class="btn btn-ghost btn-sm" style="margin-top: 4px; height: 32px; padding: 0 14px; font-size: 12px; border-radius: 8px; border: 1px solid var(--border); display: inline-flex; align-items: center; gap: 6px; text-decoration: none;">
                <i class="fa-solid fa-rotate-left"></i> Reset Filters
            </a>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <div class="table-wrap" style="overflow-x: auto; max-height: 540px; overflow-y: auto;">
            <table class="data-table" style="width: 100%; min-width: 820px; border-collapse: collapse;">
                <thead style="position: sticky; top: 0; background: var(--surface, #FFFFFF); z-index: 5;">
                    <tr style="border-bottom: 1px solid var(--border, #E5E5E5);">
                        <th style="width: 50px; text-align: center; color: var(--text2, #737373); font-size: 11px; font-weight: 600; text-transform: uppercase;">#</th>
                        <th style="color: var(--text2, #737373); font-size: 11px; font-weight: 600; text-transform: uppercase;">Event &amp; Rite</th>
                        <th style="width: 220px; color: var(--text2, #737373); font-size: 11px; font-weight: 600; text-transform: uppercase;">Schedule &amp; Sanctuary</th>
                        <th style="width: 180px; color: var(--text2, #737373); font-size: 11px; font-weight: 600; text-transform: uppercase;">Requested By</th>
                        <th style="width: 100px; text-align: center; color: var(--text2, #737373); font-size: 11px; font-weight: 600; text-transform: uppercase;">Status</th>
                        <th style="width: 105px; text-align: center; color: var(--text2, #737373); font-size: 11px; font-weight: 600; text-transform: uppercase;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    foreach ($pending_requests as $req): 
                        $isOverdue = !empty($req['start_time']) && strtotime($req['start_time']) < time();
                        $evType = $req['event_type'] ?? 'Liturgy';
                        $reqJson = htmlspecialchars(json_encode($req, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP), ENT_QUOTES);
                    ?>
                    <tr style="cursor: pointer; transition: background 0.15s; border-bottom: 1px solid var(--border); <?php echo $isOverdue ? 'border-left: 3px solid #D64545;' : ''; ?>" 
                        data-req="<?php echo $reqJson; ?>" 
                        onclick="viewRequestDetailsFromRow(this)"
                        title="Click to view full reservation details">
                        
                        <td style="text-align: center; color: var(--text2, #737373); font-size: 11px; font-family: monospace; font-weight: 600;">
                            #<?php echo $req['event_id']; ?>
                        </td>

                        <td>
                            <div style="font-weight: 600; font-size: 12.5px; color: var(--text, #111111); line-height: 1.3;">
                                <?php echo htmlspecialchars($req['title']); ?>
                            </div>
                            <div style="display: flex; align-items: center; gap: 6px; margin-top: 3px;">
                                <span class="badge" style="background: var(--bg2, #F6F6F6); color: var(--text, #111111); border: 1px solid var(--border, #E5E5E5); font-size: 11.5px; padding: 2px 7px; border-radius: 4px; font-weight: 600;">
                                    <?php echo htmlspecialchars($evType); ?>
                                </span>
                                <?php if (!empty($req['event_scope'])): ?>
                                <span style="font-size: 11.5px; color: var(--text2, #737373);">
                                    &bull; <?php echo htmlspecialchars($req['event_scope']); ?>
                                </span>
                                <?php endif; ?>
                            </div>
                        </td>

                        <td>
                            <div style="font-weight: 600; font-size: 12.5px; color: var(--text, #111111); display: flex; align-items: center; gap: 5px;">
                                <i class="fa-regular fa-calendar" style="color: var(--accent);"></i>
                                <span><?php echo date('M d, Y', strtotime($req['start_time'])); ?> &bull; <?php echo date('h:i A', strtotime($req['start_time'])); ?></span>
                            </div>
                            <div style="font-size: 11.5px; color: var(--text2, #737373); margin-top: 2px; display: flex; align-items: center; gap: 4px;">
                                <i class="fa-solid fa-place-of-worship" style="color: var(--accent); font-size: 11px;"></i>
                                <?php echo htmlspecialchars($req['chapel_location'] ?: 'Main Cathedral'); ?>
                            </div>
                        </td>

                        <td style="font-size: 12.5px; color: var(--text, #111111);">
                            <div style="font-weight: 600;">
                                <i class="fa-regular fa-user" style="color: var(--text2, #737373); margin-right: 4px;"></i>
                                <?php echo htmlspecialchars($req['requester_name'] ?? 'Parish Member'); ?>
                            </div>
                        </td>

                        <td style="text-align: center;">
                            <?php 
                            $status = $req['status'] ?? 'Pending';
                            if ($status === 'Confirmed'): ?>
                                <span class="badge" style="background: rgba(22,163,74,0.1); color: #16a34a; border: 1px solid rgba(22,163,74,0.3); font-size: 11.5px; padding: 3px 8px; border-radius: 4px; font-weight: 600;">
                                    <i class="fa-solid fa-circle-check"></i> Approved
                                </span>
                            <?php elseif ($status === 'Rejected' || $status === 'Cancelled'): ?>
                                <span class="badge" style="background: rgba(220,38,38,0.1); color: #dc2626; border: 1px solid rgba(220,38,38,0.3); font-size: 11.5px; padding: 3px 8px; border-radius: 4px; font-weight: 600;">
                                    <i class="fa-solid fa-circle-xmark"></i> <?php echo htmlspecialchars($status); ?>
                                </span>
                            <?php elseif ($isOverdue): ?>
                                <span class="badge" style="background: #FFFFFF; color: #D64545; border: 1px solid #D64545; font-size: 11.5px; padding: 3px 8px; border-radius: 4px; font-weight: 600;">
                                    <i class="fa-solid fa-triangle-exclamation"></i> Overdue
                                </span>
                            <?php else: ?>
                                <span class="badge" style="background: #F6F6F6; color: #737373; border: 1px solid #E5E5E5; font-size: 11.5px; padding: 3px 8px; border-radius: 4px; font-weight: 600;">
                                    <i class="fa-solid fa-clock"></i> Pending
                                </span>
                            <?php endif; ?>
                        </td>

                        <td style="text-align: center;">
                            <?php if ($status === 'Pending'): ?>
                            <div class="req-action-wrap" style="position: relative; display: inline-block;" onclick="event.stopPropagation();">
                                <button type="button" class="btn btn-sm btn-action-box" 
                                        onclick="toggleRequestActionBox(this, event)"
                                        aria-haspopup="true"
                                        aria-expanded="false"
                                        title="Click to review action controls"
                                        style="height: 32px; padding: 4px 12px; font-size: 12px; font-weight: 600; border-radius: 6px; border: 1px solid var(--border); background: var(--surface); color: var(--text); cursor: pointer; display: inline-flex; align-items: center; gap: 6px; box-shadow: 0 1px 2px rgba(0,0,0,0.05); transition: all 0.15s ease;">
                                    <span>Manage</span>
                                    <i class="fa-solid fa-chevron-down" style="font-size: 9px; opacity: 0.7;"></i>
                                </button>
                                <div class="req-action-popover" style="display: none; position: absolute; right: 0; top: calc(100% + 4px); background: var(--surface); border: 1px solid var(--border); border-radius: 8px; box-shadow: 0 8px 24px rgba(0,0,0,0.18); z-index: 100; min-width: 170px; padding: 5px; text-align: left;">
                                    <button type="button" class="req-popover-item"
                                            data-approve="<?php echo htmlspecialchars(json_encode([
                                                'id' => $req['event_id'],
                                                'title' => $req['title'],
                                                'location' => $req['chapel_id'],
                                                'pastor_id' => $req['pastor_id'],
                                                'start_time' => $req['start_time'],
                                                'event_type' => $req['event_type'],
                                                'custom_duration' => $req['custom_duration']
                                            ], JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>"
                                            onclick="closeAllReqActionBoxes(); openApproveModal(this);"
                                            style="width: 100%; border: none; background: transparent; padding: 8px 10px; font-size: 12px; font-weight: 600; color: #16a34a; cursor: pointer; display: flex; align-items: center; gap: 8px; border-radius: 5px; text-align: left; transition: background 0.15s ease;">
                                        <i class="fa-solid fa-circle-check" style="font-size: 14px;"></i>
                                        <span>Check / Approve</span>
                                    </button>
                                    <button type="button" class="req-popover-item"
                                            data-reject="<?php echo htmlspecialchars(json_encode([
                                                'id' => $req['event_id'],
                                                'title' => $req['title']
                                            ], JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>"
                                            onclick="closeAllReqActionBoxes(); openRejectModal(this);"
                                            style="width: 100%; border: none; background: transparent; padding: 8px 10px; font-size: 12px; font-weight: 600; color: #dc2626; cursor: pointer; display: flex; align-items: center; gap: 8px; border-radius: 5px; text-align: left; transition: background 0.15s ease;">
                                        <i class="fa-solid fa-circle-xmark" style="font-size: 14px;"></i>
                                        <span>Reject Request</span>
                                    </button>
                                    <div style="height: 1px; background: var(--border); margin: 3px 0;"></div>
                                    <button type="button" class="req-popover-item"
                                            onclick="closeAllReqActionBoxes(); viewRequestDetailsFromRow(this.closest('tr'));"
                                            style="width: 100%; border: none; background: transparent; padding: 7px 10px; font-size: 11.5px; font-weight: 500; color: var(--text2); cursor: pointer; display: flex; align-items: center; gap: 8px; border-radius: 5px; text-align: left; transition: background 0.15s ease;">
                                        <i class="fa-solid fa-eye" style="font-size: 12px;"></i>
                                        <span>Review Details</span>
                                    </button>
                                </div>
                            </div>
                            <?php else: ?>
                            <button type="button" class="btn btn-sm btn-ghost" onclick="viewRequestDetailsFromRow(this.closest('tr'))" 
                                    style="height: 30px; padding: 4px 10px; font-size: 11.5px; border-radius: 6px; border: 1px solid var(--border); color: var(--text); background: var(--surface); cursor: pointer; display: inline-flex; align-items: center; gap: 5px;">
                                <i class="fa-solid fa-eye" style="font-size: 11px; color: var(--accent);"></i>
                                <span>Details</span>
                            </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <!-- Pagination Controls -->
        <?php if ($tp > 1): ?>
        <div style="display: flex; justify-content: center; align-items: center; gap: 6px; padding: 12px 16px; background: var(--surface, #FFFFFF); border-top: 1px solid var(--border, #E5E5E5);">
            <?php 
            $pgUrl = "dashboard.php?section=event_requests&status=" . urlencode($curStatus) . "&search=" . urlencode($searchQuery);
            ?>

            <?php if ($cp > 1): ?>
                <a href="<?php echo $pgUrl; ?>&page=<?php echo $cp - 1; ?>" class="btn btn-ghost btn-sm" style="padding: 4px 10px; font-size: 11.5px; background: var(--surface, #FFFFFF); border: 1px solid var(--border, #E5E5E5); color: var(--text, #111111); border-radius: 6px;">
                    <i class="fa-solid fa-arrow-left"></i> Prev
                </a>
            <?php endif; ?>

            <?php
            $startPage = max(1, $cp - 2);
            $endPage   = min($tp, $cp + 2);
            for ($i = $startPage; $i <= $endPage; $i++):
            ?>
                <a href="<?php echo $pgUrl; ?>&page=<?php echo $i; ?>" 
                   class="btn btn-sm <?php echo ($i === $cp) ? 'btn-primary' : 'btn-ghost'; ?>" 
                   style="min-width: 28px; height: 26px; padding: 0 6px; font-size: 11.5px; font-weight: 600; display: inline-flex; align-items: center; justify-content: center; border-radius: 6px; <?php echo ($i === $cp) ? 'background:var(--accent, #c9a84c); color:#111111; border:1px solid var(--accent, #c9a84c);' : 'background:var(--surface, #FFFFFF); color:var(--text, #111111); border:1px solid var(--border, #E5E5E5);'; ?>">
                    <?php echo $i; ?>
                </a>
            <?php endfor; ?>

            <?php if ($cp < $tp): ?>
                <a href="<?php echo $pgUrl; ?>&page=<?php echo $cp + 1; ?>" class="btn btn-ghost btn-sm" style="padding: 4px 10px; font-size: 11.5px; background: var(--surface, #FFFFFF); border: 1px solid var(--border, #E5E5E5); color: var(--text, #111111); border-radius: 6px;">
                    Next <i class="fa-solid fa-arrow-right"></i>
                </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
function viewRequestDetailsFromRow(tr) {
    const dataAttr = tr.getAttribute('data-req');
    if (!dataAttr) return;
    
    try {
        const req = JSON.parse(dataAttr);
        if (typeof viewEventDetails === 'function') {
            viewEventDetails(req, false, { source: 'event_requests' });
        } else {
            const modal = document.getElementById('modal-view-event-details');
            if (modal) modal.classList.add('open');
        }
    } catch (e) {
        console.error('Failed to parse request data:', e);
    }
}

function toggleRequestActionBox(btn, e) {
    if (e) { e.preventDefault(); e.stopPropagation(); }
    const popover = btn.nextElementSibling;
    const isShown = (popover.style.display === 'block');
    closeAllReqActionBoxes();
    if (!isShown) {
        popover.style.display = 'block';
        btn.setAttribute('aria-expanded', 'true');
    }
}

function closeAllReqActionBoxes() {
    document.querySelectorAll('.req-action-popover').forEach(p => p.style.display = 'none');
    document.querySelectorAll('.btn-action-box').forEach(b => b.setAttribute('aria-expanded', 'false'));
}

document.addEventListener('click', closeAllReqActionBoxes);
</script>
