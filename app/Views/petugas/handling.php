<?php $status = $status ?? ''; $q = $q ?? ''; ?>
<?= view('layouts/header', ['title' => 'Penanganan', 'subtitle' => 'Keterangan penanganan barang rusak.']) ?>
<div class="page" id="handling">
  <div class="page-title responsive-page-title">
    <div><h1>Riwayat Penanganan</h1><span class="muted">Keterangan penanganan barang rusak.</span></div>
  </div>

  <div class="table-card mt-18">
    <div class="toolbar">
      <div class="tabs">
        <?php foreach (['' => 'Semua', 'reported' => 'Belum ditangani', 'in_handling' => 'Berjalan', 'completed' => 'Selesai', 'unrepairable' => 'Tidak diperbaiki'] as $k => $label): ?>
        <a class="tab <?= ($status ?: '') === $k ? 'active' : '' ?>" href="<?= base_url('/petugas/handling?status=' . $k) ?>"><?= $label ?></a>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="mobile-list accordion-list">
      <?php foreach ($reports as $r): ?>
      <article class="mobile-record">
        <div class="mobile-record-head">
          <span class="badge <?= damage_status_badge($r->status) ?>"><?= damage_status_label($r->status) ?></span>
          <h3><?= esc($r->item_code) ?> · <?= esc($r->description) ?></h3>
        </div>
        <details class="mobile-record-details"><summary>Lihat rincian</summary><p><b>Dilaporkan:</b> <?= esc(tgl_id($r->discovered_at, true)) ?> oleh <?= esc($r->reported_by_name ?? '—') ?></p></details>
        <div class="mobile-record-foot">
          <a class="link" href="<?= base_url('/petugas/handling/' . $r->id) ?>">Detail ›</a>
          <?php if ($r->status === 'reported' || $r->status === 'in_handling'): ?>
          <a class="btn" href="<?= base_url('/petugas/handling/create/' . $r->id) ?>">Tangani</a>
          <?php endif; ?>
        </div>
      </article>
      <?php endforeach; ?>
      <?php if (! $reports): ?><?= view('partials/empty', ['text' => 'Tidak ada data penanganan.', 'icon' => 'check']) ?><?php endif; ?>
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
            <td>
              <a class="link" href="<?= base_url('/petugas/handling/' . $r->id) ?>">Detail</a>
              <?php if ($r->status === 'reported' || $r->status === 'in_handling'): ?> · <a class="link" href="<?= base_url('/petugas/handling/create/' . $r->id) ?>">Tangani</a><?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?= view('partials/pagination') ?>
  </div>
</div>
<?= view('layouts/footer') ?>
