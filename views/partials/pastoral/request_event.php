<?php
/**
 * Request Event - Primary Submitter Form & Sizable Requests History View
 * Cathedral of St. Michael the Archangel • IFI
 */
$reqCounts     = $req_counts ?? [];
$totalReqs     = $reqCounts['total'] ?? count($my_requests);
$pendingCount  = $reqCounts['pending'] ?? count(array_filter($my_requests, fn($r) => $r['status'] === 'Pending'));
$approvedCount = $reqCounts['confirmed'] ?? count(array_filter($my_requests, fn($r) => $r['status'] === 'Confirmed'));
$rejectedCount = $reqCounts['rejected'] ?? count(array_filter($my_requests, fn($r) => in_array($r['status'], ['Cancelled','Rejected'])));

$curStatus    = $ev_filters['status'] ?? '';
$curType      = $ev_filters['event_type'] ?? '';
$curSort      = $ev_filters['sort'] ?? 'date_desc';
$curTimeframe = $ev_filters['timeframe'] ?? '';
$searchQuery  = $ev_filters['search'] ?? '';
$baseParams = [];
if (!empty($curType)) $baseParams['event_type'] = $curType;
if (!empty($curTimeframe)) $baseParams['timeframe'] = $curTimeframe;
if (!empty($searchQuery)) $baseParams['search'] = $searchQuery;
if (!empty($curSort) && $curSort !== 'date_desc') $baseParams['sort'] = $curSort;
$baseQuery    = !empty($baseParams) ? '&' . http_build_query($baseParams) : '';
$isAllStatus  = empty($curStatus);
$isTableFilterActive = !empty($curStatus) || !empty($curType) || !empty($searchQuery) || !empty($curTimeframe) || (isset($_GET['page']) && (int)$_GET['page'] > 1) || (isset($_GET['req_view']) && $_GET['req_view'] === 'table');
$activeReqView = $isTableFilterActive ? 'table' : 'form';
$isGuest = ($role === 'Guest') || empty($user_id) || !empty($isGuest);
$defaultTab = $_GET['tab'] ?? ($isGuest ? 'catalog' : ($totalReqs > 0 ? 'requests' : 'catalog'));
?>
<link rel="stylesheet" href="assets/css/workspace.css?v=2.5">

<div class="workspace req-workspace-root" style="min-height: 100%; flex: 1; display: flex; flex-direction: column; gap: 14px; width: 100%; max-width: 1680px; margin: 0 auto; padding: 14px clamp(16px, 1.8vw, 24px) 24px; box-sizing: border-box;">

    <!-- ─── 1. COMMAND HEADER WITH SERVICE VIEW TABS & PRIMARY "+ REQUEST SERVICE" BUTTON ─── -->
    <div class="req-command-header page-header-bar" style="display: flex; justify-content: space-between; align-items: center; flex-shrink: 0; border-bottom: 1px solid var(--border); padding-bottom: 8px; margin-bottom: 6px; flex-wrap: wrap; gap: 10px;">
        <div style="display: flex; align-items: center; gap: 14px;">
            <div class="page-header-icon" style="width: 38px; height: 38px; border-radius: 9px; background: rgba(201,168,76,0.14); color: var(--accent); display: flex; align-items: center; justify-content: center; font-size: 17px; flex-shrink: 0;">
                <i class="fa-solid fa-calendar-check"></i>
            </div>
            <div>
                <h1 class="page-title" style="font-family: var(--font-family, 'Inter', sans-serif); font-size: var(--font-page-title, 20px); font-weight: 700; color: var(--text); margin: 0; line-height: 1.2;">
                    <?php echo $isGuest ? 'Sacramental &amp; Liturgical Services' : 'Parish Services &amp; Requests'; ?>
                </h1>
                <p class="page-subtitle" style="font-size: var(--font-page-subtitle, 14px); color: var(--text2); margin: 3px 0 0 0;">
                    <?php echo $isGuest ? 'Browse ecclesiastical rites, offerings, and request sacramental dates' : 'Track and view your requests for baptisms, weddings, blessings, and Masses'; ?>
                </p>
            </div>
        </div>

        <!-- Controls: Services Menu vs My Requests tabs + "+ Request Service" Button -->
        <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
            <div class="service-view-tabs" style="display: inline-flex; background: var(--surface); padding: 3px; border-radius: 8px; border: 1px solid var(--border); gap: 2px;">
                <button type="button" class="btn-tab-service <?php echo ($defaultTab === 'catalog') ? 'active' : ''; ?>" id="tab-btn-catalog" onclick="switchServiceViewTab('catalog')" style="border: none; background: <?php echo ($defaultTab === 'catalog') ? 'var(--accent-soft, rgba(201,168,76,0.15))' : 'transparent'; ?>; color: <?php echo ($defaultTab === 'catalog') ? 'var(--accent)' : 'var(--text2)'; ?>; padding: 6px 14px; font-size: 12px; font-weight: 600; border-radius: 6px; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; transition: all 0.15s ease;">
                    <i class="fa-solid fa-layer-group"></i>
                    <span>Services Menu</span>
                </button>
                <button type="button" class="btn-tab-service <?php echo ($defaultTab === 'requests') ? 'active' : ''; ?>" id="tab-btn-requests" onclick="switchServiceViewTab('requests')" style="border: none; background: <?php echo ($defaultTab === 'requests') ? 'var(--accent-soft, rgba(201,168,76,0.15))' : 'transparent'; ?>; color: <?php echo ($defaultTab === 'requests') ? 'var(--accent)' : 'var(--text2)'; ?>; padding: 6px 14px; font-size: 12px; font-weight: 600; border-radius: 6px; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; transition: all 0.15s ease;">
                    <i class="fa-solid fa-clock-rotate-left"></i>
                    <span>My Requests</span>
                    <span class="badge" style="background: var(--bg2); border: 1px solid var(--border); font-size: 10px; padding: 1px 6px; border-radius: 999px;"><?php echo (int)$totalReqs; ?></span>
                </button>
            </div>

            <!-- The Big Request Event Pop-up Trigger Button -->
            <button type="button" class="btn-request-popup-trigger" onclick="openRequestEventModal()" style="height: 36px; padding: 0 16px; border-radius: 8px; border: none; background: var(--accent, #c5a059); color: #111111; font-size: 12.5px; font-weight: 700; display: inline-flex; align-items: center; gap: 7px; cursor: pointer; box-shadow: 0 2px 10px rgba(201,168,76,0.25); transition: all 0.18s ease;">
                <i class="fa-solid fa-plus" style="font-size: 12px;"></i>
                <span>Request Service</span>
            </button>
        </div>
    </div>

    <!-- Draft restore notification (if draft exists in sessionStorage) -->
    <div id="draft-restore-banner" style="display: none; background: rgba(34,197,94,0.1); border: 1px solid rgba(34,197,94,0.3); border-radius: 10px; padding: 10px 16px; align-items: center; justify-content: space-between; gap: 10px; margin-bottom: 2px;">
        <div style="display: flex; align-items: center; gap: 8px; font-size: 12px; color: var(--text);">
            <i class="fa-solid fa-file-pen" style="color: #22c55e;"></i>
            <span>You have an unsubmitted request draft for: <strong id="draft-service-name">Service Request</strong></span>
        </div>
        <div style="display: flex; gap: 8px;">
            <button type="button" class="btn btn-primary btn-sm" onclick="resumeDraft()" style="height: 28px; font-size: 11px; padding: 0 12px; border-radius: 6px; cursor: pointer;">
                Resume &amp; Submit
            </button>
            <button type="button" class="btn btn-ghost btn-sm" onclick="discardDraft()" style="height: 28px; font-size: 11px; padding: 0 10px; border-radius: 6px; cursor: pointer;">
                Discard
            </button>
        </div>
    </div>

    <?php if ($isGuest): ?>
    <!-- Guest Preview Banner -->
    <div class="guest-notice-banner" style="background: rgba(197,160,89,0.08); border: 1px solid rgba(197,160,89,0.25); border-radius: 10px; padding: 12px 18px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
        <div style="display: flex; align-items: center; gap: 12px;">
            <div style="width: 32px; height: 32px; border-radius: 8px; background: rgba(197,160,89,0.18); color: var(--accent); display: flex; align-items: center; justify-content: center; font-size: 15px; flex-shrink: 0;">
                <i class="fa-solid fa-circle-info"></i>
            </div>
            <div>
                <div style="font-size: 12.5px; font-weight: 700; color: var(--text);">Guest Preview Mode</div>
                <div style="font-size: 11.5px; color: var(--text2); margin-top: 2px;">
                    Explore Cathedral sacramental and liturgical services below. You can prepare and fill out a request anytime; when you submit or save, you will be prompted to sign up or log in as a member to finalize your booking with the Cathedral office.
                </div>
            </div>
        </div>
        <div style="display: flex; gap: 8px;">
            <a href="register.php" class="btn btn-primary btn-sm" style="font-size: 11.5px; padding: 6px 14px; text-decoration: none; border-radius: 6px; font-weight: 600;">
                <i class="fa-solid fa-user-plus text-gold"></i> Register as Member
            </a>
            <a href="logout.php?redirect=index.php?open_login=1" class="btn btn-ghost btn-sm" style="font-size: 11.5px; padding: 6px 14px; text-decoration: none; border: 1px solid var(--border); border-radius: 6px; font-weight: 600;">
                <i class="fa-solid fa-arrow-right-to-bracket text-gold"></i> Sign In
            </a>
        </div>
    </div>
    <?php endif; ?>

    <!-- ─── 2A. SERVICES MENU & CATALOG PANE ─── -->
    <div id="service-view-catalog-pane" style="display: <?php echo ($defaultTab === 'catalog') ? 'flex' : 'none'; ?>; flex-direction: column; gap: 16px;">
        <div class="services-catalog-header-bar" style="background: var(--surface); border: 1px solid var(--border); border-radius: 10px; padding: 14px 18px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
            <div>
                <div style="font-weight: 700; font-size: 14px; color: var(--text); display: flex; align-items: center; gap: 8px;">
                    <i class="fa-solid fa-book-bible" style="color: var(--accent);"></i>
                    <span>Parish Rites &amp; Liturgical Schedule</span>
                </div>
                <div style="font-size: 12px; color: var(--text2); margin-top: 2px;">
                    Select any sacramental rite or liturgy to schedule dates, choose rate tiers, and review canonical requirements
                </div>
            </div>
            <div style="display: flex; gap: 8px; align-items: center;">
                <span class="badge" style="background: var(--bg2); border: 1px solid var(--border); color: var(--text2); font-size: 11px; padding: 4px 10px; border-radius: 999px;">
                    <i class="fa-solid fa-circle-check" style="color: var(--accent); font-size: 9.5px; margin-right: 4px;"></i> Synod Rate Schedule
                </span>
            </div>
        </div>

        <?php if (!empty($services_catalog)): ?>
            <?php foreach ($services_catalog as $cat): 
                $cServices = $cat['services'] ?? [];
                if (empty($cServices)) continue;
                $catCode = strtolower($cat['code'] ?? '');
            ?>
            <div class="service-cat-section" style="background: var(--surface); border: 1px solid var(--border); border-radius: 10px; padding: 16px 20px;">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px; padding-bottom: 8px; border-bottom: 1px solid var(--border);">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <span style="width: 28px; height: 28px; border-radius: 6px; background: rgba(201,168,76,0.14); color: var(--accent); display: inline-flex; align-items: center; justify-content: center; font-size: 13px;">
                            <?php 
                            if (strpos($catCode, 'sacrament') !== false) echo '<i class="fa-solid fa-water"></i>';
                            elseif (strpos($catCode, 'liturg') !== false) echo '<i class="fa-solid fa-church"></i>';
                            elseif (strpos($catCode, 'bless') !== false) echo '<i class="fa-solid fa-hands-praying"></i>';
                            elseif (strpos($catCode, 'cert') !== false) echo '<i class="fa-solid fa-file-invoice"></i>';
                            else echo '<i class="fa-solid fa-cross"></i>';
                            ?>
                        </span>
                        <div>
                            <h2 style="font-size: 14px; font-weight: 700; color: var(--text); margin: 0; line-height: 1.2;">
                                <?php echo htmlspecialchars($cat['name']); ?>
                            </h2>
                            <?php if (!empty($cat['description'])): ?>
                            <p style="font-size: 11.5px; color: var(--text2); margin: 2px 0 0 0;"><?php echo htmlspecialchars($cat['description']); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <span class="badge" style="background: var(--bg2); border: 1px solid var(--border); color: var(--text2); font-size: 10.5px; padding: 3px 8px; border-radius: 999px;">
                        <?php echo count($cServices); ?> <?php echo count($cServices) === 1 ? 'service' : 'services'; ?>
                    </span>
                </div>

                <div class="service-cards-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(290px, 1fr)); gap: 14px;">
                    <?php foreach ($cServices as $srv): 
                        $srvCode = strtolower($srv['code'] ?? $srv['name']);
                        $isCert = (strpos($srvCode, 'certificate') !== false || strpos(strtolower($srv['name']), 'certificate') !== false);
                        
                        $iconClass = 'fa-church';
                        if (strpos($srvCode, 'baptism') !== false) $iconClass = 'fa-water';
                        elseif (strpos($srvCode, 'confirmation') !== false) $iconClass = 'fa-dove';
                        elseif (strpos($srvCode, 'wedding') !== false || strpos($srvCode, 'marriage') !== false) $iconClass = 'fa-ring';
                        elseif (strpos($srvCode, 'thanksgiving') !== false) $iconClass = 'fa-hand-holding-heart';
                        elseif (strpos($srvCode, 'burial') !== false || strpos($srvCode, 'funeral') !== false) $iconClass = 'fa-cross';
                        elseif (strpos($srvCode, 'blessing') !== false) $iconClass = 'fa-hands-praying';
                        elseif (strpos($srvCode, 'requiem') !== false) $iconClass = 'fa-cross';
                        elseif ($isCert) $iconClass = 'fa-file-shield';

                        $minPrice = null;
                        if (!empty($srv['variants'])) {
                            foreach ($srv['variants'] as $v) {
                                $p = (float)($v['price'] ?? 0);
                                if ($minPrice === null || $p < $minPrice) $minPrice = $p;
                            }
                        }
                        $priceStr = ($minPrice !== null && $minPrice > 0) ? 'From ₱' . number_format($minPrice, 2) : 'Parish Synod Stipend';
                        $reqs = $srv['requirements'] ?? [];
                    ?>
                    <div class="service-card" style="background: var(--bg2); border: 1px solid var(--border); border-radius: 10px; padding: 14px 16px; display: flex; flex-direction: column; justify-content: space-between; gap: 12px; transition: transform 0.15s ease, box-shadow 0.15s ease;">
                        <div>
                            <div style="display: flex; align-items: flex-start; justify-content: space-between; gap: 10px; margin-bottom: 8px;">
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <div style="width: 36px; height: 36px; border-radius: 8px; background: rgba(201,168,76,0.12); color: var(--accent); display: flex; align-items: center; justify-content: center; font-size: 15px; flex-shrink: 0; border: 1px solid rgba(201,168,76,0.25);">
                                        <i class="fa-solid <?php echo $iconClass; ?>"></i>
                                    </div>
                                    <div>
                                        <h3 style="font-size: 13.5px; font-weight: 700; color: var(--text); margin: 0; line-height: 1.25;">
                                            <?php echo htmlspecialchars($srv['name']); ?>
                                        </h3>
                                        <div style="font-size: 11px; color: var(--text2); margin-top: 2px;">
                                            <?php echo htmlspecialchars($cat['name']); ?>
                                        </div>
                                    </div>
                                </div>
                                <span class="badge" style="background: var(--surface); border: 1px solid var(--border); font-size: 11px; font-weight: 700; color: var(--accent); padding: 3px 8px; border-radius: 6px; white-space: nowrap;">
                                    <?php echo $priceStr; ?>
                                </span>
                            </div>

                            <?php if (!empty($srv['description'])): ?>
                            <p style="font-size: 12px; color: var(--text2); margin: 0 0 10px 0; line-height: 1.45; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                                <?php echo htmlspecialchars($srv['description']); ?>
                            </p>
                            <?php endif; ?>

                            <?php if (!empty($reqs)): ?>
                            <div style="display: flex; flex-wrap: wrap; gap: 4px; margin-bottom: 4px;">
                                <?php foreach (array_slice($reqs, 0, 3) as $rq): ?>
                                <span style="font-size: 10px; background: var(--surface); border: 1px solid var(--border); color: var(--text2); padding: 2px 6px; border-radius: 4px;">
                                    <i class="fa-solid fa-check" style="color: #22c55e; font-size: 8px;"></i> <?php echo htmlspecialchars($rq['name']); ?>
                                </span>
                                <?php endforeach; ?>
                                <?php if (count($reqs) > 3): ?>
                                <span style="font-size: 10px; color: var(--text2); padding: 2px 4px;">+<?php echo count($reqs) - 3; ?> more</span>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                        </div>

                        <div style="border-top: 1px solid var(--border); padding-top: 10px; display: flex; justify-content: space-between; align-items: center; gap: 8px;">
                            <span style="font-size: 11px; color: var(--text2);">
                                <?php echo count($srv['variants'] ?? []); ?> tier option<?php echo count($srv['variants'] ?? []) === 1 ? '' : 's'; ?>
                            </span>
                            <?php if ($isCert): ?>
                            <a href="dashboard/request_certificate" class="btn btn-primary btn-sm" style="font-size: 11.5px; padding: 6px 14px; border-radius: 6px; text-decoration: none; display: inline-flex; align-items: center; gap: 6px;">
                                <i class="fa-solid fa-file-invoice"></i> Certificates
                            </a>
                            <?php else: ?>
                            <button type="button" class="btn btn-primary btn-sm" onclick="openRequestEventModal('<?php echo htmlspecialchars($srv['name'], ENT_QUOTES); ?>')" style="font-size: 11.5px; padding: 6px 14px; border-radius: 6px; cursor: pointer; display: inline-flex; align-items: center; gap: 6px;">
                                <i class="fa-solid fa-calendar-plus"></i> Request Service
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- ─── 2B. MAIN FULL-WIDTH REQUESTS WORKSPACE (MY REQUESTS PANE) ─── -->
    <div id="service-view-requests-pane" style="display: <?php echo ($defaultTab === 'requests') ? 'flex' : 'none'; ?>; flex-direction: column; flex: 1; min-height: 0;">
    <div class="ent-card" style="border-radius: 10px; border: 1px solid var(--border); background: var(--surface); box-shadow: 0 2px 10px rgba(0,0,0,0.04); display: flex; flex-direction: column; overflow: hidden; flex: 1; min-height: 0;">
        
        <!-- Streamlined Single-Row Toolbar: Status Tabs on Left, Filters on Right -->
        <div style="padding: 10px 16px; border-bottom: 1px solid var(--border); background: var(--bg3); display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
            <!-- Left: Minimal Status Tabs -->
            <div class="req-minimal-tabs" style="display: inline-flex; background: var(--surface); padding: 3px; border-radius: 8px; border: 1px solid var(--border); gap: 2px;">
                <a href="dashboard.php?section=request_event<?php echo $baseQuery; ?>" 
                   class="req-tab-item <?php echo $isAllStatus ? 'active' : ''; ?>">
                    All (<?php echo $totalReqs; ?>)
                </a>
                <a href="dashboard.php?section=request_event&status=Confirmed<?php echo $baseQuery; ?>" 
                   class="req-tab-item <?php echo ($curStatus === 'Confirmed') ? 'active' : ''; ?>">
                    Approved <?php if ($approvedCount > 0): ?>(<?php echo $approvedCount; ?>)<?php endif; ?>
                </a>
                <a href="dashboard.php?section=request_event&status=Pending<?php echo $baseQuery; ?>" 
                   class="req-tab-item <?php echo ($curStatus === 'Pending') ? 'active' : ''; ?>">
                    Pending <?php if ($pendingCount > 0): ?>(<?php echo $pendingCount; ?>)<?php endif; ?>
                </a>
                <?php if ($rejectedCount > 0 || $curStatus === 'Cancelled' || $curStatus === 'Rejected'): ?>
                <a href="dashboard.php?section=request_event&status=Cancelled<?php echo $baseQuery; ?>" 
                   class="req-tab-item <?php echo ($curStatus === 'Cancelled' || $curStatus === 'Rejected') ? 'active' : ''; ?>">
                    Rejected <?php if ($rejectedCount > 0): ?>(<?php echo $rejectedCount; ?>)<?php endif; ?>
                </a>
                <?php endif; ?>
            </div>

            <!-- Right: Compact Filters (Timeframe, Event Type, Sort, Search) -->
            <form method="GET" style="display: flex; align-items: center; gap: 6px; margin: 0; flex-wrap: wrap;">
                <input type="hidden" name="section" value="request_event">
                <?php if (!empty($curStatus)): ?>
                <input type="hidden" name="status" value="<?php echo htmlspecialchars($curStatus); ?>">
                <?php endif; ?>

                <!-- Timeframe Filter -->
                <select name="timeframe" onchange="this.form.submit()" 
                        style="height: 34px !important; min-width: 110px; font-size: 12px; border-radius: 8px; padding: 0 10px; cursor: pointer; border: 1px solid var(--border); background: var(--bg2); color: var(--text); outline: none;">
                    <option value="">All Dates</option>
                    <option value="this_week" <?php echo ($curTimeframe === 'this_week') ? 'selected' : ''; ?>>This Week</option>
                    <option value="next_week" <?php echo ($curTimeframe === 'next_week') ? 'selected' : ''; ?>>Next Week</option>
                    <option value="this_month" <?php echo ($curTimeframe === 'this_month') ? 'selected' : ''; ?>>This Month</option>
                    <option value="upcoming" <?php echo ($curTimeframe === 'upcoming') ? 'selected' : ''; ?>>Upcoming</option>
                    <option value="past" <?php echo ($curTimeframe === 'past') ? 'selected' : ''; ?>>Past</option>
                </select>

                <!-- Event Type Filter -->
                <select name="event_type" onchange="this.form.submit()" 
                        style="height: 34px !important; min-width: 120px; font-size: 12px; border-radius: 8px; padding: 0 10px; cursor: pointer; border: 1px solid var(--border); background: var(--bg2); color: var(--text); outline: none;">
                    <?php echo render_event_type_options($curType, 'All Types'); ?>
                </select>

                <!-- Sort Toggle Icon (Up / Down) -->
                <input type="hidden" name="sort" id="sort-request-event" value="<?php echo htmlspecialchars($curSort); ?>">
                <button type="button" class="btn-sort-toggle" onclick="toggleSortDirection(this, 'sort-request-event')"
                        title="<?php echo ($curSort === 'date_asc') ? 'Sorted: Oldest First' : 'Sorted: Newest First'; ?>"
                        style="height: 34px; width: 34px; min-width: 34px; padding: 0; display: inline-flex; align-items: center; justify-content: center; border-radius: 8px; border: 1px solid var(--border); background: var(--bg2); color: var(--text); cursor: pointer; transition: all 0.15s ease;" aria-label="Toggle sort order">
                    <i class="fa-solid <?php echo ($curSort === 'date_asc') ? 'fa-arrow-up-wide-short' : 'fa-arrow-down-wide-short'; ?>" style="font-size: 12px;"></i>
                </button>

                <!-- Search Input with Clear Button -->
                <div style="position: relative; width: 190px;">
                    <i class="fa-solid fa-magnifying-glass" style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: var(--text2); font-size: 11.5px; pointer-events: none;"></i>
                    <input type="text" 
                           name="search" 
                           value="<?php echo htmlspecialchars($searchQuery); ?>" 
                           placeholder="Search requests..." 
                           style="width: 100%; height: 34px; padding-left: 30px !important; padding-right: <?php echo !empty($searchQuery) ? '26px' : '10px'; ?> !important; font-size: 12.5px; border-radius: 8px; border: 1px solid var(--border); background: var(--bg2); color: var(--text); box-sizing: border-box; outline: none; transition: border-color 0.15s ease;">
                    <?php if (!empty($searchQuery)): ?>
                    <a href="dashboard.php?section=request_event<?php echo !empty($curStatus) ? '&status=' . urlencode($curStatus) : ''; ?><?php echo !empty($curType) ? '&event_type=' . urlencode($curType) : ''; ?><?php echo !empty($curTimeframe) ? '&timeframe=' . urlencode($curTimeframe) : ''; ?>&sort=<?php echo urlencode($curSort); ?>" 
                       title="Clear search" 
                       style="position: absolute; right: 8px; top: 50%; transform: translateY(-50%); color: var(--text2); text-decoration: none; font-size: 14px; line-height: 1; padding: 2px;">&times;</a>
                    <?php endif; ?>
                </div>

                <?php if (!empty($searchQuery) || !empty($curStatus) || !empty($curType) || !empty($curTimeframe) || $curSort !== 'date_desc'): ?>
                    <a href="dashboard.php?section=request_event" class="btn btn-ghost btn-sm" title="Clear Filters" style="height: 34px; padding: 0 10px; font-size: 11.5px; color: var(--text2); background: var(--bg2); border: 1px solid var(--border); border-radius: 8px; display: inline-flex; align-items: center; justify-content: center; gap: 4px; text-decoration: none;">
                        <i class="fa-solid fa-rotate-left"></i> Reset
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Live Requests Data Table -->
        <div class="ent-card-body" style="padding: 0; overflow-y: auto; overflow-x: auto; flex: 1; min-height: 280px;">
            <?php if (empty($my_requests)): ?>
                <div class="table-empty-state" style="padding: 60px 20px; text-align: center; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 10px;">
                    <div style="width: 48px; height: 48px; border-radius: 50%; background: var(--bg2); display: inline-flex; align-items: center; justify-content: center; border: 1px solid var(--border); color: var(--accent); font-size: 18px;">
                        <i class="fa-solid fa-folder-open"></i>
                    </div>
                    <div>
                        <div style="font-size: 15px; font-weight: 600; color: var(--text);">No Requests Found</div>
                        <p style="font-size: 12.5px; color: var(--text2); margin: 4px 0 0 0; max-width: 320px;">
                            <?php echo (!empty($searchQuery) || !empty($curStatus) || !empty($curType)) ? 'No requests match your current search or filter criteria.' : 'You haven\'t submitted any event requests yet.'; ?>
                        </p>
                    </div>
                    <div style="display: flex; gap: 8px; margin-top: 4px;">
                        <?php if (!empty($searchQuery) || !empty($curStatus) || !empty($curType)): ?>
                        <a href="dashboard.php?section=request_event" class="btn btn-ghost btn-sm" style="height: 34px; padding: 0 14px; font-size: 12px; border-radius: 8px; border: 1px solid var(--border); display: inline-flex; align-items: center; gap: 6px; text-decoration: none;">
                            <i class="fa-solid fa-rotate-left"></i> Reset Filters
                        </a>
                        <?php endif; ?>
                        <button type="button" class="btn btn-primary btn-sm" onclick="openRequestEventModal()" style="height: 34px; font-size: 12px; padding: 0 16px; border-radius: 8px; display: inline-flex; align-items: center; gap: 6px;">
                            <i class="fa-solid fa-plus"></i> Submit Request
                        </button>
                    </div>
                </div>
            <?php else: ?>
            <table class="data-table" id="requests-table" style="width: 100%; min-width: 650px; table-layout: fixed; border-collapse: collapse;">
                <thead style="position: sticky; top: 0; background: var(--bg3); z-index: 5; border-bottom: 1px solid var(--border);">
                    <tr>
                        <th style="padding: 10px 14px; font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text2); font-weight: 700; text-align: left;">Event Title &amp; Location</th>
                        <th style="padding: 10px 10px; font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text2); width: 110px; text-align: center; font-weight: 700;">Type</th>
                        <th style="padding: 10px 12px; font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text2); width: 140px; font-weight: 700; text-align: left;">Requested Date</th>
                        <th style="padding: 10px 10px; font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text2); width: 110px; text-align: center; font-weight: 700;">Status</th>
                        <th style="padding: 10px 10px; font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text2); width: 100px; text-align: center; font-weight: 700;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($my_requests as $idx => $req):
                        $st = $req['status'];
                        $isOverdue = $st === 'Pending' && !empty($req['start_time']) && strtotime($req['start_time']) < time();
                        $stText    = $isOverdue ? 'Overdue' : $st;
                    ?>
                    <tr class="req-row" style="cursor: pointer; border-bottom: 1px solid var(--border);" onclick="viewRequestDetailsFromRow(this)" data-req="<?php echo htmlspecialchars(json_encode($req), ENT_QUOTES, 'UTF-8'); ?>" title="Click row to view full details">
                        <td style="padding: 10px 14px; white-space: normal;">
                            <div style="font-weight: 700; font-size: 13px; color: var(--text); line-height: 1.3; overflow-wrap: break-word;"><?php echo htmlspecialchars($req['title']); ?></div>
                            <div style="font-size: 11px; color: var(--text2); margin-top: 2px;">
                                <i class="fa-solid fa-location-dot" style="font-size: 9.5px; color: var(--accent);"></i>
                                <?php echo htmlspecialchars($req['chapel_location'] ?: 'Main Cathedral'); ?>
                            </div>
                        </td>
                        <td style="padding: 10px 10px; text-align: center;">
                            <span class="badge" style="background: var(--bg2); color: var(--text); border: 1px solid var(--border); font-size: 10.5px; padding: 2px 7px; font-weight: 600; border-radius: 4px;"><?php echo htmlspecialchars($req['event_type']); ?></span>
                        </td>
                        <td style="padding: 10px 12px;">
                            <div style="font-weight: 600; font-size: 12.5px; color: var(--text); white-space: nowrap;">
                                <i class="fa-regular fa-calendar" style="color: var(--accent); margin-right: 4px; font-size: 10.5px;"></i>
                                <?php echo date('M d, Y', strtotime($req['start_time'])); ?>
                            </div>
                        </td>
                        <td style="padding: 10px 10px; text-align: center;">
                            <?php if ($st === 'Confirmed'): ?>
                                <span class="badge" style="background: rgba(34,197,94,0.12); color: #16a34a; border: 1px solid rgba(34,197,94,0.3); font-weight: 700; font-size: 10.5px; padding: 3px 8px; border-radius: 5px; display: inline-flex; align-items: center; gap: 4px;">
                                    <i class="fa-solid fa-circle-check" style="font-size: 8px;"></i> Confirmed
                                </span>
                            <?php elseif ($isOverdue): ?>
                                <span class="badge" style="background: rgba(239,68,68,0.12); color: #dc2626; border: 1px solid rgba(239,68,68,0.3); font-weight: 700; font-size: 10.5px; padding: 3px 8px; border-radius: 5px; display: inline-flex; align-items: center; gap: 4px;">
                                    <i class="fa-solid fa-triangle-exclamation" style="font-size: 8px;"></i> Overdue
                                </span>
                            <?php elseif ($st === 'Cancelled' || $st === 'Rejected'): ?>
                                <span class="badge" style="background: rgba(239,68,68,0.1); color: #dc2626; border: 1px solid rgba(239,68,68,0.25); font-weight: 700; font-size: 10.5px; padding: 3px 8px; border-radius: 5px; display: inline-flex; align-items: center; gap: 4px;">
                                    <i class="fa-solid fa-ban" style="font-size: 8px;"></i> <?php echo htmlspecialchars($stText); ?>
                                </span>
                            <?php else: ?>
                                <span class="badge" style="background: rgba(201,168,76,0.14); color: var(--accent); border: 1px solid rgba(201,168,76,0.35); font-weight: 700; font-size: 10.5px; padding: 3px 8px; border-radius: 5px; display: inline-flex; align-items: center; gap: 4px;">
                                    <i class="fa-solid fa-clock" style="font-size: 8px;"></i> Pending
                                </span>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 10px 10px; text-align: center;" onclick="event.stopPropagation()">
                            <div style="display: flex; gap: 5px; justify-content: center; align-items: center;">
                                <button type="button" class="btn-action-icon" onclick="viewRequestDetailsFromRow(this.closest('tr'))" title="View Details">
                                    <i class="fa-solid fa-eye"></i>
                                </button>
                                <?php if ($st === 'Pending'): ?>
                                <button type="button" class="btn-action-icon" data-id="<?php echo (int)$req['event_id']; ?>" data-name="<?php echo htmlspecialchars($req['title'], ENT_QUOTES, 'UTF-8'); ?>" onclick="cancelRequest(this)" title="Cancel Request" style="color: #dc2626;">
                                    <i class="fa-solid fa-ban"></i>
                                </button>
                                <?php endif; ?>
                                <button type="button" class="btn-action-icon btn-action-danger" data-id="<?php echo (int)$req['event_id']; ?>" data-name="<?php echo htmlspecialchars($req['title'], ENT_QUOTES, 'UTF-8'); ?>" onclick="deleteRequest(this)" title="Delete Request">
                                    <i class="fa-solid fa-trash-can"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        <!-- Pagination Footer -->
        <?php if (($total_pages ?? 1) > 1):
            $cp = max(1, (int)($current_page ?? 1));
            $tp = max(1, (int)($total_pages ?? 1));
        ?>
        <div class="ent-card-footer" style="display: flex; justify-content: center; gap: 6px; border-top: 1px solid var(--border); padding: 10px 16px; background: var(--bg3);">
            <?php if ($cp > 1): ?>
            <a href="?section=request_event&page=<?php echo $cp - 1; ?><?php echo $baseQuery; ?>"
               class="btn btn-ghost btn-sm" style="padding: 4px 12px; height: 30px; font-size: 12px;">
                <i class="fa-solid fa-arrow-left"></i> Prev
            </a>
            <?php endif; ?>
            <?php for ($i = max(1, $cp - 2); $i <= min($tp, $cp + 2); $i++): ?>
            <a href="?section=request_event&page=<?php echo $i; ?><?php echo $baseQuery; ?>"
               class="btn btn-sm <?php echo $i === $cp ? 'btn-primary' : 'btn-ghost'; ?>"
               style="padding: 4px 12px; height: 30px; min-width: 32px; display: inline-flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 700; border-radius: 6px;">
                <?php echo $i; ?>
            </a>
            <?php endfor; ?>
            <?php if ($cp < $tp): ?>
            <a href="?section=request_event&page=<?php echo $cp + 1; ?><?php echo $baseQuery; ?>"
               class="btn btn-ghost btn-sm" style="padding: 4px 12px; height: 30px; font-size: 12px;">
                Next <i class="fa-solid fa-arrow-right"></i>
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
    </div> <!-- Closing #service-view-requests-pane -->
</div>

<!-- ─── 3. SIZABLE REQUEST EVENT POP-UP MODAL (Opens via "+ Request Event" Button) ─── -->
<div class="modal-overlay" id="request-event-modal" style="position: fixed; inset: 0; z-index: 99999; align-items: center; justify-content: center; background: rgba(0,0,0,0.6); backdrop-filter: blur(4px);">
    <div class="modal" style="max-width: 580px; width: 92%; max-height: 88vh; border-radius: 12px; background: var(--surface); color: var(--text); border: 1px solid var(--border); box-shadow: 0 16px 40px rgba(0,0,0,0.3); display: flex; flex-direction: column; overflow: hidden; padding: 0;">
        <!-- Modal Header -->
        <div style="padding: 14px 18px; background: var(--bg3); border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; flex-shrink: 0;">
            <div style="display: flex; align-items: center; gap: 10px;">
                <span style="display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; border-radius: 6px; background: var(--accent); color: #111; font-size: 13px;">
                    <i class="fa-solid fa-pen-nib"></i>
                </span>
                <div>
                    <h3 style="font-family: var(--font-family, 'Inter', sans-serif); font-size: 14.5px; font-weight: 700; color: var(--text); margin: 0; line-height: 1.2;">
                        Request a Parish Service
                    </h3>
                    <p style="font-size: 11.5px; color: var(--text2); margin: 2px 0 0 0;">Fill in the details to schedule a baptism, wedding, blessing, or Mass</p>
                </div>
            </div>
            <button type="button" class="btn-icon" onclick="closeRequestEventModal()" style="border: none; background: transparent; font-size: 18px; color: var(--text2); cursor: pointer; padding: 4px 8px; border-radius: 5px;">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <!-- Modal Body: Compact Form -->
        <div style="padding: 14px 18px; overflow-y: auto; flex: 1;">
            <form method="POST" action="dashboard.php" id="request-event-form" style="display: flex; flex-direction: column; gap: 10px;">
                <input type="hidden" name="form_section" value="event_request">
                <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">

                <!-- Hidden Service Catalog Inputs -->
                <input type="hidden" name="service_id" id="field-service-id" value="">
                <input type="hidden" name="variant_id" id="field-variant-id" value="">
                <input type="hidden" name="location_type" id="field-location-type" value="Church">

                <!-- 1. Basic Information -->
                <div class="req-section-block" style="background: var(--bg3); border: 1px solid var(--border); border-radius: 8px; padding: 10px 12px;">
                    <div class="req-section-title" style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.06em; color: var(--text); font-weight: 700; margin-bottom: 8px; padding-bottom: 4px; border-bottom: 1px solid var(--border); display: flex; align-items: center; gap: 6px;">
                        <i class="fa-solid fa-align-left" style="color: var(--accent);"></i> Basic Information
                    </div>
                    <div style="display: flex; flex-direction: column; gap: 8px;">
                        <div class="form-group" style="margin: 0;">
                            <label class="form-label" style="font-size: 12px; font-weight: 600; color: var(--text); margin-bottom: 4px; display: block;">
                                Service / Event Type <span style="color:#ef4444;">*</span>
                            </label>
                            <select name="event_type" id="field-event-type-request" required onchange="handleServiceCatalogChange(this); if (typeof onFormChange === 'function') onFormChange('request');" 
                                    class="req-compact-control"
                                    style="padding: 0 28px 0 10px !important; height: 34px; min-height: 34px; font-size: 12.5px; box-sizing: border-box; width: 100%; border-radius: 6px; border: 1px solid var(--border); background: var(--bg2); color: var(--text); cursor: pointer;">
                                <?php echo render_event_type_options('', 'Select Service / Rite'); ?>
                            </select>
                        </div>
                        <div class="form-group" style="margin: 0;">
                            <label class="form-label" id="field-title-label" style="font-size: 12px; font-weight: 600; color: var(--text); margin-bottom: 4px; display: block;">
                                Event / Service Title <span style="color:#ef4444;">*</span>
                            </label>
                            <input type="text" id="field-title-request" name="title" required placeholder="e.g. Wedding Mass or Thanksgiving" 
                                   class="req-compact-control"
                                   style="padding: 0 10px; height: 34px; font-size: 12.5px; box-sizing: border-box; width: 100%; border-radius: 6px; border: 1px solid var(--border); background: var(--bg2); color: var(--text);">
                        </div>
                    </div>
                </div>

                <!-- 2. Service Options & Live Fee Calculation -->
                <div id="service-catalog-panel" style="display: none; background: var(--bg3); border: 1px solid var(--border); border-radius: 8px; padding: 10px 12px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; padding-bottom: 4px; border-bottom: 1px solid var(--border);">
                        <span style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.06em; color: var(--text); font-weight: 700; display: flex; align-items: center; gap: 6px;">
                            <i class="fa-solid fa-layer-group" style="color: var(--accent);"></i> Service Rate Tier
                        </span>
                        <span id="rate-badge-reference" class="badge" style="background: var(--surface); color: var(--text2); border: 1px solid var(--border); font-size: 10px; padding: 2px 6px; font-weight: 600; border-radius: 4px;">
                            Synod 2012
                        </span>
                    </div>

                    <!-- Variant Selection -->
                    <div id="variant-selection-group" class="form-group" style="margin-bottom: 8px;">
                        <label class="form-label" style="font-size: 12px; margin-bottom: 4px; font-weight: 600; color: var(--text); display: block;">
                            Option / Rate Tier <span style="color:#ef4444;">*</span>
                        </label>
                        <select id="field-variant-selector" onchange="handleVariantChange(this)" 
                                class="req-compact-control"
                                style="padding: 0 28px 0 10px !important; height: 34px; min-height: 34px; font-size: 12.5px; box-sizing: border-box; width: 100%; border-radius: 6px; background: var(--bg2); color: var(--text); border: 1px solid var(--border); cursor: pointer;">
                            <option value="">Select Option</option>
                        </select>
                        <div id="variant-detail-hint" style="font-size: 11px; color: var(--text2); margin-top: 3px;"></div>
                    </div>

                    <!-- Optional Additional Services -->
                    <div id="addons-selection-group" style="display: none; margin-bottom: 8px;">
                        <label class="form-label" style="font-size: 12px; margin-bottom: 4px; font-weight: 600; color: var(--text); display: block;">Additional Services</label>
                        <div id="addons-checkboxes-container" style="display: flex; flex-direction: column; gap: 5px;"></div>
                    </div>

                    <!-- Requirements Checklist -->
                    <div id="requirements-display-group" style="background: var(--surface); border: 1px solid var(--border); border-radius: 6px; padding: 8px 10px; margin-bottom: 8px;">
                        <div style="font-size: 11px; font-weight: 700; color: var(--text); margin-bottom: 4px; display: flex; align-items: center; gap: 5px;">
                            <i class="fa-solid fa-list-check" style="color: var(--accent);"></i> Canonical Requirements:
                        </div>
                        <ul id="requirements-list" style="margin: 0; padding-left: 16px; font-size: 11.5px; color: var(--text2); line-height: 1.45;"></ul>
                    </div>

                    <!-- Live Dynamic Fee Box -->
                    <div style="background: var(--bg2); border: 1px solid var(--border); border-radius: 6px; padding: 8px 12px;">
                        <div style="display: flex; justify-content: space-between; font-size: 12px; color: var(--text2); margin-bottom: 2px;">
                            <span>Base Rate:</span>
                            <strong id="fee-base-display" style="color: var(--text);">PHP 0.00</strong>
                        </div>
                        <div id="fee-addons-row" style="display: none; justify-content: space-between; font-size: 12px; color: var(--text2); margin-bottom: 2px;">
                            <span>Add-ons:</span>
                            <strong id="fee-addons-display" style="color: var(--text);">+ PHP 0.00</strong>
                        </div>
                        <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px dashed var(--border); padding-top: 5px; margin-top: 5px;">
                            <span style="font-size: 12px; font-weight: 700; color: var(--text);">Estimated Fee:</span>
                            <span id="fee-total-display" style="font-size: 14.5px; font-weight: 800; color: #16a34a;">PHP 0.00</span>
                        </div>
                    </div>
                </div>

                <!-- 3. Sanctuary Scheduling Group -->
                <div id="sanctuary-scheduling-group">
                    <div class="req-section-block" style="background: var(--bg3); border: 1px solid var(--border); border-radius: 8px; padding: 10px 12px;">
                        <div class="req-section-title" style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.06em; color: var(--text); font-weight: 700; margin-bottom: 8px; padding-bottom: 4px; border-bottom: 1px solid var(--border); display: flex; align-items: center; gap: 6px;">
                            <i class="fa-regular fa-calendar-check" style="color: var(--accent);"></i> Date &amp; Sanctuary Location
                        </div>
                        
                        <!-- Date Selection -->
                        <div class="form-group" style="margin: 0 0 8px 0;">
                            <label class="form-label" style="font-size: 12px; margin-bottom: 4px; font-weight: 600; color: var(--text); display: flex; align-items: center; justify-content: space-between;">
                                <span>Select Date <span style="color:#ef4444;">*</span></span>
                                <span id="slot-sync-indicator" style="font-size: 11px; font-weight: 500; color: var(--text2);">Checking slots...</span>
                            </label>
                            <input type="date" id="field-date-request"
                                   min="<?php echo date('Y-m-d'); ?>" max="2099-12-31"
                                   value="<?php echo date('Y-m-d', strtotime('+1 day')); ?>"
                                   required onchange="syncRequestSlots()"
                                   class="req-compact-control"
                                   style="padding: 0 10px; height: 34px; font-size: 12.5px; font-weight: 600; box-sizing: border-box; width: 100%; border-radius: 6px; border: 1px solid var(--border); background: var(--bg2); color: var(--text);">
                            <input type="hidden" name="start_time" id="field-datetime-request" value="<?php echo date('Y-m-d', strtotime('+1 day')) . 'T09:00'; ?>" required>
                            <input type="hidden" name="custom_duration" id="field-custom-duration-request" value="180">
                        </div>

                        <!-- Operational Time Slot Selector (Morning vs Afternoon) -->
                        <div class="form-group" style="margin: 0 0 8px 0;">
                            <label class="form-label" style="font-size: 12px; margin-bottom: 6px; font-weight: 600; color: var(--text); display: flex; align-items: center; justify-content: space-between;">
                                <span>Slot Schedule <span style="color:#ef4444;">*</span></span>
                            </label>
                            
                            <div class="slot-picker-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px;">
                                <!-- Morning Slot Tab -->
                                <div class="slot-card morning selected" id="slot-card-morning-request" onclick="handleSlotTabClick('morning')"
                                     style="position: relative; cursor: pointer; padding: 8px 10px; border-radius: 8px; border: 1.5px solid var(--text); background: var(--surface); transition: all 0.15s ease;">
                                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 3px;">
                                        <div style="font-size: 12px; font-weight: 700; color: var(--text); display: flex; align-items: center; gap: 5px;">
                                            <i class="fa-solid fa-sun" style="color: var(--accent); font-size: 12px;"></i> Morning
                                        </div>
                                        <span class="slot-badge available" id="slot-badge-morning" style="font-size: 9.5px; padding: 1px 5px; border-radius: 4px; font-weight: 600;">
                                            Available
                                        </span>
                                    </div>
                                    <div style="font-size: 11px; color: var(--text2); font-weight: 600; margin-bottom: 5px;">
                                        9:00 AM – 12:00 PM
                                    </div>
                                    <div id="slot-chip-morning">
                                        <div class="slot-open-chip" style="font-size: 9.5px; padding: 2px 7px; border-radius: 4px; display: inline-flex; align-items: center; gap: 4px; background: var(--text); color: var(--bg); font-weight: 700;">
                                            <i class="fa-solid fa-circle-check" style="font-size: 8px;"></i> Selected
                                        </div>
                                    </div>
                                    <div id="slot-overlay-morning" class="slot-gray-block" style="display: none; position: absolute; inset: 0; background: rgba(246, 246, 246, 0.95); border-radius: 8px; border: 1px solid rgba(239, 68, 68, 0.4); align-items: center; justify-content: center; flex-direction: column; gap: 2px; z-index: 5; cursor: not-allowed;">
                                        <span style="color: #ef4444; font-size: 11px; font-weight: 700;"><i class="fa-solid fa-lock"></i> Booked</span>
                                    </div>
                                </div>

                                <!-- Afternoon Slot Tab -->
                                <div class="slot-card afternoon" id="slot-card-afternoon-request" onclick="handleSlotTabClick('afternoon')"
                                     style="position: relative; cursor: pointer; padding: 8px 10px; border-radius: 8px; border: 1px solid var(--border); background: var(--surface); transition: all 0.15s ease;">
                                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 3px;">
                                        <div style="font-size: 12px; font-weight: 700; color: var(--text); display: flex; align-items: center; gap: 5px;">
                                            <i class="fa-solid fa-cloud-sun" style="color: var(--accent); font-size: 12px;"></i> Afternoon
                                        </div>
                                        <span class="slot-badge available" id="slot-badge-afternoon" style="font-size: 9.5px; padding: 1px 5px; border-radius: 4px; font-weight: 600;">
                                            Available
                                        </span>
                                    </div>
                                    <div style="font-size: 11px; color: var(--text2); font-weight: 600; margin-bottom: 5px;">
                                        2:00 PM – 5:00 PM
                                    </div>
                                    <div id="slot-chip-afternoon">
                                        <div class="slot-open-chip" style="font-size: 9.5px; padding: 2px 7px; border-radius: 4px; display: inline-flex; align-items: center; gap: 4px; opacity: 0.75; background: var(--bg3); border: 1px solid var(--border); color: var(--text);">
                                            Select
                                        </div>
                                    </div>
                                    <div id="slot-overlay-afternoon" class="slot-gray-block" style="display: none; position: absolute; inset: 0; background: rgba(246, 246, 246, 0.95); border-radius: 8px; border: 1px solid rgba(239, 68, 68, 0.4); align-items: center; justify-content: center; flex-direction: column; gap: 2px; z-index: 5; cursor: not-allowed;">
                                        <span style="color: #ef4444; font-size: 11px; font-weight: 700;"><i class="fa-solid fa-lock"></i> Booked</span>
                                    </div>
                                </div>
                            </div>

                            <div id="slots-full-alert-request" style="display: none; margin-top: 6px; padding: 8px 10px; background: rgba(239, 68, 68, 0.08); border: 1px solid rgba(239, 68, 68, 0.3); border-radius: 6px; color: #ef4444; font-size: 11.5px; font-weight: 600; align-items: center; gap: 6px;">
                                <i class="fa-solid fa-triangle-exclamation"></i>
                                <span>Both slots booked. Please select another date.</span>
                            </div>
                        </div>
                        
                        <div id="timeline-preview-request" class="timeline-container-outer" style="display:none !important; margin:0; padding:0; height:0; overflow:hidden;"></div>
                        <div id="venue-warning-request" style="display:none; margin-bottom:6px; padding:6px 10px; background:rgba(239,68,68,0.08); border:1px solid rgba(239,68,68,0.25); border-radius:6px; color:#b91c1c; font-size:11.5px;">
                            <i class="fa-solid fa-triangle-exclamation"></i>
                            <span id="venue-warning-text-request"></span>
                        </div>

                        <!-- Preferred Venue Location -->
                        <div class="form-group" style="margin: 0;">
                            <label class="form-label" style="font-size: 12px; font-weight: 600; color: var(--text); margin-bottom: 4px; display: block;">
                                Venue Location <span style="color:#ef4444;">*</span>
                            </label>
                            <select name="chapel_id" id="field-venue-request" required onchange="handleVenueChange(this); onFormChange('request'); syncRequestSlots();" 
                                    class="req-compact-control"
                                    style="padding: 0 28px 0 10px !important; height: 34px; min-height: 34px; font-size: 12.5px; box-sizing: border-box; width: 100%; border-radius: 6px; border: 1px solid var(--border); background: var(--bg2); color: var(--text); cursor: pointer;">
                                <option value="0" <?php echo ($role === 'Cathedral Admin' || ($role !== 'Admin' && $chapel_id === null)) ? 'selected' : ''; ?>>Main Cathedral (Sanctuary)</option>
                                <?php foreach ($chapels as $ch): ?>
                                <option value="<?php echo $ch['chapel_id']; ?>" <?php echo ($chapel_id == $ch['chapel_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($ch['chapel_name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <input type="hidden" name="event_scope" id="field-scope-request" value="<?php echo ($chapel_id ? 'chapel' : 'cathedral'); ?>">
                        </div>
                    </div>

                    <!-- Priest Assignment (Admin only) -->
                    <?php if ($can_approve_events): ?>
                    <div style="background: var(--bg3); border: 1px solid var(--border); border-radius: 8px; padding: 10px 12px; margin-top: 10px;">
                        <div style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.06em; color: var(--text); font-weight: 700; margin-bottom: 6px; padding-bottom: 4px; border-bottom: 1px solid var(--border);">Priest Assignment</div>
                        <div class="form-group" style="margin: 0;">
                            <label class="form-label" style="font-size: 12px; margin-bottom: 4px; color: var(--text2); display: block;">Assign Clergy <small style="color:var(--text2); font-weight:400;">(Optional)</small></label>
                            <input type="hidden" name="pastor_id" id="selected-pastor-input-request">
                            <div id="pastor-picker-grid-request" class="pastor-picker-grid mt-6"></div>
                            <div id="pastor-picker-hint-request" style="background:var(--surface); border:1px dashed var(--border); border-radius:6px; color:var(--text2); padding:6px 10px; font-size:11.5px;">
                                <i class="fa-solid fa-info-circle"></i> Select location, type and date to view available clergy.
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- 4. Contact Information -->
                <div class="req-section-block" style="background: var(--bg3); border: 1px solid var(--border); border-radius: 8px; padding: 10px 12px;">
                    <div class="req-section-title" style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.06em; color: var(--text); font-weight: 700; margin-bottom: 8px; padding-bottom: 4px; border-bottom: 1px solid var(--border); display: flex; align-items: center; gap: 6px;">
                        <i class="fa-solid fa-address-card" style="color: var(--accent);"></i> Contact Details
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px;">
                        <div class="form-group" style="margin: 0;">
                            <label class="form-label" style="font-size: 12px; font-weight: 600; color: var(--text); margin-bottom: 4px; display: block;">
                                Email <span style="color:var(--text2); font-weight:400; font-size:11px;">(Opt)</span>
                            </label>
                            <input type="email" id="field-email-request" name="requester_email" placeholder="email@domain.com" 
                                   class="req-compact-control"
                                   style="padding: 0 10px; height: 34px; font-size: 12px; box-sizing: border-box; width: 100%; border-radius: 6px; border: 1px solid var(--border); background: var(--bg2); color: var(--text);">
                        </div>
                        <div class="form-group" style="margin: 0;">
                            <label class="form-label" style="font-size: 12px; font-weight: 600; color: var(--text); margin-bottom: 4px; display: block;">
                                Phone <span style="color:var(--text2); font-weight:400; font-size:11px;">(Opt)</span>
                            </label>
                            <input type="tel" id="field-phone-request" name="requester_phone" placeholder="0912-345-6789" maxlength="15" pattern="[0-9\+\s\-]{10,20}" 
                                   class="req-compact-control"
                                   style="padding: 0 10px; height: 34px; font-size: 12px; box-sizing: border-box; width: 100%; border-radius: 6px; border: 1px solid var(--border); background: var(--bg2); color: var(--text);">
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <!-- Modal Footer: Submit and Close Buttons -->
        <div style="padding: 12px 18px; background: var(--bg3); border-top: 1px solid var(--border); display: flex; flex-direction: column; gap: 8px; flex-shrink: 0;">
            <?php if ($isGuest): ?>
            <div style="font-size: 11.5px; color: var(--text2); display: flex; align-items: center; gap: 6px;">
                <i class="fa-solid fa-circle-info" style="color: var(--accent);"></i>
                <span>Guest Preview: When you click Submit Request, you will be prompted to register or sign in as a member to save your request.</span>
            </div>
            <?php endif; ?>
            <div style="display: flex; justify-content: space-between; align-items: center; gap: 10px;">
                <button type="button" class="btn btn-gray" onclick="closeRequestEventModal()" style="height: 36px; padding: 0 16px; border-radius: 6px; font-size: 12.5px;">
                    Cancel
                </button>
                <button type="button" id="btn-open-confirm-modal" class="btn-submit-gold" onclick="showConfirmationModal()" style="width: auto; padding: 0 20px;">
                    <i class="fa-solid fa-paper-plane" style="margin-right: 6px;"></i>
                    <span>Submit Request</span>
                </button>
            </div>
        </div>
    </div>
</div>
<!-- ─── CONFIRMATION MODAL ─── -->
<div class="modal-overlay" id="confirmation-modal" style="position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center;">
    <div class="modal" style="max-width: 600px; width: 90%; padding: 0; overflow: hidden; border-radius: 12px; background: var(--surface); color: var(--text); border: 1px solid var(--border); box-shadow: 0 10px 30px rgba(0,0,0,0.1);">
        <div style="background: var(--surface); padding: 18px 24px; border-bottom: 1px solid var(--border);">
            <div style="display: flex; align-items: center; gap: 14px;">
                <div style="width: 40px; height: 40px; background: var(--bg2); color: var(--text); border: 1px solid var(--border); border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 16px; flex-shrink: 0;">
                    <i class="fa-solid fa-clipboard-check"></i>
                </div>
                <div>
                    <h3 style="font-family: var(--font-family, 'Inter', sans-serif); font-size: 16px; font-weight: 700; color: var(--text); margin: 0;">Confirm Your Request</h3>
                    <p style="font-size: 12px; color: var(--text2); margin: 3px 0 0;">Please review the details below before submitting</p>
                </div>
            </div>
        </div>
        <div style="padding: 20px 24px; max-height: 58vh; overflow-y: auto; background: var(--surface);">
            <div id="confirmation-content" style="display: flex; flex-direction: column; gap: 14px;"></div>
        </div>
        <div id="confirmation-footer-standard" style="padding: 14px 24px; display: <?php echo $isGuest ? 'none' : 'flex'; ?>; gap: 10px; background: var(--surface); border-top: 1px solid var(--border);">
            <button type="button" class="btn btn-gray" onclick="closeConfirmationModal()" style="flex: 1; border-radius: 8px;">
                <i class="fa-solid fa-arrow-left"></i> Go Back &amp; Edit
            </button>
            <button type="button" id="btn-confirm-submit-event" class="btn btn-primary" onclick="confirmSubmission()" style="flex: 1; border-radius: 8px;">
                <i class="fa-solid fa-check"></i> Confirm &amp; Submit
            </button>
        </div>
        <div id="confirmation-footer-guest" style="padding: 14px 24px; display: <?php echo $isGuest ? 'flex' : 'none'; ?>; flex-direction: column; gap: 10px; background: var(--surface); border-top: 1px solid var(--border);">
            <div style="display: flex; gap: 8px; width: 100%;">
                <button type="button" class="btn btn-gray" onclick="closeConfirmationModal()" style="border-radius: 8px; padding: 0 16px;">
                    <i class="fa-solid fa-arrow-left"></i> Edit
                </button>
                <a href="register.php" class="btn" onclick="saveFormDraft()" style="flex: 1; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; gap: 7px; background: #111111; color: #ffffff; padding: 9px 12px; border-radius: 8px; font-size: 12.5px; font-weight: 600;">
                    <i class="fa-solid fa-user-plus text-gold"></i> Register as Member
                </a>
                <a href="logout.php?redirect=index.php?open_login=1" class="btn" onclick="saveFormDraft()" style="flex: 1; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; gap: 7px; background: #ffffff; color: var(--text); border: 1px solid var(--border); padding: 9px 12px; border-radius: 8px; font-size: 12.5px; font-weight: 600;">
                    <i class="fa-solid fa-arrow-right-to-bracket text-gold"></i> Sign In
                </a>
            </div>
            <button type="button" onclick="confirmSubmission()" style="background: transparent; border: none; font-size: 11.5px; color: var(--accent); cursor: pointer; padding: 2px; text-align: center; text-decoration: underline;">
                Or learn why membership is required
            </button>
        </div>
    </div>
</div>

<!-- ─── DELETE MODAL ─── -->
<div class="modal-overlay" id="delete-modal" style="position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center;">
    <div class="modal" style="max-width: 400px; width: 90%; padding: 24px; border-radius: 12px; background: var(--surface); color: var(--text); border: 1px solid var(--border); box-shadow: 0 10px 30px rgba(0,0,0,0.1);">
        <div style="text-align: center; margin-bottom: 20px;">
            <div style="width: 44px; height: 44px; background: rgba(239,68,68,0.08); color: #ef4444; border: 1px solid rgba(239,68,68,0.2); border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 20px; margin: 0 auto 12px;">
                <i class="fa-solid fa-triangle-exclamation"></i>
            </div>
            <h3 style="font-family: var(--font-family, 'Inter', sans-serif); font-size: 16px; font-weight: 700; color: #111111; margin: 0 0 6px;">Delete Request?</h3>
            <p style="font-size: 13px; color: var(--text2); margin: 0;">Are you sure you want to delete "<span id="delete-request-name"></span>"? This action cannot be undone.</p>
        </div>
        <div style="display: flex; gap: 10px;">
            <button class="btn btn-gray" onclick="closeDeleteModal()" style="flex: 1; border-radius: 8px;">Cancel</button>
            <form id="delete-form" method="POST" action="dashboard.php?section=request_event" style="flex: 1;">
                <input type="hidden" name="form_section" value="event_request">
                <input type="hidden" name="action" value="delete_request">
                <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
                <input type="hidden" name="event_id" id="delete-request-id">
                <button type="submit" class="btn btn-danger" style="width: 100%; border-radius: 8px;"><i class="fa-solid fa-trash"></i> Delete</button>
            </form>
        </div>
    </div>
</div>

<!-- ─── CANCEL MODAL ─── -->
<div class="modal-overlay" id="cancel-modal" style="position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center;">
    <div class="modal" style="max-width: 400px; width: 90%; padding: 24px; border-radius: 12px; background: var(--surface); color: var(--text); border: 1px solid var(--border); box-shadow: 0 10px 30px rgba(0,0,0,0.1);">
        <div style="text-align: center; margin-bottom: 20px;">
            <div style="width: 44px; height: 44px; background: rgba(220,38,38,0.1); color: #dc2626; border: 1px solid rgba(220,38,38,0.25); border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 20px; margin: 0 auto 12px;">
                <i class="fa-solid fa-ban"></i>
            </div>
            <h3 style="font-family: var(--font-family, 'Inter', sans-serif); font-size: 16px; font-weight: 700; color: #111111; margin: 0 0 6px;">Cancel Request?</h3>
            <p style="font-size: 13px; color: var(--text2); margin: 0;">Cancel "<span id="cancel-request-name"></span>"?</p>
        </div>
        <div style="display: flex; gap: 10px;">
            <button class="btn btn-gray" onclick="closeCancelModal()" style="flex: 1; border-radius: 8px;">No, Keep It</button>
            <form id="cancel-form" method="POST" action="dashboard.php?section=request_event" style="flex: 1;">
                <input type="hidden" name="form_section" value="event_request">
                <input type="hidden" name="action" value="cancel_request">
                <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
                <input type="hidden" name="event_id" id="cancel-request-id">
                <button type="submit" class="btn btn-danger btn-red" style="width: 100%; border-radius: 8px; background: #dc2626 !important; border-color: #dc2626 !important; color: #fff !important;"><i class="fa-solid fa-ban"></i> Yes, Cancel</button>
            </form>
        </div>
    </div>
</div>

<!-- ─── VIEW DETAILS MODAL ─── -->
<div class="modal-overlay" id="view-details-modal" style="position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center;">
    <div class="modal" style="max-width: 540px; width: 92%; max-height: 88vh; display: flex; flex-direction: column; padding: 0; border-radius: 12px; background: var(--surface); color: var(--text); border: 1px solid var(--border); box-shadow: 0 10px 30px rgba(0,0,0,0.1); overflow: hidden;">
        <div style="padding: 16px 20px; background: var(--surface); border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center;">
            <div style="display: flex; align-items: center; gap: 10px;">
                <i class="fa-solid fa-calendar-check" style="color: var(--accent);"></i>
                <h3 style="font-family: var(--font-family, 'Inter', sans-serif); font-size: 15px; font-weight: 700; color: var(--text); margin: 0;">Request Details</h3>
            </div>
            <button class="btn-icon" onclick="document.getElementById('view-details-modal').classList.remove('open');" title="Close" style="border: none; background: transparent; cursor: pointer; color: var(--text2);">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div id="view-details-body" style="padding: 20px; display: flex; flex-direction: column; gap: 14px; flex: 1; overflow-y: auto; background: var(--surface);"></div>
        <div style="padding: 14px 20px; background: var(--surface); border-top: 1px solid var(--border); display: flex; justify-content: flex-end;">
            <button class="btn btn-gray" onclick="document.getElementById('view-details-modal').classList.remove('open');" style="padding: 0 18px; height: 36px; border-radius: 8px;">Close</button>
        </div>
    </div>
</div>

<style>
/* ─── REQUEST EVENT MODAL & MINIMAL TABLE STYLES ─── */
.req-command-header {
    background: transparent;
}

.btn-request-popup-trigger:hover {
    filter: brightness(1.08);
    transform: translateY(-1px);
    box-shadow: 0 4px 14px rgba(201,168,76,0.38) !important;
}

/* Minimal Status Tabs */
.req-minimal-tabs {
    display: inline-flex;
    background: var(--surface);
    padding: 3px;
    border-radius: 8px;
    border: 1px solid var(--border);
    gap: 2px;
}

.req-tab-item {
    text-decoration: none;
    font-size: 12px;
    padding: 5px 12px;
    border-radius: 6px;
    font-weight: 600;
    color: var(--text2);
    background: transparent;
    transition: all 0.15s ease;
    display: inline-flex;
    align-items: center;
}

.req-tab-item:hover {
    color: var(--text);
    background: var(--bg2);
}

.req-tab-item.active {
    background: var(--accent-soft, rgba(201,168,76,0.15));
    color: var(--accent);
    font-weight: 700;
}

/* Submit Gold Button */
.btn-submit-gold {
    height: 36px;
    font-size: 13px;
    font-weight: 700;
    border-radius: 6px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    border: none;
    cursor: pointer;
    background: var(--accent, #c5a059);
    color: #111111;
    box-shadow: 0 2px 8px rgba(201,168,76,0.25);
    transition: all 0.18s ease;
}

.btn-submit-gold:hover {
    filter: brightness(1.08);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(201,168,76,0.35);
}

.req-compact-control:focus {
    border-color: var(--accent) !important;
    box-shadow: 0 0 0 2px rgba(201,168,76,0.2) !important;
    outline: none !important;
}

.ent-card { background: var(--panel-bg, var(--bg2)); border: 1px solid var(--border); border-radius: 8px; box-shadow: var(--shadow); display: flex; flex-direction: column; overflow: hidden; }
.ent-card-header { padding: 8px 12px; background: var(--panel-header-bg, var(--bg3)); border-bottom: 1px solid var(--border); flex-shrink: 0; }
.ent-card-title { font-size: 0.95rem; font-weight: 700; color: var(--text); }
.ent-card-subtitle { font-size: 0.72rem; color: var(--text2); margin-top: 2px; }
.ent-card-body { padding: 12px 14px; }

.req-row {
    transition: background-color 0.15s ease;
}
.req-row:hover td {
    background: var(--hover-bg, #F6F6F6) !important;
}
[data-theme="dark"] .req-row:hover td {
    background: #1e293b !important;
}
.btn-action-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    border-radius: 6px;
    background: var(--bg3, rgba(255, 255, 255, 0.05));
    border: 1px solid var(--border);
    color: var(--text2);
    cursor: pointer;
    font-size: 11.5px;
    transition: all 0.15s ease;
}
.btn-action-icon:hover {
    background: var(--accent);
    border-color: var(--accent);
    color: #111111;
}
[data-theme="dark"] .btn-action-icon {
    background: #1e293b;
    border-color: var(--border);
    color: var(--text);
}
[data-theme="dark"] .btn-action-icon:hover {
    background: var(--accent);
    color: #111111;
    border-color: var(--accent);
}
.btn-action-danger:hover {
    background: #D64545 !important;
    border-color: #D64545 !important;
    color: #FFFFFF !important;
}
</style>

<script>
const isGuest = <?php echo ($isGuest ?? false) ? 'true' : 'false'; ?>;

// ── SERVICES VIEW SWITCHER (MENU VS REQUESTS) ───────────────────────
window.switchServiceViewTab = function(tab) {
    const catalogPane = document.getElementById('service-view-catalog-pane');
    const requestsPane = document.getElementById('service-view-requests-pane');
    const btnCatalog = document.getElementById('tab-btn-catalog');
    const btnRequests = document.getElementById('tab-btn-requests');

    if (tab === 'catalog') {
        if (catalogPane) catalogPane.style.display = 'flex';
        if (requestsPane) requestsPane.style.display = 'none';
        if (btnCatalog) {
            btnCatalog.classList.add('active');
            btnCatalog.style.background = 'var(--accent-soft, rgba(201,168,76,0.15))';
            btnCatalog.style.color = 'var(--accent)';
        }
        if (btnRequests) {
            btnRequests.classList.remove('active');
            btnRequests.style.background = 'transparent';
            btnRequests.style.color = 'var(--text2)';
        }
    } else {
        if (catalogPane) catalogPane.style.display = 'none';
        if (requestsPane) requestsPane.style.display = 'flex';
        if (btnRequests) {
            btnRequests.classList.add('active');
            btnRequests.style.background = 'var(--accent-soft, rgba(201,168,76,0.15))';
            btnRequests.style.color = 'var(--accent)';
        }
        if (btnCatalog) {
            btnCatalog.classList.remove('active');
            btnCatalog.style.background = 'transparent';
            btnCatalog.style.color = 'var(--text2)';
        }
    }
};

// ── REQUEST EVENT POP-UP MODAL CONTROLS ─────────────────────────────
window.openRequestEventModal = function(serviceType) {
    const modal = document.getElementById('request-event-modal');
    if (modal) {
        modal.classList.add('open');
        document.body.style.overflow = 'hidden';
        if (serviceType) {
            const sel = document.getElementById('field-event-type-request');
            if (sel) {
                const searchType = String(serviceType).toLowerCase().trim();
                for (let i = 0; i < sel.options.length; i++) {
                    const opt = sel.options[i];
                    const optVal = opt.value.toLowerCase().trim();
                    const optTxt = opt.text.toLowerCase().trim();
                    const optCode = (opt.getAttribute('data-service-code') || '').toLowerCase().trim();
                    if (optVal === searchType || optTxt === searchType || optCode === searchType || optTxt.includes(searchType) || searchType.includes(optVal)) {
                        sel.selectedIndex = i;
                        handleServiceCatalogChange(sel);
                        break;
                    }
                }
            }
        }
    }
};

window.closeRequestEventModal = function() {
    const modal = document.getElementById('request-event-modal');
    if (modal) {
        modal.classList.remove('open');
        document.body.style.overflow = '';
    }
};

// Close popup on backdrop click (works on full load and AJAX SPA load)
function initRequestEventModalBackdrop() {
    const popupModal = document.getElementById('request-event-modal');
    if (popupModal && !popupModal.dataset.backdropBound) {
        popupModal.dataset.backdropBound = "true";
        popupModal.addEventListener('click', function(e) {
            if (e.target === popupModal) {
                window.closeRequestEventModal();
            }
        });
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initRequestEventModalBackdrop);
} else {
    initRequestEventModalBackdrop();
}

// Backward-compatible aliases
window.openMyRequestsModal = function() { /* Not needed since table is on page */ };
window.switchRequestView = function(mode) { if (mode === 'form') window.openRequestEventModal(); };
window.toggleLayoutMode = function() { window.openRequestEventModal(); };
// ── SERVICES CATALOG INTEGRATION ─────────────────────────────────────
const CATALOG_DATA = <?php echo json_encode($services_catalog ?? [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
let selectedServiceObj = null;
let selectedVariantObj = null;

function handleVenueChange(selectEl) {
    const scopeInput = document.getElementById('field-scope-request');
    if (scopeInput) {
        scopeInput.value = (selectEl.value === '0' || !selectEl.value) ? 'cathedral' : 'chapel';
    }
}

function handleServiceCatalogChange(selectEl) {
    const selectedOpt = selectEl.options[selectEl.selectedIndex];
    const sId = selectedOpt ? parseInt(selectedOpt.getAttribute('data-service-id') || '0', 10) : 0;
    
    const panel = document.getElementById('service-catalog-panel');
    const sIdInput = document.getElementById('field-service-id');
    const vIdInput = document.getElementById('field-variant-id');
    const titleInput = document.getElementById('field-title-request');
    const certGroup = document.getElementById('certificates-placeholder-group');
    const sanctuaryGroup = document.getElementById('sanctuary-scheduling-group');
    const officeGroup = document.getElementById('office-processing-group');
    const venueSelect = document.getElementById('field-venue-request');
    const dateInput = document.getElementById('field-date-request');
    const pickupDateInput = document.getElementById('field-pickup-date');
    const certPersonNameInput = document.getElementById('field-cert-person-name');
    const titleLabel = document.getElementById('field-title-label');
    const submitBtn = document.getElementById('btn-open-confirm-modal');
    const durationHidden = document.getElementById('field-custom-duration-request');
    const scopeSelect = document.getElementById('field-scope-request');
    
    if (!sId || !CATALOG_DATA || CATALOG_DATA.length === 0) {
        if (panel) panel.style.display = 'none';
        if (sIdInput) sIdInput.value = '';
        if (vIdInput) vIdInput.value = '';
        if (sanctuaryGroup) sanctuaryGroup.style.display = 'block';
        if (officeGroup) officeGroup.style.display = 'none';
        if (venueSelect) venueSelect.setAttribute('required', 'required');
        if (dateInput) dateInput.setAttribute('required', 'required');
        if (pickupDateInput) pickupDateInput.removeAttribute('required');
        if (certPersonNameInput) certPersonNameInput.removeAttribute('required');
        if (titleLabel) titleLabel.textContent = 'Event / Service Title';
        if (submitBtn) submitBtn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> Submit Request';
        selectedServiceObj = null;
        selectedVariantObj = null;
        return;
    }
    
    let foundService = null;
    for (const cat of CATALOG_DATA) {
        if (cat.services && Array.isArray(cat.services)) {
            for (const srv of cat.services) {
                if (parseInt(srv.id, 10) === sId) {
                    foundService = srv;
                    break;
                }
            }
        }
        if (foundService) break;
    }
    
    selectedServiceObj = foundService;
    if (!foundService) {
        if (panel) panel.style.display = 'none';
        return;
    }
    
    if (sIdInput) sIdInput.value = foundService.id;
    if (panel) panel.style.display = 'block';
    
    const isCert = (foundService.code === 'certificates' || foundService.name.toLowerCase().includes('certificate'));
    
    // Switch between Altar/Sanctuary Event and Office Document Request
    if (isCert) {
        if (sanctuaryGroup) sanctuaryGroup.style.display = 'none';
        if (officeGroup) officeGroup.style.display = 'block';
        if (venueSelect) {
            venueSelect.removeAttribute('required');
            venueSelect.value = '0';
        }
        if (dateInput) dateInput.removeAttribute('required');
        if (scopeSelect) scopeSelect.value = 'cathedral';
        if (durationHidden) durationHidden.value = '0';
        if (pickupDateInput) {
            pickupDateInput.setAttribute('required', 'required');
            syncPickupDate(pickupDateInput.value);
        }
        if (certPersonNameInput) certPersonNameInput.setAttribute('required', 'required');
        if (titleLabel) titleLabel.textContent = 'Document Subject / Person Name';
        if (submitBtn) {
            submitBtn.innerHTML = '<i class="fa-solid fa-file-signature"></i> Submit Document Application';
        }
    } else {
        if (sanctuaryGroup) sanctuaryGroup.style.display = 'block';
        if (officeGroup) officeGroup.style.display = 'none';
        if (venueSelect) venueSelect.setAttribute('required', 'required');
        if (dateInput) {
            dateInput.setAttribute('required', 'required');
            const dtHidden = document.getElementById('field-datetime-request');
            if (dtHidden && dateInput.value) {
                applySlotSelection(activeSlotSelection, dateInput.value);
            }
        }
        if (pickupDateInput) pickupDateInput.removeAttribute('required');
        if (certPersonNameInput) certPersonNameInput.removeAttribute('required');
        if (durationHidden) durationHidden.value = '180';
        if (titleLabel) titleLabel.textContent = 'Event / Service Title';
        if (submitBtn) {
            submitBtn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> Submit Request';
        }
        if (titleInput && (!titleInput.value || titleInput.value.includes(':') || titleInput.value.includes('Certificate'))) {
            titleInput.value = foundService.name + ' Request';
        }
        syncRequestSlots();
    }
    
    const vSelect = document.getElementById('field-variant-selector');
    if (vSelect) {
        vSelect.innerHTML = '<option value="">Select Option</option>';
        if (foundService.variants && foundService.variants.length > 0) {
            foundService.variants.forEach(v => {
                const opt = document.createElement('option');
                opt.value = v.id;
                opt.setAttribute('data-price', v.price);
                opt.setAttribute('data-name', v.name);
                opt.setAttribute('data-sponsors', v.included_sponsors || 0);
                opt.setAttribute('data-desc', v.description || '');
                opt.setAttribute('data-sched', v.schedule_type || '');
                
                let label = `${v.name} — PHP ${parseFloat(v.price).toLocaleString('en-US', {minimumFractionDigits: 2})}`;
                if (parseInt(v.included_sponsors || 0, 10) > 0) {
                    label += ` (incl. ${v.included_sponsors} sponsors)`;
                }
                opt.textContent = label;
                vSelect.appendChild(opt);
            });
            vSelect.selectedIndex = 1;
        }
    }
    
    if (certGroup) {
        certGroup.style.display = isCert ? 'block' : 'none';
    }
    
    const addonsGroup = document.getElementById('addons-selection-group');
    const addonsContainer = document.getElementById('addons-checkboxes-container');
    if (addonsContainer) {
        addonsContainer.innerHTML = '';
        if (!isCert && foundService.charges && foundService.charges.length > 0) {
            if (addonsGroup) addonsGroup.style.display = 'block';
            foundService.charges.forEach(ch => {
                const row = document.createElement('label');
                row.style.cssText = 'display:flex; align-items:center; justify-content:space-between; font-size:11.5px; color:var(--text); cursor:pointer; background:var(--bg2); padding:5px 8px; border-radius:5px; border:1px solid var(--border);';
                row.innerHTML = `
                    <span style="display:inline-flex; align-items:center; gap:6px;">
                        <input type="checkbox" name="addons[]" value="${ch.id}" data-price="${ch.price}" data-name="${escapeHtml(ch.name)}" onchange="recalculateServiceFees()" style="accent-color:#111111;">
                        <strong>${escapeHtml(ch.name)}</strong>
                        ${ch.description ? `<small style="color:var(--text2); font-size:10px;">(${escapeHtml(ch.description)})</small>` : ''}
                    </span>
                    <strong style="color:#111111; font-size:11.5px;">+ PHP ${parseFloat(ch.price).toLocaleString('en-US', {minimumFractionDigits:2})}</strong>
                `;
                addonsContainer.appendChild(row);
            });
        } else {
            if (addonsGroup) addonsGroup.style.display = 'none';
        }
    }
    
    const reqList = document.getElementById('requirements-list');
    const reqGroup = document.getElementById('requirements-display-group');
    if (reqList) {
        reqList.innerHTML = '';
        if (foundService.requirements && foundService.requirements.length > 0) {
            if (reqGroup) reqGroup.style.display = 'block';
            foundService.requirements.forEach(r => {
                const li = document.createElement('li');
                li.style.marginBottom = '2px';
                li.innerHTML = `<strong>${escapeHtml(r.requirement)}</strong>${r.description ? ` <span style="color:var(--text2); font-size:10px;">— ${escapeHtml(r.description)}</span>` : ''}`;
                reqList.appendChild(li);
            });
        } else {
            if (reqGroup) reqGroup.style.display = 'none';
        }
    }
    
    if (vSelect && vSelect.selectedIndex > 0) {
        handleVariantChange(vSelect);
    } else {
        recalculateServiceFees();
    }
    
    if (isCert) {
        syncCertificateTitle();
    }
}

function handleVariantChange(selectEl) {
    const vId = parseInt(selectEl.value || '0', 10);
    const vIdInput = document.getElementById('field-variant-id');
    const locTypeInput = document.getElementById('field-location-type');
    const hintEl = document.getElementById('variant-detail-hint');
    
    if (vIdInput) vIdInput.value = vId > 0 ? vId : '';
    
    if (!vId || !selectedServiceObj || !selectedServiceObj.variants) {
        selectedVariantObj = null;
        if (hintEl) hintEl.textContent = '';
        recalculateServiceFees();
        return;
    }
    
    const vObj = selectedServiceObj.variants.find(v => parseInt(v.id, 10) === vId);
    selectedVariantObj = vObj || null;
    
    const isCert = selectedServiceObj && (selectedServiceObj.code === 'certificates' || selectedServiceObj.name.toLowerCase().includes('certificate'));
    
    if (vObj) {
        if (locTypeInput) {
            if (vObj.name.toLowerCase().includes('outside')) {
                locTypeInput.value = 'Outside';
            } else {
                locTypeInput.value = 'Church';
            }
        }
        
        const unitPriceEl = document.getElementById('cert-unit-price-display');
        if (unitPriceEl && isCert) {
            unitPriceEl.textContent = `PHP ${parseFloat(vObj.price || 0).toLocaleString('en-US', {minimumFractionDigits: 2})}`;
        }
        
        let hints = [];
        if (vObj.description) hints.push(vObj.description);
        if (parseInt(vObj.included_sponsors || 0, 10) > 0) {
            hints.push(`Included Sponsors: ${vObj.included_sponsors}`);
        }
        if (vObj.schedule_type && vObj.schedule_type !== 'Standard') {
            hints.push(`Schedule: ${vObj.schedule_type}`);
        }
        if (hintEl) hintEl.innerHTML = hints.length > 0 ? `<i class="fa-solid fa-circle-info" style="color:#737373;"></i> ${escapeHtml(hints.join(' | '))}` : '';
    }
    
    recalculateServiceFees();
    
    if (isCert) {
        syncCertificateTitle();
    }
}

function syncPickupDate(val) {
    const isCert = selectedServiceObj && (selectedServiceObj.code === 'certificates' || selectedServiceObj.name.toLowerCase().includes('certificate'));
    if (!isCert) return;
    const dtHidden = document.getElementById('field-datetime-request');
    if (dtHidden && val) {
        dtHidden.value = `${val}T08:00`;
    }
}

function syncCertificateTitle() {
    const isCert = selectedServiceObj && (selectedServiceObj.code === 'certificates' || selectedServiceObj.name.toLowerCase().includes('certificate'));
    if (!isCert) return;
    
    const personName = (document.getElementById('field-cert-person-name')?.value || '').trim();
    const year = (document.getElementById('field-cert-sacrament-year')?.value || '').trim();
    const purpose = document.getElementById('field-cert-purpose')?.value || '';
    const variantName = selectedVariantObj?.name || 'Certificate';
    
    const titleInput = document.getElementById('field-title-request');
    if (titleInput) {
        let generated = variantName;
        if (personName) {
            generated += `: ${personName}`;
        }
        if (year) {
            generated += ` (${year})`;
        }
        if (purpose) {
            generated += ` - ${purpose}`;
        }
        titleInput.value = generated;
    }
}

function handleCertificateCopiesChange(val) {
    recalculateServiceFees();
}

function recalculateServiceFees() {
    let baseFee = 0.00;
    let addonsFee = 0.00;
    
    const isCert = selectedServiceObj && (selectedServiceObj.code === 'certificates' || selectedServiceObj.name.toLowerCase().includes('certificate'));
    const copiesInput = document.getElementById('field-certificate-copies');
    const copies = (isCert && copiesInput) ? Math.max(1, parseInt(copiesInput.value || '1', 10)) : 1;
    
    if (selectedVariantObj) {
        const unitPrice = parseFloat(selectedVariantObj.price || 0);
        baseFee = unitPrice * copies;
    }
    
    const checkedAddons = document.querySelectorAll('#addons-checkboxes-container input[type="checkbox"]:checked');
    checkedAddons.forEach(cb => {
        addonsFee += parseFloat(cb.getAttribute('data-price') || 0);
    });
    
    const totalFee = baseFee + addonsFee;
    
    const baseDisplay = document.getElementById('fee-base-display');
    const addonsRow = document.getElementById('fee-addons-row');
    const addonsDisplay = document.getElementById('fee-addons-display');
    const totalDisplay = document.getElementById('fee-total-display');
    
    if (baseDisplay) {
        if (isCert && copies > 1 && selectedVariantObj) {
            baseDisplay.textContent = `PHP ${baseFee.toLocaleString('en-US', {minimumFractionDigits: 2})} (${copies} × PHP ${parseFloat(selectedVariantObj.price).toFixed(2)})`;
        } else {
            baseDisplay.textContent = `PHP ${baseFee.toLocaleString('en-US', {minimumFractionDigits: 2})}`;
        }
    }
    
    if (addonsRow && addonsDisplay) {
        if (addonsFee > 0) {
            addonsRow.style.display = 'flex';
            addonsDisplay.textContent = `+ PHP ${addonsFee.toLocaleString('en-US', {minimumFractionDigits: 2})}`;
        } else {
            addonsRow.style.display = 'none';
        }
    }
    
    if (totalDisplay) {
        totalDisplay.textContent = `PHP ${totalFee.toLocaleString('en-US', {minimumFractionDigits: 2})}`;
    }
}

// ── PORTAL DROPDOWN ───────────────────────────────────────────────────
let _activePortal = null, _activeBtn = null;

function togglePortalDropdown(btn) {
    _closePortal();
    if (_activeBtn === btn) { _activeBtn = null; return; }
    _activeBtn = btn;

    const menuEl = btn.nextElementSibling;
    if (!menuEl || !menuEl.classList.contains('dropdown-menu')) return;

    const clone = menuEl.cloneNode(true);
    clone.style.cssText = `
        position: fixed; z-index: 99999;
        display: flex; flex-direction: column;
        min-width: 170px;
        background: var(--bg2);
        border: 1px solid var(--border, rgba(255,255,255,0.1));
        border-radius: 8px;
        box-shadow: 0 12px 32px rgba(0,0,0,0.5), 0 2px 8px rgba(0,0,0,0.3);
        padding: 4px;
        animation: dropFadeIn 0.12s ease;
    `;
    const rect = btn.getBoundingClientRect();
    const vw   = window.innerWidth, menuW = 170;
    let left   = rect.right - menuW;
    if (left < 4) left = rect.left;
    if (left + menuW > vw - 4) left = vw - menuW - 4;
    clone.style.top  = (rect.bottom + 4) + 'px';
    clone.style.left = left + 'px';
    document.body.appendChild(clone);
    _activePortal = clone;

    clone.querySelectorAll('.dropdown-item').forEach((cloneItem, i) => {
        const realItem = menuEl.querySelectorAll('.dropdown-item')[i];
        cloneItem.addEventListener('click', e => {
            e.stopPropagation();
            _closePortal();
            if (realItem) realItem.click();
        });
    });
}

function _closePortal() {
    if (_activePortal) { _activePortal.remove(); _activePortal = null; }
    _activeBtn = null;
}

document.addEventListener('click', e => {
    if (_activePortal && !_activePortal.contains(e.target) && e.target !== _activeBtn) _closePortal();
    if (e.target.classList && e.target.classList.contains('modal-overlay')) {
        e.target.classList.remove('open');
    }
});
document.querySelectorAll('.ent-card-body').forEach(el => el.addEventListener('scroll', _closePortal));

const _ddStyle = document.createElement('style');
_ddStyle.textContent = `@keyframes dropFadeIn { from { opacity:0; transform:translateY(-4px); } to { opacity:1; transform:translateY(0); } }`;
document.head.appendChild(_ddStyle);

// ── TIME SLOT SELECTION ENGINE (Cut in half: Morning & Noon/Afternoon) ──
let activeSlotSelection = 'morning';

async function syncRequestSlots() {
    const dateInput = document.getElementById('field-date-request');
    const venueSelect = document.getElementById('field-venue-request');
    const dtHidden = document.getElementById('field-datetime-request');
    const syncIndicator = document.getElementById('slot-sync-indicator');
    const fullAlert = document.getElementById('slots-full-alert-request');
    const submitBtn = document.getElementById('btn-submit-request');
    
    if (!dateInput || !dtHidden) return;
    const dateVal = dateInput.value;
    if (!dateVal) return;
    
    const venueVal = venueSelect ? venueSelect.value : '0';
    
    if (syncIndicator) {
        syncIndicator.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin" style="font-size:9px;"></i> Checking slots...';
    }
    
    try {
        const res = await fetch(`app/controllers/get_day_schedule.php?date=${encodeURIComponent(dateVal)}&venue=${encodeURIComponent(venueVal)}`);
        const events = await res.json();
        
        let morningTaken = false;
        let afternoonTaken = false;
        let morningTitle = '';
        let afternoonTitle = '';
        
        if (Array.isArray(events)) {
            events.forEach(ev => {
                const dt = new Date(ev.start_time);
                const hr = dt.getHours();
                if (hr < 12) {
                    morningTaken = true;
                    morningTitle = ev.title || 'Booked';
                } else {
                    afternoonTaken = true;
                    afternoonTitle = ev.title || 'Booked';
                }
            });
        }
        
        const overlayM = document.getElementById('slot-overlay-morning');
        const overlayA = document.getElementById('slot-overlay-afternoon');
        const cardM = document.getElementById('slot-card-morning-request');
        const cardA = document.getElementById('slot-card-afternoon-request');
        const badgeM = document.getElementById('slot-badge-morning');
        const badgeA = document.getElementById('slot-badge-afternoon');
        
        // Morning Slot State
        if (morningTaken) {
            if (overlayM) overlayM.style.display = 'flex';
            if (cardM) cardM.classList.add('disabled');
            if (badgeM) {
                badgeM.className = 'slot-badge booked';
                badgeM.innerHTML = '<i class="fa-solid fa-lock" style="font-size:7px;"></i> Acquired';
            }
        } else {
            if (overlayM) overlayM.style.display = 'none';
            if (cardM) cardM.classList.remove('disabled');
            if (badgeM) {
                badgeM.className = 'slot-badge available';
                badgeM.innerHTML = '<i class="fa-solid fa-circle-check" style="font-size:7px;"></i> Available';
            }
        }
        
        // Afternoon Slot State
        if (afternoonTaken) {
            if (overlayA) overlayA.style.display = 'flex';
            if (cardA) cardA.classList.add('disabled');
            if (badgeA) {
                badgeA.className = 'slot-badge booked';
                badgeA.innerHTML = '<i class="fa-solid fa-lock" style="font-size:7px;"></i> Acquired';
            }
        } else {
            if (overlayA) overlayA.style.display = 'none';
            if (cardA) cardA.classList.remove('disabled');
            if (badgeA) {
                badgeA.className = 'slot-badge available';
                badgeA.innerHTML = '<i class="fa-solid fa-circle-check" style="font-size:7px;"></i> Available';
            }
        }
        
        // Handle Fully Booked Case
        if (morningTaken && afternoonTaken) {
            if (fullAlert) fullAlert.style.display = 'flex';
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.style.opacity = '0.5';
                submitBtn.style.cursor = 'not-allowed';
            }
            if (syncIndicator) {
                syncIndicator.innerHTML = '<span style="color:#ef4444;font-weight:700;"><i class="fa-solid fa-ban"></i> Fully Booked</span>';
            }
            if (cardM) cardM.classList.remove('selected');
            if (cardA) cardA.classList.remove('selected');
            dtHidden.value = '';
            return;
        }
        
        if (fullAlert) fullAlert.style.display = 'none';
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.style.opacity = '1';
            submitBtn.style.cursor = 'pointer';
        }
        
        // Auto-switch to whichever slot is open
        if (activeSlotSelection === 'morning' && morningTaken) {
            activeSlotSelection = 'afternoon';
        } else if (activeSlotSelection === 'afternoon' && afternoonTaken) {
            activeSlotSelection = 'morning';
        }
        
        applySlotSelection(activeSlotSelection, dateVal);
        
        if (syncIndicator) {
            syncIndicator.innerHTML = '<span style="color:#22c55e;font-weight:600;"><i class="fa-solid fa-check"></i> Slots updated</span>';
        }
        
    } catch (err) {
        console.error('Slot sync error:', err);
        if (syncIndicator) syncIndicator.textContent = 'Slots verified';
    }
}

function handleSlotTabClick(slot) {
    const overlay = document.getElementById(`slot-overlay-${slot}`);
    if (overlay && overlay.style.display === 'flex') {
        // Slot is acquired/blocked
        return;
    }
    activeSlotSelection = slot;
    const dateInput = document.getElementById('field-date-request');
    const dateVal = dateInput ? dateInput.value : '';
    applySlotSelection(slot, dateVal);
}

function applySlotSelection(slot, dateVal) {
    const cardM = document.getElementById('slot-card-morning-request');
    const cardA = document.getElementById('slot-card-afternoon-request');
    const chipM = document.getElementById('slot-chip-morning');
    const chipA = document.getElementById('slot-chip-afternoon');
    const dtHidden = document.getElementById('field-datetime-request');
    
    if (slot === 'morning') {
        if (cardM) cardM.classList.add('selected');
        if (cardA) cardA.classList.remove('selected');
        if (chipM) chipM.innerHTML = '<div class="slot-open-chip" style="font-size:10px; padding:2px 6px; border-radius:4px; display:flex; align-items:center; gap:4px; background:#111111; border:1px solid #111111; color:#FFFFFF; font-weight:600;"><i class="fa-solid fa-circle-check"></i> <span>Selected</span></div>';
        if (chipA) chipA.innerHTML = '<div class="slot-open-chip" style="font-size:10px; padding:2px 6px; border-radius:4px; display:flex; align-items:center; gap:4px; opacity:0.7;"><i class="fa-solid fa-hand-pointer"></i> <span>Click to Select</span></div>';
        if (dtHidden && dateVal) dtHidden.value = `${dateVal}T09:00`;
    } else {
        if (cardA) cardA.classList.add('selected');
        if (cardM) cardM.classList.remove('selected');
        if (chipA) chipA.innerHTML = '<div class="slot-open-chip" style="font-size:10px; padding:2px 6px; border-radius:4px; display:flex; align-items:center; gap:4px; background:#111111; border:1px solid #111111; color:#FFFFFF; font-weight:600;"><i class="fa-solid fa-circle-check"></i> <span>Selected</span></div>';
        if (chipM) chipM.innerHTML = '<div class="slot-open-chip" style="font-size:10px; padding:2px 6px; border-radius:4px; display:flex; align-items:center; gap:4px; opacity:0.7;"><i class="fa-solid fa-hand-pointer"></i> <span>Click to Select</span></div>';
        if (dtHidden && dateVal) dtHidden.value = `${dateVal}T14:00`;
    }
    
    // Also trigger onFormChange for priest availability if needed
    if (typeof onFormChange === 'function') {
        onFormChange('request');
    }
}

// Initial Slot Synchronization on load
document.addEventListener('DOMContentLoaded', () => {
    syncRequestSlots();
});
setTimeout(() => {
    syncRequestSlots();
}, 200);

// ── CONFIRMATION MODAL ────────────────────────────────────────────────
function showConfirmationModal() {
    const form = document.getElementById('request-event-form');
    if (!form) return;
    if (!form.checkValidity()) { form.reportValidity(); return; }

    const data = {};
    new FormData(form).forEach((v, k) => data[k] = v);

    const isCert = selectedServiceObj && (selectedServiceObj.code === 'certificates' || selectedServiceObj.name.toLowerCase().includes('certificate'));

    if (isCert) {
        let pickupFormatted = 'Not specified';
        const pickupVal = document.getElementById('field-pickup-date')?.value || '';
        if (pickupVal) {
            try {
                const dt = new Date(pickupVal + 'T00:00:00');
                pickupFormatted = dt.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
            } catch(e) {
                pickupFormatted = pickupVal;
            }
        }
        const certName = data.cert_person_name || 'Not specified';
        const certYear = data.cert_year ? `Approx. Year: ${data.cert_year}` : 'Year not specified';
        const certPurpose = data.cert_purpose || 'Personal Records';
        const copies = parseInt(data.certificate_copies || '1', 10);
        const variantName = selectedVariantObj ? selectedVariantObj.name : 'Certificate';
        const totalFeeText = document.getElementById('fee-total-display') ? document.getElementById('fee-total-display').textContent : 'PHP 0.00';

        document.getElementById('confirmation-content').innerHTML = `
            <div style="background:var(--bg3); border:1px solid var(--border); border-radius:8px; padding:16px;">
                <div style="display:flex; align-items:start; gap:12px; margin-bottom:12px;">
                    <div style="width:40px; height:40px; border-radius:8px; background:rgba(197,160,89,0.15); border:1px solid rgba(197,160,89,0.4); color:var(--accent, #c5a059); display:flex; align-items:center; justify-content:center; font-size:18px; flex-shrink:0;">
                        <i class="fa-solid fa-file-invoice"></i>
                    </div>
                    <div>
                        <div style="font-size:11px; color:var(--text2); text-transform:uppercase; letter-spacing:0.05em; font-weight:600; margin-bottom:2px;">Certificate Request Details</div>
                        <div style="font-size:16px; font-weight:700; color:var(--text);">${escapeHtml(variantName)}</div>
                    </div>
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; border-top:1px solid var(--border); padding-top:12px;">
                    <div>
                        <div style="font-size:11px; color:var(--text2); text-transform:uppercase; letter-spacing:0.05em; font-weight:600; margin-bottom:4px;">Person on Record</div>
                        <div style="font-size:13px; color:var(--text); font-weight:700;">${escapeHtml(certName)}</div>
                    </div>
                    <div>
                        <div style="font-size:11px; color:var(--text2); text-transform:uppercase; letter-spacing:0.05em; font-weight:600; margin-bottom:4px;">Sacrament Year</div>
                        <div style="font-size:13px; color:var(--text);">${escapeHtml(certYear)}</div>
                    </div>
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; border-top:1px solid var(--border); padding-top:12px; margin-top:12px;">
                    <div>
                        <div style="font-size:11px; color:var(--text2); text-transform:uppercase; letter-spacing:0.05em; font-weight:600; margin-bottom:4px;">Target Pickup Date</div>
                        <div style="font-size:13px; color:#111111; font-weight:700;">${escapeHtml(pickupFormatted)}</div>
                    </div>
                    <div>
                        <div style="font-size:11px; color:var(--text2); text-transform:uppercase; letter-spacing:0.05em; font-weight:600; margin-bottom:4px;">Purpose</div>
                        <div style="font-size:13px; color:var(--text);">${escapeHtml(certPurpose)}</div>
                    </div>
                </div>

                <div style="border-top:1px solid var(--border); padding-top:12px; margin-top:12px; display:flex; gap:12px; align-items:start;">
                    <i class="fa-solid fa-building-columns" style="color:#737373; font-size:16px; margin-top:2px;"></i>
                    <div>
                        <div style="font-size:11px; color:var(--text2); text-transform:uppercase; letter-spacing:0.05em; font-weight:600; margin-bottom:4px;">Releasing Location</div>
                        <div style="font-size:13px; color:var(--text); font-weight:600;">IFI Cathedral Parish Office (Tuesday to Sunday, 8am–5pm)</div>
                    </div>
                </div>

                ${data.requester_email || data.requester_phone ? `
                <div style="border-top:1px solid var(--border); padding-top:12px; margin-top:12px;">
                    <div style="font-size:11px; color:var(--text2); text-transform:uppercase; letter-spacing:0.05em; font-weight:600; margin-bottom:6px;">Applicant Contact</div>
                    ${data.requester_email ? `<div style="font-size:12px; color:var(--text); margin-bottom:3px;"><i class="fa-solid fa-envelope" style="color:var(--text2); margin-right:6px;"></i>${escapeHtml(data.requester_email)}</div>` : ''}
                    ${data.requester_phone ? `<div style="font-size:12px; color:var(--text);"><i class="fa-solid fa-phone" style="color:var(--text2); margin-right:6px;"></i>${escapeHtml(data.requester_phone)}</div>` : ''}
                </div>` : ''}

                <div style="border-top:1px solid var(--border); padding-top:12px; margin-top:12px;">
                    <div style="font-size:11px; color:var(--text2); text-transform:uppercase; letter-spacing:0.05em; font-weight:600; margin-bottom:6px;">Fee Summary</div>
                    <div style="background:var(--bg2); border:1px solid var(--border); border-radius:6px; padding:10px 12px;">
                        <div style="display:flex; justify-content:space-between; font-size:12px; color:var(--text); margin-bottom:2px;">
                            <span>Quantity Requested:</span>
                            <strong>${copies} ${copies > 1 ? 'copies' : 'copy'}</strong>
                        </div>
                        <div style="display:flex; justify-content:space-between; font-size:13px; font-weight:700; color:#22c55e; border-top:1px dashed var(--border); padding-top:6px; margin-top:4px;">
                            <span>Total Document Fee:</span>
                            <strong>${totalFeeText}</strong>
                        </div>
                        <div style="font-size:9.5px; color:var(--text2); margin-top:4px;">* Reference Rate (Dec 16, 2012 Synod) — verified by Cathedral office upon releasing.</div>
                    </div>
                </div>
            </div>
            <div style="background:rgba(197,160,89,0.12); border:1px solid rgba(197,160,89,0.3); border-radius:8px; padding:10px; font-size:12px; color:var(--text2);">
                <i class="fa-solid fa-circle-info" style="color:var(--accent, #c5a059); margin-right:6px;"></i>
                By confirming, your certificate request will be submitted to the Parish Office for canonical record verification and issuance.
            </div>
        `;
        document.getElementById('confirmation-modal').classList.add('open');
        return;
    }

    let dateTime = 'Not specified';
    if (data.start_time) {
        const dt = new Date(data.start_time);
        dateTime = dt.toLocaleString('en-US', { weekday:'long', year:'numeric', month:'long', day:'numeric', hour:'2-digit', minute:'2-digit' });
    }

    const locationSel = form.querySelector('select[name="chapel_id"]');
    const locationText = locationSel ? locationSel.options[locationSel.selectedIndex].text : 'Not selected';
    const typeSel = form.querySelector('select[name="event_type"]');
    const typeText = typeSel ? typeSel.options[typeSel.selectedIndex].text : 'Not selected';

    document.getElementById('confirmation-content').innerHTML = `
        <div style="background:var(--bg3); border:1px solid var(--border); border-radius:8px; padding:16px;">
            <div style="display:flex; align-items:start; gap:12px; margin-bottom:12px;">
                <i class="fa-solid fa-calendar-check" style="color:#111111; font-size:20px; margin-top:2px;"></i>
                <div>
                    <div style="font-size:11px; color:var(--text2); text-transform:uppercase; letter-spacing:0.05em; font-weight:600; margin-bottom:4px;">Event Title</div>
                    <div style="font-size:16px; font-weight:700; color:var(--text);">${escapeHtml(data.title || 'Not specified')}</div>
                </div>
            </div>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; border-top:1px solid var(--border); padding-top:12px;">
                <div><div style="font-size:11px; color:var(--text2); text-transform:uppercase; letter-spacing:0.05em; font-weight:600; margin-bottom:4px;">Event Type</div><div style="font-size:13px; color:var(--text);">${escapeHtml(typeText)}</div></div>
                <div><div style="font-size:11px; color:var(--text2); text-transform:uppercase; letter-spacing:0.05em; font-weight:600; margin-bottom:4px;">Scope</div><div style="font-size:13px; color:var(--text); text-transform:capitalize;">${escapeHtml(data.event_scope || 'N/A')}</div></div>
            </div>
            <div style="border-top:1px solid var(--border); padding-top:12px; margin-top:12px; display:flex; gap:12px; align-items:start;">
                <i class="fa-solid fa-clock" style="color:#737373; font-size:16px; margin-top:2px;"></i>
                <div><div style="font-size:11px; color:var(--text2); text-transform:uppercase; letter-spacing:0.05em; font-weight:600; margin-bottom:4px;">Date &amp; Time</div><div style="font-size:13px; color:var(--text); font-weight:600;">${dateTime}</div></div>
            </div>
            <div style="border-top:1px solid var(--border); padding-top:12px; margin-top:12px; display:flex; gap:12px; align-items:start;">
                <i class="fa-solid fa-location-dot" style="color:#737373; font-size:16px; margin-top:2px;"></i>
                <div><div style="font-size:11px; color:var(--text2); text-transform:uppercase; letter-spacing:0.05em; font-weight:600; margin-bottom:4px;">Location</div><div style="font-size:13px; color:var(--text);">${escapeHtml(locationText)}</div></div>
            </div>
            ${data.requester_email || data.requester_phone ? `
            <div style="border-top:1px solid var(--border); padding-top:12px; margin-top:12px;">
                <div style="font-size:11px; color:var(--text2); text-transform:uppercase; letter-spacing:0.05em; font-weight:600; margin-bottom:6px;">Contact</div>
                ${data.requester_email ? `<div style="font-size:12px; color:var(--text); margin-bottom:3px;"><i class="fa-solid fa-envelope" style="color:var(--text2); margin-right:6px;"></i>${escapeHtml(data.requester_email)}</div>` : ''}
                ${data.requester_phone ? `<div style="font-size:12px; color:var(--text);"><i class="fa-solid fa-phone" style="color:var(--text2); margin-right:6px;"></i>${escapeHtml(data.requester_phone)}</div>` : ''}
            </div>` : ''}
            ${selectedVariantObj ? `
            <div style="border-top:1px solid var(--border); padding-top:12px; margin-top:12px;">
                <div style="font-size:11px; color:var(--text2); text-transform:uppercase; letter-spacing:0.05em; font-weight:600; margin-bottom:6px;">Service Tier &amp; Rate Snapshot</div>
                <div style="background:var(--bg2); border:1px solid var(--border); border-radius:6px; padding:10px 12px;">
                    <div style="display:flex; justify-content:space-between; font-size:12px; color:var(--text); margin-bottom:2px;">
                        <span>Variant / Option:</span>
                        <strong style="color:#111111;">${escapeHtml(selectedVariantObj.name)}</strong>
                    </div>
                    <div style="display:flex; justify-content:space-between; font-size:13px; font-weight:700; color:#22c55e; border-top:1px dashed var(--border); padding-top:6px; margin-top:4px;">
                        <span>Estimated Total Fee:</span>
                        <strong>${document.getElementById('fee-total-display') ? document.getElementById('fee-total-display').textContent : 'PHP 0.00'}</strong>
                    </div>
                    <div style="font-size:9.5px; color:var(--text2); margin-top:4px;">* Reference Rate (Dec 16, 2012 Synod) — verified by Cathedral upon review.</div>
                </div>
            </div>` : ''}
        </div>
        <div style="background:rgba(197,160,89,0.12); border:1px solid rgba(197,160,89,0.3); border-radius:8px; padding:10px; font-size:12px; color:var(--text2);">
            <i class="fa-solid fa-circle-info" style="color:var(--accent, #c5a059); margin-right:6px;"></i>
            By confirming, your request will be sent to the Cathedral Admin for review.
        </div>
    `;

    if (isGuest) {
        document.getElementById('confirmation-content').innerHTML += `
            <div style="background: rgba(197,160,89,0.12); border: 1px solid rgba(197,160,89,0.35); border-radius: 8px; padding: 12px 14px; margin-top: 10px; display: flex; align-items: flex-start; gap: 10px;">
                <i class="fa-solid fa-id-card-clip" style="color: var(--accent); font-size: 18px; margin-top: 2px;"></i>
                <div>
                    <div style="font-weight: 700; color: var(--accent); font-size: 13px;">Parishioner Membership Required</div>
                    <div style="font-size: 11.5px; color: var(--text); margin-top: 3px; line-height: 1.45;">
                        Your request details are complete! To officially submit and record this request with the Cathedral office, you will need to sign in or register as a member. Your details are saved!
                    </div>
                </div>
            </div>
        `;
        const footerStd = document.getElementById('confirmation-footer-standard');
        const footerGuest = document.getElementById('confirmation-footer-guest');
        if (footerStd) footerStd.style.display = 'none';
        if (footerGuest) footerGuest.style.display = 'flex';
    } else {
        const footerStd = document.getElementById('confirmation-footer-standard');
        const footerGuest = document.getElementById('confirmation-footer-guest');
        if (footerStd) footerStd.style.display = 'flex';
        if (footerGuest) footerGuest.style.display = 'none';
    }

    document.getElementById('confirmation-modal').classList.add('open');
}

function closeConfirmationModal() { document.getElementById('confirmation-modal').classList.remove('open'); }

function confirmSubmission() {
    if (isGuest) {
        saveFormDraft();
        closeConfirmationModal();
        closeRequestEventModal();
        if (typeof openGuestAuthModal === 'function') {
            openGuestAuthModal('submit your sacramental or liturgical service request');
        } else if (typeof openModal === 'function') {
            openModal('modal-guest-auth');
        }
        return false;
    }
    document.getElementById('request-event-form').submit();
}

function saveFormDraft() {
    try {
        const form = document.getElementById('request-event-form');
        if (!form) return;
        const data = {};
        new FormData(form).forEach((v, k) => data[k] = v);
        sessionStorage.setItem('ifi_service_request_draft', JSON.stringify(data));
        const banner = document.getElementById('draft-restore-banner');
        if (banner) {
            banner.style.display = 'flex';
            const nameEl = document.getElementById('draft-service-name');
            if (nameEl) nameEl.textContent = data.title || data.event_type || 'Service Request';
        }
    } catch(e) {
        console.warn('Could not save draft to sessionStorage', e);
    }
}

function resumeDraft() {
    try {
        const raw = sessionStorage.getItem('ifi_service_request_draft');
        if (!raw) return;
        const data = JSON.parse(raw);
        if (!data) return;

        openRequestEventModal(data.event_type || '');
        const form = document.getElementById('request-event-form');
        if (!form) return;

        for (const k in data) {
            const input = form.elements[k];
            if (input && input.type !== 'file') {
                input.value = data[k];
            }
        }
        if (data.event_type) {
            const sel = document.getElementById('field-event-type-request');
            if (sel) handleServiceCatalogChange(sel);
        }
        if (data.variant_id) {
            const vSel = document.getElementById('field-variant-selector');
            if (vSel) {
                vSel.value = data.variant_id;
                handleVariantChange(vSel);
            }
        }
    } catch(e) {
        console.warn('Could not resume draft', e);
    }
}

function discardDraft() {
    sessionStorage.removeItem('ifi_service_request_draft');
    const banner = document.getElementById('draft-restore-banner');
    if (banner) banner.style.display = 'none';
}

function checkDraftOnLoad() {
    try {
        const raw = sessionStorage.getItem('ifi_service_request_draft');
        if (!raw) return;
        const data = JSON.parse(raw);
        if (data && (data.event_type || data.title)) {
            const banner = document.getElementById('draft-restore-banner');
            if (banner) {
                banner.style.display = 'flex';
                const nameEl = document.getElementById('draft-service-name');
                if (nameEl) nameEl.textContent = data.title || data.event_type || 'Service Request';
            }
        }
    } catch(e) {}
}

document.addEventListener('DOMContentLoaded', checkDraftOnLoad);
setTimeout(checkDraftOnLoad, 300);

// ── DELETE / CANCEL MODALS ────────────────────────────────────────────
function deleteRequest(idOrEl, name) {
    let id = idOrEl;
    if (idOrEl instanceof HTMLElement) {
        id = idOrEl.getAttribute('data-id');
        name = idOrEl.getAttribute('data-name');
    }
    const input = document.getElementById('delete-request-id');
    if (input) input.value = id || '';
    const span = document.getElementById('delete-request-name');
    if (span) span.textContent = name || '';
    const modal = document.getElementById('delete-modal');
    if (modal) modal.classList.add('open');
}
function closeDeleteModal() { const m = document.getElementById('delete-modal'); if (m) m.classList.remove('open'); }

function cancelRequest(idOrEl, name) {
    let id = idOrEl;
    if (idOrEl instanceof HTMLElement) {
        id = idOrEl.getAttribute('data-id');
        name = idOrEl.getAttribute('data-name');
    }
    const input = document.getElementById('cancel-request-id');
    if (input) input.value = id || '';
    const span = document.getElementById('cancel-request-name');
    if (span) span.textContent = name || '';
    const modal = document.getElementById('cancel-modal');
    if (modal) modal.classList.add('open');
}
function closeCancelModal() { const m = document.getElementById('cancel-modal'); if (m) m.classList.remove('open'); }

window.deleteRequest = deleteRequest;
window.closeDeleteModal = closeDeleteModal;
window.cancelRequest = cancelRequest;
window.closeCancelModal = closeCancelModal;

// ── VIEW DETAILS ──────────────────────────────────────────────────────
function viewRequestDetailsFromRow(tr) {
    console.log('viewRequestDetailsFromRow called');
    const dataAttr = tr.getAttribute('data-req');
    console.log('Data attribute:', dataAttr);
    
    if (!dataAttr) {
        console.error('No data-req attribute found');
        alert('No data found on this row');
        return;
    }
    
    try {
        const req = JSON.parse(dataAttr);
        console.log('Parsed request:', req);
        
        // Check if modal exists
        const modal = document.getElementById('view-details-modal');
        console.log('Modal element:', modal);
        
        if (!modal) {
            console.error('Modal not found');
            alert('Modal not found in DOM');
            return;
        }
        
        // Call the view function
        viewRequestDetails(req);
        console.log('viewRequestDetails called');
        
    } catch(e) {
        console.error('Error:', e);
        alert('Error: ' + e.message);
    }
}

function viewRequestDetails(req) {
    const statusColors = { Confirmed:'#16a34a', Pending:'#c5a059', Cancelled:'#dc2626', Rejected:'#dc2626' };
    const sc = statusColors[req.status] || '#6b7280';
    
    let dt = 'N/A';
    if (req.start_time) {
        try {
            const normalized = req.start_time.replace(/-/g, '/');
            const parsedDate = new Date(normalized);
            if (!isNaN(parsedDate.getTime())) {
                dt = parsedDate.toLocaleDateString('en-US', {
                    weekday: 'short',
                    year: 'numeric',
                    month: 'short',
                    day: 'numeric'
                }) + ' @ ' + parsedDate.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
            } else {
                dt = req.start_time;
            }
        } catch (e) {
            dt = req.start_time;
        }
    }

    const evType = (req.event_type || 'Event');
    const lt = evType.toLowerCase();
    const isCertReq = lt.includes('certificate') || (req.title && req.title.toLowerCase().includes('certificate'));
    
    let typeIcon = 'fa-church';
    if (isCertReq) typeIcon = 'fa-file-invoice';
    else if (lt.includes('wedding')) typeIcon = 'fa-heart';
    else if (lt.includes('baptism')) typeIcon = 'fa-water';
    else if (lt.includes('funeral')) typeIcon = 'fa-cross';
    else if (lt.includes('confirmation')) typeIcon = 'fa-dove';

    document.getElementById('view-details-body').innerHTML = `
        <div style="display:flex; flex-direction:column; gap:12px;">
            <!-- Hero Card -->
            <div style="background: var(--bg2, #F6F6F6); border: 1px solid var(--border); border-radius: 10px; padding: 14px 16px; display: flex; align-items: center; gap: 14px;">
                <div style="width: 40px; height: 40px; border-radius: 8px; background: var(--surface, #FFFFFF); border: 1px solid var(--border); color: var(--text, #111111); display: flex; align-items: center; justify-content: center; font-size: 16px; flex-shrink: 0;">
                    <i class="fa-solid ${typeIcon}"></i>
                </div>
                <div style="min-width: 0; flex: 1;">
                    <div style="display: flex; gap: 6px; align-items: center; margin-bottom: 4px;">
                        <span class="badge" style="background: var(--surface, #FFFFFF); border: 1px solid var(--border); font-size: 11.5px; font-weight: 600; padding: 2px 8px; color: var(--text);">${escapeHtml(isCertReq ? 'Certificate Issuance' : evType)}</span>
                        <span class="badge" style="background: ${sc}18; color: ${sc}; border: 1px solid ${sc}35; font-size: 11.5px; font-weight: 600; padding: 2px 8px;">${escapeHtml(req.status)}</span>
                        <span style="font-size: 11px; color: var(--text2); font-family: monospace;">#REQ-${String(req.event_id).padStart(4, '0')}</span>
                    </div>
                    <div style="font-family: var(--font-family, 'Inter', sans-serif); font-size: 15px; font-weight: 700; color: var(--text); line-height: 1.3;">${escapeHtml(req.title)}</div>
                </div>
            </div>

            <!-- Details Grid -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                <div style="background: var(--bg2, #F6F6F6); border: 1px solid var(--border); border-radius: 8px; padding: 10px 12px;">
                    <div style="font-size: 11px; text-transform: uppercase; color: var(--text2); font-weight: 700; margin-bottom: 3px; letter-spacing: 0.05em;"><i class="fa-regular fa-calendar" style="color:var(--text); margin-right:4px;"></i> ${isCertReq ? 'Target Pickup Date' : 'Scheduled For'}</div>
                    <div style="font-size: 12.5px; color: var(--text); font-weight: 600;">${dt}</div>
                </div>
                <div style="background: var(--bg2, #F6F6F6); border: 1px solid var(--border); border-radius: 8px; padding: 10px 12px;">
                    <div style="font-size: 11px; text-transform: uppercase; color: var(--text2); font-weight: 700; margin-bottom: 3px; letter-spacing: 0.05em;"><i class="fa-solid fa-location-dot" style="color:var(--text); margin-right:4px;"></i> ${isCertReq ? 'Releasing Office' : 'Venue Location'}</div>
                    <div style="font-size: 12.5px; color: var(--text); font-weight: 600;">${escapeHtml(isCertReq ? 'IFI Cathedral Parish Office' : (req.chapel_location || 'Main Cathedral'))}</div>
                </div>
                <div style="background: var(--bg2, #F6F6F6); border: 1px solid var(--border); border-radius: 8px; padding: 10px 12px;">
                    <div style="font-size: 11px; text-transform: uppercase; color: var(--text2); font-weight: 700; margin-bottom: 3px; letter-spacing: 0.05em;"><i class="fa-solid fa-${isCertReq ? 'id-card-clip' : 'user-tie'}" style="color:var(--text); margin-right:4px;"></i> ${isCertReq ? 'Processing Section' : 'Assigned Priest'}</div>
                    <div style="font-size: 12.5px; color: var(--text); font-weight: 600;">${escapeHtml(isCertReq ? 'Registry & Archives' : (req.pastor_assigned || 'Pending Assignment'))}</div>
                </div>
                <div style="background: var(--bg2, #F6F6F6); border: 1px solid var(--border); border-radius: 8px; padding: 10px 12px;">
                    <div style="font-size: 11px; text-transform: uppercase; color: var(--text2); font-weight: 700; margin-bottom: 3px; letter-spacing: 0.05em;"><i class="fa-solid fa-layer-group" style="color:var(--text); margin-right:4px;"></i> Jurisdiction</div>
                    <div style="font-size: 12.5px; color: var(--text); font-weight: 600; text-transform: capitalize;">${escapeHtml(req.event_scope || 'Cathedral')}</div>
                </div>
            </div>

            ${req.notes ? `
            <div style="background: var(--bg2, #F6F6F6); border: 1px solid var(--border, #E5E5E5); border-radius: 10px; padding: 10px 14px;">
                <div style="font-size: 11px; text-transform: uppercase; color: var(--text, #111111); font-weight: 700; margin-bottom: 3px; letter-spacing: 0.05em;"><i class="fa-solid fa-quote-left"></i> Your Special Instructions</div>
                <div style="font-size: 12px; color: var(--text); line-height: 1.4; white-space: pre-wrap;">${escapeHtml(req.notes)}</div>
            </div>` : ''}

            ${req.rejection_note ? `
            <div style="background: rgba(239,68,68,0.08); border: 1px solid rgba(239,68,68,0.25); border-radius: 10px; padding: 10px 14px;">
                <div style="font-size: 11px; text-transform: uppercase; color: #ef4444; font-weight: 700; margin-bottom: 3px; letter-spacing: 0.05em;"><i class="fa-solid fa-triangle-exclamation"></i> Reason from Office</div>
                <div style="font-size: 12px; color: #fca5a5; line-height: 1.4; white-space: pre-wrap;">${escapeHtml(req.rejection_note)}</div>
            </div>` : ''}

            ${(parseFloat(req.total_fee || 0) > 0 || parseFloat(req.service_fee || 0) > 0) ? `
            <div style="background: rgba(34,197,94,0.06); border: 1px solid rgba(34,197,94,0.25); border-radius: 10px; padding: 10px 14px;">
                <div style="font-size: 11px; text-transform: uppercase; color: #16a34a; font-weight: 700; margin-bottom: 4px; letter-spacing: 0.05em;"><i class="fa-solid fa-receipt"></i> Financial Snapshot at Request Submission</div>
                <div style="display:flex; justify-content:space-between; font-size:12px; color:var(--text);">
                    <span>Service Base Fee:</span>
                    <strong>PHP ${parseFloat(req.service_fee || 0).toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2})}</strong>
                </div>
                ${parseFloat(req.addons_fee || 0) > 0 ? `
                <div style="display:flex; justify-content:space-between; font-size:12px; color:var(--text); margin-top:2px;">
                    <span>Optional Add-ons Fee:</span>
                    <strong>+ PHP ${parseFloat(req.addons_fee || 0).toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2})}</strong>
                </div>` : ''}
                <div style="display:flex; justify-content:space-between; font-size:13px; font-weight:700; color:var(--text, #111111); border-top:1px dashed rgba(34,197,94,0.3); padding-top:4px; margin-top:4px;">
                    <span>Total Recorded Fee:</span>
                    <strong style="color:#22c55e;">PHP ${parseFloat(req.total_fee || 0).toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2})}</strong>
                </div>
                <div style="font-size:9.5px; color:var(--text2); margin-top:4px;">Recorded historical rate. Future catalog adjustments do not affect this transaction.</div>
            </div>` : ''}
        </div>
    `;
    document.getElementById('view-details-modal').classList.add('open');
}

function escapeHtml(text) {
    if (!text && text !== 0) return '';
    const d = document.createElement('div');
    d.textContent = String(text);
    return d.innerHTML;
}

if (window.history.replaceState) {
    window.history.replaceState(null, null, window.location.href);
}
</script>
