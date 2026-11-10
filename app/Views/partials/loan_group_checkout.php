<section class="card loan-group-scan" id="loanGroupScanner" data-lookup-url="<?= esc(base_url('/petugas/loans/groups/lookup'), 'attr') ?>" data-max-units="<?= (int) $maxUnits ?>">
  <button class="btn gold" type="button" id="openGroupScan"><?= ui_icon('scan', 'btn-svg') ?> Scan unit</button>
  <button class="btn" type="button" id="openGroupManual">Masukkan kode</button>
  <p class="muted">Kumpulkan unit terlebih dahulu, lalu periksa kelengkapannya.</p>
  <dialog class="group-scan-dialog" id="groupScanDialog" aria-labelledby="groupScanTitle">
    <header class="group-scan-head"><h2 id="groupScanTitle">Tambah unit</h2><span class="badge amber" id="groupScanTotal">0 unit</span></header>
    <div class="group-scan-body">
    <div id="scan" class="loan-group-camera">
      <?= view('partials/scanner_camera', ['groupScan' => true], ['saveData' => false]) ?>
    </div>
  <details id="groupManualEntry"><summary>Masukkan kode unit</summary>
  <label for="manualCode">Kode unit</label>
  <div class="loan-group-code">
    <input class="control" id="manualCode" maxlength="40" autocapitalize="characters" autocomplete="off" spellcheck="false" placeholder="Contoh: UNT-000001">
    <button class="btn" id="manualLookup" type="button">Tambah</button>
  </div>
  </details>
  <div class="group-scan-recent"><h3>Terakhir ditambahkan</h3><ul id="groupScanRecent"></ul></div>
    </div>
    <footer class="group-scan-footer"><button class="btn gold full" type="button" id="finishGroupScan">Selesai scan · 0 unit</button></footer>
  </dialog>
  <p class="muted loan-group-message" id="loanGroupMessage" role="status" aria-live="polite">Tambahkan 2–<?= (int) $maxUnits ?> unit. Periksa kelengkapan setiap unit sebelum menyimpan.</p>
</section>
<section aria-labelledby="loanGroupListTitle">
  <h3 id="loanGroupListTitle">Unit dalam kelompok <span class="badge amber" id="loanGroupCount"><?= count($groupUnits) ?> unit</span></h3>
  <div id="loanGroupUnits">
    <?php foreach ($groupUnits as $unit): ?><?= view('partials/loan_group_unit', $unit, ['saveData' => false]) ?><?php endforeach; ?>
  </div>
</section>
<label class="accessory-check accessory-confirm">
  <input type="checkbox" name="accessory_confirmed" value="1" required data-required-message="Konfirmasikan pemeriksaan semua unit dan kelengkapannya.">
  <span>Saya sudah memeriksa semua unit dan kelengkapan yang diserahkan.</span>
</label>
