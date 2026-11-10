<?php
$type = $type ?? 'loans';
$from = $from ?? '';
$to = $to ?? '';
$status = $status ?? '';
$category = $category ?? '';
$filtersActive = $from !== '' || $to !== '' || $status !== '' || $category !== '';
$rowOffset = isset($pager) && $pager ? (($pager->getCurrentPage() - 1) * $pager->getPerPage()) : 0;
?>
<?= view('layouts/header', ['title' => 'Laporan Operasional', 'subtitle' => 'Filter dan cetak laporan peminjaman, kerusakan, dan penanganan.']) ?>
<div class="page" id="reports">
  <div class="page-title responsive-page-title">
    <div><h1>Laporan Operasional</h1><span class="muted">Peminjaman, kerusakan, dan penanganan.</span></div>
  </div>

  <form class="card mt-18 report-filter" method="get" action="<?= base_url('/petugas/reports') ?>">
    <div class="report-filter-grid">
      <div class="field">
        <label>Jenis laporan</label>
        <select class="control" name="type" id="reportType">
          <option value="loans" <?= ($type ?: 'loans') === 'loans' ? 'selected' : '' ?>>Peminjaman</option>
          <option value="damages" <?= $type === 'damages' ? 'selected' : '' ?>>Kerusakan</option>
          <option value="handling" <?= $type === 'handling' ? 'selected' : '' ?>>Penanganan</option>
        </select>
      </div>
      <div class="field"><label>Rentang tanggal</label><div class="date-pair"><input class="control" type="date" name="from" value="<?= esc($from) ?>" aria-label="Tanggal mulai"><input class="control" type="date" name="to" value="<?= esc($to) ?>" aria-label="Tanggal akhir"></div></div>
      <div class="field"><label>Kategori barang</label>
        <select class="control" name="category">
          <option value="">Semua kategori</option>
          <?php foreach ($categories as $c): ?>
          <option value="<?= esc($c->category) ?>" <?= $category === $c->category ? 'selected' : '' ?>><?= esc($c->category) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label>Status</label>
        <select class="control" name="status">
          <option value="">Semua status</option>
          <?php if ($type === 'damages'): ?>
          <option value="reported" <?= $status === 'reported' ? 'selected' : '' ?>>Dilaporkan</option>
          <option value="in_handling" <?= $status === 'in_handling' ? 'selected' : '' ?>>Dalam penanganan</option>
          <option value="completed" <?= $status === 'completed' ? 'selected' : '' ?>>Selesai</option>
          <option value="unrepairable" <?= $status === 'unrepairable' ? 'selected' : '' ?>>Tidak dapat diperbaiki</option>
          <?php elseif ($type === 'handling'): ?>
          <option value="in_progress" <?= $status === 'in_progress' ? 'selected' : '' ?>>Dalam proses</option>
          <option value="completed" <?= $status === 'completed' ? 'selected' : '' ?>>Selesai</option>
          <option value="unrepairable" <?= $status === 'unrepairable' ? 'selected' : '' ?>>Tidak dapat diperbaiki</option>
          <?php else: ?>
          <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Dipinjam</option>
          <option value="late" <?= $status === 'late' ? 'selected' : '' ?>>Terlambat</option>
          <option value="completed" <?= $status === 'completed' ? 'selected' : '' ?>>Selesai</option>
          <?php endif; ?>
        </select>
      </div>
      <div class="report-filter-submit">
        <button class="btn gold btn-icon" type="submit"><?= ui_icon('search', 'btn-svg') ?> Tampilkan</button>
        <?php if ($filtersActive): ?>
        <a class="btn" href="<?= base_url('/petugas/reports') ?>">Reset</a>
        <?php endif; ?>
      </div>
    </div>
  </form>

  <?php if (count($summary) > 1): ?>
  <?php $summaryLabels = ['total' => $type === 'damages' ? 'Laporan' : ($type === 'handling' ? 'Tindakan' : 'Transaksi'), 'completed' => 'Selesai', 'active' => 'Aktif', 'late' => 'Terlambat', 'reported' => 'Dilaporkan', 'in_handling' => 'Dalam penanganan', 'in_progress' => 'Dalam proses', 'unrepairable' => 'Tidak dapat diperbaiki']; ?>
  <div class="card report-summary-strip mt-14">
    <?php foreach ($summary as $k => $v): ?>
    <article class="report-summary-item"><small class="muted"><?= esc($summaryLabels[$k] ?? ucfirst(str_replace('_', ' ', $k))) ?></small><b><?= esc($v) ?></b></article>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <div class="table-card mt-14">
    <div class="report-table-toolbar">
      <div><b>Data laporan</b><small class="muted"><?= isset($pager) && $pager ? $pager->getTotal() : count($rows) ?> data</small></div>
      <?php if ($rows): ?>
      <a class="btn btn-icon" target="_blank" href="<?= base_url('/petugas/reports/pdf?type=' . urlencode($type) . '&from=' . urlencode($from) . '&to=' . urlencode($to) . '&status=' . urlencode($status) . '&category=' . urlencode($category)) ?>"><?= ui_icon('box', 'btn-svg') ?> Cetak PDF</a>
      <?php endif; ?>
    </div>
    <div class="table-wrap">
      <table class="table report-table report-table-<?= esc($type ?: 'loans') ?>">
        <?php if (($type ?: 'loans') === 'loans'): ?>
        <thead><tr><th>No</th><th>Nomor</th><th>Unit</th><th>Peminjam</th><th>Jenis</th><th>Pinjam</th><th>Kembali</th><th>Status</th></tr></thead>
        <tbody>
          <?php foreach ($rows as $i => $r): ?>
          <tr>
            <td><?= $rowOffset + $i + 1 ?></td>
            <td><?= esc($r->loan_number) ?></td>
            <td><?= esc($r->item_code) ?> · <?= esc($r->item_name) ?></td>
            <td><?= esc($r->borrower_name) ?></td>
            <td><?= $r->borrower_type === 'teacher' ? 'Guru' : 'Siswa' ?></td>
            <td><?= esc(tgl_id($r->borrowed_at)) ?></td>
            <td><?= esc(tgl_id($r->returned_at)) ?></td>
            <td><span class="badge <?= loan_status_label($r->returned_at, $r->due_at, true) ?>"><?= loan_status_label($r->returned_at, $r->due_at) ?></span></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
        <?php elseif ($type === 'damages'): ?>
        <thead><tr><th>No</th><th>Unit</th><th>Kerusakan</th><th>Dilaporkan</th><th>Pelapor</th><th>Status</th></tr></thead>
        <tbody>
          <?php foreach ($rows as $i => $r): ?>
          <tr>
            <td><?= $rowOffset + $i + 1 ?></td>
            <td><?= esc($r->item_code) ?></td>
            <td><?= esc($r->description) ?></td>
            <td><?= esc(tgl_id($r->discovered_at, true)) ?></td>
            <td><?= esc($r->reported_by_name ?? '—') ?></td>
            <td><span class="badge <?= damage_status_badge($r->status) ?>"><?= damage_status_label($r->status) ?></span></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
        <?php else: ?>
          <thead><tr><th>No</th><th>Unit</th><th>Masalah</th><th>Keterangan</th><th>Pelaksana</th><th>Tanggal</th><th>Status</th></tr></thead>
        <tbody>
          <?php foreach ($rows as $i => $r): ?>
          <tr>
            <td><?= $rowOffset + $i + 1 ?></td>
            <td><?= esc($r->item_code) ?></td>
            <td><?= esc($r->damage_description) ?></td>
            <td><?= esc($r->action_description) ?></td>
            <td><?= esc($r->executor_name) ?></td>
            <td><?= esc(tgl_id($r->action_date)) ?></td>
            <td><span class="badge <?= $r->status === 'completed' ? 'green' : ($r->status === 'unrepairable' ? 'amber' : 'blue') ?>"><?= $r->status === 'completed' ? 'Selesai' : ($r->status === 'unrepairable' ? 'Tidak diperbaiki' : 'Dalam proses') ?></span></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
        <?php endif; ?>
      </table>
      <?php if (! $rows): ?><?= view('partials/empty', ['text' => 'Tidak ada data untuk filter ini.', 'icon' => 'reports']) ?><?php endif; ?>
    </div>
    <?= view('partials/pagination') ?>
  </div>
</div>
<script>
document.getElementById('reportType')?.addEventListener('change',function(){
  const status=this.form.querySelector('[name="status"]');
  if(status) status.value='';
  this.form.requestSubmit();
});
</script>
<?= view('layouts/footer') ?>
