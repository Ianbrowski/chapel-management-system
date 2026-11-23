<?php
/**
 * View Template: Official Cathedral Confirmation Form
 * Interactive Digital Intake Form on Left + Real-time 1:1 Paper Replica Preview on Right
 * Matches St. Michael's Cathedral Official Confirmation Sheet
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
$confTime = $metadata['confirmation_time'] ?? '';
$dateBaptism = $metadata['date_of_baptism'] ?? '';
$placeOfBaptism = $metadata['place_of_baptism'] ?? ($existingRecord['confirmation_place_of_baptism'] ?? '');
if (empty($dateBaptism) && !empty($placeOfBaptism) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $placeOfBaptism)) {
    $dateBaptism = $placeOfBaptism;
    $placeOfBaptism = '';
}
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
    <title>Official Confirmation Form | IFI Cathedral of St. Michael the Archangel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@500;600;700;800&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <?php $assetV = static fn(string $p): int => @filemtime(__DIR__ . '/../../' . $p) ?: 1; ?>
    <link rel="stylesheet" href="assets/css/index.css?v=<?php echo $assetV('assets/css/index.css'); ?>">
    <link rel="stylesheet" href="assets/css/workspace.css?v=<?php echo $assetV('assets/css/workspace.css'); ?>">
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

        /* Header Layout */
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

        /* Form Title */
        .paper-form-title {
            text-align: center;
            font-family: 'Cinzel', 'Arial Black', sans-serif;
            font-size: 19px;
            font-weight: 800;
            color: #b91c1c;
            letter-spacing: 1.5px;
            margin: 14px 0 10px;
        }

        /* Date of Confirmation & Time header line */
        .paper-event-meta-line {
            display: flex;
            justify-content: flex-start;
            flex-direction: column;
            gap: 4px;
            font-size: 11.5px;
            margin-bottom: 12px;
            color: #333333;
        }
        .paper-meta-row {
            display: flex;
            align-items: baseline;
            gap: 6px;
        }
        .paper-meta-label {
            font-weight: 700;
            color: #333333;
            letter-spacing: 0.5px;
        }
        .paper-meta-underline {
            min-width: 200px;
            border-bottom: 1px solid #111111;
            padding-bottom: 1px;
            font-weight: 600;
        }

        /* Section Tag */
        .paper-section-tag {
            font-size: 12px;
            font-weight: 700;
            color: #222222;
            margin: 8px 0 4px;
            letter-spacing: 0.5px;
        }

        /* Table Boxed Particulars */
        .paper-grid-table {
            width: 100%;
            border-collapse: collapse;
            border: 1.5px solid #222222;
            margin-bottom: 12px;
        }
        .paper-grid-table td {
            border: 1px solid #222222;
            padding: 6px 10px;
            vertical-align: top;
            font-size: 11.5px;
        }
        .paper-label {
            display: block;
            font-size: 10.5px;
            font-weight: 700;
            color: #222222;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            margin-bottom: 3px;
        }
        .paper-val {
            font-family: 'Courier New', Courier, monospace;
            font-size: 13.5px;
            font-weight: 700;
            color: #111111;
            min-height: 18px;
            line-height: 1.25;
            word-break: break-word;
        }
        .paper-sub-labels {
            display: flex;
            justify-content: space-between;
            font-size: 8px;
            color: #777777;
            text-transform: uppercase;
            margin-top: 3px;
            border-top: 1px dotted #ccc;
            padding-top: 2px;
        }

        /* Filiation Checkboxes */
        .paper-filiation-wrap {
            display: flex;
            align-items: center;
            gap: 16px;
            font-size: 11px;
            font-weight: 700;
            margin: 6px 0 10px;
        }
        .paper-checkbox-item {
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .paper-box-square {
            width: 14px;
            height: 14px;
            border: 1.2px solid #222222;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            font-weight: 800;
            line-height: 1;
        }

        /* Sponsors Ninong at Ninang (2 columns of 3 lines) */
        .sponsors-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px 24px;
            margin-top: 6px;
            margin-bottom: 14px;
        }
        .sponsor-line-wrap {
            border-bottom: 1px solid #111111;
            min-height: 22px;
            display: flex;
            align-items: flex-end;
            padding-bottom: 2px;
        }
        .sponsor-val {
            font-family: 'Courier New', Courier, monospace;
            font-size: 12.5px;
            font-weight: 700;
            color: #111111;
        }

        /* Footer Section */
        .paper-footer {
            margin-top: 16px;
            font-size: 11px;
        }
        .sec-note {
            font-size: 9.5px;
            font-style: italic;
            color: #666666;
            margin-bottom: 6px;
        }
        .officiating-priest-line {
            display: flex;
            align-items: baseline;
            gap: 8px;
            margin-top: 4px;
        }
        .priest-underline {
            flex: 1;
            border-bottom: 1px solid #111111;
            padding-bottom: 1px;
            font-family: 'Courier New', Courier, monospace;
            font-size: 13.5px;
            font-weight: 700;
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
                padding: 20mm 15mm !important;
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
            <h2>Cathedral Confirmation Form</h2>
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
            <button type="submit" form="conf-digital-form" class="btn btn-primary btn-sm" style="font-size: 11px; height: 32px; display: inline-flex; align-items: center; gap: 6px;">
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
    <div class="workspace-split-container" id="conf-split-container">

        <!-- ══ LEFT: Interactive Digital Form ══ -->
        <div class="form-side-panel">
            <form id="conf-digital-form" method="POST" action="confirmation_form.php<?php echo $recordId > 0 ? '?record_id=' . $recordId : ''; ?>">
                <input type="hidden" name="action" value="save_confirmation_form">
                <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
                <input type="hidden" name="target_record_id" value="<?php echo $recordId; ?>">
                <input type="hidden" name="scope" value="<?php echo htmlspecialchars($scope); ?>">
                <input type="hidden" name="chapel_id" value="<?php echo (int)($existingRecord['chapel_id'] ?? $userChapel); ?>">

                <!-- 1. CONFIRMATION DETAILS -->
                <div class="form-section-card">
                    <div class="form-section-title">
                        <span><i class="fa-solid fa-dove" style="color:var(--accent); margin-right:4px;"></i> Confirmation Event Schedule</span>
                        <span style="font-size: 9px; color: var(--text2);">Event Schedule</span>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                        <div class="field-group">
                            <label>Date of Confirmation <span style="color:#ef4444;">*</span></label>
                            <input type="date" name="event_date" id="in-conf-date" required min="1900-01-01" max="2099-12-31" value="<?php echo htmlspecialchars($eventDate); ?>" class="field-input" oninput="syncFormToPaper()">
                        </div>
                        <div class="field-group">
                            <label>Time</label>
                            <input type="text" name="confirmation_time" id="in-conf-time" maxlength="20" value="<?php echo htmlspecialchars($confTime); ?>" placeholder="e.g. 10:00 AM" class="field-input" oninput="syncFormToPaper()">
                        </div>
                    </div>
                </div>

                <!-- 2. CANDIDATE (CHILD) INFORMATION -->
                <div class="form-section-card">
                    <div class="form-section-title">
                        <span><i class="fa-solid fa-user-graduate" style="color:var(--accent); margin-right:4px;"></i> ( CHILD ) Confirmation Candidate</span>
                        <span style="font-size: 9px; color: var(--text2);">Candidate Info</span>
                    </div>

                    <div style="display: grid; grid-template-columns: 2fr 1fr 2fr; gap: 8px;">
                        <div class="field-group">
                            <label>First Name <span style="color:#ef4444;">*</span></label>
                            <input type="text" name="child_first_name" id="in-child-first" required maxlength="50" value="<?php echo htmlspecialchars($childFirst); ?>" placeholder="e.g. Maria Clara" class="field-input input-text-clean" oninput="syncFormToPaper()">
                        </div>
                        <div class="field-group">
                            <label>M.I. / Middle Name</label>
                            <input type="text" name="child_middle_name" id="in-child-mi" maxlength="30" value="<?php echo htmlspecialchars($childMI); ?>" placeholder="e.g. S." class="field-input input-text-clean" oninput="syncFormToPaper()">
                        </div>
                        <div class="field-group">
                            <label>Surname <span style="color:#ef4444;">*</span></label>
                            <input type="text" name="child_surname" id="in-child-surname" required maxlength="50" value="<?php echo htmlspecialchars($childSurname); ?>" placeholder="e.g. Santos" class="field-input input-text-clean" oninput="syncFormToPaper()">
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                        <div class="field-group">
                            <label>Date of Baptism <span style="font-size:10px; color:var(--text2);">(Must precede confirmation)</span></label>
                            <input type="date" name="date_of_baptism" id="in-date-baptism" min="1900-01-01" max="2099-12-31" value="<?php echo htmlspecialchars($dateBaptism); ?>" class="field-input" oninput="syncFormToPaper()">
                        </div>
                        <div class="field-group">
                            <label>Place of Baptism <span style="font-size:9.5px; color:var(--text2);">(Parish / Church)</span></label>
                            <input type="text" name="place_of_baptism" id="in-place-baptism" maxlength="100" value="<?php echo htmlspecialchars($placeOfBaptism); ?>" placeholder="e.g. Cathedral of St. Michael" class="field-input input-place-clean" oninput="syncFormToPaper()">
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                        <div class="field-group">
                            <label>Date of Birth</label>
                            <input type="date" name="date_of_birth" id="in-date-birth" min="1900-01-01" max="2099-12-31" value="<?php echo htmlspecialchars($dob); ?>" class="field-input" oninput="syncFormToPaper()">
                        </div>
                        <div class="field-group">
                            <label>Place of Birth <span style="font-size:9px; color:var(--text2);">(City, Province)</span></label>
                            <input type="text" name="place_of_birth" id="in-place-birth" list="dl-ph-all-cities" maxlength="100" value="<?php echo htmlspecialchars($pob); ?>" placeholder="e.g. Angono, Rizal" class="field-input input-place-clean" oninput="syncFormToPaper()" autocomplete="off">
                        </div>
                    </div>

                    <div class="field-group">
                        <label>Address <span style="font-size:9px; color:var(--text2);">(House No., St., Barangay, City, Province)</span></label>
                        <input type="text" name="address" id="in-address" maxlength="150" value="<?php echo htmlspecialchars($address); ?>" placeholder="e.g. 123 Rubio St., Digman, Bacoor City" class="field-input input-address-clean" oninput="syncFormToPaper()">
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 8px;">
                        <div class="field-group">
                            <label>Gender</label>
                            <select name="gender" id="in-gender" class="field-select" onchange="syncFormToPaper()">
                                <option value="Male" <?php echo strcasecmp($gender, 'Male') === 0 ? 'selected' : ''; ?>>Male</option>
                                <option value="Female" <?php echo strcasecmp($gender, 'Female') === 0 ? 'selected' : ''; ?>>Female</option>
                            </select>
                        </div>
                        <div class="field-group">
                            <label>Nationality</label>
                            <input type="text" name="nationality" id="in-nationality" list="dl-citizenships" maxlength="40" value="<?php echo htmlspecialchars($nationality); ?>" placeholder="Filipino" class="field-input input-text-clean" oninput="syncFormToPaper()" autocomplete="off">
                        </div>
                        <div class="field-group">
                            <label>Contact No. <span style="font-size:9.5px; opacity:0.8;">09XX-XXX-XXXX</span></label>
                            <input type="tel" name="contact_no" id="in-contact" maxlength="13" inputmode="tel" value="<?php echo htmlspecialchars($contactNo); ?>" placeholder="0912-345-6789" class="field-input tel-mask" oninput="formatTelMask(this); syncFormToPaper()">
                        </div>
                    </div>
                </div>

                <!-- 3. PARENTS INFORMATION -->
                <div class="form-section-card">
                    <div class="form-section-title">
                        <span><i class="fa-solid fa-people-roof" style="color:var(--accent); margin-right:4px;"></i> ( PARENTS ) Particulars</span>
                        <span style="font-size: 9px; color: var(--text2);">Family Info</span>
                    </div>

                    <!-- Father -->
                    <div style="font-size:10.5px; font-weight:700; color:var(--text); margin-top:2px;">Father's Name & Place of Birth:</div>
                    <div style="display: grid; grid-template-columns: 2fr 1fr 2fr; gap: 6px;">
                        <input type="text" name="father_first_name" id="in-father-first" maxlength="50" value="<?php echo htmlspecialchars($fatherFirst); ?>" placeholder="First Name" class="field-input input-text-clean" oninput="syncFormToPaper()">
                        <input type="text" name="father_mi" id="in-father-mi" maxlength="5" value="<?php echo htmlspecialchars($fatherMI); ?>" placeholder="M.I." class="field-input input-mi-clean" oninput="syncFormToPaper()">
                        <input type="text" name="father_surname" id="in-father-surname" maxlength="50" value="<?php echo htmlspecialchars($fatherSurname); ?>" placeholder="Surname" class="field-input input-text-clean" oninput="syncFormToPaper()">
                    </div>
                    <div class="field-group">
                        <input type="text" name="father_birth_place" id="in-father-pob" list="dl-ph-all-cities" maxlength="100" value="<?php echo htmlspecialchars($fatherBirthPlace); ?>" placeholder="Father's Place of Birth (City/Province)" class="field-input input-place-clean" oninput="syncFormToPaper()" autocomplete="off">
                    </div>

                    <!-- Mother -->
                    <div style="font-size:10.5px; font-weight:700; color:var(--text); margin-top:6px;">Mother's Maiden Name & Place of Birth:</div>
                    <div style="display: grid; grid-template-columns: 2fr 1fr 2fr; gap: 6px;">
                        <input type="text" name="mother_first_name" id="in-mother-first" maxlength="50" value="<?php echo htmlspecialchars($motherFirst); ?>" placeholder="First Name" class="field-input input-text-clean" oninput="syncFormToPaper()">
                        <input type="text" name="mother_mi" id="in-mother-mi" maxlength="5" value="<?php echo htmlspecialchars($motherMI); ?>" placeholder="M.I." class="field-input input-mi-clean" oninput="syncFormToPaper()">
                        <input type="text" name="mother_surname" id="in-mother-surname" maxlength="50" value="<?php echo htmlspecialchars($motherSurname); ?>" placeholder="Maiden Surname" class="field-input input-text-clean" oninput="syncFormToPaper()">
                    </div>
                    <div class="field-group">
                        <input type="text" name="mother_birth_place" id="in-mother-pob" list="dl-ph-all-cities" maxlength="100" value="<?php echo htmlspecialchars($motherBirthPlace); ?>" placeholder="Mother's Place of Birth (City/Province)" class="field-input input-place-clean" oninput="syncFormToPaper()" autocomplete="off">
                    </div>

                    <!-- Filiation -->
                    <div class="field-group" style="margin-top:4px;">
                        <label>Filiation (Civil / Church Status)</label>
                        <select name="filiation" id="in-filiation" class="field-select" onchange="syncFormToPaper()">
                            <option value="Kasal" <?php echo ((stripos($filiation, 'kasal') !== false && stripos($filiation, 'di') === false) || strcasecmp($filiation, 'Legitimate') === 0) ? 'selected' : ''; ?>>Kasal (Legitimate)</option>
                            <option value="Di Kasal" <?php echo (stripos($filiation, 'di') !== false || strcasecmp($filiation, 'Illegitimate') === 0) ? 'selected' : ''; ?>>Di Kasal (Illegitimate)</option>
                        </select>
                    </div>
                </div>

                <!-- 4. SPONSORS (NINONG AT NINANG) -->
                <div class="form-section-card">
                    <div class="form-section-title">
                        <span><i class="fa-solid fa-hands-praying" style="color:var(--accent); margin-right:4px;"></i> ( SPONSORS ) Ninong at Ninang</span>
                        <span style="font-size: 9px; color: var(--text2);">Sponsors</span>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px;">
                        <?php for ($i = 0; $i < 6; $i++): ?>
                            <div class="field-group">
                                <label style="font-size:9px;">Sponsor #<?php echo $i + 1; ?></label>
                                <input type="text" name="sponsors[]" id="in-sponsor-<?php echo $i; ?>" maxlength="70" value="<?php echo htmlspecialchars($sponsors[$i] ?? ''); ?>" placeholder="Full Name" class="field-input sponsor-input input-text-clean" oninput="syncFormToPaper()">
                            </div>
                        <?php endfor; ?>
                    </div>
                </div>

                <!-- 5. PARISH RECORD PARTICULAR -->
                <div class="form-section-card">
                    <div class="form-section-title">
                        <span><i class="fa-solid fa-book-bible" style="color:var(--accent); margin-right:4px;"></i> Parish Registry Archives</span>
                        <span style="font-size: 9px; color: var(--text2);">Office Use</span>
                    </div>

                    <div class="field-group">
                        <label>Officiating Priest / Minister</label>
                        <input type="text" name="officiating_priest" id="in-priest" maxlength="70" value="<?php echo htmlspecialchars($officiatingPriest); ?>" placeholder="e.g. Fr. Alexander" class="field-input input-text-clean" oninput="syncFormToPaper()">
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
                </div>

                <!-- Submit Button -->
                <div style="padding-bottom: 24px;">
                    <button type="submit" class="btn btn-primary" style="width: 100%; height: 38px; justify-content: center; font-weight: 700; font-size: 13px;">
                        <i class="fa-solid fa-floppy-disk"></i> <?php echo $recordId > 0 ? 'Update Confirmation Record' : 'Save Confirmation to Registry'; ?>
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
                <div class="paper-form-title">CONFIRMATION FORM</div>

                <!-- Date of Confirmation & Time -->
                <div class="paper-event-meta-line">
                    <div class="paper-meta-row">
                        <span class="paper-meta-label">DATE OF CONFIRMATION:</span>
                        <span class="paper-meta-underline" id="pp-conf-date">&nbsp;</span>
                    </div>
                    <div class="paper-meta-row">
                        <span class="paper-meta-label">TIME:</span>
                        <span class="paper-meta-underline" id="pp-conf-time">&nbsp;</span>
                    </div>
                </div>

                <!-- ( CHILD ) Tag -->
                <div class="paper-section-tag">( CHILD )</div>

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
                            <div style="display: flex; gap: 14px; margin-top: 4px; font-size: 11px; font-weight: 700;">
                                <div class="paper-checkbox-item">
                                    <div class="paper-box-square" id="pp-gender-male-box">✓</div>
                                    <span>( MALE )</span>
                                </div>
                                <div class="paper-checkbox-item">
                                    <div class="paper-box-square" id="pp-gender-female-box"></div>
                                    <span>( FEMALE )</span>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <span class="paper-label">Nationality:</span>
                            <div class="paper-val" id="pp-nationality">Filipino</div>
                        </td>
                        <td>
                            <span class="paper-label">Contact No:</span>
                            <div class="paper-val" id="pp-contact-no">&nbsp;</div>
                        </td>
                    </tr>
                </table>

                <!-- ( PARENTS ) Tag -->
                <div class="paper-section-tag">( PARENTS )</div>

                <!-- ( PARENTS ) Table -->
                <table class="paper-grid-table">
                    <tr>
                        <td style="width: 65%;">
                            <span class="paper-label">Name of Father:</span>
                            <div class="paper-val" id="pp-father-name">&nbsp;</div>
                            <div class="paper-sub-labels">
                                <span>First Name</span>
                                <span>M.I.</span>
                                <span>Surname</span>
                            </div>
                        </td>
                        <td style="width: 35%;">
                            <span class="paper-label">Place of Birth:</span>
                            <div class="paper-val" id="pp-father-pob">&nbsp;</div>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <span class="paper-label">Name of Mother (Maiden Name):</span>
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
                </table>

                <!-- Filiation -->
                <div class="paper-filiation-wrap">
                    <span>FILIATION:</span>
                    <div class="paper-checkbox-item">
                        <div class="paper-box-square" id="pp-filiation-kasal-box">✓</div>
                        <span>[ KASAL ]</span>
                    </div>
                    <div class="paper-checkbox-item">
                        <div class="paper-box-square" id="pp-filiation-di-kasal-box"></div>
                        <span>[ DI KASAL ]</span>
                    </div>
                </div>

                <!-- ( SPONSORS ) NINONG AT NINANG -->
                <div class="paper-section-tag">( SPONSORS ) NINONG AT NINANG</div>
                <div class="sponsors-grid">
                    <?php for ($i = 0; $i < 6; $i++): ?>
                        <div class="sponsor-line-wrap">
                            <span class="sponsor-val" id="pp-sponsor-<?php echo $i; ?>">&nbsp;</span>
                        </div>
                    <?php endfor; ?>
                </div>

                <!-- Footer Section -->
                <div class="paper-footer">
                    <div class="sec-note">To be filled by the Secretary of the Parish</div>
                    <div class="officiating-priest-line">
                        <span style="font-weight: 700; font-size: 11px;">OFFICIATING PRIEST</span>
                        <div class="priest-underline" id="pp-priest">&nbsp;</div>
                    </div>
                    <div style="display: flex; gap: 24px; margin-top: 6px; font-size: 11px; color: #333;">
                        <div><strong>Book No.:</strong> <span id="pp-book-no">&nbsp;</span></div>
                        <div><strong>Page No.:</strong> <span id="pp-page-no">&nbsp;</span></div>
                    </div>
                </div>

            </div>
        </div>

    </div>

    <!-- ── REAL-TIME INTERACTIVE SYNC SCRIPT ── -->
    <script>
    function togglePaperPreview() {
        const splitContainer = document.getElementById('conf-split-container');
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
        // Confirmation Event Details
        const confDate = document.getElementById('in-conf-date')?.value || '';
        document.getElementById('pp-conf-date').textContent = formatDateDisplay(confDate) || '\u00A0';

        const confTime = document.getElementById('in-conf-time')?.value || '';
        document.getElementById('pp-conf-time').textContent = confTime || '\u00A0';

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

        // Sponsors (6 lines)
        for (let i = 0; i < 6; i++) {
            const spInput = document.getElementById('in-sponsor-' + i);
            const ppSp = document.getElementById('pp-sponsor-' + i);
            if (spInput && ppSp) {
                ppSp.textContent = spInput.value.trim() || '\u00A0';
            }
        }

        // Priest & Registry
        const priest = document.getElementById('in-priest')?.value || '';
        document.getElementById('pp-priest').textContent = priest || '\u00A0';

        const bk = document.getElementById('in-book-no')?.value || '';
        document.getElementById('pp-book-no').textContent = bk || '\u00A0';

        const pg = document.getElementById('in-page-no')?.value || '';
        document.getElementById('pp-page-no').textContent = pg || '\u00A0';
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
