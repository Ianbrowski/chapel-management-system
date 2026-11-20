<?php
try {
    require_once __DIR__ . '/../../config/config.php';

    if (isset($_SESSION['user_id']) && ($_SESSION['role'] ?? '') !== 'Guest') {
        header("Location: dashboard.php");
        exit();
    }
    require_once __DIR__ . '/../../app/controllers/HomeController.php';
    require_once __DIR__ . '/../../app/controllers/BaseController.php';

    $baseController = new BaseController($conn, false);
    $csrf = $baseController->generateCsrf();

    $controller = new HomeController($conn);
    $homeViewData = $controller->loadViewData();
    $bulletin_items = $homeViewData['bulletin_items'] ?? [];
    $contact_info = $homeViewData['contact_info'] ?? [];
    $mass_schedules = $homeViewData['mass_schedules'] ?? [];
    $history_intro = $homeViewData['history_intro'] ?? '';
    $history_items = $homeViewData['history_items'] ?? [];
    $leadership_intro = $homeViewData['leadership_intro'] ?? '';
    $leadership = $homeViewData['leadership'] ?? [];
    $custom_merch = $homeViewData['custom_merch'] ?? [];
    $services_catalog = $homeViewData['services_catalog'] ?? [];
    $certificate_types = $homeViewData['certificate_types'] ?? [];
    $chapels = $homeViewData['chapels'] ?? [];

    $reserveMessage = $_SESSION['res_msg'] ?? null;
    $reserveMessageType = $_SESSION['res_type'] ?? 'success';

    // Check for common URL error codes
    if (isset($_GET['error'])) {
        $err = $_GET['error'];
        $reserveMessageType = 'error';
        if ($err === 'session') $reserveMessage = "Your session has expired for security. Please log in again.";
        else if ($err === 'wrong_pass') $reserveMessage = "Invalid username or password. Please try again.";
        else if ($err === 'csrf') $reserveMessage = "Security validation failed. Please refresh and try again.";
        else if ($err === 'locked') {
           $wait = $_GET['wait'] ?? '15';
           $reserveMessage = "Too many failed attempts. Your account is locked for $wait minutes.";
        }
    }
    
    if (isset($_GET['success']) && $_GET['success'] === 'logout') {
        $reserveMessageType = 'success';
        $reserveMessage = "You have been safely logged out. God bless you.";
    }

    unset($_SESSION['res_msg'], $_SESSION['res_type']);

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && ($_POST['action'] ?? '') === 'reserve_merch') {
        require_once __DIR__ . '/../../app/controllers/PortalController.php';
        $portal = new PortalController($conn);
        $res = $portal->apiSubmitRequest();
        
        $_SESSION['res_msg'] = $res['message'];
        $_SESSION['res_type'] = $res['type'];
        
        // Prevents double submission on refresh
        header("Location: index.php");
        exit();
    }

    require_once __DIR__ . '/../../app/controllers/BaseController.php';
    $base = new BaseController($conn, false);
    $csrf = $base->generateCsrf();
} catch (Throwable $e) {
    require_once __DIR__ . '/../../app/helpers/error_debug.php';
    cms_store_exception($e);
    $dest = (strpos($_SERVER['PHP_SELF'], 'views/pages/') !== false) ? 'error_500.php' : 'views/pages/error_500.php';
    header("Location: $dest");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iglesia Filipina Independiente | Cathedral of St. Michael the Archangel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@500;600;700;800&family=Fira+Code:wght@400;500;600&family=Inter:wght@300;400;500;600;700;800&family=Outfit:wght@400;500;600;700;800&family=Playfair+Display:ital,wght@0,600;0,700;1,600&display=swap" rel="stylesheet">
    <link rel="stylesheet" type="text/css" href="assets/css/fonts.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" type="text/css" href="assets/css/theme.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" type="text/css" href="assets/css/index.css?v=<?php echo time(); ?>">
    <link rel="icon" type="image/x-icon" href="assets/img/2323.png">
    <script>
      (function () {
        const savedTheme = localStorage.getItem('theme') || localStorage.getItem('portalTheme') || 'light';
        document.documentElement.setAttribute('data-theme', savedTheme);

        // Immediate discreet URL cleaning: strip ?success=logout or ?error= from address bar
        if (window.history && window.history.replaceState && window.location.search) {
            try {
                const u = new URL(window.location.href);
                if (u.searchParams.has('success') || u.searchParams.has('error')) {
                    u.searchParams.delete('success');
                    u.searchParams.delete('error');
                    u.searchParams.delete('wait');
                    const rem = u.searchParams.toString();
                    const clean = rem ? (u.pathname + '?' + rem) : (u.pathname.replace(/\/index\.php$/, '/') || '/');
                    window.history.replaceState({}, document.title, clean);
                }
            } catch (e) {}
        }
      })();
    </script>
</head>
<body>

<?php if ($reserveMessage): ?>
<div id="reserveToast" class="scroll-reveal active" style="position:fixed; top:90px; right:24px; z-index:9999; background:<?php echo $reserveMessageType === 'success' ? '#10b981' : '#ef4444'; ?>; color:#ffffff; padding:16px 22px; border-radius:12px; box-shadow:0 10px 30px rgba(0,0,0,0.25); max-width:380px; border: 1px solid rgba(255,255,255,0.2);">
    <div style="display:flex; align-items:flex-start; gap:12px;">
        <i class="fa-solid <?php echo $reserveMessageType === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation'; ?>" style="font-size:20px; margin-top:2px;"></i>
        <div style="flex:1;">
            <h4 style="font-family:var(--font-family); font-weight:600; font-size:14px; margin-bottom:4px;"><?php 
                if ($reserveMessageType === 'success') echo 'Parish Notice';
                else echo 'Attention Required';
            ?></h4>
            <p style="font-size:13px; opacity:0.95; line-height:1.4; color:#ffffff;"><?php echo htmlspecialchars($reserveMessage); ?></p>
        </div>
        <button type="button" onclick="this.closest('#reserveToast').remove()" style="background:transparent; border:none; color:#ffffff; cursor:pointer; font-size:16px; opacity:0.8;"><i class="fa-solid fa-xmark"></i></button>
    </div>
</div>
<script>
// Discreet URL Cleaner: strip ?success=logout or ?error= query params immediately on page load
(function() {
    if (window.history && window.history.replaceState && window.location.search) {
        try {
            const url = new URL(window.location.href);
            if (url.searchParams.has('success') || url.searchParams.has('error')) {
                url.searchParams.delete('success');
                url.searchParams.delete('error');
                url.searchParams.delete('wait');
                const remaining = url.searchParams.toString();
                const clean = remaining ? (url.pathname + '?' + remaining) : (url.pathname.replace(/\/index\.php$/, '/') || '/');
                window.history.replaceState({}, document.title, clean);
            }
        } catch (e) {}
    }
})();
setTimeout(() => { 
    const toast = document.getElementById('reserveToast');
    if (toast) {
        toast.style.transition = 'opacity 0.35s ease, transform 0.35s ease';
        toast.style.opacity = '0';
        toast.style.transform = 'translateY(-10px)';
        setTimeout(() => toast.remove(), 350);
    }
}, 3000);
</script>
<?php endif; ?>

<!-- ── HEADER ── -->
<header class="header">
    <div class="logo" onclick="window.location.href='index.php'" style="display: flex; align-items: center; gap: 12px; cursor: pointer;">
        <img src="assets/img/2323.png" alt="Cathedral Seal" style="height: 42px; width: auto; object-fit: contain;">
        <div style="display: flex; flex-direction: column; text-align: left;">
            <h2 style="font-family: var(--font-family-sans); font-size: 0.98rem; font-weight: 800; margin: 0; line-height: 1.15; letter-spacing: -0.01em; color: var(--text-main);">
                <span style="display: block;">IGLESIA FILIPINA</span>
                <span style="display: block; letter-spacing: 0.055em;">INDEPENDIENTE</span>
            </h2>
            <span style="font-size: 11px; color: var(--gold); font-weight: 600; letter-spacing: 0.04em; margin-top: 2px;">Cathedral of St. Michael the Archangel</span>
        </div>
    </div>
    
    <nav id="mainNav">
        <a href="#liturgy" onclick="toggleMenu(false)">Mass Times</a>
        <a href="#services" onclick="toggleMenu(false)">Services</a>
        <a href="#bulletin" onclick="toggleMenu(false)">Announcements</a>
        <a href="#about" onclick="toggleMenu(false)">Our Story</a>
        <a href="#hub" onclick="toggleMenu(false)">Giving &amp; Map</a>
        <a href="#merchandise" onclick="toggleMenu(false)">Parish Store</a>
    </nav>

    <div class="header-actions">
        <button type="button" class="menu-toggle" onclick="toggleMenu()" aria-label="Toggle Menu">
            <i class="fa-solid fa-bars"></i>
        </button>
    </div>
</header>

<!-- ── HERO ── -->
<section class="hero" id="hero">
    <div class="hero-ambient-orb"></div>
    <div class="hero-bg-img"
         <?php if (!empty($contact_info['cathedral_image'])): ?>
             style="background-image: url('storage/<?php echo htmlspecialchars($contact_info['cathedral_image']); ?>')"
         <?php else: ?>
             style="background-image: url('assets/img/bgchapel.png')"
         <?php endif; ?>>
    </div>
    <div class="hero-overlay"></div>

    <div class="container hero-content-wrap">
        <div class="hero-content scroll-reveal">
            <!-- PRIMARY TITLE: IGLESIA FILIPINA INDEPENDIENTE (LONGER & WIDER) -->
            <h1 class="hero-church-brand-title">
                <span class="hero-church-name-gold">
                    <span class="hero-title-line hero-title-top" style="display: block;">IGLESIA FILIPINA</span>
                    <span class="hero-title-line hero-title-bottom" style="display: block;">INDEPENDIENTE</span>
                </span>
            </h1>

            <!-- CATHEDRAL SUBTITLE: DIGNIFIED & PROMINENT -->
            <div class="hero-cathedral-subtitle">
                <span class="hero-sub-flourish">✦</span>
                <span class="hero-cathedral-name">Cathedral of St. Michael the Archangel</span>
                <span class="hero-sub-flourish">✦</span>
            </div>

            <!-- HERO LOGIN CTA BUTTON (OPENS POPUP MODAL) -->
            <div class="hero-login-action-wrap">
                <button type="button" class="btn-hero-login" id="heroLoginTrigger" onclick="openLoginModal()" aria-haspopup="dialog" aria-controls="cathedralLoginModal">
                    <span class="btn-hero-login-sheen"></span>
                    <i class="fa-solid fa-arrow-right-to-bracket btn-hero-login-icon"></i>
                    <span class="btn-hero-login-label">Cathedral Portal Login</span>
                    <i class="fa-solid fa-arrow-right btn-hero-login-arrow"></i>
                </button>
            </div>
        </div>
    </div>
</section>



<!-- ── MASS TIMES & CHAPELS ── -->
<section id="liturgy" class="section church-section liturgy-clean-section section-bg-primary">
    <div class="container">
        <!-- Section Header -->
        <div class="church-section-head scroll-reveal">
            <h2 class="church-section-title">Mass Times &amp; Schedule</h2>
            <p class="church-section-sub">
                Weekly Mass schedules, prayers, confession times, and our local community chapels.
            </p>
        </div>

        <!-- Simplified Dual Column Grid -->
        <div class="liturgy-simple-grid scroll-reveal">
            <!-- COLUMN 1: CATHEDRAL SCHEDULE & DEVOTIONS -->
            <div class="liturgy-cathedral-card">
                <div class="liturgy-card-head">
                    <div class="liturgy-head-title-wrap">
                        <span class="liturgy-badge-pill"><i class="fa-solid fa-church"></i> Main Cathedral</span>
                        <h3 class="liturgy-head-title"><?php echo htmlspecialchars($contact_info['parish_name'] ?? 'IFI Cathedral'); ?></h3>
                        <p class="liturgy-head-sub"><i class="fa-solid fa-location-dot"></i> <?php echo htmlspecialchars($contact_info['address'] ?? '064 Prof, H.F. Rubio St, Bacoor, Cavite'); ?></p>
                    </div>
                    <?php if(!empty($contact_info['patron_saint'])): ?>
                    <span class="liturgy-patron-pill"><i class="fa-solid fa-cross"></i> Patron: <?php echo htmlspecialchars($contact_info['patron_saint']); ?></span>
                    <?php endif; ?>
                </div>

                <!-- Clean Mass Timetable -->
                <div class="liturgy-times-block">
                    <h4 class="liturgy-block-title"><i class="fa-solid fa-clock"></i> Cathedral Mass Schedule</h4>
                    <div class="liturgy-times-list">
                        <?php if(empty($mass_schedules)): ?>
                            <div class="liturgy-time-row sunday">
                                <span class="liturgy-day-label"><i class="fa-solid fa-sun"></i> Sunday Mass</span>
                                <div class="liturgy-chips">
                                    <span class="liturgy-chip">6:00 AM</span>
                                    <span class="liturgy-chip">8:00 AM</span>
                                    <span class="liturgy-chip">10:00 AM</span>
                                    <span class="liturgy-chip">5:00 PM</span>
                                </div>
                            </div>
                            <div class="liturgy-time-row">
                                <span class="liturgy-day-label"><i class="fa-solid fa-calendar-day"></i> Weekday Mass</span>
                                <div class="liturgy-chips">
                                    <span class="liturgy-chip">6:00 AM</span>
                                    <span class="liturgy-chip">6:00 PM</span>
                                </div>
                            </div>
                            <div class="liturgy-time-row">
                                <span class="liturgy-day-label"><i class="fa-solid fa-moon"></i> Saturday (Anticipated)</span>
                                <div class="liturgy-chips">
                                    <span class="liturgy-chip">6:00 AM</span>
                                    <span class="liturgy-chip">5:00 PM</span>
                                </div>
                            </div>
                        <?php else: ?>
                            <?php foreach($mass_schedules as $sched): 
                                $dayLower = strtolower($sched['day_type']);
                                $isSunday = strpos($dayLower, 'sun') !== false;
                                $times = array_map('trim', explode(',', $sched['schedule_time']));
                            ?>
                            <div class="liturgy-time-row <?php echo $isSunday ? 'sunday' : ''; ?>">
                                <span class="liturgy-day-label">
                                    <i class="<?php echo $isSunday ? 'fa-solid fa-sun' : 'fa-solid fa-calendar-day'; ?>"></i>
                                    <?php echo htmlspecialchars($sched['day_type']); ?>
                                </span>
                                <div class="liturgy-chips">
                                    <?php foreach($times as $t): ?>
                                        <span class="liturgy-chip"><?php echo htmlspecialchars($t); ?></span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Clean Devotions & Pastoral Services -->
                <div class="liturgy-devotions-strip">
                    <h4 class="liturgy-block-title"><i class="fa-solid fa-hands-praying"></i> Prayers &amp; Confession</h4>
                    <div class="liturgy-devotions-list">
                        <div class="liturgy-dev-item">
                            <i class="fa-solid fa-dove"></i>
                            <div>
                                <strong>Confession</strong> &bull; Saturdays 4:00 PM &ndash; 5:00 PM (or by appointment)
                            </div>
                        </div>
                        <div class="liturgy-dev-item">
                            <i class="fa-solid fa-cross"></i>
                            <div>
                                <strong>Our Lady of Perpetual Help</strong> &bull; Wednesdays 5:30 PM Novena before Mass
                            </div>
                        </div>
                        <div class="liturgy-dev-item">
                            <i class="fa-solid fa-fire"></i>
                            <div>
                                <strong>First Friday Adoration</strong> &bull; Holy Hour after 6:00 PM Mass
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- COLUMN 2: COMMUNITY CHAPELS NETWORK -->
            <div class="liturgy-chapels-card">
                <div class="liturgy-card-head">
                    <div class="liturgy-head-title-wrap">
                        <span class="liturgy-badge-pill"><i class="fa-solid fa-place-of-worship"></i> Community Chapels</span>
                        <h3 class="liturgy-head-title">Community Chapels</h3>
                        <p class="liturgy-head-sub"><i class="fa-solid fa-shield-halved"></i> <?php echo count($homeViewData['chapels'] ?? []); ?> Active Community Chapels in Bacoor</p>
                    </div>
                </div>

                <div class="liturgy-chapels-list">
                    <?php foreach(($homeViewData['chapels'] ?? []) as $chapel): ?>
                    <div class="chapel-compact-row" data-chapel="<?php echo htmlspecialchars(json_encode($chapel, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>" onclick="openChapelModal(this)" role="button" tabindex="0" title="Click to view details for <?php echo htmlspecialchars($chapel['chapel_name']); ?>">
                        <div class="chapel-compact-left">
                            <div class="chapel-compact-icon">
                                <i class="fa-solid fa-place-of-worship"></i>
                            </div>
                            <div class="chapel-compact-info">
                                <h4 class="chapel-compact-name"><?php echo htmlspecialchars($chapel['chapel_name']); ?></h4>
                                <div class="chapel-compact-meta">
                                    <?php if (!empty($chapel['patron_saint'])): ?>
                                        <span class="chapel-meta-pill"><i class="fa-solid fa-cross"></i> <?php echo htmlspecialchars($chapel['patron_saint']); ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($chapel['sunday_service_time'])): ?>
                                        <span class="chapel-meta-pill"><i class="fa-solid fa-clock"></i> <?php echo htmlspecialchars($chapel['sunday_service_time']); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <span class="chapel-compact-btn">
                            <span>View Details</span>
                            <i class="fa-solid fa-arrow-right"></i>
                        </span>
                    </div>
                    <?php endforeach; ?>

                    <div class="chapels-compact-help">
                        <i class="fa-solid fa-circle-info" style="color: var(--gold);"></i>
                        <span>Select any chapel to view mass schedules, patron feast, and location guide.</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ── CHURCH SERVICES ── -->
<section id="services" class="church-section services-master-section section-bg-alt">
    <div class="container">
        <!-- Section Header -->
        <div class="church-section-head scroll-reveal">
            <h2 class="church-section-title">Church Services</h2>
            <p class="church-section-sub">
                Baptisms, weddings, blessings, Mass intentions, and church certificates.
            </p>
        </div>

        <?php
        $catCounts = ['all' => 0];
        foreach ($services_catalog as $cItem) {
            $cCode = $cItem['code'] ?? 'general';
            $srvCount = count($cItem['services'] ?? []);
            $catCounts[$cCode] = ($catCounts[$cCode] ?? 0) + $srvCount;
            $catCounts['all'] += $srvCount;
        }
        ?>

        <!-- Toolbar: Category Tabs + Quick Search -->
        <div class="services-ledger-toolbar scroll-reveal">
            <div class="service-category-tabs">
                <button type="button" class="service-tab-btn active" onclick="filterPublicServices('all', this)">
                    <i class="fa-solid fa-layer-group"></i> All Services <span class="tab-count-badge"><?php echo $catCounts['all'] ?? 0; ?></span>
                </button>
                <button type="button" class="service-tab-btn" onclick="filterPublicServices('sacraments', this)">
                    <i class="fa-solid fa-dove"></i> Sacraments <span class="tab-count-badge"><?php echo $catCounts['sacraments'] ?? 0; ?></span>
                </button>
                <button type="button" class="service-tab-btn" onclick="filterPublicServices('liturgical_services', this)">
                    <i class="fa-solid fa-church"></i> Mass Intentions <span class="tab-count-badge"><?php echo $catCounts['liturgical_services'] ?? 0; ?></span>
                </button>
                <button type="button" class="service-tab-btn" onclick="filterPublicServices('blessings', this)">
                    <i class="fa-solid fa-hands-praying"></i> Blessings <span class="tab-count-badge"><?php echo $catCounts['blessings'] ?? 0; ?></span>
                </button>
                <button type="button" class="service-tab-btn" onclick="filterPublicServices('certificates', this)">
                    <i class="fa-solid fa-file-shield"></i> Certificates <span class="tab-count-badge"><?php echo $catCounts['certificates'] ?? 0; ?></span>
                </button>
            </div>
        </div>

        <!-- Institutional Schedule Table & Ledger -->
        <div class="services-schedule-table-wrap scroll-reveal">
            <table class="services-ledger-table" id="servicesLedgerTable">
                <thead>
                    <tr>
                        <th class="col-service" scope="col"><i class="fa-solid fa-church"></i> Service</th>
                        <th class="col-prereq" scope="col"><i class="fa-solid fa-file-shield"></i> Required Documents</th>
                        <th class="col-stipend" scope="col"><i class="fa-solid fa-coins"></i> Fee / Offering</th>
                        <th class="col-action" scope="col" style="text-align: right;">Overview</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $srvIndex = 0;
                    foreach ($services_catalog as $cat): 
                        foreach ($cat['services'] as $srv): 
                            $srvIndex++;
                            $catCode = $cat['code'] ?? 'general';
                            $iconClass = 'fa-church';
                            $sName = strtolower($srv['name']);
                            if (strpos($sName, 'baptism') !== false) $iconClass = 'fa-water';
                            elseif (strpos($sName, 'confirmation') !== false) $iconClass = 'fa-dove';
                            elseif (strpos($sName, 'wedding') !== false) $iconClass = 'fa-ring';
                            elseif (strpos($sName, 'thanksgiving') !== false) $iconClass = 'fa-hand-holding-heart';
                            elseif (strpos($sName, 'burial') !== false) $iconClass = 'fa-cross';
                            elseif (strpos($sName, 'blessing') !== false) $iconClass = 'fa-hands-praying';
                            elseif (strpos($sName, 'requiem') !== false) $iconClass = 'fa-cross';
                            elseif (strpos($sName, 'certificate') !== false) $iconClass = 'fa-certificate';

                            $minPrice = null;
                            foreach ($srv['variants'] as $v) {
                                $p = (float)($v['price'] ?? 0);
                                if ($minPrice === null || $p < $minPrice) {
                                    $minPrice = $p;
                                }
                            }
                            $variantCount = count($srv['variants']);
                            $reqCount = count($srv['requirements'] ?? []);
                            $chargeCount = count($srv['charges'] ?? []);
                            $searchHaystack = strtolower($srv['name'] . ' ' . ($cat['name'] ?? '') . ' ' . ($srv['description'] ?? ''));
                    ?>
                    <!-- Main Master Row (Clickable Summary) -->
                    <tr class="service-ledger-row service-ledger-clickable" data-cat="<?php echo htmlspecialchars($catCode); ?>" data-search="<?php echo htmlspecialchars($searchHaystack); ?>" id="srv-row-<?php echo $srvIndex; ?>" onclick="toggleServiceDocket(<?php echo $srvIndex; ?>, event)" tabindex="0" role="button" aria-expanded="false" aria-controls="srv-docket-<?php echo $srvIndex; ?>" onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();toggleServiceDocket(<?php echo $srvIndex; ?>,event);}">
                        <td class="service-ledger-cell col-service">
                            <div class="service-cell-wrap">
                                <div class="service-ledger-icon">
                                    <i class="fa-solid <?php echo $iconClass; ?>"></i>
                                </div>
                                <div class="service-cell-text">
                                    <div class="service-cell-meta">
                                        <span class="service-cat-pill"><?php echo htmlspecialchars($cat['name']); ?></span>
                                    </div>
                                    <h3 class="service-ledger-title"><?php echo htmlspecialchars($srv['name']); ?></h3>
                                </div>
                            </div>
                        </td>
                        <td class="service-ledger-cell col-prereq">
                            <div class="prereq-cell-wrap">
                                <?php if ($reqCount > 0): ?>
                                    <span class="prereq-summary-chip">
                                        <i class="fa-solid fa-file-shield"></i> <?php echo $reqCount; ?> Document<?php echo $reqCount > 1 ? 's' : ''; ?> Required
                                    </span>
                                    <span class="prereq-subnote">Click to preview requirements</span>
                                <?php else: ?>
                                    <span class="direct-booking-pill">
                                        <i class="fa-solid fa-circle-check"></i> Direct Parish Booking
                                    </span>
                                    <span class="prereq-subnote">No special documents required</span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="service-ledger-cell col-stipend">
                            <div class="stipend-cell-wrap">
                                <?php if ($variantCount === 1): ?>
                                    <div class="stipend-primary-val">
                                        <span class="stipend-amount">₱<?php echo number_format($minPrice ?? 0, 2); ?></span>
                                    </div>
                                    <span class="single-tier-tag"><i class="fa-solid fa-check"></i> Standard Fee</span>
                                <?php elseif ($variantCount > 1): ?>
                                    <div class="stipend-primary-val">
                                        <span class="stipend-prefix">From</span>
                                        <span class="stipend-amount">₱<?php echo number_format($minPrice ?? 0, 2); ?></span>
                                    </div>
                                    <span class="single-tier-tag"><?php echo $variantCount; ?> Options Available</span>
                                <?php else: ?>
                                    <div class="stipend-primary-val">
                                        <span class="stipend-amount">Voluntary</span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="service-ledger-cell col-action">
                            <div class="action-cell-wrap">
                                <button type="button" class="btn-ledger-toggle" aria-label="Toggle details for <?php echo htmlspecialchars($srv['name']); ?>">
                                    <span>View Details</span>
                                    <i class="fa-solid fa-chevron-down caret-icon"></i>
                                </button>
                            </div>
                        </td>
                    </tr>

                    <!-- Expandable Institutional Docket Drawer Row (Full Details) -->
                    <tr class="service-docket-row" id="srv-docket-<?php echo $srvIndex; ?>" data-cat="<?php echo htmlspecialchars($catCode); ?>" style="display: none;">
                        <td colspan="4" class="service-docket-cell">
                            <div class="service-docket-container">
                                <?php if (!empty($srv['description'])): ?>
                                    <p class="docket-service-full-desc">
                                        <i class="fa-solid fa-circle-info" style="color: var(--row-accent); margin-right: 6px;"></i>
                                        <?php echo htmlspecialchars($srv['description']); ?>
                                    </p>
                                <?php endif; ?>

                                <div class="service-docket-grid">
                                    <!-- Left Column: Requirements & Preparation -->
                                    <div class="docket-pane-card">
                                        <div class="docket-pane-title">
                                            <i class="fa-solid fa-clipboard-check"></i> Requirements &amp; Guidelines
                                        </div>
                                        <?php if ($reqCount > 0): ?>
                                            <ul class="docket-req-list">
                                                <?php foreach ($srv['requirements'] as $r): ?>
                                                    <li class="docket-req-item">
                                                        <i class="fa-solid fa-circle-check"></i>
                                                        <div>
                                                            <strong><?php echo htmlspecialchars($r['requirement']); ?></strong>
                                                            <?php if (!empty($r['description'])): ?>
                                                                <span class="req-desc"><?php echo htmlspecialchars($r['description']); ?></span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php else: ?>
                                            <p style="font-size: 0.80rem; color: var(--text-dim); margin-bottom: 0.75rem;">
                                                No advance documents are required for this service. You may schedule it directly with the Parish Office.
                                            </p>
                                        <?php endif; ?>
                                        <div class="docket-pastoral-note">
                                            <i class="fa-solid fa-circle-info"></i>
                                            <strong>Parish Note:</strong> Registered members can upload required documents directly through their member dashboard.
                                        </div>
                                    </div>

                                    <!-- Right Column: Fee Schedule & Options + Direct Request -->
                                    <div class="docket-pane-card">
                                        <div class="docket-pane-title">
                                            <i class="fa-solid fa-scale-balanced"></i> Fees &amp; Options
                                        </div>
                                        <table class="docket-variants-table">
                                            <thead>
                                                <tr>
                                                    <th>Option / Tier</th>
                                                    <th>Sponsors Included</th>
                                                    <th style="text-align: right;">Fee / Offering</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($srv['variants'] as $v): ?>
                                                    <tr>
                                                        <td><span class="docket-var-name"><?php echo htmlspecialchars($v['name']); ?></span></td>
                                                        <td>
                                                            <?php if ((int)($v['included_sponsors'] ?? 0) > 0): ?>
                                                                <span class="docket-var-sponsors"><i class="fa-solid fa-users"></i> <?php echo (int)$v['included_sponsors']; ?> sponsors</span>
                                                            <?php else: ?>
                                                                <span style="color: var(--text-muted); font-size: 0.72rem;">—</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td class="docket-var-price">PHP <?php echo number_format((float)$v['price'], 2); ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>

                                        <?php if (!empty($srv['charges'])): ?>
                                            <div class="docket-addons-section">
                                                <div class="docket-addons-title">
                                                    <i class="fa-solid fa-plus-circle"></i> Optional Add-ons:
                                                </div>
                                                <div class="docket-addons-grid">
                                                    <?php foreach ($srv['charges'] as $ch): ?>
                                                        <span class="docket-addon-pill">
                                                            <span><?php echo htmlspecialchars($ch['name']); ?></span>
                                                            <strong>+PHP <?php echo number_format((float)$ch['price'], 2); ?></strong>
                                                        </span>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>

                                        <button type="button" onclick="openLoginModal()" class="btn-docket-request" style="cursor: pointer; border: none; font: inherit;">
                                            <i class="fa-solid fa-calendar-check"></i> Request Schedule for <?php echo htmlspecialchars($srv['name']); ?>
                                            <i class="fa-solid fa-arrow-right"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div id="servicesLedgerEmpty" class="services-ledger-empty" style="display:none;">
                <i class="fa-solid fa-magnifying-glass"></i>
                <p>No services match your filter.</p>
            </div>
        </div>
    </div>
</section>

<!-- Public Requirements Modal -->
<div id="public-requirements-modal" class="service-modal-overlay">
    <div class="service-modal-dialog">
        <div class="service-modal-head">
            <div style="display: flex; align-items: center; gap: 8px;">
                <i class="fa-solid fa-clipboard-check" style="color: var(--accent);"></i>
                <h4 id="pub-req-modal-title">Service Requirements</h4>
            </div>
            <button type="button" onclick="closeRequirementsModal()" class="modal-close-btn" aria-label="Close modal"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="service-modal-body">
            <div class="service-modal-subtext">
                Please prepare and submit the following documents to the Cathedral Parish Office:
            </div>
            <ul id="pub-req-modal-list" class="service-modal-req-list"></ul>
            <div class="service-modal-info-callout">
                <i class="fa-solid fa-circle-info"></i>
                <div>Registered Cathedral members can upload digitized copies directly when submitting an online request.</div>
            </div>
        </div>
        <div class="service-modal-foot">
            <button type="button" onclick="closeRequirementsModal()" class="btn-req" style="flex:0 0 auto; min-width: 80px;">Close</button>
            <button type="button" onclick="closeRequirementsModal(); openLoginModal();" class="btn-act" style="flex:0 0 auto; cursor: pointer; border: none; font: inherit;">
                <i class="fa-solid fa-arrow-right"></i> Proceed to Request
            </button>
        </div>
    </div>
</div>

<script>
let currentActiveCat = 'all';

function filterPublicServices(catCode, btn) {
    currentActiveCat = catCode;
    const tabs = document.querySelectorAll('.service-category-tabs .service-tab-btn');
    tabs.forEach(t => t.classList.remove('active'));
    if (btn) btn.classList.add('active');
    
    const rows = document.querySelectorAll('.service-ledger-row');
    let visibleCount = 0;

    rows.forEach(row => {
        const rowCat = row.getAttribute('data-cat');
        const rowId = row.id.replace('srv-row-', '');
        const docketRow = document.getElementById('srv-docket-' + rowId);

        if (currentActiveCat === 'all' || rowCat === currentActiveCat) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
            if (docketRow) {
                docketRow.style.display = 'none';
                row.classList.remove('is-expanded');
                row.setAttribute('aria-expanded', 'false');
            }
        }
    });

    const emptyState = document.getElementById('servicesLedgerEmpty');
    if (emptyState) {
        emptyState.style.display = visibleCount === 0 ? 'block' : 'none';
    }
}

function toggleServiceDocket(index, event) {
    if (event && event.target && event.target.closest('a, .btn-docket-request')) {
        return; // Allow clicking links directly without closing
    }
    const row = document.getElementById('srv-row-' + index);
    const docket = document.getElementById('srv-docket-' + index);
    if (!row || !docket) return;

    const isOpen = docket.style.display === 'table-row';
    if (!isOpen) {
        docket.style.display = 'table-row';
        row.classList.add('is-expanded');
        row.setAttribute('aria-expanded', 'true');
    } else {
        docket.style.display = 'none';
        row.classList.remove('is-expanded');
        row.setAttribute('aria-expanded', 'false');
    }
}

function openRequirementsModal(srv) {
    const modal = document.getElementById('public-requirements-modal');
    const title = document.getElementById('pub-req-modal-title');
    const list = document.getElementById('pub-req-modal-list');
    if (!modal || !srv) return;
    
    title.textContent = srv.name + ' Requirements';
    list.innerHTML = '';
    
    if (srv.requirements && srv.requirements.length > 0) {
        srv.requirements.forEach(r => {
            const li = document.createElement('li');
            li.innerHTML = `<strong>${r.requirement}</strong>${r.description ? ` <span>— ${r.description}</span>` : ''}`;
            list.appendChild(li);
        });
    } else {
        list.innerHTML = '<li>No special documents required for this service.</li>';
    }
    
    modal.style.display = 'flex';
}

function closeRequirementsModal() {
    const modal = document.getElementById('public-requirements-modal');
    if (modal) modal.style.display = 'none';
}

document.getElementById('public-requirements-modal')?.addEventListener('click', function(e) {
    if (e.target === this) closeRequirementsModal();
});
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeRequirementsModal();
});
</script>

<!-- ── PARISH BULLETIN ── -->
<section id="bulletin" class="church-section section-bg-primary">
    <div class="container">
        <div class="church-section-head scroll-reveal">
            <h2 class="church-section-title">News &amp; Announcements</h2>
            <p class="church-section-sub">Parish updates, upcoming events, and community notices.</p>
        </div>

        <!-- Chapel / Scope Filter Pills -->
        <?php if (!empty($chapels)): ?>
        <div class="bulletin-filter-bar scroll-reveal" role="tablist" aria-label="Filter notices by chapel or parish">
            <button type="button" class="bulletin-pill active" data-filter="all" onclick="filterBulletin('all', this)" role="tab" aria-selected="true">
                <i class="fa-solid fa-layer-group pill-icon"></i> All Notices
                <span class="pill-count"><?php echo count($bulletin_items); ?></span>
            </button>
            <button type="button" class="bulletin-pill" data-filter="parish" onclick="filterBulletin('parish', this)" role="tab" aria-selected="false">
                <i class="fa-solid fa-church pill-icon"></i> Parish-Wide
                <span class="pill-count"><?php echo count(array_filter($bulletin_items, function($b) { return empty($b['chapel_id']); })); ?></span>
            </button>
            <?php foreach ($chapels as $ch): 
                $cCount = count(array_filter($bulletin_items, function($b) use ($ch) { return !empty($b['chapel_id']) && (int)$b['chapel_id'] === (int)$ch['chapel_id']; }));
            ?>
            <button type="button" class="bulletin-pill" data-filter="<?php echo (int)$ch['chapel_id']; ?>" onclick="filterBulletin('<?php echo (int)$ch['chapel_id']; ?>', this)" role="tab" aria-selected="false">
                <i class="fa-solid fa-place-of-worship pill-icon"></i>
                <?php echo htmlspecialchars($ch['chapel_name']); ?>
                <?php if ($cCount > 0): ?>
                <span class="pill-count"><?php echo $cCount; ?></span>
                <?php endif; ?>
            </button>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Bulletin Grid Container -->
        <div class="bulletin-frame scroll-reveal">
            <div class="bulletin-grid">
                <?php if(empty($bulletin_items)): ?>
                    <div style="grid-column:1/-1;text-align:center;padding:50px 20px;">
                        <i class="fa-regular fa-scroll" style="font-size:2.6rem;margin-bottom:12px;opacity:0.3;color:var(--gold);display:block;"></i>
                        <h4 style="font-family:var(--font-family);font-size:1.05rem;font-weight:600;margin-bottom:5px;color:var(--text-main);">No Current Announcements</h4>
                        <p style="font-size:0.82rem;color:var(--text-dim);">Check back soon for news and event updates.</p>
                    </div>
                <?php else: ?>
                    <?php foreach($bulletin_items as $index => $bi): 
                        $bChapelId = !empty($bi['chapel_id']) ? (int)$bi['chapel_id'] : 0;
                    ?>
                    <article class="bulletin-post" 
                             data-chapel="<?php echo $bChapelId; ?>"
                             data-bulletin="<?php echo htmlspecialchars(json_encode($bi, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>"
                             onclick="openBulletinModal(this)"
                             tabindex="0"
                             role="button"
                             aria-label="View notice: <?php echo htmlspecialchars($bi['title']); ?>">
                        <div class="bulletin-post-top">
                            <span class="bulletin-tag">
                                <?php if(!empty($bi['chapel_name'])): ?>
                                    <i class="fa-solid fa-place-of-worship"></i>
                                    <span><?php echo htmlspecialchars($bi['chapel_name']); ?></span>
                                <?php else: ?>
                                    <i class="fa-solid fa-church"></i>
                                    <span>Cathedral Parish-Wide</span>
                                <?php endif; ?>
                            </span>
                            <span class="bulletin-date">
                                <i class="fa-regular fa-calendar"></i> <?php echo !empty($bi['created_at']) ? date('M d, Y', strtotime($bi['created_at'])) : date('M d, Y'); ?>
                            </span>
                        </div>
                        
                        <h3 class="bulletin-post-title"><?php echo htmlspecialchars($bi['title']); ?></h3>
                        
                        <p class="bulletin-post-excerpt"><?php echo htmlspecialchars($bi['subtitle']); ?></p>
                        
                        <div class="bulletin-post-footer">
                            <span>Read Full Announcement</span>
                            <i class="fa-solid fa-arrow-right"></i>
                        </div>
                    </article>
                    <?php endforeach; ?>

                    <!-- Empty Filter State -->
                    <div id="bulletinEmptyState" class="bulletin-empty-filtered" style="display:none; grid-column:1/-1; text-align:center; padding: 48px 20px;">
                        <i class="fa-regular fa-folder-open" style="font-size:2.4rem; margin-bottom:12px; opacity:0.35; color:var(--accent); display:block;"></i>
                        <h4 style="font-family:var(--font-family-display); font-size:1.1rem; font-weight:700; margin-bottom:6px; color:var(--text-main);">No Notices Found for this Chapel</h4>
                        <p style="font-size:0.86rem; color:var(--text-dim); margin-bottom:16px;">There are currently no specific announcements posted for this location.</p>
                        <button type="button" class="btn-outline btn-sm" onclick="filterBulletin('all', document.querySelector('.bulletin-pill[data-filter=\'all\']'))" style="cursor:pointer; display:inline-flex; align-items:center; gap:6px;">
                            <i class="fa-solid fa-arrow-rotate-left"></i> View All Parish Notices
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<!-- ── BULLETIN DETAIL MODAL (FULL DETAILS) ── -->
<div id="bulletinModal" class="login-overlay bulletin-modal-overlay" onclick="if(event.target===this) closeBulletinModal()" role="dialog" aria-modal="true" aria-labelledby="bModalTitle">
    <div class="bulletin-modal-container">
        <!-- Colored Top Brand Banner -->
        <div id="bModalHeader" class="bmodal-header-accent"></div>
        
        <!-- Modal Top Bar -->
        <div class="bmodal-topbar">
            <div class="bmodal-crest-wrap">
                <img src="assets/img/2323.png" alt="Cathedral Seal" class="bmodal-crest">
                <div>
                    <span class="bmodal-diocese">Iglesia Filipina Independiente</span>
                    <span class="bmodal-parish">Cathedral Parish Council</span>
                </div>
            </div>
            <button type="button" onclick="closeBulletinModal()" class="bmodal-close-btn" aria-label="Close Announcement">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <!-- Meta Tag & Date -->
        <div class="bmodal-meta-row">
            <span id="bModalTag" class="bmodal-tag-badge">
                <i class="fa-solid fa-bullhorn"></i> Official Notice
            </span>
            <span id="bModalDate" class="bmodal-date-text">
                <i class="fa-regular fa-calendar-days"></i> <?php echo date('F j, Y'); ?>
            </span>
        </div>

        <!-- Full Title -->
        <h2 id="bModalTitle" class="bmodal-title">Announcement Title</h2>

        <!-- Full Content Body -->
        <div class="bmodal-body-scroll">
            <div class="bmodal-content-card">
                <div class="bmodal-content-icon"><i class="fa-solid fa-quote-left"></i></div>
                <div id="bModalSubtitle" class="bmodal-content-text">
                    Announcement details and pastoral message.
                </div>
            </div>
            
            <!-- Official Cathedral Sign-off Box -->
            <div class="bmodal-signoff">
                <div class="signoff-seal">
                    <i class="fa-solid fa-certificate"></i>
                </div>
                <div>
                    <div class="signoff-name">Office of the Cathedral Dean &amp; Parish Ministry</div>
                    <div class="signoff-sub">Pro Deo et Patria &bull; Faith, Justice &amp; Fellowship</div>
                </div>
            </div>
        </div>

        <!-- Footer Actions -->
        <div class="bmodal-footer-actions">
            <button type="button" id="bModalCopyBtn" onclick="copyBulletinNotice()" class="btn-outline bmodal-print-btn" title="Copy announcement text">
                <i class="fa-regular fa-copy"></i> Copy Info
            </button>
            <button type="button" onclick="window.print()" class="btn-outline bmodal-print-btn" title="Print official notice flyer">
                <i class="fa-solid fa-print"></i> Print Notice
            </button>
            <button type="button" onclick="closeBulletinModal()" class="btn-gold bmodal-dismiss-btn">
                Close Notice
            </button>
        </div>
    </div>
</div>

<!-- ── CHAPEL DETAIL MODAL ── -->
<div id="chapelModal" class="login-overlay bulletin-modal-overlay" onclick="if(event.target===this) closeChapelModal()" role="dialog" aria-modal="true" aria-labelledby="cModalTitle">
    <div class="bulletin-modal-container">
        <!-- Accent Banner -->
        <div id="cModalHeader" class="bmodal-header-accent"></div>
        
        <!-- Modal Top Bar -->
        <div class="bmodal-topbar">
            <div class="bmodal-crest-wrap">
                <div id="cModalIcon" class="chapel-modal-badge"><i class="fa-solid fa-place-of-worship"></i></div>
                <div>
                    <span class="bmodal-diocese">Diocesan Community Chapel</span>
                    <span id="cModalParish" class="bmodal-parish">Cathedral Parish Network</span>
                </div>
            </div>
            <button type="button" onclick="closeChapelModal()" class="bmodal-close-btn" aria-label="Close Chapel Details">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <!-- Meta Patron & Capacity -->
        <div class="bmodal-meta-row">
            <span id="cModalPatron" class="bmodal-tag-badge"><i class="fa-solid fa-cross"></i> Patron Saint</span>
            <span id="cModalCapacity" class="bmodal-date-text"><i class="fa-solid fa-chair"></i> Seating: 50</span>
        </div>

        <h2 id="cModalTitle" class="bmodal-title">Chapel Name</h2>

        <div class="bmodal-body-scroll">
            <!-- Schedule Box -->
            <div class="bmodal-content-card">
                <div class="bmodal-content-icon"><i class="fa-regular fa-clock"></i></div>
                <h4 style="font-family:var(--font-family);font-size:0.92rem;font-weight:600;color:var(--text-main);margin-bottom:6px;"><i class="fa-solid fa-church"></i> Mass &amp; Service Times</h4>
                <p id="cModalSchedule" style="font-size:0.88rem;color:var(--text-main);margin:0;line-height:1.6;font-weight:600;">Sunday Service: Check Parish Schedule</p>
            </div>

            <!-- Location Box -->
            <div class="bmodal-content-card" style="margin-bottom:1rem;">
                <div class="bmodal-content-icon"><i class="fa-solid fa-location-dot"></i></div>
                <h4 style="font-family:var(--font-family);font-size:0.92rem;font-weight:600;color:var(--text-main);margin-bottom:6px;"><i class="fa-solid fa-map-pin"></i> Location &amp; Address</h4>
                <p id="cModalLocation" style="font-size:0.85rem;color:var(--text-dim);margin:0;line-height:1.6;">Parish Vicinity</p>
            </div>

            <!-- Pastoral Signoff -->
            <div class="bmodal-signoff">
                <div class="signoff-seal"><i class="fa-solid fa-certificate"></i></div>
                <div>
                    <div class="signoff-name">Iglesia Filipina Independiente &bull; Diocesan Chapel Ministry</div>
                    <div class="signoff-sub">Under the pastoral care of the IFI Cathedral Dean &amp; Parish Council</div>
                </div>
            </div>
        </div>

        <!-- Footer Actions -->
        <div class="bmodal-footer-actions">
            <a href="#hub" onclick="closeChapelModal()" class="btn-outline bmodal-print-btn" style="text-decoration:none;">
                <i class="fa-solid fa-hand-holding-heart"></i> Make an Offering
            </a>
            <button type="button" onclick="closeChapelModal()" class="btn-gold bmodal-dismiss-btn">
                Close
            </button>
        </div>
    </div>
</div>

<!-- ── VISION & MISSION ── -->
<section id="vision-mission" class="church-section section-bg-alt">
    <div class="container">
        <div class="church-section-head scroll-reveal">
            <h2 class="church-section-title">Our Faith &amp; Mission</h2>
            <p class="church-section-sub">Who we are, what we believe, and how we serve our community.</p>
        </div>
        <!-- Sacred Pillars: Vision & Mission -->
        <div class="sacred-pillars-grid">
            <!-- Vision -->
            <div class="sacred-pillar-card scroll-reveal">
                <div class="sacred-pillar-icon"><i class="fa-solid fa-eye"></i></div>
                <div class="sacred-pillar-title">Our Vision</div>
                <p class="sacred-pillar-text">
                    "The Iglesia Filipina Independiente envisions a renewed church that is truly Filipino, independent, and responsive to the needs of the people. We are committed to proclaiming the Gospel, promoting justice, peace, and human dignity."
                </p>
            </div>

            <!-- Mission -->
            <div class="sacred-pillar-card scroll-reveal">
                <div class="sacred-pillar-icon"><i class="fa-solid fa-fire-flame-curved"></i></div>
                <div class="sacred-pillar-title">Our Mission</div>
                <ul class="sacred-pillar-list">
                    <li><i class="fa-solid fa-circle-check"></i><span>Proclaim the Good News of God's Kingdom through worship, teaching, and service.</span></li>
                    <li><i class="fa-solid fa-circle-check"></i><span>Administer the sacraments and nurture the spiritual life of our members.</span></li>
                    <li><i class="fa-solid fa-circle-check"></i><span>Promote social justice, human dignity, and selfless care for our neighbors.</span></li>
                    <li><i class="fa-solid fa-circle-check"></i><span>Uphold Filipino identity and heritage in reverent Christian fellowship.</span></li>
                </ul>
            </div>
        </div>

        <!-- Calling Motto Banner -->
        <div class="sacred-calling-banner scroll-reveal">
            <div class="calling-banner-crest">
                <img src="assets/img/2323.png" alt="Cathedral Crest">
            </div>
            <div class="calling-banner-text">
                <h3>Pro Deo et Patria &bull; For God and Country</h3>
                <p>Sharing Christ's love, caring for our neighbors, and serving our community with compassion.</p>
            </div>
            <div class="calling-banner-badges">
                <span class="calling-badge"><i class="fa-solid fa-cross"></i> Faith</span>
                <span class="calling-badge"><i class="fa-solid fa-scale-balanced"></i> Justice</span>
                <span class="calling-badge"><i class="fa-solid fa-dove"></i> Peace</span>
                <span class="calling-badge"><i class="fa-solid fa-users"></i> Fellowship</span>
            </div>
        </div>
    </div>
</section>

<!-- ── ABOUT OUR CHURCH ── -->
<section id="about" class="section church-section section-bg-primary">
    <div class="container">
        <div class="church-section-head scroll-reveal">
            <h2 class="church-section-title">About Our Church</h2>
            <p class="church-section-sub">A community of faith, freedom, and fellowship rooted in the history of the Filipino people.</p>
        </div>
        
        <div class="bento-story">
            <!-- History Timeline -->
            <div class="bento-card span-8 scroll-reveal">
                <h3 style="font-family:var(--font-family); font-weight:700; margin-bottom:1rem; font-size:1.35rem; color:var(--text-main);">
                    <i class="fa-solid fa-landmark" style="color:var(--gold); margin-right:8px;"></i> Our History
                </h3>
                <p style="font-size:0.95rem; margin-bottom:1.75rem; line-height:1.7; color:var(--text-dim);">
                    <?php 
                    echo !empty($history_intro) ? htmlspecialchars($history_intro) : 
                    "IFI Cathedral stands as a beacon of faith and independence, a testament to a journey that spans generations. From our humble beginnings to our place as a mother church for our community, we cherish the sacred traditions of the Iglesia Filipina Independiente, fostering unity, dignity, and love.";
                    ?>
                </p>
                <div>
                    <?php if (empty($history_items)): ?>
                        <div class="timeline-item">
                            <div class="timeline-year">ESTABLISHED</div>
                            <div class="timeline-title">Founding of the Cathedral Mission</div>
                            <p style="font-size:0.85rem; color:var(--text-dim); margin:0;">A congregation founded on the gospel of Christ and the historic pursuit of religious autonomy.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach($history_items as $hi): ?>
                        <div class="timeline-item" data-history="<?php echo htmlspecialchars(json_encode($hi, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>" onclick="openHistoryModal(this)" style="cursor:pointer;">
                            <div class="timeline-year"><?php echo htmlspecialchars($hi['year']); ?></div>
                            <div class="timeline-title"><?php echo htmlspecialchars($hi['title']); ?></div>
                            <?php if(!empty($hi['description'])): ?>
                                <p style="font-size:0.85rem; color:var(--text-dim); line-height:1.55; margin:0;"><?php echo htmlspecialchars($hi['description']); ?></p>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Cathedral Clergy -->
            <?php
            $cathedralClergy = [];
            $diocesanClergy = [];
            foreach (($leadership['assistants'] ?? []) as $ast) {
                if (stripos($ast['name'] ?? '', 'Ruben') !== false || stripos($ast['subtitle'] ?? '', 'Cathedral') !== false) {
                    $cathedralClergy[] = $ast;
                } else {
                    $diocesanClergy[] = $ast;
                }
            }
            ?>
            <div class="bento-card span-4 scroll-reveal">
                <h3 style="font-family:var(--font-family); font-weight:700; margin-bottom:1.25rem; font-size:1.25rem; color:var(--text-main);">
                    <i class="fa-solid fa-hands-praying" style="color:var(--gold); margin-right:8px;"></i> Priests &amp; Clergy
                </h3>
                <div>
                    <?php if (!empty($leadership['bishop'])): ?>
                    <div class="clergy-profile-card">
                        <img class="clergy-avatar" src="<?php echo !empty($leadership['bishop']['image_path']) ? 'storage/'.$leadership['bishop']['image_path'] : 'assets/img/2323.png'; ?>" alt="Bishop">
                        <div class="clergy-info">
                            <h4><?php echo htmlspecialchars($leadership['bishop']['name']); ?></h4>
                            <p><?php echo htmlspecialchars($leadership['bishop']['title']); ?></p>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($leadership['parish_priest'])): ?>
                    <div class="clergy-profile-card">
                        <img class="clergy-avatar" src="<?php echo !empty($leadership['parish_priest']['image_path']) ? 'storage/'.$leadership['parish_priest']['image_path'] : 'assets/img/2323.png'; ?>" alt="Parish Priest">
                        <div class="clergy-info">
                            <h4><?php echo htmlspecialchars($leadership['parish_priest']['name']); ?></h4>
                            <p><?php echo htmlspecialchars($leadership['parish_priest']['title']); ?></p>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php foreach ($cathedralClergy as $ccl): ?>
                    <div class="clergy-profile-card">
                        <img class="clergy-avatar" src="<?php echo !empty($ccl['image_path']) ? 'storage/'.$ccl['image_path'] : 'assets/img/2323.png'; ?>" alt="Cathedral Canon">
                        <div class="clergy-info">
                            <h4><?php echo htmlspecialchars($ccl['name']); ?></h4>
                            <p><?php echo htmlspecialchars($ccl['title']); ?></p>
                        </div>
                    </div>
                    <?php endforeach; ?>

                    <?php if (!empty($diocesanClergy)): ?>
                    <div style="margin-top:1rem; padding-top:0.85rem; border-top:1px solid var(--border); text-align:center;">
                        <a href="#diocesan-clergy" style="display:inline-flex; align-items:center; gap:6px; font-size:0.78rem; font-weight:600; color:var(--accent); text-decoration:none; padding:7px 14px; border-radius:var(--radius-sm); background:var(--bg-surface); border:1px solid var(--border); transition:var(--transition);">
                            <i class="fa-solid fa-cross"></i> View Diocesan Clergy (<?php echo count($diocesanClergy); ?> Parishes) <i class="fa-solid fa-arrow-down" style="font-size:0.7rem;"></i>
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Parish Councils & Directory -->
            <div id="directory" class="bento-card span-12 scroll-reveal">
                <div style="text-align:center; max-width:620px; margin:0 auto 1.75rem;">
                    <h3 style="font-family:var(--font-family); font-size:1.35rem; font-weight:700; color:var(--text-main); margin-bottom:4px;">
                        <i class="fa-solid fa-users" style="color:var(--gold); margin-right:8px;"></i> Parish Leaders &amp; Ministries
                    </h3>
                    <p style="font-size:0.88rem; color:var(--text-dim);">Our parish council, ministry leaders, and dedicated community volunteers.</p>
                </div>

                <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap:1.5rem; margin-bottom:1.5rem;">
                    <!-- Administration & Councils -->
                    <div style="background:var(--bg-surface); padding:1.75rem; border-radius:var(--radius-md); border:1px solid var(--border);">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.25rem; padding-bottom:0.75rem; border-bottom:1px solid var(--border);">
                            <h4 style="font-size:0.82rem; font-weight:800; text-transform:uppercase; color:var(--gold); letter-spacing:0.06em; margin:0;">
                                <i class="fa-solid fa-briefcase"></i> Admin & Councils
                            </h4>
                            <span style="font-size:0.75rem; color:var(--text-muted);">Council Members</span>
                        </div>
                        <table style="width:100%; font-size:0.85rem; border-collapse:collapse;">
                            <?php foreach(array_merge($leadership['executive'] ?? [], $leadership['council'] ?? []) as $exec): ?>
                            <tr class="dir-row" data-leader="<?php echo htmlspecialchars(json_encode($exec, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>" onclick="openLeaderModal(this)" style="cursor:pointer; border-bottom:1px solid var(--border);">
                                <td style="padding:8px 0; color:var(--text-dim); text-transform:capitalize;"><?php echo htmlspecialchars($exec['title']); ?></td>
                                <td style="padding:8px 0; font-weight:700; text-align:right; color:var(--text-main);"><?php echo htmlspecialchars($exec['name']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </table>
                    </div>

                    <!-- Ministries & Sectoral Coordinators -->
                    <div style="background:var(--bg-surface); padding:1.75rem; border-radius:var(--radius-md); border:1px solid var(--border);">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.25rem; padding-bottom:0.75rem; border-bottom:1px solid var(--border);">
                            <h4 style="font-size:0.82rem; font-weight:800; text-transform:uppercase; color:var(--gold); letter-spacing:0.06em; margin:0;">
                                <i class="fa-solid fa-hands-holding-child"></i> Sectoral Ministries
                            </h4>
                            <span style="font-size:0.75rem; color:var(--text-muted);">Organizations</span>
                        </div>
                        <div style="display:grid; grid-template-columns:1fr; gap:8px;">
                            <?php 
                            $sectoralList = array_merge($leadership['sectoral'] ?? [], $leadership['coordinator'] ?? []);
                            if (empty($sectoralList)): 
                            ?>
                            <div style="padding: 22px 16px; text-align: center; color: var(--text-dim); background: var(--bg-card); border-radius: var(--radius-sm); border: 1px dashed var(--border);">
                                <i class="fa-solid fa-users" style="font-size: 22px; color: var(--gold); opacity: 0.6; margin-bottom: 6px; display: block;"></i>
                                <div style="font-size: 0.85rem; font-weight: 700; color: var(--text-main); margin-bottom: 3px;">Cathedral Lay Ministries</div>
                                <p style="font-size: 0.76rem; margin: 0; line-height: 1.45; color: var(--text-dim);">Youth (YMYO), Women (WOPIC), Men (LMM), and Music ministries operate under the Cathedral Pastoral Council.</p>
                            </div>
                            <?php else: ?>
                            <?php foreach($sectoralList as $sec): ?>
                            <div class="dir-item" data-leader="<?php echo htmlspecialchars(json_encode($sec, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>" onclick="openLeaderModal(this)" style="display:flex; justify-content:space-between; align-items:center; padding:9px 12px; background:var(--bg-card); border-radius:var(--radius-sm); border:1px solid var(--border); cursor:pointer; transition:var(--transition);">
                                <div>
                                    <span style="font-size:0.7rem; color:var(--gold); font-weight:800; display:block; text-transform:capitalize;"><?php echo htmlspecialchars($sec['title']); ?></span>
                                    <span style="font-size:0.88rem; font-weight:700; color:var(--text-main);"><?php echo htmlspecialchars($sec['name']); ?></span>
                                </div>
                                <i class="fa-solid fa-chevron-right" style="font-size:0.75rem; color:var(--gold); opacity:0.6;"></i>
                            </div>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Diocesan Clergy (Cavite Parishes & Missions) - FULL WIDTH & FULL EXPANDED VIEW -->
                <div id="diocesan-clergy" style="background:var(--bg-surface); padding:1.75rem 2rem; border-radius:var(--radius-md); border:1px solid var(--border); box-shadow:0 4px 20px rgba(0,0,0,0.04); scroll-margin-top:100px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom:1.5rem; padding-bottom:1rem; border-bottom:1px solid var(--border);">
                        <div>
                            <div style="display:inline-flex; align-items:center; gap:8px; margin-bottom:4px;">
                                <span style="display:inline-flex; align-items:center; justify-content:center; width:28px; height:28px; border-radius:6px; background:rgba(197,160,89,0.15); color:var(--gold); font-size:13px;">
                                    <i class="fa-solid fa-cross"></i>
                                </span>
                                <h4 style="font-size:1.05rem; font-weight:800; text-transform:uppercase; color:var(--gold); letter-spacing:0.06em; margin:0;">
                                    Diocesan Clergy
                                </h4>
                            </div>
                            <p style="margin:0; font-size:0.82rem; color:var(--text-dim); line-height:1.4;">Diocese of Cavite · Parishes, Mission Stations &amp; Assigned Pastoral Clergy</p>
                        </div>
                        <span style="font-size:0.8rem; font-weight:700; color:var(--text-main); background:var(--bg-card); border:1px solid var(--border); padding:6px 16px; border-radius:999px; display:inline-flex; align-items:center; gap:8px;">
                            <i class="fa-solid fa-church" style="color:var(--gold); font-size:12px;"></i> <?php echo count($diocesanClergy); ?> Parishes &amp; Missions
                        </span>
                    </div>

                    <!-- Full Grid Display (No inner scrolling, fully expanded responsive columns) -->
                    <div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap:12px;">
                        <?php foreach($diocesanClergy as $dc): ?>
                        <div class="dir-item clergy-card-tile" data-leader="<?php echo htmlspecialchars(json_encode($dc, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>" onclick="openLeaderModal(this)" 
                             style="display:flex; align-items:center; gap:12px; padding:12px 16px; background:var(--bg-card); border-radius:var(--radius-sm); border:1px solid var(--border); cursor:pointer;">
                            <div class="clergy-card-icon" style="width:40px; height:40px; border-radius:10px; background:rgba(197,160,89,0.12); border:1px solid rgba(197,160,89,0.25); color:var(--gold); display:flex; align-items:center; justify-content:center; font-size:16px; flex-shrink:0;">
                                <i class="fa-solid fa-church"></i>
                            </div>
                            <div style="min-width:0; flex:1;">
                                <span style="font-size:0.75rem; color:var(--gold); font-weight:800; display:block; line-height:1.35; margin-bottom:3px;">
                                    <?php echo htmlspecialchars($dc['subtitle'] ?: $dc['title']); ?>
                                </span>
                                <span style="font-size:0.92rem; font-weight:700; color:var(--text-main); display:block; line-height:1.3; margin-bottom:2px;">
                                    <?php echo htmlspecialchars($dc['name']); ?>
                                </span>
                                <span style="font-size:0.75rem; color:var(--text-dim); font-weight:600; display:inline-block;">
                                    <?php echo htmlspecialchars($dc['title']); ?>
                                </span>
                            </div>
                            <i class="fa-solid fa-chevron-right clergy-card-chevron" style="font-size:0.75rem; color:var(--gold); opacity:0.4; flex-shrink:0; margin-left:6px;"></i>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ── LOCATION & GIVING ── -->
<section id="hub" class="church-section section-bg-alt">
    <div class="container">
        <div class="church-section-head scroll-reveal">
            <h2 class="church-section-title">Location &amp; Giving</h2>
            <p class="church-section-sub">Visit our Cathedral or support our parish ministries and community programs.</p>
        </div>

        <div class="hub-new-grid">
            <!-- Map Card -->
            <div class="hub-map-card scroll-reveal">
                <iframe src="<?php echo htmlspecialchars($contact_info['map_embed_url'] ?? ''); ?>" allowfullscreen="" loading="lazy" title="Cathedral Map"></iframe>
                <div class="hub-map-info">
                    <div class="hub-map-row">
                        <i class="fa-solid fa-location-dot"></i>
                        <span><?php echo htmlspecialchars($contact_info['address'] ?? 'Parish Sanctuary Address'); ?></span>
                    </div>
                    <div class="hub-map-row">
                        <i class="fa-solid fa-phone"></i>
                        <span><?php echo htmlspecialchars($contact_info['phone'] ?? '+63 (046) 000-0000'); ?></span>
                    </div>
                    <?php if (!empty($contact_info['email'])): ?>
                    <div class="hub-map-row">
                        <i class="fa-solid fa-envelope"></i>
                        <span><?php echo htmlspecialchars($contact_info['email']); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Sacred Giving Banner -->
            <div class="sacred-giving-banner scroll-reveal" onclick="openOfferingModal()">
                <div class="giving-heart-icon"><i class="fa-solid fa-hand-holding-heart"></i></div>
                <div class="giving-title">Support the Cathedral</div>
                <p class="giving-sub">Your donations and offerings help sustain Cathedral programs, community outreach, and church upkeep. Thank you for your support!</p>
                <div class="btn-gold" style="pointer-events:none; display:inline-flex; align-items:center; gap:8px;">
                    <i class="fa-solid fa-gift"></i> Make a Donation · GCash / Maya / Bank
                </div>
                <p style="font-size:0.75rem; color:var(--gold); margin-top:1rem; opacity:0.9;">
                    <i class="fa-solid fa-lock"></i> Secure &amp; Verified Parish Channels
                </p>
            </div>
        </div>
    </div>
</section>

<!-- ── PARISH STORE (MERCHANDISE) ── -->
<section id="merchandise" class="section church-section section-bg-primary">
    <div class="container">
        <div class="church-section-head scroll-reveal">
            <h2 class="church-section-title">Parish Store</h2>
            <p class="church-section-sub">Official Cathedral shirts, rosaries, devotional items, and keepsakes.</p>
        </div>
        
        <div class="merch-slider scroll-reveal">
            <?php if(empty($custom_merch)): ?>
                <div style="width:100%; text-align:center; padding:40px; color:var(--text-dim);">
                    <i class="fa-solid fa-store" style="font-size:2.5rem; margin-bottom:10px; opacity:0.3; color:var(--gold);"></i>
                    <p>New commemorative items arriving soon.</p>
                </div>
            <?php else: ?>
                <?php foreach($custom_merch as $item): ?>
                <div class="merch-item" data-item="<?php echo htmlspecialchars(json_encode($item, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>" onclick="openMerchModal(this)">
                    <img src="<?php echo $item['photo_path'] ?: 'assets/img/2323.png'; ?>" class="merch-img" alt="<?php echo htmlspecialchars($item['name']); ?>">
                    <div class="merch-info">
                        <h5><?php echo htmlspecialchars($item['name']); ?></h5>
                        <p style="color:var(--gold); font-weight:700; font-size:1.05rem; margin:0;">₱<?php echo number_format($item['price'], 2); ?></p>
                        <span style="font-size:0.75rem; color:var(--text-muted); display:inline-block; margin-top:4px;">Tap to view & reserve</span>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- ── PRODUCT DETAIL MODAL ── -->
<div id="merchModal" class="login-overlay" onclick="if(event.target===this) closeMerchModal()">
    <div class="login-inner" style="max-width:440px;">
        <div style="text-align:center;">
            <img id="mModalImg" src="" alt="Merchandise" style="width:100%; height:auto; max-height:240px; object-fit:contain; background:var(--bg-surface); border-radius:var(--radius-md); border:1px solid var(--border); padding:12px; margin-bottom:12px;">
            <h2 id="mModalName" style="font-family:var(--font-family); font-size:1.3rem; font-weight:700; color:var(--text-main); margin-bottom:4px;">Item Name</h2>
            <p id="mModalPrice" style="color:var(--accent); font-weight:800; font-size:1.4rem; margin-bottom:10px;">₱0.00</p>
            <p id="mModalDesc" style="color:var(--text-dim); font-size:0.88rem; margin-bottom:20px; line-height:1.55;">Description goes here.</p>
            
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px;">
                <button type="button" id="reserveBtn" class="btn-gold" style="width:100%; border-radius:var(--radius-md); padding:12px;">
                    <i class="fa-solid fa-hand-holding-heart"></i> Reserve
                </button>
                <button type="button" onclick="closeMerchModal()" class="btn-outline" style="width:100%; border-radius:var(--radius-md); padding:12px;">
                    Close
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ── RESERVATION FORM MODAL ── -->
<div id="reserveFormModal" class="login-overlay" onclick="if(event.target===this) closeReserveModal()">
    <div class="login-inner" style="max-width:460px;">
        <h2 style="font-family:var(--font-family); font-size:1.3rem; font-weight:700; margin-bottom:6px; color:var(--text-main);">Reserve an Item</h2>
        <p style="color:var(--text-dim); font-size:0.85rem; margin-bottom:18px; line-height:1.45;">Please provide your contact details. Our parish office will notify you once your item is ready for pickup.</p>
        
        <form id="merchReserveForm" action="index.php" method="POST" style="display:flex; flex-direction:column; gap:12px;">
            <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
            <input type="hidden" name="action" value="reserve_merch">
            <input type="hidden" id="reserveItemId" name="item_id">
            <input type="hidden" id="reserveItemName" name="item_name">

            <div style="text-align:left; font-size:0.88rem; padding:10px 14px; background:var(--bg-surface); border-radius:var(--radius-sm); border:1px solid var(--border);">
                <strong id="formItemName" style="color:var(--accent);">Item Name</strong><br>
                <span id="formItemPrice" style="color:var(--text-muted); font-size:0.8rem;">₱0.00</span>
            </div>

            <div style="position:relative;">
                <i class="fa-solid fa-user" style="position:absolute; left:14px; top:50%; transform:translateY(-50%); color:var(--accent);"></i>
                <input type="text" name="full_name" placeholder="Your Full Name" maxlength="100" style="width:100%; padding:11px 14px 11px 40px; background:var(--bg-card); border:1px solid var(--border); border-radius:var(--radius-sm); color:var(--text-main); font-family:var(--font-family-sans);" required>
            </div>
            <div style="position:relative;">
                <i class="fa-solid fa-envelope" style="position:absolute; left:14px; top:50%; transform:translateY(-50%); color:var(--accent);"></i>
                <input type="email" name="email" placeholder="Email Address" maxlength="100" style="width:100%; padding:11px 14px 11px 40px; background:var(--bg-card); border:1px solid var(--border); border-radius:var(--radius-sm); color:var(--text-main); font-family:var(--font-family-sans);" required>
            </div>
            <div style="position:relative;">
                <i class="fa-solid fa-phone" style="position:absolute; left:14px; top:50%; transform:translateY(-50%); color:var(--accent);"></i>
                <input type="tel" name="phone" placeholder="0912-345-6789" maxlength="20" pattern="[0-9\+\s\-]{7,20}" title="Enter valid mobile number (e.g. 0912-345-6789 or 09171234567)" style="width:100%; padding:11px 14px 11px 40px; background:var(--bg-card); border:1px solid var(--border); border-radius:var(--radius-sm); color:var(--text-main); font-family:var(--font-family-sans);" required>
            </div>
            <div style="position:relative;">
                <i class="fa-solid fa-boxes-stacked" style="position:absolute; left:14px; top:50%; transform:translateY(-50%); color:var(--accent);"></i>
                <input type="number" name="quantity" value="1" min="1" max="99" style="width:100%; padding:11px 14px 11px 40px; background:var(--bg-card); border:1px solid var(--border); border-radius:var(--radius-sm); color:var(--text-main); font-family:var(--font-family-sans);" title="Quantity" required>
            </div>
            
            <div style="position:relative;">
                <i class="fa-solid fa-place-of-worship" style="position:absolute; left:14px; top:50%; transform:translateY(-50%); color:var(--accent); z-index:1;"></i>
                <select name="target_chapel" style="width:100%; padding:11px 14px 11px 40px; background:var(--bg-card); border:1px solid var(--border); border-radius:var(--radius-sm); color:var(--text-main); font-family:var(--font-family-sans); appearance:none;" required>
                    <option value="" disabled selected>— Select Pickup Location —</option>
                    <option value="0">IFI Cathedral (Main Church)</option>
                    <?php foreach($homeViewData['chapels'] as $c): ?>
                        <option value="<?php echo $c['chapel_id']; ?>"><?php echo htmlspecialchars($c['chapel_name']); ?></option>
                    <?php endforeach; ?>
                </select>
                <i class="fa-solid fa-chevron-down" style="position:absolute; right:14px; top:50%; transform:translateY(-50%); color:var(--text-muted); pointer-events:none;"></i>
            </div>

            <button type="submit" class="btn-gold" style="width:100%; border-radius:var(--radius-sm); padding:12px; margin-top:6px;">
                Confirm Reservation
            </button>
            <button type="button" onclick="closeReserveModal()" class="btn-outline" style="width:100%; border-radius:var(--radius-sm); padding:11px;">
                Cancel
            </button>
        </form>
    </div>
</div>

<!-- ── SACRED OFFERING MODAL ── -->
<div id="offeringModal" class="login-overlay" onclick="if(event.target===this) closeOfferingModal()">
    <div class="login-inner" style="max-width:460px; text-align:center;">
        <h2 style="font-family:var(--font-family); font-size:1.35rem; font-weight:700; margin-bottom:6px; color:var(--text-main);">Donate &amp; Support</h2>
        <p style="color:var(--text-dim); font-size:0.85rem; margin-bottom:18px; line-height:1.45;">Support the ministries and community projects of the IFI Cathedral. Thank you for your generosity!</p>

        <div class="offering-tabs">
            <button type="button" class="offering-tab active" onclick="switchOfferTab('gcash', this)"><i class="fa-solid fa-mobile-screen"></i> GCash</button>
            <button type="button" class="offering-tab" onclick="switchOfferTab('maya', this)"><i class="fa-solid fa-wallet"></i> Maya</button>
            <button type="button" class="offering-tab" onclick="switchOfferTab('bank', this)"><i class="fa-solid fa-building-columns"></i> Bank Transfer</button>
        </div>

        <!-- GCASH -->
        <div id="offer-gcash" class="offering-content active">
            <div class="offer-qr-wrap">
                <i class="fa-solid fa-qrcode"></i>
            </div>
            <div class="offer-details">
                <div class="offer-row">
                    <span style="color:var(--text-muted);">Account Name</span>
                    <span class="offer-value">IFI CATHEDRAL PARISH</span>
                </div>
                <div class="offer-row">
                    <span style="color:var(--text-muted);">Mobile Number</span>
                    <span class="offer-value">0917 123 4567</span>
                    <button type="button" class="btn-copy" onclick="copyToClipboard('09171234567')">Copy</button>
                </div>
            </div>
        </div>

        <!-- MAYA -->
        <div id="offer-maya" class="offering-content">
            <div class="offer-qr-wrap">
                <i class="fa-solid fa-qrcode" style="color:#00c853;"></i>
            </div>
            <div class="offer-details">
                <div class="offer-row">
                    <span style="color:var(--text-muted);">Account Name</span>
                    <span class="offer-value">IFI CATHEDRAL</span>
                </div>
                <div class="offer-row">
                    <span style="color:var(--text-muted);">Mobile Number</span>
                    <span class="offer-value">0912 999 0000</span>
                    <button type="button" class="btn-copy" onclick="copyToClipboard('09129990000')">Copy</button>
                </div>
            </div>
        </div>

        <!-- BANK -->
        <div id="offer-bank" class="offering-content">
            <div class="offer-details" style="margin-bottom:12px;">
                <div style="text-align:left; margin-bottom:8px;">
                    <strong style="color:var(--accent); font-size:0.92rem;">BDO UNIBANK</strong>
                </div>
                <div class="offer-row">
                    <span style="color:var(--text-muted);">Account Name</span>
                    <span class="offer-value">IFI CATHEDRAL PARISH COUNCIL</span>
                </div>
                <div class="offer-row">
                    <span style="color:var(--text-muted);">Account Number</span>
                    <span class="offer-value">0012 3456 7890</span>
                    <button type="button" class="btn-copy" onclick="copyToClipboard('001234567890')">Copy</button>
                </div>
            </div>
            <p style="font-size:0.75rem; color:var(--text-muted); font-style:italic;">Please include "Offering" or "Tithe" in your transaction notes.</p>
        </div>

        <button type="button" onclick="closeOfferingModal()" class="btn-outline" style="margin-top:18px; width:100%; border-radius:var(--radius-md);">
            Close
        </button>
    </div>
</div>

<!-- ── LEADER / CLERGY DETAIL MODAL ── -->
<div id="leaderModal" class="login-overlay" onclick="if(event.target===this) closeLeaderModal()">
    <div class="login-inner" style="max-width:420px; text-align:center;">
        <img id="modalImg" src="assets/img/2323.png" alt="Leader Photo" style="width:84px; height:84px; border-radius:50%; object-fit:cover; border:3px solid var(--accent); margin:0 auto 12px; box-shadow:var(--shadow-sm);">
        <h3 id="modalName" style="font-family:var(--font-family); font-size:1.2rem; font-weight:700; color:var(--text-main); margin-bottom:4px;">Leader Name</h3>
        <p id="modalSubtitle" style="color:var(--gold); font-weight:700; font-size:0.82rem; text-transform:uppercase; letter-spacing:0.04em; margin-bottom:4px; display:none;"></p>
        <p id="modalTitle" style="color:var(--text-dim); font-weight:600; font-size:0.8rem; margin-bottom:16px;">Role / Ministry</p>
        <div style="background:var(--bg-surface); padding:14px 18px; border-radius:var(--radius-md); border:1px solid var(--border); margin-bottom:18px;">
            <p style="font-size:0.88rem; color:var(--text-dim); line-height:1.55; margin:0;">Serving with dedication in the Diocese of Cavite ministry and spiritual family.</p>
        </div>
        <button type="button" onclick="closeLeaderModal()" class="btn-outline" style="width:100%; border-radius:var(--radius-md);">Close Profile</button>
    </div>
</div>

<!-- ── HISTORY DETAIL MODAL ── -->
<div id="historyModal" class="login-overlay" onclick="if(event.target===this) closeHistoryModal()">
    <div class="login-inner" style="max-width:480px; text-align:center;">
        <span id="hModalYear" style="font-size:0.78rem; font-weight:800; color:var(--accent); letter-spacing:0.1em; text-transform:uppercase;">Year</span>
        <h2 id="hModalTitle" style="font-family:var(--font-family); font-weight:700; margin:8px 0 16px; font-size:1.3rem; color:var(--text-main);">Era Milestone</h2>
        <div style="background:var(--bg-surface); padding:18px 22px; border-radius:var(--radius-md); text-align:left; border:1px solid var(--border);">
             <p id="hModalDesc" style="font-size:0.9rem; line-height:1.65; color:var(--text-main); margin:0;">Details about this historical milestone in the life of our parish.</p>
        </div>
        <button type="button" onclick="closeHistoryModal()" class="btn-outline" style="margin-top:20px; width:100%; border-radius:var(--radius-md);">Close</button>
    </div>
</div>

<!-- ── CATHEDRAL PORTAL LOGIN MODAL POPUP ── -->
<div id="cathedralLoginModal" class="cathedral-login-modal" role="dialog" aria-modal="true" aria-labelledby="loginModalTitle" style="display: none;">
    <div class="cathedral-login-modal-overlay" onclick="closeLoginModal()"></div>
    
    <!-- Left Stage: Interactive 3D IFI Crest Showcase -->
    <div class="login-modal-left-stage" id="loginModalLeftStage">
        <!-- Backdrop click dismiss zone behind interactive container -->
        <div class="login-stage-backdrop-dismiss" onclick="closeLoginModal()" title="Click to return"></div>
        
        <div class="interactive-3d-cross-wrapper" id="interactive3dCrossWrapper">
            <!-- WebGL 3D Canvas Container -->
            <div id="crossCanvasContainer" class="cross-canvas-container" title="Click and drag to rotate the 3D IFI Crest"></div>

            <!-- Sacred Inscription -->
            <div class="cross-interactive-overlay">
                <div class="silver-cross-caption">
                    <span class="cross-latin-motto">✦ PRO DEO ET PATRIA ✦</span>
                    <span class="cross-cathedral-name">Cathedral of St. Michael the Archangel</span>
                </div>
            </div>
        </div>
    </div>
    
    <div class="cathedral-login-modal-dialog">
        <!-- Golden Top Halo Accent -->
        <div class="login-modal-halo-accent"></div>

        <!-- Close Button -->
        <button type="button" class="login-modal-close-btn" onclick="closeLoginModal()" aria-label="Close Login Modal">
            <i class="fa-solid fa-xmark"></i>
        </button>

        <div class="login-modal-card-body">
            <!-- Brand Crest & Heading -->
            <div class="login-modal-header">
                <div class="login-modal-crest-wrap">
                    <img src="assets/img/2323.png" alt="IFI Cathedral Crest" class="login-modal-crest">
                </div>
                <h3 id="loginModalTitle" class="login-modal-title">
                    <span>Cathedral Portal</span> Sign In
                </h3>
                <p class="login-modal-subtitle">Iglesia Filipina Independiente &bull; Cathedral of St. Michael the Archangel</p>
                <div class="login-modal-divider"><span>☩</span></div>
            </div>

            <!-- Dynamic Alert Box for Inline Login Feedback -->
            <div id="modalLoginAlert" class="login-modal-alert" style="display: none;" role="alert">
                <i class="fa-solid fa-triangle-exclamation modal-alert-icon"></i>
                <div class="modal-alert-text" id="modalAlertText"></div>
            </div>

            <!-- Modal Login Form -->
            <form id="modalLoginForm" method="POST" action="app/controllers/authenticate.php" autocomplete="on">
                <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf ?? ''); ?>">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf ?? ''); ?>">
                <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($_GET['redirect'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">

                <div class="modal-form-group">
                    <label for="modalUsername" class="modal-form-label">Username or Email</label>
                    <div class="modal-input-wrap">
                        <i class="fa-solid fa-user modal-input-icon"></i>
                        <input 
                            type="text" 
                            id="modalUsername" 
                            name="user" 
                            class="modal-form-input" 
                            placeholder="Enter username or email" 
                            required 
                            maxlength="100"
                            autocomplete="username"
                            spellcheck="false"
                        >
                    </div>
                </div>

                <div class="modal-form-group">
                    <label for="modalPassword" class="modal-form-label">Password</label>
                    <div class="modal-input-wrap">
                        <i class="fa-solid fa-lock modal-input-icon"></i>
                        <input 
                            type="password" 
                            id="modalPassword" 
                            name="password" 
                            class="modal-form-input" 
                            placeholder="Enter your account password" 
                            required 
                            maxlength="72"
                            autocomplete="current-password"
                        >
                        <button type="button" class="modal-pw-toggle" id="modalPwToggle" aria-label="Toggle password visibility" title="Show/Hide password">
                            <i class="fa-regular fa-eye" id="modalPwIcon"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn-modal-login-submit" id="modalSubmitBtn">
                    <span id="modalSubmitText">Sign In to Portal</span>
                    <i class="fa-solid fa-arrow-right" id="modalSubmitIcon"></i>
                </button>
            </form>

            <!-- Quick Action Links -->
            <div class="modal-quick-links">
                <a href="register.php" class="modal-link-btn modal-link-register">
                    <i class="fa-solid fa-user-plus"></i>
                    <span>Register as Member</span>
                </a>
                <a href="guest_access.php" class="modal-link-btn modal-link-guest">
                    <i class="fa-solid fa-compass"></i>
                    <span>Explore as Guest</span>
                </a>
            </div>

            <!-- Access Notice -->
            <div class="modal-footer-notice">
                <p><i class="fa-solid fa-shield-halved"></i> Authorized clergy, parish officers &amp; registered members</p>
            </div>
        </div>
    </div>
</div>

<!-- ── FOOTER ── -->
<footer class="footer">
    <div class="container">
        <div class="footer-logo">
            <img src="assets/img/2323.png" alt="Seal">
            <span>IFI CATHEDRAL PARISH</span>
        </div>
        <p>&copy; <?php echo date('Y'); ?> Iglesia Filipina Independiente &bull; Cathedral Parish. All Rights Reserved.</p>
        <p style="margin-top:4px; font-size:0.75rem; color:var(--text-muted);">Proclaiming the Gospel of Christ with Dignity, Justice, and Peace.</p>
    </div>
</footer>

<!-- ── FLOATING THEME SWITCHER (BOTTOM RIGHT) ── -->
<button type="button" id="themeToggleBtn" class="floating-theme-toggle" title="Switch Theme (Light / Dark)" aria-label="Switch Theme">
    <i id="themeToggleIcon" class="fa-regular fa-sun"></i>
</button>

<script src="assets/js/three.min.js"></script>
<script>
    function toggleLogin(show) {
        openLoginModal();
    }

    function openLeaderModal(data) {
        if (data instanceof HTMLElement) {
            const raw = data.getAttribute('data-leader');
            if (raw) {
                try { data = JSON.parse(raw); } catch (e) { console.error('Error parsing leader data:', e); return; }
            }
        }
        if (!data) return;

        const modal = document.getElementById('leaderModal');
        if (!modal) return;
        const nameEl = document.getElementById('modalName');
        if (nameEl) nameEl.innerText = data.name || '';
        const titleEl = document.getElementById('modalTitle');
        if (titleEl) titleEl.innerText = data.title || '';
        const sub = document.getElementById('modalSubtitle');
        if (sub) {
            if (data.subtitle) {
                sub.innerText = data.subtitle;
                sub.style.display = 'block';
            } else {
                sub.style.display = 'none';
            }
        }
        const imgEl = document.getElementById('modalImg');
        if (imgEl) imgEl.src = data.image_path ? 'storage/' + data.image_path : 'assets/img/2323.png';
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
    function closeLeaderModal() {
        const modal = document.getElementById('leaderModal');
        if (modal) modal.classList.remove('active');
        document.body.style.overflow = '';
    }

    function openMerchModal(item) {
        if (item instanceof HTMLElement) {
            const raw = item.getAttribute('data-item');
            if (raw) {
                try { item = JSON.parse(raw); } catch (e) { console.error('Error parsing merch data:', e); return; }
            }
        }
        if (!item) return;

        const modal = document.getElementById('merchModal');
        if (!modal) return;
        const price = parseFloat(item.price || 0).toLocaleString('en-PH', { minimumFractionDigits: 2 });
        const nameEl = document.getElementById('mModalName');
        if (nameEl) nameEl.innerText = item.name || '';
        const priceEl = document.getElementById('mModalPrice');
        if (priceEl) priceEl.innerText = '₱' + price;
        const descEl = document.getElementById('mModalDesc');
        if (descEl) descEl.innerText = item.description || 'Visit our cathedral office for more details on this commemorative item.';
        const imgEl = document.getElementById('mModalImg');
        if (imgEl) imgEl.src = item.photo_path || 'assets/img/2323.png';
        
        // Setup Reserve Button
        const rBtn = document.getElementById('reserveBtn');
        if (rBtn) {
            rBtn.onclick = () => {
                closeMerchModal();
                openReserveModal(item);
            };
        }

        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
    function closeMerchModal() {
        document.getElementById('merchModal').classList.remove('active');
        document.body.style.overflow = '';
    }

    function openReserveModal(item) {
        const modal = document.getElementById('reserveFormModal');
        const price = parseFloat(item.price).toLocaleString('en-PH', { minimumFractionDigits: 2 });
        document.getElementById('reserveItemId').value = item.id;
        document.getElementById('reserveItemName').value = item.name;
        document.getElementById('formItemName').innerText = item.name;
        document.getElementById('formItemPrice').innerText = 'Price: ₱' + price;
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
    function closeReserveModal() {
        document.getElementById('reserveFormModal').classList.remove('active');
        document.body.style.overflow = '';
    }

    function openOfferingModal() {
        document.getElementById('offeringModal').classList.add('active');
        document.body.style.overflow = 'hidden';
    }
    function closeOfferingModal() {
        document.getElementById('offeringModal').classList.remove('active');
        document.body.style.overflow = '';
    }
    function switchOfferTab(target, btn) {
        document.querySelectorAll('.offering-content').forEach(c => c.classList.remove('active'));
        document.querySelectorAll('.offering-tab').forEach(t => t.classList.remove('active'));
        document.getElementById('offer-' + target).classList.add('active');
        btn.classList.add('active');
    }

    function copyToClipboard(text) {
        navigator.clipboard.writeText(text).then(() => {
            alert('Copied to clipboard: ' + text);
        });
    }

    let currentBulletinNotice = null;

    function openBulletinModal(data) {
        if (data instanceof HTMLElement) {
            const raw = data.getAttribute('data-bulletin') || data.getAttribute('data-post');
            if (raw) {
                try { data = JSON.parse(raw); } catch (e) { console.error('Error parsing bulletin data:', e); return; }
            }
        }
        if (!data) return;

        currentBulletinNotice = data;
        const modal = document.getElementById('bulletinModal');
        if (!modal) return;
        
        const titleEl = document.getElementById('bModalTitle');
        if (titleEl) titleEl.innerText = data.title || 'Parish Notice';
        const subEl = document.getElementById('bModalSubtitle');
        if (subEl) subEl.innerText = data.subtitle || 'No details provided.';
        
        const headerEl = document.getElementById('bModalHeader');
        if (headerEl) {
            headerEl.style.background = 'linear-gradient(90deg, var(--gold, #c9a84c), #ffd700)';
        }
        
        const tagEl = document.getElementById('bModalTag');
        if (tagEl) {
            tagEl.style.borderColor = 'var(--gold-border, rgba(201, 168, 76, 0.35))';
            tagEl.style.color = 'var(--gold, #c9a84c)';
            tagEl.style.background = 'var(--gold-subtle, rgba(201, 168, 76, 0.12))';
            tagEl.innerHTML = '';
            const tagIcon = document.createElement('i');
            tagIcon.style.color = 'var(--gold, #c9a84c)';
            if (data.chapel_name) {
                tagIcon.className = 'fa-solid fa-place-of-worship';
                tagEl.appendChild(tagIcon);
                tagEl.appendChild(document.createTextNode(' ' + data.chapel_name));
            } else {
                tagIcon.className = 'fa-solid fa-church';
                tagEl.appendChild(tagIcon);
                tagEl.appendChild(document.createTextNode(' Cathedral Parish-Wide'));
            }
        }

        const dateEl = document.getElementById('bModalDate');
        if (dateEl) {
            if (data.created_at) {
                try {
                    const d = new Date(data.created_at);
                    dateEl.innerHTML = '<i class="fa-regular fa-calendar-days"></i> ' + d.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
                } catch(e) {
                    dateEl.innerHTML = '<i class="fa-regular fa-calendar-days"></i> ' + new Date().toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
                }
            } else {
                dateEl.innerHTML = '<i class="fa-regular fa-calendar-days"></i> ' + new Date().toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
            }
        }
        
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeBulletinModal() {
        const modal = document.getElementById('bulletinModal');
        if (modal) modal.classList.remove('active');
        document.body.style.overflow = '';
    }

    function copyBulletinNotice() {
        if (!currentBulletinNotice) return;
        const audience = currentBulletinNotice.chapel_name || 'Cathedral Parish-Wide';
        const dateStr = document.getElementById('bModalDate')?.innerText.trim() || '';
        const text = `IFI CATHEDRAL ANNOUNCEMENT\nAudience: ${audience}\nDate: ${dateStr}\n\n${currentBulletinNotice.title}\n\n${currentBulletinNotice.subtitle}\n\n-- Office of the Cathedral Dean & Parish Ministry`;
        
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(() => {
                const btn = document.getElementById('bModalCopyBtn');
                if (btn) {
                    const orig = btn.innerHTML;
                    btn.innerHTML = '<i class="fa-solid fa-check"></i> Copied!';
                    setTimeout(() => { btn.innerHTML = orig; }, 2000);
                }
            }).catch(() => {
                alert('Copied to clipboard:\n\n' + text);
            });
        } else {
            alert('Copied to clipboard:\n\n' + text);
        }
    }

    function filterBulletin(scope, btn) {
        const pills = document.querySelectorAll('.bulletin-pill');
        pills.forEach(p => {
            p.classList.remove('active');
            p.setAttribute('aria-selected', 'false');
        });
        if (btn) {
            btn.classList.add('active');
            btn.setAttribute('aria-selected', 'true');
        }
        
        const posts = document.querySelectorAll('.bulletin-post');
        let visibleCount = 0;
        posts.forEach(post => {
            const cId = post.getAttribute('data-chapel');
            let show = false;
            if (scope === 'all') {
                show = true;
            } else if (scope === 'parish') {
                show = (cId === '0' || !cId);
            } else {
                show = (cId === String(scope));
            }
            if (show) {
                post.style.display = 'flex';
                visibleCount++;
            } else {
                post.style.display = 'none';
            }
        });
        
        const emptyEl = document.getElementById('bulletinEmptyState');
        if (emptyEl) {
            emptyEl.style.display = visibleCount === 0 ? 'block' : 'none';
        }
    }

    // Escape key to close modals
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeBulletinModal();
            closeChapelModal();
        }
    });

    function openChapelModal(chapel) {
        if (chapel instanceof HTMLElement) {
            const raw = chapel.getAttribute('data-chapel');
            if (raw) {
                try { chapel = JSON.parse(raw); } catch (e) { console.error('Error parsing chapel data:', e); return; }
            }
        }
        if (!chapel) return;

        const modal = document.getElementById('chapelModal');
        if (!modal) return;
        
        const titleEl = document.getElementById('cModalTitle');
        if (titleEl) titleEl.innerText = chapel.chapel_name || 'Community Chapel';
        const patronEl = document.getElementById('cModalPatron');
        if (patronEl) patronEl.innerHTML = '<i class="fa-solid fa-cross"></i> ' + (chapel.patron_saint && chapel.patron_saint.trim() ? chapel.patron_saint : 'Diocesan Patron');
        const capEl = document.getElementById('cModalCapacity');
        if (capEl) capEl.innerHTML = '<i class="fa-solid fa-chair"></i> Seating: ' + (chapel.seating_capacity ? chapel.seating_capacity + ' worshippers' : '50 worshippers');
        const schedEl = document.getElementById('cModalSchedule');
        if (schedEl) schedEl.innerHTML = '<strong>Sunday Holy Service:</strong> ' + (chapel.sunday_service_time || 'Check Parish Schedule');
        const locEl = document.getElementById('cModalLocation');
        if (locEl) locEl.innerHTML = chapel.location && chapel.location.trim() ? chapel.location : 'Located within the Cathedral Parish territorial vicinity. Contact the Parish Office for guidance.';
        
        const headerEl = document.getElementById('cModalHeader');
        if (headerEl) headerEl.style.background = 'linear-gradient(90deg, var(--gold, #c9a84c), #ffd700)';
        
        const iconEl = document.getElementById('cModalIcon');
        if (iconEl) {
            iconEl.style.color = 'var(--gold, #c9a84c)';
            iconEl.style.borderColor = 'var(--gold-border, rgba(201, 168, 76, 0.35))';
            iconEl.style.background = 'var(--gold-subtle, rgba(201, 168, 76, 0.12))';
        }
        
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeChapelModal() {
        const modal = document.getElementById('chapelModal');
        if (modal) modal.classList.remove('active');
        document.body.style.overflow = '';
    }

    function openHistoryModal(data) {
        if (data instanceof HTMLElement) {
            const raw = data.getAttribute('data-history');
            if (raw) {
                try { data = JSON.parse(raw); } catch (e) { console.error('Error parsing history data:', e); return; }
            }
        }
        if (!data) return;

        const modal = document.getElementById('historyModal');
        if (!modal) return;
        const yearEl = document.getElementById('hModalYear');
        if (yearEl) yearEl.innerText = data.year || '';
        const titleEl = document.getElementById('hModalTitle');
        if (titleEl) titleEl.innerText = data.title || '';
        const descEl = document.getElementById('hModalDesc');
        if (descEl) descEl.innerText = data.description || 'This era marks a significant chapter in the building of our spiritual sanctuary.';
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
    function closeHistoryModal() {
        const modal = document.getElementById('historyModal');
        if (modal) modal.classList.remove('active');
        document.body.style.overflow = '';
    }

    window.openLeaderModal = openLeaderModal;
    window.closeLeaderModal = closeLeaderModal;
    window.openMerchModal = openMerchModal;
    window.closeMerchModal = closeMerchModal;
    window.openBulletinModal = openBulletinModal;
    window.openChapelModal = openChapelModal;
    window.closeChapelModal = closeChapelModal;
    window.openHistoryModal = openHistoryModal;
    window.closeHistoryModal = closeHistoryModal;

    // Scroll Reveal Effect
    const reveal = () => {
        const items = document.querySelectorAll('.scroll-reveal');
        items.forEach(item => {
            const top = item.getBoundingClientRect().top;
            if (top < window.innerHeight - 50) item.classList.add('active');
        });
    };
    window.addEventListener('scroll', reveal);
    window.addEventListener('load', reveal);

    // Navbar Scroll Shadow
    window.addEventListener('scroll', () => {
        const header = document.querySelector('.header');
        if (header) {
            if (window.scrollY > 15) {
                header.classList.add('scrolled');
            } else {
                header.classList.remove('scrolled');
            }
        }
    });

    // Unified Theme Engine (Single toggle sync with inside dashboard)
    (function initUnifiedTheme() {
        const attachTheme = () => {
            const btn = document.getElementById('themeToggleBtn');
            const icon = document.getElementById('themeToggleIcon');
            
            const updateIcon = () => {
                const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
                if (icon) {
                    icon.className = isDark ? 'fa-regular fa-sun' : 'fa-regular fa-moon';
                }
            };
            
            updateIcon();
            
            if (btn) {
                btn.onclick = () => {
                    const current = document.documentElement.getAttribute('data-theme') || 'light';
                    const next = (current === 'dark') ? 'light' : 'dark';
                    document.documentElement.setAttribute('data-theme', next);
                    localStorage.setItem('theme', next);
                    localStorage.setItem('portalTheme', next);
                    updateIcon();
                };
            }
        };

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', attachTheme);
        } else {
            attachTheme();
        }
    })();

    // Auto-open login modal if error, notice, or open_login parameter is present in URL
    window.addEventListener('load', () => {
        const params = new URLSearchParams(window.location.search);
        if (params.has('error') || params.has('login_modal') || params.has('open_login') || params.has('notice')) {
            openLoginModal();
            const alertBox = document.getElementById('modalLoginAlert');
            const alertText = document.getElementById('modalAlertText');
            if (alertBox && alertText) {
                if (params.has('error')) {
                    const err = params.get('error');
                    let msg = 'Authentication error. Please verify your credentials.';
                    if (err === 'session') msg = 'Your session has expired. Please sign in again.';
                    else if (err === 'wrong_pass') msg = 'Invalid username or password. Please verify credentials.';
                    else if (err === 'csrf') msg = 'Security validation failed. Please try again.';
                    else if (err === 'empty_all') msg = 'Please enter both username and password.';
                    else if (err === 'empty_user') msg = 'Username or email address is required.';
                    else if (err === 'empty_pass') msg = 'Password is required.';
                    else if (err === 'account_rejected') msg = 'Registration could not be verified by Administration.';
                    else if (err === 'locked' || err === 'ip_locked') {
                        const wait = parseInt(params.get('wait') || '15', 10);
                        startPortalModalLockout(wait * 60);
                        return;
                    }
                    alertText.textContent = msg;
                    alertBox.style.display = 'flex';
                } else if (params.has('notice')) {
                    const notice = params.get('notice');
                    let msg = 'Notice: Please sign in with your credentials.';
                    if (notice === 'registration_submitted') msg = 'Registration submitted! The Parish Office will review and approve your account shortly.';
                    else if (notice === 'pending_approval') msg = 'Your account registration is awaiting verification by the Cathedral Parish Office.';
                    alertText.textContent = msg;
                    alertBox.style.background = 'rgba(197, 160, 89, 0.15)';
                    alertBox.style.borderColor = 'rgba(197, 160, 89, 0.35)';
                    alertBox.style.color = 'var(--gold, #c5a059)';
                    alertBox.style.display = 'flex';
                }
            }
        }
    });

    // ── CATHEDRAL LOGIN MODAL FUNCTIONS & 3D CROSS ENGINE ──
    let threeCross = {
        renderer: null,
        scene: null,
        camera: null,
        crossGroup: null,
        haloRing: null,
        particles: null,
        cursorLight: null,
        animFrameId: null,
        isRunning: false,
        autoSpin: true,
        isDragging: false,
        prevPointer: { x: 0, y: 0 },
        velocity: { x: 0, y: 0.012 },
        targetRot: { x: 0.08, y: 0 },
        idleTimer: null
    };

    function init3DCross() {
        const container = document.getElementById('crossCanvasContainer');
        if (!container || typeof THREE === 'undefined') return;

        const width = container.clientWidth || 420;
        const height = container.clientHeight || 440;

        // Scene
        const scene = new THREE.Scene();
        threeCross.scene = scene;

        // Camera
        const camera = new THREE.PerspectiveCamera(45, width / height, 0.1, 100);
        camera.position.set(0, 0, 11.5);
        threeCross.camera = camera;

        // WebGL Renderer with Alpha Transparency
        const renderer = new THREE.WebGLRenderer({
            antialias: true,
            alpha: true,
            powerPreference: 'high-performance'
        });
        renderer.setSize(width, height);
        renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
        renderer.toneMapping = THREE.ACESFilmicToneMapping;
        renderer.toneMappingExposure = 1.15;
        container.innerHTML = '';
        container.appendChild(renderer.domElement);
        threeCross.renderer = renderer;

        // Lighting Rig
        const ambient = new THREE.AmbientLight(0x475569, 1.4);
        scene.add(ambient);

        const keyLight = new THREE.DirectionalLight(0xffffff, 2.8);
        keyLight.position.set(5, 7, 7);
        scene.add(keyLight);

        const rimLight = new THREE.DirectionalLight(0x93c5fd, 2.2);
        rimLight.position.set(-6, 5, -5);
        scene.add(rimLight);

        const goldAccent = new THREE.DirectionalLight(0xf59e0b, 1.0);
        goldAccent.position.set(4, -5, -4);
        scene.add(goldAccent);

        const cursorLight = new THREE.PointLight(0xffffff, 2.4, 18);
        cursorLight.position.set(0, 0, 5);
        scene.add(cursorLight);
        threeCross.cursorLight = cursorLight;

        // Materials: Imperial Gold & Sterling Silver Chrome
        const goldBodyMat = new THREE.MeshStandardMaterial({
            color: 0xd4af37,
            metalness: 0.88,
            roughness: 0.22,
            flatShading: false
        });

        const mirrorChromeMat = new THREE.MeshStandardMaterial({
            color: 0xf6d06d,
            metalness: 0.94,
            roughness: 0.14
        });

        // Root 3D IFI Crest Group
        const crossGroup = new THREE.Group();
        scene.add(crossGroup);
        threeCross.crossGroup = crossGroup;

        // 1:1 Traced Silhouette of Official IFI Crest (assets/img/2323.png)
        const crestContour = [
            [0.0, 2.65], [0.193, 2.515], [0.359, 2.294], [0.58, 2.073], [0.746, 1.852],
            [0.802, 1.68], [0.691, 1.52], [1.189, 1.41], [1.38, 1.24], [1.465, 1.02],
            [1.465, 0.746], [1.38, 0.45], [1.15, 0.193], [1.189, -0.138], [1.244, -0.47],
            [1.28, -0.802], [1.189, -1.023], [1.05, -1.28], [0.857, -1.576], [0.636, -1.797],
            [0.359, -2.018], [0.083, -2.239], [0.083, -2.48], [-0.14, -2.48], [-0.138, -2.239],
            [-0.47, -2.018], [-0.802, -1.797], [-0.967, -1.576], [-1.15, -1.30], [-1.299, -1.023],
            [-1.354, -0.75], [-1.299, -0.359], [-1.244, 0.083], [-1.45, 0.35], [-1.576, 0.70],
            [-1.54, 1.02], [-1.41, 1.299], [-1.25, 1.41], [-0.857, 1.52], [-0.912, 1.68],
            [-0.82, 1.88], [-0.636, 2.073], [-0.45, 2.30], [-0.22, 2.52]
        ];

        const crestShape = new THREE.Shape();
        crestShape.moveTo(crestContour[0][0], crestContour[0][1]);
        for (let i = 1; i < crestContour.length; i++) {
            crestShape.lineTo(crestContour[i][0], crestContour[i][1]);
        }
        crestShape.closePath();

        const extrudeSettings = {
            steps: 1,
            depth: 0.16,
            bevelEnabled: true,
            bevelThickness: 0.045,
            bevelSize: 0.04,
            bevelOffset: -0.02,
            bevelSegments: 6
        };

        const crestGeo = new THREE.ExtrudeGeometry(crestShape, extrudeSettings);
        // Center only along Z so X/Y match the 6.8x6.8 logo plane 1:1
        crestGeo.translate(0, 0, -0.08);
        const crestCoreMesh = new THREE.Mesh(crestGeo, goldBodyMat);
        crossGroup.add(crestCoreMesh);

        // Generate 2048x2048 Ultra-HD Vector IFI Crest Texture + 3D Relief Bump Map
        function buildUltraHDIFICrest(size) {
            const colorCanvas = document.createElement('canvas');
            colorCanvas.width = size;
            colorCanvas.height = size;
            const ctx = colorCanvas.getContext('2d');
            const s = size / 1024;
            ctx.save();
            ctx.scale(s, s);

            // Helper for gold gradient
            const goldGrad = ctx.createLinearGradient(250, 90, 770, 890);
            goldGrad.addColorStop(0.0, '#FFF3A1');
            goldGrad.addColorStop(0.25, '#F5C445');
            goldGrad.addColorStop(0.5, '#D49B1F');
            goldGrad.addColorStop(0.75, '#F7D060');
            goldGrad.addColorStop(1.0, '#9E6B0E');

            const brightYellowGrad = ctx.createLinearGradient(360, 100, 660, 350);
            brightYellowGrad.addColorStop(0.0, '#FFF78C');
            brightYellowGrad.addColorStop(0.5, '#FFD000');
            brightYellowGrad.addColorStop(1.0, '#E08E00');

            // ── 1. SIDE RIBBONS (INFULAE) ──
            function drawSideRibbon(dir) {
                ctx.save();
                ctx.translate(512, 0);
                ctx.scale(dir, 1);

                // Upper orange-red fold
                ctx.beginPath();
                ctx.moveTo(85, 325);
                ctx.bezierCurveTo(135, 290, 185, 285, 215, 330);
                ctx.bezierCurveTo(190, 345, 145, 345, 95, 350);
                ctx.closePath();
                const ribRed = ctx.createLinearGradient(85, 290, 215, 350);
                ribRed.addColorStop(0, '#B91C1C');
                ribRed.addColorStop(0.6, '#EA580C');
                ribRed.addColorStop(1, '#F97316');
                ctx.fillStyle = ribRed;
                ctx.fill();
                ctx.lineWidth = 6;
                ctx.strokeStyle = goldGrad;
                ctx.stroke();

                // Outer curling yellow-gold tail
                ctx.beginPath();
                ctx.moveTo(195, 320);
                ctx.bezierCurveTo(240, 345, 242, 425, 200, 465);
                ctx.lineTo(172, 438);
                ctx.bezierCurveTo(195, 400, 192, 355, 165, 338);
                ctx.closePath();
                ctx.fillStyle = brightYellowGrad;
                ctx.fill();
                ctx.lineWidth = 6;
                ctx.strokeStyle = goldGrad;
                ctx.stroke();

                ctx.restore();
            }
            drawSideRibbon(-1);
            drawSideRibbon(1);

            // ── 2. BISHOP'S MITRE (TOP) ──
            ctx.save();
            ctx.beginPath();
            ctx.moveTo(512, 96);
            ctx.quadraticCurveTo(595, 175, 652, 256);
            ctx.lineTo(604, 344);
            ctx.quadraticCurveTo(512, 352, 420, 344);
            ctx.lineTo(372, 256);
            ctx.quadraticCurveTo(429, 175, 512, 96);
            ctx.closePath();
            ctx.fillStyle = brightYellowGrad;
            ctx.fill();

            // Clip inside Mitre for red bands (titulus & circulus)
            ctx.clip();

            // Horizontal red base band
            const mitreRed = ctx.createLinearGradient(400, 96, 620, 350);
            mitreRed.addColorStop(0, '#EF4444');
            mitreRed.addColorStop(0.5, '#DC2626');
            mitreRed.addColorStop(1, '#991B1B');
            ctx.fillStyle = mitreRed;
            ctx.fillRect(360, 306, 304, 46);

            // Vertical center red pillar band
            ctx.fillRect(482, 90, 60, 260);

            // Gold trim lines inside mitre
            ctx.strokeStyle = goldGrad;
            ctx.lineWidth = 5;
            ctx.beginPath();
            ctx.moveTo(482, 96); ctx.lineTo(482, 306);
            ctx.moveTo(542, 96); ctx.lineTo(542, 306);
            ctx.moveTo(370, 306); ctx.lineTo(654, 306);
            ctx.stroke();
            ctx.restore();

            // Mitre Outer Gold Border
            ctx.beginPath();
            ctx.moveTo(512, 96);
            ctx.quadraticCurveTo(595, 175, 652, 256);
            ctx.lineTo(604, 344);
            ctx.quadraticCurveTo(512, 352, 420, 344);
            ctx.lineTo(372, 256);
            ctx.quadraticCurveTo(429, 175, 512, 96);
            ctx.closePath();
            ctx.lineWidth = 9;
            ctx.strokeStyle = goldGrad;
            ctx.stroke();

            // ── 3. OUTER WHITE SCROLL BORDER & SHIELD BACKING ──
            ctx.save();
            ctx.beginPath();
            ctx.moveTo(318, 430);
            ctx.bezierCurveTo(300, 585, 340, 745, 512, 836);
            ctx.bezierCurveTo(684, 745, 724, 585, 706, 430);
            ctx.closePath();
            ctx.fillStyle = '#FFFDF7';
            ctx.fill();
            ctx.lineWidth = 9;
            ctx.strokeStyle = goldGrad;
            ctx.stroke();
            ctx.restore();

            // ── 4. INNER HERALDIC SHIELD (LEFT RED / RIGHT BLUE) ──
            function traceInnerShield() {
                ctx.beginPath();
                ctx.moveTo(352, 432);
                ctx.bezierCurveTo(345, 575, 382, 710, 512, 788);
                ctx.bezierCurveTo(642, 710, 679, 575, 672, 432);
                ctx.closePath();
            }

            // Left Half: Crimson Red + Philippine Flag + Broken Chains
            ctx.save();
            traceInnerShield();
            ctx.clip();

            // Left red background
            const leftRedGrad = ctx.createLinearGradient(350, 430, 512, 790);
            leftRedGrad.addColorStop(0, '#DC2626');
            leftRedGrad.addColorStop(1, '#7F1D1D');
            ctx.fillStyle = leftRedGrad;
            ctx.fillRect(330, 420, 182, 380);

            // Draped Philippine Flag on Left Shield
            // White Triangle of Flag
            ctx.beginPath();
            ctx.moveTo(458, 462);
            ctx.lineTo(376, 548);
            ctx.lineTo(458, 592);
            ctx.closePath();
            ctx.fillStyle = '#FFFFFF';
            ctx.fill();
            ctx.lineWidth = 3;
            ctx.strokeStyle = '#D4AF37';
            ctx.stroke();

            // Golden Sun inside White Triangle
            const sunX = 425, sunY = 536;
            ctx.save();
            ctx.translate(sunX, sunY);
            ctx.fillStyle = '#F59E0B';
            for (let r = 0; r < 8; r++) {
                ctx.save();
                ctx.rotate((r * Math.PI) / 4);
                ctx.beginPath();
                ctx.moveTo(-3, 0);
                ctx.lineTo(0, -22);
                ctx.lineTo(3, 0);
                ctx.closePath();
                ctx.fill();
                ctx.restore();
            }
            ctx.beginPath();
            ctx.arc(0, 0, 10, 0, Math.PI * 2);
            ctx.fillStyle = '#FACC15';
            ctx.fill();
            ctx.restore();

            // 3 Golden Stars in Triangle corners
            const starCoords = [[446, 484], [392, 546], [446, 576]];
            ctx.fillStyle = '#F59E0B';
            starCoords.forEach(([sx, sy]) => {
                ctx.beginPath();
                ctx.arc(sx, sy, 4.5, 0, Math.PI * 2);
                ctx.fill();
            });

            // Flag Blue & Red Flowing Stripes
            ctx.beginPath();
            ctx.moveTo(376, 548);
            ctx.bezierCurveTo(405, 595, 445, 600, 488, 638);
            ctx.lineTo(425, 735);
            ctx.bezierCurveTo(395, 675, 365, 620, 376, 548);
            ctx.closePath();
            ctx.fillStyle = '#1D4ED8';
            ctx.fill();

            ctx.beginPath();
            ctx.moveTo(458, 462);
            ctx.lineTo(458, 592);
            ctx.bezierCurveTo(485, 615, 495, 645, 488, 685);
            ctx.lineTo(450, 645);
            ctx.closePath();
            ctx.fillStyle = '#EF4444';
            ctx.fill();

            // Broken Chains at bottom-left
            ctx.strokeStyle = '#78350F';
            ctx.lineWidth = 7;
            const chainLinks = [[382, 678], [408, 684], [435, 688], [462, 684], [448, 712], [448, 738]];
            chainLinks.forEach(([cx, cy]) => {
                ctx.beginPath();
                ctx.ellipse(cx, cy, 13, 8, 0.25, 0, Math.PI * 2);
                ctx.stroke();
                ctx.strokeStyle = '#D97706';
                ctx.lineWidth = 3;
                ctx.stroke();
                ctx.strokeStyle = '#78350F';
                ctx.lineWidth = 7;
            });

            // Right Half: Royal Blue + Bible + Chalice + Sacred Host
            const rightBlueGrad = ctx.createLinearGradient(512, 430, 680, 790);
            rightBlueGrad.addColorStop(0, '#1E3A8A');
            rightBlueGrad.addColorStop(1, '#0F172A');
            ctx.fillStyle = rightBlueGrad;
            ctx.fillRect(512, 420, 180, 380);

            // Open Holy Bible (White pages with gold trim)
            ctx.save();
            ctx.fillStyle = '#FFFBEB';
            ctx.strokeStyle = '#D4AF37';
            ctx.lineWidth = 4;
            ctx.beginPath();
            ctx.roundRect(534, 628, 108, 72, 6);
            ctx.fill();
            ctx.stroke();
            ctx.beginPath();
            ctx.moveTo(588, 628);
            ctx.lineTo(588, 700);
            ctx.stroke();
            ctx.restore();

            // Golden Chalice in front of Bible
            ctx.save();
            ctx.fillStyle = brightYellowGrad;
            ctx.strokeStyle = '#92400E';
            ctx.lineWidth = 4;
            // Chalice Cup
            ctx.beginPath();
            ctx.moveTo(548, 588);
            ctx.lineTo(630, 588);
            ctx.bezierCurveTo(626, 648, 552, 648, 548, 588);
            ctx.closePath();
            ctx.fill();
            ctx.stroke();
            // Chalice Stem & Node
            ctx.fillRect(581, 638, 16, 56);
            ctx.strokeRect(581, 638, 16, 56);
            ctx.beginPath();
            ctx.ellipse(589, 664, 16, 8, 0, 0, Math.PI * 2);
            ctx.fill();
            ctx.stroke();
            // Chalice Base
            ctx.beginPath();
            ctx.moveTo(552, 728);
            ctx.quadraticCurveTo(589, 692, 626, 728);
            ctx.closePath();
            ctx.fill();
            ctx.stroke();
            ctx.restore();

            // Sacred White Host above Chalice
            ctx.save();
            ctx.beginPath();
            ctx.arc(589, 532, 40, 0, Math.PI * 2);
            ctx.fillStyle = '#FFFDF5';
            ctx.fill();
            ctx.lineWidth = 4;
            ctx.strokeStyle = goldGrad;
            ctx.stroke();
            // Green/Gold Emblem inside Host
            ctx.strokeStyle = '#15803D';
            ctx.lineWidth = 4.5;
            ctx.beginPath();
            ctx.moveTo(589, 506); ctx.lineTo(589, 556);
            ctx.moveTo(569, 524); ctx.lineTo(609, 524);
            ctx.moveTo(573, 514); ctx.lineTo(605, 548);
            ctx.moveTo(605, 514); ctx.lineTo(573, 548);
            ctx.stroke();
            ctx.restore();

            ctx.restore(); // End Inner Shield Clip

            // Inner Shield Gold Frame
            traceInnerShield();
            ctx.lineWidth = 10;
            ctx.strokeStyle = goldGrad;
            ctx.stroke();

            // ── 5. UPPER ARCHED WHITE SCROLL ("I   F   I") ──
            ctx.save();
            ctx.beginPath();
            ctx.moveTo(324, 360);
            ctx.quadraticCurveTo(512, 326, 700, 360);
            ctx.lineTo(690, 412);
            ctx.quadraticCurveTo(512, 380, 334, 412);
            ctx.closePath();
            ctx.fillStyle = '#FFFDF7';
            ctx.fill();
            ctx.lineWidth = 7;
            ctx.strokeStyle = goldGrad;
            ctx.stroke();

            // "I  F  I" Gold Typography
            ctx.fillStyle = '#B45309';
            ctx.font = 'bold 46px Georgia, "Times New Roman", serif';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillText('I     F     I', 512, 374);
            ctx.strokeStyle = '#F59E0B';
            ctx.lineWidth = 1.5;
            ctx.strokeText('I     F     I', 512, 374);
            ctx.restore();

            // ── 6. GOLDEN BALANCE BEAM ("Pro Deo et Patria") ──
            ctx.save();
            ctx.beginPath();
            ctx.moveTo(312, 418);
            ctx.quadraticCurveTo(512, 388, 712, 418);
            ctx.lineTo(706, 448);
            ctx.quadraticCurveTo(512, 418, 318, 448);
            ctx.closePath();
            ctx.fillStyle = goldGrad;
            ctx.fill();
            ctx.lineWidth = 4;
            ctx.strokeStyle = '#78350F';
            ctx.stroke();

            // "Pro Deo et Patria" Text
            ctx.fillStyle = '#1C1917';
            ctx.font = 'bold 23px Georgia, "Times New Roman", serif';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillText('Pro Deo et Patria', 512, 421);
            ctx.restore();

            // ── 7. HANGING SCALES OF JUSTICE (LEFT & RIGHT) ──
            function drawScale(cx) {
                ctx.save();
                ctx.strokeStyle = goldGrad;
                ctx.lineWidth = 5;
                // 3 Suspension Chains
                [-34, 0, 34].forEach(dx => {
                    ctx.beginPath();
                    ctx.moveTo(cx, 436);
                    ctx.lineTo(cx + dx, 632);
                    ctx.stroke();
                });
                // Golden Scale Pan
                ctx.beginPath();
                ctx.moveTo(cx - 44, 630);
                ctx.lineTo(cx + 44, 630);
                ctx.bezierCurveTo(cx + 36, 666, cx - 36, 666, cx - 44, 630);
                ctx.closePath();
                ctx.fillStyle = brightYellowGrad;
                ctx.fill();
                ctx.lineWidth = 5;
                ctx.strokeStyle = '#92400E';
                ctx.stroke();
                ctx.restore();
            }
            drawScale(336);
            drawScale(688);

            // ── 8. CENTRAL GOLDEN SWORD ──
            ctx.save();
            // Sword Blade / Shaft
            ctx.beginPath();
            ctx.moveTo(494, 434);
            ctx.lineTo(530, 434);
            ctx.lineTo(518, 864);
            ctx.lineTo(506, 864);
            ctx.closePath();
            ctx.fillStyle = goldGrad;
            ctx.fill();
            ctx.lineWidth = 4;
            ctx.strokeStyle = '#78350F';
            ctx.stroke();

            // Central Ridge Line on Sword
            ctx.beginPath();
            ctx.moveTo(512, 434);
            ctx.lineTo(512, 864);
            ctx.lineWidth = 3;
            ctx.strokeStyle = '#FEF08A';
            ctx.stroke();

            // Sword Bottom Pommel / Tip Ornament
            ctx.beginPath();
            ctx.arc(512, 874, 15, 0, Math.PI * 2);
            ctx.fillStyle = brightYellowGrad;
            ctx.fill();
            ctx.lineWidth = 4;
            ctx.strokeStyle = '#78350F';
            ctx.stroke();
            ctx.restore();

            ctx.restore();

            // Build matching 3D Relief Bump Map from luminance
            const bumpCanvas = document.createElement('canvas');
            bumpCanvas.width = size;
            bumpCanvas.height = size;
            const bCtx = bumpCanvas.getContext('2d');
            bCtx.drawImage(colorCanvas, 0, 0);
            const imgData = bCtx.getImageData(0, 0, size, size);
            const d = imgData.data;
            for (let i = 0; i < d.length; i += 4) {
                const lum = 0.299 * d[i] + 0.587 * d[i + 1] + 0.114 * d[i + 2];
                d[i] = lum;
                d[i + 1] = lum;
                d[i + 2] = lum;
            }
            bCtx.putImageData(imgData, 0, 0);

            return { colorCanvas, bumpCanvas };
        }

        const hdMaps = buildUltraHDIFICrest(2048);

        const colorTex = new THREE.CanvasTexture(hdMaps.colorCanvas);
        colorTex.anisotropy = renderer.capabilities.getMaxAnisotropy();
        colorTex.minFilter = THREE.LinearMipmapLinearFilter;
        colorTex.magFilter = THREE.LinearFilter;
        colorTex.generateMipmaps = true;
        colorTex.needsUpdate = true;

        const bumpTex = new THREE.CanvasTexture(hdMaps.bumpCanvas);
        bumpTex.anisotropy = renderer.capabilities.getMaxAnisotropy();
        bumpTex.needsUpdate = true;

        // Ultra-Crisp Full-Color Material with 3D Embossed Bump Relief
        const faceMat = new THREE.MeshStandardMaterial({
            color: 0xffffff,
            map: colorTex,
            emissive: 0xffffff,
            emissiveMap: colorTex,
            emissiveIntensity: 0.26,
            bumpMap: bumpTex,
            bumpScale: 0.035,
            metalness: 0.10,
            roughness: 0.26,
            transparent: true,
            alphaTest: 0.15,
            side: THREE.FrontSide
        });

        const sliceMat = new THREE.MeshStandardMaterial({
            color: 0xd4af37,
            map: colorTex,
            metalness: 0.65,
            roughness: 0.25,
            transparent: true,
            alphaTest: 0.50,
            side: THREE.DoubleSide
        });

        const planeGeo = new THREE.PlaneGeometry(6.8, 6.8);

        // Volumetric 3D depth slices for solid 1:1 edge profile
        const totalSlices = 22;
        const totalDepth = 0.28;
        for (let s = 0; s < totalSlices; s++) {
            const zPos = -totalDepth / 2 + (s / (totalSlices - 1)) * totalDepth;
            const sliceMesh = new THREE.Mesh(planeGeo, sliceMat);
            sliceMesh.position.z = zPos;
            crossGroup.add(sliceMesh);
        }

        // Ultra-HD Front Face (+Z)
        const frontFace = new THREE.Mesh(planeGeo, faceMat);
        frontFace.position.z = totalDepth / 2 + 0.015;
        crossGroup.add(frontFace);

        // Ultra-HD Back Face (-Z)
        const backFace = new THREE.Mesh(planeGeo, faceMat);
        backFace.position.z = -totalDepth / 2 - 0.015;
        backFace.rotation.y = Math.PI;
        crossGroup.add(backFace);

        // 3D Celestial Gold Halo Ring Framing the IFI Crest
        const haloGeo = new THREE.TorusGeometry(2.95, 0.045, 16, 72);
        const haloMesh = new THREE.Mesh(haloGeo, mirrorChromeMat);
        haloMesh.position.set(0, 0.05, 0);
        crossGroup.add(haloMesh);
        threeCross.haloRing = haloMesh;

        // Diamond Studs on Halo Ring
        const studGeo = new THREE.OctahedronGeometry(0.13);
        const angles = [0, Math.PI / 2, Math.PI, (3 * Math.PI) / 2];
        angles.forEach(ang => {
            const stud = new THREE.Mesh(studGeo, mirrorChromeMat);
            stud.position.set(Math.cos(ang) * 2.95, 0.05 + Math.sin(ang) * 2.95, 0);
            crossGroup.add(stud);
        });

        // Floating Starlight Particles
        const particleCount = 85;
        const pGeo = new THREE.BufferGeometry();
        const pPositions = new Float32Array(particleCount * 3);
        for (let i = 0; i < particleCount * 3; i += 3) {
            const rad = 2.4 + Math.random() * 3.5;
            const theta = Math.random() * Math.PI * 2;
            const phi = (Math.random() - 0.5) * Math.PI;
            pPositions[i] = rad * Math.cos(phi) * Math.cos(theta);
            pPositions[i + 1] = rad * Math.sin(phi);
            pPositions[i + 2] = rad * Math.cos(phi) * Math.sin(theta);
        }
        pGeo.setAttribute('position', new THREE.BufferAttribute(pPositions, 3));

        const pMat = new THREE.PointsMaterial({
            color: 0xe2e8f0,
            size: 0.12,
            transparent: true,
            opacity: 0.75,
            blending: THREE.AdditiveBlending
        });
        const particles = new THREE.Points(pGeo, pMat);
        scene.add(particles);
        threeCross.particles = particles;

        crossGroup.scale.set(0.96, 0.96, 0.96);

        initCrossInteractivity(container);
    }

    function initCrossInteractivity(container) {
        const onPointerDown = (clientX, clientY) => {
            threeCross.isDragging = true;
            threeCross.prevPointer = { x: clientX, y: clientY };
            threeCross.velocity = { x: 0, y: 0 };
            if (threeCross.idleTimer) clearTimeout(threeCross.idleTimer);
        };

        const onPointerMove = (clientX, clientY) => {
            const rect = container.getBoundingClientRect();
            const normX = ((clientX - rect.left) / rect.width) * 2 - 1;
            const normY = -(((clientY - rect.top) / rect.height) * 2 - 1);

            if (threeCross.cursorLight) {
                threeCross.cursorLight.position.x = normX * 4.5;
                threeCross.cursorLight.position.y = normY * 4.5;
            }

            if (threeCross.isDragging && threeCross.crossGroup) {
                const deltaX = clientX - threeCross.prevPointer.x;
                const deltaY = clientY - threeCross.prevPointer.y;

                threeCross.velocity = {
                    x: deltaY * 0.0075,
                    y: deltaX * 0.0075
                };

                threeCross.crossGroup.rotation.y += threeCross.velocity.y;
                threeCross.crossGroup.rotation.x += threeCross.velocity.x;

                threeCross.prevPointer = { x: clientX, y: clientY };
            } else if (!threeCross.isDragging && threeCross.crossGroup) {
                threeCross.crossGroup.rotation.x = 0.06 + normY * 0.12;
            }
        };

        const onPointerUp = () => {
            if (!threeCross.isDragging) return;
            threeCross.isDragging = false;
            threeCross.idleTimer = setTimeout(() => {
                threeCross.autoSpin = true;
            }, 2500);
        };

        // Mouse Events
        container.addEventListener('mousedown', (e) => onPointerDown(e.clientX, e.clientY));
        window.addEventListener('mousemove', (e) => {
            if (threeCross.isRunning) onPointerMove(e.clientX, e.clientY);
        });
        window.addEventListener('mouseup', onPointerUp);

        // Touch Events
        container.addEventListener('touchstart', (e) => {
            if (e.touches.length > 0) {
                onPointerDown(e.touches[0].clientX, e.touches[0].clientY);
            }
        }, { passive: true });

        window.addEventListener('touchmove', (e) => {
            if (threeCross.isRunning && e.touches.length > 0) {
                onPointerMove(e.touches[0].clientX, e.touches[0].clientY);
            }
        }, { passive: true });

        window.addEventListener('touchend', onPointerUp);

        // Click Ripple Specular Shine Surge
        container.addEventListener('click', () => {
            if (threeCross.cursorLight) {
                threeCross.cursorLight.intensity = 5.5;
                setTimeout(() => {
                    if (threeCross.cursorLight) threeCross.cursorLight.intensity = 2.4;
                }, 320);
            }
        });

        // Control Buttons
        const btnSpin = document.getElementById('btnToggleSpin');
        if (btnSpin) {
            btnSpin.onclick = (e) => {
                e.stopPropagation();
                threeCross.autoSpin = !threeCross.autoSpin;
                btnSpin.classList.toggle('active', threeCross.autoSpin);
            };
        }

        const btnReset = document.getElementById('btnResetView');
        if (btnReset) {
            btnReset.onclick = (e) => {
                e.stopPropagation();
                if (threeCross.crossGroup) {
                    threeCross.crossGroup.rotation.x = 0;
                    threeCross.crossGroup.rotation.y = 0;
                    threeCross.velocity = { x: 0, y: 0.012 };
                }
            };
        }
    }

    function animate3DCross(time) {
        if (!threeCross.isRunning) return;
        threeCross.animFrameId = requestAnimationFrame(animate3DCross);

        const group = threeCross.crossGroup;
        if (group) {
            if (threeCross.isDragging) {
                // Dragging handles rotation
            } else if (threeCross.autoSpin) {
                threeCross.velocity.y = threeCross.velocity.y * 0.95 + 0.012 * 0.05;
                threeCross.velocity.x = threeCross.velocity.x * 0.95;
                group.rotation.y += threeCross.velocity.y;
                group.rotation.x += threeCross.velocity.x;
            } else {
                threeCross.velocity.y *= 0.94;
                threeCross.velocity.x *= 0.94;
                group.rotation.y += threeCross.velocity.y;
                group.rotation.x += threeCross.velocity.x;
            }

            group.position.y = Math.sin(time * 0.0016) * 0.16;
        }

        if (threeCross.particles) {
            threeCross.particles.rotation.y += 0.0015;
            threeCross.particles.rotation.x += 0.0008;
        }

        if (threeCross.renderer && threeCross.scene && threeCross.camera) {
            threeCross.renderer.render(threeCross.scene, threeCross.camera);
        }
    }

    function start3DCross() {
        if (!threeCross.renderer) {
            init3DCross();
        } else {
            const container = document.getElementById('crossCanvasContainer');
            if (container && threeCross.renderer && threeCross.camera) {
                const w = container.clientWidth || 420;
                const h = container.clientHeight || 440;
                threeCross.camera.aspect = w / h;
                threeCross.camera.updateProjectionMatrix();
                threeCross.renderer.setSize(w, h);
            }
        }
        threeCross.isRunning = true;
        if (threeCross.animFrameId) cancelAnimationFrame(threeCross.animFrameId);
        threeCross.animFrameId = requestAnimationFrame(animate3DCross);
    }

    function stop3DCross() {
        threeCross.isRunning = false;
        if (threeCross.animFrameId) {
            cancelAnimationFrame(threeCross.animFrameId);
            threeCross.animFrameId = null;
        }
    }

    function openLoginModal() {
        const modal = document.getElementById('cathedralLoginModal');
        if (!modal) return;
        modal.style.display = 'flex';
        requestAnimationFrame(() => {
            modal.classList.add('active');
            setTimeout(start3DCross, 60);
        });
        document.body.style.overflow = 'hidden';
        const userField = document.getElementById('modalUsername');
        if (userField) {
            setTimeout(() => userField.focus(), 80);
        }
    }

    function closeLoginModal() {
        const modal = document.getElementById('cathedralLoginModal');
        if (!modal) return;
        modal.classList.remove('active');
        stop3DCross();
        setTimeout(() => {
            if (!modal.classList.contains('active')) {
                modal.style.display = 'none';
            }
        }, 380);
        document.body.style.overflow = '';
    }

    // Keyboard Accessibility: Escape key closes modal
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            const modal = document.getElementById('cathedralLoginModal');
            if (modal && modal.classList.contains('active')) {
                closeLoginModal();
            }
        }
    });

    // Modal Password Visibility Toggle
    (function initModalPasswordToggle() {
        const pwToggle = document.getElementById('modalPwToggle');
        const pwInput = document.getElementById('modalPassword');
        const pwIcon = document.getElementById('modalPwIcon');
        if (pwToggle && pwInput && pwIcon) {
            pwToggle.addEventListener('click', () => {
                const isPw = pwInput.type === 'password';
                pwInput.type = isPw ? 'text' : 'password';
                pwIcon.className = isPw ? 'fa-regular fa-eye-slash' : 'fa-regular fa-eye';
            });
        }
    })();

    // Brute-force Lockout UI Controller: disables text fields and runs live cooldown timer
    let modalLockoutInterval = null;
    function startPortalModalLockout(seconds) {
        const userInput = document.getElementById('modalUsername');
        const passInput = document.getElementById('modalPassword');
        const pwToggle = document.getElementById('modalPwToggle');
        const alertBox = document.getElementById('modalLoginAlert');
        const alertText = document.getElementById('modalAlertText');
        const submitBtn = document.getElementById('modalSubmitBtn');
        const submitText = document.getElementById('modalSubmitText');
        const submitIcon = document.getElementById('modalSubmitIcon');

        if (modalLockoutInterval) clearInterval(modalLockoutInterval);

        // Make fields completely untypable during lockout
        if (userInput) {
            userInput.disabled = true;
            userInput.setAttribute('readonly', 'readonly');
            userInput.style.opacity = '0.45';
            userInput.style.cursor = 'not-allowed';
            userInput.title = 'Inputs disabled due to security lockout.';
        }
        if (passInput) {
            passInput.disabled = true;
            passInput.setAttribute('readonly', 'readonly');
            passInput.style.opacity = '0.45';
            passInput.style.cursor = 'not-allowed';
            passInput.title = 'Inputs disabled due to security lockout.';
        }
        if (pwToggle) pwToggle.disabled = true;
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.style.opacity = '0.45';
            submitBtn.style.cursor = 'not-allowed';
            if (submitIcon) submitIcon.className = 'fa-solid fa-lock';
        }

        let remaining = Math.max(1, parseInt(seconds, 10) || 900);
        // Persist expiry in localStorage so lockout persists across browser reloads
        const targetExpiry = Date.now() + (remaining * 1000);
        localStorage.setItem('portal_login_lockout_until', targetExpiry.toString());

        function updateLockoutUI() {
            const m = Math.floor(remaining / 60);
            const s = remaining % 60;
            const timeStr = `${m}:${s < 10 ? '0' : ''}${s}`;
            if (alertText) {
                alertText.innerHTML = `<strong><i class="fa-solid fa-shield-halved"></i> Security Lockout Active:</strong> Multiple failed attempts detected. Text fields are locked for <strong>${timeStr}</strong>`;
            }
            if (alertBox) {
                alertBox.style.display = 'flex';
                alertBox.style.borderColor = 'rgba(239, 68, 68, 0.45)';
                alertBox.style.background = 'rgba(239, 68, 68, 0.12)';
                alertBox.style.color = '#fca5a5';
            }
            if (submitText) submitText.textContent = `Locked (${timeStr})`;
        }

        updateLockoutUI();

        modalLockoutInterval = setInterval(() => {
            remaining--;
            if (remaining <= 0) {
                clearInterval(modalLockoutInterval);
                modalLockoutInterval = null;
                localStorage.removeItem('portal_login_lockout_until');

                // Re-enable text fields once cooldown has elapsed
                if (userInput) {
                    userInput.disabled = false;
                    userInput.removeAttribute('readonly');
                    userInput.style.opacity = '1';
                    userInput.style.cursor = 'text';
                    userInput.removeAttribute('title');
                    userInput.value = '';
                }
                if (passInput) {
                    passInput.disabled = false;
                    passInput.removeAttribute('readonly');
                    passInput.style.opacity = '1';
                    passInput.style.cursor = 'text';
                    passInput.removeAttribute('title');
                    passInput.value = '';
                }
                if (pwToggle) pwToggle.disabled = false;
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.style.opacity = '1';
                    submitBtn.style.cursor = 'pointer';
                    if (submitText) submitText.textContent = 'Sign In to Portal';
                    if (submitIcon) submitIcon.className = 'fa-solid fa-arrow-right';
                }
                if (alertText) {
                    alertText.innerHTML = '<i class="fa-solid fa-circle-check" style="color:var(--emerald, #10b981);"></i> Cooldown expired. You may now attempt to sign in.';
                }
                if (alertBox) {
                    alertBox.style.borderColor = 'rgba(16, 185, 129, 0.4)';
                    alertBox.style.background = 'rgba(16, 185, 129, 0.12)';
                    alertBox.style.color = '#6ee7b7';
                }
                if (userInput) userInput.focus();
            } else {
                updateLockoutUI();
            }
        }, 1000);
    }

    // Modal Asynchronous AJAX Login Handler with Inline Feedback
    (function initModalAjaxLogin() {
        const form = document.getElementById('modalLoginForm');
        if (!form) return;

        // Check if lockout is still active from localStorage (survives browser refresh!)
        (function checkStoredModalLockout() {
            const stored = localStorage.getItem('portal_login_lockout_until');
            if (stored) {
                const expiry = parseInt(stored, 10);
                const remainingSec = Math.floor((expiry - Date.now()) / 1000);
                if (remainingSec > 0) {
                    startPortalModalLockout(remainingSec);
                } else {
                    localStorage.removeItem('portal_login_lockout_until');
                }
            }
        })();

        form.addEventListener('submit', async function (e) {
            e.preventDefault();
            const alertBox = document.getElementById('modalLoginAlert');
            const alertText = document.getElementById('modalAlertText');
            const submitBtn = document.getElementById('modalSubmitBtn');
            const submitText = document.getElementById('modalSubmitText');
            const submitIcon = document.getElementById('modalSubmitIcon');

            if (alertBox) alertBox.style.display = 'none';
            if (submitBtn) {
                submitBtn.disabled = true;
                if (submitText) submitText.textContent = 'Authenticating...';
                if (submitIcon) submitIcon.className = 'fa-solid fa-spinner fa-spin';
            }

            const formData = new FormData(form);
            formData.append('ajax', '1');

            try {
                const resp = await fetch('app/controllers/authenticate.php', {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    },
                    body: formData
                });

                const data = await resp.json().catch(() => null);

                if (data && data.success) {
                    if (submitText) submitText.textContent = 'Welcome! Entering Portal...';
                    if (submitIcon) submitIcon.className = 'fa-solid fa-check';
                    submitBtn.style.background = 'linear-gradient(135deg, #10b981 0%, #059669 100%)';
                    setTimeout(() => {
                        window.location.href = data.redirect || 'dashboard.php';
                    }, 400);
                } else {
                    const isLock = (data && (data.code === 'locked' || data.code === 'ip_locked')) || resp.status === 429;
                    if (isLock) {
                        const waitSec = (data && data.wait_seconds) ? data.wait_seconds : ((data && data.wait ? parseInt(data.wait, 10) : 15) * 60);
                        startPortalModalLockout(waitSec);
                        return;
                    }

                    const errorMsg = (data && data.error) ? data.error : 'Invalid credentials. Please verify and try again.';
                    if (alertBox && alertText) {
                        alertText.textContent = errorMsg;
                        alertBox.style.display = 'flex';
                    }
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        if (submitText) submitText.textContent = 'Sign In to Portal';
                        if (submitIcon) submitIcon.className = 'fa-solid fa-arrow-right';
                    }
                }
            } catch (err) {
                console.warn('AJAX login submission error, falling back to standard submit:', err);
                form.submit();
            }
        });
    })();

    function toggleMenu(force) {
        const nav = document.getElementById('mainNav');
        const icon = document.querySelector('.menu-toggle i');
        
        if (force !== undefined) {
            nav.classList.toggle('active', force);
        } else {
            nav.classList.toggle('active');
        }
        
        if (nav.classList.contains('active')) {
            icon.classList.replace('fa-bars', 'fa-xmark');
            document.body.style.overflow = 'hidden';
        } else {
            icon.classList.replace('fa-xmark', 'fa-bars');
            document.body.style.overflow = '';
        }
    }

    // Close mobile menu on resize
    window.addEventListener('resize', () => {
        if (window.innerWidth > 768) {
            const nav = document.getElementById('mainNav');
            if (nav) nav.classList.remove('active');
            const icon = document.querySelector('.menu-toggle i');
            if (icon) icon.classList.replace('fa-xmark', 'fa-bars');
            document.body.style.overflow = '';
        }
    });
</script>

<script src="assets/js/theme.js"></script>
</body>
</html>
