<!-- ═══════════════════════════════════════════════════════════════ -->
<!-- block html dis ->: SERVICE & SUPPORT TICKETS                   -->
<!-- ═══════════════════════════════════════════════════════════════ -->
<link rel="stylesheet" href="assets/css/workspace.css?v=2.5">

<style>
/* Local style overrides for Service & Support Tickets */
.workspace .stat-card .stat-label {
    font-size: var(--fs-2xs);
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--text2);
    font-family: var(--font-body);
}
.workspace .stat-card .stat-value {
    font-size: var(--fs-2xl);
    font-weight: 700;
    margin-top: 2px;
    font-family: var(--font-display);
}
.tickets-filter-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
    padding: 10px 14px;
    background: var(--panel-bg);
    border: var(--panel-border);
    border-radius: var(--panel-radius);
}
</style>

<div class="workspace workspace-fluid" style="gap: 10px;">
    <!-- ── Workspace Header ── -->
    <div class="workspace-header page-header-bar" style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border); padding-bottom: 8px; margin-bottom: 6px; flex-wrap: wrap; gap: 10px;">
        <div style="display: flex; align-items: center; gap: 12px;">
            <div class="page-header-icon" style="width: 38px; height: 38px; border-radius: 9px; background: rgba(201,168,76,0.14); color: var(--accent); display: flex; align-items: center; justify-content: center; font-size: 17px; flex-shrink: 0;">
                <i class="fa-solid fa-ticket"></i>
            </div>
            <div>
                <h1 class="page-title" style="font-family: var(--font-family, 'Inter', sans-serif); font-size: var(--font-page-title, 20px); font-weight: 700; color: var(--text); margin: 0; line-height: 1.2;">
                    <?php echo $isMember ? 'My Inbox & Support' : 'Service & Support Tickets'; ?>
                </h1>
                <p class="page-subtitle" style="font-size: var(--font-page-subtitle, 14px); color: var(--text2); margin: 3px 0 0 0;">
                    <?php echo $isMember ? 'Track your service requests, merchandise reservations, and support inquiries.' : 'Manage maintenance requests, technical support, and operational tasks.'; ?>
                </p>
            </div>
        </div>
        <div style="display: flex; align-items: center; gap: 10px;">
            <button class="btn btn-primary btn-sm" onclick="openModal('modal-new-ticket')">
                <i class="fa-solid fa-plus"></i> <?php echo $isMember ? 'New Request' : 'New Ticket'; ?>
            </button>
        </div>
    </div>

    <!-- ── Stats Row ── -->
    <div class="stats-grid-4">
        <div class="stat-card sc-red">
            <div class="stat-label">Open</div>
            <div class="stat-value color-red"><?php echo number_format($status_counts['Open'] ?? 0); ?></div>
        </div>
        <div class="stat-card sc-blue">
            <div class="stat-label">In Progress</div>
            <div class="stat-value color-blue"><?php echo number_format($status_counts['In Progress'] ?? 0); ?></div>
        </div>
        <div class="stat-card sc-amber">
            <div class="stat-label">Pending Info</div>
            <div class="stat-value color-amber"><?php echo number_format($status_counts['Pending Information'] ?? 0); ?></div>
        </div>
        <div class="stat-card sc-green">
            <div class="stat-label">Resolved</div>
            <div class="stat-value color-green"><?php echo number_format($status_counts['Resolved'] ?? 0); ?></div>
        </div>
    </div>

    <!-- ── Horizontal Search & Filter Bar (100% Full Width) ── -->
    <div class="tickets-filter-bar">
        <form method="GET" style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap; flex: 1; margin: 0;">
            <input type="hidden" name="section" value="tickets">

            <div style="flex: 1; min-width: 220px; position: relative;">
                <i class="fa-solid fa-magnifying-glass" style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); font-size: 11px; color: var(--text2, #737373); pointer-events: none; z-index: 2;"></i>
                <input type="text" name="ticket_search" placeholder="Search by subject, description, or #..." 
                       value="<?php echo htmlspecialchars($_GET['ticket_search'] ?? ''); ?>"
                       style="width: 100%; height: 32px; padding-left: 32px !important; padding-right: <?php echo !empty($_GET['ticket_search']) ? '26px' : '10px'; ?> !important; font-size: 0.85rem; border-radius: 6px; border: 1px solid var(--border); background: var(--bg); color: var(--text); box-sizing: border-box;">
                <?php if (!empty($_GET['ticket_search'])): ?>
                <a href="dashboard.php?section=tickets<?php echo !empty($_GET['ticket_status']) ? '&amp;ticket_status='.urlencode($_GET['ticket_status']) : ''; ?><?php echo !empty($_GET['ticket_priority']) ? '&amp;ticket_priority='.urlencode($_GET['ticket_priority']) : ''; ?>"
                   title="Clear search"
                   style="position: absolute; right: 8px; top: 50%; transform: translateY(-50%); color: var(--text2); text-decoration: none; font-size: 14px; line-height: 1;">&times;</a>
                <?php endif; ?>
            </div>

            <div style="display: flex; align-items: center; gap: 6px;">
                <label style="font-size: 0.78rem; font-weight: 600; color: var(--text2);">Status:</label>
                <select name="ticket_status" onchange="this.form.submit()"
                        style="padding: 6px 10px; font-size: 0.85rem; border-radius: 6px; border: 1px solid var(--border); background: var(--bg); color: var(--text);">
                    <option value="">All Statuses</option>
                    <option value="Open" <?php echo ($_GET['ticket_status'] ?? '') === 'Open' ? 'selected' : ''; ?>>Open</option>
                    <option value="In Progress" <?php echo ($_GET['ticket_status'] ?? '') === 'In Progress' ? 'selected' : ''; ?>>In Progress</option>
                    <option value="Pending Information" <?php echo ($_GET['ticket_status'] ?? '') === 'Pending Information' ? 'selected' : ''; ?>>Pending Information</option>
                    <option value="Resolved" <?php echo ($_GET['ticket_status'] ?? '') === 'Resolved' ? 'selected' : ''; ?>>Resolved</option>
                    <option value="Closed" <?php echo ($_GET['ticket_status'] ?? '') === 'Closed' ? 'selected' : ''; ?>>Closed</option>
                </select>
            </div>

            <div style="display: flex; align-items: center; gap: 6px;">
                <label style="font-size: 0.78rem; font-weight: 600; color: var(--text2);">Priority:</label>
                <select name="ticket_priority" onchange="this.form.submit()"
                        style="padding: 6px 10px; font-size: 0.85rem; border-radius: 6px; border: 1px solid var(--border); background: var(--bg); color: var(--text);">
                    <option value="">All Priorities</option>
                    <option value="Urgent" <?php echo ($_GET['ticket_priority'] ?? '') === 'Urgent' ? 'selected' : ''; ?>>Urgent</option>
                    <option value="High" <?php echo ($_GET['ticket_priority'] ?? '') === 'High' ? 'selected' : ''; ?>>High</option>
                    <option value="Medium" <?php echo ($_GET['ticket_priority'] ?? '') === 'Medium' ? 'selected' : ''; ?>>Medium</option>
                    <option value="Low" <?php echo ($_GET['ticket_priority'] ?? '') === 'Low' ? 'selected' : ''; ?>>Low</option>
                </select>
            </div>

            <button type="submit" class="btn btn-primary btn-sm">
                <i class="fa-solid fa-magnifying-glass"></i> Search
            </button>

            <?php if (!empty($_GET['ticket_search']) || !empty($_GET['ticket_status']) || !empty($_GET['ticket_priority'])): ?>
                <a href="dashboard.php?section=tickets" class="btn btn-ghost btn-sm" style="color: #ef4444;" title="Reset filters">
                    <i class="fa-solid fa-times"></i> Clear
                </a>
            <?php endif; ?>
        </form>

        <div style="display: flex; align-items: center; gap: 8px; font-size: 0.78rem; color: var(--text2); border-left: 1px solid var(--border); padding-left: 12px;">
            <i class="fa-solid fa-lightbulb" style="color: var(--accent);"></i>
            <span>Click row to view thread &bull; Changing status sends SMS</span>
        </div>
    </div>

    <!-- ── Full Width Support Tickets Panel (100% Width) ── -->
    <div class="panel" style="flex: 1; display: flex; flex-direction: column; min-height: 0; margin: 0;">
        <div class="panel-header">
            <div class="panel-title">
                <i class="fa-solid fa-ticket" style="color: var(--accent);"></i>
                <span><?php echo $isMember ? 'My Support Requests' : 'Support Tickets Queue'; ?></span>
            </div>
            <?php if (!empty($tickets)): ?>
                <span class="badge badge-neutral" style="font-size: 11px;">
                    <?php echo count($tickets); ?> tickets
                </span>
            <?php endif; ?>
        </div>

        <div class="panel-body" style="padding: 0; overflow-y: auto; overflow-x: auto; flex: 1; min-height: 0;">
            <?php if (empty($tickets)): ?>
                <div style="padding: 60px 20px; text-align: center; color: var(--text2);">
                    <i class="fa-solid fa-ticket" style="font-size: 48px; opacity: 0.25; margin-bottom: 12px; display: block;"></i>
                    <div style="font-size: 16px; font-weight: 600; color: var(--text); margin-bottom: 4px;">No Tickets Found</div>
                    <div style="font-size: 13px;"><?php echo $isMember ? 'You have no support requests' : 'No tickets matching your criteria in the queue'; ?></div>
                </div>
            <?php else: ?>
            <div class="table-container" style="border: none; margin: 0; width: 100%;">
                <table class="data-table" style="width: 100%; min-width: 820px; table-layout: fixed; border-collapse: collapse;">
                    <thead style="position: sticky; top: 0; background: var(--panel-header-bg, var(--bg3)); z-index: 5;">
                        <tr>
                            <th style="width: 90px;">Ticket #</th>
                            <th style="width: auto;">Subject</th>
                            <th style="width: 110px;">Category</th>
                            <th style="width: 95px;">Priority</th>
                            <th style="width: 120px;">Status</th>
                            <?php if (!$isMember): ?><th style="width: 120px;">Assigned To</th><?php endif; ?>
                            <th style="width: 160px; text-align: center;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($tickets as $ticket): 
                            $priorityColors = [
                                'Urgent' => '#dc2626',
                                'High' => '#dc2626',
                                'Medium' => '#c5a059',
                                'Low' => '#64748b'
                            ];
                            $priorityColor = $priorityColors[$ticket['priority']] ?? '#64748b';
                            $statusColors = [
                                'Open' => '#dc2626',
                                'In Progress' => '#c5a059',
                                'Pending Information' => '#c5a059',
                                'Resolved' => '#16a34a',
                                'Closed' => '#64748b'
                            ];
                            $statusColor = $statusColors[$ticket['status']] ?? '#64748b';
                        ?>
                        <tr style="cursor: pointer; transition: background 0.15s; border-bottom: 1px solid var(--border);">
                            <td style="font-weight:700; color:var(--accent); font-family:var(--font-mono, monospace); font-size:12px;">#<?php echo htmlspecialchars($ticket['ticket_no']); ?></td>
                            <td style="white-space: normal;">
                                <div style="font-weight:600; font-size:12px; color:var(--text); margin-bottom:3px; line-height: 1.3; overflow-wrap: break-word;"><?php echo htmlspecialchars($ticket['subject']); ?></div>
                                <?php if (!$isMember && (!empty($ticket['requester_name']) || !empty($ticket['requester_phone']))): ?>
                                    <div style="font-size:11px; color:var(--text2); overflow-wrap: break-word;">
                                        <i class="fa-solid fa-user" style="margin-right:4px;"></i><?php echo htmlspecialchars($ticket['requester_name'] ?: 'Requester'); ?>
                                        <?php if (!empty($ticket['requester_phone'])): ?>
                                            <span style="margin-left:8px;"><i class="fa-solid fa-phone" style="margin-right:4px;"></i><?php echo htmlspecialchars($ticket['requester_phone']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge badge-neutral" style="font-size:11px;"><?php echo htmlspecialchars($ticket['category']); ?></span></td>
                            <td>
                                <span class="badge badge-<?php echo strtolower($ticket['priority']); ?>" style="font-size:11px;">
                                    <span class="priority-dot <?php echo strtolower($ticket['priority']); ?>" style="margin-right:4px;"></span>
                                    <?php echo htmlspecialchars($ticket['priority']); ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge" style="background: <?php echo $statusColor; ?>15; color: <?php echo $statusColor; ?>; border: 1px solid <?php echo $statusColor; ?>30; font-size: 11px; padding: 4px 10px;">
                                    <?php echo htmlspecialchars($ticket['status']); ?>
                                </span>
                            </td>
                            <?php if (!$isMember): ?><td style="font-size:13px; color:var(--text2);"><?php echo htmlspecialchars($ticket['assigned_user'] ?? 'Unassigned'); ?></td><?php endif; ?>
                            <td style="text-align: center;">
                                <div class="btn-group" style="justify-content: center;">
                                    <?php if (!$isMember): ?>
                                    <form method="POST" action="dashboard.php?section=tickets" class="ticket-status-form" style="display:inline-flex; gap:4px; align-items:center;">
                                        <input type="hidden" name="form_section" value="tickets">
                                        <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
                                        <input type="hidden" name="action" value="update_status">
                                        <input type="hidden" name="ticket_id" value="<?php echo (int)$ticket['ticket_id']; ?>">
                                        <select name="status" class="ticket-select" style="min-width: 110px; height: 30px; font-size: 11px; padding: 3px 6px; border-radius: 4px; border: 1px solid var(--border); background: var(--bg); color: var(--text);">
                                            <?php foreach (['Open', 'In Progress', 'Pending Information', 'Resolved', 'Closed'] as $ticketStatus): ?>
                                                <option value="<?php echo htmlspecialchars($ticketStatus); ?>" <?php echo ($ticket['status'] === $ticketStatus) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($ticketStatus); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button class="btn-sm btn-ghost" style="color:var(--accent); height: 30px; width: 30px; padding: 0;" type="submit" title="Save status">
                                            <i class="fa-solid fa-check"></i>
                                        </button>
                                    </form>
                                    <?php endif; ?>

                                    <button class="btn-sm btn-ghost" title="View Details" onclick="viewTicket(<?php echo $ticket['ticket_id']; ?>)" style="border: 1px solid var(--border);">
                                        <i class="fa-solid fa-eye" style="color: var(--accent);"></i>
                                    </button>
                                    
                                    <?php if (!$isMember): ?>
                                    <button class="btn-sm btn-ghost" style="color:var(--text); border: 1px solid var(--border);" title="Send Report" onclick="openSendReportModal(<?php echo $ticket['ticket_id']; ?>, '<?php echo $ticket['ticket_no']; ?>')">
                                        <i class="fa-solid fa-paper-plane"></i>
                                    </button>
                                    <?php endif; ?>
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
</div>

<!-- Scripts for Ticketing UI -->
<script>
function viewTicket(id) {
    if (typeof window.viewTicket === 'function') {
        window.viewTicket(id);
    }
}

function openSendReportModal(id, ticketNo) {
    document.getElementById('send-report-ticket-id').value = id;
    document.getElementById('send-report-no').innerText = ticketNo;
    openModal('modal-send-ticket');
}
</script>