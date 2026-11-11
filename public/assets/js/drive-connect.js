(() => {
  const backups = document.getElementById('backups');
  const updateRestore = setupRestore();
  if (backups?.dataset.driveArchives) loadDriveArchives(backups);

  function setupRestore() {
    const restore = document.getElementById('backup-restore-form');
    const confirmation = restore?.querySelector('[data-restore-confirmation]');
    if (!confirmation) return;
    const chooser = restore.querySelector('[data-restore-chooser]');
    const sources = Array.from(restore.querySelectorAll('[data-restore-source]'), panel => ({
      panel,
      input: panel.querySelector('select, input[type="file"]'),
    }));
    const confirmationInputs = confirmation.querySelectorAll('input');
    const restoreFile = document.getElementById('restoreArchive');
    const filename = document.getElementById('restore-file-name');
    restoreFile.parentElement.classList.add('is-enhanced');
    document.getElementById('restore-file-button').hidden = false;
    filename.hidden = false;
    if (chooser) chooser.hidden = false;

    function update() {
      const selected = chooser ? chooser.querySelector('input:checked')?.value : sources[0].panel.dataset.restoreSource;
      let hasArchive = false;
      for (const {panel, input} of sources) {
        const active = panel.dataset.restoreSource === selected;
        panel.hidden = !active;
        panel.disabled = !active;
        if (active) hasArchive = !input.disabled && input.value !== '';
      }
      confirmation.hidden = !hasArchive;
      confirmation.disabled = !hasArchive;
    }

    function selectionChanged() {
      for (const input of confirmationInputs) input.value = '';
      filename.textContent = restoreFile.files[0]?.name || 'Belum ada file dipilih';
      update();
    }

    chooser?.addEventListener('change', selectionChanged);
    for (const {input} of sources) input.addEventListener('change', selectionChanged);
    update();
    return update;
  }

  async function loadDriveArchives(page) {
    const history = document.getElementById('backup-history-list');
    const status = document.getElementById('backup-history-status');
    const template = document.getElementById('backup-drive-row');
    const select = document.getElementById('restoreDrive');
    try {
      const response = await fetch(page.dataset.driveArchives, { headers: { Accept: 'application/json' }, cache: 'no-store' });
      const data = await response.json();
      if (!response.ok) throw new Error(data.message || 'Daftar dari Google Drive belum dapat dimuat.');

      const listed = new Set(Array.from(history.children, row => row.dataset.driveId));
      const time = new Intl.DateTimeFormat('en-GB', {
        day: '2-digit', month: '2-digit', year: 'numeric',
        hour: '2-digit', minute: '2-digit', hourCycle: 'h23', timeZone: page.dataset.timezone,
      });
      const number = new Intl.NumberFormat('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
      select.replaceChildren(new Option(data.files.length ? 'Pilih arsip dari Drive' : 'Belum ada arsip di Google Drive', ''));
      for (const file of data.files) {
        select.append(new Option(file.name, file.id));
        if (listed.has(file.id)) continue;
        listed.add(file.id);
        const row = template.content.firstElementChild.cloneNode(true);
        row.dataset.driveId = file.id;
        row.dataset.backupTime = file.time;
        row.querySelector('[data-archive-name]').textContent = file.name;
        const size = number.format(file.bytes / 1048576) + ' MB';
        row.querySelector('[data-archive-meta]').textContent = file.time ? time.format(file.time * 1000).replace(',', '') + ' · ' + size : size;
        row.querySelector('a').href = 'https://drive.google.com/open?id=' + encodeURIComponent(file.id);
        const older = Array.from(history.children).find(item => Number(item.dataset.backupTime) < file.time);
        history.insertBefore(row, older || null);
      }
      select.disabled = data.files.length === 0;
      if (data.files.some(file => file.id === select.dataset.selectedArchive)) {
        select.value = select.dataset.selectedArchive;
      }
      updateRestore?.();
      document.getElementById('backup-history-empty').hidden = history.children.length > 0;
      status.hidden = !data.hasMore;
      if (data.hasMore) status.textContent = 'Menampilkan sampai 50 arsip terbaru. Arsip lama bisa diunduh dari Google Drive.';
    } catch (error) {
      status.textContent = error instanceof SyntaxError || error instanceof TypeError
        ? 'Daftar dari Google Drive belum dapat dimuat. Muat ulang halaman untuk mencoba lagi.'
        : error.message;
      select.replaceChildren(new Option('Daftar Drive belum tersedia', ''));
    }
  }

  const form = document.getElementById('drive-connect');
  if (!form) return;
  const button = form.querySelector('button[type="submit"]');
  const token = form.querySelector('input[type="hidden"]');
  const status = document.getElementById('drive-connect-status');
  const link = document.getElementById('drive-connect-link');
  const waiting = ['new', 'waiting', 'exchanging', 'retry'];
  const messages = {
    denied: 'Izin belum diberikan. Kamu bisa mencoba lagi.',
    failed: 'Google Drive belum berhasil dihubungkan. Coba lagi.',
    expired: 'Waktu persetujuan habis. Klik Hubungkan Google Drive untuk mencoba lagi.',
    reconnect: 'Google belum memberikan akses untuk backup berikutnya. Hubungkan ulang akun.',
    superseded: 'Sambungan dimulai dari tab lain. Muat ulang halaman untuk melihat status terbaru.',
  };
  let popup;
  let deadline;

  function announce(message) {
    status.hidden = false;
    status.textContent = message;
  }

  function showPopupMessage(title, message, canClose = false) {
    if (!popup || popup.closed) return;
    let page;
    try {
      page = popup.document;
    } catch {
      return;
    }
    if (!page.body) return;
    page.title = title + ' · Pindai.in';
    page.documentElement.lang = 'id';
    page.body.style.cssText = 'font: 1rem/1.5 system-ui, sans-serif; color: #30271e; background: #faf6ed; max-width: 32rem; margin: 12vh auto; padding: 1.5rem;';
    const heading = page.createElement('h1');
    heading.textContent = title;
    const description = page.createElement('p');
    description.textContent = message;
    page.body.replaceChildren(heading, description);
    if (canClose) {
      const tab = popup;
      const close = page.createElement('button');
      close.type = 'button';
      close.textContent = 'Tutup tab';
      close.style.cssText = 'font: inherit; padding: 0.65rem 1rem;';
      close.addEventListener('click', () => tab.close());
      page.body.append(close);
    }
  }

  function finish(message, startFailed = false) {
    if (startFailed) {
      showPopupMessage('Sambungan Google Drive belum bisa dimulai', message, true);
    } else {
      popup?.close();
    }
    link.hidden = true;
    button.disabled = false;
    announce(message);
  }

  async function post(url) {
    const response = await fetch(url, {
      method: 'POST',
      headers: { Accept: 'application/json' },
      body: new URLSearchParams({ [token.name]: token.value }),
      cache: 'no-store',
      redirect: 'error',
    });
    const data = await response.json();
    if (data.csrf) {
      for (const field of document.getElementsByName(token.name)) field.value = data.csrf;
    }
    return data;
  }

  async function poll() {
    if (Date.now() >= deadline) return finish(messages.expired);
    try {
      const { status: result } = await post(form.dataset.poll);
      if (result === 'connected') {
        popup?.close();
        location.reload();
        return;
      }
      if (!waiting.includes(result)) return finish(messages[result] || messages.failed);
    } catch {
      // The Admin session may have expired or the local server may be temporarily offline.
    }
    window.setTimeout(poll, 3000);
  }

  function startPolling() {
    button.disabled = true;
    deadline = Date.now() + 600000;
    window.setTimeout(poll, 3000);
  }

  form.addEventListener('submit', async event => {
    event.preventDefault();
    popup?.close();
    popup = window.open('about:blank', '_blank');
    if (popup) popup.opener = null;
    button.disabled = true;
    announce('Membuka persetujuan Google…');
    showPopupMessage('Menghubungkan Google Drive', 'Pindai.in sedang menyiapkan halaman persetujuan Google.');
    try {
      const { url, message } = await post(form.action);
      if (!url) return finish(message || messages.failed, true);
      if (popup && !popup.closed) {
        popup.location.replace(url);
      } else {
        link.href = url;
        link.hidden = false;
      }
      announce('Pilih akun dan beri izin di halaman Google. Status di sini akan diperbarui otomatis.');
      startPolling();
    } catch {
      finish('Halaman Pindai.in tidak dapat dihubungi. Muat ulang halaman lalu coba lagi.', true);
    }
  });

  if (form.dataset.waiting === '1') {
    announce('Menunggu persetujuan Google dari tab yang sudah dibuka.');
    startPolling();
  }
})();
