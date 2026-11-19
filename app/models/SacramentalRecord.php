<?php
require_once __DIR__ . '/../services/SacramentalCertificateService.php';
require_once __DIR__ . '/../services/SacramentalReportingService.php';
/**
 * SacramentalRecord Model
 * 
 * Handles all DB operations for the sacramental records module.
 * Supports Baptism, Wedding, and Funeral records with full audit logging,
 * certificate issuance tracking, and multi-chapel scope.
 */
class SacramentalRecord
{
    private mysqli $conn;
    private SacramentalCertificateService $certificateService;
    private SacramentalReportingService $reportingService;

    // ── Reference ID prefixes ──────────────────────────────────────
    const PREFIX = [
        'Baptism'      => 'BAP',
        'Wedding'      => 'WED',
        'Funeral'      => 'FUN',
        'Confirmation' => 'CON',
        'Communion'    => 'COM',
    ];

    public function __construct(mysqli $conn)
    {
        $this->conn = $conn;
        $this->certificateService = new SacramentalCertificateService($conn);
        $this->reportingService = new SacramentalReportingService($conn);
    }

    // ════════════════════════════════════════════════════════════════
    // READ / SEARCH
    // ════════════════════════════════════════════════════════════════

    /**
     * Paginated search with full-text filtering.
     *
     * @param array $filters  Keys: scope, chapel_id, record_type, search,
     *                               date_from, date_to, encoded_by
     * @param int   $page     1-based page number
     * @param int   $per_page rows per page
     * @return array { records: [], total: int, pages: int }
     */
    public function search(array $filters = [], int $page = 1, int $per_page = 20): array
    {
        $where  = ['sr.is_archived = 0'];
        $params = [];
        $types  = '';

        if (!empty($filters['scope'])) {
            if ($filters['scope'] === 'cathedral') {
                $where[] = "(sr.scope = 'cathedral' OR sr.chapel_id IS NULL OR sr.chapel_id = 0)";
            } elseif ($filters['scope'] === 'chapel') {
                if (!empty($filters['chapel_id'])) {
                    $where[] = 'sr.chapel_id = ?';
                    $params[] = (int)$filters['chapel_id'];
                    $types .= 'i';
                } else {
                    $where[] = "(sr.scope = 'chapel' OR (sr.chapel_id IS NOT NULL AND sr.chapel_id > 0))";
                }
            } else {
                $where[] = 'sr.scope = ?';
                $params[] = $filters['scope'];
                $types .= 's';
            }
        } elseif (!empty($filters['chapel_id'])) {
            $where[] = 'sr.chapel_id = ?';
            $params[] = (int)$filters['chapel_id'];
            $types .= 'i';
        }

        if (!empty($filters['record_type'])) {
            $where[] = 'sr.record_type = ?';
            $params[] = $filters['record_type'];
            $types .= 's';
        }

        if (!empty($filters['search'])) {
            $q        = '%' . $filters['search'] . '%';
            $where[]  = '(sr.person_name LIKE ? OR sr.reference_id LIKE ?
                          OR sr.officiating_priest LIKE ? OR sr.groom_name LIKE ?
                          OR sr.bride_name LIKE ? OR sr.deceased_name LIKE ?
                          OR sr.child_name LIKE ? OR sr.confirmation_place_of_baptism LIKE ?)';
            for ($i = 0; $i < 8; $i++) { $params[] = $q; $types .= 's'; }
        }

        if (!empty($filters['officiating_priest'])) {
            $where[] = 'sr.officiating_priest LIKE ?';
            $params[] = '%' . $filters['officiating_priest'] . '%';
            $types .= 's';
        }

        if (!empty($filters['timeframe'])) {
            switch ($filters['timeframe']) {
                case 'recently_added':
                    $where[] = 'sr.date_added >= DATE_SUB(NOW(), INTERVAL 30 DAY)';
                    break;
                case 'recently_added_7':
                    $where[] = 'sr.date_added >= DATE_SUB(NOW(), INTERVAL 7 DAY)';
                    break;
                case 'this_week':
                    $where[] = 'sr.event_date >= ? AND sr.event_date <= ?';
                    $params[] = date('Y-m-d', strtotime('monday this week'));
                    $params[] = date('Y-m-d', strtotime('sunday this week'));
                    $types .= 'ss';
                    break;
                case 'next_week':
                    $where[] = 'sr.event_date >= ? AND sr.event_date <= ?';
                    $params[] = date('Y-m-d', strtotime('monday next week'));
                    $params[] = date('Y-m-d', strtotime('sunday next week'));
                    $types .= 'ss';
                    break;
                case 'this_month':
                    $where[] = 'sr.event_date >= ? AND sr.event_date <= ?';
                    $params[] = date('Y-m-01');
                    $params[] = date('Y-m-t');
                    $types .= 'ss';
                    break;
                case 'this_year':
                    $where[] = 'YEAR(sr.event_date) = YEAR(NOW())';
                    break;
            }
        }

        if (!empty($filters['date_from'])) {
            $where[] = 'sr.event_date >= ?';
            $params[] = $filters['date_from'];
            $types .= 's';
        }

        if (!empty($filters['date_to'])) {
            $where[] = 'sr.event_date <= ?';
            $params[] = $filters['date_to'];
            $types .= 's';
        }

        if (!empty($filters['encoded_by'])) {
            $where[] = 'sr.encoded_by = ?';
            $params[] = (int)$filters['encoded_by'];
            $types .= 'i';
        }

        $whereSQL = 'WHERE ' . implode(' AND ', $where);

        // ── COUNT ──────────────────────────────────────────────────
        $countSQL = "SELECT COUNT(*) AS total
                     FROM sacramental_records sr
                     LEFT JOIN chapels c ON c.chapel_id = sr.chapel_id
                     $whereSQL";

        $total = 0;
        if ($cstmt = $this->conn->prepare($countSQL)) {
            if ($params) $cstmt->bind_param($types, ...$params);
            $cstmt->execute();
            $total = $cstmt->get_result()->fetch_assoc()['total'] ?? 0;
            $cstmt->close();
        }

        // ── FETCH ──────────────────────────────────────────────────
        $offset  = ($page - 1) * $per_page;
        $sortSQL = 'ORDER BY sr.event_date DESC, sr.record_id DESC';
        if (!empty($filters['sort'])) {
            switch ($filters['sort']) {
                case 'date_asc':      $sortSQL = 'ORDER BY sr.event_date ASC, sr.record_id ASC'; break;
                case 'date_desc':     $sortSQL = 'ORDER BY sr.event_date DESC, sr.record_id DESC'; break;
                case 'modified_desc': $sortSQL = 'ORDER BY sr.updated_at DESC'; break;
                case 'recently_added': $sortSQL = 'ORDER BY sr.date_added DESC'; break;
            }
        }

        $records = [];
        $fparams = array_merge($params, [$per_page, $offset]);
        $ftypes  = $types . 'ii';

        $fetchSQL = "SELECT sr.*,
                       c.chapel_name,
                       u_enc.user AS encoded_by_name,
                       u_upd.user AS updated_by_name
                     FROM sacramental_records sr
                     LEFT JOIN chapels c    ON c.chapel_id = sr.chapel_id
                     LEFT JOIN users u_enc  ON u_enc.id    = sr.encoded_by
                     LEFT JOIN users u_upd  ON u_upd.id    = sr.updated_by
                     $whereSQL";

        if ($fstmt = $this->conn->prepare($fetchSQL . " $sortSQL LIMIT ? OFFSET ?")) {
            if ($fparams) $fstmt->bind_param($ftypes, ...$fparams);
            $fstmt->execute();
            $res = $fstmt->get_result();
            while ($row = $res->fetch_assoc()) $records[] = $row;
            $fstmt->close();
        }

        return [
            'records' => $records,
            'total'   => (int)$total,
            'pages'   => (int)ceil($total / $per_page),
            'page'    => $page,
            'sort'    => $filters['sort'] ?? 'date_desc'
        ];
    }

    /**
     * Fetch single record with full detail.
     */
    public function getById(int $id): ?array
    {
        $sql = "SELECT sr.*,
                  c.chapel_name,
                  u_enc.user AS encoded_by_name,
                  u_upd.user AS updated_by_name
                FROM sacramental_records sr
                LEFT JOIN chapels c    ON c.chapel_id = sr.chapel_id
                LEFT JOIN users u_enc  ON u_enc.id    = sr.encoded_by
                LEFT JOIN users u_upd  ON u_upd.id    = sr.updated_by
                WHERE sr.record_id = ? AND sr.is_archived = 0";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $record = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $record ?: null;
    }

    public function getByScope(string $scope, int $limit = 9999): array
    {
        $res = $this->search(['scope' => $scope], 1, $limit);
        return $res['records'] ?? [];
    }

    public function exportByScope(string $scope, ?int $chapelId = null): array
    {
        return $this->reportingService->exportByScope($scope, $chapelId);
    }

    public function getCount($chapelId = null, ?string $scope = null): int
    {
        $where = "WHERE is_archived=0";
        if ($chapelId !== null) {
            $isCathedral = ($chapelId === 'cathedral' || (is_numeric($chapelId) && (int)$chapelId === 0));
            if ($isCathedral) {
                $where .= " AND (scope = 'cathedral' OR chapel_id IS NULL OR chapel_id = 0)";
            } else {
                $where .= " AND chapel_id = " . intval($chapelId);
            }
        } elseif ($scope === 'cathedral') {
            $where .= " AND (scope = 'cathedral' OR chapel_id IS NULL OR chapel_id = 0)";
        } elseif ($scope === 'chapel') {
            $where .= " AND (scope = 'chapel' OR (chapel_id IS NOT NULL AND chapel_id > 0))";
        }
        $sql = "SELECT COUNT(*) AS total FROM sacramental_records $where";
        $res = $this->conn->query($sql);
        return (int)($res->fetch_assoc()['total'] ?? 0);
    }

    public function getChartTypes(?int $chapelId = null): array
    {
        return $this->reportingService->getChartTypes($chapelId);
    }

    // ════════════════════════════════════════════════════════════════
    // CREATE
    // ════════════════════════════════════════════════════════════════

    /**
     * Create a new sacramental record.
     * Generates reference_id automatically.
     * Returns new record_id or false.
     */
    public function create(array $data, int $encoded_by): int|false
    {
        $this->conn->begin_transaction();
        try {
            $d = $this->sanitizeData($data);
            $ref = $this->generateReferenceId($d['record_type'] ?? 'Baptism');
            $id = $this->createClean($d, $ref, $encoded_by);
            if (!$id) throw new Exception("Failed to save record.");
            
            $this->logAudit($id, 'created', $encoded_by, null, $d);
            $this->conn->commit();
            return $id;
        } catch (Exception $e) {
            $this->conn->rollback();
            return false;
        }
    }

    /** Clean version of create using individual binds via execute array (PHP 8.1+) */
    public function createClean(array $d, string $ref, int $encoded_by): int|false
    {
        $sql = "INSERT INTO sacramental_records
                (scope, sacrament_type, chapel_id, record_type,
                 person_name, event_date, book_number, page_number, encoded_by,
                 officiating_priest, 
                 child_name, date_of_birth, place_of_birth,
                 father_name, mother_name, godfather, godmother,
                 baptism_parents_residence, baptism_legitimacy,
                 groom_name, bride_name, date_of_marriage, place_of_marriage,
                 witness_1, witness_2, 
                 wedding_groom_parents, wedding_bride_parents,
                 deceased_name, date_of_death, age_at_death,
                 date_of_funeral, place_of_burial, 
                 funeral_cause_of_death, funeral_occupation, funeral_marital_status,
                 confirmation_place_of_baptism,
                 reference_id, notes)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";

        $stmt = $this->conn->prepare($sql);
        if (!$stmt) return false;

        // Note: No future-date block here. Staff legitimately pre-register upcoming
        // events (e.g. scheduled weddings, funeral masses). The date input is trusted.

        $age = !empty($d['age_at_death']) ? (int)$d['age_at_death'] : null;
        $encByVal = ($encoded_by > 0) ? $encoded_by : null;

        $vals = [
            $d['scope'],             $d['sacrament_type'],   $d['chapel_id'],
            $d['record_type'],       $d['person_name'],      $d['event_date'],
            $d['book_number'],       $d['page_number'],      $encByVal,
            $d['officiating_priest'],
            // baptism
            $d['child_name'],        $d['date_of_birth'],    $d['place_of_birth'],
            $d['father_name'],       $d['mother_name'],      $d['godfather'],
            $d['godmother'],         $d['baptism_parents_residence'], $d['baptism_legitimacy'],
            // wedding
            $d['groom_name'],        $d['bride_name'],       $d['date_of_marriage'],
            $d['place_of_marriage'], $d['witness_1'],        $d['witness_2'],
            $d['wedding_groom_parents'], $d['wedding_bride_parents'],
            // funeral
            $d['deceased_name'],     $d['date_of_death'],    $age,
            $d['date_of_funeral'],   $d['place_of_burial'],
            $d['funeral_cause_of_death'], $d['funeral_occupation'], $d['funeral_marital_status'],
            // confirmation
            $d['confirmation_place_of_baptism'],
            // meta
            $ref,                    $d['notes']
        ];

        // 38 elements: 10 + 9 + 8 + 8 + 3 = 38
        $types = 'ssissssis' . 's' .    // 10
                 'sssssssss' .          // 9 (baptism)
                 'ssssssss' .           // 8 (wedding)
                 'ssisssss' .           // 8 (funeral incl age:i)
                 'sss';                 // 3 (confirm_pobap, ref, notes)

        $stmt->bind_param($types, ...$vals);
        $ok = $stmt->execute();
        $newId = $ok ? (int)$this->conn->insert_id : false;
        $stmt->close();

        return $newId;
    }

    // ════════════════════════════════════════════════════════════════
    // UPDATE
    // ════════════════════════════════════════════════════════════════

    public function update(int $id, array $data, int $updated_by): bool
    {
        $before = $this->getById($id);

        $d = $this->sanitizeData($data);
        $sql = "UPDATE sacramental_records SET
                  scope=?, sacrament_type=?, chapel_id=?, record_type=?, person_name=?, event_date=?,
                  book_number=?, page_number=?, officiating_priest=?,
                  child_name=?, date_of_birth=?, place_of_birth=?,
                  father_name=?, mother_name=?, godfather=?, godmother=?,
                  baptism_parents_residence=?, baptism_legitimacy=?,
                  groom_name=?, bride_name=?, date_of_marriage=?,
                  place_of_marriage=?, witness_1=?, witness_2=?,
                  wedding_groom_parents=?, wedding_bride_parents=?,
                  deceased_name=?, date_of_death=?, age_at_death=?,
                  date_of_funeral=?, place_of_burial=?, 
                  funeral_cause_of_death=?, funeral_occupation=?, funeral_marital_status=?,
                  confirmation_place_of_baptism=?,
                  notes=?, updated_by=?, updated_at=NOW()
                WHERE record_id=? AND is_archived=0";

        $stmt = $this->conn->prepare($sql);
        if (!$stmt) return false;

        // Note: We intentionally do NOT block future dates on UPDATE.
        // Records may have been legitimately saved with future dates (e.g., a pre-scheduled
        // wedding or funeral mass). Blocking edits would prevent correcting those records.

        $age = $d['age_at_death'] ? (int)$d['age_at_death'] : null;
        $updByVal = ($updated_by > 0) ? $updated_by : null;

        $vals = [
            $d['scope'],                   $d['sacrament_type'],          $d['chapel_id'],               $d['record_type'],    
            $d['person_name'],             $d['event_date'],               $d['book_number'],    
            $d['page_number'],             $d['officiating_priest'],
            // baptism
            $d['child_name'],               $d['date_of_birth'],  $d['place_of_birth'],
            $d['father_name'],              $d['mother_name'],    $d['godfather'],
            $d['godmother'],                $d['baptism_parents_residence'], $d['baptism_legitimacy'],
            // wedding
            $d['groom_name'],               $d['bride_name'],     $d['date_of_marriage'],
            $d['place_of_marriage'],        $d['witness_1'],      $d['witness_2'],
            $d['wedding_groom_parents'],    $d['wedding_bride_parents'],
            // funeral
            $d['deceased_name'],            $d['date_of_death'],  $age,
            $d['date_of_funeral'],          $d['place_of_burial'],
            $d['funeral_cause_of_death'],   $d['funeral_occupation'], $d['funeral_marital_status'],
            // confirmation
            $d['confirmation_place_of_baptism'],
            // meta
            $d['notes'],                    $updByVal,
            $id
        ];

        $types = 'ssissssss' . 'sssssssss' . 'ssssssss' . 'ssisssss' . 'ssii';
        $stmt->bind_param($types, ...$vals);
        $ok = $stmt->execute();
        $stmt->close();

        if ($ok) {
            $after = $this->getById($id);
            $this->logAudit($id, 'updated', $updated_by, $before, $after);
        }

        return $ok;
    }

    // ── SHIMS for DashboardController (Old specific methods) ────────

    public function createBaptism($scope,$rtype,$person,$edate,$chapelId,$book,$page,$encBy,$refId,
                                 $child_name,$dob,$pob,$father,$mother,$godf,$godm,$priest,
                                 $res=null,$legit=null) {
        $data = [
            'scope'=>$scope,'record_type'=>$rtype,'person_name'=>$person,'event_date'=>$edate,'chapel_id'=>$chapelId,
            'book_number'=>$book,'page_number'=>$page,'officiating_priest'=>$priest,
            'child_name'=>$child_name,'date_of_birth'=>$dob,'place_of_birth'=>$pob,
            'father_name'=>$father,'mother_name'=>$mother,'godfather'=>$godf,'godmother'=>$godm,
            'baptism_parents_residence'=>$res, 'baptism_legitimacy'=>$legit
        ];
        return $this->createClean($this->sanitizeData($data), $refId, $encBy);
    }

    public function createWedding($scope,$rtype,$person,$edate,$chapelId,$book,$page,$encBy,$refId,
                                 $groom,$bride,$dom,$pom,$w1,$w2,$priest,
                                 $gp=null,$bp=null) {
        $data = [
            'scope'=>$scope,'record_type'=>$rtype,'person_name'=>$person,'event_date'=>$edate,'chapel_id'=>$chapelId,
            'book_number'=>$book,'page_number'=>$page,'officiating_priest'=>$priest,
            'groom_name'=>$groom,'bride_name'=>$bride,'date_of_marriage'=>$dom,
            'place_of_marriage'=>$pom,'witness_1'=>$w1,'witness_2'=>$w2,
            'wedding_groom_parents'=>$gp, 'wedding_bride_parents'=>$bp
        ];
        return $this->createClean($this->sanitizeData($data), $refId, $encBy);
    }

    public function createFuneral($scope,$rtype,$person,$edate,$chapelId,$book,$page,$encBy,$refId,
                                 $deceased,$dod,$age,$dof,$poburial,$priest,
                                 $cause=null,$occ=null,$ms=null) {
        $data = [
            'scope'=>$scope,'record_type'=>$rtype,'person_name'=>$person,'event_date'=>$edate,'chapel_id'=>$chapelId,
            'book_number'=>$book,'page_number'=>$page,'officiating_priest'=>$priest,
            'deceased_name'=>$deceased,'date_of_death'=>$dod,'age_at_death'=>$age,
            'date_of_funeral'=>$dof,'place_of_burial'=>$poburial,
            'funeral_cause_of_death'=>$cause, 'funeral_occupation'=>$occ, 'funeral_marital_status'=>$ms
        ];
        return $this->createClean($this->sanitizeData($data), $refId, $encBy);
    }

    public function updateBaptism($id,$scope,$person,$edate,$chapelId,$book,$page,$priest,
                                 $child_name,$dob,$pob,$father,$mother,$godf,$godm,
                                 $res=null,$legit=null) {
        $data = [
            'scope'=>$scope,'record_type'=>'Baptism','person_name'=>$person,'event_date'=>$edate,'chapel_id'=>$chapelId,
            'book_number'=>$book,'page_number'=>$page,'officiating_priest'=>$priest,
            'child_name'=>$child_name,'date_of_birth'=>$dob,'place_of_birth'=>$pob,
            'father_name'=>$father,'mother_name'=>$mother,'godfather'=>$godf,'godmother'=>$godm,
            'baptism_parents_residence'=>$res, 'baptism_legitimacy'=>$legit
        ];
        $uid = $_SESSION['user_id'] ?? 0;
        return $this->update((int)$id, $data, (int)$uid);
    }

    public function updateWedding($id,$scope,$person,$edate,$chapelId,$book,$page,$priest,
                                 $groom,$bride,$dom,$pom,$w1,$w2,$gp=null,$bp=null) {
        $data = [
            'scope'=>$scope,'record_type'=>'Wedding','person_name'=>$person,'event_date'=>$edate,'chapel_id'=>$chapelId,
            'book_number'=>$book,'page_number'=>$page,'officiating_priest'=>$priest,
            'groom_name'=>$groom,'bride_name'=>$bride,'date_of_marriage'=>$dom,
            'place_of_marriage'=>$pom,'witness_1'=>$w1,'witness_2'=>$w2,
            'wedding_groom_parents'=>$gp, 'wedding_bride_parents'=>$bp
        ];
        $uid = $_SESSION['user_id'] ?? 0;
        return $this->update((int)$id, $data, (int)$uid);
    }

    public function updateFuneral($id,$scope,$person,$edate,$chapelId,$book,$page,$priest,
                                 $deceased,$dod,$age,$dof,$poburial,$cause=null,$occ=null,$ms=null) {
        $data = [
            'scope'=>$scope,'record_type'=>'Funeral','person_name'=>$person,'event_date'=>$edate,'chapel_id'=>$chapelId,
            'book_number'=>$book,'page_number'=>$page,'officiating_priest'=>$priest,
            'deceased_name'=>$deceased,'date_of_death'=>$dod,'age_at_death'=>$age,
            'date_of_funeral'=>$dof,'place_of_burial'=>$poburial,
            'funeral_cause_of_death'=>$cause, 'funeral_occupation'=>$occ, 'funeral_marital_status'=>$ms
        ];
        $uid = $_SESSION['user_id'] ?? 0;
        return $this->update((int)$id, $data, (int)$uid);
    }

    public function createConfirmation($scope,$rtype,$person,$edate,$chapelId,$book,$page,$encBy,$refId,
                                     $pobap,$godf,$godm,$priest) {
        $data = [
            'scope'=>$scope,'record_type'=>$rtype,'person_name'=>$person,'event_date'=>$edate,'chapel_id'=>$chapelId,
            'book_number'=>$book,'page_number'=>$page,'officiating_priest'=>$priest,
            'confirmation_place_of_baptism'=>$pobap,'godfather'=>$godf,'godmother'=>$godm
        ];
        return $this->createClean($this->sanitizeData($data), $refId, $encBy);
    }

    public function updateConfirmation($id,$scope,$person,$edate,$chapelId,$book,$page,$priest,
                                     $pobap,$godf,$godm) {
        $data = [
            'scope'=>$scope,'record_type'=>'Confirmation','person_name'=>$person,'event_date'=>$edate,'chapel_id'=>$chapelId,
            'book_number'=>$book,'page_number'=>$page,'officiating_priest'=>$priest,
            'confirmation_place_of_baptism'=>$pobap,'godfather'=>$godf,'godmother'=>$godm
        ];
        $uid = $_SESSION['user_id'] ?? 0;
        return $this->update((int)$id, $data, (int)$uid);
    }

    public function createCommunion($scope,$rtype,$person,$edate,$chapelId,$book,$page,$encBy,$refId,$priest) {
        $data = [
            'scope'=>$scope,'record_type'=>$rtype,'person_name'=>$person,'event_date'=>$edate,'chapel_id'=>$chapelId,
            'book_number'=>$book,'page_number'=>$page,'officiating_priest'=>$priest
        ];
        return $this->createClean($this->sanitizeData($data), $refId, $encBy);
    }

    public function updateCommunion($id,$scope,$person,$edate,$chapelId,$book,$page,$priest) {
        $data = [
            'scope'=>$scope,'record_type'=>'Communion','person_name'=>$person,'event_date'=>$edate,'chapel_id'=>$chapelId,
            'book_number'=>$book,'page_number'=>$page,'officiating_priest'=>$priest
        ];
        $uid = $_SESSION['user_id'] ?? 0;
        return $this->update((int)$id, $data, (int)$uid);
    }

    // ════════════════════════════════════════════════════════════════
    // SOFT DELETE (archive)
    // ════════════════════════════════════════════════════════════════

    public function archive(int $id, int $by): bool
    {
        $before = $this->getById($id);
        $stmt = $this->conn->prepare(
            "UPDATE sacramental_records SET is_archived=1, updated_by=?, updated_at=NOW() WHERE record_id=?"
        );
        $stmt->bind_param('ii', $by, $id);
        $ok = $stmt->execute();
        $stmt->close();
        if ($ok && $before) $this->logAudit($id, 'deleted', $by, $before, null);
        return $ok;
    }

    public function delete(int $id, int $by = 1): bool
    {
        return $this->archive($id, $by);
    }


    // ════════════════════════════════════════════════════════════════
    // CERTIFICATE ISSUANCE
    // ════════════════════════════════════════════════════════════════

    /**
     * Log a certificate print and return the issuance ID.
     */
    public function logCertificateIssuance(int $record_id, string $issued_to, string $purpose, int $issued_by): int|false
    {
        return $this->certificateService->logCertificateIssuance(
            $record_id,
            $issued_to,
            $purpose,
            $issued_by,
            fn(int $id, string $action, int $by, $old, $new) => $this->logAudit($id, $action, $by, $old, $new)
        );
    }

    public function getCertificateHistory(int $record_id): array
    {
        return $this->certificateService->getCertificateHistory($record_id);
    }

    // ════════════════════════════════════════════════════════════════
    // STATISTICS
    // ════════════════════════════════════════════════════════════════

    public function getStats($chapel_id = null, ?string $scope = null): array
    {
        return $this->reportingService->getStats($chapel_id, $scope);
    }

    public function getMonthlyBreakdown(int $months = 12, ?int $chapelId = null): array
    {
        return $this->reportingService->getMonthlyBreakdown($months, $chapelId);
    }

    // ════════════════════════════════════════════════════════════════
    // DUPLICATE CHECK
    // ════════════════════════════════════════════════════════════════

    /**
     * Check for likely duplicates before creating a record.
     */
    public function checkDuplicate(string $type, array $data): ?array
    {
        return $this->reportingService->checkDuplicate($type, $data);
    }

    // ════════════════════════════════════════════════════════════════
    // PRIVATE HELPERS
    // ════════════════════════════════════════════════════════════════

    public function generateReferenceId(string $type): string
    {
        $type = ($type === 'First Communion') ? 'Communion' : $type;
        $prefix = self::PREFIX[$type] ?? 'REC';
        $year   = date('Y');

        // Locking for Uniqueness: Use FOR UPDATE to prevent race conditions during peak entry times
        $sql  = "SELECT COUNT(*) AS cnt FROM sacramental_records WHERE record_type=? AND YEAR(date_added)=? FOR UPDATE";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('si', $type, $year);
        $stmt->execute();
        $cnt  = ($stmt->get_result()->fetch_assoc()['cnt'] ?? 0) + 1;
        $stmt->close();

        return sprintf('%s-%s-%05d', $prefix, $year, $cnt);
    }

    private function generateCertNo(int $record_id): string
    {
        $sql  = "SELECT COUNT(*)+1 AS nxt FROM certificate_issuances WHERE record_id=?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('i', $record_id);
        $stmt->execute();
        $nxt  = $stmt->get_result()->fetch_assoc()['nxt'] ?? 1;
        $stmt->close();
        return 'CERT-' . $record_id . '-' . sprintf('%03d', $nxt);
    }

    private function sanitizeData(array $data): array
    {
        $defaults = [
            'scope' => 'cathedral', 'sacrament_type' => 'Other',
            'chapel_id' => null, 'record_type' => '', 'person_name' => '',
            'event_date' => '', 'book_number' => '', 'page_number' => '',
            'officiating_priest' => '',
            // Baptism
            'child_name' => null, 'date_of_birth' => null, 'place_of_birth' => null,
            'father_name' => null, 'mother_name' => null,
            'godfather' => null, 'godmother' => null,
            'baptism_parents_residence' => null, 'baptism_legitimacy' => null,
            // Wedding
            'groom_name' => null, 'bride_name' => null, 'date_of_marriage' => null,
            'place_of_marriage' => null, 'witness_1' => null, 'witness_2' => null,
            'wedding_groom_parents' => null, 'wedding_bride_parents' => null,
            // Funeral
            'deceased_name' => null, 'date_of_death' => null, 'age_at_death' => null,
            'date_of_funeral' => null, 'place_of_burial' => null,
            'funeral_cause_of_death' => null, 'funeral_occupation' => null, 'funeral_marital_status' => null,
            // Confirmation
            'confirmation_place_of_baptism' => null,
            // Meta
            'notes' => null,
        ];

        $merged = array_merge($defaults, $data);

        if (empty($merged['chapel_id']) || (int)$merged['chapel_id'] <= 0) {
            $merged['chapel_id'] = null;
        } else {
            $merged['chapel_id'] = (int) $merged['chapel_id'];
        }

        // Normalize record_type
        if (!empty($merged['record_type'])) {
            if ($merged['record_type'] === 'First Communion') {
                $merged['record_type'] = 'Communion';
            }
        }
        
        // Ensure sacrament_type is set for DB NOT NULL constraint
        if (empty($merged['sacrament_type']) || $merged['sacrament_type'] === 'Other') {
            $merged['sacrament_type'] = $merged['record_type'] ?: 'Other';
        }

        // Sanitize strings
        foreach ($merged as $k => $v) {
            if (is_string($v)) {
                $merged[$k] = trim(strip_tags($v)) ?: null;
            }
        }

        return $merged;
    }

    private function logAudit(int $record_id, string $action, int $by, $old, $new): void
    {
        $old_json = $old ? json_encode($old) : null;
        $new_json = $new ? json_encode($new)  : null;
        $ip       = $_SERVER['REMOTE_ADDR'] ?? null;
        $ua       = $_SERVER['HTTP_USER_AGENT'] ?? null;
        $byVal    = ($by > 0) ? $by : null;

        $sql = "INSERT INTO sacramental_audit_log
                  (record_id, action, performed_by, old_snapshot, new_snapshot, ip_address, user_agent)
                VALUES (?,?,?,?,?,?,?)";
        $stmt = $this->conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param('isissss',  $record_id, $action, $byVal, $old_json, $new_json, $ip, $ua);
            $stmt->execute();
            $stmt->close();
        }
    }

    public function getBaptismFuneralRatio(): array
    {
        return $this->reportingService->getBaptismFuneralRatio();
    }

    public function getRetentionStats(): array
    {
        return $this->reportingService->getRetentionStats();
    }
}
