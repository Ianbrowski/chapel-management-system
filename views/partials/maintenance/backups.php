<?php
if (($role ?? '') !== 'Admin') {
    echo "<div class='alert alert-warning' style='padding: 16px; border-radius: 8px; margin: 16px 0;'><i class='fa-solid fa-shield-halved' style='margin-right: 8px;'></i> You are not authorized to view or manage backups. Database snapshots are restricted strictly to Executive Administrators.</div>";
    return;
}

require_once BASE_PATH . '/app/services/BackupService.php';

$dbCfg = [
    'host' => $host ?? '127.0.0.1',
    'port' => $port ?? 3306,
    'user' => $user ?? 'root',
    'pass' => $pass ?? '',
    'db'   => $db ?? '',
];

$backupService = new BackupService($conn, BASE_PATH, $dbCfg);
$backupLogs = $backupService->getRecentLogs(25);
$availableBackupFiles = glob(BASE_PATH . '/backups/*.zip') ?: [];
usort($availableBackupFiles, static function ($a, $b) {
    return filemtime($b) <=> filemtime($a);
});
$backupEndpoint   = rtrim(BASE_URL, '/') . '/tools/backup.php';
$restoreEndpoint  = rtrim(BASE_URL, '/') . '/tools/restore_web.php';
$downloadEndpoint = rtrim(BASE_URL, '/') . '/tools/backup_download.php';

$totalBackupCount = count($availableBackupFiles);
$totalStorageBytes = 0;
foreach ($availableBackupFiles as $f) {
    $totalStorageBytes += (int)@filesize($f);
}
$totalStorageMb = round($totalStorageBytes / (1024 * 1024), 2);
$latestBackupTime = !empty($availableBackupFiles) 
    ? date('M d, Y h:i A', (int)filemtime($availableBackupFiles[0])) 
    : 'No backups yet';
?>

<link rel="stylesheet" href="assets/css/workspace.css?v=2.5">

<div class="workspace">
    <!-- ─── Workspace Header ─── -->
    <div class="workspace-header page-header-bar" style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border); padding-bottom: 8px; margin-bottom: 6px;">
        <div style="display: flex; align-items: center; gap: 12px;">
            <div class="page-header-icon" style="width: 38px; height: 38px; border-radius: 9px; background: rgba(201,168,76,0.14); color: var(--accent); display: flex; align-items: center; justify-content: center; font-size: 17px; flex-shrink: 0;">
                <i class="fa-solid fa-database"></i>
            </div>
            <div>
                <h1 class="page-title" style="font-family: var(--font-family, 'Inter', sans-serif); font-size: var(--font-page-title, 20px); font-weight: 700; color: var(--text); margin: 0; line-height: 1.2;">
                    Database Backups
                </h1>
                <p class="page-subtitle" style="font-size: var(--font-page-subtitle, 14px); color: var(--text2); margin: 3px 0 0 0;">
                    Manage parish database archives, weekly restore points, and disaster recovery images
                </p>
            </div>
        </div>
        <div style="display: flex; gap: 8px; align-items: center;">
            <button type="button" class="btn btn-primary btn-sm" onclick="triggerBackupKind('daily')">
                <i class="fa-solid fa-plus"></i> Backup Now
            </button>
        </div>
    </div>

    <!-- ─── KPI Stats Row ─── -->
    <div class="stats-grid-4">
        <div class="stat-card sc-accent">
            <div class="stat-label">Available Restore Points</div>
            <div class="stat-value color-accent"><?php echo $totalBackupCount; ?></div>
        </div>
        <div class="stat-card sc-blue">
            <div class="stat-label">Archive Storage</div>
            <div class="stat-value color-blue"><?php echo $totalStorageMb; ?> MB</div>
        </div>
        <div class="stat-card sc-green">
            <div class="stat-label">Latest Archive</div>
            <div class="stat-value color-green" style="font-size: 1.1rem; line-height: 1.3; font-weight: 600;"><?php echo $latestBackupTime; ?></div>
        </div>
        <div class="stat-card sc-purple">
            <div class="stat-label">Database Health</div>
            <div class="stat-value color-purple" style="font-size: 1.1rem; line-height: 1.3; font-weight: 600;">Connected · MySQL</div>
        </div>
    </div>

    <!-- ─── Split Content: Tables + Control Dossier ─── -->
    <div class="split-panel split-65-35" style="flex: 1; min-height: 0;">
        
        <!-- Left: Available Restore Points & Execution Logs -->
        <div class="split-left" style="height: 100%; display: flex; flex-direction: column; gap: var(--workspace-gap); overflow: hidden; min-height: 0;">
            
            <!-- Restore Points Panel -->
            <div class="panel" style="flex: 1.2; display: flex; flex-direction: column; min-height: 0;">
                <div class="panel-header" style="padding: 10px 14px; display: flex; justify-content: space-between; align-items: center;">
                    <div class="panel-title">
                        <i class="fa-solid fa-box-archive" style="color: var(--accent);"></i>
                        <span>Available Restore Points</span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <span style="font-size: var(--font-size-xs); color: var(--text2); font-weight: 500;">
                            <?php echo $totalBackupCount; ?> restore archives ready
                        </span>
                        <button type="button" class="btn btn-primary btn-sm" onclick="triggerBackupKind('daily')" style="padding: 4px 12px; font-size: 12px;">
                            <i class="fa-solid fa-plus"></i> Backup Now
                        </button>
                    </div>
                </div>

                <div class="panel-body" style="padding: 0; overflow-y: auto; flex: 1; min-height: 0;">
                    <?php if (empty($availableBackupFiles)): ?>
                        <div style="padding: 50px 20px; text-align: center; color: var(--text2);">
                            <i class="fa-solid fa-box-open" style="font-size: 40px; opacity: 0.25; margin-bottom: 10px; display: block; color: var(--accent);"></i>
                            <div style="/* was: 15px */ font-size: var(--font-size-base); font-weight: 600; color: var(--text); margin-bottom: 4px;">No Backup Archives Found</div>
                            <div style="/* was: 12px */ font-size: var(--font-size-sm); margin-bottom: 14px;">Generate your first database backup using the control panel.</div>
                            <button type="button" class="btn btn-primary btn-sm" onclick="triggerBackupKind('daily')">
                                <i class="fa-solid fa-floppy-disk"></i> Create First Backup
                            </button>
                        </div>
                    <?php else: ?>
                    <div class="table-container" style="border: none; margin: 0; width: 100%;">
                        <table class="data-table" style="width: 100%; table-layout: fixed; border-collapse: collapse;">
                            <thead>
                                <tr>
                                    <th style="width: auto;">Archive File</th>
                                    <th style="width: 135px; text-align: center;">Kind / Retention</th>
                                    <th style="width: 85px;">Size</th>
                                    <th style="width: 135px;">Timestamp</th>
                                    <th style="width: 110px; text-align: center;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($availableBackupFiles as $fullPath): 
                                    $file = basename($fullPath);
                                    $isWeekly = str_starts_with($file, 'weekly_restore_point_');
                                    $typeLabel = $isWeekly ? 'Weekly Snapshot' : 'Daily Archive';
                                    $typeBadgeStyle = $isWeekly 
                                        ? 'background: rgba(197,160,89,0.15); color: var(--accent, #c5a059); border: 1px solid rgba(197,160,89,0.3);' 
                                        : 'background: rgba(148,163,184,0.15); color: #cbd5e1; border: 1px solid rgba(148,163,184,0.3);';
                                    $typeIcon = $isWeekly ? 'fa-calendar-week' : 'fa-calendar-day';
                                    $sizeLabel = number_format((int)filesize($fullPath) / 1024, 1) . ' KB';
                                    $timeLabel = date('M d, Y h:i A', (int)filemtime($fullPath));
                                ?>
                                <tr class="record-row">
                                    <td style="white-space: normal;">
                                        <div style="font-weight: 600; /* was: 12px */ font-size: var(--font-size-sm); color: var(--text); line-height: 1.3; overflow-wrap: break-word; font-family: var(--font-mono, monospace);">
                                            <i class="fa-solid fa-file-zipper" style="color: var(--accent); margin-right: 4px;"></i>
                                            <?php echo htmlspecialchars($file); ?>
                                        </div>
                                    </td>
                                    <td style="text-align: center;">
                                        <span class="badge" style="<?php echo $typeBadgeStyle; ?> /* was: 10px */ font-size: var(--font-size-xs); padding: 2px 8px; font-weight: 600;">
                                            <i class="fa-solid <?php echo $typeIcon; ?>" style="font-size: 9px; margin-right: 3px;"></i>
                                            <?php echo htmlspecialchars($typeLabel); ?>
                                        </span>
                                    </td>
                                    <td style="font-size: 11px; color: var(--text2); font-family: var(--font-mono, monospace);">
                                        <?php echo htmlspecialchars($sizeLabel); ?>
                                    </td>
                                    <td style="font-size: 11px; color: var(--text2); white-space: nowrap;">
                                        <i class="fa-regular fa-clock" style="color: var(--accent); margin-right: 3px;"></i>
                                        <?php echo htmlspecialchars($timeLabel); ?>
                                    </td>
                                    <td style="text-align: center;">
                                        <div style="display: flex; gap: 4px; justify-content: center;">
                                            <a class="btn-table-action" href="<?php echo htmlspecialchars($downloadEndpoint, ENT_QUOTES); ?>?file=<?php echo urlencode($file); ?>" title="Download ZIP Archive">
                                                <i class="fa-solid fa-download"></i>
                                            </a>
                                            <button type="button" class="btn-table-action" style="/* was: #ef4444 */ color: var(--color-danger);" onclick='restoreFromBackup(<?php echo json_encode($file, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)' title="Restore to this point">
                                                <i class="fa-solid fa-rotate-left"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Operation Logs Panel -->
            <div class="panel" style="flex: 1; display: flex; flex-direction: column; min-height: 0;">
                <div class="panel-header" style="padding: 10px 14px;">
                    <div class="panel-title">
                        <i class="fa-solid fa-clock-rotate-left" style="color: var(--accent);"></i>
                        <span>Recent Operation Logs</span>
                    </div>
                    <span style="/* was: 0.75rem */ font-size: var(--font-size-xs); color: var(--text2);">
                        Last 25 backup activities
                    </span>
                </div>

                <div class="panel-body" style="padding: 0; overflow-y: auto; flex: 1; min-height: 0;">
                    <?php if (empty($backupLogs)): ?>
                        <div style="padding: 40px 20px; text-align: center; color: var(--text2); font-size: 13px;">
                            No recent backup operations logged.
                        </div>
                    <?php else: ?>
                    <div class="table-container" style="border: none; margin: 0; width: 100%;">
                        <table class="data-table" style="width: 100%; table-layout: fixed; border-collapse: collapse;">
                            <thead>
                                <tr>
                                    <th style="width: 45px; text-align: center;">#</th>
                                    <th style="width: 85px; text-align: center;">Status</th>
                                    <th style="width: 90px;">Source</th>
                                    <th style="width: auto;">Archive / Notes</th>
                                    <th style="width: 75px;">Size</th>
                                    <th style="width: 130px;">Executed At</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($backupLogs as $log): 
                                    $st = strtolower((string)($log['status'] ?? ''));
                                    $stBadge = $st === 'success' 
                                        ? 'background: rgba(16,185,129,0.15); color: #34d399; border: 1px solid rgba(16,185,129,0.3);' 
                                        : 'background: rgba(239,68,68,0.15); color: #f87171; border: 1px solid rgba(239,68,68,0.3);';
                                    $stIcon = $st === 'success' ? 'fa-check' : 'fa-triangle-exclamation';
                                    $size = isset($log['backup_size']) && $log['backup_size'] !== null
                                        ? number_format((int)$log['backup_size'] / 1024, 1) . ' KB'
                                        : '—';
                                ?>
                                <tr>
                                    <td style="text-align: center; font-size: 11px; color: var(--text2); font-family: var(--font-mono, monospace);">
                                        #<?php echo (int)($log['id'] ?? 0); ?>
                                    </td>
                                    <td style="text-align: center;">
                                        <span class="badge" style="<?php echo $stBadge; ?> /* was: 10px */ font-size: var(--font-size-xs); padding: 2px 6px; font-weight: 700;">
                                            <i class="fa-solid <?php echo $stIcon; ?>" style="font-size: 9px; margin-right: 2px;"></i>
                                            <?php echo htmlspecialchars((string)($log['status'] ?? '')); ?>
                                        </span>
                                    </td>
                                    <td style="font-size: 11px; color: var(--text2); text-transform: uppercase; font-weight: 600;">
                                        <?php echo htmlspecialchars((string)($log['source'] ?? 'Manual')); ?>
                                    </td>
                                    <td style="/* was: 11.5px */ font-size: var(--font-size-sm); color: var(--text); white-space: normal; line-height: 1.3; overflow-wrap: break-word;">
                                        <div style="font-family: var(--font-mono, monospace); font-weight: 600;"><?php echo htmlspecialchars((string)($log['backup_file'] ?? '—')); ?></div>
                                        <?php if (!empty($log['notes'])): ?>
                                        <div style="/* was: 10.5px */ font-size: var(--font-size-xs); color: var(--text2); margin-top: 2px;"><?php echo htmlspecialchars((string)$log['notes']); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td style="font-size: 11px; color: var(--text2); font-family: var(--font-mono, monospace);">
                                        <?php echo $size; ?>
                                    </td>
                                    <td style="font-size: 11px; color: var(--text2); white-space: nowrap;">
                                        <?php echo htmlspecialchars((string)($log['created_at'] ?? '')); ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Right: Backup Control & Disaster Recovery Dossier -->
        <div class="split-right" style="height: 100%; display: flex; flex-direction: column;">
            <div class="panel" style="flex: 1; display: flex; flex-direction: column; min-height: 0;">
                <div class="panel-header" style="padding: 10px 14px;">
                    <div class="panel-title">
                        <i class="fa-solid fa-server" style="color: var(--accent);"></i>
                        <span>Disaster Recovery &amp; Backups</span>
                    </div>
                    <span style="font-size: 11px; color: var(--text2); font-weight: 500;">
                        System Integrity
                    </span>
                </div>

                <div class="panel-body" style="padding: 14px; display: flex; flex-direction: column; gap: 12px; flex: 1; overflow-y: auto;">
                    
                    <!-- Manual Trigger Panel -->
                    <div style="background: var(--color-surface); border: 1px solid var(--border); border-radius: 10px; padding: 14px;">
                        <div style="font-weight: 700; color: var(--accent); font-size: var(--font-size-sm); margin-bottom: 4px; display: flex; align-items: center; gap: 6px;">
                            <i class="fa-solid fa-floppy-disk"></i> Manual Archive Triggers
                        </div>
                        <p style="font-size: var(--font-size-sm); color: var(--text2); margin: 0 0 12px 0; line-height: 1.4;">
                            Generate complete database and parish asset archives on demand.
                        </p>

                        <form id="form-manual-backup" method="POST" onsubmit="return false;" style="display: flex; flex-direction: column; gap: 8px; margin: 0;">
                            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf ?? ($_SESSION['csrf'] ?? '')); ?>">
                            <button type="button" class="btn btn-primary" onclick="triggerBackupKind('daily')" style="width: 100%; justify-content: center; padding: 10px; font-weight: 600;">
                                <i class="fa-solid fa-database"></i> Create Daily Archive Now
                            </button>
                            <button type="button" class="btn btn-ghost" onclick="triggerBackupKind('weekly')" style="width: 100%; justify-content: center; padding: 9px; font-size: var(--font-size-sm); border: 1px solid var(--border);">
                                <i class="fa-solid fa-calendar-week"></i> Save Weekly Restore Point
                            </button>
                            <span id="backup-runner-state" style="font-size: var(--font-size-xs); color: var(--text2); text-align: center; font-style: italic;"></span>
                        </form>
                        <div id="backup-runner-result" style="font-size: var(--font-size-sm); margin-top: 8px;"></div>
                    </div>

                    <!-- Storage Integrity Badge -->
                    <div style="background: var(--bg2); border: 1px solid var(--border); border-radius: 8px; padding: 10px 12px; display: flex; align-items: center; justify-content: space-between; gap: 10px; font-size: 11.5px; color: var(--text2);">
                        <div style="display: flex; align-items: center; gap: 7px;">
                            <i class="fa-solid fa-shield-check" style="color: var(--accent); font-size: 13px;"></i>
                            <span>Non-destructive archives &bull; Safe for live production</span>
                        </div>
                        <span class="badge" style="background: var(--surface); color: var(--text2); border: 1px solid var(--border); font-size: 10.5px; padding: 2px 7px; border-radius: 999px;">
                            Retention: 25 Max
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.btn-table-action {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    border-radius: 6px;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid var(--border);
    color: var(--text2);
    cursor: pointer;
    transition: all 0.15s ease;
    /* was: 11px */ font-size: var(--font-size-xs);
    text-decoration: none;
}
.btn-table-action:hover {
    background: var(--accent);
    /* was: #000 */ color: var(--color-bg);
    border-color: var(--accent);
    transform: translateY(-1px);
}
</style>

<script>
if (typeof window.triggerBackupKind !== 'function') {
    window.triggerBackupKind = async function triggerBackupKind(kind) {
        const form = document.getElementById('form-manual-backup');
        const state = document.getElementById('backup-runner-state');
        const result = document.getElementById('backup-runner-result');
        const buttons = form ? form.querySelectorAll('button') : [];
        if (!form || !state || !result) return false;

        buttons.forEach(b => b.disabled = true);
        state.textContent = kind === 'weekly' ? 'Generating weekly restore snapshot...' : 'Compiling database and archive...';
        result.textContent = '';
        result.style.color = 'var(--text2)';

        try {
            const fd = new FormData(form);
            fd.set('backup_kind', kind);
            const response = await fetch('<?php echo htmlspecialchars($backupEndpoint, ENT_QUOTES); ?>', {
                method: 'POST',
                body: fd
            });

            let data = {};
            const text = await response.text();
            try { data = JSON.parse(text); } catch(e) {
                throw new Error('Server returned invalid response: ' + text.substring(0, 200));
            }

            if (!response.ok || !data.ok) {
                throw new Error(data.error || 'Backup operation failed.');
            }

            result.style.color = '#10b981';
            const label = kind === 'weekly' ? 'Weekly Restore Point Created' : 'Daily Backup Archive Completed';
            result.innerHTML = `<i class="fa-solid fa-circle-check"></i> ${label}: <strong>${data.file_name}</strong> (${Math.round((data.size || 0) / 1024)} KB)`;
            state.textContent = 'Archive ready.';

            setTimeout(() => {
                if (typeof navigateToSection === 'function') {
                    navigateToSection('backups', false);
                } else {
                    window.location.reload();
                }
            }, 2000);
        } catch (err) {
            result.style.color = '#ef4444';
            result.innerHTML = '<i class="fa-solid fa-triangle-exclamation"></i> ';
            result.appendChild(document.createTextNode(err && err.message ? err.message : 'Backup failed.'));
            state.textContent = 'Failed.';
        } finally {
            buttons.forEach(b => b.disabled = false);
        }
        return false;
    };
}

if (typeof window.restoreFromBackup !== 'function') {
    window.restoreFromBackup = async function restoreFromBackup(fileName) {
        const sure = window.confirm(
            `CRITICAL WARNING: Are you sure you want to restore the system to this backup point?\n\n${fileName}\n\nThis will completely overwrite current active database tables and storage files.`
        );
        if (!sure) return;

        const state = document.getElementById('backup-runner-state');
        const result = document.getElementById('backup-runner-result');
        if (state) state.textContent = 'Executing full system restore...';
        if (result) {
            result.textContent = '';
            result.style.color = 'var(--text2)';
        }

        try {
            const fd = new FormData();
            fd.set('csrf', '<?php echo htmlspecialchars($csrf ?? ($_SESSION['csrf'] ?? '')); ?>');
            fd.set('backup_file', fileName);
            fd.set('restore_storage', '1');

            const response = await fetch('<?php echo htmlspecialchars($restoreEndpoint, ENT_QUOTES); ?>', { method: 'POST', body: fd });
            const data = await response.json();
            if (!response.ok || !data.ok) {
                throw new Error(data.error || 'Restore failed.');
            }

            if (result) {
                result.style.color = '#10b981';
                result.textContent = `System successfully restored from ${fileName}.`;
            }
            if (state) state.textContent = 'Restore finished.';

            setTimeout(() => {
                if (typeof navigateToSection === 'function') {
                    navigateToSection('backups', false);
                } else {
                    window.location.reload();
                }
            }, 2000);
        } catch (err) {
            if (result) {
                result.style.color = '#ef4444';
                result.textContent = err && err.message ? err.message : 'Restore failed.';
            }
            if (state) state.textContent = 'Restore failed.';
        }
    };
}
</script>
