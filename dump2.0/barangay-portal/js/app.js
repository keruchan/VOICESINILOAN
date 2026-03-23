/* ══════════════════════════════════════════════════════
   barangay-app.js — Barangay Portal Main Script
   ══════════════════════════════════════════════════════ */

// ── PAGE TITLES ──
const PAGE_TITLES = {
  'dashboard':          'Dashboard',
  'blotter-management': 'Blotter Management',
  'violator-monitor':   'Violator Monitor',
  'mediation':          'Mediation Management',
  'sanctions-book':     'Sanctions Book',
  'records-archive':    'Records Archive',
  'settings':           'Settings',
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
  document.getElementById('topbar-title').textContent = PAGE_TITLES[key] || key;
  closeDetail();
}

// ── MODALS ──
function openModal(id)  { const el = document.getElementById(id); if (el) el.classList.add('open'); }
function closeModal(id) { const el = document.getElementById(id); if (el) el.classList.remove('open'); }
document.addEventListener('click', e => {
  if (e.target.classList.contains('modal-overlay')) e.target.classList.remove('open');
});

// ── DETAIL PANEL ──
function closeDetail() {
  const p = document.getElementById('detail-panel');
  if (p) p.classList.remove('open');
}

// ── TOAST ──
function showToast(msg) {
  const t = document.getElementById('toast');
  if (!t) return;
  t.textContent = msg;
  t.style.opacity = '1';
  t.style.transform = 'translateX(-50%) translateY(0)';
  clearTimeout(t._timer);
  t._timer = setTimeout(() => {
    t.style.opacity = '0';
    t.style.transform = 'translateX(-50%) translateY(10px)';
  }, 3200);
}

// ── SAMPLE DATA ──
const SAMPLE_BLOTTERS = [
  { no:'#2026-0042', complainant:'Maria Santos', incident:'Noise Disturbance', level:'Moderate', status:'Active' },
  { no:'#2026-0041', complainant:'Jose Dela Cruz', incident:'Physical Altercation', level:'Serious', status:'Escalated' },
  { no:'#2026-0040', complainant:'Rosa Mendoza', incident:'Property Damage', level:'Minor', status:'Pending Review' },
  { no:'#2026-0039', complainant:'Lina Bautista', incident:'Domestic / VAWC', level:'Critical', status:'Escalated' },
  { no:'#2026-0038', complainant:'Carlo Manalo', incident:'Trespassing', level:'Minor', status:'Transferred' },
];

const SAMPLE_MEDIATIONS = [
  { case:'#2026-0042', parties:'Santos vs. Reyes', date:'Mar 25, 2026', time:'9:00 AM' },
  { case:'#2026-0039', parties:'Bautista vs. Bautista', date:'Mar 27, 2026', time:'2:00 PM' },
  { case:'#2026-0035', parties:'Reyes vs. Soriano', date:'Mar 28, 2026', time:'10:30 AM' },
];

const HOTSPOTS = [
  { loc:'Mabini St. Area', count:14 },
  { loc:'Rizal Ave. / Luna St.', count:11 },
  { loc:'Del Pilar St.', count:8 },
  { loc:'Barangay Market', count:7 },
  { loc:'Chapel Area', count:4 },
];

const FORECASTS = [
  { name:'Ramon Bautista', pct:92, risk:'Critical' },
  { name:'Eduardo Ramos',  pct:74, risk:'High' },
  { name:'Sofia Castillo', pct:55, risk:'Medium' },
];

const FORECAST_LOCATIONS = [
  { loc:'Mabini St. Area',    pct:80 },
  { loc:'Rizal Ave. / Luna',  pct:65 },
  { loc:'Del Pilar St.',      pct:40 },
];

const SANCTIONS_DATA = [
  { id:'SAN-001', level:'minor',    type:'noise',       name:'Verbal Warning',           fine:'₱0',     service:'—',   code:'Ord. 2021-12 §2.1', desc:'1st offense. Verbal warning from barangay officer. Documented in blotter.' },
  { id:'SAN-002', level:'minor',    type:'noise',       name:'Written Warning',           fine:'₱200',   service:'2 hrs', code:'Ord. 2021-12 §2.2', desc:'2nd offense within 6 months. Written warning + community service.' },
  { id:'SAN-003', level:'minor',    type:'property',    name:'Community Service Order',   fine:'₱300',   service:'4 hrs', code:'Ord. 2022-04 §4.1', desc:'Minor property damage or first offense vandalism.' },
  { id:'SAN-004', level:'minor',    type:'verbal',      name:'Written Apology + Warning', fine:'₱0',     service:'—',   code:'Ord. 2022-04 §3.1', desc:'First offense verbal abuse. Formal apology required.' },
  { id:'SAN-005', level:'moderate', type:'noise',       name:'Noise Ordinance Fine',      fine:'₱500',   service:'4 hrs', code:'Ord. 2021-12 §3.1', desc:'Repeated noise violations or non-compliance after warning.' },
  { id:'SAN-006', level:'moderate', type:'verbal',      name:'Mediation + Surety Bond',   fine:'₱500',   service:'—',   code:'Ord. 2022-04 §3.2', desc:'Repeated verbal abuse. Surety bond required to ensure non-repetition.' },
  { id:'SAN-007', level:'moderate', type:'property',    name:'Restitution + Fine',        fine:'₱1,000', service:'8 hrs', code:'Ord. 2022-04 §4.2', desc:'Moderate property damage. Respondent must pay for damages plus fine.' },
  { id:'SAN-008', level:'moderate', type:'altercation', name:'Peace Bond + Fine',         fine:'₱500',   service:'6 hrs', code:'Ord. 2022-04 §5.1', desc:'Minor physical altercation. Peace bond signed by both parties.' },
  { id:'SAN-009', level:'serious',  type:'altercation', name:'Refer to Police / Barangay Court', fine:'₱2,000', service:'—', code:'Ord. 2022-04 §5.3', desc:'Serious bodily harm. Case escalated to PNP. Barangay documents and endorses.' },
  { id:'SAN-010', level:'serious',  type:'domestic',    name:'Protection Order + Referral', fine:'—',    service:'—',   code:'RA 9262 §14',       desc:'Barangay issues Barangay Protection Order (BPO). DSWD referral mandatory.' },
  { id:'SAN-011', level:'serious',  type:'property',    name:'Criminal Referral',         fine:'—',      service:'—',   code:'RPC Art. 327',      desc:'Malicious mischief. Referred to PNP for criminal complaint filing.' },
  { id:'SAN-012', level:'critical', type:'vawc',        name:'Barangay Protection Order + DSWD Referral', fine:'—', service:'—', code:'RA 9262 §14', desc:'Immediate BPO issuance. Victim and children referred to DSWD and LGU-VAW desk.' },
  { id:'SAN-013', level:'critical', type:'altercation', name:'Immediate Police Referral', fine:'—',      service:'—',   code:'PNP Protocol',      desc:'Life-threatening injuries. Immediate 911/PNP referral. Barangay assists with documentation.' },
  { id:'SAN-014', level:'critical', type:'domestic',    name:'Emergency Intervention Protocol', fine:'—', service:'—', code:'RA 9262 + RA 7160', desc:'Coordination with DSWD, PNP, and hospital. Barangay serves as first responder and documenter.' },
];

// ── DASHBOARD INIT ──
function initDashboard() {
  // Animate KPI counters
  animateCount('kpi-total-blotters', 42);
  animateCount('kpi-active-violators', 17);
  animateCount('kpi-pending-mediation', 8);

  // Resolution rate
  const rr = document.getElementById('kpi-resolution-rate');
  if (rr) setTimeout(() => { rr.textContent = '73%'; }, 600);

  // Hotspot list
  const hl = document.getElementById('hotspot-list');
  if (hl) {
    const max = HOTSPOTS[0].count;
    hl.innerHTML = HOTSPOTS.map((h, i) => `
      <div class="forecast-bar-wrap">
        <div class="forecast-bar-label">
          <span>${h.loc}</span>
          <span style="font-weight:600;color:var(--slate-800)">${h.count} cases</span>
        </div>
        <div class="forecast-bar-track">
          <div class="forecast-bar-fill" style="width:0%;background:var(--blue-${i<2?'600':'200'})"></div>
        </div>
      </div>`).join('');
    setTimeout(() => {
      hl.querySelectorAll('.forecast-bar-fill').forEach((bar, i) => {
        bar.style.width = Math.round(HOTSPOTS[i].count / max * 100) + '%';
      });
    }, 300);
  }

  // Forecast list
  const fl = document.getElementById('forecast-list');
  if (fl) {
    fl.innerHTML = FORECASTS.map(f => {
      const color = f.risk === 'Critical' ? 'var(--rose-400)' : f.risk === 'High' ? 'var(--amber-400)' : 'var(--amber-200)';
      return `<div class="forecast-bar-wrap">
        <div class="forecast-bar-label">
          <span>${f.name}</span>
          <span style="font-weight:600;color:${color}">${f.pct}% risk</span>
        </div>
        <div class="forecast-bar-track">
          <div class="forecast-bar-fill" style="width:0%;background:${color}"></div>
        </div>
      </div>`;
    }).join('');
    setTimeout(() => {
      fl.querySelectorAll('.forecast-bar-fill').forEach((bar, i) => {
        bar.style.width = FORECASTS[i].pct + '%';
      });
    }, 400);
  }

  // Forecast locations
  const flocEl = document.getElementById('forecast-locations');
  if (flocEl) {
    flocEl.innerHTML = FORECAST_LOCATIONS.map(f => `
      <div class="forecast-bar-wrap">
        <div class="forecast-bar-label">
          <span>${f.loc}</span>
          <span style="font-weight:600;color:var(--violet-600)">${f.pct}%</span>
        </div>
        <div class="forecast-bar-track">
          <div class="forecast-bar-fill" style="width:0%;background:var(--violet-400)"></div>
        </div>
      </div>`).join('');
    setTimeout(() => {
      flocEl.querySelectorAll('.forecast-bar-fill').forEach((bar, i) => {
        bar.style.width = FORECAST_LOCATIONS[i].pct + '%';
      });
    }, 500);
  }

  // Recent blotters
  const rb = document.getElementById('recent-blotters-body');
  if (rb) {
    const levelChip = { Minor:'chip-minor', Moderate:'chip-moderate', Serious:'chip-serious', Critical:'chip-critical' };
    const statusChip = { Active:'chip-active', Escalated:'chip-escalated', 'Pending Review':'chip-pending', Transferred:'chip-closed' };
    rb.innerHTML = SAMPLE_BLOTTERS.map(b => `
      <tr>
        <td class="td-mono">${b.no}</td>
        <td class="td-main">${b.complainant}</td>
        <td>${b.incident}</td>
        <td><span class="chip ${levelChip[b.level]||'chip-slate'}">${b.level}</span></td>
        <td><span class="chip ${statusChip[b.status]||'chip-slate'}">${b.status}</span></td>
        <td><button class="act-btn" onclick="navigate(document.querySelector('[data-page=blotter-management]'))">Open</button></td>
      </tr>`).join('');
  }

  // Upcoming mediation
  const um = document.getElementById('upcoming-mediation-list');
  if (um) {
    um.innerHTML = SAMPLE_MEDIATIONS.map(m => `
      <div style="display:flex;gap:10px;padding:11px 0;border-bottom:1px solid var(--slate-100);align-items:center">
        <div style="width:36px;height:36px;border-radius:var(--r-sm);background:var(--blue-50);color:var(--blue-600);display:flex;align-items:center;justify-content:center;flex-shrink:0">
          <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><rect x="2" y="2" width="12" height="12" rx="1.5"/><path d="M2 6h12M6 6v8"/></svg>
        </div>
        <div style="flex:1">
          <div style="font-size:13px;font-weight:500;color:var(--slate-900)">${m.parties}</div>
          <div style="font-size:11px;color:var(--slate-400)">${m.case} · ${m.date} · ${m.time}</div>
        </div>
        <button class="act-btn btn-sm" style="font-size:11px">View</button>
      </div>`).join('');
  }

  // Init Charts
  initCharts();
}

function animateCount(id, target) {
  const el = document.getElementById(id);
  if (!el) return;
  let current = 0;
  const step = Math.ceil(target / 30);
  const timer = setInterval(() => {
    current = Math.min(current + step, target);
    el.textContent = current;
    if (current >= target) clearInterval(timer);
  }, 40);
}

// ── CHARTS (Chart.js) ──
function initCharts() {
  if (typeof Chart === 'undefined') return;
  Chart.defaults.font.family = "'Plus Jakarta Sans', sans-serif";
  Chart.defaults.color = '#8C98B0';

  // Monthly trend
  const trendCtx = document.getElementById('chart-trend');
  if (trendCtx) {
    new Chart(trendCtx, {
      type: 'bar',
      data: {
        labels: ['Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec','Jan','Feb','Mar'],
        datasets: [
          { label:'Filed', data:[28,34,22,30,26,38,33,29,40,35,38,42], backgroundColor:'rgba(58,123,247,0.15)', borderColor:'#3A7BF7', borderWidth:2, borderRadius:4 },
          { label:'Resolved', data:[20,28,18,24,22,30,28,22,34,28,32,31], backgroundColor:'rgba(16,185,122,0.12)', borderColor:'#10B97A', borderWidth:2, borderRadius:4 }
        ]
      },
      options: {
        responsive:true, maintainAspectRatio:false,
        plugins:{ legend:{ position:'top', labels:{ boxWidth:10, padding:16, font:{ size:11 } } } },
        scales:{
          y:{ grid:{ color:'rgba(0,0,0,0.04)' }, ticks:{ font:{ size:11 } } },
          x:{ grid:{ display:false }, ticks:{ font:{ size:11 } } }
        }
      }
    });
  }

  // Incident types donut
  const typeCtx = document.getElementById('chart-types');
  if (typeCtx) {
    new Chart(typeCtx, {
      type: 'doughnut',
      data: {
        labels: ['Noise','Physical','Verbal','Property','Domestic','VAWC','Other'],
        datasets:[{ data:[32,18,22,12,8,4,4],
          backgroundColor:['#3A7BF7','#F04444','#F5A623','#10B97A','#7C55EF','#E91E8C','#8C98B0'],
          borderWidth:2, borderColor:'#fff', hoverOffset:6 }]
      },
      options:{
        responsive:true, maintainAspectRatio:false,
        plugins:{
          legend:{ position:'right', labels:{ boxWidth:10, padding:12, font:{ size:11 } } }
        },
        cutout:'60%'
      }
    });
  }

  // Severity bar
  const sevCtx = document.getElementById('chart-severity');
  if (sevCtx) {
    new Chart(sevCtx, {
      type:'bar',
      data:{
        labels:['Minor','Moderate','Serious','Critical'],
        datasets:[{ data:[38,28,18,16],
          backgroundColor:['#EAF3DE','#FFF8E6','#FFF0F0','#F3EFFD'],
          borderColor:['#10B97A','#F5A623','#F04444','#7C55EF'],
          borderWidth:2, borderRadius:6 }]
      },
      options:{
        indexAxis:'y', responsive:true, maintainAspectRatio:false,
        plugins:{ legend:{ display:false } },
        scales:{
          x:{ grid:{ color:'rgba(0,0,0,0.04)' }, ticks:{ font:{ size:11 } } },
          y:{ grid:{ display:false }, ticks:{ font:{ size:11 } } }
        }
      }
    });
  }
}

// ── PRESCRIPTIVE ACTIONS ──
const PRESCRIPTIVE_ACTIONS = {
  minor: [
    { title:'Document Only', desc:'Record the incident in the blotter. No further escalation needed. Inform both parties.', icon:'📄' },
    { title:'Mediation', desc:'Schedule an amicable mediation session between complainant and respondent.', icon:'🤝' },
  ],
  moderate: [
    { title:'Mediation with Peace Bond', desc:'Mediation session required. Both parties sign a peace bond upon settlement.', icon:'🤝' },
    { title:'Refer to Another Barangay', desc:'If respondent resides in another barangay, forward case for proper jurisdiction.', icon:'📤' },
    { title:'Issue Sanction Notice', desc:'Issue appropriate penalty per Sanctions Book. Monitor compliance.', icon:'📋' },
  ],
  serious: [
    { title:'Refer to Philippine National Police', desc:'Endorse case to PNP if bodily harm or criminal act is established. Barangay documents and assists.', icon:'🚔' },
    { title:'Mandatory Mediation + Surety Bond', desc:'Mediation required with surety bond. Non-settlement leads to automatic police referral.', icon:'⚖️' },
    { title:'Barangay Protection Order (BPO)', desc:'Issue BPO if there is threat to personal safety. Valid for 15 days, extendable.', icon:'🛡️' },
  ],
  critical: [
    { title:'Immediate Police Referral', desc:'Life-threatening situation. Contact PNP immediately. Barangay assists as first responder.', icon:'🚨' },
    { title:'VAWC Desk Referral', desc:'Mandatory referral to LGU VAW desk and DSWD. Issue BPO and safety plan for victim.', icon:'💜' },
    { title:'Escalate to Municipality / DILG', desc:'Case beyond barangay jurisdiction. Endorse full blotter records to Municipal/City government.', icon:'🏛️' },
  ]
};

function updatePrescriptiveActions() {
  const level = document.getElementById('modal-violation-level').value;
  const container = document.getElementById('prescriptive-actions-container');
  if (!container) return;

  if (!level) {
    container.innerHTML = `<div style="font-size:13px;color:var(--slate-400);padding:12px;text-align:center;background:var(--slate-50);border-radius:var(--r-md)">Select a violation level above to see recommended actions</div>`;
    return;
  }

  const actions = PRESCRIPTIVE_ACTIONS[level] || [];
  container.innerHTML = `
    <div style="font-size:12px;color:var(--slate-500);margin-bottom:10px">Select the appropriate action for this blotter:</div>
    ${actions.map((a, i) => `
      <div class="paction-card ${i===0?'selected':''}" onclick="selectPAction(this)">
        <div style="display:flex;align-items:center;gap:10px">
          <div style="font-size:20px">${a.icon}</div>
          <div>
            <div class="paction-title">${a.title}</div>
            <div class="paction-desc">${a.desc}</div>
          </div>
        </div>
      </div>`).join('')}`;
}

function selectPAction(el) {
  el.closest('#prescriptive-actions-container').querySelectorAll('.paction-card').forEach(c => c.classList.remove('selected'));
  el.classList.add('selected');
}

// ── SANCTIONS BOOK ──
let activeSanctionLevel = 'all';

function renderSanctions() {
  const search = (document.getElementById('sanction-search')?.value || '').toLowerCase();
  const levelFilter = document.getElementById('sanction-level-filter')?.value || '';
  const typeFilter = document.getElementById('sanction-type-filter')?.value || '';

  const filtered = SANCTIONS_DATA.filter(s => {
    const matchLevel = !levelFilter ? true : s.level === levelFilter;
    const matchType  = !typeFilter  ? true : s.type  === typeFilter;
    const matchTab   = activeSanctionLevel === 'all' ? true : s.level === activeSanctionLevel;
    const matchSearch = !search ? true : (s.name.toLowerCase().includes(search) || s.desc.toLowerCase().includes(search) || s.code.toLowerCase().includes(search) || s.type.toLowerCase().includes(search));
    return matchLevel && matchType && matchTab && matchSearch;
  });

  const container = document.getElementById('sanctions-list');
  if (!container) return;

  if (!filtered.length) {
    container.innerHTML = `<div style="text-align:center;padding:40px;color:var(--slate-400)">No sanctions found matching your search.</div>`;
    return;
  }

  const levelColors = {
    minor:    { bg:'#EAF3DE', color:'#3B6D11' },
    moderate: { bg:'#FFF8E6', color:'#B97218' },
    serious:  { bg:'#FFF0F0', color:'#B91C1C' },
    critical: { bg:'#F3EFFD', color:'#4E28CC' },
  };

  container.innerHTML = filtered.map(s => {
    const lc = levelColors[s.level] || {};
    return `<div class="sanction-entry">
      <div style="display:flex;align-items:flex-start;gap:12px;justify-content:space-between;flex-wrap:wrap">
        <div>
          <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:4px">
            <span class="sanction-name">${s.name}</span>
            <span style="font-size:10px;font-weight:700;padding:2px 8px;border-radius:20px;background:${lc.bg};color:${lc.color}">${s.level.toUpperCase()}</span>
            <span class="sanction-code">${s.code}</span>
          </div>
          <div class="sanction-meta">${s.desc}</div>
        </div>
        <div style="text-align:right;flex-shrink:0">
          <div class="sanction-penalty">${s.fine}</div>
          <div style="font-size:11px;color:var(--slate-400);margin-top:2px">Community service: ${s.service}</div>
        </div>
      </div>
    </div>`;
  }).join('');
}

function filterSanctions() { renderSanctions(); }

function setLevelTab(el, level) {
  document.querySelectorAll('.sanction-level-tab').forEach(t => {
    t.classList.remove('active');
    t.style.borderColor = 'transparent';
  });
  el.classList.add('active');
  el.style.borderColor = 'currentColor';
  activeSanctionLevel = level;
  renderSanctions();
}

// ── TAB SWITCHERS ──
function switchViolatorTab(el, tabId) {
  document.querySelectorAll('.tab-bar .tab-item').forEach(t => t.classList.remove('active'));
  el.classList.add('active');
  ['tab-all','tab-critical','tab-repeat','tab-pending'].forEach(id => {
    const el2 = document.getElementById(id);
    if (el2) el2.style.display = id === tabId ? 'block' : 'none';
  });
}

function switchMedTab(el, tabId) {
  document.querySelectorAll('#page-mediation .tab-bar .tab-item').forEach(t => t.classList.remove('active'));
  el.classList.add('active');
  ['med-upcoming','med-pending','med-past','med-notifications'].forEach(id => {
    const el2 = document.getElementById(id);
    if (el2) el2.style.display = id === tabId ? 'block' : 'none';
  });
}

function switchSettingsTab(el, tabId) {
  document.querySelectorAll('#page-settings .tab-bar .tab-item').forEach(t => t.classList.remove('active'));
  el.classList.add('active');
  ['set-barangay','set-officers','set-ordinances','set-notifications','set-system'].forEach(id => {
    const el2 = document.getElementById(id);
    if (el2) el2.style.display = id === tabId ? 'block' : 'none';
  });
}

// ── BLOTTER DETAIL ──
function openBlotterDetail(caseId) {
  const panel = document.getElementById('detail-panel');
  const title = document.getElementById('detail-panel-title');
  const body  = document.getElementById('detail-panel-body');
  if (!panel || !title || !body) return;

  title.textContent = 'Case #' + caseId;

  const actionsMap = {
    '2026-0042': { action:'Mediation', actionChip:'chip-mediation', level:'Moderate', levelChip:'chip-moderate', respondent:'Pedro Reyes', complainant:'Maria Santos', incident:'Noise Disturbance', date:'Mar 22, 2026', officer:'Kgd. Cruz' },
    '2026-0041': { action:'Refer to Police', actionChip:'chip-police', level:'Serious', levelChip:'chip-serious', respondent:'Ana Villanueva', complainant:'Jose Dela Cruz', incident:'Physical Altercation', date:'Mar 21, 2026', officer:'Kgd. Ramos' },
    '2026-0040': { action:'Document Only', actionChip:'chip-slate', level:'Minor', levelChip:'chip-minor', respondent:'Unknown', complainant:'Rosa Mendoza', incident:'Property Damage', date:'Mar 20, 2026', officer:'—' },
    '2026-0039': { action:'Refer to VAWC', actionChip:'chip-vawc', level:'Critical', levelChip:'chip-critical', respondent:'Ramon Bautista', complainant:'Lina Bautista', incident:'Domestic Dispute / VAWC', date:'Mar 19, 2026', officer:'Kgd. Santos' },
    '2026-0038': { action:'Transfer to Another Barangay', actionChip:'chip-blue', level:'Minor', levelChip:'chip-minor', respondent:'Brgy. Sta. Mesa Resident', complainant:'Carlo Manalo', incident:'Trespassing', date:'Mar 18, 2026', officer:'Kgd. Cruz' },
    '2026-0037': { action:'Mediation', actionChip:'chip-mediation', level:'Moderate', levelChip:'chip-moderate', respondent:'Elmer Soriano', complainant:'Nena Soriano', incident:'Verbal Abuse', date:'Mar 17, 2026', officer:'Kgd. Ramos' },
  };

  const d = actionsMap[caseId] || {};

  body.innerHTML = `
    <div class="detail-section">
      <div class="detail-section-title">Case Overview</div>
      <div class="detail-row"><span class="detail-key">Case No.</span><span class="detail-val" style="font-family:var(--font-mono)">#${caseId}</span></div>
      <div class="detail-row"><span class="detail-key">Date Filed</span><span class="detail-val">${d.date||'—'}</span></div>
      <div class="detail-row"><span class="detail-key">Complainant</span><span class="detail-val">${d.complainant||'—'}</span></div>
      <div class="detail-row"><span class="detail-key">Respondent</span><span class="detail-val">${d.respondent||'—'}</span></div>
      <div class="detail-row"><span class="detail-key">Incident</span><span class="detail-val">${d.incident||'—'}</span></div>
      <div class="detail-row"><span class="detail-key">Level</span><span class="detail-val"><span class="chip ${d.levelChip||'chip-slate'}">${d.level||'—'}</span></span></div>
      <div class="detail-row"><span class="detail-key">Assigned Officer</span><span class="detail-val">${d.officer||'—'}</span></div>
    </div>
    <div class="detail-section">
      <div class="detail-section-title">Prescribed Action</div>
      <div style="padding:12px;background:var(--slate-50);border-radius:var(--r-md);border:1px solid var(--slate-200)">
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px">
          <span class="chip ${d.actionChip||'chip-slate'}">${d.action||'—'}</span>
        </div>
        <div style="font-size:12px;color:var(--slate-500)">This action was prescribed based on the violation level and incident type. Update below if action needs to change.</div>
      </div>
    </div>
    <div class="detail-section">
      <div class="detail-section-title">Update Action</div>
      <select style="margin-bottom:10px">
        <option>Document Only</option>
        <option selected>Mediation</option>
        <option>Refer to Another Barangay</option>
        <option>Refer to Police</option>
        <option>Refer to VAWC</option>
        <option>Escalate to Municipality</option>
      </select>
      <textarea placeholder="Add notes or reasons for this action…" style="min-height:70px"></textarea>
      <div style="display:flex;gap:8px;margin-top:10px">
        <button class="btn btn-primary btn-sm" onclick="showToast('Case action updated.')">Save Update</button>
        <button class="btn btn-outline btn-sm" onclick="openModal('modal-schedule-mediation')">Schedule Mediation</button>
      </div>
    </div>
    <div class="detail-section">
      <div class="detail-section-title">Case Timeline</div>
      <ul class="timeline">
        <li class="tl-item"><div class="tl-dot tl-dot-blue"></div><div class="tl-label">Blotter filed</div><div class="tl-meta">${d.date||'—'}</div></li>
        <li class="tl-item"><div class="tl-dot tl-dot-amber"></div><div class="tl-label">Under review — officer assigned</div><div class="tl-meta">1 day after filing</div></li>
        <li class="tl-item"><div class="tl-dot tl-dot-violet"></div><div class="tl-label">Prescribed action set: ${d.action||'—'}</div><div class="tl-meta">Awaiting execution</div></li>
      </ul>
    </div>
  `;

  panel.classList.add('open');
}

function openViolatorDetail(vid) {
  const panel = document.getElementById('detail-panel');
  const title = document.getElementById('detail-panel-title');
  const body  = document.getElementById('detail-panel-body');
  if (!panel || !title || !body) return;

  title.textContent = vid === 'bautista' ? 'Ramon Bautista — Violator Profile' : 'Eduardo Ramos — Violator Profile';

  body.innerHTML = `
    <div class="detail-section">
      <div style="text-align:center;padding:14px 0 18px">
        <div style="width:54px;height:54px;border-radius:50%;background:var(--rose-50);color:var(--rose-600);display:flex;align-items:center;justify-content:center;font-size:18px;font-weight:700;margin:0 auto 8px">${vid==='bautista'?'RB':'ER'}</div>
        <div style="font-size:15px;font-weight:600;color:var(--slate-900)">${vid==='bautista'?'Ramon Bautista':'Eduardo Ramos'}</div>
        <div style="font-size:12px;color:var(--slate-400);margin-top:2px">123 Mabini St. · ID: VIO-2024-001</div>
        <div style="margin-top:8px"><span class="risk-badge risk-${vid==='bautista'?'critical':'high'}">● ${vid==='bautista'?'Critical':'High'} Risk</span></div>
      </div>
    </div>
    <div class="detail-section">
      <div class="detail-section-title">Violation Summary</div>
      <div class="detail-row"><span class="detail-key">Total Cases</span><span class="detail-val" style="font-weight:700;color:var(--rose-600)">${vid==='bautista'?6:4}</span></div>
      <div class="detail-row"><span class="detail-key">Missed Hearings</span><span class="detail-val" style="font-weight:700;color:var(--rose-600)">${vid==='bautista'?3:2}</span></div>
      <div class="detail-row"><span class="detail-key">Unpaid Fines</span><span class="detail-val" style="font-weight:700;color:var(--rose-600)">${vid==='bautista'?'₱1,500':'₱700'}</span></div>
      <div class="detail-row"><span class="detail-key">Risk Score</span><span class="detail-val">${vid==='bautista'?92:74}/100</span></div>
    </div>
    <div class="detail-section">
      <div class="detail-section-title">Forecast Analysis</div>
      <div style="padding:12px;background:var(--rose-50);border-radius:var(--r-md);border-left:3px solid var(--rose-400);margin-bottom:10px">
        <div style="font-size:12px;font-weight:600;color:var(--rose-700)">High probability of repeat violation</div>
        <div style="font-size:11px;color:var(--rose-600);margin-top:3px">Pattern indicates ${vid==='bautista'?'domestic disturbance':'physical altercation'} likely within 30–60 days if no intervention.</div>
      </div>
      <button class="btn btn-outline btn-sm" style="width:100%" onclick="showToast('Intervention notice sent.')">Send Intervention Notice</button>
    </div>
    <div class="detail-section">
      <div class="detail-section-title">Case History</div>
      <ul class="timeline">
        <li class="tl-item"><div class="tl-dot tl-dot-rose"></div><div class="tl-label">${vid==='bautista'?'Domestic / VAWC':'Physical Altercation'} — #2026-003${vid==='bautista'?9:8}</div><div class="tl-meta">Mar 19, 2026 · ${vid==='bautista'?'Critical':'Serious'}</div></li>
        <li class="tl-item"><div class="tl-dot tl-dot-amber"></div><div class="tl-label">Missed 2nd mediation hearing</div><div class="tl-meta">Mar 5, 2026</div></li>
        <li class="tl-item"><div class="tl-dot tl-dot-amber"></div><div class="tl-label">${vid==='bautista'?'Verbal Abuse':'Noise Disturbance'}</div><div class="tl-meta">Jan 14, 2026 · Moderate</div></li>
        <li class="tl-item"><div class="tl-dot tl-dot-blue"></div><div class="tl-label">First recorded violation</div><div class="tl-meta">Jun 2024</div></li>
      </ul>
    </div>`;

  panel.classList.add('open');
}

// ── INIT ──
document.addEventListener('DOMContentLoaded', () => {
  initDashboard();
  renderSanctions();
});
