<?php
$q = $q ?? '';
$tab = $tab ?? 'utama';
$status = $status ?? '';
$errors = session()->getFlashdata('errors') ?? [];
$temporaryPassword = session()->getFlashdata('temporary_password');
$generatedCredentials = session()->getFlashdata('generated_credentials');
$borrowers = $borrowers ?? [];
$tabUrl = static fn (string $target): string => base_url('/admin/users?tab=' . $target . ($q !== '' ? '&q=' . urlencode($q) : '') . ($status !== '' ? '&status=' . urlencode($status) : ''));
?>
<?= view('layouts/header', ['title' => 'Akun Pengguna', 'subtitle' => 'Kelola pengguna utama dan akun peminjam.']) ?>
<div class="page" id="users">
  <div class="page-title responsive-page-title">
    <div><h1>Akun Pengguna</h1><span class="muted"><?= $tab === 'peminjam' ? 'Akun guru dan siswa dibuat dari data peminjam.' : 'Akun Admin, Pengurus Barang, dan Kepala Sekolah.' ?></span></div>
  </div>

  <form class="card mt-18 user-filter" method="get" action="<?= base_url('/admin/users') ?>">
    <input type="hidden" name="tab" value="<?= esc($tab) ?>">
    <div class="user-filter-grid">
      <div class="field">
        <label for="userKeyword">Cari akun</label>
        <div class="search-control">
          <span class="search-control-icon" aria-hidden="true"><?= ui_icon('search', 'search-svg') ?></span>
          <input class="control" id="userKeyword" name="q" type="search" enterkeyhint="search" value="<?= esc($q) ?>" placeholder="<?= $tab === 'peminjam' ? 'Nama, NIP/NISN, atau username' : 'Nama atau username' ?>">
        </div>
      </div>
      <div class="field">
        <label for="userStatus">Status</label>
        <select class="control" id="userStatus" name="status" onchange="this.form.submit()">
          <option value="">Semua status</option>
          <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Aktif</option>
          <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Nonaktif (arsip)</option>
        </select>
      </div>
      <div class="user-filter-actions">
        <button class="btn gold btn-icon" type="submit"><?= ui_icon('search', 'btn-svg') ?> Cari</button>
      <?php if ($q !== ''): ?><a class="btn" href="<?= base_url('/admin/users?tab=' . $tab . ($status !== '' ? '&status=' . urlencode($status) : '')) ?>">Reset</a><?php endif; ?>
      </div>
    </div>
  </form>

  <div class="table-card mt-14">
    <div class="toolbar user-list-toolbar">
      <div class="tabs">
        <a class="tab <?= $tab === 'utama' ? 'active' : '' ?>" href="<?= $tabUrl('utama') ?>">Pengguna Utama</a>
        <a class="tab <?= $tab === 'peminjam' ? 'active' : '' ?>" href="<?= $tabUrl('peminjam') ?>">Peminjam</a>
      </div>
      <div class="toolbar-actions">
        <?php if ($tab === 'peminjam'): ?>
        <button class="btn gold" type="button" onclick="modal('borrowerGeneratorModal', true)">Generate Akun Peminjam</button>
        <?php else: ?>
        <button class="btn gold" type="button" onclick="modal('userModal', true)">Tambah Akun</button>
        <?php endif; ?>
      </div>
    </div>

    <div class="mobile-list user-mobile-list">
      <?php foreach ($users as $user): ?>
      <article class="mobile-record user-mobile-record">
        <div class="mobile-record-head">
          <div>
            <small class="muted"><?= $tab === 'peminjam' ? ($user->borrower_type === 'teacher' ? 'Guru' : 'Siswa') : esc(role_label($user->role)) ?></small>
            <h3><?= esc($tab === 'peminjam' ? ($user->borrower_name ?: $user->full_name) : $user->full_name) ?></h3>
            <p>@<?= esc($user->username) ?></p>
          </div>
          <span class="badge <?= $user->is_active ? 'green' : 'red' ?>"><?= $user->is_active ? 'Aktif' : 'Nonaktif' ?></span>
        </div>
        <div class="mobile-record-meta">
          <div><small><?= $tab === 'peminjam' ? 'NIP/NISN' : 'Jabatan' ?></small><b><?= esc($tab === 'peminjam' ? ($user->borrower_identity ?: '-') : ($user->position ?: '-')) ?></b></div>
          <div><small>Terakhir masuk</small><b><?= $user->last_login_at ? esc(tgl_id($user->last_login_at, true)) : '<span class="muted">Belum pernah masuk</span>' ?></b></div>
        </div>
        <div class="mobile-record-foot user-mobile-actions">
          <?php if ($tab !== 'peminjam'): ?>
          <button class="user-action" type="button" onclick="openEditUser(<?= htmlspecialchars(json_encode($user), ENT_QUOTES) ?>)">Ubah</button>
          <?php endif; ?>
          <button class="user-action" type="button" onclick="confirmAction('Reset password <?= $tab === 'peminjam' ? 'peminjam' : 'pengguna' ?>?', '<?= $tab === 'peminjam' ? 'Kata sandi akan dikembalikan menjadi NIP/NISN dan wajib diganti saat login.' : 'Kata sandi sementara akan dibuat dan diperlihatkan satu kali.' ?>', '<?= base_url('/admin/users/' . $user->id . '/reset-password') ?>', '<?= $tab === 'peminjam' ? 'Reset ke NIP/NISN' : 'Buat kata sandi sementara' ?>')">Reset sandi</button>
          <?php if ($user->is_active): ?>
          <button class="user-action danger" type="button" onclick="confirmAction('Hapus akun?', 'Akun dengan riwayat transaksi akan dinonaktifkan, bukan dihapus permanen.', '<?= base_url('/admin/users/' . $user->id . '/delete') ?>', 'Hapus')">Hapus</button>
          <?php else: ?>
          <button class="user-action" type="button" onclick="confirmAction('Aktifkan akun?', '<?= esc($user->full_name) ?> akan dapat masuk kembali.', '<?= base_url('/admin/users/' . $user->id . '/toggle-active') ?>', 'Aktifkan')">Aktifkan</button>
          <?php endif; ?>
        </div>
      </article>
      <?php endforeach; ?>
      <?php if (! $users): ?><?= view('partials/empty', ['text' => $tab === 'peminjam' ? 'Belum ada akun peminjam.' : 'Belum ada pengguna utama.', 'icon' => 'users']) ?><?php endif; ?>
    </div>

    <div class="table-wrap">
      <?php if ($tab === 'peminjam'): ?>
      <table class="table">
        <thead><tr><th>Peminjam</th><th>Username</th><th>Status</th><th>Terakhir masuk</th><th>Aksi</th></tr></thead>
        <tbody>
          <?php foreach ($users as $user): ?>
          <tr>
            <td><b><?= esc($user->borrower_name ?: $user->full_name) ?></b><br><small class="muted"><?= $user->borrower_type === 'teacher' ? 'Guru' : 'Siswa' ?> &middot; <?= esc($user->borrower_identity ?: '-') ?></small></td>
            <td><?= esc($user->username) ?></td>
            <td><span class="badge <?= $user->is_active ? 'green' : 'red' ?>"><?= $user->is_active ? 'Aktif' : 'Nonaktif' ?></span></td>
            <td><?= $user->last_login_at ? esc(tgl_id($user->last_login_at, true)) : '<span class="muted">Belum pernah masuk</span>' ?></td>
            <td><div class="user-row-actions">
              <div class="row-kebab">
                <button class="row-btn row-btn-kebab" type="button" aria-haspopup="true" aria-expanded="false" aria-label="Aksi lain" onclick="rowMenuToggle(this)"><?= ui_icon('dots', 'row-btn-icon') ?></button>
                <div class="row-menu" role="menu">
                  <button class="row-menu-item" type="button" role="menuitem" onclick="confirmAction('Reset password peminjam?', 'Kata sandi akan dikembalikan menjadi NIP/NISN dan wajib diganti saat login.', '<?= base_url('/admin/users/' . $user->id . '/reset-password') ?>', 'Reset ke NIP/NISN')"><?= ui_icon('key', 'row-menu-icon') ?><span>Reset sandi</span></button>
                  <?php if ($user->is_active): ?>
                  <button class="row-menu-item row-menu-item-danger" type="button" role="menuitem" onclick="confirmAction('Hapus akun?', 'Akun dengan riwayat transaksi akan dinonaktifkan, bukan dihapus permanen.', '<?= base_url('/admin/users/' . $user->id . '/delete') ?>', 'Hapus')"><?= ui_icon('warning', 'row-menu-icon') ?><span>Hapus</span></button>
                  <?php else: ?>
                  <button class="row-menu-item row-menu-item-primary" type="button" role="menuitem" onclick="confirmAction('Aktifkan akun?', '<?= esc($user->full_name) ?> akan dapat masuk kembali.', '<?= base_url('/admin/users/' . $user->id . '/toggle-active') ?>', 'Aktifkan')"><?= ui_icon('check', 'row-menu-icon') ?><span>Aktifkan</span></button>
                  <?php endif; ?>
                </div>
              </div>
            </div></td>
          </tr>
          <?php endforeach; ?>
          <?php if (! $users): ?><tr><td colspan="5" class="muted">Belum ada akun peminjam.</td></tr><?php endif; ?>
        </tbody>
      </table>
      <?php else: ?>
      <table class="table">
        <thead><tr><th>Pengguna</th><th>Username</th><th>Peran</th><th>Status</th><th>Terakhir masuk</th><th>Aksi</th></tr></thead>
        <tbody>
          <?php foreach ($users as $user): ?>
          <tr>
            <td><b><?= esc($user->full_name) ?></b><?= $user->position ? '<br><small class="muted">' . esc($user->position) . '</small>' : '' ?></td>
            <td><?= esc($user->username) ?></td>
            <td><span class="badge <?= role_badge($user->role) ?>"><?= esc(role_label($user->role)) ?></span></td>
            <td><span class="badge <?= $user->is_active ? 'green' : 'red' ?>"><?= $user->is_active ? 'Aktif' : 'Nonaktif' ?></span></td>
            <td><?= $user->last_login_at ? esc(tgl_id($user->last_login_at, true)) : '<span class="muted">Belum pernah masuk</span>' ?></td>
            <td><div class="user-row-actions">
              <button class="row-btn" type="button" onclick="openEditUser(<?= htmlspecialchars(json_encode($user), ENT_QUOTES) ?>)"><?= ui_icon('profile', 'row-btn-icon') ?><span>Ubah</span></button>
              <div class="row-kebab">
                <button class="row-btn row-btn-kebab" type="button" aria-haspopup="true" aria-expanded="false" aria-label="Aksi lain" onclick="rowMenuToggle(this)"><?= ui_icon('dots', 'row-btn-icon') ?></button>
                <div class="row-menu" role="menu">
                  <button class="row-menu-item" type="button" role="menuitem" onclick="confirmAction('Reset password pengguna?', 'Kata sandi sementara akan dibuat dan diperlihatkan satu kali.', '<?= base_url('/admin/users/' . $user->id . '/reset-password') ?>', 'Buat kata sandi sementara')"><?= ui_icon('key', 'row-menu-icon') ?><span>Reset sandi</span></button>
                  <?php if ($user->is_active): ?>
                  <button class="row-menu-item row-menu-item-danger" type="button" role="menuitem" onclick="confirmAction('Hapus akun?', 'Akun dengan riwayat transaksi akan dinonaktifkan, bukan dihapus permanen.', '<?= base_url('/admin/users/' . $user->id . '/delete') ?>', 'Hapus')"><?= ui_icon('warning', 'row-menu-icon') ?><span>Hapus</span></button>
                  <?php else: ?>
                  <button class="row-menu-item row-menu-item-primary" type="button" role="menuitem" onclick="confirmAction('Aktifkan akun?', '<?= esc($user->full_name) ?> akan dapat masuk kembali.', '<?= base_url('/admin/users/' . $user->id . '/toggle-active') ?>', 'Aktifkan')"><?= ui_icon('check', 'row-menu-icon') ?><span>Aktifkan</span></button>
                  <?php endif; ?>
                </div>
              </div>
            </div></td>
          </tr>
          <?php endforeach; ?>
          <?php if (! $users): ?><tr><td colspan="6" class="muted">Belum ada pengguna utama.</td></tr><?php endif; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>
    <div class="result-note" style="padding:12px 16px">Menampilkan <?= count($users) ?> <?= $tab === 'peminjam' ? 'akun peminjam' : 'pengguna utama' ?></div>
    <?= view('partials/pagination') ?>
  </div>
</div>

<div class="modal" id="userModal">
  <div class="modal-box">
    <span class="eyebrow">Akun operasional</span><h2>Tambahkan Pengguna</h2><p class="muted">Khusus Admin, Pengurus Barang, dan Kepala Sekolah.</p>
  <?= view('partials/notification', ['type' => 'error', 'title' => 'Periksa kembali isian', 'messages' => $errors]) ?>
    <form id="createUserForm" method="post" action="<?= base_url('/admin/users/store') ?>">
      <?= csrf_field() ?>
      <div class="field"><label for="create_full_name">Nama lengkap <span aria-hidden="true">*</span></label><input class="control" id="create_full_name" name="full_name" maxlength="120" value="<?= esc(old_value('full_name')) ?>" required></div>
      <div class="field"><label for="create_username">Username <span aria-hidden="true">*</span></label><input class="control" id="create_username" name="username" minlength="3" maxlength="60" value="<?= esc(old_value('username')) ?>" placeholder="nama.pengguna" required></div>
      <div class="field"><label for="create_position">Jabatan <span aria-hidden="true">*</span></label><input class="control" id="create_position" name="position" maxlength="120" value="<?= esc(old_value('position')) ?>" placeholder="Contoh: TU Pengurus Barang" required></div>
      <div class="field"><label for="create_role">Peran hak akses <span aria-hidden="true">*</span></label><select class="control" id="create_role" name="role" required><option value="petugas">Pengurus Barang</option><option value="kepala_sekolah">Kepala Sekolah</option><option value="admin">Admin</option></select></div>
      <div class="field"><label for="create_password">Password sementara <span aria-hidden="true">*</span></label><input class="control" type="text" id="create_password" name="password" value="<?= esc(old_value('password')) ?>" placeholder="Minimal 6 karakter" required></div>
      <div class="row"><button class="btn" type="button" data-close>Batal</button><button class="btn gold" type="submit">Simpan Akun</button></div>
    </form>
  </div>
</div>

<div class="modal" id="borrowerGeneratorModal">
  <div class="modal-box" style="max-width:720px">
    <span class="eyebrow">Akun peminjam</span><h2>Generate Akun Guru/Siswa</h2>
    <p class="muted">Username awal memakai NIP untuk guru atau NISN untuk siswa. Password acak hanya ditampilkan sekali dan wajib diganti saat masuk pertama.</p>
    <form method="post" action="<?= base_url('/admin/users/generate-borrowers') ?>" id="borrowerGeneratorForm">
      <?= csrf_field() ?>
      <div class="tabs" style="margin:12px 0"><button class="tab active" type="button" data-type-filter="all">Semua</button><button class="tab" type="button" data-type-filter="teacher">Guru</button><button class="tab" type="button" data-type-filter="student">Siswa</button></div>
      <label class="remember" style="margin:0 0 10px"><input type="checkbox" id="selectAllBorrowers"> Pilih semua yang tampil</label>
      <div class="table-wrap" style="max-height:44vh;overflow:auto;border:1px solid var(--line);border-radius:12px">
        <table class="table" id="borrowerGeneratorTable"><thead><tr><th>Pilih</th><th>Jenis</th><th>Nama</th><th>NIP/NISN</th><th>Kelas</th></tr></thead><tbody>
          <?php foreach ($borrowers as $borrower): ?>
          <?php $ready = ! $borrower->linked_user_id && trim((string) $borrower->identity_number) !== ''; ?>
          <tr data-borrower-type="<?= esc($borrower->type) ?>" class="<?= $ready ? '' : 'muted' ?>">
            <td><input type="checkbox" name="borrower_ids[]" value="<?= (int) $borrower->id ?>" <?= $ready ? '' : 'disabled' ?>></td><td><?= $borrower->type === 'teacher' ? 'Guru' : 'Siswa' ?></td><td><b><?= esc($borrower->name) ?></b><?= ! $ready ? '<br><small>' . ($borrower->linked_user_id ? 'Sudah memiliki akun' : 'NIP/NISN kosong') . '</small>' : '' ?></td><td><?= esc($borrower->identity_number ?: '-') ?></td><td><?= esc($borrower->class_name ?: '-') ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if (! $borrowers): ?><tr><td colspan="5" class="muted">Tidak ada data guru/siswa aktif.</td></tr><?php endif; ?>
        </tbody></table>
      </div>
      <div class="row" style="margin-top:16px"><button class="btn" type="button" data-close>Batal</button><button class="btn gold" type="submit">Generate akun terpilih</button></div>
    </form>
  </div>
</div>

<div class="modal" id="editUserModal">
  <div class="modal-box">
    <span class="eyebrow" id="eu_eyebrow">Data pengguna</span><h2 id="eu_title">Ubah Pengguna</h2>
    <form method="post" id="editUserForm">
      <?= csrf_field() ?>
      <div class="field" id="eu_name_field"><label>Nama lengkap</label><input class="control" name="full_name" id="eu_name" required></div>
      <div class="field"><label>Username</label><input class="control" name="username" id="eu_username" required></div>
      <div class="field" id="eu_position_field"><label>Jabatan (opsional)</label><input class="control" name="position" id="eu_position"></div>
      <div class="field" id="eu_role_field"><label>Peran hak akses</label><select class="control" name="role" id="eu_role"><option value="petugas">Pengurus Barang</option><option value="kepala_sekolah">Kepala Sekolah</option><option value="admin">Admin</option></select></div>
      <div class="row"><button class="btn" type="button" data-close>Batal</button><button class="btn gold" type="submit">Simpan Perubahan</button></div>
    </form>
  </div>
</div>

<?php if (is_array($temporaryPassword)): ?>
<div class="modal" id="temporaryPasswordModal" role="dialog" aria-modal="true" aria-labelledby="temporaryPasswordTitle"><div class="modal-box"><span class="badge green">Berhasil</span><h2 id="temporaryPasswordTitle">Kata sandi hasil reset</h2><p class="muted">Berikan kata sandi ini kepada <?= esc($temporaryPassword['user_name'] ?? 'pengguna') ?>.</p><div class="field"><label for="temporaryPasswordValue">Kata sandi</label><div class="row" style="grid-template-columns:minmax(0,1fr) auto"><input class="control" id="temporaryPasswordValue" value="<?= esc($temporaryPassword['password'] ?? '') ?>" readonly autocomplete="off"><button class="btn" type="button" onclick="copyText('temporaryPasswordValue')">Salin</button></div><small class="muted">Ditampilkan satu kali. Salin sebelum menutup dialog.</small></div><button class="btn gold full" type="button" data-close>Selesai</button></div></div>
<?php endif; ?>

<?php if (is_array($generatedCredentials)): ?>
<div class="modal" id="generatedCredentialsModal" role="dialog" aria-modal="true" aria-labelledby="generatedCredentialsTitle"><div class="modal-box" style="max-width:760px"><span class="badge green">Berhasil</span><h2 id="generatedCredentialsTitle"><?= count($generatedCredentials) ?> Akun Peminjam Dibuat</h2><p class="muted">Simpan daftar ini sekarang. Password tidak disimpan dalam bentuk teks dan hanya ditampilkan sekali.</p><div class="table-wrap" style="max-height:46vh;overflow:auto"><table class="table"><thead><tr><th>Nama</th><th>Jenis</th><th>Username</th><th>Password sementara</th></tr></thead><tbody><?php foreach ($generatedCredentials as $credential): ?><tr><td><?= esc($credential['name']) ?></td><td><?= esc($credential['type']) ?></td><td><?= esc($credential['username']) ?></td><td><code><?= esc($credential['password']) ?></code></td></tr><?php endforeach; ?></tbody></table></div><div class="row" style="margin-top:16px"><button class="btn" type="button" onclick="copyGeneratedCredentials()">Salin daftar</button><button class="btn gold" type="button" data-close>Selesai</button></div></div></div>
<?php endif; ?>

<script>
const createUserForm = document.getElementById('createUserForm');
// Validasi JS menampilkan balon bawaan browser melalui reportValidity().
createUserForm.noValidate = true;
const createUserFields = Array.from(createUserForm.querySelectorAll('[required]'));
function validateCreateUserField(field) {
  field.setCustomValidity('');
  if (!field.value.trim()) {
    const label = field.labels[0].textContent.replace('*', '').trim();
    field.setCustomValidity(label + ' wajib diisi.');
  } else if (field.name === 'username' && /\s/.test(field.value)) {
    field.setCustomValidity('Username tidak boleh menggunakan spasi.');
  } else if (field.name === 'username' && field.value.length < 3) {
    field.setCustomValidity('Username minimal 3 karakter.');
  } else if (field.name === 'password' && field.value.length < 6) {
    field.setCustomValidity('Password sementara minimal 6 karakter.');
  }
}
createUserFields.forEach(function (field) {
  field.addEventListener('input', function () { validateCreateUserField(field); });
  field.addEventListener('change', function () { validateCreateUserField(field); });
});
createUserForm.addEventListener('submit', function (event) {
  createUserFields.forEach(validateCreateUserField);
  if (!createUserForm.checkValidity()) {
    event.preventDefault();
    createUserForm.reportValidity();
  }
});
function openEditUser(user) {
  document.getElementById('eu_name').value = user.full_name;
  document.getElementById('eu_username').value = user.username;
  document.getElementById('eu_position').value = user.position || '';
  document.getElementById('eu_role').value = user.role;
  document.getElementById('editUserForm').action = '/admin/users/' + user.id + '/update';
  modal('editUserModal', true);
}
function copyText(id) { const field = document.getElementById(id); field.select(); if (navigator.clipboard) { navigator.clipboard.writeText(field.value).catch(function () { document.execCommand('copy'); }); } else { document.execCommand('copy'); } toast('Berhasil disalin'); }
const filterButtons = document.querySelectorAll('[data-type-filter]');
const borrowerRows = document.querySelectorAll('#borrowerGeneratorTable tbody tr[data-borrower-type]');
filterButtons.forEach(function (button) { button.addEventListener('click', function () { filterButtons.forEach(function (item) { item.classList.remove('active'); }); button.classList.add('active'); borrowerRows.forEach(function (row) { row.hidden = button.dataset.typeFilter !== 'all' && row.dataset.borrowerType !== button.dataset.typeFilter; }); document.getElementById('selectAllBorrowers').checked = false; }); });
document.getElementById('selectAllBorrowers').addEventListener('change', function () { borrowerRows.forEach(function (row) { const checkbox = row.querySelector('input[type="checkbox"]'); if (checkbox && !checkbox.disabled && !row.hidden) { checkbox.checked = this.checked; } }, this); });
document.getElementById('borrowerGeneratorForm').addEventListener('submit', function (event) { if (!this.querySelector('input[name="borrower_ids[]"]:checked')) { event.preventDefault(); toast('Pilih minimal satu guru atau siswa'); } });
<?php if (is_array($temporaryPassword)): ?>
document.addEventListener('DOMContentLoaded', function () { modal('temporaryPasswordModal', true); });
<?php endif; ?>
<?php if (is_array($generatedCredentials)): ?>
document.addEventListener('DOMContentLoaded', function () { modal('generatedCredentialsModal', true); });
function copyGeneratedCredentials() { const rows = <?= json_encode($generatedCredentials, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>; const text = rows.map(function (row) { return row.name + ' | ' + row.type + ' | ' + row.username + ' | ' + row.password; }).join('\n'); if (navigator.clipboard) { navigator.clipboard.writeText(text).then(function () { toast('Daftar kredensial berhasil disalin'); }).catch(function () { toast('Salin manual daftar kredensial'); }); } else { toast('Salin manual daftar kredensial'); } }
<?php endif; ?>
</script>
<?= view('layouts/footer') ?>
