<?php
$u = user();
$idDesa = (int)($u['id_desa'] ?? 0);
$validDate = fn($d) => (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && strtotime($d) !== false && date('Y-m-d', strtotime($d)) === $d;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('aksi') === 'konfirmasi') {
    $id = (int)post('id');
    if (!$idDesa) { flash('danger', 'Akun Anda belum terhubung ke desa.'); redirect('penerimaan'); }
    $r = q_one("SELECT r.*, a.id_desa, p.nama_pupuk FROM realisasi r JOIN alokasi a ON a.id_alokasi = r.id_alokasi JOIN pupuk p ON p.id_pupuk = a.id_pupuk
                WHERE r.id_realisasi = ? AND a.id_desa = ?", [$id, $idDesa]);
    if (!$r) { flash('danger', 'Data pengiriman tidak ditemukan untuk desa Anda.'); redirect('penerimaan'); }
    if ($r['status'] !== 'dikirim') { flash('danger', 'Pengiriman ini sudah dikonfirmasi atau dibatalkan.'); redirect('penerimaan'); }

    $diterima = num(post('jumlah_diterima')); $tgl = post('tanggal_terima'); $catatan = post('catatan'); $kode = post('kode');
    $dikirim = (float)$r['jumlah_dikirim'];
    $err = '';
    $blokKey = 'kode_salah_' . $id;
    if (($_SESSION[$blokKey . '_until'] ?? 0) > time()) $err = 'Terlalu banyak kode salah. Coba lagi beberapa menit lagi.';
    elseif ($diterima === null || $diterima < 0) $err = 'Jumlah diterima harus angka 0 atau lebih.';
    elseif ($diterima - $dikirim > 0.005) $err = 'Jumlah diterima tidak boleh melebihi jumlah dikirim (' . fmt($dikirim) . ' kg).';
    elseif (!$validDate($tgl) || $tgl > date('Y-m-d') || $tgl < $r['tanggal_kirim']) $err = 'Tanggal terima harus antara tanggal kirim dan hari ini.';
    elseif ($dikirim - $diterima > 0.005 && mb_strlen($catatan) < 5) $err = 'Jumlah diterima kurang dari yang dikirim: catatan penjelasan wajib diisi (min. 5 karakter).';
    elseif ($diterima > 0.005 && !hash_equals((string)$r['kode_verifikasi'], $kode)) {
        $_SESSION[$blokKey] = ($_SESSION[$blokKey] ?? 0) + 1;
        if ($_SESSION[$blokKey] >= 5) { $_SESSION[$blokKey] = 0; $_SESSION[$blokKey . '_until'] = time() + 300; }
        log_aktivitas('kode_salah', 'realisasi', $id, 'Kode verifikasi salah saat konfirmasi');
        $err = 'Kode verifikasi salah. Minta kode yang benar dari petugas pengantar.';
    }
    if ($err) { flash('danger', $err); redirect('penerimaan'); }

    $fe = null;
    $foto = simpan_foto_bukti($_FILES['foto'] ?? null, $fe);
    if (!$foto) { flash('danger', $fe); redirect('penerimaan'); }

    $sesuai = abs($dikirim - $diterima) <= 0.005;
    try {
        $st = q("UPDATE realisasi SET status=?, jumlah_diterima=?, tanggal_terima=?, foto_bukti=?, catatan_kades=?, dikonfirmasi_oleh=?, dikonfirmasi_at=NOW(), status_tinjauan=?
                 WHERE id_realisasi=? AND status='dikirim'",
                [$sesuai ? 'sesuai' : 'tidak_sesuai', round($diterima, 2), $tgl, $foto, $catatan !== '' ? $catatan : null, (int)$u['id_user'], $sesuai ? 'tidak_perlu' : 'dalam_pemeriksaan', $id]);
        if (!$st->rowCount()) { @unlink(BASE_PATH . '/uploads/bukti/' . $foto); flash('danger', 'Pengiriman sudah dikonfirmasi sebelumnya.'); redirect('penerimaan'); }
    } catch (Throwable $e) { @unlink(BASE_PATH . '/uploads/bukti/' . $foto); throw $e; }

    log_aktivitas('konfirmasi', 'realisasi', $id, ($sesuai ? 'Sesuai' : 'TIDAK SESUAI') . ': diterima ' . $diterima . ' dari ' . $dikirim . ' kg');
    flash($sesuai ? 'success' : 'warning', $sesuai ? 'Terima kasih, penerimaan pupuk dikonfirmasi sesuai.' : 'Penerimaan dicatat TIDAK SESUAI (selisih ' . fmt($dikirim - $diterima) . ' kg). Admin dan Dinas akan memeriksa laporan Anda.');
    redirect('penerimaan');
}

$musim = musim_aktif();
layout_start('Penerimaan Pupuk', 'Home / Penerimaan Pupuk');
if (!$idDesa) { echo '<div class="alert alert-danger">Akun Anda belum terhubung ke desa manapun. Hubungi admin.</div>'; layout_end(); exit; }

$pending = q_all("SELECT r.*, p.nama_pupuk, p.satuan, m.nama_musim FROM realisasi r JOIN alokasi a ON a.id_alokasi = r.id_alokasi
                  JOIN pupuk p ON p.id_pupuk = a.id_pupuk JOIN musim_tanam m ON m.id_musim = a.id_musim
                  WHERE a.id_desa = ? AND r.status = 'dikirim' ORDER BY r.tanggal_kirim", [$idDesa]);
$ringkas = $musim ? q_all("SELECT p.nama_pupuk, p.satuan, a.jumlah_alokasi,
                    COALESCE((SELECT SUM(r.jumlah_diterima) FROM realisasi r WHERE r.id_alokasi=a.id_alokasi AND r.status IN ('sesuai','tidak_sesuai')),0) AS diterima
                    FROM alokasi a JOIN pupuk p ON p.id_pupuk=a.id_pupuk WHERE a.id_desa = ? AND a.id_musim = ? AND a.status='ditetapkan' ORDER BY p.nama_pupuk", [$idDesa, $musim['id_musim']]) : [];
$riwayat = q_all("SELECT r.*, p.nama_pupuk FROM realisasi r JOIN alokasi a ON a.id_alokasi = r.id_alokasi JOIN pupuk p ON p.id_pupuk = a.id_pupuk
                  WHERE a.id_desa = ? AND r.status <> 'dikirim' ORDER BY r.tanggal_kirim DESC, r.id_realisasi DESC LIMIT 50", [$idDesa]);
?>
<div class="card">
  <h3><i class="bi bi-bar-chart me-1"></i>Jatah Pupuk Desa <?= e($u['nama_desa'] ?? '') ?> <small class="muted">- <?= e($musim['nama_musim'] ?? 'tidak ada musim aktif') ?></small></h3>
  <div class="table-wrap"><table class="table align-middle">
    <thead><tr><th>Pupuk</th><th class="num">Alokasi</th><th class="num">Sudah Diterima</th><th class="num">Sisa</th></tr></thead>
    <tbody>
    <?php if (!$ringkas): ?><tr><td colspan="4" class="center muted">Belum ada alokasi yang ditetapkan untuk desa Anda.</td></tr><?php endif; ?>
    <?php foreach ($ringkas as $r): ?>
      <tr><td><?= e($r['nama_pupuk']) ?></td><td class="num"><?= fmt($r['jumlah_alokasi']) ?> <?= e($r['satuan']) ?></td><td class="num"><?= fmt($r['diterima']) ?></td><td class="num"><?= fmt($r['jumlah_alokasi'] - $r['diterima']) ?></td></tr>
    <?php endforeach; ?>
    </tbody></table></div>
</div>

<h3 class="section-title"><i class="bi bi-box-seam me-1"></i>Menunggu Konfirmasi Anda (<?= count($pending) ?>)</h3>
<?php if (!$pending): ?><div class="card"><p class="muted center">Tidak ada kiriman yang menunggu konfirmasi.</p></div><?php endif; ?>
<?php foreach ($pending as $r): $late = realisasi_overdue($r); ?>
<div class="card <?= $late ? 'card-late' : '' ?>">
  <div class="card-head">
    <div><h3><?= e($r['nama_pupuk']) ?> - dikirim <?= fmt($r['jumlah_dikirim']) ?> <?= e($r['satuan']) ?></h3>
      <p class="muted small">Dikirim <?= e(tgl($r['tanggal_kirim'])) ?> oleh <?= e($r['petugas']) ?><?= $r['plat_kendaraan'] ? ' (' . e($r['plat_kendaraan']) . ')' : '' ?> &middot; batas konfirmasi <?= e(tgl($r['batas_konfirmasi'])) ?></p></div>
    <?= $late ? badge('Melewati batas konfirmasi', 'red') : badge('Menunggu konfirmasi', 'blue') ?>
  </div>
  <form method="post" action="<?= e(url('penerimaan')) ?>" enctype="multipart/form-data" class="grid2">
    <?= csrf_field() ?><input type="hidden" name="aksi" value="konfirmasi"><input type="hidden" name="id" value="<?= (int)$r['id_realisasi'] ?>">
    <div><label class="form-label">Jumlah yang BENAR-BENAR diterima (kg) *</label><input class="form-control" type="number" name="jumlah_diterima" min="0" max="<?= e($r['jumlah_dikirim']) ?>" step="0.01" required placeholder="Isi sesuai timbangan / hitungan sebenarnya"></div>
    <div><label class="form-label">Tanggal Diterima *</label><input class="form-control" type="date" name="tanggal_terima" required min="<?= e($r['tanggal_kirim']) ?>" max="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d') ?>"></div>
    <div><label class="form-label">Kode Verifikasi dari Petugas <small class="muted">(wajib jika ada pupuk diterima)</small></label><input class="form-control" type="text" name="kode" inputmode="numeric" maxlength="6" autocomplete="off" placeholder="6 digit"></div>
    <div><label class="form-label">Foto Bukti Penerimaan * <small class="muted">(foto pupuk di lokasi, JPG/PNG)</small></label><input type="file" name="foto" accept="image/*" required class="form-control js-photo"></div>
    <div class="full"><label class="form-label">Catatan <small class="muted">(wajib jika jumlah diterima kurang dari yang dikirim)</small></label><textarea class="form-control" name="catatan" rows="2" placeholder="Contoh: 5 karung tidak ada, kemasan robek, dsb."></textarea></div>
    <div class="full"><button class="btn btn-primary" type="submit" data-confirm-form="Kirim konfirmasi? Data tidak dapat diubah setelah dikirim."><i class="bi bi-check2-circle me-1"></i>Kirim Konfirmasi Penerimaan</button></div>
  </form>
</div>
<?php endforeach; ?>

<div class="card">
  <h3><i class="bi bi-clock-history me-1"></i>Riwayat Penerimaan</h3>
  <div class="table-wrap"><table class="table align-middle">
    <thead><tr><th>Tgl Kirim</th><th>Pupuk</th><th class="num">Dikirim</th><th class="num">Diterima</th><th>Status</th><th>Bukti</th><th>Catatan / Tanggapan</th></tr></thead>
    <tbody>
    <?php if (!$riwayat): ?><tr><td colspan="7" class="center muted">Belum ada riwayat.</td></tr><?php endif; ?>
    <?php foreach ($riwayat as $r): ?>
      <tr><td><?= e(tgl($r['tanggal_kirim'])) ?></td><td><?= e($r['nama_pupuk']) ?></td>
        <td class="num"><?= fmt($r['jumlah_dikirim']) ?> kg</td><td class="num"><?= $r['jumlah_diterima'] !== null ? fmt($r['jumlah_diterima']) . ' kg' : '-' ?></td>
        <td><?= realisasi_badge($r) ?> <?= tinjauan_badge($r) ?></td>
        <td><?php if ($r['foto_bukti']): ?><a href="<?= e(url('bukti?id=' . $r['id_realisasi'])) ?>" data-lightbox target="_blank" rel="noopener"><img class="thumb" loading="lazy" src="<?= e(url('bukti?id=' . $r['id_realisasi'] . '&t=1')) ?>" alt="Bukti"></a><?php else: ?>-<?php endif; ?></td>
        <td class="small"><?= $r['catatan_kades'] ? '<strong>Anda:</strong> ' . nl2br(e($r['catatan_kades'])) . '<br>' : '' ?><?= $r['tanggapan'] ? '<strong>Tanggapan:</strong> ' . nl2br(e($r['tanggapan'])) : '' ?></td></tr>
    <?php endforeach; ?>
    </tbody></table></div>
</div>
<?php layout_end();
