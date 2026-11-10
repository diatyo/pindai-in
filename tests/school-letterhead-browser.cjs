const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const {chromium} = require('./browser-runtime.cjs');
const fixture = JSON.parse(fs.readFileSync(process.argv[2], 'utf8'));
(async () => {
  const browser = await chromium.launch({headless: true, executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe'});
  try {
    const context = await browser.newContext({viewport: {width:1366,height:900}});
    const page = await context.newPage();
    page.setDefaultTimeout(30000);
    await page.route('**/*', route => new URL(route.request().url()).origin === fixture.baseURL ? route.continue() : route.abort());
    await page.goto(fixture.baseURL + '/login');
    await page.locator('[name=username]').fill('acc_admin');
    await page.locator('[name=password]').fill('Accessory-fixture-password');
    await Promise.all([page.waitForURL(/admin/), page.locator('button[type=submit]').click()]);
    await page.goto(fixture.baseURL + '/admin/settings');
    const form = page.locator('#settings form');
    const phone = form.locator('[name=school_phone]');
    for (const value of ['', '(0771) 21720', '0771-21720', '+62 771 21720', '077121720']) {
      await phone.fill(value);
      assert.equal(await phone.inputValue(), value, 'Telephone formatting must not be stripped.');
      assert(await phone.evaluate(input => input.checkValidity()), 'Telephone format should be accepted: ' + value);
    }
    await phone.fill('nomor tidak valid');
    assert.match(await phone.evaluate(input => input.validationMessage), /^Gunakan angka/);
    const phoneCsrf = await page.evaluate(() => ({name:document.querySelector('meta[name=csrf-name]').content, value:document.querySelector('meta[name=csrf-token]').content}));
    const invalidPhone = await context.request.post(fixture.baseURL + '/admin/settings/preview', {
      form:{[phoneCsrf.name]:phoneCsrf.value,school_phone:'nomor tidak valid'},headers:{'X-Requested-With':'XMLHttpRequest'}
    });
    assert.equal(invalidPhone.status(),422);
    assert.match((await invalidPhone.json()).message,/Format telepon sekolah tidak valid/);
    await page.evaluate(({name,token}) => {
      document.getElementsByName(name).forEach(input => {input.value=token;});
      document.querySelector('meta[name=csrf-token]').content=token;
    },{name:phoneCsrf.name,token:invalidPhone.headers()['x-csrf-token']});
    await phone.fill('(0771) 21720');
    assert.equal(await form.locator('[data-letterhead-editor-row]').count(),6);
    await form.locator('[name=letterhead_government_bold][type=checkbox]').uncheck();
    await form.locator('[name=letterhead_contact_bold][type=checkbox]').check();
    await form.locator('[name=letterhead_government_size]').fill('12.5');
    await form.locator('[name=letterhead_department_size]').fill('15');
    await form.locator('[name=letterhead_contact_size]').fill('10');
    const originalGovernment = await form.locator('[name=letterhead_government]').inputValue();
    for (const [key, value] of Object.entries({letterhead_government:'PEMERINTAH KOTA UJI & BUDAYA', letterhead_department:'DINAS PENDIDIKAN DAN KEBUDAYAAN', letterhead_school_name:'SMP NEGERI UJI KEPULAUAN RIAU', kepala_sekolah_name:'Kepala Sekolah Uji', kepala_sekolah_nip:'123456789012345678', letterhead_contact:'Jalan Pendidikan Nomor 123, Tanjungpinang\nTelepon (0771) 12345 · Email sekolah@example.test'})) {
      await form.locator('[name=' + key + ']').fill(value);
    }
    await form.locator('[name=letterhead_city_logo]').setInputFiles(fixture.image);
    await form.locator('[name=letterhead_school_logo]').setInputFiles(fixture.image);
    const previewResponse = page.waitForResponse(response => response.url().endsWith('/admin/settings/preview') && response.request().method() === 'POST');
    await form.getByRole('button',{name:'Pratinjau PDF',exact:true}).click();
    const preview = await previewResponse;
    assert(preview.ok() && preview.headers()['content-type'].includes('application/pdf'));
    await page.waitForFunction(() => !document.getElementById('previewLetterhead').disabled);
    const blobData = await page.locator('#letterheadPreviewLink').evaluate(async link => {
      const bytes = new Uint8Array(await (await fetch(link.href)).arrayBuffer());
      return btoa(Array.from(bytes, byte => String.fromCharCode(byte)).join(''));
    });
    const previewBytes = Buffer.from(blobData,'base64');
    console.log('PDF preview bytes',previewBytes.length);
    assert(previewBytes.subarray(0,5).equals(Buffer.from('%PDF-')) && previewBytes.length > 1000, 'Preview must contain a readable PDF document.');
    fs.writeFileSync(path.join(fixture.run,'kop-pratinjau.pdf'),previewBytes);
    const unchanged = await context.request.get(fixture.baseURL + '/admin/settings');
    assert((await unchanged.text()).includes(originalGovernment));
    assert(!(await unchanged.text()).includes('PEMERINTAH KOTA UJI &amp; BUDAYA'));
    await form.getByRole('button',{name:'Simpan Konfigurasi',exact:true}).click();
    await page.getByText('Konfigurasi sistem berhasil disimpan.',{exact:true}).waitFor();
    assert.equal(await form.locator('[name=letterhead_government]').inputValue(),'PEMERINTAH KOTA UJI & BUDAYA');
    assert.equal(await phone.inputValue(),'(0771) 21720', 'Formatted phone must survive saving and reloading.');
    assert(!(await form.locator('[name=letterhead_government_bold][type=checkbox]').isChecked()));
    assert(await form.locator('[name=letterhead_contact_bold][type=checkbox]').isChecked());
    assert.equal(await form.locator('[name=letterhead_department_size]').inputValue(),'15');
    assert.equal(await form.locator('[name=letterhead_government_size]').inputValue(),'12.5');
    assert.equal(await form.locator('[name=letterhead_contact_size]').inputValue(),'10');
    assert.equal(await form.locator('[name=letterhead_government]').evaluate(input => getComputedStyle(input).fontWeight),'400');
    assert.equal(await form.locator('[name=letterhead_contact]').evaluate(input => getComputedStyle(input).fontWeight),'700');
    for (const width of [1366,800,390]) {
      await page.setViewportSize({width,height:1000});
      assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth));
      await page.screenshot({path:path.join(fixture.run,`settings-letterhead-${width}.png`),fullPage:true});
    }
    const report = await context.request.get(fixture.baseURL + '/admin/activity/pdf');
    assert(report.ok() && report.headers()['content-type'].includes('application/pdf'));
    fs.writeFileSync(path.join(fixture.run,'kop-laporan-audit.pdf'),await report.body());
    const savedSchoolLogo = await form.locator('[data-school-logo]').nth(1).locator('img').getAttribute('src');
    await form.locator('[name=letterhead_school_logo]').setInputFiles({name:'logo.svg',mimeType:'image/svg+xml',buffer:Buffer.from('<svg xmlns="http://www.w3.org/2000/svg"></svg>')});
    await form.getByRole('button',{name:'Simpan Konfigurasi',exact:true}).click();
    await page.getByText(/Logo harus berupa PNG, JPG, atau WebP/).waitFor();
    assert.equal(await form.locator('[data-school-logo]').nth(1).locator('img').getAttribute('src'),savedSchoolLogo);
    await form.locator('[name=reset_letterhead_city_logo]').check();
    await form.getByRole('button',{name:'Simpan Konfigurasi',exact:true}).click();
    await page.waitForLoadState('load');
    await page.getByText('Konfigurasi sistem berhasil disimpan.',{exact:true}).waitFor();
    const currentImage = await form.locator('[data-school-logo]').first().locator('img').getAttribute('src');
    const defaultImage = await form.locator('[data-school-logo]').first().locator('img').getAttribute('data-default-logo');
    assert.equal(currentImage,defaultImage);
    const staffContext = await browser.newContext();
    const staff = await staffContext.newPage();
    await staff.goto(fixture.baseURL + '/login');
    await staff.locator('[name=username]').fill('acc_petugas');
    await staff.locator('[name=password]').fill('Accessory-fixture-password');
    await Promise.all([staff.waitForURL(/petugas/),staff.locator('button[type=submit]').click()]);
    const csrf = await staff.evaluate(() => ({name:document.querySelector('meta[name=csrf-name]').content,value:document.querySelector('meta[name=csrf-token]').content}));
    const denied = await staffContext.request.post(fixture.baseURL + '/admin/settings/preview',{form:{[csrf.name]:csrf.value}});
    assert(!denied.headers()['content-type']?.includes('application/pdf'));
    await staffContext.close();
    console.log('PASS six editable letterhead rows, individual font sizes/bold saved and applied in reports, formatted telephone input/save, Indonesian client/server validation, PDF preview, uploaded logos, reset and admin-only access.');
  } finally { await browser.close(); }
})().catch(error => {console.error(error);process.exitCode=1;});
