<?php
$type = in_array($type ?? '', ['success', 'error', 'info', 'warning'], true) ? $type : 'info';
$messages = is_array($messages ?? null) ? $messages : [$message ?? null];
$messages = array_values(array_filter($messages, static fn ($item): bool => is_string($item) && trim($item) !== ''));
$titles = [
    'success' => 'Berhasil',
    'error' => 'Terjadi kesalahan',
    'info' => 'Informasi',
    'warning' => 'Perhatian',
];
?>
<?php if ($messages): ?>
<div class="state notification <?= esc($type, 'attr') ?>" role="<?= in_array($type, ['error', 'warning'], true) ? 'alert' : 'status' ?>" aria-live="<?= $type === 'error' ? 'assertive' : 'polite' ?>">
  <div class="notification-copy">
    <strong><?= esc($title ?? $titles[$type]) ?></strong>
    <?php foreach ($messages as $item): ?><p><?= esc($item) ?></p><?php endforeach; ?>
  </div>
</div>
<?php endif; ?>
