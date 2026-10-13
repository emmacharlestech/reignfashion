/**
 * Creates the Women's, Men's and Kiddies' collection pages from
 * collections.html (head, gallery, lightbox). Run once, then run
 * tools/build-layout.js to add the shared header and footer.
 *
 *   node tools/make-collection-pages.js
 *
 * Offering text is DRAFT wording drawn from the rest of the site;
 * the owner should review it.
 */
const fs = require('fs');
const path = require('path');
const ROOT = path.join(__dirname, '..');
const SITE = 'https://reignfashionempire.com/';
const base = fs.readFileSync(path.join(ROOT, 'collections.html'), 'utf8');

const grab = (re, what) => { const m = base.match(re); if (!m) throw new Error(`Not found in collections.html: ${what}`); return m[0]; };
const head = base.slice(0, base.indexOf('</head>'));
const top = grab(/<!-- LAYOUT:TOP[\s\S]*?<!-- \/LAYOUT:TOP -->/, 'header');
const footer = grab(/<!-- LAYOUT:FOOTER[\s\S]*?<!-- \/LAYOUT:FOOTER -->/, 'footer');
const gallery = grab(/    <!-- =+\n\s+GALLERY[\s\S]*?<\/section>/, 'gallery');
const lightbox = grab(/    <!-- Lightbox[\s\S]*?<\/dialog>/, 'lightbox');
const waFloat = grab(/    <!-- Floating WhatsApp[\s\S]*?<\/a>/, 'WhatsApp button');

const img = (src, alt, w, h, sample, eager = false) =>
  `<div class="img-slot${sample ? ' is-sample' : ''} ratio-portrait"><img src="${src}" alt="${sample ? 'Sample image: ' : ''}${alt}" width="${w}" height="${h}"${eager ? ' fetchpriority="high"' : ' loading="lazy"'} decoding="async" style="object-position: 50% 25%"></div>`;

const banner = (crumb, title, lead) => `    <section class="page-hero" aria-labelledby="page-title">
      <div class="container">
        <nav class="crumbs" aria-label="Breadcrumb"><a href="./">Home</a><span aria-hidden="true">/</span><a href="collections">Collections</a><span aria-hidden="true">/</span><span aria-current="page">${crumb}</span></nav>
        <p class="eyebrow">Our Collections</p>
        <h1 id="page-title" class="display page-hero__title">${title}</h1>
        <p class="lead page-hero__lead">${lead}</p>
      </div>
    </section>`;

const intro = (p) => `    <!-- ============================================================
         ${p.crumb.toUpperCase()}: INTRODUCTION
         DRAFT offering text for the owner to review.
         IMAGE SLOT: ${p.key}-feature — ${p.image.src}
         ============================================================ -->
    <section class="section collection-intro" aria-labelledby="intro-title">
      <div class="container collection-intro__grid">
        <figure class="collection-intro__media reveal">
          ${img(p.image.src, p.image.alt, p.image.w, p.image.h, p.image.sample)}
        </figure>
        <div class="collection-intro__content">
          <p class="eyebrow reveal">${p.crumb}</p>
          <h2 id="intro-title" class="h2 reveal" style="--d: 80ms">${p.introTitle}</h2>
          <p class="lead reveal" style="--d: 160ms">${p.introLead}</p>
          <ul class="collection-intro__list reveal" style="--d: 220ms">
${p.offers.map(([t, d]) => `            <li><h3 class="collection-intro__item">${t}</h3><p>${d}</p></li>`).join('\n')}
          </ul>
          <div class="btn-row reveal" style="--d: 280ms">
            <a href="contact?service=${p.service}" class="btn btn--dark" data-service="${p.service}">${p.cta}</a>
            <a href="#gallery" class="link-arrow">See the Designs</a>
          </div>
          <p class="collection-intro__note reveal">Every piece is made to your measurements. Pricing and timelines are agreed at your consultation.</p>
        </div>
      </div>
    </section>`;

const womenLines = `    <!-- ============================================================
         WOMEN: THE THREE LINES
         ============================================================ -->
    <section class="section section--white women-lines" aria-labelledby="lines-title">
      <div class="container">
        <header class="section-head section-head--center">
          <p class="eyebrow reveal">Explore</p>
          <h2 id="lines-title" class="h2 reveal" style="--d: 80ms">Find Your <em>Look</em></h2>
        </header>
        <ul class="women-lines__grid">
          <li class="line-card reveal">
            <a href="bridal" class="line-card__link">
              <span class="line-card__media">${img('assets/images/bridal/gown-sheer-sparkle.webp', 'Reign Fashion bridal: crystal-embellished mermaid gown with sheer sleeves', 800, 1000, false)}</span>
              <span class="line-card__body"><span class="line-card__title">Bridal Gowns</span><span class="line-card__text">Elegant wedding gowns, our specialty.</span><span class="link-arrow">Visit the Bridal Studio</span></span>
            </a>
          </li>
          <li class="line-card reveal" style="--d: 100ms">
            <a href="aso-ebi" class="line-card__link">
              <span class="line-card__media">${img('assets/images/collection-aso-ebi.webp', 'Woman in a red satin gown with a train, holding red roses', 1000, 1250, true)}</span>
              <span class="line-card__body"><span class="line-card__title">Aso-Ebi</span><span class="line-card__text">Coordinated looks for celebrations.</span><span class="link-arrow">Explore Aso-Ebi Styles</span></span>
            </a>
          </li>
          <li class="line-card reveal" style="--d: 200ms">
            <a href="#gallery" class="line-card__link" data-gallery-filter="ready-to-wear">
              <span class="line-card__media">${img('assets/images/collection-ready-to-wear.webp', 'Woman in a black slip dress against a terracotta wall', 900, 1125, true)}</span>
              <span class="line-card__body"><span class="line-card__title">Ready-to-Wear</span><span class="line-card__text">Stylish outfits for everyday elegance.</span><span class="link-arrow">See Ready-to-Wear</span></span>
            </a>
          </li>
        </ul>
      </div>
    </section>`;

const PAGES = [
  {
    key: 'women', file: 'women.html', crumb: "Women's Collection", include: 'bridal,aso-ebi,ready-to-wear', service: 'ready-to-wear',
    title: "Women's Collection | Reign Fashion Empire, Asaba",
    description: "Women's fashion by Reign Fashion Empire in Asaba: elegant bridal gowns, beautiful Aso-Ebi and stylish ready-to-wear, all made to measure.",
    banner: ["Women's <em>Collection</em>", 'Elegant bridal gowns, beautiful Aso-Ebi and stylish ready-to-wear, designed around you and made to your measurements.'],
    image: { src: 'assets/images/bridal/gown-puff-sleeve-satin.webp', alt: 'Reign Fashion bridal: satin mermaid gown with puff sleeves, a pearl-beaded neckline and cathedral veil', w: 1200, h: 1500, sample: false },
    introTitle: 'Designed Around <em>You</em>',
    introLead: 'From your wedding day to everyday elegance, every piece is shaped to your style, your occasion and your measurements.',
    offers: [
      ['Bridal Gowns', 'Custom wedding gowns, from timeless silhouettes to fully bespoke designs.'],
      ['Aso-Ebi &amp; Occasion Wear', 'Coordinated and traditional outfits for weddings, celebrations and special occasions.'],
      ['Ready-to-Wear', 'Stylish, effortless outfits designed for everyday elegance.'],
    ],
    cta: 'Book a Consultation', extra: womenLines,
    galleryTitle: "Women's <em>Designs</em>", galleryLead: 'Bridal gowns, Aso-Ebi and ready-to-wear from the Reign Fashion studio.',
  },
  {
    key: 'men', file: 'men.html', crumb: "Men's Collection", include: 'men', service: 'men',
    title: "Men's Collection | Reign Fashion Empire, Asaba",
    description: "Fashionable men's wear made to measure in Asaba: traditional attire, tailored looks and occasion outfits by Reign Fashion Empire.",
    banner: ["Men's <em>Collection</em>", 'Fashionable menswear, from sharp contemporary looks to traditional attire, made to your measure.'],
    image: { src: 'assets/images/collection-men.webp', alt: 'Man in a navy checked suit with a pocket square', w: 900, h: 1350, sample: true },
    introTitle: 'Sharp, Tailored, <em>Yours</em>',
    introLead: 'Menswear cut to your measurements and finished with care, for celebrations, ceremonies and everyday style.',
    offers: [
      ['Traditional Attire', 'Traditional outfits made to your measure for weddings, ceremonies and celebrations.'],
      ['Tailored &amp; Contemporary Looks', 'Sharp, modern pieces cut to fit you perfectly.'],
      ['Occasion Wear', 'Standout outfits for weddings and special events.'],
      ['Matching Family Looks', 'Coordinate with your partner or family in matching Aso-Ebi.'],
    ],
    cta: "Order Men's Wear",
    galleryTitle: "Men's <em>Designs</em>", galleryLead: 'A selection of menswear looks from the Reign Fashion studio.',
  },
  {
    key: 'kids', file: 'kids.html', crumb: "Kiddies' Collection", include: 'kids', service: 'kids',
    title: "Kiddies' Collection | Reign Fashion Empire, Asaba",
    description: "Stunning outfits for children in Asaba: celebration, traditional and matching family looks, made to measure by Reign Fashion Empire.",
    banner: ["Kiddies' <em>Collection</em>", 'Stunning outfits for little ones, for celebrations and every day in between.'],
    image: { src: 'assets/images/collection-kids.webp', alt: 'Little girl in a cream outfit and bow headband on a studio stool', w: 900, h: 1350, sample: true },
    introTitle: 'Little Ones, <em>Beautifully</em> Dressed',
    introLead: 'Comfortable, beautifully finished outfits made to measure for children, so they look and feel special.',
    offers: [
      ['Celebration Outfits', 'Party, wedding and special-occasion looks for boys and girls.'],
      ['Traditional Attire', 'Traditional outfits sized just for them.'],
      ['Matching Family Looks', 'Coordinate the whole family in matching Aso-Ebi.'],
      ['Everyday Style', 'Stylish pieces for every day in between.'],
    ],
    cta: "Order Kiddies' Wear",
    galleryTitle: "Kiddies' <em>Designs</em>", galleryLead: 'A selection of children’s looks from the Reign Fashion studio.',
  },
];

for (const p of PAGES) {
  const url = SITE + p.key;
  const plain = p.title.replace(/&amp;/g, '&');
  const pageHead = head
    .replace(/<title>[\s\S]*?<\/title>/, `<title>${p.title}</title>`)
    .replace(/<meta name="description" content="[^"]*">/, `<meta name="description" content="${p.description}">`)
    .replace(/<link rel="canonical" href="[^"]*">/, `<link rel="canonical" href="${url}">`)
    .replace(/<meta property="og:url" content="[^"]*">/, `<meta property="og:url" content="${url}">`)
    .replace(/<meta property="og:title" content="[^"]*">/, `<meta property="og:title" content="${plain}">`)
    .replace(/<meta property="og:description" content="[^"]*">/, `<meta property="og:description" content="${p.description}">`)
    .replace(/<meta name="twitter:title" content="[^"]*">/, `<meta name="twitter:title" content="${plain}">`)
    .replace(/<meta name="twitter:description" content="[^"]*">/, `<meta name="twitter:description" content="${p.description}">`);

  const pageGallery = gallery
    .replace(/Selected <em>Creations<\/em>/, p.galleryTitle)
    .replace(/(<p class="lead reveal"[^>]*>)[\s\S]*?(<\/p>)/, `$1\n            ${p.galleryLead}\n          $2`);

  const html = [
    pageHead + '</head>',
    `<body data-page="${p.key}" data-gallery-include="${p.include}">`,
    '',
    '  ' + top,
    '',
    '  <main id="main">',
    '',
    banner(p.crumb, ...p.banner),
    '',
    intro(p),
    p.extra ? '\n' + p.extra : '',
    '',
    pageGallery,
    '',
    lightbox,
    '',
    waFloat,
    '',
    '  </main>',
    '',
    '  ' + footer,
    '',
    '  <script src="js/script.js" defer></script>',
    '</body>',
    '</html>',
    '',
  ].join('\n');
  fs.writeFileSync(path.join(ROOT, p.file), html);
  console.log(`${p.file} written (${(html.length / 1024).toFixed(0)} KB)`);
}
