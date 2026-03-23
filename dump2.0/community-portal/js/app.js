/* ══════════════════════════════════════════
   app.js — Community Portal Main Script
   ══════════════════════════════════════════ */

// ── PAGE TITLES MAP ──
const pages = {
  'dashboard':      'Dashboard',
  'my-blotters':    'My Blotters',
  'file-report':    'File a Report',
  'assigned-cases': 'Assigned Cases',
  'mediation':      'Mediation Schedule',
  'notices':        'Notices & Sanctions',
  'penalties':      'Penalties & Compliance',
  'history':        'Case History',
  'profile':        'My Profile',
};

// ── NAVIGATION ──
function navigate(el) {
  if (!el) return;
  const key = el.dataset.page;
  if (!key) return;

  document.querySelectorAll('.nav-item').forEach(i => i.classList.remove('active'));
  el.classList.add('active');

  document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
  const pg = document.getElementById('page-' + key);
  if (pg) pg.classList.add('active');

  document.getElementById('topbar-title').textContent = pages[key] || key;
  closeDetail();
}

// ── MODAL ──
function openModal(id) {
  document.getElementById(id).classList.add('open');
}

function closeModal(id) {
  document.getElementById(id).classList.remove('open');
}

document.querySelectorAll('.modal-overlay').forEach(o => {
  o.addEventListener('click', e => {
    if (e.target === o) o.classList.remove('open');
  });
});

// ── REPORT TYPE TOGGLE (Full Page) ──
function selectReportType(el, type) {
  document.querySelectorAll('#page-file-report .report-type-card').forEach(c => c.classList.remove('selected'));
  el.classList.add('selected');
  const personFields = document.getElementById('person-fields');
  if (personFields) personFields.style.display = type === 'person' ? 'block' : 'none';
}

// ── REPORT TYPE TOGGLE (Modal) ──
function selectModalType(el, type) {
  document.querySelectorAll('#modal-report .report-type-card').forEach(c => c.classList.remove('selected'));
  el.classList.add('selected');
  const modalPersonFields = document.getElementById('modal-person-fields');
  if (modalPersonFields) modalPersonFields.style.display = type === 'person' ? 'block' : 'none';
}

// ── CASE DETAIL DATA ──
const detailData = {
  'blotter-#2024-0092': {
    title: 'Case #2024-0092',
    info: [
      ['Case Number',    '#2024-0092'],
      ['Type',           'Report a Person'],
      ['Incident',       'Property boundary dispute'],
      ['Respondent',     'Pedro Santos'],
      ['Filed by',       'Juan Dela Cruz'],
      ['Date Filed',     'March 20, 2026'],
      ['Location',       '124 Sampaguita St., Brgy. San Roque'],
      ['Status',         'Under Review'],
      ['Violation Level','Minor'],
    ],
    timeline: [
      { label: 'Blotter submitted',        meta: 'Mar 20, 2026 · 10:32 AM', cls: 'tl-dot-blue'  },
      { label: 'Under review by barangay', meta: 'Mar 21, 2026 · 9:00 AM',  cls: 'tl-dot-amber' },
    ],
    notes: 'Complainant states that respondent constructed a fence that encroaches approximately 30cm into complainant\'s lot. Physical evidence (photos) submitted.'
  },

  'blotter-#2024-0087': {
    title: 'Case #2024-0087',
    info: [
      ['Case Number',    '#2024-0087'],
      ['Type',           'Report a Person'],
      ['Incident',       'Noise disturbance'],
      ['Respondent',     'Maria Reyes'],
      ['Filed by',       'Juan Dela Cruz'],
      ['Date Filed',     'March 10, 2026'],
      ['Hearing Date',   'March 27, 2026 · 9:00 AM'],
      ['Status',         'Mediation Set'],
      ['Violation Level','Moderate'],
    ],
    timeline: [
      { label: 'Blotter submitted',           meta: 'Mar 10, 2026',                     cls: 'tl-dot-blue'  },
      { label: 'Case accepted by barangay',   meta: 'Mar 11, 2026',                     cls: 'tl-dot-green' },
      { label: 'Summons issued to respondent',meta: 'Mar 14, 2026',                     cls: 'tl-dot-amber' },
      { label: 'Mediation scheduled',         meta: 'Mar 21, 2026 · Mar 27 at 9AM',    cls: 'tl-dot-amber' },
    ],
    notes: 'Loud music and parties regularly past midnight. Three separate incidents documented on March 3, 7, and 9.'
  },

  'blotter-#2024-0061': {
    title: 'Case #2024-0061',
    info: [
      ['Case Number',    '#2024-0061'],
      ['Type',           'Incident Report'],
      ['Incident',       'Vandalism on community wall'],
      ['Respondent',     'Unknown'],
      ['Filed by',       'Juan Dela Cruz'],
      ['Date Filed',     'February 18, 2026'],
      ['Status',         'Pending'],
      ['Violation Level','Moderate'],
    ],
    timeline: [
      { label: 'Incident reported',              meta: 'Feb 18, 2026', cls: 'tl-dot-blue'  },
      { label: 'Awaiting barangay investigation', meta: 'Feb 19, 2026', cls: 'tl-dot-amber' },
    ],
    notes: 'Community mural on the corner of Sampaguita and Rosal St. was vandalized with spray paint. CCTV footage being reviewed.'
  },

  'blotter-#2023-0045': {
    title: 'Case #2023-0045',
    info: [
      ['Case Number',    '#2023-0045'],
      ['Type',           'Report a Person'],
      ['Incident',       'Verbal abuse and harassment'],
      ['Respondent',     'Roberto Cruz'],
      ['Date Filed',     'November 5, 2023'],
      ['Date Resolved',  'December 12, 2023'],
      ['Status',         'Resolved'],
      ['Outcome',        'Settled via mediation'],
    ],
    timeline: [
      { label: 'Blotter submitted',             meta: 'Nov 5, 2023',  cls: 'tl-dot-blue'  },
      { label: 'Mediation held',                meta: 'Dec 10, 2023', cls: 'tl-dot-amber' },
      { label: 'Agreement signed by both parties', meta: 'Dec 10, 2023', cls: 'tl-dot-green' },
      { label: 'Case closed and resolved',      meta: 'Dec 12, 2023', cls: 'tl-dot-green' },
    ],
    notes: 'Both parties reached an amicable settlement. Respondent issued a formal apology and signed a written agreement to cease all harassment.'
  },

  'assigned-#2024-0087': {
    title: 'Assigned Case #2024-0087',
    info: [
      ['Case Number',     '#2024-0087'],
      ['Complainant',     'Juan Dela Cruz'],
      ['Incident',        'Noise disturbance'],
      ['Your Role',       'Respondent'],
      ['Violation Level', 'Moderate'],
      ['Missed Hearings', '0 of 3'],
      ['Mediation Date',  'March 27, 2026 · 9:00 AM'],
      ['Venue',           'Barangay Hall, Room 2'],
      ['Mediator',        'Brgy. Kagawad Santos'],
    ],
    timeline: [
      { label: 'Complaint filed against you', meta: 'Mar 10, 2026',              cls: 'tl-dot-amber' },
      { label: 'Summons sent to you',         meta: 'Mar 14, 2026',              cls: 'tl-dot-amber' },
      { label: 'Mediation scheduled',         meta: 'Mar 27, 2026 at 9:00 AM',  cls: 'tl-dot-blue'  },
    ],
    notes: 'You are required to appear at the scheduled mediation. Bring any evidence or documentation relevant to the case. Failure to appear will result in a written warning and penalty.'
  },

  'history-#2023-0045': {
    title: 'Archived Case #2023-0045',
    info: [
      ['Case Number', '#2023-0045'],
      ['Role',        'Complainant'],
      ['Incident',    'Verbal abuse and harassment'],
      ['Filed',       'November 5, 2023'],
      ['Resolved',    'December 12, 2023'],
      ['Outcome',     'Settled via mediation'],
      ['Status',      'Resolved'],
    ],
    timeline: [
      { label: 'Complaint filed',              meta: 'Nov 5, 2023',  cls: 'tl-dot-blue'  },
      { label: 'Mediation conducted',          meta: 'Dec 10, 2023', cls: 'tl-dot-amber' },
      { label: 'Settlement agreement signed',  meta: 'Dec 10, 2023', cls: 'tl-dot-green' },
      { label: 'Case resolved and archived',   meta: 'Dec 12, 2023', cls: 'tl-dot-green' },
    ],
    notes: 'Case successfully resolved. Formal apology issued by respondent. No further violations reported since.'
  },

  'history-#2022-0019': {
    title: 'Archived Case #2022-0019',
    info: [
      ['Case Number',        '#2022-0019'],
      ['Role',               'Respondent'],
      ['Incident',           'Public disturbance'],
      ['Filed',              'March 3, 2022'],
      ['Resolved',           'April 1, 2022'],
      ['Penalty Paid',       '₱200.00'],
      ['Community Service',  '4 hours completed'],
      ['Status',             'Closed'],
    ],
    timeline: [
      { label: 'Complaint filed against you',          meta: 'Mar 3, 2022',  cls: 'tl-dot-amber' },
      { label: 'Mediation held',                       meta: 'Mar 20, 2022', cls: 'tl-dot-amber' },
      { label: 'Penalty and community service ordered',meta: 'Mar 21, 2022', cls: 'tl-dot-red'   },
      { label: 'Community service completed',          meta: 'Mar 28, 2022', cls: 'tl-dot-green' },
      { label: 'Penalty paid · Case closed',           meta: 'Apr 1, 2022',  cls: 'tl-dot-green' },
    ],
    notes: 'All conditions of the barangay order have been fulfilled. This case is now archived. No further action required.'
  }
};

// ── DETAIL PANEL ──
function openDetail(type, caseNo) {
  const key = type + '-' + caseNo;
  const d = detailData[key];
  if (!d) return;

  document.getElementById('detail-panel-title').textContent = d.title;

  let html = '';

  if (d.notes) {
    html += `<div class="alert alert-blue" style="margin-bottom:16px">
      <svg class="alert-icon alert-icon-blue" width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><circle cx="8" cy="8" r="6"/><path d="M8 7v5M8 5.5v-.5"/></svg>
      <div class="alert-text"><span>${d.notes}</span></div>
    </div>`;
  }

  html += `<div class="detail-section">
    <div class="detail-section-title">Case Information</div>`;
  d.info.forEach(([k, v]) => {
    html += `<div class="detail-row">
      <span class="detail-key">${k}</span>
      <span class="detail-val">${v}</span>
    </div>`;
  });
  html += `</div>`;

  if (d.timeline && d.timeline.length) {
    html += `<div class="detail-section">
      <div class="detail-section-title">Case Timeline</div>
      <ul class="timeline">`;
    d.timeline.forEach(t => {
      html += `<li class="tl-item">
        <div class="tl-dot ${t.cls}"></div>
        <div class="tl-label">${t.label}</div>
        <div class="tl-meta">${t.meta}</div>
      </li>`;
    });
    html += `</ul></div>`;
  }

  document.getElementById('detail-panel-body').innerHTML = html;
  document.getElementById('detail-panel').classList.add('open');
}

function closeDetail() {
  document.getElementById('detail-panel').classList.remove('open');
}

// ── TOAST NOTIFICATION ──
function showToast(msg) {
  const t = document.getElementById('toast');
  t.textContent = msg;
  t.style.opacity = '1';
  t.style.transform = 'translateX(-50%) translateY(0)';
  clearTimeout(t._timer);
  t._timer = setTimeout(() => {
    t.style.opacity = '0';
    t.style.transform = 'translateX(-50%) translateY(10px)';
  }, 3000);
}
