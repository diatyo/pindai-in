<?= view('layouts/header', ['title' => 'Profil Saya', 'subtitle' => 'Data identitas peminjam.']) ?>
<div class="page form-desktop" id="profile">
  <div class="page-title responsive-page-title">
    <div><h1>Profil Saya</h1><span class="muted">Data ini dikelola oleh Admin.</span></div>
  </div>

  <article class="card mt-18 borrower-detail-card">
    <div class="kv"><span>Nama lengkap</span><b><?= esc($borrower->name) ?></b></div>
    <div class="kv"><span>Status</span><b><?= $borrower->type === 'teacher' ? 'Guru' : 'Siswa' ?></b></div>
    <div class="kv"><span><?= $borrower->type === 'teacher' ? 'NIP' : 'NISN' ?></span><b><?= esc($borrower->identity_number) ?></b></div>
    <?php if ($borrower->class_name): ?><div class="kv"><span>Kelas</span><b><?= esc($borrower->class_name) ?></b></div><?php endif; ?>
    <div class="kv"><span>Jenis kelamin</span><b><?= esc($borrower->gender ?? '-') ?></b></div>
    <div class="kv"><span>Nomor telepon</span><b><?= esc($borrower->phone ?? '-') ?></b></div>
    <div class="kv"><span>Nama pengguna</span><b><?= esc(session()->get('username')) ?></b></div>
  </article>

  <div class="row mt-14 borrower-profile-actions">
    <a class="btn" href="<?= base_url('/account/password') ?>">Ubah kata sandi</a>
    <a class="btn danger" href="<?= base_url('/logout') ?>">Keluar</a>
  </div>
  <p class="muted" style="margin-top:12px">Perubahan data identitas hanya dapat dilakukan oleh Admin.</p>
</div>
<?= view('layouts/footer') ?>
