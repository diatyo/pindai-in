<?php $q = $q ?? ''; $status = $status ?? ''; $category = $category ?? ''; ?>
<?= view('layouts/header', ['title' => 'Data Barang', 'subtitle' => 'Identitas dan status unit inventaris (baca saja).']) ?>
<div class="page" id="items">
  <div class="page-title responsive-page-title">
    <div><h1>Data Barang</h1><span class="muted">Identitas unit inventaris.</span></div>
  </div>

  <form class="card mt-18 item-filter" method="get" action="<?= base_url('/kepala/items') ?>">
    <input type="hidden" name="status" value="<?= esc($status) ?>">
    <div class="item-filter-grid">
      <div class="field">
        <label for="principalItemKeyword">Kata kunci</label>
        <div class="search-control">
          <span class="search-control-icon" aria-hidden="true"><?= ui_icon('search', 'search-svg') ?></span>
          <input class="control" id="principalItemKeyword" name="q" type="search" enterkeyhint="search" value="<?= esc($q) ?>" placeholder="Kode, nama, serial, atau lokasi">
        </div>
      </div>
      <div class="field"><label for="principalItemCategory">Kategori</label>
        <select class="control" id="principalItemCategory" name="category">
          <option value="">Semua kategori</option>
          <?php foreach ($categories as $c): ?>
          <option value="<?= esc($c->category) ?>" <?= $category === $c->category ? 'selected' : '' ?>><?= esc($c->category) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="item-filter-actions">
        <button class="btn gold btn-icon" type="submit"><?= ui_icon('search', 'btn-svg') ?> Cari</button>
        <?php if ($q !== '' || $category !== ''): ?><a class="btn" href="<?= base_url('/kepala/items' . ($status !== '' ? '?status=' . urlencode($status) : '')) ?>">Reset</a><?php endif; ?>
      </div>
    </div>
  </form>

  <div class="table-card mt-18">
    <div class="toolbar">
      <div class="tabs">
        <?php foreach (['' => 'Semua', 'available' => 'Tersedia', 'borrowed' => 'Dipinjam', 'damaged' => 'Rusak', 'in_handling' => 'Ditangani', 'incomplete' => 'Kelengkapan'] as $k => $label): ?>
        <a class="tab <?= ($status ?: '') === $k ? 'active' : '' ?>" href="<?= base_url('/kepala/items?status=' . $k) ?>"><?= $label ?></a>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>Kode</th><th>Barang</th><th>Serial</th><th>Status</th><th>Kondisi</th><th>Pemakai terakhir</th><th>Aksi</th></tr></thead>
        <tbody>
          <?php foreach ($items as $item): $lb = model('App\Models\ItemModel')->lastBorrower((int) $item->id); ?>
          <tr>
            <td><b><?= esc($item->item_code) ?></b></td>
            <td class="item-name-cell"><a class="item-name-link" href="<?= base_url('/kepala/items/' . $item->id) ?>"><?= esc($item->item_name) ?></a></td>
            <td><?= esc($item->serial_number ?? '—') ?></td>
            <td><span class="badge <?= item_status_badge($item->status) ?>"><?= item_status_label($item->status) ?></span></td>
            <td><?= condition_label($item->condition) ?></td>
            <td><?= $lb ? esc($lb->borrower_name) : '—' ?></td>
<td><div class="row-actions"><a class="row-btn" href="<?= base_url('/kepala/items/' . $item->id) ?>"><?= ui_icon('search', 'row-btn-icon') ?><span>Detail</span></a></div></td>
          </tr>
          <?php endforeach; ?>
          <?php if (! $items): ?><tr><td colspan="7" class="muted">Tidak ada unit.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
    <?= view('partials/pagination') ?>
  </div>
</div>
<?= view('layouts/footer') ?>
