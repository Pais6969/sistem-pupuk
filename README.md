# SIMPUPUK v2 — Sistem Informasi Manajemen Pupuk

Aplikasi web untuk menghitung, menetapkan, menyalurkan, dan melaporkan alokasi pupuk
bersubsidi dari kecamatan ke desa (studi kasus: Kecamatan X).
Final Project Praktikum Sistem Informasi — Ahmad Faiz Habibillah (240605110171),
Teknik Informatika UIN Maulana Malik Ibrahim Malang.

## Fitur Utama

- Perhitungan alokasi otomatis: Σ (luas lahan × dosis per hektar)
- Penetapan alokasi (terkunci setelah ditetapkan)
- Pencatatan pengiriman dengan kode verifikasi 6 digit
- Konfirmasi penerimaan oleh kepala desa (jumlah, foto bukti, kode)
- Dashboard, laporan realisasi (Cetak / Simpan PDF), dan log aktivitas
- Hak akses 3 role: Admin Kecamatan, Kepala Desa, Dinas Pertanian

## Teknologi

PHP 8.2 Native · MySQL/MariaDB (PDO) · Bootstrap 5 · Laragon

## Cara Instalasi

1. Clone repo ke `C:/laragon/www/`:
   `git clone https://github.com/Pais6969/sistem-pupuk.git`
2. Jalankan Laragon (Apache + MySQL).
3. Import `database/setup.sql` lewat phpMyAdmin / HeidiSQL.
4. Sesuaikan koneksi database di `config/` bila perlu.
5. Buka `http://localhost/sistem-pupuk`.

## Dokumentasi

Laporan Modul 1–10 dan slide presentasi ada di folder `docs/`.
