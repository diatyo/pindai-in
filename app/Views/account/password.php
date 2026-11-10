<?php $errors = session()->getFlashdata('errors') ?? []; ?>
<?= view('layouts/header', ['title' => 'Ubah Kata Sandi', 'subtitle' => 'Perbarui kata sandi akun Anda.']) ?>
<div class='page form-desktop account-password-page' id='accountPassword'>
  <div class='page-title'>
    <div><span class='eyebrow'>Keamanan akun</span><h1>Ubah Kata Sandi</h1></div>
  </div>

  <article class='card account-password-card'>
    <?php if ($forced): ?>
      <?= view('partials/notification', ['type' => 'warning', 'title' => 'Kata sandi sementara', 'message' => 'Buat kata sandi baru sebelum melanjutkan ke sistem.']) ?>
    <?php endif; ?>
    <?= view('partials/notification', ['type' => 'error', 'title' => 'Periksa kembali isian', 'messages' => $errors]) ?>

    <form class='account-password-form' method='post' action='<?= base_url('/account/password') ?>'>
      <?= csrf_field() ?>
      <div class='field'>
        <label for='currentPassword'>Kata sandi saat ini</label>
        <input class='control' id='currentPassword' name='current_password' type='password' autocomplete='current-password' required>
        <?php if ($forced): ?><small class='muted'>Masukkan kata sandi sementara dari Admin.</small><?php endif; ?>
      </div>
      <div class='field'>
        <label for='newPassword'>Kata sandi baru</label>
        <input class='control' id='newPassword' name='new_password' type='password' autocomplete='new-password' minlength='8' required>
        <small class='muted'>Minimal 8 karakter dan berbeda dari kata sandi saat ini.</small>
      </div>
      <div class='field'>
        <label for='passwordConfirm'>Ulangi kata sandi baru</label>
        <input class='control' id='passwordConfirm' name='password_confirm' type='password' autocomplete='new-password' minlength='8' required>
      </div>
      <div class='row account-password-actions'>
        <?php if (! $forced): ?><a class='btn' href='<?= base_url(role_home(session()->get('role'))) ?>'>Batal</a><?php endif; ?>
        <button class='btn gold full' type='submit'>Simpan Kata Sandi</button>
      </div>
    </form>
  </article>
</div>
<?= view('layouts/footer') ?>
