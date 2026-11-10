<?php
$letterhead = $letterhead ?? \App\Libraries\SchoolLetterhead::values();
$logos = $logos ?? [];
foreach (\App\Libraries\SchoolLetterhead::LOGOS as $key => $default) {
    $logos[$key] = $logos[$key] ?? \App\Libraries\SchoolLetterhead::logoData($key, $letterhead);
}
?>
<style>
.school-letterhead{color:#000;font-family:Arial,Helvetica,sans-serif;margin:0 0 10px;page-break-inside:avoid;border-bottom:4px double #000;padding-bottom:5px}
.school-letterhead table{width:100%;border:0;border-collapse:collapse;table-layout:fixed;margin:0;background:#fff}
.school-letterhead table td{border:0;padding:0;vertical-align:middle;background:#fff;text-align:center;letter-spacing:normal}
.school-letterhead .letterhead-logo{width:12%}
.school-letterhead .letterhead-copy{width:76%;white-space:normal;overflow-wrap:break-word}
.school-letterhead .letterhead-logo img{display:block;margin:0 auto;max-width:100%}
.school-letterhead .letterhead-city{width:auto;height:<?= (float) $letterhead['letterhead_city_height'] ?>px}
.school-letterhead .letterhead-school{width:auto;height:<?= (float) $letterhead['letterhead_school_height'] ?>px}
.school-letterhead .letterhead-government{font-size:<?= (float) $letterhead['letterhead_government_size'] ?>pt;line-height:1.1;font-weight:bold}
.school-letterhead .letterhead-name{font-size:<?= (float) $letterhead['letterhead_school_size'] ?>pt;line-height:1.15;font-weight:bold}
.school-letterhead .letterhead-detail{font-size:<?= (float) $letterhead['letterhead_detail_size'] ?>pt;line-height:1.2;font-weight:normal}
.school-letterhead .letterhead-website{font-size:<?= (float) $letterhead['letterhead_detail_size'] + 0.5 ?>pt;line-height:1.2;font-weight:normal}
.school-letterhead-print-only{display:none}
.school-letterhead-print-only .letterhead-government{font-size:12pt}
.school-letterhead-print-only .letterhead-name{font-size:10pt}
.school-letterhead-print-only .letterhead-detail{font-size:7pt}
.school-letterhead-print-only .letterhead-website{font-size:8pt}
.school-letterhead-print-only .letterhead-city{width:auto;height:72px}
.school-letterhead-print-only .letterhead-school{width:auto;height:80px}
@media print{.school-letterhead-print-only{display:block}.school-letterhead{-webkit-print-color-adjust:exact;print-color-adjust:exact}.mobile-list,.pagination,.modal{display:none!important}.table-wrap{display:block!important;overflow:visible!important}.table{min-width:0!important}}
</style>
<div class="school-letterhead<?= ! empty($printOnly) ? ' school-letterhead-print-only' : '' ?>">
  <table role="presentation"><colgroup><col style="width:12%"><col style="width:76%"><col style="width:12%"></colgroup><tbody><tr>
    <td class="letterhead-logo" style="width:12%"><?php if ($logos['letterhead_city_logo']): ?><img class="letterhead-city" src="<?= esc($logos['letterhead_city_logo'], 'attr') ?>" alt="Logo daerah"><?php endif; ?></td>
    <td class="letterhead-copy" style="width:76%">
      <?php foreach (['government' => 'government', 'department' => 'government', 'school_name' => 'name', 'accreditation' => 'detail', 'contact' => 'detail', 'website' => 'website'] as $key => $class): ?>
        <?php if ($letterhead['letterhead_' . $key] !== ''): ?><div class="letterhead-<?= $class ?>"><?= nl2br(esc($letterhead['letterhead_' . $key])) ?></div><?php endif; ?>
      <?php endforeach; ?>
    </td>
    <td class="letterhead-logo" style="width:12%"><?php if ($logos['letterhead_school_logo']): ?><img class="letterhead-school" src="<?= esc($logos['letterhead_school_logo'], 'attr') ?>" alt="Logo sekolah"><?php endif; ?></td>
  </tr></tbody></table>
</div>
