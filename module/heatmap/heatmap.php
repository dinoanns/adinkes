<?php
$mode     = isset($url['mode']) && $url['mode'] === 'dm' ? 'dm' : 'ht';
$kode_kel = isset($url['kode_kelurahan']) ? trim($url['kode_kelurahan']) : '';
$kode_kec = isset($url['kode_kecamatan']) ? trim($url['kode_kecamatan']) : '';
$kode_kab = isset($url['kode_kabupaten']) ? trim($url['kode_kabupaten']) : '';

// Auto-detect periode terbaru dari data
$tbl_src = $mode === 'dm' ? 'dm' : 'hipertensi';
$r_periode = fetch_array(bukaquery(
    "SELECT MONTH(MAX(c.tanggal)) AS bulan, YEAR(MAX(c.tanggal)) AS tahun
     FROM $tbl_src x JOIN checkin c ON c.id_checkin = x.idcheckin
     WHERE c.tanggal <= CURDATE()"
));
$bulan_max = (int)($r_periode['bulan'] ?? date('n'));
$tahun_max = (int)($r_periode['tahun'] ?? date('Y'));

$bulan_filter = isset($url['bulan']) ? (int)validangka($url['bulan']) : $bulan_max;
$tahun_filter = isset($url['tahun']) ? (int)validangka($url['tahun']) : $tahun_max;
$tipe_filter  = isset($url['tipe']) && in_array($url['tipe'], ['bulan','kuartal','tahun']) ? $url['tipe'] : 'bulan';

// Rentang tanggal periode
$tgl_akhir = date('Y-m-t',  mktime(0,0,0,$bulan_filter, 1, $tahun_filter));
if ($tipe_filter === 'tahun') {
    $tgl_awal3 = $tahun_filter . '-01-01';
} elseif ($tipe_filter === 'kuartal') {
    $tgl_awal3 = date('Y-m-01', mktime(0,0,0,$bulan_filter-2, 1, $tahun_filter));
} else {
    $tgl_awal3 = date('Y-m-01', mktime(0,0,0,$bulan_filter-2, 1, $tahun_filter));
}
$tgl_awal12 = date('Y-m-01', mktime(0,0,0,$bulan_filter-11, 1, $tahun_filter));

// WHERE faskes filter
$where_fk = "f.kode_kelurahan != ''";
if ($kode_kel)     $where_fk .= " AND f.kode_kelurahan = '" . real_escape($kode_kel) . "'";
elseif ($kode_kec) $where_fk .= " AND f.kode_kecamatan = '" . real_escape($kode_kec) . "'";
elseif ($kode_kab) $where_fk .= " AND f.kode_kabupaten = '" . real_escape($kode_kab) . "'";

// Daftar faskes dengan info wilayah (LEFT JOIN agar faskes yang referensi tabelnya tidak lengkap tetap masuk)
$_rf = bukaquery(
    "SELECT f.kode, f.kode_kelurahan, f.kode_kecamatan, f.kode_kabupaten,
            kel.nama_kelurahan, kec.nama_kecamatan, kab.nama_kabupaten
     FROM faskes f
     LEFT JOIN kelurahan kel ON kel.kode_kelurahan = f.kode_kelurahan
     LEFT JOIN kecamatan kec ON kec.kode_kecamatan = f.kode_kecamatan
     LEFT JOIN kabupaten kab ON kab.kode_kabupaten = f.kode_kabupaten
     WHERE $where_fk AND f.status = '1'
     ORDER BY kab.nama_kabupaten, kec.nama_kecamatan, kel.nama_kelurahan"
);
$faskes_list = [];
while ($_r = fetch_array($_rf)) $faskes_list[] = $_r;

// Build kode_kelurahan → nama info map
$kel_info = [];
foreach ($faskes_list as $f) {
    $kk = $f['kode_kelurahan'];
    if (!isset($kel_info[$kk])) $kel_info[$kk] = [
        'kelurahan' => $f['nama_kelurahan'] ?? '',
        'kecamatan' => $f['nama_kecamatan'] ?? '',
        'kota'      => $f['nama_kabupaten'] ?? '',
    ];
}

// Kumpulkan daftar kode faskes dari filter
$faskes_kodes_hm = array_unique(array_column($faskes_list, 'kode'));
$in_fk = $faskes_kodes_hm ? implode(',', array_map(fn($k) => "'" . real_escape($k) . "'", $faskes_kodes_hm)) : "''";

// Query data per kode_kelurahan
$kel_data = [];

if ($mode === 'ht') {
    $res_ht = bukaquery(
        "SELECT f.kode_kelurahan,
                COUNT(*) AS ht_total,
                SUM(CASE WHEN CAST(h.sistole  AS UNSIGNED) < 140
                          AND CAST(h.diastole AS UNSIGNED) < 90
                         THEN 1 ELSE 0 END) AS ht_ok
         FROM hipertensi h
         JOIN checkin c ON c.id_checkin = h.idcheckin
         INNER JOIN (
             SELECT c2.norm, c2.kode, MAX(c2.tanggal) AS last_visit
             FROM hipertensi h2 JOIN checkin c2 ON c2.id_checkin = h2.idcheckin
             WHERE h2.kd_penyakit LIKE 'I10%' AND c2.tanggal <= '$tgl_akhir'
             GROUP BY c2.norm, c2.kode
             HAVING last_visit BETWEEN '$tgl_awal3' AND '$tgl_akhir'
         ) lv ON lv.norm = c.norm AND lv.kode = c.kode AND lv.last_visit = c.tanggal
         JOIN faskes f ON f.kode = c.kode
         WHERE h.kd_penyakit LIKE 'I10%' AND c.kode IN ($in_fk)
         GROUP BY f.kode_kelurahan"
    );
    while ($row = fetch_array($res_ht)) {
        $kk  = $row['kode_kelurahan'];
        $tot = (int)$row['ht_total'];
        $ok  = (int)$row['ht_ok'];
        $inf = $kel_info[$kk] ?? ['kelurahan'=>'','kecamatan'=>'','kota'=>''];
        $kel_data[$kk] = [
            'kelurahan' => $inf['kelurahan'], 'kecamatan' => $inf['kecamatan'], 'kota' => $inf['kota'],
            'ht_total'  => $tot, 'ht_ok' => $ok,
            'ht_persen' => $tot > 0 ? round($ok / $tot * 100, 1) : null,
            'dm_total'  => 0, 'dm_ok' => 0, 'dm_persen' => null,
        ];
    }
} else {
    $res_dm = bukaquery(
        "SELECT f.kode_kelurahan,
                COUNT(*) AS dm_total,
                SUM(CASE WHEN (CAST(d.gdp AS DECIMAL)>0 AND CAST(d.gdp AS DECIMAL)<126)
                          OR (CAST(d.hba1c AS DECIMAL)>0 AND CAST(d.hba1c AS DECIMAL)<7)
                         THEN 1 ELSE 0 END) AS dm_ok
         FROM dm d
         JOIN checkin c ON c.id_checkin = d.idcheckin
         INNER JOIN (
             SELECT c2.norm, c2.kode, MAX(c2.tanggal) AS last_visit
             FROM dm d2 JOIN checkin c2 ON c2.id_checkin = d2.idcheckin
             WHERE c2.tanggal <= '$tgl_akhir'
             GROUP BY c2.norm, c2.kode
             HAVING last_visit BETWEEN '$tgl_awal3' AND '$tgl_akhir'
         ) lv ON lv.norm = c.norm AND lv.kode = c.kode AND lv.last_visit = c.tanggal
         JOIN faskes f ON f.kode = c.kode
         WHERE c.kode IN ($in_fk)
         GROUP BY f.kode_kelurahan"
    );
    while ($row = fetch_array($res_dm)) {
        $kk  = $row['kode_kelurahan'];
        $tot = (int)$row['dm_total'];
        $ok  = (int)$row['dm_ok'];
        $inf = $kel_info[$kk] ?? ['kelurahan'=>'','kecamatan'=>'','kota'=>''];
        $kel_data[$kk] = [
            'kelurahan' => $inf['kelurahan'], 'kecamatan' => $inf['kecamatan'], 'kota' => $inf['kota'],
            'ht_total'  => 0, 'ht_ok' => 0, 'ht_persen' => null,
            'dm_total'  => $tot, 'dm_ok' => $ok,
            'dm_persen' => $tot > 0 ? round($ok / $tot * 100, 1) : null,
        ];
    }
}

// Daftar semua kelurahan untuk autocomplete (LEFT JOIN agar semua faskes masuk)
$res_kel = bukaquery(
    "SELECT DISTINCT f.kode_kelurahan, f.kode_kecamatan,
            COALESCE(kel.nama_kelurahan, f.kode_kelurahan) AS kelurahan,
            COALESCE(kec.nama_kecamatan, f.kode_kecamatan) AS kecamatan,
            COALESCE(kab.nama_kabupaten, f.kode_kabupaten) AS kota
     FROM faskes f
     LEFT JOIN kelurahan kel ON kel.kode_kelurahan = f.kode_kelurahan
     LEFT JOIN kecamatan kec ON kec.kode_kecamatan = f.kode_kecamatan
     LEFT JOIN kabupaten kab ON kab.kode_kabupaten = f.kode_kabupaten
     WHERE f.kode_kelurahan != '' AND f.status = '1'
     ORDER BY kab.nama_kabupaten, kec.nama_kecamatan, kel.nama_kelurahan"
);
$all_kel = [];
while ($r = fetch_array($res_kel)) $all_kel[] = $r;
?>

<!-- Leaflet + Esri Leaflet -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/esri-leaflet@3.0.12/dist/esri-leaflet.js"></script>

<style>
#peta-toolbar {
  display: flex;
  align-items: center;
  gap: 1.25rem;
  padding: .65rem 1.5rem;
  background: #fff;
  border-bottom: 1px solid #e5e9ee;
  flex-wrap: wrap;
  position: relative;
  z-index: 2000;
}
.tb-group { display: flex; flex-direction: column; gap: 3px; }
.tb-label { font-size: .7rem; font-weight: 700; color: #999; text-transform: uppercase; letter-spacing: .05em; }
.tb-sel {
  height: 34px; padding: 0 .6rem;
  border: 1px solid #d1d9e0; border-radius: 6px;
  font-size: .84rem; font-family: inherit;
  background: #fff; color: #1a2b3c; box-sizing: border-box;
}
.mode-toggle { display: flex; background: #f0f4f8; border-radius: 8px; padding: 3px; gap: 2px; }
.mode-btn {
  height: 28px; padding: 0 .9rem; border: none; border-radius: 6px;
  font-size: .83rem; font-weight: 600; cursor: pointer;
  background: transparent; color: #666; font-family: inherit; transition: all .15s;
}
.mode-btn.active { background: #fff; color: #0075eb; box-shadow: 0 1px 4px rgba(0,0,0,.12); }
#btn-tampilkan {
  height: 34px; padding: 0 1.1rem;
  background: #0075eb; color: #fff; border: none; border-radius: 6px;
  font-size: .84rem; font-weight: 600; cursor: pointer; font-family: inherit;
}
#btn-tampilkan:hover { background: #005bbf; }
#peta-info { margin-left: auto; font-size: .8rem; color: #999; white-space: nowrap; }
#peta-info strong { color: #333; }
.tb-divider { width: 1px; background: #e5e9ee; align-self: stretch; margin: 0 .25rem; }
#zoom-kota, #zoom-kec, #zoom-kel {
  height: 34px; padding: 0 .6rem;
  border: 1px solid #d1d9e0; border-radius: 6px;
  font-size: .84rem; font-family: inherit;
  background: #fff; color: #1a2b3c; box-sizing: border-box;
  min-width: 140px;
}
#zoom-kota:disabled, #zoom-kec:disabled, #zoom-kel:disabled { opacity: .45; }
#btn-zoom-reset {
  height: 34px; padding: 0 .8rem;
  border: 1px solid #d1d9e0; border-radius: 6px;
  font-size: .82rem; background: #fff; color: #555;
  cursor: pointer; font-family: inherit; display: none;
}
#btn-zoom-reset:hover { background: #f4f4f4; }
#peta-map { width: 100%; }
.cust-opt:hover { background: #f5f7fa !important; }
body.page-heatmap { overflow: hidden; }
.peta-legend {
  background: #fff; padding: 10px 14px; border-radius: 8px;
  box-shadow: 0 2px 10px rgba(0,0,0,.16); font-size: .79rem; line-height: 1.6;
}
.peta-legend h4 { margin: 0 0 7px; font-size: .81rem; font-weight: 700; color: #333; }
.leg-row { display: flex; align-items: center; gap: 7px; margin-bottom: 3px; }
.leg-sw  { width: 14px; height: 14px; border-radius: 3px; flex-shrink: 0; border: 1px solid rgba(0,0,0,.08); }
#peta-loading {
  position: absolute; top: 50%; left: 50%; transform: translate(-50%,-50%);
  background: rgba(255,255,255,.9); padding: 12px 20px; border-radius: 8px;
  font-size: .85rem; color: #555; box-shadow: 0 2px 10px rgba(0,0,0,.15);
  z-index: 1000; pointer-events: none;
}
</style>

<main class="main" style="padding:0;position:relative">

  <form method="post" action="filter-heatmap" id="peta-toolbar">
    <input type="hidden" name="mode"           id="mode-input"  value="<?= $mode ?>">
    <input type="hidden" name="bulan"          id="bulan-input" value="<?= $bulan_filter ?>">
    <input type="hidden" name="tahun"          id="tahun-input" value="<?= $tahun_filter ?>">
    <input type="hidden" name="tipe"           id="tipe-input"  value="<?= $tipe_filter ?>">
    <input type="hidden" name="periode"        id="periode-input" value="<?= $bulan_filter.'_'.$tahun_filter.'_'.$tipe_filter ?>">
    <input type="hidden" name="kode_kelurahan" id="kkel-hidden" value="<?= $kode_kel ?>">
    <input type="hidden" name="kode_kecamatan" id="kkec-hidden" value="<?= $kode_kec ?>">
    <input type="hidden" name="kode_kabupaten" id="kkab-hidden" value="<?= $kode_kab ?>">

    <!-- Periode (custom dropdown, selalu terbuka ke bawah) -->
    <?php
    // Build daftar opsi periode
    $_opts = [];
    // Bulan (12 bulan terakhir)
    for ($i = 0; $i < 12; $i++) {
        $pb  = (int)date('n', mktime(0,0,0,$bulan_max - $i, 1, $tahun_max));
        $pt  = (int)date('Y', mktime(0,0,0,$bulan_max - $i, 1, $tahun_max));
        $_opts[] = ['val' => $pb.'_'.$pt.'_bulan',
                    'lbl' => konversiBulan(str_pad($pb,2,'0',STR_PAD_LEFT)).' '.$pt,
                    'grp' => 'bulan'];
    }
    // Kuartal: kumpulkan unik, lalu sort tahun desc + Q asc
    $kuartals_raw = [];
    for ($i = 0; $i < 12; $i++) {
        $pb = (int)date('n', mktime(0,0,0,$bulan_max - $i, 1, $tahun_max));
        $pt = (int)date('Y', mktime(0,0,0,$bulan_max - $i, 1, $tahun_max));
        $q  = (int)ceil($pb / 3);
        $key = $pt.'_'.$q;
        if (!isset($kuartals_raw[$key])) $kuartals_raw[$key] = ['tahun'=>$pt,'q'=>$q,'bln'=>$q*3];
    }
    krsort($kuartals_raw); // sort by key (tahun_q) descending
    // Re-sort: tahun desc, Q asc
    usort($kuartals_raw, function($a,$b){
        if ($a['tahun'] !== $b['tahun']) return $b['tahun'] - $a['tahun'];
        return $a['q'] - $b['q'];
    });
    foreach ($kuartals_raw as $kq) {
        $_opts[] = ['val' => $kq['bln'].'_'.$kq['tahun'].'_kuartal',
                    'lbl' => 'Kuartal '.$kq['q'].' — '.$kq['tahun'],
                    'grp' => 'kuartal'];
    }
    // Tahun
    for ($t = $tahun_max; $t >= 2024; $t--) {
        $_opts[] = ['val' => '12_'.$t.'_tahun', 'lbl' => 'Tahun '.$t, 'grp' => 'tahun'];
    }
    // Tentukan nilai aktif
    $_active_val = $bulan_filter.'_'.$tahun_filter.'_'.$tipe_filter;
    $_active_lbl = '';
    foreach ($_opts as $o) { if ($o['val'] === $_active_val) { $_active_lbl = $o['lbl']; break; } }
    ?>
    <div class="tb-group" style="position:relative">
      <div class="tb-label">Periode</div>
      <div id="cust-periode" class="tb-sel" style="min-width:160px;display:flex;align-items:center;justify-content:space-between;cursor:pointer;user-select:none;gap:6px">
        <span id="cust-periode-lbl"><?= htmlspecialchars($_active_lbl) ?></span>
        <span style="font-size:.7em;color:#888">▾</span>
      </div>
      <div id="cust-periode-menu" style="display:none;position:absolute;top:calc(100% + 4px);left:0;z-index:99999;background:#fff;border:1px solid #d1d9e0;border-radius:6px;box-shadow:0 8px 24px rgba(0,0,0,.15);min-width:190px;max-height:340px;overflow-y:auto;padding:4px 0">
        <?php $last_grp = null; foreach ($_opts as $o):
            if ($o['grp'] !== $last_grp):
                if ($last_grp !== null): ?>
        <div style="height:1px;background:#e5e9ee;margin:6px 0"></div>
                <?php endif; ?>
        <div style="padding:2px 14px 4px;font-size:.68rem;font-weight:700;color:#aaa;text-transform:uppercase;letter-spacing:.06em"><?=
            $o['grp'] === 'bulan' ? 'Bulan' : ($o['grp'] === 'kuartal' ? 'Kuartal' : 'Tahun')
        ?></div>
        <?php
            endif; $last_grp = $o['grp'];
            $is_active = ($o['val'] === $_active_val);
        ?>
        <div class="cust-opt" data-val="<?= htmlspecialchars($o['val']) ?>"
             style="padding:6px 14px;font-size:.84rem;cursor:pointer;white-space:nowrap;<?= $is_active ? 'font-weight:700;color:#0075eb;background:#f0f7ff' : 'color:#1a2b3c' ?>">
          <?= $is_active ? '✓ ' : '' ?><?= htmlspecialchars($o['lbl']) ?>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Penyakit -->
    <div class="tb-group">
      <div class="tb-label">Penyakit</div>
      <div class="mode-toggle">
        <button type="button" class="mode-btn <?= $mode === 'ht' ? 'active' : '' ?>" data-mode="ht">Hipertensi</button>
        <button type="button" class="mode-btn <?= $mode === 'dm' ? 'active' : '' ?>" data-mode="dm">Diabetes</button>
      </div>
    </div>

    <div style="display:flex;align-items:center;padding-top:18px">
      <button type="submit" id="btn-tampilkan">Tampilkan</button>
    </div>

    <div class="tb-divider"></div>

    <!-- Zoom wilayah (auto-zoom, tanpa tombol) -->
    <div class="tb-group">
      <div class="tb-label">Kota / Kab</div>
      <select id="zoom-kota">
        <option value="">— Semua DKI —</option>
      </select>
    </div>

    <div class="tb-group">
      <div class="tb-label">Kecamatan</div>
      <select id="zoom-kec" disabled>
        <option value="">— Semua —</option>
      </select>
    </div>

    <div class="tb-group">
      <div class="tb-label">Kelurahan</div>
      <select id="zoom-kel" disabled>
        <option value="">— Semua —</option>
      </select>
    </div>

    <div style="display:flex;align-items:center;padding-top:18px">
      <button type="button" id="btn-zoom-reset" onclick="resetZoom()" style="display:none">× Reset wilayah</button>
    </div>

    <?php
    $q_num = ceil($bulan_filter / 3);
    if ($tipe_filter === 'tahun') {
        $label_periode_info = 'Tahun ' . $tahun_filter;
    } elseif ($tipe_filter === 'kuartal') {
        $q_num = (int)ceil($bulan_filter / 3);
        $label_periode_info = 'Q' . $q_num . '-' . $tahun_filter;
    } else {
        $label_periode_info = konversiBulan(str_pad($bulan_filter,2,'0',STR_PAD_LEFT)) . ' ' . $tahun_filter;
    }
    ?>
    <div id="peta-info">
      Data: <strong><?= $label_periode_info ?></strong>
      &nbsp;·&nbsp; <strong><?= $mode === 'dm' ? 'Diabetes' : 'Hipertensi' ?></strong>
      &nbsp;·&nbsp; <?= count($kel_data) ?> kelurahan
    </div>
  </form>

  <div id="peta-loading">Memuat peta wilayah…</div>
  <div id="peta-map"></div>

</main>

<script>
var _kelData    = <?= json_encode($kel_data) ?>;
var _allKel     = <?= json_encode($all_kel) ?>;
var _mode       = <?= json_encode($mode) ?>;
var _filterKkel = <?= json_encode($kode_kel) ?>;
var _filterKkec = <?= json_encode($kode_kec) ?>;

// Layout
document.body.classList.add('page-heatmap');
function setMapHeight() {
    var el = document.getElementById('peta-map');
    var top = el.getBoundingClientRect().top;
    el.style.height = Math.max(window.innerHeight - top, 400) + 'px';
}
setMapHeight();
window.addEventListener('resize', function() {
    setMapHeight();
    if (typeof map !== 'undefined') map.invalidateSize();
});

// Mode toggle
document.querySelectorAll('.mode-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
        document.querySelectorAll('.mode-btn').forEach(function(b) { b.classList.remove('active'); });
        this.classList.add('active');
        document.getElementById('mode-input').value = this.dataset.mode;
    });
});

// Periode selector → update hidden bulan/tahun saat submit
// ── Custom periode dropdown ───────────────────────────────────────────────
var custBtn  = document.getElementById('cust-periode');
var custMenu = document.getElementById('cust-periode-menu');
var custLbl  = document.getElementById('cust-periode-lbl');

custBtn.addEventListener('click', function(e) {
    e.stopPropagation();
    custMenu.style.display = custMenu.style.display === 'none' ? 'block' : 'none';
});
document.addEventListener('click', function() { custMenu.style.display = 'none'; });

document.querySelectorAll('.cust-opt').forEach(function(opt) {
    opt.addEventListener('click', function(e) {
        e.stopPropagation();
        var val   = this.dataset.val;
        var label = this.textContent.replace(/^✓\s*/, '').trim();
        custLbl.textContent = label;
        custMenu.style.display = 'none';

        // Highlight selected
        document.querySelectorAll('.cust-opt').forEach(function(o) {
            o.style.fontWeight = '';
            o.style.color = '#1a2b3c';
            o.textContent = o.textContent.replace(/^✓\s*/, '');
        });
        this.style.fontWeight = '700';
        this.style.color = '#0075eb';
        this.textContent = '✓ ' + label;

        setPeriode(val);
    });
});

function setPeriode(val) {
    var parts = val.split('_');
    document.getElementById('periode-input').value = val;
    document.getElementById('bulan-input').value   = parts[0];
    document.getElementById('tahun-input').value   = parts[1];
    document.getElementById('tipe-input').value    = parts[2] || 'bulan';
}
// Pastikan nilai tersync saat submit
document.getElementById('peta-toolbar').addEventListener('submit', function() {
    // periode-input sudah di-set saat klik opsi, tidak perlu sync ulang
});


// ── Peta ──────────────────────────────────────────────────────────────────
var DKI_BOUNDS = L.latLngBounds([-6.40, 106.64], [-6.07, 107.01]);
var map = L.map('peta-map', {
    center: [-6.21, 106.845], zoom: 12,
    minZoom: 10, maxZoom: 17,
    maxBounds: DKI_BOUNDS.pad(0.3),
    maxBoundsViscosity: 0.8
});

// Basemap tiles (tanpa label dulu)
L.esri.basemapLayer('Gray').addTo(map);

function getColor(persen) {
    if (persen === null || persen === undefined) return 'rgba(200,200,200,0.5)';
    if (persen >= 75) return 'rgba(0,160,75,0.82)';
    if (persen >= 50) return 'rgba(120,200,80,0.82)';
    if (persen >= 25) return 'rgba(255,190,0,0.82)';
    return 'rgba(210,40,40,0.82)';
}
function pctColor(p) {
    if (p >= 75) return '#009646';
    if (p >= 50) return '#5a9f20';
    if (p >= 25) return '#cc8800';
    return '#c00';
}
function tr(label, val) {
    return '<tr><td style="padding:3px 4px 3px 0;color:#666">' + label + '</td>'
         + '<td style="padding:3px 0;text-align:right">' + val + '</td></tr>';
}

var GEOJSON_URL = '/adinkes/libs/dki-kelurahan.geojson';
var geojsonLayer;

fetch(GEOJSON_URL)
    .then(function(r) {
        if (!r.ok) throw new Error('HTTP ' + r.status);
        return r.json();
    })
    .then(function(geojson) {
        document.getElementById('peta-loading').style.display = 'none';

        buildCascade(geojson.features);

        geojsonLayer = L.geoJSON(geojson, {
            style: function(feature) {
                var kdepum   = feature.properties.kdepum || '';
                var kdcpum   = feature.properties.kdcpum || '';
                var data     = _kelData[kdepum] || null;
                var persen   = data ? (_mode === 'dm' ? data.dm_persen : data.ht_persen) : null;
                var isTarget = (_filterKkel && kdepum === String(_filterKkel))
                            || (_filterKkec && kdcpum === String(_filterKkec));
                return {
                    fillColor:   isTarget ? '#0075eb' : getColor(persen),
                    fillOpacity: isTarget ? 0.25 : 1,
                    color:       isTarget ? '#0075eb' : 'rgba(255,255,255,0.7)',
                    weight:      isTarget ? 2.5 : 0.7,
                    opacity:     1
                };
            },
            onEachFeature: function(feature, layer) {
                var kdepum = feature.properties.kdepum || '';
                var kel    = feature.properties.wadmkd || '';
                var kec    = feature.properties.wadmkc || '';
                var kota   = feature.properties.wadmkk || '';
                var data   = _kelData[kdepum] || null;

                var html = '<div style="min-width:210px;font-size:.85rem">'
                    + '<strong style="font-size:.92rem">' + kel + '</strong>'
                    + ' <span style="color:#aaa;font-size:.78rem">' + kec + '</span><br>'
                    + '<span style="color:#999;font-size:.76rem">' + kota + '</span>'
                    + '<hr style="margin:.45rem 0;border:none;border-top:1px solid #eee">';

                if (data) {
                    html += '<table style="width:100%;border-collapse:collapse;font-size:.83rem">';
                    if (_mode === 'ht') {
                        html += tr('Dalam perawatan (HT)', data.ht_total)
                            + tr('BP terkontrol',
                                data.ht_ok
                                + (data.ht_persen !== null
                                    ? ' <b style="color:' + pctColor(data.ht_persen) + '">(' + data.ht_persen + '%)</b>'
                                    : ''));
                    } else {
                        html += tr('Dalam perawatan (DM)', data.dm_total)
                            + tr('DM terkontrol',
                                data.dm_ok
                                + (data.dm_persen !== null
                                    ? ' <b style="color:' + pctColor(data.dm_persen) + '">(' + data.dm_persen + '%)</b>'
                                    : ''));
                    }
                    html += '</table>';
                } else {
                    html += '<i style="color:#bbb">Tidak ada data faskes</i>';
                }
                html += '</div>';

                layer.bindPopup(html, { maxWidth: 270 });
                layer.on('mouseover', function() {
                    this.setStyle({ weight: 2.5, color: '#0075eb', fillOpacity: 0.88 });
                    this.bringToFront();
                });
                layer.on('mouseout', function() { geojsonLayer.resetStyle(this); });
                layer.on('click',    function() { this.openPopup(); });
            }
        }).addTo(map);

        // Label kota di atas choropleth
        L.esri.basemapLayer('GrayLabels').addTo(map);

        // Zoom ke filter jika ada
        if (_filterKkel || _filterKkec) {
            var bounds = null;
            geojsonLayer.eachLayer(function(layer) {
                var kdepum = layer.feature.properties.kdepum || '';
                var kdcpum = layer.feature.properties.kdcpum || '';
                if ((_filterKkel && kdepum === String(_filterKkel)) || (_filterKkec && kdcpum === String(_filterKkec))) {
                    bounds = bounds ? bounds.extend(layer.getBounds()) : layer.getBounds();
                    if (_filterKkel) layer.openPopup();
                }
            });
            if (bounds) map.fitBounds(bounds, { padding: [50, 50] });
        } else {
            map.fitBounds(DKI_BOUNDS, { padding: [20, 20] });
        }

        // Setelah GeoJSON selesai, zoom ke DKI jika belum di-fit
        map.invalidateSize();
    })
    .catch(function(err) {
        document.getElementById('peta-loading').textContent = 'Gagal memuat data peta. Periksa koneksi internet.';
        console.error('GeoJSON error:', err);
    });

// ── Cascade filter (zoom tanpa reload) ───────────────────────────────────────
var _geoFeatures = []; // simpan semua features setelah GeoJSON load

function buildCascade(features) {
    _geoFeatures = features;

    // Kumpulkan kota unik (dari GeoJSON)
    var kotaSet = {};
    features.forEach(function(f) {
        var kota = f.properties.wadmkk || '';
        if (kota && !kotaSet[kota]) kotaSet[kota] = true;
    });
    var kotaList = Object.keys(kotaSet).sort();

    var selKota = document.getElementById('zoom-kota');
    kotaList.forEach(function(k) {
        var opt = document.createElement('option');
        opt.value = k; opt.textContent = k;
        selKota.appendChild(opt);
    });

    selKota.addEventListener('change', function() {
        var kota = this.value;
        fillKec(kota);
        fillKel('', '');
        document.getElementById('zoom-kec').disabled = !kota;
        document.getElementById('zoom-kel').disabled = true;
        document.getElementById('btn-zoom-reset').style.display = kota ? 'block' : 'none';
        doZoom();
    });

    document.getElementById('zoom-kec').addEventListener('change', function() {
        var kec = this.value;
        fillKel(document.getElementById('zoom-kota').value, kec);
        document.getElementById('zoom-kel').disabled = !kec;
        document.getElementById('btn-zoom-reset').style.display = 'block';
        doZoom();
    });

    document.getElementById('zoom-kel').addEventListener('change', function() {
        document.getElementById('btn-zoom-reset').style.display = 'block';
        doZoom();
    });
}

function fillKec(kota) {
    var sel = document.getElementById('zoom-kec');
    sel.innerHTML = '<option value="">— Semua —</option>';
    var kecSet = {};
    _geoFeatures.forEach(function(f) {
        var p = f.properties;
        if ((!kota || p.wadmkk === kota) && p.wadmkc && !kecSet[p.wadmkc]) {
            kecSet[p.wadmkc] = p.kdcpum || '';
        }
    });
    Object.keys(kecSet).sort().forEach(function(kec) {
        var opt = document.createElement('option');
        opt.value = kec; opt.textContent = kec;
        sel.appendChild(opt);
    });
}

function fillKel(kota, kec) {
    var sel = document.getElementById('zoom-kel');
    sel.innerHTML = '<option value="">— Semua —</option>';
    _geoFeatures.forEach(function(f) {
        var p = f.properties;
        if ((!kota || p.wadmkk === kota) && (!kec || p.wadmkc === kec) && p.wadmkd) {
            var opt = document.createElement('option');
            opt.value = p.kdepum || ''; opt.textContent = p.wadmkd;
            sel.appendChild(opt);
        }
    });
}

function doZoom() {
    var kdepum = document.getElementById('zoom-kel').value;
    var kecNm  = document.getElementById('zoom-kec').value;
    var kotaNm = document.getElementById('zoom-kota').value;

    if (!geojsonLayer) return;
    var bounds = null;

    // Reset style dulu
    geojsonLayer.eachLayer(function(layer) {
        geojsonLayer.resetStyle(layer);
    });

    geojsonLayer.eachLayer(function(layer) {
        var p = layer.feature.properties;
        var match = false;
        if (kdepum) {
            match = (p.kdepum === kdepum);
        } else if (kecNm) {
            match = (p.wadmkc === kecNm);
        } else if (kotaNm) {
            match = (p.wadmkk === kotaNm);
        }

        if (match) {
            bounds = bounds ? bounds.extend(layer.getBounds()) : layer.getBounds();
            // Highlight border area terpilih
            layer.setStyle({ weight: 2.5, color: '#0075eb', fillOpacity: 0.88 });
            if (kdepum) layer.openPopup();
        } else if (kdepum || kecNm || kotaNm) {
            // Redup area lain
            layer.setStyle({ fillOpacity: 0.2, opacity: 0.3 });
        }
    });

    if (bounds) {
        map.fitBounds(bounds, { padding: [60, 60], maxZoom: kdepum ? 15 : (kecNm ? 13 : 12) });
    }
    document.getElementById('btn-zoom-reset').style.display = 'block';
}

function resetZoom() {
    document.getElementById('zoom-kota').value = '';
    document.getElementById('zoom-kec').innerHTML = '<option value="">— Semua —</option>';
    document.getElementById('zoom-kel').innerHTML = '<option value="">— Semua —</option>';
    document.getElementById('zoom-kec').disabled = true;
    document.getElementById('zoom-kel').disabled = true;
    document.getElementById('btn-zoom-reset').style.display = 'none';
    if (geojsonLayer) {
        geojsonLayer.eachLayer(function(layer) { geojsonLayer.resetStyle(layer); });
        map.fitBounds(DKI_BOUNDS, { padding: [20, 20] });
    }
}

// Legenda
var legend = L.control({ position: 'bottomleft' });
legend.onAdd = function() {
    var div = L.DomUtil.create('div', 'peta-legend');
    div.innerHTML = '<h4>% Terkontrol — ' + (_mode === 'dm' ? 'Diabetes' : 'Hipertensi') + '</h4>'
        + lr('#009646', '≥ 75% — Sangat baik')
        + lr('#78C850', '50 – 74% — Baik')
        + lr('#FFBE00', '25 – 49% — Perlu perhatian')
        + lr('#D22828', '< 25% — Kritis')
        + lr('rgba(200,200,200,0.6)', 'Tidak ada data');
    return div;
};
legend.addTo(map);

function lr(color, label) {
    return '<div class="leg-row"><div class="leg-sw" style="background:' + color + '"></div><span>' + label + '</span></div>';
}
</script>
