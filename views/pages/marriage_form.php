<?php
/**
 * View Template: Official Application for Marriage License (Municipal Form 90 / Form No. 2)
 * Dual-Panel: Responsive Digital Intake Form on Left + Live 1:1 Physical Government Replica on Right
 * Robust Input Constraints, Character Limiters, Phone Formatting Masks & Anti-Bug Sanitizers
 */

$groomFirst = $metadata['groom_first_name'] ?? '';
$groomMiddle = $metadata['groom_middle_name'] ?? '';
$groomSurname = $metadata['groom_surname'] ?? '';

// Fallback from existingRecord
if (!$groomFirst && !empty($existingRecord['groom_name'])) {
    $parts = explode(' ', trim($existingRecord['groom_name']));
    if (count($parts) >= 3) {
        $groomSurname = array_pop($parts);
        $groomMiddle = array_pop($parts);
        $groomFirst = implode(' ', $parts);
    } elseif (count($parts) === 2) {
        $groomSurname = $parts[1];
        $groomFirst = $parts[0];
    } else {
        $groomFirst = $existingRecord['groom_name'];
    }
}

$brideFirst = $metadata['bride_first_name'] ?? '';
$brideMiddle = $metadata['bride_middle_name'] ?? '';
$brideSurname = $metadata['bride_surname'] ?? '';

if (!$brideFirst && !empty($existingRecord['bride_name'])) {
    $bparts = explode(' ', trim($existingRecord['bride_name']));
    if (count($bparts) >= 3) {
        $brideSurname = array_pop($bparts);
        $brideMiddle = array_pop($bparts);
        $brideFirst = implode(' ', $bparts);
    } elseif (count($bparts) === 2) {
        $brideSurname = $bparts[1];
        $brideFirst = $bparts[0];
    } else {
        $brideFirst = $existingRecord['bride_name'];
    }
}

$province = $metadata['province'] ?? 'Rizal';
$municipality = $metadata['municipality'] ?? 'Angono';
$registryNo = $metadata['registry_no'] ?? '';
$receivedBy = $metadata['received_by'] ?? '';
$dateOfReceipt = $metadata['date_of_receipt'] ?? '';
$marriageLicenseNo = $metadata['marriage_license_no'] ?? '';
$dateOfIssuance = $metadata['date_of_issuance'] ?? '';

// Groom values
$groomDob = $metadata['groom_dob'] ?? '';
$groomAge = $metadata['groom_age'] ?? '';
$groomPob = $metadata['groom_pob'] ?? '';
$groomSex = $metadata['groom_sex'] ?? 'Male';
$groomCitizenship = $metadata['groom_citizenship'] ?? 'Filipino';
$groomResidence = $metadata['groom_residence'] ?? '';
$groomReligion = $metadata['groom_religion'] ?? 'Philippine Independent Church';
$groomCivilStatus = $metadata['groom_civil_status'] ?? 'Single';
$groomContactNo = $metadata['groom_contact_no'] ?? '';
$groomDissolvedHow = $metadata['groom_dissolved_how'] ?? '';
$groomDissolvedPlace = $metadata['groom_dissolved_place'] ?? '';
$groomDissolvedDate = $metadata['groom_dissolved_date'] ?? '';
$groomDegreeRel = $metadata['groom_degree_relationship'] ?? 'None';

$groomFatherFirst = $metadata['groom_father_first'] ?? '';
$groomFatherMI = $metadata['groom_father_mi'] ?? '';
$groomFatherSurname = $metadata['groom_father_surname'] ?? '';
$groomFatherCitizenship = $metadata['groom_father_citizenship'] ?? 'Filipino';
$groomFatherResidence = $metadata['groom_father_residence'] ?? '';

$groomMotherFirst = $metadata['groom_mother_first'] ?? '';
$groomMotherMI = $metadata['groom_mother_mi'] ?? '';
$groomMotherSurname = $metadata['groom_mother_surname'] ?? '';
$groomMotherCitizenship = $metadata['groom_mother_citizenship'] ?? 'Filipino';
$groomMotherResidence = $metadata['groom_mother_residence'] ?? '';

$groomConsentPerson = $metadata['groom_consent_person'] ?? '';
$groomConsentRel = $metadata['groom_consent_relationship'] ?? '';
$groomConsentCitizenship = $metadata['groom_consent_citizenship'] ?? '';
$groomConsentResidence = $metadata['groom_consent_residence'] ?? '';
$groomCtcNo = $metadata['groom_ctc_no'] ?? '';
$groomCtcDate = $metadata['groom_ctc_date'] ?? '';
$groomCtcPlace = $metadata['groom_ctc_place'] ?? '';

// Bride values
$brideDob = $metadata['bride_dob'] ?? '';
$brideAge = $metadata['bride_age'] ?? '';
$bridePob = $metadata['bride_pob'] ?? '';
$brideSex = $metadata['bride_sex'] ?? 'Female';
$brideCitizenship = $metadata['bride_citizenship'] ?? 'Filipino';
$brideResidence = $metadata['bride_residence'] ?? '';
$brideReligion = $metadata['bride_religion'] ?? 'Philippine Independent Church';
$brideCivilStatus = $metadata['bride_civil_status'] ?? 'Single';
$brideContactNo = $metadata['bride_contact_no'] ?? '';
$brideDissolvedHow = $metadata['bride_dissolved_how'] ?? '';
$brideDissolvedPlace = $metadata['bride_dissolved_place'] ?? '';
$brideDissolvedDate = $metadata['bride_dissolved_date'] ?? '';
$brideDegreeRel = $metadata['bride_degree_relationship'] ?? 'None';

$brideFatherFirst = $metadata['bride_father_first'] ?? '';
$brideFatherMI = $metadata['bride_father_mi'] ?? '';
$brideFatherSurname = $metadata['bride_father_surname'] ?? '';
$brideFatherCitizenship = $metadata['bride_father_citizenship'] ?? 'Filipino';
$brideFatherResidence = $metadata['bride_father_residence'] ?? '';

$brideMotherFirst = $metadata['bride_mother_first'] ?? '';
$brideMotherMI = $metadata['bride_mother_mi'] ?? '';
$brideMotherSurname = $metadata['bride_mother_surname'] ?? '';
$brideMotherCitizenship = $metadata['bride_mother_citizenship'] ?? 'Filipino';
$brideMotherResidence = $metadata['bride_mother_residence'] ?? '';

$brideConsentPerson = $metadata['bride_consent_person'] ?? '';
$brideConsentRel = $metadata['bride_consent_relationship'] ?? '';
$brideConsentCitizenship = $metadata['bride_consent_citizenship'] ?? '';
$brideConsentResidence = $metadata['bride_consent_residence'] ?? '';
$brideCtcNo = $metadata['bride_ctc_no'] ?? '';
$brideCtcDate = $metadata['bride_ctc_date'] ?? '';
$brideCtcPlace = $metadata['bride_ctc_place'] ?? '';

// Church & Canonical values
$eventDate = $existingRecord['event_date'] ?? ($existingRecord['date_of_marriage'] ?? date('Y-m-d'));
$placeOfMarriage = $existingRecord['place_of_marriage'] ?? 'Cathedral of St. Michael the Archangel';
$witness1 = $existingRecord['witness_1'] ?? '';
$witness2 = $existingRecord['witness_2'] ?? '';
$officiatingPriest = $existingRecord['officiating_priest'] ?? 'Very Rev. Fr. Ramoncito Tadepa';
$bookNumber = $existingRecord['book_number'] ?? 'BK-' . date('Y');
$pageNumber = $existingRecord['page_number'] ?? 'PG-01';
$scope = $existingRecord['scope'] ?? ($_GET['scope'] ?? ($isAdmin ? 'cathedral' : 'chapel'));
if (!in_array($scope, ['cathedral', 'chapel'], true)) { $scope = 'cathedral'; }
$refId = $existingRecord['reference_id'] ?? '';

$civilRegistrarName = $metadata['civil_registrar_name'] ?? 'Local Civil Registrar';
$swornDay = $metadata['sworn_day'] ?? date('j');
$swornMonth = $metadata['sworn_month'] ?? date('F');
$swornYear = $metadata['sworn_year'] ?? date('Y');
$swornAt = $metadata['sworn_at'] ?? 'Angono, Rizal';
$marginalNotes = $metadata['marginal_notes'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Application for Marriage License | IFI Cathedral of St. Michael the Archangel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@500;600;700;800&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <?php $assetV = static fn(string $p): int => @filemtime(__DIR__ . '/../../' . $p) ?: 1; ?>
    <link rel="stylesheet" href="assets/css/index.css?v=<?php echo $assetV('assets/css/index.css'); ?>">
    <link rel="stylesheet" href="assets/css/workspace.css?v=<?php echo $assetV('assets/css/workspace.css'); ?>">
    <link rel="icon" type="image/x-icon" href="assets/img/2323.png">
    
    <script src="assets/js/ph-geo-data.js?v=<?php echo $assetV('assets/js/ph-geo-data.js'); ?>"></script>
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
        .mar-topbar {
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
        .mar-topbar-title {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .mar-topbar-title h2 {
            font-family: var(--font-heading, 'Cinzel', serif);
            font-size: 1.05rem;
            margin: 0;
            color: var(--accent);
            font-weight: 700;
        }
        .mar-badge {
            background: rgba(197, 160, 89, 0.15);
            color: var(--accent, #c5a059);
            border: 1px solid rgba(197, 160, 89, 0.3);
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 0.04em;
        }

        /* ── MAIN WORKSPACE SPLIT ── */
        .workspace-split-container {
            flex: 1;
            display: grid;
            grid-template-columns: 46% 54%;
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
            padding: 14px;
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
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .field-group label .field-hint {
            font-size: 9.5px;
            font-weight: normal;
            color: var(--text2);
            text-transform: none;
            opacity: 0.8;
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
        .field-input.has-error {
            border-color: #ef4444 !important;
            box-shadow: 0 0 0 2px rgba(239, 68, 68, 0.2) !important;
        }
        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }
        .grid-3 {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 8px;
        }
        .grid-4 {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr 1fr;
            gap: 6px;
        }

        /* ── RIGHT: PAPER PREVIEW PANEL ── */
        .preview-side-panel {
            height: 100%;
            overflow-y: auto;
            background: #25282c;
            padding: 24px;
            display: flex;
            justify-content: center;
            align-items: flex-start;
        }
        .workspace-split-container.preview-hidden .preview-side-panel {
            display: none;
        }

        /* ── 1:1 REPLICA PHYSICAL FORM (MUNICIPAL FORM 90) ── */
        .official-paper-sheet {
            width: 100%;
            max-width: 820px;
            background: #ffffff;
            color: #000000;
            box-shadow: 0 12px 40px rgba(0,0,0,0.6);
            padding: 24px 28px;
            box-sizing: border-box;
            border-radius: 1px;
            font-family: 'Arial Narrow', Arial, sans-serif;
            position: relative;
            line-height: 1.15;
            font-size: 11px;
        }

        .paper-header-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            font-size: 9.5px;
            font-family: Arial, sans-serif;
            margin-bottom: 4px;
        }
        .paper-header-row .left-form-code {
            font-size: 9px;
            font-weight: bold;
            line-height: 1.1;
        }
        .paper-header-row .right-form-instr {
            font-size: 9px;
            font-style: italic;
        }
        .paper-header-title {
            text-align: center;
            margin: 2px 0 8px;
        }
        .paper-header-title .gov-rep {
            font-size: 9.5px;
            font-family: Arial, sans-serif;
            letter-spacing: 0.05em;
            text-transform: uppercase;
        }
        .paper-header-title .gov-agency {
            font-size: 10.5px;
            font-family: Arial, sans-serif;
            font-weight: bold;
            letter-spacing: 0.04em;
        }
        .paper-header-title .main-doc-title {
            font-size: 14.5px;
            font-family: Arial, sans-serif;
            font-weight: 800;
            letter-spacing: 0.08em;
            margin-top: 4px;
        }

        /* Top Box for Registry & License */
        .paper-meta-box {
            border: 1.5px solid #000;
            margin-bottom: 6px;
            font-size: 10px;
            display: grid;
            grid-template-columns: 55% 45%;
        }
        .meta-col {
            padding: 4px 6px;
        }
        .meta-col-left {
            border-right: 1.5px solid #000;
        }
        .meta-line {
            display: flex;
            align-items: baseline;
            margin-bottom: 2px;
        }
        .meta-line-lbl {
            font-weight: 600;
            margin-right: 4px;
            white-space: nowrap;
        }
        .meta-line-val {
            flex: 1;
            border-bottom: 1px solid #000;
            padding-left: 4px;
            min-height: 14px;
            font-weight: bold;
        }

        /* 3-COLUMN TABLE FOR GROOM / FIELD LABELS / BRIDE */
        .form90-table {
            width: 100%;
            border-collapse: collapse;
            border: 1.5px solid #000;
            table-layout: fixed;
        }
        .form90-table th, .form90-table td {
            border: 1px solid #000;
            padding: 2.5px 4px;
            vertical-align: top;
            font-size: 9.5px;
        }
        .col-groom {
            width: 41%;
        }
        .col-labels {
            width: 18%;
            text-align: center;
            background: #f7f7f7;
            font-weight: 700;
            font-size: 9px;
            line-height: 1.1;
        }
        .col-bride {
            width: 41%;
        }

        .header-party-title {
            text-align: center;
            font-weight: bold;
            font-size: 12px;
            padding: 4px !important;
            letter-spacing: 0.1em;
            background: #f0f0f0;
        }
        .petition-statement {
            font-size: 8.5px;
            line-height: 1.15;
            text-align: justify;
            font-style: italic;
            padding: 4px !important;
        }
        .sub-field-hint {
            font-size: 7.5px;
            color: #333;
            display: flex;
            justify-content: space-around;
            border-top: 0.5px dotted #999;
            margin-top: 1px;
            padding-top: 1px;
        }
        .val-text {
            font-weight: 600;
            min-height: 13px;
            display: block;
            color: #000;
            word-break: break-word;
        }

        /* Bottom Jurats / Signatures */
        .bottom-jurats-box {
            border: 1.5px solid #000;
            border-top: none;
            display: grid;
            grid-template-columns: 41% 18% 41%;
        }
        .jurat-col {
            padding: 6px;
            font-size: 8.5px;
            line-height: 1.2;
        }
        .jurat-col-center {
            border-left: 1px solid #000;
            border-right: 1px solid #000;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            font-weight: bold;
            font-size: 8px;
            text-transform: uppercase;
            padding: 4px;
            background: #fafafa;
        }
        .sig-applicant-line {
            margin-top: 16px;
            border-top: 1px solid #000;
            text-align: center;
            font-weight: bold;
            font-size: 8.5px;
            padding-top: 2px;
        }
        .jurat-sworn-text {
            margin-top: 6px;
            text-align: justify;
        }
        .sig-registrar-line {
            margin-top: 22px;
            border-top: 1px solid #000;
            text-align: center;
            font-size: 8px;
            font-weight: bold;
            padding-top: 2px;
        }

        /* Buttons & Alerts */
        .btn-top-back {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            font-size: 12px;
            font-weight: 600;
            color: var(--text);
            text-decoration: none;
            background: var(--bg3);
            border: 1px solid var(--border);
            border-radius: 6px;
            transition: all 0.15s ease;
        }
        .btn-top-back:hover {
            border-color: var(--accent);
            color: var(--accent);
        }
        .btn-top-preview {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            font-size: 12px;
            font-weight: 600;
            background: transparent;
            color: var(--text2);
            border: 1px solid var(--border);
            border-radius: 6px;
            cursor: pointer;
        }
        .btn-top-preview:hover {
            color: var(--text);
            border-color: var(--text2);
        }
        .btn-top-print {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            font-size: 12px;
            font-weight: 600;
            color: var(--text);
            background: var(--bg2);
            border: 1px solid var(--border);
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.15s ease;
        }
        .btn-top-print:hover { background: var(--hover-bg); border-color: var(--accent); }
        .btn-top-save {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 16px;
            font-size: 12px;
            font-weight: 700;
            color: #111;
            background: var(--accent);
            border: none;
            border-radius: 6px;
            cursor: pointer;
            box-shadow: 0 2px 6px rgba(201,168,76,0.3);
            transition: all 0.15s ease;
        }
        .btn-top-save:hover { filter: brightness(1.1); transform: translateY(-1px); }

        .flash-bar {
            padding: 8px 14px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 10px;
        }
        .flash-bar.success {
            background: rgba(34, 197, 94, 0.15);
            border: 1px solid rgba(34, 197, 94, 0.35);
            color: #4ade80;
        }
        .flash-bar.error {
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid rgba(239, 68, 68, 0.35);
            color: #f87171;
        }

        /* ── PRINT MEDIA RULES (Clean Paper Export) ── */
        @media print {
            body {
                display: block !important;
                background: #ffffff !important;
                color: #000000 !important;
                height: auto !important;
                overflow: visible !important;
            }
            .mar-topbar, .form-side-panel, .btn-top-preview, .btn-top-print, .btn-top-save, .flash-bar {
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
                background: #ffffff !important;
                padding: 0 !important;
                overflow: visible !important;
                height: auto !important;
                display: block !important;
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
                padding: 10mm 12mm !important;
                max-width: 100% !important;
                width: 100% !important;
                margin: 0 auto !important;
            }
            @page {
                size: legal portrait;
                margin: 6mm 8mm;
            }
        }
    </style>
</head>
<body>

    <!-- ── TOP ACTIONS BAR ── -->
    <header class="mar-topbar">
        <div class="mar-topbar-title">
            <a href="dashboard.php?section=records_<?php echo htmlspecialchars($scope); ?>" class="btn-top-back" title="Return to Sacramental Records Dashboard">
                <i class="fa-solid fa-arrow-left"></i> Back to Records
            </a>
            <h2>Application for Marriage License</h2>
            <span class="mar-badge">Municipal Form 90 (Form No. 2)</span>
            <?php if ($recordId > 0): ?>
                <span style="font-size: 11px; color: var(--text2); background: var(--bg3); padding: 3px 8px; border-radius: 4px; border: 1px solid var(--border);">
                    <i class="fa-solid fa-database" style="color: var(--accent);"></i> Registry Record #<?php echo $recordId; ?> <?php echo $refId ? "($refId)" : ''; ?>
                </span>
            <?php else: ?>
                <span style="font-size: 11px; color: var(--text2); background: var(--bg3); padding: 3px 8px; border-radius: 4px; border: 1px solid var(--border);">
                    <i class="fa-solid fa-plus-circle" style="color: var(--accent);"></i> New Marriage Intake
                </span>
            <?php endif; ?>
        </div>

        <div style="display: flex; align-items: center; gap: 8px;">
            <button type="button" class="btn-top-preview" onclick="togglePreviewPane()" title="Toggle Side-by-side view">
                <i class="fa-solid fa-table-columns"></i> Toggle Side-by-Side
            </button>
            <button type="button" class="btn-top-print" onclick="window.print()" title="Print physical copy">
                <i class="fa-solid fa-print"></i> Print Form
            </button>
            <button type="submit" form="marriage-digital-form" class="btn-top-save">
                <i class="fa-solid fa-floppy-disk"></i> Save to Registry
            </button>
        </div>
    </header>

    <!-- ── WORKSPACE SPLIT ── -->
    <div class="workspace-split-container" id="mar-workspace-split">
        
        <!-- ── LEFT PANEL: DIGITAL INTAKE FORM ── -->
        <aside class="form-side-panel">
            <?php if (!empty($flashMessage)): ?>
                <div class="flash-bar <?php echo $flashType; ?>">
                    <i class="fa-solid <?php echo $flashType === 'success' ? 'fa-circle-check' : 'fa-triangle-exclamation'; ?>"></i>
                    <span><?php echo htmlspecialchars($flashMessage); ?></span>
                </div>
            <?php endif; ?>

            <form id="marriage-digital-form" method="POST" action="marriage_form.php<?php echo $recordId > 0 ? '?record_id=' . $recordId : ''; ?>">
                <input type="hidden" name="action" value="save_marriage_form">
                <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">
                <input type="hidden" name="target_record_id" value="<?php echo $recordId; ?>">
                <input type="hidden" name="chapel_id" value="<?php echo (int)($existingRecord['chapel_id'] ?? ($userChapel ?? 0)); ?>">

                <!-- SECTION 1: Filing & Civil Registry Information -->
                <div class="form-section-card">
                    <div class="form-section-title">
                        <span><i class="fa-solid fa-landmark mr-6"></i> Civil Registry & Filing Details</span>
                        <span style="font-size: 10px; color: var(--text2);">Official Registry Meta</span>
                    </div>

                    <div class="grid-2">
                        <div class="field-group">
                            <label>Province <span class="field-hint">Select / Type</span></label>
                            <input type="text" name="province" id="in-province" list="dl-ph-provinces" class="field-input" maxlength="50" value="<?php echo htmlspecialchars($province); ?>" placeholder="e.g. Rizal" autocomplete="off">
                        </div>
                        <div class="field-group">
                            <label>City / Municipality <span class="field-hint">Select / Type</span></label>
                            <input type="text" name="municipality" id="in-municipality" list="dl-ph-municipalities" class="field-input" maxlength="50" value="<?php echo htmlspecialchars($municipality); ?>" placeholder="e.g. Angono" autocomplete="off">
                        </div>
                    </div>

                    <div class="grid-3">
                        <div class="field-group">
                            <label>Registry Number</label>
                            <input type="text" name="registry_no" id="in-registry-no" class="field-input" maxlength="30" value="<?php echo htmlspecialchars($registryNo); ?>" placeholder="e.g. 2026-0428">
                        </div>
                        <div class="field-group">
                            <label>Received By</label>
                            <input type="text" name="received_by" id="in-received-by" class="field-input" maxlength="60" value="<?php echo htmlspecialchars($receivedBy); ?>" placeholder="Staff name">
                        </div>
                        <div class="field-group">
                            <label>Date of Receipt</label>
                            <input type="date" name="date_of_receipt" id="in-date-receipt" class="field-input" min="1900-01-01" max="2099-12-31" value="<?php echo htmlspecialchars($dateOfReceipt); ?>">
                        </div>
                    </div>

                    <div class="grid-2">
                        <div class="field-group">
                            <label>Marriage License No.</label>
                            <input type="text" name="marriage_license_no" id="in-license-no" class="field-input" maxlength="30" value="<?php echo htmlspecialchars($marriageLicenseNo); ?>" placeholder="e.g. ML-2026-9810">
                        </div>
                        <div class="field-group">
                            <label>Date of Issuance of License</label>
                            <input type="date" name="date_of_issuance" id="in-date-issuance" class="field-input" min="1900-01-01" max="2099-12-31" value="<?php echo htmlspecialchars($dateOfIssuance); ?>">
                        </div>
                    </div>
                </div>

                <!-- SECTION 2: Groom Information (Fields 1-21) -->
                <div class="form-section-card">
                    <div class="form-section-title">
                        <span><i class="fa-solid fa-mars" style="color: var(--accent);"></i> Contracting Party: GROOM</span>
                        <span style="font-size: 10px; color: var(--accent);">Left Column</span>
                    </div>

                    <div class="grid-3">
                        <div class="field-group">
                            <label>First Name</label>
                            <input type="text" name="groom_first_name" id="in-groom-first" class="field-input input-text-clean" maxlength="50" value="<?php echo htmlspecialchars($groomFirst); ?>" placeholder="First name" required>
                        </div>
                        <div class="field-group">
                            <label>Middle Name</label>
                            <input type="text" name="groom_middle_name" id="in-groom-middle" class="field-input input-text-clean" maxlength="50" value="<?php echo htmlspecialchars($groomMiddle); ?>" placeholder="Middle name">
                        </div>
                        <div class="field-group">
                            <label>Surname / Last Name</label>
                            <input type="text" name="groom_surname" id="in-groom-last" class="field-input input-text-clean" maxlength="50" value="<?php echo htmlspecialchars($groomSurname); ?>" placeholder="Last name" required>
                        </div>
                    </div>

                    <div class="grid-3">
                        <div class="field-group">
                            <label>Date of Birth</label>
                            <input type="date" name="groom_dob" id="in-groom-dob" class="field-input" min="1900-01-01" max="2099-12-31" value="<?php echo htmlspecialchars($groomDob); ?>" onchange="calcAge('groom')">
                        </div>
                        <div class="field-group">
                            <label>Age <span class="field-hint">18-120</span></label>
                            <input type="number" name="groom_age" id="in-groom-age" class="field-input" min="18" max="120" step="1" inputmode="numeric" value="<?php echo htmlspecialchars($groomAge); ?>" placeholder="Age" oninput="clampAge(this)">
                        </div>
                        <div class="field-group">
                            <label>Place of Birth <span class="field-hint">City / Province</span></label>
                            <input type="text" name="groom_pob" id="in-groom-pob" list="dl-ph-all-cities" class="field-input" maxlength="100" value="<?php echo htmlspecialchars($groomPob); ?>" placeholder="City, Province, Country" autocomplete="off">
                        </div>
                    </div>

                    <div class="grid-3">
                        <div class="field-group">
                            <label>Sex</label>
                            <select name="groom_sex" id="in-groom-sex" class="field-select">
                                <option value="Male" <?php echo $groomSex === 'Male' ? 'selected' : ''; ?>>Male</option>
                                <option value="Female" <?php echo $groomSex === 'Female' ? 'selected' : ''; ?>>Female</option>
                            </select>
                        </div>
                        <div class="field-group">
                            <label>Citizenship <span class="field-hint">Select / Type</span></label>
                            <input type="text" name="groom_citizenship" id="in-groom-citizenship" list="dl-citizenships" class="field-input" maxlength="40" value="<?php echo htmlspecialchars($groomCitizenship); ?>" placeholder="Filipino" autocomplete="off">
                        </div>
                        <div class="field-group">
                            <label>Religion / Sect <span class="field-hint">Select / Type</span></label>
                            <input type="text" name="groom_religion" id="in-groom-religion" list="dl-religions" class="field-input" maxlength="50" value="<?php echo htmlspecialchars($groomReligion); ?>" placeholder="e.g. Philippine Independent Church" autocomplete="off">
                        </div>
                    </div>

                    <div class="grid-2">
                        <div class="field-group">
                            <label>Residence Address</label>
                            <input type="text" name="groom_residence" id="in-groom-residence" class="field-input" maxlength="150" value="<?php echo htmlspecialchars($groomResidence); ?>" placeholder="Complete residence address">
                        </div>
                        <div class="field-group">
                            <label>Contact / Tel No. <span class="field-hint">09XX-XXX-XXXX</span></label>
                            <input type="tel" name="groom_contact_no" id="in-groom-contact" class="field-input tel-mask" maxlength="13" inputmode="tel" value="<?php echo htmlspecialchars($groomContactNo); ?>" placeholder="0912-345-6789" oninput="formatTelMask(this)">
                        </div>
                    </div>

                    <div class="grid-2">
                        <div class="field-group">
                            <label>Civil Status</label>
                            <select name="groom_civil_status" id="in-groom-civil-status" class="field-select">
                                <option value="Single" <?php echo $groomCivilStatus === 'Single' ? 'selected' : ''; ?>>Single</option>
                                <option value="Widowed" <?php echo $groomCivilStatus === 'Widowed' ? 'selected' : ''; ?>>Widowed</option>
                                <option value="Divorced" <?php echo $groomCivilStatus === 'Divorced' ? 'selected' : ''; ?>>Divorced / Annulled</option>
                            </select>
                        </div>
                        <div class="field-group">
                            <label>Degree of Relationship of Parties <span class="field-hint">Select / Type</span></label>
                            <input type="text" name="groom_degree_relationship" id="in-groom-degree-rel" list="dl-degrees-rel" class="field-input" maxlength="50" value="<?php echo htmlspecialchars($groomDegreeRel); ?>" placeholder="e.g. None" autocomplete="off">
                        </div>
                    </div>

                    <!-- Previous marriage dissolution if applicable -->
                    <div style="background: rgba(0,0,0,0.1); padding: 8px 10px; border-radius: 6px; border: 1px dashed var(--border);">
                        <div style="font-size: 11px; font-weight: 700; color: var(--text2); margin-bottom: 6px;">
                            IF PREVIOUSLY MARRIED (How dissolved, Place & Date):
                        </div>
                        <div class="grid-3">
                            <div class="field-group">
                                <label>How Dissolved <span class="field-hint">Select / Type</span></label>
                                <input type="text" name="groom_dissolved_how" id="in-groom-diss-how" list="dl-dissolutions" class="field-input" maxlength="40" value="<?php echo htmlspecialchars($groomDissolvedHow); ?>" placeholder="e.g. Death of Spouse" autocomplete="off">
                            </div>
                            <div class="field-group">
                                <label>Place Where Dissolved <span class="field-hint">City / Province</span></label>
                                <input type="text" name="groom_dissolved_place" id="in-groom-diss-place" list="dl-ph-all-cities" class="field-input" maxlength="80" value="<?php echo htmlspecialchars($groomDissolvedPlace); ?>" placeholder="City, Province" autocomplete="off">
                            </div>
                            <div class="field-group">
                                <label>Date When Dissolved</label>
                                <input type="date" name="groom_dissolved_date" id="in-groom-diss-date" class="field-input" min="1900-01-01" max="2099-12-31" value="<?php echo htmlspecialchars($groomDissolvedDate); ?>">
                            </div>
                        </div>
                    </div>

                    <!-- Groom Parents -->
                    <div style="margin-top: 6px;">
                        <div style="font-size: 11px; font-weight: 700; color: var(--text2); margin-bottom: 6px;">
                            Father & Mother of Groom:
                        </div>
                        <div class="grid-3">
                            <div class="field-group">
                                <label>Father's First Name</label>
                                <input type="text" name="groom_father_first" id="in-groom-fat-first" class="field-input input-text-clean" maxlength="50" value="<?php echo htmlspecialchars($groomFatherFirst); ?>" placeholder="First name">
                            </div>
                            <div class="field-group">
                                <label>Father's M.I.</label>
                                <input type="text" name="groom_father_mi" id="in-groom-fat-mi" class="field-input" maxlength="4" value="<?php echo htmlspecialchars($groomFatherMI); ?>" placeholder="M.I." oninput="this.value = this.value.toUpperCase();">
                            </div>
                            <div class="field-group">
                                <label>Father's Surname</label>
                                <input type="text" name="groom_father_surname" id="in-groom-fat-last" class="field-input input-text-clean" maxlength="50" value="<?php echo htmlspecialchars($groomFatherSurname); ?>" placeholder="Surname">
                            </div>
                        </div>
                        <div class="grid-2" style="margin-top: 4px;">
                            <div class="field-group">
                                <label>Father's Citizenship <span class="field-hint">Select / Type</span></label>
                                <input type="text" name="groom_father_citizenship" id="in-groom-fat-cit" list="dl-citizenships" class="field-input" maxlength="40" value="<?php echo htmlspecialchars($groomFatherCitizenship); ?>" placeholder="Filipino" autocomplete="off">
                            </div>
                            <div class="field-group">
                                <label>Father's Residence</label>
                                <input type="text" name="groom_father_residence" id="in-groom-fat-res" class="field-input" maxlength="120" value="<?php echo htmlspecialchars($groomFatherResidence); ?>" placeholder="Address">
                            </div>
                        </div>

                        <div class="grid-3" style="margin-top: 8px;">
                            <div class="field-group">
                                <label>Mother's Maiden First Name</label>
                                <input type="text" name="groom_mother_first" id="in-groom-mot-first" class="field-input input-text-clean" maxlength="50" value="<?php echo htmlspecialchars($groomMotherFirst); ?>" placeholder="First name">
                            </div>
                            <div class="field-group">
                                <label>Mother's M.I.</label>
                                <input type="text" name="groom_mother_mi" id="in-groom-mot-mi" class="field-input" maxlength="4" value="<?php echo htmlspecialchars($groomMotherMI); ?>" placeholder="M.I." oninput="this.value = this.value.toUpperCase();">
                            </div>
                            <div class="field-group">
                                <label>Mother's Maiden Surname</label>
                                <input type="text" name="groom_mother_surname" id="in-groom-mot-last" class="field-input input-text-clean" maxlength="50" value="<?php echo htmlspecialchars($groomMotherSurname); ?>" placeholder="Maiden surname">
                            </div>
                        </div>
                        <div class="grid-2" style="margin-top: 4px;">
                            <div class="field-group">
                                <label>Mother's Citizenship <span class="field-hint">Select / Type</span></label>
                                <input type="text" name="groom_mother_citizenship" id="in-groom-mot-cit" list="dl-citizenships" class="field-input" maxlength="40" value="<?php echo htmlspecialchars($groomMotherCitizenship); ?>" placeholder="Filipino" autocomplete="off">
                            </div>
                            <div class="field-group">
                                <label>Mother's Residence</label>
                                <input type="text" name="groom_mother_residence" id="in-groom-mot-res" class="field-input" maxlength="120" value="<?php echo htmlspecialchars($groomMotherResidence); ?>" placeholder="Address">
                            </div>
                        </div>
                    </div>

                    <!-- Groom Consent & CTC -->
                    <div style="margin-top: 6px;">
                        <div style="font-size: 11px; font-weight: 700; color: var(--text2); margin-bottom: 6px;">
                            Consent / Advice (For applicants ages 18-25) & CTC:
                        </div>
                        <div class="grid-2">
                            <div class="field-group">
                                <label>Person Giving Consent</label>
                                <input type="text" name="groom_consent_person" id="in-groom-consent-person" class="field-input" maxlength="60" value="<?php echo htmlspecialchars($groomConsentPerson); ?>" placeholder="Parent / Guardian name">
                            </div>
                            <div class="field-group">
                                <label>Relationship <span class="field-hint">Select / Type</span></label>
                                <input type="text" name="groom_consent_relationship" id="in-groom-consent-rel" list="dl-relationships" class="field-input" maxlength="30" value="<?php echo htmlspecialchars($groomConsentRel); ?>" placeholder="e.g. Father" autocomplete="off">
                            </div>
                        </div>
                        <div class="grid-3" style="margin-top: 6px;">
                            <div class="field-group">
                                <label>Community Tax Cert. (CTC) No.</label>
                                <input type="text" name="groom_ctc_no" id="in-groom-ctc-no" class="field-input" maxlength="25" value="<?php echo htmlspecialchars($groomCtcNo); ?>" placeholder="e.g. CCI2026-1029482">
                            </div>
                            <div class="field-group">
                                <label>CTC Date Issued</label>
                                <input type="date" name="groom_ctc_date" id="in-groom-ctc-date" class="field-input" min="1900-01-01" max="2099-12-31" value="<?php echo htmlspecialchars($groomCtcDate); ?>">
                            </div>
                            <div class="field-group">
                                <label>CTC Place Issued <span class="field-hint">City / Province</span></label>
                                <input type="text" name="groom_ctc_place" id="in-groom-ctc-place" list="dl-ph-all-cities" class="field-input" maxlength="60" value="<?php echo htmlspecialchars($groomCtcPlace); ?>" placeholder="e.g. Angono, Rizal" autocomplete="off">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- SECTION 3: Bride Information (Fields 1-21) -->
                <div class="form-section-card">
                    <div class="form-section-title">
                        <span><i class="fa-solid fa-venus" style="color: var(--accent, #c5a059);"></i> Contracting Party: BRIDE</span>
                        <span style="font-size: 10px; color: var(--accent, #c5a059);">Right Column</span>
                    </div>

                    <div class="grid-3">
                        <div class="field-group">
                            <label>First Name</label>
                            <input type="text" name="bride_first_name" id="in-bride-first" class="field-input input-text-clean" maxlength="50" value="<?php echo htmlspecialchars($brideFirst); ?>" placeholder="First name" required>
                        </div>
                        <div class="field-group">
                            <label>Middle Name</label>
                            <input type="text" name="bride_middle_name" id="in-bride-middle" class="field-input input-text-clean" maxlength="50" value="<?php echo htmlspecialchars($brideMiddle); ?>" placeholder="Middle name">
                        </div>
                        <div class="field-group">
                            <label>Surname / Maiden Name</label>
                            <input type="text" name="bride_surname" id="in-bride-last" class="field-input input-text-clean" maxlength="50" value="<?php echo htmlspecialchars($brideSurname); ?>" placeholder="Last name" required>
                        </div>
                    </div>

                    <div class="grid-3">
                        <div class="field-group">
                            <label>Date of Birth</label>
                            <input type="date" name="bride_dob" id="in-bride-dob" class="field-input" min="1900-01-01" max="2099-12-31" value="<?php echo htmlspecialchars($brideDob); ?>" onchange="calcAge('bride')">
                        </div>
                        <div class="field-group">
                            <label>Age <span class="field-hint">18-120</span></label>
                            <input type="number" name="bride_age" id="in-bride-age" class="field-input" min="18" max="120" step="1" inputmode="numeric" value="<?php echo htmlspecialchars($brideAge); ?>" placeholder="Age" oninput="clampAge(this)">
                        </div>
                        <div class="field-group">
                            <label>Place of Birth <span class="field-hint">City / Province</span></label>
                            <input type="text" name="bride_pob" id="in-bride-pob" list="dl-ph-all-cities" class="field-input" maxlength="100" value="<?php echo htmlspecialchars($bridePob); ?>" placeholder="City, Province, Country" autocomplete="off">
                        </div>
                    </div>

                    <div class="grid-3">
                        <div class="field-group">
                            <label>Sex</label>
                            <select name="bride_sex" id="in-bride-sex" class="field-select">
                                <option value="Female" <?php echo $brideSex === 'Female' ? 'selected' : ''; ?>>Female</option>
                                <option value="Male" <?php echo $brideSex === 'Male' ? 'selected' : ''; ?>>Male</option>
                            </select>
                        </div>
                        <div class="field-group">
                            <label>Citizenship <span class="field-hint">Select / Type</span></label>
                            <input type="text" name="bride_citizenship" id="in-bride-citizenship" list="dl-citizenships" class="field-input" maxlength="40" value="<?php echo htmlspecialchars($brideCitizenship); ?>" placeholder="Filipino" autocomplete="off">
                        </div>
                        <div class="field-group">
                            <label>Religion / Sect <span class="field-hint">Select / Type</span></label>
                            <input type="text" name="bride_religion" id="in-bride-religion" list="dl-religions" class="field-input" maxlength="50" value="<?php echo htmlspecialchars($brideReligion); ?>" placeholder="e.g. Philippine Independent Church" autocomplete="off">
                        </div>
                    </div>

                    <div class="grid-2">
                        <div class="field-group">
                            <label>Residence Address</label>
                            <input type="text" name="bride_residence" id="in-bride-residence" class="field-input" maxlength="150" value="<?php echo htmlspecialchars($brideResidence); ?>" placeholder="Complete residence address">
                        </div>
                        <div class="field-group">
                            <label>Contact / Tel No. <span class="field-hint">09XX-XXX-XXXX</span></label>
                            <input type="tel" name="bride_contact_no" id="in-bride-contact" class="field-input tel-mask" maxlength="13" inputmode="tel" value="<?php echo htmlspecialchars($brideContactNo); ?>" placeholder="0912-345-6789" oninput="formatTelMask(this)">
                        </div>
                    </div>

                    <div class="grid-2">
                        <div class="field-group">
                            <label>Civil Status</label>
                            <select name="bride_civil_status" id="in-bride-civil-status" class="field-select">
                                <option value="Single" <?php echo $brideCivilStatus === 'Single' ? 'selected' : ''; ?>>Single</option>
                                <option value="Widowed" <?php echo $brideCivilStatus === 'Widowed' ? 'selected' : ''; ?>>Widowed</option>
                                <option value="Divorced" <?php echo $brideCivilStatus === 'Divorced' ? 'selected' : ''; ?>>Divorced / Annulled</option>
                            </select>
                        </div>
                        <div class="field-group">
                            <label>Degree of Relationship of Parties</label>
                            <input type="text" name="bride_degree_relationship" id="in-bride-degree-rel" class="field-input" maxlength="50" value="<?php echo htmlspecialchars($brideDegreeRel); ?>" placeholder="e.g. None">
                        </div>
                    </div>

                    <!-- Previous marriage dissolution if applicable -->
                    <div style="background: rgba(0,0,0,0.1); padding: 8px 10px; border-radius: 6px; border: 1px dashed var(--border);">
                        <div style="font-size: 11px; font-weight: 700; color: var(--text2); margin-bottom: 6px;">
                            IF PREVIOUSLY MARRIED (How dissolved, Place & Date):
                        </div>
                        <div class="grid-3">
                            <div class="field-group">
                                <label>How Dissolved</label>
                                <input type="text" name="bride_dissolved_how" id="in-bride-diss-how" class="field-input" maxlength="40" value="<?php echo htmlspecialchars($brideDissolvedHow); ?>" placeholder="e.g. Death, Annulment">
                            </div>
                            <div class="field-group">
                                <label>Place Where Dissolved</label>
                                <input type="text" name="bride_dissolved_place" id="in-bride-diss-place" class="field-input" maxlength="80" value="<?php echo htmlspecialchars($brideDissolvedPlace); ?>" placeholder="City, Province">
                            </div>
                            <div class="field-group">
                                <label>Date When Dissolved</label>
                                <input type="date" name="bride_dissolved_date" id="in-bride-diss-date" class="field-input" min="1900-01-01" max="2099-12-31" value="<?php echo htmlspecialchars($brideDissolvedDate); ?>">
                            </div>
                        </div>
                    </div>

                    <!-- Bride Parents -->
                    <div style="margin-top: 6px;">
                        <div style="font-size: 11px; font-weight: 700; color: var(--text2); margin-bottom: 6px;">
                            Father & Mother of Bride:
                        </div>
                        <div class="grid-3">
                            <div class="field-group">
                                <label>Father's First Name</label>
                                <input type="text" name="bride_father_first" id="in-bride-fat-first" class="field-input input-text-clean" maxlength="50" value="<?php echo htmlspecialchars($brideFatherFirst); ?>" placeholder="First name">
                            </div>
                            <div class="field-group">
                                <label>Father's M.I.</label>
                                <input type="text" name="bride_father_mi" id="in-bride-fat-mi" class="field-input" maxlength="4" value="<?php echo htmlspecialchars($brideFatherMI); ?>" placeholder="M.I." oninput="this.value = this.value.toUpperCase();">
                            </div>
                            <div class="field-group">
                                <label>Father's Surname</label>
                                <input type="text" name="bride_father_surname" id="in-bride-fat-last" class="field-input input-text-clean" maxlength="50" value="<?php echo htmlspecialchars($brideFatherSurname); ?>" placeholder="Surname">
                            </div>
                        </div>
                        <div class="grid-2" style="margin-top: 4px;">
                            <div class="field-group">
                                <label>Father's Citizenship <span class="field-hint">Select / Type</span></label>
                                <input type="text" name="bride_father_citizenship" id="in-bride-fat-cit" list="dl-citizenships" class="field-input" maxlength="40" value="<?php echo htmlspecialchars($brideFatherCitizenship); ?>" placeholder="Filipino" autocomplete="off">
                            </div>
                            <div class="field-group">
                                <label>Father's Residence</label>
                                <input type="text" name="bride_father_residence" id="in-bride-fat-res" class="field-input" maxlength="120" value="<?php echo htmlspecialchars($brideFatherResidence); ?>" placeholder="Address">
                            </div>
                        </div>

                        <div class="grid-3" style="margin-top: 8px;">
                            <div class="field-group">
                                <label>Mother's Maiden First Name</label>
                                <input type="text" name="bride_mother_first" id="in-bride-mot-first" class="field-input input-text-clean" maxlength="50" value="<?php echo htmlspecialchars($brideMotherFirst); ?>" placeholder="First name">
                            </div>
                            <div class="field-group">
                                <label>Mother's M.I.</label>
                                <input type="text" name="bride_mother_mi" id="in-bride-mot-mi" class="field-input" maxlength="4" value="<?php echo htmlspecialchars($brideMotherMI); ?>" placeholder="M.I." oninput="this.value = this.value.toUpperCase();">
                            </div>
                            <div class="field-group">
                                <label>Mother's Maiden Surname</label>
                                <input type="text" name="bride_mother_surname" id="in-bride-mot-last" class="field-input input-text-clean" maxlength="50" value="<?php echo htmlspecialchars($brideMotherSurname); ?>" placeholder="Maiden surname">
                            </div>
                        </div>
                        <div class="grid-2" style="margin-top: 4px;">
                            <div class="field-group">
                                <label>Mother's Citizenship <span class="field-hint">Select / Type</span></label>
                                <input type="text" name="bride_mother_citizenship" id="in-bride-mot-cit" list="dl-citizenships" class="field-input" maxlength="40" value="<?php echo htmlspecialchars($brideMotherCitizenship); ?>" placeholder="Filipino" autocomplete="off">
                            </div>
                            <div class="field-group">
                                <label>Mother's Residence</label>
                                <input type="text" name="bride_mother_residence" id="in-bride-mot-res" class="field-input" maxlength="120" value="<?php echo htmlspecialchars($brideMotherResidence); ?>" placeholder="Address">
                            </div>
                        </div>
                    </div>

                    <!-- Bride Consent & CTC -->
                    <div style="margin-top: 6px;">
                        <div style="font-size: 11px; font-weight: 700; color: var(--text2); margin-bottom: 6px;">
                            Consent / Advice (For applicants ages 18-25) & CTC:
                        </div>
                        <div class="grid-2">
                            <div class="field-group">
                                <label>Person Giving Consent</label>
                                <input type="text" name="bride_consent_person" id="in-bride-consent-person" class="field-input" maxlength="60" value="<?php echo htmlspecialchars($brideConsentPerson); ?>" placeholder="Parent / Guardian name">
                            </div>
                            <div class="field-group">
                                <label>Relationship <span class="field-hint">Select / Type</span></label>
                                <input type="text" name="bride_consent_relationship" id="in-bride-consent-rel" list="dl-relationships" class="field-input" maxlength="30" value="<?php echo htmlspecialchars($brideConsentRel); ?>" placeholder="e.g. Mother" autocomplete="off">
                            </div>
                        </div>
                        <div class="grid-3" style="margin-top: 6px;">
                            <div class="field-group">
                                <label>Community Tax Cert. (CTC) No.</label>
                                <input type="text" name="bride_ctc_no" id="in-bride-ctc-no" class="field-input" maxlength="25" value="<?php echo htmlspecialchars($brideCtcNo); ?>" placeholder="e.g. CCI2026-9812734">
                            </div>
                            <div class="field-group">
                                <label>CTC Date Issued</label>
                                <input type="date" name="bride_ctc_date" id="in-bride-ctc-date" class="field-input" min="1900-01-01" max="2099-12-31" value="<?php echo htmlspecialchars($brideCtcDate); ?>">
                            </div>
                            <div class="field-group">
                                <label>CTC Place Issued <span class="field-hint">City / Province</span></label>
                                <input type="text" name="bride_ctc_place" id="in-bride-ctc-place" list="dl-ph-all-cities" class="field-input" maxlength="60" value="<?php echo htmlspecialchars($brideCtcPlace); ?>" placeholder="e.g. Angono, Rizal" autocomplete="off">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- SECTION 4: Canonical Solemnization & Registry Card -->
                <div class="form-section-card">
                    <div class="form-section-title">
                        <span><i class="fa-solid fa-church mr-6" style="color: var(--accent);"></i> Canonical Solemnization & Church Registry</span>
                        <span style="font-size: 10px; color: var(--text2);">Parish Archive Linking</span>
                    </div>

                    <div class="grid-2">
                        <div class="field-group">
                            <label>Date of Solemnization / Marriage</label>
                            <input type="date" name="event_date" id="in-event-date" class="field-input" min="1900-01-01" max="2099-12-31" value="<?php echo htmlspecialchars($eventDate); ?>" required>
                        </div>
                        <div class="field-group">
                            <label>Place of Solemnization</label>
                            <input type="text" name="place_of_marriage" id="in-place-marriage" class="field-input" maxlength="100" value="<?php echo htmlspecialchars($placeOfMarriage); ?>" placeholder="Cathedral or Chapel">
                        </div>
                    </div>

                    <div class="grid-2">
                        <div class="field-group">
                            <label>Principal Witness / Sponsor 1</label>
                            <input type="text" name="witness_1" id="in-witness-1" class="field-input input-text-clean" maxlength="60" value="<?php echo htmlspecialchars($witness1); ?>" placeholder="Principal sponsor 1">
                        </div>
                        <div class="field-group">
                            <label>Principal Witness / Sponsor 2</label>
                            <input type="text" name="witness_2" id="in-witness-2" class="field-input input-text-clean" maxlength="60" value="<?php echo htmlspecialchars($witness2); ?>" placeholder="Principal sponsor 2">
                        </div>
                    </div>

                    <div class="grid-3">
                        <div class="field-group">
                            <label>Officiating Priest / Solemnizer</label>
                            <input type="text" name="officiating_priest" id="in-priest" class="field-input input-text-clean" maxlength="60" value="<?php echo htmlspecialchars($officiatingPriest); ?>" placeholder="Name of Solemnizer">
                        </div>
                        <div class="field-group">
                            <label>Book Number</label>
                            <input type="text" name="book_number" id="in-book-no" class="field-input" maxlength="20" value="<?php echo htmlspecialchars($bookNumber); ?>" placeholder="BK-YYYY">
                        </div>
                        <div class="field-group">
                            <label>Page Number</label>
                            <input type="text" name="page_number" id="in-page-no" class="field-input" maxlength="20" value="<?php echo htmlspecialchars($pageNumber); ?>" placeholder="PG-01">
                        </div>
                    </div>

                    <div class="grid-2">
                        <div class="field-group">
                            <label>Civil Registrar Officer Name</label>
                            <input type="text" name="civil_registrar_name" id="in-registrar-name" class="field-input input-text-clean" maxlength="60" value="<?php echo htmlspecialchars($civilRegistrarName); ?>" placeholder="Name of Civil Registrar">
                        </div>
                        <div class="field-group">
                            <label>Place of Sworn Jurat <span class="field-hint">City / Province</span></label>
                            <input type="text" name="sworn_at" id="in-sworn-at" list="dl-ph-all-cities" class="field-input" maxlength="60" value="<?php echo htmlspecialchars($swornAt); ?>" placeholder="e.g. Angono, Rizal" autocomplete="off">
                        </div>
                    </div>

                    <div class="grid-3">
                        <div class="field-group">
                            <label>Sworn Day <span class="field-hint">1-31</span></label>
                            <input type="number" name="sworn_day" id="in-sworn-day" class="field-input" min="1" max="31" step="1" inputmode="numeric" value="<?php echo htmlspecialchars($swornDay); ?>" placeholder="Day" oninput="clampNumber(this, 1, 31)">
                        </div>
                        <div class="field-group">
                            <label>Sworn Month</label>
                            <input type="text" name="sworn_month" id="in-sworn-month" class="field-input" maxlength="20" value="<?php echo htmlspecialchars($swornMonth); ?>" placeholder="Month">
                        </div>
                        <div class="field-group">
                            <label>Sworn Year <span class="field-hint">2000-2099</span></label>
                            <input type="number" name="sworn_year" id="in-sworn-year" class="field-input" min="2000" max="2099" step="1" inputmode="numeric" value="<?php echo htmlspecialchars($swornYear); ?>" placeholder="Year" oninput="clampNumber(this, 2000, 2099)">
                        </div>
                    </div>

                    <div class="field-group">
                        <label>Scope / Record Jurisdiction</label>
                        <select name="scope" class="field-select">
                            <option value="cathedral" <?php echo $scope === 'cathedral' ? 'selected' : ''; ?>>Cathedral Central Records</option>
                            <option value="chapel" <?php echo $scope === 'chapel' ? 'selected' : ''; ?>>Chapel Local Jurisdiction</option>
                        </select>
                    </div>

                    <div class="field-group">
                        <label>
                            <span>Marginal Remarks / Annotations</span>
                            <span id="notes-char-counter" class="field-hint" style="color:var(--accent);">0 / 500</span>
                        </label>
                        <textarea name="marginal_notes" id="in-marginal-notes" class="field-input" maxlength="500" style="height: 60px; resize: vertical;" placeholder="Notes, canonical dispensation, pre-cana seminars, etc." oninput="updateCharCount(this)"><?php echo htmlspecialchars($marginalNotes); ?></textarea>
                    </div>
                </div>

                <div style="display:flex; justify-content:flex-end; gap:8px; margin-top:12px;">
                    <button type="submit" class="btn-top-save" style="width:100%; justify-content:center; padding:10px 16px;">
                        <i class="fa-solid fa-floppy-disk"></i> <?php echo $recordId > 0 ? 'Update Registry Record' : 'Save Marriage Record to Registry'; ?>
                    </button>
                </div>
            </form>
        </aside>

        <!-- ── RIGHT PANEL: LIVE 1:1 PAPER REPLICA ── -->
        <main class="preview-side-panel">
            <div class="official-paper-sheet" id="print-area">
                
                <!-- Official Header Bar -->
                <div class="paper-header-row">
                    <div class="left-form-code">
                        Municipal Form 90 (Form No. 2)<br>
                        (Revised January 2007)
                    </div>
                    <div class="right-form-instr">
                        (To be accomplished in quadruplicate using black ink)
                    </div>
                </div>

                <div class="paper-header-title">
                    <div class="gov-rep">Republic of the Philippines</div>
                    <div class="gov-agency">OFFICE OF THE CIVIL REGISTRAR GENERAL</div>
                    <div class="main-doc-title">APPLICATION FOR MARRIAGE LICENSE</div>
                </div>

                <!-- Upper Filing & Registry Details Box -->
                <div class="paper-meta-box">
                    <div class="meta-col meta-col-left">
                        <div class="meta-line">
                            <span class="meta-line-lbl">Province:</span>
                            <span class="meta-line-val" id="pv-province"><?php echo htmlspecialchars($province); ?></span>
                        </div>
                        <div class="meta-line">
                            <span class="meta-line-lbl">City/Municipality:</span>
                            <span class="meta-line-val" id="pv-municipality"><?php echo htmlspecialchars($municipality); ?></span>
                        </div>
                        <div class="meta-line">
                            <span class="meta-line-lbl">Received by:</span>
                            <span class="meta-line-val" id="pv-received-by"><?php echo htmlspecialchars($receivedBy); ?></span>
                        </div>
                        <div class="meta-line">
                            <span class="meta-line-lbl">Date of Receipt:</span>
                            <span class="meta-line-val" id="pv-date-receipt"><?php echo htmlspecialchars($dateOfReceipt); ?></span>
                        </div>
                    </div>
                    <div class="meta-col">
                        <div class="meta-line">
                            <span class="meta-line-lbl">Registry No.:</span>
                            <span class="meta-line-val" id="pv-registry-no"><?php echo htmlspecialchars($registryNo); ?></span>
                        </div>
                        <div class="meta-line" style="margin-top: 14px;">
                            <span class="meta-line-lbl">Marriage License No.:</span>
                            <span class="meta-line-val" id="pv-license-no"><?php echo htmlspecialchars($marriageLicenseNo); ?></span>
                        </div>
                        <div class="meta-line">
                            <span class="meta-line-lbl">Date of Issuance of Marriage License:</span>
                            <span class="meta-line-val" id="pv-date-issuance"><?php echo htmlspecialchars($dateOfIssuance); ?></span>
                        </div>
                    </div>
                </div>

                <!-- 3-Column Official Application Table -->
                <table class="form90-table">
                    <thead>
                        <tr>
                            <th class="col-groom header-party-title">GROOM</th>
                            <th class="col-labels"></th>
                            <th class="col-bride header-party-title">BRIDE</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Row 0: Sworn Petition statement -->
                        <tr>
                            <td class="col-groom petition-statement">
                                <strong>The Civil Registrar</strong><br>
                                Sir/Madam:<br>
                                May I apply for a license to contract marriage with <u id="pv-groom-intended" style="font-weight:bold;"><?php echo htmlspecialchars(trim("$brideFirst $brideMiddle $brideSurname")) ?: '________________________'; ?></u> and to this effect, being duly sworn, I hereby depose and say that I have all the necessary qualifications and none of the legal disqualifications to contract the said marriage, and that the following data are true and correct to the best of my knowledge and information:
                            </td>
                            <td class="col-labels" style="background:#fff; vertical-align:middle; font-size:7.5px; text-transform:uppercase;">
                                CONTRACTING PARTIES
                            </td>
                            <td class="col-bride petition-statement">
                                <strong>The Civil Registrar</strong><br>
                                Sir/Madam:<br>
                                May I apply for a license to contract marriage with <u id="pv-bride-intended" style="font-weight:bold;"><?php echo htmlspecialchars(trim("$groomFirst $groomMiddle $groomSurname")) ?: '________________________'; ?></u> and to this effect, being duly sworn, I hereby depose and say that I have all the necessary qualifications and none of the legal disqualifications to contract the said marriage, and that the following data are true and correct to the best of my knowledge and information:
                            </td>
                        </tr>

                        <!-- Row 1: Name of Applicant -->
                        <tr>
                            <td class="col-groom">
                                <span class="val-text" id="pv-groom-name"><?php echo htmlspecialchars(trim("$groomFirst $groomMiddle $groomSurname")); ?></span>
                                <div class="sub-field-hint">
                                    <span>(First)</span>
                                    <span>(Middle)</span>
                                    <span>(Last)</span>
                                </div>
                            </td>
                            <td class="col-labels">1. Name of Applicant</td>
                            <td class="col-bride">
                                <span class="val-text" id="pv-bride-name"><?php echo htmlspecialchars(trim("$brideFirst $brideMiddle $brideSurname")); ?></span>
                                <div class="sub-field-hint">
                                    <span>(First)</span>
                                    <span>(Middle)</span>
                                    <span>(Last)</span>
                                </div>
                            </td>
                        </tr>

                        <!-- Row 2: Date of Birth / Age -->
                        <tr>
                            <td class="col-groom">
                                <div style="display:flex; justify-content:space-between; font-weight:600;">
                                    <span id="pv-groom-dob"><?php echo htmlspecialchars($groomDob); ?></span>
                                    <span>Age: <strong id="pv-groom-age"><?php echo htmlspecialchars($groomAge); ?></strong></span>
                                </div>
                                <div class="sub-field-hint">
                                    <span>(Day)</span>
                                    <span>(Month)</span>
                                    <span>(Year)</span>
                                    <span>(Age)</span>
                                </div>
                            </td>
                            <td class="col-labels">2. Date of Birth/ Age</td>
                            <td class="col-bride">
                                <div style="display:flex; justify-content:space-between; font-weight:600;">
                                    <span id="pv-bride-dob"><?php echo htmlspecialchars($brideDob); ?></span>
                                    <span>Age: <strong id="pv-bride-age"><?php echo htmlspecialchars($brideAge); ?></strong></span>
                                </div>
                                <div class="sub-field-hint">
                                    <span>(Day)</span>
                                    <span>(Month)</span>
                                    <span>(Year)</span>
                                    <span>(Age)</span>
                                </div>
                            </td>
                        </tr>

                        <!-- Row 3: Place of Birth -->
                        <tr>
                            <td class="col-groom">
                                <span class="val-text" id="pv-groom-pob"><?php echo htmlspecialchars($groomPob); ?></span>
                                <div class="sub-field-hint">
                                    <span>(City/Municipality)</span>
                                    <span>(Province)</span>
                                    <span>(Country)</span>
                                </div>
                            </td>
                            <td class="col-labels">3. Place of Birth</td>
                            <td class="col-bride">
                                <span class="val-text" id="pv-bride-pob"><?php echo htmlspecialchars($bridePob); ?></span>
                                <div class="sub-field-hint">
                                    <span>(City/Municipality)</span>
                                    <span>(Province)</span>
                                    <span>(Country)</span>
                                </div>
                            </td>
                        </tr>

                        <!-- Row 4: Sex / Citizenship -->
                        <tr>
                            <td class="col-groom">
                                <div style="display:flex; justify-content:space-between; font-weight:600;">
                                    <span id="pv-groom-sex"><?php echo htmlspecialchars($groomSex); ?></span>
                                    <span id="pv-groom-citizenship"><?php echo htmlspecialchars($groomCitizenship); ?></span>
                                </div>
                                <div class="sub-field-hint">
                                    <span>(Male/Female)</span>
                                    <span>(Citizenship)</span>
                                </div>
                            </td>
                            <td class="col-labels">4. Sex/ Citizenship</td>
                            <td class="col-bride">
                                <div style="display:flex; justify-content:space-between; font-weight:600;">
                                    <span id="pv-bride-sex"><?php echo htmlspecialchars($brideSex); ?></span>
                                    <span id="pv-bride-citizenship"><?php echo htmlspecialchars($brideCitizenship); ?></span>
                                </div>
                                <div class="sub-field-hint">
                                    <span>(Male/Female)</span>
                                    <span>(Citizenship)</span>
                                </div>
                            </td>
                        </tr>

                        <!-- Row 5: Residence -->
                        <tr>
                            <td class="col-groom">
                                <span class="val-text" id="pv-groom-residence"><?php echo htmlspecialchars($groomResidence); ?></span>
                                <div class="sub-field-hint">
                                    <span>(House No., St., Barangay, City/Municipality, Province, Country)</span>
                                </div>
                            </td>
                            <td class="col-labels">5. Residence</td>
                            <td class="col-bride">
                                <span class="val-text" id="pv-bride-residence"><?php echo htmlspecialchars($brideResidence); ?></span>
                                <div class="sub-field-hint">
                                    <span>(House No., St., Barangay, City/Municipality, Province, Country)</span>
                                </div>
                            </td>
                        </tr>

                        <!-- Row 6: Religion / Religious Sect -->
                        <tr>
                            <td class="col-groom">
                                <span class="val-text" id="pv-groom-religion"><?php echo htmlspecialchars($groomReligion); ?></span>
                            </td>
                            <td class="col-labels">6. Religion/ Religious Sect</td>
                            <td class="col-bride">
                                <span class="val-text" id="pv-bride-religion"><?php echo htmlspecialchars($brideReligion); ?></span>
                            </td>
                        </tr>

                        <!-- Row 7: Civil Status -->
                        <tr>
                            <td class="col-groom">
                                <span class="val-text" id="pv-groom-civil-status"><?php echo htmlspecialchars($groomCivilStatus); ?></span>
                            </td>
                            <td class="col-labels">7. Civil Status</td>
                            <td class="col-bride">
                                <span class="val-text" id="pv-bride-civil-status"><?php echo htmlspecialchars($brideCivilStatus); ?></span>
                            </td>
                        </tr>

                        <!-- Row 8: IF PREVIOUSLY MARRIED: How was it dissolved? -->
                        <tr>
                            <td class="col-groom">
                                <span class="val-text" id="pv-groom-diss-how"><?php echo htmlspecialchars($groomDissolvedHow) ?: '—'; ?></span>
                            </td>
                            <td class="col-labels">8. IF PREVIOUSLY MARRIED: How was it dissolved?</td>
                            <td class="col-bride">
                                <span class="val-text" id="pv-bride-diss-how"><?php echo htmlspecialchars($brideDissolvedHow) ?: '—'; ?></span>
                            </td>
                        </tr>

                        <!-- Row 9: Place where dissolved -->
                        <tr>
                            <td class="col-groom">
                                <span class="val-text" id="pv-groom-diss-place"><?php echo htmlspecialchars($groomDissolvedPlace) ?: '—'; ?></span>
                                <div class="sub-field-hint">
                                    <span>(City/Municipality)</span>
                                    <span>(Province)</span>
                                    <span>(Country)</span>
                                </div>
                            </td>
                            <td class="col-labels">9. Place where dissolved</td>
                            <td class="col-bride">
                                <span class="val-text" id="pv-bride-diss-place"><?php echo htmlspecialchars($brideDissolvedPlace) ?: '—'; ?></span>
                                <div class="sub-field-hint">
                                    <span>(City/Municipality)</span>
                                    <span>(Province)</span>
                                    <span>(Country)</span>
                                </div>
                            </td>
                        </tr>

                        <!-- Row 10: Date when dissolved -->
                        <tr>
                            <td class="col-groom">
                                <span class="val-text" id="pv-groom-diss-date"><?php echo htmlspecialchars($groomDissolvedDate) ?: '—'; ?></span>
                                <div class="sub-field-hint">
                                    <span>(Day)</span>
                                    <span>(Month)</span>
                                    <span>(Year)</span>
                                </div>
                            </td>
                            <td class="col-labels">10. Date when dissolved</td>
                            <td class="col-bride">
                                <span class="val-text" id="pv-bride-diss-date"><?php echo htmlspecialchars($brideDissolvedDate) ?: '—'; ?></span>
                                <div class="sub-field-hint">
                                    <span>(Day)</span>
                                    <span>(Month)</span>
                                    <span>(Year)</span>
                                </div>
                            </td>
                        </tr>

                        <!-- Row 11: Degree of relationship -->
                        <tr>
                            <td class="col-groom">
                                <span class="val-text" id="pv-groom-degree-rel"><?php echo htmlspecialchars($groomDegreeRel); ?></span>
                            </td>
                            <td class="col-labels">11. Degree of relationship of contracting parties</td>
                            <td class="col-bride">
                                <span class="val-text" id="pv-bride-degree-rel"><?php echo htmlspecialchars($brideDegreeRel); ?></span>
                            </td>
                        </tr>

                        <!-- Row 12: Name of Father -->
                        <tr>
                            <td class="col-groom">
                                <span class="val-text" id="pv-groom-fat-name"><?php echo htmlspecialchars(trim("$groomFatherFirst $groomFatherMI $groomFatherSurname")); ?></span>
                                <div class="sub-field-hint">
                                    <span>(First)</span>
                                    <span>(Middle)</span>
                                    <span>(Last)</span>
                                </div>
                            </td>
                            <td class="col-labels">12. Name of Father</td>
                            <td class="col-bride">
                                <span class="val-text" id="pv-bride-fat-name"><?php echo htmlspecialchars(trim("$brideFatherFirst $brideFatherMI $brideFatherSurname")); ?></span>
                                <div class="sub-field-hint">
                                    <span>(First)</span>
                                    <span>(Middle)</span>
                                    <span>(Last)</span>
                                </div>
                            </td>
                        </tr>

                        <!-- Row 13: Citizenship (Father) -->
                        <tr>
                            <td class="col-groom">
                                <span class="val-text" id="pv-groom-fat-cit"><?php echo htmlspecialchars($groomFatherCitizenship); ?></span>
                            </td>
                            <td class="col-labels">13. Citizenship</td>
                            <td class="col-bride">
                                <span class="val-text" id="pv-bride-fat-cit"><?php echo htmlspecialchars($brideFatherCitizenship); ?></span>
                            </td>
                        </tr>

                        <!-- Row 14: Residence (Father) -->
                        <tr>
                            <td class="col-groom">
                                <span class="val-text" id="pv-groom-fat-res"><?php echo htmlspecialchars($groomFatherResidence); ?></span>
                                <div class="sub-field-hint">
                                    <span>(House No., St., Barangay, City/Municipality, Province, Country)</span>
                                </div>
                            </td>
                            <td class="col-labels">14. Residence</td>
                            <td class="col-bride">
                                <span class="val-text" id="pv-bride-fat-res"><?php echo htmlspecialchars($brideFatherResidence); ?></span>
                                <div class="sub-field-hint">
                                    <span>(House No., St., Barangay, City/Municipality, Province, Country)</span>
                                </div>
                            </td>
                        </tr>

                        <!-- Row 15: Maiden Name of Mother -->
                        <tr>
                            <td class="col-groom">
                                <span class="val-text" id="pv-groom-mot-name"><?php echo htmlspecialchars(trim("$groomMotherFirst $groomMotherMI $groomMotherSurname")); ?></span>
                                <div class="sub-field-hint">
                                    <span>(First)</span>
                                    <span>(Middle)</span>
                                    <span>(Last)</span>
                                </div>
                            </td>
                            <td class="col-labels">15. Maiden Name of Mother</td>
                            <td class="col-bride">
                                <span class="val-text" id="pv-bride-mot-name"><?php echo htmlspecialchars(trim("$brideMotherFirst $brideMotherMI $brideMotherSurname")); ?></span>
                                <div class="sub-field-hint">
                                    <span>(First)</span>
                                    <span>(Middle)</span>
                                    <span>(Last)</span>
                                </div>
                            </td>
                        </tr>

                        <!-- Row 16: Citizenship (Mother) -->
                        <tr>
                            <td class="col-groom">
                                <span class="val-text" id="pv-groom-mot-cit"><?php echo htmlspecialchars($groomMotherCitizenship); ?></span>
                            </td>
                            <td class="col-labels">16. Citizenship</td>
                            <td class="col-bride">
                                <span class="val-text" id="pv-bride-mot-cit"><?php echo htmlspecialchars($brideMotherCitizenship); ?></span>
                            </td>
                        </tr>

                        <!-- Row 17: Residence (Mother) -->
                        <tr>
                            <td class="col-groom">
                                <span class="val-text" id="pv-groom-mot-res"><?php echo htmlspecialchars($groomMotherResidence); ?></span>
                                <div class="sub-field-hint">
                                    <span>(House No., St., Barangay, City/Municipality, Province, Country)</span>
                                </div>
                            </td>
                            <td class="col-labels">17. Residence</td>
                            <td class="col-bride">
                                <span class="val-text" id="pv-bride-mot-res"><?php echo htmlspecialchars($brideMotherResidence); ?></span>
                                <div class="sub-field-hint">
                                    <span>(House No., St., Barangay, City/Municipality, Province, Country)</span>
                                </div>
                            </td>
                        </tr>

                        <!-- Row 18: Persons who gave consent or advice -->
                        <tr>
                            <td class="col-groom">
                                <span class="val-text" id="pv-groom-consent-person"><?php echo htmlspecialchars($groomConsentPerson) ?: '—'; ?></span>
                            </td>
                            <td class="col-labels">18. Persons who gave consent or advice</td>
                            <td class="col-bride">
                                <span class="val-text" id="pv-bride-consent-person"><?php echo htmlspecialchars($brideConsentPerson) ?: '—'; ?></span>
                            </td>
                        </tr>

                        <!-- Row 19: Relationship -->
                        <tr>
                            <td class="col-groom">
                                <span class="val-text" id="pv-groom-consent-rel"><?php echo htmlspecialchars($groomConsentRel) ?: '—'; ?></span>
                            </td>
                            <td class="col-labels">19. Relationship</td>
                            <td class="col-bride">
                                <span class="val-text" id="pv-bride-consent-rel"><?php echo htmlspecialchars($brideConsentRel) ?: '—'; ?></span>
                            </td>
                        </tr>

                        <!-- Row 20: Citizenship (Consent Person) -->
                        <tr>
                            <td class="col-groom">
                                <span class="val-text" id="pv-groom-consent-cit"><?php echo htmlspecialchars($groomConsentCitizenship) ?: '—'; ?></span>
                            </td>
                            <td class="col-labels">20. Citizenship</td>
                            <td class="col-bride">
                                <span class="val-text" id="pv-bride-consent-cit"><?php echo htmlspecialchars($brideConsentCitizenship) ?: '—'; ?></span>
                            </td>
                        </tr>

                        <!-- Row 21: Residence (Consent Person) -->
                        <tr>
                            <td class="col-groom">
                                <span class="val-text" id="pv-groom-consent-res"><?php echo htmlspecialchars($groomConsentResidence) ?: '—'; ?></span>
                                <div class="sub-field-hint">
                                    <span>(House No., St., Barangay, City/Municipality, Province, Country)</span>
                                </div>
                            </td>
                            <td class="col-labels">21. Residence</td>
                            <td class="col-bride">
                                <span class="val-text" id="pv-bride-consent-res"><?php echo htmlspecialchars($brideConsentResidence) ?: '—'; ?></span>
                                <div class="sub-field-hint">
                                    <span>(House No., St., Barangay, City/Municipality, Province, Country)</span>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <!-- Bottom Jurats & Signatures matching Municipal Form 90 -->
                <div class="bottom-jurats-box">
                    <!-- Groom Jurat -->
                    <div class="jurat-col">
                        <div class="sig-applicant-line">(Signature of Applicant)</div>
                        <div class="jurat-sworn-text">
                            <strong>SUBSCRIBED AND SWORN</strong> to before me this <u id="pv-sworn-day-g"><?php echo htmlspecialchars($swornDay); ?></u> day of <u id="pv-sworn-month-g"><?php echo htmlspecialchars($swornMonth); ?></u>, <u id="pv-sworn-year-g"><?php echo htmlspecialchars($swornYear); ?></u> at <u id="pv-sworn-at-g"><?php echo htmlspecialchars($swornAt); ?></u>, Philippines, affiant who exhibited to me his Community Tax Cert. No. <u id="pv-groom-ctc-no-box"><?php echo htmlspecialchars($groomCtcNo) ?: '_____________'; ?></u> issued on <u id="pv-groom-ctc-date-box"><?php echo htmlspecialchars($groomCtcDate) ?: '_____________'; ?></u> at <u id="pv-groom-ctc-place-box"><?php echo htmlspecialchars($groomCtcPlace) ?: '_____________'; ?></u>.
                        </div>
                        <div class="sig-registrar-line">
                            <span id="pv-registrar-name-g" style="text-transform:uppercase; font-size:9px;"><?php echo htmlspecialchars($civilRegistrarName) ?: '________________________________'; ?></span><br>
                            (Signature Over Printed Name of the Civil Registrar)
                        </div>
                    </div>

                    <!-- Center Tax Notice -->
                    <div class="jurat-col-center">
                        Exempt from documentary stamp tax
                    </div>

                    <!-- Bride Jurat -->
                    <div class="jurat-col">
                        <div class="sig-applicant-line">(Signature of Applicant)</div>
                        <div class="jurat-sworn-text">
                            <strong>SUBSCRIBED AND SWORN</strong> to before me this <u id="pv-sworn-day-b"><?php echo htmlspecialchars($swornDay); ?></u> day of <u id="pv-sworn-month-b"><?php echo htmlspecialchars($swornMonth); ?></u>, <u id="pv-sworn-year-b"><?php echo htmlspecialchars($swornYear); ?></u> at <u id="pv-sworn-at-b"><?php echo htmlspecialchars($swornAt); ?></u>, Philippines, affiant who exhibited to me her Community Tax Cert. No. <u id="pv-bride-ctc-no-box"><?php echo htmlspecialchars($brideCtcNo) ?: '_____________'; ?></u> issued on <u id="pv-bride-ctc-date-box"><?php echo htmlspecialchars($brideCtcDate) ?: '_____________'; ?></u> at <u id="pv-bride-ctc-place-box"><?php echo htmlspecialchars($brideCtcPlace) ?: '_____________'; ?></u>.
                        </div>
                        <div class="sig-registrar-line">
                            <span id="pv-registrar-name-b" style="text-transform:uppercase; font-size:9px;"><?php echo htmlspecialchars($civilRegistrarName) ?: '________________________________'; ?></span><br>
                            (Signature Over Printed Name of the Civil Registrar)
                        </div>
                    </div>
                </div>

            </div>
        </main>
    </div>

    <!-- ── REAL-TIME SYNCHRONIZATION & ROBUST INPUT MASKS / LIMITERS ── -->
    <script>
        function togglePreviewPane() {
            const container = document.getElementById('mar-workspace-split');
            if (container) {
                container.classList.toggle('preview-hidden');
            }
        }

        // Auto-formatter for Philippine telephone & mobile numbers (09XX-XXX-XXXX)
        function formatTelMask(input) {
            let digits = input.value.replace(/\D/g, '');
            if (digits.length > 11) {
                digits = digits.substring(0, 11);
            }
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

        // Clamp number inputs (such as day 1-31, year 2000-2099)
        function clampNumber(input, min, max) {
            let val = parseInt(input.value, 10);
            if (isNaN(val)) return;
            if (val > max) input.value = max;
            if (val < min && input.value.length >= String(min).length) input.value = min;
        }

        // Clamp age between 18 and 120
        function clampAge(input) {
            let val = parseInt(input.value, 10);
            if (isNaN(val)) return;
            if (val > 120) input.value = 120;
            if (val < 0) input.value = 18;
            syncInputs();
        }

        // Calculate age automatically from DOB
        function calcAge(party) {
            const dobInput = document.getElementById('in-' + party + '-dob');
            const ageInput = document.getElementById('in-' + party + '-age');
            if (!dobInput || !dobInput.value) return;

            const birthDate = new Date(dobInput.value);
            const today = new Date();
            let age = today.getFullYear() - birthDate.getFullYear();
            const m = today.getMonth() - birthDate.getMonth();
            if (m < 0 || (m === 0 && today.getDate() < birthDate.getDate())) {
                age--;
            }
            if (age >= 0 && ageInput) {
                if (age > 120) age = 120;
                ageInput.value = age;
                syncInputs();
            }
        }

        // Update character counter for remarks
        function updateCharCount(textarea) {
            const counter = document.getElementById('notes-char-counter');
            if (counter) {
                const len = textarea.value.length;
                counter.textContent = len + ' / 500';
                if (len >= 490) {
                    counter.style.color = '#ef4444';
                } else {
                    counter.style.color = 'var(--accent)';
                }
            }
        }

        // Sanitize names to prevent injection and bad characters
        document.querySelectorAll('.input-text-clean').forEach(elem => {
            elem.addEventListener('input', function() {
                // allow letters, accents, spaces, periods, dashes, apostrophes
                this.value = this.value.replace(/[^a-zA-ZñÑ\s\.\-']/g, '');
            });
        });

        // Addresses & Places: letters, digits, spaces, commas, periods, hyphens, #, /, apostrophes
        document.querySelectorAll('.input-place-clean, .input-address-clean').forEach(elem => {
            elem.addEventListener('input', function() {
                this.value = this.value.replace(/[^a-zA-Z0-9ñÑ\s\.,\-\'#\/\(\)]/g, '');
            });
        });

        // Registry & License Codes: alphanumeric, hyphens, slashes, periods
        document.querySelectorAll('.input-code-clean').forEach(elem => {
            elem.addEventListener('input', function() {
                this.value = this.value.replace(/[^a-zA-Z0-9\s\-\.\/]/g, '').toUpperCase();
            });
        });

        function syncInputs() {
            // Meta
            document.getElementById('pv-province').textContent = document.getElementById('in-province').value || '';
            document.getElementById('pv-municipality').textContent = document.getElementById('in-municipality').value || '';
            document.getElementById('pv-registry-no').textContent = document.getElementById('in-registry-no').value || '';
            document.getElementById('pv-received-by').textContent = document.getElementById('in-received-by').value || '';
            document.getElementById('pv-date-receipt').textContent = document.getElementById('in-date-receipt').value || '';
            document.getElementById('pv-license-no').textContent = document.getElementById('in-license-no').value || '';
            document.getElementById('pv-date-issuance').textContent = document.getElementById('in-date-issuance').value || '';

            // Groom Name
            const gFirst = document.getElementById('in-groom-first').value.trim();
            const gMid = document.getElementById('in-groom-middle').value.trim();
            const gLast = document.getElementById('in-groom-last').value.trim();
            const gFull = [gFirst, gMid, gLast].filter(Boolean).join(' ');
            document.getElementById('pv-groom-name').textContent = gFull;
            document.getElementById('pv-bride-intended').textContent = gFull || '________________________';

            // Bride Name
            const bFirst = document.getElementById('in-bride-first').value.trim();
            const bMid = document.getElementById('in-bride-middle').value.trim();
            const bLast = document.getElementById('in-bride-last').value.trim();
            const bFull = [bFirst, bMid, bLast].filter(Boolean).join(' ');
            document.getElementById('pv-bride-name').textContent = bFull;
            document.getElementById('pv-groom-intended').textContent = bFull || '________________________';

            // Groom fields
            document.getElementById('pv-groom-dob').textContent = document.getElementById('in-groom-dob').value || '';
            document.getElementById('pv-groom-age').textContent = document.getElementById('in-groom-age').value || '';
            document.getElementById('pv-groom-pob').textContent = document.getElementById('in-groom-pob').value || '';
            document.getElementById('pv-groom-sex').textContent = document.getElementById('in-groom-sex').value || 'Male';
            document.getElementById('pv-groom-citizenship').textContent = document.getElementById('in-groom-citizenship').value || '';
            document.getElementById('pv-groom-residence').textContent = document.getElementById('in-groom-residence').value || '';
            document.getElementById('pv-groom-religion').textContent = document.getElementById('in-groom-religion').value || '';
            document.getElementById('pv-groom-civil-status').textContent = document.getElementById('in-groom-civil-status').value || '';
            document.getElementById('pv-groom-degree-rel').textContent = document.getElementById('in-groom-degree-rel').value || '';

            document.getElementById('pv-groom-diss-how').textContent = document.getElementById('in-groom-diss-how').value || '—';
            document.getElementById('pv-groom-diss-place').textContent = document.getElementById('in-groom-diss-place').value || '—';
            document.getElementById('pv-groom-diss-date').textContent = document.getElementById('in-groom-diss-date').value || '—';

            // Groom Father & Mother
            const gfFirst = document.getElementById('in-groom-fat-first').value.trim();
            const gfMI = document.getElementById('in-groom-fat-mi').value.trim();
            const gfLast = document.getElementById('in-groom-fat-last').value.trim();
            document.getElementById('pv-groom-fat-name').textContent = [gfFirst, gfMI, gfLast].filter(Boolean).join(' ');
            document.getElementById('pv-groom-fat-cit').textContent = document.getElementById('in-groom-fat-cit').value || '';
            document.getElementById('pv-groom-fat-res').textContent = document.getElementById('in-groom-fat-res').value || '';

            const gmFirst = document.getElementById('in-groom-mot-first').value.trim();
            const gmMI = document.getElementById('in-groom-mot-mi').value.trim();
            const gmLast = document.getElementById('in-groom-mot-last').value.trim();
            document.getElementById('pv-groom-mot-name').textContent = [gmFirst, gmMI, gmLast].filter(Boolean).join(' ');
            document.getElementById('pv-groom-mot-cit').textContent = document.getElementById('in-groom-mot-cit').value || '';
            document.getElementById('pv-groom-mot-res').textContent = document.getElementById('in-groom-mot-res').value || '';

            // Groom Consent & CTC
            document.getElementById('pv-groom-consent-person').textContent = document.getElementById('in-groom-consent-person').value || '—';
            document.getElementById('pv-groom-consent-rel').textContent = document.getElementById('in-groom-consent-rel').value || '—';
            const gCtc = document.getElementById('in-groom-ctc-no').value.trim();
            const gCtcDate = document.getElementById('in-groom-ctc-date').value.trim();
            const gCtcPlace = document.getElementById('in-groom-ctc-place').value.trim();
            document.getElementById('pv-groom-ctc-no-box').textContent = gCtc || '_____________';
            document.getElementById('pv-groom-ctc-date-box').textContent = gCtcDate || '_____________';
            document.getElementById('pv-groom-ctc-place-box').textContent = gCtcPlace || '_____________';

            // Bride fields
            document.getElementById('pv-bride-dob').textContent = document.getElementById('in-bride-dob').value || '';
            document.getElementById('pv-bride-age').textContent = document.getElementById('in-bride-age').value || '';
            document.getElementById('pv-bride-pob').textContent = document.getElementById('in-bride-pob').value || '';
            document.getElementById('pv-bride-sex').textContent = document.getElementById('in-bride-sex').value || 'Female';
            document.getElementById('pv-bride-citizenship').textContent = document.getElementById('in-bride-citizenship').value || '';
            document.getElementById('pv-bride-residence').textContent = document.getElementById('in-bride-residence').value || '';
            document.getElementById('pv-bride-religion').textContent = document.getElementById('in-bride-religion').value || '';
            document.getElementById('pv-bride-civil-status').textContent = document.getElementById('in-bride-civil-status').value || '';
            document.getElementById('pv-bride-degree-rel').textContent = document.getElementById('in-bride-degree-rel').value || '';

            document.getElementById('pv-bride-diss-how').textContent = document.getElementById('in-bride-diss-how').value || '—';
            document.getElementById('pv-bride-diss-place').textContent = document.getElementById('in-bride-diss-place').value || '—';
            document.getElementById('pv-bride-diss-date').textContent = document.getElementById('in-bride-diss-date').value || '—';

            // Bride Father & Mother
            const bfFirst = document.getElementById('in-bride-fat-first').value.trim();
            const bfMI = document.getElementById('in-bride-fat-mi').value.trim();
            const bfLast = document.getElementById('in-bride-fat-last').value.trim();
            document.getElementById('pv-bride-fat-name').textContent = [bfFirst, bfMI, bfLast].filter(Boolean).join(' ');
            document.getElementById('pv-bride-fat-cit').textContent = document.getElementById('in-bride-fat-cit').value || '';
            document.getElementById('pv-bride-fat-res').textContent = document.getElementById('in-bride-fat-res').value || '';

            const bmFirst = document.getElementById('in-bride-mot-first').value.trim();
            const bmMI = document.getElementById('in-bride-mot-mi').value.trim();
            const bmLast = document.getElementById('in-bride-mot-last').value.trim();
            document.getElementById('pv-bride-mot-name').textContent = [bmFirst, bmMI, bmLast].filter(Boolean).join(' ');
            document.getElementById('pv-bride-mot-cit').textContent = document.getElementById('in-bride-mot-cit').value || '';
            document.getElementById('pv-bride-mot-res').textContent = document.getElementById('in-bride-mot-res').value || '';

            // Bride Consent & CTC
            document.getElementById('pv-bride-consent-person').textContent = document.getElementById('in-bride-consent-person').value || '—';
            document.getElementById('pv-bride-consent-rel').textContent = document.getElementById('in-bride-consent-rel').value || '—';
            const bCtc = document.getElementById('in-bride-ctc-no').value.trim();
            const bCtcDate = document.getElementById('in-bride-ctc-date').value.trim();
            const bCtcPlace = document.getElementById('in-bride-ctc-place').value.trim();
            document.getElementById('pv-bride-ctc-no-box').textContent = bCtc || '_____________';
            document.getElementById('pv-bride-ctc-date-box').textContent = bCtcDate || '_____________';
            document.getElementById('pv-bride-ctc-place-box').textContent = bCtcPlace || '_____________';

            // Jurats
            const sDay = document.getElementById('in-sworn-day').value.trim() || '___';
            const sMonth = document.getElementById('in-sworn-month').value.trim() || '_______';
            const sYear = document.getElementById('in-sworn-year').value.trim() || '20___';
            const sAt = document.getElementById('in-sworn-at').value.trim() || 'Angono, Rizal';
            const regName = document.getElementById('in-registrar-name').value.trim() || '________________________________';

            document.getElementById('pv-sworn-day-g').textContent = sDay;
            document.getElementById('pv-sworn-month-g').textContent = sMonth;
            document.getElementById('pv-sworn-year-g').textContent = sYear;
            document.getElementById('pv-sworn-at-g').textContent = sAt;
            document.getElementById('pv-registrar-name-g').textContent = regName;

            document.getElementById('pv-sworn-day-b').textContent = sDay;
            document.getElementById('pv-sworn-month-b').textContent = sMonth;
            document.getElementById('pv-sworn-year-b').textContent = sYear;
            document.getElementById('pv-sworn-at-b').textContent = sAt;
            document.getElementById('pv-registrar-name-b').textContent = regName;
        }

        // Listen for all input changes
        document.querySelectorAll('#marriage-digital-form input, #marriage-digital-form select, #marriage-digital-form textarea').forEach(elem => {
            elem.addEventListener('input', syncInputs);
            elem.addEventListener('change', syncInputs);
        });

        // Initial sync on page load
        document.addEventListener('DOMContentLoaded', () => {
            syncInputs();
            const notes = document.getElementById('in-marginal-notes');
            if (notes) updateCharCount(notes);
            const gTel = document.getElementById('in-groom-contact');
            if (gTel && gTel.value) formatTelMask(gTel);
            const bTel = document.getElementById('in-bride-contact');
            if (bTel && bTel.value) formatTelMask(bTel);
        });
    </script>
</body>
</html>
