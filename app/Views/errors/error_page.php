<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title><?= esc($title) ?> — Pindai.in</title>
<link rel="stylesheet" href="<?= base_url('assets/css/needmcp.css') ?>">
</head>
<body>
<div class="page" style="min-height:100vh;display:grid;place-items:center">
  <div class="card" style="width:min(100%,460px);text-align:center">
    <div class="brand-name">Pindai<span class="dotin">.in</span><small>by SMP NEGERI 1 TANJUNGPINANG</small></div>
    <div style="font-size:50px;margin-top:22px"><?= $code === 404 ? '🔍' : '🚫' ?></div>
    <h1 style="font:800 26px Nunito;margin:10px 0 6px"><?= esc($title) ?></h1>
    <p class="muted"><?= esc($message) ?></p>
    <a class="btn gold" style="margin-top:16px" href="<?= base_url('/') ?>">Kembali ke beranda</a>
  </div>
</div>
</body>
</html>