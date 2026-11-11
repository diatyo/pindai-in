<?php
$q = $q ?? '';
$module = $module ?? '';
$from = $from ?? '';
$to = $to ?? '';
$hasFilters = $q !== '' || $module !== '' || $from !== '' || $to !== '';
$emptyText = $hasFilters ? 'Tidak ada aktivitas yang cocok. Coba kata kunci lain atau ubah filter.' : 'Belum ada aktivitas.';
?>
<?= view('layouts/header', ['title' => 'Riwayat Aktivitas', 'subtitle' => 'Aktivitas akun Anda pada sistem.']) ?>
<div class="page" id="activity">
  <div class="page-title responsive-page-title">
    <div><h1>Riwayat Aktivitas</h1><span class="muted">Tindakan yang dilakukan oleh akun Anda.</span></div>
  </div>

  <form class="card mt-18 activity-filter" method="get" action="<?= base_url('/kepala/activity') ?>">
    <div class="activity-filter-grid">
      <div class="field"><label for="actFrom">Dari tanggal</label><input class="control" id="actFrom" type="date" name="from" value="<?= esc($from) ?>"></div>
      <div class="field"><label for="actTo">Sampai tanggal</label><input class="control" id="actTo" type="date" name="to" value="<?= esc($to) ?>"></div>
      <div class="field"><label for="actModule">Modul</label>
        <select class="control" id="actModule" name="module">
          <option value="">Semua modul</option>
          <?php foreach (['Sesi', 'Akun', 'Guru', 'Siswa', 'Barang', 'Peminjaman', 'Kerusakan', 'Penanganan', 'Laporan', 'Konfigurasi', 'Referensi'] as $m): ?>
          <option value="<?= esc($m) ?>" <?= $module === $m ? 'selected' : '' ?>><?= esc($m) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field activity-filter-keyword"><label for="actKeyword">Kata kunci</label>
        <div class="search-control">
          <span class="search-control-icon" aria-hidden="true"><?= ui_icon('search', 'search-svg') ?></span>
          <input class="control" id="actKeyword" name="q" type="search" enterkeyhint="search" maxlength="<?= \App\Models\ActivityLogModel::SEARCH_MAX_LENGTH ?>" value="<?= esc($q, 'attr') ?>" placeholder="Aktor, kode unit, aktivitas, atau detail">
        </div>
      </div>
      <div class="activity-filter-actions">
        <button class="btn gold btn-icon" type="submit"><?= ui_icon('search', 'btn-svg') ?> Filter</button>
        <?php if ($q !== '' || $module !== '' || $from !== '' || $to !== ''): ?><a class="btn" href="<?= base_url('/kepala/activity') ?>">Reset</a><?php endif; ?>
      </div>
    </div>
  </form>

  <div class="table-card mt-14">
    <div class="report-table-toolbar">
      <div><b><?= $hasFilters ? 'Hasil pencarian' : 'Log aktivitas' ?></b><small class="muted"><?= $pager->getTotal() ?> aktivitas ditemukan</small></div>
    </div>

    <div class="mobile-list">
      <?php foreach ($logs as $log): ?>
      <article class="mobile-record">
        <div class="mobile-record-head">
          <div><span class="badge <?= str_contains($log->action, 'kerusakan') ? 'red' : (str_contains($log->action, 'pengembalian') ? 'green' : (str_contains($log->action, 'peminjaman') || str_contains($log->action, 'login') ? 'amber' : 'blue')) ?>"><?= esc(ucfirst(str_replace('_', ' ', $log->action))) ?></span><h3><?= esc($log->object ?? '-') ?></h3><p class="muted"><?= esc(tgl_id($log->created_at, true)) ?></p></div>
        </div>
        <div class="mobile-record-meta">
          <div><small>Aktor</small><b><?= esc($log->user_name ?? '-') ?><?= $log->role ? ' · ' . esc($log->role) : '' ?></b></div>
          <div><small>IP</small><b><?= esc($log->ip_address ?? '-') ?></b></div>
        </div>
        <?php if (! empty($log->detail) && $log->detail !== 'Tidak ada perubahan'): ?><p class="mobile-record-detail"><?= esc(str_replace('; ', "\n", $log->detail)) ?></p><?php endif; ?>
      </article>
      <?php endforeach; ?>
      <?php if (! $logs): ?><?= view('partials/empty', ['text' => $emptyText, 'icon' => $hasFilters ? 'search' : 'clock']) ?><?php endif; ?>
    </div>

    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>Waktu</th><th>Aktor</th><th>Peran</th><th>Aktivitas</th><th>Objek</th><th>Detail</th><th>IP</th></tr></thead>
        <tbody>
          <?php foreach ($logs as $log): ?>
          <tr>
            <td><?= esc(tgl_id($log->created_at, true)) ?></td>
            <td><?= esc($log->user_name ?? '-') ?></td>
            <td><?= esc($log->role ?? '—') ?></td>
            <td><?= esc(ucfirst(str_replace('_', ' ', $log->action))) ?></td>
            <td><?= esc($log->object ?? '-') ?></td>
            <td class="activity-detail-cell"><?= esc(str_replace('; ', "\n", $log->detail ?? '-')) ?></td>
            <td><?= esc($log->ip_address ?? '—') ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if (! $logs): ?><tr><td colspan="7"><?= view('partials/empty', ['text' => $emptyText, 'icon' => $hasFilters ? 'search' : 'clock', 'flush' => true]) ?></td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
    <?php if ($pager->getTotal()): ?><?= view('partials/pagination') ?><?php endif; ?>
  </div>
</div>
<?= view('layouts/footer') ?>
