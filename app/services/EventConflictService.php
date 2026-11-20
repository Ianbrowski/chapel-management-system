<?php
class EventConflictService
{
    private mysqli $conn;

    public function __construct(mysqli $conn)
    {
        $this->conn = $conn;
    }

    public function getEventDuration(string $eventType, ?int $customDuration = null): int
    {
        if ($customDuration !== null && (int) $customDuration > 0) {
            return (int) $customDuration;
        }

        $defaults = [
            'Wedding' => 120,
            'Wedding (Holy Matrimony)' => 120,
            'Mass' => 90,
            'Thanksgiving Mass' => 90,
            'Burial Mass' => 90,
            'Requiem Mass' => 90,
            'Baptism' => 60,
            'Confirmation' => 60,
            'Funeral' => 90,
            'Blessings' => 60,
            'Fiesta' => 120,
            'Other' => 60,
        ];

        return $defaults[$eventType] ?? 60;
    }

    /**
     * Checks for venue AND pastor time-overlap conflicts.
     * Returns an array with conflict status and details.
     */
    public function checkConflict(string $startTime, $pastorId, $chapelId, string $eventType = 'Other', $excludeId = null, ?int $customDuration = null): array
    {
        // Certificates are administrative office transactions, not altar/sanctuary events
        if ($eventType === 'Ecclesiastical Certificates' || stripos($eventType, 'Certificate') !== false) {
            return ['conflict' => false];
        }

        $window = $this->buildWindow($startTime, $eventType, $customDuration);
        if (isset($window['error'])) {
            return $window['error'];
        }

        $venueConflict = $this->hasVenueConflict($chapelId, $window['date'], $window['start'], $window['end'], $excludeId);
        if ($venueConflict) {
            return [
                'conflict' => true,
                'type' => 'Venue',
                'details' => $this->formatConflictDetails($venueConflict),
                'status' => $venueConflict['status'] ?? null,
                'event_id' => (int)($venueConflict['event_id'] ?? 0),
            ];
        }

        if ($pastorId) {
            $dutyConflict = $this->checkPriestDutyHours($pastorId, $window['start']);
            if ($dutyConflict) {
                return $dutyConflict;
            }
        }

        $priestConflict = $this->hasPriestConflict($pastorId, $window['date'], $window['start'], $window['end'], $excludeId);
        if ($priestConflict) {
            return [
                'conflict' => true,
                'type' => 'Priest',
                'details' => $this->formatConflictDetails($priestConflict),
                'status' => $priestConflict['status'] ?? null,
                'event_id' => (int)($priestConflict['event_id'] ?? 0),
            ];
        }

        return ['conflict' => false];
    }

    public function checkVenueOnly($chapelId, string $startTime, string $eventType = 'Other', $excludeId = null, ?int $customDuration = null): array
    {
        if ($eventType === 'Ecclesiastical Certificates' || stripos($eventType, 'Certificate') !== false) {
            return ['conflict' => false];
        }

        $window = $this->buildWindow($startTime, $eventType, $customDuration);
        if (isset($window['error'])) {
            return $window['error'];
        }

        $venueConflict = $this->hasVenueConflict($chapelId, $window['date'], $window['start'], $window['end'], $excludeId);
        if (!$venueConflict) {
            return ['conflict' => false];
        }

        return [
            'conflict' => true,
            'type' => 'Venue',
            'details' => $this->formatConflictDetails($venueConflict),
            'status' => $venueConflict['status'] ?? null,
            'event_id' => (int)($venueConflict['event_id'] ?? 0),
        ];
    }

    public function checkPriestOnly($pastorId, string $startTime, string $eventType = 'Other', $excludeId = null, ?int $customDuration = null): array
    {
        $window = $this->buildWindow($startTime, $eventType, $customDuration);
        if (isset($window['error'])) {
            return $window['error'];
        }

        if ($pastorId) {
            $dutyConflict = $this->checkPriestDutyHours($pastorId, $window['start']);
            if ($dutyConflict) {
                return $dutyConflict;
            }
        }

        $priestConflict = $this->hasPriestConflict($pastorId, $window['date'], $window['start'], $window['end'], $excludeId);
        if (!$priestConflict) {
            return ['conflict' => false];
        }

        return [
            'conflict' => true,
            'type' => 'Priest',
            'details' => $this->formatConflictDetails($priestConflict),
            'status' => $priestConflict['status'] ?? null,
            'event_id' => (int)($priestConflict['event_id'] ?? 0),
        ];
    }

    private function checkPriestDutyHours($pastorId, int $startTs): ?array
    {
        $stmtD = $this->conn->prepare("SELECT duty_start_time, duty_end_time, full_name, user FROM users WHERE id = ?");
        if ($stmtD) {
            $stmtD->bind_param('i', $pastorId);
            $stmtD->execute();
            $resD = $stmtD->get_result();
            if ($pRow = $resD->fetch_assoc()) {
                if (!empty($pRow['duty_start_time']) && !empty($pRow['duty_end_time'])) {
                    $eventTimeStr = date('H:i:s', $startTs);
                    if ($eventTimeStr < $pRow['duty_start_time'] || $eventTimeStr >= $pRow['duty_end_time']) {
                        $stmtD->close();
                        $pName = !empty($pRow['full_name']) ? $pRow['full_name'] : $pRow['user'];
                        $sFmt = date('g:i A', strtotime($pRow['duty_start_time']));
                        $eFmt = date('g:i A', strtotime($pRow['duty_end_time']));
                        return [
                            'conflict' => true,
                            'type' => 'Priest',
                            'details' => "{$pName} is off-duty (Working hours: {$sFmt} – {$eFmt})",
                            'status' => 'Off-Duty',
                            'event_id' => 0
                        ];
                    }
                }
            }
            $stmtD->close();
        }
        return null;
    }

    private function buildWindow(string $startTime, string $eventType, ?int $customDuration = null): array
    {
        $newStartTs = strtotime($startTime);
        if ($newStartTs === false) {
            return [
                'error' => [
                    'conflict' => true,
                    'type' => 'System',
                    'details' => 'Invalid date format provided.',
                ],
            ];
        }

        $newDuration = $this->getEventDuration($eventType, $customDuration) * 60;
        return [
            'start' => $newStartTs,
            'end' => $newStartTs + $newDuration,
            'date' => date('Y-m-d', $newStartTs),
        ];
    }

    private function formatConflictDetails(array $row): string
    {
        return $row['title'] . ' (' . date('h:i A', strtotime($row['start_time'])) . ')';
    }

    private function hasVenueConflict($chapelId, string $dateStr, int $newStartTs, int $newEndTs, $excludeId): ?array
    {
        $sql = "SELECT event_id, title, event_type, start_time, custom_duration, status FROM events 
                WHERE ";

        if ($chapelId === null || $chapelId === '' || $chapelId == 0) {
            $sql .= "chapel_id IS NULL AND event_scope = 'cathedral'";
        } else {
            $sql .= "chapel_id = ?";
        }

        $sql .= " AND start_time >= DATE_SUB(?, INTERVAL 1 DAY) AND start_time <= DATE_ADD(?, INTERVAL 1 DAY) AND status NOT IN ('Cancelled', 'Rejected') AND is_archived = 0 AND COALESCE(event_type, '') != 'Ecclesiastical Certificates' AND COALESCE(event_type, '') NOT LIKE '%Certificate%' AND (service_id IS NULL OR service_id != 8) FOR UPDATE";

        $stmt = $this->conn->prepare($sql);
        $fullStart = date('Y-m-d H:i:s', $newStartTs);
        if ($chapelId === null || $chapelId === '' || $chapelId == 0) {
            $stmt->bind_param('ss', $fullStart, $fullStart);
        } else {
            $stmt->bind_param('iss', $chapelId, $fullStart, $fullStart);
        }
        $stmt->execute();
        $res = $stmt->get_result();

        while ($row = $res->fetch_assoc()) {
            if ($excludeId && $row['event_id'] == $excludeId) {
                continue;
            }
            if ($this->overlaps($row, $newStartTs, $newEndTs)) {
                $stmt->close();
                return $row;
            }
        }

        $stmt->close();
        return null;
    }

    private function hasPriestConflict($pastorId, string $dateStr, int $newStartTs, int $newEndTs, $excludeId): ?array
    {
        if ($pastorId === null || $pastorId === '' || (int)$pastorId === 0) {
            return null;
        }

        $stmt = $this->conn->prepare(
            "SELECT event_id, title, event_type, start_time, custom_duration, status
               FROM events
              WHERE pastor_id = ?
                AND start_time >= DATE_SUB(?, INTERVAL 1 DAY)
                AND start_time <= DATE_ADD(?, INTERVAL 1 DAY)
                AND status NOT IN ('Cancelled', 'Rejected')
                AND is_archived = 0
                AND COALESCE(event_type, '') != 'Ecclesiastical Certificates'
                AND COALESCE(event_type, '') NOT LIKE '%Certificate%'
                AND (service_id IS NULL OR service_id != 8) FOR UPDATE"
        );
        $fullStart = date('Y-m-d H:i:s', $newStartTs);
        $stmt->bind_param('iss', $pastorId, $fullStart, $fullStart);
        $stmt->execute();
        $res = $stmt->get_result();

        while ($row = $res->fetch_assoc()) {
            if ($excludeId && $row['event_id'] == $excludeId) {
                continue;
            }
            if ($this->overlaps($row, $newStartTs, $newEndTs)) {
                $stmt->close();
                return $row;
            }
        }

        $stmt->close();
        return null;
    }

    private function overlaps(array $row, int $newStartTs, int $newEndTs): bool
    {
        $existingStartStr = $row['start_time'];
        $existingTs = strtotime($existingStartStr);
        if ($existingTs === false) {
            return false;
        }

        $existingDate = date('Y-m-d', $existingTs);
        $newDate      = date('Y-m-d', $newStartTs);

        // Events on different dates never collide
        if ($existingDate !== $newDate) {
            return false;
        }

        // Check if both events fall into the Morning (< 12:00) or Afternoon (>= 12:00) slot
        $existingHour = (int)date('H', $existingTs);
        $newHour      = (int)date('H', $newStartTs);

        $existingIsMorning = ($existingHour < 12);
        $newIsMorning      = ($newHour < 12);

        return ($existingIsMorning === $newIsMorning);
    }
}
