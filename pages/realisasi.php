<?php
$isAdmin = is_role('admin');
$validDate = fn($d) => (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && strtotime($d) !== false && date('Y-m-d', strtotime($d)) === $d;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = post('aksi'); $idm = (int)post('id_musim'); $back = 'realisasi?musim=' . $idm;

    /* ---------- Admin mencatat pengiriman ---------- */
    if ($aksi === 'kirim') {
        if (!$isAdmin) forbidden();
        $ida = (int)post('id_alokasi'); $jml = num(post('jumlah_dikirim')); $tgl = post('tanggal_kirim');
        $petugas = post('petugas'); $plat = strtoupper(post('plat_kendaraan'));
        $err = '';
        if ($jml === null || $jml <= 0) $err = 'Jumlah dikirim harus lebih dari 0.';
        elseif (!$validDate($tgl) || $tgl > date('Y-m-d')) $err = 'Tanggal kirim tidak valid (tidak boleh di masa depan).';
        elseif ($petugas === '' || mb_strlen($petugas) > 100) $err = 'Nama petugas/sopir wajib diisi.';
        elseif (mb_strlen($plat) > 20) $err = 'Plat kendaraan terlalu panjang.';
        if ($err) { flash('danger', $err); redirect($back); }

        $pdo = db(); $pdo->beginTransaction();
        try {
            $a = q_one('SELECT a.*, d.nama_desa, p.nama_pupuk FROM alokasi a JOIN desa d ON d.id_desa = a.id_desa JOIN pupuk p ON p.id_pupuk = a.id_pupuk WHERE a.id_alokasi = ? FOR UPDATE', [$ida]);
            if (!$a || $a['status'] !== 'ditetapkan') { $pdo->rollBack(); flash('danger', 'Alokasi belum ditetapkan atau tidak ditemukan.'); redirect($back); }
            $sisa = (float)q_val('SELECT ' . sisa_alokasi_sql() . ' FROM alokasi a WHERE a.id_alokasi = ?', [$ida]);
            if ($jml - $sisa > 0.005) { $pdo->rollBack(); flash('danger', 'Jumlah melebihi sisa alokasi (' . fmt($sisa) . ' kg) untuk ' . $a['nama_desa'] . ' - ' . $a['nama_pupuk'] . '.'); redirect($back); }
            $kode = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $batas = date('Y-m-d', strtotime($tgl . ' +' . KONFIRMASI_HARI . ' days'));
            q('INSERT INTO realisasi (id_alokasi, tanggal_kirim, jumlah_dikirim, petugas, plat_kendaraan, kode_verifikasi, batas_konfirmasi, dibuat_oleh) VALUES (?,?,?,?,?,?,?,?)',
              [$ida, $tgl, round($jml, 2), $petugas, $plat ?: null, $kode, $batas, (int)user()['id_user']]);
            $newId = (int)$pdo->lastInsertId();
            $pdo->commit();
        } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }

        log_aktivitas('kirim', 'realisasi', $newId, $a['nama_desa'] . ' - ' . $a['nama_pupuk'] . ' ' . $jml . ' kg, petugas ' . $petugas);
        flash('success', 'Pengiriman dicatat (status: menunggu konfirmasi kepala desa). KODE VERIFIKASI: ' . $kode . ' - berikan ke petugas, kepala desa memasukkan kode ini saat konfirmasi.');
        if (!q_val("SELECT COUNT(*) FROM users WHERE role='kepala_desa' AND aktif=1 AND id_desa = ?", [$a['id_desa']])) {
            flash('warning', 'Desa ' . $a['nama_desa'] . ' belum punya akun kepala desa aktif, sehingga belum bisa konfirmasi. Buat akun di menu Kelola User.');
        }
        redirect($back);
    }

    /* ---------- Admin membatalkan pengiriman (sebelum dikonfirmasi) ---------- */
    if ($aksi === 'batal') {
        if (!$isAdmin) forbidden();
        $id = (int)post('id'); $alasan = post('alasan');
        if (mb_strlen($alasan) < 5) { flash('danger', 'Alasan pembatalan wajib diisi (min. 5 karakter).'); redirect($back); }
        $st = q("UPDATE realisasi SET status='dibatalkan', tanggapan=?, ditinjau_oleh=?, ditinjau_at=NOW() WHERE id_realisasi=? AND status='dikirim'", [$alasan, (int)user()['id_user'], $id]);
        if ($st->rowCount()) { log_aktivitas('batal_kirim', 'realisasi', $id, $alasan); flash('success', 'Pengiriman dibatalkan.'); }
        else flash('danger', 'Hanya pengiriman yang belum dikonfirmasi yang bisa dibatalkan.');
        redirect($back);
    }

    /* ---------- Admin / Dinas meninjau penerimaan yang tidak sesuai ---------- */
    if ($aksi === 'tinjau') {
        $id = (int)post('id'); $stt = post('status_tinjauan'); $tgp = post('tanggapan');
        if (!in_array($stt, ['dalam_pemeriksaan', 'selesai'], true)) { flash('danger', 'Status tinjauan tidak valid.'); redirect($back); }
        if (mb_strlen($tgp) < 5) { flash('danger', 'Tanggapan wajib diisi (min. 5 karakter).'); redirect($back); }
        $st = q("UPDATE realisasi SET status_tinjauan=?, tanggapan=?, ditinjau_oleh=?, ditinjau_at=NOW() WHERE id_realisasi=? AND status='tidak_sesuai'", [$stt, $tgp, (int)user()['id_user'], $id]);
        if ($st->rowCount()) { log_aktivitas('tinjau', 'realisasi', $id, $stt . ': ' . $tgp); flash('success', 'Tinjauan disimpan.'); }
        else flash('danger', 'Data tidak ditemukan atau tidak berstatus tidak sesuai.');
        redirect($back);
    }
    redirect($back);
}

[$musim, $musimList] = musim_terpilih();
if (!$musim) {
    layout_start('Realisasi Penyaluran', 'Home / Realisasi');
    echo '<div class="alert alert-warning">Belum ada musim tanam.</div>'; layout_end(); exit;
}
$idm = (int)$musim['id_musim'];
$fq = get('q'); $fs = get('status');

$alokList = $isAdmin ? q_all("SELECT a.id_alokasi, d.nama_desa, p.nama_pupuk, p.satuan, (" . sisa_alokasi_sql() . ") AS sisa
                              FROM alokasi a JOIN desa d ON d.id_desa=a.id_desa JOIN pupuk p ON p.id_pupuk=a.id_pupuk
                              WHERE a.id_musim = ? AND a.status='ditetapkan' HAVING sisa > 0.004 ORDER BY d.nama_desa, p.nama_pupuk", [$idm]) : [];

$where = 'a.id_musim = ?'; $par = [$idm];
if ($fq !== '') { $where .= ' AND d.nama_desa LIKE ?'; $par[] = '%' . $fq . '%'; }
if ($fs === 'menunggu') $where .= " AND r.status='dikirim' AND r.batas_konfirmasi >= CURDATE()";
elseif ($fs === 'terlambat') $where .= " AND r.status='dikirim' AND r.batas_konfirmasi < CURDATE()";
elseif (in_array($fs, ['sesuai', 'tidak_sesuai', 'dibatalkan'], true)) { $where .= ' AND r.status = ?'; $par[] = $fs; }
$rows = q_all("SELECT r.*, a.id_desa, d.nama_desa, p.nama_pupuk, p.satuan, uk.nama_lengkap AS nama_kades, ut.nama_lengkap AS nama_peninjau
               FROM realisasi r JOIN alokasi a ON a.id_alokasi=r.id_alokasi JOIN desa d ON d.id_desa=a.id_desa JOIN pupuk p ON p.id_pupuk=a.id_pupuk
               LEFT JOIN users uk ON uk.id_user = r.dikonfirmasi_oleh LEFT JOIN users ut ON ut.id_user = r.ditinjau_oleh
               WHERE $where ORDER BY d.nama_desa, r.tanggal_kirim DESC, r.id_realisasi DESC", $par);

layout_start('Realisasi Penyaluran', 'Home / Realisasi');
?>
<div class="toolbar"><?= musim_selector($musimList, $musim, 'realisasi') ?></div>

<?php if ($isAdmin): ?>
<div class="card">
  <h3><i class="bi bi-truck me-1"></i>Catat Pengiriman Pupuk</h3>
  <p class="muted">Pengiriman baru berstatus <strong>menunggu konfirmasi</strong>. Realisasi resmi baru tercatat setelah kepala desa mengonfirmasi penerimaan (foto bukti wajib). Batas konfirmasi: <?= KONFIRMASI_HARI ?> hari.</p>
  <?php if (!$alokList): ?>
    <div class="alert alert-info">Belum ada alokasi berstatus <em>ditetapkan</em> yang masih bersisa. <a href="<?= e(url('alokasi?musim=' . $idm)) ?>">Tetapkan alokasi dulu</a>.</div>
  <?php else: ?>
  <form method="post" action="<?= e(url('realisasi')) ?>" class="grid2">
    <?= csrf_field() ?><input type="hidden" name="aksi" value="kirim"><input type="hidden" name="id_musim" value="<?= $idm ?>">
    <div><label class="form-label">Alokasi (Desa - Pupuk) *</label>
      <select class="form-select" name="id_alokasi" required>
        <option value="">-- Pilih --</option>
        <?php foreach ($alokList as $a): ?><option value="<?= (int)$a['id_alokasi'] ?>"><?= e($a['nama_desa']) ?> - <?= e($a['nama_pupuk']) ?> (sisa <?= fmt($a['sisa']) ?> <?= e($a['satuan']) ?>)</option><?php endforeach; ?>
      </select></div>
    <div><label class="form-label">Jumlah Dikirim (kg) *</label><input class="form-control" type="number" name="jumlah_dikirim" min="0.01" step="0.01" required></div>
    <div><label class="form-label">Tanggal Kirim *</label><input class="form-control" type="date" name="tanggal_kirim" required max="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d') ?>"></div>
    <div><label class="form-label">Nama Petugas / Sopir *</label><input class="form-control" type="text" name="petugas" required maxlength="100" placeholder="Nama petugas lapangan"></div>
    <div><label class="form-label">Plat Kendaraan</label><input class="form-control" type="text" name="plat_kendaraan" maxlength="20" placeholder="Contoh: N 1234 AB"></div>
    <div class="full"><button class="btn btn-primary" type="submit"><i class="bi bi-save me-1"></i>Simpan Pengiriman</button></div>
  </form>
  <?php endif; ?>
</div>
<?php endif; ?>

<div class="card">
  <div class="card-head">
    <h3><i class="bi bi-card-list me-1"></i>Riwayat Realisasi <small class="muted">- <?= e($musim['nama_musim']) ?></small></h3>
    <form method="get" action="<?= e(url('realisasi')) ?>" class="inline-form">
      <input type="hidden" name="musim" value="<?= $idm ?>">
      <input class="form-control" type="text" name="q" placeholder="Cari desa..." value="<?= e($fq) ?>">
      <select class="form-select" name="status">
        <?php foreach (['' => 'Semua status', 'menunggu' => 'Menunggu konfirmasi', 'terlambat' => 'Terlambat dikonfirmasi', 'sesuai' => 'Diterima sesuai', 'tidak_sesuai' => 'Tidak sesuai', 'dibatalkan' => 'Dibatalkan'] as $v => $l): ?>
          <option value="<?= e($v) ?>" <?= $fs === $v ? 'selected' : '' ?>><?= e($l) ?></option>
        <?php endforeach; ?>
      </select>
      <button class="btn btn-sm btn-outline-success" type="submit"><i class="bi bi-search me-1"></i>Cari</button>
    </form>
  </div>
  <div class="table-wrap"><table class="table align-middle">
    <thead><tr><th>Desa</th><th>Tgl Kirim</th><th>Pupuk</th><th class="num">Dikirim</th><th class="num">Diterima</th><th class="num">Selisih</th><th>Petugas</th><th>Status</th><th>Bukti</th><th>Aksi / Detail</th></tr></thead>
    <tbody>
    <?php if (!$rows): ?><tr><td colspan="10" class="center muted">Belum ada data realisasi.</td></tr><?php endif; ?>
    <?php foreach (group_rows($rows, 'nama_desa') as $desa => $items): foreach ($items as $i => $r):
        $selisih = $r['jumlah_diterima'] !== null ? (float)$r['jumlah_dikirim'] - (float)$r['jumlah_diterima'] : null; ?>
      <tr class="<?= $i === 0 ? 'grp-first' : '' ?> <?= $r['status'] === 'dibatalkan' ? 'row-muted' : '' ?>">
        <?php if ($i === 0): ?><td rowspan="<?= count($items) ?>" class="cell-group"><?= e($desa) ?></td><?php endif; ?>
        <td><?= e(tgl($r['tanggal_kirim'])) ?></td>
        <td><?= e($r['nama_pupuk']) ?></td>
        <td class="num"><?= fmt($r['jumlah_dikirim']) ?> kg</td>
        <td class="num"><?= $r['jumlah_diterima'] !== null ? fmt($r['jumlah_diterima']) . ' kg' : '-' ?></td>
        <td class="num"><?php if ($selisih !== null): ?><span class="<?= abs($selisih) > 0.005 ? 'text-red' : 'text-green' ?>"><?= fmt($selisih) ?> kg</span><?php else: ?>-<?php endif; ?></td>
        <td class="small"><?= e($r['petugas']) ?><?= $r['plat_kendaraan'] ? '<br>' . e($r['plat_kendaraan']) : '' ?></td>
        <td><?= realisasi_badge($r) ?><br><?= tinjauan_badge($r) ?></td>
        <td><?php if ($r['foto_bukti']): ?><a href="<?= e(url('bukti?id=' . $r['id_realisasi'])) ?>" data-lightbox target="_blank" rel="noopener"><img class="thumb" loading="lazy" src="<?= e(url('bukti?id=' . $r['id_realisasi'] . '&t=1')) ?>" alt="Bukti"></a><?php else: ?>-<?php endif; ?></td>
        <td class="actions-col">
          <?php if ($isAdmin && $r['status'] === 'dikirim'): ?>
            <div class="small">Kode: <code class="kode"><?= e($r['kode_verifikasi']) ?></code><br>Batas: <?= e(tgl($r['batas_konfirmasi'])) ?></div>
          <?php endif; ?>
          <?php if ($r['status'] !== 'dikirim' || $r['catatan_kades'] || $r['tanggapan']): ?>
          <details><summary>Detail</summary>
            <div class="small detail-box">
              <?php if ($r['dikonfirmasi_at']): ?>Dikonfirmasi: <?= e(tgl($r['dikonfirmasi_at'], true)) ?> oleh <?= e($r['nama_kades'] ?? '-') ?><br>Tgl terima: <?= e(tgl($r['tanggal_terima'])) ?><br><?php endif; ?>
              <?php if ($r['catatan_kades']): ?><strong>Catatan kades:</strong> <?= nl2br(e($r['catatan_kades'])) ?><br><?php endif; ?>
              <?php if ($r['tanggapan']): ?><strong>Tanggapan<?= $r['status'] === 'dibatalkan' ? ' (alasan batal)' : '' ?>:</strong> <?= nl2br(e($r['tanggapan'])) ?><?= $r['nama_peninjau'] ? ' <em>(' . e($r['nama_peninjau']) . ')</em>' : '' ?><br><?php endif; ?>
            </div>
          </details>
          <?php endif; ?>
          <?php if ($r['status'] === 'tidak_sesuai'): ?>
          <details><summary>Tinjau</summary>
            <form method="post" action="<?= e(url('realisasi')) ?>" class="stack">
              <?= csrf_field() ?><input type="hidden" name="aksi" value="tinjau"><input type="hidden" name="id_musim" value="<?= $idm ?>"><input type="hidden" name="id" value="<?= (int)$r['id_realisasi'] ?>">
              <select class="form-select" name="status_tinjauan"><option value="dalam_pemeriksaan" <?= $r['status_tinjauan'] === 'dalam_pemeriksaan' ? 'selected' : '' ?>>Dalam pemeriksaan</option><option value="selesai" <?= $r['status_tinjauan'] === 'selesai' ? 'selected' : '' ?>>Selesai ditinjau</option></select>
              <textarea class="form-control" name="tanggapan" rows="2" required minlength="5" placeholder="Tanggapan / hasil pemeriksaan"><?= e($r['tanggapan'] ?? '') ?></textarea>
              <button class="btn btn-sm btn-primary" type="submit">Simpan</button>
            </form>
          </details>
          <?php endif; ?>
          <?php if ($isAdmin && $r['status'] === 'dikirim'): ?>
          <details><summary>Batalkan</summary>
            <form method="post" action="<?= e(url('realisasi')) ?>" class="stack" data-confirm="Batalkan pengiriman ini?">
              <?= csrf_field() ?><input type="hidden" name="aksi" value="batal"><input type="hidden" name="id_musim" value="<?= $idm ?>"><input type="hidden" name="id" value="<?= (int)$r['id_realisasi'] ?>">
              <textarea class="form-control" name="alasan" rows="2" required minlength="5" placeholder="Alasan pembatalan"></textarea>
              <button class="btn btn-sm btn-danger" type="submit">Batalkan Pengiriman</button>
            </form>
          </details>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; endforeach; ?>
    </tbody></table></div>
</div>
<?php layout_end();
