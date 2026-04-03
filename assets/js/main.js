/**
 * DMR Construction — Store Management System
 * Main JavaScript
 */

/* ── MODALS ──────────────────────────────────────────────────────── */
function openModal(id) {
  const el = document.getElementById(id);
  if (el) { el.classList.add('open'); document.body.style.overflow = 'hidden'; }
}
function closeModal(id) {
  const el = document.getElementById(id);
  if (el) { el.classList.remove('open'); document.body.style.overflow = ''; }
}

// Close on overlay click
document.addEventListener('click', function (e) {
  if (e.target.classList.contains('modal-overlay')) {
    e.target.classList.remove('open');
    document.body.style.overflow = '';
  }
});

// Close on Escape
document.addEventListener('keydown', function (e) {
  if (e.key === 'Escape') {
    document.querySelectorAll('.modal-overlay.open').forEach(m => {
      m.classList.remove('open');
      document.body.style.overflow = '';
    });
    closeNotif();
  }
});

/* ── NOTIFICATIONS ───────────────────────────────────────────────── */
function toggleNotif() {
  const panel = document.getElementById('notifPanel');
  if (!panel) return;
  panel.classList.toggle('open');
}
function closeNotif() {
  const panel = document.getElementById('notifPanel');
  if (panel) panel.classList.remove('open');
}
document.addEventListener('click', function (e) {
  const wrap = document.querySelector('.notif-wrap');
  if (wrap && !wrap.contains(e.target)) closeNotif();
});

/* ── SIDEBAR TOGGLE (MOBILE) ─────────────────────────────────────── */
function toggleSidebar() {
  const sb = document.getElementById('sidebar');
  const ovl = document.getElementById('sidebarOverlay');
  if (!sb) return;
  sb.classList.toggle('open');
  if (ovl) ovl.classList.toggle('show');
}

/* ── AJAX HELPERS ────────────────────────────────────────────────── */
async function postAction(url, data) {
  const fd = new FormData();
  const csrf = document.querySelector('meta[name="csrf"]');
  if (csrf) fd.append('csrf_token', csrf.getAttribute('content'));
  for (const [k, v] of Object.entries(data)) fd.append(k, v);
  const res = await fetch(url, { method: 'POST', body: fd });
  return res.json();
}

/* ── FLASH AUTO-DISMISS ──────────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', function () {
  const flash = document.querySelector('.flash-container .alert');
  if (flash) setTimeout(() => flash.remove(), 5000);
});

/* ── CONFIRM DELETE / DEACTIVATE ─────────────────────────────────── */
function confirmAction(msg, formId) {
  if (confirm(msg)) {
    const form = document.getElementById(formId);
    if (form) form.submit();
    return true;
  }
  return false;
}

/* ── TABLE LIVE SEARCH ───────────────────────────────────────────── */
function liveSearch(inputId, tableId, colIndex) {
  const input = document.getElementById(inputId);
  const table = document.getElementById(tableId);
  if (!input || !table) return;
  input.addEventListener('input', function () {
    const q = this.value.toLowerCase();
    table.querySelectorAll('tbody tr').forEach(row => {
      const cell = colIndex !== undefined ? row.cells[colIndex] : row;
      const text = cell ? cell.textContent.toLowerCase() : row.textContent.toLowerCase();
      row.style.display = text.includes(q) ? '' : 'none';
    });
  });
}

/* ── STOCK CHECK (ISSUE FORM) ────────────────────────────────────── */
function checkStock() {
  const prodSel = document.getElementById('product_id');
  const qtyIn = document.getElementById('quantity_requested');
  const availEl = document.getElementById('available_stock');
  const warnEl = document.getElementById('stock_warning');
  if (!prodSel || !qtyIn) return;

  const opt = prodSel.options[prodSel.selectedIndex];
  if (!opt || !opt.value) return;

  const avail = parseFloat(opt.dataset.avail || 0);
  const qty = parseFloat(qtyIn.value || 0);

  if (availEl) availEl.value = avail + ' ' + (opt.dataset.unit || '');
  if (warnEl) warnEl.style.display = (qty > avail && avail >= 0) ? 'flex' : 'none';
}

/* ── APPROVE / REJECT INLINE ─────────────────────────────────────── */
function showRejectReason(formId) {
  const group = document.getElementById('reject_reason_group');
  if (group) { group.style.display = 'block'; group.querySelector('textarea').focus(); }
  const approveBtn = document.getElementById('approveBtn');
  const rejectBtn = document.getElementById('rejectBtn');
  if (approveBtn) approveBtn.style.display = 'none';
  const form = document.getElementById(formId);
  if (rejectBtn && form) rejectBtn.onclick = () => {
    const reason = document.getElementById('reject_reason');
    if (!reason || !reason.value.trim()) { alert('Rejection reason is mandatory'); return false; }
    form.submit();
  };
}

/* ── FORMAT NUMBERS ──────────────────────────────────────────────── */
function fmt(n) { return Number(n).toLocaleString('en-IN'); }

/* ── PRINT ───────────────────────────────────────────────────────── */
function printArea(id) {
  const el = document.getElementById(id);
  if (!el) return;
  const win = window.open('', '_blank');
  win.document.write('<html><head><title>Print</title><link rel="stylesheet" href="/assets/css/style.css"></head><body>' + el.innerHTML + '</body></html>');
  win.document.close();
  win.onload = () => { win.print(); win.close(); };
}

/* ── AUTO-DISMISS TIMEOUT ALERTS ─────────────────────────────────── */
setTimeout(function () {
  document.querySelectorAll('.alert-dismissible').forEach(a => {
    a.style.opacity = '0'; a.style.transition = 'opacity 0.5s';
    setTimeout(() => a.remove(), 500);
  });
}, 4000);
