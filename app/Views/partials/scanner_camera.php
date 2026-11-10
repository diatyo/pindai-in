  <section class="scanner-stage" aria-labelledby="scannerHeading">
    <div class="scanner-frame">
      <video class="scanner" id="scannerVideo"<?= ! empty($groupScan) ? ' data-group-scan' : '' ?> muted playsinline aria-label="Pratinjau kamera pemindai QR Code"></video>
      <div class="scanner-guide" aria-hidden="true"><i></i><i></i><i></i><i></i></div>
      <button class="scanner-control" id="startScan" type="button" aria-label="Aktifkan kamera" title="Aktifkan kamera">
        <span class="scanner-control-camera" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 8.5A2.5 2.5 0 0 1 6.5 6h2l1.2-1.5h4.6L15.5 6h2A2.5 2.5 0 0 1 20 8.5v8a2.5 2.5 0 0 1-2.5 2.5h-11A2.5 2.5 0 0 1 4 16.5z"/><circle cx="12" cy="12.5" r="3.5"/></svg></span>
        <span class="scanner-control-pause" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M9 7v10M15 7v10"/></svg></span>
      </button>
    </div>
    <div class="scanner-state is-amber" id="scanState" role="status" aria-live="polite" aria-atomic="true">
        <span class="badge amber"><?= ! empty($groupScan) ? 'Scan beberapa unit' : 'Menyiapkan kamera' ?></span>
        <h3 id="scannerHeading"><?= ! empty($groupScan) ? 'Scan QR unit satu per satu' : 'Membuka pemindai QR Code' ?></h3>
        <p>Izinkan akses kamera saat diminta peramban.</p>
    </div>
  </section>
