/**
 * ====================================================================
 * LIQUID-SIDEBAR.JS — Organic Liquid Transition & Fuller Attached Sidebar
 * Coordinates the unclipped, centered liquid indicator, sub-topic morphing,
 * compact flyout menus, and full-height attached sidebar states.
 * ====================================================================
 */

(function () {
    'use strict';

    function isDesktop() {
        return window.innerWidth >= 1025;
    }

    function getSidebar() {
        return document.getElementById('sidebar');
    }

    function getNav() {
        return document.getElementById('sidebarNav');
    }

    function getIndicator() {
        return document.getElementById('liquidIndicator');
    }

    function getFlyout() {
        return document.getElementById('subtopicFlyout');
    }

    /**
     * Updates the position and shape of the liquid indicator directly relative to aside.sidebar.
     * For top-level items: 44px circular gold sphere.
     * For sub-items: morphs to 32px indented square tile aligned with tree lineage.
     * @param {HTMLElement|null} targetItem
     * @param {boolean} animate
     */
    function updateLiquidIndicator(targetItem = null, animate = true) {
        const sidebar = getSidebar();
        const nav = getNav();
        const indicator = getIndicator();

        if (!sidebar || !nav || !indicator) return;

        // If sidebar is expanded or we are on mobile, hide the liquid indicator
        if (sidebar.classList.contains('is-expanded') || !isDesktop()) {
            indicator.style.opacity = '0';
            return;
        }

        let activeItem = targetItem || nav.querySelector('.nav-item.active');

        // If sidebar is compact and activeItem is a sub-item, target its visible parent dropdown toggle on the rail!
        if (!sidebar.classList.contains('is-expanded') && activeItem && activeItem.classList.contains('sub-item')) {
            const parentToggle = activeItem.closest('.nav-dropdown')?.querySelector('.dropdown-toggle');
            if (parentToggle) {
                activeItem = parentToggle;
            }
        }

        if (!activeItem) {
            const activeDropdown = nav.querySelector('.nav-dropdown.has-active-child .dropdown-toggle');
            if (activeDropdown && !sidebar.classList.contains('is-expanded')) {
                activeItem = activeDropdown;
            } else {
                indicator.style.opacity = '0';
                return;
            }
        }

        const isParentOfActive = activeItem.classList.contains('dropdown-toggle') && activeItem.closest('.nav-dropdown')?.classList.contains('has-active-child');
        if (activeItem.classList.contains('dropdown-toggle') && !isParentOfActive) {
            indicator.style.opacity = '0';
            return;
        }

        const isSubItem = activeItem.classList.contains('sub-item');

        // Auto-scroll nav if item is partially outside visible area
        const itemTop = activeItem.offsetTop;
        const itemHeight = activeItem.offsetHeight || (isSubItem ? 36 : 48);
        const navScroll = nav.scrollTop;
        const navHeight = nav.clientHeight;

        if (itemTop < navScroll) {
            nav.scrollTop = Math.max(0, itemTop - 8);
        } else if (itemTop + itemHeight > navScroll + navHeight) {
            nav.scrollTop = itemTop + itemHeight - navHeight + 8;
        }

        // Compute coordinates relative to aside.sidebar container directly
        const sidebarRect = sidebar.getBoundingClientRect();
        const itemRect = activeItem.getBoundingClientRect();
        const navRect = nav.getBoundingClientRect();

        // Check if item is within the visible portion of sidebar-nav
        const isVisibleInNav = (itemRect.bottom > navRect.top + 5 && itemRect.top < navRect.bottom - 5);

        if (!isVisibleInNav) {
            indicator.style.opacity = '0';
            return;
        }

        // Apply morphing classes for sub-item vs top-level item
        if (isSubItem) {
            indicator.classList.add('is-sub-item');
        } else {
            indicator.classList.remove('is-sub-item');
        }

        // Half-height offset: 16 for 32px sub-item, 22 for 44px top-level
        const halfIndicator = isSubItem ? 16 : 22;
        const targetY = (itemRect.top - sidebarRect.top) + (itemRect.height / 2) - halfIndicator;

        if (!animate) {
            indicator.style.transition = 'none';
            indicator.style.transform = `translateY(${targetY}px)`;
            void indicator.offsetHeight; // Force reflow
            indicator.style.transition = '';
        } else {
            indicator.style.transform = `translateY(${targetY}px)`;
        }
        indicator.style.opacity = '1';

        // Update active icon inside the luminous circle
        const itemIcon = activeItem.querySelector('i');
        const circleIcon = indicator.querySelector('.liquid-circle-icon');
        if (itemIcon && circleIcon) {
            circleIcon.className = 'liquid-circle-icon ' + itemIcon.className;
        }
    }

    let flyoutHideTimer = null;

    function scheduleHideFlyout(delay = 200) {
        cancelHideFlyout();
        flyoutHideTimer = setTimeout(() => {
            hideSubtopicFlyout();
        }, delay);
    }

    function cancelHideFlyout() {
        if (flyoutHideTimer) {
            clearTimeout(flyoutHideTimer);
            flyoutHideTimer = null;
        }
    }

    /**
     * Shows a rich floating flyout popover for sub-topics in compact rail mode.
     * @param {HTMLElement} dropdownToggle
     */
    function showSubtopicFlyout(dropdownToggle) {
        const sidebar = getSidebar();
        const flyout = getFlyout();
        if (!sidebar || !flyout || !dropdownToggle) return;

        // Only show flyout on desktop compact mode
        if (sidebar.classList.contains('is-expanded') || !isDesktop()) return;

        const dropdown = dropdownToggle.closest('.nav-dropdown');
        if (!dropdown) return;

        cancelHideFlyout();

        const nav = getNav();
        if (nav) {
            nav.querySelectorAll('.nav-dropdown').forEach(d => {
                if (d !== dropdown) d.classList.remove('flyout-open');
            });
        }
        dropdown.classList.add('flyout-open');

        const title = dropdown.getAttribute('data-parent-title') || dropdownToggle.querySelector('span')?.textContent || 'Sub-topics';
        const icon = dropdown.getAttribute('data-parent-icon') || dropdownToggle.querySelector('i')?.className || 'fa-solid fa-folder-tree';

        const titleEl = document.getElementById('subtopicFlyoutTitle');
        const listEl = document.getElementById('subtopicFlyoutList');
        if (titleEl) {
            titleEl.innerHTML = `<i class="${icon}"></i> ${title}`;
        }

        if (listEl) {
            listEl.innerHTML = '';
            const subItems = dropdown.querySelectorAll('.sub-item');
            subItems.forEach(sub => {
                const section = sub.getAttribute('data-section');
                const href = sub.getAttribute('href');
                const subTitle = sub.querySelector('span')?.textContent || section;
                const subIcon = sub.querySelector('i')?.className || 'fa-solid fa-circle-dot';
                const subtitle = sub.getAttribute('data-subtitle') || '';
                const isActive = sub.classList.contains('active');

                const item = document.createElement('a');
                item.className = 'subtopic-flyout-item' + (isActive ? ' active' : '');
                item.href = href;
                item.setAttribute('data-section', section);
                item.innerHTML = `
                    <i class="${subIcon}"></i>
                    <div style="display:flex; flex-direction:column; gap:2px; flex:1;">
                        <div style="font-weight:600; color:${isActive ? '#B8913A' : '#F1F5F9'};">${subTitle}</div>
                        ${subtitle ? `<div style="font-size:11px; color:#94A3B8; font-weight:400;">${subtitle}</div>` : ''}
                    </div>
                    ${isActive ? '<span class="flyout-active-tag">ACTIVE</span>' : ''}
                `;

                item.addEventListener('click', function (e) {
                    e.preventDefault();
                    if (window.navigateToSection && section) {
                        window.navigateToSection(section, true);
                    } else {
                        window.location.href = href;
                    }
                    hideSubtopicFlyout();
                });

                listEl.appendChild(item);
            });
        }

        // Position flyout directly beside the dropdown toggle
        const toggleRect = dropdownToggle.getBoundingClientRect();
        flyout.setAttribute('data-active-parent', title);
        flyout.style.display = 'flex';
        const flyoutHeight = flyout.offsetHeight || 160;

        let topPos = toggleRect.top + (toggleRect.height / 2) - 40;
        topPos = Math.max(12, Math.min(topPos, window.innerHeight - flyoutHeight - 16));

        flyout.style.top = `${topPos}px`;
        flyout.style.left = '76px';

        const notch = flyout.querySelector('.subtopic-flyout-notch');
        if (notch) {
            const notchOffset = (toggleRect.top + toggleRect.height / 2) - topPos - 6;
            notch.style.top = `${Math.max(14, Math.min(notchOffset, flyoutHeight - 24))}px`;
        }
    }

    /**
     * Hides the sub-topics flyout popover.
     */
    function hideSubtopicFlyout() {
        cancelHideFlyout();
        const flyout = getFlyout();
        if (flyout) {
            flyout.style.display = 'none';
            flyout.removeAttribute('data-active-parent');
        }
        const nav = getNav();
        if (nav) {
            nav.querySelectorAll('.nav-dropdown').forEach(d => d.classList.remove('flyout-open'));
        }
    }

    /**
     * Toggles between compact liquid rail mode and expanded labels mode.
     */
    function toggleSidebarCompactMode(e) {
        if (e && e.preventDefault) e.preventDefault();
        const sidebar = getSidebar();
        if (!sidebar) return;

        if (!isDesktop()) {
            if (typeof closeSidebar === 'function') {
                closeSidebar();
            } else {
                sidebar.classList.remove('open');
                document.body.classList.remove('sidebar-open');
                const overlay = document.getElementById('overlayBg');
                if (overlay) overlay.classList.remove('open');
            }
            return;
        }

        hideSubtopicFlyout();

        const isCurrentlyExpanded = sidebar.classList.contains('is-expanded');

        if (isCurrentlyExpanded) {
            sidebar.classList.remove('is-expanded');
            document.body.classList.remove('sidebar-expanded');
            localStorage.setItem('cms_sidebar_mode', 'compact');
            setTimeout(() => {
                updateLiquidIndicator(null, false);
            }, 160);
            setTimeout(() => {
                updateLiquidIndicator(null, true);
            }, 360);
        } else {
            sidebar.classList.add('is-expanded');
            document.body.classList.add('sidebar-expanded');
            localStorage.setItem('cms_sidebar_mode', 'expanded');
            const indicator = getIndicator();
            if (indicator) indicator.style.opacity = '0';
        }

        updateToggleBtnState();
    }

    function updateToggleBtnState() {
        const toggleBtn = document.getElementById('sidebarModeToggle');
        if (!toggleBtn) return;
        const sidebar = getSidebar();
        if (!isDesktop()) {
            toggleBtn.setAttribute('data-tooltip', 'Close Navigation');
            toggleBtn.setAttribute('title', 'Close Navigation');
            toggleBtn.innerHTML = '<i class="fa-solid fa-xmark"></i>';
        } else {
            const isExp = sidebar && sidebar.classList.contains('is-expanded');
            toggleBtn.setAttribute('data-tooltip', isExp ? 'Collapse Navigation' : 'Expand Navigation');
            toggleBtn.setAttribute('title', isExp ? 'Collapse Navigation' : 'Expand Navigation');
            toggleBtn.innerHTML = '<i class="fa-solid fa-bars"></i>';
        }
    }

    /**
     * Initializes the sidebar on load.
     */
    function initLiquidSidebar() {
        const sidebar = getSidebar();
        const nav = getNav();
        if (!sidebar || !nav) return;

        const savedMode = localStorage.getItem('cms_sidebar_mode');

        if (isDesktop()) {
            if (savedMode === 'expanded') {
                sidebar.classList.add('is-expanded');
                document.body.classList.add('sidebar-expanded');
            } else {
                sidebar.classList.remove('is-expanded');
                document.body.classList.remove('sidebar-expanded');
            }
        }

        updateToggleBtnState();

        // Attach click listeners to destination nav items (exclude dropdown toggles)
        nav.querySelectorAll('.nav-item:not(.dropdown-toggle)').forEach(item => {
            item.addEventListener('click', function () {
                nav.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
                this.classList.add('active');

                // Update has-active-child on parent dropdown
                const parentDropdown = this.closest('.nav-dropdown');
                nav.querySelectorAll('.nav-dropdown').forEach(d => d.classList.remove('has-active-child'));
                if (parentDropdown) {
                    parentDropdown.classList.add('has-active-child');
                    parentDropdown.classList.add('open');
                }

                updateLiquidIndicator(this, true);
                hideSubtopicFlyout();
            });
        });

        // Auto-show sub-topics on hover without requiring clicks
        nav.querySelectorAll('.nav-dropdown').forEach(dropdown => {
            const toggle = dropdown.querySelector('.dropdown-toggle');
            if (!toggle) return;

            dropdown.addEventListener('mouseenter', () => {
                const s = getSidebar();
                if (!isDesktop() || !s) return;

                if (!s.classList.contains('is-expanded')) {
                    cancelHideFlyout();
                    showSubtopicFlyout(toggle);
                } else {
                    // Expanded mode: auto-open accordion on hover
                    dropdown.classList.add('open');
                }
            });

            dropdown.addEventListener('mouseleave', () => {
                const s = getSidebar();
                if (!isDesktop() || !s) return;

                if (!s.classList.contains('is-expanded')) {
                    scheduleHideFlyout(220);
                } else {
                    if (!dropdown.classList.contains('has-active-child')) {
                        dropdown.classList.remove('open');
                    }
                }
            });
        });

        // Hover handling directly on the floating flyout container
        const flyoutEl = getFlyout();
        if (flyoutEl) {
            flyoutEl.addEventListener('mouseenter', () => {
                cancelHideFlyout();
            });
            flyoutEl.addEventListener('mouseleave', () => {
                scheduleHideFlyout(200);
            });
        }

        // Close flyout immediately when hovering any non-dropdown destination item
        nav.querySelectorAll('.nav-item:not(.dropdown-toggle)').forEach(item => {
            item.addEventListener('mouseenter', () => {
                if (!item.closest('.subtopic-flyout')) {
                    cancelHideFlyout();
                    hideSubtopicFlyout();
                }
            });
        });

        // Synchronize active indicator circle hover state for tactile move-right feel
        nav.querySelectorAll('.nav-item').forEach(item => {
            item.addEventListener('mouseenter', function () {
                const indicator = getIndicator();
                if (!indicator) return;
                const isParentOfActive = this.classList.contains('dropdown-toggle') && this.closest('.nav-dropdown')?.classList.contains('has-active-child');
                if (this.classList.contains('active') || isParentOfActive) {
                    indicator.classList.add('is-hovered');
                }
            });

            item.addEventListener('mouseleave', function () {
                const indicator = getIndicator();
                if (indicator) {
                    indicator.classList.remove('is-hovered');
                }
            });
        });

        // Close flyout if mouse leaves sidebar rail completely
        sidebar.addEventListener('mouseleave', (e) => {
            const fl = getFlyout();
            if (e.relatedTarget && (e.relatedTarget === fl || fl?.contains(e.relatedTarget))) {
                return;
            }
            scheduleHideFlyout(200);
        });

        // Dismiss flyout on outside click
        document.addEventListener('click', (e) => {
            if (!e.target.closest('.subtopic-flyout') && !e.target.closest('.dropdown-toggle')) {
                hideSubtopicFlyout();
            }
        });

        // Keep indicator aligned on nav scroll & close flyout
        nav.addEventListener('scroll', () => {
            hideSubtopicFlyout();
            requestAnimationFrame(() => updateLiquidIndicator(null, false));
        }, { passive: true });

        // Keep indicator aligned on window resize
        window.addEventListener('resize', () => {
            hideSubtopicFlyout();
            updateToggleBtnState();
            requestAnimationFrame(() => {
                if (isDesktop() && !sidebar.classList.contains('is-expanded')) {
                    updateLiquidIndicator(null, false);
                } else {
                    const indicator = getIndicator();
                    if (indicator) indicator.style.opacity = '0';
                }
            });
        });

        // Initial indicator placement (instant, no jump)
        setTimeout(() => {
            updateLiquidIndicator(null, false);
        }, 120);
    }

    // Expose globally
    window.LiquidSidebar = {
        toggle: toggleSidebarCompactMode,
        update: updateLiquidIndicator,
        showFlyout: showSubtopicFlyout,
        hideFlyout: hideSubtopicFlyout,
        scheduleHide: scheduleHideFlyout,
        cancelHide: cancelHideFlyout
    };

    window.toggleSidebarCompactMode = toggleSidebarCompactMode;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initLiquidSidebar);
    } else {
        initLiquidSidebar();
    }
})();
