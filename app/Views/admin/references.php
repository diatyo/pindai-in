<?php
$label = $type === 'category' ? 'Kategori' : 'Lokasi';
$base = '/admin/references?type=' . $type;
$errors = session()->getFlashdata('errors') ?? [];
$editingUsage = $editing ? ($usage[$editing->name_key] ?? 0) : 0;
$referenceCss = '.reference-tabs{display:flex;gap:8px;flex-wrap:wrap;margin:20px 0 16px}.reference-layout{display:grid;grid-template-columns:minmax(0,1fr) 340px;gap:18px;align-items:start}.reference-summary{display:flex;gap:10px;flex-wrap:wrap;margin-top:12px}.reference-filter{display:flex;gap:10px;align-items:end;flex-wrap:wrap;margin-bottom:16px}.reference-filter .field{flex:1;min-width:130px;margin:0}.reference-record{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:12px;padding:18px 0;border-bottom:1px solid var(--line,#e4d8c9)}.reference-record:first-child{padding-top:0}.reference-record:last-child{border-bottom:0;padding-bottom:0}.reference-record h3{font-size:16px;margin:5px 0;overflow-wrap:anywhere}.reference-meta{font-size:12px;color:var(--muted)}.reference-actions{display:flex;gap:7px;flex-wrap:wrap;align-items:center;justify-content:flex-end}.reference-actions form{margin:0}.reference-actions .btn{min-height:40px;padding:8px 12px;font-size:12px}.reference-form h2{margin-top:0}.reference-note{line-height:1.65;font-size:13px}.reference-empty{padding:30px 0;text-align:center}.reference-actions .reference-delete{color:#9c4035}@media(max-width:1050px){.reference-layout{grid-template-columns:minmax(0,1fr) 290px}}@media(max-width:760px){.reference-layout{display:flex;flex-direction:column}.reference-layout>article{width:100%;box-sizing:border-box}.reference-form{order:-1}.reference-record{grid-template-columns:1fr}.reference-actions{justify-content:flex-start}.reference-filter .btn{width:100%}.reference-tabs .tab{flex:1;text-align:center}}';
?>
<?= view('layouts/header', ['title' => 'Data Referensi', 'subtitle' => 'Kelola pilihan kategori dan lokasi barang.', 'extraCss' => $referenceCss]) ?>
<div class="page" id="references">
  <div class="page-title"><div><span class="eyebrow">Data Master</span><h1>Data Referensi</h1><p class="muted">Pilihan yang digunakan Pengurus Barang saat mencatat inventaris.</p></div></div>
  <div class="reference-tabs" aria-label="Jenis referensi">
    <a class="tab <?= $type === 'category' ? 'active' : '' ?>" href="<?= base_url('/admin/references?type=category') ?>">Kategori Barang</a>
    <a class="tab <?= $type === 'location' ? 'active' : '' ?>" href="<?= base_url('/admin/references?type=location') ?>">Lokasi Penyimpanan</a>
  </div>
  <div class="reference-layout">
    <article class="card">
      <div class="section-head"><h2>Daftar <?= $label ?></h2><span class="badge green"><?= $active ?> aktif</span></div>
      <p class="muted reference-note"><?= $total ?> referensi · <?= $total - $active ?> nonaktif. Diurutkan otomatis A–Z. Referensi nonaktif tetap tercatat pada barang lama.</p>
      <form class="reference-filter" method="get" action="<?= base_url('/admin/references') ?>">
        <input type="hidden" name="type" value="<?= $type ?>">
        <div class="field"><label for="referenceSearch">Cari <?= strtolower($label) ?></label><input class="control" id="referenceSearch" type="search" name="q" value="<?= esc($q) ?>" placeholder="Nama <?= strtolower($label) ?>"></div>
        <div class="field"><label for="referenceStatus">Status</label><select class="control" name="status" id="referenceStatus"><option value="">Semua status</option><option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Aktif</option><option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Nonaktif</option></select></div>
        <button class="btn" type="submit">Cari</button>
      </form>
      <?php foreach ($references as $reference): $used = $usage[$reference->name_key] ?? 0; ?>
      <div class="reference-record">
        <div><span class="badge <?= $reference->is_active ? 'green' : 'red' ?>"><?= $reference->is_active ? 'Aktif' : 'Nonaktif' ?></span><h3><?= esc($reference->name) ?></h3><div class="reference-meta">Digunakan <?= $used ?> unit, termasuk arsip</div></div>
        <div class="reference-actions">
          <a class="btn" href="<?= base_url($base . '&edit=' . $reference->id) ?>#referenceForm" aria-label="Ubah <?= esc($reference->name, 'attr') ?>">Ubah</a>
          <form method="post" action="<?= base_url('/admin/references/' . $reference->id . '/toggle') ?>"><?= csrf_field() ?><button class="btn" type="submit"><?= $reference->is_active ? 'Nonaktifkan' : 'Aktifkan' ?></button></form>
          <?php if ($used === 0): ?><button class="btn reference-delete" type="button" onclick="confirmAction('Hapus referensi?', 'Referensi yang belum digunakan akan dihapus permanen.', '<?= base_url('/admin/references/' . $reference->id . '/delete') ?>', 'Hapus')" aria-label="Hapus <?= esc($reference->name, 'attr') ?>">Hapus</button><?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
      <?php if (! $references): ?><p class="muted reference-empty">Tidak ada referensi yang sesuai. Tambahkan melalui form atau ubah pencarian.</p><?php endif; ?>
      <?= view('partials/pagination', ['pager' => $pager]) ?>
    </article>
    <article class="card reference-form" id="referenceForm">
      <h2><?= $editing ? 'Ubah' : 'Tambah' ?> <?= $label ?></h2>
      <?= view('partials/notification', ['type' => 'error', 'title' => 'Periksa kembali isian', 'messages' => $errors]) ?>
      <form method="post" action="<?= base_url('/admin/references/save') ?>">
        <?= csrf_field() ?><input type="hidden" name="type" value="<?= $type ?>"><input type="hidden" name="id" value="<?= $editing->id ?? '' ?>">
        <div class="field"><label for="referenceName">Nama <?= strtolower($label) ?> <b>*</b></label><input class="control" id="referenceName" name="name" required maxlength="<?= $type === 'category' ? 60 : 100 ?>" value="<?= esc(old_value('name', $editing->name ?? '')) ?>" placeholder="<?= $type === 'category' ? 'Contoh: Peralatan Praktikum' : 'Contoh: Laboratorium Komputer' ?>"></div>
        <p class="muted reference-note">Kategori dan lokasi diurutkan otomatis berdasarkan nama, A–Z.</p>
        <?php if ($editingUsage > 0): ?><p class="reference-note">Perubahan nama juga memperbarui <?= $editingUsage ?> unit yang menggunakan referensi ini, termasuk barang arsip dan tampilan laporan terkait.</p><?php endif; ?>
        <button class="btn gold full" type="submit"><?= $editing ? 'Simpan Perubahan' : 'Tambah ' . $label ?></button>
        <?php if ($editing): ?><a class="btn full mt-14" href="<?= base_url($base) ?>">Batal mengubah</a><?php endif; ?>
      </form>
      <p class="muted reference-note">Nama harus unik dalam jenis yang sama. Nonaktifkan referensi yang tidak lagi digunakan; penghapusan tersedia untuk referensi yang belum dipakai barang.</p>
    </article>
  </div>
</div>
<?= view('layouts/footer') ?>
