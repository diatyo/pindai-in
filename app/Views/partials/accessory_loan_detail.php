<?php
$checklists = new \App\Libraries\AccessoryChecklist();
$loanAccessories = $checklists->forLoan((int) $loan->id);
$accessoryEvents = $checklists->events((int) $loan->id);
$canResolve = ! ($readOnly ?? false) && session()->get('role') === 'petugas';
?>
<section class="card mt-14 accessory-section" id="loanAccessories">
  <div class="section-head"><h2>Kelengkapan serah terima</h2><button type="button" class="btn accessory-print" onclick="window.print()">Cetak bukti</button></div>
  <?php if (empty($loan->accessory_checklist)): ?>
    <p class="muted accessory-help">Transaksi lama memakai pemeriksaan umum. Rincian kelengkapan per barang belum dicatat saat peminjaman.</p>
  <?php elseif (! $loanAccessories): ?>
    <p class="muted accessory-help">Pemeriksaan dilakukan tanpa kelengkapan pendamping.</p>
  <?php endif; ?>
  <?php foreach ($loanAccessories as $entry): $remaining = (int) $entry['quantity'] - (int) $entry['quantity_returned']; ?>
  <div class="accessory-detail-row">
    <b><?= esc($entry['name']) ?> × <?= (int) $entry['quantity'] ?></b>
    <p class="muted"><?= $entry['source_accessory_id'] ? 'Bawaan unit' : 'Tambahan transaksi' ?> · <?= $entry['is_required'] ? 'Wajib untuk kesiapan unit' : 'Opsional untuk kesiapan unit' ?></p>
    <?php if ($loan->returned_at): ?>
    <p><span class="badge <?= $remaining ? 'amber' : 'green' ?>"><?= $remaining ? 'Belum kembali ' . $remaining : 'Sudah lengkap' ?></span> · Diterima/diselesaikan <?= (int) $entry['quantity_returned'] ?> dari <?= (int) $entry['quantity'] ?></p>
    <?php if ($remaining && $canResolve): ?>
    <details class="accessory-followup"><summary>Catat penyelesaian <?= esc($entry['name']) ?></summary>
      <form method="post" action="<?= base_url('/petugas/loans/' . $loan->id . '/accessories/' . $entry['id'] . '/resolve') ?>">
        <?= csrf_field() ?><input type="hidden" name="request_key" value="<?= bin2hex(random_bytes(16)) ?>">
        <label class="field" for="resolution-qty-<?= (int) $entry['id'] ?>">Jumlah yang diselesaikan<input class="control" id="resolution-qty-<?= (int) $entry['id'] ?>" name="quantity" type="number" inputmode="numeric" min="1" max="<?= $remaining ?>" value="<?= $remaining ?>" required></label>
        <label class="field" for="resolution-kind-<?= (int) $entry['id'] ?>">Cara penyelesaian<select class="control" id="resolution-kind-<?= (int) $entry['id'] ?>" name="kind" required><option value="">Pilih penyelesaian</option><option value="returned">Barang asli dikembalikan</option><option value="replacement">Pengganti diterima dan diperiksa</option></select></label>
        <label class="field" for="resolution-note-<?= (int) $entry['id'] ?>">Catatan pemeriksaan<textarea class="control" id="resolution-note-<?= (int) $entry['id'] ?>" name="note" rows="2" maxlength="500" required placeholder="Contoh: Charger asli diterima dan berfungsi."></textarea></label>
        <button class="btn gold full" type="submit">Simpan penyelesaian</button>
      </form>
    </details>
    <?php endif; ?>
    <?php else: ?><p>Ikut diserahkan pada peminjaman.</p><?php endif; ?>
  </div>
  <?php endforeach; ?>
  <?php if ($accessoryEvents): ?>
  <details class="accessory-followup" data-accessory-history><summary>Riwayat pemeriksaan dan tindak lanjut</summary>
    <?php foreach ($accessoryEvents as $event): ?>
    <div class="accessory-detail-row"><b><?= esc($event['name']) ?> · <?= ['initial' => 'Pemeriksaan pengembalian', 'returned' => 'Dikembalikan', 'replacement' => 'Pengganti diterima'][$event['kind']] ?? 'Pemeriksaan' ?>: <?= (int) $event['quantity'] ?></b>
      <p><?= esc($event['note'] ?? '') ?></p><small class="muted"><?= esc($event['user_name']) ?> · <?= esc(tgl_id($event['created_at'], true)) ?></small></div>
    <?php endforeach; ?>
  </details>
  <div class="accessory-history-print">
    <h3>Riwayat pemeriksaan dan tindak lanjut</h3>
    <?php foreach ($accessoryEvents as $event): ?>
      <div class="accessory-detail-row"><b><?= esc($event['name']) ?> · <?= ['initial' => 'Pemeriksaan pengembalian', 'returned' => 'Dikembalikan', 'replacement' => 'Pengganti diterima'][$event['kind']] ?? 'Pemeriksaan' ?>: <?= (int) $event['quantity'] ?></b><p><?= esc($event['note'] ?? '') ?></p><small><?= esc($event['user_name']) ?> · <?= esc(tgl_id($event['created_at'], true)) ?></small></div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</section>
