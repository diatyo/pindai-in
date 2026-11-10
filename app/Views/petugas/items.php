<?php
$q = $q ?? '';
$status = $status ?? '';
$category = $category ?? '';
$itemsUrl = base_url('/petugas/items');
$statusLabels = ['' => 'Semua', 'available' => 'Tersedia', 'borrowed' => 'Dipinjam', 'damaged' => 'Rusak', 'in_handling' => 'Ditangani', 'incomplete' => 'Menunggu kelengkapan', 'archived' => 'Arsip'];
$quickStatuses = array_slice($statusLabels, 0, 3, true);
$statusCounts = $statusCounts ?? [];
$statusCounts['archived'] = $archivedCount ?? 0;
$perPage = $pager->getPerPage();
$currentPage = $pager->getCurrentPage();
$listFilters = array_filter([
    'q' => $q, 'status' => $status, 'category' => $category, 'per_page' => $perPage !== 10 ? $perPage : '',
], static fn ($value) => $value !== '');
$listUrl = static function (array $changes) use ($itemsUrl, $listFilters): string {
    $params = array_filter(array_replace($listFilters, $changes), static fn ($value) => $value !== '');
    return $itemsUrl . ($params ? '?' . http_build_query($params) : '');
};
$detailQuery = '?' . http_build_query(['daftar' => $listFilters + ['page' => $currentPage]]);
$filterCount = (int) ($category !== '') + (int) ($status !== '');
?>
<?= view('layouts/header', ['title' => 'Data Barang', 'subtitle' => 'Cari dan periksa unit inventaris.']) ?>
<div class="page" id="items">
  <div class="page-title responsive-page-title">
    <div><h1>Data Barang</h1></div>
    <a class="btn btn-icon inventory-add inventory-mobile" href="<?= base_url('/petugas/items/create') ?>" aria-label="Tambah barang"><?= ui_icon('plus', 'btn-svg') ?> Tambah</a>
  </div>

  <div class="inventory-mobile inventory-controls">
    <div class="inventory-search-tools">
      <form class="inventory-search" method="get" action="<?= esc($itemsUrl) ?>" role="search" aria-label="Cari unit inventaris">
        <?php foreach (array_diff_key($listFilters, ['q' => true]) as $key => $value): ?>
          <input type="hidden" name="<?= esc($key) ?>" value="<?= esc($value) ?>">
        <?php endforeach; ?>
        <button type="submit" aria-label="Cari barang"><?= ui_icon('search', 'btn-svg') ?></button>
        <input type="search" id="inventoryKeyword" name="q" aria-label="Cari nama, kode unit, nomor seri, atau lokasi" placeholder="Cari barang" enterkeyhint="search" value="<?= esc($q) ?>">
        <?php if ($q !== ''): ?><a href="<?= esc($listUrl(['q' => ''])) ?>" aria-label="Hapus kata kunci">×</a><?php endif; ?>
      </form>
      <button class="btn btn-icon inventory-filter-button<?= $filterCount ? ' is-active' : '' ?>" id="openInventoryFilters" type="button" aria-haspopup="dialog" aria-controls="inventoryFilters">
        <?= ui_icon('filter', 'btn-svg') ?> Filter<?php if ($filterCount): ?> <span class="inventory-count"><?= $filterCount ?></span><?php endif; ?>
      </button>
    </div>
    <nav class="inventory-scopes" aria-label="Filter status cepat">
      <?php foreach ($quickStatuses as $key => $label): ?>
        <a class="inventory-scope" href="<?= esc($listUrl(['status' => $key])) ?>"<?= $status === $key ? ' aria-current="true"' : '' ?>>
          <?= esc($label) ?> <span class="inventory-count"><?= (int) ($statusCounts[$key] ?? 0) ?></span>
        </a>
      <?php endforeach; ?>
    </nav>
    <?php if ($category !== '' || ! isset($quickStatuses[$status])): ?>
    <div class="inventory-active-filters" aria-label="Filter aktif">
      <?php if (! isset($quickStatuses[$status])): ?>
        <a href="<?= esc($listUrl(['status' => ''])) ?>" aria-label="Hapus filter status <?= esc($statusLabels[$status] ?? $status) ?>"><?= esc($statusLabels[$status] ?? $status) ?> <span aria-hidden="true">×</span></a>
      <?php endif; ?>
      <?php if ($category !== ''): ?>
        <a href="<?= esc($listUrl(['category' => ''])) ?>" aria-label="Hapus filter kategori <?= esc($category) ?>"><?= esc($category) ?> <span aria-hidden="true">×</span></a>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </div>

  <dialog class="inventory-filter-sheet" id="inventoryFilters" aria-labelledby="inventoryFiltersTitle">
    <form method="get" action="<?= esc($itemsUrl) ?>">
      <div class="inventory-sheet-heading">
        <h2 id="inventoryFiltersTitle">Filter barang</h2>
        <button class="btn" id="closeInventoryFilters" type="button" aria-label="Tutup filter" autofocus>Tutup</button>
      </div>
      <input type="hidden" name="q" value="<?= esc($q) ?>">
      <?php if ($perPage !== 10): ?><input type="hidden" name="per_page" value="<?= $perPage ?>"><?php endif; ?>
      <div class="field">
        <label for="inventoryFilterStatus">Status</label>
        <select class="control" id="inventoryFilterStatus" name="status">
          <?php foreach ($statusLabels as $key => $label): ?>
            <option value="<?= esc($key) ?>" <?= $status === $key ? 'selected' : '' ?>><?= esc($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="inventoryFilterCategory">Kategori</label>
        <select class="control" id="inventoryFilterCategory" name="category">
          <option value="">Semua kategori</option>
          <?php foreach ($categories as $c): ?>
            <option value="<?= esc($c) ?>" <?= $category === $c ? 'selected' : '' ?>><?= esc($c) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="inventory-sheet-actions">
        <a class="btn" href="<?= esc($listUrl(['status' => '', 'category' => ''])) ?>">Hapus filter</a>
        <button class="btn gold" type="submit">Terapkan</button>
      </div>
    </form>
  </dialog>

  <form class="card mt-18 item-filter inventory-desktop" method="get" action="<?= esc($itemsUrl) ?>">
    <input type="hidden" name="status" value="<?= esc($status) ?>">
    <div class="item-filter-grid">
      <div class="field">
        <label for="itemKeyword">Kata kunci</label>
        <div class="search-control">
          <span class="search-control-icon" aria-hidden="true"><?= ui_icon('search', 'search-svg') ?></span>
          <input class="control" id="itemKeyword" name="q" type="search" enterkeyhint="search" value="<?= esc($q) ?>" placeholder="Kode, nama, serial, atau lokasi">
        </div>
      </div>
      <div class="field">
        <label for="itemCategory">Kategori</label>
        <select class="control" id="itemCategory" name="category">
          <option value="">Semua kategori</option>
          <?php foreach ($categories as $c): ?>
          <option value="<?= esc($c) ?>" <?= $category === $c ? 'selected' : '' ?>><?= esc($c) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="item-filter-actions">
        <button class="btn gold btn-icon" type="submit"><?= ui_icon('search', 'btn-svg') ?> Cari</button>
        <?php if ($q !== '' || $category !== ''): ?><a class="btn" href="<?= base_url('/petugas/items' . ($status !== '' ? '?status=' . urlencode($status) : '')) ?>">Reset</a><?php endif; ?>
      </div>
    </div>
  </form>

  <div class="table-card mt-18 inventory-results">
    <div class="inventory-mobile">
      <?php if ($items): ?>
      <ul class="inventory-list" role="list" aria-label="Daftar unit">
        <?php foreach ($items as $item): ?>
        <li id="inventory-unit-<?= (int) $item->id ?>">
          <a class="inventory-item-link" href="<?= esc(base_url('/petugas/items/' . $item->id) . $detailQuery) ?>">
            <?= view('partials/inventory_thumb', ['category' => $item->category, 'photo' => $item->physical_photo], ['saveData' => false]) ?>
            <span class="inventory-item-text">
              <strong class="item-name-primary"><?= esc($item->item_name) ?></strong>
              <span class="inventory-item-meta"><?= esc($item->item_code) ?><?= $item->location ? ' · ' . esc($item->location) : '' ?></span>
              <span class="badge <?= item_status_badge($item->status) ?>"><?= esc(item_status_label($item->status)) ?></span>
            </span>
            <span class="inventory-chevron" aria-hidden="true">›</span>
          </a>
        </li>
        <?php endforeach; ?>
      </ul>
      <?php else: ?>
      <div class="inventory-empty card">
        <h2><?= $q !== '' || $filterCount ? 'Tidak ada barang yang cocok' : 'Belum ada barang' ?></h2>
        <?php if ($q !== '' || $filterCount): ?>
          <p><?= $q !== '' ? 'Ubah kata kunci atau tampilkan semua barang.' : 'Coba hapus filter untuk menampilkan semua barang.' ?></p>
          <a class="btn" href="<?= esc($listUrl(['q' => '', 'status' => '', 'category' => ''])) ?>">Tampilkan semua barang</a>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </div>
    <form class="bulk-qr-form inventory-desktop" method="get" action="<?= base_url('/petugas/items/qr-labels') ?>" target="_blank">
    <div class="toolbar">
      <div class="tabs">
        <?php foreach (['' => 'Semua', 'available' => 'Tersedia', 'borrowed' => 'Dipinjam', 'damaged' => 'Rusak', 'in_handling' => 'Ditangani', 'incomplete' => 'Kelengkapan', 'archived' => 'Arsip'] as $k => $label): ?>
        <a class="tab <?= ($status ?: '') === $k ? 'active' : '' ?>" href="<?= base_url('/petugas/items?status=' . $k . '&q=' . urlencode($q) . '&category=' . urlencode($category)) ?>"><?= $label ?> <span class="tab-count"><?= (int) ($statusCounts[$k] ?? 0) ?></span></a>
        <?php endforeach; ?>
      </div>
      <div class="toolbar-actions">
        <div class="bulk-actions">
          <button class="btn btn-icon" id="bulkPrint" type="submit" disabled><?= ui_icon('box','btn-svg') ?><span id="bulkPrintLabel">Cetak QR Terpilih</span></button>
        </div>
        <a class="btn gold btn-icon" href="<?= base_url('/petugas/items/create') ?>"><?= ui_icon('plus','btn-svg') ?> Tambah Barang</a>
      </div>
    </div>

    <div class="table-wrap">
      <table class="table">
        <thead><tr><th><input type="checkbox" class="check" id="selectAllItems" aria-label="Pilih semua unit pada halaman ini" <?= ! $items || $status === 'archived' ? 'disabled' : '' ?>></th><th>Kode unit</th><th>Barang</th><th>Serial</th><th>Status</th><th>Kondisi</th><th>Lokasi</th><th>Aksi</th></tr></thead>
        <tbody>
          <?php foreach ($items as $item): ?>
          <tr>
            <td><input type="checkbox" class="check unit-check" name="item_ids[]" value="<?= $item->id ?>" <?= ! $item->is_active ? 'disabled' : '' ?> aria-label="Pilih unit <?= esc($item->item_code) ?>"></td>
            <td><b><?= esc($item->item_code) ?></b></td>
            <td class="item-name-cell"><a class="item-name-link" href="<?= base_url('/petugas/items/' . $item->id) ?>"><?= esc($item->item_name) ?></a></td>
            <td><?= esc($item->serial_number ?? '—') ?></td>
            <td><span class="badge <?= item_status_badge($item->status) ?>"><?= item_status_label($item->status) ?></span></td>
            <td><?= condition_label($item->condition) ?></td>
            <td><?= esc($item->location) ?></td>
            <td><div class="row-actions">
              <a class="row-btn" href="<?= base_url('/petugas/items/' . $item->id) ?>"><?= ui_icon('search', 'row-btn-icon') ?><span>Detail</span></a>
<?php if ($item->is_active): ?>              <a class="row-btn" target="_blank" href="<?= base_url('/petugas/items/qr-labels?item_ids[]=' . $item->id) ?>"><?= ui_icon('box', 'row-btn-icon') ?><span>QR</span></a><?php endif; ?>
              <?php if ($item->is_active): ?>
              <button class="row-btn" type="button" onclick="confirmAction('Arsipkan barang?', 'Barang tidak dapat dipinjam setelah diarsipkan. Riwayat dan transaksi yang masih berjalan tetap tersimpan.', '<?= base_url('/petugas/items/' . $item->id . '/archive') ?>', 'Arsipkan')"><?= ui_icon('archive', 'row-btn-icon') ?><span>Arsip</span></button>
              <?php else: ?>
              <button class="row-btn" type="button" onclick="confirmAction('Aktifkan kembali barang?', 'Pastikan barang sudah ditemukan atau kembali layak digunakan. Status ditentukan dari transaksi, kerusakan, dan kondisi terkini.', '<?= base_url('/petugas/items/' . $item->id . '/restore') ?>', 'Aktifkan kembali')"><?= ui_icon('check', 'row-btn-icon') ?><span>Aktifkan kembali</span></button>
              <?php endif; ?>
            </div></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    </form>
    <?= view('partials/pagination') ?>
  </div>
</div>
<script>
(() => {
  const filters = document.getElementById('inventoryFilters');
  const filterForm = filters.querySelector('form');
  document.getElementById('openInventoryFilters').addEventListener('click', () => {
    filterForm.reset();
    filterForm.elements.q.value = document.getElementById('inventoryKeyword').value;
    filters.showModal();
  });
  document.getElementById('closeInventoryFilters').addEventListener('click', () => filters.close());

  const selectAll = document.getElementById('selectAllItems');
  const checkboxes = [...document.querySelectorAll('.unit-check:not(:disabled)')];
  const button = document.getElementById('bulkPrint');
  const label = document.getElementById('bulkPrintLabel');
  const update = () => {
    const selected = checkboxes.filter((checkbox) => checkbox.checked).length;
    button.disabled = selected === 0;
    label.textContent = selected ? `Cetak QR Terpilih (${selected})` : 'Cetak QR Terpilih';
    selectAll.checked = checkboxes.length > 0 && selected === checkboxes.length;
    selectAll.indeterminate = selected > 0 && selected < checkboxes.length;
  };
  selectAll?.addEventListener('change', () => {
    checkboxes.forEach((checkbox) => checkbox.checked = selectAll.checked);
    update();
  });
  checkboxes.forEach((checkbox) => checkbox.addEventListener('change', update));
  update();
})();
</script>
<?= view('layouts/footer') ?>
