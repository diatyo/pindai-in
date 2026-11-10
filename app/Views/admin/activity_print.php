<?php $school = school_name(); ?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Riwayat Aktivitas — <?= esc($school) ?></title>
<style>
body{font-family:'DejaVu Sans',sans-serif;font-size:11px;color:#2b241d;margin:24px}
h1{font-size:18px;margin:0}h2{font-size:13px;color:#776b5f;font-weight:600;margin:2px 0 14px}
.header{border-bottom:1px solid #999;padding-bottom:10px;margin-bottom:14px}
.brand{font-weight:800;font-size:16px;color:#3c2e24}
table{width:100%;border-collapse:collapse}th,td{border:1px solid #ddd1c2;padding:6px 8px;text-align:left}th{background:#efe3d2;text-transform:uppercase;font-size:9px}.activity-detail-cell{white-space:pre-line}
</style>
</head>
<body>
<?= view('partials/school_letterhead', ['printOnly' => false]) ?>
<div class="header">
  <div><h1>Riwayat Aktivitas</h1><h2>Periode: <?php if (! empty($from) && ! empty($to)): ?><?= esc(tgl_id($from)) ?> &ndash; <?= esc(tgl_id($to)) ?><?php elseif (! empty($from)): ?>Mulai <?= esc(tgl_id($from)) ?><?php elseif (! empty($to)): ?>Sampai <?= esc(tgl_id($to)) ?><?php else: ?>Semua tanggal<?php endif; ?></h2></div>
  <div style="text-align:right"><div style="font-weight:700">Dicetak</div><div><?= esc(date('d M Y H:i')) ?></div></div>
</div>
<table>
  <thead><tr><th>Waktu</th><th>Pengguna</th><th>Peran</th><th>Aktivitas</th><th>Objek</th><th>Detail</th><th>IP</th></tr></thead>
  <tbody>
    <?php foreach ($logs as $log): ?>
    <tr>
      <td><?= esc(tgl_id($log->created_at, true)) ?></td>
      <td><?= esc($log->user_name) ?></td>
      <td><?= esc($log->role ?? '—') ?></td>
      <td><?= esc(ucfirst(str_replace('_', ' ', $log->action))) ?></td>
      <td><?= esc($log->object ?? '—') ?></td>
      <td class="activity-detail-cell"><?= esc(str_replace('; ', "\n", $log->detail ?? '—')) ?></td>
      <td><?= esc($log->ip_address ?? '—') ?></td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
<?php if (! $logs): ?><p>Tidak ada aktivitas.</p><?php endif; ?>
<?= view('partials/report_signature', ['printOnly' => false]) ?>
</body>
</html>