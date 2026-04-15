<?php
ini_set("display_errors", 0); error_reporting(0);
set_time_limit(300);
require_once(__DIR__ . '/ajax_helpers.php');

$_ck = pcache_key('dm_cht', [
    'kab' => $filter_kab, 'kec' => $filter_kec, 'kel' => $filter_kel,
    'kode' => $filter_kode, 'tipe' => $filter_tipe,
    'bulan' => $bulan_aktif, 'thn' => $tahun_aktif, 'v' => 3,
]);
$_cached = pcache_get($_ck);
if ($_cached) { session_write_close(); echo json_encode($_cached); exit; }
session_write_close();

// ── Fetch semua kunjungan DM ──────────────────────────────────────────────────
$_res_dm_ts = bukaquery(
    "SELECT c.norm, TRIM(c.kode) AS kode, c.tanggal,
            CAST(d.gdp   AS DECIMAL(10,2)) AS gdp,
            CAST(d.hba1c AS DECIMAL(10,2)) AS hba1c,
            d.statin
     FROM dm d JOIN checkin c ON c.id_checkin = d.idcheckin
     WHERE c.kode IN ($in_faskes_kode) AND c.tanggal <= '$tgl_akhir_bln'
     ORDER BY c.norm, c.kode, c.tanggal"
);
$_dm_pts = [];
while ($_dv = fetch_array($_res_dm_ts)) {
    $_dm_pts[$_dv['norm'] . "\x00" . trim($_dv['kode'])][] = [
        't'     => $_dv['tanggal'],
        'gdp'   => (float)$_dv['gdp'],
        'hba1c' => (float)$_dv['hba1c'],
        'sta'   => $_dv['statin'],
    ];
}

// ── Pre-fetch BP per bulan (1 query) ─────────────────────────────────────────
$_bp_by_month = [];
$_res_bp_ts = bukaquery(
    "SELECT YEAR(c.tanggal) AS yr, MONTH(c.tanggal) AS mo,
            COUNT(DISTINCT c.norm) AS tot,
            SUM(CASE WHEN CAST(ht.sistole AS UNSIGNED)<140 AND CAST(ht.diastole AS UNSIGNED)<90 THEN 1 ELSE 0 END) AS ok,
            SUM(CASE WHEN CAST(ht.sistole AS UNSIGNED)<130 AND CAST(ht.diastole AS UNSIGNED)<80 THEN 1 ELSE 0 END) AS ok130
     FROM (
         SELECT c2.norm, c2.kode, YEAR(c2.tanggal) AS yr, MONTH(c2.tanggal) AS mo, MAX(c2.tanggal) AS lv
         FROM hipertensi ht2 JOIN checkin c2 ON c2.id_checkin = ht2.idcheckin
         WHERE c2.kode IN ($in_faskes_kode)
           AND c2.tanggal BETWEEN '$tgl_awal_12bln' AND '$tgl_akhir_bln'
         GROUP BY c2.norm, c2.kode, yr, mo
     ) lv
     JOIN checkin c ON c.norm = lv.norm AND c.kode = lv.kode AND c.tanggal = lv.lv
     JOIN hipertensi ht ON ht.idcheckin = c.id_checkin
     WHERE c.kode IN ($in_faskes_kode)
     GROUP BY yr, mo"
);
while ($_bprow = fetch_array($_res_bp_ts)) {
    $_bp_by_month[$_bprow['yr'] . '-' . str_pad($_bprow['mo'], 2, '0', STR_PAD_LEFT)] = $_bprow;
}

// ── Agregasi 12 bulan di PHP ──────────────────────────────────────────────────
$cht_labels=$cht_dm_ok=$cht_dm_no=$cht_dm_sedang=$cht_dm_berat=$cht_ltfu3=$cht_ltfu12=$cht_statin=[];
$cht_terdaftar=$cht_dalam_pwr=$cht_baru=$cht_skrining=$cht_skrining_pct=[];
$cht_bp_ok=$cht_bp_ok130=[];

// hitung terdaftar_alltime untuk pct ltfu12
$_all_norms = [];
foreach ($_dm_pts as $_key => $_visits) {
    $_norm = substr($_key, 0, strpos($_key, "\x00"));
    $_all_norms[$_norm] = true;
}
$terdaftar_alltime = count($_all_norms);

for ($i = 11; $i >= 0; $i--) {
    $_bln  = (int)date('n', mktime(0,0,0,$bulan_aktif-$i,1,$tahun_aktif));
    $_thn  = (int)date('Y', mktime(0,0,0,$bulan_aktif-$i,1,$tahun_aktif));
    $_end  = date('Y-m-t',  mktime(0,0,0,$_bln,   1,$_thn));
    $_bln3 = date('Y-m-01', mktime(0,0,0,$_bln-2, 1,$_thn));
    $_12   = date('Y-m-01', mktime(0,0,0,$_bln-11,1,$_thn));
    $_bln1 = date('Y-m-01', mktime(0,0,0,$_bln,   1,$_thn));

    $_tot=$_ok=$_no=$_sedang=$_berat=$_sta=$_baru=$_l3=$_l12=0;
    $_ok_den=$_no_den=$_sedang_den=$_berat_den=$_sta_den=0;
    $_norms_12=[]; $_norms_bln=[]; $_norms_all=[];

    foreach ($_dm_pts as $_key => $_visits) {
        $_norm  = substr($_key, 0, strpos($_key, "\x00"));
        $_lv = null; $_last_12 = null; $_has_bln = false;
        $_first = $_visits[0]['t'];

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

        $_tot++;
        $_gdp   = (float)$_last_12['gdp'];
        $_hba1c = (float)$_last_12['hba1c'];
        $_is_ok    = ($_gdp > 0 && $_gdp < 126) || ($_hba1c > 0 && $_hba1c < 7);
        $_is_berat = !$_is_ok && ($_gdp >= 200 || $_hba1c >= 9);
        if ($_is_ok) { $_ok++; }
        elseif ($_is_berat) { $_no++; $_berat++; }
        else { $_no++; $_sedang++; }
        if ($_last_12['sta'] === 'Diresepkan') $_sta++;
        // Denominator treatment outcomes: hanya pasien yg terdaftar sebelum 3 bulan lalu
        if ($_first < $_bln3) {
            if ($_is_ok) { $_ok_den++; }
            elseif ($_is_berat) { $_no_den++; $_berat_den++; }
            else { $_no_den++; $_sedang_den++; }
            if ($_last_12['sta'] === 'Diresepkan') $_sta_den++;
        }
    }
    $_tk  = count($_norms_12);  // patients under care (12mo)
    $_all = count($_norms_all); // all-time cumulative up to this month
    $_skr = count($_norms_bln);
    $_den = $_ok_den + $_no_den + $_l3; // treatment outcomes denominator (excl newly registered)

    $_bp_key   = $_thn . '-' . str_pad($_bln, 2, '0', STR_PAD_LEFT);
    $_bp_row   = $_bp_by_month[$_bp_key] ?? null;
    $_bp_tot   = $_bp_row ? (int)$_bp_row['tot'] : 0;
    $_bp_ok_v  = $_bp_tot > 0 ? round((int)$_bp_row['ok']    / $_bp_tot * 100) : 0;
    $_bp_ok130 = $_bp_tot > 0 ? round((int)$_bp_row['ok130'] / $_bp_tot * 100) : 0;

    $cht_labels[]       = singkatanBulan($_bln) . '-' . $_thn;
    $cht_dm_ok[]        = $_den > 0 ? round($_ok_den     / $_den * 100) : 0;
    $cht_dm_no[]        = $_den > 0 ? round($_no_den     / $_den * 100) : 0;
    $cht_dm_sedang[]    = $_den > 0 ? round($_sedang_den / $_den * 100) : 0;
    $cht_dm_berat[]     = $_den > 0 ? round($_berat_den  / $_den * 100) : 0;
    $cht_ltfu3[]        = $_den > 0 ? round($_l3         / $_den * 100) : 0;
    $cht_ltfu12[]       = $_all > 0 ? round($_l12        / $_all * 100) : 0;
    $cht_statin[]       = $_den > 0 ? round($_sta_den    / $_den * 100) : 0;
    $cht_terdaftar[]    = $_all; // all-time cumulative
    $cht_dalam_pwr[]    = $_tk;  // patients under care (12mo)
    $cht_baru[]         = $_baru;
    $cht_skrining[]     = $_skr;
    $cht_skrining_pct[] = $_tot > 0 ? round($_skr / $_tot * 100) : 0;
    $cht_bp_ok[]        = $_bp_ok_v;
    $cht_bp_ok130[]     = $_bp_ok130;
}
unset($_dm_pts, $_res_dm_ts);

// ── Skrining bulan aktif ──────────────────────────────────────────────────────
$dm_skrining_bln = !empty($cht_skrining) ? (int)end($cht_skrining) : 0;

// ── Kohort triwulanan DM ──────────────────────────────────────────────────────
function dm_quarter_range($q, $y) {
    $sm = ($q - 1) * 3 + 1; $em = $q * 3;
    return ['start' => sprintf('%04d-%02d-01', $y, $sm), 'end' => date('Y-m-t', mktime(0,0,0,$em,1,$y)), 'label' => "Q$q-$y"];
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
    $_reg  = dm_quarter_range($_rq, $_ry);
    $_meas = dm_quarter_range($_mq, $_my);

    $_koh = fetch_array(bukaquery(
        "SELECT COUNT(*) AS total,
                SUM(CASE WHEN mv.meas_visit IS NOT NULL
                          AND ((CAST(d.gdp AS DECIMAL)>0 AND CAST(d.gdp AS DECIMAL)<126) OR (CAST(d.hba1c AS DECIMAL)>0 AND CAST(d.hba1c AS DECIMAL)<7))
                         THEN 1 ELSE 0 END) AS dm_ok,
                SUM(CASE WHEN mv.meas_visit IS NOT NULL
                          AND NOT((CAST(d.gdp AS DECIMAL)>0 AND CAST(d.gdp AS DECIMAL)<126) OR (CAST(d.hba1c AS DECIMAL)>0 AND CAST(d.hba1c AS DECIMAL)<7))
                          AND NOT(CAST(d.gdp AS DECIMAL)>=200 OR CAST(d.hba1c AS DECIMAL)>=9)
                         THEN 1 ELSE 0 END) AS dm_no_mod,
                SUM(CASE WHEN mv.meas_visit IS NOT NULL
                          AND (CAST(d.gdp AS DECIMAL)>=200 OR CAST(d.hba1c AS DECIMAL)>=9)
                         THEN 1 ELSE 0 END) AS dm_no_sev,
                SUM(CASE WHEN mv.meas_visit IS NULL THEN 1 ELSE 0 END) AS dm_ltfu
         FROM (
             SELECT c.norm, c.kode FROM dm d2 JOIN checkin c ON c.id_checkin = d2.idcheckin
             WHERE c.kode IN ($in_faskes_kode)
             GROUP BY c.norm, c.kode
             HAVING MIN(c.tanggal) BETWEEN '{$_reg['start']}' AND '{$_reg['end']}'
         ) np
         LEFT JOIN (
             SELECT c2.norm, c2.kode, MAX(c2.tanggal) AS meas_visit
             FROM dm dm2 JOIN checkin c2 ON c2.id_checkin = dm2.idcheckin
             WHERE c2.kode IN ($in_faskes_kode) AND c2.tanggal BETWEEN '{$_meas['start']}' AND '{$_meas['end']}'
             GROUP BY c2.norm, c2.kode
         ) mv ON mv.norm = np.norm AND mv.kode = np.kode
         LEFT JOIN checkin cm ON cm.norm = mv.norm AND cm.kode = mv.kode AND cm.tanggal = mv.meas_visit
         LEFT JOIN dm d ON d.idcheckin = cm.id_checkin"
    ));
    if (($_koh['total'] ?? 0) > 0) {
        $data_kohort[] = [
            'label'     => $_reg['label'],
            'total'     => (int)$_koh['total'],
            'dm_ok'     => (int)$_koh['dm_ok'],
            'dm_no_mod' => (int)$_koh['dm_no_mod'],
            'dm_no_sev' => (int)$_koh['dm_no_sev'],
            'dm_ltfu'   => (int)$_koh['dm_ltfu'],
        ];
    }
}

$label_rentang = (!empty($cht_labels) ? $cht_labels[0] : '') . ' – ' . (!empty($cht_labels) ? $cht_labels[count($cht_labels)-1] : '');

$result = [
    'labels'         => $cht_labels,
    'dm_ok'          => $cht_dm_ok,
    'dm_no'          => $cht_dm_no,
    'dm_sedang'      => $cht_dm_sedang,
    'dm_berat'       => $cht_dm_berat,
    'ltfu3'          => $cht_ltfu3,
    'ltfu12'         => $cht_ltfu12,
    'statin'         => $cht_statin,
    'terdaftar'      => $cht_terdaftar,
    'dalam_pwr'      => $cht_dalam_pwr,
    'baru'           => $cht_baru,
    'skrining'       => $cht_skrining,
    'skrining_pct'   => $cht_skrining_pct,
    'bp_ok'          => $cht_bp_ok,
    'bp_ok130'       => $cht_bp_ok130,
    'skrining_bln'   => $dm_skrining_bln,
    'label_rentang'  => $label_rentang,
    'data_kohort'    => $data_kohort,
];

session_start();
pcache_set($_ck, $result);
session_write_close();
echo json_encode($result);
