<?php
/**
 * Public Sacramental & Liturgical Services Catalog Page
 * IFI Cathedral of Saint Michael the Archangel
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/app/models/ServiceModel.php';
require_once __DIR__ . '/app/models/ParishInfo.php';

$srvModel = new ServiceModel($conn);
$parishModel = new ParishInfo($conn);

$services_catalog = $srvModel->getFullCatalog(true);
$contact_info = $parishModel->getContactInfo();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sacramental &amp; Liturgical Services | <?php echo htmlspecialchars($contact_info['parish_name'] ?? 'IFI Cathedral'); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@500;600;700;800&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" type="text/css" href="assets/css/index.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" type="text/css" href="assets/css/skeleton.css?v=<?php echo time(); ?>">
    <script src="assets/js/skeleton-loader.js?v=<?php echo time(); ?>"></script>
    <link rel="icon" type="image/x-icon" href="assets/img/2323.png">
    <script>
      (function () {
        const savedTheme = localStorage.getItem('theme') || localStorage.getItem('portalTheme') || 'dark';
        document.documentElement.setAttribute('data-theme', savedTheme);
      })();
    </script>
    <style>
        .services-hero-header {
            padding: 85px 20px 40px;
            text-align: center;
            background: radial-gradient(circle at top center, rgba(201,168,76,0.18), transparent 70%), var(--bg);
            border-bottom: 1px solid var(--border);
        }
        .rate-notice-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(201,168,76,0.1);
            border: 1px solid rgba(201,168,76,0.3);
            border-radius: 20px;
            padding: 6px 16px;
            font-size: 11.5px;
            color: var(--accent);
            margin-top: 10px;
            max-width: 800px;
        }
    </style>
</head>
<body>

<!-- ── HEADER ── -->
<header class="header">
    <div class="logo" onclick="window.location.href='index.php'" style="cursor: pointer;">
        <img src="assets/img/2323.png" alt="Cathedral Seal">
        <h2>IFI <span>CATHEDRAL</span></h2>
    </div>
    
    <nav id="mainNav">
        <a href="index.php">Home</a>
        <a href="index.php#liturgy">Mass Times</a>
        <a href="services.php" style="color: var(--accent); font-weight: 700;">Services &amp; Rates</a>
        <a href="index.php#bulletin">Announcements</a>
        <a href="index.php#about">Our Story</a>
    </nav>

    <div class="header-actions">
        <a href="index.php?open_login=1" class="btn-gold login-btn-header" style="text-decoration:none;">
            <i class="fa-solid fa-arrow-right-to-bracket"></i> Login
        </a>
    </div>
</header>

<div class="services-hero-header">
    <div class="church-tag-wrap" style="justify-content: center; margin-bottom: 8px;">
        <span class="ornament-line"></span>
        <span class="church-section-tag"><i class="fa-solid fa-cross"></i> Ecclesia &amp; Sacraments</span>
        <span class="ornament-line"></span>
    </div>
    <h1 style="font-family: var(--font-heading, 'Cinzel', serif); font-size: 26px; font-weight: 800; color: var(--text, #ffffff); margin: 0 0 8px;">
        Sacramental &amp; Liturgical Services
    </h1>
    <p style="font-size: 13px; color: var(--text2, #94a3b8); max-width: 620px; margin: 0 auto; line-height: 1.5;">
        Sacred rites, Eucharistic offerings, canonical blessings, and ecclesiastical records of the Iglesia Filipina Independiente.
    </p>
    <div class="rate-notice-pill">
        <i class="fa-solid fa-circle-info"></i>
        <span><strong>Official Notice:</strong> Initial rates shown represent the December 16, 2012 Cathedral Synod rate schedule. All current fees, dates, and requirements are confirmed directly with the Parish Administration.</span>
    </div>
</div>

<main class="container" style="padding: 24px 16px 60px;">
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
    <div class="services-ledger-toolbar">
        <div class="service-category-tabs">
            <button type="button" class="service-tab-btn active" onclick="filterPublicServices('all', this)">
                <i class="fa-solid fa-layer-group"></i> All Services <span class="tab-count-badge"><?php echo $catCounts['all'] ?? 0; ?></span>
            </button>
            <button type="button" class="service-tab-btn" onclick="filterPublicServices('sacraments', this)">
                <i class="fa-solid fa-dove"></i> Sacraments <span class="tab-count-badge"><?php echo $catCounts['sacraments'] ?? 0; ?></span>
            </button>
            <button type="button" class="service-tab-btn" onclick="filterPublicServices('liturgical_services', this)">
                <i class="fa-solid fa-church"></i> Liturgical Services <span class="tab-count-badge"><?php echo $catCounts['liturgical_services'] ?? 0; ?></span>
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
    <div class="services-schedule-table-wrap">
        <table class="services-ledger-table" id="servicesLedgerTable">
            <thead>
                <tr>
                    <th class="col-service" scope="col"><i class="fa-solid fa-church"></i> Rite / Liturgical Service</th>
                    <th class="col-prereq" scope="col"><i class="fa-solid fa-file-shield"></i> Canonical Documents</th>
                    <th class="col-stipend" scope="col"><i class="fa-solid fa-coins"></i> Synod Stipend &amp; Schedule</th>
                    <th class="col-action" scope="col">Parish Action</th>
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
                <!-- Main Master Row -->
                <tr class="service-ledger-row" data-cat="<?php echo htmlspecialchars($catCode); ?>" data-search="<?php echo htmlspecialchars($searchHaystack); ?>" id="srv-row-<?php echo $srvIndex; ?>">
                    <td class="service-ledger-cell col-service">
                        <div class="service-cell-wrap">
                            <div class="service-ledger-icon">
                                <i class="fa-solid <?php echo $iconClass; ?>"></i>
                            </div>
                            <div class="service-cell-text">
                                <div class="service-cell-meta">
                                    <span class="service-cat-pill"><?php echo htmlspecialchars($cat['name']); ?></span>
                                    <span class="service-synod-chip"><i class="fa-solid fa-scroll"></i> Synod 2012</span>
                                </div>
                                <h3 class="service-ledger-title"><?php echo htmlspecialchars($srv['name']); ?></h3>
                                <?php if (!empty($srv['description'])): ?>
                                    <p class="service-ledger-desc"><?php echo htmlspecialchars($srv['description']); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </td>
                    <td class="service-ledger-cell col-prereq">
                        <div class="prereq-cell-wrap">
                            <?php if ($reqCount > 0): ?>
                                <ul class="ledger-inline-reqs">
                                    <?php 
                                    $shownReqs = array_slice($srv['requirements'], 0, 3);
                                    foreach ($shownReqs as $r): 
                                    ?>
                                        <li><i class="fa-solid fa-circle-check"></i> <span><?php echo htmlspecialchars($r['requirement']); ?></span></li>
                                    <?php endforeach; ?>
                                    <?php if ($reqCount > 3): ?>
                                        <li class="req-more-note" onclick="toggleServiceDocket(<?php echo $srvIndex; ?>)" style="cursor: pointer;" title="View all requirements in docket">+<?php echo ($reqCount - 3); ?> more in full docket <i class="fa-solid fa-chevron-down" style="font-size: 0.6rem;"></i></li>
                                    <?php endif; ?>
                                </ul>
                            <?php else: ?>
                                <div class="direct-booking-wrap">
                                    <span class="direct-booking-pill">
                                        <i class="fa-solid fa-circle-check"></i> Direct Parish Booking
                                    </span>
                                    <span class="prereq-subnote">No advance civil documents required</span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td class="service-ledger-cell col-stipend">
                        <div class="stipend-cell-wrap">
                            <?php if ($variantCount === 1): ?>
                                <div class="stipend-primary-val">
                                    <span class="stipend-amount">₱<?php echo number_format($minPrice ?? 0, 2); ?></span>
                                </div>
                                <span class="single-tier-tag"><i class="fa-solid fa-check"></i> Standard Offering</span>
                            <?php elseif ($variantCount <= 2): ?>
                                <div class="inline-variants-preview">
                                    <?php foreach ($srv['variants'] as $v): ?>
                                        <div class="variant-rate-line">
                                            <span class="v-name"><?php echo htmlspecialchars($v['name']); ?></span>
                                            <strong class="v-price">₱<?php echo number_format((float)$v['price'], 2); ?></strong>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="stipend-primary-val">
                                    <span class="stipend-prefix">From</span>
                                    <span class="stipend-amount">₱<?php echo number_format($minPrice ?? 0, 2); ?></span>
                                </div>
                                <button type="button" class="tier-toggle-pill" onclick="toggleServiceDocket(<?php echo $srvIndex; ?>)">
                                    <i class="fa-solid fa-layer-group"></i> View All <?php echo $variantCount; ?> Tiers <i class="fa-solid fa-chevron-down caret-icon"></i>
                                </button>
                            <?php endif; ?>
                            <?php if ($chargeCount > 0): ?>
                                <button type="button" class="addons-count-tag" onclick="toggleServiceDocket(<?php echo $srvIndex; ?>)" title="View optional add-ons & options">
                                    +<?php echo $chargeCount; ?> Optional Add-on<?php echo $chargeCount > 1 ? 's' : ''; ?> <i class="fa-solid fa-chevron-down" style="font-size: 0.58rem;"></i>
                                </button>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td class="service-ledger-cell col-action">
                        <div class="action-cell-wrap">
                            <a href="index.php?open_login=1&redirect=request_event" class="btn-ledger-schedule">
                                <i class="fa-solid fa-calendar-check"></i> Request Schedule
                            </a>
                        </div>
                    </td>
                </tr>

                <!-- Expandable Institutional Docket Drawer Row -->
                <tr class="service-docket-row" id="srv-docket-<?php echo $srvIndex; ?>" data-cat="<?php echo htmlspecialchars($catCode); ?>" style="display: none;">
                    <td colspan="4" class="service-docket-cell">
                        <div class="service-docket-container">
                            <div class="service-docket-grid">
                                <!-- Left Column: Canonical Prerequisites & Pastoral Guidance -->
                                <div class="docket-pane-card">
                                    <div class="docket-pane-title">
                                        <i class="fa-solid fa-clipboard-check"></i> Canonical Requirements &amp; Preparation
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
                                            This liturgical service does not mandate advance civil documentary requirements. It can be directly requested and scheduled with the Cathedral Parish Office.
                                        </p>
                                    <?php endif; ?>
                                    <div class="docket-pastoral-note">
                                        <i class="fa-solid fa-circle-info"></i>
                                        <strong>Pastoral Directive:</strong> All rites are administered under the liturgical guidelines of the Diocese. Registered members can upload digitized prerequisite documents through their online dashboard.
                                    </div>
                                </div>

                                <!-- Right Column: Synod Stipend Schedule & Add-ons -->
                                <div class="docket-pane-card">
                                    <div class="docket-pane-title">
                                        <i class="fa-solid fa-scale-balanced"></i> Synod Stipend Schedule &amp; Options
                                    </div>
                                    <table class="docket-variants-table">
                                        <thead>
                                            <tr>
                                                <th>Option / Tier</th>
                                                <th>Sponsors Included</th>
                                                <th style="text-align: right;">Synod Offering</th>
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
                                                <i class="fa-solid fa-plus-circle"></i> Authorized Optional Stipends / Add-ons:
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
                                </div>
                            </div>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div id="servicesLedgerEmpty" class="services-ledger-empty">
            <i class="fa-solid fa-magnifying-glass"></i>
            <p>No sacramental or liturgical services match your search or filter.</p>
        </div>
    </div>
</main>

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
            <a href="index.php?open_login=1&redirect=request_event" class="btn-act" style="flex:0 0 auto;">
                <i class="fa-solid fa-arrow-right"></i> Proceed to Request
            </a>
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
            }
        }
    });

    const emptyState = document.getElementById('servicesLedgerEmpty');
    if (emptyState) {
        emptyState.style.display = visibleCount === 0 ? 'block' : 'none';
    }
}

function toggleServiceDocket(index) {
    const row = document.getElementById('srv-row-' + index);
    const docket = document.getElementById('srv-docket-' + index);
    if (!row || !docket) return;

    if (docket.style.display === 'none' || docket.style.display === '') {
        docket.style.display = 'table-row';
        row.classList.add('is-expanded');
    } else {
        docket.style.display = 'none';
        row.classList.remove('is-expanded');
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
        list.innerHTML = '<li>No special canonical requirements documented for this service.</li>';
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

</body>
</html>
