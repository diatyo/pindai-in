const assert = require('node:assert/strict');
const { createRequire } = require('node:module');
const path = require('node:path');
const fs = require('node:fs');
let playwright;
try { playwright = require('playwright'); }
catch {
  const modules = process.env.PINDAI_PLAYWRIGHT_MODULES || path.join(process.env.USERPROFILE || '', '.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules');
  playwright = createRequire(path.join(modules, '__pindai_test.cjs'))('playwright');
}
const { chromium } = playwright;

(async () => {
  const base = process.env.PINDAI_DESKTOP_TEST_URL;
  assert.match(base || '', /^http:\/\/127\.0\.0\.1:\d+\/$/);
  const chrome = process.env.PINDAI_CHROME || 'C:/Program Files/Google/Chrome/Application/chrome.exe';
  const browser = await chromium.launch({headless: true, ...(fs.existsSync(chrome) ? {executablePath: chrome} : {})});
  try {
    const page = await browser.newPage();
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    await page.goto(base + 'login');
    const manifestLink = await page.locator('link[rel=manifest]').getAttribute('href');
    assert(manifestLink, 'Every login page should declare the home-screen manifest.');
    const manifestResponse = await page.request.get(manifestLink);
    assert.equal(manifestResponse.status(), 200);
    const manifest = await manifestResponse.json();
    const icon = new URL(manifest.icons[0].src, manifestLink).href;
    const imageSize = await page.evaluate(src => new Promise((resolve,reject) => {
      const image=new Image(); image.onload=()=>resolve([image.naturalWidth,image.naturalHeight]); image.onerror=reject; image.src=src;
    }), icon);
    assert.deepEqual(imageSize, [1254,1254]);
    const cdp = await page.context().newCDPSession(page);
    const parsedManifest = await cdp.send('Page.getAppManifest');
    assert.deepEqual(parsedManifest.errors.filter(error => error.critical), []);
    await page.locator('[name=username]').fill('admin.uji');
    await page.locator('[name=password]').fill('Only-Test-Init-2026!');
    await Promise.all([page.waitForURL(/\/admin\/?$/), page.locator('button[type=submit]').click()]);
    await page.goto(base + 'admin/settings');
    await page.locator('[name=school_name]').waitFor();
    assert.equal(await page.locator('[name=school_name]').inputValue(), 'Sekolah Uji Instalasi');
    assert.equal(await page.locator('[name=letterhead_school_name]').inputValue(), 'Sekolah Uji Instalasi');
    const preview = page.waitForEvent('popup');
    const pdf = page.waitForResponse(r => r.url().endsWith('/admin/settings/preview'));
    await page.locator('#previewLetterhead').click();
    const popup = await preview;
    const response = await pdf;
    assert.equal(response.status(), 200);
    assert.match(response.headers()['content-type'], /application\/pdf/);
    await popup.close();
    await page.locator('.sidebar').getByRole('link', {name:'Backup & Pemulihan'}).click();
    await page.getByRole('button', {name:'Hubungkan Google Drive'}).waitFor();
    await page.getByText('Formulir pemulihan', {exact:true}).click();
    await page.locator('#restore-file-button').waitFor();
    assert.equal(await page.locator('#restorePassword').isVisible(), false);
    assert.deepEqual(errors, []);
    console.log('PASS bundled runtime login, HD shortcut icon, PDF preview, and admin backup and restore screen.');
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
