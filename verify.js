const { chromium } = require('@playwright/test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
(async () => {
  const browser = await chromium.launch({ executablePath: 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe', headless: true });
  const page = await browser.newPage();
  const errors = [];
  page.on('pageerror', error => errors.push(error.message));
  fs.mkdirSync('validation', { recursive: true });
  for (const width of [1440, 1024, 768, 390, 320]) {
    await page.setViewportSize({ width, height: 1000 });
    await page.goto('http://localhost:3000');
    await page.evaluate(async () => {
      for (let y = 0; y < document.body.scrollHeight; y += 600) {
        window.scrollTo({ top: y, behavior: 'instant' });
        await new Promise(resolve => setTimeout(resolve, 40));
      }
    });
    await page.waitForTimeout(800);
    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true, `Overflow at ${width}`);
    const badImages = await page.locator('img').evaluateAll(images => images.filter(img => img.getClientRects().length > 0 && (!img.complete || img.naturalWidth === 0)).map(img => img.src));
    assert.deepEqual(badImages, [], `Broken images at ${width}`);
    const brokenAnchors = await page.locator('a[href^="#"]').evaluateAll(links => links.filter(link => !document.querySelector(link.getAttribute('href'))).map(link => link.href));
    assert.deepEqual(brokenAnchors, []);
    await page.evaluate(() => window.scrollTo(0, 0));
    await page.waitForTimeout(600);
    await page.screenshot({ path: `validation/home-${width}.png`, fullPage: true });
    if (width < 1001) {
      await page.locator('.menu-toggle').click();
      assert.equal(await page.locator('.menu-toggle').getAttribute('aria-expanded'), 'true');
    }
    await page.locator('.dropdown > button').first().click();
    await page.locator('#products-menu').waitFor({ state: 'visible' });
    await page.keyboard.press('Escape');
    assert.equal(await page.locator('.dropdown > button').first().getAttribute('aria-expanded'), 'false');
    console.log(`${width}px: no overflow, all images loaded, anchors valid, menus work`);
  }
  await page.locator('[name="name"]').fill('QA Example');
  await page.locator('[name="email"]').fill('invalid');
  assert.equal(await page.locator('#quote-form').evaluate(form => form.checkValidity()), false);
  await page.locator('[name="email"]').fill('qa@example.com');
  await page.locator('[name="product"]').selectOption('Aluminium Profiles');
  await page.locator('[name="message"]').fill('Please discuss our profile requirements.');
  await page.locator('#quote-form button').click();
  await page.locator('.email-draft').waitFor({ state: 'visible' });
  assert.match(await page.locator('.email-draft').getAttribute('href'), /^mailto:info@extekprofiles.com\?/);
  assert.match(await page.locator('.form-status').textContent(), /Nothing has been sent yet/);
  await page.emulateMedia({ reducedMotion: 'reduce' });
  await page.reload();
  assert.equal(await page.locator('.reveal.ready').count(), 0);
  assert.deepEqual(errors, []);
  console.log('Form validation, email draft, reduced motion and JavaScript checks passed.');
  await browser.close();
})().catch(error => { console.error(error); process.exit(1); });
