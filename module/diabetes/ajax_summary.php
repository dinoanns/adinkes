<?php
ini_set("display_errors", 0); error_reporting(0);
require_once(__DIR__ . '/ajax_helpers.php');

$_ck = pcache_key('dm_sum', [
    'kab' => $filter_kab, 'kec' => $filter_kec, 'kel' => $filter_kel,
    'kode' => $filter_kode, 'tipe' => $filter_tipe,
    'bulan' => $bulan_aktif, 'thn' => $tahun_aktif, 'v' => 5,
]);
$_cached = pcache_get($_ck);
if ($_cached) { session_write_close(); echo json_encode($_cached); exit; }
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

// ── Kunjungan terakhir tiap pasien DM dalam 3 bulan, exclude newly registered ──
// Per DO: treatment outcomes denominator = pasien kunjungan 3mo AND terdaftar sebelum 3 bulan lalu
$res_last = bukaquery(
    "SELECT CAST(d.gdp AS DECIMAL(10,2)) AS gdp, CAST(d.hba1c AS DECIMAL(10,2)) AS hba1c,
            d.statin, c.jk, CAST(c.umur AS UNSIGNED) AS umur
     FROM dm d
     JOIN checkin c ON c.id_checkin = d.idcheckin
     JOIN (
         SELECT c2.norm, c2.kode, MAX(c2.tanggal) AS tgl_terakhir
         FROM dm d2 JOIN checkin c2 ON c2.id_checkin = d2.idcheckin
         WHERE c2.kode IN ($in_faskes_kode)
           AND c2.tanggal BETWEEN '$tgl_awal_3bln' AND '$tgl_akhir_bln'
         GROUP BY c2.norm, c2.kode
     ) trk ON trk.norm = c.norm AND trk.kode = c.kode AND trk.tgl_terakhir = c.tanggal
     INNER JOIN (
         SELECT c3.norm, c3.kode FROM dm d3
         JOIN checkin c3 ON c3.id_checkin = d3.idcheckin
         WHERE c3.kode IN ($in_faskes_kode)
         GROUP BY c3.norm, c3.kode
         HAVING MIN(c3.tanggal) < '$tgl_awal_3bln'
     ) fv ON fv.norm = c.norm AND fv.kode = c.kode
     WHERE c.kode IN ($in_faskes_kode)"
);
$dm_ok=$dm_no=$dm_statin=0;
$gender_l=$gender_p=0;
$usia_18_29=$usia_30_49=$usia_50_69=$usia_70_plus=0;
$usia_18_29_p=$usia_30_49_p=$usia_50_69_p=$usia_70_p=0;
while ($lv = fetch_array($res_last)) {
    $gdp=$hba1c=0;
    $gdp   = (float)$lv['gdp'];
    $hba1c = (float)$lv['hba1c'];
    $jk    = $lv['jk'];
    $usia  = (int)($lv['umur'] ?? 0);
    if (($gdp > 0 && $gdp < 126) || ($hba1c > 0 && $hba1c < 7)) $dm_ok++; else $dm_no++;
    if ($lv['statin'] === 'Diresepkan') $dm_statin++;
    if ($jk === 'P') $gender_p++; else $gender_l++;
    if      ($usia >= 18 && $usia <= 29) { $usia_18_29++;  if ($jk==='P') $usia_18_29_p++; }
    elseif  ($usia >= 30 && $usia <= 49) { $usia_30_49++;  if ($jk==='P') $usia_30_49_p++; }
    elseif  ($usia >= 50 && $usia <= 69) { $usia_50_69++;  if ($jk==='P') $usia_50_69_p++; }
    elseif  ($usia >= 70)                { $usia_70_plus++; if ($jk==='P') $usia_70_p++;   }
}
$total_pasien = $dm_ok + $dm_no;
$dm_total     = $total_pasien;

// ── Scalar: kumulatif, baru, LTFU ────────────────────────────────────────────
$r_dm_scalar = fetch_array(bukaquery(
    "SELECT
         COUNT(DISTINCT p.norm) AS terdaftar_alltime,
         COUNT(DISTINCT CASE WHEN p.max_in_12mo IS NOT NULL THEN p.norm END) AS terdaftar_kum,
         COUNT(DISTINCT CASE WHEN p.max_in_12mo IS NOT NULL AND p.min_tgl < '$tgl_awal_3bln' THEN p.norm END) AS terdaftar_den,
         SUM(CASE WHEN p.min_tgl BETWEEN '$tgl_awal_bln' AND '$tgl_akhir_bln' THEN 1 ELSE 0 END) AS terdaftar_baru,
         SUM(CASE WHEN p.max_in_12mo IS NOT NULL AND p.max_in_12mo < '$tgl_awal_3bln' THEN 1 ELSE 0 END) AS ltfu_3bln,
         SUM(CASE WHEN p.max_tgl < '$tgl_awal_12bln' THEN 1 ELSE 0 END) AS ltfu_12bln
     FROM (
         SELECT c.norm, c.kode, MIN(c.tanggal) AS min_tgl, MAX(c.tanggal) AS max_tgl,
                MAX(CASE WHEN c.tanggal BETWEEN '$tgl_awal_12bln' AND '$tgl_akhir_bln' THEN c.tanggal END) AS max_in_12mo
         FROM dm d JOIN checkin c ON c.id_checkin = d.idcheckin
         WHERE c.kode IN ($in_faskes_kode) AND c.tanggal <= '$tgl_akhir_bln'
         GROUP BY c.norm, c.kode
     ) p"
));
$terdaftar_alltime = (int)($r_dm_scalar['terdaftar_alltime'] ?? 0);
$terdaftar_kum     = (int)($r_dm_scalar['terdaftar_kum']     ?? 0);
$terdaftar_den     = (int)($r_dm_scalar['terdaftar_den']     ?? 0);
$terdaftar_baru    = (int)($r_dm_scalar['terdaftar_baru']    ?? 0);
$dm_ltfu3          = (int)($r_dm_scalar['ltfu_3bln']         ?? 0);
$dm_ltfu12         = (int)($r_dm_scalar['ltfu_12bln']        ?? 0);

// ── BP terkontrol untuk pasien DM ────────────────────────────────────────────
$r_dm_bp = fetch_array(bukaquery(
    "SELECT COUNT(*) AS total,
            SUM(CASE WHEN CAST(ht.sistole AS UNSIGNED)<140 AND CAST(ht.diastole AS UNSIGNED)<90 THEN 1 ELSE 0 END) AS bp_ok,
            SUM(CASE WHEN CAST(ht.sistole AS UNSIGNED)<130 AND CAST(ht.diastole AS UNSIGNED)<80 THEN 1 ELSE 0 END) AS bp_ok_130
     FROM (
         SELECT c.norm, c.kode FROM dm d JOIN checkin c ON c.id_checkin = d.idcheckin
         WHERE c.kode IN ($in_faskes_kode) AND c.tanggal BETWEEN '$tgl_awal_3bln' AND '$tgl_akhir_bln'
         GROUP BY c.norm, c.kode
     ) dm_pts
     JOIN (
         SELECT ch.norm, ch.kode, MAX(ch.tanggal) AS last_ht_visit
         FROM hipertensi ht2 JOIN checkin ch ON ch.id_checkin = ht2.idcheckin
         WHERE ch.kode IN ($in_faskes_kode) AND ch.tanggal BETWEEN '$tgl_awal_3bln' AND '$tgl_akhir_bln'
         GROUP BY ch.norm, ch.kode
     ) lv ON lv.norm = dm_pts.norm AND lv.kode = dm_pts.kode
     JOIN checkin cht ON cht.norm = lv.norm AND cht.kode = lv.kode AND cht.tanggal = lv.last_ht_visit
     JOIN hipertensi ht ON ht.idcheckin = cht.id_checkin"
));
$dm_bp_total  = (int)($r_dm_bp['total']     ?? 0);
$dm_bp_ok     = (int)($r_dm_bp['bp_ok']     ?? 0);
$dm_bp_ok_130 = (int)($r_dm_bp['bp_ok_130'] ?? 0);

// ── Komorbid DM+HT ───────────────────────────────────────────────────────────
$r_komorbid_dm = fetch_array(bukaquery(
    "SELECT COUNT(DISTINCT c.norm) AS dm_ht,
            SUM(CASE WHEN (CAST(d.gdp AS DECIMAL)>0 AND CAST(d.gdp AS DECIMAL)<126) OR (CAST(d.hba1c AS DECIMAL)>0 AND CAST(d.hba1c AS DECIMAL)<7) THEN 1 ELSE 0 END) AS dm_ht_ok
     FROM dm d JOIN checkin c ON c.id_checkin = d.idcheckin
     JOIN (
         SELECT c2.norm, c2.kode, MAX(c2.tanggal) AS tgl_terakhir
         FROM dm d2 JOIN checkin c2 ON c2.id_checkin = d2.idcheckin
         WHERE c2.kode IN ($in_faskes_kode) AND c2.tanggal BETWEEN '$tgl_awal_3bln' AND '$tgl_akhir_bln'
         GROUP BY c2.norm, c2.kode
     ) trk ON trk.norm = c.norm AND trk.kode = c.kode AND trk.tgl_terakhir = c.tanggal
     WHERE c.kode IN ($in_faskes_kode)
       AND EXISTS (SELECT 1 FROM hipertensi ht JOIN checkin ch ON ch.id_checkin = ht.idcheckin WHERE ch.norm = c.norm)"
));
$dm_ht        = (int)($r_komorbid_dm['dm_ht']    ?? 0);
$dm_ht_ok     = (int)($r_komorbid_dm['dm_ht_ok'] ?? 0);

// ── Skrining oportunistik (pasien DM dengan GDP atau HbA1c tercatat bulan aktif) ──
$r_dm_skrining = fetch_array(bukaquery(
    "SELECT COUNT(DISTINCT c.norm) AS skrining_bln
     FROM dm d JOIN checkin c ON c.id_checkin = d.idcheckin
     WHERE c.kode IN ($in_faskes_kode)
       AND c.tanggal BETWEEN '$tgl_awal_bln' AND '$tgl_akhir_bln'
       AND (CAST(d.gdp AS DECIMAL) > 0 OR CAST(d.hba1c AS DECIMAL) > 0)"
));
$dm_skrining_bln = (int)($r_dm_skrining['skrining_bln'] ?? 0);

// ── Kalkulasi persentase ──────────────────────────────────────────────────────
$gender_total       = $gender_p + $gender_l;
$pct_perempuan      = $gender_total > 0 ? round($gender_p / $gender_total * 100) : 0;
$pct_laki           = $gender_total > 0 ? 100 - $pct_perempuan : 0;
$usia_18_29_l = $usia_18_29   - $usia_18_29_p;
$usia_30_49_l = $usia_30_49   - $usia_30_49_p;
$usia_50_69_l = $usia_50_69   - $usia_50_69_p;
$usia_70_l    = $usia_70_plus - $usia_70_p;
$pct_p_18_29 = $gender_p > 0 ? round($usia_18_29_p / $gender_p * 100) : 0;
$pct_p_30_49 = $gender_p > 0 ? round($usia_30_49_p / $gender_p * 100) : 0;
$pct_p_50_69 = $gender_p > 0 ? round($usia_50_69_p / $gender_p * 100) : 0;
$pct_p_70    = $gender_p > 0 ? round($usia_70_p    / $gender_p * 100) : 0;
$pct_l_18_29 = $gender_l > 0 ? round($usia_18_29_l / $gender_l * 100) : 0;
$pct_l_30_49 = $gender_l > 0 ? round($usia_30_49_l / $gender_l * 100) : 0;
$pct_l_50_69 = $gender_l > 0 ? round($usia_50_69_l / $gender_l * 100) : 0;
$pct_l_70    = $gender_l > 0 ? round($usia_70_l    / $gender_l * 100) : 0;
$usia_total  = $usia_18_29 + $usia_30_49 + $usia_50_69 + $usia_70_plus;
$pct_t_18_29 = $usia_total > 0 ? round($usia_18_29   / $usia_total * 100) : 0;
$pct_t_30_49 = $usia_total > 0 ? round($usia_30_49   / $usia_total * 100) : 0;
$pct_t_50_69 = $usia_total > 0 ? round($usia_50_69   / $usia_total * 100) : 0;
$pct_t_70    = $usia_total > 0 ? round($usia_70_plus / $usia_total * 100) : 0;
$max_p = max($pct_p_18_29, $pct_p_30_49, $pct_p_50_69, $pct_p_70, 1);
$max_l = max($pct_l_18_29, $pct_l_30_49, $pct_l_50_69, $pct_l_70, 1);

// Per DO: treatment outcomes dihitung dari pasien yg terdaftar sebelum 3 bulan lalu
$pct_dm_ok          = $terdaftar_den     > 0 ? round($dm_ok         / $terdaftar_den     * 100) : 0;
$pct_dm_no          = $terdaftar_den     > 0 ? round($dm_no         / $terdaftar_den     * 100) : 0;
$pct_statin         = $terdaftar_den     > 0 ? round($dm_statin     / $terdaftar_den     * 100) : 0;
$pct_ltfu3          = $terdaftar_den     > 0 ? round($dm_ltfu3      / $terdaftar_den     * 100) : 0;
$pct_ltfu12         = $terdaftar_alltime > 0 ? round($dm_ltfu12     / $terdaftar_alltime * 100) : 0;
$pct_perawatan      = $terdaftar_alltime > 0 ? round($terdaftar_kum / $terdaftar_alltime * 100) : 0;
$pct_terkontrol_pop = $terdaftar_alltime > 0 ? round($dm_ok         / $terdaftar_alltime * 100) : 0;
$pct_skrining       = $total_pasien > 0 ? round($dm_skrining_bln / $total_pasien * 100) : 0;
$pct_dm_bp          = $dm_bp_total > 0 ? round($dm_bp_ok    / $dm_bp_total * 100) : 0;
$pct_dm_bp130       = $dm_bp_total > 0 ? round($dm_bp_ok_130/ $dm_bp_total * 100) : 0;
$pct_dm_ht_ok       = $dm_ht > 0 ? round($dm_ht_ok / $dm_ht * 100) : 0;

$label_bulan     = konversiBulan(str_pad($bulan_aktif, 2, '0', STR_PAD_LEFT)) . '-' . $tahun_aktif;
$bln_3bln_awal   = date('Y-m-01', mktime(0,0,0,$bulan_aktif-2,1,$tahun_aktif));
$label_3bln_dari = konversiBulan(date('m', strtotime($bln_3bln_awal))) . '-' . date('Y', strtotime($bln_3bln_awal));

$result = [
    'nama_wilayah'       => $nama_wilayah,
    'label_bulan'        => $label_bulan,
    'label_3bln'         => $label_3bln_dari . ' – ' . $label_bulan,
    'total_pasien'       => $total_pasien,
    'dm_total'           => $dm_total,
    'dm_ok'              => $dm_ok,
    'dm_no'              => $dm_no,
    'dm_statin'          => $dm_statin,
    'terdaftar_alltime'  => $terdaftar_alltime,
    'terdaftar_kum'      => $terdaftar_kum,
    'terdaftar_den'      => $terdaftar_den,
    'terdaftar_baru'     => $terdaftar_baru,
    'dm_ltfu3'           => $dm_ltfu3,
    'dm_ltfu12'          => $dm_ltfu12,
    'dm_bp_total'        => $dm_bp_total,
    'dm_bp_ok'           => $dm_bp_ok,
    'dm_bp_ok_130'       => $dm_bp_ok_130,
    'dm_ht'              => $dm_ht,
    'dm_ht_ok'           => $dm_ht_ok,
    'dm_skrining_bln'    => $dm_skrining_bln,
    'pct_skrining'       => $pct_skrining,
    'pct_dm_ok'          => $pct_dm_ok,
    'pct_dm_no'          => $pct_dm_no,
    'pct_statin'         => $pct_statin,
    'pct_ltfu3'          => $pct_ltfu3,
    'pct_ltfu12'         => $pct_ltfu12,
    'pct_perawatan'      => $pct_perawatan,
    'pct_terkontrol_pop' => $pct_terkontrol_pop,
    'pct_dm_bp'          => $pct_dm_bp,
    'pct_dm_bp130'       => $pct_dm_bp130,
    'pct_dm_ht_ok'       => $pct_dm_ht_ok,
    'pct_perempuan'      => $pct_perempuan,
    'pct_laki'           => $pct_laki,
    'gender_p'           => $gender_p,
    'gender_l'           => $gender_l,
    'gender_total'       => $gender_total,
    'usia_18_29_p'       => $usia_18_29_p, 'usia_18_29_l' => $usia_18_29_l, 'pct_p_18_29' => $pct_p_18_29, 'pct_l_18_29' => $pct_l_18_29, 'pct_t_18_29' => $pct_t_18_29,
    'usia_30_49_p'       => $usia_30_49_p, 'usia_30_49_l' => $usia_30_49_l, 'pct_p_30_49' => $pct_p_30_49, 'pct_l_30_49' => $pct_l_30_49, 'pct_t_30_49' => $pct_t_30_49,
    'usia_50_69_p'       => $usia_50_69_p, 'usia_50_69_l' => $usia_50_69_l, 'pct_p_50_69' => $pct_p_50_69, 'pct_l_50_69' => $pct_l_50_69, 'pct_t_50_69' => $pct_t_50_69,
    'usia_70_p'          => $usia_70_p,    'usia_70_l'    => $usia_70_l,    'pct_p_70'    => $pct_p_70,    'pct_l_70'    => $pct_l_70,    'pct_t_70'    => $pct_t_70,
    'max_p'              => $max_p,        'max_l'        => $max_l,
];

session_start();
pcache_set($_ck, $result);
session_write_close();
echo json_encode($result);
