<?php
// Menyajikan foto bukti dengan pengecekan hak akses (folder uploads/ tidak bisa diakses langsung)
$id = (int)get('id');
$r = q_one('SELECT r.foto_bukti, a.id_desa FROM realisasi r JOIN alokasi a ON a.id_alokasi = r.id_alokasi WHERE r.id_realisasi = ?', [$id]);
if (!$r || !$r['foto_bukti']) { http_response_code(404); exit('Foto tidak ditemukan.'); }
if (is_role('kepala_desa') && (int)user()['id_desa'] !== (int)$r['id_desa']) { http_response_code(403); exit('Akses ditolak.'); }
$path = BASE_PATH . '/uploads/bukti/' . basename($r['foto_bukti']);
if (get('t') === '1') { $th = foto_thumb_path($r['foto_bukti']); if ($th) $path = $th; }   // thumbnail kecil untuk tabel
if (!is_file($path)) { http_response_code(404); exit('File foto hilang dari server.'); }
$mime = (new finfo(FILEINFO_MIME_TYPE))->file($path);
if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) { http_response_code(415); exit; }
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path));
header('Cache-Control: private, max-age=86400');
readfile($path);
exit;
