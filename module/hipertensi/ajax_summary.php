<?php
ini_set("display_errors", 0); error_reporting(0);
/**
 * AJAX endpoint: ringkasan metrik hipertensi
 * Mengembalikan JSON: scalar metrics, kaskade, gender/usia, skrining, komorbid
 */
require_once(__DIR__ . '/ajax_helpers.php');

$_ck = pcache_key('ht_sum', [
    'kab' => $filter_kab, 'kec' => $filter_kec, 'kel' => $filter_kel,
    'kode' => $filter_kode, 'tipe' => $filter_tipe,
    'bulan' => $bulan_aktif, 'thn' => $tahun_aktif, 'v' => 4,
]);
$_cached = pcache_get($_ck);
if ($_cached) {
    session_write_close();
    echo json_encode($_cached);
    exit;
}
// Cache miss: lepas session lock sebelum query berat agar request lain tidak terblokir
session_write_close();

// ── Nama wilayah ─────────────────────────────────────────────────────────────
if ($filter_kode) {
    $row_f = fetch_array(bukaquery("SELECT nama_faskes FROM faskes WHERE kode = '" . real_escape($filter_kode) . "' LIMIT 1"));
    $nama_wilayah = $row_f ? $row_f['nama_faskes'] : 'DKI Jakarta';
} elseif ($filter_kel) {
    $row_w = fetch_array(bukaquery("SELECT nama_kelurahan FROM kelurahan WHERE kode_kelurahan = '" . real_escape($filter_kel) . "' LIMIT 1"));
    $nama_wilayah = $row_w ? $row_w['nama_kelurahan'] : 'DKI Jakarta';
} elseif ($filter_kec) {
    $row_w = fetch_array(bukaquery("SELECT nama_kecamatan FROM kecamatan WHERE kode_kecamatan = '" . real_escape($filter_kec) . "' LIMIT 1"));
    $nama_wilayah = $row_w ? $row_w['nama_kecamatan'] : 'DKI Jakarta';
} elseif ($filter_kab) {
    $row_w = fetch_array(bukaquery("SELECT nama_kabupaten FROM kabupaten WHERE kode_kabupaten = '" . real_escape($filter_kab) . "' LIMIT 1"));
    $nama_wilayah = $row_w ? $row_w['nama_kabupaten'] : 'DKI Jakarta';
} else {
    $nama_wilayah = 'DKI Jakarta';
}

// ── Main metrics: pasien dalam perawatan (3-bulan window), exclude newly registered ──
// Per DO: treatment outcomes denominator = patients with visit in 3mo AND first registered before 3mo ago
$row_main = fetch_array(bukaquery(
    "SELECT COUNT(*) AS total_pasien,
            SUM(CASE WHEN CAST(h.sistole  AS UNSIGNED) < 140
                      AND CAST(h.diastole AS UNSIGNED) < 90
                     THEN 1 ELSE 0 END) AS bp_terkontrol,
            SUM(CASE WHEN NOT(CAST(h.sistole  AS UNSIGNED) < 140
                          AND CAST(h.diastole AS UNSIGNED) < 90)
                     THEN 1 ELSE 0 END) AS bp_tidak,
            SUM(c.jk='P') AS gender_perempuan,
            SUM(c.jk='L') AS gender_laki,
            SUM(CASE WHEN CAST(c.umur AS UNSIGNED) BETWEEN 18 AND 29 THEN 1 ELSE 0 END) AS usia_18_29,
            SUM(CASE WHEN CAST(c.umur AS UNSIGNED) BETWEEN 30 AND 49 THEN 1 ELSE 0 END) AS usia_30_49,
            SUM(CASE WHEN CAST(c.umur AS UNSIGNED) BETWEEN 50 AND 69 THEN 1 ELSE 0 END) AS usia_50_69,
            SUM(CASE WHEN CAST(c.umur AS UNSIGNED) >= 70             THEN 1 ELSE 0 END) AS usia_70_plus,
            SUM(CASE WHEN c.jk='P' AND CAST(c.umur AS UNSIGNED) BETWEEN 18 AND 29 THEN 1 ELSE 0 END) AS usia_18_29_p,
            SUM(CASE WHEN c.jk='P' AND CAST(c.umur AS UNSIGNED) BETWEEN 30 AND 49 THEN 1 ELSE 0 END) AS usia_30_49_p,
            SUM(CASE WHEN c.jk='P' AND CAST(c.umur AS UNSIGNED) BETWEEN 50 AND 69 THEN 1 ELSE 0 END) AS usia_50_69_p,
            SUM(CASE WHEN c.jk='P' AND CAST(c.umur AS UNSIGNED) >= 70             THEN 1 ELSE 0 END) AS usia_70_plus_p
     FROM hipertensi h
     JOIN checkin c ON c.id_checkin = h.idcheckin
     INNER JOIN (
         SELECT c2.norm, c2.kode, MAX(c2.tanggal) AS last_visit
         FROM hipertensi h2 JOIN checkin c2 ON c2.id_checkin = h2.idcheckin
         WHERE c2.kode IN ($in_faskes_kode) AND h2.kd_penyakit LIKE 'I10%'
           AND c2.tanggal <= '$tgl_akhir_bln'
         GROUP BY c2.norm, c2.kode
         HAVING last_visit BETWEEN '$tgl_awal_3bln' AND '$tgl_akhir_bln'
     ) lv ON lv.norm = c.norm AND lv.kode = c.kode AND lv.last_visit = c.tanggal
     INNER JOIN (
         SELECT c3.norm, c3.kode FROM hipertensi h3
         JOIN checkin c3 ON c3.id_checkin = h3.idcheckin
         WHERE c3.kode IN ($in_faskes_kode) AND h3.kd_penyakit LIKE 'I10%'
         GROUP BY c3.norm, c3.kode
         HAVING MIN(c3.tanggal) < '$tgl_awal_3bln'
     ) fv ON fv.norm = c.norm AND fv.kode = c.kode
     WHERE c.kode IN ($in_faskes_kode) AND h.kd_penyakit LIKE 'I10%'"
));

$total_pasien     = (int)($row_main['total_pasien']     ?? 0);
$bp_terkontrol    = (int)($row_main['bp_terkontrol']    ?? 0);
$bp_tidak         = (int)($row_main['bp_tidak']         ?? 0);
$gender_perempuan = (int)($row_main['gender_perempuan'] ?? 0);
$gender_laki      = (int)($row_main['gender_laki']      ?? 0);
$usia_18_29   = (int)($row_main['usia_18_29']    ?? 0);
$usia_30_49   = (int)($row_main['usia_30_49']    ?? 0);
$usia_50_69   = (int)($row_main['usia_50_69']    ?? 0);
$usia_70_plus = (int)($row_main['usia_70_plus']  ?? 0);
$usia_18_29_p = (int)($row_main['usia_18_29_p']  ?? 0);
$usia_30_49_p = (int)($row_main['usia_30_49_p']  ?? 0);
$usia_50_69_p = (int)($row_main['usia_50_69_p']  ?? 0);
$usia_70_p    = (int)($row_main['usia_70_plus_p'] ?? 0);

// ── Kumulatif & LTFU ────────────────────────────────────────────────────────
$r_scalar = fetch_array(bukaquery(
    "SELECT
         COUNT(DISTINCT p.norm) AS terdaftar_alltime,
         COUNT(DISTINCT CASE WHEN p.max_in_12mo IS NOT NULL THEN p.norm END) AS terdaftar_kum,
         COUNT(DISTINCT CASE WHEN p.max_in_12mo IS NOT NULL AND p.min_tgl < '$tgl_awal_3bln' THEN p.norm END) AS terdaftar_den,
         SUM(CASE WHEN p.min_tgl BETWEEN '$tgl_awal_bln' AND '$tgl_akhir_bln' THEN 1 ELSE 0 END) AS terdaftar_baru,
         SUM(CASE WHEN p.max_in_12mo IS NOT NULL AND p.max_in_12mo < '$tgl_awal_3bln' THEN 1 ELSE 0 END) AS ltfu_3bln,
         SUM(CASE WHEN p.max_tgl < '$tgl_awal_12bln' THEN 1 ELSE 0 END) AS ltfu_12bln
     FROM (
         SELECT c.norm, c.kode,
                MIN(c.tanggal) AS min_tgl,
                MAX(c.tanggal) AS max_tgl,
                MAX(CASE WHEN c.tanggal BETWEEN '$tgl_awal_12bln' AND '$tgl_akhir_bln'
                         THEN c.tanggal END) AS max_in_12mo
         FROM hipertensi h JOIN checkin c ON c.id_checkin = h.idcheckin
         WHERE h.kd_penyakit LIKE 'I10%' AND c.kode IN ($in_faskes_kode)
         GROUP BY c.norm, c.kode
     ) p"
));
$terdaftar_alltime   = (int)($r_scalar['terdaftar_alltime'] ?? 0);
$terdaftar_kumulatif = (int)($r_scalar['terdaftar_kum']     ?? 0);
$terdaftar_den       = (int)($r_scalar['terdaftar_den']     ?? 0);
$terdaftar_baru      = (int)($r_scalar['terdaftar_baru']    ?? 0);
$ltfu_3bln           = (int)($r_scalar['ltfu_3bln']         ?? 0);
$ltfu_12bln          = (int)($r_scalar['ltfu_12bln']        ?? 0);

// ── Komorbid ─────────────────────────────────────────────────────────────────
$r_komorbid = fetch_array(bukaquery(
    "SELECT COUNT(DISTINCT c.norm) AS ht_dm,
            SUM(CASE WHEN CAST(h.sistole  AS UNSIGNED) < 140
                      AND CAST(h.diastole AS UNSIGNED) < 90
                     THEN 1 ELSE 0 END) AS komorbid_bp_ok
     FROM hipertensi h
     JOIN checkin c ON c.id_checkin = h.idcheckin
     INNER JOIN (
         SELECT c2.norm, c2.kode, MAX(c2.tanggal) AS last_visit
         FROM hipertensi h2 JOIN checkin c2 ON c2.id_checkin = h2.idcheckin
         WHERE c2.kode IN ($in_faskes_kode) AND h2.kd_penyakit LIKE 'I10%'
           AND c2.tanggal <= '$tgl_akhir_bln'
         GROUP BY c2.norm, c2.kode
         HAVING last_visit BETWEEN '$tgl_awal_3bln' AND '$tgl_akhir_bln'
     ) lv ON lv.norm = c.norm AND lv.kode = c.kode AND lv.last_visit = c.tanggal
     WHERE c.kode IN ($in_faskes_kode) AND h.kd_penyakit LIKE 'I10%'
       AND EXISTS (
           SELECT 1 FROM dm d JOIN checkin cd ON cd.id_checkin = d.idcheckin
           WHERE cd.norm = c.norm
       )"
));
$ht_dm          = (int)($r_komorbid['ht_dm']          ?? 0);
$komorbid_bp_ok = (int)($r_komorbid['komorbid_bp_ok'] ?? 0);

// ── Skrining ─────────────────────────────────────────────────────────────────
$r_skrining = fetch_array(bukaquery(
    "SELECT COUNT(DISTINCT c.norm) AS skrining_bln
     FROM hipertensi h
     JOIN checkin c ON c.id_checkin = h.idcheckin
     WHERE c.kode IN ($in_faskes_kode) AND h.kd_penyakit LIKE 'I10%'
       AND c.tanggal BETWEEN '$tgl_awal_bln' AND '$tgl_akhir_bln'"
));
$skrining_bln = (int)($r_skrining['skrining_bln'] ?? 0);

// ── Kalkulasi persentase ──────────────────────────────────────────────────────
$gender_total        = $gender_perempuan + $gender_laki;
$pct_perempuan       = $gender_total > 0 ? round($gender_perempuan / $gender_total * 100) : 0;
$pct_laki            = $gender_total > 0 ? 100 - $pct_perempuan : 0;

$usia_18_29_l = $usia_18_29   - $usia_18_29_p;
$usia_30_49_l = $usia_30_49   - $usia_30_49_p;
$usia_50_69_l = $usia_50_69   - $usia_50_69_p;
$usia_70_l    = $usia_70_plus - $usia_70_p;

$pct_p_18_29 = $gender_perempuan > 0 ? round($usia_18_29_p / $gender_perempuan * 100) : 0;
$pct_p_30_49 = $gender_perempuan > 0 ? round($usia_30_49_p / $gender_perempuan * 100) : 0;
$pct_p_50_69 = $gender_perempuan > 0 ? round($usia_50_69_p / $gender_perempuan * 100) : 0;
$pct_p_70    = $gender_perempuan > 0 ? round($usia_70_p    / $gender_perempuan * 100) : 0;
$pct_l_18_29 = $gender_laki > 0 ? round($usia_18_29_l / $gender_laki * 100) : 0;
$pct_l_30_49 = $gender_laki > 0 ? round($usia_30_49_l / $gender_laki * 100) : 0;
$pct_l_50_69 = $gender_laki > 0 ? round($usia_50_69_l / $gender_laki * 100) : 0;
$pct_l_70    = $gender_laki > 0 ? round($usia_70_l    / $gender_laki * 100) : 0;
$pct_t_18_29 = $total_pasien > 0 ? round($usia_18_29   / $total_pasien * 100) : 0;
$pct_t_30_49 = $total_pasien > 0 ? round($usia_30_49   / $total_pasien * 100) : 0;
$pct_t_50_69 = $total_pasien > 0 ? round($usia_50_69   / $total_pasien * 100) : 0;
$pct_t_70    = $total_pasien > 0 ? round($usia_70_plus / $total_pasien * 100) : 0;

$max_p = max($pct_p_18_29, $pct_p_30_49, $pct_p_50_69, $pct_p_70, 1);
$max_l = max($pct_l_18_29, $pct_l_30_49, $pct_l_50_69, $pct_l_70, 1);

// Per DO: treatment outcomes dihitung dari pasien dalam perawatan yg sudah terdaftar sebelum 3 bulan lalu
$pct_terkontrol     = $terdaftar_den       > 0 ? round($bp_terkontrol / $terdaftar_den       * 100) : 0;
$pct_tidak          = $terdaftar_den       > 0 ? round($bp_tidak      / $terdaftar_den       * 100) : 0;
$pct_ltfu3          = $terdaftar_den       > 0 ? round($ltfu_3bln     / $terdaftar_den       * 100) : 0;
$pct_ltfu12         = $terdaftar_alltime   > 0 ? round($ltfu_12bln    / $terdaftar_alltime   * 100) : 0;
$pct_perawatan      = $terdaftar_alltime   > 0 ? round($terdaftar_kumulatif / $terdaftar_alltime * 100) : 0;
$pct_terkontrol_pop = $terdaftar_alltime   > 0 ? round($bp_terkontrol / $terdaftar_alltime   * 100) : 0;
$pct_komorbid_bp_ok = $ht_dm > 0              ? round($komorbid_bp_ok / $ht_dm              * 100) : 0;
$pct_skrining       = $total_pasien > 0        ? round($skrining_bln  / $total_pasien        * 100) : 0;

$label_bulan = konversiBulan(str_pad($bulan_aktif, 2, '0', STR_PAD_LEFT)) . '-' . $tahun_aktif;
$bln_3bln_awal   = date('Y-m-01', mktime(0,0,0,$bulan_aktif-2,1,$tahun_aktif));
$label_3bln_dari = konversiBulan(date('m', strtotime($bln_3bln_awal))) . '-' . date('Y', strtotime($bln_3bln_awal));

$result = [
    'nama_wilayah'        => $nama_wilayah,
    'label_bulan'         => $label_bulan,
    'label_3bln'          => $label_3bln_dari . ' – ' . $label_bulan,
    'total_pasien'        => $total_pasien,
    'bp_terkontrol'       => $bp_terkontrol,
    'bp_tidak'            => $bp_tidak,
    'terdaftar_alltime'   => $terdaftar_alltime,
    'terdaftar_kumulatif' => $terdaftar_kumulatif,
    'terdaftar_den'       => $terdaftar_den,
    'terdaftar_baru'      => $terdaftar_baru,
    'ltfu_3bln'           => $ltfu_3bln,
    'ltfu_12bln'          => $ltfu_12bln,
    'ht_dm'               => $ht_dm,
    'komorbid_bp_ok'      => $komorbid_bp_ok,
    'skrining_bln'        => $skrining_bln,
    'pct_terkontrol'      => $pct_terkontrol,
    'pct_tidak'           => $pct_tidak,
    'pct_ltfu3'           => $pct_ltfu3,
    'pct_ltfu12'          => $pct_ltfu12,
    'pct_perawatan'       => $pct_perawatan,
    'pct_terkontrol_pop'  => $pct_terkontrol_pop,
    'pct_komorbid_bp_ok'  => $pct_komorbid_bp_ok,
    'pct_skrining'        => $pct_skrining,
    'pct_perempuan'       => $pct_perempuan,
    'pct_laki'            => $pct_laki,
    'gender_perempuan'    => $gender_perempuan,
    'gender_laki'         => $gender_laki,
    'gender_total'        => $gender_total,
    'usia_18_29_p'        => $usia_18_29_p,   'usia_18_29_l' => $usia_18_29_l,   'pct_p_18_29' => $pct_p_18_29, 'pct_l_18_29' => $pct_l_18_29, 'pct_t_18_29' => $pct_t_18_29,
    'usia_30_49_p'        => $usia_30_49_p,   'usia_30_49_l' => $usia_30_49_l,   'pct_p_30_49' => $pct_p_30_49, 'pct_l_30_49' => $pct_l_30_49, 'pct_t_30_49' => $pct_t_30_49,
    'usia_50_69_p'        => $usia_50_69_p,   'usia_50_69_l' => $usia_50_69_l,   'pct_p_50_69' => $pct_p_50_69, 'pct_l_50_69' => $pct_l_50_69, 'pct_t_50_69' => $pct_t_50_69,
    'usia_70_p'           => $usia_70_p,       'usia_70_l'    => $usia_70_l,       'pct_p_70'    => $pct_p_70,    'pct_l_70'    => $pct_l_70,    'pct_t_70'    => $pct_t_70,
    'max_p'               => $max_p,           'max_l'        => $max_l,
];

session_start(); // buka kembali session untuk menulis cache
pcache_set($_ck, $result);
session_write_close();
echo json_encode($result);
