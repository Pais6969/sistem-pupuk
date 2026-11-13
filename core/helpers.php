<?php
/* =============== Umum =============== */
function e($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function url(string $path = ''): string { return BASE_URL . '/' . ltrim($path, '/'); }

function redirect(string $path = ''): void {
    header('Location: ' . url($path));
    exit;
}

function fmt($n, int $dec = 2): string {
    $s = number_format((float)$n, $dec, ',', '.');
    if ($dec > 0 && strpos($s, ',') !== false) { $s = rtrim(rtrim($s, '0'), ','); }
    return $s;
}

function tgl(?string $d, bool $jam = false): string {
    if (!$d) return '-';
    $bln = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    $t = strtotime($d);
    if (!$t) return '-';
    $out = date('j', $t) . ' ' . $bln[(int)date('n', $t)] . ' ' . date('Y', $t);
    return $jam ? $out . ' ' . date('H:i', $t) : $out;
}

function post(string $k, string $d = ''): string { return trim((string)($_POST[$k] ?? $d)); }
function get(string $k, string $d = ''): string { return trim((string)($_GET[$k] ?? $d)); }

/** Ambil angka desimal dari input; null bila tidak valid. */
function num($v): ?float {
    $v = str_replace(',', '.', trim((string)$v));
    return is_numeric($v) ? (float)$v : null;
}

function zona_label(string $z): string {
    return ['dataran_rendah' => 'Dataran Rendah', 'dataran_tinggi' => 'Dataran Tinggi', 'semua' => 'Semua Zona'][$z] ?? $z;
}

function badge(string $text, string $cls = 'gray'): string {
    return '<span class="badge rounded-pill badge-' . e($cls) . '">' . e($text) . '</span>';
}

/** Kelompokkan baris berdasarkan kunci (untuk tampilan sel desa yang digabung / rowspan). */
function group_rows(array $rows, string $key): array {
    $g = [];
    foreach ($rows as $r) { $g[$r[$key]][] = $r; }
    return $g;
}

/* =============== Flash =============== */
function flash(string $type, string $msg): void { $_SESSION['flash'][] = [$type, $msg]; }
function flash_render(): string {
    $icon = ['success' => 'check-circle-fill', 'danger' => 'exclamation-octagon-fill', 'warning' => 'exclamation-triangle-fill', 'info' => 'info-circle-fill'];
    $out = '';
    foreach ($_SESSION['flash'] ?? [] as [$t, $m]) {
        $out .= '<div class="alert alert-' . e($t) . ' alert-dismissible fade show d-flex align-items-start gap-2" role="alert">'
              . '<i class="bi bi-' . ($icon[$t] ?? 'info-circle-fill') . ' mt-1"></i><div>' . e($m) . '</div>'
              . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button></div>';
    }
    unset($_SESSION['flash']);
    return $out;
}

/* =============== CSRF =============== */
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(32)); }
    return $_SESSION['csrf'];
}
function csrf_field(): string { return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">'; }
function csrf_check(): void {
    $t = $_POST['_csrf'] ?? '';
    if (!is_string($t) || !hash_equals($_SESSION['csrf'] ?? '', $t)) {
        http_response_code(419);
        exit('Sesi formulir kedaluwarsa. Silakan kembali dan muat ulang halaman.');
    }
}

/* =============== Auth =============== */
function user(): ?array { return $_SESSION['user'] ?? null; }
function is_role(string ...$roles): bool { return user() && in_array(user()['role'], $roles, true); }
function role_label(string $r): string {
    return ['admin' => 'Admin Kecamatan', 'dinas' => 'Dinas Pertanian', 'kepala_desa' => 'Kepala Desa'][$r] ?? $r;
}
function forbidden(): void {
    http_response_code(403);
    layout_start('Akses Ditolak', 'Home / 403');
    echo '<div class="card"><h3>⛔ Akses ditolak</h3><p>Anda tidak memiliki izin untuk membuka halaman ini.</p>'
       . '<a class="btn btn-primary" href="' . e(url('dashboard')) . '">Kembali ke Dashboard</a></div>';
    layout_end();
    exit;
}

/* =============== Log aktivitas =============== */
function log_aktivitas(string $aksi, string $entitas, ?int $id = null, string $ket = ''): void {
    try {
        q('INSERT INTO log_aktivitas (id_user, username, aksi, entitas, id_entitas, keterangan, ip_address) VALUES (?,?,?,?,?,?,?)',
          [user()['id_user'] ?? null, user()['username'] ?? null, $aksi, $entitas, $id, mb_substr($ket, 0, 500), $_SERVER['REMOTE_ADDR'] ?? null]);
    } catch (Throwable $e) { /* log tidak boleh menggagalkan proses utama */ }
}

/* =============== Musim =============== */
function musim_aktif(): ?array {
    return q_one("SELECT * FROM musim_tanam WHERE status='aktif' ORDER BY id_musim DESC LIMIT 1");
}
/** Musim yang dipilih lewat ?musim=ID, default musim aktif, lalu musim terbaru. */
function musim_terpilih(): array {
    $list = q_all('SELECT * FROM musim_tanam ORDER BY tanggal_mulai DESC, id_musim DESC');
    $sel = null;
    $id = (int)get('musim');
    foreach ($list as $m) { if ($id && (int)$m['id_musim'] === $id) { $sel = $m; } }
    if (!$sel) { foreach ($list as $m) { if ($m['status'] === 'aktif') { $sel = $m; break; } } }
    if (!$sel && $list) { $sel = $list[0]; }
    return [$sel, $list];
}
function musim_selector(array $list, ?array $sel, string $route): string {
    if (!$list) return '';
    $h = '<form method="get" action="' . e(url($route)) . '" class="inline-form"><label class="form-label">Musim:</label> <select name="musim" class="form-select form-select-sm" onchange="this.form.submit()">';
    foreach ($list as $m) {
        $h .= '<option value="' . (int)$m['id_musim'] . '"' . ($sel && $sel['id_musim'] == $m['id_musim'] ? ' selected' : '') . '>'
            . e($m['nama_musim']) . ($m['status'] === 'aktif' ? ' (aktif)' : '') . '</option>';
    }
    return $h . '</select></form>';
}

/* =============== Realisasi =============== */
function realisasi_overdue(array $r): bool {
    return $r['status'] === 'dikirim' && $r['batas_konfirmasi'] < date('Y-m-d');
}
function realisasi_badge(array $r): string {
    if (realisasi_overdue($r)) return badge('Belum Dikonfirmasi (lewat batas)', 'red');
    return match ($r['status']) {
        'dikirim'      => badge('Dikirim - menunggu konfirmasi', 'blue'),
        'sesuai'       => badge('Diterima Sesuai', 'green'),
        'tidak_sesuai' => badge('Diterima Tidak Sesuai', 'orange'),
        'dibatalkan'   => badge('Dibatalkan', 'gray'),
        default        => badge($r['status']),
    };
}
function tinjauan_badge(array $r): string {
    return match ($r['status_tinjauan']) {
        'dalam_pemeriksaan' => badge('Dalam Pemeriksaan', 'orange'),
        'selesai'           => badge('Selesai Ditinjau', 'green'),
        default             => '',
    };
}
/** Sisa alokasi yang masih boleh dikirim = alokasi - (diterima untuk yg sudah dikonfirmasi, dikirim untuk yg masih jalan). */
function sisa_alokasi_sql(): string {
    return "a.jumlah_alokasi - COALESCE((SELECT SUM(CASE WHEN r.status='dikirim' THEN r.jumlah_dikirim ELSE r.jumlah_diterima END)
            FROM realisasi r WHERE r.id_alokasi = a.id_alokasi AND r.status <> 'dibatalkan'), 0)";
}

/* =============== Upload foto bukti =============== */
const FOTO_MAX_SISI = 1600;   // sisi terpanjang foto yang disimpan (px)
const FOTO_THUMB_SISI = 240;  // sisi terpanjang thumbnail (px)

/** Perkecil gambar dengan GD lalu simpan sebagai JPEG. Mengembalikan false bila GD tidak tersedia / gagal. */
function foto_resize(string $src, string $mime, string $dest, int $max, int $kualitas): bool {
    if (!function_exists('imagecreatetruecolor') || !function_exists('imagejpeg')) return false;
    try {
        $im = false;
        if ($mime === 'image/jpeg' && function_exists('imagecreatefromjpeg')) $im = @imagecreatefromjpeg($src);
        elseif ($mime === 'image/png' && function_exists('imagecreatefrompng')) $im = @imagecreatefrompng($src);
        elseif ($mime === 'image/webp' && function_exists('imagecreatefromwebp')) $im = @imagecreatefromwebp($src);
        if (!$im) return false;
        if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
            $ex = @exif_read_data($src);
            $o = (int)($ex['Orientation'] ?? 1);
            $deg = [3 => 180, 6 => -90, 8 => 90][$o] ?? 0;
            if ($deg) { $rot = imagerotate($im, $deg, 0); if ($rot) { imagedestroy($im); $im = $rot; } }
        }
        $w = imagesx($im); $h = imagesy($im);
        $s = min(1, $max / max($w, $h));
        $nw = max(1, (int)round($w * $s)); $nh = max(1, (int)round($h * $s));
        $dst = imagecreatetruecolor($nw, $nh);
        imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
        imagecopyresampled($dst, $im, 0, 0, 0, 0, $nw, $nh, $w, $h);
        imagedestroy($im);
        $ok = imagejpeg($dst, $dest, $kualitas);
        imagedestroy($dst);
        return (bool)$ok;
    } catch (Throwable $e) { return false; }
}

/** Path thumbnail (dibuat sekali lalu di-cache). Bila GD tidak ada, kembalikan file asli. */
function foto_thumb_path(string $file): ?string {
    $src = BASE_PATH . '/uploads/bukti/' . basename($file);
    if (!is_file($src)) return null;
    $dir = BASE_PATH . '/uploads/bukti/thumb';
    if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
    $dest = $dir . '/' . pathinfo($file, PATHINFO_FILENAME) . '.jpg';
    if (is_file($dest)) return $dest;
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($src);
    return foto_resize($src, $mime, $dest, FOTO_THUMB_SISI, 75) ? $dest : $src;
}

function simpan_foto_bukti(?array $f, ?string &$err): ?string {
    if (!$f || ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) { $err = 'Foto bukti wajib diunggah.'; return null; }
    if ($f['error'] !== UPLOAD_ERR_OK) {
        $err = in_array($f['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
            ? 'Ukuran foto terlalu besar (cek upload_max_filesize di php.ini).' : 'Upload foto gagal, coba lagi.';
        return null;
    }
    $fi = new finfo(FILEINFO_MIME_TYPE);
    $mime = $fi->file($f['tmp_name']);
    $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mime] ?? null;
    if (!$ext || @getimagesize($f['tmp_name']) === false) { $err = 'File harus berupa gambar JPG, PNG, atau WEBP.'; return null; }
    $dir = BASE_PATH . '/uploads/bukti';
    if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
    $base = date('Ymd_His') . '_' . bin2hex(random_bytes(8));
    // Perkecil otomatis di server (maks FOTO_MAX_SISI px) agar file ringan; pengunggah tidak dibatasi ukuran fotonya.
    if (foto_resize($f['tmp_name'], $mime, $dir . '/' . $base . '.jpg', FOTO_MAX_SISI, 82)) return $base . '.jpg';
    if (!move_uploaded_file($f['tmp_name'], $dir . '/' . $base . '.' . $ext)) { $err = 'Gagal menyimpan foto di server.'; return null; }
    return $base . '.' . $ext;
}
