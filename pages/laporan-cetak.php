<?php
// Halaman laporan "murni" (tanpa sidebar/topbar) untuk dicetak atau disimpan sebagai PDF
require_once BASE_PATH . '/core/report.php';
$u = user();
$isK = $u['role'] === 'kepala_desa';
[$musim] = musim_terpilih();
if (!$musim) { exit('Belum ada musim tanam.'); }
$idm = (int)$musim['id_musim'];
$d = laporan_data($idm, $isK ? (int)$u['id_desa'] : null);
$kec = q_val('SELECT kecamatan FROM desa ORDER BY id_desa LIMIT 1') ?: 'Kecamatan';
?><!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Laporan Realisasi Penyaluran Pupuk - <?= e($musim['nama_musim']) ?></title>
<style>
*{box-sizing:border-box}
body{margin:0;background:#e5e7eb;font-family:"Times New Roman",Times,serif;color:#000;font-size:12px;line-height:1.45}
.screen-bar{position:sticky;top:0;background:#1b5e20;color:#fff;padding:10px 16px;display:flex;gap:10px;align-items:center;font-family:Arial,sans-serif;font-size:13px;z-index:5}
.screen-bar span{flex:1}
.screen-bar button{border:0;border-radius:6px;padding:8px 14px;font-weight:600;cursor:pointer;font-size:13px}
.b-print{background:#ffd54f;color:#1b3a00}.b-close{background:#fff;color:#1b5e20}
.sheet{width:210mm;min-height:297mm;margin:16px auto;background:#fff;padding:16mm 16mm 18mm;box-shadow:0 2px 12px rgba(0,0,0,.2)}
.kop{text-align:center;border-bottom:3px double #000;padding-bottom:8px;margin-bottom:12px}
.kop .i{font-size:15px;font-weight:700;text-transform:uppercase}
.kop .k{font-size:12px}
.judul{text-align:center;margin:10px 0 4px;font-size:15px;font-weight:700;text-decoration:underline}
.sub{text-align:center;margin-bottom:12px}
.info{margin:0 0 10px;border-collapse:collapse}.info td{padding:1px 8px 1px 0;vertical-align:top}
h3{font-size:12.5px;margin:14px 0 5px}
.catatan{font-size:11px;margin:0 0 8px}
table.report-table{width:100%;border-collapse:collapse;margin-bottom:6px;font-size:11.5px}
table.report-table th,table.report-table td{border:1px solid #000;padding:4px 6px;vertical-align:top}
table.report-table thead th{background:#e8f5e9;text-align:center;font-weight:700}
.num{text-align:right;white-space:nowrap}.center{text-align:center}.muted{color:#555}.small{font-size:10.5px}.text-red{color:#b71c1c}
td.cell-group{font-weight:700;vertical-align:middle;background:#f6faf3}
tr.total-row td{font-weight:700;background:#e8f5e9}
.ttd{margin-top:26px;display:flex;justify-content:space-between;page-break-inside:avoid}
.ttd div{width:44%;text-align:center}
.ttd .sp{height:62px}
@page{size:A4;margin:0}
@media print{
  body{background:#fff}.screen-bar{display:none}
  .sheet{margin:0;box-shadow:none;width:auto;min-height:0;padding:15mm 16mm}
  thead th,td.cell-group,tr.total-row td{-webkit-print-color-adjust:exact;print-color-adjust:exact}
  tr{page-break-inside:avoid}
}
</style>
</head>
<body>
<div class="screen-bar">
  <span>Pratinjau laporan &mdash; klik <strong>Cetak / Simpan PDF</strong>, lalu pilih tujuan "Simpan sebagai PDF".</span>
  <button class="b-print" type="button" onclick="window.print()">Cetak / Simpan PDF</button>
  <button class="b-close" type="button" onclick="window.close()">Tutup</button>
</div>
<div class="sheet">
  <div class="kop">
    <div class="i"><?= e(APP_INSTANSI) ?></div>
    <div class="k"><?= e($kec) ?></div>
  </div>
  <div class="judul">LAPORAN REALISASI PENYALURAN PUPUK</div>
  <div class="sub"><?= e($musim['nama_musim']) ?></div>
  <table class="info">
    <tr><td>Periode musim</td><td>:</td><td><?= e(tgl($musim['tanggal_mulai'])) ?> s/d <?= e(tgl($musim['tanggal_selesai'])) ?></td></tr>
    <?php if ($isK): ?><tr><td>Desa</td><td>:</td><td><?= e($u['nama_desa'] ?? '') ?></td></tr><?php endif; ?>
    <tr><td>Tanggal cetak</td><td>:</td><td><?= e(tgl(date('Y-m-d H:i:s'), true)) ?></td></tr>
  </table>

  <?php laporan_isi($d, $isK); ?>

  <div class="ttd">
    <div></div>
    <div><?= e($kec) ?>, <?= e(tgl(date('Y-m-d'))) ?><br>Mengetahui,<div class="sp"></div>( ........................................ )</div>
  </div>
</div>
<script>
if (location.search.indexOf('auto=1') > -1) { window.addEventListener('load', function () { setTimeout(function () { window.print(); }, 400); }); }
</script>
</body>
</html>
