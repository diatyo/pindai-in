<?php
$first = $loans[0];
$returned = count(array_filter($loans, static fn ($loan) => $loan->returned_at !== null));
$total = count($loans);
?>
<?= view('layouts/header', [
    'title' => 'Peminjaman Kelompok', 'subtitle' => $group->group_number,
    'extraCss' => '@page{size:A4;margin:12mm}',
]) ?>
<div class="page form-desktop" id="loanGroupReceipt">
  <div class="loan-group-actions">
    <a class="btn" href="<?= base_url('/petugas/loans') ?>">‹ Daftar peminjaman</a>
    <button class="btn btn-icon" type="button" onclick="window.print()"><?= ui_icon('box', 'btn-svg') ?> Cetak bukti</button>
  </div>
  <div class="page-title">
    <div><span class="eyebrow"><?= esc($group->group_number) ?></span><h1>Serah Terima Kelompok</h1></div>
    <span class="badge <?= $returned === $total ? 'green' : 'amber' ?>" data-group-progress><?= $returned ?>/<?= $total ?> kembali</span>
  </div>
  <article class="card loan-group-summary">
    <p class="muted"><?= esc(school_name()) ?></p>
    <div class="kv"><span>Peminjam</span><b><?= esc($first->borrower_name) ?> · <?= $first->borrower_type === 'teacher' ? 'Guru' : 'Siswa' ?></b></div>
    <div class="kv"><span>Tanggal pinjam</span><b><?= esc(tgl_id($first->borrowed_at, true)) ?></b></div>
    <div class="kv"><span>Batas kembali</span><b><?= esc(tgl_id($first->due_at, true)) ?></b></div>
    <div class="kv"><span>Lokasi penggunaan</span><b><?= esc($first->usage_location) ?></b></div>
    <div class="kv"><span>Keperluan</span><b><?= esc($first->purpose) ?></b></div>
    <div class="kv"><span>Petugas</span><b><?= esc($first->processed_by_name ?? '—') ?></b></div>
  </article>
  <div class="section-head">
    <h2>Pengembalian unit</h2>
  </div>
  <?php foreach ($loans as $loan): ?>
  <article class="card loan-group-receipt-unit" data-group-loan="<?= (int) $loan->id ?>">
    <div class="loan-group-unit-head">
      <div>
        <b><?= esc($loan->item_name) ?></b>
        <p class="muted"><?= esc($loan->item_code) ?> · <?= esc($loan->loan_number) ?></p>
      </div>
      <span class="badge <?= loan_status_label($loan->returned_at, $loan->due_at, true) ?>"><?= loan_status_label($loan->returned_at, $loan->due_at) ?></span>
    </div>
    <p>Kondisi awal <?= esc(condition_label($loan->condition_out)) ?></p>
    <?php if ($loan->returned_at): ?>
    <p>Kembali <?= esc(tgl_id($loan->returned_at, true)) ?> · <?= esc(condition_label($loan->condition_in ?? 'good')) ?></p>
    <?php endif; ?>
    <?php $sent = $accessories[$loan->id] ?? []; ?>
    <?php if ($sent): ?>
    <p>Kelengkapan diserahkan: <?= esc(implode(', ', array_map(static fn ($row) => $row['name'] . ' × ' . $row['quantity'], $sent))) ?></p>
    <?php if ($loan->returned_at): ?>
      <?php $missing = array_filter($sent, static fn ($row) => (int) $row['quantity_returned'] < (int) $row['quantity']); ?>
      <?php if ($missing): ?>
      <p><span class="badge amber">Kelengkapan belum kembali</span> <?= esc(implode(', ', array_map(static fn ($row) => $row['name'] . ' × ' . ((int) $row['quantity'] - (int) $row['quantity_returned']), $missing))) ?></p>
      <?php endif; ?>
    <?php endif; ?>
    <?php endif; ?>
    <div class="loan-group-actions">
      <a class="btn" href="<?= base_url('/petugas/loans/' . $loan->id) ?>">Detail transaksi</a>
      <?php if (! $loan->returned_at): ?>
      <a class="btn gold" href="<?= base_url('/petugas/loans/' . $loan->id . '/return') ?>">Kembalikan unit</a>
      <?php endif; ?>
    </div>
  </article>
  <?php endforeach; ?>
</div>
<?= view('layouts/footer') ?>
