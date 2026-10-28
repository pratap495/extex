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
    await page.emulateMedia({ reducedMotion: 'reduce' });
    const files = fs.readdirSync('dist').filter(name => name.endsWith('.html'));
    for (const file of files) {
      for (const width of [1440, 390, 320]) {
        await page.setViewportSize({ width, height: 1000 });
        const response = await page.goto('http://localhost:3000/' + file);
        assert.equal(response.status(), 200);
        await page.evaluate(() => document.fonts.ready);
        await page.evaluate(async () => {
          for (let y = 0; y < document.body.scrollHeight; y += 700) {
            window.scrollTo({ top: y, behavior: 'instant' });
            await new Promise(resolve => setTimeout(resolve, 25));
          }
        });
        await page.waitForTimeout(150);
        assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true, `${file}: overflow at ${width}`);
        assert.equal(await page.locator('h1').count(), 1, file);
        const broken = await page.locator('img').evaluateAll(images => images.filter(img => img.getClientRects().length && (!img.complete || !img.naturalWidth)).map(img => img.src));
        assert.deepEqual(broken, [], file);
        if (width === 1440) {
          for (const href of await page.locator('a[href]').evaluateAll(links => links.map(link => link.getAttribute('href')))) {
            if (/^(https?:|mailto:|tel:)/.test(href)) continue;
            const url = new URL(href, 'http://localhost:3000/' + file);
            const target = path.join('dist', url.pathname === '/' ? 'index.html' : url.pathname.endsWith('/') ? decodeURIComponent(url.pathname.slice(1)) + 'index.html' : decodeURIComponent(url.pathname.slice(1)));
            assert.ok(fs.existsSync(target), `${file}: missing ${href}`);
            if (url.hash) assert.ok(fs.readFileSync(target, 'utf8').includes(`id="${decodeURIComponent(url.hash.slice(1))}"`), `${file}: missing anchor ${href}`);
          }
        }
        if (file === 'products.html' && width === 1440) {
          await page.evaluate(() => window.scrollTo({ top: 0, behavior: 'instant' }));
          await page.screenshot({ path: path.join(os.tmpdir(), 'extek-products-desktop.png'), fullPage: true });
        }
      }
      console.log(`${file}: desktop/mobile layout, images and local links passed`);
    }
    await page.goto('http://localhost:3000/products.html');
    await page.locator('[data-product-filter]').selectOption('Architectural');
    assert.equal(await page.locator('[data-category]:not([hidden])').count(), 3);
    await page.locator('[data-product-filter]').selectOption('all');
    assert.equal(await page.locator('[data-category]:not([hidden])').count(), 12);
    for (const file of ['contact.html?product=Aluminium%20Profiles', 'partners-procurement.html', 'sales-marketing.html', 'careers.html?role=Production%20Manager']) {
      await page.goto('http://localhost:3000/' + file);
      const form = page.locator('form[data-inquiry]');
      assert.equal(await form.evaluate(element => element.checkValidity()), false);
      await form.locator('[name="name"]').fill('QA Example');
      await form.locator('[name="email"]').fill('qa@example.com');
      await form.locator('[name="phone"]').fill('+91 98765 43210');
      await form.locator('[name="message"]').fill('Please discuss this enquiry with our team.');
      if (file.startsWith('contact')) assert.equal(await form.locator('[name="product"]').inputValue(), 'Aluminium Profiles');
      if (file.startsWith('careers')) assert.equal(await form.locator('[name="role"]').inputValue(), 'Production Manager');
      assert.equal(await form.evaluate(element => element.checkValidity()), true);
      await form.locator('button[type="submit"]').click();
      await form.locator('.email-draft').waitFor({ state: 'visible' });
      assert.match(await form.locator('.email-draft').getAttribute('href'), /^mailto:info@extekprofiles.com\?/);
      assert.match(await form.locator('.form-status').textContent(), /Nothing has been sent yet/);
    }
    await page.goto('http://localhost:3000/careers.html');
    await page.locator('.job-card summary').first().click();
    assert.equal(await page.locator('.job-card').first().getAttribute('open'), '');
    assert.deepEqual(errors, []);
    console.log('Product filters, all inquiry forms, query prefills and job accordions passed.');
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
