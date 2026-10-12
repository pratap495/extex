const fs = require('node:fs');
const path = require('node:path');
fs.mkdirSync('dist/assets', { recursive: true });
for (const name of ['index.html', 'styles.css', 'fonts.css']) fs.copyFileSync(name, path.join('dist', name));
fs.copyFileSync('assets/homepage.js', 'dist/assets/homepage.js');
for (const name of ['favicon.ico', 'favicon.png']) fs.copyFileSync(path.join('assets', name), path.join('dist', name));
// Remove the obsolete browser entrypoint from earlier local builds.
fs.rmSync('dist/app.js', { force: true });
const html = fs.readFileSync('index.html', 'utf8');
const css = fs.readFileSync('styles.css', 'utf8');
const assets = new Set([...`${html}\n${css}`.matchAll(/assets\/([\w.-]+\.webp)/g)].map(match => match[1]));
for (const name of assets) fs.copyFileSync(path.join('assets', name), path.join('dist/assets', name));
for (const name of fs.readdirSync('assets').filter(name => name.endsWith('.ttf'))) fs.copyFileSync(path.join('assets', name), path.join('dist/assets', name));
console.log(`Built homepage with ${assets.size} local image assets in dist/.`);
