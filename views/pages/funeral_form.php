<?php
/**
 * View Template: Official Cathedral Funeral & Christian Burial Form
 * Interactive Digital Intake Form on Left + Real-time 1:1 Paper Replica Preview on Right
 * Matches St. Michael's Cathedral Official Burial Certificate & Register
 */

$decFirst   = $metadata['deceased_first_name'] ?? '';
$decMI      = $metadata['deceased_mi'] ?? '';
$decSurname = $metadata['deceased_surname'] ?? '';

$nameSource = !empty($existingRecord['deceased_name']) ? $existingRecord['deceased_name'] : (!empty($existingRecord['person_name']) ? $existingRecord['person_name'] : '');
if (!$decFirst && $nameSource) {
    $parts = explode(' ', trim($nameSource));
    if (count($parts) >= 3) {
        $decSurname = array_pop($parts);
        $decMI      = array_pop($parts);
        $decFirst   = implode(' ', $parts);
    } elseif (count($parts) === 2) {
        $decSurname = $parts[1];
        $decFirst   = $parts[0];
    } else {
        $decFirst   = $nameSource;
    }
}
$decFullInit = preg_replace('/\s+/', ' ', trim("$decFirst $decMI $decSurname")) ?: ($nameSource ?: '');

$eventDate      = $existingRecord['date_of_funeral'] ?: ($existingRecord['event_date'] ?? date('Y-m-d'));
$funeralTime    = $metadata['funeral_time'] ?? '';
$dateOfDeath    = $existingRecord['date_of_death'] ?? '';
$ageAtDeath     = $existingRecord['age_at_death'] ?? '';
$gender         = $metadata['gender'] ?? 'Male';
$maritalStatus  = $existingRecord['funeral_marital_status'] ?? 'Single';
$occupation     = $existingRecord['funeral_occupation'] ?? '';
$causeOfDeath   = $existingRecord['funeral_cause_of_death'] ?? '';
$placeOfBurial  = $existingRecord['place_of_burial'] ?? '';
$residence      = $metadata['residence'] ?? '';
$nextOfKin      = $metadata['next_of_kin'] ?? '';
$contactNo      = $metadata['contact_no'] ?? '';
$marginalNotes  = $metadata['marginal_notes'] ?? '';

$officiatingPriest = $existingRecord['officiating_priest'] ?? 'Very Rev. Fr. Ramoncito Tadepa';
$bookNumber        = $existingRecord['book_number'] ?? 'BK-' . date('Y');
$pageNumber        = $existingRecord['page_number'] ?? 'PG-01';
$scope             = $existingRecord['scope'] ?? ($_GET['scope'] ?? ($isAdmin ? 'cathedral' : 'chapel'));
if (!in_array($scope, ['cathedral', 'chapel'], true)) { $scope = 'cathedral'; }
$refId = $existingRecord['reference_id'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Official Christian Burial Form | IFI Cathedral of St. Michael the Archangel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@500;600;700;800&family=EB+Garamond:ital,wght@0,400;0,600;1,400&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/index.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="assets/css/workspace.css?v=2.5">
    <script src="assets/js/ph-geo-data.js?v=<?php echo time(); ?>"></script>
    <link rel="icon" type="image/x-icon" href="assets/img/2323.png">

    <script>
      (function () {
        const savedTheme = localStorage.getItem('theme') || localStorage.getItem('portalTheme') || 'dark';
        document.documentElement.setAttribute('data-theme', savedTheme);
      })();
    </script>

    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 0;
            background: var(--bg);
            color: var(--text);
            font-family: var(--font-body, 'Inter', sans-serif);
            height: 100vh;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        /* ── TOP ACTION BAR ── */
        .conf-topbar {
            height: 52px;
            background: var(--bg2);
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 16px;
            flex-shrink: 0;
            z-index: 50;
        }
        .conf-topbar-title {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .conf-topbar-title h2 {
            font-family: var(--font-heading, 'Cinzel', serif);
            font-size: 1.05rem;
            margin: 0;
            color: var(--accent);
            font-weight: 700;
        }

        /* ── MAIN WORKSPACE SPLIT ── */
        .workspace-split-container {
            flex: 1;
            display: grid;
            grid-template-columns: 48% 52%;
            height: calc(100vh - 52px);
            overflow: hidden;
            transition: grid-template-columns 0.25s ease;
        }
        .workspace-split-container.preview-hidden {
            grid-template-columns: 100% 0%;
        }

        /* ── LEFT: FORM PANEL ── */
        .form-side-panel {
            height: 100%;
            overflow-y: auto;
            padding: 16px;
            background: var(--bg);
            border-right: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            gap: 14px;
        }
        .form-section-card {
            background: var(--bg2);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 12px 14px;
            display: flex;
            flex-direction: column;
            gap: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
            margin-bottom: 12px;
        }
        .form-section-title {
            font-size: 0.76rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--accent);
            padding-bottom: 4px;
            border-bottom: 1px solid rgba(201,168,76,0.25);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .field-group {
            display: flex;
            flex-direction: column;
            gap: 3px;
        }
        .field-group label {
            font-size: 0.72rem;
            font-weight: 600;
            color: var(--text2);
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }
        .field-input, .field-select, .field-textarea {
            width: 100%;
            height: 32px;
            padding: 4px 8px;
            background: var(--bg3);
            border: 1px solid var(--border);
            border-radius: 6px;
            color: var(--text);
            font-size: 12px;
            outline: none;
            transition: border-color 0.15s ease;
            font-family: inherit;
        }
        .field-textarea {
            height: 60px;
            padding: 6px 8px;
            resize: vertical;
        }
        .field-input:focus, .field-select:focus, .field-textarea:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 2px rgba(201,168,76,0.2);
        }

        /* ── RIGHT: PAPER PREVIEW PANEL ── */
        .preview-side-panel {
            height: 100%;
            overflow-y: auto;
            background: #2a2c30;
            padding: 24px;
            display: flex;
            justify-content: center;
            align-items: flex-start;
        }
        .workspace-split-container.preview-hidden .preview-side-panel {
            display: none;
        }

        /* ── PIXEL-PERFECT PHYSICAL PAPER SHEET (1:1 Replica) ── */
        .official-paper-sheet {
            width: 100%;
            max-width: 794px;
            background: #ffffff;
            color: #1a1a1a;
            box-shadow: 0 12px 40px rgba(0,0,0,0.6);
            padding: 34px 40px;
            box-sizing: border-box;
            border-radius: 2px;
            font-family: 'Arial', 'Helvetica', sans-serif;
            position: relative;
        }
        .paper-header {
            text-align: center;
            margin-bottom: 14px;
        }
        .paper-seal {
            width: 48px;
            height: 48px;
            object-fit: contain;
            margin-bottom: 2px;
        }
        .paper-cathedral-title {
            font-family: 'Cinzel', serif;
            font-size: 15px;
            font-weight: 700;
            color: #222222;
            letter-spacing: 0.5px;
            margin: 0;
            text-transform: uppercase;
        }
        .paper-church-sub {
            font-family: 'EB Garamond', Garamond, 'Times New Roman', serif;
            font-size: 13px;
            font-style: italic;
            color: #444444;
            margin-top: 1px;
        }
        .paper-church-loc {
            font-size: 11px;
            color: #555555;
            margin-top: 1px;
        }
        .paper-form-title {
            text-align: center;
            font-family: 'Cinzel', 'Arial Black', sans-serif;
            font-size: 18px;
            font-weight: 800;
            color: #b91c1c;
            letter-spacing: 1.5px;
            margin: 14px 0 8px;
            text-transform: uppercase;
        }
        .paper-preamble {
            text-align: center;
            font-size: 12.5px;
            line-height: 1.55;
            color: #222222;
            margin-bottom: 14px;
        }
        .paper-preamble strong.deceased-hero {
            display: block;
            font-family: 'Cinzel', serif;
            font-size: 16px;
            margin: 3px 0;
            color: #111111;
            letter-spacing: 0.5px;
        }
        .paper-section-tag {
            font-size: 11px;
            font-weight: 700;
            color: #222222;
            margin: 10px 0 4px;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }
        .paper-grid-table {
            width: 100%;
            border-collapse: collapse;
            border: 1.5px solid #222222;
            margin-bottom: 10px;
        }
        .paper-grid-table td {
            border: 1px solid #222222;
            padding: 6px 9px;
            vertical-align: top;
            font-size: 11.5px;
        }
        .paper-label {
            display: block;
            font-size: 9.5px;
            font-weight: 700;
            color: #222222;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            margin-bottom: 2px;
        }
        .paper-val {
            font-family: 'Courier New', Courier, monospace;
            font-size: 12.5px;
            font-weight: 700;
            color: #111111;
            min-height: 17px;
            line-height: 1.25;
            word-break: break-word;
        }
        .paper-footer {
            margin-top: 18px;
            font-size: 11px;
        }
        .sec-note {
            font-size: 9.5px;
            font-style: italic;
            color: #666666;
            margin-bottom: 8px;
        }
        .paper-verification-strip {
            margin-top: 16px;
            padding-top: 8px;
            border-top: 1px dashed #bbb;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 9.5px;
            color: #666;
        }

        /* ── PRINT MEDIA RULES ── */
        @media print {
            body {
                display: block !important;
                background: #ffffff !important;
                padding: 0 !important;
                height: auto !important;
                overflow: visible !important;
            }
            .conf-topbar, .form-side-panel, .flash-message-bar {
                display: none !important;
            }
            .workspace-split-container,
            .workspace-split-container.preview-hidden {
                display: block !important;
                height: auto !important;
                overflow: visible !important;
            }
            .preview-side-panel,
            .workspace-split-container.preview-hidden .preview-side-panel {
                background: transparent !important;
                padding: 0 !important;
                display: block !important;
                height: auto !important;
                overflow: visible !important;
                visibility: visible !important;
            }
            .official-paper-sheet,
            .official-paper-sheet * {
                visibility: visible !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .official-paper-sheet {
                box-shadow: none !important;
                padding: 18mm 15mm !important;
                max-width: 100% !important;
                border-radius: 0 !important;
            }
        }
    </style>
</head>
<body>

    <!-- ── TOPBAR ── -->
    <div class="conf-topbar">
        <div class="conf-topbar-title">
            <a href="dashboard.php?section=records_<?php echo $scope; ?>" class="btn btn-ghost btn-sm" style="font-size: 11px; height: 30px; display: inline-flex; align-items: center; gap: 6px; border: 1px solid var(--border);">
                <i class="fa-solid fa-arrow-left"></i> Back to Registry
            </a>
            <img src="assets/img/2323.png" alt="Cathedral Seal" style="width: 24px; height: 24px; object-fit: contain;">
            <h2>Cathedral Funeral &amp; Burial Form</h2>
            <?php if ($refId): ?>
                <span class="badge" style="background: rgba(201,168,76,0.12); color: var(--accent); border: 1px solid rgba(201,168,76,0.3); font-size: 10px; padding: 2px 8px;">
                    <?php echo htmlspecialchars($refId); ?>
                </span>
            <?php endif; ?>
        </div>

        <div style="display: flex; align-items: center; gap: 8px;">
            <button type="button" class="btn btn-ghost btn-sm" onclick="togglePaperPreview()" id="toggle-preview-btn" style="font-size: 11px; height: 32px; display: inline-flex; align-items: center; gap: 6px; border: 1px solid var(--border);">
                <i class="fa-solid fa-columns"></i> <span id="toggle-preview-text">Hide Paper Preview</span>
            </button>
            <button type="button" class="btn btn-secondary btn-sm" onclick="window.print()" style="font-size: 11px; height: 32px; display: inline-flex; align-items: center; gap: 6px;">
                <i class="fa-solid fa-print"></i> Print Official Certificate
            </button>
            <button type="submit" form="fun-digital-form" class="btn btn-primary btn-sm" style="font-size: 11px; height: 32px; display: inline-flex; align-items: center; gap: 6px;">
                <i class="fa-solid fa-floppy-disk"></i> <?php echo $recordId > 0 ? 'Update Record' : 'Save to Registry'; ?>
            </button>
        </div>
    </div>

    <!-- Flash Alert -->
    <?php if ($flashMessage): ?>
        <div class="flash-message-bar" style="background: <?php echo $flashType === 'error' ? 'rgba(239,68,68,0.15)' : 'rgba(34,197,94,0.15)'; ?>; border-bottom: 1px solid <?php echo $flashType === 'error' ? '#ef4444' : '#22c55e'; ?>; color: <?php echo $flashType === 'error' ? '#fca5a5' : '#86efac'; ?>; padding: 8px 16px; font-size: 12px; display: flex; align-items: center; justify-content: space-between; z-index: 40;">
            <div style="display: flex; align-items: center; gap: 8px;">
                <i class="fa-solid <?php echo $flashType === 'error' ? 'fa-circle-exclamation' : 'fa-circle-check'; ?>"></i>
                <span><?php echo htmlspecialchars($flashMessage); ?></span>
            </div>
            <button class="btn-icon" onclick="this.parentElement.remove()" style="font-size: 12px; opacity: 0.7;">&times;</button>
        </div>
    <?php endif; ?>

    <!-- ── WORKSPACE SPLIT: FORM (LEFT) vs PAPER PREVIEW (RIGHT) ── -->
    <div class="workspace-split-container" id="fun-split-container">

        <!-- ══ LEFT: Interactive Digital Form ══ -->
        <div class="form-side-panel">
            <form id="fun-digital-form" method="POST" action="funeral_form.php<?php echo $recordId > 0 ? '?record_id=' . $recordId : ''; ?>">
                <input type="hidden" name="action" value="save_funeral_form">
                <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
                <input type="hidden" name="target_record_id" value="<?php echo $recordId; ?>">
                <input type="hidden" name="scope" value="<?php echo htmlspecialchars($scope); ?>">
                <input type="hidden" name="chapel_id" value="<?php echo (int)($existingRecord['chapel_id'] ?? $userChapel); ?>">

                <!-- 1. FUNERAL & REQUIEM MASS SCHEDULE -->
                <div class="form-section-card">
                    <div class="form-section-title">
                        <span><i class="fa-solid fa-cross" style="color:var(--accent); margin-right:4px;"></i> Funeral &amp; Requiem Mass Schedule</span>
                        <span style="font-size: 9px; color: var(--text2);">Liturgical Rites</span>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                        <div class="field-group">
                            <label>Date of Funeral Mass <span style="color:#ef4444;">*</span></label>
                            <input type="date" name="event_date" id="in-fun-date" required min="1900-01-01" max="2099-12-31" value="<?php echo htmlspecialchars($eventDate); ?>" class="field-input" oninput="syncFormToPaper()">
                        </div>
                        <div class="field-group">
                            <label>Mass / Blessing Time</label>
                            <input type="text" name="funeral_time" id="in-fun-time" maxlength="20" value="<?php echo htmlspecialchars($funeralTime); ?>" placeholder="e.g. 2:00 PM" class="field-input" oninput="syncFormToPaper()">
                        </div>
                    </div>
                </div>

                <!-- 2. DECEASED PARTICULARS -->
                <div class="form-section-card">
                    <div class="form-section-title">
                        <span><i class="fa-solid fa-user" style="color:var(--accent); margin-right:4px;"></i> Deceased Particulars</span>
                        <span style="font-size: 9px; color: var(--text2);">Civil &amp; Vital Data</span>
                    </div>

                    <div style="display: grid; grid-template-columns: 2fr 1fr 2fr; gap: 8px;">
                        <div class="field-group">
                            <label>First Name <span style="color:#ef4444;">*</span></label>
                            <input type="text" name="deceased_first_name" id="in-dec-first" required maxlength="50" value="<?php echo htmlspecialchars($decFirst); ?>" placeholder="e.g. Pedro" class="field-input input-text-clean" oninput="syncFormToPaper()">
                        </div>
                        <div class="field-group">
                            <label>M.I. / Middle</label>
                            <input type="text" name="deceased_mi" id="in-dec-mi" maxlength="30" value="<?php echo htmlspecialchars($decMI); ?>" placeholder="e.g. M." class="field-input input-text-clean" oninput="syncFormToPaper()">
                        </div>
                        <div class="field-group">
                            <label>Surname <span style="color:#ef4444;">*</span></label>
                            <input type="text" name="deceased_surname" id="in-dec-surname" required maxlength="50" value="<?php echo htmlspecialchars($decSurname); ?>" placeholder="e.g. Penduko" class="field-input input-text-clean" oninput="syncFormToPaper()">
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 8px;">
                        <div class="field-group">
                            <label>Date of Death</label>
                            <input type="date" name="date_of_death" id="in-dod" min="1900-01-01" max="2099-12-31" value="<?php echo htmlspecialchars($dateOfDeath); ?>" class="field-input" oninput="syncFormToPaper()">
                        </div>
                        <div class="field-group">
                            <label>Age at Death</label>
                            <input type="number" name="age_at_death" id="in-age" min="0" max="150" value="<?php echo htmlspecialchars((string)$ageAtDeath); ?>" placeholder="e.g. 72" class="field-input" oninput="syncFormToPaper()">
                        </div>
                        <div class="field-group">
                            <label>Gender</label>
                            <select name="gender" id="in-gender" class="field-select" onchange="syncFormToPaper()">
                                <option value="Male" <?php echo strcasecmp($gender, 'Male') === 0 ? 'selected' : ''; ?>>Male</option>
                                <option value="Female" <?php echo strcasecmp($gender, 'Female') === 0 ? 'selected' : ''; ?>>Female</option>
                            </select>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                        <div class="field-group">
                            <label>Marital Status</label>
                            <select name="funeral_marital_status" id="in-marital" class="field-select" onchange="syncFormToPaper()">
                                <option value="Single" <?php echo strcasecmp($maritalStatus, 'Single') === 0 ? 'selected' : ''; ?>>Single</option>
                                <option value="Married" <?php echo strcasecmp($maritalStatus, 'Married') === 0 ? 'selected' : ''; ?>>Married</option>
                                <option value="Widowed" <?php echo strcasecmp($maritalStatus, 'Widowed') === 0 ? 'selected' : ''; ?>>Widowed</option>
                                <option value="Separated" <?php echo strcasecmp($maritalStatus, 'Separated') === 0 ? 'selected' : ''; ?>>Separated</option>
                            </select>
                        </div>
                        <div class="field-group">
                            <label>Occupation</label>
                            <input type="text" name="funeral_occupation" id="in-occupation" maxlength="60" value="<?php echo htmlspecialchars($occupation); ?>" placeholder="e.g. Retired Teacher" class="field-input input-text-clean" oninput="syncFormToPaper()">
                        </div>
                    </div>

                    <div class="field-group">
                        <label>Cause of Death</label>
                        <input type="text" name="funeral_cause_of_death" id="in-cause" maxlength="120" value="<?php echo htmlspecialchars($causeOfDeath); ?>" placeholder="e.g. Natural Causes / Cardiac Arrest" class="field-input input-address-clean" oninput="syncFormToPaper()">
                    </div>

                    <div class="field-group">
                        <label>Last Residence / Address</label>
                        <input type="text" name="residence" id="in-residence" maxlength="150" value="<?php echo htmlspecialchars($residence); ?>" placeholder="e.g. Digman, Bacoor City, Cavite" class="field-input input-address-clean" oninput="syncFormToPaper()">
                    </div>
                </div>

                <!-- 3. BURIAL & FAMILY CONTACT -->
                <div class="form-section-card">
                    <div class="form-section-title">
                        <span><i class="fa-solid fa-landmark" style="color:var(--accent); margin-right:4px;"></i> Interment &amp; Family Contact</span>
                        <span style="font-size: 9px; color: var(--text2);">Burial Details</span>
                    </div>

                    <div class="field-group">
                        <label>Place of Burial / Cemetery</label>
                        <input type="text" name="place_of_burial" id="in-burial-place" maxlength="120" value="<?php echo htmlspecialchars($placeOfBurial); ?>" placeholder="e.g. Bacoor Catholic Cemetery / Providence Memorial" class="field-input input-address-clean" oninput="syncFormToPaper()">
                    </div>

                    <div style="display: grid; grid-template-columns: 1.5fr 1fr; gap: 10px;">
                        <div class="field-group">
                            <label>Next of Kin / Informant</label>
                            <input type="text" name="next_of_kin" id="in-kin" maxlength="80" value="<?php echo htmlspecialchars($nextOfKin); ?>" placeholder="Spouse / Child / Relative Name" class="field-input input-text-clean" oninput="syncFormToPaper()">
                        </div>
                        <div class="field-group">
                            <label>Contact No.</label>
                            <input type="tel" name="contact_no" id="in-contact" maxlength="13" inputmode="tel" value="<?php echo htmlspecialchars($contactNo); ?>" placeholder="0912-345-6789" class="field-input tel-mask" oninput="formatTelMask(this); syncFormToPaper()">
                        </div>
                    </div>
                </div>

                <!-- 4. PARISH REGISTRY ARCHIVES -->
                <div class="form-section-card">
                    <div class="form-section-title">
                        <span><i class="fa-solid fa-book-bible" style="color:var(--accent); margin-right:4px;"></i> Parish Registry Archives</span>
                        <span style="font-size: 9px; color: var(--text2);">Office Use</span>
                    </div>

                    <div class="field-group">
                        <label>Officiating Priest / Minister</label>
                        <input type="text" name="officiating_priest" id="in-priest" maxlength="70" value="<?php echo htmlspecialchars($officiatingPriest); ?>" placeholder="e.g. Very Rev. Fr. Ramoncito Tadepa" class="field-input input-text-clean" oninput="syncFormToPaper()">
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                        <div class="field-group">
                            <label>Book Number</label>
                            <input type="text" name="book_number" id="in-book-no" maxlength="20" value="<?php echo htmlspecialchars($bookNumber); ?>" class="field-input input-code-clean" oninput="syncFormToPaper()">
                        </div>
                        <div class="field-group">
                            <label>Page Number</label>
                            <input type="text" name="page_number" id="in-page-no" maxlength="15" value="<?php echo htmlspecialchars($pageNumber); ?>" class="field-input input-code-clean" oninput="syncFormToPaper()">
                        </div>
                    </div>

                    <div class="field-group">
                        <label>Marginal Remarks</label>
                        <textarea name="marginal_notes" id="in-notes" maxlength="500" placeholder="Optional canonical notes or blessing details..." class="field-textarea" oninput="syncFormToPaper()"><?php echo htmlspecialchars($marginalNotes); ?></textarea>
                    </div>
                </div>

                <div style="padding-bottom: 24px;">
                    <button type="submit" class="btn btn-primary" style="width: 100%; height: 38px; justify-content: center; font-weight: 700; font-size: 13px;">
                        <i class="fa-solid fa-floppy-disk"></i> <?php echo $recordId > 0 ? 'Update Burial Record' : 'Save Burial Record to Registry'; ?>
                    </button>
                </div>
            </form>
        </div>

        <!-- ══ RIGHT: REAL-TIME PIXEL-PERFECT PAPER REPLICA ══ -->
        <div class="preview-side-panel" id="paper-preview-container">
            <div class="official-paper-sheet" id="printable-official-form">

                <!-- Cathedral Header -->
                <div class="paper-header">
                    <img src="assets/img/2323.png" alt="Cathedral Seal" class="paper-seal">
                    <h1 class="paper-cathedral-title">Cathedral of St. Michael the Archangel</h1>
                    <div class="paper-church-sub">Iglesia Filipina Independiente</div>
                    <div class="paper-church-loc">Digman, Bacoor City, Cavite</div>
                </div>

                <!-- Form Title -->
                <div class="paper-form-title">CERTIFICATE OF CHRISTIAN BURIAL</div>

                <div class="paper-preamble">
                    This is to certify that the Rite of Christian Burial was solemnly administered for the soul of
                    <strong class="deceased-hero" id="pp-deceased-hero"><?php echo htmlspecialchars($decFullInit ?: '—'); ?></strong>
                </div>

                <!-- Deceased Particulars Table -->
                <div class="paper-section-tag">DECEASED PARTICULARS</div>
                <table class="paper-grid-table">
                    <tr>
                        <td style="width: 60%;">
                            <span class="paper-label">Full Name of Deceased:</span>
                            <div class="paper-val" id="pp-deceased-name"><?php echo htmlspecialchars($decFullInit ?: '—'); ?></div>
                        </td>
                        <td style="width: 40%;">
                            <span class="paper-label">Age at Death &amp; Gender:</span>
                            <div class="paper-val" id="pp-age-gender"><?php echo $ageAtDeath ? htmlspecialchars($ageAtDeath . ' years · ' . $gender) : htmlspecialchars($gender); ?></div>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <span class="paper-label">Date of Death:</span>
                            <div class="paper-val" id="pp-dod"><?php echo $dateOfDeath ? date('F d, Y', strtotime($dateOfDeath)) : '—'; ?></div>
                        </td>
                        <td>
                            <span class="paper-label">Marital Status:</span>
                            <div class="paper-val" id="pp-marital"><?php echo htmlspecialchars($maritalStatus ?: '—'); ?></div>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <span class="paper-label">Occupation:</span>
                            <div class="paper-val" id="pp-occupation"><?php echo htmlspecialchars($occupation ?: '—'); ?></div>
                        </td>
                        <td>
                            <span class="paper-label">Cause of Death:</span>
                            <div class="paper-val" id="pp-cause"><?php echo htmlspecialchars($causeOfDeath ?: '—'); ?></div>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="2">
                            <span class="paper-label">Last Residence:</span>
                            <div class="paper-val" id="pp-residence"><?php echo htmlspecialchars($residence ?: '—'); ?></div>
                        </td>
                    </tr>
                </table>

                <!-- Burial Details Table -->
                <div class="paper-section-tag">BURIAL &amp; RITES DETAILS</div>
                <table class="paper-grid-table">
                    <tr>
                        <td style="width: 50%;">
                            <span class="paper-label">Date &amp; Time of Funeral Mass:</span>
                            <div class="paper-val" id="pp-fun-date"><?php echo $eventDate ? date('F d, Y', strtotime($eventDate)) . ($funeralTime ? ' · ' . htmlspecialchars($funeralTime) : '') : '—'; ?></div>
                        </td>
                        <td style="width: 50%;">
                            <span class="paper-label">Place of Burial / Cemetery:</span>
                            <div class="paper-val" id="pp-burial-place"><?php echo htmlspecialchars($placeOfBurial ?: '—'); ?></div>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <span class="paper-label">Next of Kin / Informant:</span>
                            <div class="paper-val" id="pp-kin"><?php echo htmlspecialchars($nextOfKin ?: '—'); ?></div>
                        </td>
                        <td>
                            <span class="paper-label">Contact No.:</span>
                            <div class="paper-val" id="pp-contact"><?php echo htmlspecialchars($contactNo ?: '—'); ?></div>
                        </td>
                    </tr>
                </table>

                <!-- Footer & Signatures -->
                <div class="paper-footer">
                    <div class="sec-note">To be filled by the Secretary of the Parish</div>
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:30px; margin: 14px 0;">
                        <div style="text-align:center;">
                            <div style="border-bottom:1px solid #111; padding-bottom:2px; font-weight:700; font-family:'Courier New', monospace; font-size:13px;" id="pp-priest">
                                <?php echo htmlspecialchars($officiatingPriest ?: '—'); ?>
                            </div>
                            <div style="font-size:10px; color:#555; margin-top:3px; text-transform:uppercase;">Officiating Priest</div>
                        </div>
                        <div style="text-align:center;">
                            <div style="border-bottom:1px solid #111; padding-bottom:2px; font-weight:700; font-family:'Courier New', monospace; font-size:13px;">
                                Rev. Fr. Jayson Alcantara
                            </div>
                            <div style="font-size:10px; color:#555; margin-top:3px; text-transform:uppercase;">Parish Priest / Rector</div>
                        </div>
                    </div>

                    <div style="display:flex; justify-content:space-between; font-size:11px; color:#333; border-top:1px solid #ddd; padding-top:6px;">
                        <div><strong>Book No.:</strong> <span id="pp-book-no" style="font-family:'Courier New', monospace; font-weight:700;"><?php echo htmlspecialchars($bookNumber ?: '—'); ?></span></div>
                        <div><strong>Page No.:</strong> <span id="pp-page-no" style="font-family:'Courier New', monospace; font-weight:700;"><?php echo htmlspecialchars($pageNumber ?: '—'); ?></span></div>
                        <div><strong>Register:</strong> <span>Burial Register</span></div>
                    </div>
                </div>

                <div class="paper-verification-strip">
                    <div><strong>CANONICAL ARCHIVE:</strong> Cathedral of St. Michael the Archangel &bull; Bacoor City</div>
                    <div>REF: <strong><?php echo htmlspecialchars($refId ?: 'NEW ENTRY'); ?></strong></div>
                </div>

            </div>
        </div>

    </div>

    <!-- ── REAL-TIME INTERACTIVE SYNC SCRIPT ── -->
    <script>
    function togglePaperPreview() {
        const splitContainer = document.getElementById('fun-split-container');
        const previewText = document.getElementById('toggle-preview-text');
        if (splitContainer.classList.contains('preview-hidden')) {
            splitContainer.classList.remove('preview-hidden');
            previewText.textContent = 'Hide Paper Preview';
        } else {
            splitContainer.classList.add('preview-hidden');
            previewText.textContent = 'Show Paper Preview';
        }
    }

    function formatDateDisplay(dateStr) {
        if (!dateStr) return '';
        try {
            const dt = new Date(dateStr + 'T00:00:00');
            if (isNaN(dt.getTime())) return dateStr;
            return dt.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
        } catch(e) {
            return dateStr;
        }
    }

    function syncFormToPaper() {
        const first = (document.getElementById('in-dec-first')?.value || '').trim();
        const mi    = (document.getElementById('in-dec-mi')?.value || '').trim();
        const sur   = (document.getElementById('in-dec-surname')?.value || '').trim();
        const full  = [first, mi, sur].filter(Boolean).join(' ') || '—';

        document.getElementById('pp-deceased-hero').textContent = full;
        document.getElementById('pp-deceased-name').textContent = full;

        const age = (document.getElementById('in-age')?.value || '').trim();
        const gender = document.getElementById('in-gender')?.value || 'Male';
        document.getElementById('pp-age-gender').textContent = age ? `${age} years · ${gender}` : gender;

        const dod = document.getElementById('in-dod')?.value || '';
        document.getElementById('pp-dod').textContent = formatDateDisplay(dod) || '—';

        document.getElementById('pp-marital').textContent = document.getElementById('in-marital')?.value || '—';
        document.getElementById('pp-occupation').textContent = (document.getElementById('in-occupation')?.value || '').trim() || '—';
        document.getElementById('pp-cause').textContent = (document.getElementById('in-cause')?.value || '').trim() || '—';
        document.getElementById('pp-residence').textContent = (document.getElementById('in-residence')?.value || '').trim() || '—';

        const funDate = document.getElementById('in-fun-date')?.value || '';
        const funTime = (document.getElementById('in-fun-time')?.value || '').trim();
        const funDisplay = formatDateDisplay(funDate) + (funTime ? ' · ' + funTime : '');
        document.getElementById('pp-fun-date').textContent = funDisplay || '—';

        document.getElementById('pp-burial-place').textContent = (document.getElementById('in-burial-place')?.value || '').trim() || '—';
        document.getElementById('pp-kin').textContent = (document.getElementById('in-kin')?.value || '').trim() || '—';
        document.getElementById('pp-contact').textContent = (document.getElementById('in-contact')?.value || '').trim() || '—';

        document.getElementById('pp-priest').textContent = (document.getElementById('in-priest')?.value || '').trim() || '—';
        document.getElementById('pp-book-no').textContent = (document.getElementById('in-book-no')?.value || '').trim() || '—';
        document.getElementById('pp-page-no').textContent = (document.getElementById('in-page-no')?.value || '').trim() || '—';
    }

    function formatTelMask(input) {
        let digits = input.value.replace(/\D/g, '');
        if (digits.length > 11) digits = digits.substring(0, 11);
        let formatted = '';
        if (digits.length > 7) {
            formatted = digits.substring(0, 4) + '-' + digits.substring(4, 7) + '-' + digits.substring(7);
        } else if (digits.length > 4) {
            formatted = digits.substring(0, 4) + '-' + digits.substring(4);
        } else {
            formatted = digits;
        }
        input.value = formatted;
    }

    function applyInputRestrictions() {
        document.querySelectorAll('.input-text-clean').forEach(elem => {
            if (elem.dataset.restricted) return;
            elem.dataset.restricted = 'true';
            elem.addEventListener('input', function() {
                this.value = this.value.replace(/[^a-zA-ZñÑ\s\.\-\']/g, '');
            });
        });

        document.querySelectorAll('.input-address-clean').forEach(elem => {
            if (elem.dataset.restricted) return;
            elem.dataset.restricted = 'true';
            elem.addEventListener('input', function() {
                this.value = this.value.replace(/[^a-zA-Z0-9ñÑ\s\.,\-\'#\/\(\)]/g, '');
            });
        });

        document.querySelectorAll('.input-code-clean').forEach(elem => {
            if (elem.dataset.restricted) return;
            elem.dataset.restricted = 'true';
            elem.addEventListener('input', function() {
                this.value = this.value.replace(/[^a-zA-Z0-9\s\-\.\/]/g, '').toUpperCase();
            });
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        applyInputRestrictions();
        const contactInput = document.getElementById('in-contact');
        if (contactInput && contactInput.value) {
            formatTelMask(contactInput);
        }
        syncFormToPaper();
    });
    </script>
</body>
</html>
