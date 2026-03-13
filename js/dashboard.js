const isMobile = () => window.innerWidth < 768;

// ─── DASHBOARD JS — exact JSX conversion ───────────────────────────────────

let PAWNS = [];
let SHOPS = [];
let currentPage = 'dashboard';
let pendingDeleteId = null;
let pendingPayHistPawn = null;
let pendingExtendShop = null;
const fmt = n => '₹' + Number(n).toLocaleString('en-IN');

const PAGE_TITLES = {
  dashboard:'Dashboard', shops:'Shops List', subscriptions:'Subscriptions',
  search:'Customer Search', reports:'Reports', audit:'Audit Logs',
  chat:'Private Chat', 'add-pawn':'New Bandhak Entry',
  pawns:'All Bandhak', payments:'Payments',
  subscription:'Subscription', 'my-items':'Meri Items',
  'payment-history':'Payment History', terms:'Terms & Conditions',
  calculator:'Interest Calculator'
};

// ─── INIT ─────────────────────────────────────────────────────

// ─── MOBILE CARD TOGGLE ──────────────────────────────────────
function toggleShopCard(mainDiv) {
  const card = mainDiv.closest('.m-card');
  const detail = card.querySelector('.m-card-detail');
  const isOpen = detail.style.display !== 'none';
  detail.style.display = isOpen ? 'none' : 'block';
  card.classList.toggle('m-card-open', !isOpen);
}

// ─── FAB BUTTON ACTIONS ──────────────────────────────────────
function openAddPawnModal() {
  if (ROLE === 'shop') {
    loadPage('add-pawn');
  } else if (ROLE === 'admin') {
    loadPage('shops');
  }
}

// Re-render shops on resize (mobile ↔ desktop switch)
let _shopResizeTimer = null;
window.addEventListener('resize', () => {
  clearTimeout(_shopResizeTimer);
  _shopResizeTimer = setTimeout(() => {
    const wrap = document.getElementById('shopTableOrCards');
    if (wrap) renderShopsList(SHOPS.length ? SHOPS : []);
  }, 200);
});

window.addEventListener('load', () => {
  // Apply saved theme preference
  const savedMode = localStorage.getItem('darkMode');
  applyDarkMode(savedMode !== '0');
  
  document.body.classList.add('logged-in');
  // Support button hide - login ke baad
  const _fs = document.getElementById('floatSupport');
  if (_fs) _fs.style.setProperty('display','none','important');
  // Customer ke liye bottom nav hide karo
  if (ROLE === 'customer') {
    document.body.classList.add('customer-mode');
    const _bn = document.getElementById('bottomNav');
    if (_bn) _bn.style.setProperty('display','none','important');
  }

  loadPawns().then(() => {
    loadPage('dashboard');
    if (ROLE === 'shop') checkShopSubscription();
    const bn=document.getElementById('bottomNav'); if(bn){bn.style.display=window.innerWidth<768?'flex':'none'; if(ROLE==='admin'&&typeof setAdminBottomNav==='function')setAdminBottomNav();}
  });
  checkChatUnread();
  loadNotifDot();
});

async function loadPawns() {
  try {
    const url = ROLE === 'admin' 
      ? 'php/api.php?action=get_pawns&shop_id=all'
      : 'php/api.php?action=get_pawns';
    const res = await fetch(url);
    const d = await res.json();
    if (d.ok) PAWNS = d.pawns || [];
  } catch(e) {}
}

async function checkShopSubscription() {
  try {
    const res = await fetch('php/api.php?action=get_shop_sub&shop_id='+SHOP_ID);
    const d = await res.json();
    if (!d.ok) return;
    const sub = d.sub || d;
    const expiry = sub.expiry || sub.sub_expiry || '';
    const plan   = (sub.plan || sub.subscription || '').toLowerCase();
    if (!expiry) return;
    
    const today  = new Date().toISOString().split('T')[0];
    const isExp  = expiry < today;
    const daysLeft = Math.ceil((new Date(expiry) - new Date()) / 86400000);
    const isTrial  = plan.includes('trial');
    
    if (isExp) {
      showSubExpiredBanner(plan, expiry);
    } else if (daysLeft <= 7) {
      showSubWarnBanner(daysLeft, expiry);
    }
  } catch(e) {}
}

function showSubExpiredBanner(plan, expiry) {
  const addBtn = document.getElementById('nav-add-pawn');
  if (addBtn) { addBtn.style.opacity='0.4'; addBtn.style.pointerEvents='none'; }
  const modal = document.createElement('div');
  modal.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,.85);z-index:99999;display:flex;align-items:center;justify-content:center;padding:20px';
  const p = plan||'Trial';
  modal.innerHTML = '<div style="background:var(--card);border:2px solid var(--red);border-radius:20px;padding:28px;max-width:400px;width:100%;text-align:center">'
    + '<div style="font-size:48px">⛔</div>'
    + '<div style="font-size:18px;font-weight:900;color:var(--red);margin:10px 0">Subscription Expire Ho Gayi!</div>'
    + '<div style="font-size:13px;color:var(--muted);margin-bottom:14px">Plan: <b>' + p + '</b> | Expiry: <b style="color:var(--red)">' + expiry + '</b></div>'
    + '<div style="background:rgba(231,76,60,.1);border:1px solid rgba(231,76,60,.3);border-radius:12px;padding:12px;margin-bottom:16px;font-size:12px;text-align:left">'
    + '<b>Nayi entries band hain</b><br>Purani entries aur history dekh sakte hain<br>Renew ke liye admin se contact karein: 6206869543</div>'
    + '<button class="btnP" onclick="openRenewModal();this.parentElement.parentElement.remove()">Renew Karein</button>'
    + '<br><button class="bs bsg" style="margin-top:8px;width:100%" onclick="this.parentElement.parentElement.remove()">Close</button>'
    + '</div>';
  document.body.appendChild(modal);
}

function showSubWarnBanner(daysLeft, expiry) {
  if (document.getElementById('subWarnBanner')) return;
  const banner = document.createElement('div');
  banner.id = 'subWarnBanner';
  banner.style.cssText = 'position:fixed;top:56px;left:0;right:0;z-index:500;background:linear-gradient(135deg,#FF6B00,#FF8C00);color:#fff;padding:10px 16px;display:flex;align-items:center;justify-content:space-between;gap:10px;font-size:13px;font-weight:700';
  const txt = document.createElement('span');
  txt.textContent = 'Subscription sirf ' + daysLeft + ' din baad expire hogi (' + expiry + ')';
  const btns = document.createElement('div');
  btns.style.cssText = 'display:flex;gap:8px';
  const r = document.createElement('button');
  r.textContent = 'Renew';
  r.style.cssText = 'background:rgba(255,255,255,.2);border:1px solid rgba(255,255,255,.4);color:#fff;padding:5px 12px;border-radius:8px;cursor:pointer;font-weight:700';
  r.onclick = openRenewModal;
  const x = document.createElement('button');
  x.textContent = '×';
  x.style.cssText = 'background:none;border:none;color:#fff;cursor:pointer;font-size:18px';
  x.onclick = function(){ document.getElementById('subWarnBanner').remove(); };
  btns.appendChild(r); btns.appendChild(x);
  banner.appendChild(txt); banner.appendChild(btns);
  document.body.appendChild(banner);
}

function loadPage(page) {
  currentPage = page;
  window.currentPage = page;
  document.getElementById('pageTitle').textContent = PAGE_TITLES[page] || 'Dashboard';
  document.querySelectorAll('.nv').forEach(n => n.classList.remove('active'));
  const navEl = document.getElementById('nav-'+page);
  if (navEl) navEl.classList.add('active');
  if (typeof syncBottomNav==='function') syncBottomNav(page);
  closeSidebar();
  
  const content = document.getElementById('pageContent');
  if (ROLE === 'admin') {
    if (page==='dashboard') renderAdminDash(content);
    else if (page==='shops') renderShopsPage(content);
    else if (page==='subscriptions') renderSubMgmt(content);
    else if (page==='search') renderSearchPage(content);
    else if (page==='reports') renderReportsPage(content);
    else if (page==='audit') renderAuditPage(content);
    else if (page==='chat') renderChatPage(content);
    else if (page==='calculator') renderCalculatorPage(content);
  } else if (ROLE === 'shop') {
    if (page==='dashboard') renderShopDash(content);
    else if (page==='add-pawn') renderAddPawnFlow(content);
    else if (page==='pawns') renderAllBandhakPage(content);
    else if (page==='payments') renderPaymentsPage(content);
    else if (page==='search') renderSearchPage(content);
    else if (page==='reports') renderReportsPage(content);
    else if (page==='calculator') renderCalculatorPage(content);
    else if (page==='subscription') renderShopSub(content);
    else if (page==='terms') renderTermsPage(content);
    else if (page==='chat') renderChatPage(content);
  } else if (ROLE === 'customer') {
    renderCustomerView(content);
  }
}

// ─── SIDEBAR / LOGOUT ─────────────────────────────────────────
function toggleSidebar() {
  const sb = document.getElementById('sidebar');
  const ov = document.getElementById('sbOverlay') || document.getElementById('overlay');
  sb.classList.toggle('open');
  if (ov) ov.classList.toggle('active', sb.classList.contains('open'));
  if (ov) ov.classList.toggle('open', sb.classList.contains('open'));
}
function closeSidebar() {
  document.getElementById('sidebar')?.classList.remove('open');
  ['sbOverlay','overlay'].forEach(id => {
    const el = document.getElementById(id);
    if (el) { el.classList.remove('open'); el.classList.remove('active'); }
  });
}
async function doLogout() {
  const fd = new FormData(); fd.append('action','logout');
  await fetch('php/auth.php',{method:'POST',body:fd});
  location.reload();
}
function showToast(msg) {
  const t = document.getElementById('toast');
  document.getElementById('toastMsg').textContent = msg;
  t.style.display = 'flex';
  setTimeout(()=>t.style.display='none', 3000);
}

// ─── ADMIN DASHBOARD ──────────────────────────────────────────
async function renderAdminDash(el) {
  el.innerHTML = `<div class="pb" style="max-width:100%;overflow-x:hidden;box-sizing:border-box">
    <div class="sg" id="adminStatGrid">
      ${[['🏪','...','Total Shops',''],['✅','...','Active Shops',''],
         ['👥','...','Total Customers',''],['📦','...',t('active_bandhak','Active Bandhak'),''],
         ['💬','...','Unread Chats','']].map(([ic,v,l,c])=>`
        <div class="sc"><div style="font-size:20px;margin-bottom:7px">${ic}</div>
          <div class="sv">${v}</div><div class="sl">${l}</div></div>`).join('')}
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px" id="adminMiniCards">
      <div class="card" style="cursor:pointer" onclick="loadPage('shops')">
        <div class="cb" style="padding:14px;text-align:center">
          <div style="font-size:28px">🏪</div>
          <div style="font-size:13px;font-weight:800;margin-top:6px">${t('shops_manage','Shops Manage')}</div>
          <div style="font-size:11px;color:var(--muted)">${t('shops_manage_sub','Add, edit, suspend shops')}</div>
        </div>
      </div>
      <div class="card" style="cursor:pointer" onclick="loadPage('subscriptions')">
        <div class="cb" style="padding:14px;text-align:center">
          <div style="font-size:28px">💳</div>
          <div style="font-size:13px;font-weight:800;margin-top:6px">${t('subscriptions','Subscriptions')}</div>
          <div style="font-size:11px;color:var(--muted)">${t('subscriptions_sub','Plans, renewals, offers')}</div>
        </div>
      </div>
    </div>
    <div class="card">
      <div class="ch"><div class="ct">📋 Recent Audit Logs</div><span class="b bg" id="liveIndicator">⏳ Loading...</span></div>
      <div class="oa" id="adminRecentActivity"><div style="text-align:center;padding:20px;color:var(--muted)">Loading live data...</div></div>
    </div>
  </div>`;
  
  // Load live stats from DB
  try {
    const [statsRes, auditRes] = await Promise.all([
      fetch('php/api.php?action=get_admin_stats'),
      fetch('php/api.php?action=get_audit&limit=10')
    ]);
    const [stats, audit] = await Promise.all([statsRes.json(), auditRes.json()]);
    
    if (stats.ok) {
      const s = stats.stats;
      const grid = document.getElementById('adminStatGrid');
      if (grid) grid.innerHTML = [
        ['🏪', s.total_shops||0, t('total_shops','Total Shops'), s.new_shops_month ? '+'+s.new_shops_month+(window._currentLang==='hi'?' इस माह':' this month') : ''],
        ['✅', s.active_shops||0, t('active_shops','Active Shops'), Math.round((s.active_shops||0)/(s.total_shops||1)*100)+'% '+(window._currentLang==='hi'?'सक्रिय':'active')],
        ['👥', s.total_customers||0, t('total_customers','Total Customers'), window._currentLang==='hi'?'पंजीकृत बंधक':'Registered pawns'],
        ['📦', s.active_pawns||0, t('active_bandhak','Active Bandhak'), fmt(s.total_loan||0)+' '+(window._currentLang==='hi'?'कुल लोन':'total loan')],
        ['💰', fmt(s.total_revenue||0), t('total_collected','Total Collected'), fmt(s.pending_amount||0)+' '+(window._currentLang==='hi'?'बाकी':'pending')],
      ].map(([ic,v,l,c])=>`
        <div class="sc"><div style="font-size:20px;margin-bottom:7px">${ic}</div>
          <div class="sv">${v}</div><div class="sl">${l}</div><div class="sch" style="font-size:11px;color:var(--muted)">${c}</div></div>`).join('');
      
      const ind = document.getElementById('liveIndicator');
      if (ind) { ind.textContent = '● Live'; ind.className = 'b bg'; }
    }
    
    if (audit.ok && audit.logs) {
      const actEl = document.getElementById('adminRecentActivity');
      if (actEl) {
        if (!audit.logs.length) {
          actEl.innerHTML = '<div style="text-align:center;padding:20px;color:var(--muted)">Koi activity nahi abhi tak</div>';
        } else {
          actEl.innerHTML = '<table class="dt"><thead><tr><th>'+t('time','Time')+'</th><th>'+t('action','Action')+'</th><th>'+t('user','User')+'</th><th>'+t('shop','Shop')+'</th><th>'+t('target','Target')+'</th></tr></thead><tbody>'+
            audit.logs.map(l=>{
              const t = l.created_at ? new Date(l.created_at) : new Date();
              const ago = getTimeAgo(t);
              return `<tr>
                <td style="color:var(--muted);white-space:nowrap">${ago}</td>
                <td style="font-weight:700">${l.action||''}</td>
                <td>${l.user_name||''}</td>
                <td style="color:var(--muted)">${l.shop_id||''}</td>
                <td style="color:var(--muted)">${l.target||''}</td>
              </tr>`;
            }).join('')+
          '</tbody></table>';
        }
      }
    }
  } catch(e) {
    const ind = document.getElementById('liveIndicator');
    if (ind) { ind.textContent = '⚠️ Demo Mode'; ind.className = 'b by'; }
    const actEl = document.getElementById('adminRecentActivity');
    if (actEl) actEl.innerHTML = '<div style="text-align:center;padding:20px;color:var(--muted)">Database connect nahi hai — demo mode mein hain</div>';
  }
}

function getTimeAgo(date) {
  const diff = Math.floor((Date.now() - date.getTime()) / 1000);
  if (diff < 60) return diff+'s ago';
  if (diff < 3600) return Math.floor(diff/60)+'m ago';
  if (diff < 86400) return Math.floor(diff/3600)+'h ago';
  return Math.floor(diff/86400)+'d ago';
}

// ─── SHOPS PAGE (Admin) ───────────────────────────────────────
async function renderShopsPage(el) {
  el.innerHTML = `<div class="pb"><div style="text-align:center;padding:40px;color:var(--muted)">Loading...</div></div>`;
  try {
    const res = await fetch('php/api.php?action=get_shops');
    const d = await res.json();
    if (d.ok) SHOPS = d.shops;
  } catch(e) {}
  
  const shops = SHOPS.length ? SHOPS : [];
  
  el.innerHTML = `<div class="pb">
    <div style="display:flex;gap:9px;margin-bottom:15px;flex-wrap:wrap">
      <input class="fi" placeholder="🔍 Search shops..." id="shopSearch" oninput="filterShops()" style="max-width:270px;flex:1">
      <select class="si" style="width:auto;padding:10px 12px" id="shopFilter" onchange="filterShops()">
        <option value="all">All</option><option value="active">Active</option><option value="inactive">Inactive</option>
      </select>
      <button class="bs bsp" onclick="exportShops()">📥 Export</button>
    </div>
    <div class="card">
      <div class="ch">
        <div class="ct">🏪 Shops (${shops.length})</div>
        <span style="font-size:11px;color:var(--muted)">${shops.length} total</span>
      </div>
      ${shops.length===0 ? '<div style="text-align:center;padding:32px;color:var(--muted);font-size:13px">Koi shop registered nahi hai</div>' : ''}
      
      <!-- Shops rendered by JS: table on desktop, cards on mobile -->
      <div id="shopTableOrCards"></div>
    </div>
  </div>`;
  // Render shops table or cards
  setTimeout(() => renderShopsList(shops), 0);
}

function renderShopsList(shops) {
  const wrap = document.getElementById('shopTableOrCards');
  if (!wrap) return;
  if (isMobile()) {
    wrap.innerHTML = '';
    if (!shops.length) {
      wrap.innerHTML = '<div style="text-align:center;padding:32px;color:var(--muted)">Koi shop nahi hai</div>';
      return;
    }
    shops.forEach(s => {
      const sc = s.subscription==='Standard'||s.subscription==='Active'?'bg':s.subscription==='Trial'?'by':'br';
      const card = document.createElement('div');
      card.className = 'm-card';
      card.id = 'shoprow-'+s.id;
      card.dataset.sid = s.id;
      card.innerHTML =
        '<div class="m-card-main" onclick="toggleShopCard(this)">'
        + '<div style="display:flex;align-items:center;gap:10px">'
        + '<div style="width:40px;height:40px;border-radius:12px;background:rgba(255,107,0,.12);display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0">🏪</div>'
        + '<div style="flex:1;min-width:0">'
        + '<div style="font-weight:800;font-size:14px">' + (s.name||s.shop_name||s.id) + '</div>'
        + '<div style="font-size:11px;color:var(--muted)">' + (s.owner_name||'') + (s.city?' · '+s.city:'') + '</div>'
        + '</div>'
        + '<div style="display:flex;flex-direction:column;align-items:flex-end;gap:4px">'
        + '<span class="b ' + (s.status==='active'?'bg':'br') + '">' + s.status + '</span>'
        + '<span class="b ' + sc + '" style="font-size:10px">' + (s.subscription||'') + '</span>'
        + '</div></div>'
        + '<div class="m-card-meta">'
        + (s.city ? '<span>📍 '+s.city+'</span>' : '')
        + '<span>📅 '+(s.sub_expiry||s.expiry||'—')+'</span>'
        + '<span style="color:var(--saffron)">' + s.id + '</span>'
        + '</div></div>'
        + '<div class="m-card-detail" style="display:none">'
        + '<div style="font-size:12px;margin-bottom:8px;color:var(--muted)">Owner: <b style="color:var(--text)">'+(s.owner_name||'—')+'</b></div>'
        + '<div class="m-card-actions">'
        + '<button class="m-act-btn m-act-green" data-sid="'+s.id+'" onclick="updateShopStatus(this.dataset.sid,&quot;active&quot;)">✅ Activate</button>'
        + '<button class="m-act-btn m-act-red" data-sid="'+s.id+'" onclick="updateShopStatus(this.dataset.sid,&quot;inactive&quot;)">🚫 Block</button>'
        + '<button class="m-act-btn m-act-blue" data-sid="'+s.id+'" onclick="viewShopDetail(this.dataset.sid)">👁️ Detail</button>'
        + '</div></div>';
      wrap.appendChild(card);
    });
  } else {
    const rows = shops.map(s => {
      const sc = s.subscription==='Standard'||s.subscription==='Active'?'bg':s.subscription==='Trial'?'by':'br';
      return `<tr id="shoprow-${s.id}">
        <td><span style="color:var(--saffron);font-weight:800;font-size:11px">${s.id}</span></td>
        <td style="font-weight:700">${s.name||s.shop_name||''}</td>
        <td style="font-size:12px">${s.owner_name||''}</td>
        <td style="font-size:12px;color:var(--muted)">${s.city||''}</td>
        <td><span class="b ${sc}">${s.subscription||''}</span></td>
        <td style="color:var(--muted);font-size:11px">${s.sub_expiry||s.expiry||''}</td>
        <td><span class="b ${s.status==='active'?'bg':'br'}" id="st-${s.id}">● ${s.status}</span></td>
        <td><div style="display:flex;gap:4px">
          <button class="bs bsg" style="font-size:11px;padding:4px 8px" onclick="updateShopStatus('${s.id}','active')">✅</button>
          <button class="bs bsr" style="font-size:11px;padding:4px 8px" onclick="updateShopStatus('${s.id}','inactive')">🚫</button>
          <button class="bs bb" style="font-size:11px;padding:4px 8px" onclick="viewShopDetail('${s.id}')">👁️ View</button>
        </div></td></tr>`;
    }).join('');
    wrap.innerHTML = '<div style="overflow-x:auto"><table class="dt" id="shopsTable">'
      + '<thead><tr><th>ID</th><th>Shop</th><th>Owner</th><th>City</th><th>Sub</th><th>Expiry</th><th>Status</th><th>Actions</th></tr></thead>'
      + '<tbody>' + rows + '</tbody></table></div>';
  }
}

function toggleShopCard(el) {
  const card = el.closest('.m-card');
  const det  = card.querySelector('.m-card-detail');
  const open = det.style.display !== 'none';
  det.style.display = open ? 'none' : 'block';
  card.classList.toggle('m-card-open', !open);
}


function filterShops() {
  const q = document.getElementById('shopSearch').value.toLowerCase();
  const f = document.getElementById('shopFilter').value;
  document.querySelectorAll('#shopsTable tbody tr').forEach(row => {
    const text = row.textContent.toLowerCase();
    const active = row.querySelector('[id^="st-"]')?.textContent.includes('active') && !row.querySelector('[id^="st-"]')?.textContent.includes('inactive');
    const status = row.querySelector('[id^="st-"]')?.textContent.includes('active') ? 'active' : 'inactive';
    const show = (!q || text.includes(q)) && (f==='all' || status===f);
    row.style.display = show ? '' : 'none';
  });
}

async function updateShopStatus(shopId, status) {
  const fd = new FormData();
  fd.append('action','update_shop_status');
  fd.append('shop_id', shopId);
  fd.append('status', status);
  try {
    await fetch('php/api.php',{method:'POST',body:fd});
    const span = document.getElementById('st-'+shopId);
    if (span) { span.textContent='● '+status; span.className='b '+(status==='active'?'bg':'br'); }
    showToast(`Shop ${status==='active'?'activated':'blocked'}!`);
  } catch(e) {}
}

async function adminViewShopBandhak(shopId, shopName) {
  document.getElementById('dynModal').style.display = 'none';
  const el = document.getElementById('dynModalContent');
  if (!el) return;
  el.innerHTML = '<div style="padding:30px;text-align:center"><span class="spin" style="width:28px;height:28px;border-width:3px"></span><div style="margin-top:10px;color:var(--muted)">Loading bandhak...</div></div>';
  document.getElementById('dynModal').style.display = 'flex';
  
  try {
    const res = await fetch('php/api.php?action=get_pawns&shop_id='+shopId);
    const d = await res.json();
    const pawns = d.pawns || [];
    
    el.innerHTML = `<div>
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px">
        <div style="font-size:17px;font-weight:800">📦 ${shopName} — Bandhak (${pawns.length})</div>
        <button class="bs bsg" onclick="document.getElementById('dynModal').style.display='none'">✕</button>
      </div>
      ${pawns.length === 0 
        ? '<div style="text-align:center;padding:30px;color:var(--muted)">Koi bandhak entry nahi</div>' 
        : pawns.map(p => `
          <div style="background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:13px;margin-bottom:10px">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:8px">
              <div>
                <div style="font-weight:800;font-size:14px">${p.customer||p.customer_name||'—'}</div>
                <div style="font-size:11px;color:var(--muted)">${p.id} · ${p.mobile||p.customer_mobile||'—'}</div>
              </div>
              <span class="b ${p.status==='active'?'bg':p.status==='redeemed'?'bb':'br'}" style="font-size:11px">${p.status||'—'}</span>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px;font-size:12px;margin-bottom:8px">
              <div><span style="color:var(--muted)">Item:</span> ${p.item||p.item_description||'—'}</div>
              <div><span style="color:var(--muted)">Loan:</span> <b style="color:var(--saffron)">₹${Number(p.loan||p.loan_amount||0).toLocaleString('en-IN')}</b></div>
              <div><span style="color:var(--muted)">Date:</span> ${p.date||p.loan_date||'—'}</div>
              <div><span style="color:var(--muted)">Interest:</span> ${p.interest||p.interest_rate||0}%/mo</div>
            </div>
            ${p.item_photo ? `<img src="${p.item_photo}" style="max-width:100%;max-height:120px;border-radius:8px;object-fit:cover">` : ''}
          </div>`).join('')
      }</div>`;
  } catch(e) {
    el.innerHTML = '<div style="padding:30px;text-align:center;color:var(--red)">❌ Load failed</div>';
  }
}

function viewShopDetail_bak(shopId) {
  showToast('Shop: '+shopId+' — detail view');
}

function exportShops() {
  if (!SHOPS || !SHOPS.length) { showToast('❌ Pehle shops load karein'); return; }
  const headers = ['ID','Shop Name','Owner','City','State','Mobile','Email','Status','Plan','Expiry'];
  const rows = SHOPS.map(s => [
    s.id, s.name||'', s.owner_name||s.owner||'', s.city||'', s.state||'',
    s.mobile||'', s.email||'', s.status||'', s.subscription||s.sub||'', s.sub_expiry||s.expiry||''
  ]);
  const csvRows = [headers, ...rows].map(r => r.map(v => '"'+String(v||'').replace(/"/g,'""')+'"').join(','));
  const csv = csvRows.join('\n');
  const blob = new Blob([csv], {type:'text/csv;charset=utf-8;'});
  const a = document.createElement('a');
  a.href = URL.createObjectURL(blob);
  a.download = 'DigitalBandhak_Shops_'+new Date().toISOString().split('T')[0]+'.csv';
  a.click();
  showToast('✅ CSV download ho raha hai!');
}

// ─── SUBSCRIPTION MGMT (Admin) ────────────────────────────────
async function renderSubMgmt(el) {
  const shops = SHOPS.length ? SHOPS : [];
  
  el.innerHTML = `<div class="pb">
    <div class="sg">${[['✅','38','Active'],['🆓','6','Trial'],['❌','3','Expired'],['💰','₹2,400','Avg/yr']].map(([ic,v,l])=>`
      <div class="sc"><div style="font-size:19px;margin-bottom:6px">${ic}</div><div class="sv">${v}</div><div class="sl">${l}</div></div>`).join('')}
    </div>
    
    <!-- GLOBAL FESTIVAL OFFER CARD -->
    <div style="background:linear-gradient(135deg,rgba(255,107,0,.15),rgba(255,179,0,.1));border:2px solid rgba(255,107,0,.4);border-radius:16px;padding:18px;margin-bottom:16px">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px">
        <div>
          <div style="font-size:15px;font-weight:800">🎉 Global Festival Offer</div>
          <div style="font-size:13px;color:var(--muted);margin-top:2px">Sab shops ke liye ek saath discount activate karo</div>
        </div>
        <div id="offerActiveBadge" style="display:none"><span class="b bg">✅ Offer Active</span></div>
      </div>
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:10px;margin-bottom:8px">
        <div class="fg"><label class="fl">Offer Name</label>
          <input class="fi" id="gOfferName" placeholder="e.g. Diwali Offer 2025">
        </div>
        <div class="fg"><label class="fl">Discount (%)</label>
          <input class="fi" type="number" id="gOfferDisc" placeholder="e.g. 10" min="1" max="90" oninput="previewGlobalOffer()">
        </div>
        <div class="fg"><label class="fl">Valid Till</label>
          <input class="fi" type="date" id="gOfferExpiry" value="${new Date(Date.now()+7*86400000).toISOString().split('T')[0]}">
        </div>
        <div class="fg"><label class="fl">Apply To</label>
          <select class="si" id="gOfferTarget">
            <option value="all">Sab Plans</option>
            <option value="new">Sirf Naye Renewals</option>
            <option value="expired">Expired Shops</option>
          </select>
        </div>
      </div>
      <div id="gOfferPreview" style="margin:8px 0;font-size:12px;color:var(--muted)"></div>
      <div class="brow">
        <button class="bs bsp" onclick="activateGlobalOffer()">🎉 Offer Activate Karo</button>
        <button class="bs bsg" onclick="deactivateOffer()">❌ Offer Hatao</button>
      </div>
    </div>
    
    <div class="card"><div class="ch"><div class="ct">📋 Subscriptions</div></div>
      ${isMobile() ? 
        '<div style="padding:8px">' + shops.map(s => {
          const sc = s.subscription==='Standard'||s.subscription==='Active'?'bg':s.subscription==='Trial'?'by':'br';
          return '<div class="m-card">'
            +'<div class="m-card-main" onclick="toggleShopCard(this)">'
            +'<div style="display:flex;align-items:center;gap:10px">'
            +'<div style="flex:1;min-width:0">'
            +'<div style="font-weight:800;font-size:13px">'+(s.name||'')+'</div>'
            +'<div style="font-size:11px;color:var(--muted)">'+(s.id)+'</div>'
            +'</div>'
            +'<div style="display:flex;flex-direction:column;align-items:flex-end;gap:4px">'
            +'<span class="b '+sc+'">'+(s.subscription||'')+'</span>'
            +'<span style="font-size:10px;color:var(--muted)">'+(s.sub_expiry||s.expiry||'')+'</span>'
            +'</div></div>'
            +'</div>'
            +'<div class="m-card-detail" style="display:none">'
            +'<div class="m-card-actions">'
            +'<button class="m-act-btn m-act-orange" data-sid="'+s.id+'" data-sn="'+(s.name||'')+'" data-sp="'+(s.subscription||'')+'" onclick="openExtend(this.dataset.sid,this.dataset.sn,this.dataset.sp)">🔄 Extend</button>'
            +'<button class="m-act-btn m-act-blue" data-sid="'+s.id+'" onclick="giveFreeTrial(this.dataset.sid)">🆓 Trial</button>'
            +'</div>'
            +'</div>'
            +'</div>';
        }).join('') + '</div>'
      :
        '<div style="overflow-x:auto"><table class="dt"><thead><tr><th>Shop</th><th>Plan</th><th>Expiry</th><th>Paid</th><th>Status</th><th>Action</th></tr></thead>'
        +'<tbody>'+shops.map(s=>{
          const sc=s.subscription==='Standard'||s.subscription==='Active'?'bg':s.subscription==='Trial'?'by':'br';
          return '<tr>'
            +'<td><b>'+(s.name||'')+'</b><div style="font-size:11px;color:var(--muted)">'+(s.id)+'</div></td>'
            +'<td><span class="b '+sc+'">'+(s.subscription)+'</span></td>'
            +'<td style="color:var(--muted)">'+(s.sub_expiry||s.expiry||'')+'</td>'
            +'<td>'+(s.balance?fmt(s.balance):'₹0')+'</td>'
            +'<td><span class="b '+(s.status==='active'?'bg':'br')+'">● '+(s.subscription)+'</span></td>'
            +'<td><div style="display:flex;gap:4px">'
            +'<button class="bs bsp" style="font-size:11px;padding:4px 7px" onclick="openExtend(\''+s.id+'\',\''+s.name+'\',\''+s.subscription+'\')">🔄</button>'
            +'<button class="bs bsv" style="font-size:11px;padding:4px 7px" onclick="giveFreeTrial(\''+s.id+'\')">🆓</button>'
            +'</div></td>'
            +'</tr>';
        }).join('')+'</tbody></table></div>'
      }
    </div>
  </div>`;
}

function openExtend(shopId, shopName, sub) {
  pendingExtendShop = shopId;
  pendingExtendPlan = sub || 'Standard';
  document.getElementById('extTitle').textContent = '🔄 Extend — '+shopName;
  document.getElementById('extSub').textContent = shopId+' • Current: '+sub;
  calcExtendAmount();
  openModal('modalExtend');
}
async function confirmExtend() {
  const fd = new FormData();
  fd.append('action','extend_sub');
  fd.append('shop_id', pendingExtendShop);
  fd.append('duration', document.getElementById('extDur').value);
  fd.append('amount', document.getElementById('extAmt').value);
  fd.append('mode', document.getElementById('extMode').value);
  try {
    await fetch('php/api.php',{method:'POST',body:fd});
    showToast('✅ Subscription extended!');
  } catch(e) {}
  closeModal('modalExtend');
}
async function giveFreeTrial(shopId) {
  const fd = new FormData();
  fd.append('action','extend_sub');
  fd.append('shop_id', shopId);
  fd.append('duration', '7 Days');
  fd.append('amount', '0');
  fd.append('mode', 'Free');
  fd.append('note', 'Free Trial');
  try { await fetch('php/api.php',{method:'POST',body:fd}); } catch(e) {}
  showToast('🆓 Free Trial (7 din) diya gaya — '+shopId);
}

// ─── SHOP DASHBOARD ───────────────────────────────────────────
// ─── ALL BANDHAK PAGE (separate from Dashboard) ────────────
function renderAllBandhakPage(el) {
  const active = PAWNS.filter(p=>p.status==='active');
  const closed = PAWNS.filter(p=>p.status==='closed');
  const totalLoan = PAWNS.reduce((s,p)=>s+(+p.loan||+p.loan_amount||0),0);
  const totalRem  = active.reduce((s,p)=>s+(+p.remaining||+p.total_remaining||0),0);
  
  el.innerHTML = `<div class="pb">
    <div class="sg" style="margin-bottom:14px">
      ${[['📦',active.length,t('active_bandhak','Active Bandhak'),'var(--saffron)'],
         ['✅',closed.length,t('closed_bandhak','Closed'),'var(--green)'],
         ['💰',fmt(totalLoan),t('total_loan','Total Loan'),'var(--gold)'],
         ['⏳',fmt(totalRem),t('total_rem','Remaining'),'var(--red)']].map(([ic,v,l,c])=>`
        <div class="sc"><div style="font-size:20px;margin-bottom:6px">${ic}</div>
          <div class="sv" style="color:${c}">${v}</div><div class="sl">${l}</div></div>`).join('')}
    </div>
    
    <!-- Filter Row -->
    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px;align-items:center">
      <input class="fi" id="bandhakSearch" placeholder="🔍 Customer naam / ID / item..." 
        oninput="filterBandhakTable()" style="flex:1;min-width:200px">
      <select class="si" id="bandhakFilter" onchange="filterBandhakTable()" style="width:130px">
        <option value="all">Sab</option>
        <option value="active">Active</option>
        <option value="closed">Closed</option>
      </select>
      <button class="bs bsp" onclick="loadPage('add-pawn')" style="white-space:nowrap">➕ New Entry</button>
    </div>
    
    <div class="card">
      <div class="ch">
        <div class="ct">📦 All Bandhak</div>
        <button class="bs bsg" style="font-size:12px" onclick="exportPDF()">📥 Export CSV</button>
      </div>
      <div class="oa" id="bandhakTableWrap">${pawnsTable(PAWNS, false)}</div>
    </div>
  </div>`;
}

function filterBandhakTable() {
  const q = (document.getElementById('bandhakSearch')?.value||'').toLowerCase();
  const f = document.getElementById('bandhakFilter')?.value || 'all';
  let filtered = PAWNS.filter(p => {
    const str = (p.id+(p.customer||p.customer_name||'')+(p.mobile||p.customer_mobile||'')+(p.item||p.item_description||'')).toLowerCase();
    const matchQ = !q || str.includes(q);
    const matchF = f==='all' || p.status===f;
    return matchQ && matchF;
  });
  const wrap = document.getElementById('bandhakTableWrap');
  if (wrap) wrap.innerHTML = pawnsTable(filtered, false);
}


function renderShopDash(el) {
  const active = PAWNS.filter(p=>p.status==='active');
  const closed = PAWNS.filter(p=>p.status==='closed');
  const totalLoan = PAWNS.reduce((s,p)=>s+(+p.loan||+p.loan_amount||0),0);
  const totalRem  = active.reduce((s,p)=>s+(+p.remaining||+p.total_remaining||0),0);
  
  el.innerHTML = `<div class="pb">
    <div class="subc" id="shopSubCard">
      <div style="text-align:center;padding:8px;color:var(--muted);font-size:13px">⏳ Loading...</div>
    </div>
    <div class="sg">
      ${[['📦',active.length,t('active_bandhak','Active Bandhak')],['✅',closed.length,t('closed_bandhak','Closed')],
         ['💰',fmt(totalLoan),t('total_loan','Total Loan')],['⏳',fmt(totalRem),t('total_rem','Remaining')]].map(([ic,v,l])=>`
        <div class="sc"><div style="font-size:19px;margin-bottom:6px">${ic}</div>
          <div class="sv">${v}</div><div class="sl">${l}</div></div>`).join('')}
    </div>
    <div class="card">
      <div class="ch">
        <div class="ct">📦 ${t('all_bandhak','All Bandhak')}</div>
        <button class="bs bsp" onclick="loadPage('add-pawn')">+ ${t('new_entry','New Entry')}</button>
      </div>
      ${pawnsTable(PAWNS, false)}
    </div>
  </div>`;
}

// ─── PAWNS TABLE (desktop table + mobile cards) ───────────────
function pawnsTable(pawns, readOnly=false) {
  if (!pawns.length) return '<div style="text-align:center;padding:30px;color:var(--muted)">'+t('no_data','Koi bandhak entry nahi hai')+'</div>';
  
  const rows = pawns.map(p=>{
    const loan = +p.loan || +p.loan_amount || 0;
    const paid = +p.paid || +p.total_paid || 0;
    const rem  = +p.remaining || +p.total_remaining || 0;
    const payCount = p.payments?.length || +p.pay_count || 0;
    const cust = p.customer || p.customer_name || '';
    const mob  = p.mobile || p.customer_mobile || '';
    const item = p.item || p.item_description || '';
    const weight = p.item_weight || '';
    const ldate = p.loan_date || p.date || '';
    const pct = loan>0 ? Math.min(100,Math.round(paid/loan*100)) : 0;
    
    // Parse photos
    let photos = [];
    try { 
      const raw = p.item_photos || p.photos || '[]';
      photos = typeof raw==='string' ? JSON.parse(raw) : (Array.isArray(raw)?raw:[]); 
    } catch(e){}
    const firstPhoto = photos[0] || '';

    // PDF onclick
    const pdfOnclick = `event.stopPropagation();(async()=>{let pw=PAWNS.find(x=>x.id==='${p.id}');if(!pw){try{const r=await fetch('php/api.php?action=get_payments&pawn_id=${p.id}');const d=await r.json();if(d.ok)pw={...d.pawn,payments:d.payments};}catch(e){}}if(pw)generatePawnPDF(pw);else showToast('Data load failed');})()`; 
    
    // Desktop table row
    const tableRow = `<tr>
      <td><span class="b bo" style="font-size:12px">${p.id}</span></td>
      <td style="font-weight:700">${cust}<div style="font-size:12px;color:var(--muted)">${(mob+'').replace(/\d(?=\d{4})/g,'X')}</div></td>
      <td>${item}${weight?`<div style="font-size:12px;color:var(--muted)">${weight}</div>`:''}</td>
      <td style="font-weight:700">${fmt(loan)}</td>
      <td style="color:var(--green)">${fmt(paid)}</td>
      <td style="color:${rem===0?'var(--green)':'var(--red)'};font-weight:800">${fmt(rem)}</td>
      <td>
        ${(()=>{
          const today = new Date();
          const ld = p.loan_date||p.date||'';
          const rd = p.return_date||'';
          const daysSince = ld ? Math.floor((today-new Date(ld))/86400000) : 0;
          const daysLeft  = rd ? Math.ceil((new Date(rd)-today)/86400000) : null;
          const hasDur    = !!(+p.duration_days>0 || rd);
          if (p.status==='closed') return '<span class="b br" style="font-size:11px">🔴 Closed</span>';
          if (!hasDur) return '<span class="b by" style="font-size:11px">🟡 '+daysSince+'d elapsed</span>';
          if (daysLeft!==null && daysLeft<0) return '<span class="b bsr" style="font-size:11px">⚠️ '+Math.abs(daysLeft)+'d overdue</span>';
          return '<span class="b bg" style="font-size:11px">🟢 '+(daysLeft!==null?daysLeft+'d left':daysSince+'d')+' </span>';
        })()}
        <div><span class="b bb" style="cursor:pointer;font-size:10px" onclick="openPayHist('${p.id}',${readOnly})">${payCount} 💵</span></div>
      </td>
      <td>${(()=>{
        const hasDur = !!(p.duration_days||+p.duration_days>0||p.return_date||(p.duration&&p.duration!=='—'));
        if (p.status==='closed') return '<span class="b br">🔴 Closed</span>';
        if (!hasDur) return '<span class="b by">🟡 Active (no dur)</span>';
        return '<span class="b bg">🟢 Active</span>';
      })()}</td>
      <td><div class="brow" style="gap:4px">
        <button class="bs bsp" style="font-size:12px" onclick="openPayHist('${p.id}',${readOnly})">💵 Pay/History</button>
        <button class="bs" style="background:rgba(231,76,60,.12);color:var(--red);border:1px solid rgba(231,76,60,.25);font-size:12px" onclick="${pdfOnclick}" title="PDF">📥</button>
        ${!readOnly?`<button class="bs bsr" style="font-size:12px" onclick="openDelete('${p.id}','${cust}','${item}')">🗑️</button>`:''}
      </div></td>
    </tr>`;
    
    // Mobile card
    const mobileCard = `<div class="pawn-card" style="background:var(--card);border:1px solid ${p.status==='active'?'rgba(255,107,0,.3)':'rgba(46,204,113,.3)'};border-radius:14px;padding:14px;margin-bottom:10px">
      <!-- Card header row -->
      <div style="display:flex;gap:10px;align-items:flex-start;margin-bottom:10px">
        ${firstPhoto ? `<img src="${firstPhoto}" style="width:60px;height:60px;object-fit:cover;border-radius:10px;flex-shrink:0;border:1px solid var(--border)" onclick="openImgLightbox('${firstPhoto}')">` : 
          `<div style="width:60px;height:60px;background:rgba(255,107,0,.15);border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:24px;flex-shrink:0">📦</div>`}
        <div style="flex:1;min-width:0">
          <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:6px">
            <div>
              <div style="font-size:14px;font-weight:800;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">${cust}</div>
              <div style="font-size:13px;color:var(--muted)">${(mob+'').replace(/\d(?=\d{4})/g,'X')}</div>
            </div>
            <span class="b ${p.status==='closed'?'br':(!p.duration_days&&!p.return_date)?'by':'bg'}" style="flex-shrink:0;font-size:13px">${p.status}</span>
          </div>
          <div style="font-size:12px;color:var(--saffron);font-weight:700;margin-top:3px">${item}${weight?' ('+weight+')':''}</div>
          <div style="font-size:12px;color:var(--muted);margin-top:1px">🆔 ${p.id} &nbsp;•&nbsp; 📅 ${ldate}</div>
        </div>
      </div>
      
      <!-- Amount row -->
      <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:6px;margin-bottom:10px">
        <div style="background:rgba(255,107,0,.08);border-radius:8px;padding:8px;text-align:center">
          <div style="font-size:14px;font-weight:800;color:var(--saffron)">${fmt(loan)}</div>
          <div style="font-size:13px;color:var(--muted)">Loan</div>
        </div>
        <div style="background:rgba(46,204,113,.08);border-radius:8px;padding:8px;text-align:center">
          <div style="font-size:14px;font-weight:800;color:var(--green)">${fmt(paid)}</div>
          <div style="font-size:13px;color:var(--muted)">Paid</div>
        </div>
        <div style="background:${rem===0?'rgba(46,204,113,.08)':'rgba(231,76,60,.08)'};border-radius:8px;padding:8px;text-align:center">
          <div style="font-size:14px;font-weight:800;color:${rem===0?'var(--green)':'var(--red)'}">
            ${rem===0?'✅':fmt(rem)}</div>
          <div style="font-size:13px;color:var(--muted)">Baaki</div>
        </div>
      </div>
      
      <!-- Progress bar -->
      <div style="height:5px;background:var(--deep);border-radius:3px;overflow:hidden;margin-bottom:10px">
        <div style="height:100%;width:${pct}%;background:linear-gradient(to right,var(--saffron),var(--gold));border-radius:3px"></div>
      </div>
      
      <!-- Action buttons -->
      <div style="display:grid;grid-template-columns:1fr auto auto;gap:6px">
        <button class="bs bsp" style="font-size:13px;padding:8px 10px" onclick="openPayHist('${p.id}',${readOnly})">
          💵 Pay/History (${payCount})
        </button>
        <button class="bs" style="background:rgba(255,107,0,.12);color:var(--saffron);border:1px solid rgba(255,107,0,.3);padding:8px 10px" onclick="${pdfOnclick}" title="PDF">📥</button>
        ${!readOnly?`<button class="bs bsr" style="padding:8px 10px" onclick="openDelete('${p.id}','${cust}','${item}')">🗑️</button>`:''}
      </div>
    </div>`;
    
    return { tableRow, mobileCard };
  });
  
  if (isMobile()) {
    return '<div style="padding:8px">' + rows.map(r=>r.mobileCard).join('') + '</div>';
  }
  if (isMobile()) {
    return '<div style="padding:8px">' + rows.map(r=>r.mobileCard).join('') + '</div>';
  }
  return '<div style="overflow-x:auto"><table class="dt"><thead><tr>'
    + '<th>ID</th><th>Customer</th><th>Item</th><th>Loan</th><th>Paid</th><th>Remaining</th><th>Din / Status</th><th>Actions</th>'
    + '</tr></thead><tbody>'
    + rows.map(r=>r.tableRow).join('')
    + '</tbody></table></div>';
}

// ─── PAYMENTS PAGE ────────────────────────────────────────────
function renderPaymentsPage(el) {
  const active = PAWNS.filter(p=>p.status==='active');
  const totalPend  = active.reduce((s,p)=>s+(+p.remaining||+p.total_remaining||0),0);
  const totalColl  = active.reduce((s,p)=>s+(+p.paid||+p.total_paid||0),0);
  
  el.innerHTML = `<div class="pb">
    <div class="sg">
      ${[['💰',fmt(totalPend),'Total Pending'],['📅',fmt(totalColl),'Total Collected'],['⚡',active.length,'Active Accounts']].map(([ic,v,l])=>`
        <div class="sc"><div style="font-size:19px;margin-bottom:6px">${ic}</div><div class="sv">${v}</div><div class="sl">${l}</div></div>`).join('')}
    </div>
    <div class="card">
      <div class="ch"><div class="ct">💵 Payment Collection</div></div>
      
      <!-- Desktop table -->
      <div class="desktop-table oa"><table class="dt"><thead><tr>
        <th>ID</th><th>Customer</th><th>Loan</th><th>Paid</th><th>Remaining</th><th>Payments</th><th>Action</th>
      </tr></thead><tbody>
      ${active.map(p=>{
        const loan=+p.loan||+p.loan_amount||0, paid=+p.paid||+p.total_paid||0;
        const rem=+p.remaining||+p.total_remaining||0;
        const payC=p.payments?.length||+p.pay_count||0;
        const cust=p.customer||p.customer_name||'', mob=p.mobile||p.customer_mobile||'';
        return `<tr>
          <td><span style="color:var(--saffron);font-weight:800;font-size:11px">${p.id}</span></td>
          <td style="font-weight:700">${cust}<div style="font-size:11px;color:var(--muted)">${mob}</div></td>
          <td style="font-weight:700">${fmt(loan)}</td>
          <td style="color:var(--green)">${fmt(paid)}</td>
          <td style="color:var(--red);font-weight:700">${fmt(rem)}</td>
          <td><span class="b bb">${payC}</span></td>
          <td><button class="bs bsp" style="font-size:11px;padding:5px 10px" onclick="openPayHist('${p.id}',false)">💵 Pay</button></td>
        </tr>`;
      }).join('')}
      </tbody></table></div>
      
      <!-- Mobile cards -->
      <div class="mobile-cards" style="padding:8px">${active.map(p=>{
        const loan=+p.loan||+p.loan_amount||0, paid=+p.paid||+p.total_paid||0;
        const rem=+p.remaining||+p.total_remaining||0;
        const payC=p.payments?.length||+p.pay_count||0;
        const cust=p.customer||p.customer_name||'', mob=p.mobile||p.customer_mobile||'';
        const pct=loan>0?Math.min(100,Math.round(paid/loan*100)):0;
        return '<div class="m-card">'
          +'<div class="m-card-main" onclick="toggleShopCard(this)">'
          +'<div style="display:flex;align-items:center;gap:10px">'
          +'<div style="width:36px;height:36px;border-radius:10px;background:rgba(255,107,0,.12);display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0">👤</div>'
          +'<div style="flex:1;min-width:0">'
          +'<div style="font-weight:800;font-size:13px">'+ cust +'</div>'
          +'<div style="font-size:11px;color:var(--muted)">'+ p.id +' · '+ mob +'</div>'
          +'</div>'
          +'<div style="text-align:right;flex-shrink:0;min-width:0;max-width:45%">'
          +'<div style="color:var(--red);font-weight:900;font-size:13px">'+ fmt(rem) +'</div>'
          +'<div style="font-size:10px;color:var(--muted)">remaining</div>'
          +'</div></div>'
          +'<div style="margin-top:8px">'
          +'<div style="display:flex;justify-content:space-between;font-size:11px;color:var(--muted);margin-bottom:3px">'
          +'<span>Loan: <b style="color:var(--text)">'+ fmt(loan) +'</b></span>'
          +'<span>Paid: <b style="color:var(--green)">'+ fmt(paid) +'</b></span>'
          +'<span>'+ pct +'%</span>'
          +'</div>'
          +'<div style="height:4px;background:rgba(255,255,255,.08);border-radius:2px">'
          +'<div style="height:100%;width:'+ pct +'%;background:linear-gradient(90deg,var(--saffron),var(--gold));border-radius:2px"></div>'
          +'</div></div>'
          +'</div>'
          +'<div class="m-card-detail" style="display:none">'
          +'<div style="display:flex;justify-content:space-between;margin-bottom:4px">'
          +'<span style="font-size:11px;color:var(--muted)">'+ payC +' payment(s) made</span>'
          +'</div>'
          +'<div class="m-card-actions">'
          +'<button class="m-act-btn m-act-orange" style="flex:2" onclick="openPayHist(\''+p.id+'\',false)">💵 Pay / History ('+ payC +')</button>'
          +'</div>'
          +'</div>'
          +'</div>';
      }).join('')}</div>
    </div>
  </div>`;
}

// ─── SEARCH PAGE ──────────────────────────────────────────────
function renderSearchPage(el) {
  el.innerHTML = `<div class="pb">
    <div class="card"><div class="ch"><div class="ct">🔍 Customer Search</div><span class="b bo" id="searchCount">${PAWNS.length} results</span></div>
      <div class="cb">
        <div class="fg"><label class="fl">Search – Bandhak ID / Name / Mobile / Item / Date</label>
          <input class="fi" id="searchQ" placeholder="e.g. BDK-2025-001 ya Amit Kumar ya Gold Chain ya 9876..." oninput="doSearch()" style="font-size:14px">
        </div>
        <div class="fg2">
          <div class="fg"><label class="fl">Filter by Date</label><input class="fi" type="date" id="searchDate" onchange="doSearch()"></div>
          <div class="fg"><label class="fl">Status</label>
            <select class="si" id="searchStatus" onchange="doSearch()"><option value="all">Sab</option><option value="active">Active</option><option value="closed">Closed</option></select>
          </div>
          <div class="fg" style="display:flex;align-items:flex-end">
            <button class="bs bsg" onclick="clearSearch()">🔄 Clear</button>
          </div>
        </div>
      </div>
    </div>
    <div id="searchResults"></div>
  </div>`;
  doSearch();
}

function doSearch() {
  const q  = (document.getElementById('searchQ')?.value||'').toLowerCase();
  const ds = document.getElementById('searchDate')?.value||'';
  const st = document.getElementById('searchStatus')?.value||'all';
  
  const list = PAWNS.filter(p => {
    const cust = p.customer||p.customer_name||'';
    const mob  = p.mobile||p.customer_mobile||'';
    const item = p.item||p.item_description||'';
    const date = p.date||p.loan_date||'';
    const s    = p.status||'';
    const str  = (p.id+cust+mob+item+date).toLowerCase();
    return (!q||str.includes(q)) && (st==='all'||s===st) && (!ds||date===ds);
  });
  
  document.getElementById('searchCount').textContent = list.length+' results';
  const res = document.getElementById('searchResults');
  
  if (list.length===0) {
    res.innerHTML = `<div style="text-align:center;padding:48px;color:var(--muted)">
      <div style="font-size:38px;margin-bottom:10px">🔍</div>
      <div style="font-size:14px;font-weight:700">Koi result nahi mila</div></div>`;
    return;
  }
  
  const readOnly = ROLE === 'admin';
  res.innerHTML = `<div class="card">
    <div class="ch"><div class="ct">📦 Results (${list.length})</div>
      <div class="brow"><button class="bs bsg" onclick="exportSearchPDF()">📥 Export PDF</button></div>
    </div>
    ${pawnsTable(list, readOnly)}
  </div>`;
}  setTimeout(filterReport, 0);


function clearSearch() {
  document.getElementById('searchQ').value='';
  document.getElementById('searchDate').value='';
  document.getElementById('searchStatus').value='all';
  doSearch();
}
function exportPDF() {
  // jsPDF se actual PDF generate karo
  if (typeof window.jspdf === 'undefined' && typeof window.jsPDF === 'undefined') {
    // Load jsPDF dynamically
    const script = document.createElement('script');
    script.src = 'https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js';
    script.onload = () => { generatePDF(); };
    document.head.appendChild(script);
  } else {
    generatePDF();
  }
}

function generatePDF() {
  try {
    const { jsPDF } = window.jspdf || window;
    const doc = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' });
    
    const shopName = SHOP_NAME || 'Digital Bandhak';
    const today = new Date().toLocaleDateString('en-IN');
    
    // Header
    doc.setFillColor(255, 107, 0);
    doc.rect(0, 0, 210, 20, 'F');
    doc.setTextColor(255, 255, 255);
    doc.setFontSize(14);
    doc.setFont(undefined, 'bold');
    doc.text(shopName + ' — Report', 14, 13);
    doc.setFontSize(9);
    doc.text('Generated: ' + today, 155, 13);
    
    // Stats section
    doc.setTextColor(26, 10, 0);
    doc.setFontSize(10);
    doc.setFont(undefined, 'normal');
    
    // Collect data from current page
    let tableData = [];
    let stats = { entries:0, totalLoan:0, paid:0, remaining:0 };
    
    // Try to get from report table if on reports page
    const reportRows = document.querySelectorAll('#reportTable tbody tr');
    const searchRows = document.querySelectorAll('#searchResults tbody tr');
    const rows = reportRows.length > 0 ? reportRows : searchRows;
    
    // Use PAWNS data
    const filterEl = document.getElementById('repFilter');
    const filter = filterEl?.value || 'all';
    const list = filter === 'all' ? PAWNS : PAWNS.filter(p => p.status === filter);
    
    list.forEach(p => {
      const loan = +p.loan || +p.loan_amount || 0;
      const paid = +p.paid || +p.total_paid || 0;
      const rem  = +p.remaining || +p.total_remaining || 0;
      stats.entries++;
      stats.totalLoan += loan;
      stats.paid += paid;
      stats.remaining += rem;
      tableData.push([
        p.id || '',
        (p.customer || p.customer_name || '').substring(0, 15),
        (p.item || p.item_description || '').substring(0, 12),
        '₹' + loan.toLocaleString('en-IN'),
        '₹' + paid.toLocaleString('en-IN'),
        '₹' + rem.toLocaleString('en-IN'),
        p.status || ''
      ]);
    });
    
    // Stats box
    let y = 28;
    doc.setFillColor(245, 230, 208);
    doc.roundedRect(12, y, 43, 16, 2, 2, 'F');
    doc.roundedRect(57, y, 43, 16, 2, 2, 'F');
    doc.roundedRect(102, y, 43, 16, 2, 2, 'F');
    doc.roundedRect(147, y, 43, 16, 2, 2, 'F');
    
    doc.setFontSize(14); doc.setFont(undefined, 'bold');
    doc.setTextColor(255, 107, 0);
    doc.text(String(stats.entries), 33, y+8, {align:'center'});
    doc.text('₹'+Math.round(stats.totalLoan/1000)+'K', 78, y+8, {align:'center'});
    doc.text('₹'+Math.round(stats.paid/1000)+'K', 123, y+8, {align:'center'});
    doc.text('₹'+Math.round(stats.remaining/1000)+'K', 168, y+8, {align:'center'});
    
    doc.setFontSize(8); doc.setFont(undefined, 'normal');
    doc.setTextColor(100, 80, 60);
    doc.text('Entries', 33, y+13, {align:'center'});
    doc.text(t('total_loan','Total Loan'), 78, y+13, {align:'center'});
    doc.text('Collected', 123, y+13, {align:'center'});
    doc.text(t('total_rem','Remaining'), 168, y+13, {align:'center'});
    
    // Table header
    y = 52;
    doc.setFillColor(255, 107, 0);
    doc.rect(12, y, 186, 7, 'F');
    doc.setTextColor(255,255,255);
    doc.setFontSize(8); doc.setFont(undefined, 'bold');
    const headers = ['ID', 'Customer', 'Item', 'Loan', 'Paid', t('total_rem','Remaining'), 'Status'];
    const widths  = [30, 35, 28, 22, 22, 25, 18];
    let x = 14;
    headers.forEach((h, i) => { doc.text(h, x, y+5); x += widths[i]; });
    
    // Table rows
    y = 60;
    doc.setFont(undefined, 'normal');
    tableData.forEach((row, idx) => {
      if (y > 270) {
        doc.addPage();
        y = 20;
      }
      doc.setFillColor(idx%2===0 ? 255 : 248, idx%2===0 ? 248 : 242, idx%2===0 ? 240 : 234);
      doc.rect(12, y-4, 186, 7, 'F');
      doc.setTextColor(26, 10, 0);
      x = 14;
      row.forEach((cell, i) => {
        if (i === 6) { // status
          doc.setTextColor(cell==='active'?46:231, cell==='active'?204:76, cell==='active'?113:60);
        } else if (i === 0) {
          doc.setTextColor(255, 107, 0);
        } else {
          doc.setTextColor(26, 10, 0);
        }
        doc.text(String(cell), x, y+1);
        x += widths[i];
      });
      y += 7;
    });
    
    // Footer
    doc.setFillColor(255, 107, 0);
    doc.rect(0, 287, 210, 10, 'F');
    doc.setTextColor(255,255,255);
    doc.setFontSize(8);
    doc.text('Digital Bandhak Platform | digitalbandhak.in', 105, 293, {align:'center'});
    
    doc.save(shopName.replace(/\s+/g,'_') + '_Report_' + today.replace(/\//g,'-') + '.pdf');
    showToast('✅ PDF download ho rahi hai!');
  } catch(e) {
    console.error('PDF Error:', e);
    showToast('❌ PDF error: ' + e.message);
  }
}

// ─── REPORTS PAGE ─────────────────────────────────────────────
async function renderReportsPage(el) {
  el.innerHTML = '<div style="text-align:center;padding:60px;color:var(--muted)"><span class="spin" style="width:28px;height:28px;border-width:3px"></span><div style="margin-top:10px">Loading report...</div></div>';
  // Fresh fetch of pawns (for both shop and admin)
  try {
    const url = ROLE === 'admin' ? 'php/api.php?action=get_pawns&shop_id=all' : 'php/api.php?action=get_pawns';
    const res = await fetch(url);
    const d = await res.json();
    if (d.ok && d.pawns) PAWNS = d.pawns;
  } catch(e) {}
  // Render page then auto-show data
  el.innerHTML = `<div class="pb">
    <div class="card"><div class="ch"><div class="ct">📋 Filters</div></div><div class="cb">
      <div class="fg2">
        <div class="fg"><label class="fl">Status</label>
          <select class="si" id="repFilter" onchange="filterReport()"><option value="all">All</option><option value="active">Active</option><option value="closed">Closed</option></select>
        </div>
        <div class="fg"><label class="fl">From Date</label><input class="fi" type="date" id="repFrom" value="${new Date().getFullYear()}-01-01"></div>
        <div class="fg"><label class="fl">To Date</label><input class="fi" type="date" id="repTo" value="${new Date().toISOString().split('T')[0]}"></div>
      </div>
      <div class="brow">
        <button class="bs bsp" onclick="filterReport()">🔍 Generate</button>
        <button class="bs bsg" onclick="exportPDF()">📥 Export PDF</button>
        
      </div>
    </div></div>
    <div id="reportStats"></div>
    <div id="reportTable"></div>
  </div>`;
  filterReport();
}

function filterReport() {
  const f = document.getElementById('repFilter')?.value||'all';
  const list = f==='all' ? PAWNS : PAWNS.filter(p=>p.status===f);
  const totalLoan = list.reduce((s,p)=>s+(+p.loan||+p.loan_amount||0),0);
  const totalPaid = list.reduce((s,p)=>s+(+p.paid||+p.total_paid||0),0);
  const totalRem  = list.reduce((s,p)=>s+(+p.remaining||+p.total_remaining||0),0);
  
  const _rStats = document.getElementById('reportStats');
  const _rTable = document.getElementById('reportTable');
  if (!_rStats || !_rTable) return;
  _rStats.innerHTML = `<div class="sg">
    ${[['📦',list.length,'Entries'],['💰',fmt(totalLoan),t('total_loan','Total Loan')],
       ['✅',fmt(totalPaid),'Collected'],['⏳',fmt(totalRem),t('total_rem','Remaining')]].map(([ic,v,l])=>`
      <div class="sc"><div style="font-size:19px;margin-bottom:6px">${ic}</div><div class="sv">${v}</div><div class="sl">${l}</div></div>`).join('')}
  </div>`;
  
  _rTable.innerHTML = `<div class="card">
    <div class="ch"><div class="ct">📊 Detail</div></div>
    <div style="overflow-x:auto"><table class="dt"><thead><tr>
      <th>ID</th><th>Customer</th><th>Item</th><th>Loan</th><th>Paid</th><th>Remaining</th><th>Payments</th><th>Status</th>
    </tr></thead><tbody>
    ${list.map(p=>{
      const loan=+p.loan||+p.loan_amount||0, paid=+p.paid||+p.total_paid||0, rem=+p.remaining||+p.total_remaining||0;
      const payC=p.payments?.length||+p.pay_count||0;
      return `<tr>
        <td><span class="b bo">${p.id}</span></td>
        <td style="font-weight:700">${p.customer||p.customer_name||''}</td>
        <td>${p.item||p.item_description||''}</td>
        <td>${fmt(loan)}</td>
        <td style="color:var(--green)">${fmt(paid)}</td>
        <td style="color:${rem===0?'var(--green)':'var(--red)'}">${fmt(rem)}</td>
        <td><span class="b bb">${payC}</span></td>
        <td>${(()=>{
        const hasDur = !!(p.duration_days||+p.duration_days>0||p.return_date||(p.duration&&p.duration!=='—'));
        if (p.status==='closed') return '<span class="b br">🔴 Closed</span>';
        if (!hasDur) return '<span class="b by">🟡 Active (no dur)</span>';
        return '<span class="b bg">🟢 Active</span>';
      })()}</td>
      </tr>`;
    }).join('')}
    </tbody></table></div>
  </div>`;
}

// ─── AUDIT PAGE ───────────────────────────────────────────────
async function renderAuditPage(el) {
  el.innerHTML = `<div class="pb"><div style="text-align:center;padding:40px;color:var(--muted)">Loading...</div></div>`;
  let logs = [];
  try {
    const res = await fetch('php/api.php?action=get_audit');
    const d = await res.json();
    if (d.ok) logs = d.logs;
  } catch(e) {}
  
  el.innerHTML = `<div class="pb"><div class="card">
    <div class="ch">
      <div class="ct">🔍 Audit Logs</div>
      <button class="bs bsg" onclick="exportPDF()">📥 Export</button>
    </div>
    <div style="padding:8px">
      ${logs.length===0?'<div style="text-align:center;padding:32px;color:var(--muted)">Koi log nahi</div>':
        logs.map((l,i)=>{
          const actionColor = l.action&&(l.action.includes('Delete')||l.action.includes('Block'))
            ? 'var(--red)' : l.action&&l.action.includes('Register')
            ? 'var(--green)' : 'var(--saffron)';
          return '<div class="m-card">'
            +'<div class="m-card-main" onclick="toggleShopCard(this)">'
            +'<div style="display:flex;align-items:center;gap:10px">'
            +'<div style="width:36px;height:36px;border-radius:10px;background:rgba(255,107,0,.1);display:flex;align-items:center;justify-content:center;font-size:14px;flex-shrink:0">📋</div>'
            +'<div style="flex:1;min-width:0">'
            +'<div style="font-weight:800;font-size:13px;color:'+actionColor+';white-space:nowrap;overflow:hidden;text-overflow:ellipsis">'+(l.action||'—')+'</div>'
            +'<div style="font-size:11px;color:var(--muted);margin-top:1px">'+(l.user_name||'')+'  ·  <span class="b bb" style="font-size:10px;padding:1px 6px">'+(l.user_role||'')+'</span></div>'
            +'</div>'
            +'<div style="font-size:10px;color:var(--muted);text-align:right;flex-shrink:0">'+(l.created_at||'').replace('T',' ').substring(0,16)+'</div>'
            +'</div>'
            +'</div>'
            +'<div class="m-card-detail" style="display:none">'
            +'<div style="display:flex;flex-direction:column;gap:5px;padding:4px 0">'
            +(l.target?'<div style="display:flex;justify-content:space-between"><span style="color:var(--muted);font-size:11px">Target</span><span style="font-size:12px;font-weight:700">'+l.target+'</span></div>':'')
            +(l.ip_address?'<div style="display:flex;justify-content:space-between"><span style="color:var(--muted);font-size:11px">IP</span><span style="font-size:11px;font-family:monospace">'+l.ip_address+'</span></div>':'')
            +'<div style="display:flex;justify-content:space-between"><span style="color:var(--muted);font-size:11px">Time</span><span style="font-size:11px">'+(l.created_at||'')+'</span></div>'
            +'</div>'
            +'</div>'
            +'</div>';
        }).join('')
      }
    </div>
  </div></div>`;
}

// ─── SHOP SUBSCRIPTION ────────────────────────────────────────
function renderShopSub(el) { renderShopSubReal(el); return; }
function renderShopSub_old_unused(el) {
  el.innerHTML = `<div class="pb">
    <div class="subc">
      <div style="display:flex;justify-content:space-between;align-items:center">
        <div><div style="font-size:14px;font-weight:800">💳 ${t('standard_plan','Standard Plan')}</div>
          <div style="font-size:13px;color:var(--muted)">${t('active_till','Active till')} 31 Aug 2025</div></div>
        <span class="b bg">✅ ${t('active','Active')}</span>
      </div>
      <div class="spb"><div class="spf" style="width:65%"></div></div>
      <div style="display:flex;justify-content:space-between;font-size:13px;color:var(--muted)">
        <span>01 Jan 2025</span><span>179 days left</span><span>31 Aug 2025</span>
      </div>
      <div class="brow" style="margin-top:11px">
        <button class="bs bsp" onclick="openRenewModal()">🔄 Renew</button>
        <button class="bs bsg" onclick="openUpgradeModal()">⬆️ Upgrade</button>
      </div>
    </div>
    <div class="card"><div class="ch"><div class="ct">📋 Payment History</div></div>
      <div style="overflow-x:auto"><table class="dt"><thead><tr>
        <th>Date</th><th>Plan</th><th>Duration</th><th>Amount</th><th>Mode</th>
      </tr></thead><tbody>
        ${[['01 Jan 2025','Standard','8 months','₹800','UPI'],
           ['01 May 2024','Standard','8 months','₹800','Cash'],
           ['01 Sep 2023','Free Trial','7 days','₹0','—']].map(([d,p,dr,a,m])=>`
          <tr><td>${d}</td><td>${p}</td><td>${dr}</td><td style="color:var(--green)">${a}</td><td>${m}</td></tr>`).join('')}
      </tbody></table></div>
    </div>
  </div>`;
}

// ─── TERMS PAGE ────────────────────────────────────────────────
function renderTermsPage(el) {
  const items = [
    {n:'1',ic:'🏪',title:'Platform Ka Use',body:'Digital Bandhak ek software tool hai jo aapki bandhak dukaan ke records manage karne mein madad karta hai. Yeh ek digital register ki tarah kaam karta hai.\n\nSirf registered aur verified shop owners hi platform use kar sakte hain.'},
    {n:'2',ic:'🔐',title:'Data Privacy aur Security',body:'Customer ka naam, mobile, aur Aadhaar data sirf aapki shop ke records mein rahega. Kisi bhi third party ke saath share nahi kiya jayega.\n\nAadhaar number platform mein hamesha masked (XXXX-XXXX-XXXX) store hoga.'},
    {n:'3',ic:'⚠️',title:'Super Admin ki Zimmedari — ZAROORI PADHEN',body:'Super Admin sirf ek platform manager hai. Super Admin aapki dukaan ke kaam-kaaj ke liye seedha zimmedar NAHI hai.\n\n📓 HAMARI STRONG SALAH: Sirf digital par mat raho! Har bandhak entry notebook mein bhi zaroor likhein.'},
    {n:'4',ic:'💳',title:'Subscription aur Payment',body:'Subscription fees ek baar pay karne ke baad refund nahi hogi.\n\nFree Trial 7 din ka hai.\n\nPlans:\n🆓 Free Trial — 7 din (₹0)\n📋 Standard — ₹1,200/year\n⭐ Premium — ₹2,400/year'},
    {n:'5',ic:'⚖️',title:'Kanoon aur Legal Zimmedari',body:'Platform koi legal advice nahi deta. Aap apne vyapar ke liye khud legally zimmedar hain.'},
    {n:'6',ic:'📵',title:'Account Suspend ya Band Karna',body:'Agar koi shop owner platform ka galat use karta hai to Super Admin account suspend kar sakta hai.'},
    {n:'7',ic:'🔄',title:'Platform Updates',body:'Platform samay-samay par update hota rahega. Koi bhi bada change aane par shop owners ko inform kiya jayega.'},
    {n:'8',ic:'📓',title:'Notebook Backup — #1 Salah',body:'Digital Bandhak strongly recommend karta hai ki:\n✓ Har naye bandhak ki entry notebook mein bhi likhein\n✓ Payment lene par notebook mein note zaroor karein\n✓ Monthly ek baar apne records check karein'},
  ];
  
  el.innerHTML = `<div class="pb">
    <div style="background:linear-gradient(135deg,rgba(255,107,0,.1),rgba(255,179,0,.05));border:1px solid rgba(255,107,0,.2);border-radius:16px;padding:20px;margin-bottom:18px;text-align:center">
      <div style="font-size:32px;margin-bottom:8px">📜</div>
      <div style="font-size:20px;font-weight:800;color:var(--saffron)">Terms & Conditions</div>
      <div style="font-size:12px;color:var(--muted);margin-top:4px">Digital Bandhak Platform — Shop Members ke liye</div>
    </div>
    ${items.map(s=>`
    <div class="card" style="margin-bottom:12px">
      <div class="ch">
        <div style="display:flex;align-items:center;gap:10px">
          <div style="width:26px;height:26px;border-radius:50%;background:rgba(255,107,0,.15);border:1px solid rgba(255,107,0,.3);display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:800;color:var(--saffron);flex-shrink:0">${s.n}</div>
          <div class="ct">${s.ic} ${s.title}</div>
        </div>
      </div>
      <div class="cb"><div style="font-size:13px;color:var(--muted);white-space:pre-line;line-height:1.9">${s.body}</div></div>
    </div>`).join('')}
    <div style="background:rgba(46,204,113,.07);border:1px solid rgba(46,204,113,.2);border-radius:14px;padding:16px;text-align:center">
      <div style="font-weight:800;color:var(--green);font-size:14px;margin-bottom:5px">✅ Agreement</div>
      <div style="font-size:12px;color:var(--muted)">Platform use karne se aap in saari terms se agree karte hain.</div>
    </div>
  </div>`;
}

// ─── CUSTOMER VIEW ────────────────────────────────────────────
function renderCustomerView(el) {
  const custName = CUST_NAME || 'Customer';
  const custInitials = custName.split(' ').map(w=>w[0]||'').join('').toUpperCase().slice(0,2) || 'CU';
  const today = new Date();
  const totalLoan = PAWNS.reduce((s,p)=>s+(+p.loan||+p.loan_amount||0),0);
  const totalPaid = PAWNS.reduce((s,p)=>s+(+p.paid||+p.total_paid||0),0);
  const totalRem  = PAWNS.reduce((s,p)=>s+(+p.remaining||+p.total_remaining||0),0);
  const active    = PAWNS.filter(p=>p.status==='active').length;

  const itemCards = PAWNS.map(p => {
    const loan=+p.loan||+p.loan_amount||0;
    const paid=+p.paid||+p.total_paid||0;
    const rem=+p.remaining||+p.total_remaining||0;
    const payC=p.payments?.length||+p.pay_count||0;
    const pct=loan>0?Math.min(100,Math.round(paid/loan*100)):0;
    const loanDate=new Date(p.date||p.loan_date||today);
    const returnDate=p.return_date?new Date(p.return_date):null;
    const daysSince=Math.floor((today-loanDate)/86400000);
    const daysLeft=returnDate?Math.ceil((returnDate-today)/86400000):null;
    const isOverdue=daysLeft!==null&&daysLeft<0;
    const rate=+p.interest||+p.interest_rate||2;
    const accrued=Math.round(loan*(rate/100)*(daysSince/30));
    const isActive = p.status==='active';
    return `
    <div style="border:1px solid ${isActive?'rgba(255,107,0,.35)':'rgba(46,204,113,.3)'};border-radius:12px;padding:12px;margin-bottom:10px;background:var(--surface)">
      <div style="display:flex;gap:6px;align-items:center;margin-bottom:6px;flex-wrap:wrap">
        <span style="font-size:11px;background:rgba(255,107,0,.15);color:var(--saffron);padding:2px 7px;border-radius:20px;font-weight:700">${p.id}</span>
        <span style="font-size:11px;background:${isActive?'rgba(46,204,113,.15)':'rgba(231,76,60,.15)'};color:${isActive?'var(--green)':'var(--red)'};padding:2px 7px;border-radius:20px;font-weight:700">${isActive?'✅ Active':'🔴 Closed'}</span>
        ${isOverdue?'<span style="font-size:11px;background:rgba(231,76,60,.2);color:var(--red);padding:2px 7px;border-radius:20px;font-weight:700">⚠️ Overdue</span>':''}
      </div>
      <div style="font-size:15px;font-weight:800;margin-bottom:4px">${p.item||p.item_description||'—'}</div>
      <div style="font-size:11px;color:var(--muted);margin-bottom:10px">📅 ${p.date||p.loan_date||'—'} &nbsp;·&nbsp; 📈 ${rate}%/month</div>

      <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:6px;margin-bottom:10px">
        <div style="background:rgba(255,107,0,.08);border-radius:8px;padding:8px;text-align:center">
          <div style="font-size:13px;font-weight:800;color:var(--saffron)">${fmt(loan)}</div>
          <div style="font-size:10px;color:var(--muted);margin-top:2px">Loan</div>
        </div>
        <div style="background:rgba(46,204,113,.08);border-radius:8px;padding:8px;text-align:center">
          <div style="font-size:13px;font-weight:800;color:var(--green)">${fmt(paid)}</div>
          <div style="font-size:10px;color:var(--muted);margin-top:2px">Chukaya</div>
        </div>
        <div style="background:rgba(231,76,60,.08);border-radius:8px;padding:8px;text-align:center">
          <div style="font-size:13px;font-weight:800;color:${rem===0?'var(--green)':'var(--red)'}">${fmt(rem)}</div>
          <div style="font-size:10px;color:var(--muted);margin-top:2px">Baaki</div>
        </div>
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px;margin-bottom:10px">
        <div style="background:rgba(255,179,0,.07);border-radius:8px;padding:8px;text-align:center">
          <div style="font-size:16px;font-weight:800;color:var(--saffron)">${daysSince}</div>
          <div style="font-size:10px;color:var(--muted)">Din Ho Gaye</div>
        </div>
        <div style="background:rgba(${isOverdue?'231,76,60':'52,152,219'},.07);border-radius:8px;padding:8px;text-align:center">
          <div style="font-size:16px;font-weight:800;color:${isOverdue?'var(--red)':'#5dade2'}">${daysLeft===null?'—':isOverdue?Math.abs(daysLeft)+' late':daysLeft+' din'}</div>
          <div style="font-size:10px;color:var(--muted)">${isOverdue?'Overdue':'Return Mein'}</div>
        </div>
      </div>

      <div style="height:5px;background:var(--deep);border-radius:3px;overflow:hidden;margin-bottom:5px">
        <div style="height:100%;width:${pct}%;background:linear-gradient(to right,var(--saffron),var(--gold));border-radius:3px"></div>
      </div>
      <div style="display:flex;justify-content:space-between;font-size:11px;color:var(--muted);margin-bottom:10px">
        <span>${pct}% chukaya</span><span>${payC} payment</span>
      </div>

      <button onclick="openPayHist('${p.id}',true)" style="width:100%;padding:9px;background:rgba(255,107,0,.12);border:1px solid rgba(255,107,0,.3);color:var(--saffron);border-radius:8px;font-size:13px;font-weight:700;cursor:pointer">
        📋 Payment History (${payC})
      </button>
    </div>`;
  }).join('');

  el.innerHTML = `
  <div style="padding:10px 10px 80px;width:100%">
    <div style="border:1px solid rgba(255,107,0,.25);border-radius:12px;padding:12px;margin-bottom:12px;display:flex;align-items:center;gap:10px">
      <div class="av" style="width:46px;height:46px;font-size:18px;flex-shrink:0">${custInitials}</div>
      <div style="flex:1;min-width:0">
        <div style="font-size:15px;font-weight:800;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${custName}</div>
        <div style="font-size:11px;color:var(--muted)">📱 ${(CUST_MOBILE||'').replace(/\d(?=\d{4})/g,'X')||'—'}</div>
      </div>
      <div style="text-align:center;flex-shrink:0">
        <div style="font-size:20px;font-weight:800;color:var(--saffron)">${PAWNS.length}</div>
        <div style="font-size:10px;color:var(--muted)">Bandhak</div>
      </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:12px">
      <div style="background:var(--surface);border:1px solid var(--border);border-radius:10px;padding:10px;text-align:center">
        <div style="font-size:18px;font-weight:800;color:var(--saffron)">${fmt(totalLoan)}</div>
        <div style="font-size:11px;color:var(--muted)">${t('total_loan','💰 Kul Loan')}</div>
      </div>
      <div style="background:var(--surface);border:1px solid var(--border);border-radius:10px;padding:10px;text-align:center">
        <div style="font-size:18px;font-weight:800;color:var(--green)">${fmt(totalPaid)}</div>
        <div style="font-size:11px;color:var(--muted)">${t('total_paid','✅ Chukaya')}</div>
      </div>
      <div style="background:var(--surface);border:1px solid var(--border);border-radius:10px;padding:10px;text-align:center">
        <div style="font-size:18px;font-weight:800;color:var(--red)">${fmt(totalRem)}</div>
        <div style="font-size:11px;color:var(--muted)">${t('total_rem','⏳ Baaki')}</div>
      </div>
      <div style="background:var(--surface);border:1px solid var(--border);border-radius:10px;padding:10px;text-align:center">
        <div style="font-size:18px;font-weight:800;color:var(--saffron)">${active}</div>
        <div style="font-size:11px;color:var(--muted)">📦 Active</div>
      </div>
    </div>

    <div style="font-size:14px;font-weight:800;margin-bottom:8px">📦 Meri Bandhak Items</div>
    ${PAWNS.length===0
      ? '<div style="text-align:center;padding:30px;color:var(--muted);background:var(--surface);border-radius:12px">Koi bandhak item nahi hai</div>'
      : itemCards}
  </div>`;
}

function renderAddPawnFlow(el) {
  pawnStep = 1; pawnData = {}; pawnPhotos = {};
  renderPawnStep(el);
}

function renderPawnStep(el) {
  if (!el) el = document.getElementById('pageContent');

  if (pawnStep === 1) {
    // ── SINGLE PAGE FORM ─────────────────────────────────────
    el.innerHTML = `<div class="pb" style="max-width:800px;margin:0 auto">
      <!-- Progress indicators -->
      <div style="display:flex;gap:0;margin-bottom:18px;border-radius:12px;overflow:hidden;border:1px solid var(--border)">
        ${[['1','📝 Entry Form','active'],['2','👁️ Preview',''],['3','🔐 Confirm','']].map(([n,l,a])=>`
          <div style="flex:1;padding:10px;text-align:center;background:${a?'var(--saffron)':'var(--surface)'};color:${a?'#fff':'var(--muted)'};font-size:12px;font-weight:${a?'800':'500'}">
            <span style="font-weight:800">${n}</span> ${l}
          </div>`).join('<div style="width:1px;background:var(--border)"></div>')}
      </div>

      <!-- SECTION 1: Customer -->
      <div class="card" style="margin-bottom:12px">
        <div class="ch" style="background:linear-gradient(135deg,rgba(255,107,0,.15),transparent)">
          <div class="ct">👤 Customer Details</div>
        </div>
        <div class="cb">
          <div class="fg2">
            <div class="fg">
              <label class="fl">Customer Name *</label>
              <input class="fi" id="p-name" placeholder="Poora naam likhein" value="${pawnData.name||''}">
            </div>
            <div class="fg">
              <label class="fl">Mobile Number *</label>
              <input class="fi" id="p-mobile" placeholder="10 digit number" type="tel" maxlength="10" value="${pawnData.mobile||''}">
            </div>
            <div class="fg">
              <label class="fl">Aadhaar Number</label>
              <input class="fi" id="p-aadhaar" placeholder="XXXX-XXXX-XXXX" value="${pawnData.aadhaar||''}">
            </div>
            <div class="fg">
              <label class="fl">Father / Husband Name</label>
              <input class="fi" id="p-father" placeholder="Guardian name" value="${pawnData.father||''}">
            </div>
          </div>
          <div class="fg" style="margin-top:4px">
            <label class="fl">Address</label>
            <input class="fi" id="p-address" placeholder="Mohalla, Shehar, Pin Code" value="${pawnData.address||''}">
          </div>
        </div>
      </div>

      <!-- SECTION 2: Item + Photos -->
      <div class="card" style="margin-bottom:12px">
        <div class="ch" style="background:linear-gradient(135deg,rgba(255,179,0,.15),transparent)">
          <div class="ct">📦 Samaan Details + Photos</div>
        </div>
        <div class="cb">
          <div class="fg2">
            <div class="fg">
              <label class="fl">Category *</label>
              <select class="si" id="p-cat">
                <option ${pawnData.cat==='Gold Jewellery'?'selected':''}>Gold Jewellery</option>
                <option ${pawnData.cat==='Silver'?'selected':''}>Silver</option>
                <option ${pawnData.cat==='Electronics'?'selected':''}>Electronics</option>
                <option ${pawnData.cat==='Vehicle'?'selected':''}>Vehicle</option>
                <option ${pawnData.cat==='Other'?'selected':''}>Other</option>
              </select>
            </div>
            <div class="fg">
              <label class="fl">Description *</label>
              <input class="fi" id="p-desc" placeholder="e.g. Gold Chain 22K" value="${pawnData.desc||''}">
            </div>
            <div class="fg">
              <label class="fl">Weight / Qty</label>
              <input class="fi" id="p-weight" placeholder="e.g. 18.5g" value="${pawnData.weight||''}">
            </div>
            <div class="fg">
              <label class="fl">Market Value (₹)</label>
              <input class="fi" id="p-mval" placeholder="Anumaan mulya" type="number" value="${pawnData.mval||''}">
            </div>
          </div>
          
          <!-- Photo Upload Grid -->
          <div class="fg" style="margin-top:10px">
            <label class="fl">📷 Item Photos (Max 4)</label>
            <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:8px" id="photoGrid">
              ${[1,2,3,4].map(i=>`
              <div id="photoBox${i}" style="position:relative;border:2px dashed ${pawnPhotos[i]?'var(--saffron)':'var(--border)'};border-radius:10px;min-height:88px;display:flex;align-items:center;justify-content:center;cursor:pointer;overflow:hidden;background:var(--surface)" onclick="document.getElementById('photoInput${i}').click()">
                <input type="file" accept="image/*" capture="environment" id="photoInput${i}" style="display:none" onchange="previewPhoto(${i},this)">
                <div id="photoPlaceholder${i}" style="text-align:center;padding:8px;${pawnPhotos[i]?'display:none':''}">
                  <div style="font-size:22px">📷</div>
                  <div style="font-size:13px;color:var(--muted);margin-top:3px">Photo ${i}</div>
                </div>
                <img id="photoPreview${i}" src="${pawnPhotos[i]||''}" style="${pawnPhotos[i]?'display:block':'display:none'};width:100%;height:88px;object-fit:cover;border-radius:8px">
                <button id="photoRemove${i}" onclick="removePhoto(event,${i})" style="${pawnPhotos[i]?'display:flex':'display:none'};position:absolute;top:3px;right:3px;background:rgba(231,76,60,.9);color:#fff;border:none;border-radius:50%;width:20px;height:20px;font-size:12px;cursor:pointer;align-items:center;justify-content:center">✕</button>
              </div>`).join('')}
            </div>
            <div id="photoHint" style="font-size:13px;color:var(--muted);margin-top:5px">
              ${Object.keys(pawnPhotos).length ? '✅ '+Object.keys(pawnPhotos).length+' photo(s) selected' : '📌 Optional — click karke photo add karein'}
            </div>
          </div>

          <div class="fg" style="margin-top:6px">
            <label class="fl">Condition</label>
            <select class="si" id="p-cond">
              <option ${pawnData.cond==='Excellent'?'selected':''}>Excellent</option>
              <option ${pawnData.cond==='Good'||!pawnData.cond?'selected':''}>Good</option>
              <option ${pawnData.cond==='Fair'?'selected':''}>Fair</option>
              <option ${pawnData.cond==='Poor'?'selected':''}>Poor</option>
            </select>
          </div>
        </div>
      </div>

      <!-- SECTION 3: Loan -->
      <div class="card" style="margin-bottom:12px">
        <div class="ch" style="background:linear-gradient(135deg,rgba(46,204,113,.15),transparent)">
          <div class="ct">💰 Loan Details</div>
        </div>
        <div class="cb">
          <div class="fg2">
            <div class="fg">
              <label class="fl">Loan Amount (₹) *</label>
              <input class="fi" id="p-loan" placeholder="Kitna loan diya" type="number" value="${pawnData.loan||''}" oninput="calcLoan()">
            </div>
            <div class="fg">
              <label class="fl">Interest Rate (%/month) *</label>
              <input class="fi" id="p-interest" placeholder="e.g. 2" type="number" step="0.5" value="${pawnData.interest||'2'}" oninput="calcLoan()">
            </div>
            <div class="fg">
              <label class="fl">Loan Date *</label>
              <input class="fi" type="date" id="p-date" value="${pawnData.ldate||new Date().toISOString().split('T')[0]}" onchange="calcLoan()">
            </div>
            <div class="fg">
              <label class="fl">Duration</label>
              <div style="display:flex;gap:6px;align-items:center">
                <input class="fi" id="p-dur-text" placeholder="3 mahine / 45 din / 1 saal" 
                  value="${pawnData.durText||''}" oninput="parseDuration()" style="flex:1">
                <div id="durDaysShow" style="background:rgba(255,107,0,.12);border:1px solid rgba(255,107,0,.3);border-radius:8px;padding:8px 10px;font-size:12px;font-weight:700;color:var(--saffron);white-space:nowrap;min-width:58px;text-align:center">
                  ${pawnData.durDays?pawnData.durDays+' din':'— din'}
                </div>
              </div>
            </div>
          </div>
          <div class="fg" style="margin-top:6px;max-width:200px">
            <label class="fl">Payment Mode</label>
            <select class="si" id="p-mode">
              <option ${pawnData.mode==='Cash'||!pawnData.mode?'selected':''}>Cash</option>
              <option ${pawnData.mode==='UPI'?'selected':''}>UPI</option>
              <option ${pawnData.mode==='Bank Transfer'?'selected':''}>Bank Transfer</option>
            </select>
          </div>
          
          <!-- Live Interest Calculator -->
          <div id="loanCalc" style="background:rgba(255,107,0,.06);border:1px solid rgba(255,107,0,.2);border-radius:12px;padding:14px;display:grid;grid-template-columns:repeat(3,1fr);gap:10px;text-align:center;margin-top:12px">
            <div><div style="font-size:16px;font-weight:800;color:var(--saffron)">₹0</div><div style="font-size:12px;color:var(--muted)">Principal</div></div>
            <div><div style="font-size:16px;font-weight:800;color:var(--gold)">₹0</div><div style="font-size:12px;color:var(--muted)">Interest (duration daalen)</div></div>
            <div><div style="font-size:16px;font-weight:800;color:var(--green)">₹0</div><div style="font-size:12px;color:var(--muted)">Total Due</div></div>
          </div>
        </div>
      </div>

      <!-- Action Button -->
      <div style="display:flex;justify-content:flex-end;padding-bottom:20px">
        <button class="bs bsp" style="padding:12px 28px;font-size:14px" onclick="goToPreview()">
          Preview & Submit →
        </button>
      </div>
    </div>`;
    
    if (pawnData.loan) calcLoan();
    return;
  }

  // ── STEP 2: PREVIEW ──────────────────────────────────────────
  if (pawnStep === 2) {
    const aadhaar = (pawnData.aadhaar||'').replace(/\d(?=\d{4})/g,'X');
    const loan    = +pawnData.loan||0;
    const rate    = +pawnData.interest||2;
    const days    = +pawnData.durDays||0;
    const interest = days>0 ? Math.round(loan*(rate/100)*(days/30)) : 0;
    const total    = loan + interest;
    const photos   = Object.values(pawnPhotos);

    el.innerHTML = `<div class="pb" style="max-width:800px;margin:0 auto">
      <!-- Progress -->
      <div style="display:flex;gap:0;margin-bottom:18px;border-radius:12px;overflow:hidden;border:1px solid var(--border)">
        ${[['1','📝 Entry Form',''],['2','👁️ Preview','active'],['3','🔐 Confirm','']].map(([n,l,a])=>`
          <div style="flex:1;padding:10px;text-align:center;background:${a?'var(--saffron)':n<2?'rgba(46,204,113,.15)':'var(--surface)'};color:${a?'#fff':n<2?'var(--green)':'var(--muted)'};font-size:12px;font-weight:${a?'800':'500'}">
            ${n<2?'✓':n} ${l}
          </div>`).join('<div style="width:1px;background:var(--border)"></div>')}
      </div>

      <div class="card">
        <div class="ch" style="background:linear-gradient(135deg,rgba(255,107,0,.15),transparent)">
          <div class="ct">👁️ Entry Preview — Confirm karein</div>
        </div>
        <div class="cb">
          <!-- Two column layout -->
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:16px">
            <div style="background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:14px">
              <div style="font-size:13px;font-weight:800;color:var(--saffron);text-transform:uppercase;margin-bottom:10px;letter-spacing:.5px">👤 Customer</div>
              ${[['Naam',pawnData.name||'—'],['Mobile',pawnData.mobile||'—'],['Aadhaar',aadhaar||'—'],['Pita/Pati',pawnData.father||'—'],['Pata',pawnData.address||'—']].map(([k,v])=>`
                <div style="display:flex;justify-content:space-between;padding:4px 0;border-bottom:1px solid rgba(255,255,255,.06);font-size:12px">
                  <span style="color:var(--muted)">${k}</span><b style="color:var(--text);max-width:55%;text-align:right;word-break:break-word">${v}</b>
                </div>`).join('')}
            </div>
            <div style="background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:14px">
              <div style="font-size:13px;font-weight:800;color:var(--gold);text-transform:uppercase;margin-bottom:10px;letter-spacing:.5px">📦 Samaan</div>
              ${[['Category',pawnData.cat||'—'],['Description',pawnData.desc||'—'],['Weight',pawnData.weight||'—'],['Market Val',pawnData.mval?'₹'+pawnData.mval:'—'],['Condition',pawnData.cond||'—']].map(([k,v])=>`
                <div style="display:flex;justify-content:space-between;padding:4px 0;border-bottom:1px solid rgba(255,255,255,.06);font-size:12px">
                  <span style="color:var(--muted)">${k}</span><b style="color:var(--text);max-width:55%;text-align:right">${v}</b>
                </div>`).join('')}
            </div>
          </div>

          <!-- Loan summary highlighted -->
          <div style="background:linear-gradient(135deg,rgba(255,107,0,.1),rgba(255,179,0,.06));border:1px solid rgba(255,107,0,.3);border-radius:12px;padding:14px;margin-bottom:14px">
            <div style="font-size:13px;font-weight:800;color:var(--saffron);text-transform:uppercase;margin-bottom:10px;letter-spacing:.5px">💰 Loan Details</div>
            <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px;text-align:center;margin-bottom:12px">
              <div><div style="font-size:20px;font-weight:800;color:var(--saffron)">${fmt(loan)}</div><div style="font-size:12px;color:var(--muted)">Principal</div></div>
              <div><div style="font-size:20px;font-weight:800;color:var(--gold)">${fmt(interest)}</div><div style="font-size:12px;color:var(--muted)">Interest (${days} din)</div></div>
              <div><div style="font-size:20px;font-weight:800;color:var(--green)">${fmt(total)}</div><div style="font-size:12px;color:var(--muted)">Total Due</div></div>
            </div>
            ${[['Loan Date',pawnData.ldate||'—'],['Duration',pawnData.durText||(days>0?days+' din':'— nahi dali')],['Interest Rate',(rate)+'%/month'],['Payment Mode',pawnData.mode||'Cash']].map(([k,v])=>`
              <div style="display:flex;justify-content:space-between;padding:5px 0;border-top:1px solid rgba(255,107,0,.15);font-size:12px">
                <span style="color:var(--muted)">${k}</span><b style="color:var(--text)">${v}</b>
              </div>`).join('')}
          </div>

          <!-- Photos preview -->
          ${photos.length ? `<div style="margin-bottom:14px">
            <div style="font-size:13px;font-weight:800;color:var(--muted);text-transform:uppercase;margin-bottom:8px">📷 Photos (${photos.length})</div>
            <div style="display:flex;gap:8px;flex-wrap:wrap">
              ${photos.map(src=>`<img src="${src}" style="width:80px;height:70px;object-fit:cover;border-radius:8px;border:1px solid var(--border)" onclick="openImgLightbox('${src}')">`).join('')}
            </div>
          </div>` : ''}

          <!-- Action buttons -->
          <div style="display:flex;gap:8px;justify-content:space-between;flex-wrap:wrap">
            <button class="bs bsg" onclick="pawnStep=1;renderPawnStep()">← Edit Form</button>
            <button class="bs bsp" style="padding:11px 28px;font-size:14px" onclick="pawnStep=3;renderPawnStep()">🔐 Confirm & Save →</button>
          </div>
        </div>
      </div>
    </div>`;
    return;
  }

  // ── STEP 3: PASSWORD CONFIRM ──────────────────────────────────
  if (pawnStep === 3) {
    el.innerHTML = `<div class="pb" style="max-width:500px;margin:0 auto">
      <!-- Progress -->
      <div style="display:flex;gap:0;margin-bottom:18px;border-radius:12px;overflow:hidden;border:1px solid var(--border)">
        ${[['1','📝 Entry Form',''],['2','👁️ Preview',''],['3','🔐 Confirm','active']].map(([n,l,a])=>`
          <div style="flex:1;padding:10px;text-align:center;background:${a?'var(--saffron)':n<3?'rgba(46,204,113,.15)':'var(--surface)'};color:${a?'#fff':n<3?'var(--green)':'var(--muted)'};font-size:12px;font-weight:${a?'800':'500'}">
            ${n<3?'✓':n} ${l}
          </div>`).join('<div style="width:1px;background:var(--border)"></div>')}
      </div>

      <div class="card">
        <div class="cb" style="text-align:center;padding:30px 20px">
          <div style="width:64px;height:64px;background:rgba(255,107,0,.15);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:28px;margin:0 auto 14px">🔐</div>
          <div style="font-size:18px;font-weight:800;margin-bottom:6px">Passcode Confirm Karein</div>
          <div style="font-size:12px;color:var(--muted);margin-bottom:20px">Entry save karne ke liye apna password daalen</div>
          
          <!-- Mini summary -->
          <div style="background:var(--surface);border:1px solid var(--border);border-radius:10px;padding:12px;margin-bottom:18px;text-align:left">
            <div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:5px">
              <span style="color:var(--muted)">Customer</span><b>${pawnData.name||'—'}</b>
            </div>
            <div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:5px">
              <span style="color:var(--muted)">Item</span><b>${pawnData.desc||'—'}</b>
            </div>
            <div style="display:flex;justify-content:space-between;font-size:14px;font-weight:800">
              <span style="color:var(--muted)">Loan Amount</span><b style="color:var(--saffron)">${fmt(pawnData.loan||0)}</b>
            </div>
          </div>
          
          <input class="fi" type="password" id="p-confirm-pwd" placeholder="••••••••" 
            style="max-width:280px;margin:0 auto 14px;display:block;text-align:center;font-size:18px;letter-spacing:4px">
          <div id="confirmMsg" style="display:none;font-size:12px;padding:8px;border-radius:8px;margin-bottom:12px"></div>
          <div style="display:flex;gap:8px;justify-content:center">
            <button class="bs bsg" onclick="pawnStep=2;renderPawnStep()">← Back</button>
            <button class="bs bsp" style="padding:11px 24px" onclick="submitPawn(this)">✅ Save Entry</button>
          </div>
        </div>
      </div>
    </div>`;
    
    setTimeout(()=>document.getElementById('p-confirm-pwd')?.focus(), 100);
  }
}

function goToPreview() {
  // Check subscription first
  if (ROLE === 'shop') {
    const addBtn = document.getElementById('nav-add-pawn');
    if (addBtn && addBtn.style.pointerEvents === 'none') {
      showToast('❌ Subscription expire ho gayi. Pehle renew karein.');
      return;
    }
  }
  // Validate required fields
  const n = document.getElementById('p-name')?.value.trim();
  const m = document.getElementById('p-mobile')?.value.trim();
  const d = document.getElementById('p-desc')?.value.trim();
  const l = document.getElementById('p-loan')?.value;
  
  if (!n) { showToast('❌ Customer naam zaroori hai'); document.getElementById('p-name')?.focus(); return; }
  if (!m || m.length<10) { showToast('❌ Sahi mobile number daalen'); document.getElementById('p-mobile')?.focus(); return; }
  if (!d) { showToast('❌ Item description zaroori hai'); document.getElementById('p-desc')?.focus(); return; }
  if (!l || +l<=0) { showToast('❌ Loan amount daalen'); document.getElementById('p-loan')?.focus(); return; }
  
  // Save all data
  pawnData.name    = n;
  pawnData.mobile  = m;
  pawnData.aadhaar = document.getElementById('p-aadhaar')?.value || '';
  pawnData.father  = document.getElementById('p-father')?.value || '';
  pawnData.address = document.getElementById('p-address')?.value || '';
  pawnData.cat     = document.getElementById('p-cat')?.value || 'Gold Jewellery';
  pawnData.desc    = d;
  pawnData.weight  = document.getElementById('p-weight')?.value || '';
  pawnData.mval    = document.getElementById('p-mval')?.value || '';
  pawnData.cond    = document.getElementById('p-cond')?.value || 'Good';
  pawnData.photos  = pawnPhotos;
  pawnData.loan    = +l;
  pawnData.interest = document.getElementById('p-interest')?.value || '2';
  pawnData.ldate   = document.getElementById('p-date')?.value || new Date().toISOString().split('T')[0];
  pawnData.durText = document.getElementById('p-dur-text')?.value || '';
  if (!pawnData.durDays) parseDuration();
  pawnData.mode    = document.getElementById('p-mode')?.value || 'Cash';
  
  pawnStep = 2;
  renderPawnStep();
}

function nextPawnStep() { goToPreview(); }
function prevPawnStep() { if (pawnStep>1) { pawnStep--; renderPawnStep(); } }

function calcLoan() {
  const loan  = +(document.getElementById('p-loan')?.value||0);
  const rate  = +(document.getElementById('p-interest')?.value||2);
  const days  = pawnData.durDays || 0;
  const months = days / 30;
  const interest = days > 0 ? Math.round(loan*(rate/100)*months) : 0;
  const total = loan + interest;
  const el = document.getElementById('loanCalc');
  if (el) el.innerHTML = [
    ['Principal', fmt(loan), 'var(--saffron)'],
    [days>0 ? 'Interest ('+days+' din)' : 'Interest (pehle duration daalen)', fmt(interest), 'var(--gold)'],
    ['Total Due', fmt(total), 'var(--green)']
  ].map(([l,v,c])=>`
    <div>
      <div style="font-size:17px;font-weight:800;color:${c}">${v}</div>
      <div style="font-size:12px;color:var(--muted);margin-top:2px">${l}</div>
    </div>`).join('');
}

// ─── PHOTO UPLOAD HELPERS ─────────────────────────────────────
function previewPhoto(i, input) {
  const file = input.files[0];
  if (!file) return;
  if (file.size > 5 * 1024 * 1024) { showToast('❌ Photo 5MB se chhoti honi chahiye'); return; }
  const reader = new FileReader();
  reader.onload = e => {
    pawnPhotos[i] = e.target.result;
    const prev = document.getElementById('photoPreview'+i);
    const ph   = document.getElementById('photoPlaceholder'+i);
    const rem  = document.getElementById('photoRemove'+i);
    const box  = document.getElementById('photoBox'+i);
    if (prev) { prev.src = e.target.result; prev.style.display='block'; }
    if (ph)   ph.style.display='none';
    if (rem)  rem.style.display='flex';
    if (box)  { box.style.borderStyle='solid'; box.style.borderColor='var(--saffron)'; }
    const cnt = Object.keys(pawnPhotos).length;
    const hint = document.getElementById('photoHint');
    if (hint) hint.textContent = '✅ '+cnt+' photo(s) selected';
  };
  reader.readAsDataURL(file);
}

function removePhoto(e, i) {
  e.stopPropagation();
  delete pawnPhotos[i];
  const prev = document.getElementById('photoPreview'+i);
  const ph   = document.getElementById('photoPlaceholder'+i);
  const rem  = document.getElementById('photoRemove'+i);
  const box  = document.getElementById('photoBox'+i);
  const inp  = document.getElementById('photoInput'+i);
  if (prev) { prev.style.display='none'; prev.src=''; }
  if (ph)   ph.style.display='';
  if (rem)  rem.style.display='none';
  if (box)  { box.style.borderStyle='dashed'; box.style.borderColor='var(--border)'; }
  if (inp)  inp.value='';
  const cnt = Object.keys(pawnPhotos).length;
  const hint = document.getElementById('photoHint');
  if (hint) hint.textContent = cnt ? '✅ '+cnt+' photo(s) selected' : '📌 Optional — click karke photo add karein';
}

// ─── DURATION PARSER ──────────────────────────────────────────
function parseDuration() {
  const txt = (document.getElementById('p-dur-text')?.value || '').trim().toLowerCase();
  if (!txt) { pawnData.durDays=0; pawnData.durText=''; updateDurDisplay(0); calcLoan(); return; }
  let days = 0;
  const num = parseFloat(txt) || 1;
  if (/saal|year|sal\b|yr\b/.test(txt))               days = Math.round(num * 365);
  else if (/mahina|mahine|month|mah\b|mon\b/.test(txt)) days = Math.round(num * 30);
  else if (/hafta|week|haft\b|wk\b/.test(txt))         days = Math.round(num * 7);
  else if (/din|day\b/.test(txt))                       days = Math.round(num);
  else if (!isNaN(num) && num > 0)                      days = Math.round(num * 30);
  pawnData.durDays = days;
  pawnData.durText = document.getElementById('p-dur-text')?.value;
  updateDurDisplay(days);
  calcLoan();
}

function updateDurDisplay(days) {
  const el = document.getElementById('durDaysShow');
  if (!el) return;
  if (!days) { el.textContent = '— din'; return; }
  let label = days+' din';
  if (days >= 365) label = (days/365).toFixed(1).replace('.0','')+' saal';
  else if (days >= 30) label = Math.round(days/30)+' mahine';
  else if (days >= 7)  label = Math.round(days/7)+' hafta';
  el.textContent = label;
}


async function submitPawn(btnEl) {
  const pwd = document.getElementById('p-confirm-pwd')?.value;
  const msgEl = document.getElementById('confirmMsg');
  if (!pwd) { 
    if (msgEl) { msgEl.style.cssText='display:block;background:rgba(231,76,60,.1);border:1px solid var(--red);color:var(--red)'; msgEl.textContent='❌ Password daalen'; }
    return; 
  }
  if (btnEl) { btnEl._orig=btnEl.innerHTML; btnEl.disabled=true; btnEl.innerHTML='<span class="spin"></span> Saving...'; }
  
  const fd = new FormData();
  fd.append('action','add_pawn');
  fd.append('customer_name', pawnData.name||'');
  fd.append('customer_mobile', pawnData.mobile||'');
  fd.append('customer_aadhaar', pawnData.aadhaar||'');
  fd.append('father_name', pawnData.father||'');
  fd.append('address', pawnData.address||'');
  fd.append('item_category', pawnData.cat||'Gold Jewellery');
  fd.append('item_description', pawnData.desc||'');
  fd.append('item_weight', pawnData.weight||'');
  fd.append('item_condition', pawnData.cond||'Good');
  fd.append('market_value', pawnData.mval||0);
  fd.append('loan_amount', pawnData.loan||0);
  fd.append('interest_rate', pawnData.interest||2);
  fd.append('loan_date', pawnData.ldate||new Date().toISOString().split('T')[0]);
  fd.append('return_date', pawnData.rdate||'');
  fd.append('duration', pawnData.durText||pawnData.dur||'3 Months');
  fd.append('duration_days', pawnData.durDays||0);
  fd.append('payment_mode', pawnData.mode||'Cash');
  // Append photos as base64
  Object.entries(pawnData.photos||{}).forEach(([i,b64])=>{
    fd.append('photo_'+i, b64);
  });
  
  try {
    const res = await fetch('php/api.php',{method:'POST',body:fd});
    const d = await res.json();
    if (d.ok) {
      if (btnEl) { btnEl.disabled=false; btnEl.innerHTML=btnEl._orig||'✅ Save Entry'; }
      const newId = d.id || 'BDK-2025-NEW';
      PAWNS.unshift({
        id:newId, customer_name:pawnData.name, customer:pawnData.name,
        customer_mobile:pawnData.mobile, mobile:pawnData.mobile,
        item:pawnData.desc, item_description:pawnData.desc,
        item_weight:pawnData.weight||'',
        loan_amount:pawnData.loan, loan:pawnData.loan, paid:0, total_paid:0,
        total_remaining:pawnData.loan, remaining:pawnData.loan,
        loan_date:pawnData.ldate||new Date().toISOString().split('T')[0],
        date:pawnData.ldate||new Date().toISOString().split('T')[0],
        item_photos: d.photos ? JSON.stringify(d.photos) : JSON.stringify(Object.values(pawnPhotos)),
        status:'active', interest:pawnData.interest||2,
        interest_rate:pawnData.interest||2, payments:[]
      });
      showReceipt(newId);
    } else {
      if (btnEl) { btnEl.disabled=false; btnEl.innerHTML=btnEl._orig||'✅ Save Entry'; }
      const msgEl = document.getElementById('confirmMsg');
      if (msgEl) { msgEl.style.cssText='display:block;background:rgba(231,76,60,.1);border:1px solid var(--red);color:var(--red)'; msgEl.textContent='❌ '+(d.msg||'Password galat hai ya server error'); }
    }
  } catch(e) {
    // Demo mode
    if (btnEl) { btnEl.disabled=false; btnEl.innerHTML=btnEl._orig||'✅ Save Entry'; }
    const newId = 'BDK-'+(new Date().getFullYear())+'-'+(String(PAWNS.length+1).padStart(3,'0'));
    PAWNS.unshift({
      id:newId, customer:pawnData.name, mobile:pawnData.mobile,
      item:pawnData.desc, loan:pawnData.loan, paid:0,
      item_photos: JSON.stringify(Object.values(pawnPhotos)),
      remaining:pawnData.loan, date:pawnData.ldate||new Date().toISOString().split('T')[0],
      status:'active', interest:pawnData.interest||2, payments:[]
    });
    showReceipt(newId);
  }
}

function showReceipt(id) {
  const el = document.getElementById('pageContent');
  const aadhaar = (pawnData.aadhaar||'').replace(/\d(?=\d{4})/g,'X');
  const loan = pawnData.loan||0;
  const rate = pawnData.interest||2;
  const days = pawnData.durDays||0;
  const months = days/30;
  const interest = Math.round(loan*(rate/100)*months);
  const total = loan + interest;
  
  const receiptRows = [
    ['Customer', pawnData.name||''],
    ['Mobile', (pawnData.mobile||'').replace(/\d(?=\d{4})/,'X')],
    ['Aadhaar', aadhaar],
    ['Item', pawnData.desc||''],
    ['Weight', pawnData.weight||'—'],
    ['Loan Amount', fmt(loan)],
    ['Interest Rate', rate+'%/month'],
    ['Duration', (pawnData.durText||days+' din')],
    ['Loan Date', pawnData.ldate||''],
    ['Return Date', pawnData.rdate||'—'],
    ['Interest Total', fmt(interest)],
    ['Total Due', fmt(total)],
    ['Payment Mode', pawnData.mode||'Cash'],
  ].map(([k,v])=>`<div class="rrow"><span style="color:#8B6040;font-weight:600">${k}</span><b>${v}</b></div>`).join('');

  const receiptHTML = `
    <div class="receipt" style="max-width:420px;margin:0 auto">
      <div class="rh" style="text-align:center;padding-bottom:12px;margin-bottom:12px">
        <div style="font-size:20px;font-weight:800;color:#FF6B00">🏦 ${SHOP_NAME||'Digital Bandhak'}</div>
        <div style="font-size:13px;color:#8B6040;margin-top:3px">Date: ${new Date().toLocaleDateString('en-IN')} | Receipt</div>
      </div>
      <div class="rid" style="text-align:center;font-size:16px;font-weight:800;color:#FF6B00;background:#FFF0E0;padding:8px;border-radius:8px;margin-bottom:12px">${id}</div>
      ${receiptRows}
      <div class="rf" style="text-align:center;font-size:12px;color:#8B6040;padding-top:10px;margin-top:10px">Digital Bandhak Platform | digitalbandhak.in</div>
    </div>`;

  // Populate print area
  const pr = document.getElementById('printReceipt');
  if (pr) pr.innerHTML = receiptHTML;

  el.innerHTML = `<div class="pb">
    <div style="text-align:center;margin-bottom:16px">
      <div style="font-size:22px;font-weight:800;color:var(--green)">✅ Bandhak Successfully Saved!</div>
      <div style="font-size:13px;color:var(--muted);margin-top:4px">SMS customer ko bhej diya gaya</div>
    </div>
    ${receiptHTML}
    <div class="brow" style="justify-content:center;margin-top:16px;flex-wrap:wrap;gap:8px">
      <button class="bs bsp" onclick="doPrint('receipt')">🖨️ Print Receipt</button>
      <button class="bs bsg" onclick="shareWhatsApp('${id}')">📤 WhatsApp</button>
      <button class="bs bsg" onclick="renderAddPawnFlow(document.getElementById('pageContent'))">➕ Naya Entry</button>
      <button class="bs bsg" onclick="loadPage('dashboard')">← Dashboard</button>
    </div>
  </div>`;
}

function shareWhatsApp(id) {
  const msg = encodeURIComponent(`Digital Bandhak Receipt\nBandhak ID: ${id}\nCustomer: ${pawnData.name}\nLoan: ${fmt(pawnData.loan||0)}\nDate: ${pawnData.ldate||''}`);
  window.open('https://wa.me/?text='+msg);
}

function printReceipt(pawnId) {
  openPayHist(pawnId, true); // Open payment history in read-only mode for printing
}

// ─── PRINT HELPERS ────────────────────────────────────────────
function doPrint(type) {
  const printArea = document.getElementById('printArea');
  if (printArea) {
    printArea.style.display = 'block';
    window.print();
    setTimeout(() => { printArea.style.display = 'none'; }, 3000);
  } else {
    window.print();
  }
}

function printCurrentPage(type) {
  // Build print content from current page data
  const printArea = document.getElementById('printArea');
  const printReceipt = document.getElementById('printReceipt');
  if (!printArea || !printReceipt) { window.print(); return; }
  
  const shopName = SHOP_NAME || 'Digital Bandhak';
  const today = new Date().toLocaleDateString('en-IN');
  
  if (type === 'report') {
    const stats = document.getElementById('reportStats')?.innerHTML || '';
    const table = document.getElementById('reportTable')?.innerHTML || '';
    printReceipt.innerHTML = `
      <div class="rh">
        <div style="font-size:18px;font-weight:800;color:#FF6B00">📋 ${shopName} — Report</div>
        <div style="font-size:12px;color:#8B6040">Generated: ${today}</div>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin:10px 0">${stats}</div>
      ${table}
      <div class="rf">Digital Bandhak Platform | digitalbandhak.in</div>`;
  } else if (type === 'search') {
    const results = document.getElementById('searchResults')?.innerHTML || '';
    printReceipt.innerHTML = `
      <div class="rh">
        <div style="font-size:18px;font-weight:800;color:#FF6B00">🔍 ${shopName} — Search Results</div>
        <div style="font-size:12px;color:#8B6040">Date: ${today}</div>
      </div>
      ${results}
      <div class="rf">Digital Bandhak Platform | digitalbandhak.in</div>`;
  }
  
  printArea.style.display = 'block';
  window.print();
  setTimeout(() => { printArea.style.display = 'none'; }, 3000);
}

function exportSearchPDF() {
  // Same as exportPDF but for search results
  const list = PAWNS.filter(p => {
    const q  = (document.getElementById('searchQ')?.value||'').toLowerCase();
    const st = document.getElementById('searchStatus')?.value||'all';
    const ds = document.getElementById('searchDate')?.value||'';
    const str = (p.id+(p.customer||p.customer_name||'')+(p.mobile||p.customer_mobile||'')+(p.item||p.item_description||'')).toLowerCase();
    return (!q||str.includes(q)) && (st==='all'||(p.status||'')==st) && (!ds||(p.date||p.loan_date||'')==ds);
  });
  
  // Override PAWNS temporarily for generatePDF
  const orig = [...PAWNS];
  PAWNS.length = 0;
  list.forEach(p => PAWNS.push(p));
  exportPDF();
  setTimeout(() => { PAWNS.length=0; orig.forEach(p=>PAWNS.push(p)); }, 500);
}

// ─── GLOBAL FESTIVAL OFFER ────────────────────────────────────
function previewGlobalOffer() {
  const disc = +(document.getElementById('gOfferDisc')?.value||0);
  const el = document.getElementById('gOfferPreview');
  if (!el || !disc) return;
  const plans = {Standard:1200, Premium:2400};
  el.innerHTML = Object.entries(plans).map(([name,price])=>{
    const discounted = Math.round(price*(1-disc/100));
    return `<span style="margin-right:14px;color:var(--saffron)">📋 ${name}: <s style="color:var(--muted)">₹${price}</s> → <b>₹${discounted}</b></span>`;
  }).join('');
}

function activateGlobalOffer() {
  const name  = document.getElementById('gOfferName')?.value || 'Festival Offer';
  const disc  = +(document.getElementById('gOfferDisc')?.value||0);
  const expiry= document.getElementById('gOfferExpiry')?.value || '';
  const target= document.getElementById('gOfferTarget')?.value || 'all';
  
  if (!disc || disc <= 0) { showToast('❌ Discount % daalo pehle!'); return; }
  
  // Save offer to localStorage for shop-side display
  const offer = { name, disc, expiry, target, active: true, by: 'Admin' };
  try { localStorage.setItem('db_global_offer', JSON.stringify(offer)); } catch(e) {}
  
  // Also send to server if available
  const fd = new FormData();
  fd.append('action', 'set_global_offer');
  fd.append('offer_data', JSON.stringify(offer));
  fetch('php/api.php', {method:'POST', body:fd}).catch(()=>{});
  
  document.getElementById('offerActiveBadge').style.display = '';
  showToast(`🎉 ${name} (${disc}% off) activate ho gaya! Sab shops dekh sakenge.`);
}

function deactivateOffer() {
  try { localStorage.removeItem('db_global_offer'); } catch(e) {}
  const fd = new FormData();
  fd.append('action', 'set_global_offer');
  fd.append('offer_data', JSON.stringify({active:false}));
  fetch('php/api.php', {method:'POST', body:fd}).catch(()=>{});
  document.getElementById('offerActiveBadge').style.display = 'none';
  showToast('❌ Offer deactivate ho gaya');
}


async function openPayHist(pawnId, readOnly) {
  pendingPayHistPawn = {id:pawnId, readOnly};
  const modal = document.getElementById('modalPayHist');
  const content = document.getElementById('payHistContent');
  content.innerHTML = '<div style="padding:40px;text-align:center;color:var(--muted)">Loading...</div>';
  modal.style.display = 'flex';
  
  // Find pawn
  let pawn = PAWNS.find(p=>p.id===pawnId);
  if (!pawn && pawnId) {
    try {
      const res = await fetch('php/api.php?action=get_payments&pawn_id='+pawnId);
      const d = await res.json();
      if (d.ok) pawn = {...(d.pawn||{}), payments: d.payments||[], loan:+(d.pawn?.loan_amount||0), paid:+(d.pawn?.total_paid||0), remaining:+(d.pawn?.total_remaining||0), customer:d.pawn?.customer_name, mobile:d.pawn?.customer_mobile, item:d.pawn?.item_description, interest:+(d.pawn?.interest_rate||2), date:d.pawn?.loan_date};
    } catch(e) {}
  }
  if (!pawn) { content.innerHTML = '<div style="padding:20px">Pawn not found</div>'; return; }
  
  renderPayHistModal(pawn, readOnly);
}

function renderPayHistModal(pawn, readOnly=false) {
  window._currentPayHistPawn = pawn; // Store for PDF button
  const content = document.getElementById('payHistContent');
  const loan = +pawn.loan||+pawn.loan_amount||0;
  const paid = +pawn.paid||+pawn.total_paid||0;
  const rem  = +pawn.remaining||+pawn.total_remaining||0;
  const rawDays = Math.floor((new Date()-new Date(pawn.date||pawn.loan_date||''))/(1000*86400));
  const days = Math.max(rawDays, 0);
  const intAcc = Math.round(loan*((+pawn.interest||+pawn.interest_rate||2)/100)*(days/30));
  const payments = pawn.payments||[];
  
  content.innerHTML = `
    <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:14px">
      <div>
        <div style="font-size:17px;font-weight:800">💵 Payment History</div>
        <div style="font-size:12px;color:var(--muted);margin-top:2px">${pawn.customer||pawn.customer_name||''} • ${pawn.id} • ${pawn.item||pawn.item_description||''}</div>
      </div>
      <div style="display:flex;gap:6px">
        <button class="bs bsp" style="font-size:13px" onclick="generatePawnPDF(window._currentPayHistPawn)" id="pdfBtn">📥 PDF</button>
        <button class="bs bsg" onclick="closeModal('modalPayHist')">✕ Close</button>
      </div>
    </div>
    <div id="payHistSaved" style="display:none;background:rgba(46,204,113,.1);border:1px solid var(--green);border-radius:10px;padding:10px;font-size:12px;color:var(--green);margin-bottom:12px">✅ Payment successfully saved! Remaining amount updated.</div>
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:9px;margin-bottom:16px">
      ${[[fmt(loan),'💰 Loan Amount','var(--text)'],[fmt(paid),'✅ Total Paid','var(--green)'],[fmt(rem),'⏳ Remaining','var(--red)']].map(([v,l,c])=>`
        <div style="background:var(--surface);border-radius:10px;padding:10px;text-align:center">
          <div style="font-size:15px;font-weight:800;color:${c}">${v}</div>
          <div style="font-size:12px;color:var(--muted);margin-top:2px">${l}</div>
        </div>`).join('')}
    </div>
    <div style="background:rgba(255,179,0,.07);border:1px solid rgba(255,179,0,.2);border-radius:10px;padding:12px;margin-bottom:16px;font-size:12px">
      ${[
        ['👤 Customer', pawn.customer||pawn.customer_name||'—'],
        ['📱 Mobile', (pawn.mobile||pawn.customer_mobile||'—')],
        ['🪪 Aadhaar', pawn.customer_aadhaar||pawn.aadhaar||'—'],
        ['📍 Address', pawn.customer_address||pawn.address||'—'],
        ['📦 Item', (pawn.item||pawn.item_description||'—')+(pawn.item_weight?' ('+pawn.item_weight+')':'')],
        ['📅 Loan Date', pawn.date||pawn.loan_date||'—'],
        ['🏁 Return Date', pawn.return_date||'—'],
        ['⏱️ Days Since Loan', days+' din'],
        ['📈 Interest @'+(pawn.interest||pawn.interest_rate||2)+'%/month', fmt(intAcc)],
      ].map(([k,v])=>`
        <div style="display:flex;justify-content:space-between;padding:3px 0;border-bottom:1px solid rgba(255,179,0,.1)">
          <span style="color:var(--muted)">${k}</span><b style="text-align:right;max-width:55%">${v}</b>
        </div>`).join('')}
      ${(()=>{
        let photos = [];
        try { 
          const raw = pawn.item_photos || pawn.photos || '[]';
          photos = typeof raw==='string' ? JSON.parse(raw) : (Array.isArray(raw) ? raw : []); 
        } catch(e){ photos=[]; }
        if (!photos.length) return '';
        return '<div style="margin-top:8px"><div style="font-size:12px;color:var(--muted);font-weight:700;margin-bottom:5px">📷 ITEM PHOTOS</div><div style="display:flex;gap:6px;flex-wrap:wrap">'+photos.map(src=>`<img src="${src}" style="width:65px;height:55px;object-fit:cover;border-radius:7px;cursor:pointer;border:1px solid rgba(255,107,0,.3)" onclick="openImgLightbox('${src}')">`).join('')+'</div></div>';
      })()}
    </div>
    <div style="font-size:13px;font-weight:700;color:var(--muted);margin-bottom:9px;text-transform:uppercase;letter-spacing:.5px">📋 Payment Timeline — ${payments.length} transactions</div>
    <div class="tl" style="max-height:230px;overflow-y:auto;margin-bottom:15px">
      ${payments.length===0?'<div style="text-align:center;padding:20px;color:var(--muted);font-size:13px">Abhi tak koi payment nahi aayi</div>':
        payments.map((p,i)=>`
          <div class="tli">
            <div class="tld" style="background:rgba(46,204,113,.1);border:2px solid var(--green)">💵</div>
            <div class="tlc">
              <div style="display:flex;justify-content:space-between;align-items:center">
                <div style="font-weight:800;color:var(--green);font-size:15px">${fmt(+p.amount||0)}</div>
                <div style="font-size:12px;color:var(--muted)">${p.payment_date||p.date||''}</div>
              </div>
              <div style="font-size:13px;color:var(--muted);margin-top:2px">
                Mode: <b style="color:var(--text)">${p.payment_mode||p.mode||'Cash'}</b>${p.note?` • ${p.note}`:''}
              </div>
              <div style="font-size:12px;color:var(--muted);margin-top:2px">Installment #${i+1}</div>
            </div>
          </div>`).join('')}
    </div>
    ${!readOnly && pawn.status==='active' ? `
      <div id="payAddBtn"><button class="bs bsp" style="width:100%" onclick="showPayAdd('${pawn.id}',${rem})">+ Naya Payment Add Karein</button></div>
      <div id="payAddForm" style="display:none;background:rgba(255,107,0,.05);border:1px solid rgba(255,107,0,.2);border-radius:12px;padding:14px">
        <div style="font-size:13px;font-weight:700;color:var(--saffron);margin-bottom:11px">💵 Naya Payment Enter Karein</div>
        <div class="fg2" style="margin-bottom:10px">
          <div class="fg"><label class="fl">Amount (₹) *</label><input class="fi" type="number" id="payAmt" placeholder="e.g. 5000" oninput="calcRemaining(${rem})"></div>
          <div class="fg"><label class="fl">Mode</label><select class="si" id="payMode"><option>Cash</option><option>UPI</option><option>NEFT</option><option>Cheque</option></select></div>
        </div>
        <div class="fg"><label class="fl">Note (optional)</label><input class="fi" id="payNote" placeholder="Koi remark..."></div>
        <div class="fg"><label class="fl">Owner Password *</label><input class="fi" type="password" id="payPwd" placeholder="Confirm karne ke liye password"></div>
        <div id="payPreview" style="display:none;background:var(--surface);border-radius:9px;padding:10px;font-size:12px;margin-bottom:10px"></div>
        <div class="brow">
          <button class="bs bsp" onclick="confirmPayment('${pawn.id}')">✅ Confirm Payment</button>
          <button class="bs bsg" onclick="document.getElementById('payAddForm').style.display='none';document.getElementById('payAddBtn').style.display=''">Cancel</button>
        </div>
      </div>` :
      pawn.status==='closed' ? `<div style="text-align:center;padding:12px;background:rgba(46,204,113,.07);border-radius:10px;color:var(--green);font-weight:700;font-size:13px">✅ Yeh bandhak puri tarah close ho chuka hai</div>` : ''}
  `;
}

function showPayAdd(pawnId, rem) {
  document.getElementById('payAddBtn').style.display = 'none';
  document.getElementById('payAddForm').style.display = '';
  document.getElementById('payAmt').focus();
}

function calcRemaining(rem) {
  const amt = +(document.getElementById('payAmt')?.value||0);
  const preview = document.getElementById('payPreview');
  if (amt > 0 && preview) {
    const after = Math.max(0, rem-amt);
    preview.style.display = '';
    preview.innerHTML = `
      <div style="display:flex;justify-content:space-between"><span style="color:var(--muted)">Abhi Remaining:</span><b style="color:var(--red)">${fmt(rem)}</b></div>
      <div style="display:flex;justify-content:space-between;margin-top:4px"><span style="color:var(--muted)">Is Payment ke baad:</span><b style="color:var(--green)">${fmt(after)}</b></div>`;
  }
}

async function confirmPayment(pawnId) {
  const amt = +(document.getElementById('payAmt')?.value||0);
  const mode = document.getElementById('payMode')?.value||'Cash';
  const note = document.getElementById('payNote')?.value||'';
  const pwd  = document.getElementById('payPwd')?.value||'';
  
  if (!amt||amt<=0) { alert('Amount sahi daalein'); return; }
  if (!pwd) { alert('Password required'); return; }
  
  const fd = new FormData();
  fd.append('action','add_payment');
  fd.append('pawn_id', pawnId);
  fd.append('amount', amt);
  fd.append('mode', mode);
  fd.append('note', note);
  
  try {
    const res = await fetch('php/api.php',{method:'POST',body:fd});
    const d = await res.json();
    // Update local pawn
    const p = PAWNS.find(x=>x.id===pawnId);
    if (p) {
      p.paid = d.new_paid || (+p.paid + amt);
      p.remaining = d.new_remaining !== undefined ? d.new_remaining : Math.max(0,(+p.remaining||0)-amt);
      p.status = d.status || (p.remaining===0?'closed':'active');
      if (!p.payments) p.payments=[];
      p.payments.push({date:new Date().toISOString().split('T')[0],amount:amt,mode,note});
    }
    document.getElementById('payHistSaved').style.display='';
    document.getElementById('payAddForm').style.display='none';
    document.getElementById('payAddBtn').style.display='none';
    // Re-render with updated pawn
    const updatedPawn = PAWNS.find(x=>x.id===pawnId);
    if (updatedPawn) renderPayHistModal(updatedPawn, false);
  } catch(e) {
    // Demo mode
    const p = PAWNS.find(x=>x.id===pawnId);
    if (p) {
      p.paid = (+p.paid||0) + amt;
      p.remaining = Math.max(0,(+p.remaining||0)-amt);
      p.status = p.remaining===0?'closed':'active';
      if (!p.payments) p.payments=[];
      p.payments.push({date:new Date().toISOString().split('T')[0],amount:amt,mode,note});
      renderPayHistModal(p, false);
    }
    document.getElementById('payHistSaved').style.display='';
    document.getElementById('payAddForm').style.display='none';
    document.getElementById('payAddBtn').style.display='none';
    showToast('✅ Payment saved!');
  }
}

// ─── DELETE PAWN ──────────────────────────────────────────────
function openDelete(pawnId, customer, item) {
  pendingDeleteId = pawnId;
  document.getElementById('delPawnInfo').textContent = pawnId+' – '+customer+' – '+item;
  document.getElementById('delPwd').value = '';
  openModal('modalDelete');
}

async function confirmDelete() {
  const pwd = document.getElementById('delPwd')?.value;
  if (!pwd) { alert('Password required'); return; }
  
  const fd = new FormData();
  fd.append('action','delete_pawn');
  fd.append('pawn_id', pendingDeleteId);
  
  try {
    await fetch('php/api.php',{method:'POST',body:fd});
  } catch(e) {}
  
  PAWNS = PAWNS.filter(p=>p.id!==pendingDeleteId);
  closeModal('modalDelete');
  showToast('🗑️ Bandhak deleted');
  loadPage(currentPage);
}

// ─── CHAT PAGE ────────────────────────────────────────────────
let chatShopId = null;
let chatInterval = null;

// ─── LOAD CHAT MESSAGES ──────────────────────────────────────
let chatImgBase64 = null;

async function loadChatMessages() {
  const box = document.getElementById('chatMessages');
  if (!box) return;
  const shopId = chatShopId || SHOP_ID || '';
  if (!shopId) return;

  try {
    const res = await fetch('php/api.php?action=get_chat&shop_id=' + encodeURIComponent(shopId));
    const d   = await res.json();
    if (!d.ok) return;

    const msgs   = d.messages || [];
    const atBottom = box.scrollHeight - box.scrollTop - box.clientHeight < 60;

    if (msgs.length === 0) {
      box.innerHTML = '<div style="text-align:center;padding:40px;color:var(--muted);font-size:13px">💬 Koi message nahi abhi tak.<br>Pehla message bhejein!</div>';
      return;
    }

    box.innerHTML = msgs.map(m => {
      const isMe = ROLE === 'admin'
        ? (m.sender_role === 'admin')
        : (m.sender_role === 'shop');
      const cls    = isMe ? 'me' : 'them';
      const time   = m.created_at ? new Date(m.created_at).toLocaleTimeString('en-IN',{hour:'2-digit',minute:'2-digit'}) : '';
      const sender = !isMe ? `<div style="font-size:11px;color:var(--saffron);font-weight:700;margin-bottom:3px">${m.sender_name||'Unknown'}</div>` : '';
      const imgHtml = m.image_url
        ? `<img src="${m.image_url}" style="max-width:200px;border-radius:8px;display:block;margin-bottom:4px;cursor:pointer" onclick="openImgLightbox('${m.image_url}')">`
        : '';
      const txt = m.message
        ? `<div class="mb2" style="padding:9px 13px;border-radius:${isMe?'14px 14px 4px 14px':'14px 14px 14px 4px'};font-size:13px;line-height:1.5;word-break:break-word;max-width:100%">${escHtml(m.message)}</div>`
        : '';
      return `<div class="msg ${cls}">
        ${sender}
        ${imgHtml}${txt}
        <div style="font-size:11px;color:var(--muted);margin-top:2px;padding:0 4px">${time}</div>
      </div>`;
    }).join('');

    if (atBottom) box.scrollTop = box.scrollHeight;

    // Update chat badge in nav
    const badge = document.getElementById('chatBadge');
    if (badge) badge.style.display = 'none';

  } catch(e) {
    const box2 = document.getElementById('chatMessages');
    if (box2 && box2.innerHTML.includes('Loading')) {
      box2.innerHTML = '<div style="text-align:center;padding:30px;color:var(--muted)">⚠️ Messages load nahi ho sake. Dobara try karein.</div>';
    }
  }
}

function escHtml(str) {
  if (!str) return '';
  return String(str)
    .replace(/&/g,'&amp;')
    .replace(/</g,'&lt;')
    .replace(/>/g,'&gt;')
    .replace(/"/g,'&quot;')
    .replace(/\n/g,'<br>');
}

// ─── SEND CHAT MESSAGE ────────────────────────────────────────
async function sendChat() {
  const input  = document.getElementById('chatInput');
  const sendBtn = document.querySelector('.cs');
  const msg    = input ? input.value.trim() : '';
  const shopId = chatShopId || SHOP_ID || '';

  if (!msg && !chatImgBase64) return;
  if (!shopId) { showToast('❌ Shop select karein pehle'); return; }

  if (sendBtn) sendBtn.disabled = true;
  const orig = input ? input.value : '';
  if (input) input.value = '';

  const fd = new FormData();
  fd.append('action',      'send_chat');
  fd.append('shop_id',     shopId);
  fd.append('message',     msg);
  fd.append('sender_role', ROLE);
  fd.append('sender_name', ROLE === 'admin' ? (USER_NAME || 'Super Admin') : (SHOP_NAME || SHOP_ID));
  // Send image as Blob (more reliable than base64 string)
  if (chatImgBase64) {
    try {
      const arr = chatImgBase64.split(',');
      const mime = (arr[0].match(/:(.*?);/) || ['','image/jpeg'])[1];
      const bstr = atob(arr[1]);
      const u8 = new Uint8Array(bstr.length);
      for (let i=0; i<bstr.length; i++) u8[i] = bstr.charCodeAt(i);
      const blob = new Blob([u8], {type: mime});
      const ext = mime.split('/')[1] || 'jpg';
      fd.append('image', blob, 'chat_img.' + ext);
    } catch(e) {
      fd.append('image', chatImgBase64); // fallback
    }
  }

  try {
    const res = await fetch('php/api.php', { method:'POST', body:fd });
    const d   = await res.json();
    if (d.ok) {
      cancelChatImg();
      await loadChatMessages();
      const box = document.getElementById('chatMessages');
      if (box) box.scrollTop = box.scrollHeight;
    } else {
      if (input) input.value = orig;
      showToast('❌ Message nahi gaya. Try again.');
    }
  } catch(e) {
    if (input) input.value = orig;
    showToast('❌ Network error');
  }

  if (sendBtn) sendBtn.disabled = false;
  if (input) input.focus();
}

// ─── CHAT IMAGE PREVIEW ───────────────────────────────────────
function previewChatImg(inputEl) {
  if (!inputEl.files || !inputEl.files[0]) return;
  const reader = new FileReader();
  reader.onload = (e) => {
    chatImgBase64 = e.target.result;
    const prev    = document.getElementById('imgPreview');
    const prevImg = document.getElementById('imgPreviewImg');
    if (prev)    prev.style.display    = '';
    if (prevImg) prevImg.src           = chatImgBase64;
  };
  reader.readAsDataURL(inputEl.files[0]);
}

function cancelChatImg() {
  chatImgBase64 = null;
  const prev    = document.getElementById('imgPreview');
  const input   = document.getElementById('chatImgInput');
  if (prev)  prev.style.display = 'none';
  if (input) input.value        = '';
}



// ─── NOTIFICATIONS ────────────────────────────────────────────
async function loadNotifDot() {
  try {
    const res = await fetch('php/api.php?action=get_notifs');
    const d = await res.json();
    if (d.ok) {
      const unread = d.notifs.filter(n=>!+n.is_read).length;
      const dot = document.getElementById('notifDot');
      if (dot) dot.style.display = unread>0?'':'none';
    }
  } catch(e) {}
}

document.getElementById('modalNotif')?.addEventListener('click', function(e) {
  if (e.target === this) closeModal('modalNotif');
});

document.getElementById('notifBtn')?.addEventListener('click', async function() {
  const content = document.getElementById('notifContent');
  content.innerHTML = '<div style="padding:20px;text-align:center;color:var(--muted)">Loading...</div>';
  openModal('modalNotif');
  
  let notifs = [];
  try {
    const res = await fetch('php/api.php?action=get_notifs');
    const d = await res.json();
    if (d.ok) notifs = d.notifs;
  } catch(e) {
    notifs = [
      {id:1,is_read:0,icon:'💵',title:'Payment Received',body:'Amit Kumar ne ₹5,000 pay kiya – BDK-2025-001',created_at:'2025-03-04 10:00:00',type:'success'},
      {id:2,is_read:0,icon:'⚠️',title:'Subscription Expiring',body:'Aapka subscription 11 din mein expire hoga',created_at:'2025-03-04 09:00:00',type:'warn'},
      {id:3,is_read:0,icon:'🆕',title:'New Entry Added',body:'BDK-2025-047 – Suresh Kumar – Gold Chain',created_at:'2025-03-04 08:00:00',type:'info'},
      {id:4,is_read:1,icon:'💬',title:'Chat Message',body:'Super Admin ne reply kiya hai',created_at:'2025-03-03 18:00:00',type:'info'},
      {id:5,is_read:1,icon:'✅',title:'Bandhak Closed',body:'BDK-2025-002 – Priya Singh – Fully paid',created_at:'2025-03-03 12:00:00',type:'success'},
    ];
  }
  
  const unread = notifs.filter(n=>!+n.is_read).length;
  content.innerHTML = `
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px">
      <div>
        <div style="font-size:17px;font-weight:800">🔔 Notifications</div>
        ${unread>0?`<div style="font-size:13px;color:var(--saffron)">${unread} naye notifications</div>`:''}
      </div>
      <div class="brow">
        ${unread>0?`<button class="bs bsg" onclick="markAllRead()">✓ Sab Read</button>`:''}
        <button class="bs bsg" onclick="closeModal('modalNotif')">✕</button>
      </div>
    </div>
    <div style="max-height:380px;overflow-y:auto" id="notifList">
      ${notifs.map(n=>`
        <div class="notif-item" style="opacity:${+n.is_read?.7:1}" onclick="markRead(${n.id})">
          <div class="ndot" style="background:${n.type==='success'?'var(--green)':n.type==='warn'?'var(--gold)':'var(--blue)'}"></div>
          <div style="font-size:18px;flex-shrink:0">${n.icon||'🔔'}</div>
          <div style="flex:1">
            <div style="font-size:12px;font-weight:${+n.is_read?600:700};display:flex;justify-content:space-between">
              <span>${n.title||''}</span>
              <span style="font-size:12px;color:var(--muted);flex-shrink:0">${n.created_at?new Date(n.created_at).toLocaleDateString('en-IN'):''}</span>
            </div>
            <div style="font-size:13px;color:var(--muted);margin-top:2px">${n.body||''}</div>
          </div>
          ${!+n.is_read?'<div style="width:7px;height:7px;border-radius:50%;background:var(--saffron);flex-shrink:0;margin-top:4px"></div>':''}
        </div>`).join('')}
    </div>`;
  document.getElementById('notifDot').style.display='none';
});

async function markAllRead() {
  try { await fetch('php/api.php',{method:'POST',body:new URLSearchParams({action:'mark_notifs_read'})}); } catch(e) {}
  document.querySelectorAll('.notif-item').forEach(el=>{el.style.opacity='0.7';});
  document.querySelectorAll('.notif-item > div:last-child').forEach(el=>{
    if (el.style.background?.includes('saffron')||el.style.background?.includes('FF6B00')) el.remove();
  });
}
function markRead(id) { /* local mark */ }

// ─── SETTINGS MODAL ───────────────────────────────────────────
document.getElementById('modalSettings')?.addEventListener('click', function(e) { if(e.target===this) closeModal('modalSettings'); });

document.getElementById('settingsContent')?.ownerDocument.getElementById('modalSettings')?.addEventListener('mousedown', ()=>{});

function openSettingsModal() {
  const content = document.getElementById('settingsContent');
  // Read current saved states
  const savedDark = localStorage.getItem('darkMode');
  let darkOn = savedDark !== '0'; // default dark
  let smsOn=true, wpOn=false;
  
  content.innerHTML = `
    <div style="display:flex;justify-content:space-between;margin-bottom:14px">
      <div style="font-size:17px;font-weight:800">⚙️ Settings</div>
      <button class="bs bsg" onclick="closeModal('modalSettings')">✕</button>
    </div>
    <div style="display:flex;gap:5px;margin-bottom:16px;background:var(--surface);border-radius:10px;padding:4px" id="setTabs">
      ${[['general','⚙️ General'],['notif','🔔 Notifications'],['security','🔐 Security']].map(([k,l])=>`
        <button onclick="switchSetTab('${k}')" id="stab-${k}" style="flex:1;padding:7px;border:none;border-radius:7px;background:${k==='general'?'var(--saffron)':'transparent'};color:${k==='general'?'#fff':'var(--muted)'};font:700 11px 'Baloo 2',sans-serif;cursor:pointer">${l}</button>`).join('')}
    </div>
    <div id="setPanel"></div>
`;
  
  const modal = document.getElementById('modalSettings');
  if (modal) modal.style.display='flex';
  switchSetTab('general');
}

function switchSetTab(tab) {
  ['general','notif','security'].forEach(t=>{
    const btn = document.getElementById('stab-'+t);
    if (btn) { btn.style.background=t===tab?'var(--saffron)':'transparent'; btn.style.color=t===tab?'#fff':'var(--muted)'; }
  });
  const panel = document.getElementById('setPanel');
  if (!panel) return;
  const darkOn = localStorage.getItem('darkMode') !== '0';
  
  const tog = (id, lbl, desc, on) => {
    const trackBg = on ? 'var(--saffron)' : 'rgba(255,255,255,.15)';
    const knobLeft = on ? '22px' : '2px';
    return `
    <div style="display:flex;justify-content:space-between;align-items:center;padding:12px 0;border-bottom:1px solid var(--border)">
      <div><div style="font-size:13px;font-weight:700">${lbl}</div><div style="font-size:13px;color:var(--muted);margin-top:2px">${desc}</div></div>
      <div id="tog-wrap-${id}" style="position:relative;width:44px;height:24px;background:${trackBg};border-radius:12px;cursor:pointer;flex-shrink:0;transition:background .2s" onclick="toggleSetting('${id}')">
        <div id="tog-knob-${id}" style="position:absolute;top:2px;left:${knobLeft};width:20px;height:20px;background:#fff;border-radius:50%;transition:left .2s"></div>
      </div>
    </div>`;
  };

  if (tab==='general') panel.innerHTML = 
    tog('set-dark','🌙 Dark Mode','Dark/Light theme switch (abhi lagu hoga)', darkOn) +
    tog('set-hindi','🌐 Hindi/English','UI Hindi/English switch', localStorage.getItem('lang')==='hi') +
    `<div class="fg" style="margin-top:12px"><label class="fl">&#9881; Default Interest Rate (%/month)</label>
      <input class="fi" id="set-interest" value="2" type="number" step="0.5" min="0" max="100"></div>
    <div class="fg" style="margin-top:8px"><label class="fl">&#127991; Platform Name</label>
      <input class="fi" id="set-platname" value="Digital Bandhak"></div>
    <div style="display:flex;justify-content:flex-end;margin-top:14px">
      <button class="bs bsp" id="settingsSaveBtn2" onclick="saveSettingsAndClose(this)">&#128190; Save Settings</button></div>`;
  
  else if (tab==='notif') panel.innerHTML = 
    tog('set-sms','&#128241; SMS Alerts','Payment aur bandhak ke SMS',true) +
    tog('set-wp','&#128172; WhatsApp','Payment receipt WhatsApp par',false) +
    tog('set-email-notif','&#128140; Email Notifications','Important updates email par',true) +
    tog('set-renewal','&#128276; Renewal Reminders','Subscription expire se pehle alert',true) +
    `<div style="display:flex;justify-content:flex-end;margin-top:14px">
      <button class="bs bsp" onclick="saveSettingsAndClose(this)">&#128190; Save & Close</button></div>`;
  
  else panel.innerHTML = 
    `<div class="fg"><label class="fl">&#128272; Current Password</label>
      <input class="fi" id="set-cur-pass" type="password" placeholder="........"></div>
    <div class="fg" style="margin-top:8px"><label class="fl">&#128273; New Password</label>
      <input class="fi" id="set-new-pass" type="password" placeholder="........"></div>
    <div class="fg" style="margin-top:8px"><label class="fl">&#9989; Confirm Password</label>
      <input class="fi" id="set-conf-pass" type="password" placeholder="........"></div>
    <div id="setPassMsg" style="display:none;padding:8px;border-radius:8px;font-size:12px;margin-top:8px"></div>
    <div style="display:flex;justify-content:flex-end;margin-top:14px">
      <button class="bs bsp" onclick="changePassword()">&#128274; Change Password</button></div>`;
}

function openModal(id) {
  if (id==='modalProfile') { renderProfileModal(); return; }
  if (id==='modalSettings') { openSettingsModal(); return; }
  if (id==='modalNotif')    { loadNotifications(); return; }
  const el = document.getElementById(id);
  if (el) el.style.display='flex';
}

function renderProfileModal() {
  const content = document.getElementById('profileContent');
  document.getElementById('modalProfile').style.display='flex';
  
  if (ROLE==='customer') {
    content.innerHTML = `
      <div style="display:flex;justify-content:space-between;margin-bottom:14px">
        <div style="font-size:17px;font-weight:800">👤 Meri Profile</div>
        <button class="bs bsg" onclick="closeModal('modalProfile')">✕</button>
      </div>
      <div style="background:linear-gradient(135deg,rgba(255,107,0,.12),rgba(255,179,0,.06));border:1px solid rgba(255,107,0,.2);border-radius:14px;padding:20px;text-align:center;margin-bottom:16px">
        <div class="av" style="width:64px;height:64px;font-size:24px;margin:0 auto 10px">AK</div>
        <div style="font-size:17px;font-weight:800">Amit Kumar</div>
        <div style="font-size:13px;color:var(--muted)">Customer</div>
        <span class="b bg" style="margin-top:6px;display:inline-flex">✅ Verified</span>
      </div>
      ${[['📦 Bandhak ID','BDK-2025-001'],['📱 Mobile','98765XXXXX'],['🔐 Aadhaar','XXXX-XXXX-4521'],['🏪 Shop','Sharma Bandhak Ghar']].map(([l,v])=>`
        <div style="display:flex;justify-content:space-between;padding:9px 0;border-bottom:1px solid var(--border);font-size:13px">
          <span style="color:var(--muted)">${l}</span><b>${v}</b>
        </div>`).join('')}
      <div style="background:rgba(255,179,0,.07);border:1px solid rgba(255,179,0,.15);border-radius:10px;padding:11px;margin-top:14px;font-size:13px;color:var(--muted)">
        ℹ️ Profile details change karne ke liye apni shop se contact karein.
      </div>
      <div class="brow" style="margin-top:14px;justify-content:flex-end">
        <button class="bs bsg" onclick="closeModal('modalProfile')">Close</button>
      </div>`;
    return;
  }
  
  const initials = ROLE==='admin'?'SA':(USER_NAME?USER_NAME.split(' ').map(x=>x[0]).slice(0,2).join('').toUpperCase():'RS');
  const roleLbl  = ROLE==='admin'?'Super Admin':'Shop Owner';
  
  content.innerHTML = `
    <div style="display:flex;justify-content:space-between;margin-bottom:14px">
      <div style="font-size:17px;font-weight:800">👤 Profile Settings</div>
      <button class="bs bsg" onclick="closeModal('modalProfile')">✕</button>
    </div>
    <div style="background:linear-gradient(135deg,rgba(255,107,0,.12),rgba(255,179,0,.06));border:1px solid rgba(255,107,0,.2);border-radius:14px;padding:18px;text-align:center;margin-bottom:16px;position:relative">
      <div style="position:relative;display:inline-block;margin-bottom:8px">
        <div class="av" id="profAvatarCircle" style="width:68px;height:68px;font-size:26px;overflow:hidden;padding:0">
          ${(window.USER_PHOTO||USER_PHOTO_INIT) ? 
            `<img id="profAvatarImg" src="${window.USER_PHOTO||USER_PHOTO_INIT}" style="width:100%;height:100%;object-fit:cover;border-radius:50%;display:block">
             <span id="profAvatarText" style="display:none">${initials}</span>` :
            `<img id="profAvatarImg" src="" alt="" style="display:none">
             <span id="profAvatarText">${initials}</span>`
          }
        </div>
        <label title="Photo change karein" style="position:absolute;bottom:-2px;right:-2px;width:24px;height:24px;background:var(--saffron);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:12px;cursor:pointer;border:2px solid var(--card)">📷<input type="file" id="profPhotoInput" accept="image/*" style="display:none" onchange="previewProfPhoto(this)"></label>
      </div>
      <div style="font-size:17px;font-weight:800">${USER_NAME||roleLbl}</div>
      <div style="font-size:13px;color:var(--muted)">${roleLbl}</div>
      ${ROLE==='shop'?`<div style="font-size:13px;color:var(--saffron);margin-top:2px">${SHOP_NAME||''} – ${SHOP_ID}</div>`:''}
      <div style="display:flex;justify-content:center;gap:6px;margin-top:8px;flex-wrap:wrap;padding:0 4px">
        <span class="b bo" style="font-size:11px">📅 Joined: 01 Jan 2024</span>
        <span class="b bg" style="font-size:11px">${ROLE==='admin'?SHOPS.length + ' shops':'Standard'}</span>
      </div>
    </div>
    <div id="profView">
      <div style="font-size:13px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px">Account Info</div>
      ${[['📧 Email',USER_EMAIL||'—'],['📱 Mobile',USER_MOBILE||'—'],['👤 Role',roleLbl]].map(([l,v])=>`
        <div style="display:flex;justify-content:space-between;padding:9px 0;border-bottom:1px solid var(--border);font-size:13px">
          <span style="color:var(--muted)">${l}</span><b>${v}</b>
        </div>`).join('')}
      <div class="brow" style="margin-top:14px;flex-wrap:wrap;gap:8px">
        <button class="bs bsp" onclick="showProfEdit()">✏️ Edit Profile</button>
        ${ROLE==='shop'?`<button class="bs bsg" onclick="document.getElementById('modalTC').style.display='flex';renderTC()">📜 T&C</button>`:''}
        <button class="bs bsg" onclick="closeModal('modalProfile')">Close</button>
        <button class="bs" style="background:rgba(231,76,60,.12);color:#e74c3c;border:1px solid rgba(231,76,60,.3);width:100%;margin-top:4px" onclick="if(confirm('Logout karna chahte hain?'))doLogout()">🚪 Logout</button>
      </div>
    </div>
    <div id="profEdit" style="display:none">
      <div class="fg2" style="margin-bottom:4px">
        <div class="fg"><label class="fl">Full Name</label><input class="fi" id="pe-name" value="${USER_NAME||''}"></div>
        <div class="fg"><label class="fl">Email</label><input class="fi" id="pe-email" value="${USER_EMAIL||''}"></div>
        <div class="fg"><label class="fl">Mobile</label><input class="fi" id="pe-mobile" value="${USER_MOBILE||''}"></div>
        ${ROLE==='shop'?`<div class="fg"><label class="fl">Shop Name</label><input class="fi" id="pe-shop" value="${SHOP_NAME||''}"></div>`:''}
      </div>
      <div style="border-top:1px solid var(--border);padding-top:13px;margin-top:6px">
        <div style="font-size:13px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:10px">🔒 Change Password</div>
        <div class="fg2">
          <div class="fg"><label class="fl">Current Password</label><input class="fi" type="password" id="pe-curr" placeholder="••••••••"></div>
          <div class="fg"><label class="fl">New Password</label><input class="fi" type="password" id="pe-new" placeholder="••••••••"></div>
          <div class="fg"><label class="fl">Confirm Password</label><input class="fi" type="password" id="pe-conf" placeholder="••••••••"></div>
        </div>
      </div>
      <div id="profSaved" style="display:none;background:rgba(46,204,113,.1);border:1px solid var(--green);border-radius:10px;padding:9px;font-size:12px;color:var(--green);margin-bottom:12px">✅ Changes successfully saved!</div>
      <div class="brow" style="margin-top:10px;flex-wrap:wrap">
        <button class="bs bsp" onclick="saveProfEdit()">💾 Save All Changes</button>
        <button class="bs bsg" onclick="document.getElementById('profEdit').style.display='none';document.getElementById('profView').style.display=''">Cancel</button>
        ${ROLE==='shop'?`<button class="bs bsg" onclick="document.getElementById('modalTC').style.display='flex';renderTC()">📜 Terms & Conditions</button>`:''}
      </div>
    </div>`;
}

function showProfEdit() {
  document.getElementById('profView').style.display='none';
  document.getElementById('profEdit').style.display='';
}

async function saveProfEdit() {
  // Validate passwords if entered
  const newPass  = document.getElementById('pe-new')?.value || '';
  const confPass = document.getElementById('pe-conf')?.value || '';
  const currPass = document.getElementById('pe-curr')?.value || '';
  const savedEl  = document.getElementById('profSaved');
  
  if (newPass && newPass !== confPass) {
    if (savedEl) { 
      savedEl.style.display='';
      savedEl.style.cssText += ';background:rgba(231,76,60,.1);border-color:var(--red);color:var(--red)';
      savedEl.innerHTML = '❌ New password aur confirm password match nahi kar rahe!';
      setTimeout(()=>{ savedEl.style.display='none'; savedEl.style.cssText=''; }, 3500);
    }
    return;
  }
  
  // Button spinner
  const saveBtn = document.querySelector('#profEdit .bs.bsp');
  if (saveBtn) { saveBtn._orig = saveBtn.innerHTML; saveBtn.disabled=true; saveBtn.innerHTML='<span class="spin"></span> Saving...'; }
  
  const fd = new FormData();
  fd.append('action','save_profile');
  fd.append('name',       document.getElementById('pe-name')?.value || '');
  fd.append('email',      document.getElementById('pe-email')?.value || '');
  fd.append('mobile',     document.getElementById('pe-mobile')?.value || '');
  fd.append('shop_name',  document.getElementById('pe-shop')?.value || '');
  fd.append('current_pass', currPass);
  fd.append('new_pass',   newPass);
  fd.append('conf_pass',  confPass);
  
  // Photo upload - use globally stored file (persists across re-renders)
  const photoInput = document.getElementById('profPhotoInput');
  const photoFile = (photoInput?.files?.length ? photoInput.files[0] : null) || _pendingProfilePhoto;
  if (photoFile) fd.append('photo', photoFile);
  
  let ok = false;
  try {
    const res = await fetch('php/api.php', {method:'POST', body:fd});
    const d = await res.json();
    ok = d.ok !== false;
    // Save returned photo path for persistence
    if (ok && d.photo) {
      window.USER_PHOTO = d.photo;
      // Update all avatar images immediately - no refresh needed
      document.querySelectorAll('#profAvatarImg').forEach(img => { img.src = d.photo; img.style.display='block'; });
      document.querySelectorAll('#profAvatarText').forEach(el => { el.style.display='none'; });
    }
    if (ok) { _pendingProfilePhoto = null; window._pendingProfilePhotoB64 = null; }
    
    // Update global vars and UI after save
    if (ok) {
      const newName   = document.getElementById('pe-name')?.value || '';
      const newEmail  = document.getElementById('pe-email')?.value || '';
      const newMobile = document.getElementById('pe-mobile')?.value || '';
      // Update window globals (const vars can't be reassigned)
      if (newName)   window.USER_NAME   = newName;
      if (newEmail)  window.USER_EMAIL  = newEmail;
      if (newMobile) window.USER_MOBILE = newMobile;
      // Re-render modal so view shows updated data
      setTimeout(() => {
        renderProfileModal();
        // Switch to view mode
        const pe = document.getElementById('profEdit');
        const pv = document.getElementById('profView');
        if (pe) pe.style.display = 'none';
        if (pv) pv.style.display = '';
      }, 400);
    }
  } catch(e) { 
    console.error('Profile save error:', e);
    ok = false; 
  }
  
  // Restore button
  if (saveBtn) { saveBtn.disabled=false; saveBtn.innerHTML=saveBtn._orig||'💾 Save All Changes'; }
  
  // Show result
  if (savedEl) {
    savedEl.style.display = '';
    if (ok) {
      savedEl.style.cssText = 'display:block;background:rgba(46,204,113,.1);border:1px solid var(--green);border-radius:10px;padding:9px;font-size:12px;color:var(--green);margin-bottom:12px';
      savedEl.innerHTML = '✅ Profile saved!';
      showToast('✅ Profile successfully saved!');
      // Switch back to view mode after 1.5s
      setTimeout(() => {
        const pe = document.getElementById('profEdit');
        const pv = document.getElementById('profView');
        if (pe) pe.style.display = 'none';
        if (pv) pv.style.display = '';
      }, 1500);
    } else {
      savedEl.style.cssText = 'display:block;background:rgba(231,76,60,.1);border:1px solid var(--red);border-radius:10px;padding:9px;font-size:12px;color:var(--red);margin-bottom:12px';
      savedEl.innerHTML = '❌ Save failed. Try again.';
    }
    setTimeout(()=>{ savedEl.style.display='none'; }, 4000);
  }
  
  // Clear password fields
  ['pe-curr','pe-new','pe-conf'].forEach(id => { const el=document.getElementById(id); if(el) el.value=''; });
}

let _pendingProfilePhoto = null; // Store selected photo file globally

function previewProfPhoto(input) {
  if (!input.files || !input.files[0]) return;
  _pendingProfilePhoto = input.files[0]; // Store file globally
  const reader = new FileReader();
  reader.onload = (e) => {
    window._pendingProfilePhotoB64 = e.target.result; // Store base64 too
    const img = document.getElementById('profAvatarImg');
    const txt = document.getElementById('profAvatarText');
    if (img) { img.src = e.target.result; img.style.display='block'; }
    if (txt) txt.style.display='none';
  };
  reader.readAsDataURL(input.files[0]);
}

async function renderChatPage(el) {
  clearInterval(chatInterval);
  
  // Load shops if admin and not loaded
  if (ROLE === 'admin' && (!SHOPS || SHOPS.length === 0)) {
    try {
      const res = await fetch('php/api.php?action=get_shops');
      const d = await res.json();
      if (d.ok && d.shops) SHOPS = d.shops;
    } catch(e) {}
  }
  
  chatShopId = ROLE === 'shop' ? SHOP_ID : (SHOPS[0]?.id || '');
  const shops = SHOPS || [];
  const firstShop = shops[0] || {};
  const isMob = window.innerWidth <= 768;

  el.innerHTML = `<div class="cc">
    ${ROLE==='admin' ? `
    <div class="cl" id="chatShopList">
      <div style="padding:11px 13px;border-bottom:1px solid var(--border);font-weight:700;font-size:13px">💬 Shop Chats (${shops.length})</div>
      ${shops.length === 0
        ? '<div style="padding:20px;text-align:center;color:var(--muted);font-size:13px">Koi shop nahi mili</div>'
        : shops.map((s,i) => `
        <div class="cli${i===0?' active':''}" id="chatItem-${s.id}" onclick="switchChatShop('${s.id}','${s.name||s.id}')" style="display:flex;align-items:center;gap:10px;padding:12px 14px;box-sizing:border-box;width:100%">
          <div class="av" style="width:40px;height:40px;font-size:15px;flex-shrink:0">${(s.owner_name||s.name||'?')[0].toUpperCase()}</div>
          <div style="flex:1;min-width:0">
            <div style="font-size:14px;font-weight:800;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:var(--text)">${s.name||s.id}</div>
            <div style="font-size:11px;color:var(--muted);margin-top:2px">${s.id} &nbsp;·&nbsp; <span style="color:${s.status==='active'?'var(--green)':'var(--muted)'}">${s.status||'—'}</span></div>
          </div>
          <span style="font-size:18px;flex-shrink:0">💬</span>
        </div>`).join('')}
    </div>` : ''}
    <div class="cw" id="chatWindow" ${ROLE==='admin' && isMob ? 'style="display:none"' : ''}>
      <div class="ctb" id="chatTopBar" style="display:flex;align-items:center;gap:8px;padding:10px 12px;border-bottom:1px solid var(--border);min-height:54px;box-sizing:border-box">
        ${ROLE==='admin' ? `<button id="chatBackBtn" onclick="chatGoBack()" style="background:rgba(255,107,0,.15);border:1px solid rgba(255,107,0,.3);color:var(--saffron);border-radius:8px;padding:6px 10px;cursor:pointer;font-size:13px;flex-shrink:0;white-space:nowrap">← Back</button>` : ''}
        <div class="av" style="width:36px;height:36px;font-size:14px;flex-shrink:0" id="chatAvatar">${ROLE==='admin' ? ((firstShop.owner_name||firstShop.name||'S')[0].toUpperCase()) : 'SA'}</div>
        <div id="chatTitle" style="flex:1;min-width:0;overflow:hidden">
          <div style="font-weight:700;font-size:14px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:var(--text)">${ROLE==='admin' ? (firstShop.name||'Shop select karein') : 'Super Admin'}</div>
          <div style="font-size:11px;color:var(--muted);overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${ROLE==='admin' ? (firstShop.id ? firstShop.id+' • '+( firstShop.owner_name||'') : 'Koi shop select karein') : 'Digital Bandhak Support'}</div>
        </div>
        <span style="flex-shrink:0;font-size:11px;background:rgba(46,204,113,.15);color:var(--green);padding:3px 8px;border-radius:20px;font-weight:700">● Online</span>
      </div>
      <div class="cms" id="chatMessages">
        <div style="text-align:center;padding:30px;color:var(--muted)">
          <span class="spin" style="width:20px;height:20px;border-width:2px"></span>
          <div style="margin-top:8px;font-size:13px">Loading...</div>
        </div>
      </div>
      <div class="cib">
        <label style="cursor:pointer;font-size:19px;color:var(--muted);flex-shrink:0">
          📷<input type="file" accept="image/*" id="chatImgInput" style="display:none" onchange="previewChatImg(this)">
        </label>
        <input class="ci" id="chatInput" placeholder="Message likhein..." onkeydown="if(event.key==='Enter')sendChat()">
        <button class="cs" onclick="sendChat()">➤</button>
      </div>
      <div id="imgPreview" style="padding:8px 15px;border-top:1px solid var(--border);display:none">
        <img id="imgPreviewImg" style="max-width:130px;border-radius:6px;display:block">
        <div style="font-size:12px;color:var(--muted);margin-top:3px">Preview <button onclick="cancelChatImg()" style="background:none;border:none;color:var(--red);cursor:pointer;font-size:12px">✕ Cancel</button></div>
      </div>
    </div>
  </div>`;

  // Mobile: show shop list first, hide chat window
  if (isMob && ROLE === 'admin') {
    const list = document.getElementById('chatShopList');
    const win  = document.getElementById('chatWindow');
    if (list) list.classList.add('mobile-show');
    if (win)  win.style.display = 'none';
  }

  // On desktop admin - load first shop messages
  if (ROLE === 'admin' && chatShopId && !isMob) {
    await loadChatMessages();
  } else if (ROLE === 'shop') {
    await loadChatMessages();
  }
  chatInterval = setInterval(loadChatMessages, 5000);
}

async function switchChatShop(shopId, shopName) {
  chatShopId = shopId;
  // Highlight active shop in list
  document.querySelectorAll('.cli').forEach(el => el.classList.remove('active'));
  document.getElementById('chatItem-'+shopId)?.classList.add('active');
  // Update topbar
  const shop = SHOPS.find(s => s.id === shopId) || {name:shopName, id:shopId};
  const title = document.getElementById('chatTitle');
  const avatar = document.getElementById('chatAvatar');
  if (title) {
    title.innerHTML = `
      <div style="font-weight:700;font-size:14px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:var(--text)">${shop.name||shopName}</div>
      <div style="font-size:11px;color:var(--muted);overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${shop.id}${shop.owner_name?' · '+shop.owner_name:''}</div>`;
  }
  if (avatar) avatar.textContent = (shop.owner_name||shop.name||'?')[0].toUpperCase();
  // Mobile: hide list, show chat
  if (window.innerWidth <= 768) {
    const list = document.getElementById('chatShopList');
    const win  = document.getElementById('chatWindow');
    if (list) { list.classList.remove('mobile-show'); list.style.display = 'none'; }
    if (win)  { win.style.display = 'flex'; win.style.flexDirection = 'column'; win.style.width = '100%'; }
  }
  await loadChatMessages();
}

function chatGoBack() {
  const list = document.getElementById('chatShopList');
  const win  = document.getElementById('chatWindow');
  if (!list || !win) return;
  if (window.innerWidth <= 768) {
    list.classList.add('mobile-show');
    list.style.display = '';
    win.style.display  = 'none';
  } else {
    list.style.display = '';
    win.style.display  = '';
  }
}

// ─── CHAT BADGE ───────────────────────────────────────────────
async function checkChatUnread() {
  try {
    const res = await fetch('php/api.php?action=chat_unread');
    const d = await res.json();
    const badge = document.getElementById('chatBadge');
    if (badge && d.count>0) { badge.textContent=d.count; badge.style.display=''; }
  } catch(e) {}
}

// ─── MODAL CLOSE ON OUTSIDE CLICK ────────────────────────────
document.querySelectorAll('.mo').forEach(m => {
  m.addEventListener('click', function(e) {
    if (e.target === this) this.style.display='none';
  });
});

// Re-wire topbar buttons after load
setTimeout(()=>{
  document.querySelectorAll('[onclick="openModal(\'modalSettings\')"]').forEach(b=>{
    b.setAttribute('onclick','openSettingsModal()');
  });
},100);

// TC Modal
function renderTC() {
  const container = document.getElementById('tcContent');
  if (!container) return;
  const items = [
    {n:'1',ic:'🏪',title:'Platform Ka Use',body:'Digital Bandhak ek software tool hai jo aapki bandhak dukaan ke records manage karne mein madad karta hai.\n\nSirf registered aur verified shop owners hi platform use kar sakte hain.'},
    {n:'2',ic:'🔐',title:'Data Privacy aur Security',body:'Customer ka naam, mobile, aur Aadhaar data sirf aapki shop ke records mein rahega.\n\nAadhaar number hamesha masked (XXXX-XXXX-XXXX) rahega.'},
    {n:'3',ic:'⚠️',title:'Super Admin ki Zimmedari',body:'Super Admin sirf ek platform manager hai. Seedha zimmedar NAHI hai.\n\n📓 Har bandhak entry notebook mein bhi zaroor likhein.'},
    {n:'4',ic:'💳',title:'Subscription aur Payment',body:'Subscription fees refund nahi hogi.\n\nFree Trial 7 din ka hai.\n\nPlans: Free Trial (₹0) | Standard ₹1,200/year | Premium ₹2,400/year'},
    {n:'5',ic:'⚖️',title:'Legal Zimmedari',body:'Platform koi legal advice nahi deta. Aap apne vyapar ke liye khud zimmedar hain.'},
    {n:'6',ic:'📓',title:'Notebook Backup',body:'Har naye bandhak ki entry notebook mein bhi likhein.\nMonthly ek baar apne records check karein.'},
  ];
  container.innerHTML = items.map(s=>`
    <div style="margin-bottom:18px">
      <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px">
        <div style="width:26px;height:26px;border-radius:50%;background:rgba(255,107,0,.15);border:1px solid rgba(255,107,0,.3);display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:800;color:var(--saffron);flex-shrink:0">${s.n}</div>
        <div style="font-size:14px;font-weight:800">${s.ic} ${s.title}</div>
      </div>
      <div style="padding-left:34px;color:var(--muted);white-space:pre-line;font-size:12px;line-height:1.8">${s.body}</div>
    </div>`).join('');
}

// ── CHANGE PASSWORD (Settings → Security tab) ──────────────────
async function changePassword() {
  const cur  = document.getElementById('set-cur-pass')?.value?.trim();
  const nw   = document.getElementById('set-new-pass')?.value?.trim();
  const conf = document.getElementById('set-conf-pass')?.value?.trim();
  if (!cur || !nw) { showToast('❌ Sab fields bharo'); return; }
  if (nw !== conf) { showToast('❌ New passwords match nahi kar rahe'); return; }
  if (nw.length < 6) { showToast('❌ Password kam se kam 6 characters ka hona chahiye'); return; }
  const fd = new FormData();
  fd.append('action','change_password');
  fd.append('current_pass', cur);
  fd.append('new_pass', nw);
  try {
    const res = await fetch('php/api.php',{method:'POST',body:fd});
    const d = await res.json();
    if (d.ok) { showToast('✅ Password change ho gaya!'); closeModal('modalSettings'); }
    else showToast('❌ '+(d.msg||'Error hua'));
  } catch(e) { showToast('✅ Password change ho gaya! (demo)'); closeModal('modalSettings'); }
}

// ── SAVE SETTINGS (General/Notif) ──────────────────────────────
async function saveSettings() {
  const btn = document.getElementById('settingsSaveBtn1') || document.getElementById('settingsSaveBtn2');
  await saveSettingsAndClose(btn);
}

// ─── DARK MODE TOGGLE ─────────────────────────────────────────

// ─── CUSTOMER ACCOUNTS (Admin) ──────────────────────────────
async function renderCustomerAccountsSection(containerId) {
  const el = document.getElementById(containerId);
  if (!el) return;
  el.innerHTML = '<div style="padding:20px;text-align:center;color:var(--muted)">Loading...</div>';
  
  let customers = [];
  try {
    const res = await fetch('php/api.php?action=get_pending_customers');
    const d = await res.json();
    if (d.ok) customers = d.customers || [];
  } catch(e) {}
  
  const pending = customers.filter(c=>c.status==='pending');
  const active  = customers.filter(c=>c.status==='active');
  const all     = customers;
  
  el.innerHTML = '<div style="display:flex;gap:8px;margin-bottom:10px;flex-wrap:wrap">'
    + '<button class="bs bsp" onclick="renderCustomerAccountsSection(&quot;custAccountsSection&quot;)">🔄 Refresh</button>'
    + (pending.length ? '<span class="b br" style="font-size:13px;padding:5px 12px">'+pending.length+' Pending</span>' : '')
    + '</div>'
    + (all.length === 0 ? '<div style="text-align:center;padding:30px;color:var(--muted)">Koi customer registered nahi</div>' :
      all.map(c => {
        const isPending = c.status === 'pending';
        const isActive  = c.status === 'active';
        const isBlocked = c.status === 'blocked';
        return '<div class="m-card" style="' + (isPending ? 'border-color:rgba(255,179,0,.4);' : '') + '">'
          + '<div class="m-card-main" onclick="toggleShopCard(this)">'
          + '<div style="display:flex;align-items:center;gap:10px">'
          + '<div style="width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,rgba(255,107,0,.2),rgba(255,179,0,.1));display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0">👤</div>'
          + '<div style="flex:1;min-width:0">'
          + '<div style="font-weight:800;font-size:13px">' + c.name + '</div>'
          + '<div style="font-size:11px;color:var(--muted)">📱 ' + c.mobile + (c.address ? ' · ' + c.address.substring(0,30) : '') + '</div>'
          + '</div>'
          + '<span class="b ' + (isPending?'by':isActive?'bg':'br') + '">' + c.status + '</span>'
          + '</div>'
          + '<div class="m-card-meta"><span>📅 ' + (c.registered_at||'').substring(0,10) + '</span>'
          + (c.shop_id ? '<span>🏪 '+c.shop_id+'</span>' : '') + '</div>'
          + '</div>'
          + '<div class="m-card-detail" style="display:none">'
          + (isPending ? 
            '<div class="m-card-actions">'
            + '<button class="m-act-btn m-act-green" data-cid="'+c.id+'" data-st="active" onclick="activateCustomer(+this.dataset.cid,this.dataset.st)">✅ Activate</button>'
            + '<button class="m-act-btn m-act-red" data-cid="'+c.id+'" data-st="blocked" onclick="activateCustomer(+this.dataset.cid,this.dataset.st)">🚫 Block</button>'
            + '</div>' 
            : isActive ?
            '<div class="m-card-actions"><button class="m-act-btn m-act-red" data-cid="'+c.id+'" data-st="blocked" onclick="activateCustomer(+this.dataset.cid,this.dataset.st)">🚫 Block Karo</button></div>'
            : '<div class="m-card-actions"><button class="m-act-btn m-act-green" data-cid="'+c.id+'" data-st="active" onclick="activateCustomer(+this.dataset.cid,this.dataset.st)">✅ Reactivate</button></div>')
          + '</div></div>';
      }).join('')
    );
}

async function activateCustomer(customerId, status) {
  try {
    const fd = new FormData();
    fd.append('action','activate_customer');
    fd.append('customer_id', customerId);
    fd.append('status', status);
    const res = await fetch('php/api.php', {method:'POST',body:fd});
    const d = await res.json();
    if (d.ok) {
      showToast(status==='active' ? '✅ Customer activate ho gaya!' : '🚫 Customer blocked');
      renderCustomerAccountsSection('custAccountsSection');
    } else {
      showToast('❌ ' + (d.msg||'Error'));
    }
  } catch(e) { showToast('❌ Network error'); }
}

function toggleSetting(id) {
  const wrap = document.getElementById('tog-wrap-'+id);
  const knob = document.getElementById('tog-knob-'+id);
  if (!wrap || !knob) return;
  const isOn = wrap.dataset.on !== 'false' && knob.style.left !== '2px';
  const newOn = !isOn;
  wrap.style.background = newOn ? 'var(--saffron)' : 'rgba(255,255,255,.15)';
  knob.style.left = newOn ? '22px' : '2px';
  wrap.dataset.on = newOn ? 'true' : 'false';
  if (id === 'set-dark') {
    applyDarkMode(newOn);
    localStorage.setItem('darkMode', newOn ? '1' : '0');
  }
  if (id === 'set-hindi') {
    localStorage.setItem('lang', newOn ? 'hi' : 'en');
    applyLang(newOn ? 'hi' : 'en');
  }
}

function applyDarkMode(on) {
  const r = document.documentElement;
  if (on) {
    r.style.setProperty('--bg',    '#120800');
    r.style.setProperty('--deep',  '#0d0500');
    r.style.setProperty('--card',  '#1e0f02');
    r.style.setProperty('--surface','#2a1800');
    r.style.setProperty('--border','rgba(255,107,0,.15)');
    r.style.setProperty('--text',  '#f5e6d0');
    r.style.setProperty('--muted', '#b08060');
    document.body.classList.remove('light-mode');
  } else {
    r.style.setProperty('--bg',    '#f0ebe0');
    r.style.setProperty('--deep',  '#e5ddd0');
    r.style.setProperty('--card',  '#ffffff');
    r.style.setProperty('--surface','#f8f4ee');
    r.style.setProperty('--border','rgba(0,0,0,.12)');
    r.style.setProperty('--text',  '#1a0800');
    r.style.setProperty('--muted', '#7a5540');
    document.body.classList.add('light-mode');
  }
}


// ─── HINDI/ENGLISH UI LABELS ──────────────────────────────────
const LANG = {
  en: {
    dashboard:'Dashboard', pawns:'Bandhak', payments:'Payments', 
    reports:'Reports', audit:'Audit', settings:'Settings', chat:'Chat',
    shops:'Shops', subs:'Subscriptions', addPawn:'Add Bandhak',
    logout:'Logout', profile:'Profile', search:'Search',
    active:'Active', closed:t('closed_bandhak','Closed'), total:'Total',
    loan:'Loan Amount', interest:'Interest Rate', customer:'Customer',
    date:'Date', status:'Status', action:'Action', save:'Save', cancel:'Cancel',
    export:'Export', filter:'Filter', generate:'Generate'
  },
  hi: {
    dashboard:'डैशबोर्ड', pawns:'बंधक', payments:'भुगतान',
    reports:'रिपोर्ट', audit:'ऑडिट', settings:'सेटिंग्स', chat:'चैट',
    shops:'दुकानें', subs:'सदस्यता', addPawn:'बंधक जोड़ें',
    logout:'लॉगआउट', profile:'प्रोफाइल', search:'खोजें',
    active:'सक्रिय', closed:'बंद', total:'कुल',
    loan:'ऋण राशि', interest:'ब्याज दर', customer:'ग्राहक',
    date:'तारीख', status:'स्थिति', action:'कार्य', save:'सेव', cancel:'रद्द करें',
    export:'निर्यात', filter:'फ़िल्टर', generate:'बनाएं'
  }
};

// ── FULL UI TRANSLATIONS ──────────────────────────────────────
var UI_TEXT = {
  en: {
    // Nav
    nav_home:'Home', nav_pawns:'Pawns', nav_add:'Add', nav_payments:'Payments', nav_profile:'Profile',
    // Dashboard
    dash_title:'Dashboard', total_loan:t('total_loan','Total Loan'), total_paid:'Total Paid', total_rem:t('total_rem','Remaining'),
    active_items:'Active Items', closed_items:'Closed Items',
    // Bandhak
    add_bandhak:'Add Bandhak', item_desc:'Item Description', loan_amt:'Loan Amount',
    interest_rate:'Interest Rate', loan_date:'Loan Date', return_date:'Return Date',
    customer_name:'Customer Name', mobile:'Mobile Number', aadhaar:'Aadhaar',
    // Payments
    record_payment:'Record Payment', payment_amt:'Payment Amount', payment_mode:'Payment Mode',
    payment_note:'Note', cash:'Cash', upi:'UPI', bank:'Bank Transfer',
    // Status
    active:'Active', closed:t('closed_bandhak','Closed'), pending:'Pending', blocked:'Blocked',
    // Actions
    save:'Save', cancel:'Cancel', edit:'Edit', delete:'Delete', search:'Search',
    export:'Export', filter:'Filter', back:'← Back', close:'Close',
    // Chat
    chat_placeholder:'Type a message...', send:'Send',
    // Profile
    edit_profile:'Edit Profile', change_photo:'Change Photo', change_pass:'Change Password',
    // Settings
    dark_mode:'Dark Mode', language:'Language', save_settings:'Save Settings',
    // Time
    days_ago:'days passed', days_left:'days left', overdue:'Overdue',
    // Sidebar nav
    nav_dashboard:'Dashboard', nav_shops:'Shops List', nav_subscriptions:'Subscriptions',
    nav_search:'Customer Search', nav_reports:'Reports', nav_audit:'Audit Logs',
    nav_calculator:'Interest Calculator', nav_chat:'Private Chat',
    nav_add_bandhak:'New Bandhak', nav_all_bandhak:'All Bandhak',
    nav_payments:'Payments', nav_subscription:'Subscription',
    nav_terms:'Terms & Conditions', nav_chat_admin:'Chat with Admin',
    edit_profile:'Edit Profile →', logout:'🚪 Logout',
    // Admin dashboard
    total_shops:'Total Shops', active_shops:'Active Shops',
    total_customers:'Total Customers', total_collected:'Total Collected',
    shops_manage:'Shops Manage', shops_manage_sub:'Add, edit, suspend shops',
    subscriptions:'Subscriptions', subscriptions_sub:'Plans, renewals, offers',
    recent_audit:'Recent Audit Logs',
    // Table headers
    time:'Time', action:'Action', user:'User', shop:'Shop', target:'Target',
    // Shop dashboard
    all_bandhak:'All Bandhak', new_entry:'New Entry',
  },
  hi: {
    // Nav
    nav_home:'होम', nav_pawns:'बंधक', nav_add:'जोड़ें', nav_payments:'भुगतान', nav_profile:'प्रोफाइल',
    // Dashboard
    dash_title:'डैशबोर्ड', total_loan:'कुल लोन', total_paid:'चुकाया', total_rem:'बाकी',
    active_items:'सक्रिय बंधक', closed_items:'बंद बंधक',
    active_bandhak:'सक्रिय बंधक', closed_bandhak:'बंद बंधक',
    all_bandhak:'सभी बंधक', new_entry:'+ नई एंट्री',
    // Bandhak
    add_bandhak:'बंधक जोड़ें', item_desc:'सामान का विवरण', loan_amt:'लोन राशि',
    interest_rate:'ब्याज दर', loan_date:'लोन तारीख', return_date:'वापसी तारीख',
    customer_name:'ग्राहक का नाम', mobile:'मोबाइल नंबर', aadhaar:'आधार',
    item_category:'सामान की श्रेणी', item_weight:'वज़न',
    // Payments
    record_payment:'भुगतान दर्ज करें', payment_amt:'भुगतान राशि', payment_mode:'भुगतान का तरीका',
    payment_note:'नोट', cash:'नकद', upi:'यूपीआई', bank:'बैंक ट्रांसफर',
    pay_history:'💵 भुगतान / इतिहास', total_paid_lbl:'चुकाया गया',
    // Status
    active:'सक्रिय', closed:'बंद', pending:'प्रतीक्षित', blocked:'अवरुद्ध',
    status:'स्थिति', overdue_lbl:'⚠️ समय पार', days_left_lbl:'दिन बचे',
    // Actions
    save:'सेव', cancel:'रद्द करें', edit:'संपादित', delete:'हटाएं', search:'खोजें',
    export:'निर्यात', filter:'फ़िल्टर', back:'← वापस', close:'बंद करें',
    add_payment:'+ भुगतान जोड़ें', view_detail:'विवरण देखें',
    // Table headers
    id:'आईडी', customer:'ग्राहक', item:'सामान', loan:'लोन', paid:'चुकाया', remaining:'बाकी',
    actions:'कार्रवाई', date:'तारीख',
    // Chat
    chat_placeholder:'संदेश लिखें...', send:'भेजें', chat_title:'सहायता चैट',
    // Profile
    edit_profile:'प्रोफाइल संपादित करें', change_photo:'फोटो बदलें', change_pass:'पासवर्ड बदलें',
    shop_name:'दुकान का नाम', owner_name:'मालिक का नाम',
    // Settings
    dark_mode:'डार्क मोड', language:'भाषा', save_settings:'सेटिंग सेव करें',
    general:'सामान्य', notifications:'सूचनाएं', security:'सुरक्षा',
    // Reports
    reports:'रिपोर्ट', total_shops:'कुल दुकानें', active_shops:'सक्रिय दुकानें',
    // Dashboard sections
    quick_actions:'त्वरित कार्य', recent_activity:'हाल की गतिविधि',
    subscription:'सदस्यता', days_left:'दिन बचे', expires:'समाप्ति',
    // Time
    days_ago:'दिन हो गए', days_left_msg:'दिन बचे', overdue:'समय पार',
    today:'आज', yesterday:'कल',
    // Misc
    loading:'लोड हो रहा है...', no_data:'कोई डेटा नहीं', try_again:'दोबारा कोशिश करें',
    standard_plan:'स्टैंडर्ड प्लान', active_till:'तक सक्रिय',
    // Sidebar nav
    nav_dashboard:'डैशबोर्ड', nav_shops:'दुकानें', nav_subscriptions:'सदस्यताएं',
    nav_search:'ग्राहक खोज', nav_reports:'रिपोर्ट', nav_audit:'ऑडिट लॉग',
    nav_calculator:'ब्याज कैलकुलेटर', nav_chat:'प्राइवेट चैट',
    nav_add_bandhak:'नया बंधक', nav_all_bandhak:'सभी बंधक',
    nav_payments:'भुगतान', nav_subscription:'सदस्यता',
    nav_terms:'नियम व शर्तें', nav_chat_admin:'एडमिन से चैट',
    edit_profile:'प्रोफाइल →', logout:'🚪 लॉगआउट',
    // Admin dashboard
    total_shops:'कुल दुकानें', active_shops:'सक्रिय दुकानें',
    total_customers:'कुल ग्राहक', total_collected:'कुल कमाई',
    shops_manage:'दुकान प्रबंधन', shops_manage_sub:'जोड़ें, बदलें, रोकें',
    subscriptions:'सदस्यताएं', subscriptions_sub:'प्लान, नवीनीकरण, ऑफर',
    recent_audit:'हाल के ऑडिट लॉग',
    // Table headers
    time:'समय', action:'कार्य', user:'उपयोगकर्ता', shop:'दुकान', target:'लक्ष्य',
    // Shop dashboard
    all_bandhak:'सभी बंधक', new_entry:'नई एंट्री',
  }
};

// ── TRANSLATE HELPER ──────────────────────────────────────────
function t(key, fallback) {
  if (typeof UI_TEXT === 'undefined') return fallback || key;
  const L = UI_TEXT[window._currentLang || 'en'] || UI_TEXT.en;
  return (L && L[key]) || fallback || (UI_TEXT.en && UI_TEXT.en[key]) || fallback || key;
}

function applyLang(lang) {
  const L = UI_TEXT[lang] || UI_TEXT.en;
  window._currentLang = lang;
  localStorage.setItem('lang', lang);

  // Update bottom nav labels
  const navItems = document.querySelectorAll('.bn-item');
  const navKeys  = ['nav_home','nav_pawns','nav_add','nav_payments','nav_profile'];
  navItems.forEach((el,i) => {
    const lbl = el.querySelector('.bn-lbl');
    if (lbl && navKeys[i]) lbl.textContent = L[navKeys[i]] || lbl.textContent;
  });

  // Update all elements with data-lang attribute
  document.querySelectorAll('[data-lang]').forEach(el => {
    const key = el.getAttribute('data-lang');
    if (L[key]) el.textContent = L[key];
  });

  // Update chat input placeholder
  const ci = document.getElementById('chatInput');
  if (ci) ci.placeholder = L.chat_placeholder;

  // Re-render current page content labels
  document.querySelectorAll('.ct').forEach(el => {
    const txt = el.textContent.trim();
    // Map common titles
    const titleMap_en = {'Dashboard':'Dashboard','Bandhak':'Pawns','Payments':'Payments','Reports':'Reports','Settings':'Settings','Chat':'Chat'};
    const titleMap_hi = {'Dashboard':'डैशबोर्ड','Bandhak':'बंधक','Payments':'भुगतान','Reports':'रिपोर्ट','Settings':'सेटिंग्स','Chat':'चैट'};
    const map = lang==='hi' ? titleMap_hi : titleMap_en;
    Object.entries(map).forEach(([en, tr]) => {
      if (el.textContent.includes(en) || el.textContent.includes(Object.entries(titleMap_hi).find(([,v])=>v===el.textContent.trim())?.[0]||'')) {
        // Only replace if it's a pure title (no numbers/symbols)
      }
    });
  });

  // Update search placeholders
  document.querySelectorAll('input[type="search"], input[placeholder*="Search"], input[placeholder*="Khojein"]').forEach(el => {
    el.placeholder = L.search + '...';
  });

  // Update current page title
  const PAGE_TITLES_HI = {
    dashboard:'डैशबोर्ड', shops:'दुकानें', subscriptions:'सदस्यता',
    search:'खोज', reports:'रिपोर्ट', audit:'ऑडिट', chat:'चैट',
    'add-pawn':'बंधक जोड़ें', pawns:'सभी बंधक', payments:'भुगतान',
    subscription:'सदस्यता', calculator:'कैलकुलेटर', terms:'नियम व शर्तें'
  };
  const PAGE_TITLES_EN = {
    dashboard:'Dashboard', shops:'Shops List', subscriptions:'Subscriptions',
    search:'Customer Search', reports:'Reports', audit:'Audit Logs', chat:'Private Chat',
    'add-pawn':'New Bandhak Entry', pawns:'All Bandhak', payments:'Payments',
    subscription:'Subscription', calculator:'Interest Calculator', terms:'Terms & Conditions'
  };
  const titleMap = lang === 'hi' ? PAGE_TITLES_HI : PAGE_TITLES_EN;
  if (typeof currentPage !== 'undefined' && titleMap[currentPage]) {
    const pt = document.getElementById('pageTitle');
    if (pt) pt.textContent = titleMap[currentPage];
  }

  showToast(lang === 'hi' ? '🌐 हिंदी भाषा लागू हुई' : '🌐 English language applied');
  
  // Reload current page content with new language
  // Re-render current page with new language
  if (window.currentPage) {
    setTimeout(() => {
      try { loadPage(window.currentPage); } catch(e){}
    }, 200);
  }
}

// Apply saved language on load
window._currentLang = localStorage.getItem('lang') || 'en';

// ─── IMAGE LIGHTBOX ───────────────────────────────────────────
function openImgLightbox(src) {
  let lb = document.getElementById('imgLightbox');
  if (!lb) {
    lb = document.createElement('div');
    lb.id = 'imgLightbox';
    lb.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,.92);z-index:9999;display:flex;align-items:center;justify-content:center;cursor:zoom-out';
    lb.innerHTML = `
      <button onclick="document.getElementById('imgLightbox').style.display='none'" 
        style="position:absolute;top:16px;right:20px;background:rgba(255,255,255,.15);color:#fff;border:none;border-radius:50%;width:36px;height:36px;font-size:18px;cursor:pointer;z-index:10000">✕</button>
      <img id="imgLightboxImg" style="max-width:92vw;max-height:90vh;border-radius:10px;box-shadow:0 20px 60px rgba(0,0,0,.5)">`;
    lb.addEventListener('click', e => { if (e.target===lb) lb.style.display='none'; });
    document.body.appendChild(lb);
  }
  document.getElementById('imgLightboxImg').src = src;
  lb.style.display = 'flex';
}

// ─── INTEREST CALCULATOR PAGE ─────────────────────────────────
function renderCalculatorPage(el) {
  el.innerHTML = `<div class="pb">
    <div class="card" style="max-width:600px;margin:0 auto">
      <div class="ch"><div class="ct">🧮 Interest Calculator</div></div>
      <div class="cb">
        <div class="fg2">
          <div class="fg">
            <label class="fl">Loan Amount (₹)</label>
            <input class="fi" id="calc-loan" type="number" placeholder="e.g. 25000" oninput="doCalc()">
          </div>
          <div class="fg">
            <label class="fl">Interest Rate (%/month)</label>
            <input class="fi" id="calc-rate" type="number" value="2" step="0.5" oninput="doCalc()">
          </div>
        </div>
        <div class="fg2" style="margin-top:6px">
          <div class="fg">
            <label class="fl">Duration</label>
            <input class="fi" id="calc-dur" placeholder="e.g. 3 mahine, 45 din, 1 saal" oninput="doCalcParse()">
          </div>
          <div class="fg">
            <label class="fl">Loan Date</label>
            <input class="fi" type="date" id="calc-date" value="${new Date().toISOString().split('T')[0]}" oninput="doCalc()">
          </div>
        </div>
        
                <!-- Result Box -->
        <div id="calcResult" style="display:none;margin-top:18px">
          <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-bottom:14px">
            <div style="background:rgba(255,107,0,.1);border:1px solid rgba(255,107,0,.3);border-radius:12px;padding:14px;text-align:center">
              <div id="cr-loan" style="font-size:20px;font-weight:800;color:var(--saffron)">₹0</div>
              <div style="font-size:12px;color:var(--muted);margin-top:3px">💰 Loan Amount</div>
            </div>
            <div style="background:rgba(255,179,0,.1);border:1px solid rgba(255,179,0,.3);border-radius:12px;padding:14px;text-align:center">
              <div id="cr-int" style="font-size:20px;font-weight:800;color:var(--gold)">₹0</div>
              <div id="cr-int-lbl" style="font-size:12px;color:var(--muted);margin-top:3px">📈 Interest</div>
            </div>
            <div style="background:rgba(46,204,113,.1);border:1px solid rgba(46,204,113,.3);border-radius:12px;padding:14px;text-align:center">
              <div id="cr-total" style="font-size:20px;font-weight:800;color:var(--green)">₹0</div>
              <div style="font-size:12px;color:var(--muted);margin-top:3px">✅ Total Due</div>
            </div>
          </div>
          <div style="background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:14px;font-size:12px">
            <div style="font-weight:800;margin-bottom:8px;color:var(--saffron)">📊 Breakdown</div>
            <div style="display:flex;justify-content:space-between;padding:4px 0;border-bottom:1px solid var(--border)">
              <span style="color:var(--muted)">Duration</span><b id="cr-dur">—</b>
            </div>
            <div style="display:flex;justify-content:space-between;padding:4px 0;border-bottom:1px solid var(--border)">
              <span style="color:var(--muted)">Per Day Interest</span><b id="cr-perday">—</b>
            </div>
            <div style="display:flex;justify-content:space-between;padding:4px 0;border-bottom:1px solid var(--border)">
              <span style="color:var(--muted)">Per Month Interest</span><b id="cr-permonth">—</b>
            </div>
            <div style="display:flex;justify-content:space-between;padding:4px 0">
              <span style="color:var(--muted)">Return Date</span><b id="cr-retdate" style="color:var(--saffron)">—</b>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>`;
  doCalc();
}

function doCalcParse() {
  // Parse duration text and update days
  const text = document.getElementById('calc-dur')?.value || '';
  const days = parseDurationText(text);
  if (days > 0) doCalc(days);
}

function parseDurationText(text) {
  text = text.toLowerCase().trim();
  let days = 0;
  if (/\d+\s*(saal|year|sal)/.test(text)) days = parseInt(text)*365;
  else if (/\d+\s*(mahine?|month|mahin)/.test(text)) days = parseInt(text)*30;
  else if (/\d+\s*(din|day|dinn)/.test(text)) days = parseInt(text);
  else if (/^\d+$/.test(text)) days = parseInt(text);
  return days;
}

function doCalc(forceDays) {
  const loan = +document.getElementById('calc-loan')?.value || 0;
  const rate = +document.getElementById('calc-rate')?.value || 2;
  const durText = document.getElementById('calc-dur')?.value || '';
  const dateVal = document.getElementById('calc-date')?.value || new Date().toISOString().split('T')[0];
  
  const days = forceDays || parseDurationText(durText) || 90; // default 90 days
  if (!loan) { document.getElementById('calcResult').style.display='none'; return; }
  
  const interest = Math.round(loan * (rate/100) * (days/30));
  const total = loan + interest;
  const perDay = Math.round(loan*(rate/100)/30);
  const perMonth = Math.round(loan*(rate/100));
  const retDate = new Date(new Date(dateVal).getTime() + days*86400000);
  
  const fmt = v => '₹'+Number(v).toLocaleString('en-IN');
  document.getElementById('calcResult').style.display='';
  document.getElementById('cr-loan').textContent = fmt(loan);
  document.getElementById('cr-int').textContent = fmt(interest);
  document.getElementById('cr-int-lbl').textContent = '📈 Interest ('+days+' din)';
  document.getElementById('cr-total').textContent = fmt(total);
  document.getElementById('cr-dur').textContent = days+' din';
  document.getElementById('cr-perday').textContent = fmt(perDay)+'/day';
  document.getElementById('cr-permonth').textContent = fmt(perMonth)+'/month';
  document.getElementById('cr-retdate').textContent = retDate.toLocaleDateString('en-IN',{day:'2-digit',month:'short',year:'numeric'});
}


// ══════════════════════════════════════════════════════════════
// ── NEW FUNCTIONS BLOCK ────────────────────────────────────────
// ══════════════════════════════════════════════════════════════

// ── BUTTON SPINNER HELPERS ────────────────────────────────────
function btnLoad(btn) {
  if (!btn) return;
  btn._orig = btn.innerHTML;
  btn.disabled = true;
  btn.innerHTML = '<span class="spin"></span>';
}
function btnReset(btn) {
  if (!btn) return;
  btn.disabled = false;
  btn.innerHTML = btn._orig || 'Submit';
}

// ── SAVE SETTINGS ─────────────────────────────────────────────
async function saveSettingsAndClose(btn) {
  if (btn) { btn._orig = btn.innerHTML; btn.disabled = true; btn.innerHTML = '<span class="spin"></span> Saving...'; }
  
  // Apply dark mode immediately
  const darkEl = document.getElementById('set-dark'); const darkOn = darkEl ? darkEl.checked : true;
  applyDarkMode(darkOn);
  localStorage.setItem('darkMode', darkOn?'1':'0');
  
  const fd = new FormData();
  fd.append('action', 'save_settings');
  fd.append('default_interest', document.getElementById('set-interest')?.value || '2');
  fd.append('sms_alerts',  (document.getElementById('set-sms')?.checked ? '1' : '0'));
  fd.append('wp_alerts',   (document.getElementById('set-wp')?.checked ? '1' : '0'));
  fd.append('dark_mode',   darkOn ? '1' : '0');
  applyDarkMode(darkOn);
  fd.append('platform_name', document.getElementById('set-platname')?.value || '');
  try { 
    await fetch('php/api.php', { method: 'POST', body: fd });
  } catch(e) {}
  if (btn) { btn.disabled = false; btn.innerHTML = btn._orig || '💾 Save & Close'; }
  showToast('✅ Settings save ho gayi!');
  closeModal('modalSettings');
}

// ── NOTIFICATIONS (proper function) ───────────────────────────
async function loadNotifications() {
  const modal = document.getElementById('modalNotif');
  const content = document.getElementById('notifContent');
  if (!modal || !content) return;
  content.innerHTML = '<div style="padding:40px;text-align:center;color:var(--muted)"><span class="spin" style="width:28px;height:28px;border-width:3px;border-color:rgba(255,107,0,.2);border-top-color:var(--saffron)"></span></div>';
  modal.style.display = 'flex';
  let notifs = [];
  try {
    const res = await fetch('php/api.php?action=get_notifs');
    const d = await res.json();
    if (d.ok) notifs = d.notifs || [];
  } catch(e) {
    notifs = [
      {id:1,is_read:0,icon:'💵',title:'Payment Received',body:'Amit Kumar ne ₹5,000 pay kiya',created_at:new Date().toISOString(),type:'success'},
      {id:2,is_read:0,icon:'⚠️',title:'Subscription Expiring',body:'Subscription 11 din mein expire hoga',created_at:new Date().toISOString(),type:'warn'},
      {id:3,is_read:1,icon:'💬',title:'Chat Message',body:'Super Admin ne reply kiya hai',created_at:new Date().toISOString(),type:'info'},
    ];
  }
  const unread = notifs.filter(n => !+n.is_read).length;
  const colors = {success:'var(--green)',warn:'var(--gold)',info:'#3498db',error:'var(--red)'};
  content.innerHTML = `
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px">
      <div>
        <div style="font-size:17px;font-weight:800">🔔 Notifications</div>
        ${unread > 0 ? `<div style="font-size:13px;color:var(--saffron);margin-top:2px">${unread} naye notifications</div>` : '<div style="font-size:13px;color:var(--muted)">Sab read ho gaye</div>'}
      </div>
      <div style="display:flex;gap:6px">
        ${unread > 0 ? `<button class="bs bsg" onclick="markAllRead()">✓ Sab Read</button>` : ''}
        <button class="bs bsg" onclick="closeModal('modalNotif')">✕</button>
      </div>
    </div>
    ${notifs.length === 0 ? '<div style="text-align:center;padding:30px;color:var(--muted)">Koi notification nahi</div>' :
    `<div style="max-height:400px;overflow-y:auto">
      ${notifs.map(n => {
        const c = colors[n.type || 'info'] || '#3498db';
        const dt = n.created_at ? new Date(n.created_at).toLocaleString('en-IN', {day:'2-digit',month:'short',hour:'2-digit',minute:'2-digit'}) : '';
        return `<div onclick="markRead(${n.id},this)" style="display:flex;gap:10px;align-items:flex-start;padding:11px;border-radius:10px;margin-bottom:7px;background:${+n.is_read?'var(--surface)':'rgba(255,107,0,.06)'};border:1px solid ${+n.is_read?'var(--border)':'rgba(255,107,0,.2)'};cursor:pointer">
          <div style="width:36px;height:36px;border-radius:50%;background:${c}22;border:2px solid ${c};display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0">${n.icon||'🔔'}</div>
          <div style="flex:1">
            <div style="font-size:13px;font-weight:${+n.is_read?600:700};display:flex;justify-content:space-between;gap:8px">
              <span>${n.title||''}</span>
              ${!+n.is_read ? '<div style="width:8px;height:8px;border-radius:50%;background:var(--saffron);flex-shrink:0;margin-top:4px"></div>' : ''}
            </div>
            <div style="font-size:13px;color:var(--muted);margin-top:3px">${n.body||''}</div>
            <div style="font-size:12px;color:var(--muted);margin-top:3px">${dt}</div>
          </div>
        </div>`;
      }).join('')}
    </div>`}`;
  document.getElementById('notifDot').style.display = 'none';
}

// ── RENEW MODAL (Shop side) ────────────────────────────────────
let selectedRenewPlan = null;
function openRenewModal() {
  let offerHTML = '';
  try {
    const offer = JSON.parse(localStorage.getItem('db_global_offer') || '{}');
    if (offer.active && new Date(offer.expiry) > new Date()) {
      offerHTML = `<div style="background:linear-gradient(135deg,rgba(255,107,0,.15),rgba(255,179,0,.1));border:2px solid rgba(255,107,0,.4);border-radius:12px;padding:12px;margin-bottom:14px;text-align:center">
        <div style="font-size:15px;font-weight:800;color:var(--saffron)">🎉 ${offer.name||'Special Offer'} — ${offer.disc}% OFF!</div>
        <div style="font-size:13px;color:var(--muted);margin-top:3px">Valid till ${new Date(offer.expiry).toLocaleDateString('en-IN')}</div>
      </div>`;
    }
  } catch(e2) {}
  const plans = [
    {id:'3m',label:'3 Months',months:3,price:300},
    {id:'6m',label:'6 Months',months:6,price:600},
    {id:'1y',label:'1 Year',months:12,price:1200,best:true},
    {id:'2y',label:'2 Years',months:24,price:2000,save:'₹400'},
  ];
  const el = document.getElementById('dynModalContent');
  if (!el) return;
  el.innerHTML = `
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px">
      <div style="font-size:17px;font-weight:800">💳 Plan Renew Karein</div>
      <button class="bs bsg" onclick="document.getElementById('dynModal').style.display='none'">✕</button>
    </div>
    ${offerHTML}
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:14px">
      ${plans.map(p => `
        <div id="rp-${p.id}" onclick="selectRenewPlan('${p.id}',${p.price})" style="border:2px solid var(--border);border-radius:12px;padding:14px;text-align:center;cursor:pointer;transition:all .2s;position:relative">
          ${p.best ? '<div style="position:absolute;top:-1px;left:50%;transform:translateX(-50%);background:var(--saffron);color:#fff;font-size:13px;font-weight:800;padding:2px 8px;border-radius:0 0 6px 6px">⭐ Best Value</div>' : ''}
          ${p.save ? `<div style="position:absolute;top:-1px;left:50%;transform:translateX(-50%);background:var(--green);color:#fff;font-size:13px;font-weight:800;padding:2px 8px;border-radius:0 0 6px 6px">💰 Save ${p.save}</div>` : ''}
          <div style="font-size:13px;font-weight:700;margin-bottom:4px">${p.label}</div>
          <div style="font-size:20px;font-weight:800;color:var(--saffron)">₹${p.price}</div>
        </div>`).join('')}
    </div>
    <div class="fg"><label class="fl">Payment Mode</label>
      <select class="si" id="renewMode" onchange="document.getElementById('renewUPI').style.display=this.value==='UPI'?'':'none'">
        <option>UPI</option><option>Cash</option><option>Bank Transfer</option>
      </select>
    </div>
    <div id="renewUPI" style="background:rgba(255,107,0,.06);border:1px solid rgba(255,107,0,.2);border-radius:10px;padding:12px;margin:10px 0;text-align:center">
      <div style="font-size:12px;color:var(--muted);font-weight:700;margin-bottom:4px">UPI ID</div>
      <div style="font-size:16px;font-weight:800;color:var(--saffron)">digitalbandhak@upi</div>
      <div style="font-size:12px;color:var(--muted);margin-top:4px">Payment ke baad screenshot admin ko bhejein</div>
    </div>
    <button class="btnP" id="renewSubmitBtn" onclick="submitRenewRequest(this)" style="margin-top:8px">✅ Renewal Request Bhejein</button>`;
  document.getElementById('dynModal').style.display = 'flex';
}
function selectRenewPlan(id, price) {
  document.querySelectorAll('[id^="rp-"]').forEach(e => { e.style.borderColor = 'var(--border)'; e.style.background = ''; });
  const el = document.getElementById('rp-' + id);
  if (el) { el.style.borderColor = 'var(--saffron)'; el.style.background = 'rgba(255,107,0,.07)'; }
  selectedRenewPlan = { id, price };
}
async function submitRenewRequest(btn) {
  if (!selectedRenewPlan) { showToast('❌ Pehle plan select karein'); return; }
  btnLoad(btn);
  const fd = new FormData();
  fd.append('action', 'renew_request');
  fd.append('plan_id', selectedRenewPlan.id);
  fd.append('amount', selectedRenewPlan.price);
  fd.append('mode', document.getElementById('renewMode')?.value || 'UPI');
  try { await fetch('php/api.php', { method: 'POST', body: fd }); } catch(e) {}
  btnReset(btn);
  document.getElementById('dynModal').style.display = 'none';
  showToast('✅ Renewal request send ho gaya! Admin approve karega.');
}

// ── UPGRADE MODAL ─────────────────────────────────────────────
function openUpgradeModal() {
  const el = document.getElementById('dynModalContent');
  if (!el) return;
  el.innerHTML = `
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px">
      <div style="font-size:17px;font-weight:800">⬆️ Plan Upgrade Karein</div>
      <button class="bs bsg" onclick="document.getElementById('dynModal').style.display='none'">✕</button>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px">
      ${[
        {name:'Standard',price:'₹1,200/year',color:'var(--saffron)',features:['50 entries/month','Basic reports','SMS alerts','Chat support']},
        {name:'Premium',price:'₹2,400/year',color:'var(--gold)',features:['Unlimited entries','Advanced reports','WhatsApp alerts','Priority support','Custom receipts']},
      ].map(p => `
        <div style="border:2px solid ${p.color};border-radius:14px;padding:16px;text-align:center">
          <div style="font-size:15px;font-weight:800;color:${p.color};margin-bottom:4px">⭐ ${p.name}</div>
          <div style="font-size:18px;font-weight:800;margin-bottom:12px">${p.price}</div>
          ${p.features.map(f => `<div style="font-size:13px;padding:3px 0;color:var(--muted)">✓ ${f}</div>`).join('')}
          <button class="btnP" style="margin-top:12px;font-size:12px;padding:9px" onclick="loadPage('chat');document.getElementById('dynModal').style.display='none';showToast('💬 Admin ko ${p.name} upgrade ke liye message karein')">Upgrade to ${p.name}</button>
        </div>`).join('')}
    </div>
    <div style="font-size:13px;color:var(--muted);text-align:center">Upgrade ke liye admin se Private Chat mein contact karein</div>`;
  document.getElementById('dynModal').style.display = 'flex';
}

// ── VIEW SHOP DETAIL (Admin) ───────────────────────────────────
function viewShopDetail(shopId) {
  const s = SHOPS.find(x => x.id === shopId) || {};
  const el = document.getElementById('dynModalContent');
  if (!el) return;
  el.innerHTML = `
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
      <div style="font-size:17px;font-weight:800">🏪 Shop Details</div>
      <button class="bs bsg" onclick="document.getElementById('dynModal').style.display='none'">✕</button>
    </div>
    <div style="background:linear-gradient(135deg,rgba(255,107,0,.1),transparent);border:1px solid rgba(255,107,0,.2);border-radius:14px;padding:16px;margin-bottom:14px">
      <div style="font-size:20px;font-weight:800;margin-bottom:4px">${s.name||shopId}</div>
      <div style="font-size:12px;color:var(--muted)">${s.id||shopId} &nbsp;•&nbsp; ${s.city||s.state||'—'}</div>
    </div>
    ${[
      ['👤 Owner', s.owner_name||s.owner||'—'],
      ['📧 Email', s.email||'—'],
      ['📱 Mobile', s.mobile||'—'],
      ['📍 Address', s.address||'—'],
      ['📌 Pincode', s.pincode||'—'],
      ['📋 GST', s.gst||'—'],
      ['🪪 Licence', s.licence||'—'],
      ['💳 Plan', s.subscription||s.sub||'—'],
      ['📅 Expiry', s.sub_expiry||s.expiry||'—'],
      ['📊 Status', s.status||'—'],
      ['💰 Balance Paid', s.balance ? fmt(s.balance) : (s.balance_raw || '₹0')],
    ].map(([k,v]) => `
      <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--border);font-size:13px">
        <span style="color:var(--muted);font-weight:600">${k}</span>
        <b style="color:var(--text);text-align:right;max-width:60%">${v}</b>
      </div>`).join('')}
    <div class="brow" style="margin-top:14px;flex-wrap:wrap;gap:6px">
      <button class="bs bsp" onclick="openExtend('${s.id||shopId}','${s.name||shopId}','${s.subscription||s.sub||'Trial'}');document.getElementById('dynModal').style.display='none'">🔄 Extend</button>
      <button class="bs bsv" onclick="giveFreeTrial('${s.id||shopId}');document.getElementById('dynModal').style.display='none'">🆓 Free Trial</button>
      <button class="bs" style="background:rgba(52,152,219,.12);color:#5dade2;border:1px solid rgba(52,152,219,.3)" onclick="adminViewShopBandhak('${s.id||shopId}','${s.name||shopId}')">📦 Bandhak Dekho</button>
    </div>`;
  document.getElementById('dynModal').style.display = 'flex';
}

// ── EXTEND AUTO AMOUNT ────────────────────────────────────────
let pendingExtendPlan = 'Standard';
const DUR_MONTHS_MAP = {'1 Month':1,'3 Months':3,'6 Months':6,'1 Year':12,'2 Years':24};
function calcExtendAmount() {
  const dur = document.getElementById('extDur')?.value || '1 Month';
  const months = DUR_MONTHS_MAP[dur] || 1;
  const rate = pendingExtendPlan === 'Premium' ? 200 : 100;
  let discount = 0;
  try {
    const offer = JSON.parse(localStorage.getItem('db_global_offer') || '{}');
    if (offer.active) discount = offer.disc || 0;
  } catch(e) {}
  const base = rate * months;
  const final = Math.round(base * (1 - discount / 100));
  const el = document.getElementById('extAmt');
  if (el) { el.value = final; el.placeholder = discount ? `₹${base} - ${discount}% = ₹${final}` : `₹${final}`; }
  const di = document.getElementById('extDiscInfo');
  if (di) di.innerHTML = discount ? `<span style="color:var(--green);font-size:13px">🎉 ${discount}% offer! ₹${base} → ₹${final}</span>` : '';
}

// ── EDIT SUBSCRIPTION PLAN (Admin) ────────────────────────────
function editSubPlan(shopId, shopName, currentPlan, currentExpiry) {
  const el = document.getElementById('dynModalContent');
  if (!el) return;
  el.innerHTML = `
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px">
      <div style="font-size:17px;font-weight:800">✏️ Edit Plan — ${shopName}</div>
      <button class="bs bsg" onclick="document.getElementById('dynModal').style.display='none'">✕</button>
    </div>
    <div class="fg"><label class="fl">Plan</label>
      <select class="si" id="ePlanSel">${['Trial','Standard','Premium'].map(p=>`<option ${p===currentPlan?'selected':''}>${p}</option>`).join('')}</select>
    </div>
    <div class="fg"><label class="fl">Expiry Date</label>
      <input class="fi" type="date" id="ePlanExp" value="${currentExpiry||''}">
    </div>
    <div class="fg"><label class="fl">Amount (₹)</label>
      <input class="fi" type="number" id="ePlanAmt" placeholder="e.g. 1200">
    </div>
    <div class="fg"><label class="fl">Mode</label>
      <select class="si" id="ePlanMode"><option>Cash</option><option>UPI</option><option>Bank Transfer</option><option>Free</option></select>
    </div>
    <div class="fg"><label class="fl">Note</label>
      <input class="fi" id="ePlanNote" placeholder="Optional remark...">
    </div>
    <button class="btnP" onclick="saveEditPlan('${shopId}',this)" style="margin-top:8px">✅ Save Changes</button>`;
  document.getElementById('dynModal').style.display = 'flex';
}
async function saveEditPlan(shopId, btn) {
  btnLoad(btn);
  const fd = new FormData();
  fd.append('action','extend_sub');
  fd.append('shop_id', shopId);
  fd.append('plan', document.getElementById('ePlanSel')?.value || 'Standard');
  fd.append('expiry', document.getElementById('ePlanExp')?.value || '');
  fd.append('amount', document.getElementById('ePlanAmt')?.value || 0);
  fd.append('mode', document.getElementById('ePlanMode')?.value || 'Cash');
  fd.append('note', document.getElementById('ePlanNote')?.value || '');
  fd.append('duration', '1 Month');
  try { const res = await fetch('php/api.php',{method:'POST',body:fd}); const d=await res.json(); if(d.ok) showToast('✅ Plan updated!'); else showToast('✅ Plan updated!'); } catch(e) { showToast('✅ Plan updated (demo)!'); }
  btnReset(btn);
  document.getElementById('dynModal').style.display = 'none';
}

// ── GENERATE PAWN PDF ─────────────────────────────────────────
function generatePawnPDF(pawn) {
  const jsPDFLib = window.jspdf;
  if (!jsPDFLib) {
    showToast('⏳ PDF library load ho rahi hai...');
    const s = document.createElement('script');
    s.src = 'https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js';
    s.onload = () => generatePawnPDF(pawn);
    document.head.appendChild(s);
    return;
  }
  const { jsPDF } = jsPDFLib;
  const doc = new jsPDF({ orientation:'portrait', unit:'mm', format:'a5' });
  const loan = +pawn.loan_amount||+pawn.loan||0;
  const paid = +pawn.total_paid||+pawn.paid||0;
  const rem  = +pawn.total_remaining||+pawn.remaining||0;
  const days = Math.floor((new Date()-new Date(pawn.loan_date||pawn.date||''))/(1000*86400));
  const rate = +pawn.interest_rate||+pawn.interest||2;
  const intAcc = Math.round(loan*(rate/100)*(days/30));
  const payments = pawn.payments||[];
  const fmtR = v => '₹'+Number(v).toLocaleString('en-IN');
  // Header
  doc.setFillColor(255,107,0); doc.rect(0,0,148,26,'F');
  doc.setTextColor(255,255,255); doc.setFontSize(15); doc.setFont('helvetica','bold');
  doc.text(SHOP_NAME||'Digital Bandhak', 10, 11);
  doc.setFontSize(8); doc.setFont('helvetica','normal');
  doc.text('Payment Receipt', 10, 19); doc.text('Date: '+new Date().toLocaleDateString('en-IN'), 100, 19);
  // Bandhak ID
  doc.setFillColor(30,15,5); doc.rect(0,26,148,10,'F');
  doc.setTextColor(255,179,0); doc.setFontSize(11); doc.setFont('helvetica','bold');
  doc.text('Bandhak ID: '+(pawn.id||''), 10, 33);
  doc.setTextColor(160,130,100); doc.setFontSize(8);
  doc.text('Status: '+(pawn.status||'active').toUpperCase(), 110, 33);
  // Details
  doc.setTextColor(30,15,0);
  let y = 44;
  const rows = [
    ['Customer', pawn.customer_name||pawn.customer||'—'],
    ['Mobile', (pawn.customer_mobile||pawn.mobile||'—').replace(/\d(?=\d{4})/g,'X')],
    ['Aadhaar', (pawn.customer_aadhaar||pawn.aadhaar||'—')],
    ['Address', (pawn.customer_address||pawn.address||'—').substring(0,50)],
    ['Item', (pawn.item_description||pawn.item||'—')+(pawn.item_weight?' ('+pawn.item_weight+')':'')],
    ['Loan Date', pawn.loan_date||pawn.date||'—'],
    ['Return Date', pawn.return_date||'—'],
    ['Interest', rate+'% / month'],
    ['Days Elapsed', days+' din'],
    ['Interest Accrued', fmtR(intAcc)],
  ];
  rows.forEach(([k,v]) => {
    doc.setFontSize(8); doc.setFont('helvetica','bold'); doc.setTextColor(100,70,40); doc.text(k+':', 10, y);
    doc.setFont('helvetica','normal'); doc.setTextColor(20,10,0); doc.text(String(v||''), 50, y);
    y += 5;
  });
  // Amount boxes
  y += 3;
  [[fmtR(loan),'Loan Amount','255,107,0'],[fmtR(paid),'Total Paid','46,204,113'],[fmtR(rem),t('total_rem','Remaining'),'231,76,60']].forEach(([v,l,rgb],i) => {
    const [r,g,b] = rgb.split(',').map(Number);
    const x = 10+i*46;
    doc.setFillColor(r,g,b); doc.setAlpha ? doc.setAlpha(0.1) : null;
    doc.setDrawColor(r,g,b);
    doc.roundedRect(x, y, 42, 14, 2, 2, 'D');
    doc.setFontSize(11); doc.setFont('helvetica','bold'); doc.setTextColor(r,g,b);
    doc.text(v, x+21, y+7, {align:'center'});
    doc.setFontSize(7); doc.setFont('helvetica','normal'); doc.setTextColor(100,80,60);
    doc.text(l, x+21, y+12, {align:'center'});
  });
  y += 20;
  // Payments
  doc.setFontSize(9); doc.setFont('helvetica','bold'); doc.setTextColor(30,15,0);
  doc.text('PAYMENT TIMELINE — '+payments.length+' transactions', 10, y); y += 6;
  if (payments.length === 0) {
    doc.setFont('helvetica','normal'); doc.setTextColor(120,100,80); doc.text('Koi payment abhi tak nahi aayi', 10, y); y += 6;
  } else {
    payments.forEach((p,i) => {
      doc.setDrawColor(46,204,113);
      doc.roundedRect(10, y, 128, 9, 1, 1, 'D');
      doc.setFontSize(9); doc.setFont('helvetica','bold'); doc.setTextColor(46,150,100);
      doc.text(fmtR(+p.amount||0), 14, y+6);
      doc.setFont('helvetica','normal'); doc.setTextColor(70,50,30);
      doc.text('Mode: '+(p.payment_mode||p.mode||'Cash')+(p.note?' • '+p.note.substring(0,20):''), 50, y+6);
      doc.setTextColor(100,80,60); doc.text(p.payment_date||p.date||'', 110, y+6);
      y += 11;
    });
  }
  // Footer
  doc.setFillColor(255,107,0); doc.rect(0,193,148,5,'F');
  doc.setTextColor(255,255,255); doc.setFontSize(7);
  doc.text('Digital Bandhak Platform | Aapki Girvee, Hamaari Zimmedaari', 74, 196, {align:'center'});
  doc.save((pawn.id||'Bandhak')+'_Receipt.pdf');
  showToast('✅ PDF download ho raha hai!');
}

// ── GLOBAL OFFER (better version with localStorage) ─────────────
function activateGlobalOffer() {
  const name   = document.getElementById('gOfferName')?.value?.trim();
  const disc   = +(document.getElementById('gOfferDisc')?.value || 0);
  const expiry = document.getElementById('gOfferExpiry')?.value;
  const target = document.getElementById('gOfferTarget')?.value || 'all';
  if (!name || !disc) { showToast('❌ Offer name aur discount bharo'); return; }
  const offer = { active:true, name, disc, expiry, target, ts:Date.now() };
  localStorage.setItem('db_global_offer', JSON.stringify(offer));
  const fd = new FormData();
  fd.append('action','set_global_offer');
  fd.append('offer_data', JSON.stringify(offer));
  fetch('php/api.php',{method:'POST',body:fd}).catch(()=>{});
  document.getElementById('offerActiveBadge').style.display = '';
  showToast('✅ Offer "'+name+'" active — '+disc+'% discount!');
}
function deactivateOffer() {
  localStorage.removeItem('db_global_offer');
  const fd = new FormData();
  fd.append('action','set_global_offer');
  fd.append('offer_data', JSON.stringify({active:false}));
  fetch('php/api.php',{method:'POST',body:fd}).catch(()=>{});
  document.getElementById('offerActiveBadge').style.display = 'none';
  if(document.getElementById('gOfferName')) document.getElementById('gOfferName').value = '';
  if(document.getElementById('gOfferDisc')) document.getElementById('gOfferDisc').value = '';
  showToast('❌ Offer hataya gaya');
}
function previewGlobalOffer() {
  const disc = +(document.getElementById('gOfferDisc')?.value || 0);
  if (!disc) return;
  const el = document.getElementById('gOfferPreview');
  if (el) el.innerHTML = `Standard: <del>₹1,200</del> → <b style="color:var(--green)">₹${Math.round(1200*(1-disc/100))}</b> &nbsp; Premium: <del>₹2,400</del> → <b style="color:var(--green)">₹${Math.round(2400*(1-disc/100))}</b>`;
}

// ── ASYNC SHOP SUBSCRIPTION PAGE (real data + offer) ──────────
async function renderShopSubReal(el) {
  el.innerHTML = '<div style="text-align:center;padding:60px;color:var(--muted)"><span class="spin" style="width:30px;height:30px;border-width:3px;border-color:rgba(255,107,0,.2);border-top-color:var(--saffron)"></span></div>';
  let subData = null; let payments = [];
  try {
    const res = await fetch('php/api.php?action=get_shop_sub');
    const d = await res.json();
    if (d.ok) { subData = d.sub; payments = d.payments || []; }
  } catch(e) {}
  // Check global offer
  let offerBanner = '';
  try {
    const offer = JSON.parse(localStorage.getItem('db_global_offer') || '{}');
    if (offer.active && new Date(offer.expiry) > new Date()) {
      offerBanner = `<div style="background:linear-gradient(135deg,rgba(255,107,0,.15),rgba(255,179,0,.1));border:2px solid rgba(255,107,0,.4);border-radius:14px;padding:14px;margin-bottom:16px;text-align:center"><div style="font-size:17px;font-weight:800;color:var(--saffron)">🎉 ${offer.name||'Special Offer'} — ${offer.disc}% OFF!</div><div style="font-size:12px;color:var(--muted);margin-top:4px">Valid till ${new Date(offer.expiry).toLocaleDateString('en-IN')} &nbsp;|&nbsp; Abhi renew karein!</div></div>`;
    }
  } catch(e2) {}
  const plan = subData?.plan || 'Standard';
  const expiry = subData?.expiry || '2025-12-31';
  const exDate = new Date(expiry);
  const today = new Date();
  const daysLeft = Math.ceil((exDate - today) / 86400000);
  const pct = Math.max(0, Math.min(100, Math.round(daysLeft / 365 * 100)));
  const isExp = daysLeft <= 0;
  const isSoon = daysLeft > 0 && daysLeft <= 30;
  el.innerHTML = `<div class="pb">
    ${offerBanner}
    <div class="subc">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
        <div><div style="font-size:15px;font-weight:800">💳 ${plan} Plan</div>
          <div style="font-size:12px;color:var(--muted);margin-top:2px">${isExp?'Expired on':isSoon?'⚠️ Expiring on':'Active till'} ${exDate.toLocaleDateString('en-IN')}</div></div>
        <span class="b ${isExp?'br':isSoon?'by':'bg'}">${isExp?'❌ Expired':isSoon?'⚠️ Soon':'✅ Active'}</span>
      </div>
      <div class="spb"><div class="spf" style="width:${isExp?100:pct}%;background:${isExp?'var(--red)':isSoon?'var(--gold)':'linear-gradient(to right,var(--saffron),var(--gold))'}"></div></div>
      <div style="display:flex;justify-content:space-between;font-size:13px;color:var(--muted);margin-top:4px">
        <span>Today</span>
        <span style="color:${isExp?'var(--red)':isSoon?'var(--gold)':'var(--green)'}">${isExp?'Expired':Math.abs(daysLeft)+' din bache'}</span>
        <span>${exDate.toLocaleDateString('en-IN')}</span>
      </div>
      <div class="brow" style="margin-top:12px">
        <button class="bs bsp" onclick="openRenewModal()">🔄 Renew</button>
        <button class="bs bsg" onclick="openUpgradeModal()">⬆️ Upgrade Plan</button>
      </div>
    </div>
    <div class="card" style="margin-top:14px"><div class="ch"><div class="ct">📋 Payment History</div></div>
      <div style="overflow-x:auto">${payments.length===0?'<div style="padding:20px;text-align:center;color:var(--muted)">Koi payment record nahi</div>':
      `<table class="dt"><thead><tr><th>Date</th><th>Plan</th><th>Duration</th><th>Amount</th><th>Mode</th></tr></thead><tbody>
      ${payments.map(p=>`<tr><td>${p.payment_date||p.date||'—'}</td><td>${p.plan||plan}</td><td>${p.duration||'—'}</td><td style="color:var(--green)">₹${Number(p.amount||0).toLocaleString('en-IN')}</td><td>${p.payment_mode||p.mode||'Cash'}</td></tr>`).join('')}
      </tbody></table>`}</div>
    </div>
  </div>`;
}

