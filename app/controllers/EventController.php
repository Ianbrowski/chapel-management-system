<?php
require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../models/Event.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/AuditLogger.php';
require_once __DIR__ . '/../mail/EventRequestMailer.php';
require_once __DIR__ . '/../mail/DocumentReadyMailer.php';
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
                if ($loc !== null && !$this->chapelExists($loc)) {
                    $this->flash('error', 'Please select a valid chapel location.');
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
                
                $slotLocks = $this->acquireScheduleSlotLocks($loc, $pastor, $start);
                try {
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
                    } else {
                        $this->conn->rollback();
                    }
                } finally {
                    $this->releaseScheduleSlotLocks($slotLocks);
                }

                if ($createdEventId) {
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
                    if ($status === 'Confirmed' && (trim((string)$email) !== '' || trim((string)$phone) !== '' || !empty($requestedBy))) {
                        $notifSummary = $this->sendConfirmedNotifications((int)$createdEventId, $pastor, false);
                        if ($notifSummary !== '') {
                            $notifMsg = ' ' . $notifSummary;
                        }
                    } elseif ($status === 'Pending' && (trim((string)$email) !== '' || trim((string)$phone) !== '' || !empty($requestedBy))) {
                        $notifMsg = ' ' . $this->sendPendingAcknowledgmentNotifications((int)$createdEventId, false);
                    }
                    $this->flash('success', 'Event created.' . $notifMsg);
                } else {
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
                if ($loc !== null && !$this->chapelExists($loc)) {
                    $this->flash('error', 'Please select a valid chapel location.');
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

                $updatedOk = false;
                $slotLocks = $this->acquireScheduleSlotLocks($loc, $pastor, $start);
                try {
                    $this->conn->begin_transaction();
                    // Conflict Check (exclude self)
                    $conflict = $this->eventModel->checkConflict($start, $pastor, $loc, $type, $id, $customDuration, null, $locationType);
                    if ($conflict['conflict']) {
                        $this->conn->rollback();
                        $this->flash('error', "Schedule conflict detected ({$conflict['type']}): {$conflict['details']}. Please choose a different time.");
                        break;
                    }

                    $updatedOk = (bool)$this->eventModel->update($id, $title, $type, $start, $loc, $pastor, $status, $scope, $customDuration, $phone, $email, $locationType);
                    if ($updatedOk) {
                        $this->conn->commit();
                    } else {
                        $this->conn->rollback();
                    }
                } finally {
                    $this->releaseScheduleSlotLocks($slotLocks);
                }

                if ($updatedOk) {
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
                    $existing['requester_email'] ?? null,
                    $existing['location_type'] ?? 'Church'
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
                $event['requester_email'] ?? null,
                $event['location_type'] ?? 'Church'
            )) {
                AuditLogger::logFromSession($this->conn, 'updated', 'Event Requests', "Cancelled request ID: {$reqId} - {$event['title']}", $event, array_merge($event, ['status' => 'Cancelled']));
                $this->flash('success', 'Request cancelled.');
            } else {
                $this->flash('error', 'Failed to cancel request.');
            }
            $this->redirect($redirectUrl);
        }

        if ($action === 'update_certificate_request') {
            $reqId = (int)($_POST['event_id'] ?? $_POST['id'] ?? 0);
            $event = $this->eventModel->getById($reqId);
            $isCertRecord = $event && (
                ((int)($event['service_id'] ?? 0) === 8)
                || strcasecmp((string)($event['event_type'] ?? ''), 'Ecclesiastical Certificates') === 0
                || stripos((string)($event['title'] ?? ''), 'Certificate') !== false
            );
            if (!$event || !$isCertRecord) {
                $this->flash('error', 'Certificate request record not found.');
                $this->redirect($redirectUrl);
            }

            $statusNow = (string)($event['status'] ?? 'Pending');
            $isOwner = ((int)($event['requested_by'] ?? 0) === (int)$this->userId);
            $isGlobalAdmin = in_array($this->role, ['Admin', 'Cathedral Admin'], true);
            $canAdmin = $isGlobalAdmin || (!empty($this->permissions['can_approve_events']) && $this->checkChapelAccess($event['chapel_id'] ?? null));

            if (!$canAdmin && !($isOwner && $statusNow === 'Pending')) {
                $this->flash('error', 'Unauthorized to edit this certificate request.');
                $this->redirect($redirectUrl);
            }
            if (in_array($statusNow, ['Cancelled', 'Rejected'], true)) {
                $this->flash('error', 'Cancelled or declined certificate requests cannot be edited.');
                $this->redirect($redirectUrl);
            }

            $personName = trim((string)($_POST['cert_person_name'] ?? ''));
            if ($personName === '' || mb_strlen($personName) > 150) {
                $this->flash('error', 'Person\'s name on record is required.');
                $this->redirect($redirectUrl);
            }

            $certYear = trim((string)($_POST['cert_year'] ?? ''));
            if ($certYear !== '' && (!preg_match('/^\d{4}$/', $certYear) || (int)$certYear < 1800 || (int)$certYear > 2099)) {
                $this->flash('error', 'Please enter a valid 4-digit year (1800–2099) or leave it blank.');
                $this->redirect($redirectUrl);
            }

            $certPurpose = trim((string)($_POST['cert_purpose'] ?? ''));
            if ($certPurpose === '') {
                $certPurpose = trim((string)($event['cert_purpose'] ?? '')) ?: 'Personal Records';
            }

            $pickupDate = trim((string)($_POST['pickup_date'] ?? ''));
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $pickupDate)) {
                $this->flash('error', 'Please provide a valid target pickup date.');
                $this->redirect($redirectUrl);
            }
            $existingPickupDate = !empty($event['start_time']) ? date('Y-m-d', strtotime($event['start_time'])) : '';
            if (!$canAdmin && $pickupDate !== $existingPickupDate && $pickupDate < date('Y-m-d')) {
                $this->flash('error', 'Target pickup date cannot be in the past.');
                $this->redirect($redirectUrl);
            }
            // Match parish office schedule: closed Mondays -> bump to Tuesday if changed to Monday
            if ($pickupDate !== $existingPickupDate && (int)date('N', strtotime($pickupDate)) === 1) {
                $pickupDate = date('Y-m-d', strtotime($pickupDate . ' +1 day'));
            }
            $existingTimePart = !empty($event['start_time']) ? date('H:i:s', strtotime($event['start_time'])) : '08:00:00';
            $startTime = $pickupDate . ' ' . $existingTimePart;

            $reqName  = trim((string)($_POST['requester_name'] ?? ($event['requester_name'] ?? '')));
            $reqPhone = trim((string)($_POST['requester_phone'] ?? ''));
            $reqEmail = trim((string)($_POST['requester_email'] ?? ''));
            if ($reqEmail !== '' && !filter_var($reqEmail, FILTER_VALIDATE_EMAIL)) {
                $this->flash('error', 'Please enter a valid contact email address.');
                $this->redirect($redirectUrl);
            }
            if ($reqPhone !== '' && !preg_match('/^[0-9\+\s\-()]{7,25}$/', $reqPhone)) {
                $this->flash('error', 'Please enter a valid contact phone number.');
                $this->redirect($redirectUrl);
            }

            // Issuing office / chapel scope
            $loc = isset($event['chapel_id']) && (int)$event['chapel_id'] > 0 ? (int)$event['chapel_id'] : null;
            if ($isGlobalAdmin && array_key_exists('chapel_id', $_POST)) {
                $loc = $this->normalizeNullableInt($_POST['chapel_id']);
                if ($loc !== null && !$this->chapelExists($loc)) {
                    $this->flash('error', 'Please select a valid issuing office.');
                    $this->redirect($redirectUrl);
                }
            }
            $scope = $this->deriveEventScope($loc);

            // Linked archival record (admin/staff only)
            $linkedRecordId = !empty($event['linked_record_id']) ? (int)$event['linked_record_id'] : null;
            if ($canAdmin && array_key_exists('linked_record_id', $_POST)) {
                $postedRecId = (int)$_POST['linked_record_id'];
                if ($postedRecId <= 0) {
                    $linkedRecordId = null;
                } else {
                    $recStmt = $this->conn->prepare("SELECT record_id, chapel_id FROM sacramental_records WHERE record_id = ? AND is_archived = 0 LIMIT 1");
                    $recRow = null;
                    if ($recStmt) {
                        $recStmt->bind_param('i', $postedRecId);
                        $recStmt->execute();
                        $recRow = $recStmt->get_result()->fetch_assoc();
                        $recStmt->close();
                    }
                    if (!$recRow || (!$isGlobalAdmin && !$this->checkChapelAccess($recRow['chapel_id'] ?? null))) {
                        $this->flash('error', 'Selected archival record could not be linked under your chapel scope.');
                        $this->redirect($redirectUrl);
                    }
                    $linkedRecordId = (int)$recRow['record_id'];
                }
            }

            // Preserve fee snapshot once paid or Released; otherwise allow updating document type & copies
            $paidMap = $this->eventModel->getPaidAmountsMap([$reqId]);
            $paidFee = (float)($paidMap[$reqId] ?? 0);
            $canModifyBilling = ($statusNow === 'Pending' || ($canAdmin && $statusNow === 'Confirmed')) && $paidFee <= 0.01;

            $variantId = !empty($event['variant_id']) ? (int)$event['variant_id'] : null;
            $copies = max(1, (int)($event['cert_copies'] ?? 1));
            $serviceFee = (float)($event['service_fee'] ?? 0);
            $totalFee = (float)($event['total_fee'] ?? 0);
            $variantLabel = trim(preg_replace('/\s*:.*$/', '', (string)($event['title'] ?? 'Certificate'))) ?: 'Certificate';

            require_once __DIR__ . '/../models/ServiceModel.php';
            $srvModel = new ServiceModel($this->conn);
            if ($variantId) {
                $existingVariant = $srvModel->getVariantById($variantId);
                if ($existingVariant && !empty($existingVariant['name'])) {
                    $variantLabel = trim((string)$existingVariant['name']);
                }
            }

            if ($canModifyBilling) {
                $postedVariant = (int)($_POST['variant_id'] ?? ($variantId ?? 0));
                $postedCopies = max(1, min(25, (int)($_POST['certificate_copies'] ?? $copies)));
                $svcId = !empty($event['service_id']) ? (int)$event['service_id'] : 8;
                if ($postedVariant > 0) {
                    try {
                        $calc = $srvModel->calculateAndVerifyFee($svcId, $postedVariant, [], $postedCopies);
                        $vInfo = $srvModel->getVariantById($postedVariant);
                        $variantId = $postedVariant;
                        $variantLabel = trim((string)($vInfo['name'] ?? $variantLabel));
                        $copies = $postedCopies;
                        $serviceFee = (float)$calc['base_fee'];
                        $totalFee = (float)$calc['total_fee'];
                    } catch (Exception $ex) {
                        $this->flash('error', $ex->getMessage());
                        $this->redirect($redirectUrl);
                    }
                } else {
                    $unitRate = $copies > 0 ? ($totalFee / $copies) : 200.00;
                    $copies = $postedCopies;
                    $serviceFee = round($unitRate * $copies, 2);
                    $totalFee = $serviceFee;
                }
            }

            $newTitle = $variantLabel . ': ' . $personName
                . ($certYear !== '' ? " ({$certYear})" : '')
                . ($certPurpose !== '' ? " - {$certPurpose}" : '');

            $ok = $this->eventModel->updateCertificateRequest($reqId, [
                'title'           => $newTitle,
                'variant_id'      => $variantId,
                'start_time'      => $startTime,
                'chapel_id'       => $loc,
                'event_scope'     => $scope,
                'pastor_id'       => $event['pastor_id'] ?? null,
                'requester_phone' => $reqPhone,
                'requester_email' => $reqEmail,
                'service_fee'     => $serviceFee,
                'total_fee'       => $totalFee,
                'meta'            => [
                    'requester_name'   => $reqName,
                    'cert_person_name' => $personName,
                    'cert_year'        => $certYear,
                    'cert_purpose'     => $certPurpose,
                    'cert_copies'      => $copies,
                    'linked_record_id' => $linkedRecordId,
                ],
            ]);

            if ($ok) {
                AuditLogger::logFromSession($this->conn, 'updated', 'Certificates', "Updated certificate request ID: {$reqId} - {$newTitle}", $event, [
                    'title'            => $newTitle,
                    'start_time'       => $startTime,
                    'chapel_id'        => $loc,
                    'cert_person_name' => $personName,
                    'cert_year'        => $certYear,
                    'cert_purpose'     => $certPurpose,
                    'cert_copies'      => $copies,
                    'linked_record_id' => $linkedRecordId,
                    'total_fee'        => $totalFee,
                ]);
                $this->flash('success', 'Certificate request details updated.');
            } else {
                $this->flash('error', 'Failed to update certificate request details.');
            }
            $this->redirect($redirectUrl);
        }

        if ($action === 'link_certificate_record') {
            $reqId = (int)($_POST['event_id'] ?? $_POST['id'] ?? 0);
            $recId = (int)($_POST['linked_record_id'] ?? $_POST['record_id'] ?? 0);
            $event = $this->eventModel->getById($reqId);
            $isGlobalAdmin = in_array($this->role, ['Admin', 'Cathedral Admin'], true);
            $canAdmin = $isGlobalAdmin || (!empty($this->permissions['can_approve_events']) && $event && $this->checkChapelAccess($event['chapel_id'] ?? null));
            if (!$event || !$canAdmin || $recId <= 0) {
                $this->flash('error', 'Unauthorized or invalid archival record link request.');
                $this->redirect($redirectUrl);
            }

            $recStmt = $this->conn->prepare("SELECT record_id, chapel_id, person_name, reference_id FROM sacramental_records WHERE record_id = ? AND is_archived = 0 LIMIT 1");
            $recRow = null;
            if ($recStmt) {
                $recStmt->bind_param('i', $recId);
                $recStmt->execute();
                $recRow = $recStmt->get_result()->fetch_assoc();
                $recStmt->close();
            }
            if (!$recRow || (!$isGlobalAdmin && !$this->checkChapelAccess($recRow['chapel_id'] ?? null))) {
                $this->flash('error', 'Selected archival record could not be linked under your chapel scope.');
                $this->redirect($redirectUrl);
            }

            if ($this->eventModel->linkCertificateRecord($reqId, $recId)) {
                $refLabel = !empty($recRow['reference_id']) ? " ({$recRow['reference_id']})" : '';
                AuditLogger::logFromSession($this->conn, 'updated', 'Certificates', "Linked certificate request ID {$reqId} to record #{$recId}{$refLabel}", $event, ['linked_record_id' => $recId]);
                $this->flash('success', "Linked to archive record #" . $recId . $refLabel . '.');
            } else {
                $this->flash('error', 'Failed to link archival record.');
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
        
        // Personnel Isolation: Staff/Chapel Admins default to their branch; walk-in/portal certs stay scoped to their branch
        if (!in_array($this->role, ['Admin', 'Cathedral Admin'])) {
            if ($isCertSection && !empty($this->permissions['can_approve_events']) && !empty($this->chapelId)) {
                $loc = (int)$this->chapelId;
                $scope = 'chapel';
            } elseif ($loc === null || $loc == 0) {
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

        $requestMeta = [
            'requester_name' => trim((string)($_POST['requester_name'] ?? '')),
        ];
        if ($isCertSection) {
            $requestMeta['cert_person_name'] = trim((string)($_POST['cert_person_name'] ?? ''));
            $requestMeta['cert_year']        = trim((string)($_POST['cert_year'] ?? ''));
            $requestMeta['cert_purpose']     = trim((string)($_POST['cert_purpose'] ?? ''));
            $requestMeta['cert_copies']      = max(1, (int)($_POST['certificate_copies'] ?? 1));
            $linkedId = !empty($_POST['linked_record_id']) ? (int)$_POST['linked_record_id'] : null;
            if ($linkedId === null && $requestMeta['cert_person_name'] !== '') {
                $linkedId = $this->eventModel->findUniqueArchivalRecordMatch(
                    $requestMeta['cert_person_name'],
                    (string)$title,
                    $loc,
                    $requestMeta['cert_year']
                );
            }
            $requestMeta['linked_record_id'] = $linkedId;
        }

        // Prevent slot squatting: cap active Pending sanctuary requests per Member
        if ($this->role === 'Member' && $status === 'Pending' && !$isCertSection && $this->userId > 0) {
            $cntStmt = $this->conn->prepare(
                "SELECT COUNT(*) FROM events
                  WHERE requested_by = ?
                    AND status = 'Pending'
                    AND is_archived = 0
                    AND COALESCE(event_type, '') != 'Ecclesiastical Certificates'
                    AND COALESCE(event_type, '') NOT LIKE '%Certificate%'
                    AND COALESCE(event_type, '') NOT LIKE '%Thanksgiving%'
                    AND COALESCE(event_type, '') NOT LIKE '%Requiem%'
                    AND COALESCE(event_type, '') NOT LIKE '%Intention%'"
            );
            if ($cntStmt) {
                $cntStmt->bind_param('i', $this->userId);
                $cntStmt->execute();
                $pendingOpen = (int)($cntStmt->get_result()->fetch_row()[0] ?? 0);
                $cntStmt->close();
                if ($pendingOpen >= 5) {
                    $this->flash('error', 'You currently have 5 pending sanctuary requests awaiting review. Please wait for approval or cancel an open request first.');
                    $this->redirect($redirectUrl);
                    return;
                }
            }
        }

        $slotLocks = (!$isCertSection && $type !== 'Ecclesiastical Certificates')
            ? $this->acquireScheduleSlotLocks($loc, $pastor, $start)
            : [];
        $eventId = false;
        try {
            $this->conn->begin_transaction();
            // Conflict Check: Certificates and shared Mass Intentions bypass altar exclusivity
            if (!$isCertSection && $type !== 'Ecclesiastical Certificates') {
                $conflict = $this->eventModel->checkConflict($start, $pastor, $loc, $type, null, $customDuration, $serviceId, $locationType);
                if ($conflict['conflict']) {
                    $this->conn->rollback();
                    $this->flash('error', "Schedule conflict detected ({$conflict['type']}): {$conflict['details']}. Please choose a different time.");
                    $this->redirect($redirectUrl);
                    return;
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
                    return;
                }
            }

            $eventId = $this->eventModel->requestEvent($title, $type, $start, $loc, $scope, $this->userId, $email, $status, $pastor, $customDuration, $phone, $serviceId, $variantId, $serviceFee, $addonsFee, $totalFee, $locationType, $requestMeta);
            if ($eventId) {
                foreach ($verifiedAddons as $ad) {
                    $this->eventModel->addRequestAddon((int)$eventId, (int)$ad['addon_id'], $ad['addon_name'], (float)$ad['unit_price'], (int)$ad['quantity'], (float)$ad['subtotal']);
                }
                $this->conn->commit();
            } else {
                $this->conn->rollback();
            }
        } finally {
            $this->releaseScheduleSlotLocks($slotLocks);
        }

        if ($eventId) {
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
                $notifMsg = ' ' . $this->sendPendingAcknowledgmentNotifications((int)$eventId, $isCertSection);

                // Trigger Member-specific In-App Notification
                if ($this->userId > 0) {
                    require_once __DIR__ . '/../models/SystemNotification.php';
                    $sysNotif = new SystemNotification($this->conn);
                    $notifTitle = $isCertSection ? "Certificate Request Submitted" : "Event Request Submitted";
                    $notifBody = $isCertSection
                        ? "Your certificate application \"{$title}\" has been received and is pending archive verification."
                        : "Your request for \"{$title}\" has been submitted successfully and is pending review by the Cathedral parish administration.";
                    $sysNotif->create(
                        $this->userId,
                        $notifTitle,
                        $notifBody,
                        $isCertSection ? 'cert_submitted' : 'event_submitted',
                        (int)$eventId
                    );
                }
            } elseif ($status === 'Confirmed' && (trim((string)$email) !== '' || trim((string)$phone) !== '' || $this->userId > 0)) {
                $notifSummary = $this->sendConfirmedNotifications((int)$eventId, $pastor, false);
                if ($notifSummary !== '') {
                    $notifMsg = ' ' . $notifSummary;
                }
            }

            $msg = ($status === 'Confirmed')
                ? ('Event created and confirmed.' . $notifMsg)
                : (($isCertSection ? 'Certificate application submitted successfully. The Parish Office will review and prepare your documents for pickup.' : 'Event request submitted.') . $notifMsg);
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
            $recType = (string)($record['event_type'] ?? '');
            $recServiceId = isset($record['service_id']) && (int)$record['service_id'] > 0 ? (int)$record['service_id'] : null;
            $isCertRecord = ($recType === 'Ecclesiastical Certificates' || stripos($recType, 'Certificate') !== false || $recServiceId === 8);

            $recChapel = $this->normalizeNullableInt($record['chapel_id'] ?? null);
            $slotLocks = !$isCertRecord ? $this->acquireScheduleSlotLocks($recChapel, $pastor, (string)$record['start_time']) : [];
            $approvedOk = false;
            try {
                $this->conn->begin_transaction();
                if (!$isCertRecord) {
                    $conflict = $this->eventModel->checkConflict($record['start_time'], $pastor, $record['chapel_id'], $recType, $id, $record['custom_duration'], $recServiceId, $record['location_type'] ?? 'Church');
                    if ($conflict['conflict']) {
                        $this->conn->rollback();
                        $this->flash('error', "Schedule conflict detected during approval ({$conflict['type']}): {$conflict['details']}.");
                        $this->redirect($redirectUrl);
                        return;
                    }
                }

                $approvedOk = (bool)$this->eventModel->approveRequest($id, $pastor);
                if ($approvedOk) {
                    if ($isCertRecord && empty($record['linked_record_id'])) {
                        $lookupName = trim((string)($record['cert_person_name'] ?? ''));
                        if ($lookupName === '' && !empty($record['title'])) {
                            $lookupName = preg_replace('/^[^:]+:\s*/', '', (string)$record['title']);
                            $lookupName = trim(preg_replace('/\s*-\s*.*$/', '', $lookupName));
                            $lookupName = trim(preg_replace('/\s*\(\d{4}\)$/', '', $lookupName));
                        }
                        if ($lookupName !== '') {
                            $matchedId = $this->eventModel->findUniqueArchivalRecordMatch(
                                $lookupName,
                                (string)($record['title'] ?? ''),
                                $recChapel,
                                (string)($record['cert_year'] ?? '')
                            );
                            if ($matchedId !== null) {
                                $this->eventModel->linkCertificateRecord($id, $matchedId);
                            }
                        }
                    }
                    $this->conn->commit();
                } else {
                    $this->conn->rollback();
                }
            } finally {
                $this->releaseScheduleSlotLocks($slotLocks);
            }

            if (!$approvedOk) {
                $this->flash('error', 'Failed to approve request.');
                $this->redirect($redirectUrl);
                return;
            }

            $notifSummary = $this->sendConfirmedNotifications((int)$id, $pastor, true);

            AuditLogger::logFromSession($this->conn, 'approved', $isCertRecord ? 'Certificates' : 'Event Requests', ($isCertRecord ? "Marked certificate ready ID: {$id}" : "Approved request ID: {$id}"));
            $this->flash('success', ($isCertRecord ? 'Certificate marked as ready. ' : 'Request approved. ') . $notifSummary);
        } elseif ($action === 'reject') {
            if (!$this->eventModel->rejectRequest($id, $note)) {
                $this->flash('error', 'Failed to reject request.');
                $this->redirect($redirectUrl);
            }

            $event = $this->eventModel->getById($id);
            $mailSent = false;
            $smsSent = false;
            $isCertRecord = $event && (
                ((int)($event['service_id'] ?? 0) === 8)
                || strcasecmp((string)($event['event_type'] ?? ''), 'Ecclesiastical Certificates') === 0
                || stripos((string)($event['title'] ?? ''), 'Certificate') !== false
            );
            if ($event) {
                $mailSent = EventRequestMailer::sendRejected($this->conn, $id, $note);
                $smsSent = SmsService::sendEventRejected($this->conn, $id, $note);

                // Member-specific in-app notification
                $reqUserId = (int)($event['requested_by'] ?? 0);
                if ($reqUserId > 0) {
                    require_once __DIR__ . '/../models/SystemNotification.php';
                    $sysNotif = new SystemNotification($this->conn);
                    $eventTitle = $event['title'] ?: ($isCertRecord ? 'Certificate Request' : $event['event_type']);
                    $reasonText = !empty($note) ? " Reason: {$note}" : " Please contact the parish office for details.";
                    $sysNotif->create(
                        $reqUserId,
                        $isCertRecord ? "Certificate Application Update" : "Event Request Update",
                        $isCertRecord
                            ? "Your certificate application for \"{$eventTitle}\" could not be processed at this time.{$reasonText}"
                            : "Your request for \"{$eventTitle}\" could not be approved at this time.{$reasonText}",
                        $isCertRecord ? 'cert_rejected' : 'event_rejected',
                        (int)$id
                    );
                }
            }

            AuditLogger::logFromSession($this->conn, 'rejected', $isCertRecord ? 'Certificates' : 'Event Requests', "Rejected request ID: {$id}");
            $this->flash('success', ($isCertRecord ? 'Certificate request declined. ' : 'Request rejected. ') . $this->formatEventNotificationSummary($event, $mailSent, $smsSent));
        } elseif ($action === 'resend_pickup_notice') {
            $recType = (string)($record['event_type'] ?? '');
            $recServiceId = isset($record['service_id']) && (int)$record['service_id'] > 0 ? (int)$record['service_id'] : null;
            $isCertRecord = ($recType === 'Ecclesiastical Certificates' || stripos($recType, 'Certificate') !== false || $recServiceId === 8);
            if (!$isCertRecord || ($record['status'] ?? '') !== 'Confirmed') {
                $this->flash('error', 'Only Ready / Confirmed certificates can have a pickup notice re-sent.');
                $this->redirect($redirectUrl);
            }

            $notifSummary = $this->sendConfirmedNotifications((int)$id, $pastor, false);
            AuditLogger::logFromSession($this->conn, 'updated', 'Certificates', "Re-sent pickup notification for certificate ID: {$id}");
            $this->flash('success', 'Pickup notification re-sent. ' . $notifSummary);
        } elseif ($action === 'complete') {
            if (!$this->eventModel->completeRequest($id)) {
                $this->flash('error', 'Only Ready / Confirmed requests can be marked as completed or released.');
                $this->redirect($redirectUrl);
            }

            $event = $this->eventModel->getById($id);
            $isCertRecord = ((int)($event['service_id'] ?? 0) === 8)
                || strcasecmp((string)($event['event_type'] ?? ''), 'Ecclesiastical Certificates') === 0
                || stripos((string)($event['title'] ?? ''), 'Certificate') !== false;
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
                    $sysNotif->create($reqUserId, $notifTitle, $notifBody, $isCertRecord ? 'cert_released' : 'event_completed', (int)$id);
                }

                // Log formal issuance to certificate_issuances if linked to an archival sacramental record
                $linkedRecId = (int)($event['linked_record_id'] ?? 0);
                if ($isCertRecord && $linkedRecId > 0) {
                    $chkRec = $this->conn->prepare("SELECT record_id, person_name FROM sacramental_records WHERE record_id = ? LIMIT 1");
                    if ($chkRec) {
                        $chkRec->bind_param('i', $linkedRecId);
                        $chkRec->execute();
                        $recRow = $chkRec->get_result()->fetch_assoc();
                        $chkRec->close();
                        if ($recRow) {
                            require_once __DIR__ . '/../services/SacramentalCertificateService.php';
                            $certSvc = new SacramentalCertificateService($this->conn);
                            $issuedTo = trim((string)($event['cert_person_name'] ?? ''))
                                ?: trim((string)($event['requester_name'] ?? ''))
                                ?: trim((string)($recRow['person_name'] ?? ''))
                                ?: ($event['title'] ?: 'Parishioner');
                            $purpose = trim((string)($event['cert_purpose'] ?? '')) ?: ('Certificate Request #' . $id);
                            $connRef = $this->conn;
                            $certSvc->logCertificateIssuance(
                                $linkedRecId,
                                $issuedTo,
                                $purpose,
                                (int)$this->userId,
                                static function (int $recordId, string $act, int $uid, $old, $new) use ($connRef): void {
                                    AuditLogger::logFromSession($connRef, 'created', 'Sacramental Certificates', "Issued certificate for record ID {$recordId}: " . ($new['cert_no'] ?? ''), $old, $new);
                                }
                            );
                        }
                    }
                }
            }

            AuditLogger::logFromSession($this->conn, 'completed', $isCertRecord ? 'Certificates' : 'Event Requests', "Marked ID {$id} as Completed/Released");
            $this->flash('success', $isCertRecord ? 'Certificate marked as released.' : 'Event marked as completed.');
        } elseif ($action === 'record_payment') {
            if (in_array($record['status'] ?? '', ['Cancelled', 'Rejected'], true)) {
                $this->flash('error', 'Cannot record payment for a cancelled or declined request.');
                $this->redirect($redirectUrl);
            }

            $amount = round((float)($_POST['amount'] ?? 0), 2);
            $method = trim((string)($_POST['payment_method'] ?? 'Cash'));
            $allowedMethods = ['Cash', 'GCash', 'Bank Transfer', 'Maya', 'Other'];
            if (!in_array($method, $allowedMethods, true)) {
                $method = 'Cash';
            }
            $dateRecv = trim((string)($_POST['date_received'] ?? date('Y-m-d')));
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateRecv) || $dateRecv > date('Y-m-d') || $dateRecv < '1950-01-01') {
                $dateRecv = date('Y-m-d');
            }
            $donorRef = mb_substr(trim((string)($_POST['donor_ref_id'] ?? '')), 0, 120);

            if ($amount <= 0 || $amount > 100000000) {
                $this->flash('error', 'Payment amount must be greater than zero.');
                $this->redirect($redirectUrl);
            }

            $totalFee = (float)($record['total_fee'] ?? 0);
            if ($totalFee > 0) {
                $paidMap = $this->eventModel->getPaidAmountsMap([$id]);
                $alreadyPaid = (float)($paidMap[$id] ?? 0);
                $remaining = round($totalFee - $alreadyPaid, 2);
                if ($remaining <= 0.01) {
                    $this->flash('error', 'This request has already been paid in full.');
                    $this->redirect($redirectUrl);
                }
                if ($amount > ($remaining + 0.01)) {
                    $this->flash('error', 'Payment amount (PHP ' . number_format($amount, 2) . ') exceeds the remaining balance of PHP ' . number_format($remaining, 2) . '.');
                    $this->redirect($redirectUrl);
                }
            }

            require_once __DIR__ . '/../models/Donation.php';
            $donationModel = new Donation($this->conn);
            if ($donorRef !== '' && $donationModel->activeDonorRefExists($donorRef)) {
                $this->flash('error', 'Duplicate payment reference: an active payment with reference "' . $donorRef . '" already exists.');
                $this->redirect($redirectUrl);
            }

            $isCertRecord = ((int)($record['service_id'] ?? 0) === 8)
                || strcasecmp((string)($record['event_type'] ?? ''), 'Ecclesiastical Certificates') === 0
                || stripos((string)($record['title'] ?? ''), 'Certificate') !== false;
            $padId = str_pad((string)$id, 4, '0', STR_PAD_LEFT);
            $refId = ($isCertRecord ? 'REQ-' : 'EV-') . $padId;

            $donorName = trim((string)($record['requester_name'] ?? ''))
                ?: trim((string)($record['cert_person_name'] ?? ''));
            if ($donorName === '' && !empty($record['requested_by'])) {
                $reqUser = $this->userModel->getById((int)$record['requested_by']);
                if (is_array($reqUser) && !in_array((string)($reqUser['role'] ?? ''), ['Admin', 'Cathedral Admin', 'Chapel Admin', 'Staff'], true)) {
                    $donorName = (string)($reqUser['full_name'] ?: ($reqUser['user'] ?: ''));
                }
            }
            if ($donorName === '' && !empty($record['requester_email'])) {
                $donorName = (string)$record['requester_email'];
            }
            if ($donorName === '') {
                $donorName = 'Parishioner';
            }
            $donorName = mb_substr(trim($donorName), 0, 100);
            $purposePrefix = $isCertRecord ? 'Certificate Fee: ' : 'Service Fee: ';
            $purpose = mb_substr($purposePrefix . trim((string)($record['title'] ?? 'Service Request')), 0, 100);

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
                        $isCertRecord ? 'cert_payment_recorded' : 'payment_recorded',
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
        $normalizeTime = static function ($t): string {
            $t = trim((string)$t);
            if ($t !== '' && strlen($t) === 5) {
                $t .= ':00';
            }
            return preg_match('/^\d{2}:\d{2}:\d{2}$/', $t) ? $t : '';
        };

        $stagedRaw = trim((string)($_POST['staged_prefs'] ?? ''));
        $stagedList = [];
        if ($stagedRaw !== '') {
            $decoded = json_decode($stagedRaw, true);
            if (is_array($decoded)) {
                foreach ($decoded as $pIdKey => $entry) {
                    $pid = (int)$pIdKey;
                    if ($pid <= 0 || !is_array($entry)) {
                        continue;
                    }
                    $stagedList[$pid] = [
                        'event_types' => array_values(array_filter(
                            (array)($entry['event_types'] ?? []),
                            static fn($t) => in_array($t, $allowedTypes, true)
                        )),
                        'chapel_coverage' => array_values(array_filter(
                            (array)($entry['chapel_coverage'] ?? []),
                            static fn($val) => is_string($val) && trim($val) !== ''
                        )),
                        'duty_start_time' => $normalizeTime($entry['duty_start_time'] ?? ''),
                        'duty_end_time'   => $normalizeTime($entry['duty_end_time'] ?? ''),
                    ];
                }
            }
        }

        if (!empty($stagedList)) {
            $savedNames = [];
            $allOk = true;
            $chkStmt = $this->conn->prepare("SELECT COALESCE(NULLIF(full_name, ''), user) AS display_name FROM users WHERE id = ? AND role IN ('Priest', 'Pastor') LIMIT 1");
            foreach ($stagedList as $pid => $payload) {
                $pName = 'Priest #' . $pid;
                if ($chkStmt) {
                    $chkStmt->bind_param('i', $pid);
                    $chkStmt->execute();
                    $row = $chkStmt->get_result()->fetch_assoc();
                    if (!$row) {
                        continue;
                    }
                    $pName = $row['display_name'] ?: $pName;
                }
                if ($this->userModel->savePastorPrefs($pid, $payload['event_types'], $payload['chapel_coverage'], $payload['duty_start_time'], $payload['duty_end_time'])) {
                    $savedNames[] = $pName;
                } else {
                    $allOk = false;
                }
            }
            if ($chkStmt) {
                $chkStmt->close();
            }

            if (!empty($savedNames) && $allOk) {
                $summary = count($savedNames) === 1
                    ? 'Clergy authorizations, coverage, and duty schedule updated for ' . $savedNames[0] . '.'
                    : 'Saved changes for ' . count($savedNames) . ' priests: ' . implode(', ', $savedNames) . '.';
                AuditLogger::logFromSession($this->conn, 'updated', 'Clergy Shifts', $summary);
                $this->flash('success', $summary);
            } elseif (!empty($savedNames)) {
                $this->flash('error', 'Saved some priest updates, but one or more failed.');
            } else {
                $this->flash('error', 'Failed to update clergy authorizations.');
            }
        } else {
            $types = array_values(array_filter(
                (array)($_POST['event_types'] ?? []),
                static fn($t) => in_array($t, $allowedTypes, true)
            ));
            $chapels = array_values(array_filter(
                (array)($_POST['chapel_coverage'] ?? []),
                static fn($val) => is_string($val) && trim($val) !== ''
            ));

            $dutyStartTime = $normalizeTime($_POST['duty_start_time'] ?? '');
            $dutyEndTime   = $normalizeTime($_POST['duty_end_time'] ?? '');

            if ($this->userModel->savePastorPrefs($targetPriestId, $types, $chapels, $dutyStartTime, $dutyEndTime)) {
                $this->flash('success', 'Clergy authorizations, coverage, and duty schedule updated.');
            } else {
                $this->flash('error', 'Failed to update clergy authorizations.');
            }
        }

        $targetRedirectId = !empty($_POST['next_priest_id']) ? (int)$_POST['next_priest_id'] : $targetPriestId;
        $redir = 'dashboard.php?section=priest_schedule&tab=authorizations' . ($targetRedirectId > 0 ? '&priest_id=' . $targetRedirectId : '');
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
            $isCertRecord = ((int)($event['service_id'] ?? 0) === 8)
                || strcasecmp((string)($event['event_type'] ?? ''), 'Ecclesiastical Certificates') === 0
                || stripos((string)($event['title'] ?? ''), 'Certificate') !== false;
            $reqUserId = (int)($event['requested_by'] ?? 0);
            $dateText = !empty($event['start_time']) ? date('M j, Y', strtotime($event['start_time'])) : 'Scheduled Date';

            if ($isCertRecord) {
                $docLabel = trim((string)($event['title'] ?: 'Ecclesiastical Certificate'));
                $pickupDateFmt = !empty($event['start_time'])
                    ? date('F d, Y (Tue–Sun office hours)', strtotime($event['start_time']))
                    : 'During Parish Office Hours (Tue–Sun, 8:00 AM – 5:00 PM)';

                $toEmail = trim((string)($event['requester_email'] ?? ''));
                $toPhone = trim((string)($event['requester_phone'] ?? ''));
                $recipientName = trim((string)($event['requester_name'] ?? ''))
                    ?: trim((string)($event['cert_person_name'] ?? ''))
                    ?: 'Parishioner';
                if ($reqUserId > 0) {
                    $reqUser = $this->userModel->getById($reqUserId);
                    if (is_array($reqUser)) {
                        $uRole = (string)($reqUser['role'] ?? '');
                        if (!in_array($uRole, ['Admin', 'Cathedral Admin', 'Chapel Admin', 'Staff'], true)) {
                            if ($recipientName === 'Parishioner') {
                                $recipientName = trim((string)($reqUser['full_name'] ?: $reqUser['user'] ?: 'Parishioner'));
                            }
                            if ($toEmail === '') {
                                $toEmail = trim((string)($reqUser['email'] ?? ''));
                            }
                            if ($toPhone === '') {
                                $toPhone = trim((string)($reqUser['phone'] ?? ''));
                            }
                        }
                    }
                }

                if ($toEmail !== '' && filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
                    $mailSent = DocumentReadyMailer::sendPickupNotification($toEmail, $recipientName, $docLabel, $pickupDateFmt);
                }
                if ($toPhone !== '') {
                    $smsSent = SmsService::sendPickupNotice($toPhone, $recipientName, $docLabel);
                }
                if ($notifyInApp && $reqUserId > 0) {
                    require_once __DIR__ . '/../models/SystemNotification.php';
                    $sysNotif = new SystemNotification($this->conn);
                    $sysNotif->create(
                        $reqUserId,
                        "Certificate Ready for Pickup",
                        "Your requested \"{$docLabel}\" is ready for pickup at the Parish Office (Target date: {$dateText}). Please bring a valid ID.",
                        'cert_ready',
                        $eventId
                    );
                }
            } else {
                $mailSent = EventRequestMailer::sendApproved($this->conn, $eventId, $pastorName);
                $smsSent = SmsService::sendEventApproved($this->conn, $eventId, $pastorName);

                if ($notifyInApp && $reqUserId > 0) {
                    require_once __DIR__ . '/../models/SystemNotification.php';
                    $sysNotif = new SystemNotification($this->conn);
                    $celebrantText = !empty($pastorName) ? " Celebrant: Rev. {$pastorName}." : "";
                    $eventTitle = $event['title'] ?: $event['event_type'];
                    $sysNotif->create(
                        $reqUserId,
                        "Event Request Approved",
                        "Great news! Your request for \"{$eventTitle}\" has been approved for {$dateText}.{$celebrantText}",
                        'event_approved',
                        $eventId
                    );
                }
            }
        }

        return $this->formatEventNotificationSummary($event, $mailSent, $smsSent);
    }

    private function sendPendingAcknowledgmentNotifications(int $eventId, bool $isCertSection): string
    {
        $mailSent = EventRequestMailer::sendAcknowledgment($this->conn, $eventId);
        $smsSent = SmsService::sendEventReceived($this->conn, $eventId, $isCertSection);
        return $this->formatEventNotificationSummary(null, $mailSent, $smsSent);
    }

    private function formatEventNotificationSummary(?array $event, bool $mailSent, bool $smsSent): string
    {
        if ($mailSent) {
            $emailStatus = 'sent';
        } else {
            $err = trim((string)($GLOBALS['lastMailError'] ?? ''));
            $emailStatus = $err !== '' ? "failed ({$err})" : 'skipped (no email)';
        }
        if ($smsSent) {
            $smsProv = class_exists('SmsService') ? trim((string) SmsService::getLastProvider()) : '';
            $smsStatus = ($smsProv === 'mock') ? 'mock-logged (SMS env not configured)' : 'sent';
        } else {
            $smsErr = class_exists('SmsService') ? trim((string) SmsService::getLastError()) : '';
            $smsStatus = $smsErr !== '' ? "failed ({$smsErr})" : 'skipped (no phone)';
        }

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

    /**
     * Serializes concurrent writes targeting the same empty venue shift or priest date
     * via MySQL advisory locks so phantom double-inserts cannot pass FOR UPDATE.
     */
    private function acquireScheduleSlotLocks(?int $chapelId, ?int $pastorId, string $startTime): array {
        $ts = strtotime($startTime);
        if ($ts === false) {
            return [];
        }
        $dateStr = date('Ymd', $ts);
        $shift = ((int)date('H', $ts) < 12) ? 'am' : 'pm';
        $keys = ['ifi_slot_' . ($chapelId ?? 0) . '_' . $dateStr . '_' . $shift];
        if ($pastorId !== null && $pastorId > 0) {
            $keys[] = 'ifi_priest_' . $pastorId . '_' . $dateStr;
        }
        sort($keys);

        $acquired = [];
        foreach ($keys as $k) {
            $stmt = @$this->conn->prepare('SELECT GET_LOCK(?, 5)');
            if ($stmt) {
                $stmt->bind_param('s', $k);
                if ($stmt->execute()) {
                    $row = $stmt->get_result()->fetch_row();
                    if (!empty($row[0])) {
                        $acquired[] = $k;
                    }
                }
                $stmt->close();
            }
        }
        return $acquired;
    }

    private function releaseScheduleSlotLocks(array $keys): void {
        foreach (array_reverse($keys) as $k) {
            $stmt = @$this->conn->prepare('SELECT RELEASE_LOCK(?)');
            if ($stmt) {
                $stmt->bind_param('s', $k);
                @$stmt->execute();
                $stmt->close();
            }
        }
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
