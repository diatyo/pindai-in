const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const {chromium} = require('./browser-runtime.cjs');
const fixture = JSON.parse(fs.readFileSync(process.argv[2], 'utf8'));
(async () => {
  const browser = await chromium.launch({headless:true, executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe'});
  try {
    const page = await browser.newPage({viewport:{width:1366,height:768}});
    page.setDefaultTimeout(60000);
    await page.route('**/*', route => new URL(route.request().url()).origin === fixture.baseURL ? route.continue() : route.abort());
    await page.goto(fixture.baseURL + '/login');
    await page.locator('[name=username]').fill('acc_admin');
    await page.locator('[name=password]').fill('Accessory-fixture-password');
    await Promise.all([page.waitForURL(/admin/), page.locator('button[type=submit]').click()]);
    await page.goto(fixture.baseURL + '/admin/users?tab=peminjam');
    await page.getByRole('button', {name:'Generate Akun Peminjam',exact:true}).click();
    const dialog = page.locator('#borrowerGeneratorModal');
    await dialog.locator('[data-type-filter=student]').click();
    assert.match(await dialog.locator('#generateAllBorrowers').innerText(), /1\.005/);
    await dialog.locator('[data-type-filter=teacher]').click();
    assert.match(await dialog.locator('#generateAllBorrowers').innerText(), /41/);
    const requests = [];
    page.on('request', request => { if (request.url().endsWith('/generate-borrowers-batch')) requests.push(new URLSearchParams(request.postData()).getAll('borrower_ids[]')); });
    await dialog.locator('#generateAllBorrowers').click();
    await dialog.locator('#pauseBorrowerGenerator').click();
    await page.waitForFunction(() => document.getElementById('borrowerGeneratorStatus').textContent.includes('dijeda'));
    assert.equal(requests.length, 1);
    assert.equal(requests[0].length, 20);
    await dialog.locator('#resumeBorrowerGenerator').click();
    await page.waitForFunction(() => document.getElementById('borrowerGeneratorStatus').textContent.startsWith('Selesai.'));
    assert.deepEqual(requests.map(batch => batch.length), [20,20,1]);
    assert.match(await dialog.locator('#borrowerGeneratorCounts').innerText(), /41 \/ 41 diproses · 41 dibuat · 0 dilewati/);
    const csvPromise = page.waitForEvent('download');
    await dialog.locator('#downloadBorrowerAccounts').click();
    const csv = await csvPromise;
    const csvFile = path.join(fixture.run, 'accounts-test.csv');
    await csv.saveAs(csvFile);
    assert.equal(fs.readFileSync(csvFile,'utf8').trim().split('\r\n').length, 42);
    for (const width of [390,800,1366]) {
      await page.setViewportSize({width,height:900});
      assert(await dialog.evaluate(el => el.scrollWidth <= el.clientWidth), 'Generator overflows at ' + width);
      await page.screenshot({path:path.join(fixture.run, 'accounts-' + width + '.png')});
    }
    await dialog.locator('#finishBorrowerGenerator').click();
    await page.getByRole('button', {name:'Generate Akun Peminjam',exact:true}).click();
    await dialog.locator('[data-type-filter=teacher]').click();
    assert(await dialog.locator('#generateAllBorrowers').isDisabled(), 'Existing accounts must be skipped.');
    const csrf = await page.locator('meta[name=csrf-token]').getAttribute('content');
    const body = new URLSearchParams({csrf_test_name:csrf});
    for (let i=0;i<21;i++) body.append('borrower_ids[]','1');
    const response = await page.request.post(fixture.baseURL+'/admin/users/generate-borrowers-batch', {data:body.toString(),headers:{'X-Requested-With':'XMLHttpRequest','Content-Type':'application/x-www-form-urlencoded'}});
    assert.equal(response.status(), 422);
    console.log('PASS bulk generator: 1005 visible students, filtered teacher generation in 20/20/1, pause/resume, CSRF rotation, CSV export, responsive dialog, existing-account skip and request cap.');
  } finally { await browser.close(); }
})().catch(error => {console.error(error);process.exitCode=1;});
