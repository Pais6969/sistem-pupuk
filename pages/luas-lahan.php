<?php
/** Apakah alokasi desa pada musim ini sudah ditetapkan (data lahan dikunci)? */
function lahan_terkunci(int $musim, int $desa): bool {
    return (bool)q_val("SELECT COUNT(*) FROM alokasi WHERE id_musim = ? AND id_desa = ? AND status = 'ditetapkan'", [$musim, $desa]);
}
function zona_cocok(string $geo, string $zona): bool { return $zona === 'semua' || $zona === $geo; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = post('aksi'); $idm = (int)post('id_musim');
    $back = 'luas-lahan?musim=' . $idm;
    if (!q_one('SELECT 1 FROM musim_tanam WHERE id_musim = ?', [$idm])) { flash('danger', 'Musim tidak valid.'); redirect('luas-lahan'); }

    if ($aksi === 'simpan') {
        $id = (int)post('id'); $luas = num(post('luas_hektar'));
        if ($luas === null || $luas <= 0 || $luas > 1000000) { flash('danger', 'Luas harus angka lebih dari 0.'); redirect($back); }
        if ($id) {
            $row = q_one('SELECT * FROM luas_lahan WHERE id_luas = ? AND id_musim = ?', [$id, $idm]);
            if (!$row) { flash('danger', 'Data tidak ditemukan.'); redirect($back); }
            if (lahan_terkunci($idm, (int)$row['id_desa'])) { flash('danger', 'Alokasi desa ini sudah ditetapkan, luas lahan dikunci. Batalkan penetapan dulu jika perlu mengubah.'); redirect($back); }
            q('UPDATE luas_lahan SET luas_hektar = ? WHERE id_luas = ?', [$luas, $id]);
            log_aktivitas('ubah', 'luas_lahan', $id, 'Luas menjadi ' . $luas . ' ha');
            flash('success', 'Luas lahan diperbarui. Jalankan "Hitung Alokasi" ulang untuk memperbarui alokasi draft.');
        } else {
            $desa = (int)post('id_desa'); $kom = (int)post('id_komoditas');
            $d = q_one('SELECT * FROM desa WHERE id_desa = ?', [$desa]);
            $k = q_one('SELECT * FROM komoditas WHERE id_komoditas = ?', [$kom]);
            if (!$d || !$k) { flash('danger', 'Desa atau komoditas tidak valid.'); redirect($back); }
            if (lahan_terkunci($idm, $desa)) { flash('danger', 'Alokasi desa ' . $d['nama_desa'] . ' sudah ditetapkan, luas lahan dikunci.'); redirect($back); }
            if (q_val('SELECT COUNT(*) FROM luas_lahan WHERE id_musim = ? AND id_desa = ? AND id_komoditas = ?', [$idm, $desa, $kom])) {
                flash('danger', $d['nama_desa'] . ' sudah punya data ' . $k['nama_komoditas'] . ' di musim ini. Gunakan Edit untuk mengubah luasnya.'); redirect($back);
            }
            q('INSERT INTO luas_lahan (id_musim, id_desa, id_komoditas, luas_hektar) VALUES (?,?,?,?)', [$idm, $desa, $kom, $luas]);
            log_aktivitas('tambah', 'luas_lahan', (int)db()->lastInsertId(), $d['nama_desa'] . ' - ' . $k['nama_komoditas'] . ' ' . $luas . ' ha');
            if (!zona_cocok($d['status_geografis'], $k['zona_tanam'])) {
                flash('warning', '⚠️ Peringatan zona: ' . $k['nama_komoditas'] . ' biasanya ditanam di ' . zona_label($k['zona_tanam']) . ', sedangkan ' . $d['nama_desa'] . ' berada di ' . zona_label($d['status_geografis']) . '. Data tetap disimpan, mohon periksa kembali.');
            }
            flash('success', 'Luas lahan ditambahkan.');
        }
        redirect($back);
    }
    if ($aksi === 'hapus') {
        $id = (int)post('id');
        $row = q_one('SELECT * FROM luas_lahan WHERE id_luas = ? AND id_musim = ?', [$id, $idm]);
        if ($row && lahan_terkunci($idm, (int)$row['id_desa'])) { flash('danger', 'Alokasi desa ini sudah ditetapkan, luas lahan dikunci.'); redirect($back); }
        if ($row) { q('DELETE FROM luas_lahan WHERE id_luas = ?', [$id]); log_aktivitas('hapus', 'luas_lahan', $id, ''); flash('success', 'Data luas lahan dihapus.'); }
        redirect($back);
    }
    if ($aksi === 'salin') {
        $src = (int)post('dari_musim');
        if ($src === $idm || !q_one('SELECT 1 FROM musim_tanam WHERE id_musim = ?', [$src])) { flash('danger', 'Musim sumber tidak valid.'); redirect($back); }
        $st = q("INSERT IGNORE INTO luas_lahan (id_musim, id_desa, id_komoditas, luas_hektar)
                 SELECT ?, l.id_desa, l.id_komoditas, l.luas_hektar FROM luas_lahan l
                 WHERE l.id_musim = ? AND NOT EXISTS (SELECT 1 FROM alokasi a WHERE a.id_musim = ? AND a.id_desa = l.id_desa AND a.status = 'ditetapkan')",
                [$idm, $src, $idm]);
        log_aktivitas('salin', 'luas_lahan', null, "Salin dari musim $src ke $idm: " . $st->rowCount() . ' baris');
        flash('success', $st->rowCount() . ' data luas lahan disalin.');
        redirect($back);
    }
    redirect($back);
}

[$musim, $musimList] = musim_terpilih();
if (!$musim) {
    layout_start('Data Luas Lahan', 'Home / Data Master / Luas Lahan');
    echo '<div class="alert alert-warning">Belum ada musim tanam. <a href="' . e(url('musim')) . '">Buat musim tanam dulu</a>.</div>';
    layout_end(); exit;
}
$idm = (int)$musim['id_musim'];
$edit = get('edit') !== '' ? q_one('SELECT * FROM luas_lahan WHERE id_luas = ? AND id_musim = ?', [(int)get('edit'), $idm]) : null;
$desaList = q_all('SELECT * FROM desa ORDER BY nama_desa');
$komList = q_all('SELECT * FROM komoditas ORDER BY nama_komoditas');
$rows = q_all("SELECT l.*, d.nama_desa, d.status_geografis, k.nama_komoditas, k.zona_tanam,
               (SELECT COUNT(*) FROM alokasi a WHERE a.id_musim = l.id_musim AND a.id_desa = l.id_desa AND a.status='ditetapkan') AS terkunci
               FROM luas_lahan l JOIN desa d ON d.id_desa = l.id_desa JOIN komoditas k ON k.id_komoditas = l.id_komoditas
               WHERE l.id_musim = ? ORDER BY d.nama_desa, k.nama_komoditas", [$idm]);
$total = array_sum(array_column($rows, 'luas_hektar'));

layout_start('Data Luas Lahan', 'Home / Data Master / Luas Lahan');
?>
<div class="toolbar"><?= musim_selector($musimList, $musim, 'luas-lahan') ?></div>

<div class="card">
  <h3><?= $edit ? '<i class="bi bi-pencil-square me-1"></i>Edit Luas Lahan' : '<i class="bi bi-plus-circle me-1"></i>Tambah Luas Lahan' ?> <small class="muted">- <?= e($musim['nama_musim']) ?></small></h3>
  <form method="post" action="<?= e(url('luas-lahan')) ?>" class="grid2">
    <?= csrf_field() ?><input type="hidden" name="aksi" value="simpan"><input type="hidden" name="id_musim" value="<?= $idm ?>"><input type="hidden" name="id" value="<?= (int)($edit['id_luas'] ?? 0) ?>">
    <div><label class="form-label">Desa *</label>
      <select class="form-select" name="id_desa" <?= $edit ? 'disabled' : 'required' ?>>
        <?php foreach ($desaList as $d): ?><option value="<?= (int)$d['id_desa'] ?>" <?= $edit && $edit['id_desa'] == $d['id_desa'] ? 'selected' : '' ?>><?= e($d['nama_desa']) ?> (<?= e(zona_label($d['status_geografis'])) ?>)</option><?php endforeach; ?>
      </select></div>
    <div><label class="form-label">Komoditas *</label>
      <select class="form-select" name="id_komoditas" <?= $edit ? 'disabled' : 'required' ?>>
        <?php foreach ($komList as $k): ?><option value="<?= (int)$k['id_komoditas'] ?>" <?= $edit && $edit['id_komoditas'] == $k['id_komoditas'] ? 'selected' : '' ?>><?= e($k['nama_komoditas']) ?> (<?= e(zona_label($k['zona_tanam'])) ?>)</option><?php endforeach; ?>
      </select></div>
    <div><label class="form-label">Luas (Hektar) *</label><input class="form-control" type="number" name="luas_hektar" min="0.01" step="0.01" required value="<?= e($edit['luas_hektar'] ?? '') ?>"></div>
    <div class="full"><button class="btn btn-primary" type="submit"><i class="bi bi-save me-1"></i>Simpan</button>
      <?php if ($edit): ?><a class="btn btn-outline-success" href="<?= e(url('luas-lahan?musim=' . $idm)) ?>">Batal</a><?php endif; ?></div>
  </form>
  <?php if (count($musimList) > 1): ?>
  <hr>
  <form method="post" action="<?= e(url('luas-lahan')) ?>" class="inline-form" data-confirm="Salin data luas lahan ke musim ini? Data yang sudah ada tidak ditimpa.">
    <?= csrf_field() ?><input type="hidden" name="aksi" value="salin"><input type="hidden" name="id_musim" value="<?= $idm ?>">
    <label class="form-label">Salin data dari musim:</label>
    <select class="form-select" name="dari_musim"><?php foreach ($musimList as $m): if ($m['id_musim'] == $idm) continue; ?><option value="<?= (int)$m['id_musim'] ?>"><?= e($m['nama_musim']) ?></option><?php endforeach; ?></select>
    <button class="btn btn-sm btn-outline-success" type="submit"><i class="bi bi-files me-1"></i>Salin</button>
  </form>
  <?php endif; ?>
</div>

<div class="card">
  <h3><i class="bi bi-card-list me-1"></i>Daftar Luas Lahan (<?= count($rows) ?> data)</h3>
  <div class="table-wrap"><table class="table align-middle">
    <thead><tr><th>Desa</th><th>Komoditas</th><th class="num">Luas (ha)</th><th>Keterangan</th><th>Aksi</th></tr></thead>
    <tbody>
    <?php if (!$rows): ?><tr><td colspan="5" class="center muted">Belum ada data luas lahan untuk musim ini.</td></tr><?php endif; ?>
    <?php foreach (group_rows($rows, 'nama_desa') as $desa => $items): foreach ($items as $i => $r): ?>
      <tr class="<?= $i === 0 ? 'grp-first' : '' ?>">
        <?php if ($i === 0): ?><td rowspan="<?= count($items) ?>" class="cell-group"><?= e($desa) ?><br><small class="muted"><?= e(zona_label($r['status_geografis'])) ?></small></td><?php endif; ?>
        <td><?= e($r['nama_komoditas']) ?></td>
        <td class="num"><?= fmt($r['luas_hektar']) ?> ha</td>
        <td>
          <?php if (!zona_cocok($r['status_geografis'], $r['zona_tanam'])): ?><?= badge('⚠ Zona tidak sesuai', 'orange') ?><?php endif; ?>
          <?php if ($r['terkunci']): ?><?= badge('🔒 Alokasi ditetapkan', 'gray') ?><?php endif; ?>
        </td>
        <td class="actions">
          <?php if (!$r['terkunci']): ?>
          <a class="btn btn-sm btn-outline-success" href="<?= e(url('luas-lahan?musim=' . $idm . '&edit=' . $r['id_luas'])) ?>"><i class="bi bi-pencil-square me-1"></i>Edit</a>
          <form method="post" action="<?= e(url('luas-lahan')) ?>" data-confirm="Hapus data ini?">
            <?= csrf_field() ?><input type="hidden" name="aksi" value="hapus"><input type="hidden" name="id_musim" value="<?= $idm ?>"><input type="hidden" name="id" value="<?= (int)$r['id_luas'] ?>">
            <button class="btn btn-sm btn-danger" type="submit"><i class="bi bi-trash me-1"></i>Hapus</button></form>
          <?php else: ?><span class="muted small">terkunci</span><?php endif; ?>
        </td>
      </tr>
    <?php endforeach; endforeach; ?>
    <?php if ($rows): ?><tr class="total-row"><td colspan="2">TOTAL</td><td class="num"><?= fmt($total) ?> ha</td><td colspan="2"></td></tr><?php endif; ?>
    </tbody></table></div>
</div>
<?php layout_end();
