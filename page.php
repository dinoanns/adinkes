<?php
if ($page === 'hipertensi') {
    require_once('module/hipertensi/hipertensi.php');

} elseif ($page === 'diabetes') {
    require_once('module/diabetes/diabetes.php');

} elseif ($page === 'heatmap') {
    require_once('module/heatmap/heatmap.php');

} else {
    require_once('eror.php');
}
