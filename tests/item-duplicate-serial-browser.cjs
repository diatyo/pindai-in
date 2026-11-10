const fs = require('node:fs');
const assert = require('node:assert/strict');
const {chromium} = require('./browser-runtime.cjs');
const fixture = JSON.parse(fs.readFileSync(process.argv[2], 'utf8'));
(async () => {
  const browser = await chromium.launch({headless: true, executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe'});
  let page;
  try {
    page = await browser.newPage();
    page.on('pageerror', error => console.error('JavaScript:', error.message));
    page.setDefaultTimeout(30000);
    await page.route('**/*', route => {
      const url = new URL(route.request().url());
      return url.origin !== fixture.baseURL || /\.(?:ttf|woff2?)$/.test(url.pathname) ? route.abort() : route.continue();
    });
    const go = path => page.goto(fixture.baseURL + path, {waitUntil: 'domcontentloaded'});
    await go('/login');
    await page.locator('[name=username]').fill('acc_petugas');
    await page.locator('[name=password]').fill('Accessory-fixture-password');
    await Promise.all([page.waitForURL(/petugas/), page.locator('button[type=submit]').click()]);
    await go('/petugas/items/2/edit');
    await page.locator('[name=serial_number]').fill('SERIAL-1');
    await page.locator('[name=serial_photo]').setInputFiles(fixture.image);
    await page.waitForFunction(() => !document.querySelector('[data-processing="true"]'));
    await page.evaluate(() => document.getElementById('itemForm').submit());
    await page.getByText('Nomor seri sudah digunakan. Konfirmasikan penggunaan nomor seri yang sama sebelum menyimpan.', {exact: true}).waitFor();
    await page.waitForLoadState('load');
    await page.locator('[name=serial_photo]').setInputFiles(fixture.image);
    await page.waitForFunction(() => !document.querySelector('[data-processing="true"]'));
    const dialogPromise = page.waitForEvent('dialog');
    const click = page.getByRole('button', {name: 'Simpan Perubahan', exact: true}).click();
    const dialog = await dialogPromise;
    assert(dialog.message().includes('Laptop Dapodik') && dialog.message().includes('SERIAL-1'));
    await dialog.dismiss();
    await click;
    assert.equal(await page.locator('[name=serial_photo]').evaluate(input => input.files.length), 1);
    assert.equal(await page.locator('[name=serial_number]').inputValue(), 'SERIAL-1');
    page.once('dialog', warning => warning.accept());
    await Promise.all([page.waitForURL(url => url.pathname === '/petugas/items/2'), page.getByRole('button', {name: 'Simpan Perubahan', exact: true}).click()]);
    await go('/petugas/items/create');
    await page.locator('[name=category]').selectOption('Elektronik');
    for (const [name, value] of Object.entries({item_name: 'Unit nomor seri sama', brand: 'Uji', model: 'Uji', serial_number: 'SERIAL-1', acquisition_date: '2026-01-01', specifications: 'Uji konfirmasi duplikat'})) {
      await page.locator('[name=' + name + ']').fill(value);
    }
    await page.locator('[name=location]').selectOption('Ruang Sarana');
    await page.locator('[name=condition]').selectOption('good');
    await page.locator('[name=physical_photo]').setInputFiles(fixture.image);
    await page.locator('[name=serial_photo]').setInputFiles(fixture.image);
    await page.waitForFunction(() => !document.querySelector('[data-processing="true"]'));
    page.once('dialog', warning => warning.accept());
    await Promise.all([page.waitForURL(/\/petugas\/items\/\d+$/), page.getByRole('button', {name: 'Simpan Unit & Buat QR', exact: true}).click()]);
    assert((await page.locator('#detail').innerText()).includes('SERIAL-1'));
    console.log('PASS duplicate serial: server rejection without confirmation, cancel preserves photo, confirmed edit and create.');
  } catch (error) {
    console.error('Page:', page.url());
    console.error(await page.locator('#itemForm').evaluate(form => ({valid: form.checkValidity(), invalid: Array.from(form.elements).filter(input => input.validity && !input.validity.valid).map(input => [input.name, input.validationMessage])})));
    throw error;
  } finally {
    await browser.close();
  }
})().catch(error => { console.error(error); process.exitCode = 1; });
