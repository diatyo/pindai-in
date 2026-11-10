<?= view('layouts/header', ['title' => 'Dasbor Peminjam', 'subtitle' => 'Ringkasan barang yang Anda pinjam.']) ?>
<div class="page" id="dashboard">
  <div class="page-title responsive-page-title">
    <div><h1>Dasbor</h1><span class="muted">Data peminjaman atas nama Anda.</span></div>
  </div>

  <div class="role-status-strip">
    <a class="mobile-status-item info" href="<?= base_url('/peminjam/loans') ?>"><span aria-hidden="true"><?= ui_icon('loan','status-svg') ?></span><b><?= $active ?></b><small>Sedang dipinjam</small></a>
    <a class="mobile-status-item overdue" href="<?= base_url('/peminjam/loans') ?>"><span aria-hidden="true"><?= ui_icon('warning','status-svg') ?></span><b><?= $late ?></b><small>Terlambat</small></a>
    <a class="mobile-status-item success" href="<?= base_url('/peminjam/history') ?>"><span aria-hidden="true"><?= ui_icon('clock','status-svg') ?></span><b><?= $total ?></b><small>Total riwayat</small></a>
  </div>

  <div class="section-head"><h2>Sedang dipinjam</h2><a class="link" href="<?= base_url('/peminjam/loans') ?>">Lihat semua</a></div>
  <div class="mobile-list accordion-list borrower-dashboard-list">
    <?php foreach ($active_loans as $l): ?>
    <article class="mobile-record">
      <div class="mobile-record-head">
        <span class="badge <?= loan_status_label($l->returned_at, $l->due_at, true) ?>"><?= loan_status_label($l->returned_at, $l->due_at) ?></span>
        <h3><?= esc($l->item_code) ?> &middot; <?= esc($l->item_name) ?></h3>
      </div>
      <div class="mobile-record-foot"><small class="muted">Batas kembali <?= esc(tgl_id($l->due_at, true)) ?></small><a class="link" href="<?= base_url('/peminjam/loans/' . $l->id) ?>">Detail &rsaquo;</a></div>
    </article>
    <?php endforeach; ?>
    <?php if (! $active_loans): ?><?= view('partials/empty', ['text' => 'Tidak ada barang yang sedang Anda pinjam.', 'icon' => 'loan']) ?><?php endif; ?>
  </div>

  <div class="section-head"><h2>Riwayat terbaru</h2><a class="link" href="<?= base_url('/peminjam/history') ?>">Lihat semua</a></div>
  <article class="card timeline borrower-timeline">
    <?php foreach ($recent as $l): ?>
    <div class="event"><b><?= esc($l->item_code) ?> &middot; <?= esc($l->item_name) ?> &mdash; <?= loan_status_label($l->returned_at, $l->due_at) ?></b><small><?= esc(tgl_id($l->borrowed_at, true)) ?></small></div>
    <?php endforeach; ?>
    <?php if (! $recent): ?><?= view('partials/empty', ['text' => 'Belum ada riwayat peminjaman.', 'icon' => 'clock', 'flush' => true]) ?><?php endif; ?>
  </article>
</div>
<?= view('layouts/footer') ?>
