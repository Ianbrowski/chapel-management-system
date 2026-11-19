/**
 * Dashboard Modals Module
 * Handles opening, closing, and pre-populating modals.
 */

function openModal(id) {
    const el = document.getElementById(id);
    if (el) {
        el.classList.add('open');
        el.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }
    if (id === 'modal-add-event') {
        const dateInput = document.getElementById('field-date-modal');
        if (dateInput && !dateInput.value) {
            dateInput.value = new Date().toISOString().substring(0, 10);
        }
        if (typeof window.syncModalDateTimeAndSlots === 'function') {
            window.syncModalDateTimeAndSlots('modal');
        }
        if (typeof onFormChange === 'function') {
            setTimeout(() => onFormChange('modal'), 50);
        }
    }
}

function closeModal(id) {
    const el = document.getElementById(id);
    if (el) {
        el.classList.remove('open');
        el.style.display = 'none';
    }
    const remainingOpen = document.querySelectorAll('.modal-overlay.open');
    if (remainingOpen.length === 0) {
        document.body.style.overflow = '';
    }
}

window.openModal = openModal;
window.closeModal = closeModal;

function openGuestAuthModal(actionContext) {
    const modal = document.getElementById('modal-guest-auth');
    if (!modal) return;
    const sub = document.getElementById('guest-modal-subtitle');
    if (sub && actionContext) {
        sub.textContent = 'To ' + actionContext + ', please sign in or register for a member account.';
    }
    openModal('modal-guest-auth');
    modal.style.display = 'flex';
}
window.openGuestAuthModal = openGuestAuthModal;

// Global Escape key accessibility: closes the topmost active modal
document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' || e.keyCode === 27) {
        const openModals = document.querySelectorAll('.modal-overlay.open');
        if (openModals.length > 0) {
            const topModal = openModals[openModals.length - 1];
            if (topModal.id) {
                closeModal(topModal.id);
            } else {
                topModal.classList.remove('open');
                topModal.style.display = 'none';
                if (openModals.length <= 1) {
                    document.body.style.overflow = '';
                }
            }
        }
    }
});

// Delegated click-outside handler (covers both static and AJAX SPA rendered modals)
document.addEventListener('click', function (e) {
    if (e.target && e.target.classList && e.target.classList.contains('modal-overlay') && e.target.classList.contains('open')) {
        if (e.target.id) {
            closeModal(e.target.id);
        } else {
            e.target.classList.remove('open');
            e.target.style.display = 'none';
            const remainingOpen = document.querySelectorAll('.modal-overlay.open');
            if (remainingOpen.length === 0) {
                document.body.style.overflow = '';
            }
        }
    }
});

function updateEditHeroTitle(val) {
    const el = document.getElementById('edit-hero-title-preview');
    if (el) el.innerText = val && val.trim() !== '' ? val : 'Liturgical Event';
}

function updateEditHeroStatus(st) {
    const pill = document.getElementById('edit-hero-status-pill');
    if (!pill) return;
    pill.innerText = st || 'Pending';
    if (st === 'Confirmed' || st === 'Completed') {
        pill.style.background = 'rgba(22,163,74,0.18)';
        pill.style.color = '#16a34a';
        pill.style.border = '1px solid rgba(22,163,74,0.4)';
    } else if (st === 'Cancelled' || st === 'Rejected') {
        pill.style.background = 'rgba(220,38,38,0.15)';
        pill.style.color = '#dc2626';
        pill.style.border = '1px solid rgba(220,38,38,0.35)';
    } else {
        pill.style.background = 'rgba(197,160,89,0.18)';
        pill.style.color = '#c5a059';
        pill.style.border = '1px solid rgba(197,160,89,0.4)';
    }
}

function updateEditSacramentType(type) {
    const iconEl = document.getElementById('edit-sacrament-icon');
    const boxEl = document.getElementById('edit-sacrament-icon-box');
    if (!iconEl || !boxEl) return;

    const lowerType = (type || '').toLowerCase();
    let iconClass = 'fa-solid fa-church';
    let iconColor = 'var(--accent)';
    let boxBg = 'rgba(201,168,76,0.15)';
    let boxBorder = 'var(--accent)';

    if (lowerType.includes('wedding') || lowerType.includes('matrimony')) {
        iconClass = 'fa-solid fa-heart';
        iconColor = 'var(--accent, #c9a84c)';
        boxBg = 'rgba(201,168,76,0.15)';
        boxBorder = 'rgba(201,168,76,0.4)';
    } else if (lowerType.includes('baptism') || lowerType.includes('christening')) {
        iconClass = 'fa-solid fa-water';
        iconColor = 'var(--accent, #c9a84c)';
        boxBg = 'rgba(201,168,76,0.15)';
        boxBorder = 'rgba(201,168,76,0.4)';
    } else if (lowerType.includes('funeral') || lowerType.includes('burial')) {
        iconClass = 'fa-solid fa-cross';
        iconColor = '#94a3b8';
        boxBg = 'rgba(148,163,184,0.15)';
        boxBorder = 'rgba(148,163,184,0.4)';
    } else if (lowerType.includes('mass') || lowerType.includes('eucharist')) {
        iconClass = 'fa-solid fa-church';
        iconColor = 'var(--accent, #c9a84c)';
        boxBg = 'rgba(201,168,76,0.18)';
        boxBorder = 'rgba(201,168,76,0.5)';
    } else if (lowerType.includes('confirmation')) {
        iconClass = 'fa-solid fa-dove';
        iconColor = 'var(--accent, #c9a84c)';
        boxBg = 'rgba(201,168,76,0.15)';
        boxBorder = 'rgba(201,168,76,0.4)';
    }

    iconEl.className = iconClass;
    boxEl.style.color = iconColor;
    boxEl.style.background = boxBg;
    boxEl.style.borderColor = boxBorder;
}

function openEditEventModal(input) {
    let ev = input;
    if (input instanceof HTMLElement) {
        const raw = input.getAttribute('data-event') || input.getAttribute('data-req');
        if (raw) {
            try { ev = JSON.parse(raw); } catch (e) { console.error('Failed to parse event data:', e); return; }
        }
    } else if (typeof input === 'string') {
        try { ev = JSON.parse(input); } catch (e) { console.error('Failed to parse event data:', e); return; }
    }
    if (!ev || typeof ev !== 'object') return;
    const eventId = ev.event_id || ev.id || 0;

    const idInput = document.getElementById('edit-event-id');
    if (idInput) idInput.value = eventId;

    const refBadge = document.getElementById('edit-event-ref-badge');
    if (refBadge) refBadge.innerText = `#EV-${String(eventId).padStart(4, '0')}`;

    const scopeInput = document.getElementById('edit-event-scope');
    if (scopeInput) scopeInput.value = ev.event_scope || 'cathedral';

    const titleInput = document.getElementById('edit-event-title');
    if (titleInput) titleInput.value = ev.title || '';
    updateEditHeroTitle(ev.title);

    const typeInput = document.getElementById('field-event-type-edit-modal');
    if (typeInput) {
        let rawType = ev.event_type || 'Wedding';
        let match = Array.from(typeInput.options).some(o => o.value.toLowerCase() === rawType.toLowerCase());
        if (!match) {
            if (rawType.toLowerCase().includes('wedding') || rawType.toLowerCase().includes('matrimony')) {
                rawType = 'Wedding (Holy Matrimony)';
            } else if (rawType.toLowerCase().includes('funeral') || rawType.toLowerCase().includes('burial')) {
                rawType = 'Burial Mass';
            }
        }
        typeInput.value = rawType;
    }
    updateEditSacramentType(ev.event_type || 'Wedding');

    if (ev.start_time) {
        try {
            const raw = ev.start_time.replace(' ', 'T');
            const dPart = raw.substring(0, 10);
            const tPart = raw.substring(11, 16) || '09:00';
            const dateInput = document.getElementById('field-date-edit-modal');
            if (dateInput) dateInput.value = dPart;
            const dtInput = document.getElementById('field-datetime-edit-modal');
            if (dtInput) dtInput.value = `${dPart}T${tPart}`;
            const hr = parseInt(tPart.split(':')[0], 10);
            const shift = (hr < 12) ? 'morning' : 'afternoon';
            if (typeof window.selectModalSlot === 'function') {
                window.selectModalSlot('edit-modal', shift, tPart);
            }
        } catch (e) {
            const dtInput = document.getElementById('field-datetime-edit-modal');
            if (dtInput) dtInput.value = ev.start_time;
        }
    }

    const venueValue = (ev.chapel_id !== null && ev.chapel_id !== undefined && ev.chapel_id !== '' && ev.chapel_id != 0)
        ? ev.chapel_id
        : 0;
    const venueInput = document.getElementById('field-venue-edit-modal');
    if (venueInput) venueInput.value = venueValue;

    // Status
    const statusInput = document.getElementById('edit-event-status');
    const evStatus = ev.status || 'Pending';
    if (statusInput) statusInput.value = evStatus;
    updateEditHeroStatus(evStatus);

    // Duration
    const durInput = document.getElementById('field-custom-duration-edit-modal');
    if (durInput) durInput.value = ev.custom_duration || '';

    // Contact
    const emailInput = document.getElementById('edit-event-email');
    if (emailInput) emailInput.value = ev.requester_email || '';

    const phoneInput = document.getElementById('edit-event-phone');
    if (phoneInput) phoneInput.value = ev.requester_phone || '';

    // Pre-select pastor in the visual picker
    const pastorInput = document.getElementById('selected-pastor-input-edit-modal');
    if (pastorInput) {
        pastorInput.value = ev.pastor_id || '';
        if (typeof selectedPastor !== 'undefined') {
            selectedPastor['edit-modal'] = ev.pastor_id || null;
        }
    }

    if (typeof onFormChange === 'function') {
        onFormChange('edit-modal');
    }

    openModal('modal-edit-event');
}
window.openEditEventModal = openEditEventModal;

/**
 * Populates and opens the detailed view modal for a scheduled event or request.
 * @param {Object} input The event data object or element.
 * @param {Boolean} readonly Whether to hide the edit action.
 * @param {Object} options Optional context flags (e.g. source: 'event_requests').
 */
function viewEventDetails(input, readonly = false, options = {}) {
    let ev = input;
    if (input instanceof HTMLElement) {
        const raw = input.getAttribute('data-event') || input.getAttribute('data-req');
        if (raw) {
            try { ev = JSON.parse(raw); } catch (e) { console.error('Failed to parse event details data:', e); return; }
        }
    } else if (typeof input === 'string') {
        try { ev = JSON.parse(input); } catch (e) { console.error('Failed to parse event details data:', e); return; }
    }
    if (!ev || typeof ev !== 'object') return;
    const userRole = typeof currentUserRole !== 'undefined' ? currentUserRole : (window.userRole || '');

    // 1. Ref & ID
    const eventId = ev.event_id || ev.id || 0;
    const refBadge = document.getElementById('ve-ref-badge');
    if (refBadge) refBadge.innerText = `#EV-${String(eventId).padStart(4, '0')}`;

    // 2. Title & Type
    const titleEl = document.getElementById('ve-title');
    if (titleEl) titleEl.innerText = ev.title || 'Untitled Liturgical Event';

    const evType = (ev.event_type || 'Other').trim();
    const typePill = document.getElementById('ve-type-pill');
    if (typePill) typePill.innerText = evType;

    // Liturgical Icon selection based on type
    const iconEl = document.getElementById('ve-type-icon');
    const iconBox = document.getElementById('ve-type-icon-box');
    if (iconEl) {
        const lowerType = evType.toLowerCase();
        let iconClass = 'fa-solid fa-church';
        let iconColor = 'var(--accent)';
        let boxBg = 'rgba(201,168,76,0.15)';
        let boxBorder = 'var(--accent)';

        if (lowerType.includes('wedding') || lowerType.includes('matrimony')) {
            iconClass = 'fa-solid fa-heart';
            iconColor = 'var(--accent, #c9a84c)';
            boxBg = 'rgba(201,168,76,0.15)';
            boxBorder = 'rgba(201,168,76,0.4)';
        } else if (lowerType.includes('baptism') || lowerType.includes('christening')) {
            iconClass = 'fa-solid fa-water';
            iconColor = 'var(--accent, #c9a84c)';
            boxBg = 'rgba(201,168,76,0.15)';
            boxBorder = 'rgba(201,168,76,0.4)';
        } else if (lowerType.includes('funeral') || lowerType.includes('burial')) {
            iconClass = 'fa-solid fa-cross';
            iconColor = '#94a3b8';
            boxBg = 'rgba(148,163,184,0.15)';
            boxBorder = 'rgba(148,163,184,0.4)';
        } else if (lowerType.includes('mass') || lowerType.includes('eucharist')) {
            iconClass = 'fa-solid fa-church';
            iconColor = 'var(--accent, #c9a84c)';
            boxBg = 'rgba(201,168,76,0.18)';
            boxBorder = 'rgba(201,168,76,0.5)';
        } else if (lowerType.includes('confirmation')) {
            iconClass = 'fa-solid fa-dove';
            iconColor = 'var(--accent, #c9a84c)';
            boxBg = 'rgba(201,168,76,0.15)';
            boxBorder = 'rgba(201,168,76,0.4)';
        }
        iconEl.className = iconClass;
        if (iconBox) {
            iconBox.style.color = iconColor;
            iconBox.style.background = boxBg;
            iconBox.style.borderColor = boxBorder;
        }
    }

    // 3. Status Pill & Overdue Check
    const st = ev.status || 'Pending';
    const stPill = document.getElementById('ve-status-pill');
    const startTimeMs = ev.start_time ? new Date(ev.start_time.replace(/-/g, '/')).getTime() : 0;
    const nowMs = Date.now();
    const isOverdue = (st === 'Pending' && startTimeMs > 0 && startTimeMs < nowMs);

    if (stPill) {
        if (isOverdue) {
            stPill.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> Overdue';
            stPill.style.background = 'rgba(220,38,38,0.18)';
            stPill.style.color = '#dc2626';
            stPill.style.border = '1px solid rgba(220,38,38,0.4)';
        } else if (st === 'Confirmed' || st === 'Completed') {
            stPill.innerHTML = `<i class="fa-solid fa-check"></i> ${st}`;
            stPill.style.background = 'rgba(22,163,74,0.18)';
            stPill.style.color = '#16a34a';
            stPill.style.border = '1px solid rgba(22,163,74,0.4)';
        } else if (st === 'Cancelled' || st === 'Rejected') {
            stPill.innerHTML = `<i class="fa-solid fa-ban"></i> ${st}`;
            stPill.style.background = 'rgba(220,38,38,0.12)';
            stPill.style.color = '#dc2626';
            stPill.style.border = '1px solid rgba(220,38,38,0.3)';
        } else {
            stPill.innerHTML = '<i class="fa-solid fa-clock"></i> Pending Review';
            stPill.style.background = 'rgba(197,160,89,0.18)';
            stPill.style.color = '#c5a059';
            stPill.style.border = '1px solid rgba(197,160,89,0.4)';
        }
    }

    // 4. Scope & Duration
    const scopePill = document.getElementById('ve-scope-pill');
    if (scopePill) {
        const sc = (ev.event_scope || 'cathedral').toLowerCase();
        scopePill.innerText = sc === 'cathedral' ? 'Main Cathedral Scope' : 'Chapel Branch Event';
    }

    const durText = document.getElementById('ve-duration-text');
    if (durText) {
        const customMins = parseInt(ev.custom_duration, 10);
        if (customMins && customMins > 0) {
            durText.innerText = customMins >= 60 ? `${(customMins / 60).toFixed(1).replace('.0', '')} hrs` : `${customMins} mins`;
        } else {
            const defaults = { Wedding: '2.0 hrs', Baptism: '1.5 hrs', Funeral: '1.5 hrs', Mass: '1.0 hr' };
            durText.innerText = defaults[evType] || '1.0 hr';
        }
    }

    // 5. Date & Time + Countdown Tag
    const dtEl = document.getElementById('ve-datetime');
    const cdText = document.getElementById('ve-countdown-text');
    const cdBadge = document.getElementById('ve-countdown-badge');

    if (ev.start_time) {
        try {
            const dObj = new Date(ev.start_time.replace(/-/g, '/'));
            if (!isNaN(dObj.getTime())) {
                const datePart = dObj.toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' });
                const isGuestUser = (userRole === 'Guest' || Boolean(ev.is_time_masked));
                if (isGuestUser) {
                    if (dtEl) dtEl.innerText = `${datePart} • ${ev.time_window || 'Liturgy Window'}`;
                } else {
                    const timePart = dObj.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
                    if (dtEl) dtEl.innerText = `${datePart} @ ${timePart}`;
                }

                // Calculate relative countdown
                const diffDays = Math.ceil((dObj.getTime() - nowMs) / (1000 * 60 * 60 * 24));
                if (cdText && cdBadge) {
                    if (st === 'Completed' || (st === 'Confirmed' && diffDays < 0)) {
                        cdText.innerText = 'Past Liturgical Event';
                        cdBadge.style.color = 'var(--text2)';
                        cdBadge.style.background = 'rgba(255,255,255,0.06)';
                        cdBadge.style.borderColor = 'var(--border)';
                    } else if (isOverdue) {
                        const lateDays = Math.abs(diffDays);
                        cdText.innerText = lateDays === 0 ? 'Overdue today' : `Overdue by ${lateDays} day${lateDays > 1 ? 's' : ''}`;
                        cdBadge.style.color = '#ef4444';
                        cdBadge.style.background = 'rgba(239,68,68,0.15)';
                        cdBadge.style.borderColor = 'rgba(239,68,68,0.35)';
                    } else if (diffDays === 0) {
                        cdText.innerText = 'Today';
                        cdBadge.style.color = '#10b981';
                        cdBadge.style.background = 'rgba(16,185,129,0.15)';
                        cdBadge.style.borderColor = 'rgba(16,185,129,0.35)';
                    } else if (diffDays === 1) {
                        cdText.innerText = 'Tomorrow';
                        cdBadge.style.color = 'var(--accent)';
                        cdBadge.style.background = 'rgba(201,168,76,0.15)';
                        cdBadge.style.borderColor = 'rgba(201,168,76,0.35)';
                    } else if (diffDays > 1) {
                        cdText.innerText = `In ${diffDays} days`;
                        cdBadge.style.color = 'var(--accent)';
                        cdBadge.style.background = 'rgba(201,168,76,0.12)';
                        cdBadge.style.borderColor = 'rgba(201,168,76,0.25)';
                    } else {
                        cdText.innerText = `${Math.abs(diffDays)} days ago`;
                        cdBadge.style.color = 'var(--text2)';
                        cdBadge.style.background = 'rgba(255,255,255,0.06)';
                        cdBadge.style.borderColor = 'var(--border)';
                    }
                }
            } else {
                if (dtEl) dtEl.innerText = ev.start_time;
            }
        } catch (e) {
            if (dtEl) dtEl.innerText = ev.start_time;
        }
    } else {
        if (dtEl) dtEl.innerText = 'Not specified';
    }

    // 6. Venue Location
    const locEl = document.getElementById('ve-location');
    if (locEl) {
        locEl.innerText = ev.chapel_location || (ev.chapel_id ? 'Chapel Branch' : 'Main Cathedral');
    }

    // 7. Presiding Priest & Status
    const pastorEl = document.getElementById('ve-pastor');
    const pastorStEl = document.getElementById('ve-pastor-status');
    const assignedPriest = ev.pastor_assigned && ev.pastor_assigned.trim() !== '' && !ev.pastor_assigned.includes('Unassigned')
        ? ev.pastor_assigned
        : null;

    if (pastorEl) {
        pastorEl.innerText = assignedPriest ? assignedPriest : 'Unassigned';
    }
    if (pastorStEl) {
        if (assignedPriest) {
            pastorStEl.innerText = 'Assigned Presiding Clergy';
            pastorStEl.style.color = '#10b981';
        } else {
            pastorStEl.innerText = 'Awaiting Priest Assignment';
            pastorStEl.style.color = 'var(--accent)';
        }
    }

    // 8. Requester & Contact Channels
    const reqNameEl = document.getElementById('ve-requester-name');
    if (reqNameEl) {
        reqNameEl.innerText = (userRole === 'Guest') ? 'Parishioner / Member' : (ev.requester_name || ev.encoded_by_name || 'Parishioner Request');
    }

    const emailLink = document.getElementById('ve-email-link');
    const emailText = document.getElementById('ve-email-text');
    if (userRole === 'Guest') {
        if (emailText) emailText.innerText = 'Protected (Parish Office)';
        if (emailLink) {
            emailLink.href = 'javascript:void(0)';
            emailLink.title = 'Protected Contact';
            emailLink.style.opacity = '0.65';
        }
    } else if (ev.requester_email && ev.requester_email.trim() !== '') {
        if (emailText) emailText.innerText = ev.requester_email;
        if (emailLink) {
            emailLink.href = `mailto:${encodeURIComponent(ev.requester_email)}?subject=${encodeURIComponent('IFI Cathedral Update: ' + (ev.title || 'Event'))}`;
            emailLink.title = 'Click to compose email';
            emailLink.style.opacity = '1';
        }
    } else {
        if (emailText) emailText.innerText = 'No email on record';
        if (emailLink) {
            emailLink.href = 'javascript:void(0)';
            emailLink.title = 'No email provided';
            emailLink.style.opacity = '0.65';
        }
    }

    const phoneLink = document.getElementById('ve-phone-link');
    const phoneText = document.getElementById('ve-phone-text');
    if (userRole === 'Guest') {
        if (phoneText) phoneText.innerText = 'Protected (Parish Office)';
        if (phoneLink) {
            phoneLink.href = 'javascript:void(0)';
            phoneLink.title = 'Protected Contact';
            phoneLink.style.opacity = '0.65';
        }
    } else if (ev.requester_phone && ev.requester_phone.trim() !== '') {
        if (phoneText) phoneText.innerText = ev.requester_phone;
        if (phoneLink) {
            phoneLink.href = `tel:${encodeURIComponent(ev.requester_phone)}`;
            phoneLink.title = 'Click to call / send SMS';
            phoneLink.style.opacity = '1';
        }
    } else {
        if (phoneText) phoneText.innerText = 'No phone on record';
        if (phoneLink) {
            phoneLink.href = 'javascript:void(0)';
            phoneLink.title = 'No phone provided';
            phoneLink.style.opacity = '0.65';
        }
    }

    // 9. Encoded By & Created At
    const encEl = document.getElementById('ve-encoded-by');
    if (encEl) encEl.innerText = (userRole === 'Guest') ? 'Parish Administration' : (ev.encoded_by_name || 'System / Automated');

    const catEl = document.getElementById('ve-created-at');
    if (catEl) {
        if (ev.created_at) {
            const cDate = new Date(ev.created_at.replace(/-/g, '/'));
            catEl.innerText = !isNaN(cDate.getTime())
                ? cDate.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
                : ev.created_at;
        } else {
            catEl.innerText = '—';
        }
    }

    // 10. Remarks / Notes & Rejection
    const notesBox = document.getElementById('ve-notes-container');
    const notesText = document.getElementById('ve-notes');
    const rawNotes = ev.details || ev.notes || '';
    if (userRole !== 'Guest' && rawNotes && rawNotes.trim() !== '') {
        if (notesText) notesText.innerText = rawNotes;
        if (notesBox) notesBox.style.display = 'block';
    } else {
        if (notesBox) notesBox.style.display = 'none';
    }

    const rejBox = document.getElementById('ve-rejection-container');
    const rejText = document.getElementById('ve-rejection-note');
    if (ev.rejection_note && ev.rejection_note.trim() !== '') {
        if (rejText) rejText.innerText = ev.rejection_note;
        if (rejBox) rejBox.style.display = 'block';
    } else {
        if (rejBox) rejBox.style.display = 'none';
    }

    const appBox = document.getElementById('ve-approval-container');
    const appText = document.getElementById('ve-approved-at');
    if (st === 'Confirmed' && ev.approved_at) {
        if (appText) {
            const aDate = new Date(ev.approved_at.replace(/-/g, '/'));
            appText.innerText = !isNaN(aDate.getTime())
                ? `Approved on ${aDate.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}`
                : ev.approved_at;
        }
        if (appBox) appBox.style.display = 'flex';
    } else {
        if (appBox) appBox.style.display = 'none';
    }

    // 11. Interactive Action Buttons
    const approveBtn = document.getElementById('ve-approve-btn');
    const rejectBtn = document.getElementById('ve-reject-btn');
    const canReview = ['Admin', 'Cathedral Admin', 'Chapel Admin'].includes(userRole);

    if (st === 'Pending' && canReview) {
        if (approveBtn) {
            approveBtn.style.display = 'inline-flex';
            approveBtn.onclick = () => {
                closeModal('modal-view-event-details');
                if (typeof openApproveModal === 'function') {
                    openApproveModal({
                        id: eventId,
                        title: ev.title,
                        location: ev.chapel_id,
                        pastor_id: ev.pastor_id,
                        start_time: ev.start_time,
                        event_type: ev.event_type,
                        custom_duration: ev.custom_duration
                    });
                }
            };
        }
        if (rejectBtn) {
            rejectBtn.style.display = 'inline-flex';
            rejectBtn.onclick = () => {
                closeModal('modal-view-event-details');
                if (typeof openRejectModal === 'function') {
                    openRejectModal({
                        id: eventId,
                        title: ev.title
                    });
                }
            };
        }
    } else {
        if (approveBtn) approveBtn.style.display = 'none';
        if (rejectBtn) rejectBtn.style.display = 'none';
    }

    // Setup Edit Button
    const editBtn = document.getElementById('ve-edit-btn');
    if (editBtn) {
        let autoReadonly = readonly;
        if (userRole === 'Guest' || ['Guest', 'Member'].includes(userRole)) {
            autoReadonly = true;
        } else if (['Confirmed', 'Completed'].includes(ev.status)) {
            if (!['Admin', 'Cathedral Admin', 'Chapel Admin'].includes(userRole)) {
                autoReadonly = true;
            }
        }
        if (autoReadonly) {
            editBtn.style.display = 'none';
        } else {
            editBtn.style.display = 'inline-flex';
            editBtn.onclick = () => {
                closeModal('modal-view-event-details');
                if (typeof openEditEventModal === 'function') {
                    openEditEventModal(ev);
                }
            };
        }
    }

    // Backward compatibility for legacy elements if present
    const legacyTitle = document.getElementById('det-title');
    if (legacyTitle) legacyTitle.innerText = ev.title || '';
    const legacyType = document.getElementById('det-type');
    if (legacyType) legacyType.innerText = ev.event_type || '';
    const legacyStatus = document.getElementById('det-status');
    if (legacyStatus) legacyStatus.innerText = ev.status || '';
    const legacyStart = document.getElementById('det-start');
    if (legacyStart) legacyStart.innerText = ev.start_time || '';
    const legacyLoc = document.getElementById('det-location');
    if (legacyLoc) legacyLoc.innerText = ev.chapel_location || '';
    const legacyPastor = document.getElementById('det-pastor');
    if (legacyPastor) legacyPastor.innerText = ev.pastor_assigned || 'Unassigned';
    const legacyNotes = document.getElementById('det-notes');
    if (legacyNotes) legacyNotes.innerText = ev.details || ev.notes || 'None';
    const legacyEnc = document.getElementById('det-encoded-by');
    if (legacyEnc) legacyEnc.innerText = ev.encoded_by_name || 'System';

    openModal('modal-view-event-details');
}
window.viewEventDetails = viewEventDetails;

function openEditUserModal(idOrEl, user, role, email, chapelId, fullName) {
    let id = idOrEl;
    if (idOrEl instanceof HTMLElement) {
        id = idOrEl.getAttribute('data-id');
        user = idOrEl.getAttribute('data-user');
        role = idOrEl.getAttribute('data-role');
        email = idOrEl.getAttribute('data-email');
        chapelId = idOrEl.getAttribute('data-chapel-id');
        fullName = idOrEl.getAttribute('data-fullname');
    }
    const idEl = document.getElementById('edit-user-id');
    if (idEl) idEl.value = id || '';
    const nameEl = document.getElementById('edit-user-name');
    if (nameEl) nameEl.value = user || '';
    const roleEl = document.getElementById('edit-user-role');
    if (roleEl) roleEl.value = role || '';
    const emailEl = document.getElementById('edit-user-email');
    if (emailEl) emailEl.value = email || '';
    const fnEl = document.getElementById('edit-user-fullname');
    if (fnEl) fnEl.value = fullName || '';

    const chapelSelect = document.getElementById('edit-user-chapel');
    if (chapelSelect) chapelSelect.value = chapelId || '';

    openModal('modal-edit-user');
}
window.openEditUserModal = openEditUserModal;

/**
 * Detailed View for Donation Records
 */
function viewDonationDetails(input) {
    let don = input;
    if (input instanceof HTMLElement) {
        const raw = input.getAttribute('data-donation');
        if (raw) {
            try { don = JSON.parse(raw); } catch (e) { console.error('Failed to parse donation data:', e); return; }
        }
    } else if (typeof input === 'string') {
        try { don = JSON.parse(input); } catch (e) { console.error('Failed to parse donation data:', e); return; }
    }
    if (!don || typeof don !== 'object') return;
    const refEl = document.getElementById('det-don-ref');
    if (refEl) refEl.innerText = don.reference_id || 'N/A';
    const amtEl = document.getElementById('det-don-amount');
    if (amtEl) amtEl.innerText = '₱' + parseFloat(don.amount || 0).toLocaleString(undefined, { minimumFractionDigits: 2 });
    const nameEl = document.getElementById('det-don-name');
    if (nameEl) nameEl.innerText = don.donor_name || 'Anonymous';
    const dateEl = document.getElementById('det-don-date');
    if (dateEl) dateEl.innerText = don.date_received || 'N/A';
    const methEl = document.getElementById('det-don-method');
    if (methEl) methEl.innerText = don.payment_method || 'Cash';
    const chapEl = document.getElementById('det-don-chapel');
    if (chapEl) chapEl.innerText = don.chapel_name || 'Cathedral';
    const purpEl = document.getElementById('det-don-purpose');
    if (purpEl) purpEl.innerText = don.purpose || 'General Offering';
    const logEl = document.getElementById('det-don-logger');
    if (logEl) logEl.innerText = don.logger_name || 'System';

    const printBtn = document.getElementById('det-don-print-btn');
    if (printBtn) {
        printBtn.href = `views/pages/donation_receipt.php?id=${don.donation_id}`;
    }

    openModal('modal-view-donation');
}
window.viewDonationDetails = viewDonationDetails;

/**
 * Detailed View for Merchandise Sales
 */
function viewMerchSaleDetails(input) {
    let sale = input;
    if (input instanceof HTMLElement) {
        const raw = input.getAttribute('data-sale');
        if (raw) {
            try { sale = JSON.parse(raw); } catch (e) { console.error('Failed to parse merch sale data:', e); return; }
        }
    } else if (typeof input === 'string') {
        try { sale = JSON.parse(input); } catch (e) { console.error('Failed to parse merch sale data:', e); return; }
    }
    if (!sale || typeof sale !== 'object') return;
    const itemEl = document.getElementById('det-sale-item');
    if (itemEl) itemEl.innerText = sale.item_name || 'Deleted Item';
    const totEl = document.getElementById('det-sale-total');
    if (totEl) totEl.innerText = '₱' + parseFloat(sale.total_price || 0).toLocaleString(undefined, { minimumFractionDigits: 2 });
    const buyerEl = document.getElementById('det-sale-buyer');
    if (buyerEl) buyerEl.innerText = sale.buyer_name || 'Walk-in';
    const dateEl = document.getElementById('det-sale-date');
    if (dateEl) dateEl.innerText = sale.sale_date ? new Date(sale.sale_date).toLocaleString() : 'N/A';
    const qtyEl = document.getElementById('det-sale-qty');
    if (qtyEl) qtyEl.innerText = sale.quantity || 1;

    const unitPrice = sale.unit_price || (sale.quantity ? (sale.total_price / sale.quantity) : sale.total_price);
    const unitEl = document.getElementById('det-sale-unit');
    if (unitEl) unitEl.innerText = '₱' + parseFloat(unitPrice || 0).toLocaleString(undefined, { minimumFractionDigits: 2 });
    const procEl = document.getElementById('det-sale-logger');
    if (procEl) procEl.innerText = sale.processor_name || 'System';

    const printBtn = document.getElementById('det-sale-print-btn');
    if (printBtn) {
        printBtn.href = `views/pages/merchandise_receipt.php?id=${sale.id}`;
    }

    openModal('modal-view-merch-sale');
}
window.viewMerchSaleDetails = viewMerchSaleDetails;

/**
 * Detailed View for Audit Log Entries
 */
function openAuditDetail(log) {
    if (!log) return;
    const actionEl = document.getElementById('det-audit-action');
    if (actionEl) {
        actionEl.innerText = log.action || 'info';
        actionEl.className = 'badge badge-' + (log.action || 'info').toLowerCase();
    }

    const modEl = document.getElementById('det-audit-module');
    if (modEl) modEl.innerText = log.module || 'System';
    const timeEl = document.getElementById('det-audit-time');
    if (timeEl) timeEl.innerText = log.created_at || '';
    const targEl = document.getElementById('det-audit-target');
    if (targEl) targEl.innerText = log.target_description || 'No description';
    const userEl = document.getElementById('det-audit-user');
    if (userEl) userEl.innerText = log.performed_by_name || 'System';
    const roleEl = document.getElementById('det-audit-role');
    if (roleEl) roleEl.innerText = log.performed_by_role || 'Automated';
    const ipEl = document.getElementById('det-audit-ip');
    if (ipEl) ipEl.innerText = log.ip_address || 'Internal';

    const diffCont = document.getElementById('det-audit-diff-container');
    if (log.old_snapshot || log.new_snapshot) {
        diffCont.style.display = 'block';
        const oldPre = document.getElementById('det-audit-old');
        const newPre = document.getElementById('det-audit-new');

        try {
            oldPre.innerText = log.old_snapshot ? JSON.stringify(JSON.parse(log.old_snapshot), null, 2) : 'NONE';
            newPre.innerText = log.new_snapshot ? JSON.stringify(JSON.parse(log.new_snapshot), null, 2) : 'NONE';
        } catch (e) {
            oldPre.innerText = log.old_snapshot || 'NONE';
            newPre.innerText = log.new_snapshot || 'NONE';
        }
    } else {
        diffCont.style.display = 'none';
    }

    openModal('modal-view-audit');
}


function openEditChapelModal(input) {
    let ch = input;
    if (input instanceof HTMLElement) {
        const raw = input.getAttribute('data-chapel');
        if (raw) {
            try { ch = JSON.parse(raw); } catch (e) { console.error('Failed to parse chapel data:', e); return; }
        }
    } else if (typeof input === 'string') {
        try { ch = JSON.parse(input); } catch (e) { console.error('Failed to parse chapel data:', e); return; }
    }
    if (!ch || typeof ch !== 'object') return;

    const idEl = document.getElementById('edit-chapel-id');
    const nameEl = document.getElementById('edit-chapel-name');
    const locEl = document.getElementById('edit-chapel-loc');
    const patronEl = document.getElementById('edit-chapel-patron');
    const capEl = document.getElementById('edit-chapel-capacity');
    const statusEl = document.getElementById('edit-chapel-status');
    const coordNameEl = document.getElementById('edit-chapel-coord-name');
    const coordPhoneEl = document.getElementById('edit-chapel-coord-phone');

    if (idEl) idEl.value = ch.chapel_id || '';
    if (nameEl) nameEl.value = ch.chapel_name || '';
    if (locEl) locEl.value = ch.location || '';
    if (patronEl) patronEl.value = ch.patron_saint || '';
    if (capEl) capEl.value = ch.seating_capacity || 50;
    if (statusEl) statusEl.value = ch.status || 'Active';
    if (coordNameEl) coordNameEl.value = ch.coordinator_name || '';
    if (coordPhoneEl) coordPhoneEl.value = ch.coordinator_phone || '';
    const stField = document.getElementById('edit-chapel-service-time');
    if (stField) stField.value = ch.sunday_service_time || 'Check Parish Schedule';

    const colorField = document.getElementById('edit-chapel-color');
    if (colorField) {
        colorField.value = ch.theme_color || '#4f46e5';
        if (typeof updateChapelColorDisplay === 'function') {
            updateChapelColorDisplay('edit-chapel-color', 'edit-chapel-color-preview');
        }
    }

    // Populate SMTP Fields
    const smtpHost = document.getElementById('edit-chapel-smtp-host');
    if (smtpHost) smtpHost.value = ch.smtp_host || '';

    const smtpPort = document.getElementById('edit-chapel-smtp-port');
    if (smtpPort) smtpPort.value = ch.smtp_port || '';

    const smtpUser = document.getElementById('edit-chapel-smtp-user');
    if (smtpUser) smtpUser.value = ch.smtp_user || '';

    const smtpPass = document.getElementById('edit-chapel-smtp-pass');
    if (smtpPass) smtpPass.value = ''; // Always clear password for security/manual update

    const smtpSecure = document.getElementById('edit-chapel-smtp-secure');
    if (smtpSecure) smtpSecure.value = ch.smtp_secure || 'tls';

    const fromEmail = document.getElementById('edit-chapel-from-email');
    if (fromEmail) fromEmail.value = ch.from_email || '';

    const fromName = document.getElementById('edit-chapel-from-name');
    if (fromName) fromName.value = ch.from_name || '';

    // Auto-expand SMTP if configured
    const smtpDetails = document.getElementById('edit-chapel-smtp-details');
    if (smtpDetails) {
        smtpDetails.open = !!(ch.smtp_host && ch.smtp_host.trim());
    }

    // Reset file input label
    const filenameLabel = document.getElementById('edit-chapel-filename');
    if (filenameLabel) filenameLabel.textContent = ch.chapel_image ? 'Current photo loaded' : 'No new file chosen';

    const previewDiv = document.getElementById('edit-chapel-image-preview');
    const imgTag = document.getElementById('edit-chapel-img-tag');
    if (previewDiv && imgTag) {
        if (ch.chapel_image) {
            imgTag.src = 'storage/' + ch.chapel_image;
            previewDiv.style.display = 'flex';
        } else {
            previewDiv.style.display = 'none';
        }
    }

    const container = document.getElementById('edit-fiesta-container');
    if (container) {
        container.innerHTML = '';
        let fiestas = [];
        try {
            if (ch.fiestas_json) fiestas = JSON.parse(ch.fiestas_json);
        } catch (e) { console.error('Fiesta parse error', e); }

        if (fiestas && fiestas.length > 0) {
            fiestas.forEach(f => {
                const parts = (f.date || '').split(' ');
                addFiestaRow('edit-fiesta-container', f.name, parts[0] || 'Jan', parts[1] || '1');
            });
        } else {
            addFiestaRow('edit-fiesta-container');
        }
    }

    openModal('modal-edit-chapel');
}
window.openEditChapelModal = openEditChapelModal;

function addFiestaRow(containerId, name = '', month = 'Jan', day = '1') {
    const container = document.getElementById(containerId);
    if (!container) return;

    const row = document.createElement('div');
    row.className = "chapel-fiesta-row";
    row.innerHTML = `
        <div style="flex: 2;">
            <input name="fiesta_names[]" value="${escHtml(name)}" placeholder="Feast Name (e.g. Patronal)" style="width:100%;">
        </div>
        <div style="flex: 1.2;">
            <select name="fiesta_months[]" style="width:100%;">
                ${window.fiestaMonthOpts || ''}
            </select>
        </div>
        <div style="width: 80px;">
            <select name="fiesta_days[]" style="width:100%;">
                ${window.fiestaDayOpts || ''}
            </select>
        </div>
        <button type="button" class="btn btn-ghost btn-icon" style="color:var(--color-danger, #ef4444); width:34px; height:34px; border-radius:6px; display:flex; align-items:center; justify-content:center;" title="Remove this feast day" onclick="this.closest('.chapel-fiesta-row').remove()">
            <i class="fa-solid fa-trash-can"></i>
        </button>
    `;

    const monthSel = row.querySelector('select[name="fiesta_months[]"]');
    const daySel = row.querySelector('select[name="fiesta_days[]"]');
    if (monthSel) monthSel.value = month;
    if (daySel) daySel.value = day;

    container.appendChild(row);
}

function selectChapelColorPreset(color, inputId, previewId) {
    const input = document.getElementById(inputId);
    if (input) {
        input.value = color;
        updateChapelColorDisplay(inputId, previewId);
    }
}

function updateChapelColorDisplay(inputId, previewId) {
    const input = document.getElementById(inputId);
    const preview = document.getElementById(previewId);
    if (!input || !preview) return;
    const color = input.value || '#c9a84c';
    preview.style.backgroundColor = color;
    preview.textContent = color.toUpperCase();

    const container = input.closest('.chapel-color-picker-wrap');
    if (container) {
        container.querySelectorAll('.chapel-color-swatch').forEach(sw => {
            if (sw.dataset.color.toLowerCase() === color.toLowerCase()) {
                sw.classList.add('active');
            } else {
                sw.classList.remove('active');
            }
        });
    }
}

function handleChapelImageSelect(input, previewDivId, imgTagId, filenameId) {
    const previewDiv = document.getElementById(previewDivId);
    const imgTag = document.getElementById(imgTagId);
    const filenameEl = document.getElementById(filenameId);

    if (input.files && input.files[0]) {
        const file = input.files[0];
        if (filenameEl) filenameEl.textContent = file.name + ' (' + Math.round(file.size / 1024) + ' KB)';

        const reader = new FileReader();
        reader.onload = function (e) {
            if (imgTag) imgTag.src = e.target.result;
            if (previewDiv) previewDiv.style.display = 'flex';
        };
        reader.readAsDataURL(file);
    }
}

function openViewBulletinModal(input) {
    let el = (input instanceof HTMLElement) ? (input.closest('[data-post]') || input) : null;
    let bp = input;
    if (el) {
        const raw = el.getAttribute('data-post');
        if (raw) {
            try { bp = JSON.parse(raw); } catch (e) { console.error('Failed to parse bulletin post:', e); return; }
        }
    } else if (typeof input === 'string') {
        try { bp = JSON.parse(input); } catch (e) { console.error('Failed to parse bulletin post:', e); return; }
    }
    if (!bp || typeof bp !== 'object') return;

    const dot = document.getElementById('bulletin-view-dot');
    const badge = document.getElementById('bulletin-view-badge');
    const title = document.getElementById('bulletin-view-title');
    const sub = document.getElementById('bulletin-view-subtitle');

    if (dot) dot.style.background = bp.dot_color || 'var(--accent)';
    if (badge) badge.textContent = bp.chapel_name ? '⛪ ' + bp.chapel_name : '🌐 Parish-Wide Notice';
    if (title) title.textContent = bp.title || 'Announcement';
    if (sub) sub.textContent = bp.subtitle || 'No additional details provided.';

    openModal('modal-view-bulletin');
}
window.openViewBulletinModal = openViewBulletinModal;

function openEditBulletinModal(input) {
    let el = (input instanceof HTMLElement) ? (input.closest('[data-post]') || input) : null;
    let bp = input;
    if (el) {
        const raw = el.getAttribute('data-post');
        if (raw) {
            try { bp = JSON.parse(raw); } catch (e) { console.error('Failed to parse bulletin post:', e); return; }
        }
    } else if (typeof input === 'string') {
        try { bp = JSON.parse(input); } catch (e) { console.error('Failed to parse bulletin post:', e); return; }
    }
    if (!bp || typeof bp !== 'object') return;

    const idEl = document.getElementById('edit-bulletin-id');
    const titleEl = document.getElementById('edit-bulletin-title');
    const subEl = document.getElementById('edit-bulletin-subtitle');
    const chapelSelect = document.getElementById('edit-bulletin-chapel-id');

    if (idEl) idEl.value = bp.post_id || '';
    if (titleEl) titleEl.value = bp.title || '';
    if (subEl) subEl.value = bp.subtitle || '';
    if (chapelSelect) {
        chapelSelect.value = (bp.chapel_id !== null && bp.chapel_id !== undefined) ? bp.chapel_id : '';
    }

    const radios = document.querySelectorAll('#edit-bulletin-colors input[type=radio]');
    radios.forEach(r => {
        r.checked = (r.value === bp.dot_color);
    });

    openModal('modal-edit-bulletin');
}
window.openEditBulletinModal = openEditBulletinModal;

async function openApproveModal(req) {
    if (req instanceof HTMLElement) {
        const raw = req.getAttribute('data-approve') || req.getAttribute('data-req');
        if (raw) {
            try { req = JSON.parse(raw); } catch (e) { console.error('Failed to parse approval data:', e); return; }
        }
    }
    if (!req) return;

    const idInput = document.getElementById('approve-req-id');
    if (idInput) idInput.value = req.id || req.event_id || '';
    const titleEl = document.getElementById('approve-req-title');
    if (titleEl) titleEl.textContent = req.title || '';

    // Set hidden pastor input
    const pastorInput = document.getElementById('selected-pastor-input-approve');
    if (pastorInput) {
        pastorInput.value = req.pastor_id || '';
        if (typeof selectedPastor !== 'undefined') {
            selectedPastor['approve'] = req.pastor_id || null;
        }
    }

    window.currentApprovalLoc = req.location || req.chapel_id || '';

    // Populate hidden fields for onFormChange
    const typeEl = document.getElementById('field-event-type-approve');
    const dateEl = document.getElementById('field-datetime-approve');
    const venueEl = document.getElementById('field-venue-approve');
    const durEl = document.getElementById('field-custom-duration-approve');

    if (typeEl) typeEl.value = req.event_type || '';
    if (dateEl) dateEl.value = (req.start_time || '').replace(' ', 'T').substring(0, 16);
    if (venueEl) venueEl.value = req.location || req.chapel_id || 0;
    if (durEl) durEl.value = req.custom_duration || 0;

    // Refresh availability
    if (typeof onFormChange === 'function') {
        onFormChange('approve');
    }

    const warnEl = document.getElementById('coverage-warning-approve');
    if (warnEl) warnEl.style.display = 'none';
    openModal('modal-approve-request');
}

window.openApproveModal = openApproveModal;

function openRejectModal(req) {
    if (req instanceof HTMLElement) {
        const raw = req.getAttribute('data-reject') || req.getAttribute('data-req');
        if (raw) {
            try { req = JSON.parse(raw); } catch (e) { console.error('Failed to parse reject data:', e); return; }
        }
    }
    if (!req) return;

    const idInput = document.getElementById('reject-req-id');
    if (idInput) idInput.value = req.id || req.event_id || '';
    const titleEl = document.getElementById('reject-req-title');
    if (titleEl) titleEl.textContent = req.title || '';
    const noteEl = document.getElementById('reject-note');
    if (noteEl) noteEl.value = '';
    openModal('modal-reject-request');
}

window.openRejectModal = openRejectModal;

document.addEventListener('change', (e) => {
    if (e.target.id === 'approve-pastor-select') {
        const pastorId = e.target.value;
        const loc = window.currentApprovalLoc || '';
        const warnEl = document.getElementById('coverage-warning-approve');
        const warnText = document.getElementById('coverage-warning-text-approve');

        if (!pastorId || !window.allPastors) {
            if (warnEl) warnEl.style.display = 'none';
            return;
        }

        const pastor = window.allPastors.find(p => p.id == pastorId);
        if (pastor) {
            let targetLocName = "Main Cathedral";
            if (loc && loc != "0" && window.allChapels) {
                const chapel = window.allChapels.find(c => c.chapel_id == loc);
                if (chapel) {
                    targetLocName = chapel.chapel_name;
                }
            }

            const coverage = (pastor.chapel_coverage || '').split(',');
            if (coverage.length > 0 && coverage[0] !== '') {
                if (!coverage.includes(targetLocName)) {
                    if (warnText) warnText.innerText = `Selected pastor does not cover ${targetLocName === "Main Cathedral" ? "Main Cathedral" : "this chapel (" + targetLocName + ")"}! Check their coverage settings.`;
                    if (warnEl) warnEl.style.display = 'block';
                } else {
                    if (warnEl) warnEl.style.display = 'none';
                }
            } else {
                // If pastor has no specific coverage configured, they are available everywhere by default
                if (warnEl) warnEl.style.display = 'none';
            }
        }
    }
});

/**
 * Ticketing Section JS
 */
async function viewTicket(id) {
    const modal = document.getElementById('modal-view-ticket');
    if (!modal) return;

    // Reset UI
    const noEl = document.getElementById('vt-ticket-no');
    if (noEl) noEl.innerText = 'Loading...';
    const subEl = document.getElementById('vt-subject');
    if (subEl) subEl.innerText = '...';
    const commentsEl = document.getElementById('vt-comments-container');
    if (commentsEl) commentsEl.innerHTML = '<div style="text-align:center; padding:40px; color:var(--text2);"><i class="fa-solid fa-spinner fa-spin"></i> Fetching conversation...</div>';

    openModal('modal-view-ticket');

    try {
        const formData = new FormData();
        formData.append('form_section', 'tickets');
        formData.append('action', 'get_details');
        formData.append('ticket_id', id);
        formData.append('csrf', document.querySelector('input[name="csrf"]')?.value || '');

        const baseUrl = (typeof window !== 'undefined' && window.BASE_URL) ? window.BASE_URL.replace(/\/+$/, '') : '';
        const endpoint = baseUrl ? `${baseUrl}/dashboard.php?ajax=1` : 'dashboard.php?ajax=1';
        const response = await fetch(endpoint, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: formData
        });

        const data = await response.json();
        if (data.error) {
            alert('Error: ' + data.error);
            closeModal('modal-view-ticket');
            return;
        }

        const t = data.ticket;
        if (noEl) noEl.innerText = 'Ticket ' + (t.ticket_no || '');
        if (subEl) subEl.innerText = t.subject || '';
        const descEl = document.getElementById('vt-description');
        if (descEl) descEl.innerText = t.description || 'No description provided.';
        const submitIdEl = document.getElementById('vt-submit-id');
        if (submitIdEl) submitIdEl.value = t.ticket_id || '';

        const dateStr = t.created_at ? new Date(t.created_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : '';
        const metaEl = document.getElementById('vt-meta');
        if (metaEl) metaEl.innerText = `Category: ${t.category || 'General'} • Created: ${dateStr} • By: ${t.requester_name || 'Guest'}`;

        const statusEl = document.getElementById('vt-status');
        if (statusEl) {
            statusEl.className = 'status-pill status-' + (t.status || 'open').toLowerCase().replace(' ', '-');
            statusEl.innerText = t.status || 'Open';
        }

        const priorityDot = document.getElementById('vt-priority-dot');
        if (priorityDot) priorityDot.className = 'priority-dot ' + (t.priority || 'medium').toLowerCase();
        const prioText = document.getElementById('vt-priority-text');
        if (prioText) prioText.innerText = t.priority || 'Medium';

        // Render Comments
        const commentsContainer = document.getElementById('vt-comments-container');
        if (commentsContainer) {
            commentsContainer.innerHTML = '';
            
            if (data.comments && data.comments.length > 0) {
                data.comments.forEach(c => {
                    const isMe = parseInt(c.user_id) === parseInt(data.current_user_id);
                    const commentDiv = document.createElement('div');
                    commentDiv.style = `margin-bottom:20px; display:flex; flex-direction:column; align-items: ${isMe ? 'flex-end' : 'flex-start'};`;
                    
                    const time = c.created_at ? new Date(c.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : '';
                    const internalBadge = c.is_internal == 1 ? '<span style="background:rgba(197,160,89,0.15); color:var(--accent); padding:2px 6px; border-radius:4px; font-size:9px; margin-left:6px;">INTERNAL NOTE</span>' : '';
                    
                    commentDiv.innerHTML = `
                        <div style="font-size:11px; color:var(--text2); margin-bottom:4px;">
                            ${isMe ? 'You' : (c.author_name || 'System')} • ${time} ${internalBadge}
                        </div>
                        <div style="max-width:85%; padding:12px 16px; border-radius:14px; background: ${isMe ? 'var(--accent)' : 'var(--bg3)'}; color: ${isMe ? '#000' : 'var(--text)'}; font-size:13px; line-height:1.5; border:1px solid var(--border);">
                            ${escHtml(c.comment_text)}
                        </div>
                    `;
                    commentsContainer.appendChild(commentDiv);
                });
                // Scroll to bottom
                setTimeout(() => { commentsContainer.scrollTop = commentsContainer.scrollHeight; }, 100);
            } else {
                commentsContainer.innerHTML = '<div style="text-align:center; padding:40px; color:var(--text2); opacity:0.5;">No messages yet. Start the conversation below.</div>';
            }
        }

    } catch (e) {
        console.error('Ticket Fetch Error:', e);
        alert('Failed to load ticket details.');
    }
}

function escHtml(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

// Global window assignments for reliable cross-module and inline onclick execution
window.openAuditDetail = openAuditDetail;
window.addFiestaRow = addFiestaRow;
window.selectChapelColorPreset = selectChapelColorPreset;
window.updateChapelColorDisplay = updateChapelColorDisplay;
window.handleChapelImageSelect = handleChapelImageSelect;
window.viewTicket = viewTicket;
window.escHtml = escHtml;
