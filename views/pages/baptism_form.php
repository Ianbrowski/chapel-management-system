<?php
/**
 * View Template: Official Cathedral Baptismal Form
 * Interactive Digital Intake Form on Left + Real-time 1:1 Paper Replica Preview on Right
 */

$childFirst = $metadata['child_first_name'] ?? '';
$childMI = $metadata['child_middle_name'] ?? '';
$childSurname = $metadata['child_surname'] ?? '';

// Fallback to existing record fields if metadata not populated
$nameSource = !empty($existingRecord['child_name']) ? $existingRecord['child_name'] : (!empty($existingRecord['person_name']) ? $existingRecord['person_name'] : '');
if (!$childFirst && $nameSource) {
    $parts = explode(' ', trim($nameSource));
    if (count($parts) >= 3) {
        $childSurname = array_pop($parts);
        $childMI = array_pop($parts);
        $childFirst = implode(' ', $parts);
    } elseif (count($parts) === 2) {
        $childSurname = $parts[1];
        $childFirst = $parts[0];
    } else {
        $childFirst = $nameSource;
    }
}

$eventDate = $existingRecord['event_date'] ?? date('Y-m-d');
$dob = $existingRecord['date_of_birth'] ?? '';
$pob = $existingRecord['place_of_birth'] ?? '';
$address = $metadata['address'] ?? ($existingRecord['baptism_parents_residence'] ?? '');
$gender = $metadata['gender'] ?? 'Male';
$nationality = $metadata['nationality'] ?? 'Filipino';
$contactNo = $metadata['contact_no'] ?? '';

$fatherFirst = $metadata['father_first_name'] ?? '';
$fatherMI = $metadata['father_mi'] ?? '';
$fatherSurname = $metadata['father_surname'] ?? '';
if (!$fatherFirst && !empty($existingRecord['father_name'])) {
    $fparts = explode(' ', trim($existingRecord['father_name']));
    if (count($fparts) >= 2) {
        $fatherSurname = array_pop($fparts);
        $fatherFirst = implode(' ', $fparts);
    } else {
        $fatherFirst = $existingRecord['father_name'];
    }
}
$fatherBirthPlace = $metadata['father_birth_place'] ?? '';

$motherFirst = $metadata['mother_first_name'] ?? '';
$motherMI = $metadata['mother_mi'] ?? '';
$motherSurname = $metadata['mother_surname'] ?? '';
if (!$motherFirst && !empty($existingRecord['mother_name'])) {
    $mparts = explode(' ', trim($existingRecord['mother_name']));
    if (count($mparts) >= 2) {
        $motherSurname = array_pop($mparts);
        $motherFirst = implode(' ', $mparts);
    } else {
        $motherFirst = $existingRecord['mother_name'];
    }
}
$motherBirthPlace = $metadata['mother_birth_place'] ?? '';

$filiation = $metadata['filiation'] ?? ($existingRecord['baptism_legitimacy'] ?? 'Kasal');

$paternalLolo = $metadata['paternal_lolo'] ?? '';
$paternalLola = $metadata['paternal_lola'] ?? '';
$maternalLolo = $metadata['maternal_lolo'] ?? '';
$maternalLola = $metadata['maternal_lola'] ?? '';

$sponsors = $metadata['sponsors'] ?? [];
if (empty($sponsors)) {
    if (!empty($existingRecord['godfather'])) $sponsors[] = $existingRecord['godfather'];
    if (!empty($existingRecord['godmother'])) $sponsors[] = $existingRecord['godmother'];
}
// Default 6 empty lines if no sponsors
while (count($sponsors) < 6) {
    $sponsors[] = '';
}

$officiatingPriest = $existingRecord['officiating_priest'] ?? 'Very Rev. Fr. Ramoncito Tadepa';
$bookNumber = $existingRecord['book_number'] ?? 'BK-' . date('Y');
$pageNumber = $existingRecord['page_number'] ?? 'PG-01';
$scope = $existingRecord['scope'] ?? ($_GET['scope'] ?? ($isAdmin ? 'cathedral' : 'chapel'));
if (!in_array($scope, ['cathedral', 'chapel'], true)) { $scope = 'cathedral'; }
$refId = $existingRecord['reference_id'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Official Baptismal Form | IFI Cathedral of St. Michael the Archangel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@500;600;700;800&family=Inter:wght@300;400;500;600;700&family=Libre+Baskerville:ital,wght@0,400;0,700;1,400&display=swap" rel="stylesheet">
    <?php $assetV = static fn(string $p): int => @filemtime(__DIR__ . '/../../' . $p) ?: 1; ?>
    <link rel="stylesheet" href="assets/css/index.css?v=<?php echo $assetV('assets/css/index.css'); ?>">
    <link rel="stylesheet" href="assets/css/workspace.css?v=<?php echo $assetV('assets/css/workspace.css'); ?>">
    <link rel="stylesheet" href="assets/css/skeleton.css?v=<?php echo $assetV('assets/css/skeleton.css'); ?>">
    <script src="assets/js/skeleton-loader.js?v=<?php echo $assetV('assets/js/skeleton-loader.js'); ?>"></script>
    <script src="assets/js/ph-geo-data.js?v=<?php echo $assetV('assets/js/ph-geo-data.js'); ?>"></script>
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
        .baptism-topbar {
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
        .baptism-topbar-title {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .baptism-topbar-title h2 {
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
        .field-input, .field-select {
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
        }
        .field-input:focus, .field-select:focus {
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
            max-width: 794px; /* A4 / US Letter standard proportional width */
            background: #ffffff;
            color: #1a1a1a;
            box-shadow: 0 12px 40px rgba(0,0,0,0.6);
            padding: 34px 40px;
            box-sizing: border-box;
            border-radius: 2px;
            font-family: 'Arial', 'Helvetica', sans-serif;
            position: relative;
        }

        /* Header Layout */
        .paper-header {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            margin-bottom: 12px;
        }
        .paper-seal {
            width: 44px;
            height: 44px;
            margin-bottom: 4px;
            object-fit: contain;
        }
        .paper-cathedral-title {
            font-family: 'Cinzel', serif;
            font-size: 13.5px;
            font-weight: 800;
            letter-spacing: 0.05em;
            color: #a82020;
            margin: 0;
            line-height: 1.2;
            text-transform: uppercase;
        }
        .paper-church-sub {
            font-family: 'Libre Baskerville', serif;
            font-size: 10.5px;
            font-style: italic;
            color: #a82020;
            margin: 2px 0 0;
        }
        .paper-church-loc {
            font-size: 9.5px;
            color: #a82020;
            margin: 1px 0 0;
        }

        .paper-form-title-wrap {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 10px;
            margin-bottom: 2px;
        }
        .paper-form-title {
            font-size: 10.5px;
            font-weight: 700;
            letter-spacing: 0.05em;
            color: #a82020;
            text-transform: uppercase;
        }
        .paper-section-tag {
            font-size: 10.5px;
            font-weight: 700;
            color: #a82020;
            text-transform: uppercase;
        }

        /* Paper Tables & Borders (matching real form's brick-red borders) */
        .paper-grid-table {
            width: 100%;
            border-collapse: collapse;
            border: 1.5px solid #a82020;
            margin-bottom: 8px;
            font-size: 9px;
        }
        .paper-grid-table td, .paper-grid-table th {
            border: 1px solid #a82020;
            padding: 3px 6px;
            vertical-align: top;
        }
        .paper-label {
            font-size: 8px;
            font-weight: 700;
            color: #a82020;
            text-transform: uppercase;
            margin-bottom: 2px;
            display: block;
        }
        .paper-sub-labels {
            display: flex;
            justify-content: space-between;
            font-size: 7.5px;
            color: #777;
            text-transform: uppercase;
            border-top: 1px dotted #ccc;
            margin-top: 4px;
            padding-top: 1px;
        }
        .paper-val {
            font-size: 10.5px;
            font-weight: 600;
            color: #000000;
            min-height: 14px;
            line-height: 1.25;
        }
        .paper-checkbox {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 9.5px;
            font-weight: 700;
            color: #a82020;
            margin-right: 14px;
        }
        .paper-box {
            display: inline-block;
            width: 11px;
            height: 11px;
            border: 1.2px solid #a82020;
            text-align: center;
            line-height: 9px;
            font-size: 9px;
            font-weight: 900;
            color: #000;
        }

        /* Sponsors 2-Column Lines */
        .sponsors-lines-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            column-gap: 24px;
            row-gap: 8px;
            padding: 8px 4px 12px;
        }
        .sponsor-line-item {
            border-bottom: 1px solid #a82020;
            font-size: 10px;
            font-weight: 600;
            color: #000;
            padding-bottom: 2px;
            min-height: 18px;
        }

        /* Parish Secretary Footer */
        .paper-footer-section {
            margin-top: 12px;
            padding-top: 6px;
        }
        .paper-sec-note {
            font-size: 8px;
            font-style: italic;
            color: #666;
            margin-bottom: 4px;
        }
        .paper-priest-line {
            display: flex;
            align-items: baseline;
            gap: 8px;
            font-size: 9.5px;
            font-weight: 700;
            color: #a82020;
        }
        .paper-priest-name-line {
            flex: 1;
            border-bottom: 1px solid #a82020;
            padding-left: 8px;
            font-size: 10.5px;
            color: #000;
            font-weight: 600;
        }

        /* ── PRINT RULES (@media print) ── */
        @media print {
            body {
                display: block !important;
                background: #ffffff !important;
                color: #000000 !important;
                height: auto !important;
                overflow: visible !important;
            }
            .baptism-topbar, .form-side-panel, .flash-message-bar {
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
                height: auto !important;
                display: block !important;
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
                max-width: 100% !important;
                width: 100% !important;
                padding: 0 !important;
                margin: 0 !important;
                page-break-after: avoid !important;
                page-break-inside: avoid !important;
            }
            @page {
                size: letter portrait;
                margin: 10mm 12mm;
            }
        }
    </style>
</head>
<body>

    <!-- ── TOPBAR ── -->
    <div class="baptism-topbar">
        <div class="baptism-topbar-title">
            <a href="dashboard.php?section=records_<?php echo $scope; ?>" class="btn btn-ghost btn-sm" style="font-size: 11px; height: 30px; display: inline-flex; align-items: center; gap: 6px; border: 1px solid var(--border);">
                <i class="fa-solid fa-arrow-left"></i> Back to Registry
            </a>
            <img src="assets/img/2323.png" alt="Cathedral Seal" style="width: 24px; height: 24px; object-fit: contain;">
            <h2>Cathedral Baptismal Form</h2>
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
                <i class="fa-solid fa-print"></i> Print Official Form
            </button>
            <button type="submit" form="baptism-digital-form" class="btn btn-primary btn-sm" style="font-size: 11px; height: 32px; display: inline-flex; align-items: center; gap: 6px;">
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
    <div class="workspace-split-container" id="baptism-split-container">

        <!-- ══ LEFT: Interactive Digital Form ══ -->
        <div class="form-side-panel">
            <form id="baptism-digital-form" method="POST" action="baptism_form.php<?php echo $recordId > 0 ? '?record_id=' . $recordId : ''; ?>">
                <input type="hidden" name="action" value="save_baptism_form">
                <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
                <input type="hidden" name="target_record_id" value="<?php echo $recordId; ?>">
                <input type="hidden" name="scope" value="<?php echo htmlspecialchars($scope); ?>">
                <input type="hidden" name="chapel_id" value="<?php echo (int)($existingRecord['chapel_id'] ?? $userChapel); ?>">

                <!-- 1. CHILD INFORMATION -->
                <div class="form-section-card">
                    <div class="form-section-title">
                        <span><i class="fa-solid fa-child" style="color:var(--accent); margin-right:4px;"></i> ( CHILD ) Information</span>
                        <span style="font-size: 9px; color: var(--text2);">Candidate Info</span>
                    </div>

                    <div style="display: grid; grid-template-columns: 2fr 1fr 2fr; gap: 8px;">
                        <div class="field-group">
                            <label>First Name <span style="color:#ef4444;">*</span></label>
                            <input type="text" name="child_first_name" id="in-child-first" required maxlength="50" value="<?php echo htmlspecialchars($childFirst); ?>" placeholder="e.g. Juan" class="field-input input-text-clean" oninput="syncFormToPaper()">
                        </div>
                        <div class="field-group">
                            <label>M.I. / Middle Name</label>
                            <input type="text" name="child_middle_name" id="in-child-mi" maxlength="30" value="<?php echo htmlspecialchars($childMI); ?>" placeholder="e.g. M." class="field-input input-text-clean" oninput="syncFormToPaper()">
                        </div>
                        <div class="field-group">
                            <label>Surname <span style="color:#ef4444;">*</span></label>
                            <input type="text" name="child_surname" id="in-child-surname" required maxlength="50" value="<?php echo htmlspecialchars($childSurname); ?>" placeholder="e.g. dela Cruz" class="field-input input-text-clean" oninput="syncFormToPaper()">
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px;">
                        <div class="field-group">
                            <label>Date of Baptism <span style="color:#ef4444;">*</span></label>
                            <input type="date" name="event_date" id="in-date-baptism" required min="1900-01-01" max="2099-12-31" value="<?php echo htmlspecialchars($eventDate); ?>" class="field-input" oninput="syncFormToPaper()">
                        </div>
                        <div class="field-group">
                            <label>Date of Birth <span style="color:#ef4444;">*</span></label>
                            <input type="date" name="date_of_birth" id="in-date-birth" required min="1900-01-01" max="2099-12-31" value="<?php echo htmlspecialchars($dob); ?>" class="field-input" oninput="syncFormToPaper()">
                        </div>
                    </div>

                    <div class="field-group">
                        <label>Place of Birth (House No., St., Brgy, City/Muni, Prov)</label>
                        <input type="text" name="place_of_birth" id="in-place-birth" list="dl-ph-all-cities" maxlength="100" value="<?php echo htmlspecialchars($pob); ?>" placeholder="e.g. Angono, Rizal" class="field-input input-place-clean" oninput="syncFormToPaper()" autocomplete="off">
                    </div>

                    <div class="field-group">
                        <label>Full Residential Address (House No., St., Brgy, City/Muni, Prov)</label>
                        <input type="text" name="address" id="in-address" maxlength="150" value="<?php echo htmlspecialchars($address); ?>" placeholder="e.g. 124 Evangelista St., Digman, Bacoor City, Cavite" class="field-input input-address-clean" oninput="syncFormToPaper()">
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr 1.2fr; gap: 8px;">
                        <div class="field-group">
                            <label>Gender</label>
                            <select name="gender" id="in-gender" class="field-select" onchange="syncFormToPaper()">
                                <option value="Male" <?php echo strcasecmp($gender, 'Male') === 0 ? 'selected' : ''; ?>>Male</option>
                                <option value="Female" <?php echo strcasecmp($gender, 'Female') === 0 ? 'selected' : ''; ?>>Female</option>
                            </select>
                        </div>
                        <div class="field-group">
                            <label>Nationality</label>
                            <input type="text" name="nationality" id="in-nationality" list="dl-citizenships" maxlength="40" value="<?php echo htmlspecialchars($nationality); ?>" placeholder="e.g. Filipino" class="field-input input-text-clean" oninput="syncFormToPaper()" autocomplete="off">
                        </div>
                        <div class="field-group">
                            <label>Contact No. <span style="font-size:9.5px; opacity:0.8;">09XX-XXX-XXXX</span></label>
                            <input type="tel" name="contact_no" id="in-contact" maxlength="13" inputmode="tel" value="<?php echo htmlspecialchars($contactNo); ?>" placeholder="0912-345-6789" class="field-input tel-mask" oninput="formatTelMask(this); syncFormToPaper()">
                        </div>
                    </div>
                </div>

                <!-- 2. PARENTS INFORMATION -->
                <div class="form-section-card">
                    <div class="form-section-title">
                        <span><i class="fa-solid fa-people-roof" style="color:var(--accent); margin-right:4px;"></i> ( PARENTS ) Information</span>
                        <span style="font-size: 9px; color: var(--text2);">Parents Info</span>
                    </div>

                    <!-- Father -->
                    <div style="background: var(--bg3); padding: 8px 10px; border-radius: 6px; border: 1px solid var(--border); display: flex; flex-direction: column; gap: 6px;">
                        <div style="font-size: 0.68rem; font-weight: 700; color: var(--accent); text-transform: uppercase;">Father's Details</div>
                        <div style="display: grid; grid-template-columns: 2fr 1fr 2fr; gap: 6px;">
                            <div class="field-group">
                                <label>First Name</label>
                                <input type="text" name="father_first_name" id="in-father-first" maxlength="50" value="<?php echo htmlspecialchars($fatherFirst); ?>" placeholder="First name" class="field-input input-text-clean" oninput="syncFormToPaper()">
                            </div>
                            <div class="field-group">
                                <label>M.I.</label>
                                <input type="text" name="father_mi" id="in-father-mi" maxlength="5" value="<?php echo htmlspecialchars($fatherMI); ?>" placeholder="M.I." class="field-input input-mi-clean" oninput="syncFormToPaper()">
                            </div>
                            <div class="field-group">
                                <label>Surname</label>
                                <input type="text" name="father_surname" id="in-father-surname" maxlength="50" value="<?php echo htmlspecialchars($fatherSurname); ?>" placeholder="Surname" class="field-input input-text-clean" oninput="syncFormToPaper()">
                            </div>
                        </div>
                        <div class="field-group">
                            <label>Father's Place of Birth</label>
                            <input type="text" name="father_birth_place" id="in-father-pob" list="dl-ph-all-cities" maxlength="100" value="<?php echo htmlspecialchars($fatherBirthPlace); ?>" placeholder="City, Province" class="field-input input-place-clean" oninput="syncFormToPaper()" autocomplete="off">
                        </div>
                    </div>

                    <!-- Mother -->
                    <div style="background: var(--bg3); padding: 8px 10px; border-radius: 6px; border: 1px solid var(--border); display: flex; flex-direction: column; gap: 6px;">
                        <div style="font-size: 0.68rem; font-weight: 700; color: var(--accent); text-transform: uppercase;">Mother's Details (Maiden Name)</div>
                        <div style="display: grid; grid-template-columns: 2fr 1fr 2fr; gap: 6px;">
                            <div class="field-group">
                                <label>First Name</label>
                                <input type="text" name="mother_first_name" id="in-mother-first" maxlength="50" value="<?php echo htmlspecialchars($motherFirst); ?>" placeholder="First name" class="field-input input-text-clean" oninput="syncFormToPaper()">
                            </div>
                            <div class="field-group">
                                <label>M.I.</label>
                                <input type="text" name="mother_mi" id="in-mother-mi" maxlength="5" value="<?php echo htmlspecialchars($motherMI); ?>" placeholder="M.I." class="field-input input-mi-clean" oninput="syncFormToPaper()">
                            </div>
                            <div class="field-group">
                                <label>Maiden Surname</label>
                                <input type="text" name="mother_surname" id="in-mother-surname" maxlength="50" value="<?php echo htmlspecialchars($motherSurname); ?>" placeholder="Maiden surname" class="field-input input-text-clean" oninput="syncFormToPaper()">
                            </div>
                        </div>
                        <div class="field-group">
                            <label>Mother's Place of Birth</label>
                            <input type="text" name="mother_birth_place" id="in-mother-pob" list="dl-ph-all-cities" maxlength="100" value="<?php echo htmlspecialchars($motherBirthPlace); ?>" placeholder="City, Province" class="field-input input-place-clean" oninput="syncFormToPaper()" autocomplete="off">
                        </div>
                    </div>

                    <!-- Filiation -->
                    <div class="field-group" style="padding-top: 4px;">
                        <label>Filiation / Marriage Status of Parents</label>
                        <select name="filiation" id="in-filiation" class="field-select" onchange="syncFormToPaper()">
                            <option value="Kasal" <?php echo (strcasecmp($filiation, 'Kasal') === 0 || strcasecmp($filiation, 'Legitimate') === 0) ? 'selected' : ''; ?>>KASAL (Married)</option>
                            <option value="Di Kasal" <?php echo (strcasecmp($filiation, 'Di Kasal') === 0 || strcasecmp($filiation, 'Illegitimate') === 0) ? 'selected' : ''; ?>>DI KASAL (Not Married)</option>
                        </select>
                    </div>
                </div>

                <!-- 3. GRANDPARENTS OF THE CHILD -->
                <div class="form-section-card">
                    <div class="form-section-title">
                        <span><i class="fa-solid fa-tree" style="color:var(--accent); margin-right:4px;"></i> ( GRANDPARENTS ) of the Child</span>
                        <span style="font-size: 9px; color: var(--text2);">Paternal &amp; Maternal</span>
                    </div>

                    <!-- Paternal -->
                    <div style="display: flex; flex-direction: column; gap: 6px;">
                        <div style="font-size: 0.68rem; font-weight: 700; color: var(--text2); text-transform: uppercase;">Paternal Grandparents (Father's Side)</div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px;">
                            <div class="field-group">
                                <label>Lolo sa Ama</label>
                                <input type="text" name="paternal_lolo" id="in-paternal-lolo" maxlength="70" value="<?php echo htmlspecialchars($paternalLolo); ?>" placeholder="First, M.I., Surname" class="field-input input-text-clean" oninput="syncFormToPaper()">
                            </div>
                            <div class="field-group">
                                <label>Lola sa Ama (Maiden Name)</label>
                                <input type="text" name="paternal_lola" id="in-paternal-lola" maxlength="70" value="<?php echo htmlspecialchars($paternalLola); ?>" placeholder="First, M.I., Surname" class="field-input input-text-clean" oninput="syncFormToPaper()">
                            </div>
                        </div>
                    </div>

                    <!-- Maternal -->
                    <div style="display: flex; flex-direction: column; gap: 6px; margin-top: 4px;">
                        <div style="font-size: 0.68rem; font-weight: 700; color: var(--text2); text-transform: uppercase;">Maternal Grandparents (Mother's Side)</div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px;">
                            <div class="field-group">
                                <label>Lolo sa Ina</label>
                                <input type="text" name="maternal_lolo" id="in-maternal-lolo" maxlength="70" value="<?php echo htmlspecialchars($maternalLolo); ?>" placeholder="First, M.I., Surname" class="field-input input-text-clean" oninput="syncFormToPaper()">
                            </div>
                            <div class="field-group">
                                <label>Lola sa Ina (Maiden Name)</label>
                                <input type="text" name="maternal_lola" id="in-maternal-lola" maxlength="70" value="<?php echo htmlspecialchars($maternalLola); ?>" placeholder="First, M.I., Surname" class="field-input input-text-clean" oninput="syncFormToPaper()">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 4. SPONSORS (NINONG AT NINANG) -->
                <div class="form-section-card">
                    <div class="form-section-title">
                        <span><i class="fa-solid fa-hands-holding-child" style="color:var(--accent); margin-right:4px;"></i> ( SPONSORS ) Ninong at Ninang</span>
                        <button type="button" class="btn btn-ghost btn-sm" onclick="addSponsorInputRow()" style="font-size: 10px; height: 22px; padding: 0 6px;">
                            <i class="fa-solid fa-plus"></i> Add Row
                        </button>
                    </div>

                    <div id="sponsors-form-inputs-container" style="display: flex; flex-direction: column; gap: 6px;">
                        <?php foreach ($sponsors as $idx => $sp): ?>
                            <div class="sponsor-input-row" style="display: flex; gap: 6px; align-items: center;">
                                <span style="font-size: 11px; color: var(--text2); width: 22px; text-align: right;"><?php echo ($idx + 1); ?>.</span>
                                <input type="text" name="sponsors[]" maxlength="70" value="<?php echo htmlspecialchars($sp); ?>" placeholder="Ninong or Ninang Full Name" class="field-input sponsor-field input-text-clean" oninput="syncFormToPaper()">
                                <?php if ($idx >= 4): ?>
                                    <button type="button" class="btn-icon" onclick="this.parentElement.remove(); syncFormToPaper();" style="width: 26px; height: 26px; color: #ef4444;" title="Remove">
                                        <i class="fa-solid fa-xmark"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- 5. PARISH ARCHIVES & PRIEST -->
                <div class="form-section-card">
                    <div class="form-section-title">
                        <span><i class="fa-solid fa-building-columns" style="color:var(--accent); margin-right:4px;"></i> Parish Registry &amp; Clergy</span>
                        <span style="font-size: 9px; color: var(--text2);">Official Filing</span>
                    </div>

                    <div class="field-group">
                        <label>Officiating Priest <span style="color:#ef4444;">*</span></label>
                        <input type="text" name="officiating_priest" id="in-priest" required maxlength="70" value="<?php echo htmlspecialchars($officiatingPriest); ?>" placeholder="e.g. Fr. Jayson Alcantara" class="field-input input-text-clean" oninput="syncFormToPaper()">
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px;">
                        <div class="field-group">
                            <label>Book Number</label>
                            <input type="text" name="book_number" id="in-book-no" maxlength="20" value="<?php echo htmlspecialchars($bookNumber); ?>" class="field-input input-code-clean">
                        </div>
                        <div class="field-group">
                            <label>Page / Folio Number</label>
                            <input type="text" name="page_number" id="in-page-no" maxlength="15" value="<?php echo htmlspecialchars($pageNumber); ?>" class="field-input input-code-clean">
                        </div>
                    </div>
                </div>

                <div style="padding-top: 8px; margin-bottom: 24px; display: flex; gap: 10px;">
                    <button type="submit" class="btn btn-primary" style="flex: 1; height: 38px; justify-content: center; font-weight: 600;">
                        <i class="fa-solid fa-floppy-disk"></i> <?php echo $recordId > 0 ? 'Update Record in Registry' : 'Save to Canonical Registry'; ?>
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="window.print()" style="height: 38px; padding: 0 16px;">
                        <i class="fa-solid fa-print"></i> Print
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

                <!-- Form Title & Section Header -->
                <div class="paper-form-title-wrap">
                    <div class="paper-form-title">Baptismal Form</div>
                    <div class="paper-section-tag">( Child )</div>
                </div>

                <!-- ( CHILD ) Table -->
                <table class="paper-grid-table">
                    <tr>
                        <td style="width: 65%;">
                            <span class="paper-label">Name of Child:</span>
                            <div class="paper-val" id="pp-child-name">&nbsp;</div>
                            <div class="paper-sub-labels">
                                <span>First Name</span>
                                <span>M.I.</span>
                                <span>Surname</span>
                            </div>
                        </td>
                        <td style="width: 35%;">
                            <span class="paper-label">Date of Baptism:</span>
                            <div class="paper-val" id="pp-date-baptism">&nbsp;</div>
                            <div class="paper-sub-labels">
                                <span>Month</span>
                                <span>Day</span>
                                <span>Year</span>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <span class="paper-label">Place of Birth:</span>
                            <div class="paper-val" id="pp-place-birth">&nbsp;</div>
                            <div class="paper-sub-labels">
                                <span>House No., St.</span>
                                <span>Barangay</span>
                                <span>City/Municipality</span>
                                <span>Province</span>
                            </div>
                        </td>
                        <td>
                            <span class="paper-label">Date of Birth:</span>
                            <div class="paper-val" id="pp-date-birth">&nbsp;</div>
                            <div class="paper-sub-labels">
                                <span>Month</span>
                                <span>Day</span>
                                <span>Year</span>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <span class="paper-label">Address:</span>
                            <div class="paper-val" id="pp-address">&nbsp;</div>
                            <div class="paper-sub-labels">
                                <span>House No., St.</span>
                                <span>Barangay</span>
                                <span>City/Municipality</span>
                                <span>Province</span>
                            </div>
                        </td>
                        <td>
                            <span class="paper-label">Gender:</span>
                            <div style="margin-top: 4px; display: flex; align-items: center;">
                                <span class="paper-checkbox">
                                    <span class="paper-box" id="pp-gender-male-box"></span> ( MALE )
                                </span>
                                <span class="paper-checkbox">
                                    <span class="paper-box" id="pp-gender-female-box"></span> ( FEMALE )
                                </span>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <div style="display: flex; gap: 8px; align-items: baseline;">
                                <span class="paper-label" style="margin: 0;">Nationality:</span>
                                <span class="paper-val" id="pp-nationality" style="flex: 1; border-bottom: 1px solid #a82020;">Filipino</span>
                            </div>
                        </td>
                        <td>
                            <div style="display: flex; gap: 8px; align-items: baseline;">
                                <span class="paper-label" style="margin: 0;">Contact No:</span>
                                <span class="paper-val" id="pp-contact-no" style="flex: 1; border-bottom: 1px solid #a82020;">&nbsp;</span>
                            </div>
                        </td>
                    </tr>
                </table>

                <!-- ( PARENTS ) Section Header -->
                <div style="text-align: center; font-size: 10px; font-weight: 700; color: #a82020; margin: 6px 0 2px;">
                    ( PARENTS )
                </div>

                <!-- ( PARENTS ) Table -->
                <table class="paper-grid-table">
                    <tr>
                        <td style="width: 60%;">
                            <span class="paper-label">Name of Father:</span>
                            <div class="paper-val" id="pp-father-name">&nbsp;</div>
                            <div class="paper-sub-labels">
                                <span>First Name</span>
                                <span>M.I.</span>
                                <span>Surname</span>
                            </div>
                        </td>
                        <td style="width: 40%;">
                            <span class="paper-label">Place of Birth:</span>
                            <div class="paper-val" id="pp-father-pob">&nbsp;</div>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <span class="paper-label">Name of Mother: (Maiden Name)</span>
                            <div class="paper-val" id="pp-mother-name">&nbsp;</div>
                            <div class="paper-sub-labels">
                                <span>First Name</span>
                                <span>M.I.</span>
                                <span>Surname</span>
                            </div>
                        </td>
                        <td>
                            <span class="paper-label">Place of Birth:</span>
                            <div class="paper-val" id="pp-mother-pob">&nbsp;</div>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="2">
                            <div style="display: flex; align-items: center; gap: 16px;">
                                <span class="paper-label" style="margin: 0;">Filiation:</span>
                                <span class="paper-checkbox">
                                    <span class="paper-box" id="pp-filiation-kasal-box"></span> ( KASAL )
                                </span>
                                <span class="paper-checkbox">
                                    <span class="paper-box" id="pp-filiation-di-kasal-box"></span> ( DI KASAL )
                                </span>
                            </div>
                        </td>
                    </tr>
                </table>

                <!-- ( GRANDPARENTS OF THE CHILD ) Section Header -->
                <div style="text-align: center; font-size: 10px; font-weight: 700; color: #a82020; margin: 6px 0 2px;">
                    ( GRANDPARENTS OF THE CHILD )
                </div>

                <!-- ( GRANDPARENTS ) Table -->
                <div style="font-size: 8px; font-weight: 700; color: #a82020; margin-bottom: 2px;">PATERNAL GRANDPARENTS (FATHER'S SIDE)</div>
                <table class="paper-grid-table" style="margin-bottom: 5px;">
                    <tr>
                        <td style="width: 50%;">
                            <span class="paper-label">Lolo sa Ama:</span>
                            <div class="paper-val" id="pp-paternal-lolo">&nbsp;</div>
                            <div class="paper-sub-labels">
                                <span>First Name</span>
                                <span>M.I.</span>
                                <span>Surname</span>
                            </div>
                        </td>
                        <td style="width: 50%;">
                            <span class="paper-label">Lola sa Ama: (Maiden Name)</span>
                            <div class="paper-val" id="pp-paternal-lola">&nbsp;</div>
                            <div class="paper-sub-labels">
                                <span>First Name</span>
                                <span>M.I.</span>
                                <span>Surname</span>
                            </div>
                        </td>
                    </tr>
                </table>

                <div style="font-size: 8px; font-weight: 700; color: #a82020; margin-bottom: 2px;">MATERNAL GRANDPARENTS (MOTHER'S SIDE)</div>
                <table class="paper-grid-table">
                    <tr>
                        <td style="width: 50%;">
                            <span class="paper-label">Lolo sa Ina:</span>
                            <div class="paper-val" id="pp-maternal-lolo">&nbsp;</div>
                            <div class="paper-sub-labels">
                                <span>First Name</span>
                                <span>M.I.</span>
                                <span>Surname</span>
                            </div>
                        </td>
                        <td style="width: 50%;">
                            <span class="paper-label">Lola sa Ina: (Maiden Name)</span>
                            <div class="paper-val" id="pp-maternal-lola">&nbsp;</div>
                            <div class="paper-sub-labels">
                                <span>First Name</span>
                                <span>M.I.</span>
                                <span>Surname</span>
                            </div>
                        </td>
                    </tr>
                </table>

                <!-- ( SPONSORS ) NINONG AT NINANG -->
                <div style="text-align: left; font-size: 9.5px; font-weight: 700; color: #a82020; margin: 8px 0 4px;">
                    ( SPONSORS ) NINONG AT NINANG
                </div>

                <div class="sponsors-lines-grid" id="pp-sponsors-grid">
                    <!-- Dynamic sponsor lines populated by JS -->
                </div>

                <!-- Footer Sign-off -->
                <div class="paper-footer-section">
                    <div class="paper-sec-note">To be filled by the Secretary of the Parish</div>
                    <div class="paper-priest-line">
                        <span>OFFICIATING PRIEST:</span>
                        <div class="paper-priest-name-line" id="pp-priest-name">&nbsp;</div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- ── DYNAMIC SYNC & TOGGLE SCRIPT ── -->
    <script>
    function togglePaperPreview() {
        const container = document.getElementById('baptism-split-container');
        const textSpan = document.getElementById('toggle-preview-text');
        if (container.classList.contains('preview-hidden')) {
            container.classList.remove('preview-hidden');
            textSpan.textContent = 'Hide Paper Preview';
        } else {
            container.classList.add('preview-hidden');
            textSpan.textContent = 'Show Paper Preview';
        }
    }

    function addSponsorInputRow() {
        const container = document.getElementById('sponsors-form-inputs-container');
        const rows = container.querySelectorAll('.sponsor-input-row');
        const nextNum = rows.length + 1;

        const row = document.createElement('div');
        row.className = 'sponsor-input-row';
        row.style.cssText = 'display: flex; gap: 6px; align-items: center;';
        row.innerHTML = `
            <span style="font-size: 11px; color: var(--text2); width: 22px; text-align: right;">${nextNum}.</span>
            <input type="text" name="sponsors[]" maxlength="70" placeholder="Ninong or Ninang Full Name" class="field-input sponsor-field input-text-clean" oninput="syncFormToPaper()">
            <button type="button" class="btn-icon" onclick="this.parentElement.remove(); syncFormToPaper();" style="width: 26px; height: 26px; color: #ef4444;" title="Remove">
                <i class="fa-solid fa-xmark"></i>
            </button>
        `;
        container.appendChild(row);
        applyInputRestrictions();
        syncFormToPaper();
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
        // Child
        const cFirst = (document.getElementById('in-child-first')?.value || '').trim();
        const cMI = (document.getElementById('in-child-mi')?.value || '').trim();
        const cSurname = (document.getElementById('in-child-surname')?.value || '').trim();
        const childFullName = [cFirst, cMI, cSurname].filter(Boolean).join(' ');
        document.getElementById('pp-child-name').textContent = childFullName || '\u00A0';

        const bDate = document.getElementById('in-date-baptism')?.value || '';
        document.getElementById('pp-date-baptism').textContent = formatDateDisplay(bDate) || '\u00A0';

        const pob = document.getElementById('in-place-birth')?.value || '';
        document.getElementById('pp-place-birth').textContent = pob || '\u00A0';

        const dob = document.getElementById('in-date-birth')?.value || '';
        document.getElementById('pp-date-birth').textContent = formatDateDisplay(dob) || '\u00A0';

        const addr = document.getElementById('in-address')?.value || '';
        document.getElementById('pp-address').textContent = addr || '\u00A0';

        const gender = document.getElementById('in-gender')?.value || 'Male';
        const maleBox = document.getElementById('pp-gender-male-box');
        const femaleBox = document.getElementById('pp-gender-female-box');
        if (maleBox && femaleBox) {
            if (gender.toLowerCase() === 'male') {
                maleBox.textContent = '✓';
                femaleBox.textContent = '';
            } else {
                maleBox.textContent = '';
                femaleBox.textContent = '✓';
            }
        }

        const nat = document.getElementById('in-nationality')?.value || 'Filipino';
        document.getElementById('pp-nationality').textContent = nat || 'Filipino';

        const contact = document.getElementById('in-contact')?.value || '';
        document.getElementById('pp-contact-no').textContent = contact || '\u00A0';

        // Parents
        const fFirst = (document.getElementById('in-father-first')?.value || '').trim();
        const fMI = (document.getElementById('in-father-mi')?.value || '').trim();
        const fSurname = (document.getElementById('in-father-surname')?.value || '').trim();
        const fatherFullName = [fFirst, fMI, fSurname].filter(Boolean).join(' ');
        document.getElementById('pp-father-name').textContent = fatherFullName || '\u00A0';

        const fPob = document.getElementById('in-father-pob')?.value || '';
        document.getElementById('pp-father-pob').textContent = fPob || '\u00A0';

        const mFirst = (document.getElementById('in-mother-first')?.value || '').trim();
        const mMI = (document.getElementById('in-mother-mi')?.value || '').trim();
        const mSurname = (document.getElementById('in-mother-surname')?.value || '').trim();
        const motherFullName = [mFirst, mMI, mSurname].filter(Boolean).join(' ');
        document.getElementById('pp-mother-name').textContent = motherFullName || '\u00A0';

        const mPob = document.getElementById('in-mother-pob')?.value || '';
        document.getElementById('pp-mother-pob').textContent = mPob || '\u00A0';

        const filiation = (document.getElementById('in-filiation')?.value || 'Kasal').toLowerCase();
        const kasalBox = document.getElementById('pp-filiation-kasal-box');
        const diKasalBox = document.getElementById('pp-filiation-di-kasal-box');
        if (kasalBox && diKasalBox) {
            if (filiation.includes('di') || filiation === 'illegitimate') {
                kasalBox.textContent = '';
                diKasalBox.textContent = '✓';
            } else {
                kasalBox.textContent = '✓';
                diKasalBox.textContent = '';
            }
        }

        // Grandparents
        const pLolo = document.getElementById('in-paternal-lolo')?.value || '';
        document.getElementById('pp-paternal-lolo').textContent = pLolo || '\u00A0';

        const pLola = document.getElementById('in-paternal-lola')?.value || '';
        document.getElementById('pp-paternal-lola').textContent = pLola || '\u00A0';

        const mLolo = document.getElementById('in-maternal-lolo')?.value || '';
        document.getElementById('pp-maternal-lolo').textContent = mLolo || '\u00A0';

        const mLola = document.getElementById('in-maternal-lola')?.value || '';
        document.getElementById('pp-maternal-lola').textContent = mLola || '\u00A0';

        // Sponsors
        const spInputs = document.querySelectorAll('.sponsor-field');
        const spGrid = document.getElementById('pp-sponsors-grid');
        if (spGrid) {
            spGrid.innerHTML = '';
            // Render at least 8 line items on paper sheet
            const count = Math.max(8, spInputs.length);
            for (let i = 0; i < count; i++) {
                const val = spInputs[i] ? spInputs[i].value.trim() : '';
                const line = document.createElement('div');
                line.className = 'sponsor-line-item';
                line.textContent = val ? `${i + 1}. ${val}` : `${i + 1}.`;
                spGrid.appendChild(line);
            }
        }

        // Officiating Priest
        const priest = document.getElementById('in-priest')?.value || '';
        document.getElementById('pp-priest-name').textContent = priest || '\u00A0';
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
        // Names: letters, accents, spaces, periods, dashes, apostrophes only
        document.querySelectorAll('.input-text-clean').forEach(elem => {
            if (elem.dataset.restricted) return;
            elem.dataset.restricted = 'true';
            elem.addEventListener('input', function() {
                this.value = this.value.replace(/[^a-zA-ZñÑ\s\.\-']/g, '');
            });
        });

        // Middle Initial: letters & periods only, uppercase
        document.querySelectorAll('.input-mi-clean').forEach(elem => {
            if (elem.dataset.restricted) return;
            elem.dataset.restricted = 'true';
            elem.addEventListener('input', function() {
                this.value = this.value.replace(/[^a-zA-ZñÑ\.]/g, '').toUpperCase();
            });
        });

        // Addresses & Places: letters, digits, spaces, commas, periods, hyphens, #, /, apostrophes
        document.querySelectorAll('.input-place-clean, .input-address-clean').forEach(elem => {
            if (elem.dataset.restricted) return;
            elem.dataset.restricted = 'true';
            elem.addEventListener('input', function() {
                this.value = this.value.replace(/[^a-zA-Z0-9ñÑ\s\.,\-\'#\/\(\)]/g, '');
            });
        });

        // Registry Codes: book & page numbers
        document.querySelectorAll('.input-code-clean').forEach(elem => {
            if (elem.dataset.restricted) return;
            elem.dataset.restricted = 'true';
            elem.addEventListener('input', function() {
                this.value = this.value.replace(/[^a-zA-Z0-9\s\-\.\/]/g, '').toUpperCase();
            });
        });
    }

    // Run initial sync on load
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
