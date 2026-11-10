<?php $successTitle = $loan->returned_at ? 'Pengembalian berhasil' : 'Peminjaman berhasil'; ?>
<?= view('layouts/header', ['title' => $successTitle, 'subtitle' => 'Transaksi telah disimpan.']) ?>
<div class="page form-desktop" id="loanSuccess">
  <div class="transaction-success-mark" aria-hidden="true">✓</div>
  <h1><?= esc($successTitle) ?></h1>
  <p class="muted transaction-success-note">Transaksi sudah tercatat.</p>
  <article class="card transaction-success-summary">
    <span class="eyebrow"><?= esc($loan->loan_number) ?></span>
    <h2 class="transaction-item-name"><?= esc($loan->item_name) ?></h2>
    <p class="item-unit-code">Kode unit · <?= esc($loan->item_code) ?></p>
    <div class="kv"><span>Peminjam</span><b><?= esc($loan->borrower_name) ?> · <?= $loan->borrower_type === 'teacher' ? 'Guru' : 'Siswa' ?></b></div>
    <div class="kv"><span><?= $loan->returned_at ? 'Dikembalikan' : 'Batas kembali' ?></span><b><?= esc(tgl_id($loan->returned_at ?: $loan->due_at, true)) ?></b></div>
  </article>
  <?php if ($loan->returned_at && ! empty($loan->accessory_checklist)): ?>
    <?php $missing = array_filter((new \App\Libraries\AccessoryChecklist())->forLoan((int) $loan->id), static fn ($entry) => (int) $entry['quantity_returned'] < (int) $entry['quantity']); ?>
    <?php if ($missing): ?><article class="card mt-14" style="text-align:left"><h2>Ada kelengkapan belum kembali</h2><p>Pengembalian unit sudah dicatat. <?= count($missing) ?> jenis kelengkapan perlu ditindaklanjuti. Kelengkapan wajib yang belum selesai menahan kesiapan unit.</p><a class="btn full mt-14" href="<?= base_url('/petugas/loans/' . $loan->id . '#loanAccessories') ?>">Buka tindak lanjut</a></article><?php endif; ?>
  <?php endif; ?>
  <div class="transaction-success-actions">
    <a class="btn" href="<?= base_url('/petugas/loans/' . $loan->id . '#loanAccessories') ?>">Lihat Serah Terima</a>
    <a class="btn" href="<?= base_url('/petugas/items/' . $loan->item_id) ?>">Lihat Detail</a>
    <a class="btn gold" href="<?= base_url('/petugas') ?>">Selesai</a>
  </div>
</div>
<?= view('layouts/footer') ?>
