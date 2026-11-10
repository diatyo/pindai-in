<?php $unitId = (int) $item->id; ?>
<article class="card loan-group-unit" data-group-unit="<?= $unitId ?>"<?= $blocked ? ' data-blocked' : '' ?>>
  <div class="loan-group-unit-head">
    <?= view('partials/inventory_thumb', ['category' => $item->category, 'photo' => $item->physical_photo], ['saveData' => false]) ?>
    <div><b><?= esc($item->item_name) ?></b><p class="muted"><?= esc($item->item_code) ?> · <?= esc(condition_label($item->condition)) ?></p></div>
    <button class="btn" type="button" data-remove-unit aria-label="<?= esc('Hapus ' . $item->item_name . ' dari daftar', 'attr') ?>">Hapus</button>
  </div>
  <?php if ($blocked): ?>
    <p class="muted"><?= esc($blocked) ?> Hapus unit ini untuk melanjutkan.</p>
  <?php else: ?>
    <details class="loan-group-checklist"<?= $stale ? ' open' : '' ?>>
      <summary>Periksa kelengkapan <small class="muted" data-group-required></small></summary>
      <?php if ($stale): ?><p class="muted">Daftar bawaan berubah. Periksa kembali kelengkapannya.</p><?php endif; ?>
      <?= view('partials/accessory_checkout', [
          'unitPrefix' => 'units[' . $unitId . ']', 'groupItemId' => $unitId, 'draft' => $draft,
          'accessories' => $accessories, 'accessory_template_hash' => $accessory_template_hash,
      ], ['saveData' => false]) ?>
    </details>
  <?php endif; ?>
</article>
