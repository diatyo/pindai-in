# Pindai.in

**SISTEM INFORMASI PEMINJAMAN DAN MONITORING INVENTARIS BARANG BERBASIS WEB DENGAN IDENTIFIKASI QR CODE**.
Dikembangkan di SMP Negeri 1 Tanjungpinang oleh Dimas Adi Prasetyo.
CodeIgniter 4, PHP 8.3+, dan MySQL.

Pengembangan dilakukan oleh **Dimas Adi Prasetyo** dengan bantuan **OpenAI Codex**
sebagai asisten AI untuk implementasi, penelusuran masalah, peninjauan UI/UX,
pengujian, dan penyiapan distribusi. Lihat [kontributor](CONTRIBUTORS.md).

Versi **0.9.0-beta.2** mencakup inventaris per unit, peminjaman/pengembalian,
kelengkapan serah terima, peminjaman kelompok, kerusakan/penanganan, referensi
kategori/lokasi, audit aktivitas, serta laporan PDF dengan kop yang dapat diubah.
Tampilan petugas mendukung ponsel, tablet, dan desktop pada edisi web.

## Unduh dan pasang

Installer Windows, source ZIP, dan checksum tersedia di
[GitHub Releases](https://github.com/diatyo/pindai-in/releases).
Source publik telah dipisahkan dari arsip pengembangan privat dan memakai riwayat baru.

- **Pengguna Windows:** lihat [panduan Windows](docs/INSTALL-WINDOWS.md).
  Installer menyertakan runtime dan menyiapkan database lokal terpisah.
- **Pengembang/hosting:** lihat [instalasi dari source](docs/INSTALL-SOURCE.md).
  Source ZIP memerlukan Composer dan server MySQL/PHP.
- **Admin sekolah:** lihat [pengaturan kop laporan PDF](docs/PENGATURAN-KOP-PDF.md).

Paket rilis tidak membawa `.env`, database operasional, foto barang, atau sesi.
Admin pertama dibuat menggunakan nama dan kata sandi pilihan pengguna.
Kategori/lokasi awal dapat disesuaikan di menu Data Referensi.

Windows desktop beta ditujukan untuk **satu komputer** (127.0.0.1). Untuk
petugas menggunakan HP/tablet atau beberapa komputer, pasang edisi web di server
HTTPS. Kamera browser memerlukan secure context dan dukungan perangkat.

## Mengembangkan aplikasi

```sh
composer install
php spark migrate --all
php vendor/bin/phpunit --bootstrap vendor/codeigniter4/framework/system/Test/bootstrap.php --no-coverage tests/unit
```

Konfigurasikan database pengembangan sebelum migrasi. Jangan mengarahkan tes
integrasi ke database operasional. `DatabaseSeeder` lama sudah dinonaktifkan; gunakan initializer untuk membuat
admin pertama pada database kosong.
Initializer aman `php spark install:initialize` dijelaskan di panduan source.

Folder utama: `app` (kode aplikasi), `public` (document root), `writable` (data
runtime), `tests` (pengujian), `tools` (packaging), dan `installer/windows`.
Build Windows dapat direproduksi melalui `tools/build-windows.ps1`.

Sebelum memperbarui produksi, cadangkan database dan foto, lalu uji versi baru
menggunakan salinan data. Installer beta belum ditandatangani sertifikat penerbit.
Lisensi aplikasi: [LICENSE](LICENSE). Komponen runtime mempertahankan lisensi
masing-masing; lihat [THIRD-PARTY.txt](installer/windows/THIRD-PARTY.txt).

