<?php if (isset($pager) && $pager): ?>
<?php
$pgTotal = $pager->getTotal();
$pgPerPage = $pager->getPerPage();
$pgCurrent = $pager->getCurrentPage();
$pgStart = $pgTotal ? (($pgCurrent - 1) * $pgPerPage) + 1 : 0;
$pgEnd = min($pgCurrent * $pgPerPage, $pgTotal);
$pgPageCount = $pager->getPageCount();
$pgWindowStart = max(1, min($pgCurrent - 1, $pgPageCount - 2));
$pgWindowEnd = min($pgPageCount, $pgWindowStart + 2);
$pgQuery = $_GET ?? [];
?>
<nav class="pager-bar" aria-label="Navigasi halaman">
  <div class="pager-context">
    <form class="pager-perpage" method="get" action="">
      <?php foreach ($pgQuery as $pgKey => $pgVal): ?>
        <?php if ($pgKey === 'per_page' || $pgKey === 'page' || str_starts_with((string) $pgKey, 'page_') || is_array($pgVal)) { continue; } ?>
        <input type="hidden" name="<?= esc($pgKey, 'attr') ?>" value="<?= esc($pgVal, 'attr') ?>">
      <?php endforeach; ?>
      <label for="perPageSelect">Baris</label>
      <select class="control" id="perPageSelect" name="per_page" onchange="this.form.submit()">
        <?php foreach ([10, 50, 100, 1000] as $pgOption): ?>
        <option value="<?= $pgOption ?>" <?= $pgPerPage === $pgOption ? 'selected' : '' ?>><?= $pgOption ?></option>
        <?php endforeach; ?>
      </select>
    </form>
    <div class="pager-summary">
      <?php if ($pgTotal): ?>
        Menampilkan <b><?= $pgStart ?></b>&ndash;<b><?= $pgEnd ?></b> dari <b><?= $pgTotal ?></b> data
      <?php else: ?>
        Tidak ada data
      <?php endif; ?>
    </div>
  </div>
  <div class="pager-controls">
    <?php if ($pgPageCount > 1): ?>
      <ul class="pagination" role="list">
        <li class="pg-edge<?= $pgCurrent <= 1 ? ' disabled' : '' ?>">
          <?php if ($pgCurrent > 1): ?><a href="<?= esc($pager->getPageURI($pgCurrent - 1)) ?>" aria-label="Halaman sebelumnya" rel="prev">&lsaquo;</a><?php else: ?><span aria-hidden="true">&lsaquo;</span><?php endif; ?>
        </li>
        <?php for ($pgPage = $pgWindowStart; $pgPage <= $pgWindowEnd; $pgPage++): ?>
        <li class="pg-num<?= $pgPage === $pgCurrent ? ' active' : '' ?>">
          <a href="<?= esc($pager->getPageURI($pgPage)) ?>"<?= $pgPage === $pgCurrent ? ' aria-current="page"' : '' ?>><?= $pgPage ?></a>
        </li>
        <?php endfor; ?>
        <li class="pg-edge<?= $pgCurrent >= $pgPageCount ? ' disabled' : '' ?>">
          <?php if ($pgCurrent < $pgPageCount): ?><a href="<?= esc($pager->getPageURI($pgCurrent + 1)) ?>" aria-label="Halaman berikutnya" rel="next">&rsaquo;</a><?php else: ?><span aria-hidden="true">&rsaquo;</span><?php endif; ?>
        </li>
      </ul>
    <?php endif; ?>
  </div>
</nav>
<?php endif; ?>
