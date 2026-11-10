<?php
$mode = $mode ?? 'create';
$item = $item ?? null;
$errors = session()->getFlashdata('errors') ?? [];
$oldPost = session()->get('_ci_old_input')['post'] ?? session()->getFlashdata('old') ?? [];
$formValue = static fn (string $field, string $default = ''): string => is_string($oldPost[$field] ?? null) ? $oldPost[$field] : $default;
$savedSerial = $item->serial_number ?? '';
$serialStatus = $formValue('serial_status', $item->serial_status ?? ($mode === 'create' || $savedSerial !== '' ? 'known' : ''));
$showSerial = $serialStatus === 'known';
?>
<?= view('layouts/header', ['title' => $mode === 'create' ? 'Tambah Barang' : 'Ubah Barang', 'subtitle' => 'Lengkapi identitas unit dan QR Code.']) ?>
<div class="page form-desktop" id="<?= $mode === 'create' ? 'addUnit' : 'editUnit' ?>">
  <div class="page-title"><div><span class="eyebrow">Unit</span><h1><?= $mode === 'create' ? 'Tambah Barang' : 'Ubah Barang' ?></h1></div></div>
  <?php if ($mode === 'create'): ?>
  <div class="desktop-work-note">Mobile cocok untuk melengkapi foto dan serial satu unit. Gunakan desktop untuk input banyak unit dan cetak label massal.</div>
  <?php endif; ?>

  <form id="itemForm" class="card mt-18" method="post" enctype="multipart/form-data" action="<?= $mode === 'create' ? base_url('/petugas/items/store') : base_url('/petugas/items/' . $item->id . '/update') ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="serial_duplicate_confirmed" id="serialDuplicateConfirmed" value="">
    <input type="hidden" id="serialCheckConfig" data-url="<?= esc(base_url('/petugas/items/check-serial'), 'attr') ?>" data-item-id="<?= (int) ($item->id ?? 0) ?>">
<?= view('partials/notification', ['type' => 'error', 'title' => 'Periksa kembali isian', 'messages' => $errors]) ?>

    <div class="grid2">
      <div class="field">
        <label>Kode unit <b>*</b></label>
        <input class="control" name="item_code" value="<?= esc($item->item_code ?? 'Dibuat saat disimpan') ?>" readonly>
        <small class="muted"><?= $mode === 'edit' ? 'Kode unit tetap dan tidak dapat diubah.' : 'Kode dibuat otomatis dan berurutan untuk seluruh unit.' ?></small>
      </div>
      <div class="field">
        <label for="item_category">Kategori <b>*</b></label>
        <select class="control" id="item_category" name="category" required>
          <option value="">Pilih kategori</option>
          <?php foreach ($categories as $c): ?>
          <option value="<?= esc($c) ?>" <?= $formValue('category', $item->category ?? '') === $c ? 'selected' : '' ?>><?= esc($c) ?><?= ($inactive_category ?? false) && $item->category === $c ? ' (nonaktif)' : '' ?></option>
          <?php endforeach; ?>
        </select>
        <?php if ($inactive_category ?? false): ?><small class="muted">Kategori tersimpan sudah nonaktif. Dapat dipertahankan atau diganti dengan kategori aktif.</small><?php endif; ?>
      </div>
    </div>

    <div class="field"><label for="item_item_name">Nama barang <b>*</b></label><input class="control" id="item_item_name" name="item_name" required maxlength="150" value="<?= esc($formValue('item_name', $item->item_name ?? '')) ?>" placeholder="Contoh: Proyektor Epson"></div>

    <div class="grid2">
      <div class="field"><label for="item_brand">Merek <b>*</b></label><input class="control" id="item_brand" name="brand" maxlength="100" required value="<?= esc($formValue('brand', $item->brand ?? '')) ?>" placeholder="Contoh: Epson"></div>
      <div class="field"><label for="item_model">Model <b>*</b></label><input class="control" id="item_model" name="model" maxlength="100" required value="<?= esc($formValue('model', $item->model ?? '')) ?>" placeholder="Contoh: XD201"></div>
    </div>

    <div class="grid2">
      <div class="field">
        <label for="item_serial_status">Keterangan nomor seri <b>*</b></label>
        <select class="control" id="item_serial_status" name="serial_status" required>
          <option value="">Pilih keterangan nomor seri</option>
          <?php foreach (\App\Models\ItemModel::SERIAL_STATUS_LABELS as $value => $label): ?>
          <option value="<?= esc($value, 'attr') ?>" <?= $serialStatus === $value ? 'selected' : '' ?>><?= esc($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field" id="itemSerialField" <?= $showSerial ? '' : 'hidden' ?>>
        <label for="item_serial_number">Nomor seri <b>*</b></label>
        <input class="control" id="item_serial_number" name="serial_number" maxlength="100" required <?= $showSerial ? '' : 'disabled' ?> value="<?= esc($formValue('serial_number', $savedSerial), 'attr') ?>" data-saved-serial="<?= esc($savedSerial, 'attr') ?>" placeholder="Contoh: EP-XD201-011">
      </div>
    </div>
    <?php if ($savedSerial !== '' || ! empty($item->serial_photo)): ?>
    <p class="muted item-serial-note" id="itemSerialRemoval" <?= $showSerial || $serialStatus === '' ? 'hidden' : '' ?>>Saat disimpan, nomor seri dan foto nomor seri lama akan dilepas dari data barang ini.</p>
    <?php endif; ?>

    <div class="grid2">
      <div class="field"><label for="item_acquisition_date">Tanggal pengadaan <b>*</b></label><input class="control" type="date" id="item_acquisition_date" name="acquisition_date" required value="<?= esc($formValue('acquisition_date', $item->acquisition_date ?? '')) ?>"></div>
    </div>

    <div class="grid2">
      <div class="field">
        <label for="item_location">Lokasi <b>*</b></label>
        <select class="control" id="item_location" name="location" required>
          <option value="">Pilih lokasi</option>
          <?php foreach ($locations as $l): ?>
          <option value="<?= esc($l) ?>" <?= $formValue('location', $item->location ?? '') === $l ? 'selected' : '' ?>><?= esc($l) ?><?= ($inactive_location ?? false) && $item->location === $l ? ' (nonaktif)' : '' ?></option>
          <?php endforeach; ?>
        </select>
        <?php if ($inactive_location ?? false): ?><small class="muted">Lokasi tersimpan sudah nonaktif. Dapat dipertahankan atau diganti dengan lokasi aktif.</small><?php endif; ?>
      </div>
      <div class="field">
        <label for="item_condition">Kondisi awal <b>*</b></label>
        <select class="control" id="item_condition" name="condition" required>
          <option value="">Pilih kondisi awal</option>
          <?php foreach (['good' => 'Baik', 'minor_damage' => 'Rusak ringan', 'major_damage' => 'Rusak berat'] as $k => $v): ?>
          <option value="<?= $k ?>" <?= $formValue('condition', $item->condition ?? '') === $k ? 'selected' : '' ?>><?= $v ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="field">
      <label for="item_status">Status unit</label>
      <input class="control" id="item_status" readonly value="<?= esc($item ? item_status_label($item->status) : 'Ditentukan saat disimpan') ?>">
      <small class="muted">Status ditentukan otomatis dari arsip, peminjaman aktif, laporan kerusakan, dan kondisi unit.</small>
    </div>

    <div class="field"><label for="item_specifications">Spesifikasi <b>*</b></label><textarea class="control" id="item_specifications" name="specifications" required rows="3"><?= esc($formValue('specifications', $item->specifications ?? '')) ?></textarea></div>

    <section class="item-photo-section" aria-labelledby="itemPhotoHeading">
      <h2 id="itemPhotoHeading">Foto Barang</h2>
      <div class="photos item-photo-grid">
        <div class="field">
          <label for="item_physical_photo">Foto fisik <b>*</b></label>
          <div class="photo" data-photo>
            <input type="file" id="item_physical_photo" name="physical_photo" <?= empty($item->physical_photo) ? 'required' : '' ?> data-existing-photo="<?= empty($item->physical_photo) ? '0' : '1' ?>" accept="image/*" capture="environment" aria-describedby="itemPhysicalHint" class="item-photo-input">
            <?php if (! empty($item->physical_photo)): ?>
            <img data-preview src="<?= esc(base_url($item->physical_photo), 'attr') ?>" alt="Foto fisik tersimpan">
            <?php endif; ?>
            <small><?= empty($item->physical_photo) ? 'Ambil atau unggah foto' : 'Ganti foto fisik' ?></small>
          </div>
          <p class="muted item-photo-hint" id="itemPhysicalHint">Ambil foto yang memperlihatkan seluruh barang.</p>
        </div>
        <div class="field" id="itemSerialPhotoField" <?= $showSerial ? '' : 'hidden' ?>>
          <label for="item_serial_photo">Foto nomor seri <b>*</b></label>
          <div class="photo" data-photo>
            <input type="file" id="item_serial_photo" name="serial_photo" <?= empty($item->serial_photo) ? 'required' : '' ?> <?= $showSerial ? '' : 'disabled' ?> data-existing-photo="<?= empty($item->serial_photo) ? '0' : '1' ?>" accept="image/*" capture="environment" aria-describedby="itemSerialHint" class="item-photo-input">
            <?php if (! empty($item->serial_photo)): ?>
            <img data-preview src="<?= esc(base_url($item->serial_photo), 'attr') ?>" alt="Foto nomor seri tersimpan">
            <?php endif; ?>
            <small><?= empty($item->serial_photo) ? 'Ambil atau unggah foto' : 'Ganti foto nomor seri' ?></small>
          </div>
          <p class="muted item-photo-hint" id="itemSerialHint">Pastikan nomor seri terlihat jelas dan sesuai isian.</p>
          <p class="muted item-photo-hint" id="itemSerialChanged" hidden>Nomor seri berubah. Ambil atau unggah foto yang sesuai dengan nomor baru.</p>
        </div>
      </div>
    </section>

    <?= view('partials/accessory_editor', ['accessories' => $accessories ?? []]) ?>

    <div class="form-action mt-18">
      <button class="btn gold full" type="submit"><?= $mode === 'create' ? 'Simpan Unit & Buat QR' : 'Simpan Perubahan' ?></button>
    </div>
  </form>
</div>
<style>
#itemForm [data-photo]{position:relative}
#itemForm .item-photo-input{position:absolute;inset:0;width:100%;height:100%;opacity:0;z-index:2;cursor:pointer}
#itemForm [data-photo]:focus-within{outline:2px solid var(--gold);outline-offset:3px}
#itemForm [hidden]{display:none!important}
.item-photo-section{margin-block:24px 16px;padding-top:16px;border-top:1px solid var(--line)}
.item-photo-section h2{margin:0 0 16px;font-size:18px}
.item-photo-section .field{min-width:0;margin-bottom:0}
.item-photo-hint,.item-serial-note{font-size:13px;line-height:1.5}
.item-serial-note{margin:0 0 8px}
.item-photo-hint{margin:8px 0 0}
.item-photo-grid{
  --photo-slot-height:112px;
  grid-template-columns:repeat(2,minmax(0,1fr));
  gap:16px;
}
#itemForm .item-photo-grid .photo{height:var(--photo-slot-height)}
#itemForm .item-photo-grid .photo:has([data-preview]):not(.needs-new-photo){height:168px}
.item-photo-grid img{position:absolute;inset:0;width:100%;height:100%;object-fit:contain;border-radius:inherit}
.item-photo-grid .needs-new-photo [data-preview]{display:none}
.item-photo-grid .photo:not(:has([data-preview])) small{
  padding:8px 12px;
  border:1px solid var(--line);
  border-radius:8px;
  background:var(--paper);
  color:var(--brown);
  font-weight:700;
}
@media(min-width:601px){
  .item-photo-grid:has(.photo:not(.needs-new-photo) [data-preview]){--photo-slot-height:168px}
}
@media(max-width:600px){.item-photo-grid{grid-template-columns:minmax(0,1fr)}}
</style>
<script>
(() => {
  const form = document.getElementById('itemForm');
  const fields = Array.from(form.querySelectorAll('[required], input[type="file"]'));
  const button = form.querySelector('button[type=submit]');
  const serialStatus = form.querySelector('#item_serial_status');
  const serialNumber = form.querySelector('#item_serial_number');
  const serialPhoto = form.querySelector('#item_serial_photo');
  const serialField = form.querySelector('#itemSerialField');
  const serialPhotoField = form.querySelector('#itemSerialPhotoField');
  const serialChanged = form.querySelector('#itemSerialChanged');
  const serialRemoval = form.querySelector('#itemSerialRemoval');
  form.noValidate = true;
  function updateSerialFields() {
    const showSerial = serialStatus.value === 'known';
    const changed = serialNumber.value !== serialNumber.dataset.savedSerial;
    const needsNewPhoto = serialPhoto.dataset.existingPhoto === '1' && changed;
    serialField.hidden = serialPhotoField.hidden = !showSerial;
    serialNumber.disabled = serialPhoto.disabled = !showSerial;
    serialPhoto.required = showSerial && (serialPhoto.dataset.existingPhoto !== '1' || changed);
    serialPhoto.closest('[data-photo]').classList.toggle('needs-new-photo', needsNewPhoto && !serialPhoto.files.length);
    serialChanged.hidden = !showSerial || !needsNewPhoto;
    if (serialRemoval) serialRemoval.hidden = showSerial || !serialStatus.value;
  }
  function validateField(field) {
    if (field.disabled) {
      field.setCustomValidity('');
      return;
    }
    const label = field.labels[0].textContent.replace('*', '').trim();
    const isFile = field.type === 'file';
    const message = isFile ? label + ' wajib diunggah.' : (field.tagName === 'SELECT' ? label + ' wajib dipilih.' : label + ' wajib diisi.');
    const filled = isFile ? (!field.required || field.files.length > 0) : field.value.trim() !== '';
    field.setCustomValidity(filled ? '' : message);
  }
  function updateSaveButton() {
    updateSerialFields();
    fields.forEach(validateField);
    const processing = fields.some(field => field.dataset.processing === 'true');
    button.disabled = Boolean(form.dataset.submitting) || processing;
  }
  fields.forEach(function (field) {
    field.addEventListener('input', updateSaveButton);
    field.addEventListener('change', updateSaveButton);
  });
  form.addEventListener('item-form-refresh', updateSaveButton);
  form.addEventListener('submit', function (event) {
    updateSaveButton();
    if (button.disabled || !form.checkValidity()) {
      event.preventDefault();
      form.reportValidity();
    }
  });
  updateSaveButton();
})();
</script>
<?= view('layouts/footer') ?>
<script src="<?= base_url('assets/js/item-serial-confirmation.js') ?>?v=<?= filemtime(FCPATH . 'assets/js/item-serial-confirmation.js') ?>"></script>
