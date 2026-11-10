# Pelaporan keamanan

Jangan menyertakan `.env`, password, token, database, log operasional, atau data
guru/siswa dalam issue, screenshot, dan pull request. Gunakan data dummy untuk
langkah reproduksi. Gunakan fitur private vulnerability reporting GitHub jika
tersedia untuk masalah keamanan yang belum diperbaiki.

Source publik dan installer tidak memuat data operasional sekolah. File program
yang dapat dibaca publik tidak menggantikan perlindungan instalasi: konfigurasi
database harus privat, document root harus menunjuk ke public, dan penggunaan
di jaringan memerlukan HTTPS. Ikuti docs/INSTALL-SOURCE.md.

Gunakan tools/audit-public-source.php sebelum distribusi dan periksa kembali
gambar serta arsip rilis. Pemeriksaan otomatis berbasis pola tidak menjamin
semua jenis data sensitif dapat dideteksi.
