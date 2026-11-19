<?php
/**
 * ServiceController — Administrative management of Sacramental & Liturgical Services.
 */
require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../models/ServiceModel.php';
require_once __DIR__ . '/../models/AuditLogger.php';

class ServiceController extends BaseController
{
    private ServiceModel $serviceModel;

    public function __construct(mysqli $conn, bool $mustAuth = true)
    {
        parent::__construct($conn, $mustAuth);
        $this->serviceModel = new ServiceModel($conn);
    }

    public function getModel(): ServiceModel
    {
        return $this->serviceModel;
    }

    public function handleAdminActions(): void
    {
        if (!in_array($this->role, ['Admin', 'Cathedral Admin'])) {
            $this->flash('error', 'Unauthorized: Only Cathedral Admins can manage services.');
            $this->redirect('dashboard.php?section=services');
        }

        $this->enforceCsrf('dashboard.php?section=services', 'Security token expired. Please refresh.');
        $action = $_POST['service_action'] ?? '';

        switch ($action) {
            case 'update_variant_price':
                $this->handleUpdateVariantPrice();
                break;
            case 'create_variant':
                $this->handleCreateVariant();
                break;
            case 'toggle_variant_status':
                $this->handleToggleVariantStatus();
                break;
            case 'add_requirement':
                $this->handleAddRequirement();
                break;
            case 'remove_requirement':
                $this->handleRemoveRequirement();
                break;
            case 'add_charge':
                $this->handleAddCharge();
                break;
            case 'update_charge_price':
                $this->handleUpdateChargePrice();
                break;
            case 'remove_charge':
                $this->handleRemoveCharge();
                break;
            case 'update_cert_rate':
                $this->handleUpdateCertRate();
                break;
            case 'update_metadata':
                $this->handleUpdateMetadata();
                break;
            default:
                $this->flash('error', 'Unknown service management action.');
                $this->redirect('dashboard.php?section=services');
        }
    }

    private function handleUpdateVariantPrice(): void
    {
        $variantId = (int)($_POST['variant_id'] ?? 0);
        $newPrice = (float)($_POST['new_price'] ?? 0);
        $effectiveDate = trim($_POST['effective_date'] ?? date('Y-m-d'));
        $versionRate = isset($_POST['version_rate']) ? (bool)$_POST['version_rate'] : true;

        $old = $this->serviceModel->getVariantById($variantId);
        if (!$old) {
            $this->flash('error', 'Variant not found.');
            $this->redirect('dashboard.php?section=services');
        }

        if ($newPrice < 0) {
            $this->flash('error', 'Price cannot be negative.');
            $this->redirect('dashboard.php?section=services');
        }

        $ok = $this->serviceModel->updateVariantPrice($variantId, $newPrice, $effectiveDate, $versionRate);
        if ($ok) {
            AuditLogger::logFromSession(
                $this->conn,
                'updated',
                'Services',
                "Rate updated for {$old['name']}: PHP " . number_format($newPrice, 2),
                ['price' => $old['price'], 'effective_from' => $old['effective_from']],
                ['price' => $newPrice, 'effective_from' => $effectiveDate]
            );
            $this->flash('success', "Service rate successfully updated to PHP " . number_format($newPrice, 2));
        } else {
            $this->flash('error', 'Failed to update service rate.');
        }
        $this->redirect('dashboard.php?section=services');
    }

    private function handleCreateVariant(): void
    {
        $serviceId = (int)($_POST['service_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $price = (float)($_POST['price'] ?? 0);
        $desc = trim($_POST['description'] ?? '');
        $sched = trim($_POST['schedule_type'] ?? 'Standard');
        $spons = (int)($_POST['included_sponsors'] ?? 0);

        if (empty($name) || $serviceId <= 0) {
            $this->flash('error', 'Service variant name is required.');
            $this->redirect('dashboard.php?section=services');
        }

        $newId = $this->serviceModel->createVariant($serviceId, $name, $price, $desc, $sched, $spons);
        if ($newId > 0) {
            AuditLogger::logFromSession(
                $this->conn,
                'created',
                'Services',
                "Created variant {$name} (PHP " . number_format($price, 2) . ")",
                null,
                ['variant_id' => $newId, 'name' => $name, 'price' => $price]
            );
            $this->flash('success', "Variant '{$name}' created successfully.");
        } else {
            $this->flash('error', 'Failed to create variant.');
        }
        $this->redirect('dashboard.php?section=services');
    }

    private function handleToggleVariantStatus(): void
    {
        $variantId = (int)($_POST['variant_id'] ?? 0);
        $status = ($_POST['status'] ?? '') === 'active' ? 'active' : 'inactive';
        $old = $this->serviceModel->getVariantById($variantId);

        if ($old && $this->serviceModel->toggleVariantStatus($variantId, $status)) {
            AuditLogger::logFromSession(
                $this->conn,
                'updated',
                'Services',
                "Variant {$old['name']} marked as {$status}",
                ['status' => $old['status']],
                ['status' => $status]
            );
            $this->flash('success', "Variant marked as {$status}.");
        } else {
            $this->flash('error', 'Failed to change variant status.');
        }
        $this->redirect('dashboard.php?section=services');
    }

    private function handleAddRequirement(): void
    {
        $serviceId = (int)($_POST['service_id'] ?? 0);
        $req = trim($_POST['requirement'] ?? '');
        $isReq = isset($_POST['is_required']) ? true : false;
        $desc = trim($_POST['description'] ?? '');

        if (empty($req) || $serviceId <= 0) {
            $this->flash('error', 'Requirement description is required.');
            $this->redirect('dashboard.php?section=services');
        }

        $id = $this->serviceModel->addRequirement($serviceId, $req, $isReq, $desc);
        if ($id > 0) {
            AuditLogger::logFromSession(
                $this->conn,
                'created',
                'Services',
                "Added requirement: {$req}",
                null,
                ['requirement' => $req, 'is_required' => $isReq]
            );
            $this->flash('success', 'Requirement added successfully.');
        } else {
            $this->flash('error', 'Failed to add requirement.');
        }
        $this->redirect('dashboard.php?section=services');
    }

    private function handleRemoveRequirement(): void
    {
        $reqId = (int)($_POST['requirement_id'] ?? 0);
        if ($this->serviceModel->removeRequirement($reqId)) {
            AuditLogger::logFromSession(
                $this->conn,
                'deleted',
                'Services',
                "Removed requirement ID: {$reqId}"
            );
            $this->flash('success', 'Requirement removed.');
        } else {
            $this->flash('error', 'Failed to remove requirement.');
        }
        $this->redirect('dashboard.php?section=services');
    }

    private function handleAddCharge(): void
    {
        $serviceId = (int)($_POST['service_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $price = (float)($_POST['price'] ?? 0);
        $desc = trim($_POST['description'] ?? '');
        $isOpt = isset($_POST['is_optional']) ? true : false;

        if (empty($name) || $serviceId <= 0) {
            $this->flash('error', 'Charge name is required.');
            $this->redirect('dashboard.php?section=services');
        }

        $id = $this->serviceModel->addAdditionalCharge($serviceId, $name, $price, $desc, $isOpt);
        if ($id > 0) {
            AuditLogger::logFromSession(
                $this->conn,
                'created',
                'Services',
                "Added charge: {$name} (PHP " . number_format($price, 2) . ")",
                null,
                ['name' => $name, 'price' => $price]
            );
            $this->flash('success', 'Additional charge added.');
        } else {
            $this->flash('error', 'Failed to add additional charge.');
        }
        $this->redirect('dashboard.php?section=services');
    }

    private function handleUpdateChargePrice(): void
    {
        $chargeId = (int)($_POST['charge_id'] ?? 0);
        $price = (float)($_POST['price'] ?? 0);

        if ($this->serviceModel->updateAdditionalChargePrice($chargeId, $price)) {
            AuditLogger::logFromSession(
                $this->conn,
                'updated',
                'Services',
                "Updated additional charge ID {$chargeId} price to PHP " . number_format($price, 2)
            );
            $this->flash('success', 'Additional charge price updated.');
        } else {
            $this->flash('error', 'Failed to update additional charge.');
        }
        $this->redirect('dashboard.php?section=services');
    }

    private function handleRemoveCharge(): void
    {
        $chargeId = (int)($_POST['charge_id'] ?? 0);
        if ($this->serviceModel->removeAdditionalCharge($chargeId)) {
            AuditLogger::logFromSession(
                $this->conn,
                'deleted',
                'Services',
                "Removed additional charge ID: {$chargeId}"
            );
            $this->flash('success', 'Additional charge removed.');
        } else {
            $this->flash('error', 'Failed to remove additional charge.');
        }
        $this->redirect('dashboard.php?section=services');
    }

    private function handleUpdateCertRate(): void
    {
        $certId = (int)($_POST['cert_id'] ?? 0);
        $price = (float)($_POST['price_per_copy'] ?? 0);

        if ($this->serviceModel->updateCertificateRate($certId, $price)) {
            AuditLogger::logFromSession(
                $this->conn,
                'updated',
                'Services',
                "Updated certificate ID {$certId} rate to PHP " . number_format($price, 2)
            );
            $this->flash('success', 'Certificate price updated.');
        } else {
            $this->flash('error', 'Failed to update certificate rate.');
        }
        $this->redirect('dashboard.php?section=services');
    }

    private function handleUpdateMetadata(): void
    {
        $serviceId = (int)($_POST['service_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        $ref = trim($_POST['reference_source'] ?? '');
        $eff = trim($_POST['effective_date'] ?? '2013-01-01');
        $isRef = isset($_POST['is_reference_only']) ? true : false;

        if ($this->serviceModel->updateServiceMetadata($serviceId, $name, $desc, $ref, $eff, $isRef)) {
            AuditLogger::logFromSession(
                $this->conn,
                'updated',
                'Services',
                "Updated service metadata for: {$name}"
            );
            $this->flash('success', 'Service details updated.');
        } else {
            $this->flash('error', 'Failed to update service details.');
        }
        $this->redirect('dashboard.php?section=services');
    }

}
