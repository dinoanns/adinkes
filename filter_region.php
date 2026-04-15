<?php
require_once('conf/conf.php');

$module      = isset($_GET['module'])      ? $_GET['module']                : 'hipertensi';
$tipe        = isset($_GET['tipe'])        ? trim($_GET['tipe'])            : '';
$kab         = isset($_GET['kab'])         ? trim($_GET['kab'])             : '';
$kec         = isset($_GET['kec'])         ? trim($_GET['kec'])             : '';
$kel         = isset($_GET['kel'])         ? trim($_GET['kel'])             : '';
$kode_faskes = isset($_GET['kode_faskes']) ? trim($_GET['kode_faskes'])     : '';

if (!in_array($tipe, ['puskesmas', 'rsud', 'gabungan'])) $tipe = '';

$allowed_modules = ['hipertensi', 'diabetes', 'heatmap'];
if (!in_array($module, $allowed_modules)) $module = 'hipertensi';

$params = "module=$module&act=dashboard";
if ($kode_faskes) {
    if ($tipe) $params .= '&tipe=' . urlencode($tipe);
    $params .= '&kode_faskes=' . urlencode($kode_faskes);
} else {
    if ($tipe) $params .= '&tipe=' . urlencode($tipe);
    if ($kab)  $params .= '&kab='  . urlencode($kab);
    if ($kec)  $params .= '&kec='  . urlencode($kec);
    if ($kel)  $params .= '&kel='  . urlencode($kel);
}

$base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
header('Location: ' . $base . '/page-view?' . paramEncrypt($params));
exit;
