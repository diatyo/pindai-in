<?= view('layouts/header', ['title' => 'Riwayat Unit ' . $item->item_code, 'subtitle' => 'Timeline perubahan status dan transaksi.']) ?>
<div class="page form-desktop">
  <?= view('partials/school_letterhead', ['printOnly' => true]) ?>
  <div class="page-title">
    <div><span class="eyebrow"><?= esc($item->item_code) ?></span><h1>Riwayat Unit</h1></div>
    <button class="btn" onclick="window.print()">Cetak</button>
  </div>

  <article class="card timeline mt-18">
    <?php foreach ($histories as $h): ?>
    <div class="event">
      <b><?= esc(item_status_label($h->status_to)) ?> — <?= esc($h->note ?? '') ?></b>
      <small><?= esc(tgl_id($h->created_at, true)) ?> · <?= esc($h->changed_by_name ?? 'Sistem') ?></small>
    </div>
    <?php endforeach; ?>
    <?php if (! $histories): ?><?= view('partials/empty', ['text' => 'Belum ada riwayat status.', 'icon' => 'clock', 'flush' => true]) ?><?php endif; ?>
  </article>

  <div class="section-head"><h2>Transaksi peminjaman</h2></div>
  <div class="table-card">
    <div class="mobile-list">
      <?php foreach ($loans as $l): ?>
      <article class="mobile-record">
        <div class="mobile-record-head">
          <div><h3><?= esc($l->loan_number) ?></h3><p class="muted"><?= esc($l->borrower_name) ?> · <?= $l->borrower_type === 'teacher' ? 'Guru' : 'Siswa' ?></p></div>
          <?php if (! empty($l->return_photo)): ?><button type="button" class="history-photo-thumb" data-photo-zoom="<?= esc(base_url($l->return_photo), 'attr') ?>" data-photo-title="<?= esc('Foto pengembalian · ' . $l->loan_number, 'attr') ?>" aria-label="Perbesar foto pengembalian <?= esc($l->loan_number) ?>"><img src="<?= base_url($l->return_photo) ?>" alt="Foto pengembalian <?= esc($l->loan_number) ?>" loading="lazy"></button><?php endif; ?>
        </div>
        <div class="mobile-record-meta">
          <div><small>Pinjam</small><b><?= esc(tgl_id($l->borrowed_at)) ?></b></div>
          <div><small>Kembali</small><b><?= esc(tgl_id($l->returned_at)) ?></b></div>
          <div><small>Kondisi awal</small><b><?= condition_label($l->condition_out) ?> · <?= $l->completeness_out ? ($l->completeness_out === 'lengkap' ? 'Lengkap' : 'Tidak lengkap') : '—' ?></b></div>
          <div><small>Kondisi akhir</small><b><?= $l->condition_in ? condition_label($l->condition_in) : '—' ?> · <?= $l->completeness_in ? ($l->completeness_in === 'lengkap' ? 'Lengkap' : 'Tidak lengkap') : '—' ?></b></div>
        </div>
        <div class="mobile-record-foot mobile-record-foot-end"><a class="link" href="<?= base_url('/petugas/loans/' . $l->id) ?>">Detail ›</a></div>
      </article>
      <?php endforeach; ?>
      <?php if (! $loans): ?><?= view('partials/empty', ['text' => 'Belum ada transaksi.', 'icon' => 'loan']) ?><?php endif; ?>
    </div>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>Nomor</th><th>Peminjam</th><th>Pinjam</th><th>Kembali</th><th>Kondisi awal</th><th>Kondisi akhir</th><th>Foto kembali</th></tr></thead>
        <tbody>
          <?php foreach ($loans as $l): ?>
          <tr>
            <td><a class="link" href="<?= base_url('/petugas/loans/' . $l->id) ?>"><?= esc($l->loan_number) ?></a></td>
            <td><?= esc($l->borrower_name) ?> · <?= $l->borrower_type === 'teacher' ? 'Guru' : 'Siswa' ?></td>
            <td><?= esc(tgl_id($l->borrowed_at)) ?></td>
            <td><?= esc(tgl_id($l->returned_at)) ?></td>
            <td><?= condition_label($l->condition_out) ?><br><small class="muted"><?= $l->completeness_out ? ($l->completeness_out === 'lengkap' ? 'Lengkap' : 'Tidak lengkap') : '—' ?></small></td>
            <td><?= $l->condition_in ? condition_label($l->condition_in) : '—' ?><br><small class="muted"><?= $l->completeness_in ? ($l->completeness_in === 'lengkap' ? 'Lengkap' : 'Tidak lengkap') : '—' ?></small></td>
            <td><?php if (! empty($l->return_photo)): ?><button type="button" class="history-photo-thumb" data-photo-zoom="<?= esc(base_url($l->return_photo), 'attr') ?>" data-photo-title="<?= esc('Foto pengembalian · ' . $l->loan_number, 'attr') ?>" aria-label="Perbesar foto pengembalian <?= esc($l->loan_number) ?>"><img src="<?= base_url($l->return_photo) ?>" alt="Foto pengembalian <?= esc($l->loan_number) ?>" loading="lazy"></button><?php else: ?><span class="muted">—</span><?php endif; ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if (! $loans): ?><tr><td colspan="7" class="muted">Belum ada transaksi.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?= view('partials/report_signature', ['printOnly' => true]) ?>
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
