# Reign Fashion Empire Website

A responsive, single-page website for **Reign Fashion Empire** (Asaba): wedding gowns, Aso-Ebi, ready-to-wear, kiddies' wear, men's wear and the **Reign Fashion Academy**.

Built with plain **HTML5, CSS3 and vanilla JavaScript**. There is no framework, no build step and no external JavaScript library. The only external resource is Google Fonts.

> **Status: pre-launch.** These are now real:
>
> - the logo and icons
> - phone, WhatsApp, email and address
> - Academy levels and course topics
>
> Still placeholders: **photos** (licensed samples for now), opening hours, social profile **links**, Academy duration/fees/intake, testimonials, and the form's sending service. See [Before going live](#before-going-live).

---

## Contents

1. [Run it locally](#run-it-locally)
2. [Project files](#project-files)
3. [Page sections](#page-sections)
4. [Update contact details and WhatsApp](#update-contact-details-and-whatsapp)
5. [Replace images](#replace-images)
6. [Replace the logo and favicon](#replace-the-logo-and-favicon)
7. [Backend: enquiry storage](#backend-enquiry-storage-php--mysql)
8. [Update placeholder content](#update-placeholder-content)
9. [Before going live](#before-going-live)
10. [Deploy](#deploy)
11. [Accessibility and performance notes](#accessibility-and-performance-notes)
12. [Credits](#credits)

---

## Run it locally

**With XAMPP (current setup)**

1. Start **Apache** in the XAMPP Control Panel.
2. Open <http://localhost/reignfashion/>.

**Without XAMPP**

- Open `index.html` directly in a browser. Everything works except the optional PHP form handler.
- Or serve the folder with any static server, e.g. `npx serve .` or `php -S localhost:8000`.

---

## Project files

```
reignfashion/
├── index.html          All page content and structure
├── css/style.css       All styles (design tokens at the top, then one numbered block per section)
├── js/script.js        All behaviour; SITE_CONFIG settings at the very top
├── api/enquiry.php     Enquiry form endpoint (saves to MySQL)
├── backend/            Private PHP code, config, database schema, uploads (blocked from browsers)
├── assets/
│   ├── favicon-48.png, apple-touch-icon.png   Gold RF monogram on brand green
│   └── images/
│       ├── brand/      Logo files (see "Logo and icons")
│       └── …           Put real photos here
└── README.md
```

**css/style.css** sections, in order: design tokens · reset · layout · typography · buttons · image placeholders · header · mobile menu · hero · scroll reveal · collections · bridal · services · gallery · lightbox · about · how it works · testimonials · contact · footer · academy · reduced motion.

**Brand colours** come from the logo: deep green and metallic gold. They are CSS custom properties in `:root` at the top of the stylesheet. Change them there to restyle the whole site.

| Token | Value | Used for |
|---|---|---|
| `--c-green` | `#0B3B2C` | Headings, buttons, primary ink |
| `--c-green-dark` | `#062A1F` | Dark sections (services, testimonials), mobile menu |
| `--c-green-deepest` | `#021C14` | Footer, lightbox (the logo's background) |
| `--c-gold` | `#D4A76B` | Accents on green (the logo's gold) |
| `--c-gold-deep` | `#8A6635` | Small gold text on light backgrounds |
| `--c-ivory` / `--c-champagne` | `#F8F5F0` / `#F1E6D2` | Light backgrounds (Academy uses champagne) |
| `--c-error` | `#A8322D` | Form errors only |
| `--gold-metal` | gradient | Metallic gold (logo on dark backgrounds, Academy level bars) |

Fonts are set in `--f-serif` and `--f-sans` in the same place.

**js/script.js** contains one small `init…()` function per feature (header, mobile menu, active nav, hero entrance, scroll reveal, gallery and lightbox, carousel, contact details, WhatsApp, enquiry form), all started at the bottom of the file.

---

## Page sections

| Section | Anchor | Notes |
|---|---|---|
| Hero | `#home` | Bridal-led image composition |
| Collections | `#collections` | Bridal, Aso-Ebi (`#aso-ebi`), Ready-to-Wear (`#ready-to-wear`), Kiddies (`#kids`), Men (`#men`) |
| Bridal Studio | `#bridal` | |
| Custom Orders & Services | `#services` | Draft service descriptions |
| Reign Fashion Academy | `#academy` | Duration, fees, intake still to confirm |
| Gallery ("Selected Creations") | `#gallery` | Filters + lightbox |
| About | `#about` | Provisional brand copy |
| How It Works | `#process` | |
| Testimonials | `#testimonials` | Placeholders only |
| Contact & Orders | `#contact` | Form + WhatsApp |

**Linking behaviour:**

- Links with `data-gallery-filter="…"` open the gallery pre-filtered.
- Links with `data-service="…"` pre-select that service in the enquiry form.
- The gallery's lightbox "Enquire" link also pre-fills the design name.

---

## Update contact details and WhatsApp

Every contact detail is set **once**, in `SITE_CONFIG` at the top of `js/script.js`. The contact section and the footer both update from it.

Current values:

```js
const SITE_CONFIG = {
  whatsappNumber: '2348135759554',            // 0813 575 9554, international, digits only
  phones: [
    { display: '0813 575 9554', tel: '+2348135759554' },
    { display: '0907 171 7484', tel: '+2349071717484' },
  ],
  email:   'reignfashionempire@gmail.com',
  address: 'Anni Victor Plaza, Old Anwai Road, opposite Dwell Petrol Filling Station, Asaba, Delta State',
  hours:   '',                                // not yet provided
  social: {
    instagram: { url: '', name: 'Reign Fashion Empire' },
    facebook:  { url: '', name: 'Reign Fashion Empire' },
    tiktok:    { url: '', name: 'Reign Fashion Academy Asaba' },
  },
  formEndpoint: '',                           // see "Connect the enquiry form"
};
```

**How the values display**

- **Empty values** show "To be confirmed". The site never outputs a fake or broken link.
- **Phones:** each number is a tap-to-call link. The one matching `whatsappNumber` is tagged "WhatsApp".
- **Address:** shown with a **Get directions** link, which opens a Google Maps search for the address.
- **Social:** with only a `name`, the network shows as plain text, e.g. "Instagram: Reign Fashion Empire". Add the full profile `url` (copy it from the browser's address bar on the profile page) and it becomes a link. The handles as written (with hyphens) aren't valid Instagram or TikTok usernames, so the real links are needed rather than guessed.
- **WhatsApp:** the WhatsApp card button, a floating chat button and a footer link open `https://wa.me/2348135759554` with a pre-written message that follows the service selected in the form, e.g. *"Hello Reign Fashion, I would like to make an enquiry about Aso-Ebi and traditional outfits."* Set `whatsappNumber` to `''` to hide them all.
- **Greeting wording:** to change it, edit `SERVICE_PHRASES` and `waGreeting()` in `js/script.js`.

---

## Replace images

### Owner photos (in use)

The **bridal** images are Reign Fashion's own gowns, supplied by the owner and saved in `assets/images/bridal/`. They fill the hero's main image, the Bridal collection panel, the Bridal Studio's two main images and three-photo strip, and the three Bridal gallery tiles. These slots no longer carry the `is-sample` tag.

### Temporary sample photos (other slots)

Until the owner's own photos arrive, every image slot holds a **licensed editorial-style stock photo from [Pexels](https://www.pexels.com/license/)** (free for commercial use, no attribution required), saved as WebP in `assets/images/` under the slot names below. They were chosen for an international, studio-lit look.

**Marking.** Each one is marked so visitors aren't misled into thinking it shows Reign Fashion's own work:

- **Visually:** a small **"Sample image"** tag, set by the `is-sample` class on its `.img-slot`.
- **For screen readers:** its `alt` text starts with "Sample image: …".

**Aso-Ebi slots** currently use occasion-wear gowns as stand-ins, because editorial Aso-Ebi stock is scarce. Replace these first with the owner's real Aso-Ebi photos.

**When a real photo arrives**, for each slot:

1. Save the photo over the sample file, or add a new file, and update the `<img src>`.
2. Rewrite the `alt` text to describe the real photo, without "Sample image:".
3. Remove `is-sample` from the slot's class list.
4. Update `width`/`height` if the size changed, and adjust or remove `style="object-position: …"`, which chooses the part of the photo kept visible when cropped.

**Before launch**, search `index.html` for `is-sample`. Any that remain are still stock photos.

Pexels photo IDs used (view at `https://www.pexels.com/photo/<id>/`): hero 39613153, 35618954, 33406001 · collections 32321496, 15579042, 30326211, 33933242, 31607121 · bridal 8770915, 32113448, 4623771, 34067487, 12305256 · services 36731204 · academy 9852958 · about 7148007 · gallery 38739046, 11506021, 10279699, 33290971, 11312739, 38965874, 14562236, 20808809, 38399171, 36909817, 16253943, 15248501.

### Image slots

Every image area is an `.img-slot` with a fixed aspect ratio, so the layout never jumps while images load. Without an `<img>` inside, a slot shows a labelled placeholder.

**To use a real photo:**

1. Export it at the size listed below and save it in `assets/images/`. Compress JPGs to roughly 150–400 KB; WEBP is also fine.
2. Put an `<img>` **inside** the matching slot. The placeholder label hides automatically:

```html
<!-- IMAGE SLOT: collection-aso-ebi — … -->
<div class="img-slot img-slot--wine" data-label="Collection · Aso-Ebi · 4:5">
  <img src="assets/images/collection-aso-ebi.jpg"
       alt="Guests in coordinated emerald Aso-Ebi at a wedding reception"
       width="1000" height="1250" loading="lazy" decoding="async">
</div>
```

- **`alt` text:** describe what is actually in the photo.
- **`width`/`height`:** set them to the file's real pixel size.
- **Loading:** use `loading="lazy"` for everything except the main hero image. For the hero, use `fetchpriority="high"` instead.

**Image slots** (each is marked `IMAGE SLOT:` in `index.html`):

| Slot | Suggested file | Shape | Min. size |
|---|---|---|---|
| Hero: main | `hero-bridal.jpg` | 4:5 portrait | 1200×1500 |
| Hero: overlap | `hero-men.jpg` | 3:4 portrait | 900×1200 |
| Hero: small | `hero-aso-ebi.jpg` | 1:1 square | 800×800 |
| Collection: Bridal | `collection-bridal.jpg` | 4:5 | 1400×1750 |
| Collection: Aso-Ebi | `collection-aso-ebi.jpg` | 4:5 | 1000×1250 |
| Collection: Ready-to-Wear | `collection-ready-to-wear.jpg` | 4:5 | 900×1125 |
| Collection: Kiddies | `collection-kids.jpg` | 4:5 | 900×1125 |
| Collection: Men | `collection-men.jpg` | 4:5 | 900×1125 |
| Bridal: main | `bridal-main.jpg` | 2:3 portrait | 1200×1800 |
| Bridal: detail | `bridal-detail.jpg` | 1:1 | 700×700 |
| Bridal: strip ×3 | `bridal-gallery-1…3.jpg` | 3:4 | 600×800 |
| Services: atelier | `services-atelier.jpg` | 3:2 landscape | 1400×933 |
| Academy | `academy-training.jpg` | 3:2 landscape | 1400×933 |
| About: studio | `about-studio.jpg` | 4:5 | 1200×1500 |
| Social share image | `og-image.jpg` | 1200×630 | 1200×630 |

**Gallery tiles** (`#gallery`) work the same way. See the comment above the gallery in `index.html`.

- **Titles:** update `.g-item__title`.
- **Categories:** keep `data-category` as one of `bridal`, `aso-ebi`, `ready-to-wear`, `kids`, `men`.
- **Tile shape:** choose with `g-item--tall` or `g-item--wide`, or neither for a standard tile.
- **Larger lightbox image (optional):** add `data-full="assets/images/gallery/…-large.jpg"` to the `<img>`.
- **Adding or removing tiles:** copy or delete an `<li class="g-item">`. Filters and counts update automatically.

> Only publish photos Reign Fashion Empire owns or has permission to use, and only label designs as Reign Fashion work if they are.

---

## Logo and icons

All brand files were generated from the supplied logo (`assets/images/brand/logo-original.jpg`, gold on deep green). The gold was separated from the background by brightness, with soft edges preserved.

| File | Used for |
|---|---|
| `brand/logo-mark-mask.png` | RF monogram, header (as a CSS mask) |
| `brand/logo-wordmark-mask.png` | "REIGN / FASHION EMPIRE", header (as a CSS mask) |
| `brand/logo-full-gold.webp` / `.png` | Full metallic logo on a transparent background, footer |
| `brand/og-image.jpg` | 1200×630 social share image |
| `favicon-48.png`, `apple-touch-icon.png` | Browser tab and home-screen icons |

**Header.** The header uses a horizontal **lockup** (monogram beside the wordmark). The stacked original would be unreadably small at header height. Both parts are white PNGs used as CSS masks, so their colour is set in CSS, in `.brand` in `css/style.css`:

- `--logo-gold` is the monogram's gold gradient.
- `--logo-ink` is the wordmark colour: ink on the ivory header, white when the mobile menu is open.

**Replacing the logo.** If the designer supplies official vector/SVG or transparent PNG files, swap them in under the same names, keeping each file's aspect ratio or updating the `aspect-ratio` values in `.brand__mark` and `.brand__word`.

---

## Backend: enquiry storage (PHP + MySQL)

The enquiry form is connected to a small built-in backend (`SITE_CONFIG.formEndpoint = 'api/enquiry.php'`). Every enquiry is **saved to the database first**, so nothing is lost even if email fails. The customer then sees a reference number such as `RF-2610-0007`.

```
api/enquiry.php          Public endpoint the form posts to
backend/                 Private: browsers get 403 Forbidden (backend/.htaccess)
├── bootstrap.php        Config loader, database connection, shared helpers
├── config.example.php   Settings template (safe to share)
├── config.php           Real settings + generated passwords. NEVER share or upload publicly
├── install.php          One-time installer (command line only)
├── schema.sql           Tables: enquiries, enquiry_files
└── storage/uploads/     Customers' inspiration images, stored under random names
```

**Set up locally (XAMPP).** Start **Apache** and **MySQL** in the XAMPP Control Panel, then run:

```
C:\xampp\php\php.exe C:\xampp\htdocs\reignfashion\backend\install.php
```

This does three things:

- creates the `reignfashion` database
- creates a `reign_app` user that can access **only** that database, with a random password
- writes `backend/config.php`

It's safe to re-run.

**What the endpoint does:**

- re-checks every field on the server, including that event dates aren't in the past
- checks uploads by their real content: JPG/PNG/WEBP only, up to 3 files of 5 MB each, saved with random names
- blocks spam: a hidden honeypot field, plus a limit of 5 enquiries per visitor per 10 minutes. Visitors are identified by a salted hash, and raw IP addresses are never stored.
- sends an email notification to `mail_to` when `mail_from` is set in `config.php`. The sender must be an address on the website's own domain, and Reply-To is set to the customer.

**Local email note:** XAMPP can't send email out of the box. Locally, enquiries are saved but no email goes out. That's expected. On a live host, set `mail_from` in `backend/config.php`.

### Admin inbox

Open **`/admin/`** (locally <http://localhost/reignfashion/admin/>) and log in.

- **Inbox:** filter tabs (All / New / Contacted / Closed), search by name, email, phone or reference, and pages of 20.
- **Enquiry page:** full details and inspiration images, plus one-tap **WhatsApp**, **Call** and **Email** buttons with a pre-written greeting quoting the reference. It also has status buttons, private notes and a delete action that also removes the images from disk.
- **Account:** change your password.

**Settings page (`admin/settings.php`).** The owner edits these without touching code:

- **Contact:** WhatsApp number, two phone numbers, email, studio address and opening hours
- **Social:** profile links and account names for Instagram, Facebook and TikTok
- **Academy:** duration, fees and next intake

How it works:

- **Validation:** phone numbers must look real. Social links must be full `https://` links on the right site; for example, an Instagram field only accepts instagram.com.
- **Saving:** values are stored in the `settings` table.
- **Publishing:** `api/settings.php` serves them to the website, which updates its contact section, footer, WhatsApp buttons and Academy details within about a minute.
- **Empty fields** show "To be confirmed".
- **Fallback:** the values in `SITE_CONFIG` (`js/script.js`) are used if the settings can't be loaded, for example when `index.html` is opened directly as a file.

**Gallery manager (`admin/gallery.php`).** This controls the "Selected Creations" section.

- **Add a design:** choose a photo, then set:
  - **title** and **category** (Bridal, Aso-Ebi, Ready-to-Wear, Kiddies, Men)
  - **tile shape**: standard, tall or wide
  - **crop focus**: which part of the photo stays in view (top for faces and gele, lower for hems and trains)
  - a screen-reader description
  - whether it's a stock **sample** photo, which shows the tag

  New designs go to the top of the gallery.
- **Manage:** reorder with ↑ / ↓, hide or show, edit or remove. The website follows the order shown in the admin.
- **Resizing:** photos are resized in the browser to at most 1600px WebP before upload. With PHP's GD extension (enabled on almost every host), the server also re-encodes them, which strips metadata. Locally, enable GD by removing the `;` before `extension=gd` in `C:\xampp\php\php.ini`, then restarting Apache.
- **Upload checks:** files must be real JPG/PNG/WebP images of at least 300×300px and at most 8 MB. They're saved under random names in `assets/uploads/gallery/`. That folder only ever serves image files: scripts and other file types are refused (403).
- **Live loading:** the website loads the gallery from `api/gallery.php`. The tiles hard-coded in `index.html` remain as the fallback and stay interactive while the live gallery loads.
- **First run:** the installer imported the 12 original tiles, so they can be managed like any other design. Removing an original tile never deletes its image file; only uploaded files are deleted from disk.
- **Grid gaps:** the grid packs tiles automatically, but mixing many tall and wide tiles can leave a gap at the end. If that happens, change one tile's shape.

**Testimonials (`admin/testimonials.php`).** The owner publishes genuine client feedback.

- **Each entry has:** the quote (up to 600 characters), the client's name as they'd like it shown (e.g. "Adaeze O."), and the service (Bridal, Aso-Ebi, Academy Student and so on).
- **Permission is required:** a testimonial can't be saved unless the owner ticks **"The client agreed to have these words and their name shown"**. Only real, approved feedback is published.
- **Management:** reorder with ↑ / ↓, hide or show, edit or remove. Quotation marks typed around a quote are removed automatically, because the design adds its own.
- **On the site:** the carousel loads published testimonials from `api/testimonials.php` and replaces the three placeholder slides. With none published, the placeholders stay. With one, the arrows and dots are hidden.

**Admin accounts are created from the command line:**

```
C:\xampp\php\php.exe backend\create-admin.php --email=owner@example.com --name="Owner Name"
```

A strong password is generated and shown once. Running the same command again for an existing email **resets** that account's password, which is how to recover a forgotten one.

**Security built in:**

- Passwords are stored only as secure hashes.
- After 5 wrong passwords for an account (or 10 from one device), logins are locked for 15 minutes.
- Sessions are protected against hijacking: the session changes at login, cookies are HTTP-only and SameSite=Strict, and you're logged out after 2 hours idle.
- Every action form carries a CSRF token. Logout is a button, not a link.
- Admin pages tell search engines not to index them and refuse to load inside other sites' frames.
- Customer images are only served to logged-in admins, through `admin/file.php`.

**Alternative:** to use Formspree instead of the built-in backend, set `formEndpoint` to your Formspree URL. Note that file uploads need a paid Formspree plan.

**Consent text:** the consent sentence under the form is **draft wording**. Have Reign Fashion Empire review it. If a privacy policy page is added later, link to it from that sentence and from the footer.

---

## Update placeholder content

Search `index.html` for these markers:

| Marker | What it is |
|---|---|
| `IMAGE SLOT:` | Photo placeholders (see above) |
| `DRAFT` / `PROVISIONAL` | Copy written for review (services, bridal services, About, consent) |
| `PLACEHOLDER` | Testimonials and Academy details |

**Academy (`#academy`).**

- **Confirmed by the owner:** the levels (Beginner, Intermediate, Advanced, Professional Mastery), the six course topics, and the theory-plus-practice approach.
- **Still to fill:** in "Schedule & fees", replace each `<dd class="is-pending">To be confirmed</dd>` (Duration, Fees, Next intake) with the real detail and remove `class="is-pending"`. Then delete the "To be confirmed" tag beside that heading.

**Testimonials (`#testimonials`).**

- Replace each slide's quote, name and service with **genuine** feedback, shared with the client's permission.
- Delete the `<span class="testimonial__tag">Placeholder</span>` lines.
- Add or remove `<li class="carousel__slide">` items freely; the dots and labels rebuild automatically.
- Do not add invented reviews, names, photos or star ratings.

**About (`#about`).** The brand story is deliberately generic. Replace it with Reign Fashion's real story. Only add a founder name, founding year, awards or client numbers if they are confirmed.

---

## Before going live

**Content from the owner**

- [x] WhatsApp number (0813 575 9554) and phone numbers
- [x] Business email
- [x] Studio address
- [ ] Opening hours
- [ ] Instagram, Facebook and TikTok **profile links** (names are in; URLs needed)
- [x] Logo
- [ ] Real photography for all image slots and gallery tiles
- [x] Academy levels and course topics
- [ ] Academy duration, fees and next intake
- [ ] Genuine testimonials, with permission
- [ ] Approval of draft copy: services, bridal services, About, consent wording

**Technical**

- [ ] Finish `SITE_CONFIG` in `js/script.js` (hours, social URLs)
- [ ] Connect the form (`formEndpoint`) and send a test enquiry end to end
- [ ] Update Open Graph tags in `<head>`: replace `example.com` in `og:url` and `og:image` with the real domain (the image is ready at `assets/images/brand/og-image.jpg`)
- [ ] Add `<link rel="canonical" href="https://your-domain/">`
- [ ] Compress all images; check every `<img>` has accurate `alt`, `width` and `height`
- [ ] Click every WhatsApp button on a phone and confirm it opens the right chat
- [ ] Test on real devices (iPhone Safari, Android Chrome) and desktop browsers
- [ ] Optional: add `robots.txt` and `sitemap.xml`, and register with Google Search Console

---

## Deploy

The site is static, so any web host works. Upload the whole folder so `index.html` sits at the web root:

- **Shared hosting (cPanel etc.):** upload via File Manager or FTP into `public_html/`. Use hosting with PHP 8 + MySQL for the enquiry backend. Create a database in cPanel, copy `backend/config.example.php` to `backend/config.php` and fill in its details, then import `backend/schema.sql` with phpMyAdmin.
- **Netlify / Vercel / Cloudflare Pages / GitHub Pages:** drag and drop the folder or connect a repo. These hosts don't run PHP, so use Formspree (Option A) for the form.

After deploying:

- Enable **HTTPS**.
- Re-test the form and WhatsApp links on the live URL.
- Check the social share preview with a tool such as the Facebook Sharing Debugger.

---

## Accessibility and performance notes

**Accessibility**

- **Structure:** semantic landmarks, one `h1`, and headings that never skip a level.
- **Keyboard:** a skip link, and visible focus outlines on every interactive element.
- **Mobile menu:** focus moves into the menu and stays there until it closes; Escape closes it.
- **Gallery and lightbox:** filters announce result counts. The lightbox uses the native `<dialog>` (focus contained, Escape closes, focus returns to the tile) and supports arrow keys and swipe.
- **Carousel:** no auto-rotation, previous/next buttons and dots, arrow keys and swipe. Off-screen slides can't be reached by keyboard or screen reader.
- **Form:** every field is labelled, errors are linked to their fields and announced, focus moves to the first error, and the phone field only becomes required when needed.
- **Motion:** with *reduce motion* switched on in the device settings, all animation and transitions are turned off.
- **Contrast:** every text and background pairing in the green-and-gold palette meets WCAG AA. Small gold text uses a darker gold (`--c-gold-deep`) on light backgrounds; the logo gold is used directly on green.

**Performance**

- No JavaScript libraries. Icons are inline SVG.
- Fonts load with `display=swap`.
- Every image area has a fixed aspect ratio, so there's no layout shift. Use `loading="lazy"` on below-the-fold images.

**Tested** at 360, 390, 768, 1024 and 1440 px wide, with no horizontal overflow.

---

## Credits

- **Fonts:** [Cormorant Garamond](https://fonts.google.com/specimen/Cormorant+Garamond) and [Manrope](https://fonts.google.com/specimen/Manrope), via Google Fonts (SIL Open Font License).
- **Icons:** [Lucide](https://lucide.dev) v0.453.0 (ISC License), embedded as inline SVG.
