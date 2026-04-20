<?php
// ── Baca filter dari URL (diwarisi dari media.php scope) ─────────────────────
$filter_kab  = isset($url['kab'])         ? $url['kab']         : '';
$filter_kec  = isset($url['kec'])         ? $url['kec']         : '';
$filter_kel  = isset($url['kel'])         ? $url['kel']         : '';
$filter_kode = isset($url['kode_faskes']) ? $url['kode_faskes'] : '';
$filter_tipe = isset($url['tipe'])        ? $url['tipe']        : '';
if (!in_array($filter_tipe, ['puskesmas', 'rsud', 'gabungan'])) $filter_tipe = '';

// Bangun query string untuk AJAX endpoints
$_ajax_qs = http_build_query(array_filter([
    'kab'         => $filter_kab,
    'kec'         => $filter_kec,
    'kel'         => $filter_kel,
    'kode_faskes' => $filter_kode,
    'tipe'        => $filter_tipe,
]));

$_ajax_base = 'module/hipertensi/';
$_url_summary = $_ajax_base . 'ajax_summary.php' . ($_ajax_qs ? '?' . $_ajax_qs : '');
$_url_chart   = $_ajax_base . 'ajax_chart.php'   . ($_ajax_qs ? '?' . $_ajax_qs : '');
$_url_table   = $_ajax_base . 'ajax_table.php'   . ($_ajax_qs ? '?' . $_ajax_qs : '');

switch (isset($url['act']) ? $url['act'] : '') {
    case 'dashboard':
    default:
?>
    <main class="main">
      <div class="header" id="ht-header">
        <h1 id="ht-nama-wilayah"><span class="ht-skeleton" style="display:inline-block;width:200px;height:1.2em;">&nbsp;</span></h1>
        <div class="date-updated">Data: <b id="ht-label-bulan"><span class="ht-skeleton" style="display:inline-block;width:80px;height:1em;">&nbsp;</span></b></div>
      </div>

      <h2 class="columns-header">1. Indikator ringkasan</h2>

      <div class="columns-3">
        <div class="card col-span-2">
          <div class="heading">
            <h3>Pasien terlindungi dari serangan jantung dan stroke dengan perawatan kelas dunia</h3>
            <div class="figures">
              <p class="large-num bp-controlled" id="ht-bp-terkontrol"><span class="ht-skeleton" style="display:inline-block;width:120px;height:1.5em;">&nbsp;</span></p>
              <div class="detail"><p>di DKI Jakarta dengan TD &lt;140/90</p></div>
            </div>
            <div class="chart" style="height: 350px">
              <canvas id="patientsprotected"></canvas>
            </div>
          </div>
        </div>
        <div class="card">
          <div class="heading">
            <h3>Kaskade perawatan hipertensi</h3>
            <p>Perkiraan orang dewasa (&gt;15 tahun) di DKI Jakarta dengan hipertensi yang terdaftar, dalam perawatan, dan terkontrol.</p>
            <div class="info">
              <div class="info-hover-text" id="ht-kaskade-info">
                <p>Memuat data…</p>
              </div>
            </div>
          </div>
          <div class="body">
            <div class="coverage" id="ht-kaskade">
              <div class="ht-skeleton" style="height:200px;width:100%;border-radius:4px;">&nbsp;</div>
            </div>
          </div>
        </div>
      </div>

      <h2 class="columns-header spacer">2. Indikator manajemen program</h2>

      <div class="card">
        <div class="heading">
          <h3>Hasil perawatan hipertensi</h3>
          <div class="info">
            <div class="info-hover-text">
              <p><span>TD terkontrol:</span> Sistolik &lt;140 mmHg DAN diastolik &lt;90 mmHg pada kunjungan terakhir dalam 3 bulan terakhir.</p>
              <p><span>TD tidak terkontrol:</span> Sistolik &ge;140 ATAU diastolik &ge;90 pada kunjungan terakhir dalam 3 bulan terakhir.</p>
              <p><span>Tidak berkunjung 3 bulan:</span> Pasien terdaftar tanpa kunjungan dalam 3 bulan terakhir.</p>
              <p><span>Penyebut:</span> Semua pasien hipertensi terdaftar kumulatif.</p>
            </div>
          </div>
          <p id="ht-hasil-sub">Memuat data… <span class="text-grey" style="font-size:0.8em;" id="ht-label-3bln"></span></p>
          <div class="body columns-3">
            <div class="inner-card">
              <div class="figures">
                <h4 class="bp-controlled">TD terkontrol pada kunjungan terakhir dalam 3 bulan terakhir</h4>
                <p class="large-num bp-controlled" id="ht-pct-terkontrol"><span class="ht-skeleton" style="display:inline-block;width:60px;height:1.5em;">&nbsp;</span></p>
                <div class="detail"><p id="ht-num-terkontrol">&nbsp;</p></div>
              </div>
              <div class="chart"><canvas id="bpcontrolled"></canvas></div>
            </div>
            <div class="inner-card">
              <div class="figures">
                <h4 class="bp-uncontrolled">TD tidak terkontrol pada kunjungan terakhir dalam 3 bulan terakhir</h4>
                <p class="large-num bp-uncontrolled" id="ht-pct-tidak"><span class="ht-skeleton" style="display:inline-block;width:60px;height:1.5em;">&nbsp;</span></p>
                <div class="detail"><p id="ht-num-tidak">&nbsp;</p></div>
              </div>
              <div class="chart"><canvas id="bpuncontrolled"></canvas></div>
            </div>
            <div class="inner-card">
              <div class="figures">
                <h4 class="three-month-ltfu">Tidak berkunjung dalam 3 bulan terakhir</h4>
                <p class="large-num three-month-ltfu" id="ht-pct-ltfu3"><span class="ht-skeleton" style="display:inline-block;width:60px;height:1.5em;">&nbsp;</span></p>
                <div class="detail"><p id="ht-num-ltfu3">&nbsp;</p></div>
              </div>
              <div class="chart"><canvas id="ltfu3Month"></canvas></div>
            </div>
          </div>
        </div>
      </div>

      <div class="columns-3">
        <div class="card">
          <div class="heading">
            <h3>Pasien dalam perawatan</h3>
            <div class="info">
              <div class="info-hover-text">
                <p><span>Pasien dalam perawatan:</span> Pasien dengan minimal 1 kunjungan dalam 3 bulan terakhir.</p>
                <p><span>Terdaftar kumulatif:</span> Semua pasien hipertensi yang pernah terdaftar.</p>
                <p><span>Terdaftar baru:</span> Pasien dengan kunjungan pertama dalam bulan tersebut.</p>
              </div>
            </div>
            <p>Pasien hipertensi dengan minimal 1 kunjungan dalam 12 bulan terakhir</p>
          </div>
          <div class="body">
            <div class="figures">
              <div>
                <p class="large-num bp-controlled" id="ht-total-pasien"><span class="ht-skeleton" style="display:inline-block;width:80px;height:1.5em;">&nbsp;</span></p>
                <div class="detail">
                  <p class="text-grey">dari <span class="registrations" id="ht-terdaftar-kum">&nbsp;</span></p>
                </div>
              </div>
            </div>
            <div class="chart"><canvas id="registrations"></canvas></div>
          </div>
        </div>

        <div class="card">
          <div class="heading">
            <h3>Hilang tindak lanjut 12 bulan</h3>
            <div class="info">
              <div class="info-hover-text">
                <p><span>Pembilang:</span> Pasien hipertensi tanpa kunjungan dalam 12 bulan terakhir.</p>
                <p><span>Penyebut:</span> Semua pasien hipertensi terdaftar kumulatif.</p>
              </div>
            </div>
            <p>Pasien hipertensi tanpa kunjungan dalam 12 bulan terakhir</p>
          </div>
          <div class="body">
            <div class="figures">
              <p class="large-num bp-uncontrolled" id="ht-pct-ltfu12"><span class="ht-skeleton" style="display:inline-block;width:60px;height:1.5em;">&nbsp;</span></p>
              <div class="detail" id="ht-ltfu12-detail">&nbsp;</div>
            </div>
            <div class="chart"><canvas id="ltfu12Months"></canvas></div>
          </div>
        </div>

        <div class="card">
          <div class="heading">
            <h3>Skrining tekanan darah di faskes</h3>
            <div class="info">
              <div class="info-hover-text">
                <p><span>Pembilang:</span> Pasien HT dengan pengukuran TD tercatat dalam bulan ini.</p>
                <p><span>Penyebut:</span> Total pasien HT dalam perawatan (kunjungan terakhir 3 bulan).</p>
              </div>
            </div>
            <p id="ht-skrining-label">% pasien HT dengan TD tercatat pada bulan <span id="ht-skrining-bulan"></span></p>
          </div>
          <div class="body">
            <div class="figures">
              <div>
                <p class="large-num three-month-ltfu" id="ht-pct-skrining"><span class="ht-skeleton" style="display:inline-block;width:60px;height:1.5em;">&nbsp;</span></p>
                <div class="detail" id="ht-skrining-detail">&nbsp;</div>
              </div>
            </div>
            <div class="chart"><canvas id="htSkrining"></canvas></div>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="heading">
          <h3>Bandingkan sub-wilayah</h3>
          <p>Hasil perawatan untuk pasien hipertensi pada kunjungan terakhir
            <span class="text-grey" style="font-size:0.8em;" id="ht-tbl-label-3bln"></span>
          </p>
        </div>
        <div class="table-container">
          <p class="table-scroll-message text-grey mobile-only">scroll table &rarr;</p>
          <div class="table-wrap">
            <table id="table-regions">
              <colgroup>
                <col /><col span="2" /><col span="2" /><col span="2" /><col span="2" />
              </colgroup>
              <thead>
                <tr class="text-center">
                  <td></td>
                  <th scope="colgroup">Pasien dalam perawatan</th>
                  <th scope="colgroup">Pasien baru terdaftar bulanan</th>
                  <th colspan="2" scope="colgroup">TD terkontrol</th>
                  <th colspan="2" scope="colgroup">TD tidak terkontrol</th>
                  <th colspan="2" scope="colgroup">Tidak berkunjung dalam 3 bulan terakhir</th>
                </tr>
                <tr class="head-bg">
                  <th><div>Kabupaten/Kota</div></th>
                  <th class="text-right"><div>Total</div></th>
                  <th class="text-right"><div id="ht-tbl-bln-header">&nbsp;</div></th>
                  <th class="text-right"><div>Total</div></th>
                  <th data-sort-default><div>Persen</div></th>
                  <th class="text-right"><div>Total</div></th>
                  <th><div>Persen</div></th>
                  <th class="text-right"><div>Total</div></th>
                  <th><div>Persen</div></th>
                </tr>
              </thead>
              <tbody id="ht-tbl-body">
                <tr><td colspan="9" style="text-align:center;padding:2rem;color:#aaa;">Memuat data sub-wilayah…</td></tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

<script>
// Reposisi sub-RSUD rows agar selalu tepat di bawah kota induknya
function reanchorSubRows() {
    document.querySelectorAll('.sub-kota-row[data-target]').forEach(function(kotaRow) {
        var id = kotaRow.dataset.target;
        var subRows = Array.from(document.querySelectorAll('.sub-rsud-row[data-group="' + id + '"]'));
        var ref = kotaRow;
        subRows.forEach(function(sr) {
            ref.parentNode.insertBefore(sr, ref.nextSibling);
            ref = sr;
        });
    });
}
var tblRegions = document.getElementById('table-regions');
if (tblRegions) tblRegions.addEventListener('afterSort', reanchorSubRows);
</script>

      <div class="card">
        <div class="heading">
          <h3>Laporan kohort triwulanan</h3>
          <p>Hasil perawatan untuk pasien yang baru terdaftar, diukur pada akhir triwulan berikutnya</p>
        </div>
        <div class="body">
          <p class="table-scroll-message text-grey mobile-only">scroll chart &rarr;</p>
          <div class="table-wrap">
            <div class="cohort" id="ht-kohort">
              <div class="ht-skeleton" style="height:180px;width:100%;border-radius:4px;">&nbsp;</div>
            </div>
            <div class="key">
              <div class="key-text"><span class="key-color-box kohort-1"></span> TD terkontrol &lt;140/90</div>
              <div class="key-text"><span class="key-color-box kohort-2"></span> TD tidak terkontrol</div>
              <div class="key-text"><span class="key-color-box kohort-3"></span> Tidak berkunjung 3 bulan</div>
            </div>
          </div>
        </div>
      </div>

      <h2 class="columns-header spacer">3. Usia dan jenis kelamin</h2>

      <div class="card">
        <div class="heading">
          <h3>Usia dan jenis kelamin pasien hipertensi dalam perawatan</h3>
          <p><span class="under-care" id="ht-usia-sub"><strong>&nbsp;</strong></span></p>
        </div>
        <div class="body">
          <div class="table-container">
            <p class="table-scroll-message text-grey mobile-only">scroll table &rarr;</p>
            <div class="table-wrap">
              <table id="ht-usia-tbl">
                <thead>
                  <tr>
                    <th style="width:10%;border-right:0">Usia</th>
                    <th style="width:40%" class="text-right" id="ht-th-p">Perempuan</th>
                    <th style="width:40%;border-right:0" id="ht-th-l">Laki-laki</th>
                    <th style="width:10%;border-left:0">Total</th>
                  </tr>
                </thead>
                <tbody id="ht-usia-body">
                  <tr><td colspan="4" style="text-align:center;padding:2rem;color:#333;">Memuat data…</td></tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>

    </main>

<style>
.ht-skeleton {
    background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
    background-size: 200% 100%;
    animation: ht-shimmer 1.4s infinite;
    border-radius: 3px;
    color: transparent !important;
}
@keyframes ht-shimmer {
    0%   { background-position: 200% 0; }
    100% { background-position: -200% 0; }
}
.ht-error { color: #c0392b; padding: 1rem; font-style: italic; }
</style>

<script>
(function () {
    'use strict';

    var qs = <?= json_encode($_ajax_qs ? '?' . $_ajax_qs : '') ?>;

    // ── Utilitas ────────────────────────────────────────────────────────────────
    function fmt(n) { return Number(n).toLocaleString('id-ID'); }
    function clr(el) { if (el) el.innerHTML = ''; }
    function txt(id, v) { var el = document.getElementById(id); if (el) el.textContent = v; }
    function html(id, v) { var el = document.getElementById(id); if (el) el.innerHTML = v; }

    // ── Chart instances (supaya bisa di-update) ─────────────────────────────────
    var _charts = {};
    function makeOrUpdateChart(id, cfg) {
        if (_charts[id]) { _charts[id].destroy(); }
        var ctx = document.getElementById(id);
        if (!ctx) return;
        _charts[id] = new Chart(ctx, cfg);
    }

    // ── Fungsi render per bagian ────────────────────────────────────────────────
    function renderSummary(d) {
        txt('ht-nama-wilayah', d.nama_wilayah);
        txt('ht-label-bulan', d.label_bulan);
        txt('ht-skrining-bulan', d.label_bulan);

        html('ht-bp-terkontrol', fmt(d.bp_terkontrol) + ' pasien');
        txt('ht-pct-terkontrol', d.pct_terkontrol + '%');
        html('ht-num-terkontrol', '<strong>' + fmt(d.bp_terkontrol) + '</strong> pasien dengan TD &lt;140/90');
        txt('ht-pct-tidak', d.pct_tidak + '%');
        html('ht-num-tidak', '<strong>' +  fmt(d.bp_tidak) + '</strong> pasien dengan TD &ge;140/90');
        txt('ht-pct-ltfu3', d.pct_ltfu3 + '%');
        html('ht-num-ltfu3', '<strong>' +  fmt(d.ltfu_3bln) + '</strong> pasien tanpa kunjungan');
        txt('ht-pct-ltfu12', d.pct_ltfu12 + '%');
        html('ht-ltfu12-detail',
            '<p> <strong>' + fmt(d.ltfu_12bln) + '</strong> pasien tanpa kunjungan 12 bulan</p>' +
            '<p class="text-grey">dari <span class="registrations">' + '<strong>' + fmt(d.terdaftar_alltime || 0) + '</strong> pasien terdaftar kumulatif</span></p>');

        html('ht-hasil-sub', 'Hasil untuk ' + fmt(d.terdaftar_den || 0) + ' pasien (terdaftar sebelum 3 bulan lalu)');
        txt('ht-label-3bln', '· ' + d.label_3bln);
        txt('ht-tbl-label-3bln', '· ' + d.label_3bln);
        txt('ht-tbl-bln-header', d.label_bulan);

        html('ht-total-pasien', fmt(d.total_pasien));
        html('ht-terdaftar-kum', '<strong>' + fmt(d.terdaftar_kumulatif) + '</strong> pasien dalam perawatan (12 bln)');

        txt('ht-pct-skrining', d.pct_skrining + '%');
        html('ht-skrining-detail',
            '<p> <strong>' + fmt(d.skrining_bln) + '</strong> pasien diskrining pada ' + d.label_bulan + '</p>' +
            '<p class="text-grey">dari <span class="registrations">' + '<strong>' +  fmt(d.total_pasien) + '</strong> dalam perawatan</span></p>');

        // Kaskade: Level1=semua terdaftar (all-time), Level2=dalam perawatan (12mo), Level3=terkontrol (3mo)
        var _hasData = (d.terdaftar_alltime || 0) > 0;
        html('ht-kaskade',
            '<div class="coverage-column">' +
              '<div class="coverage-bar"><div class="coverage-bar-fill registrations-bg" style="height:' + (_hasData ? '100' : '0') + '%">' +
                '<p class="coverage-number registrations">' + (_hasData ? '100' : '0') + '%</p></div></div>' +
              '<p><strong>' + fmt(d.terdaftar_alltime || 0) + '</strong></p>' +
              '<p class="text-grey label-small">Pasien terdaftar kumulatif</p>' +
            '</div>' +
            '<div class="coverage-column">' +
              '<div class="coverage-bar"><div class="coverage-bar-fill under-care-bg" style="height:' + d.pct_perawatan + '%">' +
                '<p class="coverage-number under-care">' + d.pct_perawatan + '%</p></div></div>' +
              '<p><strong>' + fmt(d.terdaftar_kumulatif) + '</strong></p>' +
              '<p class="text-grey label-small">Pasien dalam perawatan (12 bln)</p>' +
            '</div>' +
            '<div class="coverage-column">' +
              '<div class="coverage-bar"><div class="coverage-bar-fill bp-controlled-bg" style="height:' + d.pct_terkontrol_pop + '%">' +
                '<p class="coverage-number bp-controlled">' + d.pct_terkontrol_pop + '%</p></div></div>' +
              '<p><strong>' + fmt(d.bp_terkontrol) + '</strong></p>' +
              '<p class="text-grey label-small">Pasien dengan TD terkontrol</p>' +
            '</div>');

        html('ht-kaskade-info',
            '<p><b>Sumber data:</b> Dinas Kesehatan Provinsi DKI Jakarta.</p>' +
            '<p><span>Terdaftar kumulatif:</span> ' + fmt(d.terdaftar_alltime || 0) + ' pasien. Basis 100%.</p>' +
            '<p><span>Dalam perawatan (12 bln):</span> ' + fmt(d.terdaftar_kumulatif) + ' pasien (' + d.pct_perawatan + '%).</p>' +
            '<p><span>TD terkontrol:</span> ' + fmt(d.bp_terkontrol) + ' pasien (' + d.pct_terkontrol_pop + '%).</p>');

        // Tabel usia
        html('ht-th-p', 'Perempuan (' + d.pct_perempuan + '%)');
        html('ht-th-l', 'Laki-laki (' + d.pct_laki + '%)');
        txt('ht-usia-sub', fmt(d.total_pasien) + ' pasien dalam perawatan');

        var rows = [
            ['18-29', d.pct_p_18_29, d.pct_l_18_29, d.pct_t_18_29,
             Math.round(d.pct_p_18_29 / Math.max(d.max_p, 1) * 100),
             Math.round(d.pct_l_18_29 / Math.max(d.max_l, 1) * 100)],
            ['30-49', d.pct_p_30_49, d.pct_l_30_49, d.pct_t_30_49,
             Math.round(d.pct_p_30_49 / Math.max(d.max_p, 1) * 100),
             Math.round(d.pct_l_30_49 / Math.max(d.max_l, 1) * 100)],
            ['50-69', d.pct_p_50_69, d.pct_l_50_69, d.pct_t_50_69,
             Math.round(d.pct_p_50_69 / Math.max(d.max_p, 1) * 100),
             Math.round(d.pct_l_50_69 / Math.max(d.max_l, 1) * 100)],
            ['70+',   d.pct_p_70,    d.pct_l_70,    d.pct_t_70,
             Math.round(d.pct_p_70   / Math.max(d.max_p, 1) * 100),
             Math.round(d.pct_l_70   / Math.max(d.max_l, 1) * 100)],
        ];
        var tbody = '';
        rows.forEach(function(r) {
            tbody += '<tr>' +
                '<th style="border-right:0">' + r[0] + '</th>' +
                '<td class="text-right"><div style="border-radius:3px;padding:4px 8px;color:rgba(0,0,0,0.6);background:rgba(42,143,130,0.6);width:' + r[4] + '%;display:inline-block;">' + r[1] + '%</div></td>' +
                '<td style="border-right:0"><div style="border-radius:3px;padding:4px 8px;color:rgba(0,0,0,0.6);background:rgba(36,85,115,0.6);width:' + r[5] + '%;display:inline-block;">' + r[2] + '%</div></td>' +
                '<td style="border-left:0" class="text-right">' + r[3] + '%</td>' +
                '</tr>';
        });
        html('ht-usia-body', tbody);
    }

    function renderCharts(d) {
        if (typeof window.htRenderCharts === 'function') {
            window.htChartData = {
                labels: d.labels, bp_ok: d.bp_ok, bp_no: d.bp_no,
                ltfu3: d.ltfu3, ltfu12: d.ltfu12, terdaftar: d.terdaftar,
                dalam_pwr: d.dalam_pwr, baru: d.baru, protected: d.protected,
                skrining: d.skrining, skrining_pct: d.skrining_pct,
            };
            window.htRenderCharts();
        } else {
            window.htChartData = {
                labels: d.labels, bp_ok: d.bp_ok, bp_no: d.bp_no,
                ltfu3: d.ltfu3, ltfu12: d.ltfu12, terdaftar: d.terdaftar,
                dalam_pwr: d.dalam_pwr, baru: d.baru, protected: d.protected,
                skrining: d.skrining, skrining_pct: d.skrining_pct,
            };
        }

        // Kohort
        var kohortEl = document.getElementById('ht-kohort');
        if (!kohortEl) return;
        if (!d.data_kohort || !d.data_kohort.length) {
            kohortEl.innerHTML = '<p class="text-grey" style="padding:1rem;font-style:italic;">Belum ada data kohort.</p>';
            return;
        }
        var khtml = '';
        d.data_kohort.forEach(function(kh) {
            var tot = kh.total;
            var ok  = tot > 0 ? Math.round(kh.bp_ok / tot * 100) : 0;
            var no  = tot > 0 ? Math.round(kh.bp_no / tot * 100) : 0;
            var lf  = tot > 0 ? Math.round(kh.ltfu3 / tot * 100) : 0;
            khtml += '<div class="cohort-quarter">' +
                '<div class="cohort-bar">' +
                  '<div class="segment kohort-1"    style="height:' + ok + '%">' + ok + '%</div>' +
                  '<div class="segment kohort-2"  style="height:' + no + '%">' + no + '%</div>' +
                  '<div class="segment kohort-3" style="height:' + lf + '%">' + lf + '%</div>' +
                '</div>' +
                '<div class="cohort-detail"><b>' + kh.label + '</b> ' + fmt(tot) + ' pasien</div>' +
                '</div>';
        });
        kohortEl.innerHTML = khtml;
    }

    function renderTable(d) {
        var tbody = '';

        // ── Mode Gabungan: dikelompokkan per Kabupaten → RSUD/PKM → PST ──────────
        if (d.data_gabungan) {
            // Indeks data_sub per kabupaten untuk baris ringkasan kota
            var _subByKab = {};
            (d.data_sub || []).forEach(function(dk) { _subByKab[dk.nama_wilayah] = dk; });

            // Kelompokkan faskes per kabupaten (urutan dari data_gabungan sudah sorted)
            var _byKab = {}, _kabOrder = [];
            d.data_gabungan.forEach(function(fs) {
                if (!_byKab[fs.nama_kab]) { _byKab[fs.nama_kab] = []; _kabOrder.push(fs.nama_kab); }
                _byKab[fs.nama_kab].push(fs);
            });

            _kabOrder.forEach(function(kab) {
                var dk   = _subByKab[kab] || {};
                var tot  = dk.total_pasien || 0;
                var bpok = dk.bp_terkontrol || 0;
                var bpno = dk.bp_tidak_terkontrol || 0;
                var ltfu = dk.ltfu_3bulan || 0;
                var _tk  = dk.terdaftar_kumulatif || 0;
                var pok  = _tk > 0 ? Math.round(bpok / _tk * 100) : 0;
                var pno  = _tk > 0 ? Math.round(bpno / _tk * 100) : 0;
                var plf  = _tk > 0 ? Math.round(ltfu / _tk * 100) : 0;
                var kabId = 'kab-' + kab.toLowerCase().replace(/[^a-z0-9]/gi, '-');

                // Baris kabupaten (selalu bisa di-expand)
                tbody += '<tr class="sub-kota-row" data-target="' + kabId + '" style="cursor:pointer;background:#eef2ff">' +
                    '<th class="link" style="display:flex;align-items:center;gap:.4rem;font-weight:700">' +
                    '<span class="sub-caret" style="font-size:.7rem;transition:transform .2s">&#9654;</span>' +
                    htmlEscape(kab) + '</th>' +
                    '<td class="number under-care bold">' + fmt(tot) + '</td>' +
                    '<td class="number">' + fmt(dk.terdaftar_baru || 0) + '</td>' +
                    '<td class="number">' + fmt(bpok) + '</td>' +
                    '<td class="bp-controlled bold">' + pok + '%</td>' +
                    '<td class="number">' + fmt(bpno) + '</td>' +
                    '<td class="bp-uncontrolled bold">' + pno + '%</td>' +
                    '<td class="number">' + fmt(ltfu) + '</td>' +
                    '<td class="three-month-ltfu bold">' + plf + '%</td>' +
                    '</tr>';

                // Baris faskes di bawah kabupaten (tersembunyi, data-group=kabId)
                _byKab[kab].forEach(function(fs) {
                    var ft    = fs.total_pasien;
                    var ftk   = fs.terdaftar_kumulatif || 0;
                    var fbpok = fs.bp_terkontrol;
                    var fbpno = fs.bp_tidak_terkontrol;
                    var fltfu = fs.ltfu_3bulan;
                    var fpok = ftk > 0 ? Math.round(fbpok / ftk * 100) : 0;
                    var fpno = ftk > 0 ? Math.round(fbpno / ftk * 100) : 0;
                    var fplf = ftk > 0 ? Math.round(fltfu / ftk * 100) : 0;
                    var hasPst = fs.pst && fs.pst.length > 0;
                    var kid  = 'gab-' + String(fs.kode).replace(/[^a-z0-9]/gi, '-');
                    var tipeLabel = fs.tipe === 'RSUD'
                        ? '<span style="font-size:.7em;background:#e8f0fe;color:#1a56db;border-radius:3px;padding:1px 5px;margin-right:4px">RSUD</span>'
                        : '<span style="font-size:.7em;background:#def7ec;color:#03543f;border-radius:3px;padding:1px 5px;margin-right:4px">PKM</span>';

                    tbody += '<tr class="sub-rsud-row gab-faskes-row" data-group="' + kabId + '"' +
                        (hasPst ? ' data-target="' + kid + '"' : '') +
                        ' data-sort-method="none" style="display:none;cursor:' + (hasPst ? 'pointer' : 'default') + '">' +
                        '<th class="link" style="padding-left:1.5rem;display:flex;align-items:center;gap:.4rem;font-weight:normal">' +
                        (hasPst ? '<span class="sub-caret" style="font-size:.7rem;transition:transform .2s">&#9654;</span>' : '') +
                        tipeLabel + htmlEscape(fs.nama_wilayah) + '</th>' +
                        '<td class="number">' + fmt(ft) + '</td>' +
                        '<td class="number">' + fmt(fs.terdaftar_baru) + '</td>' +
                        '<td class="number">' + fmt(fbpok) + '</td>' +
                        '<td class="bp-controlled bold">' + fpok + '%</td>' +
                        '<td class="number">' + fmt(fbpno) + '</td>' +
                        '<td class="bp-uncontrolled bold">' + fpno + '%</td>' +
                        '<td class="number">' + fmt(fltfu) + '</td>' +
                        '<td class="three-month-ltfu bold">' + fplf + '%</td>' +
                        '</tr>';

                    if (hasPst) {
                        fs.pst.forEach(function(pst) {
                            var pt   = pst.total_pasien;
                            var ptk  = pst.terdaftar_kumulatif || 0;
                            var ppok = ptk > 0 ? Math.round(pst.bp_terkontrol       / ptk * 100) : 0;
                            var ppno = ptk > 0 ? Math.round(pst.bp_tidak_terkontrol / ptk * 100) : 0;
                            var pplf = ptk > 0 ? Math.round(pst.ltfu_3bulan         / ptk * 100) : 0;
                            tbody += '<tr class="sub-rsud-row" data-group="' + kid + '" data-sort-method="none" style="display:none;background:#f5f9ff">' +
                                '<th class="link" style="padding-left:3rem;font-weight:normal;font-size:.85em">' +
                                '<span style="font-size:.7em;background:#fdf6b2;color:#723b13;border-radius:3px;padding:1px 5px;margin-right:4px">PST</span>' +
                                htmlEscape(pst.nama_wilayah) + '</th>' +
                                '<td class="number">' + fmt(pt) + '</td>' +
                                '<td class="number">' + fmt(pst.terdaftar_baru) + '</td>' +
                                '<td class="number">' + fmt(pst.bp_terkontrol) + '</td>' +
                                '<td class="bp-controlled bold">' + ppok + '%</td>' +
                                '<td class="number">' + fmt(pst.bp_tidak_terkontrol) + '</td>' +
                                '<td class="bp-uncontrolled bold">' + ppno + '%</td>' +
                                '<td class="number">' + fmt(pst.ltfu_3bulan) + '</td>' +
                                '<td class="three-month-ltfu bold">' + pplf + '%</td>' +
                                '</tr>';
                        });
                    }
                });
            });

            html('ht-tbl-body', tbody || '<tr><td colspan="9" style="text-align:center;padding:2rem;color:#aaa;">Tidak ada data</td></tr>');

            // Klik kabupaten → toggle faskes di bawahnya
            document.querySelectorAll('.sub-kota-row[data-target]').forEach(function(row) {
                row.addEventListener('click', function() {
                    var id = this.dataset.target;
                    var children = document.querySelectorAll('.sub-rsud-row[data-group="' + id + '"]');
                    var caret = this.querySelector('.sub-caret');
                    var open = children[0] && children[0].style.display !== 'none';
                    // Saat menutup, sembunyikan juga cucu PST
                    if (open) {
                        children.forEach(function(r) {
                            r.style.display = 'none';
                            var subId = r.dataset.target;
                            if (subId) {
                                document.querySelectorAll('[data-group="' + subId + '"]').forEach(function(s) { s.style.display = 'none'; });
                                var sc = r.querySelector('.sub-caret'); if (sc) sc.style.transform = '';
                            }
                        });
                    } else {
                        children.forEach(function(r) { r.style.display = ''; });
                    }
                    if (caret) caret.style.transform = open ? '' : 'rotate(90deg)';
                });
            });

            // Klik faskes PKM → toggle PST di bawahnya
            document.querySelectorAll('.gab-faskes-row[data-target]').forEach(function(row) {
                row.addEventListener('click', function(e) {
                    e.stopPropagation();
                    var id = this.dataset.target;
                    var children = document.querySelectorAll('.sub-rsud-row[data-group="' + id + '"]');
                    var caret = this.querySelector('.sub-caret');
                    var open = children[0] && children[0].style.display !== 'none';
                    children.forEach(function(r) { r.style.display = open ? 'none' : ''; });
                    if (caret) caret.style.transform = open ? '' : 'rotate(90deg)';
                });
            });
            return;
        }

        (d.data_sub || []).forEach(function(dk) {
            var tot   = dk.total_pasien;
            var _tk   = dk.terdaftar_kumulatif || 0;
            var bpok  = dk.bp_terkontrol;
            var bpno  = dk.bp_tidak_terkontrol;
            var ltfu  = dk.ltfu_3bulan;
            var pok   = _tk > 0 ? Math.round(bpok / _tk * 100) : 0;
            var pno   = _tk > 0 ? Math.round(bpno / _tk * 100) : 0;
            var plf   = _tk > 0 ? Math.round(ltfu / _tk * 100) : 0;
            var kota  = dk.nama_wilayah;
            var kids  = (d.data_sub_rsud || {})[kota] || [];
            var kid   = 'sub-' + kota.toLowerCase().replace(/[^a-z0-9]/g, '-');
            var hasC  = kids.length > 0;

            tbody += '<tr class="sub-kota-row" data-target="' + kid + '" style="cursor:' + (hasC ? 'pointer' : 'default') + '">' +
                '<th class="link" style="display:flex;align-items:center;gap:.4rem">' +
                (hasC ? '<span class="sub-caret" style="font-size:.7rem;transition:transform .2s">&#9654;</span>' : '') +
                htmlEscape(kota) + '</th>' +
                '<td class="number under-care bold">' + fmt(tot) + '</td>' +
                '<td class="number">' + fmt(dk.terdaftar_baru) + '</td>' +
                '<td class="number">' + fmt(bpok) + '</td>' +
                '<td class="bp-controlled bold">' + pok + '%</td>' +
                '<td class="number">' + fmt(bpno) + '</td>' +
                '<td class="bp-uncontrolled bold">' + pno + '%</td>' +
                '<td class="number">' + fmt(ltfu) + '</td>' +
                '<td class="three-month-ltfu bold">' + plf + '%</td>' +
                '</tr>';

            kids.forEach(function(dr) {
                var dt  = dr.total_pasien;
                var dlf = dr.ltfu_3bulan;
                var _dtk = dt + dlf; // terdaftar_kumulatif = dalam_perawatan + ltfu
                var dok = dr.bp_terkontrol;
                var dno = dr.bp_tidak_terkontrol;
                var dpok = _dtk > 0 ? Math.round(dok / _dtk * 100) : 0;
                var dpno = _dtk > 0 ? Math.round(dno / _dtk * 100) : 0;
                var dplf = _dtk > 0 ? Math.round(dlf / _dtk * 100) : 0;
                tbody += '<tr class="sub-rsud-row" data-group="' + kid + '" data-sort-method="none" style="display:none;background:#f5f9ff">' +
                    '<th class="link" style="padding-left:2rem;font-weight:normal;font-size:.85em">' + htmlEscape(dr.nama_wilayah) + '</th>' +
                    '<td class="number">' + fmt(dt) + '</td>' +
                    '<td class="number">' + fmt(dr.terdaftar_baru) + '</td>' +
                    '<td class="number">' + fmt(dok) + '</td>' +
                    '<td class="bp-controlled bold">' + dpok + '%</td>' +
                    '<td class="number">' + fmt(dno) + '</td>' +
                    '<td class="bp-uncontrolled bold">' + dpno + '%</td>' +
                    '<td class="number">' + fmt(dlf) + '</td>' +
                    '<td class="three-month-ltfu bold">' + dplf + '%</td>' +
                    '</tr>';
            });
        });

        html('ht-tbl-body', tbody || '<tr><td colspan="9" style="text-align:center;padding:2rem;color:#aaa;">Tidak ada data</td></tr>');

        // Pasang event expand/collapse
        document.querySelectorAll('.sub-kota-row[data-target]').forEach(function(row) {
            row.addEventListener('click', function() {
                var id = this.dataset.target;
                var children = document.querySelectorAll('.sub-rsud-row[data-group="' + id + '"]');
                var caret = this.querySelector('.sub-caret');
                var open = children[0] && children[0].style.display !== 'none';
                children.forEach(function(r) { r.style.display = open ? 'none' : ''; });
                if (caret) caret.style.transform = open ? '' : 'rotate(90deg)';
            });
        });
    }

    function htmlEscape(s) {
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    function showError(id, msg) {
        var el = document.getElementById(id);
        if (el) el.innerHTML = '<span class="ht-error">' + msg + '</span>';
    }

    // ── Kirim tiga request paralel (native fetch, tanpa jQuery) ─────────────────
    var baseUrl = '<?= htmlspecialchars($_ajax_base, ENT_QUOTES) ?>';

    function htFetch(endpoint, onDone, onFail) {
        fetch(baseUrl + endpoint + qs)
            .then(function(res) { return res.json(); })
            .then(function(d) {
                if (d && d.error) { onFail('Error server: ' + d.error); return; }
                onDone(d);
            })
            .catch(function(err) { onFail(String(err)); });
    }

    htFetch('ajax_summary.php',
        function(d) { renderSummary(d); },
        function(e) { showError('ht-nama-wilayah', 'Gagal memuat ringkasan — ' + e); }
    );
    htFetch('ajax_chart.php',
        function(d) { renderCharts(d); },
        function(e) { showError('ht-kohort', 'Gagal memuat chart — ' + e); }
    );
    htFetch('ajax_table.php',
        function(d) { renderTable(d); },
        function(e) { showError('ht-tbl-body', '<tr><td colspan="9" class="ht-error">Gagal memuat tabel — ' + htmlEscape(e) + '</td></tr>'); }
    );
})();
</script>
<?php
        break;
}
