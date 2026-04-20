// setup semua chart interaktif pakai chartJS - https://www.chartjs.org/

// plugin garis vertikal pas hover
const dynamicChartSegementDashed = (
  ctx,
  numberOfXAxisTicks,
  numberOfDashedSegments = 1,
) => {
  const dashStyle = [4, 3];
  const segmentStartIndex = ctx.p0DataIndex;
  return isSegmentDashed(
    segmentStartIndex,
    numberOfXAxisTicks,
    numberOfDashedSegments,
  )
    ? dashStyle
    : undefined;
};

function isSegmentDashed(
  segmentStartIndex,
  numberOfXAxisTicks,
  segmentsToDashFromEnd,
) {
  return segmentStartIndex >= numberOfXAxisTicks - (segmentsToDashFromEnd + 1);
}

function dashboardReportsChartJSColors() {
  return {
    darkGreen: "rgba(0, 122, 49, 1)",
    mediumGreen: "rgba(0, 184, 73, 1)",
    lightGreen: "rgba(242, 248, 245, 0.5)",
    darkRed: "rgba(184, 22, 49, 1)",
    mediumRed: "rgba(255, 51, 85, 1)",
    lightRed: "rgba(255, 235, 238, 0.5)",
    darkPurple: "rgba(83, 0, 224, 1)",
    lightPurple: "rgba(169, 128, 239, 0.5)",
    darkBlue: "rgba(12, 57, 102, 1)",
    mediumBlue: "rgba(0, 117, 235, 1)",
    lightBlue: "rgba(233, 243, 255, 0.75)",
    darkGrey: "rgba(108, 115, 122, 1)",
    mediumGrey: "rgba(173, 178, 184, 1)",
    lightGrey: "rgba(240, 242, 245, 0.9)",
    white: "rgba(255, 255, 255, 1)",
    amber: "rgba(250, 190, 70, 1)",
    darkAmber: "rgba(223, 165, 50, 1)",
    transparent: "rgba(0, 0, 0, 0)",
    teal: "rgba(48, 184, 166, 1)",
    darkTeal: "rgba(34,140,125,1)",
    maroon: "rgba(71, 0, 0, 1)",
    darkMaroon: "rgba(60,0,0,1)",
    orange: "rgb(223,104,15)",
    lightOrange: "rgba(255,156,8,0.15)",
  };
}

function baseLineChartConfig() {
  const colors = dashboardReportsChartJSColors();
  return {
    type: "line",
    options: {
      animation: false,
      clip: false,
      maintainAspectRatio: false,
      layout: {
        padding: {
          left: 0,
          right: 0,
          top: 26,
          bottom: 0,
        },
      },
      elements: {
        point: {
          pointStyle: "circle",
          pointBackgroundColor: colors.white,
          hoverBackgroundColor: colors.white,
          borderWidth: 2,
          hoverRadius: 4,
          hoverBorderWidth: 2,
        },
        line: {
          tension: 0.4,
          borderWidth: 2,
          fill: true,
        },
      },
      interaction: {
        mode: "index",
        intersect: false,
      },
      plugins: {
        legend: {
          display: false,
        },
        tooltip: {
          // enabled: false,
          displayColors: false,
          caretPadding: 6,
        },
      },
      scales: {
        x: {
          stacked: false,
          grid: {
            display: false,
          },
          ticks: {
            autoSkip: false,
            color: colors.darkGrey,
            font: {
              family: "var(--system-ui)",
              size: 11,
            },
            padding: 6,
            showLabelBackdrop: true,
          },
          beginAtZero: true,
          min: 0,
        },
        y: {
          stacked: false,
          border: {
            display: false,
          },
          ticks: {
            autoSkip: false,
            color: colors.darkGrey,
            font: {
              // family: "var(--system-ui)",
              size: 10,
            },
            padding: 8,
            stepSize: 25,
          },
          beginAtZero: true,
          min: 0,
          max: 100,
        },
      },
    },
    plugins: [intersectDataVerticalLine],
  };
}

const intersectDataVerticalLine = {
  id: "intersectDataVerticalLine",
  beforeDraw: (chart) => {
    if (chart.tooltip._active && chart.tooltip._active.length) {
      const ctx = chart.ctx;
      ctx.save();
      const activePoint = chart.tooltip._active[0];
      const chartArea = chart.chartArea;
      // garis abu-abu pas hover, sepanjang chart
      ctx.beginPath();
      ctx.moveTo(activePoint.element.x, chartArea.top);
      ctx.lineTo(activePoint.element.x, chartArea.bottom);
      ctx.lineWidth = 2;
      ctx.strokeStyle = "rgba(0,0,0, 0.1)";
      ctx.stroke();
      ctx.restore();
      // garis berwarna dari titik ke bawah, khusus kalau cuma 1 dataset
      if (chart.tooltip._active.length === 1) {
        ctx.beginPath();
        ctx.moveTo(activePoint.element.x, activePoint.element.y);
        ctx.lineTo(activePoint.element.x, chartArea.bottom);
        ctx.lineWidth = 2;
        ctx.stroke();
        ctx.restore();
      }
    }
  },
};

function createChart(ctx, config) {
  new Chart(ctx, config);
}

// dipakai oleh chart HT maupun DM — harus di module scope
const percentageLabel = (context) =>
  `${context.dataset.label}: ${context.parsed.y}%`;

// grafik hipertensi — dibungkus fungsi agar bisa dipanggil setelah AJAX selesai
window.htRenderCharts = function () {
  // grafik TD terkontrol (hipertensi)
  const _ht =
    typeof window.htChartData !== "undefined" ? window.htChartData : null;
  const _htLabels = _ht ? _ht.labels : [];
  const _n = _htLabels.length;

  // chart BP & ltfu3 hanya tampilkan 3 bulan terakhir (sesuai window pengukuran)
  const _htLabels3 = _htLabels.slice(-3);
  const _n3 = _htLabels3.length;

  const bpControlledData = {
    labels: _htLabels3,
    datasets: [
      {
        label: "Tekanan darah terkontrol",
        data: _ht ? _ht.bp_ok.slice(-3) : [],
        borderColor: "#0f652e",
        backgroundColor: "rgba(15, 101, 46, 0.2)",
        segment: { borderDash: (ctx) => dynamicChartSegementDashed(ctx, _n3) },
      },
    ],
  };
  const bpControlledConfig = baseLineChartConfig();
  bpControlledConfig.data = bpControlledData;
  bpControlledConfig.options.scales.y.ticks.callback = (val) => val + "%";
  bpControlledConfig.options.plugins.tooltip.callbacks = {
    label: percentageLabel,
  };
  const bpControlledCanvas = document.getElementById("bpcontrolled");
  if (bpControlledCanvas) {
    createChart(bpControlledCanvas, bpControlledConfig);
  }

  // grafik TD tidak terkontrol (hipertensi)
  const bpUncontrolledData = {
    labels: _htLabels3,
    datasets: [
      {
        label: "Tekanan darah tidak terkontrol",
        data: _ht ? _ht.bp_no.slice(-3) : [],
        borderColor: "#218267",
        backgroundColor: "rgba(33, 130, 103, 0.2)",
        segment: { borderDash: (ctx) => dynamicChartSegementDashed(ctx, _n3) },
      },
    ],
  };
  const bpUncontrolledConfig = baseLineChartConfig();
  bpUncontrolledConfig.data = bpUncontrolledData;
  bpUncontrolledConfig.options.plugins.tooltip.callbacks = {
    label: percentageLabel,
  };
  bpUncontrolledConfig.options.scales.y.ticks.callback = (val) => val + "%";
  const bpUncontrolledCanvas = document.getElementById("bpuncontrolled");
  if (bpUncontrolledCanvas) {
    createChart(bpUncontrolledCanvas, bpUncontrolledConfig);
  }

  // grafik tidak berkunjung 3 bulan (hipertensi)
  const ltfu3MonthData = {
    labels: _htLabels3,
    datasets: [
      {
        label: "Tidak berkunjung dalam 3 bulan terakhir",
        data: _ht ? _ht.ltfu3.slice(-3) : [],
        borderColor: "#2a8f82",
        backgroundColor: "rgba(42, 143, 130, 0.2)",
        segment: { borderDash: (ctx) => dynamicChartSegementDashed(ctx, _n) },
      },
    ],
  };
  const ltfu3MonthConfig = baseLineChartConfig();
  ltfu3MonthConfig.data = ltfu3MonthData;
  ltfu3MonthConfig.options.plugins.tooltip.callbacks = {
    label: percentageLabel,
  };
  ltfu3MonthConfig.options.scales.y.ticks.callback = (val) => val + "%";
  const ltfu3MonthCanvas = document.getElementById("ltfu3Month");
  if (ltfu3MonthCanvas) {
    createChart(ltfu3MonthCanvas, ltfu3MonthConfig);
  }

  // grafik pendaftaran pasien hipertensi
  const _maxTerdaftar = _ht ? Math.max(..._ht.terdaftar) : 1;
  const _maxBaru = _ht ? Math.max(..._ht.baru) : 1;
  const registrationsData = {
    labels: _htLabels,
    datasets: [
      {
        label: "Registrasi kumulatif",
        data: _ht ? _ht.terdaftar : [],
        borderColor: "#2a8f82",
        backgroundColor: "transparent",
        yAxisID: "y",
      },
      {
        label: "Pasien dalam perawatan",
        data: _ht ? _ht.dalam_pwr : [],
        borderColor: "#245573",
        backgroundColor: "transparent",
        yAxisID: "y",
      },
      {
        type: "bar",
        label: "Registrasi bulanan",
        data: _ht ? _ht.baru : [],
        borderColor: "#329d9e",
        backgroundColor: "#329d9e",
        yAxisID: "yMonthlyRegistrations",
      },
    ],
  };
  const registrationsConfig = baseLineChartConfig();
  registrationsConfig.data = registrationsData;
  registrationsConfig.options.scales.y.grid = { drawTicks: false };
  registrationsConfig.options.scales.y.ticks.display = false;
  registrationsConfig.options.scales.y.ticks.count = 3;
  registrationsConfig.options.scales.y.max = _maxTerdaftar;
  registrationsConfig.options.scales.yMonthlyRegistrations = {
    display: false,
    beginAtZero: true,
    max: _maxBaru,
  };
  registrationsConfig.options.plugins.tooltip.displayColors = true;
  registrationsConfig.options.plugins.tooltip.callbacks = {
    labelColor: (ctx) => ({
      borderColor: "#fff",
      backgroundColor: ctx.dataset.borderColor,
      borderWidth: 1,
    }),
  };
  const registrationsCanvas = document.getElementById("registrations");
  if (registrationsCanvas) {
    createChart(registrationsCanvas, registrationsConfig);
  }

  // grafik hilang tindak lanjut 12 bulan (hipertensi)
  const ltfu12MonthsData = {
    labels: _htLabels,
    datasets: [
      {
        label: "Hilang tindak lanjut 12 bulan",
        data: _ht ? _ht.ltfu12 : [],
        borderColor: "#2a8f82",
        backgroundColor: "rgba(42,143,130,0.2)",
        segment: { borderDash: (ctx) => dynamicChartSegementDashed(ctx, _n) },
      },
    ],
  };
  const ltfu12MonthsConfig = baseLineChartConfig();
  ltfu12MonthsConfig.data = ltfu12MonthsData;
  ltfu12MonthsConfig.options.plugins.tooltip.callbacks = {
    label: percentageLabel,
  };
  ltfu12MonthsConfig.options.scales.y.ticks.callback = (val) => val + "%";
  const ltfu12MonthsCanvas = document.getElementById("ltfu12Months");
  if (ltfu12MonthsCanvas) {
    createChart(ltfu12MonthsCanvas, ltfu12MonthsConfig);
  }
}; // end window.htRenderCharts part 1

// ── Grafik DM — dibungkus agar bisa dipanggil setelah AJAX selesai ───────────
window.dmRenderCharts = function () {
  var _dmL = typeof window._dmLabels !== "undefined" ? window._dmLabels : [];
  var _dmD = typeof window._dm !== "undefined" ? window._dm : {};
  var _dmL3 = typeof window._dmLabels3 !== "undefined" ? window._dmLabels3 : [];
  var _dmNv = _dmL.length;
  var _dmN3v = _dmL3.length;

  // dmregistrations — registrasi kumulatif + dalam perawatan + bulanan
  var _maxDmReg =
    _dmD.terdaftar && _dmD.terdaftar.length
      ? Math.max(..._dmD.terdaftar, 1)
      : 1;
  var _maxDmBaru =
    _dmD.baru && _dmD.baru.length ? Math.max(..._dmD.baru, 1) : 1;
  var dmRegistrationsConfig = baseLineChartConfig();
  dmRegistrationsConfig.data = {
    labels: _dmL,
    datasets: [
      {
        label: "Registrasi kumulatif",
        data: _dmD.terdaftar || [],
        borderColor: "#218267",
        backgroundColor: "transparent",
        yAxisID: "y",
      },
      {
        label: "Pasien dalam perawatan",
        data: _dmD.dalam_pwr || [],
        borderColor: "#b51bdc",
        backgroundColor: "transparent",
        yAxisID: "y",
      },
      {
        type: "bar",
        label: "Registrasi bulanan",
        data: _dmD.baru || [],
        borderColor: "#cfe5e4",
        backgroundColor: "#cfe5e4",
        yAxisID: "yMonthlyRegistrations",
      },
    ],
  };
  dmRegistrationsConfig.options.scales.y.grid = { drawTicks: false };
  dmRegistrationsConfig.options.scales.y.ticks.display = false;
  dmRegistrationsConfig.options.scales.y.ticks.count = 3;
  dmRegistrationsConfig.options.scales.y.max = _maxDmReg;
  dmRegistrationsConfig.options.scales.yMonthlyRegistrations = {
    display: false,
    beginAtZero: true,
    max: _maxDmBaru,
  };
  dmRegistrationsConfig.options.plugins.tooltip.displayColors = true;
  dmRegistrationsConfig.options.plugins.tooltip.callbacks = {
    labelColor: (ctx) => ({
      borderColor: "#fff",
      backgroundColor: ctx.dataset.borderColor,
      borderWidth: 1,
    }),
  };
  var dmRegistrationsCanvas = document.getElementById("dmregistrations");
  if (dmRegistrationsCanvas)
    createChart(dmRegistrationsCanvas, dmRegistrationsConfig);

  // dmuncontrolled — stacked bar (sedang + berat) + line total (3 bulan terakhir)
  var dmUncontrolledConfig = baseLineChartConfig();
  dmUncontrolledConfig.data = {
    labels: _dmL3,
    datasets: [
      {
        label: "Tidak terkontrol (total)",
        data: _dmD.dm_no ? _dmD.dm_no.slice(-3) : [],
        borderColor: "#D19600",
        backgroundColor: "transparent",
        yAxisID: "y",
        segment: {
          borderDash: (ctx) => dynamicChartSegementDashed(ctx, _dmN3v),
        },
      },
      {
        type: "bar",
        label: "GDP \u2265200 mg/dL atau HbA1c \u22659%",
        data: _dmD.dm_berat ? _dmD.dm_berat.slice(-3) : [],
        borderColor: "rgba(249,191,45,0.8)",
        backgroundColor: "rgba(249,191,45,0.8)",
        yAxisID: "y",
      },
      {
        type: "bar",
        label: "GDP 126\u2013199 mg/dL atau HbA1c 7\u20138.9%",
        data: _dmD.dm_sedang ? _dmD.dm_sedang.slice(-3) : [],
        borderColor: "rgba(244,212,128,0.6)",
        backgroundColor: "rgba(244,212,128,0.6)",
        yAxisID: "y",
      },
    ],
  };
  dmUncontrolledConfig.options.scales.x.stacked = true;
  dmUncontrolledConfig.options.scales.y.stacked = true;
  dmUncontrolledConfig.options.scales.y.ticks.callback = (val) => val + "%";
  dmUncontrolledConfig.options.plugins.tooltip.displayColors = true;
  dmUncontrolledConfig.options.plugins.tooltip.callbacks = {
    label: percentageLabel,
    labelColor: (ctx) => ({
      borderColor: "#fff",
      backgroundColor: ctx.dataset.borderColor,
      borderWidth: 1,
    }),
  };
  var dmUncontrolledCanvas = document.getElementById("dmuncontrolled");
  if (dmUncontrolledCanvas)
    createChart(dmUncontrolledCanvas, dmUncontrolledConfig);

  // dmltfu12months — hilang tindak lanjut 12 bulan
  var dmLtfu12Config = baseLineChartConfig();
  dmLtfu12Config.data = {
    labels: _dmL,
    datasets: [
      {
        label: "Hilang tindak lanjut 12 bulan",
        data: _dmD.ltfu12 || [],
        borderColor: "#FF3355",
        backgroundColor: "rgba(255,51,85,0.1)",
        segment: {
          borderDash: (ctx) => dynamicChartSegementDashed(ctx, _dmNv),
        },
      },
    ],
  };
  dmLtfu12Config.options.plugins.tooltip.callbacks = { label: percentageLabel };
  dmLtfu12Config.options.scales.y.ticks.callback = (val) => val + "%";
  var dmLtfu12Canvas = document.getElementById("dmltfu12months");
  if (dmLtfu12Canvas) createChart(dmLtfu12Canvas, dmLtfu12Config);

  // dmcontrolled — gula darah terkontrol (3 bulan terakhir)
  var dmControlledConfig = baseLineChartConfig();
  dmControlledConfig.data = {
    labels: _dmL3,
    datasets: [
      {
        label: "Gula darah terkontrol",
        data: _dmD.dm_ok ? _dmD.dm_ok.slice(-3) : [],
        borderColor: "#3BB231",
        backgroundColor: "rgba(69,205,57,0.1)",
        segment: {
          borderDash: (ctx) => dynamicChartSegementDashed(ctx, _dmN3v),
        },
      },
    ],
  };
  dmControlledConfig.options.plugins.tooltip.callbacks = {
    label: percentageLabel,
  };
  dmControlledConfig.options.scales.y.ticks.callback = (val) => val + "%";
  var dmControlledCanvas = document.getElementById("dmcontrolled");
  if (dmControlledCanvas) createChart(dmControlledCanvas, dmControlledConfig);

  // dmLtfu3Month — tidak berkunjung 3 bulan
  var dmLtfu3Config = baseLineChartConfig();
  dmLtfu3Config.data = {
    labels: _dmL3,
    datasets: [
      {
        label: "Tidak berkunjung 3 bulan",
        data: _dmD.ltfu3 ? _dmD.ltfu3.slice(-3) : [],
        borderColor: "#ed6300",
        backgroundColor: "rgba(230,137,70,0.1)",
        segment: {
          borderDash: (ctx) => dynamicChartSegementDashed(ctx, _dmN3v),
        },
      },
    ],
  };
  dmLtfu3Config.options.plugins.tooltip.callbacks = { label: percentageLabel };
  dmLtfu3Config.options.scales.y.ticks.callback = (val) => val + "%";
  var dmLtfu3Canvas = document.getElementById("dmLtfu3Month");
  if (dmLtfu3Canvas) createChart(dmLtfu3Canvas, dmLtfu3Config);

  // dmstatins — statin tren semua bulan
  var dmStatinTrendConfig = baseLineChartConfig();
  dmStatinTrendConfig.data = {
    labels: _dmL,
    datasets: [
      {
        label: "Statin diresepkan",
        data: _dmD.statin || [],
        borderColor: "#34aea0",
        backgroundColor: "rgba(52,174,160,0.1)",
        segment: {
          borderDash: (ctx) => dynamicChartSegementDashed(ctx, _dmNv),
        },
      },
    ],
  };
  dmStatinTrendConfig.options.plugins.tooltip.callbacks = {
    label: percentageLabel,
  };
  dmStatinTrendConfig.options.scales.y.ticks.callback = (val) => val + "%";
  var dmStatinTrendCanvas = document.getElementById("dmstatins");
  if (dmStatinTrendCanvas)
    createChart(dmStatinTrendCanvas, dmStatinTrendConfig);

  // ── DM BP Controlled: dual line (<140/90 dan <130/80) ────────────────────────
  var dmBpControlledCanvas = document.getElementById("dmBpControlled");
  if (dmBpControlledCanvas && _dmD.bp_ok) {
    var _cfg = baseLineChartConfig();
    _cfg.data = {
      labels: _dmL,
      datasets: [
        {
          label: "TD <140/90",
          data: _dmD.bp_ok,
          borderColor: "#3BB231",
          backgroundColor: "rgba(69,205,57,0.1)",
          segment: {
            borderDash: (ctx) => dynamicChartSegementDashed(ctx, _dmNv),
          },
        },
        {
          label: "TD <130/80",
          data: _dmD.bp_ok130,
          borderColor: "#007535",
          backgroundColor: "transparent",
          segment: {
            borderDash: (ctx) => dynamicChartSegementDashed(ctx, _dmNv),
          },
        },
      ],
    };
    _cfg.options.plugins.tooltip.callbacks = { label: percentageLabel };
    _cfg.options.scales.y.ticks.callback = (v) => v + "%";
    createChart(dmBpControlledCanvas, _cfg);
  }

  // ── DM Skrining: bar+line dual-axis ──────────────────────────────────────────
  var dmscreeningsCanvas = document.getElementById("dmscreenings");
  if (dmscreeningsCanvas && _dmD.skrining) {
    createChart(dmscreeningsCanvas, {
      type: "bar",
      data: {
        labels: _dmL,
        datasets: [
          {
            type: "line",
            label: "% diskrining",
            data: _dmD.skrining_pct,
            borderColor: "#34AEA0",
            backgroundColor: "transparent",
            pointBackgroundColor: "#34AEA0",
            yAxisID: "yPct",
            tension: 0.3,
          },
          {
            type: "bar",
            label: "Jumlah diskrining",
            data: _dmD.skrining,
            backgroundColor: "#C5E5E2",
            borderColor: "#C5E5E2",
            yAxisID: "yCount",
          },
        ],
      },
      options: {
        plugins: { legend: { display: false } },
        scales: {
          yPct: {
            type: "linear",
            position: "right",
            min: 0,
            max: 100,
            ticks: { callback: (v) => v + "%" },
            grid: { drawOnChartArea: false },
          },
          yCount: {
            type: "linear",
            position: "left",
            min: 0,
            ticks: { display: false },
            grid: { drawTicks: false },
          },
          x: { grid: { display: false } },
        },
      },
    });
  }
}; // end window.dmRenderCharts

// grafik hipertensi bagian 2 (patientsprotected + htSkrining)
// dibungkus dan digabung ke window.htRenderCharts agar bisa dipanggil setelah AJAX
(function () {
  var _prevHt = window.htRenderCharts;
  window.htRenderCharts = function () {
    if (_prevHt) _prevHt();
    // grafik pasien terlindungi — data dari DB
    const _htP =
      typeof window.htChartData !== "undefined" ? window.htChartData : null;
    const _maxProtected = _htP ? Math.max(..._htP.protected) : 1;
    const patientsProtectedData = {
      labels: _htP ? _htP.labels : [],
      datasets: [
        {
          label: "Pasien dengan TD <140/90",
          data: _htP ? _htP.protected : [],
          borderColor: "#3BB231",
          backgroundColor: "rgba(69, 205, 57, 0.1)",
        },
      ],
    };
    const patientsProtectedConfig = baseLineChartConfig();
    patientsProtectedConfig.data = patientsProtectedData;
    patientsProtectedConfig.options.scales.y.grid = { drawTicks: false };
    patientsProtectedConfig.options.scales.y.ticks.display = true;
    patientsProtectedConfig.options.scales.y.ticks.count = 5;
    patientsProtectedConfig.options.scales.y.max =
      _maxProtected + Math.round(_maxProtected * 0.1);
    const patientsProtectedCanvas =
      document.getElementById("patientsprotected");
    if (patientsProtectedCanvas) {
      createChart(patientsProtectedCanvas, patientsProtectedConfig);
    }

    // ── HT Skrining: bar (jumlah) + line (persen) dual-axis ─────────────────────
    const htSkriningCanvas = document.getElementById("htSkrining");
    if (htSkriningCanvas && window.htChartData && window.htChartData.skrining) {
      const _htD = window.htChartData;
      createChart(htSkriningCanvas, {
        type: "bar",
        data: {
          labels: _htD.labels,
          datasets: [
            {
              type: "line",
              label: "% pasien diskrining",
              data: _htD.skrining_pct,
              borderColor: "#34AEA0",
              backgroundColor: "transparent",
              pointBackgroundColor: "#34AEA0",
              yAxisID: "yPct",
              tension: 0.3,
            },
            {
              type: "bar",
              label: "Jumlah diskrining",
              data: _htD.skrining,
              backgroundColor: "#C5E5E2",
              borderColor: "#C5E5E2",
              yAxisID: "yCount",
            },
          ],
        },
        options: {
          plugins: { legend: { display: false } },
          scales: {
            yPct: {
              type: "linear",
              position: "right",
              min: 0,
              max: 100,
              ticks: { callback: (v) => v + "%" },
              grid: { drawOnChartArea: false },
            },
            yCount: {
              type: "linear",
              position: "left",
              min: 0,
              ticks: { display: false },
              grid: { drawTicks: false },
            },
            x: { grid: { display: false } },
          },
        },
      });
    } // end if (htSkriningCanvas)
  }; // end window.htRenderCharts (extended)
})(); // end IIFE part 2

// Auto-execute jika htChartData sudah tersedia saat charts.js diparse (non-AJAX)
if (typeof window.htChartData !== "undefined") {
  window.htRenderCharts();
}
