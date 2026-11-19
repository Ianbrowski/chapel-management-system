<?php
/**
 * views/partials/insights_section.php
 * Decision Intelligence & Strategic Planning Layer
 */
if (!$can_insights && !$can_pastor_dss) return;

$portfolio = $insight_portfolio ?? ['cards' => [], 'recommendations' => [], 'venue_demand' => [], 'default_card' => 'pastoral'];
$cards = (array) ($portfolio['cards'] ?? []);
$defaultCard = (string) ($portfolio['default_card'] ?? 'pastoral');
?>
<link rel="stylesheet" href="assets/css/workspace.css?v=2.5">

<div class="workspace workspace-fluid" id="sec-insights" style="gap: 14px;">
    <!-- ── Workspace Header (Row 1: Title + Stat Badges Pinned Top-Right) ── -->
    <div class="page-header-bar" style="display: flex; justify-content: space-between; align-items: center; padding-bottom: 8px; border-bottom: 1px solid var(--border); flex-wrap: wrap; gap: 10px; margin-bottom: 6px;">
        <div style="display: flex; align-items: center; gap: 12px;">
            <div class="page-header-icon" style="width: 38px; height: 38px; border-radius: 9px; background: rgba(201,168,76,0.14); color: var(--accent); display: flex; align-items: center; justify-content: center; font-size: 17px; flex-shrink: 0;">
                <i class="fa-solid fa-chart-line"></i>
            </div>
            <div>
                <h1 class="page-title" style="font-family: var(--font-family, 'Inter', sans-serif); font-size: var(--font-page-title, 20px); font-weight: 700; color: var(--text); margin: 0; line-height: 1.2;">
                    Pastoral &amp; Ministry Analytics
                </h1>
                <p class="page-subtitle" style="font-size: var(--font-page-subtitle, 14px); color: var(--text2); margin: 3px 0 0 0;">Ministry engagement metrics, sacramental growth, and pastoral capacity insights</p>
            </div>
        </div>
        <div style="display: flex; align-items: center; gap: 8px;">
            <span class="badge" style="background: var(--bg3, #F4F4F5); border: 1px solid var(--border); color: var(--text2); font-size: 11.5px; font-weight: 600; padding: 4px 12px; border-radius: 999px;">
                <?php echo count($cards); ?> Strategic Indicators
            </span>
        </div>
    </div>

    <!-- ── Insights Highlight Cards Grid ── -->
    <div class="insight-grid" id="insight-grid">
        <?php foreach ($cards as $card): ?>
        <button type="button"
                class="insight-card highlight-card <?php echo ($card['key'] ?? '') === $defaultCard ? 'is-active' : ''; ?>"
                data-insight-key="<?php echo htmlspecialchars($card['key'] ?? ''); ?>">
            <div class="insight-icon <?php echo htmlspecialchars($card['icon_class'] ?? ''); ?>">
                <i class="<?php echo htmlspecialchars($card['icon'] ?? 'fa-solid fa-circle-info'); ?>"></i>
            </div>
            <div class="insight-content">
                <div class="insight-headline-row">
                    <h3><?php echo htmlspecialchars($card['title'] ?? 'Insight'); ?></h3>
                    <span class="insight-open-label"><i class="fa-solid fa-arrow-up-right-from-square" style="font-size: 9px; margin-right: 4px;"></i> Details</span>
                </div>
                <p><?php echo htmlspecialchars($card['card_message'] ?? ''); ?></p>
                <div class="insight-meta-row">
                    <div class="insight-action-tag"><?php echo htmlspecialchars($card['tag'] ?? 'Insight'); ?></div>
                    <div class="insight-score"><?php echo htmlspecialchars($card['score'] ?? 'Standby'); ?></div>
                </div>
            </div>
        </button>
        <?php endforeach; ?>
    </div>

    <!-- ── Modal Drilldown Shell ── -->
    <div id="insight-modal" class="insight-modal">
        <div class="insight-modal-backdrop" onclick="closeInsightModal()"></div>
        <div class="insight-modal-content">
            <button type="button" class="insight-modal-close" onclick="closeInsightModal()">
                <i class="fa-solid fa-xmark"></i>
            </button>
            
            <div class="insight-detail-card">
                <div class="insight-detail-topline">Intelligence Drilldown</div>
                <div class="insight-detail-header">
                    <div>
                        <h3 id="insight-detail-title" style="margin: 0 0 6px 0; /* was: 1.25rem */ font-size: var(--font-size-xl); font-weight: 700; color: var(--text);">Insight</h3>
                        <p id="insight-detail-summary" style="margin: 0; color: var(--text2); /* was: 13.5px */ font-size: var(--font-size-sm); line-height: 1.5;">Detailed explanation will appear here.</p>
                    </div>
                    <div class="insight-detail-badges">
                        <span class="insight-detail-pill" id="insight-detail-status">Standby</span>
                        <span class="insight-detail-pill muted" id="insight-detail-tag">Insight</span>
                    </div>
                </div>
                
                <div class="insight-detail-grid">
                    <div class="insight-detail-panel">
                        <div class="insight-detail-panel-title"><i class="fa-solid fa-magnifying-glass-chart"></i> Narrative Analysis</div>
                        <div class="insight-detail-narrative" id="insight-detail-narrative"></div>
                    </div>
                    <div class="insight-detail-panel">
                        <div class="insight-detail-panel-title"><i class="fa-solid fa-list-check"></i> Primary Metrics</div>
                        <ul class="insight-metric-list" id="insight-detail-metrics"></ul>
                    </div>
                    <div class="insight-detail-panel">
                        <div class="insight-detail-panel-title"><i class="fa-solid fa-bolt-lightning"></i> Strategic Moves</div>
                        <ul class="insight-metric-list" id="insight-detail-actions"></ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Analytics Row: Demand Heatmap & Recommended Actions ── -->
    <div class="insight-row">
        <div class="insight-chart-col">
            <div class="panel">
                <div class="panel-header">
                    <div class="panel-title">
                        <i class="fa-solid fa-map-location-dot" style="color: var(--accent);"></i>
                        <span>Venue Demand Heatmap</span>
                    </div>
                </div>
                <div class="panel-body" style="padding: 16px;">
                    <div class="chart-container" style="height: 280px; position: relative;">
                        <canvas id="venueDemandChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="insight-reco-col">
            <div class="panel" style="display: flex; flex-direction: column;">
                <div class="panel-header">
                    <div class="panel-title">
                        <i class="fa-solid fa-lightbulb" style="/* was: #f59e0b */ color: var(--color-warning);"></i>
                        <span>Recommended Actions</span>
                    </div>
                    <span class="badge badge-neutral" style="font-size: 11px;">
                        <?php echo count($portfolio['recommendations'] ?? []); ?> Available
                    </span>
                </div>
                <div class="panel-body" style="padding: 0; max-height: 280px; overflow-y: auto;">
                    <ul class="reco-list" id="reco-list">
                        <?php if (!empty($portfolio['recommendations'])): ?>
                            <?php foreach ($portfolio['recommendations'] as $recommendation): ?>
                            <li class="reco-item" data-insight-target="<?php echo htmlspecialchars($recommendation['key'] ?? ''); ?>">
                                <i class="fa-solid fa-circle-check reco-item-icon"></i>
                                <span style="flex: 1;"><?php echo htmlspecialchars($recommendation['text'] ?? ''); ?></span>
                                <i class="fa-solid fa-chevron-right" style="font-size: 10px; color: var(--text2); opacity: 0.5;"></i>
                            </li>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <li class="reco-item" style="color: var(--text2);">Gathering data for more specific recommendations...</li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <style>
        .insight-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 12px;
        }
        .insight-card {
            width: 100%;
            text-align: left;
            background: var(--panel-bg);
            border-radius: var(--panel-radius, 12px);
            padding: 18px 20px;
            display: flex;
            align-items: flex-start;
            gap: 16px;
            border: var(--panel-border);
            box-shadow: var(--panel-shadow);
            transition: transform 0.2s, box-shadow 0.2s, border-color 0.2s, background 0.2s;
            cursor: pointer;
        }
        .insight-card:hover,
        .insight-card:focus-visible,
        .insight-card.is-active {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.22);
            border-color: var(--accent);
            outline: none;
        }
        .insight-card.is-active {
            border-color: var(--accent);
            background: var(--hover-bg, #F6F6F6);
        }
        .insight-icon {
            width: 44px;
            height: 44px;
            background: var(--bg3, #F6F6F6);
            border: 1px solid var(--border);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            color: var(--text);
            flex-shrink: 0;
            transition: transform 0.25s;
        }
        .insight-card:hover .insight-icon,
        .insight-card.is-active .insight-icon {
            transform: scale(1.06);
        }
        .pulse-pastor { color: var(--accent) !important; background: rgba(197, 160, 89, 0.12) !important; }
        .pulse-growth { color: #16a34a !important; background: rgba(22, 163, 74, 0.12) !important; }
        .pulse-finance { color: var(--accent) !important; background: rgba(197, 160, 89, 0.12) !important; }
        .pulse-retention { color: var(--accent) !important; background: rgba(197, 160, 89, 0.12) !important; }
        .pulse-facility { color: var(--text) !important; background: var(--bg3) !important; }
        .pulse-efficiency { color: #16a34a !important; background: rgba(22, 163, 74, 0.12) !important; }
        .insight-content { flex: 1; min-width: 0; }
        .insight-headline-row,
        .insight-meta-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
        }
        .insight-headline-row { margin-bottom: 6px; }
        .insight-content h3 {
            /* was: 15px */ font-size: var(--font-size-base);
            font-weight: 700;
            color: var(--text);
            margin: 0;
        }
        .insight-content p {
            /* was: 12.5px */ font-size: var(--font-size-sm);
            color: var(--text2);
            line-height: 1.45;
            margin-bottom: 12px;
        }
        .insight-open-label,
        .insight-score,
        .insight-action-tag,
        .insight-detail-pill {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            padding: 3px 8px;
            border-radius: 999px;
        }
        .insight-open-label {
            border: 1px solid rgba(201, 168, 76, 0.3);
            color: var(--accent);
            background: rgba(201, 168, 76, 0.06);
        }
        .insight-action-tag,
        .insight-detail-pill.muted {
            background: var(--accent-glow, rgba(201, 168, 76, 0.12));
            color: var(--accent);
        }
        .insight-score {
            color: #cbd5e1;
            background: rgba(148, 163, 184, 0.12);
        }

        /* MODAL STYLES */
        .insight-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .insight-modal.is-open {
            opacity: 1;
            visibility: visible;
        }
        .insight-modal-backdrop {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.85);
            backdrop-filter: blur(8px);
        }
        .insight-modal-content {
            position: relative;
            width: 90%;
            max-width: 1000px;
            max-height: 90vh;
            overflow-y: auto;
            transform: scale(0.9) translateY(20px);
            transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
            z-index: 1001;
        }
        .insight-modal.is-open .insight-modal-content {
            transform: scale(1) translateY(0);
        }
        .insight-modal-close {
            position: absolute;
            top: 16px;
            right: 16px;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            /* was: rgba(15, 23, 42, 0.7) */ background: var(--color-surface);
            /* was: rgba(255,255,255,0.1) */ border: 1px solid var(--color-border);
            color: var(--text);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            z-index: 1002;
            transition: all 0.2s;
        }
        .insight-modal-close:hover {
            background: var(--accent);
            color: var(--bg);
            transform: rotate(90deg);
        }

        .insight-detail-card {
            padding: 24px;
            border-radius: 12px;
            border: 1px solid var(--border);
            box-shadow: 0 4px 16px rgba(0,0,0,0.04);
            background: var(--panel-bg);
            color: var(--text);
        }
        .insight-detail-topline {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: var(--text2);
            margin-bottom: 8px;
            font-weight: 700;
        }
        .insight-detail-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 16px;
            margin-bottom: 14px;
            border-bottom: 1px solid var(--border);
            padding-bottom: 20px;
        }
        .insight-detail-card h2 {
            font-family: var(--font-family, 'Inter', sans-serif);
            font-weight: 700;
            letter-spacing: -0.02em;
            margin-bottom: var(--space-2);
            color: var(--text);
        }
        .insight-detail-header p {
            margin: 0;
            color: var(--text2);
            line-height: 1.6;
            /* was: 15px */ font-size: var(--font-size-base);
        }
        .insight-detail-badges {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        .insight-detail-pill {
            padding: 6px 14px;
            font-size: 11px;
        }
        .insight-detail-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 20px;
        }
        .insight-panel,
        .insight-detail-panel {
            background: var(--panel-bg);
            border: var(--panel-border);
            border-radius: 10px;
            padding: 16px;
            height: 100%;
        }
        .insight-panel h4,
        .insight-detail-panel h4 {
            font-size: 12px;
            margin-bottom: 12px;
            color: var(--accent);
            text-transform: uppercase;
            letter-spacing: 0.06em;
            font-weight: 700;
        }
        .insight-detail-panel-title {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            color: var(--accent);
            margin-bottom: 16px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .insight-detail-narrative {
            color: var(--text);
            line-height: 1.8;
            /* was: 14px */ font-size: var(--font-size-base);
        }
        .insight-metric-list {
            list-style: none;
            padding: 0;
            margin: 0;
            display: grid;
            gap: 12px;
        }
        .insight-metric-list li {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            color: var(--text);
            /* was: 13.5px */ font-size: var(--font-size-sm);
            line-height: 1.5;
        }
        .insight-metric-list li i,
        .reco-item-icon {
            color: var(--accent);
            margin-top: 3px;
            font-size: 12px;
        }
        .insight-metric-divider {
            height: 1px;
            background: var(--border);
            margin: 4px 0;
        }
        .insight-row {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 12px;
        }
        .reco-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .reco-item {
            padding: 12px 16px;
            border-bottom: 1px solid var(--border);
            display: flex;
            gap: 10px;
            align-items: center;
            /* was: 12.5px */ font-size: var(--font-size-sm);
            color: var(--text);
            line-height: 1.4;
            cursor: pointer;
            transition: background 0.18s ease;
        }
        .reco-item:hover { background: rgba(201, 168, 76, 0.05); }
        .reco-item:last-child { border-bottom: none; }
        @media (max-width: 1100px) {
            .insight-detail-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 992px) {
            .insight-row { grid-template-columns: 1fr; }
        }

        /* Dark mode overrides */
        [data-theme="dark"] .insight-card {
            background: var(--panel-bg);
            border-color: var(--border);
        }
        [data-theme="dark"] .insight-card.is-active {
            background: #1e293b;
            border-color: var(--accent);
        }
        [data-theme="dark"] .insight-icon {
            background: #1e293b;
            color: var(--text);
            border-color: var(--border);
        }
        [data-theme="dark"] .insight-detail-card {
            background: var(--panel-bg);
            border-color: var(--border);
            color: var(--text);
        }
        [data-theme="dark"] .insight-detail-panel {
            background: #141f33;
            border-color: var(--border);
        }
    </style>

    <script>
    (function() {
        const portfolio = <?php echo json_encode($portfolio, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        const cards = Array.isArray(portfolio.cards) ? portfolio.cards : [];
        const defaultKey = portfolio.default_card || (cards[0] && cards[0].key) || '';
        const venueDemand = Array.isArray(portfolio.venue_demand) ? portfolio.venue_demand : [];
        const detailEls = {
            title: document.getElementById('insight-detail-title'),
            summary: document.getElementById('insight-detail-summary'),
            status: document.getElementById('insight-detail-status'),
            tag: document.getElementById('insight-detail-tag'),
            narrative: document.getElementById('insight-detail-narrative'),
            metrics: document.getElementById('insight-detail-metrics'),
            actions: document.getElementById('insight-detail-actions')
        };

        function renderList(target, items) {
            if (!target) return;
            target.innerHTML = '';
            (Array.isArray(items) ? items : []).forEach(item => {
                if (item === '---') {
                    const hr = document.createElement('div');
                    hr.className = 'insight-metric-divider';
                    target.appendChild(hr);
                } else if (typeof item === 'string' && item.startsWith('[HEADER:') && item.endsWith(']')) {
                    // Styled section header — extract label between [HEADER: and ]
                    const label = item.slice(8, -1);
                    const hd = document.createElement('div');
                    hd.style.cssText = 'font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:0.12em;color:var(--accent);padding:6px 0 2px 0;opacity:0.85;';
                    hd.textContent = label;
                    target.appendChild(hd);
                } else {
                    const li = document.createElement('li');
                    li.innerHTML = `<i class="fa-solid fa-circle-check"></i><span>${item}</span>`;
                    target.appendChild(li);
                }
            });
        }

        function activateInsight(key, skipModal = false) {
            const state = cards.find(card => card.key === key);
            if (!state) return;

            document.querySelectorAll('.insight-card[data-insight-key]').forEach(card => {
                card.classList.toggle('is-active', card.dataset.insightKey === key);
            });

            if (detailEls.title) detailEls.title.textContent = state.title || 'Insight';
            if (detailEls.summary) detailEls.summary.textContent = state.summary || '';
            if (detailEls.status) detailEls.status.textContent = state.status || 'Monitoring';
            if (detailEls.tag) detailEls.tag.textContent = state.tag || 'Insight';
            if (detailEls.narrative) detailEls.narrative.textContent = state.narrative || '';
            renderList(detailEls.metrics, state.metrics || []);
            renderList(detailEls.actions, state.actions || []);

            if (!skipModal) {
                const modal = document.getElementById('insight-modal');
                if (modal) modal.classList.add('is-open');
                document.body.style.overflow = 'hidden';
            }
        }

        window.closeInsightModal = function() {
            const modal = document.getElementById('insight-modal');
            if (modal) modal.classList.remove('is-open');
            document.body.style.overflow = '';
        };

        document.querySelectorAll('.insight-card[data-insight-key]').forEach(card => {
            card.addEventListener('click', () => activateInsight(card.dataset.insightKey));
        });

        document.querySelectorAll('.reco-item[data-insight-target]').forEach(item => {
            item.addEventListener('click', () => activateInsight(item.dataset.insightTarget));
        });

        // Close on Escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') closeInsightModal();
        });

        const ctxDemand = document.getElementById('venueDemandChart');
        if (ctxDemand && typeof Chart !== 'undefined') {
            const existing = Chart.getChart(ctxDemand);
            if (existing) existing.destroy();
            new Chart(ctxDemand, {
                type: 'doughnut',
                data: {
                    labels: venueDemand.length ? venueDemand.map(d => d.label) : ['No Data'],
                    datasets: [{
                        data: venueDemand.length ? venueDemand.map(d => d.value) : [1],
                        backgroundColor: ['#c9a84c', '#16a34a', '#0b0f19', '#d8b56f', '#64748b', '#dc2626', '#334155', '#94a3b8'],
                        hoverOffset: 12,
                        hoverBorderWidth: 4,
                        borderWidth: 0
                    }]
                },
                options: {
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right',
                            labels: { color: '#888', font: { size: 10 }, padding: 10 }
                        }
                    },
                    cutout: '70%'
                }
            });
        }

        if (defaultKey) {
            activateInsight(defaultKey, true);
        }
    })();
    </script>
</div>
