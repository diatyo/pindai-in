const path = require('node:path');
const { createRequire } = require('node:module');
let playwright;
try { playwright = require('playwright'); }
catch {
  const modules = process.env.PINDAI_PLAYWRIGHT_MODULES;
  if (!modules) throw new Error('Install playwright for developer tests or set PINDAI_PLAYWRIGHT_MODULES.');
  playwright = createRequire(path.join(modules, '__pindai_test.cjs'))('playwright');
}
module.exports = playwright;
