<?php
class User {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    private function prepareStmt(string $sql) {
        $stmt = $this->conn->prepare($sql);
        return $stmt ?: null;
    }

    /**
     * Resync the cached `pastor_assigned` display field in the events table
     * for all future, non-archived events assigned to this pastor.
     *
     * Called automatically when a Pastor's full_name changes via update(),
     * updateProfile(), or updateProfileAndPassword().
     *
     * @param int    $pastorId The ID of the pastor whose name changed.
     * @param string $newName  The new display name (full_name or username fallback).
     */
    private function resyncEventPastorNames(int $pastorId, string $newName): void
    {
        $stmt = $this->prepareStmt(
            "UPDATE events
                SET pastor_assigned = ?
              WHERE pastor_id = ?
                AND start_time >= NOW()
                AND is_archived = 0"
        );
        if (!$stmt) {
            error_log("resyncEventPastorNames: failed to prepare statement for pastor_id={$pastorId}");
            return;
        }
        $stmt->bind_param('si', $newName, $pastorId);
        $stmt->execute();
        $stmt->close();
    }

    public function getById($id) {
        $stmt = $this->prepareStmt("SELECT * FROM users WHERE id = ?");
        if (!$stmt) return null;
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $res;
    }

    public function getByExt($id) {
        $stmt = $this->prepareStmt("SELECT full_name, profile_pic FROM users WHERE id = ?");
        if (!$stmt) return null;
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $res;
    }

    public function getMyPasswordHash($id) {
        $chk = $this->prepareStmt("SELECT password FROM users WHERE id = ?");
        if (!$chk) return null;
        $chk->bind_param('i', $id); $chk->execute();
        $chk_row = $chk->get_result()->fetch_assoc(); $chk->close();
        return $chk_row['password'] ?? null;
    }

    public function updateProfile($id, $fullName, $profilePicPath = null) {
        if ($profilePicPath) {
            $s = $this->prepareStmt("UPDATE users SET full_name=?, profile_pic=? WHERE id=?");
        } else {
            $s = $this->prepareStmt("UPDATE users SET full_name=? WHERE id=?");
        }
        if (!$s) throw new Exception($this->conn->error ?: 'Unable to update profile.');
        if ($profilePicPath) {
            $s->bind_param('ssi', $fullName, $profilePicPath, $id);
        } else {
            $s->bind_param('si', $fullName, $id);
        }
        $res = $s->execute();
        if (!$res) throw new Exception($this->conn->error);
        $s->close();

        // Resync pastor_assigned cache in upcoming events if this user is a Priest.
        if ($res && !empty($fullName)) {
            $userRow = $this->getById((int)$id);
            if ($userRow && ($userRow['role'] ?? '') === 'Priest') {
                $displayName = $fullName ?: ($userRow['user'] ?? '');
                $this->resyncEventPastorNames((int)$id, $displayName);
            }
        }

        return $res;
    }

    public function updatePassword($id, $hashedPassword) {
        $s = $this->prepareStmt("UPDATE users SET password=? WHERE id=?");
        if (!$s) throw new Exception($this->conn->error ?: 'Unable to update password.');
        $s->bind_param('si', $hashedPassword, $id);
        $res = $s->execute();
        if (!$res) throw new Exception($this->conn->error);
        $s->close();
        return $res;
    }

    public function updateProfileAndPassword($id, $fullName, $profilePicPath, $hashedPassword) {
        if ($profilePicPath) {
            $s = $this->prepareStmt("UPDATE users SET full_name=?, profile_pic=?, password=? WHERE id=?");
        } else {
            $s = $this->prepareStmt("UPDATE users SET full_name=?, password=? WHERE id=?");
        }
        if (!$s) throw new Exception($this->conn->error ?: 'Unable to update profile.');
        if ($profilePicPath) {
            $s->bind_param('sssi', $fullName, $profilePicPath, $hashedPassword, $id);
        } else {
            $s->bind_param('ssi', $fullName, $hashedPassword, $id);
        }
        $res = $s->execute();
        if (!$res) throw new Exception($this->conn->error);
        $s->close();

        // Resync pastor_assigned cache in upcoming events if this user is a Priest.
        if ($res && !empty($fullName)) {
            $userRow = $this->getById((int)$id);
            if ($userRow && ($userRow['role'] ?? '') === 'Priest') {
                $displayName = $fullName ?: ($userRow['user'] ?? '');
                $this->resyncEventPastorNames((int)$id, $displayName);
            }
        }

        return $res;
    }

    public function create($username, $hashedPassword, $role, $email = null, $chapelId = null, $fullName = null, $status = 'active', $phone = null) {
        $ins = $this->prepareStmt('INSERT INTO users (`user`,`password`,`role`,`email`,`chapel_id`,`full_name`,`status`,`phone`) VALUES (?,?,?,?,?,?,?,?)');
        if (!$ins) return false;
        $chapelIdInt = $chapelId ? (int)$chapelId : null;
        $ins->bind_param('ssssisss', $username, $hashedPassword, $role, $email, $chapelIdInt, $fullName, $status, $phone);
        $res = $ins->execute();
        $ins->close();
        return $res;
    }

    public function update($id, $username, $role, $hashedPassword = null, $email = null, $chapelId = null, $fullName = null) {
        $chapelIdInt = $chapelId ? (int)$chapelId : null;
        if ($hashedPassword) {
            $s = $this->prepareStmt('UPDATE users SET `user`=?,`password`=?,`role`=?,`email`=?,`chapel_id`=?,`full_name`=? WHERE id=?');
        } else {
            $s = $this->prepareStmt('UPDATE users SET `user`=?,`role`=?,`email`=?,`chapel_id`=?,`full_name`=? WHERE id=?');
        }
        if (!$s) return false;
        if ($hashedPassword) {
            $s->bind_param('ssssisi', $username, $hashedPassword, $role, $email, $chapelIdInt, $fullName, $id);
        } else {
            $s->bind_param('sssisi', $username, $role, $email, $chapelIdInt, $fullName, $id);
        }
        $res = $s->execute();
        $s->close();

        // Resync pastor_assigned cache in upcoming events if this user is (or was) a Priest.
        if ($res && $role === 'Priest' && !empty($fullName)) {
            $displayName = $fullName ?: $username;
            $this->resyncEventPastorNames((int)$id, $displayName);
        }

        return $res;
    }

    public function getPriestEventCounts(int $userId): array {
        $counts = ['upcoming_active' => 0, 'past_or_closed' => 0, 'total' => 0];
        if ($userId <= 0) return $counts;
        $stmt = $this->prepareStmt(
            "SELECT
                SUM(CASE WHEN start_time >= NOW() AND status IN ('Pending', 'Confirmed') THEN 1 ELSE 0 END) AS upcoming_active,
                COUNT(*) AS total
             FROM events
             WHERE pastor_id = ? AND is_archived = 0"
        );
        if (!$stmt) return $counts;
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $total = (int)($row['total'] ?? 0);
        $upcoming = (int)($row['upcoming_active'] ?? 0);
        return [
            'upcoming_active' => $upcoming,
            'past_or_closed'  => max(0, $total - $upcoming),
            'total'           => $total,
        ];
    }

    public function getEventStatsByUserIds(array $userIds): array {
        $ids = array_values(array_unique(array_filter(array_map('intval', $userIds), fn($v) => $v > 0)));
        if (empty($ids)) return [];
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $types = str_repeat('i', count($ids));
        $sql = "SELECT pastor_id,
                       SUM(CASE WHEN start_time >= NOW() AND status IN ('Pending', 'Confirmed') THEN 1 ELSE 0 END) AS upcoming_active,
                       COUNT(*) AS total
                FROM events
                WHERE is_archived = 0 AND pastor_id IN ({$placeholders})
                GROUP BY pastor_id";
        $stmt = $this->prepareStmt($sql);
        if (!$stmt) return [];
        $stmt->bind_param($types, ...$ids);
        $stmt->execute();
        $res = $stmt->get_result();
        $map = [];
        while ($row = $res->fetch_assoc()) {
            $pid = (int)$row['pastor_id'];
            $total = (int)($row['total'] ?? 0);
            $upcoming = (int)($row['upcoming_active'] ?? 0);
            $map[$pid] = [
                'upcoming_active' => $upcoming,
                'past_or_closed'  => max(0, $total - $upcoming),
                'total'           => $total,
            ];
        }
        $stmt->close();
        return $map;
    }

    public function setUserStatus(int $id, string $status): bool {
        if ($id <= 0 || !in_array($status, ['active', 'inactive'], true)) {
            return false;
        }
        $s = $this->prepareStmt("UPDATE users SET `status` = ? WHERE id = ?");
        if (!$s) return false;
        $s->bind_param('si', $status, $id);
        $res = $s->execute();
        $s->close();
        return $res;
    }

    public function reassignUpcomingEvents(int $fromPastorId, ?int $toPastorId): int {
        if ($fromPastorId <= 0) {
            throw new Exception('Invalid source priest account.');
        }
        $newPastorId = ($toPastorId !== null && $toPastorId > 0) ? $toPastorId : null;
        $newDisplayName = null;

        if ($newPastorId !== null) {
            if ($newPastorId === $fromPastorId) {
                throw new Exception('Replacement priest must be a different account.');
            }
            $target = $this->getById($newPastorId);
            if (!$target || !in_array($target['role'] ?? '', ['Priest', 'Pastor'], true) || strtolower(trim((string)($target['status'] ?? 'active'))) !== 'active') {
                throw new Exception('Replacement must be an active Priest account.');
            }
            $newDisplayName = !empty($target['full_name']) ? $target['full_name'] : ($target['user'] ?? 'Priest');
        }

        $this->conn->begin_transaction();
        try {
            $stmt = $this->prepareStmt(
                "UPDATE events
                    SET pastor_id = ?, pastor_assigned = ?
                  WHERE pastor_id = ?
                    AND start_time >= NOW()
                    AND is_archived = 0
                    AND status IN ('Pending', 'Confirmed')"
            );
            if (!$stmt) {
                throw new Exception($this->conn->error ?: 'Unable to prepare bulk reassignment.');
            }
            $stmt->bind_param('isi', $newPastorId, $newDisplayName, $fromPastorId);
            if (!$stmt->execute()) {
                throw new Exception($this->conn->error ?: 'Failed to reassign upcoming events.');
            }
            $affected = (int)$stmt->affected_rows;
            $stmt->close();
            $this->conn->commit();
            return $affected;
        } catch (Throwable $e) {
            $this->conn->rollback();
            throw $e;
        }
    }

    public function delete($id) {
        $id = (int)$id;

        // Check if user has associated records in the database
        // 1. Events requested by the user
        $res = $this->conn->query("SELECT event_id FROM events WHERE requested_by = $id LIMIT 1");
        if ($res && $res->num_rows > 0) {
            throw new Exception("Cannot delete: This user has requested events in the system. Consider deactivating the account instead.");
        }

        // 2. Events assigned to the user as a pastor
        $evCounts = $this->getPriestEventCounts($id);
        if ($evCounts['total'] > 0) {
            throw new Exception("Cannot delete: This priest has {$evCounts['upcoming_active']} upcoming active and {$evCounts['past_or_closed']} past/closed event(s) assigned. Deactivate the account (or bulk-reassign upcoming events) instead so parish records remain intact.");
        }

        // 3. Donations logged by the user
        $res = $this->conn->query("SELECT donation_id FROM donations WHERE logged_by = $id LIMIT 1");
        if ($res && $res->num_rows > 0) {
            throw new Exception("Cannot delete: This user has logged donation transactions.");
        }

        // 4. Donations voided by the user
        $res = $this->conn->query("SELECT donation_id FROM donations WHERE voided_by = $id LIMIT 1");
        if ($res && $res->num_rows > 0) {
            throw new Exception("Cannot delete: This user has voided donation transactions.");
        }

        // 5. Sacramental records encoded by the user
        $res = $this->conn->query("SELECT record_id FROM sacramental_records WHERE encoded_by = $id LIMIT 1");
        if ($res && $res->num_rows > 0) {
            throw new Exception("Cannot delete: This user has encoded sacramental records.");
        }

        // 6. Sacramental records updated by the user
        $res = $this->conn->query("SELECT record_id FROM sacramental_records WHERE updated_by = $id LIMIT 1");
        if ($res && $res->num_rows > 0) {
            throw new Exception("Cannot delete: This user has updated sacramental records.");
        }

        $this->conn->begin_transaction();
        try {
            // Delete pastor event type preferences if any exist
            $stmt1 = $this->conn->prepare('DELETE FROM pastor_event_types WHERE pastor_id = ?');
            if ($stmt1) {
                $stmt1->bind_param('i', $id);
                $stmt1->execute();
                $stmt1->close();
            }

            // Perform deletion of the user
            $stmt2 = $this->conn->prepare('DELETE FROM users WHERE id = ?');
            if (!$stmt2) {
                throw new Exception($this->conn->error);
            }
            $stmt2->bind_param('i', $id);
            $res = $stmt2->execute();
            $stmt2->close();

            if (!$res) {
                throw new Exception($this->conn->error ?: 'Unable to delete user record.');
            }

            $this->conn->commit();
            return true;
        } catch (Throwable $e) {
            $this->conn->rollback();
            throw $e;
        }
    }

    public function exists($username, $excludeId = null) {
        if ($excludeId) {
            $ck = $this->prepareStmt('SELECT id FROM users WHERE user=? AND id!=?');
        } else {
            $ck = $this->prepareStmt('SELECT id FROM users WHERE user=?');
        }
        if (!$ck) return false;
        if ($excludeId) {
            $ck->bind_param('si', $username, $excludeId);
        } else {
            $ck->bind_param('s', $username);
        }
        $ck->execute();
        $exists = $ck->get_result()->num_rows > 0;
        $ck->close();
        return $exists;
    }

    public function getPaginated(int $page = 1, int $perPage = 20, ?int $chapelFilter = null): array
    {
        $total = $this->countAll($chapelFilter);
        $pages = (int) ceil($total / $perPage);
        $page = max(1, min($page, $pages ?: 1));
        $offset = ($page - 1) * $perPage;

        $where = $chapelFilter !== null ? "WHERE chapel_id = ?" : "";
        $sql = "SELECT id, user, full_name, email, phone, role, status, chapel_id, created_at 
                FROM users 
                $where 
                ORDER BY id DESC 
                LIMIT ? OFFSET ?";

        $stmt = $this->conn->prepare($sql);
        if ($chapelFilter !== null) {
            $stmt->bind_param('iii', $chapelFilter, $perPage, $offset);
        } else {
            $stmt->bind_param('ii', $perPage, $offset);
        }

        $stmt->execute();
        $res = $stmt->get_result();
        $records = [];
        while ($r = $res->fetch_assoc()) $records[] = $r;
        $stmt->close();

        return [
            'records' => $records,
            'total'   => $total,
            'pages'   => $pages,
            'page'    => $page
        ];
    }

    public function getAll(?int $chapelFilter = null) {
        $users = [];
        if ($chapelFilter !== null) {
            $stmt = $this->prepareStmt("
                SELECT id, user, full_name, email, phone, role, status, chapel_id, created_at
                FROM users
                WHERE chapel_id = ?
                ORDER BY id DESC
            ");
            if (!$stmt) return [];
            $stmt->bind_param('i', $chapelFilter);
            $stmt->execute();
            $res = $stmt->get_result();
        } else {
            $res = $this->conn->query("
                SELECT id, user, full_name, email, phone, role, status, chapel_id, created_at
                FROM users
                ORDER BY id DESC
            ");
        }
        if ($res) while ($r = $res->fetch_assoc()) $users[] = $r;
        return $users;
    }

    public function approveUser(int $id): bool {
        $s = $this->prepareStmt("UPDATE users SET `status` = 'active' WHERE id = ?");
        if (!$s) return false;
        $s->bind_param('i', $id);
        $res = $s->execute();
        $s->close();
        return $res;
    }

    public function rejectUser(int $id): bool {
        $s = $this->prepareStmt("UPDATE users SET `status` = 'rejected' WHERE id = ?");
        if (!$s) return false;
        $s->bind_param('i', $id);
        $res = $s->execute();
        $s->close();
        return $res;
    }

    public function getPendingCount(?int $chapelId = null): int {
        if ($chapelId !== null) {
            $s = $this->prepareStmt("SELECT COUNT(*) as c FROM users WHERE `status` = 'pending' AND chapel_id = ?");
            if (!$s) return 0;
            $s->bind_param('i', $chapelId);
            $s->execute();
            $res = $s->get_result()->fetch_assoc();
            $s->close();
            return (int)($res['c'] ?? 0);
        }
        $res = $this->conn->query("SELECT COUNT(*) as c FROM users WHERE `status` = 'pending'");
        return $res ? (int)($res->fetch_assoc()['c'] ?? 0) : 0;
    }

    public function countAll(int|string|null $chapelId = null) {
        if ($chapelId !== null) {
            if ($chapelId === 'cathedral' || (is_numeric($chapelId) && (int)$chapelId === 0)) {
                $res = $this->conn->query('SELECT COUNT(*) as c FROM users WHERE (chapel_id IS NULL OR chapel_id = 0)');
                return $res ? ($res->fetch_assoc()['c'] ?? 0) : 0;
            }
            if ((int)$chapelId < 0) {
                return 0;
            }
            $cid = (int)$chapelId;
            $stmt = $this->prepareStmt('SELECT COUNT(*) as c FROM users WHERE chapel_id = ?');
            if (!$stmt) return 0;
            $stmt->bind_param('i', $cid);
            $stmt->execute();
            return $stmt->get_result()->fetch_assoc()['c'] ?? 0;
        }
        $res = $this->conn->query('SELECT COUNT(*) as c FROM users');
        return $res ? ($res->fetch_assoc()['c'] ?? 0) : 0;
    }

    public function getByRole($role) {
        $users = [];
        $s = $this->prepareStmt("SELECT id, user, full_name, email, role, chapel_coverage FROM users WHERE role = ?");
        if (!$s) return $users;
        $s->bind_param('s', $role);
        $s->execute();
        $res = $s->get_result();
        while ($row = $res->fetch_assoc()) $users[] = $row;
        $s->close();
        return $users;
    }

    public function getPriestPrefs($pastorId) {
        $prefs = [];
        $ptr = $this->prepareStmt("SELECT event_type FROM pastor_event_types WHERE pastor_id = ?");
        if (!$ptr) return $prefs;
        $ptr->bind_param('i', $pastorId);
        $ptr->execute();
        $res = $ptr->get_result();
        while ($row = $res->fetch_assoc()) $prefs[] = $row['event_type'];
        $ptr->close();
        return $prefs;
    }

    public function savePriestPrefs($pastorId, $eventTypes, $chapels = [], $dutyStartTime = null, $dutyEndTime = null) {
        $del = $this->prepareStmt("DELETE FROM pastor_event_types WHERE pastor_id = ?");
        if (!$del) return false;
        $del->bind_param('i', $pastorId);
        $del->execute();
        $del->close();

        if (!empty($eventTypes)) {
            $ins = $this->prepareStmt("INSERT INTO pastor_event_types (pastor_id, event_type) VALUES (?,?)");
            if (!$ins) return false;
            foreach ($eventTypes as $etype) {
                $ins->bind_param('is', $pastorId, $etype);
                $ins->execute();
            }
            $ins->close();
        }

        // Update chapel coverage and duty hours in users table
        $coverageStr = !empty($chapels) ? implode(',', $chapels) : null;
        $dStart = !empty($dutyStartTime) ? $dutyStartTime : '08:00:00';
        $dEnd   = !empty($dutyEndTime) ? $dutyEndTime : '17:00:00';

        $upd = $this->prepareStmt("UPDATE users SET chapel_coverage = ?, duty_start_time = ?, duty_end_time = ? WHERE id = ?");
        if (!$upd) return false;
        $upd->bind_param('sssi', $coverageStr, $dStart, $dEnd, $pastorId);
        $upd->execute();
        $upd->close();
        return true;
    }

    public function getPriestsWithPrefs(bool $activeOnly = true) {
        $priests = [];
        $activeSql = $activeOnly ? " AND LOWER(COALESCE(u.status, 'active')) = 'active'" : "";
        $pr = $this->conn->query("
            SELECT u.id, u.user, u.full_name, u.status, u.chapel_coverage, u.duty_start_time, u.duty_end_time,
                   GROUP_CONCAT(pet.event_type ORDER BY pet.event_type SEPARATOR ',') AS event_types
            FROM users u
            LEFT JOIN pastor_event_types pet ON pet.pastor_id = u.id
            WHERE u.role = 'Priest'{$activeSql}
            GROUP BY u.id, u.user, u.full_name, u.status, u.chapel_coverage, u.duty_start_time, u.duty_end_time
            ORDER BY u.user ASC
        ");
        if ($pr) while ($p = $pr->fetch_assoc()) $priests[] = $p;
        return $priests;
    }

    public function getPastorsWithPrefs() {
        return $this->getPriestsWithPrefs();
    }

    public function getPastorPrefs($pastorId) {
        return $this->getPriestPrefs($pastorId);
    }

    public function savePastorPrefs($pastorId, $eventTypes, $chapels = [], $dutyStartTime = null, $dutyEndTime = null) {
        return $this->savePriestPrefs($pastorId, $eventTypes, $chapels, $dutyStartTime, $dutyEndTime);
    }

    /**
     * Analytical Methods for Dashboard Insights
     */

    public function getRoleDistribution(?int $chapelId = null): array
    {
        $sql = "SELECT role, COUNT(*) as count FROM users";
        if ($chapelId) {
            $sql .= " WHERE chapel_id = " . (int)$chapelId;
        }
        $sql .= " GROUP BY role";
        
        $res = $this->conn->query($sql);
        $dist = [];
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $dist[$row['role']] = (int)$row['count'];
            }
        }
        return $dist;
    }

    public function getMemberStats(?int $chapelId = null): array
    {
        // Count active members, pending approvals, and staff/admins
        $sql = "SELECT 
                    COUNT(*) as total_users,
                    SUM(CASE WHEN role = 'Member' THEN 1 ELSE 0 END) as member_count,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count,
                    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_count,
                    SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive_count,
                    SUM(CASE WHEN role = 'Staff' THEN 1 ELSE 0 END) as staff_count,
                    SUM(CASE WHEN role IN ('Priest', 'Pastor') THEN 1 ELSE 0 END) as priest_count,
                    SUM(CASE WHEN role IN ('Priest', 'Pastor') THEN 1 ELSE 0 END) as pastor_count,
                    SUM(CASE WHEN role IN ('Admin', 'Cathedral Admin', 'Chapel Admin') THEN 1 ELSE 0 END) as admin_count,
                    SUM(CASE WHEN role = 'Member' AND (chapel_id IS NULL OR chapel_id = 0) THEN 1 ELSE 0 END) as unassigned_count
                FROM users";
        
        if ($chapelId !== null) {
            $sql .= " WHERE chapel_id = " . (int)$chapelId;
        }
        
        $res = $this->conn->query($sql);
        return $res ? $res->fetch_assoc() : [];
    }

    public function getGrowthFromLogs(?int $chapelId = null): array
    {
        $sql = "SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as count 
                FROM audit_logs 
                WHERE module = 'Users' AND action = 'created'";
        
        if ($chapelId) {
            $sql .= " AND chapel_id = " . (int)$chapelId;
        }
        
        $sql .= " GROUP BY month ORDER BY month ASC LIMIT 12";
        
        $res = $this->conn->query($sql);
        $growth = [];
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $growth[] = $row;
            }
        }
        return $growth;
    }

    public function getDetailedMemberInsights(?int $chapelId = null): array
    {
        $sql = "SELECT 
                    COUNT(*) as total,
                    SUM(profile_pic IS NOT NULL AND profile_pic != '') as has_pic,
                    SUM(email IS NOT NULL AND email != '') as has_email,
                    SUM(login_attempts > 0) as engaged_users
                FROM users 
                WHERE role = 'Member'";
        
        if ($chapelId) {
            $sql .= " AND chapel_id = " . (int)$chapelId;
        }
        
        $res = $this->conn->query($sql);
        $basic = $res ? $res->fetch_assoc() : [];

        // Additional deep metrics
        $deepSql = "SELECT 
                        SUM(requester_phone IS NOT NULL AND requester_phone != '') as has_phone,
                        COUNT(DISTINCT requested_by) as unique_requesters
                    FROM events 
                    WHERE requested_by IN (SELECT id FROM users WHERE role = 'Member'" . ($chapelId ? " AND chapel_id = " . (int)$chapelId : "") . ")";
        
        $deepRes = $this->conn->query($deepSql);
        $deep = $deepRes ? $deepRes->fetch_assoc() : [];
        
        $basic['has_phone'] = $deep['has_phone'] ?? 0;
        $basic['active_requesters'] = $deep['unique_requesters'] ?? 0;
        
        // Add activity hubs (most active chapels by member count)
        $basic['activity_hubs'] = $this->getMemberActivityHubs($chapelId);
        
        return $basic;
    }

    public function getMemberActivityHubs(?int $chapelId = null): array
    {
        $sql = "SELECT 
                    COALESCE(c.chapel_name, 'Main Cathedral') as hub_name,
                    COUNT(u.id) as member_count
                FROM users u
                LEFT JOIN chapels c ON u.chapel_id = c.chapel_id
                WHERE u.role = 'Member'";
        
        if ($chapelId) {
            $sql .= " AND u.chapel_id = " . (int)$chapelId;
        }

        $sql .= " GROUP BY hub_name
                ORDER BY member_count DESC
                LIMIT 5";
        
        $res = $this->conn->query($sql);
        $hubs = [];
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $hubs[] = $row;
            }
        }
        return $hubs;
    }
}
