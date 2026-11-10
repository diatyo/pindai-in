<?php $q = $q ?? ''; $status = $status ?? ''; $type = $type ?? ''; $from = $from ?? ''; $to = $to ?? ''; $filtersActive = $q !== '' || $status !== '' || $type !== '' || $from !== '' || $to !== ''; ?>
<?= view('layouts/header', ['title' => 'Laporan Peminjaman', 'subtitle' => 'Ringkasan transaksi peminjaman.']) ?>
<div class="page" id="loans">
  <div class="page-title responsive-page-title">
    <div><h1>Laporan Peminjaman</h1><span class="muted">Transaksi peminjaman dan pengembalian barang.</span></div>
  </div>

  <form class="card mt-18 own-activity-filter" method="get" action="<?= base_url('/kepala/reports/loans') ?>">
    <div class="own-activity-filter-grid">
      <div class="field"><label>Rentang tanggal</label><div class="date-pair"><input class="control" type="date" name="from" value="<?= esc($from) ?>" aria-label="Tanggal mulai"><input class="control" type="date" name="to" value="<?= esc($to) ?>" aria-label="Tanggal akhir"></div></div>
      <div class="field"><label>Status transaksi</label>
        <select class="control" name="status">
          <option value="">Semua status</option>
          <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Dipinjam</option>
          <option value="completed" <?= $status === 'completed' ? 'selected' : '' ?>>Selesai</option>
          <option value="late" <?= $status === 'late' ? 'selected' : '' ?>>Terlambat</option>
        </select>
      </div>
      <div class="field"><label>Jenis peminjam</label>
        <select class="control" name="type">
          <option value="">Semua</option>
          <option value="teacher" <?= $type === 'teacher' ? 'selected' : '' ?>>Guru</option>
          <option value="student" <?= $type === 'student' ? 'selected' : '' ?>>Siswa</option>
        </select>
      </div>
      <div class="field"><label>Kata kunci</label>
        <div class="search-control">
          <span class="search-control-icon" aria-hidden="true"><?= ui_icon('search', 'search-svg') ?></span>
          <input class="control" name="q" type="search" enterkeyhint="search" value="<?= esc($q) ?>" placeholder="Unit atau peminjam">
        </div>
      </div>
      <div class="own-activity-filter-actions">
        <button class="btn gold btn-icon" type="submit"><?= ui_icon('search', 'btn-svg') ?> Terapkan</button>
        <?php if ($filtersActive): ?><a class="btn" href="<?= base_url('/kepala/reports/loans') ?>">Reset</a><?php endif; ?>
      </div>
    </div>
  </form>

  <div class="card report-summary-strip mt-14">
    <article class="report-summary-item"><small class="muted">Transaksi</small><b><?= $summary['total'] ?></b></article>
    <article class="report-summary-item"><small class="muted">Selesai</small><b><?= $summary['completed'] ?></b></article>
    <article class="report-summary-item"><small class="muted">Aktif</small><b><?= $summary['active'] ?></b></article>
    <article class="report-summary-item"><small class="muted">Terlambat</small><b><?= $summary['late'] ?></b></article>
  </div>

  <div class="table-card mt-14">
    <div class="report-table-toolbar">
      <div><b>Data peminjaman</b><small class="muted"><?= count($loans) ?> data ditampilkan</small></div>
      <?php if ($loans): ?>
      <a class="btn btn-icon" target="_blank" href="<?= base_url('/kepala/reports/pdf?type=loans&from=' . urlencode($from) . '&to=' . urlencode($to) . '&status=' . urlencode($status)) ?>"><?= ui_icon('box', 'btn-svg') ?> Cetak PDF</a>
      <?php endif; ?>
    </div>

    <div class="mobile-list">
      <?php foreach ($loans as $l): ?>
      <article class="mobile-record">
        <div class="mobile-record-head">
          <div><span class="badge blue"><?= $l->borrower_type === 'teacher' ? 'Guru' : 'Siswa' ?></span><h3><?= esc($l->item_code) ?> &middot; <?= esc($l->item_name) ?></h3><p><?= esc($l->borrower_name) ?></p></div>
          <span class="badge <?= loan_status_label($l->returned_at, $l->due_at, true) ?>"><?= loan_status_label($l->returned_at, $l->due_at) ?></span>
        </div>
        <div class="mobile-record-meta">
          <div><small>Tanggal pinjam</small><b><?= esc(tgl_id($l->borrowed_at)) ?></b></div>
          <div><small>Batas kembali</small><b><?= esc(tgl_id($l->due_at, true)) ?></b></div>
        </div>
        <div class="mobile-record-foot mobile-record-foot-end"><a class="link" href="<?= base_url('/kepala/loans/' . $l->id) ?>">Lihat detail &rsaquo;</a></div>
      </article>
      <?php endforeach; ?>
      <?php if (! $loans): ?><?= view('partials/empty', ['text' => 'Tidak ada transaksi.', 'icon' => 'loan']) ?><?php endif; ?>
    </div>

    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>Unit</th><th>Peminjam</th><th>Jenis</th><th>Tanggal pinjam</th><th>Batas kembali</th><th>Status</th><th>Detail</th></tr></thead>
        <tbody>
          <?php foreach ($loans as $l): ?>
          <tr>
            <td><?= esc($l->item_code) ?> &middot; <?= esc($l->item_name) ?></td>
            <td><?= esc($l->borrower_name) ?></td>
            <td><?= $l->borrower_type === 'teacher' ? 'Guru' : 'Siswa' ?></td>
            <td><?= esc(tgl_id($l->borrowed_at)) ?></td>
            <td><?= esc(tgl_id($l->due_at, true)) ?></td>
            <td><span class="badge <?= loan_status_label($l->returned_at, $l->due_at, true) ?>"><?= loan_status_label($l->returned_at, $l->due_at) ?></span></td>
            <td><div class="row-actions"><a class="row-btn" href="<?= base_url('/kepala/loans/' . $l->id) ?>"><?= ui_icon('search', 'row-btn-icon') ?><span>Lihat</span></a></div></td>
          </tr>
          <?php endforeach; ?>
          <?php if (! $loans): ?><tr><td colspan="7"><?= view('partials/empty', ['text' => 'Tidak ada transaksi.', 'icon' => 'loan', 'flush' => true]) ?></td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
    <?= view('partials/pagination') ?>
  </div>
</div>
<?= view('layouts/footer') ?>
