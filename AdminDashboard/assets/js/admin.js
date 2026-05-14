// ── Toast notification system ─────────────────────────────
(function () {
  const ICONS  = { success:'bi-check-circle-fill', error:'bi-x-circle-fill', warning:'bi-exclamation-triangle-fill', info:'bi-info-circle-fill' };
  const TITLES = { success:'Success', error:'Error', warning:'Warning', info:'Info' };

  function container() {
    let c = document.getElementById('toast-container');
    if (!c) { c = document.createElement('div'); c.id = 'toast-container'; document.body.appendChild(c); }
    return c;
  }

  window.notify = function (message, type, duration) {
    type     = ['success','error','warning','info'].includes(type) ? type : 'info';
    duration = duration ?? 4000;

    const toast = document.createElement('div');
    toast.className = 'toast-tv ' + type;
    toast.innerHTML =
      '<div class="toast-inner">' +
        '<div class="toast-icon"><i class="bi ' + ICONS[type] + '"></i></div>' +
        '<div class="toast-body">' +
          '<div class="toast-title">' + TITLES[type] + '</div>' +
          '<div class="toast-msg">'   + message       + '</div>' +
        '</div>' +
        '<button class="toast-close" aria-label="Close"><i class="bi bi-x-lg"></i></button>' +
      '</div>' +
      '<div class="toast-progress"></div>';

    function dismiss() {
      toast.classList.add('hide');
      toast.addEventListener('transitionend', () => toast.remove(), { once: true });
    }

    toast.querySelector('.toast-close').addEventListener('click', dismiss);
    container().appendChild(toast);

    requestAnimationFrame(() => requestAnimationFrame(() => toast.classList.add('show')));

    const bar = toast.querySelector('.toast-progress');
    bar.style.transition = 'transform ' + duration + 'ms linear';
    requestAnimationFrame(() => requestAnimationFrame(() => { bar.style.transform = 'scaleX(0)'; }));

    let timer = setTimeout(dismiss, duration);
    toast.addEventListener('mouseenter', () => { clearTimeout(timer); bar.style.transition = 'none'; });
    toast.addEventListener('mouseleave', () => { timer = setTimeout(dismiss, 1200); bar.style.transition = 'transform 1200ms linear'; bar.style.transform = 'scaleX(0)'; });

    return toast;
  };
})();

function toggleSidebar() {
  document.getElementById('sidebar').classList.toggle('open');
}

document.addEventListener('click', function(e) {
  const sidebar = document.getElementById('sidebar');
  const toggle  = document.querySelector('.sidebar-toggle');
  if (window.innerWidth <= 768 && sidebar.classList.contains('open')) {
    if (!sidebar.contains(e.target) && !toggle.contains(e.target)) {
      sidebar.classList.remove('open');
    }
  }
});

function confirmLogout() {
  (bootstrap.Modal.getInstance(document.getElementById('logoutModal')) ||
   new bootstrap.Modal(document.getElementById('logoutModal'))).show();
  return false;
}

function confirmDelete(entityLabel, formId, entityName) {
  const cap = entityLabel.charAt(0).toUpperCase() + entityLabel.slice(1);
  document.getElementById('delModalTitle').textContent = 'Delete ' + cap + '?';
  document.getElementById('delModalMsg').textContent   = entityName
    ? 'You\'re about to permanently delete "' + entityName + '". This action cannot be undone.'
    : 'This action is permanent and cannot be undone.';

  // Reset to red Delete styling each time
  const btn  = document.getElementById('delModalConfirm');
  btn.innerHTML = '<i class="bi bi-trash3"></i> Delete';
  btn.style.background = '#DC2626';
  const icon = document.querySelector('#deleteModal .del-modal-icon');
  if (icon) { icon.style.background = '#FEE2E2'; icon.innerHTML = '<i class="bi bi-trash3-fill" style="color:#DC2626;font-size:28px"></i>'; }

  const copy = btn.cloneNode(true);
  btn.parentNode.replaceChild(copy, btn);
  copy.addEventListener('click', function () {
    bootstrap.Modal.getInstance(document.getElementById('deleteModal')).hide();
    document.getElementById(formId).submit();
  });

  const existing = bootstrap.Modal.getInstance(document.getElementById('deleteModal'));
  (existing || new bootstrap.Modal(document.getElementById('deleteModal'))).show();
}

// ── Generic confirm dialog (reuses the deleteModal as a generic confirm) ──
window.tvConfirm = function (opts) {
  const modal  = document.getElementById('deleteModal');
  const title  = document.getElementById('delModalTitle');
  const msg    = document.getElementById('delModalMsg');
  const btn    = document.getElementById('delModalConfirm');
  const icon   = document.querySelector('#deleteModal .del-modal-icon');

  const palette = {
    danger:  { color:'#DC2626',           bg:'#FEE2E2',                icon:'bi-trash3-fill' },
    success: { color:'#16A34A',           bg:'#DCFCE7',                icon:'bi-check-circle-fill' },
    warning: { color:'var(--tv-orange)',  bg:'var(--tv-orange-light)', icon:'bi-exclamation-triangle-fill' },
    info:    { color:'var(--tv-blue)',    bg:'var(--tv-blue-light)',   icon:'bi-question-circle-fill' }
  }[opts.variant || 'danger'];

  if (icon) { icon.style.background = palette.bg; icon.innerHTML = '<i class="bi ' + (opts.icon || palette.icon) + '" style="color:' + palette.color + ';font-size:28px"></i>'; }
  title.textContent = opts.title   || 'Confirm';
  msg.textContent   = opts.message || '';
  btn.innerHTML        = '<i class="bi ' + (opts.confirmIcon || 'bi-check-lg') + '"></i> ' + (opts.confirmText || 'Confirm');
  btn.style.background = palette.color;

  const copy = btn.cloneNode(true);
  btn.parentNode.replaceChild(copy, btn);
  copy.addEventListener('click', function () {
    bootstrap.Modal.getInstance(modal).hide();
    if (typeof opts.onConfirm === 'function') opts.onConfirm();
  });

  (bootstrap.Modal.getInstance(modal) || new bootstrap.Modal(modal)).show();
};

// ── Intercept forms with data-confirm-message
document.addEventListener('submit', function (e) {
  const form = e.target;
  if (!form.matches || !form.matches('form[data-confirm-message]')) return;
  if (form.dataset._confirmed === '1') return;
  e.preventDefault();
  tvConfirm({
    variant:     form.dataset.confirmVariant || 'danger',
    title:       form.dataset.confirmTitle   || 'Confirm',
    message:     form.dataset.confirmMessage,
    confirmText: form.dataset.confirmAction  || 'Confirm',
    confirmIcon: form.dataset.confirmActionIcon || null,
    onConfirm() { form.dataset._confirmed = '1'; form.submit(); }
  });
}, true);

