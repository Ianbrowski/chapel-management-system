/**
 * Dashboard Core Module
 * Global helpers, clock, dark mode, and UI utility functions.
 */

// Time & Clock
function updateClock() {
    const el = document.getElementById('currentTime');
    if (el) {
        const now = new Date();
        el.textContent = now.toLocaleTimeString([], {hour:'2-digit',minute:'2-digit'});
    }
}
updateClock();
setInterval(updateClock, 1000);

// Flash Messages
const flash = document.querySelector('.flash');
if (flash) setTimeout(() => flash.style.display='none', 5000);

// Helper: Escape HTML
function escHtml(str) {
    if (str == null) return '';
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// Helper: Pad Number
function pad(n) { return String(n).padStart(2,'0'); }

// Dark / Light Mode Theme Switcher
function initThemeToggle() {
    if (typeof ThemeSystem !== 'undefined' && typeof ThemeSystem.attachToggle === 'function') {
        ThemeSystem.attachToggle('darkModeBtn', 'darkModeIcon');
        return;
    }
    const btn = document.getElementById('darkModeBtn');
    const icon = document.getElementById('darkModeIcon');
    const currentTheme = document.documentElement.getAttribute('data-theme') || localStorage.getItem('theme') || 'dark';
    
    document.documentElement.setAttribute('data-theme', currentTheme);
    
    if (icon) {
        if (currentTheme === 'dark') {
            icon.className = 'fa-regular fa-sun';
        } else {
            icon.className = 'fa-regular fa-moon';
        }
    }

    if (btn) {
        btn.onclick = function() {
            const activeTheme = document.documentElement.getAttribute('data-theme') || 'dark';
            const newTheme = activeTheme === 'dark' ? 'light' : 'dark';
            
            document.documentElement.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);
            
            if (icon) {
                icon.className = newTheme === 'dark' ? 'fa-regular fa-sun' : 'fa-regular fa-moon';
            }
        };
    }
}

document.addEventListener('DOMContentLoaded', initThemeToggle);

/**
 * ==============================================================================
 * UNIVERSAL INPUT GUARDIAN & FORMATTER
 * Enforces unbuggable inputs across all forms, modals, drawers, and workspaces:
 * - Philippine Phone/Tel Mask: 09XX-XXX-XXXX (11 digits max, auto-hyphenated)
 * - Number Bounds & Character Guard: Disallows 'e', 'E', '+', clamps to min/max
 * - Safe Date Clamping: Ensures 1900-01-01 to 2099-12-31 to prevent DB corruptions
 * - Textfield & Textarea Guard: Prevents length overflows and script injection
 * ==============================================================================
 */

// Helper: Format Philippine Mobile/Tel number into 09XX-XXX-XXXX
function formatTelMask(input) {
    if (!input) return '';
    let val = typeof input === 'string' ? input : (input.value || '');
    let digits = val.replace(/\D/g, '');
    
    // Normalise international +63 or 63 prefix to 0
    if (digits.startsWith('63') && digits.length >= 12) {
        digits = '0' + digits.substring(2);
    }
    
    // Limit to 11 digits maximum (standard Philippine mobile / area format)
    if (digits.length > 11) digits = digits.substring(0, 11);
    
    let formatted = '';
    if (digits.length > 7) {
        formatted = digits.substring(0, 4) + '-' + digits.substring(4, 7) + '-' + digits.substring(7);
    } else if (digits.length > 4) {
        formatted = digits.substring(0, 4) + '-' + digits.substring(4);
    } else {
        formatted = digits;
    }
    
    if (typeof input !== 'string' && input.tagName) {
        input.value = formatted;
        input.setAttribute('maxlength', '15');
        input.setAttribute('inputmode', 'tel');
        if (input.hasAttribute('pattern') && input.getAttribute('pattern') === '[0-9]{11}') {
            input.setAttribute('pattern', '[0-9\\+\\s\\-]{10,20}');
            input.setAttribute('title', 'Enter valid mobile number (e.g. 0912-345-6789 or 09171234567)');
        }
    }
    return formatted;
}
window.formatTelMask = formatTelMask;

// Matcher: Check if an input represents a phone/contact number
function isPhoneInput(el) {
    if (!el || el.tagName !== 'INPUT') return false;
    if (el.type === 'tel' || el.classList.contains('tel-mask')) return true;
    const name = (el.name || '').toLowerCase();
    const id = (el.id || '').toLowerCase();
    return (
        name.includes('phone') || name.includes('contact') || name.includes('mobile') || name.includes('tel_no') ||
        id.includes('phone') || id.includes('contact') || id.includes('mobile') || id.includes('tel_no')
    );
}

// Global Delegated Input Listener
document.addEventListener('input', function(e) {
    const target = e.target;
    if (!target || !target.tagName) return;

    // 1. Universal Phone Formatting & Limiter
    if (isPhoneInput(target)) {
        formatTelMask(target);
        return;
    }

    // 2. Number bounds clamping on direct typing
    if (target.tagName === 'INPUT' && target.type === 'number') {
        const max = target.getAttribute('max');
        if (max !== null && max !== '') {
            const numMax = parseFloat(max);
            if (!isNaN(numMax) && parseFloat(target.value) > numMax) {
                target.value = max;
            }
        }
        return;
    }

    // 3. Name & Plain text sanitation (strip dangerous <script> or < tags)
    if (target.tagName === 'INPUT' && (target.type === 'text' || target.type === 'search')) {
        if (target.value && /<script|<\/script/i.test(target.value)) {
            target.value = target.value.replace(/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/gi, '');
        }
    }
}, true);

// Global Delegated Keydown Listener (Block illegal keys on numbers and phones)
document.addEventListener('keydown', function(e) {
    const target = e.target;
    if (!target || !target.tagName) return;

    // For Number inputs: block exponential notation 'e', 'E', and '+'
    if (target.tagName === 'INPUT' && target.type === 'number') {
        if (e.key === 'e' || e.key === 'E' || e.key === '+') {
            e.preventDefault();
            return;
        }
        // Block minus if min is 0 or greater (or not specified)
        const min = target.getAttribute('min');
        const minVal = min !== null ? parseFloat(min) : 0;
        if (minVal >= 0 && (e.key === '-' || e.key === 'Minus')) {
            e.preventDefault();
            return;
        }
    }

    // For Phone inputs: block non-numeric keystrokes
    if (isPhoneInput(target)) {
        // Allow navigation, control keys, and shortcuts
        if (
            e.key === 'Backspace' || e.key === 'Delete' || e.key === 'Tab' ||
            e.key === 'Enter' || e.key === 'Escape' || e.key === 'ArrowLeft' ||
            e.key === 'ArrowRight' || e.key === 'Home' || e.key === 'End' ||
            e.ctrlKey || e.metaKey
        ) {
            return;
        }
        // If not a digit, block key
        if (!/^[0-9]$/.test(e.key)) {
            e.preventDefault();
        }
    }
}, true);

// Global Delegated Change / Blur Listener (Clamp min values and dates)
document.addEventListener('change', function(e) {
    const target = e.target;
    if (!target || !target.tagName) return;

    // Clamping min on numbers when user finishes typing
    if (target.tagName === 'INPUT' && target.type === 'number') {
        const min = target.getAttribute('min');
        if (min !== null && min !== '' && target.value !== '') {
            const numMin = parseFloat(min);
            if (!isNaN(numMin) && parseFloat(target.value) < numMin) {
                target.value = min;
            }
        }
    }

    // Date sanity check (keep within 1900-01-01 to 2099-12-31)
    if (target.tagName === 'INPUT' && target.type === 'date' && target.value) {
        const parts = target.value.split('-');
        if (parts.length === 3) {
            let year = parseInt(parts[0], 10);
            if (year < 1900) year = 1900;
            if (year > 2099) year = 2099;
            target.value = `${year}-${parts[1]}-${parts[2]}`;
        }
    }
}, true);

// Initialize sensible defaults for all inputs in the DOM
function applyUniversalFormSafeguards(root) {
    const context = root || document;
    
    // Apply defaults to Date inputs
    context.querySelectorAll('input[type="date"]').forEach(el => {
        if (!el.hasAttribute('min')) el.setAttribute('min', '1900-01-01');
        if (!el.hasAttribute('max')) el.setAttribute('max', '2099-12-31');
    });

    // Apply defaults to Text inputs without explicit maxlength
    context.querySelectorAll('input[type="text"]').forEach(el => {
        if (!el.hasAttribute('maxlength') && !el.classList.contains('no-limiter')) {
            el.setAttribute('maxlength', '150');
        }
    });

    // Apply defaults to Textarea without explicit maxlength
    context.querySelectorAll('textarea').forEach(el => {
        if (!el.hasAttribute('maxlength') && !el.classList.contains('no-limiter')) {
            el.setAttribute('maxlength', '2000');
        }
    });

    // Format any prefilled phone fields
    context.querySelectorAll('input').forEach(el => {
        if (isPhoneInput(el) && el.value) {
            formatTelMask(el);
        }
    });
}

// Run on load and observe dynamically loaded modals or drawers
document.addEventListener('DOMContentLoaded', function() {
    applyUniversalFormSafeguards(document);

    // Mutation observer for dynamically inserted modals / forms
    if (window.MutationObserver) {
        const observer = new MutationObserver(mutations => {
            for (let mutation of mutations) {
                if (mutation.addedNodes.length) {
                    mutation.addedNodes.forEach(node => {
                        if (node.nodeType === 1) { // ELEMENT_NODE
                            applyUniversalFormSafeguards(node);
                        }
                    });
                }
            }
        });
        observer.observe(document.body, { childList: true, subtree: true });
    }
});

/**
 * Universal Sort Toggle Helper (Ascending / Descending Icon Button)
 * Flips hidden input between date_desc and date_asc and automatically submits parent form.
 */
function toggleSortDirection(btn, inputId, defaultDesc = 'date_desc', defaultAsc = 'date_asc') {
    const input = typeof inputId === 'string' ? document.getElementById(inputId) : inputId;
    if (!input) return;
    const current = (input.value || '').trim();
    input.value = (current === defaultAsc) ? defaultDesc : defaultAsc;
    const form = btn.closest('form');
    if (form) {
        if (typeof form.requestSubmit === 'function') {
            form.requestSubmit();
        } else {
            form.submit();
        }
    }
}

/**
 * ==============================================================================
 * CLEAN URL & GET FORM SANITIZER
 * Prevents URL cluttering by omitting empty or default fields during GET form
 * submissions, and tidies existing address bar URLs on page load.
 * ==============================================================================
 */
(function() {
    // 1. Clean GET forms right before submit
    document.addEventListener('submit', function(e) {
        const form = e.target;
        if (!form || (form.method && form.method.toUpperCase() !== 'GET')) return;

        const disabledElements = [];
        const elements = Array.from(form.elements);

        elements.forEach(el => {
            if (!el.name || el.disabled) return;
            const val = (el.value != null ? String(el.value) : '').trim();
            const name = el.name;

            // Strip blank inputs or default non-filtering values
            const isOmitCandidate = (
                val === '' ||
                (name === 'ev_venue' && val === 'all') ||
                (name === 'ev_sort' && val === 'date_desc') ||
                (name === 'sort' && val === 'date_desc') ||
                (name === 'ev_tab' && val === 'active')
            );

            if (isOmitCandidate) {
                el.disabled = true;
                disabledElements.push(el);
            }
        });

        // Re-enable in the next tick to ensure no inputs stay permanently disabled
        setTimeout(() => {
            disabledElements.forEach(el => { el.disabled = false; });
        }, 300);
    }, false);

    // 2. Clean URL query string in address bar on page load without reload
    function sanitizeCurrentUrl() {
        if (!window.location.search || !window.history || !window.history.replaceState) return;

        try {
            const params = new URLSearchParams(window.location.search);
            let hasChanged = false;

            const keysToDelete = [];
            params.forEach((val, key) => {
                const trimmed = (val || '').trim();
                if (
                    trimmed === '' ||
                    (key === 'ev_venue' && trimmed === 'all') ||
                    (key === 'ev_sort' && trimmed === 'date_desc') ||
                    (key === 'sort' && trimmed === 'date_desc') ||
                    (key === 'ev_tab' && trimmed === 'active')
                ) {
                    keysToDelete.push(key);
                }
            });

            if (keysToDelete.length > 0) {
                keysToDelete.forEach(k => params.delete(k));
                const newQuery = params.toString();
                const newUrl = window.location.pathname + (newQuery ? '?' + newQuery : '') + window.location.hash;
                window.history.replaceState(null, '', newUrl);
            }
        } catch (err) {
            // Non-critical, ignore if URLSearchParams fails
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', sanitizeCurrentUrl);
    } else {
        sanitizeCurrentUrl();
    }
})();

