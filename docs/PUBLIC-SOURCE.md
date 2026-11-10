# Source publik

Repo publik dibuat dari daftar file source yang diizinkan dengan riwayat Git
baru. Dokumen pribadi, screenshot data sekolah, hasil pengujian, database,
unggahan, log, sesi, konfigurasi lokal, serta alat eksperimen internal dikeluarkan.
Riwayat repo pengembangan lama tetap disimpan secara privat.

Kode aplikasi, migrasi struktur, pengujian dengan fixture dummy, installer, dan
panduan tetap tersedia. Tidak ada akun atau password demo bawaan. Siapkan admin
pertama dengan installer atau command install:initialize.

DatabaseSeeder lama dinonaktifkan agar tidak mengosongkan data atau membuat akun
contoh tanpa sengaja. Menghapus file dari versi baru tidak menghapus salinan yang
sudah pernah diunduh dari versi lama. Jangan mempublikasikan kembali arsip lama.

Distribusi menggunakan tools/SourceDistribution.psm1. Gunakan
tools/export-public-source.ps1 untuk membuat snapshot baru, lalu lakukan audit
dan pemeriksaan isi secara manual sebelum publikasi.
