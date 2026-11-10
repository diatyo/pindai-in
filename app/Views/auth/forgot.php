<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<?= view('partials/app_icons') ?>
<title>Lupa Kata Sandi — Pindai.in</title>
<link rel="stylesheet" href="<?= base_url('assets/css/needmcp.css') ?>">
<style>
.forgot-page{min-height:100dvh;display:flex;align-items:center;justify-content:center;padding:24px;position:relative;overflow:hidden}.forgot-page:before,.forgot-page:after{content:"";position:fixed;border-radius:50%;pointer-events:none}.forgot-page:before{width:380px;height:380px;left:-190px;top:-160px;background:rgba(201,154,66,.14)}.forgot-page:after{width:420px;height:420px;right:-230px;bottom:-210px;background:rgba(117,133,107,.12)}.forgot-shell{width:min(100%,1040px);display:grid;background:rgba(255,250,242,.82);border:1px solid rgba(255,255,255,.76);border-radius:30px;box-shadow:var(--shadow);overflow:hidden;backdrop-filter:blur(16px);position:relative;z-index:1}.forgot-brand{background:var(--brown-2);color:#fff;padding:42px 36px;min-height:290px;display:flex;flex-direction:column;justify-content:space-between;position:relative;overflow:hidden}.forgot-brand:after{content:"";position:absolute;width:240px;height:240px;border:1px solid rgba(228,189,114,.27);border-radius:50%;right:-90px;bottom:-110px;box-shadow:0 0 0 32px rgba(228,189,114,.05),0 0 0 64px rgba(228,189,114,.035)}.forgot-logo{font:800 38px/1 Nunito,sans-serif;letter-spacing:-1.2px}.forgot-logo .dotin{color:var(--gold-2)}.forgot-logo small{display:block;font:600 11px/1.4 'DM Sans';letter-spacing:2px;text-transform:uppercase;color:#cfbead;margin-top:8px}.forgot-copy{position:relative;z-index:1;max-width:340px;margin:auto 0}.forgot-copy h1{font:800 30px/1.16 Nunito;margin:8px 0 10px}.forgot-copy p{color:#d8cec4;margin:0}.forgot-panel{padding:44px 38px;display:flex;flex-direction:column;justify-content:center}.forgot-panel h2{font:800 27px/1.2 Nunito;margin:8px 0 10px}.forgot-panel>.muted{margin:0}.forgot-contact{margin:24px 0 18px;padding:18px;border:1px solid var(--line);border-radius:16px;background:rgba(239,227,210,.38)}.forgot-contact b{display:block;font:700 15px Nunito;margin-bottom:5px}.forgot-contact p{margin:0;color:var(--muted)}.forgot-back{display:flex;align-items:center;justify-content:center;width:100%;min-height:50px;text-align:center;text-decoration:none}
.forgot-school{position:absolute;left:0;right:0;bottom:16px;z-index:1;display:block;text-align:center;font:600 10px/1.4 'DM Sans';letter-spacing:1.5px;text-transform:uppercase;color:var(--muted)}.forgot-school a{color:inherit;text-decoration:none;border-bottom:1px solid rgba(119,107,95,.4)}.forgot-school a:hover{color:var(--gold)}
@media(min-width:760px){.forgot-shell{grid-template-columns:1.04fr .96fr}.forgot-brand{min-height:560px;padding:54px}.forgot-panel{padding:54px 48px}}
@media(max-width:599px){.forgot-page{min-height:100dvh;padding:20px 12px;overflow:auto}.forgot-shell{border-radius:24px}.forgot-brand{min-height:0;padding:18px 20px;display:block}.forgot-brand:after{width:150px;height:150px;right:-65px;bottom:-100px}.forgot-logo{font-size:30px}.forgot-logo small{font-size:9px;margin-top:5px}.forgot-copy{display:none}.forgot-panel{padding:28px 20px 24px}.forgot-panel h2{font-size:23px}.forgot-contact{margin:20px 0 16px}}
</style>
</head>
<body>
<main class="forgot-page">
  <section class="forgot-shell">
    <aside class="forgot-brand">
      <div class="forgot-logo">Pindai<span class="dotin">.in</span><small><?= esc(school_name()) ?></small></div>
      <div class="forgot-copy">
        <span class="eyebrow" style="color:var(--gold-2)">Aset sekolah dalam satu alur</span>
        <h1>Pindai. Pinjam. Lacak.</h1>
        <p>Setiap unit tercatat melalui QR Code.</p>
      </div>
    </aside>
    <section class="forgot-panel">
      <span class="eyebrow">Bantuan akun</span>
      <h2>Lupa kata sandi?</h2>
      <p class="muted">Kata sandi lama tidak dapat dilihat kembali. Hubungi Admin untuk mengatur ulang kata sandi akun Anda.</p>
      <div class="forgot-contact">
        <b>Hubungi Admin</b>
        <p><?= esc(app_setting('help_contact', 'Ruang Tata Usaha · Jam kerja sekolah')) ?></p>
      </div>
      <a class="btn gold forgot-back" href="<?= base_url('login') ?>">Kembali ke halaman masuk</a>
    </section>
  </section>
  <small class="forgot-school">by <a href="https://smpn1tanjungpinang.sch.id/" target="_blank" rel="noopener">SMP Negeri 1 Tanjungpinang</a></small>
</main>
</body>
</html>
