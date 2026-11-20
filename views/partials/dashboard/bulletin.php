<!-- ═══════════════════════════════════════════════════════════════ -->
<!-- BULLETIN BOARD (UNIFIED STANDARD DASHBOARD LAYOUT)            -->
<!-- ═══════════════════════════════════════════════════════════════ -->
<?php
$totalPosts  = count($bulletin_posts ?? []);
$activePosts = count(array_filter($bulletin_posts ?? [], fn($p) => !empty($p['is_active'])));
$hiddenPosts = $totalPosts - $activePosts;
$curBFilter  = (string)($bulletin_filter ?? ($_GET['bulletin_chapel'] ?? 'all'));

$dot_colors = [
    '#c9a84c' => 'Gold',
    '#22c55e' => 'Green',
    '#ef4444' => 'Red',
    '#0b0f19' => 'Black',
    '#64748b' => 'Slate',
];
?>

<div class="workspace workspace-fluid" id="sec-bulletin" style="display: flex; flex-direction: column; gap: 14px; width: 100%; max-width: 1680px; margin: 0 auto; box-sizing: border-box; padding: 14px clamp(16px, 1.8vw, 24px) 24px;">

    <!-- ─── 1. TOP HEADER BAR: TITLE + STAT BADGES (PINNED TOP-RIGHT) ─── -->
    <div class="bulletin-header-bar page-header-bar" style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border); padding-bottom: 8px; margin-bottom: 6px; flex-wrap: wrap; gap: 10px;">
        <div class="bulletin-header-left" style="display: flex; align-items: center; gap: 14px;">
            <div class="bulletin-header-icon page-header-icon" style="width: 38px; height: 38px; border-radius: 9px; background: rgba(201,168,76,0.14); color: var(--accent); display: flex; align-items: center; justify-content: center; font-size: 17px; flex-shrink: 0;">
                <i class="fa-solid fa-bullhorn"></i>
            </div>
            <div>
                <h1 class="page-title" style="font-family: var(--font-family, 'Inter', sans-serif); font-size: var(--font-page-title, 20px); font-weight: 700; color: var(--text); margin: 0; line-height: 1.2;">
                    Bulletin
                </h1>
                <p class="page-subtitle" style="font-size: var(--font-page-subtitle, 14px); color: var(--text2); margin: 3px 0 0 0;">
                    <?php echo $can_post_bulletin ? 'Manage official parish announcements, schedules, and priority order' : 'Official parish announcements, liturgical schedules, and cathedral updates'; ?>
                </p>
            </div>
        </div>

        <!-- Right: Stat Badges + Add Action -->
        <div style="display: flex; align-items: center; gap: 8px;">
            <span class="badge" style="background: var(--bg3, #F4F4F5); border: 1px solid var(--border); color: var(--text2); font-size: 11.5px; font-weight: 600; padding: 4px 12px; border-radius: 999px;">
                <?php echo (int)$totalPosts; ?> Notices
            </span>
            <?php if ($hiddenPosts > 0): ?>
            <span class="badge" style="background: var(--bg3, #F4F4F5); border: 1px solid var(--border); color: var(--muted); font-size: 11.5px; font-weight: 600; padding: 4px 12px; border-radius: 999px;" title="Hidden Notices">
                <?php echo (int)$hiddenPosts; ?> Hidden
            </span>
            <?php endif; ?>
            <?php if ($can_post_bulletin): ?>
                <button type="button" class="btn btn-primary btn-sm" onclick="openModal('modal-add-bulletin')" 
                        style="height: 32px; font-weight: 600; font-size: 11.5px; display: inline-flex; align-items: center; gap: 6px; background: #111111; color: #FFFFFF; border: 1px solid #111111; border-radius: 6px; padding: 6px 14px; cursor: pointer; box-shadow: 0 1px 3px rgba(0,0,0,0.12); margin-left: 4px;">
                    <i class="fa-solid fa-plus text-gold"></i> New Announcement
                </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- ─── 2. MAIN ANNOUNCEMENT BOARD CONTAINER ─── -->
    <div class="panel bulletin-board-panel" style="border-radius: 12px; background: var(--surface, #FFFFFF); border: 1px solid var(--border); box-shadow: none; display: flex; flex-direction: column; width: 100%; padding: 0 !important;">
        
        <!-- Compact Filter & Search Toolbar inside panel header -->
        <div style="padding: 12px 16px; border-bottom: 1px solid var(--border); display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 10px; background: var(--surface, #FFFFFF); flex-shrink: 0;">
            
            <!-- Left: Search and Location Filter -->
            <div style="display: flex; align-items: center; gap: 8px; flex: 1; min-width: 280px; max-width: 540px; flex-wrap: wrap;">
                <!-- Search Input with Clear Button -->
                <div style="position: relative; flex: 1; min-width: 190px;">
                    <i class="fa-solid fa-magnifying-glass" style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: var(--text2, #737373); font-size: 11px; pointer-events: none; z-index: 2;"></i>
                    <input type="text" id="bulletinSearchInput" placeholder="Search announcements..." oninput="filterBulletinTable()" 
                           style="width: 100%; height: 32px; padding-left: 30px !important; padding-right: 24px !important; background: var(--bg2, #FAFAFA); border: 1px solid var(--border); border-radius: 6px; color: var(--text, #111111); font-size: 12px; outline: none; box-sizing: border-box;">
                    <span id="bulletin-clear-search" onclick="clearBulletinSearch()" title="Clear search" style="display: none; position: absolute; right: 8px; top: 50%; transform: translateY(-50%); cursor: pointer; color: var(--text2); font-size: 14px; line-height: 1;">&times;</span>
                </div>

                <!-- Chapel Location Filter Dropdown -->
                <select id="bulletinScopeSelect" 
                        onchange="window.location.href='dashboard.php?section=bulletin&amp;bulletin_chapel=' + encodeURIComponent(this.value)"
                        style="height: 32px; font-size: 12px; font-weight: 500; border-radius: 6px; background: var(--bg2, #FAFAFA); border: 1px solid var(--border); color: var(--text); padding: 0 28px 0 10px; width: auto; max-width: 200px;"
                        title="Filter by jurisdiction">
                    <option value="all" <?php echo ($curBFilter === 'all') ? 'selected' : ''; ?>>All Locations (<?php echo $totalPosts; ?>)</option>
                    <option value="parish_wide" <?php echo ($curBFilter === 'parish_wide' || $curBFilter === '0') ? 'selected' : ''; ?>>🌐 Parish-Wide Only</option>
                    <?php if (!empty($chapels)): ?>
                    <optgroup label="Chapels">
                        <?php foreach ($chapels as $ch): ?>
                        <option value="<?php echo $ch['chapel_id']; ?>" <?php echo ($curBFilter === (string)$ch['chapel_id']) ? 'selected' : ''; ?>>
                            ⛪ <?php echo htmlspecialchars($ch['chapel_name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </optgroup>
                    <?php endif; ?>
                </select>
            </div>

            <!-- Right: Segmented Capsule Filter Tabs -->
            <div style="display: inline-flex; align-items: center; gap: 4px; background: var(--bg3, #F4F4F5); padding: 3px; border-radius: 999px; border: 1px solid var(--border);" id="bulletin-status-tabs">
                <button type="button" class="tab-btn active" data-status-filter="all" onclick="setBulletinStatusFilter('all', this)" style="padding: 4px 12px; font-size: 11.5px; border-radius: 999px; border: none; background: transparent; cursor: pointer; font-weight: 600; color: var(--text);">
                    All (<?php echo $totalPosts; ?>)
                </button>
                <button type="button" class="tab-btn" data-status-filter="recent" onclick="setBulletinStatusFilter('recent', this)" style="padding: 4px 12px; font-size: 11.5px; border-radius: 999px; border: none; background: transparent; cursor: pointer; font-weight: 600; color: var(--text2);" title="Announcements published in the last 30 days">
                    Recently Added
                </button>
                <button type="button" class="tab-btn" data-status-filter="active" onclick="setBulletinStatusFilter('active', this)" style="padding: 4px 12px; font-size: 11.5px; border-radius: 999px; border: none; background: transparent; cursor: pointer; font-weight: 600; color: var(--text2);">
                    Active (<?php echo $activePosts; ?>)
                </button>
                <?php if ($hiddenPosts > 0): ?>
                <button type="button" class="tab-btn" data-status-filter="hidden" onclick="setBulletinStatusFilter('hidden', this)" style="padding: 4px 12px; font-size: 11.5px; border-radius: 999px; border: none; background: transparent; cursor: pointer; font-weight: 600; color: var(--text2);">
                    Hidden (<?php echo $hiddenPosts; ?>)
                </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- ─── ANNOUNCEMENT BOARD (PINNED HERO + 2-COLUMN NOTICE CARDS) ─── -->
        <div style="padding: 16px; background: var(--bg2, #FAFAFA); flex: 1; min-height: 0;">
            <?php
                $visiblePosts = [];
                foreach ($bulletin_posts ?? [] as $bp) {
                    if (!$can_post_bulletin && empty($bp['is_active'])) continue;
                    $visiblePosts[] = $bp;
                }
            ?>
            <?php if (empty($visiblePosts)): ?>
                <div style="text-align: center; padding: 56px 20px; color: var(--text2); background: var(--surface, #FFFFFF); border: 1px dashed var(--border); border-radius: 12px;">
                    <i class="fa-solid fa-bullhorn" style="font-size: 34px; opacity: 0.3; margin-bottom: 10px; display: block; color: var(--accent);"></i>
                    <div style="font-size: 14px; font-weight: 700; color: var(--text);">No announcements found for this filter.</div>
                    <div style="font-size: 12px; margin-top: 4px; color: var(--text2);">
                        <?php echo $can_post_bulletin ? 'Click "+ New Announcement" above to publish a notice.' : 'Check back later for upcoming parish notices and schedules.'; ?>
                    </div>
                </div>
            <?php else: ?>
                <div id="bulletinFeedContainer" class="bulletin-cards-grid">
                    <?php
                    $sevenDaysAgo = time() - (7 * 86400);
                    foreach ($visiblePosts as $idx => $bp):
                        $isPinnedHero = ($idx === 0 && !empty($bp['is_active']));
                        $dotColor = $bp['dot_color'] ?? '#c9a84c';
                        $createdTs = !empty($bp['created_at']) ? strtotime($bp['created_at']) : 0;
                        $isNewNotice = ($createdTs > 0 && $createdTs >= $sevenDaysAgo);
                        $dateLabel = $createdTs > 0 ? date('M j, Y', $createdTs) : 'Official Notice';
                        $postJson = htmlspecialchars(json_encode($bp, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
                    ?>
                    <article class="bulletin-feed-row bulletin-notice-card <?php echo $isPinnedHero ? 'is-pinned-hero' : ''; ?> <?php echo empty($bp['is_active']) ? 'is-hidden-row' : ''; ?>"
                             data-post-id="<?php echo intval($bp['post_id']); ?>"
                             data-post="<?php echo $postJson; ?>"
                             data-created="<?php echo $createdTs; ?>"
                             data-status="<?php echo !empty($bp['is_active']) ? 'active' : 'hidden'; ?>"
                             onclick="openViewBulletinModal(this)"
                             style="<?php echo empty($bp['is_active']) ? 'opacity: 0.68;' : ''; ?>">

                        <!-- Top Priority Accent Strip -->
                        <div style="height: <?php echo $isPinnedHero ? '4px' : '3px'; ?>; background: <?php echo htmlspecialchars($dotColor); ?>; width: 100%; flex-shrink: 0;"></div>

                        <div style="padding: <?php echo $isPinnedHero ? '18px 20px' : '15px 17px'; ?>; display: flex; flex-direction: column; gap: 10px; flex: 1; min-width: 0;">
                            <!-- Card Meta Header -->
                            <div style="display: flex; align-items: center; justify-content: space-between; gap: 8px; flex-wrap: wrap;">
                                <div style="display: flex; align-items: center; gap: 6px; flex-wrap: wrap; min-width: 0;">
                                    <?php if ($isPinnedHero): ?>
                                        <span style="display: inline-flex; align-items: center; gap: 5px; font-size: 10.5px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; padding: 3px 9px; border-radius: 999px; background: rgba(201,168,76,0.16); color: #9a7418; border: 1px solid rgba(201,168,76,0.35);">
                                            <i class="fa-solid fa-thumbtack" style="font-size: 9.5px;"></i> Pinned Notice
                                        </span>
                                    <?php endif; ?>

                                    <?php if (!empty($bp['chapel_name'])): ?>
                                        <span class="bulletin-audience-badge" title="Jurisdiction: <?php echo htmlspecialchars($bp['chapel_name']); ?>" style="font-size: 11px; font-weight: 600; padding: 3px 9px; border-radius: 999px; background: var(--bg2, #f6f6f6); border: 1px solid var(--border); color: var(--text2); display: inline-flex; align-items: center; gap: 5px; max-width: 220px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                            <i class="fa-solid fa-church text-gold" style="font-size: 10px; flex-shrink: 0;"></i>
                                            <span style="overflow: hidden; text-overflow: ellipsis;"><?php echo htmlspecialchars($bp['chapel_name']); ?></span>
                                        </span>
                                    <?php else: ?>
                                        <span class="bulletin-audience-badge" title="Jurisdiction: Parish-Wide Notice" style="font-size: 11px; font-weight: 600; padding: 3px 9px; border-radius: 999px; background: var(--bg2, #f6f6f6); border: 1px solid var(--border); color: var(--text2); display: inline-flex; align-items: center; gap: 5px;">
                                            <i class="fa-solid fa-globe text-gold" style="font-size: 10px; flex-shrink: 0;"></i> Parish-Wide
                                        </span>
                                    <?php endif; ?>

                                    <?php if ($isNewNotice): ?>
                                        <span style="font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; padding: 2px 7px; border-radius: 999px; background: rgba(34,197,94,0.12); color: #15803d; border: 1px solid rgba(34,197,94,0.28);">
                                            New
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <span style="font-size: 11px; color: var(--text2); font-weight: 500; white-space: nowrap; display: inline-flex; align-items: center; gap: 5px;">
                                    <i class="fa-regular fa-calendar" style="font-size: 10.5px; opacity: 0.75;"></i>
                                    <?php echo htmlspecialchars($dateLabel); ?>
                                </span>
                            </div>

                            <!-- Headline & Excerpt -->
                            <div style="flex: 1; min-width: 0;">
                                <div class="bulletin-row-title" style="font-family: var(--font-family-base, 'Inter', sans-serif); font-size: <?php echo $isPinnedHero ? '16.5px' : '14.5px'; ?>; font-weight: 700; color: var(--text); line-height: 1.35; display: flex; align-items: flex-start; gap: 8px;">
                                    <span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: <?php echo htmlspecialchars($dotColor); ?>; margin-top: <?php echo $isPinnedHero ? '7px' : '6px'; ?>; flex-shrink: 0;"></span>
                                    <span><?php echo htmlspecialchars($bp['title']); ?></span>
                                </div>
                                <div class="bulletin-row-subtitle" style="font-size: <?php echo $isPinnedHero ? '13px' : '12.5px'; ?>; color: var(--text2); margin-top: 6px; line-height: 1.55; display: -webkit-box; -webkit-line-clamp: <?php echo $isPinnedHero ? '4' : '3'; ?>; -webkit-box-orient: vertical; overflow: hidden; padding-left: 16px;">
                                    <?php echo htmlspecialchars($bp['subtitle'] ?? 'No additional details provided.'); ?>
                                </div>
                            </div>

                            <!-- Card Footer: Read More + Admin Controls -->
                            <div style="display: flex; align-items: center; justify-content: space-between; gap: 8px; padding-top: 10px; border-top: 1px solid var(--border); flex-wrap: wrap;" onclick="event.stopPropagation();">
                                <div style="display: inline-flex; align-items: center; gap: 8px;">
                                    <button type="button"
                                            class="btn-bulletin-view"
                                            data-post="<?php echo $postJson; ?>"
                                            onclick="openViewBulletinModal(this)"
                                            style="background: none; border: none; padding: 0; font-size: 12px; font-weight: 600; color: var(--accent, #c9a84c); cursor: pointer; display: inline-flex; align-items: center; gap: 5px;">
                                        <span>Read full notice</span>
                                        <i class="fa-solid fa-arrow-right" style="font-size: 10px;"></i>
                                    </button>
                                </div>

                                <?php if ($can_post_bulletin): ?>
                                <div style="display: inline-flex; align-items: center; gap: 5px;">
                                    <span style="font-size: 11px; font-weight: 600; color: var(--text2); margin-right: 2px;">#<?php echo $idx + 1; ?></span>

                                    <?php if ($idx > 0): ?>
                                    <form method="POST" style="display:inline;margin:0;">
                                        <input type="hidden" name="form_section" value="bulletin">
                                        <input type="hidden" name="action" value="reorder_post">
                                        <input type="hidden" name="id" value="<?php echo intval($bp['post_id']); ?>">
                                        <input type="hidden" name="direction" value="up">
                                        <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
                                        <button type="submit" title="Move Up" style="width: 26px; height: 26px; border: 1px solid var(--border); background: var(--surface); border-radius: 6px; cursor: pointer; color: var(--text2); font-size: 10px; display: inline-flex; align-items: center; justify-content: center;"><i class="fa-solid fa-chevron-up"></i></button>
                                    </form>
                                    <?php endif; ?>

                                    <?php if ($idx < count($visiblePosts) - 1): ?>
                                    <form method="POST" style="display:inline;margin:0;">
                                        <input type="hidden" name="form_section" value="bulletin">
                                        <input type="hidden" name="action" value="reorder_post">
                                        <input type="hidden" name="id" value="<?php echo intval($bp['post_id']); ?>">
                                        <input type="hidden" name="direction" value="down">
                                        <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
                                        <button type="submit" title="Move Down" style="width: 26px; height: 26px; border: 1px solid var(--border); background: var(--surface); border-radius: 6px; cursor: pointer; color: var(--text2); font-size: 10px; display: inline-flex; align-items: center; justify-content: center;"><i class="fa-solid fa-chevron-down"></i></button>
                                    </form>
                                    <?php endif; ?>

                                    <form method="POST" style="display: inline; margin: 0;">
                                        <input type="hidden" name="form_section" value="bulletin">
                                        <input type="hidden" name="action" value="toggle_post">
                                        <input type="hidden" name="id" value="<?php echo intval($bp['post_id']); ?>">
                                        <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
                                        <button type="submit"
                                                class="badge"
                                                style="border: 1px solid <?php echo $bp['is_active'] ? 'rgba(34,197,94,0.3)' : 'rgba(239,68,68,0.3)'; ?>; background: <?php echo $bp['is_active'] ? 'rgba(34,197,94,0.12)' : 'rgba(239,68,68,0.12)'; ?>; color: <?php echo $bp['is_active'] ? '#16a34a' : '#dc2626'; ?>; font-size: 10.5px; font-weight: 600; padding: 3px 8px; border-radius: 999px; cursor: pointer; display: inline-flex; align-items: center; gap: 4px;"
                                                title="Click to toggle visibility">
                                            <i class="fa-solid <?php echo $bp['is_active'] ? 'fa-eye' : 'fa-eye-slash'; ?>"></i>
                                            <?php echo $bp['is_active'] ? 'Active' : 'Hidden'; ?>
                                        </button>
                                    </form>

                                    <button type="button"
                                            class="btn btn-ghost btn-sm btn-bulletin-edit"
                                            data-post="<?php echo $postJson; ?>"
                                            onclick="openEditBulletinModal(this)"
                                            title="Edit announcement"
                                            style="width: 26px; height: 26px; padding: 0; display: inline-flex; align-items: center; justify-content: center; border-radius: 6px; border: 1px solid var(--border); background: var(--surface); color: var(--accent);">
                                        <i class="fa-solid fa-pen" style="font-size: 10.5px; pointer-events: none;"></i>
                                    </button>

                                    <form method="POST" style="display: inline; margin: 0;" onsubmit="return confirm('Delete this announcement permanently?')">
                                        <input type="hidden" name="form_section" value="bulletin">
                                        <input type="hidden" name="action" value="delete_post">
                                        <input type="hidden" name="id" value="<?php echo intval($bp['post_id']); ?>">
                                        <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
                                        <button type="submit"
                                                class="btn btn-ghost btn-sm"
                                                title="Delete announcement"
                                                style="width: 26px; height: 26px; padding: 0; display: inline-flex; align-items: center; justify-content: center; border-radius: 6px; border: 1px solid rgba(239,68,68,0.25); background: var(--surface); color: #ef4444;">
                                            <i class="fa-solid fa-trash" style="font-size: 10.5px; pointer-events: none;"></i>
                                        </button>
                                    </form>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Empty search results notice inside panel -->
        <div id="bulletinSearchEmpty" style="display: none; text-align: center; color: var(--text2); padding: 36px 20px;">
            <i class="fa-solid fa-magnifying-glass" style="font-size: 26px; opacity: 0.35; margin-bottom: 8px; display: block; color: var(--accent);"></i>
            <div style="font-size: 13px; font-weight: 700; color: var(--text);">No matching announcements found.</div>
            <div style="font-size: 11.5px; margin-top: 3px; color: var(--text2);">Try searching for a different keyword or location.</div>
        </div>
    </div>
</div>

<!-- ─── BULLETIN READER MODAL (HIGH AESTHETICS & ACCESSIBILITY) ─── -->
<div class="modal-overlay" id="modal-view-bulletin" style="z-index: 9999;">
    <div class="modal" style="max-width: 520px; width: 92%; padding: 0; overflow: hidden; border-radius: 14px; background: var(--surface, #FFFFFF); border: 1px solid var(--border); box-shadow: 0 16px 40px rgba(0,0,0,0.25);">
        <div style="padding: 16px 20px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; background: var(--bg2, #FAFAFA);">
            <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                <span id="bulletin-view-dot" style="display: inline-block; width: 10px; height: 10px; border-radius: 50%; background: var(--accent);"></span>
                <span style="font-size: 11px; text-transform: uppercase; font-weight: 700; letter-spacing: 0.05em; color: var(--text2);" id="bulletin-view-badge">Parish Announcement</span>
                <span style="font-size: 11px; color: var(--text2);" id="bulletin-view-date"></span>
            </div>
            <button type="button" class="btn-icon" onclick="closeModal('modal-view-bulletin')" style="border: none; background: transparent; cursor: pointer; color: var(--text2); font-size: 18px; line-height: 1;" aria-label="Close">&times;</button>
        </div>
        <div style="padding: 24px 22px;">
            <h3 id="bulletin-view-title" style="font-family: var(--font-family-base, 'Inter', sans-serif); font-size: 16px; font-weight: 700; color: var(--text); margin: 0 0 14px; line-height: 1.4;"></h3>
            <div id="bulletin-view-subtitle" style="font-size: 13px; color: var(--text2); line-height: 1.65; background: var(--bg2, #F8F9FA); border: 1px solid var(--border); border-radius: 10px; padding: 16px; white-space: pre-wrap; word-break: break-word;"></div>
        </div>
        <div style="padding: 12px 20px; background: var(--bg2, #FAFAFA); border-top: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; gap: 8px;">
            <button type="button" id="bulletin-copy-btn" class="btn btn-ghost btn-sm" onclick="copyBulletinNotice()" style="padding: 6px 14px; border-radius: 6px; font-weight: 600; font-size: 12px; border: 1px solid var(--border); display: inline-flex; align-items: center; gap: 6px;">
                <i class="fa-regular fa-copy"></i> Copy Notice
            </button>
            <button type="button" class="btn btn-gray btn-sm" onclick="closeModal('modal-view-bulletin')" style="padding: 6px 18px; border-radius: 6px; font-weight: 600; font-size: 12px;">Close</button>
        </div>
    </div>
</div>

<?php if ($can_post_bulletin): ?>
<!-- ─── MODAL: ADD BULLETIN NOTICE ─── -->
<div class="modal-overlay" id="modal-add-bulletin" style="z-index: 9999;">
    <div class="modal" style="max-width: 520px; width: 92%; padding: 0; overflow: hidden; border-radius: 14px; background: var(--surface, #FFFFFF); border: 1px solid var(--border); box-shadow: 0 16px 40px rgba(0,0,0,0.25);">
        <div style="padding: 16px 20px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; background: var(--bg2, #FAFAFA);">
            <div style="display: flex; align-items: center; gap: 8px;">
                <span style="display: inline-flex; width: 28px; height: 28px; border-radius: 6px; background: rgba(201,168,76,0.15); color: var(--accent); align-items: center; justify-content: center; font-size: 13px;">
                    <i class="fa-solid fa-bullhorn"></i>
                </span>
                <span style="font-size: 14px; font-weight: 700; color: var(--text);">New Announcement</span>
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
                    <input type="text" name="title" required placeholder="e.g. Cathedral Sunday Holy Mass Schedule" 
                           style="width: 100%; height: 36px; padding: 0 10px; font-size: 13px; background: var(--bg2); border: 1px solid var(--border); border-radius: 8px; color: var(--text); box-sizing: border-box;">
                </div>
                <div>
                    <label style="display: block; font-size: 12px; font-weight: 600; color: var(--text); margin-bottom: 5px;">
                        Details &amp; Schedule <span style="color: var(--text2); font-weight: 400; font-size: 11px;">(optional)</span>
                    </label>
                    <textarea name="subtitle" rows="3" placeholder="Provide schedule, briefing times, or instructions for parishioners..." 
                              style="width: 100%; padding: 8px 10px; font-size: 13px; line-height: 1.5; background: var(--bg2); border: 1px solid var(--border); border-radius: 8px; color: var(--text); box-sizing: border-box; resize: vertical;"></textarea>
                </div>
                <div>
                    <label style="display: block; font-size: 12px; font-weight: 600; color: var(--text); margin-bottom: 5px;">
                        Jurisdiction / Audience
                    </label>
                    <select name="chapel_id" style="width: 100%; height: 36px; padding: 0 10px; font-size: 12.5px; background: var(--bg2); border: 1px solid var(--border); border-radius: 8px; color: var(--text); box-sizing: border-box;">
                        <option value="">🌐 Parish-Wide (All Chapels &amp; Portals)</option>
                        <?php foreach (($chapels ?? []) as $ch): ?>
                            <option value="<?php echo $ch['chapel_id']; ?>">⛪ <?php echo htmlspecialchars($ch['chapel_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label style="display: block; font-size: 12px; font-weight: 600; color: var(--text); margin-bottom: 6px;">
                        Priority Indicator Color
                    </label>
                    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                        <?php
                        $dot_colors_add = ['#c9a84c'=>'Gold','#22c55e'=>'Green','#ef4444'=>'Red','#0b0f19'=>'Dark','#64748b'=>'Slate'];
                        foreach ($dot_colors_add as $hex => $label):
                        ?>
                        <label style="display: inline-flex; align-items: center; gap: 6px; cursor: pointer; font-size: 12px; padding: 5px 10px; border-radius: 6px; border: 1px solid var(--border); background: var(--bg2); color: var(--text2);">
                            <input type="radio" name="dot_color" value="<?php echo $hex; ?>" <?php echo $hex === '#c9a84c' ? 'checked' : ''; ?> style="accent-color: <?php echo $hex; ?>;">
                            <span style="width: 10px; height: 10px; border-radius: 50%; background: <?php echo $hex; ?>; display: inline-block;"></span>
                            <?php echo $label; ?>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div style="padding: 12px 20px; background: var(--bg2, #FAFAFA); border-top: 1px solid var(--border); display: flex; justify-content: flex-end; gap: 8px;">
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
        <div style="padding: 16px 20px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; background: var(--bg2, #FAFAFA);">
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
                    <input type="text" name="title" id="edit-bulletin-title" required 
                           style="width: 100%; height: 36px; padding: 0 10px; font-size: 13px; background: var(--bg2); border: 1px solid var(--border); border-radius: 8px; color: var(--text); box-sizing: border-box;">
                </div>
                <div>
                    <label style="display: block; font-size: 12px; font-weight: 600; color: var(--text); margin-bottom: 5px;">
                        Details &amp; Schedule <span style="color: var(--text2); font-weight: 400; font-size: 11px;">(optional)</span>
                    </label>
                    <textarea name="subtitle" id="edit-bulletin-subtitle" rows="3" placeholder="Provide schedule, briefing times, or instructions for parishioners..." 
                              style="width: 100%; padding: 8px 10px; font-size: 13px; line-height: 1.5; background: var(--bg2); border: 1px solid var(--border); border-radius: 8px; color: var(--text); box-sizing: border-box; resize: vertical;"></textarea>
                </div>
                <div>
                    <label style="display: block; font-size: 12px; font-weight: 600; color: var(--text); margin-bottom: 5px;">
                        Jurisdiction / Audience
                    </label>
                    <select name="chapel_id" id="edit-bulletin-chapel-id" style="width: 100%; height: 36px; padding: 0 10px; font-size: 12.5px; background: var(--bg2); border: 1px solid var(--border); border-radius: 8px; color: var(--text); box-sizing: border-box;">
                        <option value="">🌐 Parish-Wide (All Chapels &amp; Portals)</option>
                        <?php foreach (($chapels ?? []) as $ch): ?>
                            <option value="<?php echo $ch['chapel_id']; ?>">⛪ <?php echo htmlspecialchars($ch['chapel_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label style="display: block; font-size: 12px; font-weight: 600; color: var(--text); margin-bottom: 6px;">
                        Priority Indicator Color
                    </label>
                    <div style="display: flex; gap: 10px; flex-wrap: wrap;" id="edit-bulletin-colors">
                        <?php
                        $dot_colors_edit = ['#c9a84c'=>'Gold','#22c55e'=>'Green','#ef4444'=>'Red','#0b0f19'=>'Dark','#64748b'=>'Slate'];
                        foreach ($dot_colors_edit as $hex => $label):
                        ?>
                        <label style="display: inline-flex; align-items: center; gap: 6px; cursor: pointer; font-size: 12px; padding: 5px 10px; border-radius: 6px; border: 1px solid var(--border); background: var(--bg2); color: var(--text2);">
                            <input type="radio" name="dot_color" value="<?php echo $hex; ?>" style="accent-color: <?php echo $hex; ?>;">
                            <span style="width: 10px; height: 10px; border-radius: 50%; background: <?php echo $hex; ?>; display: inline-block;"></span>
                            <?php echo $label; ?>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div style="padding: 12px 20px; background: var(--bg2, #FAFAFA); border-top: 1px solid var(--border); display: flex; justify-content: flex-end; gap: 8px;">
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
#sec-bulletin .bulletin-board-panel {
    padding: 0 !important;
    margin-bottom: 0 !important;
    overflow: visible !important;
    flex: none !important;
    height: auto !important;
}
.bulletin-cards-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 14px;
}
.bulletin-notice-card {
    background: var(--surface, #FFFFFF);
    border: 1px solid var(--border);
    border-radius: 12px;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    cursor: pointer;
    transition: border-color 0.15s ease, box-shadow 0.15s ease, transform 0.15s ease;
    box-shadow: 0 1px 3px rgba(0,0,0,0.03);
}
.bulletin-notice-card:hover {
    border-color: rgba(201,168,76,0.55);
    box-shadow: 0 6px 18px rgba(0,0,0,0.06);
    transform: translateY(-1px);
}
.bulletin-notice-card.is-pinned-hero {
    grid-column: 1 / -1;
    border-color: rgba(201,168,76,0.45);
    background: linear-gradient(180deg, rgba(201,168,76,0.05) 0%, var(--surface, #FFFFFF) 45%);
}
@media (max-width: 820px) {
    .bulletin-cards-grid {
        grid-template-columns: 1fr;
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

    const dot = document.getElementById('bulletin-view-dot');
    const badge = document.getElementById('bulletin-view-badge');
    const dateEl = document.getElementById('bulletin-view-date');
    const title = document.getElementById('bulletin-view-title');
    const sub = document.getElementById('bulletin-view-subtitle');
    
    if (dot) dot.style.background = bp.dot_color || 'var(--accent)';
    if (badge) badge.textContent = bp.chapel_name ? '⛪ ' + bp.chapel_name : '🌐 Parish-Wide Notice';
    if (dateEl) {
        let dText = '';
        if (bp.created_at) {
            const dt = new Date(String(bp.created_at).replace(/-/g, '/'));
            if (!isNaN(dt.getTime())) {
                dText = '• ' + dt.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
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

// Delegated click listener: guaranteed activation for SPA and dynamically rendered rows
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

function setBulletinStatusFilter(status, btn) {
    currentBulletinStatusFilter = status;
    const tabs = document.querySelectorAll('#bulletin-status-tabs .tab-btn');
    tabs.forEach(t => {
        t.classList.remove('active');
        t.style.background = 'transparent';
        t.style.color = 'var(--text2)';
    });
    if (btn) {
        btn.classList.add('active');
        btn.style.background = 'var(--surface, #FFFFFF)';
        btn.style.color = 'var(--text, #111111)';
        btn.style.boxShadow = '0 1px 2px rgba(0,0,0,0.06)';
    }
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

    if (currentBulletinStatusFilter === 'recent') {
        const tbody = document.getElementById('bulletinFeedContainer');
        if (tbody) {
            const rowArr = Array.from(tbody.querySelectorAll('.bulletin-feed-row'));
            rowArr.sort((a, b) => {
                const cA = parseInt(a.getAttribute('data-created') || a.getAttribute('data-post-id') || '0', 10);
                const cB = parseInt(b.getAttribute('data-created') || b.getAttribute('data-post-id') || '0', 10);
                return cB - cA; // Newest first
            });
            rowArr.forEach(r => tbody.appendChild(r));
        }
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
        }
    });
}

document.addEventListener('DOMContentLoaded', () => {
    const checked = document.querySelector('input[name="dot_color"]:checked');
    if (checked) updateRadioSelection(checked);
});
</script>
