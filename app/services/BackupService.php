<?php

class BackupService
{
    private const BACKUP_FILE_PATTERN = '/^[a-zA-Z0-9_-]+_\d{8}_\d{6}(?:_\d+)?\.zip$/';

    private mysqli $conn;
    private string $basePath;
    private array $dbConfig;

    public function __construct(mysqli $conn, string $basePath, array $dbConfig)
    {
        $this->conn = $conn;
        $this->basePath = rtrim($basePath, '/\\');
        $this->dbConfig = $dbConfig;
    }

    public function createBackup(
        string $source = 'manual',
        int $initiatedBy = 0,
        string $initiatedByName = 'system',
        int $keepLatest = 14,
        string $filePrefix = 'backup'
    ): array {
        $this->ensureBackupLogTable();

        $prefix = trim($filePrefix) !== '' ? preg_replace('/[^a-zA-Z0-9_\-]/', '_', $filePrefix) : 'backup';
        $timestamp = date('Ymd_His');
        $backupDir = $this->basePath . DIRECTORY_SEPARATOR . 'backups';
        [$zipName, $workDir] = $this->allocateUniqueBackupTargets($backupDir, $prefix, $timestamp);
        $zipPath = $backupDir . DIRECTORY_SEPARATOR . $zipName;
        $sqlPath = $workDir . DIRECTORY_SEPARATOR . 'database.sql';
        $metaPath = $workDir . DIRECTORY_SEPARATOR . 'metadata.json';

        $this->ensureDir($backupDir);
        $this->ensureDir($workDir);
        $this->cleanupStaleWorkDirs($backupDir);

        $logId = $this->insertBackupLog([
            'source' => $source,
            'status' => 'running',
            'backup_file' => $zipName,
            'initiated_by' => $initiatedBy,
            'initiated_by_name' => $initiatedByName,
            'notes' => 'Backup started',
        ]);

        try {
            $this->runMysqldump($sqlPath);

            $metadata = [
                'created_at' => date('c'),
                'source' => $source,
                'initiated_by' => $initiatedBy,
                'initiated_by_name' => $initiatedByName,
                'db_name' => $this->dbConfig['db'] ?? '',
                'app_path' => $this->basePath,
            ];
            file_put_contents($metaPath, json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            $this->createArchive($zipPath, $workDir);
            if (!is_file($zipPath) || filesize($zipPath) <= 0) {
                throw new RuntimeException('Backup archive was created but is empty.');
            }
            $this->cleanupDirWithRetries($workDir);
            $this->applyRetention($backupDir, $keepLatest, $prefix);

            $size = is_file($zipPath) ? filesize($zipPath) : 0;
            $successPayload = [
                'status' => 'success',
                'backup_size' => $size,
                'notes' => 'Backup completed',
            ];
            $this->updateBackupLog($logId, $successPayload);
            if ($this->getBackupLogStatus($logId) !== 'success') {
                $this->updateBackupLog($logId, $successPayload);
            }

            return [
                'ok' => true,
                'file' => $zipPath,
                'file_name' => $zipName,
                'size' => $size,
                'log_id' => $logId,
            ];
        } catch (Throwable $e) {
            $this->cleanupDirWithRetries($workDir);
            $failedPayload = [
                'status' => 'failed',
                'notes' => $e->getMessage(),
            ];
            $this->updateBackupLog($logId, $failedPayload);
            if ($this->getBackupLogStatus($logId) !== 'failed') {
                $this->updateBackupLog($logId, $failedPayload);
            }

            return [
                'ok' => false,
                'error' => $e->getMessage(),
                'log_id' => $logId,
            ];
        }
    }

    public function restoreBackup(string $backupFile, bool $restoreStorage = true): array
    {
        $this->ensureBackupLogTable();

        $backupPath = $backupFile;
        if (!preg_match('/^[A-Za-z]:\\\\|^\//', $backupFile)) {
            $backupFile = basename(ltrim($backupFile, '/\\'));
            if (!self::isValidBackupFileName($backupFile)) {
                return ['ok' => false, 'error' => 'Invalid backup file name.'];
            }
            $backupPath = $this->basePath . DIRECTORY_SEPARATOR . 'backups' . DIRECTORY_SEPARATOR . $backupFile;
        } elseif (!self::isValidBackupFileName(basename($backupPath))) {
            return ['ok' => false, 'error' => 'Invalid backup file name.'];
        }

        if (!is_file($backupPath)) {
            return ['ok' => false, 'error' => 'Backup file not found: ' . $backupPath];
        }

        $restoreDir = $this->basePath . DIRECTORY_SEPARATOR . 'backups' . DIRECTORY_SEPARATOR . 'tmp_restore_' . date('Ymd_His');
        $this->ensureDir($restoreDir);

        try {
            $this->extractArchive($backupPath, $restoreDir);

            $sqlPath = $restoreDir . DIRECTORY_SEPARATOR . 'database.sql';
            if (!is_file($sqlPath)) {
                throw new RuntimeException('database.sql missing from archive.');
            }

            $this->runMysqlImport($sqlPath);

            if ($restoreStorage) {
                $restoredStorage = $restoreDir . DIRECTORY_SEPARATOR . 'storage';
                if (is_dir($restoredStorage)) {
                    $targetStorage = $this->basePath . DIRECTORY_SEPARATOR . 'storage';
                    $this->mirrorDir($restoredStorage, $targetStorage);
                }
            }

            $this->cleanupDir($restoreDir);
            return ['ok' => true, 'file' => $backupPath];
        } catch (Throwable $e) {
            $this->cleanupDir($restoreDir);
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    private function extractArchive(string $zipPath, string $targetDir): void
    {
        if (class_exists('ZipArchive')) {
            $zip = new ZipArchive();
            if ($zip->open($zipPath) !== true) {
                throw new RuntimeException('Unable to open backup archive.');
            }
            if (!$zip->extractTo($targetDir)) {
                $zip->close();
                throw new RuntimeException('Unable to extract backup archive.');
            }
            $zip->close();
            return;
        }

        $this->extractArchiveViaPowerShell($zipPath, $targetDir);
    }

    private function extractArchiveViaPowerShell(string $zipPath, string $targetDir): void
    {
        $psScript = 'Expand-Archive -LiteralPath ' . escapeshellarg($zipPath)
            . ' -DestinationPath ' . escapeshellarg($targetDir) . ' -Force';
        $cmd = 'powershell -NoProfile -ExecutionPolicy Bypass -Command ' . escapeshellarg($psScript) . ' 2>&1';

        $output = [];
        $code = 1;
        exec($cmd, $output, $code);

        if ($code !== 0) {
            $msg = trim(implode("\n", $output));
            throw new RuntimeException(
                "ZipArchive extension is unavailable and PowerShell unzip fallback failed. "
                . ($msg !== '' ? $msg : 'Enable PHP zip extension in php.ini.')
            );
        }
    }

    public function getRecentLogs(int $limit = 30): array
    {
        $this->ensureBackupLogTable();
        $limit = max(1, min(200, $limit));
        $rows = [];
        $sql = "SELECT * FROM backup_logs ORDER BY created_at DESC, id DESC LIMIT {$limit}";
        $res = $this->conn->query($sql);
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $rows[] = $row;
            }
        }
        return $rows;
    }

    public function listBackups(int $limit = 30): array
    {
        return $this->getRecentLogs($limit);
    }

    public static function isValidBackupFileName(string $file): bool
    {
        return preg_match(self::BACKUP_FILE_PATTERN, $file) === 1;
    }

    private function ensureBackupLogTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS backup_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            source VARCHAR(40) NOT NULL DEFAULT 'manual',
            status VARCHAR(20) NOT NULL DEFAULT 'running',
            backup_file VARCHAR(255) DEFAULT NULL,
            backup_size BIGINT DEFAULT NULL,
            initiated_by INT DEFAULT NULL,
            initiated_by_name VARCHAR(120) DEFAULT NULL,
            notes TEXT DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

        $this->conn->query($sql);
    }

    private function insertBackupLog(array $data): int
    {
        $stmt = $this->conn->prepare(
            "INSERT INTO backup_logs (source, status, backup_file, initiated_by, initiated_by_name, notes)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        if (!$stmt) {
            return 0;
        }

        $source = (string)($data['source'] ?? 'manual');
        $status = (string)($data['status'] ?? 'running');
        $backupFile = (string)($data['backup_file'] ?? '');
        $rawInitiatedBy = (int)($data['initiated_by'] ?? 0);
        $initiatedBy = ($rawInitiatedBy > 0) ? $rawInitiatedBy : null;
        $initiatedByName = (string)($data['initiated_by_name'] ?? 'system');
        $notes = (string)($data['notes'] ?? '');

        $stmt->bind_param('sssiss', $source, $status, $backupFile, $initiatedBy, $initiatedByName, $notes);
        $stmt->execute();
        $id = (int)$stmt->insert_id;
        $stmt->close();
        return $id;
    }

    private function updateBackupLog(int $id, array $data): void
    {
        if ($id <= 0) {
            return;
        }
        $status = (string)($data['status'] ?? 'failed');
        $size = isset($data['backup_size']) ? (int)$data['backup_size'] : null;
        $notes = (string)($data['notes'] ?? '');

        $statusEsc = $this->conn->real_escape_string($status);
        $notesEsc = $this->conn->real_escape_string($notes);
        $sizeSql = ($size === null) ? 'NULL' : (string)$size;

        $sql = "UPDATE backup_logs
                SET status = '{$statusEsc}',
                    backup_size = {$sizeSql},
                    notes = '{$notesEsc}',
                    updated_at = NOW()
                WHERE id = " . (int)$id;
        $this->conn->query($sql);
    }

    private function getBackupLogStatus(int $id): ?string
    {
        if ($id <= 0) {
            return null;
        }
        $res = $this->conn->query("SELECT status FROM backup_logs WHERE id = " . (int)$id . " LIMIT 1");
        if (!$res) {
            return null;
        }
        $row = $res->fetch_assoc();
        return $row['status'] ?? null;
    }

    private function runMysqldump(string $outputFile): void
    {
        $bin = $this->resolveMysqlBinary(true);
        $host = (string)($this->dbConfig['host'] ?? '127.0.0.1');
        $port = (int)($this->dbConfig['port'] ?? 3306);
        $user = (string)($this->dbConfig['user'] ?? 'root');
        $pass = (string)($this->dbConfig['pass'] ?? '');
        $db = (string)($this->dbConfig['db'] ?? '');

        if ($db === '') {
            throw new RuntimeException('Database name is missing for backup.');
        }

        $args = [
            $bin,
            '--single-transaction',
            '--routines',
            '--triggers',
            '--events',
            '--host=' . $host,
            '--port=' . (string)$port,
            '--user=' . $user,
            '--result-file=' . $outputFile,
        ];

        if ($pass !== '') {
            $args[] = '--password=' . $pass;
        }

        $args[] = $db;

        $result = $this->runProcess($args);
        $code = $result['code'];
        if ($code !== 0 || !is_file($outputFile) || filesize($outputFile) === 0) {
            $msg = trim($result['output']);
            throw new RuntimeException('mysqldump failed. ' . ($msg !== '' ? $msg : 'No output.'));
        }
    }

    private function runMysqlImport(string $sqlFile): void
    {
        $bin = $this->resolveMysqlBinary(false);
        $host = (string)($this->dbConfig['host'] ?? '127.0.0.1');
        $port = (int)($this->dbConfig['port'] ?? 3306);
        $user = (string)($this->dbConfig['user'] ?? 'root');
        $pass = (string)($this->dbConfig['pass'] ?? '');
        $db = (string)($this->dbConfig['db'] ?? '');

        if ($db === '') {
            throw new RuntimeException('Database name is missing for restore.');
        }

        $args = [
            $bin,
            '--host=' . $host,
            '--port=' . (string)$port,
            '--user=' . $user,
        ];

        if ($pass !== '') {
            $args[] = '--password=' . $pass;
        }

        $args[] = $db;

        $result = $this->runProcess($args, $sqlFile);
        $code = $result['code'];
        if ($code !== 0) {
            $msg = trim($result['output']);
            throw new RuntimeException('mysql import failed. ' . ($msg !== '' ? $msg : 'No output.'));
        }
    }

    private function resolveMysqlBinary(bool $dump): string
    {
        $envKey = $dump ? 'MYSQLDUMP_BIN' : 'MYSQL_BIN';
        $envBin = getenv($envKey);
        if (is_string($envBin) && $envBin !== '') {
            return $envBin;
        }

        $exe = $dump ? 'mysqldump.exe' : 'mysql.exe';
        $plain = $dump ? 'mysqldump' : 'mysql';
        $candidates = [
            'C:\\xampp\\mysql\\bin\\' . $exe,
            'C:\\Program Files\\MySQL\\MySQL Server 8.0\\bin\\' . $exe,
            'C:\\Program Files\\MySQL\\MySQL Server 5.7\\bin\\' . $exe,
        ];

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return $plain;
    }

    private function runProcess(array $args, ?string $stdinFile = null): array
    {
        $cmd = implode(' ', array_map('escapeshellarg', $args));
        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($cmd, $descriptorSpec, $pipes);
        if (!is_resource($process)) {
            throw new RuntimeException('Unable to start database command.');
        }

        if ($stdinFile !== null) {
            $input = fopen($stdinFile, 'rb');
            if ($input === false) {
                fclose($pipes[0]);
                proc_close($process);
                throw new RuntimeException('Unable to open SQL file for restore.');
            }
            while (!feof($input)) {
                fwrite($pipes[0], (string)fread($input, 8192));
            }
            fclose($input);
        }

        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]) ?: '';
        $error = stream_get_contents($pipes[2]) ?: '';
        fclose($pipes[1]);
        fclose($pipes[2]);

        return [
            'code' => proc_close($process),
            'output' => trim($output . "\n" . $error),
        ];
    }

    private function createArchive(string $zipPath, string $workDir): void
    {
        if (!class_exists('ZipArchive')) {
            $this->createArchiveViaPowerShell($zipPath, $workDir);
            return;
        }

        if (is_file($zipPath)) {
            @unlink($zipPath);
        }

        $tempZipPath = $workDir . DIRECTORY_SEPARATOR . '__bundle.zip';
        $zip = new ZipArchive();
        if ($zip->open($tempZipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to create backup archive.');
        }

        $dbFile = $workDir . DIRECTORY_SEPARATOR . 'database.sql';
        if (is_file($dbFile)) {
            $zip->addFile($dbFile, 'database.sql');
        }

        $metaFile = $workDir . DIRECTORY_SEPARATOR . 'metadata.json';
        if (is_file($metaFile)) {
            $zip->addFile($metaFile, 'metadata.json');
        }

        $storagePath = $this->basePath . DIRECTORY_SEPARATOR . 'storage';
        if (is_dir($storagePath)) {
            $this->zipDir($zip, $storagePath, 'storage');
        }

        $closed = @$zip->close();

        $finalizedZip = $tempZipPath;
        if (!is_file($finalizedZip) || filesize($finalizedZip) <= 0) {
            $candidates = glob($tempZipPath . '.*') ?: [];
            if (!empty($candidates)) {
                usort($candidates, static function ($a, $b) {
                    return filemtime($b) <=> filemtime($a);
                });
                if (is_file($candidates[0]) && filesize($candidates[0]) > 0) {
                    $finalizedZip = $candidates[0];
                }
            }
        }

        if ($closed !== true && (!is_file($finalizedZip) || filesize($finalizedZip) <= 0)) {
            throw new RuntimeException('Failed to finalize zip archive.');
        }

        if (!@rename($finalizedZip, $zipPath)) {
            if (!@copy($finalizedZip, $zipPath)) {
                throw new RuntimeException('Unable to move finalized backup archive.');
            }
            @unlink($finalizedZip);
        }

        if ($finalizedZip !== $tempZipPath && is_file($tempZipPath)) {
            @unlink($tempZipPath);
        }
    }

    private function createArchiveViaPowerShell(string $zipPath, string $workDir): void
    {
        $stageDir = $workDir . DIRECTORY_SEPARATOR . '__stage';
        $this->ensureDir($stageDir);

        $dbFile = $workDir . DIRECTORY_SEPARATOR . 'database.sql';
        if (is_file($dbFile)) {
            copy($dbFile, $stageDir . DIRECTORY_SEPARATOR . 'database.sql');
        }

        $metaFile = $workDir . DIRECTORY_SEPARATOR . 'metadata.json';
        if (is_file($metaFile)) {
            copy($metaFile, $stageDir . DIRECTORY_SEPARATOR . 'metadata.json');
        }

        $storagePath = $this->basePath . DIRECTORY_SEPARATOR . 'storage';
        if (is_dir($storagePath)) {
            $this->mirrorDir($storagePath, $stageDir . DIRECTORY_SEPARATOR . 'storage');
        }

        if (is_file($zipPath)) {
            @unlink($zipPath);
        }

        $source = $stageDir . DIRECTORY_SEPARATOR . '*';
        $psScript = 'Compress-Archive -Path ' . escapeshellarg($source)
            . ' -DestinationPath ' . escapeshellarg($zipPath) . ' -Force';
        $cmd = 'powershell -NoProfile -ExecutionPolicy Bypass -Command ' . escapeshellarg($psScript) . ' 2>&1';

        $output = [];
        $code = 1;
        exec($cmd, $output, $code);

        $this->cleanupDirWithRetries($stageDir);

        if ($code !== 0 || !is_file($zipPath) || filesize($zipPath) <= 0) {
            $msg = trim(implode("\n", $output));
            throw new RuntimeException(
                "ZipArchive extension is unavailable and PowerShell zip fallback failed. "
                . ($msg !== '' ? $msg : 'Enable PHP zip extension in php.ini.')
            );
        }
    }

    private function zipDir(ZipArchive $zip, string $realPath, string $zipBase): void
    {
        $realPath = rtrim($realPath, '/\\');
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($realPath, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }
            $full = $file->getPathname();
            $relative = substr($full, strlen($realPath) + 1);
            $relative = str_replace('\\', '/', $relative);
            $zip->addFile($full, $zipBase . '/' . $relative);
        }
    }

    private function applyRetention(string $backupDir, int $keepLatest, string $prefix = 'backup'): void
    {
        $keepLatest = max(1, $keepLatest);
        $safePrefix = trim($prefix) !== '' ? preg_replace('/[^a-zA-Z0-9_\-]/', '_', $prefix) : 'backup';
        $files = glob($backupDir . DIRECTORY_SEPARATOR . $safePrefix . '_*.zip');
        if (!$files || count($files) <= $keepLatest) {
            return;
        }

        usort($files, static function ($a, $b) {
            return filemtime($b) <=> filemtime($a);
        });

        $toDelete = array_slice($files, $keepLatest);
        foreach ($toDelete as $oldFile) {
            @unlink($oldFile);
        }
    }

    private function mirrorDir(string $source, string $target): void
    {
        $this->ensureDir($target);
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $srcPath = $item->getPathname();
            $relative = substr($srcPath, strlen(rtrim($source, '/\\')) + 1);
            $destPath = $target . DIRECTORY_SEPARATOR . $relative;

            if ($item->isDir()) {
                $this->ensureDir($destPath);
            } else {
                $parent = dirname($destPath);
                $this->ensureDir($parent);
                copy($srcPath, $destPath);
            }
        }
    }

    private function ensureDir(string $dir): void
    {
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException('Unable to create directory: ' . $dir);
        }

        if (!is_writable($dir)) {
            throw new RuntimeException('Directory is not writable: ' . $dir);
        }
    }

    private function cleanupDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            if ($item->isDir()) {
                @rmdir($item->getPathname());
            } else {
                @unlink($item->getPathname());
            }
        }
        @rmdir($dir);
    }

    private function cleanupDirWithRetries(string $dir, int $tries = 4): void
    {
        for ($i = 0; $i < $tries; $i++) {
            $this->cleanupDir($dir);
            if (!is_dir($dir)) {
                return;
            }
            usleep(250000);
        }
    }

    private function cleanupStaleWorkDirs(string $backupDir, int $olderThanHours = 24): void
    {
        $pattern = $backupDir . DIRECTORY_SEPARATOR . 'work_*';
        $dirs = glob($pattern, GLOB_ONLYDIR) ?: [];
        if (empty($dirs)) {
            return;
        }

        $cutoff = time() - ($olderThanHours * 3600);
        foreach ($dirs as $dir) {
            $mtime = @filemtime($dir);
            if ($mtime !== false && $mtime <= $cutoff) {
                $this->cleanupDirWithRetries($dir);
            }
        }
    }

    private function allocateUniqueBackupTargets(string $backupDir, string $prefix, string $timestamp): array
    {
        $attempt = 0;
        do {
            $suffix = $attempt > 0 ? '_' . $attempt : '';
            $nameCore = $prefix . '_' . $timestamp . $suffix;
            $zipName = $nameCore . '.zip';
            $workDir = $backupDir . DIRECTORY_SEPARATOR . 'work_' . $nameCore;
            $zipPath = $backupDir . DIRECTORY_SEPARATOR . $zipName;
            $attempt++;
        } while (is_file($zipPath) || is_dir($workDir));

        return [$zipName, $workDir];
    }
}
