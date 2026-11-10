<?php
$name = trim($item->item_name);
$nameWidth = mb_strwidth($name, 'UTF-8');
$nameSize = match (true) {
    $nameWidth > 42 => 16,
    $nameWidth > 26 => 19,
    default => 22,
};
$brand = trim($item->brand ?? '');
$model = trim($item->model ?? '');
$brandModel = implode(' / ', array_filter([$brand, $model], static fn (string $part): bool => $part !== ''));
$school = school_name();
if (strcasecmp($school, 'SMP Negeri 1 Tanjungpinang') === 0) {
    $school = 'SMP Negeri 1 Tanjungpinang';
}
?>
<h1 class="label-name" style="font-size:<?= $nameSize ?>pt"><?= esc($name) ?></h1>
<p class="label-code"><?= esc($item->item_code) ?></p>
<?php if ($brandModel !== ''): ?>
<p class="label-brand-model"><?= esc($brandModel) ?></p>
<?php endif; ?>
<small class="label-credit"><b>Pindai.in</b> by <?= esc($school) ?></small>
