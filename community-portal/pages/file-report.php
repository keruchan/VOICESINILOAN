
<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', dirname(__FILE__) . '/php-errors.log');

// pages/file-report.php
$uid = (int)$user['id'];
$bid = (int)$user['barangay_id'];

$ok = ''; $err = ''; $new_case = ''; $submitted = false;

// Pre-fill personal info from DB (read-only, not editable)
$my_contact = '';
try {
    $me = $pdo->prepare("SELECT contact_number FROM users WHERE id=? LIMIT 1");
    $me->execute([$uid]);
    $my_contact = $me->fetchColumn() ?: '';
} catch (PDOException $e) {}

// Barangay is always the user's own barangay — no dropdown needed

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rtype    = $_POST['report_type']              ?? 'person';
    $inc_sel  = trim($_POST['incident_type_sel']   ?? '');          // dropdown value
    $inc_other= trim($_POST['incident_type_other'] ?? '');          // free-text when "Other"
    $inc      = ($inc_sel === 'Other' && $inc_other !== '') ? $inc_other : $inc_sel;
    $level    = in_array($_POST['violation_level'] ?? '', ['minor','moderate','serious','critical'])
                ? $_POST['violation_level'] : 'minor';
    $idate    = $_POST['incident_date']            ?? date('Y-m-d');

    // Location fields
    $loc_street  = trim($_POST['incident_street']   ?? '');
    $loc_barangay= trim($_POST['incident_barangay'] ?? '');
    $loc_lat     = trim($_POST['incident_lat']      ?? '');
    $loc_lng     = trim($_POST['incident_lng']      ?? '');

    // Respondent fields
    $rn    = trim($_POST['respondent_name']     ?? '');
    $rc    = trim($_POST['respondent_contact']  ?? '');
    $r_uid = (int)($_POST['respondent_user_id'] ?? 0);

    // Narrative
    $narr = trim($_POST['narrative']               ?? '');
    $cn   = $user['name'] ?? '';
    $cc   = $my_contact;

    // ════════════════════════════════════════
    // VALIDATION CHECKS
    // ════════════════════════════════════════
    $errors = [];

    if (!$inc_sel)
        $errors[] = 'Incident type is required.';
    elseif ($inc_sel === 'Other' && $inc_other === '')
        $errors[] = 'You selected "Other" — please specify the incident type.';

    if (!$loc_street)
        $errors[] = 'Street / Address is required.';

    if (!$narr)
        $errors[] = 'Narrative / Description is required.';
    elseif (strlen($narr) < 20)
        $errors[] = 'Narrative is too short — please provide at least 20 characters.';

    if ($idate > date('Y-m-d'))
        $errors[] = 'Incident date cannot be in the future.';

    if ($rtype === 'person' && !$rn)
        $errors[] = 'Respondent name is required when reporting a person.';

    if (!empty($errors)) {
        $err = '❌ <strong>Please fix the following:</strong><ul style="margin:6px 0 0 18px;padding:0">'
             . implode('', array_map(fn($e) => "<li>$e</li>", $errors))
             . '</ul>';
    } else {
        // All validations passed, proceed with database insertion
        try {
            // Generate case number
            $last    = (int)$pdo->query("SELECT MAX(CAST(SUBSTRING_INDEX(case_number,'-',-1) AS UNSIGNED)) FROM blotters WHERE barangay_id=$bid")->fetchColumn();
            $case_no = 'BL-' . date('Y') . '-' . str_pad($bid,3,'0',STR_PAD_LEFT) . '-' . str_pad($last+1,4,'0',STR_PAD_LEFT);

            // Build combined location string
            $iloc = $loc_street ? $loc_street . ', ' . $loc_barangay : $loc_barangay;

            // NOTE: If your blotters table does NOT have incident_lat/incident_lng columns yet,
            // run this migration first:
            //   ALTER TABLE blotters ADD COLUMN incident_lat DECIMAL(10,7) NULL AFTER incident_location;
            //   ALTER TABLE blotters ADD COLUMN incident_lng DECIMAL(10,7) NULL AFTER incident_lat;
            // Or remove those two columns+placeholders from the INSERT below.
            
            $stmt = $pdo->prepare("
                INSERT INTO blotters
                  (barangay_id, case_number, complainant_user_id, complainant_name, complainant_contact,
                   respondent_user_id, respondent_name, respondent_contact, incident_type, violation_level,
                   incident_date, incident_location, incident_lat, incident_lng,
                   narrative, prescribed_action, status, created_at, updated_at)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'pending','pending_review',NOW(),NOW())
            ");
            
            $result = $stmt->execute([
                $bid, $case_no, $uid, $cn, $cc,
                ($r_uid > 0 ? $r_uid : null),
                $rn, $rc, $inc, $level,
                $idate, $iloc,
                ($loc_lat  !== '' ? $loc_lat  : null),
                ($loc_lng  !== '' ? $loc_lng  : null),
                $narr
            ]);

            if (!$result) {
                throw new PDOException("Database insert failed");
            }

            $new_id = (int)$pdo->lastInsertId();

            if (!$new_id) {
                throw new PDOException("Failed to retrieve inserted record ID");
            }

            // Handle file attachments
            $attach_count = 0;
            $attach_errors = [];
            
            if (!empty($_FILES['attachments']['name'][0])) {
                $upload_dir = dirname(__DIR__, 2) . '/uploads/blotters/' . $new_id . '/';
                
                if (!is_dir($upload_dir)) {
                    if (!mkdir($upload_dir, 0755, true)) {
                        throw new PDOException("Failed to create upload directory");
                    }
                }

                $allowed_mime = ['image/jpeg','image/png','image/gif','image/webp'];
                $max_size     = 5 * 1024 * 1024;

                foreach ($_FILES['attachments']['tmp_name'] as $i => $tmp) {
                    $file_name = $_FILES['attachments']['name'][$i] ?? 'unknown';
                    $file_error = $_FILES['attachments']['error'][$i] ?? UPLOAD_ERR_NO_FILE;
                    $file_size = $_FILES['attachments']['size'][$i] ?? 0;

                    // Check for upload errors
                    if ($file_error !== UPLOAD_ERR_OK) {
                        $error_msg = '';
                        switch ($file_error) {
                            case UPLOAD_ERR_INI_SIZE:
                                $error_msg = "File exceeds server upload limit";
                                break;
                            case UPLOAD_ERR_FORM_SIZE:
                                $error_msg = "File exceeds form limit";
                                break;
                            case UPLOAD_ERR_PARTIAL:
                                $error_msg = "File upload was incomplete";
                                break;
                            case UPLOAD_ERR_NO_FILE:
                                continue 2; // Skip this file
                            case UPLOAD_ERR_NO_TMP_DIR:
                                $error_msg = "Temporary folder missing";
                                break;
                            case UPLOAD_ERR_CANT_WRITE:
                                $error_msg = "Failed to write file to disk";
                                break;
                            case UPLOAD_ERR_EXTENSION:
                                $error_msg = "File upload stopped by extension";
                                break;
                            default:
                                $error_msg = "Unknown upload error";
                        }
                        $attach_errors[] = "$file_name: $error_msg";
                        continue;
                    }

                    // Check file size
                    if ($file_size > $max_size) {
                        $size_mb = round($max_size / 1024 / 1024, 1);
                        $attach_errors[] = "$file_name: File is too large (max ${size_mb}MB)";
                        continue;
                    }

                    // Check file type
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $mime  = finfo_file($finfo, $tmp);
                    finfo_close($finfo);
                    
                    if (!in_array($mime, $allowed_mime)) {
                        $attach_errors[] = "$file_name: Invalid file type ($mime). Only JPG, PNG, GIF, and WEBP are allowed.";
                        continue;
                    }

                    // Process valid file
                    $ext      = ['image/jpeg'=>'jpg','image/png'=>'png','image/gif'=>'gif','image/webp'=>'webp'][$mime];
                    $filename = uniqid('att_') . '.' . $ext;
                    $dest     = $upload_dir . $filename;

                    if (!move_uploaded_file($tmp, $dest)) {
                        $attach_errors[] = "$file_name: Failed to save file to server";
                        continue;
                    }

                    // Record attachment in database
                    try {
                        $rel_path = 'uploads/blotters/' . $new_id . '/' . $filename;
                        $attach_stmt = $pdo->prepare("INSERT INTO blotter_attachments(blotter_id,uploaded_by,file_path,original_name,file_size,mime_type,created_at) VALUES(?,?,?,?,?,?,NOW())");
                        
                        if ($attach_stmt->execute([$new_id,$uid,$rel_path,$file_name,$file_size,$mime])) {
                            $attach_count++;
                        } else {
                            $attach_errors[] = "$file_name: Failed to record in database";
                        }
                    } catch (PDOException $ex) {
                        error_log("Attachment DB error: " . $ex->getMessage());
                        $attach_errors[] = "$file_name: Database error while saving";
                    }
                }

                // Log attachment errors if any
                if (!empty($attach_errors)) {
                    error_log("Attachment issues: " . implode(" | ", $attach_errors));
                }
            }

            // Activity log
            try {
                $desc = "Community report filed: $case_no" . ($attach_count ? " · {$attach_count} attachment(s)" : "");
                $log_stmt = $pdo->prepare("INSERT INTO activity_log(user_id,barangay_id,action,entity_type,entity_id,description,created_at) VALUES(?,?,'blotter_filed','blotter',?,?,NOW())");
                
                if (!$log_stmt->execute([$uid,$bid,$new_id,$desc])) {
                    error_log("Failed to create activity log entry");
                }
            } catch (Exception $e) {
                error_log("Activity log error: " . $e->getMessage());
            }

            $new_case  = $case_no;
            $submitted = true;

        } catch (PDOException $e) {
            $err = '❌ <strong>Database error during submission.</strong> ' . htmlspecialchars($e->getMessage());
            error_log("Blotter submission error: " . $e->getMessage());
        } catch (Exception $e) {
            $err = '❌ <strong>An unexpected error occurred.</strong> ' . htmlspecialchars($e->getMessage());
            error_log("Blotter submission exception: " . $e->getMessage());
        }
    } // end else (validation passed)
}

// Severity auto-map — keyed by incident type
$severity_map = [
    'Noise Disturbance'     => 'minor',
    'Public Disturbance'    => 'minor',
    'Traffic Incident'      => 'minor',
    'Other'                 => 'minor',
    'Verbal Abuse / Threat' => 'moderate',
    'Trespassing'           => 'moderate',
    'Property Damage'       => 'moderate',
    'Theft / Estafa'        => 'serious',
    'Physical Altercation'  => 'serious',
    'Drug-Related'          => 'serious',
    'Domestic Dispute'      => 'serious',
    'VAWC'                  => 'critical',
];

// label, description, hex-color, emoji
$severity_info = [
    'minor'    => ['Minor',    'Low risk · typically handled via verbal warning or documentation',       '#16A34A', '🟢'],
    'moderate' => ['Moderate', 'May require mediation or a written agreement between parties',           '#B45309', '🟡'],
    'serious'  => ['Serious',  'Requires formal mediation and may result in sanctions or penalties',     '#BE123C', '🔴'],
    'critical' => ['Critical', 'Urgent — may require police referral or immediate legal intervention',   '#6D28D9', '🟣'],
];

$inc_types = array_keys($severity_map);

// Barangay center coords for map — fetch from barangays table if available
$bgy_lat = 14.5995; // fallback: Manila
$bgy_lng = 120.9842;
try {
    $bgy_row = $pdo->prepare("SELECT lat, lng FROM barangays WHERE id=? LIMIT 1");
    $bgy_row->execute([$bid]);
    $coords = $bgy_row->fetch(PDO::FETCH_ASSOC);
    if ($coords && $coords['lat'] && $coords['lng']) {
        $bgy_lat = (float)$coords['lat'];
        $bgy_lng = (float)$coords['lng'];
    }
} catch (PDOException $e) {
    error_log("Failed to fetch barangay coords: " . $e->getMessage());
}

// Fetch barangay list from barangay_name table
$barangay_list = [];
try {
    $stmt = $pdo->query("SELECT id, name FROM barangay_name ORDER BY name ASC");
    $barangay_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Failed to fetch barangay list: " . $e->getMessage());
}

?>

<!-- Leaflet CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.min.css" crossorigin=""/>

<div class="page-hdr">
  <div class="page-hdr-left"><h2>File a Report</h2><p>Submit a blotter report</p></div>
</div>

<?php if ($submitted): ?>
<!-- ══════════ SUCCESS STATE ══════════ -->
<div style="text-align:center;padding:56px 24px;max-width:500px;margin:0 auto">
  <div style="width:68px;height:68px;background:var(--green-50);border:2px solid var(--green-200);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 20px">
    <svg width="30" height="30" viewBox="0 0 30 30" fill="none" stroke="var(--green-600)" stroke-width="2.2" stroke-linecap="round"><path d="M6 15l6.5 7L24 8"/></svg>
  </div>
  <h2 style="font-family:var(--font-display);font-size:22px;color:var(--ink-900);margin-bottom:8px">Report Submitted!</h2>
  <p style="font-size:14px;color:var(--ink-500);line-height:1.7;margin-bottom:8px">Your case number is:</p>
  <div style="font-family:var(--font-mono);font-size:22px;font-weight:700;color:var(--green-700);background:var(--green-50);border:1px solid var(--green-200);border-radius:var(--r-lg);padding:10px 28px;display:inline-block;margin-bottom:20px;letter-spacing:.05em">
    <?= e($new_case) ?>
  </div>
  <p style="font-size:13px;color:var(--ink-400);line-height:1.7;margin-bottom:28px">
    Your barangay officer will review your report shortly.<br>Track its status anytime under <strong>My Blotters</strong>.
  </p>
  <div style="display:flex;gap:10px;justify-content:center;flex-wrap:wrap">
    <a href="?page=my-blotters" class="btn btn-primary">📋 Track in My Blotters</a>
    <a href="?page=file-report" class="btn btn-outline">+ File Another Report</a>
  </div>
</div>

<?php else: ?>
<!-- ══════════ FORM ══════════ -->

<?php if ($err): ?>
<div class="alert alert-rose mb16">
  <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="var(--rose-600)" stroke-width="1.5" stroke-linecap="round"><circle cx="8" cy="8" r="6"/><path d="M8 5v3.5"/><circle cx="8" cy="11.5" r=".5" fill="currentColor"/></svg>
  <div class="alert-text"><?= $err ?></div>
</div>
<?php endif; ?>

<!-- Report type toggle -->
<div style="display:flex;gap:10px;margin-bottom:22px">
  <button type="button" id="btn-person"   onclick="setType('person')"   class="btn btn-primary"  style="flex:1;justify-content:center;border-radius:var(--r-lg)">
    <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><circle cx="8" cy="5.5" r="2.5"/><path d="M2 14.5c0-3.3 2.7-6 6-6s6 2.7 6 6"/></svg>
    Report a Person
  </button>
  <button type="button" id="btn-incident" onclick="setType('incident')" class="btn btn-outline" style="flex:1;justify-content:center;border-radius:var(--r-lg)">
    <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><circle cx="8" cy="8" r="6"/><path d="M8 5v3.5"/><circle cx="8" cy="11.5" r=".5" fill="currentColor"/></svg>
    Report an Incident
  </button>
</div>

<form method="POST" id="report-form" enctype="multipart/form-data">
  <input type="hidden" name="report_type"        id="report_type"        value="person">
  <input type="hidden" name="respondent_user_id" id="respondent_user_id" value="<?= e($_POST['respondent_user_id'] ?? '') ?>">
  <input type="hidden" name="incident_lat"       id="incident_lat"       value="<?= e($_POST['incident_lat']  ?? '') ?>">
  <input type="hidden" name="incident_lng"       id="incident_lng"       value="<?= e($_POST['incident_lng']  ?? '') ?>">

  <div class="g21">
    <!-- ── LEFT COLUMN ── -->
    <div>

      <!-- Person being reported (person mode only) -->
      <div class="card mb16" id="respondent-card">
        <div class="card-hdr"><span class="card-title">⚠️ Person Being Reported</span></div>
        <div class="card-body">
          <div class="fr2">

            <!-- ── Respondent name: live search ── -->
            <div class="fg" id="resp-wrap">
              <label>Full Name <span class="req">*</span></label>

              <!-- Badge shown after selecting a registered user -->
              <div id="resp-linked-badge"
                   style="display:none;align-items:center;gap:6px;
                          background:var(--green-50);border:1px solid var(--green-200);
                          border-radius:var(--r-md);padding:7px 10px;margin-bottom:6px;font-size:12px">
                <svg width="13" height="13" viewBox="0 0 13 13" fill="none"
                     stroke="var(--green-600)" stroke-width="1.8" stroke-linecap="round">
                  <path d="M2 7l3.5 3.5L11 3"/>
                </svg>
                <span style="font-weight:600;color:var(--green-700)" id="resp-linked-name"></span>
                <span style="color:var(--green-600);font-size:11px">· Registered user</span>
                <button type="button" onclick="unlinkRespondent()"
                        title="Unlink — type manually instead"
                        style="margin-left:auto;background:none;border:none;cursor:pointer;
                               color:var(--ink-400);font-size:16px;line-height:1;padding:0 2px">×</button>
              </div>

              <!-- Text input -->
              <div style="position:relative">
                <input type="text"
                       id="resp-search-input"
                       name="respondent_name"
                       placeholder="Type to search, or leave blank if unknown"
                       value="<?= e($_POST['respondent_name'] ?? '') ?>"
                       autocomplete="off"
                       oninput="onRespInput(this.value)"
                       onkeydown="onRespKeydown(event)"
                       onfocus="onRespFocus()"
                       style="width:100%">
                <div id="resp-spinner"
                     style="display:none;position:absolute;right:10px;top:50%;transform:translateY(-50%)">
                  <svg width="14" height="14" viewBox="0 0 14 14" fill="none"
                       stroke="var(--ink-400)" stroke-width="2" stroke-linecap="round"
                       style="animation:resp-spin .75s linear infinite">
                    <circle cx="7" cy="7" r="5" stroke-opacity=".25"/>
                    <path d="M7 2a5 5 0 0 1 5 5"/>
                  </svg>
                </div>
              </div>

              <div style="font-size:11px;color:var(--ink-400);margin-top:5px;line-height:1.5">
                Type to search registered users · select if found · keep typing if not registered · leave blank if unknown
              </div>
            </div>

            <div class="fg">
              <label>Contact Number</label>
              <input type="tel" name="respondent_contact" placeholder="09XXXXXXXXX"
                     value="<?= e($_POST['respondent_contact'] ?? '') ?>">
            </div>

          </div>
        </div>
      </div>

      <!-- Respondent dropdown — appended to body via JS to escape card overflow clipping -->
      <div id="resp-dropdown"
           style="display:none;position:fixed;z-index:9999;
                  background:var(--surface,#fff);border:1px solid var(--ink-100);
                  border-radius:var(--r-lg);box-shadow:0 8px 28px rgba(0,0,0,.15);
                  overflow:hidden;min-width:260px"></div>

      <!-- Incident details -->
      <div class="card mb16">
        <div class="card-hdr"><span class="card-title">📋 Incident Details</span></div>
        <div class="card-body">

          <div class="fr2">
            <div class="fg">
              <label>Incident Type <span class="req">*</span></label>
              <select name="incident_type_sel" id="incident-type-sel"
                      onchange="autoSeverity(this.value); toggleOtherField(this.value)" required>
                <option value="">— Select incident type —</option>
                <?php foreach ($inc_types as $t): ?>
                  <option value="<?= e($t) ?>" <?= (($_POST['incident_type_sel'] ?? '') === $t) ? 'selected' : '' ?>><?= e($t) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="fg">
              <label>Incident Date <span class="req">*</span></label>
              <input type="date" name="incident_date" max="<?= date('Y-m-d') ?>" value="<?= e($_POST['incident_date'] ?? date('Y-m-d')) ?>">
            </div>
          </div>

          <!-- "Other" free-text — visible only when Other is selected -->
          <div class="fg" id="other-type-wrap" style="display:none;margin-top:-4px">
            <label>Please specify <span class="req">*</span></label>
            <input type="text" name="incident_type_other" id="incident-type-other"
                   placeholder="e.g. Illegal dumping, Stray animals, Squatting…"
                   value="<?= e($_POST['incident_type_other'] ?? '') ?>"
                   maxlength="120">
          </div>

          <!-- Auto-severity indicator -->
          <input type="hidden" name="violation_level" id="violation-level-input" value="<?= e($_POST['violation_level'] ?? 'minor') ?>">
          <div id="severity-card" style="display:none;border-radius:var(--r-md);padding:12px 14px;margin-bottom:16px;border:1px solid;transition:all .2s">
            <div style="font-size:10px;font-weight:700;color:var(--ink-400);letter-spacing:.08em;text-transform:uppercase;margin-bottom:6px">Auto-assigned Severity Level</div>
            <div style="display:flex;align-items:center;justify-content:space-between;gap:10px">
              <div style="display:flex;align-items:center;gap:8px">
                <span id="sev-emoji" style="font-size:18px;line-height:1"></span>
                <div>
                  <div id="sev-label" style="font-size:15px;font-weight:700;line-height:1.2"></div>
                  <div id="sev-desc"  style="font-size:11px;color:var(--ink-500);margin-top:2px;line-height:1.4"></div>
                </div>
              </div>
              <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="var(--ink-300)" stroke-width="1.5" stroke-linecap="round"><circle cx="8" cy="8" r="6"/><path d="M8 5v3.5"/><circle cx="8" cy="11.5" r=".5" fill="currentColor"/></svg>
            </div>
          </div>

          <!-- ── INCIDENT LOCATION ── -->
          <fieldset style="border:1px solid var(--ink-100);border-radius:var(--r-lg);padding:14px 14px 10px;margin-bottom:16px">
            <legend style="font-size:12px;font-weight:700;color:var(--ink-600);padding:0 6px">
              📍 Incident Location
            </legend>

            <div class="fr2" style="margin-bottom:12px">
             <!-- Barangay — now a dropdown, styled like normal inputs -->
<div class="fg" style="margin-bottom:0">
  <label>Barangay <span class="req">*</span></label>
  <select name="incident_barangay" id="incident-barangay"
          required
          style="
            background:#fff; /* white like other inputs */
            color:var(--ink-900); /* black text */
            border:1px solid var(--ink-100);
            cursor:pointer;
            width:100%;
            padding:10px 12px;
            border-radius:var(--r-sm);
            font-size:14px;
            line-height:1.4;
            height:38px; /* match your other inputs */
          ">
    <option value="">— Select Barangay —</option>
    <?php foreach ($barangay_list as $b): ?>
      <option value="<?= e($b['name']) ?>"
        <?= (($_POST['incident_barangay'] ?? $bgy_name ?? '') === $b['name']) ? 'selected' : '' ?>>
        <?= e($b['name']) ?>
      </option>
    <?php endforeach; ?>
  </select>
</div>
              <!-- Street / address — required -->
              <div class="fg" style="margin-bottom:0">
                <label>Street / Address <span class="req">*</span></label>
                <input type="text" name="incident_street" id="incident-street"
                       placeholder="e.g. 123 Rizal St., Purok 4"
                       value="<?= e($_POST['incident_street'] ?? '') ?>"
                       oninput="onStreetInput()"
                       required>
              </div>
            </div>

            <!-- Map toggle button + pin badge -->
            <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
              <button type="button" id="map-toggle-btn" onclick="toggleMap()"
                      style="display:inline-flex;align-items:center;gap:6px;font-size:12px;font-weight:600;
                             color:var(--green-700);background:var(--green-50);border:1px solid var(--green-200);
                             border-radius:var(--r-md);padding:6px 12px;cursor:pointer;transition:all .15s">
                <svg width="14" height="14" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round">
                  <circle cx="7" cy="6" r="2"/><path d="M7 1C4.24 1 2 3.24 2 6c0 3.75 5 8 5 8s5-4.25 5-8c0-2.76-2.24-5-5-5z"/>
                </svg>
                <span id="map-toggle-label">📌 Pin on Map (optional)</span>
              </button>
              <span id="pin-indicator"
                    style="display:none;font-size:11px;color:var(--green-700);background:var(--green-50);
                           border:1px solid var(--green-200);border-radius:var(--r-sm);padding:3px 8px;font-weight:600">
                ✓ Location pinned
              </span>
              <button type="button" id="clear-pin-btn" onclick="clearPin()"
                      style="display:none;font-size:11px;color:var(--rose-600);background:transparent;
                             border:none;cursor:pointer;text-decoration:underline;padding:0">
                Remove pin
              </button>
            </div>

            <!-- Leaflet map container (hidden by default) -->
            <div id="map-wrap" style="display:none;margin-top:10px">
              <div style="font-size:11px;color:var(--ink-400);margin-bottom:6px;line-height:1.6">
                Click on the map to drop a pin · drag pin to adjust · the Street field updates automatically.
              </div>
              <div id="incident-map"
                   style="height:300px;border-radius:var(--r-lg);border:1px solid var(--ink-100);overflow:hidden;z-index:0"></div>
              <div id="geocode-status"
                   style="font-size:11px;color:var(--ink-400);margin-top:5px;min-height:16px;line-height:1.5"></div>
            </div>

          </fieldset>
          <!-- /location -->

          <div class="fg">
            <label>Narrative / Description <span class="req">*</span></label>
            <textarea name="narrative" rows="5" required
                      placeholder="Describe what happened in detail. Include the time, place, people involved, and the sequence of events…"><?= e($_POST['narrative'] ?? '') ?></textarea>
          </div>

          <!-- Photo attachments -->
          <div class="fg" style="margin-bottom:0">
            <label>
              Photo Attachments
              <span style="font-size:11px;color:var(--ink-400);font-weight:400"> — optional · up to 5 photos · max 5MB each · JPG / PNG / GIF / WEBP</span>
            </label>

            <div id="upload-zone"
                 onclick="document.getElementById('file-input').click()"
                 style="border:2px dashed var(--ink-200);border-radius:var(--r-lg);padding:24px 16px;text-align:center;cursor:pointer;transition:border-color .15s,background .15s;background:var(--surface)">
              <div id="upload-placeholder">
                <svg width="32" height="32" viewBox="0 0 32 32" fill="none" stroke="var(--ink-300)" stroke-width="1.4" stroke-linecap="round" style="margin-bottom:8px"><rect x="4" y="4" width="24" height="24" rx="3"/><path d="M4 22l7-7 5 5 4-5 6 7"/><circle cx="22" cy="11" r="2.5"/></svg>
                <div style="font-size:13px;font-weight:500;color:var(--ink-500)">Click to upload photos</div>
                <div style="font-size:11px;color:var(--ink-400);margin-top:3px">or drag & drop images here</div>
              </div>
              <div id="upload-preview" style="display:none;text-align:left"></div>
            </div>
            <input type="file" id="file-input" name="attachments[]" multiple
                   accept="image/jpeg,image/png,image/gif,image/webp"
                   style="display:none" onchange="previewFiles(this)">
          </div>

        </div>
      </div>

    </div><!-- /left col -->

    <!-- ── RIGHT COLUMN ── -->
    <div>

      <!-- Your Information — READ ONLY -->
      <div class="card mb16">
        <div class="card-hdr">
          <span class="card-title">👤 Your Information</span>
          <span style="display:flex;align-items:center;gap:4px;font-size:11px;color:var(--ink-400)">
            <svg width="12" height="12" viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><rect x="2" y="6" width="8" height="5" rx="1"/><path d="M4 6V4a2 2 0 0 1 4 0v2"/></svg>
            Read-only
          </span>
        </div>
        <div class="card-body">
          <div class="fg">
            <label>Full Name</label>
            <input type="text" value="<?= e($user['name'] ?? '') ?>" readonly
                   style="background:var(--surface);color:var(--ink-500);cursor:not-allowed;border-color:var(--ink-50)">
          </div>
          <div class="fg" style="margin-bottom:0">
            <label>Contact Number</label>
            <input type="text"
                   value="<?= e($my_contact ?: 'Not set') ?>"
                   readonly
                   style="background:var(--surface);color:var(--ink-500);cursor:not-allowed;border-color:var(--ink-50)">
            <?php if (!$my_contact): ?>
              <div style="font-size:11px;color:var(--amber-600);margin-top:5px">
                ⚠️ <a href="?page=profile" style="color:var(--amber-600);font-weight:600;text-decoration:none">Add a contact number in My Profile →</a>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- Important notice -->
      <div class="card mb16" style="border-left:3px solid var(--amber-400)">
        <div class="card-body" style="padding:14px 16px">
          <div style="font-size:12px;font-weight:700;color:var(--amber-600);margin-bottom:8px">⚠️ IMPORTANT NOTICE</div>
          <p style="font-size:12px;color:var(--ink-600);line-height:1.75">
            Filing a false report is a punishable offense under the <em>Katarungang Pambarangay Law</em>. Ensure all information provided is accurate and truthful.
          </p>
        </div>
      </div>

      <!-- Submit button -->
      <div class="card">
        <div class="card-body">
          <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;padding:11px;font-size:14px">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><path d="M2 8l4.5 4.5L14 3"/></svg>
            Submit Report
          </button>
          <p style="font-size:11px;color:var(--ink-400);text-align:center;margin-top:10px">
            Your report will be reviewed by a barangay officer within 24 hours
          </p>
        </div>
      </div>

    </div><!-- /right col -->
  </div><!-- /g21 -->
</form>
<?php endif; ?>

<!-- ══════════ STYLES ══════════ -->
<style>
#upload-zone:hover  { border-color:var(--green-400);background:var(--green-50); }
#upload-zone.drag   { border-color:var(--green-500);background:var(--green-50); }
.prev-wrap { display:flex;flex-wrap:wrap;gap:8px;padding-top:4px; }
.prev-thumb {
  position:relative;width:72px;height:72px;border-radius:var(--r-sm);
  overflow:hidden;border:1px solid var(--ink-100);background:var(--surface-2);flex-shrink:0;
}
.prev-thumb img  { width:100%;height:100%;object-fit:cover; }
.prev-thumb .rm  {
  position:absolute;top:2px;right:2px;width:18px;height:18px;
  border-radius:50%;background:rgba(0,0,0,.65);color:#fff;
  border:none;cursor:pointer;font-size:13px;line-height:1;
  display:flex;align-items:center;justify-content:center;transition:background .12s;
}
.prev-thumb .rm:hover { background:rgba(190,18,60,.85); }
.prev-add {
  width:72px;height:72px;border-radius:var(--r-sm);
  border:2px dashed var(--ink-200);display:flex;align-items:center;
  justify-content:center;cursor:pointer;flex-shrink:0;background:var(--surface);
  transition:border-color .12s,background .12s;
}
.prev-add:hover { border-color:var(--green-400);background:var(--green-50); }

/* Leaflet z-index inside card */
#incident-map .leaflet-pane    { z-index:1 !important; }
#incident-map .leaflet-top,
#incident-map .leaflet-bottom  { z-index:2 !important; }

/* "Other" field reveal animation */
#other-type-wrap { animation: fadeSlideDown .18s ease; }
@keyframes fadeSlideDown {
  from { opacity:0; transform:translateY(-6px); }
  to   { opacity:1; transform:translateY(0); }
}

/* Map wrap slide-in */
#map-wrap { animation: fadeSlideDown .2s ease; }
</style>

<!-- ══════════ SCRIPTS ══════════ -->
<script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.min.js" crossorigin=""></script>
<script>
// ── Data from PHP ──
const SEVERITY_MAP  = <?= json_encode($severity_map) ?>;
const SEVERITY_INFO = <?= json_encode($severity_info) ?>;
const BGY_LAT       = <?= json_encode($bgy_lat) ?>;
const BGY_LNG       = <?= json_encode($bgy_lng) ?>;

// ═════════════════════════════════════════
// 1. INCIDENT TYPE — severity + Other field
// ═════════════════════════════════════════
function autoSeverity(type) {
  const card = document.getElementById('severity-card');
  if (!type) { card.style.display = 'none'; return; }
  const sev  = SEVERITY_MAP[type] || 'minor';
  const info = SEVERITY_INFO[sev];  // [label, desc, color, emoji]
  document.getElementById('violation-level-input').value = sev;
  document.getElementById('sev-emoji').textContent = info[3];
  document.getElementById('sev-label').textContent = info[0];
  document.getElementById('sev-label').style.color = info[2];
  document.getElementById('sev-desc').textContent  = info[1];
  const card_el = document.getElementById('severity-card');
  card_el.style.borderColor = info[2] + '55';
  card_el.style.background  = info[2] + '11';
  card_el.style.display     = 'block';
}

function toggleOtherField(type) {
  const wrap  = document.getElementById('other-type-wrap');
  const input = document.getElementById('incident-type-other');
  if (type === 'Other') {
    wrap.style.display = '';
    input.required     = true;
    input.focus();
  } else {
    wrap.style.display = 'none';
    input.required     = false;
    input.value        = '';
  }
}

// Restore on re-render after validation error
(function () {
  const sel = document.getElementById('incident-type-sel');
  if (sel && sel.value) {
    autoSeverity(sel.value);
    toggleOtherField(sel.value);
  }
})();

// ═════════════════════════════════════════
// 2. BARANGAY CHANGE HANDLER
// ═════════════════════════════════════════
// ═════════════════════════════════════════
// 3. REPORT TYPE TOGGLE
// ═════════════════════════════════════════
function setType(type) {
  document.getElementById('report_type').value = type;
  const card = document.getElementById('respondent-card');
  const btnP = document.getElementById('btn-person');
  const btnI = document.getElementById('btn-incident');
  const fix  = b => { b.style.flex='1'; b.style.justifyContent='center'; b.style.borderRadius='var(--r-lg)'; };
  if (type === 'person') {
    card.style.display = '';
    btnP.className = 'btn btn-primary'; btnI.className = 'btn btn-outline';
  } else {
    card.style.display = 'none';
    btnI.className = 'btn btn-primary'; btnP.className = 'btn btn-outline';
  }
  fix(btnP); fix(btnI);
}

// ═════════════════════════════════════════
// 4. LEAFLET MAP
// ═════════════════════════════════════════
let map = null, marker = null, mapVisible = false;
let geocodeTimer = null, streetTimer = null;

function toggleMap() {
  const wrap = document.getElementById('map-wrap');
  mapVisible  = !mapVisible;
  wrap.style.display = mapVisible ? '' : 'none';
  document.getElementById('map-toggle-label').textContent =
    mapVisible ? '🗺 Hide Map' : '📌 Pin on Map (optional)';

  if (mapVisible && !map)   initMap();
  if (mapVisible && map)    setTimeout(() => map.invalidateSize(), 60);
}

function makePinIcon() {
  return L.divIcon({
    className: '',
    html: `<div style="width:32px;height:40px;filter:drop-shadow(0 3px 6px rgba(0,0,0,.4))">
      <svg viewBox="0 0 32 40" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M16 0C8.27 0 2 6.27 2 14c0 9.75 14 26 14 26S30 23.75 30 14C30 6.27 23.73 0 16 0z" fill="#BE123C"/>
        <circle cx="16" cy="14" r="6" fill="white"/>
      </svg>
    </div>`,
    iconSize:   [32, 40],
    iconAnchor: [16, 40],
    popupAnchor:[0,  -40]
  });
}

function initMap() {
  const savedLat = parseFloat(document.getElementById('incident_lat').value);
  const savedLng = parseFloat(document.getElementById('incident_lng').value);
  const hasPin   = !isNaN(savedLat) && !isNaN(savedLng);
  const cLat     = hasPin ? savedLat : BGY_LAT;
  const cLng     = hasPin ? savedLng : BGY_LNG;

  map = L.map('incident-map', { zoomControl: true }).setView([cLat, cLng], hasPin ? 17 : 15);

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
    maxZoom: 19
  }).addTo(map);

  map.on('click', function (e) {
    dropPin(e.latlng.lat, e.latlng.lng);
  });

  if (hasPin) dropPin(savedLat, savedLng, false);
}

function dropPin(lat, lng, doGeocode) {
  if (marker) {
    marker.setLatLng([lat, lng]);
  } else {
    marker = L.marker([lat, lng], { icon: makePinIcon(), draggable: true }).addTo(map);
    marker.on('dragend', function (e) {
      const p = e.target.getLatLng();
      saveCoords(p.lat, p.lng);
      reverseGeocode(p.lat, p.lng);
    });
  }
  saveCoords(lat, lng);
  setPinVisible(true);
  if (doGeocode !== false) reverseGeocode(lat, lng);
}

function clearPin() {
  if (marker) { marker.remove(); marker = null; }
  document.getElementById('incident_lat').value = '';
  document.getElementById('incident_lng').value = '';
  setPinVisible(false);
  document.getElementById('geocode-status').textContent = '';
}

function saveCoords(lat, lng) {
  document.getElementById('incident_lat').value = lat.toFixed(7);
  document.getElementById('incident_lng').value = lng.toFixed(7);
}

function setPinVisible(v) {
  document.getElementById('pin-indicator').style.display  = v ? '' : 'none';
  document.getElementById('clear-pin-btn').style.display  = v ? '' : 'none';
}

// Reverse geocode via Nominatim (pin → street field)
function reverseGeocode(lat, lng) {
  const barangaySelect = document.getElementById('incident-barangay');
  const selectedBarangay = barangaySelect.value;
  
  setGeoStatus('🔄 Looking up address…');
  clearTimeout(geocodeTimer);
  geocodeTimer = setTimeout(function () {
    fetch(`https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=${lat}&lon=${lng}&addressdetails=1`, {
      headers: { 'Accept-Language': 'en' }
    })
    .then(r => r.json())
    .then(data => {
      if (data && data.address) {
        const a     = data.address;
        const house = a.house_number ? a.house_number + ' ' : '';
        const road  = a.road || a.pedestrian || a.footway || a.path || a.suburb || a.neighbourhood || '';
        let street  = (house + road).trim();
        if (!street) street = data.display_name.split(',')[0];
        if (street) document.getElementById('incident-street').value = street;
        setGeoStatus('✅ Street updated from pin. Adjust if needed.');
      } else {
        setGeoStatus('⚠️ Address not found — type the street manually.');
      }
    })
    .catch(() => setGeoStatus('⚠️ Geocoding failed — please enter the street manually.'));
  }, 400);
}

// Forward geocode (street field → map pin), debounced 700ms
function onStreetInput() {
  if (!mapVisible || !map) return;
  const street = document.getElementById('incident-street').value.trim();
  const barangaySelect = document.getElementById('incident-barangay');
  const selectedBarangay = barangaySelect.value;
  
  if (street.length < 4 || !selectedBarangay) return;

  clearTimeout(streetTimer);
  streetTimer = setTimeout(function () {
    const q = encodeURIComponent(street + ', ' + selectedBarangay + ', Philippines');
    fetch(`https://nominatim.openstreetmap.org/search?format=jsonv2&q=${q}&limit=1`, {
      headers: { 'Accept-Language': 'en' }
    })
    .then(r => r.json())
    .then(results => {
      if (results && results.length > 0) {
        const lat = parseFloat(results[0].lat);
        const lng = parseFloat(results[0].lon);
        dropPin(lat, lng, false);      // place/move pin without triggering reverse geocode
        map.setView([lat, lng], 17);
        setGeoStatus('📍 Map updated from street address.');
      }
    })
    .catch(() => {}); // silent fail for forward geocode
  }, 700);
}

function setGeoStatus(msg) {
  const el = document.getElementById('geocode-status');
  if (el) el.textContent = msg;
}

// ═════════════════════════════════════════
// 5. FILE UPLOAD / PREVIEW
// ═════════════════════════════════════════
let selectedFiles = [];

function previewFiles(input) {
  const added = Array.from(input.files).filter(f => f.type.startsWith('image/'));
  selectedFiles = [...selectedFiles, ...added].slice(0, 5);
  input.value = '';
  renderPreviews();
}

function removeFile(i) {
  selectedFiles.splice(i, 1);
  renderPreviews();
}

function renderPreviews() {
  const placeholder = document.getElementById('upload-placeholder');
  const preview     = document.getElementById('upload-preview');

  if (!selectedFiles.length) {
    placeholder.style.display = '';
    preview.style.display     = 'none';
    preview.innerHTML         = '';
    syncFileInput();
    return;
  }

  placeholder.style.display = 'none';
  preview.style.display     = 'block';

  let html = '<div class="prev-wrap">';
  selectedFiles.forEach((f, i) => {
    html += `<div class="prev-thumb">
      <img src="${URL.createObjectURL(f)}" alt="${esc(f.name)}">
      <button type="button" class="rm" onclick="removeFile(${i})">×</button>
    </div>`;
  });
  if (selectedFiles.length < 5) {
    html += `<div class="prev-add" onclick="document.getElementById('file-input').click()" title="Add more photos">
      <svg width="22" height="22" viewBox="0 0 22 22" fill="none" stroke="var(--ink-300)" stroke-width="1.6" stroke-linecap="round"><path d="M11 5v12M5 11h12"/></svg>
    </div>`;
  }
  html += '</div>';
  preview.innerHTML = html;
  syncFileInput();
}

function syncFileInput() {
  const dt = new DataTransfer();
  selectedFiles.forEach(f => dt.items.add(f));
  document.getElementById('file-input').files = dt.files;
}

function esc(s) {
  return String(s).replace(/[&<>"']/g,
    c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}

// Drag & drop on upload zone
(function () {
  const zone = document.getElementById('upload-zone');
  if (!zone) return;
  zone.addEventListener('dragover',  ev => { ev.preventDefault(); zone.classList.add('drag'); });
  zone.addEventListener('dragleave', ()  => zone.classList.remove('drag'));
  zone.addEventListener('drop', ev => {
    ev.preventDefault(); zone.classList.remove('drag');
    const dropped = Array.from(ev.dataTransfer.files).filter(f => f.type.startsWith('image/'));
    selectedFiles = [...selectedFiles, ...dropped].slice(0, 5);
    renderPreviews();
  });
})();

// ═════════════════════════════════════════
// 6. RESPONDENT LIVE SEARCH
// ═════════════════════════════════════════
// The dropdown is a fixed-position element moved to <body> on first use
// so it is never clipped by any parent card/overflow/stacking context.

let respTimer    = null;
let respResults  = [];
let respFocusIdx = -1;
let respLinked   = false;

// Move dropdown to <body> once on page load so it escapes all card clipping
(function () {
  const dd = document.getElementById('resp-dropdown');
  if (dd && dd.parentElement !== document.body) {
    document.body.appendChild(dd);
  }
})();

// Position the fixed dropdown directly under the input
function positionRespDropdown() {
  const input = document.getElementById('resp-search-input');
  const dd    = document.getElementById('resp-dropdown');
  if (!input || !dd) return;
  const rect = input.getBoundingClientRect();
  dd.style.top   = (rect.bottom + window.scrollY + 2) + 'px';
  dd.style.left  = (rect.left  + window.scrollX)     + 'px';
  dd.style.width = rect.width + 'px';
}

// Reposition on scroll/resize so it stays aligned
window.addEventListener('scroll', () => {
  if (document.getElementById('resp-dropdown')?.style.display !== 'none') positionRespDropdown();
}, true);
window.addEventListener('resize', () => {
  if (document.getElementById('resp-dropdown')?.style.display !== 'none') positionRespDropdown();
});

function onRespFocus() {
  // Re-trigger search if input already has text (e.g. user clicks back into field)
  const val = document.getElementById('resp-search-input')?.value?.trim();
  if (!respLinked && val && val.length >= 2 && !respResults.length) {
    doRespSearch(val);
  }
}

function onRespInput(val) {
  if (respLinked) unlinkRespondent(false);
  clearTimeout(respTimer);
  const q = val.trim();
  if (q.length < 2) { hideRespDropdown(); showRespSpinner(false); return; }
  showRespSpinner(true);
  respTimer = setTimeout(() => doRespSearch(q), 300);
}

function doRespSearch(q) {
  fetch('ajax/search_users.php?q=' + encodeURIComponent(q))
    .then(r => { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
    .then(data => {
      showRespSpinner(false);
      respResults  = (data.success && data.results) ? data.results : [];
      respFocusIdx = -1;
      renderRespDropdown(q);
    })
    .catch(err => { showRespSpinner(false); console.warn('Respondent search:', err); hideRespDropdown(); });
}

function renderRespDropdown(query) {
  const dd = document.getElementById('resp-dropdown');
  if (!dd) return;
  positionRespDropdown();

  if (!respResults.length) {
    dd.innerHTML = `
      <div style="padding:11px 14px;font-size:12px;color:var(--ink-400);
                  display:flex;align-items:center;gap:8px">
        <svg width="13" height="13" viewBox="0 0 13 13" fill="none" stroke="currentColor"
             stroke-width="1.6" stroke-linecap="round">
          <circle cx="6" cy="6" r="5"/><path d="M9 9l2 2"/>
        </svg>
        No registered user found for "<strong>${esc(query)}</strong>"
        — name will be saved as typed.
      </div>`;
    dd.style.display = 'block';
    return;
  }

  const safe = query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
  const re   = new RegExp('(' + safe + ')', 'gi');

  dd.innerHTML = respResults.map((u, i) => {
    const hi = esc(u.name).replace(re,
      '<mark style="background:var(--amber-100,#fef3c7);color:inherit;border-radius:2px;padding:0 1px">$1</mark>');
    return `
      <div class="resp-item" data-idx="${i}"
           onmousedown="selectRespondent(${i})"
           onmouseover="setRespFocus(${i})"
           style="display:flex;align-items:center;justify-content:space-between;
                  padding:9px 14px;cursor:pointer;font-size:13px;
                  border-bottom:1px solid var(--ink-50,#f8fafc);transition:background .1s">
        <span>${hi}</span>
        <span style="font-size:10px;font-weight:700;color:var(--green-600);background:var(--green-50);
                     border:1px solid var(--green-200);border-radius:20px;
                     padding:2px 8px;white-space:nowrap;flex-shrink:0">
          Registered ✓
        </span>
      </div>`;
  }).join('');

  dd.style.display = 'block';
}

function onRespKeydown(e) {
  const dd = document.getElementById('resp-dropdown');
  if (!dd || dd.style.display === 'none') return;
  if (e.key === 'ArrowDown') {
    e.preventDefault(); setRespFocus(Math.min(respFocusIdx + 1, respResults.length - 1));
  } else if (e.key === 'ArrowUp') {
    e.preventDefault(); setRespFocus(Math.max(respFocusIdx - 1, 0));
  } else if (e.key === 'Enter' && respFocusIdx >= 0) {
    e.preventDefault(); selectRespondent(respFocusIdx);
  } else if (e.key === 'Escape') {
    hideRespDropdown();
  }
}

function setRespFocus(idx) {
  respFocusIdx = idx;
  document.querySelectorAll('.resp-item').forEach((el, i) => {
    el.style.background = i === idx ? 'var(--green-50,#f0fdf4)' : '';
  });
}

function selectRespondent(idx) {
  const u = respResults[idx];
  if (!u) return;
  document.getElementById('respondent_user_id').value        = u.id;
  document.getElementById('resp-search-input').value         = u.name;
  document.getElementById('resp-linked-name').textContent    = u.name;
  document.getElementById('resp-linked-badge').style.display = 'flex';
  document.getElementById('resp-search-input').style.display = 'none';
  respLinked = true;
  hideRespDropdown();
}

function unlinkRespondent(clearText) {
  document.getElementById('respondent_user_id').value        = '';
  document.getElementById('resp-linked-badge').style.display = 'none';
  const inp = document.getElementById('resp-search-input');
  inp.style.display = '';
  if (clearText !== false) inp.value = '';
  inp.focus();
  respLinked = false;
}

function hideRespDropdown() {
  const dd = document.getElementById('resp-dropdown');
  if (dd) { dd.style.display = 'none'; dd.innerHTML = ''; }
  respResults  = [];
  respFocusIdx = -1;
}

function showRespSpinner(show) {
  const s = document.getElementById('resp-spinner');
  if (s) s.style.display = show ? 'block' : 'none';
}

// Close when clicking outside
document.addEventListener('mousedown', function (e) {
  const wrap = document.getElementById('resp-wrap');
  const dd   = document.getElementById('resp-dropdown');
  if (!wrap?.contains(e.target) && !dd?.contains(e.target)) hideRespDropdown();
});

// Restore linked state after validation-error re-render
(function () {
  const storedId   = document.getElementById('respondent_user_id')?.value;
  const storedName = document.getElementById('resp-search-input')?.value?.trim();
  if (storedId && storedName) {
    respResults = [{ id: parseInt(storedId), name: storedName }];
    selectRespondent(0);
  }
})();
</script>

<style>
@keyframes resp-spin { to { transform: rotate(360deg); } }
.resp-item:last-child { border-bottom: none !important; }
.resp-item:hover      { background: var(--green-50, #f0fdf4) !important; }
</style>