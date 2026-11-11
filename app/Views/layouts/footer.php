<?php
$roleKey = session()->get('role') ?? 'petugas';
$path = '/' . trim(service('uri')->getPath(), '/');
$isPath = static fn (string $prefix): bool => $path === $prefix || str_starts_with($path, $prefix . '/');
?>

    </main>
  </div>

  <?php if ($roleKey === 'petugas'): ?>
  <nav class="bottom has-fab" aria-label="Navigasi utama Petugas">
    <a class="<?= $path === '/petugas' || $path === '/petugas/dashboard' ? 'active' : '' ?>" href="<?= base_url('/petugas') ?>">
<span class="bottom-icon" aria-hidden="true"><svg viewBox="0 0 20 20" focusable="false"><path d="M3 9.5 10 3l7 6.5"/><path d="M5 8.5V17h10V8.5M8 17v-5h4v5"/></svg></span>Dasbor
    </a>
    <a class="<?= $isPath('/petugas/items') ? 'active' : '' ?>" href="<?= base_url('/petugas/items') ?>">
      <span class="bottom-icon" aria-hidden="true"><svg viewBox="0 0 20 20" focusable="false"><rect x="3.5" y="3.5" width="13" height="13" rx="1.5"/><path d="M7 7h6v6H7z"/></svg></span>Barang
    </a>
    <a class="<?= $isPath('/petugas/scan') ? 'active fab' : 'fab' ?>" href="<?= base_url('/petugas/scan') ?>" aria-label="Scan QR barang">
      <span class="bottom-icon" aria-hidden="true"><svg viewBox="0 0 20 20" focusable="false"><path d="M7 3v14M13 3v14M3 7h14M3 13h14"/></svg></span>
    </a>
    <a class="<?= $isPath('/petugas/loans') ? 'active' : '' ?>" href="<?= base_url('/petugas/loans') ?>">
      <span class="bottom-icon" aria-hidden="true"><svg viewBox="0 0 20 20" focusable="false"><path d="M5 15 15 5M9 5h6v6"/></svg></span>Pinjam
    </a>
    <button class="<?= $isPath('/petugas/activity') || $isPath('/petugas/damages') || $isPath('/petugas/handling') || $isPath('/petugas/reports') || $isPath('/petugas/accessories') ? 'active' : '' ?>" type="button" onclick="sheet()">
      <span class="bottom-icon" aria-hidden="true"><svg viewBox="0 0 20 20" focusable="false"><circle cx="4" cy="10" r="1.3" fill="currentColor" stroke="none"/><circle cx="10" cy="10" r="1.3" fill="currentColor" stroke="none"/><circle cx="16" cy="10" r="1.3" fill="currentColor" stroke="none"/></svg></span>Lainnya
    </button>
  </nav>
  <div class="sheet" id="moreSheet">
    <div class="sheet-box">
      <div class="section-head"><h2>Menu lainnya</h2><button class="round" type="button" data-close aria-label="Tutup menu">&times;</button></div>
      <div class="sheet-menu">
        <a class="btn sheet-item" href="<?= base_url('/petugas/damages') ?>"><?= ui_icon('warning','sheet-svg') ?> Kerusakan</a>
        <a class="btn sheet-item" href="<?= base_url('/petugas/accessories') ?>"><?= ui_icon('check','sheet-svg') ?> Tindak Lanjut Kelengkapan</a>
        <a class="btn sheet-item" href="<?= base_url('/petugas/reports') ?>"><?= ui_icon('reports','sheet-svg') ?> Laporan</a>
        <a class="btn sheet-item" href="<?= base_url('/petugas/activity') ?>"><?= ui_icon('clock','sheet-svg') ?> Riwayat Aktivitas</a>
        <a class="btn sheet-item" href="<?= base_url('/petugas/items/create') ?>"><?= ui_icon('plus','sheet-svg') ?> Tambah Barang</a>
        <a class="btn danger sheet-item" href="<?= base_url('/logout') ?>"><?= ui_icon('logout','sheet-svg') ?> Keluar</a>
      </div>
    </div>
  </div>
  <?php elseif ($roleKey === 'admin'): ?>
  <nav class="bottom">
<a class="<?= $path === '/admin' || $path === '/admin/dashboard' ? 'active' : '' ?>" href="<?= base_url('/admin') ?>"><span class="bottom-icon" aria-hidden="true"><?= ui_icon('home') ?></span>Dasbor</a>
    <a class="<?= $isPath('/admin/users') ? 'active' : '' ?>" href="<?= base_url('/admin/users') ?>"><span class="bottom-icon" aria-hidden="true"><?= ui_icon('users') ?></span>Pengguna</a>
    <a class="<?= $isPath('/admin/teachers') ? 'active' : '' ?>" href="<?= base_url('/admin/teachers') ?>"><span class="bottom-icon" aria-hidden="true"><?= ui_icon('teacher') ?></span>Guru</a>
    <a class="<?= $isPath('/admin/students') ? 'active' : '' ?>" href="<?= base_url('/admin/students') ?>"><span class="bottom-icon" aria-hidden="true"><?= ui_icon('student') ?></span>Siswa</a>
    <button class="<?= $isPath('/admin/activity') || $isPath('/admin/references') || $isPath('/admin/settings') || $isPath('/admin/backups') ? 'active' : '' ?>" type="button" onclick="sheet()"><span class="bottom-icon" aria-hidden="true"><?= ui_icon('dots') ?></span>Lainnya</button>
  </nav>
  <div class="sheet" id="moreSheet">
    <div class="sheet-box">
      <div class="section-head"><h2>Menu lainnya</h2><button class="round" type="button" data-close aria-label="Tutup menu">&times;</button></div>
      <div class="sheet-menu">
        <a class="btn sheet-item" href="<?= base_url('/admin/references') ?>"><?= ui_icon('box','sheet-svg') ?> Data Referensi</a>
        <a class="btn sheet-item" href="<?= base_url('/admin/activity') ?>"><?= ui_icon('clock','sheet-svg') ?> Riwayat Aktivitas</a>
        <a class="btn sheet-item" href="<?= base_url('/admin/settings') ?>"><?= ui_icon('profile','sheet-svg') ?> Konfigurasi</a>
        <a class="btn sheet-item" href="<?= base_url('/admin/backups') ?>"><?= ui_icon('archive','sheet-svg') ?> Backup &amp; Pemulihan</a>
        <a class="btn danger sheet-item" href="<?= base_url('/logout') ?>"><?= ui_icon('logout','sheet-svg') ?> Keluar</a>
      </div>
    </div>
  </div>
  <?php elseif ($roleKey === 'peminjam'): ?>
  <nav class="bottom" aria-label="Navigasi utama Peminjam">
<a class="<?= $path === '/peminjam' || $path === '/peminjam/dashboard' ? 'active' : '' ?>" href="<?= base_url('/peminjam') ?>"><span class="bottom-icon" aria-hidden="true"><?= ui_icon('home') ?></span>Dasbor</a>
    <a class="<?= $isPath('/peminjam/loans') ? 'active' : '' ?>" href="<?= base_url('/peminjam/loans') ?>"><span class="bottom-icon" aria-hidden="true"><?= ui_icon('loan') ?></span>Dipinjam</a>
    <a class="<?= $isPath('/peminjam/history') ? 'active' : '' ?>" href="<?= base_url('/peminjam/history') ?>"><span class="bottom-icon" aria-hidden="true"><?= ui_icon('clock') ?></span>Riwayat</a>
    <a class="<?= $isPath('/peminjam/profile') ? 'active' : '' ?>" href="<?= base_url('/peminjam/profile') ?>"><span class="bottom-icon" aria-hidden="true"><?= ui_icon('profile') ?></span>Profil</a>
  </nav>
  <?php else: ?>
  <nav class="bottom">
<a class="<?= $path === '/kepala' || $path === '/kepala/dashboard' ? 'active' : '' ?>" href="<?= base_url('/kepala') ?>"><span class="bottom-icon" aria-hidden="true"><?= ui_icon('home') ?></span>Dasbor</a>
    <a class="<?= $isPath('/kepala/items') ? 'active' : '' ?>" href="<?= base_url('/kepala/items') ?>"><span class="bottom-icon" aria-hidden="true"><?= ui_icon('box') ?></span>Barang</a>
    <a class="<?= $isPath('/kepala/reports/loans') ? 'active' : '' ?>" href="<?= base_url('/kepala/reports/loans') ?>"><span class="bottom-icon" aria-hidden="true"><?= ui_icon('loan') ?></span>Pinjam</a>
    <a class="<?= $isPath('/kepala/reports/damages') ? 'active' : '' ?>" href="<?= base_url('/kepala/reports/damages') ?>"><span class="bottom-icon" aria-hidden="true"><?= ui_icon('warning') ?></span>Kerusakan</a>
    <button class="<?= $isPath('/kepala/reports/handling') || $isPath('/kepala/activity') ? 'active' : '' ?>" type="button" onclick="sheet()"><span class="bottom-icon" aria-hidden="true"><?= ui_icon('dots') ?></span>Lainnya</button>
  </nav>
  <div class="sheet" id="moreSheet">
    <div class="sheet-box principal-menu-sheet">
      <div class="section-head"><h2>Menu lainnya</h2><button class="round" type="button" data-close aria-label="Tutup menu">&times;</button></div>
      <div class="sheet-menu principal-sheet-menu">
        <a class="btn sheet-item" href="<?= base_url('/kepala/reports/handling') ?>"><?= ui_icon('check','sheet-svg') ?> Penanganan</a>
        <a class="btn sheet-item" href="<?= base_url('/kepala/activity') ?>"><?= ui_icon('clock','sheet-svg') ?> Aktivitas</a>
        <a class="btn sheet-item" href="<?= base_url('/kepala/reports/loans') ?>"><?= ui_icon('loan','sheet-svg') ?> Peminjaman</a>
        <a class="btn sheet-item" href="<?= base_url('/kepala/reports/damages') ?>"><?= ui_icon('warning','sheet-svg') ?> Kerusakan</a>
        <a class="btn danger sheet-item principal-sheet-logout" href="<?= base_url('/logout') ?>"><?= ui_icon('logout','sheet-svg') ?> Keluar</a>
      </div>
      <p class="muted principal-sheet-note">Semua menu Kepala Sekolah bersifat baca saja.</p>
    </div>
  </div>
  <?php endif; ?>
</div>

<div class="modal" id="confirmModal">
  <div class="modal-box">
    <span class="badge amber">Konfirmasi</span>
    <h2 id="confirmTitle">Yakin melanjutkan?</h2>
    <p class="muted" id="confirmText"></p>
    <form id="confirmForm" method="post" action="">
      <?= csrf_field() ?>
      <div class="row mt-18">
        <button type="button" class="btn" data-close>Batal</button>
        <button type="submit" class="btn gold" id="confirmSubmit">Ya, lanjutkan</button>
      </div>
    </form>
  </div>
</div>
<div class="toast" id="toast"></div>
<script src="<?= base_url('assets/js/pindai.js') ?>?v=<?= filemtime(FCPATH . 'assets/js/pindai.js') ?>"></script>
<script src="<?= base_url('assets/js/accessories.js') ?>?v=<?= filemtime(FCPATH . 'assets/js/accessories.js') ?>"></script>
<?php if ($roleKey === 'petugas' && ($isPath('/petugas/scan') || $isPath('/petugas/loans/groups/create'))): ?>
<script src="<?= base_url('assets/vendor/jsQR.js') ?>?v=<?= filemtime(FCPATH . 'assets/vendor/jsQR.js') ?>"></script>
<script src="<?= base_url('assets/js/scanner.js') ?>?v=<?= filemtime(FCPATH . 'assets/js/scanner.js') ?>"></script>
<?php endif; ?>
<?php if ($roleKey === 'petugas' && $isPath('/petugas/loans/groups/create')): ?>
<script src="<?= base_url('assets/js/loan-groups.js') ?>?v=<?= filemtime(FCPATH . 'assets/js/loan-groups.js') ?>"></script>
<?php endif; ?>
</body>
</html>
