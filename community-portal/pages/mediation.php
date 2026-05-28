<?php // community-portal/pages/mediation.php
$uid = (int)$user['id'];
$bid = (int)$user['barangay_id'];
$uname = $user['name'] ?? '';

$name_parts = array_filter(preg_split('/[\s,]+/', $uname), fn($p) => strlen($p) > 2);
$name_likes = implode(' AND ', array_map(fn($p) => "b.respondent_name LIKE '%" . addslashes($p) . "%'", $name_parts));
$name_likes_plain = $name_likes ?: "1=0";
$case_scope = "(b.complainant_user_id = $uid OR b.respondent_user_id = $uid OR (b.respondent_user_id IS NULL AND ($name_likes_plain)))";

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
            $case_scope
            AND ms.status = 'scheduled'
            AND ms.hearing_date >= CURDATE()
        ORDER BY ms.hearing_date ASC, ms.hearing_time ASC
    ")->fetchAll();

    $past = $pdo->query("
        SELECT
            ms.hearing_date,
            ms.hearing_time,
            ms.venue,
            ms.status,
            ms.outcome,
            b.complainant_name,
            b.respondent_name,
            CASE
                WHEN b.complainant_user_id = $uid THEN ms.complainant_attended
                ELSE ms.respondent_attended
            END AS my_attended,
            b.case_number,
            b.incident_type
        FROM mediation_schedules ms
        JOIN blotters b ON b.id = ms.blotter_id
        WHERE
            $case_scope
            AND (
                ms.status != 'scheduled'
                OR ms.hearing_date < CURDATE()
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
    <input type="search" id="med-search" placeholder="Search case no., type, name…" oninput="applyMedFilters(true)">
  </div>
  <select id="med-filter-section" onchange="applyMedFilters(true)">
    <option value="all">All Hearings</option>
    <option value="upcoming">Upcoming Only</option>
    <option value="past">Past Only</option>
  </select>
  <select id="med-filter-status" onchange="applyMedFilters(true)">
    <option value="">All Statuses</option>
    <option value="scheduled">Scheduled</option>
    <option value="completed">Completed</option>
    <option value="missed">Missed</option>
    <option value="cancelled">Cancelled</option>
    <option value="rescheduled">Rescheduled</option>
  </select>
  <select id="med-filter-attended" onchange="applyMedFilters(true)">
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
  <div id="upcoming-pager" class="card-foot" style="display:none;border:1px solid var(--surface-2);border-radius:var(--r-sm);margin-top:-12px;margin-bottom:22px"></div>
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
          $attended_val = $h['my_attended'] === null ? '' : ($h['my_attended'] ? 'yes' : 'no');
        ?>
          <tr class="med-past-row"
              data-section="past"
              data-status="<?= htmlspecialchars($h['status'], ENT_QUOTES) ?>"
              data-case="<?= htmlspecialchars(strtolower($h['case_number']), ENT_QUOTES) ?>"
              data-type="<?= htmlspecialchars(strtolower($h['incident_type']), ENT_QUOTES) ?>"
              data-complainant="<?= htmlspecialchars(strtolower($h['complainant_name'] ?? ''), ENT_QUOTES) ?>"
              data-respondent="<?= htmlspecialchars(strtolower($h['respondent_name'] ?? ''), ENT_QUOTES) ?>"
              data-attended="<?= $attended_val ?>">
            <td class="td-mono"><?= e($h['case_number']) ?></td>
            <td><?= e($h['incident_type']) ?></td>
            <td style="font-size:12px;color:var(--ink-400)"><?= date('M j, Y', strtotime($h['hearing_date'])) ?></td>
            <td><span class="chip <?= $sc[$h['status']]??'ch-slate' ?>"><?= ucwords(str_replace('_',' ',$h['status'])) ?></span></td>
            <td>
              <?php if ($h['my_attended'] !== null): ?>
                <span class="chip <?= $h['my_attended']?'ch-green':'ch-rose' ?>"><?= $h['my_attended']?'Yes':'No' ?></span>
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
    <div id="past-pager" class="card-foot" style="display:none"></div>
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
  var medPageSize = 6;
  var medPages = { upcoming: 1, past: 1 };

  function val(id) {
    var el = document.getElementById(id);
    return el ? el.value.trim().toLowerCase() : '';
  }

  function renderMedPager(id, section, page, total) {
    var el = document.getElementById(id);
    if (!el) return;
    var pages = Math.max(1, Math.ceil(total / medPageSize));
    if (total <= medPageSize) {
      el.style.display = 'none';
      el.innerHTML = '';
      return;
    }
    var start = ((page - 1) * medPageSize) + 1;
    var end = Math.min(page * medPageSize, total);
    var first = Math.max(1, page - 2);
    var last = Math.min(pages, page + 2);
    var html = '<div class="pager"><span class="pager-info">Showing ' + start + '-' + end + ' of ' + total + '</span><div class="pager-btns">';
    if (page > 1) html += '<button type="button" class="btn btn-outline btn-sm" onclick="changeMedPage(\'' + section + '\',' + (page - 1) + ')">Prev</button>';
    for (var i = first; i <= last; i++) {
      html += '<button type="button" class="btn ' + (i === page ? 'btn-primary' : 'btn-outline') + ' btn-sm" onclick="changeMedPage(\'' + section + '\',' + i + ')">' + i + '</button>';
    }
    if (page < pages) html += '<button type="button" class="btn btn-outline btn-sm" onclick="changeMedPage(\'' + section + '\',' + (page + 1) + ')">Next</button>';
    html += '</div></div>';
    el.innerHTML = html;
    el.style.display = '';
  }

  function applyMedFilters(resetPages) {
    if (resetPages) {
      medPages.upcoming = 1;
      medPages.past = 1;
    }

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
      renderMedPager('upcoming-pager', 'upcoming', 1, 0);
    } else {
      if (secUp) secUp.style.display = '';
      var matchedCards = [];
      cards.forEach(function (card) {
        var matchSearch = !search || ['case','type','complainant','respondent'].some(function (k) {
          return (card.dataset[k] || '').includes(search);
        });
        var matchStatus = !status || status === 'scheduled'; // upcoming are always scheduled
        // attendance filter doesn't apply to upcoming (no data yet)
        var visible = matchSearch && matchStatus;
        if (visible) matchedCards.push(card);
      });
      var upPages = Math.max(1, Math.ceil(matchedCards.length / medPageSize));
      medPages.upcoming = Math.min(Math.max(1, medPages.upcoming), upPages);
      var upStart = (medPages.upcoming - 1) * medPageSize;
      var upEnd = upStart + medPageSize;
      cards.forEach(function (card) {
        var visible = matchedCards.indexOf(card) >= upStart && matchedCards.indexOf(card) < upEnd;
        card.style.display = visible ? '' : 'none';
      });
      visCards = matchedCards.length;
      renderMedPager('upcoming-pager', 'upcoming', medPages.upcoming, matchedCards.length);
      var noCards = cards.length > 0 && matchedCards.length === 0;
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
      renderMedPager('past-pager', 'past', 1, 0);
    } else {
      if (secPast) secPast.style.display = rows.length > 0 ? '' : 'none';
      var matchedRows = [];
      rows.forEach(function (row) {
        var matchSearch   = !search || ['case','type','complainant','respondent'].some(function (k) {
          return (row.dataset[k] || '').includes(search);
        });
        var matchStatus   = !status || row.dataset.status === status;
        var matchAttended = !attended || row.dataset.attended === attended;
        var visible = matchSearch && matchStatus && matchAttended;
        if (visible) matchedRows.push(row);
      });
      var pastPages = Math.max(1, Math.ceil(matchedRows.length / medPageSize));
      medPages.past = Math.min(Math.max(1, medPages.past), pastPages);
      var pastStart = (medPages.past - 1) * medPageSize;
      var pastEnd = pastStart + medPageSize;
      rows.forEach(function (row) {
        var visible = matchedRows.indexOf(row) >= pastStart && matchedRows.indexOf(row) < pastEnd;
        row.style.display = visible ? '' : 'none';
      });
      visRows = matchedRows.length;
      renderMedPager('past-pager', 'past', medPages.past, matchedRows.length);
      var noRows = rows.length > 0 && matchedRows.length === 0;
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
    applyMedFilters(true);
  }

  function changeMedPage(section, page) {
    medPages[section] = page;
    applyMedFilters(false);
  }

  window.applyMedFilters = applyMedFilters;
  window.clearMedFilters = clearMedFilters;
  window.changeMedPage = changeMedPage;

  // Initialise on load
  applyMedFilters();
})();
</script>
