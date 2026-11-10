<?= view('layouts/header', ['title' => 'Detail Peminjaman', 'subtitle' => 'Rincian transaksi peminjaman Anda.']) ?>
<div class="page form-desktop" id="loans">
  <div class="page-title responsive-page-title">
    <div><span class="eyebrow"><?= esc($loan->loan_number) ?></span><h1>Detail Peminjaman</h1></div>
    <span class="badge <?= loan_status_label($loan->returned_at, $loan->due_at, true) ?>"><?= loan_status_label($loan->returned_at, $loan->due_at) ?></span>
  </div>

  <div class="borrower-item-photo-wrap mt-18">
    <?php if (! empty($loan->physical_photo)): ?>
      <button type="button"
              class="borrower-item-photo"
              data-photo-zoom="<?= esc(base_url($loan->physical_photo), 'attr') ?>"
              data-photo-title="<?= esc('Foto fisik penuh · ' . $loan->item_code, 'attr') ?>"
              aria-label="Perbesar foto fisik barang">
        <img src="<?= esc(base_url($loan->physical_photo), 'attr') ?>"
             alt="Foto fisik penuh <?= esc($loan->item_code) ?>">
        <span><b>Foto fisik penuh</b><small>Ketuk untuk memperbesar</small></span>
      </button>
    <?php else: ?>
      <div class="borrower-item-photo is-empty">
        <span><b>Foto fisik penuh</b><small>Belum tersedia</small></span>
      </div>
    <?php endif; ?>
  </div>

  <article class="card mt-18 borrower-detail-card">
    <div class="kv"><span>Unit</span><b><?= esc($loan->item_code) ?> &middot; <?= esc($loan->item_name) ?> <?= esc($loan->model ?? '') ?></b></div>
    <div class="kv"><span>Peminjam</span><b><?= esc($loan->borrower_name) ?> &middot; <?= $loan->borrower_type === 'teacher' ? 'Guru' : 'Siswa' ?></b></div>
    <div class="kv"><span>Identitas</span><b><?= esc($loan->identity_number ?? '-') ?></b></div>
    <div class="kv"><span>Tanggal pinjam</span><b><?= esc(tgl_id($loan->borrowed_at, true)) ?></b></div>
    <div class="kv"><span>Batas kembali</span><b><?= esc(tgl_id($loan->due_at, true)) ?></b></div>
    <div class="kv"><span>Lokasi penggunaan</span><b><?= esc($loan->usage_location) ?></b></div>
    <div class="kv"><span>Keperluan</span><b><?= esc($loan->purpose ?? '-') ?></b></div>
    <div class="kv"><span>Kondisi awal</span><b><?= esc(condition_label($loan->condition_out)) ?></b></div>
    <div class="kv"><span>Dikembalikan</span><b><?= $loan->returned_at ? esc(tgl_id($loan->returned_at, true)) : 'Belum dikembalikan' ?></b></div>
    <div class="kv"><span>Kondisi akhir</span><b><?= $loan->condition_in ? esc(condition_label($loan->condition_in)) : '-' ?></b></div>
    <div class="kv"><span>Diproses oleh</span><b><?= esc($loan->processed_by_name ?? 'Sistem') ?></b></div>
    <div class="kv"><span>Catatan</span><b><?= esc($loan->return_notes ?? '-') ?></b></div>
  </article>

  <p class="muted mt-14">Portal peminjam bersifat baca saja. Pengembalian barang diproses oleh Pengurus Barang.</p>
  <div class="row mt-14"><a class="btn" href="<?= base_url('/peminjam/history') ?>">Kembali ke riwayat</a></div>
</div>

<div class="modal photo-viewer" id="photoViewer" aria-hidden="true">
  <div class="modal-box photo-viewer-box" role="dialog" aria-modal="true" aria-labelledby="photoViewerTitle">
    <div class="photo-viewer-head">
      <b id="photoViewerTitle">Pratinjau foto</b>
      <button class="round" type="button" data-close aria-label="Tutup pratinjau">&times;</button>
    </div>
    <div class="photo-viewer-stage" id="photoViewerStage">
      <img class="photo-viewer-image" id="photoViewerImage" alt="Pratinjau foto diperbesar" draggable="false">
      <div class="photo-viewer-controls" aria-label="Kontrol pembesaran foto">
        <button class="photo-viewer-control" type="button" data-photo-action="zoom-out" aria-label="Perkecil foto">&minus;</button>
        <output id="photoViewerZoom">100%</output>
        <button class="photo-viewer-control" type="button" data-photo-action="zoom-in" aria-label="Perbesar foto">+</button>
        <button class="photo-viewer-control" type="button" data-photo-action="reset" aria-label="Atur ulang tampilan" title="Atur ulang tampilan">&#8634;</button>
      </div>
    </div>
  </div>
</div>
<?= view('partials/accessory_loan_detail', ['readOnly' => true]) ?>
<?= view('layouts/footer') ?>
