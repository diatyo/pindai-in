(() => {
  'use strict';
  const panel = document.getElementById('loanGroupScanner');
  if (!panel) return;
  const form = panel.closest('form');
  const units = document.getElementById('loanGroupUnits');
  const count = document.getElementById('loanGroupCount');
  const message = document.getElementById('loanGroupMessage');
  const video = document.getElementById('scannerVideo');
  const manual = document.getElementById('manualCode');
  const save = document.getElementById('saveLoanGroup');
  const scanDialog = document.getElementById('groupScanDialog');
  const finishScan = document.getElementById('finishGroupScan');
  const manualEntry = document.getElementById('groupManualEntry');
  const confirmation = form.elements.accessory_confirmed;
  const maximum = Number(panel.dataset.maxUnits);
  const pending = new Set();
  const cleanups = new WeakMap();
  let queue = Promise.resolve();

  function announce(text, tone = 'neutral', title = '') {
    message.setAttribute('aria-live', scanDialog.open ? 'off' : 'polite');
    message.textContent = text;
    message.dataset.tone = tone;
    video.dispatchEvent(new CustomEvent('pindai:group-feedback', {detail: {text, tone, title}}));
  }

  function updateUnit(row) {
    const label = row.querySelector('[data-group-required]');
    if (!label) return;
    const required = Array.from(row.querySelectorAll('input[type=checkbox][required]'));
    label.textContent = required.length ? required.filter(input => input.checked).length + '/' + required.length + ' wajib diperiksa' : 'Cek barang yang ikut';
  }

  function initUnit(row) {
    cleanups.set(row, window.setupGroupAccessories(row));
    updateUnit(row);
  }

  function updateList() {
    count.textContent = units.children.length + ' unit';
    document.getElementById('groupScanTotal').textContent = count.textContent;
    finishScan.textContent = 'Selesai scan · ' + count.textContent;
    finishScan.disabled = pending.size > 0;
    const recent = Array.from(units.children).slice(-3).reverse().map(row => {
      const entry = document.createElement('li');
      entry.textContent = row.querySelector('.loan-group-unit-head b').textContent;
      return entry;
    });
    if (!recent.length) {
      const empty = document.createElement('li');
      empty.textContent = 'Belum ada unit. Arahkan kamera ke QR barang.';
      recent.push(empty);
    }
    document.getElementById('groupScanRecent').replaceChildren(...recent);
    save.disabled = units.children.length < 2 || pending.size > 0 || units.querySelector('[data-blocked]') !== null;
    save.classList.toggle('is-disabled', save.disabled);
  }

  function resetConfirmation() {
    confirmation.checked = false;
    confirmation.dispatchEvent(new Event('change'));
  }

  async function addUnit(value) {
    if (units.children.length >= maximum) {
      announce('Maksimal ' + maximum + ' unit per kelompok.', 'red', 'Daftar unit penuh');
      return;
    }
    const lookup = new URL(panel.dataset.lookupUrl);
    lookup.searchParams.set('code', value);
    announce('Mencocokkan QR dengan data inventaris…', 'amber', 'Memeriksa unit');
    try {
      const response = await fetch(lookup, {cache: 'no-store', headers: {'X-Requested-With': 'XMLHttpRequest'}});
      const result = await response.json();
      if (!response.ok) {
        announce(result.message || 'Kode unit gagal diperiksa.', 'red', 'Unit belum ditambahkan');
        return;
      }
      if (Array.from(units.children).some(row => row.dataset.groupUnit === String(result.id))) {
        announce(result.name + ' sudah ada di daftar. Total tetap ' + units.children.length + ' unit. Scan QR unit lainnya.', 'amber', 'Unit sudah terdaftar');
        return;
      }
      units.insertAdjacentHTML('beforeend', result.html);
      initUnit(units.lastElementChild);
      resetConfirmation();
      if (manual.value.trim() === value) manual.value = '';
      announce(result.name + ' ditambahkan. Total ' + units.children.length + ' unit dalam kelompok. Scan QR unit berikutnya.', 'green', '✓ Unit berhasil ditambahkan');
      try { navigator.vibrate?.(80); } catch (error) { /* Feedback remains visible when vibration is unavailable. */ }
    } catch (error) {
      announce('Kode unit gagal diperiksa. Coba lagi.', 'red', 'Unit belum ditambahkan');
    }
  }

  video.addEventListener('pindai:group-code', ({detail: code}) => {
    if (!code) {
      announce('Masukkan kode unit atau gunakan kamera.', 'red', 'Kode unit belum diisi');
      manual.focus();
      return;
    }
    const key = code.toUpperCase();
    if (pending.has(key) || pending.size >= maximum) return;
    pending.add(key);
    updateList();
    queue = queue.then(() => addUnit(code)).finally(() => {
      pending.delete(key);
      updateList();
    });
  });

  units.addEventListener('click', event => {
    const button = event.target.closest('[data-remove-unit]');
    if (!button) return;
    const row = button.closest('[data-group-unit]');
    cleanups.get(row)?.();
    const next = (row.nextElementSibling || row.previousElementSibling)?.querySelector('[data-remove-unit]');
    row.remove();
    (next || document.getElementById('openGroupScan')).focus();
    resetConfirmation();
    updateList();
    announce('Unit dihapus dari daftar.');
  });
  units.addEventListener('change', event => {
    if (event.target.matches('input[type=checkbox]')) {
      updateUnit(event.target.closest('[data-group-unit]'));
      resetConfirmation();
    }
  });
  units.addEventListener('invalid', event => {
    const details = event.target.closest('details');
    if (details) details.open = true;
  }, true);

  form.addEventListener('formdata', ({formData}) => {
    // One JSON field keeps large groups within the PHP form-input limit.
    const checkout = Object.create(null);
    for (const [name, value] of Array.from(formData.entries())) {
      const field = /^units\[(\d+)]\[(\w+)](?:\[(\d+)])?(?:\[(\w+)])?$/.exec(name);
      if (!field) continue;
      formData.delete(name);
      const [, id, fieldName, index, property] = field;
      const unit = (checkout[id] ??= {});
      if (property) ((unit[fieldName] ??= {})[index] ??= {})[property] = value;
      else if (index) (unit[fieldName] ??= {})[index] = value;
      else unit[fieldName] = value;
    }
    formData.set('group_units', JSON.stringify(checkout));
  });

  document.getElementById('openGroupScan').addEventListener('click', () => {
    manualEntry.open = false;
    scanDialog.showModal();
    video.dispatchEvent(new Event('pindai:group-open'));
  });
  document.getElementById('openGroupManual').addEventListener('click', () => {
    scanDialog.showModal();
    manualEntry.open = true;
    manual.focus();
  });
  finishScan.addEventListener('click', () => scanDialog.close());
  scanDialog.addEventListener('cancel', event => {
    if (pending.size) event.preventDefault();
  });
  scanDialog.addEventListener('close', () => {
    message.setAttribute('aria-live', 'polite');
    document.getElementById('loanGroupListTitle').scrollIntoView({block: 'start'});
    document.getElementById('openGroupScan').focus({preventScroll: true});
  });
  Array.from(units.children).forEach(initUnit);
  updateList();
})();
