/**
 * One-time conversion of the single long index.html into separate pages.
 * Usage:  node tools/split-pages.js <path-to-single-page-source.html>
 *
 * Each page gets the shared header, mobile menu, footer and scripts, its own
 * title/description/canonical, and a green page banner (except Home).
 * Header/footer links always point to pages; links inside page content stay
 * as same-page anchors when the target section is on that page.
 */
const fs = require('fs');
const path = require('path');

const ROOT = path.join(__dirname, '..');
const SITE = 'https://reignfashionempire.com/';
const src = fs.readFileSync(process.argv[2], 'utf8');

/* ---------------------------------------------------------------- parts */
const cut = (from, to, { includeEnd = true } = {}) => {
  const a = src.indexOf(from);
  const b = src.indexOf(to, a);
  if (a < 0 || b < 0) throw new Error(`Marker not found: ${from.slice(0, 40)} … ${to.slice(0, 30)}`);
  return src.slice(a, includeEnd ? b + to.length : b);
};

/** A section plus the comment block directly above it. */
function section(id) {
  const at = src.indexOf(`<section id="${id}"`);
  if (at < 0) throw new Error(`Section not found: ${id}`);
  let start = src.lastIndexOf('\n', at) + 1;
  const commentStart = src.lastIndexOf('    <!-- ====', at);
  if (commentStart >= 0) {
    const between = src.slice(src.indexOf('-->', commentStart) + 3, at);
    if (/^\s*$/.test(between)) start = commentStart;
  }
  const end = src.indexOf('</section>', at) + '</section>'.length;
  return src.slice(start, end);
}

const head = src.slice(0, src.indexOf('</head>'));
const top = cut('  <a class="skip-link"', '  <main id="main">', { includeEnd: false }); // skip link, header, mobile menu
const lightbox = cut('    <!-- Lightbox', '</dialog>');
const waFloat = cut('    <!-- Floating WhatsApp', '</a>');
const footer = cut('  <!-- ============================================================\n       FOOTER', '</footer>');

/* ---------------------------------------------------------------- pages */
// Where each section lives (used to resolve #links across pages)
const SECTION_PAGE = {
  home: 'index.html', collections: 'collections.html', 'aso-ebi': 'collections.html', 'ready-to-wear': 'collections.html',
  kids: 'collections.html', men: 'collections.html', services: 'collections.html', gallery: 'collections.html',
  bridal: 'bridal.html', 'aso-ebi-styles': 'aso-ebi.html', academy: 'academy.html', about: 'about.html',
  process: 'about.html', faq: 'about.html', testimonials: 'index.html', contact: 'contact.html',
};
// Menu/footer destinations: these always open the page
const NAV_PAGE = {
  home: 'index.html', collections: 'collections.html', bridal: 'bridal.html', 'aso-ebi': 'aso-ebi.html',
  academy: 'academy.html', about: 'about.html', contact: 'contact.html', gallery: 'collections.html#gallery',
  process: 'about.html#process', faq: 'about.html#faq', services: 'collections.html#services',
  'ready-to-wear': 'collections.html#ready-to-wear', kids: 'collections.html#kids', men: 'collections.html#men',
};

const banner = (crumb, eyebrow, title, lead) => `    <!-- ============================================================
         PAGE BANNER
         ============================================================ -->
    <section class="page-hero" aria-labelledby="page-title">
      <div class="container">
        <nav class="crumbs" aria-label="Breadcrumb"><a href="./">Home</a><span aria-hidden="true">/</span><span aria-current="page">${crumb}</span></nav>
        <p class="eyebrow">${eyebrow}</p>
        <h1 id="page-title" class="display page-hero__title">${title}</h1>
        <p class="lead page-hero__lead">${lead}</p>
      </div>
    </section>`;

const homeCta = `    <!-- ============================================================
         HOME CALL TO ACTION
         ============================================================ -->
    <section class="section home-cta" aria-labelledby="home-cta-title">
      <div class="container home-cta__inner">
        <p class="eyebrow reveal">Custom Orders &amp; Consultations</p>
        <h2 id="home-cta-title" class="h2 reveal" style="--d: 80ms">Let's Create Something <em>Special</em></h2>
        <p class="lead reveal" style="--d: 160ms">Tell us about your occasion and the look you have in mind. We'll take it from there.</p>
        <div class="btn-row reveal" style="--d: 240ms">
          <a href="#contact" class="btn btn--dark">Book a Consultation</a>
          <a href="#about" class="link-arrow">How It Works</a>
        </div>
      </div>
    </section>`;

const PAGES = [
  {
    file: 'index.html', nav: 'home', crumb: null,
    title: 'Reign Fashion Empire | Wedding Gowns, Aso-Ebi &amp; Fashion Academy in Asaba',
    description: "Reign Fashion Empire, Asaba: elegant wedding gowns, beautiful Aso-Ebi, stylish ready-to-wear, kiddies' and men's wear, plus professional fashion training at the Reign Fashion Academy.",
    body: () => [section('home'), section('collections'), section('testimonials'), homeCta],
    seo: 'business',
  },
  {
    file: 'collections.html', nav: 'collections',
    title: 'Collections | Reign Fashion Empire, Asaba',
    description: "Explore Reign Fashion Empire's collections: bridal gowns, Aso-Ebi, ready-to-wear, kiddies' wear and men's wear, plus custom orders made to measure in Asaba.",
    body: () => [banner('Collections', 'Our Collections', 'Every Occasion, <em>Beautifully</em> Dressed', 'Bridal, Aso-Ebi, ready-to-wear, kiddies’ and men’s wear, all designed and made to your measurements.'),
      section('collections'), section('services'), section('gallery'), lightbox],
    galleryFilter: '',
  },
  {
    file: 'bridal.html', nav: 'bridal',
    title: 'Bridal Gowns &amp; Wedding Dresses in Asaba | Reign Fashion Empire',
    description: 'Elegant custom wedding gowns by Reign Fashion Empire, Asaba. Book a bridal consultation to design the gown made for your day.',
    body: () => [banner('Bridal', 'The Bridal Studio', 'Bridal <em>Gowns</em>', 'Elegant wedding gowns for brides, designed around you and made to celebrate your special day.'),
      section('bridal'), section('gallery'), lightbox],
    galleryFilter: 'bridal',
  },
  {
    file: 'aso-ebi.html', nav: 'aso-ebi',
    title: 'Aso-Ebi Styles in Asaba | Reign Fashion Empire',
    description: 'Beautiful Aso-Ebi made to measure in Asaba: corset, peplum lace, mermaid and statement styles for celebrants, family and guests. Order a style or send your own design.',
    body: () => [banner('Aso-Ebi', 'Aso-Ebi', 'Aso-Ebi for Every <em>Occasion</em>', 'Coordinated, made-to-measure looks for weddings, celebrations and special occasions, for celebrants, families and guests.'),
      section('aso-ebi-styles'), section('gallery'), lightbox],
    galleryFilter: 'aso-ebi',
  },
  {
    file: 'academy.html', nav: 'academy',
    title: 'Reign Fashion Academy, Asaba | Fashion Design Training',
    description: 'Professional fashion training in Asaba from beginner to professional mastery: sewing, pattern drafting, garment construction, Aso-Ebi and bridal gown production.',
    body: () => [banner('Academy', 'Reign Fashion Academy', 'Learn the <em>Craft</em>', 'Professional fashion training for aspiring designers, from your first stitch to professional mastery.'),
      section('academy')],
  },
  {
    file: 'about.html', nav: 'about',
    title: 'About Us | Reign Fashion Empire, Asaba',
    description: 'Meet Reign Fashion Empire: our story, design philosophy and craftsmanship, how custom orders work, and answers to common questions.',
    body: () => [banner('About', 'About Reign Fashion', 'Your Style, Our <em>Creativity</em>', 'Our story, how we work with you, and answers to the questions clients ask most.'),
      section('about'), section('process'), section('faq')],
    seo: 'faq',
  },
  {
    file: 'contact.html', nav: 'contact',
    title: 'Contact &amp; Orders | Reign Fashion Empire, Asaba',
    description: 'Book a consultation or place an order with Reign Fashion Empire in Asaba. Send an enquiry, chat on WhatsApp, or visit our studio on Old Anwai Road.',
    body: () => [banner('Contact', 'Contact &amp; Orders', 'Book a <em>Consultation</em>', 'Send us an enquiry or chat with us on WhatsApp. We’ll get back to you to discuss your order.'),
      section('contact')],
    seo: 'business',
  },
];

/* ---------------------------------------------------------- link rewriting */
const pageOf = (target) => target.split('#')[0];

/** Header/menu/footer: always go to pages; mark the current page. */
function rewriteChrome(html, page) {
  html = html.replace(/href="#([\w-]+)"/g, (m, id) => {
    if (id === 'main') return m;
    const to = NAV_PAGE[id];
    if (!to) return m;
    return `href="${to === 'index.html' ? './' : to}"`;
  });
  // Current page in the menus
  const own = page.file === 'index.html' ? './' : page.file;
  html = html.replace(new RegExp(`<a href="${own.replace('.', '\\.')}" class="(nav-link|mobile-nav-link)">`, 'g'),
    (m, cls) => `<a href="${own}" class="${cls} is-active" aria-current="page">`);
  // "Back to top" goes to the top of the current page
  html = html.replace('href="./" class="footer__top-link"', 'href="#main" class="footer__top-link"');
  return html;
}

/** Page content: keep same-page anchors, otherwise link to the right page. */
function rewriteContent(html, page) {
  const idsHere = new Set([...html.matchAll(/\sid="([\w-]+)"/g)].map((m) => m[1]));

  // Enquiry links carry the service/design to the contact page
  html = html.replace(/<a href="#contact"([^>]*?)data-service="([^"]*)"([^>]*?)>/g, (m, a, service, b) => {
    if (page.file === 'contact.html') return m;
    const design = (m.match(/data-design="([^"]*)"/) || [])[1];
    const q = new URLSearchParams({ service: decode(service), ...(design ? { design: decode(design) } : {}) });
    return `<a href="contact.html?${q.toString().replace(/&/g, '&amp;')}"${a}data-service="${service}"${b}>`;
  });
  // Gallery filter links
  html = html.replace(/<a href="#gallery"([^>]*?)data-gallery-filter="([^"]*)"([^>]*?)>/g, (m, a, f, b) =>
    idsHere.has('gallery') ? m : `<a href="collections.html?filter=${f}#gallery"${a}data-gallery-filter="${f}"${b}>`);
  // Remaining anchors
  html = html.replace(/href="#([\w-]+)"/g, (m, id) => {
    if (id === 'main' || idsHere.has(id)) return m;
    const to = SECTION_PAGE[id];
    if (!to) return m;
    const p = pageOf(to);
    const nav = NAV_PAGE[id] && NAV_PAGE[id].includes('#') ? NAV_PAGE[id] : (NAV_PAGE[id] || `${p}#${id}`);
    return `href="${nav === 'index.html' ? './' : nav}"`;
  });
  return html;
}
const decode = (s) => s.replace(/&amp;/g, '&').replace(/&#39;/g, "'").replace(/&quot;/g, '"');

/* -------------------------------------------------------------------- head */
function pageHead(page) {
  const url = SITE + (page.file === 'index.html' ? '' : page.file);
  const plainTitle = page.title.replace(/&amp;/g, '&');
  let h = head
    .replace(/<title>[\s\S]*?<\/title>/, `<title>${page.title}</title>`)
    .replace(/<meta name="description" content="[^"]*">/, `<meta name="description" content="${page.description.replace(/"/g, '&quot;')}">`)
    .replace(/<link rel="canonical" href="[^"]*">/, `<link rel="canonical" href="${url}">`)
    .replace(/<meta property="og:url" content="[^"]*">/, `<meta property="og:url" content="${url}">`)
    .replace(/<meta property="og:title" content="[^"]*">/, `<meta property="og:title" content="${plainTitle.replace(/"/g, '&quot;')}">`)
    .replace(/<meta property="og:description" content="[^"]*">/, `<meta property="og:description" content="${page.description.replace(/"/g, '&quot;')}">`)
    .replace(/<meta name="twitter:title" content="[^"]*">/, `<meta name="twitter:title" content="${plainTitle.replace(/"/g, '&quot;')}">`)
    .replace(/<meta name="twitter:description" content="[^"]*">/, `<meta name="twitter:description" content="${page.description.replace(/"/g, '&quot;')}">`);
  // Structured data is filled per page by tools/build-seo.js
  h = h.replace(/(<!-- STRUCTURED DATA \(generated\)[^>]*-->)[\s\S]*?(\s*<!-- \/STRUCTURED DATA -->)/, '$1$2');
  return h;
}

/* -------------------------------------------------------------------- build */
for (const page of PAGES) {
  const bodyAttr = page.galleryFilter ? ` data-page="${page.nav}" data-gallery-filter="${page.galleryFilter}"` : ` data-page="${page.nav}"`;
  const content = rewriteContent(page.body().join('\n\n'), page);
  const html = [
    pageHead(page),
    '</head>',
    `<body${bodyAttr}>`,
    '',
    rewriteChrome(top, page) + '  <main id="main">',
    '',
    content,
    '',
    waFloat,
    '',
    '  </main>',
    '',
    rewriteChrome(footer, page),
    '',
    '  <script src="js/script.js" defer></script>',
    '</body>',
    '</html>',
    '',
  ].join('\n');
  fs.writeFileSync(path.join(ROOT, page.file), html);
  console.log(`${page.file.padEnd(17)} ${(html.length / 1024).toFixed(0).padStart(3)} KB`);
}
