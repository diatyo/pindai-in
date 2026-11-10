<?php $errors = session()->getFlashdata('errors') ?? []; $preset = $preset_item_id ?? 0; $loan = $loan ?? null; ?>
<?= view('layouts/header', ['title' => 'Lapor Kerusakan', 'subtitle' => 'Catat kondisi dan kerusakan unit.']) ?>
<div class="page form-desktop">
  <div class="page-title"><div><span class="eyebrow">Kerusakan</span><h1>Lapor Kerusakan</h1></div></div>

  <?php if ($loan): ?>
  <article class="card mt-18">
    <span class="badge amber">Dari pengembalian</span>
    <h3><?= esc($loan->item_code) ?> · <?= esc($loan->item_name) ?></h3>
    <small class="muted">Peminjam terakhir: <?= esc($loan->borrower_name ?? '—') ?> · <?= esc($loan->loan_number) ?></small>
  </article>
  <?php endif; ?>

<?= view('partials/notification', ['type' => 'error', 'title' => 'Periksa kembali isian', 'messages' => $errors]) ?>

  <form class="card mt-14" method="post" enctype="multipart/form-data" action="<?= base_url('/petugas/damages/store') ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="loan_id" value="<?= $loan->id ?? old_value('loan_id') ?>">
    <div class="field">
      <label>Unit barang <b>*</b></label>
      <select class="control" name="item_id">
        <?php foreach ($items as $it): ?>
        <option value="<?= $it->id ?>" <?= (int) $preset === (int) $it->id ? 'selected' : '' ?>><?= esc($it->item_code) ?> · <?= esc($it->item_name) ?> <?= esc($it->model ?? '') ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label>Waktu ditemukan <b>*</b></label>
      <input class="control" type="datetime-local" name="discovered_at" value="<?= esc(old_value('discovered_at', date('Y-m-d\TH:i'))) ?>">
    </div>
    <div class="field">
      <label>Kondisi <b>*</b></label>
      <select class="control" name="damage_level">
        <option value="minor_damage">Rusak ringan</option>
        <option value="major_damage">Rusak berat</option>
      </select>
    </div>
    <div class="field">
      <label>Deskripsi kerusakan <b>*</b></label>
      <textarea class="control" name="description" rows="4" placeholder="Contoh: Layar berkedip saat perangkat digunakan."><?= esc(old_value('description')) ?></textarea>
    </div>
    <div class="field">
      <label>Foto kerusakan</label>
      <div class="photo" data-photo role="button" tabindex="0"><input type="file" name="damage_photo" accept="image/*" capture="environment" hidden><small class="muted">Ambil foto kerusakan</small></div>
    </div>
    <div class="field">
      <label>Catatan</label>
      <textarea class="control" name="notes" rows="3"><?= esc(old_value('notes')) ?></textarea>
    </div>
    <button class="btn gold full" type="submit">Simpan Laporan</button>
  </form>
</div>
<?= view('layouts/footer') ?>
