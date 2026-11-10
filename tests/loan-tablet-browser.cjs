const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const {chromium} = require('./browser-runtime.cjs');
const fixture = JSON.parse(fs.readFileSync(process.argv[2], 'utf8'));
(async () => {
  const browser = await chromium.launch({headless: true, executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe'});
  try {
    const page = await browser.newPage({viewport: {width: 800, height: 1280}, isMobile: true, hasTouch: true});
    page.setDefaultTimeout(30000);
    await page.route('**/*', route => new URL(route.request().url()).origin === fixture.baseURL ? route.continue() : route.abort());
    await page.goto(fixture.baseURL + '/login');
    await page.locator('[name=username]').fill('acc_petugas');
    await page.locator('[name=password]').fill('Accessory-fixture-password');
    await Promise.all([page.waitForURL(/petugas/), page.locator('button[type=submit]').click()]);
    await page.goto(fixture.baseURL + '/petugas/loans/create/1');
    await page.locator('.borrower-option[data-type=teacher]').click();
    await page.locator('.person-row').first().click();
    await page.locator('[name=purpose]').fill('Uji tampilan pengembalian tablet');
    await page.locator('[name=usage_location]').fill('Ruang kelas');
    await page.locator('[name=due_date]').fill(new Date(Date.now() + 86400000).toISOString().slice(0, 10));
    await page.getByRole('button', {name: 'Lanjut ke kelengkapan'}).click();
    await page.locator('[name=accessory_confirmed]').check();
    await Promise.all([page.waitForURL(/\/petugas\/loans\/\d+\/success/), page.getByRole('button', {name: 'Simpan peminjaman', exact: true}).click()]);
    const loanId = new URL(page.url()).pathname.split('/')[3];
    async function captureScreens(name, selector) {
      for (const [width, height] of [[800,1280], [768,1024], [600,960], [1280,800], [390,844], [1920,1080]]) {
        await page.setViewportSize({width,height});
        await page.evaluate(() => document.fonts.ready);
        const metrics = await page.locator(selector).evaluate(heading => {
          heading.textContent = 'KIPAS ANGIN MIYAKO RUANG KELAS VIII.5';
          const card = heading.closest('.card');
          const range = document.createRange();
          range.selectNodeContents(heading);
          return {width: heading.clientWidth, cardWidth: card.clientWidth, lines: range.getClientRects().length,
            overflow: heading.scrollWidth > heading.clientWidth || document.documentElement.scrollWidth > innerWidth};
        });
        assert(!metrics.overflow, name + ' overflows at ' + width);
        if (width >= 600 && width <= 1023 && height > width) {
          assert(metrics.width >= metrics.cardWidth - 40, name + ' should give the name a full row on portrait tablets.');
          assert(metrics.lines <= 2, name + ' should wrap long names at readable word boundaries.');
        }
        await page.evaluate(() => { document.querySelector('.desktop-shell').scrollTop = 0; });
        await page.screenshot({path: path.join(fixture.run, `${name}-${width}.png`)});
        console.log(name, width, JSON.stringify(metrics));
      }
      await page.setViewportSize({width:800,height:1280});
    }
    await captureScreens('borrow-success', '#loanSuccess .transaction-item-name');
    await page.goto(fixture.baseURL + '/petugas/loans/' + loanId);
    await captureScreens('return-detail', '#loanDetail>.card>.kv:first-child .link');
    await page.getByRole('link', {name: 'Proses Pengembalian', exact: true}).click();
    await captureScreens('return-form', '.page:has(>#loanReturnForm)>article.card>h3');
    await page.locator('[name=accessory_return_confirmed]').check();
    await Promise.all([page.waitForURL(/\/success/), page.getByRole('button', {name: 'Konfirmasi Pengembalian', exact: true}).click()]);
    assert.equal(await page.locator('#loanSuccess h1').innerText(), 'Pengembalian berhasil');
    await captureScreens('return-success', '#loanSuccess .transaction-item-name');
    console.log('PASS portrait tablet flow: saved loan, detail, return form, saved return, with full-width item names.');
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
