<?php
/**
 * Empty-state seragam untuk daftar/kartu kosong.
 * Variabel opsional: $text (string), $icon (nama ui_icon), $flush (bool).
 * $flush=true untuk elemen yang sudah berada di dalam .card/.timeline (tanpa border sendiri).
 */
$text = $text ?? 'Belum ada data.';
$icon = $icon ?? 'inbox';
$flush = $flush ?? false;
?>
<div class="empty-state<?= $flush ? ' is-flush' : '' ?>">
  <span class="empty-ico" aria-hidden="true"><?= ui_icon($icon, 'empty-svg') ?></span>
  <p><?= esc($text) ?></p>
</div>