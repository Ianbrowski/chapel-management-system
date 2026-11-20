<?php
// ─── Merch stats ─────────────────────────────────────────────────────────────
$totalItems     = count($merchandise_items);
$lowStockItems  = array_filter($merchandise_items, fn($i) => $i['stock_quantity'] <= 5 && $i['stock_quantity'] > 0);
$outOfStock     = array_filter($merchandise_items, fn($i) => $i['stock_quantity'] == 0);
$totalSalesQty  = array_sum(array_column($merchandise_sales ?? [], 'quantity'));
$initialTab     = $_GET['tab'] ?? 'catalog';
if (!in_array($initialTab, ['catalog', 'sales', 'pos', 'archived'], true)) {
    $initialTab = 'catalog';
}
?>
<!-- ═══════════════════════════════════════════════════════════════ -->
<!-- PARISH SHOP & MERCHANDISE — Segmented Operational Hub           -->
<!-- ═══════════════════════════════════════════════════════════════ -->
<link rel="stylesheet" href="assets/css/workspace.css?v=2.5">

<style>
/* Local style overrides for Merchandise Management */
.merch-tab-btn {
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
.merch-tab-btn:hover {
    color: var(--text);
    background: var(--hover-bg, rgba(0,0,0,0.04));
}
.merch-tab-btn.active {
    background: #111111 !important;
    color: #FFFFFF !important;
    border-color: #111111 !important;
    font-weight: 700;
    box-shadow: 0 1px 3px rgba(0,0,0,0.14);
}
.merch-tab-btn.active .tab-badge {
    background: rgba(255,255,255,0.2) !important;
    color: #FFFFFF !important;
}
[data-theme="dark"] .merch-tabs-group {
    background: #18181b !important;
    border-color: rgba(255, 255, 255, 0.12) !important;
}
[data-theme="dark"] .merch-tab-btn {
    color: #cbd5e1;
}
[data-theme="dark"] .merch-tab-btn:hover {
    background: rgba(255,255,255,0.08);
    color: #ffffff;
}
[data-theme="dark"] .merch-tab-btn.active {
    background: #FFFFFF !important;
    color: #111111 !important;
    border-color: #FFFFFF !important;
    font-weight: 700;
    box-shadow: 0 2px 8px rgba(0,0,0,0.3);
}
[data-theme="dark"] .merch-tab-btn.active .tab-badge {
    background: rgba(0,0,0,0.18) !important;
    color: #111111 !important;
}

/* Stock Filter Pills */
.catalog-filter-pill {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 0 12px;
    height: 24px;
    font-size: 11px;
    font-weight: 500;
    border-radius: 999px;
    border: 1px solid var(--border);
    background: var(--panel-bg, #FFFFFF);
    color: var(--text2);
    cursor: pointer;
    transition: all 0.15s ease;
    white-space: nowrap;
    line-height: 1.2;
}
.catalog-filter-pill:hover {
    color: var(--text);
    border-color: var(--text2);
}
.catalog-filter-pill.active {
    background: #111111 !important;
    color: #FFFFFF !important;
    border-color: #111111 !important;
    font-weight: 700;
    box-shadow: 0 1px 3px rgba(0,0,0,0.12);
}
[data-theme="dark"] .catalog-filter-pill {
    background: rgba(255, 255, 255, 0.05);
    border-color: rgba(255, 255, 255, 0.14);
    color: #cbd5e1;
}
[data-theme="dark"] .catalog-filter-pill:hover {
    background: rgba(255, 255, 255, 0.1);
    color: #ffffff;
    border-color: rgba(255, 255, 255, 0.28);
}
[data-theme="dark"] .catalog-filter-pill.active {
    background: #FFFFFF !important;
    color: #111111 !important;
    border-color: #FFFFFF !important;
    font-weight: 700;
    box-shadow: 0 2px 8px rgba(0,0,0,0.3);
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

/* POS Card Grid */
.pos-product-card {
    background: #FFFFFF;
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 10px;
    display: flex;
    flex-direction: column;
    gap: 6px;
    cursor: pointer;
    transition: all 0.15s ease;
    position: relative;
}
.pos-product-card:hover {
    border-color: #111111;
    transform: translateY(-1px);
    box-shadow: 0 2px 6px rgba(0,0,0,0.06);
}
.pos-product-card.selected {
    border-color: #111111;
    background: #FAFAFA;
    outline: 2px solid #111111;
}
.pos-product-card.out-of-stock {
    opacity: 0.55;
    cursor: not-allowed;
    filter: grayscale(0.5);
}

.merch-gallery-card {
    background: #FFFFFF;
    border: 1px solid var(--border);
    border-radius: 12px;
    overflow: hidden;
    transition: border-color 0.15s ease;
}
.merch-gallery-card:hover {
    border-color: #111111;
}
</style>

<div class="workspace workspace-fluid" id="sec-merchandise" style="display: flex; flex-direction: column; gap: 14px; width: 100%; max-width: 1680px; margin: 0 auto; box-sizing: border-box; padding: 14px clamp(16px, 1.8vw, 24px) 24px;">

    <?php if ($isMember): ?>
    <div class="page-header-bar" style="display: flex; justify-content: space-between; align-items: center; padding-bottom: 8px; border-bottom: 1px solid var(--border); flex-wrap: wrap; gap: 10px; margin-bottom: 6px;">
        <div style="display: flex; align-items: center; gap: 14px;">
            <div class="page-header-icon" style="width: 38px; height: 38px; border-radius: 9px; background: rgba(201,168,76,0.14); color: var(--accent); display: flex; align-items: center; justify-content: center; font-size: 17px; flex-shrink: 0;">
                <i class="fa-solid fa-store"></i>
            </div>
            <div>
                <h1 class="page-title" style="font-family: var(--font-family, 'Inter', sans-serif); font-size: var(--font-page-title, 20px); font-weight: 700; color: var(--text); margin: 0; line-height: 1.2;">
                    Parish Store
                </h1>
                <p class="page-subtitle" style="font-size: var(--font-page-subtitle, 14px); color: var(--text2); margin: 3px 0 0 0;">
                    Liturgical items, religious articles, and parish devotional items
                </p>
            </div>
        </div>
    </div>
    <!-- ── Member View: Merchandise Gallery ──────────────────────── -->
    <div class="workspace-content" style="flex: 1; overflow-y: auto;">
        <div class="panel" style="border: 1px solid var(--border); border-radius: 12px; background: var(--panel-bg); overflow: hidden;">
            <div class="panel-header" style="padding: 8px 14px; border-bottom: 1px solid var(--border);">
                <div class="panel-title" style="font-size: 12px; font-weight: 600;">
                    <i class="fa-solid fa-store" style="color: var(--accent);"></i>
                    <span>Parish Merchandise Catalog</span>
                </div>
            </div>
            <div class="panel-body" style="padding: 14px;">
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 14px;">
                    <?php if (empty($merchandise_items)): ?>
                        <div style="grid-column: 1/-1; text-align: center; padding: 50px 20px; color: var(--text2);">
                            <i class="fa-solid fa-store-slash" style="font-size: 40px; opacity: 0.25; margin-bottom: 10px; display: block;"></i>
                            <div style="font-size: 15px; font-weight: 600; color: var(--text);">No items currently available.</div>
                            <div style="font-size: 12px;">Please check back later for parish merchandise updates.</div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($merchandise_items as $item): ?>
                        <div class="merch-gallery-card">
                            <div style="height: 180px; background: #F4F4F5; position: relative; overflow: hidden;">
                                <?php if (!empty($item['photo_path'])): ?>
                                    <img src="<?php echo htmlspecialchars($item['photo_path']); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                                <?php else: ?>
                                    <div style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; color: #A1A1AA; font-size: 56px;">
                                        <i class="fa-solid fa-box-open"></i>
                                    </div>
                                <?php endif; ?>
                                <div style="position: absolute; top: 10px; right: 10px; background: #111111; color: #FFFFFF; padding: 3px 9px; border-radius: 20px; font-weight: 700; font-size: 11.5px; box-shadow: 0 2px 4px rgba(0,0,0,0.15);">
                                    ₱<?php echo number_format($item['price'], 2); ?>
                                </div>
                            </div>
                            <div style="padding: 12px;">
                                <div style="font-size: 14px; font-weight: 700; margin-bottom: 4px; color: var(--text);"><?php echo htmlspecialchars($item['name']); ?></div>
                                <p style="font-size: 11.5px; color: var(--text2); margin-bottom: 10px; line-height: 1.4; height: 32px; overflow: hidden; text-overflow: ellipsis; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;">
                                    <?php echo htmlspecialchars($item['description'] ?? 'Parish religious merchandise item.'); ?>
                                </p>
                                <div style="display: flex; justify-content: space-between; align-items: center; padding-top: 8px; border-top: 1px solid var(--border);">
                                    <span style="font-size: 11px; color: var(--text2); font-weight: 500;">
                                        <?php if ($item['stock_quantity'] == 0): ?>
                                            <span style="color: #dc2626; font-weight: 700;"><i class="fa-solid fa-circle-xmark"></i> Out of Stock</span>
                                        <?php elseif ($item['stock_quantity'] <= 5): ?>
                                            <span style="color: #dc2626; font-weight: 700;"><i class="fa-solid fa-triangle-exclamation"></i> Only <?php echo $item['stock_quantity']; ?> left</span>
                                        <?php else: ?>
                                            <span style="color: #16a34a; font-weight: 600;"><i class="fa-solid fa-boxes-stacked"></i> Stock: <?php echo $item['stock_quantity']; ?></span>
                                        <?php endif; ?>
                                    </span>

                                    <?php if ($item['stock_quantity'] > 0): ?>
                                    <button class="btn btn-primary btn-sm" 
                                            data-item="<?php echo htmlspecialchars(json_encode($item, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>"
                                            onclick="openMemberReserveModal(this)" 
                                            style="height: 28px; font-size: 11px; padding: 0 12px;">
                                        <i class="fa-solid fa-cart-plus"></i> Reserve
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php else: ?>
    <!-- ── 1. UNIFIED COMMAND HEADER: TITLE, BADGES, TABS, AND ACTIONS ── -->
    <div class="page-header-bar" style="display: flex; justify-content: space-between; align-items: center; padding-bottom: 8px; border-bottom: 1px solid var(--border); flex-wrap: wrap; gap: 10px; margin-bottom: 6px;">
        <div style="display: flex; align-items: center; gap: 14px; flex-wrap: wrap;">
            <div class="page-header-icon" style="width: 38px; height: 38px; border-radius: 9px; background: rgba(201,168,76,0.14); color: var(--accent); display: flex; align-items: center; justify-content: center; font-size: 17px; flex-shrink: 0;">
                <i class="fa-solid fa-store"></i>
            </div>
            <div>
                <h1 class="page-title" style="font-family: var(--font-family, 'Inter', sans-serif); font-size: var(--font-page-title, 20px); font-weight: 700; color: var(--text); margin: 0; line-height: 1.2;">
                    Merchandise
                </h1>
                <p class="page-subtitle" style="font-size: var(--font-page-subtitle, 14px); color: var(--text2); margin: 3px 0 0 0;">
                    Inventory catalog, sales ledger, and parish point-of-sale terminal
                </p>
            </div>
        </div>

        <!-- Right Side: Stat Badges (always top-right) -->
        <div style="display: flex; align-items: center; gap: 6px;">
            <span class="badge" style="background: var(--bg3, #F4F4F5); border: 1px solid var(--border); color: var(--text2); font-size: 10.5px; font-weight: 600; padding: 2px 10px; border-radius: 999px;">
                <?php echo number_format($totalItems); ?> Items
            </span>
            <?php if (!empty($lowStockItems)): ?>
            <span class="badge badge-warning" style="font-size: 10.5px; font-weight: 600; padding: 2px 8px; border-radius: 999px;" title="<?php echo count($lowStockItems); ?> item(s) running low on inventory">
                <?php echo count($lowStockItems); ?> Low Stock
            </span>
            <?php elseif (!empty($outOfStock)): ?>
            <span class="badge badge-danger" style="font-size: 10.5px; font-weight: 600; padding: 2px 8px; border-radius: 999px;" title="<?php echo count($outOfStock); ?> item(s) out of stock">
                <?php echo count($outOfStock); ?> Out of Stock
            </span>
            <?php endif; ?>
        </div>
    </div>

    <!-- ── 2. SUB-ROW: TABS LEFT + ACTIONS RIGHT ── -->
    <div class="merch-tabs-bar" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 6px; margin-bottom: 4px;">
        <div style="display: flex; align-items: center; gap: 6px; flex-wrap: wrap;">
            <div class="merch-tabs-group" style="display: inline-flex; align-items: center; gap: 3px; background: var(--bg3, #F4F4F5); padding: 2px; border-radius: 999px; border: 1px solid var(--border); flex-wrap: wrap;">
                <button type="button" class="merch-tab-btn <?php echo ($initialTab === 'catalog') ? 'active' : ''; ?>" data-tab="catalog" onclick="switchMerchTab('catalog')">
                    <i class="fa-solid fa-boxes-stacked"></i> Catalog
                    <span class="tab-badge"><?php echo count($merchandise_items); ?></span>
                </button>
                <button type="button" class="merch-tab-btn <?php echo ($initialTab === 'sales') ? 'active' : ''; ?>" data-tab="sales" onclick="switchMerchTab('sales')">
                    <i class="fa-solid fa-receipt"></i> Sales
                    <span class="tab-badge"><?php echo count($merchandise_sales ?? []); ?></span>
                </button>
                <button type="button" class="merch-tab-btn <?php echo ($initialTab === 'pos') ? 'active' : ''; ?>" data-tab="pos" onclick="switchMerchTab('pos')">
                    <i class="fa-solid fa-cash-register"></i> POS
                </button>
                <?php if (!empty($archived_merchandise_items)): ?>
                <button type="button" class="merch-tab-btn <?php echo ($initialTab === 'archived') ? 'active' : ''; ?>" data-tab="archived" onclick="switchMerchTab('archived')">
                    <i class="fa-solid fa-box-archive"></i> Archive
                    <span class="tab-badge" style="background: rgba(100,116,139,0.15); color: #64748b;"><?php echo count($archived_merchandise_items); ?></span>
                </button>
                <?php endif; ?>
            </div>
        </div>

        <div style="display: flex; align-items: center; gap: 6px;">
            <button type="button" class="btn btn-primary btn-sm" onclick="openModal('modal-add-merch-item')" 
                    style="height: 28px; font-weight: 600; font-size: 11.5px; display: inline-flex; align-items: center; gap: 5px; background: #111111; color: #FFFFFF; border: 1px solid #111111; border-radius: 999px; padding: 0 13px; cursor: pointer; box-shadow: 0 1px 3px rgba(0,0,0,0.12);">
                <i class="fa-solid fa-plus"></i> Add Item
            </button>
        </div>
    </div>

    <!-- ── TAB 1: INVENTORY CATALOG (Full-Width High-Data View) ── -->
    <div id="merch-view-catalog" class="merch-tab-pane" style="display: <?php echo ($initialTab === 'catalog') ? 'flex' : 'none'; ?>; flex-direction: column; flex: 1; min-height: 0;">
        <div class="panel" style="border-radius: 10px; overflow: hidden; background: #FFFFFF; border: 1px solid var(--border); display: flex; flex-direction: column; flex: 1; min-height: 0;">
            
            <!-- Filter Toolbar -->
            <div style="padding: 10px 16px; border-bottom: 1px solid var(--border); display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 10px; background: #FFFFFF; flex-shrink: 0;">
                <div style="display: flex; align-items: center; gap: 8px; flex: 1; min-width: 260px; max-width: 420px;">
                    <!-- Universal Sort Toggle Button -->
                    <button type="button" class="btn-sort-toggle" onclick="toggleCatalogSort(this)"
                            title="Sorted: Name A-Z (Click to sort Z-A)"
                            style="height: 32px; width: 34px; min-width: 34px; padding: 0; display: inline-flex; align-items: center; justify-content: center; border-radius: 6px; border: 1px solid var(--border); background: var(--bg2, #FAFAFA); color: var(--text); cursor: pointer; transition: all 0.15s ease; flex-shrink: 0;" aria-label="Toggle sort order">
                        <i class="fa-solid fa-arrow-down-a-z" style="font-size: 13px;"></i>
                    </button>

                    <div style="position: relative; flex: 1;">
                        <i class="fa-solid fa-magnifying-glass" style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: var(--text2, #71717A); font-size: 11px; pointer-events: none; z-index: 2;"></i>
                        <input type="text" id="catalog-search-input" data-ac-mode="dom" data-ac-target="#catalog-data-table .catalog-row" data-ac-extract="catalog" placeholder="Search item name or description..." oninput="filterCatalogTable()" 
                               style="width: 100%; height: 32px; padding-left: 32px !important; padding-right: 24px !important; font-size: 12px; border: 1px solid var(--border); border-radius: 6px; background: var(--bg2, #FFFFFF); color: var(--text); box-sizing: border-box; outline: none;">
                        <span id="catalog-clear-search" onclick="clearCatalogSearch()" title="Clear search" style="display: none; position: absolute; right: 8px; top: 50%; transform: translateY(-50%); cursor: pointer; color: var(--text2); font-size: 14px; line-height: 1;">&times;</span>
                    </div>
                </div>

                <div class="merch-stock-tabs" style="display: inline-flex; align-items: center; background: var(--bg3, #F4F4F5); padding: 3px; border-radius: 999px; border: 1px solid var(--border); gap: 2px;">
                    <button type="button" class="catalog-filter-pill active" onclick="setCatalogStockFilter('all', this)" style="border: 1px solid transparent; background: #111111; color: #FFFFFF; font-size: 11.5px; font-weight: 600; padding: 4px 14px; border-radius: 999px; cursor: pointer; transition: all 0.15s ease;">All</button>
                    <button type="button" class="catalog-filter-pill" onclick="setCatalogStockFilter('recent', this)" style="border: 1px solid transparent; background: transparent; color: var(--text2); font-size: 11.5px; font-weight: 600; padding: 4px 14px; border-radius: 999px; cursor: pointer; transition: all 0.15s ease;" title="Items added in the last 30 days">Recently Added</button>
                    <button type="button" class="catalog-filter-pill" onclick="setCatalogStockFilter('in', this)" style="border: 1px solid transparent; background: transparent; color: var(--text2); font-size: 11.5px; font-weight: 600; padding: 4px 14px; border-radius: 999px; cursor: pointer; transition: all 0.15s ease;">In Stock</button>
                    <button type="button" class="catalog-filter-pill" onclick="setCatalogStockFilter('low', this)" style="border: 1px solid transparent; background: transparent; color: var(--text2); font-size: 11.5px; font-weight: 600; padding: 4px 14px; border-radius: 999px; cursor: pointer; transition: all 0.15s ease;">Low Stock</button>
                    <button type="button" class="catalog-filter-pill" onclick="setCatalogStockFilter('out', this)" style="border: 1px solid transparent; background: transparent; color: var(--text2); font-size: 11.5px; font-weight: 600; padding: 4px 14px; border-radius: 999px; cursor: pointer; transition: all 0.15s ease;">Out of Stock</button>
                </div>
            </div>

            <!-- Table Body -->
            <div style="padding: 0; overflow-y: auto; flex: 1; min-height: 0; width: 100%;">
                <table class="data-table" id="catalog-data-table" style="font-size: 12.5px; width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr>
                            <th style="padding: 8px 14px; width: 50px; text-align: center;">Photo</th>
                            <th style="padding: 8px 14px; text-align: left;">Item Details</th>
                            <th style="padding: 8px 14px; width: 120px; text-align: right;">Unit Price</th>
                            <th style="padding: 8px 14px; width: 130px; text-align: center;">Stock Status</th>
                            <th style="padding: 8px 14px; width: 140px; text-align: center;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($merchandise_items)): ?>
                            <tr><td colspan="5" style="padding: 40px; text-align: center; color: var(--text2); font-size: 13px;">No merchandise items recorded yet. Click <strong>Add Item</strong> to start.</td></tr>
                        <?php else: ?>
                            <?php foreach ($merchandise_items as $item): ?>
                            <?php 
                                $stockType = ($item['stock_quantity'] == 0) ? 'out' : (($item['stock_quantity'] <= 5) ? 'low' : 'in');
                            ?>
                            <tr class="catalog-row" data-id="<?php echo (int)$item['id']; ?>" data-created="<?php echo !empty($item['created_at']) ? strtotime($item['created_at']) : 0; ?>" data-stock-type="<?php echo $stockType; ?>" data-name="<?php echo strtolower(htmlspecialchars($item['name'])); ?>" data-desc="<?php echo strtolower(htmlspecialchars($item['description'] ?? '')); ?>">
                                <td style="padding: 8px 14px; text-align: center;">
                                    <?php if (!empty($item['photo_path'])): ?>
                                        <img src="<?php echo htmlspecialchars($item['photo_path']); ?>" style="width: 38px; height: 38px; border-radius: 6px; object-fit: cover; border: 1px solid var(--border); display: inline-block;">
                                    <?php else: ?>
                                        <div style="width: 38px; height: 38px; border-radius: 6px; background: #F4F4F5; border: 1px solid var(--border); display: inline-flex; align-items: center; justify-content: center; color: #A1A1AA;">
                                            <i class="fa-solid fa-box" style="font-size: 14px;"></i>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 8px 14px;">
                                    <div style="font-weight: 700; font-size: 13px; color: var(--text); line-height: 1.3;"><?php echo htmlspecialchars($item['name']); ?></div>
                                    <div style="font-size: 11px; color: var(--text2); margin-top: 2px; line-height: 1.3; max-width: 550px;"><?php echo htmlspecialchars($item['description'] ?? 'Parish religious merchandise item.'); ?></div>
                                </td>
                                <td style="padding: 8px 14px; font-weight: 700; color: #111111; font-size: 13px; text-align: right; white-space: nowrap;">
                                    ₱<?php echo number_format($item['price'], 2); ?>
                                </td>
                                <td style="padding: 8px 14px; text-align: center;">
                                    <?php if ($item['stock_quantity'] == 0): ?>
                                        <span class="badge" style="background: rgba(239,68,68,0.1); color: #dc2626; border: 1px solid rgba(239,68,68,0.3); font-size: 11px; padding: 3px 8px; border-radius: 12px; font-weight: 700;">
                                            <i class="fa-solid fa-circle-xmark"></i> Out of Stock
                                        </span>
                                    <?php elseif ($item['stock_quantity'] <= 5): ?>
                                        <span class="badge" style="background: rgba(220,38,38,0.1); color: #dc2626; border: 1px solid rgba(220,38,38,0.25); font-size: 11px; padding: 3px 8px; border-radius: 12px; font-weight: 700;">
                                            <i class="fa-solid fa-triangle-exclamation"></i> <?php echo $item['stock_quantity']; ?> units left
                                        </span>
                                    <?php else: ?>
                                        <span class="badge" style="background: rgba(16,185,129,0.1); color: #059669; border: 1px solid rgba(16,185,129,0.3); font-size: 11px; padding: 3px 8px; border-radius: 12px; font-weight: 600;">
                                            <i class="fa-solid fa-boxes-stacked"></i> <?php echo $item['stock_quantity']; ?> in stock
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 8px 14px; text-align: center;">
                                    <div class="btn-group" style="justify-content: center; gap: 4px;">
                                        <?php if ($item['stock_quantity'] > 0): ?>
                                        <button class="btn-sm btn-ghost" title="Record Sale for this Item" onclick='selectItemForPOS(<?php echo $item['id']; ?>, <?php echo json_encode($item['name']); ?>, <?php echo $item['price']; ?>, <?php echo $item['stock_quantity']; ?>)' style="width: 30px; height: 30px; padding: 0; color: #059669; border: 1px solid rgba(16,185,129,0.3); border-radius: 6px;">
                                            <i class="fa-solid fa-cart-shopping" style="font-size: 12px;"></i>
                                        </button>
                                        <?php endif; ?>
                                        <button class="btn-sm btn-ghost" 
                                                title="Edit Item" 
                                                data-item="<?php echo htmlspecialchars(json_encode($item, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>"
                                                onclick="editMerchItem(this)" 
                                                style="width: 30px; height: 30px; padding: 0; color: var(--accent); border: 1px solid var(--border); border-radius: 6px;">
                                            <i class="fa-solid fa-pen-to-square" style="font-size: 12px;"></i>
                                        </button>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Archive this merchandise item?')">
                                            <input type="hidden" name="form_section" value="merchandise">
                                            <input type="hidden" name="action" value="del_merch">
                                            <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
                                            <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
                                            <button type="submit" class="btn-sm btn-ghost" style="color: #ef4444; width: 30px; height: 30px; padding: 0; border: 1px solid rgba(239,68,68,0.25); border-radius: 6px;" title="Archive Item">
                                                <i class="fa-solid fa-box-archive" style="font-size: 12px;"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ── TAB 2: SALES TRANSACTIONS LEDGER (Full-Width View) ── -->
    <div id="merch-view-sales" class="merch-tab-pane" style="display: <?php echo ($initialTab === 'sales') ? 'flex' : 'none'; ?>; flex-direction: column; flex: 1; min-height: 0;">
        <div class="panel" style="border-radius: 10px; overflow: hidden; background: #FFFFFF; border: 1px solid var(--border); display: flex; flex-direction: column; flex: 1; min-height: 0;">
            
            <!-- Filter Toolbar -->
            <div style="padding: 10px 16px; border-bottom: 1px solid var(--border); display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 10px; background: #FFFFFF; flex-shrink: 0;">
                <div style="display: flex; align-items: center; gap: 8px; flex: 1; min-width: 260px; max-width: 420px;">
                    <!-- Universal Sort Toggle Button -->
                    <button type="button" class="btn-sort-toggle" onclick="toggleSalesSort(this)"
                            title="Sorted: Newest First (Click to sort Oldest first)"
                            style="height: 32px; width: 34px; min-width: 34px; padding: 0; display: inline-flex; align-items: center; justify-content: center; border-radius: 6px; border: 1px solid var(--border); background: var(--bg2, #FAFAFA); color: var(--text); cursor: pointer; transition: all 0.15s ease; flex-shrink: 0;" aria-label="Toggle sort order">
                        <i class="fa-solid fa-arrow-down-wide-short" style="font-size: 13px;"></i>
                    </button>

                    <div style="position: relative; flex: 1;">
                        <i class="fa-solid fa-magnifying-glass" style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: var(--text2, #71717A); font-size: 11px; pointer-events: none; z-index: 2;"></i>
                        <input type="text" id="sales-search-input" data-ac-mode="dom" data-ac-target="#sales-data-table .sales-row" data-ac-extract="sale" placeholder="Search by buyer name or item..." oninput="filterSalesTable()" 
                               style="width: 100%; height: 32px; padding-left: 32px !important; padding-right: 24px !important; font-size: 12px; border: 1px solid var(--border); border-radius: 6px; background: var(--bg2, #FFFFFF); color: var(--text); box-sizing: border-box; outline: none;">
                        <span id="sales-clear-search" onclick="clearSalesSearch()" title="Clear search" style="display: none; position: absolute; right: 8px; top: 50%; transform: translateY(-50%); cursor: pointer; color: var(--text2); font-size: 14px; line-height: 1;">&times;</span>
                    </div>
                </div>
                <div>
                    <span class="badge" style="background: var(--bg3, #F4F4F5); color: var(--text); border: 1px solid var(--border); font-size: 11.5px; padding: 4px 10px; border-radius: 999px; font-weight: 600;">
                        <?php echo count($merchandise_sales ?? []); ?> Transactions Recorded
                    </span>
                </div>
            </div>

            <!-- Table Body -->
            <div style="padding: 0; overflow-y: auto; flex: 1; min-height: 0; width: 100%;">
                <table class="data-table" id="sales-data-table" style="font-size: 12.5px; width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr>
                            <th style="padding: 8px 14px; width: 140px; text-align: left;">Date &amp; Time</th>
                            <th style="padding: 8px 14px; text-align: left;">Item Purchased</th>
                            <th style="padding: 8px 14px; width: 220px; text-align: left;">Buyer</th>
                            <th style="padding: 8px 14px; width: 70px; text-align: center;">Qty</th>
                            <th style="padding: 8px 14px; width: 120px; text-align: right;">Total Amount</th>
                            <th style="padding: 8px 14px; width: 110px; text-align: center;">Receipt</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($merchandise_sales)): ?>
                            <tr><td colspan="6" style="padding: 40px; text-align: center; color: var(--text2); font-size: 13px;">No sales recorded yet. Click <strong>New Sale (POS)</strong> to create one.</td></tr>
                        <?php else: ?>
                            <?php foreach ($merchandise_sales as $sale): ?>
                            <tr class="sales-row" 
                                data-item="<?php echo strtolower(htmlspecialchars($sale['item_name'])); ?>" 
                                data-buyer="<?php echo strtolower(htmlspecialchars($sale['buyer_name'] ?? 'walk-in')); ?>" 
                                data-date="<?php echo strtotime($sale['sale_date']); ?>" 
                                data-amount="<?php echo (float)$sale['total_price']; ?>"
                                data-sale="<?php echo htmlspecialchars(json_encode($sale, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>"
                                style="cursor: pointer; transition: background 0.15s;" 
                                onclick="viewMerchSaleDetails(this)">
                                <td style="padding: 8px 14px; white-space: nowrap;">
                                    <div style="font-size: 12px; font-weight: 600; color: var(--text);"><?php echo date('M d, Y', strtotime($sale['sale_date'])); ?></div>
                                    <div style="font-size: 10.5px; color: var(--text2);"><?php echo date('h:i A', strtotime($sale['sale_date'])); ?></div>
                                </td>
                                <td style="padding: 8px 14px;">
                                    <div style="font-weight: 700; font-size: 13px; color: var(--text); line-height: 1.3;"><?php echo htmlspecialchars($sale['item_name']); ?></div>
                                </td>
                                <td style="padding: 8px 14px;">
                                    <span style="font-size: 12px; font-weight: 500; color: var(--text);">
                                        <i class="fa-solid fa-user" style="color: var(--text2); margin-right: 4px; font-size: 11px;"></i>
                                        <?php echo htmlspecialchars($sale['buyer_name'] ?: 'Walk-in Customer'); ?>
                                    </span>
                                </td>
                                <td style="padding: 8px 14px; text-align: center; font-weight: 700; color: var(--text);">
                                    <?php echo (int)$sale['quantity']; ?>
                                </td>
                                <td style="padding: 8px 14px; font-weight: 700; color: #111111; text-align: right; white-space: nowrap; font-size: 13px;">
                                    ₱<?php echo number_format($sale['total_price'], 2); ?>
                                </td>
                                <td style="padding: 8px 14px; text-align: center;" onclick="event.stopPropagation()">
                                    <a href="views/pages/merchandise_receipt.php?id=<?php echo $sale['id']; ?>" target="_blank" class="btn-sm btn-ghost" style="color: var(--accent); border: 1px solid var(--border); border-radius: 6px; padding: 4px 8px; text-decoration: none; font-size: 11px; display: inline-flex; align-items: center; gap: 4px;" title="Print Receipt">
                                        <i class="fa-solid fa-print"></i> Receipt
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if (($sales_pages ?? 0) > 1): ?>
            <div class="panel-footer" style="display: flex; justify-content: space-between; align-items: center; padding: 8px 16px; border-top: 1px solid var(--border); background: #FFFFFF;">
                <div>
                    <?php
                    $cp = max(1, (int) ($sales_current ?? 1));
                    $tp = max(1, (int) ($sales_pages ?? 1));
                    $base = '?section=merchandise&tab=sales';
                    ?>
                    <?php if ($cp > 1): ?>
                        <a href="<?php echo $base; ?>&page=<?php echo $cp - 1; ?>" class="btn-sm btn-ghost"><i class="fa-solid fa-arrow-left"></i> Prev</a>
                    <?php endif; ?>
                </div>
                <span style="font-size: 11.5px; color: var(--text2);">Page <?php echo $cp; ?> of <?php echo $tp; ?></span>
                <div>
                    <?php if ($cp < $tp): ?>
                        <a href="<?php echo $base; ?>&page=<?php echo $cp + 1; ?>" class="btn-sm btn-ghost">Next <i class="fa-solid fa-arrow-right"></i></a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ── TAB 3: POINT OF SALE (POS) CASHIER TERMINAL ── -->
    <div id="merch-view-pos" class="merch-tab-pane" style="display: <?php echo ($initialTab === 'pos') ? 'flex' : 'none'; ?>; flex: 1; min-height: 0;">
        <div style="display: grid; grid-template-columns: 1.25fr 0.95fr; gap: 14px; width: 100%; flex: 1; min-height: 0;">
            
            <!-- LEFT (60%): Interactive Product Catalog Selector -->
            <div class="panel" style="border-radius: 10px; overflow: hidden; background: var(--surface); border: 1px solid var(--border); display: flex; flex-direction: column; min-height: 0;">
                <div class="panel-header" style="padding: 10px 14px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; background: var(--surface); flex-shrink: 0;">
                    <div style="font-size: 13px; font-weight: 700; color: var(--text); display: flex; align-items: center; gap: 6px;">
                        <i class="fa-solid fa-layer-group" style="color: var(--accent);"></i> Click an Item to Sell
                    </div>
                    <div style="position: relative; width: 220px;">
                        <i class="fa-solid fa-magnifying-glass" style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: var(--text2, #71717A); font-size: 11px; pointer-events: none; z-index: 2;"></i>
                        <input type="text" id="pos-product-search" data-ac-mode="dom" data-ac-target="#pos-product-grid .pos-product-card" data-ac-extract="pos" placeholder="Quick find product..." oninput="filterPosProductGrid()" 
                               style="width: 100%; height: 32px; padding-left: 32px !important; padding-right: 24px !important; font-size: 12px; border: 1px solid var(--border); border-radius: 6px; outline: none; background: var(--bg2, #FAFAFA); color: var(--text); box-sizing: border-box;">
                        <span id="pos-clear-search" onclick="clearPosSearch()" title="Clear search" style="display: none; position: absolute; right: 8px; top: 50%; transform: translateY(-50%); cursor: pointer; color: var(--text2); font-size: 14px; line-height: 1;">&times;</span>
                    </div>
                </div>

                <div class="panel-body" style="padding: 14px; overflow-y: auto; flex: 1; min-height: 0;">
                    <?php
                    // Top in-stock products for quick 1-click ringing
                    $quickPicks = [];
                    foreach ($merchandise_items as $mi) {
                        if ($mi['stock_quantity'] > 0) {
                            $quickPicks[] = $mi;
                            if (count($quickPicks) >= 5) break;
                        }
                    }
                    ?>
                    <?php if (!empty($quickPicks)): ?>
                    <div class="pos-quick-shelf" style="display: flex; gap: 8px; overflow-x: auto; padding-bottom: 10px; margin-bottom: 12px; border-bottom: 1px solid var(--border);">
                        <span style="font-size: 11px; font-weight: 700; color: var(--text2); display: flex; align-items: center; gap: 4px; white-space: nowrap;">
                            <i class="fa-solid fa-bolt" style="color: var(--accent);"></i> Quick Picks:
                        </span>
                        <?php foreach ($quickPicks as $qp): ?>
                        <button type="button" class="pos-quick-pick-btn" 
                                onclick='selectItemForPOS(<?php echo $qp['id']; ?>, <?php echo json_encode($qp['name']); ?>, <?php echo $qp['price']; ?>, <?php echo $qp['stock_quantity']; ?>)'
                                style="display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; border-radius: 16px; border: 1px solid var(--border); background: var(--bg2); color: var(--text); font-size: 11.5px; font-weight: 600; cursor: pointer; white-space: nowrap; transition: all 0.15s ease;">
                            <span><?php echo htmlspecialchars($qp['name']); ?></span>
                            <span style="background: rgba(201,168,76,0.18); color: var(--accent); padding: 1px 6px; border-radius: 8px; font-size: 10px; font-weight: 700;">₱<?php echo number_format($qp['price'], 2); ?></span>
                        </button>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <div id="pos-product-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 10px;">
                        <?php foreach ($merchandise_items as $item): ?>
                        <?php $isOutOfStock = ($item['stock_quantity'] <= 0); ?>
                        <div class="pos-product-card <?php echo $isOutOfStock ? 'out-of-stock' : ''; ?>" 
                             data-id="<?php echo $item['id']; ?>"
                             data-name="<?php echo strtolower(htmlspecialchars($item['name'])); ?>"
                             <?php if (!$isOutOfStock): ?>
                             onclick='selectItemForPOS(<?php echo $item['id']; ?>, <?php echo json_encode($item['name']); ?>, <?php echo $item['price']; ?>, <?php echo $item['stock_quantity']; ?>)'
                             <?php endif; ?>>
                            
                            <div style="height: 100px; background: #F4F4F5; border-radius: 6px; overflow: hidden; position: relative;">
                                <?php if (!empty($item['photo_path'])): ?>
                                    <img src="<?php echo htmlspecialchars($item['photo_path']); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                                <?php else: ?>
                                    <div style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; color: #A1A1AA; font-size: 32px;">
                                        <i class="fa-solid fa-box"></i>
                                    </div>
                                <?php endif; ?>
                                <div style="position: absolute; bottom: 6px; right: 6px; background: rgba(17,17,17,0.9); color: #FFFFFF; padding: 2px 8px; border-radius: 12px; font-weight: 700; font-size: 11px;">
                                    ₱<?php echo number_format($item['price'], 2); ?>
                                </div>
                            </div>

                            <div style="display: flex; flex-direction: column; gap: 2px;">
                                <div style="font-weight: 700; font-size: 12px; color: var(--text); line-height: 1.25; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                    <?php echo htmlspecialchars($item['name']); ?>
                                </div>
                                <div style="font-size: 10.5px; color: var(--text2);">
                                    <?php if ($isOutOfStock): ?>
                                        <span style="color: #ef4444; font-weight: 700;"><i class="fa-solid fa-xmark"></i> Out of stock</span>
                                    <?php elseif ($item['stock_quantity'] <= 5): ?>
                                        <span style="color: #dc2626; font-weight: 700;"><i class="fa-solid fa-triangle-exclamation"></i> Only <?php echo $item['stock_quantity']; ?> left</span>
                                    <?php else: ?>
                                        <span style="color: #059669; font-weight: 600;"><?php echo $item['stock_quantity']; ?> in stock</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- RIGHT (40%): POS Checkout Cashier Register -->
            <div class="panel" style="border-radius: 10px; overflow: hidden; background: var(--surface); border: 1px solid var(--border); display: flex; flex-direction: column; min-height: 0;">
                <div class="panel-header" style="padding: 10px 14px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; background: var(--surface); flex-shrink: 0;">
                    <div style="font-size: 13px; font-weight: 700; color: var(--text); display: flex; align-items: center; gap: 6px;">
                        <i class="fa-solid fa-cash-register" style="color: var(--accent);"></i> Cashier Checkout Slip
                    </div>
                    <span style="font-size: 11px; color: var(--text2);"><i class="fa-solid fa-bolt" style="color: var(--accent);"></i> Fast POS <kbd style="font-size: 9px; padding: 1px 4px; background: var(--bg2); border: 1px solid var(--border); border-radius: 4px; color: var(--text2);">F2 Search</kbd></span>
                </div>

                <div class="panel-body" style="padding: 12px 14px; overflow-y: auto; flex: 1; display: flex; flex-direction: column;">
                    <form method="POST" id="pos-checkout-form" style="display: flex; flex-direction: column; gap: 10px; flex: 1;">
                        <input type="hidden" name="form_section" value="merchandise">
                        <input type="hidden" name="action" value="record_sale">
                        <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
                        <input type="hidden" name="inquiry_id" id="sale-inquiry-id" value="">

                        <!-- Selected Item Dropdown -->
                        <div>
                            <label style="font-size: 10.5px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; color: var(--text2); display: block; margin-bottom: 3px;">
                                Selected Product <span style="color:#ef4444;">*</span>
                            </label>
                            <select name="item_id" required id="sale-item-select" onchange="onSelectItemDropdown()" 
                                    style="width: 100%; font-size: 12px; padding: 6px 10px; border: 1px solid var(--border); border-radius: 6px; background: var(--bg2); color: var(--text); font-weight: 600;">
                                <option value="">— Select an Item from catalog —</option>
                                <?php foreach ($merchandise_items as $item): ?>
                                    <?php if ($item['stock_quantity'] > 0): ?>
                                    <option value="<?php echo $item['id']; ?>" data-price="<?php echo $item['price']; ?>" data-stock="<?php echo $item['stock_quantity']; ?>" data-name="<?php echo htmlspecialchars($item['name']); ?>">
                                        <?php echo htmlspecialchars($item['name']); ?> — ₱<?php echo number_format($item['price'], 2); ?> (<?php echo $item['stock_quantity']; ?> available)
                                    </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                            <div id="pos-stock-hint" style="font-size: 10.5px; color: var(--text2); margin-top: 3px;">
                                Choose an item above or click one on the left.
                            </div>
                        </div>

                        <!-- Quantity Stepper -->
                        <div>
                            <label style="font-size: 10.5px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; color: var(--text2); display: block; margin-bottom: 3px;">
                                Quantity <span style="color:#ef4444;">*</span>
                            </label>
                            <div style="display: flex; align-items: center; gap: 6px;">
                                <button type="button" class="btn-sm btn-ghost" onclick="stepQuantity(-1)" style="width: 32px; height: 32px; border: 1px solid var(--border); border-radius: 6px; font-size: 15px; font-weight: 700; display: flex; align-items: center; justify-content: center; background: var(--bg2); color: var(--text); cursor: pointer;">−</button>
                                <input type="number" name="quantity" id="sale-quantity" min="1" value="1" required oninput="updateSaleTotal()" 
                                       style="flex: 1; height: 32px; text-align: center; font-size: 14px; font-weight: 700; border: 1px solid var(--border); border-radius: 6px; background: var(--bg2); color: var(--text); box-sizing: border-box;">
                                <button type="button" class="btn-sm btn-ghost" onclick="stepQuantity(1)" style="width: 32px; height: 32px; border: 1px solid var(--border); border-radius: 6px; font-size: 15px; font-weight: 700; display: flex; align-items: center; justify-content: center; background: var(--bg2); color: var(--text); cursor: pointer;">+</button>
                            </div>
                        </div>

                        <!-- Buyer Name -->
                        <div>
                            <label style="font-size: 10.5px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; color: var(--text2); display: block; margin-bottom: 3px;">
                                Customer / Buyer Name <span style="font-weight: 400; text-transform: none; color: var(--text2);">(Optional)</span>
                            </label>
                            <input type="text" name="buyer_name" placeholder="Walk-in Customer" 
                                   style="width: 100%; font-size: 12px; padding: 6px 10px; border: 1px solid var(--border); border-radius: 6px; background: var(--bg2); color: var(--text); box-sizing: border-box;">
                        </div>

                        <!-- Cash Tendered & Quick Tender Pill Buttons -->
                        <div style="margin-top: 2px;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 3px;">
                                <label style="font-size: 10.5px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; color: var(--text2); margin: 0;">
                                    Cash Tendered
                                </label>
                                <span id="pos-change-badge" style="font-size: 11.5px; font-weight: 700; color: var(--text2);">
                                    Change: ₱0.00
                                </span>
                            </div>
                            <div style="position: relative;">
                                <span style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); font-size: 12px; font-weight: 700; color: var(--text2); pointer-events: none;">₱</span>
                                <input type="number" step="any" min="0" id="sale-cash-tendered" placeholder="0.00" oninput="calculatePosChange()"
                                       style="width: 100%; height: 32px; padding-left: 24px; padding-right: 10px; font-size: 13px; font-weight: 700; border: 1px solid var(--border); border-radius: 6px; background: var(--bg2); color: var(--text); box-sizing: border-box;">
                            </div>
                            <!-- Quick Tender Amounts Row -->
                            <div style="display: flex; gap: 4px; margin-top: 6px; flex-wrap: wrap;">
                                <button type="button" class="btn-quick-tender" onclick="setPosTenderExact()" style="padding: 3px 8px; font-size: 10.5px; font-weight: 700; border-radius: 4px; border: 1px solid var(--border); background: var(--bg2); color: var(--text); cursor: pointer; transition: all 0.15s ease;">Exact</button>
                                <button type="button" class="btn-quick-tender" onclick="setPosTender(50)" style="padding: 3px 8px; font-size: 10.5px; font-weight: 700; border-radius: 4px; border: 1px solid var(--border); background: var(--bg2); color: var(--text); cursor: pointer; transition: all 0.15s ease;">₱50</button>
                                <button type="button" class="btn-quick-tender" onclick="setPosTender(100)" style="padding: 3px 8px; font-size: 10.5px; font-weight: 700; border-radius: 4px; border: 1px solid var(--border); background: var(--bg2); color: var(--text); cursor: pointer; transition: all 0.15s ease;">₱100</button>
                                <button type="button" class="btn-quick-tender" onclick="setPosTender(200)" style="padding: 3px 8px; font-size: 10.5px; font-weight: 700; border-radius: 4px; border: 1px solid var(--border); background: var(--bg2); color: var(--text); cursor: pointer; transition: all 0.15s ease;">₱200</button>
                                <button type="button" class="btn-quick-tender" onclick="setPosTender(500)" style="padding: 3px 8px; font-size: 10.5px; font-weight: 700; border-radius: 4px; border: 1px solid var(--border); background: var(--bg2); color: var(--text); cursor: pointer; transition: all 0.15s ease;">₱500</button>
                                <button type="button" class="btn-quick-tender" onclick="setPosTender(1000)" style="padding: 3px 8px; font-size: 10.5px; font-weight: 700; border-radius: 4px; border: 1px solid var(--border); background: var(--bg2); color: var(--text); cursor: pointer; transition: all 0.15s ease;">₱1,000</button>
                            </div>
                        </div>

                        <!-- Total Calculation Box -->
                        <div style="margin-top: auto; padding: 10px 12px; border-radius: 8px; background: var(--bg2); border: 1px solid var(--border);">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2px;">
                                <span style="font-size: 11px; color: var(--text2); font-weight: 600;">Unit Price</span>
                                <span style="font-size: 12px; font-weight: 600; color: var(--text);" id="pos-unit-price">₱0.00</span>
                            </div>
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px; border-bottom: 1px dashed var(--border); padding-bottom: 4px;">
                                <span style="font-size: 11px; color: var(--text2); font-weight: 600;">Total Quantity</span>
                                <span style="font-size: 12px; font-weight: 600; color: var(--text);" id="pos-qty-display">1</span>
                            </div>
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                                <span style="font-size: 11.5px; color: var(--text); font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em;">Grand Total</span>
                                <strong style="font-size: 22px; color: var(--accent, #EAB308); font-weight: 800;" id="sale-total-display">₱0.00</strong>
                            </div>

                            <button type="submit" id="btn-complete-sale" class="btn btn-primary" 
                                    style="width: 100%; height: 38px; justify-content: center; font-size: 12.5px; font-weight: 700; background: var(--accent); color: #000000; border: none; border-radius: 6px; cursor: pointer; transition: all 0.15s; display: flex; align-items: center; gap: 6px; box-shadow: 0 2px 8px rgba(201,168,76,0.25);">
                                <i class="fa-solid fa-check"></i> Complete Sale &amp; Issue Receipt
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- ── TAB 4: ARCHIVED ITEMS ── -->
    <?php if (!empty($archived_merchandise_items)): ?>
    <div id="merch-view-archived" class="merch-tab-pane" style="display: <?php echo ($initialTab === 'archived') ? 'flex' : 'none'; ?>; flex-direction: column; flex: 1; min-height: 0;">
        <div class="panel" style="border-radius: 10px; overflow: hidden; background: #FFFFFF; border: 1px solid var(--border); display: flex; flex-direction: column; flex: 1; min-height: 0;">
            <div class="panel-header" style="padding: 10px 16px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; background: #FFFFFF; flex-shrink: 0;">
                <div style="font-size: 13px; font-weight: 700; color: var(--text); display: flex; align-items: center; gap: 6px;">
                    <i class="fa-solid fa-box-archive" style="color: var(--accent);"></i> Archived Merchandise Products
                </div>
                <span class="badge" style="background: rgba(100,116,139,0.15); color: #64748b; border: 1px solid rgba(100,116,139,0.3); font-size: 11px; padding: 3px 8px; border-radius: 6px; font-weight: 600;">
                    <?php echo count($archived_merchandise_items); ?> Inactive Items
                </span>
            </div>

            <div class="panel-body" style="padding: 0; overflow-y: auto; flex: 1; min-height: 0;">
                <table class="data-table" style="font-size: 12.5px; width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr>
                            <th style="padding: 8px 14px; text-align: left;">Item Name</th>
                            <th style="padding: 8px 14px; width: 120px; text-align: right;">Last Price</th>
                            <th style="padding: 8px 14px; width: 110px; text-align: center;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($archived_merchandise_items as $archived): ?>
                        <tr>
                            <td style="padding: 10px 14px;">
                                <div style="font-weight: 700; font-size: 13px; color: var(--text); line-height: 1.3;"><?php echo htmlspecialchars($archived['name']); ?></div>
                                <div style="font-size: 11px; color: var(--text2);"><?php echo htmlspecialchars($archived['description'] ?? 'Archived item'); ?></div>
                            </td>
                            <td style="padding: 10px 14px; color: #111111; font-weight: 700; white-space: nowrap; text-align: right; font-size: 13px;">
                                ₱<?php echo number_format($archived['price'], 2); ?>
                            </td>
                            <td style="padding: 10px 14px; text-align: center;">
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Restore item to active catalog?')">
                                    <input type="hidden" name="form_section" value="merchandise">
                                    <input type="hidden" name="action" value="restore_merch">
                                    <input type="hidden" name="id" value="<?php echo (int)$archived['id']; ?>">
                                    <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
                                    <button type="submit" class="btn-sm btn-ghost" title="Restore to Catalog" style="font-size: 11px; padding: 4px 10px; color: #059669; border: 1px solid rgba(16,185,129,0.3); border-radius: 6px;">
                                        <i class="fa-solid fa-arrow-rotate-left"></i> Restore
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php endif; ?>

</div><!-- /.workspace -->

<!-- ── Modals ─────────────────────────────────────────────────────────────── -->

<!-- Add Item Modal -->
<div class="modal-overlay" id="modal-add-merch-item">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title"><i class="fa-solid fa-plus"></i> New Item</div>
            <button class="modal-close" onclick="closeModal('modal-add-merch-item')">×</button>
        </div>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="form_section" value="merchandise">
            <input type="hidden" name="action" value="add_merch">
            <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
            <div class="modal-body">
                <div class="form-group mb-16">
                    <label class="form-label">Item Name</label>
                    <input type="text" name="name" required placeholder="e.g. Scapular, Rosary, Bible">
                </div>
                <div class="form-group mb-16">
                    <label class="form-label">Description</label>
                    <input type="text" name="description" placeholder="Optional description">
                </div>
                <div class="form-row">
                    <div class="form-group mb-16">
                        <label class="form-label">Price (₱)</label>
                        <input type="number" name="price" min="0.01" step="0.01" required placeholder="0.00">
                    </div>
                    <div class="form-group mb-16">
                        <label class="form-label">Initial Stock</label>
                        <input type="number" name="stock" min="0" required value="0">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Item Photo <span style="font-size:11px;color:var(--text2);">(optional)</span></label>
                    <input type="file" name="photo" accept="image/*">
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Save Item</button>
                <button type="button" class="btn btn-gray" onclick="closeModal('modal-add-merch-item')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Item Modal -->
<div class="modal-overlay" id="modal-edit-merch-item">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title"><i class="fa-solid fa-pen-to-square"></i> Edit Item</div>
            <button class="modal-close" onclick="closeModal('modal-edit-merch-item')">×</button>
        </div>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="form_section" value="merchandise">
            <input type="hidden" name="action" value="edit_merch">
            <input type="hidden" name="id" id="edit-merch-id">
            <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
            <div class="modal-body">
                <div class="form-group mb-16">
                    <label class="form-label">Item Name</label>
                    <input type="text" name="name" id="edit-merch-name" required>
                </div>
                <div class="form-group mb-16">
                    <label class="form-label">Description</label>
                    <input type="text" name="description" id="edit-merch-desc">
                </div>
                <div class="form-row">
                    <div class="form-group mb-16">
                        <label class="form-label">Price (₱)</label>
                        <input type="number" name="price" id="edit-merch-price" min="0.01" step="0.01" required>
                    </div>
                    <div class="form-group mb-16">
                        <label class="form-label">Stock Quantity</label>
                        <input type="number" name="stock" id="edit-merch-stock" min="0" required>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Update Photo <span style="font-size:11px;color:var(--text2);">(leave empty to keep current)</span></label>
                    <input type="file" name="photo" accept="image/*">
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Update Item</button>
                <button type="button" class="btn btn-gray" onclick="closeModal('modal-edit-merch-item')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Member Reserve Modal -->
<div class="modal-overlay" id="modal-member-reserve">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title"><i class="fa-solid fa-cart-plus"></i> Reserve Item</div>
            <button class="modal-close" onclick="closeModal('modal-member-reserve')">×</button>
        </div>
        <form method="POST">
            <input type="hidden" name="form_section" value="merchandise">
            <input type="hidden" name="action" value="member_reserve">
            <input type="hidden" name="id" id="member-res-item-id">
            <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
            <div class="modal-body">
                <div style="background: #F6F6F6; padding: 14px 16px; border-radius: 8px; margin-bottom: 14px; border: 1px solid var(--border);">
                    <div id="member-res-item-name" style="font-size: 16px; font-weight: 700; color: #111111;"></div>
                    <div id="member-res-item-price" style="font-size: 13px; color: var(--text2); margin-top: 4px;"></div>
                </div>
                <div class="form-group">
                    <label class="form-label">Quantity</label>
                    <input type="number" name="quantity" id="member-res-qty" min="1" value="1" required>
                </div>
                <div class="form-group" style="margin-top:10px;">
                    <label class="form-label">Pickup Location</label>
                    <select name="chapel_id" required>
                        <option value="0">IFI Cathedral (Main)</option>
                        <?php foreach ($chapels as $ch): ?>
                        <option value="<?php echo $ch['chapel_id']; ?>" <?php echo ($chapel_id == $ch['chapel_id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($ch['chapel_name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <p style="font-size:12px; color:var(--text2); margin-top:12px;">
                    <i class="fa-solid fa-circle-info"></i> Note: This will record your order in our system. Please visit the parish office for pickup and payment.
                </p>
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check"></i> Confirm Reservation</button>
                <button type="button" class="btn btn-gray" onclick="closeModal('modal-member-reserve')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Sale Detail Modal -->
<div class="modal-overlay" id="modal-sale-detail">
    <div class="modal" style="max-width: 440px;">
        <div class="modal-header">
            <div class="modal-title"><i class="fa-solid fa-receipt"></i> Sale Details</div>
            <button class="modal-close" onclick="closeModal('modal-sale-detail')">×</button>
        </div>
        <div class="modal-body" id="sale-detail-body" style="display:flex; flex-direction:column; gap:12px;">
            <!-- Filled dynamically by JS -->
        </div>
        <div class="modal-footer">
            <a id="sale-detail-receipt-link" href="#" target="_blank" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 6px;">
                <i class="fa-solid fa-print"></i> Print Official Receipt
            </a>
            <button type="button" class="btn btn-gray" onclick="closeModal('modal-sale-detail')">Close</button>
        </div>
    </div>
</div>

<script>
// ── TAB SWITCHING ─────────────────────────────────────────────────────────────
function switchMerchTab(tabId) {
    document.querySelectorAll('.merch-tab-btn').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.tab === tabId);
    });
    document.querySelectorAll('.merch-tab-pane').forEach(pane => {
        pane.style.display = (pane.id === 'merch-view-' + tabId) ? 'flex' : 'none';
    });
    
    // Update URL parameter without reload
    const url = new URL(window.location);
    url.searchParams.set('tab', tabId);
    window.history.replaceState({}, '', url);
}

// ── POS PRODUCT SELECTION & TOTAL CALCULATION ──────────────────────────────────
function selectItemForPOS(id, name, price, stock) {
    switchMerchTab('pos');
    
    // Select option in dropdown
    const select = document.getElementById('sale-item-select');
    if (select) {
        select.value = String(id);
    }
    
    // Update visual card selection
    document.querySelectorAll('.pos-product-card').forEach(card => {
        card.classList.toggle('selected', card.dataset.id === String(id));
    });

    // Reset qty to 1 and update calculation
    const qtyInput = document.getElementById('sale-quantity');
    if (qtyInput) {
        qtyInput.value = 1;
        qtyInput.max = stock;
    }

    updateSaleTotal();
}

function onSelectItemDropdown() {
    const select = document.getElementById('sale-item-select');
    const selectedId = select.value;
    
    document.querySelectorAll('.pos-product-card').forEach(card => {
        card.classList.toggle('selected', card.dataset.id === selectedId);
    });

    updateSaleTotal();
}

function stepQuantity(delta) {
    const qtyInput = document.getElementById('sale-quantity');
    if (!qtyInput) return;
    let val = parseInt(qtyInput.value) || 1;
    val += delta;
    if (val < 1) val = 1;

    const select = document.getElementById('sale-item-select');
    const option = select.options[select.selectedIndex];
    if (option && option.value) {
        const maxStock = parseInt(option.getAttribute('data-stock')) || 9999;
        if (val > maxStock) val = maxStock;
    }

    qtyInput.value = val;
    updateSaleTotal();
}

function updateSaleTotal() {
    const select = document.getElementById('sale-item-select');
    const option = select ? select.options[select.selectedIndex] : null;
    const qtyInput = document.getElementById('sale-quantity');
    const totalDisplay = document.getElementById('sale-total-display');
    const unitPriceDisplay = document.getElementById('pos-unit-price');
    const qtyDisplay = document.getElementById('pos-qty-display');
    const stockHint = document.getElementById('pos-stock-hint');

    if (option && option.value !== '') {
        const price = parseFloat(option.getAttribute('data-price')) || 0;
        const maxStock = parseInt(option.getAttribute('data-stock')) || 0;
        let qty = parseInt(qtyInput.value) || 1;
        if (qty > maxStock) { 
            qty = maxStock; 
            qtyInput.value = maxStock; 
        }
        if (qty < 1) {
            qty = 1;
            qtyInput.value = 1;
        }

        const total = price * qty;
        totalDisplay.textContent = '₱' + total.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
        if (unitPriceDisplay) unitPriceDisplay.textContent = '₱' + price.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
        if (qtyDisplay) qtyDisplay.textContent = qty;
        if (stockHint) {
            stockHint.innerHTML = `<span style="color:#059669; font-weight:600;"><i class="fa-solid fa-boxes-stacked"></i> ${maxStock} units currently available in stock.</span>`;
        }
    } else {
        totalDisplay.textContent = '₱0.00';
        if (unitPriceDisplay) unitPriceDisplay.textContent = '₱0.00';
        if (qtyDisplay) qtyDisplay.textContent = '0';
        if (stockHint) {
            stockHint.textContent = 'Choose an item above or click one on the left.';
        }
    }
    calculatePosChange();
}

function getPosCurrentTotal() {
    const select = document.getElementById('sale-item-select');
    const option = select ? select.options[select.selectedIndex] : null;
    const qtyInput = document.getElementById('sale-quantity');
    if (!option || !option.value) return 0;
    const price = parseFloat(option.getAttribute('data-price')) || 0;
    const qty = parseInt(qtyInput ? qtyInput.value : '1') || 1;
    return price * qty;
}

function calculatePosChange() {
    const total = getPosCurrentTotal();
    const tenderInput = document.getElementById('sale-cash-tendered');
    const badge = document.getElementById('pos-change-badge');
    if (!badge) return;
    
    const tender = parseFloat(tenderInput ? tenderInput.value : '0') || 0;
    if (tender <= 0 || total <= 0) {
        badge.innerHTML = 'Change: ₱0.00';
        badge.style.color = 'var(--text2)';
        return;
    }
    
    const change = tender - total;
    if (change >= 0) {
        badge.innerHTML = `Change: <strong style="color: #10b981; font-size: 12px;">₱${change.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,')}</strong>`;
        badge.style.color = '#10b981';
    } else {
        const short = Math.abs(change);
        badge.innerHTML = `Short: <strong style="color: #ef4444; font-size: 12px;">₱${short.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,')}</strong>`;
        badge.style.color = '#ef4444';
    }
}

function setPosTender(amount) {
    const input = document.getElementById('sale-cash-tendered');
    if (input) {
        input.value = amount.toFixed(2);
        calculatePosChange();
    }
}

function setPosTenderExact() {
    const total = getPosCurrentTotal();
    const input = document.getElementById('sale-cash-tendered');
    if (input) {
        input.value = total > 0 ? total.toFixed(2) : '';
        calculatePosChange();
    }
}

window.addEventListener('keydown', (e) => {
    if (e.key === 'F2') {
        const search = document.getElementById('pos-product-search');
        if (search) {
            e.preventDefault();
            search.focus();
            search.select();
        }
    }
});

// ── CLIENT-SIDE SEARCH, SORT & FILTERING ──────────────────────────────────────
let _catalogSortAsc = true;
let _salesSortAsc = false;

function clearCatalogSearch() {
    const input = document.getElementById('catalog-search-input');
    if (input) {
        input.value = '';
        filterCatalogTable();
        input.focus();
    }
}

function clearSalesSearch() {
    const input = document.getElementById('sales-search-input');
    if (input) {
        input.value = '';
        filterSalesTable();
        input.focus();
    }
}

function filterCatalogTable() {
    const input = document.getElementById('catalog-search-input');
    const term = (input?.value || '').toLowerCase().trim();
    const clearBtn = document.getElementById('catalog-clear-search');
    if (clearBtn) {
        clearBtn.style.display = term ? 'block' : 'none';
    }

    const rows = document.querySelectorAll('#catalog-data-table .catalog-row');
    const activeFilter = window._activeCatalogStockFilter || 'all';
    const thirtyDaysAgo = Math.floor(Date.now() / 1000) - (30 * 86400);

    rows.forEach(row => {
        const name = row.dataset.name || '';
        const desc = row.dataset.desc || '';
        const stockType = row.dataset.stockType || '';
        const created = parseInt(row.dataset.created || '0', 10);

        const matchesTerm = !term || name.includes(term) || desc.includes(term);
        const matchesStock = (activeFilter === 'all') 
            || (activeFilter === 'recent' ? (created >= thirtyDaysAgo || created === 0) : (stockType === activeFilter));

        row.style.display = (matchesTerm && matchesStock) ? '' : 'none';
    });

    if (activeFilter === 'recent') {
        const tbody = document.querySelector('#catalog-data-table tbody');
        if (tbody) {
            const rowArr = Array.from(tbody.querySelectorAll('.catalog-row'));
            rowArr.sort((a, b) => {
                const cA = parseInt(a.dataset.created || a.dataset.id || '0', 10);
                const cB = parseInt(b.dataset.created || b.dataset.id || '0', 10);
                return cB - cA; // Newest first
            });
            rowArr.forEach(r => tbody.appendChild(r));
        }
    }
}

function toggleCatalogSort(btn) {
    _catalogSortAsc = !_catalogSortAsc;
    const icon = btn.querySelector('i');
    if (icon) {
        icon.className = _catalogSortAsc ? 'fa-solid fa-arrow-down-a-z' : 'fa-solid fa-arrow-down-z-a';
    }
    btn.title = _catalogSortAsc ? 'Sorted: Name A-Z (Click to sort Z-A)' : 'Sorted: Name Z-A (Click to sort A-Z)';

    const tbody = document.querySelector('#catalog-data-table tbody');
    if (!tbody) return;
    const rows = Array.from(tbody.querySelectorAll('.catalog-row'));
    rows.sort((a, b) => {
        const nameA = (a.dataset.name || '').toLowerCase();
        const nameB = (b.dataset.name || '').toLowerCase();
        return _catalogSortAsc ? nameA.localeCompare(nameB) : nameB.localeCompare(nameA);
    });
    rows.forEach(r => tbody.appendChild(r));
}

function setCatalogStockFilter(type, btn) {
    window._activeCatalogStockFilter = type;
    document.querySelectorAll('.catalog-filter-pill').forEach(b => {
        b.classList.remove('active');
        b.style.background = 'transparent';
        b.style.color = 'var(--text2)';
    });
    if (btn) {
        btn.classList.add('active');
        btn.style.background = '#111111';
        btn.style.color = '#FFFFFF';
    }
    filterCatalogTable();
}

function filterSalesTable() {
    const input = document.getElementById('sales-search-input');
    const term = (input?.value || '').toLowerCase().trim();
    const clearBtn = document.getElementById('sales-clear-search');
    if (clearBtn) {
        clearBtn.style.display = term ? 'block' : 'none';
    }

    const rows = document.querySelectorAll('#sales-data-table .sales-row');
    rows.forEach(row => {
        const item = row.dataset.item || '';
        const buyer = row.dataset.buyer || '';
        const matches = !term || item.includes(term) || buyer.includes(term);
        row.style.display = matches ? '' : 'none';
    });
}

function toggleSalesSort(btn) {
    _salesSortAsc = !_salesSortAsc;
    const icon = btn.querySelector('i');
    if (icon) {
        icon.className = _salesSortAsc ? 'fa-solid fa-arrow-down-wide-short' : 'fa-solid fa-arrow-up-wide-short';
    }
    btn.title = _salesSortAsc ? 'Sorted: Newest First (Click to sort Oldest first)' : 'Sorted: Oldest First (Click to sort Newest first)';

    const tbody = document.querySelector('#sales-data-table tbody');
    if (!tbody) return;
    const rows = Array.from(tbody.querySelectorAll('.sales-row'));
    rows.sort((a, b) => {
        const dateA = parseInt(a.dataset.date || '0', 10);
        const dateB = parseInt(b.dataset.date || '0', 10);
        return _salesSortAsc ? (dateA - dateB) : (dateB - dateA);
    });
    rows.forEach(r => tbody.appendChild(r));
}

function clearPosSearch() {
    const input = document.getElementById('pos-product-search');
    if (input) {
        input.value = '';
        filterPosProductGrid();
        input.focus();
    }
}

function filterPosProductGrid() {
    const input = document.getElementById('pos-product-search');
    const term = (input?.value || '').toLowerCase().trim();
    const clearBtn = document.getElementById('pos-clear-search');
    if (clearBtn) {
        clearBtn.style.display = term ? 'block' : 'none';
    }
    const cards = document.querySelectorAll('#pos-product-grid .pos-product-card');

    cards.forEach(card => {
        const name = card.dataset.name || '';
        card.style.display = (!term || name.includes(term)) ? 'flex' : 'none';
    });
}

// ── INBOX SALE PREFILL ────────────────────────────────────────────────────────
function applyMerchSalePrefill() {
    let prefill = null;
    try {
        const raw = sessionStorage.getItem('merchSalePrefill');
        if (raw) prefill = JSON.parse(raw);
    } catch (e) {}
    if (!prefill) return;

    // Switch to POS tab automatically
    switchMerchTab('pos');

    const itemSelect   = document.getElementById('sale-item-select');
    const qtyInput     = document.getElementById('sale-quantity');
    const buyerInput   = document.querySelector('input[name="buyer_name"]');
    const inquiryInput = document.getElementById('sale-inquiry-id');

    if (!itemSelect || !qtyInput || !buyerInput) return;

    if (prefill.itemId) {
        itemSelect.value = String(prefill.itemId);
        onSelectItemDropdown();
    }
    if (prefill.quantity)  qtyInput.value   = prefill.quantity;
    if (prefill.buyerName) buyerInput.value = prefill.buyerName;
    if (inquiryInput && prefill.inquiryId) inquiryInput.value = prefill.inquiryId;

    updateSaleTotal();
    sessionStorage.removeItem('merchSalePrefill');
}

// ── EDIT MERCH ITEM MODAL ─────────────────────────────────────────────────────
function editMerchItem(input) {
    let item = input;
    if (input instanceof HTMLElement) {
        const raw = input.getAttribute('data-item');
        if (raw) {
            try { item = JSON.parse(raw); } catch (e) { console.error('Failed to parse item:', e); return; }
        }
    } else if (typeof input === 'string') {
        try { item = JSON.parse(input); } catch (e) { console.error('Failed to parse item:', e); return; }
    }
    if (!item || typeof item !== 'object') return;

    document.getElementById('edit-merch-id').value    = item.id;
    document.getElementById('edit-merch-name').value  = item.name;
    document.getElementById('edit-merch-desc').value  = item.description || '';
    document.getElementById('edit-merch-price').value = item.price;
    document.getElementById('edit-merch-stock').value = item.stock_quantity;
    openModal('modal-edit-merch-item');
}
window.editMerchItem = editMerchItem;

// ── MEMBER RESERVE MODAL ──────────────────────────────────────────────────────
function openMemberReserveModal(input) {
    let item = input;
    if (input instanceof HTMLElement) {
        const raw = input.getAttribute('data-item');
        if (raw) {
            try { item = JSON.parse(raw); } catch (e) { console.error('Failed to parse item:', e); return; }
        }
    } else if (typeof input === 'string') {
        try { item = JSON.parse(input); } catch (e) { console.error('Failed to parse item:', e); return; }
    }
    if (!item || typeof item !== 'object') return;

    document.getElementById('member-res-item-id').value       = item.id;
    document.getElementById('member-res-item-name').innerText = item.name;
    document.getElementById('member-res-item-price').innerText = '₱' + parseFloat(item.price).toLocaleString('en-PH', { minimumFractionDigits: 2 });
    document.getElementById('member-res-qty').value           = 1;
    document.getElementById('member-res-qty').max             = item.stock_quantity;
    openModal('modal-member-reserve');
}
window.openMemberReserveModal = openMemberReserveModal;

// ── SALE DETAIL MODAL ─────────────────────────────────────────────────────────
function viewMerchSaleDetails(input) {
    let sale = input;
    if (input instanceof HTMLElement) {
        const raw = input.getAttribute('data-sale');
        if (raw) {
            try { sale = JSON.parse(raw); } catch (e) { console.error('Failed to parse sale:', e); return; }
        }
    } else if (typeof input === 'string') {
        try { sale = JSON.parse(input); } catch (e) { console.error('Failed to parse sale:', e); return; }
    }
    if (!sale || typeof sale !== 'object') return;

    const fmt = (v) => parseFloat(v).toLocaleString('en-PH', { minimumFractionDigits: 2 });
    const dt  = sale.sale_date
        ? new Date(sale.sale_date.replace(' ', 'T')).toLocaleString('en-US', {
            weekday:'short', year:'numeric', month:'short',
            day:'numeric', hour:'2-digit', minute:'2-digit'
          })
        : '—';

    document.getElementById('sale-detail-body').innerHTML = `
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
            <div style="padding:10px 12px; border-radius:8px; background:#F9FAFB; border:1px solid var(--border);">
                <div style="font-size:10px; text-transform:uppercase; letter-spacing:.06em; color:var(--text2); margin-bottom:2px; font-weight:700;">Item Name</div>
                <div style="font-weight:700; color:var(--text); font-size:13px;">${sale.item_name || '—'}</div>
            </div>
            <div style="padding:10px 12px; border-radius:8px; background:#F9FAFB; border:1px solid var(--border);">
                <div style="font-size:10px; text-transform:uppercase; letter-spacing:.06em; color:var(--text2); margin-bottom:2px; font-weight:700;">Customer</div>
                <div style="font-weight:600; color:var(--text); font-size:13px;">${sale.buyer_name || 'Walk-in'}</div>
            </div>
            <div style="padding:10px 12px; border-radius:8px; background:#F9FAFB; border:1px solid var(--border);">
                <div style="font-size:10px; text-transform:uppercase; letter-spacing:.06em; color:var(--text2); margin-bottom:2px; font-weight:700;">Quantity</div>
                <div style="font-weight:800; color:var(--text); font-size:18px;">${sale.quantity}</div>
            </div>
            <div style="padding:10px 12px; border-radius:8px; background:rgba(201,168,76,0.12); border:1px solid rgba(201,168,76,0.3);">
                <div style="font-size:10px; text-transform:uppercase; letter-spacing:.06em; color:var(--text2); margin-bottom:2px; font-weight:700;">Total Amount</div>
                <div style="font-weight:800; color:var(--accent); font-size:18px;">₱${fmt(sale.total_price)}</div>
            </div>
        </div>
        <div style="padding:8px 12px; border-radius:6px; background:#F9FAFB; border:1px solid var(--border); font-size:11.5px; color:var(--text2);">
            <i class="fa-solid fa-clock" style="margin-right:5px;"></i>${dt}
        </div>
    `;
    document.getElementById('sale-detail-receipt-link').href = `views/pages/merchandise_receipt.php?id=${sale.id}`;
    openModal('modal-sale-detail');
}
window.viewMerchSaleDetails = viewMerchSaleDetails;

document.addEventListener('DOMContentLoaded', () => {
    applyMerchSalePrefill();
    updateSaleTotal();
});
</script>