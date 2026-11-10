const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const {chromium} = require('./browser-runtime.cjs');
const fixture = JSON.parse(fs.readFileSync(process.argv[2], 'utf8'));
(async () => {
  const browser = await chromium.launch({headless: true, executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe'});
  try {
    const page = await browser.newPage();
    page.setDefaultTimeout(30000);
    await page.route('**/*', route => new URL(route.request().url()).origin === fixture.baseURL ? route.continue() : route.abort());
    await page.goto(fixture.baseURL + '/login');
    await page.locator('[name=username]').fill('acc_petugas');
    await page.locator('[name=password]').fill('Accessory-fixture-password');
    await Promise.all([page.waitForURL(/petugas/), page.locator('button[type=submit]').click()]);
    await page.goto(fixture.baseURL + '/petugas/items');
    const widths = [[1280, 720], [1366, 680], [1366, 768], [1440, 900], [1536, 864], [1600, 900], [1792, 1008], [1800, 1000], [1920, 993], [1920, 1080], [390, 844]];
    for (const [width, height] of widths) {
      await page.setViewportSize({width, height});
      await page.evaluate(() => document.fonts.ready);
      const result = await page.evaluate(() => {
        const table = document.querySelector('.bulk-qr-form .table');
        // Exercise serials that are long enough to squeeze the original auto-layout table.
        table.querySelector('tbody td:nth-child(4)').textContent = '13600287484/MRJT31100313600B3A8400';
        const rows = Array.from(table.querySelectorAll('tbody tr'));
        const actions = rows.map(row => {
          const cell = row.lastElementChild.getBoundingClientRect();
          const buttons = Array.from(row.querySelectorAll('.row-btn')).map(button => {
            const {x, y, width, height} = button.getBoundingClientRect();
            return {x, y, width, height};
          });
          return {height: row.getBoundingClientRect().height, cellRight: cell.right, buttons};
        });
        const wrap = table.parentElement;
        const tabs = document.querySelector('#items .toolbar .tabs').getBoundingClientRect();
        const tools = document.querySelector('#items .toolbar-actions').getBoundingClientRect();
        return {pageFits: document.documentElement.scrollWidth <= innerWidth, tableFits: wrap.scrollWidth <= wrap.clientWidth + 1, actions, toolbarRows: Math.abs(tabs.y - tools.y) < 12 ? 1 : 2};
      });
      assert(result.pageFits, 'Page overflow at ' + width);
      if (width >= 1200 && width <= 1799) {
        assert(result.tableFits, 'Inventory table overflow at ' + width);
        assert(result.actions.every(row => row.buttons.every(button => button.x + button.width <= row.cellRight + 1)), 'Row action is clipped at ' + width);
        assert(result.actions.every(row => row.buttons.every(button => Math.abs(button.y - row.buttons[0].y) <= 1)), 'Row actions stack at ' + width);
      }
      console.log(width + 'x' + height, JSON.stringify({tableFits: result.tableFits, rowHeight: result.actions[0].height, toolbarRows: result.toolbarRows}));
      await page.screenshot({path: path.join(fixture.run, `inventory-desktop-${width}x${height}.png`)});
    }
    console.log('PASS inventory desktop layout at laptop, full-HD and mobile widths.');
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
