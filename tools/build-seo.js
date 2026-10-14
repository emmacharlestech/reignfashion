/**
 * Regenerates the structured data (JSON-LD) in index.html from the page's own
 * content, so Google sees exactly what visitors see. Run after editing the FAQ
 * or contact details:
 *
 *   node tools/build-seo.js
 *
 * Opening hours and social profile links are added automatically once they are
 * filled in below (they are not public yet, so they are left out rather than guessed).
 */
const fs = require('fs');
const path = require('path');

const SITE = 'https://reignfashionempire.com/';
const BUSINESS = {
  name: 'Reign Fashion Empire',
  telephone: ['+2348135759554', '+2349071717484'],
  email: 'reignfashionempire@gmail.com',
  street: 'Anni Victor Plaza, Old Anwai Road, opposite Dwell Petrol Filling Station',
  locality: 'Asaba',
  region: 'Delta State',
  country: 'NG',
  openingHours: [],   // e.g. ['Mo-Sa 09:00-18:00'] once confirmed
  sameAs: [
    'https://www.instagram.com/reign_fashion_empire/',
    'https://www.facebook.com/Reign-Fashion-Empire',
    'https://www.tiktok.com/@reignfashionacademy'
  ],
};

const ROOT = path.join(__dirname, '..');  // tools/ sits in the site root
// Which structured data goes on which page
const TARGETS = { 'index.html': ['business'], 'contact.html': ['business'], 'about.html': ['faq'] };

// The FAQ is read from the page that shows it
const faqSource = fs.readFileSync(path.join(ROOT, 'about.html'), 'utf8');
let html = faqSource;

const decode = (s) => s
  .replace(/<[^>]+>/g, '')
  .replace(/&amp;/g, '&').replace(/&#39;|&apos;/g, "'").replace(/&quot;/g, '"').replace(/&nbsp;/g, ' ')
  .replace(/\s+/g, ' ').trim();

// FAQ: read the visible questions and answers
const faq = [...html.matchAll(/<details class="faq__item">\s*<summary>([\s\S]*?)<\/summary>\s*<div class="faq__answer">([\s\S]*?)<\/div>\s*<\/details>/g)]
  .map(([, q, a]) => ({
    '@type': 'Question',
    name: decode(q),
    acceptedAnswer: { '@type': 'Answer', text: decode(a) },
  }));

const business = {
  '@context': 'https://schema.org',
  '@type': 'ClothingStore',
  '@id': SITE + '#business',
  name: BUSINESS.name,
  url: SITE,
  logo: SITE + 'assets/images/brand/logo-full-gold.png',
  image: SITE + 'assets/images/brand/og-image.jpg',
  description: 'Fashion design house in Asaba creating wedding gowns, Aso-Ebi, ready-to-wear, kiddies\' and men\'s wear, with professional fashion training at the Reign Fashion Academy.',
  slogan: 'Your Style, Our Creativity, Our Passion.',
  telephone: BUSINESS.telephone[0],
  email: BUSINESS.email,
  address: {
    '@type': 'PostalAddress',
    streetAddress: BUSINESS.street,
    addressLocality: BUSINESS.locality,
    addressRegion: BUSINESS.region,
    addressCountry: BUSINESS.country,
  },
  areaServed: { '@type': 'City', name: 'Asaba' },
  contactPoint: BUSINESS.telephone.map((t) => ({ '@type': 'ContactPoint', telephone: t, contactType: 'customer service', availableLanguage: 'English' })),
};
if (BUSINESS.openingHours.length) business.openingHours = BUSINESS.openingHours;
if (BUSINESS.sameAs.length) business.sameAs = BUSINESS.sameAs;

const DATA = {
  business: business,
  faq: faq.length ? { '@context': 'https://schema.org', '@type': 'FAQPage', mainEntity: faq } : null,
};

const start = '<!-- STRUCTURED DATA (generated): business details + FAQ for Google -->';
const end = '<!-- /STRUCTURED DATA -->';

for (const [file, kinds] of Object.entries(TARGETS)) {
  const full = path.join(ROOT, file);
  let page = fs.readFileSync(full, 'utf8');
  const json = kinds.map((k) => DATA[k]).filter(Boolean)
    .map((b) => '  <script type="application/ld+json">\n' + JSON.stringify(b, null, 2).replace(/</g, '\\u003c').replace(/^/gm, '  ') + '\n  </script>')
    .join('\n');
  const a = page.indexOf(start);
  const b = page.indexOf(end);
  if (a < 0 || b < a) throw new Error(`Structured data markers not found in ${file}`);
  page = page.slice(0, a + start.length) + '\n' + json + '\n  ' + page.slice(b);
  fs.writeFileSync(full, page);
  console.log(`${file}: ${kinds.join(' + ')}`);
}
console.log(`FAQ entries: ${faq.length}`);
