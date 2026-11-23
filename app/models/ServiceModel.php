<?php
/**
 * ServiceModel — Data layer for Sacramental & Liturgical Services Catalog,
 * variants, requirements, additional charges, schedules, and certificate rates.
 */
class ServiceModel
{
    protected mysqli $conn;
    private static array $catalogCache = [];

    public function __construct(mysqli $conn)
    {
        $this->conn = $conn;
    }

    public static function clearCatalogCache(): void
    {
        self::$catalogCache = [];
    }

    /**
     * Get all categories with nested services, variants, requirements, charges, and schedules
     * Uses bulk IN (...) queries + per-request cache instead of nested N+1 queries.
     */
    public function getFullCatalog(bool $activeOnly = true): array
    {
        $cacheKey = spl_object_id($this->conn) . '|' . ($activeOnly ? '1' : '0');
        if (isset(self::$catalogCache[$cacheKey])) {
            return self::$catalogCache[$cacheKey];
        }

        $statusClause = $activeOnly ? "WHERE sc.status = 'active'" : "";
        $res = $this->conn->query("
            SELECT sc.* 
            FROM service_categories sc 
            {$statusClause}
            ORDER BY sc.display_order ASC, sc.name ASC
        ");

        $catalog = [];
        $catIndexById = [];
        if (!$res) {
            return $catalog;
        }

        while ($cat = $res->fetch_assoc()) {
            $cat['services'] = [];
            $cid = (int)$cat['id'];
            $catIndexById[$cid] = count($catalog);
            $catalog[] = $cat;
        }
        if (empty($catalog)) {
            return self::$catalogCache[$cacheKey] = $catalog;
        }

        $catIds = implode(',', array_keys($catIndexById));
        $srvStatus = $activeOnly ? "AND s.status = 'active'" : "";
        $srvRes = $this->conn->query("
            SELECT s.* 
            FROM services s 
            WHERE s.category_id IN ({$catIds}) {$srvStatus}
            ORDER BY s.id ASC
        ");

        $servicesById = [];
        $serviceCatById = [];
        if ($srvRes) {
            while ($srv = $srvRes->fetch_assoc()) {
                $sid = (int)$srv['id'];
                $srv['variants']     = [];
                $srv['requirements'] = [];
                $srv['charges']      = [];
                $srv['schedules']    = [];
                $servicesById[$sid]   = $srv;
                $serviceCatById[$sid] = (int)$srv['category_id'];
            }
        }

        if (!empty($servicesById)) {
            $sidList = implode(',', array_keys($servicesById));

            $varFilter = $activeOnly ? "AND status = 'active' AND is_current = 1" : "";
            if ($r = $this->conn->query("SELECT * FROM service_variants WHERE service_id IN ({$sidList}) {$varFilter} ORDER BY price ASC, id ASC")) {
                while ($row = $r->fetch_assoc()) {
                    $servicesById[(int)$row['service_id']]['variants'][] = $row;
                }
            }

            $reqFilter = $activeOnly ? "AND status = 'active'" : "";
            if ($r = $this->conn->query("SELECT * FROM service_requirements WHERE service_id IN ({$sidList}) {$reqFilter} ORDER BY is_required DESC, id ASC")) {
                while ($row = $r->fetch_assoc()) {
                    $servicesById[(int)$row['service_id']]['requirements'][] = $row;
                }
            }

            $chgFilter = $activeOnly ? "AND status = 'active'" : "";
            if ($r = $this->conn->query("SELECT * FROM service_additional_charges WHERE service_id IN ({$sidList}) {$chgFilter} ORDER BY price ASC, id ASC")) {
                while ($row = $r->fetch_assoc()) {
                    $servicesById[(int)$row['service_id']]['charges'][] = $row;
                }
            }

            $schFilter = $activeOnly ? "AND status = 'active'" : "";
            if ($r = $this->conn->query("SELECT * FROM service_schedules WHERE service_id IN ({$sidList}) {$schFilter} ORDER BY id ASC")) {
                while ($row = $r->fetch_assoc()) {
                    $servicesById[(int)$row['service_id']]['schedules'][] = $row;
                }
            }

            foreach ($servicesById as $sid => $srvData) {
                $catIdx = $catIndexById[$serviceCatById[$sid]] ?? null;
                if ($catIdx !== null) {
                    $catalog[$catIdx]['services'][] = $srvData;
                }
            }
        }

        self::$catalogCache[$cacheKey] = $catalog;
        return $catalog;
    }

    public function getServiceById(int $id): ?array
    {
        $stmt = $this->conn->prepare("
            SELECT s.*, sc.name AS category_name, sc.code AS category_code 
            FROM services s 
            JOIN service_categories sc ON s.category_id = sc.id 
            WHERE s.id = ? LIMIT 1
        ");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $res = $stmt->get_result();
        $service = $res->fetch_assoc();
        if (!$service) return null;

        $service['variants']     = $this->getVariants($id, false);
        $service['requirements'] = $this->getRequirements($id, false);
        $service['charges']      = $this->getAdditionalCharges($id, false);
        $service['schedules']    = $this->getSchedules($id, false);
        return $service;
    }

    public function getServiceByCode(string $code): ?array
    {
        $stmt = $this->conn->prepare("SELECT id FROM services WHERE code = ? LIMIT 1");
        $stmt->bind_param("s", $code);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        if (!$row) return null;
        return $this->getServiceById((int)$row['id']);
    }

    public function getVariants(int $serviceId, bool $activeOnly = true): array
    {
        $statusClause = $activeOnly ? "AND status = 'active' AND is_current = 1" : "";
        $res = $this->conn->query("
            SELECT * FROM service_variants 
            WHERE service_id = {$serviceId} {$statusClause}
            ORDER BY price ASC, id ASC
        ");
        $rows = [];
        if ($res) {
            while ($r = $res->fetch_assoc()) $rows[] = $r;
        }
        return $rows;
    }

    public function getVariantById(int $variantId): ?array
    {
        $stmt = $this->conn->prepare("SELECT * FROM service_variants WHERE id = ? LIMIT 1");
        $stmt->bind_param("i", $variantId);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    public function getRequirements(int $serviceId, bool $activeOnly = true): array
    {
        $statusClause = $activeOnly ? "AND status = 'active'" : "";
        $res = $this->conn->query("
            SELECT * FROM service_requirements 
            WHERE service_id = {$serviceId} {$statusClause}
            ORDER BY is_required DESC, id ASC
        ");
        $rows = [];
        if ($res) {
            while ($r = $res->fetch_assoc()) $rows[] = $r;
        }
        return $rows;
    }

    public function getAdditionalCharges(int $serviceId, bool $activeOnly = true): array
    {
        $statusClause = $activeOnly ? "AND status = 'active'" : "";
        $res = $this->conn->query("
            SELECT * FROM service_additional_charges 
            WHERE service_id = {$serviceId} {$statusClause}
            ORDER BY price ASC, id ASC
        ");
        $rows = [];
        if ($res) {
            while ($r = $res->fetch_assoc()) $rows[] = $r;
        }
        return $rows;
    }

    public function getSchedules(int $serviceId, bool $activeOnly = true): array
    {
        $statusClause = $activeOnly ? "AND status = 'active'" : "";
        $res = $this->conn->query("
            SELECT * FROM service_schedules 
            WHERE service_id = {$serviceId} {$statusClause}
            ORDER BY id ASC
        ");
        $rows = [];
        if ($res) {
            while ($r = $res->fetch_assoc()) $rows[] = $r;
        }
        return $rows;
    }

    public function getCertificateTypes(): array
    {
        $res = $this->conn->query("SELECT * FROM certificate_types ORDER BY name ASC");
        $rows = [];
        if ($res) {
            while ($r = $res->fetch_assoc()) $rows[] = $r;
        }
        return $rows;
    }

    public function updateCertificateRate(int $id, float $price): bool
    {
        $stmt = $this->conn->prepare("UPDATE certificate_types SET price_per_copy = ?, updated_at = NOW() WHERE id = ?");
        $stmt->bind_param("di", $price, $id);
        $ok = $stmt->execute();
        if ($ok) self::clearCatalogCache();
        return $ok;
    }

    /**
     * Rate Versioning: Update variant price. Marks previous variant effective_until and creates/updates version
     */
    public function updateVariantPrice(int $variantId, float $newPrice, string $effectiveFrom = '', bool $versionRate = true): bool
    {
        $old = $this->getVariantById($variantId);
        if (!$old) return false;

        $effDate = !empty($effectiveFrom) ? $effectiveFrom : date('Y-m-d');

        if ($versionRate) {
            // Close the old rate window
            $stmtClose = $this->conn->prepare("UPDATE service_variants SET effective_until = ?, is_current = 0 WHERE id = ?");
            $yesterday = date('Y-m-d', strtotime('-1 day', strtotime($effDate)));
            $stmtClose->bind_param("si", $yesterday, $variantId);
            $stmtClose->execute();

            // Insert new version
            $stmtNew = $this->conn->prepare("
                INSERT INTO service_variants 
                (service_id, name, description, price, schedule_type, included_sponsors, effective_from, is_current, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, 1, 'active')
            ");
            $sId = (int)$old['service_id'];
            $name = $old['name'];
            $desc = $old['description'];
            $sched = $old['schedule_type'];
            $spons = (int)$old['included_sponsors'];
            $stmtNew->bind_param("issdsis", $sId, $name, $desc, $newPrice, $sched, $spons, $effDate);
            $ok = $stmtNew->execute();
            if ($ok) self::clearCatalogCache();
            return $ok;
        } else {
            // Direct overwrite
            $stmt = $this->conn->prepare("UPDATE service_variants SET price = ?, effective_from = ? WHERE id = ?");
            $stmt->bind_param("dsi", $newPrice, $effDate, $variantId);
            $ok = $stmt->execute();
            if ($ok) self::clearCatalogCache();
            return $ok;
        }
    }

    public function createVariant(int $serviceId, string $name, float $price, string $description = '', string $scheduleType = 'Standard', int $sponsors = 0): int
    {
        $stmt = $this->conn->prepare("
            INSERT INTO service_variants (service_id, name, description, price, schedule_type, included_sponsors, effective_from, is_current, status)
            VALUES (?, ?, ?, ?, ?, ?, CURDATE(), 1, 'active')
        ");
        $stmt->bind_param("issdsi", $serviceId, $name, $description, $price, $scheduleType, $sponsors);
        if ($stmt->execute()) {
            self::clearCatalogCache();
            return $this->conn->insert_id;
        }
        return 0;
    }

    public function toggleVariantStatus(int $variantId, string $status): bool
    {
        $stmt = $this->conn->prepare("UPDATE service_variants SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $status, $variantId);
        $ok = $stmt->execute();
        if ($ok) self::clearCatalogCache();
        return $ok;
    }

    public function addRequirement(int $serviceId, string $requirement, bool $isRequired = true, string $description = ''): int
    {
        $reqVal = $isRequired ? 1 : 0;
        $stmt = $this->conn->prepare("
            INSERT INTO service_requirements (service_id, requirement, is_required, description, status)
            VALUES (?, ?, ?, ?, 'active')
        ");
        $stmt->bind_param("isis", $serviceId, $requirement, $reqVal, $description);
        if ($stmt->execute()) {
            self::clearCatalogCache();
            return $this->conn->insert_id;
        }
        return 0;
    }

    public function removeRequirement(int $requirementId): bool
    {
        $stmt = $this->conn->prepare("DELETE FROM service_requirements WHERE id = ?");
        $stmt->bind_param("i", $requirementId);
        $ok = $stmt->execute();
        if ($ok) self::clearCatalogCache();
        return $ok;
    }

    public function addAdditionalCharge(int $serviceId, string $name, float $price, string $description = '', bool $isOptional = true): int
    {
        $optVal = $isOptional ? 1 : 0;
        $stmt = $this->conn->prepare("
            INSERT INTO service_additional_charges (service_id, name, description, price, is_optional, status)
            VALUES (?, ?, ?, ?, ?, 'active')
        ");
        $stmt->bind_param("issdi", $serviceId, $name, $description, $price, $optVal);
        if ($stmt->execute()) {
            self::clearCatalogCache();
            return $this->conn->insert_id;
        }
        return 0;
    }

    public function removeAdditionalCharge(int $chargeId): bool
    {
        $stmt = $this->conn->prepare("DELETE FROM service_additional_charges WHERE id = ?");
        $stmt->bind_param("i", $chargeId);
        $ok = $stmt->execute();
        if ($ok) self::clearCatalogCache();
        return $ok;
    }

    public function updateAdditionalChargePrice(int $chargeId, float $price): bool
    {
        $stmt = $this->conn->prepare("UPDATE service_additional_charges SET price = ? WHERE id = ?");
        $stmt->bind_param("di", $price, $chargeId);
        $ok = $stmt->execute();
        if ($ok) self::clearCatalogCache();
        return $ok;
    }

    public function updateServiceStatus(int $serviceId, string $status): bool
    {
        $stmt = $this->conn->prepare("UPDATE services SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $status, $serviceId);
        $ok = $stmt->execute();
        if ($ok) self::clearCatalogCache();
        return $ok;
    }

    public function updateServiceMetadata(int $serviceId, string $name, string $description, string $referenceSource, string $effectiveDate, bool $isReferenceOnly): bool
    {
        $refVal = $isReferenceOnly ? 1 : 0;
        $stmt = $this->conn->prepare("
            UPDATE services 
            SET name = ?, description = ?, reference_source = ?, effective_date = ?, is_reference_only = ?
            WHERE id = ?
        ");
        $stmt->bind_param("ssssii", $name, $description, $referenceSource, $effectiveDate, $refVal, $serviceId);
        $ok = $stmt->execute();
        if ($ok) self::clearCatalogCache();
        return $ok;
    }

    /**
     * Server-side Price Verification and Snapshotting Calculation.
     * Guaranteed: never trusts client price inputs.
     */
    public function calculateAndVerifyFee(int $serviceId, int $variantId, array $selectedAddonIds = [], int $quantity = 1): array
    {
        $variant = $this->getVariantById($variantId);
        if (!$variant || (int)$variant['service_id'] !== $serviceId || $variant['status'] !== 'active') {
            throw new InvalidArgumentException("Invalid or inactive service variant.");
        }

        $quantity = max(1, $quantity);
        $unitPrice = (float)$variant['price'];
        $baseFee = $unitPrice * $quantity;
        $addonsFee = 0.00;
        $validAddons = [];

        if (!empty($selectedAddonIds)) {
            $inList = implode(',', array_map('intval', $selectedAddonIds));
            $res = $this->conn->query("
                SELECT * FROM service_additional_charges 
                WHERE id IN ({$inList}) AND service_id = {$serviceId} AND status = 'active'
            ");
            if ($res) {
                while ($addon = $res->fetch_assoc()) {
                    $addonsFee += (float)$addon['price'];
                    $validAddons[] = [
                        'addon_id'   => (int)$addon['id'],
                        'addon_name' => $addon['name'],
                        'unit_price' => (float)$addon['price'],
                        'quantity'   => 1,
                        'subtotal'   => (float)$addon['price'],
                    ];
                }
            }
        }

        $totalFee = $baseFee + $addonsFee;

        return [
            'unit_price'   => $unitPrice,
            'quantity'     => $quantity,
            'base_fee'     => $baseFee,
            'addons_fee'   => $addonsFee,
            'total_fee'    => $totalFee,
            'variant_name' => $variant['name'],
            'addons'       => $validAddons
        ];
    }
}
