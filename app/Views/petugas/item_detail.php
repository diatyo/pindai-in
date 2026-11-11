<?php
$readOnly = $readOnly ?? false;
$active_loan = $active_loan ?? null;
$isArchived = ! $item->is_active || $item->status === 'disabled';
$itemTitle = trim($item->item_name);
$qrDownloadUrl = base_url('/petugas/items/' . $item->id . '/qr-image');
$qrPrintUrl = base_url('/petugas/items/qr-labels?item_ids[]=' . $item->id);
$qrImageUrl = $readOnly
    ? qr_image_src($item->item_code, $item->qr_filename ?? null)
    : $qrDownloadUrl;
$brandModel = trim(($item->brand ?? '') . ' / ' . ($item->model ?? ''), ' /');
$lastBorrowerText = $last_borrower
    ? $last_borrower->borrower_name . ' · ' . ($last_borrower->borrower_type === 'teacher' ? 'Guru' : 'Siswa')
    : '—';
$pendingAccessories = $accessory_pending ?? [];
$pendingLoanIds = array_unique(array_column($pendingAccessories, 'loan_id'));
$pendingSummary = $item->status === 'incomplete'
    ? array_filter($pendingAccessories, static fn ($entry) => (bool) $entry->is_required)
    : $pendingAccessories;
$primaryAction = null;
$secondaryAction = null;
if (! $readOnly) {
    if ($active_loan) {
        $primaryAction = ['Proses Pengembalian', base_url('/petugas/loans/' . $active_loan->id . '/return')];
        $secondaryAction = ['Lihat Transaksi Aktif', base_url('/petugas/loans/' . $active_loan->id)];
    } elseif ($isArchived) {
        if (empty($listBackUrl)) {
            $secondaryAction = ['Lihat Arsip Barang', base_url('/petugas/items?status=archived')];
        }
    } elseif ($item->status === 'available') {
        $primaryAction = ['Mulai Peminjaman', base_url('/petugas/loans/create/' . $item->id)];
        $secondaryAction = ['Laporkan Kerusakan', base_url('/petugas/damages/create/' . $item->id)];
    } elseif ($item->status === 'incomplete') {
        $target = count($pendingLoanIds) === 1
            ? base_url('/petugas/loans/' . reset($pendingLoanIds) . '#loanAccessories')
            : ($pendingLoanIds ? '#detailAccessoryPending' : base_url('/petugas/accessories'));
        $primaryAction = ['Tindak Lanjuti', $target];
    } elseif (in_array($item->status, ['damaged', 'in_handling'], true)) {
        $primaryAction = ['Lihat Penanganan', base_url('/petugas/damages?item=' . $item->id)];
        if ($item->status === 'damaged' && ! empty($damage_reports[0]->id)) {
            $secondaryAction = ['Mulai Penanganan', base_url('/petugas/handling/create/' . $damage_reports[0]->id)];
        }
    }
}
$detailCss = <<<'CSS'
#detail .detail-header-actions{display:flex;align-items:center;justify-content:flex-end;flex-wrap:wrap;gap:8px}
#detail .detail-primary-action{
  display:inline-flex;align-items:center;justify-content:center;min-height:44px;
  background:var(--gold-soft);border-color:var(--gold);color:var(--brown-2);text-align:center
}
#detail .detail-identity-meta{display:flex;align-items:center;flex-wrap:wrap;gap:6px 10px;margin-top:7px}
#detail .detail-identity-meta .item-unit-code{margin:0}
#detail .detail-identity-meta .badge{white-space:normal}
#detail .detail-location{margin:6px 0 0;color:var(--muted);font-size:13px;overflow-wrap:anywhere}
#detail .detail-context{display:grid;gap:5px;margin:12px 0 0;padding:12px 14px;font-size:13px;line-height:1.5}
#detail .detail-context p{margin:0;overflow-wrap:anywhere}
#detail .detail-inventory{order:2;padding:0;margin-top:12px}
#detail .detail-inventory summary{
  display:flex;align-items:center;justify-content:space-between;gap:12px;
  min-height:48px;padding:14px 16px;font-weight:700;list-style:none;cursor:pointer
}
#detail .detail-inventory summary::-webkit-details-marker{display:none}
#detail .detail-inventory summary::after{
  content:'';width:7px;height:7px;margin-right:4px;
  border-right:2px solid var(--muted);border-bottom:2px solid var(--muted);transform:rotate(45deg)
}
#detail .detail-inventory[open] summary::after{transform:rotate(225deg)}
#detail .detail-inventory-values{padding:0 16px 8px}
#detail .detail-actions-menu{display:none;position:relative}
#detail .detail-actions-menu summary{display:flex;align-items:center;justify-content:center;list-style:none;cursor:pointer}
#detail .detail-actions-menu summary::-webkit-details-marker{display:none}
#detail .detail-actions-menu[open] .detail-actions-popover{
  position:absolute;z-index:10;top:calc(100% + 6px);left:0;
  padding:6px;border:1px solid var(--line);border-radius:12px;background:var(--paper);box-shadow:var(--shadow-sm)
}
#detailAccessoryPending{scroll-margin-top:84px}
#detail .detail-photos{display:grid;grid-template-columns:1fr 1fr;gap:12px}
#detail .detail-photo{position:relative;min-width:0;height:220px;padding:0;overflow:hidden;background:var(--cream-2);display:flex;align-items:center;justify-content:center}
#detail .detail-photo img{display:block;width:100%;height:100%;object-fit:cover;object-position:center}
#detail .detail-photo.is-zoomable{cursor:zoom-in}
#detail .detail-photo.is-zoomable:focus-visible{outline:3px solid rgba(201,154,66,.65);outline-offset:3px}
#detail .detail-photo-caption{position:absolute;left:10px;right:10px;bottom:10px;display:grid;padding:8px 10px;border-radius:11px;background:rgba(43,36,29,.82);color:#fff;text-align:left}
#detail .detail-photo-caption small{overflow:hidden;color:#eee4da;text-overflow:ellipsis;white-space:nowrap}
#detail .detail-photo-empty{display:grid;width:100%;height:100%;place-content:center;text-align:center;color:var(--muted)}
#detail .qrbox{background:var(--glass);border:1px solid var(--surface-border);box-shadow:var(--shadow-sm);backdrop-filter:blur(12px)}
#detail .qrbox>img{display:block;width:100%;aspect-ratio:1;object-fit:contain;border-radius:10px;background:#fff;padding:8px}
#detail .detail-qr-preview{display:none}
.detail-qr-dialog{
  width:min(calc(100% - 32px),360px);max-height:calc(100dvh - 32px);
  margin:auto;padding:18px;border:1px solid var(--line);border-radius:20px;
  background:var(--paper);color:var(--ink);box-shadow:var(--shadow)
}
.detail-qr-dialog::backdrop{background:rgba(43,36,29,.66)}
.detail-qr-dialog header{display:flex;align-items:center;justify-content:space-between;gap:10px}
.detail-qr-dialog h2{margin:0;font-size:18px}
.detail-qr-dialog header p{margin:4px 0 0}
.detail-qr-dialog .round{min-width:44px;min-height:44px}
.detail-qr-image{
  display:block;width:min(100%,240px);aspect-ratio:1;object-fit:contain;
  margin:16px auto;padding:8px;border-radius:12px;background:#fff
}
.detail-qr-actions{display:flex;flex-wrap:wrap;gap:8px}
.detail-qr-actions .btn{flex:1 0 max-content;min-height:44px;text-align:center}
#detail .unit-action-card .btn{display:flex;align-items:center;justify-content:center;text-align:center}
#detail .unit-action-card .btn.damage-action{color:var(--red)}
#detail .timeline .event.has-photo{display:grid;grid-template-columns:minmax(0,1fr) 54px;align-items:center;gap:12px}
#detail .timeline .event-copy{min-width:0}
#detail .timeline-photo-thumb{display:block;width:54px;height:54px;padding:0;overflow:hidden;border:1px solid var(--surface-border);border-radius:10px;background:var(--cream-2);cursor:zoom-in}
#detail .timeline-photo-thumb:hover{border-color:rgba(201,154,66,.55)}
#detail .timeline-photo-thumb:focus-visible{outline:2px solid var(--gold-2);outline-offset:2px}
#detail .timeline-photo-thumb img{display:block;width:100%;height:100%;object-fit:cover}
.photo-viewer{z-index:40;align-items:center;justify-content:center}
.photo-viewer-box{display:grid;width:min(94vw,900px);max-width:900px;max-height:92dvh;padding:12px;gap:10px}
.photo-viewer-head{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:2px 4px}
.photo-viewer-head b{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.photo-viewer-controls{position:absolute;left:50%;bottom:max(10px,env(safe-area-inset-bottom));z-index:3;display:flex;align-items:center;justify-content:center;gap:2px;padding:4px;transform:translateX(-50%);border:1px solid rgba(255,255,255,.14);border-radius:15px;background:rgba(35,29,24,.78);box-shadow:0 6px 20px rgba(43,36,29,.24);backdrop-filter:blur(10px)}
.photo-viewer-control{display:grid;width:36px;height:36px;padding:0;place-items:center;border:0;border-radius:11px;background:transparent;color:#fff;font:700 18px/1 Nunito}
.photo-viewer-control:hover,.photo-viewer-control:active{background:rgba(255,255,255,.14)}
.photo-viewer-control:disabled{cursor:default;opacity:.28}
.photo-viewer-control:disabled:hover{background:transparent}
.photo-viewer-controls output{min-width:43px;text-align:center;color:#fff;font:700 11px/1 'DM Sans'}
.photo-viewer-stage{position:relative;display:flex;align-items:center;justify-content:center;width:100%;height:min(78dvh,760px);overflow:hidden;border-radius:16px;background:var(--cream-2);touch-action:none;user-select:none}
.photo-viewer-stage.is-zoomed{cursor:grab}
.photo-viewer-stage.is-dragging{cursor:grabbing}
.photo-viewer-image{display:block;width:auto;height:auto;max-width:100%;max-height:100%;object-fit:contain;transform-origin:center;will-change:transform;-webkit-user-drag:none}
@media(max-width:600px){
  #detail .detail-header-actions:has(.detail-primary-action):has(.detail-archive-direct){
    display:grid;
    grid-template-columns:max-content max-content minmax(0,1fr);
    gap:6px;
    width:100%;
  }
  #detail .detail-header-actions .btn{
    min-height:44px;
    min-width:0;
    padding-inline:8px;
    font-size:0.75rem;
    line-height:1.25;
  }
}
@media(max-width:22.5em){
  #detail .detail-archive-direct{display:none}
  #detail .detail-actions-menu{display:block}
}
@media(max-width:1023px){
  #detail .detail-heading{display:grid;grid-template-columns:minmax(0,1fr) auto;align-items:start;gap:12px;width:100%}
  #detail .detail-qr-preview{
    display:grid;place-items:center;width:68px;aspect-ratio:1;padding:5px;
    border:1px solid var(--line);border-radius:12px;background:#fff;cursor:pointer
  }
  #detail .detail-qr-preview img{display:block;width:100%;height:100%;object-fit:contain}
  .petugas-app #detail .detail-layout .qrbox{display:none}
  #detail .detail-photos{gap:8px}
  #detail .detail-photo{height:105px}
  #detail .detail-photo-caption{left:6px;right:6px;bottom:6px;padding:5px 7px;border-radius:8px;font-size:9px}
  #detail .detail-photo-caption small{font-size:8px}
}
@media(min-width:1024px){
  #detail .detail-layout>aside{align-self:start;display:grid;gap:16px}
  #detail .unit-action-card{margin:0}
}
CSS;
?>
<?= view('layouts/header', ['title' => 'Detail Barang', 'subtitle' => $item->item_code . ' · Identitas, status, dan riwayat unit.', 'extraCss' => $detailCss]) ?>
<div class='page' id='detail'>
  <?php if (! empty($listBackUrl)): ?>
  <a class="inventory-mobile inventory-back" href="<?= esc($listBackUrl) ?>"><span aria-hidden="true">‹</span> Kembali ke Data Barang</a>
  <?php endif; ?>
  <div class='page-title'>
    <div class="detail-heading">
      <div>
        <span class='eyebrow'>Detail barang</span>
        <h1 class="item-name-primary"><?= esc($itemTitle) ?></h1>
        <div class="detail-identity-meta">
          <p class="item-unit-code">Kode unit · <b><?= esc($item->item_code) ?></b></p>
          <span class="badge <?= item_status_badge($item->status) ?>"><?= esc(item_status_label($item->status)) ?></span>
        </div>
        <p class="detail-location"><?= esc(condition_label($item->condition)) ?> · <?= esc($item->location) ?></p>
      </div>
      <button class="detail-qr-preview" type="button" aria-label="Perbesar QR barang" aria-haspopup="dialog" aria-controls="itemQrDialog" onclick="document.getElementById('itemQrDialog').showModal()">
        <img src="<?= esc($qrImageUrl, 'attr') ?>" alt="" width="58" height="58">
      </button>
    </div>
    <?php if (! $readOnly): ?>
    <div class="detail-header-actions">
      <?php if (! $isArchived): ?>
      <button id="detailArchive" class="btn btn-icon detail-archive-direct" type="button" onclick="confirmAction('Arsipkan barang?', 'Barang tidak dapat dipinjam setelah diarsipkan. Riwayat dan transaksi yang masih berjalan tetap tersimpan.', '<?= base_url('/petugas/items/' . $item->id . '/archive') ?>', 'Arsipkan')"><?= ui_icon('archive', 'btn-svg') ?> Arsipkan</button>
      <details class="detail-actions-menu">
        <summary class="btn">Lainnya</summary>
        <div class="detail-actions-popover">
          <button class="btn" type="button" onclick="this.closest('details').open = false; document.getElementById('detailArchive').click()">Arsipkan</button>
        </div>
      </details>
      <?php else: ?>
      <button class="btn btn-icon<?= $primaryAction ? '' : ' gold' ?>" type="button" onclick="confirmAction('Aktifkan kembali barang?', 'Pastikan barang sudah ditemukan atau kembali layak digunakan. Status ditentukan dari transaksi, kerusakan, dan kondisi terkini.', '<?= base_url('/petugas/items/' . $item->id . '/restore') ?>', 'Aktifkan kembali')"><?= ui_icon('check', 'btn-svg') ?> Aktifkan kembali</button>
      <?php endif; ?>
      <a class="btn" href="<?= base_url('/petugas/items/' . $item->id . '/edit') ?>">Ubah Data</a>
      <?php if ($primaryAction): ?>
      <a class="btn gold detail-primary-action" href="<?= esc($primaryAction[1], 'attr') ?>"><?= esc($primaryAction[0]) ?></a>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </div>

  <?php if ($active_loan || $pendingSummary): ?>
  <section class="card detail-context" aria-label="Ringkasan transaksi barang">
    <?php if ($active_loan): ?>
    <p><b>Peminjam aktif</b> · <?= esc($active_borrower->name ?? $active_loan->borrower_name ?? '—') ?></p>
    <p><b>Batas kembali</b> · <?= esc(tgl_id($active_loan->due_at ?? null, true)) ?></p>
    <?php endif; ?>
    <?php if ($pendingSummary): ?>
    <p><b>Kelengkapan yang belum kembali</b></p>
    <?php foreach (array_slice($pendingSummary, 0, 2) as $entry): ?>
    <p><?= esc($entry->name) ?> · Kurang <?= (int) $entry->quantity - (int) $entry->quantity_returned ?></p>
    <?php endforeach; ?>
    <?php if (count($pendingSummary) > 2): ?><a href="#detailAccessoryPending">Lihat <?= count($pendingSummary) - 2 ?> kelengkapan lainnya</a><?php endif; ?>
    <?php endif; ?>
  </section>
  <?php endif; ?>

  <div class='detail-layout mt-18'>
    <div>
      <div class='photos detail-photos'>
        <div class='photo detail-photo<?= $item->physical_photo ? ' is-zoomable' : '' ?>' <?= $item->physical_photo ? 'data-photo-zoom=\'' . esc(base_url($item->physical_photo), 'attr') . '\' data-photo-title=\'' . esc('Foto fisik penuh · ' . $item->item_code, 'attr') . '\' role=\'button\' tabindex=\'0\'' : '' ?>>
          <?php if ($item->physical_photo): ?>
            <img src='<?= base_url($item->physical_photo) ?>' alt='Foto fisik penuh <?= esc($item->item_code) ?>'>
            <span class='detail-photo-caption'><b>Foto fisik penuh</b><small><?= esc('Foto fisik ' . $item->item_code) ?></small></span>
          <?php else: ?>
            <div class='detail-photo-empty'><b>Foto fisik penuh</b><small>Belum tersedia</small></div>
          <?php endif; ?>
        </div>
        <div class='photo detail-photo<?= $item->serial_photo ? ' is-zoomable' : '' ?>' <?= $item->serial_photo ? 'data-photo-zoom=\'' . esc(base_url($item->serial_photo), 'attr') . '\' data-photo-title=\'' . esc('Foto nomor seri · ' . $item->item_code, 'attr') . '\' role=\'button\' tabindex=\'0\'' : '' ?>>
          <?php if ($item->serial_photo): ?>
            <img src='<?= base_url($item->serial_photo) ?>' alt='Foto nomor seri <?= esc($item->item_code) ?>'>
            <span class='detail-photo-caption'><b>Foto nomor seri</b><small><?= esc($item->serial_number ?: $item->item_code) ?></small></span>
          <?php else: ?>
            <div class='detail-photo-empty'><b>Foto nomor seri</b><small><?= esc(($item->serial_status ?? 'known') === 'known' ? 'Belum tersedia' : \App\Models\ItemModel::serialLabel($item)) ?></small></div>
          <?php endif; ?>
        </div>
      </div>

      <details class="card detail-inventory">
        <summary>Rincian inventaris</summary>
        <div class="detail-inventory-values">
          <div class='kv'><span>Kategori</span><b><?= esc($item->category) ?></b></div>
          <div class='kv'><span>Merek / model</span><b><?= esc($brandModel ?: '—') ?></b></div>
          <div class='kv'><span>Nomor seri</span><b><?= esc(\App\Models\ItemModel::serialLabel($item)) ?></b></div>
          <div class='kv'><span>Tanggal pengadaan</span><b><?= esc(tgl_id($item->acquisition_date)) ?></b></div>
          <div class='kv'><span>Pemakai terakhir</span><b><?= esc($lastBorrowerText) ?></b></div>
        </div>
      </details>

      <article class="card accessory-section">
        <h2>Kelengkapan bawaan</h2>
        <?php $unitAccessories = $accessories ?? (new \App\Libraries\AccessoryChecklist())->template((int) $item->id); ?>
        <?php foreach ($unitAccessories as $accessory): ?>
          <div class="accessory-detail-row"><b><?= esc($accessory['name']) ?> × <?= (int) $accessory['quantity'] ?></b><p class="muted"><?= $accessory['is_required'] ? 'Wajib menyertai unit' : 'Opsional disertakan' ?></p></div>
        <?php endforeach; ?>
        <?php if (! $unitAccessories): ?><p class="muted accessory-help">Belum ada daftar kelengkapan bawaan.</p><?php endif; ?>
        <?php if ($pendingAccessories): ?>
          <div id="detailAccessoryPending">
            <p class="accessory-help"><b>Ada kelengkapan yang belum diselesaikan.</b></p>
            <?php foreach ($pendingAccessories as $pendingAccessory): ?><p><?= esc($pendingAccessory->name) ?>: kurang <?= (int) $pendingAccessory->quantity - (int) $pendingAccessory->quantity_returned ?> · <?= esc($pendingAccessory->borrower_name) ?><?php if (! $readOnly): ?> · <a href="<?= base_url('/petugas/loans/' . $pendingAccessory->loan_id . '#loanAccessories') ?>">Tindak lanjuti</a><?php endif; ?></p><?php endforeach; ?>
          </div>
        <?php endif; ?>
      </article>

      <div class='section-head'>
        <h2>Riwayat terakhir</h2>
        <?php if (! $readOnly): ?><a class='link' href='<?= base_url('/petugas/items/' . $item->id . '/history') ?>'>Lihat lengkap</a><?php endif; ?>
      </div>
      <article class='card timeline'>
        <?php foreach (array_slice($histories, 0, 5) as $history): ?>
          <div class='event<?= ! empty($history->event_photo) ? ' has-photo' : '' ?>'>
            <div class='event-copy'>
              <b><?= esc(item_status_label($history->status_to)) ?> &mdash; <?= esc($history->note ?? '') ?></b>
              <small><?= esc(tgl_id($history->created_at, true)) ?> &middot; <?= esc($history->changed_by_name ?? 'Sistem') ?></small>
            </div>
            <?php if (! empty($history->event_photo)): ?>
              <?php $eventPhotoTitle = $history->damage_report_id ? 'Foto kerusakan' : 'Foto pengembalian'; ?>
              <button class='timeline-photo-thumb' type='button' data-photo-zoom='<?= esc(base_url($history->event_photo), 'attr') ?>' data-photo-title='<?= esc($eventPhotoTitle . ' · ' . $item->item_code, 'attr') ?>' aria-label='Perbesar <?= esc(strtolower($eventPhotoTitle)) ?>'>
                <img src='<?= esc(base_url($history->event_photo), 'attr') ?>' alt='<?= esc($eventPhotoTitle . ' ' . $item->item_code) ?>' loading='lazy'>
              </button>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
        <?php if (! $histories): ?><?= view('partials/empty', ['text' => 'Belum ada riwayat.', 'icon' => 'clock', 'flush' => true]) ?><?php endif; ?>
      </article>
    </div>

    <aside>
      <div class='qrbox'>
        <img src='<?= esc($qrImageUrl, 'attr') ?>' alt='QR <?= esc($item->item_code, 'attr') ?>'>
        <b><?= esc($item->item_code) ?></b>
        <p class='muted'>Pindai.in by SMP NEGERI 1 TANJUNGPINANG</p>
        <?php if (! $readOnly): ?>
          <div class='row'>
            <a class='btn gold' href='<?= esc($qrPrintUrl, 'attr') ?>' target='_blank' rel='noopener'>Cetak QR</a>
            <a class='btn' href='<?= esc($qrDownloadUrl, 'attr') ?>' download>Unduh PNG</a>
          </div>
          <button class='link' style='margin-top:10px' onclick='fetchPost(`<?= base_url('/petugas/items/' . $item->id . '/regenerate-qr') ?>`, {}, (json) => toast(json.message || `QR dibuat ulang`))'>Buat ulang QR</button>
        <?php endif; ?>
      </div>

      <?php if ($secondaryAction): ?>
      <article class='card unit-action-card'>
        <b>Tindakan untuk barang ini</b>
        <a class="btn full<?= $secondaryAction[0] === 'Laporkan Kerusakan' ? ' damage-action' : '' ?>" href="<?= esc($secondaryAction[1], 'attr') ?>"><?= esc($secondaryAction[0]) ?></a>
      </article>
      <?php endif; ?>
    </aside>
  </div>
</div>
<dialog id="itemQrDialog" class="detail-qr-dialog" aria-labelledby="itemQrTitle">
  <header>
    <div>
      <h2 id="itemQrTitle">QR Barang</h2>
      <p class="muted"><?= esc($item->item_code) ?></p>
    </div>
    <form method="dialog">
      <button class="round" type="submit" aria-label="Tutup QR" autofocus>&times;</button>
    </form>
  </header>
  <img class="detail-qr-image" src="<?= esc($qrImageUrl, 'attr') ?>" alt="QR <?= esc($item->item_code, 'attr') ?>">
  <?php if (! $readOnly): ?>
  <div class="detail-qr-actions">
    <?php if ($item->is_active): ?>
    <a class="btn gold" href="<?= esc($qrPrintUrl, 'attr') ?>" target="_blank" rel="noopener">Cetak QR</a>
    <?php endif; ?>
    <a class="btn" href="<?= esc($qrDownloadUrl, 'attr') ?>" download>Unduh PNG</a>
  </div>
  <?php endif; ?>
</dialog>
<div class='modal photo-viewer' id='photoViewer' aria-hidden='true'>
  <div class='modal-box photo-viewer-box' role='dialog' aria-modal='true' aria-labelledby='photoViewerTitle'>
    <div class='photo-viewer-head'>
      <b id='photoViewerTitle'>Preview foto</b>
      <button class='round' type='button' data-close aria-label='Tutup preview'>&times;</button>
    </div>
    <div class='photo-viewer-stage' id='photoViewerStage'>
      <img class='photo-viewer-image' id='photoViewerImage' alt='Preview foto diperbesar' draggable='false'>
      <div class='photo-viewer-controls' aria-label='Kontrol zoom foto'>
        <button class='photo-viewer-control' type='button' data-photo-action='zoom-out' aria-label='Perkecil foto'>&minus;</button>
        <output id='photoViewerZoom'>100%</output>
        <button class='photo-viewer-control' type='button' data-photo-action='zoom-in' aria-label='Perbesar foto'>+</button>
        <button class='photo-viewer-control' type='button' data-photo-action='reset' aria-label='Reset tampilan' title='Reset tampilan'>&#8634;</button>
      </div>
    </div>
  </div>
</div>
<?= view('layouts/footer') ?>
