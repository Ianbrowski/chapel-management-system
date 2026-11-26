<!-- ═══════════════════════════════════════════════════════════════ -->
<!-- block html dis ->: MANAGE CHAPELS (MAX-DATA EXPANDED VIEW)        -->
<!-- ═══════════════════════════════════════════════════════════════ -->
<style>
#status-filter-tabs .tab-btn {
    padding: 7px 16px;
    border-radius: 999px;
    border: 1px solid var(--border, #E5E5E5);
    background: var(--panel-bg, #FFFFFF);
    color: var(--text2, #737373);
    font-size: 12.5px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.15s ease;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    line-height: 1.2;
    min-height: 34px;
    box-sizing: border-box;
}
#status-filter-tabs .tab-btn:hover {
    color: var(--text, #111111);
    border-color: var(--text2, #737373);
}
#status-filter-tabs .tab-btn.active {
    background: #111111 !important;
    color: #FFFFFF !important;
    border-color: #111111 !important;
    font-weight: 700;
    box-shadow: 0 1px 3px rgba(0,0,0,0.12);
}
[data-theme="dark"] #status-filter-tabs .tab-btn {
    background: rgba(255, 255, 255, 0.05);
    border-color: rgba(255, 255, 255, 0.14);
    color: #cbd5e1;
}
[data-theme="dark"] #status-filter-tabs .tab-btn:hover {
    background: rgba(255, 255, 255, 0.1);
    color: #ffffff;
    border-color: rgba(255, 255, 255, 0.28);
}
[data-theme="dark"] #status-filter-tabs .tab-btn.active {
    background: #FFFFFF !important;
    color: #111111 !important;
    border-color: #FFFFFF !important;
    font-weight: 700;
    box-shadow: 0 2px 8px rgba(0,0,0,0.3);
}
[data-theme="dark"] #status-filter-tabs .tab-btn.active i {
    color: #111111 !important;
}
</style>

<div class="workspace" style="display: flex; flex-direction: column; gap: 12px; height: 100%; min-height: 0; box-sizing: border-box;">
    
<?php
$totalChapels = count($chapels ?? []);
$activeChapels = count(array_filter($chapels ?? [], fn($c) => ($c['status'] ?? '') === 'Active'));
$inactiveChapels = $totalChapels - $activeChapels;
?>
    <!-- ─── 1. PAGE HEADER (ROW 1: TITLE + STAT BADGES PINNED TOP-RIGHT) ─── -->
    <div class="page-header-bar" style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border); padding-bottom: 8px; flex-shrink: 0; flex-wrap: wrap; gap: 10px; margin-bottom: 6px;">
        <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
            <div class="page-header-icon" style="width: 38px; height: 38px; border-radius: 9px; background: rgba(201,168,76,0.14); color: var(--accent); display: flex; align-items: center; justify-content: center; font-size: 17px; flex-shrink: 0;">
                <i class="fa-solid fa-church"></i>
            </div>
            <div>
                <h1 class="page-title" style="font-family: var(--font-family, 'Inter', sans-serif); font-size: var(--font-page-title, 20px); font-weight: 700; color: var(--text); margin: 0; line-height: 1.2;">
                    <?php echo ($role === 'Chapel Admin') ? 'My Chapel Profile' : 'Chapels'; ?>
                </h1>
                <p class="page-subtitle" style="font-size: var(--font-page-subtitle, 14px); color: var(--text2); margin: 3px 0 0 0;">Parish chapel jurisdictions, leadership contacts, and location assignments</p>
            </div>
        </div>
        <!-- Right Side: Stat Badges Pinned Top-Right -->
        <div style="display: flex; align-items: center; gap: 6px;">
            <span class="badge" style="background: var(--bg3, #F4F4F5); border: 1px solid var(--border); color: var(--text2); font-size: 11.5px; font-weight: 600; padding: 4px 12px; border-radius: 999px;">
                <?php echo $totalChapels; ?> Chapels
            </span>
            <?php if ($inactiveChapels > 0): ?>
            <span class="badge badge-warning" style="font-size: 11.5px; font-weight: 600; padding: 4px 10px; border-radius: 999px;">
                <?php echo $inactiveChapels; ?> Inactive
            </span>
            <?php endif; ?>
        </div>
    </div>

    <!-- ─── 2. SUB-ROW: STATUS TABS LEFT + ACTIONS RIGHT ─── -->
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; margin-bottom: 8px;">
        <div class="chapel-tabs-group" style="display: inline-flex; align-items: center; gap: 2px; background: var(--bg3, #F4F4F5); padding: 3px; border-radius: 999px; border: 1px solid var(--border);">
            <button type="button" class="chapel-status-pill active" onclick="setChapelStatusFilter('', this)" style="border: 1px solid transparent; background: #111111; color: #FFFFFF; font-size: 11.5px; font-weight: 600; padding: 4px 14px; border-radius: 999px; cursor: pointer; transition: all 0.15s ease;">
                All (<?php echo $totalChapels; ?>)
            </button>
            <button type="button" class="chapel-status-pill" onclick="setChapelStatusFilter('recent', this)" style="border: 1px solid transparent; background: transparent; color: var(--text2); font-size: 11.5px; font-weight: 600; padding: 4px 14px; border-radius: 999px; cursor: pointer; transition: all 0.15s ease;" title="Recently registered chapel locations">
                Recently Added
            </button>
            <button type="button" class="chapel-status-pill" onclick="setChapelStatusFilter('active', this)" style="border: 1px solid transparent; background: transparent; color: var(--text2); font-size: 11.5px; font-weight: 600; padding: 4px 14px; border-radius: 999px; cursor: pointer; transition: all 0.15s ease;">
                Active (<?php echo $activeChapels; ?>)
            </button>
            <?php if ($inactiveChapels > 0): ?>
            <button type="button" class="chapel-status-pill" onclick="setChapelStatusFilter('inactive', this)" style="border: 1px solid transparent; background: transparent; color: var(--text2); font-size: 11.5px; font-weight: 600; padding: 4px 14px; border-radius: 999px; cursor: pointer; transition: all 0.15s ease;">
                Inactive (<?php echo $inactiveChapels; ?>)
            </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- ─── EXPANDED MAIN CHAPELS TABLE CARD (MAX DATA VIEW) ─── -->
    <div class="panel" style="border-radius: 12px; overflow: hidden; background: var(--panel-bg); border: 1px solid var(--border); box-shadow: none; display: flex; flex-direction: column; flex: 1; min-height: 0;">
        
        <!-- Compact Filter & Search Toolbar -->
        <div style="padding: 10px 16px; border-bottom: 1px solid var(--border); display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 10px; background: var(--panel-bg); flex-shrink: 0;">
            <div style="display: flex; align-items: center; gap: 8px; flex: 1; min-width: 260px; max-width: 420px;">
                <!-- Universal Sort Toggle Button -->
                <button type="button" class="btn-sort-toggle" onclick="toggleChapelSort(this)"
                        title="Sorted: Name A-Z (Click to sort Z-A)"
                        style="height: 32px; width: 34px; min-width: 34px; padding: 0; display: inline-flex; align-items: center; justify-content: center; border-radius: 6px; border: 1px solid var(--border); background: var(--bg2, #FAFAFA); color: var(--text); cursor: pointer; transition: all 0.15s ease; flex-shrink: 0;" aria-label="Toggle sort order">
                    <i class="fa-solid fa-arrow-down-a-z" style="font-size: 13px;"></i>
                </button>

                <div style="position: relative; flex: 1;">
                    <i class="fa-solid fa-magnifying-glass" style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: var(--text2, #737373); font-size: 11px; pointer-events: none; z-index: 2;"></i>
                    <input type="text" id="chapel-table-search" data-ac-mode="dom" data-ac-target="#chapel-management-table .chapel-table-row" data-ac-extract="chapel" placeholder="Search chapels by name, location, patron saint..." onkeyup="filterChapelTable()" 
                           style="width: 100%; height: 32px; padding-left: 32px !important; padding-right: 24px !important; background: var(--bg2, #FFFFFF); border: 1px solid var(--border); border-radius: 6px; color: var(--text, #111111); font-size: 12px; outline: none; box-sizing: border-box;">
                    <span id="chapel-clear-search" onclick="clearChapelSearch()" title="Clear search" style="display: none; position: absolute; right: 8px; top: 50%; transform: translateY(-50%); cursor: pointer; color: var(--text2); font-size: 14px; line-height: 1;">&times;</span>
                </div>
            </div>

            <div>
                <span class="badge" style="background: var(--bg3, #F4F4F5); color: var(--text); border: 1px solid var(--border); font-size: 11.5px; padding: 4px 10px; border-radius: 999px; font-weight: 600;">
                    <?php echo $totalChapels; ?> Locations Configured
                </span>
            </div>
        </div>

        <!-- Data Table (Expanded Scroll Area Fitting Max Rows) -->
        <div style="overflow-y: auto; overflow-x: auto; flex: 1; width: 100%; min-height: 0;">
            <table class="data-table" id="chapel-management-table" style="width: 100%; min-width: 860px; table-layout: fixed; border-collapse: collapse;">
                <thead style="position: sticky; top: 0; z-index: 10;">
                    <tr style="background: var(--panel-header-bg); border-bottom: 1px solid var(--border);">
                        <th style="width: 60px; text-align: center; padding: 10px 12px; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text2);">Photo</th>
                        <th style="width: auto; padding: 10px 12px; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text2);">Chapel Info</th>
                        <th style="width: 28%; padding: 10px 12px; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text2);">Schedule &amp; Fiestas</th>
                        <th style="width: 25%; padding: 10px 12px; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text2);">Capacity &amp; Coordinator</th>
                        <th style="width: 100px; text-align: center; padding: 10px 12px; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text2);">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($chapels)): ?>
                        <tr>
                            <td colspan="5" style="padding: 50px 20px; text-align: center; color: var(--text2);">
                                <i class="fa-solid fa-church" style="font-size: 42px; opacity: 0.3; margin-bottom: 12px; display: block; color: var(--accent);"></i>
                                <div style="font-size: 16px; font-weight: 700; color: var(--text); margin-bottom: 4px;">No Chapels Registered</div>
                                <div style="font-size: 13px;">Get started by adding your first chapel location.</div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($chapels as $ch): 
                            $is_near_fiesta = $ch['is_near_fiesta'] ?? false;
                        ?>
                        <tr class="chapel-table-row" 
                            data-id="<?php echo (int)$ch['chapel_id']; ?>"
                            data-created="<?php echo !empty($ch['created_at']) ? strtotime($ch['created_at']) : (int)$ch['chapel_id']; ?>"
                            data-name="<?php echo strtolower(htmlspecialchars($ch['chapel_name'] . ' ' . ($ch['location'] ?? '') . ' ' . ($ch['patron_saint'] ?? ''))); ?>" 
                            data-status="<?php echo strtolower($ch['status'] ?? 'active'); ?>" 
                            style="border-bottom: 1px solid var(--border); transition: background 0.15s; <?php echo $is_near_fiesta ? 'background: rgba(201,168,76,0.08);' : ''; ?>">
                            <td style="padding: 10px 12px; text-align: center;">
                                <?php if (!empty($ch['chapel_image'])): ?>
                                    <img src="storage/<?php echo $ch['chapel_image']; ?>" style="width: 40px; height: 40px; object-fit: cover; border-radius: 8px; border: 1px solid var(--border);">
                                <?php else: ?>
                                    <div style="width: 40px; height: 40px; background: var(--bg3); display: flex; align-items: center; justify-content: center; border-radius: 8px; color: var(--text2); border: 1px solid var(--border); margin: 0 auto;">
                                        <i class="fa-solid fa-church" style="font-size: 16px; color: var(--accent);"></i>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 10px 12px; white-space: normal;">
                                <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 2px;">
                                    <span style="display: inline-block; width: 10px; height: 10px; border-radius: 50%; background: <?php echo htmlspecialchars($ch['theme_color'] ?? '#4f46e5'); ?>; flex-shrink: 0;" title="Theme color"></span>
                                    <strong style="font-size: 0.95rem; color: var(--text);"><?php echo htmlspecialchars($ch['chapel_name']); ?></strong>
                                    <?php if ($is_near_fiesta): ?><span title="Fiesta this week!"><i class="fa-solid fa-fire-flame-curved" style="color: var(--accent);"></i></span><?php endif; ?>
                                    <span class="badge badge-<?php echo ($ch['status'] === 'Active') ? 'confirmed' : 'pending'; ?>" style="font-size: 0.7rem; padding: 2px 8px; border-radius: 10px;"><?php echo $ch['status']; ?></span>
                                </div>
                                <div style="font-size: 0.8rem; color: var(--text2); display: flex; align-items: center; gap: 8px;">
                                    <span><i class="fa-solid fa-location-dot" style="color: var(--accent);"></i> <?php echo htmlspecialchars($ch['location'] ?? '—'); ?></span>
                                    <?php if (!empty($ch['patron_saint'])): ?>
                                        <span>·</span>
                                        <span><i class="fa-solid fa-cross" style="color: var(--accent);"></i> <?php echo htmlspecialchars($ch['patron_saint']); ?></span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td style="padding: 10px 12px;">
                                <div style="font-size: 0.85rem; color: var(--text);">
                                    <i class="fa-solid fa-clock" style="color: var(--accent); margin-right: 4px;"></i> <?php echo htmlspecialchars($ch['sunday_service_time'] ?: '—'); ?>
                                </div>
                                <?php if (!empty($ch['fiesta_list'])): ?>
                                <div style="margin-top: 3px; display: flex; flex-wrap: wrap; gap: 4px;">
                                    <?php foreach(explode(', ', $ch['fiesta_list']) as $f): ?>
                                        <span class="badge" style="font-size: 11.5px; background: rgba(255,255,255,0.04); color: var(--text2); border: 1px solid var(--border); padding: 2px 7px; border-radius: 4px;">
                                            <i class="fa-solid fa-cake-candles" style="font-size: 11px; color: var(--accent);"></i> <?php echo htmlspecialchars($f); ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 10px 12px;">
                                <div style="display: flex; align-items: center; gap: 6px; font-size: 0.85rem; color: var(--text); margin-bottom: 2px;">
                                    <i class="fa-solid fa-users-viewfinder" style="color: var(--accent);"></i>
                                    <span><strong><?php echo intval($ch['seating_capacity']); ?></strong> seats capacity</span>
                                </div>
                                <?php if ($ch['coordinator_name']): ?>
                                    <div style="font-size: 0.8rem; color: var(--text2);">
                                        <i class="fa-solid fa-user-tie" style="color: var(--accent); margin-right: 4px;"></i>
                                        <strong><?php echo htmlspecialchars($ch['coordinator_name']); ?></strong>
                                        <?php if (!empty($ch['coordinator_phone'])): ?>
                                            <span>(<?php echo htmlspecialchars($ch['coordinator_phone']); ?>)</span>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <span style="color: var(--text2); font-size: 0.8rem;">No coordinator assigned</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: center; padding: 10px 12px;">
                                <div style="display: flex; align-items: center; justify-content: center; gap: 4px;">
                                    <button class="btn-sm btn-ghost" 
                                            data-chapel="<?php echo htmlspecialchars(json_encode($ch, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>"
                                            onclick="openEditChapelModal(this)" 
                                            title="Edit Chapel Profile" 
                                            style="width: 30px; height: 30px; border-radius: 6px; display: flex; align-items: center; justify-content: center; background: #FFFFFF; border: 1px solid var(--border); color: #111111;">
                                        <i class="fa-solid fa-pen-to-square" style="font-size: 12px;"></i>
                                    </button>
                                    <?php if (in_array($role, ['Admin', 'Cathedral Admin'])): ?>
                                    <form method="POST" style="margin: 0; display: inline;" onsubmit="return confirm('Danger: Are you sure you want to permanently delete this chapel location?')">
                                        <input type="hidden" name="form_section" value="chapels">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo $ch['chapel_id']; ?>">
                                        <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
                                        <button type="submit" class="btn-sm btn-ghost" style="color: #D64545; width: 30px; height: 30px; border-radius: 6px; display: flex; align-items: center; justify-content: center; background: #FFFFFF; border: 1px solid #D64545;" title="Delete Chapel Location">
                                            <i class="fa-solid fa-trash-can" style="font-size: 12px;"></i>
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ─── SLEEK MODULE GUIDELINES FOOTER BANNER ─── -->
    <div style="background: rgba(255,255,255,0.02); border: 1px dashed var(--border); border-radius: 10px; padding: 8px 16px; display: flex; flex-wrap: wrap; align-items: center; justify-content: space-around; gap: 12px; font-size: 0.8rem; color: var(--text2); flex-shrink: 0;">
        <div style="display: flex; align-items: center; gap: 6px;">
            <i class="fa-solid fa-church" style="color: var(--accent); font-size: 12px;"></i>
            <span>Chapel Admins can update their assigned chapel profile and service times.</span>
        </div>
        <div style="display: flex; align-items: center; gap: 6px;">
            <i class="fa-solid fa-cake-candles" style="color: var(--accent); font-size: 12px;"></i>
            <span>Fiesta dates automatically publish to parish portal calendars.</span>
        </div>
        <div style="display: flex; align-items: center; gap: 6px;">
            <i class="fa-solid fa-envelope" style="color: var(--accent); font-size: 12px;"></i>
            <span>Custom SMTP settings enable chapel-branded automated emails.</span>
        </div>
    </div>
</div>

<script>
let _chapelStatusFilter = '';
let _chapelSortAsc = true;

function setChapelStatusFilter(status, btn) {
    _chapelStatusFilter = status;
    document.querySelectorAll('.chapel-status-pill').forEach(b => {
        b.classList.remove('active');
        b.style.background = 'transparent';
        b.style.color = 'var(--text2)';
    });
    if (btn) {
        btn.classList.add('active');
        btn.style.background = '#111111';
        btn.style.color = '#FFFFFF';
    }
    filterChapelTable();
}

function clearChapelSearch() {
    const input = document.getElementById('chapel-table-search');
    if (input) {
        input.value = '';
        filterChapelTable();
        input.focus();
    }
}

function filterChapelTable() {
    const input = document.getElementById('chapel-table-search');
    const term = (input?.value || '').toLowerCase().trim();
    const clearBtn = document.getElementById('chapel-clear-search');
    if (clearBtn) {
        clearBtn.style.display = term ? 'block' : 'none';
    }

    const rows = document.querySelectorAll('#chapel-management-table .chapel-table-row');
    const thirtyDaysAgo = Math.floor(Date.now() / 1000) - (30 * 86400);

    rows.forEach(row => {
        const name = row.dataset.name || '';
        const status = row.dataset.status || '';
        const created = parseInt(row.dataset.created || '0', 10);
        const matchesTerm = !term || name.includes(term);
        const matchesStatus = (!_chapelStatusFilter || (_chapelStatusFilter === 'recent' ? (created >= thirtyDaysAgo || created === 0) : (status === _chapelStatusFilter)));
        row.style.display = (matchesTerm && matchesStatus) ? '' : 'none';
    });

    if (_chapelStatusFilter === 'recent') {
        const tbody = document.querySelector('#chapel-management-table tbody');
        if (tbody) {
            const rowArr = Array.from(tbody.querySelectorAll('.chapel-table-row'));
            rowArr.sort((a, b) => {
                const cA = parseInt(a.dataset.created || a.dataset.id || '0', 10);
                const cB = parseInt(b.dataset.created || b.dataset.id || '0', 10);
                return cB - cA; // Newest first
            });
            rowArr.forEach(r => tbody.appendChild(r));
        }
    }
}

function toggleChapelSort(btn) {
    _chapelSortAsc = !_chapelSortAsc;
    const icon = btn.querySelector('i');
    if (icon) {
        icon.className = _chapelSortAsc ? 'fa-solid fa-arrow-down-a-z' : 'fa-solid fa-arrow-down-z-a';
    }
    btn.title = _chapelSortAsc ? 'Sorted: Name A-Z (Click to sort Z-A)' : 'Sorted: Name Z-A (Click to sort A-Z)';

    const tbody = document.querySelector('#chapel-management-table tbody');
    if (!tbody) return;
    const rows = Array.from(tbody.querySelectorAll('.chapel-table-row'));
    rows.sort((a, b) => {
        const nameA = (a.dataset.name || '').toLowerCase();
        const nameB = (b.dataset.name || '').toLowerCase();
        return _chapelSortAsc ? nameA.localeCompare(nameB) : nameB.localeCompare(nameA);
    });
    rows.forEach(r => tbody.appendChild(r));
}
</script>