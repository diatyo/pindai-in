<?php
/**
 * Layout aplikasi (diikuti layouts/footer).
 * Variabel: $title, $subtitle, $extraCss.
 */
$roleKey = session()->get('role') ?? 'petugas';
$userName = session()->get('full_name') ?? 'Pengguna';
$roleLabel = match ($roleKey) {
    'admin' => 'Admin',
    'petugas' => 'Pengurus Barang',
    'kepala_sekolah' => 'Kepala Sekolah',
    'peminjam' => 'Peminjam',
    default => 'Pengguna',
};
$school = school_name();
$avatar = initials($userName);
$subtitle = $subtitle ?? '';
$extraCss = $extraCss ?? '';

$nav = [
    'petugas' => [
    ['url' => '/petugas', 'icon' => 'home', 'label' => 'Dasbor', 'key' => 'dashboard'],
        ['url' => '/petugas/items', 'icon' => 'box', 'label' => 'Data Barang', 'key' => 'items'],
        ['url' => '/petugas/loans', 'icon' => 'loan', 'label' => 'Peminjaman', 'key' => 'loans'],
        ['url' => '/petugas/accessories', 'icon' => 'check', 'label' => 'Kelengkapan', 'key' => 'accessories'],
        ['url' => '/petugas/damages', 'icon' => 'warning', 'label' => 'Kerusakan', 'key' => 'damages'],
        ['url' => '/petugas/reports', 'icon' => 'reports', 'label' => 'Laporan', 'key' => 'reports'],
        ['url' => '/petugas/activity', 'icon' => 'clock', 'label' => 'Riwayat Aktivitas', 'key' => 'activity', 'divider' => true],
    ],
    'admin' => [
        ['url' => '/admin', 'icon' => 'home', 'label' => 'Dasbor', 'key' => 'dashboard'],
        ['url' => '/admin/users', 'icon' => 'users', 'label' => 'Akun Pengguna', 'key' => 'users'],
        ['url' => '/admin/teachers', 'icon' => 'teacher', 'label' => 'Data Guru', 'key' => 'teachers'],
        ['url' => '/admin/students', 'icon' => 'student', 'label' => 'Data Siswa', 'key' => 'students'],
        ['url' => '/admin/references', 'icon' => 'box', 'label' => 'Data Referensi', 'key' => 'references'],
        ['url' => '/admin/activity', 'icon' => 'clock', 'label' => 'Riwayat Aktivitas', 'key' => 'activity', 'divider' => true],
        ['url' => '/admin/settings', 'icon' => 'profile', 'label' => 'Konfigurasi', 'key' => 'settings'],
    ],
    'kepala_sekolah' => [
        ['url' => '/kepala', 'icon' => 'home', 'label' => 'Dasbor', 'key' => 'dashboard'],
        ['url' => '/kepala/items', 'icon' => 'box', 'label' => 'Data Barang', 'key' => 'items'],
        ['url' => '/kepala/reports/loans', 'icon' => 'loan', 'label' => 'Laporan Peminjaman', 'key' => 'loans'],
        ['url' => '/kepala/reports/damages', 'icon' => 'warning', 'label' => 'Laporan Kerusakan', 'key' => 'damages'],
        ['url' => '/kepala/reports/handling', 'icon' => 'check', 'label' => 'Riwayat Penanganan', 'key' => 'handling'],
        ['url' => '/kepala/activity', 'icon' => 'clock', 'label' => 'Riwayat Aktivitas', 'key' => 'activity', 'divider' => true],
    ],
    'peminjam' => [
    ['url' => '/peminjam', 'icon' => 'home', 'label' => 'Dasbor', 'key' => 'dashboard'],
        ['url' => '/peminjam/loans', 'icon' => 'loan', 'label' => 'Sedang Dipinjam', 'key' => 'loans'],
        ['url' => '/peminjam/history', 'icon' => 'clock', 'label' => 'Riwayat Peminjaman', 'key' => 'history'],
        ['url' => '/peminjam/profile', 'icon' => 'profile', 'label' => 'Profil Saya', 'key' => 'profile'],
    ],
];
$activeKey = 'dashboard';
$seg = service('uri')->getSegments();
if (count($seg) >= 2) {
    $activeKey = $seg[1] === 'reports' ? ($seg[2] ?? 'reports') : $seg[1];
}
if ($roleKey === 'petugas' && $activeKey === 'handling') {
    $activeKey = 'damages';
}
$cssFiles = [
    'petugas' => ['petugas-mobile.css', 'petugas-flow.css', 'professional-ui.css', 'logout.css'],
    'admin' => ['admin-master.css', 'admin-audit.css', 'professional-ui.css', 'role-parity.css', 'logout.css'],
    'kepala_sekolah' => ['professional-ui.css', 'kepala-sekolah-ui.css', 'role-parity.css', 'logout.css'],
    'peminjam' => ['professional-ui.css', 'kepala-sekolah-ui.css', 'role-parity.css', 'peminjam.css', 'logout.css'],
];
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<?= view('partials/app_icons') ?>
<meta name="csrf-token" content="<?= csrf_hash() ?>">
<meta name="csrf-name" content="<?= csrf_token() ?>">
<title><?= esc($title) ?> &mdash; Pindai.in by <?= esc($school) ?></title>
<link rel="preload" href="<?= base_url('assets/fonts/DMSans-VariableFont_opsz,wght.ttf') ?>" as="font" type="font/ttf" crossorigin>
<link rel="preload" href="<?= base_url('assets/fonts/Nunito-VariableFont_wght.ttf') ?>" as="font" type="font/ttf" crossorigin>
<link rel="stylesheet" href="<?= base_url('assets/css/needmcp.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/needmcp.css') ?>">
<?php foreach ($cssFiles[$roleKey] ?? [] as $file): ?>
<link rel="stylesheet" href="<?= base_url('assets/css/' . $file) ?>?v=<?= filemtime(FCPATH . 'assets/css/' . $file) ?>">
<?php endforeach; ?>
<link rel="stylesheet" href="<?= base_url('assets/css/pindai.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/pindai.css') ?>">
<link rel="stylesheet" href="<?= base_url('assets/css/accessories.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/accessories.css') ?>">
<link rel="stylesheet" href="<?= base_url('assets/css/inventory-identity.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/inventory-identity.css') ?>">
<style><?= $extraCss ?></style>
</head>
<body>
<div class="app <?= $roleKey === 'admin' ? 'admin-app' : ($roleKey === 'kepala_sekolah' ? 'principal-app' : ($roleKey === 'peminjam' ? 'principal-app borrower-app' : 'petugas-app')) ?>">
  <header class="top">
    <a class="profile profile-link" href="<?= base_url('/account/password') ?>" aria-label="Ubah kata sandi akun">
      <div class="avatar"><?= esc($avatar) ?></div>
      <div><small><?= esc($roleLabel) ?></small><b><?= esc($userName) ?></b></div>
    </a>
    <?php if ($roleKey === 'petugas'): ?>
    <a class="round" href="<?= base_url('petugas/activity') ?>" aria-label="Buka Riwayat Aktivitas" title="Riwayat Aktivitas"><?= ui_icon('clock', 'round-svg') ?></a>
    <?php elseif ($roleKey === 'kepala_sekolah' || $roleKey === 'peminjam'): ?>
    <span class="badge blue">Baca saja</span>
    <?php else: ?>
    <a class="round" href="<?= base_url('logout') ?>" aria-label="Keluar" title="Keluar"><?= ui_icon('logout', 'round-svg') ?></a>
    <?php endif; ?>
  </header>

  <div class="desktop-shell">
    <aside class="sidebar">
      <div class="brand-name">Pindai<span class="dotin">.in</span><small><?= esc($school) ?></small></div>
      <nav class="nav">
        <?php foreach ($nav[$roleKey] ?? [] as $item): ?>
        <?php if (! empty($item['divider'])): ?><span class="nav-divider" aria-hidden="true"></span><?php endif; ?>
        <a href="<?= base_url($item['url']) ?>" class="<?= $activeKey === $item['key'] ? 'active' : '' ?>"><span class="nav-icon" aria-hidden="true"><?= ui_icon($item['icon'], 'nav-svg') ?></span><span><?= esc($item['label']) ?></span></a>
        <?php endforeach; ?>
      </nav>
      <div class="side-profile">
        <div class="side-profile-identity">
          <span class="side-profile-avatar" aria-hidden="true"><?= esc($avatar) ?></span>
          <span class="side-profile-copy">
            <b class="side-profile-name"><?= esc($userName) ?></b>
            <small><?= esc($roleLabel) ?></small>
          </span>
        </div>
        <div class="side-profile-actions">
          <a class="account-btn" href="<?= base_url('/account/password') ?>"><?= ui_icon('key', 'side-action-icon') ?><span>Ubah sandi</span></a>
          <a class="logout-btn" href="<?= base_url('logout') ?>"><?= ui_icon('logout', 'side-action-icon') ?><span>Keluar</span></a>
        </div>
      </div>
      <div class="sidebar-credit">by SMP Negeri 1 Tanjungpinang</div>
    </aside>

    <main class="desktop-main">
      <header class="desktop-top">
        <div class="desktop-page-heading">
          <b><?= esc($title) ?></b>
          <small><?= esc($subtitle) ?></small>
        </div>
        <?php if ($roleKey === 'kepala_sekolah' || $roleKey === 'peminjam'): ?>
        <span class="badge blue">Baca saja</span>
        <?php endif; ?>
      </header>
      <?= view('partials/flash', [
        'flashSuccess' => session()->getFlashdata('success'),
        'flashError' => session()->getFlashdata('error'),
        'flashInfo' => session()->getFlashdata('info'),
        'flashWarning' => session()->getFlashdata('warning'),
      ]) ?>
