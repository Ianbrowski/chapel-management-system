<?php
require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../models/Donation.php';
require_once __DIR__ . '/../models/Merchandise.php';
require_once __DIR__ . '/../models/AuditLogger.php';

/**
 * FinanceController — Manages money flow (Donations, Merchandise, Reports).
 */
class FinanceController extends BaseController {
    private $donationModel;
    private $merchModel;
    private $uploadError;

    public function __construct($conn) {
        parent::__construct($conn);
        $this->donationModel = new Donation($this->conn);
        $this->merchModel    = new Merchandise($this->conn);
    }

    public function handleRequest() {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') return;
        if (($_POST['form_section'] ?? '') !== 'donations' && ($_POST['form_section'] ?? '') !== 'merchandise') return;
 
        $this->enforceCsrf();
        $action = $_POST['action'] ?? '';

        switch ($action) {
            case 'log_donation':    $this->logDonation(); break;
            case 'void_donation':   $this->voidDonation(); break;
            case 'add_merch':       $this->addMerchItem(); break;
            case 'edit_merch':      $this->editMerchItem(); break;
            case 'del_merch':       $this->deleteMerchItem(); break;
            case 'restore_merch':   $this->restoreMerchItem(); break;
            case 'record_sale':     $this->recordMerchSale(); break;
            case 'member_reserve':  $this->memberReserve(); break;
        }
    }

    private function memberReserve() {
        if ($this->role !== 'Member') {
            $this->flash('error', 'Only Members can place online orders.');
            $this->redirect('dashboard.php?section=merchandise');
        }

        $id  = (int)($_POST['id'] ?? 0);
        $qty = (int)($_POST['quantity'] ?? 1);

        if ($id <= 0 || $qty <= 0) {
            $this->flash('error', 'Invalid item or quantity.');
            $this->redirect('dashboard.php?section=merchandise');
        }

        $item = $this->merchModel->getItem($id);
        if (!$item) {
            $this->flash('error', 'Item not found.');
            $this->redirect('dashboard.php?section=merchandise');
        }

        if ($qty > $item['stock_quantity']) {
            $this->flash('error', 'Not enough stock available.');
            $this->redirect('dashboard.php?section=merchandise');
        }

        $pickupChapelId = $this->normalizeNullableInt($_POST['chapel_id'] ?? null);
        if ($pickupChapelId !== null && !$this->chapelExists($pickupChapelId)) {
            $this->flash('error', 'Invalid pickup location.');
            $this->redirect('dashboard.php?section=merchandise');
        }

        require_once __DIR__ . '/../models/User.php';
        $userM = new User($this->conn);
        $userData = $userM->getById($this->userId);
        $full_name = (!empty($userData['full_name'])) ? $userData['full_name'] : $userData['user'];
        $email = $userData['email'] ?? '';
        $phone = $userData['phone'] ?? '';

        require_once __DIR__ . '/../models/Inquiry.php';
        $inquiryM = new Inquiry($this->conn);
        
        $pickupLabel = $pickupChapelId === null ? 'Main Cathedral' : $this->getChapelName($pickupChapelId);
        $details = "Member Reservation: {$item['name']} x {$qty}. (Item ID: {$id}) Pickup: {$pickupLabel}. (Auth Member: {$userData['user']})";
        
        // Use Inquiry so it shows in the Staff Inbox for processing
        $success = $inquiryM->create(
            $full_name,
            $email,
            $phone,
            'Merchandise',
            'Reservation',
            $details,
            null, // date
            $pickupChapelId,
            $id, // item_id
            $qty // quantity
        );

        if ($success) {
            AuditLogger::logFromSession($this->conn, 'created', 'Finance', "Member Online Order Inquiry: {$qty}x {$item['name']} by {$full_name}");
            $this->flash('success', 'Your order has been placed! Please wait for the office staff to process your reservation. You can visit the parish office for pickup and payment.');
        } else {
            $this->flash('error', 'Could not place order request.');
        }
        $this->redirect('dashboard.php?section=merchandise');
    }

    /**
     * Returns all view data needed by the Finance sections (donations + merchandise).
     * Called by DashboardController::loadViewData() to keep data loading decentralized.
     */
    public function getFinanceViewData(): array {
        if ($this->role === 'Member') {
            return [
                'donation_stats'             => ['total_amount' => 0, 'total_count' => 0],
                'donations'                  => [],
                'don_pages'                  => 1,
                'don_current'                => 1,
                'don_total'                  => 0,
                'voided_donations'           => [],
                'merchandise_items'          => $this->merchModel->getAllItems(),
                'archived_merchandise_items' => [],
                'merchandise_sales'          => [],
                'sales_pages'                => 1,
                'sales_current'              => 1,
                'financial_summary'          => [
                    'total_donations' => 0,
                    'total_merch'     => 0,
                    'grand_total'     => 0,
                ],
            ];
        }

        $chapelFilter = $this->resolveScopedChapelFilter();
        $stats = $this->donationModel->getStats($chapelFilter);

        $page = (int)($_GET['page'] ?? 1);
        $perPage = 30;

        // Collect GET filters for donation ledger
        $donFilters = [
            'don_from'      => $_GET['don_from']      ?? '',
            'don_to'        => $_GET['don_to']        ?? '',
            'don_method'    => $_GET['don_method']    ?? '',
            'don_chapel'    => $_GET['don_chapel']    ?? '',
            'don_timeframe' => $_GET['don_timeframe'] ?? '',
        ];
        // Non-admins can't filter by chapel (enforce their own)
        if ($chapelFilter !== null) {
            unset($donFilters['don_chapel']);
        }

        $donRes = $this->donationModel->getPaginated($page, $perPage, $chapelFilter, $donFilters);
        $items = $this->merchModel->getAllItems();
        $archivedItems = $this->merchModel->getArchivedItems();
        
        // Sales pagination (optional but good for consistency)
        $salesRes = $this->merchModel->getSalesPaginated($page, $perPage);
        $sales = $salesRes['records'];

        $totalMerch = (in_array($this->role, ['Admin', 'Cathedral Admin'], true) || $chapelFilter === 'cathedral')
            ? $this->merchModel->getTotalSalesRevenue()
            : 0.0;

        // Voided records visible only to Admin/Cathedral Admin
        $voidedDonations = in_array($this->role, ['Admin', 'Cathedral Admin'])
            ? $this->donationModel->getVoided(null, 100)
            : [];

        return [
            'donation_stats'    => $stats,
            'donations'         => $donRes['records'],
            'don_pages'         => $donRes['pages'],
            'don_current'       => $donRes['page'],
            'don_total'         => $donRes['total'],
            'voided_donations'  => $voidedDonations,
            'merchandise_items' => $items,
            'archived_merchandise_items' => $archivedItems,
            'merchandise_sales' => $sales,
            'sales_pages'       => $salesRes['pages'],
            'sales_current'     => $salesRes['page'],
            'financial_summary' => [
                'total_donations' => $stats['total_amount'],
                'total_merch'     => $totalMerch,
                'grand_total'     => $stats['total_amount'] + $totalMerch
            ],
        ];
    }

    public function getSummary() {
        $chapelFilter = $this->resolveScopedChapelFilter();
        $stats = $this->donationModel->getStats($chapelFilter);
        $totalMerch = (in_array($this->role, ['Admin', 'Cathedral Admin'], true) || $chapelFilter === 'cathedral')
            ? $this->merchModel->getTotalSalesRevenue()
            : 0.0;

        return [
            'total_donations' => $stats['total_amount'],
            'total_merch'     => $totalMerch,
            'grand_total'     => $stats['total_amount'] + $totalMerch
        ];
    }

    public function getDonationReceipt(int $id) {
        if (!in_array($this->role, ['Admin','Cathedral Admin','Chapel Admin','Staff'], true)) {
            return null;
        }
        $d = $this->donationModel->getById($id);
        if (!$d || !empty($d['is_archived'])) return null;
        
        if (!$this->checkChapelAccess($d['chapel_id'] ?? null)) {
            return null;
        }
        return $d;
    }

    public function getMerchandiseReceipt(int $id) {
        $isGlobalAdmin = in_array($this->role, ['Admin', 'Cathedral Admin'], true);
        $isCathedralStaff = ($this->role === 'Staff' && ((int)($this->chapelId ?? 0) === 0));
        if (!$isGlobalAdmin && !$isCathedralStaff) {
            return null;
        }
        $sale = $this->merchModel->getSaleById($id);
        if (!$sale) return null;

        if (isset($sale['chapel_id']) && $sale['chapel_id'] !== null && !$this->checkChapelAccess($sale['chapel_id'])) {
            return null;
        }
        return $sale;
    }

    private function logDonation() {
        if (!$this->permissions['can_donations']) {
            $this->flash('error', 'You do not have permission to log donations.');
            $this->redirect('dashboard.php?section=donations');
        }
        
        $amount   = (float)($_POST['amount'] ?? 0);
        if (!is_finite($amount) || $amount <= 0 || $amount > 100000000) {
            $this->flash('error', 'Donation amount must be greater than zero and within valid limits.');
            $this->redirect('dashboard.php?section=donations');
        }

        $purpose  = mb_substr(trim((string)($_POST['purpose'] ?? '')), 0, 100);
        $donor    = mb_substr(trim((string)($_POST['donor_name'] ?? '')), 0, 100);
        if ($donor === '') {
            $donor = 'Anonymous';
        }
        $date     = $_POST['date_received'] ?? date('Y-m-d');
        if (strtotime($date) === false || strtotime($date) > time()) {
            $this->flash('error', 'Donation date is invalid or cannot be in the future.');
            $this->redirect('dashboard.php?section=donations');
        }
        $allowedMethods = ['Cash', 'GCash', 'Bank Transfer', 'Maya', 'Check', 'Other'];
        $rawMethod = trim((string)($_POST['payment_method'] ?? 'Cash'));
        $method   = in_array($rawMethod, $allowedMethods, true) ? $rawMethod : 'Cash';
        $chapelId = $this->getAppliedChapelId($_POST['chapel_id'] ?? 0);
        $donorRef = mb_substr(trim((string)($_POST['donor_ref_id'] ?? '')), 0, 100);
        $refId    = $this->donationModel->generateRefId();

        $id = $this->donationModel->log($chapelId, $amount, $purpose, $donor, $date, $refId, $this->userId, $method, $donorRef);
        if ($id) {
            // Capture new donation snapshot
            $newDonation = $this->donationModel->getById((int)$id);
            AuditLogger::logFromSession(
                $this->conn,
                'created',
                'Finance',
                "Donation of ₱".number_format($amount,2)." from {$donor}",
                null,
                $newDonation
            );
            $this->flash('success', 'Donation logged successfully.');
            $_SESSION['new_donation_id'] = (int)$id; // For receipt link
        } else {
            $this->flash('error', 'Could not log donation. Please verify inputs and try again.');
        }
        $this->redirect('dashboard.php?section=donations');
    }

    private function voidDonation() {
        // Only Admin / Cathedral Admin can void financial records
        if (!in_array($this->role, ['Admin', 'Cathedral Admin'], true)) {
            $this->flash('error', 'Only Admins can void donation records.');
            $this->redirect('dashboard.php?section=donations');
        }

        $id     = (int)($_POST['id'] ?? 0);
        $reason = trim($_POST['void_reason'] ?? '');

        if ($id <= 0) {
            $this->flash('error', 'Invalid donation record.');
            $this->redirect('dashboard.php?section=donations');
        }
        if ($reason === '') {
            $this->flash('error', 'A reason is required to void a donation record.');
            $this->redirect('dashboard.php?section=donations');
        }

        // Capture donation state BEFORE voiding for accurate audit trail
        $before = $this->donationModel->getById($id);
        if ($this->donationModel->void($id, $reason, $this->userId)) {
            AuditLogger::logFromSession(
                $this->conn,
                'voided',
                'Finance',
                "Voided Donation ID: {$id}",
                $before,
                ['void_reason' => $reason]
            );
            $this->flash('success', 'Donation record voided and moved to the Voided ledger.');
        } else {
            $this->flash('error', 'Could not void this record. It may already be voided or does not exist.');
        }
        $this->redirect('dashboard.php?section=donations');
    }

    private function addMerchItem() {
        if (empty($this->permissions['can_manage_donations'])) {
            $this->flash('error', 'You do not have permission to add merchandise items.');
            $this->redirect('dashboard.php?section=merchandise');
            return;
        }
        
        $name = trim((string)($_POST['name'] ?? ''));
        if ($name === '') {
            $this->flash('error', 'Merchandise item name is required.');
            $this->redirect('dashboard.php?section=merchandise');
            return;
        }
        $desc = $_POST['description'] ?? '';
        $price = (float)($_POST['price'] ?? 0);
        $stock = (int)($_POST['stock'] ?? 0);
        
        if ($price < 0 || $stock < 0) {
            $this->flash('error', 'Price and stock cannot be negative.');
            $this->redirect('dashboard.php?section=merchandise');
        }
        $photo = $this->handleUpload('photo', 'storage/merchandise/');
        if ($photo === false) {
            $this->flash('error', $this->uploadError ?: 'Invalid merchandise upload.');
            $this->redirect('dashboard.php?section=merchandise');
        }

        if ($this->merchModel->addItem($name, $desc, $price, $stock, $photo)) {
            $newItem = ['name' => $name, 'price' => $price, 'stock' => $stock, 'photo' => $photo];
            AuditLogger::logFromSession(
                $this->conn,
                'created',
                'Merchandise',
                "Added item: {$name}",
                null,
                $newItem
            );
            $this->flash('success', 'Item added.');
        }
        $this->redirect('dashboard.php?section=merchandise');
    }

    private function editMerchItem() {
        if (empty($this->permissions['can_manage_donations'])) {
            $this->flash('error', 'You do not have permission to edit merchandise items.');
            $this->redirect('dashboard.php?section=merchandise');
            return;
        }
        $id = (int)$_POST['id'];
        $name = trim((string)($_POST['name'] ?? ''));
        if ($name === '') {
            $this->flash('error', 'Merchandise item name is required.');
            $this->redirect('dashboard.php?section=merchandise');
            return;
        }
        $desc = $_POST['description'] ?? '';
        $price = (float)($_POST['price'] ?? 0);
        $stock = (int)($_POST['stock'] ?? 0);
        
        if ($price < 0 || $stock < 0) {
            $this->flash('error', 'Price and stock cannot be negative.');
            $this->redirect('dashboard.php?section=merchandise');
        }
        $photo = $this->handleUpload('photo', 'storage/merchandise/');
        if ($photo === false) {
            $this->flash('error', $this->uploadError ?: 'Invalid merchandise upload.');
            $this->redirect('dashboard.php?section=merchandise');
        }

        // Capture old merchandise state before update
        $before = $this->merchModel->getById($id);

        if ($this->merchModel->updateItem($id, $name, $desc, $price, $stock, $photo)) {
            $newItem = $this->merchModel->getById($id);
            AuditLogger::logFromSession(
                $this->conn,
                'updated',
                'Merchandise',
                "Updated item: {$name}",
                $before,
                $newItem
            );
            $this->flash('success', 'Item updated.');
        } else {
            $this->flash('error', 'Could not update item.');
        }
        $this->redirect('dashboard.php?section=merchandise');
    }

    private function deleteMerchItem() {
        if (empty($this->permissions['can_manage_donations'])) {
            $this->flash('error', 'You do not have permission to delete merchandise items.');
            $this->redirect('dashboard.php?section=merchandise');
            return;
        }
        $id = (int)$_POST['id'];
        if ($id <= 0) {
            $this->flash('error', 'Invalid merchandise item.');
            $this->redirect('dashboard.php?section=merchandise');
        }

        // Capture item state BEFORE archiving for accurate audit trail
        $before = $this->merchModel->getById($id);
        if ($this->merchModel->archiveItem($id)) {
            AuditLogger::logFromSession(
                $this->conn,
                'deleted',
                'Merchandise',
                "Deleted item ID: {$id}",
                $before,
                null
            );
            $this->flash('success', $this->merchModel->supportsArchiveFeature() ? 'Item archived.' : 'Item removed.');
        } else {
            $this->flash('error', 'Could not archive the item. If this is an older database, please migrate the merchandise archive column first.');
        }
        $this->redirect('dashboard.php?section=merchandise');
    }

    private function restoreMerchItem() {
        if (empty($this->permissions['can_manage_donations'])) {
            $this->flash('error', 'You do not have permission to restore merchandise items.');
            $this->redirect('dashboard.php?section=merchandise');
            return;
        }
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            $this->flash('error', 'Invalid merchandise item.');
            $this->redirect('dashboard.php?section=merchandise');
        }

        // Capture old merchandise state before restoration
        $before = $this->merchModel->getItem($id, true);
        if ($this->merchModel->restoreItem($id)) {
            // Capture new merchandise state after restoration
            $after = $this->merchModel->getById($id);
            AuditLogger::logFromSession(
                $this->conn,
                'updated',
                'Merchandise',
                "Restored item ID: {$id}",
                $before,
                $after
            );
            $this->flash('success', 'Item restored.');
        } else {
            $this->flash('error', 'Could not restore the item.');
        }
        $this->redirect('dashboard.php?section=merchandise');
    }

    private function recordMerchSale() {
        if (!$this->permissions['can_donations']) {
            $this->flash('error', 'You do not have permission to record sales.');
            $this->redirect('dashboard.php?section=merchandise');
        }

        $id = (int)$_POST['item_id'];
        $buyer = trim((string)($_POST['buyer_name'] ?? 'Walk-in'));
        $qty = (int)($_POST['quantity'] ?? 1);
        $inquiryId = isset($_POST['inquiry_id']) ? (int)$_POST['inquiry_id'] : 0;

        if ($id <= 0) {
            $this->flash('error', 'Please choose a valid merchandise item.');
            $this->redirect('dashboard.php?section=merchandise');
        }
        if ($qty <= 0) {
            $this->flash('error', 'Quantity must be at least 1.');
            $this->redirect('dashboard.php?section=merchandise');
        }

        $before = $this->merchModel->getById($id);

        if ($inquiryId > 0) {
            require_once BASE_PATH . '/app/models/Inquiry.php';
            $inquiryModel = new Inquiry($this->conn);
            $inq = null;
            $res = true;

            $this->conn->begin_transaction();
            try {
                $lockStmt = $this->conn->prepare("SELECT id, email, full_name, phone, service_category, status, is_archived FROM public_inquiries WHERE id = ? FOR UPDATE");
                if ($lockStmt) {
                    $lockStmt->bind_param('i', $inquiryId);
                    $lockStmt->execute();
                    $inq = $lockStmt->get_result()->fetch_assoc();
                    $lockStmt->close();
                }
                if (!$inq || ($inq['status'] ?? '') === 'Completed' || !empty($inq['is_archived'])) {
                    throw new RuntimeException('Reservation #' . $inquiryId . ' has already been fulfilled or does not exist.');
                }

                $res = $this->merchModel->recordSale($id, $buyer, $qty, $this->userId, false);
                if ($res !== true) {
                    throw new RuntimeException($res ?: 'Sale failed.');
                }

                if (!$inquiryModel->updateStatus($inquiryId, 'Completed', "Fulfillment: Sale recorded & stock deducted via Merchandise Ledger.")
                    || !$inquiryModel->archive($inquiryId)) {
                    throw new RuntimeException('Failed to mark reservation completed.');
                }
                $this->conn->commit();
            } catch (Throwable $e) {
                $this->conn->rollback();
                $this->flash('error', $e->getMessage());
                $this->redirect('dashboard.php?section=merchandise');
                return;
            }

            AuditLogger::logFromSession(
                $this->conn,
                'created',
                'Finance',
                "Sale: {$qty}x Item ID {$id} to {$buyer}",
                $before,
                ['sale_qty' => $qty, 'buyer' => $buyer]
            );

            // Trigger notifications for the completed inquiry
            require_once BASE_PATH . '/app/mail/InquiryMailer.php';
            $mailSent = !empty($inq['email'])
                ? InquiryMailer::sendStatusUpdate($inq['email'], $inq['full_name'], $inq['service_category'], 'Completed', 'Your reservation has been processed and is now marked as picked up. Thank you!')
                : false;

            require_once BASE_PATH . '/app/services/SmsService.php';
            $smsSent = !empty($inq['phone'])
                ? SmsService::sendInquiryUpdated($this->conn, $inquiryId, 'Completed')
                : false;

            $notif = [];
            if (!empty($inq['email'])) {
                $mailErr = trim((string)($GLOBALS['lastMailError'] ?? ''));
                $notif[] = $mailSent ? 'Email sent' : ('Email failed' . ($mailErr !== '' ? ": {$mailErr}" : ''));
            }
            if (!empty($inq['phone'])) {
                $smsErr = trim((string) SmsService::getLastError());
                $notif[] = $smsSent ? 'SMS sent' : ('SMS failed' . ($smsErr !== '' ? ": {$smsErr}" : ''));
            }
            $notifStr = !empty($notif) ? ' (' . implode(', ', $notif) . ')' : '';

            $this->flash('success', "Sale recorded and linked reservation #{$inquiryId} automatically completed & archived.{$notifStr}");
        } else {
            $res = $this->merchModel->recordSale($id, $buyer, $qty, $this->userId);
            if ($res === true) {
                AuditLogger::logFromSession(
                    $this->conn,
                    'created',
                    'Finance',
                    "Sale: {$qty}x Item ID {$id} to {$buyer}",
                    $before,
                    ['sale_qty' => $qty, 'buyer' => $buyer]
                );
                $this->flash('success', 'Sale recorded.');
            } else {
                $this->flash('error', $res ?: 'Sale failed.');
            }
        }
        $this->redirect('dashboard.php?section=merchandise');
    }

    private function sanitizeCsvCell($value): string {
        $str = (string) ($value ?? '');
        if ($str !== '' && preg_match('/^[=+\-@\t\r]/', $str)) {
            return "'" . $str;
        }
        return $str;
    }

    public function exportDonations() {
        if (!$this->permissions['can_finance_reports']) {
            $this->flash('error', 'Unauthorized to export financial data.');
            $this->redirect('dashboard.php?section=donations');
        }

        $chapelFilter = $this->resolveScopedChapelFilter();
        $data = $this->donationModel->getAll(1000, $chapelFilter);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=donations_export_' . date('Y-m-d') . '.csv');
        
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Ref ID', 'Donor', 'Amount', 'Purpose', 'Date', 'Method', 'Chapel']);
        
        foreach ($data as $row) {
            fputcsv($output, [
                $this->sanitizeCsvCell($row['reference_id'] ?? ''),
                $this->sanitizeCsvCell($row['donor_name'] ?? ''),
                $row['amount'],
                $this->sanitizeCsvCell($row['purpose'] ?? ''),
                $row['date_received'],
                $this->sanitizeCsvCell($row['payment_method'] ?? ''),
                $this->sanitizeCsvCell($row['chapel_name'] ?? 'Cathedral')
            ]);
        }
        fclose($output);
        exit();
    }

    public function exportSales() {
        if (!$this->permissions['can_finance_reports']) {
            $this->flash('error', 'Unauthorized to export sales data.');
            $this->redirect('dashboard.php?section=merchandise');
        }
        $scopeFilter = $this->resolveScopedChapelFilter();
        if ($scopeFilter !== null && $scopeFilter !== 'cathedral') {
            $this->flash('error', 'Unauthorized to export parish merchandise sales.');
            $this->redirect('dashboard.php?section=merchandise');
        }

        $data = $this->merchModel->getSales(1000);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=sales_export_' . date('Y-m-d') . '.csv');
        
        $output = fopen('php://output', 'w');
        fputcsv($output, ['ID', 'Item', 'Buyer', 'Qty', 'Total', 'Date']);
        
        foreach ($data as $row) {
            fputcsv($output, [
                $row['id'],
                $this->sanitizeCsvCell($row['item_name'] ?? ''),
                $this->sanitizeCsvCell($row['buyer_name'] ?? ''),
                $row['quantity'],
                $row['total_price'],
                $row['sale_date']
            ]);
        }
        fclose($output);
        exit();
    }

    private function handleUpload($key, $dir) {
        $this->uploadError = null;
        if (!isset($_FILES[$key])) return null;

        $file = $_FILES[$key];
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $this->uploadError = 'Upload failed. Please try again.';
            return false;
        }

        $maxBytes = 5 * 1024 * 1024;
        if (($file['size'] ?? 0) <= 0 || $file['size'] > $maxBytes) {
            $this->uploadError = 'Image must be 5MB or smaller.';
            return false;
        }

        $ext = strtolower((string) pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
        ];
        if (!isset($allowed[$ext])) {
            $this->uploadError = 'Only JPG, PNG, and WEBP files are allowed.';
            return false;
        }

        $tmpName = $file['tmp_name'] ?? '';
        if (!is_uploaded_file($tmpName)) {
            $this->uploadError = 'Invalid upload source.';
            return false;
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($tmpName) ?: '';
        if ($mime !== $allowed[$ext]) {
            $this->uploadError = 'Uploaded file content does not match the selected file type.';
            return false;
        }

        $targetDir = rtrim(BASE_PATH . '/' . trim($dir, '/\\'), '/\\') . DIRECTORY_SEPARATOR;
        if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true)) {
            $this->uploadError = 'Unable to prepare upload storage.';
            return false;
        }

        try {
            $randomName = bin2hex(random_bytes(16)) . '.' . $ext;
        } catch (Throwable $e) {
            $this->uploadError = 'Unable to generate a secure filename.';
            return false;
        }

        if (move_uploaded_file($tmpName, $targetDir . $randomName)) {
            return rtrim($dir, '/\\') . '/' . $randomName;
        }

        $this->uploadError = 'Failed to save the uploaded file.';
        return false;
    }

    private function normalizeNullableInt($value): ?int {
        if ($value === null || $value === '') return null;
        $intValue = (int)$value;
        return $intValue > 0 ? $intValue : null;
    }

    private function getChapelName(int $chapelId): string {
        $stmt = $this->conn->prepare("SELECT chapel_name FROM chapels WHERE chapel_id = ? LIMIT 1");
        if (!$stmt) return 'Chapel ID ' . $chapelId;
        $stmt->bind_param('i', $chapelId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row['chapel_name'] ?? ('Chapel ID ' . $chapelId);
    }

    private function chapelExists(int $chapelId): bool {
        $stmt = $this->conn->prepare("SELECT 1 FROM chapels WHERE chapel_id = ? LIMIT 1");
        if (!$stmt) return false;
        $stmt->bind_param('i', $chapelId);
        $stmt->execute();
        $exists = (bool)$stmt->get_result()->fetch_row();
        $stmt->close();
        return $exists;
    }
}
