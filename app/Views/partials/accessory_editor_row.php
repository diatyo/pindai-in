<?php $rowName = is_string($entry['name'] ?? null) ? $entry['name'] : ''; $rowQuantity = is_scalar($entry['quantity'] ?? null) ? (string) $entry['quantity'] : '1'; ?>
<div class="accessory-saved-row" data-accessory-row>
  <input type="hidden" name="<?= $prefix ?>[<?= $index ?>][name]" value="<?= esc($rowName, 'attr') ?>">
  <input type="hidden" name="<?= $prefix ?>[<?= $index ?>][quantity]" value="<?= esc($rowQuantity, 'attr') ?>">
  <?php if ($extra): ?>
  <input type="hidden" name="<?= $prefix ?>[<?= $index ?>][sent]" value="1">
  <?php else: ?>
  <input type="hidden" name="<?= $prefix ?>[<?= $index ?>][is_required]" value="<?= ! empty($entry['is_required']) ? '1' : '0' ?>">
  <?php endif; ?>
  <div class="accessory-saved-copy">
    <div><b data-accessory-label><?= esc($rowName) ?></b><span class="accessory-saved-quantity" data-accessory-label-quantity>Jumlah <?= esc($rowQuantity) ?></span></div>
    <?php if (! $extra): ?><small data-accessory-label-kind><?= ! empty($entry['is_required']) ? 'Wajib menyertai unit' : 'Opsional disertakan' ?></small><?php endif; ?>
  </div>
  <div class="accessory-saved-actions"><button class="accessory-edit" type="button" data-accessory-edit aria-label="Ubah kelengkapan <?= esc($rowName, 'attr') ?>">Ubah</button><button class="accessory-remove" type="button" data-accessory-remove aria-label="Hapus kelengkapan <?= esc($rowName, 'attr') ?>">Hapus</button></div>
</div>
