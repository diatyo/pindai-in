<?php
$principalName = trim((string) ($principalName ?? app_setting('kepala_sekolah_name', '')));
$principalNip = trim((string) ($principalNip ?? app_setting('kepala_sekolah_nip', '')));
$signatureMonths = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
$signatureDate = date('j') . ' ' . $signatureMonths[(int) date('n')] . ' ' . date('Y');
?>
<style>
.report-signature{width:300px;margin:24px 0 0 auto;page-break-inside:avoid;break-inside:avoid;color:#000;font-size:11px;line-height:1.5;text-align:left}
.report-signature-space{height:64px}
.report-signature-name{font-weight:bold;text-decoration:underline}
.report-signature-print-only{display:none}
@media print{.report-signature-print-only{display:block}}
</style>
<div class="report-signature<?= ! empty($printOnly) ? ' report-signature-print-only' : '' ?>">
  <div>Diketahui,</div>
  <div>Kepala Sekolah, <?= esc($signatureDate) ?></div>
  <div class="report-signature-space"></div>
  <div class="report-signature-name"><?= esc($principalName !== '' ? $principalName : '........................................') ?></div>
  <div>NIP. <?= esc($principalNip !== '' ? $principalNip : '........................................') ?></div>
</div>
