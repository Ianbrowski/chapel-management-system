<?php
try {
    require_once __DIR__ . '/../../config/config.php';
    require_once BASE_PATH . '/app/controllers/DashboardController.php';

    // Initialize Traffic Controller
    $controller = new DashboardController($conn);
    $controller->handleRequest();

    // Composer loads data based on the active section (Performance Optimized)
    $active_section = $_GET['section'] ?? null;
    if (!$active_section) {
        $active_section = 'events';
    }
    $viewData = $controller->loadViewData($active_section);
    $active_section = $viewData['active_section'] ?? $active_section;
    require BASE_PATH . '/views/partials/dashboard/view_context.php';

    // AJAX Request Handling for Smooth Navigation - Moved inside try/catch for safety
    if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
        // If AJAX, we skip the main layout and return only the requested partial
        include __DIR__ . '/../../views/partials/dashboard/ajax_renderer.php';
        exit();
    }
} catch (Throwable $e) {
    require_once __DIR__ . '/../../app/helpers/error_debug.php';
    cms_store_exception($e);
    $dest = (strpos($_SERVER['PHP_SELF'], 'views/pages/') !== false) ? 'error_500.php' : 'views/pages/error_500.php';
    header("Location: $dest");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Cathedral Management System</title>
    <base href="<?php echo rtrim(BASE_URL, '/') . '/'; ?>">
    <script>
        window.BASE_URL = '<?php echo rtrim(BASE_URL, '/'); ?>';
        window.CSRF_TOKEN = '<?php echo htmlspecialchars($_SESSION['csrf'] ?? '', ENT_QUOTES, 'UTF-8'); ?>';
    </script>
    <link rel="icon" type="image/png" href="assets/img/2323.png">
    <link rel="shortcut icon" type="image/x-icon" href="assets/img/2323.png">
    <link rel="apple-touch-icon" href="assets/img/2323.png">
    <script src="assets/js/theme.js"></script>
    <script>
        ThemeSystem.applyInitialTheme();
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@500;600;700;800&family=Fira+Code:wght@400;500;600&family=Inter:wght@300;400;500;600;700;800&family=Outfit:wght@400;500;600;700;800&family=Playfair+Display:ital,wght@0,600;0,700;1,600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/fonts.css?v=<?= time() ?>">
    <link rel="stylesheet" href="assets/css/all.min.css">
    <link rel="stylesheet" href="assets/css/theme.css?v=<?= time() ?>">
    <link rel="stylesheet" href="assets/css/utilities.css?v=<?= time() ?>">
    <link rel="stylesheet" href="assets/css/layout.css?v=<?= time() ?>">
    <link rel="stylesheet" href="assets/css/components.css?v=<?= time() ?>">
    <link rel="stylesheet" href="assets/css/dashboard.css?v=<?= time() ?>">
    <link rel="stylesheet" href="assets/css/skeleton.css?v=<?= time() ?>">
    <link rel="stylesheet" href="assets/css/workspace.css?v=<?= time() ?>">
    <link rel="stylesheet" href="assets/css/liquid-sidebar.css?v=<?= time() ?>">
    <?php if (in_array($role ?? '', ['Admin', 'Cathedral Admin', 'Chapel Admin', 'Priest', 'Pastor', 'Staff'], true)): ?>
    <link rel="stylesheet" href="assets/css/parish-chat.css?v=<?= time() ?>">
    <?php endif; ?>
    <script src="assets/js/chart.umd.min.js"></script>
    <script src="assets/js/skeleton-loader.js?v=<?= time() ?>"></script>
    <script src="assets/js/dashboard-records.js?v=<?= time() ?>"></script>
    <script src="assets/js/liquid-sidebar.js?v=<?= time() ?>"></script>
</head>

<body>

    <div class="overlay-bg" id="overlayBg" onclick="closeSidebar()"></div>

    <!-- SIDEBAR -->
    <aside class="sidebar" id="sidebar">
        <!-- Liquid Gliding Active Indicator (Centered, unclipped, pure transparent background) -->
        <div class="liquid-indicator" id="liquidIndicator">
            <div class="liquid-circle">
                <i class="liquid-circle-icon fa-solid fa-circle"></i>
            </div>
        </div>

        <!-- Floating Sub-topics Flyout Menu (Compact Rail Mode) -->
        <div class="subtopic-flyout" id="subtopicFlyout" style="display: none;" role="menu" aria-label="Sub-topics menu">
            <div class="subtopic-flyout-notch"></div>
            <div class="subtopic-flyout-header">
                <div class="subtopic-flyout-eyebrow"><i class="fa-solid fa-diagram-project"></i> Sub-topics</div>
                <div class="subtopic-flyout-title" id="subtopicFlyoutTitle">Category</div>
            </div>
            <div class="subtopic-flyout-list" id="subtopicFlyoutList"></div>
        </div>

        <div class="sidebar-header">
            <button type="button" class="sidebar-mode-toggle" id="sidebarModeToggle" title="Expand Navigation" data-tooltip="Expand Navigation" onclick="toggleSidebarCompactMode(event)">
                <i class="fa-solid fa-bars"></i>
            </button>
            <div class="sidebar-logo" role="button" tabindex="0" title="Refresh Dashboard"
                onclick="window.location.reload();"
                onkeydown="if(event.key==='Enter' || event.key===' '){ event.preventDefault(); window.location.reload(); }"
                style="cursor:pointer;">
                <img src="assets/img/2323.png" alt="logo">
                <div class="sidebar-logo-text">Cathedral<br>Management</div>
            </div>
        </div>

        <div class="sidebar-user" data-tooltip="<?php echo htmlspecialchars($full_name ?: 'My Profile'); ?>" <?php if (!$isGuest): ?>onclick="showSection('my_profile',null)" style="cursor:pointer;"<?php else: ?>style="cursor:default;"<?php endif; ?>>
            <div class="sidebar-avatar <?php echo $profile_pic ? 'has-pic' : ''; ?>">
                <?php if ($profile_pic && file_exists(BASE_PATH . '/storage/' . $profile_pic)): ?>
                    <img src="storage/<?php echo htmlspecialchars($profile_pic); ?>" class="sidebar-avatar-img">
                <?php else: ?>
                    <i class="fa-solid fa-circle-user"></i>
                <?php endif; ?>
            </div>
            <div class="sidebar-user-info">
                <div class="sidebar-username"><?php echo htmlspecialchars($full_name); ?></div>
                <div class="sidebar-role"><?php echo $isGuest ? '<span class="badge" style="background: rgba(197,160,89,0.18); color: var(--accent); font-size:10px; padding:2px 8px; border-radius:10px; border:1px solid rgba(197,160,89,0.35);">Guest View</span>' : htmlspecialchars($role); ?></div>
            </div>
        </div>

        <nav class="sidebar-nav" id="sidebarNav">
            <?php if ($isGuest): ?>
                <!-- ── GUEST PREVIEW ── -->
                <div class="nav-section-label">Guest Preview</div>
                <a class="nav-item <?php echo in_array($active_section, ['events', 'overview']) ? 'active' : ''; ?>"
                    data-section="events" data-tooltip="Calendar" href="dashboard/events">
                    <i class="fa-solid fa-calendar-days"></i> <span>Calendar</span>
                </a>
                <a class="nav-item <?php echo ($active_section === 'bulletin') ? 'active' : ''; ?>"
                    data-section="bulletin" data-tooltip="Bulletin" href="dashboard/bulletin">
                    <i class="fa-solid fa-bullhorn"></i> <span>Bulletin</span>
                </a>
                <a class="nav-item <?php echo ($active_section === 'request_certificate') ? 'active' : ''; ?>"
                    data-section="request_certificate" data-tooltip="Certificates" href="dashboard/request_certificate">
                    <i class="fa-solid fa-file-invoice"></i> <span>Certificates</span>
                </a>
                <a class="nav-item <?php echo ($active_section === 'request_event') ? 'active' : ''; ?>"
                    data-section="request_event" data-tooltip="Request Service" href="dashboard/request_event">
                    <i class="fa-solid fa-paper-plane"></i> <span>Request Service</span>
                </a>

            <?php elseif ($isMember): ?>
                <!-- ── PARISHIONER PORTAL (MEMBER VIEW) ── -->
                <div class="nav-section-label">Parishioner Portal</div>
                <a class="nav-item <?php echo in_array($active_section, ['events', 'overview']) ? 'active' : ''; ?>"
                    data-section="events" data-tooltip="Calendar" href="dashboard/events">
                    <i class="fa-solid fa-calendar-days"></i> <span>Calendar</span>
                </a>
                <a class="nav-item <?php echo ($active_section === 'bulletin') ? 'active' : ''; ?>"
                    data-section="bulletin" data-tooltip="Bulletin" href="dashboard/bulletin">
                    <i class="fa-solid fa-bullhorn"></i> <span>Bulletin</span>
                </a>

                <a class="nav-item <?php echo ($active_section === 'request_certificate') ? 'active' : ''; ?>"
                    data-section="request_certificate" data-tooltip="Certificates" href="dashboard/request_certificate">
                    <i class="fa-solid fa-file-invoice"></i> <span>Certificates</span>
                </a>

                <a class="nav-item <?php echo ($active_section === 'request_event') ? 'active' : ''; ?>"
                    data-section="request_event" data-tooltip="Request Service" href="dashboard/request_event">
                    <i class="fa-solid fa-paper-plane"></i> <span>Request Service</span>
                </a>

                <a class="nav-item <?php echo ($active_section === 'merchandise') ? 'active' : ''; ?>"
                    data-section="merchandise" data-tooltip="Parish Store" href="dashboard/merchandise">
                    <i class="fa-solid fa-store"></i> <span>Parish Store</span>
                </a>

                <a class="nav-item <?php echo ($active_section === 'public_inquiries') ? 'active' : ''; ?>"
                    data-section="public_inquiries" data-tooltip="Inquiries" href="dashboard/public_inquiries">
                    <i class="fa-solid fa-comments"></i> <span>Inquiries</span>
                    <?php if (!empty($pending_inquiries)): ?>
                        <span class="nav-badge"><?php echo (int)$pending_inquiries; ?></span>
                    <?php endif; ?>
                </a>

            <?php elseif (in_array($role, ['Priest', 'Pastor'], true)): ?>
                <!-- ── LITURGICAL CELEBRANT (PRIEST VIEW) ── -->
                <div class="nav-section-label">Pastoral Ministry</div>
                <a class="nav-item <?php echo in_array($active_section, ['priest_schedule', 'priest_prefs', 'pastor_schedule', 'pastor_prefs']) ? 'active' : ''; ?>"
                    data-section="priest_schedule" data-tooltip="Priest Duty" href="dashboard/priest_schedule">
                    <i class="fa-solid fa-clipboard-user"></i> <span>Priest Duty</span>
                </a>

                <a class="nav-item <?php echo in_array($active_section, ['events', 'overview']) ? 'active' : ''; ?>" data-section="events"
                    data-tooltip="Calendar" href="dashboard/events">
                    <i class="fa-solid fa-calendar-days"></i> <span>Calendar</span>
                </a>

                <div class="nav-section-label">Community</div>
                <a class="nav-item <?php echo ($active_section === 'bulletin') ? 'active' : ''; ?>"
                    data-section="bulletin" data-tooltip="Bulletin" href="dashboard/bulletin">
                    <i class="fa-solid fa-bullhorn"></i> <span>Bulletin</span>
                </a>

            <?php else: ?>

                <!-- ── PILLAR 1: SERVICES & EVENTS ── -->
                <div class="nav-section-label">Services &amp; Events</div>
                <a class="nav-item <?php echo in_array($active_section, ['events', 'overview']) ? 'active' : ''; ?>" data-section="events"
                    data-tooltip="Calendar" href="dashboard/events">
                    <i class="fa-solid fa-calendar-days"></i> <span>Calendar</span>
                </a>

                <?php if ($can_approve_events): ?>
                    <a class="nav-item <?php echo ($active_section === 'event_requests') ? 'active' : ''; ?>"
                        data-section="event_requests" data-tooltip="Event Requests" href="dashboard/event_requests">
                        <i class="fa-solid fa-inbox"></i> <span>Event Requests</span>
                        <?php if ($pending_count > 0): ?>
                            <span id="badge-events" class="nav-badge">
                                <?php echo $pending_count; ?>
                            </span>
                        <?php endif; ?>
                    </a>
                <?php endif; ?>

                <?php if ($can_request_event && !in_array($role, ['Priest', 'Pastor'])): ?>
                    <a class="nav-item <?php echo ($active_section === 'request_event') ? 'active' : ''; ?>"
                        data-section="request_event" data-tooltip="Request Service" href="dashboard/request_event">
                        <i class="fa-solid fa-paper-plane"></i> <span>Request Service</span>
                    </a>
                <?php endif; ?>

                <?php if (in_array($role, ['Admin', 'Cathedral Admin'])): ?>
                    <a class="nav-item <?php echo in_array($active_section, ['priest_schedule', 'priest_prefs', 'pastor_schedule', 'pastor_prefs']) ? 'active' : ''; ?>"
                        data-section="priest_schedule" data-tooltip="Priest Duty" href="dashboard/priest_schedule">
                        <i class="fa-solid fa-clipboard-user"></i> <span>Priest Duty</span>
                    </a>
                <?php endif; ?>

                <!-- ── PILLAR 2: CHURCH RECORDS ── -->
                <?php 
                $hasCathAccess = ($can_cathedral_sched || $can_cathedral_rec);
                $hasChapelAccess = ($can_chapel_sched || $can_chapel_rec);
                ?>
                <div class="nav-section-label">Church Records</div>
                
                <?php if ($hasCathAccess && $hasChapelAccess): ?>
                    <?php 
                    $isSacramentActive = in_array($active_section, ['schedule_cathedral', 'records_cathedral', 'schedule_chapel', 'records_chapel', 'cathedral', 'chapel']);
                    ?>
                    <div class="nav-dropdown <?php echo $isSacramentActive ? 'open has-active-child' : ''; ?>" data-parent-title="Sacraments" data-parent-icon="fa-solid fa-book-bookmark">
                        <button type="button" class="nav-item dropdown-toggle" data-tooltip="Records" onclick="toggleDropdown(this)">
                            <i class="fa-solid fa-book-bookmark"></i>
                            <span>Records</span>
                            <span class="active-child-pip" title="Active sub-topic inside"></span>
                            <i class="fa-solid fa-chevron-down"></i>
                        </button>
                        <div class="dropdown-content">
                            <div class="sub-topic-header-pill">
                                <i class="fa-solid fa-diagram-project"></i> <span>Sub-topics</span>
                            </div>
                            <a class="nav-item sub-item <?php echo in_array($active_section, ['records_cathedral', 'schedule_cathedral', 'cathedral']) ? 'active' : ''; ?>"
                                data-section="records_cathedral" data-tooltip="Cathedral Records" href="dashboard/records_cathedral"
                                data-subtitle="Cathedral records">
                                <i class="fa-solid fa-place-of-worship"></i> <span>Cathedral Records</span>
                                <span class="sub-active-dot" title="Active"></span>
                            </a>
                            <a class="nav-item sub-item <?php echo in_array($active_section, ['records_chapel', 'schedule_chapel', 'chapel']) ? 'active' : ''; ?>"
                                data-section="records_chapel" data-tooltip="Chapel Records" href="dashboard/records_chapel"
                                data-subtitle="Chapel records">
                                <i class="fa-solid fa-church"></i> <span>Chapel Records</span>
                                <span class="sub-active-dot" title="Active"></span>
                            </a>
                        </div>
                    </div>
                <?php elseif ($hasCathAccess): ?>
                    <a class="nav-item <?php echo in_array($active_section, ['records_cathedral', 'schedule_cathedral', 'cathedral']) ? 'active' : ''; ?>"
                        data-section="records_cathedral" data-tooltip="Cathedral Records" href="dashboard/records_cathedral">
                        <i class="fa-solid fa-book-bookmark"></i> <span>Cathedral Records</span>
                    </a>
                <?php elseif ($hasChapelAccess): ?>
                    <?php 
                    $chapelFullTitle = $assigned_chapel_name ? htmlspecialchars($assigned_chapel_name) . ' Records' : 'Chapel Records';
                    $chapelShortTitle = 'Chapel Records';
                    if (!empty($assigned_chapel_name)) {
                        $cleanCName = trim(preg_replace('/\bChapel\b/i', '', $assigned_chapel_name));
                        // If still long, shorten "San Miguel Arcangel" to "San Miguel" for navigation rail
                        if (mb_strlen($cleanCName) > 22) {
                            $cleanCName = trim(preg_replace('/Arcangel\s*/i', '', $cleanCName));
                        }
                        $chapelShortTitle = htmlspecialchars($cleanCName) . ' Records';
                    }
                    ?>
                    <a class="nav-item <?php echo in_array($active_section, ['records_chapel', 'schedule_chapel', 'chapel']) ? 'active' : ''; ?>"
                        data-section="records_chapel" data-tooltip="<?php echo $chapelFullTitle; ?>" title="<?php echo $chapelFullTitle; ?>" href="dashboard/records_chapel">
                        <i class="fa-solid fa-book-bookmark"></i> <span><?php echo $chapelShortTitle; ?></span>
                    </a>
                <?php endif; ?>

                <a class="nav-item <?php echo ($active_section === 'request_certificate') ? 'active' : ''; ?>"
                    data-section="request_certificate" data-tooltip="Certificates" href="dashboard/request_certificate">
                    <i class="fa-solid fa-file-invoice"></i> <span>Certificates</span>
                </a>

                <!-- ── PILLAR 3: COMMUNITY ── -->
                <div class="nav-section-label">Community</div>
                <a class="nav-item <?php echo ($active_section === 'bulletin') ? 'active' : ''; ?>"
                    data-section="bulletin" data-tooltip="Bulletin" href="dashboard/bulletin">
                    <i class="fa-solid fa-bullhorn"></i> <span>Bulletin</span>
                </a>

                <?php if ($can_manage_inquiries): ?>
                    <a class="nav-item <?php echo ($active_section === 'public_inquiries') ? 'active' : ''; ?>"
                        data-section="public_inquiries" data-tooltip="Messages" href="dashboard/public_inquiries">
                        <i class="fa-solid fa-inbox"></i> <span>Messages</span>
                        <?php if (!empty($pending_inquiries)): ?>
                            <span class="nav-badge badge-inquiries" id="badge-sidebar-inquiries"><?php echo (int)$pending_inquiries; ?></span>
                        <?php endif; ?>
                    </a>
                <?php endif; ?>

                <?php if ($can_donations): ?>
                    <?php 
                    $isStewardshipActive = in_array($active_section, ['donations', 'merchandise']);
                    $stewardshipTitle = 'Giving & Store';
                    $stewardshipIcon = 'fa-solid fa-coins';
                    ?>
                    <div class="nav-dropdown <?php echo $isStewardshipActive ? 'open has-active-child' : ''; ?>" data-parent-title="<?php echo htmlspecialchars($stewardshipTitle); ?>" data-parent-icon="<?php echo $stewardshipIcon; ?>">
                        <button type="button" class="nav-item dropdown-toggle" data-tooltip="<?php echo htmlspecialchars($stewardshipTitle); ?>" onclick="toggleDropdown(this)">
                            <i class="<?php echo $stewardshipIcon; ?>"></i>
                            <span><?php echo htmlspecialchars($stewardshipTitle); ?></span>
                            <span class="active-child-pip" title="Active sub-topic inside"></span>
                            <i class="fa-solid fa-chevron-down"></i>
                        </button>
                        <div class="dropdown-content">
                            <div class="sub-topic-header-pill">
                                <i class="fa-solid fa-diagram-project"></i> <span>Sub-topics</span>
                            </div>
                            <a class="nav-item sub-item <?php echo ($active_section === 'donations') ? 'active' : ''; ?>"
                                data-section="donations" data-tooltip="Donations" href="dashboard/donations"
                                data-subtitle="Donations & tithes">
                                <i class="fa-solid fa-hand-holding-heart"></i> <span>Donations</span>
                                <span class="sub-active-dot" title="Active"></span>
                            </a>
                            <a class="nav-item sub-item <?php echo ($active_section === 'merchandise') ? 'active' : ''; ?>"
                                data-section="merchandise" data-tooltip="Parish Store" href="dashboard/merchandise"
                                data-subtitle="Books, shirts & items">
                                <i class="fa-solid fa-store"></i> <span>Parish Store</span>
                                <span class="sub-active-dot" title="Active"></span>
                            </a>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- ── PILLAR 4: ADMINISTRATION & GOVERNANCE ── -->
                <?php if ($can_manage_users || $can_manage_chapels || $can_parish_info || $can_manage_services || !empty($can_reports) || !empty($can_finance_reports) || in_array($role, ['Admin', 'Cathedral Admin'])): ?>
                    <div class="nav-section-label">Administration</div>

                    <?php if ($can_manage_users || $can_manage_chapels || $can_parish_info || $can_manage_services || !empty($can_reports) || !empty($can_finance_reports) || in_array($role, ['Admin', 'Cathedral Admin'])): ?>
                        <a class="nav-item <?php echo in_array($active_section, ['settings', 'users', 'chapels', 'services', 'parish_info', 'backups', 'reports', 'insights']) ? 'active' : ''; ?>"
                            data-section="settings" data-tooltip="Settings" href="dashboard/settings">
                            <i class="fa-solid fa-gears"></i> <span>Settings</span>
                            <?php if (!empty($pending_count)): ?>
                                <span class="nav-badge"><?php echo (int)$pending_count; ?></span>
                            <?php endif; ?>
                        </a>
                    <?php endif; ?>

                <?php endif; ?>

            <?php endif; ?>
        </nav>
        <div class="sidebar-footer">
            <?php if ($isGuest): ?>
                <a href="logout.php" class="logout-link" data-tooltip="Sign In / Register" title="Exit Guest View and Sign In or Register" style="color: var(--accent); display: flex; align-items: center; gap: 12px;">
                    <i class="fa-solid fa-arrow-right-to-bracket"></i> <span>Sign In / Register</span>
                </a>
            <?php else: ?>
                <a href="javascript:void(0)" onclick="openModal('modal-logout')" class="logout-link" data-tooltip="Logout">
                    <i class="fa-solid fa-right-from-bracket"></i> <span>Logout</span>
                </a>
            <?php endif; ?>
        </div>
    </aside>

    <!-- MAIN CONTENT -->
    <div class="main">
        <div class="topbar">
            <div class="topbar-left">
                <button class="hamburger" onclick="toggleSidebar()"><i class="fa-solid fa-bars"></i></button>
                <button type="button" class="topbar-quick-jump" onclick="openCommandPalette()" title="Quick Jump (Ctrl + K)">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <span>Quick Jump...</span>
                    <kbd>Ctrl K</kbd>
                </button>
            </div>
            <div class="topbar-right">
                <!-- Inbox Notifications with Facebook-style Dropdown -->
                <?php if ($can_manage_inquiries || $isMember): ?>
                    <div class="topbar-inbox-wrapper" id="topbarInboxWrapper">
                        <button type="button" 
                                id="topbarInboxBtn" 
                                class="topbar-btn topbar-inbox-btn <?php echo ($active_section === 'public_inquiries') ? 'active' : ''; ?>" 
                                title="<?php echo $isMember ? 'Inbox' : 'Inquiries & Messages'; ?>" 
                                aria-label="Inbox"
                                aria-expanded="false"
                                aria-haspopup="true"
                                onclick="if(typeof toggleInboxDropdown==='function'){ toggleInboxDropdown(event); } else { window.location.href='dashboard/public_inquiries'; }">
                            <i class="fa-solid fa-inbox"></i>
                            <span id="badge-inquiries" class="topbar-badge badge-inquiries" style="<?php echo ($pending_inquiries <= 0) ? 'display:none;' : ''; ?>">
                                <?php echo $pending_inquiries; ?>
                            </span>
                        </button>

                        <!-- Facebook-style Floating Inbox Dropdown -->
                        <div class="inbox-dropdown-menu" id="inboxDropdownMenu" role="dialog" aria-label="Inbox preview" style="display: none;">
                            <div class="inbox-dd-header">
                                <div class="inbox-dd-title-row">
                                    <span class="inbox-dd-title">
                                        <i class="fa-solid fa-bell" style="color: var(--accent); font-size: 0.95rem;"></i>
                                        <?php echo $isMember ? 'Notifications' : 'Inbox'; ?>
                                    </span>
                                    <span class="inbox-dd-count-pill" id="inboxDdCountPill" style="<?php echo ($pending_inquiries <= 0) ? 'display:none;' : ''; ?>">
                                        <?php echo $pending_inquiries; ?> <?php echo $isMember ? 'new' : 'pending'; ?>
                                    </span>
                                </div>
                                <div class="inbox-dd-actions">
                                    <button type="button" class="inbox-dd-readall-btn" id="inboxDdReadAllBtn" title="Mark all as read" onclick="markAllInboxRead(event)">
                                        <i class="fa-solid fa-check-double"></i>
                                        <span>Read all</span>
                                    </button>
                                    <button type="button" class="inbox-dd-icon-btn" id="inboxDdRefreshBtn" title="Refresh messages" onclick="refreshInboxDropdown(event)">
                                        <i class="fa-solid fa-rotate-right"></i>
                                    </button>
                                    <a href="dashboard/public_inquiries" 
                                       class="inbox-dd-icon-btn" 
                                       title="Open full page inbox"
                                       onclick="handleInboxFullView(event)">
                                        <i class="fa-solid fa-arrow-up-right-from-square"></i>
                                    </a>
                                </div>
                            </div>

                            <div class="inbox-dd-tabs">
                                <button type="button" class="inbox-dd-tab active" data-tab="all" onclick="switchInboxDdTab('all', this)">All</button>
                                <button type="button" class="inbox-dd-tab" data-tab="pending" onclick="switchInboxDdTab('pending', this)">Unread</button>
                            </div>

                            <div class="inbox-dd-body" id="inboxDdList">
                                <!-- Dynamic items loaded via JS / Skeleton -->
                                <div class="inbox-dd-skeleton">
                                    <div class="inbox-skel-item">
                                        <div class="inbox-skel-avatar"></div>
                                        <div class="inbox-skel-lines">
                                            <div class="inbox-skel-line w-75"></div>
                                            <div class="inbox-skel-line w-50"></div>
                                            <div class="inbox-skel-line w-90"></div>
                                        </div>
                                    </div>
                                    <div class="inbox-skel-item">
                                        <div class="inbox-skel-avatar"></div>
                                        <div class="inbox-skel-lines">
                                            <div class="inbox-skel-line w-60"></div>
                                            <div class="inbox-skel-line w-40"></div>
                                            <div class="inbox-skel-line w-80"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="inbox-dd-footer">
                                <a href="dashboard/public_inquiries" 
                                   class="inbox-dd-see-all" 
                                   onclick="handleInboxFullView(event)">
                                    <span><?php echo $isMember ? 'View Notifications' : 'See all in Inbox'; ?></span>
                                    <i class="fa-solid fa-arrow-right"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Parish Ministry Chat Launcher (Staff & Clergy) -->
                <?php if (in_array($role ?? '', ['Admin', 'Cathedral Admin', 'Chapel Admin', 'Priest', 'Pastor', 'Staff'], true)): ?>
                    <button type="button" 
                            id="topbarChatBtn" 
                            class="topbar-btn topbar-chat-btn" 
                            title="Parish Ministry Chat" 
                            aria-label="Parish Ministry Chat"
                            onclick="if(typeof ParishChat !== 'undefined' && ParishChat.toggleChat){ ParishChat.toggleChat(); } else if(window.toggleParishChat){ window.toggleParishChat(); }">
                        <i class="fa-solid fa-comments"></i>
                        <span id="topbarChatBadge" class="topbar-badge" style="display:none;">0</span>
                    </button>
                <?php endif; ?>

                <button id="darkModeBtn" class="topbar-btn" title="Toggle Theme">
                    <i id="darkModeIcon" class="fa-regular fa-moon"></i>
                </button>
                <div class="topbar-clock-chip">
                    <i class="fa-regular fa-clock"></i>
                    <span id="currentTime"></span>
                </div>
            </div>
        </div>

        <div class="content">
            <?php if ($isGuest): ?>
                <div class="guest-banner" style="background: linear-gradient(135deg, rgba(201,168,76,0.12), rgba(201,168,76,0.04)); border: 1px solid rgba(201,168,76,0.3); border-radius: 12px; padding: 10px 18px; margin: 14px clamp(20px, 2.2vw, 32px) 0; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; flex-shrink: 0;">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <div style="width: 26px; height: 26px; border-radius: 50%; background: rgba(201,168,76,0.2); display: flex; align-items: center; justify-content: center; color: var(--accent); font-size: 12px; flex-shrink: 0;">
                            <i class="fa-solid fa-eye"></i>
                        </div>
                        <div>
                            <div style="font-weight: 700; font-size: 12.5px; color: var(--text);">Guest Preview Mode</div>
                            <div style="font-size: 11px; color: var(--text2);">You are previewing public schedules, chapel services, and bulletin notices. To access personal records, booking requests, or parish history, create an account.</div>
                        </div>
                    </div>
                    <div style="display: flex; gap: 8px; align-items: center;">
                        <a href="register.php" class="btn btn-primary btn-sm" style="font-size: 11px; padding: 5px 12px; text-decoration: none; border-radius: 6px;">
                            <i class="fa-solid fa-user-plus"></i> Register as Member
                        </a>
                        <a href="logout.php" class="btn btn-ghost btn-sm" style="font-size: 11px; padding: 5px 12px; text-decoration: none; border: 1px solid var(--border); border-radius: 6px;">
                            <i class="fa-solid fa-arrow-right-to-bracket"></i> Sign In
                        </a>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($flash): ?>
                <?php
                $flash_text = (string) $flash;
                if (strpos($flash_text, 'Notifications sent (Email:') !== false) {
                    $flash_text = str_replace(['✅', '❌'], ['sent', 'not sent'], $flash_text);
                    $flash_text = str_replace('Notifications sent', 'Notifications checked', $flash_text);
                }
                // Allow safe HTML spans for status coloring (e.g. notification summaries)
                $flash_safe = strip_tags($flash_text, '<span><strong><em><i>');
                ?>
                <div class="flash flash-<?php echo ($flash_type === 'error') ? 'error' : 'success'; ?>" onclick="this.remove()" title="Click to dismiss">
                    <i class="fa-solid <?php echo ($flash_type === 'error') ? 'fa-circle-exclamation' : 'fa-circle-check'; ?>"></i>
                    <span style="flex: 1;"><?php echo $flash_safe; ?></span>
                    <i class="fa-solid fa-xmark" style="margin-left: 8px; opacity: 0.65; font-size: 12px;"></i>
                </div>
                <script>
                    setTimeout(() => {
                        const f = document.querySelector('.flash');
                        if (f) {
                            f.style.opacity = '0';
                            f.style.transform = 'translateY(-10px) scale(0.96)';
                            setTimeout(() => f.remove(), 350);
                        }
                    }, 4500);
                </script>
            <?php endif; ?>

            <!-- ── MODULAR INCLUDES ─────────────────────────────────────────── -->
            <div id="dynamic-content-area" style="transition: opacity 0.3s ease; display: flex; flex-direction: column; flex: 1; min-height: 0; width: 100%;">
                <?php
                // Initial load rendered natively
                include BASE_PATH . '/views/partials/dashboard/ajax_renderer.php';
                ?>
            </div>
        </div>
    </div>

    <!-- ── PARISH MINISTRY CHAT WIDGET ── -->
    <?php
    $internalChatRoles = ['Admin', 'Cathedral Admin', 'Chapel Admin', 'Priest', 'Pastor', 'Staff'];
    if (in_array($role ?? '', $internalChatRoles, true)) {
        include BASE_PATH . '/views/partials/chat/chat_widget.php';
    }
    ?>

    <!-- ── UNIVERSAL COMMAND PALETTE (CTRL + K) ── -->
    <div class="modal-overlay cmd-palette-overlay" id="modal-command-palette" role="dialog" aria-label="Command Palette">
        <div class="cmd-palette-box">
            <div class="cmd-palette-header">
                <i class="fa-solid fa-magnifying-glass cmd-palette-icon"></i>
                <input type="text" id="cmd-palette-search" placeholder="Type a section, module, or action..." autocomplete="off" oninput="filterCommandPalette(this.value)">
                <kbd class="cmd-esc-badge" onclick="closeCommandPalette()">ESC</kbd>
            </div>
            <div class="cmd-palette-body" id="cmd-palette-results">
                <!-- Group 1: Navigation -->
                <div class="cmd-group-title">Navigation &amp; Registry</div>
                <a href="dashboard/events" class="cmd-item" data-title="Calendar Liturgical Events">
                    <div class="cmd-item-icon"><i class="fa-solid fa-calendar-days"></i></div>
                    <div class="cmd-item-info">
                        <div class="cmd-item-title">Liturgical Calendar</div>
                        <div class="cmd-item-sub">Parish schedules, mass intentions &amp; feast days</div>
                    </div>
                    <span class="cmd-item-enter"><i class="fa-solid fa-arrow-turn-down"></i></span>
                </a>
                <?php if (!empty($can_request_event) || !empty($isGuest)): ?>
                <a href="dashboard/request_event" class="cmd-item" data-title="Request Service Mass Intention Baptism Wedding Blessing Funeral">
                    <div class="cmd-item-icon"><i class="fa-solid fa-paper-plane"></i></div>
                    <div class="cmd-item-info">
                        <div class="cmd-item-title">Request Service</div>
                        <div class="cmd-item-sub">Submit &amp; track Mass, Baptism, Wedding, and Blessing requests</div>
                    </div>
                    <span class="cmd-item-enter"><i class="fa-solid fa-arrow-turn-down"></i></span>
                </a>
                <?php endif; ?>
                <?php if (!empty($can_cathedral_rec)): ?>
                <a href="dashboard/records_cathedral" class="cmd-item" data-title="Cathedral Sacramental Records Baptism Confirmation Marriage Funeral">
                    <div class="cmd-item-icon"><i class="fa-solid fa-book-bookmark"></i></div>
                    <div class="cmd-item-info">
                        <div class="cmd-item-title">Cathedral Registry</div>
                        <div class="cmd-item-sub">Baptism, Confirmation, Marriage &amp; Funeral records</div>
                    </div>
                    <span class="cmd-item-enter"><i class="fa-solid fa-arrow-turn-down"></i></span>
                </a>
                <?php endif; ?>
                <?php if (!empty($can_chapel_rec)): ?>
                <a href="dashboard/records_chapel" class="cmd-item" data-title="Chapel Sacramental Records">
                    <div class="cmd-item-icon"><i class="fa-solid fa-church"></i></div>
                    <div class="cmd-item-info">
                        <div class="cmd-item-title">Chapel Records</div>
                        <div class="cmd-item-sub">Sub-parish chapel books and sacramental entries</div>
                    </div>
                    <span class="cmd-item-enter"><i class="fa-solid fa-arrow-turn-down"></i></span>
                </a>
                <?php endif; ?>
                <a href="dashboard/request_certificate" class="cmd-item" data-title="Certificates Issuance">
                    <div class="cmd-item-icon"><i class="fa-solid fa-file-invoice"></i></div>
                    <div class="cmd-item-info">
                        <div class="cmd-item-title">Certificates</div>
                        <div class="cmd-item-sub">Issue and verify sacramental certificates</div>
                    </div>
                    <span class="cmd-item-enter"><i class="fa-solid fa-arrow-turn-down"></i></span>
                </a>
                <?php if (!empty($can_donations)): ?>
                <a href="dashboard/donations" class="cmd-item" data-title="Donations Stewardship Offerings Tithes">
                    <div class="cmd-item-icon"><i class="fa-solid fa-hand-holding-heart"></i></div>
                    <div class="cmd-item-info">
                        <div class="cmd-item-title">Donations &amp; Offerings</div>
                        <div class="cmd-item-sub">Financial stewardship, tithes &amp; mass intention ledger</div>
                    </div>
                    <span class="cmd-item-enter"><i class="fa-solid fa-arrow-turn-down"></i></span>
                </a>
                <?php endif; ?>
                <?php if (!empty($can_donations) || !empty($isMember)): ?>
                <a href="dashboard/merchandise" class="cmd-item" data-title="Merchandise POS Store Inventory Sales">
                    <div class="cmd-item-icon"><i class="fa-solid fa-store"></i></div>
                    <div class="cmd-item-info">
                        <div class="cmd-item-title">Merchandise &amp; Shop</div>
                        <div class="cmd-item-sub">Catalog, inventory &amp; sales records</div>
                    </div>
                    <span class="cmd-item-enter"><i class="fa-solid fa-arrow-turn-down"></i></span>
                </a>
                <?php endif; ?>
                <?php if (!empty($can_manage_inquiries) || !empty($isMember)): ?>
                <a href="dashboard/public_inquiries" class="cmd-item" data-title="Inquiries Messages Notifications Requests">
                    <div class="cmd-item-icon"><i class="fa-solid fa-inbox"></i></div>
                    <div class="cmd-item-info">
                        <div class="cmd-item-title">Public Inquiries &amp; Inbox</div>
                        <div class="cmd-item-sub">Member requests, messages and certificates</div>
                    </div>
                    <span class="cmd-item-enter"><i class="fa-solid fa-arrow-turn-down"></i></span>
                </a>
                <?php endif; ?>
                <?php if (!empty($can_manage_users) || !empty($can_manage_chapels) || !empty($can_manage_services) || !empty($can_parish_info) || !empty($can_reports)): ?>
                <a href="dashboard/settings" class="cmd-item" data-title="Settings Configuration Users Chapels Reports Backups">
                    <div class="cmd-item-icon"><i class="fa-solid fa-gears"></i></div>
                    <div class="cmd-item-info">
                        <div class="cmd-item-title">Settings &amp; Administration</div>
                        <div class="cmd-item-sub">Users, chapels, financial reports &amp; database backups</div>
                    </div>
                    <span class="cmd-item-enter"><i class="fa-solid fa-arrow-turn-down"></i></span>
                </a>
                <?php endif; ?>

                <!-- Group 2: Fast Shortcuts -->
                <div class="cmd-group-title">Fast Actions</div>
                <?php if (in_array($role ?? '', ['Admin', 'Cathedral Admin', 'Chapel Admin', 'Priest', 'Pastor', 'Staff'], true)): ?>
                <a href="javascript:void(0)" onclick="closeCommandPalette(); if(typeof ParishChat !== 'undefined'){ ParishChat.toggleChat(true); } else if(window.toggleParishChat){ window.toggleParishChat(); }" class="cmd-item" data-title="Ministry Chat Clergy Staff Direct Messages Channels Coordination">
                    <div class="cmd-item-icon" style="color: var(--accent);"><i class="fa-solid fa-comments"></i></div>
                    <div class="cmd-item-info">
                        <div class="cmd-item-title">Open Ministry Chat</div>
                        <div class="cmd-item-sub">Intra-parish chat rooms &amp; direct messages for clergy and staff</div>
                    </div>
                    <span class="cmd-item-enter"><i class="fa-solid fa-arrow-turn-down"></i></span>
                </a>
                <?php endif; ?>
                <?php if (!empty($can_donations)): ?>
                <a href="dashboard.php?section=merchandise&amp;tab=pos" class="cmd-item" data-title="Fast POS Cashier Terminal Point of Sale">
                    <div class="cmd-item-icon" style="color: var(--accent);"><i class="fa-solid fa-cash-register"></i></div>
                    <div class="cmd-item-info">
                        <div class="cmd-item-title">Open POS Cashier Terminal</div>
                        <div class="cmd-item-sub">Instant counter sale, quick picks &amp; change calculator</div>
                    </div>
                    <span class="cmd-item-enter"><i class="fa-solid fa-arrow-turn-down"></i></span>
                </a>
                <a href="dashboard.php?section=donations&amp;tab=new" class="cmd-item" data-title="Record New Offering Donation Tithe">
                    <div class="cmd-item-icon" style="color: var(--accent);"><i class="fa-solid fa-plus-circle"></i></div>
                    <div class="cmd-item-info">
                        <div class="cmd-item-title">Record New Offering</div>
                        <div class="cmd-item-sub">Add a collection, mass intention, or donation</div>
                    </div>
                    <span class="cmd-item-enter"><i class="fa-solid fa-arrow-turn-down"></i></span>
                </a>
                <?php endif; ?>
                <a href="dashboard.php?section=events&amp;sched_mode=table" class="cmd-item" data-title="Table View Events Schedule List">
                    <div class="cmd-item-icon"><i class="fa-solid fa-table-list"></i></div>
                    <div class="cmd-item-info">
                        <div class="cmd-item-title">Events Table View</div>
                        <div class="cmd-item-sub">Quickly filter, sort, and search all liturgies</div>
                    </div>
                    <span class="cmd-item-enter"><i class="fa-solid fa-arrow-turn-down"></i></span>
                </a>
                <?php if (!empty($can_reports) || !empty($can_finance_reports)): ?>
                <a href="dashboard.php?section=settings&amp;tab=reports" class="cmd-item" data-title="Parish Reports Financial Statement Summary">
                    <div class="cmd-item-icon"><i class="fa-solid fa-chart-pie"></i></div>
                    <div class="cmd-item-info">
                        <div class="cmd-item-title">Financial Reports</div>
                        <div class="cmd-item-sub">Generate statement of accounts &amp; CSV exports</div>
                    </div>
                    <span class="cmd-item-enter"><i class="fa-solid fa-arrow-turn-down"></i></span>
                </a>
                <?php endif; ?>
            </div>
            <div class="cmd-palette-footer">
                <div class="cmd-footer-hint"><kbd>↑</kbd> <kbd>↓</kbd> Navigate</div>
                <div class="cmd-footer-hint"><kbd>↵</kbd> Select</div>
                <div class="cmd-footer-hint"><kbd>ESC</kbd> Close</div>
            </div>
        </div>
    </div>

    <!-- ── SHARED MODALS ── -->
    <?php
    include BASE_PATH . '/views/modals/dashboard_modals.php';
    ?>

    <!-- ── CIRCULAR DARK-MODE TOGGLE (BOTTOM RIGHT) ── -->
    <button type="button" id="floatingThemeToggleBtn" class="floating-theme-toggle" title="Switch Theme (Light / Dark)" aria-label="Toggle Theme">
        <i class="fa-solid fa-moon"></i>
    </button>

    <!-- ── MODULAR SCRIPTS ── -->
    <script src="assets/js/dashboard-core.js?v=<?= time() ?>"></script>
    <script src="assets/js/search-autocomplete.js?v=<?= time() ?>"></script>
    <script src="assets/js/ph-geo-data.js?v=<?= time() ?>"></script>
    <script src="assets/js/dashboard-nav.js?v=<?= time() ?>"></script>
    <script src="assets/js/dashboard-availability.js?v=<?= time() ?>"></script>
    <script src="assets/js/dashboard-modals.js?v=<?= time() ?>"></script>
    <script src="assets/js/dashboard-calendar.js?v=<?= time() ?>"></script>
    <script src="assets/js/dashboard-profile.js?v=<?= time() ?>"></script>
    <script src="assets/js/notifications.js?v=<?= time() ?>"></script>
    <script src="assets/js/inbox-dropdown.js?v=<?= time() ?>"></script>
    <?php if (in_array($role ?? '', $internalChatRoles, true)): ?>
    <script src="assets/js/parish-chat.js?v=<?= time() ?>"></script>
    <?php endif; ?>
    <script>
        const currentSec = '<?php echo $active_section; ?>';
        const currentUserRole = '<?php echo $role; ?>';
        const currentUserChapel = <?php echo json_encode($assigned_chapel_id); ?>;
        // Calendar events will be loaded dynamically within their partials

        document.addEventListener('DOMContentLoaded', () => {
            // Calendars and other JS elements initialize from their own section modules.
            <?php if ($isGuest && !empty($_GET['auth_prompt'])): ?>
            setTimeout(() => {
                if (typeof openGuestAuthModal === 'function') {
                    openGuestAuthModal('submit requests or access member services');
                }
            }, 100);
            <?php endif; ?>
        });
    </script>
</body>

</html>