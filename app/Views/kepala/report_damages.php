<?php
$q = $q ?? '';
$status = $status ?? '';
$filtersActive = $q !== '' || $status !== '';
$emptyMessage = $filtersActive ? 'Tidak ada laporan kerusakan yang cocok dengan filter.' : 'Belum ada laporan kerusakan yang dibuat.';
?>
<?= view('layouts/header', ['title' => 'Laporan Kerusakan', 'subtitle' => 'Kerusakan dan status penanganan.']) ?>
<div class="page" id="damages">
  <div class="page-title responsive-page-title">
    <div><h1>Laporan Kerusakan</h1><span class="muted">Barang rusak dan tindak lanjut.</span></div>
  </div>

  <?php if ($unitsWithoutReport): ?>
  <section class="card mt-18" id="unit-rusak" aria-labelledby="unit-rusak-title">
    <div class="principal-panel-heading">
      <div>
        <h2 id="unit-rusak-title"><?= count($unitsWithoutReport) ?> unit rusak tanpa laporan aktif</h2>
        <p class="muted">Status rusak tercatat di Data Barang. Buka detail unit untuk melihat kondisinya.</p>
      </div>
    </div>
    <ul class="principal-popular-list">
      <?php foreach ($unitsWithoutReport as $item): ?>
      <li>
        <a href="<?= base_url('/kepala/items/' . $item->id) ?>">
          <span class="principal-popular-item"><b><?= esc($item->item_name) ?></b><small><?= esc($item->item_code) ?></small></span>
          <small><?= esc($item->condition === 'good' ? item_status_label($item->status) : condition_label($item->condition)) ?></small>
          <span class="principal-arrow" aria-hidden="true">&rsaquo;</span>
        </a>
      </li>
      <?php endforeach; ?>
    </ul>
  </section>
  <?php endif; ?>

  <form class="card mt-18 own-activity-filter" method="get" action="<?= base_url('/kepala/reports/damages') ?>">
    <div class="own-activity-filter-grid">
      <div class="field"><label>Status penanganan</label>
        <select class="control" name="status">
          <option value="">Semua status</option>
          <option value="open" <?= $status === 'open' ? 'selected' : '' ?>>Laporan terbuka</option>
          <option value="reported" <?= $status === 'reported' ? 'selected' : '' ?>>Dilaporkan</option>
          <option value="in_handling" <?= $status === 'in_handling' ? 'selected' : '' ?>>Dalam penanganan</option>
          <option value="completed" <?= $status === 'completed' ? 'selected' : '' ?>>Selesai</option>
          <option value="unrepairable" <?= $status === 'unrepairable' ? 'selected' : '' ?>>Tidak dapat diperbaiki</option>
        </select>
      </div>
      <div class="field"><label>Kata kunci</label>
        <div class="search-control">
          <span class="search-control-icon" aria-hidden="true"><?= ui_icon('search', 'search-svg') ?></span>
          <input class="control" name="q" type="search" enterkeyhint="search" value="<?= esc($q) ?>" placeholder="Unit atau kerusakan">
        </div>
      </div>
      <div class="own-activity-filter-actions">
        <button class="btn gold btn-icon" type="submit"><?= ui_icon('search', 'btn-svg') ?> Terapkan</button>
        <?php if ($filtersActive): ?><a class="btn" href="<?= base_url('/kepala/reports/damages') ?>">Reset</a><?php endif; ?>
      </div>
    </div>
  </form>

  <div class="table-card mt-14">
    <div class="report-table-toolbar">
      <div><b>Laporan tercatat</b><small class="muted"><?= count($reports) ?> laporan ditampilkan</small></div>
      <?php if ($reports): ?>
      <a class="btn btn-icon" target="_blank" href="<?= base_url('/kepala/reports/pdf?type=damages&status=' . urlencode($status)) ?>"><?= ui_icon('box', 'btn-svg') ?> Cetak PDF</a>
      <?php endif; ?>
    </div>

    <div class="mobile-list">
      <?php foreach ($reports as $r): ?>
      <article class="mobile-record">
        <div class="mobile-record-head">
          <div><h3><?= esc($r->item_code) ?></h3><p><?= esc($r->description) ?></p></div>
          <span class="badge <?= damage_status_badge($r->status) ?>"><?= damage_status_label($r->status) ?></span>
        </div>
        <div class="mobile-record-meta">
          <div><small>Dilaporkan</small><b><?= esc(tgl_id($r->discovered_at, true)) ?></b></div>
          <div><small>Pelapor</small><b><?= esc($r->reported_by_name ?? '-') ?></b></div>
        </div>
        <div class="mobile-record-foot mobile-record-foot-end"><a class="link" href="<?= base_url('/kepala/damages/' . $r->id) ?>">Lihat detail &rsaquo;</a></div>
      </article>
      <?php endforeach; ?>
      <?php if (! $reports): ?><?= view('partials/empty', ['text' => $emptyMessage, 'icon' => 'warning']) ?><?php endif; ?>
    </div>

    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>Unit</th><th>Kerusakan</th><th>Dilaporkan</th><th>Pelapor</th><th>Status</th><th>Detail</th></tr></thead>
        <tbody>
          <?php foreach ($reports as $r): ?>
          <tr>
            <td><?= esc($r->item_code) ?></td>
            <td><?= esc($r->description) ?></td>
            <td><?= esc(tgl_id($r->discovered_at, true)) ?></td>
            <td><?= esc($r->reported_by_name ?? '-') ?></td>
            <td><span class="badge <?= damage_status_badge($r->status) ?>"><?= damage_status_label($r->status) ?></span></td>
            <td><div class="row-actions"><a class="row-btn" href="<?= base_url('/kepala/damages/' . $r->id) ?>"><?= ui_icon('search', 'row-btn-icon') ?><span>Lihat</span></a></div></td>
          </tr>
          <?php endforeach; ?>
          <?php if (! $reports): ?><tr><td colspan="6"><?= view('partials/empty', ['text' => $emptyMessage, 'icon' => 'warning', 'flush' => true]) ?></td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
    <?= view('partials/pagination') ?>
  </div>
</div>
<?= view('layouts/footer') ?>
