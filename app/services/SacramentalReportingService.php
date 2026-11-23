<?php
class SacramentalReportingService
{
    private mysqli $conn;

    public function __construct(mysqli $conn)
    {
        $this->conn = $conn;
    }

    public function exportByScope(string $scope, int|string|null $chapelId = null): array
    {
        $sql = "SELECT sr.reference_id, sr.record_type, sr.person_name, sr.event_date,
                       COALESCE(c.chapel_name, 'Main Cathedral') AS chapel_name,
                       sr.officiating_priest, sr.book_number, sr.page_number
                FROM sacramental_records sr
                LEFT JOIN chapels c ON c.chapel_id = sr.chapel_id
                WHERE sr.is_archived = 0";
        $params = [];
        $types = '';

        if ($scope === 'cathedral') {
            if ($chapelId !== null) {
                if ($chapelId === 'cathedral' || (int)$chapelId === 0) {
                    $sql .= " AND (sr.scope = 'cathedral' OR sr.chapel_id IS NULL OR sr.chapel_id = 0)";
                } elseif ((int)$chapelId < 0) {
                    $sql .= " AND 1 = 0";
                } else {
                    $sql .= " AND sr.chapel_id = ?";
                    $params[] = (int)$chapelId;
                    $types .= 'i';
                }
            }
        } elseif ($scope === 'chapel') {
            if ($chapelId !== null) {
                if ($chapelId === 'cathedral' || (int)$chapelId <= 0) {
                    $sql .= " AND 1 = 0";
                } else {
                    $sql .= " AND sr.chapel_id = ?";
                    $params[] = (int)$chapelId;
                    $types .= 'i';
                }
            } else {
                $sql .= " AND (sr.scope = 'chapel' OR (sr.chapel_id IS NOT NULL AND sr.chapel_id > 0))";
            }
        } else {
            $sql .= " AND sr.scope = ?";
            $params[] = $scope;
            $types .= 's';
        }

        $sql .= " ORDER BY sr.event_date DESC, sr.record_id DESC";
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            return [];
        }
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = [];
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
        $stmt->close();
        return $rows;
    }

    public function getChartTypes(int|string|null $chapelId = null): array
    {
        $where = "WHERE is_archived=0";
        $params = [];
        $types = '';
        if ($chapelId !== null) {
            if ($chapelId === 'cathedral' || (is_numeric($chapelId) && (int)$chapelId === 0)) {
                $where .= " AND (scope = 'cathedral' OR chapel_id IS NULL OR chapel_id = 0)";
            } elseif ((int)$chapelId < 0) {
                $where .= " AND 1 = 0";
            } else {
                $where .= " AND chapel_id = ?";
                $params[] = (int)$chapelId;
                $types .= 'i';
            }
        }
        $sql = "SELECT record_type, COUNT(*) AS cnt FROM sacramental_records $where GROUP BY record_type";
        if (!empty($params)) {
            $stmt = $this->conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param($types, ...$params);
                $stmt->execute();
                $res = $stmt->get_result();
            } else {
                $res = null;
            }
        } else {
            $res = $this->conn->query($sql);
        }

        $typesArr = [];
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $typesArr[$row['record_type']] = (int) $row['cnt'];
            }
        }
        return $typesArr;
    }

    public function getStats($chapelId = null, ?string $scope = null): array
    {
        if (is_string($chapelId) && in_array($chapelId, ['cathedral', 'chapel'], true)) {
            $scope = $chapelId;
            $chapelId = null;
        }

        $sql = "SELECT
                  COUNT(*) AS total,
                  COALESCE(SUM(record_type='Baptism'), 0) AS baptisms,
                  COALESCE(SUM(record_type='Wedding'), 0) AS weddings,
                  COALESCE(SUM(record_type='Funeral'), 0) AS funerals,
                  COALESCE(SUM(record_type='Confirmation'), 0) AS confirmations,
                  COALESCE(SUM(record_type='Communion'), 0) AS communions,
                  COALESCE(SUM(YEAR(event_date)=YEAR(NOW())), 0) AS this_year,
                  COALESCE(SUM(DATE_FORMAT(event_date,'%Y-%m')=DATE_FORMAT(NOW(),'%Y-%m')), 0) AS this_month
                FROM sacramental_records
                WHERE is_archived=0";
        $params = [];
        $types = '';
        if ($chapelId !== null && $chapelId !== '') {
            if ((int)$chapelId === 0) {
                $sql .= " AND (scope = 'cathedral' OR chapel_id IS NULL OR chapel_id = 0)";
            } else {
                $sql .= " AND chapel_id = ?";
                $params[] = (int)$chapelId;
                $types .= 'i';
            }
        } elseif ($scope === 'chapel') {
            $sql .= " AND (scope = 'chapel' OR (chapel_id IS NOT NULL AND chapel_id > 0))";
        }

        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            return [];
        }
        if ($params) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc() ?: [];
        $stmt->close();
        return $row;
    }

    public function getMonthlyBreakdown(int $months = 12, ?int $chapelId = null): array
    {
        $where = "WHERE is_archived=0 AND event_date >= DATE_SUB(NOW(), INTERVAL ? MONTH)";
        $params = [$months];
        $types = 'i';
        if ($chapelId !== null) {
            $isCathedral = ($chapelId === 0);
            if ($isCathedral) {
                $where .= " AND (scope = 'cathedral' OR chapel_id IS NULL OR chapel_id = 0)";
            } else {
                $where .= " AND chapel_id = ?";
                $params[] = (int)$chapelId;
                $types .= 'i';
            }
        }
        $sql = "SELECT DATE_FORMAT(event_date,'%Y-%m') AS ym,
                       record_type,
                       COUNT(*) AS cnt
                FROM sacramental_records
                $where
                GROUP BY ym, record_type
                ORDER BY ym ASC";
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            return [];
        }
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = [];
        while ($r = $res->fetch_assoc()) {
            $rows[] = $r;
        }
        $stmt->close();
        return $rows;
    }

    public function checkDuplicate(string $type, array $data): ?array
    {
        switch ($type) {
            case 'Baptism':
                $sql = "SELECT record_id, reference_id, event_date FROM sacramental_records
                        WHERE is_archived=0 AND record_type='Baptism'
                          AND child_name = ? AND YEAR(event_date) = ?
                        LIMIT 1";
                $stmt = $this->conn->prepare($sql);
                $childName = trim((string)($data['child_name'] ?? $data['person_name'] ?? ''));
                $dateRef   = $data['event_date'] ?? ($data['date_of_birth'] ?? date('Y-m-d'));
                $year      = (int)date('Y', strtotime((string)$dateRef) ?: time());
                $stmt->bind_param('si', $childName, $year);
                break;
            case 'Wedding':
                $sql = "SELECT record_id, reference_id, event_date FROM sacramental_records
                        WHERE is_archived=0 AND record_type='Wedding'
                          AND groom_name = ? AND bride_name = ?
                        LIMIT 1";
                $stmt = $this->conn->prepare($sql);
                $stmt->bind_param('ss', $data['groom_name'], $data['bride_name']);
                break;
            case 'Funeral':
                $sql = "SELECT record_id, reference_id, event_date FROM sacramental_records
                        WHERE is_archived=0 AND record_type='Funeral'
                          AND deceased_name = ? AND date_of_death = ?
                        LIMIT 1";
                $stmt = $this->conn->prepare($sql);
                $stmt->bind_param('ss', $data['deceased_name'], $data['date_of_death']);
                break;
            default:
                return null;
        }

        $stmt->execute();
        $dup = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $dup ?: null;
    }

    public function getBaptismFuneralRatio(): array
    {
        return $this->getGroupedCounts(['Baptism', 'Funeral']);
    }

    public function getRetentionStats(): array
    {
        return $this->getGroupedCounts(['Baptism', 'Confirmation']);
    }

    private function getGroupedCounts(array $types): array
    {
        $placeholders = implode(',', array_fill(0, count($types), '?'));
        $sql = "SELECT record_type, COUNT(*) as c FROM sacramental_records
                WHERE is_archived=0 AND record_type IN ($placeholders)
                GROUP BY record_type";
        $stmt = $this->conn->prepare($sql);
        $bindTypes = str_repeat('s', count($types));
        $stmt->bind_param($bindTypes, ...$types);
        $stmt->execute();
        $res = $stmt->get_result();

        $data = [];
        foreach ($types as $type) {
            $data[$type] = 0;
        }
        while ($row = $res->fetch_assoc()) {
            $data[$row['record_type']] = (int) $row['c'];
        }
        $stmt->close();
        return $data;
    }
}
