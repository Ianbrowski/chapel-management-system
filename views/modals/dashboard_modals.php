<?php require_once __DIR__ . '/../partials/modals/record_form_fields.php'; ?>
<?php
$chapelId = $chapelId ?? ($_SESSION['chapel_id'] ?? null);
$months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
$monthOpts = '';
foreach ($months as $m) {
    $monthOpts .= "<option value='$m'>$m</option>";
}
$dayOpts = '';
for ($i = 1; $i <= 31; $i++) {
    $dayOpts .= "<option value='$i'>$i</option>";
}
?>

<!-- MODAL: Void Donation (Admin only) -->
<div class="modal-overlay" id="modal-void-donation">
    <div class="modal" style="max-width:480px;">
        <div class="modal-header">
            <div class="modal-title" style="color:#ef4444;"><i class="fa-solid fa-ban"></i> Void Donation</div>
            <button class="modal-close" onclick="closeModal('modal-void-donation')">×</button>
        </div>
        <form id="form-void-donation" method="POST">
            <input type="hidden" name="form_section" value="donations">
            <input type="hidden" name="action" value="void_donation">
            <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
            <input type="hidden" name="id" id="void-donation-id">
            <div class="modal-body">
                <div style="background:rgba(239,68,68,0.08); border:1px solid rgba(239,68,68,0.25); border-radius:10px; padding:14px; margin-bottom:18px;">
                    <div style="font-size:11px; text-transform:uppercase; letter-spacing:.08em; color:#f87171; font-weight:600; margin-bottom:6px;">
                        <i class="fa-solid fa-triangle-exclamation"></i> Voiding Record
                    </div>
                    <div style="font-size:14px; font-weight:700;" id="void-donation-label">—</div>
                    <div style="font-size:11px; color:var(--text2); margin-top:4px;">
                        This record will be moved to the Voided ledger. It cannot be permanently deleted — it remains visible to Admins for audit purposes.
                    </div>
                </div>
                <div class="form-group">
                    <label>Reason for Voiding <span style="color:#ef4444;">*</span></label>
                    <input type="text" name="void_reason" id="void-reason-input"
                           placeholder="e.g. Duplicate entry, Data entry error, Reversed transaction"
                           required maxlength="500"
                           style="border-color:rgba(239,68,68,0.4);">
                    <p style="font-size:11px; color:var(--text2); margin-top:5px;">
                        <i class="fa-solid fa-circle-info"></i> This reason will be permanently logged in the audit trail with your name and timestamp.
                    </p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn btn-red" id="void-submit-btn"
                        onclick="if(!document.getElementById('void-reason-input').value.trim()){alert('Please enter a reason.');return false;}">
                    <i class="fa-solid fa-ban"></i> Confirm Void
                </button>
                <button type="button" class="btn btn-gray" onclick="closeModal('modal-void-donation')">Cancel</button>
            </div>
        </form>
    </div>
</div>
<script>
function openVoidDonationModal(idOrEl, refId, donorName) {
    let id = idOrEl;
    if (idOrEl instanceof HTMLElement) {
        id = idOrEl.getAttribute('data-id');
        refId = idOrEl.getAttribute('data-ref');
        donorName = idOrEl.getAttribute('data-donor');
    }
    const idInput = document.getElementById('void-donation-id');
    if (idInput) idInput.value = id || '';
    const labelEl = document.getElementById('void-donation-label');
    if (labelEl) labelEl.textContent = (refId || '') + ' — ' + (donorName || 'Anonymous');
    const reasonInput = document.getElementById('void-reason-input');
    if (reasonInput) reasonInput.value = '';
    openModal('modal-void-donation');
}
window.openVoidDonationModal = openVoidDonationModal;
</script>

<!-- MODAL: Chapel Hub Profile & Liturgical Snapshot -->
<div class="modal-overlay" id="modal-chapel-hub">
    <div class="modal" style="max-width: 580px; border-radius: 12px; overflow: hidden; padding: 0; background: var(--panel-bg, var(--bg));">
        <!-- Chapel Header Stripe -->
        <div id="hub-header-stripe" style="height: 4px; background: var(--accent, #C9A84C);"></div>

        <div style="padding: 16px 20px 14px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; background: var(--bg2);">
            <div style="display: flex; gap: 12px; align-items: center;">
                <div id="hub-icon-box" style="width: 44px; height: 44px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 18px; background: var(--bg3); color: var(--text); border: 1px solid var(--border); flex-shrink: 0;">
                    <i class="fa-solid fa-church"></i>
                </div>
                <div>
                    <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                        <h2 id="hub-chapel-name" style="font-family: var(--font-family, 'Inter', sans-serif); font-size: 1.15rem; color: var(--text); margin: 0; font-weight: 700;">Chapel Name</h2>
                        <span id="hub-status-badge" class="badge badge-confirmed" style="font-size: 10px;">Active</span>
                    </div>
                    <div id="hub-patron-saint" style="color: var(--text2); font-size: 12px; font-weight: 500; margin-top: 2px;">
                        <i class="fa-solid fa-cross"></i> Patron: Saint Michael the Archangel
                    </div>
                </div>
            </div>
            <button class="modal-close" onclick="closeModal('modal-chapel-hub')" style="margin-top: -4px;">&times;</button>
        </div>

        <div class="modal-body" style="padding: 16px 20px; max-height: 70vh; overflow-y: auto; display: flex; flex-direction: column; gap: 14px;">
            <!-- Fiesta Countdown Banner -->
            <div id="hub-fiesta-card" style="background: var(--bg3); border: 1px solid var(--border); border-radius: 8px; padding: 12px 14px; display: flex; justify-content: space-between; align-items: center; gap: 10px;">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <div style="width: 36px; height: 36px; border-radius: 6px; background: var(--bg2); color: var(--text); border: 1px solid var(--border); display: flex; align-items: center; justify-content: center; font-size: 15px; flex-shrink: 0;">
                        <i class="fa-solid fa-champagne-glasses"></i>
                    </div>
                    <div>
                        <div style="font-size: 10px; font-weight: 700; text-transform: uppercase; color: var(--text2); letter-spacing: 0.05em;">Annual Fiesta Celebration</div>
                        <div id="hub-fiesta-title" style="font-weight: 700; font-size: 13px; color: var(--text);">Patronal Feast</div>
                        <div id="hub-fiesta-date" style="font-size: 11px; color: var(--text2);">Date: October 29</div>
                    </div>
                </div>
                <div id="hub-fiesta-countdown" style="text-align: right; background: var(--bg2); border: 1px solid var(--border); border-radius: 6px; padding: 6px 10px; min-width: 80px; flex-shrink: 0;">
                    <div id="hub-countdown-days" style="font-size: 16px; font-weight: 700; color: var(--text); line-height: 1;">--</div>
                    <div style="font-size: 9px; color: var(--text2); text-transform: uppercase;">Days Ahead</div>
                </div>
            </div>

            <!-- Liturgical & Facility Specs Grid -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                <div style="background: var(--bg2); border: 1px solid var(--border); border-radius: 8px; padding: 10px 12px;">
                    <div style="font-size: 10px; text-transform: uppercase; font-weight: 700; color: var(--text2); margin-bottom: 3px; display: flex; align-items: center; gap: 5px;">
                        <i class="fa-regular fa-clock" style="color: var(--text2);"></i> Regular Mass Schedule
                    </div>
                    <div id="hub-mass-time" style="font-size: 12px; font-weight: 600; color: var(--text);">Check Parish Schedule</div>
                </div>

                <div style="background: var(--bg2); border: 1px solid var(--border); border-radius: 8px; padding: 10px 12px;">
                    <div style="font-size: 10px; text-transform: uppercase; font-weight: 700; color: var(--text2); margin-bottom: 3px; display: flex; align-items: center; gap: 5px;">
                        <i class="fa-solid fa-chair" style="color: var(--text2);"></i> Seating Capacity
                    </div>
                    <div id="hub-capacity" style="font-size: 12px; font-weight: 600; color: var(--text);">50 worshippers</div>
                </div>
            </div>

            <!-- Location & Leadership Specs -->
            <div style="background: var(--bg2); border: 1px solid var(--border); border-radius: 8px; padding: 10px 14px; display: flex; flex-direction: column; gap: 6px;">
                <div style="display: flex; align-items: flex-start; gap: 8px; font-size: 11.5px;">
                    <i class="fa-solid fa-location-dot" style="color: var(--text2); margin-top: 3px; width: 14px; text-align: center;"></i>
                    <div>
                        <strong style="color: var(--text);">Location:</strong>
                        <span id="hub-location" style="color: var(--text2); margin-left: 4px;">Main Road, Parish Vicariate</span>
                    </div>
                </div>
                <div style="display: flex; align-items: flex-start; gap: 8px; font-size: 11.5px;">
                    <i class="fa-solid fa-user-tie" style="color: var(--text2); margin-top: 3px; width: 14px; text-align: center;"></i>
                    <div>
                        <strong style="color: var(--text);">Coordinator:</strong>
                        <span id="hub-coordinator" style="color: var(--text2); margin-left: 4px;">Parish Office</span>
                    </div>
                </div>
                <div style="display: flex; align-items: flex-start; gap: 8px; font-size: 11.5px;">
                    <i class="fa-solid fa-phone" style="color: var(--text2); margin-top: 3px; width: 14px; text-align: center;"></i>
                    <div>
                        <strong style="color: var(--text);">Contact:</strong>
                        <span id="hub-phone" style="color: var(--text2); margin-left: 4px;">(046) Parish Hotline</span>
                    </div>
                </div>
            </div>

            <!-- Upcoming Liturgies at this Chapel -->
            <div>
                <div style="font-weight: 700; font-size: 11.5px; color: var(--text); margin-bottom: 6px; display: flex; align-items: center; gap: 6px;">
                    <i class="fa-solid fa-calendar-check" style="color: var(--text2);"></i> Upcoming Liturgies at this Chapel
                </div>
                <div id="hub-upcoming-list" style="border: 1px solid var(--border); border-radius: 8px; background: var(--bg2); max-height: 120px; overflow-y: auto; padding: 4px;">
                    <!-- Dynamically populated -->
                </div>
            </div>
        </div>

        <!-- Footer Actions -->
        <div class="modal-footer" style="padding: 10px 20px; background: var(--bg2); border-top: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; gap: 8px;">
            <button type="button" id="hub-filter-cal-btn" class="btn btn-primary btn-sm" style="display: inline-flex; align-items: center; gap: 6px; font-size: 11.5px;">
                <i class="fa-solid fa-filter"></i> Filter Calendar Here
            </button>
            <div style="display: flex; gap: 6px;">
                <a href="dashboard.php?section=bulletin" id="hub-bulletin-link" class="btn btn-ghost btn-sm" style="font-size: 11px; border: 1px solid var(--border);">
                    <i class="fa-solid fa-bullhorn"></i> Post Bulletin
                </a>
                <button type="button" class="btn btn-gray btn-sm" onclick="closeModal('modal-chapel-hub')">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
function openChapelHubModal(chapel) {
    if (!chapel) return;
    const themeColor = chapel.theme_color || '#c9a84c';

    // Set accents
    const stripe = document.getElementById('hub-header-stripe');
    if (stripe) stripe.style.background = themeColor;

    const iconBox = document.getElementById('hub-icon-box');
    if (iconBox) {
        iconBox.style.color = themeColor;
        iconBox.style.borderColor = themeColor + '55';
        iconBox.style.background = themeColor + '22';
    }

    // Set text
    document.getElementById('hub-chapel-name').textContent = chapel.chapel_name || 'Chapel';
    const patronEl = document.getElementById('hub-patron-saint');
    if (patronEl) {
        patronEl.innerHTML = chapel.patron_saint 
            ? `<i class="fa-solid fa-cross"></i> Patron: ${chapel.patron_saint}` 
            : `<i class="fa-solid fa-church"></i> Vicariate Sub-Parish`;
    }

    const locEl = document.getElementById('hub-location');
    if (locEl) locEl.textContent = chapel.location || 'Parish Jurisdiction';

    const coordEl = document.getElementById('hub-coordinator');
    if (coordEl) coordEl.textContent = chapel.coordinator_name || 'Parish Office';

    const phoneEl = document.getElementById('hub-phone');
    if (phoneEl) phoneEl.textContent = chapel.coordinator_phone || 'Contact Parish Admin';

    const massEl = document.getElementById('hub-mass-time');
    if (massEl) massEl.textContent = chapel.sunday_service_time || 'Check Parish Schedule';

    const capEl = document.getElementById('hub-capacity');
    if (capEl) capEl.textContent = (chapel.seating_capacity || 50) + ' worshippers';

    // Fiesta countdown
    const fiestaCard = document.getElementById('hub-fiesta-card');
    let fiestas = [];
    try {
        if (chapel.fiestas_json) {
            fiestas = typeof chapel.fiestas_json === 'string' ? JSON.parse(chapel.fiestas_json) : chapel.fiestas_json;
        } else if (chapel.raw_fiestas) {
            chapel.raw_fiestas.split('===').forEach(p => {
                const parts = p.split('|||');
                if (parts.length === 2) fiestas.push({ name: parts[0], date: parts[1] });
            });
        }
    } catch(e) {}

    if (fiestas && fiestas.length > 0) {
        if (fiestaCard) fiestaCard.style.display = 'flex';
        const f = fiestas[0];
        document.getElementById('hub-fiesta-title').textContent = f.name || 'Patronal Feast';
        document.getElementById('hub-fiesta-date').textContent = 'Annual Feast: ' + (f.date || '');

        // Calculate days remaining
        let daysStr = '--';
        try {
            const now = new Date();
            const thisYear = now.getFullYear();
            let target = new Date(`${f.date} ${thisYear}`);
            if (isNaN(target.getTime())) {
                target = new Date(`${f.date}, ${thisYear}`);
            }
            if (!isNaN(target.getTime())) {
                if (target < now) {
                    target.setFullYear(thisYear + 1);
                }
                const diffTime = target.getTime() - now.getTime();
                const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
                daysStr = diffDays > 0 ? diffDays : 'Today!';
            }
        } catch(e) {}
        document.getElementById('hub-countdown-days').textContent = daysStr;
    } else {
        if (fiestaCard) fiestaCard.style.display = 'none';
    }

    // Upcoming liturgies at this chapel
    const upcomingList = document.getElementById('hub-upcoming-list');
    if (upcomingList) {
        const eventsList = (typeof priestEvents !== 'undefined' && Array.isArray(priestEvents)) ? priestEvents : [];
        const chEvents = eventsList.filter(ev => ev.chapel_id == chapel.chapel_id && ev.start_time);
        if (chEvents.length === 0) {
            upcomingList.innerHTML = '<div style="padding:10px;text-align:center;color:var(--text2);font-size:11px;">No scheduled liturgies upcoming for this chapel.</div>';
        } else {
            upcomingList.innerHTML = chEvents.slice(0, 3).map(ev => {
                const d = new Date(ev.start_time.replace(' ', 'T'));
                const dateStr = isNaN(d.getTime()) ? ev.start_time : d.toLocaleDateString([], {month:'short', day:'numeric'}) + ' · ' + d.toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'});
                return `
                    <div style="display:flex;align-items:center;justify-content:space-between;padding:5px 8px;border-bottom:1px solid rgba(255,255,255,0.05);font-size:11px;">
                        <span style="font-weight:700;color:var(--text);">${escHtmlSafe(ev.title)}</span>
                        <span style="color:var(--text2);font-size:10px;">${dateStr}</span>
                    </div>
                `;
            }).join('');
        }
    }

    // Set filter calendar button
    const filterBtn = document.getElementById('hub-filter-cal-btn');
    if (filterBtn) {
        filterBtn.onclick = function() {
            if (typeof selectChapelFilter === 'function') {
                selectChapelFilter(chapel.chapel_id);
            }
            closeModal('modal-chapel-hub');
        };
    }

    // Bulletin link
    const bulletinLink = document.getElementById('hub-bulletin-link');
    if (bulletinLink) {
        bulletinLink.href = 'dashboard.php?section=bulletin&bulletin_chapel=' + encodeURIComponent(chapel.chapel_id);
    }

    openModal('modal-chapel-hub');
}
</script>

<!-- MODAL: Add Event -->
<div class="modal-overlay" id="modal-add-event">
    <div class="modal modal-lg" style="max-width: 760px; width: 94%; border-radius: 12px; background: var(--surface); border: 1px solid var(--border); box-shadow: 0 16px 40px rgba(0,0,0,0.22); overflow: hidden; display: flex; flex-direction: column;">
        <div class="modal-header" style="padding: 14px 22px; background: var(--surface); border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center;">
            <div class="modal-title" style="display: flex; align-items: center; gap: 10px; font-family: var(--font-family, 'Inter', sans-serif); font-size: 1.05rem; font-weight: 700; color: var(--text);">
                <i class="fa-solid fa-calendar-plus" style="color: var(--accent);"></i>
                <span>Create Event</span>
            </div>
            <button class="modal-close" onclick="closeModal('modal-add-event')" style="font-size: 20px; cursor: pointer; border: none; background: transparent; color: var(--text2);">×</button>
        </div>
        <form id="form-add-event" method="POST">
            <input type="hidden" name="form_section" value="events">
            <input type="hidden" name="action" value="create_event">
            <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
            <input type="hidden" name="event_scope" value="<?php echo in_array($role, ['Admin', 'Cathedral Admin']) ? 'cathedral' : 'chapel'; ?>">
            <div class="modal-body" style="padding: 18px 22px; max-height: calc(85vh - 110px); overflow-y: auto;">
                <div style="display: flex; flex-direction: column; gap: 13px;">
                    
                    <!-- Row 1: Event Type, Event Title, Location -->
                    <div style="display: grid; grid-template-columns: 1fr 1.2fr 1fr; gap: 12px;">
                        <div class="form-group" style="margin: 0;">
                            <label style="font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text2); margin-bottom: 5px; display: block;">
                                Event Type <span style="color: #ef4444;">*</span>
                            </label>
                            <select name="event_type" id="field-event-type-modal" required onchange="onFormChange('modal')"
                                    style="height: 34px !important; font-size: 12.5px; border-radius: 6px; border: 1px solid var(--border); background: var(--bg2, #FAFAFA); width: 100%;">
                                <?php echo render_event_type_options('', 'Select Event / Rite', null, 'event_schedule'); ?>
                            </select>
                        </div>
                        
                        <div class="form-group" style="margin: 0;">
                            <label style="font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text2); margin-bottom: 5px; display: block;">
                                Event Title <span style="color: #ef4444;">*</span>
                            </label>
                            <input name="title" required placeholder="e.g. Wedding Mass"
                                   style="height: 34px; padding: 0 10px; font-size: 12.5px; border-radius: 6px; border: 1px solid var(--border); background: var(--bg2, #FAFAFA); width: 100%; box-sizing: border-box;">
                        </div>
                        
                        <div class="form-group" style="margin: 0;">
                            <label style="font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text2); margin-bottom: 5px; display: block;">
                                Location <span style="color: #ef4444;">*</span>
                            </label>
                            <?php if (in_array($role, ['Admin', 'Cathedral Admin'])): ?>
                            <select name="chapel_id" id="field-venue-modal" required onchange="onFormChange('modal')"
                                    style="height: 34px !important; font-size: 12.5px; border-radius: 6px; border: 1px solid var(--border); background: var(--bg2, #FAFAFA); width: 100%;">
                                <option value="">Select Location</option>
                                <option value="0">IFI Cathedral (Main)</option>
                                <?php foreach ($chapels as $ch): ?>
                                <option value="<?php echo $ch['chapel_id']; ?>"><?php echo htmlspecialchars($ch['chapel_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php else: ?>
                            <select name="chapel_id" id="field-venue-modal" required onchange="onFormChange('modal')" disabled 
                                    style="height: 34px !important; font-size: 12.5px; border-radius: 6px; border: 1px solid var(--border); background: var(--bg2, #FAFAFA); width: 100%; opacity: 0.85; cursor: not-allowed;">
                                <?php if ($chapelId == 0 && $chapelId !== null): ?>
                                    <option value="0" selected>IFI Cathedral (Main)</option>
                                <?php else: ?>
                                    <?php foreach ($chapels as $ch): ?>
                                        <?php if ((int)$chapelId === (int)$ch['chapel_id']): ?>
                                            <option value="<?php echo $ch['chapel_id']; ?>" selected><?php echo htmlspecialchars($ch['chapel_name']); ?></option>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                            <input type="hidden" name="chapel_id" value="<?php echo $chapelId; ?>">
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Row 2: Event Date & Status side-by-side -->
                    <?php
                    $defEvDate = ((int)date('H') >= 17) ? date('Y-m-d', strtotime('+1 day')) : date('Y-m-d');
                    $defEvSlot = ((int)date('H') >= 12 && (int)date('H') < 17) ? '14:00' : '09:00';
                    $defIsMorning = ($defEvSlot === '09:00');
                    ?>
                    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 12px;">
                        <div class="form-group" style="margin: 0;">
                            <label style="font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text2); margin-bottom: 5px; display: block;">
                                <i class="fa-regular fa-calendar" style="color: var(--accent); margin-right: 3px;"></i> Event Date <span style="color: #ef4444;">*</span>
                            </label>
                            <input type="date" id="field-date-modal" min="<?php echo date('Y-m-d'); ?>" value="<?php echo $defEvDate; ?>" required onchange="syncModalDateTimeAndSlots('modal')" 
                                   style="width: 100%; height: 34px; padding: 0 10px; font-size: 12.5px; border-radius: 6px; border: 1px solid var(--border); background: var(--bg2, #FAFAFA); color: var(--text); box-sizing: border-box;">
                        </div>

                        <div class="form-group" style="margin: 0;">
                            <label style="font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text2); margin-bottom: 5px; display: block;">
                                Initial Step / Status
                            </label>
                            <select name="status" style="height: 34px !important; font-size: 12px; border-radius: 6px; border: 1px solid var(--border); background: var(--bg2, #FAFAFA); width: 100%;">
                                <option value="Confirmed">Confirmed (Schedule Now)</option>
                                <option value="Pending">Pending (Queue for Review)</option>
                                <option value="Completed">Completed (Already Done)</option>
                            </select>
                        </div>
                    </div>

                    <!-- Row 3: Canonical Time Slots -->
                    <div style="margin: 0;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                            <label style="margin: 0; font-weight: 700; font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: var(--accent); display: flex; align-items: center; gap: 5px;">
                                <i class="fa-regular fa-clock"></i> Time Slot
                            </label>
                            <span id="active-slot-pill-modal" class="badge" style="background: rgba(201,168,76,0.12); color: var(--accent); border: 1px solid rgba(201,168,76,0.25); font-size: 10.5px; padding: 2px 8px; font-weight: 700; border-radius: 999px;"><?php echo $defIsMorning ? 'Morning Slot (09:00 AM)' : 'Afternoon Slot (02:00 PM)'; ?></span>
                        </div>
                        
                        <div class="slot-picker-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                            <!-- Morning Slot Card -->
                            <div class="slot-card morning <?php echo $defIsMorning ? 'selected' : ''; ?>" id="slot-card-morning-modal" onclick="selectModalSlot('modal', 'morning', '09:00')" 
                                 style="cursor: pointer; background: var(--bg2); border: 1.5px solid <?php echo $defIsMorning ? 'var(--accent)' : 'var(--border)'; ?>; border-radius: 8px; padding: 10px 12px; display: flex; flex-direction: column; gap: 6px; transition: all 0.15s ease;">
                                <div style="display: flex; align-items: center; justify-content: space-between;">
                                    <div style="font-size: 12.5px; font-weight: 700; color: var(--text); display: flex; align-items: center; gap: 6px;">
                                        <i class="fa-solid fa-sun" style="color: var(--accent); font-size: 12px;"></i> Morning
                                    </div>
                                    <span id="slot-badge-morning-modal" style="font-size: 10px; font-weight: 700; color: #2E9E5B; background: rgba(46,158,91,0.12); border-radius: 999px; padding: 1px 7px;">
                                        <i class="fa-solid fa-circle-check" style="font-size: 8px;"></i> Available
                                    </span>
                                </div>
                                <div style="font-size: 11px; color: var(--text2); display: flex; align-items: center; gap: 5px;">
                                    <i class="fa-regular fa-clock" style="font-size: 10px;"></i> 09:00 AM – 12:00 PM
                                </div>
                                <div id="slot-chip-morning-modal" style="margin-top: 2px; height: 26px; border-radius: 6px; font-size: 11px; font-weight: 600; display: flex; align-items: center; justify-content: center; gap: 5px; <?php echo $defIsMorning ? 'background: var(--text); color: var(--bg); border: 1px solid var(--text);' : 'background: var(--bg3); color: var(--text2); border: 1px solid var(--border);'; ?>">
                                    <?php if ($defIsMorning): ?>
                                    <i class="fa-solid fa-circle-check" style="font-size: 9px;"></i> <span>Selected</span>
                                    <?php else: ?>
                                    <i class="fa-regular fa-hand-pointer" style="font-size: 9px;"></i> <span>Select</span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Afternoon Slot Card -->
                            <div class="slot-card afternoon <?php echo !$defIsMorning ? 'selected' : ''; ?>" id="slot-card-afternoon-modal" onclick="selectModalSlot('modal', 'afternoon', '14:00')" 
                                 style="cursor: pointer; background: var(--bg2); border: 1.5px solid <?php echo !$defIsMorning ? 'var(--accent)' : 'var(--border)'; ?>; border-radius: 8px; padding: 10px 12px; display: flex; flex-direction: column; gap: 6px; transition: all 0.15s ease;">
                                <div style="display: flex; align-items: center; justify-content: space-between;">
                                    <div style="font-size: 12.5px; font-weight: 700; color: var(--text); display: flex; align-items: center; gap: 6px;">
                                        <i class="fa-solid fa-cloud-sun" style="color: var(--accent); font-size: 12px;"></i> Afternoon
                                    </div>
                                    <span id="slot-badge-afternoon-modal" style="font-size: 10px; font-weight: 700; color: #2E9E5B; background: rgba(46,158,91,0.12); border-radius: 999px; padding: 1px 7px;">
                                        <i class="fa-solid fa-circle-check" style="font-size: 8px;"></i> Available
                                    </span>
                                </div>
                                <div style="font-size: 11px; color: var(--text2); display: flex; align-items: center; gap: 5px;">
                                    <i class="fa-regular fa-clock" style="font-size: 10px;"></i> 02:00 PM – 05:00 PM
                                </div>
                                <div id="slot-chip-afternoon-modal" style="margin-top: 2px; height: 26px; border-radius: 6px; font-size: 11px; font-weight: 600; display: flex; align-items: center; justify-content: center; gap: 5px; <?php echo !$defIsMorning ? 'background: var(--text); color: var(--bg); border: 1px solid var(--text);' : 'background: var(--bg3); color: var(--text2); border: 1px solid var(--border);'; ?>">
                                    <?php if (!$defIsMorning): ?>
                                    <i class="fa-solid fa-circle-check" style="font-size: 9px;"></i> <span>Selected</span>
                                    <?php else: ?>
                                    <i class="fa-regular fa-hand-pointer" style="font-size: 9px;"></i> <span>Select</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Hidden inputs for backend submission -->
                        <input type="hidden" name="start_time" id="field-datetime-modal" value="<?php echo $defEvDate . 'T' . $defEvSlot; ?>" required>
                        <input type="hidden" name="custom_duration" id="field-custom-duration-modal" value="">
                        
                        <div id="venue-warning-modal" style="display:none; margin-top:8px; padding:8px 12px; background:rgba(239,68,68,0.08); border:1px solid rgba(239,68,68,0.25); border-radius:6px; color:#b91c1c; font-size:12px;">
                            <i class="fa-solid fa-triangle-exclamation"></i>
                            <span id="venue-warning-text-modal"></span>
                        </div>
                    </div>

                    <!-- Row 4: Assign Priest -->
                    <div style="margin: 0;">
                        <label style="font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text2); margin-bottom: 5px; display: block;">
                            Assign Priest <small style="color: var(--text2); font-weight: normal; text-transform: none;">(Optional)</small>
                        </label>
                        <input type="hidden" name="pastor_id" id="selected-pastor-input-modal">
                        <div id="pastor-picker-grid-modal" class="pastor-picker-grid" style="margin: 0; min-height: 0;"></div>
                        <div id="pastor-picker-hint-modal" style="font-size: 11px; color: var(--text2); margin-top: 4px; display: flex; align-items: center; gap: 4px;">
                            <i class="fa-solid fa-circle-info" style="color: var(--accent);"></i> Select a type and time to see available priests.
                        </div>
                    </div>
                    
                    <!-- Row 5: Optional Booking Source & Requester Contacts -->
                    <div class="form-group" style="margin: 0;">
                        <label style="font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text2); margin-bottom: 5px; display: block;">
                            Booking Source <span style="color: var(--text2); font-weight: 400; text-transform: none;">(optional)</span>
                        </label>
                        <select name="requester_source" style="height: 34px; padding: 0 10px; font-size: 12.5px; border-radius: 6px; border: 1px solid var(--border); background: var(--bg2, #FAFAFA); width: 100%; box-sizing: border-box;">
                            <option value="office">Parish Office (Encoded by Me)</option>
                            <option value="walkin">Walk-in Parishioner</option>
                        </select>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                        <div class="form-group" style="margin: 0;">
                            <label style="font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text2); margin-bottom: 5px; display: block;">
                                Requester Email <span style="color: var(--text2); font-weight: 400; text-transform: none;">(optional)</span>
                            </label>
                            <input type="email" name="requester_email" placeholder="requester@email.com"
                                   style="height: 34px; padding: 0 10px; font-size: 12.5px; border-radius: 6px; border: 1px solid var(--border); background: var(--bg2, #FAFAFA); width: 100%; box-sizing: border-box;">
                        </div>
                        <div class="form-group" style="margin: 0;">
                            <label style="font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text2); margin-bottom: 5px; display: block;">
                                Requester Phone <span style="color: var(--text2); font-weight: 400; text-transform: none;">(SMS notification)</span>
                            </label>
                            <input type="tel" name="requester_phone" placeholder="0912-345-6789" maxlength="15" pattern="[0-9\+\s\-]{10,20}" title="Enter valid mobile number"
                                   style="height: 34px; padding: 0 10px; font-size: 12.5px; border-radius: 6px; border: 1px solid var(--border); background: var(--bg2, #FAFAFA); width: 100%; box-sizing: border-box;">
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer" style="padding: 12px 22px; background: var(--surface); border-top: 1px solid var(--border); display: flex; justify-content: flex-end; gap: 10px;">
                <button type="button" class="btn btn-gray" onclick="closeModal('modal-add-event')" style="height: 34px; min-height: 34px; font-size: 12px; padding: 0 16px; border-radius: 6px; cursor: pointer;">Cancel</button>
                <button type="submit" class="btn btn-primary" style="height: 34px; min-height: 34px; font-size: 12px; padding: 0 20px; font-weight: 600; border-radius: 6px; background: #111111; color: #FFFFFF; border: 1px solid #111111; cursor: pointer;">Save Event</button>
            </div>
        </form>
    </div>
</div><!-- MODAL: Edit Event (populated via openEditEventModal() JS) -->
<div class="modal-overlay" id="modal-edit-event">
    <div class="modal modal-lg" style="max-width: 720px; width: 94%; border-radius: 12px; background: var(--bg2); border: 1px solid var(--border); box-shadow: none; overflow: hidden; display: flex; flex-direction: column;">
        <div class="modal-header" style="padding: 14px 22px; background: var(--bg2); border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center;">
            <div class="modal-title" style="display: flex; align-items: center; gap: 10px; font-family: var(--font-family, 'Inter', sans-serif); font-size: 15px; font-weight: 700; color: var(--text);">
                <i class="fa-solid fa-pen-to-square" style="color: var(--accent);"></i>
                <span>Edit Event</span>
                <span id="edit-event-ref-badge" class="badge" style="font-family: var(--font-mono, monospace); background: var(--bg3); color: var(--text); border: 1px solid var(--border); font-size: 11px; padding: 2px 8px; font-weight: 700; border-radius: 6px;">#0000</span>
            </div>
            <button class="modal-close" onclick="closeModal('modal-edit-event')" title="Close">&times;</button>
        </div>
        <form id="form-edit-event" method="POST" style="margin: 0; display: flex; flex-direction: column; min-height: 0;">
            <input type="hidden" name="form_section" value="events">
            <input type="hidden" name="action" value="update_event">
            <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
            <input type="hidden" name="id" id="edit-event-id">
            <input type="hidden" name="event_scope" id="edit-event-scope">
            
            <div class="modal-body" style="padding: 18px 22px; overflow-y: auto; max-height: calc(86vh - 125px); display: flex; flex-direction: column; gap: 16px; scrollbar-width: thin;">
                
                <!-- Hero Sacrament Context Card -->
                <div style="background: var(--bg3); border: 1px solid var(--border); border-radius: 8px; padding: 14px 16px; display: flex; align-items: center; justify-content: space-between; gap: 14px;">
                    <div style="display: flex; align-items: center; gap: 14px; min-width: 0;">
                        <div id="edit-sacrament-icon-box" style="width: 44px; height: 44px; border-radius: 8px; background: var(--bg2); border: 1px solid var(--border); color: var(--text); display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0; box-shadow: none;">
                            <i class="fa-solid fa-church" id="edit-sacrament-icon"></i>
                        </div>
                        <div style="min-width: 0;">
                            <div style="font-size: 10px; text-transform: uppercase; letter-spacing: 0.08em; color: var(--text2); font-weight: 700;">Modifying Schedule Record</div>
                            <div id="edit-hero-title-preview" style="font-family: var(--font-family, 'Inter', sans-serif); font-size: 15px; font-weight: 700; color: var(--text); line-height: 1.3; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">Event Details</div>
                        </div>
                    </div>
                    <span id="edit-hero-status-pill" class="badge" style="font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; padding: 3px 10px; border-radius: 6px; flex-shrink: 0;">Pending</span>
                </div>

                <!-- Section 1: Event Identity & Sanctuary Venue -->
                <div style="background: var(--bg2); border: 1px solid var(--border); border-radius: 8px; padding: 16px 18px; display: flex; flex-direction: column; gap: 14px;">
                    <div style="font-size: 11.5px; text-transform: uppercase; letter-spacing: 0.08em; color: var(--text); font-weight: 700; display: flex; align-items: center; gap: 6px; padding-bottom: 8px; border-bottom: 1px solid var(--border);">
                        <i class="fa-solid fa-file-pen" style="color: var(--accent);"></i> Event Title &amp; Sanctuary
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px;" class="edit-event-tri-grid">
                        <div class="form-group" style="margin: 0;">
                            <label style="font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text2); margin-bottom: 6px; display: block;">
                                Sacrament / Type
                            </label>
                            <select name="event_type" id="field-event-type-edit-modal" onchange="onFormChange('edit-modal'); updateEditSacramentType(this.value);"
                                    style="width: 100%; padding: 9px 12px; font-size: 13px; border-radius: 8px; background: var(--bg); border: 1px solid var(--border); color: var(--text); outline: none;">
                                <?php echo render_event_type_options('', 'Select Event / Rite', null, 'event_schedule'); ?>
                            </select>
                        </div>

                        <div class="form-group" style="margin: 0;">
                            <label style="font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text2); margin-bottom: 6px; display: block;">
                                Sanctuary Location
                            </label>
                            <?php if (in_array($role, ['Admin', 'Cathedral Admin'])): ?>
                            <select name="chapel_id" id="field-venue-edit-modal" onchange="onFormChange('edit-modal')"
                                    style="width: 100%; padding: 9px 12px; font-size: 13px; border-radius: 8px; background: var(--bg); border: 1px solid var(--border); color: var(--text); outline: none;">
                                <option value="">Select Location</option>
                                <option value="0">Main Cathedral</option>
                                <?php foreach ($chapels as $ch): ?>
                                <option value="<?php echo $ch['chapel_id']; ?>"><?php echo htmlspecialchars($ch['chapel_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php else: ?>
                            <select name="chapel_id" id="field-venue-edit-modal" onchange="onFormChange('edit-modal')" disabled 
                                    style="width: 100%; padding: 9px 12px; font-size: 13px; border-radius: 8px; background: var(--bg); border: 1px solid var(--border); color: var(--text); opacity: 0.75; cursor: not-allowed;">
                                <?php if ($chapelId == 0 && $chapelId !== null): ?>
                                    <option value="0" selected>Main Cathedral</option>
                                <?php else: ?>
                                    <?php foreach ($chapels as $ch): ?>
                                        <?php if ((int)$chapelId === (int)$ch['chapel_id']): ?>
                                            <option value="<?php echo $ch['chapel_id']; ?>" selected><?php echo htmlspecialchars($ch['chapel_name']); ?></option>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                            <input type="hidden" name="chapel_id" value="<?php echo $chapelId; ?>">
                            <?php endif; ?>
                        </div>

                        <div class="form-group" style="margin: 0;">
                            <label style="font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text2); margin-bottom: 6px; display: block;">
                                Canonical Status
                            </label>
                            <select name="status" id="edit-event-status" onchange="updateEditHeroStatus(this.value)"
                                    style="width: 100%; padding: 9px 12px; font-size: 13px; border-radius: 8px; background: var(--bg); border: 1px solid var(--border); color: var(--text); outline: none;">
                                <option value="Pending">Pending</option>
                                <option value="Confirmed">Confirmed</option>
                                <option value="Completed">Completed</option>
                                <option value="Cancelled">Cancelled</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group" style="margin: 0;">
                        <label style="font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text2); margin-bottom: 6px; display: block;">
                            Event Title <span style="color: #ef4444;">*</span>
                        </label>
                        <input name="title" id="edit-event-title" required placeholder="e.g. Baptism of Luke Fernandez" 
                               style="width: 100%; padding: 10px 14px; font-size: 13.5px; border-radius: 8px; background: var(--bg); border: 1px solid var(--border); color: var(--text); outline: none; transition: border-color 0.2s;"
                               oninput="updateEditHeroTitle(this.value)">
                    </div>
                </div>

                <!-- Section 2: Liturgical Date, Time & Canonical Slot Selection -->
                <div style="background: var(--panel-bg, var(--bg3)); border: 1px solid var(--border); border-radius: 12px; padding: 16px 18px; display: flex; flex-direction: column; gap: 14px;">
                    <div style="font-size: 11.5px; text-transform: uppercase; letter-spacing: 0.08em; color: var(--accent); font-weight: 700; display: flex; align-items: center; justify-content: space-between; padding-bottom: 8px; border-bottom: 1px solid var(--border);">
                        <span><i class="fa-solid fa-calendar-days"></i> Schedule &amp; Canonical Time Slots</span>
                        <span id="active-slot-pill-edit-modal" style="font-size: 10.5px; font-weight: 700; color: var(--accent); background: rgba(201,168,76,0.12); padding: 2px 8px; border-radius: 4px; border: 1px solid rgba(201,168,76,0.3);">Morning Slot (09:00 AM)</span>
                    </div>

                    <input type="hidden" name="location_type" id="field-location-type-edit-modal" value="Church">
                    <div class="form-group" style="margin: 0;">
                        <label style="font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text2); margin-bottom: 6px; display: block;">
                            Event Date <span style="color: #ef4444;">*</span>
                        </label>
                        <input type="date" id="field-date-edit-modal" max="2099-12-31" required onchange="syncModalDateTimeAndSlots('edit-modal')"
                               style="width: 100%; padding: 9px 12px; font-size: 13px; border-radius: 8px; background: var(--bg); border: 1px solid var(--border); color: var(--text); outline: none;">
                    </div>

                    <div class="slot-picker-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin: 0;">
                        <!-- Morning Slot Card -->
                        <div class="slot-card morning selected" id="slot-card-morning-edit-modal" onclick="selectModalSlot('edit-modal', 'morning', '09:00')" style="cursor: pointer;">
                            <div class="slot-card-header">
                                <div class="slot-title">
                                    <i class="fa-solid fa-sun" style="color:var(--accent,#c5a059);font-size:13px;"></i> Morning Slot
                                </div>
                                <span class="slot-badge available" id="slot-badge-morning-edit-modal">
                                    <i class="fa-solid fa-circle-check" style="font-size:7px;"></i> Available
                                </span>
                            </div>
                            <div class="slot-time-range"><i class="fa-regular fa-clock" style="font-size:9px;"></i> 09:00 AM – 12:00 PM</div>
                            <div class="slot-open-chip" id="slot-chip-morning-edit-modal" style="background:rgba(212,175,55,0.12);border-color:rgba(212,175,55,0.3);color:var(--accent,#d4af37);">
                                <i class="fa-solid fa-circle-dot" style="font-size:9px;"></i> <span>Active Slot Selection</span>
                            </div>
                        </div>

                        <!-- Afternoon Slot Card -->
                        <div class="slot-card afternoon" id="slot-card-afternoon-edit-modal" onclick="selectModalSlot('edit-modal', 'afternoon', '14:00')" style="cursor: pointer;">
                            <div class="slot-card-header">
                                <div class="slot-title">
                                    <i class="fa-solid fa-cloud-sun" style="color:var(--accent,#c5a059);font-size:13px;"></i> Afternoon Slot
                                </div>
                                <span class="slot-badge available" id="slot-badge-afternoon-edit-modal">
                                    <i class="fa-solid fa-circle-check" style="font-size:7px;"></i> Available
                                </span>
                            </div>
                            <div class="slot-time-range"><i class="fa-regular fa-clock" style="font-size:9px;"></i> 02:00 PM – 05:00 PM</div>
                            <div class="slot-open-chip" id="slot-chip-afternoon-edit-modal">
                                <i class="fa-regular fa-hand-pointer" style="font-size:9px;"></i> <span>Click to Select</span>
                            </div>
                        </div>
                    </div>

                    <!-- Hidden inputs for backend form processing -->
                    <input type="hidden" name="start_time" id="field-datetime-edit-modal" required>
                    <input type="hidden" name="custom_duration" id="field-custom-duration-edit-modal" value="">
                </div>

                <!-- Section 3: Presiding Priest Selection -->
                <div style="background: var(--panel-bg, var(--bg3)); border: 1px solid var(--border); border-radius: 12px; padding: 16px 18px; display: flex; flex-direction: column; gap: 12px;">
                    <div style="font-size: 11.5px; text-transform: uppercase; letter-spacing: 0.08em; color: var(--accent); font-weight: 700; display: flex; align-items: center; justify-content: space-between; padding-bottom: 8px; border-bottom: 1px solid var(--border);">
                        <span><i class="fa-solid fa-user-tie"></i> Presiding Priest Assignment</span>
                        <small style="color: var(--text2); font-size: 10.5px; font-weight: 400; text-transform: none;">Optional</small>
                    </div>
                    <input type="hidden" name="pastor_id" id="selected-pastor-input-edit-modal">
                    <div id="pastor-picker-grid-edit-modal" class="pastor-picker-grid" style="margin: 0; max-height: 180px; overflow-y: auto;">
                        <!-- Dynamically populated via dashboard-availability.js -->
                    </div>
                    <div id="pastor-none-edit-modal" class="p-16 text-center text-sm text-muted" style="display:none; background:var(--bg); border:1px dashed var(--border); border-radius:10px; color: var(--text2); font-size: 12px; padding: 12px;">
                        No priests available for this sacrament and schedule.
                    </div>
                </div>

                <!-- Section 4: Parishioner Contact Channels -->
                <div style="background: var(--bg2); border: 1px solid var(--border); border-radius: 8px; padding: 16px 18px; display: flex; flex-direction: column; gap: 12px;">
                    <div style="font-size: 11.5px; text-transform: uppercase; letter-spacing: 0.08em; color: var(--text); font-weight: 700; display: flex; align-items: center; gap: 6px; padding-bottom: 8px; border-bottom: 1px solid var(--border);">
                        <i class="fa-solid fa-address-book" style="color: var(--accent);"></i> Parishioner Contact &amp; Notifications
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;" class="edit-event-contact-grid">
                        <div class="form-group" style="margin: 0;">
                            <label style="font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text2); margin-bottom: 6px; display: block;">
                                Requester Email <small style="color: var(--text2); text-transform: none; font-weight: 400;">(email alerts)</small>
                            </label>
                            <input type="email" name="requester_email" id="edit-event-email" placeholder="e.g. parishioner@email.com"
                                   style="width: 100%; padding: 9px 12px; font-size: 13px; border-radius: 8px; background: var(--bg); border: 1px solid var(--border); color: var(--text); outline: none;">
                        </div>

                        <div class="form-group" style="margin: 0;">
                            <label style="font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text2); margin-bottom: 6px; display: block;">
                                Requester Phone <small style="color: var(--text2); text-transform: none; font-weight: 400;">(SMS alerts)</small>
                            </label>
                            <input type="tel" name="requester_phone" id="edit-event-phone" placeholder="0912-345-6789" maxlength="15" pattern="[0-9\+\s\-]{10,20}" title="Enter valid mobile number (e.g. 0912-345-6789 or 09171234567)"
                                   style="width: 100%; padding: 9px 12px; font-size: 13px; border-radius: 8px; background: var(--bg); border: 1px solid var(--border); color: var(--text); outline: none;">
                        </div>
                    </div>
                </div>

            </div>

            <!-- Action Toolbar Footer -->
            <div class="modal-footer" style="padding: 14px 22px; background: var(--bg2); border-top: 1px solid var(--border); display: flex; justify-content: flex-end; align-items: center; gap: 10px;">
                <button type="button" class="btn btn-gray btn-sm" onclick="closeModal('modal-edit-event')" style="height: 36px; padding: 0 16px; font-weight: 500;">
                    Cancel
                </button>
                <button type="submit" class="btn btn-primary btn-sm" style="height: 36px; padding: 0 20px; font-weight: 600; display: inline-flex; align-items: center; gap: 8px; box-shadow: none;">
                    <i class="fa-solid fa-floppy-disk"></i> Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL: View Event Details (Redesigned Liturgical Dossier) -->
<div class="modal-overlay" id="modal-view-event-details">
    <div class="modal modal-lg" style="max-width: 680px; width: 92%; border-radius: 12px; background: var(--bg2); border: 1px solid var(--border); box-shadow: none; overflow: hidden; display: flex; flex-direction: column;">
        <div class="modal-header" style="padding: 14px 20px; background: var(--bg2); border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center;">
            <div class="modal-title" style="display: flex; align-items: center; gap: 10px; font-family: var(--font-family, 'Inter', sans-serif); font-size: 15px; font-weight: 700; color: var(--text);">
                <i class="fa-solid fa-calendar-check" style="color: var(--accent);"></i>
                <span>Event Details</span>
                <span id="ve-ref-badge" class="badge" style="font-family: var(--font-mono, monospace); background: var(--bg3); color: var(--text); border: 1px solid var(--border); font-size: 11px; padding: 2px 8px; font-weight: 700; border-radius: 6px;">#0000</span>
            </div>
            <button class="modal-close" onclick="closeModal('modal-view-event-details')" title="Close">&times;</button>
        </div>

        <div class="modal-body" style="padding: 18px 20px; overflow-y: auto; max-height: calc(85vh - 120px); display: flex; flex-direction: column; gap: 14px;">
            <!-- Hero Event Banner -->
            <div style="background: var(--bg3); border: 1px solid var(--border); border-radius: 8px; padding: 16px 18px; display: flex; align-items: flex-start; gap: 16px; position: relative;">
                <div id="ve-type-icon-box" style="width: 48px; height: 48px; border-radius: 8px; background: var(--bg2); border: 1px solid var(--border); color: var(--text); display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0; box-shadow: none;">
                    <i class="fa-solid fa-church" id="ve-type-icon"></i>
                </div>
                <div style="flex: 1; min-width: 0;">
                    <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 6px; margin-bottom: 6px;">
                        <span id="ve-type-pill" class="badge" style="background: var(--bg2); border: 1px solid var(--border); font-size: 10px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; padding: 2px 8px; border-radius: 6px; color: var(--text);">Event</span>
                        <span id="ve-status-pill" class="badge" style="font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; padding: 2px 8px; border-radius: 6px;">Pending</span>
                        <span id="ve-scope-pill" class="badge" style="background: var(--bg2); border: 1px solid var(--border); color: var(--text2); font-size: 10px; padding: 2px 8px; border-radius: 6px;">Cathedral</span>
                        <span id="ve-duration-pill" class="badge" style="background: var(--bg2); border: 1px solid var(--border); color: var(--text2); font-size: 10px; padding: 2px 8px; border-radius: 6px;"><i class="fa-regular fa-clock" style="margin-right: 4px;"></i><span id="ve-duration-text">1 hr</span></span>
                    </div>
                    <h2 id="ve-title" style="font-family: var(--font-family, 'Inter', sans-serif); font-size: 1.15rem; font-weight: 700; color: var(--text); margin: 0; line-height: 1.35; overflow-wrap: break-word;">Event Title</h2>
                </div>
            </div>

            <!-- Two-Column Information Dossier -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;" class="ve-dossier-grid">
                <!-- Left: Liturgical Schedule & Sanctuary Venue -->
                <div style="background: var(--bg2); border: 1px solid var(--border); border-radius: 8px; padding: 14px 16px; display: flex; flex-direction: column; gap: 12px;">
                    <div style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.08em; color: var(--text); font-weight: 700; display: flex; align-items: center; gap: 6px; padding-bottom: 6px; border-bottom: 1px solid var(--border);">
                        <i class="fa-solid fa-calendar-day" style="color: var(--accent);"></i> Schedule &amp; Sanctuary
                    </div>

                    <div>
                        <div style="font-size: 10.5px; color: var(--text2); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 2px;">Date &amp; Time</div>
                        <div id="ve-datetime" style="font-weight: 700; font-size: 13.5px; color: var(--text); line-height: 1.3;">—</div>
                        <div id="ve-countdown-wrapper" style="margin-top: 4px;">
                            <span id="ve-countdown-badge" style="display: inline-flex; align-items: center; gap: 4px; font-size: 11px; font-weight: 600; padding: 2px 8px; border-radius: 6px; background: var(--bg3); color: var(--text); border: 1px solid var(--border);">
                                <i class="fa-solid fa-stopwatch" style="color: var(--accent);"></i> <span id="ve-countdown-text">Upcoming</span>
                            </span>
                        </div>
                    </div>

                    <div>
                        <div style="font-size: 10.5px; color: var(--text2); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 2px;">Sanctuary Location</div>
                        <div style="display: flex; align-items: center; gap: 6px;">
                            <i class="fa-solid fa-location-dot" style="color: var(--text2); font-size: 12px;"></i>
                            <span id="ve-location" style="font-weight: 600; font-size: 13px; color: var(--text);">Main Cathedral</span>
                        </div>
                    </div>

                    <div>
                        <div style="font-size: 10.5px; color: var(--text2); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 2px;">Presiding Priest</div>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <div style="width: 26px; height: 26px; border-radius: 50%; background: var(--bg3); border: 1px solid var(--border); color: var(--text); display: flex; align-items: center; justify-content: center; font-size: 11px;">
                                <i class="fa-solid fa-user-tie"></i>
                            </div>
                            <div>
                                <span id="ve-pastor" style="font-weight: 600; font-size: 13px; color: var(--text);">Unassigned</span>
                                <div id="ve-pastor-status" style="font-size: 10.5px; color: var(--text2);">Pending Assignment</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right: Parishioner & Request Attribution -->
                <div style="background: var(--bg2); border: 1px solid var(--border); border-radius: 8px; padding: 14px 16px; display: flex; flex-direction: column; gap: 12px;">
                    <div style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.08em; color: var(--text); font-weight: 700; display: flex; align-items: center; gap: 6px; padding-bottom: 6px; border-bottom: 1px solid var(--border);">
                        <i class="fa-solid fa-address-card" style="color: var(--accent);"></i> Parishioner &amp; Contact
                    </div>

                    <div>
                        <div style="font-size: 10.5px; color: var(--text2); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 2px;">Requester Name</div>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <div style="width: 26px; height: 26px; border-radius: 50%; background: var(--bg3); border: 1px solid var(--border); color: var(--text); display: flex; align-items: center; justify-content: center; font-size: 11px;">
                                <i class="fa-solid fa-user"></i>
                            </div>
                            <span id="ve-requester-name" style="font-weight: 700; font-size: 13px; color: var(--text);">Parish Office</span>
                        </div>
                    </div>

                    <div>
                        <div style="font-size: 10.5px; color: var(--text2); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 4px;">Contact Channels</div>
                        <div style="display: flex; flex-direction: column; gap: 5px;">
                            <a id="ve-email-link" href="#" style="display: flex; align-items: center; gap: 6px; font-size: 12px; color: var(--text); text-decoration: none; padding: 4px 8px; background: var(--bg); border: 1px solid var(--border); border-radius: 6px; word-break: break-all;">
                                <i class="fa-solid fa-envelope" style="color: var(--text2); font-size: 11px;"></i>
                                <span id="ve-email-text">No email provided</span>
                            </a>
                            <a id="ve-phone-link" href="#" style="display: flex; align-items: center; gap: 6px; font-size: 12px; color: var(--text); text-decoration: none; padding: 4px 8px; background: var(--bg); border: 1px solid var(--border); border-radius: 6px;">
                                <i class="fa-solid fa-phone" style="color: #10b981; font-size: 11px;"></i>
                                <span id="ve-phone-text">No phone provided</span>
                            </a>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 6px; border-top: 1px solid var(--border); padding-top: 8px;">
                        <div>
                            <div style="font-size: 10px; color: var(--text2); text-transform: uppercase; letter-spacing: 0.05em;">Encoded By</div>
                            <div id="ve-encoded-by" style="font-size: 11.5px; font-weight: 600; color: var(--text); margin-top: 1px;">System</div>
                        </div>
                        <div>
                            <div style="font-size: 10px; color: var(--text2); text-transform: uppercase; letter-spacing: 0.05em;">Requested On</div>
                            <div id="ve-created-at" style="font-size: 11.5px; font-weight: 600; color: var(--text); margin-top: 1px;">—</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Dynamic Context & Remarks Panel -->
            <div id="ve-notes-container" style="display: none; background: var(--bg3); border: 1px solid var(--border); border-radius: 8px; padding: 12px 14px;">
                <div style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.06em; font-weight: 700; color: var(--text); margin-bottom: 4px; display: flex; align-items: center; gap: 6px;">
                    <i class="fa-solid fa-quote-left" style="color: var(--accent);"></i> Parishioner Notes &amp; Special Requests
                </div>
                <div id="ve-notes" style="font-size: 12.5px; color: var(--text); line-height: 1.5; white-space: pre-wrap;">—</div>
            </div>

            <div id="ve-rejection-container" style="display: none; background: rgba(239,68,68,0.08); border: 1px solid rgba(239,68,68,0.3); border-radius: 10px; padding: 12px 14px;">
                <div style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.06em; font-weight: 700; color: #ef4444; margin-bottom: 4px; display: flex; align-items: center; gap: 6px;">
                    <i class="fa-solid fa-triangle-exclamation"></i> Rejection / Cancellation Reason
                </div>
                <div id="ve-rejection-note" style="font-size: 12.5px; color: var(--text); line-height: 1.5;">—</div>
            </div>

            <div id="ve-approval-container" style="display: none; background: rgba(16,185,129,0.08); border: 1px solid rgba(16,185,129,0.3); border-radius: 10px; padding: 10px 14px; display: flex; align-items: center; justify-content: space-between;">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <i class="fa-solid fa-circle-check" style="color: #10b981; font-size: 14px;"></i>
                    <span style="font-size: 12px; font-weight: 600; color: var(--text);">Officially Confirmed &amp; Added to Canonical Calendar</span>
                </div>
                <span id="ve-approved-at" style="font-size: 11px; color: var(--text2);">—</span>
            </div>

            <!-- Finance / Payment Status & Quick Stamp Bar -->
            <div id="ve-payment-container" style="display: none; background: var(--bg3); border: 1px solid var(--border); border-radius: 8px; padding: 10px 14px; align-items: center; justify-content: space-between; gap: 10px; flex-wrap: wrap;">
                <div style="display: flex; align-items: center; gap: 8px; min-width: 0;">
                    <i id="ve-payment-icon" class="fa-solid fa-receipt" style="color: var(--accent); font-size: 13px;"></i>
                    <span id="ve-payment-summary" style="font-size: 12px; font-weight: 600; color: var(--text);">—</span>
                </div>
                <div style="display: flex; align-items: center; gap: 6px; flex-wrap: wrap;">
                    <button type="button" id="ve-stamp-paid-btn" class="btn btn-sm" style="display: none; height: 28px; padding: 0 10px; font-size: 11.5px; font-weight: 600; background: #16a34a; color: #FFFFFF; border: 1px solid #15803d; border-radius: 6px; cursor: pointer; align-items: center; gap: 5px;">
                        <i class="fa-solid fa-peso-sign"></i> Stamp Paid (Finance)
                    </button>
                    <a id="ve-go-finance-link" href="dashboard.php?section=donations&tab=ledger" class="btn btn-gray btn-sm" style="display: inline-flex; height: 28px; padding: 0 10px; font-size: 11.5px; font-weight: 600; align-items: center; gap: 5px; text-decoration: none; border-radius: 6px;">
                        <i class="fa-solid fa-vault" style="color: var(--accent);"></i> Open Finance
                    </a>
                </div>
            </div>

            <!-- Guided Step Info Bar -->
            <div id="ve-next-step-guide" style="background: var(--bg2); border: 1px solid var(--border); border-left: 3px solid var(--accent); border-radius: 8px; padding: 10px 14px; font-size: 12px; color: var(--text); line-height: 1.45;"></div>
        </div>

        <!-- Action Toolbar Footer -->
        <div class="modal-footer" style="padding: 12px 20px; background: var(--panel-header-bg, var(--bg3)); border-top: 1px solid var(--border); display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 10px;">
            <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;" id="ve-review-actions-group">
                <button type="button" class="btn btn-success btn-sm" id="ve-approve-btn" style="display: none; height: 34px; padding: 0 16px; font-weight: 600;">
                    <i class="fa-solid fa-check-double"></i> Approve Request
                </button>
                <button type="button" class="btn btn-danger btn-sm" id="ve-reject-btn" style="display: none; height: 34px; padding: 0 14px; font-weight: 600;">
                    <i class="fa-solid fa-xmark"></i> Reject
                </button>
                <button type="button" class="btn btn-sm" id="ve-complete-btn" style="display: none; height: 34px; padding: 0 14px; font-weight: 600; background: #2563eb; color: #FFFFFF; border: 1px solid #1d4ed8; border-radius: 6px; cursor: pointer; align-items: center; gap: 6px;">
                    <i class="fa-solid fa-flag-checkered"></i> Mark Completed (Step 3)
                </button>
                <button type="button" class="btn btn-gray btn-sm" id="ve-chat-btn" style="display: none; height: 34px; padding: 0 14px; font-weight: 600;">
                    <i class="fa-solid fa-comments" style="color: var(--accent);"></i> Chat Requester
                </button>
            </div>
            <form id="ve-fallback-complete-form" method="POST" action="dashboard.php?section=event_requests" style="display: none;">
                <input type="hidden" name="form_section" value="review_request">
                <input type="hidden" name="redirect_section" value="event_requests">
                <input type="hidden" name="action" value="complete">
                <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
                <input type="hidden" name="id" id="ve-fallback-complete-id">
            </form>
            <div style="display: flex; gap: 8px; align-items: center; margin-left: auto;">
                <button type="button" class="btn btn-primary btn-sm" id="ve-edit-btn" style="height: 34px; padding: 0 14px; font-weight: 600;">
                    <i class="fa-solid fa-pen-to-square"></i> Edit Event
                </button>
                <button type="button" class="btn btn-gray btn-sm" onclick="closeModal('modal-view-event-details')" style="height: 34px; padding: 0 14px;">
                    Close
                </button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL: Add User -->
<div class="modal-overlay" id="modal-add-user">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title">New User</div>
            <button class="modal-close" onclick="closeModal('modal-add-user')">×</button>
        </div>
        <form id="form-add-user" method="POST">
            <input type="hidden" name="form_section" value="users">
            <input type="hidden" name="action" value="create">
            <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group" style="grid-column:1/2;"><label>Username</label><input name="user" minlength="3" maxlength="50" required></div>
                    <div class="form-group" style="grid-column:2/3;"><label>Full Name</label><input name="full_name" maxlength="100" placeholder="Juan Dela Cruz"></div>
                    <div class="form-group" style="grid-column:1/-1;"><label>Password</label><input type="password" name="password" minlength="6" maxlength="72" required></div>
                    <div class="form-group" style="grid-column:1/-1;"><label>Email</label><input type="email" name="email" maxlength="100" placeholder="user@email.com"></div>
                    <div class="form-group" style="grid-column:1/-1;"><label>Role</label>
                        <select name="role" required>
                            <option value="">Select role</option>
                            <?php if ($role === 'Admin'): ?><option value="Admin">Admin</option><?php endif; ?>
                            <option value="Cathedral Admin">Cathedral Admin</option>
                            <option value="Chapel Admin">Chapel Admin</option>
                            <option value="Priest">Priest</option>
                            <option value="Staff">Staff</option>
                            <option value="Member">Member</option>
                        </select>
                    </div>
                    <div class="form-group" style="grid-column:1/-1;"><label>Assigned Chapel <span style="color:var(--text2);font-weight:400;">(Required for Staff/Chapel Admin)</span></label>
                        <select name="chapel_id" <?php echo !in_array($role, ['Admin', 'Cathedral Admin']) ? 'disabled' : ''; ?>>
                            <option value="">-- No Chapel (Global) --</option>
                            <option value="0" <?php echo ($chapelId == 0 && $chapelId !== null) ? 'selected' : ''; ?>>IFI Cathedral (Main)</option>
                            <?php foreach ($chapels as $ch): ?>
                            <option value="<?php echo $ch['chapel_id']; ?>" <?php echo ($chapelId == $ch['chapel_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($ch['chapel_name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (!in_array($role, ['Admin', 'Cathedral Admin'])): ?>
                            <input type="hidden" name="chapel_id" value="<?php echo $chapelId; ?>">
                            <p style="font-size:11px; color:var(--accent); margin-top:4px;"><i class="fa-solid fa-lock"></i> Locked to your assigned branch.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn btn-primary">Create User</button>
                <button type="button" class="btn btn-gray" onclick="closeModal('modal-add-user')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL: Edit User (populated via openEditUserModal() JS) -->
<div class="modal-overlay" id="modal-edit-user">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title">Edit User</div>
            <button class="modal-close" onclick="closeModal('modal-edit-user')">×</button>
        </div>
        <form id="form-edit-user" method="POST">
            <input type="hidden" name="form_section" value="users">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
            <input type="hidden" name="id" id="edit-user-id">
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group" style="grid-column:1/2;"><label>Username</label><input name="user" id="edit-user-name" minlength="3" maxlength="50" required></div>
                    <div class="form-group" style="grid-column:2/3;"><label>Full Name</label><input name="full_name" id="edit-user-fullname" maxlength="100" placeholder="Juan Dela Cruz"></div>
                    <div class="form-group" style="grid-column:1/-1;"><label>Password <span style="color:var(--text2);font-weight:400;">(leave blank to keep)</span></label><input type="password" name="password" minlength="6" maxlength="72"></div>
                    <div class="form-group" style="grid-column:1/-1;"><label>Email</label><input type="email" name="email" id="edit-user-email" maxlength="100" placeholder="user@email.com"></div>
                    <div class="form-group" style="grid-column:1/-1;"><label>Role</label>
                        <select name="role" id="edit-user-role" required>
                            <?php if ($role === 'Admin'): ?><option value="Admin">Admin</option><?php endif; ?>
                            <option value="Cathedral Admin">Cathedral Admin</option>
                            <option value="Chapel Admin">Chapel Admin</option>
                            <option value="Priest">Priest</option>
                            <option value="Staff">Staff</option>
                            <option value="Member">Member</option>
                        </select>
                    </div>
                    <div class="form-group" style="grid-column:1/-1;"><label>Assigned Chapel <span style="color:var(--text2);font-weight:400;">(Required for Staff/Chapel Admin)</span></label>
                        <select name="chapel_id" id="edit-user-chapel">
                            <option value="">-- No Chapel (Global) --</option>
                            <option value="0">IFI Cathedral (Main)</option>
                            <?php foreach ($chapels as $ch): ?>
                            <option value="<?php echo $ch['chapel_id']; ?>"><?php echo htmlspecialchars($ch['chapel_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn btn-primary">Save</button>
                <button type="button" class="btn btn-gray" onclick="closeModal('modal-edit-user')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL: Add Chapel -->
<div class="modal-overlay" id="modal-add-chapel">
    <div class="modal modal-lg chapel-modal">
        <div class="modal-header">
            <div style="display: flex; align-items: center; gap: 12px;">
                <div class="modal-icon-avatar">
                    <i class="fa-solid fa-church"></i>
                </div>
                <div>
                    <div class="modal-title">New Chapel</div>
                    <div class="modal-subtitle">Register a new chapel branch, coordinator details, and Sunday schedule</div>
                </div>
            </div>
            <button class="modal-close" onclick="closeModal('modal-add-chapel')" title="Close dialog">×</button>
        </div>
        <form id="form-add-chapel" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="form_section" value="chapels">
            <input type="hidden" name="action" value="create">
            <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">

            <div class="modal-body chapel-modal-body">
                <!-- Section 1: Basic Information -->
                <div class="chapel-form-card">
                    <div class="chapel-card-title">
                        <i class="fa-solid fa-circle-info"></i> Basic Information
                    </div>
                    <div class="chapel-form-grid">
                        <div class="form-group grid-span-2">
                            <label for="add-chapel-name">Chapel Name <span class="req-star">*</span></label>
                            <input name="chapel_name" id="add-chapel-name" placeholder="e.g. San Miguel Arcangel Chapel" required>
                        </div>
                        <div class="form-group">
                            <label for="add-chapel-status">Operating Status</label>
                            <select name="status" id="add-chapel-status">
                                <option value="Active">Active</option>
                                <option value="Maintenance">Maintenance</option>
                            </select>
                        </div>
                        <div class="form-group grid-span-3">
                            <label for="add-chapel-loc"><i class="fa-solid fa-location-dot text-accent mr-1"></i> Location / Address</label>
                            <input name="location" id="add-chapel-loc" placeholder="e.g. Purok 4, Barangay San Juan, Bacoor">
                        </div>
                        <div class="form-group grid-span-2">
                            <label for="add-chapel-patron"><i class="fa-solid fa-cross text-accent mr-1"></i> Patron Saint</label>
                            <input name="patron_saint" id="add-chapel-patron" placeholder="e.g. St. Jude Thaddaeus">
                        </div>
                        <div class="form-group">
                            <label for="add-chapel-capacity"><i class="fa-solid fa-users text-accent mr-1"></i> Seating Capacity</label>
                            <input type="number" name="seating_capacity" id="add-chapel-capacity" min="1" max="10000" value="50">
                        </div>
                    </div>
                </div>

                <!-- Section 2: Leadership & Schedule -->
                <div class="chapel-form-card">
                    <div class="chapel-card-title">
                        <i class="fa-solid fa-user-clock"></i> Leadership & Schedule
                    </div>
                    <div class="chapel-form-grid">
                        <div class="form-group">
                            <label for="add-chapel-coord-name"><i class="fa-solid fa-user-tie text-accent mr-1"></i> Coordinator Name</label>
                            <input name="coordinator_name" id="add-chapel-coord-name" placeholder="e.g. Bro. Juan Dela Cruz">
                        </div>
                        <div class="form-group">
                            <label for="add-chapel-coord-phone"><i class="fa-solid fa-phone text-accent mr-1"></i> Coordinator Contact</label>
                            <input name="coordinator_phone" id="add-chapel-coord-phone" placeholder="e.g. 0917-123-4567">
                        </div>
                        <div class="form-group grid-span-2">
                            <label for="add-chapel-service-time"><i class="fa-solid fa-clock text-accent mr-1"></i> Sunday Service Schedule</label>
                            <input name="sunday_service_time" id="add-chapel-service-time" list="common-service-times" placeholder="e.g. 8:00 AM - 9:30 AM" value="Check Parish Schedule">
                            <span class="chapel-field-hint">Select a common time or type a custom regular schedule for this chapel.</span>
                        </div>
                    </div>
                </div>

                <!-- Section 3: Identity & Media -->
                <div class="chapel-form-card">
                    <div class="chapel-card-title">
                        <i class="fa-solid fa-palette"></i> Presentation & Aesthetics
                    </div>
                    <div class="chapel-form-grid" style="grid-template-columns: 1fr 1fr; gap: 16px;">
                        <!-- Color Palette Picker -->
                        <div class="form-group chapel-color-picker-wrap">
                            <label><i class="fa-solid fa-paintbrush text-accent mr-1"></i> Chapel Accent Color</label>
                            <div class="chapel-color-presets">
                                <button type="button" class="chapel-color-swatch" data-color="#c9a84c" style="background:#c9a84c;" title="Cathedral Gold" onclick="selectChapelColorPreset('#c9a84c', 'add-chapel-color', 'add-chapel-color-preview')"></button>
                                <button type="button" class="chapel-color-swatch" data-color="#d8b56f" style="background:#d8b56f;" title="Warm Ochre" onclick="selectChapelColorPreset('#d8b56f', 'add-chapel-color', 'add-chapel-color-preview')"></button>
                                <button type="button" class="chapel-color-swatch" data-color="#16a34a" style="background:#16a34a;" title="San Juan Forest Green" onclick="selectChapelColorPreset('#16a34a', 'add-chapel-color', 'add-chapel-color-preview')"></button>
                                <button type="button" class="chapel-color-swatch" data-color="#dc2626" style="background:#dc2626;" title="St. Michael Crimson" onclick="selectChapelColorPreset('#dc2626', 'add-chapel-color', 'add-chapel-color-preview')"></button>
                                <button type="button" class="chapel-color-swatch" data-color="#0b0f19" style="background:#0b0f19;" title="Deep Obsidian" onclick="selectChapelColorPreset('#0b0f19', 'add-chapel-color', 'add-chapel-color-preview')"></button>
                                <button type="button" class="chapel-color-swatch" data-color="#64748b" style="background:#64748b;" title="Slate Stone" onclick="selectChapelColorPreset('#64748b', 'add-chapel-color', 'add-chapel-color-preview')"></button>
                            </div>
                            <div class="chapel-color-input-row">
                                <input type="color" name="theme_color" id="add-chapel-color" value="#c9a84c" onchange="updateChapelColorDisplay('add-chapel-color', 'add-chapel-color-preview')">
                                <span class="chapel-color-preview-badge" id="add-chapel-color-preview">#C9A84C</span>
                                <span class="text-xs text-muted">Click preset or customize</span>
                            </div>
                        </div>

                        <!-- Chapel Image Upload -->
                        <div class="form-group">
                            <label><i class="fa-solid fa-camera text-accent mr-1"></i> Chapel Photo</label>
                            <input type="file" name="chapel_image" id="add-chapel-image-input" accept="image/*" style="display:none;" onchange="handleChapelImageSelect(this, 'add-chapel-image-preview', 'add-chapel-img-tag', 'add-chapel-filename')">
                            <div class="chapel-dropzone" onclick="document.getElementById('add-chapel-image-input').click()">
                                <i class="fa-solid fa-cloud-arrow-up chapel-dropzone-icon"></i>
                                <div class="chapel-dropzone-text">Click to choose a photo</div>
                                <div class="chapel-dropzone-sub">JPG, PNG, WebP (Max 5MB)</div>
                                <div id="add-chapel-filename" class="chapel-filename-label">No file selected</div>
                            </div>
                            <div id="add-chapel-image-preview" class="chapel-image-preview-card" style="display:none;">
                                <img id="add-chapel-img-tag" src="" alt="Selected Photo">
                                <div>
                                    <div style="font-size:12px; font-weight:600; color:var(--text);">Selected Image</div>
                                    <div class="text-xs text-muted">Ready to upload on save</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section 4: Annual Fiestas -->
                <div class="chapel-form-card">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                        <div class="chapel-card-title" style="margin-bottom:0;">
                            <i class="fa-solid fa-calendar-days"></i> Annual Feast Days &amp; Fiestas
                        </div>
                        <button type="button" class="btn btn-ghost btn-sm" style="color:var(--accent); font-weight:600;" onclick="addFiestaRow('add-fiesta-container')">
                            <i class="fa-solid fa-plus-circle"></i> Add Feast Day
                        </button>
                    </div>
                    <div id="add-fiesta-container" class="chapel-fiesta-container">
                        <!-- Default Initial Row -->
                        <div class="chapel-fiesta-row">
                            <div style="flex: 2;">
                                <input name="fiesta_names[]" placeholder="Feast Name (e.g. Patronal Feast)" style="width: 100%;">
                            </div>
                            <div style="flex: 1.2;">
                                <select name="fiesta_months[]" style="width: 100%;">
                                    <?php echo $monthOpts; ?>
                                </select>
                            </div>
                            <div style="width: 80px;">
                                <select name="fiesta_days[]" style="width: 100%;">
                                    <?php echo $dayOpts; ?>
                                </select>
                            </div>
                            <button type="button" class="btn btn-ghost btn-icon" style="color: var(--color-danger, #ef4444); width: 34px; height: 34px; border-radius: 6px; display: flex; align-items: center; justify-content: center;" title="Remove this feast day" onclick="this.closest('.chapel-fiesta-row').remove()">
                                <i class="fa-solid fa-trash-can"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Section 5: Email Server Configuration (Collapsible) -->
                <details class="chapel-smtp-details" id="add-chapel-smtp-details">
                    <summary class="chapel-smtp-summary">
                        <div style="display:flex; align-items:center; gap:10px;">
                            <i class="fa-solid fa-envelope-circle-check text-accent"></i>
                            <div>
                                <div style="font-weight:600; font-size:13px; color:var(--text);">Outgoing Email Dispatch (Optional SMTP)</div>
                                <div style="font-size:11px; color:var(--text2);">Configure if this chapel delivers notifications from its own mailbox</div>
                            </div>
                        </div>
                        <div style="display:flex; align-items:center; gap:8px;">
                            <span class="chapel-optional-badge">Optional</span>
                            <i class="fa-solid fa-chevron-down chapel-accordion-icon"></i>
                        </div>
                    </summary>
                    <div class="chapel-smtp-body">
                        <div class="chapel-form-grid" style="grid-template-columns: 1fr 1fr; gap: 12px;">
                            <div class="form-group">
                                <label for="add-chapel-smtp-host">SMTP Host</label>
                                <input name="smtp_host" id="add-chapel-smtp-host" placeholder="e.g. smtp.gmail.com">
                            </div>
                            <div class="form-group">
                                <label for="add-chapel-smtp-port">SMTP Port</label>
                                <input type="number" name="smtp_port" id="add-chapel-smtp-port" placeholder="587">
                            </div>
                            <div class="form-group">
                                <label for="add-chapel-smtp-user">SMTP Username / Email</label>
                                <input name="smtp_user" id="add-chapel-smtp-user" placeholder="chapel.office@gmail.com">
                            </div>
                            <div class="form-group">
                                <label for="add-chapel-smtp-pass">SMTP Password / App Password</label>
                                <input type="password" name="smtp_pass" id="add-chapel-smtp-pass" placeholder="App Password">
                            </div>
                            <div class="form-group">
                                <label for="add-chapel-smtp-secure">Encryption Protocol</label>
                                <select name="smtp_secure" id="add-chapel-smtp-secure">
                                    <option value="tls">STARTTLS (Port 587 - Recommended)</option>
                                    <option value="ssl">SSL / TLS (Port 465)</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="add-chapel-from-email">Sender Email (From)</label>
                                <input name="from_email" id="add-chapel-from-email" placeholder="public-info@chapel.org">
                            </div>
                            <div class="form-group grid-span-2">
                                <label for="add-chapel-from-name">Sender Display Name</label>
                                <input name="from_name" id="add-chapel-from-name" placeholder="e.g. St. Jude Chapel Secretariat">
                            </div>
                        </div>
                        <p class="text-xs text-muted" style="margin-top: 10px; display: flex; align-items: center; gap: 6px;">
                            <i class="fa-solid fa-circle-info text-accent"></i> Leave blank to use the Cathedral's master outgoing mail server.
                        </p>
                    </div>
                </details>
            </div>

            <div class="modal-footer chapel-modal-footer">
                <button type="button" class="btn btn-ghost" onclick="closeModal('modal-add-chapel')">
                    <i class="fa-solid fa-xmark mr-1"></i> Cancel
                </button>
                <button type="submit" class="btn btn-primary" style="padding: 10px 22px; font-weight: 600;">
                    <i class="fa-solid fa-plus-circle mr-1"></i> Create Chapel
                </button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL: Edit Chapel -->
<div class="modal-overlay" id="modal-edit-chapel">
    <div class="modal modal-lg chapel-modal">
        <div class="modal-header">
            <div style="display: flex; align-items: center; gap: 12px;">
                <div class="modal-icon-avatar">
                    <i class="fa-solid fa-church"></i>
                </div>
                <div>
                    <div class="modal-title">Edit Chapel</div>
                    <div class="modal-subtitle">Update location, coordinator details, service schedule, and appearance</div>
                </div>
            </div>
            <button class="modal-close" onclick="closeModal('modal-edit-chapel')" title="Close dialog">×</button>
        </div>
        <form id="form-edit-chapel" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="form_section" value="chapels">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
            <input type="hidden" name="id" id="edit-chapel-id">

            <div class="modal-body chapel-modal-body">
                <!-- Section 1: Basic Information -->
                <div class="chapel-form-card">
                    <div class="chapel-card-title">
                        <i class="fa-solid fa-circle-info"></i> Basic Information
                    </div>
                    <div class="chapel-form-grid">
                        <div class="form-group grid-span-2">
                            <label for="edit-chapel-name">Chapel Name <span class="req-star">*</span></label>
                            <input name="chapel_name" id="edit-chapel-name" placeholder="e.g. San Miguel Arcangel Chapel" required>
                        </div>
                        <div class="form-group">
                            <label for="edit-chapel-status">Operating Status</label>
                            <select name="status" id="edit-chapel-status">
                                <option value="Active">Active</option>
                                <option value="Maintenance">Maintenance</option>
                            </select>
                        </div>
                        <div class="form-group grid-span-3">
                            <label for="edit-chapel-loc"><i class="fa-solid fa-location-dot text-accent mr-1"></i> Location / Address</label>
                            <input name="location" id="edit-chapel-loc" placeholder="e.g. Purok 4, Barangay San Juan, Bacoor">
                        </div>
                        <div class="form-group grid-span-2">
                            <label for="edit-chapel-patron"><i class="fa-solid fa-cross text-accent mr-1"></i> Patron Saint</label>
                            <input name="patron_saint" id="edit-chapel-patron" placeholder="e.g. St. Jude Thaddaeus">
                        </div>
                        <div class="form-group">
                            <label for="edit-chapel-capacity"><i class="fa-solid fa-users text-accent mr-1"></i> Seating Capacity</label>
                            <input type="number" name="seating_capacity" id="edit-chapel-capacity" min="1" max="10000" placeholder="50">
                        </div>
                    </div>
                </div>

                <!-- Section 2: Leadership & Schedule -->
                <div class="chapel-form-card">
                    <div class="chapel-card-title">
                        <i class="fa-solid fa-user-clock"></i> Leadership & Schedule
                    </div>
                    <div class="chapel-form-grid">
                        <div class="form-group">
                            <label for="edit-chapel-coord-name"><i class="fa-solid fa-user-tie text-accent mr-1"></i> Coordinator Name</label>
                            <input name="coordinator_name" id="edit-chapel-coord-name" placeholder="e.g. Bro. Juan Dela Cruz">
                        </div>
                        <div class="form-group">
                            <label for="edit-chapel-coord-phone"><i class="fa-solid fa-phone text-accent mr-1"></i> Coordinator Contact</label>
                            <input name="coordinator_phone" id="edit-chapel-coord-phone" placeholder="e.g. 0917-123-4567">
                        </div>
                        <div class="form-group grid-span-2">
                            <label for="edit-chapel-service-time"><i class="fa-solid fa-clock text-accent mr-1"></i> Sunday Service Schedule</label>
                            <input name="sunday_service_time" id="edit-chapel-service-time" list="common-service-times" placeholder="e.g. 8:00 AM - 9:30 AM or Check Parish Schedule">
                            <span class="chapel-field-hint">Select a common time or type a custom regular schedule for this chapel.</span>
                        </div>
                    </div>
                </div>

                <!-- Section 3: Identity & Media -->
                <div class="chapel-form-card">
                    <div class="chapel-card-title">
                        <i class="fa-solid fa-palette"></i> Presentation & Aesthetics
                    </div>
                    <div class="chapel-form-grid" style="grid-template-columns: 1fr 1fr; gap: 16px;">
                        <!-- Color Palette Picker -->
                        <div class="form-group chapel-color-picker-wrap">
                            <label><i class="fa-solid fa-paintbrush text-accent mr-1"></i> Chapel Accent Color</label>
                            <div class="chapel-color-presets">
                                <button type="button" class="chapel-color-swatch" data-color="#c9a84c" style="background:#c9a84c;" title="Cathedral Gold" onclick="selectChapelColorPreset('#c9a84c', 'edit-chapel-color', 'edit-chapel-color-preview')"></button>
                                <button type="button" class="chapel-color-swatch" data-color="#d8b56f" style="background:#d8b56f;" title="Warm Ochre" onclick="selectChapelColorPreset('#d8b56f', 'edit-chapel-color', 'edit-chapel-color-preview')"></button>
                                <button type="button" class="chapel-color-swatch" data-color="#16a34a" style="background:#16a34a;" title="San Juan Forest Green" onclick="selectChapelColorPreset('#16a34a', 'edit-chapel-color', 'edit-chapel-color-preview')"></button>
                                <button type="button" class="chapel-color-swatch" data-color="#dc2626" style="background:#dc2626;" title="St. Michael Crimson" onclick="selectChapelColorPreset('#dc2626', 'edit-chapel-color', 'edit-chapel-color-preview')"></button>
                                <button type="button" class="chapel-color-swatch" data-color="#0b0f19" style="background:#0b0f19;" title="Deep Obsidian" onclick="selectChapelColorPreset('#0b0f19', 'edit-chapel-color', 'edit-chapel-color-preview')"></button>
                                <button type="button" class="chapel-color-swatch" data-color="#64748b" style="background:#64748b;" title="Slate Stone" onclick="selectChapelColorPreset('#64748b', 'edit-chapel-color', 'edit-chapel-color-preview')"></button>
                            </div>
                            <div class="chapel-color-input-row">
                                <input type="color" name="theme_color" id="edit-chapel-color" value="#c9a84c" onchange="updateChapelColorDisplay('edit-chapel-color', 'edit-chapel-color-preview')">
                                <span class="chapel-color-preview-badge" id="edit-chapel-color-preview">#C9A84C</span>
                                <span class="text-xs text-muted">Click preset or customize</span>
                            </div>
                        </div>

                        <!-- Chapel Image Upload -->
                        <div class="form-group">
                            <label><i class="fa-solid fa-camera text-accent mr-1"></i> Chapel Photo</label>
                            <input type="file" name="chapel_image" id="edit-chapel-image-input" accept="image/*" style="display:none;" onchange="handleChapelImageSelect(this, 'edit-chapel-image-preview', 'edit-chapel-img-tag', 'edit-chapel-filename')">
                            <div class="chapel-dropzone" onclick="document.getElementById('edit-chapel-image-input').click()">
                                <i class="fa-solid fa-cloud-arrow-up chapel-dropzone-icon"></i>
                                <div class="chapel-dropzone-text">Click to choose a photo</div>
                                <div class="chapel-dropzone-sub">JPG, PNG, WebP (Max 5MB)</div>
                                <div id="edit-chapel-filename" class="chapel-filename-label">No new file chosen</div>
                            </div>
                            <div id="edit-chapel-image-preview" class="chapel-image-preview-card" style="display:none;">
                                <img id="edit-chapel-img-tag" src="" alt="Chapel Image Preview">
                                <div>
                                    <div style="font-size:12px; font-weight:600; color:var(--text);">Current Image</div>
                                    <div class="text-xs text-muted">Uploaded chapel banner photo</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section 4: Annual Fiestas -->
                <div class="chapel-form-card">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                        <div class="chapel-card-title" style="margin-bottom:0;">
                            <i class="fa-solid fa-calendar-days"></i> Annual Feast Days &amp; Fiestas
                        </div>
                        <button type="button" class="btn btn-ghost btn-sm" style="color:var(--accent); font-weight:600;" onclick="addFiestaRow('edit-fiesta-container')">
                            <i class="fa-solid fa-plus-circle"></i> Add Feast Day
                        </button>
                    </div>
                    <div id="edit-fiesta-container" class="chapel-fiesta-container">
                        <!-- Rows injected via JS -->
                    </div>
                </div>

                <!-- Section 5: Email Server Configuration (Collapsible) -->
                <details class="chapel-smtp-details" id="edit-chapel-smtp-details">
                    <summary class="chapel-smtp-summary">
                        <div style="display:flex; align-items:center; gap:10px;">
                            <i class="fa-solid fa-envelope-circle-check text-accent"></i>
                            <div>
                                <div style="font-weight:600; font-size:13px; color:var(--text);">Outgoing Email Dispatch (Optional SMTP)</div>
                                <div style="font-size:11px; color:var(--text2);">Configured if this chapel delivers notifications from its own mailbox</div>
                            </div>
                        </div>
                        <div style="display:flex; align-items:center; gap:8px;">
                            <span class="chapel-optional-badge">Optional</span>
                            <i class="fa-solid fa-chevron-down chapel-accordion-icon"></i>
                        </div>
                    </summary>
                    <div class="chapel-smtp-body">
                        <div class="chapel-form-grid" style="grid-template-columns: 1fr 1fr; gap: 12px;">
                            <div class="form-group">
                                <label for="edit-chapel-smtp-host">SMTP Host</label>
                                <input name="smtp_host" id="edit-chapel-smtp-host" placeholder="e.g. smtp.gmail.com">
                            </div>
                            <div class="form-group">
                                <label for="edit-chapel-smtp-port">SMTP Port</label>
                                <input type="number" name="smtp_port" id="edit-chapel-smtp-port" placeholder="587">
                            </div>
                            <div class="form-group">
                                <label for="edit-chapel-smtp-user">SMTP Username / Email</label>
                                <input name="smtp_user" id="edit-chapel-smtp-user" placeholder="chapel.office@gmail.com">
                            </div>
                            <div class="form-group">
                                <label for="edit-chapel-smtp-pass">SMTP Password / App Password</label>
                                <input type="password" name="smtp_pass" id="edit-chapel-smtp-pass" placeholder="Leave empty to retain existing password">
                            </div>
                            <div class="form-group">
                                <label for="edit-chapel-smtp-secure">Encryption Protocol</label>
                                <select name="smtp_secure" id="edit-chapel-smtp-secure">
                                    <option value="tls">STARTTLS (Port 587 - Recommended)</option>
                                    <option value="ssl">SSL / TLS (Port 465)</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="edit-chapel-from-email">Sender Email (From)</label>
                                <input name="from_email" id="edit-chapel-from-email" placeholder="public-info@chapel.org">
                            </div>
                            <div class="form-group grid-span-2">
                                <label for="edit-chapel-from-name">Sender Display Name</label>
                                <input name="from_name" id="edit-chapel-from-name" placeholder="e.g. San Miguel Chapel Secretariat">
                            </div>
                        </div>
                        <p class="text-xs text-muted" style="margin-top: 10px; display: flex; align-items: center; gap: 6px;">
                            <i class="fa-solid fa-circle-info text-accent"></i> When left blank, all automated notifications fall back to the Cathedral's master mail server.
                        </p>
                    </div>
                </details>
            </div>

            <div class="modal-footer chapel-modal-footer">
                <button type="button" class="btn btn-ghost" onclick="closeModal('modal-edit-chapel')">
                    <i class="fa-solid fa-xmark mr-1"></i> Cancel
                </button>
                <button type="submit" class="btn btn-primary" style="padding: 10px 22px; font-weight: 600;">
                    <i class="fa-solid fa-floppy-disk mr-1"></i> Update Chapel
                </button>
            </div>
        </form>
    </div>
</div>
<script>
    // Templates for JS Injection
    window.fiestaMonthOpts = "<?php echo $monthOpts; ?>";
    window.fiestaDayOpts   = "<?php echo $dayOpts; ?>";
</script>

<datalist id="common-service-times">
    <option value="6:00 AM">
    <option value="7:00 AM">
    <option value="8:00 AM">
    <option value="9:00 AM">
    <option value="10:00 AM">
    <option value="11:00 AM">
    <option value="12:00 PM">
    <option value="3:00 PM">
    <option value="4:00 PM">
    <option value="5:00 PM">
    <option value="6:00 PM">
    <option value="Check Parish Schedule">
</datalist>


<!-- MODAL: Approve Request -->
<div class="modal-overlay" id="modal-approve-request">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title">Approve Request</div>
            <button class="modal-close" onclick="closeModal('modal-approve-request')">×</button>
        </div>
        <form id="form-approve-request" method="POST">
            <input type="hidden" name="form_section" value="review_request">
            <input type="hidden" name="action" value="approve">
            <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
            <input type="hidden" name="id" id="approve-req-id">
            <div class="modal-body">
                <p class="text-md text-muted mb-16">Approving: <strong id="approve-req-title"></strong></p>
                
                <!-- Hidden fields for onFormChange compatibility -->
                <input type="hidden" id="field-event-type-approve">
                <input type="hidden" id="field-datetime-approve">
                <input type="hidden" id="field-venue-approve">
                <input type="hidden" id="field-custom-duration-approve">

                <div class="form-group">
                    <label>Assign Priest <small class="text-muted">(Optional)</small></label>
                    <input type="hidden" name="pastor_id" id="selected-pastor-input-approve">
                    
                    <div id="pastor-picker-grid-approve" class="pastor-picker-grid mt-12">
                        <!-- Loaded dynamically via dashboard-availability.js -->
                    </div>
                    
                    <div id="pastor-picker-none-approve" class="p-16 text-center text-sm text-muted" style="display:none; background:var(--bg3); border:1px dashed var(--border); border-radius:12px;">
                        No priests available for this type/time.
                    </div>

                    <div id="coverage-warning-approve" style="display:none; margin-top:8px; padding:10px; background:rgba(220,38,38,0.1); border:1px solid rgba(220,38,38,0.3); border-radius:6px; color:#dc2626; font-size:12px;">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                        <span id="coverage-warning-text-approve"></span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn btn-green"><i class="fa-solid fa-check"></i> Confirm Approval</button>
                <button type="button" class="btn btn-gray" onclick="closeModal('modal-approve-request')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL: Reject Request -->
<div class="modal-overlay" id="modal-reject-request">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title">Reject Request</div>
            <button class="modal-close" onclick="closeModal('modal-reject-request')">×</button>
        </div>
        <form id="form-reject-request" method="POST">
            <input type="hidden" name="form_section" value="review_request">
            <input type="hidden" name="action" value="reject">
            <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
            <input type="hidden" name="id" id="reject-req-id">
            <div class="modal-body">
                <p class="text-lg text-muted mb-16">Rejecting: <strong id="reject-req-title"></strong></p>
                <div class="form-group">
                    <label>Reason for Rejection <span class="text-muted text-sm">(shown to requester)</span></label>
                    <input type="text" name="rejection_note" id="reject-note" placeholder="e.g. Date conflict, venue unavailable" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn btn-red"><i class="fa-solid fa-xmark"></i> Confirm Rejection</button>
                <button type="button" class="btn btn-gray" onclick="closeModal('modal-reject-request')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Bulletin Modals are self-contained in views/partials/dashboard/bulletin.php -->

<!-- ── MODALS: SACRAMENTAL RECORDS ────────────────────────────────────────── -->

<!-- Add Cathedral Record -->
<div class="modal-overlay" id="modal-add-cathedral-record">
    <div class="modal modal-lg" style="max-width: 860px; border-radius: 12px; border: 1px solid var(--border); box-shadow: none; overflow: hidden; background: #FFFFFF;">
        <div class="modal-header" style="background: #FFFFFF; border-bottom: 1px solid var(--border); padding: 16px 24px;">
            <div style="display: flex; align-items: center; gap: 12px;">
                <div style="width: 38px; height: 38px; border-radius: 8px; background: #F6F6F6; color: #111111; display: flex; align-items: center; justify-content: center; font-size: 16px; border: 1px solid #E5E5E5;">
                    <i class="fa-solid fa-book-bible"></i>
                </div>
                <div>
                    <div class="modal-title" style="font-size: 15px; font-weight: 700; font-family: var(--font-family, 'Inter', sans-serif); margin: 0; line-height: 1.2; color: #111111;">New Cathedral Record</div>
                    <div style="font-size: 11px; color: #737373; font-weight: 400; margin-top: 2px;">Register sacramental entry into cathedral records</div>
                </div>
            </div>
            <button class="modal-close" onclick="closeModal('modal-add-cathedral-record')">×</button>
        </div>
        <form id="form-add-cathedral-record" method="POST">
            <input type="hidden" name="form_section" value="records_cathedral">
            <input type="hidden" name="action" value="create_record">
            <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
            <div class="modal-body" style="padding: 20px 24px; max-height: 75vh; overflow-y: auto;">
                <?php echo renderRecordFormFields($chapels, 'add-cath'); ?>
            </div>
            <div class="modal-footer" style="padding: 14px 24px; border-top: 1px solid var(--border); background: #FFFFFF; display: flex; justify-content: flex-end; align-items: center; gap: 10px;">
                <button type="button" class="btn btn-gray" onclick="closeModal('modal-add-cathedral-record')">Cancel</button>
                <button type="submit" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 8px; font-weight: 600; padding: 9px 20px; box-shadow: none;">
                    <i class="fa-solid fa-floppy-disk"></i> Save Record
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Cathedral Record -->
<div class="modal-overlay" id="modal-edit-cath-record">
    <div class="modal modal-lg" style="max-width: 860px; border-radius: 12px; border: 1px solid var(--border); box-shadow: none; overflow: hidden; background: #FFFFFF;">
        <div class="modal-header" style="background: #FFFFFF; border-bottom: 1px solid var(--border); padding: 16px 24px;">
            <div style="display: flex; align-items: center; gap: 12px;">
                <div style="width: 38px; height: 38px; border-radius: 8px; background: #F6F6F6; color: #111111; display: flex; align-items: center; justify-content: center; font-size: 16px; border: 1px solid #E5E5E5;">
                    <i class="fa-solid fa-pen-fancy"></i>
                </div>
                <div>
                    <div class="modal-title" style="font-size: 15px; font-weight: 700; font-family: var(--font-family, 'Inter', sans-serif); margin: 0; line-height: 1.2; color: #111111;">Edit Cathedral Record</div>
                    <div style="font-size: 11px; color: #737373; font-weight: 400; margin-top: 2px;">Modify existing archival data with full audit tracking</div>
                </div>
            </div>
            <button class="modal-close" onclick="closeModal('modal-edit-cath-record')">×</button>
        </div>
        <form id="form-edit-cath-record" method="POST">
            <input type="hidden" name="form_section" value="records_cathedral">
            <input type="hidden" name="action" value="update_record">
            <input type="hidden" name="id" id="edit-cath-rec-id">
            <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
            <div class="modal-body" style="padding: 20px 24px; max-height: 75vh; overflow-y: auto;">
                <?php echo renderRecordFormFields($chapels, 'cath'); ?>
            </div>
            <div class="modal-footer" style="padding: 14px 24px; border-top: 1px solid var(--border); background: #FFFFFF; display: flex; justify-content: flex-end; align-items: center; gap: 10px;">
                <button type="button" class="btn btn-gray" onclick="closeModal('modal-edit-cath-record')">Cancel</button>
                <button type="submit" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 8px; font-weight: 600; padding: 9px 20px; box-shadow: none;">
                    <i class="fa-solid fa-check-double"></i> Update Record
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Add Chapel Record -->
<div class="modal-overlay" id="modal-add-chapel-record">
    <div class="modal modal-lg" style="max-width: 860px; border-radius: 12px; border: 1px solid var(--border); box-shadow: none; overflow: hidden; background: #FFFFFF;">
        <div class="modal-header" style="background: #FFFFFF; border-bottom: 1px solid var(--border); padding: 16px 24px;">
            <div style="display: flex; align-items: center; gap: 12px;">
                <div style="width: 38px; height: 38px; border-radius: 8px; background: #F6F6F6; color: #111111; display: flex; align-items: center; justify-content: center; font-size: 16px; border: 1px solid #E5E5E5;">
                    <i class="fa-solid fa-church"></i>
                </div>
                <div>
                    <div class="modal-title" style="font-size: 15px; font-weight: 700; font-family: var(--font-family, 'Inter', sans-serif); margin: 0; line-height: 1.2; color: #111111;">New Chapel Record</div>
                    <div style="font-size: 11px; color: #737373; font-weight: 400; margin-top: 2px;">Register sacramental entry into chapel records</div>
                </div>
            </div>
            <button class="modal-close" onclick="closeModal('modal-add-chapel-record')">×</button>
        </div>
        <form id="form-add-chapel-record" method="POST">
            <input type="hidden" name="form_section" value="records_chapel">
            <input type="hidden" name="action" value="create_record">
            <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
            <div class="modal-body" style="padding: 20px 24px; max-height: 75vh; overflow-y: auto;">
                <?php echo renderRecordFormFields($chapels, 'add-chap'); ?>
            </div>
            <div class="modal-footer" style="padding: 14px 24px; border-top: 1px solid var(--border); background: #FFFFFF; display: flex; justify-content: flex-end; align-items: center; gap: 10px;">
                <button type="button" class="btn btn-gray" onclick="closeModal('modal-add-chapel-record')">Cancel</button>
                <button type="submit" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 8px; font-weight: 600; padding: 9px 20px; box-shadow: none;">
                    <i class="fa-solid fa-floppy-disk"></i> Save Record
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Chapel Record -->
<div class="modal-overlay" id="modal-edit-chap-record">
    <div class="modal modal-lg" style="max-width: 860px; border-radius: 12px; border: 1px solid var(--border); box-shadow: none; overflow: hidden; background: #FFFFFF;">
        <div class="modal-header" style="background: #FFFFFF; border-bottom: 1px solid var(--border); padding: 16px 24px;">
            <div style="display: flex; align-items: center; gap: 12px;">
                <div style="width: 38px; height: 38px; border-radius: 8px; background: #F6F6F6; color: #111111; display: flex; align-items: center; justify-content: center; font-size: 16px; border: 1px solid #E5E5E5;">
                    <i class="fa-solid fa-pen-nib"></i>
                </div>
                <div>
                    <div class="modal-title" style="font-size: 15px; font-weight: 700; font-family: var(--font-family, 'Inter', sans-serif); margin: 0; line-height: 1.2; color: #111111;">Edit Chapel Record</div>
                    <div style="font-size: 11px; color: #737373; font-weight: 400; margin-top: 2px;">Update sacramental data for designated chapel</div>
                </div>
            </div>
            <button class="modal-close" onclick="closeModal('modal-edit-chap-record')">×</button>
        </div>
        <form id="form-edit-chap-record" method="POST">
            <input type="hidden" name="form_section" value="records_chapel">
            <input type="hidden" name="action" value="update_record">
            <input type="hidden" name="id" id="edit-chap-rec-id">
            <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
            <div class="modal-body" style="padding: 20px 24px; max-height: 75vh; overflow-y: auto;">
                <?php echo renderRecordFormFields($chapels, 'chap'); ?>
            </div>
            <div class="modal-footer" style="padding: 14px 24px; border-top: 1px solid var(--border); background: #FFFFFF; display: flex; justify-content: flex-end; align-items: center; gap: 10px;">
                <button type="button" class="btn btn-gray" onclick="closeModal('modal-edit-chap-record')">Cancel</button>
                <button type="submit" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 8px; font-weight: 600; padding: 9px 20px; box-shadow: none;">
                    <i class="fa-solid fa-check-double"></i> Update Record
                </button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL: Issue Certificate -->
<div class="modal-overlay" id="modal-issue-cert">
    <div class="modal" style="max-width: 450px;">
        <div class="modal-header">
            <div class="modal-title"><i class="fa-solid fa-certificate"></i> Issue Certificate</div>
            <button class="modal-close" onclick="closeModal('modal-issue-cert')">×</button>
        </div>
        <form method="POST">
            <input type="hidden" name="form_section" id="issue-cert-section" value="records_cathedral">
            <input type="hidden" name="action" value="issue_cert">
            <input type="hidden" name="record_id" id="issue-cert-record-id">
            <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
            
            <div class="modal-body">
                <div class="p-16 mb-16" style="background:var(--bg3); border:1px solid var(--border); border-radius:10px; border-left:4px solid var(--accent);">
                    <div class="text-xs text-muted text-uppercase text-bold mb-4">Generating For</div>
                    <div style="font-size:16px; font-weight:700;" id="issue-cert-person-name">...</div>
                    <div class="text-sm text-accent" id="issue-cert-type">...</div>
                </div>

                <div class="form-group mb-16">
                    <label>Issued To (Recipient Name)</label>
                    <input name="issued_to" id="issue-cert-issued-to" placeholder="e.g. Juan dela Cruz" required>
                    <p class="text-xs text-muted mt-4">This name will appear on the "Issued To" line of the certificate footer.</p>
                </div>

                <div class="form-group mb-16">
                    <label>Purpose of Issuance</label>
                    <input name="purpose" id="issue-cert-purpose" placeholder="e.g. For School, Personal Copy, Marriage Requirement">
                </div>

                <div class="text-center p-12" style="background:rgba(138,96,16,0.05); border-radius:8px; border:1px dashed rgba(138,96,16,0.3);">
                    <p class="text-xs text-muted" style="color:#8a6010!important;">
                        <i class="fa-solid fa-circle-info mr-4"></i>
                        Confirming this will log an official issuance entry in the system and open the printable document.
                    </p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-print mr-6"></i> Issue & Print</button>
                <button type="button" class="btn btn-ghost" onclick="previewCert()">Preview instead</button>
                <button type="button" class="btn btn-gray" onclick="closeModal('modal-issue-cert')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL: New Support Ticket -->
<div class="modal-overlay" id="modal-new-ticket">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title">New Support Ticket</div>
            <button class="modal-close" onclick="closeModal('modal-new-ticket')">×</button>
        </div>
        <form id="form-new-ticket" method="POST">
            <input type="hidden" name="form_section" value="tickets">
            <input type="hidden" name="action" value="create">
            <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group" style="grid-column:1/-1;">
                        <label>Subject</label>
                        <input name="subject" placeholder="e.g. Broken AC in Chapel, Website Error" required>
                    </div>
                    <div class="form-group">
                        <label>Category</label>
                        <select name="category">
                            <option>General</option>
                            <option>Maintenance</option>
                            <option>Technical Support</option>
                            <option>Event Query</option>
                            <option>Financial</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Priority</label>
                        <select name="priority">
                            <option>Low</option>
                            <option>Medium</option>
                            <option>High</option>
                            <option>Urgent</option>
                        </select>
                    </div>
                    <div class="form-group" style="grid-column:1/-1;">
                        <label>Description</label>
                        <textarea name="description" rows="4" style="width:100%; padding:10px; border:1px solid var(--border); border-radius:6px; background:var(--bg2); color:var(--text1);" placeholder="Describe the issue in detail..."></textarea>
                    </div>
                    <div class="form-group">
                        <label>Requester Name</label>
                        <input name="name" placeholder="e.g. Juan Dela Cruz">
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" placeholder="juan@email.com">
                    </div>
                    <div class="form-group" style="grid-column:1/-1;">
                        <label>Mobile Number <span style="color:var(--text2);font-weight:400;font-size:11px;">(for SMS when resolved)</span></label>
                        <input type="tel" name="phone" placeholder="0912-345-6789" maxlength="15" pattern="[0-9\+\s\-]{10,20}" title="Enter valid mobile number (e.g. 0912-345-6789 or 09171234567)">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn btn-primary">Submit Ticket</button>
                <button type="button" class="btn btn-gray" onclick="closeModal('modal-new-ticket')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL: Send Ticket Report -->
<div class="modal-overlay" id="modal-send-ticket">
    <div class="modal" style="max-width: 450px;">
        <div class="modal-header">
            <div class="modal-title">Send Ticket Report</div>
            <button class="modal-close" onclick="closeModal('modal-send-ticket')">×</button>
        </div>
        <form id="form-send-ticket" method="POST">
            <input type="hidden" name="form_section" value="tickets">
            <input type="hidden" name="action" value="send_report">
            <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
            <input type="hidden" name="ticket_id" id="send-report-ticket-id">
            <div class="modal-body">
                <div style="text-align:center; margin-bottom:20px;">
                    <i class="fa-solid fa-paper-plane" style="font-size:3rem; color:var(--accent); opacity:0.8;"></i>
                    <p style="margin-top:10px; font-weight:600;">Dispatching Report for Ticket #<span id="send-report-no">...</span></p>
                </div>
                <div class="form-group">
                    <label>Recipient Email Address</label>
                    <input type="email" name="recipient_email" placeholder="e.g. administrator@cathedral.org" required>
                </div>
                <div class="form-group">
                    <label>Message / Note <span class="text-muted text-sm">(optional)</span></label>
                    <textarea name="message" rows="3" placeholder="Add a custom note to the recipient..." style="width:100%; padding:10px; border:1px solid var(--border); border-radius:6px; background:var(--bg2); color:var(--text1);"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn btn-primary">Send Report</button>
                <button type="button" class="btn btn-gray" onclick="closeModal('modal-send-ticket')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL: Logout Confirmation -->
<div class="modal-overlay" id="modal-logout">
    <div class="modal" style="max-width: 400px; text-align: center;">
        <div class="modal-header" style="justify-content: center; border-bottom: none;">
            <div class="modal-title" style="font-size: 1.5rem;">Confirm Logout</div>
        </div>
        <div class="modal-body" style="padding: 20px 0;">
            <div style="font-size: 3rem; color: #ef4444; margin-bottom: 20px;">
                <i class="fa-solid fa-right-from-bracket"></i>
            </div>
            <p style="color: var(--text2); font-size: 1.1rem; margin: 0;">Are you sure you want to log out of the system?</p>
        </div>
        <div class="modal-footer" style="justify-content: center; border-top: none; gap: 12px; padding-bottom: 30px;">
            <a href="logout.php" class="btn btn-red" style="padding: 10px 24px; font-weight: 600; text-decoration: none;">Yes, Log Me Out</a>
            <button class="btn btn-gray" onclick="closeModal('modal-logout')" style="padding: 10px 24px; font-weight: 600;">Cancel</button>
        </div>
    </div>
</div>

<!-- MODAL: View Ticket Details & Conversations -->
<div class="modal-overlay" id="modal-view-ticket">
    <div class="modal modal-lg" style="max-height:90vh;">
        <div class="modal-header">
            <div class="modal-title" id="vt-ticket-no">Ticket Details</div>
            <button class="modal-close" onclick="closeModal('modal-view-ticket')">×</button>
        </div>
        <div class="modal-body" style="padding:0; display:flex; flex-direction:column; height: 75vh;">
            <div style="padding: 24px; border-bottom:1px solid var(--border); background:var(--bg3);">
                <div style="display:flex; justify-content:space-between; align-items:start; margin-bottom:12px;">
                    <div>
                        <h3 id="vt-subject" style="font-size:18px; margin-bottom:4px;">...</h3>
                        <p id="vt-meta" style="font-size:12px; color:var(--text2);">...</p>
                    </div>
                    <div style="text-align:right;">
                        <span id="vt-status" class="status-pill">...</span>
                        <div id="vt-priority" style="margin-top:8px; font-size:11px; font-weight:600; text-transform:uppercase; display:flex; align-items:center; justify-content:flex-end;">
                            <span class="priority-dot" id="vt-priority-dot"></span>
                            <span id="vt-priority-text">...</span>
                        </div>
                    </div>
                </div>
                <div id="vt-description" style="padding:15px; background:var(--bg2); border:1px solid var(--border); border-radius:10px; font-size:13px; color:var(--text); line-height:1.6; white-space:pre-wrap; max-height:120px; overflow-y:auto;">
                    ...
                </div>
            </div>

            <!-- Conversation/Comments Area -->
            <div style="flex:1; overflow-y:auto; padding:24px; background:var(--bg2);" id="vt-comments-container">
                <!-- Comments injected here -->
            </div>

            <div style="padding: 20px 24px; background:var(--bg3); border-top:1px solid var(--border);">
                <form id="form-ticket-comment" method="POST">
                    <input type="hidden" name="form_section" value="tickets">
                    <input type="hidden" name="action" value="add_comment">
                    <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
                    <input type="hidden" name="ticket_id" id="vt-submit-id">
                    
                    <div style="display:flex; gap:12px; align-items:flex-end;">
                        <div style="flex:1;">
                            <textarea name="comment" rows="2" placeholder="Write a response or internal note..." style="width:100%; min-height:80px; padding:12px; border-radius:10px; border:1px solid var(--border); background:var(--bg2); color:var(--text); font-family:inherit; resize:none;"></textarea>
                            <label style="font-size:11px; color:var(--text2); display:flex; align-items:center; gap:6px; margin-top:8px; cursor:pointer;">
                                <input type="checkbox" name="is_internal" value="1"> Internal Staff Note (Requester won't see this)
                            </label>
                        </div>
                        <button type="submit" class="btn btn-primary" style="height:48px; padding:0<?php if ($can_manage_inquiries): ?>
<!-- Inquiry Management Modal -->
<div id="modal-manage-inquiry" class="modal-overlay" style="position:fixed; top:0; left:0; width:100vw; height:100vh; z-index:9999; background:rgba(0,0,0,0.5); backdrop-filter:blur(5px); align-items:center; justify-content:center; padding:20px;">
    <div class="modal-box modal" style="max-width:520px; width:100%; max-height:90vh; background:var(--bg2); color:var(--text); display:flex; flex-direction:column; border-radius:12px; box-shadow:none; border:1px solid var(--border); overflow:hidden;">
        <!-- Header -->
        <div class="modal-header" style="flex-shrink:0; padding:15px 22px; border-bottom:1px solid var(--border); background:var(--bg2); display:flex; justify-content:space-between; align-items:center;">
            <div>
               <h3 style="font-size:15px; margin:0; color:var(--text); font-family:var(--font-family, 'Inter', sans-serif); font-weight:700;">Request Management</h3>
               <p style="font-size:11px; color:var(--text2); margin-top:2px;">UID: <span id="inq-uid-display" style="font-family:monospace; color:var(--text);">#000</span></p>
            </div>
            <button class="modal-close" style="color:var(--text2); font-size:20px; background:none; border:none; cursor:pointer;" onclick="closeModal('modal-manage-inquiry')">&times;</button>
        </div>

        <form id="form-manage-inquiry" method="POST" action="dashboard.php" style="display:flex; flex-direction:column; margin:0; overflow:hidden;">
            <input type="hidden" name="form_section" value="public_inquiries">
            <input type="hidden" name="action" value="update_status">
            <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
            <input type="hidden" name="id" id="inq-id">

            <div class="modal-body" style="padding:20px; overflow-y:auto; background:var(--bg2); scrollbar-width:thin;">
                
                <!-- PARISHIONER PROFILE CARD -->
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; background:var(--bg3); padding:12px 14px; border-radius:8px; border:1px solid var(--border);">
                    <div style="display:flex; align-items:center; gap:12px;">
                        <div style="width:36px; height:36px; border-radius:8px; background:var(--bg2); display:flex; align-items:center; justify-content:center; color:var(--text); border:1px solid var(--border);">
                            <i class="fa-solid fa-user-tag" style="font-size:15px;"></i>
                        </div>
                        <div>
                            <h4 id="inq-name" style="margin:0; color:var(--text); font-size:14px; font-weight:600;"></h4>
                            <div id="inq-chapel" style="font-size:10px; color:var(--text2); text-transform:uppercase; letter-spacing:0.5px; margin-top:1px;"></div>
                        </div>
                    </div>
                    <div style="display:flex; gap:8px;">
                        <button type="button" id="btn-sms" class="btn btn-icon" style="width:32px; height:32px; background:var(--bg2); border:1px solid var(--border); color:var(--text); display:flex; align-items:center; justify-content:center; border-radius:6px;" title="Auto-Send SMS" onclick="submitInquiryQuickContact('sms')"><i class="fa-solid fa-comment-sms" style="font-size:13px;"></i></button>
                        <button type="button" id="btn-email" class="btn btn-icon" style="width:32px; height:32px; background:var(--bg2); border:1px solid var(--border); color:var(--text); display:flex; align-items:center; justify-content:center; border-radius:6px;" title="Auto-Send Email" onclick="submitInquiryQuickContact('email')"><i class="fa-solid fa-envelope" style="font-size:13px;"></i></button>
                    </div>
                </div>

                <!-- REQUEST METADATA GRID -->
                <div style="display:grid; grid-template-columns: repeat(3, 1fr); gap:10px; margin-bottom:16px;">
                    <div style="background:var(--bg3); padding:10px; border-radius:8px; border:1px solid var(--border);">
                        <label style="font-size:10px; text-transform:uppercase; color:var(--text2); font-weight:700; display:block; margin-bottom:3px;">Category</label>
                        <span id="inq-cat-badge" style="font-weight:600; color:var(--text); font-size:11px;"></span>
                    </div>
                    <div style="background:var(--bg3); padding:10px; border-radius:8px; border:1px solid var(--border);">
                        <label style="font-size:10px; text-transform:uppercase; color:var(--text2); font-weight:700; display:block; margin-bottom:3px;">Target Date</label>
                        <span id="inq-target-val" style="font-weight:600; color:var(--text); font-size:11px;"></span>
                    </div>
                    <div style="background:var(--bg3); padding:10px; border-radius:8px; border:1px solid var(--border);">
                        <label style="font-size:10px; text-transform:uppercase; color:var(--text2); font-weight:700; display:block; margin-bottom:3px;">Type</label>
                        <span id="inq-type-val" style="font-weight:600; color:var(--text); font-size:11px;"></span>
                    </div>
                </div>

                <!-- MESSAGE CONTENT / MERCH PREVIEW -->
                <div id="merch-preview-box" style="display:none; margin-bottom:16px; background:var(--bg3); padding:14px; border-radius:8px; border:1px solid var(--border);">
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <div>
                            <h5 style="color:var(--text); margin:0; font-size:10px; font-weight:700; text-transform:uppercase;"><i class="fa-solid fa-gift"></i> Merchandise Reservation</h5>
                            <p id="merch-item-line" style="font-size:13px; font-weight:600; margin-top:3px; color:var(--text);"></p>
                        </div>
                        <div style="text-align:right;">
                            <span id="merch-qty-badge" style="background:var(--bg2); color:var(--text); border:1px solid var(--border); padding:2px 8px; border-radius:6px; font-weight:600; font-size:10px;">Qty: 1</span>
                        </div>
                    </div>
                    <div style="display:flex; gap:8px; margin-top:12px; border-top:1px solid var(--border); padding-top:12px;">
                        <div style="flex:1;">
                            <button type="button" class="btn btn-primary" style="width:100%; justify-content:center; font-weight:600; font-size:11.5px;" onclick="submitMerchFulfill()">
                               <i class="fa-solid fa-bolt"></i> Auto-Process Sale
                            </button>
                        </div>
                        <button type="button" class="btn btn-gray" style="font-size:11px;" onclick="transferInquiryToMerch()" title="Take me to the full sale form">
                            <i class="fa-solid fa-arrow-right-from-bracket"></i> Manual
                        </button>
                    </div>
                </div>

                <div style="background:var(--bg3); padding:14px; border-radius:8px; border:1px solid var(--border); margin-bottom:16px;">
                    <label style="font-size:10px; text-transform:uppercase; color:var(--text2); font-weight:700; display:block; margin-bottom:6px; letter-spacing:0.5px;">Original Message</label>
                    <div id="inq-details-val" style="font-size:12px; line-height:1.5; color:var(--text); max-height:150px; overflow-y:auto;"></div>
                </div>

                <div style="background:var(--bg3); border:1px solid var(--border); border-radius:8px; padding:8px 12px; margin-bottom:16px; display:flex; align-items:center; gap:8px;">
                    <i class="fa-solid fa-circle-info" style="color:var(--text2); font-size:11px;"></i>
                    <span style="font-size:11px; color:var(--text2);">Status <strong>For Claiming</strong> keeps the request in the inbox. <strong>Completed</strong> or <strong>Cancelled</strong> will automatically archive it.</span>
                </div>
                
                <div style="display:grid; grid-template-columns: 1fr; gap:12px;">
                    <div class="form-group">
                        <label style="font-weight:600; margin-bottom:4px; display:block; font-size:11.5px; color:var(--text);">Response Status</label>
                        <select name="status" id="inq-status" class="ticket-select" style="height:36px; font-size:12.5px; width:100%; border-radius:6px; background:var(--bg); border:1px solid var(--border); color:var(--text);">
                            <option>Reviewed</option>
                            <option>For Claiming</option>
                            <option>Cancelled</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label style="font-weight:600; margin-bottom:4px; display:block; font-size:11.5px; color:var(--text);">Internal Office Notes</label>
                        <textarea name="admin_note" id="inq-note" rows="2" style="width:100%; border-radius:6px; background:var(--bg); border:1px solid var(--border); color:var(--text); padding:8px 10px; font-size:12px; outline:none; resize:none;" placeholder="Notes on follow-up calls..."></textarea>
                    </div>
                </div>
            </div>
            
            <div class="modal-footer" style="padding:14px 22px; border-top:1px solid var(--border); background:var(--bg2); display:flex; justify-content:flex-end; gap:8px;">
                <button type="button" class="btn btn-gray" style="padding:8px 16px; font-size:12px;" onclick="closeModal('modal-manage-inquiry')">Close</button>
                
                <button type="button" id="btn-inq-resolved" class="btn btn-success" style="padding:8px 16px; font-size:12px; display:none;" onclick="submitInquiryResolved()">
                    <i class="fa-solid fa-check-double"></i> Mark as Claimed
                </button>

                <button type="button" id="btn-merch-sale" class="btn btn-primary" style="padding:8px 16px; font-size:12px; display:none;" onclick="transferInquiryToMerch()">
                    <i class="fa-solid fa-cart-shopping"></i> Complete Sale
                </button>

                <button type="submit" class="btn btn-primary" style="padding:8px 18px; font-size:12px;">Update Record</button>
            </div>
        </form>ne; border-radius:10px; box-shadow:0 8px 16px var(--accent-glow); cursor:pointer;">Update Record</button>
            </div>
        </form>

        <form method="POST" id="form-quick-sms" onsubmit="return confirm('Send a quick SMS acknowledgment to this parishioner?');" style="display:none;">
            <input type="hidden" name="form_section" value="public_inquiries">
            <input type="hidden" name="action" value="send_quick_contact">
            <input type="hidden" name="contact_method" value="sms">
            <input type="hidden" name="id" id="inq-sms-id">
            <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
        </form>

        <form method="POST" id="form-quick-email" onsubmit="return confirm('Send a quick Email acknowledgment to this parishioner?');" style="display:none;">
            <input type="hidden" name="form_section" value="public_inquiries">
            <input type="hidden" name="action" value="send_quick_contact">
            <input type="hidden" name="contact_method" value="email">
            <input type="hidden" name="id" id="inq-email-id">
            <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
        </form>
    </div>
</div>
<?php endif; ?>

<?php if ($isMember): ?>
<!-- Read-only Inquiry Modal for Members -->
<div id="modal-view-inquiry" class="modal-overlay" style="position:fixed; top:0; left:0; width:100vw; height:100vh; z-index:9999; background:rgba(0,0,0,0.5); backdrop-filter:blur(5px); align-items:center; justify-content:center; padding:20px;">
    <div class="modal-box modal" style="max-width:520px; width:100%; max-height:90vh; background:var(--bg2); color:var(--text); display:flex; flex-direction:column; border-radius:12px; box-shadow:none; border:1px solid var(--border); overflow:hidden;">
        <div class="modal-header" style="flex-shrink:0; padding:16px 20px; border-bottom:1px solid var(--border); background:var(--bg2); display:flex; justify-content:space-between; align-items:center;">
            <div>
               <h3 style="font-size:15px; margin:0; color:var(--text); font-family:var(--font-family, 'Inter', sans-serif); font-weight:700; letter-spacing:-0.2px;">Request Details</h3>
               <p style="font-size:11px; color:var(--text2); margin-top:2px;">UID: <span id="view-inq-uid-display" style="font-family:monospace; color:var(--text);">#000</span></p>
            </div>
            <button class="modal-close" style="color:var(--text2); font-size:20px; background:none; border:none; cursor:pointer;" onclick="closeModal('modal-view-inquiry')">&times;</button>
        </div>

        <div class="modal-body" style="padding:20px; overflow-y:auto; background:var(--bg2); scrollbar-width:thin;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; background:var(--bg3); padding:12px 16px; border-radius:8px; border:1px solid var(--border);">
                <div style="display:flex; align-items:center; gap:12px;">
                    <div style="width:36px; height:36px; border-radius:8px; background:var(--bg2); display:flex; align-items:center; justify-content:center; color:var(--text); border:1px solid var(--border);">
                        <i class="fa-solid fa-file-lines" style="font-size:14px;"></i>
                    </div>
                    <div>
                        <h4 id="view-inq-name" style="margin:0; color:var(--text); font-size:14px; font-weight:700; font-family:var(--font-family, 'Inter', sans-serif);"></h4>
                        <div id="view-inq-chapel" style="font-size:11px; color:var(--text2); text-transform:uppercase; letter-spacing:0.5px; margin-top:1px;"></div>
                    </div>
                </div>
                <span id="view-inq-status" class="badge badge-pending"></span>
            </div>

            <div style="display:grid; grid-template-columns: repeat(3, 1fr); gap:10px; margin-bottom:16px;">
                <div style="background:var(--bg3); padding:10px; border-radius:8px; border:1px solid var(--border);">
                    <label style="font-size:10px; text-transform:uppercase; color:var(--text2); font-weight:700; display:block; margin-bottom:3px;">Category</label>
                    <span id="view-inq-cat-badge" style="font-weight:600; color:var(--text); font-size:12px;"></span>
                </div>
                <div style="background:var(--bg3); padding:10px; border-radius:8px; border:1px solid var(--border);">
                    <label style="font-size:10px; text-transform:uppercase; color:var(--text2); font-weight:700; display:block; margin-bottom:3px;">Target Date</label>
                    <span id="view-inq-target-val" style="font-weight:600; color:var(--text); font-size:12px;"></span>
                </div>
                <div style="background:var(--bg3); padding:10px; border-radius:8px; border:1px solid var(--border);">
                    <label style="font-size:10px; text-transform:uppercase; color:var(--text2); font-weight:700; display:block; margin-bottom:3px;">Type</label>
                    <span id="view-inq-type-val" style="font-weight:600; color:var(--text); font-size:12px;"></span>
                </div>
            </div>

            <div style="background:var(--bg3); padding:14px; border-radius:8px; border:1px solid var(--border); margin-bottom:16px;">
                <label style="font-size:10px; text-transform:uppercase; color:var(--text2); font-weight:700; display:block; margin-bottom:6px; letter-spacing:0.5px;">Original Message</label>
                <div id="view-inq-details-val" style="font-size:13px; line-height:1.5; color:var(--text); transition:all 0.3s; max-height:150px; overflow-y:auto; scrollbar-width:none;"></div>
            </div>

            <div style="background:var(--bg3); padding:14px; border-radius:8px; border:1px solid var(--border);">
                <label style="font-size:10px; text-transform:uppercase; color:var(--text2); font-weight:700; display:block; margin-bottom:6px; letter-spacing:0.5px;">Office Response</label>
                <div id="view-inq-note" style="font-size:13px; line-height:1.5; color:var(--text); min-height:20px;"></div>
            </div>
        </div>

        <div class="modal-footer" style="padding:14px 20px; border-top:1px solid var(--border); background:var(--bg2); display:flex; justify-content:flex-end; gap:10px;">
            <button type="button" class="btn btn-gray" style="border-radius:8px;" onclick="closeModal('modal-view-inquiry')">Close</button>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- MODAL: Member Merchandise Order -->
<div class="modal-overlay" id="modal-member-reserve">
    <div class="modal" style="max-width: 450px; border-radius: 12px; border: 1px solid #E5E5E5; box-shadow: 0 10px 30px rgba(0,0,0,0.1);">
        <div class="modal-header" style="border-bottom: 1px solid #E5E5E5; padding: 16px 20px;">
            <div class="modal-title" style="font-family: var(--font-family, 'Inter', sans-serif); font-weight: 700; font-size: 15px; color: #111111;"><i class="fa-solid fa-cart-shopping"></i> Item Reservation</div>
            <button class="modal-close" onclick="closeModal('modal-member-reserve')">×</button>
        </div>
        <form method="POST">
            <input type="hidden" name="form_section" value="merchandise">
            <input type="hidden" name="action" value="member_reserve">
            <input type="hidden" name="id" id="member-res-item-id">
            <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
            <div class="modal-body" style="padding: 20px;">
                <div style="background: #F6F6F6; padding: 14px 16px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #E5E5E5;">
                    <div id="member-res-item-name" style="font-weight: 700; font-size: 15px; color: #111111;">Item Name</div>
                    <div id="member-res-item-price" style="font-size: 13px; color: #737373; margin-top: 2px;">₱0.00</div>
                </div>

                <div class="form-group mb-16">
                    <label style="font-size: 12px; font-weight: 600; color: #111111; margin-bottom: 6px; display: block;">Quantity</label>
                    <input type="number" name="quantity" id="member-res-qty" value="1" min="1" required style="width: 100%; border: 1px solid #E5E5E5; border-radius: 8px; padding: 10px;">
                </div>

                <div class="form-group mb-16">
                    <label style="font-size: 12px; font-weight: 600; color: #111111; margin-bottom: 6px; display: block;">Pickup Location</label>
                    <select name="chapel_id" required style="width: 100%; border: 1px solid #E5E5E5; border-radius: 8px; padding: 10px;">
                        <option value="0">IFI Cathedral (Main)</option>
                        <?php foreach ($chapels as $ch): ?>
                        <option value="<?php echo $ch['chapel_id']; ?>"><?php echo htmlspecialchars($ch['chapel_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="alert alert-info" style="font-size: 12px; margin-top: 10px; background: #F6F6F6; border: 1px solid #E5E5E5; color: #737373; border-radius: 8px;">
                    <i class="fa-solid fa-circle-info" style="color: #111111;"></i> Your reservation will be sent to the parish office. You will be notified via email when it's ready for pickup.
                </div>
            </div>
            <div class="modal-footer" style="border-top: 1px solid #E5E5E5; padding: 14px 20px;">
                <button type="submit" class="btn btn-primary" style="border-radius: 8px;"><i class="fa-solid fa-check"></i> Place Reservation</button>
                <button type="button" class="btn btn-gray" style="border-radius: 8px;" onclick="closeModal('modal-member-reserve')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL: View Donation Details -->
<div class="modal-overlay" id="modal-view-donation">
    <div class="modal" style="max-width:500px; border-radius: 12px; border: 1px solid #E5E5E5; box-shadow: 0 10px 30px rgba(0,0,0,0.1);">
        <div class="modal-header" style="background:#FFFFFF; border-bottom:1px solid #E5E5E5; padding: 16px 20px;">
            <div class="modal-title" style="font-family: var(--font-family, 'Inter', sans-serif); font-weight: 700; font-size: 15px; color: #111111;"><i class="fa-solid fa-receipt"></i> Donation Details</div>
            <button class="modal-close" onclick="closeModal('modal-view-donation')">×</button>
        </div>
        <div class="modal-body" id="detail-donation-body" style="padding: 20px;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; background:#F6F6F6; padding:14px 16px; border-radius:8px; border:1px solid #E5E5E5;">
                <div>
                    <div style="font-size:10px; color:#737373; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:4px; font-weight: 700;">Reference ID</div>
                    <div id="det-don-ref" style="font-size:16px; font-weight:700; color:#111111; font-family:monospace;">—</div>
                </div>
                <div style="text-align:right;">
                    <div style="font-size:10px; color:#737373; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:4px; font-weight: 700;">Amount</div>
                    <div id="det-don-amount" style="font-size:20px; font-weight:800; color:#111111;">₱0.00</div>
                </div>
            </div>

            <div class="detail-grid" style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                <div>
                    <label style="font-size:10px; text-transform:uppercase; color:#737373; font-weight:700; display:block; margin-bottom:4px;">Donor Name</label>
                    <div id="det-don-name" style="font-weight:600; color: #111111; font-size: 13px;">—</div>
                </div>
                <div>
                    <label style="font-size:10px; text-transform:uppercase; color:#737373; font-weight:700; display:block; margin-bottom:4px;">Date Received</label>
                    <div id="det-don-date" style="font-size: 13px; color: #111111;">—</div>
                </div>
                <div>
                    <label style="font-size:10px; text-transform:uppercase; color:#737373; font-weight:700; display:block; margin-bottom:4px;">Payment Method</label>
                    <div id="det-don-method" style="font-size: 13px; color: #111111;">—</div>
                </div>
                <div>
                    <label style="font-size:10px; text-transform:uppercase; color:#737373; font-weight:700; display:block; margin-bottom:4px;">Location</label>
                    <div id="det-don-chapel" style="font-size: 13px; color: #111111;">—</div>
                </div>
                <div style="grid-column:1/-1;">
                    <label style="font-size:10px; text-transform:uppercase; color:#737373; font-weight:700; display:block; margin-bottom:4px;">Purpose / Notes</label>
                    <div id="det-don-purpose" style="background:#F6F6F6; padding:10px 12px; border-radius:8px; border:1px solid #E5E5E5; font-size:12px; min-height:40px; color: #111111;">—</div>
                </div>
                <div>
                    <label style="font-size:10px; text-transform:uppercase; color:#737373; font-weight:700; display:block; margin-bottom:4px;">Logged By</label>
                    <div id="det-don-logger" style="font-size:12px; color:#737373;">—</div>
                </div>
            </div>
        </div>
        <div class="modal-footer" style="background:#FFFFFF; border-top:1px solid #E5E5E5; padding: 14px 20px;">
            <button type="button" class="btn btn-gray" style="border-radius: 8px;" onclick="closeModal('modal-view-donation')">Close</button>
            <a id="det-don-print-btn" href="#" class="btn btn-primary" style="border-radius: 8px;"><i class="fa-solid fa-print"></i> Print Receipt</a>
        </div>
    </div>
</div>

<!-- MODAL: View Merchandise Sale Details -->
<div class="modal-overlay" id="modal-view-merch-sale">
    <div class="modal" style="max-width:500px; border-radius: 12px; border: 1px solid #E5E5E5; box-shadow: 0 10px 30px rgba(0,0,0,0.1);">
        <div class="modal-header" style="background:#FFFFFF; border-bottom:1px solid #E5E5E5; padding: 16px 20px;">
            <div class="modal-title" style="font-family: var(--font-family, 'Inter', sans-serif); font-weight: 700; font-size: 15px; color: #111111;"><i class="fa-solid fa-cash-register"></i> Sale Details</div>
            <button class="modal-close" onclick="closeModal('modal-view-merch-sale')">×</button>
        </div>
        <div class="modal-body" style="padding: 20px;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; background:#F6F6F6; padding:14px 16px; border-radius:8px; border:1px solid #E5E5E5;">
                <div>
                    <div style="font-size:10px; color:#737373; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:4px; font-weight: 700;">Item Name</div>
                    <div id="det-sale-item" style="font-size:15px; font-weight:700; color:#111111;">—</div>
                </div>
                <div style="text-align:right;">
                    <div style="font-size:10px; color:#737373; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:4px; font-weight: 700;">Total Price</div>
                    <div id="det-sale-total" style="font-size:20px; font-weight:800; color:#111111;">₱0.00</div>
                </div>
            </div>

            <div class="detail-grid" style="display:grid; grid-template-columns:1fr 1fr; gap:20px;">
                <div>
                    <label style="font-size:9px; text-transform:uppercase; color:var(--text2); font-weight:800; display:block; margin-bottom:5px;">Buyer Name</label>
                    <div id="det-sale-buyer" style="font-weight:600;">—</div>
                </div>
                <div>
                    <label style="font-size:9px; text-transform:uppercase; color:var(--text2); font-weight:800; display:block; margin-bottom:5px;">Sale Date</label>
                    <div id="det-sale-date">—</div>
                </div>
                <div>
                    <label style="font-size:9px; text-transform:uppercase; color:var(--text2); font-weight:800; display:block; margin-bottom:5px;">Quantity</label>
                    <div id="det-sale-qty">—</div>
                </div>
                <div>
                    <label style="font-size:9px; text-transform:uppercase; color:var(--text2); font-weight:800; display:block; margin-bottom:5px;">Unit Price</label>
                    <div id="det-sale-unit">—</div>
                </div>
                <div>
                    <label style="font-size:9px; text-transform:uppercase; color:var(--text2); font-weight:800; display:block; margin-bottom:5px;">Processed By</label>
                    <div id="det-sale-logger" style="font-size:11px; color:var(--text2);">—</div>
                </div>
            </div>
        </div>
        <div class="modal-footer" style="background:var(--bg3); border-top:1px solid var(--border);">
            <button type="button" class="btn btn-gray" onclick="closeModal('modal-view-merch-sale')">Close</button>
            <a id="det-sale-print-btn" href="#" class="btn btn-primary"><i class="fa-solid fa-print"></i> Print Receipt</a>
        </div>
    </div>
</div>

<!-- MODAL: View Audit Log Details -->
<div class="modal-overlay" id="modal-view-audit">
    <div class="modal" style="max-width:600px;">
        <div class="modal-header" style="background:var(--bg3); border-bottom:1px solid var(--border);">
            <div class="modal-title"><i class="fa-solid fa-list-check"></i> Audit Details</div>
            <button class="modal-close" onclick="closeModal('modal-view-audit')">×</button>
        </div>
        <div class="modal-body">
            <div style="background:var(--bg3); padding:15px; border-radius:12px; border:1px solid var(--border); margin-bottom:20px; display:flex; justify-content:space-between; align-items:center;">
                <div>
                    <div style="font-size:9px; text-transform:uppercase; color:var(--text2); font-weight:800; margin-bottom:4px;">Action</div>
                    <span id="det-audit-action" class="badge">Created</span>
                </div>
                <div style="text-align:center;">
                    <div style="font-size:9px; text-transform:uppercase; color:var(--text2); font-weight:800; margin-bottom:4px;">Module</div>
                    <div id="det-audit-module" style="font-weight:700;">Events</div>
                </div>
                <div style="text-align:right;">
                    <div style="font-size:9px; text-transform:uppercase; color:var(--text2); font-weight:800; margin-bottom:4px;">Timestamp</div>
                    <div id="det-audit-time" style="font-size:11px; color:var(--text2);">—</div>
                </div>
            </div>

            <div style="margin-bottom:20px;">
                <label style="font-size:9px; text-transform:uppercase; color:var(--text2); font-weight:800; display:block; margin-bottom:5px;">Target Description</label>
                <div id="det-audit-target" style="font-weight:600; font-size:14px; padding:10px; background:var(--bg3); border-radius:8px; border:1px solid var(--border);">—</div>
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px; margin-bottom:20px;">
                <div>
                    <label style="font-size:9px; text-transform:uppercase; color:var(--text2); font-weight:800; display:block; margin-bottom:5px;">Performed By</label>
                    <div id="det-audit-user" style="font-weight:600;">—</div>
                    <div id="det-audit-role" style="font-size:10px; color:var(--text2); text-transform:uppercase;">—</div>
                </div>
                <div>
                    <label style="font-size:9px; text-transform:uppercase; color:var(--text2); font-weight:800; display:block; margin-bottom:5px;">IP Address</label>
                    <div id="det-audit-ip" style="font-family:monospace; font-size:12px;">—</div>
                </div>
            </div>

            <div id="det-audit-diff-container" style="display:none;">
                <label style="font-size:9px; text-transform:uppercase; color:var(--text2); font-weight:800; display:block; margin-bottom:8px;">Changes (JSON)</label>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                    <div>
                        <div style="font-size:10px; color:var(--text2); margin-bottom:4px;">Before</div>
                        <pre id="det-audit-old" style="background:#000; color:#ef4444; padding:10px; border-radius:8px; font-size:10px; overflow-x:auto; max-height:200px; border:1px solid #450a0a;"></pre>
                    </div>
                    <div>
                        <div style="font-size:10px; color:var(--text2); margin-bottom:4px;">After</div>
                        <pre id="det-audit-new" style="background:#000; color:#22c55e; padding:10px; border-radius:8px; font-size:10px; overflow-x:auto; max-height:200px; border:1px solid #064e3b;"></pre>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-gray" onclick="closeModal('modal-view-audit')">Close</button>
        </div>
    </div>
</div>

<!-- ── MODAL: GUEST SPECTATOR AUTHENTICATION GATE ── -->
<div class="modal-overlay" id="modal-guest-auth" style="align-items: center; justify-content: center; z-index: 99999;">
    <div class="modal" style="max-width: 500px; width: 92%; border-radius: 16px; background: var(--surface, #ffffff); border: 1px solid var(--border); box-shadow: 0 20px 50px rgba(0,0,0,0.3); overflow: hidden; display: flex; flex-direction: column;">
        <!-- Top Gold Accent Ribbon -->
        <div style="height: 4px; background: linear-gradient(90deg, #c5a059, #f3e3ba, #c5a059);"></div>
        
        <div class="modal-header" style="padding: 18px 22px 14px; border-bottom: 1px solid var(--border); display: flex; align-items: flex-start; justify-content: space-between; gap: 14px; background: var(--surface);">
            <div style="display: flex; align-items: center; gap: 12px;">
                <div style="width: 42px; height: 42px; border-radius: 10px; background: rgba(197, 160, 89, 0.15); color: var(--accent, #c5a059); display: flex; align-items: center; justify-content: center; font-size: 19px; flex-shrink: 0; border: 1px solid rgba(197,160,89,0.3);">
                    <i class="fa-solid fa-id-card-clip"></i>
                </div>
                <div>
                    <div class="modal-title" style="font-family: var(--font-family, 'Inter', sans-serif); font-size: 1.05rem; font-weight: 700; color: var(--text); margin: 0; line-height: 1.25;">
                        Parishioner Membership Required
                    </div>
                    <div style="font-size: 12px; color: var(--text2); margin-top: 3px;" id="guest-modal-subtitle">
                        Please sign in or register to submit requests and applications.
                    </div>
                </div>
            </div>
            <button type="button" class="modal-close" onclick="closeModal('modal-guest-auth')" style="font-size: 22px; cursor: pointer; border: none; background: transparent; color: var(--text2); line-height: 1; padding: 2px 6px; border-radius: 6px;">×</button>
        </div>

        <div class="modal-body" style="padding: 20px 22px; display: flex; flex-direction: column; gap: 16px; background: var(--surface);">
            <div style="background: rgba(197, 160, 89, 0.08); border: 1px solid rgba(197, 160, 89, 0.22); border-radius: 10px; padding: 12px 14px;">
                <div style="font-size: 12px; line-height: 1.5; color: var(--text);">
                    <strong style="color: var(--accent, #c5a059);"><i class="fa-solid fa-shield-halved"></i> Why is an account required?</strong>
                    <p style="margin: 4px 0 0; color: var(--text2); font-size: 11.5px; line-height: 1.45;">
                        Sacramental certificates and parish requests are official ecclesiastical records. A verified account secures identity privacy, ensures canonical record lookup, and allows you to track pickup readiness in real time.
                    </p>
                </div>
            </div>

            <!-- Features Checklist -->
            <div style="display: flex; flex-direction: column; gap: 9px; font-size: 12px; color: var(--text);">
                <div style="display: flex; align-items: center; gap: 9px;">
                    <i class="fa-solid fa-circle-check" style="color: #10b981; font-size: 13px; flex-shrink: 0;"></i>
                    <span><strong>Live Request Tracking:</strong> Follow status from archival search to signed &amp; sealed.</span>
                </div>
                <div style="display: flex; align-items: center; gap: 9px;">
                    <i class="fa-solid fa-circle-check" style="color: #10b981; font-size: 13px; flex-shrink: 0;"></i>
                    <span><strong>Canonical Privacy:</strong> Confidential records are securely tied to verified parishioners.</span>
                </div>
                <div style="display: flex; align-items: center; gap: 9px;">
                    <i class="fa-solid fa-circle-check" style="color: #10b981; font-size: 13px; flex-shrink: 0;"></i>
                    <span><strong>Pickup Notifications:</strong> Receive immediate email &amp; SMS alerts when documents are ready.</span>
                </div>
            </div>
        </div>

        <div class="modal-footer" style="padding: 14px 22px; background: var(--bg2, #fbfbfb); border-top: 1px solid var(--border); display: flex; flex-direction: column; gap: 10px;">
            <div style="display: flex; gap: 10px; width: 100%;">
                <a href="register.php" class="btn" style="flex: 1; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; gap: 7px; background: #111111; color: #ffffff; padding: 9px 14px; border-radius: 8px; font-size: 12.5px; font-weight: 600; box-shadow: 0 1px 3px rgba(0,0,0,0.15); transition: all 0.15s ease;">
                    <i class="fa-solid fa-user-plus text-gold"></i> Register as Member
                </a>
                <a href="logout.php?redirect=index.php?open_login=1" class="btn" style="flex: 1; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; gap: 7px; background: #ffffff; color: var(--text); border: 1px solid var(--border); padding: 9px 14px; border-radius: 8px; font-size: 12.5px; font-weight: 600; transition: all 0.15s ease;">
                    <i class="fa-solid fa-arrow-right-to-bracket text-gold"></i> Sign In
                </a>
            </div>
            <button type="button" onclick="closeModal('modal-guest-auth')" style="background: transparent; border: none; font-size: 11.5px; color: var(--text2); cursor: pointer; padding: 2px; text-align: center; text-decoration: underline;">
                Continue spectating as Guest
            </button>
        </div>
    </div>
</div>

