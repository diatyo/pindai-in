/* Pindai.in — utilitas umum untuk halaman server-rendered */
(function () {
  const csrfName = (document.querySelector('meta[name="csrf-name"]') || {}).content || 'csrf_test_name';
  const csrfToken = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';

  function toast(msg) {
    const t = document.getElementById('toast');
    if (!t) return;
    t.textContent = msg;
    t.classList.add('show');
    clearTimeout(window.__toast);
    window.__toast = setTimeout(() => t.classList.remove('show'), 2600);
  }

  function modal(id, open) {
    const el = document.getElementById(id);
    if (el) el.classList.toggle('open', open !== false);
  }

  function closeOverlays() {
    document.querySelectorAll('.modal.open, .sheet.open').forEach((overlay) => {
      overlay.classList.remove('open');
      if (overlay.hasAttribute('aria-hidden')) overlay.setAttribute('aria-hidden', 'true');
    });
  }

  function sheet() {
    const el = document.getElementById('moreSheet');
    if (el) el.classList.toggle('open');
  }

  function confirmAction(title, text, url, submitLabel) {
    document.getElementById('confirmTitle').textContent = title || 'Yakin melanjutkan?';
    document.getElementById('confirmText').textContent = text || '';
    const form = document.getElementById('confirmForm');
    form.action = url || '';
    const btn = document.getElementById('confirmSubmit');
    btn.textContent = submitLabel || 'Ya, lanjutkan';
    modal('confirmModal', true);
  }

  function toggleTab(btn) {
    const parent = btn.parentElement;
    parent.querySelectorAll('.tab').forEach((x) => x.classList.remove('active'));
    btn.classList.add('active');
  }

  function closeRowMenus(except) {
    document.querySelectorAll('.row-kebab.open').forEach((kebab) => {
      if (kebab === except) return;
      kebab.classList.remove('open');
      const trigger = kebab.querySelector('.row-btn-kebab');
      if (trigger) trigger.setAttribute('aria-expanded', 'false');
      const menu = kebab.__rowMenu;
      if (menu) { menu.classList.remove('is-open'); menu.style.top = ''; menu.style.left = ''; menu.style.right = ''; kebab.appendChild(menu); }
    });
  }

  function rowMenuToggle(trigger) {
    const kebab = trigger.closest('.row-kebab');
    if (!kebab) return;
    const willOpen = !kebab.classList.contains('open');
    closeRowMenus(kebab);
    const menu = kebab.__rowMenu || kebab.querySelector('.row-menu');
    if (!menu) return;
    kebab.__rowMenu = menu;
    if (!willOpen) {
      kebab.classList.remove('open');
      trigger.setAttribute('aria-expanded', 'false');
      menu.classList.remove('is-open');
      menu.style.top = ''; menu.style.left = ''; menu.style.right = '';
      kebab.appendChild(menu);
      return;
    }
    kebab.classList.add('open');
    trigger.setAttribute('aria-expanded', 'true');
    // Portal ke body agar tidak terpengaruh ancestor ber-backdrop-filter/overflow.
    document.body.appendChild(menu);
    menu.classList.add('is-open');
    menu.style.visibility = 'hidden';
    menu.style.left = '0px';
    menu.style.top = '0px';
    menu.style.right = 'auto';
    const r = trigger.getBoundingClientRect();
    const width = menu.offsetWidth || 200;
    const height = menu.offsetHeight || 0;
    let left = r.right - width;
    if (left < 8) left = 8;
    let top = r.bottom + 6;
    if (top + height > window.innerHeight - 8) top = r.top - height - 6;
    menu.style.left = left + 'px';
    menu.style.top = Math.max(8, top) + 'px';
    menu.style.visibility = '';
  }

  function fetchPost(url, data, onOk) {
    const body = new URLSearchParams(data);
    body.append(csrfName, csrfToken);
    fetch(url, { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body })
      .then((r) => r.json())
      .then((json) => onOk(json))
      .catch((e) => toast('Terjadi kesalahan jaringan: ' + e));
  }

  function selectAll(source) {
    document.querySelectorAll('.unit-check').forEach((x) => { x.checked = source.checked; });
  }

  /* ---- Pencarian peminjam (borrower picker) ---- */
  let borrowerSearchRequest;
  let loanBorrowerId;
  let loanStep;
  let loanHistoryStart;

  function openBorrowerPicker(type) {
    const picker = document.getElementById('borrowerPicker');
    if (!picker) return;
    picker.classList.add('open');
    const head = document.getElementById('pickerHeadLabel');
    if (head) head.textContent = type === 'student' ? 'Siswa' : 'Guru';
    document.getElementById('borrowerType').value = type;
    document.getElementById('borrowerSearch').value = '';
    const classFilter = document.getElementById('classFilter');
    classFilter.value = '';
    classFilter.hidden = type !== 'student';
    searchBorrowers();
  }

  function searchBorrowers() {
    const type = document.getElementById('borrowerType').value;
    const q = document.getElementById('borrowerSearch').value;
    const klass = document.getElementById('classFilter').value;
    const list = document.getElementById('borrowerList');
    const empty = document.getElementById('borrowerEmpty');
    const query = new URLSearchParams({ type, q, class: klass });
    borrowerSearchRequest?.abort();
    const request = new AbortController();
    borrowerSearchRequest = request;
    list.replaceChildren();
    empty.hidden = false;
    empty.textContent = 'Mencari peminjam…';
    fetch('/petugas/borrowers/search?' + query, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, signal: request.signal })
      .then(async (response) => {
        const json = await response.json();
        if (!response.ok) throw new Error(json.message || 'Pencarian peminjam gagal.');
        if (!Array.isArray(json)) throw new Error('Format data peminjam tidak sesuai.');
        return json;
      })
      .then((rows) => {
        if (request.signal.aborted) return;
        list.replaceChildren(...rows.map((borrower) => {
          const button = document.createElement('button');
          button.type = 'button';
          button.className = 'person-row';
          button.innerHTML = '<span class="person-avatar"></span><span><b></b><small></small></span><i aria-hidden="true">›</i>';
          button.querySelector('.person-avatar').textContent = initials(borrower.name);
          button.querySelector('b').textContent = borrower.name;
          button.querySelector('small').textContent = borrower.meta;
          button.addEventListener('click', () => selectBorrower(borrower.id, borrower.name, borrower.meta, borrower.type));
          return button;
        }));
        empty.textContent = 'Tidak ditemukan';
        empty.hidden = rows.length > 0;
        list.hidden = rows.length === 0;
      })
      .catch((error) => {
        if (request.signal.aborted) return;
        list.hidden = true;
        empty.hidden = false;
        empty.textContent = 'Pencarian gagal. Coba cari kembali.';
        toast(error.message || 'Pencarian peminjam gagal.');
      });
  }

  function selectBorrower(id, name, meta, type) {
    loanBorrowerId = id;
    document.getElementById('selectedInitial').textContent = initials(name);
    document.getElementById('selectedName').textContent = name;
    document.getElementById('selectedMeta').textContent = (type === 'student' ? 'Siswa · ' : 'Guru · ') + meta;
    advanceLoanStep(2);
  }

  function resetBorrowerSelection() {
    backToLoanStep(1);
  }

  function advanceLoanStep(step) {
    history.pushState({...history.state, loanStep: step}, '');
    showLoanStep(step);
  }

  function backToLoanStep(step) {
    if (step >= loanHistoryStart && history.state?.loanStep === loanStep) {
      history.go(step - loanStep);
      return;
    }
    loanHistoryStart = step;
    history.replaceState({...history.state, loanStep: step}, '');
    showLoanStep(step);
  }

  function showLoanStep(step) {
    const borrower = document.getElementById('loanBorrowerStep');
    const details = document.getElementById('loanDetailsStep');
    const accessories = document.getElementById('loanAccessoriesStep');
    loanStep = step;
    borrower.hidden = step !== 1;
    details.hidden = step !== 2;
    details.disabled = step === 1;
    accessories.hidden = accessories.disabled = step !== 3;
    document.getElementById('borrower_id').value = step === 1 ? '' : loanBorrowerId;
    closeOverlays();
    const activeStep = [borrower, details, accessories][step - 1];
    activeStep.querySelector('h2').focus({preventScroll: true});
    activeStep.scrollIntoView({block: 'start'});
  }

  function setupLoanForm(form) {
    loanStep = loanHistoryStart = Number(form.dataset.step);
    loanBorrowerId = document.getElementById('borrower_id').value;
    history.replaceState({...history.state, loanStep}, '');

    const fields = [...document.querySelectorAll('#loanDetailsStep [data-missing-message]')];
    for (const field of fields) {
      field.addEventListener('input', () => field.setCustomValidity(''));
    }
    function firstInvalidDetail() {
      for (const field of fields) {
        field.setCustomValidity(field.value.trim() ? '' : field.dataset.missingMessage);
      }
      return fields.find(field => !field.validity.valid);
    }
    document.getElementById('continueLoan').addEventListener('click', () => {
      const invalid = firstInvalidDetail();
      if (invalid) {
        invalid.reportValidity();
        return;
      }
      advanceLoanStep(3);
    });
    document.getElementById('changeLoanBorrower').addEventListener('click', resetBorrowerSelection);
    document.getElementById('backToLoanDetails').addEventListener('click', () => backToLoanStep(2));
    window.addEventListener('popstate', (event) => {
      const step = event.state?.loanStep;
      if (step === 3 && loanStep === 2) {
        const invalid = firstInvalidDetail();
        if (invalid) {
          history.back();
          toast(invalid.validationMessage);
          return;
        }
      }
      if (step === 1 || (loanBorrowerId && (step === 2 || step === 3))) showLoanStep(step);
    });
    form.addEventListener('submit', (event) => {
      if (loanStep !== 3 || !loanBorrowerId) event.preventDefault();
    });
  }

  function initials(name) {
    return String(name || '?').split(/\s+/).slice(0, 2).map((x) => x[0]).join('').toUpperCase();
  }

  function decodePhoto(file) {
    return new Promise((resolve, reject) => {
      const url = URL.createObjectURL(file);
      const image = new Image();
      image.onload = () => resolve({ image, url });
      image.onerror = () => { URL.revokeObjectURL(url); reject(new Error('Foto tidak dapat dibaca.')); };
      image.src = url;
    });
  }

  async function compressPhoto(file) {
    const decoded = await decodePhoto(file);
    try {
      const scale = Math.min(1, 1800 / Math.max(decoded.image.naturalWidth, decoded.image.naturalHeight));
      const canvas = document.createElement('canvas');
      canvas.width = Math.max(1, Math.round(decoded.image.naturalWidth * scale));
      canvas.height = Math.max(1, Math.round(decoded.image.naturalHeight * scale));
      canvas.getContext('2d').drawImage(decoded.image, 0, 0, canvas.width, canvas.height);
      const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/webp', .84));
      if (!blob) return file;
      const name = file.name.replace(/\.[^.]+$/, '') + '.webp';
      return new File([blob], name, { type: 'image/webp', lastModified: Date.now() });
    } finally {
      URL.revokeObjectURL(decoded.url);
    }
  }

  function renderPhotoPreview(container, file) {
    if (container.dataset.previewUrl) URL.revokeObjectURL(container.dataset.previewUrl);
    const url = URL.createObjectURL(file);
    container.dataset.previewUrl = url;
    let image = container.querySelector('[data-preview]');
    if (!image) {
      image = document.createElement('img');
      image.dataset.preview = '';
      container.prepend(image);
    }
    image.src = url;
    image.alt = 'Preview foto terpilih';
    image.style.cssText = 'width:100%;height:100%;object-fit:cover;border-radius:14px;display:block';
    container.style.position = 'relative';
    container.style.overflow = 'hidden';
    container.style.padding = '0';
    const label = container.querySelector('small') || document.createElement('small');
    if (!label.parentElement) container.append(label);
    label.textContent = `Foto siap ? ${Math.max(1, Math.round(file.size / 1024))} KB`;
    label.style.cssText = 'position:absolute;left:8px;right:8px;bottom:8px;padding:6px 8px;border-radius:9px;background:rgba(43,36,29,.78);color:#fff;font-size:11px';
  }

  function setupPhotoField(container) {
    const input = container.querySelector('input[type="file"]');
    if (!input) return;
    const existing = container.querySelector('[data-preview]');
    if (existing) {
      container.style.position = 'relative';
      container.style.overflow = 'hidden';
      container.style.padding = '0';
      const label = container.querySelector('small');
      if (label) label.style.cssText = 'position:absolute;left:8px;right:8px;bottom:8px;padding:6px 8px;border-radius:9px;background:rgba(43,36,29,.78);color:#fff;font-size:11px';
    }
    const openPicker = () => input.click();
    container.addEventListener('click', (event) => { if (event.target !== input) openPicker(); });
    container.addEventListener('keydown', (event) => {
      if (event.key === 'Enter' || event.key === ' ') {
        event.preventDefault();
        openPicker();
      }
    });
    input.addEventListener('change', async () => {
      const original = input.files && input.files[0];
      if (!original) return;
      if (original.type && !original.type.startsWith('image/')) {
        input.value = '';
        input.form?.dispatchEvent(new Event('item-form-refresh'));
        toast('File harus berupa gambar.');
        return;
      }
      const label = container.querySelector('small');
      if (label) label.textContent = 'Mengoptimalkan foto...';
      input.dataset.processing = 'true';
      input.form?.dispatchEvent(new Event('item-form-refresh'));
      try {
        const compressed = await compressPhoto(original);
        try {
          const transfer = new DataTransfer();
          transfer.items.add(compressed);
          input.files = transfer.files;
        } catch (_) {
          // Browser lama tetap mengirim file asli; server akan mengompresnya.
        }
        renderPhotoPreview(container, compressed);
      } catch (_) {
        renderPhotoPreview(container, original);
        toast('Preview tampil; foto akan dioptimalkan saat disimpan.');
      } finally {
        delete input.dataset.processing;
        input.form?.dispatchEvent(new Event('item-form-refresh'));
      }
    });
  }

  function setupItemForm(form) {
    const button = form.querySelector('button[type=submit]');
    if (!button) return;
    const originalText = button.textContent;
    const savingText = originalText.includes('Buat QR') ? 'Menyimpan unit dan membuat QR...' : 'Menyimpan perubahan...';
    form.addEventListener('submit', (event) => {
      if (event.defaultPrevented) return;
      if (!form.checkValidity()) {
        event.preventDefault();
        form.reportValidity();
        return;
      }
      if (form.querySelector('input[data-processing="true"]')) {
        event.preventDefault();
        return;
      }
      if (form.dataset.submitting) {
        event.preventDefault();
        return;
      }
      form.dataset.submitting = 'true';
      form.setAttribute('aria-busy', 'true');
      button.disabled = true;
      button.textContent = savingText;
      toast('Data sedang disimpan. Jangan tutup halaman.');
    });
    window.addEventListener('pageshow', () => {
      delete form.dataset.submitting;
      form.removeAttribute('aria-busy');
      button.disabled = false;
      button.textContent = originalText;
      form.dispatchEvent(new Event('item-form-refresh'));
    });
  }

  function openPhotoViewer(trigger) {
    const viewer = document.getElementById('photoViewer');
    const image = document.getElementById('photoViewerImage');
    const title = document.getElementById('photoViewerTitle');
    if (!viewer || !image || !trigger.dataset.photoZoom) return;
    image.src = trigger.dataset.photoZoom;
    image.alt = trigger.dataset.photoTitle || 'Preview foto diperbesar';
    if (title) title.textContent = trigger.dataset.photoTitle || 'Preview foto';
    resetPhotoViewer();
    viewer.classList.add('open');
    viewer.setAttribute('aria-hidden', 'false');
    viewer.querySelector('[data-close]')?.focus();
  }

  const photoViewerState = { scale: 1, x: 0, y: 0, dragging: false, pointerId: null, startX: 0, startY: 0, originX: 0, originY: 0 };

  function updatePhotoViewer() {
    const stage = document.getElementById('photoViewerStage');
    const image = document.getElementById('photoViewerImage');
    if (!stage || !image) return;
    const maxX = Math.max(0, (image.offsetWidth * photoViewerState.scale - stage.clientWidth) / 2);
    const maxY = Math.max(0, (image.offsetHeight * photoViewerState.scale - stage.clientHeight) / 2);
    photoViewerState.x = Math.max(-maxX, Math.min(maxX, photoViewerState.x));
    photoViewerState.y = Math.max(-maxY, Math.min(maxY, photoViewerState.y));
    image.style.transform = `translate3d(${photoViewerState.x}px, ${photoViewerState.y}px, 0) scale(${photoViewerState.scale})`;
    stage.classList.toggle('is-zoomed', photoViewerState.scale > 1);
    const level = document.getElementById('photoViewerZoom');
    if (level) level.textContent = `${Math.round(photoViewerState.scale * 100)}%`;
    const zoomOut = document.querySelector('[data-photo-action=zoom-out]');
    const zoomIn = document.querySelector('[data-photo-action=zoom-in]');
    const reset = document.querySelector('[data-photo-action=reset]');
    if (zoomOut) zoomOut.disabled = photoViewerState.scale <= 1;
    if (zoomIn) zoomIn.disabled = photoViewerState.scale >= 4;
    if (reset) reset.disabled = photoViewerState.scale === 1 && photoViewerState.x === 0 && photoViewerState.y === 0;
  }

  function setPhotoViewerZoom(scale) {
    photoViewerState.scale = Math.max(1, Math.min(4, scale));
    if (photoViewerState.scale === 1) {
      photoViewerState.x = 0;
      photoViewerState.y = 0;
    }
    updatePhotoViewer();
  }

  function resetPhotoViewer() {
    photoViewerState.scale = 1;
    photoViewerState.x = 0;
    photoViewerState.y = 0;
    photoViewerState.dragging = false;
    photoViewerState.pointerId = null;
    document.getElementById('photoViewerStage')?.classList.remove('is-dragging');
    updatePhotoViewer();
  }

  function setupPhotoViewer() {
    const stage = document.getElementById('photoViewerStage');
    const image = document.getElementById('photoViewerImage');
    if (!stage || !image) return;
    document.querySelectorAll('[data-photo-action]').forEach((button) => {
      button.addEventListener('click', () => {
        if (button.dataset.photoAction === 'zoom-in') setPhotoViewerZoom(photoViewerState.scale + .5);
        if (button.dataset.photoAction === 'zoom-out') setPhotoViewerZoom(photoViewerState.scale - .5);
        if (button.dataset.photoAction === 'reset') resetPhotoViewer();
      });
    });
    stage.addEventListener('wheel', (event) => {
      event.preventDefault();
      setPhotoViewerZoom(photoViewerState.scale + (event.deltaY < 0 ? .25 : -.25));
    }, { passive: false });
    stage.addEventListener('pointerdown', (event) => {
      if (event.target.closest('.photo-viewer-controls') || photoViewerState.scale <= 1) return;
      photoViewerState.dragging = true;
      photoViewerState.pointerId = event.pointerId;
      photoViewerState.startX = event.clientX;
      photoViewerState.startY = event.clientY;
      photoViewerState.originX = photoViewerState.x;
      photoViewerState.originY = photoViewerState.y;
      stage.classList.add('is-dragging');
      stage.setPointerCapture(event.pointerId);
    });
    stage.addEventListener('pointermove', (event) => {
      if (!photoViewerState.dragging || event.pointerId !== photoViewerState.pointerId) return;
      photoViewerState.x = photoViewerState.originX + event.clientX - photoViewerState.startX;
      photoViewerState.y = photoViewerState.originY + event.clientY - photoViewerState.startY;
      updatePhotoViewer();
    });
    const stopDragging = (event) => {
      if (event.pointerId !== photoViewerState.pointerId) return;
      photoViewerState.dragging = false;
      photoViewerState.pointerId = null;
      stage.classList.remove('is-dragging');
    };
    stage.addEventListener('pointerup', stopDragging);
    stage.addEventListener('pointercancel', stopDragging);
    stage.addEventListener('dblclick', (event) => {
      if (event.target.closest('.photo-viewer-controls')) return;
      setPhotoViewerZoom(photoViewerState.scale === 1 ? 2 : 1);
    });
    image.addEventListener('load', resetPhotoViewer);
    window.addEventListener('resize', updatePhotoViewer);
    updatePhotoViewer();
  }

  window.toast = toast;
  window.modal = modal;
  window.closeOverlays = closeOverlays;
  window.sheet = sheet;
  window.confirmAction = confirmAction;
  window.toggleTab = toggleTab;
  window.rowMenuToggle = rowMenuToggle;
  window.fetchPost = fetchPost;
  window.selectAll = selectAll;
  window.openBorrowerPicker = openBorrowerPicker;
  window.searchBorrowers = searchBorrowers;
  window.selectBorrower = selectBorrower;
  window.resetBorrowerSelection = resetBorrowerSelection;
  window.initials = initials;

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-close]').forEach((x) => x.addEventListener('click', closeOverlays));
    document.querySelectorAll('.tab').forEach((x) => x.addEventListener('click', () => toggleTab(x)));
    document.querySelectorAll('.modal, .sheet').forEach((x) => x.addEventListener('click', (e) => { if (e.target === x) closeOverlays(); }));
    const qs = document.getElementById('borrowerSearch');
    if (qs) qs.addEventListener('input', searchBorrowers);
    const cf = document.getElementById('classFilter');
    if (cf) cf.addEventListener('change', searchBorrowers);
    document.querySelectorAll('.borrower-option').forEach((x) => x.addEventListener('click', () => openBorrowerPicker(x.dataset.type)));
    const loanForm = document.getElementById('loanCreateForm');
    if (loanForm) setupLoanForm(loanForm);
    document.querySelectorAll('[data-photo]').forEach(setupPhotoField);
    setupPhotoViewer();
    document.querySelectorAll('[data-photo-zoom]').forEach((trigger) => {
      trigger.addEventListener('click', () => openPhotoViewer(trigger));
      trigger.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' || event.key === ' ') {
          event.preventDefault();
          openPhotoViewer(trigger);
        }
      });
    });
    document.querySelectorAll('form').forEach((form) => {
      if (form.method === 'post' && form.action.includes('/petugas/items/')) setupItemForm(form);
    });
    document.addEventListener('click', (event) => {
      if (!event.target.closest('.row-kebab')) closeRowMenus();
    });
    document.addEventListener('keydown', (event) => { if (event.key === 'Escape') closeRowMenus(); });
    window.addEventListener('resize', () => closeRowMenus());
    window.addEventListener('scroll', () => closeRowMenus(), true);
  });
})();
