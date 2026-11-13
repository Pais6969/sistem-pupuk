<?php
require_once BASE_PATH . '/core/report.php';
$u = user();
$isK = $u['role'] === 'kepala_desa';
[$musim, $musimList] = musim_terpilih();

layout_start('Laporan Realisasi', 'Home / Laporan');
if (!$musim) { echo '<div class="alert alert-warning">Belum ada musim tanam.</div>'; layout_end(); exit; }
$idm = (int)$musim['id_musim'];
$d = laporan_data($idm, $isK ? (int)$u['id_desa'] : null);
?>
<div class="toolbar">
  <?= musim_selector($musimList, $musim, 'laporan') ?>
  <a class="btn btn-primary" href="<?= e(url('laporan-cetak?auto=1&musim=' . $idm)) ?>" target="_blank" rel="noopener">
    <i class="bi bi-file-earmark-pdf me-1"></i> Cetak / Simpan PDF</a>
</div>
<p class="muted small">Tombol di atas membuka halaman laporan bersih (tanpa menu) di tab baru. Pada dialog cetak, pilih tujuan <strong>"Simpan sebagai PDF"</strong>.</p>

<div class="card report">
  <div class="report-head">
    <h2>LAPORAN REALISASI PENYALURAN PUPUK</h2>
    <div><?= e(APP_INSTANSI) ?></div>
    <div>Musim Tanam: <strong><?= e($musim['nama_musim']) ?></strong> (<?= e(tgl($musim['tanggal_mulai'])) ?> s/d <?= e(tgl($musim['tanggal_selesai'])) ?>)</div>
    <?php if ($isK): ?><div>Desa: <strong><?= e($u['nama_desa'] ?? '') ?></strong></div><?php endif; ?>
  </div>
  <?php laporan_isi($d, $isK); ?>
</div>
<?php layout_end();
