/**
 * Dashboard Navigation Module
 * Handles Hybrid SPA section switching, sidebar toggles, and dropdowns.
 */

let dashboardHistory = [];
let isNavigatingBack = false;
let currentActiveSection = new URLSearchParams(window.location.search).get('section')
    || (window.location.pathname.match(/\/dashboard\/([^/?#]+)/)?.[1] || 'overview');

async function navigateToSection(id, pushState = true, extraQuery = '') {
    if (id === 'cathedral') id = 'schedule_cathedral';
    if (id === 'chapel' || id === 'chapels_schedule') id = 'schedule_chapel';

    // 1. Update navigation active state
    document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
    let el = document.querySelector(`.nav-item[data-section="${id}"]`);
    if (!el) {
        if (id === 'schedule_cathedral' || id === 'records_cathedral') {
            el = document.querySelector('.nav-item[data-section="schedule_cathedral"]') || document.querySelector('.nav-item[data-section="cathedral"]');
        } else if (id === 'schedule_chapel' || id === 'records_chapel') {
            el = document.querySelector('.nav-item[data-section="schedule_chapel"]') || document.querySelector('.nav-item[data-section="chapels"]');
        } else if (['users', 'chapels', 'services', 'parish_info', 'backups', 'settings', 'reports', 'insights'].includes(id)) {
            el = document.querySelector('.nav-item[data-section="settings"]');
        }
    }
    if (el) {
        el.classList.add('active');
        const parentDropdown = el.closest('.nav-dropdown');
        document.querySelectorAll('.nav-dropdown').forEach(d => d.classList.remove('has-active-child'));
        if (parentDropdown) {
            parentDropdown.classList.add('open');
            parentDropdown.classList.add('has-active-child');
        }
        if (window.LiquidSidebar && typeof window.LiquidSidebar.update === 'function') {
            window.LiquidSidebar.update(el, true);
        }
    }
    if (window.LiquidSidebar && typeof window.LiquidSidebar.hideFlyout === 'function') {
        window.LiquidSidebar.hideFlyout();
    }
    
    closeSidebar();

    // Track history for the back button
    if (id === 'overview') {
        dashboardHistory = [];
    } else if (pushState && !isNavigatingBack) {
        if (currentActiveSection && currentActiveSection !== id) {
            dashboardHistory.push(currentActiveSection);
        }
    }
    currentActiveSection = id;
    updateBackButtonVisibility();

    const extraQs = typeof extraQuery === 'string'
        ? extraQuery.replace(/^[?&]+/, '')
        : (extraQuery && typeof extraQuery === 'object' ? new URLSearchParams(extraQuery).toString() : '');

    if (pushState) {
        const baseUrl = window.BASE_URL || '';
        const cleanUrl = (id === 'overview' || !id) ? `${baseUrl}/dashboard` : `${baseUrl}/dashboard/${id}`;
        history.pushState({section: id}, '', extraQs ? `${cleanUrl}?${extraQs}` : cleanUrl);
    }
    
    // 2. Fetch partial content
    const targetDiv = document.getElementById('dynamic-content-area');
    if (!targetDiv) return;

    // Destroy any existing Chart.js instances before replacing DOM
    if (typeof Chart !== 'undefined' && targetDiv) {
        targetDiv.querySelectorAll('canvas').forEach(canvas => {
            const existing = (typeof Chart.getChart === 'function') ? Chart.getChart(canvas) : null;
            if (existing) {
                try { existing.destroy(); } catch (e) {}
            }
        });
    }

    // Show Instant Section-Specific Skeleton Loader
    if (window.SkeletonLoader && targetDiv) {
        targetDiv.innerHTML = window.SkeletonLoader.section(id);
    }

    try {
        const baseUrl = window.BASE_URL || '';
        const response = await fetch(`${baseUrl}/dashboard.php?section=${id}&ajax=1${extraQs ? '&' + extraQs : ''}`);
        if (!response.ok) {
            if (response.status === 401 || response.status === 403) {
                window.location.href = 'index.php?error=session';
                return;
            }
            throw new Error('Network response was not ok');
        }

        // Safety check against followed 302 redirects to index.php
        if (response.redirected && response.url && response.url.includes('index.php')) {
            window.location.href = response.url;
            return;
        }

        const html = await response.text();

        // Safety check: if response contains JSON session expired or the full landing page HTML
        try {
            const jsonCheck = JSON.parse(html);
            if (jsonCheck && jsonCheck.session_expired) {
                window.location.href = jsonCheck.redirect || 'index.php?error=session';
                return;
            }
        } catch (e) {}

        if (html.includes('id="hero"') || (html.includes('Cathedral of St. Michael') && html.includes('hero-church-brand-title'))) {
            window.location.href = 'index.php?error=session';
            return;
        }
        
        targetDiv.innerHTML = html;
        targetDiv.querySelectorAll('.section').forEach(sec => sec.classList.add('active'));
        
        // Intercept DOMContentLoaded listeners while executing partial scripts:
        // Since main DOM is already loaded, any partial registering DOMContentLoaded
        // will have its callback executed immediately once scripts finish!
        const origAddEventListener = document.addEventListener;
        const domReadyCallbacks = [];
        document.addEventListener = function(type, listener, options) {
            if (type === 'DOMContentLoaded' && typeof listener === 'function') {
                domReadyCallbacks.push(listener);
            } else {
                origAddEventListener.call(document, type, listener, options);
            }
        };

        const scripts = Array.from(targetDiv.querySelectorAll('script'));
        for (const s of scripts) {
            if (s.src) {
                await loadScriptAsync(s.src);
            } else if (s.textContent.trim()) {
                try {
                    // Convert top-level let/const declarations to var so re-navigation doesn't trigger SyntaxError
                    const code = s.textContent.replace(/^(const|let)\s+/gm, 'var ');
                    (0, eval)(code);
                } catch (e) {
                    console.warn('Notice executing section script for ' + id + ':', e);
                    try {
                        const fn = new Function(s.textContent);
                        fn.call(window);
                    } catch (err) {}
                }
            }
        }

        // Restore original addEventListener
        document.addEventListener = origAddEventListener;

        // Run all registered DOMContentLoaded callbacks now that partial scripts have executed
        domReadyCallbacks.forEach(cb => {
            try { cb(); } catch (err) { console.warn('Error in DOMContentLoaded callback:', err); }
        });
        
        // Re-init section-specific components
        reinitializeSectionComponents(id);

    } catch (err) {
        console.error('Failed to load partial page', err);
        targetDiv.innerHTML = '<div class="alert alert-danger" style="color: red; padding: 20px;">Failed to load content. Please check your connection.</div>';
    }

    setTimeout(() => { targetDiv.style.opacity = 1; }, 50);
}

function loadScriptAsync(src) {
    return new Promise((resolve) => {
        if (document.querySelector(`script[src="${src}"]`)) {
            resolve();
            return;
        }
        const script = document.createElement('script');
        script.src = src;
        script.onload = () => resolve();
        script.onerror = () => resolve();
        document.head.appendChild(script);
    });
}

function reinitializeSectionComponents(id) {
    // 1. Priest / Pastor Schedule
    if (id === 'priest_schedule' || id === 'pastor_schedule') {
        if (typeof window.initPriestSchedule === 'function') {
            window.initPriestSchedule();
        }
    }
    if ((id === 'priest_schedule' || id === 'pastor_schedule') && document.getElementById('cal-grid')) {
        if (typeof window.priestEvents !== 'undefined') {
            window.pastorEvents = window.priestEvents;
        } else if (typeof window.pastorEvents !== 'undefined') {
            window.priestEvents = window.pastorEvents;
        }
        if (typeof renderCalendar === 'function') {
            renderCalendar(calYear, calMonth);
        }
        if (typeof renderTimeline === 'function') {
            const dateInput = document.getElementById('timeline-date');
            const d = new Date();
            const dateStr = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2,'0') + '-' + String(d.getDate()).padStart(2,'0');
            renderTimeline(dateInput?.value || dateStr);
        }
    }

    // 2. Overview Calendar & Agenda
    if (id === 'overview') {
        if (typeof renderOvCalendar === 'function' && document.getElementById('ov-cal-grid')) {
            renderOvCalendar();
            const todayStr = typeof localDateKey === 'function' ? localDateKey() : new Date().toISOString().substring(0, 10);
            if (typeof ovShowAgenda === 'function') {
                ovShowAgenda(todayStr);
            }
        }
    }

    // 3. Request Event (Slots & Availability)
    if (id === 'request_event') {
        if (typeof syncRequestSlots === 'function') {
            syncRequestSlots();
        }
    }

    // 4. Cathedral & Chapel Schedule Auto-Selection
    if (id === 'schedule_cathedral' || id === 'schedule_chapel') {
        const firstRow = document.querySelector('.record-row');
        if (firstRow && typeof showEventDetails === 'function') {
            const evData = firstRow.getAttribute('data-event') || firstRow.dataset.event;
            if (evData) {
                try {
                    showEventDetails(JSON.parse(evData), firstRow);
                } catch (e) {}
            }
        }
    }

    // 5. Records Initializer
    if (id === 'records_cathedral' || id === 'records_chapel') {
        if (typeof initRecordsSection === 'function') {
            initRecordsSection();
        }
    }

    // 6. Messages / Inquiries Initializer
    if (id === 'public_inquiries') {
        if (typeof window.initWorkspaceInquirySelection === 'function') {
            window.initWorkspaceInquirySelection();
        } else {
            const firstRow = document.querySelector('.inq-row');
            if (firstRow && typeof window.showWorkspaceInquiryDetails === 'function') {
                window.showWorkspaceInquiryDetails(firstRow);
            }
        }
    }

    // 7. Re-bind / reset live search autocomplete across AJAX-swapped partials
    if (typeof window.hideSearchAutocomplete === 'function') {
        window.hideSearchAutocomplete();
    }
    if (typeof window.initSearchAutocomplete === 'function') {
        window.initSearchAutocomplete();
    }
}

function showSection(id, el) {
    if (id) navigateToSection(id, true);
}

/**
 * Logic for the Back Button
 */
function navigateBack() {
    if (dashboardHistory.length > 0) {
        isNavigatingBack = true;
        const prevSection = dashboardHistory.pop();
        navigateToSection(prevSection, true);
        isNavigatingBack = false;
    }
}

function updateBackButtonVisibility() {
    const btn = document.getElementById('backNavBtn');
    if (btn) {
        btn.classList.remove('visible');
        btn.style.display = 'none';
    }
}

function isMobileView() {
    return window.innerWidth <= 1024;
}

function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('overlayBg');
    if (!sidebar) return;

    if (window.innerWidth >= 1025) {
        if (window.LiquidSidebar && typeof window.LiquidSidebar.toggle === 'function') {
            window.LiquidSidebar.toggle();
            return;
        }
    }

    if (sidebar.classList.contains('open')) {
        closeSidebar();
    } else {
        sidebar.classList.add('open');
        if (overlay) overlay.classList.add('open');
        if (isMobileView()) document.body.classList.add('sidebar-open');
    }
}

function closeSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('overlayBg');
    if (sidebar) sidebar.classList.remove('open');
    if (overlay) overlay.classList.remove('open');
    document.body.classList.remove('sidebar-open');
}

function toggleDropdown(el) {
    const parent = el.closest('.nav-dropdown');
    if (!parent) return;
    const sidebar = document.getElementById('sidebar');
    const isCompact = sidebar && !sidebar.classList.contains('is-expanded') && window.innerWidth >= 1025;

    if (isCompact) {
        const flyout = document.getElementById('subtopicFlyout');
        const parentTitle = parent.getAttribute('data-parent-title') || el.querySelector('span')?.textContent;
        const isFlyoutOpen = flyout && flyout.style.display === 'flex' && flyout.getAttribute('data-active-parent') === parentTitle;

        if (isFlyoutOpen) {
            if (window.LiquidSidebar && typeof window.LiquidSidebar.hideFlyout === 'function') {
                window.LiquidSidebar.hideFlyout();
            }
        } else {
            if (window.LiquidSidebar && typeof window.LiquidSidebar.showFlyout === 'function') {
                window.LiquidSidebar.showFlyout(el);
            }
        }
        return;
    }

    // Expanded mode: standard inline accordion
    document.querySelectorAll('.nav-dropdown').forEach(d => {
        if (d !== parent) d.classList.remove('open');
    });

    parent.classList.toggle('open');

    // Re-align the liquid indicator after drawer transition
    if (window.LiquidSidebar && typeof window.LiquidSidebar.update === 'function') {
        setTimeout(() => {
            window.LiquidSidebar.update(null, true);
        }, 220);
    }
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.sidebar-nav .nav-item[data-section], #topbarInboxBtn[data-section]').forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const section = this.dataset.section;
            if (section) navigateToSection(section, true);
        });
    });

    document.addEventListener('click', function(e) {
        const tabLink = e.target.closest('.settings-tab-link[data-section]');
        if (tabLink) {
            e.preventDefault();
            const section = tabLink.dataset.section;
            if (section) navigateToSection(section, true);
        }
    });

    updateBackButtonVisibility();
});

window.addEventListener('popstate', function(e) {
    if (e.state && e.state.section) {
        navigateToSection(e.state.section, false);
    } else {
        const urlParams = new URLSearchParams(window.location.search);
        let section = urlParams.get('section');
        if (!section) {
            const pathParts = window.location.pathname.split('/').filter(Boolean);
            const dashIdx = pathParts.indexOf('dashboard');
            if (dashIdx !== -1 && pathParts[dashIdx + 1]) {
                section = pathParts[dashIdx + 1];
            }
        }
        navigateToSection(section || 'overview', false);
    }
});

window.addEventListener('resize', () => {
    if (!isMobileView()) {
        const overlay = document.getElementById('overlayBg');
        if (overlay) overlay.classList.remove('open');
        document.body.classList.remove('sidebar-open');
    }
});

// ── UNIVERSAL COMMAND PALETTE (CTRL + K) CONTROLLER ──────────────────────────
let _cmdSelectedIdx = 0;

function openCommandPalette() {
    const modal = document.getElementById('modal-command-palette');
    const search = document.getElementById('cmd-palette-search');
    if (!modal || !search) return;
    
    modal.classList.add('open');
    search.value = '';
    filterCommandPalette('');
    setTimeout(() => search.focus(), 50);
}

function closeCommandPalette() {
    const modal = document.getElementById('modal-command-palette');
    if (modal) modal.classList.remove('open');
}

function filterCommandPalette(term) {
    const q = (term || '').toLowerCase().trim();
    const items = document.querySelectorAll('#cmd-palette-results .cmd-item');
    const groups = document.querySelectorAll('#cmd-palette-results .cmd-group-title');
    
    items.forEach((item) => {
        const title = (item.dataset.title || '').toLowerCase();
        const text = (item.textContent || '').toLowerCase();
        const matches = !q || title.includes(q) || text.includes(q);
        item.style.display = matches ? 'flex' : 'none';
        item.classList.remove('selected');
    });

    // Hide group headers when actively searching
    groups.forEach(g => {
        g.style.display = q ? 'none' : 'block';
    });

    _cmdSelectedIdx = 0;
    highlightCommandItem(0);
}

function highlightCommandItem(index) {
    const visibleItems = Array.from(document.querySelectorAll('#cmd-palette-results .cmd-item')).filter(i => i.style.display !== 'none');
    if (visibleItems.length === 0) return;
    
    if (index < 0) index = visibleItems.length - 1;
    if (index >= visibleItems.length) index = 0;
    _cmdSelectedIdx = index;

    visibleItems.forEach((item, idx) => {
        item.classList.toggle('selected', idx === index);
        if (idx === index) {
            item.scrollIntoView({ block: 'nearest' });
        }
    });
}

// Global Keyboard Shortcuts
window.addEventListener('keydown', (e) => {
    const modal = document.getElementById('modal-command-palette');
    const isOpen = modal && modal.classList.contains('open');

    // Ctrl + K or Cmd + K toggles palette
    if ((e.ctrlKey || e.metaKey) && (e.key === 'k' || e.key === 'K')) {
        e.preventDefault();
        if (isOpen) {
            closeCommandPalette();
        } else {
            openCommandPalette();
        }
        return;
    }

    if (!isOpen) return;

    if (e.key === 'Escape') {
        e.preventDefault();
        closeCommandPalette();
    } else if (e.key === 'ArrowDown') {
        e.preventDefault();
        highlightCommandItem(_cmdSelectedIdx + 1);
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        highlightCommandItem(_cmdSelectedIdx - 1);
    } else if (e.key === 'Enter') {
        const visibleItems = Array.from(document.querySelectorAll('#cmd-palette-results .cmd-item')).filter(i => i.style.display !== 'none');
        if (visibleItems[_cmdSelectedIdx]) {
            e.preventDefault();
            visibleItems[_cmdSelectedIdx].click();
        }
    }
});
