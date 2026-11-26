<?php
    $inboxTab       = $inbox_tab ?? 'active';
    $isArchived     = ($inboxTab === 'archived');
    $activeCount    = $inq_active_count ?? count($inquiries);
    $archivedCount  = $inq_archive_count ?? 0;
    $inqPage        = $inq_page ?? 1;
    $inqPages       = $inq_pages ?? 1;
    $inqTotal       = $inq_total ?? count($inquiries);
?>

<div class="workspace workspace-fluid inbox-workspace">

    <!-- ─── PAGE HEADER (ROW 1: TITLE + STAT BADGES PINNED TOP-RIGHT) ─── -->
    <div class="page-header-bar" style="display: flex; justify-content: space-between; align-items: center; flex-shrink: 0; border-bottom: 1px solid var(--border); padding-bottom: 8px; margin-bottom: 6px; flex-wrap: wrap; gap: 10px;">
        <div style="display: flex; align-items: center; gap: 12px;">
            <div class="page-header-icon" style="width: 38px; height: 38px; border-radius: 9px; background: rgba(201,168,76,0.14); color: var(--accent); display: flex; align-items: center; justify-content: center; font-size: 17px; flex-shrink: 0;">
                <i class="fa-solid fa-inbox"></i>
            </div>
            <div>
                <h1 class="page-title" style="font-family: var(--font-family, 'Inter', sans-serif); font-size: var(--font-page-title, 20px); font-weight: 700; color: var(--text); margin: 0; line-height: 1.2;">
                    <?php echo $isMember ? 'Inbox' : ($isArchived ? 'Archived Inquiries' : 'Inbox'); ?>
                </h1>
                <p class="page-subtitle" style="font-size: var(--font-page-subtitle, 14px); color: var(--text2); margin: 3px 0 0 0;">Parishioner communications, canonical inquiries, and service ticket requests</p>
            </div>
        </div>

        <!-- Right: Stat Badges Pinned Top-Right -->
        <div style="display: flex; align-items: center; gap: 8px;">
            <span class="badge" style="background: var(--bg3, #F4F4F5); border: 1px solid var(--border); color: var(--text2); font-size: 11.5px; font-weight: 600; padding: 4px 12px; border-radius: 999px;">
                <?php echo number_format($inqTotal); ?> Inquiries
            </span>
            <span class="badge" style="background: rgba(197,160,89,0.12); color: var(--accent); border: 1px solid rgba(197,160,89,0.3); font-size: 11.5px; font-weight: 600; padding: 4px 12px; border-radius: 999px;">
                <?php echo number_format($activeCount); ?> Active
            </span>
            <?php if ($archivedCount > 0): ?>
            <span class="badge" style="background: var(--bg3, #F4F4F5); border: 1px solid var(--border); color: var(--text2); font-size: 11.5px; font-weight: 600; padding: 4px 12px; border-radius: 999px;">
                <?php echo number_format($archivedCount); ?> Archived
            </span>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!$isMember): ?>
    <!-- ─── SUBNAV ROW (ROW 2: TABS ON LEFT, ACTIONS ON RIGHT) ─── -->
    <div style="display: flex; justify-content: space-between; align-items: center; gap: 12px; margin-bottom: 0; flex-wrap: wrap;">
        <!-- Left: Segmented Navigation Tabs -->
        <div style="display: inline-flex; align-items: center; background: var(--bg3, #F4F4F5); padding: 3px; border-radius: 999px; border: 1px solid var(--border); gap: 2px;">
            <a href="dashboard.php?section=public_inquiries&inbox_tab=active"
               class="btn btn-sm"
               style="padding: 4px 14px; font-size: 11.5px; font-weight: 600; border-radius: 999px; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; <?php echo !$isArchived ? 'background: var(--surface, #FFFFFF); color: var(--text, #111111); box-shadow: 0 1px 3px rgba(0,0,0,0.08); border: 1px solid var(--border);' : 'background: transparent; color: var(--text2, #71717A); border: 1px solid transparent;'; ?>">
                <i class="fa-solid fa-inbox"></i> Active
                <span style="font-size: 11px; font-weight: 700; opacity: 0.85; background: <?php echo !$isArchived ? 'var(--bg3, #F4F4F5)' : 'rgba(0,0,0,0.05)'; ?>; padding: 1px 7px; border-radius: 999px;"><?php echo $activeCount; ?></span>
            </a>
            <a href="dashboard.php?section=public_inquiries&inbox_tab=archived"
               class="btn btn-sm"
               style="padding: 4px 14px; font-size: 11.5px; font-weight: 600; border-radius: 999px; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; <?php echo $isArchived ? 'background: var(--surface, #FFFFFF); color: var(--text, #111111); box-shadow: 0 1px 3px rgba(0,0,0,0.08); border: 1px solid var(--border);' : 'background: transparent; color: var(--text2, #71717A); border: 1px solid transparent;'; ?>">
                <i class="fa-solid fa-box-archive"></i> Archived
                <span style="font-size: 11px; font-weight: 700; opacity: 0.85; background: <?php echo $isArchived ? 'var(--bg3, #F4F4F5)' : 'rgba(0,0,0,0.05)'; ?>; padding: 1px 7px; border-radius: 999px;"><?php echo $archivedCount; ?></span>
            </a>
        </div>
        <!-- Right: Contextual Actions -->
        <div style="display: flex; align-items: center; gap: 8px;">
            <a href="dashboard.php?section=public_inquiries&inbox_tab=<?php echo $inboxTab; ?>" class="btn btn-ghost btn-sm" style="font-size: 11.5px; padding: 5px 12px; border-radius: 6px; border: 1px solid var(--border); display: inline-flex; align-items: center; gap: 6px; text-decoration: none; color: var(--text);" title="Refresh Inbox">
                <i class="fa-solid fa-rotate-right"></i> Refresh
            </a>
        </div>
    </div>
    <?php endif; ?>

    <!-- ─── 65/35 SPLIT WORKSPACE ─── -->
    <div class="inbox-split-panel">

        <!-- ══ LEFT COLUMN: Filters + Inbox List ══ -->
        <div class="inbox-split-left">

            <!-- Search & Filter Bar (Comfortable Single-Row) -->
            <div class="panel inbox-search-panel" style="flex: none !important; height: auto !important; background: var(--panel-bg); border: var(--panel-border); border-radius: var(--panel-radius, 14px);">
                <div class="panel-body" style="padding: 10px 14px;">
                    <form method="GET" style="display: flex; flex-wrap: wrap; gap: 8px; align-items: center; margin: 0; width: 100%;">
                        <input type="hidden" name="section" value="public_inquiries">
                        <input type="hidden" name="inbox_tab" value="<?php echo $inboxTab; ?>">

                        <div style="flex: 1 1 160px; min-width: 140px; position: relative;">
                            <i class="fa-solid fa-magnifying-glass" style="position: absolute; left: 11px; top: 50%; transform: translateY(-50%); font-size: 11px; color: var(--text2); pointer-events: none; z-index: 2;"></i>
                            <input type="text" id="inq-search" name="inq_search"
                                   data-ac-mode="dom" data-ac-target=".inq-row" data-ac-extract="inquiry"
                                   placeholder="Search by requester, type..."
                                   value="<?php echo htmlspecialchars($_GET['inq_search'] ?? ''); ?>"
                                   onkeyup="filterInquiries()"
                                   style="width: 100%; height: 32px; padding: 0 28px 0 34px !important; background: var(--bg); border: 1px solid var(--border); border-radius: 6px; color: var(--text); font-size: 12px; box-sizing: border-box;">
                            <?php if (!empty($_GET['inq_search'])): ?>
                                <a href="dashboard.php?section=public_inquiries&amp;inbox_tab=<?php echo $inboxTab; ?>" style="position: absolute; right: 8px; top: 50%; transform: translateY(-50%); color: var(--text2); font-size: 14px; text-decoration: none; cursor: pointer; line-height: 1; padding: 2px;" title="Clear search">&times;</a>
                            <?php endif; ?>
                        </div>

                        <select name="inquiry_status" onchange="this.form.submit()"
                                style="width: auto !important; min-width: 130px; max-width: 170px; height: 32px !important; font-size: 12px; flex: 0 0 auto;">
                            <option value="">All Statuses</option>
                            <option value="Pending"      <?php echo ($_GET['inquiry_status'] ?? '') === 'Pending'      ? 'selected' : ''; ?>>Pending</option>
                            <option value="In Progress"  <?php echo ($_GET['inquiry_status'] ?? '') === 'In Progress'  ? 'selected' : ''; ?>>In Progress</option>
                            <option value="Reviewed"     <?php echo ($_GET['inquiry_status'] ?? '') === 'Reviewed'     ? 'selected' : ''; ?>>Reviewed</option>
                            <option value="For Claiming" <?php echo ($_GET['inquiry_status'] ?? '') === 'For Claiming' ? 'selected' : ''; ?>>For Claiming</option>
                            <option value="Completed"    <?php echo ($_GET['inquiry_status'] ?? '') === 'Completed'    ? 'selected' : ''; ?>>Completed</option>
                            <option value="Cancelled"    <?php echo ($_GET['inquiry_status'] ?? '') === 'Cancelled'    ? 'selected' : ''; ?>>Cancelled</option>
                        </select>

                        <!-- Sort Toggle Icon (Up / Down) -->
                        <?php $curInqSort = $_GET['inq_sort'] ?? 'date_desc'; ?>
                        <input type="hidden" name="inq_sort" id="inq-sort-val" value="<?php echo htmlspecialchars($curInqSort); ?>">
                        <button type="button" class="btn-sort-toggle" onclick="toggleSortDirection(this, 'inq-sort-val')"
                                title="<?php echo ($curInqSort === 'date_asc') ? 'Sorted: Oldest First (Click to sort Newest first)' : 'Sorted: Newest First (Click to sort Oldest first)'; ?>"
                                style="height: 32px; width: 34px; min-width: 34px; padding: 0; display: inline-flex; align-items: center; justify-content: center; border-radius: 6px; border: 1px solid var(--border); background: var(--bg); color: var(--text); cursor: pointer; transition: all 0.15s ease; flex-shrink: 0;" aria-label="Toggle sort order">
                            <i class="fa-solid <?php echo ($curInqSort === 'date_asc') ? 'fa-arrow-up-wide-short' : 'fa-arrow-down-wide-short'; ?>" style="font-size: 13px;"></i>
                        </button>

                        <button type="submit" class="btn btn-primary btn-sm" style="height: 32px; padding: 0 12px; font-size: 12px; flex: 0 0 auto; display: inline-flex; align-items: center; gap: 4px;">
                            <i class="fa-solid fa-magnifying-glass"></i> Search
                        </button>
                        <a href="dashboard.php?section=public_inquiries&inbox_tab=<?php echo $inboxTab; ?>"
                           class="btn btn-ghost btn-sm" style="height: 30px; padding: 0 8px; font-size: 11.5px; color: var(--text2); flex: 0 0 auto; display: inline-flex; align-items: center; gap: 4px;">
                            <i class="fa-solid fa-times"></i> Reset
                        </a>
                    </form>
                </div>
            </div>

            <!-- Inbox Table Panel -->
            <div class="panel inbox-table-panel" style="flex: 1; display: flex; flex-direction: column; min-height: 0; overflow: hidden; background: var(--panel-bg); border: var(--panel-border);">
                <div class="panel-header" style="background: var(--panel-header-bg); padding: 10px 14px; border-bottom: var(--panel-border);">
                    <div class="panel-title" style="font-size: 0.88rem; display: flex; align-items: center; gap: 8px;">
                        <i class="fa-solid fa-<?php echo ($isArchived || $isMember) ? 'box-archive' : 'inbox'; ?>" style="color: var(--accent);"></i>
                        <span><?php echo $isMember ? 'My Sent Requests' : ($isArchived ? 'Archived Requests' : 'Incoming Requests'); ?></span>
                    </div>
                    <span class="badge" style="background: var(--accent-glow); color: var(--accent); font-weight: 700; font-size: 11px; padding: 2px 8px;"><?php echo $inqTotal; ?> requests</span>
                </div>

                <div class="panel-body" style="padding: 0; overflow-y: auto; flex: 1; min-height: 0;">
                    <?php if (empty($inquiries)): ?>
                        <div style="padding: 40px 20px; text-align: center; color: var(--text2);">
                            <i class="fa-solid fa-inbox" style="font-size: 44px; opacity: 0.3; margin-bottom: 12px; display: block;"></i>
                            <div style="font-weight: 600; color: var(--text);"><?php echo $isMember ? 'No Personal Requests Filed Yet' : ($isArchived ? 'No archived requests.' : 'No incoming requests yet.'); ?></div>
                            <?php if ($isMember): ?>
                            <p style="font-size: 12px; margin: 4px auto 0; max-width: 380px;">When you submit certificate requests or event inquiries, real-time updates and pickup notices from the Cathedral Office will be listed here.</p>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                    <div class="table-container" style="border: none; margin: 0; width: 100%;">
                        <table class="data-table" style="width: 100%; table-layout: fixed; border-collapse: collapse;">
                            <thead>
                                <tr>
                                    <th style="width: 40px; text-align: center;">#</th>
                                    <th style="width: auto;">Parishioner &amp; Request</th>
                                    <th style="width: 110px;">Target Date</th>
                                    <th style="width: 118px; text-align: center;">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $startIdx = (($inqPage - 1) * ($limit ?? 25)) + 1;
                                foreach ($inquiries as $idx => $inq):
                                    $st = $inq['status'] ?: 'Pending';
                                    $isGood = in_array($st, ['Completed', 'Resolved', 'For Claiming']);
                                    $isBad  = in_array($st, ['Cancelled', 'Rejected']);
                                    
                                    $badgeStyle = 'background: #F6F6F6; color: #737373; border: 1px solid #E5E5E5;';
                                    if ($isGood) $badgeStyle = 'background: #FFFFFF; color: #2E9E5B; border: 1px solid #2E9E5B;';
                                    if ($isBad)  $badgeStyle = 'background: #FFFFFF; color: #D64545; border: 1px solid #D64545;';

                                    $indicatorColor = $isGood ? '#2E9E5B' : ($isBad ? '#D64545' : '#E5E5E5');

                                    $type = $inq['request_type'];
                                    $cat  = $inq['service_category'] ?: 'Other';
                                    $typeIcon = 'fa-circle-question';
                                    if ($type === 'Certificate')   $typeIcon = 'fa-file-invoice';
                                    elseif ($type === 'Merchandise') $typeIcon = 'fa-store';
                                    elseif ($type === 'Event Inquiry') $typeIcon = 'fa-calendar-day';
                                ?>
                                <tr data-inquiry-id="<?php echo (int)$inq['inquiry_id']; ?>"
                                    data-inquiry="<?php echo htmlspecialchars(json_encode($inq, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>"
                                    onclick="showWorkspaceInquiryDetails(this)"
                                    style="cursor: pointer; border-bottom: 1px solid var(--border);" class="inq-row" title="Click to view details">
                                    <td style="text-align: center; color: #737373; font-size: 11px; font-family: monospace; font-weight: 600;"><?php echo $startIdx + $idx; ?></td>
                                    <td style="white-space: normal;">
                                        <div style="display: flex; align-items: center; gap: 10px; overflow-wrap: break-word;">
                                            <div style="flex-shrink: 0; width: 3px; height: 32px; border-radius: 2px; background: <?php echo $indicatorColor; ?>;"></div>
                                            <div style="min-width: 0;">
                                                <div style="font-weight: 600; display: flex; align-items: center; gap: 8px;">
                                                    <i class="fa-solid <?php echo $typeIcon; ?>" style="color: #737373; font-size: 11px;"></i>
                                                    <span style="font-size: 13px; color: #111111;"><?php echo htmlspecialchars($cat); ?></span>
                                                    <span class="badge" style="font-size: 11.5px; background: #F6F6F6; color: #737373; border: 1px solid #E5E5E5; padding: 2px 6px;"><?php echo htmlspecialchars($type); ?></span>
                                                </div>
                                                <div style="font-size: 12px; margin-top: 2px; color: #111111;">
                                                    <strong><?php echo htmlspecialchars($inq['full_name']); ?></strong>
                                                    <span style="color: #737373; font-size: 11.5px;"> · <?php echo htmlspecialchars($inq['email']); ?></span>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <?php
                                        $rawDate = trim((string)($inq['target_date'] ?? ''));
                                        $ts = ($rawDate !== '' && $rawDate !== '0000-00-00') ? strtotime($rawDate) : false;
                                    ?>
                                    <td style="font-weight: 600; color: #111111; font-size: 12px;">
                                        <?php echo ($ts !== false && $ts > 0) ? date('M d, Y', $ts) : '—'; ?>
                                    </td>
                                    <td style="text-align: center;">
                                        <span class="badge" style="font-size: 11.5px; padding: 2px 8px; border-radius: 6px; font-weight: 600; <?php echo $badgeStyle; ?>"><?php echo htmlspecialchars($st); ?></span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>

                <?php if ($inqPages > 1): ?>
                <div class="panel-footer" style="display: flex; justify-content: space-between; align-items: center; padding: 8px;">
                    <div>
                        <?php
                            $baseUrl = "dashboard.php?section=public_inquiries&inbox_tab={$inboxTab}";
                            if (!empty($_GET['inquiry_status'])) $baseUrl .= "&inquiry_status=" . urlencode($_GET['inquiry_status']);
                            if (!empty($_GET['inq_sort'])) $baseUrl .= "&inq_sort=" . urlencode($_GET['inq_sort']);
                        ?>
                        <?php if ($inqPage > 1): ?>
                            <a href="<?php echo $baseUrl . '&inq_page=' . ($inqPage - 1); ?>" class="btn btn-ghost btn-sm" style="padding: 4px 8px;">
                                <i class="fa-solid fa-arrow-left"></i> Prev
                            </a>
                        <?php endif; ?>
                    </div>
                    <span style="font-size: 0.75rem; color: var(--text2);">Page <?php echo $inqPage; ?> of <?php echo $inqPages; ?></span>
                    <div>
                        <?php if ($inqPage < $inqPages): ?>
                            <a href="<?php echo $baseUrl . '&inq_page=' . ($inqPage + 1); ?>" class="btn btn-ghost btn-sm" style="padding: 4px 8px;">
                                Next <i class="fa-solid fa-arrow-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>

        </div><!-- end LEFT column -->

        <!-- ══ RIGHT COLUMN: Inspector Panel ══ -->
        <div class="inbox-split-right">
            <div class="panel inbox-inspector-panel" style="flex: 1; display: flex; flex-direction: column; min-height: 0; overflow: hidden;">
                <div class="panel-header">
                    <div class="panel-title">
                        <i class="fa-solid fa-circle-info"></i>
                        <span>Request Inspector</span>
                    </div>
                    <span id="inspector-badge" style="font-size: 11px; color: var(--text2); display: none;">
                        <i class="fa-solid fa-arrow-left" style="font-size: 10px;"></i> Click a row
                    </span>
                </div>
                <div class="panel-body" id="workspace-inquiry-inspector"
                     style="padding: 12px; display: flex; flex-direction: column; gap: 12px; flex: 1; overflow-y: auto; min-height: 0;">
                    <div class="empty-state" style="padding: 40px 10px;">
                        <i class="fa-solid fa-inbox" style="font-size: 40px; opacity: 0.15; margin-bottom: 12px;"></i>
                        <h3 style="font-size: 0.95rem; font-weight: 600; color: var(--text);">Select a Request</h3>
                        <p style="font-size: 0.8rem; color: var(--text2);">Click any row in the inbox list to read the submission, contact the parishioner, update workflow status, or view notes.</p>
                    </div>
                </div>
            </div>
        </div><!-- end RIGHT column -->

    </div><!-- end grid -->

</div><!-- end workspace -->

<!-- Hidden contact forms used by action triggers -->
<form id="form-quick-sms" method="POST" action="dashboard.php" style="display: none;">
    <input type="hidden" name="form_section" value="public_inquiries">
    <input type="hidden" name="action" value="send_quick_sms">
    <input type="hidden" name="id" id="inq-sms-id">
    <input type="hidden" name="message" id="inq-sms-msg">
    <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
</form>
<form id="form-quick-email" method="POST" action="dashboard.php" style="display: none;">
    <input type="hidden" name="form_section" value="public_inquiries">
    <input type="hidden" name="action" value="send_quick_email">
    <input type="hidden" name="id" id="inq-email-id">
    <input type="hidden" name="message" id="inq-email-msg">
    <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
</form>

<style>
.inbox-workspace {
    gap: 10px !important;
}
.inbox-split-panel {
    display: flex;
    gap: var(--split-gap, clamp(16px, 1.8vw, 24px));
    flex: 1;
    min-height: 0;
    overflow: hidden;
    width: 100%;
}
.inbox-split-left {
    flex: 1 1 64%;
    min-width: 0;
    height: 100%;
    display: flex;
    flex-direction: column;
    gap: 12px;
    min-height: 0;
}
.inbox-split-panel .panel {
    padding: 0 !important;
    margin-bottom: 0 !important;
}
.inbox-search-panel .panel-body {
    padding: 10px 14px !important;
}
.inbox-table-panel .panel-header,
.inbox-inspector-panel .panel-header {
    padding: 11px 16px !important;
}
.inbox-table-panel .panel-body {
    padding: 0 !important;
}
.inbox-table-panel .data-table thead th {
    position: sticky;
    top: 0;
    z-index: 2;
    background: var(--bg2, #FAFAFA);
}
.inbox-inspector-panel .panel-body {
    padding: 12px 14px !important;
}
.inbox-split-right {
    flex: 0 0 36%;
    max-width: 480px;
    min-width: 340px;
    height: 100%;
    display: flex;
    flex-direction: column;
    min-height: 0;
}
@media (max-width: 1100px) {
    .inbox-split-panel {
        flex-direction: column;
        overflow-y: auto;
        height: auto;
    }
    .inbox-split-left,
    .inbox-split-right {
        flex: 1 1 100%;
        max-width: 100%;
        min-width: 100%;
        height: auto;
    }
}
.inq-row.active-row {
    background: rgba(201, 168, 76, 0.1) !important;
    border-left: 3px solid var(--accent) !important;
}
.inbox-detail-card {
    background: var(--bg);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 12px;
    display: flex;
    flex-direction: column;
    gap: 10px;
}
.inbox-detail-item {
    display: flex;
    flex-direction: column;
    gap: 2px;
    border-bottom: 1px solid rgba(255,255,255,0.04);
    padding-bottom: 8px;
}
.inbox-detail-label {
    font-size: 11px;
    text-transform: uppercase;
    color: var(--text2);
    font-weight: 700;
    letter-spacing: 0.04em;
}
.inbox-detail-value {
    font-size: 0.85rem;
    color: var(--text);
    line-height: 1.4;
}
</style>

<script>
var currentInquiry = null;
const canManageInquiries = <?php echo $can_manage_inquiries ? 'true' : 'false'; ?>;
const inqCsrf = '<?php echo $csrf; ?>';
const isArchivedInbox = <?php echo $isArchived ? 'true' : 'false'; ?>;

function escInqHtml(v) {
    return String(v ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function showWorkspaceInquiryDetails(inq, rowElement) {
    if (inq instanceof HTMLElement) {
        rowElement = inq;
        const raw = inq.getAttribute('data-inquiry');
        if (raw) {
            try { inq = JSON.parse(raw); } catch (e) { console.error('Failed to parse inquiry data:', e); return; }
        }
    }
    if (!inq || typeof inq !== 'object') return;

    // Highlight active row
    document.querySelectorAll('.inq-row').forEach(r => r.classList.remove('active-row'));
    if (rowElement) rowElement.classList.add('active-row');

    currentInquiry = inq;
    const inspector = document.getElementById('workspace-inquiry-inspector');
    if (!inspector) return;

    const formatDate = (dateStr) => {
        if (!dateStr) return 'N/A';
        const d = new Date(dateStr.replace(/-/g, '/'));
        return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
    };

    const statusClasses = {
        'Pending': 'badge-pending',
        'In Progress': 'bg-blue',
        'Reviewed': 'bg-blue',
        'For Claiming': 'bg-green-glow',
        'Completed': 'badge-confirmed',
        'Resolved': 'badge-confirmed',
        'Cancelled': 'badge-cancelled',
        'Rejected': 'badge-cancelled'
    };
    const statusBadgeClass = statusClasses[inq.status] || 'badge-pending';

    // Merchandise block
    const merchMeta = extractMerchMeta(inq);
    let merchBlockHtml = '';
    if (merchMeta.isMerch) {
        const canFulfillDirect = (canManageInquiries && !isArchivedInbox && merchMeta.itemId > 0);
        merchBlockHtml = `
            <div style="background: var(--bg3); border: 1px solid var(--border); padding: 10px; border-radius: 8px; font-size: 12px;">
                <div style="font-weight: 700; color: var(--accent); margin-bottom: 4px;"><i class="fa-solid fa-store"></i> Merchandise Reservation</div>
                <div style="color: var(--text); font-weight: 600;">${escInqHtml(merchMeta.itemName)}</div>
                <div style="color: var(--text2); font-size: 11px; margin-top: 2px;">Qty: <strong>${escInqHtml(String(merchMeta.itemQty))}</strong>${merchMeta.itemId > 0 ? ` &bull; Item #${escInqHtml(String(merchMeta.itemId))}` : ''}</div>
                ${canManageInquiries && !isArchivedInbox ? `
                <div style="display: flex; gap: 6px; margin-top: 8px;">
                    ${canFulfillDirect ? `
                    <button type="button" class="btn btn-success btn-sm" onclick="submitMerchFulfill()" style="flex: 1.2; justify-content: center; font-size: 11px; height: 30px; font-weight: 700;" title="Record sale, deduct inventory, archive, and email/SMS buyer">
                        <i class="fa-solid fa-circle-check"></i> Fulfill &amp; Notify
                    </button>` : ''}
                    <button type="button" class="btn btn-primary btn-sm" onclick="transferInquiryToMerch()" style="flex: 1; justify-content: center; font-size: 11px; height: 30px;">
                        <i class="fa-solid fa-basket-shopping"></i> Open POS
                    </button>
                </div>` : ''}
            </div>`;
    }

    // Contact buttons (uses Office Response Note above if filled, else polite acknowledgment)
    let contactButtons = '';
    if (canManageInquiries && !isArchivedInbox) {
        contactButtons = `
            <div style="display: flex; flex-direction: column; gap: 5px;">
                <div style="display: flex; gap: 6px;">
                    <button type="button" class="btn btn-ghost btn-sm" style="flex:1; padding:6px; justify-content:center;" onclick="submitInquiryQuickContact('sms')" ${inq.phone ? '' : 'disabled'} title="${inq.phone ? 'Send Office Response Note (or acknowledgment) via SMS' : 'No phone number'}">
                        <i class="fa-solid fa-message"></i> Send SMS
                    </button>
                    <button type="button" class="btn btn-ghost btn-sm" style="flex:1; padding:6px; justify-content:center;" onclick="submitInquiryQuickContact('email')" ${inq.email ? '' : 'disabled'} title="${inq.email ? 'Send Office Response Note (or acknowledgment) via Email' : 'No email address'}">
                        <i class="fa-solid fa-envelope"></i> Send Email
                    </button>
                </div>
                <div style="font-size: 10.5px; color: var(--text2); text-align: center;">Uses Office Response Note below if filled, otherwise sends a receipt confirmation.</div>
            </div>`;
    }

    // Action area
    let actionAreaHtml = '';
    if (canManageInquiries) {
        if (isArchivedInbox) {
            actionAreaHtml = `
                <div style="display:flex; gap:6px; margin-top:4px;">
                    <form method="POST" action="dashboard.php" onsubmit="return confirm('Restore this request?')" style="margin:0; flex:1;">
                        <input type="hidden" name="form_section" value="public_inquiries">
                        <input type="hidden" name="action" value="unarchive">
                        <input type="hidden" name="id" value="${inq.inquiry_id}">
                        <input type="hidden" name="csrf" value="${inqCsrf}">
                        <button type="submit" class="btn btn-primary btn-sm" style="width:100%; justify-content:center; padding:8px;"><i class="fa-solid fa-arrow-rotate-left"></i> Restore</button>
                    </form>
                    <form method="POST" action="dashboard.php" onsubmit="return confirm('Permanently delete?')" style="margin:0; flex:1;">
                        <input type="hidden" name="form_section" value="public_inquiries">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="${inq.inquiry_id}">
                        <input type="hidden" name="csrf" value="${inqCsrf}">
                        <button type="submit" class="btn btn-danger btn-sm" style="width:100%; justify-content:center; padding:8px;"><i class="fa-solid fa-trash"></i> Delete</button>
                    </form>
                </div>`;
        } else {
            actionAreaHtml = `
                <form method="POST" action="dashboard.php" id="form-manage-inquiry"
                      style="display:flex; flex-direction:column; gap:8px; border-top:1px solid var(--border); padding-top:10px; margin-top:4px;">
                    <input type="hidden" name="form_section" value="public_inquiries">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="id" value="${inq.inquiry_id}">
                    <input type="hidden" name="csrf" value="${inqCsrf}">

                    <div class="form-group" style="margin:0;">
                        <label class="form-label" style="font-size:11.5px;">Workflow Status</label>
                        <select name="status" id="inq-status"
                                style="padding:6px; font-size:12px; background:var(--bg); border:1px solid var(--border); border-radius:4px; color:var(--text); width:100%;">
                            <option value="Pending"      ${inq.status === 'Pending'      ? 'selected' : ''}>Pending</option>
                            <option value="In Progress"  ${inq.status === 'In Progress'  ? 'selected' : ''}>In Progress</option>
                            <option value="Reviewed"     ${inq.status === 'Reviewed'     ? 'selected' : ''}>Reviewed</option>
                            <option value="For Claiming" ${inq.status === 'For Claiming' ? 'selected' : ''}>For Claiming</option>
                            <option value="Completed"    ${inq.status === 'Completed'    ? 'selected' : ''}>Completed</option>
                            <option value="Cancelled"    ${inq.status === 'Cancelled'    ? 'selected' : ''}>Cancelled</option>
                        </select>
                    </div>

                    <div class="form-group" style="margin:0;">
                        <label class="form-label" style="font-size:11.5px;">Office Response Note</label>
                        <textarea name="admin_note" id="inq-note"
                                  style="padding:6px; font-size:12px; background:var(--bg); border:1px solid var(--border); border-radius:4px; color:var(--text); width:100%; height:60px; resize:vertical;">${inq.admin_note || ''}</textarea>
                    </div>

                    <div style="display:flex; gap:6px;">
                        <button type="submit" class="btn btn-primary btn-sm" style="flex:1.5; justify-content:center; padding:8px;">
                            <i class="fa-solid fa-floppy-disk"></i> Save Update
                        </button>
                        <button type="button" id="btn-inq-resolved" class="btn btn-success btn-sm"
                                onclick="submitInquiryResolved()"
                                style="display:${inq.status === 'For Claiming' ? 'flex' : 'none'}; padding:8px;">
                            <i class="fa-solid fa-check-double"></i> Complete
                        </button>
                    </div>
                </form>

                <form method="POST" action="dashboard.php" onsubmit="return confirm('Archive this record?')" style="margin:0;">
                    <input type="hidden" name="form_section" value="public_inquiries">
                    <input type="hidden" name="action" value="archive">
                    <input type="hidden" name="id" value="${inq.inquiry_id}">
                    <input type="hidden" name="csrf" value="${inqCsrf}">
                    <button type="submit" class="btn btn-ghost btn-sm"
                            style="width:100%; justify-content:center; padding:6px; border-color:var(--border); color:var(--text2); font-size:11px;">
                        <i class="fa-solid fa-box-archive"></i> Archive Request
                    </button>
                </form>`;
        }
    } else {
        actionAreaHtml = `
            <div style="border-top:1px solid var(--border); padding-top:10px; margin-top:4px;">
                <div class="inbox-detail-label" style="margin-bottom:4px;">Office Response</div>
                <div style="background:var(--bg); border:1px solid var(--border); padding:8px; border-radius:6px; font-size:12px; color:var(--text); line-height:1.4;">
                    ${escInqHtml(inq.admin_note || 'No administrative response yet. Your request is being reviewed.')}
                </div>
            </div>`;
    }

    const contactInfo = `
        ${inq.email ? `<div><i class="fa-regular fa-envelope" style="color:var(--accent); margin-right:4px;"></i>${escInqHtml(inq.email)}</div>` : ''}
        ${inq.phone ? `<div style="margin-top:3px;"><i class="fa-solid fa-phone" style="color:var(--accent); margin-right:4px;"></i>${escInqHtml(inq.phone)}</div>` : ''}
    `;

    inspector.innerHTML = `
        <div class="inbox-detail-card">
            <div style="display:flex; justify-content:space-between; align-items:start; gap:10px; border-bottom:1px solid var(--border); padding-bottom:8px;">
                <div style="font-weight:700; font-size:0.95rem; color:var(--text);">Request #${escInqHtml(String(inq.inquiry_id).padStart(4,'0'))}</div>
                <span class="badge ${statusBadgeClass}" style="font-size:11.5px; padding:2px 7px;">${escInqHtml(inq.status || 'Pending')}</span>
            </div>

            <div class="inbox-detail-item">
                <div class="inbox-detail-label">Parishioner</div>
                <div class="inbox-detail-value" style="font-weight:700; color:var(--accent2);">${escInqHtml(inq.full_name)}</div>
                <div style="font-size:11px; color:var(--text2); margin-top:2px;">${contactInfo}</div>
            </div>

            <div class="inbox-detail-item">
                <div class="inbox-detail-label">Request Type</div>
                <div class="inbox-detail-value">
                    <strong>${escInqHtml(inq.service_category || 'Other')}</strong>
                    <span class="badge" style="font-size:11.5px; background:var(--bg3); padding:2px 6px; margin-left:4px;">${escInqHtml(inq.request_type)}</span>
                </div>
            </div>

            <div class="inbox-detail-item">
                <div class="inbox-detail-label">Target / Event Date</div>
                <div class="inbox-detail-value" style="font-weight:600;">
                    <i class="fa-regular fa-calendar" style="color:var(--accent); margin-right:4px;"></i>
                    ${escInqHtml(formatDate(inq.target_date))}
                </div>
            </div>

            <div class="inbox-detail-item">
                <div class="inbox-detail-label">Chapel Scope</div>
                <div class="inbox-detail-value">${escInqHtml(inq.chapel_name || 'General Cathedral')}</div>
            </div>

            <div class="inbox-detail-item" style="border-bottom:none; padding-bottom:0;">
                <div class="inbox-detail-label">Message Details</div>
                <div class="inbox-detail-value" style="font-size:0.8rem; color:var(--text2); white-space:pre-wrap; line-height:1.5;">${escInqHtml(inq.details || 'No message provided.')}</div>
            </div>

            ${merchBlockHtml}
        </div>

        ${contactButtons}
        ${actionAreaHtml}
    `;

    const smsInput   = document.getElementById('inq-sms-id');
    const emailInput = document.getElementById('inq-email-id');
    if (smsInput)   smsInput.value   = inq.inquiry_id;
    if (emailInput) emailInput.value = inq.inquiry_id;
}
window.showWorkspaceInquiryDetails = showWorkspaceInquiryDetails;

function extractMerchMeta(inq) {
    const details = String((inq && inq.details) || '');
    const isMerch = !!(inq && (inq.request_type === 'Merchandise' || details.includes('Reservation for') || details.includes('Member Reservation:') || parseInt(inq.item_id || '0', 10) > 0));
    let itemId = inq ? (parseInt(inq.item_id || '0', 10) || 0) : 0;
    if (itemId <= 0) {
        const idMatch = details.match(/\(Item ID: (\d+)\)/);
        if (idMatch) itemId = parseInt(idMatch[1], 10) || 0;
    }
    let itemQty = inq ? (parseInt(inq.quantity || '0', 10) || 0) : 0;
    let itemName = (inq && inq.service_category && inq.service_category !== 'Reservation') ? String(inq.service_category) : 'Merchandise Item';
    const lineMatch = details.match(/(?:Reservation for|Member Reservation:)\s*(.+?)\s+x\s+(\d+)\./);
    if (lineMatch) {
        itemName = lineMatch[1].trim();
        if (itemQty <= 0) itemQty = parseInt(lineMatch[2], 10) || 1;
    }
    if (itemQty <= 0) itemQty = 1;
    return { isMerch, itemId, itemQty, itemName };
}

function transferInquiryToMerch() {
    if (!currentInquiry) return;
    const merch = extractMerchMeta(currentInquiry);
    const prefill = {
        itemId: merch.itemId > 0 ? String(merch.itemId) : '',
        quantity: String(merch.itemQty),
        buyerName: currentInquiry.full_name,
        inquiryId: currentInquiry.inquiry_id || null,
        title: merch.itemName || currentInquiry.service_category || currentInquiry.request_type || 'Merchandise'
    };
    try { sessionStorage.setItem('merchSalePrefill', JSON.stringify(prefill)); } catch(e) {}
    showSection('merchandise');
    if (typeof applyMerchSalePrefill === 'function') setTimeout(applyMerchSalePrefill, 500);
}

function submitMerchFulfill() {
    const form = document.getElementById('form-manage-inquiry');
    if (!form) return;
    if (confirm('Fulfill this merchandise reservation, deduct inventory, archive the inquiry, and notify the buyer?')) {
        const actionInput = form.querySelector('input[name="action"]');
        if (actionInput) actionInput.value = 'fulfill_merch_reservation';
        if (typeof form.requestSubmit === 'function') form.requestSubmit(); else form.submit();
    }
}


function submitInquiryQuickContact(method) {
    if (!currentInquiry) return;
    const isSms    = (method === 'sms');
    const target   = isSms ? (currentInquiry.phone || '') : (currentInquiry.email || '');
    const formId   = isSms ? 'form-quick-sms' : 'form-quick-email';
    const inputId  = isSms ? 'inq-sms-id'     : 'inq-email-id';
    const msgId    = isSms ? 'inq-sms-msg'    : 'inq-email-msg';
    const form     = document.getElementById(formId);
    const hiddenId = document.getElementById(inputId);
    const hiddenMsg = document.getElementById(msgId);
    if (!form || !hiddenId || !target) return;

    const noteEl  = document.getElementById('inq-note');
    const custom  = noteEl ? noteEl.value.trim() : '';
    const preview = custom !== ''
        ? `Send your Office Response Note via ${method.toUpperCase()} to ${target}?\n\n"${custom}"`
        : `Office Response Note is blank. Send a standard confirmation receipt via ${method.toUpperCase()} to ${target}?`;
    if (!confirm(preview)) return;

    hiddenId.value = currentInquiry.inquiry_id;
    if (hiddenMsg) hiddenMsg.value = custom;
    if (typeof form.requestSubmit === 'function') form.requestSubmit(); else form.submit();
}

function submitInquiryResolved() {
    if (!confirm('Mark this request as Claimed/Completed? It will be archived.')) return;
    const form         = document.getElementById('form-manage-inquiry');
    const statusSelect = document.getElementById('inq-status');
    if (statusSelect) {
        if (!Array.from(statusSelect.options).some(o => o.value === 'Completed')) {
            const opt = document.createElement('option');
            opt.value = 'Completed'; opt.text = 'Completed';
            statusSelect.add(opt);
        }
        statusSelect.value = 'Completed';
    }
    if (form) {
        if (typeof form.requestSubmit === 'function') form.requestSubmit(); else form.submit();
    }
}

function filterInquiries() {
    const filter = document.getElementById('inq-search').value.toLowerCase();
    const rows   = document.querySelectorAll('.inq-row');
    rows.forEach(tr => {
        const cell = tr.cells[1];
        if (cell) {
            tr.style.display = cell.textContent.toLowerCase().includes(filter) ? '' : 'none';
        }
    });
}

// Explicit window bindings for SPA/AJAX compatibility
window.showWorkspaceInquiryDetails = showWorkspaceInquiryDetails;
window.transferInquiryToMerch = transferInquiryToMerch;
window.submitMerchFulfill = submitMerchFulfill;
window.submitInquiryQuickContact = submitInquiryQuickContact;
window.submitInquiryResolved = submitInquiryResolved;
window.filterInquiries = filterInquiries;

// Select deep-linked request (from notification inbox or URL ?inq_id=) or fall back to first row
window.initWorkspaceInquirySelection = function() {
    var serverSelectedId = <?php echo (int)($selected_inquiry_id ?? 0); ?>;
    var serverSelectedData = <?php echo json_encode($selected_inquiry ?? null, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP); ?>;
    var urlSelectedId = parseInt(new URLSearchParams(window.location.search).get('inq_id') || '0', 10) || 0;
    var pendingId = parseInt(window.__pendingInboxSelectId || '0', 10) || 0;
    var targetId = pendingId || urlSelectedId || serverSelectedId || 0;

    if (pendingId) {
        window.__pendingInboxSelectId = null;
    }

    if (targetId > 0) {
        var targetRow = document.querySelector('.inq-row[data-inquiry-id="' + targetId + '"]');
        if (targetRow && typeof showWorkspaceInquiryDetails === 'function') {
            var searchInput = document.getElementById('inq-search');
            if (targetRow.style.display === 'none') {
                if (searchInput) searchInput.value = '';
                document.querySelectorAll('.inq-row').forEach(function(r) { r.style.display = ''; });
            }
            showWorkspaceInquiryDetails(targetRow);
            targetRow.scrollIntoView({ behavior: 'smooth', block: 'center' });
            return true;
        }
        if (serverSelectedData && Number(serverSelectedData.inquiry_id) === targetId && typeof showWorkspaceInquiryDetails === 'function') {
            showWorkspaceInquiryDetails(serverSelectedData, null);
            return true;
        }
    }

    var firstRow = document.querySelector('.inq-row');
    if (firstRow && typeof showWorkspaceInquiryDetails === 'function') {
        showWorkspaceInquiryDetails(firstRow);
        return true;
    }
    return false;
};

(function() {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() { window.initWorkspaceInquirySelection(); });
    } else {
        setTimeout(function() { window.initWorkspaceInquirySelection(); }, 80);
    }
})();
</script>