<?php
// pages/file-report.php
$uid = (int)$user['id'];
$bid = (int)$user['barangay_id'];

$ok = ''; $err = ''; $new_case = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rtype   = $_POST['report_type'] ?? 'person'; // person | incident
    $inc     = trim($_POST['incident_type']      ?? '');
    $level   = in_array($_POST['violation_level']??'',['minor','moderate','serious','critical']) ? $_POST['violation_level'] : 'minor';
    $idate   = $_POST['incident_date']            ?? date('Y-m-d');
    $iloc    = trim($_POST['incident_location']   ?? '');
    $narr    = trim($_POST['narrative']           ?? '');
    $cn      = trim($_POST['complainant_name']    ?? ($user['name'] ?? ''));
    $cc      = trim($_POST['complainant_contact'] ?? '');
    $rn      = trim($_POST['respondent_name']     ?? '');
    $rc      = trim($_POST['respondent_contact']  ?? '');

    if (!$inc || !$narr || !$cn) {
        $err = 'Please fill in all required fields (incident type, narrative, your name).';
    } else {
        try {
            // Generate case number
            $last = (int)$pdo->query("SELECT MAX(CAST(SUBSTRING_INDEX(case_number,'-',-1) AS UNSIGNED)) FROM blotters WHERE barangay_id=$bid")->fetchColumn();
            $case_no = 'BL-'.date('Y').'-'.str_pad($bid,3,'0',STR_PAD_LEFT).'-'.str_pad($last+1,4,'0',STR_PAD_LEFT);

            $pdo->prepare("
                INSERT INTO blotters
                  (barangay_id, case_number, complainant_user_id, complainant_name, complainant_contact,
                   respondent_name, respondent_contact, incident_type, violation_level,
                   incident_date, incident_location, narrative,
                   prescribed_action, status, created_at, updated_at)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,'document_only','pending_review',NOW(),NOW())
            ")->execute([$bid,$case_no,$uid,$cn,$cc,$rn,$rc,$inc,$level,$idate,$iloc,$narr]);

            $new_id = (int)$pdo->lastInsertId();
            $new_case = $case_no;
            $ok = "Your report has been submitted. Case No: <strong>$case_no</strong>";

            // Activity log
            try { $pdo->prepare("INSERT INTO activity_log(user_id,barangay_id,action,entity_type,entity_id,description,created_at) VALUES(?,?,'blotter_filed','blotter',?,?,NOW())")->execute([$uid,$bid,$new_id,"Community report filed: $case_no"]); } catch(Exception $e){}
        } catch (PDOException $e) {
            $err = 'Submission failed. Please try again.';
            error_log($e->getMessage());
        }
    }
}

$inc_types = ['Noise Disturbance','Physical Altercation','Verbal Abuse / Threat','Property Damage','Domestic Dispute','VAWC','Trespassing','Theft / Estafa','Drug-Related','Traffic Incident','Public Disturbance','Other'];

// Pre-fill contact from user record
$my_contact = '';
try { $row = $pdo->prepare("SELECT contact_number FROM users WHERE id=? LIMIT 1"); $row->execute([$uid]); $my_contact = $row->fetchColumn() ?: ''; } catch(PDOException $e){}
?>

<div class="page-hdr">
  <div class="page-hdr-left"><h2>File a Report</h2><p>Submit a blotter report to <?= e($bgy_name) ?></p></div>
</div>

<?php if ($ok): ?>
<div class="alert alert-green mb16">
  <svg width="18" height="18" viewBox="0 0 18 18" fill="none" stroke="var(--green-600)" stroke-width="1.6" stroke-linecap="round"><path d="M4 9.5l3.5 3.5 7-7"/></svg>
  <div class="alert-text">
    <strong>Report submitted successfully!</strong>
    <span>Case No: <strong><?= e($new_case) ?></strong> — Your barangay officer will review this shortly. <a href="?page=my-blotters" style="color:var(--green-700);font-weight:600">Track in My Blotters →</a></span>
  </div>
</div>
<?php endif; ?>
<?php if ($err): ?>
<div class="alert alert-rose mb16">
  <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="var(--rose-600)" stroke-width="1.5" stroke-linecap="round"><circle cx="8" cy="8" r="6"/><path d="M8 5v3.5"/></svg>
  <div class="alert-text"><strong><?= e($err) ?></strong></div>
</div>
<?php endif; ?>

<!-- Report type toggle -->
<div style="display:flex;gap:10px;margin-bottom:22px">
  <button id="btn-person" onclick="setType('person')" class="btn btn-primary" style="flex:1;justify-content:center;border-radius:var(--r-lg)">
    <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><circle cx="8" cy="5.5" r="2.5"/><path d="M2 14.5c0-3.3 2.7-6 6-6s6 2.7 6 6"/></svg>
    Report a Person
  </button>
  <button id="btn-incident" onclick="setType('incident')" class="btn btn-outline" style="flex:1;justify-content:center;border-radius:var(--r-lg)">
    <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><circle cx="8" cy="8" r="6"/><path d="M8 5v3.5"/><circle cx="8" cy="11.5" r=".5" fill="currentColor"/></svg>
    Report an Incident
  </button>
</div>

<form method="POST" id="report-form">
  <input type="hidden" name="report_type" id="report_type" value="person">
  <div class="g21">
    <!-- Left: details -->
    <div>
      <!-- Respondent (person report only) -->
      <div class="card mb16" id="respondent-card">
        <div class="card-hdr"><span class="card-title">⚠️ Person Being Reported</span></div>
        <div class="card-body">
          <div class="fr2">
            <div class="fg"><label>Full Name <span class="req">*</span></label><input type="text" name="respondent_name" placeholder="Last, First Middle" value="<?= e($_POST['respondent_name']??'') ?>"></div>
            <div class="fg"><label>Contact Number</label><input type="tel" name="respondent_contact" placeholder="09XXXXXXXXX" value="<?= e($_POST['respondent_contact']??'') ?>"></div>
          </div>
        </div>
      </div>

      <!-- Incident details -->
      <div class="card mb16">
        <div class="card-hdr"><span class="card-title">📋 Incident Details</span></div>
        <div class="card-body">
          <div class="fr3">
            <div class="fg">
              <label>Incident Type <span class="req">*</span></label>
              <select name="incident_type" required>
                <option value="">— Select —</option>
                <?php foreach ($inc_types as $t): ?>
                  <option <?= (($_POST['incident_type']??'')===$t)?'selected':'' ?>><?= $t ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="fg">
              <label>Severity Level <span class="req">*</span></label>
              <select name="violation_level" required>
                <?php foreach (['minor'=>'Minor','moderate'=>'Moderate','serious'=>'Serious','critical'=>'Critical'] as $v=>$l): ?>
                  <option value="<?= $v ?>" <?= (($_POST['violation_level']??'minor')===$v)?'selected':'' ?>><?= $l ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="fg"><label>Incident Date <span class="req">*</span></label><input type="date" name="incident_date" max="<?= date('Y-m-d') ?>" value="<?= e($_POST['incident_date']??date('Y-m-d')) ?>"></div>
          </div>
          <div class="fg"><label>Incident Location</label><input type="text" name="incident_location" placeholder="Street, Sitio, or Landmark" value="<?= e($_POST['incident_location']??'') ?>"></div>
          <div class="fg"><label>Narrative / Description <span class="req">*</span></label><textarea name="narrative" rows="5" required placeholder="Describe what happened in detail. Include the time, place, people involved, and sequence of events…"><?= e($_POST['narrative']??'') ?></textarea></div>
        </div>
      </div>
    </div>

    <!-- Right: complainant + notice + submit -->
    <div>
      <div class="card mb16">
        <div class="card-hdr"><span class="card-title">👤 Your Information</span></div>
        <div class="card-body">
          <div class="fg"><label>Full Name <span class="req">*</span></label><input type="text" name="complainant_name" value="<?= e($_POST['complainant_name']??$user['name']??'') ?>" required></div>
          <div class="fg"><label>Contact Number</label><input type="tel" name="complainant_contact" placeholder="09XXXXXXXXX" value="<?= e($_POST['complainant_contact']??$my_contact) ?>"></div>
        </div>
      </div>

      <div class="card mb16" style="border-left:3px solid var(--amber-400)">
        <div class="card-body" style="padding:14px 16px">
          <div style="font-size:12px;font-weight:700;color:var(--amber-600);margin-bottom:8px">⚠️ IMPORTANT NOTICE</div>
          <p style="font-size:12px;color:var(--ink-600);line-height:1.7">Filing a false report is a punishable offense. Ensure all information is accurate and truthful. Your barangay officer will review this submission.</p>
        </div>
      </div>

      <div class="card">
        <div class="card-body">
          <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;padding:11px">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><path d="M2 8l4.5 4.5L14 3"/></svg>
            Submit Report
          </button>
          <p style="font-size:11px;color:var(--ink-400);text-align:center;margin-top:10px">Your report will be reviewed by a barangay officer within 24 hours</p>
        </div>
      </div>
    </div>
  </div>
</form>

<script>
function setType(type) {
  document.getElementById('report_type').value = type;
  const card = document.getElementById('respondent-card');
  const btnP = document.getElementById('btn-person');
  const btnI = document.getElementById('btn-incident');
  if (type === 'person') {
    card.style.display = '';
    btnP.className = 'btn btn-primary'; btnI.className = 'btn btn-outline';
  } else {
    card.style.display = 'none';
    btnI.className = 'btn btn-primary'; btnP.className = 'btn btn-outline';
  }
  [btnP, btnI].forEach(b => { b.style.flex='1'; b.style.justifyContent='center'; b.style.borderRadius='var(--r-lg)'; });
}
</script>
