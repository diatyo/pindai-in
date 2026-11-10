const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const path = require('node:path');
const root = path.resolve(__dirname, '../..');
const view = fs.readFileSync(path.join(root, 'app/Views/petugas/item_form.php'), 'utf8');
const common = fs.readFileSync(path.join(root, 'public/assets/js/pindai.js'), 'utf8');
const fields = [
  { type: 'text', tagName: 'INPUT', value: '', files: [], label: 'Nama barang' },
  { type: 'file', tagName: 'INPUT', value: '', files: [], label: 'Foto fisik', required: true },
  { type: 'file', tagName: 'INPUT', value: '', files: [], label: 'Foto nomor seri' },
  { type: 'select-one', tagName: 'SELECT', value: 'known', files: [], label: 'Keterangan nomor seri' },
  { type: 'text', tagName: 'INPUT', value: 'SN-001', files: [], label: 'Nomor seri' },
].map(f => Object.assign(f, {
  dataset: {}, labels: [{ textContent: f.label + ' *' }], validity: { valid: true }, listeners: {},
  setCustomValidity(message) { this.validity.valid = !message; },
  reportValidity() {},
  addEventListener(name, callback) { this.listeners[name] = callback; },
}));
fields[4].dataset.savedSerial = 'SN-001';
fields[2].closest = () => ({classList: {toggle() {}}});
const formElements = {
  '#item_serial_status': fields[3],
  '#item_serial_number': fields[4],
  '#item_serial_photo': fields[2],
  '#itemSerialField': {},
  '#itemSerialPhotoField': {},
  '#itemSerialChanged': {},
};
const button = { disabled: false, textContent: 'Simpan Unit & Buat QR' };
const listeners = {}, windowListeners = {};
let busy = false, toasts = 0, bubbles = 0;
const form = {
  dataset: {},
  querySelectorAll: () => fields,
  querySelector: selector => selector.startsWith('button') ? button : (selector.startsWith('#') ? formElements[selector] : fields.find(f => f.dataset.processing === 'true')),
  addEventListener(name, fn) { (listeners[name] ??= []).push(fn); },
  checkValidity: () => fields.every(f => f.validity.valid), reportValidity() { bubbles++; },
  setAttribute() { busy = true; }, removeAttribute() { busy = false; },
  dispatchEvent(event) { for (const fn of listeners[event.type] ?? []) fn(event); },
};
class TestEvent {
  constructor(type) { this.type = type; this.defaultPrevented = false; }
  preventDefault() { this.defaultPrevented = true; }
}
const context = vm.createContext({ document: { getElementById: () => form }, window: { addEventListener(name, fn) { windowListeners[name] = fn; } }, Event: TestEvent, toast() { toasts++; } });
vm.runInContext(view.match(/<script>([\s\S]*?)<\/script>/)[1], context);
vm.runInContext(common.slice(common.indexOf('  function setupItemForm('), common.indexOf('  function openPhotoViewer(')), context);
context.setupItemForm(form);
assert.equal(button.disabled, false);
const rejected = new TestEvent('submit'); form.dispatchEvent(rejected);
assert.equal(rejected.defaultPrevented, true);
assert.equal(bubbles, 1);
assert.equal(button.textContent, 'Simpan Unit & Buat QR');
assert.equal(busy, false); assert.equal(toasts, 0);
fields[0].value = 'Proyektor'; fields[0].listeners.input();
assert.equal(button.disabled, false);
fields[1].files = [{}]; fields[1].listeners.change();
assert.equal(button.disabled, false);
fields[2].files = [{}]; fields[2].dataset.processing = 'true'; fields[2].listeners.change();
assert.equal(button.disabled, true);
delete fields[2].dataset.processing;
form.dispatchEvent(new TestEvent('item-form-refresh'));
assert.equal(button.disabled, false);
fields[0].value = '  '; fields[0].listeners.input(); assert.equal(button.disabled, false);
fields[0].value = 'Proyektor'; fields[0].listeners.input();
const accepted = new TestEvent('submit'); form.dispatchEvent(accepted);
assert.equal(accepted.defaultPrevented, false); assert.equal(busy, true); assert.equal(button.disabled, true);
assert.equal(button.textContent, 'Menyimpan unit dan membuat QR...');
fields[2].files = []; windowListeners.pageshow();
assert.equal(button.disabled, false); assert.equal(button.textContent, 'Simpan Unit & Buat QR'); assert.equal(busy, false);
console.log('PASS: incomplete form never enters saving state; both photos must be ready; valid submission and back navigation work.');

// Edit mode accepts existing photos and still requires any photo missing from storage.
fields[1].files = []; fields[2].files = [];
fields[1].dataset.existingPhoto = '1'; fields[2].dataset.existingPhoto = '1';
fields[1].required = false;
form.dispatchEvent(new TestEvent('item-form-refresh'));
assert.equal(form.checkValidity(), true);
fields[2].dataset.existingPhoto = '0';
const missingEditPhoto = new TestEvent('submit'); form.dispatchEvent(missingEditPhoto);
assert.equal(missingEditPhoto.defaultPrevented, true); assert.equal(busy, false);
fields[2].files = [{}]; fields[2].listeners.change();
assert.equal(form.checkValidity(), true);
console.log('PASS: edit accepts saved photos and blocks a missing photo until uploaded.');

fields[2].files = []; fields[2].dataset.existingPhoto = '1';
fields[4].value = 'SN-NEW'; fields[4].listeners.input();
assert.equal(form.checkValidity(), false);
fields[3].value = 'unreadable'; fields[3].listeners.change();
assert.equal(form.checkValidity(), true);
assert.equal(fields[2].disabled, true);
assert.equal(formElements['#itemSerialPhotoField'].hidden, true);
fields[3].value = 'known'; fields[3].listeners.change();
assert.equal(form.checkValidity(), false);
