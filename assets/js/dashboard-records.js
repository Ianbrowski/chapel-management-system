function toggleRecordFields(uid) {
    const typeSelect = document.getElementById(uid + '-rec-type');
    if (!typeSelect) return;
    const type = typeSelect.value;

    // Update active pill button visual state
    const pills = document.querySelectorAll('#' + uid + '-pills .sacrament-pill-btn');
    pills.forEach(p => {
        const isMatch = (p.getAttribute('data-type') === type);
        p.classList.toggle('active', isMatch);
    });

    const activeTypeLabel = document.getElementById(uid + '-active-type-label');
    if (activeTypeLabel) {
        activeTypeLabel.textContent = type ? (type.toUpperCase() + ' RECORD PARTICULARS') : 'RECORD PARTICULARS';
    }

    const sections = ['Baptism', 'Wedding', 'Funeral', 'Confirmation', 'Communion'];
    sections.forEach(s => {
        const el = document.getElementById(uid + '-type-' + s);
        if (el) el.style.display = 'none';
    });
    const family = document.getElementById(uid + '-section-family');
    const sponsors = document.getElementById(uid + '-section-sponsors');
    if (family) family.style.display = 'none';
    if (sponsors) sponsors.style.display = 'none';

    if (type === 'Baptism') {
        if (family) family.style.display = 'block';
        if (sponsors) sponsors.style.display = 'block';
        const el = document.getElementById(uid + '-type-Baptism');
        if (el) el.style.display = 'block';
    } else if (type === 'Confirmation') {
        if (family) family.style.display = 'block';
        if (sponsors) sponsors.style.display = 'block';
        const conEl = document.getElementById(uid + '-type-Confirmation');
        if (conEl) conEl.style.display = 'block';
    } else if (type === 'Communion') {
        if (family) family.style.display = 'block';
    } else if (type === 'Wedding') {
        const el = document.getElementById(uid + '-type-Wedding');
        if (el) el.style.display = 'block';
    } else if (type === 'Funeral') {
        const el = document.getElementById(uid + '-type-Funeral');
        if (el) el.style.display = 'block';
    }
}

function selectSacramentPill(uid, type) {
    const typeSelect = document.getElementById(uid + '-rec-type');
    if (typeSelect) {
        typeSelect.value = type;
        toggleRecordFields(uid);
    }
}
window.selectSacramentPill = selectSacramentPill;

function openEditRecordModal(r, scope) {
    const prefix = (scope === 'cathedral' ? 'cath' : 'chap');
    const modalId = (scope === 'cathedral' ? 'modal-edit-cath-record' : 'modal-edit-chap-record');

    const idField = document.getElementById(scope === 'cathedral' ? 'edit-cath-rec-id' : 'edit-chap-rec-id');
    if (idField) idField.value = r.record_id;

    let cleanNotes = (r.notes || '').trim();
    if (cleanNotes.startsWith('{') && cleanNotes.endsWith('}')) {
        try {
            const parsed = JSON.parse(cleanNotes);
            cleanNotes = (parsed.marginal_notes || parsed.remarks || '').trim();
        } catch(e) {
            cleanNotes = '';
        }
    }

    const baseFields = {
        '-rec-type': r.record_type,
        '-person-name': r.person_name,
        '-event-date': r.event_date,
        '-chapel': (r.chapel_id === null || r.chapel_id === undefined || r.chapel_id === '' || r.chapel_id === '0')
            ? ''
            : r.chapel_id,
        '-priest': r.officiating_priest || '',
        '-book': r.book_number || '',
        '-page': r.page_number || '',
        '-notes': cleanNotes
    };

    for (const [suffix, val] of Object.entries(baseFields)) {
        const el = document.getElementById(prefix + suffix);
        if (el) el.value = val;
    }

    const fatherField = document.getElementById(prefix + '-father');
    if (fatherField) fatherField.value = r.father_name || '';
    const motherField = document.getElementById(prefix + '-mother');
    if (motherField) motherField.value = r.mother_name || '';
    const godfatherField = document.getElementById(prefix + '-godfather');
    if (godfatherField) godfatherField.value = r.godfather || '';
    const godmotherField = document.getElementById(prefix + '-godmother');
    if (godmotherField) godmotherField.value = r.godmother || '';

    if (r.record_type === 'Baptism') {
        if (document.getElementById(prefix + '-bap-child')) {
            document.getElementById(prefix + '-bap-child').setAttribute('value', r.child_name || '');
            document.getElementById(prefix + '-bap-child').value = r.child_name || '';
        }
        if (document.getElementById(prefix + '-bap-dob')) document.getElementById(prefix + '-bap-dob').value = r.date_of_birth || '';
        if (document.getElementById(prefix + '-bap-pob')) document.getElementById(prefix + '-bap-pob').value = r.place_of_birth || '';
        if (document.getElementById(prefix + '-bap-res')) document.getElementById(prefix + '-bap-res').value = r.baptism_parents_residence || '';
        if (document.getElementById(prefix + '-bap-legit')) document.getElementById(prefix + '-bap-legit').value = r.baptism_legitimacy || 'Legitimate';
    } else if (r.record_type === 'Wedding') {
        if (document.getElementById(prefix + '-wed-groom')) document.getElementById(prefix + '-wed-groom').value = r.groom_name || '';
        if (document.getElementById(prefix + '-wed-gp')) document.getElementById(prefix + '-wed-gp').value = r.wedding_groom_parents || '';
        if (document.getElementById(prefix + '-wed-bride')) document.getElementById(prefix + '-wed-bride').value = r.bride_name || '';
        if (document.getElementById(prefix + '-wed-bp')) document.getElementById(prefix + '-wed-bp').value = r.wedding_bride_parents || '';
        if (document.getElementById(prefix + '-wed-date')) document.getElementById(prefix + '-wed-date').value = r.date_of_marriage || '';
        if (document.getElementById(prefix + '-wed-loc')) document.getElementById(prefix + '-wed-loc').value = r.place_of_marriage || '';
        if (document.getElementById(prefix + '-wed-w1')) document.getElementById(prefix + '-wed-w1').value = r.witness_1 || '';
        if (document.getElementById(prefix + '-wed-w2')) document.getElementById(prefix + '-wed-w2').value = r.witness_2 || '';
        
        let wedMeta = {};
        if (r.notes && r.notes.trim().startsWith('{')) {
            try { wedMeta = JSON.parse(r.notes); } catch(e) {}
        }
        if (document.getElementById(prefix + '-wed-lic-no')) document.getElementById(prefix + '-wed-lic-no').value = wedMeta.marriage_license_no || '';
        if (document.getElementById(prefix + '-wed-lic-date')) document.getElementById(prefix + '-wed-lic-date').value = wedMeta.license_date_issued || wedMeta.date_of_issuance || '';
        if (document.getElementById(prefix + '-wed-lic-place')) document.getElementById(prefix + '-wed-lic-place').value = wedMeta.license_place_issued || wedMeta.place_of_issuance || '';
        if (document.getElementById(prefix + '-wed-groom-civ')) document.getElementById(prefix + '-wed-groom-civ').value = wedMeta.groom_civil_status || 'Single';
        if (document.getElementById(prefix + '-wed-bride-civ')) document.getElementById(prefix + '-wed-bride-civ').value = wedMeta.bride_civil_status || 'Single';
    } else if (r.record_type === 'Funeral') {
        if (document.getElementById(prefix + '-fun-dec')) document.getElementById(prefix + '-fun-dec').value = r.deceased_name || '';
        if (document.getElementById(prefix + '-fun-dod')) document.getElementById(prefix + '-fun-dod').value = r.date_of_death || '';
        if (document.getElementById(prefix + '-fun-age')) document.getElementById(prefix + '-fun-age').value = r.age_at_death || 0;
        if (document.getElementById(prefix + '-fun-marital')) document.getElementById(prefix + '-fun-marital').value = r.funeral_marital_status || 'Single';
        if (document.getElementById(prefix + '-fun-occ')) document.getElementById(prefix + '-fun-occ').value = r.funeral_occupation || '';
        if (document.getElementById(prefix + '-fun-cause')) document.getElementById(prefix + '-fun-cause').value = r.funeral_cause_of_death || '';
        if (document.getElementById(prefix + '-fun-date-mass')) document.getElementById(prefix + '-fun-date-mass').value = r.date_of_funeral || '';
        if (document.getElementById(prefix + '-fun-burial')) document.getElementById(prefix + '-fun-burial').value = r.place_of_burial || '';
    } else if (r.record_type === 'Confirmation') {
        if (document.getElementById(prefix + '-con-pobap')) document.getElementById(prefix + '-con-pobap').value = r.confirmation_place_of_baptism || '';
    }

    toggleRecordFields(prefix);
    openModal(modalId);
}

function syncIssueCertPurpose() {
    const sel = document.getElementById('issue-cert-purpose-select');
    const custom = document.getElementById('issue-cert-purpose-custom');
    const hidden = document.getElementById('issue-cert-purpose');
    if (!sel || !hidden) return;
    const isCustom = (sel.value === '__custom__');
    if (custom) {
        custom.style.display = isCustom ? 'block' : 'none';
        custom.required = isCustom;
    }
    hidden.value = isCustom
        ? ((custom && custom.value.trim()) ? custom.value.trim() : '')
        : sel.value;
}

function openCertModal(r, scope) {
    document.getElementById('issue-cert-section').value = (scope === 'cathedral' ? 'records_cathedral' : 'records_chapel');
    document.getElementById('issue-cert-record-id').value = r.record_id;
    document.getElementById('issue-cert-person-name').textContent = r.person_name;
    document.getElementById('issue-cert-type').textContent = r.record_type;
    document.getElementById('issue-cert-issued-to').value = '';
    const sel = document.getElementById('issue-cert-purpose-select');
    const custom = document.getElementById('issue-cert-purpose-custom');
    if (sel) sel.value = 'Personal Records';
    if (custom) custom.value = '';
    syncIssueCertPurpose();
    openModal('modal-issue-cert');
}

window.syncIssueCertPurpose = syncIssueCertPurpose;

function previewCert() {
    const recordId = document.getElementById('issue-cert-record-id').value;
    if (recordId) {
        window.location.href = 'certificates/sacramental_certificate.php?record_id=' + recordId + '&preview=1';
    }
}

function openNotifyModal(target, scope) {
    const sectionValue = typeof scope === 'string'
        ? (scope === 'cathedral' ? 'records_cathedral' : 'records_chapel')
        : String(target || 'records_cathedral');
    document.getElementById('notify-pickup-section').value = sectionValue;

    if (target && typeof target === 'object') {
        const formOptions = document.querySelector('#modal-notify-pickup select[name="document_type"]').options;
        for (let opt of formOptions) {
            if (opt.value.includes(target.record_type)) {
                opt.selected = true;
                break;
            }
        }
    }

    openModal('modal-notify-pickup');
}

function escapeHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function getSacramentBadgeHtml(type) {
    const icons = {
        'Baptism': 'fa-water',
        'Wedding': 'fa-ring',
        'Funeral': 'fa-cross',
        'Confirmation': 'fa-dove',
        'Communion': 'fa-wine-glass',
    };
    const icon = icons[type] || 'fa-scroll';
    return `<span class="badge" style="background: #F6F6F6; color: #111111; border: 1px solid #E5E5E5; font-weight: 600; font-size: 10.5px; padding: 2px 7px; border-radius: 6px; display: inline-flex; align-items: center; gap: 4px; margin: 0;"><i class="fa-solid ${icon}" style="font-size: 10px; color: #737373;"></i> ${escapeHtml(type)}</span>`;
}

function showRecordDetails(rec, rowElement) {
    if (!rec) return;
    window.currentSelectedRecord = rec;
    
    // Highlight selected row in table
    document.querySelectorAll('.record-row').forEach(row => row.classList.remove('active-row'));
    if (rowElement) {
        rowElement.classList.add('active-row');
    }

    const inspector = document.getElementById('workspace-record-inspector');
    if (!inspector) return;

    // Date formatting helper
    const formatDate = (dateStr) => {
        if (!dateStr) return '—';
        const d = new Date(dateStr.replace(/-/g, '/'));
        return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
    };

    const certUrl = `certificates/sacramental_certificate.php?record_id=${rec.record_id}&preview=1`;
    const userRolePermissions = window.userRolePermissions || { can_delete: true, can_edit: true };
    const sacramentalCsrf = window.sacramentalCsrf || '';
    const recordsScope = window.recordsScope || (rec.chapel_id && rec.chapel_id != 0 ? 'chapel' : 'cathedral');

    // Action: Edit Record
    let editBtn = '';
    if (userRolePermissions.can_edit) {
        editBtn = `
            <button type="button" class="btn-edit-primary" onclick="typeof openOfficialRecordEdit === 'function' ? openOfficialRecordEdit(window.currentSelectedRecord, '${recordsScope}') : openEditRecordModal(window.currentSelectedRecord, '${recordsScope}')" style="height: 34px; padding: 0 10px; font-size: 11.5px; font-weight: 600; display: inline-flex; align-items: center; justify-content: center; gap: 6px; background: #111111; color: #FFFFFF; border: 1px solid #111111; border-radius: 6px; cursor: pointer; text-decoration: none;">
                <i class="fa-solid fa-pen-to-square"></i> Edit Record
            </button>
        `;
    }

    // Action: Delete Record
    let deleteBtn = '';
    if (userRolePermissions.can_delete) {
        deleteBtn = `
            <form method="POST" style="margin: 4px 0 0; width: 100%; text-align: center;" onsubmit="return confirm('Danger: Are you sure you want to permanently delete this canonical sacramental record?')">
                <input type="hidden" name="form_section" value="records_${recordsScope}">
                <input type="hidden" name="action" value="delete_record">
                <input type="hidden" name="id" value="${rec.record_id}">
                <input type="hidden" name="csrf" value="${sacramentalCsrf}">
                <button type="submit" style="background: none; border: none; color: #DC2626; font-size: 11px; font-weight: 500; cursor: pointer; padding: 2px 6px; display: inline-flex; align-items: center; gap: 4px;">
                    <i class="fa-solid fa-trash-can" style="font-size: 10px;"></i> Delete Record
                </button>
            </form>
        `;
    }

    // Dynamic Sacrament-specific fields
    let specificHtml = '';
    if (rec.record_type === 'Baptism') {
        specificHtml = `
            <div class="inspector-section-title">
                <i class="fa-solid fa-users" style="font-size: 11px; color: #737373;"></i> Canonical Parents &amp; Sponsors
            </div>
            <div class="inspector-field-row">
                <span class="inspector-field-label">Father's Name</span>
                <span class="inspector-field-val">${escapeHtml(rec.father_name) || '—'}</span>
            </div>
            <div class="inspector-field-row">
                <span class="inspector-field-label">Mother's Name</span>
                <span class="inspector-field-val">${escapeHtml(rec.mother_name) || '—'}</span>
            </div>
            <div class="inspector-field-row">
                <span class="inspector-field-label">Godparents / Sponsors</span>
                <span class="inspector-field-val">${escapeHtml(rec.sponsor_names || rec.godfather || rec.godmother) || '—'}</span>
            </div>
            ${rec.date_of_birth ? `
            <div class="inspector-field-row">
                <span class="inspector-field-label">Date of Birth</span>
                <span class="inspector-field-val">${formatDate(rec.date_of_birth)}</span>
            </div>` : ''}
            ${rec.place_of_birth ? `
            <div class="inspector-field-row">
                <span class="inspector-field-label">Place of Birth</span>
                <span class="inspector-field-val">${escapeHtml(rec.place_of_birth)}</span>
            </div>` : ''}
        `;
    } else if (rec.record_type === 'Wedding') {
        specificHtml = `
            <div class="inspector-section-title">
                <i class="fa-solid fa-heart" style="font-size: 11px; color: #737373;"></i> Nuptial Details &amp; Witnesses
            </div>
            <div class="inspector-field-row">
                <span class="inspector-field-label">Groom</span>
                <span class="inspector-field-val">${escapeHtml(rec.groom_name) || '—'}</span>
            </div>
            <div class="inspector-field-row">
                <span class="inspector-field-label">Bride</span>
                <span class="inspector-field-val">${escapeHtml(rec.bride_name) || '—'}</span>
            </div>
            <div class="inspector-field-row">
                <span class="inspector-field-label">Principal Witnesses</span>
                <span class="inspector-field-val">${escapeHtml(rec.sponsor_names || rec.witness_1 || rec.witness_2) || '—'}</span>
            </div>
        `;
    } else if (rec.record_type === 'Funeral') {
        specificHtml = `
            <div class="inspector-section-title">
                <i class="fa-solid fa-cross" style="font-size: 11px; color: #737373;"></i> Funeral &amp; Burial Details
            </div>
            <div class="inspector-field-row">
                <span class="inspector-field-label">Date of Death</span>
                <span class="inspector-field-val">${formatDate(rec.date_of_death)}</span>
            </div>
            <div class="inspector-field-row">
                <span class="inspector-field-label">Age at Death</span>
                <span class="inspector-field-val">${escapeHtml(rec.age_at_death) ? rec.age_at_death + ' years old' : '—'}</span>
            </div>
            <div class="inspector-field-row">
                <span class="inspector-field-label">Place of Burial</span>
                <span class="inspector-field-val">${escapeHtml(rec.place_of_burial) || '—'}</span>
            </div>
        `;
    } else if (rec.record_type === 'Confirmation') {
        let confDateBaptism = '';
        let confPlaceBaptism = escapeHtml(rec.confirmation_place_of_baptism || '').trim();
        
        let parsedNotesObj = null;
        try {
            if ((rec.notes || '').trim().startsWith('{')) {
                parsedNotesObj = JSON.parse(rec.notes);
            }
        } catch(e) {}

        if (parsedNotesObj && parsedNotesObj.date_of_baptism) {
            confDateBaptism = formatDate(parsedNotesObj.date_of_baptism);
        }

        if (/^\d{4}-\d{2}-\d{2}$/.test(confPlaceBaptism)) {
            if (!confDateBaptism) confDateBaptism = formatDate(confPlaceBaptism);
            confPlaceBaptism = (parsedNotesObj && parsedNotesObj.place_of_baptism) ? escapeHtml(parsedNotesObj.place_of_baptism) : '';
        }

        specificHtml = `
            <div class="inspector-section-title">
                <i class="fa-solid fa-dove" style="font-size: 11px; color: #737373;"></i> Confirmation Details
            </div>
            ${confDateBaptism ? `
            <div class="inspector-field-row">
                <span class="inspector-field-label">Date of Baptism</span>
                <span class="inspector-field-val">${confDateBaptism}</span>
            </div>` : ''}
            <div class="inspector-field-row">
                <span class="inspector-field-label">Place of Baptism</span>
                <span class="inspector-field-val">${confPlaceBaptism || '—'}</span>
            </div>
            <div class="inspector-field-row">
                <span class="inspector-field-label">Sponsors</span>
                <span class="inspector-field-val">${escapeHtml(rec.sponsor_names || rec.godfather || rec.godmother) || '—'}</span>
            </div>
        `;
    } else if (rec.record_type === 'Communion') {
        specificHtml = `
            <div class="inspector-section-title">
                <i class="fa-solid fa-wine-glass" style="font-size: 11px; color: #737373;"></i> First Communion Details
            </div>
            <div class="inspector-field-row">
                <span class="inspector-field-label">Parents</span>
                <span class="inspector-field-val">${[escapeHtml(rec.father_name), escapeHtml(rec.mother_name)].filter(Boolean).join(' &amp; ') || '—'}</span>
            </div>
        `;
    }

    // Notes section
    let notesHtml = '';
    let displayRemarks = (rec.notes || '').trim();
    if (displayRemarks.startsWith('{') && displayRemarks.endsWith('}')) {
        try {
            const parsedNotes = JSON.parse(displayRemarks);
            displayRemarks = (parsedNotes.marginal_notes || parsedNotes.remarks || '').trim();
        } catch(e) {
            displayRemarks = '';
        }
    }
    if (displayRemarks !== '') {
        notesHtml = `
            <div class="inspector-section-title">
                <i class="fa-solid fa-note-sticky" style="font-size: 11px; color: #737373;"></i> Marginal Remarks
            </div>
            <div class="inspector-notes-box">
                ${escapeHtml(displayRemarks)}
            </div>
        `;
    }

    const rawPriest = (rec.officiating_priest || '').trim();
    let displayPriest = '—';
    if (rawPriest) {
        displayPriest = /^(Fr\.|Rev\.|Father|Msgr\.)/i.test(rawPriest) ? escapeHtml(rawPriest) : `Fr. ${escapeHtml(rawPriest)}`;
    }

    inspector.innerHTML = `
        <div class="inspector-card">
            <div class="inspector-header">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    ${getSacramentBadgeHtml(rec.record_type)}
                    <span class="inspector-ref-badge">REF: <strong>${escapeHtml(rec.reference_id)}</strong></span>
                </div>
            </div>

            <!-- Subject Hero -->
            <div class="inspector-hero">
                <div class="inspector-hero-name" style="word-break: break-word;">
                    ${escapeHtml(rec.person_name)}
                </div>
                <div class="inspector-meta-row" style="display: flex; flex-direction: column; gap: 6px;">
                    <div class="inspector-meta-item">
                        <i class="fa-regular fa-calendar" style="color: #737373; flex-shrink: 0;"></i>
                        <strong>${formatDate(rec.event_date)}</strong>
                    </div>
                    <div class="inspector-meta-item" style="word-break: break-word; align-items: flex-start;">
                        <i class="fa-solid fa-place-of-worship" style="color: #737373; margin-top: 2px; flex-shrink: 0;"></i>
                        <span style="line-height: 1.35;">${escapeHtml(rec.chapel_name || 'Main Cathedral')}</span>
                    </div>
                    <div class="inspector-meta-item" style="word-break: break-word; align-items: flex-start;">
                        <i class="fa-solid fa-user-tie" style="color: #737373; margin-top: 2px; flex-shrink: 0;"></i>
                        <span style="line-height: 1.35;">${displayPriest}</span>
                    </div>
                </div>
            </div>

            <!-- Registry Book & Page Micro Cards -->
            <div class="inspector-grid-2">
                <div class="inspector-mini-card">
                    <div class="inspector-mini-label"><i class="fa-solid fa-book" style="color: #737373;"></i> Book No.</div>
                    <div class="inspector-mini-val">${escapeHtml(rec.book_number) ? 'Book ' + escapeHtml(rec.book_number) : '—'}</div>
                </div>
                <div class="inspector-mini-card">
                    <div class="inspector-mini-label"><i class="fa-solid fa-file" style="color: #737373;"></i> Folio / Page</div>
                    <div class="inspector-mini-val">${escapeHtml(rec.page_number) ? 'Page ' + escapeHtml(rec.page_number) : '—'}</div>
                </div>
            </div>

            <!-- Canonical Specifics -->
            ${specificHtml}

            <!-- Remarks -->
            ${notesHtml}

            <!-- Action Toolbar -->
            <div class="inspector-actions">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px;">
                    ${editBtn}
                    <a href="${certUrl}" target="_blank" class="btn btn-secondary btn-sm" style="height: 34px; padding: 0 10px; font-size: 11.5px; font-weight: 600; display: inline-flex; align-items: center; justify-content: center; gap: 6px; background: #FFFFFF; color: #111111; border: 1px solid #E5E5E5; border-radius: 6px; text-decoration: none;">
                        <i class="fa-solid fa-print"></i> Certificate
                    </a>
                </div>
                ${deleteBtn}
            </div>
        </div>
    `;
}

function initRecordsSection() {
    const firstRow = document.querySelector('.record-row');
    if (firstRow) {
        const raw = firstRow.getAttribute('data-record');
        if (raw) {
            try {
                const rec = JSON.parse(raw);
                showRecordDetails(rec, firstRow);
            } catch (e) {
                console.warn('Could not parse data-record', e);
            }
        }
    }
}

// Ensure global accessibility across all contexts
window.escapeHtml = escapeHtml;
window.getSacramentBadgeHtml = getSacramentBadgeHtml;
window.showRecordDetails = showRecordDetails;
window.initRecordsSection = initRecordsSection;
window.toggleRecordFields = toggleRecordFields;
window.openEditRecordModal = openEditRecordModal;
window.openCertModal = openCertModal;
window.previewCert = previewCert;
window.openNotifyModal = openNotifyModal;

