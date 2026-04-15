<?php
ini_set("display_errors", 0); error_reporting(0);
set_time_limit(300);
/**
 * AJAX endpoint: data chart time series + kohort hipertensi
 * Mengembalikan JSON: cht_labels, cht_bp_ok, cht_bp_no, cht_ltfu3, cht_ltfu12,
 *                    cht_terdaftar, cht_dalam_pwr, cht_baru, cht_protected,
 *                    cht_skrining, cht_skrining_pct, data_kohort, label_rentang
 */
require_once(__DIR__ . '/ajax_helpers.php');

$_ck = pcache_key('ht_cht', [
    'kab' => $filter_kab, 'kec' => $filter_kec, 'kel' => $filter_kel,
    'kode' => $filter_kode, 'tipe' => $filter_tipe,
    'bulan' => $bulan_aktif, 'thn' => $tahun_aktif, 'v' => 3,
]);
$_cached = pcache_get($_ck);
if ($_cached) {
    session_write_close();
    echo json_encode($_cached);
    exit;
}
session_write_close();

// ── Fetch semua kunjungan HT untuk agregasi PHP ──────────────────────────────
$_res_ts = bukaquery(
    "SELECT c.norm, TRIM(c.kode) AS kode, c.tanggal,
            CAST(h.sistole  AS UNSIGNED) AS sistole,
            CAST(h.diastole AS UNSIGNED) AS diastole
     FROM hipertensi h
     JOIN checkin c ON c.id_checkin = h.idcheckin
     WHERE h.kd_penyakit LIKE 'I10%' AND c.kode IN ($in_faskes_kode)
       AND c.tanggal <= '$tgl_akhir_bln'
     ORDER BY c.norm, c.kode, c.tanggal"
);

$_pts = [];
while ($_rv = fetch_array($_res_ts)) {
    $_pts[$_rv['norm'] . "\x00" . trim($_rv['kode'])][] = [
        't'   => $_rv['tanggal'],
        'sys' => (int)$_rv['sistole'],
        'dia' => (int)$_rv['diastole'],
    ];
}

$cht_labels = $cht_bp_ok = $cht_bp_no = $cht_ltfu3 = $cht_ltfu12 = [];
$cht_terdaftar = $cht_dalam_pwr = $cht_baru = $cht_protected = $cht_skrining = $cht_skrining_pct = [];

for ($i = 11; $i >= 0; $i--) {
    $_bln  = (int)date('n', mktime(0,0,0,$bulan_aktif - $i, 1, $tahun_aktif));
    $_thn  = (int)date('Y', mktime(0,0,0,$bulan_aktif - $i, 1, $tahun_aktif));
    $_end  = date('Y-m-t',  mktime(0,0,0,$_bln,   1, $_thn));
    $_bln3 = date('Y-m-01', mktime(0,0,0,$_bln-2, 1, $_thn));
    $_12   = date('Y-m-01', mktime(0,0,0,$_bln-11,1, $_thn));
    $_bln1 = date('Y-m-01', mktime(0,0,0,$_bln,   1, $_thn));

    $_tp = $_ok = $_no = $_baru = $_l3 = $_l12 = 0;
    $_ok_den = $_no_den = 0;
    $_norms_12 = []; $_norms_bln = []; $_norms_all = [];

    foreach ($_pts as $_key => $_visits) {
        $_norm  = substr($_key, 0, strpos($_key, "\x00"));
        $_lv    = null;
        $_last_12 = null;
        $_has_bln = false;
        $_first   = $_visits[0]['t'];

        foreach ($_visits as $_v) {
            if ($_v['t'] > $_end) break;
            $_lv = $_v;
            if ($_v['t'] >= $_12)   $_last_12 = $_v;
            if ($_v['t'] >= $_bln1) $_has_bln = true;
        }

        if ($_lv !== null) $_norms_all[$_norm] = true;
        if ($_last_12 !== null) $_norms_12[$_norm] = true;
        if ($_first >= $_bln1 && $_first <= $_end) $_baru++;
        if (!$_lv || $_lv['t'] < $_12) { $_l12++; continue; }
        if ($_has_bln) $_norms_bln[$_norm] = true;
        if ($_last_12['t'] < $_bln3) { $_l3++; continue; }

        // Pasien dalam perawatan (kunjungan dalam 3 bulan)
        $_tp++;
        $_is_ok = ($_last_12['sys'] < 140 && $_last_12['dia'] < 90);
        if ($_is_ok) $_ok++; else $_no++;
        // Hanya masuk denominator treatment outcomes jika terdaftar sebelum 3 bulan lalu
        if ($_first < $_bln3) {
            if ($_is_ok) $_ok_den++; else $_no_den++;
        }
    }
    $_tk  = count($_norms_12);  // patients under care (12mo)
    $_all = count($_norms_all); // all-time cumulative up to this month
    $_skr = count($_norms_bln);
    $_den = $_ok_den + $_no_den + $_l3; // treatment outcomes denominator (excl newly registered)

    $cht_labels[]       = singkatanBulan($_bln) . '-' . $_thn;
    $cht_bp_ok[]        = $_den > 0 ? round($_ok_den / $_den * 100) : 0;
    $cht_bp_no[]        = $_den > 0 ? round($_no_den / $_den * 100) : 0;
    $cht_ltfu3[]        = $_den > 0 ? round($_l3     / $_den * 100) : 0;
    $cht_ltfu12[]       = $_all > 0 ? round($_l12    / $_all * 100) : 0;
    $cht_terdaftar[]    = $_all; // all-time cumulative
    $cht_dalam_pwr[]    = $_tk;  // patients under care (12mo)
    $cht_baru[]         = $_baru;
    $cht_protected[]    = $_ok_den;
    $cht_skrining[]     = $_skr;
    $cht_skrining_pct[] = $_tp > 0 ? round($_skr / $_tp * 100) : 0;
}
unset($_pts, $_res_ts);

// ── Kohort triwulanan ─────────────────────────────────────────────────────────
function ht_quarter_range($q, $y) {
    $sm = ($q - 1) * 3 + 1;
    $em = $q * 3;
    return [
        'start' => sprintf('%04d-%02d-01', $y, $sm),
        'end'   => date('Y-m-t', mktime(0,0,0,$em,1,$y)),
        'label' => "Q$q-$y",
    ];
}
$data_kohort = [];
$_cur_q = (int)ceil($bulan_aktif / 3);
$_cur_y = $tahun_aktif;
for ($qi = 8; $qi >= 2; $qi--) {
    $_mq_abs = $_cur_y * 4 + $_cur_q - $qi;
    $_my = (int)floor(($_mq_abs - 1) / 4);
    $_mq = (($_mq_abs - 1) % 4) + 1;
    $_rq_abs = $_mq_abs - 1;
    $_ry = (int)floor(($_rq_abs - 1) / 4);
    $_rq = (($_rq_abs - 1) % 4) + 1;

    $_reg  = ht_quarter_range($_rq, $_ry);
    $_meas = ht_quarter_range($_mq, $_my);

    $_koh = fetch_array(bukaquery(
        "SELECT COUNT(*) AS total,
                SUM(CASE WHEN mv.meas_visit IS NOT NULL
                          AND CAST(h.sistole  AS UNSIGNED) < 140
                          AND CAST(h.diastole AS UNSIGNED) < 90
                         THEN 1 ELSE 0 END) AS bp_ok,
                SUM(CASE WHEN mv.meas_visit IS NOT NULL
                          AND NOT(CAST(h.sistole  AS UNSIGNED) < 140
                              AND CAST(h.diastole AS UNSIGNED) < 90)
                         THEN 1 ELSE 0 END) AS bp_no,
                SUM(CASE WHEN mv.meas_visit IS NULL THEN 1 ELSE 0 END) AS ltfu3
         FROM (
             SELECT c.norm, c.kode
             FROM hipertensi h2 JOIN checkin c ON c.id_checkin = h2.idcheckin
             WHERE c.kode IN ($in_faskes_kode) AND h2.kd_penyakit LIKE 'I10%'
             GROUP BY c.norm, c.kode
             HAVING MIN(c.tanggal) BETWEEN '{$_reg['start']}' AND '{$_reg['end']}'
         ) np
         LEFT JOIN (
             SELECT c2.norm, c2.kode, MAX(c2.tanggal) AS meas_visit
             FROM hipertensi hm JOIN checkin c2 ON c2.id_checkin = hm.idcheckin
             WHERE c2.kode IN ($in_faskes_kode) AND hm.kd_penyakit LIKE 'I10%'
               AND c2.tanggal BETWEEN '{$_meas['start']}' AND '{$_meas['end']}'
             GROUP BY c2.norm, c2.kode
         ) mv ON mv.norm = np.norm AND mv.kode = np.kode
         LEFT JOIN checkin cm ON cm.norm = mv.norm AND cm.kode = mv.kode AND cm.tanggal = mv.meas_visit
         LEFT JOIN hipertensi h ON h.idcheckin = cm.id_checkin AND h.kd_penyakit LIKE 'I10%'"
    ));
    if (($_koh['total'] ?? 0) > 0) {
        $data_kohort[] = [
            'label' => $_reg['label'],
            'total' => (int)$_koh['total'],
            'bp_ok' => (int)$_koh['bp_ok'],
            'bp_no' => (int)$_koh['bp_no'],
            'ltfu3' => (int)$_koh['ltfu3'],
        ];
    }
}

$label_rentang = (!empty($cht_labels) ? $cht_labels[0] : '') . ' – ' . (!empty($cht_labels) ? $cht_labels[count($cht_labels)-1] : '');

$result = [
    'labels'       => $cht_labels,
    'bp_ok'        => $cht_bp_ok,
    'bp_no'        => $cht_bp_no,
    'ltfu3'        => $cht_ltfu3,
    'ltfu12'       => $cht_ltfu12,
    'terdaftar'    => $cht_terdaftar,
    'dalam_pwr'    => $cht_dalam_pwr,
    'baru'         => $cht_baru,
    'protected'    => $cht_protected,
    'skrining'     => $cht_skrining,
    'skrining_pct' => $cht_skrining_pct,
    'label_rentang'=> $label_rentang,
    'data_kohort'  => $data_kohort,
];

session_start();
pcache_set($_ck, $result);
session_write_close();
echo json_encode($result);
