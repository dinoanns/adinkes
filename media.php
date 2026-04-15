<?php
require_once('conf/conf.php');

$url  = decode($_SERVER['REQUEST_URI']);
$page     = isset($url['module']) ? $url['module'] : 'hipertensi';
// Untuk link filter navbar, heatmap diarahkan ke hipertensi
$nav_page = $page === 'heatmap' ? 'hipertensi' : $page;

// ── Filter wilayah global ────────────────────────────────────────────────
$filter_kab  = isset($url['kab'])        ? $url['kab']        : '';
$filter_kec  = isset($url['kec'])        ? $url['kec']        : '';
$filter_kel  = isset($url['kel'])        ? $url['kel']        : '';
$filter_kode = isset($url['kode_faskes'])? $url['kode_faskes']: '';
$filter_tipe = isset($url['tipe'])       ? $url['tipe']       : '';
if (!in_array($filter_tipe, ['puskesmas', 'rsud', 'gabungan'])) $filter_tipe = '';

if ($filter_kode) {
    $filter_suffix = '&kode_faskes=' . urlencode($filter_kode);
} else {
    $filter_suffix = ($filter_tipe ? '&tipe=' . urlencode($filter_tipe) : '')
                   . ($filter_kab  ? '&kab='  . urlencode($filter_kab)  : '')
                   . ($filter_kec  ? '&kec='  . urlencode($filter_kec)  : '')
                   . ($filter_kel  ? '&kel='  . urlencode($filter_kel)  : '');
}

// ── Nama filter aktif untuk label tab ───────────────────────────────────
$_aktif_display = '';
if ($filter_kode) {
    $r = fetch_array(bukaquery("SELECT nama_faskes FROM faskes WHERE kode = '" . real_escape($filter_kode) . "' LIMIT 1"));
    $_aktif_display = $r['nama_faskes'] ?? '';
} elseif ($filter_kel) {
    $r = fetch_array(bukaquery("SELECT nama_kelurahan FROM kelurahan WHERE kode_kelurahan = '" . real_escape($filter_kel) . "' LIMIT 1"));
    $_aktif_display = $r['nama_kelurahan'] ?? '';
} elseif ($filter_kec) {
    $r = fetch_array(bukaquery("SELECT nama_kecamatan FROM kecamatan WHERE kode_kecamatan = '" . real_escape($filter_kec) . "' LIMIT 1"));
    $_aktif_display = $r['nama_kecamatan'] ?? '';
} elseif ($filter_kab) {
    $r = fetch_array(bukaquery("SELECT nama_kabupaten FROM kabupaten WHERE kode_kabupaten = '" . real_escape($filter_kab) . "' LIMIT 1"));
    $_aktif_display = $r['nama_kabupaten'] ?? '';
}

// ── Hierarki wilayah: kabupaten → kecamatan → kelurahan ─────────────────
$nav_wilayah = [];
$res_wil = bukaquery(
    "SELECT DISTINCT f.kode_kabupaten, k.nama_kabupaten,
            f.kode_kecamatan, kec.nama_kecamatan,
            f.kode_kelurahan, kel.nama_kelurahan
     FROM faskes f
     JOIN kabupaten k   ON k.kode_kabupaten   = f.kode_kabupaten
     JOIN kecamatan kec ON kec.kode_kecamatan = f.kode_kecamatan
     JOIN kelurahan kel ON kel.kode_kelurahan = f.kode_kelurahan
     WHERE f.status = '1'
     ORDER BY k.nama_kabupaten, kec.nama_kecamatan, kel.nama_kelurahan"
);
while ($rw = fetch_array($res_wil)) {
    $nav_wilayah[$rw['kode_kabupaten']][$rw['kode_kecamatan']][] = [
        'nama_kab' => $rw['nama_kabupaten'],
        'nama_kec' => $rw['nama_kecamatan'],
        'kode_kel' => $rw['kode_kelurahan'],
        'nama_kel' => $rw['nama_kelurahan'],
    ];
}

// ── Daftar RSUD & Puskesmas per kabupaten ────────────────────────────────
$nav_rsud = [];
$res_rsud = bukaquery(
    "SELECT f.kode, f.nama_faskes, f.kode_kabupaten, k.nama_kabupaten
     FROM faskes f
     JOIN kabupaten k ON k.kode_kabupaten = f.kode_kabupaten
     WHERE f.kode_master = 'RSUD' AND f.status = '1'
     ORDER BY k.nama_kabupaten, f.nama_faskes"
);
while ($rn = fetch_array($res_rsud)) {
    $nav_rsud[$rn['kode_kabupaten']][] = [
        'kode' => $rn['kode'], 'nama' => $rn['nama_faskes'],
        'nama_kab' => $rn['nama_kabupaten'],
    ];
}

$nav_puskesmas = [];
$res_pusk = bukaquery(
    "SELECT f.kode, f.nama_faskes, f.kode_kabupaten, k.nama_kabupaten,
            f.kode_kecamatan, kec.nama_kecamatan
     FROM faskes f
     JOIN kabupaten k   ON k.kode_kabupaten   = f.kode_kabupaten
     JOIN kecamatan kec ON kec.kode_kecamatan = f.kode_kecamatan
     WHERE f.kode_master = 'PKM' AND f.status = '1'
     ORDER BY k.nama_kabupaten, kec.nama_kecamatan, f.nama_faskes"
);
while ($rp = fetch_array($res_pusk)) {
    $nav_puskesmas[$rp['kode_kabupaten']][$rp['kode_kecamatan']][] = [
        'kode' => $rp['kode'], 'nama' => $rp['nama_faskes'],
        'nama_kab' => $rp['nama_kabupaten'], 'nama_kec' => $rp['nama_kecamatan'],
    ];
}
// ── Gabungan: RSUD + PKM (dengan PST per kecamatan) per kabupaten ───────────
$nav_gabungan = []; // [kode_kab] => ['nama_kab'=>, 'rsud'=>[], 'pkm'=>[kode_pkm=>[nama,pst:[]]]]
$res_gab = bukaquery(
    "SELECT f.kode, f.nama_faskes, f.kode_master, f.kode_kabupaten, f.kode_kecamatan,
            k.nama_kabupaten,
            pkm.kode AS kode_induk, pkm.nama_faskes AS nama_induk
     FROM faskes f
     JOIN kabupaten k ON k.kode_kabupaten = f.kode_kabupaten
     LEFT JOIN faskes pkm ON pkm.kode_master = 'PKM'
         AND pkm.kode_kecamatan = f.kode_kecamatan AND pkm.status = '1'
     WHERE f.kode_master IN ('RSUD','PKM','PST') AND f.status = '1'
     ORDER BY k.nama_kabupaten, f.kode_master DESC, f.nama_faskes"
);
while ($rg = fetch_array($res_gab)) {
    $kb = $rg['kode_kabupaten'];
    if (!isset($nav_gabungan[$kb])) $nav_gabungan[$kb] = ['nama_kab' => $rg['nama_kabupaten'], 'rsud' => [], 'pkm' => []];
    if ($rg['kode_master'] === 'RSUD') {
        $nav_gabungan[$kb]['rsud'][] = ['kode' => $rg['kode'], 'nama' => $rg['nama_faskes']];
    } elseif ($rg['kode_master'] === 'PKM') {
        if (!isset($nav_gabungan[$kb]['pkm'][$rg['kode']])) {
            $nav_gabungan[$kb]['pkm'][$rg['kode']] = ['nama' => $rg['nama_faskes'], 'kode' => $rg['kode'], 'pst' => []];
        }
    } elseif ($rg['kode_master'] === 'PST' && $rg['kode_induk']) {
        $kp = $rg['kode_induk'];
        if (!isset($nav_gabungan[$kb]['pkm'][$kp])) {
            $nav_gabungan[$kb]['pkm'][$kp] = ['nama' => $rg['nama_induk'], 'kode' => $kp, 'pst' => []];
        }
        $nav_gabungan[$kb]['pkm'][$kp]['pst'][] = ['kode' => $rg['kode'], 'nama' => $rg['nama_faskes']];
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>ADINKES — Dinkes Provinsi DKI Jakarta</title>
    <link rel="icon" href="favicon.png" />
    <link rel="stylesheet" href="css/preflight.css?v=1.0" />
    <link rel="stylesheet" href="css/template.css?v=<?= filemtime(__DIR__.'/css/template.css') ?>" />
    <link rel="stylesheet" href="css/dinkes.css?v=<?= filemtime(__DIR__.'/css/dinkes.css') ?>" />
    <?php if ($page === 'overdue'): ?>
    <link rel="stylesheet" href="css/overdue.css" />
    <?php endif; ?>
</head>
<body>
    <div class="banner">
      <div class="banner-body">
        <div class="faskes-nav">
          <!-- Tab buttons -->
          <div class="faskes-tabs">
            <button class="faskes-tab<?= !$filter_tipe ? ' faskes-tab-active' : '' ?>"
                    data-panel="panel-semua" type="button">
              <span class="tab-main-label">Semua Wilayah</span>
              <?php if (!$filter_tipe && $_aktif_display): ?>
              <span class="tab-filter-badge"><?= htmlspecialchars($_aktif_display) ?></span>
              <?php endif; ?>
            </button>
            <button class="faskes-tab<?= $filter_tipe === 'puskesmas' ? ' faskes-tab-active' : '' ?>"
                    data-panel="panel-puskesmas" type="button">
              <span class="tab-main-label">Puskesmas</span>
              <?php if ($filter_tipe === 'puskesmas' && $_aktif_display): ?>
              <span class="tab-filter-badge"><?= htmlspecialchars($_aktif_display) ?></span>
              <?php endif; ?>
            </button>
            <button class="faskes-tab<?= $filter_tipe === 'rsud' ? ' faskes-tab-active' : '' ?>"
                    data-panel="panel-rsud" type="button">
              <span class="tab-main-label">RSUD</span>
              <?php if ($filter_tipe === 'rsud' && $_aktif_display): ?>
              <span class="tab-filter-badge"><?= htmlspecialchars($_aktif_display) ?></span>
              <?php endif; ?>
            </button>
            <button class="faskes-tab<?= $filter_tipe === 'gabungan' ? ' faskes-tab-active' : '' ?>"
                    data-panel="panel-gabungan" type="button">
              <span class="tab-main-label">RSUD + Puskesmas</span>
              <?php if ($filter_tipe === 'gabungan' && $_aktif_display): ?>
              <span class="tab-filter-badge"><?= htmlspecialchars($_aktif_display) ?></span>
              <?php endif; ?>
            </button>
            <span style="width:1px;background:rgba(255,255,255,0.3);margin:4px 2px;align-self:stretch"></span>
            <a class="faskes-tab<?= $page === 'heatmap' ? ' faskes-tab-active' : '' ?>"
               href="page-view?<?= paramEncrypt('module=heatmap&act=dashboard') ?>">
              <span class="tab-main-label">Peta Sebaran</span>
            </a>
          </div>

          <!-- Panel: Semua (wilayah) -->
          <div id="panel-semua" class="nav-panel" style="display:none">
            <div class="nav-panel-search">
              <input type="search" id="wilayah-search" placeholder="Cari kota / kecamatan..." autocomplete="off">
            </div>
            <ul class="nav-list" id="wilayah-list">
              <li class="region-national">
                <a href="filter-region?module=<?= $nav_page ?>"
                   <?= !$filter_tipe && !$filter_kab && !$filter_kode ? 'aria-current="page"' : '' ?>>
                  Seluruh DKI Jakarta
                </a>
              </li>
              <?php foreach ($nav_wilayah as $kode_kab => $kec_list): ?>
              <?php $nama_kab = $kec_list[array_key_first($kec_list)][0]['nama_kab']; ?>
              <li class="region-state">
                <a href="filter-region?module=<?= $nav_page ?>&kab=<?= urlencode($kode_kab) ?>"
                   data-kab="<?= htmlspecialchars($kode_kab) ?>"
                   <?= !$filter_tipe && $filter_kab === $kode_kab && !$filter_kec ? 'aria-current="page"' : '' ?>>
                  <?= htmlspecialchars($nama_kab) ?>
                </a>
                <ul>
                  <?php foreach ($kec_list as $kode_kec => $kel_list): ?>
                  <?php $nama_kec = $kel_list[0]['nama_kec']; ?>
                  <li class="region-district">
                    <a href="filter-region?module=<?= $nav_page ?>&kab=<?= urlencode($kode_kab) ?>&kec=<?= urlencode($kode_kec) ?>"
                       <?= !$filter_tipe && $filter_kec === $kode_kec && !$filter_kel ? 'aria-current="page"' : '' ?>>
                      <?= htmlspecialchars($nama_kec) ?>
                    </a>
                    <ul>
                      <?php foreach ($kel_list as $kel): ?>
                      <li class="region-village">
                        <a href="filter-region?module=<?= $nav_page ?>&kab=<?= urlencode($kode_kab) ?>&kec=<?= urlencode($kode_kec) ?>&kel=<?= urlencode($kel['kode_kel']) ?>"
                           <?= !$filter_tipe && $filter_kel === $kel['kode_kel'] ? 'aria-current="page"' : '' ?>>
                          <?= htmlspecialchars($kel['nama_kel']) ?>
                        </a>
                      </li>
                      <?php endforeach; ?>
                    </ul>
                  </li>
                  <?php endforeach; ?>
                </ul>
              </li>
              <?php endforeach; ?>
            </ul>
          </div>

          <!-- Panel: Puskesmas -->
          <div id="panel-puskesmas" class="card nav-panel" style="display:none">
            <div class="nav-panel-search">
              <input type="search" id="puskesmas-search" placeholder="Cari puskesmas / kecamatan..." autocomplete="off">
            </div>
            <ul class="nav-list" id="puskesmas-list">
              <li class="region-national">
                <a href="filter-region?module=<?= $nav_page ?>&tipe=puskesmas"
                   <?= $filter_tipe === 'puskesmas' && !$filter_kab && !$filter_kode ? 'aria-current="page"' : '' ?>>
                  Semua Puskesmas DKI
                </a>
              </li>
              <?php if (empty($nav_puskesmas)): ?>
              <li class="region-state" style="color:#999;padding:.5rem 1rem;font-style:italic;">Belum ada data puskesmas</li>
              <?php else: foreach ($nav_puskesmas as $kode_kab => $kec_list): ?>
              <?php $nama_kab_p = $kec_list[array_key_first($kec_list)][0]['nama_kab']; ?>
              <li class="region-state">
                <span class="caret"></span>
                <a href="filter-region?module=<?= $nav_page ?>&tipe=puskesmas&kab=<?= urlencode($kode_kab) ?>"
                   data-kab="<?= htmlspecialchars($kode_kab) ?>"
                   <?= $filter_tipe === 'puskesmas' && $filter_kab === $kode_kab && !$filter_kec ? 'aria-current="page"' : '' ?>>
                  <?= htmlspecialchars($nama_kab_p) ?>
                </a>
                <ul class="nested">
                  <?php foreach ($kec_list as $kode_kec => $faskes_list): ?>
                  <?php $nama_kec_p = $faskes_list[0]['nama_kec']; ?>
                  <li class="region-district">
                    <span class="caret"></span>
                    <a href="filter-region?module=<?= $nav_page ?>&tipe=puskesmas&kab=<?= urlencode($kode_kab) ?>&kec=<?= urlencode($kode_kec) ?>"
                       <?= $filter_tipe === 'puskesmas' && $filter_kec === $kode_kec && !$filter_kode ? 'aria-current="page"' : '' ?>>
                      <?= htmlspecialchars($nama_kec_p) ?>
                    </a>
                    <ul class="nested">
                      <?php foreach ($faskes_list as $fs): ?>
                      <li class="region-district">
                        <a href="filter-region?module=<?= $nav_page ?>&tipe=puskesmas&kode_faskes=<?= urlencode($fs['kode']) ?>"
                           data-faskes-kode="<?= htmlspecialchars($fs['kode']) ?>"
                           <?= $filter_kode === $fs['kode'] ? 'aria-current="page"' : '' ?>>
                          <?= htmlspecialchars($fs['nama']) ?>
                        </a>
                      </li>
                      <?php endforeach; ?>
                    </ul>
                  </li>
                  <?php endforeach; ?>
                </ul>
              </li>
              <?php endforeach; endif; ?>
            </ul>
          </div>

          <!-- Panel: RSUD -->
          <div id="panel-rsud" class="nav-panel" style="display:none">
            <div class="nav-panel-search">
              <input type="search" id="rsud-search" placeholder="Cari RSUD / kota..." autocomplete="off">
            </div>
            <ul class="nav-list" id="rsud-list">
              <li class="region-national">
                <a href="filter-region?module=<?= $nav_page ?>&tipe=rsud"
                   <?= $filter_tipe === 'rsud' && !$filter_kab && !$filter_kode ? 'aria-current="page"' : '' ?>>
                  Semua RSUD DKI Jakarta
                </a>
              </li>
              <?php foreach ($nav_rsud as $kode_kab => $faskes_list): ?>
              <?php $nama_kab_r = $faskes_list[0]['nama_kab']; ?>
              <li class="region-state">
                <a href="filter-region?module=<?= $nav_page ?>&tipe=rsud&kab=<?= urlencode($kode_kab) ?>"
                   data-kab="<?= htmlspecialchars($kode_kab) ?>"
                   <?= $filter_tipe === 'rsud' && $filter_kab === $kode_kab && !$filter_kode ? 'aria-current="page"' : '' ?>>
                  <?= htmlspecialchars($nama_kab_r) ?>
                </a>
                <?php foreach ($faskes_list as $fs): ?>
                <li class="region-district">
                  <a href="filter-region?module=<?= $nav_page ?>&tipe=rsud&kode_faskes=<?= urlencode($fs['kode']) ?>"
                     data-faskes-kode="<?= htmlspecialchars($fs['kode']) ?>"
                     <?= $filter_kode === $fs['kode'] ? 'aria-current="page"' : '' ?>>
                    <?= htmlspecialchars($fs['nama']) ?>
                  </a>
                </li>
                <?php endforeach; ?>
              </li>
              <?php endforeach; ?>
            </ul>
          </div>

          <!-- Panel: Gabungan (RSUD + PKM + PST) -->
          <div id="panel-gabungan" class="nav-panel" style="display:none">
            <div class="nav-panel-search">
              <input type="search" id="gabungan-search" placeholder="Cari faskes / kota..." autocomplete="off">
            </div>
            <ul class="nav-list" id="gabungan-list">
              <li class="region-national">
                <a href="filter-region?module=<?= $nav_page ?>&tipe=gabungan"
                   <?= $filter_tipe === 'gabungan' && !$filter_kab && !$filter_kode ? 'aria-current="page"' : '' ?>>
                  Semua RSUD + Puskesmas DKI
                </a>
              </li>
              <?php foreach ($nav_gabungan as $kode_kab => $gab): ?>
              <li class="region-state">
                <span class="caret"></span>
                <a href="filter-region?module=<?= $nav_page ?>&tipe=gabungan&kab=<?= urlencode($kode_kab) ?>"
                   data-kab="<?= htmlspecialchars($kode_kab) ?>"
                   <?= $filter_tipe === 'gabungan' && $filter_kab === $kode_kab && !$filter_kode ? 'aria-current="page"' : '' ?>>
                  <?= htmlspecialchars($gab['nama_kab']) ?>
                </a>
                <ul class="nested">
                  <?php if (!empty($gab['rsud'])): ?>
                  <li class="nav-section-label">RSUD</li>
                  <?php foreach ($gab['rsud'] as $fs): ?>
                  <li class="region-district">
                    <a href="filter-region?module=<?= $nav_page ?>&tipe=gabungan&kode_faskes=<?= urlencode($fs['kode']) ?>"
                       data-faskes-kode="<?= htmlspecialchars($fs['kode']) ?>"
                       <?= $filter_kode === $fs['kode'] ? 'aria-current="page"' : '' ?>>
                      <?= htmlspecialchars($fs['nama']) ?>
                    </a>
                  </li>
                  <?php endforeach; endif; ?>
                  <?php if (!empty($gab['pkm'])): ?>
                  <li class="nav-section-label">Puskesmas</li>
                  <?php foreach ($gab['pkm'] as $pkm): ?>
                  <li class="region-district">
                    <?php if (!empty($pkm['pst'])): ?><span class="caret"></span><?php endif; ?>
                    <a href="filter-region?module=<?= $nav_page ?>&tipe=gabungan&kode_faskes=<?= urlencode($pkm['kode']) ?>"
                       data-faskes-kode="<?= htmlspecialchars($pkm['kode']) ?>"
                       <?= $filter_kode === $pkm['kode'] ? 'aria-current="page"' : '' ?>>
                      <?= htmlspecialchars($pkm['nama']) ?>
                    </a>
                    <?php if (!empty($pkm['pst'])): ?>
                    <ul class="nested">
                      <?php foreach ($pkm['pst'] as $pst): ?>
                      <li class="region-village">
                        <a href="filter-region?module=<?= $nav_page ?>&tipe=gabungan&kode_faskes=<?= urlencode($pst['kode']) ?>"
                           data-faskes-kode="<?= htmlspecialchars($pst['kode']) ?>"
                           <?= $filter_kode === $pst['kode'] ? 'aria-current="page"' : '' ?>>
                          <?= htmlspecialchars($pst['nama']) ?>
                        </a>
                      </li>
                      <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>
                  </li>
                  <?php endforeach; endif; ?>
                </ul>
              </li>
              <?php endforeach; ?>
            </ul>
          </div>
        </div>

        <!-- Logo + Judul institusi -->
        <div class="dk-brand-wrap">
          <img src="img/jakarta.png" alt="Logo DKI Jakarta" style="height:52px;width:auto;flex-shrink:0;">
          <img src="img/dinkes.png" alt="Logo Dinkes DKI" style="height:52px;width:auto;flex-shrink:0;">
        </div>
      </div>
    </div>

    <?php if ($page !== 'heatmap'): ?>
    <div class="link-dashboards">
        <ul>
            <li<?php echo $page === 'hipertensi' ? ' class="active-link"' : ''; ?>>
                <a href="page-view?<?php echo paramEncrypt('module=hipertensi&act=dashboard'.$filter_suffix); ?>">
                    Dashboard Hipertensi
                </a>
            </li>
            <li<?php echo $page === 'diabetes' ? ' class="active-link"' : ''; ?>>
                <a href="page-view?<?php echo paramEncrypt('module=diabetes&act=dashboard'.$filter_suffix); ?>">
                    Dashboard Diabetes
                </a>
            </li>
        </ul>
    </div>
    <?php endif; ?>

    <?php require_once('page.php'); ?>

    <!-- Chart.js -->
    <script src="libs/chart.umd.min.js"></script>
    <script src="js/charts.js?v=<?= filemtime(__DIR__.'/js/charts.js') ?>"></script>

    <!-- Tablesort -->
    <script src="https://cdn.jsdelivr.net/npm/tablesort@5.3.0/dist/tablesort.min.js"></script>
    <script src="js/tablesort.number.js"></script>

    <!-- Navigation -->
    <script src="js/navigation.js"></script>

    <script>
        if (document.getElementById('table-regions'))        new Tablesort(document.getElementById('table-regions'));
        if (document.getElementById('table-stock-inventory')) new Tablesort(document.getElementById('table-stock-inventory'));
    </script>

    <script>
    (function() {
        var aktifKab   = <?= json_encode($filter_kab) ?>;
        var aktifKec   = <?= json_encode($filter_kec) ?>;
        var aktifKode  = <?= json_encode($filter_kode) ?>;
        var aktifTipe  = <?= json_encode($filter_tipe) ?>;

        // ── Tab switching ────────────────────────────────────────────────────
        var tabs   = document.querySelectorAll('.faskes-tab[data-panel]');
        var panels = document.querySelectorAll('.nav-panel');

        function openPanel(panelId) {
            panels.forEach(function(p) { p.style.display = 'none'; });
            var target = document.getElementById(panelId);
            if (target) target.style.display = 'block';
        }

        tabs.forEach(function(tab) {
            tab.addEventListener('click', function(e) {
                var panelId = this.dataset.panel;
                var isOpen  = document.getElementById(panelId).style.display !== 'none';
                panels.forEach(function(p) { p.style.display = 'none'; });
                if (!isOpen) openPanel(panelId);
            });
        });


        // ── Search filter panel-semua ────────────────────────────────────
        var searchInput = document.getElementById('wilayah-search');
        if (searchInput) {
            searchInput.addEventListener('input', function() {
                var q = this.value.toLowerCase().trim();
                document.querySelectorAll('#wilayah-list .region-state').forEach(function(kotaLi) {
                    var kotaText = kotaLi.querySelector(':scope > a').textContent.toLowerCase();
                    var kotaMatch = !q || kotaText.includes(q);
                    var anyKecMatch = false;

                    kotaLi.querySelectorAll('.region-district').forEach(function(kecLi) {
                        var kecText = kecLi.querySelector(':scope > a').textContent.toLowerCase();
                        var kecMatch = kotaMatch || kecText.includes(q);

                        kecLi.querySelectorAll('.region-village').forEach(function(kelLi) {
                            var kelText = kelLi.querySelector('a').textContent.toLowerCase();
                            kelLi.style.display = (!q || kotaMatch || kecText.includes(q) || kelText.includes(q)) ? '' : 'none';
                        });

                        kecLi.style.display = kecMatch ? '' : 'none';
                        if (kecMatch) anyKecMatch = true;
                    });

                    kotaLi.style.display = (kotaMatch || anyKecMatch) ? '' : 'none';
                });
            });
        }

        // ── Search filter panel-puskesmas ────────────────────────────────
        var puskesmasSearch = document.getElementById('puskesmas-search');
        if (puskesmasSearch) {
            puskesmasSearch.addEventListener('input', function() {
                var q = this.value.toLowerCase().trim();
                document.querySelectorAll('#puskesmas-list .region-state').forEach(function(kotaLi) {
                    var kotaText = kotaLi.querySelector(':scope > a').textContent.toLowerCase();
                    var kotaMatch = !q || kotaText.includes(q);
                    var anyMatch = false;

                    kotaLi.querySelectorAll('.region-district').forEach(function(kecLi) {
                        var kecLink = kecLi.querySelector(':scope > a');
                        var faskesLink = kecLi.querySelector('a[data-faskes-id]');

                        if (faskesLink) {
                            // leaf: individual puskesmas
                            var faskesText = faskesLink.textContent.toLowerCase();
                            var show = !q || kotaMatch || faskesText.includes(q);
                            kecLi.style.display = show ? '' : 'none';
                            if (show) anyMatch = true;
                        } else if (kecLink) {
                            // kecamatan group
                            var kecText = kecLink.textContent.toLowerCase();
                            var kecMatch = kotaMatch || kecText.includes(q);
                            var anyFaskesMatch = false;
                            kecLi.querySelectorAll('.region-district a[data-faskes-id]').forEach(function(fa) {
                                var show = !q || kotaMatch || kecText.includes(q) || fa.textContent.toLowerCase().includes(q);
                                fa.closest('.region-district').style.display = show ? '' : 'none';
                                if (show) anyFaskesMatch = true;
                            });
                            kecLi.style.display = (kecMatch || anyFaskesMatch) ? '' : 'none';
                            if (kecMatch || anyFaskesMatch) {
                                anyMatch = true;
                                if (q && (kecText.includes(q) || anyFaskesMatch)) {
                                    var ul = kecLi.querySelector(':scope > ul');
                                    if (ul) { ul.classList.add('active'); var c = kecLi.querySelector(':scope > .caret'); if (c) c.classList.add('caret-down'); }
                                }
                            }
                        }
                    });

                    kotaLi.style.display = (kotaMatch || anyMatch) ? '' : 'none';
                    if (q && (kotaMatch || anyMatch)) {
                        var ul = kotaLi.querySelector(':scope > ul');
                        if (ul) { ul.classList.add('active'); var c = kotaLi.querySelector(':scope > .caret'); if (c) c.classList.add('caret-down'); }
                    }
                });
            });
        }

        // ── Search filter panel-rsud ──────────────────────────────────────
        var rsudSearch = document.getElementById('rsud-search');
        if (rsudSearch) {
            rsudSearch.addEventListener('input', function() {
                var q = this.value.toLowerCase().trim();
                document.querySelectorAll('#rsud-list .region-state').forEach(function(kotaLi) {
                    var kotaText = kotaLi.querySelector(':scope > a').textContent.toLowerCase();
                    var kotaMatch = !q || kotaText.includes(q);
                    var anyMatch = false;

                    kotaLi.querySelectorAll('.region-district').forEach(function(rsudLi) {
                        var rsudText = rsudLi.querySelector('a').textContent.toLowerCase();
                        var show = !q || kotaMatch || rsudText.includes(q);
                        rsudLi.style.display = show ? '' : 'none';
                        if (show) anyMatch = true;
                    });

                    kotaLi.style.display = (kotaMatch || anyMatch) ? '' : 'none';
                });
            });
        }

        // ── Search filter panel-gabungan ──────────────────────────────────
        var gabunganSearch = document.getElementById('gabungan-search');
        if (gabunganSearch) {
            gabunganSearch.addEventListener('input', function() {
                var q = this.value.toLowerCase().trim();
                document.querySelectorAll('#gabungan-list .region-state').forEach(function(kotaLi) {
                    var kotaText = kotaLi.querySelector(':scope > a').textContent.toLowerCase();
                    var kotaMatch = !q || kotaText.includes(q);
                    var anyMatch = false;

                    kotaLi.querySelectorAll('.region-district').forEach(function(fsLi) {
                        var fsText = fsLi.querySelector(':scope > a').textContent.toLowerCase();
                        var fsMatch = kotaMatch || fsText.includes(q);
                        var anyPstMatch = false;

                        fsLi.querySelectorAll('.region-village').forEach(function(pstLi) {
                            var pstText = pstLi.querySelector('a').textContent.toLowerCase();
                            var show = !q || kotaMatch || fsText.includes(q) || pstText.includes(q);
                            pstLi.style.display = show ? '' : 'none';
                            if (show) anyPstMatch = true;
                        });

                        var show = fsMatch || anyPstMatch;
                        fsLi.style.display = show ? '' : 'none';
                        if (show) anyMatch = true;
                        if (q && anyPstMatch) {
                            var ul = fsLi.querySelector(':scope > ul');
                            if (ul) { ul.classList.add('active'); var c = fsLi.querySelector(':scope > .caret'); if (c) c.classList.add('caret-down'); }
                        }
                    });

                    kotaLi.style.display = (kotaMatch || anyMatch) ? '' : 'none';
                    if (q && (kotaMatch || anyMatch)) {
                        var ul = kotaLi.querySelector(':scope > ul');
                        if (ul) { ul.classList.add('active'); var c = kotaLi.querySelector(':scope > .caret'); if (c) c.classList.add('caret-down'); }
                    }
                });
            });
        }

        // ── Auto-expand kabupaten aktif dalam panel RSUD/Puskesmas ───────
        document.querySelectorAll('#panel-rsud .region-state, #panel-puskesmas .region-state, #panel-gabungan .region-state').forEach(function(stateLi) {
            var kabLink = stateLi.querySelector(':scope > a');
            if (!kabLink) return;
            var kab = kabLink.dataset.kab;

            var hasActiveChild = false;
            stateLi.querySelectorAll(':scope a[data-faskes-kode]').forEach(function(a) {
                if (aktifKode && a.dataset.faskesKode === aktifKode) hasActiveChild = true;
            });

            if (aktifKab === kab || hasActiveChild) {
                var ul = stateLi.querySelector(':scope > ul');
                if (ul) { ul.classList.add('active'); var c = stateLi.querySelector(':scope > .caret'); if (c) c.classList.add('caret-down'); }
            }
        });

        // Auto-expand kecamatan aktif dalam panel Puskesmas
        document.querySelectorAll('#panel-puskesmas .region-district').forEach(function(kecLi) {
            var kecLink = kecLi.querySelector(':scope > a[href*="kec="]');
            if (!kecLink) return;
            var hasActiveChild = false;
            kecLi.querySelectorAll('a[data-faskes-kode]').forEach(function(a) {
                if (aktifKode && a.dataset.faskesKode === aktifKode) hasActiveChild = true;
            });
            if (hasActiveChild) {
                var ul = kecLi.querySelector(':scope > ul');
                if (ul) { ul.classList.add('active'); var c = kecLi.querySelector(':scope > .caret'); if (c) c.classList.add('caret-down'); }
            }
        });

        // ── Tutup panel saat klik di luar ────────────────────────────────
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.faskes-nav')) {
                panels.forEach(function(p) { p.style.display = 'none'; });
            }
        });
    })();
    </script>
</body>
</html>
