(() => {
  'use strict';
  const form = document.getElementById('itemForm');
  const config = document.getElementById('serialCheckConfig');
  if (!form || !config) return;
  const serial = document.getElementById('item_serial_number');
  const status = document.getElementById('item_serial_status');
  const confirmed = document.getElementById('serialDuplicateConfirmed');
  const button = form.querySelector('button[type=submit]');
  let checking = false;
  let approved = false;

  form.addEventListener('submit', async event => {
    if (approved) {
      approved = false;
      return;
    }
    if (event.defaultPrevented || status.value !== 'known' || !form.checkValidity()
        || form.querySelector('input[data-processing="true"]')) return;
    event.preventDefault();
    if (checking) return;
    checking = true;
    button.disabled = true;
    const value = serial.value;
    confirmed.value = '';
    try {
      const url = new URL(config.dataset.url);
      url.searchParams.set('serial', value);
      url.searchParams.set('exclude', config.dataset.itemId);
      const response = await fetch(url, {cache: 'no-store', headers: {'X-Requested-With': 'XMLHttpRequest'}});
      const result = await response.json();
      if (!response.ok || !Array.isArray(result.matches)) throw new Error('Serial check failed');
      if (serial.value !== value || status.value !== 'known') {
        window.alert('Nomor seri berubah. Periksa isian lalu tekan Simpan kembali.');
        return;
      }
      if (result.matches.length) {
        const matches = result.matches.map(item => item.item_name + ' (' + item.item_code + ')').join('\n');
        if (!window.confirm('Nomor seri "' + value + '" sudah digunakan oleh barang berikut.\n\n' + matches
          + '\n\nPastikan nomor pada foto sudah sesuai. Tetap simpan barang dengan nomor seri yang sama?')) return;
        confirmed.value = value;
      }
      approved = true;
    } catch (error) {
      window.alert('Nomor seri belum dapat diperiksa. Coba simpan kembali setelah koneksi tersedia.');
    } finally {
      checking = false;
      button.disabled = false;
    }
    if (approved) form.requestSubmit();
  }, true);
})();
