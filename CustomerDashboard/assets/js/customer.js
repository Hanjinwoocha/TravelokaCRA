// ── Toast notification system ───────────────────────────────
(function () {
  const ICONS  = { success:'bi-check-circle-fill', error:'bi-x-circle-fill', warning:'bi-exclamation-triangle-fill', info:'bi-info-circle-fill' };
  const TITLES = { success:'Success', error:'Error', warning:'Warning', info:'Notice' };

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

// ── Generic confirm dialog ──────────────────────────────────
window.tvConfirm = function (opts) {
  const modal    = document.getElementById('confirmModal');
  if (!modal) { if (typeof opts.onConfirm === 'function') opts.onConfirm(); return; }

  const titleEl  = document.getElementById('confirmTitle');
  const msgEl    = document.getElementById('confirmMsg');
  const btn      = document.getElementById('confirmBtn');
  const iconWrap = document.getElementById('confirmIcon');

  const variant = opts.variant || 'danger';
  const palette = {
    danger:  { color:'#DC2626',           bg:'#FEE2E2',                icon:'bi-trash3-fill' },
    success: { color:'#16A34A',           bg:'#DCFCE7',                icon:'bi-check-circle-fill' },
    warning: { color:'var(--tv-orange)',  bg:'var(--tv-orange-light)', icon:'bi-exclamation-triangle-fill' },
    info:    { color:'var(--tv-blue)',    bg:'var(--tv-blue-light)',   icon:'bi-question-circle-fill' }
  }[variant] || { color:'#DC2626', bg:'#FEE2E2', icon:'bi-trash3-fill' };

  iconWrap.style.background = palette.bg;
  iconWrap.innerHTML = '<i class="bi ' + (opts.icon || palette.icon) + '" style="color:' + palette.color + ';font-size:28px"></i>';
  titleEl.textContent = opts.title || 'Confirm';
  msgEl.textContent   = opts.message || '';
  btn.innerHTML = '<i class="bi ' + (opts.confirmIcon || 'bi-check-lg') + '"></i> ' + (opts.confirmText || 'Confirm');
  btn.style.background = palette.color;

  const copy = btn.cloneNode(true);
  btn.parentNode.replaceChild(copy, btn);
  copy.addEventListener('click', function () {
    bootstrap.Modal.getInstance(modal).hide();
    if (typeof opts.onConfirm === 'function') opts.onConfirm();
  });

  (bootstrap.Modal.getInstance(modal) || new bootstrap.Modal(modal)).show();
};

function confirmLogout() {
  const m = document.getElementById('logoutModal');
  if (m) (bootstrap.Modal.getInstance(m) || new bootstrap.Modal(m)).show();
  return false;
}

// Intercept forms with data-confirm-message
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

// ── Mobile menu toggle ──────────────────────────────────────
function toggleMobileMenu() {
  document.getElementById('mobileMenu').classList.toggle('open');
}
document.addEventListener('click', function (e) {
  const menu   = document.getElementById('mobileMenu');
  const burger = document.querySelector('.cust-hamburger');
  if (menu && menu.classList.contains('open')) {
    if (!menu.contains(e.target) && (!burger || !burger.contains(e.target))) {
      menu.classList.remove('open');
    }
  }
});
