<?php
require_once __DIR__ . '/../services/EventConflictService.php';
require_once __DIR__ . '/../services/EventAnalyticsService.php';

class Event
{
    private const NON_CERT_SQL = "(COALESCE(event_type, '') != 'Ecclesiastical Certificates' AND COALESCE(event_type, '') NOT LIKE '%Certificate%' AND (service_id IS NULL OR service_id != 8))";
    private const NON_CERT_E_SQL = "(COALESCE(e.event_type, '') != 'Ecclesiastical Certificates' AND COALESCE(e.event_type, '') NOT LIKE '%Certificate%' AND (e.service_id IS NULL OR e.service_id != 8))";

    private $conn;
    private EventConflictService $conflicts;
    private EventAnalyticsService $analytics;

    public function __construct($conn)
    {
        $this->conn = $conn;
        $this->conflicts = new EventConflictService($conn);
        $this->analytics = new EventAnalyticsService($conn);
    }

    private function getChapelDisplayName(?int $chapelId): ?string
    {
        if ($chapelId === null) {
            return 'Main Cathedral';
        }

        $stmt = $this->conn->prepare("SELECT chapel_name FROM chapels WHERE chapel_id = ? LIMIT 1");
        if (!$stmt) {
            return null;
        }

        $stmt->bind_param('i', $chapelId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $row['chapel_name'] ?? null;
    }

    private function getPastorDisplayName(?int $pastorId): ?string
    {
        if ($pastorId === null) {
            return null;
        }

        $stmt = $this->conn->prepare("SELECT COALESCE(full_name, `user`) AS display_name FROM users WHERE id = ? LIMIT 1");
        if (!$stmt) {
            return null;
        }

        $stmt->bind_param('i', $pastorId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $row['display_name'] ?? null;
    }

    private function syncEventDisplayFields(int $eventId, ?int $chapelId, ?int $pastorId): void
    {
        $chapelLocation = $this->getChapelDisplayName($chapelId);
        $pastorAssigned = $this->getPastorDisplayName($pastorId);

        $stmt = $this->conn->prepare("UPDATE events SET chapel_location = ?, pastor_assigned = ? WHERE event_id = ?");
        if (!$stmt) {
            return;
        }

        $stmt->bind_param('ssi', $chapelLocation, $pastorAssigned, $eventId);
        $stmt->execute();
        $stmt->close();
    }

    // ──────────────────────────────────────────────────────────────────────────
    // CORE CRUD
    // ──────────────────────────────────────────────────────────────────────────

    public function create($title, $eventType, $startTime, $chapelId, $pastorId, $status, $scope, $customDuration = null, $phone = null, $email = null, $requestedBy = null)
    {
        $cd = ($customDuration !== null && (int)$customDuration > 0) ? (int)$customDuration : null;
        $chapelId = ($chapelId && (int)$chapelId > 0) ? (int)$chapelId : null;
        $pastorId = ($pastorId !== null && (int)$pastorId > 0) ? (int)$pastorId : null;
        $reqById = ($requestedBy !== null && (int)$requestedBy > 0) ? (int)$requestedBy : null;

        if ($status === 'Confirmed') {
            $sql = "INSERT INTO events (title, event_type, start_time, chapel_id, pastor_id, status, event_scope, custom_duration, approved_at, requester_phone, requester_email, requested_by) VALUES (?,?,?,?,?,?,?,?, NOW(), ?, ?, ?)";
        } else {
            $sql = "INSERT INTO events (title, event_type, start_time, chapel_id, pastor_id, status, event_scope, custom_duration, approved_at, requester_phone, requester_email, requested_by) VALUES (?,?,?,?,?,?,?,?, NULL, ?, ?, ?)";
        }
        $s = $this->conn->prepare($sql);
        if (!$s) {
            return false;
        }
        $s->bind_param('sssiississi', $title, $eventType, $startTime, $chapelId, $pastorId, $status, $scope, $cd, $phone, $email, $reqById);
        $res = $s->execute();
        if (!$res) {
            $_SESSION['db_error'] = $s->error;
        }
        $eid = (int)$this->conn->insert_id;
        if ($res) {
            $this->syncEventDisplayFields((int)$this->conn->insert_id, $chapelId, $pastorId);
        }
        $s->close();
        return $res ? $eid : false;
    }

    public function update($id, $title, $eventType, $startTime, $chapelId, $pastorId, $status, $scope, $customDuration = null, $phone = null, $email = null)
    {
        // If status is updated to confirmed and approved_at is null, set it to now
        $check = $this->conn->prepare("SELECT approved_at FROM events WHERE event_id=?");
        if (!$check) {
            return false;
        }
        $check->bind_param('i', $id);
        $check->execute();
        $old_approved = $check->get_result()->fetch_assoc()['approved_at'] ?? null;
        $check->close();

        $approved_sql = "";
        if ($status === 'Confirmed' && !$old_approved) {
            $approved_sql = ", approved_at=NOW()";
        }

        $cd = ($customDuration !== null && (int)$customDuration > 0) ? (int)$customDuration : null;
        $chapelId = ($chapelId && (int)$chapelId > 0) ? (int)$chapelId : null;
        $pastorId = ($pastorId !== null && (int)$pastorId > 0) ? (int)$pastorId : null;

        $sql = "UPDATE events SET title=?, event_type=?, start_time=?, chapel_id=?, pastor_id=?, status=?, event_scope=?, custom_duration=?, requester_phone=?, requester_email=? $approved_sql WHERE event_id=?";
        $s = $this->conn->prepare($sql);
        if (!$s) {
            return false;
        }
        $s->bind_param('sssiississi', $title, $eventType, $startTime, $chapelId, $pastorId, $status, $scope, $cd, $phone, $email, $id);
        $res = $s->execute();
        if ($res) {
            $this->syncEventDisplayFields($id, $chapelId, $pastorId);
        }
        $s->close();
        return $res;
    }

    public function delete($id)
    {
        return $this->archive($id);
    }

    public function archive($id)
    {
        $d = $this->conn->prepare("UPDATE events SET is_archived=1 WHERE event_id=?");
        if (!$d) {
            return false;
        }
        $d->bind_param('i', $id);
        $res = $d->execute();
        $d->close();
        return $res;
    }

    public function restore($id)
    {
        $d = $this->conn->prepare("UPDATE events SET is_archived=0 WHERE event_id=?");
        if (!$d) {
            return false;
        }
        $d->bind_param('i', $id);
        $res = $d->execute();
        $d->close();
        return $res;
    }

    public function getById($id, $includeArchived = false)
    {
        $where = "WHERE e.event_id = ?";
        if (!$includeArchived) {
            $where .= " AND e.is_archived = 0";
        }
        $stmt = $this->conn->prepare(
            "SELECT e.*, IFNULL(c.chapel_name, e.chapel_location) as chapel_location, 
                    COALESCE(NULLIF(u.full_name, ''), u.user) as pastor_assigned,
                    COALESCE(NULLIF(u_req.full_name, ''), u_req.user, 'System / Automated') as encoded_by_name
             FROM events e
             LEFT JOIN chapels c ON e.chapel_id = c.chapel_id
             LEFT JOIN users u ON e.pastor_id = u.id
             LEFT JOIN users u_req ON e.requested_by = u_req.id
             $where
             LIMIT 1"
        );
        if (!$stmt) {
            return null;
        }

        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $row ?: null;
    }

    public function requestEvent($title, $eventType, $startTime, $chapelId, $scope, $reqBy, $requesterEmail = null, $status = 'Pending', $pastorId = null, $customDuration = null, $requesterPhone = null, $serviceId = null, $variantId = null, $serviceFee = 0.00, $addonsFee = 0.00, $totalFee = 0.00, $locationType = 'Church')
    {
        $cd = ($customDuration !== null && (int)$customDuration > 0) ? (int)$customDuration : null;
        $chapelId = ($chapelId && (int)$chapelId > 0) ? (int)$chapelId : null;
        $pastorId = ($pastorId !== null && (int)$pastorId > 0) ? (int)$pastorId : null;
        $reqBy = ($reqBy !== null && (int)$reqBy > 0) ? (int)$reqBy : null;
        $approvedAt = ($status === 'Confirmed') ? date('Y-m-d H:i:s') : null;
        $sId = ($serviceId && (int)$serviceId > 0) ? (int)$serviceId : null;
        $vId = ($variantId && (int)$variantId > 0) ? (int)$variantId : null;
        $locType = in_array($locationType, ['Church', 'Outside']) ? $locationType : 'Church';

        $s = $this->conn->prepare("INSERT INTO events (title, event_type, service_id, variant_id, start_time, chapel_id, status, event_scope, requested_by, requester_email, pastor_id, custom_duration, service_fee, addons_fee, total_fee, location_type, approved_at, requester_phone) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        if (!$s) {
            return false;
        }
        $s->bind_param('ssiisssissidddssss', $title, $eventType, $sId, $vId, $startTime, $chapelId, $status, $scope, $reqBy, $requesterEmail, $pastorId, $cd, $serviceFee, $addonsFee, $totalFee, $locType, $approvedAt, $requesterPhone);
        $res = $s->execute();
        $eid = (int)$this->conn->insert_id;
        if (!$res) {
            $_SESSION['db_error'] = $s->error;
        }
        if ($res) {
            $this->syncEventDisplayFields($eid, $chapelId, $pastorId);
        }
        $s->close();
        return $res ? $eid : false;
    }

    public function addRequestAddon($eventId, $addonId, $addonName, $unitPrice, $quantity = 1, $subtotal = null)
    {
        $sub = $subtotal ?? ($unitPrice * $quantity);
        $stmt = $this->conn->prepare("INSERT INTO service_request_addons (event_id, addon_id, addon_name, unit_price, quantity, subtotal) VALUES (?, ?, ?, ?, ?, ?)");
        if (!$stmt) return false;
        $stmt->bind_param('iisdid', $eventId, $addonId, $addonName, $unitPrice, $quantity, $sub);
        $res = $stmt->execute();
        $stmt->close();
        return $res;
    }

    public function getRequestAddons($eventId): array
    {
        $stmt = $this->conn->prepare("SELECT * FROM service_request_addons WHERE event_id = ? ORDER BY id ASC");
        if (!$stmt) return [];
        $stmt->bind_param('i', $eventId);
        $stmt->execute();
        $res = $stmt->get_result();
        $addons = [];
        while ($r = $res->fetch_assoc()) $addons[] = $r;
        $stmt->close();
        return $addons;
    }

    public function approveRequest($id, $pastorId)
    {
        $pastorId = ($pastorId && (int)$pastorId > 0) ? (int)$pastorId : null;
        if ($pastorId === null) {
            $existing = $this->getById($id);
            $pastorId = !empty($existing['pastor_id']) ? (int)$existing['pastor_id'] : null;
        }
        $s = $this->conn->prepare("UPDATE events SET status='Confirmed', pastor_id=?, approved_at=NOW(), rejection_note=NULL WHERE event_id=? AND status='Pending'");
        if (!$s) {
            return false;
        }
        $s->bind_param('ii', $pastorId, $id);
        $res = $s->execute();
        if ($res) {
            $row = $this->getById($id);
            if ($row) {
                $this->syncEventDisplayFields($id, isset($row['chapel_id']) ? (int)$row['chapel_id'] : null, $pastorId);
            }
        }
        $s->close();
        return $res;
    }

    public function rejectRequest($id, $note)
    {
        $s = $this->conn->prepare("UPDATE events SET status='Cancelled', rejection_note=?, approved_at=NOW() WHERE event_id=? AND status='Pending'");
        if (!$s) {
            return false;
        }
        $s->bind_param('si', $note, $id);
        $res = $s->execute();
        $s->close();
        return $res;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // DURATION ENGINE
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Returns the effective duration (in minutes) for an event.
     *
     * Priority:
     *   1. $customDuration (if > 0) — used as the TOTAL duration
     *   2. Default map based on event type
     *
     * @param string   $eventType     The event type string
     * @param int|null $customDuration Override total duration in minutes (optional)
     * @return int Duration in minutes
     */
    public function getEventDuration($eventType, $customDuration = null)
    {
        return $this->conflicts->getEventDuration((string) $eventType, $customDuration !== null ? (int) $customDuration : null);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // CONFLICT DETECTION
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Checks for venue AND pastor time-overlap conflicts.
     * Ignores Cancelled / Rejected events.
     * Skips the event being edited (via $excludeId = self-conflict fix).
     *
     * @param int|null $customDuration Total duration override (minutes) for the NEW event
     */
    public function checkConflict($startTime, $pastorId, $chapelId, $eventType = 'Other', $excludeId = null, $customDuration = null)
    {
        return $this->conflicts->checkConflict(
            (string) $startTime,
            $pastorId,
            $chapelId,
            (string) $eventType,
            $excludeId,
            $customDuration !== null ? (int) $customDuration : null
        );
    }

    // ──────────────────────────────────────────────────────────────────────────
    // READS / QUERIES
    // ──────────────────────────────────────────────────────────────────────────

    public function getAll($limit = 100, $chapelId = null)
    {
        $events = [];
        $l = intval($limit);
        if ($chapelId !== null) {
            $isCathedral = ($chapelId === 'cathedral' || (is_numeric($chapelId) && (int)$chapelId === 0));
            if ($isCathedral) {
                $sql = "SELECT e.*, IFNULL(c.chapel_name, e.chapel_location) as chapel_location, 
                               c.theme_color as chapel_theme_color,
                               COALESCE(NULLIF(u.full_name, ''), u.user) as pastor_assigned,
                               COALESCE(NULLIF(u_req.full_name, ''), u_req.user, 'System / Automated') as encoded_by_name
                        FROM events e
                        LEFT JOIN chapels c ON e.chapel_id = c.chapel_id
                        LEFT JOIN users u ON e.pastor_id = u.id
                        LEFT JOIN users u_req ON e.requested_by = u_req.id
                        WHERE (e.chapel_id IS NULL OR e.chapel_id = 0) AND e.is_archived = 0 AND " . self::NON_CERT_E_SQL . "
                        ORDER BY e.start_time DESC LIMIT ?";
                $s = $this->conn->prepare($sql);
                if (!$s) {
                    return $events;
                }
                $s->bind_param('i', $l);
            } else {
                $s = $this->conn->prepare("SELECT e.*, IFNULL(c.chapel_name, e.chapel_location) as chapel_location, 
                                                  c.theme_color as chapel_theme_color,
                                                  COALESCE(NULLIF(u.full_name, ''), u.user) as pastor_assigned,
                                                  COALESCE(NULLIF(u_req.full_name, ''), u_req.user, 'System / Automated') as encoded_by_name
                   FROM events e
                   LEFT JOIN chapels c ON e.chapel_id = c.chapel_id
                   LEFT JOIN users u ON e.pastor_id = u.id
                   LEFT JOIN users u_req ON e.requested_by = u_req.id
                   WHERE e.chapel_id = ? AND e.is_archived = 0 AND " . self::NON_CERT_E_SQL . "
                   ORDER BY e.start_time DESC LIMIT ?");
                if (!$s) {
                    return $events;
                }
                $cid = (int)$chapelId;
                $s->bind_param('ii', $cid, $l);
            }
        } else {
            $s = $this->conn->prepare("SELECT e.*, IFNULL(c.chapel_name, e.chapel_location) as chapel_location, 
                                              c.theme_color as chapel_theme_color,
                                              COALESCE(NULLIF(u.full_name, ''), u.user) as pastor_assigned,
                                              COALESCE(NULLIF(u_req.full_name, ''), u_req.user, 'System / Automated') as encoded_by_name
               FROM events e
               LEFT JOIN chapels c ON e.chapel_id = c.chapel_id
               LEFT JOIN users u ON e.pastor_id = u.id
               LEFT JOIN users u_req ON e.requested_by = u_req.id
               WHERE e.is_archived = 0 AND " . self::NON_CERT_E_SQL . "
               ORDER BY e.start_time DESC LIMIT ?");
            if (!$s) {
                return $events;
            }
            $s->bind_param('i', $l);
        }
        $s->execute();
        $res = $s->get_result();
        while ($r = $res->fetch_assoc()) $events[] = $r;
        $s->close();
        return $events;
    }

    public function getByPriest($username, $order = 'DESC', $limit = 100)
    {
        // Notice we still accept the username here to map from the login logic, but we query user id
        $events = [];
        $ord = $order === 'ASC' ? 'ASC' : 'DESC';
        $ps = $this->conn->prepare("SELECT e.*, IFNULL(c.chapel_name, e.chapel_location) as chapel_location, 
                                            COALESCE(NULLIF(pu.full_name, ''), pu.user) as pastor_assigned,
                                            COALESCE(NULLIF(u_req.full_name, ''), u_req.user, 'System / Automated') as encoded_by_name
                                    FROM events e 
                                    JOIN users pu ON e.pastor_id = pu.id 
                                    LEFT JOIN chapels c ON e.chapel_id = c.chapel_id 
                                    LEFT JOIN users u_req ON e.requested_by = u_req.id
                                    WHERE pu.user = ? AND e.is_archived = 0
                                    ORDER BY e.start_time $ord" . ($limit ? " LIMIT " . intval($limit) : ""));
        if (!$ps) {
            return $events;
        }
        $ps->bind_param('s', $username);
        $ps->execute();
        $res = $ps->get_result();
        while ($row = $res->fetch_assoc())
            $events[] = $row;
        $ps->close();
        return $events;
    }

    public function getByPastor($username, $order = 'DESC', $limit = 100)
    {
        return $this->getByPriest($username, $order, $limit);
    }

    public function getByScope($scope, $limit = 100, $chapelId = null)
    {
        $events = [];
        $where = "WHERE e.event_scope=? AND e.is_archived=0";
        $params = [$scope];
        $types = "s";
        
        if ($chapelId) {
            $where .= " AND e.chapel_id = ?";
            $params[] = (int)$chapelId;
            $types .= "i";
        }
        
        $sql = "SELECT e.*, IFNULL(c.chapel_name, e.chapel_location) as chapel_location, 
                       COALESCE(NULLIF(u.full_name, ''), u.user) as pastor_assigned,
                       COALESCE(NULLIF(u_req.full_name, ''), u_req.user, 'System / Automated') as encoded_by_name
                FROM events e 
                LEFT JOIN chapels c ON e.chapel_id = c.chapel_id 
                LEFT JOIN users u ON e.pastor_id = u.id 
                LEFT JOIN users u_req ON e.requested_by = u_req.id
                $where ORDER BY e.start_time DESC LIMIT ?";
        
        $params[] = intval($limit);
        $types .= "i";
        
        $s = $this->conn->prepare($sql);
        if (!$s) {
            return $events;
        }
        $s->bind_param($types, ...$params);
        $s->execute();
        $res = $s->get_result();
        while ($r = $res->fetch_assoc())
            $events[] = $r;
        $s->close();
        return $events;
    }

    public function getPendingRequests($chapelId = null, $page = 1, $perPage = 50, array $filters = [])
    {
        $requests = [];
        $st = strtolower($filters['status'] ?? 'pending');
        if ($st === 'all') {
            $where = "WHERE e.is_archived = 0 AND " . self::NON_CERT_E_SQL;
        } elseif ($st === 'confirmed' || $st === 'approved') {
            $where = "WHERE e.status = 'Confirmed' AND e.is_archived = 0 AND " . self::NON_CERT_E_SQL;
        } elseif ($st === 'rejected' || $st === 'cancelled') {
            $where = "WHERE e.status IN ('Rejected', 'Cancelled') AND e.is_archived = 0 AND " . self::NON_CERT_E_SQL;
        } elseif ($st === 'urgent') {
            $where = "WHERE e.status = 'Pending' AND e.is_archived = 0 AND " . self::NON_CERT_E_SQL . " AND (e.start_time < NOW() OR e.start_time <= DATE_ADD(NOW(), INTERVAL 3 DAY))";
        } else {
            $where = "WHERE e.status = 'Pending' AND e.is_archived = 0 AND " . self::NON_CERT_E_SQL;
        }
        $params = [];
        $types = "";

        if ($chapelId !== null) {
            $isCathedral = ($chapelId === 'cathedral' || (is_numeric($chapelId) && (int)$chapelId === 0));
            if ($isCathedral) {
                $where .= " AND (e.chapel_id IS NULL OR e.chapel_id = 0)";
            } else {
                $where .= " AND e.chapel_id = ?";
                $params[] = (int)$chapelId;
                $types .= "i";
            }
        }

        // Event type filter
        if (!empty($filters['event_type'])) {
            $where .= " AND e.event_type = ?";
            $params[] = $filters['event_type'];
            $types .= "s";
        }

        // Search filter (title, requester name, phone, email)
        if (!empty($filters['search'])) {
            $searchTerm = '%' . trim($filters['search']) . '%';
            $where .= " AND (e.title LIKE ? OR u.full_name LIKE ? OR u.user LIKE ? OR e.requester_phone LIKE ? OR e.requester_email LIKE ?)";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $types .= "sssss";
        }

        // Timeframe filter (Recently added / submitted)
        if (!empty($filters['timeframe'])) {
            if ($filters['timeframe'] === 'recently_added' || $filters['timeframe'] === 'recently_added_30') {
                $where .= " AND e.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
            } elseif ($filters['timeframe'] === 'recently_added_7') {
                $where .= " AND e.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
            }
        }

        // Count total
        $countSql = "SELECT COUNT(*) as total FROM events e 
                     LEFT JOIN users u ON e.requested_by = u.id 
                     $where";
        $total = 0;
        $cstmt = $this->conn->prepare($countSql);
        if ($cstmt) {
            if ($params) $cstmt->bind_param($types, ...$params);
            $cstmt->execute();
            $total = $cstmt->get_result()->fetch_assoc()['total'] ?? 0;
            $cstmt->close();
        }

        $pages = (int)ceil($total / $perPage);
        $page = max(1, min($page, $pages ?: 1));
        $offset = ($page - 1) * $perPage;

        // Sort order
        $sortOrder = "e.start_time ASC";
        if (!empty($filters['sort'])) {
            if ($filters['sort'] === 'date_desc') {
                $sortOrder = "e.start_time DESC";
            } elseif ($filters['sort'] === 'recently_added' || $filters['sort'] === 'created_desc') {
                $sortOrder = "e.created_at DESC";
            }
        }

        $sql = "SELECT e.*, COALESCE(NULLIF(u.full_name, ''), u.user, 'Walk-in / Parish Office') as requester_name,
                       COALESCE(NULLIF(u.full_name, ''), u.user, 'System / Automated') as encoded_by_name,
                       IFNULL(c.chapel_name, e.chapel_location) as chapel_location,
                       COALESCE(NULLIF(pu.full_name, ''), pu.user) as pastor_assigned
                FROM events e
                LEFT JOIN users u ON e.requested_by = u.id
                LEFT JOIN chapels c ON e.chapel_id = c.chapel_id
                LEFT JOIN users pu ON e.pastor_id = pu.id
                $where
                ORDER BY $sortOrder
                LIMIT ? OFFSET ?";
        
        $fparams = array_merge($params, [$perPage, $offset]);
        $ftypes = $types . "ii";

        $s = $this->conn->prepare($sql);
        if ($s) {
            $s->bind_param($ftypes, ...$fparams);
            $s->execute();
            $res = $s->get_result();
            while ($row = $res->fetch_assoc()) $requests[] = $row;
            $s->close();
        }

        return [
            'records' => $requests,
            'total'   => (int)$total,
            'pages'   => $pages,
            'page'    => $page
        ];
    }

    public function getAdminRequestQueueCounts($chapelId = null): array
    {
        $chapelClause = "";
        $params = [];
        $types = "";
        if ($chapelId !== null) {
            $isCathedral = ($chapelId === 'cathedral' || (is_numeric($chapelId) && (int)$chapelId === 0));
            if ($isCathedral) {
                $chapelClause = " AND (chapel_id IS NULL OR chapel_id = 0)";
            } else {
                $chapelClause = " AND chapel_id = ?";
                $params[] = (int)$chapelId;
                $types = "i";
            }
        }

        $sql = "
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'Pending' AND (start_time < NOW() OR start_time <= DATE_ADD(NOW(), INTERVAL 3 DAY)) THEN 1 ELSE 0 END) as urgent,
                SUM(CASE WHEN status = 'Confirmed' THEN 1 ELSE 0 END) as confirmed,
                SUM(CASE WHEN status IN ('Rejected', 'Cancelled') THEN 1 ELSE 0 END) as rejected
            FROM events
            WHERE is_archived = 0 AND " . self::NON_CERT_SQL . " $chapelClause
        ";
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            return ['total' => 0, 'pending' => 0, 'urgent' => 0, 'confirmed' => 0, 'rejected' => 0];
        }
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return [
            'total'     => (int)($res['total'] ?? 0),
            'pending'   => (int)($res['pending'] ?? 0),
            'urgent'    => (int)($res['urgent'] ?? 0),
            'confirmed' => (int)($res['confirmed'] ?? 0),
            'rejected'  => (int)($res['rejected'] ?? 0),
        ];
    }

    public function getPendingCount($chapelId = null): int
    {
        $sql = "SELECT COUNT(*) as total FROM events WHERE status = 'Pending' AND is_archived = 0 AND " . self::NON_CERT_SQL;
        if ($chapelId !== null) {
            $isCathedral = ($chapelId === 'cathedral' || (is_numeric($chapelId) && (int)$chapelId === 0));
            if ($isCathedral) {
                $sql .= " AND (chapel_id IS NULL OR chapel_id = 0)";
                $s = $this->conn->prepare($sql);
            } else {
                $sql .= " AND chapel_id = ?";
                $s = $this->conn->prepare($sql);
                if ($s) {
                    $cid = (int)$chapelId;
                    $s->bind_param('i', $cid);
                }
            }
        } else {
            $s = $this->conn->prepare($sql);
        }

        if (!$s) {
            return 0;
        }

        $s->execute();
        $res = $s->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $s->close();
        return (int)($row['total'] ?? 0);
    }

    public function getRequestCountsByUser(int $userId): array
    {
        $stmt = $this->conn->prepare("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'Confirmed' THEN 1 ELSE 0 END) as confirmed,
                SUM(CASE WHEN status IN ('Cancelled', 'Rejected') THEN 1 ELSE 0 END) as rejected
            FROM events
            WHERE requested_by = ? AND is_archived = 0
              AND (event_type != 'Ecclesiastical Certificates' AND (service_id IS NULL OR service_id != 8))
        ");
        if (!$stmt) {
            return ['total' => 0, 'pending' => 0, 'confirmed' => 0, 'rejected' => 0];
        }
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return [
            'total'     => (int)($res['total'] ?? 0),
            'pending'   => (int)($res['pending'] ?? 0),
            'confirmed' => (int)($res['confirmed'] ?? 0),
            'rejected'  => (int)($res['rejected'] ?? 0),
        ];
    }

    public function getCertificateRequestCounts(?int $userId = null, ?int $chapelId = null): array
    {
        $where = "is_archived = 0 AND (event_type = 'Ecclesiastical Certificates' OR event_type LIKE '%Certificate%' OR service_id = 8)";
        $params = [];
        $types = "";
        if ($userId !== null) {
            $where .= " AND requested_by = ?";
            $params[] = $userId;
            $types .= "i";
        }
        if ($chapelId !== null) {
            $where .= " AND chapel_id = ?";
            $params[] = $chapelId;
            $types .= "i";
        }
        $stmt = $this->conn->prepare("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'Confirmed' THEN 1 ELSE 0 END) as confirmed
            FROM events
            WHERE $where
        ");
        if (!$stmt) {
            return ['total' => 0, 'pending' => 0, 'confirmed' => 0];
        }
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return [
            'total'     => (int)($res['total'] ?? 0),
            'pending'   => (int)($res['pending'] ?? 0),
            'confirmed' => (int)($res['confirmed'] ?? 0),
        ];
    }

    public function getOverduePendingRequests($chapelId = null)
    {
        $requests = [];
        $sql = "SELECT e.*, COALESCE(NULLIF(u.full_name, ''), u.user, 'Walk-in / Parish Office') as requester_name,
                       COALESCE(NULLIF(u.full_name, ''), u.user, 'System / Automated') as encoded_by_name,
                       IFNULL(c.chapel_name, e.chapel_location) as chapel_location,
                       COALESCE(NULLIF(pu.full_name, ''), pu.user) as pastor_assigned
                FROM events e
                LEFT JOIN users u ON e.requested_by = u.id
                LEFT JOIN chapels c ON e.chapel_id = c.chapel_id
                LEFT JOIN users pu ON e.pastor_id = pu.id
                WHERE e.status='Pending' AND e.is_archived = 0 AND e.start_time < NOW() AND " . self::NON_CERT_E_SQL;
        if ($chapelId !== null) {
            $isCathedral = ($chapelId === 'cathedral' || (is_numeric($chapelId) && (int)$chapelId === 0));
            if ($isCathedral) {
                $s = $this->conn->prepare($sql . " AND (e.chapel_id IS NULL OR e.chapel_id = 0) ORDER BY e.start_time ASC");
                if (!$s) {
                    return $requests;
                }
            } else {
                $s = $this->conn->prepare($sql . " AND e.chapel_id = ? ORDER BY e.start_time ASC");
                if (!$s) {
                    return $requests;
                }
                $cid = (int)$chapelId;
                $s->bind_param('i', $cid);
            }
        } else {
            $s = $this->conn->prepare($sql . " ORDER BY e.start_time ASC");
            if (!$s) {
                return $requests;
            }
        }
        $s->execute();
        $res = $s->get_result();
        while ($row = $res->fetch_assoc()) {
            $requests[] = $row;
        }
        $s->close();
        return $requests;
    }

    public function getMyRequests($userId, $limit = 50)
    {
        $requests = [];
        $mr = $this->conn->prepare("SELECT e.*, IFNULL(c.chapel_name, e.chapel_location) as chapel_location, COALESCE(NULLIF(u.full_name, ''), u.user) as pastor_assigned 
                                    FROM events e 
                                    LEFT JOIN chapels c ON e.chapel_id = c.chapel_id 
                                    LEFT JOIN users u ON e.pastor_id = u.id 
                                    WHERE e.requested_by = ? AND e.is_archived = 0 ORDER BY e.start_time DESC LIMIT ?");
        if (!$mr) {
            return $requests;
        }
        $l = intval($limit);
        $mr->bind_param('ii', $userId, $l);
        $mr->execute();
        $res = $mr->get_result();
        while ($row = $res->fetch_assoc())
            $requests[] = $row;
        $mr->close();
        return $requests;
    }

    public function getUpcoming($limit = 5, $chapelId = null)
    {
        return $this->analytics->getUpcoming((int) $limit, $chapelId);
    }

    public function exportCsv($from, $to, $chapelId = null)
    {
        return $this->analytics->exportCsv($from, $to, $chapelId);
    }

    public function getCount($chapelId = null)
    {
        if ($chapelId !== null) {
            $isCathedral = ($chapelId === 'cathedral' || (is_numeric($chapelId) && (int)$chapelId === 0));
            if ($isCathedral) {
                $s = $this->conn->prepare("SELECT COUNT(*) as c FROM events WHERE (chapel_id IS NULL OR chapel_id = 0) AND is_archived = 0 AND " . self::NON_CERT_SQL);
                if (!$s) {
                    return 0;
                }
            } else {
                $s = $this->conn->prepare("SELECT COUNT(*) as c FROM events WHERE chapel_id = ? AND is_archived = 0 AND " . self::NON_CERT_SQL);
                if (!$s) {
                    return 0;
                }
                $cid = (int)$chapelId;
                $s->bind_param('i', $cid);
            }
        } else {
            $s = $this->conn->prepare("SELECT COUNT(*) as c FROM events WHERE is_archived = 0 AND " . self::NON_CERT_SQL);
            if (!$s) {
                return 0;
            }
        }
        $s->execute();
        $c = $s->get_result()->fetch_assoc()['c'] ?? 0;
        $s->close();
        return (int)$c;
    }

    public function getArchivedCount($chapelId = null)
    {
        if ($chapelId !== null) {
            $isCathedral = ($chapelId === 'cathedral' || (is_numeric($chapelId) && (int)$chapelId === 0));
            if ($isCathedral) {
                $s = $this->conn->prepare("SELECT COUNT(*) as c FROM events WHERE (chapel_id IS NULL OR chapel_id = 0) AND is_archived = 1 AND " . self::NON_CERT_SQL);
                if (!$s) {
                    return 0;
                }
            } else {
                $s = $this->conn->prepare("SELECT COUNT(*) as c FROM events WHERE chapel_id = ? AND is_archived = 1 AND " . self::NON_CERT_SQL);
                if (!$s) {
                    return 0;
                }
                $cid = (int)$chapelId;
                $s->bind_param('i', $cid);
            }
        } else {
            $s = $this->conn->prepare("SELECT COUNT(*) as c FROM events WHERE is_archived = 1 AND " . self::NON_CERT_SQL);
            if (!$s) {
                return 0;
            }
        }
        $s->execute();
        $c = $s->get_result()->fetch_assoc()['c'] ?? 0;
        $s->close();
        return (int)$c;
    }

    public function getCountByStatus($status, $chapelId = null)
    {
        $where = "WHERE status=? AND is_archived=0 AND " . self::NON_CERT_SQL;
        $params = [$status];
        $types = "s";
        
        if ($chapelId !== null) {
            $isCathedral = ($chapelId === 'cathedral' || (is_numeric($chapelId) && (int)$chapelId === 0));
            if ($isCathedral) {
                $where .= " AND (chapel_id IS NULL OR chapel_id = 0)";
            } else {
                $where .= " AND chapel_id = ?";
                $params[] = (int)$chapelId;
                $types .= "i";
            }
        }
        
        $s = $this->conn->prepare("SELECT COUNT(*) as c FROM events $where");
        if (!$s) {
            return 0;
        }
        $s->bind_param($types, ...$params);
        $s->execute();
        $c = $s->get_result()->fetch_assoc()['c'] ?? 0;
        $s->close();
        return (int)$c;
    }

    public function getChartTypes($chapelId = null)
    {
        return $this->analytics->getChartTypes($chapelId);
    }

    public function getChartStatus($chapelId = null)
    {
        return $this->analytics->getChartStatus($chapelId);
    }

    public function getChartMonthly($chapelId = null)
    {
        return $this->analytics->getChartMonthly($chapelId);
    }

    public function getPriestLocations($username)
    {
        $locations = [];
        $pl = $this->conn->prepare("SELECT DISTINCT IFNULL(c.chapel_name, e.chapel_location) as chapel_location 
                                    FROM events e 
                                    JOIN users pu ON e.pastor_id = pu.id 
                                    LEFT JOIN chapels c ON e.chapel_id = c.chapel_id 
                                    WHERE pu.user = ? AND e.is_archived = 0 AND (e.chapel_id IS NOT NULL OR e.chapel_location != '') 
                                    ORDER BY chapel_location ASC");
        if (!$pl) {
            return $locations;
        }
        $pl->bind_param('s', $username);
        $pl->execute();
        $res = $pl->get_result();
        while ($row = $res->fetch_assoc())
            $locations[] = $row['chapel_location'];
        $pl->close();
        return $locations;
    }

    public function getPriestWorkload($chapelId = null)
    {
        return $this->analytics->getPriestWorkload($chapelId);
    }

    public function getPastorLocations($username)
    {
        return $this->getPriestLocations($username);
    }

    public function getPastorWorkload($chapelId = null)
    {
        return $this->getPriestWorkload($chapelId);
    }

    public function getVenueDemand($chapelId = null)
    {
        return $this->analytics->getVenueDemand($chapelId);
    }

    public function getUsageStatsByVenue($chapelId = null)
    {
        return $this->analytics->getUsageStatsByVenue($chapelId);
    }

    public function getResponseTimeStats($chapelId = null)
    {
        return $this->analytics->getResponseTimeStats($chapelId);
    }

    public function getStaleConfirmedEvents($scope = null, $chapelId = null)
    {
        $data = [];
        $sql = "SELECT e.*, IFNULL(c.chapel_name, e.chapel_location) as chapel_location, COALESCE(NULLIF(u.full_name, ''), u.user) as pastor_assigned 
                FROM events e 
                LEFT JOIN chapels c ON e.chapel_id = c.chapel_id 
                LEFT JOIN users u ON e.pastor_id = u.id 
                WHERE e.status = 'Confirmed' AND e.start_time < NOW() AND e.is_archived = 0 AND " . self::NON_CERT_E_SQL;

        $params = [];
        $types = "";

        if ($scope) {
            $sql .= " AND e.event_scope = ?";
            $params[] = $scope;
            $types .= "s";
        }
        
        if ($chapelId !== null) {
            $isCathedral = ($chapelId === 'cathedral' || (is_numeric($chapelId) && (int)$chapelId === 0));
            if ($isCathedral) {
                $sql .= " AND (e.chapel_id IS NULL OR e.chapel_id = 0)";
            } else {
                $sql .= " AND e.chapel_id = ?";
                $params[] = (int)$chapelId;
                $types .= "i";
            }
        }

        $sql .= " ORDER BY e.start_time ASC";
        
        if ($params) {
            $s = $this->conn->prepare($sql);
            if (!$s) {
                return $data;
            }
            $s->bind_param($types, ...$params);
            $s->execute();
            $res = $s->get_result();
        } else {
            $res = $this->conn->query($sql);
        }

        if ($res) while ($row = $res->fetch_assoc()) $data[] = $row;
        return $data;
    }

    public function getWorkflowIssues($scope = null, $chapelId = null): array
    {
        return [
            'pending_overdue' => $this->getOverduePendingRequests($chapelId),
            'confirmed_stale' => $this->getStaleConfirmedEvents($scope, $chapelId),
            'schedule_conflicts' => $this->getScheduleConflicts($chapelId),
            'schedule_misplacements' => $this->getScheduleMisplacements($chapelId),
        ];
    }

    public function getScheduleMisplacements($chapelId = null): array
    {
        $issues = [];
        $where = "WHERE e.is_archived = 0 AND e.status NOT IN ('Cancelled', 'Rejected') AND " . self::NON_CERT_E_SQL;
        $params = [];
        $types = '';

        if ($chapelId !== null) {
            $where .= " AND e.chapel_id = ?";
            $params[] = (int)$chapelId;
            $types .= 'i';
        }

        $sql = "SELECT e.*, IFNULL(c.chapel_name, e.chapel_location) as chapel_location,
                       COALESCE(NULLIF(u.full_name, ''), u.user) as pastor_assigned
                FROM events e
                LEFT JOIN chapels c ON e.chapel_id = c.chapel_id
                LEFT JOIN users u ON e.pastor_id = u.id
                $where
                ORDER BY e.start_time ASC";

        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            return $issues;
        }
        if ($params) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $hour = (int)date('G', strtotime($row['start_time']));
            $expectedScope = empty($row['chapel_id']) ? 'cathedral' : 'chapel';
            if ($hour < 6 || $hour >= 21 || ($row['event_scope'] ?? '') !== $expectedScope) {
                $row['issue_reason'] = ($hour < 6 || $hour >= 21)
                    ? 'Outside operating hours'
                    : 'Wrong schedule scope';
                $issues[] = $row;
            }
        }
        $stmt->close();
        return $issues;
    }

    public function getScheduleConflicts($chapelId = null): array
    {
        $events = [];
        $where = "WHERE e.is_archived = 0 AND e.status NOT IN ('Cancelled', 'Rejected') AND " . self::NON_CERT_E_SQL;
        $params = [];
        $types = '';

        if ($chapelId !== null) {
            $where .= " AND e.chapel_id = ?";
            $params[] = (int)$chapelId;
            $types .= 'i';
        }

        $sql = "SELECT e.*, IFNULL(c.chapel_name, e.chapel_location) as chapel_location,
                       COALESCE(NULLIF(u.full_name, ''), u.user) as pastor_assigned
                FROM events e
                LEFT JOIN chapels c ON e.chapel_id = c.chapel_id
                LEFT JOIN users u ON e.pastor_id = u.id
                $where
                ORDER BY e.start_time ASC";

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

        $conflicts = [];
        $seen = [];
        $count = count($events);
        for ($i = 0; $i < $count; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {
                if (date('Y-m-d', strtotime($events[$i]['start_time'])) !== date('Y-m-d', strtotime($events[$j]['start_time']))) {
                    continue;
                }

                $sameVenue = ((string)($events[$i]['chapel_id'] ?? '') === (string)($events[$j]['chapel_id'] ?? ''));
                $samePastor = !empty($events[$i]['pastor_id']) && (string)$events[$i]['pastor_id'] === (string)($events[$j]['pastor_id'] ?? '');
                if (!$sameVenue && !$samePastor) {
                    continue;
                }

                if (!$this->eventsOverlap($events[$i], $events[$j])) {
                    continue;
                }

                $key = min($events[$i]['event_id'], $events[$j]['event_id']) . ':' . max($events[$i]['event_id'], $events[$j]['event_id']);
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;

                $conflicts[] = [
                    'type' => $sameVenue ? 'Venue' : 'Priest',
                    'a' => $events[$i],
                    'b' => $events[$j],
                ];
            }
        }

        return $conflicts;
    }

    private function eventsOverlap(array $a, array $b): bool
    {
        $buffer = 15 * 60;
        $aStart = strtotime($a['start_time']);
        $bStart = strtotime($b['start_time']);
        $aEnd = $aStart + ($this->getEventDuration($a['event_type'], $a['custom_duration']) * 60);
        $bEnd = $bStart + ($this->getEventDuration($b['event_type'], $b['custom_duration']) * 60);

        return $aStart < ($bEnd + $buffer) && ($aEnd + $buffer) > $bStart;
    }

    public function resolveStaleStatus($id, $newStatus, $newStartTime = null)
    {
        if ($newStatus === 'Delete') {
            return $this->archive($id);
        } elseif ($newStatus === 'Rescheduled') {
            if (empty($newStartTime)) {
                return false;
            }
            $event = $this->getById($id);
            if (!$event) return false;

            $conflict = $this->checkConflict($newStartTime, $event['pastor_id'], $event['chapel_id'], $event['event_type'], $id, $event['custom_duration']);
            if ($conflict['conflict']) return false;

            $s = $this->conn->prepare("UPDATE events SET start_time = ?, status = 'Confirmed' WHERE event_id = ? AND is_archived = 0");
            if (!$s) {
                return false;
            }
            $s->bind_param('si', $newStartTime, $id);
        } else {
            $s = $this->conn->prepare("UPDATE events SET status = ? WHERE event_id = ? AND is_archived = 0");
            if (!$s) {
                return false;
            }
            $s->bind_param('si', $newStatus, $id);
        }
        $res = $s->execute();
        $s->close();
        return $res;
    }

    public function search(array $filters = [], int $page = 1, int $per_page = 20): array
    {
        $archivedStatus = isset($filters['is_archived']) ? (int)$filters['is_archived'] : 0;
        $where  = ['e.is_archived = ?'];
        $params = [$archivedStatus];
        $types  = 'i';

        if (!empty($filters['scope'])) {
            $where[] = 'e.event_scope = ?';
            $params[] = $filters['scope'];
            $types .= 's';
        }

        if (!empty($filters['venue_scope'])) {
            if ($filters['venue_scope'] === 'cathedral') {
                $where[] = '(e.chapel_id IS NULL OR e.chapel_id = 0)';
            } elseif ($filters['venue_scope'] === 'chapel') {
                $where[] = '(e.chapel_id IS NOT NULL AND e.chapel_id > 0)';
            }
        }

        if (!empty($filters['is_certificate'])) {
            $where[] = "(COALESCE(e.event_type, '') = 'Ecclesiastical Certificates' OR COALESCE(e.event_type, '') LIKE '%Certificate%' OR e.service_id = 8)";
        } elseif (!empty($filters['exclude_certificates'])) {
            $where[] = self::NON_CERT_E_SQL;
        }
        if (!empty($filters['event_type'])) {
            $where[] = 'e.event_type = ?';
            $params[] = $filters['event_type'];
            $types .= 's';
        }

        if (!empty($filters['status'])) {
            $where[] = 'e.status = ?';
            $params[] = $filters['status'];
            $types .= 's';
        }

        if (!empty($filters['chapel_id'])) {
            $where[] = 'e.chapel_id = ?';
            $params[] = $filters['chapel_id'];
            $types .= 'i';
        }

        if (!empty($filters['pastor_id'])) {
            $where[] = 'e.pastor_id = ?';
            $params[] = $filters['pastor_id'];
            $types .= 'i';
        } elseif (!empty($filters['has_pastor'])) {
            $where[] = 'e.pastor_id IS NOT NULL AND e.pastor_id > 0';
        }

        if (!empty($filters['requested_by'])) {
            $where[] = 'e.requested_by = ?';
            $params[] = $filters['requested_by'];
            $types .= 'i';
        }

        if (!empty($filters['search'])) {
            $q        = '%' . $filters['search'] . '%';
            $where[]  = '(e.title LIKE ? OR u.full_name LIKE ? OR c.chapel_name LIKE ? OR e.chapel_location LIKE ?)';
            for ($i = 0; $i < 4; $i++) { $params[] = $q; $types .= 's'; }
        }

        if (!empty($filters['timeframe'])) {
            switch ($filters['timeframe']) {
                case 'recently_added':
                    $where[] = 'e.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)';
                    break;
                case 'recently_added_7':
                    $where[] = 'e.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)';
                    break;
                case 'this_week':
                    $where[] = 'e.start_time >= ? AND e.start_time <= ?';
                    $params[] = date('Y-m-d 00:00:00', strtotime('monday this week'));
                    $params[] = date('Y-m-d 23:59:59', strtotime('sunday this week'));
                    $types .= 'ss';
                    break;
                case 'next_week':
                    $where[] = 'e.start_time >= ? AND e.start_time <= ?';
                    $params[] = date('Y-m-d 00:00:00', strtotime('monday next week'));
                    $params[] = date('Y-m-d 23:59:59', strtotime('sunday next week'));
                    $types .= 'ss';
                    break;
                case 'this_month':
                    $where[] = 'e.start_time >= ? AND e.start_time <= ?';
                    $params[] = date('Y-m-01 00:00:00');
                    $params[] = date('Y-m-t 23:59:59');
                    $types .= 'ss';
                    break;
                case 'upcoming':
                    $where[] = 'e.start_time >= NOW()';
                    break;
                case 'past':
                    $where[] = 'e.start_time < NOW()';
                    break;
            }
        }

        if (!empty($filters['date_from'])) {
            $where[] = 'e.start_time >= ?';
            $params[] = $filters['date_from'] . ' 00:00:00';
            $types .= 's';
        }

        if (!empty($filters['date_to'])) {
            $where[] = 'e.start_time <= ?';
            $params[] = $filters['date_to'] . ' 23:59:59';
            $types .= 's';
        }

        $whereSQL = 'WHERE ' . implode(' AND ', $where);

        $sortSQL = 'ORDER BY e.start_time DESC';
        if (!empty($filters['sort'])) {
            switch ($filters['sort']) {
                case 'date_asc':      $sortSQL = 'ORDER BY e.start_time ASC'; break;
                case 'date_desc':     $sortSQL = 'ORDER BY e.start_time DESC'; break;
                case 'modified_desc': $sortSQL = 'ORDER BY e.updated_at DESC'; break;
                case 'recently_added': $sortSQL = 'ORDER BY e.created_at DESC'; break;
            }
        }

        $countSQL = "SELECT COUNT(e.event_id) AS total FROM events e 
                     LEFT JOIN chapels c ON e.chapel_id = c.chapel_id 
                     LEFT JOIN users u ON e.pastor_id = u.id $whereSQL";
        $total = 0;
        if ($cstmt = $this->conn->prepare($countSQL)) {
            if ($params) $cstmt->bind_param($types, ...$params);
            $cstmt->execute();
            $total = $cstmt->get_result()->fetch_assoc()['total'] ?? 0;
            $cstmt->close();
        }

        $offset   = ($page - 1) * $per_page;
        $fetchSQL = "SELECT e.*, IFNULL(c.chapel_name, e.chapel_location) as chapel_location, 
                            COALESCE(NULLIF(u.full_name, ''), u.user) as pastor_assigned,
                            COALESCE(NULLIF(u_req.full_name, ''), u_req.user, 'System / Automated') as encoded_by_name
                     FROM events e 
                     LEFT JOIN chapels c ON e.chapel_id = c.chapel_id 
                     LEFT JOIN users u ON e.pastor_id = u.id 
                     LEFT JOIN users u_req ON e.requested_by = u_req.id
                     $whereSQL $sortSQL LIMIT ? OFFSET ?";

        $records = [];
        $fparams = array_merge($params, [$per_page, $offset]);
        $ftypes  = $types . 'ii';

        if ($fstmt = $this->conn->prepare($fetchSQL)) {
            if ($fparams) $fstmt->bind_param($ftypes, ...$fparams);
            $fstmt->execute();
            $res = $fstmt->get_result();
            while ($row = $res->fetch_assoc()) $records[] = $row;
            $fstmt->close();
        }

        return [
            'records' => $records,
            'total'   => (int)$total,
            'pages'   => (int)ceil($total / $per_page),
            'page'    => $page,
            'sort'    => $filters['sort'] ?? 'date_desc'
        ];
    }

    // ──────────────────────────────────────────────────────────────────────────
    // AVAILABILITY QUERIES
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Returns all pastors qualified for the event type, with availability flags.
     * Ignores Cancelled/Rejected events. Self-conflict excluded via $excludeId.
     *
     * @param int|null $customDuration Total duration override for the NEW event (minutes)
     */
    public function getAvailablePriests($eventType, $dateTime, $excludeId = null, $customDuration = null, $chapelId = null)
    {
        $pastors = [];

        $canonicalType = $eventType;
        $lower = strtolower($eventType);
        if (strpos($lower, 'wedding') !== false || strpos($lower, 'matrimony') !== false) {
            $canonicalType = 'Wedding';
        } elseif (strpos($lower, 'baptism') !== false || strpos($lower, 'christening') !== false) {
            $canonicalType = 'Baptism';
        } elseif (strpos($lower, 'funeral') !== false || strpos($lower, 'burial') !== false || strpos($lower, 'requiem') !== false) {
            $canonicalType = 'Funeral';
        } elseif (strpos($lower, 'confirmation') !== false) {
            $canonicalType = 'Confirmation';
        } elseif (strpos($lower, 'mass') !== false) {
            $canonicalType = 'Mass';
        }

        // 1. Get pastors (including admins) that support this event type
        // We also pull chapel_coverage to filter by location
        $sql = "SELECT u.id, u.user, u.full_name, u.chapel_coverage, u.duty_start_time, u.duty_end_time
                FROM users u
                INNER JOIN pastor_event_types pet ON pet.pastor_id = u.id
                WHERE u.role = 'Priest'
                AND (pet.event_type = ? OR pet.event_type = ?)
                GROUP BY u.id, u.user, u.full_name, u.chapel_coverage, u.duty_start_time, u.duty_end_time
                ORDER BY u.user ASC";

        if ($stmt = $this->conn->prepare($sql)) {
            $stmt->bind_param('ss', $eventType, $canonicalType);
            $stmt->execute();
            $res = $stmt->get_result();

            // Fetch chapel name if $chapelId is a chapel (not Main Cathedral or null)
            $chapelName = '';
            if ($chapelId !== null && $chapelId !== '' && $chapelId != 0) {
                $stmtCh = $this->conn->prepare("SELECT chapel_name FROM chapels WHERE chapel_id = ?");
                if ($stmtCh) {
                    $stmtCh->bind_param('i', $chapelId);
                    $stmtCh->execute();
                    $resCh = $stmtCh->get_result();
                    if ($rowCh = $resCh->fetch_assoc()) {
                        $chapelName = $rowCh['chapel_name'];
                    }
                    $stmtCh->close();
                }
            }

            while ($p = $res->fetch_assoc()) {
                // Chapel Coverage Filter
                $targetVenue = 'Main Cathedral';
                if ($chapelId !== null && $chapelId !== '' && $chapelId != 0) {
                    $targetVenue = $chapelName;
                }

                if ($targetVenue !== '') {
                    $coverage = $p['chapel_coverage'] ? explode(',', $p['chapel_coverage']) : [];
                    // If pastor has specific coverage defined and this chapel isn't in it, skip them
                    if (!empty($coverage) && !in_array($targetVenue, $coverage)) {
                        continue;
                    }
                }

                $available = true;
                $conflict  = null;
                $conflictStatus = null;

                if ($dateTime) {
                    $newStartTs = strtotime($dateTime);
                    $newEndTs   = $newStartTs + ($this->getEventDuration($eventType, $customDuration) * 60);

                    // 1. Check Priest Duty / Working Hours Schedule
                    if (!empty($p['duty_start_time']) && !empty($p['duty_end_time'])) {
                        $eventTimeStr = date('H:i:s', $newStartTs);
                        $dStart = $p['duty_start_time'];
                        $dEnd   = $p['duty_end_time'];

                        // If the event starts before duty start or at/after duty end
                        if ($eventTimeStr < $dStart || $eventTimeStr >= $dEnd) {
                            $available = false;
                            $sFmt = date('g:i A', strtotime($dStart));
                            $eFmt = date('g:i A', strtotime($dEnd));
                            $conflict = "Off-Duty (Working Hours: {$sFmt} – {$eFmt})";
                            $conflictStatus = 'Off-Duty';
                        }
                    }

                    // 2. Check Overlapping Scheduled Events
                    if ($available) {
                        $conflictCheck = $this->conflicts->checkPriestOnly($p['id'], $dateTime, $eventType, $excludeId, $customDuration);
                        if ($conflictCheck['conflict'] && $conflictCheck['type'] === 'Priest') {
                            $available = false;
                            $conflict  = $conflictCheck['details'];
                            $conflictStatus = $conflictCheck['status'] ?? 'Confirmed';
                        }
                    }
                }

                $displayName = !empty($p['full_name']) ? $p['full_name'] : $p['user'];
                $pastors[] = [
                    'id'              => $p['id'],
                    'name'            => $displayName,
                    'initials'        => (strpos($displayName, ' ') !== false)
                        ? strtoupper(substr($displayName, 0, 1) . substr(strrchr($displayName, ' '), 1, 1))
                        : strtoupper(substr($displayName, 0, 2)),
                    'available'       => $available,
                    'conflict_reason' => $conflict,
                    'conflict_status' => $conflictStatus,
                    'duty_start'      => $p['duty_start_time'] ?? null,
                    'duty_end'        => $p['duty_end_time'] ?? null
                ];
            }
            $stmt->close();
        }
        return $pastors;
    }

    public function getAvailablePastors($eventType, $dateTime, $excludeId = null, $customDuration = null, $chapelId = null)
    {
        return $this->getAvailablePriests($eventType, $dateTime, $excludeId, $customDuration, $chapelId);
    }

    /**
     * Returns all venues with availability flags for a given date/time and event type.
     * Ignores Cancelled/Rejected events.
     *
     * @param int|null $customDuration Total duration override for the NEW event (minutes)
     */
    public function getVenueAvailability($dateTime, $eventType = 'Other', $excludeId = null, $customDuration = null)
    {
        $venues = [];

        $sql = "SELECT NULL as id, 'Main Cathedral' as name
                UNION
                SELECT chapel_id as id, chapel_name as name FROM chapels
                ORDER BY name ASC";
        $res = $this->conn->query($sql);

        if ($res) {
            while ($v = $res->fetch_assoc()) {
                $available = true;
                $conflict  = null;

                if ($dateTime) {
                    $newStartTs = strtotime($dateTime);
                    $newEndTs   = $newStartTs + ($this->getEventDuration($eventType, $customDuration) * 60);
                    $dateStr    = date('Y-m-d', $newStartTs);

                    $conflictCheck = $this->conflicts->checkVenueOnly($v['id'], $dateTime, $eventType, $excludeId, $customDuration);
                    if ($conflictCheck['conflict'] && $conflictCheck['type'] === 'Venue') {
                        $available = false;
                        $conflict  = $conflictCheck['details'];
                    }
                }

                $venues[] = [
                    'id'             => $v['id'],
                    'name'           => $v['name'],
                    'available'       => $available,
                    'conflict_reason' => $conflict,
                    'conflict_status' => $conflict ? ($conflictCheck['status'] ?? 'Confirmed') : null
                ];
            }
        }
        return $venues;
    }

    /**
     * Checks if a specific venue has a conflict for a given date/time and event type.
     * Ignores Cancelled/Rejected events.
     *
     * @param int|null $customDuration Total duration override for the NEW event (minutes)
     */
    public function checkVenueConflict($venue, $dateTime, $eventType = 'Other', $excludeId = null, $customDuration = null)
    {
        $response = ['available' => true, 'conflict' => null];

        if ($venue !== '' && $dateTime !== '') {
            $newStartTs = strtotime($dateTime);
            $newEndTs   = $newStartTs + ($this->getEventDuration($eventType, $customDuration) * 60);
            $dateStr    = date('Y-m-d', $newStartTs);

            $conflictCheck = $this->conflicts->checkVenueOnly($venue, $dateTime, $eventType, $excludeId, $customDuration);
            if ($conflictCheck['conflict'] && $conflictCheck['type'] === 'Venue') {
                $response['available']       = false;
                $response['conflict']        = $conflictCheck['details'];
                $response['conflict_status'] = $conflictCheck['status'] ?? 'Confirmed';
            }
        }
        return $response;
    }
}
