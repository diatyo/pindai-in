const fs = require('fs');
const path = require('path');
const assert = require('assert/strict');
const {chromium} = require('./browser-runtime.cjs');
const fixture = JSON.parse(fs.readFileSync(process.argv[2], 'utf8'));
let checks = 0;
const ok = (value, message) => { assert.ok(value, message); checks++; };
(async () => {
  const browser = await chromium.launch({headless: true, executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe', args: ['--disable-gpu']});
  const errors = [];
  const context = await browser.newContext({viewport: {width: 390, height: 844}, isMobile: true, hasTouch: true});
  const guard = async (context, origin = fixture.baseURL) => context.route('**/*', route => {
    const url = new URL(route.request().url());
    return url.origin === origin ? route.continue() : route.abort();
  });
  await guard(context);
  const page = await context.newPage();
  page.on('pageerror', error => errors.push(error.message));
  page.setDefaultTimeout(12000);
  page.setDefaultNavigationTimeout(60000);
  const goto = async route => {
    const response = await page.goto(fixture.baseURL + route);
    ok(response.status() === 200, `${route}: HTTP ${response.status()}`);
    ok(new URL(page.url()).origin === fixture.baseURL, 'Requests must remain on the isolated server.');
  };
  const screen = async name => {
    for (const width of (/^(item|loan)-list/.test(name) ? [320, 360, 390, 768, 1280] : [320, 390, 768, 1280])) {
      await page.setViewportSize({width, height: 844});
      await page.evaluate(() => {
        window.scrollTo(0, 0);
        document.querySelectorAll('main, .main, .content, .app-main, .desktop-shell').forEach(element => { element.scrollTop = 0; });
      });
      const dimensions = await page.evaluate(() => ({width: innerWidth, scroll: document.documentElement.scrollWidth}));
      ok(dimensions.scroll <= dimensions.width + 1, `${name} overflows at ${width}: ${dimensions.scroll}`);
      if (name === 'item-list' && width < 1024) {
        for (const selector of ['#openInventoryFilters', '.inventory-add', '.inventory-search button', '.inventory-scope', '.inventory-item-link']) {
          const target = await page.locator(selector).first().boundingBox();
          ok(target && target.width >= 44 && target.height >= 44 && target.x >= 0 && target.x + target.width <= width, `${selector} fits and has a touch target at ${width}.`);
        }
        const scopePositions = await page.locator('.inventory-scope').evaluateAll(links => links.map(link => link.offsetTop));
        ok(new Set(scopePositions).size === 1, `Quick filters fit on a single row at ${width}.`);
        ok(!(await page.locator('.bulk-qr-form').isVisible()), 'Desktop QR form is hidden on mobile.');
      }
      if (name === 'loan-list' && width < 1024) {
        const scopes = await page.locator('#loans .inventory-scope').evaluateAll(links => links.map(link => {
          const {x, y, width, height} = link.getBoundingClientRect();
          const range = document.createRange();
          range.selectNodeContents(link.firstElementChild);
          return {x, y, width, height, lines: range.getClientRects().length};
        }));
        ok(scopes.length === 4 && scopes.every(scope => scope.y === scopes[0].y && scope.width >= 44 && scope.height >= 44 && scope.x + scope.width <= width && scope.lines === 1), `All four loan statuses fit at ${width}.`);
      }
      if (name.startsWith('dashboard')) {
        const aligned = await page.evaluate(() => [...document.querySelectorAll('#dashboard .status-legend>span')].every(row => {
          const text = [...row.childNodes].find(node => node.nodeType === Node.TEXT_NODE && node.textContent.trim());
          const value = row.querySelector('b').getBoundingClientRect();
          if (!text || !row.querySelector('i')) return false;
          const range = document.createRange(); range.selectNodeContents(text);
          return range.getBoundingClientRect().right <= value.left - 3 && value.right <= row.getBoundingClientRect().right + 1;
        }));
        ok(aligned, `Status labels and counts must not overlap at ${width}.`);
      }
      await page.screenshot({path: path.join(fixture.run, `${name}-${width}.png`), fullPage: true});
    }
    await page.setViewportSize({width: 390, height: 844});
  };
  const login = async (targetPage, role, origin = fixture.baseURL) => {
    await targetPage.goto(origin + '/login');
    ok(new URL(targetPage.url()).origin === origin, 'Login must remain on the isolated server.');
    ok((await targetPage.locator('form[action*="login"]').getAttribute('action')).startsWith(origin), 'Login form must target the isolated server.');
    await targetPage.locator('[name=username]').fill('acc_' + role);
    await targetPage.locator('[name=password]').fill('Accessory-fixture-password');
    await Promise.all([targetPage.waitForURL(url => url.pathname !== '/login'), targetPage.locator('button[type=submit]').click()]);
  };
  try {
    await login(page, 'petugas');
    await goto('/petugas');
    ok(await page.locator('.mobile-status-item').count() === 4, 'Accessory follow-up is integrated into the four dashboard cards.');
    await screen('dashboard-empty');
    await goto('/petugas/loans');
    const loanRows = page.locator('#loans .inventory-item-link');
    const loanSearch = page.locator('#loans .inventory-search input[type=search]');
    const loanScopes = page.locator('#loans .inventory-scopes a');
    ok(await loanRows.first().innerText().then(text => ['Chromebook 03', 'Guru Uji', 'Terlambat', 'Tenggat'].every(part => text.includes(part))), 'A mobile loan row exposes its item, borrower, and deadline.');
    await loanRows.nth(1).locator('img').evaluate(image => image.decode());
    ok((await loanRows.nth(1).innerText()).includes('Kembali'), 'Completed loans show the actual return time.');
    await screen('loan-list');
    await loanSearch.fill('Chromebook 03');
    await loanSearch.press('Enter');
    assert.deepEqual(await loanScopes.locator('.inventory-count').allTextContents(), ['1', '1', '1', '0']);
    await loanScopes.filter({hasText: 'Terlambat'}).click();
    await loanRows.first().click();
    await page.getByRole('link', {name: 'Kembali ke Peminjaman'}).click();
    const loanBack = new URL(page.url());
    ok(loanBack.searchParams.get('q') === 'Chromebook 03' && loanBack.searchParams.get('status') === 'late' && loanBack.hash === '#inventory-loan-1', 'Back restores the loan list query, status, and record anchor.');
    await loanScopes.filter({hasText: 'Selesai'}).click();
    ok(await page.getByRole('heading', {name: 'Tidak ada transaksi yang cocok'}).isVisible(), 'Loan search shows a recovery action for no matches.');
    await page.getByRole('link', {name: 'Tampilkan semua transaksi'}).click();
    await page.getByRole('link', {name: 'Halaman berikutnya'}).click();
    ok(await loanRows.count() === 2 && new URL(page.url()).searchParams.get('page') === '2', 'Loan pagination is reachable on mobile.');
    await page.setViewportSize({width: 1280, height: 844});
    ok(await page.locator('#loans .table').isVisible() && !(await loanSearch.isVisible()), 'Desktop loans still use the table and full search form.');
    await page.setViewportSize({width: 390, height: 844});
    await goto('/petugas/items');
    ok(await page.locator('.item-name-link').first().innerText() === 'Laptop Dapodik LOQ-15IAX9E', 'List displays stored item name without appending model.');
    ok(await page.locator('.item-name-primary').first().innerText() === 'Laptop Dapodik LOQ-15IAX9E', 'Mobile list has the same unambiguous name.');
    const thumb = page.locator('#inventory-unit-1 .inventory-thumb img');
    await thumb.evaluate(image => image.decode());
    ok(await thumb.getAttribute('loading') === 'lazy' && await thumb.isVisible(), 'The mobile thumbnail loads the inventory photo lazily.');
    const brokenThumb = page.locator('.inventory-item-link').filter({hasText: 'ZZM-001'}).locator('.inventory-thumb');
    await brokenThumb.locator('img').waitFor({state: 'detached'});
    ok(await brokenThumb.locator('svg').isVisible(), 'A missing photo file falls back to a category icon.');
    const mobileRow = page.locator('#inventory-unit-1 .inventory-item-link');
    const beforePress = await mobileRow.evaluate(row => getComputedStyle(row).backgroundColor);
    await mobileRow.hover();
    await page.mouse.down();
    ok(await mobileRow.evaluate(row => getComputedStyle(row).backgroundColor) !== beforePress, 'Pressing an inventory row shows visual feedback.');
    await page.mouse.move(5, 70);
    await page.mouse.up();
    await screen('item-list');
    const mobileSearch = page.locator('#inventoryKeyword');
    const filterButton = page.locator('#openInventoryFilters');
    const filterSheet = page.getByRole('dialog', {name: 'Filter barang'});
    await mobileSearch.fill('ACC-001');
    await filterButton.click();
    await filterSheet.getByLabel('Kategori').selectOption('Perangkat Komputer');
    await filterSheet.getByLabel('Status').selectOption('available');
    await page.screenshot({path: path.join(fixture.run, 'item-list-filter-390.png')});
    await filterSheet.getByRole('button', {name: 'Terapkan'}).click();
    ok(await mobileSearch.inputValue() === 'ACC-001' && await page.locator('.inventory-item-link').count() === 1, 'The sheet applies status and category without losing the entered unit code.');
    const filteredUrl = new URL(page.url());
    await page.locator('.inventory-item-link').first().click();
    await page.getByRole('link', {name: 'Kembali ke Data Barang'}).click();
    const backUrl = new URL(page.url());
    ok([...filteredUrl.searchParams].every(([key, value]) => backUrl.searchParams.get(key) === value) && backUrl.hash === '#inventory-unit-1', 'Back restores filters and anchors to the same unit.');
    await filterButton.click();
    await filterSheet.getByLabel('Status').selectOption('borrowed');
    await page.keyboard.press('Escape');
    ok(!(await filterSheet.isVisible()) && await filterButton.evaluate(button => button === document.activeElement), 'Dismissing the sheet returns focus to Filter.');
    await filterButton.click();
    ok(await filterSheet.getByLabel('Status').inputValue() === 'available', 'Reopening discards an unapplied status change.');
    await filterSheet.getByRole('button', {name: 'Tutup filter'}).click();
    await page.getByRole('link', {name: 'Hapus filter kategori Perangkat Komputer'}).click();
    ok(new URL(page.url()).searchParams.get('status') === 'available' && await mobileSearch.inputValue() === 'ACC-001', 'Removing one filter preserves the rest.');
    await mobileSearch.fill('barang-yang-tidak-ada');
    await mobileSearch.press('Enter');
    ok(await page.getByRole('heading', {name: 'Tidak ada barang yang cocok'}).isVisible(), 'An empty search shows recovery instructions.');
    await page.getByRole('link', {name: 'Tampilkan semua barang'}).click();
    const lastVisibleUnit = page.locator('.inventory-item-link').last();
    const unitAnchor = await lastVisibleUnit.locator('..').getAttribute('id');
    await lastVisibleUnit.click();
    await page.getByRole('link', {name: 'Kembali ke Data Barang'}).click();
    await page.waitForFunction(id => {
      const unit = document.getElementById(id);
      if (!unit) return false;
      const {top, bottom} = unit.getBoundingClientRect();
      return top >= 0 && bottom < 778;
    }, unitAnchor);
    ok(new URL(page.url()).hash === '#' + unitAnchor, 'Back from a lower result returns the selected unit into view.');
    await page.getByRole('link', {name: 'Halaman berikutnya'}).click();
    ok(new URL(page.url()).searchParams.get('page') === '2' && await page.locator('.inventory-item-link').count() === 1, 'Mobile can reach the next inventory page.');
    await screen('item-list-page2');
    await page.locator('.inventory-item-link').click();
    await page.goBack();
    ok(new URL(page.url()).searchParams.get('page') === '2', 'Browser Back returns to the same page of inventory.');
    await goto('/petugas/items');
    await filterButton.click();
    await filterSheet.getByLabel('Status').selectOption('archived');
    await filterSheet.getByRole('button', {name: 'Terapkan'}).click();
    await page.locator('.inventory-item-link').filter({hasText: 'Unit Arsip Uji'}).click();
    await page.waitForLoadState('load');
    await page.getByRole('button', {name: 'Aktifkan kembali', exact: true}).click();
    ok(await page.locator('#confirmModal').isVisible() && (await page.locator('#confirmModal form').getAttribute('action')).endsWith('/restore'), 'Archived unit can be restored from its mobile detail.');
    await page.locator('#confirmModal [data-close]').first().click();
    await page.setViewportSize({width: 1280, height: 844});
    await goto('/petugas/items');
    ok(await page.locator('.bulk-qr-form .table').isVisible() && !(await filterButton.isVisible()) && !(await page.locator('.inventory-add').isVisible()), 'Desktop still shows its table and toolbar without mobile controls.');
    await page.setViewportSize({width: 390, height: 844});
    await goto('/petugas/items/1');
    const inventoryPhotos = await page.locator('#detail .detail-photos img').evaluateAll(images => images.map(img => img.src));
    const anonymous = await browser.newContext();
    const denied = await anonymous.request.get(inventoryPhotos[1], {maxRedirects: 0});
    ok(denied.status() === 302 && denied.headers().location.endsWith('/login'), 'A direct legacy photo URL redirects an anonymous user to login.');
    await anonymous.close();
    const staffPhoto = await page.request.get(inventoryPhotos[1]);
    ok(staffPhoto.ok() && staffPhoto.headers()['content-type'].startsWith('image/') && staffPhoto.headers()['cache-control'].includes('no-store'), 'Staff can view the legacy photo privately.');
    ok(await page.locator('#detail h1').innerText() === 'Laptop Dapodik LOQ-15IAX9E', 'Detail heading is the item name.');
    const hierarchy = await page.evaluate(() => ({
      name: parseFloat(getComputedStyle(document.querySelector('#detail h1')).fontSize),
      code: parseFloat(getComputedStyle(document.querySelector('#detail .item-unit-code')).fontSize)
    }));
    ok(hierarchy.name > hierarchy.code, 'Name is more prominent than unit code.');
    await page.setViewportSize({width: 320, height: 844});
    const detailActions = await page.locator('#detail .detail-header-actions').evaluate(actions =>
      Array.from(actions.querySelectorAll(':scope > .btn, :scope > details > summary')).filter(button => button.getBoundingClientRect().width > 0).map(button => {
        const {top, bottom, right} = button.getBoundingClientRect();
        return {top, bottom, right};
      })
    );
    ok(detailActions.length === 3 && detailActions.every(button => Math.abs(button.top - detailActions[0].top) <= 1 && button.right <= 320), 'All three actions fit on one row at 320 pixels.');
    ok(detailActions[2].bottom <= 844 && await page.getByRole('link', {name: 'Mulai Peminjaman', exact: true}).count() === 1, 'The single start-loan action is visible without scrolling.');
    ok((await page.locator('#detail .detail-identity-meta').innerText()).includes('Tersedia'), 'Availability is shown next to the item code.');
    await page.locator('#detail .detail-actions-menu summary').click();
    await page.locator('#detail .detail-actions-menu').getByRole('button', {name: 'Arsipkan', exact: true}).click();
    ok(await page.locator('#confirmModal').isVisible(), 'Archive remains reachable through the compact menu.');
    await page.locator('#confirmModal [data-close]').first().click();
    await page.setViewportSize({width: 390, height: 844});
    ok(await page.locator('#detailArchive').isVisible() && !(await page.locator('#detail .detail-actions-menu').isVisible()), 'Wider phones keep the archive action in the row.');
    const inventory = page.locator('#detail .detail-inventory');
    ok(!(await inventory.locator('.detail-inventory-values').isVisible()), 'Technical inventory details start collapsed.');
    await inventory.locator('summary').click();
    ok((await inventory.innerText()).includes('SERIAL-1'), 'Opening inventory details exposes the serial number.');
    await inventory.locator('summary').click();
    const qrPreview = page.getByRole('button', {name: 'Perbesar QR barang'});
    const qrDialog = page.getByRole('dialog', {name: 'QR Barang'});
    await qrPreview.locator('img').evaluate(img => img.decode());
    ok(await qrPreview.isVisible() && !(await page.locator('#detail .qrbox').isVisible()), 'The compact QR replaces the lower QR card on mobile.');
    await qrPreview.click();
    ok(await qrDialog.isVisible() && await qrDialog.getByRole('link', {name: 'Cetak QR'}).isVisible() && await qrDialog.getByRole('link', {name: 'Unduh PNG'}).isVisible(), 'Opening the QR gives the operator both actions.');
    await qrDialog.getByRole('button', {name: 'Tutup QR'}).click();
    ok(!(await qrDialog.isVisible()), 'The QR dialog closes.');
    await page.setViewportSize({width: 1280, height: 844});
    ok(!(await qrPreview.isVisible()) && await page.locator('#detail .qrbox').isVisible(), 'Desktop keeps the QR card.');
    await screen('item-identity');

    const printPage = await context.newPage();
    await printPage.addInitScript(() => { window.print = () => {}; });
    await printPage.goto(fixture.baseURL + '/petugas/items/qr-labels?item_ids[]=1&item_ids[]=4&item_ids[]=7&item_ids[]=11');
    await printPage.locator('.label img').evaluateAll(images => Promise.all(images.map(image => image.decode())));
    const labels = printPage.locator('.label');
    assert.deepEqual(await labels.locator('.label-name').allTextContents(), [
      'Laptop Dapodik LOQ-15IAX9E', 'Chromebook 03', 'Chromebook 01', 'Perangkat Multimedia dengan Nama Inventaris yang Sangat Panjang',
    ]);
    assert.deepEqual(await labels.locator('.label-code').allTextContents(), ['ACC-001', 'ZZM-002', 'ZZM-005', 'ZZM-009']);
    assert.deepEqual(await labels.locator('.label-brand-model').allTextContents(), ['Uji / LOQ-15IAX9E', 'Acer / C734', 'Acer', 'Epson / EB-X06']);
    assert.deepEqual(await labels.locator('.label-name').evaluateAll(names => names.map(name => name.style.fontSize)), ['22pt', '22pt', '22pt', '16pt']);
    ok(await labels.evaluateAll(cards => cards.every(card => {
      const lines = [...card.children];
      const [qr, name, code, brandModel, credit] = lines;
      return parseFloat(getComputedStyle(name).fontSize) > parseFloat(getComputedStyle(code).fontSize)
        && parseFloat(getComputedStyle(code).fontSize) > parseFloat(getComputedStyle(brandModel).fontSize)
        && qr.tagName === 'IMG' && credit.textContent.trim() === 'Pindai.in by SMP Negeri 1 Tanjungpinang'
        && lines.every((line, index) => index === 0 || lines[index - 1].getBoundingClientRect().bottom <= line.getBoundingClientRect().top);
    })), 'Printed labels show QR, name, code, brand/model, and school credit in order.');
    await printPage.setViewportSize({width: 320, height: 844});
    ok(await printPage.evaluate(() => document.documentElement.scrollWidth <= innerWidth), 'Mobile print preview fits a narrow viewport.');
    await printPage.pdf({path: path.join(fixture.run, 'label-qr-massal.pdf'), preferCSSPageSize: true, printBackground: true});
    await printPage.goto(fixture.baseURL + '/petugas/items/qr-label/ZZM-005');
    ok(await printPage.locator('.label-name').textContent() === 'Chromebook 01' && await printPage.locator('.label-code').textContent() === 'ZZM-005', 'Single browser label also uses the stored name first.');
    await printPage.goto(fixture.baseURL + '/petugas/items/qr-label/ZZM-008');
    ok(await printPage.locator('.label-name').evaluate(name => name.style.fontSize) === '19pt' && await printPage.locator('.label-brand-model').count() === 0, 'A medium-length name scales down and empty metadata is omitted.');
    await printPage.close();
    const labelPdf = await page.request.get(fixture.baseURL + '/petugas/items/11/qr-pdf');
    const pdfBytes = await labelPdf.body();
    ok(labelPdf.ok() && pdfBytes.subarray(0, 5).toString() === '%PDF-', 'Single PDF label route renders a PDF.');
    fs.writeFileSync(path.join(fixture.run, 'label-qr-tunggal.pdf'), pdfBytes);

    await goto('/petugas/loans');
    await page.getByRole('link', {name: 'Pinjam beberapa unit'}).click();
    await page.locator('.borrower-option[data-type=teacher]').click();
    await page.locator('.person-row').filter({hasText: 'Guru Uji'}).click();
    await page.locator('[name=purpose]').fill('Uji peminjaman kelas');
    await page.locator('[name=usage_location]').fill('Ruang kelas');
    const groupTomorrow = new Date(Date.now() + 86400000).toISOString().slice(0, 10);
    await page.locator('[name=due_date]').fill(groupTomorrow);
    await page.locator('[name=due_time]').fill('16:00');
    await page.getByRole('button', {name: 'Lanjut ke daftar unit'}).click();
    const groupCode = page.locator('#manualCode');
    const groupMessage = page.locator('#loanGroupMessage');
    const groupUnits = page.locator('#loanGroupUnits [data-group-unit]');
    const saveGroup = page.locator('#saveLoanGroup');
    async function addGroupCode(code, feedback) {
      if (!await page.locator('#groupScanDialog').evaluate(dialog => dialog.open)) {
        await page.locator('#openGroupManual').click();
      }
      await groupCode.fill(code);
      await page.locator('#manualLookup').click();
      await groupMessage.filter({hasText: feedback}).waitFor();
    }
    await addGroupCode('ZZM-002', /Dipinjam/i);
    ok(await groupMessage.getAttribute('data-tone') === 'red', 'Unavailable unit feedback has a distinct error state.');
    ok(await groupUnits.count() === 0, 'Group lookup rejects a unit with an active loan.');
    await addGroupCode('PINDAI.IN|ZZM-003', /ditambahkan/);
    ok(await saveGroup.isDisabled(), 'Group save requires at least two units.');
    await addGroupCode('ZZM-003', /sudah ada di daftar/);
    ok(await groupMessage.getAttribute('data-tone') === 'amber', 'Duplicate scan feedback uses a warning state.');
    ok(await groupUnits.count() === 1, 'Duplicate scans do not add a second row.');
    await page.evaluate(() => {
      window.BarcodeDetector = class {
        detect() { return Promise.resolve([{rawValue: 'PINDAI.IN|ZZM-004'}]); }
      };
      navigator.mediaDevices.getUserMedia = async () => {
        const canvas = document.createElement('canvas');
        canvas.width = canvas.height = 80;
        const pixels = canvas.getContext('2d');
        const stream = canvas.captureStream(12);
        for (let frame = 0; frame < 4; frame++) {
          setTimeout(() => pixels.fillRect(frame, frame, 4, 4), 100 * frame);
        }
        return stream;
      };
    });
    await page.locator('#finishGroupScan').click();
    await page.getByRole('button', {name: 'Scan unit', exact: true}).click();
    await groupUnits.nth(1).waitFor();
    await page.locator('#scanState.is-green').filter({hasText: 'Total 2 unit dalam kelompok'}).waitFor();
    ok((await page.locator('#scanState').innerText()).includes('Perangkat Multimedia 4 ditambahkan.'), 'Camera feedback confirms the scanned item name and group total beside the preview.');
    ok(await page.locator('#scannerVideo').evaluate(video => video.srcObject?.active), 'The group camera stays active after a successful scan.');
    for (const width of [320, 390, 768, 1280]) {
      await page.setViewportSize({width, height: 844});
      const frame = await page.locator('#scannerVideo').boundingBox();
      const finish = await page.locator('#finishGroupScan').boundingBox();
      ok(frame.height <= 280 && finish.y + finish.height <= 844, 'Camera stays compact and the finish button remains visible at width ' + width);
      await page.screenshot({path: path.join(fixture.run, `group-scanner-${width}.png`)});
    }
    await page.setViewportSize({width: 390, height: 844});
    await page.locator('#finishGroupScan').click();
    await page.waitForFunction(() => document.getElementById('scannerVideo').srcObject === null);
    const groupForm = page.locator('#loanCreateForm');
    const requestKey = await groupForm.locator('[name=request_key]').inputValue();
    await groupForm.locator('[name=accessory_confirmed]').check();
    await groupForm.evaluate(form => form.submit());
    await page.getByText(/Kelengkapan wajib belum diserahkan: Adaptor unit/).waitFor();
    ok(await groupUnits.count() === 2,
      'Server rejects missing required handover and restores the complete group draft.');
    const groupUnit = groupUnits.first();
    await groupUnit.locator('summary').click();
    await groupUnit.locator('input[type=checkbox][required]').check();
    const groupExtras = groupUnit.locator('[data-accessory-editor]');
    await groupExtras.locator('[data-accessory-open]').click();
    await groupExtras.locator('[data-accessory-name]').fill('Kabel kelompok');
    await groupExtras.locator('[data-accessory-add]').click();
    await groupForm.locator('[name=accessory_confirmed]').check();
    await screen('loan-group-create');
    await Promise.all([page.waitForURL(/\/groups\/\d+$/), saveGroup.click()]);
    const groupUrl = new URL(page.url());
    const groupReceipt = page.locator('#loanGroupReceipt');
    ok((await groupReceipt.locator('.eyebrow').textContent()).startsWith('PMG-') && await groupReceipt.locator('[data-group-progress]').innerText() === '0/2 kembali',
      'Group saves once with its own number and two unit records.');
    ok((await groupReceipt.locator('[data-group-loan]').first().innerText()).includes('Kabel kelompok'), 'Receipt lists each unit handover.');
    const number = await groupReceipt.locator('.eyebrow').textContent();
    await goto('/petugas/loans?q=' + encodeURIComponent(number));
    ok(await page.locator('#loans .inventory-item-link').count() === 2, 'The group number finds all unit transactions.');
    await goto(groupUrl.pathname);
    const tokenPage = await page.request.get(fixture.baseURL + '/petugas/loans/groups/create');
    const tokenDoc = await tokenPage.text();
    const tokenName = tokenDoc.match(/<meta name="csrf-name" content="([^"]+)"/)[1];
    const token = tokenDoc.match(/<meta name="csrf-token" content="([^"]+)"/)[1];
    const retry = await page.request.post(fixture.baseURL + '/petugas/loans/groups/store', {form: {[tokenName]: token, request_key: requestKey}, maxRedirects: 0});
    ok(retry.status() === 303 || retry.status() === 302, 'A repeated group request redirects.');
    ok(new URL(retry.headers().location, fixture.baseURL).pathname === groupUrl.pathname, 'A repeated group request opens the original receipt.');
    await goto(groupUrl.pathname);
    await groupReceipt.getByRole('link', {name: 'Kembalikan unit'}).first().click();
    for (const input of await page.locator('[data-return-quantity]').all()) await input.check();
    await page.locator('[name=accessory_return_confirmed]').check();
    await Promise.all([page.waitForURL(/\/groups\/\d+$/), page.getByRole('button', {name: 'Konfirmasi Pengembalian', exact: true}).click()]);
    ok(await groupReceipt.locator('[data-group-progress]').innerText() === '1/2 kembali' && await groupReceipt.getByRole('link', {name: 'Kembalikan unit'}).count() === 1,
      'Returning a single unit updates group progress and leaves the other member outstanding.');
    await screen('loan-group-receipt');
    await page.pdf({path: path.join(fixture.run, 'bukti-peminjaman-kelompok.pdf'), preferCSSPageSize: true, printBackground: true});

    await goto('/petugas/items/1/edit');
    const editor = page.locator('[data-accessory-editor]');
    ok(await editor.locator('[data-accessory-composer]').isVisible(), 'Empty editor offers direct entry.');
    ok(!(await editor.locator('[data-accessory-count]').isVisible()), 'Empty editor has no technical 0/30 counter.');
    ok(!(await editor.locator('[data-accessory-undo-panel]').isVisible()), 'Undo appears only after a deletion.');
    for (const width of [320, 390, 1280]) {
      await page.setViewportSize({width, height: 844});
      await editor.screenshot({path: path.join(fixture.run, `editor-empty-${width}.png`)});
      const nameBox = await editor.locator('[data-accessory-name]').boundingBox();
      const qtyBox = await editor.locator('[data-accessory-quantity]').boundingBox();
      ok(nameBox.width > qtyBox.width, 'Name input receives more space than quantity.');
    }
    await page.setViewportSize({width: 390, height: 844});
    await editor.locator('[data-accessory-name]').fill('Charger');
    await editor.locator('[data-accessory-required]').check();
    await editor.locator('[data-accessory-name]').press('Enter');
    let row = editor.locator('[data-accessory-row]').nth(0);
    ok(await row.locator('[name$="[name]"]').inputValue() === 'Charger', 'Enter adds a named accessory without submitting the whole form.');
    ok(new URL(page.url()).pathname.endsWith('/edit'), 'Enter stays in item editor.');
    ok(!(await editor.locator('[data-accessory-composer]').isVisible()), 'Form closes after entry so the list can be reviewed.');
    await editor.locator('[data-accessory-open]').click();
    await editor.locator('[data-accessory-name]').fill('  charger  ');
    await editor.locator('[data-accessory-add]').click();
    ok(await editor.locator('[data-accessory-row]').count() === 1, 'Duplicate names are rejected before insertion.');
    ok((await editor.locator('[data-accessory-error]').innerText()).includes('sudah ada'), 'Duplicate error is displayed beside the entry form.');
    await page.getByRole('button', {name: 'Simpan Perubahan', exact: true}).click();
    ok(new URL(page.url()).pathname.endsWith('/edit'), 'Invalid unfinished entry blocks saving the whole item.');
    ok(await editor.locator('[data-accessory-name]').inputValue() === '  charger  ', 'Blocked save preserves the draft so it can be corrected.');
    await editor.locator('[data-accessory-name]').fill('Tas');
    await editor.locator('[data-accessory-add]').click();
    await row.locator('[data-accessory-edit]').click();
    await editor.locator('[data-accessory-name]').fill('Nama belum diterapkan');
    await editor.locator('[data-accessory-cancel]').click();
    ok(await row.locator('[name$="[name]"]').inputValue() === 'Charger', 'Cancel edit retains the original row.');
    await editor.locator('[data-accessory-row]').nth(1).locator('[data-accessory-edit]').click();
    await editor.locator('[data-accessory-quantity]').fill('0');
    await editor.locator('[data-accessory-add]').click();
    ok(await editor.locator('[data-accessory-error]').isVisible(), 'Invalid quantity has an inline error.');
    await editor.locator('[data-accessory-quantity]').fill('2');
    await editor.locator('[data-accessory-add]').click();
    ok(await editor.locator('[data-accessory-row]').count() === 2, 'Edit updates an existing row without duplicating it.');
    ok(await editor.locator('[data-accessory-row]').nth(1).locator('[name$="[quantity]"]').inputValue() === '2', 'Edited quantity is applied.');
    await editor.locator('[data-accessory-row]').nth(1).locator('[data-accessory-remove]').click();
    ok(await editor.locator('[data-accessory-row]').count() === 1, 'Removing a row updates the editor.');
    await editor.locator('[data-accessory-undo]').click();
    ok(await editor.locator('[data-accessory-row]').count() === 2, 'Undo restores a removed row.');
    ok(await editor.locator('[data-accessory-row]').nth(1).locator('[name$="[name]"]').inputValue() === 'Tas', 'Undo preserves entered values.');
    await screen('master');
    for (const width of [320, 390, 1280]) {
      await page.setViewportSize({width, height: 844});
      await editor.screenshot({path: path.join(fixture.run, `master-editor-${width}.png`)});
      const tapTarget = await editor.locator('[data-accessory-remove]').first().boundingBox();
      ok(tapTarget.width >= 44 && tapTarget.height >= 44, 'Remove controls remain touch accessible.');
      const editTarget = await editor.locator('[data-accessory-edit]').first().boundingBox();
      ok(editTarget.width >= 44 && editTarget.height >= 44, 'Edit controls remain touch accessible.');
      const clipped = await editor.evaluate(el => el.scrollWidth > el.clientWidth + 1);
      ok(!clipped, 'Editor fits inside its container.');
    }
    await page.setViewportSize({width: 390, height: 844});
    await row.locator('[data-accessory-edit]').click();
    for (const width of [320, 390, 1280]) {
      await page.setViewportSize({width, height: 844});
      await editor.screenshot({path: path.join(fixture.run, `editor-edit-${width}.png`)});
    }
    await editor.locator('[data-accessory-cancel]').click();
    await page.setViewportSize({width: 390, height: 844});
    await Promise.all([page.waitForURL(url => url.pathname === '/petugas/items/1'), page.getByRole('button', {name: 'Simpan Perubahan', exact: true}).click()]);
    ok(await page.getByText('Wajib menyertai unit', {exact: true}).count() > 0, 'Master checklist persisted.');
    await goto('/petugas/loans/create/1');
    await page.locator('.borrower-option[data-type=teacher]').click();
    await page.locator('.person-row').first().click();
    const next = page.getByRole('button', {name: 'Lanjut ke kelengkapan'});
    const checkout = page.locator('#loanAccessoriesStep');
    ok(!(await checkout.isVisible()), 'The checklist is a separate third step.');
    await next.click();
    ok(!(await checkout.isVisible()) && await page.locator('[name=purpose]').evaluate(input => input.validationMessage) === 'Keperluan wajib diisi.', 'Empty details block the next step with Indonesian feedback.');
    await page.locator('[name=purpose]').fill('Pembelajaran menggunakan Chromebook');
    await page.locator('[name=usage_location]').fill('VIII A');
    const tomorrow = new Date(Date.now() + 86400000).toISOString().slice(0, 10);
    await page.locator('[name=due_date]').fill(tomorrow);
    await next.click();
    ok(await checkout.isVisible() && !(await page.locator('#loanDetailsStep').isVisible()), 'Continuing opens the checklist on the same page.');
    const requiredAccessory = page.locator('[name^="accessory_sent["][required]').first();
    const requiredMessage = 'Charger wajib disertakan bersama unit. Periksa dan centang setelah diserahkan kepada peminjam.';
    ok(await requiredAccessory.evaluate(input => input.validationMessage) === requiredMessage, 'Required accessory validation is Indonesian and names the missing item.');
    ok(await page.locator('[name^="accessory_sent["]:not([required])').first().evaluate(input => input.validationMessage) === '', 'Optional accessories are allowed to remain unchecked.');
    await page.locator('[name=accessory_confirmed]').check();
    await page.getByRole('button', {name: 'Simpan peminjaman', exact: true}).click();
    ok(new URL(page.url()).pathname === '/petugas/loans/create/1', 'Missing required accessory blocks checkout before submission.');
    ok(await requiredAccessory.evaluate(input => document.activeElement === input), 'Browser focuses the missing required accessory.');
    await requiredAccessory.check();
    ok(await requiredAccessory.evaluate(input => input.validationMessage) === '', 'Checking the required accessory clears its custom validation.');
    await requiredAccessory.uncheck();
    ok(await requiredAccessory.evaluate(input => input.validationMessage) === requiredMessage, 'Unchecking restores the Indonesian message.');
    await requiredAccessory.check();
    await page.locator('[name=accessory_confirmed]').uncheck();
    ok((await page.locator('[name=accessory_confirmed]').evaluate(input => input.validationMessage)).startsWith('Konfirmasikan bahwa'), 'Checkout confirmation is also validated in Indonesian.');
    const extras = page.locator('[data-accessory-editor]');
    ok(!(await extras.locator('[data-accessory-composer]').isVisible()), 'Optional checkout extras start collapsed.');
    ok(await extras.locator('[data-accessory-open]').getAttribute('aria-expanded') === 'false', 'Collapsed state is accessible.');
    await extras.locator('[data-accessory-open]').click();
    ok(await extras.locator('[data-accessory-composer]').isVisible(), 'Add opens the optional extra form.');
    ok(await extras.locator('input[type=checkbox]').count() === 0, 'An extra only needs a name and quantity.');
    await page.screenshot({path: path.join(fixture.run, 'checkout-extra-390.png'), fullPage: true});
    await extras.locator('[data-accessory-cancel]').click();
    ok(!(await extras.locator('[data-accessory-composer]').isVisible()), 'Cancel closes an optional extra form.');
    await extras.locator('[data-accessory-open]').click();
    await extras.locator('[data-accessory-name]').fill('Kabel HDMI');
    await extras.locator('[data-accessory-quantity]').fill('2');
    await extras.locator('[data-accessory-add]').click();
    row = extras.locator('[data-accessory-row]').first();
    ok(await row.locator('[name$="[sent]"]').inputValue() === '1' && await row.locator('[name$="[is_required]"]').count() === 0, 'An added extra is sent without a required-for-readiness flag.');
    await row.getByRole('button', {name: 'Ubah kelengkapan Kabel HDMI'}).click();
    ok(await extras.locator('[data-accessory-quantity]').inputValue() === '2', 'Editing an extra restores the saved quantity.');
    await extras.locator('[data-accessory-add]').click();
    await page.locator('[name=accessory_confirmed]').check();
    await page.evaluate(() => history.back());
    await page.locator('#loanDetailsStep').waitFor({state: 'visible'});
    ok(await page.locator('[name=purpose]').inputValue() === 'Pembelajaran menggunakan Chromebook', 'Browser Back keeps the draft details.');
    await page.evaluate(() => history.forward());
    await checkout.waitFor({state: 'visible'});
    ok(await requiredAccessory.isChecked() && await row.locator('[name$="[name]"]').inputValue() === 'Kabel HDMI', 'Browser Forward keeps checked and added accessories.');
    await page.getByRole('button', {name: 'Kembali ke detail'}).click();
    await page.locator('#loanDetailsStep').waitFor({state: 'visible'});
    await page.locator('[name=purpose]').fill('');
    await page.evaluate(() => history.forward());
    await page.getByText('Keperluan wajib diisi.', {exact: true}).waitFor({state: 'visible'});
    ok(!(await checkout.isVisible()), 'Browser Forward cannot bypass newly incomplete details.');
    await page.locator('[name=purpose]').fill('Pembelajaran menggunakan Chromebook');
    await next.click();
    ok(await page.locator('[name=accessory_confirmed]').isChecked(), 'The in-page Back and Next also preserve inspection confirmation.');
    await screen('checkout');
    await Promise.all([page.waitForURL(/\/petugas\/loans\/\d+\/success/), page.getByRole('button', {name: 'Simpan peminjaman', exact: true}).click()]);
    const loanId = Number(new URL(page.url()).pathname.split('/')[3]);
    ok(loanId > 0, 'Loan created.');
    await goto('/petugas/items/1');
    const returnAction = page.locator('#detail .detail-header-actions').getByRole('link', {name: 'Proses Pengembalian'});
    ok((await returnAction.getAttribute('href')).endsWith(`/petugas/loans/${loanId}/return`), 'A borrowed item has the return action in the header.');
    ok((await page.locator('#detail .detail-context').innerText()).includes('Guru Uji') && (await page.locator('#detail .detail-context').innerText()).includes('Batas kembali'), 'The active borrower and deadline are visible together.');
    await goto(`/petugas/loans/${loanId}/return`);
    ok((await page.locator('[name=accessory_return_confirmed]').evaluate(input => input.validationMessage)).startsWith('Konfirmasikan bahwa'), 'Return confirmation is also validated in Indonesian.');
    ok(await page.locator('[data-return-quantity]').count() === 2, 'Only sent accessories are required on return.');
    ok(await page.locator('[data-return-quantity]:checked').count() === 0, 'Return inspection must start unchecked.');
    await page.locator('[data-return-quantity][type=number]').fill('1');
    await page.locator('[name=return_notes]').fill('Charger belum kembali; satu kabel HDMI belum kembali.');
    await page.locator('[name=return_photo]').setInputFiles(fixture.image);
    await page.locator('[name=accessory_return_confirmed]').check();
    ok((await page.locator('[data-accessory-result]').innerText()).includes('Unit ditahan'), 'Readiness is held for missing required accessory.');
    await screen('return');
    await Promise.all([page.waitForURL(/\/success/), page.getByRole('button', {name: 'Konfirmasi Pengembalian', exact: true}).click()]);
    await goto('/petugas/items/1');
    ok((await page.locator('body').innerText()).includes('Menunggu Kelengkapan'), 'Returned item held.');
    ok((await page.locator('#detail .detail-context').innerText()).includes('Charger · Kurang 1'), 'The header area shows which required accessory is missing.');
    ok((await page.locator('#detail .detail-header-actions').getByRole('link', {name: 'Tindak Lanjuti', exact: true}).getAttribute('href')).endsWith(`/petugas/loans/${loanId}#loanAccessories`), 'A single pending loan opens directly from the header.');
    await goto('/petugas');
    await screen('dashboard-pending');
    ok(await page.locator('.field-jobs .accessory-task').count() === 1, 'Unresolved accessories remain visible as a dashboard task.');
    await goto('/petugas/accessories');
    ok(await page.getByRole('link', {name: 'Periksa & tindak lanjuti'}).count() === 2, 'Both missing accessories appear in follow-up.');
    await screen('pending');
    await goto(`/petugas/loans/${loanId}`);
    const returnPhotoUrl = await page.locator('.history-photo-thumb img').first().getAttribute('src');
    const staffEvidence = await page.request.get(returnPhotoUrl);
    ok(staffEvidence.ok() && staffEvidence.headers()['content-type'].startsWith('image/'), 'Staff can view newly uploaded private evidence.');
    await screen('detail');
    const summary = page.getByText('Catat penyelesaian Charger', {exact: true});
    await summary.click();
    const resolution = page.locator('details').filter({has: summary}).locator('form');
    await resolution.locator('[name=kind]').selectOption('returned');
    await resolution.locator('[name=note]').fill('Charger asli diterima dan berfungsi.');
    await Promise.all([page.waitForURL(url => url.pathname === `/petugas/loans/${loanId}`), resolution.getByRole('button', {name: 'Simpan penyelesaian'}).click()]);
    await goto('/petugas/items/1');
    ok(await page.getByRole('link', {name: 'Mulai Peminjaman', exact: true}).isVisible(), 'Optional shortage does not hold unit after charger returned.');
    await goto(`/petugas/loans/${loanId}`);
    const cableSummary = page.getByText('Catat penyelesaian Kabel HDMI', {exact: true});
    await cableSummary.click();
    const cableForm = page.locator('details').filter({has: cableSummary}).locator('form');
    await cableForm.locator('[name=kind]').selectOption('replacement');
    await cableForm.locator('[name=note]').fill('Satu kabel pengganti diterima dan diperiksa.');
    await Promise.all([page.waitForURL(url => url.pathname === `/petugas/loans/${loanId}`), cableForm.getByRole('button', {name: 'Simpan penyelesaian'}).click()]);
    await goto('/petugas/accessories');
    ok((await page.locator('body').innerText()).includes('Tidak ada kekurangan terbuka'), 'Follow-up fully resolved.');
    ok(await page.locator('#perPageSelect').count() === 0, 'Empty follow-up has no unnecessary pagination.');
    await screen('pending-empty');
    // Historical snapshots must survive later edits through the actual master form.
    await goto('/petugas/items/1/edit');
    ok(!(await editor.locator('[data-accessory-composer]').isVisible()), 'Existing master opens as a summary instead of an empty form.');
    await page.locator('[data-accessory-row]').first().locator('[data-accessory-edit]').click();
    await editor.locator('[data-accessory-name]').fill('Adaptor baru');
    // Saving the whole item must include a valid draft even if its local Apply button was not pressed.
    await Promise.all([page.waitForURL(url => url.pathname === '/petugas/items/1'), page.getByRole('button', {name: 'Simpan Perubahan', exact: true}).click()]);
    ok((await page.locator('body').innerText()).includes('Adaptor baru'), 'Saving the parent form includes a valid unfinished edit.');
    await goto(`/petugas/loans/${loanId}`);
    ok((await page.locator('#loanAccessories').innerText()).includes('Charger'), 'Snapshot keeps original name.');
    ok(!(await page.locator('#loanAccessories').innerText()).includes('Adaptor baru'), 'Master rename does not rewrite history.');
    await page.pdf({path: path.join(fixture.run, 'bukti-serah-terima.pdf'), format: 'A4', printBackground: true});
    ok(fs.statSync(path.join(fixture.run, 'bukti-serah-terima.pdf')).size > 1000, 'Printable receipt produced.');
    // Preserve the current all-good return path on an older loan without a checklist.
    await goto('/petugas/loans/create/2');
    ok(!(await page.locator('[data-accessory-composer]').isVisible()), 'Extras also start collapsed for units without a master checklist.');
    await page.locator('.borrower-option[data-type=teacher]').click();
    await page.locator('.person-row').first().click();
    await page.locator('[name=purpose]').fill('Pemeriksaan unit tanpa kelengkapan');
    await page.locator('[name=usage_location]').fill('Ruang TU');
    await page.locator('[name=due_date]').fill(tomorrow);
    await page.getByRole('button', {name: 'Lanjut ke kelengkapan'}).click();
    await page.locator('[name=accessory_confirmed]').check();
    await Promise.all([page.waitForURL(/\/petugas\/loans\/\d+\/success/), page.getByRole('button', {name: 'Simpan peminjaman', exact: true}).click()]);
    const emptyLoanId = Number(new URL(page.url()).pathname.split('/')[3]);
    await goto(`/petugas/loans/${emptyLoanId}/return`);
    ok(await page.locator('[data-return-quantity]').count() === 0, 'Empty checklist does not invent accessories.');
    await page.locator('[name=accessory_return_confirmed]').check();
    await Promise.all([page.waitForURL(/\/success/), page.getByRole('button', {name: 'Konfirmasi Pengembalian', exact: true}).click()]);
    await goto('/petugas/items/1/edit');
    const photoSection = page.getByRole('heading', {name: 'Foto Barang'});
    ok(await photoSection.evaluate(heading => Boolean(heading.compareDocumentPosition(document.querySelector('[data-accessory-editor]')) & Node.DOCUMENT_POSITION_FOLLOWING)), 'Photo section precedes the accessories in edit mode.');
    await page.locator('[name=serial_number]').fill('SERIAL-NEW');
    ok(await page.locator('[name=serial_photo]').evaluate(input => !input.validity.valid), 'Changing the serial invalidates the existing serial photo.');
    await page.evaluate(() => document.getElementById('itemForm').submit());
    await page.getByText('Unggah foto yang sesuai dengan nomor seri.', {exact: true}).waitFor();
    ok(await page.locator('[name=serial_number]').inputValue() === 'SERIAL-NEW', 'Server rejects a changed serial without a new photo and restores the draft.');

    await goto('/petugas/items/create');
    ok(await photoSection.evaluate(heading => Boolean(heading.compareDocumentPosition(document.querySelector('[data-accessory-editor]')) & Node.DOCUMENT_POSITION_FOLLOWING)), 'Photo section precedes the accessories in create mode.');
    const photoSlots = page.locator('#itemForm .item-photo-grid [data-photo]');
    for (const width of [1280, 390]) {
      await page.setViewportSize({width, height: 844});
      await page.evaluate(() => document.fonts.ready);
      const [physical, serial] = await photoSlots.evaluateAll(slots => slots.map(slot => {
        const {y, height} = slot.getBoundingClientRect();
        return {y, height};
      }));
      if (width === 1280) {
        ok(Math.abs(physical.y - serial.y) <= 1 && physical.height === serial.height, 'Empty photo slots align even when help text wraps.');
      } else {
        ok(serial.y > physical.y + physical.height, 'Photo slots stack on a narrow screen.');
      }
      await page.locator('.item-photo-section').screenshot({path: path.join(fixture.run, `foto-barang-${width}.png`)});
    }
    await page.locator('[name=category]').selectOption('Perangkat Komputer');
    await page.locator('[name=item_name]').fill('Unit uji tanpa nomor seri');
    await page.locator('[name=brand]').fill('Uji');
    await page.locator('[name=model]').fill('Uji');
    await page.locator('[name=acquisition_date]').fill('2026-01-01');
    await page.locator('[name=location]').selectOption('Ruang Sarana');
    await page.locator('[name=condition]').selectOption('good');
    await page.locator('[name=specifications]').fill('Fixture uji bukti foto barang.');
    await page.locator('[name=serial_status]').selectOption('missing');
    ok(await page.locator('[name=serial_photo]').isDisabled(), 'No serial means no serial photo is requested.');
    ok(await page.locator('[name=physical_photo]').evaluate(input => !input.validity.valid), 'Physical photo is still required.');
    await page.locator('[name=physical_photo]').setInputFiles(fixture.image);
    await Promise.all([page.waitForURL(/\/petugas\/items\/\d+$/), page.getByRole('button', {name: 'Simpan Unit & Buat QR', exact: true}).click()]);
    const serialFixtureId = Number(new URL(page.url()).pathname.split('/').pop());
    const unrelatedPhoto = await page.locator('#detail .detail-photos img').first().getAttribute('src');
    ok((await page.locator('#detail').innerText()).includes('Tidak ada nomor seri'), 'Missing serial reason is shown on item detail.');
    await goto(`/petugas/items/${serialFixtureId}/edit`);
    ok(await page.locator('[name=serial_status]').inputValue() === 'missing', 'The saved serial reason is restored in edit mode.');
    await page.locator('[name=serial_status]').selectOption('unreadable');
    await Promise.all([page.waitForURL(/\/petugas\/items\/\d+$/), page.getByRole('button', {name: 'Simpan Perubahan', exact: true}).click()]);
    ok((await page.locator('#detail').innerText()).includes('Nomor seri tidak terbaca'), 'Unreadable serial reason persists without a second photo.');
    await goto('/petugas/items/2/edit');
    await page.locator('[name=serial_number]').fill('SERIAL-1');
    await page.locator('[name=serial_photo]').setInputFiles(fixture.image);
    await page.waitForFunction(() => !document.querySelector('[data-processing="true"]'));
    await page.evaluate(() => document.getElementById('itemForm').submit());
    await page.getByText('Nomor seri sudah digunakan. Konfirmasikan penggunaan nomor seri yang sama sebelum menyimpan.', {exact: true}).waitFor();
    await page.waitForLoadState('load');
    ok(true, 'Server rejects a duplicate serial without explicit confirmation.');
    await page.locator('[name=serial_photo]').setInputFiles(fixture.image);
    await page.waitForFunction(() => !document.querySelector('[data-processing="true"]'));
    const canceledDialog = page.waitForEvent('dialog');
    const pendingSave = page.getByRole('button', {name: 'Simpan Perubahan', exact: true}).click();
    const warning = await canceledDialog;
    ok(warning.message().includes('Laptop Dapodik') && warning.message().includes('SERIAL-1'), 'Duplicate warning identifies the serial and matching item.');
    await warning.dismiss();
    await pendingSave;
    ok(await page.locator('[name=serial_number]').inputValue() === 'SERIAL-1'
      && await page.locator('[name=serial_photo]').evaluate(input => input.files.length === 1), 'Cancel preserves the serial and selected photo.');
    page.once('dialog', dialog => dialog.accept());
    await Promise.all([page.waitForURL(url => url.pathname === '/petugas/items/2'), page.getByRole('button', {name: 'Simpan Perubahan', exact: true}).click()]);
    ok((await page.locator('#detail').innerText()).includes('SERIAL-1'), 'Confirmed duplicate serial is saved when editing an item.');
    for (const role of ['kepala_sekolah', 'peminjam', 'admin']) {
      const readContext = await browser.newContext({viewport: {width: 390, height: 844}});
      await guard(readContext);
      const readPage = await readContext.newPage();
      await login(readPage, role);
      const [physical, serial, evidence, unrelated] = await Promise.all(
        [inventoryPhotos[0], inventoryPhotos[1], returnPhotoUrl, unrelatedPhoto].map(url => readContext.request.get(url))
      );
      if (role === 'kepala_sekolah') {
        ok([physical, serial, evidence, unrelated].every(response => response.ok()), 'Principal can review inventory photos and evidence.');
      } else if (role === 'peminjam') {
        ok(physical.ok() && [serial, evidence, unrelated].every(response => response.status() === 404), 'Borrower can only view a physical photo from their own loan history.');
      } else {
        ok([physical, serial, evidence, unrelated].every(response => response.status() === 404), 'Admin account without inventory permissions cannot open photos.');
      }
      if (role !== 'admin') {
        await readPage.goto(fixture.baseURL + (role === 'peminjam' ? `/peminjam/loans/${loanId}` : `/kepala/loans/${loanId}`));
        ok(await readPage.locator('#loanAccessories').count() === 1, `${role}: checklist readable.`);
        ok(await readPage.locator('form[action*="/accessories/"]').count() === 0, `${role}: no mutation controls.`);
      }
      await readPage.goto(fixture.baseURL + '/petugas/accessories');
      ok(new URL(readPage.url()).pathname !== '/petugas/accessories', `${role}: petugas route denied.`);
      await readContext.close();
    }
    const peerContext = await browser.newContext({viewport: {width: 390, height: 844}});
    await guard(peerContext, fixture.peerURL);
    const peer = await peerContext.newPage();
    peer.setDefaultTimeout(12000);
    await login(peer, 'petugas2', fixture.peerURL);
    const operators = [{page, origin: fixture.baseURL}, {page: peer, origin: fixture.peerURL}];
    const submit = (screen, button, route) => Promise.all([
      screen.waitForURL(route),
      screen.getByRole('button', {name: button, exact: true}).click(),
    ]);
    await Promise.all(operators.map(async ({page: screen, origin}, index) => {
      await screen.goto(origin + '/petugas/items/create');
      await screen.locator('[name=category]').selectOption(index ? 'Elektronik' : 'Perangkat Komputer');
      await screen.locator('[name=item_name]').fill('Unit paralel ' + index);
      await screen.locator('[name=brand]').fill('Uji');
      await screen.locator('[name=model]').fill('Uji');
      await screen.locator('[name=serial_number]').fill('SERIAL-1');
      await screen.locator('[name=serial_photo]').setInputFiles(fixture.image);
      screen.once('dialog', dialog => dialog.accept());
      await screen.locator('[name=acquisition_date]').fill('2026-01-01');
      await screen.locator('[name=location]').selectOption(index ? 'Ruang TU' : 'Ruang Sarana');
      await screen.locator('[name=condition]').selectOption('good');
      await screen.locator('[name=specifications]').fill('Unit uji penomoran paralel.');
      await screen.locator('[name=physical_photo]').setInputFiles(fixture.image);
    }));
    await Promise.all(operators.map(({page: screen}) => submit(screen, 'Simpan Unit & Buat QR', /\/petugas\/items\/\d+$/)));
    for (const operator of operators) {
      operator.itemId = Number(new URL(operator.page.url()).pathname.split('/').pop());
      operator.code = await operator.page.locator('.item-unit-code b').innerText();
      const qr = await operator.page.request.get(operator.origin + '/petugas/items/' + operator.itemId + '/qr-image');
      ok(qr.ok() && (await qr.body()).subarray(0, 8).equals(Buffer.from('89504e470d0a1a0a', 'hex')), 'Each saved item has a readable PNG QR.');
    }
    ok(operators[0].code !== operators[1].code && operators.every(operator => /^UNT-\d{6,}$/.test(operator.code)), 'Concurrent item codes differ and retain their original format.');
    await Promise.all(operators.map(async ({page: screen, origin, itemId}) => {
      await screen.goto(origin + '/petugas/loans/create/' + itemId);
      await screen.locator('.borrower-option[data-type=teacher]').click();
      await screen.locator('.person-row').first().click();
      await screen.locator('[name=purpose]').fill('Uji nomor peminjaman paralel');
      await screen.locator('[name=usage_location]').fill('Ruang TU');
      await screen.locator('[name=due_date]').fill(tomorrow);
      await screen.getByRole('button', {name: 'Lanjut ke kelengkapan'}).click();
      await screen.locator('[name=accessory_confirmed]').check();
    }));
    await Promise.all(operators.map(({page: screen}) => submit(screen, 'Simpan peminjaman', /\/petugas\/loans\/\d+\/success$/)));
    const parallelLoan = Number(new URL(page.url()).pathname.split('/')[3]);
    await page.goto(fixture.baseURL + '/petugas/loans/' + parallelLoan + '/return');
    await page.locator('label').filter({has: page.locator('[name=condition_in][value=minor_damage]')}).click();
    await page.locator('[name=return_notes]').fill('Kerusakan ditemukan saat pengembalian.');
    await page.locator('[name=return_photo]').setInputFiles(fixture.image);
    await page.locator('[name=accessory_return_confirmed]').check();
    await peer.goto(fixture.peerURL + '/petugas/damages/create/' + operators[1].itemId);
    await peer.locator('[name=damage_level]').selectOption('minor_damage');
    await peer.locator('[name=description]').fill('Laporan manual paralel dengan pengembalian.');
    await Promise.all([
      submit(page, 'Konfirmasi Pengembalian', /\/petugas\/damages\/\d+$/),
      Promise.all([peer.waitForURL(/\/petugas\/damages\/\d+$/), peer.locator('form[action*="/damages/store"] button[type=submit]').click()]),
    ]);
    await peerContext.close();
    ok(errors.length === 0, 'No JavaScript errors: ' + errors.join('; '));
    console.log(`Browser/mobile/accessory flow OK (${checks} checks; widths 320/390/768/1280).`);
  } catch (error) {
    await page.screenshot({path: path.join(fixture.run, 'failure.png'), fullPage: true}).catch(() => {});
    console.error('Current page:', page.url());
    console.error('JavaScript errors:', errors.join('; '));
    throw error;
  } finally { await browser.close(); }
})().catch(error => { console.error(error.stack); process.exitCode = 1; });
