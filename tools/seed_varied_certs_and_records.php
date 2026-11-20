<?php
declare(strict_types=1);

/**
 * Idempotent CLI Seeder for Sacramental Records, Certificates, and Certificate Requests
 * Ensures Main Cathedral and each active branch Chapel have at least 10 varied records
 * per Sacrament Type (Baptism, Wedding, Funeral, Confirmation, Communion), plus
 * issued certificates and certificate request events.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Forbidden: Seeder utility must be executed via CLI.');
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/models/SacramentalRecord.php';
require_once __DIR__ . '/../app/services/SacramentalCertificateService.php';
require_once __DIR__ . '/../app/models/Event.php';
require_once __DIR__ . '/../app/models/Donation.php';

$safeBoot = in_array('--safe-boot', $argv ?? [], true);
if (!isset($conn) || !($conn instanceof mysqli) || $conn->connect_error) {
    echo "[SEED SKIP] Database connection unavailable.\n";
    exit($safeBoot ? 0 : 1);
}

$sacModel = new SacramentalRecord($conn);
$certSvc  = new SacramentalCertificateService($conn);
$evModel  = new Event($conn);
$donModel = new Donation($conn);

// Dynamic user lookups so FKs never break across local vs Render DBs
$adminRow = $conn->query("SELECT id FROM users WHERE role IN ('Admin','Cathedral Admin') ORDER BY id ASC LIMIT 1")->fetch_assoc();
if (!$adminRow) {
    $adminRow = $conn->query("SELECT id FROM users ORDER BY id ASC LIMIT 1")->fetch_assoc();
}
if (!$adminRow) {
    echo "[SEED SKIP] No users found in database.\n";
    exit($safeBoot ? 0 : 1);
}
$encoderId = (int)$adminRow['id'];

$memRow = $conn->query("SELECT id FROM users WHERE role = 'Member' ORDER BY id ASC LIMIT 1")->fetch_assoc();
$reqUserId = $memRow ? (int)$memRow['id'] : $encoderId;

$priestsByChapel = [];
$fallbackPriestId = null;
$fallbackPriestName = 'Rev. Fr. Parish Priest';
$pRes = $conn->query("SELECT id, full_name, `user`, chapel_id FROM users WHERE role IN ('Priest','Pastor') ORDER BY id ASC");
if ($pRes) {
    while ($p = $pRes->fetch_assoc()) {
        $pid = (int)$p['id'];
        $pname = trim((string)($p['full_name'] ?: $p['user'] ?: 'Parish Priest'));
        if (!preg_match('/^(Fr\.|Rev\.|Most Rev\.)/i', $pname)) {
            $pname = 'Rev. Fr. ' . $pname;
        }
        $pcid = (int)($p['chapel_id'] ?? 0);
        if (!isset($priestsByChapel[$pcid])) {
            $priestsByChapel[$pcid] = ['id' => $pid, 'name' => $pname];
        }
        if ($fallbackPriestId === null) {
            $fallbackPriestId = $pid;
            $fallbackPriestName = $pname;
        }
    }
}

// Build venues dynamically (Main Cathedral + all Chapels in DB)
$venues = [
    [
        'scope' => 'cathedral',
        'chapel_id' => 0,
        'name' => 'Cathedral of St. Michael the Archangel',
        'barangay' => 'Digman, Bacoor City, Cavite',
        'priest' => $priestsByChapel[0]['name'] ?? $fallbackPriestName,
        'priest_id' => $priestsByChapel[0]['id'] ?? $fallbackPriestId,
        'code' => 'CAT',
    ],
];
$cRes = $conn->query("SELECT chapel_id, chapel_name, location FROM chapels ORDER BY chapel_id ASC");
if ($cRes) {
    while ($c = $cRes->fetch_assoc()) {
        $cid = (int)$c['chapel_id'];
        $venues[] = [
            'scope' => 'chapel',
            'chapel_id' => $cid,
            'name' => (string)$c['chapel_name'],
            'barangay' => (string)($c['location'] ?: 'Bacoor City, Cavite'),
            'priest' => $priestsByChapel[$cid]['name'] ?? $fallbackPriestName,
            'priest_id' => $priestsByChapel[$cid]['id'] ?? $fallbackPriestId,
            'code' => 'CH' . $cid,
        ];
    }
}

$firstMale   = ['Mateo', 'Gabriel', 'Rafael', 'Nathaniel', 'Lucas', 'Lorenzo', 'Sebastian', 'Elijah', 'Julian', 'Joaquin', 'Miguel', 'Enzo', 'Dominic', 'Adrian', 'Christian', 'Marcus', 'Paolo', 'Carlo', 'Emmanuel', 'Francis', 'Andres', 'Jerome', 'Patrick', 'Cesar', 'Benedict'];
$firstFemale = ['Sofia', 'Isabella', 'Camille', 'Bianca', 'Gabriela', 'Teresita', 'Clarissa', 'Angelica', 'Mikaela', 'Carmela', 'Beatriz', 'Marielle', 'Andrea', 'Nicole', 'Kristine', 'Patricia', 'Lorraine', 'Vanessa', 'Hannah', 'Denise', 'Clarisse', 'Valerie', 'Marisol', 'Katrina', 'Noemi'];
$surnames    = ['Villanueva', 'Mendoza', 'Bautista', 'Delos Santos', 'Ramos', 'Aquino', 'Fernandez', 'Navarro', 'Castillo', 'Panganiban', 'Tolentino', 'Mercado', 'Salazar', 'Evangelista', 'Domingo', 'Soriano', 'Pangilinan', 'Magbanua', 'Calderon', 'Buenaventura', 'Alvarez', 'Vergara', 'Legaspi', 'Rivera', 'Delgado'];
$midInit     = ['A.', 'B.', 'C.', 'D.', 'E.', 'G.', 'L.', 'M.', 'P.', 'R.', 'S.', 'T.', 'V.'];
$barangays   = ['Brgy. Digman', 'Brgy. Tabing Ilog', 'Brgy. Habay I', 'Brgy. Habay II', 'Brgy. Dulong Bayan', 'Brgy. Mabolo', 'Brgy. Panapaan IV', 'Brgy. Zapote V', 'Brgy. Niog III', 'Brgy. Molino II'];
$filiations  = ['Kasal', 'Kasal', 'Solo', 'Civil', 'Kasal'];
$nationalities = ['Filipino', 'Filipino', 'Filipino', 'Filipino-American', 'Filipino'];
$causesDeath = ['Natural Causes / Old Age', 'Cardiorespiratory Arrest', 'Pneumonia', 'Hypertensive Cardiovascular Disease', 'Chronic Renal Failure', 'Acute Myocardial Infarction', 'Stroke / Cerebrovascular Accident', 'Sepsis Secondary to Infection'];
$cemeteries  = ['Bacoor Municipal Cemetery', 'Heavenly Peace Memorial Park, Bacoor', 'Providence Memorial Park, Dasmariñas', 'Manila South Cemetery', 'St. Michael Memorial Gardens, Cavite'];
$maritalSts  = ['Married', 'Widowed', 'Single', 'Married', 'Widower'];
$occupations = ['Retired Teacher', 'Barangay Elder', 'Master Carpenter', 'Market Vendor', 'Retired Seamstress', 'Fisherman', 'Retired Nurse', 'Parish Sacristan', 'Tricycle Operator', 'Small Business Owner'];
$purposes    = ['School / Enrollment', 'Passport / DFA Requirement', 'Marriage Canonical Requirement', 'Employment Verification', 'SSS / PhilHealth Benefit Claim', 'Personal / Family Records', 'Parish Transfer Record'];
$certFees    = [200.00, 200.00, 250.00, 300.00, 200.00];
$reqStatuses = ['Pending', 'Confirmed', 'Completed', 'Confirmed', 'Completed', 'Pending', 'Completed', 'Cancelled', 'Confirmed', 'Completed'];

function countVenueRecordsByType(mysqli $conn, int $chapelId, string $type): int
{
    if ($chapelId === 0) {
        $stmt = $conn->prepare("SELECT COUNT(*) c FROM sacramental_records WHERE is_archived=0 AND record_type=? AND (chapel_id IS NULL OR chapel_id = 0)");
        $stmt->bind_param('s', $type);
    } else {
        $stmt = $conn->prepare("SELECT COUNT(*) c FROM sacramental_records WHERE is_archived=0 AND record_type=? AND chapel_id=?");
        $stmt->bind_param('si', $type, $chapelId);
    }
    $stmt->execute();
    $c = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
    $stmt->close();
    return $c;
}

function uniqueSeedName(array $firstPool, array $surnames, array $midInit, int &$cursor, array &$used): string
{
    $tries = 0;
    do {
        $cursor++;
        $f = $firstPool[($cursor * 3 + $tries) % count($firstPool)];
        $m = $midInit[($cursor + $tries * 2) % count($midInit)];
        $s = $surnames[($cursor * 7 + $tries * 5) % count($surnames)];
        $candidate = "{$f} {$m} {$s}";
        $tries++;
    } while (isset($used[$candidate]) && $tries < 200);
    $used[$candidate] = true;
    return $candidate;
}

$usedNames = [];
$resExistingNames = $conn->query("SELECT person_name FROM sacramental_records WHERE person_name IS NOT NULL");
if ($resExistingNames) {
    while ($rn = $resExistingNames->fetch_assoc()) {
        $usedNames[(string)$rn['person_name']] = true;
    }
    $resExistingNames->free();
}

$cursor = 0;
$addedRecords = 0;
$addedIssuances = 0;
$addedCertRequests = 0;

foreach ($venues as $vIdx => $v) {
    $cid = $v['chapel_id'];
    $scope = $v['scope'];
    $venueIssuedTargets = [];

    // 1. Baptism (target >= 10)
    $needBap = max(0, 10 - countVenueRecordsByType($conn, $cid, 'Baptism'));
    for ($i = 0; $i < $needBap; $i++) {
        $isMale = ($i % 2 === 0);
        $full = uniqueSeedName($isMale ? $firstMale : $firstFemale, $surnames, $midInit, $cursor, $usedNames);
        $parts = explode(' ', $full);
        $cFirst = $parts[0];
        $cMid = $parts[1] ?? 'A.';
        $cSur = $parts[2] ?? 'Santos';

        $fFirst = $firstMale[($cursor + 4) % count($firstMale)];
        $mFirst = $firstFemale[($cursor + 7) % count($firstFemale)];
        $mSur   = $surnames[($cursor + 9) % count($surnames)];
        $sp1    = $firstMale[($cursor + 1) % count($firstMale)] . ' ' . $surnames[($cursor + 2) % count($surnames)];
        $sp2    = $firstFemale[($cursor + 3) % count($firstFemale)] . ' ' . $surnames[($cursor + 6) % count($surnames)];

        $year = 2024 + ($i % 3);
        $month = str_pad((string)(1 + (($i * 2 + $vIdx) % 12)), 2, '0', STR_PAD_LEFT);
        $day = str_pad((string)(3 + (($i * 3 + $vIdx) % 24)), 2, '0', STR_PAD_LEFT);
        $eventDate = "{$year}-{$month}-{$day}";
        $dob = date('Y-m-d', strtotime($eventDate . ' -' . (60 + $i * 25) . ' days'));
        $filiation = $filiations[$i % count($filiations)];
        $addr = $barangays[($i + $vIdx) % count($barangays)] . ', Bacoor City, Cavite';

        $meta = [
            'child_first_name' => $cFirst, 'child_middle_name' => $cMid, 'child_surname' => $cSur,
            'gender' => $isMale ? 'Male' : 'Female',
            'nationality' => $nationalities[$i % count($nationalities)],
            'contact_no' => '0917' . str_pad((string)(1000000 + $cursor * 713), 7, '0', STR_PAD_LEFT),
            'address' => $addr,
            'father_first_name' => $fFirst, 'father_mi' => 'R.', 'father_surname' => $cSur, 'father_birth_place' => 'Bacoor, Cavite',
            'mother_first_name' => $mFirst, 'mother_mi' => 'M.', 'mother_surname' => $mSur, 'mother_birth_place' => 'Imus, Cavite',
            'filiation' => $filiation,
            'paternal_lolo' => 'Roberto ' . $cSur, 'paternal_lola' => 'Elena ' . $cSur,
            'maternal_lolo' => 'Arturo ' . $mSur, 'maternal_lola' => 'Luzviminda ' . $mSur,
            'sponsors' => [$sp1, $sp2, 'Ramon ' . $mSur],
            'marginal_notes' => 'Canonical Baptism entered at ' . $v['name'] . '.',
        ];

        $rid = $sacModel->create([
            'scope' => $scope, 'chapel_id' => $cid, 'record_type' => 'Baptism',
            'person_name' => $full, 'child_name' => $full,
            'event_date' => $eventDate, 'date_of_birth' => $dob, 'place_of_birth' => $addr,
            'father_name' => "{$fFirst} R. {$cSur}", 'mother_name' => "{$mFirst} M. {$mSur}",
            'baptism_parents_residence' => $addr, 'baptism_legitimacy' => $filiation,
            'godfather' => $sp1, 'godmother' => $sp2,
            'officiating_priest' => $v['priest'],
            'book_number' => 'BK-BAP-' . $v['code'] . '-' . $year,
            'page_number' => (string)(10 + (($cursor * 3) % 85)),
            'notes' => json_encode($meta, JSON_UNESCAPED_UNICODE),
        ], $encoderId);
        if ($rid) {
            $addedRecords++;
            if ($i < 2) $venueIssuedTargets[] = ['record_id' => $rid, 'recipient' => $full];
        }
    }

    // 2. Wedding (target >= 10)
    $needWed = max(0, 10 - countVenueRecordsByType($conn, $cid, 'Wedding'));
    for ($i = 0; $i < $needWed; $i++) {
        $groom = uniqueSeedName($firstMale, $surnames, $midInit, $cursor, $usedNames);
        $bride = uniqueSeedName($firstFemale, $surnames, $midInit, $cursor, $usedNames);
        $gp = explode(' ', $groom);
        $bp = explode(' ', $bride);
        $gFirst = $gp[0]; $gMid = $gp[1] ?? 'A.'; $gSur = $gp[2] ?? 'Santos';
        $bFirst = $bp[0]; $bMid = $bp[1] ?? 'M.'; $bSur = $bp[2] ?? 'Reyes';

        $year = 2024 + ($i % 3);
        $month = str_pad((string)(2 + (($i * 3 + $vIdx) % 11)), 2, '0', STR_PAD_LEFT);
        $day = str_pad((string)(5 + (($i * 2 + $vIdx) % 22)), 2, '0', STR_PAD_LEFT);
        $eventDate = "{$year}-{$month}-{$day}";
        $w1 = $firstMale[($cursor + 6) % count($firstMale)] . ' ' . $surnames[($cursor + 1) % count($surnames)];
        $w2 = $firstFemale[($cursor + 8) % count($firstFemale)] . ' ' . $surnames[($cursor + 4) % count($surnames)];

        $meta = [
            'groom_first_name' => $gFirst, 'groom_middle_name' => $gMid, 'groom_surname' => $gSur,
            'bride_first_name' => $bFirst, 'bride_middle_name' => $bMid, 'bride_surname' => $bSur,
            'groom_civil_status' => ($i % 4 === 0) ? 'Widower' : 'Single',
            'bride_civil_status' => ($i % 5 === 0) ? 'Widow' : 'Single',
            'marriage_license_no' => "CAV-{$year}-" . str_pad((string)(4100 + $cursor), 5, '0', STR_PAD_LEFT),
            'license_date_issued' => date('Y-m-d', strtotime($eventDate . ' -14 days')),
            'license_place_issued' => 'Bacoor City Civil Registrar',
            'marginal_notes' => 'Banns proclaimed cleanly; canonical matrimony solemnized at ' . $v['name'] . '.',
        ];

        $couple = "{$groom} & {$bride}";
        $usedNames[$couple] = true;
        $rid = $sacModel->create([
            'scope' => $scope, 'chapel_id' => $cid, 'record_type' => 'Wedding',
            'person_name' => $couple, 'groom_name' => $groom, 'bride_name' => $bride,
            'event_date' => $eventDate, 'date_of_marriage' => $eventDate,
            'place_of_marriage' => $v['name'] . ', ' . $v['barangay'],
            'witness_1' => $w1, 'witness_2' => $w2,
            'wedding_groom_parents' => "Ernesto {$gSur} & Rosita {$gSur}",
            'wedding_bride_parents' => "Danilo {$bSur} & Marites {$bSur}",
            'officiating_priest' => $v['priest'],
            'book_number' => 'BK-WED-' . $v['code'] . '-' . $year,
            'page_number' => (string)(10 + (($cursor * 3) % 85)),
            'notes' => json_encode($meta, JSON_UNESCAPED_UNICODE),
        ], $encoderId);
        if ($rid) {
            $addedRecords++;
            if ($i < 2) $venueIssuedTargets[] = ['record_id' => $rid, 'recipient' => $couple];
        }
    }

    // 3. Funeral (target >= 10)
    $needFun = max(0, 10 - countVenueRecordsByType($conn, $cid, 'Funeral'));
    for ($i = 0; $i < $needFun; $i++) {
        $isMale = ($i % 2 === 1);
        $dec = uniqueSeedName($isMale ? $firstMale : $firstFemale, $surnames, $midInit, $cursor, $usedNames);
        $dp = explode(' ', $dec);
        $dSur = $dp[2] ?? 'Ramos';

        $year = 2024 + ($i % 3);
        $month = str_pad((string)(1 + (($i + $vIdx * 2) % 12)), 2, '0', STR_PAD_LEFT);
        $day = str_pad((string)(4 + (($i * 3) % 22)), 2, '0', STR_PAD_LEFT);
        $dod = "{$year}-{$month}-{$day}";
        $dof = date('Y-m-d', strtotime($dod . ' +4 days'));
        $age = 54 + (($i * 4 + $vIdx * 3) % 38);

        $meta = [
            'gender' => $isMale ? 'Male' : 'Female',
            'residence' => $barangays[($i + $vIdx) % count($barangays)] . ', Bacoor City',
            'sacrament_received' => ($i % 2 === 0) ? 'Anointing of the Sick & Viaticum' : 'Final Absolution & Requiem Rites',
            'informant_name' => 'Noel ' . $dSur,
            'marginal_notes' => 'Christian burial rites celebrated at ' . $v['name'] . '.',
        ];

        $rid = $sacModel->create([
            'scope' => $scope, 'chapel_id' => $cid, 'record_type' => 'Funeral',
            'person_name' => $dec, 'deceased_name' => $dec,
            'event_date' => $dof, 'date_of_death' => $dod, 'date_of_funeral' => $dof,
            'age_at_death' => $age,
            'place_of_burial' => $cemeteries[($i + $vIdx) % count($cemeteries)],
            'funeral_cause_of_death' => $causesDeath[($i + $vIdx) % count($causesDeath)],
            'funeral_occupation' => $occupations[($i + $vIdx) % count($occupations)],
            'funeral_marital_status' => $maritalSts[($i + $vIdx) % count($maritalSts)],
            'officiating_priest' => $v['priest'],
            'book_number' => 'BK-FUN-' . $v['code'] . '-' . $year,
            'page_number' => (string)(10 + (($cursor * 3) % 85)),
            'notes' => json_encode($meta, JSON_UNESCAPED_UNICODE),
        ], $encoderId);
        if ($rid) {
            $addedRecords++;
            if ($i < 2) $venueIssuedTargets[] = ['record_id' => $rid, 'recipient' => $dec];
        }
    }

    // 4. Confirmation (target >= 10)
    $needCon = max(0, 10 - countVenueRecordsByType($conn, $cid, 'Confirmation'));
    for ($i = 0; $i < $needCon; $i++) {
        $isMale = ($i % 2 === 0);
        $full = uniqueSeedName($isMale ? $firstMale : $firstFemale, $surnames, $midInit, $cursor, $usedNames);
        $cp = explode(' ', $full);
        $cFirst = $cp[0]; $cMid = $cp[1] ?? 'A.'; $cSur = $cp[2] ?? 'Aquino';

        $fName = $firstMale[($cursor + 3) % count($firstMale)] . ' R. ' . $cSur;
        $mName = $firstFemale[($cursor + 9) % count($firstFemale)] . ' C. ' . $surnames[($cursor + 11) % count($surnames)];
        $sp1   = ($isMale ? 'Jose ' : 'Maria ') . $surnames[($cursor + 12) % count($surnames)];

        $year = 2024 + ($i % 3);
        $month = str_pad((string)(3 + (($i * 2 + $vIdx) % 9)), 2, '0', STR_PAD_LEFT);
        $day = str_pad((string)(6 + (($i * 2) % 20)), 2, '0', STR_PAD_LEFT);
        $eventDate = "{$year}-{$month}-{$day}";
        $dob = date('Y-m-d', strtotime($eventDate . ' -14 years'));
        $dateBap = date('Y-m-d', strtotime($dob . ' +90 days'));

        $meta = [
            'child_first_name' => $cFirst, 'child_middle_name' => $cMid, 'child_surname' => $cSur,
            'confirmation_time' => ($i % 2 === 0) ? '09:00 AM' : '02:30 PM',
            'date_of_baptism' => $dateBap, 'place_of_baptism' => $v['name'],
            'gender' => $isMale ? 'Male' : 'Female', 'nationality' => 'Filipino',
            'address' => $barangays[($i + $vIdx) % count($barangays)] . ', Bacoor City',
            'sponsors' => [$sp1],
            'marginal_notes' => 'Confirmed during Diocesan Pastoral Visitation at ' . $v['name'] . '.',
        ];

        $rid = $sacModel->create([
            'scope' => $scope, 'chapel_id' => $cid, 'record_type' => 'Confirmation',
            'person_name' => $full, 'child_name' => $full,
            'event_date' => $eventDate, 'date_of_birth' => $dob, 'place_of_birth' => 'Bacoor, Cavite',
            'father_name' => $fName, 'mother_name' => $mName, 'godfather' => $sp1,
            'confirmation_place_of_baptism' => $v['name'],
            'officiating_priest' => ($i % 3 === 0) ? 'Most Rev. Bishop Diocesan' : $v['priest'],
            'book_number' => 'BK-CON-' . $v['code'] . '-' . $year,
            'page_number' => (string)(10 + (($cursor * 3) % 85)),
            'notes' => json_encode($meta, JSON_UNESCAPED_UNICODE),
        ], $encoderId);
        if ($rid) {
            $addedRecords++;
            if ($i < 2) $venueIssuedTargets[] = ['record_id' => $rid, 'recipient' => $full];
        }
    }

    // 5. Communion (target >= 10)
    $needCom = max(0, 10 - countVenueRecordsByType($conn, $cid, 'Communion'));
    for ($i = 0; $i < $needCom; $i++) {
        $isMale = ($i % 2 === 1);
        $full = uniqueSeedName($isMale ? $firstMale : $firstFemale, $surnames, $midInit, $cursor, $usedNames);
        $cp = explode(' ', $full);
        $cFirst = $cp[0]; $cMid = $cp[1] ?? 'A.'; $cSur = $cp[2] ?? 'Mendoza';

        $fName = $firstMale[($cursor + 2) % count($firstMale)] . ' L. ' . $cSur;
        $mName = $firstFemale[($cursor + 4) % count($firstFemale)] . ' D. ' . $surnames[($cursor + 15) % count($surnames)];

        $year = 2024 + ($i % 3);
        $month = str_pad((string)(4 + (($i * 2 + $vIdx) % 8)), 2, '0', STR_PAD_LEFT);
        $day = str_pad((string)(7 + (($i * 2) % 20)), 2, '0', STR_PAD_LEFT);
        $eventDate = "{$year}-{$month}-{$day}";
        $dob = date('Y-m-d', strtotime($eventDate . ' -9 years'));

        $meta = [
            'child_first_name' => $cFirst, 'child_middle_name' => $cMid, 'child_surname' => $cSur,
            'gender' => $isMale ? 'Male' : 'Female',
            'address' => $barangays[($i + $vIdx) % count($barangays)] . ', Bacoor City',
            'school' => ($i % 2 === 0) ? 'Bacoor Elementary School' : 'St. Michael Parish Academy',
            'marginal_notes' => 'Completed First Holy Communion catechism at ' . $v['name'] . '.',
        ];

        $rid = $sacModel->create([
            'scope' => $scope, 'chapel_id' => $cid, 'record_type' => 'Communion',
            'person_name' => $full, 'child_name' => $full,
            'event_date' => $eventDate, 'date_of_birth' => $dob, 'place_of_birth' => 'Bacoor City, Cavite',
            'father_name' => $fName, 'mother_name' => $mName,
            'officiating_priest' => $v['priest'],
            'book_number' => 'BK-COM-' . $v['code'] . '-' . $year,
            'page_number' => (string)(10 + (($cursor * 3) % 85)),
            'notes' => json_encode($meta, JSON_UNESCAPED_UNICODE),
        ], $encoderId);
        if ($rid) {
            $addedRecords++;
            if ($i < 2) $venueIssuedTargets[] = ['record_id' => $rid, 'recipient' => $full];
        }
    }

    // 6. Issue up to 10 Certificates (certificate_issuances) for newly seeded records in this venue
    foreach ($venueIssuedTargets as $idx => $cr) {
        $purpose = $purposes[($idx + $vIdx) % count($purposes)];
        $issId = $certSvc->logCertificateIssuance(
            (int)$cr['record_id'],
            (string)$cr['recipient'],
            $purpose,
            $encoderId,
            static function () {}
        );
        if ($issId) $addedIssuances++;
    }

    // 7. Certificate Request Events (service_id=8) — target >= 10 per venue
    if ($cid === 0) {
        $evCntRes = $conn->query("SELECT COUNT(*) c FROM events WHERE is_archived=0 AND service_id=8 AND (chapel_id IS NULL OR chapel_id = 0)");
    } else {
        $evCntRes = $conn->query("SELECT COUNT(*) c FROM events WHERE is_archived=0 AND service_id=8 AND chapel_id = " . (int)$cid);
    }
    $curCertReq = $evCntRes ? (int)$evCntRes->fetch_assoc()['c'] : 0;
    $needCertReq = max(0, 10 - $curCertReq);

    $certTitles = [
        'Baptismal Certificate', 'Baptismal Certificate',
        'Confirmation Certificate', 'Confirmation Certificate',
        'Marriage Certificate', 'Marriage Certificate',
        'Burial / Death Certificate', 'Burial / Death Certificate',
        'First Communion Certificate', 'First Communion Certificate',
    ];
    for ($c = 0; $c < $needCertReq; $c++) {
        $cursor++;
        $rName = $firstMale[($cursor + 3) % count($firstMale)] . ' ' . $surnames[($cursor + 5) % count($surnames)];
        $purpose = $purposes[($c + $vIdx) % count($purposes)];
        $status = $reqStatuses[($c + $vIdx) % count($reqStatuses)];
        $fee = $certFees[($c + $vIdx) % count($certFees)];
        $day = str_pad((string)(2 + (($c * 2 + $vIdx) % 25)), 2, '0', STR_PAD_LEFT);
        $hour = str_pad((string)(8 + ($c % 8)), 2, '0', STR_PAD_LEFT);
        $targetDt = "2026-11-{$day} {$hour}:00:00";
        $evTitle = "{$certTitles[$c]}: {$rName} - {$purpose}";

        $evId = $evModel->requestEvent(
            $evTitle,
            'Ecclesiastical Certificates',
            $targetDt,
            $cid > 0 ? $cid : null,
            $scope === 'cathedral' ? 'Cathedral' : 'Chapel',
            $reqUserId,
            'parishioner.' . strtolower($v['code']) . '.' . ($c + 1) . '@example.local',
            $status,
            $v['priest_id'],
            30,
            '0917' . str_pad((string)(5000000 + $cursor * 31), 7, '0', STR_PAD_LEFT),
            8,
            null,
            $fee,
            0.00,
            $fee,
            'Church'
        );

        if ($evId) {
            $addedCertRequests++;
            if ($status === 'Completed' || ($status === 'Confirmed' && $c % 2 === 0)) {
                $evRef = 'EV-' . str_pad((string)$evId, 4, '0', STR_PAD_LEFT);
                $methods = ['Cash', 'GCash', 'Maya', 'Bank Transfer'];
                $donModel->log(
                    $cid > 0 ? $cid : null,
                    $fee,
                    "Certificate Processing Fee ({$certTitles[$c]})",
                    $rName,
                    date('Y-m-d'),
                    $evRef,
                    $encoderId,
                    $methods[($c + $vIdx) % count($methods)]
                );
            }
        }
    }
}

echo "SEED_SUMMARY|RECORDS_ADDED={$addedRecords}|CERT_ISSUANCES_ADDED={$addedIssuances}|CERT_REQUESTS_ADDED={$addedCertRequests}\n";
exit(0);
