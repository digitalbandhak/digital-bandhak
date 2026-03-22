// ============================================
// DIGITAL BANDHAK — app.js v6 FINAL
// ============================================

// ---- THEME ----
(function(){
  const t = localStorage.getItem('db_theme') || 'light';
  document.documentElement.setAttribute('data-theme', t);
  updateThemeIcon(t);
})();
function toggleTheme() {
  const cur  = document.documentElement.getAttribute('data-theme') || 'light';
  const next = cur === 'light' ? 'dark' : 'light';
  document.documentElement.setAttribute('data-theme', next);
  localStorage.setItem('db_theme', next);
  updateThemeIcon(next);
}
function updateThemeIcon(t) {
  const btn = document.getElementById('themeToggle');
  if (btn) btn.textContent = t === 'dark' ? '☀️' : '🌙';
}

// ---- TABS ----
function switchTab(tabBarId, panelId, idx) {
  document.querySelectorAll('#'+tabBarId+' .tab-btn').forEach((t,i) => t.classList.toggle('active', i===idx));
  document.querySelectorAll('#'+panelId+' .tab-pane').forEach((p,i) => p.classList.toggle('active', i===idx));
}

// ---- SIDEBAR ----
function toggleSidebar() {
  const s   = document.getElementById('sidebar');
  const ov  = document.getElementById('sidebarOverlay');
  if (!s) return;
  const isOpen = s.classList.contains('open');
  if (isOpen) {
    s.classList.remove('open');
    if (ov) ov.classList.remove('show');
    document.body.style.overflow = '';
  } else {
    s.classList.add('open');
    if (ov) ov.classList.add('show');
    if (window.innerWidth <= 768) document.body.style.overflow = 'hidden';
  }
}
document.addEventListener('click', e => {
  const sb  = document.getElementById('sidebar');
  const tog = document.querySelector('.sidebar-toggle, .ham-btn');
  const ov  = document.getElementById('sidebarOverlay');
  if (!sb) return;
  if (e.target === ov || (sb.classList.contains('open') && !sb.contains(e.target) && tog && !tog.contains(e.target))) {
    sb.classList.remove('open');
    if (ov) ov.classList.remove('show');
    document.body.style.overflow = '';
  }
});

// ---- MODAL ----
function openModal(id) {
  const el = document.getElementById(id);
  if (el) { el.style.display='flex'; document.body.style.overflow='hidden'; }
}
function closeModal(id) {
  const el = document.getElementById(id);
  if (el) { el.style.display='none'; document.body.style.overflow=''; }
}
document.addEventListener('click', e => {
  if (e.target.classList.contains('modal-backdrop')) closeModal(e.target.id);
});

// ---- TOAST ALERT ----
function showAlert(msg, type='success', duration=3500) {
  const el = document.createElement('div');
  el.className = `alert alert-${type}`;
  el.style.cssText = 'position:fixed;top:72px;right:20px;z-index:9999;min-width:280px;max-width:420px;box-shadow:0 4px 20px rgba(0,0,0,0.18);animation:slideUp .2s ease;cursor:pointer';
  el.innerHTML = (type==='success'?'✔ ':type==='danger'?'✖ ':'⚠ ') + msg;
  el.onclick = () => el.remove();
  document.body.appendChild(el);
  setTimeout(() => el.remove(), duration);
}

// ---- AJAX HELPER ----
async function apiPost(url, data) {
  const fd = new FormData();
  Object.keys(data).forEach(k => fd.append(k, data[k]));
  const r = await fetch(url, { method:'POST', body:fd });
  return r.json();
}

// ---- LOAN CALC ----
function calcLoanPreview() {
  const loan = parseFloat(document.getElementById('loan_amount')?.value) || 0;
  const rate = parseFloat(document.getElementById('interest_rate')?.value) || 0;
  const durRaw = parseInt(document.getElementById('duration_months')?.value) || 0;
  const unit = document.getElementById('duration_unit')?.value || 'months';
  const el   = document.getElementById('loan-preview');
  if (!el) return;
  // If duration is 0 — show 0 interest (no error)
  const dur    = Math.max(0, durRaw);
  const months = dur === 0 ? 0 : (unit==='years' ? dur*12 : (unit==='days' ? Math.ceil(dur/30) : dur));
  const mi = loan * (rate/100);
  const ti = mi * months;
  const td = loan + ti;
  let html = `<div class="stat-grid" style="margin-top:10px">
    <div class="stat-card"><div class="stat-label">Principal</div><div class="stat-value" style="font-size:18px">₹${loan.toLocaleString('en-IN')}</div></div>
    <div class="stat-card"><div class="stat-label">Monthly Interest</div><div class="stat-value" style="font-size:18px;color:var(--warning)">₹${Math.round(mi).toLocaleString('en-IN')}</div></div>
    <div class="stat-card"><div class="stat-label">Total Interest (${months}mo)</div><div class="stat-value" style="font-size:18px;color:var(--warning)">₹${Math.round(ti).toLocaleString('en-IN')}</div></div>
    <div class="stat-card" style="border-color:var(--gold)"><div class="stat-label">Total Due</div><div class="stat-value" style="color:var(--danger)">₹${Math.round(td).toLocaleString('en-IN')}</div></div>
  </div>`;
  if (loan > 0 && months > 1) {
    html += `<div class="table-wrap mt-12" style="max-height:180px;overflow-y:auto"><table><thead><tr><th style="font-size:11px">Month</th><th style="font-size:11px;text-align:right">Monthly Interest</th><th style="font-size:11px;text-align:right">Cumulative Due</th></tr></thead><tbody>`;
    for (let m=1; m<=Math.min(months,24); m++) {
      html += `<tr><td style="font-size:12px">Month ${m}</td><td style="text-align:right;color:var(--warning);font-size:12px">₹${Math.round(mi).toLocaleString('en-IN')}</td><td style="text-align:right;color:var(--danger);font-weight:600;font-size:12px">₹${Math.round(loan+mi*m).toLocaleString('en-IN')}</td></tr>`;
    }
    if (months > 24) html += `<tr><td colspan="3" style="text-align:center;padding:8px;color:var(--text3);font-size:12px">... ${months-24} more months</td></tr>`;
    html += '</tbody></table></div>';
  }
  el.innerHTML = html;
}

// ---- PAYMENT REMAINING ----
function calcPaymentRemaining() {
  const rem = parseFloat(document.getElementById('current_remaining')?.value) || 0;
  const pay = parseFloat(document.getElementById('pay_amount')?.value) || 0;
  const el  = document.getElementById('remaining-after');
  if (el) {
    const after = Math.max(0, rem - pay);
    el.textContent = '₹' + after.toLocaleString('en-IN', {minimumFractionDigits:2});
    el.style.color = after === 0 ? 'var(--success)' : 'var(--danger)';
  }
}

// ---- CHAT ----
let chatLastId = 0;

function initChat(shopId, role) {
  loadMessages(shopId, role);
  clearInterval(window.chatInterval);
  window.chatInterval = setInterval(() => loadMessages(shopId, role), 3000);
}

async function loadMessages(shopId, role) {
  try {
    const base = window.CHAT_BASE || '../';
    const fd = new FormData();
    fd.append('shop_id', shopId);
    fd.append('last_id', chatLastId);
    const r    = await fetch(base + 'php/chat_fetch.php', { method:'POST', body:fd });
    const data = await r.json();
    if (data.messages && data.messages.length) {
      const box = document.getElementById('chatMessages');
      if (!box) return;
      data.messages.forEach(m => {
        chatLastId = Math.max(chatLastId, parseInt(m.id));
        const mine = (role==='admin' && m.sender_type==='admin') || (role==='owner' && m.sender_type==='owner');
        const div  = document.createElement('div');
        div.className = `msg-row ${mine ? 'owner' : 'admin'}`;

        let bubble = `<div class="msg-bubble">${escHtml(m.message)}`;
        if (m.file_path && m.file_type === 'image') {
          // Build URL - use file_url from server, or construct from CHAT_BASE
          let imgUrl = m.file_url;
          if (!imgUrl && m.file_path) {
            const base = window.CHAT_BASE || '../';
            // Remove leading 'uploads/' if CHAT_BASE already has it
            const cleanPath = m.file_path.replace(/^uploads\//, '');
            imgUrl = base.replace(/php\/?$/, '') + 'uploads/' + cleanPath;
          }
          if (imgUrl) {
            bubble += `<br/><img src="${escHtml(imgUrl)}" style="max-width:200px;max-height:150px;border-radius:8px;margin-top:6px;cursor:pointer;display:block;border:2px solid rgba(255,255,255,0.15)" onclick="openChatImage('${escHtml(imgUrl)}')" alt="Photo" loading="lazy" onerror="this.style.display='none';this.nextSibling.style.display='flex'"/><a href="${escHtml(imgUrl)}" target="_blank" style="display:none;align-items:center;gap:6px;padding:5px 10px;background:rgba(0,0,0,0.1);border-radius:8px;font-size:12px;color:inherit;text-decoration:none;margin-top:4px">📷 View Photo</a>`;
          }
        } else if (m.file_path) {
          let fileUrl = m.file_url;
          if (!fileUrl && m.file_path) {
            const base = window.CHAT_BASE || '../';
            const cleanPath = m.file_path.replace(/^uploads\//, '');
            fileUrl = base.replace(/php\/?$/, '') + 'uploads/' + cleanPath;
          }
          const fname = escHtml(m.file_name || 'File');
          bubble += `<br/><a href="${escHtml(fileUrl||'#')}" target="_blank" rel="noopener" style="display:inline-flex;align-items:center;gap:6px;padding:5px 10px;background:rgba(0,0,0,0.1);border-radius:8px;font-size:12px;text-decoration:none;color:inherit;margin-top:4px">📎 ${fname}</a>`;
        }
        bubble += `</div><div class="msg-time">${mine ? 'You' : (m.sender_type==='admin' ? 'Admin' : 'Owner')} · ${m.time}</div>`;
        div.innerHTML = `<div>${bubble}</div>`;
        box.appendChild(div);
      });
      box.scrollTop = box.scrollHeight;
    }
  } catch(e) { /* silent fail */ }
}

// ---- CHAT IMAGE LIGHTBOX (FIXED) ----
function openChatImage(url) {
  // Remove existing modal if any
  let existing = document.getElementById('chatImageModal');
  if (existing) existing.remove();

  const modal = document.createElement('div');
  modal.id = 'chatImageModal';
  modal.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,0.88);z-index:99999;display:flex;align-items:center;justify-content:center;padding:20px;cursor:pointer';
  modal.onclick = () => modal.remove();
  modal.innerHTML = `
    <div style="position:relative;max-width:90vw;max-height:90vh">
      <button style="position:fixed;top:16px;right:20px;background:rgba(255,255,255,0.15);border:none;color:#fff;font-size:24px;width:40px;height:40px;border-radius:50%;cursor:pointer;display:flex;align-items:center;justify-content:center" onclick="document.getElementById('chatImageModal').remove()">✕</button>
      <img src="${url}" style="max-width:90vw;max-height:86vh;border-radius:12px;object-fit:contain;display:block" alt="Photo"/>
      <a href="${url}" download target="_blank" style="display:block;text-align:center;margin-top:10px;color:#fff;font-size:13px;text-decoration:none;background:rgba(255,255,255,0.1);padding:6px 16px;border-radius:20px" onclick="event.stopPropagation()">📥 Download</a>
    </div>`;
  document.body.appendChild(modal);
}

async function sendChatMessage(shopId, senderType) {
  const inp = document.getElementById('chatInput');
  const msg = inp.value.trim();
  if (!msg) return;
  inp.value = '';
  const base = window.CHAT_BASE || '../';
  const r = await apiPost(base + 'php/chat_send.php', { shop_id:shopId, message:msg, sender_type:senderType });
  if (r.success) loadMessages(shopId, senderType);
  else showAlert(r.msg || 'Send failed', 'danger');
}

// ---- OTP — CUSTOMER LOGIN (FIXED) ----
// Global store for OTP modal state
let _otpBandhakId = '';
let _otpMobile    = '';

async function requestOtp() {
  const bandhakId = (document.getElementById('c_bandhak_id')?.value || '').trim().toUpperCase();
  const mobileRaw = (document.getElementById('c_mobile')?.value || '').replace(/\D/g, '');
  const mobile    = mobileRaw.slice(-10);

  if (!bandhakId) return showAlert('Bandhak ID daalo', 'warning');
  if (mobile.length < 10) return showAlert('Valid 10-digit mobile daalo', 'warning');

  // Store for verify step
  _otpBandhakId = bandhakId;
  _otpMobile    = mobile;

  const btn = document.querySelector('[onclick="requestOtp()"]');
  if (btn) { btn.disabled = true; btn.textContent = '⏳ Sending...'; }

  try {
    const r = await apiPost('php/otp_request.php', { bandhak_id: bandhakId, mobile });
    if (r.success) {
      // Show OTP step
      const s1 = document.getElementById('otp-s1');
      const s2 = document.getElementById('otp-s2');
      if (s1) s1.style.display = 'none';
      if (s2) s2.style.display = 'block';

      // DEV: show OTP toast and auto-fill boxes
      if (r.dev_otp) {
        showAlert('🔑 DEV OTP: ' + r.dev_otp, 'warning', 20000);
        // Auto fill OTP boxes — find them inside modal
        const modal  = document.getElementById('modal-otp');
        const boxes  = modal ? modal.querySelectorAll('.otp-input') : document.querySelectorAll('.otp-input');
        String(r.dev_otp).split('').forEach((d, i) => { if (boxes[i]) { boxes[i].value = d; } });
      } else {
        showAlert(r.msg || 'OTP sent!', 'success');
      }
    } else {
      showAlert(r.msg || 'Error', 'danger');
    }
  } catch(e) {
    showAlert('Request failed. Network check karo.', 'danger');
  } finally {
    if (btn) { btn.disabled = false; btn.textContent = '📱 OTP Bhejo'; }
  }
}

async function verifyOtp() {
  // Use stored bandhak_id and mobile (more reliable than reading from fields)
  const bandhakId = _otpBandhakId || (document.getElementById('c_bandhak_id')?.value || '').trim().toUpperCase();
  const mobile    = _otpMobile    || (document.getElementById('c_mobile')?.value || '').replace(/\D/g, '').slice(-10);

  if (!bandhakId) return showAlert('Bandhak ID missing. Back karo aur fill karo.', 'warning');

  // Collect OTP from modal boxes
  const modal = document.getElementById('modal-otp');
  const boxes = modal ? modal.querySelectorAll('.otp-input') : document.querySelectorAll('#otp-s2 .otp-input');
  const otp   = Array.from(boxes).map(b => b.value.trim()).join('');

  if (otp.length < 6) return showAlert('6-digit OTP daalo', 'warning');

  // Disable button
  const btn = modal ? modal.querySelector('#otp-s2 .login-btn, #otp-s2 button') : document.querySelector('#otp-s2 button');
  if (btn) { btn.disabled = true; btn.textContent = '⏳ Verifying...'; }

  try {
    const r = await apiPost('php/otp_verify.php', { bandhak_id: bandhakId, mobile, otp });
    if (r.success) {
      showAlert('Login successful! Redirecting...', 'success');
      setTimeout(() => { window.location.href = r.redirect || 'customer_dashboard.php'; }, 500);
    } else {
      showAlert(r.msg || 'Invalid OTP', 'danger');
    }
  } catch(e) {
    showAlert('Verification failed. Try again.', 'danger');
  } finally {
    if (btn) { btn.disabled = false; btn.textContent = '✔ Verify & Login'; }
  }
}

// ---- FORM VALIDATION ----
function validateShopForm() {
  const p1 = document.getElementById('reg_password')?.value;
  const p2 = document.getElementById('reg_confirm')?.value;
  if (p1 !== p2)     { showAlert('Passwords match nahi kiye!', 'danger'); return false; }
  if (p1.length < 8) { showAlert('Password 8+ characters chahiye', 'warning'); return false; }
  return true;
}

// ---- DELETE PAWN ----
function confirmDelete(pawnId, bandhakId) {
  const pid = document.getElementById('delete_pawn_id');
  const lbl = document.getElementById('delete_bandhak_label');
  if (pid) pid.value = pawnId;
  if (lbl) lbl.textContent = bandhakId;
  openModal('modal-delete');
}
async function submitDelete() {
  const pawnId  = document.getElementById('delete_pawn_id')?.value;
  const ownerPw = document.getElementById('delete_owner_pw')?.value;
  if (!ownerPw) return showAlert('Owner password zaroori hai', 'warning');
  const base = window.API_BASE || '../';
  const r = await apiPost(base + 'php/pawn_delete.php', { pawn_id: pawnId, owner_password: ownerPw });
  if (r.success) { showAlert('Entry deleted!'); closeModal('modal-delete'); setTimeout(() => location.reload(), 1200); }
  else showAlert(r.msg, 'danger');
}

// ---- RECEIPT ----
function printReceipt(id, type) {
  const base = window.RECEIPT_BASE !== undefined ? window.RECEIPT_BASE : '../';
  const url  = base + 'php/receipt_print.php?id=' + id + (type === 'dup' ? '&dup=1' : '');
  const w    = window.open(url, '_blank', 'width=440,height=700');
  if (w) w.focus();
}

// ---- OTP BOX NAVIGATION ----
function otpNext(el, idx) {
  el.value = el.value.replace(/\D/, '').slice(0, 1);
  // Find sibling boxes
  const parent = el.closest('.otp-inputs') || el.parentElement;
  const boxes  = parent ? parent.querySelectorAll('.otp-input') : document.querySelectorAll('.otp-input');
  if (el.value && idx < boxes.length - 1) boxes[idx + 1].focus();
  // Auto-verify if all filled (for customer modal only)
  if (el.value && idx === boxes.length - 1) {
    const allFilled = Array.from(boxes).every(b => b.value.length === 1);
    if (allFilled && typeof verifyOtp === 'function') {
      setTimeout(verifyOtp, 300);
    }
  }
}
function otpBack(el, idx, e) {
  if (e.key === 'Backspace' && !el.value) {
    const parent = el.closest('.otp-inputs') || el.parentElement;
    const boxes  = parent ? parent.querySelectorAll('.otp-input') : document.querySelectorAll('.otp-input');
    if (idx > 0) { boxes[idx-1].value = ''; boxes[idx-1].focus(); }
  }
}

// ---- UTILS ----
function escHtml(s) {
  return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function formatINR(n) {
  return '₹' + parseFloat(n).toLocaleString('en-IN', { minimumFractionDigits: 2 });
}
