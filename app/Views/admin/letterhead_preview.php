<!doctype html>
<html lang="id"><head><meta charset="utf-8"><title>Pratinjau kop laporan</title>
<style>@page{margin:18mm 14mm}body{font:11px Arial,sans-serif;color:#000}h1{font-size:16px;text-align:center;margin:20px 0 10px}.sample{width:100%;border-collapse:collapse}.sample th,.sample td{padding:9px;border:1px solid #bbb}.sample th{background:#eee}.note{color:#555;font-size:10px}</style>
</head><body>
<?= view('partials/school_letterhead', ['letterhead' => $letterhead, 'logos' => $logos]) ?>
<h1>PRATINJAU LAPORAN INVENTARIS</h1>
<p class="note">Contoh tata letak kop pada laporan PDF A4 lanskap. Pratinjau ini memakai isian formulir yang belum disimpan.</p>
<table class="sample"><thead><tr><th>Kode unit</th><th>Barang</th><th>Peminjam</th><th>Status</th></tr></thead><tbody><tr><td>CONTOH-001</td><td>Proyektor sekolah</td><td>Contoh peminjam</td><td>Tersedia</td></tr></tbody></table>
<?= view('partials/report_signature', ['principalName' => $settings['kepala_sekolah_name'] ?? '', 'principalNip' => $settings['kepala_sekolah_nip'] ?? '']) ?>
</body></html>
