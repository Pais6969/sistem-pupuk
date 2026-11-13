<?php
function slug_user(string $s): string { $s = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', $s)); return trim($s, '_') ?: 'desa'; }
function pass_acak(int $n = 8): string {
    $abc = 'abcdefghjkmnpqrstuvwxyzACDEFGHJKLMNPQRTUVWXYZ2345679'; $o = '';
    for ($i = 0; $i < $n; $i++) { $o .= $abc[random_int(0, strlen($abc) - 1)]; }
    return $o;
}
$me = (int)user()['id_user'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = post('aksi');
    if ($aksi === 'simpan') {
        $id = (int)post('id'); $un = strtolower(post('username')); $nama = post('nama_lengkap'); $role = post('role');
        $desa = (int)post('id_desa'); $pw = (string)($_POST['password'] ?? '');
        if ($id === $me) { $role = 'admin'; }
        $err = '';
        if (!preg_match('/^[a-z0-9_.]{3,50}$/', $un)) $err = 'Username 3-50 karakter: huruf kecil, angka, titik, atau underscore.';
        elseif ($nama === '' || mb_strlen($nama) > 100) $err = 'Nama lengkap wajib diisi.';
        elseif (!in_array($role, ['admin', 'dinas', 'kepala_desa'], true)) $err = 'Role tidak valid.';
        elseif ($role === 'kepala_desa' && !q_one('SELECT 1 FROM desa WHERE id_desa = ?', [$desa])) $err = 'Kepala desa harus dihubungkan ke sebuah desa.';
        elseif (!$id && strlen($pw) < 6) $err = 'Password minimal 6 karakter.';
        elseif ($id && $pw !== '' && strlen($pw) < 6) $err = 'Password baru minimal 6 karakter.';
        elseif (q_val('SELECT COUNT(*) FROM users WHERE username = ? AND id_user <> ?', [$un, $id])) $err = 'Username sudah dipakai.';
        if ($err) { flash('danger', $err); redirect('user' . ($id ? '?edit=' . $id : '')); }
        $desaVal = $role === 'kepala_desa' ? $desa : null;
        if ($id) {
            q('UPDATE users SET username=?, nama_lengkap=?, role=?, id_desa=? WHERE id_user=?', [$un, $nama, $role, $desaVal, $id]);
            if ($pw !== '') q('UPDATE users SET password=? WHERE id_user=?', [password_hash($pw, PASSWORD_DEFAULT), $id]);
            log_aktivitas('ubah', 'users', $id, $un . ($pw !== '' ? ' (password direset)' : ''));
            flash('success', 'User diperbarui.');
        } else {
            q('INSERT INTO users (username, password, nama_lengkap, role, id_desa) VALUES (?,?,?,?,?)', [$un, password_hash($pw, PASSWORD_DEFAULT), $nama, $role, $desaVal]);
            log_aktivitas('tambah', 'users', (int)db()->lastInsertId(), $un . ' (' . $role . ')');
            flash('success', 'User ditambahkan.');
        }
        redirect('user');
    }
    if ($aksi === 'toggle') {
        $id = (int)post('id');
        if ($id === $me) { flash('danger', 'Anda tidak dapat menonaktifkan akun sendiri.'); redirect('user'); }
        q('UPDATE users SET aktif = 1 - aktif WHERE id_user = ?', [$id]);
        log_aktivitas('toggle_aktif', 'users', $id, '');
        flash('success', 'Status akun diubah.');
        redirect('user');
    }
    if ($aksi === 'buat_kades_semua') {
        $kred = [];
        foreach (q_all("SELECT d.* FROM desa d WHERE NOT EXISTS (SELECT 1 FROM users u WHERE u.id_desa = d.id_desa AND u.role = 'kepala_desa') ORDER BY d.nama_desa") as $d) {
            $base = 'kades_' . slug_user($d['nama_desa']); $un = $base; $n = 1;
            while (q_val('SELECT COUNT(*) FROM users WHERE username = ?', [$un])) { $n++; $un = $base . $n; }
            $pw = pass_acak();
            q("INSERT INTO users (username, password, nama_lengkap, role, id_desa) VALUES (?,?,?,'kepala_desa',?)", [$un, password_hash($pw, PASSWORD_DEFAULT), 'Kepala Desa ' . $d['nama_desa'], $d['id_desa']]);
            $kred[] = [$d['nama_desa'], $un, $pw];
            log_aktivitas('tambah', 'users', (int)db()->lastInsertId(), $un . ' (otomatis)');
        }
        if ($kred) { $_SESSION['kredensial_baru'] = $kred; flash('success', count($kred) . ' akun kepala desa dibuat.'); }
        else flash('info', 'Semua desa sudah memiliki akun kepala desa.');
        redirect('user');
    }
    redirect('user');
}

$edit = get('edit') !== '' ? q_one('SELECT * FROM users WHERE id_user = ?', [(int)get('edit')]) : null;
$desaList = q_all('SELECT * FROM desa ORDER BY nama_desa');
$rows = q_all("SELECT u.*, d.nama_desa FROM users u LEFT JOIN desa d ON d.id_desa = u.id_desa ORDER BY FIELD(u.role, 'admin','dinas','kepala_desa'), u.username");
$kred = $_SESSION['kredensial_baru'] ?? []; unset($_SESSION['kredensial_baru']);

layout_start('Kelola User', 'Home / Sistem / Kelola User');
?>
<?php if ($kred): ?>
<div class="card card-warn">
  <h3><i class="bi bi-shield-lock me-1"></i>Akun Kepala Desa yang Baru Dibuat</h3>
  <p><strong>Catat sekarang!</strong> Password hanya ditampilkan sekali ini. Minta kepala desa mengganti password di menu "Akun Saya".</p>
  <div class="table-wrap"><table class="table align-middle"><thead><tr><th>Desa</th><th>Username</th><th>Password</th></tr></thead><tbody>
  <?php foreach ($kred as [$dn, $un, $pw]): ?><tr><td><?= e($dn) ?></td><td><code><?= e($un) ?></code></td><td><code><?= e($pw) ?></code></td></tr><?php endforeach; ?>
  </tbody></table></div>
  <button type="button" class="btn btn-outline-success no-print" data-print><i class="bi bi-printer me-1"></i>Cetak daftar ini</button>
</div>
<?php endif; ?>

<div class="card">
  <h3><?= $edit ? '<i class="bi bi-pencil-square me-1"></i>Edit User' : '<i class="bi bi-plus-circle me-1"></i>Tambah User' ?></h3>
  <form method="post" action="<?= e(url('user')) ?>" class="grid2">
    <?= csrf_field() ?><input type="hidden" name="aksi" value="simpan"><input type="hidden" name="id" value="<?= (int)($edit['id_user'] ?? 0) ?>">
    <div><label class="form-label">Username *</label><input class="form-control" type="text" name="username" required pattern="[a-z0-9_.]{3,50}" value="<?= e($edit['username'] ?? '') ?>"></div>
    <div><label class="form-label">Nama Lengkap *</label><input class="form-control" type="text" name="nama_lengkap" required maxlength="100" value="<?= e($edit['nama_lengkap'] ?? '') ?>"></div>
    <div><label class="form-label">Role *</label>
      <select class="form-select" name="role" id="roleSel" <?= $edit && (int)$edit['id_user'] === $me ? 'disabled' : '' ?>>
        <?php foreach (['admin', 'dinas', 'kepala_desa'] as $r): ?><option value="<?= $r ?>" <?= ($edit['role'] ?? 'kepala_desa') === $r ? 'selected' : '' ?>><?= e(role_label($r)) ?></option><?php endforeach; ?>
      </select></div>
    <div id="desaWrap"><label class="form-label">Desa (untuk Kepala Desa)</label>
      <select class="form-select" name="id_desa"><?php foreach ($desaList as $d): ?><option value="<?= (int)$d['id_desa'] ?>" <?= ($edit['id_desa'] ?? 0) == $d['id_desa'] ? 'selected' : '' ?>><?= e($d['nama_desa']) ?></option><?php endforeach; ?></select></div>
    <div class="full"><label class="form-label">Password <?= $edit ? '<small class="muted">(kosongkan jika tidak diubah)</small>' : '*' ?></label>
      <input class="form-control" type="password" name="password" minlength="6" autocomplete="new-password" <?= $edit ? '' : 'required' ?>></div>
    <div class="full"><button class="btn btn-primary" type="submit"><i class="bi bi-save me-1"></i>Simpan</button>
      <?php if ($edit): ?><a class="btn btn-outline-success" href="<?= e(url('user')) ?>">Batal</a><?php endif; ?></div>
  </form>
  <hr>
  <form method="post" action="<?= e(url('user')) ?>" data-confirm="Buat akun kepala desa untuk semua desa yang belum punya akun?">
    <?= csrf_field() ?><input type="hidden" name="aksi" value="buat_kades_semua">
    <button class="btn btn-warning" type="submit"><i class="bi bi-lightning-charge me-1"></i>Buat Akun Kepala Desa Otomatis untuk Semua Desa yang Belum Punya</button>
  </form>
</div>

<div class="card">
  <h3><i class="bi bi-people me-1"></i>Daftar User (<?= count($rows) ?>)</h3>
  <div class="table-wrap"><table class="table align-middle">
    <thead><tr><th>Username</th><th>Nama</th><th>Role</th><th>Desa</th><th>Status</th><th>Aksi</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr><td><code><?= e($r['username']) ?></code></td><td><?= e($r['nama_lengkap']) ?></td>
        <td><?= badge(role_label($r['role']), $r['role'] === 'admin' ? 'green' : ($r['role'] === 'dinas' ? 'blue' : 'orange')) ?></td>
        <td><?= e($r['nama_desa'] ?? '-') ?></td><td><?= $r['aktif'] ? badge('Aktif', 'green') : badge('Nonaktif', 'red') ?></td>
        <td class="actions">
          <a class="btn btn-sm btn-outline-success" href="<?= e(url('user?edit=' . $r['id_user'])) ?>"><i class="bi bi-pencil-square me-1"></i>Edit</a>
          <?php if ((int)$r['id_user'] !== $me): ?>
          <form method="post" action="<?= e(url('user')) ?>" data-confirm="<?= $r['aktif'] ? 'Nonaktifkan' : 'Aktifkan' ?> akun <?= e($r['username']) ?>?">
            <?= csrf_field() ?><input type="hidden" name="aksi" value="toggle"><input type="hidden" name="id" value="<?= (int)$r['id_user'] ?>">
            <button class="btn btn-sm <?= $r['aktif'] ? 'btn-danger' : 'btn-primary' ?>" type="submit"><?= $r['aktif'] ? '<i class="bi bi-slash-circle me-1"></i>Nonaktifkan' : '<i class="bi bi-check2-circle me-1"></i>Aktifkan' ?></button></form>
          <?php endif; ?></td></tr>
    <?php endforeach; ?>
    </tbody></table></div>
</div>
<?php layout_end();
