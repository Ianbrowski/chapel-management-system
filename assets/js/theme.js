/**
 * ====================================================================
 * THEME.JS — Unified Theme Management Engine
 * Chapel Information Management System (Capstone Project)
 * ====================================================================
 * Handles persistent dark/light theme switching:
 * 1. Reads 'theme' from localStorage.
 * 2. Falls back to window.matchMedia('(prefers-color-scheme: dark)') if no stored preference.
 * 3. Applies data-theme attribute to <html> (document.documentElement).
 * 4. Syncs toggle button icons and dispatches 'themechange' event.
 */

(function (root, factory) {
    if (typeof define === 'function' && define.amd) {
        define([], factory);
    } else if (typeof exports === 'object') {
        module.exports = factory();
    } else {
        root.ThemeSystem = factory();
    }
}(typeof self !== 'undefined' ? self : this, function () {
    'use strict';

    const STORAGE_KEY = 'theme';
    const THEME_DARK = 'dark';
    const THEME_LIGHT = 'light';

    /**
     * Resolves the active theme.
     * Hierarchy: 1. localStorage -> 2. OS preference (prefers-color-scheme) -> 3. 'dark' fallback
     * @returns {'dark' | 'light'}
     */
    function getPreferredTheme() {
        const stored = localStorage.getItem(STORAGE_KEY);
        if (stored === THEME_DARK || stored === THEME_LIGHT) {
            return stored;
        }
        if (window.matchMedia && window.matchMedia('(prefers-color-scheme: light)').matches) {
            return THEME_LIGHT;
        }
        return THEME_DARK;
    }

    /**
     * Applies the specified theme to <html> and updates storage.
     * @param {'dark' | 'light'} theme 
     * @param {boolean} [persist=true] 
     */
    function setTheme(theme, persist = true) {
        const activeTheme = (theme === THEME_LIGHT) ? THEME_LIGHT : THEME_DARK;
        document.documentElement.setAttribute('data-theme', activeTheme);
        
        if (persist) {
            try {
                localStorage.setItem(STORAGE_KEY, activeTheme);
            } catch (e) {
                console.warn('[ThemeSystem] Unable to save to localStorage:', e);
            }
        }

        updateToggleIcons(activeTheme);

        // Dispatch an event so charts and sub-modules can recalculate styles if needed
        const event = new CustomEvent('themechange', {
            detail: { theme: activeTheme }
        });
        document.dispatchEvent(event);
    }

    /**
     * Toggles between 'dark' and 'light'.
     * @returns {'dark' | 'light'} The new active theme
     */
    function toggleTheme() {
        const current = document.documentElement.getAttribute('data-theme') || getPreferredTheme();
        const next = (current === THEME_DARK) ? THEME_LIGHT : THEME_DARK;
        setTheme(next, true);
        return next;
    }

    /**
     * Updates icon elements matching id or selector.
     * @param {'dark' | 'light'} theme 
     */
    function updateToggleIcons(theme) {
        const icons = document.querySelectorAll('#darkModeIcon, .theme-toggle-icon, .floating-theme-toggle i');
        icons.forEach(icon => {
            if (theme === THEME_DARK) {
                // When in dark mode, button indicates action to switch to light (sun)
                icon.className = 'fa-solid fa-sun';
            } else {
                // When in light mode, button indicates action to switch to dark (moon)
                icon.className = 'fa-solid fa-moon';
            }
        });
    }

    /**
     * Attaches click listener to toggle button(s).
     * @param {string} [buttonId='darkModeBtn'] 
     * @param {string} [iconId='darkModeIcon'] 
     */
    function attachToggle(buttonId = 'darkModeBtn', iconId = 'darkModeIcon') {
        const currentTheme = document.documentElement.getAttribute('data-theme') || getPreferredTheme();
        updateToggleIcons(currentTheme);

        const targets = document.querySelectorAll('#' + buttonId + ', #themeToggleBtn, #floatingThemeToggleBtn, .floating-theme-toggle');
        targets.forEach(btn => {
            btn.onclick = function (e) {
                e.preventDefault();
                toggleTheme();
            };
        });
    }

    /**
     * Fast-executing init for <head> to prevent FOUC (Flash of Unstyled Content).
     */
    function applyInitialTheme() {
        const theme = getPreferredTheme();
        document.documentElement.setAttribute('data-theme', theme);
    }

    // Auto-listen for OS preference changes if user hasn't explicitly set a preference
    if (window.matchMedia) {
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', e => {
            if (!localStorage.getItem(STORAGE_KEY)) {
                setTheme(e.matches ? THEME_DARK : THEME_LIGHT, false);
            }
        });
    }

    if (typeof document !== 'undefined') {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => attachToggle());
        } else {
            attachToggle();
        }
    }

    return {
        getPreferredTheme,
        setTheme,
        toggleTheme,
        attachToggle,
        applyInitialTheme
    };
}));
