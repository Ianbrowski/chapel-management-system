<?php
/**
 * AuditLogSection - audit log query/filter service.
 */
class AuditLogSection
{
    private $conn;
    private $logs = [];
    private $filters = [];
    private $currentPage = 1;
    private $totalPages = 0;
    private $totalEntries = 0;

    private const MODULES = ['Events', 'Event Requests', 'Records', 'Users', 'Chapels', 'Finance', 'Merchandise', 'Bulletin', 'Auth', 'Inquiries', 'System'];
    private const ACTIONS = ['created', 'updated', 'deleted', 'requested', 'approved', 'rejected', 'archived', 'issued_cert', 'reordered', 'login', 'logout', 'failed_login', 'restored'];

    public function __construct(
        $logsOrConn = [],
        array $filters = [],
        int $currentPage = 1,
        int $totalPages = 0,
        int $totalEntries = 0
    ) {
        if ($logsOrConn instanceof \mysqli) {
            $this->conn = $logsOrConn;
            $this->logs = [];
        } else {
            $this->logs = is_array($logsOrConn) ? $logsOrConn : [];
        }

        $this->filters = $filters;
        $this->currentPage = $currentPage;
        $this->totalPages = $totalPages;
        $this->totalEntries = $totalEntries;
    }

    public function getAll($conn = null, $limit = 30): array
    {
        $db = $conn ?? $this->conn;
        if (!$db)
            return [];
        $logs = [];
        $res = $db->query("SELECT * FROM audit_logs ORDER BY created_at DESC LIMIT $limit");
        if ($res)
            while ($r = $res->fetch_assoc())
                $logs[] = $r;
        return $logs;
    }

    public function getFiltered(array $filters, int $page = 1, int $perPage = 30): array
    {
        $this->filters = $this->normalizeFilters($filters);
        [$whereSQL, $types, $params] = $this->buildWhereClause($this->filters);

        $countSQL = "SELECT COUNT(*) AS total FROM audit_logs WHERE $whereSQL";
        $stmt = $this->conn->prepare($countSQL);
        if (!$stmt) {
            $this->logs = [];
            $this->totalEntries = 0;
            $this->totalPages = 0;
            $this->currentPage = 1;
            return [];
        }
        if ($params)
            $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $this->totalEntries = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
        $stmt->close();

        $this->totalPages = (int) ceil($this->totalEntries / $perPage);
        $this->currentPage = max(1, min($page, $this->totalPages ?: 1));
        $offset = ($this->currentPage - 1) * $perPage;

        $fetchSQL = "SELECT * FROM audit_logs WHERE $whereSQL ORDER BY created_at DESC LIMIT ? OFFSET ?";
        $stmt = $this->conn->prepare($fetchSQL);
        if (!$stmt) {
            $this->logs = [];
            return [];
        }
        $fParams = array_merge($params, [$perPage, $offset]);
        $fTypes = $types . 'ii';
        $stmt->bind_param($fTypes, ...$fParams);
        $stmt->execute();
        $res = $stmt->get_result();

        $this->logs = [];
        if ($res)
            while ($row = $res->fetch_assoc())
                $this->logs[] = $row;
        $stmt->close();

        return $this->logs;
    }

    public function exportFiltered(array $filters): array
    {
        $normalized = $this->normalizeFilters($filters);
        [$whereSQL, $types, $params] = $this->buildWhereClause($normalized);

        $sql = "SELECT created_at, action, module, target_description, performed_by_name, performed_by_role, ip_address
                FROM audit_logs
                WHERE $whereSQL
                ORDER BY created_at DESC";
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            return [];
        }

        if ($params) {
            $stmt->bind_param($types, ...$params);
        }

        $stmt->execute();
        $res = $stmt->get_result();
        $rows = [];
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $rows[] = $row;
            }
        }
        $stmt->close();

        return $rows;
    }

    public function getFilterOptions(): array
    {
        return [
            'modules' => self::MODULES,
            'actions' => self::ACTIONS,
        ];
    }

    public function getFilters(): array { return $this->filters; }
    public function getCurrentPage(): int { return $this->currentPage; }
    public function getTotalPages(): int { return $this->totalPages; }
    public function getTotalEntries(): int { return $this->totalEntries; }

    private function normalizeFilters(array $filters): array
    {
        return [
            'user' => trim((string) ($filters['user'] ?? $filters['user_id'] ?? '')),
            'module' => trim((string) ($filters['module'] ?? '')),
            'action' => trim((string) ($filters['action'] ?? '')),
            'date_from' => trim((string) ($filters['date_from'] ?? $filters['date'] ?? '')),
            'date_to' => trim((string) ($filters['date_to'] ?? $filters['date'] ?? '')),
            'chapel_id' => $filters['chapel_id'] ?? null,
        ];
    }

    private function buildWhereClause(array $filters): array
    {
        $where = ['1=1'];
        $params = [];
        $types = '';

        if ($filters['user'] !== '') {
            if (is_numeric($filters['user'])) {
                $where[] = 'performed_by_id = ?';
                $params[] = (int) $filters['user'];
                $types .= 'i';
            } else {
                $where[] = 'performed_by_name LIKE ?';
                $params[] = '%' . $filters['user'] . '%';
                $types .= 's';
            }
        }

        if ($filters['module'] !== '') {
            $where[] = 'module = ?';
            $params[] = $filters['module'];
            $types .= 's';
        }

        if ($filters['action'] !== '') {
            $where[] = 'action = ?';
            $params[] = $filters['action'];
            $types .= 's';
        }

        if ($filters['date_from'] !== '') {
            $where[] = 'DATE(created_at) >= ?';
            $params[] = $filters['date_from'];
            $types .= 's';
        }

        if ($filters['date_to'] !== '') {
            $where[] = 'DATE(created_at) <= ?';
            $params[] = $filters['date_to'];
            $types .= 's';
        }

        if ($filters['chapel_id'] !== null) {
            $where[] = 'chapel_id = ?';
            $params[] = (int)$filters['chapel_id'];
            $types .= 'i';
        }

        return [implode(' AND ', $where), $types, $params];
    }

}

