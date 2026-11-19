<?php
if (!function_exists('renderRecordFormFields')) {
    function renderRecordFormFields(array $chapels, string $prefix): string {
        // Generate options for chapels
        $chapelOptions = '<option value="">Select Chapel / Cathedral</option>';
        $chapelOptions .= '<option value="0">IFI Cathedral (Main)</option>';
        foreach ($chapels as $ch) {
            $chapelOptions .= '<option value="' . (int)$ch['chapel_id'] . '">' . htmlspecialchars($ch['chapel_name']) . '</option>';
        }

        $html = <<<HTML
        <style>
            /* ── MODERN SACRED RECORD MODAL STYLES ── */
            .record-form-container {
                display: flex;
                flex-direction: column;
                gap: 16px;
                color: var(--text);
                font-family: var(--font-body, 'Inter', sans-serif);
            }

            /* Sacrament Pill Selector Bar */
            .sacrament-selector-card {
                background: var(--bg3, rgba(255, 255, 255, 0.03));
                border: 1px solid var(--border);
                border-radius: 12px;
                padding: 12px 14px;
                display: flex;
                flex-direction: column;
                gap: 8px;
            }
            .sacrament-selector-header {
                display: flex;
                align-items: center;
                justify-content: space-between;
                font-size: 11px;
                font-weight: 700;
                text-transform: uppercase;
                letter-spacing: 0.08em;
                color: var(--text);
            }
            .sacrament-pills-row {
                display: grid;
                grid-template-columns: repeat(5, 1fr);
                gap: 8px;
            }
            @media (max-width: 640px) {
                .sacrament-pills-row {
                    grid-template-columns: repeat(3, 1fr);
                }
            }
            .sacrament-pill-btn {
                background: var(--panel-bg, #FFFFFF);
                border: 1px solid var(--border, #E5E5E5);
                border-radius: 8px;
                padding: 8px 6px;
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                gap: 4px;
                cursor: pointer;
                transition: background 0.15s ease, border-color 0.15s ease;
                color: var(--text2, #737373);
                font-family: inherit;
            }
            .sacrament-pill-btn i {
                font-size: 14px;
            }
            .sacrament-pill-btn span {
                font-size: 11px;
                font-weight: 600;
                letter-spacing: 0.02em;
            }
            .sacrament-pill-btn:hover {
                border-color: var(--accent);
                color: var(--text);
                background: var(--hover-bg, #F6F6F6);
            }
            /* Active Sacrament State */
            .sacrament-pill-btn.active,
            .sacrament-pill-btn[data-type="Baptism"].active,
            .sacrament-pill-btn[data-type="Wedding"].active,
            .sacrament-pill-btn[data-type="Confirmation"].active,
            .sacrament-pill-btn[data-type="Communion"].active,
            .sacrament-pill-btn[data-type="Funeral"].active {
                background: var(--accent, #111111) !important;
                border-color: var(--accent, #111111) !important;
                color: #111111 !important;
                box-shadow: none !important;
                font-weight: 600;
            }

            /* Record Section Sub-Cards */
            .record-card-box {
                background: var(--panel-bg, #FFFFFF);
                border: 1px solid var(--border, #E5E5E5);
                border-radius: 8px;
                padding: 14px 16px;
                display: flex;
                flex-direction: column;
                gap: 12px;
                box-shadow: none;
            }
            .record-card-title {
                display: flex;
                align-items: center;
                gap: 8px;
                font-family: var(--font-family, 'Inter', sans-serif);
                font-size: 11.5px;
                font-weight: 700;
                letter-spacing: 0.04em;
                color: var(--text);
                text-transform: uppercase;
                border-bottom: 1px solid var(--border, #E5E5E5);
                padding-bottom: 8px;
                margin-bottom: 2px;
            }

            /* Input Fields Styling */
            .record-form-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 12px;
            }
            .record-form-group {
                display: flex;
                flex-direction: column;
                gap: 4px;
            }
            .record-form-group.col-full {
                grid-column: 1 / -1;
            }
            .record-field-label {
                font-size: 11px;
                font-weight: 600;
                color: var(--text2, #737373);
                letter-spacing: 0.02em;
                display: flex;
                align-items: center;
                gap: 5px;
            }
            .record-field-label .req {
                color: #D64545;
                font-weight: 700;
            }
            .record-input-wrap {
                position: relative;
                display: flex;
                align-items: center;
            }
            .record-input-wrap i {
                position: absolute;
                left: 11px;
                color: var(--text2, #737373);
                font-size: 13px;
                pointer-events: none;
                opacity: 0.85;
            }
            .record-input-wrap input,
            .record-input-wrap select,
            .record-input-wrap textarea {
                width: 100%;
                background: var(--input-bg, #FFFFFF) !important;
                border: 1px solid var(--border, #E5E5E5) !important;
                border-radius: 6px !important;
                padding: 8px 12px 8px 34px !important;
                color: var(--text, #111111) !important;
                font-family: inherit !important;
                font-size: 12.5px !important;
                font-weight: 400 !important;
                outline: none !important;
                transition: border-color 0.15s ease !important;
                box-shadow: none !important;
            }
            .record-input-wrap textarea {
                padding-left: 12px !important;
                min-height: 80px;
                resize: vertical;
            }
            .record-input-wrap input:focus,
            .record-input-wrap select:focus,
            .record-input-wrap textarea:focus {
                border-color: var(--accent) !important;
                box-shadow: 0 0 0 2px rgba(197, 160, 89, 0.15) !important;
            }
            .record-input-wrap select {
                cursor: pointer;
            }

            /* Quick Baptism Intake Banner */
            .baptism-intake-shortcut {
                background: linear-gradient(135deg, rgba(56, 189, 248, 0.12), rgba(56, 189, 248, 0.04));
                border: 1px dashed rgba(56, 189, 248, 0.4);
                border-radius: 8px;
                padding: 10px 14px;
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 12px;
                margin-top: 4px;
            }
            .baptism-intake-shortcut a {
                color: var(--accent);
                text-decoration: none;
                font-size: 11.5px;
                font-weight: 700;
                display: inline-flex;
                align-items: center;
                gap: 5px;
            }
            .baptism-intake-shortcut a:hover {
                text-decoration: underline;
            }

            /* Dark mode overrides for modal */
            [data-theme="dark"] .sacrament-selector-card {
                background: #141f33;
                border-color: var(--border);
            }
            [data-theme="dark"] .sacrament-pill-btn {
                background: #1e293b;
                border-color: var(--border);
                color: var(--text2);
            }
            [data-theme="dark"] .sacrament-pill-btn:hover {
                background: #25334a;
                border-color: var(--accent);
                color: var(--text);
            }
            [data-theme="dark"] .sacrament-pill-btn.active {
                background: var(--accent) !important;
                border-color: var(--accent) !important;
                color: #111111 !important;
            }
            [data-theme="dark"] .record-card-box {
                background: #162238 !important;
                border-color: var(--border) !important;
            }
            [data-theme="dark"] .record-card-title {
                color: var(--text) !important;
                border-bottom-color: var(--border) !important;
            }
            [data-theme="dark"] .record-field-label {
                color: var(--text2) !important;
            }
            [data-theme="dark"] .record-input-wrap input,
            [data-theme="dark"] .record-input-wrap select,
            [data-theme="dark"] .record-input-wrap textarea {
                background: #1e293b !important;
                border-color: var(--border) !important;
                color: var(--text) !important;
            }
            [data-theme="dark"] .record-input-wrap input:focus,
            [data-theme="dark"] .record-input-wrap select:focus,
            [data-theme="dark"] .record-input-wrap textarea:focus {
                border-color: var(--accent) !important;
                box-shadow: 0 0 0 2px rgba(197, 160, 89, 0.25) !important;
            }
        </style>

        <div class="record-form-container">
            
            <!-- ── SACRAMENT TYPE PILL SELECTOR ── -->
            <div class="sacrament-selector-card">
                <div class="sacrament-selector-header">
                    <span><i class="fa-solid fa-cross"></i> Select Sacrament Type</span>
                    <span id="{$prefix}-active-type-label" style="font-size: 10px; color: var(--text); font-weight: 700; letter-spacing: 0.06em;">BAPTISM RECORD PARTICULARS</span>
                </div>
                <div id="{$prefix}-pills" class="sacrament-pills-row">
                    <button type="button" class="sacrament-pill-btn active" data-type="Baptism" onclick="selectSacramentPill('{$prefix}', 'Baptism')">
                        <i class="fa-solid fa-droplet"></i>
                        <span>Baptism</span>
                    </button>
                    <button type="button" class="sacrament-pill-btn" data-type="Wedding" onclick="selectSacramentPill('{$prefix}', 'Wedding')">
                        <i class="fa-solid fa-ring"></i>
                        <span>Wedding</span>
                    </button>
                    <button type="button" class="sacrament-pill-btn" data-type="Confirmation" onclick="selectSacramentPill('{$prefix}', 'Confirmation')">
                        <i class="fa-solid fa-dove"></i>
                        <span>Confirmation</span>
                    </button>
                    <button type="button" class="sacrament-pill-btn" data-type="Communion" onclick="selectSacramentPill('{$prefix}', 'Communion')">
                        <i class="fa-solid fa-bread-slice"></i>
                        <span>Communion</span>
                    </button>
                    <button type="button" class="sacrament-pill-btn" data-type="Funeral" onclick="selectSacramentPill('{$prefix}', 'Funeral')">
                        <i class="fa-solid fa-cross"></i>
                        <span>Funeral</span>
                    </button>
                </div>
                <!-- Native select preserved for form submission & automated script compatibility -->
                <select name="record_type" id="{$prefix}-rec-type" onchange="toggleRecordFields('{$prefix}')" required style="display:none;">
                    <option value="Baptism" selected>Baptism</option>
                    <option value="Wedding">Wedding</option>
                    <option value="Confirmation">Confirmation</option>
                    <option value="Communion">First Communion</option>
                    <option value="Funeral">Funeral</option>
                </select>
            </div>

            <!-- ── CARD 1: PRIMARY REGISTRY IDENTIFICATION ── -->
            <div class="record-card-box">
                <div class="record-card-title">
                    <i class="fa-solid fa-feather-pointed"></i> Primary Record Particulars
                </div>
                <div class="record-form-grid">
                    <div class="record-form-group col-full">
                        <label class="record-field-label">
                            <i class="fa-regular fa-user"></i> Full Name of Subject <span class="req">*</span>
                        </label>
                        <div class="record-input-wrap">
                            <i class="fa-solid fa-user"></i>
                            <input type="text" name="person_name" id="{$prefix}-person-name" maxlength="80" placeholder="e.g. Maria Clara Santos" required oninput="syncPersonName('{$prefix}', this.value)">
                        </div>
                    </div>

                    <div class="record-form-group">
                        <label class="record-field-label">
                            <i class="fa-regular fa-calendar-days"></i> Sacrament Event Date <span class="req">*</span>
                        </label>
                        <div class="record-input-wrap">
                            <i class="fa-solid fa-calendar-day"></i>
                            <input type="date" name="event_date" id="{$prefix}-event-date" min="1900-01-01" max="2099-12-31" required>
                        </div>
                    </div>

                    <div class="record-form-group">
                        <label class="record-field-label">
                            <i class="fa-solid fa-church"></i> Location / Chapel
                        </label>
                        <div class="record-input-wrap">
                            <i class="fa-solid fa-location-dot"></i>
                            <select name="chapel_id" id="{$prefix}-chapel">
                                {$chapelOptions}
                            </select>
                        </div>
                    </div>

                    <div class="record-form-group">
                        <label class="record-field-label">
                            <i class="fa-solid fa-user-tie"></i> Officiating Priest / Minister
                        </label>
                        <div class="record-input-wrap">
                            <i class="fa-solid fa-cross"></i>
                            <input type="text" name="officiating_priest" id="{$prefix}-priest" maxlength="70" placeholder="e.g. Rev. Fr. Alexander Cruz">
                        </div>
                    </div>

                    <div class="record-form-group">
                        <label class="record-field-label">
                            <i class="fa-solid fa-book-bookmark"></i> Registry Book Number
                        </label>
                        <div class="record-input-wrap">
                            <i class="fa-solid fa-book"></i>
                            <input type="text" name="book_number" id="{$prefix}-book" maxlength="25" placeholder="e.g. Book B-12">
                        </div>
                    </div>

                    <div class="record-form-group">
                        <label class="record-field-label">
                            <i class="fa-solid fa-file-lines"></i> Folio / Page Number
                        </label>
                        <div class="record-input-wrap">
                            <i class="fa-solid fa-file"></i>
                            <input type="text" name="page_number" id="{$prefix}-page" maxlength="25" placeholder="e.g. Page 45">
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── CARD 2: SACRAMENTAL SPECIFIC DETAILS ── -->

            <!-- RECORD TYPE: Baptism -->
            <div id="{$prefix}-type-Baptism" class="record-type-fields">
                <div class="record-card-box">
                    <div class="record-card-title">
                        <i class="fa-solid fa-droplet" style="color: var(--accent);"></i> Holy Baptism Details
                    </div>
                    <div class="record-form-grid">
                        <div class="record-form-group col-full">
                            <label class="record-field-label">Full Name of Child / Baptized</label>
                            <div class="record-input-wrap">
                                <i class="fa-solid fa-baby"></i>
                                <input type="text" name="child_name" id="{$prefix}-bap-child" maxlength="70" placeholder="Child's baptismal name" oninput="syncChildName('{$prefix}', this.value)">
                            </div>
                        </div>

                        <div class="record-form-group">
                            <label class="record-field-label">Date of Birth</label>
                            <div class="record-input-wrap">
                                <i class="fa-solid fa-cake-candles"></i>
                                <input type="date" name="date_of_birth" id="{$prefix}-bap-dob" min="1900-01-01" max="2099-12-31">
                            </div>
                        </div>

                        <div class="record-form-group">
                            <label class="record-field-label">Place of Birth</label>
                            <div class="record-input-wrap">
                                <i class="fa-solid fa-hospital"></i>
                                <input type="text" name="place_of_birth" id="{$prefix}-bap-pob" list="dl-ph-all-cities" maxlength="100" placeholder="e.g. Manila" autocomplete="off">
                            </div>
                        </div>

                        <div class="record-form-group">
                            <label class="record-field-label">Parents' Residence / Address</label>
                            <div class="record-input-wrap">
                                <i class="fa-solid fa-house"></i>
                                <input type="text" name="baptism_parents_residence" id="{$prefix}-bap-res" maxlength="150" placeholder="e.g. Barangay San Roque, Angono">
                            </div>
                        </div>

                        <div class="record-form-group">
                            <label class="record-field-label">Legitimacy</label>
                            <div class="record-input-wrap">
                                <i class="fa-solid fa-stamp"></i>
                                <select name="baptism_legitimacy" id="{$prefix}-bap-legit">
                                    <option value="Legitimate">Legitimate</option>
                                    <option value="Illegitimate">Illegitimate</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Shortcut to Official Baptismal Form -->
                    <div class="baptism-intake-shortcut">
                        <div style="font-size: 11.5px; color: var(--text2);">
                            <i class="fa-solid fa-circle-info" style="color: var(--accent); margin-right: 4px;"></i>
                            Need 1:1 physical certificate intake with printable replica?
                        </div>
                        <a href="baptism_form.php">
                            <span>Open Baptismal Form</span> <i class="fa-solid fa-arrow-right"></i>
                        </a>
                    </div>
                </div>
            </div>

            <!-- RECORD TYPE: Wedding -->
            <div id="{$prefix}-type-Wedding" class="record-type-fields" style="display:none;">
                <div class="record-card-box">
                    <div class="record-card-title">
                        <i class="fa-solid fa-ring" style="color: var(--accent, #c5a059);"></i> Holy Matrimony Details
                    </div>
                    <div style="font-size: 11px; margin-bottom: 12px; color: var(--text2); background: rgba(197, 160, 89, 0.08); padding: 8px 12px; border-radius: 6px; border: 1px solid rgba(197, 160, 89, 0.25); display: flex; align-items: center; justify-content: space-between;">
                        <span><i class="fa-solid fa-file-contract mr-6" style="color: var(--accent, #c5a059);"></i> Need 1:1 physical Application for Marriage License (Municipal Form 90)?</span>
                        <a href="marriage_form.php" style="color: var(--accent, #c5a059); font-weight:700; text-decoration:underline;">Open Marriage Form &rarr;</a>
                    </div>
                    <div class="record-form-grid">
                        <div class="record-form-group">
                            <label class="record-field-label">Groom's Full Name</label>
                            <div class="record-input-wrap">
                                <i class="fa-solid fa-mars"></i>
                                <input type="text" name="groom_name" id="{$prefix}-wed-groom" maxlength="70" placeholder="Groom's legal name">
                            </div>
                        </div>

                        <div class="record-form-group">
                            <label class="record-field-label">Bride's Full Name</label>
                            <div class="record-input-wrap">
                                <i class="fa-solid fa-venus"></i>
                                <input type="text" name="bride_name" id="{$prefix}-wed-bride" maxlength="70" placeholder="Bride's maiden name">
                            </div>
                        </div>

                        <div class="record-form-group">
                            <label class="record-field-label">Groom's Parents</label>
                            <div class="record-input-wrap">
                                <i class="fa-solid fa-user-group"></i>
                                <input type="text" name="wedding_groom_parents" id="{$prefix}-wed-gp" maxlength="120" placeholder="Father and Mother of Groom">
                            </div>
                        </div>

                        <div class="record-form-group">
                            <label class="record-field-label">Bride's Parents</label>
                            <div class="record-input-wrap">
                                <i class="fa-solid fa-user-group"></i>
                                <input type="text" name="wedding_bride_parents" id="{$prefix}-wed-bp" maxlength="120" placeholder="Father and Mother of Bride">
                            </div>
                        </div>

                        <div class="record-form-group">
                            <label class="record-field-label">Date of Marriage</label>
                            <div class="record-input-wrap">
                                <i class="fa-solid fa-calendar-day"></i>
                                <input type="date" name="date_of_marriage" id="{$prefix}-wed-date" min="1900-01-01" max="2099-12-31">
                            </div>
                        </div>

                        <div class="record-form-group">
                            <label class="record-field-label">Place of Solemnization</label>
                            <div class="record-input-wrap">
                                <i class="fa-solid fa-church"></i>
                                <input type="text" name="place_of_marriage" id="{$prefix}-wed-loc" maxlength="100" placeholder="Cathedral or Chapel venue">
                            </div>
                        </div>

                        <div class="record-form-group">
                            <label class="record-field-label">Principal Witness / Sponsor 1</label>
                            <div class="record-input-wrap">
                                <i class="fa-solid fa-signature"></i>
                                <input type="text" name="witness_1" id="{$prefix}-wed-w1" maxlength="70" placeholder="Principal Sponsor 1">
                            </div>
                        </div>

                        <div class="record-form-group">
                            <label class="record-field-label">Principal Witness / Sponsor 2</label>
                            <div class="record-input-wrap">
                                <i class="fa-solid fa-signature"></i>
                                <input type="text" name="witness_2" id="{$prefix}-wed-w2" maxlength="70" placeholder="Principal Sponsor 2">
                            </div>
                        </div>

                        <!-- Civil Registry & Marriage License Prerequisite Fields -->
                        <div class="record-form-group">
                            <label class="record-field-label">Marriage License No. (Form 90)</label>
                            <div class="record-input-wrap">
                                <i class="fa-solid fa-certificate"></i>
                                <input type="text" name="marriage_license_no" id="{$prefix}-wed-lic-no" maxlength="30" placeholder="e.g. ML-2026-0819">
                            </div>
                        </div>

                        <div class="record-form-group">
                            <label class="record-field-label">License Date of Issuance</label>
                            <div class="record-input-wrap">
                                <i class="fa-solid fa-calendar-check"></i>
                                <input type="date" name="license_date_issued" id="{$prefix}-wed-lic-date" min="1900-01-01" max="2099-12-31">
                            </div>
                        </div>

                        <div class="record-form-group">
                            <label class="record-field-label">Place of Issuance / Registry No.</label>
                            <div class="record-input-wrap">
                                <i class="fa-solid fa-landmark"></i>
                                <input type="text" name="license_place_issued" id="{$prefix}-wed-lic-place" list="dl-ph-all-cities" maxlength="100" placeholder="e.g. Angono, Rizal / Reg #1234" autocomplete="off">
                            </div>
                        </div>

                        <div class="record-form-group">
                            <label class="record-field-label">Groom Civil Status</label>
                            <div class="record-input-wrap">
                                <i class="fa-solid fa-user-tag"></i>
                                <select name="groom_civil_status" id="{$prefix}-wed-groom-civ">
                                    <option value="Single">Single</option>
                                    <option value="Widowed">Widowed</option>
                                    <option value="Divorced">Divorced / Annulled</option>
                                </select>
                            </div>
                        </div>

                        <div class="record-form-group">
                            <label class="record-field-label">Bride Civil Status</label>
                            <div class="record-input-wrap">
                                <i class="fa-solid fa-user-tag"></i>
                                <select name="bride_civil_status" id="{$prefix}-wed-bride-civ">
                                    <option value="Single">Single</option>
                                    <option value="Widowed">Widowed</option>
                                    <option value="Divorced">Divorced / Annulled</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- RECORD TYPE: Funeral -->
            <div id="{$prefix}-type-Funeral" class="record-type-fields" style="display:none;">
                <div class="record-card-box">
                    <div class="record-card-title">
                        <i class="fa-solid fa-cross" style="color: #94a3b8;"></i> Christian Burial Details
                    </div>
                    <div class="record-form-grid">
                        <div class="record-form-group col-full">
                            <label class="record-field-label">Name of Deceased</label>
                            <div class="record-input-wrap">
                                <i class="fa-solid fa-user-slash"></i>
                                <input type="text" name="deceased_name" id="{$prefix}-fun-dec" maxlength="70" placeholder="Full name of deceased">
                            </div>
                        </div>

                        <div class="record-form-group">
                            <label class="record-field-label">Date of Death</label>
                            <div class="record-input-wrap">
                                <i class="fa-solid fa-calendar-xmark"></i>
                                <input type="date" name="date_of_death" id="{$prefix}-fun-dod" min="1900-01-01" max="2099-12-31">
                            </div>
                        </div>

                        <div class="record-form-group">
                            <label class="record-field-label">Age at Death</label>
                            <div class="record-input-wrap">
                                <i class="fa-solid fa-hashtag"></i>
                                <input type="number" name="age_at_death" id="{$prefix}-fun-age" min="0" max="130" step="1" inputmode="numeric" placeholder="Age in years" oninput="if(parseInt(this.value,10)>130)this.value=130; if(parseInt(this.value,10)<0)this.value=0;">
                            </div>
                        </div>

                        <div class="record-form-group">
                            <label class="record-field-label">Civil Status</label>
                            <div class="record-input-wrap">
                                <i class="fa-solid fa-id-badge"></i>
                                <select name="funeral_marital_status" id="{$prefix}-fun-marital">
                                    <option value="Single">Single</option>
                                    <option value="Married">Married</option>
                                    <option value="Widowed">Widowed</option>
                                </select>
                            </div>
                        </div>

                        <div class="record-form-group">
                            <label class="record-field-label">Occupation</label>
                            <div class="record-input-wrap">
                                <i class="fa-solid fa-briefcase"></i>
                                <input type="text" name="funeral_occupation" id="{$prefix}-fun-occ" maxlength="60" placeholder="Occupation / profession">
                            </div>
                        </div>

                        <div class="record-form-group">
                            <label class="record-field-label">Cause of Death</label>
                            <div class="record-input-wrap">
                                <i class="fa-solid fa-heart-pulse"></i>
                                <input type="text" name="funeral_cause_of_death" id="{$prefix}-fun-cause" maxlength="100" placeholder="Medical or registered cause">
                            </div>
                        </div>

                        <div class="record-form-group">
                            <label class="record-field-label">Date of Burial / Funeral Mass</label>
                            <div class="record-input-wrap">
                                <i class="fa-solid fa-calendar-check"></i>
                                <input type="date" name="date_of_funeral" id="{$prefix}-fun-date-mass" min="1900-01-01" max="2099-12-31">
                            </div>
                        </div>

                        <div class="record-form-group">
                            <label class="record-field-label">Place of Burial / Cemetery</label>
                            <div class="record-input-wrap">
                                <i class="fa-solid fa-monument"></i>
                                <input type="text" name="place_of_burial" id="{$prefix}-fun-burial" maxlength="100" placeholder="Cemetery or memorial park">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- RECORD TYPE: Confirmation Specifics -->
            <div id="{$prefix}-type-Confirmation" class="record-type-fields" style="display:none;">
                <div class="record-card-box">
                    <div class="record-card-title">
                        <i class="fa-solid fa-dove" style="color: var(--accent, #c5a059);"></i> Holy Confirmation Details
                    </div>
                    <div style="font-size: 11px; margin-bottom: 12px; color: var(--text2); background: rgba(197, 160, 89, 0.08); padding: 8px 12px; border-radius: 6px; border: 1px solid rgba(197, 160, 89, 0.25); display: flex; align-items: center; justify-content: space-between;">
                        <span><i class="fa-solid fa-file-lines mr-6" style="color: var(--accent, #c5a059);"></i> Need to print or fill out the official 1:1 Cathedral Confirmation Form sheet?</span>
                        <a href="confirmation_form.php" style="color: var(--accent, #c5a059); font-weight:700; text-decoration:underline;">Open Confirmation Form &rarr;</a>
                    </div>
                    <div class="record-form-grid">
                        <div class="record-form-group col-full">
                            <label class="record-field-label">Place / Parish of Prior Baptism</label>
                            <div class="record-input-wrap">
                                <i class="fa-solid fa-church"></i>
                                <input type="text" name="confirmation_place_of_baptism" id="{$prefix}-con-pobap" maxlength="100" placeholder="Parish where candidate was previously baptized">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── CARD 3: SHARED FAMILY DETAILS (PARENTS) ── -->
            <div id="{$prefix}-section-family" class="record-type-fields">
                <div class="record-card-box">
                    <div class="record-card-title">
                        <i class="fa-solid fa-people-roof"></i> Parents &amp; Family Information
                    </div>
                    <div class="record-form-grid">
                        <div class="record-form-group">
                            <label class="record-field-label"><i class="fa-solid fa-person"></i> Father's Full Name</label>
                            <div class="record-input-wrap">
                                <i class="fa-solid fa-person"></i>
                                <input type="text" name="father_name" id="{$prefix}-father" maxlength="70" placeholder="Full name of father">
                            </div>
                        </div>
                        <div class="record-form-group">
                            <label class="record-field-label"><i class="fa-solid fa-person-dress"></i> Mother's Maiden Name</label>
                            <div class="record-input-wrap">
                                <i class="fa-solid fa-person-dress"></i>
                                <input type="text" name="mother_name" id="{$prefix}-mother" maxlength="70" placeholder="Maiden name of mother">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── CARD 4: SHARED SPONSORS (GODPARENTS) ── -->
            <div id="{$prefix}-section-sponsors" class="record-type-fields">
                <div class="record-card-box">
                    <div class="record-card-title">
                        <i class="fa-solid fa-award"></i> Sponsors &amp; Godparents
                    </div>
                    <div class="record-form-grid">
                        <div class="record-form-group">
                            <label class="record-field-label">Godfather (Principal Sponsor)</label>
                            <div class="record-input-wrap">
                                <i class="fa-solid fa-user-shield"></i>
                                <input type="text" name="godfather" id="{$prefix}-godfather" maxlength="70" placeholder="Full name of godfather">
                            </div>
                        </div>
                        <div class="record-form-group">
                            <label class="record-field-label">Godmother (Principal Sponsor)</label>
                            <div class="record-input-wrap">
                                <i class="fa-solid fa-user-shield"></i>
                                <input type="text" name="godmother" id="{$prefix}-godmother" maxlength="70" placeholder="Full name of godmother">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── CARD 5: MARGINAL NOTES & REMARKS ── -->
            <div class="record-card-box">
                <div class="record-card-title">
                    <i class="fa-solid fa-pen-nib"></i> Marginal Notes &amp; Remarks
                </div>
                <div class="record-form-group col-full">
                    <div class="record-input-wrap">
                        <textarea name="notes" id="{$prefix}-notes" maxlength="1000" placeholder="Marginal remarks (e.g. Confirmed at IFI Cathedral on..., Married to... on..., or decree annotations)"></textarea>
                    </div>
                    <div style="font-size: 11px; color: var(--text2); margin-top: 4px; display: flex; align-items: center; gap: 4px;">
                        <i class="fa-solid fa-circle-info" style="color: #737373;"></i>
                        <span>Notations appear in official sacramental certificates and parish archive extracts.</span>
                    </div>
                </div>
            </div>

        </div>

        <script>
            // Two-way synchronization helpers between Person Name & Sacrament Subjects
            function syncPersonName(prefix, val) {
                const typeSelect = document.getElementById(prefix + '-rec-type');
                const t = typeSelect ? typeSelect.value : '';
                if (t === 'Baptism') {
                    const child = document.getElementById(prefix + '-bap-child');
                    if (child && !child.value) child.value = val;
                } else if (t === 'Funeral') {
                    const dec = document.getElementById(prefix + '-fun-dec');
                    if (dec && !dec.value) dec.value = val;
                }
            }

            function syncChildName(prefix, val) {
                const pName = document.getElementById(prefix + '-person-name');
                if (pName && (!pName.value || pName.value === val.substring(0, val.length - 1))) {
                    pName.value = val;
                }
            }

            // Input symbol sanitizers for Sacramental Records
            (function initRecordInputSanitizers() {
                const nameInputs = [
                    'person-name', 'priest', 'bap-child', 'wed-groom', 'wed-bride',
                    'wed-gp', 'wed-bp', 'wed-w1', 'wed-w2', 'fun-dec', 'father', 'mother',
                    'bap-godfather', 'bap-godmother', 'con-godfather', 'con-godmother'
                ];
                ['add', 'edit'].forEach(pfx => {
                    nameInputs.forEach(fld => {
                        const el = document.getElementById(pfx + '-' + fld);
                        if (el && !el.dataset.sanitized) {
                            el.dataset.sanitized = 'true';
                            el.addEventListener('input', function() {
                                this.value = this.value.replace(/[^a-zA-ZñÑ\s\.\-']/g, '');
                            });
                        }
                    });
                    const codeInputs = ['book', 'page', 'wed-lic-no'];
                    codeInputs.forEach(fld => {
                        const el = document.getElementById(pfx + '-' + fld);
                        if (el && !el.dataset.sanitized) {
                            el.dataset.sanitized = 'true';
                            el.addEventListener('input', function() {
                                this.value = this.value.replace(/[^a-zA-Z0-9\s\-\.\/]/g, '').toUpperCase();
                            });
                        }
                    });
                });
            })();
        </script>
        HTML;

        return $html;
    }
}
