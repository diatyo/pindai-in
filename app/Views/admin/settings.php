<?php $settings = $settings ?? []; $letterhead = $letterhead ?? \App\Libraries\SchoolLetterhead::values($settings); ?>
<?= view('layouts/header', ['title' => 'Konfigurasi Sistem', 'subtitle' => 'Identitas sekolah, logo, dan kop laporan PDF.']) ?>
<div class="page form-desktop" id="settings">
  <div class="page-title"><div><span class="eyebrow">Konfigurasi</span><h1>Konfigurasi Sistem</h1></div></div>
  <form class="card mt-18" method="post" enctype="multipart/form-data" action="<?= base_url('/admin/settings/save') ?>">
    <?= csrf_field() ?>
    <div class="field"><label>Nama sekolah</label><input class="control" name="school_name" value="<?= esc(old('school_name', $settings['school_name'] ?? '', false)) ?>"></div>
    <div class="field"><label>Alamat sekolah</label><input class="control" name="school_address" value="<?= esc(old('school_address', $settings['school_address'] ?? '', false)) ?>"></div>
    <div class="grid2">
      <div class="field"><label>Telepon sekolah</label><input class="control" name="school_phone" type="text" inputmode="numeric" pattern="[0-9]*" title="Hanya angka (0-9)" oninput="this.value = this.value.replace(/[^0-9]/g, '')" value="<?= esc(old('school_phone', $settings['school_phone'] ?? '', false)) ?>"></div>
      <div class="field"><label>Nama Kepala Sekolah</label><input class="control" name="kepala_sekolah_name" value="<?= esc(old('kepala_sekolah_name', $settings['kepala_sekolah_name'] ?? '', false)) ?>"></div>
    </div>
    <div class="field"><label for="kepala_sekolah_nip">NIP Kepala Sekolah</label><input id="kepala_sekolah_nip" class="control" name="kepala_sekolah_nip" type="text" inputmode="numeric" pattern="[0-9]*" title="Hanya angka (0-9)" oninput="this.value = this.value.replace(/[^0-9]/g, '')" value="<?= esc(old('kepala_sekolah_nip', $settings['kepala_sekolah_nip'] ?? '', false)) ?>"></div>
    <div class="field"><label>Kontak bantuan (halaman lupa sandi)</label><textarea class="control" name="help_contact" rows="2"><?= esc(old('help_contact', $settings['help_contact'] ?? '', false)) ?></textarea></div>
    <section class="school-branding-section" aria-labelledby="schoolLogoHeading">
      <h2 id="schoolLogoHeading">Logo pada laporan</h2>
      <p class="muted">Logo daerah di kiri dan logo sekolah di kanan kop. Gunakan PNG, JPG, atau WebP maksimal 2 MB.</p>
      <div class="grid2 school-logo-grid">
        <?php foreach (\App\Libraries\SchoolLetterhead::LOGOS as $key => $default): ?>
        <div class="school-logo-field" data-school-logo>
          <label for="<?= $key ?>"><?= $key === 'letterhead_city_logo' ? 'Logo daerah' : 'Logo sekolah' ?></label>
          <div class="school-logo-preview"><img src="<?= esc(\App\Libraries\SchoolLetterhead::logoData($key, $letterhead), 'attr') ?>" data-default-logo="<?= esc(\App\Libraries\SchoolLetterhead::logoData($key, []), 'attr') ?>" alt="<?= $key === 'letterhead_city_logo' ? 'Logo daerah saat ini' : 'Logo sekolah saat ini' ?>" data-logo-preview></div>
          <input class="control" type="file" name="<?= $key ?>" id="<?= $key ?>" accept="image/png,image/jpeg,image/webp" data-logo-input>
          <label class="school-logo-reset"><input type="checkbox" name="reset_<?= $key ?>" value="1"> Gunakan kembali logo bawaan</label>
        </div>
        <?php endforeach; ?>
      </div>
    </section>
    <section class="school-branding-section" aria-labelledby="letterheadHeading">
      <h2 id="letterheadHeading">Isi kop laporan PDF</h2>
      <p class="muted">Sesuaikan nama instansi dan identitas resmi sekolah. Baris keterangan yang dikosongkan akan disembunyikan pada kop.</p>
      <?php foreach (\App\Libraries\SchoolLetterhead::TEXT as $key => $field): ?>
      <div class="field"><label for="<?= $key ?>"><?= esc($field[0]) ?></label><textarea class="control" name="<?= $key ?>" id="<?= $key ?>" maxlength="<?= $field[2] ?>" rows="<?= in_array($key, ['letterhead_contact', 'letterhead_accreditation'], true) ? 2 : 1 ?>"><?= esc(old($key, $letterhead[$key], false)) ?></textarea></div>
      <?php endforeach; ?>
      <details class="school-letterhead-sizes"><summary>Ukuran tulisan dan logo</summary><div class="grid2">
        <?php foreach (\App\Libraries\SchoolLetterhead::SIZES as $key => $field): ?>
        <div class="field"><label for="<?= $key ?>"><?= esc($field[0]) ?></label><input class="control" type="number" name="<?= $key ?>" id="<?= $key ?>" min="<?= $field[2] ?>" max="<?= $field[3] ?>" step="0.5" required value="<?= esc(old($key, (string) $letterhead[$key], false), 'attr') ?>"></div>
        <?php endforeach; ?>
      </div></details>
    </section>
    <p class="muted school-branding-note">Pratinjau PDF memakai isian dan logo yang sedang dipilih. Tekan Simpan Konfigurasi untuk menerapkannya pada laporan berikutnya.</p>
    <div class="school-settings-actions">
      <button class="btn" id="previewLetterhead" type="submit" formaction="<?= base_url('/admin/settings/preview') ?>" formtarget="_blank">Pratinjau PDF</button>
      <button class="btn gold" type="submit">Simpan Konfigurasi</button>
    </div>
    <p id="letterheadPreviewError" role="alert" hidden></p>
    <a id="letterheadPreviewLink" class="btn" target="_blank" rel="noopener" hidden>Buka pratinjau PDF</a>
  </form>
</div>
<style>
#settings .school-branding-section{border-top:1px solid var(--line);margin-top:24px;padding-top:20px}
#settings .school-branding-section h2{font-size:19px;margin:0 0 8px}
#settings .school-branding-section>p{margin:0 0 18px;line-height:1.6}
#settings .school-logo-field{min-width:0;border:1px solid var(--line);border-radius:14px;padding:14px}
#settings .school-logo-field>label:first-child{font-weight:700}
#settings .school-logo-preview{display:grid;place-items:center;min-height:120px;margin:10px 0;background:#fff;border:1px solid var(--line);border-radius:10px}
#settings .school-logo-preview img{max-width:100%;max-height:96px;object-fit:contain}
#settings .school-logo-field input[type=file]{max-width:100%;font-size:13px;padding:10px}
#settings .school-logo-reset{display:flex;align-items:center;gap:8px;margin-top:12px;min-height:44px;font-size:13px}
#settings .school-logo-reset input{width:18px;height:18px;flex:none}
#settings .school-letterhead-sizes summary{min-height:44px;cursor:pointer;font-weight:700;margin-bottom:12px}
#settings .school-branding-note{font-size:13px;line-height:1.6;margin:20px 0 14px}
#settings .school-settings-actions{display:grid;grid-template-columns:1fr 1fr;gap:10px}
#settings .school-settings-actions .btn{min-height:48px}
#settings [hidden]{display:none!important}
#letterheadPreviewError{color:var(--red);margin-top:12px}
@media(max-width:600px){#settings .school-logo-grid,#settings .school-settings-actions{grid-template-columns:minmax(0,1fr)}}
</style>
<script>
const previewButton = document.getElementById('previewLetterhead');
previewButton.form.addEventListener('submit', async event => {
  if (event.submitter !== previewButton) return;
  event.preventDefault();
  const form = previewButton.form;
  const error = document.getElementById('letterheadPreviewError');
  const link = document.getElementById('letterheadPreviewLink');
  const previewWindow = window.open('about:blank', '_blank');
  if (previewWindow) previewWindow.document.body.textContent = 'Menyiapkan pratinjau PDF…';
  const buttons = Array.from(form.querySelectorAll('button[type=submit]'));
  buttons.forEach(button => { button.disabled = true; });
  error.hidden = link.hidden = true;
  try {
    const response = await fetch(previewButton.formAction, {method:'POST', body:new FormData(form), headers:{'X-Requested-With':'XMLHttpRequest'}});
    const token = response.headers.get('X-CSRF-Token');
    if (token) {
      const csrfName = document.querySelector('meta[name=csrf-name]').content;
      document.getElementsByName(csrfName).forEach(input => { input.value = token; });
      document.querySelector('meta[name=csrf-token]').content = token;
    }
    if (!response.ok || !response.headers.get('Content-Type')?.includes('application/pdf')) {
      const result = await response.json().catch(() => ({}));
      throw new Error(result.message || 'Pratinjau gagal. Muat ulang halaman dan coba kembali.');
    }
    const url = URL.createObjectURL(await response.blob());
    link.href = url;
    link.hidden = false;
    if (previewWindow) previewWindow.location.replace(url);
  } catch (failure) {
    if (previewWindow) previewWindow.close();
    error.textContent = failure.message;
    error.hidden = false;
  } finally {
    buttons.forEach(button => { button.disabled = false; });
  }
});
document.querySelectorAll('[data-school-logo]').forEach(field => {
  const input = field.querySelector('[data-logo-input]');
  const image = field.querySelector('[data-logo-preview]');
  const reset = field.querySelector('input[type=checkbox]');
  const original = image.src;
  input.addEventListener('change', () => {
    const file = input.files[0];
    if (!file) { image.src = original; return; }
    reset.checked = false;
    const reader = new FileReader();
    reader.onload = () => { if (input.files[0] === file && !reset.checked) image.src = reader.result; };
    reader.readAsDataURL(file);
  });
  reset.addEventListener('change', () => { if (reset.checked) input.value = ''; image.src = reset.checked ? image.dataset.defaultLogo : original; });
});
</script>
<?= view('layouts/footer') ?>
