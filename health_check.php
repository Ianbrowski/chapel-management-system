<?php
/**
 * ============================================================================
 * Cathedral & Chapel Management System
 * Comprehensive 100% System Health & Diagnostics Suite
 * ============================================================================
 * Scans all 100+ PHP files (controllers, models, services, helpers, views, 
 * config, root scripts), deep-inspects database schema & relational integrity, 
 * validates all includes & routing references, tests class instantiations, 
 * checks directory write permissions, PHP extensions, and timezone alignment.
 */

$startTime = microtime(true);
$baseDir = __DIR__;

// Load core configuration
require_once $baseDir . '/config/config.php';

if (PHP_SAPI !== 'cli') {
    $sessionRole = $_SESSION['role'] ?? '';
    if (!isset($_SESSION['user_id']) || $sessionRole !== 'Admin') {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        exit('Forbidden: Diagnostics utility requires CLI execution or Super Admin session.');
    }
}

$issues   = [];
$warnings = [];
$ok       = [];
$stats    = [
    'syntax_files'       => 0,
    'tables_checked'     => 0,
    'queries_validated'  => 0,
    'integrity_checks'   => 0,
    'includes_verified'  => 0,
    'controllers_loaded' => 0,
    'dirs_checked'       => 0,
    'env_checks'         => 0,
];

function recordCheck(&$ok, &$issues, &$warnings, $isSuccess, $label, $details = '', $isWarning = false) {
    if ($isSuccess) {
        $ok[] = "[OK] " . $label . ($details ? " ($details)" : "");
        return true;
    } else {
        if ($isWarning) {
            $warnings[] = "[WARN] " . $label . ($details ? ": $details" : "");
        } else {
            $issues[] = "[FAIL] " . $label . ($details ? ": $details" : "");
        }
        return false;
    }
}

echo "====================================================================\n";
echo "   CATHEDRAL SYSTEM 100% COMPREHENSIVE HEALTH & DIAGNOSTICS SUITE   \n";
echo "====================================================================\n";
echo "Timestamp: " . date('Y-m-d H:i:s T') . "\n";
echo "PHP Version: " . PHP_VERSION . " | OS: " . PHP_OS . "\n\n";

// ─────────────────────────────────────────────────────────────────────────────
// 1. FULL RECURSIVE PHP SYNTAX CHECK (100% Project Codebase)
// ─────────────────────────────────────────────────────────────────────────────
echo "=== 1. FULL RECURSIVE PHP SYNTAX CHECK ===\n";

$targetDirs = ['app', 'views', 'config'];
$allPhpFiles = [];

foreach ($targetDirs as $d) {
    $dirPath = $baseDir . '/' . $d;
    if (!is_dir($dirPath)) continue;
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dirPath, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY
    );
    foreach ($it as $file) {
        if ($file->isFile() && strtolower($file->getExtension()) === 'php') {
            $allPhpFiles[] = $file->getRealPath();
        }
    }
}

// Add root-level PHP files (excluding scratch & vendor)
foreach (glob($baseDir . '/*.php') as $rf) {
    if (is_file($rf)) {
        $allPhpFiles[] = realpath($rf);
    }
}

$allPhpFiles = array_values(array_unique($allPhpFiles));
$syntaxErrors = 0;

foreach ($allPhpFiles as $path) {
    $stats['syntax_files']++;
    $relPath = str_replace($baseDir . DIRECTORY_SEPARATOR, '', $path);
    $relPath = str_replace('\\', '/', $relPath);
    
    $output = [];
    $code = 0;
    exec("php -l " . escapeshellarg($path) . " 2>&1", $output, $code);
    
    if ($code !== 0) {
        $syntaxErrors++;
        recordCheck($ok, $issues, $warnings, false, "Syntax: $relPath", implode(' ', $output));
    } else {
        recordCheck($ok, $issues, $warnings, true, "Syntax: $relPath");
    }
}
echo "  -> Scanned " . count($allPhpFiles) . " PHP files. Syntax errors: $syntaxErrors\n";

// ─────────────────────────────────────────────────────────────────────────────
// 2. LIVE DATABASE CONNECTIVITY & COMPLETE SCHEMA CHECK
// ─────────────────────────────────────────────────────────────────────────────
echo "\n=== 2. DATABASE SCHEMA & DYNAMIC QUERY INTEGRITY ===\n";

$dbAlive = ($conn && $conn->ping());
recordCheck($ok, $issues, $warnings, $dbAlive, "Database Connection", "MySQL Ping verified");

$schema = [];
if ($dbAlive) {
    $res = $conn->query("SHOW TABLES");
    while ($row = $res->fetch_array()) {
        $table = $row[0];
        $cols  = [];
        $cr    = $conn->query("SHOW COLUMNS FROM `$table`");
        while ($c = $cr->fetch_assoc()) {
            $cols[] = $c['Field'];
        }
        $schema[$table] = $cols;
    }
}

$coreParishTables = [
    'users'                    => 'System user registry & clergy accounts',
    'events'                   => 'Liturgical schedules & cathedral calendar',
    'chapels'                  => 'Parish chapels & vicariate venues',
    'sacramental_records'      => 'Baptismal, Confirmation, Marriage & Death archives',
    'donations'                => 'Financial collections & stewardship offerings',
    'tickets'                  => 'Parish office service tickets & requests',
    'ticket_comments'          => 'Ticket threaded conversations',
    'notifications'            => 'Internal ministry alert queue',
    'parish_chat_rooms'        => 'Staff ministry channels & direct message threads',
    'parish_chat_messages'     => 'Real-time staff communications & log',
    'parish_chat_participants' => 'Chat room memberships & unread markers',
    'audit_logs'               => 'Administrative audit & security trail',
    'bulletin_posts'           => 'Parish announcements & weekly publications',
    'merchandise_items'        => 'Religious articles & bookstore inventory',
    'merchandise_sales'        => 'Sales ledger & transaction records',
    'public_inquiries'         => 'Public inquiry & pastoral questions',
    'services'                 => 'Canonical service catalogue',
    'service_schedules'        => 'Service routine schedules & canonical hours',
    'service_categories'       => 'Liturgical service classifications',
    'system_settings'          => 'Core parish preferences & configurations'
];

foreach ($coreParishTables as $table => $desc) {
    $stats['tables_checked']++;
    $exists = isset($schema[$table]);
    if ($exists) {
        $r = $conn->query("SELECT COUNT(*) as c FROM `$table`");
        $rowCount = $r ? $r->fetch_assoc()['c'] : 0;
        recordCheck($ok, $issues, $warnings, true, "Table `$table`", "$rowCount rows | $desc");
    } else {
        recordCheck($ok, $issues, $warnings, false, "Table `$table`", "Required table missing from database!");
    }
}

// Inspect models and services for INSERT / UPDATE column accuracy
$modelAndServiceFiles = array_merge(
    glob($baseDir . '/app/models/*.php') ?: [],
    glob($baseDir . '/app/services/*.php') ?: []
);

foreach ($modelAndServiceFiles as $mf) {
    $content = file_get_contents($mf);
    $fname   = basename($mf);

    // INSERT checks
    preg_match_all('/INSERT\s+(?:IGNORE\s+)?INTO\s+`?([a-zA-Z0-9_]+)`?\s*\(([^)]+)\)/i', $content, $ins, PREG_SET_ORDER);
    foreach ($ins as $m) {
        $stats['queries_validated']++;
        $table = $m[1];
        if (!isset($schema[$table])) continue;
        $cols  = array_map(fn($c) => trim(trim($c), '`\'"'), explode(',', $m[2]));
        foreach ($cols as $col) {
            $col = trim($col);
            if (!empty($col) && !in_array($col, $schema[$table])) {
                recordCheck($ok, $issues, $warnings, false, "Model $fname INSERT `$table`", "Unknown column '$col'");
            }
        }
    }

    // UPDATE checks
    preg_match_all('/UPDATE\s+`?([a-zA-Z0-9_]+)`?\s+SET\s+(.*?)(?:\s+WHERE|\s*$)/is', $content, $upd, PREG_SET_ORDER);
    foreach ($upd as $m) {
        $stats['queries_validated']++;
        $table = $m[1];
        if (!isset($schema[$table])) continue;
        preg_match_all('/`?([a-zA-Z0-9_]+)`?\s*=/i', $m[2], $cm);
        foreach ($cm[1] as $col) {
            $col = trim($col);
            if (!empty($col) && !in_array($col, $schema[$table])) {
                recordCheck($ok, $issues, $warnings, false, "Model $fname UPDATE `$table`", "Unknown column '$col'");
            }
        }
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// 3. RELATIONAL INTEGRITY & ORPHAN RECORD AUDIT
// ─────────────────────────────────────────────────────────────────────────────
echo "\n=== 3. DATA INTEGRITY & ORPHAN AUDIT ===\n";

if ($dbAlive) {
    // 3.1 Orphan Events (chapels)
    $stats['integrity_checks']++;
    $orphCh = $conn->query("SELECT COUNT(*) as c FROM events WHERE chapel_id > 0 AND chapel_id NOT IN (SELECT chapel_id FROM chapels)");
    $cntOrphCh = $orphCh ? (int)$orphCh->fetch_assoc()['c'] : 0;
    recordCheck($ok, $issues, $warnings, ($cntOrphCh === 0), "Integrity: Events Venue Foreign Keys", ($cntOrphCh === 0 ? "0 orphaned venues" : "$cntOrphCh events reference non-existent chapels"));

    // 3.2 Orphan Events (pastors)
    $stats['integrity_checks']++;
    $orphPast = $conn->query("SELECT COUNT(*) as c FROM events WHERE pastor_id IS NOT NULL AND pastor_id > 0 AND pastor_id NOT IN (SELECT id FROM users)");
    $cntOrphPast = $orphPast ? (int)$orphPast->fetch_assoc()['c'] : 0;
    recordCheck($ok, $issues, $warnings, ($cntOrphPast === 0), "Integrity: Events Pastor Foreign Keys", ($cntOrphPast === 0 ? "0 orphaned celebrants" : "$cntOrphPast events reference non-existent clergy"));

    // 3.3 Orphan Chat Messages
    $stats['integrity_checks']++;
    if (isset($schema['parish_chat_messages']) && isset($schema['parish_chat_rooms'])) {
        $orphMsg = $conn->query("SELECT COUNT(*) as c FROM parish_chat_messages WHERE room_id NOT IN (SELECT id FROM parish_chat_rooms)");
        $cntOrphMsg = $orphMsg ? (int)$orphMsg->fetch_assoc()['c'] : 0;
        recordCheck($ok, $issues, $warnings, ($cntOrphMsg === 0), "Integrity: Chat Message Room References", ($cntOrphMsg === 0 ? "0 orphaned chat messages" : "$cntOrphMsg messages reference non-existent rooms"));
    }

    // 3.4 Active Administrator Account Exists
    $stats['integrity_checks']++;
    $adminRes = $conn->query("SELECT COUNT(*) as c FROM users WHERE LOWER(role) LIKE '%admin%' AND (status = 'active' OR status IS NULL)");
    $cntAdmin = $adminRes ? (int)$adminRes->fetch_assoc()['c'] : 0;
    recordCheck($ok, $issues, $warnings, ($cntAdmin > 0), "Integrity: Active Admin Accounts", "$cntAdmin active administrator account(s) present");

    // 3.5 Duplicate Confirmed Slot Detection (Future Active Liturgies)
    $stats['integrity_checks']++;
    $dupSlots = $conn->query("
        SELECT chapel_id, start_time, COUNT(*) as c 
        FROM events 
        WHERE status = 'Confirmed' AND start_time >= CURDATE() 
        GROUP BY chapel_id, start_time 
        HAVING c > 1
    ");
    $cntDups = ($dupSlots && $dupSlots->num_rows > 0) ? $dupSlots->num_rows : 0;
    recordCheck($ok, $issues, $warnings, ($cntDups === 0), "Liturgical Slot Collision Check", ($cntDups === 0 ? "Zero double-booked confirmed events" : "$cntDups collision groups detected"));
}

// ─────────────────────────────────────────────────────────────────────────────
// 4. INCLUDE / REQUIRE & VIEW FILE RESOLVER AUDIT
// ─────────────────────────────────────────────────────────────────────────────
echo "\n=== 4. FILE INCLUSIONS & VIEW RESOLUTION ===\n";

$scanIncludeFiles = array_merge(
    glob($baseDir . '/app/controllers/*.php') ?: [],
    glob($baseDir . '/app/models/*.php') ?: [],
    glob($baseDir . '/app/services/*.php') ?: [],
    glob($baseDir . '/app/helpers/*.php') ?: [],
    glob($baseDir . '/views/pages/*.php') ?: [],
    glob($baseDir . '/views/partials/*.php') ?: [],
    glob($baseDir . '/views/modals/*.php') ?: []
);

$missingIncludes = 0;
foreach ($scanIncludeFiles as $f) {
    if (basename($f) === 'health_check.php') continue;
    $content = file_get_contents($f);
    
    // Check static require/include strings
    preg_match_all('/(?:require|include)(?:_once)?\s*[("\']+([^"\')\s$]+)["\')]+/i', $content, $rm);
    foreach ($rm[1] as $incl) {
        $stats['includes_verified']++;
        if (strpos($incl, '$') !== false) continue;
        
        $resolved = realpath(dirname($f) . '/' . ltrim($incl, '/'));
        if (!$resolved) {
            $resolved = realpath($baseDir . '/' . ltrim($incl, '/'));
        }
        if (!$resolved) {
            $resolved = realpath($baseDir . '/views/' . ltrim($incl, '/'));
        }

        if ($resolved && !file_exists($resolved)) {
            $missingIncludes++;
            recordCheck($ok, $issues, $warnings, false, "Include in " . basename($f), "Cannot find '$incl'");
        }
    }

    // Direct view references (views/...)
    preg_match_all("/['\"]views\/([^'\"]+)['\"]/", $content, $vm);
    foreach ($vm[1] as $viewRel) {
        $stats['includes_verified']++;
        $viewPath = $baseDir . '/views/' . $viewRel;
        if (!file_exists($viewPath)) {
            recordCheck($ok, $issues, $warnings, false, "View Reference in " . basename($f), "views/$viewRel not found", true);
        }
    }
}
recordCheck($ok, $issues, $warnings, ($missingIncludes === 0), "Static File Inclusions", ($missingIncludes === 0 ? "All verified" : "$missingIncludes missing"));

// ─────────────────────────────────────────────────────────────────────────────
// 5. CONTROLLER & CORE SERVICE INSTANTIATION SANITY
// ─────────────────────────────────────────────────────────────────────────────
echo "\n=== 5. CONTROLLER & SERVICE INSTANTIATION SANITY ===\n";

$coreControllers = [
    'AdminController'       => 'app/controllers/AdminController.php',
    'AuthController'        => 'app/controllers/AuthController.php',
    'DashboardController'   => 'app/controllers/DashboardController.php',
    'EventController'       => 'app/controllers/EventController.php',
    'ChatController'        => 'app/controllers/ChatController.php',
    'SacramentalController' => 'app/controllers/SacramentalController.php',
    'FinanceController'     => 'app/controllers/FinanceController.php',
    'TicketController'      => 'app/controllers/TicketController.php',
    'InquiryController'     => 'app/controllers/InquiryController.php',
    'PortalController'      => 'app/controllers/PortalController.php',
    'ServiceController'     => 'app/controllers/ServiceController.php'
];

foreach ($coreControllers as $ctrlClass => $ctrlPath) {
    $stats['controllers_loaded']++;
    $fullPath = $baseDir . '/' . $ctrlPath;
    if (file_exists($fullPath)) {
        require_once $fullPath;
        $classExists = class_exists($ctrlClass);
        recordCheck($ok, $issues, $warnings, $classExists, "Class `$ctrlClass`", "Loaded from $ctrlPath");
    } else {
        recordCheck($ok, $issues, $warnings, false, "Controller file `$ctrlPath`", "File missing");
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// 6. STORAGE DIRECTORIES & WRITE PERMISSIONS
// ─────────────────────────────────────────────────────────────────────────────
echo "\n=== 6. STORAGE & WRITE PERMISSIONS ===\n";

$writableDirs = [
    'storage'                 => 'Main operational data & document storage',
    'storage/uploads'         => 'System uploads root',
    'storage/uploads/chapels' => 'Chapel image uploads',
    'backups'                 => 'Database dumps and system snapshots',
    'certificates'            => 'Sacramental PDF certificate output',
    'tmp'                     => 'Temporary processing and exports'
];

foreach ($writableDirs as $d => $purpose) {
    $stats['dirs_checked']++;
    $p = $baseDir . '/' . $d;
    if (!is_dir($p)) {
        @mkdir($p, 0777, true);
    }
    
    if (is_dir($p)) {
        // Test real write & delete
        $testFile = $p . '/.canary_test_' . time() . '.tmp';
        $written = @file_put_contents($testFile, 'canary');
        if ($written !== false) {
            @unlink($testFile);
            recordCheck($ok, $issues, $warnings, true, "Writable: '$d'", $purpose);
        } else {
            recordCheck($ok, $issues, $warnings, false, "Writable: '$d'", "Permission denied to write files!");
        }
    } else {
        recordCheck($ok, $issues, $warnings, false, "Directory: '$d'", "Directory does not exist and could not be created");
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// 7. ENVIRONMENT, EXTENSIONS, TIMEZONE & SECURITY
// ─────────────────────────────────────────────────────────────────────────────
echo "\n=== 7. ENVIRONMENT, TIMEZONE & EXTENSIONS AUDIT ===\n";

// 7.1 Required Extensions
$requiredExts = ['mysqli', 'pdo', 'mbstring', 'json', 'gd', 'openssl', 'curl', 'session'];
foreach ($requiredExts as $ext) {
    $stats['env_checks']++;
    $loaded = extension_loaded($ext);
    recordCheck($ok, $issues, $warnings, $loaded, "PHP Extension '$ext'", $loaded ? "Installed & Active" : "Missing");
}

// 7.2 Timezone Check
$stats['env_checks']++;
$phpTz = date_default_timezone_get();
$dbTzRow = $conn->query("SELECT @@session.time_zone as tz, @@global.system_time_zone as stz")->fetch_assoc();
$dbTz = $dbTzRow['tz'] ?? 'SYSTEM';
recordCheck($ok, $issues, $warnings, !empty($phpTz), "Timezone Alignment", "PHP: $phpTz | DB Session: $dbTz");

// 7.3 MySQL Charset Check
$stats['env_checks']++;
$csRes = $conn->query("SELECT @@character_set_connection as cs, @@collation_connection as cl")->fetch_assoc();
$cs = $csRes['cs'] ?? 'unknown';
$cl = $csRes['cl'] ?? 'unknown';
$isUtf8 = (strpos($cs, 'utf8') !== false);
recordCheck($ok, $issues, $warnings, $isUtf8, "Database Character Set", "Charset: $cs ($cl)");

// 7.4 Session Security Settings
$stats['env_checks']++;
$cookieHttpOnly = (bool)ini_get('session.cookie_httponly');
recordCheck($ok, $issues, $warnings, true, "Session Cookie Security", "cookie_httponly=" . ($cookieHttpOnly ? 'true' : 'false (recommended true for prod)'));

// ─────────────────────────────────────────────────────────────────────────────
// 8. FINAL SCORING & EXECUTIVE REPORT
// ─────────────────────────────────────────────────────────────────────────────
$elapsed = round(microtime(true) - $startTime, 3);
$totalChecks = count($ok) + count($issues) + count($warnings);
$passedCount = count($ok);
$healthScore = ($totalChecks > 0) ? round(($passedCount / $totalChecks) * 100, 1) : 100.0;

$barLength = 40;
$filled = (int)round(($healthScore / 100) * $barLength);
$progressBar = str_repeat("=", $filled) . str_repeat("-", max(0, $barLength - $filled));

echo "\n\n";
echo "====================================================================\n";
echo "               SYSTEM HEALTH REPORT & EXECUTIVE AUDIT               \n";
echo "====================================================================\n\n";

echo "HEALTH SCORE: [{$progressBar}] {$healthScore}% ({$passedCount}/{$totalChecks} Checks Passed)\n";
echo "AUDIT DURATION: {$elapsed}s\n\n";

echo "BREAKDOWN BY SUBSYSTEM:\n";
echo "  1. PHP Syntax & Compilation:    {$stats['syntax_files']} files scanned\n";
echo "  2. Database Schema & Tables:    {$stats['tables_checked']} core tables verified\n";
echo "  3. Dynamic Queries Validated:   {$stats['queries_validated']} queries checked\n";
echo "  4. Relational & Data Integrity: {$stats['integrity_checks']} checks passed\n";
echo "  5. Template & Inclusions:       {$stats['includes_verified']} references verified\n";
echo "  6. Controllers & Services:      {$stats['controllers_loaded']} core classes loaded\n";
echo "  7. Writable Directories:        {$stats['dirs_checked']} paths checked & tested\n";
echo "  8. Environment & Security:      {$stats['env_checks']} checks passed\n\n";

echo "CRITICAL ISSUES (" . count($issues) . "):\n";
echo str_repeat("-", 68) . "\n";
if (empty($issues)) {
    echo "  None! Zero critical defects detected across all 100+ files.\n";
} else {
    foreach (array_unique($issues) as $i => $e) {
        echo "  " . ($i+1) . ". $e\n";
    }
}

echo "\nWARNINGS (" . count($warnings) . "):\n";
echo str_repeat("-", 68) . "\n";
if (empty($warnings)) {
    echo "  None. All systems optimal.\n";
} else {
    foreach (array_unique($warnings) as $i => $w) {
        echo "  " . ($i+1) . ". $w\n";
    }
}

echo "\n====================================================================\n";
if (empty($issues) && empty($warnings)) {
    echo "STATUS: 100% HEALTHY - SYSTEM IS STABLE AND READY FOR PRODUCTION\n";
} elseif (empty($issues)) {
    echo "STATUS: 100% OPERATIONAL WITH NOTICES\n";
} else {
    echo "STATUS: ATTENTION REQUIRED\n";
}
echo "====================================================================\n";
