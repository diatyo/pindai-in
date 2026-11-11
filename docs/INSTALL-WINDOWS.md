# Windows desktop beta

Pindai.in — **Sistem informasi peminjaman dan monitoring inventaris barang berbasis web dengan identifikasi QR Code**.

Unduh installer `.exe` dan `SHA256SUMS.txt` dari GitHub Releases. Repository
publik menyediakan source ZIP di rilis yang sama dengan installer.

Windows 10/11 x64 dan izin administrator Windows diperlukan. Instal, lalu buka pintasan Pindai.in. Penyiapan
pertama menanyakan nama sekolah serta admin dan kata sandi pilihan sendiri.
Tidak ada akun bawaan atau data operasional sekolah pengembang dalam installer.

Pindai.in mendukung akses melalui komputer, HP, dan tablet pada server yang sama.
Konfigurasi bawaan installer memakai `127.0.0.1`, sehingga akses langsung dari
perangkat lain belum aktif. Akses melalui LAN/HTTPS atau tunnel HTTPS seperti
ngrok memerlukan penyesuaian alamat aplikasi, bind server, dan validasi host.
Pengaturan jaringan/tunnel belum tersedia dalam wizard beta ini. File `.env`
desktop dihasilkan launcher, sehingga mengeditnya langsung bukan konfigurasi
jaringan yang persisten.

Tunnel meneruskan koneksi HTTPS ke server lokal; alamat aplikasi dan penanganan
host tetap harus sesuai agar tautan, login, dan pemindai bekerja dari HP.
Kamera browser memerlukan secure context serta izin pengguna.
Lihat [dokumentasi ngrok](https://ngrok.com/docs/gateway/endpoints/http) dan
[ketentuan kamera browser](https://developer.mozilla.org/en-US/docs/Web/API/MediaDevices/getUserMedia).

Data bersama di komputer berada di `%PROGRAMDATA%\PindaiIn\data`, program di
`%PROGRAMFILES%\PindaiIn`. Laragon dan database hosting tetap terpisah.
Windows Service **PindaiIn** menyala otomatis, menjalankan runtime sendiri
sebagai **LocalService**. Pintasan hanya membuka browser. Kelola service melalui
`services.msc` dengan izin administrator. Hentikan sebelum membuat
salinan seluruh folder data ke media cadangan. Simpan cadangan secara privat.

Installer berikutnya menghentikan service dan memasang program di lokasi sama.
Penyiapan mencadangkan SQL,
foto, logo, dan konfigurasi sebelum migrasi ketika versi berubah. Jika migrasi
gagal, aplikasi tidak membuka web; simpan log dan cadangan untuk pemulihan.
Jangan menjalankan versi lama pada database yang sudah dimigrasikan tanpa
memulihkan cadangannya. Uninstall tidak menghapus folder data.

Admin dapat membuat cadangan aplikasi ke Google Drive dan memilih arsip dari
Drive, server, atau perangkat saat memulihkan. Ikuti [panduan backup dan
pemulihan](BACKUP-PEMULIHAN.md). Koneksi Drive memerlukan internet dan akun
Google yang dapat mengotorisasi aplikasi Pindai.in.

Installer belum ditandatangani sertifikat penerbit, sehingga SmartScreen bisa
menampilkan peringatan. Cocokkan hash lewat `Get-FileHash <installer> -Algorithm SHA256`.
Pengujian pada komputer pengembang tidak menggantikan uji Windows bersih.

## Build untuk pengembang

1. Siapkan source, PHP Windows x64 **8.4.25 TS**, MySQL Community **8.4.3 x64**,
   Composer, .NET Framework compiler bawaan Windows, dan Inno Setup **7.1.1**.
2. Letakkan redistributable resmi Microsoft `vc_redist.x64.exe` di
   `build/installer-tools/` (tanda tangan Microsoft harus valid).
3. Jalankan `tools/build-windows.ps1` dengan parameter PhpRoot, MySqlRoot,
   ComposerPhar, dan InnoCompiler sesuai komputer Anda.
4. Uji payload melalui `tests/windows-installer-smoke.ps1`, lalu jalankan
   `tests/windows-service-smoke.ps1` dari PowerShell administrator untuk menguji
   service terpisah, startup otomatis, restart server, dan shutdown.
5. Sertakan source PHP/MySQL dengan versi yang sama, lisensi, serta hash pada
   rilis yang sama. Script tidak menyalin config atau datadir runtime sumber.

Build memakai daftar folder yang diizinkan; `.env`, runtime data, foto unggahan,
dokumen pribadi di docs, dan backup tidak masuk source ZIP. Vendor diambil dari
composer.lock untuk installer; pengguna source ZIP menjalankan Composer sendiri.

