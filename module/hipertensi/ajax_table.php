<?php
/**
 * AJAX endpoint: data tabel sub-wilayah hipertensi (per kabupaten & per faskes)
 * Mengembalikan JSON: data_sub (array), data_sub_rsud (object keyed by kabupaten)
 */
require_once(__DIR__ . '/ajax_helpers.php');

$_ck = pcache_key('ht_tbl', [
    'kab' => $filter_kab, 'kec' => $filter_kec, 'kel' => $filter_kel,
    'kode' => $filter_kode, 'tipe' => $filter_tipe,
    'bulan' => $bulan_aktif, 'thn' => $tahun_aktif, 'v' => 2,
]);
$_cached = pcache_get($_ck);
if ($_cached) {
    session_write_close();
    echo json_encode($_cached);
    exit;
}
session_write_close();

// ── Sub-wilayah per kabupaten: query A (total+BP) ────────────────────────────
$_sub_a = [];
$_res_sa = bukaquery(
    "SELECT k.nama_kabupaten, COUNT(*) AS total_pasien,
            SUM(CASE WHEN CAST(h.sistole  AS UNSIGNED) < 140
                      AND CAST(h.diastole AS UNSIGNED) < 90
                     THEN 1 ELSE 0 END) AS bp_terkontrol,
            SUM(CASE WHEN NOT(CAST(h.sistole  AS UNSIGNED) < 140
                          AND CAST(h.diastole AS UNSIGNED) < 90)
                     THEN 1 ELSE 0 END) AS bp_tidak
     FROM hipertensi h
     JOIN checkin c ON c.id_checkin = h.idcheckin
     INNER JOIN (
         SELECT c2.norm, c2.kode, MAX(c2.tanggal) AS last_visit
         FROM hipertensi h2 JOIN checkin c2 ON c2.id_checkin = h2.idcheckin
         WHERE h2.kd_penyakit LIKE 'I10%' AND c2.tanggal <= '$tgl_akhir_bln'
         GROUP BY c2.norm, c2.kode
         HAVING last_visit BETWEEN '$tgl_awal_3bln' AND '$tgl_akhir_bln'
     ) lv ON lv.norm = c.norm AND lv.kode = c.kode AND lv.last_visit = c.tanggal
     JOIN faskes f ON f.kode = c.kode
     JOIN kabupaten k ON k.kode_kabupaten = f.kode_kabupaten
     WHERE h.kd_penyakit LIKE 'I10%'
     GROUP BY k.kode_kabupaten, k.nama_kabupaten ORDER BY k.nama_kabupaten"
);
while ($_r = fetch_array($_res_sa)) $_sub_a[$_r['nama_kabupaten']] = $_r;

// ── Sub-wilayah per kabupaten: query B (kumulatif+LTFU) ─────────────────────
$_sub_b = [];
$_res_sb = bukaquery(
    "SELECT k.nama_kabupaten,
            COUNT(DISTINCT p.norm) AS terdaftar_kumulatif,
            SUM(p.is_baru)  AS terdaftar_baru,
            SUM(p.is_ltfu3) AS ltfu_3bulan
     FROM (
         SELECT c.norm, c.kode,
                CASE WHEN MIN(c.tanggal) BETWEEN '$tgl_awal_bln' AND '$tgl_akhir_bln' THEN 1 ELSE 0 END AS is_baru,
                CASE WHEN MAX(c.tanggal) < '$tgl_awal_3bln' THEN 1 ELSE 0 END AS is_ltfu3
         FROM hipertensi h JOIN checkin c ON c.id_checkin = h.idcheckin
         WHERE h.kd_penyakit LIKE 'I10%'
           AND c.tanggal BETWEEN '$tgl_awal_12bln' AND '$tgl_akhir_bln'
         GROUP BY c.norm, c.kode
     ) p
     JOIN faskes f ON f.kode = p.kode
     JOIN kabupaten k ON k.kode_kabupaten = f.kode_kabupaten
     GROUP BY k.kode_kabupaten, k.nama_kabupaten ORDER BY k.nama_kabupaten"
);
while ($_r = fetch_array($_res_sb)) $_sub_b[$_r['nama_kabupaten']] = $_r;

$data_sub = [];
$_all_kab = array_unique(array_merge(array_keys($_sub_a), array_keys($_sub_b)));
sort($_all_kab);
foreach ($_all_kab as $_kab) {
    $data_sub[] = [
        'nama_wilayah'        => $_kab,
        'total_pasien'        => (int)(($_sub_a[$_kab]['total_pasien']        ?? 0)),
        'terdaftar_kumulatif' => (int)(($_sub_b[$_kab]['terdaftar_kumulatif'] ?? 0)),
        'terdaftar_baru'      => (int)(($_sub_b[$_kab]['terdaftar_baru']      ?? 0)),
        'bp_terkontrol'       => (int)(($_sub_a[$_kab]['bp_terkontrol']       ?? 0)),
        'bp_tidak_terkontrol' => (int)(($_sub_a[$_kab]['bp_tidak']            ?? 0)),
        'ltfu_3bulan'         => (int)(($_sub_b[$_kab]['ltfu_3bulan']         ?? 0)),
    ];
}

// ── Per faskes ────────────────────────────────────────────────────────────────
$_faskes_a = [];
$_res_fa = bukaquery(
    "SELECT k.nama_kabupaten, f.nama_faskes AS nama_wilayah, COUNT(*) AS total_pasien,
            SUM(CASE WHEN CAST(h.sistole  AS UNSIGNED) < 140
                      AND CAST(h.diastole AS UNSIGNED) < 90
                     THEN 1 ELSE 0 END) AS bp_terkontrol,
            SUM(CASE WHEN NOT(CAST(h.sistole  AS UNSIGNED) < 140
                          AND CAST(h.diastole AS UNSIGNED) < 90)
                     THEN 1 ELSE 0 END) AS bp_tidak_terkontrol
     FROM hipertensi h
     JOIN checkin c ON c.id_checkin = h.idcheckin
     INNER JOIN (
         SELECT c2.norm, c2.kode, MAX(c2.tanggal) AS last_visit
         FROM hipertensi h2 JOIN checkin c2 ON c2.id_checkin = h2.idcheckin
         WHERE h2.kd_penyakit LIKE 'I10%' AND c2.tanggal <= '$tgl_akhir_bln'
         GROUP BY c2.norm, c2.kode
         HAVING last_visit BETWEEN '$tgl_awal_3bln' AND '$tgl_akhir_bln'
     ) lv ON lv.norm = c.norm AND lv.kode = c.kode AND lv.last_visit = c.tanggal
     JOIN faskes f ON f.kode = c.kode
     JOIN kabupaten k ON k.kode_kabupaten = f.kode_kabupaten
     WHERE h.kd_penyakit LIKE 'I10%'
     GROUP BY f.kode, k.nama_kabupaten, f.nama_faskes ORDER BY k.nama_kabupaten, f.nama_faskes"
);
while ($_r = fetch_array($_res_fa)) $_faskes_a[$_r['nama_kabupaten']][$_r['nama_wilayah']] = $_r;

$_faskes_b = [];
$_res_fb = bukaquery(
    "SELECT k.nama_kabupaten, f.nama_faskes AS nama_wilayah,
            COUNT(DISTINCT p.norm) AS terdaftar_baru,
            SUM(p.is_ltfu3) AS ltfu_3bulan
     FROM (
         SELECT c.norm, c.kode,
                CASE WHEN MIN(c.tanggal) BETWEEN '$tgl_awal_bln' AND '$tgl_akhir_bln' THEN 1 ELSE 0 END AS is_baru,
                CASE WHEN MAX(c.tanggal) < '$tgl_awal_3bln' THEN 1 ELSE 0 END AS is_ltfu3
         FROM hipertensi h JOIN checkin c ON c.id_checkin = h.idcheckin
         WHERE h.kd_penyakit LIKE 'I10%'
           AND c.tanggal BETWEEN '$tgl_awal_12bln' AND '$tgl_akhir_bln'
         GROUP BY c.norm, c.kode
     ) p
     JOIN faskes f ON f.kode = p.kode
     JOIN kabupaten k ON k.kode_kabupaten = f.kode_kabupaten
     GROUP BY f.kode, k.nama_kabupaten, f.nama_faskes ORDER BY k.nama_kabupaten, f.nama_faskes"
);
while ($_r = fetch_array($_res_fb)) $_faskes_b[$_r['nama_kabupaten']][$_r['nama_wilayah']] = $_r;

$data_sub_rsud = [];
foreach ($_faskes_a as $_kab => $_list) {
    foreach ($_list as $_fnama => $_ra) {
        $_rb = $_faskes_b[$_kab][$_fnama] ?? [];
        $data_sub_rsud[$_kab][] = [
            'nama_wilayah'        => $_fnama,
            'total_pasien'        => (int)($_ra['total_pasien']         ?? 0),
            'terdaftar_baru'      => (int)($_rb['terdaftar_baru']       ?? 0),
            'bp_terkontrol'       => (int)($_ra['bp_terkontrol']        ?? 0),
            'bp_tidak_terkontrol' => (int)($_ra['bp_tidak_terkontrol']  ?? 0),
            'ltfu_3bulan'         => (int)($_rb['ltfu_3bulan']          ?? 0),
        ];
    }
}

// ── Gabungan: per faskes (PKM+PST+RSUD) dengan relasi PST→PKM ────────────────
$data_gabungan = null;
if ($filter_tipe === 'gabungan' || (!$filter_tipe && !$filter_kode)) {
    // Query A gabungan: total + BP per faskes
    $_gab_a = [];
    $_res_ga = bukaquery(
        "SELECT f.kode, f.nama_faskes, f.kode_master, k.nama_kabupaten,
                pkm.kode AS kode_induk, pkm.nama_faskes AS nama_induk,
                COUNT(*) AS total_pasien,
                SUM(CASE WHEN CAST(h.sistole AS UNSIGNED)<140 AND CAST(h.diastole AS UNSIGNED)<90 THEN 1 ELSE 0 END) AS bp_terkontrol,
                SUM(CASE WHEN NOT(CAST(h.sistole AS UNSIGNED)<140 AND CAST(h.diastole AS UNSIGNED)<90) THEN 1 ELSE 0 END) AS bp_tidak
         FROM hipertensi h
         JOIN checkin c ON c.id_checkin = h.idcheckin
         INNER JOIN (
             SELECT c2.norm, c2.kode, MAX(c2.tanggal) AS last_visit
             FROM hipertensi h2 JOIN checkin c2 ON c2.id_checkin = h2.idcheckin
             WHERE h2.kd_penyakit LIKE 'I10%' AND c2.kode IN ($in_faskes_kode) AND c2.tanggal <= '$tgl_akhir_bln'
             GROUP BY c2.norm, c2.kode
             HAVING last_visit BETWEEN '$tgl_awal_3bln' AND '$tgl_akhir_bln'
         ) lv ON lv.norm = c.norm AND lv.kode = c.kode AND lv.last_visit = c.tanggal
         JOIN faskes f ON f.kode = c.kode
         JOIN kabupaten k ON k.kode_kabupaten = f.kode_kabupaten
         LEFT JOIN faskes pkm ON pkm.kode_master='PKM' AND pkm.kode_kecamatan=f.kode_kecamatan AND pkm.status='1'
         WHERE h.kd_penyakit LIKE 'I10%'
         GROUP BY f.kode, f.nama_faskes, f.kode_master, k.nama_kabupaten, pkm.kode, pkm.nama_faskes
         ORDER BY k.nama_kabupaten, f.kode_master DESC, f.nama_faskes"
    );
    while ($_r = fetch_array($_res_ga)) $_gab_a[$_r['kode']] = $_r;

    // Query B gabungan: kumulatif + baru + ltfu per faskes
    $_gab_b = [];
    $_res_gb = bukaquery(
        "SELECT f.kode,
                COUNT(DISTINCT p.norm) AS terdaftar_kumulatif,
                SUM(p.is_baru) AS terdaftar_baru,
                SUM(p.is_ltfu3) AS ltfu_3bulan
         FROM (
             SELECT c.norm, c.kode,
                    CASE WHEN MIN(c.tanggal) BETWEEN '$tgl_awal_bln' AND '$tgl_akhir_bln' THEN 1 ELSE 0 END AS is_baru,
                    CASE WHEN MAX(c.tanggal) < '$tgl_awal_3bln' THEN 1 ELSE 0 END AS is_ltfu3
             FROM hipertensi h JOIN checkin c ON c.id_checkin = h.idcheckin
             WHERE h.kd_penyakit LIKE 'I10%' AND c.kode IN ($in_faskes_kode)
               AND c.tanggal BETWEEN '$tgl_awal_12bln' AND '$tgl_akhir_bln'
             GROUP BY c.norm, c.kode
         ) p
         JOIN faskes f ON f.kode = p.kode
         GROUP BY f.kode"
    );
    while ($_r = fetch_array($_res_gb)) $_gab_b[$_r['kode']] = $_r;

    // Ambil semua faskes yang relevan (termasuk yang tidak ada data)
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

    // Susun struktur: RSUD flat + PKM dengan PST children
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
            'total_pasien'        => (int)($_ra['total_pasien']   ?? 0),
            'terdaftar_kumulatif' => (int)($_rb['terdaftar_kumulatif'] ?? 0),
            'terdaftar_baru'      => (int)($_rb['terdaftar_baru'] ?? 0),
            'bp_terkontrol'       => (int)($_ra['bp_terkontrol']  ?? 0),
            'bp_tidak_terkontrol' => (int)($_ra['bp_tidak']       ?? 0),
            'ltfu_3bulan'         => (int)($_rb['ltfu_3bulan']    ?? 0),
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
        if (isset($_pkm_map[$_kode_pkm])) {
            $_pkm_map[$_kode_pkm]['pst'] = $_pst_list;
        }
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
