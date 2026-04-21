<?php
$pdo = new PDO('mysql:host=localhost;dbname=voice2_db;charset=utf8mb4', 'root', '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$incidentTypes = ['Noise Disturbance','Physical Altercation','Verbal Abuse / Threat','Property Damage','Domestic Dispute','VAWC','Trespassing','Theft / Estafa','Drug-Related','Traffic Incident','Public Disturbance','Other'];
$violationLevels = ['minor','moderate','serious','critical'];
$prescribedActions = ['document_only','mediation','refer_barangay','refer_police','refer_vawc','escalate_municipality','pending','barangay_deliberation'];
$statuses = ['pending_review','active','mediation_set','escalated','resolved','closed','transferred','dismissed','cfa_issued','repudiated','deliberation'];

$streets = ['Rizal Street','Mabini Avenue','Quezon Boulevard','Bonifacio Street','Aguinaldo Road','Emilio Jacinto Street','Andres Bonifacio Avenue','Macarthur Street','Katipunan Avenue','Bayanihan Street','Maharlika Highway','Purok 1','Purok 2','Purok 3','Purok 4','Purok 5','Purok 6','Purok 7','Purok 8','Sitio Kanluran','Sitio Silanganan','Bagong Timog','Bagong Sikat','Poblacion Central','Barangay Hall Road'];

$narrativeTemplates = [
    'Nagtalo ang dalawang partido dahil sa [reason]. Ang [respondent] ay [action] habang ang [complainant] ay nagsikap na mag-alaga ng kapayapaan.',
    'Nag-report ang [complainant] na ang [respondent] ay [action] sa property niya. Ito ay nangyari noong [time] sa [location].',
    'May ingay na galing sa bahay ng [respondent] na [time]. Maraming tao ang nag-complain dahil sa ingay.',
    'Ang [complainant] ay makipag-ugnayan sa [respondent] tungkol sa [reason]. Nag-uusap kami ng maingat upang malutas ang isyu.',
    'Nag-alala ang [complainant] dahil sa behavior ng [respondent]. Hiniling naming tulungan ang barangay na mag-intervene.',
    'May insidente ng [action] sa [location] na may kasamang [respondent] at [complainant].',
    'Nag-report ng [reason] ang [complainant] laban sa [respondent]. Kinekwestyon ang kultura ng kapangyarihan.',
    'Ang barangay ay nakatanggap ng aming paghahabag mula sa [complainant] tungkol sa [reason].',
];

$actions = ['umano ay lumakas','sumubok na','nagsagawa ng','nag-atubili sa','namumuhunan ng','nagsalita ng','nagsama ng'];
$reasons = ['property dispute','unpaid debt','family conflict','land boundary issue','harassment','intimidation','disrespect'];

$userStmt = $pdo->prepare("SELECT id, barangay_id, first_name, last_name, contact_number, address FROM users WHERE role = 'community' ORDER BY RAND() LIMIT 500");
$userStmt->execute();
$communityUsers = $userStmt->fetchAll(PDO::FETCH_ASSOC);

if (count($communityUsers) < 2) {
    die("Insufficient community users in database\n");
}

$barangayCountStmt = $pdo->prepare("SELECT DISTINCT barangay_id FROM users WHERE role = 'community'");
$barangayCountStmt->execute();
$barangays = $barangayCountStmt->fetchAll(PDO::FETCH_COLUMN);

if (empty($barangays)) {
    die("No barangays found\n");
}

$lastCaseNumberStmt = $pdo->prepare("SELECT MAX(CAST(SUBSTRING_INDEX(case_number, '-', -1) AS UNSIGNED)) as maxNum FROM blotters WHERE case_number LIKE 'BL-2026-%'");
$lastCaseNumberStmt->execute();
$lastResult = $lastCaseNumberStmt->fetch(PDO::FETCH_ASSOC);
$caseCounter = ($lastResult['maxNum'] ?? 0) + 1;

$insertStmt = $pdo->prepare("
    INSERT INTO blotters (
        case_number, barangay_id, incident_type, violation_level, incident_date, incident_time,
        incident_location, incident_lat, incident_lng, complainant_user_id, complainant_name, complainant_contact,
        complainant_address, complainant_missed, respondent_user_id, respondent_name, respondent_contact,
        respondent_address, respondent_missed, narrative, prescribed_action, status,
        created_at, updated_at
    ) VALUES (
        ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
    )
");

for ($i = 0; $i < 200; $i++) {
    $selectedBarangay = $barangays[array_rand($barangays)];
    
    $barangayUsersStmt = $pdo->prepare("SELECT id, barangay_id, first_name, last_name, contact_number, address FROM users WHERE role = 'community' AND barangay_id = ? LIMIT 100");
    $barangayUsersStmt->execute([$selectedBarangay]);
    $barangayUsers = $barangayUsersStmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($barangayUsers) < 2) {
        $complainantUser = $communityUsers[array_rand($communityUsers)];
        $respondentUser = $communityUsers[array_rand($communityUsers)];
        while ($respondentUser['id'] == $complainantUser['id']) {
            $respondentUser = $communityUsers[array_rand($communityUsers)];
        }
    } else {
        $complainantUser = $barangayUsers[array_rand($barangayUsers)];
        $respondentUser = $barangayUsers[array_rand($barangayUsers)];
        while ($respondentUser['id'] == $complainantUser['id']) {
            $respondentUser = $barangayUsers[array_rand($barangayUsers)];
        }
    }
    
    $barangayId = $selectedBarangay;
    
    $complaintName = $complainantUser['first_name'] . ' ' . $complainantUser['last_name'];
    $respondentName = $respondentUser['first_name'] . ' ' . $respondentUser['last_name'];
    
    $caseNumber = sprintf("BL-2026-%03d-%04d", 1, $caseCounter++);
    $incidentType = $incidentTypes[array_rand($incidentTypes)];
    $violationLevel = $violationLevels[array_rand($violationLevels)];
    $prescribedAction = $prescribedActions[array_rand($prescribedActions)];
    $status = $statuses[array_rand($statuses)];
    
    $incidentDate = (new DateTime())->sub(new DateInterval('P' . rand(0, 180) . 'D'))->format('Y-m-d');
    $incidentTime = sprintf("%02d:%02d", rand(0, 23), rand(0, 59));
    
    $street = $streets[array_rand($streets)];
    $incidentLocation = $street . ', Paete, Laguna';
    
    $baseLat = 14.3520;
    $baseLng = 121.2500;
    $lat = $baseLat + (rand(-100, 100) / 1000);
    $lng = $baseLng + (rand(-100, 100) / 1000);
    
    $narrative = str_replace(
        ['[respondent]', '[complainant]', '[reason]', '[action]', '[time]', '[location]'],
        [$respondentName, $complaintName, $reasons[array_rand($reasons)], $actions[array_rand($actions)], $incidentTime, $street],
        $narrativeTemplates[array_rand($narrativeTemplates)]
    );
    
    $complainantMissed = rand(0, 3);
    $respondentMissed = rand(0, 3);
    
    $createdDaysAgo = rand(0, 180);
    $updatedDaysAgo = rand(0, $createdDaysAgo);
    
    $createdAt = date('Y-m-d H:i:s', strtotime('-' . $createdDaysAgo . ' days'));
    $updatedAt = date('Y-m-d H:i:s', strtotime('-' . $updatedDaysAgo . ' days'));
    
    $insertStmt->execute([
        $caseNumber, 
        $barangayId, 
        $incidentType, 
        $violationLevel, 
        $incidentDate, 
        $incidentTime,
        $incidentLocation, 
        $lat, 
        $lng, 
        $complainantUser['id'], 
        $complaintName, 
        $complainantUser['contact_number'],
        $complainantUser['address'] ?? '', 
        $complainantMissed, 
        $respondentUser['id'], 
        $respondentName, 
        $respondentUser['contact_number'],
        $respondentUser['address'] ?? '', 
        $respondentMissed, 
        $narrative, 
        $prescribedAction, 
        $status,
        $createdAt, 
        $updatedAt
    ]);
}

$pdo = null;
?>