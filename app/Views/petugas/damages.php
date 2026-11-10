<?php $status = $status ?? ''; $q = $q ?? ''; $itemId = (int) ($item_id ?? 0); $statusCounts = $status_counts ?? []; ?>
<?= view('layouts/header', ['title' => 'Kerusakan & Penanganan', 'subtitle' => 'Laporan kondisi dan tindak lanjut barang.']) ?>
<div class="page" id="damages">
  <div class="page-title responsive-page-title">
    <div><h1>Kerusakan & Penanganan</h1><span class="muted">Pantau laporan dari ditemukan hingga selesai ditangani.</span></div>
  </div>

  <form class="card mt-18 damage-filter" method="get" action="<?= base_url('/petugas/damages') ?>">
    <input type="hidden" name="status" value="<?= esc($status) ?>">
    <?php if ($itemId > 0): ?><input type="hidden" name="item" value="<?= $itemId ?>"><?php endif; ?>
    <div class="damage-filter-grid">
      <div class="field">
        <label for="damageKeyword">Cari laporan</label>
        <div class="search-control">
          <span class="search-control-icon" aria-hidden="true"><?= ui_icon('search', 'search-svg') ?></span>
          <input class="control" id="damageKeyword" name="q" type="search" enterkeyhint="search" value="<?= esc($q) ?>" placeholder="Kode unit atau masalah kerusakan">
        </div>
      </div>
      <div class="damage-filter-actions">
        <button class="btn gold btn-icon" type="submit"><?= ui_icon('search', 'btn-svg') ?> Cari</button>
        <?php if ($q !== ''): ?><a class="btn" href="<?= base_url('/petugas/damages?status=' . urlencode($status) . ($itemId > 0 ? '&item=' . $itemId : '')) ?>">Reset</a><?php endif; ?>
      </div>
    </div>
  </form>

  <div class="table-card mt-18">
    <div class="toolbar">
      <div class="tabs">
        <?php foreach (['' => 'Semua', 'reported' => 'Belum ditangani', 'in_handling' => 'Dalam penanganan', 'completed' => 'Selesai', 'unrepairable' => 'Tidak diperbaiki'] as $k => $label): ?>
        <?php $count = (int) ($statusCounts[$k === '' ? 'all' : $k] ?? 0); ?>
        <a class="tab <?= ($status ?: '') === $k ? 'active' : '' ?>" href="<?= base_url('/petugas/damages?status=' . $k . '&q=' . urlencode($q) . ($itemId > 0 ? '&item=' . $itemId : '')) ?>"><?= $label ?> <span class="tab-count"><?= $count ?></span></a>
        <?php endforeach; ?>
      </div>
      <div class="toolbar-actions">
        <a class="btn gold btn-icon" href="<?= base_url('/petugas/damages/create') ?>"><?= ui_icon('plus','btn-svg') ?> Lapor Kerusakan</a>
      </div>
    </div>

    <div class="mobile-list accordion-list">
      <?php foreach ($reports as $r): ?>
      <article class="mobile-record">
        <div class="mobile-record-head">
          <span class="badge <?= damage_status_badge($r->status) ?>"><?= damage_status_label($r->status) ?></span>
          <h3><?= esc($r->item_code) ?> · <?= esc($r->description) ?></h3>
        </div>
        <details class="mobile-record-details"><summary>Lihat rincian</summary><p><b>Pelapor:</b> <?= esc($r->reported_by_name ?? '—') ?><br><b>Dilaporkan:</b> <?= esc(tgl_id($r->discovered_at, true)) ?></p></details>
        <div class="mobile-record-foot">
          <a class="link" href="<?= base_url('/petugas/damages/' . $r->id) ?>">Lihat detail</a>
          <?php if ($r->status === 'reported' || $r->status === 'in_handling'): ?>
          <a class="btn" href="<?= base_url('/petugas/handling/create/' . $r->id) ?>"><?= $r->status === 'reported' ? 'Tangani' : 'Lanjutkan' ?></a>
          <?php endif; ?>
        </div>
      </article>
      <?php endforeach; ?>
      <?php if (! $reports): ?><?= view('partials/empty', ['text' => 'Tidak ada laporan kerusakan.', 'icon' => 'warning']) ?><?php endif; ?>
    </div>

    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>Unit</th><th>Masalah</th><th>Pelapor</th><th>Tanggal</th><th>Status</th><th>Aksi</th></tr></thead>
        <tbody>
          <?php foreach ($reports as $r): ?>
          <tr>
            <td><?= esc($r->item_code) ?></td>
            <td><?= esc($r->description) ?></td>
            <td><?= esc($r->reported_by_name ?? '—') ?></td>
            <td><?= esc(tgl_id($r->discovered_at)) ?></td>
            <td><span class="badge <?= damage_status_badge($r->status) ?>"><?= damage_status_label($r->status) ?></span></td>
            <td><div class="row-actions">
              <a class="row-btn" href="<?= base_url('/petugas/damages/' . $r->id) ?>"><?= ui_icon('search', 'row-btn-icon') ?><span>Detail</span></a>
              <?php if ($r->status === 'reported' || $r->status === 'in_handling'): ?>
              <a class="row-btn row-btn-primary" href="<?= base_url('/petugas/handling/create/' . $r->id) ?>"><?= ui_icon('check', 'row-btn-icon') ?><span><?= $r->status === 'reported' ? 'Tangani' : 'Lanjutkan' ?></span></a>
              <?php endif; ?>
            </div></td>
          </tr>
          <?php endforeach; ?>
          <?php if (! $reports): ?>
          <tr><td colspan="6"><?= view('partials/empty', ['text' => 'Tidak ada laporan pada status ini.', 'icon' => 'warning', 'flush' => true]) ?></td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
    <?= view('partials/pagination') ?>
  </div>
</div>
<?= view('layouts/footer') ?>
