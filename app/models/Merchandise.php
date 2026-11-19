<?php
class Merchandise {
    private $conn;
    private bool $supportsArchive;

    public function __construct($conn) {
        $this->conn = $conn;
        $this->supportsArchive = $this->columnExists('merchandise_items', 'is_archived');
    }

    public function supportsArchiveFeature(): bool {
        return $this->supportsArchive;
    }

    private function columnExists(string $table, string $column): bool {
        $stmt = $this->conn->prepare(
            "SELECT 1
             FROM information_schema.columns
             WHERE table_schema = DATABASE()
               AND table_name = ?
               AND column_name = ?
             LIMIT 1"
        );
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param("ss", $table, $column);
        $stmt->execute();
        $exists = (bool)$stmt->get_result()->fetch_row();
        $stmt->close();
        return $exists;
    }

    public function getAllItems($includeArchived = false) {
        $sql = "SELECT * FROM merchandise_items";
        if ($this->supportsArchive && !$includeArchived) {
            $sql .= " WHERE is_archived = 0";
        }
        $sql .= " ORDER BY name ASC";
        $result = $this->conn->query($sql);
        if (!$result) {
            return [];
        }
        $items = [];
        while ($row = $result->fetch_assoc()) {
            $items[] = $row;
        }
        return $items;
    }

    public function getAll($includeArchived = false) {
        return $this->getAllItems($includeArchived);
    }

    public function getItem($id) {
        $stmt = $this->conn->prepare("SELECT * FROM merchandise_items WHERE id = ?");
        if (!$stmt) return null;
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $item = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $item;
    }

    public function getById($id) {
        return $this->getItem($id);
    }

    public function addItem($name, $desc, $price, $stock, $photo_path = null) {
        if ($this->supportsArchive) {
            $stmt = $this->conn->prepare("INSERT INTO merchandise_items (name, description, price, stock_quantity, photo_path, is_archived) VALUES (?, ?, ?, ?, ?, 0)");
            $stmt->bind_param("ssdis", $name, $desc, $price, $stock, $photo_path);
        } else {
            $stmt = $this->conn->prepare("INSERT INTO merchandise_items (name, description, price, stock_quantity, photo_path) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("ssdis", $name, $desc, $price, $stock, $photo_path);
        }
        $res = $stmt->execute();
        $stmt->close();
        return $res;
    }

    public function updateItem($id, $name, $desc, $price, $stock, $photo_path = null) {
        if ($photo_path) {
            $stmt = $this->conn->prepare("UPDATE merchandise_items SET name=?, description=?, price=?, stock_quantity=?, photo_path=? WHERE id=?");
            $stmt->bind_param("ssdisi", $name, $desc, $price, $stock, $photo_path, $id);
        } else {
            $stmt = $this->conn->prepare("UPDATE merchandise_items SET name=?, description=?, price=?, stock_quantity=? WHERE id=?");
            $stmt->bind_param("ssdii", $name, $desc, $price, $stock, $id);
        }
        $res = $stmt->execute();
        $stmt->close();
        return $res;
    }

    public function deleteItem($id) {
        return $this->archiveItem($id);
    }

    public function archiveItem($id) {
        if ($this->supportsArchive) {
            $stmt = $this->conn->prepare("UPDATE merchandise_items SET is_archived = 1 WHERE id=?");
            if (!$stmt) {
                return false;
            }
            $stmt->bind_param("i", $id);
            $res = $stmt->execute();
            $stmt->close();
            return $res;
        }

        if ($this->hasSales($id)) {
            return false;
        }

        $stmt = $this->conn->prepare("DELETE FROM merchandise_items WHERE id=?");
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param("i", $id);
        $res = $stmt->execute();
        $stmt->close();
        return $res;
    }

    public function restoreItem($id) {
        if (!$this->supportsArchive) {
            return false;
        }
        $stmt = $this->conn->prepare("UPDATE merchandise_items SET is_archived = 0 WHERE id=?");
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param("i", $id);
        $res = $stmt->execute();
        $stmt->close();
        return $res;
    }

    public function getArchivedItems() {
        if (!$this->supportsArchive) {
            return [];
        }
        $stmt = $this->conn->prepare("SELECT * FROM merchandise_items WHERE is_archived = 1 ORDER BY name ASC");
        if (!$stmt) {
            return [];
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $items = [];
        while ($row = $result->fetch_assoc()) {
            $items[] = $row;
        }
        $stmt->close();
        return $items;
    }

    public function hasSales($item_id) {
        $stmt = $this->conn->prepare("SELECT COUNT(*) AS c FROM merchandise_sales WHERE item_id = ?");
        if (!$stmt) {
            return true;
        }
        $stmt->bind_param("i", $item_id);
        $stmt->execute();
        $count = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
        $stmt->close();
        return $count > 0;
    }

    public function getSalesPaginated(int $page = 1, int $perPage = 20): array {
        $res = $this->conn->query("SELECT COUNT(*) as cnt FROM merchandise_sales");
        $total = $res ? (int)($res->fetch_assoc()['cnt'] ?? 0) : 0;
        $pages = (int) ceil($total / $perPage);
        $page = max(1, min($page, $pages ?: 1));
        $offset = ($page - 1) * $perPage;

        $records = $this->getSales($perPage, $offset);

        return [
            'records' => $records,
            'total'   => $total,
            'pages'   => $pages,
            'page'    => $page
        ];
    }

    public function getSales($limit = 50, $offset = 0) {
        $sql = "SELECT s.*, i.name as item_name, u.full_name as processor_name 
                FROM merchandise_sales s 
                LEFT JOIN merchandise_items i ON s.item_id = i.id 
                LEFT JOIN users u ON s.processed_by = u.id 
                ORDER BY s.sale_date DESC LIMIT ? OFFSET ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("ii", $limit, $offset);
        $stmt->execute();
        $result = $stmt->get_result();
        $sales = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $sales[] = $row;
            }
        }
        $stmt->close();
        return $sales;
    }

    public function getSaleById($id) {
        $stmt = $this->conn->prepare("
            SELECT s.*, m.name as item_name, m.price as unit_price, u.user as sold_by_name
            FROM merchandise_sales s
            JOIN merchandise_items m ON s.item_id = m.id
            LEFT JOIN users u ON s.processed_by = u.id
            WHERE s.id = ?
        ");
        if (!$stmt) return null;
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $sale = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $sale ?: null;
    }

    public function recordSale($item_id, $buyer_name, $quantity, $user_id) {
        $item_id = (int)$item_id;
        $quantity = (int)$quantity;
        $buyer_name = trim((string)$buyer_name);

        if ($item_id <= 0) {
            return "Invalid merchandise item.";
        }
        if ($quantity <= 0) {
            return "Quantity must be at least 1.";
        }

        $this->conn->begin_transaction();
        try {
            // Get item price and check stock
            $stmt = $this->conn->prepare("SELECT price, stock_quantity FROM merchandise_items WHERE id = ? FOR UPDATE");
            $stmt->bind_param("i", $item_id);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res->num_rows === 0) throw new Exception("Item not found");
            $item = $res->fetch_assoc();
            $stmt->close();

            if ($item['stock_quantity'] < $quantity) {
                throw new Exception("Not enough stock available");
            }

            $total_price = $item['price'] * $quantity;

            // Update stock
            $stmt = $this->conn->prepare("UPDATE merchandise_items SET stock_quantity = stock_quantity - ? WHERE id = ?");
            $stmt->bind_param("ii", $quantity, $item_id);
            $stmt->execute();
            $stmt->close();

            // Record sale
            $procBy = ((int)$user_id > 0) ? (int)$user_id : null;
            $stmt = $this->conn->prepare("INSERT INTO merchandise_sales (item_id, buyer_name, quantity, total_price, processed_by) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("isidi", $item_id, $buyer_name, $quantity, $total_price, $procBy);
            $stmt->execute();
            $stmt->close();

            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            $this->conn->rollback();
            return $e->getMessage();
        }
    }
}
?>
