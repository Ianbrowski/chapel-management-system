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
$pendingCertCount = $certCounts['pending'] ?? count(array_filter($cert_requests, fn($r) => ($r['status'] ?? '') === 'Pending'));
$readyCertCount   = $certCounts['confirmed'] ?? count(array_filter($cert_requests, fn($r) => ($r['status'] ?? '') === 'Confirmed'));
?>

<link rel="stylesheet" href="assets/css/workspace.css?v=2.5">

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
                    Certificates
                </h1>
                <p class="page-subtitle" style="font-size: var(--font-page-subtitle, 14px); color: var(--text2); margin: 3px 0 0 0;">
                    Request official baptism, confirmation, marriage, or christian burial certificates &bull; Releasing at Parish Office (Tue&ndash;Sun, 8am&ndash;5pm)
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
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; margin-bottom: 8px;">
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
            <div class="panel-header" style="background: var(--bg2); padding: 10px 16px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
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
                        <button type="button" class="cert-status-filter" onclick="filterCertStatus('pending', this)" style="border: 1px solid transparent; background: transparent; color: var(--text2); font-size: 11.5px; font-weight: 600; padding: 4px 12px; border-radius: 999px; cursor: pointer; transition: all 0.15s ease;">Pending (<?php echo $pendingCertCount; ?>)</button>
                        <button type="button" class="cert-status-filter" onclick="filterCertStatus('ready', this)" style="border: 1px solid transparent; background: transparent; color: var(--text2); font-size: 11.5px; font-weight: 600; padding: 4px 12px; border-radius: 999px; cursor: pointer; transition: all 0.15s ease;">Ready (<?php echo $readyCertCount; ?>)</button>
                    </div>

                    <!-- Universal Sort Toggle Button -->
                    <button type="button" class="btn-sort-toggle" onclick="toggleCertSort(this)"
                            title="Sorted: Newest First (Click to sort Oldest first)"
                            style="height: 34px; width: 34px; min-width: 34px; padding: 0; display: inline-flex; align-items: center; justify-content: center; border-radius: 8px; border: 1px solid var(--border); background: var(--bg2); color: var(--text); cursor: pointer; transition: all 0.15s ease; flex-shrink: 0;" aria-label="Toggle sort order">
                        <i class="fa-solid fa-arrow-down-wide-short" style="font-size: 12px;"></i>
                    </button>

                    <div style="position: relative; width: 230px;">
                        <input type="text" id="cert-search-input" maxlength="60" placeholder="Search applicant, ref, purpose..." 
                               onkeyup="filterCertLedger()"
                               style="padding-left: 32px !important; padding-right: 24px !important; font-size: 12.5px; width: 100%; height: 34px; border-radius: 8px; border: 1px solid var(--border); background: var(--bg2); color: var(--text); box-sizing: border-box; outline: none; transition: border-color 0.15s ease;">
                        <i class="fa-solid fa-magnifying-glass" style="position: absolute; left: 11px; top: 50%; transform: translateY(-50%); font-size: 11.5px; color: var(--text2); pointer-events: none; z-index: 2;"></i>
                        <span id="cert-clear-search" onclick="clearCertSearch()" title="Clear search" style="display: none; position: absolute; right: 8px; top: 50%; transform: translateY(-50%); cursor: pointer; color: var(--text2); font-size: 14px; line-height: 1;">&times;</span>
                    </div>
                </div>
            </div>

            <!-- Table Body -->
            <div class="panel-body" style="padding: 0; overflow-y: auto; flex: 1;">
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
                    <table class="data-table" id="cert-requests-table" style="width: 100%; table-layout: fixed; border-collapse: collapse;">
                        <thead style="position: sticky; top: 0; background: var(--bg3); z-index: 5; border-bottom: 1px solid var(--border);">
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
                                $isCancelled = in_array($st, ['Cancelled', 'Rejected']);
                                $fee = (float)($req['total_fee'] ?? 200.00);
                                $pickupTime = !empty($req['start_time']) ? date('M d, Y', strtotime($req['start_time'])) : 'Pending';
                                
                                // Extract person name and year from title if formatted
                                $pTitle = $req['title'] ?? 'Certificate';
                            ?>
                            <tr class="cert-table-row" 
                                data-status="<?php echo strtolower($st); ?>"
                                data-title="<?php echo strtolower(htmlspecialchars($pTitle)); ?>"
                                data-id="<?php echo $req['event_id']; ?>"
                                data-created="<?php echo !empty($req['created_at']) ? strtotime($req['created_at']) : 0; ?>"
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
                                                #REQ-<?php echo str_pad((string)$req['event_id'], 4, '0', STR_PAD_LEFT); ?> &bull; Parish Archives
                                            </div>
                                        </div>
                                    </div>
                                </td>

                                <td style="padding: 10px 14px;">
                                    <div style="font-size: 11.5px; color: var(--text); font-weight: 500;">
                                        <?php echo htmlspecialchars($req['event_type'] ?? 'Ecclesiastical Certificate'); ?>
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
                                    ₱<?php echo number_format($fee, 2); ?>
                                </td>

                                <td style="padding: 10px 12px; text-align: center; white-space: nowrap;">
                                    <?php if ($isConfirmed): ?>
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
                                    <?php
                                        $currentUid = (int)($_SESSION['user_id'] ?? 0);
                                        $reqOwnerUid = (int)($req['requested_by'] ?? 0);
                                        $isRowOwner = ($currentUid > 0 && $reqOwnerUid === $currentUid);
                                        $canDeleteRow = $isAdmin || $isRowOwner || in_array($st, ['Cancelled', 'Rejected']);
                                        $canChatApplicant = ($isAdmin && $reqOwnerUid > 0 && $reqOwnerUid !== $currentUid);
                                    ?>
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
                                            <?php if ($canChatApplicant): ?>
                                            <a class="dropdown-item" href="javascript:void(0)" onclick="openCertApplicantChat(<?php echo $reqOwnerUid; ?>)" style="padding: 6px 10px; font-size: 11.5px; display: flex; align-items: center; gap: 6px; text-decoration: none; color: var(--text); border-radius: 4px;">
                                                <i class="fa-solid fa-comments" style="color: var(--accent);"></i> Chat Applicant
                                            </a>
                                            <?php elseif (!$isAdmin && !$isGuest): ?>
                                            <a class="dropdown-item" href="javascript:void(0)" onclick="if (window.ParishChat) ParishChat.toggleChat(true);" style="padding: 6px 10px; font-size: 11.5px; display: flex; align-items: center; gap: 6px; text-decoration: none; color: var(--text); border-radius: 4px;">
                                                <i class="fa-solid fa-comments" style="color: var(--accent);"></i> Chat Parish Office
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
                    <input type="hidden" name="event_type" value="Ecclesiastical Certificates">
                    <input type="hidden" name="chapel_id" value="0">
                    <input type="hidden" name="event_scope" value="cathedral">
                    <input type="hidden" name="custom_duration" value="0">
                    <input type="hidden" name="location_type" value="Church">
                    <input type="hidden" name="start_time" id="cert-field-datetime" value="<?php echo date('Y-m-d', strtotime('+2 days')) . 'T08:00'; ?>">
                    <input type="hidden" name="title" id="cert-field-title" value="Baptismal Certificate Request">

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
                            <div>
                                <label style="font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; color: var(--text2); display: block; margin-bottom: 4px;">
                                    Person's Name on Record <span style="color:#ef4444;">*</span>
                                </label>
                                <input type="text" id="cert-person-name" name="cert_person_name" required maxlength="80" placeholder="e.g. Juan dela Cruz (Full name as recorded in archives)" oninput="this.value = this.value.replace(/[^a-zA-ZñÑ\s\.\-']/g, ''); syncCertDynamicTitle()" 
                                       style="padding: 6px 12px; height: 36px; font-size: 12.5px; box-sizing: border-box; width: 100%; border-radius: 6px; background: #FFFFFF; color: var(--text); border: 1px solid var(--border);">
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
                                       value="<?php echo date('Y-m-d', strtotime('+2 days')); ?>"
                                       onchange="onCertPickupDateChange(this.value)"
                                       required
                                       style="padding: 6px 12px; height: 36px; font-size: 12.5px; box-sizing: border-box; width: 100%; border-radius: 6px; border: 1px solid var(--border); background: #FFFFFF; color: var(--text); font-weight: 500;">
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

                        <div>
                            <label style="font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; color: var(--text2); display: block; margin-bottom: 4px;">
                                Email Address <span style="font-weight: 400; color: var(--text2);">(Optional)</span>
                            </label>
                            <input type="email" name="requester_email" maxlength="100" placeholder="e.g. juan@email.com" 
                                   style="padding: 6px 12px; height: 36px; font-size: 12px; box-sizing: border-box; width: 100%; border-radius: 6px; border: 1px solid var(--border); background: #FFFFFF; color: var(--text);">
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
    <div class="modal" style="max-width: 500px; width: 92%; max-height: 85vh; display: flex; flex-direction: column; padding: 0; border-radius: 12px; background: var(--surface); border: 1px solid var(--border); box-shadow: var(--shadow-lg); overflow: hidden;">
        <div style="padding: 16px 20px; background: var(--surface); border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center;">
            <div style="display: flex; align-items: center; gap: 10px;">
                <i class="fa-solid fa-certificate" style="color: var(--accent);"></i>
                <h3 style="font-family: var(--font-family, 'Inter', sans-serif); font-size: 15px; font-weight: 700; color: var(--text); margin: 0;">Certificate Details</h3>
            </div>
            <button class="btn-icon" onclick="closeCertViewModal()" title="Close" style="border: none; background: transparent; cursor: pointer; color: var(--text2);">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div id="cert-view-body" style="padding: 20px; display: flex; flex-direction: column; gap: 12px; flex: 1; overflow-y: auto; background: var(--surface);"></div>
        <div style="padding: 14px 20px; background: var(--surface); border-top: 1px solid var(--border); display: flex; justify-content: flex-end;">
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
    <input type="hidden" name="form_section" value="event_review">
    <input type="hidden" name="redirect_section" value="request_certificate">
    <input type="hidden" name="action" value="approve">
    <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
    <input type="hidden" name="id" id="cert-approve-id-input">
</form>

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
                <div style="font-size:12px; color:#111111;">IFI Cathedral Parish Office &bull; Tuesday to Sunday, 8am&ndash;5pm</div>
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

function viewCertDetailsFromRow(tr) {
    const raw = tr.getAttribute('data-req');
    if (!raw) return;
    try {
        const req = JSON.parse(raw);
        viewCertDetails(req);
    } catch(e){}
}

function viewCertDetails(req) {
    const statusColors = { Confirmed:'#16a34a', Pending:'#c5a059', Cancelled:'#dc2626', Rejected:'#dc2626' };
    const sc = statusColors[req.status] || '#6b7280';
    
    let pickupFormatted = req.start_time || 'Pending';
    try {
        const dt = new Date(req.start_time.replace(/-/g, '/'));
        if (!isNaN(dt.getTime())) {
            pickupFormatted = dt.toLocaleDateString('en-US', { weekday: 'short', year: 'numeric', month: 'short', day: 'numeric' });
        }
    } catch(e){}
    
    document.getElementById('cert-view-body').innerHTML = `
        <div style="background: #F6F6F6; border: 1px solid var(--border); border-radius: 10px; padding: 12px 14px; display: flex; align-items: center; gap: 12px;">
            <div style="width: 40px; height: 40px; border-radius: 8px; background: #FFFFFF; border: 1px solid var(--border); color: #111111; display: flex; align-items: center; justify-content: center; font-size: 16px; flex-shrink: 0;">
                <i class="fa-solid fa-file-invoice"></i>
            </div>
            <div style="min-width: 0; flex: 1;">
                <div style="display: flex; gap: 6px; align-items: center; margin-bottom: 2px;">
                    <span class="badge" style="background: #FFFFFF; border: 1px solid var(--border); font-size: 10px; font-weight: 600; padding: 2px 8px; color: var(--text);">Certificate Issuance</span>
                    <span class="badge" style="background: ${sc}18; color: ${sc}; border: 1px solid ${sc}35; font-size: 10px; font-weight: 600; padding: 2px 8px;">${escapeHtml(req.status)}</span>
                    <span style="font-size: 10px; color: var(--text2); font-family: monospace;">#REQ-${String(req.event_id).padStart(4, '0')}</span>
                </div>
                <div style="font-family: var(--font-family, 'Inter', sans-serif); font-size: 15px; font-weight: 700; color: var(--text); line-height: 1.3;">${escapeHtml(req.title)}</div>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px;">
            <div style="background: #F6F6F6; border: 1px solid var(--border); border-radius: 8px; padding: 10px 12px;">
                <div style="font-size: 10px; text-transform: uppercase; color: var(--text2); font-weight: 700; margin-bottom: 2px;"><i class="fa-regular fa-calendar-check" style="color:var(--text); margin-right:4px;"></i> Target Pickup Date</div>
                <div style="font-size: 12px; color: var(--text); font-weight: 600;">${pickupFormatted}</div>
            </div>
            <div style="background: #F6F6F6; border: 1px solid var(--border); border-radius: 8px; padding: 10px 12px;">
                <div style="font-size: 10px; text-transform: uppercase; color: var(--text2); font-weight: 700; margin-bottom: 2px;"><i class="fa-solid fa-building-columns" style="color:var(--text); margin-right:4px;"></i> Releasing Office</div>
                <div style="font-size: 12px; color: var(--text); font-weight: 600;">IFI Cathedral Parish Office</div>
            </div>
            <div style="background: #F6F6F6; border: 1px solid var(--border); border-radius: 8px; padding: 10px 12px;">
                <div style="font-size: 10px; text-transform: uppercase; color: var(--text2); font-weight: 700; margin-bottom: 2px;"><i class="fa-solid fa-id-card-clip" style="color:var(--text); margin-right:4px;"></i> Processing Section</div>
                <div style="font-size: 12px; color: var(--text); font-weight: 600;">Archives &amp; Records</div>
            </div>
            <div style="background: #F6F6F6; border: 1px solid var(--border); border-radius: 8px; padding: 10px 12px;">
                <div style="font-size: 10px; text-transform: uppercase; color: var(--text2); font-weight: 700; margin-bottom: 2px;"><i class="fa-solid fa-receipt" style="color:var(--text); margin-right:4px;"></i> Recorded Total Fee</div>
                <div style="font-size: 12px; color: #111111; font-weight: 700;">PHP ${parseFloat(req.total_fee || 200).toLocaleString('en-US', {minimumFractionDigits: 2})}</div>
            </div>
        </div>

        ${req.rejection_note ? `
        <div style="background: rgba(239,68,68,0.08); border: 1px solid rgba(239,68,68,0.25); border-radius: 8px; padding: 8px 10px;">
            <div style="font-size: 9.5px; text-transform: uppercase; color: #ef4444; font-weight: 700; margin-bottom: 2px;">Note from Office</div>
            <div style="font-size: 11.5px; color: #fca5a5; line-height: 1.35;">${escapeHtml(req.rejection_note)}</div>
        </div>` : ''}
    `;
    
    const m = document.getElementById('cert-view-modal');
    m.style.display = 'flex';
    m.classList.add('open');
}

function closeCertViewModal() {
    const m = document.getElementById('cert-view-modal');
    if (m) {
        m.style.display = 'none';
        m.classList.remove('open');
    }
}

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

window.approveCertRequest = approveCertRequest;
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
    if (input) {
        input.value = '';
        filterCertLedger();
        input.focus();
    }
}

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

        const matchesTerm = !term || title.includes(term) || id.includes(term);
        const matchesStatus = (_certCurrentStatusFilter === 'all') || 
                              (_certCurrentStatusFilter === 'pending' && status === 'pending') ||
                              (_certCurrentStatusFilter === 'ready' && status === 'confirmed') ||
                              (_certCurrentStatusFilter === 'recent' && (created >= thirtyDaysAgo || created === 0));

        row.style.display = (matchesTerm && matchesStatus) ? '' : 'none';
    });

    if (_certCurrentStatusFilter === 'recent') {
        const tbody = document.querySelector('#cert-requests-table tbody');
        if (tbody) {
            const rowArr = Array.from(tbody.querySelectorAll('.cert-table-row'));
            rowArr.sort((a, b) => {
                const cA = parseInt(a.dataset.created || a.dataset.id || '0', 10);
                const cB = parseInt(b.dataset.created || b.dataset.id || '0', 10);
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
