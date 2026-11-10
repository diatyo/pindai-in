<?= view('layouts/header', ['title' => 'Dasbor Kepala Sekolah', 'subtitle' => 'Kondisi barang, peminjaman, dan penanganan dalam satu tampilan.']) ?>
<div class="page" id="dashboard">
  <div class="page-title responsive-page-title">
    <div><h1>Dasbor Kepala Sekolah</h1><span class="muted">Kondisi barang, peminjaman, dan penanganan dalam satu tampilan.</span></div>
  </div>

  <div class="role-status-strip">
    <a class="mobile-status-item success" href="<?= base_url('/kepala/items') ?>"><span aria-hidden="true"><?= ui_icon('box','status-svg') ?></span><b><?= $total_units ?></b><small>Total unit</small></a>
    <a class="mobile-status-item info" href="<?= base_url('/kepala/reports/loans') ?>"><span aria-hidden="true"><?= ui_icon('loan','status-svg') ?></span><b><?= $month_loans ?></b><small>Peminjaman bulan ini</small></a>
    <a class="mobile-status-item success" href="<?= base_url('/kepala/reports/loans') ?>"><span aria-hidden="true"><?= ui_icon('check','status-svg') ?></span><b><?= $on_time_pct ?>%</b><small>Tepat waktu</small></a>
    <a class="mobile-status-item overdue" href="<?= base_url('/kepala/reports/damages') ?>"><span aria-hidden="true"><?= ui_icon('warning','status-svg') ?></span><b><?= $need_handling ?></b><small>Perlu penanganan</small></a>
  </div>

  <div class="chart-grid">
    <article class="card weekly-chart-card">
      <?php $weekTotal = array_sum($trend); $peak = max($trend); $scaleMax = max(1, $peak); $dayLabels = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab']; ?>
      <div class="section-head"><h2>Peminjaman 7 Hari Terakhir</h2><span class="muted"><?= $weekTotal > 0 ? $weekTotal . ' transaksi' : 'Belum ada transaksi' ?></span></div>
      <div class="weekly-chart-layout">
        <div class="weekly-chart-axis" aria-hidden="true"><span></span><div><span><?= $peak ?></span><span><?= $peak > 1 ? (int) ceil($peak / 2) : '' ?></span><span>0</span></div><span></span></div>
        <div class="chart weekly-chart" role="img" aria-label="Grafik peminjaman tujuh hari terakhir, total <?= $weekTotal ?> transaksi">
          <?php foreach ($trend as $day => $count): $timestamp = strtotime($day); ?>
          <div class="weekly-bar-item<?= $day === date('Y-m-d') ? ' is-today' : '' ?><?= $count === 0 ? ' is-zero' : '' ?>" title="<?= esc(tgl_id($day)) ?>: <?= $count ?> transaksi">
            <span class="weekly-bar-value"><?= $count ?></span>
            <div class="weekly-bar-track"><span class="bar" style="height:<?= $count > 0 ? max(8, round(($count / $scaleMax) * 100)) : 0 ?>%"></span></div>
            <small><b><?= $dayLabels[(int) date('w', $timestamp)] ?></b><span><?= date('d/m', $timestamp) ?></span></small>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </article>
    <article class="card status-chart-card">
      <div class="section-head"><h2>Status barang</h2></div>
      <div class="donut<?= $total_units > 0 ? '' : ' is-empty' ?>" style="--available-end:<?= $available_end ?>%;--borrowed-end:<?= $borrowed_end ?>%"><span><b><?= $total_units ?></b><small>Total unit</small></span></div>
      <div class="kv"><span><i style="color:var(--gold)">●</i> Tersedia</span><b><?= $available ?></b></div>
      <div class="kv"><span>Menunggu kelengkapan</span><b><?= (int) ($incomplete ?? 0) ?></b></div>
      <div class="kv"><span><i style="color:var(--sage)">●</i> Dipinjam</span><b><?= $borrowed ?></b></div>
      <div class="kv"><span><i style="color:var(--brown)">●</i> Rusak/ditangani</span><b><?= $damaged ?></b></div>
    </article>
  </div>

  <div class="section-head"><h2>Barang paling sering dipinjam</h2><a class="link" href="<?= base_url('/kepala/reports/loans') ?>">Lihat laporan</a></div>
  <div class="summary">
    <?php foreach ($top_items as $i => $t): ?>
    <article class="card"><span class="badge amber"><?= str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) ?></span><h3><?= esc($t->item_name) ?></h3><b><?= esc($t->total) ?></b><small class="muted">transaksi</small></article>
    <?php endforeach; ?>
    <?php if (! $top_items): ?><?= view('partials/empty', ['text' => 'Belum ada data.', 'icon' => 'box', 'flush' => true]) ?><?php endif; ?>
  </div>

  <div class="section-head"><h2>Perlu perhatian</h2></div>
  <article class="card">
    <?php foreach ($unhandled as $u): ?>
    <div class="task-head" style="margin-bottom:10px">
      <div><span class="badge red"><?= esc($u->item_code) ?></span><b><?= esc($u->item_name) ?></b><br><small class="muted"><?= esc($u->description) ?></small></div>
      <a href="<?= base_url('/kepala/reports/damages') ?>">&rsaquo;</a>
    </div>
    <?php endforeach; ?>
    <?php if (! $unhandled): ?><?= view('partials/empty', ['text' => 'Tidak ada laporan belum ditangani.', 'icon' => 'check', 'flush' => true]) ?><?php endif; ?>
  </article>
</div>
<?= view('layouts/footer') ?>
