<?= view('layouts/header', ['title' => 'Dasbor', 'subtitle' => 'Pantau akun, data guru, data siswa, dan aktivitas sistem.']) ?>
<div class="page admin-dashboard" id="dashboard">
  <div class="page-title responsive-page-title">
    <div><h1>Dasbor Admin</h1><span class="muted">Pantau akun, data guru, data siswa, dan aktivitas sistem.</span></div>
  </div>

  <div class="role-status-strip">
    <a class="mobile-status-item info" href="<?= base_url('/admin/users') ?>"><span aria-hidden="true"><?= ui_icon('users','status-svg') ?></span><b><?= $total_users ?></b><small>Akun sistem</small></a>
    <a class="mobile-status-item success" href="<?= base_url('/admin/teachers') ?>"><span aria-hidden="true"><?= ui_icon('teacher','status-svg') ?></span><b><?= $teachers ?></b><small>Data guru</small></a>
    <a class="mobile-status-item due" href="<?= base_url('/admin/students') ?>"><span aria-hidden="true"><?= ui_icon('student','status-svg') ?></span><b><?= $students ?></b><small>Data siswa</small></a>
    <a class="mobile-status-item <?= $inactive_users ? 'overdue' : 'success' ?>" href="<?= base_url('/admin/users') ?>"><span aria-hidden="true"><?= ui_icon('warning','status-svg') ?></span><b><?= $inactive_users ?></b><small>Akun nonaktif</small></a>
  </div>

  <div class="admin-overview">
    <article class="card">
      <div class="section-head"><h2>Perlu diperhatikan</h2></div>
      <?php if ($inactive_account): ?>
      <div class="admin-check">
        <span class="badge amber">Akun</span>
        <div><b><?= $inactive_users ?> akun nonaktif</b><small><?= esc($inactive_account->full_name) ?> belum dapat masuk ke sistem.</small></div>
        <a class="link" href="<?= base_url('/admin/users') ?>">Periksa</a>
      </div>
      <?php else: ?>
      <div class="admin-check">
        <span class="badge green">Akun</span>
        <div><b>Semua akun aktif</b><small>Seluruh akun dapat mengakses sistem sesuai perannya.</small></div>
        <a class="link" href="<?= base_url('/admin/users') ?>">Lihat</a>
      </div>
      <?php endif; ?>
      <div class="admin-check">
        <span class="badge green">Audit</span>
        <div><b>Pencatatan aktivitas aktif</b><small>Aktivitas akun dan pengelolaan data master tercatat.</small></div>
        <a class="link" href="<?= base_url('/admin/activity') ?>">Lihat</a>
      </div>
    </article>

    <article class="card">
      <div class="section-head"><h2>Status administrasi</h2></div>
      <div class="kv"><span>Akun aktif</span><b><?= $active_users ?> dari <?= $total_users ?></b></div>
      <div class="kv"><span>Data guru</span><b><?= $teachers ?></b></div>
      <div class="kv"><span>Data siswa</span><b><?= $students ?></b></div>
      <div class="kv"><span>Pembaruan data terakhir</span><b><?= $last_master_update ? esc(date('d M Y', strtotime($last_master_update))) : '—' ?></b></div>
    </article>
  </div>

  <div class="section-head admin-activity-head"><h2>Aktivitas administratif terbaru</h2><a class="link" href="<?= base_url('/admin/activity') ?>">Lihat semua</a></div>
  <article class="card timeline admin-timeline">
    <?php foreach ($recent_activities as $activity): ?>
    <?php $activityText = match ($activity->action) {
      'login' => 'berhasil masuk',
      'logout' => 'keluar dari sistem',
      'data_guru_diperbarui' => 'memperbarui data guru',
      'data_siswa_diperbarui' => 'memperbarui data siswa',
      default => str_replace('_', ' ', $activity->action),
    }; ?>
    <div class="event"><b><?= esc($activity->user_name) ?> <?= esc($activityText) ?></b><small><?= esc(tgl_id($activity->created_at, true)) ?> &middot; <?= esc($activity->module ?: role_label((string) ($activity->role ?? ''))) ?></small></div>
    <?php endforeach; ?>
    <?php if (! $recent_activities): ?><?= view('partials/empty', ['text' => 'Belum ada aktivitas administratif.', 'icon' => 'clock', 'flush' => true]) ?><?php endif; ?>
  </article>
</div>
<?= view('layouts/footer') ?>
