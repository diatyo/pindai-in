<?= view('layouts/header', ['title' => 'Detail Unit ' . $item->item_code, 'subtitle' => 'Rincian unit (baca saja).']) ?>
<div class="page">
  <div class="page-title responsive-page-title">
    <div><span class="eyebrow">Unit fisik</span><h1><?= esc($item->item_code) ?></h1><span class="muted"><?= esc($item->item_name) ?> <?= esc($item->model ?? '') ?></span></div>
  </div>

  <div class="detail-layout mt-18">
    <div>
      <article class="card">
        <div class="kv"><span>Status / kondisi</span><b><span class="badge <?= item_status_badge($item->status) ?>"><?= item_status_label($item->status) ?></span> · <?= condition_label($item->condition) ?></b></div>
        <div class="kv"><span>Kategori</span><b><?= esc($item->category) ?></b></div>
        <div class="kv"><span>Nomor seri</span><b><?= esc(\App\Models\ItemModel::serialLabel($item)) ?></b></div>
        <div class="kv"><span>Lokasi</span><b><?= esc($item->location) ?></b></div>
        <div class="kv"><span>Tanggal pengadaan</span><b><?= esc(tgl_id($item->acquisition_date)) ?></b></div>
        <div class="kv"><span>Pemakai terakhir</span><b><?= $last_borrower ? esc($last_borrower->borrower_name . ' · ' . ($last_borrower->borrower_type === 'teacher' ? 'Guru' : 'Siswa')) : '—' ?></b></div>
      </article>
      <div class="section-head"><h2>Riwayat unit</h2></div>
      <article class="card timeline">
        <?php foreach ($histories as $h): ?>
        <div class="event"><b><?= esc(item_status_label($h->status_to)) ?> — <?= esc($h->note ?? '') ?></b><small><?= esc(tgl_id($h->created_at, true)) ?> · <?= esc($h->changed_by_name ?? 'Sistem') ?></small></div>
        <?php endforeach; ?>
        <?php if (! $histories): ?><?= view('partials/empty', ['text' => 'Belum ada riwayat.', 'icon' => 'clock', 'flush' => true]) ?><?php endif; ?>
      </article>
    </div>
    <aside>
      <div class="qrbox">
        <img src="<?= esc(qr_image_src($item->item_code, $item->qr_filename ?? null), 'attr') ?>" alt="QR" style="width:100%;border-radius:10px;background:#fff;padding:8px">
        <b><?= esc($item->item_code) ?></b>
        <p class="muted">Pindai.in by SMP NEGERI 1 TANJUNGPINANG</p>
        <small class="muted">QR ditampilkan sebagai identitas. Tidak tersedia aksi buat ulang.</small>
      </div>
    </aside>
  </div>
</div>
<?= view('layouts/footer') ?>
