# Pengaturan kop PDF

Admin dapat mengganti logo sekolah dan daerah melalui Konfigurasi Sistem.
Bagian **Isi kop laporan PDF** menampilkan enam baris sesuai urutan cetaknya.
Di setiap baris, pilih **ukuran font (pt)**, tandai **Tebal** bila diperlukan,
lalu isi kotak teks di bawahnya. Kosongkan baris yang tidak digunakan.

Isi kop yang sudah tersimpan tetap dipakai. Ukuran dan ketebalan awal mengikuti
kop sebelumnya sampai admin mengubahnya. Pengaturan **Ukuran logo** tersedia
terpisah di bawah editor baris.

Telepon sekolah menerima kode area dan pemisah, misalnya `(0771) 21720`,
`0771-21720`, atau `+62 771 21720`. Formatnya tetap dipertahankan saat disimpan.

Gunakan PNG/JPG/WebP maksimal 2 MB. Gambar divalidasi dan dikonversi menjadi PNG,
proporsi dipertahankan. Logo disimpan di writable/branding, di luar document root.
Tombol kembali ke logo bawaan tersedia terpisah untuk kedua logo.

Pratinjau PDF memakai isian yang belum disimpan. Klik Simpan untuk menerapkannya
pada laporan berikutnya. Perubahan pengaturan dan pratinjau tercatat dalam log.
PDF yang telah diunduh tetap merupakan dokumen lama.

Fitur ini memakai tabel settings yang sudah ada; tidak membutuhkan tabel baru.
Cadangkan database dan writable/branding saat memindahkan instalasi.
