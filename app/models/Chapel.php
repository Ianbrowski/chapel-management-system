<?php
class Chapel {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    /**
     * Resync the cached `chapel_location` display field in the events table
     * for all future, non-archived events assigned to this chapel.
     *
     * Called automatically when a chapel's name changes via update().
     *
     * @param int    $chapelId The ID of the chapel whose name changed.
     * @param string $newName  The new chapel_name value.
     */
    private function resyncEventChapelLocations(int $chapelId, string $newName): void
    {
        $stmt = $this->conn->prepare(
            "UPDATE events
                SET chapel_location = ?
              WHERE chapel_id = ?
                AND start_time >= NOW()
                AND is_archived = 0"
        );
        if (!$stmt) {
            error_log("resyncEventChapelLocations: failed to prepare statement for chapel_id={$chapelId}");
            return;
        }
        $stmt->bind_param('si', $newName, $chapelId);
        $stmt->execute();
        $stmt->close();
}

    public function getById($id) {
        $stmt = $this->conn->prepare("SELECT * FROM chapels WHERE chapel_id = ?");
        if (!$stmt) return null;
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $res;
    }

    public function create($name, $location, $patron, $capacity, $coord, $phone, $fiestas = [], $sundayServiceTime = 'Check Parish Schedule', $themeColor = '#4f46e5', $imagePath = null, $smtp = []) {
        $this->conn->begin_transaction();
        try {
            $themeColor = preg_match('/^#[0-9a-fA-F]{6}$/', $themeColor) ? $themeColor : '#4f46e5';
            
            $s = $this->conn->prepare("INSERT INTO chapels (
                chapel_name, location, patron_saint, seating_capacity, coordinator_name, coordinator_phone, 
                sunday_service_time, theme_color, chapel_image,
                smtp_host, smtp_port, smtp_user, smtp_pass, smtp_secure, from_email, from_name
            ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
            
            if (!$s) {
                throw new Exception($this->conn->error);
            }

            $s_host   = $smtp['host'] ?? null;
            $s_port   = isset($smtp['port']) ? (int)$smtp['port'] : null;
            $s_user   = $smtp['user'] ?? null;
            $s_pass   = $smtp['pass'] ?? null;
            $s_secure = $smtp['secure'] ?? 'tls';
            $s_from_e = $smtp['from_email'] ?? null;
            $s_from_n = $smtp['from_name'] ?? null;

            $s->bind_param('sssisssssssissss', 
                $name, $location, $patron, $capacity, $coord, $phone, 
                $sundayServiceTime, $themeColor, $imagePath,
                $s_host, $s_port, $s_user, $s_pass, $s_secure, $s_from_e, $s_from_n
            );
            
            if (!$s->execute()) {
                throw new Exception($this->conn->error);
            }
            $newId = $this->conn->insert_id;
            $s->close();

            if (!empty($fiestas)) {
                $this->saveFiestas($newId, $fiestas);
            }

            $this->conn->commit();
            return true;
        } catch (Throwable $e) {
            $this->conn->rollback();
            error_log("Chapel creation failed: " . $e->getMessage());
            return false;
        }
    }

    public function update($id, $name, $location, $patron, $capacity, $coord, $phone, $status, $sundayServiceTime = 'Check Parish Schedule', $fiestas = [], $themeColor = '#4f46e5', $imagePath = null, $smtp = []) {
        $this->conn->begin_transaction();
        try {
            $themeColor = preg_match('/^#[0-9a-fA-F]{6}$/', $themeColor) ? $themeColor : '#4f46e5';
            
            $s_host   = $smtp['host'] ?? null;
            $s_port   = isset($smtp['port']) ? (int)$smtp['port'] : null;
            $s_user   = $smtp['user'] ?? null;
            $s_pass   = isset($smtp['pass']) ? trim((string)$smtp['pass']) : '';
            $hasPass  = ($s_pass !== '');
            $s_secure = $smtp['secure'] ?? 'tls';
            $s_from_e = $smtp['from_email'] ?? null;
            $s_from_n = $smtp['from_name'] ?? null;

            $sql = "UPDATE chapels SET 
                    chapel_name=?, location=?, patron_saint=?, seating_capacity=?, 
                    coordinator_name=?, coordinator_phone=?, status=?, 
                    sunday_service_time=?, theme_color=?,
                    smtp_host=?, smtp_port=?, smtp_user=?";
            if ($hasPass) {
                $sql .= ", smtp_pass=?";
            }
            $sql .= ", smtp_secure=?, from_email=?, from_name=?";

            if ($imagePath !== null) {
                $sql .= ", chapel_image=?";
            }
            $sql .= " WHERE chapel_id=?";

            $s = $this->conn->prepare($sql);
            if (!$s) {
                throw new Exception($this->conn->error);
            }

            $types = 'sssissssssis';
            $params = [
                $name, $location, $patron, $capacity, $coord, $phone, $status,
                $sundayServiceTime, $themeColor, $s_host, $s_port, $s_user
            ];
            if ($hasPass) {
                $types .= 's';
                $params[] = $s_pass;
            }
            $types .= 'sss';
            $params[] = $s_secure;
            $params[] = $s_from_e;
            $params[] = $s_from_n;
            if ($imagePath !== null) {
                $types .= 's';
                $params[] = $imagePath;
            }
            $types .= 'i';
            $params[] = $id;
            $s->bind_param($types, ...$params);
            if (!$s->execute()) {
                throw new Exception($this->conn->error);
            }
            $s->close();

            $this->saveFiestas($id, $fiestas);

            // Resync chapel_location cache in upcoming events after successful chapel name update.
            $this->resyncEventChapelLocations((int)$id, (string)$name);

            $this->conn->commit();
            return true;
        } catch (Throwable $e) {
            $this->conn->rollback();
            error_log("Chapel update failed: " . $e->getMessage());
            return false;
        }
    }

    public function saveFiestas($chapelId, $fiestas) {
        // Clear existing fiestas for a clean sync
        $delete = $this->conn->prepare("DELETE FROM chapel_fiestas WHERE chapel_id = ?");
        if (!$delete) {
            throw new Exception('Unable to clear chapel fiestas.');
        }
        $delete->bind_param("i", $chapelId);
        if (!$delete->execute()) {
            throw new Exception($this->conn->error);
        }
        $delete->close();
        
        if (empty($fiestas)) return;

        $stmt = $this->conn->prepare("INSERT INTO chapel_fiestas (chapel_id, fiesta_name, fiesta_date) VALUES (?, ?, ?)");
        if (!$stmt) {
            throw new Exception('Unable to save chapel fiestas.');
        }
        foreach ($fiestas as $f) {
            if (empty($f['name']) || empty($f['date'])) continue;
            $stmt->bind_param("iss", $chapelId, $f['name'], $f['date']);
            if (!$stmt->execute()) {
                throw new Exception($this->conn->error);
            }
        }
        $stmt->close();
    }

    public function getFiestas($chapelId) {
        $stmt = $this->conn->prepare("SELECT fiesta_name as name, fiesta_date as date FROM chapel_fiestas WHERE chapel_id = ? ORDER BY fiesta_date ASC");
        if (!$stmt) {
            return [];
        }
        $stmt->bind_param("i", $chapelId);
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();
        return $rows;
    }

    public function delete($id) {
        $id = (int)$id;

        // Check for dependencies to avoid orphans/errors
        // 1. Check for Users
        $res = $this->conn->query("SELECT id FROM users WHERE chapel_id = $id LIMIT 1");
        if ($res && $res->num_rows > 0) {
            throw new Exception("Cannot delete: There are users assigned to this chapel.");
        }

        // 2. Check for Events (active or archived)
        $res = $this->conn->query("SELECT event_id FROM events WHERE chapel_id = $id LIMIT 1");
        if ($res && $res->num_rows > 0) {
            throw new Exception("Cannot delete: There are events associated with this chapel.");
        }

        // 3. Check for Sacramental Records (active or archived)
        $res = $this->conn->query("SELECT record_id FROM sacramental_records WHERE chapel_id = $id LIMIT 1");
        if ($res && $res->num_rows > 0) {
            throw new Exception("Cannot delete: There are sacramental records associated with this chapel.");
        }

        // 4. Check for Donations
        $res = $this->conn->query("SELECT donation_id FROM donations WHERE chapel_id = $id LIMIT 1");
        if ($res && $res->num_rows > 0) {
            throw new Exception("Cannot delete: There are donation records logged for this chapel.");
        }

        // 5. Check for Public Inquiries
        $res = $this->conn->query("SELECT inquiry_id FROM public_inquiries WHERE chapel_id = $id LIMIT 1");
        if ($res && $res->num_rows > 0) {
            throw new Exception("Cannot delete: There are public inquiries received for this chapel.");
        }

        $this->conn->begin_transaction();
        try {
            // Delete chapel fiestas to avoid orphaned fiesta data
            $stmt1 = $this->conn->prepare("DELETE FROM chapel_fiestas WHERE chapel_id = ?");
            if ($stmt1) {
                $stmt1->bind_param("i", $id);
                $stmt1->execute();
                $stmt1->close();
            }

            // Delete the chapel itself
            $stmt2 = $this->conn->prepare("DELETE FROM chapels WHERE chapel_id = ?");
            if (!$stmt2) {
                throw new Exception($this->conn->error);
            }
            $stmt2->bind_param("i", $id);
            $ok = $stmt2->execute();
            $stmt2->close();

            if (!$ok) {
                throw new Exception($this->conn->error ?: 'Unable to delete chapel record.');
            }

            $this->conn->commit();
            return true;
        } catch (Throwable $e) {
            $this->conn->rollback();
            throw $e;
        }
    }

    public function getAll($chapelId = null) {
        $sql = "SELECT c.*, 
                (SELECT GROUP_CONCAT(CONCAT(fiesta_name, '|||', fiesta_date) SEPARATOR '===') 
                 FROM chapel_fiestas WHERE chapel_id = c.chapel_id) as raw_fiestas,
                (SELECT GROUP_CONCAT(CONCAT(fiesta_name, ' (', fiesta_date, ')') SEPARATOR ', ') 
                 FROM chapel_fiestas WHERE chapel_id = c.chapel_id) as fiesta_list 
                FROM chapels c";
                
        if ($chapelId) {
            $sql .= " WHERE c.chapel_id = ?";
        }
        $sql .= " ORDER BY c.chapel_name ASC";
        
        $chapels = [];
        $stmt = $this->conn->prepare($sql);
        if ($stmt) {
            if ($chapelId) {
                $stmt->bind_param("i", $chapelId);
            }
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $fArray = [];
                if (!empty($row['raw_fiestas'])) {
                    $pairs = explode('===', $row['raw_fiestas']);
                    foreach ($pairs as $p) {
                        $parts = explode('|||', $p);
                        if (count($parts) === 2) {
                            $fArray[] = ['name' => $parts[0], 'date' => $parts[1]];
                        }
                    }
                }
                $row['fiestas_json'] = json_encode($fArray);
                $chapels[] = $row;
            }
            $stmt->close();
        }
        return $chapels;
    }

    public function countAll() {
        $res = $this->conn->query("SELECT COUNT(*) as c FROM chapels");
        $row = $res->fetch_assoc();
        return (int)($row['c'] ?? 0);
    }
}
