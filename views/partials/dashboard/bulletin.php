<!-- ═══════════════════════════════════════════════════════════════ -->
<!-- PARISH BULLETIN (OFFICIAL BULLETED ANNOUNCEMENT SHEET)          -->
<!-- ═══════════════════════════════════════════════════════════════ -->
<?php
$bulletinPosts = $bulletin_posts ?? [];
$totalPosts  = count($bulletinPosts);
$activePosts = count(array_filter($bulletinPosts, fn($p) => !empty($p['is_active'])));
$hiddenPosts = $totalPosts - $activePosts;
$curBFilter  = (string)($bulletin_filter ?? ($_GET['bulletin_chapel'] ?? 'all'));
$isGlobalBulletinMgr = in_array($role ?? '', ['Admin', 'Cathedral Admin'], true);

$bulletinCategories = [
    '#c9a84c' => ['name' => 'Parish Notice'],
    '#22c55e' => ['name' => 'Schedule & Events'],
    '#ef4444' => ['name' => 'Urgent / Important'],
    '#0b0f19' => ['name' => 'Pastoral Advisory'],
    '#64748b' => ['name' => 'Community Update'],
];

$visiblePosts = [];
foreach ($bulletinPosts as $bp) {
    if (empty($can_post_bulletin) && empty($bp['is_active'])) continue;
    $visiblePosts[] = $bp;
}

$canManagePostRow = static function (array $post) use ($can_post_bulletin, $isGlobalBulletinMgr, $chapel_id): bool {
    if (empty($can_post_bulletin)) return false;
    if ($isGlobalBulletinMgr) return true;
    $postChapel = $post['chapel_id'] ?? null;
    if ($postChapel === null || $postChapel === '' || (int)$postChapel === 0) {
        return empty($chapel_id) || (int)$chapel_id === 0;
    }
    return !empty($chapel_id) && (int)$chapel_id === (int)$postChapel;
};

$scopedChapelLabel = 'Cathedral / Parish-Wide';
if (!$isGlobalBulletinMgr && !empty($chapel_id)) {
    foreach (($chapels ?? []) as $ch) {
        if ((int)$ch['chapel_id'] === (int)$chapel_id) {
            $scopedChapelLabel = $ch['chapel_name'] . ' (Assigned Chapel)';
            break;
        }
    }
}
?>

<div class="workspace workspace-fluid" id="sec-bulletin" style="display: flex; flex-direction: column; gap: 14px; width: 100%; max-width: 1320px; margin: 0 auto; box-sizing: border-box; padding: 14px clamp(16px, 2vw, 28px) 28px;">

    <!-- ─── 1. PAGE HEADER ─── -->
    <div class="bulletin-header-bar page-header-bar" style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border); padding-bottom: 8px; flex-wrap: wrap; gap: 10px;">
        <div class="bulletin-header-left" style="display: flex; align-items: center; gap: 14px;">
            <div class="bulletin-header-icon page-header-icon" style="width: 38px; height: 38px; border-radius: 9px; background: rgba(201,168,76,0.14); color: var(--accent); display: flex; align-items: center; justify-content: center; font-size: 17px; flex-shrink: 0;">
                <i class="fa-solid fa-bullhorn"></i>
            </div>
            <div>
                <h1 class="page-title" style="font-family: var(--font-family, 'Inter', sans-serif); font-size: var(--font-page-title, 20px); font-weight: 700; color: var(--text); margin: 0; line-height: 1.2;">
                    Parish Bulletin
                </h1>
                <p class="page-subtitle" style="font-size: var(--font-page-subtitle, 14px); color: var(--text2); margin: 3px 0 0 0;">
                    <?php echo !empty($can_post_bulletin) ? 'Manage official parish and chapel announcements' : 'Weekly parish notices and pulpit announcements'; ?>
                </p>
            </div>
        </div>

        <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
            <span class="badge" style="background: var(--bg3, #F4F4F5); border: 1px solid var(--border); color: var(--text2); font-size: 11.5px; font-weight: 600; padding: 4px 12px; border-radius: 999px;">
                <?php echo (int)$activePosts; ?> Active
            </span>
            <?php if (!empty($can_post_bulletin) && $hiddenPosts > 0): ?>
            <span class="badge" style="background: var(--bg3, #F4F4F5); border: 1px solid var(--border); color: var(--muted); font-size: 11.5px; font-weight: 600; padding: 4px 12px; border-radius: 999px;">
                <?php echo (int)$hiddenPosts; ?> Hidden
            </span>
            <?php endif; ?>
            <?php if (!empty($can_post_bulletin)): ?>
                <button type="button" class="btn btn-primary btn-sm" onclick="openModal('modal-add-bulletin')"
                        style="height: 33px; font-weight: 600; font-size: 12px; display: inline-flex; align-items: center; gap: 6px; background: #111111; color: #FFFFFF; border: 1px solid #111111; border-radius: 999px; padding: 6px 16px; cursor: pointer;">
                    <i class="fa-solid fa-plus text-gold"></i> New Announcement
                </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- ─── 2. OFFICIAL BULLETIN SHEET ─── -->
    <section class="panel pb-sheet-container" style="padding: 0 !important; overflow: hidden !important; flex: none !important; height: auto !important;">

        <!-- Bulletin Letterhead -->
        <header class="pb-letterhead">
            <div class="pb-letterhead-brand">
                <img src="assets/img/2323.png" alt="IFI Cathedral Seal" class="pb-letterhead-seal">
                <div>
                    <div class="pb-letterhead-diocese">Iglesia Filipina Independiente &bull; Cathedral of St. Michael the Archangel</div>
                    <h2 class="pb-letterhead-title">Parish Bulletin &amp; Announcements</h2>
                </div>
            </div>
            <div class="pb-letterhead-date">
                <?php echo date('l, F j, Y'); ?>
            </div>
        </header>

        <!-- Discreet Filter Bar -->
        <div class="pb-filter-strip">
            <div style="display: flex; align-items: center; gap: 8px; flex: 1; min-width: 240px; max-width: 520px; flex-wrap: wrap;">
                <div style="position: relative; flex: 1; min-width: 180px;">
                    <i class="fa-solid fa-magnifying-glass" style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: var(--text2); font-size: 11px; pointer-events: none; z-index: 2;"></i>
                    <input type="text" id="bulletinSearchInput" data-ac-mode="dom" data-ac-target="#bulletinFeedContainer .bulletin-feed-row" data-ac-extract="bulletin" placeholder="Search bulletin announcements..." oninput="filterBulletinTable()"
                           style="width: 100%; height: 32px; padding-left: 30px !important; padding-right: 24px !important; background: var(--bg2); border: 1px solid var(--border); border-radius: 6px; color: var(--text); font-size: 12px; outline: none; box-sizing: border-box;">
                    <span id="bulletin-clear-search" onclick="clearBulletinSearch()" title="Clear search" style="display: none; position: absolute; right: 8px; top: 50%; transform: translateY(-50%); cursor: pointer; color: var(--text2); font-size: 14px; line-height: 1;">&times;</span>
                </div>

                <select id="bulletinScopeSelect"
                        onchange="window.location.href='dashboard.php?section=bulletin&amp;bulletin_chapel=' + encodeURIComponent(this.value)"
                        style="height: 32px; font-size: 12px; font-weight: 500; border-radius: 6px; background: var(--bg2); border: 1px solid var(--border); color: var(--text); padding: 0 26px 0 10px; width: auto; max-width: 210px;"
                        title="Filter by location">
                    <option value="all" <?php echo ($curBFilter === 'all') ? 'selected' : ''; ?>>All Locations (<?php echo $totalPosts; ?>)</option>
                    <option value="parish_wide" <?php echo ($curBFilter === 'parish_wide' || $curBFilter === '0') ? 'selected' : ''; ?>>Parish-Wide Only</option>
                    <?php if (!empty($chapels)): ?>
                    <optgroup label="Chapels">
                        <?php foreach ($chapels as $ch): ?>
                        <option value="<?php echo (int)$ch['chapel_id']; ?>" <?php echo ($curBFilter === (string)$ch['chapel_id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($ch['chapel_name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </optgroup>
                    <?php endif; ?>
                </select>
            </div>

            <div class="pb-tabs" id="bulletin-status-tabs">
                <button type="button" class="tab-btn active" data-status-filter="all" onclick="setBulletinStatusFilter('all', this)">All (<?php echo count($visiblePosts); ?>)</button>
                <button type="button" class="tab-btn" data-status-filter="recent" onclick="setBulletinStatusFilter('recent', this)">Recent</button>
                <button type="button" class="tab-btn" data-status-filter="active" onclick="setBulletinStatusFilter('active', this)">Active (<?php echo $activePosts; ?>)</button>
                <?php if (!empty($can_post_bulletin) && $hiddenPosts > 0): ?>
                <button type="button" class="tab-btn" data-status-filter="hidden" onclick="setBulletinStatusFilter('hidden', this)">Hidden (<?php echo $hiddenPosts; ?>)</button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Bulleted Announcement Body -->
        <div class="pb-sheet-body-wrap">
            <?php if (empty($visiblePosts)): ?>
                <div class="pb-empty">
                    <i class="fa-solid fa-scroll" style="font-size: 28px; color: var(--accent); opacity: 0.45; margin-bottom: 8px;"></i>
                    <div style="font-family: 'Playfair Display', Georgia, serif; font-size: 16px; font-weight: 700; color: var(--text);">No Announcements Currently Posted</div>
                    <div style="font-size: 12.5px; color: var(--text2); margin-top: 4px;">
                        <?php echo !empty($can_post_bulletin) ? 'Use "New Announcement" above to add a bullet to the parish sheet.' : 'Check back soon for new parish notices.'; ?>
                    </div>
                </div>
            <?php else: ?>
                <ul id="bulletinFeedContainer" class="pb-bullet-list">
                    <?php
                    $sevenDaysAgo = time() - (7 * 86400);
                    foreach ($visiblePosts as $idx => $bp):
                        $dotColor = $bp['dot_color'] ?? '#c9a84c';
                        $catName = $bulletinCategories[$dotColor]['name'] ?? 'Parish Notice';
                        $createdTs = !empty($bp['created_at']) ? strtotime($bp['created_at']) : time();
                        $isNewNotice = ($createdTs > 0 && $createdTs >= $sevenDaysAgo);
                        $dateStr = date('M j, Y', $createdTs);
                        $scopeLabel = !empty($bp['chapel_name']) ? $bp['chapel_name'] : 'Parish-Wide';
                        $canEditThis = $canManagePostRow($bp);

                        $bpEnriched = $bp;
                        $bpEnriched['category_label'] = $catName;
                        $postJson = htmlspecialchars(json_encode($bpEnriched, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
                    ?>
                    <li class="bulletin-feed-row pb-bullet-item <?php echo empty($bp['is_active']) ? 'is-hidden-bullet' : ''; ?>"
                        data-post-id="<?php echo (int)$bp['post_id']; ?>"
                        data-sort-idx="<?php echo (int)$idx; ?>"
                        data-post="<?php echo $postJson; ?>"
                        data-created="<?php echo (int)$createdTs; ?>"
                        data-status="<?php echo !empty($bp['is_active']) ? 'active' : 'hidden'; ?>"
                        onclick="openViewBulletinModal(this)"
                        tabindex="0"
                        role="button"
                        aria-label="Announcement: <?php echo htmlspecialchars($bp['title']); ?>">

                        <!-- Left Bullet Marker -->
                        <span class="pb-bullet-marker" aria-hidden="true" style="color: <?php echo htmlspecialchars($dotColor); ?>;">
                            <span class="pb-bullet-dot" style="background: <?php echo htmlspecialchars($dotColor); ?>;"></span>
                        </span>

                        <!-- Center Announcement Text -->
                        <div class="pb-bullet-content">
                            <div class="pb-bullet-headline-line">
                                <span class="bulletin-row-title pb-bullet-title"><?php echo htmlspecialchars($bp['title']); ?></span>
                                <?php if ($idx === 0 && !empty($bp['is_active'])): ?>
                                    <span class="pb-inline-tag pb-tag-pinned"><i class="fa-solid fa-thumbtack"></i> Pinned</span>
                                <?php endif; ?>
                                <?php if ($isNewNotice && !empty($bp['is_active'])): ?>
                                    <span class="pb-inline-tag pb-tag-new">New</span>
                                <?php endif; ?>
                                <?php if (empty($bp['is_active'])): ?>
                                    <span class="pb-inline-tag pb-tag-hidden">Hidden</span>
                                <?php endif; ?>
                            </div>

                            <?php if (!empty($bp['subtitle'])): ?>
                            <p class="bulletin-row-subtitle pb-bullet-text"><?php echo htmlspecialchars($bp['subtitle']); ?></p>
                            <?php endif; ?>

                            <div class="pb-bullet-meta">
                                <span class="pb-meta-item" style="color: <?php echo htmlspecialchars($dotColor); ?>; font-weight: 600;">
                                    <?php echo htmlspecialchars($catName); ?>
                                </span>
                                <span class="pb-meta-sep">&bull;</span>
                                <span class="bulletin-audience-badge pb-meta-item">
                                    <?php echo htmlspecialchars($scopeLabel); ?>
                                </span>
                                <span class="pb-meta-sep">&bull;</span>
                                <span class="pb-meta-item"><?php echo htmlspecialchars($dateStr); ?></span>
                            </div>
                        </div>

                        <!-- Right Action Controls -->
                        <div class="pb-bullet-actions" onclick="event.stopPropagation();">
                            <button type="button"
                                    class="btn-bulletin-view pb-view-text-btn"
                                    data-post="<?php echo $postJson; ?>"
                                    onclick="openViewBulletinModal(this)"
                                    title="Open full notice sheet">
                                Open
                            </button>

                            <?php if ($canEditThis): ?>
                                <?php if ($idx > 0): ?>
                                <form method="POST" style="display:inline;margin:0;">
                                    <input type="hidden" name="form_section" value="bulletin">
                                    <input type="hidden" name="action" value="reorder_post">
                                    <input type="hidden" name="id" value="<?php echo (int)$bp['post_id']; ?>">
                                    <input type="hidden" name="direction" value="up">
                                    <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
                                    <button type="submit" class="pb-ctrl-btn" title="Move Up"><i class="fa-solid fa-chevron-up"></i></button>
                                </form>
                                <?php endif; ?>

                                <?php if ($idx < count($visiblePosts) - 1): ?>
                                <form method="POST" style="display:inline;margin:0;">
                                    <input type="hidden" name="form_section" value="bulletin">
                                    <input type="hidden" name="action" value="reorder_post">
                                    <input type="hidden" name="id" value="<?php echo (int)$bp['post_id']; ?>">
                                    <input type="hidden" name="direction" value="down">
                                    <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
                                    <button type="submit" class="pb-ctrl-btn" title="Move Down"><i class="fa-solid fa-chevron-down"></i></button>
                                </form>
                                <?php endif; ?>

                                <form method="POST" style="display:inline;margin:0;">
                                    <input type="hidden" name="form_section" value="bulletin">
                                    <input type="hidden" name="action" value="toggle_post">
                                    <input type="hidden" name="id" value="<?php echo (int)$bp['post_id']; ?>">
                                    <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
                                    <button type="submit"
                                            class="pb-ctrl-btn <?php echo !empty($bp['is_active']) ? 'is-live' : 'is-hidden'; ?>"
                                            title="<?php echo !empty($bp['is_active']) ? 'Published (click to hide)' : 'Hidden (click to publish)'; ?>">
                                        <i class="fa-solid <?php echo !empty($bp['is_active']) ? 'fa-eye' : 'fa-eye-slash'; ?>"></i>
                                    </button>
                                </form>

                                <button type="button"
                                        class="pb-ctrl-btn pb-ctrl-edit btn-bulletin-edit"
                                        data-post="<?php echo $postJson; ?>"
                                        onclick="openEditBulletinModal(this)"
                                        title="Edit Announcement">
                                    <i class="fa-solid fa-pen" style="pointer-events:none;"></i>
                                </button>

                                <form method="POST" style="display:inline;margin:0;" onsubmit="return confirm('Delete this announcement permanently?')">
                                    <input type="hidden" name="form_section" value="bulletin">
                                    <input type="hidden" name="action" value="delete_post">
                                    <input type="hidden" name="id" value="<?php echo (int)$bp['post_id']; ?>">
                                    <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
                                    <button type="submit" class="pb-ctrl-btn pb-ctrl-del" title="Delete Announcement">
                                        <i class="fa-solid fa-trash" style="pointer-events:none;"></i>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <div id="bulletinSearchEmpty" style="display: none; text-align: center; color: var(--text2); padding: 36px 20px;">
                <i class="fa-solid fa-magnifying-glass" style="font-size: 24px; opacity: 0.35; margin-bottom: 8px; display: block; color: var(--accent);"></i>
                <div style="font-size: 13px; font-weight: 700; color: var(--text);">No matching bulletin entries found.</div>
                <div style="font-size: 12px; margin-top: 3px; color: var(--text2);">Try another keyword or clear the filter.</div>
            </div>
        </div>
    </section>
</div>

<!-- ─── OFFICIAL BULLETIN READER MODAL ─── -->
<div class="modal-overlay" id="modal-view-bulletin" style="z-index: 9999;">
    <div class="modal" style="max-width: 560px; width: 92%; padding: 0; overflow: hidden; border-radius: 14px; background: var(--surface, #FFFFFF); border: 1px solid var(--border); box-shadow: 0 20px 50px rgba(0,0,0,0.3);">
        <div id="bulletin-view-stripe" style="height: 5px; width: 100%; background: var(--accent, #c9a84c);"></div>
        <div style="padding: 14px 20px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; background: var(--bg2);">
            <div style="display: flex; align-items: center; gap: 10px;">
                <img src="assets/img/2323.png" alt="Cathedral Seal" style="width: 32px; height: 32px; border-radius: 7px; object-fit: cover;">
                <div>
                    <div style="font-family: 'Cinzel', Georgia, serif; font-size: 10px; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; color: var(--accent);">Iglesia Filipina Independiente</div>
                    <div style="font-size: 12px; font-weight: 600; color: var(--text2);" id="bulletin-view-badge">Parish-Wide Announcement</div>
                </div>
            </div>
            <button type="button" class="btn-icon" onclick="closeModal('modal-view-bulletin')" style="border: 1px solid var(--border); background: var(--surface); width: 28px; height: 28px; border-radius: 7px; cursor: pointer; color: var(--text2); font-size: 16px; line-height: 1;" aria-label="Close">&times;</button>
        </div>
        <div style="padding: 22px 24px;">
            <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-bottom: 10px;">
                <span id="bulletin-view-dot" style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: var(--accent);"></span>
                <span id="bulletin-view-category" style="font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text2);">Parish Notice</span>
                <span style="font-size: 11.5px; color: var(--text2);" id="bulletin-view-date"></span>
            </div>
            <h3 id="bulletin-view-title" style="font-family: 'Playfair Display', Georgia, serif; font-size: 20px; font-weight: 700; color: var(--text); margin: 0 0 12px; line-height: 1.32;"></h3>
            <div id="bulletin-view-subtitle" style="font-size: 13.5px; color: var(--text); line-height: 1.7; background: var(--bg2); border: 1px solid var(--border); border-left: 3px solid var(--accent); border-radius: 8px; padding: 14px 16px; white-space: pre-wrap; word-break: break-word;"></div>
        </div>
        <div style="padding: 12px 20px; background: var(--bg2); border-top: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; gap: 8px;">
            <button type="button" id="bulletin-copy-btn" class="btn btn-ghost btn-sm" onclick="copyBulletinNotice()" style="padding: 6px 14px; border-radius: 6px; font-weight: 600; font-size: 12px; border: 1px solid var(--border); display: inline-flex; align-items: center; gap: 6px;">
                <i class="fa-regular fa-copy"></i> Copy Notice
            </button>
            <button type="button" class="btn btn-gray btn-sm" onclick="closeModal('modal-view-bulletin')" style="padding: 6px 18px; border-radius: 6px; font-weight: 600; font-size: 12px;">Close</button>
        </div>
    </div>
</div>

<?php if (!empty($can_post_bulletin)): ?>
<!-- ─── MODAL: ADD BULLETIN NOTICE ─── -->
<div class="modal-overlay" id="modal-add-bulletin" style="z-index: 9999;">
    <div class="modal" style="max-width: 520px; width: 92%; padding: 0; overflow: hidden; border-radius: 14px; background: var(--surface, #FFFFFF); border: 1px solid var(--border); box-shadow: 0 16px 40px rgba(0,0,0,0.25);">
        <div style="padding: 16px 20px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; background: var(--bg2);">
            <div style="display: flex; align-items: center; gap: 8px;">
                <span style="display: inline-flex; width: 28px; height: 28px; border-radius: 6px; background: rgba(201,168,76,0.15); color: var(--accent); align-items: center; justify-content: center; font-size: 13px;">
                    <i class="fa-solid fa-bullhorn"></i>
                </span>
                <span style="font-size: 14px; font-weight: 700; color: var(--text);">New Bulletin Announcement</span>
            </div>
            <button type="button" class="btn-icon" onclick="closeModal('modal-add-bulletin')" style="border: none; background: transparent; cursor: pointer; color: var(--text2); font-size: 18px; line-height: 1;" aria-label="Close">&times;</button>
        </div>
        <form method="POST" action="dashboard.php?section=bulletin">
            <input type="hidden" name="form_section" value="bulletin">
            <input type="hidden" name="action" value="create_post">
            <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
            <div style="padding: 20px 22px; display: flex; flex-direction: column; gap: 14px;">
                <div>
                    <label style="display: block; font-size: 12px; font-weight: 600; color: var(--text); margin-bottom: 5px;">
                        Announcement Title <span style="color: #ef4444;">*</span>
                    </label>
                    <input type="text" name="title" required maxlength="100" placeholder="e.g. Cathedral Sunday Holy Mass Schedule"
                           style="width: 100%; height: 36px; padding: 0 10px; font-size: 13px; background: var(--bg2); border: 1px solid var(--border); border-radius: 8px; color: var(--text); box-sizing: border-box;">
                </div>
                <div>
                    <label style="display: block; font-size: 12px; font-weight: 600; color: var(--text); margin-bottom: 5px;">
                        Details &amp; Schedule <span style="color: var(--text2); font-weight: 400; font-size: 11px;">(optional, max 255 chars)</span>
                    </label>
                    <textarea name="subtitle" rows="3" maxlength="255" placeholder="Provide times, venue notes, or parish instructions..."
                              style="width: 100%; padding: 8px 10px; font-size: 13px; line-height: 1.5; background: var(--bg2); border: 1px solid var(--border); border-radius: 8px; color: var(--text); box-sizing: border-box; resize: vertical;"></textarea>
                </div>
                <div>
                    <label style="display: block; font-size: 12px; font-weight: 600; color: var(--text); margin-bottom: 5px;">
                        Jurisdiction / Audience
                    </label>
                    <?php if ($isGlobalBulletinMgr): ?>
                    <select name="chapel_id" style="width: 100%; height: 36px; padding: 0 10px; font-size: 12.5px; background: var(--bg2); border: 1px solid var(--border); border-radius: 8px; color: var(--text); box-sizing: border-box;">
                        <option value="">Parish-Wide (All Chapels &amp; Portals)</option>
                        <?php foreach (($chapels ?? []) as $ch): ?>
                            <option value="<?php echo (int)$ch['chapel_id']; ?>"><?php echo htmlspecialchars($ch['chapel_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php else: ?>
                    <input type="hidden" name="chapel_id" value="<?php echo !empty($chapel_id) ? (int)$chapel_id : ''; ?>">
                    <div style="height: 36px; padding: 0 10px; font-size: 12.5px; background: var(--bg2); border: 1px solid var(--border); border-radius: 8px; color: var(--text2); display: flex; align-items: center; gap: 6px;">
                        <i class="fa-solid fa-lock text-gold" style="font-size: 11px;"></i>
                        <span><?php echo htmlspecialchars($scopedChapelLabel); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
                <div>
                    <label style="display: block; font-size: 12px; font-weight: 600; color: var(--text); margin-bottom: 6px;">
                        Bullet Color &amp; Category
                    </label>
                    <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                        <?php foreach ($bulletinCategories as $hex => $cMeta): ?>
                        <label style="display: inline-flex; align-items: center; gap: 6px; cursor: pointer; font-size: 11.5px; padding: 5px 10px; border-radius: 6px; border: 1px solid var(--border); background: var(--bg2); color: var(--text2);">
                            <input type="radio" name="dot_color" value="<?php echo $hex; ?>" <?php echo $hex === '#c9a84c' ? 'checked' : ''; ?> onchange="updateRadioSelection(this)" style="accent-color: <?php echo $hex; ?>;">
                            <span style="width: 9px; height: 9px; border-radius: 50%; background: <?php echo $hex; ?>; display: inline-block;"></span>
                            <?php echo htmlspecialchars($cMeta['name']); ?>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div style="padding: 12px 20px; background: var(--bg2); border-top: 1px solid var(--border); display: flex; justify-content: flex-end; gap: 8px;">
                <button type="button" class="btn btn-gray btn-sm" onclick="closeModal('modal-add-bulletin')" style="padding: 6px 16px; border-radius: 6px; font-weight: 600; font-size: 12px;">Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm" style="padding: 6px 18px; border-radius: 6px; font-weight: 600; font-size: 12px; background: #111111; color: #FFFFFF; border: 1px solid #111111; display: inline-flex; align-items: center; gap: 6px;">
                    <i class="fa-solid fa-plus text-gold"></i> Publish Notice
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ─── MODAL: EDIT BULLETIN NOTICE ─── -->
<div class="modal-overlay" id="modal-edit-bulletin" style="z-index: 9999;">
    <div class="modal" style="max-width: 520px; width: 92%; padding: 0; overflow: hidden; border-radius: 14px; background: var(--surface, #FFFFFF); border: 1px solid var(--border); box-shadow: 0 16px 40px rgba(0,0,0,0.25);">
        <div style="padding: 16px 20px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; background: var(--bg2);">
            <div style="display: flex; align-items: center; gap: 8px;">
                <span style="display: inline-flex; width: 28px; height: 28px; border-radius: 6px; background: rgba(201,168,76,0.15); color: var(--accent); align-items: center; justify-content: center; font-size: 13px;">
                    <i class="fa-solid fa-pen"></i>
                </span>
                <span style="font-size: 14px; font-weight: 700; color: var(--text);">Edit Announcement</span>
            </div>
            <button type="button" class="btn-icon" onclick="closeModal('modal-edit-bulletin')" style="border: none; background: transparent; cursor: pointer; color: var(--text2); font-size: 18px; line-height: 1;" aria-label="Close">&times;</button>
        </div>
        <form id="form-edit-bulletin" method="POST" action="dashboard.php?section=bulletin">
            <input type="hidden" name="form_section" value="bulletin">
            <input type="hidden" name="action" value="update_post">
            <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
            <input type="hidden" name="id" id="edit-bulletin-id">
            <div style="padding: 20px 22px; display: flex; flex-direction: column; gap: 14px;">
                <div>
                    <label style="display: block; font-size: 12px; font-weight: 600; color: var(--text); margin-bottom: 5px;">
                        Announcement Title <span style="color: #ef4444;">*</span>
                    </label>
                    <input type="text" name="title" id="edit-bulletin-title" required maxlength="100"
                           style="width: 100%; height: 36px; padding: 0 10px; font-size: 13px; background: var(--bg2); border: 1px solid var(--border); border-radius: 8px; color: var(--text); box-sizing: border-box;">
                </div>
                <div>
                    <label style="display: block; font-size: 12px; font-weight: 600; color: var(--text); margin-bottom: 5px;">
                        Details &amp; Schedule <span style="color: var(--text2); font-weight: 400; font-size: 11px;">(optional, max 255 chars)</span>
                    </label>
                    <textarea name="subtitle" id="edit-bulletin-subtitle" rows="3" maxlength="255" placeholder="Provide times, venue notes, or parish instructions..."
                              style="width: 100%; padding: 8px 10px; font-size: 13px; line-height: 1.5; background: var(--bg2); border: 1px solid var(--border); border-radius: 8px; color: var(--text); box-sizing: border-box; resize: vertical;"></textarea>
                </div>
                <div>
                    <label style="display: block; font-size: 12px; font-weight: 600; color: var(--text); margin-bottom: 5px;">
                        Jurisdiction / Audience
                    </label>
                    <?php if ($isGlobalBulletinMgr): ?>
                    <select name="chapel_id" id="edit-bulletin-chapel-id" style="width: 100%; height: 36px; padding: 0 10px; font-size: 12.5px; background: var(--bg2); border: 1px solid var(--border); border-radius: 8px; color: var(--text); box-sizing: border-box;">
                        <option value="">Parish-Wide (All Chapels &amp; Portals)</option>
                        <?php foreach (($chapels ?? []) as $ch): ?>
                            <option value="<?php echo (int)$ch['chapel_id']; ?>"><?php echo htmlspecialchars($ch['chapel_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php else: ?>
                    <input type="hidden" name="chapel_id" id="edit-bulletin-chapel-id" value="<?php echo !empty($chapel_id) ? (int)$chapel_id : ''; ?>">
                    <div style="height: 36px; padding: 0 10px; font-size: 12.5px; background: var(--bg2); border: 1px solid var(--border); border-radius: 8px; color: var(--text2); display: flex; align-items: center; gap: 6px;">
                        <i class="fa-solid fa-lock text-gold" style="font-size: 11px;"></i>
                        <span><?php echo htmlspecialchars($scopedChapelLabel); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
                <div>
                    <label style="display: block; font-size: 12px; font-weight: 600; color: var(--text); margin-bottom: 6px;">
                        Bullet Color &amp; Category
                    </label>
                    <div style="display: flex; gap: 8px; flex-wrap: wrap;" id="edit-bulletin-colors">
                        <?php foreach ($bulletinCategories as $hex => $cMeta): ?>
                        <label style="display: inline-flex; align-items: center; gap: 6px; cursor: pointer; font-size: 11.5px; padding: 5px 10px; border-radius: 6px; border: 1px solid var(--border); background: var(--bg2); color: var(--text2);">
                            <input type="radio" name="dot_color" value="<?php echo $hex; ?>" onchange="updateRadioSelection(this)" style="accent-color: <?php echo $hex; ?>;">
                            <span style="width: 9px; height: 9px; border-radius: 50%; background: <?php echo $hex; ?>; display: inline-block;"></span>
                            <?php echo htmlspecialchars($cMeta['name']); ?>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div style="padding: 12px 20px; background: var(--bg2); border-top: 1px solid var(--border); display: flex; justify-content: flex-end; gap: 8px;">
                <button type="button" class="btn btn-gray btn-sm" onclick="closeModal('modal-edit-bulletin')" style="padding: 6px 16px; border-radius: 6px; font-weight: 600; font-size: 12px;">Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm" style="padding: 6px 18px; border-radius: 6px; font-weight: 600; font-size: 12px; background: #111111; color: #FFFFFF; border: 1px solid #111111; display: inline-flex; align-items: center; gap: 6px;">
                    <i class="fa-solid fa-check text-gold"></i> Save Changes
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<style>
#sec-bulletin.workspace-fluid {
    height: auto !important;
    min-height: 0 !important;
    overflow: visible !important;
}
.pb-sheet-container {
    background: var(--surface, #FFFFFF);
    border: 1px solid var(--border);
    border-radius: 14px;
    display: flex;
    flex-direction: column;
    width: 100%;
    box-shadow: none;
}

/* Official Bulletin Letterhead */
.pb-letterhead {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
    flex-wrap: wrap;
    padding: 16px 24px;
    border-bottom: 2px solid rgba(201,168,76,0.45);
    background: linear-gradient(135deg, rgba(201,168,76,0.10) 0%, transparent 65%);
}
.pb-letterhead-brand {
    display: flex;
    align-items: center;
    gap: 12px;
}
.pb-letterhead-seal {
    width: 38px;
    height: 38px;
    border-radius: 8px;
    object-fit: cover;
    border: 1px solid rgba(201,168,76,0.45);
    flex-shrink: 0;
}
.pb-letterhead-diocese {
    font-family: 'Cinzel', Georgia, serif;
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: var(--accent, #c9a84c);
}
.pb-letterhead-title {
    font-family: 'Playfair Display', Georgia, serif;
    font-size: 19px;
    font-weight: 700;
    color: var(--text);
    margin: 1px 0 0 0;
    line-height: 1.25;
}
.pb-letterhead-date {
    font-size: 12px;
    font-weight: 600;
    color: var(--text2);
    white-space: nowrap;
}

/* Filter Strip */
.pb-filter-strip {
    padding: 10px 24px;
    border-bottom: 1px solid var(--border);
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 10px;
    background: var(--bg2, #FAFAFA);
}
.pb-tabs {
    display: inline-flex;
    align-items: center;
    gap: 3px;
    background: var(--bg3, #F4F4F5);
    padding: 3px;
    border-radius: 999px;
    border: 1px solid var(--border);
}
.pb-tabs .tab-btn {
    padding: 4px 12px;
    font-size: 11.5px;
    border-radius: 999px;
    border: none;
    background: transparent;
    cursor: pointer;
    font-weight: 600;
    color: var(--text2);
}
.pb-tabs .tab-btn.active {
    background: var(--surface, #FFFFFF);
    color: var(--text);
    box-shadow: 0 1px 2px rgba(0,0,0,0.06);
}

/* Single-Column Bulleted Announcement List */
.pb-sheet-body-wrap {
    padding: 6px 24px 20px;
    background: var(--surface, #FFFFFF);
}
.pb-empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    padding: 48px 20px;
}
.pb-bullet-list {
    list-style: none;
    margin: 0;
    padding: 0;
}
.pb-bullet-item {
    display: flex;
    align-items: flex-start;
    gap: 16px;
    padding: 18px 8px;
    border-bottom: 1px solid var(--border);
    cursor: pointer;
    transition: background 0.14s ease;
    border-radius: 8px;
}
.pb-bullet-item:last-child {
    border-bottom: none;
}
.pb-bullet-item:hover {
    background: rgba(201,168,76,0.04);
}
.pb-bullet-item.is-hidden-bullet {
    opacity: 0.58;
}

/* Genuine Bullet Marker */
.pb-bullet-marker {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 18px;
    padding-top: 7px;
    flex-shrink: 0;
}
.pb-bullet-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    display: inline-block;
    box-shadow: 0 0 0 4px rgba(201,168,76,0.12);
}

/* Content Column */
.pb-bullet-content {
    flex: 1;
    min-width: 0;
}
.pb-bullet-headline-line {
    display: flex;
    align-items: baseline;
    flex-wrap: wrap;
    gap: 8px;
}
.pb-bullet-title {
    font-family: 'Playfair Display', Georgia, serif;
    font-size: 16.5px;
    font-weight: 700;
    color: var(--text);
    line-height: 1.35;
}
.pb-bullet-text {
    font-size: 13.5px;
    color: var(--text2);
    line-height: 1.62;
    margin: 5px 0 7px 0;
    max-width: 82ch;
}
.pb-bullet-meta {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 6px;
    font-size: 11.5px;
    color: var(--text2);
}
.pb-meta-sep {
    opacity: 0.45;
}
.pb-inline-tag {
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    padding: 2px 7px;
    border-radius: 4px;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
.pb-tag-pinned {
    background: rgba(201,168,76,0.16);
    color: #9a7418;
}
.pb-tag-new {
    background: rgba(34,197,94,0.14);
    color: #15803d;
}
.pb-tag-hidden {
    background: rgba(239,68,68,0.12);
    color: #dc2626;
}

/* Right Action Cluster */
.pb-bullet-actions {
    display: flex;
    align-items: center;
    gap: 4px;
    flex-shrink: 0;
    padding-top: 2px;
}
.pb-view-text-btn {
    background: transparent;
    border: 1px solid var(--border);
    border-radius: 6px;
    padding: 4px 10px;
    font-size: 11.5px;
    font-weight: 600;
    color: var(--accent, #c9a84c);
    cursor: pointer;
}
.pb-view-text-btn:hover {
    border-color: var(--accent, #c9a84c);
    background: rgba(201,168,76,0.08);
}
.pb-ctrl-btn {
    width: 26px;
    height: 26px;
    border: 1px solid var(--border);
    background: var(--bg2);
    border-radius: 6px;
    cursor: pointer;
    color: var(--text2);
    font-size: 10px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}
.pb-ctrl-btn:hover {
    border-color: var(--accent);
    color: var(--text);
}
.pb-ctrl-btn.is-live { color: #16a34a; }
.pb-ctrl-btn.is-hidden { color: #dc2626; }
.pb-ctrl-edit { color: var(--accent); }
.pb-ctrl-del { color: #ef4444; }
.pb-ctrl-del:hover { border-color: #ef4444; }

[data-theme="dark"] .pb-tag-pinned { color: #facc15; }
[data-theme="dark"] .pb-tag-new { color: #4ade80; }
[data-theme="dark"] .pb-bullet-dot { box-shadow: 0 0 0 4px rgba(255,255,255,0.06); }

@media (max-width: 720px) {
    .pb-letterhead,
    .pb-filter-strip,
    .pb-sheet-body-wrap {
        padding-left: 14px;
        padding-right: 14px;
    }
    .pb-bullet-item {
        flex-wrap: wrap;
        gap: 10px;
    }
    .pb-bullet-actions {
        width: 100%;
        padding-left: 28px;
        justify-content: flex-start;
    }
}
</style>

<script>
var currentBulletinStatusFilter = 'all';

function openViewBulletinModal(input) {
    let el = (input instanceof HTMLElement) ? (input.closest('[data-post]') || input) : null;
    let bp = input;
    if (el) {
        const raw = el.getAttribute('data-post');
        if (raw) {
            try { bp = JSON.parse(raw); } catch (e) { console.error('Failed to parse bulletin post:', e); return; }
        }
    } else if (typeof input === 'string') {
        try { bp = JSON.parse(input); } catch (e) { console.error('Failed to parse bulletin post:', e); return; }
    }
    if (!bp || typeof bp !== 'object') return;

    const stripe = document.getElementById('bulletin-view-stripe');
    const dot = document.getElementById('bulletin-view-dot');
    const catEl = document.getElementById('bulletin-view-category');
    const badge = document.getElementById('bulletin-view-badge');
    const dateEl = document.getElementById('bulletin-view-date');
    const title = document.getElementById('bulletin-view-title');
    const sub = document.getElementById('bulletin-view-subtitle');

    const color = bp.dot_color || 'var(--accent, #c9a84c)';
    if (stripe) stripe.style.background = color;
    if (dot) dot.style.background = color;
    if (catEl) catEl.textContent = bp.category_label || 'Parish Notice';
    if (badge) badge.textContent = bp.chapel_name ? (bp.chapel_name + ' • Official Notice') : 'Parish-Wide Official Announcement';
    if (dateEl) {
        let dText = '';
        if (bp.created_at) {
            const dt = new Date(String(bp.created_at).replace(/-/g, '/'));
            if (!isNaN(dt.getTime())) {
                dText = '• ' + dt.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
            }
        }
        dateEl.textContent = dText;
    }
    if (title) title.textContent = bp.title || 'Announcement';
    if (sub) sub.textContent = bp.subtitle || 'No additional details provided.';

    openModal('modal-view-bulletin');
}
window.openViewBulletinModal = openViewBulletinModal;

function copyBulletinNotice() {
    const title = document.getElementById('bulletin-view-title')?.textContent || '';
    const sub = document.getElementById('bulletin-view-subtitle')?.textContent || '';
    const text = (title ? title + '\n\n' : '') + sub;
    if (!text.trim() || !navigator.clipboard) return;
    navigator.clipboard.writeText(text).then(() => {
        const btn = document.getElementById('bulletin-copy-btn');
        if (btn) {
            const orig = btn.innerHTML;
            btn.innerHTML = '<i class="fa-solid fa-check"></i> Copied';
            setTimeout(() => { btn.innerHTML = orig; }, 1500);
        }
    }).catch(() => {});
}
window.copyBulletinNotice = copyBulletinNotice;

function openEditBulletinModal(input) {
    let el = (input instanceof HTMLElement) ? (input.closest('[data-post]') || input) : null;
    let bp = input;
    if (el) {
        const raw = el.getAttribute('data-post');
        if (raw) {
            try { bp = JSON.parse(raw); } catch (e) { console.error('Failed to parse bulletin post:', e); return; }
        }
    } else if (typeof input === 'string') {
        try { bp = JSON.parse(input); } catch (e) { console.error('Failed to parse bulletin post:', e); return; }
    }
    if (!bp || typeof bp !== 'object') return;

    const idEl = document.getElementById('edit-bulletin-id');
    const titleEl = document.getElementById('edit-bulletin-title');
    const subEl = document.getElementById('edit-bulletin-subtitle');
    const chapelSelect = document.getElementById('edit-bulletin-chapel-id');

    if (idEl) idEl.value = bp.post_id || '';
    if (titleEl) titleEl.value = bp.title || '';
    if (subEl) subEl.value = bp.subtitle || '';
    if (chapelSelect) {
        chapelSelect.value = (bp.chapel_id !== null && bp.chapel_id !== undefined) ? bp.chapel_id : '';
    }

    const radios = document.querySelectorAll('#edit-bulletin-colors input[type=radio]');
    radios.forEach(r => {
        r.checked = (r.value === bp.dot_color);
    });
    if (typeof updateRadioSelection === 'function') {
        const checked = document.querySelector('#edit-bulletin-colors input[type=radio]:checked');
        if (checked) updateRadioSelection(checked);
    }

    openModal('modal-edit-bulletin');
}
window.openEditBulletinModal = openEditBulletinModal;

if (!window._bulletinDelegatedClickBound) {
    window._bulletinDelegatedClickBound = true;
    document.addEventListener('click', function(e) {
        const editBtn = e.target.closest('.btn-bulletin-edit');
        if (editBtn) {
            e.preventDefault();
            openEditBulletinModal(editBtn);
            return;
        }
        const viewBtn = e.target.closest('.btn-bulletin-view');
        if (viewBtn) {
            e.preventDefault();
            openViewBulletinModal(viewBtn);
            return;
        }
    });
    document.addEventListener('keydown', function(e) {
        if ((e.key === 'Enter' || e.key === ' ') && e.target.classList && e.target.classList.contains('pb-bullet-item')) {
            e.preventDefault();
            openViewBulletinModal(e.target);
        }
    });
}

function setBulletinStatusFilter(status, btn) {
    currentBulletinStatusFilter = status;
    document.querySelectorAll('#bulletin-status-tabs .tab-btn').forEach(t => t.classList.remove('active'));
    if (btn) btn.classList.add('active');
    filterBulletinTable();
}

function clearBulletinSearch() {
    const inp = document.getElementById('bulletinSearchInput');
    if (inp) {
        inp.value = '';
        filterBulletinTable();
    }
}

function filterBulletinTable() {
    const q = (document.getElementById('bulletinSearchInput')?.value || '').toLowerCase().trim();
    const clearBtn = document.getElementById('bulletin-clear-search');
    if (clearBtn) clearBtn.style.display = q ? 'block' : 'none';

    const rows = document.querySelectorAll('#bulletinFeedContainer .bulletin-feed-row');
    let visibleCount = 0;
    const thirtyDaysAgo = Math.floor(Date.now() / 1000) - (30 * 86400);

    rows.forEach(row => {
        const title = (row.querySelector('.bulletin-row-title')?.textContent || '').toLowerCase();
        const text = (row.querySelector('.bulletin-row-subtitle')?.textContent || '').toLowerCase();
        const badge = (row.querySelector('.bulletin-audience-badge')?.textContent || '').toLowerCase();
        const rowStatus = row.getAttribute('data-status') || 'active';
        const created = parseInt(row.getAttribute('data-created') || '0', 10);

        const matchesQuery = !q || title.includes(q) || text.includes(q) || badge.includes(q);
        const matchesStatus = (currentBulletinStatusFilter === 'all')
            || (currentBulletinStatusFilter === 'recent' ? (created >= thirtyDaysAgo || created === 0) : (rowStatus === currentBulletinStatusFilter));

        if (matchesQuery && matchesStatus) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });

    const container = document.getElementById('bulletinFeedContainer');
    if (container) {
        const rowArr = Array.from(container.querySelectorAll('.bulletin-feed-row'));
        if (currentBulletinStatusFilter === 'recent') {
            rowArr.sort((a, b) => {
                const cA = parseInt(a.getAttribute('data-created') || a.getAttribute('data-post-id') || '0', 10);
                const cB = parseInt(b.getAttribute('data-created') || b.getAttribute('data-post-id') || '0', 10);
                return cB - cA;
            });
        } else {
            rowArr.sort((a, b) => {
                const iA = parseInt(a.getAttribute('data-sort-idx') || '0', 10);
                const iB = parseInt(b.getAttribute('data-sort-idx') || '0', 10);
                return iA - iB;
            });
        }
        rowArr.forEach(r => container.appendChild(r));
    }

    const emptyMsg = document.getElementById('bulletinSearchEmpty');
    if (emptyMsg) {
        emptyMsg.style.display = (visibleCount === 0 && rows.length > 0) ? 'block' : 'none';
    }
}

function updateRadioSelection(input) {
    document.querySelectorAll('input[name="dot_color"]').forEach(r => {
        const parent = r.closest('label');
        if (parent) {
            parent.style.borderColor = r.checked ? 'var(--accent)' : 'var(--border)';
            parent.style.color = r.checked ? 'var(--text)' : 'var(--text2)';
        }
    });
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('input[name="dot_color"]:checked').forEach(checked => updateRadioSelection(checked));
});
</script>
