/**
 * Unified Live Pre-Search Autocomplete (DOM & Server Modes)
 *
 * Declaratively attaches clean, keyboard-navigable dropdown suggestions
 * to any input annotated with data-ac-mode="dom" or data-ac-mode="server".
 */
(function () {
    'use strict';

    let activeInput = null;
    let activeDropdown = null;
    let activeIdx = -1;
    let debounceTimer = null;
    let abortCtrl = null;

    function escHtml(str) {
        return String(str || '').replace(/[&<>"']/g, function (ch) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch];
        });
    }

    function highlightMatch(text, query) {
        const safe = escHtml(text);
        const q = (query || '').trim();
        if (!q) return safe;
        const escapedRe = q.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        try {
            return safe.replace(
                new RegExp('(' + escapedRe + ')', 'ig'),
                '<mark style="background: rgba(201,168,76,0.28); color: #111111; padding: 0 2px; border-radius: 3px; font-weight: 700;">$1</mark>'
            );
        } catch (e) {
            return safe;
        }
    }

    function ensureDropdown() {
        if (!activeDropdown) {
            activeDropdown = document.createElement('div');
            activeDropdown.id = 'parish-global-ac-dropdown';
            activeDropdown.setAttribute('role', 'listbox');
            activeDropdown.style.cssText = [
                'display: none',
                'position: fixed',
                'z-index: 9999',
                'max-height: 290px',
                'overflow-y: auto',
                'background: var(--surface, #FFFFFF)',
                'border: 1px solid var(--border, #E4E4E7)',
                'border-radius: 8px',
                'box-shadow: 0 12px 28px rgba(0,0,0,0.14)',
                'box-sizing: border-box',
                'font-family: inherit'
            ].join(';');
            document.body.appendChild(activeDropdown);
        }
        return activeDropdown;
    }

    function positionDropdown(input) {
        const box = ensureDropdown();
        const rect = input.getBoundingClientRect();
        const minW = Math.max(rect.width, 280);
        const maxW = Math.min(minW, window.innerWidth - 20);
        let left = rect.left;
        if (left + maxW > window.innerWidth - 10) {
            left = Math.max(10, window.innerWidth - maxW - 10);
        }
        box.style.left = left + 'px';
        box.style.top = (rect.bottom + 4) + 'px';
        box.style.width = maxW + 'px';
    }

    function hideDropdown() {
        if (activeDropdown) {
            activeDropdown.style.display = 'none';
            activeDropdown.innerHTML = '';
        }
        activeIdx = -1;
        activeInput = null;
    }

    function extractDomItems(input) {
        const targetSel = input.getAttribute('data-ac-target');
        const preset = input.getAttribute('data-ac-extract') || 'default';
        if (!targetSel) return [];

        const nodes = Array.from(document.querySelectorAll(targetSel));
        const items = [];
        const seen = new Set();

        nodes.forEach(function (el) {
            let label = '';
            let value = '';
            let sub = '';
            let badge = '';
            let badgeColor = '#52525b';
            let badgeBg = '#f4f4f5';
            let searchText = '';

            if (preset === 'donation') {
                let d = null;
                try { d = JSON.parse(el.getAttribute('data-donation') || 'null'); } catch (e) {}
                if (d) {
                    label = (d.donor_name || '').trim() || 'Anonymous Donor';
                    value = (d.donor_name || '').trim() || (d.reference_id || '');
                    sub = [d.reference_id || '', d.purpose || ''].filter(Boolean).join(' · ');
                    if (d.amount !== undefined && d.amount !== null) {
                        badge = '₱' + Number(d.amount).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                        badgeColor = '#111111';
                        badgeBg = '#f4f4f5';
                    }
                    searchText = [label, d.reference_id, d.donor_ref_id, d.purpose, d.chapel_name].join(' ');
                }
            } else if (preset === 'bulletin') {
                const tEl = el.querySelector('.bulletin-row-title');
                const sEl = el.querySelector('.bulletin-row-subtitle');
                const bEl = el.querySelector('.bulletin-audience-badge');
                label = tEl ? tEl.textContent.trim() : '';
                value = label;
                sub = sEl ? sEl.textContent.trim().slice(0, 80) : '';
                badge = bEl ? bEl.textContent.trim() : '';
                searchText = [label, sub, badge].join(' ');
            } else if (preset === 'user') {
                const cells = el.cells || [];
                const nameCell = cells[0] ? cells[0].innerText.replace(/\s+/g, ' ').trim() : '';
                const lines = (cells[0] ? cells[0].innerText : '').split('\n').map(function (s) { return s.trim(); }).filter(Boolean);
                label = lines[0] || el.dataset.name || nameCell;
                value = label;
                sub = lines.slice(1, 3).join(' · ');
                badge = cells[1] ? cells[1].innerText.replace(/\s+/g, ' ').trim() : '';
                searchText = [label, sub, el.dataset.name || '', el.dataset.email || '', nameCell].join(' ');
            } else if (preset === 'chapel') {
                const cells = el.cells || [];
                const lines = (cells[0] ? cells[0].innerText : '').split('\n').map(function (s) { return s.trim(); }).filter(Boolean);
                label = lines[0] || el.dataset.name || '';
                value = label;
                const loc = cells[1] ? cells[1].innerText.replace(/\s+/g, ' ').trim() : '';
                const saint = cells[2] ? cells[2].innerText.replace(/\s+/g, ' ').trim() : '';
                sub = [loc, saint].filter(Boolean).join(' · ');
                badge = (el.dataset.status || '').toUpperCase();
                searchText = [label, sub, el.dataset.name || ''].join(' ');
            } else if (preset === 'catalog') {
                const nameEl = el.querySelector('td:nth-child(2) div');
                label = (nameEl ? nameEl.textContent.trim() : '') || (el.dataset.name || '');
                value = label;
                sub = el.dataset.desc || '';
                const st = el.dataset.stockType || '';
                if (st === 'in') { badge = 'In Stock'; badgeColor = '#047857'; badgeBg = '#d1fae5'; }
                else if (st === 'low') { badge = 'Low Stock'; badgeColor = '#b45309'; badgeBg = '#fef3c7'; }
                else if (st === 'out') { badge = 'Out of Stock'; badgeColor = '#b91c1c'; badgeBg = '#fee2e2'; }
                searchText = [label, sub, el.dataset.name || ''].join(' ');
            } else if (preset === 'sale') {
                const cells = el.cells || [];
                const buyer = cells[1] ? cells[1].innerText.replace(/\s+/g, ' ').trim() : (el.dataset.buyer || '');
                const item = cells[2] ? cells[2].innerText.replace(/\s+/g, ' ').trim() : (el.dataset.item || '');
                label = item || buyer;
                value = item || buyer;
                sub = buyer ? ('Buyer: ' + buyer) : '';
                badge = cells[4] ? cells[4].innerText.replace(/\s+/g, ' ').trim() : '';
                searchText = [item, buyer, el.dataset.item || '', el.dataset.buyer || ''].join(' ');
            } else if (preset === 'pos') {
                const titleEl = el.querySelector('.pos-card-title, div[style*="font-weight: 700"]');
                label = (titleEl ? titleEl.textContent.trim() : '') || (el.dataset.name || '');
                value = label;
                const metaText = el.innerText.replace(/\s+/g, ' ').trim();
                sub = metaText.replace(label, '').trim().slice(0, 60);
                searchText = [label, el.dataset.name || '', metaText].join(' ');
            } else if (preset === 'inquiry') {
                const cell = (el.cells && el.cells[1]) ? el.cells[1] : el;
                const lines = cell.innerText.split('\n').map(function (s) { return s.trim(); }).filter(Boolean);
                label = lines[0] || '';
                value = label;
                sub = lines.slice(1, 3).join(' · ');
                const statusCell = el.cells && el.cells[el.cells.length - 1];
                badge = statusCell ? statusCell.innerText.replace(/\s+/g, ' ').trim().slice(0, 20) : '';
                searchText = cell.innerText;
            } else {
                label = el.textContent.replace(/\s+/g, ' ').trim().slice(0, 80);
                value = label;
                searchText = label;
            }

            if (!label) return;
            const dedupeKey = label.toLowerCase() + '|' + sub.toLowerCase();
            if (seen.has(dedupeKey)) return;
            seen.add(dedupeKey);

            items.push({
                label: label,
                value: value || label,
                sub: sub,
                badge: badge,
                badgeColor: badgeColor,
                badgeBg: badgeBg,
                searchText: searchText.toLowerCase(),
                node: el
            });
        });

        return items;
    }

    function renderSuggestions(input, items, query, totalMatched) {
        const box = ensureDropdown();
        activeInput = input;
        activeIdx = -1;
        positionDropdown(input);

        const q = (query || '').trim();
        if (!items.length) {
            box.innerHTML = '<div style="padding: 10px 12px; font-size: 12px; color: var(--text2, #71717A);">No matches for "' + escHtml(q) + '".</div>';
            box.style.display = 'block';
            return;
        }

        const headerText = totalMatched > items.length
            ? ('Quick Suggestions (' + totalMatched + ' matches)')
            : ('Quick Suggestions (' + items.length + ')');

        const header = '<div style="padding: 6px 12px; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text2, #71717A); background: var(--bg3, #F4F4F5); border-bottom: 1px solid var(--border, #E4E4E7);">'
            + headerText + '</div>';

        const rowsHtml = items.map(function (item, idx) {
            const badgeHtml = item.badge
                ? '<span style="flex-shrink: 0; font-size: 10px; font-weight: 700; padding: 2px 7px; border-radius: 999px; color: ' + (item.badgeColor || '#52525b') + '; background: ' + (item.badgeBg || '#f4f4f5') + '; max-width: 110px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">' + escHtml(item.badge) + '</span>'
                : '';
            const subHtml = item.sub
                ? '<div style="font-size: 11px; color: var(--text2, #71717A); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">' + highlightMatch(item.sub, q) + '</div>'
                : '';

            return '<button type="button" class="parish-ac-item" data-ac-idx="' + idx + '" '
                + 'style="display: flex; align-items: center; justify-content: space-between; gap: 10px; width: 100%; text-align: left; padding: 8px 12px; border: none; border-bottom: 1px solid var(--border, #f4f4f5); background: var(--surface, #FFFFFF); cursor: pointer; font-family: inherit;">'
                + '<div style="min-width: 0; flex: 1;">'
                + '<div style="font-size: 12.5px; font-weight: 600; color: var(--text, #111111); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">' + highlightMatch(item.label, q) + '</div>'
                + subHtml
                + '</div>'
                + badgeHtml
                + '</button>';
        }).join('');

        box.innerHTML = header + rowsHtml;
        box.style.display = 'block';

        box.querySelectorAll('.parish-ac-item').forEach(function (btn, idx) {
            btn.addEventListener('mouseenter', function () {
                btn.style.background = 'var(--bg3, #F4F4F5)';
            });
            btn.addEventListener('mouseleave', function () {
                btn.style.background = (idx === activeIdx) ? 'var(--bg3, #F4F4F5)' : 'var(--surface, #FFFFFF)';
            });
            btn.addEventListener('mousedown', function (e) {
                e.preventDefault();
            });
            btn.addEventListener('click', function () {
                selectSuggestion(input, items[idx]);
            });
        });
    }

    function selectSuggestion(input, item) {
        if (!input || !item) return;
        input.value = item.value;
        hideDropdown();

        const mode = input.getAttribute('data-ac-mode');
        if (mode === 'dom') {
            input.dispatchEvent(new Event('input', { bubbles: true }));
            input.dispatchEvent(new Event('keyup', { bubbles: true }));
            if (item.node && typeof item.node.scrollIntoView === 'function') {
                item.node.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                const prevBg = item.node.style.backgroundColor;
                item.node.style.backgroundColor = 'rgba(254, 249, 195, 0.85)';
                setTimeout(function () {
                    item.node.style.backgroundColor = prevBg;
                }, 1100);
            }
        } else if (mode === 'server') {
            if (input.form) {
                input.form.submit();
            }
        }
    }

    function runDomSearch(input) {
        const q = input.value.trim();
        if (!q) {
            hideDropdown();
            return;
        }
        const qLower = q.toLowerCase();
        const all = extractDomItems(input);
        const matched = [];
        all.forEach(function (item) {
            if (!item.searchText.includes(qLower)) return;
            const isPrefix = item.label.toLowerCase().startsWith(qLower);
            matched.push({ score: isPrefix ? 0 : 1, item: item });
        });
        matched.sort(function (a, b) {
            return a.score - b.score || a.item.label.localeCompare(b.item.label);
        });
        const top = matched.slice(0, 8).map(function (m) { return m.item; });
        renderSuggestions(input, top, q, matched.length);
    }

    function runServerSearch(input) {
        const q = input.value.trim();
        if (!q) {
            if (debounceTimer) clearTimeout(debounceTimer);
            if (abortCtrl) abortCtrl.abort();
            hideDropdown();
            return;
        }
        const scope = input.getAttribute('data-ac-scope') || '';
        if (!scope) return;

        if (debounceTimer) clearTimeout(debounceTimer);
        debounceTimer = setTimeout(function () {
            if (abortCtrl) abortCtrl.abort();
            abortCtrl = new AbortController();

            let url = 'app/controllers/get_search_suggestions.php?scope='
                + encodeURIComponent(scope)
                + '&q=' + encodeURIComponent(q);

            const recTypeSel = document.querySelector('select[name="record_type"]');
            if (scope === 'records' && recTypeSel && recTypeSel.value) {
                url += '&record_type=' + encodeURIComponent(recTypeSel.value);
            }

            fetch(url, { signal: abortCtrl.signal, credentials: 'same-origin' })
                .then(function (r) { return r.ok ? r.json() : { suggestions: [] }; })
                .then(function (data) {
                    if (document.activeElement !== input) return;
                    const list = data.suggestions || [];
                    renderSuggestions(input, list, q, list.length);
                })
                .catch(function (err) {
                    if (err && err.name !== 'AbortError') {
                        hideDropdown();
                    }
                });
        }, 150);
    }

    function attachInput(input) {
        if (!input || input.dataset.acBound === '1') return;
        input.dataset.acBound = '1';
        input.setAttribute('autocomplete', 'off');

        const mode = input.getAttribute('data-ac-mode');

        input.addEventListener('input', function () {
            if (mode === 'dom') runDomSearch(input);
            else if (mode === 'server') runServerSearch(input);
        });

        input.addEventListener('focus', function () {
            if (input.value.trim() !== '') {
                if (mode === 'dom') runDomSearch(input);
                else if (mode === 'server') runServerSearch(input);
            }
        });

        input.addEventListener('keydown', function (e) {
            if (!activeDropdown || activeDropdown.style.display === 'none' || activeInput !== input) return;
            const btns = Array.from(activeDropdown.querySelectorAll('.parish-ac-item'));
            if (!btns.length) {
                if (e.key === 'Escape') hideDropdown();
                return;
            }
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                activeIdx = (activeIdx + 1) % btns.length;
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                activeIdx = (activeIdx <= 0) ? (btns.length - 1) : (activeIdx - 1);
            } else if (e.key === 'Enter' && activeIdx >= 0 && btns[activeIdx]) {
                e.preventDefault();
                btns[activeIdx].click();
                return;
            } else if (e.key === 'Escape') {
                e.preventDefault();
                hideDropdown();
                return;
            } else {
                return;
            }

            btns.forEach(function (el, i) {
                el.style.background = (i === activeIdx) ? 'var(--bg3, #F4F4F5)' : 'var(--surface, #FFFFFF)';
            });
            btns[activeIdx].scrollIntoView({ block: 'nearest' });
        });
    }

    function initSearchAutocomplete() {
        document.querySelectorAll('input[data-ac-mode]').forEach(attachInput);
    }

    document.addEventListener('click', function (e) {
        if (!activeDropdown || activeDropdown.style.display === 'none') return;
        if (activeInput && (e.target === activeInput || activeInput.contains(e.target))) return;
        if (activeDropdown.contains(e.target)) return;
        hideDropdown();
    });

    window.addEventListener('resize', function () {
        if (activeInput && activeDropdown && activeDropdown.style.display !== 'none') {
            positionDropdown(activeInput);
        }
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initSearchAutocomplete);
    } else {
        initSearchAutocomplete();
    }

    window.initSearchAutocomplete = initSearchAutocomplete;
})();
