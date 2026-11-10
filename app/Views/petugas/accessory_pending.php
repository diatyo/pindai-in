<?= view('layouts/header', ['title' => 'Tindak Lanjut Kelengkapan', 'subtitle' => 'Periksa kekurangan dan catat penyelesaian pengembaliannya.']) ?>
<div class="page accessory-followups" id="accessoryFollowups">
  <div class="page-title responsive-page-title"><div><span class="eyebrow">Serah terima</span><h1>Tindak Lanjut Kelengkapan</h1></div></div>
  <div class="accessory-page-intro">
    <div><h2>Kelengkapan belum kembali</h2><p class="muted">Tindak lanjuti barang pendamping dari unit yang sudah dikembalikan.</p></div>
    <a class="btn" href="<?= base_url('/petugas/loans') ?>">Lihat peminjaman</a>
  </div>
  <?php if ($pending): ?>
    <div class="accessory-policy-note"><span class="badge amber">Wajib</span><span>Menahan peminjaman unit sampai selesai.</span><span class="badge blue">Opsional</span><span>Tetap dicatat dan ditindaklanjuti.</span></div>
    <div class="table-card accessory-pending-table">
      <div class="accessory-list-heading"><h3>Daftar tindak lanjut</h3><span class="muted"><?= (int) $pager->getTotal() ?> kelengkapan</span></div>
      <table class="table"><thead><tr><th>Barang & kelengkapan</th><th>Peminjam</th><th>Belum kembali</th><th>Kesiapan unit</th><th>Tindakan</th></tr></thead><tbody>
        <?php foreach ($pending as $entry): $missing = (int) $entry->quantity - (int) $entry->quantity_returned; ?>
        <tr>
          <td><b><?= esc($entry->name) ?></b><span class="accessory-cell-secondary"><?= esc($entry->item_name) ?></span><span class="accessory-cell-secondary"><?= esc($entry->item_code) ?> · <?= esc($entry->loan_number) ?></span></td>
          <td><?= esc($entry->borrower_name) ?><span class="accessory-cell-secondary">Unit kembali <?= esc(tgl_id($entry->returned_at)) ?></span></td>
          <td><b class="accessory-missing-count"><?= $missing ?></b><span class="accessory-cell-secondary">dari <?= (int) $entry->quantity ?> diserahkan</span></td>
          <td><span class="badge <?= $entry->is_required ? 'amber' : 'blue' ?>"><?= $entry->is_required ? 'Menahan peminjaman' : 'Opsional' ?></span></td>
          <td><a class="btn" href="<?= base_url('/petugas/loans/' . $entry->loan_id . '#loanAccessories') ?>">Periksa & tindak lanjuti</a></td>
        </tr>
        <?php endforeach; ?>
      </tbody></table>
    </div>
    <div class="accessory-pending-cards">
      <?php foreach ($pending as $entry): $missing = (int) $entry->quantity - (int) $entry->quantity_returned; ?>
      <article class="card accessory-pending-card">
        <div class="accessory-pending-top"><span class="badge <?= $entry->is_required ? 'amber' : 'blue' ?>"><?= $entry->is_required ? 'Menahan peminjaman' : 'Opsional' ?></span><span class="accessory-unit-label"><?= esc($entry->item_code) ?></span></div>
        <h2><?= esc($entry->name) ?></h2><p class="accessory-pending-unit"><?= esc($entry->item_name) ?></p>
        <div class="accessory-pending-balance"><b><?= $missing ?> belum kembali</b><span>dari <?= (int) $entry->quantity ?> diserahkan</span></div>
        <p class="accessory-cell-secondary"><?= esc($entry->borrower_name) ?> · <?= esc($entry->loan_number) ?></p>
        <p class="accessory-cell-secondary">Unit kembali <?= esc(tgl_id($entry->returned_at, true)) ?></p>
        <a class="btn full" href="<?= base_url('/petugas/loans/' . $entry->loan_id . '#loanAccessories') ?>">Periksa & tindak lanjuti</a>
      </article>
      <?php endforeach; ?>
    </div>
    <?= view('partials/pagination') ?>
  <?php else: ?>
    <section class="card accessory-all-clear" aria-labelledby="accessoryClearTitle">
      <span class="accessory-clear-icon" aria-hidden="true"><?= ui_icon('check', 'accessory-clear-svg') ?></span>
      <h2 id="accessoryClearTitle">Tidak ada kekurangan terbuka</h2>
      <p>Belum ada kelengkapan yang perlu ditindaklanjuti. Kekurangan yang dicatat saat pengembalian akan muncul di sini.</p>
      <a class="btn" href="<?= base_url('/petugas/items') ?>">Lihat data barang</a>
    </section>
  <?php endif; ?>
</div>
<?= view('layouts/footer') ?>
