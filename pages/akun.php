<?php
$u = user();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $lama = (string)($_POST['password_lama'] ?? ''); $baru = (string)($_POST['password_baru'] ?? ''); $ulang = (string)($_POST['password_ulang'] ?? '');
    $hash = q_val('SELECT password FROM users WHERE id_user = ?', [(int)$u['id_user']]);
    if (!$hash || !password_verify($lama, $hash)) flash('danger', 'Password lama salah.');
    elseif (strlen($baru) < 6) flash('danger', 'Password baru minimal 6 karakter.');
    elseif ($baru !== $ulang) flash('danger', 'Konfirmasi password baru tidak sama.');
    else {
        q('UPDATE users SET password = ? WHERE id_user = ?', [password_hash($baru, PASSWORD_DEFAULT), (int)$u['id_user']]);
        log_aktivitas('ganti_password', 'users', (int)$u['id_user'], 'Password diganti sendiri');
        session_regenerate_id(true);
        flash('success', 'Password berhasil diganti.');
    }
    redirect('akun');
}
layout_start('Akun Saya', 'Home / Sistem / Akun Saya');
?>
<div class="card">
  <h3><i class="bi bi-person-circle me-1"></i>Profil</h3>
  <p>Username: <code><?= e($u['username']) ?></code><br>Nama: <?= e($u['nama_lengkap']) ?><br>Peran: <?= e(role_label($u['role'])) ?><?= !empty($u['nama_desa']) ? ' - Desa ' . e($u['nama_desa']) : '' ?></p>
</div>
<div class="card">
  <h3><i class="bi bi-key me-1"></i>Ganti Password</h3>
  <form method="post" action="<?= e(url('akun')) ?>" class="grid2">
    <?= csrf_field() ?>
    <div class="full"><label class="form-label">Password Lama *</label><input class="form-control" type="password" name="password_lama" required autocomplete="current-password"></div>
    <div><label class="form-label">Password Baru *</label><input class="form-control" type="password" name="password_baru" required minlength="6" autocomplete="new-password"></div>
    <div><label class="form-label">Ulangi Password Baru *</label><input class="form-control" type="password" name="password_ulang" required minlength="6" autocomplete="new-password"></div>
    <div class="full"><button class="btn btn-primary" type="submit"><i class="bi bi-save me-1"></i>Ganti Password</button></div>
  </form>
</div>
<?php layout_end();
