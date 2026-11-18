<?php
$subtitle = 'Pantau kesiapan barang dan tindak lanjut.';
$monthLabel = substr(tgl_id($month_start), 3);
$loansUrl = '/kepala/reports/loans';
$monthReport = base_url($loansUrl . '?' . http_build_query(['from' => $month_start, 'to' => $month_end]));
$attention = [
    ['label' => 'Peminjaman lewat batas', 'count' => $late_loans, 'icon' => 'clock', 'path' => $loansUrl . '?status=late'],
    ['label' => 'Unit menunggu kelengkapan', 'count' => $statuses['incomplete'], 'icon' => 'box', 'path' => '/kepala/items?status=incomplete'],
    ['label' => 'Laporan kerusakan terbuka', 'count' => $open_reports, 'icon' => 'warning', 'path' => '/kepala/reports/damages?status=open'],
];
$attention = array_filter($attention, static fn (array $entry): bool => $entry['count'] > 0);
$statusLabels = [
    'available'  => 'Tersedia',
    'borrowed'   => 'Dipinjam',
    'incomplete' => 'Menunggu kelengkapan',
    'damaged'    => 'Rusak atau ditangani',
];
if ($statuses['other'] > 0) {
    $statusLabels['other'] = 'Status lain';
}
$weekTotal = array_sum($trend);
$weekPeak = max(1, max($trend));
$dayLabels = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];
?>
<?= view('layouts/header', ['title' => 'Dasbor Kepala Sekolah', 'subtitle' => $subtitle]) ?>
<div class="page principal-dashboard" id="dashboard">
  <div class="page-title responsive-page-title">
    <div><h1>Dasbor Kepala Sekolah</h1><span class="muted"><?= esc($subtitle) ?></span></div>
  </div>

  <div class="principal-overview" aria-label="Ringkasan inventaris">
    <a class="card principal-metric" href="<?= esc(base_url('/kepala/items?status=available'), 'attr') ?>">
      <span class="principal-metric-icon is-available" aria-hidden="true"><?= ui_icon('box', 'status-svg') ?></span>
      <span class="principal-metric-label">Unit tersedia</span>
      <strong><?= $statuses['available'] ?></strong>
      <small>dari <?= $total_units ?> unit aktif</small>
    </a>
    <a class="card principal-metric" href="<?= esc($monthReport, 'attr') ?>">
      <span class="principal-metric-icon is-borrowed" aria-hidden="true"><?= ui_icon('loan', 'status-svg') ?></span>
      <span class="principal-metric-label">Peminjaman unit bulan ini</span>
      <strong><?= $month_loans ?></strong>
      <small><?= esc($monthLabel) ?></small>
    </a>
    <a class="card principal-metric" href="<?= esc(base_url($loansUrl . '?status=completed'), 'attr') ?>">
      <span class="principal-metric-icon" aria-hidden="true"><?= ui_icon('check', 'status-svg') ?></span>
      <span class="principal-metric-label">Pengembalian tepat waktu</span>
      <?php if ($on_time_pct !== null): ?>
        <strong><?= $on_time_pct ?>%</strong>
        <small><?= $on_time ?> dari <?= $completed ?> pengembalian unit · Semua periode</small>
      <?php else: ?>
        <strong class="principal-metric-empty">Belum ada pengembalian</strong>
        <small>Semua periode</small>
      <?php endif; ?>
    </a>
  </div>

  <section class="card principal-attention<?= $attention ? ' has-attention' : '' ?>" aria-labelledby="principal-attention-title">
    <h2 id="principal-attention-title">Perlu perhatian</h2>
    <?php if ($attention): ?>
      <ul class="principal-attention-list">
        <?php foreach ($attention as $entry): ?>
          <li>
            <a href="<?= esc(base_url($entry['path']), 'attr') ?>">
              <span class="principal-attention-icon" aria-hidden="true"><?= ui_icon($entry['icon'], 'status-svg') ?></span>
              <span><?= esc($entry['label']) ?></span>
              <strong><?= $entry['count'] ?></strong>
              <span class="principal-arrow" aria-hidden="true">›</span>
            </a>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php else: ?>
      <p class="principal-attention-clear"><span aria-hidden="true"><?= ui_icon('check', 'status-svg') ?></span>Tidak ada masalah yang perlu ditindaklanjuti.</p>
    <?php endif; ?>
  </section>

  <div class="principal-dashboard-grid">
    <section class="card principal-panel" aria-labelledby="principal-status-title">
      <div class="principal-panel-heading">
        <h2 id="principal-status-title">Status barang</h2>
        <a class="link principal-report-link" href="<?= base_url('/kepala/items') ?>">Data barang <span aria-hidden="true">›</span></a>
      </div>
      <div class="principal-status-bar" aria-hidden="true">
        <?php foreach ($statusLabels as $status => $label): ?>
          <?php if ($statuses[$status] > 0): ?>
            <span class="is-<?= esc($status, 'attr') ?>" style="flex-grow:<?= $statuses[$status] ?>"></span>
          <?php endif; ?>
        <?php endforeach; ?>
      </div>
      <dl class="principal-status-rows">
        <?php foreach ($statusLabels as $status => $label): ?>
          <div><dt><span class="principal-status-dot is-<?= esc($status, 'attr') ?>" aria-hidden="true"></span><?= esc($label) ?></dt><dd><?= $statuses[$status] ?></dd></div>
        <?php endforeach; ?>
      </dl>
    </section>

    <section class="card principal-panel" aria-labelledby="principal-trend-title">
      <div class="principal-panel-heading">
        <div>
          <h2 id="principal-trend-title">Unit dipinjam per hari</h2>
          <p class="muted">7 hari terakhir<?= $weekTotal > 0 ? ' · ' . $weekTotal . ' peminjaman unit' : '' ?></p>
        </div>
      </div>
      <?php if ($weekTotal > 0): ?>
      <ul class="principal-trend" aria-label="Jumlah peminjaman unit per hari">
        <?php foreach ($trend as $day => $count): ?>
          <?php $timestamp = strtotime($day); $isToday = $day === $today; ?>
          <li class="<?= $isToday ? 'is-today' : '' ?>">
            <span class="principal-trend-value" aria-hidden="true"><?= $count ?></span>
            <span class="principal-trend-track" aria-hidden="true">
              <?php if ($count > 0): ?><span style="height:<?= $count * 100 / $weekPeak ?>%"></span><?php endif; ?>
            </span>
            <time datetime="<?= esc($day, 'attr') ?>" aria-label="<?= esc(tgl_id_panjang($day) . ', ' . $count . ' peminjaman unit', 'attr') ?>">
              <b><?= $isToday ? 'Hari ini' : $dayLabels[(int) date('w', $timestamp)] ?></b>
              <small><?= date('d/m', $timestamp) ?></small>
            </time>
          </li>
        <?php endforeach; ?>
      </ul>
      <?php else: ?>
        <p class="muted principal-trend-empty">Belum ada peminjaman dalam 7 hari terakhir.</p>
      <?php endif; ?>
    </section>
  </div>

  <section class="card principal-panel" aria-labelledby="principal-popular-title">
    <div class="principal-panel-heading">
      <div>
        <h2 id="principal-popular-title">Barang paling sering dipinjam</h2>
        <p class="muted"><?= esc($monthLabel) ?></p>
      </div>
      <a class="link principal-report-link" href="<?= esc($monthReport, 'attr') ?>">Lihat laporan <span aria-hidden="true">›</span></a>
    </div>
    <?php if ($top_items): ?>
      <ul class="principal-popular-list">
        <?php foreach ($top_items as $item): ?>
          <li>
            <a href="<?= esc(base_url('/kepala/items/' . $item->id), 'attr') ?>">
              <span class="principal-popular-item"><b><?= esc($item->item_name) ?></b><small><?= esc($item->item_code) ?></small></span>
              <span class="principal-popular-count"><strong><?= (int) $item->total ?></strong><small>kali dipinjam</small></span>
              <span class="principal-arrow" aria-hidden="true">›</span>
            </a>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php else: ?>
      <p class="muted principal-popular-empty">Belum ada peminjaman bulan ini.</p>
    <?php endif; ?>
  </section>
</div>
<?= view('layouts/footer') ?>
