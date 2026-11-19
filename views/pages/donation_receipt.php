<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../app/controllers/FinanceController.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../index.php?error=session');
    exit();
}

$controller = new FinanceController($conn);
$id = intval($_GET['id'] ?? 0);
if (!$id) {
    header('Location: ../../dashboard.php?section=donations');
    exit();
}

$d = $controller->getDonationReceipt($id);
if (!$d) {
    header('Location: ../../dashboard.php?section=donations&error=unauthorized');
    exit();
}

/**
 * Converts numeric currency amount to English words.
 */
function amountToWords(float $amount): string {
    $ones = [
        1 => 'One', 2 => 'Two', 3 => 'Three', 4 => 'Four', 5 => 'Five',
        6 => 'Six', 7 => 'Seven', 8 => 'Eight', 9 => 'Nine', 10 => 'Ten',
        11 => 'Eleven', 12 => 'Twelve', 13 => 'Thirteen', 14 => 'Fourteen',
        15 => 'Fifteen', 16 => 'Sixteen', 17 => 'Seventeen', 18 => 'Eighteen',
        19 => 'Nineteen'
    ];
    $tens = [
        2 => 'Twenty', 3 => 'Thirty', 4 => 'Forty', 5 => 'Fifty',
        6 => 'Sixty', 7 => 'Seventy', 8 => 'Eighty', 9 => 'Ninety'
    ];
    $num = (int)floor($amount);
    $cents = (int)round(($amount - $num) * 100);

    if ($num === 0) {
        $words = 'Zero Pesos';
    } else {
        $convertGroup = function($n) use ($ones, $tens) {
            $str = '';
            if ($n >= 100) {
                $h = (int)floor($n / 100);
                $str .= $ones[$h] . ' Hundred ';
                $n %= 100;
            }
            if ($n >= 20) {
                $t = (int)floor($n / 10);
                $str .= $tens[$t] . ' ';
                $n %= 10;
            }
            if ($n > 0) {
                $str .= $ones[$n] . ' ';
            }
            return trim($str);
        };
        $groups = [];
        $scales = ['', 'Thousand', 'Million', 'Billion'];
        $scaleIndex = 0;
        while ($num > 0 && $scaleIndex < count($scales)) {
            $rem = $num % 1000;
            if ($rem > 0) {
                $part = $convertGroup($rem);
                if (!empty($scales[$scaleIndex])) {
                    $part .= ' ' . $scales[$scaleIndex];
                }
                array_unshift($groups, $part);
            }
            $num = (int)floor($num / 1000);
            $scaleIndex++;
        }
        $words = implode(' ', $groups) . ' Pesos';
    }
    if ($cents > 0) {
        $words .= ' and ' . $cents . '/100';
    } else {
        $words .= ' Only';
    }
    return $words;
}

$amount = (float)($d['amount'] ?? 0);
$amountWords = amountToWords($amount);
$parishName = !empty($d['chapel_name']) && $d['chapel_name'] !== 'Cathedral' ? $d['chapel_name'] : PARISH_NAME;
$donorName = !empty($d['donor_name']) ? $d['donor_name'] : 'Anonymous Donor';
$purpose = !empty($d['purpose']) ? $d['purpose'] : 'General Parish Donation & Support';
$dateFormatted = !empty($d['date_received']) ? date('F d, Y', strtotime($d['date_received'])) : date('F d, Y');
$loggedBy = !empty($d['logged_by_name']) ? $d['logged_by_name'] : 'Authorized Parish Staff';
$refId = $d['reference_id'] ?? ('DON-' . $id);
$paymentMethod = !empty($d['payment_method']) ? $d['payment_method'] : 'Cash';

// Extract serial number digits
preg_match('/\d+$/', $refId, $matches);
$serialNumber = !empty($matches[0]) ? str_pad($matches[0], 5, '0', STR_PAD_LEFT) : str_pad((string)$id, 5, '0', STR_PAD_LEFT);

// Format diocese text
$dioceseRaw = defined('DIOCESE_NAME') ? DIOCESE_NAME : 'Diocese of Cavite';
if (preg_match('/Diocese\s+of\s+([A-Za-z\s]+)/i', $dioceseRaw, $dm)) {
    $dioceseDisplay = 'DIOCESE OF ' . strtoupper(trim($dm[1]));
} else {
    $dioceseDisplay = strtoupper($dioceseRaw);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ecclesiastical Receipt — <?php echo htmlspecialchars($refId); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@500;600;700&family=EB+Garamond:ital,wght@0,400;0,500;0,600;0,700;1,400;1,600&family=Inter:wght@400;500;600;700&family=UnifrakturMaguntia&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'EB Garamond', Garamond, 'Times New Roman', serif;
            background: #edece8;
            color: #1a1a1a;
            padding: 30px 15px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        /* ── SCREEN CONTROLS ────────────────────────────────────────── */
        .screen-toolbar {
            width: 100%;
            max-width: 820px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            background: #ffffff;
            padding: 12px 20px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.06);
            border: 1px solid #dcd7ce;
        }
        .toolbar-info {
            font-family: 'Inter', system-ui, sans-serif;
            font-size: 13px;
            color: #4b5563;
        }
        .toolbar-info strong {
            color: #111827;
        }
        .toolbar-actions {
            display: flex;
            gap: 10px;
        }
        .btn-tb {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 18px;
            border-radius: 6px;
            font-family: 'Inter', system-ui, sans-serif;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            border: none;
            transition: all 0.15s ease;
        }
        .btn-tb-primary {
            background: #8a6010;
            color: #ffffff;
        }
        .btn-tb-primary:hover {
            background: #73500c;
        }
        .btn-tb-back {
            background: #f3f4f6;
            color: #374151;
            border: 1px solid #d1d5db;
        }
        .btn-tb-back:hover {
            background: #e5e7eb;
        }

        /* ── ECCLESIASTICAL RECEIPT SLIP ────────────────────────────── */
        .receipt-slip-container {
            width: 100%;
            max-width: 820px;
            background: #ffffff;
            border: 1.5px solid #222222;
            padding: 24px 28px 22px;
            position: relative;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }

        /* ── TOP HEADER ROW ─────────────────────────────────────────── */
        .header-row {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 12px;
        }
        .header-crest {
            width: 48px;
            height: 48px;
            object-fit: contain;
            flex-shrink: 0;
        }
        .header-text-block {
            flex: 1;
        }
        .church-title-line {
            display: flex;
            align-items: baseline;
            flex-wrap: wrap;
            gap: 8px;
            line-height: 1.15;
        }
        .ifi-main-name {
            font-family: 'UnifrakturMaguntia', 'EB Garamond', serif;
            font-size: 23px;
            font-weight: 400;
            color: #111111;
            letter-spacing: 0.5px;
        }
        .ifi-pipe {
            font-size: 18px;
            color: #555555;
            font-weight: 300;
        }
        .ifi-diocese {
            font-family: 'Cinzel', serif;
            font-size: 13px;
            font-weight: 600;
            letter-spacing: 1px;
            color: #222222;
        }
        .parish-line {
            display: flex;
            align-items: baseline;
            margin-top: 6px;
            font-size: 14px;
            gap: 8px;
        }
        .parish-label {
            font-family: 'Cinzel', serif;
            font-size: 11.5px;
            font-weight: 700;
            letter-spacing: 1px;
            color: #222222;
        }
        .parish-underline {
            flex: 1;
            border-bottom: 1px solid #111111;
            font-family: 'EB Garamond', serif;
            font-size: 15px;
            font-weight: 600;
            color: #111111;
            padding-bottom: 1px;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        /* ── MAIN RECEIPT GRID ──────────────────────────────────────── */
        .receipt-body-grid {
            display: grid;
            grid-template-columns: 210px 1fr;
            gap: 22px;
            align-items: stretch;
            margin-top: 6px;
        }

        /* Left Column */
        .left-col {
            display: flex;
            flex-direction: column;
            justify-content: flex-start;
        }
        .date-line-row {
            display: flex;
            align-items: baseline;
            gap: 6px;
            font-size: 14px;
            margin-bottom: 12px;
        }
        .date-label {
            font-family: 'EB Garamond', serif;
            font-size: 14px;
            color: #222222;
            font-weight: 500;
        }
        .date-val {
            flex: 1;
            border-bottom: 1px solid #111111;
            font-family: 'EB Garamond', serif;
            font-size: 14.5px;
            font-weight: 600;
            padding-bottom: 1px;
            color: #111111;
        }
        .eccl-title {
            font-family: 'DM Sans', 'Arial', sans-serif;
            font-size: 21px;
            font-weight: 800;
            line-height: 1.15;
            color: #1e293b;
            letter-spacing: -0.2px;
            margin-bottom: 14px;
        }
        .eccl-emblem-wrap {
            display: flex;
            justify-content: center;
            align-items: center;
            flex: 1;
            min-height: 120px;
            padding: 8px 0;
        }
        .eccl-cross-svg {
            width: 105px;
            height: 125px;
            color: #1e293b;
        }

        /* Right Boxed Details */
        .boxed-particulars {
            border: 1.5px solid #222222;
            padding: 16px 20px;
            background: #ffffff;
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        /* Received From */
        .rec-from-row {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .rec-from-label {
            font-family: 'EB Garamond', serif;
            font-size: 16.5px;
            color: #111111;
            white-space: nowrap;
        }
        .rec-from-box {
            flex: 1;
            border: 1.2px solid #222222;
            min-height: 32px;
            padding: 4px 10px;
            display: flex;
            align-items: center;
            font-family: 'EB Garamond', serif;
            font-size: 16px;
            font-weight: 700;
            color: #111111;
            letter-spacing: 0.3px;
        }

        /* Amount of Words */
        .amount-words-row {
            display: flex;
            align-items: baseline;
            gap: 8px;
            line-height: 1.4;
        }
        .amount-words-label {
            font-family: 'EB Garamond', serif;
            font-size: 15.5px;
            color: #111111;
            white-space: nowrap;
        }
        .amount-words-line {
            flex: 1;
            border-bottom: 1px solid #111111;
            font-family: 'EB Garamond', serif;
            font-size: 15.5px;
            font-style: italic;
            font-weight: 600;
            color: #111111;
            padding-bottom: 1px;
            display: flex;
            justify-content: space-between;
            align-items: baseline;
        }
        .amount-words-line .closing-paren {
            font-style: normal;
            font-weight: 400;
            margin-left: 4px;
        }

        /* Numerical Figures */
        .amount-figures-row {
            display: flex;
            justify-content: flex-end;
            margin-top: -6px;
            margin-bottom: -4px;
        }
        .amount-figures-box {
            font-family: 'DM Sans', sans-serif;
            font-size: 17px;
            font-weight: 800;
            color: #111111;
            letter-spacing: 0.5px;
        }

        /* In Payment For */
        .payment-for-row {
            display: flex;
            align-items: baseline;
            gap: 8px;
        }
        .payment-for-label {
            font-family: 'EB Garamond', serif;
            font-size: 15.5px;
            color: #111111;
            white-space: nowrap;
        }
        .payment-for-line {
            flex: 1;
            border-bottom: 1px solid #111111;
            font-family: 'EB Garamond', serif;
            font-size: 15px;
            font-weight: 600;
            color: #111111;
            padding-bottom: 1px;
        }

        /* Continuation Notes Line */
        .continuation-row {
            display: flex;
            align-items: baseline;
            margin-top: -4px;
        }
        .continuation-line {
            flex: 1;
            border-bottom: 1px solid #111111;
            font-family: 'EB Garamond', serif;
            font-size: 13.5px;
            color: #4b5563;
            padding-bottom: 1px;
        }

        /* ── BOTTOM ROW: SIGNATURE & SERIAL NUMBER ──────────────────── */
        .receipt-bottom-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            margin-top: 18px;
            padding: 0 4px;
        }
        .received-by-wrap {
            display: flex;
            flex-direction: column;
            width: 380px;
        }
        .received-by-line-wrap {
            display: flex;
            align-items: baseline;
            gap: 8px;
        }
        .received-by-label {
            font-family: 'EB Garamond', serif;
            font-size: 16px;
            font-weight: 700;
            color: #111111;
            white-space: nowrap;
        }
        .received-by-underline {
            flex: 1;
            border-bottom: 1px solid #111111;
            font-family: 'EB Garamond', serif;
            font-size: 15px;
            font-weight: 600;
            color: #111111;
            text-align: center;
            padding-bottom: 2px;
        }
        .auth-sig-subtitle {
            text-align: center;
            font-family: 'EB Garamond', serif;
            font-size: 11.5px;
            font-style: italic;
            color: #555555;
            margin-top: 3px;
            margin-left: 90px;
        }

        /* Red Serial Number */
        .serial-number-wrap {
            display: flex;
            align-items: baseline;
            gap: 8px;
        }
        .serial-label {
            font-family: 'EB Garamond', serif;
            font-size: 18px;
            font-weight: 700;
            color: #111111;
        }
        .serial-digits {
            font-family: 'Courier New', Courier, monospace;
            font-size: 24px;
            font-weight: 800;
            color: #dc2626;
            letter-spacing: 2px;
        }

        /* ── PRINT MEDIA RULES ──────────────────────────────────────── */
        @page {
            size: auto;
            margin: 10mm;
        }
        @media print {
            body {
                background: #ffffff;
                padding: 0;
                color: #000000;
            }
            .screen-toolbar {
                display: none !important;
            }
            .receipt-slip-container {
                box-shadow: none !important;
                border: 1.5px solid #000000 !important;
                margin: 0 auto;
                max-width: 100% !important;
            }
            .serial-digits {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
                color: #dc2626 !important;
            }
        }
    </style>
</head>
<body>

    <!-- ── SCREEN NAVIGATION TOOLBAR ────────────────────────────────── -->
    <div class="screen-toolbar">
        <div class="toolbar-info">
            <strong>Ecclesiastical Receipt</strong> &bull; Ref: <?php echo htmlspecialchars($refId); ?>
        </div>
        <div class="toolbar-actions">
            <button class="btn-tb btn-tb-primary" onclick="window.print()">
                <i class="fa-solid fa-print"></i> Print Receipt
            </button>
            <a href="../../dashboard.php?section=donations" class="btn-tb btn-tb-back">
                <i class="fa-solid fa-arrow-left"></i> Back to Donations
            </a>
        </div>
    </div>

    <!-- ── ECCLESIASTICAL RECEIPT SLIP ──────────────────────────────── -->
    <div class="receipt-slip-container">

        <!-- Top Header -->
        <div class="header-row">
            <img src="../../assets/img/2323.png" alt="IFI Coat of Arms" class="header-crest">
            <div class="header-text-block">
                <div class="church-title-line">
                    <span class="ifi-main-name">Iglesia Filipina Independiente</span>
                    <span class="ifi-pipe">|</span>
                    <span class="ifi-diocese"><?php echo htmlspecialchars($dioceseDisplay); ?></span>
                </div>
                <div class="parish-line">
                    <span class="parish-label">PARISH OF</span>
                    <span class="parish-underline"><?php echo htmlspecialchars($parishName); ?></span>
                </div>
            </div>
        </div>

        <!-- Body Grid -->
        <div class="receipt-body-grid">

            <!-- Left Column -->
            <div class="left-col">
                <div class="date-line-row">
                    <span class="date-label">Date</span>
                    <span class="date-val"><?php echo htmlspecialchars($dateFormatted); ?></span>
                </div>
                <div class="eccl-title">
                    <div>Ecclesiastical</div>
                    <div>Receipt</div>
                </div>
                <div class="eccl-emblem-wrap">
                    <!-- Ornate Ecclesiastical Cross Emblem -->
                    <svg class="eccl-cross-svg" viewBox="0 0 100 130" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <!-- Main Vertical Shaft -->
                        <line x1="50" y1="12" x2="50" y2="118" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
                        <!-- Main Horizontal Crossbar -->
                        <line x1="16" y1="48" x2="84" y2="48" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>

                        <!-- Vertical Terminal Flourishes Top & Bottom -->
                        <path d="M42 16 C46 8, 54 8, 58 16" stroke="currentColor" stroke-width="2" fill="none"/>
                        <path d="M42 114 C46 122, 54 122, 58 114" stroke="currentColor" stroke-width="2" fill="none"/>
                        <!-- Horizontal Terminal Flourishes Left & Right -->
                        <path d="M20 40 C12 44, 12 52, 20 56" stroke="currentColor" stroke-width="2" fill="none"/>
                        <path d="M80 40 C88 44, 88 52, 80 56" stroke="currentColor" stroke-width="2" fill="none"/>

                        <!-- Central Crossbar Accents -->
                        <line x1="38" y1="28" x2="62" y2="28" stroke="currentColor" stroke-width="2"/>
                        <line x1="38" y1="68" x2="62" y2="68" stroke="currentColor" stroke-width="2"/>

                        <!-- Corner Flourishes / Celtic Scrollwork -->
                        <!-- Top-Left -->
                        <path d="M30 48 C30 32, 40 28, 50 38" stroke="currentColor" stroke-width="2" fill="none"/>
                        <!-- Top-Right -->
                        <path d="M70 48 C70 32, 60 28, 50 38" stroke="currentColor" stroke-width="2" fill="none"/>
                        <!-- Bottom-Left -->
                        <path d="M30 48 C30 64, 40 68, 50 58" stroke="currentColor" stroke-width="2" fill="none"/>
                        <!-- Bottom-Right -->
                        <path d="M70 48 C70 64, 60 68, 50 58" stroke="currentColor" stroke-width="2" fill="none"/>

                        <!-- Radiating Quad Accents -->
                        <circle cx="50" cy="48" r="3.5" fill="currentColor"/>
                    </svg>
                </div>
            </div>

            <!-- Right Boxed Section -->
            <div class="boxed-particulars">

                <!-- Received from -->
                <div class="rec-from-row">
                    <span class="rec-from-label">Received from</span>
                    <div class="rec-from-box">
                        <?php echo htmlspecialchars($donorName); ?>
                    </div>
                </div>

                <!-- The amount of -->
                <div class="amount-words-row">
                    <span class="amount-words-label">the amount of</span>
                    <div class="amount-words-line">
                        <span><?php echo htmlspecialchars($amountWords); ?></span>
                        <span class="closing-paren">)</span>
                    </div>
                </div>

                <!-- Figure in parentheses -->
                <div class="amount-figures-row">
                    <div class="amount-figures-box">
                        (&nbsp;₱ <?php echo number_format($amount, 2); ?>&nbsp;)
                    </div>
                </div>

                <!-- In payment for -->
                <div class="payment-for-row">
                    <span class="payment-for-label">in payment for</span>
                    <div class="payment-for-line">
                        <?php echo htmlspecialchars($purpose); ?>
                    </div>
                </div>

                <!-- Continuation Line -->
                <div class="continuation-row">
                    <div class="continuation-line">
                        Payment Method: <?php echo htmlspecialchars($paymentMethod); ?> &bull; Ref: <?php echo htmlspecialchars($refId); ?>
                    </div>
                </div>

            </div>
        </div>

        <!-- Bottom Signature & Serial Number -->
        <div class="receipt-bottom-row">
            <div class="received-by-wrap">
                <div class="received-by-line-wrap">
                    <span class="received-by-label">Received by:</span>
                    <span class="received-by-underline"><?php echo htmlspecialchars($loggedBy); ?></span>
                </div>
                <div class="auth-sig-subtitle">Authorized Signature</div>
            </div>

            <div class="serial-number-wrap">
                <span class="serial-label">No.</span>
                <span class="serial-digits"><?php echo htmlspecialchars($serialNumber); ?></span>
            </div>
        </div>

    </div>

</body>
</html>
