/**
 * Dashboard Calendar Module
 * Handles monthly grids and timeline views for both Pastors and Overview.
 */

let calYear  = new Date().getFullYear();
let calMonth = new Date().getMonth(); 
let ovYear   = new Date().getFullYear();
let ovMonth  = new Date().getMonth();

const typeColors = { 
    'Mass': '#000000', 
    'Wedding': '#000000', 
    'Baptism': '#000000', 
    'Funeral': '#71717a', 
    'Fiesta': '#000000', 
    'Confirmation': '#000000', 
    'Other': '#71717a' 
};
const scheduleDurationDefaults = {
    'Wedding': 120, 'Wedding (Holy Matrimony)': 120,
    'Mass': 90, 'Thanksgiving Mass': 90, 'Burial Mass': 90, 'Requiem Mass': 90,
    'Baptism': 60, 'Confirmation': 60, 'Funeral': 90, 'Blessings': 60, 'Fiesta': 120, 'Other': 60
};

const escHtmlSafe = (str) => {
    if (typeof escHtml === 'function') return escHtml(str);
    return (str || '').toString().replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
};

function localDateKey(date = new Date()) {
    return date.getFullYear() + '-' + String(date.getMonth() + 1).padStart(2, '0') + '-' + String(date.getDate()).padStart(2, '0');
}

function formatEventTime(timeStr) {
    if (!timeStr) return { timeFmt: '', isMorning: true, hour: 9 };
    try {
        const dt = new Date(timeStr.replace(' ', 'T'));
        if (isNaN(dt.getTime())) return { timeFmt: '', isMorning: true, hour: 9 };
        const hr = dt.getHours();
        const min = dt.getMinutes();
        const period = hr >= 12 ? 'PM' : 'AM';
        const displayHr = hr % 12 === 0 ? 12 : hr % 12;
        const displayMin = min === 0 ? ':00' : ':' + String(min).padStart(2, '0');
        return {
            timeFmt: `${displayHr}${displayMin} ${period}`,
            isMorning: hr < 12,
            hour: hr
        };
    } catch (e) {
        return { timeFmt: '', isMorning: true, hour: 9 };
    }
}

let calSelectedDate = localDateKey();

function calPrev() { 
    calMonth--; 
    if (calMonth < 0) { calMonth = 11; calYear--; } 
    renderCalendar(calYear, calMonth); 
}

function calNext() { 
    calMonth++; 
    if (calMonth > 11) { calMonth = 0; calYear++; } 
    renderCalendar(calYear, calMonth); 
}

function selectCalDay(dateStr) {
    calSelectedDate = dateStr;
    const dInput = document.getElementById('timeline-date');
    if (dInput) dInput.value = dateStr;
    renderCalendar(calYear, calMonth);
    renderTimeline(dateStr);

    if (window.innerWidth <= 768) {
        const detailCard = document.getElementById('sched-day-detail-card');
        if (detailCard) {
            detailCard.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
    }
}

function eventDurationMinutes(ev) {
    const custom = parseInt(ev.custom_duration || 0, 10);
    if (custom > 0) return custom;
    return scheduleDurationDefaults[ev.event_type] || 60;
}

function eventsOverlap(a, b) {
    if (!a.start_time || !b.start_time) return false;
    const aDateStr = a.start_time.substring(0, 10);
    const bDateStr = b.start_time.substring(0, 10);
    if (aDateStr !== bDateStr) return false;

    // Check if same celebrant (when viewing master schedule)
    if (a.pastor_id && b.pastor_id && a.pastor_id !== b.pastor_id) return false;

    const aStart = new Date(a.start_time.replace(' ', 'T'));
    const bStart = new Date(b.start_time.replace(' ', 'T'));
    if (isNaN(aStart.getTime()) || isNaN(bStart.getTime())) return false;

    // Cut-in-half operational timeslots: Morning (< 12:00) vs Afternoon (>= 12:00)
    const aIsMorning = (aStart.getHours() < 12);
    const bIsMorning = (bStart.getHours() < 12);
    return (aIsMorning === bIsMorning);
}

// ─── CHAPEL SWITCHER FILTER ENGINE ───
window.selectedChapelFilter = 'all';
window.selectedTypeFilter = 'all';
window.selectedTimeframeFilter = 'all';

function getChapelFilteredEvents() {
    let list = (typeof priestEvents !== 'undefined' && Array.isArray(priestEvents)) 
        ? priestEvents 
        : ((typeof pastorEvents !== 'undefined' && Array.isArray(pastorEvents)) ? pastorEvents : []);
    
    // 1. Chapel / Venue Filter
    if (window.selectedChapelFilter && window.selectedChapelFilter !== 'all') {
        if (window.selectedChapelFilter === 'cathedral' || window.selectedChapelFilter === '0') {
            list = list.filter(ev => !ev.chapel_id || ev.chapel_id == 0);
        } else {
            list = list.filter(ev => ev.chapel_id == window.selectedChapelFilter);
        }
    }

    // 2. Event Type Filter
    if (window.selectedTypeFilter && window.selectedTypeFilter !== 'all') {
        list = list.filter(ev => {
            const t = (ev.event_type || '').toLowerCase();
            const f = window.selectedTypeFilter.toLowerCase();
            return t === f || t.includes(f);
        });
    }

    // 3. Timeframe Filter
    if (window.selectedTimeframeFilter && window.selectedTimeframeFilter !== 'all') {
        const now = new Date();
        const todayStr = localDateKey(now);
        
        // Calculate week bounds
        const d = new Date(now);
        const day = d.getDay();
        const diff = d.getDate() - day + (day === 0 ? -6 : 1); // Monday start
        const monday = new Date(d.setDate(diff));
        monday.setHours(0, 0, 0, 0);
        const sunday = new Date(monday);
        sunday.setDate(sunday.getDate() + 6);
        sunday.setHours(23, 59, 59, 999);

        const nextMon = new Date(monday);
        nextMon.setDate(nextMon.getDate() + 7);
        const nextSun = new Date(sunday);
        nextSun.setDate(nextSun.getDate() + 7);

        list = list.filter(ev => {
            if (!ev.start_time) return false;
            const evDate = new Date(ev.start_time.replace(/-/g, '/'));
            if (isNaN(evDate.getTime())) return false;

            if (window.selectedTimeframeFilter === 'this_week') {
                return evDate >= monday && evDate <= sunday;
            } else if (window.selectedTimeframeFilter === 'next_week') {
                return evDate >= nextMon && evDate <= nextSun;
            } else if (window.selectedTimeframeFilter === 'this_month') {
                return evDate.getFullYear() === calYear && evDate.getMonth() === calMonth;
            } else if (window.selectedTimeframeFilter === 'upcoming') {
                return ev.start_time >= todayStr;
            } else if (window.selectedTimeframeFilter === 'past') {
                return ev.start_time < todayStr;
            }
            return true;
        });
    }

    return list;
}

function selectTypeFilter(typeVal) {
    window.selectedTypeFilter = typeVal || 'all';
    renderCalendar(calYear, calMonth);
    renderTimeline(calSelectedDate);
}
window.selectTypeFilter = selectTypeFilter;

function selectTimeframeFilter(tfVal) {
    window.selectedTimeframeFilter = tfVal || 'all';
    renderCalendar(calYear, calMonth);
    renderTimeline(calSelectedDate);
}
window.selectTimeframeFilter = selectTimeframeFilter;

function updateVenueThemeContext(filterId = window.selectedChapelFilter || 'all') {
    const colorMap = window.chapelColorMap || {};
    const nameMap  = window.chapelNameMap || {};
    const vColor   = colorMap[String(filterId)] || '#000000';
    const vName    = nameMap[String(filterId)] || '';

    // Overview elements
    const calCard      = document.getElementById('ov-cal-card');
    const venueBadge   = document.getElementById('ov-cal-venue-badge');
    const venueWrap    = document.getElementById('ov-venue-select-wrap');
    const venuePin     = document.getElementById('ov-venue-pin-icon');
    const calTitleIcon = document.getElementById('ov-cal-title-icon');

    // Pastoral calendar elements
    const schedCalCard  = document.getElementById('sched-cal-card');
    const calVenueWrap  = document.getElementById('cal-venue-select-wrap');
    const calVenuePin   = document.getElementById('cal-venue-pin-icon');
    const calVenueBadge = document.getElementById('cal-venue-badge');

    [venueWrap, calVenueWrap].forEach(wrap => {
        if (!wrap) return;
        wrap.style.borderColor = (filterId === 'all') ? 'var(--border)' : 'var(--accent)';
        wrap.style.boxShadow = 'none';
    });

    [venuePin, calVenuePin].forEach(pin => {
        if (!pin) return;
        pin.style.color = 'var(--accent)';
    });

    [calCard, schedCalCard].forEach(card => {
        if (!card) return;
        card.style.borderTop = '1px solid var(--border)';
    });

    if (calTitleIcon) {
        calTitleIcon.style.color = 'var(--accent)';
    }

    [venueBadge, calVenueBadge].forEach(badge => {
        if (!badge) return;
        if (filterId === 'all' || !vName) {
            badge.style.display = 'none';
        } else {
            badge.textContent = vName;
            badge.style.background = 'var(--hover-bg)';
            badge.style.color = 'var(--text)';
            badge.style.border = '1px solid var(--border)';
            badge.style.display = 'inline-flex';
        }
    });
}
window.updateVenueThemeContext = updateVenueThemeContext;

function selectChapelFilter(filterId) {
    window.selectedChapelFilter = filterId;
    
    // Sync dropdown filter if present
    const ovVenueSelect = document.getElementById('ov-cal-venue-select');
    if (ovVenueSelect && ovVenueSelect.value !== String(filterId)) {
        ovVenueSelect.value = String(filterId);
    }
    const calVenueSelect = document.getElementById('cal-venue-select');
    if (calVenueSelect && calVenueSelect.value !== String(filterId)) {
        calVenueSelect.value = String(filterId);
    }
    const tableVenueSelect = document.getElementById('table-venue-select');
    if (tableVenueSelect && tableVenueSelect.value !== String(filterId)) {
        tableVenueSelect.value = String(filterId);
    }

    // Apply Option 1: Calendar context theming based on active venue
    updateVenueThemeContext(filterId);

    // Update active bubble styling
    document.querySelectorAll('.chapel-switcher-bubble').forEach(btn => {
        const isMatch = (String(btn.getAttribute('data-chapel-id')) === String(filterId));
        btn.classList.toggle('active', isMatch);
        if (isMatch) {
            btn.style.background = 'var(--accent)';
            btn.style.color = '#111111';
            btn.style.borderColor = 'var(--accent)';
            btn.style.fontWeight = '600';
            btn.style.boxShadow = 'none';
            btn.style.transform = 'none';
        } else {
            btn.style.background = 'var(--bg2)';
            btn.style.color = 'var(--text2)';
            btn.style.borderColor = 'var(--border)';
            btn.style.fontWeight = '500';
            btn.style.boxShadow = 'none';
            btn.style.transform = 'none';
        }
    });

    // Re-render calendar & daily liturgical slots
    if (typeof renderCalendar === 'function') {
        renderCalendar(calYear, calMonth);
    }
    if (typeof renderTimeline === 'function') {
        const curDate = calSelectedDate || (new Date().toISOString().substring(0, 10));
        renderTimeline(curDate);
    }
    if (typeof renderOvCalendar === 'function') {
        renderOvCalendar();
    }

    // Filter master schedule table rows if on calendar page
    const tableRows = document.querySelectorAll('#events-master-tbody tr[data-chapel-id]');
    let visibleCount = 0;
    tableRows.forEach(row => {
        const rowCh = row.getAttribute('data-chapel-id');
        let show = false;
        if (!filterId || filterId === 'all') {
            show = true;
        } else if (filterId === 'cathedral' || filterId === '0') {
            show = (!rowCh || rowCh === '' || rowCh === '0');
        } else {
            show = (rowCh == filterId);
        }
        row.style.display = show ? '' : 'none';
        if (show) visibleCount++;
    });

    const emptyNotice = document.getElementById('events-filter-empty-row');
    if (emptyNotice) {
        emptyNotice.style.display = (tableRows.length > 0 && visibleCount === 0) ? '' : 'none';
    }

    // Retain filter state in URL without full refresh
    try {
        const url = new URL(window.location.href);
        if (filterId === 'all') {
            url.searchParams.delete('chapel_id');
        } else {
            url.searchParams.set('chapel_id', filterId);
        }
        window.history.replaceState({}, '', url.toString());
    } catch(e) {}
}

function calToday() {
    const now = new Date();
    calYear = now.getFullYear();
    calMonth = now.getMonth();
    calSelectedDate = localDateKey(now);
    const dInput = document.getElementById('timeline-date');
    if (dInput) dInput.value = calSelectedDate;
    renderCalendar(calYear, calMonth);
    renderTimeline(calSelectedDate);
}
window.calToday = calToday;

function calJump(y, m) {
    if (y !== undefined && y !== '') calYear = parseInt(y, 10);
    if (m !== undefined && m !== '') calMonth = parseInt(m, 10);
    renderCalendar(calYear, calMonth);
}
window.calJump = calJump;

function renderCalendar(year = calYear, month = calMonth) {
    const grid  = document.getElementById('cal-grid');
    const label = document.getElementById('cal-month-label');
    const monthSelect = document.getElementById('cal-month-select');
    const yearSelect  = document.getElementById('cal-year-select');
    if (!grid) return;

    calYear = year;
    calMonth = month;

    // Apply active venue theme colors to calendar frame and header
    updateVenueThemeContext();

    const monthNames = ['January','February','March','April','May','June','July','August','September','October','November','December'];
    if (label) label.textContent = monthNames[month] + ' ' + year;
    if (monthSelect) monthSelect.value = String(month);
    if (yearSelect) {
        if (!yearSelect.querySelector(`option[value="${year}"]`)) {
            const opt = document.createElement('option');
            opt.value = year;
            opt.textContent = year;
            yearSelect.appendChild(opt);
        }
        yearSelect.value = String(year);
    }

    const today     = new Date();
    const firstDay  = new Date(year, month, 1).getDay();
    const daysInMon = new Date(year, month + 1, 0).getDate();

    const totalSlots = firstDay + daysInMon;
    const numWeeks   = Math.ceil(totalSlots / 7);
    const isMobile   = (window.innerWidth <= 640);
    const rowHeight  = isMobile ? 'minmax(48px, 1fr)' : 'minmax(86px, 1fr)';
    grid.style.gridTemplateRows = `28px repeat(${numWeeks}, ${rowHeight})`;

    if (!calSelectedDate) {
        calSelectedDate = localDateKey(today);
    }

    const eventMap = {};
    const eventsList = getChapelFilteredEvents();

    eventsList.forEach(ev => {
        if (!ev.start_time) return;
        const d = ev.start_time.substring(0, 10);
        if (!eventMap[d]) eventMap[d] = [];
        eventMap[d].push(ev);
    });

    const conflictDates = new Set();
    eventsList.forEach((ev, i) => {
        eventsList.forEach((ev2, j) => {
            if (i === j) return;
            if (eventsOverlap(ev, ev2)) {
                conflictDates.add(ev.start_time.substring(0, 10));
            }
        });
    });

    let html = '';
    const dayHeaders = [
        { full: 'SUN', short: 'S' },
        { full: 'MON', short: 'M' },
        { full: 'TUE', short: 'T' },
        { full: 'WED', short: 'W' },
        { full: 'THU', short: 'T' },
        { full: 'FRI', short: 'F' },
        { full: 'SAT', short: 'S' }
    ];
    dayHeaders.forEach(dh => {
        html += `<div class="ov-cal-day-hdr"><span class="day-hdr-full">${dh.full}</span><span class="day-hdr-short">${dh.short}</span></div>`;
    });

    const esc = escHtmlSafe;
    const todayDateKey = localDateKey(today);

    // Faded trailing days from previous month
    const prevMonthDays = new Date(year, month, 0).getDate();
    for (let i = firstDay - 1; i >= 0; i--) { 
        const pd = prevMonthDays - i;
        html += `
            <div class="ov-cal-day ov-cal-day-other-month" onclick="calPrev()" title="Previous month (${pd})">
                <div class="ov-cal-day-top">
                    <span class="ov-cal-day-num">${pd}</span>
                </div>
            </div>
        `;
    }

    for (let d = 1; d <= daysInMon; d++) {
        const dateStr    = `${year}-${String(month+1).padStart(2,'0')}-${String(d).padStart(2,'0')}`;
        const isToday    = (dateStr === todayDateKey);
        const isPast     = (dateStr < todayDateKey);
        const isSelected = (dateStr === calSelectedDate);
        const dayEvs     = eventMap[dateStr] || [];
        const hasEvents  = dayEvs.length > 0;
        const hasConfl   = conflictDates.has(dateStr);

        let eventsHtml = '';
        if (hasEvents) {
            eventsHtml += '<div class="ov-cal-events-list">';
            dayEvs.sort((a, b) => (a.start_time || '').localeCompare(b.start_time || ''));
            const maxVisible = 2;
            const slice = dayEvs.slice(0, maxVisible);
            slice.forEach(ev => {
                const { timeFmt } = formatEventTime(ev.start_time);
                const st = (ev.status || 'Confirmed').toLowerCase();
                let chipClass = 'cal-chip-confirmed';
                let dotClass = 'green';
                if (st === 'cancelled') {
                    chipClass = 'cal-chip-cancelled';
                    dotClass = 'red';
                } else if (st === 'pending') {
                    chipClass = 'cal-chip-pending';
                    dotClass = 'amber';
                } else if (st === 'completed') {
                    chipClass = 'cal-chip-completed';
                    dotClass = 'gray';
                }
                const evType      = esc(ev.event_type || 'Event');
                const evTitle     = esc(ev.title || evType);
                const venueName   = esc(ev.chapel_location || 'Main Cathedral');
                const tooltip     = esc(`${timeFmt ? timeFmt + ' • ' : ''}${evTitle} (${ev.status || 'Confirmed'}) - ${venueName}`);

                window.__allEventsMap = window.__allEventsMap || {};
                const evKey = String(ev.event_id || ev.id || Math.random());
                window.__allEventsMap[evKey] = ev;

                eventsHtml += `
                    <div class="cal-chip ${chipClass}" 
                         title="${tooltip}" 
                         onclick="event.stopPropagation(); if (typeof viewEventDetails === 'function') viewEventDetails(window.__allEventsMap['${evKey}']);">
                        <div class="cal-chip-header">
                            <span class="cal-chip-dot ${dotClass}"></span>
                            <span class="cal-chip-title">${evTitle}</span>
                        </div>
                        ${timeFmt ? `<div class="cal-chip-time">${timeFmt}</div>` : ''}
                    </div>
                `;
            });

            if (dayEvs.length > maxVisible) {
                const remaining = dayEvs.length - maxVisible;
                eventsHtml += `
                    <div class="cal-day-overflow-pill" 
                         title="Click to view all ${dayEvs.length} events for ${dateStr}" 
                         onclick="event.stopPropagation(); selectCalDay('${dateStr}');">
                        +${remaining} more
                    </div>
                `;
            }
            eventsHtml += '</div>';
        }

        const cls = `ov-cal-day${isToday ? ' ov-today' : ''}${isPast ? ' ov-cal-day-past' : ''}${isSelected ? ' ov-selected-day' : ''}${hasEvents ? ' ov-has-events' : ''}`;

        html += `
            <div class="${cls}" data-date="${dateStr}" onclick="selectCalDay('${dateStr}')">
                <div class="ov-cal-day-top">
                    <span class="ov-cal-day-num">${d}</span>
                    ${hasEvents ? `<span class="ov-cal-day-count-badge ${hasConfl ? 'has-conflict' : ''}" title="${dayEvs.length} event${dayEvs.length > 1 ? 's' : ''}">${dayEvs.length}</span>` : ''}
                </div>
                ${eventsHtml}
            </div>
        `;
    }

    // Trailing days from next month
    const totalCells = numWeeks * 7;
    const trailingSlots = totalCells - totalSlots;
    for (let i = 1; i <= trailingSlots; i++) {
        html += `
            <div class="ov-cal-day ov-cal-day-other-month" onclick="calNext()" title="Next month (${i})">
                <div class="ov-cal-day-top">
                    <span class="ov-cal-day-num">${i}</span>
                </div>
            </div>
        `;
    }

    grid.innerHTML = html;
}

function quickBookSlot(dateStr, timeStr = '09:00') {
    const dateInput = document.getElementById('field-date-modal');
    if (dateInput) {
        dateInput.value = dateStr;
    }
    const dtInput = document.getElementById('field-datetime-modal');
    if (dtInput) {
        dtInput.value = `${dateStr}T${timeStr}`;
    }
    const venueSelect = document.getElementById('field-venue-modal');
    if (venueSelect && window.selectedChapelFilter && window.selectedChapelFilter !== 'all') {
        const val = (window.selectedChapelFilter === 'cathedral' || window.selectedChapelFilter === '0') ? '0' : String(window.selectedChapelFilter);
        if (venueSelect.querySelector(`option[value="${val}"]`)) {
            venueSelect.value = val;
        }
    }
    const hr = parseInt(timeStr.split(':')[0], 10);
    const shift = (hr < 12) ? 'morning' : 'afternoon';
    if (typeof window.selectModalSlot === 'function') {
        window.selectModalSlot('modal', shift, timeStr);
    }
    if (typeof openModal === 'function') {
        openModal('modal-add-event');
    }
}
window.quickBookSlot = quickBookSlot;

function renderTimeline(dateStr) {
    const container = document.getElementById('timeline-container');
    if (!container) return;

    calSelectedDate = dateStr;

    // Check if selected date is today
    const now = new Date();
    const todayStr = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;
    const isToday = (dateStr === todayStr);

    // Update Header date & weekday
    const dateTitleEl = document.getElementById('day-detail-date-title');
    const daySubtitleEl = document.getElementById('day-detail-day-subtitle');
    if (dateTitleEl || daySubtitleEl) {
        try {
            const parts = dateStr.split('-');
            const dObj = new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, parseInt(parts[2], 10));
            if (!isNaN(dObj.getTime())) {
                const monthShort = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
                const dayNames = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
                const dayName = dayNames[dObj.getDay()];
                const dayNum = dObj.getDate();
                const mName = monthShort[dObj.getMonth()];

                if (dateTitleEl) {
                    dateTitleEl.innerHTML = `<span>${dayName}, ${mName} ${dayNum}</span>`;
                }
                if (daySubtitleEl) {
                    daySubtitleEl.textContent = 'Daily Schedule & Available Slots';
                }
            }
        } catch(e) {}
    }

    const eventsList = getChapelFilteredEvents();
    const dayEvents = eventsList.filter(ev => ev.start_time && ev.start_time.substring(0, 10) === dateStr);
    dayEvents.sort((a, b) => (a.start_time || '').localeCompare(b.start_time || ''));

    const canCreate = Boolean(window.canCreateEvent);

    let html = '';

    // ── SECTION 1: SCHEDULE ──
    const countLabel = `${dayEvents.length} event${dayEvents.length === 1 ? '' : 's'}`;
    const scheduleTitle = "Daily Schedule";
    html += `
        <div class="day-section-head">
            <h3 class="day-section-title">${scheduleTitle}</h3>
            <span class="day-section-count">${countLabel}</span>
        </div>
    `;

    if (dayEvents.length === 0) {
        html += `
            <div class="day-empty-state">
                <div class="empty-state-icon-wrap"><i class="fa-regular fa-calendar-check"></i></div>
                <div class="empty-state-title">No Liturgies Scheduled</div>
                <div class="empty-state-subtitle">No pastoral services or bookings recorded for this day.</div>
                ${canCreate ? `<button type="button" class="btn-empty-action" onclick="if(typeof openModal === 'function') openModal('modal-add-event');"><i class="fa-solid fa-plus"></i> Schedule Service</button>` : ''}
            </div>
        `;
    } else {
        html += '<div class="day-events-stack">';
        dayEvents.forEach(ev => {
            const { timeFmt } = formatEventTime(ev.start_time);
            const st = (ev.status || 'Confirmed').toLowerCase();
            let accentCls = 'accent-green';
            let dotCls = 'green';
            let badgeCls = 'status-confirmed';
            if (st === 'cancelled') {
                accentCls = 'accent-red';
                dotCls = 'red';
                badgeCls = 'status-cancelled';
            } else if (st === 'pending') {
                accentCls = 'accent-amber';
                dotCls = 'amber';
                badgeCls = 'status-pending';
            }

            const title = escHtmlSafe(ev.title || ev.event_type || 'Event');
            const venue = escHtmlSafe(ev.chapel_location || 'Main Cathedral');
            const statusText = escHtmlSafe(ev.status || 'Confirmed');

            const startHour = parseInt((ev.start_time || '').substring(11, 13) || '0', 10);
            const isMorning = startHour < 12;
            const slotLabel = isMorning ? 'Morning Slot' : 'Afternoon Slot';
            const slotIcon = isMorning ? 'fa-sun' : 'fa-moon';

            window.__allEventsMap = window.__allEventsMap || {};
            const evKey = String(ev.event_id || ev.id || Math.random());
            window.__allEventsMap[evKey] = ev;

            const isGuestRole = (typeof currentUserRole !== 'undefined' ? currentUserRole : (window.currentUserRole || '')) === 'Guest';
            const showMaskedTime = ev.is_time_masked || isGuestRole;

            html += `
                <div class="day-sched-card ${accentCls}" onclick="if (typeof viewEventDetails === 'function') viewEventDetails(window.__allEventsMap['${evKey}']);">
                    <div class="day-sched-card-left">
                        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 2px; flex-wrap: wrap;">
                            ${showMaskedTime 
                                ? `<span class="day-sched-time" style="font-weight: 600; color: var(--accent);"><i class="fa-solid fa-clock" style="font-size: 10px;"></i> ${escHtmlSafe(ev.time_window || slotLabel)}</span>`
                                : (timeFmt ? `<span class="day-sched-time" style="font-weight: 600;">${timeFmt}</span>` : '')
                            }
                            <span style="font-size: 10px; font-weight: 600; padding: 1px 7px; border-radius: 999px; background: var(--accent-soft); color: var(--accent); border: 1px solid rgba(184,150,62,0.3); display: inline-flex; align-items: center; gap: 4px;">
                                <i class="fa-regular ${slotIcon}" style="font-size: 9px;"></i> ${slotLabel}
                            </span>
                        </div>
                        <div class="day-sched-title-wrap">
                            <span class="day-sched-dot ${dotCls}"></span>
                            <span class="day-sched-title">${title}</span>
                        </div>
                        <div class="day-sched-venue" style="font-size: 11.5px; color: var(--muted);"><i class="fa-solid fa-location-dot" style="font-size: 10px;"></i> ${venue}</div>
                        ${(ev.pastor_assigned && ev.pastor_assigned.trim() !== '') ? `
                        <div class="day-sched-priest" style="font-size: 11.5px; color: var(--ink); font-weight: 600; margin-top: 4px; display: inline-flex; align-items: center; gap: 5px;">
                            <i class="fa-solid fa-user-tie" style="font-size: 10px; color: var(--accent);"></i>
                            <span>Celebrant: Rev. ${escHtmlSafe(ev.pastor_assigned)}</span>
                        </div>` : ''}
                    </div>
                    <div class="day-sched-card-right">
                        <span class="day-sched-status-badge ${badgeCls}">${statusText}</span>
                        <i class="fa-solid fa-chevron-right day-sched-arrow"></i>
                    </div>
                </div>
            `;
        });
        html += '</div>';
    }

    // ── SECTION 2: AVAILABLE TIME SLOTS (DYNAMIC OCCUPANCY) ──
    const activeDayEvents = dayEvents.filter(ev => {
        const s = (ev.status || '').toLowerCase();
        return s !== 'cancelled' && s !== 'rejected';
    });
    const morningBooked = activeDayEvents.some(ev => {
        const h = parseInt((ev.start_time || '').substring(11, 13) || '0', 10);
        return h < 12;
    });
    const afternoonBooked = activeDayEvents.some(ev => {
        const h = parseInt((ev.start_time || '').substring(11, 13) || '0', 10);
        return h >= 12;
    });

    let openSlotCount = 0;
    if (!morningBooked) openSlotCount++;
    if (!afternoonBooked) openSlotCount++;
    const slotCountLabel = openSlotCount === 0 ? 'Fully Booked' : `${openSlotCount} slot${openSlotCount === 1 ? '' : 's'} available`;

    html += `
        <div class="day-section-head" style="margin-top: 14px;">
            <h3 class="day-section-title">Available Time Slots</h3>
            <span class="day-section-count" style="${openSlotCount === 0 ? 'color: #ef4444; font-weight: 600;' : ''}">${slotCountLabel}</span>
        </div>
        <div class="day-slots-stack">
            <!-- Morning Slot (09:00 - 12:00) -->
            <div class="day-slot-card" style="${morningBooked ? 'background: var(--bg2); border-style: dashed;' : ''}">
                <div class="day-slot-left">
                    <div class="day-slot-icon sun" style="${morningBooked ? 'opacity: 0.6;' : ''}">
                        <i class="fa-regular fa-sun"></i>
                    </div>
                    <div>
                        <div style="display: flex; align-items: center; gap: 6px;">
                            <span class="day-slot-name">Morning</span>
                            ${morningBooked 
                                ? `<span style="font-size: 11px; font-weight: 600; padding: 2px 8px; border-radius: 999px; background: rgba(239, 68, 68, 0.08); color: #dc2626; border: 1px solid rgba(239, 68, 68, 0.2); display: inline-flex; align-items: center; gap: 4px;"><i class="fa-solid fa-lock" style="font-size: 9px;"></i> Booked</span>` 
                                : `<span style="font-size: 11px; font-weight: 600; padding: 2px 8px; border-radius: 999px; background: rgba(34, 197, 94, 0.08); color: #15803d; border: 1px solid rgba(34, 197, 94, 0.2); display: inline-flex; align-items: center; gap: 4px;"><i class="fa-solid fa-circle-check" style="font-size: 9px;"></i> Open</span>`
                            }
                        </div>
                        <div class="day-slot-hours">9:00 AM &ndash; 12:00 PM</div>
                    </div>
                </div>
                ${canCreate 
                    ? (morningBooked 
                        ? `<span style="font-size: 11px; color: var(--muted); font-weight: 500; padding: 5px 10px;"><i class="fa-solid fa-lock" style="font-size: 10px;"></i> Occupied</span>`
                        : `<button type="button" class="btn-book-slot-gold" onclick="quickBookSlot('${dateStr}', '09:00')">Book Slot</button>`)
                    : ''}
            </div>

            <!-- Afternoon Slot (14:00 - 17:00) -->
            <div class="day-slot-card" style="${afternoonBooked ? 'background: var(--bg2); border-style: dashed;' : ''}">
                <div class="day-slot-left">
                    <div class="day-slot-icon moon" style="${afternoonBooked ? 'opacity: 0.6;' : ''}">
                        <i class="fa-regular fa-moon"></i>
                    </div>
                    <div>
                        <div style="display: flex; align-items: center; gap: 6px;">
                            <span class="day-slot-name">Afternoon</span>
                            ${afternoonBooked 
                                ? `<span style="font-size: 11px; font-weight: 600; padding: 2px 8px; border-radius: 999px; background: rgba(239, 68, 68, 0.08); color: #dc2626; border: 1px solid rgba(239, 68, 68, 0.2); display: inline-flex; align-items: center; gap: 4px;"><i class="fa-solid fa-lock" style="font-size: 9px;"></i> Booked</span>` 
                                : `<span style="font-size: 11px; font-weight: 600; padding: 2px 8px; border-radius: 999px; background: rgba(34, 197, 94, 0.08); color: #15803d; border: 1px solid rgba(34, 197, 94, 0.2); display: inline-flex; align-items: center; gap: 4px;"><i class="fa-solid fa-circle-check" style="font-size: 9px;"></i> Open</span>`
                            }
                        </div>
                        <div class="day-slot-hours">2:00 PM &ndash; 5:00 PM</div>
                    </div>
                </div>
                ${canCreate 
                    ? (afternoonBooked 
                        ? `<span style="font-size: 11px; color: var(--muted); font-weight: 500; padding: 5px 10px;"><i class="fa-solid fa-lock" style="font-size: 10px;"></i> Occupied</span>`
                        : `<button type="button" class="btn-book-slot-gold" onclick="quickBookSlot('${dateStr}', '14:00')">Book Slot</button>`)
                    : ''}
            </div>
        </div>
    `;

    container.innerHTML = html;
}

function ovSyncSelectedDate() {
    const today = new Date();
    if (ovYear === today.getFullYear() && ovMonth === today.getMonth()) {
        window.ovSelectedDate = localDateKey(today);
    } else {
        window.ovSelectedDate = `${ovYear}-${String(ovMonth + 1).padStart(2, '0')}-01`;
    }
    ovShowAgenda(window.ovSelectedDate);
}

function ovCalPrev() { 
    ovMonth--; 
    if (ovMonth < 0) { ovMonth = 11; ovYear--; } 
    ovSyncSelectedDate();
    renderOvCalendar(); 
}

function ovCalNext() { 
    ovMonth++; 
    if (ovMonth > 11) { ovMonth = 0; ovYear++; } 
    ovSyncSelectedDate();
    renderOvCalendar(); 
}

function ovCalJumpMonth(m) {
    ovMonth = parseInt(m, 10);
    ovSyncSelectedDate();
    renderOvCalendar();
}

function ovCalJumpYear(y) {
    ovYear = parseInt(y, 10);
    ovSyncSelectedDate();
    renderOvCalendar();
}

function ovCalToday() {
    const now = new Date();
    ovYear = now.getFullYear();
    ovMonth = now.getMonth();
    window.ovSelectedDate = localDateKey(now);
    renderOvCalendar();
    ovShowAgenda(window.ovSelectedDate);
}

function ovSelectDay(dateStr) {
    window.ovSelectedDate = dateStr;
    const allCells = document.querySelectorAll('.ov-cal-day');
    allCells.forEach(cell => {
        if (cell.getAttribute('data-date') === dateStr) {
            cell.classList.add('ov-selected-day');
        } else {
            cell.classList.remove('ov-selected-day');
        }
    });
    ovShowAgenda(dateStr);
}

function renderOvCalendar() {
    const grid  = document.getElementById('ov-cal-grid');
    const label = document.getElementById('ov-cal-month-label');
    const monthSelect = document.getElementById('ov-cal-month-select');
    const yearSelect  = document.getElementById('ov-cal-year-select');
    if (!grid) return;

    // Apply active venue theme colors to calendar frame and header
    updateVenueThemeContext();

    const monthNames = ['January','February','March','April','May','June','July','August','September','October','November','December'];
    if (label) label.textContent = monthNames[ovMonth] + ' ' + ovYear;
    if (monthSelect) monthSelect.value = String(ovMonth);
    if (yearSelect) {
        if (!yearSelect.querySelector(`option[value="${ovYear}"]`)) {
            const opt = document.createElement('option');
            opt.value = ovYear;
            opt.textContent = ovYear;
            yearSelect.appendChild(opt);
        }
        yearSelect.value = String(ovYear);
    }

    const today    = new Date();
    const firstDay = new Date(ovYear, ovMonth, 1).getDay();
    const daysInM  = new Date(ovYear, ovMonth + 1, 0).getDate();

    const totalSlots = firstDay + daysInM;
    const numWeeks   = Math.ceil(totalSlots / 7);
    grid.style.gridTemplateRows = `24px repeat(${numWeeks}, minmax(0, 1fr))`;

    if (!window.ovSelectedDate) {
        window.ovSelectedDate = localDateKey(today);
    }

    const eventMap = {};
    if (typeof ovEvents !== 'undefined' && Array.isArray(ovEvents)) {
        let ovList = ovEvents;
        if (window.selectedChapelFilter && window.selectedChapelFilter !== 'all') {
            if (window.selectedChapelFilter === 'cathedral' || window.selectedChapelFilter === '0') {
                ovList = ovEvents.filter(ev => !ev.chapel_id || ev.chapel_id == 0);
            } else {
                ovList = ovEvents.filter(ev => ev.chapel_id == window.selectedChapelFilter);
            }
        }
        ovList.forEach(ev => {
            if (!ev.start_time) return;
            const d = ev.start_time.substring(0, 10);
            if (!eventMap[d]) eventMap[d] = [];
            eventMap[d].push(ev);
        });
    }

    let html = '';
    ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'].forEach(d => {
        html += `<div class="ov-cal-day-hdr">${d}</div>`;
    });

    // Leading days from previous month
    const prevMonthLastDay = new Date(ovYear, ovMonth, 0).getDate();
    for (let i = firstDay - 1; i >= 0; i--) {
        const prevDayNum = prevMonthLastDay - i;
        html += `
            <div class="ov-cal-day ov-cal-day-other-month" onclick="ovCalPrev()" title="Previous month (${prevDayNum})">
                <div class="ov-cal-day-top">
                    <span class="ov-cal-day-num">${prevDayNum}</span>
                </div>
            </div>
        `;
    }

    const esc = (typeof escHtml === 'function') ? escHtml : (s => (s || '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])));

    const ovTodayDateKey = localDateKey(today);

    for (let d = 1; d <= daysInM; d++) {
        const dateStr    = `${ovYear}-${String(ovMonth+1).padStart(2,'0')}-${String(d).padStart(2,'0')}`;
        const isToday    = (dateStr === ovTodayDateKey);
        const isPast     = (dateStr < ovTodayDateKey);
        const isSelected = (window.ovSelectedDate === dateStr);
        const evs        = eventMap[dateStr] || [];
        const hasEvs     = evs.length > 0;

        let dotsHtml = '';
        if (hasEvs) {
            dotsHtml += '<div class="ov-cal-dots-wrap">';
            const maxDots = 3;
            const slice = evs.slice(0, maxDots);
            slice.forEach(ev => {
                const st = (ev.status || '').toLowerCase();
                const isCancelled = (st === 'cancelled');
                const dotColor = isCancelled ? '#D64545' : '#2E9E5B';
                const dotTitle = esc(`${ev.title || ev.event_type || 'Event'} (${ev.status || 'Confirmed'})`);
                dotsHtml += `<span class="ov-cal-event-dot" style="background:${dotColor};" title="${dotTitle}"></span>`;
            });
            if (evs.length > maxDots) {
                const remaining = evs.length - maxDots;
                dotsHtml += `<span class="ov-cal-more-dots">+${remaining}</span>`;
            }
            dotsHtml += '</div>';
        }

        const cls = `ov-cal-day${isToday ? ' ov-today' : ''}${isPast ? ' ov-cal-day-past' : ''}${isSelected ? ' ov-selected-day' : ''}${hasEvs ? ' ov-has-events' : ''}`;
        
        html += `
            <div class="${cls}" data-date="${dateStr}" onclick="ovSelectDay('${dateStr}')">
                <div class="ov-cal-day-top">
                    <span class="ov-cal-day-num">${d}</span>
                </div>
                ${dotsHtml}
            </div>
        `;
    }

    // Trailing days from next month
    const totalCells = numWeeks * 7;
    const trailingSlots = totalCells - totalSlots;
    for (let i = 1; i <= trailingSlots; i++) {
        html += `
            <div class="ov-cal-day ov-cal-day-other-month" onclick="ovCalNext()" title="Next month (${i})">
                <div class="ov-cal-day-top">
                    <span class="ov-cal-day-num">${i}</span>
                </div>
            </div>
        `;
    }

    grid.innerHTML = html;
}

function ovShowAgenda(dateStr) {
    const agendaEl   = document.getElementById('ov-cal-agenda');
    const dateLabel  = document.getElementById('ov-cal-agenda-date');
    const countBadge = document.getElementById('ov-cal-agenda-count');
    if (!agendaEl || !dateLabel) return;

    window.ovSelectedDate = dateStr;

    const monthNames = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    const parts = dateStr.split('-').map(Number);
    const y = parts[0], m = parts[1], d = parts[2];
    const dateObj = new Date(y, m - 1, d);
    const weekdays = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
    
    dateLabel.textContent = `${weekdays[dateObj.getDay()]}, ${monthNames[m-1]} ${d}, ${y}`;

    let dayEvs = (typeof ovEvents !== 'undefined' && Array.isArray(ovEvents))
        ? ovEvents.filter(ev => ev.start_time && ev.start_time.substring(0, 10) === dateStr)
                  .sort((a,b) => new Date(a.start_time.replace(' ','T')) - new Date(b.start_time.replace(' ','T')))
        : [];

    if (window.selectedChapelFilter && window.selectedChapelFilter !== 'all') {
        if (window.selectedChapelFilter === 'cathedral' || window.selectedChapelFilter === '0') {
            dayEvs = dayEvs.filter(ev => !ev.chapel_id || ev.chapel_id == 0);
        } else {
            dayEvs = dayEvs.filter(ev => ev.chapel_id == window.selectedChapelFilter);
        }
    }

    if (countBadge) {
        if (dayEvs.length > 0) {
            countBadge.textContent = dayEvs.length === 1 ? '1 Event' : `${dayEvs.length} Events`;
            countBadge.style.display = 'inline-block';
        } else {
            countBadge.textContent = 'Available';
            countBadge.style.display = 'inline-block';
        }
    }

    const morningEvents = [];
    const afternoonEvents = [];
    const otherEvents = [];

    dayEvs.forEach(ev => {
        let hr = 9;
        try {
            const dt = new Date(ev.start_time.replace(' ', 'T'));
            if (!isNaN(dt.getTime())) hr = dt.getHours();
        } catch(e) {}
        if (hr < 12) {
            morningEvents.push(ev);
        } else if (hr < 18) {
            afternoonEvents.push(ev);
        } else {
            otherEvents.push(ev);
        }
    });

    const canCreate = Boolean(window.canCreateEvent);
    const canEdit   = Boolean(window.canEditEvents);

    function renderOvBookedSlotRow(ev) {
        let timeFmt = '';
        try {
            const dt = new Date(ev.start_time.replace(' ', 'T'));
            timeFmt = isNaN(dt.getTime()) ? '' : dt.toLocaleTimeString([], {hour:'numeric', minute:'2-digit'});
        } catch(e) {}

        window.__ovSlotEvents = window.__ovSlotEvents || {};
        const evKey = String(ev.event_id || ev.id || Math.random());
        window.__ovSlotEvents[evKey] = ev;
        const evJson = `window.__ovSlotEvents['${evKey}']`;

        const isCancelled = (ev.status && ev.status.toLowerCase() === 'cancelled');
        const eventType = ev.event_type || 'Event';
        const title = ev.title || eventType;
        const venue = ev.chapel_location || 'Main Cathedral';
        const priest = ev.pastor_assigned || '';

        return `
            <div class="slot-row slot-booked${isCancelled ? ' slot-cancelled' : ''}" ${isCancelled ? 'style="border-left-color: #D64545;"' : ''}>
                <div class="slot-booked-main">
                    <div class="slot-booked-title">
                        <span class="slot-booked-time">${timeFmt}</span>
                        <span>${escHtmlSafe(title)}</span>
                        <span class="slot-type-badge">${escHtmlSafe(eventType)}</span>
                    </div>
                    <div class="slot-booked-venue">
                        <i class="fa-solid fa-location-dot" style="font-size:10px;margin-right:4px;"></i>${escHtmlSafe(venue)}
                    </div>
                </div>
                <div class="slot-hover-actions">
                    ${priest ? `<span class="slot-priest-name"><i class="fa-solid fa-user-tie" style="font-size:10px;margin-right:4px;"></i>${escHtmlSafe(priest)}</span>` : ''}
                    <button type="button" class="slot-icon-btn" onclick="viewEventDetails(${evJson})" title="View Details">
                        <i class="fa-solid fa-eye"></i>
                    </button>
                    ${canEdit ? `
                        <button type="button" class="slot-icon-btn" onclick="openEditEventModal(${evJson})" title="Edit Event">
                            <i class="fa-solid fa-pen"></i>
                        </button>
                    ` : ''}
                </div>
            </div>
        `;
    }

    let html = '<div class="sched-slots-body">';

    // Morning Slot
    if (morningEvents.length === 0) {
        html += `
            <div class="slot-row">
                <div class="slot-available-info">
                    <span class="slot-avail-dot"></span>
                    <span>Morning &middot; Available</span>
                    <span class="slot-time-sub">(9:00 AM &ndash; 12:00 PM)</span>
                </div>
                ${canCreate ? `<button type="button" class="slot-book-link" onclick="quickBookSlot('${dateStr}', '09:00')">Book slot</button>` : ''}
            </div>
        `;
    } else {
        morningEvents.forEach(ev => {
            html += renderOvBookedSlotRow(ev);
        });
    }

    // Afternoon Slot
    if (afternoonEvents.length === 0) {
        html += `
            <div class="slot-row">
                <div class="slot-available-info">
                    <span class="slot-avail-dot"></span>
                    <span>Afternoon &middot; Available</span>
                    <span class="slot-time-sub">(2:00 PM &ndash; 5:00 PM)</span>
                </div>
                ${canCreate ? `<button type="button" class="slot-book-link" onclick="quickBookSlot('${dateStr}', '14:00')">Book slot</button>` : ''}
            </div>
        `;
    } else {
        afternoonEvents.forEach(ev => {
            html += renderOvBookedSlotRow(ev);
        });
    }

    // Other/Evening events
    if (otherEvents.length > 0) {
        otherEvents.forEach(ev => {
            html += renderOvBookedSlotRow(ev);
        });
    }

    html += '</div>';
    agendaEl.innerHTML = html;
}

// Bind to window for global access
window.calPrev = calPrev;
window.calNext = calNext;
window.selectCalDay = selectCalDay;
window.renderCalendar = renderCalendar;
window.renderTimeline = renderTimeline;

window.ovCalPrev = ovCalPrev;
window.ovCalNext = ovCalNext;
window.ovCalToday = ovCalToday;
window.ovSelectDay = ovSelectDay;
window.renderOvCalendar = renderOvCalendar;
window.ovShowAgenda = ovShowAgenda;

document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('cal-grid')) {
        renderCalendar(calYear, calMonth);
        const d = new Date();
        renderTimeline(d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2,'0') + '-' + String(d.getDate()).padStart(2,'0'));
    }
    if (document.getElementById('ov-cal-grid')) {
        renderOvCalendar();
        ovShowAgenda(localDateKey());
    }
});
