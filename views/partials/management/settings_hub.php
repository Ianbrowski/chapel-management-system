<?php
/**
 * System Settings & Administration Hub
 * Unified container for Users, Chapels, Services, Parish Profile, and Backups.
 */
$currentSettingsTab = $settings_tab ?? ($active_section ?? 'settings');
if ($currentSettingsTab === 'settings') {
    $currentSettingsTab = $settings_tab ?? ($can_manage_users ? 'users' : ($can_manage_chapels ? 'chapels' : 'parish_info'));
}

// Compute live governance summary stats for the info bar
$userStats = $user_stats ?? [];
if (!empty($userStats)) {
    $totalUsersCount = (int)($userStats['total_users'] ?? count($users ?? []));
    $memberUsersCount = (int)($userStats['member_count'] ?? 0);
    $pendingUsersCount = (int)($pending_count ?? ($userStats['pending_count'] ?? 0));
} else {
    $isGlobalAdminHub = in_array($role ?? '', ['Admin', 'Cathedral Admin'], true);
    $hubChapelId = !$isGlobalAdminHub && !empty($chapel_id) ? (int)$chapel_id : null;
    $userWhere = $hubChapelId !== null ? " WHERE chapel_id = {$hubChapelId}" : "";
    $uRes = $conn->query("SELECT COUNT(*) as total, SUM(CASE WHEN role='Member' THEN 1 ELSE 0 END) as members, SUM(CASE WHEN status='pending' THEN 1 ELSE 0 END) as pending FROM users{$userWhere}");
    if ($uRes && $uRow = $uRes->fetch_assoc()) {
        $totalUsersCount = (int)$uRow['total'];
        $memberUsersCount = (int)$uRow['members'];
        $pendingUsersCount = (int)$uRow['pending'];
    } else {
        $totalUsersCount = count($users ?? []);
        $memberUsersCount = 0;
        $pendingUsersCount = (int)($pending_count ?? 0);
    }
}

$chapelsCount = isset($chapels) ? count($chapels) : 0;
if ($chapelsCount === 0) {
    $cRes = $conn->query("SELECT COUNT(*) as c FROM chapels");
    if ($cRes && $cRow = $cRes->fetch_assoc()) {
        $chapelsCount = (int)$cRow['c'];
    }
}
?>

<div class="settings-hub-container" style="display: flex; flex-direction: column; gap: 14px; width: 100%; max-width: 1680px; margin: 0 auto; box-sizing: border-box; padding: 14px clamp(16px, 1.8vw, 24px) 24px;">
    
    <!-- ─── 1. TOP HEADER BAR: TITLE + INFORMATIONAL STATS BADGES (TOP-RIGHT) ─── -->
    <div class="settings-hub-header page-header-bar" style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border); padding-bottom: 8px; margin-bottom: 6px; flex-wrap: wrap; gap: 10px;">
        <div style="display: flex; align-items: center; gap: 12px;">
            <div class="page-header-icon" style="width: 38px; height: 38px; border-radius: 9px; background: rgba(201,168,76,0.14); color: var(--accent); display: flex; align-items: center; justify-content: center; font-size: 17px; flex-shrink: 0;">
                <i class="fa-solid fa-gears"></i>
            </div>
            <div>
                <h1 class="page-title" style="font-family: var(--font-family, 'Inter', sans-serif); font-size: var(--font-page-title, 20px); font-weight: 700; color: var(--text); margin: 0; line-height: 1.2;">
                    Settings
                </h1>
                <p class="page-subtitle" style="font-size: var(--font-page-subtitle, 14px); color: var(--text2); margin: 3px 0 0 0;">
                    Parish configuration, user accounts, branches, catalog, and maintenance
                </p>
            </div>
        </div>

        <!-- Right Side: Stat Badges (always top-right) -->
        <div style="display: flex; align-items: center; gap: 6px;">
            <?php if ($currentSettingsTab === 'reports'): ?>
                <span class="badge" style="background: var(--bg3, #F4F4F5); border: 1px solid var(--border); color: var(--text2); font-size: 11.5px; font-weight: 600; padding: 4px 12px; border-radius: 999px;">
                    <?php echo number_format($stats['total_events'] ?? 0); ?> Events
                </span>
                <?php if (!empty($stats['pending'])): ?>
                <span class="badge badge-warning" style="font-size: 11.5px; font-weight: 600; padding: 4px 10px; border-radius: 999px;">
                    <?php echo number_format($stats['pending']); ?> Pending
                </span>
                <?php endif; ?>
            <?php else: ?>
                <span class="badge" style="background: var(--bg3, #F4F4F5); border: 1px solid var(--border); color: var(--text2); font-size: 11.5px; font-weight: 600; padding: 4px 12px; border-radius: 999px;">
                    <?php echo $totalUsersCount; ?> Accounts
                </span>
                <?php if ($pendingUsersCount > 0): ?>
                <span class="badge badge-warning" style="font-size: 11.5px; font-weight: 600; padding: 4px 10px; border-radius: 999px;" title="<?php echo $pendingUsersCount; ?> registration(s) pending review">
                    <?php echo $pendingUsersCount; ?> Pending
                </span>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- ─── 2. SUB-ROW: SEGMENTED MODULE TABS + CONTEXTUAL ACTION BUTTON ─── -->
    <div class="settings-subnav-bar" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; margin-top: 2px; margin-bottom: 6px;">
        <!-- Left: Segmented Module Navigation Tabs -->
        <div class="settings-tabs-bar">
            <?php if ($can_manage_users): ?>
            <a href="dashboard.php?section=users" 
               class="settings-tab-btn settings-tab-link <?php echo ($currentSettingsTab === 'users') ? 'active' : ''; ?>"
               data-section="users">
                <i class="fa-solid fa-users-gear"></i>
                <span>Users</span>
                <?php if (!empty($pendingUsersCount)): ?>
                    <span class="badge-count"><?php echo (int)$pendingUsersCount; ?></span>
                <?php endif; ?>
            </a>
            <?php endif; ?>

            <?php if ($can_manage_chapels): ?>
            <a href="dashboard.php?section=chapels" 
               class="settings-tab-btn settings-tab-link <?php echo ($currentSettingsTab === 'chapels') ? 'active' : ''; ?>"
               data-section="chapels">
                <i class="fa-solid fa-place-of-worship"></i>
                <span><?php echo ($role === 'Chapel Admin') ? 'My Chapel Profile' : 'Chapels'; ?></span>
            </a>
            <?php endif; ?>

            <?php if ($can_manage_services): ?>
            <a href="dashboard.php?section=services" 
               class="settings-tab-btn settings-tab-link <?php echo ($currentSettingsTab === 'services') ? 'active' : ''; ?>"
               data-section="services">
                <i class="fa-solid fa-hand-holding-heart"></i>
                <span>Services</span>
            </a>
            <?php endif; ?>

            <?php if ($can_parish_info): ?>
            <a href="dashboard.php?section=parish_info" 
               class="settings-tab-btn settings-tab-link <?php echo ($currentSettingsTab === 'parish_info') ? 'active' : ''; ?>"
               data-section="parish_info">
                <i class="fa-solid fa-church"></i>
                <span>Parish Profile</span>
            </a>
            <?php endif; ?>

            <?php if ($role === 'Admin'): ?>
            <a href="dashboard.php?section=backups" 
               class="settings-tab-btn settings-tab-link <?php echo ($currentSettingsTab === 'backups') ? 'active' : ''; ?>"
               data-section="backups">
                <i class="fa-solid fa-database"></i>
                <span>Backups</span>
            </a>
            <?php endif; ?>

            <?php if (!empty($can_reports) || !empty($can_finance_reports)): ?>
            <a href="dashboard.php?section=reports" 
               class="settings-tab-btn settings-tab-link <?php echo ($currentSettingsTab === 'reports') ? 'active' : ''; ?>"
               data-section="reports">
                <i class="fa-solid fa-file-invoice-dollar"></i>
                <span>Reports</span>
            </a>
            <?php endif; ?>
        </div>

        <!-- Right Side: Primary Action Button for active section -->
        <div class="settings-actions-bar" style="display: flex; align-items: center; gap: 8px;">
            <?php if ($currentSettingsTab === 'users' && $can_manage_users): ?>
                <button type="button" class="btn btn-primary btn-sm" onclick="openModal('modal-add-user')" style="height: 32px; min-height: 32px; font-weight: 600; font-size: 12px; display: inline-flex; align-items: center; gap: 6px; background: #111111; color: #FFFFFF; border: 1px solid #111111; border-radius: 999px; padding: 6px 16px; cursor: pointer; box-shadow: 0 1px 3px rgba(0,0,0,0.12);">
                    <i class="fa-solid fa-user-plus text-gold" style="font-size: 11px;"></i> New User
                </button>
            <?php elseif ($currentSettingsTab === 'chapels' && in_array($role, ['Admin', 'Cathedral Admin'])): ?>
                <button type="button" class="btn btn-primary btn-sm" onclick="openModal('modal-add-chapel')" style="height: 32px; min-height: 32px; font-weight: 600; font-size: 12px; display: inline-flex; align-items: center; gap: 6px; background: #111111; color: #FFFFFF; border: 1px solid #111111; border-radius: 999px; padding: 6px 16px; cursor: pointer; box-shadow: 0 1px 3px rgba(0,0,0,0.12);">
                    <i class="fa-solid fa-plus text-gold" style="font-size: 11px;"></i> Add Chapel
                </button>
            <?php elseif ($currentSettingsTab === 'services' && $can_manage_services): ?>
                <button class="btn btn-ghost btn-sm" onclick="window.location.reload();" title="Refresh Catalog" style="height: 32px; min-height: 32px; font-size: 12px; background: var(--surface, #FFFFFF); border: 1px solid var(--border); color: var(--text); border-radius: 999px; display: inline-flex; align-items: center; gap: 6px; padding: 6px 16px;">
                    <i class="fa-solid fa-arrows-rotate"></i> Refresh
                </button>
            <?php elseif ($currentSettingsTab === 'backups' && $role === 'Admin'): ?>
                <button type="button" class="btn btn-primary btn-sm" onclick="if(typeof triggerBackupKind==='function'){triggerBackupKind('daily');}else if(typeof triggerBackup==='function'){triggerBackup();}else{window.location.href='tools/backup.php';}" style="height: 32px; min-height: 32px; font-weight: 600; font-size: 12px; display: inline-flex; align-items: center; gap: 6px; background: #111111; color: #FFFFFF; border: 1px solid #111111; border-radius: 999px; padding: 6px 16px; cursor: pointer; box-shadow: 0 1px 3px rgba(0,0,0,0.12);">
                    <i class="fa-solid fa-database text-gold" style="font-size: 11px;"></i> Create Snapshot
                </button>
            <?php elseif ($currentSettingsTab === 'reports'): ?>
                <a href="views/pages/service_register_print.php?from=<?php echo urlencode($rfrom ?? date('Y-01-01')); ?>&to=<?php echo urlencode($rto ?? date('Y-m-d')); ?>"
                   target="_blank"
                   class="btn btn-primary btn-sm"
                   style="height: 32px; min-height: 32px; font-weight: 600; font-size: 12px; display: inline-flex; align-items: center; gap: 6px; background: #111111; color: #FFFFFF; border: 1px solid #111111; border-radius: 999px; padding: 6px 16px; cursor: pointer; box-shadow: 0 1px 3px rgba(0,0,0,0.12); text-decoration: none;">
                    <i class="fa-solid fa-book-bible text-gold" style="font-size: 11px;"></i> Service Register
                </a>
            <?php endif; ?>
        </div>
    </div>

    <style>
        /* Segmented Tabs Bar (Matching Reference Certificates Design) */
        .settings-tabs-bar {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: var(--bg3, #F4F4F5);
            padding: 3px;
            border-radius: 999px;
            border: 1px solid var(--border);
            flex-wrap: wrap;
        }
        .settings-tab-btn {
            border: 1px solid transparent;
            background: transparent;
            padding: 7px 16px;
            border-radius: 999px;
            font-size: 12.5px;
            font-weight: 500;
            color: var(--text2);
            text-decoration: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 7px;
            transition: all 0.18s ease;
            line-height: 1.2;
            min-height: 34px;
            box-sizing: border-box;
            white-space: nowrap;
        }
        .settings-tab-btn i {
            font-size: 12px;
            transition: color 0.18s ease;
        }
        .settings-tab-btn:hover {
            color: var(--text);
            background: var(--hover-bg, rgba(0,0,0,0.04));
        }
        .settings-tab-btn.active {
            background: #111111 !important;
            color: #FFFFFF !important;
            font-weight: 700;
            box-shadow: 0 1px 3px rgba(0,0,0,0.14);
        }
        .settings-tab-btn.active i {
            color: #FFFFFF !important;
        }
        .settings-tab-btn .badge-count {
            font-size: 10px;
            padding: 1px 6px;
            border-radius: 10px;
            font-weight: 700;
            background: var(--bg2, #E4E4E7);
            color: var(--text2, #71717A);
        }
        .settings-tab-btn.active .badge-count {
            background: rgba(255,255,255,0.2);
            color: #FFFFFF;
        }

        /* Suppress redundant duplicate headers in nested subviews */
        .settings-content-wrapper .page-header-bar,
        .settings-content-wrapper .workspace-header {
            display: none !important;
        }
        .settings-content-wrapper .workspace {
            padding: 0 !important;
            margin: 0 !important;
            gap: 10px !important;
            height: auto !important;
            min-height: 100% !important;
        }

        /* Dark Mode Overrides */
        [data-theme="dark"] .settings-tabs-bar {
            background: #18181b;
            border-color: rgba(255, 255, 255, 0.12);
        }
        [data-theme="dark"] .settings-tab-btn {
            color: #cbd5e1;
        }
        [data-theme="dark"] .settings-tab-btn:hover {
            color: #ffffff;
            background: rgba(255, 255, 255, 0.08);
        }
        [data-theme="dark"] .settings-tab-btn.active {
            background: #FFFFFF !important;
            color: #111111 !important;
            border-color: #FFFFFF !important;
            box-shadow: 0 2px 8px rgba(0,0,0,0.3);
        }
        [data-theme="dark"] .settings-tab-btn.active i {
            color: #111111 !important;
        }
        [data-theme="dark"] .settings-tab-btn.active .badge-count {
            background: rgba(0,0,0,0.25);
            color: #111111;
        }
    </style>

    <!-- ─── 3. ACTIVE TAB CONTENT (CLEAN MAX-DATA VIEW WITHOUT REPETITIVE HEADERS) ─── -->
    <div class="settings-content-wrapper" style="display: flex; flex-direction: column; flex: 1; min-height: 0; height: auto;">
        <?php
        switch ($currentSettingsTab) {
            case 'chapels':
                include BASE_PATH . '/views/partials/admin/chapels.php';
                break;
            case 'services':
                include BASE_PATH . '/views/partials/management/services.php';
                break;
            case 'parish_info':
                include BASE_PATH . '/views/partials/admin/parish_info.php';
                break;
            case 'backups':
                include BASE_PATH . '/views/partials/maintenance/backups.php';
                break;
            case 'reports':
                include BASE_PATH . '/views/partials/finance/reports.php';
                break;
            case 'users':
            default:
                if ($can_manage_users) {
                    include BASE_PATH . '/views/partials/admin/users.php';
                } elseif ($can_manage_chapels) {
                    include BASE_PATH . '/views/partials/admin/chapels.php';
                } elseif ($can_parish_info) {
                    include BASE_PATH . '/views/partials/admin/parish_info.php';
                } else {
                    echo "<div class='alert alert-warning'>You do not have permission to view this settings module.</div>";
                }
                break;
        }
        ?>
    </div>
</div>
