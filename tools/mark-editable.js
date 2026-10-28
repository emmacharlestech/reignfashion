#!/usr/bin/env node
/**
 * Marks the text and photos the owner can change in admin/pages.php with a
 * stable data-edit="key" attribute. Edits are saved in the database against
 * that key and applied by page.php when the page is served.
 *
 *   node tools/mark-editable.js
 *
 * Safe to re-run after adding new content: existing keys are kept, new
 * elements get new keys. Only <main> is scanned (header and footer come from
 * tools/build-layout.js), and anything the site's JavaScript fills in (gallery,
 * testimonials, videos, forms, contact details) is skipped.
 */
'use strict';

const fs = require('fs');
const path = require('path');

const ROOT = path.join(__dirname, '..');
const PAGES = ['index', 'about', 'academy', 'aso-ebi', 'bridal', 'collections', 'contact', 'kids', 'men', 'women', 'privacy'];

const TEXT_TAGS = new Set(['h1', 'h2', 'h3', 'h4', 'p', 'li', 'figcaption', 'summary', 'dt', 'dd']);
const VOID_TAGS = new Set(['img', 'br', 'input', 'meta', 'link', 'source', 'hr', 'wbr']);
// Never edit inside these: interactive or filled in by js/script.js
const SKIP_TAGS = new Set(['form', 'dialog', 'button', 'select', 'label', 'template', 'script', 'style', 'svg', 'nav']);
const SKIP_ATTRS = /\bid="gallery-grid"|\bdata-design-videos\b|\bdata-contact\b|\bclass="[^"]*\bcarousel__track\b/;
// Text may only contain these inline tags (they map to *italic*, **bold** and line breaks in the admin)
const INLINE_ONLY = /^(?:[^<]|<\/?(?:em|strong)>|<br\s*\/?>)*$/;

const TOKEN = /<!--[\s\S]*?-->|<(\/?)([a-zA-Z][a-zA-Z0-9]*)\b((?:[^>"']|"[^"]*"|'[^']*')*)>/g;

/** Index just past the closing tag that matches the element opened at `from`. */
function findClose(html, tag, from) {
  const re = new RegExp(`<(/?)${tag}\\b[^>]*>`, 'gi');
  re.lastIndex = from;
  let depth = 1;
  for (let m; (m = re.exec(html)); ) {
    depth += m[1] ? -1 : 1;
    if (depth === 0) return { innerEnd: m.index, end: re.lastIndex };
  }
  return null;
}

function mark(file) {
  const html = fs.readFileSync(file, 'utf8');
  const start = html.indexOf('<main');
  const stop = html.lastIndexOf('</main>');
  if (start < 0 || stop < 0) return 0;

  const existing = new Set([...html.matchAll(/data-edit="([^"]+)"/g)].map((m) => m[1]));
  const counters = {};
  const nextKey = (section, kind) => {
    let key;
    do {
      counters[section + kind] = (counters[section + kind] || 0) + 1;
      key = `${section}-${kind}${counters[section + kind]}`;
    } while (existing.has(key));
    existing.add(key);
    return key;
  };

  const inserts = []; // [position, text]
  const stack = [];
  TOKEN.lastIndex = start;
  for (let m; (m = TOKEN.exec(html)) && m.index < stop; ) {
    if (!m[2]) continue; // comment
    const [whole, closing, rawTag, attrs] = m;
    const tag = rawTag.toLowerCase();

    if (closing) {
      const i = stack.map((s) => s.tag).lastIndexOf(tag);
      if (i >= 0) stack.length = i;
      continue;
    }

    const skipped = stack.some((s) => s.skip) || SKIP_TAGS.has(tag) || SKIP_ATTRS.test(attrs);
    const section = [...stack].reverse().find((s) => s.tag === 'section' && s.id)?.id || 'main';
    const selfClosing = VOID_TAGS.has(tag) || attrs.trim().endsWith('/');
    const tagEnd = m.index + whole.length - (attrs.trim().endsWith('/') ? 2 : 1);
    // Elements with an id or data-* are usually script hooks; headings' ids only label their sections
    const plain = !/\sdata-/.test(attrs) && (/^h[1-4]$/.test(tag) || !/\sid="/.test(attrs));

    if (!skipped && plain) {
      if (tag === 'img') {
        inserts.push([tagEnd, ` data-edit="${nextKey(section, 'img')}"`]);
      } else if (TEXT_TAGS.has(tag) || (tag === 'a' && /\bclass="[^"]*\b(btn|link-arrow)\b/.test(attrs))) {
        const close = findClose(html, tag, m.index + whole.length);
        const inner = close && html.slice(m.index + whole.length, close.innerEnd);
        if (inner && INLINE_ONLY.test(inner) && inner.replace(/<[^>]+>|&nbsp;|\s/g, '') !== '') {
          inserts.push([tagEnd, ` data-edit="${nextKey(section, 't')}"`]);
        }
      }
    }

    if (!selfClosing) {
      stack.push({ tag, id: (attrs.match(/\sid="([^"]+)"/) || [])[1], skip: skipped });
    }
  }

  let out = html;
  for (const [pos, text] of inserts.sort((a, b) => b[0] - a[0])) {
    out = out.slice(0, pos) + text + out.slice(pos);
  }
  if (out !== html) fs.writeFileSync(file, out);
  return inserts.length;
}

for (const page of PAGES) {
  const n = mark(path.join(ROOT, `${page}.html`));
  console.log(`${page}.html: ${n} new editable item${n === 1 ? '' : 's'}`);
}
