<?= view('layouts/header', ['title' => 'Riwayat Unit', 'subtitle' => 'Semua perubahan status unit tercatat di sini.']) ?>
<div class="page form-desktop">
  <?= view('partials/school_letterhead', ['printOnly' => true]) ?>
  <div class="page-title"><div><span class="eyebrow">Timeline</span><h1>Riwayat Unit</h1></div><button class="btn" onclick="window.print()">Cetak</button></div>
  <article class="card timeline mt-18">
    <?php foreach ($histories as $h): ?>
    <div class="event">
      <b><?= esc($h->item_code) ?> — <?= esc(item_status_label($h->status_to)) ?> — <?= esc($h->note ?? '') ?></b>
      <small><?= esc(tgl_id($h->created_at, true)) ?> · <?= esc($h->changed_by_name ?? 'Sistem') ?></small>
    </div>
    <?php endforeach; ?>
    <?php if (! $histories): ?><?= view('partials/empty', ['text' => 'Belum ada riwayat.', 'icon' => 'clock', 'flush' => true]) ?><?php endif; ?>
  </article>
  <?= view('partials/pagination') ?>
  <?= view('partials/report_signature', ['printOnly' => true]) ?>
</div>
<?= view('layouts/footer') ?>