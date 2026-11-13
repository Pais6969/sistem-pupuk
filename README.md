# SIMPUPUK - Sistem Informasi Manajemen Pupuk (PHP Native + MySQL)

## Cara pasang (Laragon)
1. Ekstrak folder `sistem-pupuk` ke `C:\laragon\www\`
2. Start Laragon (Apache + MySQL). Pastikan `mod_rewrite` aktif (default aktif).
3. Buka HeidiSQL -> File -> Run SQL file -> pilih `database/setup.sql`
   (PERINGATAN: database `db_pupuk_kecamatan` lama akan dihapus & dibuat ulang)
4. Cek `config/config.php` bila user/password MySQL Anda bukan `root` / kosong.
5. Buka http://localhost/sistem-pupuk  (atau http://sistem-pupuk.test)

## Akun awal (segera ganti lewat menu Akun Saya)
| Peran | Username | Password |
|---|---|---|
| Admin Kecamatan | admin_kec | admin123 |
| Dinas Pertanian | dinas_pertanian | dinas123 |
| Kepala Desa Sukamaju | kades_sukamaju | kades123 |
Akun kepala desa untuk desa lain: menu Kelola User -> "Buat Akun Kepala Desa Otomatis".

## Alur kerja
1. Admin: isi master (desa, komoditas, pupuk, parameter dosis, musim) dan Luas Lahan per musim.
2. Admin: Perhitungan Alokasi -> Hitung Sekarang. Admin/Dinas: Tetapkan (alokasi terkunci).
3. Admin: Realisasi -> Catat Pengiriman (muncul KODE VERIFIKASI 6 digit untuk petugas).
4. Kepala Desa: Penerimaan Pupuk -> isi jumlah yang benar-benar diterima + foto bukti (wajib)
   + kode verifikasi. Jika kurang dari yang dikirim: status "Tidak Sesuai" dan catatan wajib.
5. Admin/Dinas: tinjau penerimaan yang tidak sesuai. Batas konfirmasi 3 hari
   (`KONFIRMASI_HARI` di config), lewat batas tampil peringatan merah.
6. Realisasi resmi di dashboard & laporan = jumlah DITERIMA kades, bukan jumlah dikirim.

## Pembaruan tampilan (v2)
- Memakai Bootstrap 5.3 + Bootstrap Icons dari CDN (butuh internet saat membuka aplikasi).
- CSS/JS memakai nama baru `assets/css/simpupuk.css` dan `assets/js/simpupuk.js`.
  Hapus `assets/css/style.css` / `assets/js/app.js` lama bila masih ada di folder Anda.
- Foto bukti: diperkecil otomatis (browser & server, maks 1600 px), tabel memakai thumbnail,
  klik foto untuk melihat versi besar dalam jendela.
- Laporan: tombol "Cetak / Simpan PDF" membuka halaman `laporan-cetak` (tanpa menu).
- Log aktivitas tidak mencatat login/logout.

## Catatan
- Butuh PHP 8.0+ dengan ekstensi pdo_mysql & fileinfo (bawaan Laragon).
- Tombol "Simpan PDF" memakai dialog cetak browser (pilih "Simpan sebagai PDF").
- Foto bukti disimpan di `uploads/bukti` dan hanya bisa dibuka lewat aplikasi (ada cek hak akses).
- Setelah dipakai sungguhan, ubah `DEBUG` di `config/config.php` menjadi `false`.
