<?php
// ===== Konfigurasi aplikasi =====
define('APP_NAME', 'SIMPUPUK');
define('APP_SUB', 'Sistem Informasi Manajemen Pupuk');
define('APP_INSTANSI', 'Dinas Pertanian Kabupaten Y');
define('KONFIRMASI_HARI', 3);               // batas hari konfirmasi kepala desa
define('MAX_UPLOAD_BYTES', 5 * 1024 * 1024); // 5 MB per foto bukti

// ===== Database (default Laragon) =====
define('DB_HOST', 'localhost');
define('DB_NAME', 'db_pupuk_kecamatan');
define('DB_USER', 'root');
define('DB_PASS', '147369');

date_default_timezone_set('Asia/Jakarta');

// Ubah ke false saat sudah dipakai sungguhan (agar detail error tidak tampil)
define('DEBUG', true);
