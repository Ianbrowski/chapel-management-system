<?php
declare(strict_types=1);

/**
 * Idempotent Database Integrity Migration:
 * Introduces safe secondary/unique indexes and InnoDB foreign keys
 * verified against application code paths and live data.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Forbidden: Database migration tool must be executed via CLI.');
}

require_once __DIR__ . '/../config/config.php';

$dryRun = in_array('--dry-run', $argv ?? [], true);
$safeBoot = in_array('--safe-boot', $argv ?? [], true);
echo "=== CATHEDRAL DB INTEGRITY MIGRATION (" . ($dryRun ? 'DRY-RUN' : ($safeBoot ? 'SAFE-BOOT' : 'APPLY')) . ") ===\n";

if (!isset($conn) || !($conn instanceof mysqli) || $conn->connect_error) {
    echo "[SKIP] Database connection unavailable.\n";
    exit($safeBoot ? 0 : 1);
}

// Batch-fetch metadata in 3 queries (avoids hundreds of roundtrips over remote TLS)
$existingIndexes = [];
$rIdx = $conn->query("SELECT TABLE_NAME, INDEX_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE()");
if ($rIdx) {
    while ($row = $rIdx->fetch_assoc()) {
        $existingIndexes[$row['TABLE_NAME'] . '.' . $row['INDEX_NAME']] = true;
    }
    $rIdx->free();
}

$existingColumns = [];
$rCols = $conn->query("SELECT TABLE_NAME, COLUMN_NAME, IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()");
if ($rCols) {
    while ($row = $rCols->fetch_assoc()) {
        $existingColumns[$row['TABLE_NAME'] . '.' . $row['COLUMN_NAME']] = (strtoupper((string)$row['IS_NULLABLE']) === 'YES');
    }
    $rCols->free();
}

$existingFks = [];
$rFks = $conn->query("SELECT TABLE_NAME, CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_TYPE = 'FOREIGN KEY'");
if ($rFks) {
    while ($row = $rFks->fetch_assoc()) {
        $existingFks[$row['TABLE_NAME'] . '.' . $row['CONSTRAINT_NAME']] = true;
    }
    $rFks->free();
}

function hasIndex(mysqli $conn, string $table, string $indexName): bool
{
    global $existingIndexes;
    return isset($existingIndexes[$table . '.' . $indexName]);
}

function hasForeignKey(mysqli $conn, string $table, string $fkName): bool
{
    global $existingFks;
    return isset($existingFks[$table . '.' . $fkName]);
}

function columnExists(mysqli $conn, string $table, string $column): bool
{
    global $existingColumns;
    return array_key_exists($table . '.' . $column, $existingColumns);
}

function isColumnNullable(mysqli $conn, string $table, string $column): bool
{
    global $existingColumns;
    return !empty($existingColumns[$table . '.' . $column]);
}

// Step 0: Normalize empty reference_id in sacramental_records to NULL for UNIQUE index safety
$resEmptyRef = $conn->query("SELECT COUNT(*) c FROM sacramental_records WHERE reference_id = ''");
$emptyRefCnt = $resEmptyRef ? (int)$resEmptyRef->fetch_assoc()['c'] : 0;
if ($emptyRefCnt > 0) {
    echo "[PRE] Normalizing {$emptyRefCnt} empty sacramental_records.reference_id -> NULL\n";
    if (!$dryRun) {
        $conn->query("UPDATE sacramental_records SET reference_id = NULL WHERE reference_id = ''");
    }
}

// Step 1: Secondary & Unique Indexes
$indexes = [
    // Users
    ['users', 'uq_users_user', 'UNIQUE INDEX `uq_users_user` (`user`)'],
    ['users', 'idx_users_email', 'INDEX `idx_users_email` (`email`)'],
    ['users', 'idx_users_role_status', 'INDEX `idx_users_role_status` (`role`, `status`)'],
    ['users', 'idx_users_chapel', 'INDEX `idx_users_chapel` (`chapel_id`)'],

    // Chapels & Clergy Preferences
    ['chapel_fiestas', 'idx_chapel_fiestas_chapel', 'INDEX `idx_chapel_fiestas_chapel` (`chapel_id`)'],
    ['pastor_event_types', 'idx_pastor_types_pastor', 'INDEX `idx_pastor_types_pastor` (`pastor_id`)'],

    // Events (Conflict detection, Calendar, Analytics, FKs)
    ['events', 'idx_events_conflict_venue', 'INDEX `idx_events_conflict_venue` (`chapel_id`, `start_time`, `status`, `is_archived`)'],
    ['events', 'idx_events_conflict_pastor', 'INDEX `idx_events_conflict_pastor` (`pastor_id`, `start_time`, `status`, `is_archived`)'],
    ['events', 'idx_events_requested_by', 'INDEX `idx_events_requested_by` (`requested_by`, `is_archived`)'],
    ['events', 'idx_events_service_variant', 'INDEX `idx_events_service_variant` (`service_id`, `variant_id`)'],
    ['events', 'idx_events_variant', 'INDEX `idx_events_variant` (`variant_id`)'],

    // Services Catalog & Request Addons
    ['services', 'idx_services_category', 'INDEX `idx_services_category` (`category_id`, `status`)'],
    ['service_variants', 'idx_service_variants_service', 'INDEX `idx_service_variants_service` (`service_id`, `status`, `is_current`)'],
    ['service_additional_charges', 'idx_service_charges_service', 'INDEX `idx_service_charges_service` (`service_id`, `status`)'],
    ['service_requirements', 'idx_service_reqs_service', 'INDEX `idx_service_reqs_service` (`service_id`, `status`)'],
    ['service_schedules', 'idx_service_sched_service', 'INDEX `idx_service_sched_service` (`service_id`, `status`)'],
    ['service_request_addons', 'idx_req_addons_event', 'INDEX `idx_req_addons_event` (`event_id`)'],
    ['service_request_addons', 'idx_req_addons_addon', 'INDEX `idx_req_addons_addon` (`addon_id`)'],

    // Sacramental Records & Certificates
    ['sacramental_records', 'uq_sacramental_reference_id', 'UNIQUE INDEX `uq_sacramental_reference_id` (`reference_id`)'],
    ['sacramental_records', 'idx_sacramental_search', 'INDEX `idx_sacramental_search` (`is_archived`, `chapel_id`, `record_type`, `event_date`)'],
    ['sacramental_records', 'idx_sacramental_dup', 'INDEX `idx_sacramental_dup` (`is_archived`, `record_type`, `child_name`, `event_date`)'],
    ['sacramental_records', 'idx_sacramental_encoded_by', 'INDEX `idx_sacramental_encoded_by` (`encoded_by`)'],
    ['sacramental_records', 'idx_sacramental_updated_by', 'INDEX `idx_sacramental_updated_by` (`updated_by`)'],
    ['certificate_issuances', 'uq_cert_issuances_no', 'UNIQUE INDEX `uq_cert_issuances_no` (`certificate_no`)'],
    ['certificate_issuances', 'idx_cert_issuances_rec_time', 'INDEX `idx_cert_issuances_rec_time` (`record_id`, `issued_at`)'],
    ['certificate_issuances', 'idx_cert_issuances_issued_by', 'INDEX `idx_cert_issuances_issued_by` (`issued_by`)'],
    ['sacramental_audit_log', 'idx_sac_audit_rec', 'INDEX `idx_sac_audit_rec` (`record_id`, `audit_id`)'],
    ['sacramental_audit_log', 'idx_sac_audit_performed_by', 'INDEX `idx_sac_audit_performed_by` (`performed_by`)'],

    // Donations & Merchandise
    ['donations', 'idx_donations_reference_id', 'INDEX `idx_donations_reference_id` (`reference_id`, `is_archived`, `voided_at`)'],
    ['donations', 'idx_donations_chapel_date', 'INDEX `idx_donations_chapel_date` (`chapel_id`, `is_archived`, `date_received`)'],
    ['donations', 'idx_donations_logged_by', 'INDEX `idx_donations_logged_by` (`logged_by`)'],
    ['donations', 'idx_donations_voided_by', 'INDEX `idx_donations_voided_by` (`voided_by`)'],
    ['merchandise_sales', 'idx_merch_sales_item_date', 'INDEX `idx_merch_sales_item_date` (`item_id`, `sale_date`)'],
    ['merchandise_sales', 'idx_merch_sales_processed_by', 'INDEX `idx_merch_sales_processed_by` (`processed_by`)'],

    // Inquiries, Bulletin, Notifications & Chat
    ['public_inquiries', 'idx_inquiries_chapel_arch_status', 'INDEX `idx_inquiries_chapel_arch_status` (`chapel_id`, `is_archived`, `status`, `created_at`)'],
    ['public_inquiries', 'idx_inquiries_email', 'INDEX `idx_inquiries_email` (`email`)'],
    ['public_inquiries', 'idx_inquiries_item', 'INDEX `idx_inquiries_item` (`item_id`)'],
    ['bulletin_posts', 'idx_bulletin_active_order', 'INDEX `idx_bulletin_active_order` (`is_active`, `chapel_id`, `sort_order`)'],
    ['bulletin_posts', 'idx_bulletin_chapel', 'INDEX `idx_bulletin_chapel` (`chapel_id`)'],
    ['bulletin_posts', 'idx_bulletin_created_by', 'INDEX `idx_bulletin_created_by` (`created_by`)'],
    ['system_notifications', 'idx_sys_notif_user_read', 'INDEX `idx_sys_notif_user_read` (`user_id`, `is_read`, `created_at`)'],
    ['parish_chat_participants', 'idx_chat_part_user', 'INDEX `idx_chat_part_user` (`user_id`)'],
    ['parish_chat_messages', 'idx_chat_msg_room_id', 'INDEX `idx_chat_msg_room_id` (`room_id`, `message_id`)'],
    ['parish_chat_messages', 'idx_chat_msg_sender', 'INDEX `idx_chat_msg_sender` (`sender_id`)'],
];

$idxAdded = 0;
$idxSkipped = 0;
$idxFailed = 0;

foreach ($indexes as [$table, $name, $clause]) {
    if (hasIndex($conn, $table, $name)) {
        $idxSkipped++;
        continue;
    }
    $sql = "ALTER TABLE `{$table}` ADD {$clause}";
    if ($dryRun) {
        echo "[DRY] {$sql}\n";
        $idxAdded++;
        continue;
    }
    try {
        if ($conn->query($sql)) {
            echo "[IDX OK] {$table}.{$name}\n";
            $idxAdded++;
        } else {
            echo "[IDX ERR] {$table}.{$name}: {$conn->error}\n";
            $idxFailed++;
        }
    } catch (Throwable $e) {
        echo "[IDX ERR] {$table}.{$name}: {$e->getMessage()}\n";
        $idxFailed++;
    }
}

// Step 2: InnoDB Foreign Key Constraints (35 verified orphan-free relationships)
$foreignKeys = [
    // Chapels hierarchy
    ['chapel_fiestas', 'fk_chapel_fiestas_chapel', 'chapel_id', 'chapels', 'chapel_id', 'CASCADE', 'CASCADE'],
    ['users', 'fk_users_chapel', 'chapel_id', 'chapels', 'chapel_id', 'RESTRICT', 'CASCADE'],
    ['events', 'fk_events_chapel', 'chapel_id', 'chapels', 'chapel_id', 'RESTRICT', 'CASCADE'],
    ['sacramental_records', 'fk_sacramental_chapel', 'chapel_id', 'chapels', 'chapel_id', 'RESTRICT', 'CASCADE'],
    ['donations', 'fk_donations_chapel', 'chapel_id', 'chapels', 'chapel_id', 'RESTRICT', 'CASCADE'],
    ['public_inquiries', 'fk_inquiries_chapel', 'chapel_id', 'chapels', 'chapel_id', 'RESTRICT', 'CASCADE'],
    ['bulletin_posts', 'fk_bulletin_chapel', 'chapel_id', 'chapels', 'chapel_id', 'SET NULL', 'CASCADE'],

    // Service catalog & request add-ons
    ['services', 'fk_services_category', 'category_id', 'service_categories', 'id', 'RESTRICT', 'CASCADE'],
    ['service_variants', 'fk_variants_service', 'service_id', 'services', 'id', 'CASCADE', 'CASCADE'],
    ['service_additional_charges', 'fk_charges_service', 'service_id', 'services', 'id', 'CASCADE', 'CASCADE'],
    ['service_requirements', 'fk_requirements_service', 'service_id', 'services', 'id', 'CASCADE', 'CASCADE'],
    ['service_schedules', 'fk_srv_sched_service', 'service_id', 'services', 'id', 'CASCADE', 'CASCADE'],
    ['service_request_addons', 'fk_req_addons_event', 'event_id', 'events', 'event_id', 'CASCADE', 'CASCADE'],
    ['service_request_addons', 'fk_req_addons_charge', 'addon_id', 'service_additional_charges', 'id', 'RESTRICT', 'CASCADE'],

    // Events & Clergy
    ['pastor_event_types', 'fk_pastor_types_user', 'pastor_id', 'users', 'id', 'CASCADE', 'CASCADE'],
    ['events', 'fk_events_pastor', 'pastor_id', 'users', 'id', 'RESTRICT', 'CASCADE'],
    ['events', 'fk_events_requested_by', 'requested_by', 'users', 'id', 'RESTRICT', 'CASCADE'],
    ['events', 'fk_events_service', 'service_id', 'services', 'id', 'SET NULL', 'CASCADE'],
    ['events', 'fk_events_variant', 'variant_id', 'service_variants', 'id', 'SET NULL', 'CASCADE'],

    // Sacramental records & certificates
    ['sacramental_records', 'fk_sacramental_encoded_by', 'encoded_by', 'users', 'id', 'RESTRICT', 'CASCADE'],
    ['sacramental_records', 'fk_sacramental_updated_by', 'updated_by', 'users', 'id', 'SET NULL', 'CASCADE'],
    ['certificate_issuances', 'fk_cert_issuances_record', 'record_id', 'sacramental_records', 'record_id', 'RESTRICT', 'CASCADE'],
    ['certificate_issuances', 'fk_cert_issuances_issued_by', 'issued_by', 'users', 'id', 'SET NULL', 'CASCADE'],
    ['sacramental_audit_log', 'fk_sac_audit_performed_by', 'performed_by', 'users', 'id', 'SET NULL', 'CASCADE'],

    // Donations & Merchandise
    ['donations', 'fk_donations_logged_by', 'logged_by', 'users', 'id', 'RESTRICT', 'CASCADE'],
    ['donations', 'fk_donations_voided_by', 'voided_by', 'users', 'id', 'SET NULL', 'CASCADE'],
    ['merchandise_sales', 'fk_merch_sales_item', 'item_id', 'merchandise_items', 'id', 'RESTRICT', 'CASCADE'],
    ['merchandise_sales', 'fk_merch_sales_proc_by', 'processed_by', 'users', 'id', 'SET NULL', 'CASCADE'],

    // Inquiries, Bulletin, Notifications & Chat
    ['public_inquiries', 'fk_inquiries_item', 'item_id', 'merchandise_items', 'id', 'SET NULL', 'CASCADE'],
    ['bulletin_posts', 'fk_bulletin_created_by', 'created_by', 'users', 'id', 'SET NULL', 'CASCADE'],
    ['system_notifications', 'fk_sys_notif_user', 'user_id', 'users', 'id', 'CASCADE', 'CASCADE'],
    ['parish_chat_participants', 'fk_chat_part_room', 'room_id', 'parish_chat_rooms', 'room_id', 'CASCADE', 'CASCADE'],
    ['parish_chat_participants', 'fk_chat_part_user', 'user_id', 'users', 'id', 'CASCADE', 'CASCADE'],
    ['parish_chat_messages', 'fk_chat_msg_room', 'room_id', 'parish_chat_rooms', 'room_id', 'CASCADE', 'CASCADE'],
    ['parish_chat_messages', 'fk_chat_msg_sender', 'sender_id', 'users', 'id', 'RESTRICT', 'CASCADE'],
];

$fkAdded = 0;
$fkSkipped = 0;
$fkFailed = 0;

foreach ($foreignKeys as [$table, $name, $col, $refTable, $refCol, $onDelete, $onUpdate]) {
    if (!columnExists($conn, $table, $col) || !columnExists($conn, $refTable, $refCol)) {
        $fkSkipped++;
        continue;
    }
    if (hasForeignKey($conn, $table, $name)) {
        $fkSkipped++;
        continue;
    }

    // Safely normalize legacy zero IDs (e.g. chapel_id = 0) to NULL when nullable
    if (isColumnNullable($conn, $table, $col)) {
        $zRes = $conn->query("SELECT COUNT(*) c FROM `{$table}` WHERE `{$col}` = 0");
        $zCnt = $zRes ? (int)$zRes->fetch_assoc()['c'] : 0;
        if ($zCnt > 0) {
            echo "[PRE] Normalizing {$zCnt} zero values in {$table}.{$col} -> NULL\n";
            if (!$dryRun) {
                $conn->query("UPDATE `{$table}` SET `{$col}` = NULL WHERE `{$col}` = 0");
            }
        }
    }

    // Safety pre-flight check before adding FK
    $orphanQ = $conn->query(
        "SELECT COUNT(*) c FROM `{$table}` c LEFT JOIN `{$refTable}` p ON c.`{$col}` = p.`{$refCol}`
         WHERE c.`{$col}` IS NOT NULL AND p.`{$refCol}` IS NULL"
    );
    $orphanCnt = $orphanQ ? (int)$orphanQ->fetch_assoc()['c'] : -1;
    if ($orphanCnt !== 0) {
        echo "[FK BLOCKED] {$table}.{$name}: orphan/invalid count = {$orphanCnt}\n";
        $fkFailed++;
        continue;
    }

    $sql = "ALTER TABLE `{$table}` ADD CONSTRAINT `{$name}` FOREIGN KEY (`{$col}`) REFERENCES `{$refTable}` (`{$refCol}`) ON DELETE {$onDelete} ON UPDATE {$onUpdate}";
    if ($dryRun) {
        echo "[DRY] {$sql}\n";
        $fkAdded++;
        continue;
    }
    try {
        if ($conn->query($sql)) {
            echo "[FK OK] {$table}.{$name} ({$col} -> {$refTable}.{$refCol})\n";
            $fkAdded++;
        } else {
            echo "[FK ERR] {$table}.{$name}: {$conn->error}\n";
            $fkFailed++;
        }
    } catch (Throwable $e) {
        echo "[FK ERR] {$table}.{$name}: {$e->getMessage()}\n";
        $fkFailed++;
    }
}

echo "=== SUMMARY ===\n";
echo "INDEXES: added={$idxAdded} skipped={$idxSkipped} failed={$idxFailed}\n";
echo "FOREIGN_KEYS: added={$fkAdded} skipped={$fkSkipped} failed={$fkFailed}\n";

if ($safeBoot) {
    exit(0);
}
exit(($idxFailed + $fkFailed) > 0 ? 1 : 0);
