<?php
$u = user();
$musim = musim_aktif();
$isK = $u['role'] === 'kepala_desa';
$fd = $isK ? ' AND a.id_desa = ' . (int)$u['id_desa'] : '';
$idm = $musim ? (int)$musim['id_musim'] : 0;

$totAlokasi = (float)q_val("SELECT COALESCE(SUM(a.jumlah_alokasi),0) FROM alokasi a WHERE a.id_musim = ? AND a.status='ditetapkan'" . $fd, [$idm]);
$totDikirim = (float)q_val("SELECT COALESCE(SUM(r.jumlah_dikirim),0) FROM realisasi r JOIN alokasi a ON a.id_alokasi=r.id_alokasi
                            WHERE a.id_musim = ? AND r.status <> 'dibatalkan'" . $fd, [$idm]);
$totDiterima = (float)q_val("SELECT COALESCE(SUM(r.jumlah_diterima),0) FROM realisasi r JOIN alokasi a ON a.id_alokasi=r.id_alokasi
                             WHERE a.id_musim = ? AND r.status IN ('sesuai','tidak_sesuai')" . $fd, [$idm]);
$persen = $totAlokasi > 0 ? min(100, $totDiterima / $totAlokasi * 100) : 0;

$today = date('Y-m-d');
$nDraft = (int)q_val("SELECT COUNT(*) FROM alokasi a WHERE a.id_musim = ? AND a.status='draft'" . $fd, [$idm]);
$nOverdue = (int)q_val("SELECT COUNT(*) FROM realisasi r JOIN alokasi a ON a.id_alokasi=r.id_alokasi
                        WHERE a.id_musim = ? AND r.status='dikirim' AND r.batas_konfirmasi < ?" . $fd, [$idm, $today]);
$nMenunggu = (int)q_val("SELECT COUNT(*) FROM realisasi r JOIN alokasi a ON a.id_alokasi=r.id_alokasi
                         WHERE a.id_musim = ? AND r.status='dikirim'" . $fd, [$idm]);
$nSelisih = (int)q_val("SELECT COUNT(*) FROM realisasi r JOIN alokasi a ON a.id_alokasi=r.id_alokasi
                        WHERE a.id_musim = ? AND r.status='tidak_sesuai' AND r.status_tinjauan='dalam_pemeriksaan'" . $fd, [$idm]);

$recent = q_all("SELECT r.*, d.nama_desa, p.nama_pupuk FROM realisasi r
                 JOIN alokasi a ON a.id_alokasi=r.id_alokasi JOIN desa d ON d.id_desa=a.id_desa JOIN pupuk p ON p.id_pupuk=a.id_pupuk
                 WHERE a.id_musim = ? AND r.status <> 'dibatalkan'" . $fd . " ORDER BY r.created_at DESC, r.id_realisasi DESC LIMIT 5", [$idm]);

layout_start('Dashboard', 'Home / Dashboard');
?>
<?php if ($musim): ?>
<p class="muted">Musim tanam aktif: <strong><?= e($musim['nama_musim']) ?></strong><?= $isK ? ' &middot; Desa ' . e($u['nama_desa'] ?? '-') : '' ?></p>
<?php else: ?>
<div class="alert alert-warning">Belum ada musim tanam aktif. <?= is_role('admin') ? '<a href="' . e(url('musim')) . '">Atur musim tanam</a>.' : 'Hubungi admin.' ?></div>
<?php endif; ?>

<div class="stats">
  <div class="stat s-green"><div class="stat-icon"><i class="bi bi-box-seam"></i></div><div><span>Total Alokasi (ditetapkan)</span><strong><?= fmt($totAlokasi) ?> kg</strong></div></div>
  <div class="stat s-blue"><div class="stat-icon"><i class="bi bi-truck"></i></div><div><span>Total Dikirim</span><strong><?= fmt($totDikirim) ?> kg</strong></div></div>
  <div class="stat s-green2"><div class="stat-icon"><i class="bi bi-patch-check"></i></div><div><span>Total Diterima (terkonfirmasi)</span><strong><?= fmt($totDiterima) ?> kg</strong></div></div>
  <div class="stat s-orange"><div class="stat-icon"><i class="bi bi-percent"></i></div><div class="flex-grow-1"><span>Persentase Penyaluran</span><strong><?= fmt($persen, 1) ?>%</strong>
    <div class="progress mt-1" style="height:6px"><div class="progress-bar" style="width:<?= (float)$persen ?>%"></div></div></div></div>
</div>

<?php if ($isK && $nMenunggu > 0): ?>
<div class="alert alert-info"><i class="bi bi-box-seam me-1"></i>Ada <strong><?= $nMenunggu ?></strong> kiriman pupuk menunggu konfirmasi Anda.
  <a href="<?= e(url('penerimaan')) ?>">Konfirmasi sekarang</a></div>
<?php endif; ?>
<?php if (!$isK): ?>
  <?php if ($nDraft > 0): ?><div class="alert alert-warning"><i class="bi bi-pencil me-1"></i><strong><?= $nDraft ?></strong> alokasi masih berstatus draft dan belum ditetapkan. <a href="<?= e(url('alokasi')) ?>">Buka perhitungan alokasi</a></div><?php endif; ?>
  <?php if ($nOverdue > 0): ?><div class="alert alert-danger"><i class="bi bi-alarm me-1"></i><strong><?= $nOverdue ?></strong> pengiriman belum dikonfirmasi kepala desa melewati batas <?= KONFIRMASI_HARI ?> hari. <a href="<?= e(url('realisasi')) ?>">Lihat realisasi</a></div><?php endif; ?>
  <?php if ($nSelisih > 0): ?><div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-1"></i><strong><?= $nSelisih ?></strong> penerimaan tidak sesuai menunggu tinjauan. <a href="<?= e(url('realisasi')) ?>">Tinjau sekarang</a></div><?php endif; ?>
<?php endif; ?>

<div class="card">
  <h3><i class="bi bi-card-list me-1"></i>5 Realisasi Terbaru</h3>
  <div class="table-wrap">
  <table class="table align-middle">
    <thead><tr><th>Tgl Kirim</th><th>Desa</th><th>Pupuk</th><th class="num">Dikirim</th><th class="num">Diterima</th><th>Status</th></tr></thead>
    <tbody>
    <?php if (!$recent): ?><tr><td colspan="6" class="center muted">Belum ada data realisasi.</td></tr><?php endif; ?>
    <?php foreach ($recent as $r): ?>
      <tr>
        <td><?= e(tgl($r['tanggal_kirim'])) ?></td>
        <td><?= e($r['nama_desa']) ?></td>
        <td><?= e($r['nama_pupuk']) ?></td>
        <td class="num"><?= fmt($r['jumlah_dikirim']) ?> kg</td>
        <td class="num"><?= $r['jumlah_diterima'] !== null ? fmt($r['jumlah_diterima']) . ' kg' : '-' ?></td>
        <td><?= realisasi_badge($r) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>
<?php layout_end();
