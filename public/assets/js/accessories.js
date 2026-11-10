(() => {
  'use strict';
  function setupRequiredInput(input, signal) {
    const syncValidity = () => input.setCustomValidity(input.required && !input.checked ? input.dataset.requiredMessage : '');
    input.addEventListener('input', syncValidity);
    input.addEventListener('change', syncValidity);
    input.addEventListener('invalid', syncValidity);
    window.addEventListener('pageshow', syncValidity, {signal});
    input.form?.addEventListener('reset', () => queueMicrotask(syncValidity), {signal});
    syncValidity();
  }
  function setupEditor(editor, signal) {
    const rows = editor.querySelector('[data-accessory-rows]');
    const add = editor.querySelector('[data-accessory-add]');
    const count = editor.querySelector('[data-accessory-count]');
    const undoPanel = editor.querySelector('[data-accessory-undo-panel]');
    const undoButton = editor.querySelector('[data-accessory-undo]');
    const name = editor.querySelector('[data-accessory-name]');
    const quantity = editor.querySelector('[data-accessory-quantity]');
    const required = editor.querySelector('[data-accessory-required]');
    const cancel = editor.querySelector('[data-accessory-cancel]');
    const message = editor.querySelector('[data-accessory-message]');
    const composer = editor.querySelector('[data-accessory-composer]');
    const open = editor.querySelector('[data-accessory-open]');
    const heading = editor.querySelector('[data-accessory-composer-heading]');
    const error = editor.querySelector('[data-accessory-error]');
    let removed = null;
    let editing = null;
    let index = rows.children.length;
    const field = (row, key) => row.querySelector(`input[name$="[${key}]"]`);
    const normalized = value => value.trim().replace(/\s+/g, ' ').toLocaleLowerCase('id-ID');
    const announce = text => { message.textContent = text; };
    const setOpen = value => {
      composer.hidden = !value;
      open.hidden = value;
      open.setAttribute('aria-expanded', String(value));
    };
    const clearError = () => {
      error.textContent = ''; error.hidden = true;
      [name, quantity].forEach(input => {
        input.setCustomValidity(''); input.removeAttribute('aria-invalid'); input.removeAttribute('aria-describedby');
      });
    };
    const sync = () => {
      add.disabled = rows.children.length >= 30 && !editing;
      count.hidden = rows.children.length === 0;
      const countValue = rows.children.length + ' kelengkapan';
      if (count.textContent !== countValue) count.textContent = countValue;
      undoButton.disabled = !removed || rows.children.length >= 30;
      rows.querySelectorAll('[data-accessory-row]').forEach((row, position) => {
        const label = field(row, 'name').value.trim();
        row.querySelector('[data-accessory-remove]').setAttribute('aria-label', 'Hapus kelengkapan ' + (label || position + 1));
        row.querySelector('[data-accessory-edit]').setAttribute('aria-label', 'Ubah kelengkapan ' + (label || position + 1));
      });
    };
    const reset = () => {
      editing?.classList.remove('is-editing');
      editing = null;
      name.value = ''; quantity.value = '1';
      if (required) required.checked = false;
      clearError();
      add.textContent = 'Masukkan ke daftar'; heading.textContent = 'Tambah kelengkapan';
      composer.classList.remove('is-editing');
      sync();
    };
    const dirty = () => editing || name.value.trim() !== '' || quantity.value !== '1' || required?.checked;
    const validate = () => {
      name.setCustomValidity(''); quantity.setCustomValidity('');
      if (!name.value.trim()) name.setCustomValidity('Isi nama kelengkapan terlebih dahulu.');
      else if (name.value.trim().length > 80) name.setCustomValidity('Nama kelengkapan maksimal 80 karakter.');
      else if ([...rows.children].some(row => row !== editing && normalized(field(row, 'name').value) === normalized(name.value))) {
        name.setCustomValidity('Kelengkapan ini sudah ada. Gunakan Ubah untuk menyesuaikan jumlahnya.');
      }
      if (!/^\d+$/.test(quantity.value) || Number(quantity.value) < 1 || Number(quantity.value) > 99) {
        quantity.setCustomValidity('Isi jumlah 1 sampai 99.');
      }
      const invalid = [name, quantity].find(input => !input.checkValidity());
      if (invalid) {
        error.textContent = invalid.validationMessage; error.hidden = false;
        invalid.setAttribute('aria-invalid', 'true'); invalid.setAttribute('aria-describedby', error.id);
        invalid.focus(); return false;
      }
      if (!editing && rows.children.length >= 30) { announce('Daftar sudah berisi 30 kelengkapan. Ubah atau hapus yang sudah ada.'); return false; }
      return true;
    };
    const commit = (focus = true) => {
      if (!validate()) return false;
      let row = editing;
      if (!row) {
        const content = editor.querySelector('template').content.cloneNode(true);
        content.querySelectorAll('[name]').forEach(input => input.name = input.name.replaceAll('__INDEX__', String(index)));
        index++;
        row = content.querySelector('[data-accessory-row]');
        rows.appendChild(content);
      }
      const label = name.value.trim();
      field(row, 'name').value = label;
      field(row, 'quantity').value = String(Number(quantity.value));
      if (required) {
        field(row, 'is_required').value = required.checked ? '1' : '0';
        row.querySelector('[data-accessory-label-kind]').textContent = required.checked ? 'Wajib menyertai unit' : 'Opsional disertakan';
      }
      row.querySelector('[data-accessory-label]').textContent = label;
      row.querySelector('[data-accessory-label-quantity]').textContent = 'Jumlah ' + Number(quantity.value);
      const wasEditing = Boolean(editing);
      removed = null; undoPanel.hidden = true;
      reset();
      setOpen(false);
      announce(label + (wasEditing ? ' diperbarui.' : ' ditambahkan.'));
      if (focus) open.focus();
      editor.closest('form')?.dispatchEvent(new Event('item-form-refresh'));
      return true;
    };
    add.addEventListener('click', () => commit());
    open.addEventListener('click', () => { reset(); setOpen(true); announce(''); name.focus(); });
    cancel.addEventListener('click', () => { reset(); setOpen(false); announce(''); open.focus(); });
    [name, quantity, required].filter(Boolean).forEach(input => {
      input.addEventListener('input', () => {
        clearError();
        announce('');
      });
      input.addEventListener('keydown', event => {
        if (event.key === 'Enter') { event.preventDefault(); commit(); }
        if (event.key === 'Escape') { reset(); setOpen(false); announce(''); open.focus(); }
      });
    });
    rows.addEventListener('click', event => {
      const edit = event.target.closest('[data-accessory-edit]');
      if (edit) {
        const row = edit.closest('[data-accessory-row]');
        if (row === editing) { name.focus(); return; }
        if (dirty()) {
          error.textContent = 'Masukkan isian ini ke daftar atau pilih Batal sebelum mengubah kelengkapan lain.';
          error.hidden = false; name.focus(); return;
        }
        clearError(); setOpen(true);
        editing = row; row.classList.add('is-editing'); composer.classList.add('is-editing');
        name.value = field(row, 'name').value; quantity.value = field(row, 'quantity').value;
        if (required) required.checked = field(row, 'is_required').value === '1';
        add.textContent = 'Terapkan perubahan'; heading.textContent = 'Ubah kelengkapan';
        announce('Mengubah ' + name.value + '.'); name.focus(); sync();
        return;
      }
      const button = event.target.closest('[data-accessory-remove]');
      if (!button) return;
      const row = button.closest('[data-accessory-row]');
      if (row === editing) { reset(); setOpen(false); announce(''); }
      removed = {row, next: row.nextElementSibling};
      row.remove();
      editor.querySelector('[data-accessory-undo-message]').textContent = field(row, 'name').value + ' dihapus.';
      undoPanel.hidden = false;
      const nextControl = (removed.next || rows.lastElementChild)?.querySelector('[data-accessory-edit]');
      (nextControl || (composer.hidden ? open : name)).focus();
      sync();
    });
    undoButton.addEventListener('click', () => {
      if (!removed || rows.children.length >= 30) return;
      rows.insertBefore(removed.row, removed.next?.parentNode === rows ? removed.next : null);
      removed.row.querySelector('[data-accessory-edit]').focus();
      removed = null;
      undoPanel.hidden = true;
      sync();
    });
    editor.closest('form')?.addEventListener('submit', event => {
      // Capture before the parent form locks its submit button; include a valid unfinished entry.
      if (dirty() && !commit(false)) { setOpen(true); event.preventDefault(); event.stopImmediatePropagation(); }
    }, {capture: true, signal});
    sync();
  }
  function setupAccessories(root, signal) {
    const included = element => root !== document || !element.closest('[data-group-unit]');
    root.querySelectorAll('input[type="checkbox"][data-required-message]').forEach(input => {
      if (included(input)) setupRequiredInput(input, signal);
    });
    root.querySelectorAll('[data-accessory-editor]').forEach(editor => {
      if (included(editor)) setupEditor(editor, signal);
    });
  }
  window.setupGroupAccessories = root => {
    const events = new AbortController();
    setupAccessories(root, events.signal);
    return () => events.abort();
  };
  setupAccessories(document);
  const checklist = document.querySelector('[data-accessory-return]');
  if (!checklist) return;
  const quantities = [...checklist.querySelectorAll('[data-return-quantity]')];
  const syncReturn = () => {
    let total = 0, returned = 0, blocking = false;
    quantities.forEach(input => {
      const expected = Number(input.dataset.total);
      const value = input.type === 'checkbox' ? (input.checked ? 1 : 0) : Math.max(0, Math.min(expected, Number(input.value) || 0));
      total += expected;
      returned += value;
      if (value < expected && input.dataset.required === '1') blocking = true;
    });
    const incomplete = returned < total;
    const result = checklist.querySelector('[data-completeness-result]');
    result.value = incomplete ? 'tidak_lengkap' : 'lengkap';
    result.dispatchEvent(new Event('change', {bubbles: true}));
    checklist.querySelector('[data-accessory-result]').textContent = total === 0 ? 'Tidak ada kelengkapan pendamping.'
      : returned + ' dari ' + total + ' kembali. ' + (incomplete ? (blocking ? 'Unit ditahan sampai kelengkapan wajib selesai.' : 'Kekurangan opsional dicatat untuk ditindaklanjuti.') : 'Seluruh kelengkapan sudah kembali.');
  };
  quantities.forEach(input => input.addEventListener('input', syncReturn));
  syncReturn();
})();
