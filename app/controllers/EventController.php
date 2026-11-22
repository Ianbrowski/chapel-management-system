<?php
require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../models/Event.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/AuditLogger.php';
require_once __DIR__ . '/../mail/EventRequestMailer.php';
require_once __DIR__ . '/../services/SmsService.php';

/**
 * EventController — Handles all scheduling, event requests, and pastoral preferences.
 */
class EventController extends BaseController {
    private $eventModel;
    private $userModel;

    public function __construct($conn) {
        parent::__construct($conn);
        $this->eventModel = new Event($this->conn);
        $this->userModel  = new User($this->conn);
    }

    public function handleRequest() {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') return;
        
        $section = $_POST['form_section'] ?? '';
        $action  = $_POST['action'] ?? '';

        // CSRF Protection for state-changing actions
        if (in_array($section, ['events', 'event_request', 'certificate_request', 'review_request', 'priest_prefs', 'pastor_prefs', 'schedule_audit'])) {
            $this->enforceCsrf();
        }

        // Enforce chapel isolation for schedule records. Event requests are allowed
        // to target another venue, then admins review them before confirmation.
        if (isset($_POST['chapel_id']) && !in_array($section, ['event_request', 'certificate_request'])) {
            $_POST['chapel_id'] = $this->getAppliedChapelId($_POST['chapel_id']);
        }

        switch ($section) {
            case 'events':              $this->handleEventActions($action); break;
            case 'event_request':
            case 'certificate_request': $this->handleEventRequestActions($action); break;
            case 'review_request':      $this->handleReviewActions($action); break;
            case 'priest_prefs':
            case 'pastor_prefs':        $this->handlePastorPrefs(); break;
            case 'schedule_audit':      $this->handleScheduleAuditActions($action); break;
        }
    }

    private function handleEventActions($action) {
        $id = (int)($_POST['id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $type = $_POST['event_type'] ?? '';
        if ($title === '') {
            $title = "Untitled " . ($type ?: 'Event');
        }
        $start = $_POST['start_time'] ?? '';
        $loc = $this->normalizeNullableInt($_POST['chapel_id'] ?? null);
        $pastor = $this->normalizeNullableInt($_POST['pastor_id'] ?? null);
        $status = $_POST['status'] ?? 'Pending';
        $scope  = $this->deriveEventScope($loc);

        $customDuration = (isset($_POST['custom_duration']) && (int)$_POST['custom_duration'] > 0) 
            ? (int)$_POST['custom_duration'] : null;
        $phone = $_POST['requester_phone'] ?? null;
        $email = $_POST['requester_email'] ?? null;
        $locationType = in_array($_POST['location_type'] ?? '', ['Church', 'Outside'], true) ? $_POST['location_type'] : 'Church';

        // Permission check
        $canManage = ($scope === 'cathedral') ? $this->permissions['can_cathedral_sched'] : $this->permissions['can_chapel_sched'];

        switch ($action) {
            case 'create_event':
                // Only admins (including Chapel Admin) can directly create events
                if (!in_array($this->role, ['Admin', 'Cathedral Admin', 'Chapel Admin'])) {
                    $this->flash('error', 'You do not have permission to create events directly. Use request instead.');
                    break;
                }
                if (!$canManage) {
                    $this->flash('error', 'You do not have permission to create schedules.');
                    break;
                }
                if ($loc !== null && !$this->checkChapelAccess($loc)) {
                    $this->flash('error', 'You cannot create events for this chapel.');
                    break;
                }
                if (!$this->isValidEventType($type)) {
                    $this->flash('error', 'Invalid event type selected. Please choose a valid event type.');
                    break;
                }
                $scheduleError = $this->validateScheduleInput($start, true);
                if ($scheduleError !== null) {
                    $this->flash('error', $scheduleError);
                    break;
                }
                
                $this->conn->begin_transaction();
                // Conflict Check
                $conflict = $this->eventModel->checkConflict($start, $pastor, $loc, $type, null, $customDuration, null, $locationType);
                if ($conflict['conflict']) {
                    $this->conn->rollback();
                    $this->flash('error', "Schedule conflict detected ({$conflict['type']}): {$conflict['details']}. Please choose a different time.");
                    break;
                }

                $reqSource = $_POST['requester_source'] ?? 'office';
                $requestedBy = ($reqSource === 'walkin') ? null : $this->userId;

                $createdEventId = $this->eventModel->create($title, $type, $start, $loc, $pastor, $status, $scope, $customDuration, $phone, $email, $requestedBy, $locationType);
                if ($createdEventId) {
                    $this->conn->commit();
                    AuditLogger::logFromSession(
                        $this->conn,
                        'created',
                        'Events',
                        "Created event: {$title} ({$type})",
                        null,
                        [
                            'title' => $title,
                            'event_type' => $type,
                            'start_time' => $start,
                            'chapel_id' => $loc,
                            'pastor_id' => $pastor,
                            'status' => $status,
                            'scope' => $scope,
                            'custom_duration' => $customDuration,
                            'requester_phone' => $phone,
                            'requester_email' => $email
                        ]
                    );
                    $notifMsg = '';
                    if ($status === 'Confirmed' && (trim((string)$email) !== '' || trim((string)$phone) !== '')) {
                        $notifSummary = $this->sendConfirmedNotifications((int)$createdEventId, $pastor, false);
                        if ($notifSummary !== '') {
                            $notifMsg = ' ' . $notifSummary;
                        }
                    }
                    $this->flash('success', 'Event created.' . $notifMsg);
                } else {
                    $this->conn->rollback();
                    $error = $_SESSION['db_error'] ?? $this->conn->error;
                    unset($_SESSION['db_error']);
                    $this->flash('error', "Failed to create event. Database error: " . $error);
                }
                break;
            case 'update_event':
                if (!$canManage) {
                    $this->flash('error', 'You do not have permission to update schedules.');
                    break;
                }
                $existing = $this->eventModel->getById($id);
                if (!$existing || !$this->canManageExistingChapelRecord($existing['chapel_id'] ?? null)) {
                    $this->flash('error', 'You cannot update this event.');
                    break;
                }
                if ($loc !== null && !$this->checkChapelAccess($loc)) {
                    $this->flash('error', 'You cannot update events to this chapel.');
                    break;
                }
                if (!$this->isValidEventType($type)) {
                    $this->flash('error', 'Invalid event type selected. Please choose a valid event type.');
                    break;
                }
                $scheduleError = $this->validateScheduleInput($start, false);
                if ($scheduleError !== null) {
                    $this->flash('error', $scheduleError);
                    break;
                }

                // 🛡️ Security Guard: Lock Confirmed/Completed events for non-admins
                if (in_array($existing['status'], ['Confirmed', 'Completed'], true)) {
                    if (!in_array($this->role, ['Admin', 'Cathedral Admin', 'Chapel Admin'], true)) {
                        $this->flash('error', "This event is already {$existing['status']} and cannot be modified. Please contact a system administrator for any necessary corrections.");
                        break;
                    }
                }

                // 🛡️ Integrity Guard: Prevent status "downgrade" bypass (Confirmed -> Pending)
                // Admins and Chapel Admins (for their assigned chapel) can revert status to Pending for corrections
                if ($existing['status'] === 'Confirmed' && $status === 'Pending') {
                    if (!in_array($this->role, ['Admin', 'Cathedral Admin', 'Chapel Admin'])) {
                        $status = 'Confirmed'; // Revert back if not high-level or chapel admin
                    }
                }

                $this->conn->begin_transaction();
                // Conflict Check (exclude self)
                $conflict = $this->eventModel->checkConflict($start, $pastor, $loc, $type, $id, $customDuration, null, $locationType);
                if ($conflict['conflict']) {
                    $this->conn->rollback();
                    $this->flash('error', "Schedule conflict detected ({$conflict['type']}): {$conflict['details']}. Please choose a different time.");
                    break;
                }

                if ($this->eventModel->update($id, $title, $type, $start, $loc, $pastor, $status, $scope, $customDuration, $phone, $email, $locationType)) {
                    $this->conn->commit();
                                        AuditLogger::logFromSession(
                        $this->conn,
                        'updated',
                        'Events',
                        "Updated event ID: {$id} - {$title}",
                        $existing,
                        [
                            'title' => $title,
                            'event_type' => $type,
                            'start_time' => $start,
                            'chapel_id' => $loc,
                            'pastor_id' => $pastor,
                            'status' => $status,
                            'scope' => $scope,
                            'custom_duration' => $customDuration,
                            'requester_phone' => $phone,
                            'requester_email' => $email
                        ]
                    );
                    $notifMsg = '';
                    if ($existing['status'] !== 'Confirmed' && $status === 'Confirmed'
                        && (trim((string)$email) !== '' || trim((string)$phone) !== '' || !empty($existing['requested_by']))) {
                        $notifSummary = $this->sendConfirmedNotifications((int)$id, $pastor, true);
                        if ($notifSummary !== '') {
                            $notifMsg = ' ' . $notifSummary;
                        }
                    }
                    $this->flash('success', 'Event updated.' . $notifMsg);
                } else {
                    $this->conn->rollback();
                    $this->flash('error', 'Failed to update event.');
                }
                break;
            case 'delete_event':
                if (!$this->permissions['can_delete_events']) {
                    $this->flash('error', 'You do not have permission to delete events.');
                    break;
                }

                $event = $this->eventModel->getById($id);
                if (!$event || !$this->canManageExistingChapelRecord($event['chapel_id'] ?? null)) {
                    $this->flash('error', 'You cannot delete this event.');
                    break;
                }
                if ($event && in_array($event['status'], ['Confirmed', 'Completed'])) {
                    $this->flash('error', 'Cannot delete confirmed or completed events. Please use Archive/Stale resolution instead.');
                    break;
                }

                if ($this->eventModel->delete($id)) {
                                        AuditLogger::logFromSession(
                        $this->conn,
                        'deleted',
                        'Events',
                        "Deleted event ID: {$id}",
                        $event,
                        null
                    );
                    $this->flash('success', 'Event deleted.');
                } else {
                    $this->flash('error', 'Failed to delete event.');
                }
                break;
            case 'archive_event':
                if (!$this->permissions['can_delete_events']) {
                    $this->flash('error', 'You do not have permission to archive events.');
                    break;
                }
                $event = $this->eventModel->getById($id, true);
                if (!$event || !$this->canManageExistingChapelRecord($event['chapel_id'] ?? null)) {
                    $this->flash('error', 'You cannot archive this event.');
                    break;
                }
                if ($this->eventModel->archive($id)) {
                                        AuditLogger::logFromSession(
                        $this->conn,
                        'archived',
                        'Events',
                        "Archived event ID: {$id}",
                        $event,
                        null
                    );
                    $this->flash('success', 'Event archived.');
                } else {
                    $this->flash('error', 'Failed to archive event.');
                }
                break;
            case 'restore_event':
                if (!$this->permissions['can_delete_events']) {
                    $this->flash('error', 'You do not have permission to restore events.');
                    break;
                }
                $event = $this->eventModel->getById($id, true);
                if (!$event || !$this->canManageExistingChapelRecord($event['chapel_id'] ?? null)) {
                    $this->flash('error', 'You cannot restore this event.');
                    break;
                }
                if ($this->eventModel->restore($id)) {
                                        AuditLogger::logFromSession(
                        $this->conn,
                        'restored',
                        'Events',
                        "Restored event ID: {$id}",
                        $event,
                        null
                    );
                    $this->flash('success', 'Event restored to active schedule.');
                } else {
                    $this->flash('error', 'Failed to restore event.');
                }
                break;
            case 'cancel_event':
                if (!$canManage) {
                    $this->flash('error', 'You do not have permission to cancel events.');
                    break;
                }
                $existing = $this->eventModel->getById($id);
                if (!$existing || !$this->canManageExistingChapelRecord($existing['chapel_id'] ?? null)) {
                    $this->flash('error', 'You cannot cancel this event.');
                    break;
                }
                if ($this->eventModel->update(
                    $id,
                    $existing['title'],
                    $existing['event_type'],
                    $existing['start_time'],
                    $existing['chapel_id'],
                    $existing['pastor_id'],
                    'Cancelled',
                    $existing['event_scope'] ?? $existing['scope'] ?? 'cathedral',
                    $existing['custom_duration'] ?? null,
                    $existing['requester_phone'] ?? null,
                    $existing['requester_email'] ?? null
                )) {
                    AuditLogger::logFromSession(
                        $this->conn,
                        'updated',
                        'Events',
                        "Cancelled event ID: {$id} - {$existing['title']}",
                        $existing,
                        array_merge($existing, ['status' => 'Cancelled'])
                    );
                    $this->flash('success', 'Event marked as Cancelled.');
                } else {
                    $this->flash('error', 'Failed to cancel event.');
                }
                break;
        }
        $returnSection = $_POST['return_section'] ?? ($_GET['section'] ?? 'events');
        $this->redirect('dashboard.php?section=' . $returnSection);
    }

    private function handleEventRequestActions($action) {
        $reqRedirect = $_POST['redirect_section'] ?? '';
        $isCertSection = ($_POST['form_section'] ?? '') === 'certificate_request' || $reqRedirect === 'request_certificate';
        if ($reqRedirect === 'event_requests') {
            $redirectUrl = 'dashboard.php?section=event_requests';
        } elseif ($isCertSection) {
            $redirectUrl = 'dashboard.php?section=request_certificate';
        } else {
            $redirectUrl = 'dashboard.php?section=request_event';
        }

        if ($this->role === 'Guest' || (int)$this->userId === 0) {
            $this->flash('error', 'Guest accounts cannot submit requests. Please register or sign in as a parishioner member.');
            $this->redirect($redirectUrl . '&auth_prompt=1');
            return;
        }

        if ($action === 'delete_request') {
            $reqId = (int)($_POST['event_id'] ?? $_POST['id'] ?? 0);
            $event = $this->eventModel->getById($reqId);
            if (!$event) {
                $this->flash('error', 'Request record not found.');
                $this->redirect($redirectUrl);
            }
            $isOwner = ((int)($event['requested_by'] ?? 0) === (int)$this->userId);
            $canAdmin = in_array($this->role, ['Admin', 'Cathedral Admin']) || $this->checkChapelAccess($event['chapel_id'] ?? null);
            if (!$isOwner && !$canAdmin) {
                $this->flash('error', 'Unauthorized to delete this request.');
                $this->redirect($redirectUrl);
            }
            if ($this->eventModel->delete($reqId)) {
                AuditLogger::logFromSession($this->conn, 'deleted', 'Event Requests', "Deleted request ID: {$reqId} - {$event['title']}", $event, null);
                $this->flash('success', 'Request deleted.');
            } else {
                $this->flash('error', 'Failed to delete request.');
            }
            $this->redirect($redirectUrl);
        }

        if ($action === 'cancel_request') {
            $reqId = (int)($_POST['event_id'] ?? $_POST['id'] ?? 0);
            $event = $this->eventModel->getById($reqId);
            if (!$event) {
                $this->flash('error', 'Request record not found.');
                $this->redirect($redirectUrl);
            }
            $isOwner = ((int)($event['requested_by'] ?? 0) === (int)$this->userId);
            $canAdmin = in_array($this->role, ['Admin', 'Cathedral Admin']) || $this->checkChapelAccess($event['chapel_id'] ?? null);
            if (!$isOwner && !$canAdmin) {
                $this->flash('error', 'Unauthorized to cancel this request.');
                $this->redirect($redirectUrl);
            }
            if ($this->eventModel->update(
                $reqId,
                $event['title'],
                $event['event_type'],
                $event['start_time'],
                $event['chapel_id'],
                $event['pastor_id'],
                'Cancelled',
                $event['event_scope'] ?? $event['scope'] ?? 'cathedral',
                $event['custom_duration'] ?? null,
                $event['requester_phone'] ?? null,
                $event['requester_email'] ?? null
            )) {
                AuditLogger::logFromSession($this->conn, 'updated', 'Event Requests', "Cancelled request ID: {$reqId} - {$event['title']}", $event, array_merge($event, ['status' => 'Cancelled']));
                $this->flash('success', 'Request cancelled.');
            } else {
                $this->flash('error', 'Failed to cancel request.');
            }
            $this->redirect($redirectUrl);
        }

        $title = $_POST['title'] ?? '';
        $type = $_POST['event_type'] ?? '';
        $start = $_POST['start_time'] ?? '';
        $loc = $this->normalizeNullableInt($_POST['chapel_id'] ?? null);
        $scope = $this->deriveEventScope($loc);

        if ($loc !== null && !$this->chapelExists($loc)) {
            $this->flash('error', 'Please select a valid chapel location.');
            $this->redirect($redirectUrl);
        }
        
        // Personnel Isolation: Staff/Chapel Admins default to their branch but can request cross-branch
        if (!in_array($this->role, ['Admin', 'Cathedral Admin'])) {
            if ($loc === null || $loc == 0) {
                // Requesting at Cathedral — Allowed for all
                $scope = 'cathedral';
            } elseif ((int)$loc !== (int)$this->chapelId) {
                // Requesting at another branch — Allowed as request
                $scope = 'chapel';
            } else {
                $scope = 'chapel';
            }
        }

        $email = $_POST['requester_email'] ?? '';
        $phone = $_POST['requester_phone'] ?? '';
        $pastor = $this->normalizeNullableInt($_POST['pastor_id'] ?? null);
        $status = 'Pending';
        $customDuration = isset($_POST['custom_duration']) && (int)$_POST['custom_duration'] > 0 
            ? (int)$_POST['custom_duration'] : null;

        if (!$this->isValidEventType($type)) {
            $this->flash('error', 'Invalid event type selected. Please choose from available services.');
            $this->redirect($redirectUrl);
            return;
        }

        $scheduleError = $this->validateScheduleInput($start, true);
        if ($scheduleError !== null) {
            $this->flash('error', $scheduleError);
            $this->redirect($redirectUrl);
        }

        // Only auto-confirm if user has permission AND is acting within their own chapel
        if ($this->permissions['can_approve_events'] && !empty($pastor) && $this->checkChapelAccess($loc)) {
            // Additional check: Chapel Admins cannot auto-confirm Cathedral events
            if (!in_array($this->role, ['Admin', 'Cathedral Admin']) && $loc === null) {
                $status = 'Pending';
            } else {
                $status = 'Confirmed';
            }
        }
        // Service Catalog Pricing & Snapshotting
        $serviceId = isset($_POST['service_id']) && (int)$_POST['service_id'] > 0 ? (int)$_POST['service_id'] : null;
        $variantId = isset($_POST['variant_id']) && (int)$_POST['variant_id'] > 0 ? (int)$_POST['variant_id'] : null;
        $selectedAddons = isset($_POST['addons']) && is_array($_POST['addons']) ? $_POST['addons'] : [];
        $locationType = in_array($_POST['location_type'] ?? '', ['Church', 'Outside']) ? $_POST['location_type'] : 'Church';

        $this->conn->begin_transaction();
        // Conflict Check: Certificates and shared Mass Intentions bypass altar exclusivity
        if (!$isCertSection && $type !== 'Ecclesiastical Certificates') {
            $conflict = $this->eventModel->checkConflict($start, $pastor, $loc, $type, null, $customDuration, $serviceId, $locationType);
            if ($conflict['conflict']) {
                $this->conn->rollback();
                $this->flash('error', "Schedule conflict detected ({$conflict['type']}): {$conflict['details']}. Please choose a different time.");
                $this->redirect($redirectUrl);
            }
        }

        $serviceFee = 0.00;
        $addonsFee  = 0.00;
        $totalFee   = 0.00;
        $verifiedAddons = [];

        if ($serviceId && $variantId) {
            require_once __DIR__ . '/../models/ServiceModel.php';
            $srvModel = new ServiceModel($this->conn);
            try {
                // Verify submitted event_type matches the catalog service to prevent conflict-bypass tampering
                $srvCheckStmt = $this->conn->prepare("SELECT name FROM services WHERE id = ? LIMIT 1");
                if ($srvCheckStmt) {
                    $srvCheckStmt->bind_param('i', $serviceId);
                    $srvCheckStmt->execute();
                    $srvRow = $srvCheckStmt->get_result()->fetch_assoc();
                    $srvCheckStmt->close();
                    if (!$srvRow || (strcasecmp(trim($srvRow['name']), trim($type)) !== 0 && !$isCertSection)) {
                        throw new InvalidArgumentException("Selected service option does not match the requested rite.");
                    }
                }
                $quantity = max(1, (int)($_POST['certificate_copies'] ?? $_POST['quantity'] ?? 1));
                $calc = $srvModel->calculateAndVerifyFee($serviceId, $variantId, $selectedAddons, $quantity);
                $serviceFee = $calc['base_fee'];
                $addonsFee  = $calc['addons_fee'];
                $totalFee   = $calc['total_fee'];
                $verifiedAddons = $calc['addons'];
            } catch (Exception $ex) {
                $this->conn->rollback();
                $this->flash('error', $ex->getMessage());
                $this->redirect($redirectUrl);
            }
        }

        $eventId = $this->eventModel->requestEvent($title, $type, $start, $loc, $scope, $this->userId, $email, $status, $pastor, $customDuration, $phone, $serviceId, $variantId, $serviceFee, $addonsFee, $totalFee, $locationType);
        if ($eventId) {
            foreach ($verifiedAddons as $ad) {
                $this->eventModel->addRequestAddon((int)$eventId, (int)$ad['addon_id'], $ad['addon_name'], (float)$ad['unit_price'], (int)$ad['quantity'], (float)$ad['subtotal']);
            }
            $this->conn->commit();
            AuditLogger::logFromSession(
                $this->conn,
                'requested',
                'Event Requests',
                "New request: {$title} (Total Fee: PHP " . number_format($totalFee, 2) . ")",
                null,
                [
                    'title' => $title,
                    'event_type' => $type,
                    'service_id' => $serviceId,
                    'variant_id' => $variantId,
                    'service_fee' => $serviceFee,
                    'addons_fee' => $addonsFee,
                    'total_fee' => $totalFee,
                    'start_time' => $start,
                    'chapel_id' => $loc,
                    'scope' => $scope,
                    'requester_id' => $this->userId,
                    'requester_email' => $email,
                    'status' => $status,
                    'pastor_id' => $pastor,
                    'custom_duration' => $customDuration,
                    'requester_phone' => $phone
                ]
            );
            
            // Automated Acknowledgment or Confirmation Notifications
            $notifMsg = '';
            if ($status === 'Pending') {
                EventRequestMailer::sendAcknowledgment($this->conn, (int)$eventId);

                // Trigger Member-specific In-App Notification
                if ($this->userId > 0) {
                    require_once __DIR__ . '/../models/SystemNotification.php';
                    $sysNotif = new SystemNotification($this->conn);
                    $sysNotif->create(
                        $this->userId,
                        "Event Request Submitted",
                        "Your request for \"{$title}\" has been submitted successfully and is pending review by the Cathedral parish administration.",
                        'event_submitted',
                        (int)$eventId
                    );
                }
            } elseif ($status === 'Confirmed' && (trim((string)$email) !== '' || trim((string)$phone) !== '')) {
                $notifSummary = $this->sendConfirmedNotifications((int)$eventId, $pastor, false);
                if ($notifSummary !== '') {
                    $notifMsg = ' ' . $notifSummary;
                }
            }

            $msg = ($status === 'Confirmed') ? ('Event created and confirmed.' . $notifMsg) : ($isCertSection ? 'Certificate application submitted successfully. The Parish Office will review and prepare your documents for pickup.' : 'Event request submitted.');
            $this->flash('success', $msg);
        } else {
            $this->conn->rollback();
            $error = $_SESSION['db_error'] ?? $this->conn->error;
            unset($_SESSION['db_error']);
            // DEBUG: write full context to file
            $dbg = "[EventRequest DEBUG] DB Error: $error\nPOST: " . json_encode($_POST) . "\nUser: {$this->userId}, Role: {$this->role}, Chapel: {$this->chapelId}\n";
            file_put_contents(__DIR__ . '/../../storage/debug.txt', $dbg, FILE_APPEND);
            $this->flash('error', "Failed to submit event request. Database error: " . $error);
        }
        $this->redirect($redirectUrl);
    }

    private function handleReviewActions($action) {
        if (!$this->permissions['can_approve_events']) return;
        $id = (int)($_POST['id'] ?? 0);
        $note = $_POST['rejection_note'] ?? '';
        $redirectSection = ($_POST['redirect_section'] ?? '') === 'request_certificate' ? 'request_certificate' : 'event_requests';
        $redirectUrl = 'dashboard.php?section=' . $redirectSection;

        $record = $this->eventModel->getById($id);
        if (!$record || !$this->canManageExistingChapelRecord($record['chapel_id'] ?? null)) {
            $this->flash('error', 'You do not have permission to review requests for other chapels.');
            $this->redirect($redirectUrl);
        }

        $submittedPastor = isset($_POST['pastor_id']) && (int)$_POST['pastor_id'] > 0 ? (int)$_POST['pastor_id'] : null;
        $pastor = $submittedPastor !== null ? $submittedPastor : (!empty($record['pastor_id']) ? (int)$record['pastor_id'] : null);

        if ($action === 'approve') {
            $this->conn->begin_transaction();
            // Optional: Re-check conflict before final approval (skip for office-only Certificates)
            $recType = (string)($record['event_type'] ?? '');
            $recServiceId = isset($record['service_id']) && (int)$record['service_id'] > 0 ? (int)$record['service_id'] : null;
            $isCertRecord = ($recType === 'Ecclesiastical Certificates' || stripos($recType, 'Certificate') !== false || $recServiceId === 8);
            if (!$isCertRecord) {
                $conflict = $this->eventModel->checkConflict($record['start_time'], $pastor, $record['chapel_id'], $recType, $id, $record['custom_duration'], $recServiceId, $record['location_type'] ?? 'Church');
                if ($conflict['conflict']) {
                    $this->conn->rollback();
                    $this->flash('error', "Schedule conflict detected during approval ({$conflict['type']}): {$conflict['details']}.");
                    $this->redirect($redirectUrl);
                }
            }

            if (!$this->eventModel->approveRequest($id, $pastor)) {
                $this->conn->rollback();
                $this->flash('error', 'Failed to approve request.');
                $this->redirect($redirectUrl);
            }
            $this->conn->commit();

            $notifSummary = $this->sendConfirmedNotifications((int)$id, $pastor, true);

            AuditLogger::logFromSession($this->conn, 'approved', 'Event Requests', "Approved request ID: {$id}");
            $this->flash('success', 'Request approved. ' . $notifSummary);
        } elseif ($action === 'reject') {
            if (!$this->eventModel->rejectRequest($id, $note)) {
                $this->flash('error', 'Failed to reject request.');
                $this->redirect($redirectUrl);
            }

            $event = $this->eventModel->getById($id);
            $mailSent = false;
            $smsSent = false;
            if ($event) {
                $mailSent = EventRequestMailer::sendRejected($this->conn, $id, $note);
                if (!empty($event['requester_phone'])) {
                    $smsSent = SmsService::sendEventRejected($this->conn, $id, $note);
                }

                // Member-specific in-app notification
                $reqUserId = (int)($event['requested_by'] ?? 0);
                if ($reqUserId > 0) {
                    require_once __DIR__ . '/../models/SystemNotification.php';
                    $sysNotif = new SystemNotification($this->conn);
                    $eventTitle = $event['title'] ?: $event['event_type'];
                    $reasonText = !empty($note) ? " Reason: {$note}" : " Please contact the parish office for details.";
                    $sysNotif->create(
                        $reqUserId,
                        "Event Request Update",
                        "Your request for \"{$eventTitle}\" could not be approved at this time.{$reasonText}",
                        'event_rejected',
                        (int)$id
                    );
                }
            }

            AuditLogger::logFromSession($this->conn, 'rejected', 'Event Requests', "Rejected request ID: {$id}");
            $this->flash('success', 'Request rejected. ' . $this->formatEventNotificationSummary($event, $mailSent, $smsSent));
        } elseif ($action === 'complete') {
            if (!$this->eventModel->completeRequest($id)) {
                $this->flash('error', 'Only Ready / Confirmed requests can be marked as completed or released.');
                $this->redirect($redirectUrl);
            }

            $event = $this->eventModel->getById($id);
            $isCertRecord = ((int)($event['service_id'] ?? 0) === 8) || stripos((string)($event['title'] ?? ''), 'Certificate') !== false;
            if ($event) {
                $reqUserId = (int)($event['requested_by'] ?? 0);
                if ($reqUserId > 0) {
                    require_once __DIR__ . '/../models/SystemNotification.php';
                    $sysNotif = new SystemNotification($this->conn);
                    $evTitle = $event['title'] ?: ($event['event_type'] ?: 'Request');
                    $notifTitle = $isCertRecord ? "Certificate Released" : "Event Completed";
                    $notifBody = $isCertRecord
                        ? "Your certificate request \"{$evTitle}\" has been marked as released."
                        : "Your scheduled event \"{$evTitle}\" has been marked as completed.";
                    $sysNotif->create($reqUserId, $notifTitle, $notifBody, 'event_completed', (int)$id);
                }
            }

            AuditLogger::logFromSession($this->conn, 'completed', $isCertRecord ? 'Certificates' : 'Event Requests', "Marked ID {$id} as Completed/Released");
            $this->flash('success', $isCertRecord ? 'Certificate marked as released.' : 'Event marked as completed.');
        } elseif ($action === 'record_payment') {
            $amount = round((float)($_POST['amount'] ?? 0), 2);
            $method = trim((string)($_POST['payment_method'] ?? 'Cash'));
            $allowedMethods = ['Cash', 'GCash', 'Bank Transfer', 'Maya', 'Other'];
            if (!in_array($method, $allowedMethods, true)) {
                $method = 'Cash';
            }
            $dateRecv = trim((string)($_POST['date_received'] ?? date('Y-m-d')));
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateRecv)) {
                $dateRecv = date('Y-m-d');
            }
            $donorRef = mb_substr(trim((string)($_POST['donor_ref_id'] ?? '')), 0, 120);

            if ($amount <= 0) {
                $this->flash('error', 'Payment amount must be greater than zero.');
                $this->redirect($redirectUrl);
            }

            $isCertRecord = ((int)($record['service_id'] ?? 0) === 8) || stripos((string)($record['title'] ?? ''), 'Certificate') !== false;
            $padId = str_pad((string)$id, 4, '0', STR_PAD_LEFT);
            $refId = ($isCertRecord ? 'REQ-' : 'EV-') . $padId;

            $donorName = 'Parishioner';
            if (!empty($record['requested_by'])) {
                $reqUser = $this->userModel->getById((int)$record['requested_by']);
                if (is_array($reqUser)) {
                    $donorName = (string)($reqUser['full_name'] ?: ($reqUser['user'] ?: 'Parishioner'));
                }
            } elseif (!empty($record['requester_email'])) {
                $donorName = (string)$record['requester_email'];
            }
            $donorName = mb_substr(trim($donorName), 0, 100);
            $purposePrefix = $isCertRecord ? 'Certificate Fee: ' : 'Service Fee: ';
            $purpose = mb_substr($purposePrefix . trim((string)($record['title'] ?? 'Service Request')), 0, 100);

            require_once __DIR__ . '/../models/Donation.php';
            $donationModel = new Donation($this->conn);
            $donId = $donationModel->create([
                'chapel_id'      => !empty($record['chapel_id']) ? (int)$record['chapel_id'] : null,
                'amount'         => $amount,
                'purpose'        => $purpose,
                'donor_name'     => $donorName,
                'date_received'  => $dateRecv,
                'reference_id'   => $refId,
                'donor_ref_id'   => $donorRef,
                'logger_id'      => $this->userId,
                'payment_method' => $method,
            ]);

            if ($donId) {
                if (!empty($record['requested_by'])) {
                    require_once __DIR__ . '/../models/SystemNotification.php';
                    $sysNotif = new SystemNotification($this->conn);
                    $evTitle = $record['title'] ?: 'Service Request';
                    $sysNotif->create(
                        (int)$record['requested_by'],
                        "Payment Recorded",
                        "Payment of PHP " . number_format($amount, 2) . " ({$method}) for \"{$evTitle}\" ({$refId}) has been recorded.",
                        'payment_recorded',
                        (int)$id
                    );
                }
                AuditLogger::logFromSession($this->conn, 'created', 'Finance', "Recorded fee payment PHP " . number_format($amount, 2) . " for {$refId}");
                $this->flash('success', "Payment of ₱" . number_format($amount, 2) . " recorded to Finance ({$refId}).");
            } else {
                $this->flash('error', 'Failed to record payment.');
            }
        }

        $this->redirect($redirectUrl);
    }

    private function handlePastorPrefs() {
        $role = $_SESSION['role'] ?? '';
        $isAdmin = in_array($role, ['Admin', 'Cathedral Admin'], true);
        if (!$isAdmin) {
            $this->flash('error', 'Only Cathedral Administrators can modify clergy authorizations and coverage.');
            $this->redirect('dashboard.php?section=priest_schedule&tab=authorizations');
            return;
        }

        $targetPriestId = $this->userId;
        if (!empty($_POST['priest_id'])) {
            $targetPriestId = (int)$_POST['priest_id'];
        }

        $allowedTypes = ['Baptism', 'Wedding', 'Mass', 'Funeral', 'Confirmation', 'Other'];
        $types = array_values(array_filter(
            (array)($_POST['event_types'] ?? []),
            fn($t) => in_array($t, $allowedTypes, true)
        ));
        $chapels = array_values(array_filter(
            (array)($_POST['chapel_coverage'] ?? []),
            fn($val) => is_string($val) && trim($val) !== ''
        ));

        $dutyStartTime = trim((string)($_POST['duty_start_time'] ?? ''));
        $dutyEndTime   = trim((string)($_POST['duty_end_time'] ?? ''));
        if ($dutyStartTime !== '' && strlen($dutyStartTime) === 5) {
            $dutyStartTime .= ':00';
        }
        if ($dutyEndTime !== '' && strlen($dutyEndTime) === 5) {
            $dutyEndTime .= ':00';
        }

        if ($this->userModel->savePastorPrefs($targetPriestId, $types, $chapels, $dutyStartTime, $dutyEndTime)) {
            $msg = !empty($_POST['next_priest_id']) ? 'Changes saved! Now editing next priest.' : 'Clergy authorizations, coverage, and duty schedule updated.';
            $this->flash('success', $msg);
        } else {
            $this->flash('error', 'Failed to update clergy authorizations.');
        }
        $targetRedirectId = !empty($_POST['next_priest_id']) ? (int)$_POST['next_priest_id'] : $targetPriestId;
        $redir = 'dashboard.php?section=priest_schedule&tab=authorizations' . (!empty($_POST['priest_id']) || !empty($_POST['next_priest_id']) ? '&priest_id=' . $targetRedirectId : '');
        $this->redirect($redir);
    }

    private function handleScheduleAuditActions($action) {
        if (!$this->permissions['can_audit_health']) return;
        $id = (int)($_POST['id'] ?? 0);
        $status = $_POST['status'] ?? '';
        $newStartTime = trim((string)($_POST['new_start_time'] ?? ''));
        $allowedStatuses = ['Completed', 'Cancelled', 'Rescheduled', 'Delete'];

        if ($action === 'resolve_stale' && $id && in_array($status, $allowedStatuses, true)) {
            $event = $this->eventModel->getById($id);
            if (!$event || !$this->canManageExistingChapelRecord($event['chapel_id'] ?? null)) {
                $this->flash('error', 'You cannot resolve this event.');
                $this->redirect('dashboard.php?section=schedule_audit');
            }
            if ($status === 'Rescheduled' && $newStartTime === '') {
                $this->flash('error', 'Please choose a new date and time for rescheduling.');
            } elseif ($status === 'Rescheduled' && ($scheduleError = $this->validateScheduleInput($newStartTime, true)) !== null) {
                $this->flash('error', $scheduleError);
            } else {
                // Capture old state before modification
                $oldEvent = $this->eventModel->getById($id);
                if ($this->eventModel->resolveStaleStatus($id, $status, $newStartTime)) {
                    // Capture new state after modification (may be null for delete)
                    $newEvent = $this->eventModel->getById($id);
                    // Log audit entry with snapshots
                    AuditLogger::logFromSession(
                        $this->conn,
                        'resolved',
                        'Events',
                        "Resolved stale event ID: {$id} ({$status})",
                        $oldEvent,
                        $newEvent
                    );
                    $label = ($status === 'Rescheduled') ? 'rescheduled' : "updated to {$status}";
                    $this->flash('success', "Event #{$id} {$label}.");
                } else {
                    $this->flash('error', 'Failed to update stale event.');
                }
            }
        } elseif ($action === 'resolve_stale') {
            $this->flash('error', 'Invalid status value provided.');
        }
        $this->redirect('dashboard.php?section=schedule_audit');
    }

    private function sendConfirmedNotifications(int $eventId, ?int $pastorId, bool $notifyInApp = true): string
    {
        $pastorName = '';
        if (!empty($pastorId)) {
            $pastorRow = $this->userModel->getById($pastorId);
            if (is_array($pastorRow)) {
                $pastorName = (string)($pastorRow['full_name'] ?? $pastorRow['user'] ?? '');
            }
        }

        $event = $this->eventModel->getById($eventId);
        $mailSent = false;
        $smsSent = false;
        if ($event) {
            $mailSent = EventRequestMailer::sendApproved($this->conn, $eventId, $pastorName);
            if (!empty($event['requester_phone'])) {
                $smsSent = SmsService::sendEventApproved($this->conn, $eventId, $pastorName);
            }

            $reqUserId = (int)($event['requested_by'] ?? 0);
            if ($notifyInApp && $reqUserId > 0) {
                require_once __DIR__ . '/../models/SystemNotification.php';
                $sysNotif = new SystemNotification($this->conn);
                $celebrantText = !empty($pastorName) ? " Celebrant: Rev. {$pastorName}." : "";
                $eventTitle = $event['title'] ?: $event['event_type'];
                $dateText = !empty($event['start_time']) ? date('M j, Y', strtotime($event['start_time'])) : 'Scheduled Date';
                $sysNotif->create(
                    $reqUserId,
                    "Event Request Approved",
                    "Great news! Your request for \"{$eventTitle}\" has been approved for {$dateText}.{$celebrantText}",
                    'event_approved',
                    $eventId
                );
            }
        }

        return $this->formatEventNotificationSummary($event, $mailSent, $smsSent);
    }

    private function formatEventNotificationSummary(?array $event, bool $mailSent, bool $smsSent): string
    {
        $emailStatus = $mailSent ? '<span class="text-success">sent</span>' : '<span class="text-muted">skipped/failed</span>';
        $smsStatus   = $smsSent ? '<span class="text-success">sent</span>' : '<span class="text-muted">skipped/failed</span>';

        return "Notifications: Email {$emailStatus}, SMS {$smsStatus}";
    }

    private function normalizeNullableInt($value): ?int {
        if ($value === null || $value === '') return null;
        $intValue = (int)$value;
        return $intValue > 0 ? $intValue : null;
    }

    private function deriveEventScope(?int $chapelId): string {
        return $chapelId === null ? 'cathedral' : 'chapel';
    }

    private function validateScheduleInput(string $startTime, bool $rejectPast): ?string {
        $timestamp = strtotime($startTime);
        if ($startTime === '' || $timestamp === false) {
            return 'Please choose a valid schedule date and time.';
        }

        if ($rejectPast && $timestamp < time()) {
            return 'Schedule date and time cannot be in the past.';
        }

        $hour = (int)date('G', $timestamp);
        if ($hour < 6 || $hour >= 21) {
            return 'Events can only be scheduled between 6:00 AM and 9:00 PM.';
        }

        return null;
    }

    private function chapelExists(int $chapelId): bool {
        $stmt = $this->conn->prepare("SELECT 1 FROM chapels WHERE chapel_id = ? LIMIT 1");
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('i', $chapelId);
        $stmt->execute();
        $exists = (bool)$stmt->get_result()->fetch_row();
        $stmt->close();
        return $exists;
    }

    public function getEventViewData($section) {
        $data = [];
        if ($section === 'event_requests') {
            $chapelFilter = !in_array($this->role, ['Admin', 'Cathedral Admin']) ? $this->chapelId : null;
            $page = max(1, (int)($_GET['page'] ?? 1));
            $limit = 30;
            
            $result = $this->eventModel->getPendingRequests($chapelFilter, $page, $limit);
            
            $data['pending_requests'] = $result['records'];
            $data['total_pages'] = $result['pages'];
            $data['current_page'] = $result['page'];
            $data['total_count'] = $result['total'];
            $data['pending_count'] = $result['total']; // For the badge
            $data['limit'] = $limit;
            $data['overdue_request_count'] = count($this->eventModel->getOverduePendingRequests($chapelFilter));
        }
        return $data;
    }

    private function canManageExistingChapelRecord($recordChapelId): bool {
        if (in_array($this->role, ['Admin', 'Cathedral Admin'], true)) {
            return true;
        }
        return $this->chapelId !== null
            && $recordChapelId !== null
            && (int)$this->chapelId === (int)$recordChapelId;
    }

    /**
     * Retrieves all valid event types by combining standard core types and active service catalog items.
     * @return array<string>
     */
    public function getAllowedEventTypes(): array {
        $allowed = [
            'Mass',
            'Baptism',
            'Wedding',
            'Funeral',
            'Confirmation',
            'Fiesta',
            'Blessings',
            'Other',
            'Ecclesiastical Certificates'
        ];

        try {
            $res = $this->conn->query("SELECT name FROM services WHERE status = 'active'");
            if ($res) {
                while ($row = $res->fetch_assoc()) {
                    $name = trim($row['name'] ?? '');
                    if ($name !== '' && !in_array($name, $allowed, true)) {
                        $allowed[] = $name;
                    }
                }
            }
        } catch (Throwable $e) {
            // Graceful fallback to default core types
        }

        return $allowed;
    }

    /**
     * Validates whether a submitted event type matches allowed options.
     * @param string $eventType
     * @return bool
     */
    public function isValidEventType(string $eventType): bool {
        $eventType = trim($eventType);
        if ($eventType === '') {
            return false;
        }
        $allowed = $this->getAllowedEventTypes();
        foreach ($allowed as $valid) {
            if (strcasecmp($eventType, $valid) === 0) {
                return true;
            }
        }
        return false;
    }
}
