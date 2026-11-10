PINDAI.IN - WINDOWS DESKTOP (BETA)
Sistem informasi peminjaman dan monitoring inventaris barang berbasis web dengan identifikasi QR Code

1. Jalankan PindaiIn-Setup-...-win64.exe di Windows 10/11 64-bit.
2. Jika dibutuhkan, izinkan pemasangan Visual C++ Runtime resmi Microsoft.
3. Izinkan akses administrator untuk mendaftarkan Windows Service. Saat
   penyiapan muncul, isikan nama sekolah dan akun admin pertama.
   Simpan kata sandi Anda. Penyiapan awal dapat memerlukan beberapa menit.
4. Browser membuka aplikasi. Login dengan akun yang baru Anda buat.
5. Di Pengaturan admin, sesuaikan identitas sekolah, logo daerah/sekolah,
   kop PDF, dan nama/NIP kepala sekolah. Buat akun petugas dan kepala sekolah.
6. Petugas memasukkan inventaris dan daftar guru/siswa sebelum meminjamkan.

DATABASE KOSONG
Paket tidak membawa data inventaris, foto, siswa, guru, atau akun dari sekolah
pengembang. Kategori/lokasi awal tersedia dan dapat disesuaikan admin.

MENUTUP APLIKASI
Windows Service PindaiIn aktif otomatis saat Windows menyala. Menutup browser
tidak mematikan server. Kelola service melalui services.msc dengan izin admin.
Pintasan Pindai.in membuka browser. Siapkan Pindai.in dapat memperbaiki service.

DATA DAN PEMBARUAN
Data disimpan di %PROGRAMDATA%\PindaiIn\data.
Program ada di %PROGRAMFILES%\PindaiIn.
Untuk upgrade, jalankan installer versi berikutnya pada
folder program yang sama. Saat pertama dibuka setelah upgrade, aplikasi
mencadangkan database, foto, logo, dan konfigurasi ke folder data\backups
sebelum menjalankan migrasi. Cadangkan folder data saat server dihentikan
ke media lain secara berkala. Cadangan memuat kredensial, simpan secara privat.
Uninstall mempertahankan folder data. Jangan hapus folder ini bila masih dipakai.
Service berjalan sebagai LocalService dengan hak terbatas, bukan LocalSystem.

LINGKUP BETA
Aplikasi dapat digunakan melalui komputer, HP, dan tablet yang terhubung ke
server. Installer bawaan memakai 127.0.0.1. Akses perangkat lain melalui LAN
atau tunnel HTTPS seperti ngrok memerlukan penyesuaian konfigurasi server,
alamat aplikasi, dan validasi host. Wizard beta belum mengatur jaringan/tunnel.
Kamera pemindai QR membutuhkan HTTPS dan izin browser pada perangkat pengguna.
Installer belum ditandatangani sertifikat penerbit. Windows SmartScreen dapat
meminta pemeriksaan tambahan; cocokkan SHA256SUMS.txt dari rilis yang sama.
Beta ini belum diuji di seluruh variasi komputer Windows/antivirus.

SOURCE DAN LISENSI
PindaiIn-source-...zip, installer, SHA256SUMS.txt, dan source runtime ada
bersama pada GitHub Releases: https://github.com/diatyo/pindai-in/releases
Akses mengikuti izin repositori. Lihat THIRD-PARTY.txt dan folder licenses.

