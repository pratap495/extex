# Extek Profiles homepage

## Complete static website

The project now includes 15 local pages, all using the existing green/slate design:

| Page | File |
| --- | --- |
| Home | `index.html` |
| About | `about.html` |
| Product catalogue (11 categories) | `products.html` |
| Quality assurance | `quality.html` |
| Profile cutting | `profile-cutting.html` |
| Fabrication | `fabrication.html` |
| Profile design | `profile-design.html` |
| Materials | `materials.html` |
| Perforated aluminium | `perforated-aluminium.html` |
| Industries (6 sectors) | `industries.html` |
| Partners and procurement | `partners-procurement.html` |
| Sales and marketing | `sales-marketing.html` |
| Careers | `careers.html` |
| Contact | `contact.html` |
| Client portal handoff | `client-login.html` |

`generate-pages.js` contains the remaining 13 pages' content and reusable layouts, and derives their header/footer from `index.html`. `npm run build` regenerates those pages before copying all 15 into `dist/`. Edit that generator for generated-page content changes; direct changes to those 13 HTML files will be overwritten at build time. Home and About remain hand-authored HTML.

Content is adapted from the corresponding company pages at https://extekprofiles.com/, with grammar cleaned up. The full catalogue, quality stages, fabrication methods, design resources, material properties, industry descriptions and business enquiry fields are represented. Illustrations are used where suitable verified photographs are unavailable. No new certifications, employee counts or production statistics have been added.

Vendor, dealer, career and contact forms validate inputs and prepare an email draft addressed to `info@extekprofiles.com`; visitors must send it from their email application. Career applicants must attach their CV to the email themselves. Files are not uploaded and forms do not claim that a message has been sent. Product and job links prefill the relevant enquiry field.

The Client Login page hands off to the original HTTPS Extek portal, without collecting credentials or simulating authentication. A local authenticated client area needs the actual authentication/backend integration. Job descriptions are identified as roles listed on the original company site; current availability requires confirmation. The original site's Blog link points to its homepage, so it now points to the local homepage rather than an invented blog.

With `npm run dev` running, use `npm run test:site` for all-page desktop/mobile checks, link/image validation, catalogue filters, inquiry validation and prefills, job accordions and the portal handoff. `npm run test:production` checks HTTP responses for all 15 production HTML files and static assets. Existing `npm test` and `npm run test:about` verify navigation and interactions across five widths.

The website remains a static Vercel deployment using `vercel.json`; no browser code is evaluated as a server function. Changes are local until committed, pushed and successfully redeployed.

A complete local homepage in HTML, CSS and JavaScript. The workspace was empty, so no existing routes, backend or application files were changed.

## Run

```sh
npm install
npm run build
npm run dev
```

Open http://localhost:3000. Deployable static files are in `dist/`. The homepage can also be opened directly from `index.html`.

The About page is at http://localhost:3000/about.html (or open `about.html` directly). Homepage navigation and company links now open this local page. It shares the header, footer, green/slate palette and browser interactions. Its company introduction, vision, mission, values and sustainability goal are adapted from https://extekprofiles.com/view/about with grammar cleaned up. No experience statistics or completed sustainability reductions are claimed.

`npm run test:about` checks the new page at desktop, tablet and mobile widths, including local links, images, dropdowns, reduced motion and navigation from the homepage. Run the preview server first. The shared browser script registers the quote form handler only on pages that contain the form.

## Validation

`npm test` runs browser checks using the locally installed Microsoft Edge on Windows, with the preview server running. Update the executable path in `verify.js` for other environments.

Verified at 1440, 1024, 768, 390 and 320 pixels: image loading, overflow, section anchors, dropdowns, mobile navigation, Escape dismissal, form validation, email draft creation, reduced motion and JavaScript errors. Screenshots are in `validation/`.

## Content and brand sources

- Official company content and contacts: https://extekprofiles.com/
- Product categories: https://extekprofiles.com/view/products
- Industries: https://extekprofiles.com/view/industries-we-serve
- Exact brand tokens from the company's `/css/custom.css`: lime `#86c659`, slate `#2a3e49`. A downloaded copy is retained in `assets/brand-source.css`.
- Logo, hero, about, pipes, rods, profiles and industry photographs come from the company's existing public assets. Factory images are described as images featured by Extek; ownership of the depicted facility is not assumed.
- Supplementary product illustrations: `tubes.webp` from https://www.prohrominox.com/img/proizvodi/aluminijum/cevi/kvadratne-cevi.jpg ; `industrial.webp` from https://image.made-in-china.com/2f0j00JDlcuHOhCUkp/Kitchen-40-40-Industrials-Material-Slot-T-Track-Extrusion-Aluminium-Profile.jpg ; `extrusions.webp` from https://www2.tubefittings.eu/48216-medium_default/profile-aluminium-carre-15x15x1-5-mm-brut-200-cm.jpg . These are illustrative reference images, not verified Extek product photographs; usage rights have not been verified for public release.
- The initial ThemeForest preview was blocked. The revised design uses the supplied full-homepage screenshots and the accessible direct demo at https://live.themewild.com/manrox/ as visual references. It recreates the reference composition with Extek content, green accents and slate brand surfaces. Unverified ratings, statistics, pricing packages, staff profiles and subscriptions are replaced with relevant product, material, process and inquiry sections.
- Typography uses locally hosted Sora and Roboto from Google Fonts; `fonts.css` declares the font faces. No external font requests are needed during browsing.

The form prepares an email link after browser validation. It has no backend, does not transmit or store inquiry data, and explicitly tells visitors that nothing has been sent. Social accounts and operating hours were omitted because the source contained generic social platform links and no verified hours.

## Files

- `index.html`: all homepage sections and semantic content.
- `styles.css`: responsive layouts, centralized brand tokens and motion preferences.
- `fonts.css`: locally hosted Sora and Roboto font declarations.
- `assets/homepage.js`: browser-only menus, reveal animations and email draft preparation.
- `assets/`: optimized local WebP images and source assets.
- `build.js`, `server.js`: dependency-free static build and local preview.
- `verify.js`: browser validation.
- `research/`: downloaded reference HTML and asset review contact sheets.

The site has been built and tested locally; no hosted publication was requested.

## Vercel deployment fix

This is a static website, not React, Next.js or an SSR application. `build.js` copies the homepage, styles, browser JavaScript, fonts and images into `dist/`. `server.js` is only a local preview server; Vercel should serve `dist/` directly.

The former root `app.js` accessed `document.querySelector` immediately at module initialization. Loading it in Node reproduced `ReferenceError: document is not defined` at line 1. It is now `assets/homepage.js`, loaded only by the deferred HTML script tag. Browser APIs inside this script and inside Playwright browser evaluation callbacks are intentional. The only installed dependencies are Playwright development tools; no runtime third-party library is imported by the homepage or preview server.

`vercel.json` explicitly selects the Other/static preset (`framework: null`), runs `npm run build`, and publishes `dist`. No function handler or catch-all function rewrite is required. Both favicon paths now have static files based on Extek's existing favicon asset. Styling, homepage copy, imagery and animation behavior are preserved.

Run `npm run build` and `npm run test:production`. The production check launches the built output on a temporary port and checks `/`, `/favicon.ico`, `/favicon.png`, JavaScript and styles return 200 with correct content types and file bytes, and missing paths return 404. Run `npm test` with the local preview server running for responsive UI and interaction checks.

The reported live URL is https://extex-topaz.vercel.app/. Its homepage and ICO request returned HTTP 500 before the fix. The live function's entrypoint selection cannot be confirmed without Vercel project settings or a full stack trace, but the exact local initialization error has been reproduced. Production recovery requires deploying this commit. No authenticated Vercel account or deployment configuration was available locally for verification of the redeployed site.

Commit and push from the repository's `main` branch:

```sh
git add app.js assets/homepage.js assets/favicon.png assets/favicon.ico index.html build.js server.js vercel.json verify-production.js package.json README.md
git commit -m "Fix Vercel deployment by serving the homepage as static files"
git push origin main
```

In Vercel, the project root must be this repository folder. The checked-in config sets Framework Preset to Other, Build Command to `npm run build`, and Output Directory to `dist`. Redeploy the new commit if automatic Git deployments are disabled. Remove any separate dashboard routing override that sends static requests to an old function, if one exists.
