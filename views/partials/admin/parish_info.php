<!-- ═══════════════════════════════════════════════════════════════ -->
<!-- MANAGE PARISH INFO & SETTINGS                                      -->
<!-- ═══════════════════════════════════════════════════════════════ -->
<?php
$active_tab = $_GET['tab'] ?? 'contact';
if (!in_array($active_tab, ['contact', 'mass', 'history', 'leaders', 'email'], true)) {
    $active_tab = 'contact';
}
?>

<style>
    .parish-nav-tabs {
        display: flex;
        gap: 8px;
        margin-bottom: 22px;
        border-bottom: 1px solid var(--border);
        overflow-x: auto;
        padding-bottom: 8px;
        scrollbar-width: thin;
    }
    .parish-nav-tabs::-webkit-scrollbar {
        height: 4px;
    }
    .parish-nav-tabs::-webkit-scrollbar-thumb {
        background: var(--border);
        border-radius: 4px;
    }
    .parish-tab-btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 9px 20px;
        border: 1px solid transparent;
        background: var(--bg2);
        color: var(--text2);
        border-radius: 999px;
        cursor: pointer;
        transition: all 0.2s ease;
        font-weight: 600;
        font-size: 13.5px;
        white-space: nowrap;
        min-height: 38px;
        box-sizing: border-box;
    }
    .parish-tab-btn i {
        font-size: 14px;
        opacity: 0.8;
    }
    .parish-tab-btn:hover {
        color: var(--text);
        background: var(--color-surface-hover, var(--bg3));
        border-color: var(--border);
    }
    .parish-tab-btn.active {
        color: #FFFFFF;
        background: #111111;
        border-color: #111111;
        box-shadow: 0 2px 6px rgba(0,0,0,0.12);
    }
    .parish-tab-btn.active i {
        opacity: 1;
        color: #FFFFFF;
    }
    [data-theme="dark"] .parish-tab-btn {
        background: rgba(255, 255, 255, 0.05);
        color: #cbd5e1;
    }
    [data-theme="dark"] .parish-tab-btn:hover {
        background: rgba(255, 255, 255, 0.1);
        color: #ffffff;
    }
    [data-theme="dark"] .parish-tab-btn.active {
        color: #111111 !important;
        background: #FFFFFF !important;
        border-color: #FFFFFF !important;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.35);
    }
    [data-theme="dark"] .parish-tab-btn.active i {
        color: #111111 !important;
    }

    .parish-tab-pane {
        display: none;
        animation: fadeIn 0.25s ease-out;
    }
    .parish-tab-pane.active {
        display: block;
    }

    /* Modern Card Layout */
    .parish-card {
        background: #FFFFFF;
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 24px 28px;
        margin-bottom: 20px;
        box-shadow: none;
    }
    .parish-card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        padding-bottom: 12px;
        border-bottom: 1px solid var(--border);
    }
    .parish-card-title {
        font-size: 15px;
        font-weight: 600;
        color: #111111;
        display: flex;
        align-items: center;
        gap: 10px;
        letter-spacing: 0.2px;
    }
    .parish-card-desc {
        font-size: 12px;
        color: var(--text2);
        margin-top: 2px;
    }

    .parish-grid-2 {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
    }
    .parish-grid-3 {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 16px;
    }

    .parish-form-group {
        display: flex;
        flex-direction: column;
        gap: 6px;
        margin-bottom: 16px;
    }
    .parish-form-group label {
        font-size: 12px;
        font-weight: 600;
        color: var(--text);
        display: flex;
        align-items: center;
        gap: 6px;
    }
    .parish-form-group label i {
        color: var(--accent);
        font-size: 13px;
        width: 16px;
    }
    .parish-field-hint {
        font-size: 11px;
        color: var(--text2);
        margin-top: 4px;
        line-height: 1.4;
    }

    /* Image Dropzone in Parish Settings */
    .parish-photo-layout {
        display: flex;
        gap: 16px;
        align-items: center;
        background: var(--bg);
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 16px;
    }
    .parish-current-photo {
        width: 170px;
        height: 115px;
        border-radius: 10px;
        object-fit: cover;
        border: 1px solid var(--border);
        box-shadow: var(--shadow-sm);
        flex-shrink: 0;
    }
    .parish-photo-upload-zone {
        flex: 1;
        border: 2px dashed var(--border);
        border-radius: 10px;
        padding: 16px 14px;
        text-align: center;
        cursor: pointer;
        background: var(--bg2);
        transition: all 0.2s ease;
    }
    .parish-photo-upload-zone:hover {
        border-color: var(--accent);
        background: var(--color-surface-hover);
    }

    /* List Items (Schedule, History, Leaders) */
    .parish-item-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 12px;
    }
    .parish-list-card {
        background: var(--bg);
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 14px 18px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 16px;
        transition: transform 0.15s ease, border-color 0.15s ease;
    }
    .parish-list-card:hover {
        border-color: var(--accent);
        transform: translateY(-1px);
    }

    /* Leader Card Styling */
    .parish-leader-avatar {
        width: 46px;
        height: 46px;
        border-radius: 50%;
        object-fit: cover;
        border: 2px solid var(--border);
        box-shadow: 0 2px 6px rgba(0,0,0,0.15);
        flex-shrink: 0;
    }
    .parish-leader-placeholder {
        width: 46px;
        height: 46px;
        border-radius: 50%;
        background: var(--color-surface-active, rgba(201, 168, 76, 0.15));
        border: 1px solid var(--border);
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--accent);
        font-weight: 700;
        font-size: 16px;
        flex-shrink: 0;
    }

    /* Modern Modals in Parish Info */
    .pi-overlay {
        display: flex;
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.55);
        z-index: 1000;
        align-items: center;
        justify-content: center;
        backdrop-filter: blur(4px);
        opacity: 0;
        visibility: hidden;
        pointer-events: none;
        transition: opacity 0.25s ease, visibility 0.25s ease;
        padding: 20px;
    }
    .pi-overlay.open {
        opacity: 1;
        visibility: visible;
        pointer-events: auto;
    }
    .pi-modal-box {
        background: var(--bg2);
        border: 1px solid var(--border);
        border-radius: 16px;
        width: 540px;
        max-width: 95%;
        box-shadow: var(--shadow-lg);
        overflow: hidden;
        animation: modalSlideIn 0.3s var(--ease-out-quart) both;
    }
    .pi-modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 16px 22px;
        background: var(--bg2);
        border-bottom: 1px solid var(--border);
    }
    .pi-modal-title {
        font-family: var(--font-family, 'Inter', sans-serif);
        font-size: 1.15rem;
        font-weight: 600;
        color: #111111;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .pi-modal-body {
        padding: 20px 24px;
        max-height: calc(85vh - 120px);
        overflow-y: auto;
    }
    .pi-modal-footer {
        display: flex;
        justify-content: flex-end;
        gap: 12px;
        padding: 14px 22px;
        background: var(--bg2);
        border-top: 1px solid var(--border);
    }

    @media (max-width: 768px) {
        .parish-grid-2, .parish-grid-3 {
            grid-template-columns: 1fr;
        }
        .parish-photo-layout {
            flex-direction: column;
            align-items: center;
        }
        .parish-current-photo {
            width: 100%;
            height: 180px;
        }
    }
</style>

<div class="section <?php echo ($active_section === 'parish_info') ? 'active' : ''; ?>" id="sec-parish_info" style="padding: 20px 28px 36px; width: 100%; box-sizing: border-box;">
    <!-- Section Header (Row 1: Title + Stat Badge Pinned Top-Right) -->
    <div class="page-header-bar" style="margin-bottom: 6px; padding-bottom: 8px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
        <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
            <div class="page-header-icon" style="width: 38px; height: 38px; border-radius: 9px; background: rgba(201,168,76,0.14); color: var(--accent); display: flex; align-items: center; justify-content: center; font-size: 17px; flex-shrink: 0;">
                <i class="fa-solid fa-church"></i>
            </div>
            <div>
                <h1 class="page-title" style="font-family: var(--font-family, 'Inter', sans-serif); font-size: var(--font-page-title, 20px); font-weight: 700; color: var(--text); margin: 0; line-height: 1.2;">
                    Parish Settings
                </h1>
                <p class="page-subtitle" style="font-size: var(--font-page-subtitle, 14px); color: var(--text2); margin: 3px 0 0 0;">Manage Cathedral contact credentials, mass schedules, historical milestones, and pastoral leadership</p>
            </div>
        </div>
        <!-- Right Side: Stat Badges Pinned Top-Right -->
        <div style="display: flex; align-items: center; gap: 6px;">
            <span class="badge" style="background: var(--bg3, #F4F4F5); border: 1px solid var(--border); color: var(--text2); font-size: 11.5px; font-weight: 600; padding: 4px 12px; border-radius: 999px;">
                Cathedral Master Configuration
            </span>
        </div>
    </div>

    <!-- Navigation Tabs -->
    <div class="parish-nav-tabs">
        <button type="button" class="parish-tab-btn <?php echo $active_tab === 'contact' ? 'active' : ''; ?>" data-tab="contact" onclick="switchPITab('contact')">
            <i class="fa-solid fa-address-book"></i> Contact &amp; Location
        </button>
        <button type="button" class="parish-tab-btn <?php echo $active_tab === 'mass' ? 'active' : ''; ?>" data-tab="mass" onclick="switchPITab('mass')">
            <i class="fa-solid fa-calendar-check"></i> Mass Schedules
        </button>
        <button type="button" class="parish-tab-btn <?php echo $active_tab === 'history' ? 'active' : ''; ?>" data-tab="history" onclick="switchPITab('history')">
            <i class="fa-solid fa-landmark"></i> Historical Milestones
        </button>
        <button type="button" class="parish-tab-btn <?php echo $active_tab === 'leaders' ? 'active' : ''; ?>" data-tab="leaders" onclick="switchPITab('leaders')">
            <i class="fa-solid fa-users"></i> Pastoral Leadership
        </button>
        <button type="button" class="parish-tab-btn <?php echo $active_tab === 'email' ? 'active' : ''; ?>" data-tab="email" onclick="switchPITab('email')">
            <i class="fa-solid fa-envelope-circle-check"></i> Outgoing Mail Server
        </button>
    </div>

    <!-- ───────────────────────────────────────────────────────────────── -->
    <!-- TAB 1: CONTACT INFORMATION                                       -->
    <!-- ───────────────────────────────────────────────────────────────── -->
    <div class="parish-tab-pane <?php echo $active_tab === 'contact' ? 'active' : ''; ?>" id="pi-tab-contact">
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="form_section" value="parish_info">
            <input type="hidden" name="action" value="update_contact">
            <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">

            <!-- Card 1: Official Cathedral Identity & Photo -->
            <div class="parish-card">
                <div class="parish-card-header">
                    <div>
                        <div class="parish-card-title"><i class="fa-solid fa-church"></i> Cathedral Identity &amp; Presentation</div>
                        <div class="parish-card-desc">Official parish name, patron saint, coordinator, and cathedral banner media</div>
                    </div>
                </div>

                <div class="parish-grid-2">
                    <div>
                        <div class="parish-form-group">
                            <label><i class="fa-solid fa-church"></i> Parish / Cathedral Name <span class="req-star">*</span></label>
                            <input type="text" name="parish_name" value="<?php echo htmlspecialchars($contact_info['parish_name'] ?? 'IFI Cathedral'); ?>" required>
                        </div>
                        <div class="parish-form-group">
                            <label><i class="fa-solid fa-cross"></i> Patron Saint</label>
                            <input type="text" name="patron_saint" value="<?php echo htmlspecialchars($contact_info['patron_saint'] ?? ''); ?>" placeholder="e.g. St. Michael the Archangel">
                        </div>
                        <div class="parish-grid-2" style="gap:12px;">
                            <div class="parish-form-group">
                                <label><i class="fa-solid fa-user-tie"></i> Lead Coordinator</label>
                                <input type="text" name="coordinator_name" value="<?php echo htmlspecialchars($contact_info['coordinator_name'] ?? ''); ?>" placeholder="Full Name">
                            </div>
                            <div class="parish-form-group">
                                <label><i class="fa-solid fa-phone"></i> Coordinator Phone</label>
                                <input type="text" name="coordinator_phone" value="<?php echo htmlspecialchars($contact_info['coordinator_phone'] ?? ''); ?>" placeholder="09XXXXXXXXX">
                            </div>
                        </div>
                    </div>

                    <div>
                        <div class="parish-form-group">
                            <label><i class="fa-solid fa-camera"></i> Cathedral Official Photo <span class="text-xs text-muted">(Max 5MB)</span></label>
                            <input type="file" name="cathedral_image" id="cathedral-photo-input" accept="image/*" style="display:none;" onchange="previewCathedralPhoto(this)">
                            
                            <div class="parish-photo-layout">
                                <div style="text-align:center;">
                                    <?php if (!empty($contact_info['cathedral_image'])): ?>
                                        <img id="cathedral-photo-preview-img" src="storage/<?php echo $contact_info['cathedral_image']; ?>" class="parish-current-photo" alt="Cathedral Photo">
                                    <?php else: ?>
                                        <img id="cathedral-photo-preview-img" src="" class="parish-current-photo" style="display:none;" alt="Cathedral Photo">
                                        <div id="cathedral-photo-placeholder" style="width:170px; height:115px; border-radius:10px; background:var(--bg3); display:flex; flex-direction:column; align-items:center; justify-content:center; color:var(--text2); border:1px solid var(--border);">
                                            <i class="fa-solid fa-image" style="font-size:30px; margin-bottom:4px; opacity:0.5;"></i>
                                            <span class="text-xs">No photo uploaded</span>
                                        </div>
                                    <?php endif; ?>
                                    <div class="text-xs text-muted" style="margin-top:6px;">Current Photo</div>
                                </div>

                                <div class="parish-photo-upload-zone" onclick="document.getElementById('cathedral-photo-input').click()">
                                    <i class="fa-solid fa-cloud-arrow-up text-accent" style="font-size:24px; margin-bottom:6px;"></i>
                                    <div style="font-weight:600; font-size:13px; color:var(--text);">Choose New Photo</div>
                                    <div class="text-xs text-muted" style="margin-top:2px;">PNG, JPG, WebP up to 5MB</div>
                                    <div id="cathedral-photo-filename" class="text-xs text-accent" style="font-weight:600; margin-top:6px; word-break:break-all;"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Card 2: Contact Channels & Location -->
            <div class="parish-card">
                <div class="parish-card-header">
                    <div>
                        <div class="parish-card-title"><i class="fa-solid fa-location-dot"></i> Location, Rectory Hours &amp; Public Contact Channels</div>
                        <div class="parish-card-desc">Information displayed on the visitor portal, certificates, footer, and member receipts</div>
                    </div>
                </div>

                <div class="parish-grid-2">
                    <div>
                        <div class="parish-form-group">
                            <label><i class="fa-solid fa-map-pin"></i> Complete Physical Address</label>
                            <input type="text" name="address" value="<?php echo htmlspecialchars($contact_info['address'] ?? ''); ?>" placeholder="e.g. Gov. Espiritu St., San Juan, Bacoor, Cavite">
                        </div>
                        <div class="parish-grid-2" style="gap:12px;">
                            <div class="parish-form-group">
                                <label><i class="fa-solid fa-phone"></i> Official Parish Phone</label>
                                <input type="text" name="phone" value="<?php echo htmlspecialchars($contact_info['phone'] ?? ''); ?>" placeholder="(046) 123-4567">
                            </div>
                            <div class="parish-form-group">
                                <label><i class="fa-solid fa-envelope"></i> Public Email Address</label>
                                <input type="email" name="email" value="<?php echo htmlspecialchars($contact_info['email'] ?? ''); ?>" placeholder="info@cathedral.org">
                            </div>
                        </div>
                        <div class="parish-form-group">
                            <label><i class="fa-solid fa-clock"></i> Rectory / Office Hours</label>
                            <input type="text" name="office_hours" value="<?php echo htmlspecialchars($contact_info['office_hours'] ?? ''); ?>" placeholder="e.g. Tuesday to Sunday: 8:00 AM – 5:00 PM (Monday Closed)">
                        </div>
                    </div>

                    <div>
                        <div class="parish-form-group">
                            <label for="parish-map-input"><i class="fa-solid fa-map-location-dot"></i> Google Maps Location Embed</label>
                            <input type="text" name="map_embed_url" id="parish-map-input" value="<?php echo htmlspecialchars($contact_info['map_embed_url'] ?? ''); ?>" placeholder="https://www.google.com/maps/embed?pb=..." oninput="handleMapUrlInput(this.value)">
                            <span class="parish-field-hint">Paste the embed URL or embed code from Google Maps (Share &rarr; Embed a map). Live preview updates below.</span>
                        </div>

                        <!-- Live Map Preview Frame -->
                        <div id="map-preview-container" style="<?php echo empty($contact_info['map_embed_url']) ? 'display:none;' : ''; ?> margin-top: 10px; background: var(--bg); border: 1px solid var(--border); border-radius: 10px; overflow: hidden;">
                            <div style="padding: 6px 12px; background: var(--bg3); border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; font-size: 11px;">
                                <span style="font-weight: 600; color: var(--text);"><i class="fa-solid fa-location-dot text-accent mr-1"></i> Interactive Map Preview</span>
                                <span class="badge badge-subtle" style="font-size: 10px;">Portal Display</span>
                            </div>
                            <div style="height: 140px; position: relative;">
                                <iframe id="map-preview-iframe" src="<?php echo htmlspecialchars($contact_info['map_embed_url'] ?? ''); ?>" style="width: 100%; height: 100%; border: 0;" allowfullscreen="" loading="lazy" title="Interactive Cathedral Map"></iframe>
                            </div>
                        </div>
                    </div>
                </div>

                <div style="display: flex; justify-content: flex-end; margin-top: 18px; padding-top: 16px; border-top: 1px solid var(--border);">
                    <button type="submit" class="btn btn-primary" style="padding: 10px 24px; font-weight: 600;">
                        <i class="fa-solid fa-floppy-disk mr-1"></i> Save Contact Settings
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- ───────────────────────────────────────────────────────────────── -->
    <!-- TAB 2: MASS SCHEDULE                                             -->
    <!-- ───────────────────────────────────────────────────────────────── -->
    <div class="parish-tab-pane <?php echo $active_tab === 'mass' ? 'active' : ''; ?>" id="pi-tab-mass">
        <div class="parish-card">
            <div class="parish-card-header">
                <div>
                    <div class="parish-card-title"><i class="fa-solid fa-calendar-check"></i> Cathedral Mass Schedules</div>
                    <div class="parish-card-desc">Define regular liturgical schedules and worship services displayed across portals</div>
                </div>
                <button type="button" class="btn btn-primary" onclick="openScheduleModal(0)" style="font-weight: 600;">
                    <i class="fa-solid fa-plus-circle mr-1"></i> Add Mass Schedule
                </button>
            </div>

            <?php if (empty($mass_schedules)): ?>
                <div style="text-align: center; padding: 36px 20px; color: var(--text2);">
                    <i class="fa-solid fa-calendar-xmark" style="font-size: 32px; opacity: 0.5; margin-bottom: 8px;"></i>
                    <p style="margin: 0; font-size: 13px;">No mass schedules currently listed.</p>
                </div>
            <?php else: ?>
                <div class="parish-item-grid">
                    <?php foreach ($mass_schedules as $s): ?>
                        <div class="parish-list-card">
                            <div style="display: flex; align-items: center; gap: 14px; flex: 1;">
                                <div class="modal-icon-avatar" style="width: 38px; height: 38px; font-size: 15px;">
                                    <i class="fa-solid fa-clock"></i>
                                </div>
                                <div>
                                    <div style="font-weight: 700; font-size: 14px; color: var(--text);">
                                        <?php echo htmlspecialchars($s['day_type']); ?>
                                        <span class="badge <?php echo $s['is_active'] ? 'badge-confirmed' : 'badge-cancelled'; ?> ml-2" style="font-size: 11px;">
                                            <?php echo $s['is_active'] ? 'Active' : 'Hidden'; ?>
                                        </span>
                                    </div>
                                    <div style="font-size: 12px; color: var(--accent); margin-top: 2px;">
                                        <i class="fa-regular fa-clock mr-1"></i> <?php echo htmlspecialchars($s['schedule_time']); ?>
                                        <?php if (!empty($s['sort_order'])): ?>
                                            <span class="text-muted ml-2">(Sort: <?php echo (int)$s['sort_order']; ?>)</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <button type="button" class="btn btn-ghost btn-sm" onclick="openScheduleModal(<?php echo $s['schedule_id']; ?>,'<?php echo addslashes($s['day_type']); ?>','<?php echo addslashes($s['schedule_time']); ?>',<?php echo $s['sort_order']; ?>,<?php echo $s['is_active']; ?>)" title="Edit schedule">
                                    <i class="fa-solid fa-pen-to-square mr-1"></i> Edit
                                </button>
                                <form method="POST" style="margin: 0;" onsubmit="return confirm('Are you sure you want to delete this schedule?')">
                                    <input type="hidden" name="form_section" value="parish_info">
                                    <input type="hidden" name="action" value="delete_mass">
                                    <input type="hidden" name="id" value="<?php echo $s['schedule_id']; ?>">
                                    <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
                                    <button type="submit" class="btn btn-ghost btn-sm text-danger" title="Delete schedule">
                                        <i class="fa-solid fa-trash-can mr-1"></i> Delete
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ───────────────────────────────────────────────────────────────── -->
    <!-- TAB 3: PARISH HISTORY                                             -->
    <!-- ───────────────────────────────────────────────────────────────── -->
    <div class="parish-tab-pane <?php echo $active_tab === 'history' ? 'active' : ''; ?>" id="pi-tab-history">
        <!-- Overview Narrative Card -->
        <div class="parish-card">
            <div class="parish-card-header">
                <div>
                    <div class="parish-card-title"><i class="fa-solid fa-book-open"></i> Historical Narrative Overview</div>
                    <div class="parish-card-desc">General background story of the Cathedral displayed on the visitor portal history page</div>
                </div>
            </div>
            <form method="POST">
                <input type="hidden" name="form_section" value="parish_info">
                <input type="hidden" name="action" value="update_history_intro">
                <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
                
                <div class="parish-form-group">
                    <textarea name="intro" style="min-height: 110px; line-height: 1.5;" placeholder="Write the history overview..."><?php echo htmlspecialchars($history_intro); ?></textarea>
                </div>
                <div style="display: flex; justify-content: flex-end;">
                    <button type="submit" class="btn btn-primary" style="font-weight: 600;">
                        <i class="fa-solid fa-floppy-disk mr-1"></i> Update Overview
                    </button>
                </div>
            </form>
        </div>

        <!-- Milestones Timeline Card -->
        <div class="parish-card">
            <div class="parish-card-header">
                <div>
                    <div class="parish-card-title"><i class="fa-solid fa-timeline"></i> Timeline Milestones</div>
                    <div class="parish-card-desc">Chronological events in the history of the parish</div>
                </div>
                <button type="button" class="btn btn-primary" onclick="openHistoryModal(0)" style="font-weight: 600;">
                    <i class="fa-solid fa-plus-circle mr-1"></i> Add Timeline Milestone
                </button>
            </div>

            <?php if (empty($history_items)): ?>
                <div style="text-align: center; padding: 36px 20px; color: var(--text2);">
                    <i class="fa-solid fa-hourglass-empty" style="font-size: 32px; opacity: 0.5; margin-bottom: 8px;"></i>
                    <p style="margin: 0; font-size: 13px;">No timeline items recorded yet.</p>
                </div>
            <?php else: ?>
                <div class="parish-item-grid">
                    <?php foreach ($history_items as $h): ?>
                        <div class="parish-list-card">
                            <div style="display: flex; align-items: flex-start; gap: 14px; flex: 1;">
                                <div style="padding: 4px 10px; border-radius: 6px; background: #F6F6F6; border: 1px solid var(--border); color: #111111; font-weight: 700; font-size: 13px; font-family: var(--font-family, 'Inter', sans-serif); flex-shrink: 0;">
                                    <?php echo htmlspecialchars($h['year']); ?>
                                </div>
                                <div>
                                    <div style="font-weight: 700; font-size: 14px; color: var(--text);">
                                        <?php echo htmlspecialchars($h['title']); ?>
                                        <?php if (!$h['is_active']): ?>
                                            <span class="badge badge-cancelled ml-2">Hidden</span>
                                        <?php endif; ?>
                                    </div>
                                    <div style="font-size: 12px; color: var(--text2); margin-top: 3px; line-height: 1.4;">
                                        <?php echo htmlspecialchars(substr($h['description'], 0, 140)) . (strlen($h['description']) > 140 ? '...' : ''); ?>
                                    </div>
                                </div>
                            </div>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <button type="button" class="btn btn-ghost btn-sm" onclick="openHistoryModal(<?php echo $h['history_id']; ?>,'<?php echo addslashes($h['year']); ?>','<?php echo addslashes($h['title']); ?>','<?php echo addslashes($h['description']); ?>',<?php echo $h['sort_order']; ?>,<?php echo $h['is_active']; ?>)" title="Edit milestone">
                                    <i class="fa-solid fa-pen-to-square mr-1"></i> Edit
                                </button>
                                <form method="POST" style="margin: 0;" onsubmit="return confirm('Are you sure you want to delete this timeline event?')">
                                    <input type="hidden" name="form_section" value="parish_info">
                                    <input type="hidden" name="action" value="delete_history">
                                    <input type="hidden" name="id" value="<?php echo $h['history_id']; ?>">
                                    <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
                                    <button type="submit" class="btn btn-ghost btn-sm text-danger" title="Delete milestone">
                                        <i class="fa-solid fa-trash-can mr-1"></i> Delete
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ───────────────────────────────────────────────────────────────── -->
    <!-- TAB 4: PASTORAL LEADERSHIP                                       -->
    <!-- ───────────────────────────────────────────────────────────────── -->
    <div class="parish-tab-pane <?php echo $active_tab === 'leaders' ? 'active' : ''; ?>" id="pi-tab-leaders">
        <!-- Leadership Welcome Card -->
        <div class="parish-card">
            <div class="parish-card-header">
                <div>
                    <div class="parish-card-title"><i class="fa-solid fa-quote-left"></i> Leadership Welcome &amp; Pastoral Message</div>
                    <div class="parish-card-desc">Introductory text introducing the parish leaders and clergy on the public site</div>
                </div>
            </div>
            <form method="POST">
                <input type="hidden" name="form_section" value="parish_info">
                <input type="hidden" name="action" value="update_leadership_intro">
                <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
                
                <div class="parish-form-group">
                    <textarea name="intro" style="min-height: 100px; line-height: 1.5;" placeholder="Write leadership introduction..."><?php echo htmlspecialchars($leadership_intro); ?></textarea>
                </div>
                <div style="display: flex; justify-content: flex-end;">
                    <button type="submit" class="btn btn-primary" style="font-weight: 600;">
                        <i class="fa-solid fa-floppy-disk mr-1"></i> Update Welcome Message
                    </button>
                </div>
            </form>
        </div>

        <!-- Leaders List Card -->
        <div class="parish-card">
            <div class="parish-card-header">
                <div>
                    <div class="parish-card-title"><i class="fa-solid fa-users"></i> Parish Clergy &amp; Leadership Directory</div>
                    <div class="parish-card-desc">Bishops, parish priests, assistant priests, and pastoral council members</div>
                </div>
                <button type="button" class="btn btn-primary" onclick="openLeaderModal(0)" style="font-weight: 600;">
                    <i class="fa-solid fa-user-plus mr-1"></i> Add Leader / Clergy
                </button>
            </div>

            <?php if (empty($leaders)): ?>
                <div style="text-align: center; padding: 36px 20px; color: var(--text2);">
                    <i class="fa-solid fa-users-slash" style="font-size: 32px; opacity: 0.5; margin-bottom: 8px;"></i>
                    <p style="margin: 0; font-size: 13px;">No parish leaders recorded yet.</p>
                </div>
            <?php else: ?>
                <div class="parish-item-grid">
                    <?php foreach ($leaders as $l): ?>
                        <div class="parish-list-card">
                            <div style="display: flex; align-items: center; gap: 14px; flex: 1;">
                                <?php if (!empty($l['image_path'])): ?>
                                    <img src="storage/<?php echo htmlspecialchars($l['image_path']); ?>" class="parish-leader-avatar" alt="<?php echo htmlspecialchars($l['name']); ?>">
                                <?php else: ?>
                                    <div class="parish-leader-placeholder">
                                        <?php echo strtoupper(substr($l['name'], 0, 1)); ?>
                                    </div>
                                <?php endif; ?>

                                <div>
                                    <div style="font-weight: 700; font-size: 14px; color: var(--text);">
                                        <?php echo htmlspecialchars($l['name']); ?>
                                        <span class="badge badge-subtle ml-2" style="font-size: 11px; text-transform: capitalize;">
                                            <?php echo str_replace('_', ' ', $l['position_type']); ?>
                                        </span>
                                        <?php if (!$l['is_active']): ?>
                                            <span class="badge badge-cancelled ml-1">Hidden</span>
                                        <?php endif; ?>
                                    </div>
                                    <div style="font-size: 12px; color: var(--accent); margin-top: 2px;">
                                        <?php echo htmlspecialchars($l['title']); ?>
                                        <?php if (!empty($l['subtitle'])): ?>
                                            <span class="text-muted ml-2">• <?php echo htmlspecialchars($l['subtitle']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <button type="button" class="btn btn-ghost btn-sm" onclick="openLeaderModal(<?php echo $l['leader_id']; ?>,'<?php echo addslashes($l['name']); ?>','<?php echo addslashes($l['title']); ?>','<?php echo $l['position_type']; ?>','<?php echo addslashes($l['subtitle'] ?? ''); ?>',<?php echo $l['sort_order']; ?>,<?php echo $l['is_active']; ?>,'<?php echo addslashes($l['image_path'] ?? ''); ?>')" title="Edit leader">
                                    <i class="fa-solid fa-pen-to-square mr-1"></i> Edit
                                </button>
                                <form method="POST" style="margin: 0;" onsubmit="return confirm('Are you sure you want to delete this leader?')">
                                    <input type="hidden" name="form_section" value="parish_info">
                                    <input type="hidden" name="action" value="delete_leader">
                                    <input type="hidden" name="id" value="<?php echo $l['leader_id']; ?>">
                                    <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
                                    <button type="submit" class="btn btn-ghost btn-sm text-danger" title="Delete leader">
                                        <i class="fa-solid fa-trash-can mr-1"></i> Delete
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ───────────────────────────────────────────────────────────────── -->
    <!-- TAB 5: GLOBAL EMAIL SETTINGS                                     -->
    <!-- ───────────────────────────────────────────────────────────────── -->
    <div class="parish-tab-pane <?php echo $active_tab === 'email' ? 'active' : ''; ?>" id="pi-tab-email">
        <div class="parish-card">
            <div class="parish-card-header">
                <div>
                    <div class="parish-card-title"><i class="fa-solid fa-envelope-circle-check"></i> Cathedral Master Outgoing Mail Server (SMTP)</div>
                    <div class="parish-card-desc">Global SMTP configuration used for system alerts, registration confirmations, and chapel mail fallback</div>
                </div>
            </div>

            <div style="background: var(--bg); border: 1px solid var(--border); border-radius: 10px; padding: 14px 18px; margin-bottom: 20px; display: flex; align-items: flex-start; gap: 12px;">
                <i class="fa-solid fa-circle-info text-accent" style="font-size: 18px; margin-top: 2px;"></i>
                <div style="font-size: 12.5px; color: var(--text2); line-height: 1.5;">
                    These SMTP credentials act as the master mail server for all Cathedral-wide communications. If an individual chapel has not defined its own unique mail server, the system automatically falls back to these settings.
                </div>
            </div>
            
            <form method="POST">
                <input type="hidden" name="form_section" value="parish_info">
                <input type="hidden" name="action" value="update_email_settings">
                <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">

                <div class="parish-grid-2">
                    <div>
                        <div class="parish-form-group">
                            <label><i class="fa-solid fa-server"></i> SMTP Server Host</label>
                            <input type="text" name="smtp_host" value="<?php echo htmlspecialchars($contact_info['smtp_host'] ?? ''); ?>" placeholder="e.g. smtp.gmail.com">
                        </div>
                        <div class="parish-form-group">
                            <label><i class="fa-solid fa-network-wired"></i> SMTP Port</label>
                            <input type="number" name="smtp_port" value="<?php echo htmlspecialchars($contact_info['smtp_port'] ?? '587'); ?>">
                        </div>
                        <div class="parish-form-group">
                            <label><i class="fa-solid fa-user"></i> SMTP Username / Account</label>
                            <input type="text" name="smtp_user" value="<?php echo htmlspecialchars($contact_info['smtp_user'] ?? ''); ?>" placeholder="e.g. cathedral.office@gmail.com">
                        </div>
                    </div>

                    <div>
                        <div class="parish-form-group">
                            <label><i class="fa-solid fa-key"></i> SMTP Password / App Password</label>
                            <input type="password" name="smtp_pass" placeholder="Leave empty to retain existing password">
                        </div>
                        <div class="parish-form-group">
                            <label><i class="fa-solid fa-shield-halved"></i> Encryption Protocol</label>
                            <select name="smtp_secure">
                                <option value="tls" <?php echo ($contact_info['smtp_secure'] ?? '') === 'tls' ? 'selected' : ''; ?>>STARTTLS (Port 587 - Recommended)</option>
                                <option value="ssl" <?php echo ($contact_info['smtp_secure'] ?? '') === 'ssl' ? 'selected' : ''; ?>>SSL / TLS (Port 465)</option>
                            </select>
                        </div>
                        <div class="parish-grid-2" style="gap:12px;">
                            <div class="parish-form-group">
                                <label><i class="fa-solid fa-signature"></i> Display "From" Name</label>
                                <input type="text" name="from_name" value="<?php echo htmlspecialchars($contact_info['from_name'] ?? ''); ?>" placeholder="e.g. IFI Cathedral Office">
                            </div>
                            <div class="parish-form-group">
                                <label><i class="fa-solid fa-envelope"></i> Sender "From" Email</label>
                                <input type="email" name="from_email" value="<?php echo htmlspecialchars($contact_info['from_email'] ?? ''); ?>" placeholder="e.g. office@cathedral.org">
                            </div>
                        </div>
                    </div>
                </div>

                <div style="display: flex; justify-content: flex-end; margin-top: 18px; padding-top: 16px; border-top: 1px solid var(--border);">
                    <button type="submit" class="btn btn-primary" style="padding: 10px 24px; font-weight: 600;">
                        <i class="fa-solid fa-check mr-1"></i> Save Global Email Settings
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════ -->
<!-- MODALS (SCHEDULE, HISTORY, LEADER)                                  -->
<!-- ═══════════════════════════════════════════════════════════════════ -->

<!-- 1. SCHEDULE MODAL -->
<div class="pi-overlay" id="pi-modal-schedule" onclick="if(event.target===this)closePIModal('schedule')">
    <div class="pi-modal-box">
        <div class="pi-modal-header">
            <div class="pi-modal-title">
                <i class="fa-solid fa-clock"></i> <span id="pi-schedule-mtitle">Add Mass Schedule</span>
            </div>
            <button type="button" class="modal-close" onclick="closePIModal('schedule')">×</button>
        </div>
        <form method="POST">
            <input type="hidden" name="form_section" value="parish_info">
            <input type="hidden" name="action" value="save_mass">
            <input type="hidden" name="id" id="pi-sche-id" value="0">
            <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">

            <div class="pi-modal-body">
                <div class="parish-form-group">
                    <label><i class="fa-solid fa-calendar-day"></i> Day / Category <span class="req-star">*</span></label>
                    <input type="text" name="day_type" id="pi-sche-day" placeholder="e.g. Sunday Regular Mass or Weekday Mass" required>
                </div>
                <div class="parish-form-group">
                    <label><i class="fa-solid fa-clock"></i> Schedule Times <span class="req-star">*</span></label>
                    <input type="text" name="schedule_time" id="pi-sche-time" placeholder="e.g. 6:00 AM, 8:00 AM, 10:00 AM & 5:00 PM" required>
                </div>
                <div class="parish-grid-2">
                    <div class="parish-form-group">
                        <label><i class="fa-solid fa-arrow-down-1-9"></i> Sort Order</label>
                        <input type="number" name="sort_order" id="pi-sche-sort" value="0">
                    </div>
                    <div class="parish-form-group" style="justify-content: center; margin-top: 16px;">
                        <label style="cursor: pointer; user-select: none;">
                            <input type="checkbox" name="is_active" id="pi-sche-active" value="1" checked style="width: auto; margin-right: 8px;">
                            Active &amp; Visible
                        </label>
                    </div>
                </div>
            </div>
            <div class="pi-modal-footer">
                <button type="button" class="btn btn-ghost" onclick="closePIModal('schedule')">Cancel</button>
                <button type="submit" class="btn btn-primary" style="font-weight: 600;">
                    <i class="fa-solid fa-check mr-1"></i> Save Schedule
                </button>
            </div>
        </form>
    </div>
</div>

<!-- 2. HISTORY MODAL -->
<div class="pi-overlay" id="pi-modal-history" onclick="if(event.target===this)closePIModal('history')">
    <div class="pi-modal-box">
        <div class="pi-modal-header">
            <div class="pi-modal-title">
                <i class="fa-solid fa-timeline"></i> <span id="pi-history-mtitle">Add Timeline Milestone</span>
            </div>
            <button type="button" class="modal-close" onclick="closePIModal('history')">×</button>
        </div>
        <form method="POST">
            <input type="hidden" name="form_section" value="parish_info">
            <input type="hidden" name="action" value="save_history">
            <input type="hidden" name="id" id="pi-hist-id" value="0">
            <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">

            <div class="pi-modal-body">
                <div class="parish-grid-2">
                    <div class="parish-form-group">
                        <label><i class="fa-solid fa-calendar"></i> Year / Date <span class="req-star">*</span></label>
                        <input type="text" name="year" id="pi-hist-year" placeholder="e.g. 1902" required>
                    </div>
                    <div class="parish-form-group">
                        <label><i class="fa-solid fa-arrow-down-1-9"></i> Sort Order</label>
                        <input type="number" name="sort_order" id="pi-hist-sort" value="0">
                    </div>
                </div>
                <div class="parish-form-group">
                    <label><i class="fa-solid fa-heading"></i> Milestone Title <span class="req-star">*</span></label>
                    <input type="text" name="title" id="pi-hist-title" placeholder="e.g. Establishment of the Cathedral" required>
                </div>
                <div class="parish-form-group">
                    <label><i class="fa-solid fa-align-left"></i> Description Narrative <span class="req-star">*</span></label>
                    <textarea name="description" id="pi-hist-desc" style="min-height: 90px;" placeholder="Detailed historical narrative..." required></textarea>
                </div>
                <div class="parish-form-group">
                    <label style="cursor: pointer; user-select: none;">
                        <input type="checkbox" name="is_active" id="pi-hist-active" value="1" checked style="width: auto; margin-right: 8px;">
                        Active &amp; Visible on Portal
                    </label>
                </div>
            </div>
            <div class="pi-modal-footer">
                <button type="button" class="btn btn-ghost" onclick="closePIModal('history')">Cancel</button>
                <button type="submit" class="btn btn-primary" style="font-weight: 600;">
                    <i class="fa-solid fa-check mr-1"></i> Save Milestone
                </button>
            </div>
        </form>
    </div>
</div>

<!-- 3. LEADER MODAL -->
<div class="pi-overlay" id="pi-modal-leader" onclick="if(event.target===this)closePIModal('leader')">
    <div class="pi-modal-box">
        <div class="pi-modal-header">
            <div class="pi-modal-title">
                <i class="fa-solid fa-user-plus"></i> <span id="pi-leader-mtitle">Add Leader / Clergy</span>
            </div>
            <button type="button" class="modal-close" onclick="closePIModal('leader')">×</button>
        </div>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="form_section" value="parish_info">
            <input type="hidden" name="action" value="save_leader">
            <input type="hidden" name="id" id="pi-leader-id" value="0">
            <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">

            <div class="pi-modal-body">
                <div class="parish-form-group">
                    <label><i class="fa-solid fa-camera"></i> Leader Photo <span class="text-xs text-muted">(Optional, Max 2MB)</span></label>
                    <div id="pi-leader-img-preview" style="display:none; align-items:center; gap:12px; margin-bottom:10px; background:var(--bg); padding:8px 12px; border-radius:8px; border:1px solid var(--border);">
                        <img id="pi-leader-img-src" src="" style="width:48px; height:48px; object-fit:cover; border-radius:50%; border:1px solid var(--border);">
                        <span class="text-xs text-muted">Current photo attached</span>
                    </div>
                    <input type="file" name="leader_image" accept="image/*">
                </div>

                <div class="parish-form-group">
                    <label><i class="fa-solid fa-user"></i> Full Name <span class="req-star">*</span></label>
                    <input type="text" name="name" id="pi-leader-name" placeholder="e.g. Most Rev. Joselito M. Cruz" required>
                </div>

                <div class="parish-grid-2">
                    <div class="parish-form-group">
                        <label><i class="fa-solid fa-tag"></i> Position Category <span class="req-star">*</span></label>
                        <select name="position_type" id="pi-leader-type" required>
                            <option value="bishop">Bishop</option>
                            <option value="parish_priest">Parish Priest</option>
                            <option value="assistant">Assistant Priest</option>
                            <option value="council">Council Member</option>
                            <option value="sectoral">Sectoral Head</option>
                            <option value="executive">Executive</option>
                            <option value="coordinator">Coordinator</option>
                        </select>
                    </div>
                    <div class="parish-form-group">
                        <label><i class="fa-solid fa-id-badge"></i> Display Title <span class="req-star">*</span></label>
                        <input type="text" name="title" id="pi-leader-title" placeholder="e.g. Diocesan Bishop" required>
                    </div>
                </div>

                <div class="parish-form-group">
                    <label><i class="fa-solid fa-align-left"></i> Subtitle / Ministry Role (Optional)</label>
                    <input type="text" name="subtitle" id="pi-leader-subtitle" placeholder="e.g. Chairman, Commission on Liturgy">
                </div>

                <div class="parish-grid-2">
                    <div class="parish-form-group">
                        <label><i class="fa-solid fa-arrow-down-1-9"></i> Sort Order</label>
                        <input type="number" name="sort_order" id="pi-leader-sort" value="0">
                    </div>
                    <div class="parish-form-group" style="justify-content: center; margin-top: 16px;">
                        <label style="cursor: pointer; user-select: none;">
                            <input type="checkbox" name="is_active" id="pi-leader-active" value="1" checked style="width: auto; margin-right: 8px;">
                            Active in Directory
                        </label>
                    </div>
                </div>
            </div>

            <div class="pi-modal-footer">
                <button type="button" class="btn btn-ghost" onclick="closePIModal('leader')">Cancel</button>
                <button type="submit" class="btn btn-primary" style="font-weight: 600;">
                    <i class="fa-solid fa-check mr-1"></i> Save Leader
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════ -->
<!-- SCRIPT: TABS, PREVIEWS, AND MODALS                                  -->
<!-- ═══════════════════════════════════════════════════════════════════ -->
<script>
function switchPITab(tab) {
    document.querySelectorAll('#sec-parish_info .parish-tab-btn').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('#sec-parish_info .parish-tab-pane').forEach(p => p.classList.remove('active'));
    
    const trigger = document.querySelector(`.parish-tab-btn[data-tab="${tab}"]`);
    const pane = document.getElementById(`pi-tab-${tab}`);
    if (trigger) trigger.classList.add('active');
    if (pane) pane.classList.add('active');

    try {
        const url = new URL(window.location);
        url.searchParams.set('tab', tab);
        window.history.replaceState({}, '', url);
    } catch(e) {}
}

function previewCathedralPhoto(input) {
    if (input.files && input.files[0]) {
        const file = input.files[0];
        const filenameEl = document.getElementById('cathedral-photo-filename');
        if (filenameEl) filenameEl.textContent = file.name + ' (' + Math.round(file.size / 1024) + ' KB)';
        
        const reader = new FileReader();
        reader.onload = function(e) {
            const previewImg = document.getElementById('cathedral-photo-preview-img');
            const placeholder = document.getElementById('cathedral-photo-placeholder');
            if (previewImg) {
                previewImg.src = e.target.result;
                previewImg.style.display = 'block';
            }
            if (placeholder) {
                placeholder.style.display = 'none';
            }
        };
        reader.readAsDataURL(file);
    }
}

function handleMapUrlInput(val) {
    let url = (val || '').trim();
    // Auto-clean: if user pasted a full <iframe ... src="..." ...>, extract just the src URL
    const match = url.match(/src=["']([^"']+)["']/i);
    if (match) {
        url = match[1];
        const input = document.getElementById('parish-map-input');
        if (input) input.value = url;
    }
    const iframe = document.getElementById('map-preview-iframe');
    const container = document.getElementById('map-preview-container');
    if (iframe && container) {
        if (url && (url.startsWith('https://') || url.startsWith('http://'))) {
            iframe.src = url;
            container.style.display = 'block';
        } else {
            container.style.display = 'none';
        }
    }
}

function openScheduleModal(id, day='', time='', sort=0, active=1) {
    document.getElementById('pi-sche-id').value = id;
    document.getElementById('pi-sche-day').value = day;
    document.getElementById('pi-sche-time').value = time;
    document.getElementById('pi-sche-sort').value = sort;
    document.getElementById('pi-sche-active').checked = !!active;
    document.getElementById('pi-schedule-mtitle').innerText = id ? 'Edit Mass Schedule' : 'Add Mass Schedule';
    document.getElementById('pi-modal-schedule').classList.add('open');
}

function openHistoryModal(id, year='', title='', desc='', sort=0, active=1) {
    document.getElementById('pi-hist-id').value = id;
    document.getElementById('pi-hist-year').value = year;
    document.getElementById('pi-hist-title').value = title;
    document.getElementById('pi-hist-desc').value = desc;
    document.getElementById('pi-hist-sort').value = sort;
    document.getElementById('pi-hist-active').checked = !!active;
    document.getElementById('pi-history-mtitle').innerText = id ? 'Edit Timeline Milestone' : 'Add Timeline Milestone';
    document.getElementById('pi-modal-history').classList.add('open');
}

function openLeaderModal(id, name='', title='', type='parish_priest', subtitle='', sort=0, active=1, imagePath='') {
    document.getElementById('pi-leader-id').value = id;
    document.getElementById('pi-leader-name').value = name;
    document.getElementById('pi-leader-title').value = title;
    document.getElementById('pi-leader-type').value = type || 'parish_priest';
    document.getElementById('pi-leader-subtitle').value = subtitle;
    document.getElementById('pi-leader-sort').value = sort;
    document.getElementById('pi-leader-active').checked = !!active;
    
    const preview = document.getElementById('pi-leader-img-preview');
    const imgSrc = document.getElementById('pi-leader-img-src');
    if (imagePath) {
        imgSrc.src = 'storage/' + imagePath;
        preview.style.display = 'flex';
    } else {
        imgSrc.src = '';
        preview.style.display = 'none';
    }

    document.getElementById('pi-leader-mtitle').innerText = id ? 'Edit Leader / Clergy' : 'Add Leader / Clergy';
    document.getElementById('pi-modal-leader').classList.add('open');
}

function closePIModal(type) {
    document.getElementById(`pi-modal-${type}`).classList.remove('open');
}
</script>
