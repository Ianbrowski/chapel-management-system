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

    private function isNonExclusiveService(string $eventType, ?int $serviceId = null): bool
    {
        $isNonExclusiveType = ($eventType === 'Ecclesiastical Certificates'
            || stripos($eventType, 'Certificate') !== false
            || stripos($eventType, 'Thanksgiving') !== false
            || stripos($eventType, 'Requiem') !== false
            || stripos($eventType, 'Intention') !== false);

        if ($isNonExclusiveType) {
            return true;
        }
        // Only honor non-exclusive service_id shortcut when event_type wasn't forged to an exclusive rite
        if ($serviceId !== null && in_array($serviceId, [4, 7, 8], true) && trim($eventType) === '') {
            return true;
        }
        return false;
    }

    /**
     * Checks for venue AND pastor time-overlap conflicts.
     * Ignores Cancelled / Rejected events.
     * Skips church venue conflict when location_type is 'Outside' (while keeping priest check).
     */
    public function checkConflict(string $startTime, $pastorId, $chapelId, string $eventType = 'Other', $excludeId = null, ?int $customDuration = null, ?int $serviceId = null, ?string $locationType = null): array
    {
        // Certificates and shared Mass Intentions do not exclusively occupy the church altar
        if ($this->isNonExclusiveService($eventType, $serviceId)) {
            return ['conflict' => false];
        }

        $window = $this->buildWindow($startTime, $eventType, $customDuration);
        if (isset($window['error'])) {
            return $window['error'];
        }

        $isOutside = ($locationType !== null && strcasecmp(trim($locationType), 'Outside') === 0);
        if (!$isOutside) {
            $venueConflict = $this->hasVenueConflict($chapelId, $window['date'], $window['start'], $window['end'], $excludeId);
            if ($venueConflict) {
                $locLabel = $this->resolveLocationLabel($venueConflict);
                $timeStr = date('h:i A', strtotime($venueConflict['start_time']));
                return [
                    'conflict' => true,
                    'type' => 'Venue',
                    'details' => $this->formatConflictDetails($venueConflict, true),
                    'masked_details' => "Booked at {$locLabel} ({$timeStr})",
                    'location' => $locLabel,
                    'chapel_id' => $venueConflict['chapel_id'] !== null ? (int)$venueConflict['chapel_id'] : null,
                    'status' => $venueConflict['status'] ?? null,
                    'event_id' => (int)($venueConflict['event_id'] ?? 0),
                ];
            }
        }

        if ($pastorId) {
            $dutyConflict = $this->checkPriestDutyHours($pastorId, $window['start'], $window['end']);
            if ($dutyConflict) {
                return $dutyConflict;
            }
        }

        $priestConflict = $this->hasPriestConflict($pastorId, $window['date'], $window['start'], $window['end'], $excludeId);
        if ($priestConflict) {
            $locLabel = $this->resolveLocationLabel($priestConflict);
            $timeStr = date('h:i A', strtotime($priestConflict['start_time']));
            return [
                'conflict' => true,
                'type' => 'Priest',
                'details' => $this->formatConflictDetails($priestConflict, true),
                'masked_details' => "Booked at {$locLabel} ({$timeStr})",
                'location' => $locLabel,
                'chapel_id' => $priestConflict['chapel_id'] !== null ? (int)$priestConflict['chapel_id'] : null,
                'status' => $priestConflict['status'] ?? null,
                'event_id' => (int)($priestConflict['event_id'] ?? 0),
            ];
        }

        return ['conflict' => false];
    }

    public function checkVenueOnly($chapelId, string $startTime, string $eventType = 'Other', $excludeId = null, ?int $customDuration = null, ?int $serviceId = null, ?string $locationType = null): array
    {
        if ($this->isNonExclusiveService($eventType, $serviceId)
            || ($locationType !== null && strcasecmp(trim($locationType), 'Outside') === 0)) {
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

        $locLabel = $this->resolveLocationLabel($venueConflict);
        $timeStr = date('h:i A', strtotime($venueConflict['start_time']));
        return [
            'conflict' => true,
            'type' => 'Venue',
            'details' => $this->formatConflictDetails($venueConflict, true),
            'masked_details' => "Booked at {$locLabel} ({$timeStr})",
            'location' => $locLabel,
            'chapel_id' => $venueConflict['chapel_id'] !== null ? (int)$venueConflict['chapel_id'] : null,
            'status' => $venueConflict['status'] ?? null,
            'event_id' => (int)($venueConflict['event_id'] ?? 0),
        ];
    }

    public function checkPriestOnly($pastorId, string $startTime, string $eventType = 'Other', $excludeId = null, ?int $customDuration = null, bool $skipDutyHoursCheck = false): array
    {
        $window = $this->buildWindow($startTime, $eventType, $customDuration);
        if (isset($window['error'])) {
            return $window['error'];
        }

        if ($pastorId && !$skipDutyHoursCheck) {
            $dutyConflict = $this->checkPriestDutyHours($pastorId, $window['start'], $window['end']);
            if ($dutyConflict) {
                return $dutyConflict;
            }
        }

        $priestConflict = $this->hasPriestConflict($pastorId, $window['date'], $window['start'], $window['end'], $excludeId);
        if (!$priestConflict) {
            return ['conflict' => false];
        }

        $locLabel = $this->resolveLocationLabel($priestConflict);
        $timeStr = date('h:i A', strtotime($priestConflict['start_time']));
        return [
            'conflict' => true,
            'type' => 'Priest',
            'details' => $this->formatConflictDetails($priestConflict, true),
            'masked_details' => "Booked at {$locLabel} ({$timeStr})",
            'location' => $locLabel,
            'chapel_id' => $priestConflict['chapel_id'] !== null ? (int)$priestConflict['chapel_id'] : null,
            'status' => $priestConflict['status'] ?? null,
            'event_id' => (int)($priestConflict['event_id'] ?? 0),
        ];
    }

    private function checkPriestDutyHours($pastorId, int $startTs, ?int $endTs = null): ?array
    {
        $stmtD = $this->conn->prepare("SELECT duty_start_time, duty_end_time, full_name, user FROM users WHERE id = ?");
        if ($stmtD) {
            $stmtD->bind_param('i', $pastorId);
            $stmtD->execute();
            $resD = $stmtD->get_result();
            if ($pRow = $resD->fetch_assoc()) {
                if (!empty($pRow['duty_start_time']) && !empty($pRow['duty_end_time'])) {
                    $eventDate    = date('Y-m-d', $startTs);
                    $dutyStartTs  = strtotime($eventDate . ' ' . $pRow['duty_start_time']);
                    $dutyEndTs    = strtotime($eventDate . ' ' . $pRow['duty_end_time']);
                    $effEndTs     = $endTs !== null ? $endTs : $startTs;

                    if ($dutyStartTs !== false && $dutyEndTs !== false && ($startTs < $dutyStartTs || $startTs >= $dutyEndTs || $effEndTs > $dutyEndTs)) {
                        $stmtD->close();
                        $pName = !empty($pRow['full_name']) ? $pRow['full_name'] : $pRow['user'];
                        $sFmt = date('g:i A', $dutyStartTs);
                        $eFmt = date('g:i A', $dutyEndTs);
                        return [
                            'conflict' => true,
                            'type' => 'Priest',
                            'details' => "{$pName} is off-duty (Working hours: {$sFmt} – {$eFmt})",
                            'masked_details' => "Off-Duty (Working hours: {$sFmt} – {$eFmt})",
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

    private function resolveLocationLabel(array $row): string
    {
        if (strcasecmp(trim((string)($row['location_type'] ?? '')), 'Outside') === 0) {
            return 'Outside Parish';
        }
        if (!empty($row['chapel_name'])) {
            return trim((string)$row['chapel_name']);
        }
        if (empty($row['chapel_id'])) {
            return 'Main Cathedral';
        }
        return 'Chapel #' . (int)$row['chapel_id'];
    }

    private function formatConflictDetails(array $row, bool $includeVenue = false): string
    {
        $base = $row['title'] . ' (' . date('h:i A', strtotime($row['start_time'])) . ')';
        if (!$includeVenue) {
            return $base;
        }
        return $base . ' — at ' . $this->resolveLocationLabel($row);
    }

    private function hasVenueConflict($chapelId, string $dateStr, int $newStartTs, int $newEndTs, $excludeId): ?array
    {
        $sql = "SELECT e.event_id, e.title, e.event_type, e.start_time, e.custom_duration, e.status, e.chapel_id, e.location_type, c.chapel_name
                  FROM events e
                  LEFT JOIN chapels c ON c.chapel_id = e.chapel_id
                 WHERE ";

        if ($chapelId === null || $chapelId === '' || $chapelId == 0) {
            $sql .= "(e.chapel_id IS NULL OR e.chapel_id = 0)";
        } else {
            $sql .= "e.chapel_id = ?";
        }

        $sql .= " AND e.start_time >= DATE_SUB(?, INTERVAL 1 DAY) AND e.start_time <= DATE_ADD(?, INTERVAL 1 DAY) AND e.status NOT IN ('Cancelled', 'Rejected') AND e.is_archived = 0 AND COALESCE(e.event_type, '') != 'Ecclesiastical Certificates' AND COALESCE(e.event_type, '') NOT LIKE '%Certificate%' AND COALESCE(e.location_type, 'Church') != 'Outside' AND (e.service_id IS NULL OR e.service_id NOT IN (4, 7, 8)) FOR UPDATE";

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
            "SELECT e.event_id, e.title, e.event_type, e.start_time, e.custom_duration, e.status, e.chapel_id, e.location_type, c.chapel_name
               FROM events e
               LEFT JOIN chapels c ON c.chapel_id = e.chapel_id
              WHERE e.pastor_id = ?
                AND e.start_time >= DATE_SUB(?, INTERVAL 1 DAY)
                AND e.start_time <= DATE_ADD(?, INTERVAL 1 DAY)
                AND e.status NOT IN ('Cancelled', 'Rejected')
                AND e.is_archived = 0
                AND COALESCE(e.event_type, '') != 'Ecclesiastical Certificates'
                AND COALESCE(e.event_type, '') NOT LIKE '%Certificate%'
                AND (e.service_id IS NULL OR e.service_id NOT IN (4, 7, 8)) FOR UPDATE"
        );
        $fullStart = date('Y-m-d H:i:s', $newStartTs);
        $stmt->bind_param('iss', $pastorId, $fullStart, $fullStart);
        $stmt->execute();
        $res = $stmt->get_result();

        while ($row = $res->fetch_assoc()) {
            if ($excludeId && $row['event_id'] == $excludeId) {
                continue;
            }
            if ($this->priestIntervalsOverlap($row, $newStartTs, $newEndTs)) {
                $stmt->close();
                return $row;
            }
        }

        $stmt->close();
        return null;
    }

    private function priestIntervalsOverlap(array $row, int $newStartTs, int $newEndTs): bool
    {
        $existingTs = strtotime((string)($row['start_time'] ?? ''));
        if ($existingTs === false) {
            return false;
        }
        $existingDurMin = $this->getEventDuration(
            (string)($row['event_type'] ?? 'Other'),
            isset($row['custom_duration']) && (int)$row['custom_duration'] > 0 ? (int)$row['custom_duration'] : null
        );
        $existingEndTs = $existingTs + ($existingDurMin * 60);
        return ($existingTs < $newEndTs) && ($newStartTs < $existingEndTs);
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
