const fs = require('node:fs');
const path = require('node:path');
fs.mkdirSync('dist/assets', { recursive: true });
// Remove old generated files so deleted pages cannot remain reachable locally.
for (const name of fs.readdirSync('dist')) {
  if (name.endsWith('.html')) fs.rmSync(path.join('dist', name), { force: true });
}
const pages = ['index.html', 'about.html', ...require('./generate-pages.js')];
for (const name of [...pages, 'styles.css', 'fonts.css']) fs.copyFileSync(name, path.join('dist', name));
// Keep a clean, dedicated About route as well as the direct about.html file.
// This prevents an About navigation from resolving to the homepage on static hosts.
fs.mkdirSync(path.join('dist', 'about'), { recursive: true });
const aboutRouteHtml = fs.readFileSync('about.html', 'utf8').replace('<head>', '<head><base href="/">');
fs.writeFileSync(path.join('dist', 'about', 'index.html'), aboutRouteHtml);
fs.mkdirSync(path.join('dist', 'login'), { recursive: true });
const loginRouteHtml = fs.readFileSync('login.html', 'utf8').replace('<head>', '<head><base href="/">');
fs.writeFileSync(path.join('dist', 'login', 'index.html'), loginRouteHtml);
fs.copyFileSync('assets/homepage.js', 'dist/assets/homepage.js');
for (const name of ['favicon.ico', 'favicon.png']) fs.copyFileSync(path.join('assets', name), path.join('dist', name));
// Remove the obsolete browser entrypoint from earlier local builds.
fs.rmSync('dist/app.js', { force: true });
const html = pages.map(name => fs.readFileSync(name, 'utf8')).join('\n');
const css = fs.readFileSync('styles.css', 'utf8');
const assets = new Set([...`${html}\n${css}`.matchAll(/assets\/([\w.-]+\.webp)/g)].map(match => match[1]));
for (const name of assets) fs.copyFileSync(path.join('assets', name), path.join('dist/assets', name));
for (const name of fs.readdirSync('assets').filter(name => name.endsWith('.ttf'))) fs.copyFileSync(path.join('assets', name), path.join('dist/assets', name));
console.log(`Built ${pages.length} pages with ${assets.size} local image assets in dist/.`);
