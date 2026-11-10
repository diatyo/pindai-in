<?= view('layouts/header', ['title' => 'Detail Penanganan', 'subtitle' => 'Perkembangan dan keterangan penanganan unit.']) ?>
<div class="page form-desktop">
  <div class="page-title">
    <div><span class="eyebrow"><?= esc($report->item_code) ?></span><h1>Detail Penanganan</h1></div>
    <span class="badge <?= damage_status_badge($report->status) ?>"><?= damage_status_label($report->status) ?></span>
  </div>

  <article class="card mt-18">
    <div class="kv"><span>Unit</span><b><a class="link" href="<?= base_url('/petugas/items/' . $report->item_id) ?>"><?= esc($report->item_code) ?> · <?= esc($report->item_name) ?> <?= esc($report->model ?? '') ?></a></b></div>
    <div class="kv"><span>Masalah</span><b><?= esc($report->description) ?></b></div>
    <div class="kv"><span>Dilaporkan</span><b><?= esc(tgl_id($report->discovered_at, true)) ?></b></div>
    <div class="kv"><span>Pelapor</span><b><?= esc($report->reported_by_name ?? '—') ?></b></div>
    <div class="kv"><span>Status unit</span><b><?= item_status_label($report->item_status) ?></b></div>
  </article>

  <div class="section-head"><h2>Riwayat keterangan</h2></div>
  <?php if ($actions): ?>
  <article class="card timeline">
    <?php foreach ($actions as $a): ?>
    <div class="event">
      <b><?= esc($a->action_description) ?></b>
      <small><?= esc(tgl_id($a->action_date)) ?> · <?= esc($a->executor_name) ?> · <?= $a->status === 'completed' ? 'Selesai' : ($a->status === 'unrepairable' ? 'Tidak dapat diperbaiki' : 'Berjalan') ?><?= $a->result_condition ? ' · Hasil: ' . condition_label($a->result_condition) : '' ?></small>
    </div>
    <?php endforeach; ?>
  </article>
  <?php else: ?>
  <?= view('partials/empty', ['text' => 'Belum ada keterangan penanganan.', 'icon' => 'check']) ?>
  <?php endif; ?>

  <?php if ($report->status === 'reported' || $report->status === 'in_handling'): ?>
  <a class="btn gold full" href="<?= base_url('/petugas/handling/create/' . $report->id) ?>">Tambah Keterangan</a>
  <?php endif; ?>
</div>
<?= view('layouts/footer') ?>
