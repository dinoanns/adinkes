<?php
require_once(__DIR__ . '/ajax_helpers.php');

$_ck = pcache_key('dm_tbl', [
    'kab' => $filter_kab, 'kec' => $filter_kec, 'kel' => $filter_kel,
    'kode' => $filter_kode, 'tipe' => $filter_tipe,
    'bulan' => $bulan_aktif, 'thn' => $tahun_aktif, 'v' => 2,
]);
$_cached = pcache_get($_ck);
if ($_cached) { session_write_close(); echo json_encode($_cached); exit; }
session_write_close();

$sub_base_join =
    "FROM faskes f
     JOIN kabupaten k ON k.kode_kabupaten = f.kode_kabupaten
     JOIN checkin c ON c.kode = f.kode
     JOIN dm d ON d.idcheckin = c.id_checkin
     JOIN (
         SELECT c2.norm, c2.kode, MAX(c2.tanggal) AS tgl_terakhir
         FROM dm d2 JOIN checkin c2 ON c2.id_checkin = d2.idcheckin
         WHERE c2.kode IN ($in_faskes_kode)
           AND c2.tanggal BETWEEN '$tgl_awal_3bln' AND '$tgl_akhir_bln'
         GROUP BY c2.norm, c2.kode
     ) trk ON trk.norm = c.norm AND trk.kode = c.kode AND trk.tgl_terakhir = c.tanggal
     WHERE f.kode IN ($in_faskes_kode)";

$sub_select =
    "SELECT k.nama_kabupaten AS nama_wilayah, f.kode AS fkode, f.nama_faskes,
            COUNT(DISTINCT c.norm) AS dalam_pwr,
            COUNT(DISTINCT CASE WHEN (CAST(d.gdp AS DECIMAL)>0 AND CAST(d.gdp AS DECIMAL)<126) OR (CAST(d.hba1c AS DECIMAL)>0 AND CAST(d.hba1c AS DECIMAL)<7) THEN c.norm END) AS dm_ok,
            COUNT(DISTINCT CASE WHEN NOT((CAST(d.gdp AS DECIMAL)>0 AND CAST(d.gdp AS DECIMAL)<126) OR (CAST(d.hba1c AS DECIMAL)>0 AND CAST(d.hba1c AS DECIMAL)<7))
                                     AND NOT(CAST(d.gdp AS DECIMAL)>=200 OR CAST(d.hba1c AS DECIMAL)>=9) THEN c.norm END) AS dm_sedang,
            COUNT(DISTINCT CASE WHEN CAST(d.gdp AS DECIMAL)>=200 OR CAST(d.hba1c AS DECIMAL)>=9 THEN c.norm END) AS dm_berat,
            COUNT(DISTINCT CASE WHEN d.statin = 'Diresepkan' THEN c.norm END) AS dm_statin ";

// LTFU 3 bulan per faskes & kabupaten
$ltfu3_by_kode = []; $ltfu3_by_kab = [];
$res_ltfu3_sub = bukaquery(
    "SELECT f.kode_kabupaten, k.nama_kabupaten, c.kode, COUNT(DISTINCT c.norm) AS n
     FROM dm d JOIN checkin c ON c.id_checkin = d.idcheckin
     JOIN faskes f ON f.kode = c.kode
     JOIN kabupaten k ON k.kode_kabupaten = f.kode_kabupaten
     WHERE c.kode IN ($in_faskes_kode)
       AND c.tanggal BETWEEN '$tgl_awal_12bln' AND '$tgl_akhir_bln'
       AND NOT EXISTS (
           SELECT 1 FROM dm dx JOIN checkin cx ON cx.id_checkin = dx.idcheckin
           WHERE cx.norm = c.norm AND cx.kode = c.kode
             AND cx.tanggal BETWEEN '$tgl_awal_3bln' AND '$tgl_akhir_bln'
       )
     GROUP BY f.kode_kabupaten, k.nama_kabupaten, c.kode"
);
while ($rl = fetch_array($res_ltfu3_sub)) {
    $ltfu3_by_kode[$rl['kode']] = (int)$rl['n'];
    $ltfu3_by_kab[$rl['nama_kabupaten']] = ($ltfu3_by_kab[$rl['nama_kabupaten']] ?? 0) + (int)$rl['n'];
}

// Terdaftar baru per faskes & kabupaten
$baru_by_kode = []; $baru_by_kab = [];
$res_baru_sub = bukaquery(
    "SELECT f.kode_kabupaten, k.nama_kabupaten, c.kode, COUNT(*) AS baru
     FROM (
         SELECT c2.norm, c2.kode FROM dm d2 JOIN checkin c2 ON c2.id_checkin = d2.idcheckin
         WHERE c2.kode IN ($in_faskes_kode)
         GROUP BY c2.norm, c2.kode
         HAVING MIN(c2.tanggal) BETWEEN '$tgl_awal_bln' AND '$tgl_akhir_bln'
     ) nb
     JOIN faskes f ON f.kode = nb.kode
     JOIN kabupaten k ON k.kode_kabupaten = f.kode_kabupaten
     JOIN checkin c ON c.norm = nb.norm AND c.kode = nb.kode
     GROUP BY f.kode_kabupaten, k.nama_kabupaten, c.kode"
);
while ($rb = fetch_array($res_baru_sub)) {
    $baru_by_kode[$rb['kode']] = (int)$rb['baru'];
    $baru_by_kab[$rb['nama_kabupaten']] = ($baru_by_kab[$rb['nama_kabupaten']] ?? 0) + (int)$rb['baru'];
}

// Per kabupaten
$res_sub = bukaquery($sub_select . $sub_base_join . " GROUP BY k.kode_kabupaten, k.nama_kabupaten ORDER BY k.nama_kabupaten");
$data_sub = [];
while ($rs = fetch_array($res_sub)) {
    $rs['dm_ltfu3']       = $ltfu3_by_kab[$rs['nama_wilayah']] ?? 0;
    $rs['terdaftar_baru'] = $baru_by_kab[$rs['nama_wilayah']]  ?? 0;
    $data_sub[] = $rs;
}

// Per faskes
$res_sub_rsud = bukaquery($sub_select . $sub_base_join . " GROUP BY f.kode, k.nama_kabupaten, f.nama_faskes ORDER BY k.nama_kabupaten, f.nama_faskes");
$data_sub_rsud = [];
while ($rr = fetch_array($res_sub_rsud)) {
    $rr['dm_ltfu3']       = $ltfu3_by_kode[$rr['fkode']] ?? 0;
    $rr['terdaftar_baru'] = $baru_by_kode[$rr['fkode']]  ?? 0;
    $data_sub_rsud[$rr['nama_wilayah']][] = $rr;
}

// ── Gabungan: per faskes (PKM+PST+RSUD) dengan relasi PST→PKM ────────────────
$data_gabungan = null;
if ($filter_tipe === 'gabungan' || (!$filter_tipe && !$filter_kode)) {
    // Query A gabungan: outcome DM per faskes (kunjungan 3 bulan)
    $_gab_a = [];
    $_res_ga = bukaquery(
        "SELECT f.kode, f.nama_faskes, f.kode_master, k.nama_kabupaten,
                pkm.kode AS kode_induk, pkm.nama_faskes AS nama_induk,
                COUNT(*) AS total_pasien,
                SUM(CASE WHEN (CAST(d.gdp AS DECIMAL)>0 AND CAST(d.gdp AS DECIMAL)<126) OR (CAST(d.hba1c AS DECIMAL)>0 AND CAST(d.hba1c AS DECIMAL)<7) THEN 1 ELSE 0 END) AS dm_ok,
                SUM(CASE WHEN (CAST(d.gdp AS DECIMAL)>=126 AND CAST(d.gdp AS DECIMAL)<200) OR (CAST(d.hba1c AS DECIMAL)>=7 AND CAST(d.hba1c AS DECIMAL)<9) THEN 1 ELSE 0 END) AS dm_sedang,
                SUM(CASE WHEN CAST(d.gdp AS DECIMAL)>=200 OR CAST(d.hba1c AS DECIMAL)>=9 THEN 1 ELSE 0 END) AS dm_berat,
                SUM(CASE WHEN d.statin='Diresepkan' THEN 1 ELSE 0 END) AS dm_statin
         FROM dm d
         JOIN checkin c ON c.id_checkin = d.idcheckin
         INNER JOIN (
             SELECT c2.norm, c2.kode, MAX(c2.tanggal) AS last_visit
             FROM dm d2 JOIN checkin c2 ON c2.id_checkin = d2.idcheckin
             WHERE c2.kode IN ($in_faskes_kode) AND c2.tanggal <= '$tgl_akhir_bln'
             GROUP BY c2.norm, c2.kode
             HAVING last_visit BETWEEN '$tgl_awal_3bln' AND '$tgl_akhir_bln'
         ) lv ON lv.norm = c.norm AND lv.kode = c.kode AND lv.last_visit = c.tanggal
         JOIN faskes f ON f.kode = c.kode
         JOIN kabupaten k ON k.kode_kabupaten = f.kode_kabupaten
         LEFT JOIN faskes pkm ON pkm.kode_master='PKM' AND pkm.kode_kecamatan=f.kode_kecamatan AND pkm.status='1'
         GROUP BY f.kode, f.nama_faskes, f.kode_master, k.nama_kabupaten, pkm.kode, pkm.nama_faskes
         ORDER BY k.nama_kabupaten, f.kode_master DESC, f.nama_faskes"
    );
    while ($_r = fetch_array($_res_ga)) $_gab_a[$_r['kode']] = $_r;

    // Query B gabungan: baru + ltfu3 per faskes
    $_gab_b = [];
    $_res_gb = bukaquery(
        "SELECT f.kode,
                COUNT(DISTINCT p.norm) AS terdaftar_kumulatif,
                SUM(p.is_baru) AS terdaftar_baru,
                SUM(p.is_ltfu3) AS dm_ltfu3
         FROM (
             SELECT c.norm, c.kode,
                    CASE WHEN MIN(c.tanggal) BETWEEN '$tgl_awal_bln' AND '$tgl_akhir_bln' THEN 1 ELSE 0 END AS is_baru,
                    CASE WHEN MAX(c.tanggal) < '$tgl_awal_3bln' THEN 1 ELSE 0 END AS is_ltfu3
             FROM dm d JOIN checkin c ON c.id_checkin = d.idcheckin
             WHERE c.kode IN ($in_faskes_kode)
               AND c.tanggal BETWEEN '$tgl_awal_12bln' AND '$tgl_akhir_bln'
             GROUP BY c.norm, c.kode
         ) p
         JOIN faskes f ON f.kode = p.kode
         GROUP BY f.kode"
    );
    while ($_r = fetch_array($_res_gb)) $_gab_b[$_r['kode']] = $_r;

    // Semua faskes (termasuk yang tidak ada data → tampil 0)
    $_all_faskes = [];
    $_res_af = bukaquery(
        "SELECT f.kode, f.nama_faskes, f.kode_master, k.nama_kabupaten,
                pkm.kode AS kode_induk, pkm.nama_faskes AS nama_induk
         FROM faskes f
         JOIN kabupaten k ON k.kode_kabupaten = f.kode_kabupaten
         LEFT JOIN faskes pkm ON pkm.kode_master='PKM' AND pkm.kode_kecamatan=f.kode_kecamatan AND pkm.status='1'
         WHERE f.kode IN ($in_faskes_kode)
         ORDER BY k.nama_kabupaten, f.kode_master DESC, f.nama_faskes"
    );
    while ($_r = fetch_array($_res_af)) $_all_faskes[(string)$_r['kode']] = $_r;

    $_pkm_map = []; $_rsud_rows = []; $_pst_map = [];
    foreach ($_all_faskes as $_kode => $_fs) {
        $_kode = (string)$_kode;
        $_ra = $_gab_a[$_kode] ?? [];
        $_rb = $_gab_b[$_kode] ?? [];
        $_row = [
            'kode'                => $_kode,
            'nama_wilayah'        => $_fs['nama_faskes'],
            'tipe'                => $_fs['kode_master'],
            'nama_kab'            => $_fs['nama_kabupaten'],
            'total_pasien'        => (int)($_ra['total_pasien']        ?? 0),
            'terdaftar_kumulatif' => (int)($_rb['terdaftar_kumulatif'] ?? 0),
            'terdaftar_baru'      => (int)($_rb['terdaftar_baru']      ?? 0),
            'dm_ok'               => (int)($_ra['dm_ok']               ?? 0),
            'dm_sedang'           => (int)($_ra['dm_sedang']           ?? 0),
            'dm_berat'            => (int)($_ra['dm_berat']            ?? 0),
            'dm_statin'           => (int)($_ra['dm_statin']           ?? 0),
            'dm_ltfu3'            => (int)($_rb['dm_ltfu3']            ?? 0),
        ];
        if ($_fs['kode_master'] === 'RSUD') {
            $_rsud_rows[] = $_row;
        } elseif ($_fs['kode_master'] === 'PKM') {
            $_pkm_map[$_kode] = array_merge($_row, ['pst' => []]);
        } elseif ($_fs['kode_master'] === 'PST' && $_fs['kode_induk']) {
            $_pst_map[$_fs['kode_induk']][] = $_row;
        }
    }
    foreach ($_pst_map as $_kode_pkm => $_pst_list) {
        if (isset($_pkm_map[$_kode_pkm])) $_pkm_map[$_kode_pkm]['pst'] = $_pst_list;
    }
    $data_gabungan = array_merge($_rsud_rows, array_values($_pkm_map));
}

$result = [
    'data_sub'      => $data_sub,
    'data_sub_rsud' => $data_sub_rsud,
    'data_gabungan' => $data_gabungan,
];

session_start();
pcache_set($_ck, $result);
session_write_close();
echo json_encode($result);
