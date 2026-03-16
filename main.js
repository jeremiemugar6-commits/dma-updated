/* =====================================================
   Document Management System — main.js
   ===================================================== */

// ── Toast ─────────────────────────────────────────────
function showToast(message, type = 'info', duration = 3500) {
  let box = document.getElementById('toast-container');
  if (!box) { box = document.createElement('div'); box.id = 'toast-container'; box.className = 'toast-container'; document.body.appendChild(box); }
  const svgs = {
    success: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><polyline points="20 6 9 17 4 12"/></svg>',
    error:   '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><circle cx="12" cy="12" r="10"/><path d="M15 9l-6 6M9 9l6 6"/></svg>',
    warning: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
    info:    '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>',
  };
  const t = document.createElement('div');
  t.className = 'toast toast-' + type;
  t.innerHTML = (svgs[type] || svgs.info) + '<span>' + message + '</span>';
  box.appendChild(t);
  setTimeout(() => { t.style.opacity = '0'; t.style.transition = 'opacity .2s'; setTimeout(() => t.remove(), 220); }, duration);
}

// ── Modal ─────────────────────────────────────────────
function openModal(id) {
  const el = document.getElementById(id);
  if (el) { el.classList.add('open'); document.body.style.overflow = 'hidden'; }
}
function closeModal(id) {
  const el = document.getElementById(id);
  if (el) { el.classList.remove('open'); document.body.style.overflow = ''; }
}
document.addEventListener('click', function(e) {
  // Click on overlay backdrop closes modal
  if (e.target.classList.contains('modal-overlay')) {
    e.target.classList.remove('open');
    document.body.style.overflow = '';
  }
  // Click on close button
  const closeBtn = e.target.closest('.modal-close');
  if (closeBtn) {
    const overlay = closeBtn.closest('.modal-overlay');
    if (overlay) { overlay.classList.remove('open'); document.body.style.overflow = ''; }
  }
});

// ── Dropdown ──────────────────────────────────────────
document.addEventListener('click', function(e) {
  const trigger = e.target.closest('[data-dropdown]');
  if (trigger) {
    const dd = trigger.closest('.dropdown');
    const wasOpen = dd.classList.contains('open');
    document.querySelectorAll('.dropdown.open').forEach(d => d.classList.remove('open'));
    if (!wasOpen) dd.classList.add('open');
    e.stopPropagation();
    return;
  }
  document.querySelectorAll('.dropdown.open').forEach(d => d.classList.remove('open'));
});

// ── Sidebar (mobile) ──────────────────────────────────
document.addEventListener('DOMContentLoaded', function() {
  const hamburger = document.getElementById('hamburger');
  const sidebar   = document.querySelector('.sidebar');
  const overlay   = document.getElementById('sidebar-overlay');
  if (hamburger && sidebar) {
    hamburger.addEventListener('click', function() {
      sidebar.classList.toggle('open');
      if (overlay) overlay.classList.toggle('open');
    });
  }
});

// ── API Fetch ─────────────────────────────────────────
async function apiFetch(url, options = {}) {
  try {
    const res = await fetch(url, {
      headers: {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        ...(options.headers || {})
      },
      ...options,
    });
    const data = await res.json();
    if (!res.ok) throw new Error(data.error || 'Request failed (HTTP ' + res.status + ')');
    return data;
  } catch (err) {
    showToast(err.message || 'Network error', 'error');
    throw err;
  }
}
