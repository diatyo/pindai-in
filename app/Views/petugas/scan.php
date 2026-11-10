<?= view('layouts/header', ['title' => 'Scan QR Code', 'subtitle' => 'Pindai unit melalui kamera atau kode unit.']) ?>
<div class="page form-desktop scanner-page" id="scan">
  <div class="page-title scanner-page-title"><div><span class="eyebrow">Identifikasi</span><h1>Scan QR Code</h1><span class="muted">Sistem menentukan transaksi dari status barang.</span></div></div>

  <?= view('partials/scanner_camera') ?>

  <details class="card manual-entry">
    <summary>Masukkan kode secara manual</summary>
    <div class="manual-entry-body">
      <p class="muted">Gunakan kode unit jika kamera tidak tersedia.</p>
      <label for="manualCode">Kode unit</label>
      <div class="row">
        <input class="control" id="manualCode" maxlength="40" autocapitalize="characters" autocomplete="off" spellcheck="false" placeholder="Contoh: PRO-001">
        <button class="btn" id="manualLookup" type="button">Periksa</button>
      </div>
    </div>
  </details>
</div>
<?= view('layouts/footer') ?>
