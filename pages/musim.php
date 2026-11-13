<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = post('aksi');
    if ($aksi === 'simpan') {
        $id = (int)post('id'); $nama = post('nama_musim'); $mulai = post('tanggal_mulai'); $selesai = post('tanggal_selesai');
        $aktif = post('aktif') === '1';
        $ok = fn($d) => (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && strtotime($d) !== false;
        $err = '';
        if ($nama === '' || mb_strlen($nama) > 100) $err = 'Nama musim wajib diisi.';
        elseif (!$ok($mulai) || !$ok($selesai)) $err = 'Tanggal tidak valid.';
        elseif ($selesai < $mulai) $err = 'Tanggal selesai tidak boleh sebelum tanggal mulai.';
        elseif (q_val('SELECT COUNT(*) FROM musim_tanam WHERE nama_musim = ? AND id_musim <> ?', [$nama, $id])) $err = 'Nama musim sudah ada.';
        if ($err) { flash('danger', $err); redirect('musim' . ($id ? '?edit=' . $id : '')); }
        $pdo = db(); $pdo->beginTransaction();
        try {
            if ($aktif) { q("UPDATE musim_tanam SET status='selesai'"); }
            if ($id) {
                q('UPDATE musim_tanam SET nama_musim=?, tanggal_mulai=?, tanggal_selesai=?, status=? WHERE id_musim=?',
                  [$nama, $mulai, $selesai, $aktif ? 'aktif' : 'selesai', $id]);
                log_aktivitas('ubah', 'musim_tanam', $id, $nama);
            } else {
                q('INSERT INTO musim_tanam (nama_musim, tanggal_mulai, tanggal_selesai, status) VALUES (?,?,?,?)', [$nama, $mulai, $selesai, $aktif ? 'aktif' : 'selesai']);
                log_aktivitas('tambah', 'musim_tanam', (int)$pdo->lastInsertId(), $nama);
            }
            $pdo->commit();
        } catch (Throwable $e) { $pdo->rollBack(); throw $e; }
        flash('success', 'Musim tanam disimpan.');
        redirect('musim');
    }
    if ($aksi === 'aktifkan') {
        $id = (int)post('id');
        $m = q_one('SELECT nama_musim FROM musim_tanam WHERE id_musim = ?', [$id]);
        if ($m) {
            $pdo = db(); $pdo->beginTransaction();
            q("UPDATE musim_tanam SET status='selesai'");
            q("UPDATE musim_tanam SET status='aktif' WHERE id_musim = ?", [$id]);
            $pdo->commit();
            log_aktivitas('aktifkan', 'musim_tanam', $id, $m['nama_musim']);
            flash('success', 'Musim "' . $m['nama_musim'] . '" sekarang aktif.');
        }
        redirect('musim');
    }
    if ($aksi === 'hapus') {
        $id = (int)post('id');
        $m = q_one('SELECT nama_musim FROM musim_tanam WHERE id_musim = ?', [$id]);
        if (!$m) { flash('danger', 'Musim tidak ditemukan.'); redirect('musim'); }
        $nl = (int)q_val('SELECT COUNT(*) FROM luas_lahan WHERE id_musim = ?', [$id]);
        $na = (int)q_val('SELECT COUNT(*) FROM alokasi WHERE id_musim = ?', [$id]);
        if ($nl || $na) flash('danger', 'Musim "' . $m['nama_musim'] . '" tidak bisa dihapus: sudah punya ' . $nl . ' data luas lahan dan ' . $na . ' alokasi.');
        else { q('DELETE FROM musim_tanam WHERE id_musim = ?', [$id]); log_aktivitas('hapus', 'musim_tanam', $id, $m['nama_musim']); flash('success', 'Musim dihapus.'); }
        redirect('musim');
    }
    redirect('musim');
}

$edit = get('edit') !== '' ? q_one('SELECT * FROM musim_tanam WHERE id_musim = ?', [(int)get('edit')]) : null;
$rows = q_all('SELECT * FROM musim_tanam ORDER BY tanggal_mulai DESC');

layout_start('Musim Tanam', 'Home / Data Master / Musim Tanam');
?>
<div class="card">
  <h3><?= $edit ? '<i class="bi bi-pencil-square me-1"></i>Edit Musim Tanam' : '<i class="bi bi-plus-circle me-1"></i>Tambah Musim Tanam' ?></h3>
  <form method="post" action="<?= e(url('musim')) ?>" class="grid2">
    <?= csrf_field() ?><input type="hidden" name="aksi" value="simpan"><input type="hidden" name="id" value="<?= (int)($edit['id_musim'] ?? 0) ?>">
    <div class="full"><label class="form-label">Nama Musim *</label><input class="form-control" type="text" name="nama_musim" required maxlength="100" placeholder="Contoh: Musim Kemarau 2027" value="<?= e($edit['nama_musim'] ?? '') ?>"></div>
    <div><label class="form-label">Tanggal Mulai *</label><input class="form-control" type="date" name="tanggal_mulai" required value="<?= e($edit['tanggal_mulai'] ?? '') ?>"></div>
    <div><label class="form-label">Tanggal Selesai *</label><input class="form-control" type="date" name="tanggal_selesai" required value="<?= e($edit['tanggal_selesai'] ?? '') ?>"></div>
    <div class="full"><label class="check"><input class="form-check-input" type="checkbox" name="aktif" value="1" <?= ($edit['status'] ?? '') === 'aktif' ? 'checked' : '' ?>> Jadikan musim aktif (musim lain otomatis selesai)</label></div>
    <div class="full"><button class="btn btn-primary" type="submit"><i class="bi bi-save me-1"></i>Simpan</button>
      <?php if ($edit): ?><a class="btn btn-outline-success" href="<?= e(url('musim')) ?>">Batal</a><?php endif; ?></div>
  </form>
</div>
<div class="card">
  <h3><i class="bi bi-card-list me-1"></i>Daftar Musim Tanam</h3>
  <div class="table-wrap"><table class="table align-middle">
    <thead><tr><th>Musim</th><th>Mulai</th><th>Selesai</th><th>Status</th><th>Aksi</th></tr></thead>
    <tbody>
    <?php if (!$rows): ?><tr><td colspan="5" class="center muted">Belum ada musim tanam.</td></tr><?php endif; ?>
    <?php foreach ($rows as $r): ?>
      <tr><td><strong><?= e($r['nama_musim']) ?></strong></td><td><?= e(tgl($r['tanggal_mulai'])) ?></td><td><?= e(tgl($r['tanggal_selesai'])) ?></td>
        <td><?= $r['status'] === 'aktif' ? badge('Aktif', 'green') : badge('Selesai', 'gray') ?></td>
        <td class="actions">
          <?php if ($r['status'] !== 'aktif'): ?>
          <form method="post" action="<?= e(url('musim')) ?>"><?= csrf_field() ?><input type="hidden" name="aksi" value="aktifkan"><input type="hidden" name="id" value="<?= (int)$r['id_musim'] ?>">
            <button class="btn btn-sm btn-outline-success" type="submit"><i class="bi bi-check2-circle me-1"></i>Aktifkan</button></form>
          <?php endif; ?>
          <a class="btn btn-sm btn-outline-success" href="<?= e(url('musim?edit=' . $r['id_musim'])) ?>"><i class="bi bi-pencil-square me-1"></i>Edit</a>
          <form method="post" action="<?= e(url('musim')) ?>" data-confirm="Hapus musim <?= e($r['nama_musim']) ?>?">
            <?= csrf_field() ?><input type="hidden" name="aksi" value="hapus"><input type="hidden" name="id" value="<?= (int)$r['id_musim'] ?>">
            <button class="btn btn-sm btn-danger" type="submit"><i class="bi bi-trash me-1"></i>Hapus</button></form></td></tr>
    <?php endforeach; ?>
    </tbody></table></div>
</div>
<?php layout_end();
