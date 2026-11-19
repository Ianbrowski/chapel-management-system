<?php
class Donation {
    private $conn;
    private bool $hasDonorRefId = true;

    public function __construct($conn) {
        $this->conn = $conn;
        $this->hasDonorRefId = true;
    }

    private function ensureRefIndex(): void {
        // Index idx_unique_ref on reference_id is permanent in schema
    }

    /** Column donor_ref_id is permanent in schema */
    private function ensureDonorRefIdColumn(): bool {
        return true;
    }

    private function prepareOrThrow(string $sql): mysqli_stmt {
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            throw new RuntimeException('Donation query failed to prepare: ' . ($this->conn->error ?: 'Unknown database error'));
        }
        return $stmt;
    }

    public function log($chapelId, $amount, $purpose, $donor, $dateRecv, $refId, $loggedBy, $method, $donorRefId = '') {
        $chapelIdVal  = intval($chapelId) > 0 ? intval($chapelId) : null;
        $loggedByVal  = intval($loggedBy) > 0 ? intval($loggedBy) : null;
        $donorRefId   = trim((string)$donorRefId);
        if ($this->hasDonorRefId) {
            $s = $this->prepareOrThrow("INSERT INTO donations (chapel_id,amount,purpose,donor_name,date_received,reference_id,donor_ref_id,logged_by,payment_method) VALUES (?,?,?,?,?,?,?,?,?)");
            $s->bind_param('idsssssis', $chapelIdVal, $amount, $purpose, $donor, $dateRecv, $refId, $donorRefId, $loggedByVal, $method);
        } else {
            $s = $this->prepareOrThrow("INSERT INTO donations (chapel_id,amount,purpose,donor_name,date_received,reference_id,logged_by,payment_method) VALUES (?,?,?,?,?,?,?,?)");
            $s->bind_param('idssssis', $chapelIdVal, $amount, $purpose, $donor, $dateRecv, $refId, $loggedByVal, $method);
        }
        $res = $s->execute();
        $id  = $res ? $this->conn->insert_id : null;
        $s->close();
        return $id;
    }

    public function create(array $data): ?int {
        $chapelId = $data['chapel_id'] ?? null;
        $amount = floatval($data['amount'] ?? 0);
        $purpose = $data['purpose'] ?? 'General Offering';
        $donor = $data['donor_name'] ?? 'Anonymous';
        $dateRecv = $data['date_received'] ?? date('Y-m-d H:i:s');
        $refId = $data['reference_id'] ?? $this->generateRefId();
        $loggedBy = $data['logger_id'] ?? ($_SESSION['user_id'] ?? 1);
        $method = $data['payment_method'] ?? 'Cash';
        $donorRefId = $data['donor_ref_id'] ?? '';

        return $this->log($chapelId, $amount, $purpose, $donor, $dateRecv, $refId, $loggedBy, $method, $donorRefId);
    }

    public function delete($id) {
        return $this->archive($id);
    }

    public function archive($id) {
        $d = $this->conn->prepare("UPDATE donations SET is_archived=1 WHERE donation_id=?");
        if (!$d) return false;
        $d->bind_param('i', $id);
        $res = $d->execute();
        $d->close();
        return $res;
    }

    /**
     * Void a donation with a mandatory reason.
     * Stamps void_reason, voided_by, voided_at — auto-provisions columns if missing.
     * Returns true on success, false on failure.
     */
    public function void(int $id, string $reason, int $voidedBy): bool {
        $this->ensureVoidColumns();
        $reason = trim($reason);
        if ($reason === '') return false;

        $s = $this->conn->prepare(
            "UPDATE donations
                SET is_archived = 1,
                    void_reason  = ?,
                    voided_by    = ?,
                    voided_at    = NOW()
              WHERE donation_id  = ? AND is_archived = 0"
        );
        if (!$s) return false;
        $s->bind_param('sii', $reason, $voidedBy, $id);
        $res = $s->execute();
        $affected = $s->affected_rows;
        $s->close();
        return $res && $affected > 0;
    }

    /** Fetch voided (archived) donations for admin review tab. */
    public function getVoided(?int $chapelFilter = null, int $limit = 200): array {
        $this->ensureVoidColumns();
        $where = ['d.is_archived = 1'];
        $types = '';
        $params = [];

        if ($chapelFilter !== null) {
            $where[] = 'd.chapel_id = ?';
            $types  .= 'i';
            $params[] = $chapelFilter;
        }
        $types   .= 'i';
        $params[] = max(1, $limit);

        $whereSQL = implode(' AND ', $where);
        $sql = "
            SELECT d.donation_id, d.chapel_id, d.amount, d.purpose, d.donor_name,
                   d.date_received, d.reference_id, d.payment_method,
                   d.void_reason, d.voided_at,
                   c.chapel_name,
                   COALESCE(NULLIF(u.full_name,''), u.user, 'System') AS logger_name,
                   COALESCE(NULLIF(v.full_name,''), v.user, 'Unknown')  AS voided_by_name
            FROM donations d
            LEFT JOIN chapels c ON d.chapel_id  = c.chapel_id
            LEFT JOIN users   u ON d.logged_by  = u.id
            LEFT JOIN users   v ON d.voided_by  = v.id
            WHERE {$whereSQL}
            ORDER BY d.voided_at DESC
            LIMIT ?
        ";
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) return [];
        if ($params) $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = [];
        while ($r = $res->fetch_assoc()) $rows[] = $r;
        $stmt->close();
        return $rows;
    }

    /** Void columns are permanent in schema. */
    private function ensureVoidColumns(): void {
        // Void columns (void_reason, voided_by, voided_at) are permanent in schema
    }

    public function getNextRefNum($year) {
        $stmt = $this->conn->prepare("SELECT COUNT(*) as cnt FROM donations WHERE YEAR(date_received) = ?");
        $stmt->bind_param('i', $year);
        $stmt->execute();
        $count = $stmt->get_result()->fetch_assoc()['cnt'] ?? 0;
        $stmt->close();
        return $count + 1;
    }

    public function generateRefId() {
        $year = date('Y');
        $base = (int) $this->getNextRefNum($year);
        $tries = 0;
        do {
            $next = str_pad((string) ($base + $tries), 5, '0', STR_PAD_LEFT);
            $ref = "DON-{$year}-{$next}";
            $tries++;
        } while ($tries < 1000 && $this->referenceExists($ref));

        return $ref;
    }

    public function countFiltered(?int $chapelFilter = null, array $filters = []): int {
        $where = ['d.is_archived = 0'];
        $types = '';
        $params = [];

        if ($chapelFilter !== null) {
            $where[] = 'd.chapel_id = ?';
            $types  .= 'i';
            $params[] = $chapelFilter;
        }
        if (!empty($filters['don_timeframe'])) {
            if ($filters['don_timeframe'] === 'recently_added' || $filters['don_timeframe'] === 'recently_added_30') {
                $where[] = 'd.date_received >= DATE_SUB(NOW(), INTERVAL 30 DAY)';
            } elseif ($filters['don_timeframe'] === 'recently_added_7') {
                $where[] = 'd.date_received >= DATE_SUB(NOW(), INTERVAL 7 DAY)';
            }
        }
        if (!empty($filters['don_from'])) {
            $where[] = 'd.date_received >= ?';
            $types  .= 's';
            $params[] = $filters['don_from'];
        }
        if (!empty($filters['don_to'])) {
            $where[] = 'd.date_received <= ?';
            $types  .= 's';
            $params[] = $filters['don_to'];
        }
        if (!empty($filters['don_method'])) {
            $where[] = 'd.payment_method = ?';
            $types  .= 's';
            $params[] = $filters['don_method'];
        }
        if (isset($filters['don_chapel']) && $filters['don_chapel'] !== '') {
            $val = (int)$filters['don_chapel'];
            if ($val === 0) {
                $where[] = '(d.chapel_id IS NULL OR d.chapel_id = 0)';
            } else {
                $where[] = 'd.chapel_id = ?';
                $types  .= 'i';
                $params[] = $val;
            }
        }

        $whereSQL = implode(' AND ', $where);
        $sql = "SELECT COUNT(*) as cnt FROM donations d WHERE {$whereSQL}";
        $stmt = $this->conn->prepare($sql);
        if ($types && $params) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc()['cnt'] ?? 0;
        $stmt->close();
        return (int)$res;
    }

    public function getPaginated(int $page = 1, int $perPage = 20, ?int $chapelFilter = null, array $filters = []): array {
        $total = $this->countFiltered($chapelFilter, $filters);
        $pages = (int) ceil($total / $perPage);
        $page = max(1, min($page, $pages ?: 1));
        $offset = ($page - 1) * $perPage;

        $records = $this->getAll($perPage, $chapelFilter, $filters, $offset);

        return [
            'records' => $records,
            'total'   => $total,
            'pages'   => $pages,
            'page'    => $page
        ];
    }

    public function getAll(int $limit = 100, ?int $chapelFilter = null, array $filters = [], int $offset = 0): array {
        $donations = [];

        $where = ['d.is_archived = 0'];
        $types = '';
        $params = [];

        if ($chapelFilter !== null) {
            $where[] = 'd.chapel_id = ?';
            $types  .= 'i';
            $params[] = $chapelFilter;
        }
        if (!empty($filters['don_timeframe'])) {
            if ($filters['don_timeframe'] === 'recently_added' || $filters['don_timeframe'] === 'recently_added_30') {
                $where[] = 'd.date_received >= DATE_SUB(NOW(), INTERVAL 30 DAY)';
            } elseif ($filters['don_timeframe'] === 'recently_added_7') {
                $where[] = 'd.date_received >= DATE_SUB(NOW(), INTERVAL 7 DAY)';
            }
        }
        if (!empty($filters['don_from'])) {
            $where[] = 'd.date_received >= ?';
            $types  .= 's';
            $params[] = $filters['don_from'];
        }
        if (!empty($filters['don_to'])) {
            $where[] = 'd.date_received <= ?';
            $types  .= 's';
            $params[] = $filters['don_to'];
        }
        if (!empty($filters['don_method'])) {
            $where[] = 'd.payment_method = ?';
            $types  .= 's';
            $params[] = $filters['don_method'];
        }
        if (isset($filters['don_chapel']) && $filters['don_chapel'] !== '') {
            $val = (int)$filters['don_chapel'];
            if ($val === 0) {
                $where[] = '(d.chapel_id IS NULL OR d.chapel_id = 0)';
            } else {
                $where[] = 'd.chapel_id = ?';
                $types  .= 'i';
                $params[] = $val;
            }
        }

        $whereSQL = implode(' AND ', $where);
        $types   .= 'ii';
        $params[] = max(1, $limit);
        $params[] = $offset;

        $donorRefSelect = $this->hasDonorRefId ? 'd.donor_ref_id' : "'' AS donor_ref_id";
        $sql = "
            SELECT d.donation_id,
                   d.chapel_id,
                   d.amount,
                   d.purpose,
                   d.donor_name,
                   d.date_received,
                   d.reference_id,
                   {$donorRefSelect},
                   d.logged_by,
                   d.payment_method,
                   c.chapel_name,
                   COALESCE(NULLIF(u.full_name, ''), u.user, 'System') AS logger_name
            FROM donations d
            LEFT JOIN chapels c ON d.chapel_id = c.chapel_id
            LEFT JOIN users u ON d.logged_by = u.id
            WHERE {$whereSQL}
            ORDER BY d.date_received DESC, d.donation_id DESC
            LIMIT ? OFFSET ?
        ";

        $dr = $this->prepareOrThrow($sql);
        if ($types && $params) {
            $dr->bind_param($types, ...$params);
        }
        $dr->execute();
        $res = $dr->get_result();
        while ($row = $res->fetch_assoc()) {
            $donations[] = $row;
        }
        $dr->close();
        return $donations;
    }

    public function getById(int $id): ?array {
        $sql = "
            SELECT d.*, c.chapel_name, COALESCE(NULLIF(u.full_name, ''), u.user, 'System') AS logged_by_name
            FROM donations d
            LEFT JOIN chapels c ON d.chapel_id = c.chapel_id
            LEFT JOIN users u ON d.logged_by = u.id
            WHERE d.donation_id = ?
            LIMIT 1
        ";
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) return null;
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }

    private function referenceExists(string $referenceId): bool {
        $stmt = $this->conn->prepare("SELECT 1 FROM donations WHERE reference_id = ? LIMIT 1");
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('s', $referenceId);
        $stmt->execute();
        $found = $stmt->get_result()->num_rows > 0;
        $stmt->close();
        return $found;
    }

    public function getStats(?int $chapelFilter = null) {
        $stats = ['total_amount' => 0, 'total_count' => 0, 'this_month' => 0];

        // Total all-time
        if ($chapelFilter !== null) {
            $s1 = $this->conn->prepare("SELECT COALESCE(SUM(amount),0) as total, COUNT(*) as cnt FROM donations WHERE chapel_id = ? AND is_archived = 0");
            $s1->bind_param('i', $chapelFilter);
        } else {
            $s1 = $this->conn->prepare("SELECT COALESCE(SUM(amount),0) as total, COUNT(*) as cnt FROM donations WHERE is_archived = 0");
        }
        $s1->execute();
        $r = $s1->get_result()->fetch_assoc();
        $s1->close();
        $stats['total_amount'] = $r['total'] ?? 0;
        $stats['total_count']  = $r['cnt']   ?? 0;

        // This month only
        if ($chapelFilter !== null) {
            $s2 = $this->conn->prepare("SELECT COALESCE(SUM(amount),0) as m FROM donations WHERE YEAR(date_received)=YEAR(NOW()) AND MONTH(date_received)=MONTH(NOW()) AND chapel_id = ? AND is_archived = 0");
            $s2->bind_param('i', $chapelFilter);
        } else {
            $s2 = $this->conn->prepare("SELECT COALESCE(SUM(amount),0) as m FROM donations WHERE YEAR(date_received)=YEAR(NOW()) AND MONTH(date_received)=MONTH(NOW()) AND is_archived = 0");
        }
        $s2->execute();
        $stats['this_month'] = $s2->get_result()->fetch_assoc()['m'] ?? 0;
        $s2->close();

        return $stats;
    }
}
