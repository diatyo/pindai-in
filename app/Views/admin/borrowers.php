<?php
$type = $type ?? 'teacher';
$q = $q ?? '';
$class = $class ?? '';
$archive = (bool) ($archive ?? false);
$errors = session()->getFlashdata('errors') ?? [];
$reopenCreate = (bool) session()->getFlashdata('borrower_create_error');
$label = $type === 'teacher' ? 'Guru' : 'Siswa';
$idLabel = $type === 'teacher' ? 'NIP' : 'NISN';
$classOptions = [];
foreach (['VII' => 10, 'VIII' => 10, 'IX' => 9] as $grade => $count) {
    for ($number = 1; $number <= $count; $number++) {
        $classOptions[] = $grade . '.' . $number;
    }
}
$basePath = $type === 'teacher' ? '/admin/teachers' : '/admin/students';
$viewUrl = static fn (bool $isArchive): string => base_url($basePath . '?view=' . ($isArchive ? 'archive' : 'active'));
?>
<?= view('layouts/header', ['title' => ($archive ? 'Arsip ' : 'Data ') . $label, 'subtitle' => $archive ? 'Data nonaktif tetap tersimpan; akun login tidak berubah.' : 'Data ' . $label . ' yang dapat dipilih sebagai peminjam.']) ?>
<div class="page" id="<?= $type === 'teacher' ? 'teachers' : 'students' ?>">
  <div class="page-title responsive-page-title">
    <div>
      <h1><?= $archive ? 'Arsip ' : 'Data ' ?><?= $label ?></h1>
      <span class="muted"><?= $archive ? 'Data nonaktif tetap tersimpan; akun login tidak berubah.' : 'Data ' . $label . ' yang dapat dipilih sebagai peminjam.' ?></span>
    </div>
  </div>

  <form class="card mt-18 master-filter" method="get" action="<?= base_url($basePath) ?>">
    <input type="hidden" name="view" value="<?= $archive ? 'archive' : 'active' ?>">
    <div class="master-filter-grid">
      <div class="field">
        <label for="masterKeyword">Cari <?= $label ?></label>
        <div class="search-control">
          <span class="search-control-icon" aria-hidden="true"><?= ui_icon('search', 'search-svg') ?></span>
          <input class="control" id="masterKeyword" name="q" type="search" enterkeyhint="search" value="<?= esc($q) ?>" placeholder="Nama atau <?= $idLabel ?>">
        </div>
      </div>
      <?php if ($type === 'student'): ?>
      <div class="field">
        <label for="masterClass">Kelas</label>
        <select class="control" id="masterClass" name="class">
          <option value="">Semua kelas</option>
          <?php foreach ($classOptions as $option): ?>
          <option value="<?= $option ?>" <?= $class === $option ? 'selected' : '' ?>><?= $option ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php endif; ?>
      <div class="master-filter-actions">
        <button class="btn gold btn-icon" type="submit"><?= ui_icon('search', 'btn-svg') ?> Cari</button>
        <?php if ($q !== '' || $class !== ''): ?><a class="btn" href="<?= $viewUrl($archive) ?>">Reset</a><?php endif; ?>
      </div>
    </div>
  </form>

  <div class="table-card mt-14">
    <div class="toolbar master-toolbar">
      <div class="tabs">
        <a class="tab <?= ! $archive ? 'active' : '' ?>" href="<?= $viewUrl(false) ?>">Data Aktif</a>
        <a class="tab <?= $archive ? 'active' : '' ?>" href="<?= $viewUrl(true) ?>">Arsip</a>
      </div>
      <div class="toolbar-actions master-actions">
        <span class="badge <?= $archive ? 'red' : 'amber' ?>"><?= $total ?> <?= strtolower($label) ?><?= $archive ? ' diarsipkan' : '' ?></span>
        <?php if (! $archive): ?>
        <button class="btn" type="button" onclick="modal('importModal', true)">Impor Excel</button>
        <button class="btn gold" type="button" onclick="modal('masterModal', true)">Tambah <?= $label ?></button>
        <?php endif; ?>
      </div>
    </div>

    <div class="mobile-list master-mobile-list">
      <?php foreach ($rows as $row): ?>
      <article class="mobile-record master-mobile-record">
        <div class="mobile-record-head">
          <div>
            <small class="muted"><?= $idLabel ?> <?= esc($row->identity_number) ?></small>
            <h3><?= esc($row->name) ?></h3>
          </div>
          <?php if ($archive): ?><span class="badge red">Diarsipkan</span><?php endif; ?>
        </div>
        <div class="mobile-record-meta">
          <div><small>Jenis Kelamin</small><b><?= esc($row->gender ?? '-') ?></b></div>
          <div><small><?= $type === 'teacher' ? 'No. HP' : 'Kelas' ?></small><b><?= esc(($type === 'teacher' ? $row->phone : $row->class_name) ?? '-') ?></b></div>
        </div>
        <div class="mobile-record-foot master-mobile-actions">
          <?php if ($archive): ?>
          <button class="user-action" type="button" onclick="confirmAction('Aktifkan kembali data?', '<?= esc($row->name) ?> dapat dipilih kembali sebagai peminjam. Status akun login tidak akan berubah.', '<?= base_url('/admin/borrowers/' . $row->id . '/restore') ?>', 'Aktifkan kembali')">Aktifkan kembali</button>
          <?php else: ?>
          <button class="user-action" type="button" onclick="openEdit(<?= htmlspecialchars(json_encode($row), ENT_QUOTES) ?>)">Ubah</button>
          <button class="user-action danger" type="button" onclick="confirmAction('Hapus data?', 'Data dengan riwayat transaksi akan dipindahkan ke Arsip, bukan dihapus permanen.', '<?= base_url('/admin/borrowers/' . $row->id . '/delete') ?>', 'Hapus')">Hapus</button>
          <?php endif; ?>
        </div>
      </article>
      <?php endforeach; ?>
      <?php if (! $rows): ?><?= view('partials/empty', ['text' => $archive ? 'Arsip kosong.' : 'Tidak ada data aktif.', 'icon' => 'users']) ?><?php endif; ?>
    </div>

    <div class="table-wrap">
      <table class="table">
        <thead><tr><th><?= $idLabel ?></th><th>Nama</th><th>Jenis Kelamin</th><?php if ($type === 'teacher'): ?><th>No. HP</th><?php else: ?><th>Kelas</th><?php endif; ?><th>Aksi</th></tr></thead>
        <tbody>
          <?php foreach ($rows as $row): ?>
          <tr>
            <td><?= esc($row->identity_number) ?></td>
            <td><b><?= esc($row->name) ?></b><?= $archive ? ' <span class="badge red">Diarsipkan</span>' : '' ?></td>
            <td><?= esc($row->gender ?? '-') ?></td>
            <?php if ($type === 'teacher'): ?><td><?= esc($row->phone ?? '-') ?></td><?php else: ?><td><?= esc($row->class_name ?? '-') ?></td><?php endif; ?>
            <td><div class="user-row-actions">
              <?php if ($archive): ?>
          <button class="row-btn row-btn-primary" type="button" onclick="confirmAction('Aktifkan kembali data?', '<?= esc($row->name) ?> dapat dipilih kembali sebagai peminjam. Status akun login tidak akan berubah.', '<?= base_url('/admin/borrowers/' . $row->id . '/restore') ?>', 'Aktifkan kembali')"><?= ui_icon('check', 'row-btn-icon') ?><span>Aktifkan kembali</span></button>
              <?php else: ?>
          <button class="row-btn" type="button" onclick="openEdit(<?= htmlspecialchars(json_encode($row), ENT_QUOTES) ?>)"><?= ui_icon('profile', 'row-btn-icon') ?><span>Ubah</span></button>
          <button class="row-btn row-btn-danger" type="button" onclick="confirmAction('Hapus data?', 'Data dengan riwayat transaksi akan dipindahkan ke Arsip, bukan dihapus permanen.', '<?= base_url('/admin/borrowers/' . $row->id . '/delete') ?>', 'Hapus')"><?= ui_icon('warning', 'row-btn-icon') ?><span>Hapus</span></button>
              <?php endif; ?>
            </div></td>
          </tr>
          <?php endforeach; ?>
          <?php if (! $rows): ?><tr><td colspan="5" class="muted"><?= $archive ? 'Arsip kosong.' : 'Tidak ada data aktif.' ?></td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
    <?= view('partials/pagination') ?>
  </div>
</div>

<?php if (! $archive): ?>
<div class="modal" id="masterModal">
  <div class="modal-box">
    <span class="eyebrow">Data master</span><h2>Tambah <?= $label ?></h2>
  <?= view('partials/notification', ['type' => 'error', 'title' => 'Periksa kembali isian', 'messages' => $errors]) ?>
    <form method="post" action="<?= base_url('/admin/borrowers/store') ?>">
      <?= csrf_field() ?><input type="hidden" name="type" value="<?= $type ?>">
      <div class="field"><label for="create_borrower_identity_number"><?= $idLabel ?> <span aria-hidden="true">*</span></label><input class="control" id="create_borrower_identity_number" inputmode="numeric" pattern="<?= $type === 'teacher' ? '[0-9]{18}' : '[0-9]{10}' ?>" data-identity-label="<?= $idLabel ?>" title="<?= $idLabel ?> wajib tepat <?= $type === 'teacher' ? 18 : 10 ?> digit angka" oninput="validateTeacherNumber(this)" name="identity_number" value="<?= esc(old_value('identity_number')) ?>" required></div>
      <div class="field"><label for="create_borrower_name">Nama lengkap<?php if ($type === 'teacher'): ?> <span aria-hidden="true">*</span><?php endif; ?></label><input class="control" id="create_borrower_name" name="name" value="<?= esc(old_value('name')) ?>" required></div>
      <div class="field"><label for="create_borrower_gender">Jenis kelamin<?php if ($type === 'teacher'): ?> <span aria-hidden="true">*</span><?php endif; ?></label><select class="control" id="create_borrower_gender" name="gender" <?= $type === 'teacher' ? 'required' : '' ?>><option value="Laki-laki">Laki-laki</option><option value="Perempuan">Perempuan</option></select></div>
      <?php if ($type === 'teacher'): ?>
      <div class="field"><label for="create_borrower_phone">No. HP<?php if ($type === 'teacher'): ?> <span aria-hidden="true">*</span><?php endif; ?></label><input class="control" id="create_borrower_phone" inputmode="numeric" pattern="[0-9]{10,}" minlength="10" title="No. HP minimal 10 digit angka" oninput="validateTeacherNumber(this)" name="phone" required value="<?= esc(old_value('phone')) ?>"></div>
      <?php else: ?>
      <div class="field"><label>Kelas</label><select class="control" name="class_name"><?php foreach ($classOptions as $option): ?><option value="<?= esc($option) ?>" <?= old_value('class_name') === $option ? 'selected' : '' ?>><?= esc($option) ?></option><?php endforeach; ?></select></div>
      <?php endif; ?>
      <div class="row"><button class="btn" type="button" data-close>Batal</button><button class="btn gold" type="submit">Simpan</button></div>
    </form>
  </div>
</div>

<div class="modal" id="editModal">
  <div class="modal-box">
    <span class="eyebrow">Data master</span><h2>Ubah <?= $label ?></h2>
    <form method="post" id="editForm">
      <?= csrf_field() ?>
      <div class="field"><label for="em_id"><?= $idLabel ?></label><input class="control" name="identity_number" id="em_id"<?php if ($type === 'teacher'): ?> inputmode="numeric" pattern="[0-9]{18}" title="NIP wajib tepat 18 digit angka" oninput="validateTeacherNumber(this)"<?php endif; ?> required></div>
      <div class="field"><label for="em_name">Nama lengkap</label><input class="control" name="name" id="em_name" required></div>
      <div class="field"><label>Jenis kelamin</label><select class="control" name="gender" id="em_gender"><option value="Laki-laki">Laki-laki</option><option value="Perempuan">Perempuan</option></select></div>
      <?php if ($type === 'teacher'): ?><div class="field"><label for="em_extra">No. HP <span aria-hidden="true">*</span></label><input class="control" name="phone" id="em_extra" required inputmode="numeric" pattern="[0-9]{10,}" minlength="10" title="No. HP minimal 10 digit angka" oninput="validateTeacherNumber(this)"></div><?php else: ?><div class="field"><label>Kelas</label><input class="control" name="class_name" id="em_extra"></div><?php endif; ?>
      <div class="field"><label><input type="checkbox" name="is_active" value="1" id="em_active" checked> Aktif</label></div>
      <div class="row"><button class="btn" type="button" data-close>Batal</button><button class="btn gold" type="submit">Simpan Perubahan</button></div>
    </form>
  </div>
</div>

<div class="modal" id="importModal">
  <div class="modal-box">
    <span class="eyebrow">Impor data</span><h2>Impor <?= $label ?> dari Excel</h2>
    <p class="muted">Kolom: <code><?= $idLabel ?>, Nama<?= $type === 'teacher' ? ', JenisKelamin, NoHP' : ', JenisKelamin, Kelas' ?></code>. Baris pertama adalah header. Unduh template agar format sesuai.</p>
    <?php if ($type === 'student'): ?><p class="muted">Gunakan template siswa. NISN wajib 10 digit angka dan disimpan sebagai teks agar nol di depan tetap ada. Nama dan kelas wajib diisi; jenis kelamin L/P atau Laki-laki/Perempuan. Baris tidak valid akan dilewati.</p><?php endif; ?>
    <p style="margin:-4px 0 14px"><a class="btn" href="<?= base_url('/admin/borrowers/template?type=' . $type) ?>"><?= ui_icon('reports', 'btn-svg') ?> Unduh template Excel</a></p>
    <form method="post" enctype="multipart/form-data" action="<?= base_url('/admin/borrowers/import') ?>">
      <?= csrf_field() ?><input type="hidden" name="type" value="<?= $type ?>">
      <div class="import-drop" id="csvDropZone" role="button" tabindex="0" aria-controls="csvFileInput" aria-describedby="csvFileHint">
  <input class="import-file-input" id="csvFileInput" type="file" name="import_file" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required>
  <span class="import-drop-icon" aria-hidden="true">&#x21E7;</span>
  <b id="csvDropTitle">Tarik dan lepas file Excel di sini</b>
  <span class="muted">atau klik untuk memilih file</span>
  <small id="csvFileHint">Hanya file Excel (.xlsx).</small>
  <span class="import-file-meta" id="csvFileMeta" aria-live="polite"></span>
</div>
      <div class="row"><button class="btn" type="button" data-close>Batal</button><button class="btn gold" type="submit">Impor Data</button></div>
    </form>
  </div>
</div>
<script>
<?php if ($reopenCreate && $errors): ?>
document.addEventListener('DOMContentLoaded', function () {
  modal('masterModal', true);
  const fieldErrors = <?= json_encode($errors, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>;
  const form = document.querySelector('#masterModal form');
  const key = Object.keys(fieldErrors)[0];
  const field = form.elements.namedItem(key);
  if (field && typeof field.setCustomValidity === 'function') {
    field.setCustomValidity(fieldErrors[key]);
    field.addEventListener('input', function clearServerError() {
      field.setCustomValidity('');
      if (typeof validateCreateTeacherField === 'function') validateCreateTeacherField(field);
      field.removeEventListener('input', clearServerError);
    });
    requestAnimationFrame(function () { field.reportValidity(); });
  }
});
<?php endif; ?>
function validateTeacherNumber(field, showBubble = true) {
  const label = field.name === 'identity_number' ? (field.dataset.identityLabel || 'NIP') : 'No. HP';
  const identityDigits = label === 'NISN' ? 10 : 18;
  let message = '';
  if (!field.value.trim()) {
    message = label + ' wajib diisi.';
  } else if (/[^0-9]/.test(field.value)) {
    message = label + ' hanya boleh berisi angka (0-9).';
  } else if (field.name === 'phone' && field.value.length < 10) {
    message = 'No. HP minimal 10 digit angka (saat ini ' + field.value.length + ' digit).';
  } else if (field.name === 'identity_number' && field.value.length !== identityDigits) {
    message = label + ' wajib tepat ' + identityDigits + ' digit angka (saat ini ' + field.value.length + ' digit).';
  }
  field.setCustomValidity(message);
  if (message && showBubble) {
    field.reportValidity();
  }
}
<?php if ($type === 'teacher'): ?>
const editTeacherForm = document.getElementById('editForm');
editTeacherForm.noValidate = true;
const editTeacherFields = Array.from(editTeacherForm.querySelectorAll('[required]'));
editTeacherFields.forEach(function (field) {
  field.addEventListener('input', function () { validateCreateTeacherField(field); });
  field.addEventListener('change', function () { validateCreateTeacherField(field); });
});
editTeacherForm.addEventListener('submit', function (event) {
  editTeacherFields.forEach(validateCreateTeacherField);
  if (!editTeacherForm.checkValidity()) {
    event.preventDefault();
    editTeacherForm.reportValidity();
  }
});
<?php endif; ?>
const createTeacherForm = document.querySelector('#masterModal form');
createTeacherForm.noValidate = true;
const createTeacherFields = Array.from(createTeacherForm.querySelectorAll('[required]'));
function validateCreateTeacherField(field) {
  const label = field.labels[0].textContent.replace('*', '').trim();
  field.setCustomValidity(field.value.trim() ? '' : label + ' wajib diisi.');
  if (['identity_number', 'phone'].includes(field.name)) {
    validateTeacherNumber(field, false);
  }
}
createTeacherFields.forEach(function (field) {
  field.addEventListener('input', function () { validateCreateTeacherField(field); });
  field.addEventListener('change', function () { validateCreateTeacherField(field); });
});
createTeacherForm.addEventListener('submit', function (event) {
  createTeacherFields.forEach(validateCreateTeacherField);
  if (!createTeacherForm.checkValidity()) {
    event.preventDefault();
    createTeacherForm.reportValidity();
  }
});
function openEdit(row) {
  document.getElementById('em_id').value = row.identity_number;
  document.getElementById('em_name').value = row.name;
  document.getElementById('em_gender').value = row.gender || 'Laki-laki';
  document.getElementById('em_extra').value = row.phone || row.class_name || '';
  document.getElementById('em_active').checked = !!row.is_active;
  document.querySelectorAll('#editForm input').forEach(function (field) { field.setCustomValidity(''); });
  document.getElementById('editForm').action = '/admin/borrowers/' + row.id + '/update';
  modal('editModal', true);
}

const csvDropZone = document.getElementById('csvDropZone');
const csvFileInput = document.getElementById('csvFileInput');
const csvDropTitle = document.getElementById('csvDropTitle');
const csvFileMeta = document.getElementById('csvFileMeta');

function formatFileSize(bytes) {
  if (bytes < 1024 * 1024) { return Math.max(1, Math.round(bytes / 1024)) + ' KB'; }
  return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
}

function setCsvFile(file) {
  if (!file) { return; }
  if (!/\.xlsx$/i.test(file.name)) {
    csvDropZone.classList.add('has-error');
    csvDropZone.classList.remove('has-file');
    csvDropTitle.textContent = 'Pilih file dengan ekstensi .xlsx';
    csvFileMeta.textContent = '';
    csvFileInput.value = '';
    return;
  }
  csvDropZone.classList.remove('has-error');
  csvDropZone.classList.add('has-file');
  csvDropTitle.textContent = file.name;
  csvFileMeta.textContent = formatFileSize(file.size) + ' · siap diimpor';
}

csvFileInput.addEventListener('change', function () { setCsvFile(this.files[0]); });
['dragenter', 'dragover'].forEach(function (eventName) {
  csvDropZone.addEventListener(eventName, function (event) { event.preventDefault(); csvDropZone.classList.add('is-dragging'); });
});
['dragleave', 'drop'].forEach(function (eventName) {
  csvDropZone.addEventListener(eventName, function (event) { event.preventDefault(); csvDropZone.classList.remove('is-dragging'); });
});
csvDropZone.addEventListener('drop', function (event) {
  const file = event.dataTransfer.files[0];
  if (!file) { return; }
  if (/\.xlsx$/i.test(file.name)) {
    csvFileInput.files = event.dataTransfer.files;
  }
  setCsvFile(file);
});
csvDropZone.addEventListener('keydown', function (event) {
  if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); csvFileInput.click(); }
});
document.getElementById('importModal').querySelector('form').addEventListener('submit', function (event) {
  if (!csvFileInput.files.length || !/\.xlsx$/i.test(csvFileInput.files[0].name)) {
    event.preventDefault();
    csvDropZone.classList.add('has-error');
    csvDropTitle.textContent = 'Pilih file Excel sebelum mengimpor';
  }
});
</script>
<?php endif; ?>
<?= view('layouts/footer') ?>
