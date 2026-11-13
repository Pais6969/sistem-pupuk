<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = post('aksi');
    if ($aksi === 'simpan') {
        $id = (int)post('id'); $nama = post('nama_pupuk'); $sat = post('satuan', 'kg');
        $err = '';
        if ($nama === '' || mb_strlen($nama) > 100) $err = 'Nama pupuk wajib diisi (maks 100 karakter).';
        elseif ($sat === '' || mb_strlen($sat) > 20) $err = 'Satuan wajib diisi (maks 20 karakter).';
        elseif (q_val('SELECT COUNT(*) FROM pupuk WHERE nama_pupuk = ? AND id_pupuk <> ?', [$nama, $id])) $err = 'Pupuk "' . $nama . '" sudah ada.';
        if ($err) { flash('danger', $err); redirect('pupuk' . ($id ? '?edit=' . $id : '')); }
        if ($id) {
            q('UPDATE pupuk SET nama_pupuk=?, satuan=? WHERE id_pupuk=?', [$nama, $sat, $id]);
            log_aktivitas('ubah', 'pupuk', $id, $nama); flash('success', 'Jenis pupuk diperbarui.');
        } else {
            q('INSERT INTO pupuk (nama_pupuk, satuan) VALUES (?,?)', [$nama, $sat]);
            log_aktivitas('tambah', 'pupuk', (int)db()->lastInsertId(), $nama); flash('success', 'Jenis pupuk ditambahkan.');
        }
        redirect('pupuk');
    }
    if ($aksi === 'hapus') {
        $id = (int)post('id');
        $p = q_one('SELECT nama_pupuk FROM pupuk WHERE id_pupuk = ?', [$id]);
        if (!$p) { flash('danger', 'Pupuk tidak ditemukan.'); redirect('pupuk'); }
        $np = (int)q_val('SELECT COUNT(*) FROM parameter_pupuk WHERE id_pupuk = ?', [$id]);
        $na = (int)q_val('SELECT COUNT(*) FROM alokasi WHERE id_pupuk = ?', [$id]);
        if ($np || $na) flash('danger', 'Pupuk "' . $p['nama_pupuk'] . '" masih dipakai (' . $np . ' parameter dosis, ' . $na . ' alokasi).');
        else { q('DELETE FROM pupuk WHERE id_pupuk = ?', [$id]); log_aktivitas('hapus', 'pupuk', $id, $p['nama_pupuk']); flash('success', 'Jenis pupuk dihapus.'); }
        redirect('pupuk');
    }
    redirect('pupuk');
}

$edit = get('edit') !== '' ? q_one('SELECT * FROM pupuk WHERE id_pupuk = ?', [(int)get('edit')]) : null;
$rows = q_all('SELECT * FROM pupuk ORDER BY nama_pupuk');

layout_start('Jenis Pupuk', 'Home / Data Master / Jenis Pupuk');
?>
<div class="card">
  <h3><?= $edit ? '<i class="bi bi-pencil-square me-1"></i>Edit Jenis Pupuk' : '<i class="bi bi-plus-circle me-1"></i>Tambah Jenis Pupuk' ?></h3>
  <form method="post" action="<?= e(url('pupuk')) ?>" class="grid2">
    <?= csrf_field() ?><input type="hidden" name="aksi" value="simpan"><input type="hidden" name="id" value="<?= (int)($edit['id_pupuk'] ?? 0) ?>">
    <div><label class="form-label">Nama Pupuk *</label><input class="form-control" type="text" name="nama_pupuk" required maxlength="100" value="<?= e($edit['nama_pupuk'] ?? '') ?>"></div>
    <div><label class="form-label">Satuan *</label><input class="form-control" type="text" name="satuan" required maxlength="20" value="<?= e($edit['satuan'] ?? 'kg') ?>"></div>
    <div class="full"><button class="btn btn-primary" type="submit"><i class="bi bi-save me-1"></i>Simpan</button>
      <?php if ($edit): ?><a class="btn btn-outline-success" href="<?= e(url('pupuk')) ?>">Batal</a><?php endif; ?></div>
  </form>
</div>
<div class="card">
  <h3><i class="bi bi-card-list me-1"></i>Daftar Jenis Pupuk (<?= count($rows) ?> data)</h3>
  <div class="table-wrap"><table class="table align-middle">
    <thead><tr><th>No</th><th>Nama Pupuk</th><th>Satuan</th><th>Aksi</th></tr></thead>
    <tbody>
    <?php if (!$rows): ?><tr><td colspan="4" class="center muted">Belum ada data pupuk.</td></tr><?php endif; ?>
    <?php foreach ($rows as $i => $r): ?>
      <tr><td><?= $i + 1 ?></td><td><strong><?= e($r['nama_pupuk']) ?></strong></td><td><?= e($r['satuan']) ?></td>
        <td class="actions">
          <a class="btn btn-sm btn-outline-success" href="<?= e(url('pupuk?edit=' . $r['id_pupuk'])) ?>"><i class="bi bi-pencil-square me-1"></i>Edit</a>
          <form method="post" action="<?= e(url('pupuk')) ?>" data-confirm="Hapus pupuk <?= e($r['nama_pupuk']) ?>?">
            <?= csrf_field() ?><input type="hidden" name="aksi" value="hapus"><input type="hidden" name="id" value="<?= (int)$r['id_pupuk'] ?>">
            <button class="btn btn-sm btn-danger" type="submit"><i class="bi bi-trash me-1"></i>Hapus</button></form></td></tr>
    <?php endforeach; ?>
    </tbody></table></div>
</div>
<?php layout_end();
