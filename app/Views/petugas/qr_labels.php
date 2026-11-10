<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Cetak <?= count($items) ?> QR Terpilih</title>
<?= view('partials/app_icons') ?>
<style>
@page{size:A4;margin:10mm}
*{box-sizing:border-box}
body{margin:0;padding:10mm;background:#fff;color:#3c2e24;font-family:'Segoe UI',system-ui,sans-serif}
.sheet{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8mm;max-width:190mm;margin:auto}
.label{break-inside:avoid;border:2px dashed #c99a42;border-radius:14px;padding:8mm;text-align:center;background:#fff}
.label img{display:block;width:46mm;height:46mm;margin:0 auto 3mm}
.label-name{margin:0;font-weight:700;line-height:1.15;overflow-wrap:anywhere}
.label-code{margin:1.5mm 0 0;color:#5e544b;font-size:11pt;font-weight:700;overflow-wrap:anywhere}
.label-brand-model{margin:0.5mm 0 0;color:#5e544b;font-size:9pt;overflow-wrap:anywhere}
.label-credit{display:block;margin-top:2.5mm;color:#805b25;font-size:7.5pt}
@media screen and (max-width:620px){
  body{padding:16px}
  .sheet{grid-template-columns:minmax(0,1fr);max-width:92mm;gap:16px}
}
@media print{body{padding:0}.sheet{max-width:none}}
</style>
</head>
<body>
<main class="sheet">
  <?php foreach ($items as $item): ?>
  <article class="label">
    <img src="<?= base_url('/petugas/items/' . $item->id . '/qr-image') ?>" alt="QR <?= esc($item->item_code) ?>">
    <?= view('partials/qr_label_identity', ['item' => $item]) ?>
  </article>
  <?php endforeach; ?>
</main>
<script>addEventListener('load',()=>print())</script>
</body>
</html>
