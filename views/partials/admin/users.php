<!-- ═══════════════════════════════════════════════════════════════ -->
<!-- block html dis ->: MANAGE USERS (MAX-DATA EXPANDED VIEW)          -->
<!-- ═══════════════════════════════════════════════════════════════ -->
<link rel="stylesheet" href="assets/css/workspace.css?v=2.5">
<style>
#role-filter-tabs .tab-btn {
    padding: 7px 16px;
    border-radius: 999px;
    border: 1px solid var(--border, #E5E5E5);
    background: var(--panel-bg, #FFFFFF);
    color: var(--text2, #737373);
    font-size: 12.5px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.15s ease;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    line-height: 1.2;
    min-height: 34px;
    box-sizing: border-box;
}
#role-filter-tabs .tab-btn:hover {
    color: var(--text, #111111);
    border-color: var(--text2, #737373);
}
#role-filter-tabs .tab-btn.active {
    background: #111111 !important;
    color: #FFFFFF !important;
    border-color: #111111 !important;
    font-weight: 700;
    box-shadow: 0 1px 3px rgba(0,0,0,0.12);
}
[data-theme="dark"] #role-filter-tabs .tab-btn {
    background: rgba(255, 255, 255, 0.05);
    border-color: rgba(255, 255, 255, 0.14);
    color: #cbd5e1;
}
[data-theme="dark"] #role-filter-tabs .tab-btn:hover {
    background: rgba(255, 255, 255, 0.1);
    color: #ffffff;
    border-color: rgba(255, 255, 255, 0.28);
}
[data-theme="dark"] #role-filter-tabs .tab-btn.active {
    background: #FFFFFF !important;
    color: #111111 !important;
    border-color: #FFFFFF !important;
    font-weight: 700;
    box-shadow: 0 2px 8px rgba(0,0,0,0.3);
}
[data-theme="dark"] #role-filter-tabs .tab-btn.active i {
    color: #111111 !important;
}

/* ─── UNIFIED ACTION BUTTONS: SYMMETRIC, PROPORTIONATE & ALIGNED ─── */
.user-row-actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 4px;
    margin: 0;
    padding: 0;
    flex-wrap: nowrap;
    white-space: nowrap;
}
.user-row-actions > form {
    display: contents !important;
}
.user-action-btn {
    width: 28px !important;
    height: 28px !important;
    min-width: 28px !important;
    min-height: 28px !important;
    max-width: 28px !important;
    max-height: 28px !important;
    border-radius: 6px !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    padding: 0 !important;
    margin: 0 !important;
    font-size: 11.5px !important;
    line-height: 1 !important;
    box-sizing: border-box !important;
    cursor: pointer !important;
    transition: all 0.15s ease !important;
    background: #FFFFFF !important;
    border: 1px solid #E5E5E5 !important;
    color: #111111 !important;
    vertical-align: middle !important;
    flex-shrink: 0 !important;
}
.user-action-btn i {
    font-size: 11px !important;
    line-height: 1 !important;
    pointer-events: none;
}
.user-action-btn:hover {
    background: #F5F5F5 !important;
    border-color: #D4D4D4 !important;
}
/* Quick Approve & Restore: Green Accent */
.user-action-btn.btn-approve,
.user-action-btn.btn-restore {
    border-color: #2E9E5B !important;
    color: #2E9E5B !important;
    background: #FFFFFF !important;
}
.user-action-btn.btn-approve:hover,
.user-action-btn.btn-restore:hover {
    background: #E8F8EE !important;
    border-color: #2E9E5B !important;
    color: #1E7B42 !important;
}
/* Quick Decline: Red Accent */
.user-action-btn.btn-decline {
    border-color: #D64545 !important;
    color: #D64545 !important;
    background: #FFFFFF !important;
}
.user-action-btn.btn-decline:hover {
    background: #FEECEC !important;
    border-color: #D64545 !important;
    color: #B91C1C !important;
}
/* Standard Action: View Profile */
.user-action-btn.btn-view {
    color: #404040 !important;
    border-color: #E5E5E5 !important;
}
.user-action-btn.btn-view:hover {
    background: #F5F5F5 !important;
    color: #111111 !important;
    border-color: #C5A059 !important;
}
/* Standard Action: Edit Details */
.user-action-btn.btn-edit {
    color: #404040 !important;
    border-color: #E5E5E5 !important;
}
.user-action-btn.btn-edit:hover {
    background: #F5F5F5 !important;
    color: #111111 !important;
    border-color: #C5A059 !important;
}
/* Standard Action: Delete User */
.user-action-btn.btn-delete {
    color: #D64545 !important;
    border-color: #E5E5E5 !important;
}
.user-action-btn.btn-delete:hover {
    background: #FEECEC !important;
    border-color: #D64545 !important;
    color: #B91C1C !important;
}
/* Vertical divider between verification decisions and standard actions */
.user-action-divider {
    width: 1px !important;
    height: 18px !important;
    background: #E5E5E5 !important;
    margin: 0 3px !important;
    display: inline-block !important;
    flex-shrink: 0 !important;
}
/* Dark theme support */
[data-theme="dark"] .user-action-btn {
    background: #1E293B !important;
    border-color: #334155 !important;
    color: #E2E8F0 !important;
}
[data-theme="dark"] .user-action-btn:hover {
    background: #334155 !important;
    border-color: #475569 !important;
}
[data-theme="dark"] .user-action-btn.btn-approve,
[data-theme="dark"] .user-action-btn.btn-restore {
    background: rgba(46, 158, 91, 0.12) !important;
    border-color: #2E9E5B !important;
    color: #4ADE80 !important;
}
[data-theme="dark"] .user-action-btn.btn-approve:hover,
[data-theme="dark"] .user-action-btn.btn-restore:hover {
    background: rgba(46, 158, 91, 0.25) !important;
}
[data-theme="dark"] .user-action-btn.btn-decline,
[data-theme="dark"] .user-action-btn.btn-delete {
    background: rgba(214, 69, 69, 0.1) !important;
    border-color: rgba(214, 69, 69, 0.4) !important;
    color: #F87171 !important;
}
[data-theme="dark"] .user-action-btn.btn-decline:hover,
[data-theme="dark"] .user-action-btn.btn-delete:hover {
    background: rgba(214, 69, 69, 0.22) !important;
    border-color: #EF4444 !important;
}
[data-theme="dark"] .user-action-divider {
    background: #334155 !important;
}
</style>

<div class="workspace" style="display: flex; flex-direction: column; gap: 12px; height: 100%; min-height: 0; box-sizing: border-box;">
    
    <!-- ─── UNIFIED HEADER & CONTROLS (ROW 1: TITLE + STAT BADGES PINNED TOP-RIGHT) ─── -->
    <?php $pendingCount = $pending_count ?? ($user_stats['pending_count'] ?? 0); ?>
    <div class="page-header-bar" style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border); padding-bottom: 8px; flex-shrink: 0; flex-wrap: wrap; gap: 10px; margin-bottom: 6px;">
        <div style="display: flex; align-items: center; gap: 12px;">
            <div class="page-header-icon" style="width: 38px; height: 38px; border-radius: 9px; background: rgba(201,168,76,0.14); color: var(--accent); display: flex; align-items: center; justify-content: center; font-size: 17px; flex-shrink: 0;">
                <i class="fa-solid fa-users-gear"></i>
            </div>
            <div>
                <h1 class="page-title" style="font-family: var(--font-family, 'Inter', sans-serif); font-size: var(--font-page-title, 20px); font-weight: 700; color: var(--text); margin: 0; line-height: 1.2;">
                    Users
                </h1>
                <p class="page-subtitle" style="font-size: var(--font-page-subtitle, 14px); color: var(--text2); margin: 3px 0 0 0;">Parish user accounts, role-based access control, and membership verification</p>
            </div>
        </div>
        <div style="display: flex; align-items: center; gap: 8px;">
            <span class="badge" style="background: var(--bg3, #F4F4F5); border: 1px solid var(--border); color: var(--text2); font-size: 11.5px; font-weight: 600; padding: 4px 12px; border-radius: 999px;">
                <?php echo number_format($user_stats['total_users'] ?? count($users)); ?> Users
            </span>
            <?php if ($pendingCount > 0): ?>
            <span class="badge badge-warning" style="font-size: 11.5px; font-weight: 600; padding: 4px 10px; border-radius: 999px; cursor: pointer;" onclick="document.querySelector('#role-filter-tabs button[data-filter=\'pending\']')?.click()">
                <i class="fa-solid fa-clock"></i> <?php echo number_format($pendingCount); ?> Pending
            </span>
            <?php endif; ?>
            <button type="button" class="btn btn-primary btn-sm" onclick="openModal('modal-add-user')" style="height: 32px; font-weight: 600; font-size: 11.5px; display: inline-flex; align-items: center; gap: 6px; background: #111111; color: #FFFFFF; border: 1px solid #111111; border-radius: 6px; margin-left: 6px;">
                <i class="fa-solid fa-user-plus text-gold"></i> New User
            </button>
        </div>
    </div>

    <!-- ─── EXPANDED MAIN USER TABLE CARD (MAX DATA VIEW) ─── -->
    <div class="panel" style="border-radius: 12px; overflow: hidden; background: var(--panel-bg); border: 1px solid var(--border); box-shadow: none; display: flex; flex-direction: column; flex: 1; min-height: 0;">
        
        <!-- Compact Filter & Search Toolbar -->
        <div style="padding: 12px 16px; border-bottom: 1px solid var(--border); display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 10px; background: var(--panel-bg); flex-shrink: 0;">
            
            <div style="display: flex; align-items: center; gap: 8px; flex: 1; min-width: 260px; max-width: 420px;">
                <!-- Universal Sort Toggle Button -->
                <button type="button" class="btn-sort-toggle" onclick="toggleUserSort(this)"
                        title="Sorted: Name A-Z (Click to sort Z-A)"
                        style="height: 32px; width: 34px; min-width: 34px; padding: 0; display: inline-flex; align-items: center; justify-content: center; border-radius: 6px; border: 1px solid var(--border); background: var(--bg2, #FAFAFA); color: var(--text); cursor: pointer; transition: all 0.15s ease; flex-shrink: 0;" aria-label="Toggle sort order">
                    <i class="fa-solid fa-arrow-down-a-z" style="font-size: 13px;"></i>
                </button>

                <!-- Search Input with Proper Left Padding and Clear Button -->
                <div style="position: relative; flex: 1;">
                    <i class="fa-solid fa-magnifying-glass" style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: var(--text2, #737373); font-size: 11px; pointer-events: none; z-index: 2;"></i>
                    <input type="text" id="user-table-search" placeholder="Search users by name, username, or email..." oninput="filterUserTable()" 
                           style="width: 100%; height: 32px; padding-left: 32px !important; padding-right: 24px !important; background: var(--bg2, #FFFFFF); border: 1px solid var(--border); border-radius: 6px; color: var(--text, #111111); font-size: 12px; outline: none; box-sizing: border-box;">
                    <span id="user-clear-search" onclick="clearUserSearch()" title="Clear search" style="display: none; position: absolute; right: 8px; top: 50%; transform: translateY(-50%); cursor: pointer; color: var(--text2); font-size: 14px; line-height: 1;">&times;</span>
                </div>
            </div>

            <!-- Compact Role & Status Filter Pills (Segmented Capsule) -->
            <div style="display: inline-flex; align-items: center; gap: 4px; background: var(--bg3, #F4F4F5); padding: 3px; border-radius: 999px; border: 1px solid var(--border); flex-wrap: wrap;" id="role-filter-tabs">
                <button type="button" class="tab-btn active" data-filter="" onclick="setRoleFilter('', this)">
                    All (<?php echo number_format(count($users)); ?>)
                </button>
                <button type="button" class="tab-btn" data-filter="recent" onclick="setRoleFilter('recent', this)" title="Show accounts registered in the last 30 days">
                    <i class="fa-solid fa-clock-rotate-left"></i> Recently Added
                </button>
                <?php if ($pendingCount > 0): ?>
                <button type="button" class="tab-btn" data-filter="pending" onclick="setRoleFilter('pending', this)">
                    <i class="fa-solid fa-clock"></i> Pending (<?php echo number_format($pendingCount); ?>)
                </button>
                <?php endif; ?>
                <button type="button" class="tab-btn" data-filter="member" onclick="setRoleFilter('member', this)">
                    Members
                </button>
                <button type="button" class="tab-btn" data-filter="staff" onclick="setRoleFilter('staff', this)">
                    Staff
                </button>
                <button type="button" class="tab-btn" data-filter="priest" onclick="setRoleFilter('priest', this)">
                    Priests
                </button>
                <button type="button" class="tab-btn" data-filter="admin" onclick="setRoleFilter('admin', this)">
                    Admins
                </button>
            </div>
        </div>

        <div style="overflow-y: auto; overflow-x: auto; flex: 1; width: 100%; min-height: 0;">
            <table class="data-table" id="user-management-table" style="width: 100%; min-width: 820px; table-layout: fixed; border-collapse: collapse;">
                <thead style="position: sticky; top: 0; z-index: 10;">
                    <tr style="background: var(--panel-header-bg); border-bottom: 1px solid var(--border);">
                        <th style="width: 50px; text-align: center; padding: 10px 12px; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.04em; color: var(--text2);">ID</th>
                        <th style="width: auto; padding: 10px 12px; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.04em; color: var(--text2);">User Profile</th>
                        <th style="width: 28%; min-width: 210px; padding: 10px 12px; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.04em; color: var(--text2);">Role &amp; Chapel Assignment</th>
                        <th style="width: 200px; min-width: 200px; text-align: right; padding: 10px 14px; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.04em; color: var(--text2);">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="4" style="padding: 50px 20px; text-align: center; color: #737373;">
                                <i class="fa-solid fa-users-slash" style="font-size: 36px; opacity: 0.3; margin-bottom: 12px; display: block; color: #737373;"></i>
                                <div style="font-size: 15px; font-weight: 600; color: #111111; margin-bottom: 4px;">No Users Found</div>
                                <div style="font-size: 13px;">Click <strong>+ New User</strong> to add your first account.</div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($users as $u): ?>
                        <?php
                        $status = strtolower($u['status'] ?? 'active');
                        $isPending = ($status === 'pending');
                        $isRejected = ($status === 'rejected');

                        $cname = '—';
                        if (!empty($u['chapel_id'])) {
                            foreach($chapels as $ch) {
                                if($ch['chapel_id'] == $u['chapel_id']) { $cname = htmlspecialchars($ch['chapel_name']); break; }
                            }
                        }
                        $rowFilter = strtolower($u['role'] . ' ' . $status . ' ' . $u['user'] . ' ' . ($u['full_name'] ?? '') . ' ' . ($u['email'] ?? ''));
                        ?>
                        <tr class="user-table-row" style="border-bottom: 1px solid var(--border); transition: background 0.15s; <?php echo $isPending ? 'background: rgba(201,168,76,0.06);' : ''; ?>" 
                            data-id="<?php echo (int)$u['id']; ?>"
                            data-created="<?php echo !empty($u['created_at']) ? strtotime($u['created_at']) : 0; ?>"
                            data-name="<?php echo strtolower(htmlspecialchars(($u['full_name'] ?? '') . ' ' . $u['user'])); ?>"
                            data-status-filter="<?php echo htmlspecialchars($rowFilter); ?>"
                            data-role="<?php echo strtolower($u['role']); ?>"
                            data-status="<?php echo $status; ?>">
                            <td style="text-align: center; color: #737373; font-size: 11px; font-family: monospace; font-weight: 600; padding: 10px 12px;">#<?php echo $u['id']; ?></td>
                            <td style="padding: 10px 12px; white-space: normal;">
                                <div style="display: flex; align-items: center; gap: 12px; overflow-wrap: break-word;">
                                    <div style="width: 34px; height: 34px; border-radius: 50%; background: #F6F6F6; border: 1px solid #E5E5E5; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-weight: 600; color: #111111; font-size: 13px;">
                                        <?php echo strtoupper(substr($u['user'], 0, 1)); ?>
                                    </div>
                                    <div>
                                        <div style="font-weight: 600; font-size: 0.9rem; color: #111111; line-height: 1.2; display: flex; align-items: center; gap: 6px; flex-wrap: wrap;">
                                            <span><?php echo htmlspecialchars($u['full_name'] ?: $u['user']); ?></span>
                                            <?php if ($isPending): ?>
                                                <span class="badge" style="background: #F6F6F6; color: #737373; border: 1px solid #E5E5E5; font-size: 11px; padding: 2px 7px; border-radius: 6px; font-weight: 600; text-transform: uppercase;">
                                                    <i class="fa-solid fa-clock"></i> Pending
                                                </span>
                                            <?php elseif ($isRejected): ?>
                                                <span class="badge" style="background: #FFFFFF; color: #D64545; border: 1px solid #D64545; font-size: 11px; padding: 2px 7px; border-radius: 6px; font-weight: 600; text-transform: uppercase;">
                                                    <i class="fa-solid fa-ban"></i> Declined
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <div style="font-size: 11.5px; color: #737373; margin-top: 2px; display: flex; align-items: center; gap: 6px; flex-wrap: wrap;">
                                            <span style="font-weight: 600; color: #111111;">@<?php echo htmlspecialchars($u['user']); ?></span>
                                            <span>·</span>
                                            <span><i class="fa-regular fa-envelope" style="margin-right: 3px;"></i><?php echo htmlspecialchars($u['email'] ?? 'No email'); ?></span>
                                            <?php if (!empty($u['phone'])): ?>
                                                <span>·</span>
                                                <span><i class="fa-solid fa-phone" style="margin-right: 3px;"></i><?php echo htmlspecialchars($u['phone']); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td style="padding: 10px 12px;">
                                <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                                    <span class="badge" style="background: #F6F6F6; color: #111111; border: 1px solid #E5E5E5; font-size: 11.5px; padding: 3px 8px; border-radius: 6px; font-weight: 600; text-transform: uppercase;">
                                        <?php echo htmlspecialchars($u['role']); ?>
                                    </span>
                                    <?php if ($cname !== '—'): ?>
                                        <span style="font-size: 11.5px; color: #737373; display: flex; align-items: center; gap: 4px; background: #FFFFFF; padding: 2px 8px; border-radius: 4px; border: 1px solid #E5E5E5;">
                                            <i class="fa-solid fa-location-dot" style="color: var(--accent); font-size: 11px;"></i> <?php echo $cname; ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td style="text-align: right; padding: 8px 14px;">
                                <div class="user-row-actions">
                                    <?php if ($isPending): ?>
                                        <!-- Quick Approve Button (Protected POST) -->
                                        <form method="POST" action="dashboard.php?section=users" onsubmit="return confirm('Verify and approve member account for @<?php echo addslashes(htmlspecialchars($u['user'])); ?>?')">
                                            <input type="hidden" name="form_section" value="users">
                                            <input type="hidden" name="action" value="approve">
                                            <input type="hidden" name="id" value="<?php echo (int)$u['id']; ?>">
                                            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf ?? ''); ?>">
                                            <button type="submit" class="user-action-btn btn-approve" title="Approve Registration">
                                                <i class="fa-solid fa-check"></i>
                                            </button>
                                        </form>
                                        <!-- Quick Decline Button (Protected POST) -->
                                        <form method="POST" action="dashboard.php?section=users" onsubmit="return confirm('Decline registration for @<?php echo addslashes(htmlspecialchars($u['user'])); ?>?')">
                                            <input type="hidden" name="form_section" value="users">
                                            <input type="hidden" name="action" value="reject">
                                            <input type="hidden" name="id" value="<?php echo (int)$u['id']; ?>">
                                            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf ?? ''); ?>">
                                            <button type="submit" class="user-action-btn btn-decline" title="Decline Registration">
                                                <i class="fa-solid fa-xmark"></i>
                                            </button>
                                        </form>
                                        <span class="user-action-divider"></span>
                                    <?php elseif ($isRejected): ?>
                                        <!-- Re-Approve Button (Protected POST) -->
                                        <form method="POST" action="dashboard.php?section=users" onsubmit="return confirm('Re-approve member account for @<?php echo addslashes(htmlspecialchars($u['user'])); ?>?')">
                                            <input type="hidden" name="form_section" value="users">
                                            <input type="hidden" name="action" value="approve">
                                            <input type="hidden" name="id" value="<?php echo (int)$u['id']; ?>">
                                            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf ?? ''); ?>">
                                            <button type="submit" class="user-action-btn btn-restore" title="Re-Approve Account">
                                                <i class="fa-solid fa-rotate-left"></i>
                                            </button>
                                        </form>
                                        <span class="user-action-divider"></span>
                                    <?php endif; ?>

                                    <?php
                                    $uViewData = [
                                        'id' => (int)$u['id'],
                                        'user' => $u['user'],
                                        'full_name' => $u['full_name'] ?? '',
                                        'email' => $u['email'] ?? '',
                                        'phone' => $u['phone'] ?? '',
                                        'status' => $u['status'] ?? 'active',
                                        'role' => $u['role'],
                                        'chapel_name' => $cname
                                    ];
                                    ?>
                                    <button type="button" 
                                            class="user-action-btn btn-view" 
                                            data-user="<?php echo htmlspecialchars(json_encode($uViewData, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>"
                                            onclick="openViewUserModal(this)" 
                                            title="View Profile">
                                        <i class="fa-solid fa-eye"></i>
                                    </button>
                                    <button type="button" 
                                            class="user-action-btn btn-edit" 
                                            data-id="<?php echo (int)$u['id']; ?>"
                                            data-user="<?php echo htmlspecialchars($u['user'], ENT_QUOTES, 'UTF-8'); ?>"
                                            data-role="<?php echo htmlspecialchars($u['role'], ENT_QUOTES, 'UTF-8'); ?>"
                                            data-email="<?php echo htmlspecialchars($u['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                            data-chapel-id="<?php echo htmlspecialchars($u['chapel_id'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                            data-fullname="<?php echo htmlspecialchars($u['full_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                            onclick="openEditUserModal(this)" 
                                            title="Edit User">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </button>
                                    <button type="button" 
                                            class="user-action-btn btn-delete" 
                                            data-id="<?php echo (int)$u['id']; ?>"
                                            data-username="<?php echo htmlspecialchars($u['user'], ENT_QUOTES, 'UTF-8'); ?>"
                                            onclick="submitUserAction('delete', this)" 
                                            title="Delete User">
                                        <i class="fa-solid fa-trash-can"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination Footer -->
        <?php if (($total_pages ?? 0) > 1): ?>
        <div style="padding: 8px 16px; border-top: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; background: rgba(255,255,255,0.02); flex-shrink: 0;">
            <div>
                <?php
                $cp = max(1, (int) ($current_page ?? 1));
                $tp = max(1, (int) ($total_pages ?? 1));
                $base = '?section=users';
                ?>
                <?php if ($cp > 1): ?>
                    <a href="<?php echo $base; ?>&page=<?php echo $cp - 1; ?>" class="btn btn-gray btn-sm" style="padding: 4px 12px; border-radius: 6px; font-size: 11px;">
                        <i class="fa-solid fa-arrow-left"></i> Previous
                    </a>
                <?php endif; ?>
            </div>
            <span style="font-size: 0.8rem; color: var(--text2); font-weight: 600;">Page <?php echo $cp; ?> of <?php echo $tp; ?></span>
            <div>
                <?php if ($cp < $tp): ?>
                    <a href="<?php echo $base; ?>&page=<?php echo $cp + 1; ?>" class="btn btn-gray btn-sm" style="padding: 4px 12px; border-radius: 6px; font-size: 11px;">
                        Next <i class="fa-solid fa-arrow-right"></i>
                    </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- ─── SLEEK MODULE GUIDELINES FOOTER BANNER ─── -->
    <div style="background: rgba(255,255,255,0.02); border: 1px dashed var(--border); border-radius: 10px; padding: 8px 16px; display: flex; flex-wrap: wrap; align-items: center; justify-content: space-around; gap: 12px; font-size: 0.8rem; color: var(--text2); flex-shrink: 0;">
        <div style="display: flex; align-items: center; gap: 6px;">
            <i class="fa-solid fa-shield-halved" style="color: var(--accent); font-size: 12px;"></i>
            <span>Staff &amp; Chapel Admins require an assigned chapel location.</span>
        </div>
        <div style="display: flex; align-items: center; gap: 6px;">
            <i class="fa-solid fa-key" style="color: var(--accent); font-size: 12px;"></i>
            <span>Passwords are securely hashed using bcrypt prior to database storage.</span>
        </div>
        <div style="display: flex; align-items: center; gap: 6px;">
            <i class="fa-solid fa-user-lock" style="color: var(--accent); font-size: 12px;"></i>
            <span>Self-account deletion is disabled to prevent lockout.</span>
        </div>
    </div>
</div>

<!-- VIEW USER MODAL -->
<div class="modal-overlay" id="modal-view-user">
    <div class="modal" style="max-width: 460px; padding: 0; overflow: hidden; border-radius: 12px; background: #FFFFFF; border: 1px solid #E5E5E5; box-shadow: none;">
        <div id="view-user-header-band" style="background: #FFFFFF; padding: 20px 24px; border-bottom: 1px solid #E5E5E5; position: relative;">
            <button class="modal-close" onclick="closeModal('modal-view-user')" style="position: absolute; top: 16px; right: 16px; border: none; background: transparent; font-size: 18px; cursor: pointer; color: #737373;">×</button>
            <div style="display: flex; align-items: center; gap: 16px;">
                <div id="view-user-avatar" style="width: 52px; height: 52px; background: #F6F6F6; color: #111111; border: 1px solid #E5E5E5; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 20px; flex-shrink: 0;">
                    <i class="fa-solid fa-user"></i>
                </div>
                <div style="min-width: 0;">
                    <div style="font-family: var(--font-family, 'Inter', sans-serif); font-size: 16px; font-weight: 600; color: #111111; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" id="view-user-fullname-text">User Name</div>
                    <div style="font-size: 12px; color: #737373; margin-top: 2px;" id="view-user-handle-text">@username</div>
                    <div id="view-user-role-badge" class="badge" style="margin-top: 6px; display: inline-flex; font-size: 11.5px; padding: 3px 10px; background: #F6F6F6; color: #111111; border: 1px solid #E5E5E5;">Role</div>
                </div>
            </div>
        </div>
        <div style="padding: 20px 24px; background: #FFFFFF;">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                <div>
                    <div style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: #737373; margin-bottom: 4px;">User ID</div>
                    <div id="view-user-id-text" style="font-weight: 600; color: #111111; font-size: 13px;">#0</div>
                </div>
                <div>
                    <div style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: #737373; margin-bottom: 4px;">Assigned Role</div>
                    <div id="view-user-role-text" style="font-weight: 600; color: #111111; font-size: 13px;">—</div>
                </div>
                <div style="grid-column: span 2;">
                    <div style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: #737373; margin-bottom: 4px;">Email Address</div>
                    <div id="view-user-email-text" style="font-weight: 400; color: #111111; font-size: 13px; word-break: break-all;">—</div>
                </div>
                <div>
                    <div style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: #737373; margin-bottom: 4px;">Contact #</div>
                    <div id="view-user-phone-text" style="font-weight: 600; color: #111111; font-size: 13px;">—</div>
                </div>
                <div>
                    <div style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: #737373; margin-bottom: 4px;">Account Status</div>
                    <div id="view-user-status-text" style="font-weight: 600; color: #111111; font-size: 13px;">—</div>
                </div>
                <div style="grid-column: span 2; padding-top: 10px; border-top: 1px solid #E5E5E5;">
                    <div style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: #737373; margin-bottom: 4px;">Chapel / Branch</div>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <i class="fa-solid fa-location-dot" style="color: var(--accent); font-size: 12px;"></i>
                        <span id="view-user-chapel-text" style="font-weight: 600; color: #111111; font-size: 13px;">Main Cathedral</span>
                    </div>
                </div>
            </div>
        </div>
        <div style="padding: 12px 24px 16px; display: flex; justify-content: flex-end; background: #FFFFFF; border-top: 1px solid #E5E5E5;">
            <button class="btn btn-ghost" onclick="closeModal('modal-view-user')" style="background: #FFFFFF; border: 1px solid #E5E5E5; color: #111111; padding: 6px 14px; border-radius: 6px; font-size: 12px; cursor: pointer;">Close</button>
        </div>
    </div>
</div>

<script>
function openViewUserModal(u) {
    if (u instanceof HTMLElement) {
        const raw = u.getAttribute('data-user');
        if (raw) {
            try { u = JSON.parse(raw); } catch (e) { console.error('Error parsing user data:', e); return; }
        }
    }
    if (!u) return;

    const idEl = document.getElementById('view-user-id-text');
    if (idEl) idEl.textContent = '#' + u.id;
    const fnEl = document.getElementById('view-user-fullname-text');
    if (fnEl) fnEl.textContent = u.full_name || u.user;
    const handleEl = document.getElementById('view-user-handle-text');
    if (handleEl) handleEl.textContent = '@' + u.user;
    const emailEl = document.getElementById('view-user-email-text');
    if (emailEl) emailEl.textContent = u.email || 'No email registered';
    const phoneEl = document.getElementById('view-user-phone-text');
    if (phoneEl) phoneEl.textContent = u.phone || 'No phone registered';
    const roleEl = document.getElementById('view-user-role-text');
    if (roleEl) roleEl.textContent = u.role;
    const chapelEl = document.getElementById('view-user-chapel-text');
    if (chapelEl) chapelEl.textContent = u.chapel_name || 'Main Cathedral';

    const statusElem = document.getElementById('view-user-status-text');
    const st = (u.status || 'active').toLowerCase();
    statusElem.textContent = st.toUpperCase();
    if (st === 'pending') {
        statusElem.style.color = '#c5a059';
    } else if (st === 'rejected') {
        statusElem.style.color = '#dc2626';
    } else {
        statusElem.style.color = '#16a34a';
    }

    const badge = document.getElementById('view-user-role-badge');
    badge.textContent = u.role;
    const roleColors = {
        'Admin':           'background:rgba(197,160,89,0.18);color:var(--accent);border:1px solid rgba(197,160,89,0.4);',
        'Cathedral Admin': 'background:rgba(197,160,89,0.15);color:var(--accent);border:1px solid rgba(197,160,89,0.3);',
        'Chapel Admin':    'background:rgba(197,160,89,0.12);color:var(--accent);border:1px solid rgba(197,160,89,0.25);',
        'Staff':           'background:rgba(22,163,74,0.15);color:#16a34a;border:1px solid rgba(22,163,74,0.3);',
        'Priest':          'background:rgba(197,160,89,0.15);color:var(--accent);border:1px solid rgba(197,160,89,0.3);',
        'Pastor':          'background:rgba(197,160,89,0.15);color:var(--accent);border:1px solid rgba(197,160,89,0.3);',
        'Member':          'background:rgba(148,163,184,0.12);color:#94a3b8;border:1px solid rgba(148,163,184,0.2);',
    };
    badge.style.cssText = (roleColors[u.role] || roleColors['Member']) + 'border-radius:999px;font-size:11.5px;font-weight:700;letter-spacing:0.06em;text-transform:uppercase;padding:4px 12px;display:inline-flex;';

    openModal('modal-view-user');
}

let currentRoleFilter = '';
let _userSortAsc = true;

function clearUserSearch() {
    const input = document.getElementById('user-table-search');
    if (input) {
        input.value = '';
        applyUserFilters();
        input.focus();
    }
}

function setRoleFilter(filter, btn) {
    currentRoleFilter = (filter || '').toLowerCase().trim();
    document.querySelectorAll('#role-filter-tabs .tab-btn').forEach(b => b.classList.remove('active'));
    if (btn) btn.classList.add('active');
    applyUserFilters();
}

function filterUserTable() {
    applyUserFilters();
}

function applyUserFilters() {
    const input = document.getElementById('user-table-search');
    const filter = input ? input.value.toLowerCase().trim() : '';
    const clearBtn = document.getElementById('user-clear-search');
    if (clearBtn) {
        clearBtn.style.display = filter ? 'block' : 'none';
    }

    const table = document.querySelector('#user-management-table');
    if (!table) return;
    const tr = table.getElementsByTagName('tr');

    for (let i = 1; i < tr.length; i++) {
        const row = tr[i];
        const role = (row.getAttribute('data-role') || '').toLowerCase();
        const status = (row.getAttribute('data-status') || '').toLowerCase();
        const rowText = (row.getAttribute('data-status-filter') || '').toLowerCase();

        let matchesRole = true;
        if (currentRoleFilter === 'recent') {
            const created = parseInt(row.getAttribute('data-created') || '0', 10);
            const thirtyDaysAgo = Math.floor(Date.now() / 1000) - (30 * 86400);
            // Accounts created in the last 30 days, or recent pending/new accounts
            matchesRole = (created >= thirtyDaysAgo) || (status === 'pending');
        } else if (currentRoleFilter === 'pending') {
            matchesRole = (status === 'pending');
        } else if (currentRoleFilter === 'member') {
            matchesRole = (role === 'member');
        } else if (currentRoleFilter === 'staff') {
            matchesRole = (role === 'staff');
        } else if (currentRoleFilter === 'priest') {
            matchesRole = (role === 'priest' || role === 'pastor');
        } else if (currentRoleFilter === 'admin') {
            matchesRole = (role.indexOf('admin') > -1);
        } else if (currentRoleFilter !== '') {
            matchesRole = (role === currentRoleFilter);
        }

        let matchesSearch = true;
        if (filter !== '') {
            matchesSearch = (rowText.indexOf(filter) > -1);
        }

        row.style.display = (matchesRole && matchesSearch) ? "" : "none";
    }

    if (currentRoleFilter === 'recent') {
        const tbody = document.querySelector('#user-management-table tbody');
        if (tbody) {
            const rows = Array.from(tbody.querySelectorAll('.user-table-row'));
            rows.sort((a, b) => {
                const cA = parseInt(a.getAttribute('data-created') || a.getAttribute('data-id') || '0', 10);
                const cB = parseInt(b.getAttribute('data-created') || b.getAttribute('data-id') || '0', 10);
                return cB - cA; // Newest first
            });
            rows.forEach(r => tbody.appendChild(r));
        }
    }
}

function toggleUserSort(btn) {
    _userSortAsc = !_userSortAsc;
    const icon = btn.querySelector('i');
    if (icon) {
        icon.className = _userSortAsc ? 'fa-solid fa-arrow-down-a-z' : 'fa-solid fa-arrow-down-z-a';
    }
    btn.title = _userSortAsc ? 'Sorted: Name A-Z (Click to sort Z-A)' : 'Sorted: Name Z-A (Click to sort A-Z)';

    const tbody = document.querySelector('#user-management-table tbody');
    if (!tbody) return;
    const rows = Array.from(tbody.querySelectorAll('.user-table-row'));
    rows.sort((a, b) => {
        const nameA = (a.dataset.name || '').toLowerCase();
        const nameB = (b.dataset.name || '').toLowerCase();
        return _userSortAsc ? nameA.localeCompare(nameB) : nameB.localeCompare(nameA);
    });
    rows.forEach(r => tbody.appendChild(r));
}

function submitUserAction(action, idOrEl, username) {
    let id = idOrEl;
    if (idOrEl instanceof HTMLElement) {
        id = idOrEl.getAttribute('data-id');
        username = idOrEl.getAttribute('data-username');
        if (!action) action = idOrEl.getAttribute('data-action') || 'delete';
    }

    let msg = 'Are you sure?';
    if (action === 'approve') msg = 'Verify and approve member account for @' + username + '?';
    else if (action === 'reject') msg = 'Decline registration for @' + username + '?';
    else if (action === 'delete') msg = 'Delete user @' + username + '? This action cannot be undone.';
    
    if (!confirm(msg)) return;
    
    if (action === 'approve' || action === 'reject') {
        window.location.href = 'dashboard.php?section=users&action=' + encodeURIComponent(action) + '&id=' + encodeURIComponent(id);
        return;
    }
    
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'dashboard.php';
    
    const sec = document.createElement('input');
    sec.type = 'hidden'; sec.name = 'form_section'; sec.value = 'users'; form.appendChild(sec);
    
    const act = document.createElement('input');
    act.type = 'hidden'; act.name = 'action'; act.value = action; form.appendChild(act);
    
    const idInput = document.createElement('input');
    idInput.type = 'hidden'; idInput.name = 'id'; idInput.value = id; form.appendChild(idInput);
    
    const csrfInput = document.createElement('input');
    csrfInput.type = 'hidden'; csrfInput.name = 'csrf'; csrfInput.value = '<?php echo $csrf; ?>'; form.appendChild(csrfInput);
    
    document.body.appendChild(form);
    form.submit();
}

window.openViewUserModal = openViewUserModal;
window.submitUserAction = submitUserAction;
</script>