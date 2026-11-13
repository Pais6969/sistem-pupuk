<?php
// Front controller: semua URL bersih (tanpa .php) masuk ke sini lewat .htaccess
define('BASE_PATH', __DIR__);
$scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
define('BASE_URL', $scriptDir);

require BASE_PATH . '/config/config.php';
require BASE_PATH . '/config/database.php';
require BASE_PATH . '/core/helpers.php';
require BASE_PATH . '/core/layout.php';

if (DEBUG) { ini_set('display_errors', '1'); error_reporting(E_ALL); }
else       { ini_set('display_errors', '0'); }

set_exception_handler(function (Throwable $ex) {
    http_response_code(500);
    error_log($ex);
    echo '<h3>Terjadi kesalahan pada server.</h3>';
    if (DEBUG) { echo '<pre>' . e($ex->getMessage()) . "\n" . e($ex->getFile() . ':' . $ex->getLine()) . '</pre>'; }
    exit;
});

session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'path' => $scriptDir === '' ? '/' : $scriptDir . '/']);
session_name('SIMPUPUKSESS');
session_start();
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');

// ---- Routing ----
$uri  = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';
$path = trim(urldecode(substr($uri, strlen(BASE_URL))), '/');
if ($path !== '' && substr($path, -4) === '.php') {            // buang akhiran .php bila diketik
    $clean = preg_replace('/(^|\/)index\.php$/', '', $path);
    $clean = preg_replace('/\.php$/', '', $clean);
    redirect($clean);
}
if ($path === '') { $path = 'dashboard'; }

$A = 'admin'; $D = 'dinas'; $K = 'kepala_desa';
$routes = [
    'login'      => ['login.php',      null],           // publik
    'logout'     => ['logout.php',     [$A, $D, $K]],
    'dashboard'  => ['dashboard.php',  [$A, $D, $K]],
    'desa'       => ['desa.php',       [$A]],
    'komoditas'  => ['komoditas.php',  [$A]],
    'pupuk'      => ['pupuk.php',      [$A]],
    'parameter'  => ['parameter.php',  [$A]],
    'musim'      => ['musim.php',      [$A]],
    'luas-lahan' => ['luas-lahan.php', [$A]],
    'alokasi'    => ['alokasi.php',    [$A, $D]],
    'realisasi'  => ['realisasi.php',  [$A, $D]],
    'penerimaan' => ['penerimaan.php', [$K]],
    'bukti'      => ['bukti.php',      [$A, $D, $K]],
    'laporan'    => ['laporan.php',    [$A, $D, $K]],
    'laporan-cetak' => ['laporan-cetak.php', [$A, $D, $K]],
    'user'       => ['user.php',       [$A]],
    'log'        => ['log.php',        [$A, $D]],
    'akun'       => ['akun.php',       [$A, $D, $K]],
];

if (!isset($routes[$path])) {
    http_response_code(404);
    define('ROUTE', '');
    layout_start('Halaman Tidak Ditemukan', 'Home / 404');
    echo '<div class="card"><h3>🔍 Halaman tidak ditemukan</h3><a class="btn btn-primary" href="' . e(url('dashboard')) . '">Ke Dashboard</a></div>';
    layout_end();
    exit;
}

[$file, $roles] = $routes[$path];
define('ROUTE', $path);

// ---- Autentikasi: muat ulang data user dari DB tiap request ----
if (!empty($_SESSION['uid'])) {
    $u = q_one('SELECT u.*, d.nama_desa FROM users u LEFT JOIN desa d ON d.id_desa = u.id_desa WHERE u.id_user = ? AND u.aktif = 1', [$_SESSION['uid']]);
    if ($u) { unset($u['password']); $_SESSION['user'] = $u; }
    else    { unset($_SESSION['uid'], $_SESSION['user']); }
}

if ($roles !== null) {
    if (!user()) { redirect('login'); }
    if (!in_array(user()['role'], $roles, true)) { forbidden(); }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') { csrf_check(); }

require BASE_PATH . '/pages/' . $file;
