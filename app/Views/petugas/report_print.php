<?php $school = school_name(); $type = $type ?? 'loans'; $typeLabels = ['loans' => 'Peminjaman', 'damages' => 'Kerusakan', 'handling' => 'Penanganan']; $typeLabel = $typeLabels[$type] ?? 'Peminjaman'; ?>
<?php
$status = $status ?? '';
$category = $category ?? '';
$statusLabels = [
    'active' => 'Dipinjam', 'late' => 'Terlambat', 'completed' => 'Selesai',
    'reported' => 'Dilaporkan', 'in_handling' => 'Dalam penanganan',
    'in_progress' => 'Dalam proses', 'unrepairable' => 'Tidak dapat diperbaiki',
];
$periodLabel = 'Semua tanggal';
if (! empty($from) && ! empty($to)) {
    $periodLabel = tgl_id($from) . ' – ' . tgl_id($to);
    $start = strtotime($from);
    $end = strtotime($to);
    if ($start !== false && $end !== false && date('Y-m', $start) === date('Y-m', $end)
        && date('d', $start) === '01' && date('d', $end) === date('t', $end)) {
        $months = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
        $periodLabel = $months[(int) date('n', $start)] . ' ' . date('Y', $start);
    }
} elseif (! empty($from)) {
    $periodLabel = 'Mulai ' . tgl_id($from);
} elseif (! empty($to)) {
    $periodLabel = 'Sampai ' . tgl_id($to);
}
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Laporan <?= esc($typeLabel) ?> — <?= esc($school) ?></title>
<style>
body{font-family:'DejaVu Sans',sans-serif;font-size:11px;color:#2b241d;margin:24px}
h1{font-size:18px;margin:0}
h2{font-size:13px;color:#776b5f;font-weight:600;margin:2px 0 14px}
.report-heading{text-align:center;margin:16px 0 12px;page-break-inside:avoid;color:#000}
.report-heading h1{font-size:18px;font-weight:bold;line-height:1.4}
.report-filters{font-size:11px;margin-top:6px}
.header{border-bottom:1px solid #999;padding-bottom:10px;margin-bottom:14px}
.brand{font-weight:800;font-size:16px;color:#3c2e24}
table{width:100%;border-collapse:collapse}
th,td{border:1px solid #ddd1c2;padding:6px 8px;text-align:left}
th{background:#efe3d2;text-transform:uppercase;font-size:9px;letter-spacing:.5px}
</style>
</head>
<body>
<?= view('partials/school_letterhead', ['printOnly' => false]) ?>
<div class="report-heading">
  <h1>Laporan <?= esc($typeLabel) ?> Barang<br>Periode <?= esc($periodLabel) ?></h1>
  <div class="report-filters">Status: <?= esc($statusLabels[$status] ?? 'Semua status') ?> | Kategori: <?= esc($category !== '' && $category !== 'all' ? $category : 'Semua kategori') ?></div>
</div>
<div class="header">
  <div style="text-align:right"><div style="font-weight:700">Dicetak</div><div><?= esc(date('d M Y H:i')) ?></div></div>
</div>
<table>
  <?php if ($type === 'loans'): ?>
  <thead><tr><th>No</th><th>Nomor</th><th>Unit</th><th>Peminjam</th><th>Jenis</th><th>Tanggal Pinjam</th><th>Batas Kembali</th><th>Tanggal Kembali</th><th>Status</th></tr></thead>
  <tbody>
    <?php foreach ($rows as $i => $r): ?>
    <tr>
      <td><?= $i + 1 ?></td><td><?= esc($r->loan_number) ?></td><td><?= esc($r->item_code) ?> · <?= esc($r->item_name) ?></td>
      <td><?= esc($r->borrower_name) ?></td><td><?= $r->borrower_type === 'teacher' ? 'Guru' : 'Siswa' ?></td>
      <td><?= esc(tgl_id($r->borrowed_at)) ?></td><td><?= esc(tgl_id($r->due_at, true)) ?></td>
      <td><?= esc(tgl_id($r->returned_at)) ?></td><td><?= loan_status_label($r->returned_at, $r->due_at) ?></td>
    </tr>
    <?php endforeach; ?>
  </tbody>
  <?php elseif ($type === 'damages'): ?>
  <thead><tr><th>No</th><th>Unit</th><th>Kerusakan</th><th>Dilaporkan</th><th>Pelapor</th><th>Status</th></tr></thead>
  <tbody>
    <?php foreach ($rows as $i => $r): ?>
    <tr><td><?= $i + 1 ?></td><td><?= esc($r->item_code) ?></td><td><?= esc($r->description) ?></td><td><?= esc(tgl_id($r->discovered_at, true)) ?></td><td><?= esc($r->reported_by_name ?? '—') ?></td><td><?= damage_status_label($r->status) ?></td></tr>
    <?php endforeach; ?>
  </tbody>
  <?php else: ?>
<thead><tr><th>No</th><th>Unit</th><th>Masalah</th><th>Keterangan</th><th>Pelaksana</th><th>Tanggal</th><th>Status</th></tr></thead>
  <tbody>
    <?php foreach ($rows as $i => $r): ?>
    <tr><td><?= $i + 1 ?></td><td><?= esc($r->item_code) ?></td><td><?= esc($r->damage_description) ?></td><td><?= esc($r->action_description) ?></td><td><?= esc($r->executor_name) ?></td><td><?= esc(tgl_id($r->action_date)) ?></td><td><?= $r->status === 'completed' ? 'Selesai' : ($r->status === 'unrepairable' ? 'Tidak diperbaiki' : 'Dalam proses') ?></td></tr>
    <?php endforeach; ?>
  </tbody>
  <?php endif; ?>
</table>
<?php if (! $rows): ?><p>Tidak ada data untuk periode ini.</p><?php endif; ?>
<?= view('partials/report_signature', ['printOnly' => false]) ?>
</body>
</html>
