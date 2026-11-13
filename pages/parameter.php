<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = post('aksi');
    if ($aksi === 'simpan') {
        $id = (int)post('id'); $jml = num(post('jumlah_per_hektar'));
        if ($jml === null || $jml <= 0 || $jml > 99999999) { flash('danger', 'Dosis per hektar harus angka lebih dari 0.'); redirect('parameter' . ($id ? '?edit=' . $id : '')); }
        if ($id) {
            if (!q_one('SELECT 1 FROM parameter_pupuk WHERE id_parameter = ?', [$id])) { flash('danger', 'Data tidak ditemukan.'); redirect('parameter'); }
            q('UPDATE parameter_pupuk SET jumlah_per_hektar = ? WHERE id_parameter = ?', [$jml, $id]);
            log_aktivitas('ubah', 'parameter_pupuk', $id, 'Dosis menjadi ' . $jml . ' kg/ha');
            flash('success', 'Dosis diperbarui. Jalankan "Hitung Alokasi" ulang agar alokasi draft mengikuti dosis baru.');
        } else {
            $kom = (int)post('id_komoditas'); $pup = (int)post('id_pupuk');
            if (!q_one('SELECT 1 FROM komoditas WHERE id_komoditas = ?', [$kom]) || !q_one('SELECT 1 FROM pupuk WHERE id_pupuk = ?', [$pup])) {
                flash('danger', 'Komoditas atau pupuk tidak valid.'); redirect('parameter');
            }
            if (q_val('SELECT COUNT(*) FROM parameter_pupuk WHERE id_komoditas = ? AND id_pupuk = ?', [$kom, $pup])) {
                flash('danger', 'Kombinasi komoditas dan pupuk ini sudah ada. Gunakan tombol Edit.'); redirect('parameter');
            }
            q('INSERT INTO parameter_pupuk (id_komoditas, id_pupuk, jumlah_per_hektar) VALUES (?,?,?)', [$kom, $pup, $jml]);
            log_aktivitas('tambah', 'parameter_pupuk', (int)db()->lastInsertId(), "komoditas $kom, pupuk $pup, $jml kg/ha");
            flash('success', 'Parameter dosis ditambahkan.');
        }
        redirect('parameter');
    }
    if ($aksi === 'hapus') {
        $id = (int)post('id');
        q('DELETE FROM parameter_pupuk WHERE id_parameter = ?', [$id]);
        log_aktivitas('hapus', 'parameter_pupuk', $id, '');
        flash('success', 'Parameter dosis dihapus.');
        redirect('parameter');
    }
    redirect('parameter');
}

$edit = get('edit') !== '' ? q_one('SELECT * FROM parameter_pupuk WHERE id_parameter = ?', [(int)get('edit')]) : null;
$komList = q_all('SELECT * FROM komoditas ORDER BY nama_komoditas');
$pupList = q_all('SELECT * FROM pupuk ORDER BY nama_pupuk');
$rows = q_all('SELECT pp.*, k.nama_komoditas, p.nama_pupuk FROM parameter_pupuk pp
               JOIN komoditas k ON k.id_komoditas = pp.id_komoditas JOIN pupuk p ON p.id_pupuk = pp.id_pupuk
               ORDER BY k.nama_komoditas, p.nama_pupuk');

layout_start('Parameter Dosis Pupuk', 'Home / Data Master / Parameter Dosis');
?>
<div class="card">
  <h3><?= $edit ? '<i class="bi bi-pencil-square me-1"></i>Edit Dosis' : '<i class="bi bi-plus-circle me-1"></i>Tambah Parameter Dosis' ?></h3>
  <p class="muted">Dosis pupuk (kg) per hektar untuk tiap komoditas. Dipakai pada rumus: Luas Lahan × Dosis per Hektar.</p>
  <form method="post" action="<?= e(url('parameter')) ?>" class="grid2">
    <?= csrf_field() ?><input type="hidden" name="aksi" value="simpan"><input type="hidden" name="id" value="<?= (int)($edit['id_parameter'] ?? 0) ?>">
    <div><label class="form-label">Komoditas *</label>
      <select class="form-select" name="id_komoditas" <?= $edit ? 'disabled' : 'required' ?>>
        <?php foreach ($komList as $k): ?><option value="<?= (int)$k['id_komoditas'] ?>" <?= $edit && $edit['id_komoditas'] == $k['id_komoditas'] ? 'selected' : '' ?>><?= e($k['nama_komoditas']) ?></option><?php endforeach; ?>
      </select></div>
    <div><label class="form-label">Jenis Pupuk *</label>
      <select class="form-select" name="id_pupuk" <?= $edit ? 'disabled' : 'required' ?>>
        <?php foreach ($pupList as $p): ?><option value="<?= (int)$p['id_pupuk'] ?>" <?= $edit && $edit['id_pupuk'] == $p['id_pupuk'] ? 'selected' : '' ?>><?= e($p['nama_pupuk']) ?></option><?php endforeach; ?>
      </select></div>
    <div><label class="form-label">Dosis per Hektar (kg) *</label><input class="form-control" type="number" name="jumlah_per_hektar" min="0.01" step="0.01" required value="<?= e($edit['jumlah_per_hektar'] ?? '') ?>"></div>
    <div class="full"><button class="btn btn-primary" type="submit"><i class="bi bi-save me-1"></i>Simpan</button>
      <?php if ($edit): ?><a class="btn btn-outline-success" href="<?= e(url('parameter')) ?>">Batal</a><?php endif; ?></div>
  </form>
</div>
<div class="card">
  <h3><i class="bi bi-card-list me-1"></i>Daftar Parameter Dosis (<?= count($rows) ?> data)</h3>
  <div class="table-wrap"><table class="table align-middle">
    <thead><tr><th>Komoditas</th><th>Pupuk</th><th class="num">Dosis (kg/ha)</th><th>Aksi</th></tr></thead>
    <tbody>
    <?php if (!$rows): ?><tr><td colspan="4" class="center muted">Belum ada parameter dosis.</td></tr><?php endif; ?>
    <?php foreach (group_rows($rows, 'nama_komoditas') as $kom => $items): foreach ($items as $i => $r): ?>
      <tr class="<?= $i === 0 ? 'grp-first' : '' ?>">
        <?php if ($i === 0): ?><td rowspan="<?= count($items) ?>" class="cell-group"><?= e($kom) ?></td><?php endif; ?>
        <td><?= e($r['nama_pupuk']) ?></td>
        <td class="num"><strong><?= fmt($r['jumlah_per_hektar']) ?></strong></td>
        <td class="actions">
          <a class="btn btn-sm btn-outline-success" href="<?= e(url('parameter?edit=' . $r['id_parameter'])) ?>"><i class="bi bi-pencil-square me-1"></i>Edit</a>
          <form method="post" action="<?= e(url('parameter')) ?>" data-confirm="Hapus parameter ini?">
            <?= csrf_field() ?><input type="hidden" name="aksi" value="hapus"><input type="hidden" name="id" value="<?= (int)$r['id_parameter'] ?>">
            <button class="btn btn-sm btn-danger" type="submit"><i class="bi bi-trash me-1"></i>Hapus</button></form></td>
      </tr>
    <?php endforeach; endforeach; ?>
    </tbody></table></div>
</div>
<?php layout_end();
