<?php
class ParishInfo {
    private $conn;
    private array $tableExistsCache = [];

    public function __construct($conn) {
        $this->conn = $conn;
    }

    private function ensureEmailWebhookColumns(): void {
        $cols = [
            'email_provider'       => 'VARCHAR(30) NULL',
            'email_api_key'        => 'VARCHAR(255) NULL',
            'email_webhook_url'    => 'VARCHAR(500) NULL',
            'email_webhook_secret' => 'VARCHAR(255) NULL',
        ];
        foreach ($cols as $colName => $colDef) {
            $colRes = $this->conn->query("SHOW COLUMNS FROM parish_contact_info LIKE '{$colName}'");
            if ($colRes && $colRes->num_rows === 0) {
                @$this->conn->query("ALTER TABLE parish_contact_info ADD COLUMN {$colName} {$colDef}");
            }
        }
    }

    public function updateEmailSettings($data) {
        $this->ensureEmailWebhookColumns();

        $webhookUrl = trim((string)($data['email_webhook_url'] ?? ''));
        $emailProvider = strtolower(trim((string)($data['email_provider'] ?? '')));
        $stmt = $this->conn->prepare("UPDATE parish_contact_info SET 
            smtp_host=?, smtp_port=?, smtp_user=?, smtp_secure=?, 
            from_email=?, from_name=?, email_webhook_url=?, email_provider=? 
            WHERE info_id = (SELECT info_id FROM (SELECT info_id FROM parish_contact_info ORDER BY info_id DESC LIMIT 1) as t)");
        
        if (!$stmt) return false;
        $stmt->bind_param("sissssss", 
            $data['smtp_host'], $data['smtp_port'], $data['smtp_user'], 
            $data['smtp_secure'], $data['from_email'], $data['from_name'], $webhookUrl, $emailProvider);
        
        $res = $stmt->execute();
        $stmt->close();

        // Update optional secrets only when non-empty so existing saved secrets are retained
        $optionalSecrets = [
            'smtp_pass'            => $data['smtp_pass'] ?? '',
            'email_api_key'        => $data['email_api_key'] ?? '',
            'email_webhook_secret' => $data['email_webhook_secret'] ?? '',
        ];
        foreach ($optionalSecrets as $col => $val) {
            $val = trim((string)$val);
            if ($val !== '') {
                $stmt = $this->conn->prepare("UPDATE parish_contact_info SET {$col}=? WHERE info_id = (SELECT info_id FROM (SELECT info_id FROM parish_contact_info ORDER BY info_id DESC LIMIT 1) as t)");
                if ($stmt) {
                    $stmt->bind_param("s", $val);
                    $stmt->execute();
                    $stmt->close();
                }
            }
        }

        return $res;
    }

    public function getContactInfo() {
        if ($this->tableExists('parish_contact_info')) {
            $ci_res = $this->conn->query("SELECT * FROM parish_contact_info ORDER BY info_id DESC LIMIT 1");
            if ($ci_res && $ci_res->num_rows > 0) return $ci_res->fetch_assoc();
        }
        
        // Default values
        return [
            'parish_name' => 'IFI Cathedral',
            'address' => '123 Cathedral Street, Quezon City, Metro Manila',
            'phone' => '(02) 8123-4567',
            'email' => 'cathedral@ifi.org.ph',
            'office_hours' => 'Mon-Sat: 8:00 AM - 5:00 PM',
            'map_embed_url' => 'https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3860.682157892077!2d121.01947031483!3d14.609722389797!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x0%3A0x0!2zMTTCsDM2JzM1LjAiTiAxMjHCsDAxJzEwLjEiRQ!5e0!3m2!1sen!2sph!4v1234567890',
            'cathedral_image' => null,
            'patron_saint' => 'St. Mary',
            'coordinator_name' => null,
            'coordinator_phone' => null
        ];
    }

    public function getMassSchedules() {
        $schedules = [];
        if ($this->tableExists('mass_schedules')) {
            $ms_res = $this->conn->query("SELECT * FROM mass_schedules WHERE is_active=1 ORDER BY sort_order ASC");
            if ($ms_res) while ($row = $ms_res->fetch_assoc()) $schedules[] = $row;
        }

        if (empty($schedules)) {
            return [
                ['day_type' => 'Sunday', 'schedule_time' => '6:00 AM, 8:00 AM, 10:00 AM, 5:00 PM'],
                ['day_type' => 'Weekdays', 'schedule_time' => '6:00 AM, 6:00 PM'],
                ['day_type' => 'Saturday', 'schedule_time' => '6:00 AM, 5:00 PM (Anticipated)']
            ];
        }

        return $schedules;
    }

    public function getHistoryIntro() {
        if ($this->tableExists('parish_history_intro')) {
            $hi_res = $this->conn->query("SELECT intro_text FROM parish_history_intro ORDER BY intro_id DESC LIMIT 1");
            if ($hi_res && $hi_res->num_rows > 0) return $hi_res->fetch_assoc()['intro_text'];
        }
        
        return 'The Iglesia Filipina Independiente Cathedral stands as a testament to faith, resilience, and the Filipino spirit of independence. Our cathedral has been a beacon of hope and spiritual guidance for generations.';
    }

    public function getHistoryItems() {
        $items = [];
        if ($this->tableExists('parish_history')) {
            $ph_res = $this->conn->query("SELECT * FROM parish_history WHERE is_active=1 ORDER BY sort_order ASC");
            if ($ph_res) while ($row = $ph_res->fetch_assoc()) $items[] = $row;
        }
        return $items;
    }

    public function getLeadershipIntro() {
        if ($this->tableExists('parish_leadership_intro')) {
            $li_res = $this->conn->query("SELECT intro_text FROM parish_leadership_intro ORDER BY intro_id DESC LIMIT 1");
            if ($li_res && $li_res->num_rows > 0) return $li_res->fetch_assoc()['intro_text'];
        }

        return 'Our dedicated clergy and staff work together to serve the spiritual and pastoral needs of our parish community.';
    }

    public function getLeadership() {
        $leadership = [
            'bishop' => null,
            'parish_priest' => null,
            'assistants' => [],
            'council' => [],
            'sectoral' => [],
            'executive' => [],
            'coordinator' => []
        ];

        if ($this->tableExists('parish_leadership')) {
            $pl_res = $this->conn->query("SELECT * FROM parish_leadership WHERE is_active=1 ORDER BY sort_order ASC");
            if ($pl_res) {
                while ($row = $pl_res->fetch_assoc()) {
                    if ($row['position_type'] == 'bishop') {
                        $leadership['bishop'] = $row;
                    } elseif ($row['position_type'] == 'parish_priest') {
                        $leadership['parish_priest'] = $row;
                    } elseif ($row['position_type'] == 'assistant' || $row['position_type'] == 'deacon') {
                        $leadership['assistants'][] = $row;
                    } elseif ($row['position_type'] == 'council') {
                        $leadership['council'][] = $row;
                    } elseif ($row['position_type'] == 'sectoral') {
                        $leadership['sectoral'][] = $row;
                    } elseif ($row['position_type'] == 'executive') {
                        $leadership['executive'][] = $row;
                    } elseif ($row['position_type'] == 'coordinator') {
                        $leadership['coordinator'][] = $row;
                    }
                }
            }
        }

        return $leadership;
    }

    public function getAllLeaders() {
        $leaders = [];
        $res = $this->conn->query("SELECT * FROM parish_leadership ORDER BY sort_order ASC");
        if ($res) while ($row = $res->fetch_assoc()) $leaders[] = $row;
        return $leaders;
    }

    public function getLeaderById($id) {
        $stmt = $this->conn->prepare("SELECT * FROM parish_leadership WHERE leader_id=?");
        if (!$stmt) return null;
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $res;
    }

    public function getHistoryItemById($id) {
        $stmt = $this->conn->prepare("SELECT * FROM parish_history WHERE history_id=?");
        if (!$stmt) return null;
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $res;
    }

    private function ensureIntroRecord(string $table): void {
        $check = $this->conn->query("SELECT intro_id FROM $table LIMIT 1");
        if ($check && $check->num_rows == 0) {
            $this->conn->query("INSERT INTO $table (intro_text, updated_by) VALUES ('', NULL)");
        }
    }

    public function updateHistoryIntro($intro, $userId) {
        $this->ensureIntroRecord('parish_history_intro');
        $uid = ((int)$userId > 0) ? (int)$userId : null;
        $stmt = $this->conn->prepare("UPDATE parish_history_intro SET intro_text=?, updated_by=?, updated_at=NOW() WHERE intro_id=1");
        if (!$stmt) return false;
        $stmt->bind_param("si", $intro, $uid);
        $res = $stmt->execute();
        $stmt->close();
        return $res;
    }

    public function saveHistory($id, $year, $title, $description, $sort, $active, $userId) {
        $uid = ((int)$userId > 0) ? (int)$userId : null;
        if ($id) {
            $stmt = $this->conn->prepare("UPDATE parish_history SET year=?, title=?, description=?, sort_order=?, is_active=?, updated_at=NOW() WHERE history_id=?");
            if (!$stmt) return false;
            $stmt->bind_param("sssiii", $year, $title, $description, $sort, $active, $id);
        } else {
            $stmt = $this->conn->prepare("INSERT INTO parish_history (year, title, description, sort_order, is_active, created_by) VALUES (?,?,?,?,?,?)");
            if (!$stmt) return false;
            $stmt->bind_param("sssiii", $year, $title, $description, $sort, $active, $uid);
        }
        $res = $stmt->execute();
        $stmt->close();
        return $res;
    }

    public function deleteHistory($id) {
        $stmt = $this->conn->prepare("DELETE FROM parish_history WHERE history_id=?");
        if (!$stmt) return false;
        $stmt->bind_param("i", $id);
        $res = $stmt->execute();
        $stmt->close();
        return $res;
    }

    public function updateLeadershipIntro($intro, $userId) {
        $this->ensureIntroRecord('parish_leadership_intro');
        $uid = ((int)$userId > 0) ? (int)$userId : null;
        $stmt = $this->conn->prepare("UPDATE parish_leadership_intro SET intro_text=?, updated_by=?, updated_at=NOW() WHERE intro_id=1");
        if (!$stmt) return false;
        $stmt->bind_param("si", $intro, $uid);
        $res = $stmt->execute();
        $stmt->close();
        return $res;
    }

    public function saveLeader($id, $name, $title, $type, $subtitle, $sort, $active, $userId, $imagePath = null) {
        $uid = ((int)$userId > 0) ? (int)$userId : null;
        if ($id) {
            $sql = "UPDATE parish_leadership SET name=?, title=?, position_type=?, subtitle=?, sort_order=?, is_active=?, updated_at=NOW()";
            if ($imagePath !== null) {
                $sql .= ", image_path=? WHERE leader_id=?";
                $stmt = $this->conn->prepare($sql);
                if (!$stmt) return false;
                $stmt->bind_param("ssssiisi", $name, $title, $type, $subtitle, $sort, $active, $imagePath, $id);
            } else {
                $sql .= " WHERE leader_id=?";
                $stmt = $this->conn->prepare($sql);
                if (!$stmt) return false;
                $stmt->bind_param("ssssiii", $name, $title, $type, $subtitle, $sort, $active, $id);
            }
        } else {
            $stmt = $this->conn->prepare("INSERT INTO parish_leadership (name, title, position_type, subtitle, sort_order, is_active, created_by, image_path) VALUES (?,?,?,?,?,?,?,?)");
            if (!$stmt) return false;
            $stmt->bind_param("ssssiiis", $name, $title, $type, $subtitle, $sort, $active, $uid, $imagePath);
        }
        $res = $stmt->execute();
        $stmt->close();
        return $res;
    }

    public function deleteLeader($id) {
        $stmt = $this->conn->prepare("DELETE FROM parish_leadership WHERE leader_id=?");
        if (!$stmt) return false;
        $stmt->bind_param("i", $id);
        $res = $stmt->execute();
        $stmt->close();
        return $res;
    }

    private function tableExists(string $table): bool {
        if (array_key_exists($table, $this->tableExistsCache)) {
            return $this->tableExistsCache[$table];
        }

        $stmt = $this->conn->prepare(
            "SELECT 1
             FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
             LIMIT 1"
        );
        if (!$stmt) {
            $this->tableExistsCache[$table] = false;
            return false;
        }

        $stmt->bind_param("s", $table);
        $stmt->execute();
        $res = $stmt->get_result();
        $this->tableExistsCache[$table] = (bool)($res && $res->num_rows > 0);
        $stmt->close();

        return $this->tableExistsCache[$table];
    }
}
