<?php
class EventAnalyticsService
{
    private mysqli $conn;

    public function __construct(mysqli $conn)
    {
        $this->conn = $conn;
    }

    public function getUpcoming(int $limit = 5, ?int $chapelId = null): array
    {
        $upcoming = [];
        if ($chapelId !== null) {
            $stmt = $this->conn->prepare(
                "SELECT e.title, e.event_type, e.start_time, IFNULL(c.chapel_name, e.chapel_location) as chapel_location, e.status, e.chapel_id
                 FROM events e
                 LEFT JOIN chapels c ON e.chapel_id = c.chapel_id
                 WHERE e.start_time >= NOW() AND e.chapel_id = ? AND e.is_archived = 0
                   AND (e.event_type != 'Ecclesiastical Certificates' AND e.event_type NOT LIKE '%Certificate%')
                 ORDER BY e.start_time ASC LIMIT ?"
            );
            $stmt->bind_param('ii', $chapelId, $limit);
        } else {
            $stmt = $this->conn->prepare(
                "SELECT e.title, e.event_type, e.start_time, IFNULL(c.chapel_name, e.chapel_location) as chapel_location, e.status, e.chapel_id
                 FROM events e
                 LEFT JOIN chapels c ON e.chapel_id = c.chapel_id
                 WHERE e.start_time >= NOW() AND e.is_archived = 0
                   AND (e.event_type != 'Ecclesiastical Certificates' AND e.event_type NOT LIKE '%Certificate%')
                 ORDER BY e.start_time ASC LIMIT ?"
            );
            $stmt->bind_param('i', $limit);
        }
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) {
            $upcoming[] = $r;
        }
        $stmt->close();
        return $upcoming;
    }

    public function exportCsv(?string $from, ?string $to, ?int $chapelId = null): array
    {
        $sql = "SELECT e.*, IFNULL(c.chapel_name, e.chapel_location) as chapel_location, COALESCE(NULLIF(u.full_name, ''), u.user) as pastor_assigned
                FROM events e
                LEFT JOIN chapels c ON e.chapel_id = c.chapel_id
                LEFT JOIN users u ON e.pastor_id = u.id";
        $params = [];
        $types = '';
        $where = ['e.is_archived = 0'];
        if ($from) {
            $where[] = 'e.start_time >= ?';
            $params[] = $from . ' 00:00:00';
            $types .= 's';
        }
        if ($to) {
            $where[] = 'e.start_time <= ?';
            $params[] = $to . ' 23:59:59';
            $types .= 's';
        }
        if ($chapelId !== null) {
            $where[] = 'e.chapel_id = ?';
            $params[] = $chapelId;
            $types .= 'i';
        }
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY e.start_time DESC';

        $events = [];
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            return [];
        }
        if ($params) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $events[] = $row;
        }
        $stmt->close();
        return $events;
    }

    public function getChartTypes(?int $chapelId = null): array
    {
        // Convert chapelId = 0 to null (0 represents Main Cathedral, which has NULL chapel_id)
        if ($chapelId === 0) {
            $chapelId = null;
        }
        
        $sql = "SELECT event_type, COUNT(*) as c FROM events WHERE is_archived = 0";
        $params = [];
        $types = '';
        if ($chapelId !== null) {
            $sql .= " AND chapel_id = ?";
            $params[] = $chapelId;
            $types .= 'i';
        }
        $sql .= " GROUP BY event_type ORDER BY c DESC";
        return $this->fetchKeyedCounts($sql, 'event_type', 'Other', $params, $types);
    }

    public function getChartStatus(?int $chapelId = null): array
    {
        // Convert chapelId = 0 to null (0 represents Main Cathedral, which has NULL chapel_id)
        if ($chapelId === 0) {
            $chapelId = null;
        }
        
        $sql = "SELECT status, COUNT(*) as c FROM events WHERE is_archived = 0";
        $params = [];
        $types = '';
        if ($chapelId !== null) {
            $sql .= " AND chapel_id = ?";
            $params[] = $chapelId;
            $types .= 'i';
        }
        $sql .= " GROUP BY status";
        return $this->fetchKeyedCounts($sql, 'status', 'Unknown', $params, $types);
    }

    public function getChartMonthly(?int $chapelId = null): array
    {
        // Convert chapelId = 0 to null (0 represents Main Cathedral, which has NULL chapel_id)
        if ($chapelId === 0) {
            $chapelId = null;
        }
        
        $params = [];
        $types = '';
        $where = "WHERE is_archived = 0 AND start_time >= DATE_SUB(NOW(), INTERVAL 12 MONTH)";
        if ($chapelId !== null) {
            $where .= " AND chapel_id = ?";
            $params[] = $chapelId;
            $types .= 'i';
        }
        $sql = "SELECT DATE_FORMAT(start_time,'%Y-%m') as ym, COUNT(*) as c FROM events $where GROUP BY ym ORDER BY ym ASC";
        return $this->fetchKeyedCounts($sql, 'ym', '', $params, $types);
    }

    public function getPriestWorkload(?int $chapelId = null): array
    {
        // Convert chapelId = 0 to null (0 represents Main Cathedral, which has NULL chapel_id)
        if ($chapelId === 0) {
            $chapelId = null;
        }
        
        $data = [];
        $params = [];
        $types = '';
        $sql = "SELECT u.full_name as pastor, COUNT(e.event_id) as c
                FROM users u
                JOIN events e ON e.pastor_id = u.id
                WHERE u.role = 'Priest' AND e.is_archived = 0";
        if ($chapelId !== null) {
            $sql .= " AND e.chapel_id = ?";
            $params[] = $chapelId;
            $types .= 'i';
        }
        $sql .= " GROUP BY u.id ORDER BY c DESC";

        $stmt = $this->conn->prepare($sql);
        if (!$stmt) return $data;
        if ($params) $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $data[] = ['pastor' => $row['pastor'], 'count' => (int) $row['c']];
        }
        $stmt->close();
        return $data;
    }

    public function getVenueDemand(?int $chapelId = null): array
    {
        // Convert chapelId = 0 to null (0 represents Main Cathedral, which has NULL chapel_id)
        if ($chapelId === 0) {
            $chapelId = null;
        }
        
        $params = [];
        $types = '';
        $sql = "SELECT IFNULL(c.chapel_name, e.chapel_location) as label, COUNT(e.event_id) as value
                FROM events e
                LEFT JOIN chapels c ON e.chapel_id = c.chapel_id
                WHERE e.is_archived = 0";
        if ($chapelId !== null) {
            $sql .= " AND e.chapel_id = ?";
            $params[] = $chapelId;
            $types .= 'i';
        }
        $sql .= " GROUP BY label ORDER BY value DESC LIMIT 10";
        return $this->fetchRows($sql, $params, $types);
    }

    public function getUsageStatsByVenue(?int $chapelId = null): array
    {
        // Convert chapelId = 0 to null (0 represents Main Cathedral, which has NULL chapel_id)
        if ($chapelId === 0) {
            $chapelId = null;
        }
        
        $confirmedStatus = 'Confirmed';
        $params = [$confirmedStatus];
        $types = 's';
        $sql = "SELECT IFNULL(c.chapel_name, e.chapel_location) as chapel_location, COUNT(e.event_id) as total_events
                FROM events e
                LEFT JOIN chapels c ON e.chapel_id = c.chapel_id
                WHERE e.status = ? AND e.is_archived = 0";
        if ($chapelId !== null) {
            $sql .= " AND e.chapel_id = ?";
            $params[] = $chapelId;
            $types .= 'i';
        }
        $sql .= " GROUP BY chapel_location ORDER BY total_events DESC";
        return $this->fetchRows($sql, $params, $types);
    }

    public function getResponseTimeStats(?int $chapelId = null): ?float
    {
        // Convert chapelId = 0 to null (0 represents Main Cathedral, which has NULL chapel_id)
        if ($chapelId === 0) {
            $chapelId = null;
        }
        
        $params = [];
        $types = '';
        $where = "WHERE approved_at IS NOT NULL AND is_archived = 0";
        if ($chapelId !== null) {
            $where .= " AND chapel_id = ?";
            $params[] = $chapelId;
            $types .= 'i';
        }

        // First check if any records match
        $checkSql = "SELECT COUNT(*) as c FROM events $where";
        $checkStmt = $this->conn->prepare($checkSql);
        if (!$checkStmt) return null;
        if ($params) $checkStmt->bind_param($types, ...$params);
        $checkStmt->execute();
        $count = $checkStmt->get_result()->fetch_assoc()['c'] ?? 0;
        $checkStmt->close();

        if ($count == 0) return null;

        // Compute average response time
        $avgSql = "SELECT AVG(TIMESTAMPDIFF(SECOND, created_at, approved_at)) as avg_seconds
                   FROM events $where AND status IN ('Confirmed', 'Cancelled')";
        $avgStmt = $this->conn->prepare($avgSql);
        if (!$avgStmt) return null;
        if ($params) $avgStmt->bind_param($types, ...$params);
        $avgStmt->execute();
        $row = $avgStmt->get_result()->fetch_assoc();
        $avgStmt->close();

        return round(((float) ($row['avg_seconds'] ?? 0)) / 3600, 1);
    }

    /**
     * Executes a keyed-count query and returns an associative array.
     * Optionally accepts bound parameters for parameterized queries.
     */
    private function fetchKeyedCounts(string $sql, string $keyColumn, string $defaultKey = '', array $params = [], string $types = ''): array
    {
        $chart = [];
        if ($params) {
            $stmt = $this->conn->prepare($sql);
            if (!$stmt) return $chart;
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $res = $stmt->get_result();
            $stmt->close();
        } else {
            $res = $this->conn->query($sql);
        }
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $key = $row[$keyColumn] ?? $defaultKey;
                $chart[$key] = (int) $row['c'];
            }
        }
        return $chart;
    }

    /**
     * Executes a raw row-fetch query and returns all rows.
     * Optionally accepts bound parameters for parameterized queries.
     */
    private function fetchRows(string $sql, array $params = [], string $types = ''): array
    {
        $rows = [];
        if ($params) {
            $stmt = $this->conn->prepare($sql);
            if (!$stmt) return $rows;
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $res = $stmt->get_result();
            $stmt->close();
        } else {
            $res = $this->conn->query($sql);
        }
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $rows[] = $row;
            }
        }
        return $rows;
    }
}
