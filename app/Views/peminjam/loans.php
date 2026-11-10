<?php $q = $q ?? ''; $scope = $scope ?? 'all'; $base = $scope === 'open' ? '/peminjam/loans' : '/peminjam/history'; ?>
<?= view('layouts/header', ['title' => $title, 'subtitle' => $subtitle]) ?>
<div class="page" id="loans">
  <div class="page-title responsive-page-title">
    <div><h1><?= esc($title) ?></h1><span class="muted"><?= esc($subtitle) ?></span></div>
  </div>

  <div class="table-card mt-18 borrower-loans-card">
    <div class="toolbar borrower-search-toolbar">
      <form method="get" action="<?= base_url($base) ?>" class="row borrower-search-form">
        <input class="control search" name="q" value="<?= esc($q) ?>" placeholder="Cari kode atau nama barang">
        <button class="btn gold" type="submit">Cari</button>
      </form>
    </div>

    <div class="mobile-list accordion-list borrower-loan-list">
      <?php foreach ($loans as $l): ?>
      <article class="mobile-record">
        <div class="mobile-record-head">
          <span class="badge <?= loan_status_label($l->returned_at, $l->due_at, true) ?>"><?= loan_status_label($l->returned_at, $l->due_at) ?></span>
          <h3><?= esc($l->item_code) ?> &middot; <?= esc($l->item_name) ?></h3>
        </div>
        <details class="mobile-record-details">
          <summary>Lihat rincian</summary>
          <p><b>Tanggal pinjam:</b> <?= esc(tgl_id($l->borrowed_at, true)) ?><br><b>Batas kembali:</b> <?= esc(tgl_id($l->due_at, true)) ?><br><b>Lokasi:</b> <?= esc($l->usage_location) ?></p>
        </details>
        <div class="mobile-record-foot"><a class="link" href="<?= base_url('/peminjam/loans/' . $l->id) ?>">Detail &rsaquo;</a></div>
      </article>
      <?php endforeach; ?>
      <?php if (! $loans): ?><?= view('partials/empty', ['text' => 'Tidak ada transaksi.', 'icon' => 'loan']) ?><?php endif; ?>
    </div>

    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>Unit</th><th>Barang</th><th>Tanggal pinjam</th><th>Batas kembali</th><th>Status</th><th>Aksi</th></tr></thead>
        <tbody>
          <?php foreach ($loans as $l): ?>
          <tr>
            <td><b><?= esc($l->item_code) ?></b></td>
            <td><?= esc($l->item_name) ?></td>
            <td><?= esc(tgl_id($l->borrowed_at, true)) ?></td>
            <td><?= esc(tgl_id($l->due_at, true)) ?></td>
            <td><span class="badge <?= loan_status_label($l->returned_at, $l->due_at, true) ?>"><?= loan_status_label($l->returned_at, $l->due_at) ?></span></td>
            <td><div class="row-actions"><a class="row-btn" href="<?= base_url('/peminjam/loans/' . $l->id) ?>"><?= ui_icon('search', 'row-btn-icon') ?><span>Detail</span></a></div></td>
          </tr>
          <?php endforeach; ?>
          <?php if (! $loans): ?><tr><td colspan="6" class="muted">Tidak ada transaksi.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
    <?= view('partials/pagination') ?>
  </div>
</div>
<?= view('layouts/footer') ?>
