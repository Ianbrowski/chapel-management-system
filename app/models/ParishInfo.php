<?php
class ParishInfo {
    private $conn;
    private array $tableExistsCache = [];
    private bool $historySchemaEnsured = false;

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

        $cntRes = $this->conn->query("SELECT COUNT(*) AS c FROM parish_contact_info");
        $cntRow = $cntRes ? $cntRes->fetch_assoc() : null;
        if ((int)($cntRow['c'] ?? 0) === 0) {
            @$this->conn->query("INSERT INTO parish_contact_info (parish_name, email) VALUES ('Cathedral of St. Michael the Archangel', 'cathedral@ifi.org.ph')");
        }

        $webhookUrl = trim((string)($data['email_webhook_url'] ?? ''));
        $emailProvider = strtolower(trim((string)($data['email_provider'] ?? '')));
        $smtpPort = ((int)($data['smtp_port'] ?? 0) > 0) ? (int)$data['smtp_port'] : 587;
        $stmt = $this->conn->prepare("UPDATE parish_contact_info SET 
            smtp_host=?, smtp_port=?, smtp_user=?, smtp_secure=?, 
            from_email=?, from_name=?, email_webhook_url=?, email_provider=? 
            WHERE info_id = (SELECT info_id FROM (SELECT info_id FROM parish_contact_info ORDER BY info_id DESC LIMIT 1) as t)");
        
        if (!$stmt) return false;
        $stmt->bind_param("sissssss",
            $data['smtp_host'], $smtpPort, $data['smtp_user'],
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
        $this->ensureHistoryEnhancements();
        if ($this->tableExists('parish_history_intro')) {
            $hi_res = $this->conn->query("SELECT intro_text FROM parish_history_intro ORDER BY intro_id DESC LIMIT 1");
            if ($hi_res && $hi_res->num_rows > 0) return $hi_res->fetch_assoc()['intro_text'];
        }
        
        return 'Rooted in the patriotic and spiritual heritage of Bacoor, Cavite, the Cathedral of St. Michael the Archangel (Iglesia Filipina Independiente) preserves generations of Filipino faith, civic courage, and community stewardship.';
    }

    public function getHistoryItems() {
        $this->ensureHistoryEnhancements();
        $items = [];
        if ($this->tableExists('parish_history')) {
            $ph_res = $this->conn->query("SELECT * FROM parish_history WHERE is_active=1 ORDER BY sort_order ASC, history_id ASC");
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

    public function ensureHistoryEnhancements(): void {
        if ($this->historySchemaEnsured) {
            return;
        }
        $this->historySchemaEnsured = true;

        if (!$this->tableExists('parish_history')) {
            return;
        }

        // 1. Widen `year` to accommodate ranges like "1980s–1995" and "2020–Present"
        $yrRes = $this->conn->query("SHOW COLUMNS FROM parish_history LIKE 'year'");
        if ($yrRes && ($yrCol = $yrRes->fetch_assoc())) {
            $typeStr = strtolower((string)($yrCol['Type'] ?? ''));
            if (preg_match('/varchar\((\d+)\)/', $typeStr, $m) && (int)$m[1] < 30) {
                @$this->conn->query("ALTER TABLE parish_history MODIFY COLUMN `year` VARCHAR(30) NOT NULL");
            }
        }

        // 2. Ensure image columns exist on parish_history
        $histCols = [
            'image_path'   => 'VARCHAR(255) NULL',
            'image_credit' => 'VARCHAR(150) NULL',
        ];
        foreach ($histCols as $colName => $colDef) {
            $colRes = $this->conn->query("SHOW COLUMNS FROM parish_history LIKE '{$colName}'");
            if ($colRes && $colRes->num_rows === 0) {
                @$this->conn->query("ALTER TABLE parish_history ADD COLUMN {$colName} {$colDef}");
            }
        }

        // 3. Upgrade untouched placeholder starter rows cleanly to the researched IFI Bacoor chronicle
        $existing = [];
        $res = $this->conn->query("SELECT history_id, year, title FROM parish_history ORDER BY history_id ASC");
        if ($res) {
            while ($r = $res->fetch_assoc()) {
                $existing[] = $r;
            }
            $res->free();
        }

        $isEmpty = empty($existing);
        $titles = array_map(static fn($r) => trim((string)($r['title'] ?? '')), $existing);
        $isLegacySeed = (count($existing) === 4 && $titles === ['Foundation', 'Cathedral Built', 'Major Restoration', 'Digital Transformation']);

        if ($isEmpty || $isLegacySeed) {
            $milestones = [
                [
                    'year' => 'Pre-1902',
                    'title' => 'Revolutionary & GOMBURZA Roots (Bacoor)',
                    'description' => "Bacoor's deep patriotic consciousness was shaped by Fr. Mariano Gomes (long-serving parish priest of Bacoor and martyr of GOMBURZA, 1872) and the town's vital role in the Philippine Revolution.",
                    'sort_order' => 1,
                    'image_path' => 'assets/img/history/pre1902_gomes_monument.jpg',
                    'image_credit' => 'Plaza Mariano Gomes Monument, Bacoor (Wikimedia Commons, CC BY 4.0)',
                ],
                [
                    'year' => 'Dec 1902',
                    'title' => 'Embracing the Iglesia Filipina Independiente',
                    'description' => "Months after the 1902 proclamation of the IFI by Isabelo de los Reyes and Obispo Maximo Gregorio Aglipay, Bacoor's cura parroco Padre Fortunato Clemeña joined the nationalist church alongside the vast majority of Bacoor's faithful (later becoming the first Obispo de Cavite), while revolutionary Gen. Mariano Noriel served as first president of Bacoor's IFI laymen's branch.",
                    'sort_order' => 2,
                    'image_path' => 'assets/img/history/1902_bishop_aglipay.jpg',
                    'image_credit' => 'Obispo Maximo Gregorio Aglipay, c. 1905 (Wikimedia Commons, Public Domain)',
                ],
                [
                    'year' => '1906–1912',
                    'title' => 'Steadfast Faith & Building Our Sanctuary',
                    'description' => 'Following the legal turnover of Spanish-era stone parish structures (Barlin v. Ramirez), the IFI congregation worshiped in temporary chapels (including Barrio Maliksi) and constructed their own parish sanctuary and chapels across major phases from 1906 to 1912.',
                    'sort_order' => 3,
                    'image_path' => 'assets/img/history/1906_ifi_bacoor_bell.jpg',
                    'image_credit' => 'Historic Bacoor IFI Church Bell, Digman (Ralff Nestor Nacor, CC BY-SA 4.0)',
                ],
                [
                    'year' => '1950–1965',
                    'title' => 'Parish Growth & Bacoor Parish School',
                    'description' => 'Mid-century infrastructure expansion (~1950), episcopal milestones under diocesan leadership (e.g., Bishop Montenegro, consecrated 1957), and the founding of Bacoor Parish School (1965) for youth and community formation.',
                    'sort_order' => 4,
                    'image_path' => 'assets/img/history/1950_ifi_bacoor_2.jpg',
                    'image_credit' => 'Cathedral Sanctuary & Belltower, Digman, Bacoor (Ralff Nestor Nacor, CC BY-SA 4.0)',
                ],
                [
                    'year' => '1980s–1995',
                    'title' => 'Renewal & Seat of the Cathedral',
                    'description' => 'Navigating periods of institutional consolidation in the 1980s and episcopal stewardship (e.g., Bishop Lucea, consecrated 1995), the Parish of St. Michael the Archangel grew as the Cathedral seat serving Digman and partner chapels across Bacoor.',
                    'sort_order' => 5,
                    'image_path' => 'assets/img/history/1980_ifi_bacoor_1.jpg',
                    'image_credit' => 'IFI Cathedral of St. Michael the Archangel Facade (Ralff Nestor Nacor, CC BY-SA 4.0)',
                ],
                [
                    'year' => '2020–Present',
                    'title' => 'Preserving Archives & Digital Stewardship',
                    'description' => 'Safeguarding multi-generational sacramental registries alongside modern parish scheduling, certificate issuance, and community ministries.',
                    'sort_order' => 6,
                    'image_path' => 'storage/uploads/main_chapel_i.png',
                    'image_credit' => 'Cathedral of St. Michael the Archangel, Digman, Bacoor',
                ],
            ];

            $this->conn->begin_transaction();
            try {
                if ($isLegacySeed) {
                    @$this->conn->query("DELETE FROM parish_history");
                }
                $ins = $this->conn->prepare("INSERT INTO parish_history (year, title, description, sort_order, is_active, image_path, image_credit) VALUES (?, ?, ?, ?, 1, ?, ?)");
                if ($ins) {
                    foreach ($milestones as $ms) {
                        $ins->bind_param('sssiss', $ms['year'], $ms['title'], $ms['description'], $ms['sort_order'], $ms['image_path'], $ms['image_credit']);
                        $ins->execute();
                    }
                    $ins->close();
                }

                if ($this->tableExists('parish_history_intro')) {
                    $this->ensureIntroRecord('parish_history_intro');
                    $introRes = $this->conn->query("SELECT intro_text FROM parish_history_intro ORDER BY intro_id DESC LIMIT 1");
                    $curIntro = $introRes ? trim((string)($introRes->fetch_assoc()['intro_text'] ?? '')) : '';
                    if ($curIntro === '' || stripos($curIntro, 'The Iglesia Filipina Independiente Cathedral stands as a testament') === 0) {
                        $newIntro = 'Rooted in Bacoor\'s patriotic heritage and the 1902 proclamation of the Iglesia Filipina Independiente, the Cathedral of St. Michael the Archangel in Digman continues over a century of faith, civic courage, and community stewardship.';
                        $upIntro = $this->conn->prepare("UPDATE parish_history_intro SET intro_text = ?, updated_at = NOW() WHERE intro_id = 1");
                        if ($upIntro) {
                            $upIntro->bind_param('s', $newIntro);
                            $upIntro->execute();
                            $upIntro->close();
                        }
                    }
                }
                $this->conn->commit();
            } catch (Throwable $e) {
                $this->conn->rollback();
            }
        }
    }

    public function getHistoryItemById($id) {
        $this->ensureHistoryEnhancements();
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

    public function saveHistory($id, $year, $title, $description, $sort, $active, $userId, $imagePath = null, $imageCredit = null) {
        $this->ensureHistoryEnhancements();
        $uid = ((int)$userId > 0) ? (int)$userId : null;
        $credit = ($imageCredit !== null && trim((string)$imageCredit) !== '') ? trim((string)$imageCredit) : null;
        if ($id) {
            if ($imagePath !== null) {
                $imgVal = ($imagePath !== '') ? $imagePath : null;
                $stmt = $this->conn->prepare("UPDATE parish_history SET year=?, title=?, description=?, sort_order=?, is_active=?, image_path=?, image_credit=?, updated_at=NOW() WHERE history_id=?");
                if (!$stmt) return false;
                $stmt->bind_param("sssiissi", $year, $title, $description, $sort, $active, $imgVal, $credit, $id);
            } else {
                $stmt = $this->conn->prepare("UPDATE parish_history SET year=?, title=?, description=?, sort_order=?, is_active=?, image_credit=?, updated_at=NOW() WHERE history_id=?");
                if (!$stmt) return false;
                $stmt->bind_param("sssiisi", $year, $title, $description, $sort, $active, $credit, $id);
            }
        } else {
            $imgVal = ($imagePath !== null && $imagePath !== '') ? $imagePath : null;
            $stmt = $this->conn->prepare("INSERT INTO parish_history (year, title, description, sort_order, is_active, created_by, image_path, image_credit) VALUES (?,?,?,?,?,?,?,?)");
            if (!$stmt) return false;
            $stmt->bind_param("sssiiiss", $year, $title, $description, $sort, $active, $uid, $imgVal, $credit);
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
