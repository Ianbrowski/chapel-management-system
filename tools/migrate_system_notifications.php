<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This maintenance script may only be run from CLI.');
}
require_once __DIR__ . '/../config/config.php';

echo "Running system_notifications migration..." . PHP_EOL;

$sql = "CREATE TABLE IF NOT EXISTS system_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    type VARCHAR(50) NOT NULL DEFAULT 'event',
    reference_id INT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_read (user_id, is_read),
    INDEX idx_user_created (user_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

if (!$conn->query($sql)) {
    die("Error creating table: " . $conn->error . PHP_EOL);
}
echo "Table system_notifications ensured." . PHP_EOL;

// Check existing notifications count
$count = $conn->query("SELECT COUNT(*) AS c FROM system_notifications")->fetch_assoc()['c'];
if ((int)$count === 0) {
    // Find member user IDs
    $memberRes = $conn->query("SELECT id, user FROM users WHERE role = 'Member'");
    $stmt = $conn->prepare("INSERT INTO system_notifications (user_id, title, message, type, is_read, created_at) VALUES (?, ?, ?, ?, 0, NOW())");
    
    $sampleTitle = "Holy Baptism Schedule Confirmed";
    $sampleMsg = "Grace and blessings. Your liturgical baptism request at the Cathedral of St. Michael the Archangel Sanctuary has been approved and scheduled for Sunday liturgy. Presiding: Rev. Cathedral Clergy.";
    $sampleType = "sacrament";

    while ($m = $memberRes->fetch_assoc()) {
        $uid = (int)$m['id'];
        $stmt->bind_param("isss", $uid, $sampleTitle, $sampleMsg, $sampleType);
        $stmt->execute();
        echo "Seeded realistic sample baptism notification for member {$m['user']} (ID {$uid})." . PHP_EOL;
    }
    $stmt->close();
} else {
    echo "system_notifications already contains {$count} records." . PHP_EOL;
}

echo "Migration finished successfully." . PHP_EOL;
