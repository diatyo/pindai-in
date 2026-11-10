<?php $readOnly = $readOnly ?? false; $itemDetailUrl = $itemDetailUrl ?? base_url('/petugas/items/' . $loan->item_id); ?>
<?= view('layouts/header', ['title' => 'Detail Peminjaman', 'subtitle' => 'Rincian transaksi peminjaman unit.']) ?>
<div class="page form-desktop" id="loanDetail">
  <?php if (! empty($listBackUrl)): ?>
  <a class="inventory-mobile inventory-back" href="<?= esc($listBackUrl) ?>"><span aria-hidden="true">‹</span> Kembali ke Peminjaman</a>
  <?php endif; ?>
  <div class="page-title">
    <div><span class="eyebrow"><?= esc($loan->loan_number) ?></span><h1>Detail Peminjaman</h1></div>
    <span class="badge <?= loan_status_label($loan->returned_at, $loan->due_at, true) ?>"><?= loan_status_label($loan->returned_at, $loan->due_at) ?></span>
  </div>
  <?php if (! $readOnly && ! empty($loan->loan_group_id)): ?>
  <a class="btn btn-icon" href="<?= base_url('/petugas/loans/groups/' . $loan->loan_group_id) ?>"><?= ui_icon('box', 'btn-svg') ?> Buka peminjaman kelompok</a>
  <?php endif; ?>

  <div class="loan-item-photo-wrap mt-18">
    <?php if (! empty($loan->physical_photo)): ?>
      <button type="button" class="loan-item-photo" data-photo-zoom="<?= esc(base_url($loan->physical_photo), 'attr') ?>" data-photo-title="<?= esc('Foto fisik penuh · ' . $loan->item_code, 'attr') ?>" aria-label="Perbesar foto fisik barang">
        <img src="<?= esc(base_url($loan->physical_photo), 'attr') ?>" alt="Foto fisik penuh <?= esc($loan->item_code) ?>">
        <span><b>Foto fisik penuh</b><small>Ketuk untuk memperbesar</small></span>
      </button>
    <?php else: ?>
      <div class="loan-item-photo is-empty"><span><b>Foto fisik penuh</b><small>Belum tersedia</small></span></div>
    <?php endif; ?>
  </div>

  <article class="card mt-18">
    <div class="kv"><span>Unit</span><b><a class="link" href="<?= esc($itemDetailUrl, 'attr') ?>"><?= esc($loan->item_name) ?></a><br><small class="muted"><?= esc($loan->item_code) ?></small></b></div>
    <div class="kv"><span>Peminjam</span><b><?= esc($loan->borrower_name) ?> · <?= $loan->borrower_type === 'teacher' ? 'Guru' : 'Siswa' ?></b></div>
    <div class="kv"><span>Identitas</span><b><?= esc($loan->identity_number ?? '—') ?></b></div>
    <div class="kv"><span>Tanggal pinjam</span><b><?= esc(tgl_id($loan->borrowed_at, true)) ?></b></div>
    <div class="kv"><span>Batas kembali</span><b><?= esc(tgl_id($loan->due_at, true)) ?></b></div>
    <div class="kv"><span>Lokasi penggunaan</span><b><?= esc($loan->usage_location) ?></b></div>
    <div class="kv"><span>Keperluan</span><b><?= esc($loan->purpose) ?></b></div>
    <div class="kv"><span>Kondisi awal</span><b><?= condition_label($loan->condition_out) ?> · <?= $loan->completeness_out === 'lengkap' ? 'Lengkap' : 'Tidak lengkap' ?></b></div>
    <?php if ($loan->returned_at): ?>
    <div class="kv"><span>Dikembalikan</span><b><?= esc(tgl_id($loan->returned_at, true)) ?></b></div>
    <div class="kv"><span>Kondisi akhir</span><b><?= $loan->condition_in ? condition_label($loan->condition_in) : '—' ?> · <?= $loan->completeness_in ? ($loan->completeness_in === 'lengkap' ? 'Lengkap' : 'Tidak lengkap') : '—' ?></b></div>
    <div class="kv"><span>Catatan</span><b><?= esc($loan->return_notes ?? '—') ?></b></div>
    <div class="kv loan-return-photo-row">
      <span>Foto pengembalian</span>
      <?php if (! empty($loan->return_photo)): ?>
        <button type="button" class="history-photo-thumb" data-photo-zoom="<?= esc(base_url($loan->return_photo), 'attr') ?>" data-photo-title="<?= esc('Foto pengembalian · ' . $loan->loan_number, 'attr') ?>" aria-label="Perbesar foto pengembalian">
          <img src="<?= esc(base_url($loan->return_photo), 'attr') ?>" alt="Foto pengembalian <?= esc($loan->loan_number) ?>" loading="lazy">
        </button>
      <?php else: ?>
        <b>—</b>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </article>

  <?= view('partials/accessory_loan_detail', ['readOnly' => $readOnly]) ?>
  <?php if (! $readOnly && ! $loan->returned_at): ?>
  <a class="btn gold full mt-14" href="<?= base_url('/petugas/loans/' . $loan->id . '/return') ?>">Proses Pengembalian</a>
  <?php endif; ?>
</div>
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
<?= view('layouts/footer') ?>
