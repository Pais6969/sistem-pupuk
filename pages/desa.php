<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = post('aksi');
    if ($aksi === 'simpan') {
        $id = (int)post('id');
        $nama = post('nama_desa'); $kec = post('kecamatan'); $geo = post('status_geografis');
        $pend = num(post('jumlah_penduduk', '0'));
        $err = '';
        if ($nama === '' || mb_strlen($nama) > 100) $err = 'Nama desa wajib diisi (maks 100 karakter).';
        elseif ($kec === '') $err = 'Kecamatan wajib diisi.';
        elseif (!in_array($geo, ['dataran_rendah', 'dataran_tinggi'], true)) $err = 'Status geografis tidak valid.';
        elseif ($pend === null || $pend < 0 || floor($pend) != $pend) $err = 'Jumlah penduduk harus bilangan bulat >= 0.';
        elseif (q_val('SELECT COUNT(*) FROM desa WHERE nama_desa = ? AND id_desa <> ?', [$nama, $id])) $err = 'Nama desa "' . $nama . '" sudah ada.';
        if ($err) { flash('danger', $err); redirect('desa' . ($id ? '?edit=' . $id : '')); }
        if ($id) {
            q('UPDATE desa SET nama_desa=?, kecamatan=?, status_geografis=?, jumlah_penduduk=? WHERE id_desa=?', [$nama, $kec, $geo, (int)$pend, $id]);
            log_aktivitas('ubah', 'desa', $id, $nama);
            flash('success', 'Data desa diperbarui.');
        } else {
            q('INSERT INTO desa (nama_desa, kecamatan, status_geografis, jumlah_penduduk) VALUES (?,?,?,?)', [$nama, $kec, $geo, (int)$pend]);
            log_aktivitas('tambah', 'desa', (int)db()->lastInsertId(), $nama);
            flash('success', 'Desa berhasil ditambahkan.');
        }
        redirect('desa');
    }
    if ($aksi === 'hapus') {
        $id = (int)post('id');
        $d = q_one('SELECT nama_desa FROM desa WHERE id_desa = ?', [$id]);
        if (!$d) { flash('danger', 'Desa tidak ditemukan.'); redirect('desa'); }
        $nl = (int)q_val('SELECT COUNT(*) FROM luas_lahan WHERE id_desa = ?', [$id]);
        $na = (int)q_val('SELECT COUNT(*) FROM alokasi WHERE id_desa = ?', [$id]);
        if ($nl || $na) {
            flash('danger', 'Desa "' . $d['nama_desa'] . '" tidak bisa dihapus: masih punya ' . $nl . ' data luas lahan dan ' . $na . ' data alokasi.');
        } else {
            q('DELETE FROM desa WHERE id_desa = ?', [$id]);
            log_aktivitas('hapus', 'desa', $id, $d['nama_desa']);
            flash('success', 'Desa dihapus.');
        }
        redirect('desa');
    }
    redirect('desa');
}

$edit = null;
if (get('edit') !== '') { $edit = q_one('SELECT * FROM desa WHERE id_desa = ?', [(int)get('edit')]); }
$rows = q_all('SELECT * FROM desa ORDER BY nama_desa');

layout_start('Data Desa', 'Home / Data Master / Desa');
?>
<div class="card">
  <h3><?= $edit ? '<i class="bi bi-pencil-square me-1"></i>Edit Desa' : '<i class="bi bi-plus-circle me-1"></i>Tambah Desa Baru' ?></h3>
  <form method="post" action="<?= e(url('desa')) ?>" class="grid2">
    <?= csrf_field() ?><input type="hidden" name="aksi" value="simpan"><input type="hidden" name="id" value="<?= (int)($edit['id_desa'] ?? 0) ?>">
    <div><label class="form-label">Nama Desa *</label><input class="form-control" type="text" name="nama_desa" required maxlength="100" value="<?= e($edit['nama_desa'] ?? '') ?>"></div>
    <div><label class="form-label">Kecamatan *</label><input class="form-control" type="text" name="kecamatan" required value="<?= e($edit['kecamatan'] ?? 'Kecamatan X') ?>"></div>
    <div><label class="form-label">Status Geografis *</label>
      <select class="form-select" name="status_geografis">
        <?php foreach (['dataran_rendah', 'dataran_tinggi'] as $g): ?>
          <option value="<?= $g ?>" <?= ($edit['status_geografis'] ?? '') === $g ? 'selected' : '' ?>><?= e(zona_label($g)) ?></option>
        <?php endforeach; ?>
      </select></div>
    <div><label class="form-label">Jumlah Penduduk</label><input class="form-control" type="number" name="jumlah_penduduk" min="0" step="1" value="<?= e($edit['jumlah_penduduk'] ?? 0) ?>"></div>
    <div class="full">
      <button class="btn btn-primary" type="submit"><i class="bi bi-save me-1"></i>Simpan</button>
      <?php if ($edit): ?><a class="btn btn-outline-success" href="<?= e(url('desa')) ?>">Batal</a><?php endif; ?>
    </div>
  </form>
</div>

<div class="card">
  <h3><i class="bi bi-card-list me-1"></i>Tabel Desa (<?= count($rows) ?> data)</h3>
  <div class="table-wrap"><table class="table align-middle">
    <thead><tr><th>No</th><th>Nama Desa</th><th>Kecamatan</th><th>Geografis</th><th class="num">Penduduk</th><th>Aksi</th></tr></thead>
    <tbody>
    <?php if (!$rows): ?><tr><td colspan="6" class="center muted">Belum ada desa.</td></tr><?php endif; ?>
    <?php foreach ($rows as $i => $r): ?>
      <tr>
        <td><?= $i + 1 ?></td><td><strong><?= e($r['nama_desa']) ?></strong></td><td><?= e($r['kecamatan']) ?></td>
        <td><?= badge(zona_label($r['status_geografis']), $r['status_geografis'] === 'dataran_tinggi' ? 'orange' : 'blue') ?></td>
        <td class="num"><?= fmt($r['jumlah_penduduk'], 0) ?></td>
        <td class="actions">
          <a class="btn btn-sm btn-outline-success" href="<?= e(url('desa?edit=' . $r['id_desa'])) ?>"><i class="bi bi-pencil-square me-1"></i>Edit</a>
          <form method="post" action="<?= e(url('desa')) ?>" data-confirm="Hapus desa <?= e($r['nama_desa']) ?>?">
            <?= csrf_field() ?><input type="hidden" name="aksi" value="hapus"><input type="hidden" name="id" value="<?= (int)$r['id_desa'] ?>">
            <button class="btn btn-sm btn-danger" type="submit"><i class="bi bi-trash me-1"></i>Hapus</button></form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</div>
<?php layout_end();
