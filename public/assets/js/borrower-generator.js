/* Bulk account creation stays in small, retryable HTTP transactions. */
(() => {
  const form = document.getElementById('borrowerGeneratorForm');
  if (!form) return;
  const dialog = document.getElementById('borrowerGeneratorModal');
  const rows = [...form.querySelectorAll('tr[data-borrower-type]')];
  const filters = [...form.querySelectorAll('[data-type-filter]')];
  const all = document.getElementById('selectAllBorrowers');
  const startAll = document.getElementById('generateAllBorrowers');
  const startSelected = document.getElementById('generateSelectedBorrowers');
  const status = document.getElementById('borrowerGeneratorStatus');
  const meter = document.getElementById('borrowerGeneratorMeter');
  const counts = document.getElementById('borrowerGeneratorCounts');
  const pause = document.getElementById('pauseBorrowerGenerator');
  const resume = document.getElementById('resumeBorrowerGenerator');
  const download = document.getElementById('downloadBorrowerAccounts');
  const finish = document.getElementById('finishBorrowerGenerator');
  const csrfName = document.querySelector('meta[name="csrf-name"]').content;
  const number = value => value.toLocaleString('id-ID');
  let queue = [], total = 0, processed = 0, created = [], skipped = [];
  let running = false, pauseRequested = false, started = false;
  const eligible = () => rows.filter(row => !row.hidden).map(row => row.querySelector('input')).filter(box => !box.disabled);
  function selection() {
    const boxes = eligible();
    const selected = boxes.filter(box => box.checked).length;
    document.getElementById('borrowerGeneratorSelection').textContent = `${number(boxes.length)} belum memiliki akun · ${number(selected)} dipilih`;
    startAll.textContent = `Generate semua (${number(boxes.length)})`;
    startAll.disabled = started || !boxes.length;
    startSelected.disabled = started || !selected;
    all.checked = !!boxes.length && selected === boxes.length;
    all.indeterminate = selected > 0 && selected < boxes.length;
  }
  filters.forEach(button => button.addEventListener('click', () => {
    if (started) return;
    filters.forEach(item => item.classList.toggle('active', item === button));
    rows.forEach(row => { row.hidden = button.dataset.typeFilter !== 'all' && row.dataset.borrowerType !== button.dataset.typeFilter; });
    selection();
  }));
  all.addEventListener('change', () => { eligible().forEach(box => { box.checked = all.checked; }); selection(); });
  rows.forEach(row => row.querySelector('input').addEventListener('change', selection));
  function updateToken(token) {
    if (!token) return;
    document.querySelector('meta[name="csrf-token"]').content = token;
    document.querySelectorAll('input[type="hidden"]').forEach(input => { if (input.name === csrfName) input.value = token; });
  }
  function progress() {
    meter.max = total;
    meter.value = processed;
    counts.textContent = `${number(processed)} / ${number(total)} diproses · ${number(created.length)} dibuat · ${number(skipped.length)} dilewati`;
    download.hidden = !created.length;
  }
  function start(boxes) {
    if (started || !boxes.length) return;
    queue = boxes.map(box => box.value);
    total = queue.length;
    started = true;
    form.querySelectorAll('input[type="checkbox"], [data-type-filter], [data-close]').forEach(control => { control.disabled = true; });
    startAll.disabled = startSelected.disabled = true;
    filters[0].closest('.tabs').hidden = true;
    all.closest('label').hidden = true;
    document.getElementById('borrowerGeneratorTable').parentElement.hidden = true;
    document.getElementById('borrowerGeneratorSelection').hidden = true;
    startAll.parentElement.hidden = true;
    document.getElementById('borrowerGeneratorProgress').hidden = false;
    dialog.querySelector('.modal-box').scrollTop = 0;
    processQueue();
  }
  async function processQueue() {
    if (running) return;
    running = true;
    pauseRequested = false;
    pause.hidden = false; pause.disabled = false; resume.hidden = true; finish.hidden = true;
    progress();
    try {
      while (queue.length && !pauseRequested) {
        status.textContent = 'Sedang membuat akun. Jangan tutup halaman ini.';
        const batch = queue.slice(0, 20);
        const body = new URLSearchParams();
        body.set(csrfName, form.querySelector('input[name="' + csrfName + '"]').value);
        batch.forEach(id => body.append('borrower_ids[]', id));
        const response = await fetch(form.dataset.batchUrl, {method: 'POST', body, credentials: 'same-origin', cache: 'no-store', headers: {'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json'}});
        if (!response.headers.get('content-type')?.includes('application/json')) throw new Error('Sesi atau koneksi terputus. Muat ulang halaman lalu generate kembali; akun yang sudah dibuat tetap aman.');
        const result = await response.json();
        updateToken(result.csrf);
        if (!response.ok) throw new Error(result.message || 'Bagian ini gagal diproses. Silakan lanjutkan.');
        created.push(...result.created);
        skipped.push(...result.skipped);
        queue.splice(0, batch.length);
        processed += batch.length;
        result.created.forEach(account => {
          const box = form.querySelector('input[value="' + account.id + '"]');
          if (box) box.checked = false;
        });
        progress();
      }
      status.textContent = queue.length ? 'Proses dijeda. Akun yang berhasil dibuat sudah tersimpan.' : 'Selesai. Semua data yang dipilih sudah diproses.';
    } catch (error) {
      status.textContent = error.message === 'Failed to fetch' ? 'Koneksi terputus. Coba lanjutkan saat koneksi kembali.' : error.message;
    } finally {
      running = false;
      pause.hidden = true;
      resume.hidden = !queue.length;
      finish.hidden = false;
      const details = document.getElementById('borrowerGeneratorSkipped');
      details.hidden = !skipped.length;
      const list = details.querySelector('ul');
      list.replaceChildren(...skipped.map(row => { const li = document.createElement('li'); li.textContent = row.name + ' — ' + row.reason; return li; }));
    }
  }
  form.addEventListener('submit', event => { event.preventDefault(); start(eligible().filter(box => box.checked)); });
  startAll.addEventListener('click', () => start(eligible()));
  pause.addEventListener('click', () => { pauseRequested = true; pause.disabled = true; status.textContent = 'Menunggu bagian yang berjalan selesai sebelum jeda…'; });
  resume.addEventListener('click', async () => {
    resume.disabled = true;
    try {
      // Refresh CSRF after a lost response: server may have committed that batch already.
      const response = await fetch(location.href, {credentials: 'same-origin', cache: 'no-store'});
      const page = new DOMParser().parseFromString(await response.text(), 'text/html');
      if (!response.ok || !page.getElementById('borrowerGeneratorForm')) throw new Error('Sesi berakhir. Masuk kembali lalu lanjutkan generate.');
      updateToken(page.querySelector('meta[name="csrf-token"]').content);
      await processQueue();
    } catch (error) { status.textContent = error.message; }
    finally { resume.disabled = false; }
  });
  download.addEventListener('click', () => {
    const cell = value => '"' + String(value).replace(/^[=+@\-\t\r]/, "'$&").replaceAll('"', '""') + '"';
    const lines = [['Nama','Jenis','Username','Kata sandi awal'], ...created.map(row => [row.name,row.type,row.username,row.username])];
    const url = URL.createObjectURL(new Blob(['\uFEFF' + lines.map(row => row.map(cell).join(';')).join('\r\n')], {type:'text/csv;charset=utf-8'}));
    const link = document.createElement('a'); link.href = url; link.download = 'akun-peminjam-baru.csv'; link.click();
    setTimeout(() => URL.revokeObjectURL(url), 1000);
  });
  dialog.addEventListener('click', event => { if (started && event.target === dialog) event.stopImmediatePropagation(); }, true);
  window.addEventListener('beforeunload', event => { if (running || queue.length) { event.preventDefault(); event.returnValue = ''; } });
  selection();
})();
