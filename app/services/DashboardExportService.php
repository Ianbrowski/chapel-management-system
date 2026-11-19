<?php
class DashboardExportService
{
    private mysqli $conn;

    public function __construct(mysqli $conn)
    {
        $this->conn = $conn;
    }

    public function streamReportCsv(?string $from, ?string $to, ?int $chapelId = null): void
    {
        $from = trim((string) $from);
        $to = trim((string) $to);

        foreach (['from' => $from, 'to' => $to] as $label => $value) {
            if ($value !== '' && !$this->isValidDate($value)) {
                throw new InvalidArgumentException(ucfirst($label) . ' date must use YYYY-MM-DD format.');
            }
        }

        // We will output a highly styled multi-section CSV for professional Excel rendering.
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="parish_detailed_report_' . date('Ymd_His') . '.csv"');
        $out = fopen('php://output', 'w');

        // Helper to output empty line
        $br = function() use ($out) { fputcsv($out, []); };

        // Output Premium Title and Metadata block
        fputcsv($out, ['======================================================================']);
        fputcsv($out, ['   IFI CATHEDRAL PARISH — DETAILED MANAGEMENT & FINANCIAL REPORT']);
        fputcsv($out, ['======================================================================']);
        fputcsv($out, ['REPORT PARAMETERS']);
        fputcsv($out, ['  Date Range:', $from . ' to ' . $to]);
        fputcsv($out, ['  Generated On:', date('Y-m-d h:i:s A')]);
        fputcsv($out, ['  Scope Filter:', $chapelId ? 'Chapel ID ' . $chapelId : 'Full Parish (All Chapels & Cathedral)']);
        fputcsv($out, ['======================================================================']);
        $br();

        // Common filter bindings
        $p1 = $from . ' 00:00:00';
        $p2 = $to . ' 23:59:59';
        
        $whereChapelEvent = $chapelId ? ' AND chapel_id = ' . (int)$chapelId : '';
        $whereChapelDonation = $chapelId ? ' AND d.chapel_id = ' . (int)$chapelId : '';
        $whereChapelRecord = $chapelId ? ' AND chapel_id = ' . (int)$chapelId : '';

        // --- 1. REVENUE SUMMARY ---
        fputcsv($out, ['----------------------------------------------------------------------']);
        fputcsv($out, [' SECTION 1: FINANCIAL REVENUE OVERVIEW']);
        fputcsv($out, ['----------------------------------------------------------------------']);
        $stats_sql = "SELECT 
            (SELECT SUM(amount) FROM donations d WHERE date_received BETWEEN ? AND ? AND is_archived=0 $whereChapelDonation) as total_donations,
            (SELECT COUNT(*) FROM donations d WHERE date_received BETWEEN ? AND ? AND is_archived=0 $whereChapelDonation) as count_donations,
            (SELECT SUM(total_price) FROM merchandise_sales WHERE sale_date BETWEEN ? AND ?) as total_merch_sales,
            (SELECT SUM(quantity) FROM merchandise_sales WHERE sale_date BETWEEN ? AND ?) as qty_merch_sales,
            (SELECT COUNT(*) FROM sacramental_records WHERE date_added BETWEEN ? AND ? AND is_archived=0 $whereChapelRecord) as records_added,
            (SELECT COUNT(*) FROM events WHERE start_time BETWEEN ? AND ? AND status='Completed' AND is_archived=0 $whereChapelEvent) as events_completed
        ";
        $stmt = $this->conn->prepare($stats_sql);
        $stmt->bind_param('ssssssssssss', $p1, $p2, $p1, $p2, $p1, $p2, $p1, $p2, $p1, $p2, $p1, $p2);
        $stmt->execute();
        $stats = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $gTotal = ($stats['total_donations'] ?? 0) + ($stats['total_merch_sales'] ?? 0);
        fputcsv($out, ['REVENUE SOURCE', 'VOLUME / QUANTITY', 'TOTAL AMOUNT (PHP)']);
        fputcsv($out, ['Donations Received (Tithes & General)', $stats['count_donations'] . ' Transactions', number_format((float)($stats['total_donations'] ?? 0), 2, '.', '')]);
        fputcsv($out, ['Merchandise Sales (Items & Souvenirs)', $stats['qty_merch_sales'] . ' Items Sold', number_format((float)($stats['total_merch_sales'] ?? 0), 2, '.', '')]);
        fputcsv($out, ['GRAND TOTAL REVENUE', '', number_format($gTotal, 2, '.', '')]);
        $br();

        // --- 2. DONATIONS BREAKDOWN ---
        fputcsv($out, ['----------------------------------------------------------------------']);
        fputcsv($out, [' SECTION 2: DONATIONS BY VENUE & PAYMENT METHOD']);
        fputcsv($out, ['----------------------------------------------------------------------']);
        $don_sql = "SELECT COALESCE(c.chapel_name, 'Main Cathedral') as chapel, d.payment_method, COUNT(*) as txns, SUM(d.amount) as total 
                    FROM donations d LEFT JOIN chapels c ON d.chapel_id = c.chapel_id 
                    WHERE d.date_received BETWEEN ? AND ? AND d.is_archived=0 $whereChapelDonation 
                    GROUP BY d.chapel_id, d.payment_method ORDER BY total DESC";
        $stmt = $this->conn->prepare($don_sql);
        $stmt->bind_param('ss', $from, $to);
        $stmt->execute();
        $res = $stmt->get_result();
        fputcsv($out, ['VENUE LOCATION', 'PAYMENT METHOD', 'TRANSACTION COUNT', 'TOTAL AMOUNT (PHP)']);
        while ($r = $res->fetch_assoc()) {
            fputcsv($out, [$r['chapel'], $r['payment_method'], $r['txns'], number_format((float)$r['total'], 2, '.', '')]);
        }
        $stmt->close();
        $br();

        // --- 3. MERCHANDISE BREAKDOWN ---
        fputcsv($out, ['----------------------------------------------------------------------']);
        fputcsv($out, [' SECTION 3: MERCHANDISE SALES BY ITEM']);
        fputcsv($out, ['----------------------------------------------------------------------']);
        $merch_sql = "SELECT mi.name, SUM(ms.quantity) as qty, SUM(ms.total_price) as total 
                      FROM merchandise_sales ms JOIN merchandise_items mi ON ms.item_id = mi.id 
                      WHERE ms.sale_date BETWEEN ? AND ? 
                      GROUP BY ms.item_id ORDER BY total DESC";
        $stmt = $this->conn->prepare($merch_sql);
        $stmt->bind_param('ss', $p1, $p2);
        $stmt->execute();
        $res = $stmt->get_result();
        fputcsv($out, ['ITEM NAME', 'UNITS SOLD', 'TOTAL REVENUE (PHP)']);
        while ($r = $res->fetch_assoc()) {
            fputcsv($out, [$r['name'], $r['qty'], number_format((float)$r['total'], 2, '.', '')]);
        }
        $stmt->close();
        $br();

        // --- 4. EVENTS BREAKDOWN ---
        fputcsv($out, ['----------------------------------------------------------------------']);
        fputcsv($out, [' SECTION 4: EVENTS BREAKDOWN & STATUS SUMMARY']);
        fputcsv($out, ['----------------------------------------------------------------------']);
        fputcsv($out, ['Total Completed Events:', $stats['events_completed'] ?? 0]);
        $br();
        fputcsv($out, ['BY EVENT TYPE']);
        fputcsv($out, ['  EVENT TYPE', 'COUNT']);
        $ev_type_sql = "SELECT event_type, COUNT(*) as cnt FROM events WHERE start_time BETWEEN ? AND ? AND is_archived=0 $whereChapelEvent GROUP BY event_type ORDER BY cnt DESC";
        $stmt = $this->conn->prepare($ev_type_sql);
        $stmt->bind_param('ss', $p1, $p2);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) {
            fputcsv($out, ['  ' . $r['event_type'], $r['cnt']]);
        }
        $stmt->close();
        $br();

        fputcsv($out, ['BY EVENT STATUS']);
        fputcsv($out, ['  STATUS', 'COUNT']);
        $ev_status_sql = "SELECT status, COUNT(*) as cnt FROM events WHERE start_time BETWEEN ? AND ? AND is_archived=0 $whereChapelEvent GROUP BY status ORDER BY cnt DESC";
        $stmt = $this->conn->prepare($ev_status_sql);
        $stmt->bind_param('ss', $p1, $p2);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) {
            fputcsv($out, ['  ' . $r['status'], $r['cnt']]);
        }
        $stmt->close();
        $br();

        // --- 5. SACRAMENTAL RECORDS BREAKDOWN ---
        fputcsv($out, ['----------------------------------------------------------------------']);
        fputcsv($out, [' SECTION 5: SACRAMENTAL RECORDS INDEXED']);
        fputcsv($out, ['----------------------------------------------------------------------']);
        fputcsv($out, ['Total Records Registered:', $stats['records_added'] ?? 0]);
        $br();
        fputcsv($out, ['SACRAMENT TYPE', 'RECORD COUNT']);
        $rec_sql = "SELECT record_type, COUNT(*) as cnt FROM sacramental_records WHERE date_added BETWEEN ? AND ? AND is_archived=0 $whereChapelRecord GROUP BY record_type ORDER BY cnt DESC";
        $stmt = $this->conn->prepare($rec_sql);
        $stmt->bind_param('ss', $p1, $p2);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) {
            fputcsv($out, [$r['record_type'], $r['cnt']]);
        }
        $stmt->close();
        $br();

        // --- 6. DETAILED EVENTS LIST ---
        fputcsv($out, ['----------------------------------------------------------------------']);
        fputcsv($out, [' SECTION 6: CHRONOLOGICAL EVENTS LEDGER']);
        fputcsv($out, ['----------------------------------------------------------------------']);
        $sql = "SELECT start_time, title, event_type, chapel_location, pastor_assigned, status
                FROM events
                WHERE start_time BETWEEN ? AND ? AND is_archived=0 $whereChapelEvent
                ORDER BY start_time DESC";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('ss', $p1, $p2);
        $stmt->execute();
        $res = $stmt->get_result();
        fputcsv($out, ['DATE & TIME', 'EVENT TITLE', 'TYPE', 'LOCATION', 'ASSIGNED PRIEST', 'STATUS']);
        while ($row = $res->fetch_assoc()) {
            fputcsv($out, [
                date('Y-m-d h:i A', strtotime($row['start_time'])),
                $row['title'],
                $row['event_type'],
                $row['chapel_location'] ?: 'Main Cathedral',
                $row['pastor_assigned'] ?: '—',
                $row['status']
            ]);
        }
        $stmt->close();
        $br();

        // --- 7. TOP 10 DONORS ---
        fputcsv($out, ['----------------------------------------------------------------------']);
        fputcsv($out, [' SECTION 7: TOP 10 GENEROUS DONORS (PERIOD)']);
        fputcsv($out, ['----------------------------------------------------------------------']);
        $sql = "SELECT donor_name, SUM(amount) as total FROM donations d WHERE date_received BETWEEN ? AND ? AND is_archived=0 $whereChapelDonation GROUP BY donor_name ORDER BY total DESC LIMIT 10";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('ss', $from, $to);
        $stmt->execute();
        $res = $stmt->get_result();
        fputcsv($out, ['DONOR NAME', 'TOTAL AMOUNT (PHP)']);
        while ($row = $res->fetch_assoc()) {
            fputcsv($out, [$row['donor_name'], number_format((float)$row['total'], 2, '.', '')]);
        }
        $stmt->close();
        $br();

        // --- 8. INDIVIDUAL DONATIONS LEDGER ---
        fputcsv($out, ['----------------------------------------------------------------------']);
        fputcsv($out, [' SECTION 8: DETAILED DONATIONS GENERAL LEDGER']);
        fputcsv($out, ['----------------------------------------------------------------------']);
        $sql = "SELECT donation_id, donor_name, amount, purpose, payment_method, date_received FROM donations d WHERE date_received BETWEEN ? AND ? AND is_archived=0 $whereChapelDonation ORDER BY date_received DESC";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('ss', $from, $to);
        $stmt->execute();
        $res = $stmt->get_result();
        fputcsv($out, ['TRANSACTION ID', 'DONOR NAME', 'AMOUNT (PHP)', 'PURPOSE / FUND', 'PAYMENT METHOD', 'DATE RECEIVED']);
        while ($row = $res->fetch_assoc()) {
            fputcsv($out, [
                'DON-' . str_pad($row['donation_id'], 6, '0', STR_PAD_LEFT),
                $row['donor_name'],
                number_format((float)$row['amount'], 2, '.', ''),
                $row['purpose'],
                $row['payment_method'],
                $row['date_received']
            ]);
        }
        $stmt->close();
        $br();

        // --- 9. INDIVIDUAL MERCHANDISE SALES ---
        fputcsv($out, ['----------------------------------------------------------------------']);
        fputcsv($out, [' SECTION 9: DETAILED MERCHANDISE SALES LEDGER']);
        fputcsv($out, ['----------------------------------------------------------------------']);
        $sql = "SELECT ms.id, mi.name as item, ms.buyer_name, ms.quantity, ms.total_price, ms.sale_date FROM merchandise_sales ms JOIN merchandise_items mi ON ms.item_id=mi.id WHERE ms.sale_date BETWEEN ? AND ? ORDER BY ms.sale_date DESC";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('ss', $p1, $p2);
        $stmt->execute();
        $res = $stmt->get_result();
        fputcsv($out, ['SALE ID', 'ITEM NAME', 'BUYER NAME', 'QUANTITY', 'TOTAL VALUE (PHP)', 'SALE DATE']);
        while ($row = $res->fetch_assoc()) {
            fputcsv($out, [
                'SALE-' . str_pad($row['id'], 6, '0', STR_PAD_LEFT),
                $row['item'],
                $row['buyer_name'],
                $row['quantity'],
                number_format((float)$row['total_price'], 2, '.', ''),
                date('Y-m-d h:i A', strtotime($row['sale_date']))
            ]);
        }
        $stmt->close();
        $br();

        // --- 10. INDIVIDUAL SACRAMENTAL RECORDS ---
        fputcsv($out, ['----------------------------------------------------------------------']);
        fputcsv($out, [' SECTION 10: REGISTERED SACRAMENTAL RECORDS LEDGER']);
        fputcsv($out, ['----------------------------------------------------------------------']);
        $sql = "SELECT record_type, COALESCE(person_name,'N/A') as person_name, event_date, book_number, page_number FROM sacramental_records WHERE date_added BETWEEN ? AND ? AND is_archived=0 $whereChapelRecord ORDER BY date_added DESC";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('ss', $p1, $p2);
        $stmt->execute();
        $res = $stmt->get_result();
        fputcsv($out, ['SACRAMENT TYPE', 'PRIMARY PERSON NAME', 'EVENT/SACRAMENT DATE', 'BOOK NUMBER', 'PAGE NUMBER']);
        while ($row = $res->fetch_assoc()) { fputcsv($out, $row); }
        $stmt->close();
        $br();

        // --- 11. CERTIFICATE ISSUANCES ---
        fputcsv($out, ['----------------------------------------------------------------------']);
        fputcsv($out, [' SECTION 11: CERTIFICATES ISSUANCES LOG']);
        fputcsv($out, ['----------------------------------------------------------------------']);
        $sql = "SELECT certificate_no, issued_to, purpose, issued_at FROM certificate_issuances WHERE issued_at BETWEEN ? AND ? ORDER BY issued_at DESC";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('ss', $p1, $p2);
        $stmt->execute();
        $res = $stmt->get_result();
        fputcsv($out, ['CERTIFICATE NUMBER', 'ISSUED TO', 'PURPOSE', 'DATE & TIME ISSUED']);
        while ($row = $res->fetch_assoc()) {
            fputcsv($out, [
                $row['certificate_no'],
                $row['issued_to'],
                $row['purpose'],
                date('Y-m-d h:i A', strtotime($row['issued_at']))
            ]);
        }
        $stmt->close();
        $br();

        // --- 12. TICKETS / INQUIRIES ---
        fputcsv($out, ['----------------------------------------------------------------------']);
        fputcsv($out, [' SECTION 12: INCOMING INQUIRIES & TICKETS SUMMARY']);
        fputcsv($out, ['----------------------------------------------------------------------']);
        $sql = "SELECT status, COUNT(*) as cnt FROM tickets WHERE created_at BETWEEN ? AND ? GROUP BY status";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('ss', $p1, $p2);
        $stmt->execute();
        $res = $stmt->get_result();
        fputcsv($out, ['TICKET STATUS', 'TOTAL COUNT']);
        while ($row = $res->fetch_assoc()) { fputcsv($out, $row); }
        $stmt->close();
        $br();

        // --- 13. VOIDED DONATIONS ---
        fputcsv($out, ['----------------------------------------------------------------------']);
        fputcsv($out, [' SECTION 13: VOIDED DONATIONS LEDGER (AUDIT ONLY)']);
        fputcsv($out, ['----------------------------------------------------------------------']);
        $sql = "SELECT donor_name, amount, void_reason, date_received FROM donations d WHERE is_archived=1 AND date_received BETWEEN ? AND ? $whereChapelDonation ORDER BY date_received DESC";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('ss', $from, $to);
        $stmt->execute();
        $res = $stmt->get_result();
        fputcsv($out, ['DONOR NAME', 'AMOUNT VOIDED (PHP)', 'VOID REASON / EXPLANATION', 'ORIGINAL DATE']);
        while ($row = $res->fetch_assoc()) {
            fputcsv($out, [
                $row['donor_name'],
                number_format((float)$row['amount'], 2, '.', ''),
                $row['void_reason'],
                $row['date_received']
            ]);
        }
        $stmt->close();
        $br();

        // --- 14. STAFF ACTIVITY ---
        fputcsv($out, ['----------------------------------------------------------------------']);
        fputcsv($out, [' SECTION 14: SYSTEM AUDIT & STAFF ACTIVITY LOGS']);
        fputcsv($out, ['----------------------------------------------------------------------']);
        $sql = "SELECT performed_by_name, action, module, COUNT(*) as cnt FROM audit_logs WHERE created_at BETWEEN ? AND ? GROUP BY performed_by_name, action, module ORDER BY cnt DESC LIMIT 15";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('ss', $p1, $p2);
        $stmt->execute();
        $res = $stmt->get_result();
        fputcsv($out, ['STAFF MEMBER NAME', 'ACTION PERFORMED', 'SYSTEM MODULE', 'TOTAL ACTION COUNT']);
        while ($row = $res->fetch_assoc()) { fputcsv($out, $row); }
        $stmt->close();
        $br();

        fclose($out);
        exit();
    }

    public function streamAuditCsv(array $rows): void
    {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="audit_logs_' . date('Ymd_His') . '.csv"');

        $out = fopen('php://output', 'w');
        fputcsv($out, ['Timestamp', 'Action', 'Module', 'Target', 'Performed By', 'Role', 'IP Address']);
        foreach ($rows as $row) {
            fputcsv($out, [
                $row['created_at'] ?? '',
                $row['action'] ?? '',
                $row['module'] ?? '',
                $row['target_description'] ?? '',
                $row['performed_by_name'] ?? '',
                $row['performed_by_role'] ?? '',
                $row['ip_address'] ?? '',
            ]);
        }
        fclose($out);
        exit();
    }

    public function streamRecordsCsv(string $scope, array $rows): void
    {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="records_' . $scope . '_' . date('Ymd_His') . '.csv"');

        $out = fopen('php://output', 'w');
        fputcsv($out, ['Reference ID', 'Type', 'Primary Person', 'Event Date', 'Venue', 'Priest', 'Book', 'Page']);
        foreach ($rows as $row) {
            fputcsv($out, [
                $row['reference_id'] ?? '',
                $row['record_type'] ?? '',
                $row['person_name'] ?? '',
                $row['event_date'] ?? '',
                $row['chapel_name'] ?? '',
                $row['officiating_priest'] ?? '',
                $row['book_number'] ?? '',
                $row['page_number'] ?? '',
            ]);
        }
        fclose($out);
        exit();
    }

    private function isValidDate(string $value): bool
    {
        $dt = DateTime::createFromFormat('Y-m-d', $value);
        return $dt !== false && $dt->format('Y-m-d') === $value;
    }
}
