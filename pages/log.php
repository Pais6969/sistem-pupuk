<?php
$fq = get('q');
// Log hanya mencatat aktivitas setelah login (login/logout tidak dicatat; catatan lama disembunyikan)
$where = "aksi NOT IN ('login','logout')"; $par = [];
if ($fq !== '') { $where .= ' AND (username LIKE ? OR aksi LIKE ? OR entitas LIKE ? OR keterangan LIKE ?)'; $par = array_fill(0, 4, '%' . $fq . '%'); }
$rows = q_all("SELECT * FROM log_aktivitas WHERE $where ORDER BY id_log DESC LIMIT 200", $par);
layout_start('Log Aktivitas', 'Home / Sistem / Log Aktivitas');
?>
<div class="card">
  <div class="card-head"><h3><i class="bi bi-clock-history me-1"></i>Log Aktivitas (200 terakhir)</h3>
    <form method="get" action="<?= e(url('log')) ?>" class="inline-form"><input class="form-control" type="text" name="q" placeholder="Cari user / aksi / keterangan..." value="<?= e($fq) ?>"><button class="btn btn-sm btn-outline-success" type="submit"><i class="bi bi-search me-1"></i>Cari</button></form></div>
  <div class="table-wrap"><table class="table align-middle">
    <thead><tr><th>Waktu</th><th>User</th><th>Aksi</th><th>Data</th><th>Keterangan</th><th>IP</th></tr></thead>
    <tbody>
    <?php if (!$rows): ?><tr><td colspan="6" class="center muted">Belum ada aktivitas.</td></tr><?php endif; ?>
    <?php foreach ($rows as $r): ?>
      <tr><td class="small"><?= e(tgl($r['created_at'], true)) ?></td><td><?= e($r['username'] ?? '-') ?></td><td><?= badge($r['aksi'], 'blue') ?></td>
        <td class="small"><?= e($r['entitas']) ?><?= $r['id_entitas'] ? ' #' . (int)$r['id_entitas'] : '' ?></td><td class="small"><?= e($r['keterangan']) ?></td><td class="small"><?= e($r['ip_address']) ?></td></tr>
    <?php endforeach; ?>
    </tbody></table></div>
</div>
<?php layout_end();
