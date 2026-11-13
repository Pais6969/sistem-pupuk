<?php
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('dashboard'); }
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 3600, $p['path'], $p['domain'] ?? '', $p['secure'] ?? false, true);
}
session_destroy();
session_start();
flash('success', 'Anda sudah keluar.');
redirect('login');
