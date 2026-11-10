<?php
$returnRows = (new \App\Libraries\AccessoryChecklist())->forLoan((int) $loan->id);
$oldReturns = session()->get('_ci_old_input')['post']['accessory_returned'] ?? [];
?>
<section class="accessory-section" data-accessory-return>
  <h2>Periksa yang kembali</h2>
  <p class="muted accessory-help">Daftar ini mengikuti barang yang diserahkan pada transaksi ini. Centang setelah diperiksa.</p>
  <input type="hidden" name="completeness_in" value="lengkap" data-completeness-result>
  <?php foreach ($returnRows as $entry): ?>
    <?php if ((int) $entry['quantity'] === 1): ?>
    <label class="accessory-check">
      <input type="checkbox" name="accessory_returned[<?= (int) $entry['id'] ?>]" value="1" data-return-quantity data-total="1" data-required="<?= (int) $entry['is_required'] ?>" <?= ! empty($oldReturns[$entry['id']]) ? 'checked' : '' ?>>
      <span><b><?= esc($entry['name']) ?></b><small><?= $entry['is_required'] ? 'Wajib untuk kesiapan unit' : 'Opsional untuk kesiapan unit; tetap dicatat bila kurang' ?></small></span>
    </label>
    <?php else: ?>
    <label class="accessory-count" for="return-accessory-<?= (int) $entry['id'] ?>"><span><b><?= esc($entry['name']) ?></b><small>Diserahkan <?= (int) $entry['quantity'] ?> · <?= $entry['is_required'] ? 'Wajib untuk kesiapan unit' : 'Opsional untuk kesiapan unit' ?></small></span>
      <input class="control" id="return-accessory-<?= (int) $entry['id'] ?>" name="accessory_returned[<?= (int) $entry['id'] ?>]" type="number" inputmode="numeric" min="0" max="<?= (int) $entry['quantity'] ?>" required value="<?= esc(is_scalar($oldReturns[$entry['id']] ?? null) ? $oldReturns[$entry['id']] : '') ?>" placeholder="0" aria-label="Jumlah <?= esc($entry['name'], 'attr') ?> yang kembali" data-return-quantity data-total="<?= (int) $entry['quantity'] ?>" data-required="<?= (int) $entry['is_required'] ?>">
    </label>
    <?php endif; ?>
  <?php endforeach; ?>
  <?php if (! $returnRows): ?><p class="muted accessory-help">Tidak ada kelengkapan pendamping yang diserahkan.</p><?php endif; ?>
  <p class="accessory-result" data-accessory-result aria-live="polite"></p>
  <label class="accessory-check accessory-confirm"><input type="checkbox" name="accessory_return_confirmed" value="1" required data-required-message="Konfirmasikan bahwa unit dan kelengkapan yang kembali sudah diperiksa sebelum menyimpan pengembalian."><span>Saya sudah memeriksa unit dan kelengkapan yang kembali.</span></label>
</section>
