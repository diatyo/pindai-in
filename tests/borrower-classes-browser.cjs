const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const {chromium} = require('./browser-runtime.cjs');
const fixture = JSON.parse(fs.readFileSync(process.argv[2], 'utf8'));
(async () => {
  const browser = await chromium.launch({headless: true, executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe'});
  try {
    const page = await browser.newPage({viewport: {width: 1366, height: 680}});
    page.setDefaultTimeout(30000);
    await page.route('**/*', route => new URL(route.request().url()).origin === fixture.baseURL ? route.continue() : route.abort());
    await page.goto(fixture.baseURL + '/login');
    await page.locator('[name=username]').fill('acc_petugas');
    await page.locator('[name=password]').fill('Accessory-fixture-password');
    await Promise.all([page.waitForURL(/petugas/), page.locator('button[type=submit]').click()]);
    await page.goto(fixture.baseURL + '/petugas/loans/create/1');
    await page.locator('.borrower-option[data-type=student]').click();
    const filter = page.locator('#classFilter');
    assert.deepEqual(await filter.locator('option').allTextContents(), ['Semua kelas', 'VII.1', 'VII.5', 'VIII.1', 'IX.9']);
    for (const [width, height] of [[1366, 680], [390, 844]]) {
      await page.setViewportSize({width, height});
      const result = page.waitForResponse(response => response.url().includes('/petugas/borrowers/search?') && response.url().includes('class=VII.5'));
      await filter.selectOption('VII.5');
      await result;
      await page.locator('.person-row').filter({hasText: 'Siswa kelas VII.5'}).waitFor();
      assert.equal(await page.locator('#borrowerList .person-row').count(), 1);
      assert((await page.locator('#borrowerList').innerText()).includes('VII.5'));
      await page.screenshot({path: path.join(fixture.run, `borrower-class-${width}.png`)});
      await filter.selectOption('');
    }
    await filter.selectOption('VII.5');
    await page.locator('.person-row').filter({hasText: 'Siswa kelas VII.5'}).click();
    assert((await page.locator('#selectedMeta').innerText()).includes('VII.5'));
    await page.locator('#changeLoanBorrower').click();
    await page.locator('.borrower-option[data-type=teacher]').click();
    assert(await filter.isHidden());
    await page.locator('.person-row').filter({hasText: 'Guru Uji'}).waitFor();
    console.log('PASS actual class options, exact student filtering, borrower selection, teacher switch, desktop and mobile.');
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
