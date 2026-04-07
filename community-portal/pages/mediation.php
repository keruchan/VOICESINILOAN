<?php
// community-portal/pages/mediation.php
$uid = (int)$user['id'];
$bid = (int)$user['barangay_id'];

// ── Filters ───────────────────────────────────────────────────────────────────
$f_search = trim($_GET['ms_search'] ?? '');
$f_status = $_GET['ms_status']      ?? '';    // upcoming | past
$f_role   = $_GET['ms_role']        ?? '';    // complainant | respondent

// ── Fetch all hearings where user is complainant OR respondent ─────────────────
// We use UNION to cover both sides then sort in PHP (avoids complex subquery)
$all_hearings = [];
try {
    // Side 1: user is complainant
    $s1 = $pdo->prepare("
        SELECT
            ms.id          AS med_id,
            ms.hearing_date,
            ms.hearing_time,
            ms.venue,
            ms.status      AS med_status,
            ms.outcome,
            ms.complainant_attended,
            ms.respondent_attended,
            ms.notes,
            b.id           AS blotter_id,
            b.case_number,
            b.incident_type,
            b.violation_level,
            b.complainant_name,
            b.respondent_name,
            b.status       AS blotter_status,
            'complainant'  AS my_role
        FROM mediation_schedules ms
        JOIN blotters b ON b.id = ms.blotter_id
        WHERE b.complainant_user_id = ?
    ");
    $s1->execute([$uid]);
    $rows1 = $s1->fetchAll(PDO::FETCH_ASSOC);

    // Side 2: user is respondent (tagged via respondent_user_id)
    $s2 = $pdo->prepare("
        SELECT
            ms.id          AS med_id,
            ms.hearing_date,
            ms.hearing_time,
            ms.venue,
            ms.status      AS med_status,
            ms.outcome,
            ms.complainant_attended,
            ms.respondent_attended,
            ms.notes,
            b.id           AS blotter_id,
            b.case_number,
            b.incident_type,
            b.violation_level,
            b.complainant_name,
            b.respondent_name,
            b.status       AS blotter_status,
            'respondent'   AS my_role
        FROM mediation_schedules ms
        JOIN blotters b ON b.id = ms.blotter_id
        WHERE b.respondent_user_id = ?
    ");
    $s2->execute([$uid]);
    $rows2 = $s2->fetchAll(PDO::FETCH_ASSOC);

    // Merge — deduplicate by med_id (edge case: user is both complainant & respondent)
    $seen = [];
    foreach (array_merge($rows1, $rows2) as $row) {
        $key = $row['med_id'];
        if (!isset($seen[$key])) {
            $seen[$key] = $row;
        }
        // If same mediation appears in both, prefer 'complainant' label
        elseif ($row['my_role'] === 'complainant') {
            $seen[$key]['my_role'] = 'complainant';
        }
    }
    $all_hearings = array_values($seen);

    // Sort: upcoming first by date ASC, then past by date DESC
    usort($all_hearings, function($a, $b) {
        $today = date('Y-m-d');
        $aFuture = ($a['hearing_date'] >= $today && $a['med_status'] === 'scheduled');
        $bFuture = ($b['hearing_date'] >= $today && $b['med_status'] === 'scheduled');
        if ($aFuture && !$bFuture) return -1;
        if (!$aFuture && $bFuture) return  1;
        if ($aFuture)  return strcmp($a['hearing_date'], $b['hearing_date']);  // ASC for upcoming
        return strcmp($b['hearing_date'], $a['hearing_date']);                 // DESC for past
    });

} catch (PDOException $e) {
    error_log('mediation.php: ' . $e->getMessage());
}

// ── Apply filters ─────────────────────────────────────────────────────────────
$today = date('Y-m-d');
$filtered = array_filter($all_hearings, function($h) use ($f_search, $f_status, $f_role, $today) {
    // Role filter
    if ($f_role && $h['my_role'] !== $f_role) return false;

    // Status filter (upcoming = scheduled & future, past = everything else)
    $isUpcoming = ($h['med_status'] === 'scheduled' && $h['hearing_date'] >= $today);
    if ($f_status === 'upcoming' && !$isUpcoming) return false;
    if ($f_status === 'past'     &&  $isUpcoming) return false;

    // Search: case number, incident type, names, venue
    if ($f_search) {
        $hay = strtolower(implode(' ', [
            $h['case_number'], $h['incident_type'],
            $h['complainant_name'], $h['respondent_name'] ?? '',
            $h['venue'] ?? '',
        ]));
        if (strpos($hay, strtolower($f_search)) === false) return false;
    }

    return true;
});
$filtered = array_values($filtered);

// ── Split for display ─────────────────────────────────────────────────────────
$upcoming = array_values(array_filter($filtered, fn($h) =>
    $h['med_status'] === 'scheduled' && $h['hearing_date'] >= $today));
$past = array_values(array_filter($filtered, fn($h) =>
    !($h['med_status'] === 'scheduled' && $h['hearing_date'] >= $today)));

// ── Summary counts (unfiltered, for tabs) ─────────────────────────────────────
$cnt_upcoming = count(array_filter($all_hearings, fn($h) =>
    $h['med_status'] === 'scheduled' && $h['hearing_date'] >= $today));
$cnt_past = count(array_filter($all_hearings, fn($h) =>
    !($h['med_status'] === 'scheduled' && $h['hearing_date'] >= $today)));
$cnt_as_comp = count(array_filter($all_hearings, fn($h) => $h['my_role'] === 'complainant'));
$cnt_as_resp = count(array_filter($all_hearings, fn($h) => $h['my_role'] === 'respondent'));

$lm = ['minor'=>'ch-green','moderate'=>'ch-amber','serious'=>'ch-rose','critical'=>'ch-violet'];
$sc = ['completed'=>'ch-green','cancelled'=>'ch-rose','missed'=>'ch-amber','rescheduled'=>'ch-navy','scheduled'=>'ch-teal'];

// Helper to build URL preserving filters
function mq(array $o = []): string {
    $base = array_filter([
        'page'      => 'mediation',
        'ms_search' => $_GET['ms_search'] ?? '',
        'ms_status' => $_GET['ms_status'] ?? '',
        'ms_role'   => $_GET['ms_role']   ?? '',
    ], fn($v) => $v !== '');
    return '?' . http_build_query(array_merge($base, $o));
}
?>

<div class="page-hdr">
  <div class="page-hdr-left">
    <h2>Mediation Schedule</h2>
    <p>All hearings related to your cases</p>
  </div>
</div>

<!-- ── Summary chips ───────────────────────────────────────────────────────── -->
<div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px">
  <span style="font-size:12px;color:var(--ink-500);display:flex;align-items:center;gap:5px;
               background:var(--green-50);border:1px solid var(--green-200);
               border-radius:var(--r-lg);padding:4px 12px">
    📅 <strong><?= $cnt_upcoming ?></strong> upcoming
  </span>
  <span style="font-size:12px;color:var(--ink-500);display:flex;align-items:center;gap:5px;
               background:var(--surface-2);border:1px solid var(--ink-100);
               border-radius:var(--r-lg);padding:4px 12px">
    🕐 <strong><?= $cnt_past ?></strong> past
  </span>
  <?php if ($cnt_as_resp > 0): ?>
  <span style="font-size:12px;color:var(--rose-600);display:flex;align-items:center;gap:5px;
               background:var(--rose-50);border:1px solid var(--rose-200);
               border-radius:var(--r-lg);padding:4px 12px">
    ⚠️ <strong><?= $cnt_as_resp ?></strong> as respondent
  </span>
  <?php endif; ?>
</div>

<!-- ── Filter bar ──────────────────────────────────────────────────────────── -->
<form method="GET" style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin-bottom:18px">
  <input type="hidden" name="page" value="mediation">

  <!-- Search -->
  <div class="inp-icon" style="flex:1;min-width:200px;max-width:300px">
    <svg width="14" height="14" viewBox="0 0 14 14" fill="none" stroke="currentColor"
         stroke-width="1.5" stroke-linecap="round">
      <circle cx="6" cy="6" r="4"/><path d="M11 11l-2.5-2.5"/>
    </svg>
    <input type="search" name="ms_search"
           placeholder="Case no., type, name, venue…"
           value="<?= e($f_search) ?>">
  </div>

  <!-- Status filter -->
  <select name="ms_status" onchange="this.form.submit()"
          style="font-size:13px;padding:7px 10px;border:1px solid var(--ink-100);
                 border-radius:var(--r-md);background:var(--surface);cursor:pointer">
    <option value=""      <?= !$f_status      ? 'selected':'' ?>>All Hearings</option>
    <option value="upcoming" <?= $f_status==='upcoming' ? 'selected':'' ?>>
      Upcoming (<?= $cnt_upcoming ?>)
    </option>
    <option value="past"  <?= $f_status==='past'  ? 'selected':'' ?>>
      Past (<?= $cnt_past ?>)
    </option>
  </select>

  <!-- Role filter -->
  <select name="ms_role" onchange="this.form.submit()"
          style="font-size:13px;padding:7px 10px;border:1px solid var(--ink-100);
                 border-radius:var(--r-md);background:var(--surface);cursor:pointer">
    <option value=""             <?= !$f_role             ? 'selected':'' ?>>All Roles</option>
    <option value="complainant"  <?= $f_role==='complainant' ? 'selected':'' ?>>
      As Complainant (<?= $cnt_as_comp ?>)
    </option>
    <option value="respondent"   <?= $f_role==='respondent'  ? 'selected':'' ?>>
      As Respondent (<?= $cnt_as_resp ?>)
    </option>
  </select>

  <button type="submit" class="btn btn-outline btn-sm">Search</button>

  <?php if ($f_search || $f_status || $f_role): ?>
  <a href="?page=mediation" class="btn btn-ghost btn-sm">Clear</a>
  <?php endif; ?>
</form>

<?php if (empty($filtered)): ?>
<!-- ── Empty state ─────────────────────────────────────────────────────────── -->
<div class="empty-state">
  <div class="es-icon">📅</div>
  <div class="es-title"><?= ($f_search || $f_status || $f_role) ? 'No hearings match your filters' : 'No hearings yet' ?></div>
  <div class="es-sub">
    <?= ($f_search || $f_status || $f_role)
        ? '<a href="?page=mediation">Clear filters</a> to see all hearings'
        : 'Your barangay will notify you when a hearing is scheduled' ?>
  </div>
</div>

<?php else: ?>

<!-- ── UPCOMING hearings (cards) ───────────────────────────────────────────── -->
<?php if (!empty($upcoming)): ?>
<div style="font-size:11px;font-weight:700;letter-spacing:.05em;text-transform:uppercase;
            color:var(--ink-400);margin-bottom:12px">
  📅 UPCOMING HEARINGS
  <span style="font-size:10px;background:var(--green-50);border:1px solid var(--green-200);
               color:var(--green-700);border-radius:10px;padding:1px 7px;margin-left:4px">
    <?= count($upcoming) ?>
  </span>
</div>
<div class="g2 mb22">
  <?php foreach ($upcoming as $h): ?>
  <div class="card" style="border-top:3px solid <?= $h['my_role']==='respondent' ? 'var(--amber-400)' : 'var(--green-500)' ?>">
    <div class="card-body" style="padding:16px 18px">

      <!-- Case + Role badge -->
      <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:10px">
        <div>
          <div style="font-size:15px;font-weight:700;color:var(--ink-900)"><?= e($h['case_number']) ?></div>
          <div style="font-size:12px;color:var(--ink-400)"><?= e($h['incident_type']) ?></div>
        </div>
        <div style="display:flex;flex-direction:column;align-items:flex-end;gap:4px">
          <span class="chip <?= $lm[$h['violation_level']]??'ch-slate' ?>"><?= ucfirst($h['violation_level']) ?></span>
          <?php if ($h['my_role']==='respondent'): ?>
          <span class="chip ch-amber" style="font-size:10px">⚠️ You are the respondent</span>
          <?php else: ?>
          <span class="chip ch-teal" style="font-size:10px">👤 You are the complainant</span>
          <?php endif; ?>
        </div>
      </div>

      <!-- Date / Time / Venue -->
      <div style="background:<?= $h['my_role']==='respondent' ? 'var(--amber-50)' : 'var(--green-50)' ?>;
                  border:1px solid <?= $h['my_role']==='respondent' ? 'var(--amber-200)' : 'var(--green-100)' ?>;
                  border-radius:var(--r-md);padding:12px;margin-bottom:12px">
        <div style="font-size:18px;font-weight:700;color:<?= $h['my_role']==='respondent' ? 'var(--amber-700)' : 'var(--green-700)' ?>">
          <?= date('D, M j, Y', strtotime($h['hearing_date'])) ?>
        </div>
        <?php if ($h['hearing_time']): ?>
        <div style="font-size:14px;font-weight:600;margin-top:2px;
                    color:<?= $h['my_role']==='respondent' ? 'var(--amber-600)' : 'var(--green-600)' ?>">
          <?= date('g:i A', strtotime($h['hearing_time'])) ?>
        </div>
        <?php endif; ?>
        <div style="font-size:12px;color:var(--ink-500);margin-top:4px">
          📍 <?= e($h['venue'] ?: 'Barangay Hall') ?>
        </div>
      </div>

      <!-- Parties -->
      <div class="dr">
        <span class="dr-lbl">Complainant</span>
        <span class="dr-val <?= $h['my_role']==='complainant' ? '' : '' ?>">
          <?= e($h['complainant_name']) ?>
          <?php if ($h['my_role']==='complainant'): ?>
          <span style="font-size:10px;color:var(--green-600);font-weight:600"> (you)</span>
          <?php endif; ?>
        </span>
      </div>
      <div class="dr">
        <span class="dr-lbl">Respondent</span>
        <span class="dr-val">
          <?= e($h['respondent_name'] ?: 'Unknown') ?>
          <?php if ($h['my_role']==='respondent'): ?>
          <span style="font-size:10px;color:var(--amber-600);font-weight:600"> (you)</span>
          <?php endif; ?>
        </span>
      </div>

      <?php if ($h['notes']): ?>
      <div style="font-size:12px;color:var(--ink-500);background:var(--surface-2);
                  border-radius:var(--r-sm);padding:8px 10px;margin-top:8px;line-height:1.5">
        📝 <?= e($h['notes']) ?>
      </div>
      <?php endif; ?>

    </div>
    <div class="card-foot">
      <button class="act-btn" onclick="viewBlotter(<?= $h['blotter_id'] ?>)">View Case Details</button>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- ── PAST hearings (table) ───────────────────────────────────────────────── -->
<?php if (!empty($past)): ?>
<div style="font-size:11px;font-weight:700;letter-spacing:.05em;text-transform:uppercase;
            color:var(--ink-400);margin-bottom:12px">
  🕐 PAST HEARINGS
  <span style="font-size:10px;background:var(--surface-2);border:1px solid var(--ink-100);
               color:var(--ink-500);border-radius:10px;padding:1px 7px;margin-left:4px">
    <?= count($past) ?>
  </span>
</div>
<div class="card">
  <div class="tbl-wrap">
    <table>
      <thead>
        <tr>
          <th>Case No.</th>
          <th>Type</th>
          <th>Date</th>
          <th>Your Role</th>
          <th>Status</th>
          <th>You Attended</th>
          <th>Outcome</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($past as $h): ?>
        <tr>
          <td class="td-mono"><?= e($h['case_number']) ?></td>
          <td style="font-size:12px"><?= e($h['incident_type']) ?></td>
          <td style="font-size:12px;color:var(--ink-400)"><?= date('M j, Y', strtotime($h['hearing_date'])) ?></td>
          <td>
            <?php if ($h['my_role']==='respondent'): ?>
            <span class="chip ch-amber" style="font-size:10px">⚠️ Respondent</span>
            <?php else: ?>
            <span class="chip ch-teal"  style="font-size:10px">👤 Complainant</span>
            <?php endif; ?>
          </td>
          <td>
            <span class="chip <?= $sc[$h['med_status']]??'ch-slate' ?>">
              <?= ucwords(str_replace('_',' ',$h['med_status'])) ?>
            </span>
          </td>
          <td>
            <?php
            // Show attendance for the user's role
            $attended = $h['my_role']==='respondent'
                ? $h['respondent_attended']
                : $h['complainant_attended'];
            if ($attended !== null): ?>
              <span class="chip <?= $attended?'ch-green':'ch-rose' ?>">
                <?= $attended ? 'Yes' : 'No' ?>
              </span>
            <?php else: ?>
              <span style="color:var(--ink-300)">—</span>
            <?php endif; ?>
          </td>
          <td style="font-size:12px;color:var(--ink-500);max-width:180px;white-space:normal;line-height:1.4">
            <?= e(mb_strimwidth($h['outcome']??'—', 0, 80, '…')) ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php endif; // end $filtered check ?>