<?php
require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Chapel.php';
require_once __DIR__ . '/../models/AuditLogger.php';
require_once __DIR__ . '/../models/ParishInfo.php';

/**
 * AdminController — System administration (Users, Roles, Audit Logs, Chapels).
 */
class AdminController extends BaseController {
    private $userModel;
    private $chapelModel;
    private $parishModel;

    public function __construct($conn) {
        parent::__construct($conn);
        $this->userModel = new User($this->conn);
        $this->chapelModel = new Chapel($this->conn);
        $this->parishModel = new ParishInfo($this->conn);
    }

    public function handleRequest() {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if ($method !== 'POST') return;

        $section = $_POST['form_section'] ?? '';
        $action  = $_POST['action'] ?? '';
        
        if (!$this->validateCsrf()) {
            $this->flash('error', 'Security session expired. Please refresh.');
            $this->redirect('dashboard.php');
        }

        switch ($section) {
            case 'users':       $this->handleUserActions($action); break;
            case 'chapels':     $this->handleChapelActions($action); break;
            case 'parish_info': $this->handleParishInfoActions($action); break;
            case 'my_profile':  $this->updateProfile(); break;
        }
    }

    private function handleUserActions($action) {
        if (!$this->permissions['can_manage_users']) return;
        
        $id = (int)($_POST['id'] ?? 0);
        $user = trim((string)($_POST['user'] ?? ''));
        $role = trim((string)($_POST['role'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $pass = (string)($_POST['password'] ?? '');
        $fullName = isset($_POST['full_name']) ? trim((string)$_POST['full_name']) : null;
        $chapelId = !empty($_POST['chapel_id']) ? (int)$_POST['chapel_id'] : null;

        if (in_array($action, ['create', 'update'], true)) {
            $allowedRoles = ['Admin', 'Cathedral Admin', 'Chapel Admin', 'Staff', 'Priest', 'Member'];
            if (!in_array($role, $allowedRoles, true)) {
                $this->flash('error', 'Invalid role selected.');
                $this->redirect('dashboard.php?section=users');
                return;
            }
            if ($user === '') {
                $this->flash('error', 'Username cannot be empty.');
                $this->redirect('dashboard.php?section=users');
                return;
            }
            if (mb_strlen($user) < 3 || mb_strlen($user) > 50) {
                $this->flash('error', 'Username must be between 3 and 50 characters.');
                $this->redirect('dashboard.php?section=users');
                return;
            }
            if ($fullName !== null && mb_strlen($fullName) > 100) {
                $this->flash('error', 'Full Name cannot exceed 100 characters.');
                $this->redirect('dashboard.php?section=users');
                return;
            }
            if (!empty($_POST['email']) && mb_strlen((string)$_POST['email']) > 100) {
                $this->flash('error', 'Email address cannot exceed 100 characters.');
                $this->redirect('dashboard.php?section=users');
                return;
            }
            if ($action === 'create' && trim($pass) === '') {
                $this->flash('error', 'Password is required when creating a user.');
                $this->redirect('dashboard.php?section=users');
                return;
            }
            if (!empty($pass)) {
                if (mb_strlen($pass) < 6 || mb_strlen($pass) > 72) {
                    $this->flash('error', 'Password must be between 6 and 72 characters.');
                    $this->redirect('dashboard.php?section=users');
                    return;
                }
            }
        }

        if (in_array($role, ['Staff', 'Chapel Admin']) && !$chapelId) {
             $this->flash('error', 'Staff and Chapel Admins MUST be assigned to exactly one chapel. Assignment cannot be empty.');
             $this->redirect('dashboard.php?section=users');
             return;
        }

        // Security Guard: Cathedral Admin can manage staff, members, priests, and chapel admins,
        // but cannot create, elevate, modify, or delete root System Admin accounts.
        if ($this->role === 'Cathedral Admin') {
            if ($role === 'Admin') {
                $this->flash('error', 'Cathedral Admins cannot create or promote users to System Admin.');
                $this->redirect('dashboard.php?section=users');
                return;
            }
            if ($id > 0) {
                $target = $this->userModel->getById($id);
                if ($target && $target['role'] === 'Admin') {
                    $this->flash('error', 'Cathedral Admins cannot modify System Administrator accounts.');
                    $this->redirect('dashboard.php?section=users');
                    return;
                }
            }
        }

        $emailNormalized = $email !== '' ? strtolower(trim($email)) : null;
        if ($emailNormalized !== null && !filter_var($emailNormalized, FILTER_VALIDATE_EMAIL)) {
            $this->flash('error', 'Please enter a valid email address.');
            $this->redirect('dashboard.php?section=users');
            return;
        }
        if ($emailNormalized !== null && in_array($action, ['create', 'update'], true)) {
            if ($action === 'update' && $id > 0) {
                $chkEmail = $this->conn->prepare("SELECT id FROM users WHERE LOWER(TRIM(email)) = LOWER(?) AND (status IS NULL OR status != 'rejected') AND id != ? LIMIT 1");
                if ($chkEmail) {
                    $chkEmail->bind_param('si', $emailNormalized, $id);
                }
            } else {
                $chkEmail = $this->conn->prepare("SELECT id FROM users WHERE LOWER(TRIM(email)) = LOWER(?) AND (status IS NULL OR status != 'rejected') LIMIT 1");
                if ($chkEmail) {
                    $chkEmail->bind_param('s', $emailNormalized);
                }
            }
            if (!empty($chkEmail)) {
                $chkEmail->execute();
                $dupEmail = $chkEmail->get_result()->num_rows > 0;
                $chkEmail->close();
                if ($dupEmail) {
                    $this->flash('error', 'An active account with this email address already exists.');
                    $this->redirect('dashboard.php?section=users');
                    return;
                }
            }
        }

        switch ($action) {
            case 'create':
                if ($this->userModel->exists($user)) {
                    $this->flash('error', 'Username already exists.');
                } else {
                    $hashed = password_hash($pass, PASSWORD_BCRYPT, ['cost' => 12]);
                    if ($this->userModel->create($user, $hashed, $role, $email, $chapelId, $fullName)) {
                        if (!empty($_POST['force_password_change'])) {
                            $newUid = (int)$this->conn->insert_id;
                            if ($newUid > 0) $this->setMustChangePasswordFlag($newUid, true);
                        }
                        $newData = [
                            'username' => $user,
                            'role' => $role,
                            'email' => $email,
                            'chapel_id' => $chapelId,
                            'full_name' => $fullName
                        ];
                        AuditLogger::logFromSession($this->conn, 'created', 'Users', "Created user: {$user}", null, $newData);
                        $this->flash('success', 'User created.');
                    } else {
                        $this->flash('error', 'Failed to create user.');
                    }
                }
                break;
            case 'update':
                if ($this->userModel->exists($user, $id)) {
                    $this->flash('error', 'Username already exists. Please choose a different one.');
                    break;
                }
                
                $oldData = $this->userModel->getById($id);
                $hashed = !empty($pass) ? password_hash($pass, PASSWORD_BCRYPT, ['cost' => 12]) : null;
                
                if ($this->userModel->update($id, $user, $role, $hashed, $email, $chapelId, $fullName)) {
                    if ($hashed !== null && !empty($_POST['force_password_change'])) {
                        $this->setMustChangePasswordFlag($id, true);
                    }
                    $newData = [
                        'username' => $user,
                        'role' => $role,
                        'email' => $email,
                        'chapel_id' => $chapelId,
                        'full_name' => $fullName
                    ];
                    // Don't log the password hash in the audit trail for security
                    if ($oldData) unset($oldData['password']);
                    
                    AuditLogger::logFromSession($this->conn, 'updated', 'Users', "Updated user: {$user}", $oldData, $newData);
                    $this->flash('success', 'User updated.');
                } else {
                    $this->flash('error', 'Failed to update user.');
                }
                break;
            case 'approve':
                if (!$id) {
                    $this->flash('error', 'Invalid user ID.');
                    break;
                }
                $targetUser = $this->userModel->getById($id);
                if (!$targetUser) {
                    $this->flash('error', 'User not found.');
                    break;
                }
                if (($targetUser['role'] ?? '') !== 'Member') {
                    $this->flash('error', 'Only Member registrations can be approved through this queue.');
                    break;
                }
                if ($this->userModel->approveUser($id)) {
                    AuditLogger::logFromSession(
                        $this->conn,
                        'approved',
                        'Users',
                        "Verified and approved member account: @{$targetUser['user']} (" . ($targetUser['full_name'] ?: 'No Name') . ")",
                        ['status' => $targetUser['status'] ?? 'pending'],
                        ['status' => 'active']
                    );
                    $this->flash('success', "Member account @{$targetUser['user']} has been verified and approved.");
                } else {
                    $this->flash('error', 'Failed to approve member account.');
                }
                break;
            case 'reject':
                if (!$id) {
                    $this->flash('error', 'Invalid user ID.');
                    break;
                }
                $targetUser = $this->userModel->getById($id);
                if (!$targetUser) {
                    $this->flash('error', 'User not found.');
                    break;
                }
                if (($targetUser['role'] ?? '') !== 'Member') {
                    $this->flash('error', 'Only Member registrations can be declined through this queue.');
                    break;
                }
                if ($this->userModel->rejectUser($id)) {
                    AuditLogger::logFromSession(
                        $this->conn,
                        'rejected',
                        'Users',
                        "Declined member registration: @{$targetUser['user']} (" . ($targetUser['full_name'] ?: 'No Name') . ")",
                        ['status' => $targetUser['status'] ?? 'pending'],
                        ['status' => 'rejected']
                    );
                    $this->flash('success', "Member registration @{$targetUser['user']} has been declined.");
                } else {
                    $this->flash('error', 'Failed to decline member registration.');
                }
                break;
            case 'delete':
                if ($id === (int)$this->userId) {
                    $this->flash('error', 'Cannot delete: You cannot delete your own logged-in account.');
                    break;
                }
                try {
                    $oldData = $this->userModel->getById($id);
                    if ($this->userModel->delete($id)) {
                        if ($oldData) unset($oldData['password']);
                        AuditLogger::logFromSession($this->conn, 'deleted', 'Users', "Deleted user ID: {$id}", $oldData, null);
                        $this->flash('success', 'User deleted.');
                    } else {
                        $this->flash('error', 'Failed to delete user.');
                    }
                } catch (Exception $e) {
                    $this->flash('error', $e->getMessage());
                }
                break;
        }
        $this->redirect('dashboard.php?section=users');
    }

    private function handleChapelActions($action) {
        if (!$this->permissions['can_manage_chapels']) {
            $this->flash('error', 'Unauthorized: You do not have permission to manage chapels.');
            $this->redirect('dashboard.php');
            return;
        }
        $id       = (int)($_POST['id'] ?? 0);
        $name     = $_POST['chapel_name'] ?? '';
        $loc      = $_POST['location'] ?? '';
        $patron   = $_POST['patron_saint'] ?? '';
        $capacity = (int)($_POST['seating_capacity'] ?? 50);
        $coord    = $_POST['coordinator_name'] ?? '';
        $phone    = $_POST['coordinator_phone'] ?? '';
        $status   = $_POST['status'] ?? 'Active';
        $sundayServiceTime = $_POST['sunday_service_time'] ?? 'Check Parish Schedule';
        $themeColor = $_POST['theme_color'] ?? '#4f46e5';

        $smtp = [
            'host'       => $_POST['smtp_host'] ?? null,
            'port'       => !empty($_POST['smtp_port']) ? (int)$_POST['smtp_port'] : null,
            'user'       => $_POST['smtp_user'] ?? null,
            'pass'       => $_POST['smtp_pass'] ?? null,
            'secure'     => $_POST['smtp_secure'] ?? 'tls',
            'from_email' => $_POST['from_email'] ?? null,
            'from_name'  => $_POST['from_name'] ?? null
        ];

        if ($this->role === 'Chapel Admin') {
            if ($action !== 'update' || $this->chapelId === null || $id !== (int)$this->chapelId) {
                $this->flash('error', 'Chapel Admins can only update their assigned chapel.');
                $this->redirect('dashboard.php?section=chapels');
                return;
            }
        }

        // 1. Handle Chapel Image Upload
        $uploadError = '';
        $imagePath = $this->handleImageUpload('chapel_image', 'uploads/chapels/', 'chapel_', 5 * 1024 * 1024, $uploadError);

        // 2. Collect Multi-Fiestas
        $fiestas = [];
        $fNames  = $_POST['fiesta_names'] ?? [];
        $fMonths = $_POST['fiesta_months'] ?? [];
        $fDays   = $_POST['fiesta_days'] ?? [];
        foreach ($fNames as $i => $fn) {
            $month = $fMonths[$i] ?? '';
            $day   = $fDays[$i] ?? '';
            if (!empty($fn) && !empty($month) && !empty($day)) {
                $fiestas[] = ['name' => $fn, 'date' => "$month $day"];
            }
        }

        switch ($action) {
            case 'create':
                if ($this->chapelModel->create($name, $loc, $patron, $capacity, $coord, $phone, $fiestas, $sundayServiceTime, $themeColor, $imagePath, $smtp)) {
                    $newData = [
                        'name' => $name,
                        'location' => $loc,
                        'patron' => $patron,
                        'capacity' => $capacity,
                        'coordinator' => $coord,
                        'fiestas' => $fiestas,
                        'smtp_host' => $smtp['host']
                    ];
                    AuditLogger::logFromSession($this->conn, 'created', 'Chapels', "Created chapel: {$name}", null, $newData);
                    $this->flash('success', "Chapel added.{$uploadError}");
                } else {
                    $this->flash('error', 'Failed to add chapel.');
                }
                break;
            case 'update':
                $oldData = $this->chapelModel->getById($id);
                if ($oldData) {
                    $oldData['fiestas'] = $this->chapelModel->getFiestas($id);
                }
                
                if ($this->chapelModel->update($id, $name, $loc, $patron, $capacity, $coord, $phone, $status, $sundayServiceTime, $fiestas, $themeColor, $imagePath, $smtp)) {
                    $newData = [
                        'name' => $name,
                        'location' => $loc,
                        'status' => $status,
                        'fiestas' => $fiestas,
                        'smtp_host' => $smtp['host']
                    ];
                    AuditLogger::logFromSession($this->conn, 'updated', 'Chapels', "Updated chapel: {$name}", $oldData, $newData);
                    $this->flash('success', "Chapel updated.{$uploadError}");
                } else {
                    $this->flash('error', 'Failed to update chapel.');
                }
                break;
            case 'delete':
                try {
                    $oldData = $this->chapelModel->getById($id);
                    if ($this->chapelModel->delete($id)) {
                        AuditLogger::logFromSession($this->conn, 'deleted', 'Chapels', "Deleted chapel ID: {$id}", $oldData, null);
                        $this->flash('success', 'Chapel removed.');
                    } else {
                        $this->flash('error', 'Failed to remove chapel.');
                    }
                } catch (Exception $e) {
                    $this->flash('error', $e->getMessage());
                }
                break;
        }
        $this->redirect('dashboard.php?section=chapels');
    }

    private function handleParishInfoActions($action) {
        if (!$this->permissions['can_parish_info']) return;

        switch ($action) {
            case 'update_contact':
                $name = $_POST['parish_name'] ?? 'IFI Cathedral';
                $patron = $_POST['patron_saint'] ?? '';
                $cname = $_POST['coordinator_name'] ?? '';
                $cphone = $_POST['coordinator_phone'] ?? '';
                $addr = $_POST['address'] ?? '';
                $ph   = $_POST['phone'] ?? '';
                $em   = $_POST['email'] ?? '';
                $hrs  = $_POST['office_hours'] ?? '';
                $map  = trim($_POST['map_embed_url'] ?? '');
                if (preg_match('/src=["\']([^"\']+)["\']/i', $map, $mapMatches)) {
                    $map = trim($mapMatches[1]);
                }
                if ($map !== '') {
                    $parsedMap = parse_url($map);
                    if (!$parsedMap || strtolower((string)($parsedMap['scheme'] ?? '')) !== 'https' || empty($parsedMap['host'])) {
                        $this->flash('error', 'Map embed URL must be a valid HTTPS URL.');
                        $this->redirect('dashboard.php?section=parish_info');
                        return;
                    }
                }

                $uploadMsg = '';
                $imagePath = $this->handleImageUpload('cathedral_image', 'uploads/chapels/', 'cathedral_', 5 * 1024 * 1024, $uploadMsg);

                $oldData = $this->parishModel->getContactInfo();
                $targetInfoId = (int)($oldData['info_id'] ?? 1);
                $sql = "UPDATE parish_contact_info SET parish_name=?, address=?, phone=?, email=?, office_hours=?, map_embed_url=?, updated_by=?, patron_saint=?, coordinator_name=?, coordinator_phone=?";
                if ($imagePath) $sql .= ", cathedral_image=?";
                $sql .= " WHERE info_id=?";

                $stmt = $this->conn->prepare($sql);
                if (!$stmt) {
                    $this->flash('error', 'Failed to update contact info.');
                    break;
                }
                if ($imagePath) {
                    $stmt->bind_param('ssssssissssi', $name, $addr, $ph, $em, $hrs, $map, $this->userId, $patron, $cname, $cphone, $imagePath, $targetInfoId);
                } else {
                    $stmt->bind_param('ssssssisssi', $name, $addr, $ph, $em, $hrs, $map, $this->userId, $patron, $cname, $cphone, $targetInfoId);
                }
                
                if ($stmt->execute()) {
                    $newData = [
                        'parish_name' => $name,
                        'patron' => $patron,
                        'coordinator' => $cname,
                        'address' => $addr
                    ];
                    AuditLogger::logFromSession($this->conn, 'updated', 'Records', "Updated Cathedral contact info", $oldData, $newData);
                    $this->flash('success', 'Contact info updated.' . $uploadMsg);
                } else {
                    $this->flash('error', 'Failed to update contact info.');
                }
                $stmt->close();
                break;

            case 'save_mass':
                $id = (int)($_POST['id'] ?? 0);
                $day = $_POST['day_type'] ?? '';
                $tm = $_POST['schedule_time'] ?? '';
                $srt = (int)($_POST['sort_order'] ?? 0);
                $act = isset($_POST['is_active']) ? 1 : 0;

                if ($id) {
                    $oldData = [];
                    $ores = $this->conn->query("SELECT * FROM mass_schedules WHERE schedule_id=$id");
                    if($ores) $oldData = $ores->fetch_assoc();

                    $stmt = $this->conn->prepare("UPDATE mass_schedules SET day_type=?, schedule_time=?, sort_order=?, is_active=? WHERE schedule_id=?");
                    if (!$stmt) {
                        $this->flash('error', 'Failed to save schedule.');
                        break;
                    }
                    $stmt->bind_param('ssiii', $day, $tm, $srt, $act, $id);
                    if ($stmt->execute()) {
                        $newData = ['day' => $day, 'time' => $tm, 'active' => $act];
                        AuditLogger::logFromSession($this->conn, 'updated', 'Records', "Updated mass schedule: $day", $oldData, $newData);
                        $this->flash('success', 'Schedule saved.');
                    }
                } else {
                    $stmt = $this->conn->prepare("INSERT INTO mass_schedules (day_type, schedule_time, sort_order, is_active) VALUES (?,?,?,?)");
                    if (!$stmt) {
                        $this->flash('error', 'Failed to save schedule.');
                        break;
                    }
                    $stmt->bind_param('ssii', $day, $tm, $srt, $act);
                    if ($stmt->execute()) {
                        $newData = ['day' => $day, 'time' => $tm, 'active' => $act];
                        AuditLogger::logFromSession($this->conn, 'created', 'Records', "Added mass schedule: $day", null, $newData);
                        $this->flash('success', 'Schedule saved.');
                    }
                }
                $stmt->close();
                break;
            
            case 'update_email_settings':
                $webhookUrl = trim((string)($_POST['email_webhook_url'] ?? ''));
                if ($webhookUrl !== '') {
                    if (!filter_var($webhookUrl, FILTER_VALIDATE_URL)) {
                        $this->flash('error', 'Please enter a valid Email Webhook URL (or leave it empty).');
                        break;
                    }
                    $parsedWebhook = parse_url($webhookUrl);
                    $whScheme = strtolower((string)($parsedWebhook['scheme'] ?? ''));
                    $whHost = strtolower((string)($parsedWebhook['host'] ?? ''));
                    if ($whScheme !== 'https' || $whHost === '') {
                        $this->flash('error', 'Email Webhook URL must begin with https:// (e.g. https://script.google.com/macros/s/.../exec).');
                        break;
                    }
                    $whIps = filter_var($whHost, FILTER_VALIDATE_IP) ? [$whHost] : [];
                    if (empty($whIps)) {
                        $resolved = @gethostbyname($whHost);
                        if ($resolved && $resolved !== $whHost) {
                            $whIps[] = $resolved;
                        }
                    }
                    foreach ($whIps as $whIp) {
                        if (!filter_var($whIp, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                            $this->flash('error', 'Email Webhook URL cannot point to private, loopback, or reserved network addresses.');
                            break 2;
                        }
                    }
                }
                $emailProvider = strtolower(trim((string)($_POST['email_provider'] ?? '')));
                if (!in_array($emailProvider, ['', 'brevo', 'resend', 'sendgrid', 'webhook', 'smtp'], true)) {
                    $emailProvider = '';
                }
                $data = [
                    'smtp_host'            => trim((string)($_POST['smtp_host'] ?? '')),
                    'smtp_port'            => (int)($_POST['smtp_port'] ?? 0),
                    'smtp_user'            => trim((string)($_POST['smtp_user'] ?? '')),
                    'smtp_pass'            => (string)($_POST['smtp_pass'] ?? ''),
                    'smtp_secure'          => $_POST['smtp_secure'] ?? 'tls',
                    'from_email'           => trim((string)($_POST['from_email'] ?? '')),
                    'from_name'            => trim((string)($_POST['from_name'] ?? '')),
                    'email_provider'       => $emailProvider,
                    'email_api_key'        => (string)($_POST['email_api_key'] ?? ''),
                    'email_webhook_url'    => $webhookUrl,
                    'email_webhook_secret' => (string)($_POST['email_webhook_secret'] ?? ''),
                ];
                if ($this->parishModel->updateEmailSettings($data)) {
                    AuditLogger::logFromSession($this->conn, 'updated', 'System', 'Global Email Settings updated', null, null);
                    $this->flash('success', 'Cathedral email settings updated.');
                } else {
                    $this->flash('error', 'Failed to update email settings.');
                }
                break;

            case 'send_test_email':
                $testEmail = trim((string)($_POST['test_email'] ?? ''));
                if ($testEmail === '' || !filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
                    $this->flash('error', 'Please enter a valid recipient email address for the test.');
                    break;
                }
                require_once __DIR__ . '/../mail/mailer.php';
                $sentAt = date('F j, Y \a\t g:i:s A');
                $testSubject = 'IFI Cathedral System — Email Delivery Test';
                $testBody = '<div style="font-family:Arial,sans-serif;padding:20px;background:#f8fafc;border-radius:8px;border:1px solid #e2e8f0;">'
                    . '<h2 style="margin:0 0 10px;color:#1e293b;">Email Delivery Test Successful</h2>'
                    . '<p style="margin:0 0 8px;color:#334155;">This message confirms that outgoing email delivery from your IFI Cathedral Management System is working properly.</p>'
                    . '<p style="margin:0;font-size:12px;color:#64748b;">Sent on: ' . htmlspecialchars($sentAt, ENT_QUOTES, 'UTF-8') . ' (Asia/Manila)</p>'
                    . '</div>';
                $sent = sendChurchEmail($testEmail, 'Test Recipient', $testSubject, $testBody, null);
                $transport = $GLOBALS['lastMailTransport'] ?? '';
                $errDetail = $GLOBALS['lastMailError'] ?? '';
                if ($sent) {
                    $via = $transport !== '' ? " via {$transport}" : '';
                    AuditLogger::logFromSession($this->conn, 'updated', 'System', "Sent test email to {$testEmail}{$via}");
                    $this->flash('success', "Test email sent successfully to {$testEmail}{$via}.");
                } else {
                    $reason = $errDetail !== '' ? " ({$errDetail})" : '';
                    $this->flash('error', "Test email failed for {$testEmail}{$reason}.");
                }
                break;

            case 'delete_mass':
                $id = (int)($_POST['id'] ?? 0);
                $stmt = $this->conn->prepare("DELETE FROM mass_schedules WHERE schedule_id=?");
                if (!$stmt) {
                    $this->flash('error', 'Failed to delete schedule.');
                    break;
                }
                $stmt->bind_param('i', $id);
                if (!$stmt->execute()) {
                    $this->flash('error', 'Failed to delete schedule.');
                    $stmt->close();
                    break;
                }
                $stmt->close();
                $this->flash('success', 'Schedule deleted.');
                AuditLogger::logFromSession($this->conn, 'deleted', 'Records', "Deleted mass schedule ID: {$id}");
                
                break;

            case 'update_history_intro':
                $intro = $_POST['intro'] ?? '';
                if ($this->parishModel->updateHistoryIntro($intro, $this->userId)) {
                    $this->flash('success', 'History intro updated.');
                } else {
                    $this->flash('error', 'Failed to update intro.');
                }
                break;

            case 'save_history':
                $id = (int)($_POST['id'] ?? 0);
                $year = $_POST['year'] ?? '';
                $title = $_POST['title'] ?? '';
                $desc = $_POST['description'] ?? '';
                $sort = (int)($_POST['sort_order'] ?? 0);
                $active = isset($_POST['is_active']) ? 1 : 0;

                $oldData = $id ? $this->parishModel->getHistoryItemById($id) : null;

                if ($this->parishModel->saveHistory($id, $year, $title, $desc, $sort, $active, $this->userId)) {
                    $newData = ['year' => $year, 'title' => $title, 'active' => $active];
                    $action = $id ? 'updated' : 'created';
                    AuditLogger::logFromSession($this->conn, $action, 'Records', "History item: $year - $title", $oldData, $newData);
                    $this->flash('success', 'History item saved.');
                } else {
                    $this->flash('error', 'Failed to save history item.');
                }
                break;

            case 'delete_history':
                $id = (int)($_POST['id'] ?? 0);
                $oldData = $this->parishModel->getHistoryItemById($id);
                if ($this->parishModel->deleteHistory($id)) {
                    AuditLogger::logFromSession($this->conn, 'deleted', 'Records', "Deleted history item ID: $id", $oldData, null);
                    $this->flash('success', 'History item deleted.');
                } else {
                    $this->flash('error', 'Failed to delete history item.');
                }
                break;

            case 'update_leadership_intro':
                $intro = $_POST['intro'] ?? '';
                if ($this->parishModel->updateLeadershipIntro($intro, $this->userId)) {
                    $this->flash('success', 'Leadership intro updated.');
                } else {
                    $this->flash('error', 'Failed to update intro.');
                }
                break;

            case 'save_leader':
                $id = (int)($_POST['id'] ?? 0);
                $name = $_POST['name'] ?? '';
                $title = $_POST['title'] ?? '';
                $type = $_POST['position_type'] ?? '';
                $subtitle = $_POST['subtitle'] ?? '';
                $sort = (int)($_POST['sort_order'] ?? 0);
                $active = isset($_POST['is_active']) ? 1 : 0;

                $oldData = $id ? $this->parishModel->getLeaderById($id) : null;
                $uploadMsg = '';
                $imagePath = $this->handleImageUpload('leader_image', 'uploads/leaders/', 'leader_', 5 * 1024 * 1024, $uploadMsg);

                if ($this->parishModel->saveLeader($id, $name, $title, $type, $subtitle, $sort, $active, $this->userId, $imagePath)) {
                    $newData = ['name' => $name, 'title' => $title, 'type' => $type, 'active' => $active];
                    $action = $id ? 'updated' : 'created';
                    AuditLogger::logFromSession($this->conn, $action, 'Records', "Parish Leader: $name", $oldData, $newData);
                    $this->flash('success', 'Leader saved.' . $uploadMsg);
                } else {
                    $this->flash('error', 'Failed to save leader.');
                }
                break;

            case 'delete_leader':
                $id = (int)($_POST['id'] ?? 0);
                $oldData = $this->parishModel->getLeaderById($id);
                if ($this->parishModel->deleteLeader($id)) {
                    AuditLogger::logFromSession($this->conn, 'deleted', 'Records', "Deleted parish leader ID: $id", $oldData, null);
                    $this->flash('success', 'Leader deleted.');
                } else {
                    $this->flash('error', 'Failed to delete leader.');
                }
                break;
        }
        $redirectTab = preg_replace('/[^a-z0-9_-]/i', '', (string)($_POST['redirect_tab'] ?? ($_GET['tab'] ?? 'contact'))) ?: 'contact';
        $this->redirect('dashboard.php?section=parish_info&tab=' . $redirectTab);
    }

    /**
     * Provides data arrays directly to the Dashboard view, removing bulk from DashboardController.
     */
    public function getAdminViewData($section) {
        $data = [];
        $page = (int)($_GET['page'] ?? 1);
        $perPage = 30;

        if ($section === 'users') {
            $chapelFilter = !in_array($this->role, ['Admin', 'Cathedral Admin']) ? $this->chapelId : null;
            $result = $this->userModel->getPaginated($page, $perPage, $chapelFilter);
            $data['users'] = $result['records'];
            $data['total_pages'] = $result['pages'];
            $data['current_page'] = $result['page'];
            $data['total_count'] = $result['total'];
            $data['user_stats'] = $this->userModel->getMemberStats($chapelFilter);
            $data['pending_count'] = $this->userModel->getPendingCount($chapelFilter);
        } elseif ($section === 'chapels') {
            $chapelFilter = !in_array($this->role, ['Admin', 'Cathedral Admin']) ? $this->chapelId : null;
            // Since there are few chapels, we might not strictly NEED pagination, but for consistency:
            $data['chapels'] = $this->enrichChapelsWithFiesta($this->chapelModel->getAll($chapelFilter));
        } elseif ($section === 'parish_info') {
            $data['contact_info'] = $this->parishModel->getContactInfo();
            $data['mass_schedules'] = $this->parishModel->getMassSchedules();
            $data['history_intro'] = $this->parishModel->getHistoryIntro();
            $data['history_items'] = $this->parishModel->getHistoryItems();
            $data['leadership_intro'] = $this->parishModel->getLeadershipIntro();
            $data['leaders'] = $this->parishModel->getAllLeaders();
        }
        return $data;
    }

    private function updateProfile()
    {
        try {
            $name = trim($_POST['full_name'] ?? '');
            if (mb_strlen($name) > 100) {
                $name = mb_substr($name, 0, 100);
            }
            if (empty($name)) {
                $me = $this->userModel->getById($this->userId);
                if (is_array($me)) {
                    $name = $me['full_name'] ?? $me['user'] ?? 'User';
                } else {
                    $name = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'User';
                }
            }

            $uploadMsg = '';
            $profilePicPath = $this->handleImageUpload('profile_pic', 'uploads/profiles/', '', 2 * 1024 * 1024, $uploadMsg);

            // Handle password change
            $curPwd     = trim((string)($_POST['current_password'] ?? ''));
            $newPwd     = trim((string)($_POST['new_password'] ?? ''));
            $confirmPwd = isset($_POST['confirm_password']) ? trim((string)$_POST['confirm_password']) : null;

            if ($newPwd !== '') {
                if (mb_strlen($newPwd) < 6 || mb_strlen($newPwd) > 72) {
                    $this->flash('error', 'New password must be between 6 and 72 characters.');
                    $this->redirect('dashboard.php?section=my_profile');
                    return;
                }
                if ($confirmPwd !== null && $confirmPwd !== $newPwd) {
                    $this->flash('error', 'New password and confirm password do not match.');
                    $this->redirect('dashboard.php?section=my_profile');
                    return;
                }
                $hash = $this->userModel->getMyPasswordHash($this->userId);
                if ($curPwd !== '' && $hash && password_verify($curPwd, $hash)) {
                    $newHash = password_hash($newPwd, PASSWORD_BCRYPT, ['cost' => 12]);
                    $this->userModel->updateProfileAndPassword($this->userId, $name, $profilePicPath, $newHash);
                    $this->setMustChangePasswordFlag((int)$this->userId, false);
                    unset($_SESSION['must_change_password']);
                    if (session_status() === PHP_SESSION_ACTIVE) {
                        session_regenerate_id(true);
                    }
                    AuditLogger::logFromSession($this->conn, 'password_changed', 'Auth', "User updated their own password");
                    $this->flash('success', 'Profile and password updated.' . $uploadMsg);
                } else {
                    $this->flash('error', 'Current password is incorrect.');
                }
            } elseif (!empty($_SESSION['must_change_password'])) {
                $this->flash('error', 'Please enter and confirm a new password before continuing.');
            } else {
                $this->userModel->updateProfile($this->userId, $name, $profilePicPath);
                $this->flash('success', 'Profile updated.' . $uploadMsg);
            }
        } catch (Exception $e) {
            $this->flash('error', 'Update Error: ' . $e->getMessage());
        }

        $this->redirect('dashboard.php?section=my_profile');
    }

    private function setMustChangePasswordFlag(int $targetUserId, bool $required): void {
        require_once __DIR__ . '/AuthController.php';
        $auth = new AuthController($this->conn);
        if (!$auth->supportsMustChangePassword()) {
            return;
        }
        $val = $required ? 1 : 0;
        $stmt = $this->conn->prepare('UPDATE users SET must_change_password = ? WHERE id = ?');
        if ($stmt) {
            $stmt->bind_param('ii', $val, $targetUserId);
            $stmt->execute();
            $stmt->close();
        }
    }

    private function handleImageUpload(string $field, string $relativeDir, string $prefix, int $maxBytes, string &$uploadMsg): ?string {
        if (!isset($_FILES[$field])) {
            return null;
        }
        $file = $_FILES[$field];
        $err = $file['error'] ?? UPLOAD_ERR_NO_FILE;
        if ($err === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if ($err !== UPLOAD_ERR_OK) {
            $uploadMsg = ' (Upload failed)';
            return null;
        }

        $tmpName = $file['tmp_name'] ?? '';
        if (!is_uploaded_file($tmpName)) {
            $uploadMsg = ' (Invalid upload source)';
            return null;
        }
        if (($file['size'] ?? 0) <= 0 || $file['size'] > $maxBytes) {
            $mb = max(1, (int)round($maxBytes / (1024 * 1024)));
            $uploadMsg = " (Image too large, max {$mb}MB)";
            return null;
        }
        if (!class_exists('finfo')) {
            $uploadMsg = ' (Server MIME inspector unavailable)';
            return null;
        }

        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmpName) ?: '';
        $allowed = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
        ];
        if (!isset($allowed[$mime]) || @getimagesize($tmpName) === false) {
            $uploadMsg = ' (Invalid image format)';
            return null;
        }

        $ext = $allowed[$mime];
        $bytesLen = ($prefix === '') ? 16 : 8;
        $filename = $prefix . bin2hex(random_bytes($bytesLen)) . '.' . $ext;
        $rel = trim($relativeDir, '/\\') . '/';
        $absDir = BASE_PATH . '/storage/' . $rel;

        if (!is_dir($absDir) && !mkdir($absDir, 0755, true) && !is_dir($absDir)) {
            $uploadMsg = ' (Upload storage error)';
            return null;
        }

        if (move_uploaded_file($tmpName, $absDir . $filename)) {
            return $rel . $filename;
        }

        $uploadMsg = ' (Upload failed)';
        return null;
    }

    /**
     * Pre-enriches a chapels array with a boolean `is_near_fiesta` flag (within 7 days).
     * Moved from DashboardController to AdminController.
     */
    public function enrichChapelsWithFiesta(array $chapels): array
    {
        $today = new DateTime('today');
        foreach ($chapels as &$ch) {
            $ch['is_near_fiesta'] = false;
            $fjson = json_decode($ch['fiestas_json'] ?? '[]', true);
            if (!is_array($fjson)) continue;
            foreach ($fjson as $fj) {
                $fdate = DateTime::createFromFormat('Y-m-d', $fj['date'] ?? '');
                if (!$fdate) $fdate = @date_create(($fj['date'] ?? '') . ' ' . $today->format('Y'));
                if (!$fdate) continue;
                $fdate->setTime(0, 0, 0);
                // If already passed this year, roll to next year
                if ($fdate < $today) $fdate->modify('+1 year');
                $diff = (int) $today->diff($fdate)->format('%r%a');
                if ($diff >= 0 && $diff <= 7) {
                    $ch['is_near_fiesta'] = true;
                    break;
                }
            }
        }
        unset($ch); // Break reference
        return $chapels;
    }
}
