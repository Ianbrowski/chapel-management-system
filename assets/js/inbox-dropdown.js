/**
 * Cathedral Management System - Topbar Inbox Dropdown
 * Facebook / Messenger-style interactive inbox preview menu.
 */
(function() {
    'use strict';

    const InboxDropdown = {
        isOpen: false,
        currentTab: 'all',
        cachedData: null,
        lastFetched: 0,
        cacheTtl: 30000, // 30 seconds fresh cache
        isFetching: false,

        init() {
            this.wrapper = document.getElementById('topbarInboxWrapper');
            this.btn = document.getElementById('topbarInboxBtn');
            this.menu = document.getElementById('inboxDropdownMenu');
            this.list = document.getElementById('inboxDdList');
            this.countPill = document.getElementById('inboxDdCountPill');
            this.refreshBtn = document.getElementById('inboxDdRefreshBtn');
            this.readAllBtn = document.getElementById('inboxDdReadAllBtn');
            this.isMember = false;

            if (!this.wrapper || !this.btn || !this.menu) return;

            // Outside click listener
            document.addEventListener('click', (e) => {
                if (this.isOpen && !this.wrapper.contains(e.target)) {
                    this.close();
                }
            });

            // Escape key listener
            document.addEventListener('keydown', (e) => {
                if (this.isOpen && e.key === 'Escape') {
                    this.close();
                    this.btn.focus();
                }
            });
        },

        toggle(e) {
            if (e) {
                e.preventDefault();
                e.stopPropagation();
            }
            if (this.isOpen) {
                this.close();
            } else {
                this.open();
            }
        },

        open() {
            if (!this.menu || !this.btn) return;
            this.isOpen = true;
            this.btn.classList.add('is-open');
            this.btn.setAttribute('aria-expanded', 'true');
            this.menu.style.display = 'flex';
            this.menu.classList.add('show');

            // Close other topbar popovers or flyouts if open
            if (window.LiquidSidebar && typeof window.LiquidSidebar.hideFlyout === 'function') {
                window.LiquidSidebar.hideFlyout();
            }

            const now = Date.now();
            if (!this.cachedData || (now - this.lastFetched > this.cacheTtl)) {
                this.fetchData();
            } else {
                this.render();
            }
        },

        close() {
            if (!this.menu || !this.btn) return;
            this.isOpen = false;
            this.btn.classList.remove('is-open');
            this.btn.setAttribute('aria-expanded', 'false');
            this.menu.classList.remove('show');
            this.menu.style.display = 'none';
        },

        switchTab(tab, btnElement) {
            if (this.currentTab === tab) return;
            this.currentTab = tab;

            if (this.menu) {
                this.menu.querySelectorAll('.inbox-dd-tab').forEach(b => b.classList.remove('active'));
            }
            if (btnElement) {
                btnElement.classList.add('active');
            }
            this.render();
        },

        async fetchData(force = false) {
            if (this.isFetching) return;
            this.isFetching = true;

            if (this.refreshBtn) {
                const icon = this.refreshBtn.querySelector('i');
                if (icon) icon.classList.add('fa-spin');
            }

            // Show skeleton if no cached data yet
            if (!this.cachedData || force) {
                this.showSkeleton();
            }

            try {
                const baseUrl = (typeof window !== 'undefined' && window.BASE_URL) ? window.BASE_URL.replace(/\/+$/, '') : '';
                const endpoint = baseUrl ? `${baseUrl}/app/controllers/inbox_preview.php` : 'app/controllers/inbox_preview.php';
                const res = await fetch(`${endpoint}?t=${Date.now()}`, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
                });

                if (!res.ok) throw new Error('Network error ' + res.status);
                const data = await res.json();

                if (data.success) {
                    this.cachedData = Array.isArray(data.items) ? data.items : [];
                    this.isMember = Boolean(data.is_member);
                    this.lastFetched = Date.now();
                    this.updateBadges(data.pending_count || 0);
                    this.render();
                } else {
                    this.showError('Unable to load messages.');
                }
            } catch (err) {
                console.warn('[InboxDropdown] fetch error:', err);
                if (!this.cachedData) {
                    this.showError('Failed to connect to inbox.');
                }
            } finally {
                this.isFetching = false;
                if (this.refreshBtn) {
                    const icon = this.refreshBtn.querySelector('i');
                    if (icon) icon.classList.remove('fa-spin');
                }
            }
        },

        updateBadges(pendingCount) {
            const unreadItems = (this.cachedData || []).filter(it => it.is_unread !== undefined ? Boolean(it.is_unread) : Boolean(it.is_pending)).length;
            if (this.readAllBtn) {
                this.readAllBtn.disabled = unreadItems <= 0;
            }

            // Update dropdown pill
            if (this.countPill) {
                if (pendingCount > 0) {
                    this.countPill.textContent = pendingCount + (this.isMember ? ' unread' : ' pending');
                    this.countPill.style.display = 'inline-flex';
                } else {
                    this.countPill.style.display = 'none';
                }
            }

            // Update topbar inbox badge
            const topbarBadge = document.getElementById('badge-inquiries');
            if (topbarBadge) {
                if (pendingCount > 0) {
                    topbarBadge.textContent = pendingCount > 99 ? '99+' : pendingCount;
                    topbarBadge.style.display = 'inline-flex';
                } else {
                    topbarBadge.style.display = 'none';
                }
            }
        },

        render() {
            if (!this.list) return;

            const items = this.cachedData || [];
            const isItemUnread = (it) => (it.is_unread !== undefined ? Boolean(it.is_unread) : Boolean(it.is_pending));
            const unreadTotal = items.filter(isItemUnread).length;
            if (this.readAllBtn) {
                this.readAllBtn.disabled = unreadTotal <= 0;
            }

            const filtered = this.currentTab === 'pending'
                ? items.filter(isItemUnread)
                : items;

            if (filtered.length === 0) {
                const isPendingTab = (this.currentTab === 'pending');
                this.list.innerHTML = `
                    <div class="inbox-dd-empty">
                        <i class="fa-solid fa-envelope-open-text"></i>
                        <div class="inbox-dd-empty-title">${isPendingTab ? 'No Unread Notifications' : 'All Caught Up'}</div>
                        <div class="inbox-dd-empty-sub">
                            ${isPendingTab ? 'Everything in your inbox has been marked as read.' : 'Your inbox has no recent messages or notifications.'}
                        </div>
                    </div>
                `;
                return;
            }

            let html = '';
            filtered.forEach(it => {
                const escapedName = this.escapeHtml(it.name || 'Parishioner');
                const escapedType = this.escapeHtml(it.request_type || 'General');
                const escapedCategory = it.category ? this.escapeHtml(it.category) : '';
                const escapedChapel = it.chapel_name ? this.escapeHtml(it.chapel_name) : '';
                const escapedSnippet = this.escapeHtml(it.snippet || '');
                const escapedTime = this.escapeHtml(it.time_ago || '');
                const isUnread = isItemUnread(it);
                const stateClass = isUnread ? 'is-unread' : 'is-read';
                const stateBadge = isUnread
                    ? '<span class="inbox-state-pill unread" title="Unread"><span class="inbox-unread-dot"></span>Unread</span>'
                    : '<span class="inbox-state-pill read" title="Read"><i class="fa-solid fa-check"></i>Read</span>';

                html += `
                    <div class="inbox-dd-item ${stateClass}"
                         role="button"
                         tabindex="0"
                         title="${isUnread ? 'Unread — click to view' : 'Read — click to view'}"
                         onclick="InboxDropdown.handleItemClick(${it.id}, event)"
                         onkeydown="if(event.key==='Enter'||event.key===' '){ event.preventDefault(); InboxDropdown.handleItemClick(${it.id}, event); }">
                        <div class="inbox-dd-avatar ${it.icon_class || 'inbox-icon-general'}">
                            <i class="fa-solid ${it.icon || 'fa-circle-question'}"></i>
                        </div>
                        <div class="inbox-dd-content">
                            <div class="inbox-dd-row1">
                                <span class="inbox-dd-name">${escapedName}</span>
                                <span class="inbox-dd-meta-right">
                                    ${stateBadge}
                                    <span class="inbox-dd-time">${escapedTime}</span>
                                </span>
                            </div>
                            <div class="inbox-dd-row2">
                                <span class="inbox-dd-type-badge">${escapedType}${escapedCategory ? ' • ' + escapedCategory : ''}</span>
                                ${escapedChapel ? `<span class="inbox-dd-chapel-pip">• ${escapedChapel}</span>` : ''}
                            </div>
                            <div class="inbox-dd-snippet">${escapedSnippet}</div>
                        </div>
                    </div>
                `;
            });

            this.list.innerHTML = html;
        },

        showSkeleton() {
            if (!this.list) return;
            this.list.innerHTML = `
                <div class="inbox-dd-skeleton">
                    <div class="inbox-skel-item">
                        <div class="inbox-skel-avatar"></div>
                        <div class="inbox-skel-lines">
                            <div class="inbox-skel-line w-75"></div>
                            <div class="inbox-skel-line w-50"></div>
                            <div class="inbox-skel-line w-90"></div>
                        </div>
                    </div>
                    <div class="inbox-skel-item">
                        <div class="inbox-skel-avatar"></div>
                        <div class="inbox-skel-lines">
                            <div class="inbox-skel-line w-60"></div>
                            <div class="inbox-skel-line w-40"></div>
                            <div class="inbox-skel-line w-80"></div>
                        </div>
                    </div>
                    <div class="inbox-skel-item">
                        <div class="inbox-skel-avatar"></div>
                        <div class="inbox-skel-lines">
                            <div class="inbox-skel-line w-75"></div>
                            <div class="inbox-skel-line w-50"></div>
                            <div class="inbox-skel-line w-60"></div>
                        </div>
                    </div>
                </div>
            `;
        },

        showError(msg) {
            if (!this.list) return;
            this.list.innerHTML = `
                <div class="inbox-dd-empty" style="padding: 28px 16px;">
                    <i class="fa-solid fa-triangle-exclamation" style="color: #ef4444; opacity: 0.7;"></i>
                    <div class="inbox-dd-empty-title">${this.escapeHtml(msg)}</div>
                    <button type="button" class="btn btn-sm btn-ghost" style="margin-top: 8px; font-size: 11px;" onclick="InboxDropdown.fetchData(true)">
                        <i class="fa-solid fa-rotate-right"></i> Try Again
                    </button>
                </div>
            `;
        },

        handleItemClick(id, e) {
            if (e) {
                e.preventDefault();
                e.stopPropagation();
            }
            this.close();

            const clickedItem = (this.cachedData || []).find(it => it.id == id);

            // Mark notification as read in background (POST + CSRF)
            try {
                const baseUrl = (typeof window !== 'undefined' && window.BASE_URL) ? window.BASE_URL.replace(/\/+$/, '') : '';
                const endpoint = baseUrl ? `${baseUrl}/app/controllers/inbox_preview.php` : 'app/controllers/inbox_preview.php';
                const csrfToken = (typeof window !== 'undefined' && window.CSRF_TOKEN) ? window.CSRF_TOKEN : '';
                const body = new URLSearchParams({ action: 'mark_read', id: String(id), csrf: csrfToken });
                fetch(endpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: body.toString()
                }).catch(() => {});
                if (clickedItem && (clickedItem.is_unread || clickedItem.is_pending)) {
                    clickedItem.is_unread = false;
                    if (this.isMember) {
                        clickedItem.is_pending = false;
                        const unreadRem = (this.cachedData || []).filter(it => it.is_unread).length;
                        this.updateBadges(unreadRem);
                    }
                    this.render();
                }
            } catch(err) {}

            if (clickedItem && clickedItem.target_url) {
                window.location.href = clickedItem.target_url;
                return;
            }

            const targetSection = 'public_inquiries';
            const currentSection = new URLSearchParams(window.location.search).get('section') || '';

            if (currentSection === targetSection) {
                // Already in public_inquiries: highlight matching row
                this.highlightRowInWorkspace(id);
            } else {
                // Navigate to public_inquiries
                if (typeof window.navigateToSection === 'function') {
                    window.navigateToSection(targetSection).then(() => {
                        setTimeout(() => this.highlightRowInWorkspace(id), 250);
                    });
                } else {
                    window.location.href = `dashboard.php?section=${targetSection}&inq_id=${id}`;
                }
            }
        },

        highlightRowInWorkspace(id) {
            const table = document.querySelector('.data-table');
            if (!table) return;

            // Search for row with matching inquiry id
            const rows = table.querySelectorAll('tbody tr.inq-row');
            for (const row of rows) {
                const onclickAttr = row.getAttribute('onclick') || '';
                if (onclickAttr.includes(`"inquiry_id":"${id}"`) || onclickAttr.includes(`"inquiry_id":${id}`) || onclickAttr.includes(`inquiry_id: ${id}`)) {
                    row.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    row.click();
                    row.classList.add('active-row');
                    return;
                }
            }
        },

        handleFullView(e) {
            if (e) {
                e.preventDefault();
                e.stopPropagation();
            }
            this.close();

            const targetSection = 'public_inquiries';
            if (typeof window.navigateToSection === 'function') {
                window.navigateToSection(targetSection);
            } else {
                window.location.href = 'dashboard.php?section=' + targetSection;
            }
        },

        async markAllRead(e) {
            if (e) {
                e.preventDefault();
                e.stopPropagation();
            }
            const items = this.cachedData || [];
            const ids = items.map(it => it.id).filter(Boolean).join(',');
            if (this.readAllBtn) {
                this.readAllBtn.disabled = true;
            }
            try {
                const baseUrl = (typeof window !== 'undefined' && window.BASE_URL) ? window.BASE_URL.replace(/\/+$/, '') : '';
                const endpoint = baseUrl ? `${baseUrl}/app/controllers/inbox_preview.php` : 'app/controllers/inbox_preview.php';
                const csrfToken = (typeof window !== 'undefined' && window.CSRF_TOKEN) ? window.CSRF_TOKEN : '';
                const body = new URLSearchParams({ action: 'mark_read', id: '0', ids, csrf: csrfToken });
                const res = await fetch(endpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: body.toString()
                });
                const data = await res.json().catch(() => ({}));
                if (res.ok && data.success) {
                    items.forEach(it => {
                        it.is_unread = false;
                        if (this.isMember) {
                            it.is_pending = false;
                            it.status = 'Read';
                        }
                    });
                    if (this.isMember) {
                        this.updateBadges(0);
                    } else {
                        const unreadRem = items.filter(it => it.is_unread).length;
                        if (this.readAllBtn) this.readAllBtn.disabled = unreadRem <= 0;
                    }
                    this.render();
                } else if (this.readAllBtn) {
                    this.readAllBtn.disabled = false;
                }
            } catch (err) {
                if (this.readAllBtn) this.readAllBtn.disabled = false;
            }
        },

        escapeHtml(str) {
            if (!str) return '';
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }
    };

    // Expose to window for inline HTML onclick handlers
    window.InboxDropdown = InboxDropdown;
    window.toggleInboxDropdown = function(e) { InboxDropdown.toggle(e); };
    window.refreshInboxDropdown = function(e) { e && e.stopPropagation(); InboxDropdown.fetchData(true); };
    window.switchInboxDdTab = function(tab, btn) { InboxDropdown.switchTab(tab, btn); };
    window.handleInboxFullView = function(e) { InboxDropdown.handleFullView(e); };
    window.markAllInboxRead = function(e) { InboxDropdown.markAllRead(e); };

    // Auto-initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => InboxDropdown.init());
    } else {
        InboxDropdown.init();
    }
})();
