<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = post('aksi');
    if ($aksi === 'simpan') {
        $id = (int)post('id'); $nama = post('nama_komoditas'); $zona = post('zona_tanam');
        $err = '';
        if ($nama === '' || mb_strlen($nama) > 100) $err = 'Nama komoditas wajib diisi (maks 100 karakter).';
        elseif (!in_array($zona, ['dataran_rendah', 'dataran_tinggi', 'semua'], true)) $err = 'Zona tanam tidak valid.';
        elseif (q_val('SELECT COUNT(*) FROM komoditas WHERE nama_komoditas = ? AND id_komoditas <> ?', [$nama, $id])) $err = 'Komoditas "' . $nama . '" sudah ada.';
        if ($err) { flash('danger', $err); redirect('komoditas' . ($id ? '?edit=' . $id : '')); }
        if ($id) {
            q('UPDATE komoditas SET nama_komoditas=?, zona_tanam=? WHERE id_komoditas=?', [$nama, $zona, $id]);
            log_aktivitas('ubah', 'komoditas', $id, $nama); flash('success', 'Komoditas diperbarui.');
        } else {
            q('INSERT INTO komoditas (nama_komoditas, zona_tanam) VALUES (?,?)', [$nama, $zona]);
            log_aktivitas('tambah', 'komoditas', (int)db()->lastInsertId(), $nama); flash('success', 'Komoditas ditambahkan.');
        }
        redirect('komoditas');
    }
    if ($aksi === 'hapus') {
        $id = (int)post('id');
        $k = q_one('SELECT nama_komoditas FROM komoditas WHERE id_komoditas = ?', [$id]);
        if (!$k) { flash('danger', 'Komoditas tidak ditemukan.'); redirect('komoditas'); }
        $np = (int)q_val('SELECT COUNT(*) FROM parameter_pupuk WHERE id_komoditas = ?', [$id]);
        $nl = (int)q_val('SELECT COUNT(*) FROM luas_lahan WHERE id_komoditas = ?', [$id]);
        if ($np || $nl) flash('danger', 'Komoditas "' . $k['nama_komoditas'] . '" masih dipakai (' . $np . ' parameter dosis, ' . $nl . ' data luas lahan).');
        else { q('DELETE FROM komoditas WHERE id_komoditas = ?', [$id]); log_aktivitas('hapus', 'komoditas', $id, $k['nama_komoditas']); flash('success', 'Komoditas dihapus.'); }
        redirect('komoditas');
    }
    redirect('komoditas');
}

$edit = get('edit') !== '' ? q_one('SELECT * FROM komoditas WHERE id_komoditas = ?', [(int)get('edit')]) : null;
$rows = q_all('SELECT * FROM komoditas ORDER BY nama_komoditas');

layout_start('Komoditas', 'Home / Data Master / Komoditas');
?>
<div class="card">
  <h3><?= $edit ? '<i class="bi bi-pencil-square me-1"></i>Edit Komoditas' : '<i class="bi bi-plus-circle me-1"></i>Tambah Komoditas' ?></h3>
  <form method="post" action="<?= e(url('komoditas')) ?>" class="grid2">
    <?= csrf_field() ?><input type="hidden" name="aksi" value="simpan"><input type="hidden" name="id" value="<?= (int)($edit['id_komoditas'] ?? 0) ?>">
    <div><label class="form-label">Nama Komoditas *</label><input class="form-control" type="text" name="nama_komoditas" required maxlength="100" value="<?= e($edit['nama_komoditas'] ?? '') ?>"></div>
    <div><label class="form-label">Zona Tanam *</label>
      <select class="form-select" name="zona_tanam">
        <?php foreach (['dataran_rendah', 'dataran_tinggi', 'semua'] as $z): ?>
          <option value="<?= $z ?>" <?= ($edit['zona_tanam'] ?? 'semua') === $z ? 'selected' : '' ?>><?= e(zona_label($z)) ?></option>
        <?php endforeach; ?>
      </select><small class="muted">Dipakai untuk peringatan jika komoditas ditanam di desa dengan zona berbeda.</small></div>
    <div class="full"><button class="btn btn-primary" type="submit"><i class="bi bi-save me-1"></i>Simpan</button>
      <?php if ($edit): ?><a class="btn btn-outline-success" href="<?= e(url('komoditas')) ?>">Batal</a><?php endif; ?></div>
  </form>
</div>
<div class="card">
  <h3><i class="bi bi-card-list me-1"></i>Daftar Komoditas (<?= count($rows) ?> data)</h3>
  <div class="table-wrap"><table class="table align-middle">
    <thead><tr><th>No</th><th>Komoditas</th><th>Zona Tanam</th><th>Aksi</th></tr></thead>
    <tbody>
    <?php if (!$rows): ?><tr><td colspan="4" class="center muted">Belum ada komoditas.</td></tr><?php endif; ?>
    <?php foreach ($rows as $i => $r): ?>
      <tr><td><?= $i + 1 ?></td><td><strong><?= e($r['nama_komoditas']) ?></strong></td>
        <td><?= badge(zona_label($r['zona_tanam']), $r['zona_tanam'] === 'dataran_tinggi' ? 'orange' : ($r['zona_tanam'] === 'semua' ? 'gray' : 'blue')) ?></td>
        <td class="actions">
          <a class="btn btn-sm btn-outline-success" href="<?= e(url('komoditas?edit=' . $r['id_komoditas'])) ?>"><i class="bi bi-pencil-square me-1"></i>Edit</a>
          <form method="post" action="<?= e(url('komoditas')) ?>" data-confirm="Hapus komoditas <?= e($r['nama_komoditas']) ?>?">
            <?= csrf_field() ?><input type="hidden" name="aksi" value="hapus"><input type="hidden" name="id" value="<?= (int)$r['id_komoditas'] ?>">
            <button class="btn btn-sm btn-danger" type="submit"><i class="bi bi-trash me-1"></i>Hapus</button></form></td></tr>
    <?php endforeach; ?>
    </tbody></table></div>
</div>
<?php layout_end();
