# Backup dan Pemulihan Pindai.in

Menu **Backup & Pemulihan** pada akun Admin membuat file `.pindai` berisi data aplikasi, foto barang, bukti kerusakan dan pengembalian, serta logo kop yang diunggah. QR yang menggunakan kode unit dapat dibuat kembali oleh aplikasi. Siapkan instalasi Pindai.in dengan skema database yang sama untuk pemulihan di server pengganti.

## Backup ke Google Drive sebagai Admin

1. Buka **Backup & Pemulihan**, lalu klik **Hubungkan Google Drive**. Tab Google akan terbuka. Pilih akun Google sekolah dan berikan izin. Status di tab Pindai.in diperbarui otomatis. Jika browser menahan tab baru, klik **Buka halaman Google**.
2. Setelah kembali ke Pindai.in, klik **Backup ke Google Drive**. Setelah berhasil, pilih **Buka di Drive** dari riwayat.
3. Jika pengiriman tertunda, klik **Lanjutkan unggahan**. Pindai.in memakai file sementara yang sama dan melanjutkan dari posisi yang dikonfirmasi Drive. Jika sesi unggahan kedaluwarsa, file yang sama dikirim ulang memakai ID Drive yang sama.

Jika Admin memilih akun Google lain, unggahan yang tertunda tetap menunggu akun asalnya. Akun baru dapat membuat backup sendiri.

Riwayat memuat sampai 50 arsip Pindai.in terbaru dari akun Google Drive yang terhubung, termasuk arsip yang dibuat dari instalasi lain. Gunakan akun Google yang sama di hosting, Windows, atau Laragon untuk melihat arsip Drive yang sama. Cadangan lokal juga tetap tampil di instalasi tempat file tersebut disimpan.

Server perlu ruang sementara untuk membuat dan mengirim satu paket. Pindai.in menghapus paket sementara setelah ukuran dan checksum file dikonfirmasi oleh Drive. Riwayat menyimpan tautan dan informasi file. File `.pindai` berisi data sekolah, jadi batasi akses di Google Drive dan pada salinan yang diunduh.

## Sambungan Google Drive untuk Laragon, Windows, dan hosting

Ketiga lingkungan memakai layanan OAuth Pindai.in di `https://pindaiin.my.id`. Google mengirim callback ke `https://pindaiin.my.id/admin/backups/drive/callback`. Browser menyelesaikan persetujuan di tab terpisah sementara tab Admin memeriksa status melalui server Pindai.in yang sedang dipakai. Port Laragon dan port yang dipilih installer tidak perlu didaftarkan ke Google. Koneksi dari HP bekerja selama HP dapat membuka instalasi Pindai.in tersebut dan server aplikasinya dapat menjangkau internet.

Server pusat menukar kode dengan PKCE S256 dan meminta akses offline dengan scope `drive.file`. Server itu menyimpan access token dan refresh token dalam rekaman terenkripsi AES-256-GCM. Setiap instalasi menyimpan kunci sambungannya sendiri di folder privat. Saat membuat backup, server instalasi meminta access token yang masih valid, lalu mengunggah arsip langsung ke Google Drive. Layanan OAuth tidak menerima isi arsip.

**Hubungkan ulang Google Drive** memperbarui sambungan. Bila Google tidak memberikan refresh token baru, sambungan valid sebelumnya dipertahankan untuk akun yang sama. **Putuskan sambungan** menghapus token yang disimpan untuk instalasi tersebut dan mempertahankan file backup yang sudah ada. Untuk mencabut izin Google bagi seluruh instalasi pada akun tersebut, kelola akses Pindai.in dari akun Google.

## Penyiapan satu kali oleh pengelola hosting

1. Pasang versi ini di `pindaiin.my.id`. Gunakan `app.baseURL = 'https://pindaiin.my.id/'` dan konfigurasi production.
2. Masukkan Client ID dan Client Secret **Web application** yang sudah dibuat ke konfigurasi privat hosting sebagai `backup.driveClientId` dan `backup.driveClientSecret`. Callback HTTPS yang sudah didaftarkan dipakai oleh semua instalasi. Installer dan kode sumber membawa alamat layanan, sedangkan Client Secret disimpan di hosting.
3. PHP di tiap instalasi memerlukan `curl`, `openssl`, dan `zip`. Simpan folder `writable/backups` di luar document root dan beri akses hanya ke akun yang menjalankan aplikasi. Cadangan `.pindai` tidak menyertakan token, rekaman sambungan, atau kunci enkripsinya. Untuk pemulihan layanan OAuth pusat, perlakukan folder itu beserta kunci lokalnya sebagai data rahasia.
4. Periksa konfigurasi access log web server dan proxy untuk jalur callback. Catat path saja tanpa query string, karena Google mengirim authorization code pada query callback. Di Apache gunakan format khusus untuk rute ini dengan `%m %U %H`, tanpa `%r`, `%q`, atau header referer. Nonaktifkan perekaman payload request untuk kanal OAuth. Atur ini pada virtual host atau melalui pengelola hosting.
5. Uji dengan akun yang diizinkan pada **Audience**. Pada mode **External / Testing**, hanya akun yang ada di daftar penguji dapat terhubung, dan izin Drive berlaku tujuh hari. Uji **Backup ke Google Drive** dan buka tautan file dari riwayat.
6. Instalasi yang pernah memakai integrasi lama perlu klik **Hubungkan Google Drive** kembali. Setelah koneksi baru terbukti, hapus salinan pengaturan lama `drive-client.json` dan token lama `drive.json` dari folder privat jika ada. Arsip backup lama tetap dapat dipulihkan.

Instalasi yang sudah menggunakan `backup.password` tetap memakai enkripsi arsip, dan kunci tersebut dapat digunakan untuk membuka ZIP lama.

## Pulihkan database dan foto

1. Buka **Formulir pemulihan** dan pilih sumber yang tersedia, **Google Drive**, **Server ini**, atau **Unggah file**. Pilih arsip dari sumber tersebut. Gunakan cadangan yang dibuat oleh instalasi yang Anda percayai. Untuk file unggahan besar, sesuaikan batas unggahan PHP di hosting.
2. Setelah memilih arsip, isi kata sandi Admin saat ini dan ketik `PULIHKAN`. Sistem memverifikasi paket dan membuat cadangan dengan awalan nama `pindai-sebelum-pulih-` sebelum mengganti data. Jika foto di server sudah hilang, cadangan sebelum pemulihan mencatat referensi yang tidak bisa disalin agar pemulihan dari paket yang lengkap tetap dapat berjalan.
3. Setelah selesai, masuk dengan akun dari backup dan buka beberapa detail barang beserta fotonya untuk memeriksa hasil. Simpan cadangan sebelum pemulihan sampai pemeriksaan selesai.

Cadangan pengaman sebelum pemulihan tersimpan di `writable/backups` untuk berjaga jika perlu kembali ke kondisi sebelumnya.
