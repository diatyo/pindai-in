<?php
$error = $loginError ?? (session()->getFlashdata('login_error') ?: session()->getFlashdata('error'));
$usernameValue = $usernameValue ?? session()->getFlashdata('login_username') ?? '';
$success = session()->getFlashdata('success');
$info = session()->getFlashdata('info');
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<?= view('partials/app_icons') ?>
<title>Masuk — Pindai.in</title>
<link rel="stylesheet" href="<?= base_url('assets/css/needmcp.css') ?>">
<style>
.login-page{min-height:100dvh;display:flex;align-items:center;justify-content:center;padding:24px;position:relative;overflow-y:auto}.login-page:before,.login-page:after{content:"";position:fixed;border-radius:50%;pointer-events:none}.login-page:before{width:380px;height:380px;left:-190px;top:-160px;background:rgba(201,154,66,.14)}.login-page:after{width:420px;height:420px;right:-230px;bottom:-210px;background:rgba(117,133,107,.12)}.login-shell{width:min(100%,1040px);display:grid;background:rgba(255,250,242,.82);border:1px solid rgba(255,255,255,.76);border-radius:30px;box-shadow:var(--shadow);overflow:hidden;backdrop-filter:blur(16px);position:relative;z-index:1}.login-brand{background:var(--brown-2);color:#fff;padding:42px 36px;min-height:290px;display:flex;flex-direction:column;justify-content:space-between;position:relative;overflow:hidden}.login-brand:after{content:"";position:absolute;width:240px;height:240px;border:1px solid rgba(228,189,114,.27);border-radius:50%;right:-90px;bottom:-110px;box-shadow:0 0 0 32px rgba(228,189,114,.05),0 0 0 64px rgba(228,189,114,.035)}.login-logo{font:800 38px/1 Nunito,sans-serif;letter-spacing:-1.2px}.login-logo .dotin{color:var(--gold-2)}.login-logo small{display:block;font:600 11px/1.4 'DM Sans';letter-spacing:2px;text-transform:uppercase;color:#cfbead;margin-top:8px}.login-copy{position:relative;z-index:1;max-width:340px;margin:auto 0}.login-copy h1{font:800 28px/1.16 Nunito;margin:0 0 10px}.login-copy p{color:#d8cec4;margin:0}.login-panel{padding:32px 25px}.login-panel h2{font:800 25px Nunito;margin:0}.login-panel>div+form{margin-top:32px}.login-panel>.muted{display:block;margin:4px 0 24px}.password-wrap{position:relative}.password-wrap .control{padding-right:55px}.password-toggle{position:absolute;right:7px;top:7px;width:40px;height:38px;border:0;background:transparent;color:var(--muted);border-radius:10px;display:grid;place-items:center}.toggle-svg{width:20px;height:20px;fill:none;stroke:currentColor;stroke-width:1.7;stroke-linecap:round;stroke-linejoin:round;display:block}.login-options{display:flex;justify-content:space-between;align-items:center;gap:15px;margin:4px 0 20px;font-size:12px}.login-error{display:none;margin-bottom:14px}.login-error.show{display:block}@media(min-width:760px){.login-shell{grid-template-columns:1.04fr .96fr}.login-brand{min-height:560px;padding:54px}.login-panel{padding:54px 48px;display:flex;flex-direction:column;justify-content:center}.login-copy h1{font-size:36px}}@media(max-width:599px){.login-page{min-height:100dvh;height:auto;padding:20px 12px;place-items:center;overflow:visible}.login-shell{width:100%;height:auto;max-height:none;grid-template-rows:auto auto;border-radius:24px;overflow:hidden}.login-brand{min-height:0;padding:18px 20px;display:block}.login-brand:after{width:150px;height:150px;right:-65px;bottom:-100px}.login-logo{font-size:30px}.login-logo small{font-size:9px;margin-top:5px}.login-copy{display:none}.login-panel{padding:28px 20px 24px;min-height:0;overflow:visible;display:block}.login-panel h2{font-size:23px}.login-panel>div+form{margin-top:30px}.login-panel form{display:grid;gap:0}.login-panel .field{margin-bottom:13px}.login-panel .control{min-height:50px}.login-options{margin:0 0 16px;font-size:12px}.login-panel #loginButton{min-height:52px}}
.login-school{position:absolute;left:0;right:0;bottom:16px;z-index:1;display:block;text-align:center;font:600 10px/1.4 'DM Sans';letter-spacing:1.5px;text-transform:uppercase;color:var(--muted)}.login-school a{color:inherit;text-decoration:none;border-bottom:1px solid rgba(119,107,95,.4)}.login-school a:hover{color:var(--gold)}.remember{display:flex;align-items:center;gap:7px}.remember input{accent-color:var(--gold);width:16px;height:16px}</style>
</head>
<body>
<main class="login-page">
  <section class="login-shell">
    <aside class="login-brand">
      <div class="login-logo">Pindai<span class="dotin">.in</span><small><?= esc(school_name()) ?></small></div>
      <div class="login-copy">
        <span class="eyebrow" style="color:var(--gold-2)">Aset sekolah dalam satu alur</span>
        <h1>Pindai. Pinjam. Lacak.</h1>
        <p>Setiap unit tercatat melalui QR Code.</p>
      </div>
    </aside>
    <section class="login-panel">
      <div><h2>Masuk</h2></div>
      <?= view('partials/notification', ['type' => 'error', 'message' => $error]) ?>
      <?= view('partials/notification', ['type' => 'success', 'message' => $success]) ?>
      <?= view('partials/notification', ['type' => 'info', 'message' => $info]) ?>
      <form action="<?= base_url('login') ?>" method="post">
        <?= csrf_field() ?>
        <div class="field">
          <label for="username">Nama pengguna</label>
          <input class="control" id="username" name="username" autocomplete="username" maxlength="60" value="<?= esc($usernameValue) ?>" placeholder="Masukkan nama pengguna">
        </div>
        <div class="field">
          <label for="password">Kata sandi</label>
          <div class="password-wrap">
            <input class="control" id="password" name="password" type="password" autocomplete="current-password" placeholder="Masukkan kata sandi">
            <button class="password-toggle" type="button" id="togglePassword" aria-label="Tampilkan kata sandi"><?= ui_icon('eye','toggle-svg') ?></button>
          </div>
        </div>
        <div class="login-options">
          <span class="muted">Gunakan akun yang diberikan Admin</span>
          <a class="link" href="<?= base_url('forgot') ?>">Lupa kata sandi?</a>
        </div>
        <button class="btn gold full" id="loginButton" type="submit">Masuk</button>
      </form>
    </section>
  </section>
  <small class="login-school">by <a href="https://smpn1tanjungpinang.sch.id/" target="_blank" rel="noopener">SMP Negeri 1 Tanjungpinang</a></small>
</main>
<script>
const password=document.getElementById('password');
const toggle=document.getElementById('togglePassword');
const eyeOn=`<?= ui_icon("eye","toggle-svg") ?>`;const eyeOff=`<?= ui_icon("eye-off","toggle-svg") ?>`;toggle.addEventListener('click',()=>{const show=password.type==='password';password.type=show?'text':'password';toggle.innerHTML=show?eyeOff:eyeOn;toggle.setAttribute('aria-label',show?'Sembunyikan kata sandi':'Tampilkan kata sandi')});
</script>
</body>
</html>
