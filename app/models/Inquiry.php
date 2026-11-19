<?php
class Inquiry {
    private $conn;
    private $hasUpdatedAt = null;
    private $hasIsArchived = null;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    /**
     * Check once if a column exists in public_inquiries.
     */
    private function columnExists(string $col): bool {
        $r = $this->conn->query("SHOW COLUMNS FROM public_inquiries LIKE '{$col}'");
        return ($r && $r->num_rows > 0);
    }

    private function hasUpdatedAt(): bool {
        return true;
    }

    private function hasIsArchived(): bool {
        return true;
    }

    public function create($name, $email, $phone, $type, $category, $details, $date = null, $chapelId = null, $itemId = null, $quantity = null) {
        $this->ensureMerchColumns();
        $s = $this->conn->prepare("INSERT INTO public_inquiries (chapel_id, full_name, email, phone, request_type, service_category, target_date, details, status, item_id, quantity) VALUES (?,?,?,?,?,?,?,?, 'Pending', ?, ?)");
        if (!$s) {
            return false;
        }
        $chapelIdVal = $chapelId ? (int)$chapelId : null;
        $itemIdVal = $itemId ? (int)$itemId : null;
        $qtyVal = $quantity ? (int)$quantity : null;
        $s->bind_param('isssssssii', $chapelIdVal, $name, $email, $phone, $type, $category, $date, $details, $itemIdVal, $qtyVal);
        $res = $s->execute();
        $insertId = (int)$this->conn->insert_id;
        $s->close();
        return $res ? $insertId : false;
    }

    private function ensureMerchColumns(): void {
        // Merch columns (item_id, quantity) are permanent in schema
    }

    public function getById($id) {
        $stmt = $this->conn->prepare("SELECT * FROM public_inquiries WHERE inquiry_id = ? LIMIT 1");
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('i', $id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    /**
     * Get active (non-archived) inquiries with optional pagination.
     * Returns ['records'=>[], 'total'=>int, 'pages'=>int, 'page'=>int]
     */
    public function getAll($filters = [], $page = 1, $perPage = 25) {
        $status = $filters['status'] ?? null;
        $chapelId = $filters['chapel_id'] ?? null;
        $archiveClause = $this->hasIsArchived() ? "i.is_archived = 0" : "1=1";
        $where = $archiveClause;
        $params = [];
        $types  = "";

        if ($status) {
            $where .= " AND i.status = ?";
            $params[] = $status;
            $types  .= "s";
        }
        if ($chapelId) {
            $where .= " AND (i.chapel_id = ? OR i.chapel_id IS NULL)";
            $params[] = (int)$chapelId;
            $types  .= "i";
        }

        // Count total
        $cntSql = "SELECT COUNT(*) as c FROM public_inquiries i WHERE {$where}";
        $cntStmt = $this->conn->prepare($cntSql);
        if (!$cntStmt) return ['records' => [], 'total' => 0, 'pages' => 1, 'page' => 1];
        if (!empty($params)) $cntStmt->bind_param($types, ...$params);
        $cntStmt->execute();
        $total = (int)($cntStmt->get_result()->fetch_assoc()['c'] ?? 0);
        $cntStmt->close();

        $pages = max(1, (int)ceil($total / $perPage));
        $page  = max(1, min($page, $pages));
        $offset = ($page - 1) * $perPage;

        // Fetch page
        $sortSQL = "ORDER BY i.created_at DESC";
        if (!empty($filters['sort'])) {
            switch ($filters['sort']) {
                case 'date_asc':      $sortSQL = "ORDER BY i.created_at ASC"; break;
                case 'date_desc':     $sortSQL = "ORDER BY i.created_at DESC"; break;
                case 'modified_desc': 
                    $sortSQL = $this->hasUpdatedAt() ? "ORDER BY i.updated_at DESC" : "ORDER BY i.created_at DESC";
                    break;
            }
        }

        $sql = "SELECT i.*, c.chapel_name
                FROM public_inquiries i
                LEFT JOIN chapels c ON i.chapel_id = c.chapel_id
                WHERE {$where}
                {$sortSQL}
                LIMIT ? OFFSET ?";
        $params[] = $perPage;
        $params[] = $offset;
        $types   .= "ii";

        $stmt = $this->conn->prepare($sql);
        if (!$stmt) return ['records' => [], 'total' => 0, 'pages' => 1, 'page' => 1];
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res  = $stmt->get_result();
        $data = [];
        while ($row = $res->fetch_assoc()) $data[] = $row;
        $stmt->close();

        return ['records' => $data, 'total' => $total, 'pages' => $pages, 'page' => $page];
    }

    /**
     * Get archived inquiries with pagination.
     */
    public function getArchived($filters = [], $page = 1, $perPage = 25) {
        $chapelId = $filters['chapel_id'] ?? null;
        // If is_archived column doesn't exist, archived tab shows nothing
        if (!$this->hasIsArchived()) {
            return ['records' => [], 'total' => 0, 'pages' => 1, 'page' => 1];
        }

        $where = "i.is_archived = 1";
        $params = [];
        $types  = "";

        if ($chapelId) {
            $where .= " AND (i.chapel_id = ? OR i.chapel_id IS NULL)";
            $params[] = (int)$chapelId;
            $types  .= "i";
        }

        // Count
        $cntStmt = $this->conn->prepare("SELECT COUNT(*) as c FROM public_inquiries i WHERE {$where}");
        if (!$cntStmt) return ['records' => [], 'total' => 0, 'pages' => 1, 'page' => 1];
        if (!empty($params)) $cntStmt->bind_param($types, ...$params);
        $cntStmt->execute();
        $total = (int)($cntStmt->get_result()->fetch_assoc()['c'] ?? 0);
        $cntStmt->close();

        $pages  = max(1, (int)ceil($total / $perPage));
        $page   = max(1, min($page, $pages));
        $offset = ($page - 1) * $perPage;

        $orderBy = $this->hasUpdatedAt() ? "i.updated_at DESC" : "i.created_at DESC";
        if (!empty($filters['sort'])) {
            switch ($filters['sort']) {
                case 'date_asc':      $orderBy = "i.created_at ASC"; break;
                case 'date_desc':     $orderBy = "i.created_at DESC"; break;
                case 'modified_desc': 
                    $orderBy = $this->hasUpdatedAt() ? "i.updated_at DESC" : "i.created_at DESC";
                    break;
            }
        }
        $sql = "SELECT i.*, c.chapel_name
                FROM public_inquiries i
                LEFT JOIN chapels c ON i.chapel_id = c.chapel_id
                WHERE {$where}
                ORDER BY {$orderBy}
                LIMIT ? OFFSET ?";
        $p2 = array_merge($params, [$perPage, $offset]);
        $t2 = $types . "ii";

        $stmt = $this->conn->prepare($sql);
        if (!$stmt) return ['records' => [], 'total' => 0, 'pages' => 1, 'page' => 1];
        $stmt->bind_param($t2, ...$p2);
        $stmt->execute();
        $res  = $stmt->get_result();
        $data = [];
        while ($row = $res->fetch_assoc()) $data[] = $row;
        $stmt->close();

        return ['records' => $data, 'total' => $total, 'pages' => $pages, 'page' => $page];
    }

    /**
     * Count active (non-archived) inquiries for the badge.
     */
    public function getActiveCount($chapelId = null) {
        if (!$this->hasIsArchived()) {
            $sql = "SELECT COUNT(*) as c FROM public_inquiries WHERE 1=1";
        } else {
            $sql = "SELECT COUNT(*) as c FROM public_inquiries WHERE is_archived = 0";
        }
        if ($chapelId) $sql .= " AND (chapel_id = " . (int)$chapelId . " OR chapel_id IS NULL)";
        $res = $this->conn->query($sql);
        return $res ? (int)($res->fetch_assoc()['c'] ?? 0) : 0;
    }

    /**
     * Count archived inquiries.
     */
    public function getArchivedCount($chapelId = null) {
        if (!$this->hasIsArchived()) return 0;
        $sql = "SELECT COUNT(*) as c FROM public_inquiries WHERE is_archived = 1";
        if ($chapelId) $sql .= " AND (chapel_id = " . (int)$chapelId . " OR chapel_id IS NULL)";
        $res = $this->conn->query($sql);
        return $res ? (int)($res->fetch_assoc()['c'] ?? 0) : 0;
    }

    public function updateStatus($id, $status, $note = null) {
        // Build query dynamically — updated_at may not exist on older DB installs
        $setClauses = "status = ?, admin_note = ?";
        if ($this->hasUpdatedAt()) {
            $setClauses .= ", updated_at = NOW()";
        }
        $s = $this->conn->prepare("UPDATE public_inquiries SET {$setClauses} WHERE inquiry_id = ?");
        if (!$s) {
            return false;
        }
        $s->bind_param('ssi', $status, $note, $id);
        $res = $s->execute();
        $s->close();
        return $res;
    }

    public function delete($id) {
        $s = $this->conn->prepare("DELETE FROM public_inquiries WHERE inquiry_id = ?");
        if (!$s) return false;
        $s->bind_param('i', $id);
        $res = $s->execute();
        $s->close();
        return $res;
    }

    public function archive($id) {
        if (!$this->hasIsArchived()) return true; // column missing — treat as no-op success
        $sets = "is_archived = 1";
        if ($this->hasUpdatedAt()) $sets .= ", updated_at = NOW()";
        $s = $this->conn->prepare("UPDATE public_inquiries SET {$sets} WHERE inquiry_id = ?");
        if (!$s) return false;
        $s->bind_param('i', $id);
        $res = $s->execute();
        $s->close();
        return $res;
    }

    public function unarchive($id) {
        if (!$this->hasIsArchived()) return true; // column missing — treat as no-op success
        $sets = "is_archived = 0";
        if ($this->hasUpdatedAt()) $sets .= ", updated_at = NOW()";
        $s = $this->conn->prepare("UPDATE public_inquiries SET {$sets} WHERE inquiry_id = ?");
        if (!$s) return false;
        $s->bind_param('i', $id);
        $res = $s->execute();
        $s->close();
        return $res;
    }

    public function getPendingCount($chapelId = null) {
        $archiveClause = $this->hasIsArchived() ? " AND is_archived = 0" : "";
        $sql = "SELECT COUNT(*) as c FROM public_inquiries WHERE status = 'Pending'{$archiveClause}";
        if ($chapelId) {
            $sql .= " AND (chapel_id = " . (int)$chapelId . " OR chapel_id IS NULL)";
        }
        $res = $this->conn->query($sql);
        return $res ? (int)($res->fetch_assoc()['c'] ?? 0) : 0;
    }

    /**
     * Count pending requests for a specific email.
     * Used for rate-limiting and spam prevention.
     */
    public function getCountByEmailAndStatus($email, $status) {
        $archiveClause = $this->hasIsArchived() ? " AND is_archived = 0" : "";
        $stmt = $this->conn->prepare("SELECT COUNT(*) as c FROM public_inquiries WHERE email = ? AND status = ?{$archiveClause}");
        if (!$stmt) return 0;
        $stmt->bind_param('ss', $email, $status);
        $stmt->execute();
        $res = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
        $stmt->close();
        return $res;
    }

    /**
     * Get inquiries for a specific email (used for Member's My Requests view)
     */
    public function getByEmail($email, $page = 1, $perPage = 25) {
        $where = "i.email = ?";
        $params = [$email];
        $types = "s";

        // Count total
        $cntSql = "SELECT COUNT(*) as c FROM public_inquiries i WHERE {$where}";
        $cntStmt = $this->conn->prepare($cntSql);
        if (!$cntStmt) return ['records' => [], 'total' => 0, 'pages' => 1, 'page' => 1];
        $cntStmt->bind_param($types, ...$params);
        $cntStmt->execute();
        $total = (int)($cntStmt->get_result()->fetch_assoc()['c'] ?? 0);
        $cntStmt->close();

        $pages = max(1, (int)ceil($total / $perPage));
        $page  = max(1, min($page, $pages));
        $offset = ($page - 1) * $perPage;

        $sql = "SELECT i.*, c.chapel_name
                FROM public_inquiries i
                LEFT JOIN chapels c ON i.chapel_id = c.chapel_id
                WHERE {$where}
                ORDER BY i.created_at DESC
                LIMIT ? OFFSET ?";
        
        $limitParams = array_merge($params, [$perPage, $offset]);
        $limitTypes = $types . "ii";

        $stmt = $this->conn->prepare($sql);
        if (!$stmt) return ['records' => [], 'total' => 0, 'pages' => 1, 'page' => 1];
        $stmt->bind_param($limitTypes, ...$limitParams);
        $stmt->execute();
        $res  = $stmt->get_result();
        $data = [];
        while ($row = $res->fetch_assoc()) $data[] = $row;
        $stmt->close();

        return ['records' => $data, 'total' => $total, 'pages' => $pages, 'page' => $page];
    }
}
