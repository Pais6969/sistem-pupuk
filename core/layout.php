<?php
function menu_items(): array {
    // [route, ikon bootstrap-icons, label, roles, grup]
    return [
        ['dashboard',  'speedometer2',   'Dashboard',           ['admin','dinas','kepala_desa'], null],
        ['desa',       'houses',         'Data Desa',           ['admin'], 'Data Master'],
        ['komoditas',  'flower1',        'Komoditas',           ['admin'], 'Data Master'],
        ['pupuk',      'droplet-half',   'Jenis Pupuk',         ['admin'], 'Data Master'],
        ['parameter',  'sliders',        'Parameter Dosis',     ['admin'], 'Data Master'],
        ['musim',      'calendar3',      'Musim Tanam',         ['admin'], 'Data Master'],
        ['luas-lahan', 'bounding-box',   'Luas Lahan',          ['admin'], 'Data Master'],
        ['alokasi',    'calculator',     'Perhitungan Alokasi', ['admin','dinas'], 'Penyaluran'],
        ['realisasi',  'truck',          'Realisasi',           ['admin','dinas'], 'Penyaluran'],
        ['penerimaan', 'box-seam',       'Penerimaan Pupuk',    ['kepala_desa'], 'Penyaluran'],
        ['laporan',    'file-earmark-text', 'Laporan',          ['admin','dinas','kepala_desa'], 'Penyaluran'],
        ['user',       'people',         'Kelola User',         ['admin'], 'Sistem'],
        ['log',        'clock-history',  'Log Aktivitas',       ['admin','dinas'], 'Sistem'],
        ['akun',       'key',            'Akun Saya',           ['admin','dinas','kepala_desa'], 'Sistem'],
    ];
}

function asset(string $path): string {
    $f = BASE_PATH . '/' . $path;
    return url($path) . (is_file($f) ? '?v=' . filemtime($f) : '');
}

function head_assets(): string {
    return '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">' . "\n"
         . '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">' . "\n"
         . '<link rel="stylesheet" href="' . e(asset('assets/css/simpupuk.css')) . '">';
}

function layout_start(string $title, string $crumb = ''): void {
    $u = user();
    $route = defined('ROUTE') ? ROUTE : '';
    ?><!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> - <?= e(APP_NAME) ?></title>
<?= head_assets() ?>
</head>
<body>
<div class="app">
<aside class="sidebar" id="sidebar">
  <a class="brand" href="<?= e(url('dashboard')) ?>"><span class="brand-logo">🌾</span><span><?= e(APP_NAME) ?></span></a>
  <nav class="side-nav">
  <?php
    $lastGroup = '';
    foreach (menu_items() as [$r, $ic, $lb, $roles, $grp]) {
        if (!$u || !in_array($u['role'], $roles, true)) continue;
        if ($grp && $grp !== $lastGroup) { echo '<div class="menu-group">' . e($grp) . '</div>'; $lastGroup = $grp; }
        $act = ($route === $r) ? ' active' : '';
        echo '<a class="menu' . $act . '" href="' . e(url($r)) . '"><i class="bi bi-' . $ic . '"></i><span>' . e($lb) . '</span></a>';
    }
  ?>
  </nav>
  <?php if ($u): ?>
  <div class="side-user">
    <div class="side-user-name"><?= e($u['nama_lengkap']) ?></div>
    <div class="side-user-role"><?= e(role_label($u['role'])) ?><?= $u['role'] === 'kepala_desa' && !empty($u['nama_desa']) ? ' &middot; ' . e($u['nama_desa']) : '' ?></div>
    <form method="post" action="<?= e(url('logout')) ?>"><?= csrf_field() ?>
      <button class="btn btn-sm btn-light w-100 mt-2" type="submit"><i class="bi bi-box-arrow-left me-1"></i> Logout</button></form>
  </div>
  <?php endif; ?>
</aside>
<div class="sidebar-backdrop" id="backdrop"></div>

<div class="main">
  <header class="topbar">
    <button class="burger" type="button" id="burger" aria-label="Menu"><i class="bi bi-list"></i></button>
    <div class="topbar-title">
      <h1><?= e($title) ?></h1>
      <div class="crumb"><?= e($crumb) ?></div>
    </div>
    <?php if ($u): ?>
    <div class="dropdown ms-auto">
      <button class="who dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
        <span class="avatar"><?= e(mb_strtoupper(mb_substr($u['nama_lengkap'], 0, 1))) ?></span>
        <span class="who-name"><?= e($u['nama_lengkap']) ?></span>
      </button>
      <ul class="dropdown-menu dropdown-menu-end shadow-sm">
        <li><h6 class="dropdown-header"><?= e(role_label($u['role'])) ?></h6></li>
        <li><a class="dropdown-item" href="<?= e(url('akun')) ?>"><i class="bi bi-key me-2"></i>Akun Saya</a></li>
        <li><hr class="dropdown-divider"></li>
        <li><form method="post" action="<?= e(url('logout')) ?>"><?= csrf_field() ?><button class="dropdown-item" type="submit"><i class="bi bi-box-arrow-left me-2"></i>Logout</button></form></li>
      </ul>
    </div>
    <?php endif; ?>
  </header>
  <main class="content">
  <?= flash_render() ?>
<?php
}

function layout_end(): void {
    ?>
  </main>
</div>
</div>

<div class="modal fade" id="lightbox" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header py-2"><h6 class="modal-title">Foto Bukti</h6><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button></div>
      <div class="modal-body text-center p-2"><img id="lightboxImg" class="lightbox-img" alt="Foto bukti"></div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= e(asset('assets/js/simpupuk.js')) ?>"></script>
</body>
</html>
<?php
}
