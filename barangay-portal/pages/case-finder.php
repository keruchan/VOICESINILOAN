<?php
// pages/case-finder.php — Smart Case Finder
// Free-text relevance search over the Sanctions Book and past blotter cases.
$cat_labels = ['kp_law'=>'KP Law (R.A. 7160)','local_ordinance'=>'Local Ordinance','barangay_program'=>'Barangay Program'];
$cat_chips  = ['kp_law'=>'ch-navy','local_ordinance'=>'ch-teal','barangay_program'=>'ch-slate'];
$lm = ['minor'=>'ch-emerald','moderate'=>'ch-amber','serious'=>'ch-rose','critical'=>'ch-violet'];
$sm = ['pending_review'=>'ch-amber','active'=>'ch-teal','mediation_set'=>'ch-navy','resolved'=>'ch-emerald','closed'=>'ch-slate','escalated'=>'ch-rose','transferred'=>'ch-slate'];
?>

<div class="page-hdr">
  <div class="page-hdr-left">
    <h2>Smart Case Finder</h2>
    <p>Describe an incident in your own words — find which Sanctions Book entries and past cases it matches, ranked by relevance</p>
  </div>
  <a href="?page=sanctions-book" class="btn btn-outline btn-sm">← Back to Sanctions Book</a>
</div>

<div class="card mb16">
  <div class="card-body" style="padding:18px 20px">
    <div class="fg" style="margin-bottom:12px">
      <label>Describe the incident or complaint</label>
      <textarea id="cf-query" rows="4" placeholder="e.g. Respondent did not show up at the second mediation hearing despite being notified…"></textarea>
    </div>
    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
      <button class="btn btn-primary" id="cf-search-btn" onclick="runCaseFinder()">🔎 Find Matching Cases</button>
      <button class="btn btn-ghost btn-sm" onclick="clearCaseFinder()">✕ Clear</button>
      <span id="cf-status" style="font-size:12px;color:var(--ink-400)"></span>
    </div>
  </div>
</div>

<div id="cf-results"></div>

<!-- View Details Modal (Sanctions Book entry) -->
<div class="modal-overlay" id="modal-view-sanction">
  <div class="modal modal-lg">
    <div class="modal-hdr"><span class="modal-title" id="vs-title">Sanction Details</span><button class="modal-x" onclick="closeModal('modal-view-sanction')">×</button></div>
    <div class="modal-body">
      <div style="display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap">
        <span class="chip" id="vs-level-chip"></span>
        <span class="chip" id="vs-cat-chip"></span>
      </div>
      <div class="dr"><span class="dr-lbl">Violation Type</span><span class="dr-val" id="vs-vtype" style="font-weight:600"></span></div>
      <div class="dr"><span class="dr-lbl">Sanction</span><span class="dr-val" id="vs-sname"></span></div>
      <div class="dr"><span class="dr-lbl">Fine</span><span class="dr-val" id="vs-fine" style="font-weight:600;color:var(--rose-600)"></span></div>
      <div class="dr"><span class="dr-lbl">Community Service</span><span class="dr-val" id="vs-hours"></span></div>
      <div class="dr"><span class="dr-lbl">Reference No.</span><span class="dr-val" id="vs-ordref" style="font-family:var(--font-mono)"></span></div>
      <div id="vs-legal-wrap" style="margin-top:16px;padding:14px 16px;border-radius:var(--r-md);background:var(--navy-50,#eef3fb);border:1px solid var(--navy-100,#d7e3f5)">
        <div style="font-size:11px;font-weight:700;color:var(--navy-700,#1F4068);text-transform:uppercase;letter-spacing:.06em;margin-bottom:6px">
          Legal Basis — <span id="vs-basis" style="font-family:var(--font-mono);text-transform:none;letter-spacing:normal"></span>
        </div>
        <p id="vs-explanation" style="font-size:13px;line-height:1.7;color:var(--ink-700);margin:0"></p>
      </div>
      <div id="vs-desc-wrap" style="margin-top:14px">
        <div style="font-size:11px;font-weight:700;color:var(--ink-400);text-transform:uppercase;letter-spacing:.06em;margin-bottom:4px">Notes</div>
        <p id="vs-desc" style="font-size:12px;color:var(--ink-500);margin:0"></p>
      </div>
    </div>
    <div class="modal-foot">
      <button class="btn btn-outline" onclick="closeModal('modal-view-sanction')">Close</button>
      <a class="btn btn-primary" href="?page=sanctions-book">Manage in Sanctions Book →</a>
    </div>
  </div>
</div>

<script>
const CF_CAT_LABELS = <?= json_encode($cat_labels) ?>;
const CF_CAT_CHIPS  = <?= json_encode($cat_chips) ?>;
const CF_LEVEL_CH   = <?= json_encode($lm) ?>;
const CF_STATUS_CH  = <?= json_encode($sm) ?>;

function cfEsc(s) {
  return String(s || '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}

// Bold each query word wherever it appears in the snippet — Google-style highlighting
function cfHighlight(text, words) {
  let out = cfEsc(text || '');
  words.forEach(w => {
    if (!w || w.length < 2) return;
    const re = new RegExp('(' + w.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'ig');
    out = out.replace(re, '<mark>$1</mark>');
  });
  return out;
}

function cfSnippet(text, maxLen) {
  const t = (text || '').trim();
  return t.length > maxLen ? t.substring(0, maxLen) + '…' : t;
}

function barColor(pct) {
  return pct >= 75 ? 'var(--teal-500)' : pct >= 40 ? 'var(--amber-400)' : 'var(--ink-300)';
}

function clearCaseFinder() {
  document.getElementById('cf-query').value = '';
  document.getElementById('cf-results').innerHTML = '';
  document.getElementById('cf-status').textContent = '';
}

function runCaseFinder() {
  const q = document.getElementById('cf-query').value.trim();
  if (!q) return showToast('Type a description first.', 'error');

  const btn = document.getElementById('cf-search-btn');
  btn.disabled = true; btn.textContent = 'Searching…';
  document.getElementById('cf-status').textContent = '';

  fetch('ajax/case_finder_search.php', {
    method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify({ q })
  })
    .then(r => r.json())
    .then(d => {
      btn.disabled = false; btn.textContent = '🔎 Find Matching Cases';
      if (!d.success) { showToast(d.message, 'error'); return; }
      renderResults(d.sanctions || [], d.blotters || [], q);
    })
    .catch(() => {
      btn.disabled = false; btn.textContent = '🔎 Find Matching Cases';
      showToast('Search failed. Try again.', 'error');
    });
}

function renderResults(sanctions, blotters, q) {
  const words = q.split(/[^\p{L}\p{N}]+/u).filter(w => w.length >= 2);
  const results = document.getElementById('cf-results');
  const total = sanctions.length + blotters.length;
  document.getElementById('cf-status').textContent = total
    ? `${total} match${total===1?'':'es'} found`
    : '';

  if (total === 0) {
    results.innerHTML = `<div class="empty-state"><div class="es-icon">🔍</div><div class="es-title">No matches found</div><div class="es-sub">Try describing the incident with different words (e.g. "noise", "boundary dispute", "missed hearing").</div></div>`;
    return;
  }

  let html = '';

  if (sanctions.length) {
    html += `<div class="med-section-lbl" style="font-size:11px;font-weight:700;letter-spacing:.05em;text-transform:uppercase;color:var(--ink-400);margin-bottom:12px">Matching Sanctions Book Entries</div>`;
    html += `<div class="g2 mb22">`;
    sanctions.forEach(s => {
      const catChip = CF_CAT_CHIPS[s.legal_category] || 'ch-slate';
      const catLabel = CF_CAT_LABELS[s.legal_category] || s.legal_category;
      const levelChip = CF_LEVEL_CH[s.violation_level] || 'ch-slate';
      html += `
      <div class="card" style="cursor:pointer" onclick='cfViewSanction(${JSON.stringify(s).replace(/'/g,"&#39;")})'>
        <div class="card-body" style="padding:14px 18px">
          <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:8px;margin-bottom:8px">
            <div>
              <div class="td-main" style="font-size:14px">${cfHighlight(s.violation_type, words)}</div>
              <div style="font-size:12px;color:var(--ink-500);margin-top:2px">${cfHighlight(s.sanction_name, words)}</div>
            </div>
            <span class="chip ${levelChip}" style="flex-shrink:0">${s.violation_level.charAt(0).toUpperCase()+s.violation_level.slice(1)}</span>
          </div>
          <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px">
            <div style="flex:1;height:6px;background:var(--surface-2);border-radius:10px;overflow:hidden">
              <div style="width:${s.match_pct}%;height:100%;background:${barColor(s.match_pct)};border-radius:10px"></div>
            </div>
            <span style="font-size:11px;font-weight:700;color:var(--ink-500);white-space:nowrap">${s.match_pct}% match</span>
          </div>
          <div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap">
            <span class="chip ${catChip}" style="font-size:10px">${catLabel}</span>
            ${s.legal_basis ? `<span style="font-size:11px;color:var(--ink-400);font-family:var(--font-mono)">${cfEsc(s.legal_basis)}</span>` : ''}
          </div>
        </div>
      </div>`;
    });
    html += `</div>`;
  }

  if (blotters.length) {
    html += `<div class="med-section-lbl" style="font-size:11px;font-weight:700;letter-spacing:.05em;text-transform:uppercase;color:var(--ink-400);margin-bottom:12px">Similar Past Cases</div>`;
    html += `<div class="card"><div class="tbl-wrap"><table>
      <thead><tr><th>Case No.</th><th>Type</th><th>Status</th><th>Match</th><th>Snippet</th><th></th></tr></thead>
      <tbody>`;
    blotters.forEach(b => {
      const statusChip = CF_STATUS_CH[b.status] || 'ch-slate';
      html += `
      <tr>
        <td class="td-mono">${cfEsc(b.case_number)}</td>
        <td style="font-size:12px">${cfHighlight(b.incident_type, words)}</td>
        <td><span class="chip ${statusChip}">${b.status.replace(/_/g,' ').replace(/\b\w/g,c=>c.toUpperCase())}</span></td>
        <td style="white-space:nowrap">
          <div style="display:flex;align-items:center;gap:6px">
            <div style="width:44px;height:6px;background:var(--surface-2);border-radius:10px;overflow:hidden">
              <div style="width:${b.match_pct}%;height:100%;background:${barColor(b.match_pct)};border-radius:10px"></div>
            </div>
            <span style="font-size:11px;color:var(--ink-500)">${b.match_pct}%</span>
          </div>
        </td>
        <td style="font-size:12px;color:var(--ink-500);max-width:280px;white-space:normal">${cfHighlight(cfSnippet(b.narrative, 140), words)}</td>
        <td><button class="act-btn" onclick="viewBlotter(${b.id})">View Case</button></td>
      </tr>`;
    });
    html += `</tbody></table></div></div>`;
  }

  results.innerHTML = html;
}

function cfViewSanction(s) {
  document.getElementById('vs-title').textContent = s.violation_type;
  document.getElementById('vs-vtype').textContent  = s.violation_type;
  document.getElementById('vs-sname').textContent  = s.sanction_name;
  document.getElementById('vs-fine').textContent   = '₱' + Number(s.fine_amount || 0).toLocaleString();
  document.getElementById('vs-hours').textContent  = s.community_hours ? s.community_hours + ' hrs' : '—';
  document.getElementById('vs-ordref').textContent = s.ordinance_ref || '—';

  const levelChip = document.getElementById('vs-level-chip');
  levelChip.className = 'chip ' + (CF_LEVEL_CH[s.violation_level] || 'ch-slate');
  levelChip.textContent = s.violation_level.charAt(0).toUpperCase() + s.violation_level.slice(1);

  const catChip = document.getElementById('vs-cat-chip');
  catChip.className = 'chip ' + (CF_CAT_CHIPS[s.legal_category] || 'ch-slate');
  catChip.textContent = CF_CAT_LABELS[s.legal_category] || s.legal_category;

  const legalWrap = document.getElementById('vs-legal-wrap');
  if (s.legal_explanation) {
    legalWrap.style.display = '';
    document.getElementById('vs-basis').textContent = s.legal_basis || '—';
    document.getElementById('vs-explanation').textContent = s.legal_explanation;
  } else {
    legalWrap.style.display = 'none';
  }

  const descWrap = document.getElementById('vs-desc-wrap');
  if (s.description) { descWrap.style.display = ''; document.getElementById('vs-desc').textContent = s.description; }
  else descWrap.style.display = 'none';

  openModal('modal-view-sanction');
}

// Allow Ctrl+Enter to trigger search from the textarea
document.getElementById('cf-query').addEventListener('keydown', function (e) {
  if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') runCaseFinder();
});
</script>

<style>
#cf-results mark { background:var(--amber-100,#fef3c7); color:inherit; padding:0 1px; border-radius:2px; }
</style>
