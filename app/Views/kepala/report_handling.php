<?php $q = $q ?? ''; $status = $status ?? ''; $filtersActive = $q !== '' || $status !== ''; ?>
<?= view('layouts/header', ['title' => 'Riwayat Penanganan', 'subtitle' => 'Riwayat keterangan penanganan barang.']) ?>
<div class="page" id="handling">
  <div class="page-title responsive-page-title">
    <div><h1>Riwayat Penanganan</h1><span class="muted">Keterangan penanganan barang rusak.</span></div>
  </div>

  <form class="card mt-18 own-activity-filter" method="get" action="<?= base_url('/kepala/reports/handling') ?>">
    <div class="own-activity-filter-grid">
      <div class="field"><label>Status penanganan</label>
        <select class="control" name="status">
          <option value="">Semua status</option>
          <option value="in_progress" <?= $status === 'in_progress' ? 'selected' : '' ?>>Berjalan</option>
          <option value="completed" <?= $status === 'completed' ? 'selected' : '' ?>>Selesai</option>
          <option value="unrepairable" <?= $status === 'unrepairable' ? 'selected' : '' ?>>Tidak diperbaiki</option>
        </select>
      </div>
      <div class="field"><label>Kata kunci</label>
        <div class="search-control">
          <span class="search-control-icon" aria-hidden="true"><?= ui_icon('search', 'search-svg') ?></span>
          <input class="control" name="q" type="search" enterkeyhint="search" value="<?= esc($q) ?>" placeholder="Unit atau pelaksana">
        </div>
      </div>
      <div class="own-activity-filter-actions">
        <button class="btn gold btn-icon" type="submit"><?= ui_icon('search', 'btn-svg') ?> Terapkan</button>
        <?php if ($filtersActive): ?><a class="btn" href="<?= base_url('/kepala/reports/handling') ?>">Reset</a><?php endif; ?>
      </div>
    </div>
  </form>

  <div class="table-card mt-14">
    <div class="report-table-toolbar">
      <div><b>Data penanganan</b><small class="muted"><?= count($actions) ?> data ditampilkan</small></div>
      <?php if ($actions): ?>
      <a class="btn btn-icon" target="_blank" href="<?= base_url('/kepala/reports/pdf?type=handling&status=' . urlencode($status)) ?>"><?= ui_icon('box', 'btn-svg') ?> Cetak PDF</a>
      <?php endif; ?>
    </div>

    <div class="mobile-list">
      <?php foreach ($actions as $a): ?>
      <article class="mobile-record">
        <div class="mobile-record-head">
          <div><h3><?= esc($a->item_code) ?></h3><p><?= esc($a->damage_description) ?></p></div>
          <span class="badge <?= $a->status === 'completed' ? 'green' : ($a->status === 'unrepairable' ? 'amber' : 'blue') ?>"><?= $a->status === 'completed' ? 'Selesai' : ($a->status === 'unrepairable' ? 'Tidak diperbaiki' : 'Berjalan') ?></span>
        </div>
        <div class="mobile-record-meta">
          <div><small>Keterangan</small><b><?= esc($a->action_description) ?></b></div>
          <div><small>Pelaksana</small><b><?= esc($a->executor_name) ?></b></div>
          <div><small>Tanggal</small><b><?= esc(tgl_id($a->action_date)) ?></b></div>
        </div>
        <div class="mobile-record-foot mobile-record-foot-end"><a class="link" href="<?= base_url('/kepala/damages/' . $a->damage_report_id) ?>">Lihat detail kerusakan &rsaquo;</a></div>
      </article>
      <?php endforeach; ?>
      <?php if (! $actions): ?><?= view('partials/empty', ['text' => 'Tidak ada keterangan penanganan.', 'icon' => 'check']) ?><?php endif; ?>
    </div>

    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>Unit</th><th>Masalah</th><th>Keterangan</th><th>Pelaksana</th><th>Tanggal</th><th>Status</th><th>Detail</th></tr></thead>
        <tbody>
          <?php foreach ($actions as $a): ?>
          <tr>
            <td><?= esc($a->item_code) ?></td>
            <td><?= esc($a->damage_description) ?></td>
            <td><?= esc($a->action_description) ?></td>
            <td><?= esc($a->executor_name) ?></td>
            <td><?= esc(tgl_id($a->action_date)) ?></td>
            <td><span class="badge <?= $a->status === 'completed' ? 'green' : ($a->status === 'unrepairable' ? 'amber' : 'blue') ?>"><?= $a->status === 'completed' ? 'Selesai' : ($a->status === 'unrepairable' ? 'Tidak diperbaiki' : 'Berjalan') ?></span></td>
            <td><div class="row-actions"><a class="row-btn" href="<?= base_url('/kepala/damages/' . $a->damage_report_id) ?>"><?= ui_icon('search', 'row-btn-icon') ?><span>Lihat</span></a></div></td>
          </tr>
          <?php endforeach; ?>
          <?php if (! $actions): ?><tr><td colspan="7"><?= view('partials/empty', ['text' => 'Tidak ada keterangan penanganan.', 'icon' => 'check', 'flush' => true]) ?></td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
    <?= view('partials/pagination') ?>
  </div>
</div>
<?= view('layouts/footer') ?>
