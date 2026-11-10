# Instalasi dari source

Pindai.in — **Sistem informasi peminjaman dan monitoring inventaris barang berbasis web dengan identifikasi QR Code**.

Gunakan PHP 8.3/8.4, Composer 2, dan MySQL 8.4. Ekstensi PHP: curl, exif,
fileinfo, gd, intl, mbstring, mysqli, openssl, zip. Ekstrak source ZIP ke folder
baru. Paket source tidak membawa vendor, `.env`, database, sesi, atau foto barang.

1. Jalankan `composer install --no-dev --optimize-autoloader`.
2. Buat database kosong dan user MySQL khusus database tersebut.
3. Salin `env` menjadi `.env`. Atur `CI_ENVIRONMENT = production`, `app.baseURL`,
   hostname/port/database/username/password database sesuai server Anda.
4. Arahkan document root Apache/Nginx ke folder **public**, aktifkan rewrite,
   dan pastikan writable dapat ditulis proses PHP. Untuk hosting gunakan HTTPS.
5. Jalankan `php spark migrate --all`.
6. Buat admin pertama dengan `php spark install:initialize`. Command membaca
   JSON melalui stdin. Contoh PowerShell (input kata sandi tersembunyi):

```powershell
$secret = Read-Host 'Kata sandi admin (minimal 12 karakter)' -AsSecureString
$credential = [pscredential]::new('admin', $secret)
@{
    school_name = 'Nama sekolah Anda'
    full_name = 'Nama admin'
    username = 'admin'
    password = $credential.GetNetworkCredential().Password
} | ConvertTo-Json -Compress | php spark install:initialize
Remove-Variable secret,credential
```

Command menolak database yang sudah berisi akun/inventaris/transaksi/pengaturan.
Jangan jalankan `DatabaseSeeder` atau `migrate --seed` pada data produksi.
Seeder lama sudah dinonaktifkan dan menolak perubahan database.

Login, sesuaikan Pengaturan admin (termasuk kop PDF), buat akun petugas/kepala,
dan masukkan data sekolah. Source ini versi beta, termasuk peminjaman kelompok.
Uji dengan salinan data sebelum mengganti aplikasi produksi.

Untuk development: `composer install`, lalu
`php vendor/bin/phpunit --bootstrap vendor/codeigniter4/framework/system/Test/bootstrap.php --no-coverage tests/unit`.
Tes unit memerlukan ekstensi SQLite3 pada PHP pengembang.
Uji integrasi MySQL/browser memiliki kebutuhan tambahan; lihat tests/README.md.
Untuk membangun installer lihat [INSTALL-WINDOWS.md](INSTALL-WINDOWS.md).
