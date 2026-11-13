<?php
/** Data laporan realisasi (dipakai halaman web & halaman cetak). */
function laporan_data(int $idm, ?int $idDesa): array {
    $fd = $idDesa ? ' AND a.id_desa = ' . (int)$idDesa : '';
    $rows = q_all("SELECT d.nama_desa, p.nama_pupuk, p.satuan, a.jumlah_alokasi,
                   COALESCE((SELECT SUM(r.jumlah_dikirim) FROM realisasi r WHERE r.id_alokasi = a.id_alokasi AND r.status <> 'dibatalkan'),0) AS dikirim,
                   COALESCE((SELECT SUM(r.jumlah_diterima) FROM realisasi r WHERE r.id_alokasi = a.id_alokasi AND r.status IN ('sesuai','tidak_sesuai')),0) AS diterima
                   FROM alokasi a JOIN desa d ON d.id_desa = a.id_desa JOIN pupuk p ON p.id_pupuk = a.id_pupuk
                   WHERE a.id_musim = ? AND a.status = 'ditetapkan'" . $fd . " ORDER BY d.nama_desa, p.nama_pupuk", [$idm]);
    $d = ['rows' => $rows];
    $d['nDraft'] = (int)q_val("SELECT COUNT(*) FROM alokasi a WHERE a.id_musim = ? AND a.status = 'draft'" . $fd, [$idm]);
    $d['nMenunggu'] = (int)q_val("SELECT COUNT(*) FROM realisasi r JOIN alokasi a ON a.id_alokasi=r.id_alokasi WHERE a.id_musim = ? AND r.status='dikirim'" . $fd, [$idm]);
    $d['masalah'] = q_all("SELECT r.*, d.nama_desa, p.nama_pupuk FROM realisasi r JOIN alokasi a ON a.id_alokasi=r.id_alokasi
                           JOIN desa d ON d.id_desa=a.id_desa JOIN pupuk p ON p.id_pupuk=a.id_pupuk
                           WHERE a.id_musim = ? AND r.status = 'tidak_sesuai'" . $fd . " ORDER BY d.nama_desa, r.tanggal_kirim", [$idm]);
    $tA = $tK = $tT = 0; $pp = [];
    foreach ($rows as $r) {
        $tA += $r['jumlah_alokasi']; $tK += $r['dikirim']; $tT += $r['diterima'];
        $pp[$r['nama_pupuk']]['a'] = ($pp[$r['nama_pupuk']]['a'] ?? 0) + $r['jumlah_alokasi'];
        $pp[$r['nama_pupuk']]['k'] = ($pp[$r['nama_pupuk']]['k'] ?? 0) + $r['dikirim'];
        $pp[$r['nama_pupuk']]['t'] = ($pp[$r['nama_pupuk']]['t'] ?? 0) + $r['diterima'];
    }
    ksort($pp);
    $d['tA'] = $tA; $d['tK'] = $tK; $d['tT'] = $tT; $d['perPupuk'] = $pp;
    return $d;
}

function laporan_pct($t, $a): string { return $a > 0 ? fmt($t / $a * 100, 1) . '%' : '0%'; }

/** Isi laporan: catatan + tabel A/B/C. */
function laporan_isi(array $d, bool $isK): void {
    $rows = $d['rows'];
    ?>
  <p class="catatan">Realisasi dihitung dari jumlah yang <strong>dikonfirmasi diterima oleh kepala desa</strong>, bukan dari jumlah yang dikirim.
    <?php if ($d['nDraft']): ?>Alokasi berstatus draft (<?= (int)$d['nDraft'] ?>) tidak dimasukkan. <?php endif; ?>
    <?php if ($d['nMenunggu']): ?>Terdapat <?= (int)$d['nMenunggu'] ?> pengiriman yang masih menunggu konfirmasi.<?php endif; ?></p>

  <h3>A. Rekapitulasi per Desa</h3>
  <div class="table-responsive"><table class="table table-bordered align-middle report-table">
    <thead><tr><th>Desa</th><th>Jenis Pupuk</th><th class="num">Alokasi (kg)</th><th class="num">Dikirim (kg)</th><th class="num">Diterima (kg)</th><th class="num">Selisih (kg)</th><th class="num">Persentase</th></tr></thead>
    <tbody>
    <?php if (!$rows): ?><tr><td colspan="7" class="center muted">Belum ada alokasi yang ditetapkan.</td></tr><?php endif; ?>
    <?php foreach (group_rows($rows, 'nama_desa') as $desa => $items): foreach ($items as $i => $r): ?>
      <tr class="<?= $i === 0 ? 'grp-first' : '' ?>">
        <?php if ($i === 0): ?><td rowspan="<?= count($items) ?>" class="cell-group"><?= e($desa) ?></td><?php endif; ?>
        <td><?= e($r['nama_pupuk']) ?></td>
        <td class="num"><?= fmt($r['jumlah_alokasi']) ?></td><td class="num"><?= fmt($r['dikirim']) ?></td><td class="num"><?= fmt($r['diterima']) ?></td>
        <td class="num"><?= fmt($r['jumlah_alokasi'] - $r['diterima']) ?></td><td class="num"><?= laporan_pct($r['diterima'], $r['jumlah_alokasi']) ?></td>
      </tr>
    <?php endforeach; endforeach; ?>
    <?php if ($rows): ?><tr class="total-row"><td colspan="2">TOTAL</td><td class="num"><?= fmt($d['tA']) ?></td><td class="num"><?= fmt($d['tK']) ?></td><td class="num"><?= fmt($d['tT']) ?></td><td class="num"><?= fmt($d['tA'] - $d['tT']) ?></td><td class="num"><?= laporan_pct($d['tT'], $d['tA']) ?></td></tr><?php endif; ?>
    </tbody></table></div>

  <?php if ($d['perPupuk'] && !$isK): ?>
  <h3>B. Rekapitulasi per Jenis Pupuk</h3>
  <div class="table-responsive"><table class="table table-bordered align-middle report-table">
    <thead><tr><th>Jenis Pupuk</th><th class="num">Alokasi (kg)</th><th class="num">Dikirim (kg)</th><th class="num">Diterima (kg)</th><th class="num">Persentase</th></tr></thead>
    <tbody>
    <?php foreach ($d['perPupuk'] as $nama => $v): ?>
      <tr><td><?= e($nama) ?></td><td class="num"><?= fmt($v['a']) ?></td><td class="num"><?= fmt($v['k']) ?></td><td class="num"><?= fmt($v['t']) ?></td><td class="num"><?= laporan_pct($v['t'], $v['a']) ?></td></tr>
    <?php endforeach; ?>
    </tbody></table></div>
  <?php endif; ?>

  <h3><?= $isK ? 'B' : 'C' ?>. Penerimaan Tidak Sesuai (Selisih Pengiriman)</h3>
  <div class="table-responsive"><table class="table table-bordered align-middle report-table">
    <thead><tr><th>Desa</th><th>Tgl Kirim</th><th>Pupuk</th><th class="num">Dikirim</th><th class="num">Diterima</th><th class="num">Selisih</th><th>Catatan Kepala Desa</th><th>Tinjauan</th></tr></thead>
    <tbody>
    <?php if (!$d['masalah']): ?><tr><td colspan="8" class="center muted">Tidak ada selisih penerimaan.</td></tr><?php endif; ?>
    <?php foreach ($d['masalah'] as $r): ?>
      <tr><td><?= e($r['nama_desa']) ?></td><td><?= e(tgl($r['tanggal_kirim'])) ?></td><td><?= e($r['nama_pupuk']) ?></td>
        <td class="num"><?= fmt($r['jumlah_dikirim']) ?></td><td class="num"><?= fmt($r['jumlah_diterima']) ?></td><td class="num text-red"><?= fmt($r['jumlah_dikirim'] - $r['jumlah_diterima']) ?></td>
        <td class="small"><?= e($r['catatan_kades']) ?></td>
        <td class="small"><?= $r['status_tinjauan'] === 'selesai' ? 'Selesai' : 'Dalam pemeriksaan' ?><?= $r['tanggapan'] ? ': ' . e($r['tanggapan']) : '' ?></td></tr>
    <?php endforeach; ?>
    </tbody></table></div>
<?php
}
