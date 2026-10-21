/* ==========================================================================
   REIGN FASHION EMPIRE — Main script
   Vanilla ES6+. Each feature is a small self-contained init function.
   ========================================================================== */

(() => {
  'use strict';

  /* ========================================================================
     SITE SETTINGS — edit these when the real details are confirmed.
     Leave a value as '' until it is known: the page then shows
     "To be confirmed" and never outputs a fake or broken link.
     ======================================================================== */
  const SITE_CONFIG = {
    // WhatsApp number in international format, digits only, no "+" or spaces.
    // Nigerian 0813 575 9554 → '2348135759554'
    whatsappNumber: '2348135759554',

    // Phone numbers: `display` is shown on the page, `tel` is dialled (international format).
    phones: [
      { display: '0813 575 9554', tel: '+2348135759554' },
      { display: '0907 171 7484', tel: '+2349071717484' },
    ],

    email: 'reignfashionempire@gmail.com',
    address: 'Anni Victor Plaza, Old Anwai Road, opposite Dwell Petrol Filling Station, Asaba, Delta State',
    hours: '',          // e.g. 'Mon–Sat, 9am–6pm' (not yet provided)

    // For each network: `url` is the full profile link (makes it clickable);
    // `name` is the account name shown as plain text until the link is known.
    social: {
      instagram: { url: 'https://www.instagram.com/reign_fashion_empire/', name: '@Reign-Fashion-Empire' },
      facebook:  { url: 'https://www.facebook.com/Reign-Fashion-Empire',  name: '@Reign-Fashion-Empire' },
      tiktok:    { url: 'https://www.tiktok.com/@reignfashionacademy',    name: '@#Reign-Fashion-Academy Asaba' },
    },
    // Academy schedule & fees (empty = "To be confirmed")
    academy: { duration: '', fees: '', intake: '' },
    // Ways to pay and bank accounts (set in Admin → Settings → Payment)
    payment: { methods: [], terms: '', accounts: [] },

    // Live settings edited in the admin area (admin/settings.php). The values
    // above are the fallback if this can't be reached (e.g. file:// preview).
    settingsEndpoint: 'api/settings.php',

    // Where the enquiry form is sent. Leave '' to disable online sending.
    // 'api/enquiry.php' = the built-in PHP + MySQL backend; a Formspree URL also works.
    formEndpoint: 'api/enquiry.php',
  };

  const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

  /** Link to the contact page with the enquiry pre-filled. */
  const contactUrl = (service, design = '') => {
    const q = new URLSearchParams({ service });
    if (design) q.set('design', design);
    return `contact?${q}`;
  };
  const DESKTOP_NAV_QUERY = window.matchMedia('(min-width: 1180px)');

  /* ------------------------------------------------------------------------
     Header: solid background once the page scrolls
     ------------------------------------------------------------------------ */
  function initHeader() {
    const header = document.getElementById('site-header');
    if (!header) return;

    const update = () => header.classList.toggle('is-scrolled', window.scrollY > 24);
    update();
    window.addEventListener('scroll', update, { passive: true });
  }

  /* ------------------------------------------------------------------------
     Mobile menu: open/close, focus trap, Escape, scroll lock
     ------------------------------------------------------------------------ */
  function initMobileMenu() {
    const toggle = document.getElementById('menu-toggle');
    const menu = document.getElementById('mobile-menu');
    if (!toggle || !menu) return;

    const wordmark = document.querySelector('.site-header .wordmark');
    let isOpen = false;

    const focusables = () => [
      wordmark,
      toggle,
      ...menu.querySelectorAll('a[href], button:not([disabled])'),
    ].filter(Boolean);

    function open() {
      isOpen = true;
      menu.hidden = false;
      // Force a reflow so the opacity transition runs after un-hiding
      void menu.offsetWidth;
      menu.classList.add('is-open');
      document.body.classList.add('menu-open', 'is-locked');
      toggle.setAttribute('aria-expanded', 'true');
      toggle.setAttribute('aria-label', 'Close menu');
      menu.querySelector('.mobile-nav-link')?.focus();
    }

    function close({ returnFocus = true } = {}) {
      if (!isOpen) return;
      isOpen = false;
      menu.classList.remove('is-open');
      document.body.classList.remove('menu-open', 'is-locked');
      toggle.setAttribute('aria-expanded', 'false');
      toggle.setAttribute('aria-label', 'Open menu');

      const hide = () => { if (!isOpen) menu.hidden = true; };
      if (prefersReducedMotion.matches) hide();
      else menu.addEventListener('transitionend', hide, { once: true });

      if (returnFocus) toggle.focus();
    }

    toggle.addEventListener('click', () => (isOpen ? close() : open()));

    // Close after choosing a link (let the anchor scroll happen naturally)
    menu.addEventListener('click', (e) => {
      if (e.target.closest('a[href^="#"]')) close({ returnFocus: false });
    });

    document.addEventListener('keydown', (e) => {
      if (!isOpen) return;

      if (e.key === 'Escape') {
        e.preventDefault();
        close();
        return;
      }

      // Keep Tab focus inside the header controls + menu while open
      if (e.key === 'Tab') {
        const items = focusables();
        const first = items[0];
        const last = items[items.length - 1];
        if (e.shiftKey && document.activeElement === first) {
          e.preventDefault();
          last.focus();
        } else if (!e.shiftKey && document.activeElement === last) {
          e.preventDefault();
          first.focus();
        }
      }
    });

    // If the viewport grows to desktop size, the overlay is no longer needed
    DESKTOP_NAV_QUERY.addEventListener('change', (e) => {
      if (e.matches) close({ returnFocus: false });
    });
  }

  /* ------------------------------------------------------------------------
     Active nav link: highlight the section currently in view
     ------------------------------------------------------------------------ */
  /* ------------------------------------------------------------------------
     Header dropdowns (Collections): open on hover/focus via CSS; the arrow
     button opens it on touch screens and keyboards. Escape or a click
     elsewhere closes it.
     ------------------------------------------------------------------------ */
  function initNavDropdowns() {
    const items = [...document.querySelectorAll('.nav-item.has-sub')];
    if (!items.length) return;

    const close = (item, focusToggle = false) => {
      item.classList.remove('is-open');
      const btn = item.querySelector('.nav-sub-toggle');
      btn?.setAttribute('aria-expanded', 'false');
      if (focusToggle) btn?.focus();
    };

    items.forEach((item) => {
      const btn = item.querySelector('.nav-sub-toggle');
      btn?.addEventListener('click', () => {
        const open = !item.classList.contains('is-open');
        items.forEach((other) => close(other));
        item.classList.toggle('is-open', open);
        btn.setAttribute('aria-expanded', String(open));
      });
    });

    document.addEventListener('click', (e) => {
      items.forEach((item) => { if (!item.contains(e.target)) close(item); });
    });
    document.addEventListener('keydown', (e) => {
      if (e.key !== 'Escape') return;
      const open = items.find((item) => item.classList.contains('is-open') || item.contains(document.activeElement));
      if (open) close(open, true);
    });
  }

  function initActiveNav() {
    const links = [...document.querySelectorAll('.primary-nav .nav-link[href^="#"]')];
    if (!links.length || !('IntersectionObserver' in window)) return;

    const byId = new Map(links.map((link) => [link.getAttribute('href').slice(1), link]));
    // Watch every section (not only linked ones) so the highlight clears in
    // sections without a menu item, e.g. Services or Gallery
    const sections = [
      ...document.querySelectorAll('main > section[id]'),
      ...[...byId.keys()].map((id) => document.getElementById(id)),
    ].filter((el, i, all) => el && all.indexOf(el) === i);

    const setActive = (id) => {
      links.forEach((link) => {
        const active = link === byId.get(id);
        link.classList.toggle('is-active', active);
        if (active) link.setAttribute('aria-current', 'location');
        else link.removeAttribute('aria-current');
      });
    };

    // A section is "current" when it crosses a band just below the header
    const observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) setActive(entry.target.id);
        });
      },
      { rootMargin: '-40% 0px -55% 0px' }
    );

    sections.forEach((section) => observer.observe(section));
  }

  /* ------------------------------------------------------------------------
     Hero entrance: start on the next frame. Fonts load without blocking
     (they swap in when ready), so waiting for them would only keep the
     headline hidden longer on slow connections.
     ------------------------------------------------------------------------ */
  function initHeroEntrance() {
    requestAnimationFrame(() => setTimeout(() => document.body.classList.add('is-loaded'), 30));
  }

  /* ------------------------------------------------------------------------
     Scroll reveal: fades .reveal elements in as they enter the viewport
     ------------------------------------------------------------------------ */
  function initReveal() {
    const items = document.querySelectorAll('.reveal');
    if (!items.length) return;

    if (!('IntersectionObserver' in window)) {
      items.forEach((el) => el.classList.add('is-visible'));
      return;
    }

    const observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (!entry.isIntersecting) return;
          entry.target.classList.add('is-visible');
          observer.unobserve(entry.target);
        });
      },
      { rootMargin: '0px 0px -8% 0px', threshold: 0.12 }
    );

    items.forEach((el) => observer.observe(el));
  }

  /* ------------------------------------------------------------------------
     Gallery: category filters + accessible lightbox
     ------------------------------------------------------------------------ */
  /* ------------------------------------------------------------------------
     Live gallery: replace the built-in tiles with the designs managed in the
     admin area (admin/gallery.php). Built with DOM methods (never innerHTML)
     so titles typed in the admin can't inject markup. If the request fails,
     the built-in tiles in the page HTML stay as they are.
     ------------------------------------------------------------------------ */
  async function loadLiveGallery() {
    const grid = document.getElementById('gallery-grid');
    if (!grid || location.protocol === 'file:') return;
    try {
      const controller = new AbortController();
      const timer = setTimeout(() => controller.abort(), 4000);
      const res = await fetch('api/gallery.php', { headers: { Accept: 'application/json' }, signal: controller.signal });
      clearTimeout(timer);
      if (!res.ok) return;
      const { items } = await res.json();
      if (!Array.isArray(items) || !items.length) return;

      const el = (tag, cls, text) => {
        const n = document.createElement(tag);
        if (cls) n.className = cls;
        if (text) n.textContent = text;
        return n;
      };
      grid.replaceChildren(...items.map((it) => {
        const li = el('li', 'g-item' + (it.size === 'tall' ? ' g-item--tall' : it.size === 'wide' ? ' g-item--wide' : ''));
        li.dataset.category = it.category;
        const btn = el('button', 'g-item__btn');
        btn.type = 'button';
        const slot = el('span', 'img-slot' + (it.isSample ? ' is-sample' : ''));
        const img = document.createElement('img');
        // loading must be set BEFORE src, or the browser starts downloading immediately
        img.loading = 'lazy';
        img.decoding = 'async';
        Object.assign(img, { width: it.width, height: it.height });
        img.src = it.src;
        img.alt = (it.isSample ? 'Sample image: ' : '') + it.alt;
        img.style.objectPosition = it.focus;
        slot.append(img);
        const cap = el('span', 'g-item__caption');
        cap.append(el('span', 'g-item__cat', it.categoryLabel), el('span', 'g-item__title', it.title));
        btn.append(slot, cap);
        li.append(btn);
        return li;
      }));
      return true;
    } catch (err) {
      console.info('Live gallery unavailable; showing built-in designs.', err);
    }
    return false;
  }

  let refreshGallery = () => {};

  function initGallery() {
    const grid = document.getElementById('gallery-grid');
    if (!grid) return;

    // Always read the current tiles: the live gallery may replace them after load
    const getItems = () => [...grid.querySelectorAll('.g-item')];
    let currentFilter = 'all';
    const buttons = [...document.querySelectorAll('.filter-btn[data-filter]')];
    const status = document.getElementById('gallery-status');
    const count = getItems().length;
    if (status) status.textContent = `Showing ${count} ${count === 1 ? 'design' : 'designs'}`;

    const labelFor = (filter) =>
      buttons.find((b) => b.dataset.filter === filter)?.textContent.trim() ?? filter;

    // Collection pages show only their own categories, e.g.
    // <body data-gallery-include="bridal,aso-ebi,ready-to-wear">
    const include = (document.body.dataset.galleryInclude || '').split(',').map((s) => s.trim()).filter(Boolean);
    const allowed = (cat) => !include.length || include.includes(cat);
    buttons.forEach((b) => { if (b.dataset.filter !== 'all' && !allowed(b.dataset.filter)) b.hidden = true; });
    const filterBar = document.querySelector('.gallery__filters');
    if (filterBar && include.length === 1) filterBar.hidden = true;   // one category: no filters needed

    /* ---- Filtering ---- */
    function applyFilter(filter) {
      if (!buttons.some((b) => b.dataset.filter === filter && !b.hidden)) filter = 'all';
      currentFilter = filter;

      buttons.forEach((b) => b.setAttribute('aria-pressed', String(b.dataset.filter === filter)));

      let shown = 0;
      getItems().forEach((item) => {
        const match = allowed(item.dataset.category) && (filter === 'all' || item.dataset.category === filter);
        item.hidden = !match;
        item.classList.remove('is-entering');
        if (match) {
          shown += 1;
          if (!prefersReducedMotion.matches) {
            void item.offsetWidth; // restart the entrance animation
            item.classList.add('is-entering');
          }
        }
      });

      const noun = shown === 1 ? 'design' : 'designs';
      status.textContent = filter === 'all'
        ? `Showing ${shown} ${noun}`
        : `Showing ${shown} ${noun} in ${labelFor(filter)}`;

      fillGaps();
    }

    /* ---- Gap filling ----
       Tile shapes are fixed, so some combinations of visible designs leave
       empty cells at the end of the grid. This counts empty cells and nudges
       tiles (grow a standard tile to two rows, or shrink a tall one) until
       the grid is complete. Runs after every filter and on resize. */
    function countGaps() {
      const items = getItems().filter((i) => !i.hidden);
      if (!items.length) return 0;
      const cs = getComputedStyle(grid);
      const cols = cs.gridTemplateColumns.split(' ').length;
      const rowH = parseFloat(cs.gridAutoRows);
      const gap = parseFloat(cs.rowGap) || 0;
      const top = grid.getBoundingClientRect().top;
      const colW = (grid.clientWidth - gap * (cols - 1)) / cols;
      let cells = 0;
      let rows = 0;
      items.forEach((i) => {
        const b = i.getBoundingClientRect();
        cells += Math.round((b.height + gap) / (rowH + gap)) * Math.round((b.width + gap) / (colW + gap));
        rows = Math.max(rows, Math.round((b.bottom - top + gap) / (rowH + gap)));
      });
      return rows * cols - cells;
    }

    function fillGaps() {
      grid.style.gridTemplateColumns = '';
      const tune = () => {
        getItems().forEach((i) => i.classList.remove('is-grown', 'is-shrunk'));
        return adjustTiles();
      };
      let gaps = tune();
      if (!gaps) return;

      // Small sets (e.g. 3 bridal designs) can't fill 4 columns: try fewer
      // columns and keep whichever arrangement leaves the fewest gaps.
      const startCols = getComputedStyle(grid).gridTemplateColumns.split(' ').length;
      let best = { cols: 0, gaps };
      for (let cols = startCols - 1; cols >= 2 && best.gaps > 0; cols -= 1) {
        grid.style.gridTemplateColumns = `repeat(${cols}, minmax(0, 1fr))`;
        const g = tune();
        if (g < best.gaps) best = { cols, gaps: g };
      }
      grid.style.gridTemplateColumns = best.cols ? `repeat(${best.cols}, minmax(0, 1fr))` : '';
      tune();
    }

    function adjustTiles() {
      let gaps = countGaps();
      if (!gaps) return 0;
      const visible = () => getItems().filter((i) => !i.hidden);
      // Try changes from the end of the grid backwards; keep any that reduce gaps
      for (let attempt = 0; attempt < 12 && gaps > 0; attempt += 1) {
        let improved = false;
        for (const item of visible().reverse()) {
          const isTall = item.classList.contains('g-item--tall') && !item.classList.contains('is-shrunk');
          const isStd = !item.classList.contains('g-item--tall') && !item.classList.contains('g-item--wide') && !item.classList.contains('is-grown');
          if (!isTall && !isStd) continue;
          const cls = isTall ? 'is-shrunk' : 'is-grown';
          item.classList.add(cls);
          const next = countGaps();
          if (next < gaps) { gaps = next; improved = true; break; }
          item.classList.remove(cls);
        }
        if (!improved) break;
      }
      return gaps;
    }

    let resizeTimer;
    window.addEventListener('resize', () => {
      clearTimeout(resizeTimer);
      resizeTimer = setTimeout(fillGaps, 200);
    });

    buttons.forEach((btn) => btn.addEventListener('click', () => applyFilter(btn.dataset.filter)));

    // Lets the live gallery re-apply the active filter after swapping in new tiles
    refreshGallery = () => applyFilter(currentFilter);

    // Start on the page's own category (bridal, aso-ebi, men…) or one passed
    // in the link, e.g. collections?filter=men#gallery
    const initialFilter = new URLSearchParams(location.search).get('filter') || document.body.dataset.galleryFilter;
    applyFilter(initialFilter || 'all');   // also applies the page's category limits

    // Links elsewhere on the page (e.g. "Explore Aso-Ebi") pre-select a category
    document.addEventListener('click', (e) => {
      const link = e.target.closest('a[data-gallery-filter]');
      if (link) applyFilter(link.dataset.galleryFilter);
    });

    /* ---- Lightbox ---- */
    const dialog = document.getElementById('lightbox');
    if (!dialog || typeof dialog.showModal !== 'function') return;

    const figure = dialog.querySelector('.lightbox__figure');
    const media = document.getElementById('lightbox-media');
    const catEl = document.getElementById('lightbox-cat');
    const titleEl = document.getElementById('lightbox-title');
    const countEl = document.getElementById('lightbox-count');
    const enquire = document.getElementById('lightbox-enquire');

    let visible = [];
    let index = 0;
    let trigger = null;

    const ratioOf = (item) => {
      if (item.classList.contains('g-item--tall')) return 2 / 3;
      if (item.classList.contains('g-item--wide')) return 3 / 2;
      return 4 / 5;
    };

    function render() {
      const item = visible[index];
      const slot = item.querySelector('.img-slot');
      const title = item.querySelector('.g-item__title').textContent.trim();
      const category = item.querySelector('.g-item__cat').textContent.trim();

      // Copy the tile's image (or placeholder); prefer a larger file if provided
      const clone = slot.cloneNode(true);
      const img = clone.querySelector('img');
      if (img) {
        if (img.dataset.full) img.src = img.dataset.full;
        img.removeAttribute('loading');
      }
      media.replaceChildren(clone);

      figure.style.setProperty('--ar', img?.naturalWidth ? img.naturalWidth / img.naturalHeight : ratioOf(item));
      catEl.textContent = category;
      titleEl.textContent = title;
      countEl.textContent = `${index + 1} / ${visible.length}`;

      enquire.dataset.service = item.dataset.category;
      enquire.dataset.design = title;
      enquire.href = contactUrl(item.dataset.category, title);
      enquire.innerHTML = `Enquire<span class="visually-hidden"> about ${title}</span>`;

      const single = visible.length < 2;
      dialog.querySelectorAll('.lightbox__nav').forEach((b) => { b.hidden = single; });
    }

    function open(item) {
      visible = getItems().filter((i) => !i.hidden);
      index = Math.max(0, visible.indexOf(item));
      trigger = item.querySelector('.g-item__btn');
      render();
      dialog.showModal();
      document.body.classList.add('is-locked');
    }

    const step = (delta) => {
      index = (index + delta + visible.length) % visible.length;
      render();
    };

    grid.addEventListener('click', (e) => {
      const btn = e.target.closest('.g-item__btn');
      if (btn) open(btn.closest('.g-item'));
    });

    dialog.addEventListener('click', (e) => {
      const action = e.target.closest('[data-lightbox]')?.dataset.lightbox;
      if (action === 'close') dialog.close();
      else if (action === 'prev') step(-1);
      else if (action === 'next') step(1);
      else if (e.target === dialog || e.target.classList.contains('lightbox__inner')) dialog.close();
    });

    // Enquire link: close the viewer, then let the anchor scroll to the form
    enquire.addEventListener('click', () => dialog.close());

    dialog.addEventListener('keydown', (e) => {
      if (e.key === 'ArrowLeft') { e.preventDefault(); step(-1); }
      if (e.key === 'ArrowRight') { e.preventDefault(); step(1); }
    });

    dialog.addEventListener('close', () => {
      document.body.classList.remove('is-locked');
      trigger?.focus({ preventScroll: true });
    });

    // Swipe left / right on touch screens
    let startX = null;
    media.addEventListener('pointerdown', (e) => { startX = e.clientX; });
    media.addEventListener('pointerup', (e) => {
      if (startX === null || visible.length < 2) return;
      const dx = e.clientX - startX;
      startX = null;
      if (Math.abs(dx) > 50) step(dx < 0 ? 1 : -1);
    });
  }

  /* ------------------------------------------------------------------------
     Testimonials carousel: prev/next, dots, arrow keys, swipe.
     No auto-rotation, so content never moves while someone is reading.
     ------------------------------------------------------------------------ */
  let refreshCarousels = () => {};

  function initCarousel() {
    const rebuilders = [];
    document.querySelectorAll('.carousel').forEach((root) => {
      const track = root.querySelector('.carousel__track');
      const dotsWrap = root.querySelector('.carousel__dots');
      const controls = root.querySelector('.carousel__controls');
      if (!track) return;

      let slides = [];
      let dots = [];
      let index = 0;

      function go(i) {
        if (!slides.length) return;
        index = (i + slides.length) % slides.length;
        track.style.transform = `translateX(${index * -100}%)`;
        slides.forEach((slide, n) => {
          const active = n === index;
          slide.setAttribute('aria-label', `${n + 1} of ${slides.length}`);
          slide.toggleAttribute('inert', !active); // off-screen slides are not focusable
          slide.setAttribute('aria-hidden', String(!active));
        });
        dots.forEach((dot, n) => dot.setAttribute('aria-current', String(n === index)));
      }

      // (Re)reads the slides and rebuilds the dots: runs at start-up and again
      // if live testimonials replace the placeholder slides.
      function build() {
        slides = [...root.querySelectorAll('.carousel__slide')];
        if (controls) controls.hidden = slides.length < 2;
        dotsWrap?.replaceChildren();
        dots = slides.length < 2 ? [] : slides.map((_, i) => {
          const dot = document.createElement('button');
          dot.type = 'button';
          dot.className = 'carousel__dot';
          dot.setAttribute('aria-label', `Show testimonial ${i + 1} of ${slides.length}`);
          dot.addEventListener('click', () => go(i));
          dotsWrap?.appendChild(dot);
          return dot;
        });
        go(0);
      }

      root.querySelector('[data-carousel="prev"]')?.addEventListener('click', () => go(index - 1));
      root.querySelector('[data-carousel="next"]')?.addEventListener('click', () => go(index + 1));

      root.addEventListener('keydown', (e) => {
        if (slides.length < 2) return;
        if (e.key === 'ArrowLeft') { e.preventDefault(); go(index - 1); }
        if (e.key === 'ArrowRight') { e.preventDefault(); go(index + 1); }
      });

      let startX = null;
      track.addEventListener('pointerdown', (e) => { startX = e.clientX; });
      track.addEventListener('pointerup', (e) => {
        if (startX === null || slides.length < 2) return;
        const dx = e.clientX - startX;
        startX = null;
        if (Math.abs(dx) > 50) go(index + (dx < 0 ? 1 : -1));
      });

      build();
      rebuilders.push(build);
    });
    refreshCarousels = () => rebuilders.forEach((fn) => fn());
  }

  /* ------------------------------------------------------------------------
     In Motion: videos of clients wearing Reign Fashion designs, published
     from the admin area (admin/videos.php) to the Home and Collections pages.
     The section stays hidden until there is at least one video. Videos load
     only when played, and only one plays at a time.
     ------------------------------------------------------------------------ */
  async function loadDesignVideos() {
    const section = document.querySelector('[data-design-videos]');
    const grid = section && section.querySelector('.motion__grid');
    if (!grid || location.protocol === 'file:') return;
    try {
      const res = await fetch('api/videos.php', { headers: { Accept: 'application/json' } });
      if (!res.ok) return;
      const { items } = await res.json();
      if (!Array.isArray(items) || !items.length) return;

      const mk = (tag, cls, text) => {
        const n = document.createElement(tag);
        if (cls) n.className = cls;
        if (text) n.textContent = text;
        return n;
      };
      const svgNS = 'http://www.w3.org/2000/svg';
      const playIcon = () => {
        const svg = document.createElementNS(svgNS, 'svg');
        svg.setAttribute('viewBox', '0 0 24 24');
        svg.setAttribute('aria-hidden', 'true');
        svg.setAttribute('focusable', 'false');
        const path = document.createElementNS(svgNS, 'path');
        path.setAttribute('d', 'M7 4.5v15l13-7.5z');
        svg.append(path);
        return svg;
      };

      grid.replaceChildren(...items.map((v) => {
        const li = mk('li', 'motion-card');
        const media = mk('div', 'motion-card__media');
        const video = document.createElement('video');
        video.preload = 'none';
        video.playsInline = true;
        video.setAttribute('playsinline', '');
        if (v.poster) video.poster = v.poster;
        const source = document.createElement('source');
        source.src = v.src;
        if (v.type) source.type = v.type;
        video.append(source);
        const label = `${v.categoryLabel || 'Reign Fashion'} video`;
        video.setAttribute('aria-label', v.caption ? `${label}: ${v.caption}` : label);

        const play = mk('button', 'motion-card__play');
        play.type = 'button';
        play.setAttribute('aria-label', `Play ${label}${v.caption ? `: ${v.caption}` : ''}${v.duration ? ` (${v.duration})` : ''}`);
        const icon = mk('span', 'motion-card__play-icon');
        icon.append(playIcon());
        play.append(icon);
        play.addEventListener('click', () => {
          li.classList.add('is-playing');
          video.controls = true;
          video.play().catch(() => {});
          video.focus();
        });
        // Only one video plays at a time
        video.addEventListener('play', () => {
          grid.querySelectorAll('video').forEach((other) => { if (other !== video) other.pause(); });
        });

        media.append(video, play);
        if (v.duration) media.append(mk('span', 'motion-card__duration', v.duration));
        li.append(media);
        if (v.categoryLabel) li.append(mk('p', 'motion-card__meta', v.categoryLabel));
        if (v.caption) li.append(mk('p', 'motion-card__caption', v.caption));
        return li;
      }));
      section.hidden = false;
    } catch (err) {
      console.info('Design videos unavailable.', err);
    }
  }

  /* ------------------------------------------------------------------------
     Live testimonials: genuine client quotes published from the admin area
     (admin/testimonials.php) replace the placeholder slides. Built with DOM
     methods only. If none are published yet, the placeholders stay.
     ------------------------------------------------------------------------ */
  async function loadLiveTestimonials() {
    const track = document.querySelector('.testimonials .carousel__track');
    if (!track || location.protocol === 'file:') return false;
    try {
      const res = await fetch('api/testimonials.php', { headers: { Accept: 'application/json' } });
      if (!res.ok) return false;
      const { items } = await res.json();
      if (!Array.isArray(items) || !items.length) return false;

      const mk = (tag, cls, text) => {
        const n = document.createElement(tag);
        if (cls) n.className = cls;
        if (text) n.textContent = text;
        return n;
      };
      track.replaceChildren(...items.map((t) => {
        const li = mk('li', 'carousel__slide');
        li.setAttribute('role', 'group');
        li.setAttribute('aria-roledescription', 'slide');
        const fig = mk('figure', 'testimonial');
        const quote = mk('blockquote', 'testimonial__quote');
        quote.append(mk('p', '', t.quote));
        const cite = mk('figcaption', 'testimonial__cite');
        cite.append(mk('span', 'testimonial__name', t.name), mk('span', 'testimonial__meta', t.serviceLabel));
        fig.append(quote, cite);
        li.append(fig);
        return li;
      }));
      return true;
    } catch (err) {
      console.info('Live testimonials unavailable; showing placeholders.', err);
      return false;
    }
  }

  /* ------------------------------------------------------------------------
     Contact details: fill every [data-contact] element from SITE_CONFIG
     ------------------------------------------------------------------------ */
  const el = (tag, props = {}, text = '') => {
    const node = Object.assign(document.createElement(tag), props);
    if (text) node.textContent = text;
    return node;
  };

  const pending = (text = 'To be confirmed') => el('span', { className: 'is-pending' }, text);

  function initContactDetails() {
    const { phones = [], whatsappNumber, email, address, hours, social } = SITE_CONFIG;

    const fillers = {
      // One line per number; the WhatsApp number is marked
      phone: () => {
        if (!phones.length) return pending();
        const frag = document.createDocumentFragment();
        phones.forEach(({ display, tel }) => {
          const line = el('span', { className: 'contact-phone' });
          line.append(el('a', { href: `tel:${tel}` }, display));
          if (tel.replace(/\D/g, '') === whatsappNumber) {
            line.append(el('span', { className: 'contact-phone__tag' }, 'WhatsApp'));
          }
          frag.append(line);
        });
        return frag;
      },
      email: () => (email ? el('a', { href: `mailto:${email}` }, email) : pending()),
      address: () => {
        if (!address) return pending();
        const frag = document.createDocumentFragment();
        frag.append(el('span', {}, address));
        const maps = el('a', {
          className: 'contact-directions',
          href: `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(address)}`,
          target: '_blank',
          rel: 'noopener',
        }, 'Get directions');
        frag.append(maps);
        return frag;
      },
      hours: () => (hours ? el('span', {}, hours) : pending()),
      // Hidden until the owner saves payment options in the admin
      payment: (node) => {
        const { methods = [], terms = '', accounts = [] } = SITE_CONFIG.payment ?? {};
        const row = node.closest('li');
        if (row) row.hidden = !methods.length && !accounts.length;
        const frag = document.createDocumentFragment();
        if (methods.length) frag.append(el('span', {}, methods.join(' · ')));
        accounts.forEach(({ bank, name, number }) => {
          const acct = el('span', { className: 'contact-bank' });
          acct.append(el('strong', {}, bank), el('span', {}, name), el('span', { className: 'contact-bank__number' }, number));
          frag.append(acct);
        });
        if (terms) frag.append(el('span', { className: 'contact-pay-note' }, terms));
        if (accounts.length) {
          frag.append(el('span', { className: 'contact-pay-note' },
            `Only pay into ${accounts.length > 1 ? 'these accounts' : 'this account'}. If anyone asks you to pay elsewhere, call us first.`));
        }
        return frag;
      },
      social: () => {
        const frag = document.createDocumentFragment();
        const ICONS = {
          instagram: '<svg class="social-icon" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><rect width="20" height="20" x="2" y="2" rx="5" ry="5"/><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"/><line x1="17.5" x2="17.51" y1="6.5" y2="6.5"/></svg>',
          facebook: '<svg class="social-icon" viewBox="0 0 24 24" width="20" height="20" fill="currentColor" aria-hidden="true" focusable="false"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/></svg>',
          tiktok: '<svg class="social-icon" viewBox="0 0 24 24" width="20" height="20" fill="currentColor" aria-hidden="true" focusable="false"><path d="M19.59 6.69a4.83 4.83 0 0 1-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 0 1-5.2 1.74 2.89 2.89 0 0 1 2.31-4.64c.298-.002.595.042.88.13V9.4a6.33 6.33 0 0 0-1-.08A6.34 6.34 0 0 0 3 15.66a6.34 6.34 0 0 0 10.86 4.43c.03-.03.06-.06.09-.09V11.8a8.16 8.16 0 0 0 5.64 2.2V10.5a4.88 4.88 0 0 1-3-1.07 4.78 4.78 0 0 1-1.46-2.74h-.01z"/></svg>',
        };
        [['instagram', 'Instagram'], ['facebook', 'Facebook'], ['tiktok', 'TikTok']].forEach(([key, label]) => {
          const li = el('li');
          const { url = '', name = '' } = social?.[key] ?? {};
          const handleLabel = name ? `${label}: ${name}` : label;
          if (url) {
            const a = el('a', {
              href: url,
              target: '_blank',
              rel: 'noopener noreferrer',
              className: `social-link social-link--${key}`,
            });
            a.setAttribute('aria-label', `${handleLabel} (opens in a new tab)`);
            a.setAttribute('title', handleLabel);
            a.innerHTML = `${ICONS[key]}<span class="visually-hidden">${label}</span>`;
            li.append(a);
          } else if (name) {
            const span = el('span', { className: `social-link social-link--${key} is-pending` });
            span.setAttribute('title', handleLabel);
            span.innerHTML = `${ICONS[key]}<span class="visually-hidden">${handleLabel}</span>`;
            li.append(span);
          } else {
            li.append(pending(`${label} (link to be added)`));
          }
          frag.append(li);
        });
        return frag;
      },
    };

    document.querySelectorAll('[data-contact]').forEach((node) => {
      const fill = fillers[node.dataset.contact];
      if (fill) node.replaceChildren(fill(node));
    });
  }

  /* ------------------------------------------------------------------------
     WhatsApp: links are only shown once a valid number is configured
     ------------------------------------------------------------------------ */
  let waNumber = '';
  const refreshWaNumber = () => {
    waNumber = /^\d{8,15}$/.test(SITE_CONFIG.whatsappNumber) ? SITE_CONFIG.whatsappNumber : '';
  };
  refreshWaNumber();
  const waHref = (message) => `https://wa.me/${waNumber}?text=${encodeURIComponent(message)}`;

  const SERVICE_PHRASES = {
    bridal: 'bridal and wedding gowns',
    'aso-ebi': 'Aso-Ebi and traditional outfits',
    'ready-to-wear': 'ready-to-wear outfits',
    kids: "kiddies' wear",
    men: "men's wear",
    custom: 'a custom design',
    academy: 'training at the Reign Fashion Academy',
    other: 'your services',
  };

  function waGreeting(service, design) {
    const topic = SERVICE_PHRASES[service] ?? 'your fashion design services';
    let msg = `Hello Reign Fashion, I would like to make an enquiry about ${topic}.`;
    if (design) msg += ` I'm interested in "${design}".`;
    return msg;
  }

  // Remembers the chosen service/design, so re-rendering (e.g. when live
  // settings arrive) keeps the tailored WhatsApp message.
  const waChoice = { service: '', design: '' };

  function updateWhatsAppLinks(service = waChoice.service, design = waChoice.design) {
    waChoice.service = service;
    waChoice.design = design;
    if (!waNumber) return;
    document.querySelectorAll('a[data-whatsapp]').forEach((a) => {
      a.href = waHref(waGreeting(service, design));
    });
  }

  function initWhatsApp() {
    refreshWaNumber();
    // Links only appear with a valid number; otherwise the "to be confirmed" note shows
    document.querySelectorAll('[data-whatsapp]').forEach((a) => { a.hidden = !waNumber; });
    document.querySelectorAll('[data-whatsapp-pending]').forEach((n) => { n.hidden = Boolean(waNumber); });
    if (!waNumber && SITE_CONFIG.whatsappNumber) {
      console.warn('SITE_CONFIG.whatsappNumber should be digits only in international format, e.g. 2348012345678');
    }
    updateWhatsAppLinks();
  }

  /* ------------------------------------------------------------------------
     Academy schedule & fees
     ------------------------------------------------------------------------ */
  function initAcademy() {
    const a = SITE_CONFIG.academy ?? {};
    let allSet = true;
    document.querySelectorAll('[data-academy]').forEach((dd) => {
      const value = (a[dd.dataset.academy] ?? '').trim();
      dd.textContent = value || 'To be confirmed';
      dd.classList.toggle('is-pending', !value);
      if (!value) allSet = false;
    });
    document.querySelectorAll('[data-academy-pending]').forEach((tag) => { tag.hidden = allSet; });
  }

  /* ------------------------------------------------------------------------
     Live settings: fetch what the owner saved in the admin area, then
     re-render contact details, WhatsApp links and Academy info. The page
     has already rendered with the built-in values, so a slow or failed
     request never leaves anything blank.
     ------------------------------------------------------------------------ */
  async function loadLiveSettings() {
    if (!SITE_CONFIG.settingsEndpoint || location.protocol === 'file:') return;
    try {
      const res = await fetch(SITE_CONFIG.settingsEndpoint, { headers: { Accept: 'application/json' } });
      if (!res.ok) return;
      const { settings } = await res.json();
      if (!settings) return;
      ['whatsappNumber', 'phones', 'email', 'address', 'hours', 'social', 'academy', 'payment'].forEach((key) => {
        if (settings[key] !== undefined) SITE_CONFIG[key] = settings[key];
      });
      initContactDetails();
      initWhatsApp();
      initAcademy();
    } catch (err) {
      console.info('Live settings unavailable; using built-in values.', err);
    }
  }

  /* ------------------------------------------------------------------------
     Enquiry form: validation, uploads, honest submission states
     ------------------------------------------------------------------------ */
  /* ------------------------------------------------------------------------
     Academy registration form (home page) → api/academy-register.php
     ------------------------------------------------------------------------ */
  function initAcademyForm() {
    const form = document.getElementById('academy-form');
    if (!form) return;
    const status = document.getElementById('academy-status');
    const submit = form.querySelector('.reg-form__submit');
    const $ = (sel) => form.querySelector(sel);
    let attempted = false;

    const FIELDS = {
      name: ['#a-name', () => ($('#a-name').value.trim().length >= 2 ? '' : 'Please enter your full name.')],
      email: ['#a-email', () => (/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test($('#a-email').value.trim()) ? '' : 'Please enter a valid email address.')],
      phone: ['#a-phone', () => {
        const d = $('#a-phone').value.replace(/\D/g, '');
        if (!d) return 'Please enter a phone number so we can reach you.';
        return d.length >= 7 && d.length <= 15 ? '' : 'Please enter a valid phone number.';
      }],
      level: ['#a-level', () => ($('#a-level').value ? '' : 'Please choose the level that suits you.')],
      schedule: ['input[name="schedule"]', () => (form.querySelector('input[name="schedule"]:checked') ? '' : 'Please choose when you would prefer to attend.')],
      consent: ['input[name="consent"]', () => ($('input[name="consent"]').checked ? '' : 'Please confirm that we may use your details to contact you.')],
    };

    function setError(key, msg) {
      const control = $(FIELDS[key][0]);
      const field = control.closest('.field');
      field.classList.toggle('is-invalid', Boolean(msg));
      const err = field.querySelector('.field__error');
      if (err) err.textContent = msg;
      form.querySelectorAll(`[name="${control.name}"]`).forEach((c) => (msg ? c.setAttribute('aria-invalid', 'true') : c.removeAttribute('aria-invalid')));
      return msg ? control : null;
    }
    const validate = () => Object.keys(FIELDS).map((k) => setError(k, FIELDS[k][1]())).filter(Boolean);

    function show(type, title, lines = []) {
      status.className = `form-status form-status--${type}`;
      status.replaceChildren(el('strong', {}, title), ...lines.map((l) => el('p', {}, l)));
      status.hidden = false;
      status.focus({ preventScroll: true });
      status.scrollIntoView({ behavior: prefersReducedMotion.matches ? 'auto' : 'smooth', block: 'nearest' });
    }

    form.addEventListener('input', () => { if (attempted) validate(); });
    form.addEventListener('change', () => { if (attempted) validate(); });

    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      attempted = true;
      if ($('#a-website').value) return;
      const invalid = validate();
      if (invalid.length) {
        show('error', 'Please check the form.', [`${invalid.length === 1 ? 'One field needs' : `${invalid.length} fields need`} your attention.`]);
        invalid[0].focus();
        return;
      }
      submit.disabled = true;
      submit.textContent = 'Sending…';
      try {
        const res = await fetch('api/academy-register.php', { method: 'POST', body: new FormData(form), headers: { Accept: 'application/json' } });
        const data = await res.json().catch(() => ({}));
        if (res.status === 400 && data.errors) {
          const bad = Object.entries(data.errors).map(([k, msg]) => (FIELDS[k] ? setError(k, msg) : null)).filter(Boolean);
          show('error', 'Please check the form.', Object.values(data.errors));
          bad[0]?.focus();
          return;
        }
        if (!res.ok) throw new Error(data.error || `HTTP ${res.status}`);
        const first = $('#a-name').value.trim().split(/\s+/)[0];
        form.reset();
        attempted = false;
        show('success', `Thank you, ${first}. Your registration has been received.`, [
          ...(data.ref ? [`Your reference is ${data.ref}.`] : []),
          'The Academy team will contact you about classes, fees and the next intake.',
        ]);
      } catch (err) {
        console.error('Academy registration failed:', err);
        show('error', 'Sorry, your registration could not be sent.', [
          err.message && !err.message.startsWith('HTTP') ? err.message : 'Please check your connection and try again, or contact us on WhatsApp.',
        ]);
      } finally {
        submit.disabled = false;
        submit.textContent = 'Register';
      }
    });
  }

  function initEnquiryForm() {
    const form = document.getElementById('enquiry-form');
    if (!form) return;

    const $ = (sel) => form.querySelector(sel);
    const status = document.getElementById('form-status');
    const submitBtn = $('.enquiry__submit');
    const service = $('#f-service');
    const message = $('#f-message');
    const phone = $('#f-phone');
    const date = $('#f-date');
    const fileInput = $('#f-files');
    const fileList = document.getElementById('f-files-list');
    const dropZone = $('.upload');

    const MAX_FILES = 3;
    const MAX_BYTES = 5 * 1024 * 1024;
    const ACCEPTED = ['image/jpeg', 'image/png', 'image/webp'];

    let files = [];
    let design = '';
    let attempted = false;

    // No past dates for the occasion
    const now = new Date();
    const today = new Date(now.getTime() - now.getTimezoneOffset() * 60000).toISOString().slice(0, 10);
    date.min = today;

    const serviceLabel = () => service.selectedOptions[0]?.value ? service.selectedOptions[0].textContent : '';
    const methodValue = () => form.querySelector('input[name="contact_method"]:checked')?.value ?? '';
    const methodLabel = () => form.querySelector('input[name="contact_method"]:checked')?.parentElement.textContent.trim() ?? '';

    /* ---- Pre-select a service from "Enquire" / "Book" links site-wide ---- */
    document.addEventListener('click', (e) => {
      const link = e.target.closest('a[data-service]');
      if (!link || link.closest('form')) return;
      if ([...service.options].some((o) => o.value === link.dataset.service)) {
        service.value = link.dataset.service;
      }
      design = link.dataset.design ?? '';
      if (design && !message.value.trim()) message.value = `I'm interested in "${design}". `;
      updateWhatsAppLinks(service.value, design);
      if (attempted) validate();
    });

    service.addEventListener('change', () => updateWhatsAppLinks(service.value, design));

    /* ---- Arriving from another page: contact?service=…&design=… ---- */
    const params = new URLSearchParams(location.search);
    const fromService = params.get('service');
    const fromDesign = (params.get('design') || '').slice(0, 150);
    if (fromService && [...service.options].some((o) => o.value === fromService)) {
      service.value = fromService;
    }
    if (fromDesign) {
      design = fromDesign;
      if (!message.value.trim()) message.value = `I'm interested in "${design}". `;
    }
    if (fromService || fromDesign) updateWhatsAppLinks(service.value, design);

    /* ---- Phone becomes required for phone / WhatsApp contact ---- */
    const phoneReqMark = form.querySelector('.req--conditional');
    form.querySelectorAll('input[name="contact_method"]').forEach((r) => r.addEventListener('change', () => {
      const needsPhone = ['phone', 'whatsapp'].includes(methodValue());
      phoneReqMark.hidden = !needsPhone;
      phone.required = needsPhone;
    }));

    /* ---- Uploads (choose or drag & drop; removable) ---- */
    function syncFiles() {
      if (typeof DataTransfer === 'function') {
        const dt = new DataTransfer();
        files.forEach((f) => dt.items.add(f));
        fileInput.files = dt.files;
      }
      fileList.replaceChildren(...files.map((f, i) => {
        const li = el('li');
        li.append(el('span', {}, `${f.name} (${(f.size / 1024 / 1024).toFixed(1)} MB)`));
        const rm = el('button', { type: 'button', className: 'upload__remove' }, 'Remove');
        rm.setAttribute('aria-label', `Remove ${f.name}`);
        rm.addEventListener('click', () => {
          files.splice(i, 1);
          syncFiles();
          if (attempted) validate();
          fileInput.focus();
        });
        li.append(rm);
        return li;
      }));
    }

    function addFiles(list) {
      files = [...files, ...list];
      syncFiles();
      validate({ only: 'files' });
    }

    fileInput.addEventListener('change', () => addFiles([...fileInput.files].filter((f) => !files.includes(f))));

    ['dragenter', 'dragover'].forEach((type) => dropZone.addEventListener(type, (e) => {
      e.preventDefault();
      dropZone.classList.add('is-dragover');
    }));
    ['dragleave', 'drop'].forEach((type) => dropZone.addEventListener(type, () => dropZone.classList.remove('is-dragover')));
    dropZone.addEventListener('drop', (e) => {
      e.preventDefault();
      addFiles([...(e.dataTransfer?.files ?? [])]);
    });

    /* ---- Validation ---- */
    const rules = [
      ['#f-name', () => {
        const v = $('#f-name').value.trim();
        if (!v) return 'Please enter your full name.';
        if (v.length < 2) return 'Please enter your full name.';
        return '';
      }],
      ['#f-email', () => {
        const v = $('#f-email').value.trim();
        if (!v) return 'Please enter your email address.';
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(v)) return 'Please enter a valid email address, e.g. name@example.com.';
        return '';
      }],
      ['#f-phone', () => {
        const v = phone.value.trim();
        if (!v) return phone.required ? `Please enter a phone number so we can reach you by ${methodLabel().toLowerCase()}.` : '';
        const digits = v.replace(/\D/g, '');
        if (!/^\+?[\d\s().-]+$/.test(v) || digits.length < 7 || digits.length > 15) return 'Please enter a valid phone number, including your country code.';
        return '';
      }],
      ['input[name="contact_method"]', () => (methodValue() ? '' : 'Please choose how you would like us to contact you.')],
      ['#f-service', () => (service.value ? '' : 'Please select the service you are interested in.')],
      ['#f-date', () => (date.value && date.value < today ? 'Please choose a date that is today or later.' : '')],
      ['#f-message', () => {
        const v = message.value.trim();
        if (!v) return 'Please tell us a little about the outfit you have in mind.';
        if (v.length < 10) return 'Please add a few more details (at least 10 characters).';
        return '';
      }],
      ['#f-files', () => {
        if (files.length > MAX_FILES) return `Please choose up to ${MAX_FILES} images.`;
        const bad = files.find((f) => !ACCEPTED.includes(f.type));
        if (bad) return `"${bad.name}" is not a supported format. Please use JPG, PNG or WEBP.`;
        const big = files.find((f) => f.size > MAX_BYTES);
        if (big) return `"${big.name}" is larger than 5 MB.`;
        return '';
      }, 'files'],
      ['input[name="consent"]', () => ($('input[name="consent"]').checked ? '' : 'Please confirm that we may use your details to respond.')],
    ];

    function setFieldState(control, msg) {
      const field = control.closest('.field');
      const errId = field.querySelector('.field__error')?.id;
      const err = errId && document.getElementById(errId);
      field.classList.toggle('is-invalid', Boolean(msg));
      if (err) err.textContent = msg;
      form.querySelectorAll(`[name="${control.name}"]`).forEach((c) => {
        if (msg) c.setAttribute('aria-invalid', 'true');
        else c.removeAttribute('aria-invalid');
      });
    }

    function validate({ only } = {}) {
      const invalid = [];
      rules.forEach(([sel, check, key]) => {
        if (only && key !== only) return;
        const control = $(sel);
        const msg = check();
        setFieldState(control, msg);
        if (msg) invalid.push(control);
      });
      return invalid;
    }

    // Re-validate as people correct things (only after a first submit attempt)
    form.addEventListener('input', () => { if (attempted) validate(); });
    form.addEventListener('change', () => { if (attempted) validate(); });

    /* ---- Status messages (built with textContent: no HTML injection) ---- */
    function showStatus(type, title, lines = [], actions = []) {
      status.className = `form-status form-status--${type}`;
      status.replaceChildren(el('strong', {}, title), ...lines.map((l) => el('p', {}, l)));
      if (actions.length) {
        const row = el('div', { className: 'btn-row' });
        actions.forEach((a) => row.append(a));
        status.append(row);
      }
      status.hidden = false;
      status.focus({ preventScroll: true });
      status.scrollIntoView({ behavior: prefersReducedMotion.matches ? 'auto' : 'smooth', block: 'nearest' });
    }

    function summary() {
      const data = new FormData(form);
      const lines = [
        'Hello Reign Fashion, I would like to make an enquiry.',
        '',
        `Name: ${data.get('name')}`,
        `Service: ${serviceLabel()}`,
      ];
      if (data.get('event_date')) lines.push(`Occasion date: ${data.get('event_date')}`);
      lines.push(`Preferred contact: ${methodLabel()}`);
      if (data.get('phone')) lines.push(`Phone: ${data.get('phone')}`);
      lines.push(`Email: ${data.get('email')}`, '', `Details: ${data.get('message')}`);
      if (files.length) lines.push('', `I have ${files.length} inspiration image(s) to share.`);
      return lines.join('\n');
    }

    function fallbackActions() {
      const actions = [];
      if (waNumber) {
        actions.push(el('a', { className: 'btn btn--dark btn--sm', href: waHref(summary()), target: '_blank', rel: 'noopener' }, 'Send via WhatsApp'));
      }
      if (SITE_CONFIG.email) {
        const subject = `Enquiry: ${serviceLabel() || 'Reign Fashion'}`;
        actions.push(el('a', {
          className: 'btn btn--outline btn--sm',
          href: `mailto:${SITE_CONFIG.email}?subject=${encodeURIComponent(subject)}&body=${encodeURIComponent(summary())}`,
        }, 'Send by Email'));
      }
      return actions;
    }

    function setBusy(busy) {
      submitBtn.disabled = busy;
      submitBtn.setAttribute('aria-busy', String(busy));
      submitBtn.textContent = busy ? 'Sending…' : 'Send Enquiry';
    }

    /* ---- Submit ---- */
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      attempted = true;

      if ($('#f-website').value) return; // honeypot filled: likely a bot

      const invalid = validate();
      if (invalid.length) {
        showStatus('error', 'Please check the form.',
          [`${invalid.length === 1 ? 'One field needs' : `${invalid.length} fields need`} your attention.`]);
        invalid[0].focus();
        return;
      }

      const firstName = $('#f-name').value.trim().split(/\s+/)[0];

      // No sending service connected yet: say so plainly and offer working alternatives
      if (!SITE_CONFIG.formEndpoint) {
        console.info('Enquiry form: set SITE_CONFIG.formEndpoint in js/script.js to enable online sending.');
        const actions = fallbackActions();
        showStatus('info', 'Your enquiry has not been sent yet.', [
          'Online sending is not connected on this website yet, so your details have not been submitted.',
          actions.length
            ? 'Please use one of the options below to send it now. Your details are included automatically.'
            : 'Reign Fashion\'s contact details will be published here soon. Please keep your notes and try again later.',
        ], actions);
        return;
      }

      setBusy(true);
      try {
        const res = await fetch(SITE_CONFIG.formEndpoint, {
          method: 'POST',
          body: new FormData(form),
          headers: { Accept: 'application/json' },
        });
        const data = await res.json().catch(() => ({}));

        // Server-side validation: show each message next to its field
        if (res.status === 400 && data.errors) {
          const FIELD = {
            name: '#f-name', email: '#f-email', phone: '#f-phone', contact_method: 'input[name="contact_method"]',
            service: '#f-service', event_date: '#f-date', message: '#f-message', inspiration: '#f-files',
            consent: 'input[name="consent"]',
          };
          const bad = Object.entries(data.errors).map(([key, msg]) => {
            const control = FIELD[key] && $(FIELD[key]);
            if (control) setFieldState(control, msg);
            return control;
          }).filter(Boolean);
          showStatus('error', 'Please check the form.', Object.values(data.errors));
          bad[0]?.focus();
          return;
        }
        // Too many attempts, or any other server-reported problem
        if (!res.ok) {
          if (data.error) {
            showStatus('error', 'Your enquiry was not sent.', [data.error], fallbackActions());
            return;
          }
          throw new Error(`HTTP ${res.status}`);
        }

        form.reset();
        files = [];
        design = '';
        syncFiles();
        attempted = false;
        phoneReqMark.hidden = true;
        phone.required = false;
        updateWhatsAppLinks('', '');   // form sent: back to the general message
        showStatus('success', `Thank you, ${firstName}. Your enquiry has been sent.`, [
          ...(data.ref ? [`Your reference number is ${data.ref}. Please quote it if you contact us about this enquiry.`] : []),
          'The Reign Fashion team will be in touch using your preferred contact method.',
        ]);
      } catch (err) {
        console.error('Enquiry form submission failed:', err);
        showStatus('error', 'Sorry, your enquiry could not be sent.',
          ['Please check your connection and try again. Your details are still in the form.',
            ...(fallbackActions().length ? ['You can also send it another way:'] : [])],
          fallbackActions());
      } finally {
        setBusy(false);
      }
    });
  }

  /* ------------------------------------------------------------------------
     Boot
     ------------------------------------------------------------------------ */
  document.addEventListener('DOMContentLoaded', () => {
    initHeroEntrance();
    initHeader();
    initMobileMenu();
    initNavDropdowns();
    initActiveNav();
    initReveal();
    initGallery();                                         // interactive immediately
    loadLiveGallery().then((replaced) => { if (replaced) refreshGallery(); });
    initCarousel();
    loadLiveTestimonials().then((replaced) => { if (replaced) refreshCarousels(); });
    loadDesignVideos();
    initContactDetails();
    document.querySelectorAll('[data-year]').forEach((n) => { n.textContent = new Date().getFullYear(); });
    initWhatsApp();
    initAcademy();
    loadLiveSettings();
    initEnquiryForm();
    initAcademyForm();
  });
})();
