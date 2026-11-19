<?php
require_once __DIR__ . '/../../config/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../index.php?error=session');
    exit();
}

$role = $_SESSION['role'] ?? '';
$canView = in_array($role, ['Admin', 'Cathedral Admin', 'Chapel Admin', 'Priest', 'Pastor', 'Staff']);
if (!$canView) {
    header('Location: ../../dashboard.php');
    exit();
}

// Date Range
$from = (isset($_GET['from']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['from'])) ? $_GET['from'] : date('Y-m-01');
$to   = (isset($_GET['to']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['to'])) ? $_GET['to'] : date('Y-m-t');
$mode = $_GET['mode'] ?? 'prefill'; // 'prefill' or 'blank'
$blankRows = max(10, min(50, (int)($_GET['blank_rows'] ?? 18)));

// Chapel scoping: non-admins are strictly isolated to their assigned chapel (or Cathedral if Cathedral Staff)
$userChapelId = $_SESSION['chapel_id'] ?? null;
$cathedralOnly = false;
if (in_array($role, ['Admin', 'Cathedral Admin', 'Priest', 'Pastor'], true)) {
    if (isset($_GET['chapel_id']) && $_GET['chapel_id'] !== '') {
        if ($_GET['chapel_id'] === '0' || $_GET['chapel_id'] === 'cathedral') {
            $cathedralOnly = true;
            $chapelId = null;
        } elseif (is_numeric($_GET['chapel_id'])) {
            $chapelId = (int)$_GET['chapel_id'];
        } else {
            $chapelId = null;
        }
    } else {
        $chapelId = null;
    }
} elseif (!empty($userChapelId)) {
    $chapelId = (int)$userChapelId;
} else {
    $cathedralOnly = true;
    $chapelId = null;
}

// Fetch Parish Info
$parishName = 'Cathedral of the Holy Child';
$dioceseName = 'Diocese of Rizal and Pampanga — Iglesia Filipina Independiente';
$parishAddress = 'Angono, Rizal, Philippines';

$stmt = $conn->query("SELECT parish_name, address FROM parish_contact_info LIMIT 1");
if ($stmt && $row = $stmt->fetch_assoc()) {
    if (!empty($row['parish_name'])) $parishName = $row['parish_name'];
    if (!empty($row['address'])) $parishAddress = $row['address'];
}

// Fetch Events & Sacraments if pre-fill mode
$records = [];
if ($mode === 'prefill') {
    // 1. Events (Masses, Liturgies, Services)
    $pFrom = $from . ' 00:00:00';
    $pTo   = $to . ' 23:59:59';
    
    $evSql = "SELECT e.event_id, e.title, e.event_type, e.start_time, e.end_time, e.details, 
                     COALESCE(u.full_name, 'Parish Priest') as celebrant,
                     c.chapel_name
              FROM events e
              LEFT JOIN users u ON e.pastor_id = u.id
              LEFT JOIN chapels c ON e.chapel_id = c.chapel_id
              WHERE e.start_time BETWEEN ? AND ? AND e.is_archived = 0";
    if ($cathedralOnly) {
        $evSql .= " AND (e.chapel_id IS NULL OR e.chapel_id = 0)";
    } elseif ($chapelId !== null) {
        $evSql .= " AND e.chapel_id = " . (int)$chapelId;
    }
    $evSql .= " ORDER BY e.start_time ASC";
    
    $stmt = $conn->prepare($evSql);
    if ($stmt) {
        $stmt->bind_param('ss', $pFrom, $pTo);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) {
            $dt = new DateTime($r['start_time']);
            $records[] = [
                'date' => $dt->format('Y-m-d'),
                'time' => $dt->format('h:i A'),
                'service' => $r['event_type'] ?: $r['title'],
                'recipients' => $r['details'] ?: 'General Congregation',
                'attendance' => '',
                'communicants' => '',
                'offerings' => '',
                'pledges' => '',
                'receipts' => '',
                'celebrant' => $r['celebrant'],
                'remarks' => $r['chapel_name'] ? "Venue: {$r['chapel_name']}" : ''
            ];
        }
        $stmt->close();
    }

    // 2. Sacramental Records (Baptisms, Weddings, Funerals, Confirmations)
    $sacSql = "SELECT sr.record_id, sr.record_type, sr.person_name, sr.groom_name, sr.bride_name,
                      sr.event_date, sr.officiating_priest, sr.reference_id, c.chapel_name
               FROM sacramental_records sr
               LEFT JOIN chapels c ON sr.chapel_id = c.chapel_id
               WHERE sr.event_date BETWEEN ? AND ? AND sr.is_archived = 0";
    if ($cathedralOnly) {
        $sacSql .= " AND (sr.chapel_id IS NULL OR sr.chapel_id = 0)";
    } elseif ($chapelId !== null) {
        $sacSql .= " AND sr.chapel_id = " . (int)$chapelId;
    }
    $sacSql .= " ORDER BY sr.event_date ASC";

    $stmt2 = $conn->prepare($sacSql);
    if ($stmt2) {
        $stmt2->bind_param('ss', $from, $to);
        $stmt2->execute();
        $res2 = $stmt2->get_result();
        while ($s = $res2->fetch_assoc()) {
            $recip = $s['person_name'];
            if ($s['record_type'] === 'Wedding' && ($s['groom_name'] || $s['bride_name'])) {
                $recip = trim(($s['groom_name'] ?: '') . ' & ' . ($s['bride_name'] ?: ''));
            }
            $records[] = [
                'date' => $s['event_date'],
                'time' => '—',
                'service' => $s['record_type'] . ' Sacrament',
                'recipients' => $recip ?: '—',
                'attendance' => '',
                'communicants' => '',
                'offerings' => '',
                'pledges' => '',
                'receipts' => $s['reference_id'] ?: '',
                'celebrant' => $s['officiating_priest'] ?: 'Parish Priest',
                'remarks' => $s['chapel_name'] ? "Chapel: {$s['chapel_name']}" : ''
            ];
        }
        $stmt2->close();
    }

    // Sort combined by date
    usort($records, function($a, $b) {
        return strcmp($a['date'], $b['date']);
    });
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Canonical Register of Services & Sacraments — Print Report</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        :root {
            --accent: #c9a84c;
            --border-dark: #334155;
            --text-dark: #0f172a;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', sans-serif;
            color: var(--text-dark);
            background: #e2e8f0;
            padding: 20px;
            font-size: 11px;
            line-height: 1.3;
        }

        /* ── INTERACTIVE CONFIG TOOLBAR (Hidden during print) ── */
        .config-toolbar {
            background: #1e293b;
            color: #f8fafc;
            border-radius: 12px;
            padding: 16px 20px;
            margin-bottom: 20px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.3);
        }

        .toolbar-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            padding-bottom: 12px;
            margin-bottom: 14px;
        }

        .toolbar-title {
            font-family: 'Cinzel', serif;
            font-size: 16px;
            font-weight: 700;
            color: var(--accent);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .toolbar-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .btn-print {
            background: var(--accent);
            color: #0f172a;
            border: none;
            padding: 8px 18px;
            border-radius: 6px;
            font-weight: 700;
            font-size: 13px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
        }
        .btn-print:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }

        .btn-ghost {
            background: transparent;
            color: #94a3b8;
            border: 1px solid rgba(255, 255, 255, 0.2);
            padding: 7px 14px;
            border-radius: 6px;
            font-size: 12px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-ghost:hover {
            color: #fff;
            border-color: #fff;
        }

        .toolbar-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 16px;
        }

        .control-group-title {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--accent);
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .toggle-chips {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }

        .toggle-label {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 6px;
            padding: 5px 10px;
            font-size: 11.5px;
            color: #cbd5e1;
            cursor: pointer;
            user-select: none;
            transition: all 0.15s;
        }
        .toggle-label:hover {
            background: rgba(255, 255, 255, 0.12);
        }
        .toggle-label input[type="checkbox"] {
            accent-color: var(--accent);
            cursor: pointer;
        }

        .preset-btn {
            background: rgba(201, 168, 76, 0.15);
            color: var(--accent);
            border: 1px solid var(--accent);
            border-radius: 4px;
            padding: 3px 8px;
            font-size: 10px;
            font-weight: 600;
            cursor: pointer;
        }
        .preset-btn:hover {
            background: var(--accent);
            color: #0f172a;
        }

        /* ── PRINT PAPER SHEET ── */
        .paper-page {
            background: #ffffff;
            width: 100%;
            max-width: 1300px;
            margin: 0 auto;
            padding: 24px 30px;
            border-radius: 4px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
            border: 1px solid #cbd5e1;
            page-break-after: always;
        }

        .paper-header {
            text-align: center;
            margin-bottom: 12px;
            padding-bottom: 8px;
            border-bottom: 2px solid #000;
        }
        .paper-header h1 {
            font-family: 'Cinzel', serif;
            font-size: 18px;
            font-weight: 800;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            margin-bottom: 2px;
        }
        .paper-header h2 {
            font-family: 'Cinzel', serif;
            font-size: 14px;
            font-weight: 700;
            color: #334155;
            margin-bottom: 2px;
        }
        .paper-header p {
            font-size: 10px;
            color: #64748b;
        }

        .meta-strip {
            display: flex;
            justify-content: space-between;
            font-size: 10px;
            font-weight: 600;
            color: #475569;
            margin-bottom: 8px;
            padding: 0 2px;
        }

        /* ── THE CANONICAL GRID TABLE ── */
        .register-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: auto;
            border: 1.5px solid #000;
        }

        .register-table th,
        .register-table td {
            border: 1px solid #000;
            padding: 6px 5px;
            vertical-align: middle;
            font-size: 9.5px;
        }

        .register-table th {
            background: #f8fafc;
            font-weight: 700;
            text-align: center;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            color: #000;
            line-height: 1.15;
        }

        .register-table td {
            height: 28px; /* Room for handwriting or clean data */
        }

        .col-center { text-align: center; }
        .col-right  { text-align: right; }
        .col-editable {
            outline: none;
            background: transparent;
        }
        .col-editable:focus {
            background: #fef08a;
        }

        /* ── SIGNATURE SECTION ── */
        .signature-strip {
            display: flex;
            justify-content: space-between;
            gap: 20px;
            margin-top: 35px;
            padding-top: 10px;
            page-break-inside: avoid;
        }

        .signature-box {
            flex: 1;
            text-align: center;
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
        }

        .signature-line {
            border-top: 1px solid #000;
            margin-bottom: 4px;
            width: 100%;
        }

        .signature-title {
            font-family: 'Cinzel', serif;
            font-size: 9.5px;
            font-weight: 700;
            letter-spacing: 0.06em;
            text-transform: uppercase;
        }

        .signature-sub {
            font-size: 8px;
            color: #64748b;
        }

        /* ── PRINT STYLING (A4 / Letter Landscape) ── */
        @media print {
            @page {
                size: landscape;
                margin: 8mm 10mm;
            }

            body {
                background: #fff;
                padding: 0;
                font-size: 9px;
            }

            .config-toolbar {
                display: none !important;
            }

            .paper-page {
                border: none;
                box-shadow: none;
                padding: 0;
                max-width: 100%;
                width: 100%;
            }

            .register-table {
                border: 1.5px solid #000 !important;
            }

            .register-table th,
            .register-table td {
                border: 1px solid #000 !important;
                padding: 4px 3px !important;
                font-size: 8.5px !important;
            }

            .register-table td {
                height: 24px !important;
            }

            .signature-strip {
                margin-top: 30px !important;
            }
        }
    </style>
</head>
<body>

    <!-- ── 1. CONFIGURATION & TOGGLE TOOLBAR ── -->
    <div class="config-toolbar">
        <div class="toolbar-header">
            <div class="toolbar-title">
                <i class="fa-solid fa-sliders"></i>
                <span>Register of Services — Print Customizer</span>
            </div>
            <div class="toolbar-actions">
                <a href="../../dashboard.php?section=reports" onclick="handleBack(event)" class="btn-ghost" title="Return to Dashboard">
                    <i class="fa-solid fa-arrow-left"></i> Back
                </a>
                <button type="button" class="btn-print" onclick="window.print()">
                    <i class="fa-solid fa-print"></i> Print Document
                </button>
            </div>
        </div>

        <div class="toolbar-grid">
            <!-- GROUP A: Mode & Presets -->
            <div>
                <div class="control-group-title">
                    <span>1. Report Mode & Presets</span>
                </div>
                <div style="display: flex; gap: 8px; margin-bottom: 10px;">
                    <button type="button" class="preset-btn" onclick="applyPreset('all')">
                        <i class="fa-solid fa-check-double"></i> All Columns
                    </button>
                    <button type="button" class="preset-btn" onclick="applyPreset('liturgical')">
                        <i class="fa-solid fa-church"></i> Pastoral Only
                    </button>
                    <button type="button" class="preset-btn" onclick="applyPreset('financial')">
                        <i class="fa-solid fa-coins"></i> Financial Focus
                    </button>
                </div>
                <form method="GET" style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                    <label style="font-size: 11px;">Mode:</label>
                    <select name="mode" onchange="this.form.submit()" style="padding: 4px 8px; border-radius: 4px; background: #0f172a; color: #fff; border: 1px solid rgba(255,255,255,0.2); font-size: 11px;">
                        <option value="prefill" <?php echo $mode === 'prefill' ? 'selected' : ''; ?>>Pre-filled with System Events</option>
                        <option value="blank" <?php echo $mode === 'blank' ? 'selected' : ''; ?>>Blank Sacristy Sheet</option>
                    </select>
                    <?php if ($mode === 'prefill'): ?>
                        <input type="date" name="from" value="<?php echo htmlspecialchars($from); ?>" style="padding: 3px 6px; border-radius: 4px; font-size: 10.5px;">
                        <input type="date" name="to" value="<?php echo htmlspecialchars($to); ?>" style="padding: 3px 6px; border-radius: 4px; font-size: 10.5px;">
                    <?php else: ?>
                        <label style="font-size: 11px;">Rows:</label>
                        <input type="number" name="blank_rows" min="10" max="40" value="<?php echo $blankRows; ?>" style="width: 55px; padding: 3px 6px; border-radius: 4px; font-size: 10.5px;">
                    <?php endif; ?>
                    <button type="submit" class="preset-btn" style="padding: 4px 10px;">Reload</button>
                </form>
            </div>

            <!-- GROUP B: Column Toggles -->
            <div style="grid-column: span 2;">
                <div class="control-group-title">
                    <span>2. Toggle Columns (Turn Off What Is Not Needed)</span>
                    <span style="font-size: 9.5px; opacity: 0.7;">Check/Uncheck to show/hide in real-time</span>
                </div>
                <div class="toggle-chips">
                    <label class="toggle-label"><input type="checkbox" id="chk-col-date" checked onchange="toggleCol('col-date')"> Date</label>
                    <label class="toggle-label"><input type="checkbox" id="chk-col-time" checked onchange="toggleCol('col-time')"> Time</label>
                    <label class="toggle-label"><input type="checkbox" id="chk-col-service" checked onchange="toggleCol('col-service')"> Service</label>
                    <label class="toggle-label"><input type="checkbox" id="chk-col-recipient" checked onchange="toggleCol('col-recipient')"> Recipient/s</label>
                    <label class="toggle-label"><input type="checkbox" id="chk-col-attendance" checked onchange="toggleCol('col-attendance')"> Attendance</label>
                    <label class="toggle-label"><input type="checkbox" id="chk-col-communicants" checked onchange="toggleCol('col-communicants')"> Communicants</label>
                    <label class="toggle-label"><input type="checkbox" id="chk-col-offerings" checked onchange="toggleCol('col-offerings')"> Offerings</label>
                    <label class="toggle-label"><input type="checkbox" id="chk-col-pledges" checked onchange="toggleCol('col-pledges')"> Pledges</label>
                    <label class="toggle-label"><input type="checkbox" id="chk-col-receipts" checked onchange="toggleCol('col-receipts')"> Sacramental Receipts</label>
                    <label class="toggle-label"><input type="checkbox" id="chk-col-celebrant" checked onchange="toggleCol('col-celebrant')"> Celebrant/Officiant</label>
                    <label class="toggle-label"><input type="checkbox" id="chk-col-signature" checked onchange="toggleCol('col-signature')"> Signature</label>
                    <label class="toggle-label"><input type="checkbox" id="chk-col-remarks" checked onchange="toggleCol('col-remarks')"> Remarks</label>
                </div>
            </div>

            <!-- GROUP C: Signature Lines & Header -->
            <div>
                <div class="control-group-title">
                    <span>3. Signature Lines & Header</span>
                </div>
                <div class="toggle-chips">
                    <label class="toggle-label"><input type="checkbox" id="chk-sig-sec" checked onchange="toggleSig('sig-sec')"> Parish Secretary</label>
                    <label class="toggle-label"><input type="checkbox" id="chk-sig-priest" checked onchange="toggleSig('sig-priest')"> Parish Priest</label>
                    <label class="toggle-label"><input type="checkbox" id="chk-sig-treas" checked onchange="toggleSig('sig-treas')"> Parish Treasurer</label>
                    <label class="toggle-label"><input type="checkbox" id="chk-sig-bishop" checked onchange="toggleSig('sig-bishop')"> Diocesan Bishop</label>
                    <label class="toggle-label"><input type="checkbox" id="chk-header" checked onchange="toggleHeader()"> Show Parish Header</label>
                </div>
            </div>
        </div>
    </div>

    <!-- ── 2. PRINTABLE PAPER SHEET ── -->
    <div class="paper-page" id="printable-sheet">
        
        <!-- Header -->
        <div class="paper-header" id="section-paper-header">
            <h1><?php echo htmlspecialchars($parishName); ?></h1>
            <h2>REGISTER OF SERVICES AND CHURCH SACRAMENTS</h2>
            <p><?php echo htmlspecialchars($dioceseName); ?> • <?php echo htmlspecialchars($parishAddress); ?></p>
        </div>

        <!-- Meta strip -->
        <div class="meta-strip">
            <span>Period: <strong><?php echo date('F d, Y', strtotime($from)); ?> — <?php echo date('F d, Y', strtotime($to)); ?></strong></span>
            <span>Canonical Service Logbook</span>
            <span>Printed on: <?php echo date('M d, Y h:i A'); ?></span>
        </div>

        <!-- Canonical Table -->
        <table class="register-table">
            <thead>
                <tr>
                    <th class="col-date" style="width: 7%;">Date</th>
                    <th class="col-time" style="width: 6%;">Time</th>
                    <th class="col-service" style="width: 14%;">Service</th>
                    <th class="col-recipient" style="width: 15%;">Recipient/s</th>
                    <th class="col-attendance" style="width: 7%;">Attendance</th>
                    <th class="col-communicants" style="width: 8%;">Communicants</th>
                    <th class="col-offerings" style="width: 7%;">Offerings</th>
                    <th class="col-pledges" style="width: 6%;">Pledges</th>
                    <th class="col-receipts" style="width: 8%;">Sacramental<br>Receipts</th>
                    <th class="col-celebrant" style="width: 10%;">Celebrant /<br>Officiant</th>
                    <th class="col-signature" style="width: 6%;">Signature</th>
                    <th class="col-remarks" style="width: 6%;">Remarks</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($mode === 'prefill' && !empty($records)): ?>
                    <?php foreach ($records as $r): ?>
                        <tr>
                            <td class="col-date col-center"><?php echo htmlspecialchars($r['date']); ?></td>
                            <td class="col-time col-center"><?php echo htmlspecialchars($r['time']); ?></td>
                            <td class="col-service"><?php echo htmlspecialchars($r['service']); ?></td>
                            <td class="col-recipient"><?php echo htmlspecialchars($r['recipients']); ?></td>
                            <td class="col-attendance col-center col-editable" contenteditable="true"><?php echo htmlspecialchars($r['attendance']); ?></td>
                            <td class="col-communicants col-center col-editable" contenteditable="true"><?php echo htmlspecialchars($r['communicants']); ?></td>
                            <td class="col-offerings col-right col-editable" contenteditable="true"><?php echo htmlspecialchars($r['offerings']); ?></td>
                            <td class="col-pledges col-right col-editable" contenteditable="true"><?php echo htmlspecialchars($r['pledges']); ?></td>
                            <td class="col-receipts col-center col-editable" contenteditable="true"><?php echo htmlspecialchars($r['receipts']); ?></td>
                            <td class="col-celebrant"><?php echo htmlspecialchars($r['celebrant']); ?></td>
                            <td class="col-signature"></td>
                            <td class="col-remarks col-editable" contenteditable="true"><?php echo htmlspecialchars($r['remarks']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <!-- Add extra blank rows if records are fewer than 12 for signing -->
                    <?php for ($i = count($records); $i < 14; $i++): ?>
                        <tr>
                            <td class="col-date"></td>
                            <td class="col-time"></td>
                            <td class="col-service"></td>
                            <td class="col-recipient"></td>
                            <td class="col-attendance"></td>
                            <td class="col-communicants"></td>
                            <td class="col-offerings"></td>
                            <td class="col-pledges"></td>
                            <td class="col-receipts"></td>
                            <td class="col-celebrant"></td>
                            <td class="col-signature"></td>
                            <td class="col-remarks"></td>
                        </tr>
                    <?php endfor; ?>
                <?php else: ?>
                    <!-- Blank Sheet Mode -->
                    <?php for ($i = 0; $i < $blankRows; $i++): ?>
                        <tr>
                            <td class="col-date"></td>
                            <td class="col-time"></td>
                            <td class="col-service"></td>
                            <td class="col-recipient"></td>
                            <td class="col-attendance"></td>
                            <td class="col-communicants"></td>
                            <td class="col-offerings"></td>
                            <td class="col-pledges"></td>
                            <td class="col-receipts"></td>
                            <td class="col-celebrant"></td>
                            <td class="col-signature"></td>
                            <td class="col-remarks"></td>
                        </tr>
                    <?php endfor; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- Signatures Strip -->
        <div class="signature-strip" id="section-signatures">
            <div class="signature-box" id="sig-sec">
                <div class="signature-line"></div>
                <div class="signature-title">Parish Secretary</div>
                <div class="signature-sub">Recorded & Verified</div>
            </div>
            <div class="signature-box" id="sig-priest">
                <div class="signature-line"></div>
                <div class="signature-title">Parish Priest</div>
                <div class="signature-sub">Attested & Approved</div>
            </div>
            <div class="signature-box" id="sig-treas">
                <div class="signature-line"></div>
                <div class="signature-title">Parish Treasurer</div>
                <div class="signature-sub">Financial Audit</div>
            </div>
            <div class="signature-box" id="sig-bishop">
                <div class="signature-line"></div>
                <div class="signature-title">Diocesan Bishop</div>
                <div class="signature-sub">Episcopal Canonical Visitation</div>
            </div>
        </div>

    </div>

    <!-- ── 3. INTERACTIVE COLUMN & SIGNATURE CONTROLLER SCRIPT ── -->
    <script>
        function toggleCol(colClass) {
            const cells = document.querySelectorAll('.' + colClass);
            const isChecked = document.getElementById('chk-' + colClass).checked;
            cells.forEach(el => {
                el.style.display = isChecked ? '' : 'none';
            });
        }

        function toggleSig(sigId) {
            const el = document.getElementById(sigId);
            const isChecked = document.getElementById('chk-' + sigId).checked;
            if (el) el.style.display = isChecked ? '' : 'none';
        }

        function toggleHeader() {
            const h = document.getElementById('section-paper-header');
            const isChecked = document.getElementById('chk-header').checked;
            if (h) h.style.display = isChecked ? '' : 'none';
        }

        function applyPreset(name) {
            const cols = ['col-date','col-time','col-service','col-recipient','col-attendance','col-communicants','col-offerings','col-pledges','col-receipts','col-celebrant','col-signature','col-remarks'];
            
            if (name === 'all') {
                cols.forEach(c => {
                    const chk = document.getElementById('chk-' + c);
                    if (chk) { chk.checked = true; toggleCol(c); }
                });
                ['sig-sec','sig-priest','sig-treas','sig-bishop'].forEach(s => {
                    const chk = document.getElementById('chk-' + s);
                    if (chk) { chk.checked = true; toggleSig(s); }
                });
            } else if (name === 'liturgical') {
                // Turn off financials & treasurer signature
                cols.forEach(c => {
                    const isFin = ['col-offerings', 'col-pledges', 'col-receipts'].includes(c);
                    const chk = document.getElementById('chk-' + c);
                    if (chk) { chk.checked = !isFin; toggleCol(c); }
                });
                document.getElementById('chk-sig-treas').checked = false; toggleSig('sig-treas');
                document.getElementById('chk-sig-sec').checked = true; toggleSig('sig-sec');
                document.getElementById('chk-sig-priest').checked = true; toggleSig('sig-priest');
                document.getElementById('chk-sig-bishop').checked = true; toggleSig('sig-bishop');
            } else if (name === 'financial') {
                // Turn off communicants & remarks
                cols.forEach(c => {
                    const isLit = ['col-communicants', 'col-remarks'].includes(c);
                    const chk = document.getElementById('chk-' + c);
                    if (chk) { chk.checked = !isLit; toggleCol(c); }
                });
                document.getElementById('chk-sig-sec').checked = true; toggleSig('sig-sec');
                document.getElementById('chk-sig-priest').checked = true; toggleSig('sig-priest');
                document.getElementById('chk-sig-treas').checked = true; toggleSig('sig-treas');
                document.getElementById('chk-sig-bishop').checked = false; toggleSig('sig-bishop');
            }
        }

        function handleBack(e) {
            // If opened in a popup/tab that can be closed, try closing it first
            try {
                if (window.opener) {
                    window.close();
                    return;
                }
            } catch (err) {}
            // Fallback: navigate directly back to the reports section in dashboard
            window.location.href = '../../dashboard.php?section=reports';
        }
    </script>
</body>
</html>
