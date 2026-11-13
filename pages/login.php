<?php
if (user()) { redirect('dashboard'); }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $lock = $_SESSION['login_lock'] ?? 0;
    if ($lock > time()) {
        $error = 'Terlalu banyak percobaan. Coba lagi dalam ' . ($lock - time()) . ' detik.';
    } else {
        $username = post('username');
        $row = q_one('SELECT * FROM users WHERE username = ? AND aktif = 1', [$username]);
        if ($row && password_verify($_POST['password'] ?? '', $row['password'])) {
            session_regenerate_id(true);
            $_SESSION['uid'] = (int)$row['id_user'];
            $_SESSION['login_fail'] = 0;
            unset($_SESSION['login_lock']);
            $_SESSION['user'] = ['id_user' => $row['id_user'], 'username' => $row['username'], 'role' => $row['role']];
            redirect('dashboard');
        }
        $_SESSION['login_fail'] = ($_SESSION['login_fail'] ?? 0) + 1;
        if ($_SESSION['login_fail'] >= 5) { $_SESSION['login_lock'] = time() + 60; $_SESSION['login_fail'] = 0; }
        $error = 'Username atau password salah.';
    }
}
?><!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Login - <?= e(APP_NAME) ?></title>
<?= head_assets() ?>
</head>
<body class="login-body">
<div class="login-card">
  <div class="login-logo">🌾</div>
  <h2><?= e(APP_NAME) ?></h2>
  <p class="muted"><?= e(APP_SUB) ?></p>
  <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
  <form method="post" action="<?= e(url('login')) ?>" autocomplete="off">
    <?= csrf_field() ?>
    <label class="form-label">Username</label>
    <input class="form-control" type="text" name="username" value="<?= e(post('username')) ?>" required autofocus>
    <label class="form-label">Password</label>
    <input class="form-control" type="password" name="password" required>
    <button class="btn btn-primary w-100" type="submit"><i class="bi bi-shield-lock me-1"></i>Masuk</button>
  </form>
  <p class="muted small center">© <?= date('Y') ?> <?= e(APP_INSTANSI) ?></p>
</div>
</body>
</html>
