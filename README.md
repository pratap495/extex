# Extek Profiles homepage

A complete local homepage in HTML, CSS and JavaScript. The workspace was empty, so no existing routes, backend or application files were changed.

## Run

```sh
npm install
npm run build
npm run dev
```

Open http://localhost:3000. Deployable static files are in `dist/`. The homepage can also be opened directly from `index.html`.

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
- `app.js`: menus, reveal animations and email draft preparation.
- `assets/`: optimized local WebP images and source assets.
- `build.js`, `server.js`: dependency-free static build and local preview.
- `verify.js`: browser validation.
- `research/`: downloaded reference HTML and asset review contact sheets.

The site has been built and tested locally; no hosted publication was requested.
