<?php
/**
 * sacramental_certificate.php
 *
 * Official Canonical Sacramental Certificates & Archival Forms
 * Cathedral of St. Michael the Archangel · Iglesia Filipina Independiente · Digman, Bacoor City, Cavite
 *
 * Renders 1:1 authentic physical paper sheets matching the system's official forms:
 *   Confirmation · Baptism · Wedding · Funeral · Communion
 *
 * Usage:
 *   sacramental_certificate.php?record_id=104&preview=1
 *   sacramental_certificate.php?record_id=102&preview=1
 *   sacramental_certificate.php?record_id=5&issuance_id=12
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) { 
    header('Location: ../index.php?error=session'); 
    exit(); 
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/models/SacramentalRecord.php';

$recordId    = (int)($_GET['record_id'] ?? ($_GET['id'] ?? 0));
$issuanceId  = (int)($_GET['issuance_id']  ?? 0);
$isPreview   = isset($_GET['preview']);

if (!$recordId) { 
    header('Location: ../dashboard.php'); 
    exit(); 
}

$model  = new SacramentalRecord($conn);
$record = $model->getById($recordId);
if (!$record) { 
    header('Location: ../dashboard.php'); 
    exit(); 
}

$role = (string)($_SESSION['role'] ?? '');
$sessionChapelId = $_SESSION['chapel_id'] ?? null;
$recordChapelId = $record['chapel_id'] ?? null;
$isAdmin = in_array($role, ['Admin', 'Cathedral Admin'], true);
$canRecordRole = in_array($role, ['Admin', 'Cathedral Admin', 'Chapel Admin', 'Staff'], true);

$hasChapelAccess = false;
if ($isAdmin) {
    $hasChapelAccess = true;
} elseif ($role === 'Chapel Admin') {
    if ($recordChapelId !== null && (int)$recordChapelId > 0 && (int)$sessionChapelId === (int)$recordChapelId) {
        $hasChapelAccess = true;
    }
} elseif ($role === 'Staff') {
    $userChapel = (int)($sessionChapelId ?? 0);
    $recordChapel = (int)($recordChapelId ?? 0);
    $hasChapelAccess = ($userChapel === $recordChapel);
}

if (!$canRecordRole || !empty($record['is_archived']) || !$hasChapelAccess) {
    header('Location: ../dashboard.php?error=unauthorized');
    exit();
}

// Fetch issuance data if available
$issuance = null;
if ($issuanceId) {
    $stmt = $conn->prepare("SELECT * FROM certificate_issuances WHERE issuance_id=? AND record_id=?");
    $stmt->bind_param('ii', $issuanceId, $recordId);
    $stmt->execute();
    $issuance = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$issuance) {
        header('Location: ../dashboard.php?error=unauthorized');
        exit();
    }
}

// Metadata decoding
$metadata = [];
if (!empty($record['notes']) && str_starts_with(trim($record['notes']), '{')) {
    $decoded = json_decode($record['notes'], true);
    if (is_array($decoded)) {
        $metadata = $decoded;
    }
}

$type     = $record['record_type'];
$scope    = $record['scope'] ?? 'cathedral';
$chapel   = !empty($record['chapel_name']) && $record['chapel_name'] !== 'Cathedral' ? $record['chapel_name'] : 'Cathedral of St. Michael the Archangel';
$certDate = date('F d, Y');
$certNo   = $issuance['certificate_no'] ?? ($record['reference_id'] ?: ('CERT-' . date('Y') . '-' . str_pad((string)$recordId, 5, '0', STR_PAD_LEFT)));

$logoPath = __DIR__ . '/../assets/img/2323.png';
$logoSrc  = file_exists($logoPath) ? 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath)) : '../assets/img/2323.png';

$churchName = 'Cathedral of St. Michael the Archangel';
$denomination = 'Iglesia Filipina Independiente';
$churchLocation = 'Digman, Bacoor City, Cavite';

// Query active priest / rector
$rectorName = 'Rev. Fr. Jayson Alcantara';
try {
    $rStmt = $conn->query("SELECT full_name FROM users WHERE role IN ('Priest', 'Pastor') AND (status = 'active' OR status IS NULL) ORDER BY id ASC LIMIT 1");
    if ($rStmt && $rRow = $rStmt->fetch_assoc()) {
        if (!empty($rRow['full_name'])) {
            $rectorName = $rRow['full_name'];
        }
    }
} catch (Throwable $e) {}

$officiatingPriest = !empty($record['officiating_priest']) ? $record['officiating_priest'] : $rectorName;
if (!preg_match('/^(Fr\.|Rev\.|Father|Msgr\.|Most Rev\.)/i', $officiatingPriest)) {
    $officiatingPriest = 'Rev. Fr. ' . $officiatingPriest;
}
if (!preg_match('/^(Fr\.|Rev\.|Father|Msgr\.|Most Rev\.)/i', $rectorName)) {
    $rectorName = 'Rev. Fr. ' . $rectorName;
}

$custodianName = !empty($_SESSION['full_name']) ? $_SESSION['full_name'] : ($_SESSION['user'] ?? 'Parish Secretary');

// Helpers
if (!function_exists('formatDate')) {
    function formatDate(?string $d = null): string {
        return ($d && $d !== '0000-00-00') ? date('F d, Y', strtotime($d)) : '—';
    }
}
if (!function_exists('esc')) {
    function esc(mixed $v): string {
        return htmlspecialchars((string)($v ?? '—'));
    }
}

// ── SACRAMENT DATA DECOMPOSITION ───────────────────────────────────

// 1. Child / Candidate Decomposition (Confirmation & Baptism)
$childFirst   = $metadata['child_first_name'] ?? '';
$childMI      = $metadata['child_middle_name'] ?? '';
$childSurname = $metadata['child_surname'] ?? '';
$candidateName = !empty($record['child_name']) ? $record['child_name'] : (!empty($record['person_name']) ? $record['person_name'] : '');

if (!$childFirst && $candidateName) {
    $parts = explode(' ', trim($candidateName));
    if (count($parts) >= 3) {
        $childSurname = array_pop($parts);
        $childMI      = array_pop($parts);
        $childFirst   = implode(' ', $parts);
    } elseif (count($parts) === 2) {
        $childSurname = $parts[1];
        $childFirst   = $parts[0];
    } else {
        $childFirst   = $candidateName;
    }
}
$candidateFullName = preg_replace('/\s+/', ' ', trim("$childFirst $childMI $childSurname")) ?: ($candidateName ?: '—');

// Event Date & Time
$eventDate = $record['event_date'] ?? date('Y-m-d');
$confTime  = $metadata['confirmation_time'] ?? '';

// Baptism particulars for Confirmation
$dateBaptism   = $metadata['date_of_baptism'] ?? '';
$placeOfBaptism = $metadata['place_of_baptism'] ?? ($record['confirmation_place_of_baptism'] ?? '');
if (empty($dateBaptism) && !empty($placeOfBaptism) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $placeOfBaptism)) {
    $dateBaptism = $placeOfBaptism;
    $placeOfBaptism = '';
}

$dob         = $record['date_of_birth'] ?? ($metadata['dob'] ?? '');
$pob         = $record['place_of_birth'] ?? ($metadata['pob'] ?? '');
$address     = $metadata['address'] ?? ($record['baptism_parents_residence'] ?? '');
$gender      = $metadata['gender'] ?? 'Male';
$nationality = $metadata['nationality'] ?? 'Filipino';
$contactNo   = $metadata['contact_no'] ?? '';

// Parents
$fatherFirst   = $metadata['father_first_name'] ?? '';
$fatherMI      = $metadata['father_mi'] ?? '';
$fatherSurname = $metadata['father_surname'] ?? '';
if (!$fatherFirst && !empty($record['father_name'])) {
    $fparts = explode(' ', trim($record['father_name']));
    if (count($fparts) >= 2) {
        $fatherSurname = array_pop($fparts);
        $fatherFirst   = implode(' ', $fparts);
    } else {
        $fatherFirst   = $record['father_name'];
    }
}
$fatherFullName   = preg_replace('/\s+/', ' ', trim("$fatherFirst $fatherMI $fatherSurname")) ?: ($record['father_name'] ?? '—');
$fatherBirthPlace = $metadata['father_birth_place'] ?? '';

$motherFirst   = $metadata['mother_first_name'] ?? '';
$motherMI      = $metadata['mother_mi'] ?? '';
$motherSurname = $metadata['mother_surname'] ?? '';
if (!$motherFirst && !empty($record['mother_name'])) {
    $mparts = explode(' ', trim($record['mother_name']));
    if (count($mparts) >= 2) {
        $motherSurname = array_pop($mparts);
        $motherFirst   = implode(' ', $mparts);
    } else {
        $motherFirst   = $record['mother_name'];
    }
}
$motherFullName   = preg_replace('/\s+/', ' ', trim("$motherFirst $motherMI $motherSurname")) ?: ($record['mother_name'] ?? '—');
$motherBirthPlace = $metadata['mother_birth_place'] ?? '';

$filiation = $metadata['filiation'] ?? ($record['baptism_legitimacy'] ?? 'Kasal');

// Grandparents (Baptism)
$paternalLolo = $metadata['paternal_lolo'] ?? '';
$paternalLola = $metadata['paternal_lola'] ?? '';
$maternalLolo = $metadata['maternal_lolo'] ?? '';
$maternalLola = $metadata['maternal_lola'] ?? '';

// Sponsors
$sponsors = $metadata['sponsors'] ?? [];
if (empty($sponsors) || !is_array($sponsors)) {
    $sponsors = [];
    if (!empty($record['godfather'])) $sponsors[] = $record['godfather'];
    if (!empty($record['godmother'])) $sponsors[] = $record['godmother'];
}
while (count($sponsors) < 6) {
    $sponsors[] = '';
}

// Registry Books
$bookNumber = $record['book_number'] ?: 'BK-' . date('Y');
$pageNumber = $record['page_number'] ?: 'PG-01';

// 2. Wedding Parties Decomposition
$groomFirst = $metadata['groom_first_name'] ?? '';
$groomMiddle = $metadata['groom_middle_name'] ?? '';
$groomSurname = $metadata['groom_surname'] ?? '';
if (!$groomFirst && !empty($record['groom_name'])) {
    $gparts = explode(' ', trim($record['groom_name']));
    if (count($gparts) >= 3) {
        $groomSurname = array_pop($gparts);
        $groomMiddle = array_pop($gparts);
        $groomFirst = implode(' ', $gparts);
    } elseif (count($gparts) === 2) {
        $groomSurname = $gparts[1];
        $groomFirst = $gparts[0];
    } else {
        $groomFirst = $record['groom_name'];
    }
}
$groomFullName = preg_replace('/\s+/', ' ', trim("$groomFirst $groomMiddle $groomSurname")) ?: ($record['groom_name'] ?: '—');

$brideFirst = $metadata['bride_first_name'] ?? '';
$brideMiddle = $metadata['bride_middle_name'] ?? '';
$brideSurname = $metadata['bride_surname'] ?? '';
if (!$brideFirst && !empty($record['bride_name'])) {
    $bparts = explode(' ', trim($record['bride_name']));
    if (count($bparts) >= 3) {
        $brideSurname = array_pop($bparts);
        $brideMiddle = array_pop($bparts);
        $brideFirst = implode(' ', $bparts);
    } elseif (count($bparts) === 2) {
        $brideSurname = $bparts[1];
        $brideFirst = $bparts[0];
    } else {
        $brideFirst = $record['bride_name'];
    }
}
$brideFullName = preg_replace('/\s+/', ' ', trim("$brideFirst $brideMiddle $brideSurname")) ?: ($record['bride_name'] ?: '—');

if ((!$groomFullName || $groomFullName === '—') && !empty($record['person_name']) && str_contains($record['person_name'], '&')) {
    $pnames = explode('&', $record['person_name']);
    $groomFullName = trim($pnames[0]);
    $brideFullName = trim($pnames[1] ?? '—');
}

$marriageDate = $record['date_of_marriage'] ?: ($record['event_date'] ?: date('Y-m-d'));
$marriagePlace = $record['place_of_marriage'] ?: $churchName;
$witness1 = $record['witness_1'] ?: ($metadata['witness_1'] ?? '—');
$witness2 = $record['witness_2'] ?: ($metadata['witness_2'] ?? '—');
$groomParents = $record['wedding_groom_parents'] ?: ($metadata['groom_parents'] ?? '—');
$brideParents = $record['wedding_bride_parents'] ?: ($metadata['bride_parents'] ?? '—');

// Notes / Remarks
$displayNotes = '';
if (!empty($record['notes'])) {
    $rawNotes = trim($record['notes']);
    if (str_starts_with($rawNotes, '{') && str_ends_with($rawNotes, '}')) {
        $metaObj = json_decode($rawNotes, true);
        if (is_array($metaObj)) {
            $displayNotes = trim((string)($metaObj['marginal_notes'] ?? ($metaObj['remarks'] ?? '')));
        }
    } else {
        $displayNotes = $rawNotes;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo esc($type); ?> Certificate — <?php echo esc($record['reference_id']); ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@500;600;700;800&family=EB+Garamond:ital,wght@0,400;0,600;1,400&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
<link rel="icon" type="image/x-icon" href="../assets/img/2323.png">
<style>
/* ── RESET & BASE ───────────────────────────────────────────── */
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box;}
body {
    font-family:'Arial', 'Helvetica', sans-serif;
    background:#25282c;
    color:#1a1a1a;
    padding:20px 15px 40px;
    display:flex;
    flex-direction:column;
    align-items:center;
    min-height:100vh;
}

/* ── SCREEN CONTROLS (SCREEN ONLY) ──────────────────────────── */
.screen-controls {
    display:flex;
    gap:12px;
    padding:12px 20px;
    background:#ffffff;
    align-items:center;
    justify-content:space-between;
    flex-wrap:wrap;
    border-radius:10px;
    border:1px solid #dcd7ce;
    box-shadow:0 4px 15px rgba(0,0,0,0.18);
    width:100%;
    max-width:820px;
    margin:0 auto 18px;
}
.screen-info {
    display:flex;
    align-items:center;
    gap:10px;
    flex-wrap:wrap;
    font-family:'Inter', sans-serif;
    font-size:13px;
    color:#334155;
}
.preview-pill {
    background:rgba(184,145,58,0.14);
    color:#8a6010;
    border:1px solid rgba(184,145,58,0.35);
    padding:3px 10px;
    border-radius:999px;
    font-size:11px;
    font-weight:700;
    letter-spacing:0.04em;
    display:inline-flex;
    align-items:center;
    gap:5px;
}
.screen-actions {
    display:flex;
    align-items:center;
    gap:10px;
    flex-wrap:wrap;
}
.btn {
    padding:8px 16px;
    border-radius:8px;
    font-size:12.5px;
    font-weight:600;
    font-family:'Inter', sans-serif;
    cursor:pointer;
    border:none;
    display:inline-flex;
    align-items:center;
    gap:6px;
    text-decoration:none;
    transition:all 0.15s ease;
}
.btn-gold {
    background:#B8913A;
    color:#111116;
    box-shadow:0 2px 8px rgba(184,145,58,0.3);
}
.btn-gold:hover {
    background:#a37f2f;
}
.btn-gray {
    background:#f1f5f9;
    color:#475569;
    border:1px solid #cbd5e1;
}
.btn-gray:hover {
    background:#e2e8f0;
    color:#1e293b;
}

/* ── 1:1 CANONICAL PAPER SHEET ──────────────────────────────── */
.official-paper-sheet {
    width:100%;
    max-width:794px; /* A4 Standard proportional width */
    background:#ffffff;
    color:#1a1a1a;
    box-shadow:0 14px 45px rgba(0,0,0,0.45);
    padding:34px 40px;
    box-sizing:border-box;
    border-radius:2px;
    position:relative;
    margin:0 auto;
}

/* Cathedral Header */
.paper-header {
    text-align:center;
    margin-bottom:14px;
}
.paper-seal {
    width:50px;
    height:50px;
    object-fit:contain;
    margin-bottom:3px;
}
.paper-cathedral-title {
    font-family:'Cinzel', serif;
    font-size:15px;
    font-weight:700;
    color:#222222;
    letter-spacing:0.5px;
    margin:0;
    text-transform:uppercase;
}
.paper-church-sub {
    font-family:'EB Garamond', Garamond, serif;
    font-size:13px;
    font-style:italic;
    color:#444444;
    margin-top:1px;
}
.paper-church-loc {
    font-size:11px;
    color:#555555;
    margin-top:1px;
}

/* Form Titles */
.paper-form-title {
    text-align:center;
    font-family:'Cinzel', 'Arial Black', sans-serif;
    font-size:18px;
    font-weight:800;
    color:#b91c1c;
    letter-spacing:1.5px;
    margin:14px 0 10px;
    text-transform:uppercase;
}
.paper-form-title-wrap {
    display:flex;
    justify-content:space-between;
    align-items:center;
    margin-top:10px;
    margin-bottom:4px;
}
.paper-form-title-wrap .paper-form-title {
    margin:0;
    font-size:12px;
    letter-spacing:0.05em;
    color:#a82020;
}

/* Event Meta Line */
.paper-event-meta-line {
    display:flex;
    flex-direction:column;
    gap:4px;
    font-size:11.5px;
    margin-bottom:12px;
    color:#333333;
}
.paper-meta-row {
    display:flex;
    align-items:baseline;
    gap:6px;
}
.paper-meta-label {
    font-weight:700;
    color:#333333;
    letter-spacing:0.5px;
}
.paper-meta-underline {
    min-width:200px;
    border-bottom:1px solid #111111;
    padding-bottom:1px;
    font-weight:700;
    font-family:'Courier New', monospace;
    font-size:12.5px;
}

/* Section Tags */
.paper-section-tag {
    font-size:11px;
    font-weight:700;
    color:#222222;
    margin:8px 0 4px;
    letter-spacing:0.5px;
    text-transform:uppercase;
}

/* Boxed Grid Tables */
.paper-grid-table {
    width:100%;
    border-collapse:collapse;
    border:1.5px solid #222222;
    margin-bottom:10px;
}
.paper-grid-table td {
    border:1px solid #222222;
    padding:5px 8px;
    vertical-align:top;
    font-size:11.5px;
}
.paper-label {
    display:block;
    font-size:9.5px;
    font-weight:700;
    color:#222222;
    text-transform:uppercase;
    letter-spacing:0.3px;
    margin-bottom:2px;
}
.paper-val {
    font-family:'Courier New', Courier, monospace;
    font-size:12.5px;
    font-weight:700;
    color:#111111;
    min-height:16px;
    line-height:1.2;
    word-break:break-word;
}
.paper-sub-labels {
    display:flex;
    justify-content:space-between;
    font-size:7.5px;
    color:#777777;
    text-transform:uppercase;
    margin-top:3px;
    border-top:1px dotted #ccc;
    padding-top:1px;
}

/* Checkbox Wrappers */
.paper-filiation-wrap {
    display:flex;
    align-items:center;
    gap:16px;
    font-size:11px;
    font-weight:700;
    margin:6px 0 10px;
}
.paper-checkbox-item {
    display:inline-flex;
    align-items:center;
    gap:6px;
}
.paper-box-square {
    width:13px;
    height:13px;
    border:1.2px solid #222222;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    font-size:10px;
    font-weight:800;
    line-height:1;
}

/* Sponsors Grid */
.sponsors-grid {
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:6px 20px;
    margin-top:4px;
    margin-bottom:12px;
}
.sponsor-line-wrap {
    border-bottom:1px solid #111111;
    min-height:20px;
    display:flex;
    align-items:flex-end;
    padding-bottom:1px;
}
.sponsor-val {
    font-family:'Courier New', Courier, monospace;
    font-size:12px;
    font-weight:700;
    color:#111111;
}

/* Footer Section */
.paper-footer {
    margin-top:14px;
    font-size:11px;
}
.sec-note {
    font-size:9px;
    font-style:italic;
    color:#666666;
    margin-bottom:4px;
}
.officiating-priest-line {
    display:flex;
    align-items:baseline;
    gap:8px;
    margin-top:3px;
}
.priest-underline {
    flex:1;
    border-bottom:1px solid #111111;
    padding-bottom:1px;
    font-family:'Courier New', Courier, monospace;
    font-size:13px;
    font-weight:700;
}

/* Official Barcode / Registry Stamp */
.paper-verification-strip {
    margin-top:16px;
    padding-top:8px;
    border-top:1px dashed #bbb;
    display:flex;
    justify-content:space-between;
    align-items:center;
    font-size:9.5px;
    color:#666;
    font-family:'Inter', sans-serif;
}

/* ── PRINT RULES ────────────────────────────────────────────── */
@page {
    size:A4 portrait;
    margin:10mm 12mm;
}
@media print {
    body {
        display:block !important;
        background:#ffffff !important;
        padding:0 !important;
        min-height:auto !important;
    }
    .screen-controls {
        display:none !important;
    }
    .official-paper-sheet,
    .official-paper-sheet * {
        visibility:visible !important;
        -webkit-print-color-adjust:exact !important;
        print-color-adjust:exact !important;
    }
    .official-paper-sheet {
        box-shadow:none !important;
        padding:0 !important;
        max-width:100% !important;
        border-radius:0 !important;
        margin:0 !important;
    }
}
</style>
</head>
<body>

<!-- ── TOP SCREEN CONTROLS ──────────────────────────────────── -->
<div class="screen-controls">
    <div class="screen-info">
        <strong><?php echo esc($type); ?> <?php echo in_array($type, ['Confirmation', 'Baptism'], true) ? 'Form' : 'Certificate'; ?></strong> 
        &bull; Ref: <code style="font-weight:700; background:#f1f5f9; padding:2px 6px; border-radius:4px;"><?php echo esc($record['reference_id']); ?></code>
        <?php if ($isPreview): ?>
            <span class="preview-pill"><i class="fa-solid fa-eye"></i> Canonical Preview</span>
        <?php endif; ?>
    </div>
    <div class="screen-actions">
        <button class="btn btn-gold" onclick="window.print()">
            <i class="fa-solid fa-print"></i> Print Document
        </button>
        <?php
        $backSec = (!empty($record['chapel_id']) && (int)$record['chapel_id'] > 0) ? 'records_chapel' : 'records_cathedral';
        ?>
        <a href="../dashboard.php?section=<?php echo $backSec; ?>" class="btn btn-gray">
            <i class="fa-solid fa-arrow-left"></i> Back to Registry
        </a>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════ -->
<!-- 1. OFFICIAL CONFIRMATION SHEET (1:1 REPLICA)                   -->
<!-- ══════════════════════════════════════════════════════════════ -->
<?php if ($type === 'Confirmation'): ?>
<div class="official-paper-sheet">
    
    <!-- Cathedral Header -->
    <div class="paper-header">
        <img src="<?php echo $logoSrc; ?>" alt="Cathedral Seal" class="paper-seal">
        <h1 class="paper-cathedral-title"><?php echo esc($churchName); ?></h1>
        <div class="paper-church-sub"><?php echo esc($denomination); ?></div>
        <div class="paper-church-loc"><?php echo esc($churchLocation); ?></div>
    </div>

    <!-- Form Title -->
    <div class="paper-form-title">CONFIRMATION FORM</div>

    <!-- Date of Confirmation & Time -->
    <div class="paper-event-meta-line">
        <div class="paper-meta-row">
            <span class="paper-meta-label">DATE OF CONFIRMATION:</span>
            <span class="paper-meta-underline"><?php echo formatDate($eventDate); ?></span>
        </div>
        <div class="paper-meta-row">
            <span class="paper-meta-label">TIME:</span>
            <span class="paper-meta-underline"><?php echo esc($confTime ?: '—'); ?></span>
        </div>
    </div>

    <!-- ( CHILD ) Tag -->
    <div class="paper-section-tag">( CHILD )</div>

    <!-- ( CHILD ) Table -->
    <table class="paper-grid-table">
        <tr>
            <td style="width: 65%;">
                <span class="paper-label">Name of Child:</span>
                <div class="paper-val"><?php echo esc($candidateFullName); ?></div>
                <div class="paper-sub-labels">
                    <span>First Name</span>
                    <span>M.I.</span>
                    <span>Surname</span>
                </div>
            </td>
            <td style="width: 35%;">
                <span class="paper-label">Date of Baptism:</span>
                <div class="paper-val"><?php echo formatDate($dateBaptism); ?></div>
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
                <div class="paper-val"><?php echo esc($pob ?: '—'); ?></div>
                <div class="paper-sub-labels">
                    <span>House No., St.</span>
                    <span>Barangay</span>
                    <span>City/Municipality</span>
                    <span>Province</span>
                </div>
            </td>
            <td>
                <span class="paper-label">Date of Birth:</span>
                <div class="paper-val"><?php echo formatDate($dob); ?></div>
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
                <div class="paper-val"><?php echo esc($address ?: '—'); ?></div>
                <div class="paper-sub-labels">
                    <span>House No., St.</span>
                    <span>Barangay</span>
                    <span>City/Municipality</span>
                    <span>Province</span>
                </div>
            </td>
            <td>
                <span class="paper-label">Gender:</span>
                <div style="display:flex; gap:14px; margin-top:3px; font-size:11px; font-weight:700;">
                    <div class="paper-checkbox-item">
                        <div class="paper-box-square"><?php echo (strtolower($gender) === 'male' || !$gender) ? '✓' : ''; ?></div>
                        <span>( MALE )</span>
                    </div>
                    <div class="paper-checkbox-item">
                        <div class="paper-box-square"><?php echo (strtolower($gender) === 'female') ? '✓' : ''; ?></div>
                        <span>( FEMALE )</span>
                    </div>
                </div>
            </td>
        </tr>
        <tr>
            <td>
                <span class="paper-label">Nationality:</span>
                <div class="paper-val"><?php echo esc($nationality ?: 'Filipino'); ?></div>
            </td>
            <td>
                <span class="paper-label">Contact No:</span>
                <div class="paper-val"><?php echo esc($contactNo ?: '—'); ?></div>
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
                <div class="paper-val"><?php echo esc($fatherFullName); ?></div>
                <div class="paper-sub-labels">
                    <span>First Name</span>
                    <span>M.I.</span>
                    <span>Surname</span>
                </div>
            </td>
            <td style="width: 35%;">
                <span class="paper-label">Place of Birth:</span>
                <div class="paper-val"><?php echo esc($fatherBirthPlace ?: '—'); ?></div>
            </td>
        </tr>
        <tr>
            <td>
                <span class="paper-label">Name of Mother (Maiden Name):</span>
                <div class="paper-val"><?php echo esc($motherFullName); ?></div>
                <div class="paper-sub-labels">
                    <span>First Name</span>
                    <span>M.I.</span>
                    <span>Surname</span>
                </div>
            </td>
            <td>
                <span class="paper-label">Place of Birth:</span>
                <div class="paper-val"><?php echo esc($motherBirthPlace ?: '—'); ?></div>
            </td>
        </tr>
    </table>

    <!-- Filiation -->
    <div class="paper-filiation-wrap">
        <span>FILIATION:</span>
        <div class="paper-checkbox-item">
            <div class="paper-box-square"><?php echo (!str_contains(strtolower($filiation), 'di')) ? '✓' : ''; ?></div>
            <span>[ KASAL ]</span>
        </div>
        <div class="paper-checkbox-item">
            <div class="paper-box-square"><?php echo (str_contains(strtolower($filiation), 'di')) ? '✓' : ''; ?></div>
            <span>[ DI KASAL ]</span>
        </div>
    </div>

    <!-- ( SPONSORS ) NINONG AT NINANG -->
    <div class="paper-section-tag">( SPONSORS ) NINONG AT NINANG</div>
    <div class="sponsors-grid">
        <?php for ($i = 0; $i < 6; $i++): ?>
            <div class="sponsor-line-wrap">
                <span class="sponsor-val"><?php echo esc($sponsors[$i] ?? '—'); ?></span>
            </div>
        <?php endfor; ?>
    </div>

    <!-- Footer Section -->
    <div class="paper-footer">
        <div class="sec-note">To be filled by the Secretary of the Parish</div>
        <div class="officiating-priest-line">
            <span style="font-weight: 700; font-size: 11px;">OFFICIATING PRIEST:</span>
            <div class="priest-underline"><?php echo esc($officiatingPriest); ?></div>
        </div>
        <div style="display: flex; gap: 24px; margin-top: 6px; font-size: 11px; color: #333;">
            <div><strong>Book No.:</strong> <span style="font-family:'Courier New', monospace; font-weight:700;"><?php echo esc($bookNumber); ?></span></div>
            <div><strong>Page No.:</strong> <span style="font-family:'Courier New', monospace; font-weight:700;"><?php echo esc($pageNumber); ?></span></div>
            <div><strong>Place of Baptism:</strong> <span><?php echo esc($placeOfBaptism ?: '—'); ?></span></div>
        </div>
    </div>

    <div class="paper-verification-strip">
        <div><strong>CANONICAL ARCHIVE:</strong> <?php echo esc($churchName); ?> &bull; Bacoor City</div>
        <div>REF: <strong><?php echo esc($record['reference_id']); ?></strong> &bull; ISSUED: <?php echo $certDate; ?></div>
    </div>

</div>
<?php endif; ?>

<!-- ══════════════════════════════════════════════════════════════ -->
<!-- 2. OFFICIAL BAPTISMAL SHEET (1:1 REPLICA)                      -->
<!-- ══════════════════════════════════════════════════════════════ -->
<?php if ($type === 'Baptism'): ?>
<div class="official-paper-sheet">
    
    <!-- Cathedral Header -->
    <div class="paper-header">
        <img src="<?php echo $logoSrc; ?>" alt="Cathedral Seal" class="paper-seal">
        <h1 class="paper-cathedral-title"><?php echo esc($churchName); ?></h1>
        <div class="paper-church-sub"><?php echo esc($denomination); ?></div>
        <div class="paper-church-loc"><?php echo esc($churchLocation); ?></div>
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
                <div class="paper-val"><?php echo esc($candidateFullName); ?></div>
                <div class="paper-sub-labels">
                    <span>First Name</span>
                    <span>M.I.</span>
                    <span>Surname</span>
                </div>
            </td>
            <td style="width: 35%;">
                <span class="paper-label">Date of Baptism:</span>
                <div class="paper-val"><?php echo formatDate($eventDate); ?></div>
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
                <div class="paper-val"><?php echo esc($pob ?: '—'); ?></div>
                <div class="paper-sub-labels">
                    <span>House No., St.</span>
                    <span>Barangay</span>
                    <span>City/Municipality</span>
                    <span>Province</span>
                </div>
            </td>
            <td>
                <span class="paper-label">Date of Birth:</span>
                <div class="paper-val"><?php echo formatDate($dob); ?></div>
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
                <div class="paper-val"><?php echo esc($address ?: '—'); ?></div>
                <div class="paper-sub-labels">
                    <span>House No., St.</span>
                    <span>Barangay</span>
                    <span>City/Municipality</span>
                    <span>Province</span>
                </div>
            </td>
            <td>
                <span class="paper-label">Gender:</span>
                <div style="display:flex; gap:14px; margin-top:3px; font-size:11px; font-weight:700;">
                    <div class="paper-checkbox-item">
                        <div class="paper-box-square"><?php echo (strtolower($gender) === 'male' || !$gender) ? '✓' : ''; ?></div>
                        <span>( MALE )</span>
                    </div>
                    <div class="paper-checkbox-item">
                        <div class="paper-box-square"><?php echo (strtolower($gender) === 'female') ? '✓' : ''; ?></div>
                        <span>( FEMALE )</span>
                    </div>
                </div>
            </td>
        </tr>
        <tr>
            <td>
                <span class="paper-label">Nationality:</span>
                <div class="paper-val"><?php echo esc($nationality ?: 'Filipino'); ?></div>
            </td>
            <td>
                <span class="paper-label">Contact No:</span>
                <div class="paper-val"><?php echo esc($contactNo ?: '—'); ?></div>
            </td>
        </tr>
    </table>

    <!-- ( PARENTS ) Section Header -->
    <div style="text-align: center; font-size: 10px; font-weight: 700; color: #222; margin: 6px 0 2px;">
        ( PARENTS )
    </div>

    <!-- ( PARENTS ) Table -->
    <table class="paper-grid-table">
        <tr>
            <td style="width: 60%;">
                <span class="paper-label">Name of Father:</span>
                <div class="paper-val"><?php echo esc($fatherFullName); ?></div>
                <div class="paper-sub-labels">
                    <span>First Name</span>
                    <span>M.I.</span>
                    <span>Surname</span>
                </div>
            </td>
            <td style="width: 40%;">
                <span class="paper-label">Place of Birth:</span>
                <div class="paper-val"><?php echo esc($fatherBirthPlace ?: '—'); ?></div>
            </td>
        </tr>
        <tr>
            <td>
                <span class="paper-label">Name of Mother (Maiden Name):</span>
                <div class="paper-val"><?php echo esc($motherFullName); ?></div>
                <div class="paper-sub-labels">
                    <span>First Name</span>
                    <span>M.I.</span>
                    <span>Surname</span>
                </div>
            </td>
            <td>
                <span class="paper-label">Place of Birth:</span>
                <div class="paper-val"><?php echo esc($motherBirthPlace ?: '—'); ?></div>
            </td>
        </tr>
        <tr>
            <td colspan="2">
                <div style="display:flex; align-items:center; gap:16px;">
                    <span class="paper-label" style="margin:0;">Filiation:</span>
                    <div class="paper-checkbox-item">
                        <div class="paper-box-square"><?php echo (!str_contains(strtolower($filiation), 'di')) ? '✓' : ''; ?></div>
                        <span>( KASAL )</span>
                    </div>
                    <div class="paper-checkbox-item">
                        <div class="paper-box-square"><?php echo (str_contains(strtolower($filiation), 'di')) ? '✓' : ''; ?></div>
                        <span>( DI KASAL )</span>
                    </div>
                </div>
            </td>
        </tr>
    </table>

    <!-- ( GRANDPARENTS OF THE CHILD ) Section Header -->
    <div style="text-align: center; font-size: 10px; font-weight: 700; color: #222; margin: 6px 0 2px;">
        ( GRANDPARENTS OF THE CHILD )
    </div>

    <div style="font-size: 8.5px; font-weight: 700; color: #222; margin-bottom: 2px;">PATERNAL GRANDPARENTS (FATHER'S SIDE)</div>
    <table class="paper-grid-table" style="margin-bottom: 6px;">
        <tr>
            <td style="width: 50%;">
                <span class="paper-label">Lolo sa Ama:</span>
                <div class="paper-val"><?php echo esc($paternalLolo ?: '—'); ?></div>
            </td>
            <td style="width: 50%;">
                <span class="paper-label">Lola sa Ama (Maiden Name):</span>
                <div class="paper-val"><?php echo esc($paternalLola ?: '—'); ?></div>
            </td>
        </tr>
    </table>

    <div style="font-size: 8.5px; font-weight: 700; color: #222; margin-bottom: 2px;">MATERNAL GRANDPARENTS (MOTHER'S SIDE)</div>
    <table class="paper-grid-table">
        <tr>
            <td style="width: 50%;">
                <span class="paper-label">Lolo sa Ina:</span>
                <div class="paper-val"><?php echo esc($maternalLolo ?: '—'); ?></div>
            </td>
            <td style="width: 50%;">
                <span class="paper-label">Lola sa Ina (Maiden Name):</span>
                <div class="paper-val"><?php echo esc($maternalLola ?: '—'); ?></div>
            </td>
        </tr>
    </table>

    <!-- ( SPONSORS / NINONG AT NINANG ) -->
    <div class="paper-section-tag">( SPONSORS / NINONG AT NINANG )</div>
    <div class="sponsors-grid">
        <?php for ($i = 0; $i < 6; $i++): ?>
            <div class="sponsor-line-wrap">
                <span class="sponsor-val"><?php echo esc($sponsors[$i] ?? '—'); ?></span>
            </div>
        <?php endfor; ?>
    </div>

    <!-- Registry Footer -->
    <div class="paper-footer">
        <div class="sec-note">To be filled by the Secretary of the Parish</div>
        <div class="officiating-priest-line">
            <span style="font-weight: 700; font-size: 11px;">OFFICIATING PRIEST:</span>
            <div class="priest-underline"><?php echo esc($officiatingPriest); ?></div>
        </div>
        <div style="display: flex; gap: 24px; margin-top: 6px; font-size: 11px; color: #333;">
            <div><strong>Book No.:</strong> <span style="font-family:'Courier New', monospace; font-weight:700;"><?php echo esc($bookNumber); ?></span></div>
            <div><strong>Page No.:</strong> <span style="font-family:'Courier New', monospace; font-weight:700;"><?php echo esc($pageNumber); ?></span></div>
        </div>
    </div>

    <div class="paper-verification-strip">
        <div><strong>CANONICAL ARCHIVE:</strong> <?php echo esc($churchName); ?> &bull; Bacoor City</div>
        <div>REF: <strong><?php echo esc($record['reference_id']); ?></strong> &bull; ISSUED: <?php echo $certDate; ?></div>
    </div>

</div>
<?php endif; ?>

<!-- ══════════════════════════════════════════════════════════════ -->
<!-- 3. OFFICIAL MARRIAGE SHEET (1:1 CATHEDRAL REPLICA)             -->
<!-- ══════════════════════════════════════════════════════════════ -->
<?php if ($type === 'Wedding'): ?>
<div class="official-paper-sheet">
    
    <!-- Cathedral Header -->
    <div class="paper-header">
        <img src="<?php echo $logoSrc; ?>" alt="Cathedral Seal" class="paper-seal">
        <h1 class="paper-cathedral-title"><?php echo esc($churchName); ?></h1>
        <div class="paper-church-sub"><?php echo esc($denomination); ?></div>
        <div class="paper-church-loc"><?php echo esc($churchLocation); ?></div>
    </div>

    <!-- Title -->
    <div class="paper-form-title">CERTIFICATE OF MARRIAGE</div>
    <div style="text-align:center; font-family:'EB Garamond', serif; font-style:italic; font-size:13.5px; color:#5a4a1a; margin-top:-6px; margin-bottom:12px;">
        Sacrament of Holy Matrimony
    </div>

    <!-- Solemnization Text -->
    <div style="text-align:center; font-size:12.5px; line-height:1.6; color:#222; margin-bottom:12px; padding:0 10px;">
        This is to certify that the Sacrament of Holy Matrimony was canonically solemnized between<br>
        <strong style="font-size:15px; font-family:'Cinzel', serif; letter-spacing:0.5px;"><?php echo esc($groomFullName); ?></strong><br>
        and<br>
        <strong style="font-size:15px; font-family:'Cinzel', serif; letter-spacing:0.5px;"><?php echo esc($brideFullName); ?></strong><br>
        on <strong><?php echo formatDate($marriageDate); ?></strong> at <strong><?php echo esc($marriagePlace); ?></strong>
    </div>

    <!-- Parties Grid -->
    <div class="paper-section-tag">CONTRACTING PARTIES</div>
    <table class="paper-grid-table">
        <tr>
            <th style="width:50%; text-align:left; background:#f8f6f0; font-weight:700; font-size:10.5px; padding:4px 8px;">GROOM</th>
            <th style="width:50%; text-align:left; background:#f8f6f0; font-weight:700; font-size:10.5px; padding:4px 8px;">BRIDE</th>
        </tr>
        <tr>
            <td>
                <span class="paper-label">Full Name:</span>
                <div class="paper-val"><?php echo esc($groomFullName); ?></div>
            </td>
            <td>
                <span class="paper-label">Full Name:</span>
                <div class="paper-val"><?php echo esc($brideFullName); ?></div>
            </td>
        </tr>
        <tr>
            <td>
                <span class="paper-label">Parents:</span>
                <div class="paper-val"><?php echo esc($groomParents ?: '—'); ?></div>
            </td>
            <td>
                <span class="paper-label">Parents:</span>
                <div class="paper-val"><?php echo esc($brideParents ?: '—'); ?></div>
            </td>
        </tr>
    </table>

    <!-- Witnesses Grid -->
    <div class="paper-section-tag">WITNESSES &amp; SPONSORS</div>
    <table class="paper-grid-table">
        <tr>
            <td style="width:50%;">
                <span class="paper-label">Principal Sponsor / Witness:</span>
                <div class="paper-val"><?php echo esc($witness1); ?></div>
            </td>
            <td style="width:50%;">
                <span class="paper-label">Principal Sponsor / Witness:</span>
                <div class="paper-val"><?php echo esc($witness2); ?></div>
            </td>
        </tr>
    </table>

    <!-- Registry & Signatures -->
    <div class="paper-footer" style="margin-top:20px;">
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:30px; margin-bottom:16px;">
            <div style="text-align:center;">
                <div style="border-bottom:1px solid #111; padding-bottom:2px; font-weight:700; font-family:'Courier New', monospace; font-size:13px;">
                    <?php echo esc($officiatingPriest); ?>
                </div>
                <div style="font-size:10px; color:#555; margin-top:3px; text-transform:uppercase;">Solemnizing Clergy</div>
            </div>
            <div style="text-align:center;">
                <div style="border-bottom:1px solid #111; padding-bottom:2px; font-weight:700; font-family:'Courier New', monospace; font-size:13px;">
                    <?php echo esc($rectorName); ?>
                </div>
                <div style="font-size:10px; color:#555; margin-top:3px; text-transform:uppercase;">Parish Priest / Rector</div>
            </div>
        </div>

        <div style="display:flex; justify-content:space-between; font-size:11px; color:#333; border-top:1px solid #ddd; padding-top:6px;">
            <div><strong>Book No.:</strong> <span style="font-family:'Courier New', monospace; font-weight:700;"><?php echo esc($bookNumber); ?></span></div>
            <div><strong>Page No.:</strong> <span style="font-family:'Courier New', monospace; font-weight:700;"><?php echo esc($pageNumber); ?></span></div>
            <div><strong>Register:</strong> <span>Matrimonial Roll</span></div>
        </div>
    </div>

    <div class="paper-verification-strip">
        <div><strong>CANONICAL ARCHIVE:</strong> <?php echo esc($churchName); ?> &bull; Bacoor City</div>
        <div>REF: <strong><?php echo esc($record['reference_id']); ?></strong> &bull; ISSUED: <?php echo $certDate; ?></div>
    </div>

</div>
<?php endif; ?>

<!-- ══════════════════════════════════════════════════════════════ -->
<!-- 4. OFFICIAL BURIAL SHEET                                       -->
<!-- ══════════════════════════════════════════════════════════════ -->
<?php if ($type === 'Funeral'):
    $funDeceased    = $record['deceased_name'] ?: $record['person_name'];
    $funTime        = $metadata['funeral_time'] ?? '';
    $funGender      = $metadata['gender'] ?? '';
    $funAge         = $record['age_at_death'] ? $record['age_at_death'] . ' years' : '';
    $funAgeGender   = trim($funAge . ($funAge && $funGender ? ' · ' : '') . $funGender) ?: '—';
    $funResidence   = $metadata['residence'] ?? '';
    $funNextOfKin   = $metadata['next_of_kin'] ?? '';
    $funContact     = $metadata['contact_no'] ?? '';
    $funDateMass    = formatDate($record['date_of_funeral'] ?: $record['event_date']) . ($funTime ? ' · ' . $funTime : '');
?>
<div class="official-paper-sheet">
    
    <!-- Cathedral Header -->
    <div class="paper-header">
        <img src="<?php echo $logoSrc; ?>" alt="Cathedral Seal" class="paper-seal">
        <h1 class="paper-cathedral-title"><?php echo esc($churchName); ?></h1>
        <div class="paper-church-sub"><?php echo esc($denomination); ?></div>
        <div class="paper-church-loc"><?php echo esc($churchLocation); ?></div>
    </div>

    <div class="paper-form-title">CERTIFICATE OF CHRISTIAN BURIAL</div>

    <div style="text-align:center; font-size:12.5px; line-height:1.6; color:#222; margin-bottom:12px;">
        This is to certify that the Rite of Christian Burial was solemnly administered for the soul of<br>
        <strong style="font-size:16px; font-family:'Cinzel', serif; letter-spacing:0.5px;"><?php echo esc($funDeceased); ?></strong>
    </div>

    <div class="paper-section-tag">DECEASED PARTICULARS</div>
    <table class="paper-grid-table">
        <tr>
            <td style="width:60%;">
                <span class="paper-label">Full Name of Deceased:</span>
                <div class="paper-val"><?php echo esc($funDeceased); ?></div>
            </td>
            <td style="width:40%;">
                <span class="paper-label">Age at Death<?php echo $funGender ? ' &amp; Gender' : ''; ?>:</span>
                <div class="paper-val"><?php echo esc($funAgeGender); ?></div>
            </td>
        </tr>
        <tr>
            <td>
                <span class="paper-label">Date of Death:</span>
                <div class="paper-val"><?php echo formatDate($record['date_of_death']); ?></div>
            </td>
            <td>
                <span class="paper-label">Marital Status:</span>
                <div class="paper-val"><?php echo esc($record['funeral_marital_status'] ?: '—'); ?></div>
            </td>
        </tr>
        <tr>
            <td>
                <span class="paper-label">Occupation:</span>
                <div class="paper-val"><?php echo esc($record['funeral_occupation'] ?: '—'); ?></div>
            </td>
            <td>
                <span class="paper-label">Cause of Death:</span>
                <div class="paper-val"><?php echo esc($record['funeral_cause_of_death'] ?: '—'); ?></div>
            </td>
        </tr>
        <?php if (!empty($funResidence)): ?>
        <tr>
            <td colspan="2">
                <span class="paper-label">Last Residence:</span>
                <div class="paper-val"><?php echo esc($funResidence); ?></div>
            </td>
        </tr>
        <?php endif; ?>
    </table>

    <div class="paper-section-tag">BURIAL &amp; RITES DETAILS</div>
    <table class="paper-grid-table">
        <tr>
            <td style="width:50%;">
                <span class="paper-label">Date<?php echo $funTime ? ' &amp; Time' : ''; ?> of Funeral Mass:</span>
                <div class="paper-val"><?php echo esc($funDateMass); ?></div>
            </td>
            <td style="width:50%;">
                <span class="paper-label">Place of Burial / Cemetery:</span>
                <div class="paper-val"><?php echo esc($record['place_of_burial'] ?: '—'); ?></div>
            </td>
        </tr>
        <?php if (!empty($funNextOfKin) || !empty($funContact)): ?>
        <tr>
            <td>
                <span class="paper-label">Next of Kin / Informant:</span>
                <div class="paper-val"><?php echo esc($funNextOfKin ?: '—'); ?></div>
            </td>
            <td>
                <span class="paper-label">Contact No.:</span>
                <div class="paper-val"><?php echo esc($funContact ?: '—'); ?></div>
            </td>
        </tr>
        <?php endif; ?>
    </table>

    <?php if (!empty($displayNotes)): ?>
    <div style="margin-top:8px; padding:6px 10px; border:1px solid #ccc; font-size:11px; color:#333; background:#fafafa;">
        <strong>Marginal Remarks:</strong> <?php echo nl2br(esc($displayNotes)); ?>
    </div>
    <?php endif; ?>

    <div class="paper-footer" style="margin-top:20px;">
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:30px; margin-bottom:16px;">
            <div style="text-align:center;">
                <div style="border-bottom:1px solid #111; padding-bottom:2px; font-weight:700; font-family:'Courier New', monospace; font-size:13px;">
                    <?php echo esc($officiatingPriest); ?>
                </div>
                <div style="font-size:10px; color:#555; margin-top:3px; text-transform:uppercase;">Officiating Priest</div>
            </div>
            <div style="text-align:center;">
                <div style="border-bottom:1px solid #111; padding-bottom:2px; font-weight:700; font-family:'Courier New', monospace; font-size:13px;">
                    <?php echo esc($rectorName); ?>
                </div>
                <div style="font-size:10px; color:#555; margin-top:3px; text-transform:uppercase;">Parish Priest / Rector</div>
            </div>
        </div>

        <div style="display:flex; justify-content:space-between; font-size:11px; color:#333; border-top:1px solid #ddd; padding-top:6px;">
            <div><strong>Book No.:</strong> <span style="font-family:'Courier New', monospace; font-weight:700;"><?php echo esc($bookNumber); ?></span></div>
            <div><strong>Page No.:</strong> <span style="font-family:'Courier New', monospace; font-weight:700;"><?php echo esc($pageNumber); ?></span></div>
            <div><strong>Register:</strong> <span>Burial Register</span></div>
        </div>
    </div>

    <div class="paper-verification-strip">
        <div><strong>CANONICAL ARCHIVE:</strong> <?php echo esc($churchName); ?> &bull; Bacoor City</div>
        <div>REF: <strong><?php echo esc($record['reference_id']); ?></strong> &bull; ISSUED: <?php echo $certDate; ?></div>
    </div>

</div>
<?php endif; ?>

<!-- ══════════════════════════════════════════════════════════════ -->
<!-- 5. COMMUNION SHEET                                             -->
<!-- ══════════════════════════════════════════════════════════════ -->
<?php if ($type === 'Communion'): ?>
<div class="official-paper-sheet">
    
    <!-- Cathedral Header -->
    <div class="paper-header">
        <img src="<?php echo $logoSrc; ?>" alt="Cathedral Seal" class="paper-seal">
        <h1 class="paper-cathedral-title"><?php echo esc($churchName); ?></h1>
        <div class="paper-church-sub"><?php echo esc($denomination); ?></div>
        <div class="paper-church-loc"><?php echo esc($churchLocation); ?></div>
    </div>

    <div class="paper-form-title">CERTIFICATE OF FIRST COMMUNION</div>

    <div style="text-align:center; font-size:12.5px; line-height:1.6; color:#222; margin-bottom:12px;">
        This is to certify that<br>
        <strong style="font-size:16px; font-family:'Cinzel', serif; letter-spacing:0.5px;"><?php echo esc($record['person_name']); ?></strong><br>
        received for the first time the Sacrament of the Most Holy Eucharist on <strong><?php echo formatDate($eventDate); ?></strong>
    </div>

    <div class="paper-section-tag">COMMUNION DETAILS</div>
    <table class="paper-grid-table">
        <tr>
            <td style="width:60%;">
                <span class="paper-label">Communicant Name:</span>
                <div class="paper-val"><?php echo esc($record['person_name']); ?></div>
            </td>
            <td style="width:40%;">
                <span class="paper-label">Date of Communion:</span>
                <div class="paper-val"><?php echo formatDate($eventDate); ?></div>
            </td>
        </tr>
        <tr>
            <td>
                <span class="paper-label">Father's Name:</span>
                <div class="paper-val"><?php echo esc($record['father_name'] ?: '—'); ?></div>
            </td>
            <td>
                <span class="paper-label">Mother's Maiden Name:</span>
                <div class="paper-val"><?php echo esc($record['mother_name'] ?: '—'); ?></div>
            </td>
        </tr>
    </table>

    <div class="paper-footer" style="margin-top:20px;">
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:30px; margin-bottom:16px;">
            <div style="text-align:center;">
                <div style="border-bottom:1px solid #111; padding-bottom:2px; font-weight:700; font-family:'Courier New', monospace; font-size:13px;">
                    <?php echo esc($officiatingPriest); ?>
                </div>
                <div style="font-size:10px; color:#555; margin-top:3px; text-transform:uppercase;">Officiating Priest</div>
            </div>
            <div style="text-align:center;">
                <div style="border-bottom:1px solid #111; padding-bottom:2px; font-weight:700; font-family:'Courier New', monospace; font-size:13px;">
                    <?php echo esc($rectorName); ?>
                </div>
                <div style="font-size:10px; color:#555; margin-top:3px; text-transform:uppercase;">Parish Priest / Rector</div>
            </div>
        </div>

        <div style="display:flex; justify-content:space-between; font-size:11px; color:#333; border-top:1px solid #ddd; padding-top:6px;">
            <div><strong>Book No.:</strong> <span style="font-family:'Courier New', monospace; font-weight:700;"><?php echo esc($bookNumber); ?></span></div>
            <div><strong>Page No.:</strong> <span style="font-family:'Courier New', monospace; font-weight:700;"><?php echo esc($pageNumber); ?></span></div>
        </div>
    </div>

    <div class="paper-verification-strip">
        <div><strong>CANONICAL ARCHIVE:</strong> <?php echo esc($churchName); ?> &bull; Bacoor City</div>
        <div>REF: <strong><?php echo esc($record['reference_id']); ?></strong> &bull; ISSUED: <?php echo $certDate; ?></div>
    </div>

</div>
<?php endif; ?>

</body>
</html>
