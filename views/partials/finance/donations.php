<?php
// ─── Donations Stats & Configuration ──────────────────────────────────────────
$initialDonationTab = $_GET['tab'] ?? 'ledger';
if (!in_array($initialDonationTab, ['ledger', 'log', 'voided'], true)) {
    $initialDonationTab = 'ledger';
}
$activeDonationsCount = count($donations ?? []);
$voidedCount = count($voided_donations ?? []);
?>
<!-- ═══════════════════════════════════════════════════════════════ -->
<!-- DONATIONS & STEWARDSHIP — Segmented Operational Hub             -->
<!-- ═══════════════════════════════════════════════════════════════ -->
<link rel="stylesheet" href="assets/css/workspace.css?v=2.5">

<style>
/* Local style overrides for Donations Hub */
.don-tab-btn {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 0 12px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 500;
    color: var(--text2);
    background: transparent;
    border: 1px solid transparent;
    cursor: pointer;
    text-decoration: none;
    transition: all 0.18s ease;
    user-select: none;
    line-height: 1.2;
    height: 28px;
    min-height: 28px;
    box-sizing: border-box;
    white-space: nowrap;
}
.don-tab-btn:hover {
    color: var(--text);
    background: var(--hover-bg, rgba(0,0,0,0.04));
}
.don-tab-btn.active {
    background: #111111 !important;
    color: #FFFFFF !important;
    border-color: #111111 !important;
    font-weight: 700;
    box-shadow: 0 1px 3px rgba(0,0,0,0.14);
}
.don-tab-btn.active .tab-badge {
    background: rgba(255,255,255,0.2) !important;
    color: #FFFFFF !important;
}
[data-theme="dark"] .don-tabs-group {
    background: #18181b !important;
    border-color: rgba(255, 255, 255, 0.12) !important;
}
[data-theme="dark"] .don-tab-btn {
    color: #cbd5e1;
}
[data-theme="dark"] .don-tab-btn:hover {
    background: rgba(255,255,255,0.08);
    color: #ffffff;
}
[data-theme="dark"] .don-tab-btn.active {
    background: #FFFFFF !important;
    color: #111111 !important;
    border-color: #FFFFFF !important;
    font-weight: 700;
    box-shadow: 0 2px 8px rgba(0,0,0,0.3);
}
[data-theme="dark"] .don-tab-btn.active .tab-badge {
    background: rgba(0,0,0,0.18) !important;
    color: #111111 !important;
}
.tab-badge {
    font-size: 9.5px;
    padding: 1px 5px;
    border-radius: 10px;
    font-weight: 700;
    background: #F4F4F5;
    color: #71717A;
    border: 1px solid #E4E4E7;
}

/* Preset Amount Buttons */
.preset-amt-btn {
    padding: 3px 9px;
    font-size: 11px;
    font-weight: 600;
    border-radius: 6px;
    border: 1px solid var(--border);
    background: #FFFFFF;
    color: var(--text);
    cursor: pointer;
    transition: all 0.15s ease;
}
.preset-amt-btn:hover {
    border-color: #111111;
    background: #F4F4F5;
}
</style>

<div class="workspace workspace-fluid" id="sec-donations" style="display: flex; flex-direction: column; gap: 14px; width: 100%; max-width: 1680px; margin: 0 auto; box-sizing: border-box; padding: 14px clamp(16px, 1.8vw, 24px) 24px;">

    <!-- ── 1. UNIFIED COMMAND HEADER: TITLE, BADGES, TABS, AND ACTIONS ── -->
    <div class="page-header-bar" style="display: flex; justify-content: space-between; align-items: center; padding-bottom: 8px; border-bottom: 1px solid var(--border); flex-wrap: wrap; gap: 10px; margin-bottom: 6px;">
        <div style="display: flex; align-items: center; gap: 14px; flex-wrap: wrap;">
            <div class="page-header-icon" style="width: 38px; height: 38px; border-radius: 9px; background: rgba(201,168,76,0.14); color: var(--accent); display: flex; align-items: center; justify-content: center; font-size: 17px; flex-shrink: 0;">
                <i class="fa-solid fa-vault"></i>
            </div>
            <div>
                <h1 class="page-title" style="font-family: var(--font-family, 'Inter', sans-serif); font-size: var(--font-page-title, 20px); font-weight: 700; color: var(--text); margin: 0; line-height: 1.2;">
                    Donations
                </h1>
                <p class="page-subtitle" style="font-size: var(--font-page-subtitle, 14px); color: var(--text2); margin: 3px 0 0 0;">
                    Parish collections, tithes, mass intentions, and financial stewardship records
                </p>
            </div>
        </div>

        <!-- Right Side: Stat Badges (always top-right) -->
        <div style="display: flex; align-items: center; gap: 6px;">
            <span class="badge" style="background: var(--bg3, #F4F4F5); border: 1px solid var(--border); color: var(--text2); font-size: 11.5px; font-weight: 600; padding: 3px 11px; border-radius: 999px;">
                Total: ₱<?php echo number_format($financial_summary['grand_total'] ?? 0, 2); ?>
            </span>
        </div>
    </div>

    <!-- ── 2. SUB-ROW: TABS LEFT + ACTIONS RIGHT ── -->
    <div class="don-tabs-bar" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 6px; margin-bottom: 4px;">
        <div style="display: flex; align-items: center; gap: 6px; flex-wrap: wrap;">
            <div class="don-tabs-group" style="display: inline-flex; align-items: center; gap: 3px; background: var(--bg3, #F4F4F5); padding: 2px; border-radius: 999px; border: 1px solid var(--border); flex-wrap: wrap;">
                <button type="button" class="don-tab-btn <?php echo ($initialDonationTab === 'ledger') ? 'active' : ''; ?>" data-tab="ledger" onclick="switchDonationTab('ledger')">
                    <i class="fa-solid fa-list-check"></i> Ledger
                    <span class="tab-badge"><?php echo $activeDonationsCount; ?></span>
                </button>
                <button type="button" class="don-tab-btn <?php echo ($initialDonationTab === 'log') ? 'active' : ''; ?>" data-tab="log" onclick="switchDonationTab('log')">
                    <i class="fa-solid fa-plus-circle"></i> New Offering
                </button>
                <?php if (in_array($role, ['Admin', 'Cathedral Admin'], true)): ?>
                <button type="button" class="don-tab-btn <?php echo ($initialDonationTab === 'voided') ? 'active' : ''; ?>" data-tab="voided" onclick="switchDonationTab('voided')">
                    <i class="fa-solid fa-ban"></i> Voided
                    <?php if ($voidedCount > 0): ?>
                        <span class="tab-badge" style="background: rgba(239,68,68,0.14); color: #dc2626; border-color: rgba(239,68,68,0.25);"><?php echo $voidedCount; ?></span>
                    <?php else: ?>
                        <span class="tab-badge">0</span>
                    <?php endif; ?>
                </button>
                <?php endif; ?>
            </div>
        </div>

        <div style="display: flex; align-items: center; gap: 6px;">
            <?php if ($can_finance_reports): ?>
            <a href="dashboard.php?action=export_donations" class="btn btn-ghost btn-sm" 
               style="font-size: 11.5px; height: 28px; font-weight: 600; display: inline-flex; align-items: center; gap: 5px; background: var(--surface, #FFFFFF); border: 1px solid var(--border); color: var(--text); text-decoration: none; border-radius: 999px; padding: 0 13px;">
                <i class="fa-solid fa-file-export"></i> Export CSV
            </a>
            <?php endif; ?>
            <button type="button" class="btn btn-primary btn-sm" onclick="switchDonationTab('log')" 
                    style="font-size: 11.5px; height: 28px; font-weight: 600; display: inline-flex; align-items: center; gap: 5px; background: #111111; color: #FFFFFF; border: 1px solid #111111; border-radius: 999px; padding: 0 13px; cursor: pointer; box-shadow: 0 1px 3px rgba(0,0,0,0.12);">
                <i class="fa-solid fa-plus"></i> Record Offering
            </button>
        </div>
    </div>

    <!-- ── Newly Logged Donation Flash Banner ── -->
    <?php if (!empty($new_donation_id)): ?>
    <div style="background: rgba(16,185,129,0.08); border: 1px solid rgba(16,185,129,0.3); border-radius: 8px; padding: 8px 14px; display: flex; align-items: center; justify-content: space-between; gap: 10px; margin-bottom: 8px;">
        <div style="display: flex; align-items: center; gap: 8px; font-size: 12.5px; color: #065f46; font-weight: 600;">
            <i class="fa-solid fa-circle-check" style="font-size: 16px;"></i>
            <span>Donation recorded successfully! Receipt #DON-<?php echo (int)$new_donation_id; ?> generated.</span>
        </div>
        <a href="views/pages/donation_receipt.php?id=<?php echo (int)$new_donation_id; ?>" target="_blank" class="btn btn-primary btn-sm" 
           style="font-size: 11px; padding: 4px 10px; display: inline-flex; align-items: center; gap: 4px; text-decoration: none; border-radius: 6px;">
            <i class="fa-solid fa-print"></i> Print Official Receipt
        </a>
    </div>
    <?php endif; ?>

    <!-- ── TAB 1: ACTIVE DONATIONS LEDGER (Full Width, No Horizontal Scroll) ── -->
    <div id="don-view-ledger" class="don-tab-pane" style="display: <?php echo ($initialDonationTab === 'ledger') ? 'flex' : 'none'; ?>; flex-direction: column; flex: 1; min-height: 0;">
        <div class="panel" style="border-radius: 10px; overflow: hidden; background: #FFFFFF; border: 1px solid var(--border); display: flex; flex-direction: column; flex: 1; min-height: 0;">
            
            <!-- Quick Filter Toolbar -->
            <div style="padding: 10px 16px; border-bottom: 1px solid var(--border); background: #FFFFFF; display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 10px; flex-shrink: 0;">
                <form method="GET" style="display: flex; flex-wrap: wrap; gap: 8px; align-items: center; margin: 0;">
                    <input type="hidden" name="section" value="donations">
                    <input type="hidden" name="tab" value="ledger">
                    
                    <div style="display: flex; align-items: center; gap: 4px;">
                        <input type="date" name="don_from" value="<?php echo htmlspecialchars($_GET['don_from'] ?? ''); ?>" 
                               style="height: 32px; padding: 0 8px; font-size: 11.5px; border-radius: 6px; border: 1px solid var(--border); background: #FAFAFA; color: var(--text);" title="From date">
                        <span style="font-size: 11px; color: var(--text2);">to</span>
                        <input type="date" name="don_to" value="<?php echo htmlspecialchars($_GET['don_to'] ?? ''); ?>" 
                               style="height: 32px; padding: 0 8px; font-size: 11.5px; border-radius: 6px; border: 1px solid var(--border); background: #FAFAFA; color: var(--text);" title="To date">
                    </div>

                    <select name="don_timeframe" onchange="this.form.submit()" 
                            style="height: 32px !important; min-width: 140px; font-size: 12px; border-radius: 6px; border: 1px solid var(--border); color: var(--text);">
                        <option value="">All Timeframes</option>
                        <option value="recently_added" <?php echo (($_GET['don_timeframe'] ?? '') === 'recently_added') ? 'selected' : ''; ?>>Recently Added (30 Days)</option>
                        <option value="recently_added_7" <?php echo (($_GET['don_timeframe'] ?? '') === 'recently_added_7') ? 'selected' : ''; ?>>Recently Added (7 Days)</option>
                    </select>

                    <select name="don_method" onchange="this.form.submit()" 
                            style="height: 32px !important; min-width: 140px; font-size: 12px; border-radius: 6px; border: 1px solid var(--border); color: var(--text);">
                        <option value="">All Payment Methods</option>
                        <option value="Cash" <?php echo (($_GET['don_method']??'')==='Cash')?'selected':''; ?>>Cash</option>
                        <option value="GCash" <?php echo (($_GET['don_method']??'')==='GCash')?'selected':''; ?>>GCash</option>
                        <option value="Bank Transfer" <?php echo (($_GET['don_method']??'')==='Bank Transfer')?'selected':''; ?>>Bank Transfer</option>
                        <option value="Maya" <?php echo (($_GET['don_method']??'')==='Maya')?'selected':''; ?>>Maya</option>
                        <option value="Other" <?php echo (($_GET['don_method']??'')==='Other')?'selected':''; ?>>Other</option>
                    </select>

                    <?php if ($can_donations_admin): ?>
                    <select name="don_chapel" onchange="this.form.submit()" 
                            style="height: 32px !important; min-width: 140px; font-size: 12px; border-radius: 6px; border: 1px solid var(--border); color: var(--text);">
                        <option value="">All Branches / Chapels</option>
                        <option value="0" <?php echo (($_GET['don_chapel']??'')==='0')?'selected':''; ?>>General / Cathedral</option>
                        <?php foreach($chapels as $ch): ?>
                        <option value="<?php echo $ch['chapel_id']; ?>" <?php echo (($_GET['don_chapel']??'')==(string)$ch['chapel_id'])?'selected':''; ?>><?php echo htmlspecialchars($ch['chapel_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php endif; ?>

                    <button type="submit" class="btn btn-primary btn-sm" style="height: 32px; padding: 0 12px; font-size: 11.5px; display: inline-flex; align-items: center; gap: 4px; border-radius: 6px;">
                        <i class="fa-solid fa-filter"></i> Apply
                    </button>
                    
                    <?php if (!empty($_GET['don_from']) || !empty($_GET['don_to']) || !empty($_GET['don_method']) || !empty($_GET['don_chapel']) || !empty($_GET['don_timeframe'])): ?>
                    <a href="dashboard.php?section=donations&tab=ledger" class="btn btn-ghost btn-sm" style="height: 32px; padding: 0 8px; font-size: 11px; color: var(--text2); display: inline-flex; align-items: center; gap: 4px; border-radius: 6px; border: 1px solid var(--border);">
                        <i class="fa-solid fa-xmark"></i> Clear Filters
                    </a>
                    <?php endif; ?>
                </form>

                <div style="display: flex; align-items: center; gap: 8px; flex: 1; max-width: 360px; min-width: 240px;">
                    <!-- Universal Sort Toggle Button -->
                    <button type="button" class="btn-sort-toggle" onclick="toggleDonationsSort(this)"
                            title="Sorted: Newest First (Click to sort Oldest first)"
                            style="height: 32px; width: 34px; min-width: 34px; padding: 0; display: inline-flex; align-items: center; justify-content: center; border-radius: 6px; border: 1px solid var(--border); background: var(--bg2, #FAFAFA); color: var(--text); cursor: pointer; transition: all 0.15s ease; flex-shrink: 0;" aria-label="Toggle sort order">
                        <i class="fa-solid fa-arrow-down-wide-short" style="font-size: 13px;"></i>
                    </button>

                    <!-- Client-side Quick Search with Clear Button -->
                    <div style="position: relative; flex: 1;">
                        <i class="fa-solid fa-magnifying-glass" style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: var(--text2, #71717A); font-size: 11px; pointer-events: none; z-index: 2;"></i>
                        <input type="text" id="donations-search-input" placeholder="Search donor, ref ID, purpose..." oninput="filterDonationsLedger()" 
                               style="width: 100%; height: 32px; padding-left: 32px !important; padding-right: 24px !important; font-size: 12px; border: 1px solid var(--border); border-radius: 6px; box-sizing: border-box; outline: none; background: var(--bg2, #FFFFFF); color: var(--text);">
                        <span id="donations-clear-search" onclick="clearDonationsSearch()" title="Clear search" style="display: none; position: absolute; right: 8px; top: 50%; transform: translateY(-50%); cursor: pointer; color: var(--text2); font-size: 14px; line-height: 1;">&times;</span>
                    </div>
                </div>
            </div>

            <!-- Table Body -->
            <div style="padding: 0; overflow-y: auto; flex: 1; min-height: 0; width: 100%;">
                <table class="data-table" id="donations-data-table" style="font-size: 12.5px; width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr>
                            <th style="padding: 8px 14px; width: 110px; text-align: left;">Date Received</th>
                            <th style="padding: 8px 14px; text-align: left;">Donor Name</th>
                            <th style="padding: 8px 14px; width: 140px; text-align: left;">Reference ID</th>
                            <th style="padding: 8px 14px; text-align: left;">Purpose / Intention</th>
                            <th style="padding: 8px 14px; width: 100px; text-align: center;">Method</th>
                            <th style="padding: 8px 14px; width: 160px; text-align: left;">Jurisdiction</th>
                            <th style="padding: 8px 14px; width: 130px; text-align: right;">Amount</th>
                            <th style="padding: 8px 14px; width: 110px; text-align: center;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($donations)): ?>
                            <tr><td colspan="8" style="padding: 40px; text-align: center; color: var(--text2); font-size: 13px;">No donations recorded yet. Click <strong>Log Donation</strong> above to register a donation.</td></tr>
                        <?php else: ?>
                            <?php foreach ($donations as $don): ?>
                            <tr class="donation-row" 
                                data-donor="<?php echo strtolower(htmlspecialchars($don['donor_name'] ?: 'anonymous')); ?>" 
                                data-ref="<?php echo strtolower(htmlspecialchars($don['reference_id'] . ' ' . ($don['donor_ref_id'] ?? ''))); ?>" 
                                data-purpose="<?php echo strtolower(htmlspecialchars($don['purpose'] ?: '')); ?>"
                                data-date="<?php echo strtotime($don['date_received']); ?>"
                                data-amount="<?php echo (float)($don['amount'] ?? 0); ?>"
                                data-donation="<?php echo htmlspecialchars(json_encode($don, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>"
                                style="cursor: pointer; transition: background 0.15s;" 
                                onclick="viewDonationDetails(this)">
                                
                                <td style="padding: 8px 14px; white-space: nowrap; font-size: 12px; font-weight: 500; color: var(--text);">
                                    <?php echo date('M d, Y', strtotime($don['date_received'])); ?>
                                </td>
                                <td style="padding: 8px 14px;">
                                    <div style="font-weight: 700; font-size: 13px; color: var(--text); line-height: 1.3;">
                                        <?php echo htmlspecialchars($don['donor_name'] ?: 'Anonymous Donor'); ?>
                                    </div>
                                </td>
                                <td style="padding: 8px 14px;">
                                    <span style="font-size: 11px; font-family: var(--font-mono, monospace); color: var(--text2); font-weight: 600;">
                                        <?php echo htmlspecialchars($don['reference_id'] ?: '—'); ?>
                                    </span>
                                    <?php if (!empty($don['donor_ref_id'])): ?>
                                        <div style="color: var(--accent); font-size: 10px; font-weight: 600;"><?php echo htmlspecialchars($don['donor_ref_id']); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 8px 14px; color: var(--text2); font-size: 12px; line-height: 1.3;">
                                    <?php echo htmlspecialchars($don['purpose'] ?: 'General Offering'); ?>
                                </td>
                                <td style="padding: 8px 14px; text-align: center;">
                                    <span class="badge" style="background: #F4F4F5; color: #111111; border: 1px solid var(--border); font-size: 11px; padding: 2px 8px; border-radius: 6px; font-weight: 600;">
                                        <?php echo htmlspecialchars($don['payment_method'] ?? 'Cash'); ?>
                                    </span>
                                </td>
                                <td style="padding: 8px 14px; font-size: 12px; color: var(--text);">
                                    <i class="fa-solid fa-place-of-worship" style="color: var(--accent); font-size: 11px; margin-right: 4px;"></i>
                                    <?php echo htmlspecialchars($don['chapel_name'] ?? 'Cathedral'); ?>
                                </td>
                                <td style="padding: 8px 14px; font-weight: 800; color: #111111; font-size: 13.5px; text-align: right; white-space: nowrap;">
                                    ₱<?php echo number_format($don['amount'], 2); ?>
                                </td>
                                <td style="padding: 8px 14px; text-align: center;" onclick="event.stopPropagation()">
                                    <div class="btn-group" style="justify-content: center; gap: 4px;">
                                        <a href="views/pages/donation_receipt.php?id=<?php echo $don['donation_id']; ?>" target="_blank"
                                           class="btn-sm btn-ghost" style="color: var(--accent); border: 1px solid var(--border); border-radius: 6px; padding: 4px 8px; text-decoration: none; font-size: 11px; display: inline-flex; align-items: center; gap: 4px;" title="Print Receipt">
                                            <i class="fa-solid fa-print"></i> Receipt
                                        </a>
                                        <?php if (in_array($role, ['Admin', 'Cathedral Admin'], true)): ?>
                                        <button type="button" class="btn-sm btn-ghost" style="color: #ef4444; border: 1px solid rgba(239,68,68,0.25); border-radius: 6px; width: 28px; height: 28px; padding: 0;"
                                                title="Void this financial record"
                                                data-id="<?php echo (int)$don['donation_id']; ?>"
                                                data-ref="<?php echo htmlspecialchars($don['reference_id'], ENT_QUOTES, 'UTF-8'); ?>"
                                                data-donor="<?php echo htmlspecialchars($don['donor_name'] ?: 'Anonymous', ENT_QUOTES, 'UTF-8'); ?>"
                                                onclick="openVoidDonationModal(this)">
                                            <i class="fa-solid fa-ban"></i>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            
                            <!-- Current Page Summary Row -->
                            <tr style="border-top: 2px solid var(--border); background: #FAFAFA;">
                                <td colspan="6" style="text-align: right; font-weight: 700; font-size: 12px; color: var(--text2); padding: 10px 14px;">
                                    Page Total:
                                </td>
                                <td style="text-align: right; font-weight: 800; font-size: 14px; color: #111111; padding: 10px 14px;">
                                    ₱<?php echo number_format(array_sum(array_column($donations, 'amount')), 2); ?>
                                </td>
                                <td></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if (($don_pages ?? 0) > 1): ?>
            <div class="panel-footer" style="display: flex; justify-content: space-between; align-items: center; padding: 8px 16px; border-top: 1px solid var(--border); background: #FFFFFF;">
                <div>
                    <?php
                    $cp = max(1, (int) ($don_current ?? 1));
                    $tp = max(1, (int) ($don_pages ?? 1));
                    $base = '?' . http_build_query(array_filter([
                        'section' => 'donations',
                        'tab' => 'ledger',
                        'don_from' => $_GET['don_from'] ?? '',
                        'don_to' => $_GET['don_to'] ?? '',
                        'don_method' => $_GET['don_method'] ?? '',
                        'don_chapel' => $_GET['don_chapel'] ?? '',
                    ], static fn($v) => $v !== ''));
                    ?>
                    <?php if ($cp > 1): ?>
                        <a href="<?php echo $base; ?>&page=<?php echo $cp - 1; ?>" class="btn-sm btn-ghost" style="padding: 4px 10px; font-size: 11px; border: 1px solid var(--border); border-radius: 6px; text-decoration: none;">
                            <i class="fa-solid fa-arrow-left"></i> Prev
                        </a>
                    <?php endif; ?>
                </div>
                <span style="font-size: 11.5px; color: var(--text2);">Page <?php echo $cp; ?> of <?php echo $tp; ?></span>
                <div>
                    <?php if ($cp < $tp): ?>
                        <a href="<?php echo $base; ?>&page=<?php echo $cp + 1; ?>" class="btn-sm btn-ghost" style="padding: 4px 10px; font-size: 11px; border: 1px solid var(--border); border-radius: 6px; text-decoration: none;">
                            Next <i class="fa-solid fa-arrow-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ── TAB 2: LOG NEW DONATION (High-Focus Dedicated Form) ── -->
    <div id="don-view-log" class="don-tab-pane" style="display: <?php echo ($initialDonationTab === 'log') ? 'flex' : 'none'; ?>; justify-content: center; align-items: flex-start; flex: 1; min-height: 0; padding: 2px 0;">
        <div class="panel" style="width: 100%; max-width: 650px; border-radius: 10px; overflow: hidden; background: #FFFFFF; border: 1px solid var(--border); display: flex; flex-direction: column; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
            
            <div class="panel-header" style="padding: 9px 16px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; background: #FFFFFF; flex-shrink: 0;">
                <div style="font-size: 13px; font-weight: 700; color: var(--text); display: flex; align-items: center; gap: 8px;">
                    <i class="fa-solid fa-plus-circle" style="color: var(--accent);"></i>
                    <span>Record Donation</span>
                </div>
                <button type="button" class="btn btn-ghost btn-sm" onclick="switchDonationTab('ledger')" style="font-size: 11px; padding: 2px 8px; border: 1px solid var(--border); border-radius: 6px;">
                    <i class="fa-solid fa-xmark"></i> Back to Ledger
                </button>
            </div>

            <div class="panel-body" style="padding: 10px 16px; overflow-y: auto;">
                <form method="POST" style="display: flex; flex-direction: column; gap: 8px; margin: 0;">
                    <input type="hidden" name="form_section" value="donations">
                    <input type="hidden" name="action" value="log_donation">
                    <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">

                    <!-- Donor Name with Quick Anonymous Button -->
                    <div>
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2px;">
                            <label style="font-size: 10.5px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; color: var(--text2);">
                                Donor Name
                            </label>
                            <button type="button" class="btn-sm btn-ghost" onclick="setAnonymousDonor()" 
                                    style="font-size: 10px; padding: 1px 6px; color: var(--accent); border: 1px solid var(--border); border-radius: 4px; line-height: 1.2;">
                                Set as Anonymous
                            </button>
                        </div>
                        <input type="text" name="donor_name" id="log-donor-name" placeholder="e.g. Juan dela Cruz (or leave blank for anonymous)" 
                               style="width: 100%; font-size: 11.5px; padding: 5px 10px; border: 1px solid var(--border); border-radius: 6px; box-sizing: border-box;">
                    </div>

                    <!-- Amount with Preset Buttons -->
                    <div>
                        <label style="font-size: 10.5px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; color: var(--text2); display: block; margin-bottom: 2px;">
                            Contribution Amount (₱) <span style="color:#ef4444;">*</span>
                        </label>
                        <div style="position: relative; margin-bottom: 4px;">
                            <span style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); font-size: 14px; font-weight: 700; color: var(--text2);">₱</span>
                            <input type="number" name="amount" id="log-donation-amount" min="0.01" step="0.01" required placeholder="0.00" 
                                    style="width: 100%; font-size: 14.5px; font-weight: 800; padding: 5px 10px 5px 24px; border: 1px solid var(--border); border-radius: 6px; box-sizing: border-box;">
                        </div>
                        
                        <!-- Preset Amount Pills -->
                        <div style="display: flex; gap: 5px; flex-wrap: wrap; align-items: center;">
                            <span style="font-size: 10px; color: var(--text2); margin-right: 2px;">Quick Select:</span>
                            <button type="button" class="preset-amt-btn" onclick="setPresetAmount(100)" style="padding: 3px 9px; font-size: 11px;">₱100</button>
                            <button type="button" class="preset-amt-btn" onclick="setPresetAmount(200)" style="padding: 3px 9px; font-size: 11px;">₱200</button>
                            <button type="button" class="preset-amt-btn" onclick="setPresetAmount(500)" style="padding: 3px 9px; font-size: 11px;">₱500</button>
                            <button type="button" class="preset-amt-btn" onclick="setPresetAmount(1000)" style="padding: 3px 9px; font-size: 11px;">₱1,000</button>
                            <button type="button" class="preset-amt-btn" onclick="setPresetAmount(5000)" style="padding: 3px 9px; font-size: 11px;">₱5,000</button>
                        </div>
                    </div>

                    <!-- Purpose / Intention -->
                    <div>
                        <label style="font-size: 10.5px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; color: var(--text2); display: block; margin-bottom: 2px;">
                            Purpose / Special Intention
                        </label>
                        <input type="text" name="purpose" id="log-donation-purpose" placeholder="e.g. Mass Offering, Church Maintenance, Tithe, Fiesta" 
                               style="width: 100%; font-size: 11.5px; padding: 5px 10px; border: 1px solid var(--border); border-radius: 6px; box-sizing: border-box;">
                    </div>

                    <!-- Two Columns: Payment Method & Chapel Assignment -->
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px;">
                        <div>
                            <label style="font-size: 10.5px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; color: var(--text2); display: block; margin-bottom: 2px;">
                                Payment Method
                            </label>
                            <select name="payment_method" style="width: 100%; font-size: 11.5px; padding: 5px 10px; border: 1px solid var(--border); border-radius: 6px; background: #FFFFFF;">
                                <option value="Cash">Cash</option>
                                <option value="GCash">GCash</option>
                                <option value="Bank Transfer">Bank Transfer</option>
                                <option value="Maya">Maya</option>
                                <option value="Check">Check</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>

                        <div>
                            <label style="font-size: 10.5px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; color: var(--text2); display: block; margin-bottom: 2px;">
                                Parish / Chapel Branch
                            </label>
                            <select name="chapel_id" style="width: 100%; font-size: 11.5px; padding: 5px 10px; border: 1px solid var(--border); border-radius: 6px; background: #FFFFFF;">
                                <option value="0">— General / Cathedral (Main) —</option>
                                <?php foreach ($chapels as $ch): ?>
                                <option value="<?php echo $ch['chapel_id']; ?>"><?php echo htmlspecialchars($ch['chapel_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Two Columns: Reference ID & Date Received -->
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px;">
                        <div>
                            <label style="font-size: 10.5px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; color: var(--text2); display: block; margin-bottom: 2px;">
                                Reference / Ref ID <span style="font-weight: 400; text-transform: none; color: var(--text2);">(Optional)</span>
                            </label>
                            <input type="text" name="donor_ref_id" placeholder="e.g. GCash Ref #12345678" maxlength="100" 
                                   style="width: 100%; font-size: 11.5px; padding: 5px 10px; border: 1px solid var(--border); border-radius: 6px; box-sizing: border-box;">
                        </div>

                        <div>
                            <label style="font-size: 10.5px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; color: var(--text2); display: block; margin-bottom: 2px;">
                                Date Received <span style="color:#ef4444;">*</span>
                            </label>
                            <input type="date" name="date_received" max="2099-12-31" value="<?php echo date('Y-m-d'); ?>" required 
                                   style="width: 100%; font-size: 11.5px; padding: 5px 10px; border: 1px solid var(--border); border-radius: 6px; box-sizing: border-box;">
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <div style="margin-top: 4px; padding-top: 8px; border-top: 1px solid var(--border);">
                        <button type="submit" class="btn btn-primary" 
                                style="width: 100%; height: 35px; font-size: 12.5px; font-weight: 700; background: #111111; color: #FFFFFF; border: none; border-radius: 6px; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px;">
                            <i class="fa-solid fa-floppy-disk"></i> Record Donation &amp; Issue Receipt
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ── TAB 3: VOIDED RECORDS (Immutable Audit Trail) ── -->
    <?php if (in_array($role, ['Admin', 'Cathedral Admin'], true)): ?>
    <div id="don-view-voided" class="don-tab-pane" style="display: <?php echo ($initialDonationTab === 'voided') ? 'flex' : 'none'; ?>; flex-direction: column; flex: 1; min-height: 0;">
        <div class="panel" style="border-radius: 10px; overflow: hidden; background: #FFFFFF; border: 1px solid rgba(239, 68, 68, 0.25); display: flex; flex-direction: column; flex: 1; min-height: 0;">
            
            <div class="panel-header" style="background: rgba(239, 68, 68, 0.04); padding: 10px 16px; border-bottom: 1px solid rgba(239, 68, 68, 0.2); display: flex; justify-content: space-between; align-items: center; flex-shrink: 0;">
                <div style="color: #ef4444; font-size: 13px; font-weight: 700; display: flex; align-items: center; gap: 8px;">
                    <i class="fa-solid fa-ban"></i>
                    <span>Voided Records</span>
                </div>
                <span class="badge" style="background: rgba(239, 68, 68, 0.15); color: #ef4444; font-weight: 700; font-size: 11px; padding: 3px 8px; border-radius: 6px;">
                    <?php echo $voidedCount; ?> Voided Transactions
                </span>
            </div>

            <!-- Audit Notice Strip -->
            <div style="padding: 7px 16px; border-bottom: 1px solid var(--border); background: #FAFAFA; font-size: 11.5px; color: var(--text2); display: flex; align-items: center; gap: 6px; flex-shrink: 0;">
                <i class="fa-solid fa-shield-halved" style="color: #ef4444; font-size: 12px;"></i>
                <span>These records have been permanently voided and excluded from active parish financial totals. Kept for ecclesiastical audit compliance.</span>
            </div>

            <!-- Table Body -->
            <div style="padding: 0; overflow-y: auto; flex: 1; min-height: 0; width: 100%;">
                <?php if (empty($voided_donations)): ?>
                    <div style="padding: 60px 20px; text-align: center; color: var(--text2);">
                        <i class="fa-solid fa-circle-check" style="font-size: 40px; color: #10b981; opacity: 0.7; margin-bottom: 12px; display: block;"></i>
                        <div style="font-weight: 700; color: var(--text); margin-bottom: 4px; font-size: 14px;">No Voided Records Found</div>
                        <div style="font-size: 12px;">The parish financial ledger is clean and fully balanced.</div>
                    </div>
                <?php else: ?>
                    <table class="data-table" style="font-size: 12.5px; width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr>
                                <th style="padding: 8px 14px; width: 130px; text-align: left;">Reference ID</th>
                                <th style="padding: 8px 14px; text-align: left;">Original Donor</th>
                                <th style="padding: 8px 14px; width: 120px; text-align: right;">Voided Amount</th>
                                <th style="padding: 8px 14px; width: 110px; text-align: left;">Original Date</th>
                                <th style="padding: 8px 14px; text-align: left;">Reason for Void</th>
                                <th style="padding: 8px 14px; width: 140px; text-align: left;">Voided By</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($voided_donations as $vd): ?>
                            <tr style="opacity: 0.9;">
                                <td style="padding: 10px 14px; font-size: 11px; font-family: var(--font-mono, monospace); color: var(--text2); font-weight: 600;">
                                    <?php echo htmlspecialchars($vd['reference_id'] ?: '—'); ?>
                                </td>
                                <td style="padding: 10px 14px;">
                                    <s style="color: var(--text2); font-weight: 600; font-size: 12.5px;"><?php echo htmlspecialchars($vd['donor_name'] ?: 'Anonymous'); ?></s>
                                </td>
                                <td style="padding: 10px 14px; color: #ef4444; font-weight: 800; text-align: right; font-size: 13.5px; white-space: nowrap;">
                                    ₱<?php echo number_format($vd['amount'], 2); ?>
                                </td>
                                <td style="padding: 10px 14px; font-size: 12px; color: var(--text2); white-space: nowrap;">
                                    <?php echo date('M d, Y', strtotime($vd['date_received'])); ?>
                                </td>
                                <td style="padding: 10px 14px; font-size: 12px; color: #dc2626; font-style: italic; line-height: 1.3;">
                                    <?php echo htmlspecialchars($vd['void_reason'] ?? '—'); ?>
                                </td>
                                <td style="padding: 10px 14px; font-size: 12px; color: var(--text);">
                                    <i class="fa-solid fa-user-shield" style="font-size: 11px; color: var(--text2); margin-right: 4px;"></i>
                                    <?php echo htmlspecialchars($vd['voided_by_name'] ?? 'Administrator'); ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

</div><!-- /.workspace -->

<script>
// ── TAB SWITCHING ─────────────────────────────────────────────────────────────
function switchDonationTab(tabId) {
    document.querySelectorAll('.don-tab-btn').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.tab === tabId);
    });
    document.querySelectorAll('.don-tab-pane').forEach(pane => {
        pane.style.display = (pane.id === 'don-view-' + tabId) ? 'flex' : 'none';
    });
    
    // Update URL parameter without reload
    const url = new URL(window.location);
    url.searchParams.set('tab', tabId);
    window.history.replaceState({}, '', url);
}

// ── PRESET AMOUNTS & ANONYMOUS HELPER ─────────────────────────────────────────
function setPresetAmount(amt) {
    const amtInput = document.getElementById('log-donation-amount');
    if (amtInput) {
        amtInput.value = parseFloat(amt).toFixed(2);
        amtInput.focus();
    }
}

function setAnonymousDonor() {
    const donorInput = document.getElementById('log-donor-name');
    if (donorInput) {
        donorInput.value = 'Anonymous';
        donorInput.focus();
    }
}

// ── CLIENT-SIDE SEARCH, SORT & CLEAR FOR ACTIVE LEDGER ─────────────────────────
let _donSortAsc = false; // Default: newest first (date descending)

function clearDonationsSearch() {
    const input = document.getElementById('donations-search-input');
    if (input) {
        input.value = '';
        filterDonationsLedger();
        input.focus();
    }
}

function filterDonationsLedger() {
    const input = document.getElementById('donations-search-input');
    const term = (input?.value || '').toLowerCase().trim();
    const clearBtn = document.getElementById('donations-clear-search');
    if (clearBtn) {
        clearBtn.style.display = term ? 'block' : 'none';
    }

    const rows = document.querySelectorAll('#donations-data-table .donation-row');
    rows.forEach(row => {
        const donor = row.dataset.donor || '';
        const ref = row.dataset.ref || '';
        const purpose = row.dataset.purpose || '';
        const matches = !term || donor.includes(term) || ref.includes(term) || purpose.includes(term);
        row.style.display = matches ? '' : 'none';
    });
}

function toggleDonationsSort(btn) {
    _donSortAsc = !_donSortAsc;
    const icon = btn.querySelector('i');
    if (icon) {
        icon.className = _donSortAsc ? 'fa-solid fa-arrow-up-wide-short' : 'fa-solid fa-arrow-down-wide-short';
    }
    btn.title = _donSortAsc ? 'Sorted: Oldest First (Click to sort Newest first)' : 'Sorted: Newest First (Click to sort Oldest first)';

    const tbody = document.querySelector('#donations-data-table tbody');
    if (!tbody) return;
    const rows = Array.from(tbody.querySelectorAll('.donation-row'));
    rows.sort((a, b) => {
        const dateA = parseInt(a.dataset.date || '0', 10);
        const dateB = parseInt(b.dataset.date || '0', 10);
        return _donSortAsc ? (dateA - dateB) : (dateB - dateA);
    });
    rows.forEach(r => tbody.appendChild(r));
}
</script>