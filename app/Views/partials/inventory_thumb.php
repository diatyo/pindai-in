<?php $categoryIcons = ['Perangkat Komputer' => 'laptop', 'Jaringan & Multimedia' => 'screen', 'Buku & Perpustakaan' => 'book', 'Alat Olahraga' => 'ball']; ?>
<span class="inventory-thumb" aria-hidden="true">
  <?= ui_icon($categoryIcons[$category ?? ''] ?? 'box') ?>
  <?php if (! empty($photo)): ?>
    <img src="<?= esc(base_url($photo), 'attr') ?>" width="44" height="44" alt="" loading="lazy" decoding="async" onerror="this.remove()">
  <?php endif; ?>
</span>
