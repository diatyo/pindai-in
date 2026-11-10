/* Pindai.in — Scanner QR via kamera (BarcodeDetector native) + fallback kode manual */
(function () {
  const video = document.getElementById('scannerVideo');
  const state = document.getElementById('scanState');
  const manual = document.getElementById('manualCode');
  const startButton = document.getElementById('startScan');
  const manualButton = document.getElementById('manualLookup');
  const groupMode = video?.hasAttribute('data-group-scan');
  const cameraPanel = groupMode ? video.closest('dialog') : null;
  const loanStep = groupMode ? video.closest('fieldset') : null;
  let stream = null;
  let scanTimer = null;
  let detector = null;
  let detectorMode = '';
  let frameCanvas = null;
  let frameContext = null;
  let scanning = false;
  let cameraStarting = false;
  let lookupPending = false;
  let cameraRequestId = 0;
  let lastCode = '';

  function escapeHtml(value) {
    return String(value || '').replace(/[&<>'"]/g, (character) => ({
      '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;'
    })[character]);
  }

  function setState(badge, title, message, tone) {
    if (!state) return;
    const stateTone = tone || 'amber';
    state.className = 'scanner-state is-' + stateTone;
    state.innerHTML = '<span class="badge ' + stateTone + '">' + escapeHtml(badge) + '</span>' +
      '<h3 id="scannerHeading">' + escapeHtml(title) + '</h3><p>' + escapeHtml(message) + '</p>';
  }

  function setError(title, message) {
    setState('Pemindai bermasalah', title, message, 'red');
  }

  function setCameraControl(mode) {
    if (!startButton) return;
    const active = mode === 'scanning';
    const busy = mode === 'starting' || mode === 'checking';
    const label = active ? 'Hentikan pemindaian' : busy ? 'Menyiapkan kamera' : 'Aktifkan kamera';
    startButton.classList.toggle('is-scanning', active);
    startButton.disabled = busy;
    startButton.setAttribute('aria-label', label);
    startButton.title = label;
  }

  function prepareDetector() {
    detector = null;
    detectorMode = '';
    if ('BarcodeDetector' in window) {
      try {
        detector = new window.BarcodeDetector({ formats: ['qr_code'] });
        detectorMode = 'native';
        return true;
      } catch (error) {
        detector = null;
      }
    }
    if (typeof window.jsQR === 'function') {
      frameCanvas = frameCanvas || document.createElement('canvas');
      frameContext = frameContext || frameCanvas.getContext('2d', { willReadFrequently: true });
      detectorMode = frameContext ? 'jsqr' : '';
    }
    return detectorMode !== '';
  }

  async function detectCodes() {
    if (detectorMode === 'native') return detector.detect(video);
    const width = video.videoWidth;
    const height = video.videoHeight;
    if (!width || !height || !frameContext) return [];
    if (frameCanvas.width !== width || frameCanvas.height !== height) {
      frameCanvas.width = width;
      frameCanvas.height = height;
    }
    frameContext.drawImage(video, 0, 0, width, height);
    const image = frameContext.getImageData(0, 0, width, height);
    const result = window.jsQR(image.data, width, height, { inversionAttempts: 'attemptBoth' });
    return result && result.data ? [{ rawValue: result.data }] : [];
  }

  function stopCamera() {
    cameraRequestId += 1;
    scanning = false;
    lastCode = '';
    if (scanTimer) {
      window.clearTimeout(scanTimer);
      scanTimer = null;
    }
    if (stream) {
      stream.getTracks().forEach((track) => track.stop());
      stream = null;
    }
    if (video) video.srcObject = null;
    setCameraControl('idle');
  }

  function scheduleDetection() {
    if (scanning) scanTimer = window.setTimeout(detectFrame, 300);
  }

  async function detectFrame() {
    if (!scanning || !detectorMode || !video || !video.videoWidth) {
      scheduleDetection();
      return;
    }
    try {
      const requestId = cameraRequestId;
      const codes = await detectCodes();
      if (!scanning || requestId !== cameraRequestId) return;
      const nearest = groupMode && codes.length > 1 ? codes.reduce((best, code) => {
        const distance = candidate => {
          const points = candidate.cornerPoints;
          if (!points?.length) return Infinity;
          const x = points.reduce((sum, point) => sum + point.x, 0) / points.length;
          const y = points.reduce((sum, point) => sum + point.y, 0) / points.length;
          return (x - video.videoWidth / 2) ** 2 + (y - video.videoHeight / 2) ** 2;
        };
        return distance(code) < distance(best) ? code : best;
      }) : codes?.[0];
      const value = nearest?.rawValue || '';
      if (groupMode) {
        if (value && value !== lastCode) lookupCode(value);
        lastCode = value;
      } else if (value) {
        stopCamera();
        lookupCode(value);
        return;
      }
    } catch (error) {
      // Frame detection may fail while the camera is focusing; continue scanning.
    }
    scheduleDetection();
  }

  async function startCamera() {
    if (!video || scanning || cameraStarting) return;
    if (groupMode && (loanStep.hidden || !cameraPanel.open)) return;
    if (!window.isSecureContext) {
      setError('Kamera memerlukan koneksi aman', 'Gunakan HTTPS atau localhost. Akses melalui alamat IP HTTP hanya mendukung input kode manual.');
      return;
    }
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
      setError('Kamera tidak tersedia', 'Peramban atau perangkat tidak menyediakan akses kamera. Gunakan kode unit manual.');
      return;
    }
    if (!prepareDetector()) {
      setError('Pemindai tidak tersedia', 'Decoder QR lokal gagal dimuat. Muat ulang halaman atau gunakan kode unit manual.');
      return;
    }

    cameraStarting = true;
    stopCamera();
    const requestId = cameraRequestId;
    setCameraControl('starting');
    setState('Menyiapkan kamera…', 'Mengaktifkan kamera', 'Izinkan akses kamera saat diminta peramban.');

    try {
      const requestedStream = await navigator.mediaDevices.getUserMedia({
        video: { facingMode: { ideal: 'environment' } },
        audio: false
      });
      if (requestId !== cameraRequestId) {
        requestedStream.getTracks().forEach((track) => track.stop());
        return;
      }
      stream = requestedStream;
      video.srcObject = stream;
      await video.play();
      if (requestId !== cameraRequestId) return;
      scanning = true;
      setCameraControl('scanning');
      setState('Kamera siap', 'Arahkan kamera ke QR Code unit', groupMode
        ? 'Scan QR berikutnya untuk menambah unit ke kelompok.'
        : 'Sistem menentukan transaksi dari status barang.', 'green');
      scheduleDetection();
    } catch (error) {
      stopCamera();
      const denied = error && (error.name === 'NotAllowedError' || error.name === 'SecurityError');
      setError(denied ? 'Izin kamera ditolak' : 'Kamera gagal diaktifkan', denied
        ? 'Aktifkan izin kamera di peramban, atau gunakan kode unit manual.'
        : 'Pastikan kamera tidak sedang dipakai aplikasi lain, lalu coba kembali.');
    } finally {
      cameraStarting = false;
    }
  }

  async function lookupCode(code) {
    const value = String(code || '').trim();
    if (groupMode) {
      video.dispatchEvent(new CustomEvent('pindai:group-code', {detail: value}));
      return;
    }
    if (!value) {
      setError('Kode unit belum diisi', 'Masukkan kode unit atau pindai QR Code terlebih dahulu.');
      if (manual) manual.focus();
      return;
    }
    if (lookupPending) return;

    lookupPending = true;
    stopCamera();
    setCameraControl('checking');
    if (manualButton) manualButton.disabled = true;
    setState('Memeriksa unit…', value, 'Mencocokkan kode dengan data inventaris.');

    const name = (document.querySelector('meta[name="csrf-name"]') || {}).content || 'csrf_test_name';
    const token = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
    const body = new URLSearchParams({ code: value });
    body.append(name, token);

    try {
      const response = await fetch('/petugas/scan/lookup', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body
      });
      const json = await response.json();
      if (json.ok && json.redirect) {
        window.location.assign(json.redirect);
        return;
      }
      setError('QR tidak dikenali', json.message || 'Kode unit tidak ditemukan.');
    } catch (error) {
      setError('Terjadi kesalahan', 'Server tidak dapat memeriksa kode. Coba kembali.');
    } finally {
      lookupPending = false;
      if (manualButton) manualButton.disabled = false;
      setCameraControl('idle');
    }
  }

  window.addEventListener('pagehide', stopCamera);
  document.addEventListener('visibilitychange', function () {
    if (document.hidden) stopCamera();
  });

  document.addEventListener('DOMContentLoaded', function () {
    if (startButton) startButton.addEventListener('click', function () {
      if (scanning) {
        stopCamera();
        setState('Kamera dihentikan', 'Pemindaian dijeda', 'Tekan tombol untuk mengaktifkan kamera kembali.', 'blue');
      } else {
        startCamera();
      }
    });
    if (manualButton) manualButton.addEventListener('click', () => lookupCode(manual ? manual.value : ''));
    if (manual) manual.addEventListener('keydown', (event) => {
      if (event.key === 'Enter') {
        event.preventDefault();
        lookupCode(manual.value);
      }
    });
    if (groupMode) {
      video.addEventListener('pindai:group-feedback', ({detail}) => {
        setState(detail.title || 'Daftar diperbarui', detail.tone === 'green' ? 'Siap scan unit berikutnya' : 'Scan beberapa unit', detail.text, detail.tone === 'neutral' ? 'amber' : detail.tone);
      });
      video.addEventListener('pindai:group-open', startCamera);
      cameraPanel.addEventListener('close', stopCamera);
      new MutationObserver(() => {
        if (loanStep.hidden) {
          stopCamera();
          cameraPanel.close();
        }
      }).observe(loanStep, {attributes: true, attributeFilter: ['hidden']});
    } else if (document.visibilityState === 'visible') {
      window.requestAnimationFrame(startCamera);
    }
  });
})();
