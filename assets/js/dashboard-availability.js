/**
 * Dashboard Availability Module
 * Handles pastor availability checks, card picker logic, and venue conflict detection.
 */

let selectedPastor = { 
    cathedral: null, 
    chapel: null, 
    modal: null, 
    'edit-modal': null, 
    approve: null, 
    request: null 
};

function escapeAvailHtml(str) {
    return String(str ?? '').replace(/[&<>"']/g, m => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    }[m]));
}

function getApiUrl(path) {
    const base = (typeof window !== 'undefined' && window.BASE_URL) ? window.BASE_URL.replace(/\/+$/, '') : '';
    const cleanPath = path.replace(/^\/+/, '');
    return base ? `${base}/${cleanPath}` : cleanPath;
}

function setScheduleWarning(scope, html) {
    const warning = document.getElementById('venue-warning-' + scope);
    const text = document.getElementById('venue-warning-text-' + scope);
    if (!warning || !text) return;

    text.innerHTML = html || '';
    warning.classList.remove('d-none');
    warning.style.display = html ? 'block' : 'none';
}

function onFormChange(scope) {
    try {
        const typeSelect = document.getElementById(`field-event-type-${scope}`);
        const venueSelect = document.getElementById(`field-venue-${scope}`);
        const datetimeInput = document.getElementById(`field-datetime-${scope}`);
        const customDurInput = document.getElementById(`field-custom-duration-${scope}`);

        if (!datetimeInput) return;

        const type = typeSelect ? typeSelect.value : '';
        const hasVenueField = !!venueSelect;
        const venue = venueSelect ? venueSelect.value : null;
        const datetime = datetimeInput.value;
        let customDur = customDurInput ? parseInt(customDurInput.value) : 0;

        const timeline = document.getElementById(`timeline-preview-${scope}`);
        if (!datetime) {
            if (timeline) timeline.style.display = 'none';
            return;
        }

        // Get default duration if custom is not set
        if (!customDur || isNaN(customDur)) {
            const defaults = {
                'Wedding': 120, 'Wedding (Holy Matrimony)': 120,
                'Mass': 90, 'Thanksgiving Mass': 90, 'Burial Mass': 90, 'Requiem Mass': 90,
                'Baptism': 60, 'Confirmation': 60, 'Funeral': 90, 'Blessings': 60, 'Fiesta': 120, 'Other': 60
            };
            customDur = defaults[type] || 60;
        }

        const dt = new Date(datetime);
        const hr = dt.getHours();

        // Show timeline and synchronize slot availability (skip sanctuary lock indicator for Outside events or unselected Location)
        const locTypeEl = document.getElementById('field-location-type-' + scope) || (scope === 'request' ? document.getElementById('field-location-type') : null);
        const isOutsideLoc = locTypeEl && String(locTypeEl.value || '').toLowerCase() === 'outside';
        const isUnselectedVenue = hasVenueField && String(venue || '').trim() === '';

        if (isOutsideLoc || isUnselectedVenue) {
            if (timeline) timeline.style.display = 'none';
            resetStaticModalSlots(scope, isOutsideLoc ? 'Outside Parish' : 'Select Location');
            toggleSubmitButton(scope, false);
        } else if (scope !== 'cathedral' && scope !== 'chapel') {
            renderAvailabilityTimeline(venue, datetime, scope, type, customDur);
        }

        if (hr < 6 || hr >= 21) {
            setScheduleWarning(scope, 'Operating Hours: Events can only be scheduled between 6:00 AM and 9:00 PM.');
            toggleSubmitButton(scope, true);
            if (type) loadPastorCards(type, datetime, scope, customDur, venue);
            return;
        }

        setScheduleWarning(scope, '');

        // Trigger updates if sacrament type is picked
        if (type) {
            loadPastorCards(type, datetime, scope, customDur, venue);
            if (!isOutsideLoc && !isUnselectedVenue) {
                checkVenueAvailability(venue, datetime, scope, type, customDur);
            }
        }

    } catch (e) {
        console.error('onFormChange error:', e);
    }
}

async function renderAvailabilityTimeline(venue, datetime, scope, eventType, duration) {
    if (scope === 'cathedral' || scope === 'chapel') {
        const container = document.getElementById('timeline-preview-' + scope);
        if (container) {
            container.style.display = 'none';
            container.innerHTML = '';
        }
        return;
    }
    const container = document.getElementById('timeline-preview-' + scope);
    if (!container) return;

    const dt = new Date(datetime);
    if (isNaN(dt.getTime())) {
        container.style.display = 'none';
        return;
    }

    const isGlobalInspector = (scope === 'global');

    // Show loading state
    container.style.display = 'block';
    container.innerHTML = `
        <div style="display:flex; align-items:center; justify-content:center; padding:12px; color:var(--text2); font-size:11px; background:rgba(255,255,255,0.02); border-radius:6px; border:1px dashed var(--border);">
            <i class="fa-solid fa-circle-notch fa-spin mr-8"></i> Synchronizing Slots...
        </div>
    `;

    let excludeId = '';
    if (scope === 'edit-modal') excludeId = document.getElementById('edit-event-id')?.value || '';
    if (scope === 'approve') excludeId = document.getElementById('approve-req-id')?.value || '';

    const dateStr = dt.getFullYear() + '-' + String(dt.getMonth() + 1).padStart(2, '0') + '-' + String(dt.getDate()).padStart(2, '0');
    const selectedHour = dt.getHours();
    const isSelectedMorning = selectedHour < 12;

    const params = new URLSearchParams({ 
        venue: (venue === null || venue === undefined || venue === 'null') ? '' : venue, 
        date: dateStr,
        exclude_id: excludeId
    });

    if (window.SkeletonLoader && container) {
        container.style.display = 'block';
        container.innerHTML = window.SkeletonLoader.slotPicker();
    }

    try {
        const res = await fetch(getApiUrl(`app/controllers/get_day_schedule.php?${params}`), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const events = await res.json();

        let morningEvent = null;
        let afternoonEvent = null;

        events.forEach(ev => {
            const evDate = new Date(String(ev.start_time || '').replace(' ', 'T'));
            const hr = evDate.getHours();
            if (hr < 12) {
                morningEvent = ev;
            } else {
                afternoonEvent = ev;
            }
        });

        const isMorningBooked = !!morningEvent;
        const isAfternoonBooked = !!afternoonEvent;

        // Conflict check for interactive form picker
        let hasConflict = false;
        if (!isGlobalInspector) {
            if (isSelectedMorning && isMorningBooked) {
                hasConflict = true;
            } else if (!isSelectedMorning && isAfternoonBooked) {
                hasConflict = true;
            }
        }

        // Live update static modal cards if present in DOM (for modal and edit-modal)
        const staticMCard = document.getElementById(`slot-card-morning-${scope}`);
        const staticACard = document.getElementById(`slot-card-afternoon-${scope}`);
        const staticMBadge = document.getElementById(`slot-badge-morning-${scope}`);
        const staticABadge = document.getElementById(`slot-badge-afternoon-${scope}`);
        const staticMChip = document.getElementById(`slot-chip-morning-${scope}`);
        const staticAChip = document.getElementById(`slot-chip-afternoon-${scope}`);

        if (staticMCard && staticMBadge) {
            if (isMorningBooked) {
                staticMCard.classList.add('disabled');
                staticMBadge.className = 'slot-badge ' + (morningEvent.status === 'Pending' ? 'pending' : 'booked');
                staticMBadge.innerHTML = `<i class="fa-solid ${morningEvent.status === 'Pending' ? 'fa-clock' : 'fa-lock'}" style="font-size:7px;"></i> ${morningEvent.status === 'Pending' ? 'Pending' : 'Booked'}`;
                if (staticMChip) {
                    staticMChip.innerHTML = `<i class="fa-solid fa-lock" style="font-size:9px;"></i> <span>${escapeAvailHtml(morningEvent.title || 'Booked')}</span>`;
                }
            } else {
                staticMCard.classList.remove('disabled');
                staticMBadge.className = 'slot-badge available';
                staticMBadge.innerHTML = '<i class="fa-solid fa-circle-check" style="font-size:7px;"></i> Available';
                if (isSelectedMorning && staticMChip) {
                    staticMChip.innerHTML = '<i class="fa-solid fa-circle-dot" style="font-size:9px;"></i> <span>Active Slot Selection</span>';
                } else if (staticMChip) {
                    staticMChip.innerHTML = '<i class="fa-regular fa-hand-pointer" style="font-size:9px;"></i> <span>Click to Select</span>';
                }
            }
        }

        if (staticACard && staticABadge) {
            if (isAfternoonBooked) {
                staticACard.classList.add('disabled');
                staticABadge.className = 'slot-badge ' + (afternoonEvent.status === 'Pending' ? 'pending' : 'booked');
                staticABadge.innerHTML = `<i class="fa-solid ${afternoonEvent.status === 'Pending' ? 'fa-clock' : 'fa-lock'}" style="font-size:7px;"></i> ${afternoonEvent.status === 'Pending' ? 'Pending' : 'Booked'}`;
                if (staticAChip) {
                    staticAChip.innerHTML = `<i class="fa-solid fa-lock" style="font-size:9px;"></i> <span>${escapeAvailHtml(afternoonEvent.title || 'Booked')}</span>`;
                }
            } else {
                staticACard.classList.remove('disabled');
                staticABadge.className = 'slot-badge available';
                staticABadge.innerHTML = '<i class="fa-solid fa-circle-check" style="font-size:7px;"></i> Available';
                if (!isSelectedMorning && staticAChip) {
                    staticAChip.innerHTML = '<i class="fa-solid fa-circle-dot" style="font-size:9px;"></i> <span>Active Slot Selection</span>';
                } else if (staticAChip) {
                    staticAChip.innerHTML = '<i class="fa-regular fa-hand-pointer" style="font-size:9px;"></i> <span>Click to Select</span>';
                }
            }
        }

        if (scope === 'modal' || scope === 'edit-modal' || scope === 'request') {
            // Dedicated slot cards exist, keep timeline container hidden
            container.style.display = 'none';
            toggleSubmitButton(scope, hasConflict);
        } else if (isGlobalInspector) {
            container.style.display = 'block';
            // Inspector / Schedule List View (Compact Fit, No Redundant Header)
            container.innerHTML = `
                <div class="slot-picker-grid slot-grid-vertical">
                    <!-- Morning Slot -->
                    <div class="slot-card morning inspector-card ${isMorningBooked ? 'disabled' : ''}">
                        <div class="slot-card-header">
                            <div class="slot-title">
                                <i class="fa-solid fa-sun" style="color:var(--accent,#c5a059);font-size:12px;"></i> Morning Slot
                            </div>
                            <span class="slot-badge ${isMorningBooked ? (morningEvent.status === 'Pending' ? 'pending' : 'booked') : 'available'}">
                                <i class="fa-solid ${isMorningBooked ? (morningEvent.status === 'Pending' ? 'fa-clock' : 'fa-lock') : 'fa-circle-check'}" style="font-size:7px;"></i>
                                ${isMorningBooked ? (morningEvent.status === 'Pending' ? 'Pending' : 'Booked') : 'Available'}
                            </span>
                        </div>
                        <div class="slot-time-range"><i class="fa-regular fa-clock" style="font-size:9px;"></i> 09:00 AM – 12:00 PM</div>
                        ${morningEvent 
                            ? `<div class="slot-event-chip" title="${escapeAvailHtml(morningEvent.title)} (${escapeAvailHtml(morningEvent.status)})">
                                 <span class="slot-event-title-text"><i class="fa-solid fa-bookmark" style="color:var(--accent,#c5a059);font-size:9px;margin-right:4px;"></i>${escapeAvailHtml(morningEvent.title)}</span>
                                 <span style="font-size:9px;color:var(--text2,#94a3b8);background:var(--bg3,rgba(255,255,255,0.06));padding:1px 5px;border-radius:4px;flex-shrink:0;">${escapeAvailHtml(morningEvent.event_type)}</span>
                               </div>` 
                            : '<div class="slot-open-chip"><i class="fa-solid fa-circle-check" style="font-size:10px;"></i> <span>Open for Scheduling</span></div>'}
                    </div>

                    <!-- Afternoon Slot -->
                    <div class="slot-card afternoon inspector-card ${isAfternoonBooked ? 'disabled' : ''}">
                        <div class="slot-card-header">
                            <div class="slot-title">
                                <i class="fa-solid fa-cloud-sun" style="color:var(--accent,#c5a059);font-size:12px;"></i> Afternoon Slot
                            </div>
                            <span class="slot-badge ${isAfternoonBooked ? (afternoonEvent.status === 'Pending' ? 'pending' : 'booked') : 'available'}">
                                <i class="fa-solid ${isAfternoonBooked ? (afternoonEvent.status === 'Pending' ? 'fa-clock' : 'fa-lock') : 'fa-circle-check'}" style="font-size:7px;"></i>
                                ${isAfternoonBooked ? (afternoonEvent.status === 'Pending' ? 'Pending' : 'Booked') : 'Available'}
                            </span>
                        </div>
                        <div class="slot-time-range"><i class="fa-regular fa-clock" style="font-size:9px;"></i> 02:00 PM – 05:00 PM</div>
                        ${afternoonEvent 
                            ? `<div class="slot-event-chip" title="${escapeAvailHtml(afternoonEvent.title)} (${escapeAvailHtml(afternoonEvent.status)})">
                                 <span class="slot-event-title-text"><i class="fa-solid fa-bookmark" style="color:var(--accent,#c5a059);font-size:9px;margin-right:4px;"></i>${escapeAvailHtml(afternoonEvent.title)}</span>
                                 <span style="font-size:9px;color:var(--text2,#94a3b8);background:var(--bg3,rgba(255,255,255,0.06));padding:1px 5px;border-radius:4px;flex-shrink:0;">${escapeAvailHtml(afternoonEvent.event_type)}</span>
                               </div>` 
                            : '<div class="slot-open-chip"><i class="fa-solid fa-circle-check" style="font-size:10px;"></i> <span>Open for Scheduling</span></div>'}
                    </div>
                </div>
            `;
        } else {
            container.style.display = 'block';
            // Interactive Form / Modal Picker
            container.innerHTML = `
                <div style="font-size:11px; font-weight:700; color:#111111; margin-bottom:8px; display:flex; justify-content:space-between; align-items:center; letter-spacing:0.04em; text-transform:uppercase;">
                    <span><i class="fa-solid fa-calendar-day mr-4" style="color:#737373;"></i> Select Time Slot (Max 2 per day)</span>
                    <span style="font-size:10px; color:#111111; font-weight:700; background:#F6F6F6; border:1px solid #E5E5E5; padding:2px 8px; border-radius:4px;">${dateStr}</span>
                </div>
                <div class="slot-picker-grid">
                    <!-- Morning Slot -->
                    <div class="slot-card morning ${isSelectedMorning ? 'selected' : ''} ${isMorningBooked ? 'disabled' : ''}" 
                         id="slot-morning-${scope}">
                        <div class="slot-card-header">
                            <div class="slot-title">
                                <i class="fa-solid fa-sun" style="color:var(--accent,#c5a059);font-size:13px;"></i> Morning Slot
                            </div>
                            <span class="slot-badge ${isSelectedMorning ? 'selected' : (isMorningBooked ? (morningEvent.status === 'Pending' ? 'pending' : 'booked') : 'available')}">
                                <i class="fa-solid ${isSelectedMorning ? 'fa-circle-dot' : (isMorningBooked ? (morningEvent.status === 'Pending' ? 'fa-clock' : 'fa-lock') : 'fa-circle-check')}" style="font-size:7px;"></i>
                                ${isSelectedMorning ? 'Selected' : (isMorningBooked ? (morningEvent.status === 'Pending' ? 'Pending' : 'Booked') : 'Available')}
                            </span>
                        </div>
                        <div class="slot-time-range"><i class="fa-regular fa-clock" style="font-size:9px;"></i> 09:00 AM – 12:00 PM</div>
                        ${morningEvent 
                            ? `<div class="slot-event-chip" title="${morningEvent.title} (${morningEvent.status})">
                                 <span class="slot-event-title-text"><i class="fa-solid fa-lock" style="color:#f87171;font-size:9px;margin-right:4px;"></i>${morningEvent.title}</span>
                               </div>` 
                            : `<div class="slot-open-chip" style="${isSelectedMorning ? 'background:#111111;border:1px solid #111111;color:#FFFFFF;' : ''}"><i class="fa-solid ${isSelectedMorning ? 'fa-circle-check' : 'fa-circle-check'}" style="font-size:9px;"></i> <span>${isSelectedMorning ? 'Active Slot Selection' : 'Click to Select'}</span></div>`}
                    </div>

                    <!-- Afternoon Slot -->
                    <div class="slot-card afternoon ${!isSelectedMorning ? 'selected' : ''} ${isAfternoonBooked ? 'disabled' : ''}" 
                         id="slot-afternoon-${scope}">
                        <div class="slot-card-header">
                            <div class="slot-title">
                                <i class="fa-solid fa-cloud-sun" style="color:#b87333;font-size:13px;"></i> Afternoon Slot
                            </div>
                            <span class="slot-badge ${!isSelectedMorning ? 'selected' : (isAfternoonBooked ? (afternoonEvent.status === 'Pending' ? 'pending' : 'booked') : 'available')}">
                                <i class="fa-solid ${!isSelectedMorning ? 'fa-circle-dot' : (isAfternoonBooked ? (afternoonEvent.status === 'Pending' ? 'fa-clock' : 'fa-lock') : 'fa-circle-check')}" style="font-size:7px;"></i>
                                ${!isSelectedMorning ? 'Selected' : (isAfternoonBooked ? (afternoonEvent.status === 'Pending' ? 'Pending' : 'Booked') : 'Available')}
                            </span>
                        </div>
                        <div class="slot-time-range"><i class="fa-regular fa-clock" style="font-size:9px;"></i> 02:00 PM – 05:00 PM</div>
                        ${afternoonEvent 
                            ? `<div class="slot-event-chip" title="${afternoonEvent.title} (${afternoonEvent.status})">
                                 <span class="slot-event-title-text"><i class="fa-solid fa-lock" style="color:#f87171;font-size:9px;margin-right:4px;"></i>${afternoonEvent.title}</span>
                               </div>` 
                            : `<div class="slot-open-chip" style="${!isSelectedMorning ? 'background:#111111;border:1px solid #111111;color:#FFFFFF;' : ''}"><i class="fa-solid ${!isSelectedMorning ? 'fa-circle-check' : 'fa-circle-check'}" style="font-size:9px;"></i> <span>${!isSelectedMorning ? 'Active Slot Selection' : 'Click to Select'}</span></div>`}
                    </div>
                </div>
            `;

            // Card Click Handlers
            const morningCard = document.getElementById(`slot-morning-${scope}`);
            const afternoonCard = document.getElementById(`slot-afternoon-${scope}`);
            const datetimeInput = document.getElementById('field-datetime-' + scope);

            if (morningCard && !isMorningBooked) {
                morningCard.addEventListener('click', () => {
                    if (datetimeInput) {
                        datetimeInput.value = `${dateStr}T09:00`;
                        onFormChange(scope);
                    }
                });
            }

            if (afternoonCard && !isAfternoonBooked) {
                afternoonCard.addEventListener('click', () => {
                    if (datetimeInput) {
                        datetimeInput.value = `${dateStr}T14:00`;
                        onFormChange(scope);
                    }
                });
            }

            // Toggle submit button state based on availability
            toggleSubmitButton(scope, hasConflict);
        }

    } catch (e) {
        console.error('Slot availability fetch error', e);
        container.style.display = 'none';
    }
}

window.selectModalSlot = function(scope, shift, timeStr) {
    const targetCard = document.getElementById(`slot-card-${shift}-${scope}`);
    if (targetCard && targetCard.classList.contains('disabled')) {
        return;
    }
    const dateInput = document.getElementById(`field-date-${scope}`);
    const dtInput = document.getElementById(`field-datetime-${scope}`);
    if (!dateInput || !dtInput) return;

    const nowLocal = new Date();
    const todayLocalYmd = nowLocal.getFullYear() + '-' + String(nowLocal.getMonth() + 1).padStart(2, '0') + '-' + String(nowLocal.getDate()).padStart(2, '0');
    const dateVal = dateInput.value || todayLocalYmd;
    dtInput.value = `${dateVal}T${timeStr}`;

    const pill = document.getElementById(`active-slot-pill-${scope}`);
    if (pill) {
        pill.textContent = shift === 'morning' ? `Morning Slot (${timeStr === '09:00' ? '09:00 AM' : timeStr})` : `Afternoon Slot (${timeStr === '14:00' ? '02:00 PM' : timeStr})`;
    }

    const mCard = document.getElementById(`slot-card-morning-${scope}`);
    const aCard = document.getElementById(`slot-card-afternoon-${scope}`);
    const mChip = document.getElementById(`slot-chip-morning-${scope}`);
    const aChip = document.getElementById(`slot-chip-afternoon-${scope}`);

    if (shift === 'morning') {
        if (mCard) {
            mCard.classList.add('selected');
            mCard.style.borderColor = '#111111';
        }
        if (aCard) {
            aCard.classList.remove('selected');
            aCard.style.borderColor = 'var(--border)';
        }
        if (mChip) {
            mChip.style.background = '#111111';
            mChip.style.borderColor = '#111111';
            mChip.style.color = '#FFFFFF';
            mChip.innerHTML = '<i class="fa-solid fa-circle-check" style="font-size:9px;"></i> <span>Active Slot Selection</span>';
        }
        if (aChip) {
            aChip.style.background = 'var(--bg3, #F4F4F5)';
            aChip.style.borderColor = 'var(--border)';
            aChip.style.color = 'var(--text2)';
            aChip.innerHTML = '<i class="fa-regular fa-hand-pointer" style="font-size:9px;"></i> <span>Click to Select</span>';
        }
    } else {
        if (mCard) {
            mCard.classList.remove('selected');
            mCard.style.borderColor = 'var(--border)';
        }
        if (aCard) {
            aCard.classList.add('selected');
            aCard.style.borderColor = '#111111';
        }
        if (aChip) {
            aChip.style.background = '#111111';
            aChip.style.borderColor = '#111111';
            aChip.style.color = '#FFFFFF';
            aChip.innerHTML = '<i class="fa-solid fa-circle-check" style="font-size:9px;"></i> <span>Active Slot Selection</span>';
        }
        if (mChip) {
            mChip.style.background = 'var(--bg3, #F4F4F5)';
            mChip.style.borderColor = 'var(--border)';
            mChip.style.color = 'var(--text2)';
            mChip.innerHTML = '<i class="fa-regular fa-hand-pointer" style="font-size:9px;"></i> <span>Click to Select</span>';
        }
    }

    if (typeof onFormChange === 'function') {
        onFormChange(scope);
    }
};

window.syncModalDateTimeAndSlots = function(scope) {
    const dateInput = document.getElementById(`field-date-${scope}`);
    const dtInput = document.getElementById(`field-datetime-${scope}`);
    if (!dateInput || !dtInput) return;

    const dateVal = dateInput.value;
    if (!dateVal) return;

    let timePart = '09:00';
    if (dtInput.value && dtInput.value.includes('T')) {
        timePart = dtInput.value.split('T')[1];
    }
    dtInput.value = `${dateVal}T${timePart}`;

    const hr = parseInt(timePart.split(':')[0], 10);
    const shift = (hr < 12) ? 'morning' : 'afternoon';
    selectModalSlot(scope, shift, timePart);
};

function resetStaticModalSlots(scope, reasonText = 'Available') {
    ['morning', 'afternoon'].forEach(shift => {
        const card = document.getElementById(`slot-card-${shift}-${scope}`);
        const badge = document.getElementById(`slot-badge-${shift}-${scope}`);
        const chip = document.getElementById(`slot-chip-${shift}-${scope}`);
        if (card) card.classList.remove('disabled');
        if (badge) {
            badge.className = 'slot-badge available';
            badge.innerHTML = `<i class="fa-solid fa-circle-info" style="font-size:7px;"></i> ${escapeAvailHtml(reasonText)}`;
        }
        if (chip && card && !card.classList.contains('selected')) {
            chip.innerHTML = '<i class="fa-regular fa-hand-pointer" style="font-size:9px;"></i> <span>Click to Select</span>';
        }
    });
}

function toggleSubmitButton(scope, hasConflict) {
    const anchor = document.getElementById('timeline-preview-' + scope)
        || document.getElementById('venue-warning-' + scope)
        || document.getElementById('field-datetime-' + scope)
        || document.getElementById('selected-pastor-input-' + scope);
    const form = anchor ? anchor.closest('form') : null;
    const btn = form ? form.querySelector('button[type="submit"]') : null;
    if (!btn) return;

    if (hasConflict) {
        btn.disabled = true;
        btn.style.opacity = '0.5';
        btn.style.cursor = 'not-allowed';
        btn.title = 'Cannot submit due to schedule conflict.';
    } else {
        btn.disabled = false;
        btn.style.opacity = '1';
        btn.style.cursor = 'pointer';
        btn.title = '';
    }
}

function initDraggableTimeline(block, bar, startHour, endHour, scope, curDur = 60) {
    let isDragging = false;
    let dragOffsetX = 0;
    const input = document.getElementById('field-datetime-' + scope);
    if (!input) return;

    block.addEventListener('mousedown', (e) => {
        isDragging = true;
        const blockRect = block.getBoundingClientRect();
        dragOffsetX = e.clientX - blockRect.left;
        block.style.cursor = 'grabbing';
        block.classList.add('dragging');
        e.preventDefault();
    });

    window.addEventListener('mousemove', (e) => {
        if (!isDragging) return;

        const rect = bar.getBoundingClientRect();
        let offsetX = (e.clientX - dragOffsetX) - rect.left;
        
        const totalMin = (endHour - startHour) * 60;
        const maxOffset = rect.width * (1 - (curDur / totalMin));
        
        if (offsetX < 0) offsetX = 0;
        if (offsetX > maxOffset) offsetX = maxOffset;

        const percent = offsetX / rect.width;
        let targetMin = percent * totalMin;

        // Snap to 15 minutes
        targetMin = Math.round(targetMin / 15) * 15;
        
        const visualLeft = (targetMin / totalMin) * 100;
        block.style.left = visualLeft + '%';

        const currentDate = new Date(input.value || new Date());
        if (isNaN(currentDate.getTime())) return;

        const newDate = new Date(currentDate);
        newDate.setHours(startHour + Math.floor(targetMin / 60));
        newDate.setMinutes(targetMin % 60);

        const newValue = newDate.getFullYear() + '-' + 
                        String(newDate.getMonth() + 1).padStart(2, '0') + '-' + 
                        String(newDate.getDate()).padStart(2, '0') + 'T' + 
                        String(newDate.getHours()).padStart(2, '0') + ':' + 
                        String(newDate.getMinutes()).padStart(2, '0');
        
        if (input.value !== newValue) {
            input.value = newValue;
        }
    });

    window.addEventListener('mouseup', () => {
        if (isDragging) {
            isDragging = false;
            block.style.cursor = 'grab';
            block.classList.remove('dragging');
            onFormChange(scope); // Re-validate availability on drop
        }
    });
}

function getPastorStatusInfo(pastor, datetimeStr = '') {
    if (!pastor || pastor.available) {
        return { label: 'Available', dropdownTag: '', statusClass: 'available', color: '#16a34a' };
    }
    if (pastor.conflict_status === 'Off-Duty') {
        return { label: 'Unavailable', dropdownTag: ' (Unavailable - Off-Duty)', statusClass: 'off-duty', color: '#64748b' };
    }
    const now = new Date();
    const todayStr = now.getFullYear() + '-' + String(now.getMonth() + 1).padStart(2, '0') + '-' + String(now.getDate()).padStart(2, '0');
    const targetDate = String(datetimeStr || '').slice(0, 10);
    const isPresentDay = (targetDate === '' || targetDate === todayStr);
    const isPending = (pastor.conflict_status === 'Pending');

    if (isPresentDay) {
        return {
            label: 'Busy',
            dropdownTag: isPending ? ' (Busy - Pending)' : ' (Busy)',
            statusClass: isPending ? 'pending-conflict' : 'unavailable',
            color: isPending ? '#c5a059' : '#dc2626'
        };
    }
    return {
        label: 'Not Available',
        dropdownTag: isPending ? ' (Not Available - Pending)' : ' (Not Available)',
        statusClass: isPending ? 'pending-conflict' : 'unavailable',
        color: isPending ? '#c5a059' : '#dc2626'
    };
}

function updatePastorSelect(eventType, datetime, selectId, customDuration = 0) {
    const select = document.getElementById(selectId);
    if (!select) return;

    let excludeId = '';
    if (selectId === 'field-pastor-edit-modal') excludeId = document.getElementById('edit-event-id')?.value || '';

    const scope = selectId.replace(/^field-pastor-/, '');

    // Robust Venue Lookup
    let venue = "";
    const venueEl = document.getElementById('field-venue-' + scope);
    if (venueEl) {
        venue = venueEl.value;
    } else if (selectId === 'approve-pastor-select') {
        venue = window.currentApprovalLoc || "";
    } else {
        // Fallback to searching by selectId derivation
        const derivedVenueEl = document.getElementById('field-venue-' + selectId.replace('field-pastor-', ''));
        if (derivedVenueEl) venue = derivedVenueEl.value;
    }

    const params = new URLSearchParams({
        event_type:      eventType,
        datetime:        datetime,
        chapel_id:       venue || '',
        exclude_id:      excludeId,
        custom_duration: customDuration || 0
    });

    fetch(getApiUrl(`app/controllers/get_priest_availability.php?${params}`), {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
        .then(r => r.json())
        .then(pastors => {
            const firstOpt = select.options[0];
            select.innerHTML = '';
            select.appendChild(firstOpt);
            pastors.forEach(p => {
                const st = getPastorStatusInfo(p, datetime);
                const opt = document.createElement('option');
                opt.value = p.id;
                opt.textContent = p.name + st.dropdownTag;
                opt.disabled = !p.available;
                select.appendChild(opt);
            });
        });
}

function checkVenueAvailability(venue, datetime, scope, eventType = 'Other', customDuration = 0) {
    if (venue === null || venue === undefined || String(venue).trim() === '') {
        setScheduleWarning(scope, '');
        return;
    }

    let excludeId = '';
    if (scope === 'edit-modal') excludeId = document.getElementById('edit-event-id')?.value || '';
    if (scope === 'approve') excludeId = document.getElementById('approve-req-id')?.value || '';

    const locTypeEl = document.getElementById('field-location-type-' + scope) || (scope === 'request' ? document.getElementById('field-location-type') : null);
    const locationType = locTypeEl ? (locTypeEl.value || 'Church') : 'Church';

    const params = new URLSearchParams({
        venue:           venue,
        datetime:        datetime,
        event_type:      eventType,
        exclude_id:      excludeId,
        custom_duration: customDuration || 0,
        location_type:   locationType
    });

    fetch(getApiUrl(`app/controllers/get_venue_availability.php?${params}`), {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
        .then(r => r.json())
        .then(data => {
            if (data && !Array.isArray(data) && data.available === false) {
                const statusLabel = data.conflict_status === 'Pending' ? ' (Pending Request)' : ' (Confirmed)';
                const color = data.conflict_status === 'Pending' ? '#c5a059' : '#dc2626';
                setScheduleWarning(scope, `<span style="color:${color}; font-weight:600;">Venue conflict:</span> "${escapeAvailHtml(data.conflict || 'Time slot reserved')}" ${statusLabel} overlaps with this event's scheduled time.`);
                if (scope === 'approve') toggleSubmitButton(scope, true);
            } else {
                setScheduleWarning(scope, '');
                if (scope === 'approve') toggleSubmitButton(scope, false);
            }
        })
        .catch(() => {
            setScheduleWarning(scope, '');
        });
}

function loadPastorCards(eventType, datetime, scope, customDuration = 0, chapelId = null) {
    const grid = document.getElementById('pastor-picker-grid-' + scope);
    const none = document.getElementById('pastor-none-' + scope) || document.getElementById('pastor-picker-none-' + scope);
    if (!grid) return;

    let excludeId = '';
    if (scope === 'edit-modal') excludeId = document.getElementById('edit-event-id')?.value || '';
    if (scope === 'approve') excludeId = document.getElementById('approve-req-id')?.value || '';

    grid.innerHTML = '<div class="p-16 text-center" style="grid-column:1/-1; color:var(--text2); font-size:12px;"><i class="fa-solid fa-spinner fa-spin mr-8"></i> Checking availability...</div>';
    if (none) none.style.display = 'none';

    // Normalize venue ID
    const venue = (chapelId !== null && chapelId !== undefined) ? chapelId : (document.getElementById('field-venue-' + scope)?.value || '');

    const params = new URLSearchParams({
        event_type:      eventType,
        datetime:        datetime,
        chapel_id:       venue,
        exclude_id:      excludeId,
        custom_duration: customDuration || 0
    });

    fetch(getApiUrl(`app/controllers/get_priest_availability.php?${params}`), {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
        .then(r => r.json())
        .then(pastors => {
            grid.innerHTML = '';
            if (pastors.length === 0) {
                if (none) none.style.display = 'block';
                return;
            }

            pastors.forEach(pastor => {
                const isAvailable = pastor.available;
                const isSelected  = selectedPastor[scope] == pastor.id;
                const stInfo      = getPastorStatusInfo(pastor, datetime);
                const statusClass = stInfo.statusClass;
                const dotClass    = isSelected ? 'dot-selected' : (isAvailable ? 'dot-available' : (statusClass === 'off-duty' ? 'dot-off-duty' : (statusClass === 'pending-conflict' ? 'dot-pending' : 'dot-unavailable')));

                const card = document.createElement('div');
                card.className = 'pastor-card ' + (isSelected ? 'selected' : statusClass);
                card.dataset.id    = pastor.id;
                card.dataset.name  = pastor.name;
                card.dataset.scope = scope;

                const conflictLabel = stInfo.label;
                const conflictColor = stInfo.color;

                // Format duty hours if available
                let dutyHtml = '';
                if (pastor.duty_start && pastor.duty_end) {
                    const formatTime = (t) => {
                        const parts = t.split(':');
                        const hr = parseInt(parts[0], 10);
                        const min = parts[1] || '00';
                        const ampm = hr >= 12 ? 'PM' : 'AM';
                        const h12 = hr % 12 || 12;
                        return `${h12}:${min} ${ampm}`;
                    };
                    const dStr = `${formatTime(pastor.duty_start)} – ${formatTime(pastor.duty_end)}`;
                    dutyHtml = `<div style="font-size:10.5px; color:var(--text2); margin-top:1px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" title="Duty Schedule: ${dStr}"><i class="fa-regular fa-clock" style="font-size:9px; margin-right:3px;"></i>${dStr}</div>`;
                }

                card.innerHTML = `
                    <div class="pastor-avatar">
                        ${pastor.initials}
                        <span class="pastor-status-dot ${dotClass}"></span>
                    </div>
                    <div class="pastor-info" style="flex:1; min-width:0; text-align:left;">
                        <div class="pastor-card-name" title="${escapeAvailHtml(pastor.name)}">${escapeAvailHtml(pastor.name)}</div>
                        ${dutyHtml}
                        ${!isAvailable && pastor.conflict_reason
                            ? `<div style="font-size:10px; color:${conflictColor}; margin-top:2px; line-height:1.3; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="${escapeAvailHtml(pastor.conflict_reason)}"><i class="fa-solid fa-location-dot" style="font-size:8.5px; margin-right:3px;"></i>${escapeAvailHtml(pastor.conflict_reason)}</div>`
                            : ''}
                    </div>
                    ${!isAvailable
                        ? `<div class="pastor-status-badge" style="font-size:10px; font-weight:700; padding:2px 7px; border-radius:10px; background:${statusClass === 'off-duty' ? 'rgba(148,163,184,0.1)' : (statusClass === 'pending-conflict' ? 'rgba(197,160,89,0.1)' : 'rgba(220,38,38,0.1)')}; color:${conflictColor}; border:1px solid ${statusClass === 'off-duty' ? 'rgba(148,163,184,0.25)' : (statusClass === 'pending-conflict' ? 'rgba(197,160,89,0.25)' : 'rgba(220,38,38,0.25)')}; flex-shrink:0;">
                            ${conflictLabel}
                           </div>
                           <div class="pastor-tooltip">${escapeAvailHtml(pastor.conflict_reason || (statusClass === 'off-duty' ? 'Unavailable (outside working hours)' : 'Not available due to an existing booking'))}</div>`
                        : `<div class="pastor-status-badge" style="font-size:10px; font-weight:700; padding:2px 7px; border-radius:10px; background:${pastor.is_inactive ? 'rgba(148,163,184,0.14)' : 'rgba(34,197,94,0.1)'}; color:${pastor.is_inactive ? '#64748b' : '#16a34a'}; border:1px solid ${pastor.is_inactive ? 'rgba(148,163,184,0.3)' : 'rgba(34,197,94,0.25)'}; flex-shrink:0;">
                            ${pastor.is_inactive ? 'Deactivated' : 'Available'}
                           </div>`
                    }
                `;

                if (isAvailable) card.addEventListener('click', () => selectPastor(pastor.id, scope));
                grid.appendChild(card);
            });

            if (selectedPastor[scope]) {
                const stillAvailable = pastors.find(p => p.id == selectedPastor[scope] && p.available);
                if (!stillAvailable) {
                    selectedPastor[scope] = null;
                    const input = document.getElementById('selected-pastor-input-' + scope);
                    if (input) input.value = '';
                }
            }
        })
        .catch(() => {
            grid.innerHTML = '<span style="font-size:12px;color:#ef4444;">Failed to load priest availability.</span>';
        });
}

function selectPastor(id, scope) {
    const isBlockedCard = (card) =>
        card.classList.contains('unavailable') ||
        card.classList.contains('pending-conflict') ||
        card.classList.contains('off-duty');

    const restoreAvailableCard = (card) => {
        if (isBlockedCard(card)) return;
        card.className = 'pastor-card available';
        const dot = card.querySelector('.pastor-status-dot');
        if (dot) dot.className = 'pastor-status-dot dot-available';
    };

    // Toggle deselection if already selected
    if (selectedPastor[scope] == id) {
        selectedPastor[scope] = null;
        const input = document.getElementById('selected-pastor-input-' + scope);
        if (input) input.value = '';

        document.querySelectorAll('.pastor-card[data-scope="' + scope + '"]').forEach(restoreAvailableCard);
        return;
    }

    selectedPastor[scope] = id;
    const input = document.getElementById('selected-pastor-input-' + scope);
    if (input) input.value = id;

    document.querySelectorAll('.pastor-card[data-scope="' + scope + '"]').forEach(card => {
        if (card.dataset.id == id) {
            if (isBlockedCard(card)) return;
            card.className = 'pastor-card selected';
            const dot = card.querySelector('.pastor-status-dot');
            if (dot) dot.className = 'pastor-status-dot dot-selected';
        } else {
            restoreAvailableCard(card);
        }
    });
}

// Auto-initialize timelines on load for visible forms
document.addEventListener('DOMContentLoaded', () => {
    const scopes = ['cathedral', 'chapel', 'request', 'modal', 'edit-modal', 'approve'];
    scopes.forEach(scope => {
        const dtInput = document.getElementById('field-datetime-' + scope);
        if (dtInput && dtInput.value) {
            // Wait slightly for other scripts (like pastor picker) to be ready
            setTimeout(() => onFormChange(scope), 100);
        }
    });
});
