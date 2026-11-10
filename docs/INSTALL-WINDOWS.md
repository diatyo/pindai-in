# Windows desktop beta

Pindai.in — **SISTEM INFORMASI PEMINJAMAN DAN MONITORING INVENTARIS BARANG BERBASIS WEB DENGAN IDENTIFIKASI QR CODE**.

Unduh installer `.exe` dan `SHA256SUMS.txt` dari GitHub Releases. Repository
publik menyediakan source ZIP di rilis yang sama dengan installer.

Windows 10/11 x64 dan izin administrator Windows diperlukan. Instal, lalu buka pintasan Pindai.in. Penyiapan
pertama menanyakan nama sekolah serta admin dan kata sandi pilihan sendiri.
Tidak ada akun bawaan atau data operasional sekolah pengembang dalam installer.

Edisi ini untuk akses lokal satu komputer melalui browser. Server memakai
127.0.0.1; akses kamera webcam di localhost mengikuti dukungan browser. Untuk
HP/tablet atau beberapa komputer, pasang source di server HTTPS terpisah.

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

