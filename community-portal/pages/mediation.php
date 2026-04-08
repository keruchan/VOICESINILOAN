<?php // community-portal/pages/mediation.php
$uid = (int)$user['id'];
$bid = (int)$user['barangay_id'];

$upcoming = []; $past = [];
try {
    $upcoming = $pdo->query("
        SELECT
            ms.hearing_date,
            ms.hearing_time,
            ms.venue,
            ms.id AS med_id,
            b.id AS blotter_id,
            b.case_number,
            b.incident_type,
            b.violation_level,
            b.complainant_name,
            b.respondent_name
        FROM mediation_schedules ms
        JOIN blotters b ON b.id = ms.blotter_id
        WHERE
            (b.complainant_user_id = $uid OR b.respondent_user_id = $uid)
            AND ms.status = 'scheduled'
            AND TIMESTAMP(ms.hearing_date, ms.hearing_time) >= NOW()
        ORDER BY ms.hearing_date ASC, ms.hearing_time ASC
    ")->fetchAll();

    $past = $pdo->query("
        SELECT
            ms.hearing_date,
            ms.hearing_time,
            ms.venue,
            ms.status,
            ms.outcome,
            ms.complainant_attended,
            ms.respondent_attended,
            b.case_number,
            b.incident_type
        FROM mediation_schedules ms
        JOIN blotters b ON b.id = ms.blotter_id
        WHERE
            (b.complainant_user_id = $uid OR b.respondent_user_id = $uid)
            AND (
                ms.status != 'scheduled'
                OR TIMESTAMP(ms.hearing_date, ms.hearing_time) < NOW()
            )
        ORDER BY ms.hearing_date DESC, ms.hearing_time DESC
        LIMIT 20
    ")->fetchAll();
} catch (PDOException $e) {}

$lm = ['minor'=>'ch-green','moderate'=>'ch-amber','serious'=>'ch-rose','critical'=>'ch-violet'];
$sc = ['completed'=>'ch-green','cancelled'=>'ch-rose','missed'=>'ch-amber','rescheduled'=>'ch-navy'];
?>

<style>
.med-section-lbl {
    font-size:11px; font-weight:700; letter-spacing:.05em;
    text-transform:uppercase; color:var(--ink-400); margin-bottom:12px;
}
.mt16 { margin-top: 16px; }
</style>

<div class="page-hdr">
  <div class="page-hdr-left"><h2>Mediation Schedule</h2><p>All hearings related to your cases</p></div>
</div>

<!-- ══════ FILTER & SEARCH BAR ══════ -->
<div class="filter-bar mb16">
  <div class="inp-icon" style="flex:1;min-width:180px;max-width:300px">
    <svg width="14" height="14" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"><circle cx="6" cy="6" r="4"/><path d="M10 10l2.5 2.5"/></svg>
    <input type="search" id="med-search" placeholder="Search case no., type, name…" oninput="applyMedFilters()">
  </div>
  <select id="med-filter-section" onchange="applyMedFilters()">
    <option value="all">All Hearings</option>
    <option value="upcoming">Upcoming Only</option>
    <option value="past">Past Only</option>
  </select>
  <!-- <select id="med-filter-status" onchange="applyMedFilters()">
    <option value="">All Statuses</option>
    <option value="scheduled">Scheduled</option>
    <option value="completed">Completed</option>
    <option value="missed">Missed</option>
    <option value="cancelled">Cancelled</option>
    <option value="rescheduled">Rescheduled</option>
  </select> -->
  <select id="med-filter-attended" onchange="applyMedFilters()">
    <option value="">Attendance: Any</option>
    <option value="yes">I Attended</option>
    <option value="no">I Did Not Attend</option>
  </select>
  <button class="btn btn-outline btn-sm" onclick="clearMedFilters()">✕ Clear</button>
  <span id="med-count-label" style="font-size:12px;color:var(--ink-400);align-self:center;white-space:nowrap"></span>
</div>

<!-- ══════ GLOBAL NO-RESULTS ══════ -->
<div id="med-no-results" style="display:none">
  <div class="empty-state mb22">
    <div class="es-icon">🔍</div>
    <div class="es-title">No hearings match your filters</div>
    <div class="es-sub">Try adjusting the search or filter options.</div>
  </div>
</div>

<!-- ══════ UPCOMING HEARINGS ══════ -->
<?php if (!empty($upcoming)): ?>
<div id="section-upcoming">
  <div class="med-section-lbl" id="lbl-upcoming">UPCOMING HEARINGS</div>
  <div class="g2 mb22" id="upcoming-grid">
    <?php foreach ($upcoming as $h): ?>
    <div class="card med-card"
         data-section="upcoming"
         data-status="scheduled"
         data-case="<?= htmlspecialchars(strtolower($h['case_number']), ENT_QUOTES) ?>"
         data-type="<?= htmlspecialchars(strtolower($h['incident_type']), ENT_QUOTES) ?>"
         data-complainant="<?= htmlspecialchars(strtolower($h['complainant_name']), ENT_QUOTES) ?>"
         data-respondent="<?= htmlspecialchars(strtolower($h['respondent_name'] ?? ''), ENT_QUOTES) ?>"
         style="border-top:3px solid var(--teal-500)">
      <div class="card-body" style="padding:16px 18px">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:12px">
          <div>
            <div style="font-size:15px;font-weight:700;color:var(--ink-900)"><?= e($h['case_number']) ?></div>
            <div style="font-size:12px;color:var(--ink-400)"><?= e($h['incident_type']) ?></div>
          </div>
          <span class="chip <?= $lm[$h['violation_level']]??'ch-slate' ?>"><?= ucfirst($h['violation_level']) ?></span>
        </div>
        <div style="background:var(--teal-50);border:1px solid var(--teal-100);border-radius:var(--r-md);padding:12px;margin-bottom:12px">
          <div style="font-size:18px;font-weight:700;color:var(--teal-700)"><?= date('D, M j, Y', strtotime($h['hearing_date'])) ?></div>
          <?php if ($h['hearing_time']): ?>
          <div style="font-size:14px;color:var(--teal-600);font-weight:600;margin-top:2px"><?= date('g:i A', strtotime($h['hearing_time'])) ?></div>
          <?php endif; ?>
          <div style="font-size:12px;color:var(--ink-500);margin-top:4px">📍 <?= e($h['venue'] ?: 'Barangay Hall') ?></div>
        </div>
        <div class="dr"><span class="dr-lbl">Complainant</span><span class="dr-val"><?= e($h['complainant_name']) ?></span></div>
        <div class="dr"><span class="dr-lbl">Respondent</span><span class="dr-val"><?= e($h['respondent_name'] ?: 'Unknown') ?></span></div>
      </div>
      <div class="card-foot">
        <button class="act-btn" onclick="viewBlotter(<?= $h['blotter_id'] ?>)">View Case Details</button>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <!-- shown when search hides all upcoming cards -->
  <div id="upcoming-empty" style="display:none">
    <div class="empty-state mb22"><div class="es-icon">📅</div><div class="es-title">No upcoming hearings match</div></div>
  </div>
</div>
<?php else: ?>
<div id="section-upcoming" style="display:none">
  <div id="upcoming-empty" style="display:none"></div>
</div>
<?php if (empty($past)): ?>
<div class="empty-state mb22">
  <div class="es-icon">📅</div>
  <div class="es-title">No upcoming hearings</div>
  <div class="es-sub">Your barangay will notify you when a hearing is scheduled</div>
</div>
<?php endif; ?>
<?php endif; ?>

<!-- ══════ PAST HEARINGS ══════ -->
<?php if (!empty($past)): ?>
<div id="section-past">
  <div class="med-section-lbl" id="lbl-past">PAST HEARINGS</div>
  <div class="card">
    <div class="tbl-wrap">
      <table>
        <thead>
          <tr>
            <th>Case No.</th>
            <th>Type</th>
            <th>Date</th>
            <th>Status</th>
            <th>You Attended</th>
            <th>Outcome</th>
          </tr>
        </thead>
        <tbody id="past-tbody">
        <?php foreach ($past as $h):
          $attended_val = $h['complainant_attended'] === null ? '' : ($h['complainant_attended'] ? 'yes' : 'no');
        ?>
          <tr class="med-past-row"
              data-section="past"
              data-status="<?= htmlspecialchars($h['status'], ENT_QUOTES) ?>"
              data-case="<?= htmlspecialchars(strtolower($h['case_number']), ENT_QUOTES) ?>"
              data-type="<?= htmlspecialchars(strtolower($h['incident_type']), ENT_QUOTES) ?>"
              data-attended="<?= $attended_val ?>">
            <td class="td-mono"><?= e($h['case_number']) ?></td>
            <td><?= e($h['incident_type']) ?></td>
            <td style="font-size:12px;color:var(--ink-400)"><?= date('M j, Y', strtotime($h['hearing_date'])) ?></td>
            <td><span class="chip <?= $sc[$h['status']]??'ch-slate' ?>"><?= ucwords(str_replace('_',' ',$h['status'])) ?></span></td>
            <td>
              <?php if ($h['complainant_attended'] !== null): ?>
                <span class="chip <?= $h['complainant_attended']?'ch-green':'ch-rose' ?>"><?= $h['complainant_attended']?'Yes':'No' ?></span>
              <?php else: ?>
                <span style="color:var(--ink-300)">—</span>
              <?php endif; ?>
            </td>
            <td style="font-size:12px;color:var(--ink-500);max-width:200px;white-space:normal"><?= e(mb_strimwidth($h['outcome']??'—',0,70,'…')) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <!-- shown when search hides all past rows -->
  <div id="past-empty" style="display:none">
    <div class="empty-state mt16"><div class="es-icon">📋</div><div class="es-title">No past hearings match</div></div>
  </div>
</div>
<?php else: ?>
<div id="section-past" style="display:none">
  <div id="past-empty" style="display:none"></div>
</div>
<?php endif; ?>

<script>
(function () {

  function val(id) {
    var el = document.getElementById(id);
    return el ? el.value.trim().toLowerCase() : '';
  }

  function applyMedFilters() {
    var search   = val('med-search');
    var section  = val('med-filter-section');  // all | upcoming | past
    var status   = val('med-filter-status');
    var attended = val('med-filter-attended'); // '' | yes | no

    // ── Upcoming cards ────────────────────────────────────────
    var secUp    = document.getElementById('section-upcoming');
    var lblUp    = document.getElementById('lbl-upcoming');
    var upEmpty  = document.getElementById('upcoming-empty');
    var cards    = document.querySelectorAll('.med-card');
    var visCards = 0;

    if (section === 'past') {
      if (secUp) secUp.style.display = 'none';
    } else {
      if (secUp) secUp.style.display = '';
      cards.forEach(function (card) {
        var matchSearch = !search || ['case','type','complainant','respondent'].some(function (k) {
          return (card.dataset[k] || '').includes(search);
        });
        var matchStatus = !status || status === 'scheduled'; // upcoming are always scheduled
        // attendance filter doesn't apply to upcoming (no data yet)
        var visible = matchSearch && matchStatus;
        card.style.display = visible ? '' : 'none';
        if (visible) visCards++;
      });
      var noCards = cards.length > 0 && visCards === 0;
      if (upEmpty) upEmpty.style.display = noCards ? '' : 'none';
      if (lblUp)   lblUp.style.display   = noCards ? 'none' : '';
    }

    // ── Past rows ─────────────────────────────────────────────
    var secPast  = document.getElementById('section-past');
    var lblPast  = document.getElementById('lbl-past');
    var pastEmp  = document.getElementById('past-empty');
    var rows     = document.querySelectorAll('.med-past-row');
    var visRows  = 0;

    if (section === 'upcoming') {
      if (secPast) secPast.style.display = 'none';
    } else {
      if (secPast) secPast.style.display = rows.length > 0 ? '' : 'none';
      rows.forEach(function (row) {
        var matchSearch   = !search || ['case','type'].some(function (k) {
          return (row.dataset[k] || '').includes(search);
        });
        var matchStatus   = !status || row.dataset.status === status;
        var matchAttended = !attended || row.dataset.attended === attended;
        var visible = matchSearch && matchStatus && matchAttended;
        row.style.display = visible ? '' : 'none';
        if (visible) visRows++;
      });
      var noRows = rows.length > 0 && visRows === 0;
      if (pastEmp) pastEmp.style.display = noRows ? '' : 'none';
      if (lblPast) lblPast.style.display  = noRows ? 'none' : '';
    }

    // ── Global no-results ─────────────────────────────────────
    var totalItems   = cards.length + rows.length;
    var totalVisible = visCards + visRows;
    var noResults = document.getElementById('med-no-results');
    if (noResults) {
      noResults.style.display = (totalItems > 0 && totalVisible === 0) ? '' : 'none';
    }

    // ── Count label ───────────────────────────────────────────
    var countLbl = document.getElementById('med-count-label');
    if (countLbl) {
      var hasFilter = search || status || attended || section !== 'all';
      if (hasFilter) {
        var shownUpcoming = section === 'past'     ? 0 : visCards;
        var shownPast     = section === 'upcoming' ? 0 : visRows;
        var totalShown    = shownUpcoming + shownPast;
        var totalScope    = (section === 'past' ? 0 : cards.length) + (section === 'upcoming' ? 0 : rows.length);
        countLbl.textContent = 'Showing ' + totalShown + ' of ' + totalScope;
      } else {
        countLbl.textContent = '';
      }
    }
  }

  function clearMedFilters() {
    ['med-search','med-filter-status','med-filter-attended'].forEach(function (id) {
      var el = document.getElementById(id);
      if (el) el.value = '';
    });
    var sec = document.getElementById('med-filter-section');
    if (sec) sec.value = 'all';
    applyMedFilters();
  }

  window.applyMedFilters = applyMedFilters;
  window.clearMedFilters = clearMedFilters;

  // Initialise on load
  applyMedFilters();
})();
</script>