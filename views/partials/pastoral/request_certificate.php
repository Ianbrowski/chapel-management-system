<?php
/**
 * Dedicated Portal: Ecclesiastical Certificates & Office Document Services
 * 
 * Provides an administrative document issuance application workflow:
 * - Canonical registry record lookup details (holder name, sacrament year, purpose)
 * - Number of copies multiplier & real-time fee calculation
 * - Target office pickup window at Cathedral Parish Office
 * - Zero church altar time-slot occupancy
 * - Dedicated certificate applications ledger
 */

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php?open_login=1');
    exit;
}

$csrf = $_SESSION['csrf'] ?? ($_SESSION['csrf_token'] ?? ($csrf ?? ''));
$my_requests = $my_requests ?? [];
$ev_filters = $ev_filters ?? [];
$totalReqs = count($my_requests);

$role = $_SESSION['role'] ?? '';
$isGuest = ($role === 'Guest');
$isAdmin = in_array($role, ['Admin', 'Cathedral Admin']) || !empty($can_approve_events);
$scopeView = $_GET['scope_view'] ?? ($scope_view ?? ($isAdmin ? 'all' : 'mine'));

// Locate Certificates Service in Catalog
$certService = null;
if (!empty($services_catalog)) {
    foreach ($services_catalog as $cat) {
        if (!empty($cat['services'])) {
            foreach ($cat['services'] as $srv) {
                if ($srv['code'] === 'certificates' || stripos($srv['name'], 'certificate') !== false) {
                    $certService = $srv;
                    break 2;
                }
            }
        }
    }
}

// Fallback variants if not yet loaded from DB
$certVariants = $certService['variants'] ?? [
    ['id' => 16, 'name' => 'Baptismal Certificate', 'price' => 200.00],
    ['id' => 17, 'name' => 'Confirmation Certificate', 'price' => 200.00],
    ['id' => 18, 'name' => 'Marriage Certificate', 'price' => 200.00],
    ['id' => 31, 'name' => 'Christian Burial Certificate', 'price' => 200.00],
    ['id' => 22, 'name' => 'Certificate of Good Standing / Membership', 'price' => 200.00],
];
$certServiceId = $certService['id'] ?? 8;

// All certificate requests loaded by dedicated controller method
$cert_requests = $my_requests;

$initialCertTab = $_GET['tab'] ?? ($isGuest ? 'spectate' : 'ledger');
if ($isGuest && $initialCertTab === 'apply') {
    $initialCertTab = 'spectate';
} elseif (!in_array($initialCertTab, ['ledger', 'apply', 'spectate'], true)) {
    $initialCertTab = $isGuest ? 'spectate' : 'ledger';
}
$certCounts       = $cert_counts ?? [];
$totalCertCount   = $certCounts['total'] ?? count($cert_requests);
$pendingCertCount  = $certCounts['pending'] ?? count(array_filter($cert_requests, fn($r) => ($r['status'] ?? '') === 'Pending'));
$readyCertCount    = $certCounts['confirmed'] ?? count(array_filter($cert_requests, fn($r) => ($r['status'] ?? '') === 'Confirmed'));
$releasedCertCount = $certCounts['completed'] ?? count(array_filter($cert_requests, fn($r) => ($r['status'] ?? '') === 'Completed'));
?>

<style>
/* Segmented Tabs Navigation */
.cert-tabs-bar {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: var(--bg3, #F4F4F5);
    padding: 3px;
    border-radius: 8px;
    border: 1px solid var(--border);
    margin-bottom: 2px;
}
.cert-tab-btn {
    border: 1px solid transparent;
    background: transparent;
    padding: 8px 18px;
    border-radius: 999px;
    font-size: 13px;
    font-weight: 500;
    color: var(--text2);
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.18s ease;
    line-height: 1.2;
    min-height: 36px;
    box-sizing: border-box;
    white-space: nowrap;
}
.cert-tab-btn:hover {
    color: var(--text);
    background: var(--hover-bg, rgba(0,0,0,0.03));
}
.cert-tab-btn.active {
    background: #111111;
    color: #FFFFFF;
    font-weight: 700;
    box-shadow: 0 1px 3px rgba(0,0,0,0.12);
}
[data-theme="dark"] .cert-tab-btn {
    color: #cbd5e1;
}
[data-theme="dark"] .cert-tab-btn:hover {
    color: #ffffff;
    background: rgba(255, 255, 255, 0.08);
}
[data-theme="dark"] .cert-tab-btn.active {
    background: #FFFFFF !important;
    color: #111111 !important;
    border-color: #FFFFFF !important;
    font-weight: 700;
    box-shadow: 0 2px 8px rgba(0,0,0,0.3);
}
.cert-tab-btn .badge-count {
    font-size: 10px;
    padding: 1px 6px;
    border-radius: 10px;
    font-weight: 700;
    background: var(--bg3, #F4F4F5);
    color: var(--text2, #71717A);
    border: 1px solid var(--border);
}
.cert-tab-btn.active .badge-count {
    background: rgba(255,255,255,0.2);
    color: #FFFFFF;
    border-color: transparent;
}
[data-theme="dark"] .cert-tab-btn.active .badge-count {
    background: rgba(0,0,0,0.25);
    color: #111111;
}

.ent-card {
    background: var(--panel-bg, #FFFFFF);
    border: 1px solid var(--border, #E5E5E5);
    border-radius: 8px;
    box-shadow: none;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}
.ent-card-header {
    padding: 10px 16px;
    background: var(--panel-header-bg, #FFFFFF);
    border-bottom: 1px solid var(--border, #E5E5E5);
    flex-shrink: 0;
}
.ent-card-title {
    font-size: 0.88rem;
    font-weight: 700;
    color: var(--text, #111111);
}
.ent-card-subtitle {
    font-size: 0.72rem;
    color: var(--text2, #737373);
    margin-top: 2px;
}
.ent-card-body {
    padding: 12px 16px;
}
[data-theme="dark"] .ent-card {
    background: var(--panel-bg) !important;
    border-color: var(--border) !important;
}
[data-theme="dark"] .ent-card-header {
    background: var(--panel-header-bg) !important;
    border-color: var(--border) !important;
}
[data-theme="dark"] .ent-card-title {
    color: var(--text) !important;
}
[data-theme="dark"] .ent-card-subtitle {
    color: var(--text2) !important;
}

/* Ledger scroll container & sticky table header (prevents global .panel-body !important padding and border-collapse sticky bleed) */
#sec-certificates .cert-ledger-header {
    padding: 10px 16px !important;
}
#sec-certificates .cert-ledger-scroll {
    padding: 0 !important;
    overflow: auto;
    flex: 1;
    min-height: 0;
    background: var(--surface, #FFFFFF);
}
#cert-requests-table {
    width: 100%;
    min-width: 860px;
    table-layout: fixed;
    border-collapse: separate;
    border-spacing: 0;
}
#cert-requests-table thead th {
    position: sticky;
    top: 0;
    z-index: 15;
    background: var(--bg3, #F8FAFC) !important;
    border-bottom: 1px solid var(--border);
    max-width: none !important;
    box-sizing: border-box !important;
}
[data-theme="dark"] #cert-requests-table thead th {
    background: #18181b !important;
    border-bottom-color: rgba(255, 255, 255, 0.1);
}
#cert-requests-table tbody td {
    max-width: none !important;
    box-sizing: border-box !important;
    border-bottom: 1px solid var(--border);
}
</style>

<div class="workspace workspace-fluid" id="sec-certificates" style="display: flex; flex-direction: column; gap: 14px; width: 100%; max-width: 1680px; margin: 0 auto; box-sizing: border-box; padding: 14px clamp(16px, 1.8vw, 24px) 24px;">

    <!-- ── 1. UNIFIED COMMAND HEADER: TITLE + STATS + TABS + SHORTCUT ── -->
    <div class="page-header-bar" style="display: flex; justify-content: space-between; align-items: center; padding-bottom: 8px; border-bottom: 1px solid var(--border); flex-wrap: wrap; gap: 10px; margin-bottom: 6px;">
        <div style="display: flex; align-items: center; gap: 14px;">
            <div class="page-header-icon" style="width: 38px; height: 38px; border-radius: 9px; background: rgba(201,168,76,0.14); color: var(--accent); display: flex; align-items: center; justify-content: center; font-size: 17px; flex-shrink: 0;">
                <i class="fa-solid fa-file-invoice"></i>
            </div>
            <div>
                <h1 class="page-title" style="font-family: var(--font-family, 'Inter', sans-serif); font-size: var(--font-page-title, 20px); font-weight: 700; color: var(--text); margin: 0; line-height: 1.2;">
                    <?php echo $isAdmin ? 'Certificate Queue' : 'Request Certificates'; ?>
                </h1>
                <p class="page-subtitle" style="font-size: var(--font-page-subtitle, 14px); color: var(--text2); margin: 3px 0 0 0;">
                    <?php echo $isAdmin ? 'Process archive certificate requests, record fees, mark ready or released for parish office pickup' : 'Request official baptism, confirmation, marriage, or christian burial certificates &bull; Releasing at Parish Office (Tue&ndash;Sun, 8am&ndash;5pm)'; ?>
                </p>
            </div>
        </div>

        <!-- Right Side: Stat Badges (always top-right) -->
        <div style="display: flex; align-items: center; gap: 6px;">
            <span class="badge" style="background: var(--bg3, #F4F4F5); border: 1px solid var(--border); color: var(--text2); font-size: 11.5px; font-weight: 600; padding: 4px 12px; border-radius: 999px;">
                <?php echo $totalCertCount; ?> Applications
            </span>
            <?php if ($pendingCertCount > 0): ?>
            <span class="badge badge-warning" style="font-size: 11.5px; font-weight: 600; padding: 4px 10px; border-radius: 999px;">
                <?php echo $pendingCertCount; ?> Pending
            </span>
            <?php endif; ?>
        </div>
    </div>

    <!-- ── 2. SUB-ROW: TABS LEFT + ACTIONS RIGHT ── -->
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; margin-bottom: 0;">
        <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
            <div class="cert-tabs-bar" style="margin-bottom: 0;">
                <button type="button" class="cert-tab-btn <?php echo ($initialCertTab === 'ledger') ? 'active' : ''; ?>" 
                        data-tab="ledger" onclick="switchCertTab('ledger')" style="min-height: 30px; height: 30px; padding: 5px 14px; font-size: 12px; border-radius: 999px;">
                    <i class="fa-solid fa-list-check"></i>
                    <span>Queue</span>
                    <span class="badge-count" style="font-size: 10px;"><?php echo $totalCertCount; ?></span>
                </button>

                <?php if ($isGuest): ?>
                <button type="button" class="cert-tab-btn <?php echo ($initialCertTab === 'spectate') ? 'active' : ''; ?>" 
                        data-tab="spectate" onclick="switchCertTab('spectate')" style="min-height: 30px; height: 30px; padding: 5px 14px; font-size: 12px; border-radius: 999px;">
                    <i class="fa-solid fa-circle-info"></i>
                    <span>Types &amp; Requirements</span>
                </button>
                <button type="button" class="cert-tab-btn" 
                        onclick="openGuestAuthModal('request an official certificate')" style="min-height: 30px; height: 30px; padding: 5px 14px; font-size: 12px; border-radius: 999px; cursor: pointer;">
                    <i class="fa-solid fa-lock" style="font-size: 10.5px; color: var(--accent);"></i>
                    <span>Request Copy</span>
                </button>
                <?php else: ?>
                <button type="button" class="cert-tab-btn <?php echo ($initialCertTab === 'apply') ? 'active' : ''; ?>" 
                        data-tab="apply" onclick="switchCertTab('apply')" style="min-height: 30px; height: 30px; padding: 5px 14px; font-size: 12px; border-radius: 999px;">
                    <i class="fa-solid fa-plus-circle"></i>
                    <span><?php echo $isAdmin ? 'New Application' : 'Request Copy'; ?></span>
                </button>
                <?php endif; ?>
            </div>
        </div>

        <div style="display: flex; align-items: center; gap: 8px;">
            <?php if ($isAdmin): ?>
                <a href="dashboard.php?section=records_cathedral" class="records-tab-link" style="font-size: 12px; height: 32px; min-height: 32px; display: inline-flex; align-items: center; gap: 6px; border: 1px solid var(--border); background: var(--bg3, #F4F4F5); color: var(--text); border-radius: 999px; padding: 5px 14px; text-decoration: none;">
                    <i class="fa-solid fa-book-bible" style="color: var(--accent);"></i> Records
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- ── TAB 1: APPLICATIONS QUEUE (Full-Width Master Ledger) ── -->
    <div id="cert-view-ledger" class="cert-tab-pane" style="display: <?php echo ($initialCertTab === 'ledger') ? 'flex' : 'none'; ?>; flex-direction: column; flex: 1; min-height: 0;">
        <div class="panel" style="border-radius: 12px; overflow: hidden; background: var(--surface); border: 1px solid var(--border); display: flex; flex-direction: column; flex: 1; min-height: 0;">
            
            <!-- Filter & Search Toolbar -->
            <div class="panel-header cert-ledger-header" style="background: var(--bg2); padding: 10px 16px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                    <div style="font-weight: 700; font-size: 13px; color: var(--text); display: flex; align-items: center; gap: 6px;">
                        <i class="fa-solid fa-folder-open text-gold"></i>
                        <span><?php echo $isAdmin ? 'Certificate Requests' : 'My Certificate Requests'; ?></span>
                    </div>

                    <?php if ($isAdmin): ?>
                    <div style="display: inline-flex; align-items: center; background: var(--bg3, #F4F4F5); padding: 3px; border-radius: 999px; border: 1px solid var(--border); gap: 2px; margin-left: 6px;">
                        <a href="dashboard.php?section=request_certificate&scope_view=all&tab=ledger" 
                           style="padding: 4px 12px; font-size: 11.5px; font-weight: 600; text-decoration: none; border-radius: 999px; transition: all 0.15s ease; background: <?php echo ($scopeView !== 'mine') ? '#111111' : 'transparent'; ?>; color: <?php echo ($scopeView !== 'mine') ? '#FFFFFF' : 'var(--text2)'; ?>;">
                            All Requests
                        </a>
                        <a href="dashboard.php?section=request_certificate&scope_view=mine&tab=ledger" 
                           style="padding: 4px 12px; font-size: 11.5px; font-weight: 600; text-decoration: none; border-radius: 999px; transition: all 0.15s ease; background: <?php echo ($scopeView === 'mine') ? '#111111' : 'transparent'; ?>; color: <?php echo ($scopeView === 'mine') ? '#FFFFFF' : 'var(--text2)'; ?>;">
                            My Submissions
                        </a>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Search & Quick Filters -->
                <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                    <div class="cert-status-group" style="display: inline-flex; align-items: center; background: var(--bg3, #F4F4F5); padding: 3px; border-radius: 999px; border: 1px solid var(--border); gap: 2px;">
                        <button type="button" class="cert-status-filter active" onclick="filterCertStatus('all', this)" style="border: 1px solid transparent; background: #111111; color: #FFFFFF; font-size: 11.5px; font-weight: 600; padding: 4px 12px; border-radius: 999px; cursor: pointer; transition: all 0.15s ease;">All (<?php echo $totalCertCount; ?>)</button>
                        <button type="button" class="cert-status-filter" onclick="filterCertStatus('recent', this)" style="border: 1px solid transparent; background: transparent; color: var(--text2); font-size: 11.5px; font-weight: 600; padding: 4px 12px; border-radius: 999px; cursor: pointer; transition: all 0.15s ease;" title="Applications submitted in the last 30 days">Recently Added</button>
                        <button type="button" class="cert-status-filter" onclick="filterCertStatus('modified', this)" style="border: 1px solid transparent; background: transparent; color: var(--text2); font-size: 11.5px; font-weight: 600; padding: 4px 12px; border-radius: 999px; cursor: pointer; transition: all 0.15s ease;" title="Applications updated in the last 30 days">Recently Modified</button>
                        <button type="button" class="cert-status-filter" onclick="filterCertStatus('pending', this)" style="border: 1px solid transparent; background: transparent; color: var(--text2); font-size: 11.5px; font-weight: 600; padding: 4px 12px; border-radius: 999px; cursor: pointer; transition: all 0.15s ease;">Pending (<?php echo $pendingCertCount; ?>)</button>
                        <button type="button" class="cert-status-filter" onclick="filterCertStatus('ready', this)" style="border: 1px solid transparent; background: transparent; color: var(--text2); font-size: 11.5px; font-weight: 600; padding: 4px 12px; border-radius: 999px; cursor: pointer; transition: all 0.15s ease;">Ready (<?php echo $readyCertCount; ?>)</button>
                        <?php if ($releasedCertCount > 0): ?>
                        <button type="button" class="cert-status-filter" onclick="filterCertStatus('completed', this)" style="border: 1px solid transparent; background: transparent; color: var(--text2); font-size: 11.5px; font-weight: 600; padding: 4px 12px; border-radius: 999px; cursor: pointer; transition: all 0.15s ease;">Released (<?php echo $releasedCertCount; ?>)</button>
                        <?php endif; ?>
                    </div>

                    <!-- Universal Sort Toggle Button -->
                    <button type="button" class="btn-sort-toggle" onclick="toggleCertSort(this)"
                            title="Sorted: Newest First (Click to sort Oldest first)"
                            style="height: 34px; width: 34px; min-width: 34px; padding: 0; display: inline-flex; align-items: center; justify-content: center; border-radius: 8px; border: 1px solid var(--border); background: var(--bg2); color: var(--text); cursor: pointer; transition: all 0.15s ease; flex-shrink: 0;" aria-label="Toggle sort order">
                        <i class="fa-solid fa-arrow-down-wide-short" style="font-size: 12px;"></i>
                    </button>

                    <div id="cert-search-wrap" style="position: relative; width: 268px;">
                        <input type="text" id="cert-search-input" maxlength="60" autocomplete="off"
                               placeholder="Search applicant, ref, purpose..." 
                               oninput="handleCertSearchInput()"
                               onfocus="showCertSearchSuggestions()"
                               onkeydown="handleCertSuggestionKeydown(event, 'cert-search-suggestions')"
                               style="padding-left: 32px !important; padding-right: 24px !important; font-size: 12.5px; width: 100%; height: 34px; border-radius: 8px; border: 1px solid var(--border); background: var(--bg2); color: var(--text); box-sizing: border-box; outline: none; transition: border-color 0.15s ease;">
                        <i class="fa-solid fa-magnifying-glass" style="position: absolute; left: 11px; top: 50%; transform: translateY(-50%); font-size: 11.5px; color: var(--text2); pointer-events: none; z-index: 2;"></i>
                        <span id="cert-clear-search" onclick="clearCertSearch()" title="Clear search" style="display: none; position: absolute; right: 8px; top: 50%; transform: translateY(-50%); cursor: pointer; color: var(--text2); font-size: 14px; line-height: 1; z-index: 2;">&times;</span>
                        <div id="cert-search-suggestions" role="listbox" style="display: none; position: absolute; top: calc(100% + 4px); left: 0; width: 320px; max-width: 85vw; max-height: 290px; overflow-y: auto; background: #FFFFFF; border: 1px solid var(--border); border-radius: 8px; box-shadow: 0 10px 25px rgba(0,0,0,0.12); z-index: 60;"></div>
                    </div>
                </div>
            </div>

            <!-- Table Body -->
            <div class="panel-body cert-ledger-scroll">
                <?php if ($isGuest): ?>
                    <div style="padding: 45px 20px; text-align: center; color: var(--text2);">
                        <div style="width: 52px; height: 52px; border-radius: 50%; background: rgba(197,160,89,0.12); display: inline-flex; align-items: center; justify-content: center; margin-bottom: 12px; border: 1px solid rgba(197,160,89,0.25); color: var(--accent);">
                            <i class="fa-solid fa-eye" style="font-size: 22px;"></i>
                        </div>
                        <div style="font-size: 15px; font-weight: 700; color: var(--text); margin-bottom: 6px;">
                            Guest Spectator Mode
                        </div>
                        <div style="font-size: 12.5px; max-width: 440px; margin: 0 auto 16px; color: var(--text2); line-height: 1.5;">
                            You are previewing the Cathedral Certificate Ledger as a Guest. To submit certificate requests and track progress through canonical verification to pickup, please register or sign in.
                        </div>
                        <div style="display: inline-flex; gap: 10px; align-items: center; justify-content: center; flex-wrap: wrap;">
                            <button type="button" class="btn" onclick="openGuestAuthModal('request an official certificate')" style="background: #111111; color: #FFFFFF; padding: 7px 16px; border-radius: 6px; font-size: 12px; font-weight: 600; cursor: pointer; border: none; display: inline-flex; align-items: center; gap: 6px;">
                                <i class="fa-solid fa-user-plus text-gold"></i> Register as Member
                            </button>
                            <button type="button" class="btn" onclick="openGuestAuthModal('request an official certificate')" style="background: #FFFFFF; color: var(--text); border: 1px solid var(--border); padding: 7px 16px; border-radius: 6px; font-size: 12px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 6px;">
                                <i class="fa-solid fa-arrow-right-to-bracket text-gold"></i> Sign In
                            </button>
                        </div>
                    </div>
                <?php elseif (empty($cert_requests)): ?>
                    <div style="padding: 50px 20px; text-align: center; color: var(--text2);">
                        <div style="width: 48px; height: 48px; border-radius: 50%; background: var(--bg2); display: inline-flex; align-items: center; justify-content: center; margin-bottom: 12px; border: 1px solid var(--border); color: var(--accent);">
                            <i class="fa-solid fa-file-invoice" style="font-size: 20px;"></i>
                        </div>
                        <div style="font-size: 15px; font-weight: 700; color: var(--text); margin-bottom: 4px;">
                            <?php echo $isAdmin ? 'No Certificate Applications Found' : 'No Certificate Requests Yet'; ?>
                        </div>
                        <div style="font-size: 12px; max-width: 340px; margin: 0 auto; color: var(--text2); line-height: 1.45;">
                            Click the <strong>Request Certificate</strong> tab above to record or submit an official stamped copy from the parish archives.
                        </div>
                    </div>
                <?php else: ?>
                    <table class="data-table" id="cert-requests-table">
                        <thead>
                            <tr>
                                <th style="padding: 9px 16px; text-align: left; font-size: 11px; text-transform: uppercase; width: auto;">Certificate / Person</th>
                                <th style="padding: 9px 14px; text-align: left; font-size: 11px; text-transform: uppercase; width: 175px;">Sacrament Details</th>
                                <th style="padding: 9px 14px; text-align: left; font-size: 11px; text-transform: uppercase; width: 135px;">Target Pickup</th>
                                <th style="padding: 9px 14px; text-align: right; font-size: 11px; text-transform: uppercase; width: 128px;">Document Fee</th>
                                <th style="padding: 9px 12px; text-align: center; font-size: 11px; text-transform: uppercase; width: 110px;">Status</th>
                                <th style="padding: 9px 12px; text-align: center; font-size: 11px; text-transform: uppercase; width: 88px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cert_requests as $req):
                                $st = $req['status'];
                                $isConfirmed = ($st === 'Confirmed');
                                $isCompleted = ($st === 'Completed');
                                $isCancelled = in_array($st, ['Cancelled', 'Rejected']);
                                $fee = (float)($req['total_fee'] ?? 200.00);
                                $pickupTime = !empty($req['start_time']) ? date('M d, Y', strtotime($req['start_time'])) : 'Pending';
                                $paidMap = $event_paid_map ?? [];
                                $paidFee = (float)($paidMap[(int)$req['event_id']] ?? 0);
                                $isPaidFee = ($fee > 0 && $paidFee >= ($fee - 0.01));
                                $currentUid = (int)($_SESSION['user_id'] ?? 0);
                                $reqOwnerUid = (int)($req['requested_by'] ?? 0);
                                $isRowOwner = ($currentUid > 0 && $reqOwnerUid === $currentUid);
                                $canDeleteRow = $isAdmin || $isRowOwner || $isCancelled;
                                $canEditRow = !$isCancelled && ($isAdmin || ($isRowOwner && $st === 'Pending'));
                                $canChatApplicant = ($isAdmin && $reqOwnerUid > 0 && $reqOwnerUid !== $currentUid);

                                // Extract person name and year from title if formatted
                                $pTitle = $req['title'] ?? 'Certificate';
                                $searchPerson = trim((string)($req['cert_person_name'] ?? ''));
                                if ($searchPerson === '') {
                                    $searchPerson = preg_replace('/^[^:]+:\s*/', '', (string)$pTitle);
                                    $searchPerson = trim(preg_replace('/\s*-\s*.*$/', '', $searchPerson));
                                    $searchPerson = trim(preg_replace('/\s*\(\d{4}\)$/', '', $searchPerson));
                                }
                                $recordsSection = (!empty($req['chapel_id']) && (int)$req['chapel_id'] > 0) ? 'records_chapel' : 'records_cathedral';
                            ?>
                            <tr class="cert-table-row" 
                                data-status="<?php echo strtolower($st); ?>"
                                data-title="<?php echo strtolower(htmlspecialchars($pTitle)); ?>"
                                data-id="<?php echo $req['event_id']; ?>"
                                data-paid="<?php echo number_format($paidFee, 2, '.', ''); ?>"
                                data-can-edit="<?php echo $canEditRow ? '1' : '0'; ?>"
                                data-records-section="<?php echo htmlspecialchars($recordsSection, ENT_QUOTES, 'UTF-8'); ?>"
                                data-records-query="<?php echo htmlspecialchars($searchPerson, ENT_QUOTES, 'UTF-8'); ?>"
                                data-created="<?php echo !empty($req['created_at']) ? strtotime($req['created_at']) : 0; ?>"
                                data-updated="<?php echo !empty($req['updated_at']) ? strtotime($req['updated_at']) : (!empty($req['created_at']) ? strtotime($req['created_at']) : 0); ?>"
                                onclick="viewCertDetailsFromRow(this)" 
                                data-req="<?php echo htmlspecialchars(json_encode($req), ENT_QUOTES, 'UTF-8'); ?>"
                                style="cursor: pointer; border-bottom: 1px solid var(--border); transition: background 0.15s ease;">
                                
                                <td style="padding: 10px 16px; min-width: 0;">
                                    <div style="display: flex; align-items: center; gap: 10px; min-width: 0;">
                                        <div style="width: 32px; height: 32px; border-radius: 6px; background: rgba(201,168,76,0.12); color: var(--accent); display: flex; align-items: center; justify-content: center; font-size: 13px; flex-shrink: 0;">
                                            <i class="fa-solid fa-certificate"></i>
                                        </div>
                                        <div style="min-width: 0; flex: 1;">
                                            <div title="<?php echo htmlspecialchars($pTitle, ENT_QUOTES, 'UTF-8'); ?>" style="font-weight: 700; color: var(--text); font-size: 12.5px; line-height: 1.3; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                                <?php echo htmlspecialchars($pTitle); ?>
                                            </div>
                                            <div style="font-size: 10.5px; color: var(--text2); font-family: var(--font-mono, monospace); margin-top: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                                #REQ-<?php echo str_pad((string)$req['event_id'], 4, '0', STR_PAD_LEFT); ?> &bull; <?php echo htmlspecialchars(!empty($req['chapel_location']) ? $req['chapel_location'] : 'Cathedral Parish Office'); ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>

                                <td style="padding: 10px 14px;">
                                    <?php
                                        $rawCertType = trim((string)($req['event_type'] ?? ''));
                                        $displayCertCategory = ($rawCertType !== '' && $rawCertType !== 'Other')
                                            ? $rawCertType
                                            : 'Ecclesiastical Certificate';
                                    ?>
                                    <div style="font-size: 11.5px; color: var(--text); font-weight: 500;">
                                        <?php echo htmlspecialchars($displayCertCategory); ?>
                                    </div>
                                    <div style="font-size: 10.5px; color: var(--text2); margin-top: 1px;">
                                        Pickup Window: Tue&ndash;Sun
                                    </div>
                                </td>

                                <td style="padding: 10px 14px; font-size: 11.5px; color: var(--text); font-weight: 500; white-space: nowrap;">
                                    <i class="fa-regular fa-calendar-check" style="color: var(--accent); margin-right: 5px;"></i>
                                    <?php echo htmlspecialchars($pickupTime); ?>
                                </td>

                                <td style="padding: 10px 14px; font-size: 12.5px; font-weight: 700; color: var(--text); text-align: right; white-space: nowrap;">
                                    <div>₱<?php echo number_format($fee, 2); ?></div>
                                    <?php if ($fee > 0): ?>
                                        <span style="display: inline-block; margin-top: 2px; font-size: 9.5px; font-weight: 700; padding: 1px 6px; border-radius: 4px; <?php echo $isPaidFee ? 'background: rgba(46,158,91,0.12); color: #16a34a;' : 'background: rgba(197,160,89,0.14); color: #9a7418;'; ?>">
                                            <?php echo $isPaidFee ? 'PAID' : ($paidFee > 0 ? 'PARTIAL' : 'UNPAID'); ?>
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <td style="padding: 10px 12px; text-align: center; white-space: nowrap;">
                                    <?php if ($isCompleted): ?>
                                        <span class="badge" style="background: rgba(59,130,246,0.12); color: #2563eb; border: 1px solid rgba(59,130,246,0.3); font-size: 10.5px; font-weight: 600; padding: 2px 8px; border-radius: 6px;">
                                            Released
                                        </span>
                                    <?php elseif ($isConfirmed): ?>
                                        <span class="badge" style="background: rgba(46,158,91,0.12); color: #2E9E5B; border: 1px solid rgba(46,158,91,0.3); font-size: 10.5px; font-weight: 600; padding: 2px 8px; border-radius: 6px;">
                                            Ready
                                        </span>
                                    <?php elseif ($isCancelled): ?>
                                        <span class="badge" style="background: rgba(239,68,68,0.1); color: #ef4444; border: 1px solid rgba(239,68,68,0.25); font-size: 10.5px; font-weight: 600; padding: 2px 8px; border-radius: 6px;">
                                            <?php echo htmlspecialchars($st); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="badge" style="background: rgba(197,160,89,0.14); color: var(--accent, #c5a059); border: 1px solid rgba(197,160,89,0.3); font-size: 10.5px; font-weight: 600; padding: 2px 8px; border-radius: 6px;">
                                            Pending
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <td style="padding: 10px 16px; text-align: center;" onclick="event.stopPropagation();">
                                    <div class="portal-dropdown-wrapper" style="position: relative; display: inline-block;">
                                        <button class="btn-icon" onclick="togglePortalDropdown(this)" title="Actions" style="width: 28px; height: 28px; border-radius: 6px; border: 1px solid var(--border); background: #FFFFFF; color: var(--text); cursor: pointer;">
                                            <i class="fa-solid fa-ellipsis-vertical" style="font-size: 11px;"></i>
                                        </button>
                                        <div class="dropdown-menu" style="display: none; position: absolute; top: calc(100% + 4px); right: 0; left: auto; background: #FFFFFF; border: 1px solid var(--border); border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); z-index: 50; min-width: 175px; padding: 4px; text-align: left;">
                                            <a class="dropdown-item" href="javascript:void(0)" onclick="viewCertDetailsFromRow(this.closest('tr'))" style="padding: 6px 10px; font-size: 11.5px; display: flex; align-items: center; gap: 6px; text-decoration: none; color: var(--text); border-radius: 4px;">
                                                <i class="fa-solid fa-eye" style="color: var(--accent);"></i> View Details
                                            </a>
                                            <?php if ($isAdmin && $st === 'Pending'): ?>
                                            <a class="dropdown-item" href="javascript:void(0)" data-id="<?php echo (int)$req['event_id']; ?>" onclick="approveCertRequest(this)" style="padding: 6px 10px; font-size: 11.5px; display: flex; align-items: center; gap: 6px; text-decoration: none; color: #16a34a; border-radius: 4px;">
                                                <i class="fa-solid fa-circle-check"></i> Mark as Ready
                                            </a>
                                            <?php endif; ?>
                                            <?php if ($isAdmin && $st === 'Confirmed'): ?>
                                            <a class="dropdown-item" href="javascript:void(0)" data-id="<?php echo (int)$req['event_id']; ?>" onclick="resendCertPickupNotice(this)" style="padding: 6px 10px; font-size: 11.5px; display: flex; align-items: center; gap: 6px; text-decoration: none; color: #b45309; border-radius: 4px;">
                                                <i class="fa-solid fa-paper-plane"></i> Resend Pickup Notice
                                            </a>
                                            <a class="dropdown-item" href="javascript:void(0)" data-id="<?php echo (int)$req['event_id']; ?>" onclick="completeCertRequest(this)" style="padding: 6px 10px; font-size: 11.5px; display: flex; align-items: center; gap: 6px; text-decoration: none; color: #2563eb; border-radius: 4px;">
                                                <i class="fa-solid fa-box-open"></i> Mark as Released
                                            </a>
                                            <?php endif; ?>
                                            <?php if ($isAdmin && $fee > 0 && !$isPaidFee): ?>
                                            <a class="dropdown-item" href="javascript:void(0)" data-id="<?php echo (int)$req['event_id']; ?>" data-title="<?php echo htmlspecialchars($req['title'], ENT_QUOTES, 'UTF-8'); ?>" data-balance="<?php echo number_format(max(0, $fee - $paidFee), 2, '.', ''); ?>" onclick="openCertPaymentModal(this)" style="padding: 6px 10px; font-size: 11.5px; display: flex; align-items: center; gap: 6px; text-decoration: none; color: #16a34a; border-radius: 4px;">
                                                <i class="fa-solid fa-peso-sign"></i> Record Payment
                                            </a>
                                            <?php endif; ?>
                                            <?php if ($isAdmin): ?>
                                            <a class="dropdown-item" href="dashboard.php?section=<?php echo $recordsSection; ?>&amp;search=<?php echo urlencode($searchPerson); ?>" style="padding: 6px 10px; font-size: 11.5px; display: flex; align-items: center; gap: 6px; text-decoration: none; color: var(--text); border-radius: 4px;">
                                                <i class="fa-solid fa-folder-open" style="color: var(--accent);"></i> Find in Records
                                            </a>
                                            <?php endif; ?>
                                            <?php if ($canChatApplicant): ?>
                                            <a class="dropdown-item" href="javascript:void(0)" onclick="openCertApplicantChat(<?php echo $reqOwnerUid; ?>)" style="padding: 6px 10px; font-size: 11.5px; display: flex; align-items: center; gap: 6px; text-decoration: none; color: var(--text); border-radius: 4px;">
                                                <i class="fa-solid fa-comments" style="color: var(--accent);"></i> Chat Applicant
                                            </a>
                                            <?php elseif (!$isAdmin && !$isGuest): ?>
                                            <a class="dropdown-item" href="javascript:void(0)" onclick="if (window.ParishChat) { ParishChat.toggleChat(true); } else if (typeof navigateToSection === 'function') { navigateToSection('public_inquiries', true); } else { window.location.href = 'dashboard.php?section=public_inquiries'; }" style="padding: 6px 10px; font-size: 11.5px; display: flex; align-items: center; gap: 6px; text-decoration: none; color: var(--text); border-radius: 4px;">
                                                <i class="fa-solid fa-comments" style="color: var(--accent);"></i> Message Parish Office
                                            </a>
                                            <?php endif; ?>
                                            <?php if ($st === 'Pending'): ?>
                                            <a class="dropdown-item" href="javascript:void(0)" data-id="<?php echo (int)$req['event_id']; ?>" data-title="<?php echo htmlspecialchars($req['title'], ENT_QUOTES, 'UTF-8'); ?>" onclick="cancelCertRequest(this)" style="padding: 6px 10px; font-size: 11.5px; display: flex; align-items: center; gap: 6px; text-decoration: none; color: #dc2626; border-radius: 4px;">
                                                <i class="fa-solid fa-ban"></i> Cancel Request
                                            </a>
                                            <?php endif; ?>
                                            <?php if ($canDeleteRow): ?>
                                            <a class="dropdown-item" href="javascript:void(0)" data-id="<?php echo (int)$req['event_id']; ?>" data-title="<?php echo htmlspecialchars($req['title'], ENT_QUOTES, 'UTF-8'); ?>" onclick="deleteCertRequest(this)" style="padding: 6px 10px; font-size: 11.5px; display: flex; align-items: center; gap: 6px; text-decoration: none; color: #ef4444; border-radius: 4px;">
                                                <i class="fa-solid fa-trash"></i> Delete
                                            </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if ($isGuest): ?>
    <!-- ── TAB: GUEST SPECTATOR CATALOG & REQUIREMENTS ── -->
    <div id="cert-view-spectate" class="cert-tab-pane" style="display: <?php echo ($initialCertTab === 'spectate') ? 'flex' : 'none'; ?>; flex-direction: column; width: 100%; align-items: center;">
        <div style="width: 100%; max-width: 860px; border-radius: 12px; background: var(--surface); border: 1px solid var(--border); box-shadow: 0 1px 4px rgba(0,0,0,0.04); overflow: hidden; margin-top: 4px;">
            
            <div style="padding: 18px 24px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; background: var(--bg2);">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <div style="width: 34px; height: 34px; border-radius: 8px; background: rgba(201,168,76,0.14); color: var(--accent); display: flex; align-items: center; justify-content: center; font-size: 15px;">
                        <i class="fa-solid fa-book-bookmark"></i>
                    </div>
                    <div>
                        <h2 style="font-size: 14.5px; font-weight: 700; color: var(--text); margin: 0; line-height: 1.2;">
                            Ecclesiastical Certificates &amp; Parish Archives (Guest View)
                        </h2>
                        <p style="font-size: 11.5px; color: var(--text2); margin: 2px 0 0 0;">
                            Official canonical document guidelines &bull; Physical pickup at Cathedral Parish Office
                        </p>
                    </div>
                </div>
                <button type="button" class="btn btn-primary btn-sm" onclick="openGuestAuthModal('request an official certificate')" style="font-size: 11.5px; padding: 5px 12px; border-radius: 6px; cursor: pointer; display: inline-flex; align-items: center; gap: 6px;">
                    <i class="fa-solid fa-lock text-gold"></i> Request Document
                </button>
            </div>

            <div style="padding: 24px 28px; display: flex; flex-direction: column; gap: 20px;">
                <!-- Guest Notice Banner -->
                <div style="background: rgba(201,168,76,0.08); border: 1px solid rgba(201,168,76,0.25); border-radius: 10px; padding: 14px 16px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <i class="fa-solid fa-circle-info" style="color: var(--accent); font-size: 18px; flex-shrink: 0;"></i>
                        <div style="font-size: 12px; color: var(--text);">
                            <strong>Viewing in Spectator Mode:</strong> To apply online, track issuance progress, and receive email/SMS pickup notices, please sign in or register as a Cathedral member.
                        </div>
                    </div>
                    <div style="display: flex; gap: 8px;">
                        <a href="register.php" class="btn btn-primary btn-sm" style="font-size: 11.5px; padding: 5px 12px; text-decoration: none; border-radius: 6px;">
                            <i class="fa-solid fa-user-plus"></i> Register
                        </a>
                        <a href="logout.php?redirect=index.php?open_login=1" class="btn btn-ghost btn-sm" style="font-size: 11.5px; padding: 5px 12px; text-decoration: none; border: 1px solid var(--border); border-radius: 6px;">
                            <i class="fa-solid fa-arrow-right-to-bracket"></i> Sign In
                        </a>
                    </div>
                </div>

                <!-- Certificate Cards -->
                <div>
                    <div style="font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text2); margin-bottom: 12px; display: flex; align-items: center; gap: 6px;">
                        <i class="fa-solid fa-stamp" style="color: var(--accent);"></i> Available Document Types &amp; Standard Fees
                    </div>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 12px;">
                        <?php foreach ($certVariants as $cv): ?>
                        <div style="background: var(--bg2); border: 1px solid var(--border); border-radius: 10px; padding: 14px 16px; display: flex; flex-direction: column; justify-content: space-between; gap: 8px;">
                            <div>
                                <div style="font-size: 13px; font-weight: 700; color: var(--text);"><?php echo htmlspecialchars($cv['name']); ?></div>
                                <div style="font-size: 11px; color: var(--text2); margin-top: 3px;">Official sealed parish record copy</div>
                            </div>
                            <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid var(--border); padding-top: 8px; margin-top: 4px;">
                                <span style="font-size: 11px; color: var(--text2);">Parish Fee:</span>
                                <span style="font-size: 13px; font-weight: 700; color: var(--accent);">₱<?php echo number_format($cv['price'], 2); ?></span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Requirements and Procedures -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                    <div style="background: var(--surface); border: 1px solid var(--border); border-radius: 10px; padding: 14px 16px;">
                        <div style="font-size: 11.5px; font-weight: 700; color: var(--text); margin-bottom: 8px; display: flex; align-items: center; gap: 6px;">
                            <i class="fa-solid fa-list-check" style="color: var(--accent);"></i> Requirements to Apply
                        </div>
                        <ul style="margin: 0; padding-left: 18px; font-size: 11.5px; color: var(--text2); line-height: 1.6;">
                            <li>Full canonical name of the record holder</li>
                            <li>Sacrament year and approximate month</li>
                            <li>Names of parents / sponsors on file</li>
                            <li>Specific purpose for request (School, Marriage, Legal, etc.)</li>
                        </ul>
                    </div>
                    <div style="background: var(--surface); border: 1px solid var(--border); border-radius: 10px; padding: 14px 16px;">
                        <div style="font-size: 11.5px; font-weight: 700; color: var(--text); margin-bottom: 8px; display: flex; align-items: center; gap: 6px;">
                            <i class="fa-solid fa-building-columns" style="color: var(--accent);"></i> Office Pickup &amp; Release
                        </div>
                        <ul style="margin: 0; padding-left: 18px; font-size: 11.5px; color: var(--text2); line-height: 1.6;">
                            <li>Processing window: 2 to 3 office working days</li>
                            <li>Cathedral Parish Office: Tue&ndash;Sun, 8:00 AM &ndash; 5:00 PM</li>
                            <li>Closed Mondays &amp; Cathedral Feast Days</li>
                            <li>Present valid government ID upon pickup</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!$isGuest): ?>
    <!-- ── TAB 2: APPLY FOR CERTIFICATE (Clean, Unified Layout) ── -->
    <div id="cert-view-apply" class="cert-tab-pane" style="display: <?php echo ($initialCertTab === 'apply') ? 'flex' : 'none'; ?>; flex-direction: column; width: 100%; align-items: center;">
        <div style="width: 100%; max-width: 820px; border-radius: 12px; background: #FFFFFF; border: 1px solid var(--border); box-shadow: 0 1px 4px rgba(0,0,0,0.04); overflow: hidden; margin-top: 4px;">
            
            <!-- Clean Header -->
            <div style="padding: 16px 24px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; background: #FAFAFA;">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <div style="width: 32px; height: 32px; border-radius: 8px; background: rgba(201,168,76,0.12); color: var(--accent); display: flex; align-items: center; justify-content: center; font-size: 14px;">
                        <i class="fa-solid fa-file-invoice"></i>
                    </div>
                    <div>
                        <h2 style="font-size: 14px; font-weight: 700; color: var(--text); margin: 0; line-height: 1.2;">
                            <?php echo $isAdmin ? 'Record Certificate Request' : 'Request a Certificate'; ?>
                        </h2>
                        <p style="font-size: 11px; color: var(--text2); margin: 2px 0 0 0;">
                            Official parish document &bull; Releasing at Parish Office (Tue&ndash;Sun, 8am&ndash;5pm)
                        </p>
                    </div>
                </div>
                <button type="button" class="btn btn-ghost btn-sm" onclick="switchCertTab('ledger')" style="font-size: 11.5px; padding: 4px 10px; border: 1px solid var(--border); border-radius: 6px; cursor: pointer; color: var(--text2);">
                    <i class="fa-solid fa-arrow-left" style="margin-right: 4px;"></i> Back to Queue
                </button>
            </div>

            <!-- Single Unified Form Body - Free of Box-Inside-Box Clutter -->
            <div style="padding: 24px 28px;">
                <form method="POST" action="dashboard.php?section=request_certificate" id="cert-application-form" style="display: flex; flex-direction: column; gap: 20px; margin: 0;">
                    <input type="hidden" name="form_section" value="certificate_request">
                    <input type="hidden" name="redirect_section" value="request_certificate">
                    <input type="hidden" name="action" value="request_event">
                    <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
                    <input type="hidden" name="service_id" value="<?php echo $certServiceId; ?>">
                    <input type="hidden" name="variant_id" id="cert-field-variant-id" value="<?php echo $certVariants[0]['id'] ?? 19; ?>">
<?php
    $isGlobalAdminCert = in_array($role, ['Admin', 'Cathedral Admin'], true);
    $userChapelCert = (int)($_SESSION['chapel_id'] ?? ($chapel_id ?? 0));
    $certChapelsList = $chapels ?? [];
    $isLockedBranchCert = (!$isGlobalAdminCert && $isAdmin && $userChapelCert > 0);
    $defaultCertChapelId = ($isLockedBranchCert || (!$isGlobalAdminCert && $userChapelCert > 0)) ? $userChapelCert : 0;
?>
                    <input type="hidden" name="event_type" value="Ecclesiastical Certificates">
                    <?php if ($isLockedBranchCert): ?>
                    <input type="hidden" name="chapel_id" id="cert-field-chapel-id" value="<?php echo $userChapelCert; ?>">
                    <input type="hidden" name="event_scope" id="cert-field-scope" value="chapel">
                    <?php else: ?>
                    <input type="hidden" name="event_scope" id="cert-field-scope" value="<?php echo $defaultCertChapelId > 0 ? 'chapel' : 'cathedral'; ?>">
                    <?php endif; ?>
                    <input type="hidden" name="custom_duration" value="0">
                    <input type="hidden" name="location_type" value="Church">
                    <input type="hidden" name="start_time" id="cert-field-datetime" value="<?php echo date('Y-m-d', strtotime('+2 days')) . 'T08:00'; ?>">
                    <input type="hidden" name="title" id="cert-field-title" value="Baptismal Certificate Request">
                    <input type="hidden" name="linked_record_id" id="cert-linked-record-id" value="">

                    <!-- 1. Document & Record Section -->
                    <div>
                        <div style="font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text2); margin-bottom: 12px; display: flex; align-items: center; gap: 6px;">
                            <i class="fa-solid fa-file-lines" style="color: var(--accent);"></i>
                            <span>1. Document &amp; Person on Record</span>
                        </div>

                        <div style="display: grid; grid-template-columns: 2.5fr 1fr; gap: 14px; margin-bottom: 14px;">
                            <div>
                                <label style="font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; color: var(--text2); display: block; margin-bottom: 4px;">
                                    Document Requested <span style="color:#ef4444;">*</span>
                                </label>
                                <select id="cert-variant-selector" onchange="onCertVariantChange(this)" style="padding: 6px 12px; height: 36px; font-size: 12.5px; box-sizing: border-box; width: 100%; border-radius: 6px; background: #FFFFFF; color: var(--text); border: 1px solid var(--border); font-weight: 600;">
                                    <?php foreach ($certVariants as $cv): ?>
                                        <option value="<?php echo $cv['id']; ?>" data-name="<?php echo htmlspecialchars($cv['name']); ?>" data-price="<?php echo $cv['price']; ?>">
                                            <?php echo htmlspecialchars($cv['name']); ?> &mdash; PHP <?php echo number_format($cv['price'], 2); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div>
                                <label style="font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; color: var(--text2); display: block; margin-bottom: 4px;">
                                    Copies <span style="font-weight: 400; color: var(--text2);">(Qty)</span>
                                </label>
                                <input type="number" id="cert-copies-input" name="certificate_copies" min="1" max="25" value="1" oninput="onCertCopiesChange(this.value)" 
                                       style="padding: 6px 12px; height: 36px; font-size: 13px; box-sizing: border-box; width: 100%; border-radius: 6px; background: #FFFFFF; color: var(--text); border: 1px solid var(--border); font-weight: 700; text-align: center;">
                            </div>
                        </div>

                        <div style="display: grid; grid-template-columns: 2.5fr 1fr; gap: 14px; margin-bottom: 14px;">
                            <div id="cert-record-search-wrap" style="position: relative;">
                                <label style="font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; color: var(--text2); display: block; margin-bottom: 4px;">
                                    Person's Name on Record <span style="color:#ef4444;">*</span>
                                    <?php if ($isAdmin): ?>
                                    <span style="font-weight: 500; text-transform: none; letter-spacing: 0; color: var(--text2); margin-left: 4px;">(Type to match archives)</span>
                                    <?php endif; ?>
                                </label>
                                <input type="text" id="cert-person-name" name="cert_person_name" required maxlength="80" autocomplete="off"
                                       placeholder="e.g. Juan dela Cruz (Full name as recorded in archives)" 
                                       oninput="this.value = this.value.replace(/[^a-zA-ZñÑ\s\.\-']/g, ''); const lr = document.getElementById('cert-linked-record-id'); if (lr) lr.value = ''; syncCertDynamicTitle();<?php echo $isAdmin ? ' handleCertRecordNameInput();' : ''; ?>"
                                       <?php if ($isAdmin): ?>
                                       onfocus="if (this.value.trim().length > 0) handleCertRecordNameInput();"
                                       onkeydown="handleCertSuggestionKeydown(event, 'cert-record-suggestions')"
                                       <?php endif; ?>
                                       style="padding: 6px 12px; height: 36px; font-size: 12.5px; box-sizing: border-box; width: 100%; border-radius: 6px; background: #FFFFFF; color: var(--text); border: 1px solid var(--border);">
                                <?php if ($isAdmin): ?>
                                <div id="cert-record-suggestions" role="listbox" style="display: none; position: absolute; top: calc(100% + 4px); left: 0; right: 0; max-height: 250px; overflow-y: auto; background: #FFFFFF; border: 1px solid var(--border); border-radius: 8px; box-shadow: 0 10px 25px rgba(0,0,0,0.12); z-index: 60;"></div>
                                <?php endif; ?>
                            </div>

                            <div>
                                <label style="font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; color: var(--text2); display: block; margin-bottom: 4px;">
                                    Approx. Year <span style="font-weight: 400; color: var(--text2);">(Optional)</span>
                                </label>
                                <input type="number" id="cert-year-input" name="cert_year" min="1900" max="2099" placeholder="e.g. 2019" oninput="syncCertDynamicTitle()" 
                                       style="padding: 6px 12px; height: 36px; font-size: 12px; box-sizing: border-box; width: 100%; border-radius: 6px; background: #FFFFFF; color: var(--text); border: 1px solid var(--border); text-align: center;">
                            </div>
                        </div>

                        <div>
                            <label style="font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; color: var(--text2); display: block; margin-bottom: 4px;">
                                Purpose of Request <span style="color:#ef4444;">*</span>
                            </label>
                            <select id="cert-purpose-select" name="cert_purpose" onchange="syncCertDynamicTitle()" style="padding: 6px 12px; height: 36px; font-size: 12px; box-sizing: border-box; width: 100%; border-radius: 6px; background: #FFFFFF; color: var(--text); border: 1px solid var(--border);">
                                <option value="Personal Records">Personal Records / File Copy</option>
                                <option value="Marriage Requirement">Marriage Requirement</option>
                                <option value="School / Enrollment">School / University Requirement</option>
                                <option value="Confirmation / Holy Orders">Church Requirement</option>
                                <option value="Passport / Visa / Travel">Passport / Visa / Embassy</option>
                                <option value="Employment / Legal">Employment / Legal</option>
                            </select>
                        </div>
                    </div>

                    <div style="height: 1px; background: var(--border);"></div>

                    <!-- 2. Pickup & Contact Section -->
                    <div>
                        <div style="font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text2); margin-bottom: 12px; display: flex; align-items: center; justify-content: space-between;">
                            <span style="display: flex; align-items: center; gap: 6px;">
                                <i class="fa-solid fa-calendar-check" style="color: var(--accent);"></i>
                                <span>2. Claiming &amp; Contact Information</span>
                            </span>
                            <span style="font-size: 11px; color: #2E9E5B; font-weight: 600; text-transform: none;">
                                <i class="fa-solid fa-circle-check"></i> Standard 2-day preparation
                            </span>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 14px;">
                            <div>
                                <label style="font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; color: var(--text2); display: block; margin-bottom: 4px;">
                                    Target Office Pickup Date <span style="color:#ef4444;">*</span>
                                </label>
                                <input type="date" id="cert-pickup-date-input"
                                       min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" max="2099-12-31"
                                       value="<?php
                                           $defaultPickupTs = strtotime('+2 days');
                                           if ((int)date('N', $defaultPickupTs) === 1) {
                                               $defaultPickupTs = strtotime('+3 days');
                                           }
                                           echo date('Y-m-d', $defaultPickupTs);
                                       ?>"
                                       onchange="const pEl = this; if (pEl.value) { const pd = new Date(pEl.value + 'T00:00:00'); if (pd.getDay() === 1) { const nextTue = new Date(pd.getTime() + 86400000); const tueStr = nextTue.getFullYear() + '-' + String(nextTue.getMonth()+1).padStart(2,'0') + '-' + String(nextTue.getDate()).padStart(2,'0'); pEl.value = tueStr; const wEl = document.getElementById('cert-monday-warning'); if (wEl) { wEl.style.display = 'block'; setTimeout(() => { wEl.style.display = 'none'; }, 5000); } } } onCertPickupDateChange(pEl.value)"
                                       required
                                       style="padding: 6px 12px; height: 36px; font-size: 12.5px; box-sizing: border-box; width: 100%; border-radius: 6px; border: 1px solid var(--border); background: #FFFFFF; color: var(--text); font-weight: 500;">
                                <div id="cert-monday-warning" style="display: none; font-size: 11px; color: #b45309; margin-top: 4px; font-weight: 600;">
                                    <i class="fa-solid fa-circle-info"></i> Parish office is closed Mondays; adjusted to Tuesday.
                                </div>
                                <div style="font-size: 11px; color: var(--text2); margin-top: 5px;">
                                    <i class="fa-regular fa-clock" style="color: var(--accent); margin-right: 3px;"></i> Releasing: Tue&ndash;Sun, 8am&ndash;12pm &amp; 1:30pm&ndash;5pm (Closed Mondays)
                                </div>
                            </div>

                            <div>
                                <label style="font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; color: var(--text2); display: block; margin-bottom: 4px;">
                                    Mobile Phone <span style="font-weight: 400; color: var(--text2);">(Optional)</span>
                                </label>
                                <input type="tel" name="requester_phone" placeholder="0912-345-6789" maxlength="15" pattern="[0-9\+\s\-]{10,20}" 
                                       style="padding: 6px 12px; height: 36px; font-size: 12px; box-sizing: border-box; width: 100%; border-radius: 6px; border: 1px solid var(--border); background: #FFFFFF; color: var(--text);">
                                <div style="font-size: 11px; color: var(--text2); margin-top: 5px;">
                                    <i class="fa-solid fa-id-card" style="color: var(--accent); margin-right: 3px;"></i> Valid ID required upon pickup (or authorization letter)
                                </div>
                            </div>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                            <div>
                                <label style="font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; color: var(--text2); display: block; margin-bottom: 4px;">
                                    Email Address <span style="font-weight: 400; color: var(--text2);">(Optional)</span>
                                </label>
                                <input type="email" name="requester_email" maxlength="100" placeholder="e.g. juan@email.com" 
                                       style="padding: 6px 12px; height: 36px; font-size: 12px; box-sizing: border-box; width: 100%; border-radius: 6px; border: 1px solid var(--border); background: #FFFFFF; color: var(--text);">
                            </div>
                            <div>
                                <label style="font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; color: var(--text2); display: block; margin-bottom: 4px;">
                                    Issuing / Pickup Office
                                </label>
                                <?php if ($isLockedBranchCert):
                                    $lockedChapelName = 'Assigned Chapel Office';
                                    foreach ($certChapelsList as $cc) {
                                        if ((int)$cc['chapel_id'] === $userChapelCert) {
                                            $lockedChapelName = $cc['chapel_name'];
                                            break;
                                        }
                                    }
                                ?>
                                <input type="text" readonly value="<?php echo htmlspecialchars($lockedChapelName); ?>"
                                       style="padding: 6px 12px; height: 36px; font-size: 12px; box-sizing: border-box; width: 100%; border-radius: 6px; border: 1px solid var(--border); background: #f8fafc; color: var(--text2); font-weight: 600;">
                                <?php else: ?>
                                <select name="chapel_id" id="cert-chapel-selector" onchange="onCertChapelChange(this)"
                                        style="padding: 6px 12px; height: 36px; font-size: 12px; box-sizing: border-box; width: 100%; border-radius: 6px; border: 1px solid var(--border); background: #FFFFFF; color: var(--text); font-weight: 500;">
                                    <option value="0" <?php echo $defaultCertChapelId === 0 ? 'selected' : ''; ?>>Cathedral Parish Office (Main)</option>
                                    <?php foreach ($certChapelsList as $cc): ?>
                                        <option value="<?php echo (int)$cc['chapel_id']; ?>" <?php echo $defaultCertChapelId === (int)$cc['chapel_id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($cc['chapel_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div style="height: 1px; background: var(--border);"></div>

                    <!-- 3. Clean Unified Summary & Action Bar -->
                    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px; background: #FAFAFA; border: 1px solid var(--border); border-radius: 8px; padding: 14px 18px;">
                        <div>
                            <div style="font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; color: var(--text2);">
                                Document Issuance Fee
                            </div>
                            <div style="display: flex; align-items: baseline; gap: 8px; margin-top: 2px;">
                                <span id="cert-summary-total-fee" style="font-size: 18px; font-weight: 800; color: #111111;">PHP 200.00</span>
                                <span style="font-size: 11.5px; color: var(--text2);">
                                    (<span id="cert-summary-unit-rate">PHP 200.00</span> &times; <span id="cert-summary-copies">1 copy</span> &bull; Pay at office)
                                </span>
                            </div>
                        </div>

                        <button type="button" class="btn btn-primary" onclick="openCertConfirmModal()" 
                                style="height: 40px; padding: 0 24px; font-size: 13px; font-weight: 700; background: #111111; color: #FFFFFF; border: none; border-radius: 6px; cursor: pointer; display: inline-flex; align-items: center; gap: 8px;">
                            <i class="fa-solid fa-file-signature"></i>
                            <span><?php echo $isAdmin ? 'Record Request' : 'Review &amp; Submit Application'; ?></span>
                        </button>
                    </div>

                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

</div><!-- /.workspace -->

<!-- ─── CONFIRMATION MODAL ─── -->
<div class="modal-overlay" id="cert-confirm-modal" style="z-index: 10000;">
    <div class="modal" style="max-width: 520px; width: 92%; padding: 0; overflow: hidden; border-radius: 12px; background: var(--surface); border: 1px solid var(--border); box-shadow: var(--shadow-lg);">
        <div style="background: var(--surface); padding: 18px 22px; border-bottom: 1px solid var(--border); display: flex; align-items: center; gap: 14px;">
            <div style="width: 40px; height: 40px; background: var(--bg3); color: var(--accent); border: 1px solid var(--border); border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 16px; flex-shrink: 0;">
                <i class="fa-solid fa-file-invoice"></i>
            </div>
            <div>
                <h3 style="font-family: var(--font-family, 'Inter', sans-serif); font-size: 16px; font-weight: 700; color: var(--text); margin: 0;">Review Certificate Application</h3>
                <p style="font-size: 11.5px; color: var(--text2); margin: 2px 0 0;">Parish Archives &bull; Please review your details before submitting</p>
            </div>
        </div>

        <div style="padding: 20px; max-height: 60vh; overflow-y: auto; background: var(--surface);">
            <div id="cert-confirm-content" style="display: flex; flex-direction: column; gap: 12px;"></div>
        </div>

        <div style="padding: 14px 20px; display: flex; gap: 10px; background: var(--surface); border-top: 1px solid var(--border);">
            <button type="button" class="btn btn-gray" onclick="closeCertConfirmModal()" style="flex: 1; border-radius: 8px;">
                <i class="fa-solid fa-arrow-left"></i> Go Back
            </button>
            <button type="button" class="btn btn-primary" onclick="submitCertForm()" style="flex: 1; border-radius: 8px;">
                <i class="fa-solid fa-check"></i> Confirm &amp; Submit
            </button>
        </div>
    </div>
</div>

<!-- ─── DOSSIER VIEW MODAL ─── -->
<div class="modal-overlay" id="cert-view-modal" style="z-index: 10000;">
    <div class="modal" style="max-width: 560px; width: 94%; max-height: 88vh; display: flex; flex-direction: column; padding: 0; border-radius: 12px; background: var(--surface); border: 1px solid var(--border); box-shadow: var(--shadow-lg); overflow: hidden;">
        <div style="padding: 15px 20px; background: var(--surface); border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; gap: 10px;">
            <div style="display: flex; align-items: center; gap: 10px; min-width: 0;">
                <i class="fa-solid fa-certificate" style="color: var(--accent);"></i>
                <h3 id="cert-view-modal-title" style="font-family: var(--font-family, 'Inter', sans-serif); font-size: 15px; font-weight: 700; color: var(--text); margin: 0;">Certificate Details</h3>
                <span id="cert-view-unsaved-badge" style="display: none; font-size: 10.5px; font-weight: 700; color: #b45309; background: rgba(245,158,11,0.14); border: 1px solid rgba(245,158,11,0.35); padding: 2px 8px; border-radius: 999px; white-space: nowrap;">
                    <i class="fa-solid fa-circle-dot" style="font-size: 8px; margin-right: 3px;"></i> Unsaved changes
                </span>
            </div>
            <button class="btn-icon" onclick="closeCertViewModal()" title="Close" style="border: none; background: transparent; cursor: pointer; color: var(--text2);">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div id="cert-view-body" style="padding: 18px 20px; display: flex; flex-direction: column; gap: 12px; flex: 1; overflow-y: auto; background: var(--surface);"></div>
        <div id="cert-view-footer" style="padding: 12px 20px; background: var(--surface); border-top: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
            <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;"></div>
            <button class="btn btn-gray" onclick="closeCertViewModal()" style="padding: 0 18px; height: 36px; border-radius: 8px;">Close</button>
        </div>
    </div>
</div>

<!-- ─── DELETE / CANCEL MODALS ─── -->
<div class="modal-overlay" id="cert-delete-modal" style="z-index: 10000;">
    <div class="modal" style="max-width: 400px; width: 90%; padding: 24px; border-radius: 12px; background: var(--surface); border: 1px solid var(--border); box-shadow: var(--shadow-lg);">
        <div style="text-align: center; margin-bottom: 18px;">
            <div style="width: 44px; height: 44px; background: rgba(239,68,68,0.12); color: #ef4444; border: 1px solid rgba(239,68,68,0.25); border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 20px; margin: 0 auto 12px;">
                <i class="fa-solid fa-trash"></i>
            </div>
            <h3 style="font-family: var(--font-family, 'Inter', sans-serif); font-size: 16px; font-weight: 700; color: var(--text); margin: 0 0 6px;">Delete Application?</h3>
            <p style="font-size: 12px; color: var(--text2); margin: 0;">Delete "<span id="cert-delete-title-span"></span>"?</p>
        </div>
        <div style="display: flex; gap: 10px;">
            <button class="btn btn-gray" onclick="closeCertDeleteModal()" style="flex: 1; border-radius: 8px;">Cancel</button>
            <form method="POST" action="dashboard.php?section=request_certificate" style="flex: 1;">
                <input type="hidden" name="form_section" value="certificate_request">
                <input type="hidden" name="redirect_section" value="request_certificate">
                <input type="hidden" name="action" value="delete_request">
                <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
                <input type="hidden" name="event_id" id="cert-delete-id-input">
                <button type="submit" class="btn btn-danger" style="width: 100%; border-radius: 8px;"><i class="fa-solid fa-trash"></i> Delete</button>
            </form>
        </div>
    </div>
</div>

<div class="modal-overlay" id="cert-cancel-modal" style="z-index: 10000;">
    <div class="modal" style="max-width: 400px; width: 90%; padding: 24px; border-radius: 12px; background: var(--surface); border: 1px solid var(--border); box-shadow: var(--shadow-lg);">
        <div style="text-align: center; margin-bottom: 18px;">
            <div style="width: 44px; height: 44px; background: rgba(220,38,38,0.12); color: #dc2626; border: 1px solid rgba(220,38,38,0.25); border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 20px; margin: 0 auto 12px;">
                <i class="fa-solid fa-ban"></i>
            </div>
            <h3 style="font-family: var(--font-family, 'Inter', sans-serif); font-size: 16px; font-weight: 700; color: var(--text); margin: 0 0 6px;">Cancel Application?</h3>
            <p style="font-size: 12px; color: var(--text2); margin: 0;">Cancel "<span id="cert-cancel-title-span"></span>"?</p>
        </div>
        <div style="display: flex; gap: 10px;">
            <button class="btn btn-gray" onclick="closeCertCancelModal()" style="flex: 1; border-radius: 8px;">Keep Application</button>
            <form method="POST" action="dashboard.php?section=request_certificate" style="flex: 1;">
                <input type="hidden" name="form_section" value="certificate_request">
                <input type="hidden" name="redirect_section" value="request_certificate">
                <input type="hidden" name="action" value="cancel_request">
                <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
                <input type="hidden" name="event_id" id="cert-cancel-id-input">
                <button type="submit" class="btn btn-danger btn-red" style="width: 100%; border-radius: 8px; background: #dc2626 !important; border-color: #dc2626 !important; color: #fff !important;"><i class="fa-solid fa-ban"></i> Yes, Cancel</button>
            </form>
        </div>
    </div>
</div>

<form id="cert-approve-form" method="POST" action="dashboard.php?section=request_certificate" style="display: none;">
    <input type="hidden" name="form_section" value="review_request">
    <input type="hidden" name="redirect_section" value="request_certificate">
    <input type="hidden" name="action" value="approve">
    <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
    <input type="hidden" name="id" id="cert-approve-id-input">
</form>

<form id="cert-complete-form" method="POST" action="dashboard.php?section=request_certificate" style="display: none;">
    <input type="hidden" name="form_section" value="review_request">
    <input type="hidden" name="redirect_section" value="request_certificate">
    <input type="hidden" name="action" value="complete">
    <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
    <input type="hidden" name="id" id="cert-complete-id-input">
</form>

<form id="cert-resend-form" method="POST" action="dashboard.php?section=request_certificate" style="display: none;">
    <input type="hidden" name="form_section" value="review_request">
    <input type="hidden" name="redirect_section" value="request_certificate">
    <input type="hidden" name="action" value="resend_pickup_notice">
    <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
    <input type="hidden" name="id" id="cert-resend-id-input">
</form>

<form id="cert-link-record-form" method="POST" action="dashboard.php?section=request_certificate" style="display: none;">
    <input type="hidden" name="form_section" value="certificate_request">
    <input type="hidden" name="redirect_section" value="request_certificate">
    <input type="hidden" name="action" value="link_certificate_record">
    <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
    <input type="hidden" name="event_id" id="cert-link-event-id-input">
    <input type="hidden" name="linked_record_id" id="cert-link-record-id-input">
</form>

<?php if ($isAdmin): ?>
<div class="modal-overlay" id="cert-payment-modal" style="z-index: 10000;">
    <div class="modal" style="max-width: 420px; width: 92%; padding: 22px; border-radius: 12px; background: var(--surface); border: 1px solid var(--border); box-shadow: var(--shadow-lg);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px;">
            <h3 style="font-size: 15px; font-weight: 700; color: var(--text); margin: 0;">Record Certificate Fee</h3>
            <button type="button" onclick="closeCertPaymentModal()" style="border: none; background: transparent; color: var(--text2); font-size: 18px; cursor: pointer;">&times;</button>
        </div>
        <p id="cert-pay-title-label" style="font-size: 12px; color: var(--text2); margin: 0 0 14px;"></p>
        <form method="POST" action="dashboard.php?section=request_certificate" style="display: flex; flex-direction: column; gap: 10px;">
            <input type="hidden" name="form_section" value="review_request">
            <input type="hidden" name="redirect_section" value="request_certificate">
            <input type="hidden" name="action" value="record_payment">
            <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
            <input type="hidden" name="id" id="cert-pay-id-input">
            <div>
                <label style="font-size: 11px; font-weight: 700; color: var(--text2); display: block; margin-bottom: 4px;">Amount (₱)</label>
                <input type="number" step="0.01" min="0.01" name="amount" id="cert-pay-amount-input" required style="width: 100%; height: 34px; padding: 0 10px; border: 1px solid var(--border); border-radius: 6px; background: var(--bg2); color: var(--text); box-sizing: border-box;">
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px;">
                <div>
                    <label style="font-size: 11px; font-weight: 700; color: var(--text2); display: block; margin-bottom: 4px;">Method</label>
                    <select name="payment_method" style="width: 100%; height: 34px; padding: 0 8px; border: 1px solid var(--border); border-radius: 6px; background: var(--bg2); color: var(--text);">
                        <option value="Cash">Cash</option>
                        <option value="GCash">GCash</option>
                        <option value="Bank Transfer">Bank Transfer</option>
                        <option value="Maya">Maya</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div>
                    <label style="font-size: 11px; font-weight: 700; color: var(--text2); display: block; margin-bottom: 4px;">Date</label>
                    <input type="date" name="date_received" value="<?php echo date('Y-m-d'); ?>" required style="width: 100%; height: 34px; padding: 0 8px; border: 1px solid var(--border); border-radius: 6px; background: var(--bg2); color: var(--text); box-sizing: border-box;">
                </div>
            </div>
            <div>
                <label style="font-size: 11px; font-weight: 700; color: var(--text2); display: block; margin-bottom: 4px;">OR / Reference # <span style="font-weight: 400;">(optional)</span></label>
                <input type="text" name="donor_ref_id" maxlength="100" placeholder="e.g. OR-2026-001" style="width: 100%; height: 34px; padding: 0 10px; border: 1px solid var(--border); border-radius: 6px; background: var(--bg2); color: var(--text); box-sizing: border-box;">
            </div>
            <div style="display: flex; gap: 8px; margin-top: 6px;">
                <button type="button" class="btn btn-gray" onclick="closeCertPaymentModal()" style="flex: 1; border-radius: 6px;">Cancel</button>
                <button type="submit" class="btn btn-primary" style="flex: 1; border-radius: 6px;">Save Payment</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
let certSelectedPrice = <?php echo (float)($certVariants[0]['price'] ?? 200.00); ?>;
let certSelectedName = '<?php echo addslashes($certVariants[0]['name'] ?? 'Baptismal Certificate'); ?>';

function onCertVariantChange(selectEl) {
    const opt = selectEl.options[selectEl.selectedIndex];
    if (!opt) return;
    const vId = opt.value;
    const price = parseFloat(opt.getAttribute('data-price') || '200');
    const name = opt.getAttribute('data-name') || 'Certificate';
    
    document.getElementById('cert-field-variant-id').value = vId;
    certSelectedPrice = price;
    certSelectedName = name;
    
    const unitLabel = document.getElementById('cert-unit-rate-label');
    if (unitLabel) unitLabel.textContent = `PHP ${price.toLocaleString('en-US', {minimumFractionDigits: 2})}`;
    recalcCertTotals();
    syncCertDynamicTitle();
}

function onCertCopiesChange(val) {
    recalcCertTotals();
}

function recalcCertTotals() {
    const copiesInput = document.getElementById('cert-copies-input');
    const copies = Math.max(1, parseInt(copiesInput?.value || '1', 10));
    const total = certSelectedPrice * copies;
    
    const unitEl = document.getElementById('cert-summary-unit-rate');
    const copiesEl = document.getElementById('cert-summary-copies');
    const totalEl = document.getElementById('cert-summary-total-fee');
    
    if (unitEl) unitEl.textContent = `PHP ${certSelectedPrice.toLocaleString('en-US', {minimumFractionDigits: 2})}`;
    if (copiesEl) copiesEl.textContent = `${copies} ${copies > 1 ? 'copies' : 'copy'}`;
    if (totalEl) totalEl.textContent = `PHP ${total.toLocaleString('en-US', {minimumFractionDigits: 2})}`;
}

function onCertPickupDateChange(val) {
    const dtHidden = document.getElementById('cert-field-datetime');
    if (dtHidden && val) {
        dtHidden.value = `${val}T08:00`;
    }
}

function syncCertDynamicTitle() {
    const personName = (document.getElementById('cert-person-name')?.value || '').trim();
    const year = (document.getElementById('cert-year-input')?.value || '').trim();
    const purpose = document.getElementById('cert-purpose-select')?.value || '';
    
    let title = certSelectedName;
    if (personName) title += `: ${personName}`;
    if (year) title += ` (${year})`;
    if (purpose) title += ` - ${purpose}`;
    
    const titleHidden = document.getElementById('cert-field-title');
    if (titleHidden) titleHidden.value = title;
}

function openCertConfirmModal() {
    const form = document.getElementById('cert-application-form');
    if (!form) return;
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }
    
    const personName = (document.getElementById('cert-person-name')?.value || 'Not specified').trim();
    const year = (document.getElementById('cert-year-input')?.value || 'N/A').trim();
    const purpose = document.getElementById('cert-purpose-select')?.value || 'Personal Records';
    const pickupVal = document.getElementById('cert-pickup-date-input')?.value || '';
    const copies = Math.max(1, parseInt(document.getElementById('cert-copies-input')?.value || '1', 10));
    const totalText = document.getElementById('cert-summary-total-fee')?.textContent || 'PHP 200.00';
    const chapelSel = document.getElementById('cert-chapel-selector');
    const releasingOffice = (chapelSel && chapelSel.options && chapelSel.selectedIndex >= 0)
        ? chapelSel.options[chapelSel.selectedIndex].text.trim()
        : (document.querySelector('#cert-application-form input[readonly]')?.value?.trim() || 'Cathedral Parish Office');
    
    let pickupFormatted = pickupVal;
    try {
        const dt = new Date(pickupVal + 'T00:00:00');
        pickupFormatted = dt.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
    } catch(e){}
    
    document.getElementById('cert-confirm-content').innerHTML = `
        <div style="background:var(--bg3); border:1px solid var(--border); border-radius:8px; padding:14px;">
            <div style="display:flex; align-items:center; gap:10px; margin-bottom:12px;">
                <div style="width:36px; height:36px; border-radius:8px; background:#F6F6F6; border:1px solid #E5E5E5; color:#111111; display:flex; align-items:center; justify-content:center; font-size:16px;">
                    <i class="fa-solid fa-file-invoice"></i>
                </div>
                <div>
                    <div style="font-size:10.5px; color:#737373; text-transform:uppercase; font-weight:700;">Document Requested</div>
                    <div style="font-size:15px; font-weight:700; color:#111111;">${escapeHtml(certSelectedName)}</div>
                </div>
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; border-top:1px solid #E5E5E5; padding-top:10px;">
                <div>
                    <div style="font-size:10.5px; color:#737373; font-weight:600;">Person on Record</div>
                    <div style="font-size:12.5px; color:#111111; font-weight:700;">${escapeHtml(personName)}</div>
                </div>
                <div>
                    <div style="font-size:10.5px; color:#737373; font-weight:600;">Sacrament Year</div>
                    <div style="font-size:12.5px; color:#111111;">${escapeHtml(year)}</div>
                </div>
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; border-top:1px solid #E5E5E5; padding-top:10px; margin-top:10px;">
                <div>
                    <div style="font-size:10.5px; color:#737373; font-weight:600;">Target Pickup Date</div>
                    <div style="font-size:12.5px; color:#111111; font-weight:700;">${escapeHtml(pickupFormatted)}</div>
                </div>
                <div>
                    <div style="font-size:10.5px; color:#737373; font-weight:600;">Purpose</div>
                    <div style="font-size:12.5px; color:#111111;">${escapeHtml(purpose)}</div>
                </div>
            </div>

            <div style="border-top:1px solid #E5E5E5; padding-top:10px; margin-top:10px;">
                <div style="font-size:10.5px; color:#737373; font-weight:600; margin-bottom:4px;">Releasing Location</div>
                <div style="font-size:12px; color:#111111;">${escapeHtml(releasingOffice)} &bull; Tuesday to Sunday, 8am&ndash;5pm</div>
            </div>

            <div style="background:#F6F6F6; border:1px solid #E5E5E5; border-radius:6px; padding:8px 10px; margin-top:10px;">
                <div style="display:flex; justify-content:space-between; font-size:11.5px; color:#111111;">
                    <span>Copies Requested:</span>
                    <strong>${copies} ${copies > 1 ? 'copies' : 'copy'}</strong>
                </div>
                <div style="display:flex; justify-content:space-between; font-size:13px; font-weight:700; color:#111111; border-top:1px dashed #E5E5E5; padding-top:4px; margin-top:4px;">
                    <span>Total Document Fee:</span>
                    <strong>${totalText}</strong>
                </div>
            </div>
        </div>
        <div style="background:#F6F6F6; border:1px solid #E5E5E5; border-radius:6px; padding:8px 10px; font-size:11.5px; color:#737373;">
            <i class="fa-solid fa-circle-info" style="color:#111111; margin-right:4px;"></i>
            By confirming, this certificate request will be queued for registry lookup and parish sealing.
        </div>
    `;
    
    document.getElementById('cert-confirm-modal').style.display = 'flex';
    document.getElementById('cert-confirm-modal').classList.add('open');
}

function closeCertConfirmModal() {
    const m = document.getElementById('cert-confirm-modal');
    m.style.display = 'none';
    m.classList.remove('open');
}

function submitCertForm() {
    document.getElementById('cert-application-form').submit();
}

const CERT_MODAL_CFG = {
    isAdmin: <?php echo $isAdmin ? 'true' : 'false'; ?>,
    isGlobalAdmin: <?php echo in_array($role, ['Admin', 'Cathedral Admin'], true) ? 'true' : 'false'; ?>,
    csrf: <?php echo json_encode((string)$csrf); ?>,
    variants: <?php echo json_encode(array_values($certVariants), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
    chapels: <?php echo json_encode(array_values($chapels ?? []), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>
};

const CERT_PURPOSE_OPTIONS = [
    { value: 'Personal Records', label: 'Personal Records / File Copy' },
    { value: 'Marriage Requirement', label: 'Marriage Requirement' },
    { value: 'School / Enrollment', label: 'School / University Requirement' },
    { value: 'Confirmation / Holy Orders', label: 'Church Requirement' },
    { value: 'Passport / Visa / Travel', label: 'Passport / Visa / Embassy' },
    { value: 'Employment / Legal', label: 'Employment / Legal' }
];

let _activeCertDetailReq = null;
let _activeCertDetailMeta = null;
let _certEditMode = false;
let _certEditDirty = false;
let _certEditSubmitting = false;

function setCertEditDirty(dirty) {
    _certEditDirty = !!dirty;
    const badge = document.getElementById('cert-view-unsaved-badge');
    if (badge) badge.style.display = _certEditDirty ? 'inline-flex' : 'none';
    const saveBtn = document.getElementById('cert-edit-save-btn');
    if (saveBtn) saveBtn.disabled = !_certEditDirty;
}

function parseCertFallbackMeta(req) {
    const rawTitle = String(req.title || '');
    let docName = '';
    let personName = String(req.cert_person_name || '').trim();
    let certYear = String(req.cert_year || '').trim();
    let purpose = String(req.cert_purpose || '').trim();

    const colonIdx = rawTitle.indexOf(':');
    let rest = rawTitle;
    if (colonIdx !== -1) {
        docName = rawTitle.slice(0, colonIdx).trim();
        rest = rawTitle.slice(colonIdx + 1).trim();
    }
    if (rest && (!personName || !purpose)) {
        const dashMatch = rest.match(/^(.*?)\s+-\s+(.+)$/);
        if (dashMatch) {
            if (!purpose) purpose = dashMatch[2].trim();
            rest = dashMatch[1].trim();
        }
        const yearMatch = rest.match(/^(.*?)\s*\((\d{4})\)\s*$/);
        if (yearMatch) {
            if (!certYear) certYear = yearMatch[2].trim();
            rest = yearMatch[1].trim();
        }
        if (!personName) personName = rest;
    }

    if (!docName && req.variant_id && Array.isArray(CERT_MODAL_CFG.variants)) {
        const found = CERT_MODAL_CFG.variants.find(v => parseInt(v.id, 10) === parseInt(req.variant_id, 10));
        if (found) docName = found.name;
    }

    return {
        docName: docName || 'Ecclesiastical Certificate',
        personName: personName || 'Not specified',
        certYear: certYear,
        purpose: purpose || 'Personal Records'
    };
}

function viewCertDetailsFromRow(tr) {
    const raw = tr.getAttribute('data-req');
    if (!raw) return;
    try {
        const req = JSON.parse(raw);
        const meta = {
            paidFee: parseFloat(tr.getAttribute('data-paid') || '0') || 0,
            canEdit: tr.getAttribute('data-can-edit') === '1',
            recordsSection: tr.getAttribute('data-records-section') || 'records_cathedral',
            recordsQuery: tr.getAttribute('data-records-query') || ''
        };
        viewCertDetails(req, meta);
    } catch(e){}
}

function viewCertDetails(req, meta = {}) {
    _activeCertDetailReq = req;
    _activeCertDetailMeta = {
        paidFee: parseFloat(meta.paidFee || 0) || 0,
        canEdit: !!meta.canEdit,
        recordsSection: meta.recordsSection || ((parseInt(req.chapel_id || '0', 10) > 0) ? 'records_chapel' : 'records_cathedral'),
        recordsQuery: meta.recordsQuery || ''
    };
    _certEditMode = false;
    _certEditSubmitting = false;
    setCertEditDirty(false);
    renderCertDetailModal();

    const m = document.getElementById('cert-view-modal');
    if (m) {
        m.style.display = 'flex';
        m.classList.add('open');
    }
}

function renderCertDetailModal() {
    const req = _activeCertDetailReq;
    const meta = _activeCertDetailMeta || {};
    if (!req) return;

    const titleEl = document.getElementById('cert-view-modal-title');
    if (titleEl) titleEl.textContent = _certEditMode ? 'Edit Certificate Request' : 'Certificate Details';

    const bodyEl = document.getElementById('cert-view-body');
    const footerEl = document.getElementById('cert-view-footer');
    if (!bodyEl || !footerEl) return;

    const parsed = parseCertFallbackMeta(req);
    const totalFee = parseFloat(req.total_fee || 200) || 0;
    const paidFee = parseFloat(meta.paidFee || 0) || 0;
    const isPaidFee = (totalFee > 0 && paidFee >= (totalFee - 0.01));
    const balance = Math.max(0, totalFee - paidFee);
    const linkedRecId = parseInt(req.linked_record_id || '0', 10);
    const copies = Math.max(1, parseInt(req.cert_copies || '1', 10));
    const reqStatus = String(req.status || 'Pending');

    if (_certEditMode) {
        const canModifyBilling = (reqStatus === 'Pending' || (CERT_MODAL_CFG.isAdmin && reqStatus === 'Confirmed')) && paidFee <= 0.01;
        let pickupDateVal = '';
        if (req.start_time) {
            const m = String(req.start_time).match(/^(\d{4}-\d{2}-\d{2})/);
            if (m) pickupDateVal = m[1];
        }
        const selectedVariantId = parseInt(req.variant_id || '0', 10) || (CERT_MODAL_CFG.variants[0]?.id || '');
        const variantOptionsHtml = CERT_MODAL_CFG.variants.map(v => {
            const selected = parseInt(v.id, 10) === parseInt(selectedVariantId, 10) ? 'selected' : '';
            return `<option value="${escapeHtml(v.id)}" data-name="${escapeHtml(v.name)}" data-price="${escapeHtml(v.price)}" ${selected}>${escapeHtml(v.name)} — PHP ${parseFloat(v.price || 0).toLocaleString('en-US', {minimumFractionDigits: 2})}</option>`;
        }).join('');

        const hasPresetPurpose = CERT_PURPOSE_OPTIONS.some(o => o.value === parsed.purpose);
        const purposeOptionsHtml = (hasPresetPurpose ? '' : `<option value="${escapeHtml(parsed.purpose)}" selected>${escapeHtml(parsed.purpose)}</option>`)
            + CERT_PURPOSE_OPTIONS.map(o => `<option value="${escapeHtml(o.value)}" ${o.value === parsed.purpose ? 'selected' : ''}>${escapeHtml(o.label)}</option>`).join('');

        const currentChapelId = parseInt(req.chapel_id || '0', 10);
        const chapelOptionsHtml = `<option value="0" ${currentChapelId === 0 ? 'selected' : ''}>Cathedral Parish Office (Main)</option>`
            + CERT_MODAL_CFG.chapels.map(c => `<option value="${escapeHtml(c.chapel_id)}" ${parseInt(c.chapel_id, 10) === currentChapelId ? 'selected' : ''}>${escapeHtml(c.chapel_name)}</option>`).join('');

        bodyEl.innerHTML = `
            <form id="cert-edit-form" method="POST" action="dashboard.php?section=request_certificate" style="display: flex; flex-direction: column; gap: 12px; margin: 0;" onsubmit="_certEditSubmitting = true;">
                <input type="hidden" name="form_section" value="certificate_request">
                <input type="hidden" name="redirect_section" value="request_certificate">
                <input type="hidden" name="action" value="update_certificate_request">
                <input type="hidden" name="csrf" value="${escapeHtml(CERT_MODAL_CFG.csrf)}">
                <input type="hidden" name="event_id" value="${escapeHtml(req.event_id)}">
                <input type="hidden" name="linked_record_id" id="cert-edit-linked-record-id" value="${linkedRecId > 0 ? linkedRecId : ''}">

                <div style="display: grid; grid-template-columns: 2.2fr 1fr; gap: 10px;">
                    <div>
                        <label style="font-size: 10.5px; font-weight: 700; text-transform: uppercase; color: var(--text2); display: block; margin-bottom: 4px;">Document Type</label>
                        <select name="variant_id" id="cert-edit-variant" ${canModifyBilling ? '' : 'disabled'} onchange="updateCertEditFeePreview(); setCertEditDirty(true);"
                                style="width: 100%; height: 36px; padding: 0 10px; font-size: 12.5px; border-radius: 6px; border: 1px solid var(--border); background: ${canModifyBilling ? 'var(--surface)' : 'var(--bg2)'}; color: var(--text); font-weight: 600;">
                            ${variantOptionsHtml}
                        </select>
                    </div>
                    <div>
                        <label style="font-size: 10.5px; font-weight: 700; text-transform: uppercase; color: var(--text2); display: block; margin-bottom: 4px;">Copies</label>
                        <input type="number" name="certificate_copies" id="cert-edit-copies" min="1" max="25" value="${copies}" ${canModifyBilling ? '' : 'readonly'} oninput="updateCertEditFeePreview(); setCertEditDirty(true);"
                               style="width: 100%; height: 36px; padding: 0 10px; font-size: 12.5px; border-radius: 6px; border: 1px solid var(--border); background: ${canModifyBilling ? 'var(--surface)' : 'var(--bg2)'}; color: var(--text); font-weight: 700; text-align: center; box-sizing: border-box;">
                    </div>
                </div>
                ${!canModifyBilling ? `<div style="font-size: 11px; color: var(--text2); background: var(--bg2); border: 1px solid var(--border); border-radius: 6px; padding: 6px 10px;"><i class="fa-solid fa-lock" style="margin-right: 5px; color: var(--accent);"></i> Document type &amp; copies are locked because this request is already paid or released. Recorded fee: <strong>PHP ${totalFee.toLocaleString('en-US', {minimumFractionDigits: 2})}</strong>.</div>` : `<div id="cert-edit-fee-banner" style="font-size: 11.5px; color: var(--text2); background: var(--bg2); border: 1px solid var(--border); border-radius: 6px; padding: 6px 10px; display: flex; justify-content: space-between;"><span>Updated Issuance Fee Preview</span><strong id="cert-edit-fee-val" style="color: var(--text);">PHP ${totalFee.toLocaleString('en-US', {minimumFractionDigits: 2})}</strong></div>`}

                <div style="display: grid; grid-template-columns: 2.2fr 1fr; gap: 10px;">
                    <div id="cert-edit-record-wrap" style="position: relative;">
                        <label style="font-size: 10.5px; font-weight: 700; text-transform: uppercase; color: var(--text2); display: block; margin-bottom: 4px;">Person's Name on Record <span style="color:#ef4444;">*</span></label>
                        <input type="text" name="cert_person_name" id="cert-edit-person-name" required maxlength="150" autocomplete="off" value="${escapeHtml(parsed.personName === 'Not specified' ? '' : parsed.personName)}"
                               oninput="setCertEditDirty(true);${CERT_MODAL_CFG.isAdmin ? ' handleCertRecordNameInput(_CERT_EDIT_AUTOCOMPLETE);' : ''}"
                               ${CERT_MODAL_CFG.isAdmin ? 'onfocus="if (this.value.trim().length > 0) handleCertRecordNameInput(_CERT_EDIT_AUTOCOMPLETE);" onkeydown="handleCertSuggestionKeydown(event, \'cert-edit-record-suggestions\')"' : ''}
                               style="width: 100%; height: 36px; padding: 0 10px; font-size: 12.5px; border-radius: 6px; border: 1px solid var(--border); background: var(--surface); color: var(--text); box-sizing: border-box;">
                        ${CERT_MODAL_CFG.isAdmin ? '<div id="cert-edit-record-suggestions" role="listbox" style="display: none; position: absolute; top: calc(100% + 4px); left: 0; right: 0; max-height: 210px; overflow-y: auto; background: var(--surface, #FFFFFF); border: 1px solid var(--border); border-radius: 8px; box-shadow: 0 10px 25px rgba(0,0,0,0.16); z-index: 80;"></div>' : ''}
                    </div>
                    <div>
                        <label style="font-size: 10.5px; font-weight: 700; text-transform: uppercase; color: var(--text2); display: block; margin-bottom: 4px;">Approx. Year</label>
                        <input type="number" name="cert_year" id="cert-edit-year" min="1800" max="2099" placeholder="e.g. 1995" value="${escapeHtml(parsed.certYear)}" oninput="setCertEditDirty(true);"
                               style="width: 100%; height: 36px; padding: 0 10px; font-size: 12.5px; border-radius: 6px; border: 1px solid var(--border); background: var(--surface); color: var(--text); text-align: center; box-sizing: border-box;">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                    <div>
                        <label style="font-size: 10.5px; font-weight: 700; text-transform: uppercase; color: var(--text2); display: block; margin-bottom: 4px;">Purpose of Request</label>
                        <select name="cert_purpose" onchange="setCertEditDirty(true);"
                                style="width: 100%; height: 36px; padding: 0 10px; font-size: 12px; border-radius: 6px; border: 1px solid var(--border); background: var(--surface); color: var(--text);">
                            ${purposeOptionsHtml}
                        </select>
                    </div>
                    <div>
                        <label style="font-size: 10.5px; font-weight: 700; text-transform: uppercase; color: var(--text2); display: block; margin-bottom: 4px;">Target Pickup Date <span style="color:#ef4444;">*</span></label>
                        <input type="date" name="pickup_date" required value="${escapeHtml(pickupDateVal)}" max="2099-12-31" onchange="setCertEditDirty(true);"
                               style="width: 100%; height: 36px; padding: 0 10px; font-size: 12px; border-radius: 6px; border: 1px solid var(--border); background: var(--surface); color: var(--text); box-sizing: border-box;">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                    <div>
                        <label style="font-size: 10.5px; font-weight: 700; text-transform: uppercase; color: var(--text2); display: block; margin-bottom: 4px;">Applicant Name</label>
                        <input type="text" name="requester_name" maxlength="150" placeholder="Requester / Claimant name" value="${escapeHtml(req.requester_name || req.encoded_by_name || '')}" oninput="setCertEditDirty(true);"
                               style="width: 100%; height: 36px; padding: 0 10px; font-size: 12px; border-radius: 6px; border: 1px solid var(--border); background: var(--surface); color: var(--text); box-sizing: border-box;">
                    </div>
                    <div>
                        <label style="font-size: 10.5px; font-weight: 700; text-transform: uppercase; color: var(--text2); display: block; margin-bottom: 4px;">Mobile Phone</label>
                        <input type="tel" name="requester_phone" maxlength="25" placeholder="0912-345-6789" value="${escapeHtml(req.requester_phone || '')}" oninput="setCertEditDirty(true);"
                               style="width: 100%; height: 36px; padding: 0 10px; font-size: 12px; border-radius: 6px; border: 1px solid var(--border); background: var(--surface); color: var(--text); box-sizing: border-box;">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: ${CERT_MODAL_CFG.isGlobalAdmin ? '1fr 1fr' : '1fr'}; gap: 10px;">
                    <div>
                        <label style="font-size: 10.5px; font-weight: 700; text-transform: uppercase; color: var(--text2); display: block; margin-bottom: 4px;">Email Address</label>
                        <input type="email" name="requester_email" maxlength="150" placeholder="applicant@email.com" value="${escapeHtml(req.requester_email || '')}" oninput="setCertEditDirty(true);"
                               style="width: 100%; height: 36px; padding: 0 10px; font-size: 12px; border-radius: 6px; border: 1px solid var(--border); background: var(--surface); color: var(--text); box-sizing: border-box;">
                    </div>
                    ${CERT_MODAL_CFG.isGlobalAdmin ? `
                    <div>
                        <label style="font-size: 10.5px; font-weight: 700; text-transform: uppercase; color: var(--text2); display: block; margin-bottom: 4px;">Releasing Office</label>
                        <select name="chapel_id" onchange="setCertEditDirty(true);"
                                style="width: 100%; height: 36px; padding: 0 10px; font-size: 12px; border-radius: 6px; border: 1px solid var(--border); background: var(--surface); color: var(--text);">
                            ${chapelOptionsHtml}
                        </select>
                    </div>` : ''}
                </div>

                ${CERT_MODAL_CFG.isAdmin ? `
                <div style="background: var(--bg2); border: 1px solid var(--border); border-radius: 8px; padding: 9px 12px; display: flex; justify-content: space-between; align-items: center; gap: 10px;">
                    <div style="font-size: 11.5px; color: var(--text);">
                        <i class="fa-solid fa-link" style="color: var(--accent); margin-right: 5px;"></i>
                        <span id="cert-edit-link-status">${linkedRecId > 0 ? `Linked to Archival Record #${linkedRecId}` : 'No archival record linked — pick a match while typing name above'}</span>
                    </div>
                    <button type="button" id="cert-edit-clear-link-btn" onclick="clearCertEditLinkedRecord()" style="display: ${linkedRecId > 0 ? 'inline-flex' : 'none'}; align-items: center; gap: 4px; border: 1px solid var(--border); background: var(--surface); color: var(--text2); font-size: 11px; padding: 3px 8px; border-radius: 5px; cursor: pointer;">
                        <i class="fa-solid fa-xmark"></i> Unlink
                    </button>
                </div>` : ''}
            </form>
        `;

        footerEl.innerHTML = `
            <div style="font-size: 11px; color: var(--text2);">Editing #REQ-${String(req.event_id).padStart(4, '0')}</div>
            <div style="display: flex; align-items: center; gap: 8px;">
                <button type="button" class="btn btn-gray" onclick="cancelCertEditMode()" style="padding: 0 14px; height: 36px; border-radius: 8px;">Cancel Edit</button>
                <button type="submit" form="cert-edit-form" id="cert-edit-save-btn" class="btn btn-primary" disabled style="padding: 0 18px; height: 36px; border-radius: 8px; display: inline-flex; align-items: center; gap: 6px;">
                    <i class="fa-solid fa-floppy-disk"></i> Save Changes
                </button>
            </div>
        `;
        return;
    }

    const statusColors = { Confirmed:'#16a34a', Completed:'#2563eb', Pending:'#c5a059', Cancelled:'#dc2626', Rejected:'#dc2626' };
    const statusLabels = { Confirmed:'Ready', Completed:'Released', Pending:'Pending', Cancelled:'Cancelled', Rejected:'Rejected' };
    const sc = statusColors[reqStatus] || '#6b7280';
    const sl = statusLabels[reqStatus] || reqStatus || 'Pending';

    let stepGuide = '';
    if (reqStatus === 'Pending') {
        stepGuide = '<strong>Step 1 of 3 &mdash; Pending Archive Check:</strong> Verify the person in Records, link the matching archive record, then click <strong>Mark as Ready</strong>.';
    } else if (reqStatus === 'Confirmed') {
        stepGuide = '<strong>Step 2 of 3 &mdash; Ready for Pickup:</strong> Available at the Parish Office (Tue&ndash;Sun, 8am&ndash;5pm). Record payment if unpaid and click <strong>Mark as Released</strong> upon pickup.';
    } else if (reqStatus === 'Completed') {
        stepGuide = '<strong>Step 3 of 3 &mdash; Released:</strong> This certificate has been claimed and completed.';
    } else {
        stepGuide = '<strong>Closed:</strong> This certificate request was cancelled or declined.';
    }

    let pickupFormatted = req.start_time || 'Pending';
    try {
        const dt = new Date(req.start_time.replace(/-/g, '/'));
        if (!isNaN(dt.getTime())) {
            pickupFormatted = dt.toLocaleDateString('en-US', { weekday: 'short', year: 'numeric', month: 'short', day: 'numeric' });
        }
    } catch(e){}

    const applicantName = (req.requester_name || req.encoded_by_name || 'Walk-in / Parishioner').trim();
    const contactParts = [];
    if (req.requester_phone) contactParts.push(escapeHtml(req.requester_phone));
    if (req.requester_email) contactParts.push(escapeHtml(req.requester_email));

    const payBadgeHtml = totalFee > 0
        ? `<span style="display:inline-block; margin-left:6px; font-size:9.5px; font-weight:700; padding:1px 6px; border-radius:4px; ${isPaidFee ? 'background:rgba(46,158,91,0.12);color:#16a34a;' : 'background:rgba(197,160,89,0.16);color:#9a7418;'}">${isPaidFee ? 'PAID' : (paidFee > 0 ? `PARTIAL (Bal: PHP ${balance.toLocaleString('en-US', {minimumFractionDigits: 2})})` : 'UNPAID')}</span>`
        : '';

    const archiveSearchUrl = `dashboard.php?section=${encodeURIComponent(meta.recordsSection || 'records_cathedral')}&search=${encodeURIComponent(meta.recordsQuery || parsed.personName)}`;
    const certPreviewUrl = linkedRecId > 0 ? `certificates/sacramental_certificate.php?record_id=${linkedRecId}&preview=1` : '';

    bodyEl.innerHTML = `
        <div style="background: var(--bg2, #F6F6F6); border: 1px solid var(--border); border-radius: 10px; padding: 12px 14px; display: flex; align-items: center; gap: 12px;">
            <div style="width: 40px; height: 40px; border-radius: 8px; background: var(--surface, #FFFFFF); border: 1px solid var(--border); color: var(--text, #111111); display: flex; align-items: center; justify-content: center; font-size: 16px; flex-shrink: 0;">
                <i class="fa-solid fa-file-invoice"></i>
            </div>
            <div style="min-width: 0; flex: 1;">
                <div style="display: flex; gap: 6px; align-items: center; flex-wrap: wrap; margin-bottom: 2px;">
                    <span class="badge" style="background: var(--surface, #FFFFFF); border: 1px solid var(--border); font-size: 10px; font-weight: 600; padding: 2px 8px; color: var(--text);">${escapeHtml(parsed.docName)}</span>
                    <span class="badge" style="background: ${sc}18; color: ${sc}; border: 1px solid ${sc}35; font-size: 10px; font-weight: 600; padding: 2px 8px;">${escapeHtml(sl)}</span>
                    <span style="font-size: 10px; color: var(--text2); font-family: monospace;">#REQ-${String(req.event_id).padStart(4, '0')}</span>
                </div>
                <div style="font-family: var(--font-family, 'Inter', sans-serif); font-size: 14.5px; font-weight: 700; color: var(--text); line-height: 1.3;">${escapeHtml(req.title)}</div>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px;">
            <div style="background: var(--bg2, #F6F6F6); border: 1px solid var(--border); border-radius: 8px; padding: 10px 12px;">
                <div style="font-size: 10px; text-transform: uppercase; color: var(--text2); font-weight: 700; margin-bottom: 2px;"><i class="fa-solid fa-user-check" style="color:var(--text); margin-right:4px;"></i> Person on Record</div>
                <div style="font-size: 12px; color: var(--text); font-weight: 600;">${escapeHtml(parsed.personName)}${parsed.certYear ? ` <span style="color:var(--text2);font-weight:500;">(${escapeHtml(parsed.certYear)})</span>` : ''}</div>
            </div>
            <div style="background: var(--bg2, #F6F6F6); border: 1px solid var(--border); border-radius: 8px; padding: 10px 12px;">
                <div style="font-size: 10px; text-transform: uppercase; color: var(--text2); font-weight: 700; margin-bottom: 2px;"><i class="fa-regular fa-calendar-check" style="color:var(--text); margin-right:4px;"></i> Target Pickup Date</div>
                <div style="font-size: 12px; color: var(--text); font-weight: 600;">${escapeHtml(pickupFormatted)}</div>
            </div>
            <div style="background: var(--bg2, #F6F6F6); border: 1px solid var(--border); border-radius: 8px; padding: 10px 12px;">
                <div style="font-size: 10px; text-transform: uppercase; color: var(--text2); font-weight: 700; margin-bottom: 2px;"><i class="fa-solid fa-id-card-clip" style="color:var(--text); margin-right:4px;"></i> Purpose &amp; Copies</div>
                <div style="font-size: 12px; color: var(--text); font-weight: 600;">${escapeHtml(parsed.purpose)} &bull; ${copies} ${copies > 1 ? 'copies' : 'copy'}</div>
            </div>
            <div style="background: var(--bg2, #F6F6F6); border: 1px solid var(--border); border-radius: 8px; padding: 10px 12px;">
                <div style="font-size: 10px; text-transform: uppercase; color: var(--text2); font-weight: 700; margin-bottom: 2px;"><i class="fa-solid fa-building-columns" style="color:var(--text); margin-right:4px;"></i> Releasing Office</div>
                <div style="font-size: 12px; color: var(--text); font-weight: 600;">${escapeHtml(req.chapel_location || 'IFI Cathedral Parish Office')}</div>
            </div>
            <div style="background: var(--bg2, #F6F6F6); border: 1px solid var(--border); border-radius: 8px; padding: 10px 12px;">
                <div style="font-size: 10px; text-transform: uppercase; color: var(--text2); font-weight: 700; margin-bottom: 2px;"><i class="fa-regular fa-address-book" style="color:var(--text); margin-right:4px;"></i> Applicant &amp; Contact</div>
                <div style="font-size: 12px; color: var(--text); font-weight: 600;">${escapeHtml(applicantName)}</div>
                ${contactParts.length ? `<div style="font-size: 11px; color: var(--text2); margin-top: 2px; word-break: break-word;">${contactParts.join(' &bull; ')}</div>` : ''}
            </div>
            <div style="background: var(--bg2, #F6F6F6); border: 1px solid var(--border); border-radius: 8px; padding: 10px 12px;">
                <div style="font-size: 10px; text-transform: uppercase; color: var(--text2); font-weight: 700; margin-bottom: 2px;"><i class="fa-solid fa-receipt" style="color:var(--text); margin-right:4px;"></i> Recorded Total Fee</div>
                <div style="font-size: 12px; color: var(--text); font-weight: 700;">PHP ${totalFee.toLocaleString('en-US', {minimumFractionDigits: 2})}${payBadgeHtml}</div>
            </div>
        </div>

        ${CERT_MODAL_CFG.isAdmin ? `
        <div style="background: var(--bg2, #F6F6F6); border: 1px solid var(--border); border-radius: 8px; padding: 10px 12px; display: flex; flex-direction: column; gap: 8px;">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
                <div style="font-size: 11.5px; color: var(--text);">
                    <i class="fa-solid fa-folder-tree" style="color: var(--accent); margin-right: 6px;"></i>
                    ${linkedRecId > 0
                        ? `<strong style="color:#15803d;">Linked to Archive Record #${linkedRecId}</strong>`
                        : `<span id="cert-detail-link-status-text" style="color: var(--text2);">No archive record linked yet &mdash; checking archives&hellip;</span>`}
                </div>
                <div style="display: flex; align-items: center; gap: 6px; flex-wrap: wrap;">
                    ${certPreviewUrl ? `<a href="${certPreviewUrl}" target="_blank" rel="noopener" class="btn btn-sm" style="height: 28px; padding: 0 10px; font-size: 11px; font-weight: 600; border-radius: 6px; border: 1px solid var(--border); background: var(--surface); color: var(--text); text-decoration: none; display: inline-flex; align-items: center; gap: 5px;"><i class="fa-solid fa-file-pdf" style="color: #15803d;"></i> Preview Certificate</a>` : ''}
                    <a href="${archiveSearchUrl}" class="btn btn-sm" style="height: 28px; padding: 0 10px; font-size: 11px; font-weight: 600; border-radius: 6px; border: 1px solid var(--border); background: var(--surface); color: var(--text); text-decoration: none; display: inline-flex; align-items: center; gap: 5px;"><i class="fa-solid fa-folder-open" style="color: var(--accent);"></i> Find in Records</a>
                </div>
            </div>
            ${linkedRecId <= 0 ? `<div id="cert-detail-archive-matches" style="display: none; flex-direction: column; gap: 6px; border-top: 1px dashed var(--border); padding-top: 8px;"></div>` : ''}
        </div>` : ''}

        ${req.rejection_note ? `
        <div style="background: rgba(239,68,68,0.08); border: 1px solid rgba(239,68,68,0.25); border-radius: 8px; padding: 8px 10px;">
            <div style="font-size: 9.5px; text-transform: uppercase; color: #ef4444; font-weight: 700; margin-bottom: 2px;">Note from Office</div>
            <div style="font-size: 11.5px; color: #b91c1c; line-height: 1.35;">${escapeHtml(req.rejection_note)}</div>
        </div>` : ''}

        <div style="background: var(--bg2, #FAFAFA); border: 1px solid var(--border); border-left: 3px solid var(--accent); border-radius: 8px; padding: 10px 12px; font-size: 12px; color: var(--text); line-height: 1.45;">
            <i class="fa-solid fa-list-check" style="color: var(--accent); margin-right: 6px;"></i>${stepGuide}
        </div>
    `;

    const leftActions = [];
    if (meta.canEdit) {
        leftActions.push(`<button type="button" class="btn btn-sm" onclick="toggleCertEditMode(true)" style="height: 34px; padding: 0 12px; border-radius: 7px; border: 1px solid var(--border); background: var(--bg2); color: var(--text); font-weight: 600; font-size: 12px; display: inline-flex; align-items: center; gap: 6px; cursor: pointer;"><i class="fa-solid fa-pen-to-square" style="color: var(--accent);"></i> Edit Details</button>`);
    }
    if (CERT_MODAL_CFG.isAdmin) {
        if (reqStatus === 'Pending') {
            leftActions.push(`<button type="button" class="btn btn-sm" onclick="triggerCertActionFromDetail('approve')" style="height: 34px; padding: 0 12px; border-radius: 7px; border: 1px solid rgba(22,163,74,0.35); background: rgba(22,163,74,0.12); color: #15803d; font-weight: 700; font-size: 12px; display: inline-flex; align-items: center; gap: 6px; cursor: pointer;"><i class="fa-solid fa-circle-check"></i> Mark as Ready</button>`);
        } else if (reqStatus === 'Confirmed') {
            leftActions.push(`<button type="button" class="btn btn-sm" onclick="triggerCertActionFromDetail('complete')" style="height: 34px; padding: 0 12px; border-radius: 7px; border: 1px solid rgba(37,99,235,0.35); background: rgba(37,99,235,0.12); color: #2563eb; font-weight: 700; font-size: 12px; display: inline-flex; align-items: center; gap: 6px; cursor: pointer;"><i class="fa-solid fa-box-open"></i> Mark as Released</button>`);
            leftActions.push(`<button type="button" class="btn btn-sm" onclick="triggerCertActionFromDetail('resend')" style="height: 34px; padding: 0 12px; border-radius: 7px; border: 1px solid rgba(180,83,9,0.3); background: rgba(180,83,9,0.08); color: #b45309; font-weight: 600; font-size: 12px; display: inline-flex; align-items: center; gap: 6px; cursor: pointer;"><i class="fa-solid fa-paper-plane"></i> Resend Notice</button>`);
        }
        if (totalFee > 0 && !isPaidFee) {
            leftActions.push(`<button type="button" class="btn btn-sm" onclick="triggerCertActionFromDetail('payment')" style="height: 34px; padding: 0 12px; border-radius: 7px; border: 1px solid rgba(22,163,74,0.35); background: rgba(22,163,74,0.12); color: #15803d; font-weight: 700; font-size: 12px; display: inline-flex; align-items: center; gap: 6px; cursor: pointer;"><i class="fa-solid fa-peso-sign"></i> Record Payment</button>`);
        }
    } else if (reqStatus === 'Pending') {
        leftActions.push(`<button type="button" class="btn btn-sm" onclick="triggerCertActionFromDetail('cancel')" style="height: 34px; padding: 0 12px; border-radius: 7px; border: 1px solid rgba(220,38,38,0.3); background: rgba(220,38,38,0.08); color: #dc2626; font-weight: 600; font-size: 12px; display: inline-flex; align-items: center; gap: 6px; cursor: pointer;"><i class="fa-solid fa-ban"></i> Cancel Request</button>`);
    }

    footerEl.innerHTML = `
        <div style="display: flex; align-items: center; gap: 6px; flex-wrap: wrap;">${leftActions.join('')}</div>
        <button type="button" class="btn btn-gray" onclick="closeCertViewModal()" style="padding: 0 18px; height: 36px; border-radius: 8px;">Close</button>
    `;

    if (CERT_MODAL_CFG.isAdmin && linkedRecId <= 0) {
        loadCertDetailArchiveMatches(req, parsed);
    }
}

let _certDetailMatchAbort = null;
function loadCertDetailArchiveMatches(req, parsed) {
    const container = document.getElementById('cert-detail-archive-matches');
    const statusText = document.getElementById('cert-detail-link-status-text');
    const q = (parsed && parsed.personName && parsed.personName !== 'Not specified') ? parsed.personName.trim() : '';
    if (!container || !q) {
        if (statusText) statusText.textContent = 'No archive record linked yet';
        return;
    }

    let rType = '';
    const docLower = String(parsed.docName || req.title || '').toLowerCase();
    if (docLower.includes('baptism')) rType = 'Baptism';
    else if (docLower.includes('marriage') || docLower.includes('wedding')) rType = 'Wedding';
    else if (docLower.includes('burial') || docLower.includes('funeral')) rType = 'Funeral';
    else if (docLower.includes('confirmation')) rType = 'Confirmation';

    if (_certDetailMatchAbort) _certDetailMatchAbort.abort();
    _certDetailMatchAbort = new AbortController();
    const url = 'app/controllers/get_cert_record_suggestions.php?q=' + encodeURIComponent(q) + (rType ? '&record_type=' + encodeURIComponent(rType) : '');

    fetch(url, { signal: _certDetailMatchAbort.signal, credentials: 'same-origin' })
        .then(r => r.ok ? r.json() : { suggestions: [] })
        .then(data => {
            if (!_activeCertDetailReq || parseInt(_activeCertDetailReq.event_id, 10) !== parseInt(req.event_id, 10)) return;
            const list = Array.isArray(data.suggestions) ? data.suggestions.slice(0, 3) : [];
            if (!list.length) {
                if (statusText) statusText.textContent = 'No matching archive record found automatically';
                return;
            }
            if (statusText) {
                statusText.innerHTML = `<span style="color:#b45309;font-weight:600;">Not linked yet &bull; ${list.length} archive match${list.length > 1 ? 'es' : ''} found</span>`;
            }
            container.style.display = 'flex';
            container.innerHTML = list.map(rec => {
                const sub = [rec.record_type, rec.year, rec.reference_id, rec.chapel_name].filter(Boolean).join(' · ');
                return `
                    <div style="display: flex; align-items: center; justify-content: space-between; gap: 8px; padding: 6px 8px; background: var(--surface, #FFFFFF); border: 1px solid var(--border); border-radius: 6px;">
                        <div style="min-width: 0; flex: 1;">
                            <div style="font-size: 12px; font-weight: 600; color: var(--text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">${escapeHtml(rec.person_name || '')}</div>
                            <div style="font-size: 10.5px; color: var(--text2); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">${escapeHtml(sub)}</div>
                        </div>
                        <div style="display: flex; align-items: center; gap: 5px; flex-shrink: 0;">
                            <a href="certificates/sacramental_certificate.php?record_id=${encodeURIComponent(rec.record_id)}&preview=1" target="_blank" rel="noopener" class="btn btn-sm" style="height: 26px; padding: 0 8px; font-size: 11px; border-radius: 5px; border: 1px solid var(--border); background: var(--bg2); color: var(--text); text-decoration: none; display: inline-flex; align-items: center; gap: 4px;">
                                <i class="fa-regular fa-eye"></i> Preview
                            </a>
                            <button type="button" onclick="linkCertArchiveRecord(${parseInt(req.event_id, 10)}, ${parseInt(rec.record_id, 10)})" class="btn btn-sm" style="height: 26px; padding: 0 9px; font-size: 11px; font-weight: 700; border-radius: 5px; border: 1px solid rgba(22,163,74,0.35); background: rgba(22,163,74,0.12); color: #15803d; cursor: pointer; display: inline-flex; align-items: center; gap: 4px;">
                                <i class="fa-solid fa-link"></i> Link
                            </button>
                        </div>
                    </div>`;
            }).join('');
        })
        .catch(() => {
            if (statusText) statusText.textContent = 'No archive record linked yet';
        });
}

function linkCertArchiveRecord(eventId, recordId) {
    const evInp = document.getElementById('cert-link-event-id-input');
    const recInp = document.getElementById('cert-link-record-id-input');
    const form = document.getElementById('cert-link-record-form');
    if (evInp && recInp && form && eventId > 0 && recordId > 0) {
        evInp.value = String(eventId);
        recInp.value = String(recordId);
        form.submit();
    }
}
window.linkCertArchiveRecord = linkCertArchiveRecord;

function updateCertEditFeePreview() {
    const sel = document.getElementById('cert-edit-variant');
    const copiesInp = document.getElementById('cert-edit-copies');
    const feeVal = document.getElementById('cert-edit-fee-val');
    if (!sel || !copiesInp || !feeVal) return;
    const opt = sel.options[sel.selectedIndex];
    const price = parseFloat(opt ? opt.getAttribute('data-price') || '200' : '200') || 200;
    const copies = Math.max(1, parseInt(copiesInp.value || '1', 10));
    feeVal.textContent = 'PHP ' + (price * copies).toLocaleString('en-US', {minimumFractionDigits: 2});
}

function clearCertEditLinkedRecord() {
    const inp = document.getElementById('cert-edit-linked-record-id');
    const lbl = document.getElementById('cert-edit-link-status');
    const btn = document.getElementById('cert-edit-clear-link-btn');
    if (inp) inp.value = '';
    if (lbl) lbl.textContent = 'No archival record linked — pick a match while typing name above';
    if (btn) btn.style.display = 'none';
    setCertEditDirty(true);
}

function toggleCertEditMode(enable) {
    _certEditMode = !!enable;
    if (!enable) setCertEditDirty(false);
    renderCertDetailModal();
}

function cancelCertEditMode() {
    if (_certEditDirty && !confirm('You have unsaved changes to this certificate request. Discard them?')) {
        return;
    }
    toggleCertEditMode(false);
}

function triggerCertActionFromDetail(kind) {
    const req = _activeCertDetailReq;
    const meta = _activeCertDetailMeta || {};
    if (!req) return;
    const id = req.event_id;
    const totalFee = parseFloat(req.total_fee || 200) || 0;
    const paidFee = parseFloat(meta.paidFee || 0) || 0;
    const balance = Math.max(0, totalFee - paidFee);

    closeCertViewModal(true);
    if (kind === 'approve') {
        const input = document.getElementById('cert-approve-id-input');
        const form = document.getElementById('cert-approve-form');
        if (input && form) { input.value = id; form.submit(); }
    } else if (kind === 'complete') {
        const input = document.getElementById('cert-complete-id-input');
        const form = document.getElementById('cert-complete-form');
        if (input && form) { input.value = id; form.submit(); }
    } else if (kind === 'resend') {
        const input = document.getElementById('cert-resend-id-input');
        const form = document.getElementById('cert-resend-form');
        if (input && form) { input.value = id; form.submit(); }
    } else if (kind === 'payment') {
        const fakeEl = document.createElement('span');
        fakeEl.setAttribute('data-id', String(id));
        fakeEl.setAttribute('data-title', req.title || '');
        fakeEl.setAttribute('data-balance', balance.toFixed(2));
        openCertPaymentModal(fakeEl);
    } else if (kind === 'cancel') {
        cancelCertRequest(id, req.title || '');
    }
}

function closeCertViewModal(force = false) {
    if (!force && _certEditMode && _certEditDirty && !_certEditSubmitting) {
        if (!confirm('You have unsaved changes to this certificate request. Discard them?')) {
            return;
        }
    }
    _certEditMode = false;
    setCertEditDirty(false);
    const m = document.getElementById('cert-view-modal');
    if (m) {
        m.style.display = 'none';
        m.classList.remove('open');
    }
}

window.viewCertDetailsFromRow = viewCertDetailsFromRow;
window.viewCertDetails = viewCertDetails;
window.closeCertViewModal = closeCertViewModal;
window.toggleCertEditMode = toggleCertEditMode;
window.cancelCertEditMode = cancelCertEditMode;
window.updateCertEditFeePreview = updateCertEditFeePreview;
window.clearCertEditLinkedRecord = clearCertEditLinkedRecord;
window.triggerCertActionFromDetail = triggerCertActionFromDetail;
window.setCertEditDirty = setCertEditDirty;

function cancelCertRequest(idOrEl, title) {
    let id = idOrEl;
    if (idOrEl instanceof HTMLElement) {
        id = idOrEl.getAttribute('data-id');
        title = idOrEl.getAttribute('data-title');
    }
    const input = document.getElementById('cert-cancel-id-input');
    if (input) input.value = id || '';
    const span = document.getElementById('cert-cancel-title-span');
    if (span) span.textContent = title || '';
    const m = document.getElementById('cert-cancel-modal');
    if (m) {
        m.style.display = 'flex';
        m.classList.add('open');
    }
}

function closeCertCancelModal() {
    const m = document.getElementById('cert-cancel-modal');
    if (m) {
        m.style.display = 'none';
        m.classList.remove('open');
    }
}

function deleteCertRequest(idOrEl, title) {
    let id = idOrEl;
    if (idOrEl instanceof HTMLElement) {
        id = idOrEl.getAttribute('data-id');
        title = idOrEl.getAttribute('data-title');
    }
    const input = document.getElementById('cert-delete-id-input');
    if (input) input.value = id || '';
    const span = document.getElementById('cert-delete-title-span');
    if (span) span.textContent = title || '';
    const m = document.getElementById('cert-delete-modal');
    if (m) {
        m.style.display = 'flex';
        m.classList.add('open');
    }
}

function approveCertRequest(el) {
    const id = el ? el.getAttribute('data-id') : '';
    if (!id) return;
    const input = document.getElementById('cert-approve-id-input');
    const form = document.getElementById('cert-approve-form');
    if (input && form) {
        input.value = id;
        form.submit();
    }
}

function openCertApplicantChat(userId) {
    if (!userId || !window.ParishChat) return;
    if (typeof window.ParishChat.toggleChat === 'function') {
        window.ParishChat.toggleChat(true);
    }
    if (typeof window.ParishChat.startDirectChat === 'function') {
        window.ParishChat.startDirectChat(userId);
    }
}

function completeCertRequest(el) {
    const id = el ? el.getAttribute('data-id') : '';
    if (!id) return;
    const input = document.getElementById('cert-complete-id-input');
    const form = document.getElementById('cert-complete-form');
    if (input && form) {
        input.value = id;
        form.submit();
    }
}

function resendCertPickupNotice(el) {
    const id = el ? el.getAttribute('data-id') : '';
    if (!id) return;
    const input = document.getElementById('cert-resend-id-input');
    const form = document.getElementById('cert-resend-form');
    if (input && form) {
        input.value = id;
        form.submit();
    }
}

function onCertChapelChange(sel) {
    const scopeEl = document.getElementById('cert-field-scope');
    if (scopeEl && sel) {
        scopeEl.value = (parseInt(sel.value || '0', 10) > 0) ? 'chapel' : 'cathedral';
    }
}

function openCertPaymentModal(el) {
    if (!el) return;
    const idInp = document.getElementById('cert-pay-id-input');
    const amtInp = document.getElementById('cert-pay-amount-input');
    const lbl = document.getElementById('cert-pay-title-label');
    if (idInp) idInp.value = el.getAttribute('data-id') || '';
    if (amtInp) amtInp.value = el.getAttribute('data-balance') || '';
    if (lbl) lbl.textContent = el.getAttribute('data-title') || '';
    const m = document.getElementById('cert-payment-modal');
    if (m) { m.style.display = 'flex'; m.classList.add('open'); }
}

function closeCertPaymentModal() {
    const m = document.getElementById('cert-payment-modal');
    if (m) { m.style.display = 'none'; m.classList.remove('open'); }
}

window.approveCertRequest = approveCertRequest;
window.completeCertRequest = completeCertRequest;
window.resendCertPickupNotice = resendCertPickupNotice;
window.onCertChapelChange = onCertChapelChange;
window.openCertPaymentModal = openCertPaymentModal;
window.closeCertPaymentModal = closeCertPaymentModal;
window.openCertApplicantChat = openCertApplicantChat;
window.cancelCertRequest = cancelCertRequest;
window.closeCertCancelModal = closeCertCancelModal;
window.deleteCertRequest = deleteCertRequest;
window.closeCertDeleteModal = closeCertDeleteModal;

function closeCertDeleteModal() {
    const m = document.getElementById('cert-delete-modal');
    if (m) {
        m.style.display = 'none';
        m.classList.remove('open');
    }
}

function escapeHtml(text) {
    if (!text && text !== 0) return '';
    const d = document.createElement('div');
    d.textContent = String(text);
    return d.innerHTML;
}

// ── ACTIONS DROPDOWN PORTAL ──
let _activeCertPortal = null, _activeCertBtn = null;

function togglePortalDropdown(btn) {
    _closeCertPortal();
    if (_activeCertBtn === btn) { _activeCertBtn = null; return; }
    _activeCertBtn = btn;

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
    const vw = window.innerWidth, menuW = 170;
    let left = rect.right - menuW;
    if (left < 4) left = rect.left;
    if (left + menuW > vw - 4) left = vw - menuW - 4;
    clone.style.top = (rect.bottom + 4) + 'px';
    clone.style.left = left + 'px';
    document.body.appendChild(clone);
    _activeCertPortal = clone;

    clone.querySelectorAll('.dropdown-item').forEach((cloneItem, i) => {
        const realItem = menuEl.querySelectorAll('.dropdown-item')[i];
        cloneItem.addEventListener('click', e => {
            e.stopPropagation();
            _closeCertPortal();
            if (realItem) realItem.click();
        });
    });
}

function _closeCertPortal() {
    if (_activeCertPortal) { _activeCertPortal.remove(); _activeCertPortal = null; }
    _activeCertBtn = null;
}

document.addEventListener('click', e => {
    if (_activeCertPortal && !_activeCertPortal.contains(e.target) && e.target !== _activeCertBtn) _closeCertPortal();
});

// ── CERTIFICATE TAB SWITCHING ─────────────────────────────────────────────
function switchCertTab(tabId) {
    const isGuest = <?php echo ($isGuest ?? false) ? 'true' : 'false'; ?>;
    if (isGuest && tabId === 'apply') {
        if (typeof openGuestAuthModal === 'function') {
            openGuestAuthModal('request an official certificate');
        }
        return;
    }
    document.querySelectorAll('.cert-tab-btn').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.tab === tabId);
    });
    document.querySelectorAll('.cert-tab-pane').forEach(pane => {
        pane.style.display = (pane.id === 'cert-view-' + tabId) ? 'flex' : 'none';
    });
    
    // Update URL parameter without reload
    const url = new URL(window.location);
    url.searchParams.set('tab', tabId);
    window.history.replaceState({}, '', url);
}

// ── CLIENT-SIDE SEARCH, SORT & STATUS FILTER FOR CERTIFICATES LEDGER ────────────
let _certCurrentStatusFilter = 'all';
let _certSortAsc = false; // Default: newest first (id descending)

function filterCertStatus(status, btn) {
    _certCurrentStatusFilter = status;
    document.querySelectorAll('.cert-status-filter').forEach(b => {
        const isTarget = (b === btn);
        b.classList.toggle('active', isTarget);
        if (isTarget) {
            b.style.background = '#111111';
            b.style.color = '#FFFFFF';
        } else {
            b.style.background = 'transparent';
            b.style.color = 'var(--text2)';
        }
    });
    filterCertLedger();
}

function clearCertSearch() {
    const input = document.getElementById('cert-search-input');
    hideCertSuggestions('cert-search-suggestions');
    if (input) {
        input.value = '';
        filterCertLedger();
        input.focus();
    }
}

function escapeCertHtml(str) {
    return String(str || '').replace(/[&<>"']/g, ch => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    }[ch]));
}

function highlightCertMatch(text, query) {
    const safeText = escapeCertHtml(text);
    const q = (query || '').trim();
    if (!q) return safeText;
    const escapedRe = q.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    try {
        return safeText.replace(new RegExp('(' + escapedRe + ')', 'ig'),
            '<mark style="background: rgba(201,168,76,0.28); color: #111111; padding: 0 2px; border-radius: 3px; font-weight: 700;">$1</mark>');
    } catch (e) {
        return safeText;
    }
}

function hideCertSuggestions(containerId) {
    const box = document.getElementById(containerId);
    if (box) {
        box.style.display = 'none';
        box.dataset.activeIdx = '-1';
    }
}

function handleCertSuggestionKeydown(e, containerId) {
    const box = document.getElementById(containerId);
    if (!box || box.style.display === 'none') return;
    const items = Array.from(box.querySelectorAll('.cert-suggestion-item'));
    if (!items.length) {
        if (e.key === 'Escape') hideCertSuggestions(containerId);
        return;
    }

    let idx = parseInt(box.dataset.activeIdx || '-1', 10);
    if (e.key === 'ArrowDown') {
        e.preventDefault();
        idx = (idx + 1) % items.length;
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        idx = (idx <= 0) ? (items.length - 1) : (idx - 1);
    } else if (e.key === 'Enter' && idx >= 0 && items[idx]) {
        e.preventDefault();
        items[idx].click();
        return;
    } else if (e.key === 'Escape') {
        e.preventDefault();
        hideCertSuggestions(containerId);
        return;
    } else {
        return;
    }

    box.dataset.activeIdx = String(idx);
    items.forEach((el, i) => {
        el.style.background = (i === idx) ? '#F4F4F5' : '#FFFFFF';
    });
    items[idx].scrollIntoView({ block: 'nearest' });
}

function handleCertSearchInput() {
    filterCertLedger();
    showCertSearchSuggestions();
}

function extractCertPersonLabel(title) {
    const raw = String(title || '');
    const colonIdx = raw.indexOf(':');
    let body = colonIdx >= 0 ? raw.slice(colonIdx + 1).trim() : raw.trim();
    const parenIdx = body.indexOf('(');
    if (parenIdx > 0) body = body.slice(0, parenIdx).trim();
    const pipeIdx = body.indexOf('|');
    if (pipeIdx > 0) body = body.slice(0, pipeIdx).trim();
    return body || raw.trim();
}

function showCertSearchSuggestions() {
    const input = document.getElementById('cert-search-input');
    const box = document.getElementById('cert-search-suggestions');
    if (!input || !box) return;

    const term = input.value.trim();
    if (!term) {
        hideCertSuggestions('cert-search-suggestions');
        return;
    }

    const qLower = term.toLowerCase();
    const rows = Array.from(document.querySelectorAll('#cert-requests-table .cert-table-row'));
    const scored = [];
    const seen = new Set();

    rows.forEach(row => {
        let req = null;
        try { req = JSON.parse(row.getAttribute('data-req') || 'null'); } catch (e) {}
        const title = (req && req.title) ? String(req.title) : (row.dataset.title || '');
        const idStr = String((req && req.id) || row.dataset.id || '');
        const applicant = (req && req.user_name) ? String(req.user_name) : '';
        const status = String((req && req.status) || row.dataset.status || '').toLowerCase();
        const personLabel = extractCertPersonLabel(title);
        const refLabel = idStr ? ('REQ-' + idStr.padStart(4, '0')) : '';

        const haystack = [personLabel, applicant, title, refLabel, idStr].join(' ').toLowerCase();
        if (!haystack.includes(qLower)) return;

        const dedupeKey = personLabel.toLowerCase() + '|' + refLabel;
        if (seen.has(dedupeKey)) return;
        seen.add(dedupeKey);

        const isPrefix = personLabel.toLowerCase().startsWith(qLower)
            || applicant.toLowerCase().startsWith(qLower)
            || refLabel.toLowerCase().startsWith(qLower)
            || idStr.startsWith(qLower);

        scored.push({
            score: isPrefix ? 0 : 1,
            id: parseInt(idStr || '0', 10),
            personLabel,
            applicant,
            title,
            refLabel,
            status
        });
    });

    scored.sort((a, b) => (a.score - b.score) || (b.id - a.id));
    const top = scored.slice(0, 8);

    const statusMap = {
        pending:   { label: 'Pending',   color: '#b45309', bg: '#fef3c7' },
        confirmed: { label: 'Ready',     color: '#047857', bg: '#d1fae5' },
        completed: { label: 'Released',  color: '#3f3f46', bg: '#e4e4e7' },
        rejected:  { label: 'Cancelled', color: '#b91c1c', bg: '#fee2e2' }
    };

    if (!top.length) {
        box.innerHTML = '<div style="padding: 10px 12px; font-size: 12px; color: var(--text2);">No matching certificate requests for "' + escapeCertHtml(term) + '".</div>';
    } else {
        const header = '<div style="padding: 6px 12px; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text2); background: #F4F4F5; border-bottom: 1px solid var(--border);">'
            + 'Quick Matches (' + scored.length + ')</div>';
        const itemsHtml = top.map(item => {
            const st = statusMap[item.status] || { label: item.status || 'Open', color: '#52525b', bg: '#f4f4f5' };
            const filterVal = item.personLabel || item.refLabel;
            const subParts = [];
            if (item.refLabel) subParts.push(item.refLabel);
            if (item.applicant && item.applicant.toLowerCase() !== item.personLabel.toLowerCase()) {
                subParts.push('By: ' + item.applicant);
            }
            return '<button type="button" class="cert-suggestion-item" '
                + 'data-filter="' + escapeCertHtml(filterVal) + '" '
                + 'data-id="' + item.id + '" '
                + 'onclick="selectCertSearchSuggestion(this.dataset.filter, this.dataset.id)" '
                + 'onmouseenter="this.style.background=\'#F4F4F5\'" onmouseleave="this.style.background=\'#FFFFFF\'" '
                + 'style="display: flex; align-items: center; justify-content: space-between; gap: 10px; width: 100%; text-align: left; padding: 8px 12px; border: none; border-bottom: 1px solid #f4f4f5; background: #FFFFFF; cursor: pointer; font-family: inherit;">'
                + '<div style="min-width: 0; flex: 1;">'
                + '<div style="font-size: 12.5px; font-weight: 600; color: var(--text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">' + highlightCertMatch(item.personLabel, term) + '</div>'
                + '<div style="font-size: 11px; color: var(--text2); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">' + highlightCertMatch(subParts.join(' · '), term) + '</div>'
                + '</div>'
                + '<span style="flex-shrink: 0; font-size: 10px; font-weight: 700; padding: 2px 7px; border-radius: 999px; color: ' + st.color + '; background: ' + st.bg + ';">' + escapeCertHtml(st.label) + '</span>'
                + '</button>';
        }).join('');
        box.innerHTML = header + itemsHtml;
    }

    box.dataset.activeIdx = '-1';
    box.style.display = 'block';
}

function selectCertSearchSuggestion(filterVal, reqId) {
    const input = document.getElementById('cert-search-input');
    if (input) {
        input.value = filterVal || '';
        filterCertLedger();
    }
    hideCertSuggestions('cert-search-suggestions');
    const row = document.querySelector('#cert-requests-table .cert-table-row[data-id="' + String(reqId || '') + '"]');
    if (row) {
        row.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        const prevBg = row.style.backgroundColor;
        row.style.backgroundColor = '#fef9c3';
        setTimeout(() => { row.style.backgroundColor = prevBg; }, 1200);
    }
}

let _certRecordSuggestTimer = null;
let _certRecordSuggestAbort = null;

const _CERT_APPLY_AUTOCOMPLETE = {
    inputId: 'cert-person-name',
    yearId: 'cert-year-input',
    linkedId: 'cert-linked-record-id',
    boxId: 'cert-record-suggestions',
    variantSelectId: 'cert-variant-selector'
};
const _CERT_EDIT_AUTOCOMPLETE = {
    inputId: 'cert-edit-person-name',
    yearId: 'cert-edit-year',
    linkedId: 'cert-edit-linked-record-id',
    boxId: 'cert-edit-record-suggestions',
    variantSelectId: 'cert-edit-variant',
    isEdit: true
};
let _activeCertRecordCtx = _CERT_APPLY_AUTOCOMPLETE;

function inferCertRecordTypeHint(selectId = 'cert-variant-selector') {
    const sel = document.getElementById(selectId);
    if (!sel || sel.selectedIndex < 0) return '';
    const txt = (sel.options[sel.selectedIndex].text || '').toLowerCase();
    if (txt.includes('baptism')) return 'Baptism';
    if (txt.includes('marriage')) return 'Wedding';
    if (txt.includes('burial') || txt.includes('funeral')) return 'Funeral';
    if (txt.includes('confirmation')) return 'Confirmation';
    return '';
}

function handleCertRecordNameInput(ctx = _CERT_APPLY_AUTOCOMPLETE) {
    _activeCertRecordCtx = ctx || _CERT_APPLY_AUTOCOMPLETE;
    const input = document.getElementById(_activeCertRecordCtx.inputId);
    const box = document.getElementById(_activeCertRecordCtx.boxId);
    if (!input || !box) return;

    const q = input.value.trim();
    if (!q) {
        if (_certRecordSuggestTimer) clearTimeout(_certRecordSuggestTimer);
        if (_certRecordSuggestAbort) _certRecordSuggestAbort.abort();
        hideCertSuggestions(_activeCertRecordCtx.boxId);
        return;
    }

    if (_certRecordSuggestTimer) clearTimeout(_certRecordSuggestTimer);
    _certRecordSuggestTimer = setTimeout(() => {
        if (_certRecordSuggestAbort) _certRecordSuggestAbort.abort();
        _certRecordSuggestAbort = new AbortController();
        const rType = inferCertRecordTypeHint(_activeCertRecordCtx.variantSelectId);
        const url = 'app/controllers/get_cert_record_suggestions.php?q='
            + encodeURIComponent(q)
            + (rType ? '&record_type=' + encodeURIComponent(rType) : '');

        fetch(url, { signal: _certRecordSuggestAbort.signal, credentials: 'same-origin' })
            .then(r => r.ok ? r.json() : { suggestions: [] })
            .then(data => {
                renderCertRecordSuggestions(data.suggestions || [], q, _activeCertRecordCtx);
            })
            .catch(err => {
                if (err && err.name !== 'AbortError') {
                    hideCertSuggestions(_activeCertRecordCtx.boxId);
                }
            });
    }, 150);
}

function renderCertRecordSuggestions(suggestions, query, ctx = _CERT_APPLY_AUTOCOMPLETE) {
    const targetCtx = ctx || _CERT_APPLY_AUTOCOMPLETE;
    _activeCertRecordCtx = targetCtx;
    const box = document.getElementById(targetCtx.boxId);
    if (!box) return;

    if (!suggestions.length) {
        box.innerHTML = '<div style="padding: 9px 12px; font-size: 11.5px; color: var(--text2);">No archive records matching "' + escapeCertHtml(query) + '". You may continue typing the name manually.</div>';
        box.dataset.activeIdx = '-1';
        box.style.display = 'block';
        return;
    }

    const header = '<div style="padding: 6px 12px; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text2); background: var(--bg2, #F4F4F5); border-bottom: 1px solid var(--border);">'
        + 'Archive Name Matches (Click to fill)</div>';

    const itemsHtml = suggestions.map(rec => {
        const meta = [];
        if (rec.record_type) meta.push(rec.record_type);
        if (rec.year) meta.push(rec.year);
        if (rec.book_number || rec.page_number) {
            meta.push('Bk ' + (rec.book_number || '-') + ' / Pg ' + (rec.page_number || '-'));
        }
        if (rec.chapel_name) meta.push(rec.chapel_name);
        return '<button type="button" class="cert-suggestion-item" '
            + 'data-record-id="' + escapeCertHtml(String(rec.record_id || '')) + '" '
            + 'data-name="' + escapeCertHtml(rec.person_name || '') + '" '
            + 'data-year="' + escapeCertHtml(rec.year || '') + '" '
            + 'onclick="selectCertRecordSuggestion(this.dataset.name, this.dataset.year, this.dataset.recordId)" '
            + 'onmouseenter="this.style.background=\'var(--bg2, #F4F4F5)\'" onmouseleave="this.style.background=\'var(--surface, #FFFFFF)\'" '
            + 'style="display: flex; align-items: center; justify-content: space-between; gap: 10px; width: 100%; text-align: left; padding: 8px 12px; border: none; border-bottom: 1px solid var(--border); background: var(--surface, #FFFFFF); cursor: pointer; font-family: inherit;">'
            + '<div style="min-width: 0; flex: 1;">'
            + '<div style="font-size: 12.5px; font-weight: 600; color: var(--text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">' + highlightCertMatch(rec.person_name || '', query) + '</div>'
            + '<div style="font-size: 11px; color: var(--text2); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">' + escapeCertHtml(meta.join(' · ')) + '</div>'
            + '</div>'
            + (rec.reference_id ? '<span style="flex-shrink: 0; font-family: monospace; font-size: 10.5px; color: var(--text2); background: var(--bg2, #F4F4F5); padding: 2px 6px; border-radius: 4px;">' + escapeCertHtml(rec.reference_id) + '</span>' : '')
            + '</button>';
    }).join('');

    box.innerHTML = header + itemsHtml;
    box.dataset.activeIdx = '-1';
    box.style.display = 'block';
}

function selectCertRecordSuggestion(personName, year, recordId) {
    const ctx = _activeCertRecordCtx || _CERT_APPLY_AUTOCOMPLETE;
    const nameInput = document.getElementById(ctx.inputId);
    const yearInput = document.getElementById(ctx.yearId);
    const linkedInput = document.getElementById(ctx.linkedId);
    if (nameInput && personName) {
        nameInput.value = personName;
    }
    if (yearInput && year) {
        yearInput.value = year;
    }
    if (linkedInput) {
        linkedInput.value = recordId ? String(recordId) : '';
    }
    if (ctx.isEdit) {
        const lbl = document.getElementById('cert-edit-link-status');
        const clr = document.getElementById('cert-edit-clear-link-btn');
        if (lbl) lbl.textContent = recordId ? `Linked to Archival Record #${recordId}` : 'No archival record linked — pick a match while typing name above';
        if (clr) clr.style.display = recordId ? 'inline-flex' : 'none';
        setCertEditDirty(true);
    } else if (typeof syncCertDynamicTitle === 'function') {
        syncCertDynamicTitle();
    }
    hideCertSuggestions(ctx.boxId);
}

document.addEventListener('click', function(e) {
    const searchWrap = document.getElementById('cert-search-wrap');
    if (searchWrap && !searchWrap.contains(e.target)) {
        hideCertSuggestions('cert-search-suggestions');
    }
    const editRecordWrap = document.getElementById('cert-edit-record-wrap');
    if (editRecordWrap && !editRecordWrap.contains(e.target)) {
        hideCertSuggestions('cert-edit-record-suggestions');
    }
    const recWrap = document.getElementById('cert-record-search-wrap');
    if (recWrap && !recWrap.contains(e.target)) {
        hideCertSuggestions('cert-record-suggestions');
    }
});

function filterCertLedger() {
    const input = document.getElementById('cert-search-input');
    const term = (input?.value || '').toLowerCase().trim();
    const clearBtn = document.getElementById('cert-clear-search');
    if (clearBtn) {
        clearBtn.style.display = term ? 'block' : 'none';
    }

    const rows = document.querySelectorAll('#cert-requests-table .cert-table-row');
    const thirtyDaysAgo = Math.floor(Date.now() / 1000) - (30 * 86400);

    rows.forEach(row => {
        const title = row.dataset.title || '';
        const status = row.dataset.status || '';
        const id = row.dataset.id || '';
        const created = parseInt(row.dataset.created || '0', 10);
        const updated = parseInt(row.dataset.updated || '0', 10);

        const matchesTerm = !term || title.includes(term) || id.includes(term);
        const matchesStatus = (_certCurrentStatusFilter === 'all') || 
                              (_certCurrentStatusFilter === 'pending' && status === 'pending') ||
                              (_certCurrentStatusFilter === 'ready' && status === 'confirmed') ||
                              (_certCurrentStatusFilter === 'completed' && status === 'completed') ||
                              (_certCurrentStatusFilter === 'recent' && (created >= thirtyDaysAgo || created === 0)) ||
                              (_certCurrentStatusFilter === 'modified' && (updated >= thirtyDaysAgo || updated === 0));

        row.style.display = (matchesTerm && matchesStatus) ? '' : 'none';
    });

    if (_certCurrentStatusFilter === 'recent' || _certCurrentStatusFilter === 'modified') {
        const tbody = document.querySelector('#cert-requests-table tbody');
        if (tbody) {
            const rowArr = Array.from(tbody.querySelectorAll('.cert-table-row'));
            const key = (_certCurrentStatusFilter === 'modified') ? 'updated' : 'created';
            rowArr.sort((a, b) => {
                const cA = parseInt(a.dataset[key] || a.dataset.id || '0', 10);
                const cB = parseInt(b.dataset[key] || b.dataset.id || '0', 10);
                return cB - cA; // Newest first
            });
            rowArr.forEach(r => tbody.appendChild(r));
        }
    }
}

function toggleCertSort(btn) {
    _certSortAsc = !_certSortAsc;
    const icon = btn.querySelector('i');
    if (icon) {
        icon.className = _certSortAsc ? 'fa-solid fa-arrow-up-wide-short' : 'fa-solid fa-arrow-down-wide-short';
    }
    btn.title = _certSortAsc ? 'Sorted: Oldest First (Click to sort Newest first)' : 'Sorted: Newest First (Click to sort Oldest first)';

    const tbody = document.querySelector('#cert-requests-table tbody');
    if (!tbody) return;
    const rows = Array.from(tbody.querySelectorAll('.cert-table-row'));
    rows.sort((a, b) => {
        const idA = parseInt(a.dataset.id || '0', 10);
        const idB = parseInt(b.dataset.id || '0', 10);
        return _certSortAsc ? (idA - idB) : (idB - idA);
    });
    rows.forEach(r => tbody.appendChild(r));
}
</script>
