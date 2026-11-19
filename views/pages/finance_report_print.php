<?php
require_once __DIR__ . '/../../config/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../index.php?error=session');
    exit();
}

// Role guard
$_role = $_SESSION['role'] ?? '';
if (!in_array($_role, ['Admin','Cathedral Admin','Chapel Admin'])) {
    header('Location: ../../dashboard.php');
    exit();
}

$userChapelId = !in_array($_role, ['Admin', 'Cathedral Admin'], true) ? (int)($_SESSION['chapel_id'] ?? 0) : null;
$whereDonation = $userChapelId ? " AND d.chapel_id = $userChapelId" : "";
$whereRecord = $userChapelId ? " AND sr.chapel_id = $userChapelId" : "";
$whereEvent = $userChapelId ? " AND e.chapel_id = $userChapelId" : "";

$from = (isset($_GET['from']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['from'])) ? $_GET['from'] : date('Y-m-01');
$to   = (isset($_GET['to']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['to'])) ? $_GET['to'] : date('Y-m-d');
$p1 = $from . ' 00:00:00';
$p2 = $to . ' 23:59:59';

// 1. Overview Stats (Period)
if ($userChapelId) {
    $stats_sql = "SELECT 
        (SELECT SUM(amount) FROM donations d WHERE date_received BETWEEN ? AND ? AND is_archived=0 $whereDonation) as total_donations,
        0 as total_merch_sales,
        (SELECT COUNT(*) FROM sacramental_records sr WHERE date_added BETWEEN ? AND ? AND is_archived=0 $whereRecord) as records_added,
        (SELECT COUNT(*) FROM events e WHERE start_time BETWEEN ? AND ? AND status='Completed' AND is_archived=0 $whereEvent) as events_completed
    ";
    $stmt = $conn->prepare($stats_sql);
    $stmt->bind_param('ssssss', $from, $to, $p1, $p2, $p1, $p2);
} else {
    $stats_sql = "SELECT 
        (SELECT SUM(amount) FROM donations d WHERE date_received BETWEEN ? AND ? AND is_archived=0) as total_donations,
        (SELECT SUM(total_price) FROM merchandise_sales WHERE sale_date BETWEEN ? AND ?) as total_merch_sales,
        (SELECT COUNT(*) FROM sacramental_records sr WHERE date_added BETWEEN ? AND ? AND is_archived=0) as records_added,
        (SELECT COUNT(*) FROM events e WHERE start_time BETWEEN ? AND ? AND status='Completed' AND is_archived=0) as events_completed
    ";
    $stmt = $conn->prepare($stats_sql);
    $stmt->bind_param('ssssssss', $from, $to, $p1, $p2, $p1, $p2, $p1, $p2);
}
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();
$stmt->close();
$grand_total = ($stats['total_donations'] ?? 0) + ($stats['total_merch_sales'] ?? 0);

// Helper function to fetch
function fetch_all_params($conn, $sql, $params) {
    $stmt = $conn->prepare($sql);
    if($stmt) {
        $stmt->bind_param(str_repeat('s', count($params)), ...$params);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $res;
    }
    return [];
}

// Existing Breakdowns
$events_by_type = fetch_all_params($conn, "SELECT event_type, COUNT(*) as cnt FROM events e WHERE start_time BETWEEN ? AND ? AND is_archived=0 $whereEvent GROUP BY event_type ORDER BY cnt DESC", [$p1, $p2]);
$events_by_status = fetch_all_params($conn, "SELECT status, COUNT(*) as cnt FROM events e WHERE start_time BETWEEN ? AND ? AND is_archived=0 $whereEvent GROUP BY status ORDER BY cnt DESC", [$p1, $p2]);
$donations_breakdown = fetch_all_params($conn, "SELECT COALESCE(c.chapel_name, 'Main Cathedral') as chapel, payment_method, SUM(amount) as total, COUNT(*) as cnt FROM donations d LEFT JOIN chapels c ON d.chapel_id = c.chapel_id WHERE date_received BETWEEN ? AND ? AND d.is_archived=0 $whereDonation GROUP BY d.chapel_id, payment_method ORDER BY total DESC", [$from, $to]);
$merch_breakdown = $userChapelId ? [] : fetch_all_params($conn, "SELECT mi.name, SUM(ms.quantity) as qty, SUM(ms.total_price) as total FROM merchandise_sales ms JOIN merchandise_items mi ON ms.item_id = mi.id WHERE sale_date BETWEEN ? AND ? GROUP BY ms.item_id ORDER BY total DESC", [$p1, $p2]);
$records_breakdown = fetch_all_params($conn, "SELECT record_type, COUNT(*) as cnt FROM sacramental_records sr WHERE date_added BETWEEN ? AND ? AND is_archived=0 $whereRecord GROUP BY record_type ORDER BY cnt DESC", [$p1, $p2]);

// Analytics
$top_donors = fetch_all_params($conn, "SELECT donor_name, SUM(amount) as total FROM donations d WHERE date_received BETWEEN ? AND ? AND is_archived=0 $whereDonation GROUP BY donor_name ORDER BY total DESC LIMIT 10", [$from, $to]);
$voided_donations = fetch_all_params($conn, "SELECT donor_name, amount, date_received, void_reason FROM donations d WHERE is_archived=1 AND date_received BETWEEN ? AND ? $whereDonation ORDER BY date_received DESC", [$from, $to]);
$ticketSql = $userChapelId ? "SELECT status, COUNT(*) as cnt FROM tickets WHERE created_at BETWEEN ? AND ? AND chapel_id = $userChapelId GROUP BY status" : "SELECT status, COUNT(*) as cnt FROM tickets WHERE created_at BETWEEN ? AND ? GROUP BY status";
$tickets_status = fetch_all_params($conn, $ticketSql, [$p1, $p2]);
$auditSql = $userChapelId
    ? "SELECT al.performed_by_name, al.action, al.module, COUNT(*) as cnt FROM audit_logs al JOIN users u ON al.performed_by = u.id WHERE al.created_at BETWEEN ? AND ? AND u.chapel_id = $userChapelId GROUP BY al.performed_by_name, al.action, al.module ORDER BY cnt DESC LIMIT 15"
    : "SELECT performed_by_name, action, module, COUNT(*) as cnt FROM audit_logs WHERE created_at BETWEEN ? AND ? GROUP BY performed_by_name, action, module ORDER BY cnt DESC LIMIT 15";
$staff_activity = fetch_all_params($conn, $auditSql, [$p1, $p2]);

// Ledgers
$donations_ledger = fetch_all_params($conn, "SELECT donation_id, donor_name, amount, purpose, payment_method, date_received FROM donations d WHERE date_received BETWEEN ? AND ? AND is_archived=0 $whereDonation ORDER BY date_received DESC", [$from, $to]);
$merch_ledger = $userChapelId ? [] : fetch_all_params($conn, "SELECT ms.id, mi.name as item, ms.buyer_name, ms.quantity, ms.total_price, ms.sale_date FROM merchandise_sales ms JOIN merchandise_items mi ON ms.item_id=mi.id WHERE ms.sale_date BETWEEN ? AND ? ORDER BY ms.sale_date DESC", [$p1, $p2]);
$records_ledger = fetch_all_params($conn, "SELECT record_type, COALESCE(person_name,'N/A') as person_name, event_date, book_number, page_number FROM sacramental_records sr WHERE date_added BETWEEN ? AND ? AND is_archived=0 $whereRecord ORDER BY date_added DESC", [$p1, $p2]);
$certSql = $userChapelId 
    ? "SELECT ci.certificate_no, ci.issued_to, ci.purpose, ci.issued_at FROM certificate_issuances ci JOIN sacramental_records sr ON ci.record_id = sr.record_id WHERE ci.issued_at BETWEEN ? AND ? AND sr.chapel_id = $userChapelId ORDER BY ci.issued_at DESC"
    : "SELECT certificate_no, issued_to, purpose, issued_at FROM certificate_issuances WHERE issued_at BETWEEN ? AND ? ORDER BY issued_at DESC";
$certs_ledger = fetch_all_params($conn, $certSql, [$p1, $p2]);

// Overall Stats (All time)
$overall_sql = "SELECT 
    (SELECT SUM(amount) FROM donations d WHERE is_archived=0 $whereDonation) as total_donations_all,
    " . ($userChapelId ? "0" : "(SELECT SUM(total_price) FROM merchandise_sales)") . " as total_merch_sales_all,
    (SELECT COUNT(*) FROM sacramental_records sr WHERE is_archived=0 $whereRecord) as records_added_all,
    (SELECT COUNT(*) FROM events e WHERE status='Completed' AND is_archived=0 $whereEvent) as events_completed_all";
$overall_stmt = $conn->prepare($overall_sql);
$overall_stmt->execute();
$overall = $overall_stmt->get_result()->fetch_assoc();
$overall_stmt->close();
$overall_grand_total = ($overall['total_donations_all'] ?? 0) + ($overall['total_merch_sales_all'] ?? 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Detailed Financial & Activity Report</title>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@600;700&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'DM Sans', sans-serif; padding: 30px 40px; color: #1e293b; background: white; margin: 0; }
        .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #1e293b; padding-bottom: 16px; }
        .header h1 { font-family: 'Cinzel', serif; font-size: 24px; color: #b8882a; margin: 0 0 6px; }
        .header p { font-size: 13px; color: #64748b; margin: 0; }
        .report-meta { display: flex; justify-content: space-between; margin-bottom: 24px; font-size: 13px; color: #475569; }
        .section-title { font-family: 'Cinzel', serif; font-size: 17px; margin: 26px 0 12px; border-left: 4px solid #b8882a; padding-left: 10px; page-break-after: avoid; }
        .stats-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .stat-label { font-size: 12px; text-transform: uppercase; color: #64748b; letter-spacing: 0.08em; font-weight: 600; }
        .stat-value { font-size: 22px; font-weight: 700; margin-top: 4px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; margin-bottom: 18px; page-break-inside: avoid; }
        th { text-align: left; background: #f8fafc; padding: 10px 14px; font-size: 12px; font-weight: 700; color: #475569; border-bottom: 1px solid #e2e8f0; text-transform: uppercase; letter-spacing: 0.04em; }
        td { padding: 10px 14px; font-size: 13px; border-bottom: 1px solid #f1f5f9; }
        .text-right { text-align: right; }
        .font-bold { font-weight: 600; }
        ul.data-list { list-style: none; padding: 0; margin: 0; }
        ul.data-list li { padding: 8px 0; border-bottom: 1px dashed #e2e8f0; display: flex; justify-content: space-between; font-size: 13.5px; }
        ul.data-list li.total { border-bottom: none; border-top: 2px solid #e2e8f0; font-weight: 700; font-size: 16px; margin-top: 4px; padding-top: 12px; color: #b8882a; }
        .footer { margin-top: 40px; text-align: center; font-size: 12px; color: #94a3b8; border-top: 1px solid #f1f5f9; padding-top: 16px; page-break-inside: avoid; }
        .two-col-tables { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; }
        .page-break { page-break-before: always; margin-top: 30px; }
        .text-red-500 { color: #ef4444; }

        /* Report Customization Bar (.no-print) */
        .config-toolbar {
            background: #0f172a;
            color: #f8fafc;
            padding: 16px 20px;
            border-radius: 12px;
            margin-bottom: 24px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }
        .config-toolbar h3 {
            margin: 0 0 10px;
            font-size: 14px;
            font-weight: 700;
            color: #c9a84c;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .config-options {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(210px, 1fr));
            gap: 8px 16px;
            margin-bottom: 14px;
        }
        .config-options label {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 12px;
            color: #cbd5e1;
            cursor: pointer;
            user-select: none;
        }
        .config-options input[type="checkbox"] {
            accent-color: #c9a84c;
            width: 15px;
            height: 15px;
            cursor: pointer;
        }
        .config-actions {
            display: flex;
            gap: 10px;
            align-items: center;
            justify-content: flex-between;
            border-top: 1px solid rgba(255,255,255,0.1);
            padding-top: 12px;
        }
        .btn-tb {
            padding: 6px 14px;
            font-size: 12px;
            font-weight: 600;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-tb-primary { background: #c9a84c; color: #0f172a; }
        .btn-tb-primary:hover { background: #d4af37; }
        .btn-tb-ghost { background: rgba(255,255,255,0.1); color: #f8fafc; }
        .btn-tb-ghost:hover { background: rgba(255,255,255,0.2); }

        .hidden-section { display: none !important; }

        @media print {
            .no-print { display: none !important; }
            body { padding: 0; }
            .page-break { margin-top: 0; }
            .hidden-section { display: none !important; }
        }
    </style>
</head>
<body>

    <!-- ── Interactive Report Customizer Toolbar (Screen Only) ── -->
    <div class="config-toolbar no-print">
        <h3>
            <span><i class="fa-solid fa-sliders"></i> Customize Printable Report Sections</span>
            <span style="font-size:11px; font-weight:normal; color:#94a3b8;">Uncheck sections to exclude them before printing</span>
        </h3>
        
        <div class="config-options">
            <label><input type="checkbox" class="sec-toggle" data-target="sec-revenue" checked> Revenue Summary</label>
            <label><input type="checkbox" class="sec-toggle" data-target="sec-donations-breakdown" checked> Donations & Top Donors</label>
            <label><input type="checkbox" class="sec-toggle" data-target="sec-donations-ledger" checked> Individual Donations Ledger</label>
            <?php if (!$userChapelId): ?>
            <label><input type="checkbox" class="sec-toggle" data-target="sec-merch" checked> Merchandise Sales & Ledger</label>
            <?php endif; ?>
            <label><input type="checkbox" class="sec-toggle" data-target="sec-events" checked> Events & Records Summary</label>
            <label><input type="checkbox" class="sec-toggle" data-target="sec-records-ledger" checked> Sacramental Records Ledger</label>
            <label><input type="checkbox" class="sec-toggle" data-target="sec-certs-ledger" checked> Certificate Issuances</label>
            <label><input type="checkbox" class="sec-toggle" data-target="sec-tickets-voids" checked> Tickets & Voided Ledger</label>
            <label><input type="checkbox" class="sec-toggle" data-target="sec-staff-activity" checked> Staff Activity Logs</label>
            <label><input type="checkbox" class="sec-toggle" data-target="sec-overall" checked> All-Time Overall Stats</label>
        </div>

        <div class="config-actions">
            <div style="display:flex; gap:8px;">
                <button type="button" class="btn-tb btn-tb-ghost" onclick="toggleAllSections(true)">Select All</button>
                <button type="button" class="btn-tb btn-tb-ghost" onclick="toggleAllSections(false)">Deselect All</button>
            </div>
            <div style="margin-left: auto; display:flex; gap:8px;">
                <button type="button" class="btn-tb btn-tb-primary" onclick="window.print()">🖨 Print Selected Report</button>
                <button type="button" class="btn-tb btn-tb-ghost" onclick="try { window.close(); } catch(e){} setTimeout(() => { window.location.href = '../../dashboard.php?section=reports'; }, 100);">Close</button>
            </div>
        </div>
    </div>

    <!-- ── Report Header ── -->
    <div class="header">
        <h1><?php echo htmlspecialchars(PARISH_NAME); ?> Management System</h1>
        <p>Official Detailed Financial &amp; Activity Report</p>
    </div>

    <div class="report-meta">
        <div>
            <strong>Reporting Period:</strong> <?php echo date('M d, Y', strtotime($from)); ?> – <?php echo date('M d, Y', strtotime($to)); ?>
        </div>
        <div>
            <strong>Generated On:</strong> <?php echo date('M d, Y h:i A'); ?>
        </div>
    </div>

    <!-- ── 1. Revenue Summary ── -->
    <div id="sec-revenue" class="report-block">
        <div class="section-title">Revenue Summary</div>
        <ul class="data-list">
            <li><span>Donations Received:</span> <strong>₱<?php echo number_format($stats['total_donations'] ?? 0, 2); ?></strong></li>
            <li><span>Merchandise Sales:</span> <strong>₱<?php echo number_format($stats['total_merch_sales'] ?? 0, 2); ?></strong></li>
            <li class="total"><span>Grand Total Revenue:</span> <strong>₱<?php echo number_format($grand_total, 2); ?></strong></li>
        </ul>
    </div>

    <!-- ── 2. Donations Breakdown & Top Donors ── -->
    <div id="sec-donations-breakdown" class="report-block">
        <div class="two-col-tables" style="margin-top: 24px;">
            <div>
                <div class="section-title" style="margin-top: 0;">Donations Breakdown</div>
                <?php if (empty($donations_breakdown)): ?>
                    <p style="font-size:13px; color:#94a3b8;">No donations recorded in this period.</p>
                <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Location / Method</th>
                            <th class="text-right">Txns</th>
                            <th class="text-right">Total (₱)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($donations_breakdown as $db): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($db['chapel']); ?> - <?php echo htmlspecialchars($db['payment_method']); ?></td>
                            <td class="text-right"><?php echo $db['cnt']; ?></td>
                            <td class="text-right font-bold"><?php echo number_format($db['total'], 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
            <div>
                <div class="section-title" style="margin-top: 0;">Top Donors</div>
                <?php if (empty($top_donors)): ?>
                    <p style="font-size:13px; color:#94a3b8;">No donors found.</p>
                <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Donor Name</th>
                            <th class="text-right">Total (₱)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($top_donors as $td): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($td['donor_name']); ?></td>
                            <td class="text-right font-bold"><?php echo number_format($td['total'], 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ── 3. Individual Donations Ledger ── -->
    <div id="sec-donations-ledger" class="report-block">
        <div class="section-title">Individual Donation Transactions</div>
        <?php if (empty($donations_ledger)): ?>
            <p style="font-size:13px; color:#94a3b8;">No transactions found.</p>
        <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Donor Name</th>
                    <th>Date</th>
                    <th>Method</th>
                    <th>Purpose</th>
                    <th class="text-right">Amount (₱)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($donations_ledger as $dl): ?>
                <tr>
                    <td>#<?php echo $dl['donation_id']; ?></td>
                    <td><?php echo htmlspecialchars($dl['donor_name']); ?></td>
                    <td><?php echo htmlspecialchars($dl['date_received']); ?></td>
                    <td><?php echo htmlspecialchars($dl['payment_method']); ?></td>
                    <td><?php echo htmlspecialchars($dl['purpose']); ?></td>
                    <td class="text-right font-bold"><?php echo number_format($dl['amount'], 2); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <?php if (!$userChapelId): ?>
    <!-- ── 4. Merchandise Breakdown & Sales Ledger ── -->
    <div id="sec-merch" class="report-block">
        <div class="section-title" style="margin-top: 24px;">Merchandise Breakdown</div>
        <?php if (empty($merch_breakdown)): ?>
            <p style="font-size:13px; color:#94a3b8;">No merchandise sales.</p>
        <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Item Name</th>
                    <th class="text-right">Qty</th>
                    <th class="text-right">Total (₱)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($merch_breakdown as $mb): ?>
                <tr>
                    <td><?php echo htmlspecialchars($mb['name']); ?></td>
                    <td class="text-right"><?php echo $mb['qty']; ?></td>
                    <td class="text-right font-bold"><?php echo number_format($mb['total'], 2); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>

        <div class="section-title">Individual Merchandise Sales Ledger</div>
        <?php if (empty($merch_ledger)): ?>
            <p style="font-size:13px; color:#94a3b8;">No sales found.</p>
        <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Buyer</th>
                    <th>Item</th>
                    <th>Date</th>
                    <th class="text-right">Qty</th>
                    <th class="text-right">Total (₱)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($merch_ledger as $ml): ?>
                <tr>
                    <td>#<?php echo $ml['id']; ?></td>
                    <td><?php echo htmlspecialchars($ml['buyer_name']); ?></td>
                    <td><?php echo htmlspecialchars($ml['item']); ?></td>
                    <td><?php echo htmlspecialchars($ml['sale_date']); ?></td>
                    <td class="text-right"><?php echo $ml['quantity']; ?></td>
                    <td class="text-right font-bold"><?php echo number_format($ml['total_price'], 2); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- ── 5. Events & Sacramental Summary ── -->
    <div id="sec-events" class="report-block">
        <div class="section-title">Events &amp; Sacramental Summary</div>
        <ul class="data-list">
            <li><span>Events Successfully Completed:</span> <strong><?php echo number_format($stats['events_completed'] ?? 0); ?></strong></li>
            <li><span>Sacramental Records Encoded:</span> <strong><?php echo number_format($stats['records_added'] ?? 0); ?></strong></li>
        </ul>

        <div class="two-col-tables" style="margin-top: 20px;">
            <div>
                <div class="section-title" style="margin-top: 0;">Events by Type</div>
                <?php if (empty($events_by_type)): ?>
                    <p style="font-size:13px; color:#94a3b8;">No events recorded.</p>
                <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Event Type</th>
                            <th class="text-right">Count</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($events_by_type as $ebt): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($ebt['event_type']); ?></td>
                            <td class="text-right"><?php echo $ebt['cnt']; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
            <div>
                <div class="section-title" style="margin-top: 0;">Events by Status</div>
                <?php if (empty($events_by_status)): ?>
                    <p style="font-size:13px; color:#94a3b8;">No events recorded.</p>
                <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Status</th>
                            <th class="text-right">Count</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($events_by_status as $ebs): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($ebs['status']); ?></td>
                            <td class="text-right"><?php echo $ebs['cnt']; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ── 6. Sacramental Records Ledger ── -->
    <div id="sec-records-ledger" class="report-block">
        <div class="section-title">Individual Sacramental Records</div>
        <?php if (empty($records_ledger)): ?>
            <p style="font-size:13px; color:#94a3b8;">No records found.</p>
        <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Person Name</th>
                    <th>Sacrament Type</th>
                    <th>Event Date</th>
                    <th>Book No.</th>
                    <th>Page No.</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($records_ledger as $rl): ?>
                <tr>
                    <td class="font-bold"><?php echo htmlspecialchars($rl['person_name']); ?></td>
                    <td><?php echo htmlspecialchars($rl['record_type']); ?></td>
                    <td><?php echo htmlspecialchars($rl['event_date']); ?></td>
                    <td><?php echo htmlspecialchars($rl['book_number']); ?></td>
                    <td><?php echo htmlspecialchars($rl['page_number']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <!-- ── 7. Certificate Issuances ── -->
    <div id="sec-certs-ledger" class="report-block">
        <div class="section-title">Certificate Issuances</div>
        <?php if (empty($certs_ledger)): ?>
            <p style="font-size:13px; color:#94a3b8;">No certificates issued in this period.</p>
        <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Certificate No.</th>
                    <th>Issued To</th>
                    <th>Purpose</th>
                    <th>Date Issued</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($certs_ledger as $cl): ?>
                <tr>
                    <td><?php echo htmlspecialchars($cl['certificate_no']); ?></td>
                    <td class="font-bold"><?php echo htmlspecialchars($cl['issued_to']); ?></td>
                    <td><?php echo htmlspecialchars($cl['purpose']); ?></td>
                    <td><?php echo htmlspecialchars($cl['issued_at']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <!-- ── 8. Tickets & Voided Donations ── -->
    <div id="sec-tickets-voids" class="report-block">
        <div class="two-col-tables" style="margin-top: 20px;">
            <div>
                <div class="section-title" style="margin-top: 0;">Tickets / Inquiries</div>
                <?php if (empty($tickets_status)): ?>
                    <p style="font-size:13px; color:#94a3b8;">No tickets created in this period.</p>
                <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Status</th>
                            <th class="text-right">Count</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tickets_status as $ts): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($ts['status']); ?></td>
                            <td class="text-right"><?php echo $ts['cnt']; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
            <div>
                <div class="section-title" style="margin-top: 0;">Voided Donations</div>
                <?php if (empty($voided_donations)): ?>
                    <p style="font-size:13px; color:#94a3b8;">No voided donations in this period.</p>
                <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Donor</th>
                            <th>Reason</th>
                            <th class="text-right">Amount (₱)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($voided_donations as $vd): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($vd['donor_name']); ?></td>
                            <td><?php echo htmlspecialchars($vd['void_reason']); ?></td>
                            <td class="text-right text-red-500 font-bold"><?php echo number_format($vd['amount'], 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ── 9. Staff Activity Logs ── -->
    <div id="sec-staff-activity" class="report-block">
        <div class="section-title">Staff Activity Logs (Top 15 Actions)</div>
        <?php if (empty($staff_activity)): ?>
            <p style="font-size:13px; color:#94a3b8;">No staff activities recorded.</p>
        <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Staff Name</th>
                    <th>Action</th>
                    <th>Module</th>
                    <th class="text-right">Count</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($staff_activity as $sa): ?>
                <tr>
                    <td class="font-bold"><?php echo htmlspecialchars($sa['performed_by_name']); ?></td>
                    <td><?php echo htmlspecialchars($sa['action']); ?></td>
                    <td><?php echo htmlspecialchars($sa['module']); ?></td>
                    <td class="text-right"><?php echo $sa['cnt']; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <!-- ── 10. All-Time Overall Stats ── -->
    <div id="sec-overall" class="report-block" style="page-break-inside: avoid;">
        <div class="section-title">Overall Statistics (All Time)</div>
        <ul class="data-list">
            <li><span>Total Donations (All):</span> <strong>₱<?php echo number_format($overall['total_donations_all'] ?? 0, 2); ?></strong></li>
            <li><span>Total Merchandise Sales (All):</span> <strong>₱<?php echo number_format($overall['total_merch_sales_all'] ?? 0, 2); ?></strong></li>
            <li class="total"><span>Grand Total Revenue (All):</span> <strong>₱<?php echo number_format($overall_grand_total, 2); ?></strong></li>
            <li><span>Sacramental Records (All):</span> <strong><?php echo number_format($overall['records_added_all'] ?? 0); ?></strong></li>
            <li><span>Events Completed (All):</span> <strong><?php echo number_format($overall['events_completed_all'] ?? 0); ?></strong></li>
        </ul>
    </div>

    <div class="footer">
        &copy; <?php echo date('Y'); ?> IFI Cathedral Parish. All Rights Reserved.
    </div>

    <script>
    function toggleAllSections(checkAll) {
        document.querySelectorAll('.sec-toggle').forEach(cb => {
            cb.checked = checkAll;
            applyToggle(cb);
        });
    }

    function applyToggle(cb) {
        const targetId = cb.getAttribute('data-target');
        const block = document.getElementById(targetId);
        if (block) {
            if (cb.checked) {
                block.classList.remove('hidden-section');
            } else {
                block.classList.add('hidden-section');
            }
        }
    }

    document.querySelectorAll('.sec-toggle').forEach(cb => {
        cb.addEventListener('change', function() {
            applyToggle(this);
        });
    });

    // Check URL query parameters for section pre-selection if passed
    (function() {
        const urlParams = new URLSearchParams(window.location.search);
        const hideLedgers = urlParams.get('hide_ledgers');
        if (hideLedgers === '1') {
            ['sec-donations-ledger', 'sec-merch', 'sec-records-ledger', 'sec-certs-ledger', 'sec-staff-activity'].forEach(id => {
                const cb = document.querySelector(`.sec-toggle[data-target="${id}"]`);
                if (cb) {
                    cb.checked = false;
                    applyToggle(cb);
                }
            });
        }
    })();
    </script>

</body>
</html>
