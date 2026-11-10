<?php
$q = $q ?? '';
$module = $module ?? '';
$hasFilters = $q !== '' || $module !== '';
$emptyText = $hasFilters ? 'Tidak ada aktivitas yang cocok. Coba kata kunci lain atau ubah filter.' : 'Belum ada aktivitas akun Anda.';
?>
<?= view('layouts/header', ['title' => 'Riwayat Aktivitas', 'subtitle' => 'Aktivitas akun Anda pada sistem.']) ?>
<div class="page own-activity-page" id="activityLog">
  <div class="page-title responsive-page-title">
    <div><h1>Riwayat Aktivitas</h1><span class="muted">Tindakan yang dilakukan oleh akun Anda.</span></div>
  </div>

  <form class="card mt-18 own-activity-filter" method="get" role="search" aria-label="Cari riwayat aktivitas" action="<?= base_url('/petugas/activity') ?>">
    <div class="own-activity-filter-grid">
      <div class="field">
        <label for="activityKeyword">Cari aktivitas</label>
        <div class="search-control">
          <span class="search-control-icon" aria-hidden="true"><?= ui_icon('search', 'search-svg') ?></span>
          <input class="control" id="activityKeyword" name="q" type="search" enterkeyhint="search" maxlength="<?= \App\Models\ActivityLogModel::SEARCH_MAX_LENGTH ?>" value="<?= esc($q, 'attr') ?>" placeholder="Contoh: Chromebook atau UNT-000003">
        </div>
      </div>
      <div class="field">
        <label for="activityModule">Modul</label>
        <select class="control" id="activityModule" name="module">
          <option value="">Semua modul</option>
          <?php foreach (['Sesi', 'Barang', 'Peminjaman', 'Kerusakan', 'Penanganan', 'Laporan'] as $m): ?>
          <option value="<?= esc($m) ?>" <?= $module === $m ? 'selected' : '' ?>><?= esc($m) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="own-activity-filter-actions">
        <button class="btn gold btn-icon" type="submit"><?= ui_icon('search', 'btn-svg') ?> Cari</button>
        <?php if ($hasFilters): ?><a class="btn" href="<?= base_url('/petugas/activity') ?>">Reset</a><?php endif; ?>
      </div>
    </div>
  </form>

  <div class="table-card mt-18">
    <div class="report-table-toolbar">
      <div><b><?= $hasFilters ? 'Hasil pencarian' : 'Log aktivitas' ?></b><small class="muted"><?= $pager->getTotal() ?> aktivitas ditemukan</small></div>
    </div>

    <div class="mobile-list">
      <?php foreach ($logs as $log): ?>
      <article class="mobile-record">
        <div class="mobile-record-head">
          <span class="badge <?= str_contains($log->action, 'kerusakan') ? 'red' : (str_contains($log->action, 'pengembalian') ? 'green' : (str_contains($log->action, 'peminjaman') || str_contains($log->action, 'login') ? 'amber' : 'blue')) ?>"><?= esc(ucfirst(str_replace('_', ' ', $log->action))) ?></span>
          <h3><?= esc($log->object ?? '—') ?></h3>
          <p class="muted"><?= esc(tgl_id($log->created_at, true)) ?></p>
        </div>
        <?php if (! empty($log->detail) && $log->detail !== 'Tidak ada perubahan'): ?><p class="mobile-record-detail"><?= esc(str_replace('; ', "\n", $log->detail)) ?></p><?php endif; ?>
      </article>
      <?php endforeach; ?>
      <?php if (! $logs): ?><?= view('partials/empty', ['text' => $emptyText, 'icon' => $hasFilters ? 'search' : 'clock']) ?><?php endif; ?>
    </div>

    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>Waktu</th><th>Aktivitas</th><th>Objek</th><th>Detail</th></tr></thead>
        <tbody>
          <?php foreach ($logs as $log): ?>
          <tr>
            <td><?= esc(tgl_id($log->created_at, true)) ?></td>
            <td><?= esc(ucfirst(str_replace('_', ' ', $log->action))) ?></td>
            <td><?= esc($log->object ?? '—') ?></td>
            <td class="activity-detail-cell"><?= esc(str_replace('; ', "\n", $log->detail ?? '—')) ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if (! $logs): ?><tr><td colspan="4" class="muted"><?= esc($emptyText) ?></td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
    <?php if ($pager->getTotal()): ?><?= view('partials/pagination') ?><?php endif; ?>
  </div>
</div>
<?= view('layouts/footer') ?>
