<?php
class Bulletin {
    private $conn;
    private array $tableExistsCache = [];

    public function __construct($conn) {
        $this->conn = $conn;
    }

    public function getById(int $id): ?array {
        $stmt = $this->conn->prepare("SELECT * FROM bulletin_posts WHERE post_id = ? LIMIT 1");
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();
        return $row ?: null;
    }

    public function create($title, $subtitle, $color, $createdBy, $chapelId = null) {
        $maxRes = $this->conn->query("SELECT COALESCE(MAX(sort_order),0) as m FROM bulletin_posts");
        if (!$maxRes) {
            return false;
        }
        $max = $maxRes->fetch_assoc()['m'];
        $nextOrder = intval($max) + 1;

        // Human Error: Cap lengths to prevent UI breaking
        $title = mb_strimwidth(trim($title), 0, 100, "...");
        $subtitle = mb_strimwidth(trim($subtitle), 0, 255, "...");
        $chapelId = (!empty($chapelId) && (int)$chapelId > 0) ? (int)$chapelId : null;
        $createdBy = (!empty($createdBy) && (int)$createdBy > 0) ? (int)$createdBy : null;

        $s = $this->conn->prepare("INSERT INTO bulletin_posts (title,subtitle,dot_color,chapel_id,sort_order,is_active,created_by) VALUES (?,?,?,?,?,1,?)");
        if (!$s) {
            return false;
        }
        $s->bind_param('sssiii', $title, $subtitle, $color, $chapelId, $nextOrder, $createdBy);
        $res = $s->execute();
        $s->close();
        return $res;
    }

    public function update($id, $title, $subtitle, $color, $chapelId = null) {
        // Human Error: Cap lengths to prevent UI breaking
        $title = mb_strimwidth(trim($title), 0, 100, "...");
        $subtitle = mb_strimwidth(trim($subtitle), 0, 255, "...");
        $chapelId = (!empty($chapelId) && (int)$chapelId > 0) ? (int)$chapelId : null;

        $s = $this->conn->prepare("UPDATE bulletin_posts SET title=?,subtitle=?,dot_color=?,chapel_id=? WHERE post_id=?");
        if (!$s) {
            return false;
        }
        $s->bind_param('sssii', $title, $subtitle, $color, $chapelId, $id);
        $res = $s->execute();
        $s->close();
        return $res;
    }

    public function toggle($id) {
        $s = $this->conn->prepare("UPDATE bulletin_posts SET is_active = 1 - is_active WHERE post_id=?");
        if (!$s) {
            return false;
        }
        $s->bind_param('i', $id);
        $res = $s->execute();
        $s->close();
        return $res;
    }

    public function delete($id) {
        $d = $this->conn->prepare("DELETE FROM bulletin_posts WHERE post_id=?");
        if (!$d) {
            return false;
        }
        $d->bind_param('i', $id);
        $res = $d->execute();
        $d->close();
        return $res;
    }

    public function reorder($id, $direction) {
        $cur = $this->conn->prepare("SELECT sort_order FROM bulletin_posts WHERE post_id=?");
        if (!$cur) {
            return false;
        }
        $cur->bind_param('i', $id); $cur->execute();
        $cur_order = $cur->get_result()->fetch_assoc()['sort_order'] ?? 0;
        $cur->close();

        if ($direction === 'up') {
            $adj = $this->conn->prepare("SELECT post_id, sort_order FROM bulletin_posts WHERE sort_order < ? ORDER BY sort_order DESC LIMIT 1");
        } else {
            $adj = $this->conn->prepare("SELECT post_id, sort_order FROM bulletin_posts WHERE sort_order > ? ORDER BY sort_order ASC LIMIT 1");
        }
        if (!$adj) {
            return false;
        }
        $adj->bind_param('i', $cur_order); $adj->execute();
        $adj_row = $adj->get_result()->fetch_assoc();
        $adj->close();

        if ($adj_row) {
            $adj_sort = intval($adj_row['sort_order']);
            $adj_id   = intval($adj_row['post_id']);
            $s1 = $this->conn->prepare("UPDATE bulletin_posts SET sort_order=? WHERE post_id=?");
            if (!$s1) return false;
            $s1->bind_param('ii', $adj_sort, $id); $s1->execute(); $s1->close();
            $s2 = $this->conn->prepare("UPDATE bulletin_posts SET sort_order=? WHERE post_id=?");
            if (!$s2) return false;
            $s2->bind_param('ii', $cur_order, $adj_id); $s2->execute(); $s2->close();
            return true;
        }
        return false;
    }

    public function getAll(?int $chapelId = null) {
        $posts = [];
        if ($this->tableExists('bulletin_posts')) {
            $sql = "SELECT bp.*, c.chapel_name, c.theme_color AS chapel_color 
                    FROM bulletin_posts bp 
                    LEFT JOIN chapels c ON bp.chapel_id = c.chapel_id";
            if ($chapelId !== null) {
                if ($chapelId === 0) {
                    $sql .= " WHERE bp.chapel_id IS NULL";
                } else {
                    $sql .= " WHERE bp.chapel_id = ?";
                }
            }
            $sql .= " ORDER BY bp.sort_order ASC";

            if ($chapelId !== null && $chapelId > 0) {
                $stmt = $this->conn->prepare($sql);
                if ($stmt) {
                    $stmt->bind_param('i', $chapelId);
                    $stmt->execute();
                    $res = $stmt->get_result();
                    if ($res) while ($row = $res->fetch_assoc()) $posts[] = $row;
                    $stmt->close();
                }
            } else {
                $bp_res = $this->conn->query($sql);
                if ($bp_res) while ($row = $bp_res->fetch_assoc()) $posts[] = $row;
            }
        }
        return $posts;
    }

    public function getActive(?int $chapelId = null) {
        $posts = [];
        if ($this->tableExists('bulletin_posts')) {
            $sql = "SELECT bp.*, c.chapel_name, c.theme_color AS chapel_color 
                    FROM bulletin_posts bp 
                    LEFT JOIN chapels c ON bp.chapel_id = c.chapel_id 
                    WHERE bp.is_active = 1";
            if ($chapelId !== null) {
                if ($chapelId === 0) {
                    $sql .= " AND bp.chapel_id IS NULL";
                } else {
                    $sql .= " AND (bp.chapel_id = ? OR bp.chapel_id IS NULL)";
                }
            }
            $sql .= " ORDER BY bp.sort_order ASC";

            if ($chapelId !== null && $chapelId > 0) {
                $stmt = $this->conn->prepare($sql);
                if ($stmt) {
                    $stmt->bind_param('i', $chapelId);
                    $stmt->execute();
                    $res = $stmt->get_result();
                    if ($res) while ($row = $res->fetch_assoc()) $posts[] = $row;
                    $stmt->close();
                }
            } else {
                $bp_res = $this->conn->query($sql);
                if ($bp_res) while ($row = $bp_res->fetch_assoc()) $posts[] = $row;
            }
        }
        return $posts;
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

        $stmt->bind_param('s', $table);
        $stmt->execute();
        $res = $stmt->get_result();
        $this->tableExistsCache[$table] = (bool) ($res && $res->num_rows > 0);
        $stmt->close();

        return $this->tableExistsCache[$table];
    }
}
