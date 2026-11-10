<?= view('layouts/header', ['title' => 'Dasbor', 'subtitle' => 'Pantau tugas dan kondisi barang hari ini.']) ?>
<div class="page" id="dashboard">
  <div class="page-title responsive-page-title">
    <div>
      <span class="eyebrow">Pindai.in</span>
      <h1>Dasbor</h1>
      <span class="muted">Pantau tugas dan kondisi barang hari ini.</span>
    </div>
  </div>

  <div class="mobile-operation">
    <div class="mobile-status-strip">
      <a class="mobile-status-item overdue" href="<?= base_url('/petugas/loans?status=late') ?>" aria-label="Buka <?= $late ?> barang terlambat">
        <span aria-hidden="true"><?= ui_icon('clock','status-svg') ?></span><b><?= $late ?></b><small>Barang terlambat</small>
      </a>
      <a class="mobile-status-item due" href="<?= base_url('/petugas/loans') ?>" aria-label="Buka <?= $due_today ?> barang yang kembali hari ini">
        <span aria-hidden="true"><?= ui_icon('calendar','status-svg') ?></span><b><?= $due_today ?></b><small>Kembali hari ini</small>
      </a>
      <a class="mobile-status-item damage" href="<?= base_url('/petugas/damages?status=reported') ?>" aria-label="Buka <?= $damage_reports ?> laporan rusak">
        <span aria-hidden="true"><?= ui_icon('warning','status-svg') ?></span><b><?= $damage_reports ?></b><small>Laporan rusak</small>
      </a>
      <a class="mobile-status-item accessory" href="<?= base_url('/petugas/accessories') ?>" aria-label="Buka <?= (int) ($accessory_pending_count ?? 0) ?> kelengkapan belum kembali; <?= (int) ($incomplete ?? 0) ?> unit ditahan">
        <span aria-hidden="true"><?= ui_icon('check','status-svg') ?></span><b><?= (int) ($accessory_pending_count ?? 0) ?></b><small>Kelengkapan kurang</small>
      </a>
    </div>
  </div>


  <div class="dashboard-workspace">
    <div class="dashboard-status-column">
      <section class="mobile-insights" aria-label="Ringkasan inventaris">
        <article class="insight-card status-insight">
          <div class="status-donut<?= $total > 0 ? '' : ' is-empty' ?>" style="--available-end:<?= $available_end ?>%;--borrowed-end:<?= $borrowed_end ?>%;--handling-end:<?= $total > 0 ? min(100, round((($available + $borrowed + $in_handling) / $total) * 100, 2)) : 0 ?>%"><b><?= $total ?></b><small>Total unit</small></div>
          <div class="status-legend">
            <h2>Status barang</h2>
            <small class="status-caption"><?= $total ?> unit tercatat</small>
            <span><i class="ready"></i>Tersedia <b><?= $available ?></b></span>
            <span><i class="loaned"></i>Dipinjam <b><?= $borrowed ?></b></span>
            <span><i class="handling"></i>Perlu penanganan <b><?= $in_handling ?></b></span>
            <span><i class="incomplete"></i>Menunggu kelengkapan <b><?= (int) ($incomplete ?? 0) ?></b></span>
          </div>
        </article>
      </section>

      <section class="recent-activity" aria-labelledby="recentActivityTitle">
        <div class="activity-heading">
          <div><span class="eyebrow">Riwayat</span><h2 id="recentActivityTitle">Aktivitas terbaru</h2></div>
          <a class="link" href="<?= base_url('/petugas/activity') ?>">Lihat semua</a>
        </div>
        <div class="activity-list">
          <?php if (! $recent_activities): ?>
          <?= view('partials/empty', ['text' => 'Belum ada aktivitas tercatat.', 'icon' => 'clock', 'flush' => true]) ?>
          <?php endif; ?>
          <?php foreach ($recent_activities as $a): ?>
          <button class="activity-row" type="button">
            <span class="activity-icon <?= str_contains($a->action, 'kerusakan') ? 'damage' : (str_contains($a->action, 'pengembalian') || str_contains($a->action, 'kembali') ? 'return' : 'loan') ?>"><?= str_contains($a->action, 'kerusakan') ? ui_icon('warning','activity-svg') : ui_icon('loan','activity-svg') ?></span>
            <span><b><?= esc($a->object ?? 'Aktivitas') ?> · <?= esc(str_replace('_', ' ', ucfirst($a->action))) ?></b><small><?= esc($a->user_name) ?> · <?= esc(tgl_id($a->created_at)) ?></small></span>
            <i>›</i>
          </button>
          <?php endforeach; ?>
        </div>
      </section>
    </div>

    <section class="priority-workspace">
      <div class="section-head">
        <div><span class="eyebrow">Prioritas</span><h2>Tugas hari ini</h2></div>
        <a class="btn compact-link" href="<?= base_url('/petugas/loans') ?>">Lihat semua tugas</a>
      </div>
      <div class="field-jobs">
        <?php foreach ($active_loans as $l): ?>
        <article class="card task-card" role="button" tabindex="0" onclick="window.location='<?= base_url('/petugas/loans/' . $l->id) ?>'">
          <div class="task-head">
            <div><span class="badge <?= loan_status_label($l->returned_at, $l->due_at, true) ?>"><?= loan_status_label($l->returned_at, $l->due_at) ?></span><span class="task-code"><?= esc($l->item_code) ?></span></div>
            <span class="task-chevron" aria-hidden="true">›</span>
          </div>
          <h3><?= esc($l->item_name) ?></h3>
          <small class="muted"><?= esc($l->borrower_name) ?> · <?= $l->borrower_type === 'teacher' ? 'Guru' : 'Siswa' ?></small>
          <div class="task-foot"><span>Batas kembali</span><b><?= esc(tgl_id($l->due_at, true)) ?></b></div>
        </article>
        <?php endforeach; ?>
        <?php foreach ($pending_handling as $h): ?>
        <?php $handlingUrl = $h->report_id ? base_url('/petugas/handling/' . $h->report_id) : base_url('/petugas/items/' . $h->item_id); ?>
        <article class="card task-card" role="button" tabindex="0" onclick="window.location='<?= $handlingUrl ?>'">
          <div class="task-head">
            <div><span class="badge red"><?= $h->report_status === 'in_handling' ? 'Sedang ditangani' : 'Belum ditangani' ?></span><span class="task-code"><?= esc($h->item_code) ?></span></div>
            <span class="task-chevron" aria-hidden="true">›</span>
          </div>
          <h3><?= esc($h->item_name) ?></h3>
          <small class="muted"><?= esc($h->description ?: 'Perlu penanganan.') ?></small>
          <div class="task-foot"><span>Dilaporkan</span><b><?= $h->discovered_at ? esc(tgl_id($h->discovered_at)) : '—' ?></b></div>
        </article>
        <?php endforeach; ?>
        <?php if (($accessory_pending_count ?? 0) > 0): ?>
        <a class="card task-card accessory-task" href="<?= base_url('/petugas/accessories') ?>">
          <div class="task-head"><div><span class="badge amber">Perlu tindak lanjut</span><span class="task-code">Serah terima</span></div><span class="task-chevron" aria-hidden="true">›</span></div>
          <h3>Kelengkapan belum kembali</h3>
          <small class="muted"><?= (int) $accessory_pending_count ?> jenis kelengkapan perlu diperiksa dan diselesaikan.</small>
          <div class="task-foot"><span>Unit tertahan</span><b><?= (int) ($incomplete ?? 0) ?> unit</b></div>
        </a>
        <?php endif; ?>
        <?php if (! $active_loans && ! $pending_handling && ! ($accessory_pending_count ?? 0)): ?>
        <?= view('partials/empty', ['text' => 'Tidak ada tugas prioritas hari ini. Semua berjalan lancar.', 'icon' => 'check']) ?>
        <?php endif; ?>
      </div>
    </section>
  </div>
</div>
<?= view('layouts/footer') ?>
