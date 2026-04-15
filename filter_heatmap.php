<?php
require_once('conf/conf.php');

$src = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;

// Periode: baca dari field "periode" (format: bulan_tahun_tipe) — paling reliable
$bulan = (int)date('m');
$tahun = (int)date('Y');
$tipe  = 'bulan';
if (!empty($src['periode'])) {
    $parts = explode('_', $src['periode']);
    if (count($parts) === 3) {
        $bulan = (int)validangka($parts[0]);
        $tahun = (int)validangka($parts[1]);
        $tipe  = in_array($parts[2], ['bulan','kuartal','tahun']) ? $parts[2] : 'bulan';
    }
} else {
    // fallback ke hidden inputs lama
    if (!empty($src['bulan'])) $bulan = (int)validangka($src['bulan']);
    if (!empty($src['tahun'])) $tahun = (int)validangka($src['tahun']);
    if (!empty($src['tipe'])  && in_array($src['tipe'], ['bulan','kuartal','tahun'])) $tipe = $src['tipe'];
}

$mode     = isset($src['mode'])           && $src['mode'] === 'dm' ? 'dm' : 'ht';
$kode_kel = isset($src['kode_kelurahan']) ? trim($src['kode_kelurahan']) : '';
$kode_kec = isset($src['kode_kecamatan']) ? trim($src['kode_kecamatan']) : '';
$kode_kab = isset($src['kode_kabupaten']) ? trim($src['kode_kabupaten']) : '';

$params = 'module=heatmap&act=dashboard&bulan=' . $bulan . '&tahun=' . $tahun . '&tipe=' . $tipe . '&mode=' . $mode;
if ($kode_kel) $params .= '&kode_kelurahan=' . urlencode($kode_kel);
if ($kode_kec) $params .= '&kode_kecamatan=' . urlencode($kode_kec);
if ($kode_kab) $params .= '&kode_kabupaten=' . urlencode($kode_kab);

$base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
header('Location: ' . $base . '/page-view?' . paramEncrypt($params));
exit;
