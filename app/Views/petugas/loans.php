<?php
$status = $status ?? '';
$q = $q ?? '';
$statusCounts = $status_counts ?? [];
$statusLabels = ['' => 'Semua', 'ongoing' => 'Aktif', 'late' => 'Terlambat', 'completed' => 'Selesai'];
$loansUrl = base_url('/petugas/loans');
$perPage = $pager->getPerPage();
$listFilters = array_filter([
    'q' => $q, 'status' => $status, 'per_page' => $perPage !== 10 ? $perPage : '',
], static fn ($value) => $value !== '');
$listUrl = static function (array $changes) use ($loansUrl, $listFilters): string {
    $params = array_filter(array_replace($listFilters, $changes), static fn ($value) => $value !== '');
    return $loansUrl . ($params ? '?' . http_build_query($params) : '');
};
$detailQuery = '?' . http_build_query(['daftar' => $listFilters + ['page' => $pager->getCurrentPage()]]);
?>
<?= view('layouts/header', ['title' => 'Peminjaman', 'subtitle' => 'Transaksi aktif dan riwayat peminjaman.']) ?>
<div class="page" id="loans">
  <div class="page-title responsive-page-title">
    <div><h1>Peminjaman</h1></div>
    <a class="btn btn-icon loan-group-start" href="<?= base_url('/petugas/loans/groups/create') ?>"><?= ui_icon('plus', 'btn-svg') ?> Pinjam beberapa unit</a>
  </div>

  <div class="inventory-mobile inventory-controls">
    <form class="inventory-search" method="get" action="<?= esc($loansUrl) ?>" role="search" aria-label="Cari peminjaman">
      <?php foreach (array_diff_key($listFilters, ['q' => true]) as $key => $value): ?>
        <input type="hidden" name="<?= esc($key) ?>" value="<?= esc($value) ?>">
      <?php endforeach; ?>
      <button type="submit" aria-label="Cari transaksi"><?= ui_icon('search', 'btn-svg') ?></button>
      <input type="search" name="q" aria-label="Cari kode unit, barang, peminjam, atau nomor kelompok" placeholder="Cari transaksi" enterkeyhint="search" value="<?= esc($q) ?>">
      <?php if ($q !== ''): ?><a href="<?= esc($listUrl(['q' => ''])) ?>" aria-label="Hapus kata kunci">×</a><?php endif; ?>
    </form>
    <nav class="inventory-scopes" aria-label="Filter status peminjaman">
      <?php foreach ($statusLabels as $key => $label): ?>
        <a class="inventory-scope" href="<?= esc($listUrl(['status' => $key])) ?>"<?= $status === $key ? ' aria-current="true"' : '' ?>>
          <span><?= esc($label) ?></span> <span class="inventory-count"><?= (int) ($statusCounts[$key ?: 'all'] ?? 0) ?></span>
        </a>
      <?php endforeach; ?>
    </nav>
  </div>

  <form class="card mt-18 loan-filter inventory-desktop" method="get" action="<?= esc($loansUrl) ?>">
    <input type="hidden" name="status" value="<?= esc($status) ?>">
    <div class="loan-filter-grid">
      <div class="field">
        <label for="loanKeyword">Cari transaksi</label>
        <div class="search-control">
          <span class="search-control-icon" aria-hidden="true"><?= ui_icon('search', 'search-svg') ?></span>
          <input class="control" id="loanKeyword" name="q" type="search" enterkeyhint="search" value="<?= esc($q) ?>" placeholder="Kode unit, barang, peminjam, atau nomor kelompok">
        </div>
      </div>
      <div class="loan-filter-actions">
        <button class="btn gold btn-icon" type="submit"><?= ui_icon('search', 'btn-svg') ?> Cari</button>
        <?php if ($q !== ''): ?><a class="btn" href="<?= base_url('/petugas/loans' . ($status !== '' ? '?status=' . urlencode($status) : '')) ?>">Reset</a><?php endif; ?>
      </div>
    </div>
  </form>

  <div class="table-card mt-18 inventory-results">
    <div class="toolbar inventory-desktop">
      <div class="tabs">
        <?php foreach ($statusLabels as $k => $label): ?>
        <?php $count = (int) ($statusCounts[$k === '' ? 'all' : $k] ?? 0); ?>
        <a class="tab <?= ($status ?: '') === $k ? 'active' : '' ?>" href="<?= base_url('/petugas/loans?status=' . $k . '&q=' . urlencode($q)) ?>"><?= $label ?> <span class="tab-count"><?= $count ?></span></a>
        <?php endforeach; ?>
      </div>
      <div class="toolbar-actions">
        <a class="btn gold btn-icon" href="<?= base_url('/petugas/scan') ?>"><?= ui_icon('scan', 'btn-svg') ?> Scan QR Barang</a>
      </div>
    </div>

    <div class="inventory-mobile">
      <?php if ($loans): ?>
      <ul class="inventory-list" role="list" aria-label="Daftar peminjaman">
        <?php foreach ($loans as $l): ?>
          <li id="inventory-loan-<?= (int) $l->id ?>">
            <a class="inventory-item-link" href="<?= esc(base_url('/petugas/loans/' . $l->id) . $detailQuery) ?>">
              <?= view('partials/inventory_thumb', ['category' => $l->category, 'photo' => $l->physical_photo], ['saveData' => false]) ?>
              <span class="inventory-item-text">
                <strong class="item-name-primary"><?= esc($l->item_name) ?></strong>
                <span class="inventory-item-meta"><?= esc($l->borrower_name) ?> · <?= esc($l->item_code) ?></span>
                <?php if ($l->group_number): ?><span class="inventory-item-meta">Kelompok <?= esc($l->group_number) ?></span><?php endif; ?>
                <span class="inventory-loan-status">
                  <span class="badge <?= loan_status_label($l->returned_at, $l->due_at, true) ?>"><?= loan_status_label($l->returned_at, $l->due_at) ?></span>
                  <span><?= $l->returned_at ? 'Kembali' : 'Tenggat' ?> <?= esc(tgl_id($l->returned_at ?: $l->due_at, true)) ?></span>
                </span>
              </span>
              <span class="inventory-chevron" aria-hidden="true">›</span>
            </a>
          </li>
        <?php endforeach; ?>
      </ul>
      <?php else: ?>
      <div class="inventory-empty card">
        <h2><?= $q !== '' || $status !== '' ? 'Tidak ada transaksi yang cocok' : 'Belum ada transaksi' ?></h2>
        <?php if ($q !== '' || $status !== ''): ?>
          <p>Ubah kata kunci atau pilih status lain.</p>
          <a class="btn" href="<?= esc($listUrl(['q' => '', 'status' => ''])) ?>">Tampilkan semua transaksi</a>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </div>

    <div class="table-wrap inventory-desktop">
      <table class="table">
        <thead><tr><th>Unit</th><th>Peminjam</th><th>Jenis</th><th>Tanggal</th><th>Batas kembali</th><th>Status</th><th>Aksi</th></tr></thead>
        <tbody>
          <?php foreach ($loans as $l): ?>
          <tr>
            <td><?= esc($l->item_code) ?><?php if ($l->group_number): ?><br><a class="link" href="<?= base_url('/petugas/loans/groups/' . $l->loan_group_id) ?>"><?= esc($l->group_number) ?></a><?php endif; ?></td>
            <td><?= esc($l->borrower_name) ?></td>
            <td><?= $l->borrower_type === 'teacher' ? 'Guru' : 'Siswa' ?></td>
            <td><?= esc(tgl_id($l->borrowed_at)) ?></td>
            <td><?= esc(tgl_id($l->due_at, true)) ?></td>
            <td><span class="badge <?= loan_status_label($l->returned_at, $l->due_at, true) ?>"><?= loan_status_label($l->returned_at, $l->due_at) ?></span></td>
            <td><div class="row-actions"><a class="row-btn" href="<?= base_url('/petugas/loans/' . $l->id) ?>"><?= ui_icon('search', 'row-btn-icon') ?><span>Detail</span></a></div></td>
          </tr>
          <?php endforeach; ?>
          <?php if (! $loans): ?>
          <tr><td colspan="7"><?= view('partials/empty', ['text' => 'Tidak ada transaksi pada status ini.', 'icon' => 'loan', 'flush' => true]) ?></td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
    <?= view('partials/pagination') ?>
  </div>
</div>
<?= view('layouts/footer') ?>
