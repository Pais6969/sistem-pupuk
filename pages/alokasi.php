<?php
/** Hasil hitung terbaru: Luas Lahan x Dosis per Hektar, dikelompokkan per desa+pupuk. */
function rincian_hitung(int $idm): array {
    return q_all("SELECT l.id_desa, pp.id_pupuk, k.nama_komoditas, l.luas_hektar, pp.jumlah_per_hektar,
                  l.luas_hektar * pp.jumlah_per_hektar AS subtotal
                  FROM luas_lahan l
                  JOIN parameter_pupuk pp ON pp.id_komoditas = l.id_komoditas
                  JOIN komoditas k ON k.id_komoditas = l.id_komoditas
                  WHERE l.id_musim = ? AND l.luas_hektar > 0
                  ORDER BY k.nama_komoditas", [$idm]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = post('aksi'); $idm = (int)post('id_musim');
    $back = 'alokasi?musim=' . $idm;
    if (!q_one('SELECT 1 FROM musim_tanam WHERE id_musim = ?', [$idm])) { flash('danger', 'Musim tidak valid.'); redirect('alokasi'); }

    if ($aksi === 'hitung') {
        if (!is_role('admin')) forbidden();
        $calc = [];
        foreach (rincian_hitung($idm) as $r) { $calc[$r['id_desa'] . '-' . $r['id_pupuk']] = ($calc[$r['id_desa'] . '-' . $r['id_pupuk']] ?? 0) + (float)$r['subtotal']; }
        $pdo = db(); $pdo->beginTransaction();
        try {
            $baru = 0; $upd = 0; $kunci = 0; $hapus = 0;
            foreach ($calc as $key => $sum) {
                [$desa, $pupuk] = array_map('intval', explode('-', $key));
                $ex = q_one('SELECT id_alokasi, status FROM alokasi WHERE id_musim = ? AND id_desa = ? AND id_pupuk = ?', [$idm, $desa, $pupuk]);
                if (!$ex) { q("INSERT INTO alokasi (id_musim, id_desa, id_pupuk, jumlah_alokasi, status) VALUES (?,?,?,?, 'draft')", [$idm, $desa, $pupuk, round($sum, 2)]); $baru++; }
                elseif ($ex['status'] === 'draft') { q('UPDATE alokasi SET jumlah_alokasi = ? WHERE id_alokasi = ?', [round($sum, 2), $ex['id_alokasi']]); $upd++; }
                else { $kunci++; }
            }
            // hapus draft yang sudah tidak punya dasar perhitungan
            foreach (q_all("SELECT id_alokasi, id_desa, id_pupuk FROM alokasi WHERE id_musim = ? AND status = 'draft'", [$idm]) as $a) {
                if (!isset($calc[$a['id_desa'] . '-' . $a['id_pupuk']])) { q('DELETE FROM alokasi WHERE id_alokasi = ?', [$a['id_alokasi']]); $hapus++; }
            }
            $pdo->commit();
        } catch (Throwable $e) { $pdo->rollBack(); throw $e; }
        log_aktivitas('hitung', 'alokasi', null, "Musim $idm: baru $baru, diperbarui $upd, terkunci $kunci, dihapus $hapus");
        flash('success', "Perhitungan selesai: $baru baru, $upd draft diperbarui" . ($hapus ? ", $hapus draft usang dihapus" : '') . ($kunci ? ", $kunci alokasi ditetapkan tidak diubah (terkunci)" : '') . '.');
        redirect($back);
    }

    if ($aksi === 'tetapkan' || $aksi === 'tetapkan_semua') {
        $uid = (int)user()['id_user'];
        if ($aksi === 'tetapkan') {
            $st = q("UPDATE alokasi SET status='ditetapkan', ditetapkan_oleh=?, ditetapkan_at=NOW() WHERE id_alokasi=? AND id_musim=? AND status='draft'", [$uid, (int)post('id'), $idm]);
        } else {
            $st = q("UPDATE alokasi SET status='ditetapkan', ditetapkan_oleh=?, ditetapkan_at=NOW() WHERE id_musim=? AND status='draft'", [$uid, $idm]);
        }
        log_aktivitas('tetapkan', 'alokasi', $aksi === 'tetapkan' ? (int)post('id') : null, $st->rowCount() . ' alokasi ditetapkan');
        flash('success', $st->rowCount() . ' alokasi ditetapkan. Alokasi yang sudah ditetapkan terkunci dan siap dikirim.');
        redirect($back);
    }

    if ($aksi === 'batal') {
        if (!is_role('admin')) forbidden();
        $id = (int)post('id');
        $n = (int)q_val("SELECT COUNT(*) FROM realisasi WHERE id_alokasi = ? AND status <> 'dibatalkan'", [$id]);
        if ($n > 0) { flash('danger', 'Penetapan tidak bisa dibatalkan: sudah ada ' . $n . ' pengiriman/realisasi aktif untuk alokasi ini.'); redirect($back); }
        q("UPDATE alokasi SET status='draft', ditetapkan_oleh=NULL, ditetapkan_at=NULL WHERE id_alokasi=? AND id_musim=?", [$id, $idm]);
        log_aktivitas('batal_tetapkan', 'alokasi', $id, 'Penetapan dibatalkan');
        flash('success', 'Penetapan dibatalkan, alokasi kembali menjadi draft.');
        redirect($back);
    }
    redirect($back);
}

[$musim, $musimList] = musim_terpilih();
if (!$musim) {
    layout_start('Perhitungan Alokasi', 'Home / Perhitungan Alokasi');
    echo '<div class="alert alert-warning">Belum ada musim tanam.</div>'; layout_end(); exit;
}
$idm = (int)$musim['id_musim'];

$rincian = []; $calc = [];
foreach (rincian_hitung($idm) as $r) {
    $k = $r['id_desa'] . '-' . $r['id_pupuk'];
    $rincian[$k][] = $r; $calc[$k] = ($calc[$k] ?? 0) + (float)$r['subtotal'];
}
$rows = q_all("SELECT a.*, d.nama_desa, p.nama_pupuk, p.satuan,
               (SELECT COALESCE(SUM(r.jumlah_dikirim),0) FROM realisasi r WHERE r.id_alokasi = a.id_alokasi AND r.status <> 'dibatalkan') AS dikirim,
               (SELECT COALESCE(SUM(r.jumlah_diterima),0) FROM realisasi r WHERE r.id_alokasi = a.id_alokasi AND r.status IN ('sesuai','tidak_sesuai')) AS diterima,
               (SELECT COUNT(*) FROM realisasi r WHERE r.id_alokasi = a.id_alokasi AND r.status <> 'dibatalkan') AS n_real
               FROM alokasi a JOIN desa d ON d.id_desa = a.id_desa JOIN pupuk p ON p.id_pupuk = a.id_pupuk
               WHERE a.id_musim = ? ORDER BY d.nama_desa, p.nama_pupuk", [$idm]);
$nDraft = 0; $total = 0; $nBeda = 0;
foreach ($rows as &$r) {
    if ($r['status'] === 'draft') $nDraft++;
    $total += (float)$r['jumlah_alokasi'];
    $k = $r['id_desa'] . '-' . $r['id_pupuk'];
    $r['beda'] = !isset($calc[$k]) || abs($calc[$k] - (float)$r['jumlah_alokasi']) > 0.005;
    if ($r['beda']) $nBeda++;
}
unset($r);
$isAdmin = is_role('admin');

layout_start('Perhitungan Alokasi', 'Home / Perhitungan Alokasi');
?>
<div class="toolbar"><?= musim_selector($musimList, $musim, 'alokasi') ?></div>

<div class="card">
  <div class="card-head">
    <div>
      <h3><i class="bi bi-calculator me-1"></i>Hitung Alokasi Otomatis</h3>
      <p class="muted">Musim: <strong><?= e($musim['nama_musim']) ?></strong> &mdash; Rumus: <code>Luas Lahan × Dosis Pupuk per Hektar</code>, dijumlahkan per desa dan jenis pupuk.<br>
      Alokasi <em>draft</em> dihitung ulang, alokasi <em>ditetapkan</em> tidak berubah (terkunci).</p>
    </div>
    <div class="btn-group">
      <?php if ($isAdmin): ?>
      <form method="post" action="<?= e(url('alokasi')) ?>"><?= csrf_field() ?><input type="hidden" name="aksi" value="hitung"><input type="hidden" name="id_musim" value="<?= $idm ?>">
        <button class="btn btn-warning" type="submit"><i class="bi bi-lightning-charge me-1"></i>Hitung Sekarang</button></form>
      <?php endif; ?>
      <?php if ($nDraft > 0): ?>
      <form method="post" action="<?= e(url('alokasi')) ?>" data-confirm="Tetapkan semua <?= $nDraft ?> alokasi draft? Setelah ditetapkan, alokasi terkunci dan bisa dikirim.">
        <?= csrf_field() ?><input type="hidden" name="aksi" value="tetapkan_semua"><input type="hidden" name="id_musim" value="<?= $idm ?>">
        <button class="btn btn-primary" type="submit"><i class="bi bi-check2-circle me-1"></i>Tetapkan Semua Draft (<?= $nDraft ?>)</button></form>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php if ($nBeda > 0): ?>
<div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-1"></i><?= $nBeda ?> alokasi tidak sama dengan hasil hitung terbaru (data luas lahan atau dosis berubah). <?= $isAdmin ? 'Klik "Hitung Sekarang" untuk memperbarui yang masih draft; yang sudah ditetapkan harus dibatalkan penetapannya dulu.' : 'Hubungi admin.' ?></div>
<?php endif; ?>

<div class="card">
  <h3><i class="bi bi-card-list me-1"></i>Hasil Perhitungan (<?= count($rows) ?> baris)</h3>
  <div class="table-wrap"><table class="table align-middle">
    <thead><tr><th>Desa</th><th>Jenis Pupuk</th><th class="num">Jumlah Alokasi</th><th>Rincian</th><th>Penyaluran</th><th>Status</th><th>Aksi</th></tr></thead>
    <tbody>
    <?php if (!$rows): ?><tr><td colspan="7" class="center muted">Belum ada hasil perhitungan. <?= $isAdmin ? 'Klik "Hitung Sekarang" untuk memulai.' : '' ?></td></tr><?php endif; ?>
    <?php foreach (group_rows($rows, 'nama_desa') as $desa => $items): foreach ($items as $i => $r): $k = $r['id_desa'] . '-' . $r['id_pupuk']; ?>
      <tr class="<?= $i === 0 ? 'grp-first' : '' ?>">
        <?php if ($i === 0): ?><td rowspan="<?= count($items) ?>" class="cell-group"><?= e($desa) ?></td><?php endif; ?>
        <td><?= e($r['nama_pupuk']) ?></td>
        <td class="num"><strong><?= fmt($r['jumlah_alokasi']) ?> <?= e($r['satuan']) ?></strong>
          <?php if ($r['beda']): ?><br><?= badge('⚠ berbeda dari hitung terbaru', 'orange') ?><?php endif; ?></td>
        <td><details><summary>Lihat</summary>
          <?php foreach ($rincian[$k] ?? [] as $x): ?>
            <div class="small"><?= e($x['nama_komoditas']) ?>: <?= fmt($x['luas_hektar']) ?> ha × <?= fmt($x['jumlah_per_hektar']) ?> = <?= fmt($x['subtotal']) ?></div>
          <?php endforeach; if (empty($rincian[$k])): ?><div class="small muted">Tidak ada dasar perhitungan saat ini.</div><?php endif; ?>
        </details></td>
        <td class="small">Dikirim: <?= fmt($r['dikirim']) ?><br>Diterima: <?= fmt($r['diterima']) ?></td>
        <td><?= $r['status'] === 'ditetapkan' ? badge('Ditetapkan', 'green') : badge('Draft', 'orange') ?>
          <?php if ($r['ditetapkan_at']): ?><br><small class="muted"><?= e(tgl($r['ditetapkan_at'])) ?></small><?php endif; ?></td>
        <td class="actions">
          <?php if ($r['status'] === 'draft'): ?>
          <form method="post" action="<?= e(url('alokasi')) ?>"><?= csrf_field() ?><input type="hidden" name="aksi" value="tetapkan"><input type="hidden" name="id_musim" value="<?= $idm ?>"><input type="hidden" name="id" value="<?= (int)$r['id_alokasi'] ?>">
            <button class="btn btn-sm btn-primary" type="submit"><i class="bi bi-check2-circle me-1"></i>Tetapkan</button></form>
          <?php elseif ($isAdmin && (int)$r['n_real'] === 0): ?>
          <form method="post" action="<?= e(url('alokasi')) ?>" data-confirm="Batalkan penetapan alokasi ini?"><?= csrf_field() ?><input type="hidden" name="aksi" value="batal"><input type="hidden" name="id_musim" value="<?= $idm ?>"><input type="hidden" name="id" value="<?= (int)$r['id_alokasi'] ?>">
            <button class="btn btn-sm btn-outline-success" type="submit"><i class="bi bi-arrow-counterclockwise me-1"></i>Batalkan</button></form>
          <?php else: ?><span class="muted small"><i class="bi bi-lock me-1"></i>terkunci</span><?php endif; ?>
        </td>
      </tr>
    <?php endforeach; endforeach; ?>
    <?php if ($rows): ?><tr class="total-row"><td colspan="2">TOTAL</td><td class="num"><?= fmt($total) ?> kg</td><td colspan="4"></td></tr><?php endif; ?>
    </tbody></table></div>
</div>
<?php layout_end();
