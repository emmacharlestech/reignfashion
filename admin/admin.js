/* Reign Fashion admin: phone menu toggle. Escape or a tap outside closes it. */
(() => {
  'use strict';
  const toggle = document.querySelector('.topbar__toggle');
  const nav = document.getElementById('admin-nav');
  if (!toggle || !nav) return;

  const setOpen = (open) => {
    toggle.setAttribute('aria-expanded', String(open));
    nav.classList.toggle('is-open', open);
    toggle.querySelector('.topbar__toggle-label').textContent = open ? 'Close' : 'Menu';
  };

  toggle.addEventListener('click', () => setOpen(toggle.getAttribute('aria-expanded') !== 'true'));
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && nav.classList.contains('is-open')) { setOpen(false); toggle.focus(); }
  });
  document.addEventListener('click', (e) => {
    if (nav.classList.contains('is-open') && !nav.contains(e.target) && !toggle.contains(e.target)) setOpen(false);
  });
})();

/* Copy buttons (e.g. payment details): [data-copy] holds the text */
(() => {
  'use strict';
  document.querySelectorAll('[data-copy]').forEach((btn) => {
    const label = btn.textContent;
    btn.addEventListener('click', async () => {
      try {
        await navigator.clipboard.writeText(btn.dataset.copy);
        btn.textContent = 'Copied ✓';
      } catch {
        // Clipboard blocked (e.g. not HTTPS): show the text so it can be copied by hand
        window.prompt('Copy the payment details:', btn.dataset.copy);
      }
      setTimeout(() => { btn.textContent = label; }, 2000);
    });
  });
})();
