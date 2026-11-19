/**
 * ====================================================================
 * SKELETON-LOADER.JS — Reusable Skeleton Loader Engine
 * Chapel & Cathedral Information Management System
 * ====================================================================
 * Programmatically generates fluid, theme-aware skeleton placeholders
 * for data tables, metric cards, calendar grids, inspectors, and sections.
 */

window.SkeletonLoader = (function () {
    'use strict';

    /**
     * Generate HTML for a single skeleton block
     */
    function block(widthClass = 'sk-w-100', heightClass = 'sk-h-md', extraClasses = '', style = '') {
        return `<span class="sk-block ${widthClass} ${heightClass} ${extraClasses}" style="${style}"></span>`;
    }

    /**
     * Generate HTML for Stat/Telemetry Cards
     */
    function stats(count = 5) {
        let html = `<div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr)); gap:10px; width:100%;">`;
        for (let i = 0; i < count; i++) {
            html += `
                <div class="sk-stat-card">
                    <span class="sk-block sk-stat-icon-box sk-circle"></span>
                    <div class="sk-stat-info">
                        <span class="sk-block sk-w-50 sk-h-xs"></span>
                        <span class="sk-block sk-w-80 sk-h-lg"></span>
                    </div>
                </div>
            `;
        }
        html += `</div>`;
        return html;
    }

    /**
     * Generate HTML for a Data Table Skeleton
     */
    function table(options = {}) {
        const rows = options.rows || 6;
        const cols = options.cols || 6;
        const widths = options.widths || ['sk-w-20', 'sk-w-80', 'sk-w-60', 'sk-w-50', 'sk-w-70', 'sk-w-40'];

        let html = `<div class="sk-table-container">`;
        
        // Header
        html += `<div class="sk-table-header">`;
        for (let c = 0; c < cols; c++) {
            const w = widths[c % widths.length] || 'sk-w-50';
            html += `<div style="flex:1;"><span class="sk-block ${w} sk-h-xs"></span></div>`;
        }
        html += `</div>`;

        // Body Rows
        for (let r = 0; r < rows; r++) {
            html += `<div class="sk-table-row">`;
            for (let c = 0; c < cols; c++) {
                const w = widths[(r + c) % widths.length] || 'sk-w-60';
                html += `<div style="flex:1;"><span class="sk-block ${w} sk-h-sm"></span></div>`;
            }
            html += `</div>`;
        }

        html += `</div>`;
        return html;
    }

    /**
     * Generate HTML for Calendar Grid Skeleton
     */
    function calendar() {
        let html = `
            <div class="sk-calendar-card" style="flex:1;">
                <div class="sk-cal-topbar">
                    <div style="display:flex; align-items:center; gap:10px;">
                        <span class="sk-block sk-w-40 sk-h-lg" style="width:140px !important;"></span>
                    </div>
                    <div style="display:flex; gap:6px;">
                        <span class="sk-block sk-btn" style="width:34px !important; height:28px !important;"></span>
                        <span class="sk-block sk-btn" style="width:34px !important; height:28px !important;"></span>
                    </div>
                </div>
                <div class="sk-cal-grid">
        `;

        // Days of week row
        for (let i = 0; i < 7; i++) {
            html += `<div style="text-align:center; padding:4px;"><span class="sk-block sk-w-60 sk-h-xs"></span></div>`;
        }

        // 35 Day cells (5 weeks)
        for (let i = 0; i < 35; i++) {
            const hasEvent = (i % 3 === 0);
            html += `
                <div class="sk-cal-day-cell">
                    <span class="sk-block sk-w-30 sk-h-xs"></span>
                    ${hasEvent ? `<span class="sk-block sk-w-80 sk-h-xs sk-pill" style="margin-top:auto;"></span>` : ''}
                </div>
            `;
        }

        html += `
                </div>
            </div>
        `;
        return html;
    }

    /**
     * Generate HTML for Timeline Agenda Skeleton
     */
    function agenda(count = 4) {
        let html = `<div class="sk-agenda-list">`;
        for (let i = 0; i < count; i++) {
            html += `
                <div class="sk-agenda-item">
                    <span class="sk-block sk-circle" style="width:10px; height:10px;"></span>
                    <div style="flex:1; display:flex; flex-direction:column; gap:6px;">
                        <span class="sk-block sk-w-75 sk-h-sm"></span>
                        <span class="sk-block sk-w-40 sk-h-xs"></span>
                    </div>
                    <span class="sk-block sk-badge sk-w-25" style="width:60px !important;"></span>
                </div>
            `;
        }
        html += `</div>`;
        return html;
    }

    /**
     * Generate HTML for Record Inspector Card Skeleton
     */
    function inspector() {
        return `
            <div class="sk-inspector-card">
                <div style="display:flex; flex-direction:column; align-items:center; gap:6px; padding-bottom:8px; border-bottom:1px solid var(--sk-border);">
                    <span class="sk-block sk-w-40 sk-h-xs"></span>
                    <span class="sk-block sk-w-70 sk-h-md"></span>
                    <span class="sk-block sk-pill sk-w-30 sk-h-xs"></span>
                </div>
                <div class="sk-inspector-hero">
                    <span class="sk-block sk-w-80 sk-h-md"></span>
                    <span class="sk-block sk-w-50 sk-h-xs"></span>
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px;">
                    <div style="background:rgba(255,255,255,0.02); border:1px solid var(--sk-border); border-radius:6px; padding:8px; display:flex; flex-direction:column; gap:4px;">
                        <span class="sk-block sk-w-50 sk-h-xs"></span>
                        <span class="sk-block sk-w-80 sk-h-sm"></span>
                    </div>
                    <div style="background:rgba(255,255,255,0.02); border:1px solid var(--sk-border); border-radius:6px; padding:8px; display:flex; flex-direction:column; gap:4px;">
                        <span class="sk-block sk-w-50 sk-h-xs"></span>
                        <span class="sk-block sk-w-80 sk-h-sm"></span>
                    </div>
                </div>
                <div style="display:flex; flex-direction:column; gap:8px;">
                    <div class="sk-inspector-field-row"><span class="sk-block sk-w-30 sk-h-xs"></span><span class="sk-block sk-w-50 sk-h-xs"></span></div>
                    <div class="sk-inspector-field-row"><span class="sk-block sk-w-40 sk-h-xs"></span><span class="sk-block sk-w-45 sk-h-xs"></span></div>
                    <div class="sk-inspector-field-row"><span class="sk-block sk-w-35 sk-h-xs"></span><span class="sk-block sk-w-60 sk-h-xs"></span></div>
                    <div class="sk-inspector-field-row"><span class="sk-block sk-w-45 sk-h-xs"></span><span class="sk-block sk-w-40 sk-h-xs"></span></div>
                </div>
                <div style="display:flex; gap:8px; margin-top:6px;">
                    <span class="sk-block sk-btn sk-w-50"></span>
                    <span class="sk-block sk-btn sk-w-50"></span>
                </div>
            </div>
        `;
    }

    /**
     * Generate HTML for Cards Grid (Services / Catalog)
     */
    function cards(count = 6) {
        let html = `<div class="sk-card-grid">`;
        for (let i = 0; i < count; i++) {
            html += `
                <div class="sk-card">
                    <div style="display:flex; align-items:center; justify-content:space-between;">
                        <span class="sk-block sk-avatar-sm"></span>
                        <span class="sk-block sk-badge" style="width:70px !important;"></span>
                    </div>
                    <span class="sk-block sk-w-75 sk-h-md"></span>
                    <span class="sk-block sk-w-100 sk-h-xs"></span>
                    <span class="sk-block sk-w-60 sk-h-xs"></span>
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-top:6px; padding-top:8px; border-top:1px solid var(--sk-border);">
                        <span class="sk-block sk-w-30 sk-h-sm"></span>
                        <span class="sk-block sk-btn" style="width:90px !important; height:28px !important;"></span>
                    </div>
                </div>
            `;
        }
        html += `</div>`;
        return html;
    }

    /**
     * Generate HTML for Interactive Slot Picker (Morning & Afternoon)
     */
    function slotPicker() {
        return `
            <div class="sk-slot-grid">
                <div class="sk-slot-card">
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <span class="sk-block sk-w-40 sk-h-xs"></span>
                        <span class="sk-block sk-badge" style="width:65px !important;"></span>
                    </div>
                    <span class="sk-block sk-w-50 sk-h-xs"></span>
                    <span class="sk-block sk-w-80 sk-h-sm" style="border-radius:4px;"></span>
                </div>
                <div class="sk-slot-card">
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <span class="sk-block sk-w-40 sk-h-xs"></span>
                        <span class="sk-block sk-badge" style="width:65px !important;"></span>
                    </div>
                    <span class="sk-block sk-w-50 sk-h-xs"></span>
                    <span class="sk-block sk-w-80 sk-h-sm" style="border-radius:4px;"></span>
                </div>
            </div>
        `;
    }

    /**
     * Generate HTML for Analytics / Finance Chart Placeholder
     */
    function chart() {
        return `
            <div class="sk-chart-card">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <span class="sk-block sk-w-40 sk-h-sm"></span>
                    <span class="sk-block sk-pill sk-w-20 sk-h-xs" style="width:80px !important;"></span>
                </div>
                <div class="sk-chart-bars-wrap">
                    <span class="sk-block sk-chart-bar" style="height:40%;"></span>
                    <span class="sk-block sk-chart-bar" style="height:75%;"></span>
                    <span class="sk-block sk-chart-bar" style="height:55%;"></span>
                    <span class="sk-block sk-chart-bar" style="height:90%;"></span>
                    <span class="sk-block sk-chart-bar" style="height:65%;"></span>
                    <span class="sk-block sk-chart-bar" style="height:85%;"></span>
                    <span class="sk-block sk-chart-bar" style="height:45%;"></span>
                </div>
            </div>
        `;
    }

    /**
     * Generate Full-Page Section Skeleton based on section ID
     */
    function section(sectionId) {
        const id = (sectionId || 'overview').toLowerCase();

        // 1. OVERVIEW SECTION SKELETON
        if (id === 'overview') {
            return `
                <div class="sk-section-container">
                    <!-- Top banner placeholder -->
                    <div style="height:42px; border-radius:12px; background:var(--sk-card-bg); border:1px solid var(--sk-card-border); padding:8px 16px; display:flex; align-items:center; justify-content:space-between;">
                        <span class="sk-block sk-w-30 sk-h-sm"></span>
                        <span class="sk-block sk-w-20 sk-h-xs"></span>
                    </div>

                    <!-- Telemetry Stats Row -->
                    ${stats(5)}

                    <!-- Hero 2-Column (Calendar Sanctuary + Agenda) -->
                    <div style="display:grid; grid-template-columns:minmax(0, 1fr) 320px; gap:12px; align-items:stretch;">
                        ${calendar()}
                        <div style="display:flex; flex-direction:column; gap:12px;">
                            <div style="background:var(--sk-card-bg); border:1px solid var(--sk-card-border); border-radius:12px; padding:14px; display:flex; flex-direction:column; gap:10px;">
                                <div style="display:flex; justify-content:space-between; align-items:center;">
                                    <span class="sk-block sk-w-50 sk-h-sm"></span>
                                    <span class="sk-block sk-w-20 sk-h-xs"></span>
                                </div>
                                ${agenda(4)}
                            </div>
                            <div style="background:var(--sk-card-bg); border:1px solid var(--sk-card-border); border-radius:12px; padding:12px; display:flex; flex-direction:column; gap:8px;">
                                <span class="sk-block sk-w-40 sk-h-xs"></span>
                                <div style="display:flex; gap:6px;">
                                    <span class="sk-block sk-btn sk-w-50" style="height:28px !important;"></span>
                                    <span class="sk-block sk-btn sk-w-50" style="height:28px !important;"></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }

        // 2. SACRAMENTAL RECORDS SECTION SKELETON
        if (id.startsWith('records_')) {
            const scopeTitle = id.includes('chapel') ? 'Chapel Sacramental Records' : 'Cathedral Sacramental Records';
            return `
                <div class="sk-section-container">
                    <!-- Workspace Header -->
                    <div style="display:flex; justify-content:space-between; align-items:center; height:38px;">
                        <div style="display:flex; align-items:center; gap:8px;">
                            <span class="sk-block sk-circle" style="width:20px; height:20px;"></span>
                            <span class="sk-block sk-w-40 sk-h-md" style="width:200px !important;"></span>
                        </div>
                        <div style="display:flex; gap:8px;">
                            <span class="sk-block sk-btn" style="width:140px !important; height:30px !important;"></span>
                            <span class="sk-block sk-btn" style="width:100px !important; height:30px !important;"></span>
                        </div>
                    </div>

                    <!-- 6-Metric Stats Row -->
                    <div style="display:grid; grid-template-columns:repeat(6, 1fr); gap:8px;">
                        ${Array.from({length: 6}).map(() => `
                            <div class="sk-stat-card" style="padding:8px 10px;">
                                <div class="sk-stat-info">
                                    <span class="sk-block sk-w-50 sk-h-xs"></span>
                                    <span class="sk-block sk-w-80 sk-h-md"></span>
                                </div>
                            </div>
                        `).join('')}
                    </div>

                    <!-- Split Panel 65/35 -->
                    <div style="display:grid; grid-template-columns:minmax(0, 1.85fr) minmax(300px, 1fr); gap:12px; align-items:start;">
                        <div style="display:flex; flex-direction:column; gap:10px;">
                            <!-- Filter bar -->
                            <div style="background:var(--sk-card-bg); border:1px solid var(--sk-card-border); border-radius:8px; padding:6px 10px; display:flex; gap:8px;">
                                <span class="sk-block sk-w-50" style="height:30px !important; border-radius:6px;"></span>
                                <span class="sk-block sk-w-25" style="height:30px !important; border-radius:6px;"></span>
                                <span class="sk-block sk-btn" style="width:75px !important; height:30px !important;"></span>
                            </div>
                            <!-- Table -->
                            ${table({ rows: 7, cols: 6, widths: ['sk-w-20', 'sk-w-80', 'sk-w-60', 'sk-w-50', 'sk-w-70', 'sk-w-40'] })}
                        </div>
                        <!-- Right Inspector -->
                        <div>
                            ${inspector()}
                        </div>
                    </div>
                </div>
            `;
        }

        // 3. EVENTS / CALENDAR SECTION SKELETON
        if (id === 'events' || id === 'pastoral_calendar' || id === 'schedule_cathedral' || id === 'schedule_chapel' || id.includes('schedule')) {
            return `
                <div class="sk-section-container">
                    <div style="display:flex; justify-content:space-between; align-items:center; height:38px;">
                        <span class="sk-block sk-w-30 sk-h-md" style="width:180px !important;"></span>
                        <span class="sk-block sk-btn" style="width:110px !important; height:30px !important;"></span>
                    </div>
                    <div style="display:grid; grid-template-columns:minmax(0, 1fr) 320px; gap:12px;">
                        ${calendar()}
                        <div style="display:flex; flex-direction:column; gap:12px;">
                            <div style="background:var(--sk-card-bg); border:1px solid var(--sk-card-border); border-radius:12px; padding:14px; display:flex; flex-direction:column; gap:10px;">
                                <span class="sk-block sk-w-60 sk-h-sm"></span>
                                ${agenda(5)}
                            </div>
                            ${slotPicker()}
                        </div>
                    </div>
                </div>
            `;
        }

        // 4. SERVICES / CATALOG SECTION SKELETON
        if (id === 'services' || id === 'service_catalog') {
            return `
                <div class="sk-section-container">
                    <div style="text-align:center; padding:20px 0 10px; display:flex; flex-direction:column; align-items:center; gap:8px;">
                        <span class="sk-block sk-w-40 sk-h-lg"></span>
                        <span class="sk-block sk-w-60 sk-h-xs"></span>
                        <span class="sk-block sk-pill sk-w-30 sk-h-sm" style="margin-top:6px;"></span>
                    </div>
                    ${cards(6)}
                </div>
            `;
        }

        // 5. FINANCE / REPORTS / DONATIONS SKELETON
        if (id === 'finance' || id === 'reports' || id === 'donations' || id === 'merchandise' || id === 'insights') {
            return `
                <div class="sk-section-container">
                    <div style="display:flex; justify-content:space-between; align-items:center; height:38px;">
                        <span class="sk-block sk-w-30 sk-h-md" style="width:160px !important;"></span>
                        <span class="sk-block sk-btn" style="width:95px !important; height:30px !important;"></span>
                    </div>
                    ${stats(4)}
                    <div style="display:grid; grid-template-columns:minmax(0, 1.4fr) minmax(0, 1fr); gap:12px; margin-top:6px;">
                        <div style="display:flex; flex-direction:column; gap:10px;">
                            <div style="background:var(--sk-card-bg); border:1px solid var(--sk-card-border); border-radius:8px; padding:8px 12px; display:flex; justify-content:space-between; align-items:center;">
                                <span class="sk-block sk-w-30 sk-h-sm"></span>
                                <span class="sk-block sk-w-20 sk-h-xs"></span>
                            </div>
                            ${table({ rows: 6, cols: 5, widths: ['sk-w-25', 'sk-w-70', 'sk-w-50', 'sk-w-40', 'sk-w-40'] })}
                        </div>
                        <div>
                            ${chart()}
                        </div>
                    </div>
                </div>
            `;
        }

        // 6. GENERIC / USERS / AUDIT / MANAGEMENT DEFAULT SKELETON
        return `
            <div class="sk-section-container">
                <div style="display:flex; justify-content:space-between; align-items:center; height:38px;">
                    <div style="display:flex; align-items:center; gap:8px;">
                        <span class="sk-block sk-circle" style="width:22px; height:22px;"></span>
                        <span class="sk-block sk-w-30 sk-h-md" style="width:160px !important;"></span>
                    </div>
                    <span class="sk-block sk-btn" style="width:100px !important; height:32px !important;"></span>
                </div>
                <div style="background:var(--sk-card-bg); border:1px solid var(--sk-card-border); border-radius:8px; padding:8px 12px; display:flex; gap:10px;">
                    <span class="sk-block sk-w-40" style="height:30px !important; border-radius:6px;"></span>
                    <span class="sk-block sk-w-20" style="height:30px !important; border-radius:6px;"></span>
                </div>
                ${table({ rows: 8, cols: 6 })}
            </div>
        `;
    }

    /**
     * Inject a skeleton into a specific DOM element
     */
    function attach(container, type = 'table', options = {}) {
        if (!container) return;
        if (typeof container === 'string') container = document.getElementById(container);
        if (!container) return;

        let content = '';
        switch (type) {
            case 'table': content = table(options); break;
            case 'stats': content = stats(options.count || 5); break;
            case 'calendar': content = calendar(); break;
            case 'agenda': content = agenda(options.count || 4); break;
            case 'inspector': content = inspector(); break;
            case 'cards': content = cards(options.count || 6); break;
            case 'slotPicker': content = slotPicker(); break;
            case 'chart': content = chart(); break;
            case 'section': content = section(options.sectionId || 'overview'); break;
            default: content = table(options); break;
        }

        container.innerHTML = content;
    }

    /**
     * Remove or clear skeleton
     */
    function detach(container) {
        if (!container) return;
        if (typeof container === 'string') container = document.getElementById(container);
        if (!container) return;
        container.innerHTML = '';
    }

    // Public API
    return {
        block,
        stats,
        table,
        calendar,
        agenda,
        inspector,
        cards,
        slotPicker,
        chart,
        section,
        attach,
        detach
    };
})();
