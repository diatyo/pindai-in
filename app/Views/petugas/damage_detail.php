<?php $readOnly = $readOnly ?? false; $itemDetailUrl = $itemDetailUrl ?? base_url('/petugas/items/' . $report->item_id); ?>
<?= view('layouts/header', ['title' => 'Detail Kerusakan', 'subtitle' => 'Laporan kerusakan dan tindak lanjut.']) ?>
<div class="page form-desktop">
  <?php if (! $readOnly && ! empty($report->loan_group_id)): ?>
  <a class="btn" href="<?= base_url('/petugas/loans/groups/' . $report->loan_group_id) ?>">Kembali ke kelompok</a>
  <?php endif; ?>
  <div class="page-title">
    <div><span class="eyebrow"><?= esc($report->report_number) ?></span><h1>Detail Kerusakan</h1></div>
    <span class="badge <?= damage_status_badge($report->status) ?>"><?= damage_status_label($report->status) ?></span>
  </div>

  <?php if ($report->damage_photo): ?>
  <figure class="damage-photo mt-14">
    <button type="button" class="damage-photo-btn" data-photo-zoom="<?= esc(base_url($report->damage_photo), 'attr') ?>" data-photo-title="<?= esc('Foto kerusakan · ' . $report->report_number, 'attr') ?>" aria-label="Perbesar foto kerusakan">
      <img src="<?= esc(base_url($report->damage_photo), 'attr') ?>" alt="Foto kerusakan <?= esc($report->report_number) ?>" loading="lazy">
    </button>
  </figure>
  <?php endif; ?>

  <article class="card mt-14">
    <div class="kv"><span>Unit</span><b><a class="link" href="<?= esc($itemDetailUrl, 'attr') ?>"><?= esc($report->item_code) ?> · <?= esc($report->item_name) ?> <?= esc($report->model ?? '') ?></a></b></div>
    <div class="kv"><span>Dilaporkan</span><b><?= esc(tgl_id($report->discovered_at, true)) ?></b></div>
    <div class="kv"><span>Pelapor</span><b><?= esc($report->reported_by_name ?? '—') ?></b></div>
    <div class="kv"><span>Kondisi</span><b><?= condition_label($report->damage_level) ?></b></div>
    <div class="kv"><span>Deskripsi</span><b><?= esc($report->description) ?></b></div>
    <div class="kv"><span>Catatan</span><b><?= esc($report->notes ?? '—') ?></b></div>
    <?php if ($report->loan_number): ?>
    <div class="kv"><span>Terhubung transaksi</span><b><?= esc($report->loan_number) ?> · <?= esc($report->borrower_name ?? '') ?></b></div>
    <?php endif; ?>
  </article>

  <div class="section-head"><h2>Riwayat keterangan</h2>
    <?php if (! $readOnly && ($report->status === 'reported' || $report->status === 'in_handling')): ?>
    <a class="btn gold" href="<?= base_url('/petugas/handling/create/' . $report->id) ?>">Tambah Keterangan</a>
    <?php endif; ?>
  </div>
  <?php if ($actions): ?>
  <article class="card timeline">
    <?php foreach ($actions as $a): ?>
    <div class="event"><b><?= esc($a->action_description) ?></b><small><?= esc(tgl_id($a->action_date)) ?> · <?= esc($a->executor_name) ?> · <?= $a->status === 'completed' ? 'Selesai' : ($a->status === 'unrepairable' ? 'Tidak dapat diperbaiki' : 'Berjalan') ?></small></div>
    <?php endforeach; ?>
  </article>
  <?php else: ?>
  <?= view('partials/empty', ['text' => 'Belum ada keterangan penanganan.', 'icon' => 'check']) ?>
  <?php endif; ?>
</div>
<?php if ($report->damage_photo): ?>
<div class="modal photo-viewer" id="photoViewer" aria-hidden="true">
  <div class="modal-box photo-viewer-box" role="dialog" aria-modal="true" aria-labelledby="photoViewerTitle">
    <div class="photo-viewer-head">
      <b id="photoViewerTitle">Preview foto</b>
      <button class="round" type="button" data-close aria-label="Tutup preview">&times;</button>
    </div>
    <div class="photo-viewer-stage" id="photoViewerStage">
      <img class="photo-viewer-image" id="photoViewerImage" alt="Preview foto diperbesar" draggable="false">
      <div class="photo-viewer-controls" aria-label="Kontrol zoom foto">
        <button class="photo-viewer-control" type="button" data-photo-action="zoom-out" aria-label="Perkecil foto">&minus;</button>
        <output id="photoViewerZoom">100%</output>
        <button class="photo-viewer-control" type="button" data-photo-action="zoom-in" aria-label="Perbesar foto">+</button>
        <button class="photo-viewer-control" type="button" data-photo-action="reset" aria-label="Reset tampilan" title="Reset tampilan">&#8634;</button>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>
<?= view('layouts/footer') ?>
