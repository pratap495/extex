const { chromium } = require('@playwright/test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const os = require('node:os');

(async () => {
  const browser = await chromium.launch({ executablePath: 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe', headless: true });
  try {
    const page = await browser.newPage();
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    for (const width of [1440, 1024, 768, 390, 320]) {
      await page.setViewportSize({ width, height: 1000 });
      await page.goto('http://localhost:3000/about/');
      await page.evaluate(() => document.fonts.ready);
      await page.evaluate(async () => {
        for (let y = 0; y < document.body.scrollHeight; y += 650) {
          window.scrollTo({ top: y, behavior: 'instant' });
          await new Promise(resolve => setTimeout(resolve, 50));
        }
      });
      await page.waitForTimeout(800);
      assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true, `Overflow at ${width}`);
      assert.equal(await page.locator('h1').count(), 1);
      assert.equal(await page.locator('.navigation a[aria-current="page"]').textContent(), 'About Us');
      const broken = await page.locator('img').evaluateAll(images => images.filter(img => img.getClientRects().length && (!img.complete || !img.naturalWidth)).map(img => img.src));
      assert.deepEqual(broken, []);
      for (const href of await page.locator('a').evaluateAll(links => links.map(link => link.getAttribute('href')).filter(href => href && !/^(https?:|tel:|mailto:)/.test(href)))) {
        const [file, fragment] = href.split('#');
        const target = !file ? 'about/index.html' : file.endsWith('/') ? file.replace(/^\//, '') + 'index.html' : file.replace(/^\//, '');
        const html = fs.readFileSync(path.join('dist', target), 'utf8');
        if (fragment) assert.ok(html.includes(`id="${fragment}"`), `Missing target ${href}`);
      }
      await page.evaluate(() => window.scrollTo({ top: 0, behavior: 'instant' }));
      await page.waitForTimeout(200);
      if (width < 1001) {
        await page.locator('.menu-toggle').click();
        assert.equal(await page.locator('.menu-toggle').getAttribute('aria-expanded'), 'true');
      }
      assert.equal(await page.locator('.source-navigation > a').count(), 8);
      await page.locator('[aria-controls="quality-menu"]').click();
      assert.equal(await page.locator('#quality-menu a').count(), 7);
      await page.keyboard.press('Escape');
      assert.ok(await page.locator('[data-icon] svg').count() > 0);
      if (width === 1440 || width === 390) await page.screenshot({ path: path.join(os.tmpdir(), `extek-about-${width}.png`), fullPage: true });
      console.log(`${width}px: About page images, layout, links, icons and menus passed`);
    }
    await page.goto('http://localhost:3000');
    await page.locator('.menu-toggle').click();
    await page.locator('.navigation a[href="about.html"]').click();
    assert.ok(page.url().endsWith('/about.html'));
    await page.emulateMedia({ reducedMotion: 'reduce' });
    await page.reload();
    assert.equal(await page.locator('.reveal.ready').count(), 0);
    assert.deepEqual(errors, [], 'Shared navigation must run safely without a homepage quote form');
    console.log('Homepage-to-About navigation, reduced motion and shared JavaScript passed.');
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
