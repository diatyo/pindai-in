<?php
$canSend = $driveReady && $drive['connected'];
$pending = in_array(true, array_column($files, 'pending'), true);
$localFiles = array_filter($files, static fn ($file) => $file['local']);
$timezone = config('App')->appTimezone;
$localTime = static fn (int $time): string => (new DateTimeImmutable('@' . $time))
    ->setTimezone(new DateTimeZone($timezone))->format('d/m/Y H:i');
$state = match (true) {
    $canSend => 'Terhubung',
    $drive['reconnect'] => 'Hubungkan ulang',
    $driveReady => 'Belum terhubung',
    default => 'Belum tersedia',
};
$restoreSelection = session()->getFlashdata('backupRestoreSelection');
$restoreSource = $restoreSelection['source'] ?? '';
$restoreArchive = $restoreSelection['archive'] ?? '';
$restoreSources = array_filter([
    'drive' => $canSend ? 'Google Drive' : null,
    'server' => $localFiles ? 'Server ini' : null,
    'device' => 'Unggah file',
]);
?>
<?= view('layouts/header', ['title' => 'Backup & Pemulihan', 'subtitle' => 'Cadangkan dan pulihkan data inventaris sekolah.']) ?>
<link rel="stylesheet" href="<?= base_url('assets/css/admin-backups.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/admin-backups.css') ?>">
<div class="page backup-page" id="backups" data-timezone="<?= esc($timezone, 'attr') ?>"
    <?= $canSend ? 'data-drive-archives="' . esc(base_url('/admin/backups') . '?driveArchives=1', 'attr') . '"' : '' ?>>
  <div class="page-title responsive-page-title"><div><h1>Backup &amp; Pemulihan</h1></div></div>
  <?php if (! $ready): ?>
    <p class="backup-notice" role="status">Ekstensi ZIP pada PHP perlu diaktifkan untuk membuat dan memulihkan backup.</p>
  <?php endif; ?>

  <div class="backup-grid">
    <section class="card backup-card backup-drive" aria-labelledby="backupDriveTitle">
      <header class="backup-service">
        <span class="backup-icon"><?= ui_icon('archive') ?></span>
        <div class="backup-service-name">
          <h2 id="backupDriveTitle">Google Drive</h2>
          <?php if ($canSend && $drive['email'] !== ''): ?><p class="muted"><?= esc($drive['email']) ?></p><?php endif; ?>
        </div>
        <span class="backup-state badge <?= $canSend ? 'green' : ($drive['reconnect'] ? 'amber' : '') ?>"><?= esc($state) ?></span>
      </header>
      <p class="muted backup-description">Simpan database, foto barang, bukti transaksi, dan logo sekolah dalam satu file .pindai.</p>
      <?php if ($pending && $canSend): ?>
        <p class="backup-notice" role="status">Ada unggahan yang tertunda. Lanjutkan pengiriman file yang sama.</p>
      <?php elseif ($drive['reconnect']): ?>
        <p class="backup-notice" role="status">Izin Google Drive berakhir. Hubungkan ulang akun untuk melanjutkan backup.</p>
      <?php elseif (! $driveReady): ?>
        <p class="backup-notice" role="status">Sambungan Google Drive belum tersedia pada instalasi ini.</p>
      <?php endif; ?>
      <?php if ($driveReady): ?>
        <div class="backup-actions">
          <?php if ($canSend): ?>
          <form method="post" action="<?= base_url('/admin/backups/create') ?>">
            <?= csrf_field() ?>
            <button class="btn gold" type="submit" <?= $ready ? '' : 'disabled' ?>>
              <?= ui_icon('archive') ?> <?= $pending ? 'Lanjutkan unggahan' : 'Backup ke Google Drive' ?>
            </button>
          </form>
          <details class="backup-account">
            <summary class="btn backup-disclosure">Kelola koneksi</summary>
          <?php endif; ?>
            <div class="backup-connection-controls">
              <form id="drive-connect" method="post" action="<?= base_url('/admin/backups/drive/connect') ?>"
                  data-poll="<?= esc(base_url('/admin/backups/drive/poll'), 'attr') ?>" data-waiting="<?= $driveWaiting ? '1' : '0' ?>">
                <?= csrf_field() ?>
                <button class="btn <?= $canSend ? '' : 'gold' ?>" type="submit">
                  <?= $canSend || $drive['reconnect'] ? 'Hubungkan ulang Google Drive' : 'Hubungkan Google Drive' ?>
                </button>
              </form>
              <?php if ($canSend || $drive['reconnect']): ?>
              <form method="post" action="<?= base_url('/admin/backups/drive/disconnect') ?>">
                <?= csrf_field() ?><button class="btn" type="submit">Putuskan sambungan</button>
              </form>
              <?php endif; ?>
            </div>
          <?php if ($canSend): ?></details><?php endif; ?>
        </div>
        <p id="drive-connect-status" class="backup-notice" role="status" hidden></p>
        <a id="drive-connect-link" class="btn backup-fallback" target="_blank" rel="noopener noreferrer" hidden>Buka halaman Google</a>
      <?php endif; ?>
    </section>

    <section class="card backup-card backup-restore" aria-labelledby="backupRestoreTitle">
      <header class="backup-service">
        <span class="backup-icon"><?= ui_icon('clock') ?></span>
        <div class="backup-service-name"><h2 id="backupRestoreTitle">Pulihkan backup</h2></div>
      </header>
      <p class="muted backup-description">Kembalikan database dan foto dari arsip Google Drive atau file backup yang kamu simpan.</p>
      <details class="backup-restore-panel" <?= $restoreSelection ? 'open' : '' ?>>
        <summary class="btn backup-disclosure">Formulir pemulihan</summary>
        <form id="backup-restore-form" method="post" enctype="multipart/form-data" action="<?= base_url('/admin/backups/restore') ?>">
          <?= csrf_field() ?>
          <?php if (count($restoreSources) > 1): ?>
          <fieldset class="backup-restore-fields backup-source-chooser" data-restore-chooser hidden>
            <legend>Pilih sumber backup</legend>
            <div class="backup-source-options">
              <?php foreach ($restoreSources as $source => $label): ?>
              <label>
                <input type="radio" name="restore_source" value="<?= esc($source, 'attr') ?>" <?= $source === $restoreSource ? 'checked' : '' ?>>
                <span><?= esc($label) ?></span>
              </label>
              <?php endforeach; ?>
            </div>
          </fieldset>
          <noscript><p class="muted">Pilih satu sumber backup.</p></noscript>
          <?php endif; ?>
          <?php if ($canSend): ?>
          <fieldset class="backup-restore-fields" data-restore-source="drive">
            <div class="field">
              <label for="restoreDrive">Backup dari Google Drive</label>
              <select class="control" name="drive_backup" id="restoreDrive"
                  data-selected-archive="<?= esc($restoreSource === 'drive' ? $restoreArchive : '', 'attr') ?>" disabled>
                <option value="">Memuat cadangan dari Drive&hellip;</option>
              </select>
            </div>
          </fieldset>
          <?php endif; ?>
          <?php if ($localFiles): ?>
          <fieldset class="backup-restore-fields" data-restore-source="server">
            <div class="field">
              <label for="restoreBackup">Backup di instalasi ini</label>
              <select class="control" name="backup" id="restoreBackup">
                <option value="">Pilih backup di server</option>
                <?php foreach ($localFiles as $file): ?>
                  <option value="<?= esc($file['name'], 'attr') ?>" <?= $restoreSource === 'server' && $file['name'] === $restoreArchive ? 'selected' : '' ?>><?= esc($file['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </fieldset>
          <?php endif; ?>
          <fieldset class="backup-restore-fields" data-restore-source="device">
            <div class="field">
              <label for="restoreArchive">File backup dari perangkat</label>
              <div class="backup-file">
                <input class="control" type="file" name="archive" id="restoreArchive" accept=".pindai,.zip,application/zip">
                <label class="btn" for="restoreArchive" id="restore-file-button" hidden>Pilih file</label>
                <span class="muted" id="restore-file-name" role="status" hidden>Belum ada file dipilih</span>
              </div>
            </div>
          </fieldset>
          <fieldset class="backup-restore-fields" data-restore-confirmation>
            <p class="backup-notice backup-restore-warning">
              <?= ui_icon('warning') ?> Pemulihan mengganti data saat ini. Pindai.in membuat cadangan pengaman terlebih dahulu, lalu semua pengguna perlu masuk kembali.
            </p>
            <div class="field">
              <label for="restorePassword">Kata sandi Admin saat ini</label>
              <input class="control" type="password" name="password" id="restorePassword" autocomplete="current-password" required>
            </div>
            <div class="field">
              <label for="restoreConfirm">Ketik PULIHKAN untuk konfirmasi</label>
              <input class="control" name="confirm" id="restoreConfirm" autocapitalize="characters" required>
            </div>
            <button class="btn backup-restore-submit" type="submit" <?= $ready ? '' : 'disabled' ?>>Pulihkan database dan foto</button>
          </fieldset>
        </form>
      </details>
    </section>

    <section class="card backup-card backup-history" aria-labelledby="backupHistoryTitle">
      <h2 id="backupHistoryTitle">Riwayat backup</h2>
      <p class="muted backup-history-description">Arsip Drive dari akun yang terhubung dan cadangan di instalasi ini.</p>
      <?php if ($canSend): ?>
        <p id="backup-history-status" class="muted" role="status">Memuat riwayat dari Google Drive&hellip;</p>
      <?php endif; ?>
      <div class="backup-empty" id="backup-history-empty" <?= $files || $canSend ? 'hidden' : '' ?>>
        <span class="backup-icon"><?= ui_icon('clock') ?></span>
        <div><strong>Belum ada backup</strong><p class="muted">Cadangan yang tersimpan akan muncul di sini.</p></div>
      </div>
      <ul class="backup-list" id="backup-history-list">
        <?php foreach ($files as $file): ?>
        <li class="backup-record" data-backup-time="<?= (int) $file['time'] ?>"
            <?= $file['driveId'] ? 'data-drive-id="' . esc($file['driveId'], 'attr') . '"' : '' ?>>
          <div class="backup-record-info">
            <strong><?= esc($file['name']) ?></strong>
            <div class="backup-record-meta muted">
              <span><?= esc($localTime((int) $file['time'])) ?><?= $file['bytes'] > 0 ? ' &middot; ' . number_format($file['bytes'] / 1048576, 2, ',', '.') . ' MB' : '' ?></span>
              <span class="backup-state badge <?= $file['driveId'] ? 'green' : ($file['pending'] ? 'amber' : '') ?>"><?= esc($file['drive']) ?></span>
            </div>
            <?php if ($file['error']): ?><p class="backup-record-error"><?= esc($file['error']) ?></p><?php endif; ?>
          </div>
          <div class="backup-record-actions">
            <?php if ($file['local']): ?><a class="btn" href="<?= base_url('/admin/backups/download/' . $file['name']) ?>">Unduh backup</a><?php endif; ?>
            <?php if ($file['driveId']): ?>
              <a class="btn" href="https://drive.google.com/open?id=<?= rawurlencode($file['driveId']) ?>" target="_blank" rel="noopener">Buka di Drive <?= ui_icon('loan') ?></a>
            <?php elseif ($canSend && $file['local']): ?>
              <form method="post" action="<?= base_url('/admin/backups/upload/' . $file['name']) ?>">
                <?= csrf_field() ?><button class="btn" type="submit">Salin ke Google Drive</button>
              </form>
            <?php endif; ?>
          </div>
        </li>
        <?php endforeach; ?>
      </ul>
      <?php if ($canSend): ?>
      <template id="backup-drive-row">
        <li class="backup-record">
          <div class="backup-record-info">
            <strong data-archive-name></strong>
            <div class="backup-record-meta muted"><span data-archive-meta></span><span class="backup-state badge green">Tersimpan di Google Drive</span></div>
          </div>
          <div class="backup-record-actions"><a class="btn" target="_blank" rel="noopener">Buka di Drive <?= ui_icon('loan') ?></a></div>
        </li>
      </template>
      <?php endif; ?>
    </section>
  </div>
</div>
<script src="<?= base_url('assets/js/drive-connect.js') ?>?v=<?= filemtime(FCPATH . 'assets/js/drive-connect.js') ?>" defer></script>
<?= view('layouts/footer') ?>
