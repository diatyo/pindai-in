<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>QR <?= esc($item->item_code) ?></title>
<style>
@page{margin:10mm}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',system-ui,sans-serif;display:flex;justify-content:center;align-items:center;min-height:100vh;background:#fff}
.pdf-label-page{padding-top:10mm}
.label{width:340px;max-width:100%;margin:0 auto;border:2px dashed #c99a42;border-radius:18px;padding:20px;text-align:center;background:#fff}
img{width:210px;height:210px;display:block;margin:0 auto 10px}
.label-name{margin:0;color:#3c2e24;font-weight:700;line-height:1.15;word-wrap:break-word;overflow-wrap:anywhere}
.label-code{margin:1.5mm 0 0;color:#5e544b;font-size:11pt;font-weight:700;word-wrap:break-word;overflow-wrap:anywhere}
.label-brand-model{margin:0.5mm 0 0;color:#5e544b;font-size:9pt;word-wrap:break-word;overflow-wrap:anywhere}
.label-credit{display:block;margin-top:2.5mm;color:#805b25;font-size:7.5pt}
@media print{body{min-height:auto}.label{width:100%}}
</style>
</head>
<body<?= ! empty($pdf) ? ' class="pdf-label-page"' : '' ?>>
<div class="label">
  <img src="<?= esc(qr_data_uri($item->item_code), 'attr') ?>" alt="QR Code">
  <?= view('partials/qr_label_identity', ['item' => $item]) ?>
</div>
<?php if (empty($pdf)): ?>
<script>addEventListener('load', () => print())</script>
<?php endif; ?>
</body>
</html>
