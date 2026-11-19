<?php
require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../models/SacramentalRecord.php';
require_once __DIR__ . '/../models/AuditLogger.php';

/**
 * SacramentalController — Manages Baptism, Wedding, Funeral, Confirmation, and Communion records.
 */
class SacramentalController extends BaseController {
    private $recordModel;

    public function __construct($conn) {
        parent::__construct($conn);
        $this->recordModel = new SacramentalRecord($this->conn);
    }

    /**
     * Returns all view data needed by the Records sections (cathedral or chapel).
     * Called by DashboardController::loadViewData() to keep data loading decentralized.
     */
    public function getRecordsViewData(string $scope, ?int $chapelFilter = null): array {
        $page = (int)($_GET['rec_page'] ?? 1);
        $filters = [
            'scope'       => $scope,
            'search'      => $_GET['search'] ?? '',
            'record_type' => $_GET['record_type'] ?? '',
            'sort'        => $_GET['rec_sort'] ?? ($_GET['sort'] ?? 'date_desc')
        ];
        if (!empty($_GET['timeframe'])) {
            $filters['timeframe'] = trim($_GET['timeframe']);
        }
        if (!empty($_GET['priest'])) {
            $filters['officiating_priest'] = trim($_GET['priest']);
        }
        if ($chapelFilter !== null) {
            $filters['chapel_id'] = $chapelFilter;
        }
        $limit = 30;
        $res = $this->recordModel->search($filters, $page, $limit);
        return [
            'records'      => $res['records'],
            'total_pages'  => $res['pages'],
            'total_records'=> $res['total'],
            'current_page' => $res['page'],
            'limit'        => $limit,
            'rec_filters'  => $filters,
            'record_stats' => $this->recordModel->getStats($chapelFilter, $scope),
        ];
    }

    public function handleRequest() {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') return;
        if (!in_array(($_POST['form_section'] ?? ''), ['records_cathedral', 'records_chapel', 'baptism_form', 'wedding_form', 'marriage_form', 'funeral_form', 'confirmation_form', 'communion_form'])) return;

        if (!$this->validateCsrf()) {
            $this->flash('error', 'Security session expired. Please refresh.');
            $this->redirect('dashboard.php');
        }
        
        $action = $_POST['action'] ?? '';
        $scope = $_POST['scope'] ?? ($_POST['form_section'] === 'records_cathedral' ? 'cathedral' : 'chapel');
        $type  = $_POST['record_type'] ?? '';

        // Authorization check
        $canManage = ($scope === 'cathedral') ? $this->permissions['can_cathedral_rec'] : $this->permissions['can_chapel_rec'];
        if (!$canManage) {
            $this->flash('error', 'You do not have permission to modify these records.');
            $this->redirect('dashboard.php?section=records_' . $scope);
        }

        // Enforce Chapel isolation on incoming data: use helper to overwrite if needed
        $action = $_POST['action'] ?? '';
        $scope = $_POST['scope'] ?? 'cathedral';
        $type  = $_POST['record_type'] ?? '';

        // Force Chapel Admin to their assigned scope and chapel
        if ($this->role === 'Chapel Admin' && $this->chapelId !== null) {
            $_POST['chapel_id'] = $this->chapelId;
            $_POST['scope'] = 'chapel';
            $scope = 'chapel';
        } else {
            // Auto-correct scope based on chapel_id for Super/Cathedral Admins
            if (!empty($_POST['chapel_id'])) {
                $scope = 'chapel';
                $_POST['scope'] = 'chapel';
            } else {
                $scope = 'cathedral';
                $_POST['scope'] = 'cathedral';
            }
        }

        if ($action === 'create_record' && !$this->permissions['can_create_records']) {
            $this->flash('error', 'You do not have permission to create records.');
            $this->redirect('dashboard.php?section=records_' . $scope);
        }

        switch ($action) {
            case 'create_record':
            case 'create':
                switch ($type) {
                    case 'Baptism':      $this->createBaptism(); break;
                    case 'Wedding':      $this->createWedding(); break;
                    case 'Funeral':      $this->createFuneral(); break;
                    case 'Confirmation': $this->createConfirmation(); break;
                    case 'Communion':
                    case 'First Communion':    $this->createCommunion(); break;
                    default:
                        $this->flash('error', 'Unsupported record type.');
                }
                break;
            case 'update_record':
            case 'update':
                if (!$this->canAccessExistingRecord((int)($_POST['id'] ?? 0))) {
                    $this->flash('error', 'You do not have permission to update this record.');
                    break;
                }
                switch ($type) {
                    case 'Baptism':      $this->updateBaptism(); break;
                    case 'Wedding':      $this->updateWedding(); break;
                    case 'Funeral':      $this->updateFuneral(); break;
                    case 'Confirmation': $this->updateConfirmation(); break;
                    case 'Communion':
                    case 'First Communion':    $this->updateCommunion(); break;
                    default:
                        $this->flash('error', 'Unsupported record type.');
                }
                break;
            case 'delete_record':
            case 'delete':
                if (!$this->permissions['can_delete_records']) {
                    $this->flash('error', 'You do not have permission to archive records.');
                    break;
                }
                if (!$this->canAccessExistingRecord((int)($_POST['id'] ?? 0))) {
                    $this->flash('error', 'You do not have permission to archive this record.');
                    break;
                }
                $this->deleteRecord(); 
                break;
            case 'issue_cert':
            case 'issue_certificate':
                if (!$this->canAccessExistingRecord((int)($_POST['record_id'] ?? 0))) {
                    $this->flash('error', 'You do not have permission to issue this certificate.');
                    break;
                }
                $this->issueCert(); 
                break;
        }
        $this->redirect('dashboard.php?section=records_' . $scope);
    }

    private function createBaptism() {
        $id = $this->recordModel->create($_POST, $this->userId);
        if ($id) {
            // Capture the full newly‑created record for the audit snapshot
            $newRecord = $this->recordModel->getById($id);
            AuditLogger::logFromSession(
                $this->conn,
                'created',
                'Records',
                "Baptism: {$_POST['person_name']}",
                null,          // no old values
                $newRecord     // full new row
            );
            $this->flash('success', 'Baptism record created.');
            return;
        }
        $this->flash('error', 'Failed to save baptism record.');
    }

    private function updateBaptism() {
        $id = (int)$_POST['id'];
        // Capture state before the update
        $oldRecord = $this->recordModel->getById($id);
        if ($this->recordModel->update($id, $_POST, $this->userId)) {
            // Capture state after the update
            $newRecord = $this->recordModel->getById($id);
            AuditLogger::logFromSession(
                $this->conn,
                'updated',
                'Records',
                "Updated Baptism: {$_POST['person_name']}",
                $oldRecord,
                $newRecord
            );
            $this->flash('success', 'Baptism record updated.');
            return;
        }
        $this->flash('error', 'Failed to update baptism record.');
    }

    private function bundleWeddingMeta(): void {
        $meta = [];
        if (!empty($_POST['notes']) && str_starts_with(trim($_POST['notes']), '{')) {
            $parsed = json_decode($_POST['notes'], true);
            if (is_array($parsed)) $meta = $parsed;
        }
        $fields = [
            'marriage_license_no', 'license_date_issued', 'license_place_issued',
            'groom_civil_status', 'bride_civil_status'
        ];
        foreach ($fields as $f) {
            if (isset($_POST[$f]) && trim($_POST[$f]) !== '') {
                $meta[$f] = trim($_POST[$f]);
            }
        }
        if (!empty($meta)) {
            $_POST['notes'] = json_encode($meta, JSON_UNESCAPED_UNICODE);
        }
        if (empty($_POST['person_name']) && (!empty($_POST['groom_name']) || !empty($_POST['bride_name']))) {
            $_POST['person_name'] = trim(($_POST['groom_name'] ?? '') . ' & ' . ($_POST['bride_name'] ?? ''));
        }
    }

    private function createWedding() {
        $this->bundleWeddingMeta();
        $id = $this->recordModel->create($_POST, $this->userId);
        if ($id) {
            // Capture the full newly‑created wedding record
            $newRecord = $this->recordModel->getById($id);
            AuditLogger::logFromSession(
                $this->conn,
                'created',
                'Records',
                "Wedding: {$_POST['groom_name']} & {$_POST['bride_name']}",
                null,
                $newRecord
            );
            $this->flash('success', 'Wedding record created.');
            return;
        }
        $this->flash('error', 'Failed to save wedding record.');
    }

    private function updateWedding() {
        $this->bundleWeddingMeta();
        $id = (int)$_POST['id'];
        $oldRecord = $this->recordModel->getById($id);
        if ($this->recordModel->update($id, $_POST, $this->userId)) {
            $newRecord = $this->recordModel->getById($id);
            AuditLogger::logFromSession(
                $this->conn,
                'updated',
                'Records',
                "Updated Wedding ID: {$id}",
                $oldRecord,
                $newRecord
            );
            $this->flash('success', 'Wedding record updated.');
            return;
        }
        $this->flash('error', 'Failed to update wedding record.');
    }

    private function createFuneral() {
        $id = $this->recordModel->create($_POST, $this->userId);
        if ($id) {
            $newRecord = $this->recordModel->getById($id);
            AuditLogger::logFromSession(
                $this->conn,
                'created',
                'Records',
                "Funeral: {$_POST['deceased_name']}",
                null,
                $newRecord
            );
            $this->flash('success', 'Funeral record created.');
            return;
        }
        $this->flash('error', 'Failed to save funeral record.');
    }

    private function updateFuneral() {
        $id = (int)$_POST['id'];
        $oldRecord = $this->recordModel->getById($id);
        if ($this->recordModel->update($id, $_POST, $this->userId)) {
            $newRecord = $this->recordModel->getById($id);
            AuditLogger::logFromSession(
                $this->conn,
                'updated',
                'Records',
                "Updated Funeral ID: {$id}",
                $oldRecord,
                $newRecord
            );
            $this->flash('success', 'Funeral record updated.');
            return;
        }
        $this->flash('error', 'Failed to update funeral record.');
    }

    private function createConfirmation() {
        $id = $this->recordModel->create($_POST, $this->userId);
        if ($id) {
            $newRecord = $this->recordModel->getById($id);
            AuditLogger::logFromSession(
                $this->conn,
                'created',
                'Records',
                "Confirmation record created ID: {$id}",
                null,
                $newRecord
            );
            $this->flash('success', 'Confirmation record created.');
        } else {
            $this->flash('error', 'Failed to save confirmation record.');
        }
    }

    private function updateConfirmation() {
        $id = (int)$_POST['id'];
        $oldRecord = $this->recordModel->getById($id);
        if ($this->recordModel->update($id, $_POST, $this->userId)) {
            $newRecord = $this->recordModel->getById($id);
            AuditLogger::logFromSession(
                $this->conn,
                'updated',
                'Records',
                "Updated Confirmation ID: {$id}",
                $oldRecord,
                $newRecord
            );
            $this->flash('success', 'Confirmation record updated.');
            return;
        }
        $this->flash('error', 'Failed to update confirmation record.');
    }

    private function createCommunion() {
        $id = $this->recordModel->create($_POST, $this->userId);
        if ($id) {
            $newRecord = $this->recordModel->getById($id);
            AuditLogger::logFromSession(
                $this->conn,
                'created',
                'Records',
                "Communion: {$_POST['person_name']}",
                null,
                $newRecord
            );
            $this->flash('success', 'Communion record created.');
            return;
        }
        $this->flash('error', 'Failed to save communion record.');
    }

    private function updateCommunion() {
        $id = (int)$_POST['id'];
        $oldRecord = $this->recordModel->getById($id);
        if ($this->recordModel->update($id, $_POST, $this->userId)) {
            $newRecord = $this->recordModel->getById($id);
            AuditLogger::logFromSession(
                $this->conn,
                'updated',
                'Records',
                "Updated Communion ID: {$id}",
                $oldRecord,
                $newRecord
            );
            $this->flash('success', 'Communion record updated.');
            return;
        }
        $this->flash('error', 'Failed to update communion record.');
    }

    private function deleteRecord() {
        $id = (int)$_POST['id'];
        $oldRecord = $this->recordModel->getById($id);
        if ($this->recordModel->archive($id, $this->userId)) {
            AuditLogger::logFromSession(
                $this->conn,
                'deleted',
                'Records',
                "Archived record ID: {$id}",
                $oldRecord,
                null
            );
            $this->flash('success', 'Record archived.');
            return;
        }
        $this->flash('error', 'Failed to archive record.');
    }

    private function issueCert() {
        $id = (int)($_POST['record_id'] ?? 0);
        $to = trim($_POST['issued_to'] ?? '');
        $purp = trim($_POST['purpose'] ?? 'General');
        
        if (!$id || empty($to)) {
            $this->flash('error', 'Recipient name is required to issue a certificate.');
            return;
        }
        
        $issuanceId = $this->recordModel->logCertificateIssuance($id, $to, $purp, $this->userId);
        
        if ($issuanceId) {
            // Snapshot the record and include issuance details
            $record = $this->recordModel->getById($id);
            AuditLogger::logFromSession(
                $this->conn,
                'issued_cert',
                'Records',
                "Issued certificate for record ID: {$id} to {$to}",
                $record,
                ['issuance_id' => $issuanceId, 'issued_to' => $to, 'purpose' => $purp]
            );
            $this->flash('success', 'Certificate issued successfully.');
            
            // Redirect to printable certificate view
            $certUrl = rtrim(BASE_URL, '/') . "/certificates/sacramental_certificate.php?record_id={$id}&issuance_id={$issuanceId}";
            header("Location: " . $certUrl);
            exit();
        }
        
        $this->flash('error', 'Failed to log certificate issuance.');
    }

    private function canAccessExistingRecord(int $id): bool {
        if ($id <= 0) return false;
        $record = $this->recordModel->getById($id);
        if (!$record) return false;
        
        // Super/Cathedral Admin can access all records
        if (in_array($this->role, ['Admin', 'Cathedral Admin'], true)) return true;
        
        // Chapel Admin can only access records belonging to their assigned chapel
        return $this->chapelId !== null 
            && ($record['chapel_id'] ?? null) !== null 
            && (int)$this->chapelId === (int)$record['chapel_id'];
    }

    /**
     * Unified Controller Entry Point for Canonical Intake & 1:1 Archival Forms.
     * Centralizes authentication, chapel scoping, CSRF, persistence, and view orchestration.
     */
    public function handleIntakePage(string $sacramentType): void {
        // 1. Role Authorization
        if (!in_array($this->role, ['Admin', 'Cathedral Admin', 'Chapel Admin', 'Staff', 'Priest', 'Pastor'], true)) {
            header('Location: dashboard.php?error=unauthorized');
            exit();
        }

        $isAdmin = in_array($this->role, ['Admin', 'Cathedral Admin'], true);
        $userChapel = (int)($this->chapelId ?? 0);

        // 2. Load Existing Record & Enforce Chapel Isolation
        $recordId = (int)($_GET['record_id'] ?? ($_GET['id'] ?? 0));
        $existingRecord = null;
        $metadata = [];

        if ($recordId > 0) {
            $existingRecord = $this->recordModel->getById($recordId);
            if ($existingRecord) {
                if (!$this->checkChapelAccess($existingRecord['chapel_id'] ?? null)) {
                    header('Location: dashboard.php?error=unauthorized');
                    exit();
                }
                if (!empty($existingRecord['notes']) && str_starts_with(trim($existingRecord['notes']), '{')) {
                    $parsed = json_decode($existingRecord['notes'], true);
                    if (is_array($parsed)) {
                        $metadata = $parsed;
                    }
                }
            } else {
                header('Location: dashboard.php?error=not_found');
                exit();
            }
        }

        // 3. Handle Form Submission (Save / Update)
        $flashMessage = '';
        $flashType = '';

        // Synchronize CSRF tokens
        $csrf = $this->generateCsrf();
        $_SESSION['csrf_token'] = $csrf;

        $expectedAction = 'save_' . strtolower($sacramentType) . '_form';
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['action']) && $_POST['action'] === $expectedAction) {
            $submittedCsrf = $_POST['csrf'] ?? ($_POST['csrf_token'] ?? '');
            if (!$this->validateCsrf($submittedCsrf)) {
                $flashMessage = 'Security token invalid or expired. Please reload and try again.';
                $flashType = 'error';
            } else {
                $saveResult = $this->processIntakeSave($sacramentType, $userChapel, $isAdmin);
                $flashMessage = $saveResult['message'];
                $flashType    = $saveResult['type'];

                if (!empty($saveResult['record_id'])) {
                    $recordId = (int)$saveResult['record_id'];
                    $existingRecord = $this->recordModel->getById($recordId);
                    if ($existingRecord && !empty($existingRecord['notes']) && str_starts_with(trim($existingRecord['notes']), '{')) {
                        $metadata = json_decode($existingRecord['notes'], true) ?: [];
                    }
                }
            }
        }

        // 4. View Rendering
        $refId = $existingRecord['reference_id'] ?? '';
        $scope = $existingRecord['scope'] ?? ($_GET['scope'] ?? ($isAdmin ? 'cathedral' : 'chapel'));
        if (!in_array($scope, ['cathedral', 'chapel'], true)) {
            $scope = 'cathedral';
        }

        $viewMap = [
            'Baptism'      => 'baptism_form.php',
            'Confirmation' => 'confirmation_form.php',
            'Marriage'     => 'marriage_form.php',
            'Funeral'      => 'funeral_form.php',
        ];

        $viewFile = BASE_PATH . '/views/pages/' . ($viewMap[$sacramentType] ?? 'baptism_form.php');
        if (is_file($viewFile)) {
            include $viewFile;
        } else {
            $this->flash('error', 'Canonical form view template not found.');
            $this->redirect('dashboard.php');
        }
    }

    /**
     * Builds payload, saves to database, and writes audit trail for canonical intake.
     */
    private function processIntakeSave(string $sacramentType, int $userChapel, bool $isAdmin): array {
        $targetRecordId = (int)($_POST['target_record_id'] ?? 0);
        $userId = $this->userId;
        $oldRecord = null;

        if ($targetRecordId > 0) {
            $oldRecord = $this->recordModel->getById($targetRecordId);
            if (!$oldRecord) {
                return ['success' => false, 'type' => 'error', 'message' => 'Record not found.'];
            }
            if (!$this->checkChapelAccess($oldRecord['chapel_id'] ?? null)) {
                return ['success' => false, 'type' => 'error', 'message' => 'Unauthorized: You do not have access to this chapel record.'];
            }
            $canManage = ($oldRecord['scope'] === 'cathedral') ? $this->permissions['can_cathedral_rec'] : $this->permissions['can_chapel_rec'];
            if (!$canManage) {
                return ['success' => false, 'type' => 'error', 'message' => 'You do not have permission to modify these records.'];
            }
        } else {
            if (!$this->permissions['can_create_records']) {
                return ['success' => false, 'type' => 'error', 'message' => 'You do not have permission to create records.'];
            }
        }

        // Enforce role-based chapel isolation on scope & chapel_id
        if ($this->role === 'Chapel Admin' && $this->chapelId !== null) {
            $scope = 'chapel';
            $chapelId = (int)$this->chapelId;
        } elseif ($targetRecordId > 0 && !$isAdmin) {
            $scope = $oldRecord['scope'] ?? 'chapel';
            $chapelId = (int)($oldRecord['chapel_id'] ?? 0);
        } else {
            $requestedScope = trim($_POST['scope'] ?? ($isAdmin ? 'cathedral' : 'chapel'));
            $scope = in_array($requestedScope, ['cathedral', 'chapel'], true) ? $requestedScope : 'cathedral';
            $chapelId = (int)($_POST['chapel_id'] ?? ($scope === 'chapel' ? $userChapel : 0));
            if ($scope === 'cathedral') {
                $chapelId = 0;
            }
        }

        switch ($sacramentType) {
            case 'Baptism':
                $childFirstName = $this->cleanName($_POST['child_first_name'] ?? '', 50);
                $childMiddleName = $this->cleanName($_POST['child_middle_name'] ?? '', 30);
                $childSurname = $this->cleanName($_POST['child_surname'] ?? '', 50);
                $fullName = trim($childFirstName . ' ' . ($childMiddleName ? $childMiddleName . ' ' : '') . $childSurname);
                if (empty($fullName)) $fullName = $this->cleanName($_POST['person_name'] ?? 'Unnamed Child', 80);

                $dateOfBaptism = trim($_POST['event_date'] ?? date('Y-m-d'));
                $dateOfBirth = trim($_POST['date_of_birth'] ?? '');
                $placeOfBirth = $this->cleanAddress($_POST['place_of_birth'] ?? '', 100);
                $address = $this->cleanAddress($_POST['address'] ?? '', 150);
                $gender = trim($_POST['gender'] ?? 'Male');
                $nationality = $this->cleanName($_POST['nationality'] ?? 'Filipino', 40);
                $contactNo = $this->cleanPhone($_POST['contact_no'] ?? '', 15);

                $fatherFirst = $this->cleanName($_POST['father_first_name'] ?? '', 50);
                $fatherMI = $this->cleanName($_POST['father_mi'] ?? '', 5);
                $fatherSurname = $this->cleanName($_POST['father_surname'] ?? '', 50);
                $fatherFull = trim($fatherFirst . ' ' . ($fatherMI ? $fatherMI . ' ' : '') . $fatherSurname);
                $fatherBirthPlace = $this->cleanAddress($_POST['father_birth_place'] ?? '', 100);

                $motherFirst = $this->cleanName($_POST['mother_first_name'] ?? '', 50);
                $motherMI = $this->cleanName($_POST['mother_mi'] ?? '', 5);
                $motherSurname = $this->cleanName($_POST['mother_surname'] ?? '', 50);
                $motherFull = trim($motherFirst . ' ' . ($motherMI ? $motherMI . ' ' : '') . $motherSurname);
                $motherBirthPlace = $this->cleanAddress($_POST['mother_birth_place'] ?? '', 100);

                $filiation = trim($_POST['filiation'] ?? 'Kasal');
                $paternalLolo = $this->cleanName($_POST['paternal_lolo'] ?? '', 70);
                $paternalLola = $this->cleanName($_POST['paternal_lola'] ?? '', 70);
                $maternalLolo = $this->cleanName($_POST['maternal_lolo'] ?? '', 70);
                $maternalLola = $this->cleanName($_POST['maternal_lola'] ?? '', 70);

                $sponsorsRaw = $_POST['sponsors'] ?? [];
                $sponsorsList = [];
                if (is_array($sponsorsRaw)) {
                    foreach ($sponsorsRaw as $sp) {
                        $spClean = $this->cleanName($sp, 70);
                        if (!empty($spClean)) $sponsorsList[] = $spClean;
                    }
                }

                $officiatingPriest = $this->cleanName($_POST['officiating_priest'] ?? '', 70);
                $bookNumber = $this->cleanCode($_POST['book_number'] ?? 'BK-' . date('Y'), 20);
                $pageNumber = $this->cleanCode($_POST['page_number'] ?? 'PG-01', 15);
                $marginalNotes = mb_substr(strip_tags(trim($_POST['marginal_notes'] ?? '')), 0, 500);

                $extendedMeta = [
                    'child_first_name' => $childFirstName,
                    'child_middle_name' => $childMiddleName,
                    'child_surname' => $childSurname,
                    'gender' => $gender,
                    'nationality' => $nationality,
                    'contact_no' => $contactNo,
                    'address' => $address,
                    'father_first_name' => $fatherFirst,
                    'father_mi' => $fatherMI,
                    'father_surname' => $fatherSurname,
                    'father_birth_place' => $fatherBirthPlace,
                    'mother_first_name' => $motherFirst,
                    'mother_mi' => $motherMI,
                    'mother_surname' => $motherSurname,
                    'mother_birth_place' => $motherBirthPlace,
                    'filiation' => $filiation,
                    'paternal_lolo' => $paternalLolo,
                    'paternal_lola' => $paternalLola,
                    'maternal_lolo' => $maternalLolo,
                    'maternal_lola' => $maternalLola,
                    'sponsors' => $sponsorsList,
                    'marginal_notes' => $marginalNotes,
                ];
                $encodedMeta = json_encode($extendedMeta, JSON_UNESCAPED_UNICODE);

                $payload = [
                    'scope' => $scope,
                    'chapel_id' => $chapelId,
                    'record_type' => 'Baptism',
                    'person_name' => $fullName,
                    'child_name' => $fullName,
                    'event_date' => $dateOfBaptism,
                    'date_of_birth' => $dateOfBirth,
                    'place_of_birth' => $placeOfBirth,
                    'father_name' => $fatherFull,
                    'mother_name' => $motherFull,
                    'baptism_parents_residence' => $address,
                    'baptism_legitimacy' => $filiation,
                    'godfather' => $sponsorsList[0] ?? '',
                    'godmother' => $sponsorsList[1] ?? '',
                    'officiating_priest' => $officiatingPriest,
                    'book_number' => $bookNumber,
                    'page_number' => $pageNumber,
                    'notes' => $encodedMeta,
                ];
                $itemLabel = "Baptismal Form for $fullName";
                break;

            case 'Confirmation':
                $childFirstName = $this->cleanName($_POST['child_first_name'] ?? '', 50);
                $childMiddleName = $this->cleanName($_POST['child_middle_name'] ?? '', 30);
                $childSurname = $this->cleanName($_POST['child_surname'] ?? '', 50);
                $fullName = trim($childFirstName . ' ' . ($childMiddleName ? $childMiddleName . ' ' : '') . $childSurname);
                if (empty($fullName)) $fullName = $this->cleanName($_POST['person_name'] ?? 'Unnamed Candidate', 80);

                $dateOfConfirmation = trim($_POST['event_date'] ?? date('Y-m-d'));
                $confirmationTime = mb_substr(strip_tags(trim($_POST['confirmation_time'] ?? '')), 0, 20);
                $dateOfBaptism = trim($_POST['date_of_baptism'] ?? '');
                $dateOfBirth = trim($_POST['date_of_birth'] ?? '');
                $placeOfBirth = $this->cleanAddress($_POST['place_of_birth'] ?? '', 100);
                $address = $this->cleanAddress($_POST['address'] ?? '', 150);
                $gender = trim($_POST['gender'] ?? 'Male');
                $nationality = $this->cleanName($_POST['nationality'] ?? 'Filipino', 40);
                $contactNo = $this->cleanPhone($_POST['contact_no'] ?? '', 15);

                $fatherFirst = $this->cleanName($_POST['father_first_name'] ?? '', 50);
                $fatherMI = $this->cleanName($_POST['father_mi'] ?? '', 5);
                $fatherSurname = $this->cleanName($_POST['father_surname'] ?? '', 50);
                $fatherFull = trim($fatherFirst . ' ' . ($fatherMI ? $fatherMI . ' ' : '') . $fatherSurname);
                $fatherBirthPlace = $this->cleanAddress($_POST['father_birth_place'] ?? '', 100);

                $motherFirst = $this->cleanName($_POST['mother_first_name'] ?? '', 50);
                $motherMI = $this->cleanName($_POST['mother_mi'] ?? '', 5);
                $motherSurname = $this->cleanName($_POST['mother_surname'] ?? '', 50);
                $motherFull = trim($motherFirst . ' ' . ($motherMI ? $motherMI . ' ' : '') . $motherSurname);
                $motherBirthPlace = $this->cleanAddress($_POST['mother_birth_place'] ?? '', 100);

                $filiation = trim($_POST['filiation'] ?? 'Kasal');
                $sponsorsRaw = $_POST['sponsors'] ?? [];
                $sponsorsList = [];
                if (is_array($sponsorsRaw)) {
                    foreach ($sponsorsRaw as $sp) {
                        $spClean = $this->cleanName($sp, 70);
                        if (!empty($spClean)) $sponsorsList[] = $spClean;
                    }
                }

                $officiatingPriest = $this->cleanName($_POST['officiating_priest'] ?? '', 70);
                $bookNumber = $this->cleanCode($_POST['book_number'] ?? 'BK-' . date('Y'), 20);
                $pageNumber = $this->cleanCode($_POST['page_number'] ?? 'PG-01', 15);
                $marginalNotes = mb_substr(strip_tags(trim($_POST['marginal_notes'] ?? '')), 0, 500);
                $placeOfBaptism = $this->cleanAddress($_POST['place_of_baptism'] ?? ($_POST['confirmation_place_of_baptism'] ?? ''), 100);

                $extendedMeta = [
                    'child_first_name' => $childFirstName,
                    'child_middle_name' => $childMiddleName,
                    'child_surname' => $childSurname,
                    'confirmation_time' => $confirmationTime,
                    'date_of_baptism' => $dateOfBaptism,
                    'place_of_baptism' => $placeOfBaptism,
                    'gender' => $gender,
                    'nationality' => $nationality,
                    'contact_no' => $contactNo,
                    'address' => $address,
                    'father_first_name' => $fatherFirst,
                    'father_mi' => $fatherMI,
                    'father_surname' => $fatherSurname,
                    'father_birth_place' => $fatherBirthPlace,
                    'mother_first_name' => $motherFirst,
                    'mother_mi' => $motherMI,
                    'mother_surname' => $motherSurname,
                    'mother_birth_place' => $motherBirthPlace,
                    'filiation' => $filiation,
                    'sponsors' => $sponsorsList,
                    'marginal_notes' => $marginalNotes,
                ];
                $encodedMeta = json_encode($extendedMeta, JSON_UNESCAPED_UNICODE);

                $payload = [
                    'scope' => $scope,
                    'chapel_id' => $chapelId,
                    'record_type' => 'Confirmation',
                    'person_name' => $fullName,
                    'child_name' => $fullName,
                    'event_date' => $dateOfConfirmation,
                    'date_of_birth' => $dateOfBirth,
                    'place_of_birth' => $placeOfBirth,
                    'father_name' => $fatherFull,
                    'mother_name' => $motherFull,
                    'baptism_parents_residence' => $address,
                    'baptism_legitimacy' => $filiation,
                    'godfather' => $sponsorsList[0] ?? '',
                    'godmother' => $sponsorsList[1] ?? '',
                    'confirmation_place_of_baptism' => $placeOfBaptism,
                    'officiating_priest' => $officiatingPriest,
                    'book_number' => $bookNumber,
                    'page_number' => $pageNumber,
                    'notes' => $encodedMeta,
                ];
                $itemLabel = "Confirmation Form for $fullName";
                break;

            case 'Marriage':
                $province = $this->cleanAddress($_POST['province'] ?? 'Rizal', 50);
                $municipality = $this->cleanAddress($_POST['municipality'] ?? 'Angono', 50);
                $registryNo = $this->cleanCode($_POST['registry_no'] ?? '', 30);
                $receivedBy = $this->cleanName($_POST['received_by'] ?? '', 60);
                $dateOfReceipt = trim($_POST['date_of_receipt'] ?? '');
                $marriageLicenseNo = $this->cleanCode($_POST['marriage_license_no'] ?? '', 30);
                $dateOfIssuance = trim($_POST['date_of_issuance'] ?? '');

                $groomFirst = $this->cleanName($_POST['groom_first_name'] ?? '', 50);
                $groomMiddle = $this->cleanName($_POST['groom_middle_name'] ?? '', 50);
                $groomLast = $this->cleanName($_POST['groom_surname'] ?? '', 50);
                $groomFull = trim($groomFirst . ' ' . ($groomMiddle ? $groomMiddle . ' ' : '') . $groomLast);
                if (empty($groomFull)) $groomFull = $this->cleanName($_POST['groom_name'] ?? 'Unnamed Groom', 80);

                $brideFirst = $this->cleanName($_POST['bride_first_name'] ?? '', 50);
                $brideMiddle = $this->cleanName($_POST['bride_middle_name'] ?? '', 50);
                $brideLast = $this->cleanName($_POST['bride_surname'] ?? '', 50);
                $brideFull = trim($brideFirst . ' ' . ($brideMiddle ? $brideMiddle . ' ' : '') . $brideLast);
                if (empty($brideFull)) $brideFull = $this->cleanName($_POST['bride_name'] ?? 'Unnamed Bride', 80);

                $dateOfMarriage = trim($_POST['event_date'] ?? ($_POST['date_of_marriage'] ?? date('Y-m-d')));
                $placeOfMarriage = $this->cleanAddress($_POST['place_of_marriage'] ?? 'Cathedral of St. Michael the Archangel', 100);
                $witness1 = $this->cleanName($_POST['witness_1'] ?? '', 70);
                $witness2 = $this->cleanName($_POST['witness_2'] ?? '', 70);
                $officiatingPriest = $this->cleanName($_POST['officiating_priest'] ?? '', 70);
                $bookNumber = $this->cleanCode($_POST['book_number'] ?? 'BK-' . date('Y'), 20);
                $pageNumber = $this->cleanCode($_POST['page_number'] ?? 'PG-01', 15);

                $groomFatherFull = trim($this->cleanName($_POST['groom_father_first'] ?? '', 50) . ' ' . $this->cleanName($_POST['groom_father_mi'] ?? '', 5) . ' ' . $this->cleanName($_POST['groom_father_surname'] ?? '', 50));
                $groomMotherFull = trim($this->cleanName($_POST['groom_mother_first'] ?? '', 50) . ' ' . $this->cleanName($_POST['groom_mother_mi'] ?? '', 5) . ' ' . $this->cleanName($_POST['groom_mother_surname'] ?? '', 50));
                $brideFatherFull = trim($this->cleanName($_POST['bride_father_first'] ?? '', 50) . ' ' . $this->cleanName($_POST['bride_father_mi'] ?? '', 5) . ' ' . $this->cleanName($_POST['bride_father_surname'] ?? '', 50));
                $brideMotherFull = trim($this->cleanName($_POST['bride_mother_first'] ?? '', 50) . ' ' . $this->cleanName($_POST['bride_mother_mi'] ?? '', 5) . ' ' . $this->cleanName($_POST['bride_mother_surname'] ?? '', 50));

                $marginalNotes = mb_substr(strip_tags(trim($_POST['marginal_notes'] ?? '')), 0, 500);

                // Collect complete Form 90 fields into metadata
                $extendedMeta = [
                    'province' => $province,
                    'municipality' => $municipality,
                    'registry_no' => $registryNo,
                    'received_by' => $receivedBy,
                    'date_of_receipt' => $dateOfReceipt,
                    'marriage_license_no' => $marriageLicenseNo,
                    'date_of_issuance' => $dateOfIssuance,
                    'groom_first_name' => $groomFirst,
                    'groom_middle_name' => $groomMiddle,
                    'groom_surname' => $groomLast,
                    'groom_dob' => trim($_POST['groom_dob'] ?? ''),
                    'groom_age' => trim($_POST['groom_age'] ?? ''),
                    'groom_pob' => $this->cleanAddress($_POST['groom_pob'] ?? '', 100),
                    'groom_sex' => trim($_POST['groom_sex'] ?? 'Male'),
                    'groom_citizenship' => $this->cleanName($_POST['groom_citizenship'] ?? 'Filipino', 40),
                    'groom_residence' => $this->cleanAddress($_POST['groom_residence'] ?? '', 150),
                    'groom_religion' => $this->cleanName($_POST['groom_religion'] ?? 'Philippine Independent Church', 50),
                    'groom_civil_status' => trim($_POST['groom_civil_status'] ?? 'Single'),
                    'groom_contact_no' => $this->cleanPhone($_POST['groom_contact_no'] ?? '', 15),
                    'groom_dissolved_how' => $this->cleanName($_POST['groom_dissolved_how'] ?? '', 40),
                    'groom_dissolved_place' => $this->cleanAddress($_POST['groom_dissolved_place'] ?? '', 80),
                    'groom_dissolved_date' => trim($_POST['groom_dissolved_date'] ?? ''),
                    'groom_degree_relationship' => $this->cleanName($_POST['groom_degree_relationship'] ?? 'None', 50),
                    'groom_father_first' => $this->cleanName($_POST['groom_father_first'] ?? '', 50),
                    'groom_father_mi' => $this->cleanName($_POST['groom_father_mi'] ?? '', 5),
                    'groom_father_surname' => $this->cleanName($_POST['groom_father_surname'] ?? '', 50),
                    'groom_father_citizenship' => $this->cleanName($_POST['groom_father_citizenship'] ?? 'Filipino', 40),
                    'groom_father_residence' => $this->cleanAddress($_POST['groom_father_residence'] ?? '', 120),
                    'groom_mother_first' => $this->cleanName($_POST['groom_mother_first'] ?? '', 50),
                    'groom_mother_mi' => $this->cleanName($_POST['groom_mother_mi'] ?? '', 5),
                    'groom_mother_surname' => $this->cleanName($_POST['groom_mother_surname'] ?? '', 50),
                    'groom_mother_citizenship' => $this->cleanName($_POST['groom_mother_citizenship'] ?? 'Filipino', 40),
                    'groom_mother_residence' => $this->cleanAddress($_POST['groom_mother_residence'] ?? '', 120),
                    'groom_consent_person' => $this->cleanName($_POST['groom_consent_person'] ?? '', 60),
                    'groom_consent_relationship' => $this->cleanName($_POST['groom_consent_relationship'] ?? '', 30),
                    'groom_consent_citizenship' => $this->cleanName($_POST['groom_consent_citizenship'] ?? '', 40),
                    'groom_consent_residence' => $this->cleanAddress($_POST['groom_consent_residence'] ?? '', 120),
                    'groom_ctc_no' => $this->cleanCode($_POST['groom_ctc_no'] ?? '', 25),
                    'groom_ctc_date' => trim($_POST['groom_ctc_date'] ?? ''),
                    'groom_ctc_place' => $this->cleanAddress($_POST['groom_ctc_place'] ?? '', 60),
                    'bride_first_name' => $brideFirst,
                    'bride_middle_name' => $brideMiddle,
                    'bride_surname' => $brideLast,
                    'bride_dob' => trim($_POST['bride_dob'] ?? ''),
                    'bride_age' => trim($_POST['bride_age'] ?? ''),
                    'bride_pob' => $this->cleanAddress($_POST['bride_pob'] ?? '', 100),
                    'bride_sex' => trim($_POST['bride_sex'] ?? 'Female'),
                    'bride_citizenship' => $this->cleanName($_POST['bride_citizenship'] ?? 'Filipino', 40),
                    'bride_residence' => $this->cleanAddress($_POST['bride_residence'] ?? '', 150),
                    'bride_religion' => $this->cleanName($_POST['bride_religion'] ?? 'Philippine Independent Church', 50),
                    'bride_civil_status' => trim($_POST['bride_civil_status'] ?? 'Single'),
                    'bride_contact_no' => $this->cleanPhone($_POST['bride_contact_no'] ?? '', 15),
                    'bride_dissolved_how' => $this->cleanName($_POST['bride_dissolved_how'] ?? '', 40),
                    'bride_dissolved_place' => $this->cleanAddress($_POST['bride_dissolved_place'] ?? '', 80),
                    'bride_dissolved_date' => trim($_POST['bride_dissolved_date'] ?? ''),
                    'bride_degree_relationship' => $this->cleanName($_POST['bride_degree_relationship'] ?? 'None', 50),
                    'bride_father_first' => $this->cleanName($_POST['bride_father_first'] ?? '', 50),
                    'bride_father_mi' => $this->cleanName($_POST['bride_father_mi'] ?? '', 5),
                    'bride_father_surname' => $this->cleanName($_POST['bride_father_surname'] ?? '', 50),
                    'bride_father_citizenship' => $this->cleanName($_POST['bride_father_citizenship'] ?? 'Filipino', 40),
                    'bride_father_residence' => $this->cleanAddress($_POST['bride_father_residence'] ?? '', 120),
                    'bride_mother_first' => $this->cleanName($_POST['bride_mother_first'] ?? '', 50),
                    'bride_mother_mi' => $this->cleanName($_POST['bride_mother_mi'] ?? '', 5),
                    'bride_mother_surname' => $this->cleanName($_POST['bride_mother_surname'] ?? '', 50),
                    'bride_mother_citizenship' => $this->cleanName($_POST['bride_mother_citizenship'] ?? 'Filipino', 40),
                    'bride_mother_residence' => $this->cleanAddress($_POST['bride_mother_residence'] ?? '', 120),
                    'bride_consent_person' => $this->cleanName($_POST['bride_consent_person'] ?? '', 60),
                    'bride_consent_relationship' => $this->cleanName($_POST['bride_consent_relationship'] ?? '', 30),
                    'bride_consent_citizenship' => $this->cleanName($_POST['bride_consent_citizenship'] ?? '', 40),
                    'bride_consent_residence' => $this->cleanAddress($_POST['bride_consent_residence'] ?? '', 120),
                    'bride_ctc_no' => $this->cleanCode($_POST['bride_ctc_no'] ?? '', 25),
                    'bride_ctc_date' => trim($_POST['bride_ctc_date'] ?? ''),
                    'bride_ctc_place' => $this->cleanAddress($_POST['bride_ctc_place'] ?? '', 60),
                    'civil_registrar_name' => $this->cleanName($_POST['civil_registrar_name'] ?? '', 60),
                    'sworn_day' => trim($_POST['sworn_day'] ?? date('j')),
                    'sworn_month' => trim($_POST['sworn_month'] ?? date('F')),
                    'sworn_year' => trim($_POST['sworn_year'] ?? date('Y')),
                    'sworn_at' => $this->cleanAddress($_POST['sworn_at'] ?? 'Angono, Rizal', 60),
                    'marginal_notes' => $marginalNotes,
                ];
                $encodedMeta = json_encode($extendedMeta, JSON_UNESCAPED_UNICODE);

                $groomParentsStr = trim($groomFatherFull . ($groomFatherFull && $groomMotherFull ? ' & ' : '') . $groomMotherFull);
                $brideParentsStr = trim($brideFatherFull . ($brideFatherFull && $brideMotherFull ? ' & ' : '') . $brideMotherFull);

                $payload = [
                    'scope' => $scope,
                    'chapel_id' => $chapelId,
                    'record_type' => 'Wedding',
                    'person_name' => $groomFull . ' & ' . $brideFull,
                    'groom_name' => $groomFull,
                    'bride_name' => $brideFull,
                    'event_date' => $dateOfMarriage,
                    'date_of_marriage' => $dateOfMarriage,
                    'place_of_marriage' => $placeOfMarriage,
                    'wedding_groom_parents' => $groomParentsStr,
                    'wedding_bride_parents' => $brideParentsStr,
                    'witness_1' => $witness1,
                    'witness_2' => $witness2,
                    'officiating_priest' => $officiatingPriest,
                    'book_number' => $bookNumber,
                    'page_number' => $pageNumber,
                    'notes' => $encodedMeta,
                ];
                $itemLabel = "Marriage License Application for $groomFull & $brideFull";
                break;

            case 'Funeral':
                $decFirst   = $this->cleanName($_POST['deceased_first_name'] ?? '', 50);
                $decMI      = $this->cleanName($_POST['deceased_mi'] ?? '', 30);
                $decSurname = $this->cleanName($_POST['deceased_surname'] ?? '', 50);
                $decFull    = preg_replace('/\s+/', ' ', trim($decFirst . ' ' . ($decMI ? $decMI . ' ' : '') . $decSurname));
                if (empty($decFull)) $decFull = $this->cleanName($_POST['deceased_name'] ?? ($_POST['person_name'] ?? 'Unnamed Deceased'), 80);

                $dateOfFuneral = trim($_POST['event_date'] ?? ($_POST['date_of_funeral'] ?? date('Y-m-d')));
                $funeralTime   = mb_substr(strip_tags(trim($_POST['funeral_time'] ?? '')), 0, 20);
                $dateOfDeath   = trim($_POST['date_of_death'] ?? '');
                $ageAtDeath    = max(0, min(150, (int)($_POST['age_at_death'] ?? 0)));
                $gender        = trim($_POST['gender'] ?? 'Male');
                $maritalStatus = trim($_POST['funeral_marital_status'] ?? 'Single');
                $occupation    = $this->cleanName($_POST['funeral_occupation'] ?? '', 60);
                $causeOfDeath  = $this->cleanAddress($_POST['funeral_cause_of_death'] ?? '', 120);
                $placeOfBurial = $this->cleanAddress($_POST['place_of_burial'] ?? '', 120);
                $residence     = $this->cleanAddress($_POST['residence'] ?? '', 150);
                $nextOfKin     = $this->cleanName($_POST['next_of_kin'] ?? '', 80);
                $contactNo     = $this->cleanPhone($_POST['contact_no'] ?? '', 15);

                $officiatingPriest = $this->cleanName($_POST['officiating_priest'] ?? '', 70);
                $bookNumber        = $this->cleanCode($_POST['book_number'] ?? 'BK-' . date('Y'), 20);
                $pageNumber        = $this->cleanCode($_POST['page_number'] ?? 'PG-01', 15);
                $marginalNotes     = mb_substr(strip_tags(trim($_POST['marginal_notes'] ?? '')), 0, 500);

                $extendedMeta = [
                    'deceased_first_name' => $decFirst,
                    'deceased_mi'         => $decMI,
                    'deceased_surname'    => $decSurname,
                    'funeral_time'        => $funeralTime,
                    'gender'              => $gender,
                    'residence'           => $residence,
                    'next_of_kin'         => $nextOfKin,
                    'contact_no'          => $contactNo,
                    'marginal_notes'      => $marginalNotes,
                ];
                $encodedMeta = json_encode($extendedMeta, JSON_UNESCAPED_UNICODE);

                $payload = [
                    'scope'                  => $scope,
                    'chapel_id'              => $chapelId,
                    'record_type'            => 'Funeral',
                    'person_name'            => $decFull,
                    'deceased_name'          => $decFull,
                    'event_date'             => $dateOfFuneral,
                    'date_of_funeral'        => $dateOfFuneral,
                    'date_of_death'          => $dateOfDeath,
                    'age_at_death'           => $ageAtDeath,
                    'funeral_marital_status' => $maritalStatus,
                    'funeral_occupation'     => $occupation,
                    'funeral_cause_of_death' => $causeOfDeath,
                    'place_of_burial'        => $placeOfBurial,
                    'officiating_priest'     => $officiatingPriest,
                    'book_number'            => $bookNumber,
                    'page_number'            => $pageNumber,
                    'notes'                  => $encodedMeta,
                ];
                $itemLabel = "Christian Burial Form for $decFull";
                break;

            default:
                return ['success' => false, 'type' => 'error', 'message' => 'Unsupported sacrament type.'];
        }

        // Execute Update or Create
        if ($targetRecordId > 0) {
            $updated = $this->recordModel->update($targetRecordId, $payload, $userId);
            if ($updated) {
                $newRecord = $this->recordModel->getById($targetRecordId);
                AuditLogger::logFromSession($this->conn, 'updated', 'Records', "Updated canonical $itemLabel (ID: $targetRecordId)", $oldRecord, $newRecord);
                return [
                    'success'   => true,
                    'type'      => 'success',
                    'message'   => "Official $itemLabel updated successfully!",
                    'record_id' => $targetRecordId,
                ];
            }
            return ['success' => false, 'type' => 'error', 'message' => "Error updating canonical $itemLabel."];
        } else {
            $newId = $this->recordModel->create($payload, $userId);
            if ($newId) {
                $newRecord = $this->recordModel->getById($newId);
                AuditLogger::logFromSession($this->conn, 'created', 'Records', "Created official $itemLabel (ID: $newId)", null, $newRecord);
                return [
                    'success'   => true,
                    'type'      => 'success',
                    'message'   => "Official $itemLabel recorded in canonical registry! Reference ID generated.",
                    'record_id' => $newId,
                ];
            }
            return ['success' => false, 'type' => 'error', 'message' => "Error recording canonical $itemLabel. Please check inputs."];
        }
    }

    /**
     * Sanitizes person and clergy names: allows letters, Filipino accents (ñ/Ñ), spaces, dots, dashes, apostrophes.
     */
    private function cleanName(?string $str, int $max = 70): string {
        if ($str === null) return '';
        $str = preg_replace('/[^a-zA-ZñÑ\s\.\-\']/', '', $str);
        $str = preg_replace('/\s+/', ' ', trim($str));
        return mb_substr($str, 0, $max);
    }

    /**
     * Sanitizes residential addresses, places of birth/baptism: allows alphanumeric, punctuation, hashes, slashes.
     */
    private function cleanAddress(?string $str, int $max = 150): string {
        if ($str === null) return '';
        $str = preg_replace('/[^a-zA-Z0-9ñÑ\s\.,\-\'#\/\(\)]/', '', $str);
        $str = preg_replace('/\s+/', ' ', trim($str));
        return mb_substr($str, 0, $max);
    }

    /**
     * Sanitizes codes, registry folio, and book numbers: alphanumeric, uppercase, dashes, slashes.
     */
    private function cleanCode(?string $str, int $max = 25): string {
        if ($str === null) return '';
        $str = preg_replace('/[^a-zA-Z0-9\s\-\.\/]/', '', $str);
        $str = strtoupper(preg_replace('/\s+/', ' ', trim($str)));
        return mb_substr($str, 0, $max);
    }

    /**
     * Sanitizes phone / mobile contact numbers: digits, dashes, spaces, plus sign only.
     */
    private function cleanPhone(?string $str, int $max = 15): string {
        if ($str === null) return '';
        $str = preg_replace('/[^0-9\+\s\-]/', '', $str);
        $str = preg_replace('/\s+/', ' ', trim($str));
        return mb_substr($str, 0, $max);
    }
}
