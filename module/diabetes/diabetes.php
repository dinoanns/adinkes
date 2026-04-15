<?php
// ── Filter dari URL (diwarisi dari media.php scope) ──────────────────────────
$filter_kab  = isset($url['kab'])         ? $url['kab']         : '';
$filter_kec  = isset($url['kec'])         ? $url['kec']         : '';
$filter_kel  = isset($url['kel'])         ? $url['kel']         : '';
$filter_kode = isset($url['kode_faskes']) ? $url['kode_faskes'] : '';
$filter_tipe = isset($url['tipe'])        ? $url['tipe']        : '';
if (!in_array($filter_tipe, ['puskesmas', 'rsud', 'gabungan'])) $filter_tipe = '';

$_ajax_qs   = http_build_query(array_filter([
    'kab'         => $filter_kab,
    'kec'         => $filter_kec,
    'kel'         => $filter_kel,
    'kode_faskes' => $filter_kode,
    'tipe'        => $filter_tipe,
]));
$_ajax_base = 'module/diabetes/';
$_qs        = $_ajax_qs ? '?' . $_ajax_qs : '';

switch (isset($url['act']) ? $url['act'] : '') {
    case 'dashboard':
    default:
?>
<style>
.dm-skeleton {
    background: linear-gradient(90deg,#f0f0f0 25%,#e0e0e0 50%,#f0f0f0 75%);
    background-size: 200% 100%;
    animation: dm-shimmer 1.4s infinite;
    border-radius: 3px;
    color: transparent !important;
}
@keyframes dm-shimmer { 0%{background-position:200% 0} 100%{background-position:-200% 0} }
.dm-error { color:#c0392b; padding:.5rem; font-style:italic; }
</style>

<main class="main">
  <div class="header">
    <h1 id="dm-nama-wilayah"><span class="dm-skeleton" style="display:inline-block;width:200px;height:1.2em;">&nbsp;</span></h1>
    <div class="date-updated">Data: <b id="dm-label-bulan"><span class="dm-skeleton" style="display:inline-block;width:80px;height:1em;">&nbsp;</span></b></div>
  </div>

  <!-- ══ SECTION 1 ═════════════════════════════════════════════════════════ -->
  <h2 class="columns-header">1. Indikator kardiovaskular + DM</h2>
  <div class="columns-3">

    <!-- DM dengan TD terkontrol -->
    <div class="card">
      <div class="heading">
        <h3>Pasien DM dengan TD terkontrol</h3>
        <div class="info"><div class="info-hover-text">
          <p><span>Pembilang:</span> Pasien DM dalam perawatan yang juga terdaftar HT, dengan TD terkontrol pada kunjungan HT terakhir dalam 3 bulan.</p>
          <p><span>Penyebut:</span> Pasien DM dalam perawatan yang memiliki kunjungan HT dalam 3 bulan terakhir.</p>
        </div></div>
        <p>Pasien DM dalam perawatan dengan TD terkontrol pada kunjungan terakhir dalam 3 bulan terakhir</p>
      </div>
      <div class="body">
        <div class="figures">
          <div class="detail" id="dm-bp-detail"><span class="dm-skeleton" style="display:inline-block;width:180px;height:1em;">&nbsp;</span></div>
        </div>
        <div class="chart"><canvas id="dmBpControlled"></canvas></div>
      </div>
    </div>

    <!-- Statin -->
    <div class="card">
      <div class="heading">
        <h3>Pasien DM diresepkan statin</h3>
        <div class="info"><div class="info-hover-text">
          <p><span>Pembilang:</span> Pasien DM dalam perawatan yang mendapat resep statin pada kunjungan terakhir dalam 3 bulan terakhir.</p>
          <p><span>Penyebut:</span> Semua pasien DM dalam perawatan (minimal 1 kunjungan dalam 3 bulan terakhir).</p>
        </div></div>
        <p id="dm-statin-sub">Pasien DM yang mendapat resep statin pada kunjungan terakhir dalam 12 bulan terakhir <span class="text-grey" style="font-size:0.8em;" id="dm-label-rentang"></span></p>
      </div>
      <div class="body">
        <div class="figures">
          <div>
            <p class="large-num" style="color:#34aea0" id="dm-pct-statin"><span class="dm-skeleton" style="display:inline-block;width:60px;height:1.5em;">&nbsp;</span></p>
            <div class="detail" id="dm-statin-detail">&nbsp;</div>
          </div>
        </div>
        <div class="chart"><canvas id="dmstatins"></canvas></div>
      </div>
    </div>

  </div>

  <!-- ══ SECTION 2 ═════════════════════════════════════════════════════════ -->
  <h2 class="columns-header spacer">2. Indikator manajemen program</h2>

  <!-- Hasil perawatan DM -->
  <div class="card">
    <div class="heading">
      <h3>Hasil perawatan DM</h3>
      <div class="info"><div class="info-hover-text">
        <p><span>Gula darah terkontrol:</span> GDP &lt;126 mg/dL atau HbA1c &lt;7% pada kunjungan terakhir dalam 3 bulan terakhir.</p>
        <p><span>Gula darah tidak terkontrol:</span> GDP &ge;126 mg/dL atau HbA1c &ge;7% pada kunjungan terakhir dalam 3 bulan terakhir.</p>
        <p><span>Tidak berkunjung 3 bulan:</span> Pasien terdaftar tanpa kunjungan dalam 3 bulan terakhir.</p>
      </div></div>
      <p id="dm-hasil-sub">Memuat data… <span class="text-grey" style="font-size:0.8em;" id="dm-label-3bln"></span></p>
      <div class="body columns-3">
        <div class="inner-card">
          <div class="figures">
            <h4 class="bp-controlled">Gula darah terkontrol pada kunjungan terakhir dalam 3 bulan terakhir</h4>
            <p class="large-num bp-controlled" id="dm-pct-ok"><span class="dm-skeleton" style="display:inline-block;width:60px;height:1.5em;">&nbsp;</span></p>
            <div class="detail" id="dm-ok-detail">&nbsp;</div>
          </div>
          <div class="chart"><canvas id="dmcontrolled"></canvas></div>
        </div>
        <div class="inner-card">
          <div class="figures">
            <h4 class="bp-uncontrolled">Gula darah tidak terkontrol pada kunjungan terakhir dalam 3 bulan terakhir</h4>
            <p class="large-num bp-uncontrolled" id="dm-pct-no"><span class="dm-skeleton" style="display:inline-block;width:60px;height:1.5em;">&nbsp;</span></p>
            <div class="detail" id="dm-no-detail">&nbsp;</div>
          </div>
          <div class="chart"><canvas id="dmuncontrolled"></canvas></div>
        </div>
        <div class="inner-card">
          <div class="figures">
            <h4 class="three-month-ltfu">Tidak berkunjung dalam 3 bulan terakhir</h4>
            <p class="large-num three-month-ltfu" id="dm-pct-ltfu3"><span class="dm-skeleton" style="display:inline-block;width:60px;height:1.5em;">&nbsp;</span></p>
            <div class="detail" id="dm-ltfu3-detail">&nbsp;</div>
          </div>
          <div class="chart"><canvas id="dmLtfu3Month"></canvas></div>
        </div>
      </div>
    </div>
  </div>

  <!-- 3 kartu baris bawah -->
  <div class="columns-3">
    <div class="card">
      <div class="heading">
        <h3>Pasien dalam perawatan</h3>
        <div class="info"><div class="info-hover-text">
          <p><span>Pasien dalam perawatan:</span> Pasien diabetes dengan minimal 1 kunjungan dalam 3 bulan terakhir.</p>
          <p><span>Terdaftar kumulatif:</span> Semua pasien diabetes yang pernah terdaftar.</p>
        </div></div>
        <p id="dm-reg-sub">Pasien diabetes dengan minimal 1 kunjungan dalam 12 bulan terakhir <span class="text-grey" id="dm-reg-rentang"></span></p>
      </div>
      <div class="body">
        <div class="figures"><div>
          <p class="large-num under-care" id="dm-total-pasien"><span class="dm-skeleton" style="display:inline-block;width:80px;height:1.5em;">&nbsp;</span></p>
          <div class="detail" id="dm-reg-detail">&nbsp;</div>
        </div></div>
        <div class="chart"><canvas id="dmregistrations"></canvas></div>
      </div>
    </div>

    <div class="card">
      <div class="heading">
        <h3>Hilang tindak lanjut 12 bulan</h3>
        <div class="info"><div class="info-hover-text">
          <p><span>Pembilang:</span> Pasien diabetes tanpa kunjungan dalam 12 bulan terakhir.</p>
          <p><span>Penyebut:</span> Semua pasien diabetes terdaftar kumulatif.</p>
        </div></div>
        <p id="dm-ltfu12-sub">Pasien diabetes tanpa kunjungan dalam 12 bulan terakhir <span class="text-grey" id="dm-ltfu12-rentang"></span></p>
      </div>
      <div class="body">
        <div class="figures">
          <p class="large-num twelve-month-ltfu" id="dm-pct-ltfu12"><span class="dm-skeleton" style="display:inline-block;width:60px;height:1.5em;">&nbsp;</span></p>
          <div class="detail" id="dm-ltfu12-detail">&nbsp;</div>
        </div>
        <div class="chart"><canvas id="dmltfu12months"></canvas></div>
      </div>
    </div>

    <div class="card">
      <div class="heading">
        <h3>Skrining oportunistik</h3>
        <div class="info"><div class="info-hover-text">
          <p><span>Pembilang:</span> Pasien DM dalam perawatan dengan GDP atau HbA1c tercatat dalam bulan ini.</p>
          <p><span>Penyebut:</span> Total pasien DM dalam perawatan (kunjungan terakhir 3 bulan).</p>
        </div></div>
        <p>% pasien DM dengan GDP atau HbA1c tercatat pada bulan <span id="dm-skrining-bulan"></span></p>
      </div>
      <div class="body">
        <div class="figures"><div>
          <p class="large-num" style="color:#34aea0" id="dm-pct-skrining"><span class="dm-skeleton" style="display:inline-block;width:60px;height:1.5em;">&nbsp;</span></p>
          <div class="detail" id="dm-skrining-detail">&nbsp;</div>
        </div></div>
        <div class="chart"><canvas id="dmscreenings"></canvas></div>
      </div>
    </div>
  </div>

  <!-- Kaskade -->
  <div class="card">
    <div class="heading">
      <h3>Kaskade perawatan diabetes</h3>
      <div class="info"><div class="info-hover-text" id="dm-kaskade-info"><p>Memuat data…</p></div></div>
      <p>Pasien DM terdaftar, dalam perawatan, dan terkontrol</p>
    </div>
    <div class="body">
      <div class="coverage" id="dm-kaskade">
        <div class="dm-skeleton" style="height:200px;width:100%;border-radius:4px;">&nbsp;</div>
      </div>
    </div>
  </div>

  <!-- Tabel sub-wilayah (bagian dari Section 2) -->
  <div class="card">
    <div class="heading">
      <h3>Bandingkan sub-wilayah</h3>
      <p>Hasil perawatan DM pada kunjungan terakhir <span class="text-grey" style="font-size:0.8em;" id="dm-tbl-label-3bln"></span></p>
    </div>
    <div class="table-container">
      <p class="table-scroll-message text-grey mobile-only">scroll table &rarr;</p>
      <div class="table-wrap">
        <table id="table-regions">
          <colgroup><col/><col span="2"/><col/><col/><col span="2"/><col span="2"/><col span="2"/><col span="2"/></colgroup>
          <thead>
            <tr class="text-center">
              <td></td>
              <th scope="colgroup">Pasien dalam perawatan</th>
              <th scope="colgroup">Pasien baru</th>
              <th scope="colgroup">Statin</th>
              <th colspan="2" scope="colgroup">Gula darah terkontrol</th>
              <th colspan="2" scope="colgroup">Tidak terkontrol (sedang)</th>
              <th colspan="2" scope="colgroup">Tidak terkontrol (berat)</th>
              <th colspan="2" scope="colgroup">Tidak berkunjung 3 bulan</th>
            </tr>
            <tr class="head-bg">
              <th><div>Kabupaten/Kota</div></th>
              <th class="text-right"><div>Total</div></th>
              <th class="text-right"><div id="dm-tbl-bln-header">&nbsp;</div></th>
              <th class="text-right"><div>Total</div></th>
              <th class="text-right"><div>Total</div></th>
              <th data-sort-default><div>Persen</div></th>
              <th class="text-right"><div>Total</div></th>
              <th><div>Persen</div></th>
              <th class="text-right"><div>Total</div></th>
              <th><div>Persen</div></th>
              <th class="text-right"><div>Total</div></th>
              <th><div>Persen</div></th>
            </tr>
          </thead>
          <tbody id="dm-tbl-body">
            <tr><td colspan="12" style="text-align:center;padding:2rem;color:#aaa;">Memuat data sub-wilayah…</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

<script>
var tblRegions = document.getElementById('table-regions');
if (tblRegions) tblRegions.addEventListener('afterSort', function() {
    document.querySelectorAll('.sub-kota-row[data-target]').forEach(function(r) {
        var id = r.dataset.target;
        var subs = Array.from(document.querySelectorAll('.sub-rsud-row[data-group="' + id + '"]'));
        var ref = r;
        subs.forEach(function(s) { ref.parentNode.insertBefore(s, ref.nextSibling); ref = s; });
    });
});
</script>

  <!-- Kohort DM -->
  <div class="card">
    <div class="heading">
      <h3>Laporan kohort triwulanan DM</h3>
      <p>Hasil perawatan untuk pasien DM baru terdaftar, diukur pada akhir triwulan berikutnya</p>
    </div>
    <div class="body">
      <p class="table-scroll-message text-grey mobile-only">scroll chart &rarr;</p>
      <div class="table-wrap">
        <div class="cohort" id="dm-kohort">
          <div class="dm-skeleton" style="height:180px;width:100%;border-radius:4px;">&nbsp;</div>
        </div>
        <div class="key">
          <div class="key-text"><span class="key-color-box bp-controlled-bg"></span> Gula darah terkontrol</div>
          <div class="key-text"><span class="key-color-box bp-uncontrolled-bg"></span> Tidak terkontrol (sedang)</div>
          <div class="key-text"><span class="key-color-box bp-uncontrolled-dark-bg" style="background:#7b0000;display:inline-block;width:12px;height:12px;margin-right:4px;"></span> Tidak terkontrol (berat)</div>
          <div class="key-text"><span class="key-color-box three-month-ltfu-bg"></span> Tidak berkunjung 3 bulan</div>
        </div>
      </div>
    </div>
  </div>

  <!-- ══ SECTION 3 ═════════════════════════════════════════════════════════ -->
  <h2 class="columns-header spacer">3. Usia dan jenis kelamin</h2>
  <div class="card">
    <div class="heading">
      <h3>Usia dan jenis kelamin pasien DM dalam perawatan</h3>
      <p><span class="under-care" id="dm-usia-sub">&nbsp;</span></p>
    </div>
    <div class="body">
      <div class="table-container">
        <p class="table-scroll-message text-grey mobile-only">scroll table &rarr;</p>
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th style="width:10%;border-right:0">Usia</th>
                <th style="width:40%" class="text-right" id="dm-th-p">Perempuan</th>
                <th style="width:40%;border-right:0" id="dm-th-l">Laki-laki</th>
                <th style="width:10%;border-left:0">Total</th>
              </tr>
            </thead>
            <tbody id="dm-usia-body">
              <tr><td colspan="4" style="text-align:center;padding:2rem;color:#aaa;">Memuat data…</td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

</main>

<script>
(function () {
    'use strict';

    var qs      = <?= json_encode($_qs) ?>;
    var baseUrl = <?= json_encode($_ajax_base) ?>;

    function fmt(n) { return Number(n).toLocaleString('id-ID'); }
    function txt(id, v) { var el=document.getElementById(id); if(el) el.textContent=v; }
    function html(id, v) { var el=document.getElementById(id); if(el) el.innerHTML=v; }
    function showErr(id, msg) { var el=document.getElementById(id); if(el) el.innerHTML='<span class="dm-error">'+msg+'</span>'; }
    function hesc(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

    function dmFetch(endpoint, onDone, onFail) {
        fetch(baseUrl + endpoint + qs)
            .then(function(r){ return r.json(); })
            .then(function(d){
                if (d && d.error) { onFail('Error server: ' + d.error); return; }
                onDone(d);
            })
            .catch(function(e){ onFail(String(e)); });
    }

    // ── render ringkasan ────────────────────────────────────────────────────────
    function renderSummary(d) {
        txt('dm-nama-wilayah', d.nama_wilayah);
        txt('dm-label-bulan',  d.label_bulan);
        txt('dm-skrining-bulan', d.label_bulan);
        txt('dm-label-3bln',  '· ' + d.label_3bln);
        txt('dm-tbl-label-3bln', '· ' + d.label_3bln);
        txt('dm-tbl-bln-header', d.label_bulan);

        txt('dm-pct-ok',    d.pct_dm_ok + '%');
        html('dm-ok-detail', fmt(d.dm_ok) + ' pasien dengan GDP &lt;126 mg/dL atau HbA1c &lt;7%');
        txt('dm-pct-no',    d.pct_dm_no + '%');
        html('dm-no-detail', fmt(d.dm_no) + ' pasien gula darah tidak terkontrol');
        txt('dm-pct-ltfu3', d.pct_ltfu3 + '%');
        html('dm-ltfu3-detail', fmt(d.dm_ltfu3) + ' pasien tanpa kunjungan');
        html('dm-hasil-sub', 'Hasil untuk ' + fmt(d.terdaftar_den || 0) + ' pasien (terdaftar sebelum 3 bulan lalu)');

        html('dm-total-pasien', fmt(d.dm_total));
        html('dm-reg-detail',
            '<p>' + fmt(d.terdaftar_baru) + ' pasien terdaftar pada ' + d.label_bulan + '</p>' +
            '<p class="text-grey">dari <span class="registrations">' + fmt(d.terdaftar_alltime || 0) + ' pasien terdaftar kumulatif</span></p>');

        txt('dm-pct-ltfu12', d.pct_ltfu12 + '%');
        html('dm-ltfu12-detail',
            '<p>' + fmt(d.dm_ltfu12) + ' pasien tanpa kunjungan 12 bulan</p>' +
            '<p class="text-grey">dari <span class="registrations">' + fmt(d.terdaftar_alltime) + ' pasien terdaftar kumulatif</span></p>');

        txt('dm-pct-statin', d.pct_statin + '%');
        html('dm-statin-detail',
            '<p>' + fmt(d.dm_statin) + ' pasien diresepkan statin</p>' +
            '<p class="text-grey">dari <span class="under-care">' + fmt(d.dm_total) + ' pasien DM dalam perawatan</span></p>');

        html('dm-bp-detail',
            '<p><b class="bp-controlled">' + d.pct_dm_bp + '%</b> &nbsp; ' + fmt(d.dm_bp_ok) + ' pasien dengan TD &lt;140/90</p>' +
            '<p><b class="bp-controlled-dark">' + d.pct_dm_bp130 + '%</b> &nbsp; ' + fmt(d.dm_bp_ok_130) + ' pasien dengan TD &lt;130/80</p>' +
            '<p class="text-grey">dari ' + fmt(d.dm_bp_total) + ' pasien dalam perawatan (dengan data TD)</p>');

        txt('dm-pct-skrining', d.pct_skrining !== undefined ? d.pct_skrining + '%' : '–');
        html('dm-skrining-detail',
            '<p>' + fmt(d.dm_skrining_bln || 0) + ' pasien diskrining pada ' + d.label_bulan + '</p>' +
            '<p class="text-grey">dari ' + fmt(d.total_pasien) + ' dalam perawatan</p>');

        // Kaskade: Level1=semua terdaftar (all-time), Level2=dalam perawatan (12mo), Level3=terkontrol (3mo)
        var _hasData = (d.terdaftar_alltime || 0) > 0;
        html('dm-kaskade',
            '<div class="coverage-column">' +
              '<div class="coverage-bar"><div class="coverage-bar-fill" style="height:' + (_hasData ? '100' : '0') + '%"><div class="coverage-estimated">' + (_hasData ? '100' : '0') + '%</div></div></div>' +
              '<p>' + fmt(d.terdaftar_alltime || 0) + '</p>' +
              '<p class="text-grey label-small">Pasien terdaftar kumulatif</p>' +
            '</div>' +
            '<div class="coverage-column">' +
              '<div class="coverage-bar"><div class="coverage-bar-fill under-care-bg" style="height:' + d.pct_perawatan + '%">' +
                '<p class="coverage-number under-care">' + d.pct_perawatan + '%</p></div></div>' +
              '<p>' + fmt(d.terdaftar_kum) + '</p>' +
              '<p class="text-grey label-small">Pasien dalam perawatan (12 bln)</p>' +
            '</div>' +
            '<div class="coverage-column">' +
              '<div class="coverage-bar"><div class="coverage-bar-fill bp-controlled-bg" style="height:' + d.pct_terkontrol_pop + '%">' +
                '<p class="coverage-number bp-controlled">' + d.pct_terkontrol_pop + '%</p></div></div>' +
              '<p>' + fmt(d.dm_ok) + '</p>' +
              '<p class="text-grey label-small">Pasien dengan gula darah terkontrol</p>' +
            '</div>');

        html('dm-kaskade-info',
            '<p><b>Sumber data:</b> Dinas Kesehatan Provinsi DKI Jakarta.</p>' +
            '<p><span>Terdaftar kumulatif:</span> ' + fmt(d.terdaftar_alltime || 0) + ' pasien. Basis 100%.</p>' +
            '<p><span>Dalam perawatan (12 bln):</span> ' + fmt(d.terdaftar_kum) + ' pasien (' + d.pct_perawatan + '%).</p>' +
            '<p><span>Gula darah terkontrol:</span> ' + fmt(d.dm_ok) + ' pasien (' + d.pct_terkontrol_pop + '%).</p>');

        // Tabel usia
        txt('dm-th-p', 'Perempuan (' + d.pct_perempuan + '%)');
        txt('dm-th-l', 'Laki-laki (' + d.pct_laki + '%)');
        txt('dm-usia-sub', fmt(d.dm_total) + ' pasien dalam perawatan · ' + d.label_bulan);
        var rows = [
            ['18-29', d.pct_p_18_29, d.pct_l_18_29, d.pct_t_18_29,
             Math.round(d.pct_p_18_29/Math.max(d.max_p,1)*100), Math.round(d.pct_l_18_29/Math.max(d.max_l,1)*100)],
            ['30-49', d.pct_p_30_49, d.pct_l_30_49, d.pct_t_30_49,
             Math.round(d.pct_p_30_49/Math.max(d.max_p,1)*100), Math.round(d.pct_l_30_49/Math.max(d.max_l,1)*100)],
            ['50-69', d.pct_p_50_69, d.pct_l_50_69, d.pct_t_50_69,
             Math.round(d.pct_p_50_69/Math.max(d.max_p,1)*100), Math.round(d.pct_l_50_69/Math.max(d.max_l,1)*100)],
            ['70+',   d.pct_p_70,    d.pct_l_70,    d.pct_t_70,
             Math.round(d.pct_p_70/Math.max(d.max_p,1)*100),    Math.round(d.pct_l_70/Math.max(d.max_l,1)*100)],
        ];
        var tbody = '';
        rows.forEach(function(r) {
            tbody += '<tr>' +
                '<th style="border-right:0">' + r[0] + '</th>' +
                '<td class="text-right"><div style="border-radius:3px;padding:4px 8px;color:rgba(0,0,0,0.6);background:Thistle;width:' + r[4] + '%;display:inline-block;">' + r[1] + '%</div></td>' +
                '<td style="border-right:0"><div style="border-radius:3px;padding:4px 8px;color:rgba(0,0,0,0.6);background:LightSteelBlue;width:' + r[5] + '%;display:inline-block;">' + r[2] + '%</div></td>' +
                '<td style="border-left:0" class="text-right">' + r[3] + '%</td>' +
                '</tr>';
        });
        html('dm-usia-body', tbody);
    }

    // ── render chart ─────────────────────────────────────────────────────────
    function renderCharts(d) {
        // Set global vars yang dipakai charts.js
        window._dmLabels  = d.labels;
        window._dmLabels3 = d.labels.slice(-3);
        window._dm = {
            dm_ok: d.dm_ok, dm_no: d.dm_no, dm_sedang: d.dm_sedang, dm_berat: d.dm_berat,
            ltfu3: d.ltfu3, ltfu12: d.ltfu12,
            statin: d.statin, terdaftar: d.terdaftar, dalam_pwr: d.dalam_pwr,
            baru: d.baru, skrining: d.skrining, skrining_pct: d.skrining_pct,
            bp_ok: d.bp_ok, bp_ok130: d.bp_ok130,
        };
        window._dmKomorbid = { dm_ht: 0, dm_ht_ok: 0, total_pasien: 0 };
        window._dmSkrining = { skrining_bln: d.skrining_bln, total_pasien: 0 };

        if (typeof window.dmRenderCharts === 'function') {
            window.dmRenderCharts();
        }

        // Kohort
        var kohortEl = document.getElementById('dm-kohort');
        if (!kohortEl) return;
        if (!d.data_kohort || !d.data_kohort.length) {
            kohortEl.innerHTML = '<p class="text-grey" style="padding:1rem;font-style:italic;">Belum ada data kohort DM.</p>';
            return;
        }
        var khtml = '';
        d.data_kohort.forEach(function(kh) {
            var tot = kh.total;
            var ok  = tot>0 ? Math.round(kh.dm_ok     /tot*100) : 0;
            var nom = tot>0 ? Math.round(kh.dm_no_mod  /tot*100) : 0;
            var nos = tot>0 ? Math.round(kh.dm_no_sev  /tot*100) : 0;
            var lf  = tot>0 ? Math.round(kh.dm_ltfu    /tot*100) : 0;
            khtml += '<div class="cohort-quarter">' +
                '<div class="cohort-bar">' +
                  '<div class="segment bp-controlled-bg"    style="height:'+ok+'%">'+ok+'%</div>' +
                  '<div class="segment bp-uncontrolled-bg"  style="height:'+nom+'%">'+nom+'%</div>' +
                  '<div class="segment" style="height:'+nos+'%;background:#7b0000;color:#fff;">'+nos+'%</div>' +
                  '<div class="segment three-month-ltfu-bg" style="height:'+lf+'%">'+lf+'%</div>' +
                '</div>' +
                '<div class="cohort-detail"><b>' + hesc(kh.label) + '</b> ' + fmt(tot) + ' pasien</div>' +
                '</div>';
        });
        kohortEl.innerHTML = khtml;
    }

    // ── render tabel ─────────────────────────────────────────────────────────
    function renderTable(d) {
        var tbody = '';

        // ── Mode Gabungan: dikelompokkan per Kabupaten → RSUD/PKM → PST ──────────
        if (d.data_gabungan) {
            // Indeks data_sub per kabupaten untuk baris ringkasan kota
            var _subByKab = {};
            (d.data_sub || []).forEach(function(dk) { _subByKab[dk.nama_wilayah] = dk; });

            // Kelompokkan faskes per kabupaten
            var _byKab = {}, _kabOrder = [];
            d.data_gabungan.forEach(function(fs) {
                if (!_byKab[fs.nama_kab]) { _byKab[fs.nama_kab] = []; _kabOrder.push(fs.nama_kab); }
                _byKab[fs.nama_kab].push(fs);
            });

            _kabOrder.forEach(function(kab) {
                var dk   = _subByKab[kab] || {};
                var dp   = dk.dalam_pwr || 0;
                var ok   = dk.dm_ok || 0;
                var sed  = dk.dm_sedang || 0;
                var brt  = dk.dm_berat || 0;
                var st   = dk.dm_statin || 0;
                var lf   = dk.dm_ltfu3 || 0;
                var _tk  = dp + lf; // terdaftar_kumulatif = dalam_perawatan + ltfu
                var ok_p  = _tk>0?Math.round(ok /_tk*100):0;
                var sed_p = _tk>0?Math.round(sed/_tk*100):0;
                var brt_p = _tk>0?Math.round(brt/_tk*100):0;
                var lf_p  = _tk>0?Math.round(lf /_tk*100):0;
                var kabId = 'kab-' + kab.toLowerCase().replace(/[^a-z0-9]/gi,'-');

                tbody += '<tr class="sub-kota-row" data-target="'+kabId+'" style="cursor:pointer;background:#eef2ff">' +
                    '<th class="link" style="display:flex;align-items:center;gap:.4rem;font-weight:700">' +
                    '<span class="sub-caret" style="font-size:.7rem;transition:transform .2s">&#9654;</span>' +
                    hesc(kab)+'</th>' +
                    '<td class="number under-care bold">'+fmt(dp)+'</td>' +
                    '<td class="number">'+fmt(dk.terdaftar_baru||0)+'</td>' +
                    '<td class="number">'+fmt(st)+'</td>' +
                    '<td class="number">'+fmt(ok)+'</td>' +
                    '<td class="bp-controlled bold">'+ok_p+'%</td>' +
                    '<td class="number">'+fmt(sed)+'</td>' +
                    '<td class="bp-uncontrolled bold">'+sed_p+'%</td>' +
                    '<td class="number">'+fmt(brt)+'</td>' +
                    '<td class="bp-uncontrolled bold" style="color:#7b0000">'+brt_p+'%</td>' +
                    '<td class="number">'+fmt(lf)+'</td>' +
                    '<td class="three-month-ltfu bold">'+lf_p+'%</td>' +
                    '</tr>';

                _byKab[kab].forEach(function(fs) {
                    var fp   = fs.total_pasien;
                    var ftk  = fs.terdaftar_kumulatif || 0;
                    var fok  = fs.dm_ok; var fsed = fs.dm_sedang; var fbrt = fs.dm_berat;
                    var fst  = fs.dm_statin; var flf = fs.dm_ltfu3;
                    var fok_p  = ftk>0?Math.round(fok /ftk*100):0;
                    var fsed_p = ftk>0?Math.round(fsed/ftk*100):0;
                    var fbrt_p = ftk>0?Math.round(fbrt/ftk*100):0;
                    var flf_p  = ftk>0?Math.round(flf /ftk*100):0;
                    var hasPst = fs.pst && fs.pst.length > 0;
                    var kid  = 'gab-' + String(fs.kode).replace(/[^a-z0-9]/gi,'-');
                    var tipeLabel = fs.tipe==='RSUD'
                        ? '<span style="font-size:.7em;background:#e8f0fe;color:#1a56db;border-radius:3px;padding:1px 5px;margin-right:4px">RSUD</span>'
                        : '<span style="font-size:.7em;background:#def7ec;color:#03543f;border-radius:3px;padding:1px 5px;margin-right:4px">PKM</span>';

                    tbody += '<tr class="sub-rsud-row gab-faskes-row" data-group="'+kabId+'"'+
                        (hasPst?' data-target="'+kid+'"':'')+
                        ' data-sort-method="none" style="display:none;cursor:'+(hasPst?'pointer':'default')+'">' +
                        '<th class="link" style="padding-left:1.5rem;display:flex;align-items:center;gap:.4rem;font-weight:normal">' +
                        (hasPst?'<span class="sub-caret" style="font-size:.7rem;transition:transform .2s">&#9654;</span>':'') +
                        tipeLabel + hesc(fs.nama_wilayah)+'</th>' +
                        '<td class="number">'+fmt(fp)+'</td>' +
                        '<td class="number">'+fmt(fs.terdaftar_baru)+'</td>' +
                        '<td class="number">'+fmt(fst)+'</td>' +
                        '<td class="number">'+fmt(fok)+'</td>' +
                        '<td class="bp-controlled bold">'+fok_p+'%</td>' +
                        '<td class="number">'+fmt(fsed)+'</td>' +
                        '<td class="bp-uncontrolled bold">'+fsed_p+'%</td>' +
                        '<td class="number">'+fmt(fbrt)+'</td>' +
                        '<td class="bp-uncontrolled bold" style="color:#7b0000">'+fbrt_p+'%</td>' +
                        '<td class="number">'+fmt(flf)+'</td>' +
                        '<td class="three-month-ltfu bold">'+flf_p+'%</td>' +
                        '</tr>';

                    if (hasPst) {
                        fs.pst.forEach(function(pst) {
                            var pp   = pst.total_pasien;
                            var ptk  = pst.terdaftar_kumulatif || 0;
                            var pok_p  = ptk>0?Math.round(pst.dm_ok    /ptk*100):0;
                            var psed_p = ptk>0?Math.round(pst.dm_sedang/ptk*100):0;
                            var pbrt_p = ptk>0?Math.round(pst.dm_berat /ptk*100):0;
                            var plf_p  = ptk>0?Math.round(pst.dm_ltfu3 /ptk*100):0;
                            tbody += '<tr class="sub-rsud-row" data-group="'+kid+'" data-sort-method="none" style="display:none;background:#f5f9ff">' +
                                '<th class="link" style="padding-left:3rem;font-weight:normal;font-size:.85em">' +
                                '<span style="font-size:.7em;background:#fdf6b2;color:#723b13;border-radius:3px;padding:1px 5px;margin-right:4px">PST</span>' +
                                hesc(pst.nama_wilayah)+'</th>' +
                                '<td class="number">'+fmt(pp)+'</td>' +
                                '<td class="number">'+fmt(pst.terdaftar_baru)+'</td>' +
                                '<td class="number">'+fmt(pst.dm_statin)+'</td>' +
                                '<td class="number">'+fmt(pst.dm_ok)+'</td>' +
                                '<td class="bp-controlled bold">'+pok_p+'%</td>' +
                                '<td class="number">'+fmt(pst.dm_sedang)+'</td>' +
                                '<td class="bp-uncontrolled bold">'+psed_p+'%</td>' +
                                '<td class="number">'+fmt(pst.dm_berat)+'</td>' +
                                '<td class="bp-uncontrolled bold" style="color:#7b0000">'+pbrt_p+'%</td>' +
                                '<td class="number">'+fmt(pst.dm_ltfu3)+'</td>' +
                                '<td class="three-month-ltfu bold">'+plf_p+'%</td>' +
                                '</tr>';
                        });
                    }
                });
            });

            html('dm-tbl-body', tbody ||
                '<tr><td colspan="12" style="text-align:center;padding:2rem;color:#aaa;">Tidak ada data</td></tr>');

            // Klik kabupaten → toggle faskes
            document.querySelectorAll('.sub-kota-row[data-target]').forEach(function(row) {
                row.addEventListener('click', function() {
                    var id = this.dataset.target;
                    var children = document.querySelectorAll('.sub-rsud-row[data-group="'+id+'"]');
                    var caret = this.querySelector('.sub-caret');
                    var open = children[0] && children[0].style.display !== 'none';
                    if (open) {
                        children.forEach(function(r) {
                            r.style.display = 'none';
                            var subId = r.dataset.target;
                            if (subId) {
                                document.querySelectorAll('[data-group="'+subId+'"]').forEach(function(s){ s.style.display='none'; });
                                var sc = r.querySelector('.sub-caret'); if(sc) sc.style.transform='';
                            }
                        });
                    } else {
                        children.forEach(function(r){ r.style.display=''; });
                    }
                    if (caret) caret.style.transform = open?'':'rotate(90deg)';
                });
            });

            // Klik faskes PKM → toggle PST
            document.querySelectorAll('.gab-faskes-row[data-target]').forEach(function(row) {
                row.addEventListener('click', function(e) {
                    e.stopPropagation();
                    var id = this.dataset.target;
                    var children = document.querySelectorAll('.sub-rsud-row[data-group="'+id+'"]');
                    var caret = this.querySelector('.sub-caret');
                    var open = children[0] && children[0].style.display !== 'none';
                    children.forEach(function(r){ r.style.display=open?'none':''; });
                    if (caret) caret.style.transform = open?'':'rotate(90deg)';
                });
            });
            return;
        }

        (d.data_sub || []).forEach(function(dk) {
            var dp   = dk.dalam_pwr;
            var ok   = dk.dm_ok;
            var sed  = dk.dm_sedang;
            var brt  = dk.dm_berat;
            var st   = dk.dm_statin;
            var lf   = dk.dm_ltfu3;
            var baru = dk.terdaftar_baru;
            var kota = dk.nama_wilayah;
            var kids = (d.data_sub_rsud || {})[kota] || [];
            var kid  = 'sub-' + kota.toLowerCase().replace(/[^a-z0-9]/g,'-');
            var hasC = kids.length > 0;
            var _tk  = dp + lf; // terdaftar_kumulatif = dalam_perawatan + ltfu
            var ok_pct  = _tk>0 ? Math.round(ok /_tk*100) : 0;
            var sed_pct = _tk>0 ? Math.round(sed/_tk*100) : 0;
            var brt_pct = _tk>0 ? Math.round(brt/_tk*100) : 0;
            var lf_pct  = _tk>0 ? Math.round(lf /_tk*100) : 0;
            var st_pct  = _tk>0 ? Math.round(st /_tk*100) : 0;

            tbody += '<tr class="sub-kota-row" data-target="'+kid+'" style="cursor:'+(hasC?'pointer':'default')+'">' +
                '<th class="link" style="display:flex;align-items:center;gap:.4rem">' +
                (hasC?'<span class="sub-caret" style="font-size:.7rem;transition:transform .2s">&#9654;</span>':'') +
                hesc(kota) + '</th>' +
                '<td class="number under-care bold">'+fmt(dp)+'</td>' +
                '<td class="number">'+fmt(baru)+'</td>' +
                '<td class="number">'+fmt(st)+'</td>' +
                '<td class="number">'+fmt(ok)+'</td>' +
                '<td class="bp-controlled bold">'+ok_pct+'%</td>' +
                '<td class="number">'+fmt(sed)+'</td>' +
                '<td class="bp-uncontrolled bold">'+sed_pct+'%</td>' +
                '<td class="number">'+fmt(brt)+'</td>' +
                '<td class="bp-uncontrolled bold" style="color:#7b0000">'+brt_pct+'%</td>' +
                '<td class="number">'+fmt(lf)+'</td>' +
                '<td class="three-month-ltfu bold">'+lf_pct+'%</td>' +
                '</tr>';

            kids.forEach(function(dr) {
                var ddp  = dr.dalam_pwr; var dok  = dr.dm_ok;
                var dsed = dr.dm_sedang; var dbrt = dr.dm_berat;
                var dst  = dr.dm_statin; var dlf  = dr.dm_ltfu3; var dbaru = dr.terdaftar_baru;
                var _dtk = ddp + dlf; // terdaftar_kumulatif = dalam_perawatan + ltfu
                var dok_p  = _dtk>0?Math.round(dok /_dtk*100):0;
                var dsed_p = _dtk>0?Math.round(dsed/_dtk*100):0;
                var dbrt_p = _dtk>0?Math.round(dbrt/_dtk*100):0;
                var dlf_p  = _dtk>0?Math.round(dlf /_dtk*100):0;
                var dst_p  = _dtk>0?Math.round(dst /_dtk*100):0;
                tbody += '<tr class="sub-rsud-row" data-group="'+kid+'" data-sort-method="none" style="display:none;background:#f5f9ff">' +
                    '<th class="link" style="padding-left:2rem;font-weight:normal;font-size:.85em">'+hesc(dr.nama_faskes || dr.nama_wilayah)+'</th>' +
                    '<td class="number">'+fmt(ddp)+'</td>' +
                    '<td class="number">'+fmt(dbaru)+'</td>' +
                    '<td class="number">'+fmt(dst)+'</td>' +
                    '<td class="number">'+fmt(dok)+'</td>' +
                    '<td class="bp-controlled bold">'+dok_p+'%</td>' +
                    '<td class="number">'+fmt(dsed)+'</td>' +
                    '<td class="bp-uncontrolled bold">'+dsed_p+'%</td>' +
                    '<td class="number">'+fmt(dbrt)+'</td>' +
                    '<td class="bp-uncontrolled bold" style="color:#7b0000">'+dbrt_p+'%</td>' +
                    '<td class="number">'+fmt(dlf)+'</td>' +
                    '<td class="three-month-ltfu bold">'+dlf_p+'%</td>' +
                    '</tr>';
            });
        });

        html('dm-tbl-body', tbody ||
            '<tr><td colspan="12" style="text-align:center;padding:2rem;color:#aaa;">Tidak ada data</td></tr>');

        document.querySelectorAll('.sub-kota-row[data-target]').forEach(function(row) {
            row.addEventListener('click', function() {
                var id = this.dataset.target;
                var children = document.querySelectorAll('.sub-rsud-row[data-group="'+id+'"]');
                var caret = this.querySelector('.sub-caret');
                var open = children[0] && children[0].style.display !== 'none';
                children.forEach(function(r){ r.style.display = open?'none':''; });
                if (caret) caret.style.transform = open?'':'rotate(90deg)';
            });
        });
    }

    // ── kirim tiga request paralel ───────────────────────────────────────────
    dmFetch('ajax_summary.php',
        function(d){ renderSummary(d); },
        function(e){ showErr('dm-nama-wilayah','Gagal memuat ringkasan — '+e); }
    );
    dmFetch('ajax_chart.php',
        function(d){ renderCharts(d); },
        function(e){ showErr('dm-kohort','Gagal memuat chart — '+e); }
    );
    dmFetch('ajax_table.php',
        function(d){ renderTable(d); },
        function(e){ showErr('dm-tbl-body','<tr><td colspan="12" class="dm-error">Gagal memuat tabel — '+hesc(e)+'</td></tr>'); }
    );
})();
</script>
<?php
        break;
}
