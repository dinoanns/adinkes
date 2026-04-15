<?php
/**
 * Helper bersama untuk semua endpoint AJAX hipertensi.
 * Di-include oleh ajax_summary.php, ajax_chart.php, ajax_table.php.
 * Menghasilkan $in_faskes_kode, $bulan_aktif, $tahun_aktif, dan semua $tgl_* .
 */
ob_start(); // tangkap semua stray output agar JSON tidak rusak
ini_set('display_errors', 0);
error_reporting(0);

require_once(__DIR__ . '/../../conf/conf.php');

// Release session lock segera setelah data session dibaca
// (supaya AJAX requests paralel tidak saling tunggu)
session_write_close();

// Tangkap PHP error/warning agar tidak merusak JSON response
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    return true; // suppress semua, jangan output ke response
});
register_shutdown_function(function() {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        ob_clean();
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(500);
        }
        echo json_encode(['error' => "PHP Fatal: {$e['message']} in {$e['file']}:{$e['line']}"]);
    }
});

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

// ── Bersihkan & validasi filter ─────────────────────────────────────────────
function _ht_clean(string $v): string {
    return preg_replace('/[^a-zA-Z0-9_\-]/', '', $v);
}

$filter_kab  = _ht_clean($_GET['kab']         ?? '');
$filter_kec  = _ht_clean($_GET['kec']         ?? '');
$filter_kel  = _ht_clean($_GET['kel']         ?? '');
$filter_kode = _ht_clean($_GET['kode_faskes'] ?? '');
$filter_tipe = _ht_clean($_GET['tipe']        ?? '');
if (!in_array($filter_tipe, ['puskesmas', 'rsud', 'gabungan'])) $filter_tipe = '';

$kode_master_map = ['puskesmas' => 'PKM', 'rsud' => 'RSUD'];

// ── Daftar kode faskes ───────────────────────────────────────────────────────
$_wf = "status = '1'";
if ($filter_kode) {
    $_wf .= " AND kode = '" . real_escape($filter_kode) . "'";
} else {
    if ($filter_tipe === 'gabungan')
        $_wf .= " AND kode_master IN ('PKM','PST','RSUD')";
    elseif ($filter_tipe && isset($kode_master_map[$filter_tipe]))
        $_wf .= " AND kode_master = '" . $kode_master_map[$filter_tipe] . "'";
    if ($filter_kab) $_wf .= " AND kode_kabupaten = '" . real_escape($filter_kab) . "'";
    if ($filter_kec) $_wf .= " AND kode_kecamatan = '" . real_escape($filter_kec) . "'";
    if ($filter_kel) $_wf .= " AND kode_kelurahan = '" . real_escape($filter_kel) . "'";
}
$_rf = bukaquery("SELECT kode FROM faskes WHERE $_wf");
$faskes_kodes = [];
while ($_r = fetch_array($_rf)) $faskes_kodes[] = "'" . real_escape($_r['kode']) . "'";
$in_faskes_kode = $faskes_kodes ? implode(',', $faskes_kodes) : "''";

// ── Periode aktif ────────────────────────────────────────────────────────────
$row_periode = fetch_array(bukaquery(
    "SELECT MONTH(MAX(c.tanggal)) AS bulan, YEAR(MAX(c.tanggal)) AS tahun
     FROM hipertensi h
     JOIN checkin c ON c.id_checkin = h.idcheckin
     WHERE c.kode IN ($in_faskes_kode)
       AND h.kd_penyakit LIKE 'I10%'
       AND c.tanggal <= CURDATE()"
));
$bulan_aktif = (int)($row_periode['bulan'] ?? date('n'));
$tahun_aktif = (int)($row_periode['tahun'] ?? date('Y'));

// ── Date ranges ──────────────────────────────────────────────────────────────
$tgl_akhir_bln  = date('Y-m-t',  mktime(0,0,0,$bulan_aktif,   1,$tahun_aktif));
$tgl_awal_bln   = date('Y-m-01', mktime(0,0,0,$bulan_aktif,   1,$tahun_aktif));
$tgl_awal_3bln  = date('Y-m-01', mktime(0,0,0,$bulan_aktif-2, 1,$tahun_aktif));
$tgl_awal_12bln = date('Y-m-01', mktime(0,0,0,$bulan_aktif-11,1,$tahun_aktif));
