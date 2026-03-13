// ─── SPINNER CSS INJECTION ────────────────────────────────────
(function(){
  const s = document.createElement('style');
  s.textContent = '@keyframes spin360{to{transform:rotate(360deg)}}.login-spin{display:inline-block;width:16px;height:16px;border:2px solid rgba(255,255,255,.3);border-top-color:#fff;border-radius:50%;animation:spin360 .7s linear infinite;vertical-align:middle}';
  document.head.appendChild(s);
})();

// ─── LOGIN PAGE JS ─────────────────────────────────────────────────────────

function switchTab(tab) {
  // New unified login - just show the main login form
  const unified = document.getElementById('panel-unified');
  const custPanel = document.getElementById('panel-customer');
  if (unified) unified.style.display = '';
  if (custPanel) custPanel.style.display = 'none';
  // Legacy tab support (ignored if elements don't exist)
  ['admin','shop','customer'].forEach(t => {
    const p = document.getElementById('panel-'+t);
    const tb = document.getElementById('tab-'+t);
    if (p) p.style.display = t===tab ? '' : 'none';
    if (tb) tb.classList.toggle('active', t===tab);
  });
}

function togglePwd(id) {
  const el = document.getElementById(id);
  el.type = el.type === 'password' ? 'text' : 'password';
}

function otpMove(el, nextId) {
  if (el.value && nextId) document.getElementById(nextId)?.focus();
}

function showErr(id, msg) {
  const el = document.getElementById(id);
  if (el) { el.textContent = msg; el.style.display = 'block'; }
}
function hideErr(id) {
  const el = document.getElementById(id);
  if (el) el.style.display = 'none';
}

// ── ADMIN LOGIN ──────────────────────────────────────────────
async function loginAdmin() {
  hideErr('admin-err');
  const _adminBtn = document.querySelector('#panel-admin .btnP');
  if (_adminBtn) { _adminBtn._orig=_adminBtn.innerHTML; _adminBtn.disabled=true; _adminBtn.innerHTML='<span class="login-spin"></span>'; }
  const _restoreAdmin = () => { if(_adminBtn){_adminBtn.disabled=false;_adminBtn.innerHTML=_adminBtn._orig||'Super Admin Login';} };
  const email = document.getElementById('admin-email').value.trim();
  const pass  = document.getElementById('admin-pass').value.trim();
  if (!email || !pass) { showErr('admin-err','Email aur password daalein'); return; }
  
  const fd = new FormData();
  fd.append('action','login_admin');
  fd.append('email', email);
  fd.append('password', pass);
  
  try {
    const res = await fetch('php/auth.php', {method:'POST', body:fd});
    const d = await res.json();
    if (d.ok) location.reload();
    else { _restoreAdmin(); showErr('admin-err', d.msg || 'Login failed'); }
  } catch(e) { _restoreAdmin(); showErr('admin-err','Server error'); }
}

// ── SHOP LOGIN ───────────────────────────────────────────────
async function loginShop() {
  hideErr('shop-err');
  const _shopBtn = document.querySelector('#panel-shop .btnP');
  if (_shopBtn) { _shopBtn._orig=_shopBtn.innerHTML; _shopBtn.disabled=true; _shopBtn.innerHTML='<span class="login-spin"></span>'; }
  const _restoreShop = () => { if(_shopBtn){_shopBtn.disabled=false;_shopBtn.innerHTML=_shopBtn._orig||'Shop Login';} };
  const sid  = document.getElementById('shop-id').value.trim();
  const pass = document.getElementById('shop-pass').value.trim();
  if (!sid || !pass) { showErr('shop-err','Shop ID aur password daalein'); return; }
  
  const fd = new FormData();
  fd.append('action','login_shop');
  fd.append('shop_id', sid);
  fd.append('password', pass);
  
  try {
    const res = await fetch('php/auth.php', {method:'POST', body:fd});
    const d = await res.json();
    if (d.ok) location.reload();
    else { _restoreShop(); showErr('shop-err', d.msg || 'Login failed'); }
  } catch(e) { _restoreShop(); showErr('shop-err','Server error'); }
}

// ── OTP SEND ─────────────────────────────────────────────────
async function sendOTP() {
  hideErr('cust-err');
  const mobile = document.getElementById('cust-mobile').value.trim();
  const bid    = document.getElementById('cust-bandhak').value.trim();
  if (!mobile || !bid) { showErr('cust-err','Bandhak ID aur mobile daalein'); return; }
  
  const fd = new FormData();
  fd.append('action','send_otp');
  fd.append('mobile', mobile);
  fd.append('bandhak_id', bid);
  
  try {
    const res = await fetch('php/auth.php', {method:'POST', body:fd});
    const d = await res.json();
    if (d.ok) {
      document.getElementById('otp-panel').style.display = '';
      document.getElementById('otpBtn').textContent = 'Resend';
      if (d.demo_otp) {
        // Show OTP hint for testing (production mein SMS se aayega)
        const hint = document.getElementById('otp-hint');
        if (hint) {
          hint.style.cssText = 'font-size:11px;color:rgba(255,107,0,0.6);text-align:center;margin-top:6px';
          hint.textContent = '📱 OTP sent! (Test: ' + d.demo_otp + ')';
        }
      }
      document.getElementById('o1').focus();
    }
  } catch(e) {}
}

// ── OTP VERIFY ───────────────────────────────────────────────
async function verifyOTP() {
  hideErr('cust-err');
  const _otpBtn = document.querySelector('#otp-panel .btnP');
  if (_otpBtn) { _otpBtn._orig=_otpBtn.innerHTML; _otpBtn.disabled=true; _otpBtn.innerHTML='<span class="login-spin"></span> Verifying...'; }
  const _restoreOTP = () => { if(_otpBtn){_otpBtn.disabled=false;_otpBtn.innerHTML=_otpBtn._orig||'Verify & Login';} };
  const otp = ['o1','o2','o3','o4','o5','o6'].map(id => document.getElementById(id).value).join('');
  if (otp.length < 6) { showErr('cust-err','6-digit OTP daalein'); return; }
  
  const fd = new FormData();
  fd.append('action','verify_otp');
  fd.append('otp', otp);
  
  try {
    const res = await fetch('php/auth.php', {method:'POST', body:fd});
    const d = await res.json();
    if (d.ok) location.reload();
    else { _restoreOTP(); showErr('cust-err', d.msg || 'OTP galat hai'); }
  } catch(e) { _restoreOTP(); }
}

// ── REGISTER ─────────────────────────────────────────────────
function showRegister() {
  document.getElementById('loginCard').style.display = 'none';
  document.getElementById('registerCard').style.display = '';
  document.getElementById('forgotCard').style.display = 'none';
}
function hideRegister() {
  document.getElementById('loginCard').style.display = '';
  document.getElementById('registerCard').style.display = 'none';
}

let tcAgreeCallback = null;
function showRegTC() {
  renderTC();
  const m = document.getElementById('modalTC');
  if (!m) return;
  m.style.display = 'flex';
  tcAgreeCallback = () => {
    document.getElementById('reg-tc').checked = true;
    document.getElementById('regBtn').disabled = false;
    document.getElementById('regBtn').style.opacity = '1';
  };
}
function tcAgree() {
  if (tcAgreeCallback) tcAgreeCallback();
  closeModal('modalTC');
}

function showRegSuccessModal(shopId, msg) {
  // Create overlay
  const ov = document.createElement('div');
  ov.id = 'regSuccessOv';
  ov.style.cssText = 'position:fixed;inset:0;z-index:99999;background:rgba(0,0,0,.7);display:flex;align-items:center;justify-content:center;padding:20px;box-sizing:border-box';
  ov.innerHTML = `
    <div style="background:var(--card,#1e1a15);border:2px solid rgba(255,107,0,.4);border-radius:20px;padding:28px 22px;max-width:360px;width:100%;text-align:center;box-shadow:0 20px 60px rgba(0,0,0,.5),0 0 0 1px rgba(255,107,0,.15);animation:popIn .3s ease">
      <div style="font-size:52px;margin-bottom:8px">🎉</div>
      <div style="font-size:20px;font-weight:800;color:#f0a500;margin-bottom:4px">Registration Successful!</div>
      <div style="font-size:13px;color:rgba(255,255,255,.6);margin-bottom:20px">Digital Bandhak mein swagat hai</div>
      
      <div style="background:rgba(255,107,0,.1);border:2px dashed rgba(255,107,0,.4);border-radius:14px;padding:18px;margin-bottom:18px">
        <div style="font-size:11px;color:rgba(255,255,255,.5);text-transform:uppercase;letter-spacing:1px;margin-bottom:6px">Aapka Shop ID</div>
        <div style="font-size:32px;font-weight:900;color:#ff6b00;letter-spacing:3px">${shopId}</div>
        <div style="font-size:11px;color:rgba(255,255,255,.4);margin-top:4px">Yeh ID save kar lein</div>
      </div>
      
      <div style="background:rgba(255,179,0,.07);border:1px solid rgba(255,179,0,.2);border-radius:10px;padding:12px;margin-bottom:20px;font-size:12px;color:rgba(255,255,255,.7);line-height:1.6;text-align:left">
        ⏳ <b>24 ghante mein</b> aapka account activate ho jaega<br>
        📱 Admin se contact: <b style="color:#f0a500">6206869543</b><br>
        🔐 Login ke liye yeh Shop ID use karein
      </div>
      
      <button onclick="document.getElementById('regSuccessOv').remove();const uid=document.getElementById('uni-id');if(uid){uid.value='${shopId}';uid.focus();}" 
        style="width:100%;background:linear-gradient(135deg,#ff6b00,#f0a500);color:#fff;border:none;border-radius:12px;padding:14px;font-size:15px;font-weight:800;cursor:pointer;letter-spacing:.5px">
        ✅ Login Karein
      </button>
    </div>`;
  document.body.appendChild(ov);
  // Close on outside click
  ov.addEventListener('click', e => { if(e.target===ov) ov.remove(); });
}

async function doRegister() {
  hideErr('reg-err');
  const fields = {
    shop_name: 'r-shop', owner_name: 'r-owner', mobile: 'r-mobile',
    email: 'r-email', pincode: 'r-pin', address: 'r-address',
    password: 'r-pass', conf_pass: 'r-conf'
  };
  const fd = new FormData();
  fd.append('action','register_shop');
  for (const [k, id] of Object.entries(fields)) {
    fd.append(k, document.getElementById(id).value.trim());
  }
  fd.append('state', document.getElementById('r-state').value);
  fd.append('gst', document.getElementById('r-gst').value.trim());
  fd.append('licence', document.getElementById('r-lic').value.trim());
  
  try {
    const res = await fetch('php/auth.php', {method:'POST', body:fd});
    const d = await res.json();
    if (d.ok) {
      hideRegister();
      showRegSuccessModal(d.shop_id || '', d.msg || '');
    } else {
      showErr('reg-err', d.msg || 'Registration failed');
    }
  } catch(e) { showErr('reg-err','Server error'); }
}

// ── FORGOT PASSWORD ──────────────────────────────────────────
function showForgot() {
  document.getElementById('loginCard').style.display = 'none';
  document.getElementById('forgotCard').style.display = '';
}
function hideForgot() {
  document.getElementById('loginCard').style.display = '';
  document.getElementById('forgotCard').style.display = 'none';
  ['fp-step1','fp-step2','fp-step3'].forEach((id,i) => {
    document.getElementById(id).style.display = i===0 ? '' : 'none';
  });
}

async function fpStep1() {
  const mobile = document.getElementById('fp-mobile').value.trim();
  const shop   = document.getElementById('fp-shop').value.trim();
  if (!mobile || !shop) { showErr('fp-err','Mobile aur Shop ID daalein'); return; }
  
  const fd = new FormData();
  fd.append('action','forgot_send_otp');
  fd.append('mobile', mobile);
  fd.append('shop_id', shop);
  
  try {
    const res = await fetch('php/auth.php', {method:'POST', body:fd});
    const d = await res.json();
    if (d.ok) {
      document.getElementById('fp-step1').style.display = 'none';
      document.getElementById('fp-step2').style.display = '';
      if (d.demo_otp) {
        const h = document.getElementById('fp-hint');
        if (h) { h.style.cssText='font-size:11px;color:rgba(255,107,0,0.6);text-align:center'; h.textContent='📱 OTP sent! (Test: '+d.demo_otp+')'; }
      }
    }
  } catch(e) {}
}

async function fpStep2() {
  const otp = ['fp1','fp2','fp3','fp4','fp5','fp6'].map(id => document.getElementById(id).value).join('');
  if (otp.length < 6) { showErr('fp-err','6-digit OTP daalein'); return; }
  
  const fd = new FormData();
  fd.append('action','forgot_verify_otp');
  fd.append('otp', otp);
  
  try {
    const res = await fetch('php/auth.php', {method:'POST', body:fd});
    const d = await res.json();
    if (d.ok) {
      document.getElementById('fp-step2').style.display = 'none';
      document.getElementById('fp-step3').style.display = '';
    } else { showErr('fp-err', d.msg || 'OTP galat hai'); }
  } catch(e) {}
}

async function fpStep3() {
  const np = document.getElementById('fp-new').value;
  const cp = document.getElementById('fp-conf').value;
  if (np !== cp) { showErr('fp-err','Passwords match nahi kar rahe'); return; }
  
  const fd = new FormData();
  fd.append('action','reset_password');
  fd.append('new_pass', np);
  fd.append('conf_pass', cp);
  
  try {
    const res = await fetch('php/auth.php', {method:'POST', body:fd});
    const d = await res.json();
    if (d.ok) { alert('✅ Password update ho gaya! Ab login karein.'); hideForgot(); }
    else showErr('fp-err', d.msg);
  } catch(e) {}
}

// Enter key support
document.addEventListener('keydown', e => {
  if (e.key === 'Enter') {
    const panel = document.querySelector('.ltab.active')?.id?.replace('tab-','');
    if (panel === 'admin') loginAdmin();
    else if (panel === 'shop') loginShop();
  }
});

// ── TERMS & CONDITIONS CONTENT ────────────────────────────────
function renderTC() {
  const items = [
    {n:'1',ic:'🏪',title:'Platform Ka Use',body:'Digital Bandhak ek software tool hai jo aapki bandhak dukaan ke records manage karne mein madad karta hai. Yeh ek digital register ki tarah kaam karta hai.\n\nSirf registered aur verified shop owners hi platform use kar sakte hain.'},
    {n:'2',ic:'🔐',title:'Data Privacy aur Security',body:'Customer ka naam, mobile, aur Aadhaar data sirf aapki shop ke records mein rahega. Kisi bhi third party ke saath share nahi kiya jayega.\n\nAadhaar number platform mein hamesha masked (XXXX-XXXX-XXXX) store hoga.'},
    {n:'3',ic:'⚠️',title:'Super Admin ki Zimmedari — IMPORTANT',body:'Super Admin sirf ek platform manager hai. Super Admin aapki dukaan ke kaam-kaaj mein seedha zimmedar NAHI hai.\n\n📓 HAMARI STRONG SALAH: Sirf digital par mat raho! Har bandhak entry notebook mein bhi zaroor likhein.'},
    {n:'4',ic:'💳',title:'Subscription aur Payment',body:'Subscription fees ek baar pay karne ke baad refund nahi hogi.\n\nFree Trial 7 din ka hai.\n\nPlans:\n🆓 Free Trial — 7 din (₹0)\n📋 Standard — ₹1,200/year\n⭐ Premium — ₹2,400/year'},
    {n:'5',ic:'⚖️',title:'Kanoon aur Legal Zimmedari',body:'Bandhak ka kaam aapke state ke local laws ke hisaab se regulated ho sakta hai.\n\nPlatform koi legal advice nahi deta. Aap apne vyapar ke liye khud zimmedar hain.'},
    {n:'6',ic:'📵',title:'Account Suspend ya Band Karna',body:'Agar koi shop owner platform ka galat use karta hai ya fake data daalta hai to Super Admin account suspend kar sakta hai.'},
    {n:'7',ic:'🔄',title:'Platform Updates',body:'Platform samay-samay par update hota rahega. Koi bhi bada change aane par shop owners ko inform kiya jayega.'},
    {n:'8',ic:'📓',title:'Notebook Backup — #1 Salah',body:'Digital Bandhak strongly recommend karta hai ki:\n✓ Har naye bandhak ki entry notebook mein bhi likhein\n✓ Payment lene par notebook mein note zaroor karein\n✓ Customer ka contact number notebook mein save karein\n✓ Monthly ek baar apne records check karein'},
  ];
  const container = document.getElementById('tcContent');
  if (!container) return;
  container.innerHTML = items.map(s => `
    <div style="margin-bottom:18px">
      <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px">
        <div style="width:26px;height:26px;border-radius:50%;background:rgba(255,107,0,.15);border:1px solid rgba(255,107,0,.3);display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:800;color:var(--saffron);flex-shrink:0">${s.n}</div>
        <div style="font-size:14px;font-weight:800">${s.ic} ${s.title}</div>
      </div>
      <div style="padding-left:34px;color:var(--muted);white-space:pre-line;font-size:12px;line-height:1.8">${s.body}</div>
    </div>
  `).join('');
}

// ── MODAL HELPERS ─────────────────────────────────────────────
function openModal(id) {
  document.getElementById(id).style.display = 'flex';
}
function closeModal(id) {
  document.getElementById(id).style.display = 'none';
}
document.addEventListener('keydown', e => {
  if (e.key === 'Escape') {
    document.querySelectorAll('.mo').forEach(m => m.style.display = 'none');
  }
});

// ── UNIFIED LOGIN (Admin + Shop in one form) ─────────────────
function showBlockedScreen(msg) {
  // Replace login card content with blocked message
  const card = document.getElementById('loginCard');
  if (!card) return;
  card.innerHTML = `
    <div style="text-align:center;padding:16px 8px">
      <div style="font-size:52px;margin-bottom:12px">🚫</div>
      <div style="font-size:18px;font-weight:800;color:#e74c3c;margin-bottom:8px">Account Block Hai</div>
      <div style="font-size:13px;color:var(--muted);margin-bottom:20px;line-height:1.6">${msg||'Aapka account admin ne block kiya hai.'}<br><br>Help ke liye admin se contact karein.</div>
      <a href="https://wa.me/916206869543?text=Mera+account+block+hai.+Shop+ID:+${document.getElementById('uni-id')?.value||''}" target="_blank" style="display:flex;align-items:center;justify-content:center;gap:8px;background:rgba(37,211,102,.12);border:1px solid rgba(37,211,102,.3);color:#2ecc71;border-radius:12px;padding:13px;font-weight:700;font-size:14px;text-decoration:none;margin-bottom:10px">
        💬 WhatsApp par Contact Karein
      </a>
      <button class="btnG" style="width:100%" onclick="location.reload()">← Wapas Login</button>
    </div>`;
}

async function doUnifiedLogin() {
  const idVal  = document.getElementById('uni-id')?.value.trim();
  const pass   = document.getElementById('uni-pass')?.value.trim();
  const errEl  = document.getElementById('uni-err');
  const btn    = document.getElementById('uniLoginBtn');
  
  if (errEl) errEl.style.display = 'none';
  if (!idVal || !pass) { 
    if (errEl) { errEl.textContent = '❌ ID/Email aur password daalein'; errEl.style.display = 'block'; }
    return; 
  }
  
  if (btn) { btn._orig = btn.innerHTML; btn.disabled = true; btn.innerHTML = '<span class="login-spin"></span> Logging in...'; }
  const restore = () => { if (btn) { btn.disabled = false; btn.innerHTML = btn._orig || '🔓 Login'; } };
  
  // Detect: email → admin, SHxxx → shop, number → try shop by mobile
  const isEmail  = idVal.includes('@');
  const isShopId = /^SH\d+$/i.test(idVal);
  const isMobile = /^\d{10}$/.test(idVal);
  
  const fd = new FormData();
  
  if (isEmail || (!isShopId && !isMobile)) {
    // Try admin first
    fd.append('action', 'login_admin');
    fd.append('email', idVal);
    fd.append('password', pass);
    try {
      const res = await fetch('php/auth.php', {method:'POST', body:fd});
      const d = await res.json();
      if (d.ok) { location.reload(); return; }
      // If admin fail, try shop by email
      const fd2 = new FormData();
      fd2.append('action', 'login_shop_by_email');
      fd2.append('email', idVal);
      fd2.append('password', pass);
      const res2 = await fetch('php/auth.php', {method:'POST', body:fd2});
      const d2 = await res2.json();
      if (d2.ok) { location.reload(); return; }
      restore();
      if (d2.blocked) { showBlockedScreen(d2.msg); return; }
      if (errEl) { errEl.textContent = '❌ Email ya password galat hai'; errEl.style.display = 'block'; }
    } catch(e) { restore(); if (errEl) { errEl.textContent = '❌ Server error'; errEl.style.display = 'block'; } }
    
  } else if (isShopId) {
    // Shop ID login
    fd.append('action', 'login_shop');
    fd.append('shop_id', idVal.toUpperCase());
    fd.append('password', pass);
    try {
      const res = await fetch('php/auth.php', {method:'POST', body:fd});
      const d = await res.json();
      if (d.ok) { location.reload(); return; }
      restore();
      if (d.blocked) { showBlockedScreen(d.msg); return; }
      if (errEl) { errEl.textContent = '❌ '+(d.msg||'Shop ID ya password galat hai'); errEl.style.display = 'block'; }
    } catch(e) { restore(); if (errEl) { errEl.textContent = '❌ Server error'; errEl.style.display = 'block'; } }
    
  } else if (isMobile) {
    // Mobile number → try shop by mobile
    fd.append('action', 'login_shop_by_mobile');
    fd.append('mobile', idVal);
    fd.append('password', pass);
    try {
      const res = await fetch('php/auth.php', {method:'POST', body:fd});
      const d = await res.json();
      if (d.ok) { location.reload(); return; }
      restore();
      if (errEl) { errEl.textContent = '❌ '+(d.msg||'Mobile ya password galat hai'); errEl.style.display = 'block'; }
    } catch(e) { restore(); if (errEl) { errEl.textContent = '❌ Server error'; errEl.style.display = 'block'; } }
  }
}

function showCustomerLogin() {
  document.getElementById('panel-unified').style.display = 'none';
  document.getElementById('panel-customer').style.display = '';
}
function hideCustomerLogin() {
  document.getElementById('panel-unified').style.display = '';
  document.getElementById('panel-customer').style.display = 'none';
}

// Support floating button
function openSupport() {
  const msg = `Namaskar! 🙏\nMujhe Digital Bandhak platform mein help chahiye.\nMera issue: `;
  const phone = '916206869543'; // admin mobile
  window.open('https://wa.me/'+phone+'?text='+encodeURIComponent(msg), '_blank');
}


// ─── CUSTOMER REGISTRATION ────────────────────────────────────
function showCustomerRegister() {
  // Hide unified panel, show register form
  document.getElementById('panel-unified').style.display = 'none';
  document.getElementById('panel-customer').style.display = 'none';
  document.getElementById('panel-cust-register').style.display = 'block';
}

function hideCustomerRegister() {
  document.getElementById('panel-cust-register').style.display = 'none';
  document.getElementById('panel-unified').style.display = 'block';
}

async function doCustomerRegister() {
  const name    = document.getElementById('cr-name')?.value?.trim();
  const mobile  = document.getElementById('cr-mobile')?.value?.trim();
  const address = document.getElementById('cr-address')?.value?.trim();
  const btn     = document.getElementById('crBtn');
  const err     = document.getElementById('cr-err');
  
  if (err) { err.style.display = 'none'; err.textContent = ''; }
  if (!name || !mobile) { 
    if (err) { err.textContent = '❌ Naam aur mobile zaroori hai'; err.style.display = 'block'; }
    return; 
  }
  if (mobile.length !== 10 || isNaN(mobile)) {
    if (err) { err.textContent = '❌ Valid 10-digit mobile daalein'; err.style.display = 'block'; }
    return;
  }
  
  if (btn) { btn._orig = btn.innerHTML; btn.innerHTML = '<span class="spin" style="width:16px;height:16px;border-width:2px;display:inline-block;border-radius:50%;border:2px solid rgba(255,255,255,.3);border-top-color:#fff;animation:spin360 .7s linear infinite"></span> Registering...'; btn.disabled = true; }
  
  try {
    const fd = new FormData();
    fd.append('action', 'register_customer');
    fd.append('name', name);
    fd.append('mobile', mobile);
    fd.append('address', address || '');
    
    const res = await fetch('php/api.php', { method: 'POST', body: fd });
    const d   = await res.json();
    
    if (d.ok) {
      // Show success screen
      const panel = document.getElementById('panel-cust-register');
      panel.innerHTML = `
        <div style="text-align:center;padding:16px 0">
          <div style="font-size:52px;margin-bottom:12px">✅</div>
          <div style="font-size:18px;font-weight:900;color:var(--saffron);margin-bottom:8px">Registration Complete!</div>
          <div style="font-size:13px;color:var(--muted);margin-bottom:16px;line-height:1.5">
            <b style="color:var(--text)">${name}</b>, aapka registration ho gaya hai.<br>
            Admin aapka account approve karega.<br>
            <b>Mobile:</b> ${mobile}
          </div>
          <div style="background:rgba(255,179,0,.1);border:1px solid rgba(255,179,0,.3);border-radius:12px;padding:12px;margin-bottom:16px;font-size:12px;color:var(--text)">
            ⏳ Approval ke baad aap OTP se login kar sakenge
          </div>
          <button class="btnP" onclick="window.location.reload()">← Wapas Login Par</button>
        </div>`;
    } else if (d.already_active) {
      // Already active - redirect to customer login
      if (err) { err.textContent = ''; err.style.display = 'none'; }
      showCustomerLogin();
      const custErr = document.getElementById('cust-err');
      if (custErr) { custErr.textContent = '✅ Aapka account active hai! Mobile se OTP le kar login karein.'; custErr.style.display = 'block'; custErr.style.color = 'var(--green)'; }
    } else if (d.pending) {
      const panel = document.getElementById('panel-cust-register');
      panel.innerHTML = `
        <div style="text-align:center;padding:16px 0">
          <div style="font-size:52px;margin-bottom:12px">⏳</div>
          <div style="font-size:18px;font-weight:900;color:var(--gold);margin-bottom:8px">Approval Pending</div>
          <div style="font-size:13px;color:var(--muted);margin-bottom:16px">Aapka account pehle se registered hai aur admin approval ka wait kar raha hai.</div>
          <button class="btnP" onclick="window.location.reload()">← Wapas</button>
        </div>`;
    } else {
      if (err) { err.textContent = '❌ ' + (d.msg || 'Error aaya'); err.style.display = 'block'; }
    }
  } catch(e) {
    if (err) { err.textContent = '❌ Network error. Try again.'; err.style.display = 'block'; }
  } finally {
    if (btn) { btn.disabled = false; btn.innerHTML = btn._orig || '✅ Register'; }
  }
}

function navTo(page, el) {
  document.querySelectorAll('.bn-item').forEach(b=>b.classList.remove('active'));
  if(el) el.classList.add('active');
  // Profile opens modal
  if (page === 'profile') {
    if (typeof renderProfileModal === 'function') renderProfileModal();
    return;
  }
  // Map nav tab names to page names
  const pageMap = {
    'bandhak': typeof ROLE !== 'undefined' && ROLE === 'admin' ? 'shops' : 'pawns',
    'home':    'dashboard',
    'subs':    'subscriptions',
    'pay':     'payments',
  };
  const realPage = pageMap[page] || page;
  if(typeof loadPage==='function') loadPage(realPage);
}
function syncBottomNav(page) {
  const m={dashboard:'bn-dash',bandhak:'bn-bandhak',payments:'bn-payments',profile:'bn-profile',shops:'bn-bandhak',subscriptions:'bn-payments'};
  document.querySelectorAll('.bn-item').forEach(b=>b.classList.remove('active'));
  const id=m[page]; if(id){const e=document.getElementById(id);if(e)e.classList.add('active');}
}
function setAdminBottomNav() {
  const nav=document.getElementById('bottomNav');
  if(!nav) return;
  nav.innerHTML=`
    <button class="bn-item active" id="bn-dash" onclick="navTo('dashboard',this)"><span class="bn-icon">🏠</span><span class="bn-label">Home</span></button>
    <button class="bn-item" id="bn-bandhak" onclick="navTo('shops',this)"><span class="bn-icon">🏪</span><span class="bn-label">Shops</span></button>
    <button class="bn-item" id="bn-payments" onclick="navTo('subscriptions',this)"><span class="bn-icon">💳</span><span class="bn-label">Subs</span></button>
    <button class="bn-item" id="bn-chat2" onclick="navTo('chat',this)"><span class="bn-icon">💬</span><span class="bn-label">Chat</span></button>
    <button class="bn-item" id="bn-profile" onclick="navTo('profile',this)"><span class="bn-icon">👤</span><span class="bn-label">Profile</span></button>`;
}
