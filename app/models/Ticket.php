<?php
class Ticket {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    /**
     * Create a new ticket (Internal or Guest)
     */
    public function create($data) {
        $ticket_no     = 'TKT-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4));
        $subject       = trim((string)($data['subject'] ?? ''));
        $description   = trim((string)($data['description'] ?? ''));
        
        if ($subject === '' || $description === '') {
            return false; // Or throw Exception, but false is consistent here
        }

        $category      = (string)($data['category'] ?? 'General');
        $priority      = (string)($data['priority'] ?? 'Medium');
        $requester_id  = (isset($data['requester_id']) && (int)$data['requester_id'] > 0) ? (int)$data['requester_id'] : null;
        $requester_name  = isset($data['requester_name'])  && $data['requester_name']  !== '' ? (string)$data['requester_name']  : null;
        $requester_email = isset($data['requester_email']) && $data['requester_email'] !== '' ? (string)$data['requester_email'] : null;
        $requester_phone = isset($data['requester_phone']) && $data['requester_phone'] !== '' ? (string)$data['requester_phone'] : null;
        $chapel_id     = (isset($data['chapel_id']) && (int)$data['chapel_id'] > 0) ? (int)$data['chapel_id'] : null;

        $sql = "INSERT INTO tickets 
                    (ticket_no, subject, description, category, priority, requester_id, requester_name, requester_email, requester_phone, chapel_id) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            error_log('Ticket::create prepare failed: ' . $this->conn->error);
            return false;
        }

        // Bind: s=ticket_no, s=subject, s=description, s=category, s=priority,
        //       i=requester_id (nullable int), s=requester_name, s=requester_email,
        //       s=requester_phone, i=chapel_id (nullable int)
        $stmt->bind_param('sssssisssi',
            $ticket_no, $subject, $description, $category, $priority,
            $requester_id, $requester_name, $requester_email, $requester_phone, $chapel_id
        );

        $res = $stmt->execute();
        if (!$res) {
            error_log('Ticket::create execute failed: ' . $stmt->error);
        }
        $stmt->close();
        return $res ? $ticket_no : false;
    }

    /**
     * Get all tickets with optional filtering
     */
    public function getAll($filters = []) {
        $sql = "SELECT t.*, u.user as assigned_user, c.chapel_name 
                FROM tickets t 
                LEFT JOIN users u ON t.assigned_to = u.id 
                LEFT JOIN chapels c ON t.chapel_id = c.chapel_id 
                WHERE 1=1";
        
        $params = [];
        $types = "";

        if (!empty($filters['status'])) {
            $sql .= " AND t.status = ?";
            $params[] = $filters['status'];
            $types .= "s";
        }

        if (!empty($filters['assigned_to'])) {
            $sql .= " AND t.assigned_to = ?";
            $params[] = $filters['assigned_to'];
            $types .= "i";
        }

        if (array_key_exists('chapel_id', $filters) && $filters['chapel_id'] !== null) {
            $sql .= " AND t.chapel_id = ?";
            $params[] = $filters['chapel_id'];
            $types .= "i";
        }

        if (!empty($filters['requester_id'])) {
            $sql .= " AND t.requester_id = ?";
            $params[] = $filters['requester_id'];
            $types .= "i";
        }

        if (!empty($filters['priority'])) {
            $sql .= " AND t.priority = ?";
            $params[] = $filters['priority'];
            $types .= "s";
        }

        if (!empty($filters['search'])) {
            $sql .= " AND (t.subject LIKE ? OR t.ticket_no LIKE ? OR t.description LIKE ?)";
            $searchVal = "%" . $filters['search'] . "%";
            $params[] = $searchVal;
            $params[] = $searchVal;
            $params[] = $searchVal;
            $types .= "sss";
        }

        $sql .= " ORDER BY t.created_at DESC";
        
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) return null; // Table likely doesn't exist yet
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        
        $stmt->execute();
        $res = $stmt->get_result();
        $data = $res->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $data;
    }

    /**
     * Get count of tickets with optional filtering
     */
    public function countAll($filters = []) {
        $sql = "SELECT COUNT(*) FROM tickets t WHERE 1=1";
        
        $params = [];
        $types = "";

        if (!empty($filters['status'])) {
            $sql .= " AND t.status = ?";
            $params[] = $filters['status'];
            $types .= "s";
        }

        if (!empty($filters['assigned_to'])) {
            $sql .= " AND t.assigned_to = ?";
            $params[] = $filters['assigned_to'];
            $types .= "i";
        }

        if (array_key_exists('chapel_id', $filters) && $filters['chapel_id'] !== null) {
            $sql .= " AND t.chapel_id = ?";
            $params[] = $filters['chapel_id'];
            $types .= "i";
        }

        if (!empty($filters['requester_id'])) {
            $sql .= " AND t.requester_id = ?";
            $params[] = $filters['requester_id'];
            $types .= "i";
        }

        if (!empty($filters['priority'])) {
            $sql .= " AND t.priority = ?";
            $params[] = $filters['priority'];
            $types .= "s";
        }

        if (!empty($filters['search'])) {
            $sql .= " AND (t.subject LIKE ? OR t.ticket_no LIKE ? OR t.description LIKE ?)";
            $searchVal = "%" . $filters['search'] . "%";
            $params[] = $searchVal;
            $params[] = $searchVal;
            $params[] = $searchVal;
            $types .= "sss";
        }
        
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) return 0;
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        
        $stmt->execute();
        $res = $stmt->get_result();
        $count = (int)$res->fetch_row()[0];
        $stmt->close();
        return $count;
    }

    /**
     * Update ticket status or assignment
     */
    public function update($id, $data) {
        $fields = [];
        $params = [];
        $types = "";

        if (isset($data['status'])) {
            $fields[] = "status = ?";
            $params[] = $data['status'];
            $types .= "s";
            if ($data['status'] === 'Resolved') {
                $fields[] = "resolved_at = NOW()";
            }
        }

        if (isset($data['assigned_to'])) {
            $fields[] = "assigned_to = ?";
            $params[] = $data['assigned_to'];
            $types .= "i";
        }

        if (empty($fields)) return false;

        $sql = "UPDATE tickets SET " . implode(', ', $fields) . " WHERE ticket_id = ?";
        $params[] = $id;
        $types .= "i";

        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            error_log('Ticket::update prepare failed: ' . $this->conn->error);
            return false;
        }
        $stmt->bind_param($types, ...$params);
        $res = $stmt->execute();
        $stmt->close();
        return $res;
    }

    public function getById($id) {
        $stmt = $this->conn->prepare("SELECT * FROM tickets WHERE ticket_id = ? LIMIT 1");
        if (!$stmt) {
            error_log('Ticket::getById prepare failed: ' . $this->conn->error);
            return null;
        }
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $result ?: null;
    }

    /**
     * Add a comment/note to a ticket
     */
    public function addComment($ticket_id, $user_id, $text, $is_internal = 0) {
        $stmt = $this->conn->prepare("INSERT INTO ticket_comments (ticket_id, user_id, comment_text, is_internal) VALUES (?, ?, ?, ?)");
        if (!$stmt) {
            error_log('Ticket::addComment prepare failed: ' . $this->conn->error);
            return false;
        }
        $stmt->bind_param('iisi', $ticket_id, $user_id, $text, $is_internal);
        $res = $stmt->execute();
        $stmt->close();
        return $res;
    }

    public function getComments($ticket_id, $include_internal = false) {
        $sql = "SELECT tc.*, u.user as author_name 
                FROM ticket_comments tc 
                LEFT JOIN users u ON tc.user_id = u.id 
                WHERE tc.ticket_id = ?";
        
        if (!$include_internal) {
            $sql .= " AND tc.is_internal = 0";
        }
        
        $sql .= " ORDER BY tc.created_at ASC";
        
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) return [];
        $stmt->bind_param('i', $ticket_id);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }
}
?>
