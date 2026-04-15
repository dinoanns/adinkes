<?php
// ── Import CSV ke checkin + hipertensi / dm ───────────────────────────────────
// id_checkin : YYYYMMDD + 7 digit urutan per hari  (contoh: 202604100000001)
// id_hipertensi : "HT" + id_checkin
// id_dm         : "DM" + id_checkin
// Duplikat (norm + kode + tanggal sama) → dilewati
// gds → dimasukkan ke gdp jika gdp kosong (pustu tanpa lab)
// ─────────────────────────────────────────────────────────────────────────────
set_time_limit(300);
ini_set('display_errors', 0);

$db = mysqli_connect('10.15.47.116', 'dev_pasienjourney', 'XFd0NefqTvlrhsNBubtW!', 'dev_pasienjourney', 6033);
if (!$db) die("Gagal connect: " . mysqli_connect_error());
mysqli_set_charset($db, 'utf8mb4');

$result    = null;
$tipe_data = $_POST['tipe_data'] ?? 'hipertensi';

// ── Auto-increment id per tanggal ────────────────────────────────────────────
$_seq_cache = [];
function nextSeqId($db, $tbl, $col, $prefix_full, &$cache) {
    $k = $tbl . $prefix_full;
    if (!isset($cache[$k])) {
        $plen = strlen($prefix_full) + 1;
        $r = mysqli_fetch_row(mysqli_query($db,
            "SELECT MAX(CAST(SUBSTRING(`$col`, $plen) AS UNSIGNED))
             FROM `$tbl` WHERE `$col` LIKE '" . mysqli_real_escape_string($db, $prefix_full) . "%'"
        ));
        $cache[$k] = (int)($r[0] ?? 0);
    }
    $cache[$k]++;
    return $prefix_full . str_pad($cache[$k], 7, '0', STR_PAD_LEFT);
}
function nextCheckinId($db, $tgl_ymd, &$cache) {
    $prefix = str_replace('-', '', $tgl_ymd); // YYYYMMDD
    return nextSeqId($db, 'checkin', 'id_checkin', $prefix, $cache);
}
function nextHtId($db, $tgl_ymd, &$cache) {
    $prefix = 'HT' . str_replace('-', '', $tgl_ymd);
    return nextSeqId($db, 'hipertensi', 'id_hipertensi', $prefix, $cache);
}
function nextDmId($db, $tgl_ymd, &$cache) {
    $prefix = 'DM' . str_replace('-', '', $tgl_ymd);
    return nextSeqId($db, 'dm', 'id_dm', $prefix, $cache);
}

// ── Peta kolom CSV → field DB ─────────────────────────────────────────────────
// Alias ditulis huruf kecil, cocokkan ke header CSV yang sudah di-lowercase+trim
$MAP_CHECKIN = [
    'kode'         => ['kode puskesmas','kode_puskesmas','kode faskes','kode'],
    'tanggal'      => ['tanggal regitrasi','tanggal registrasi','tgl_registrasi','tanggal'],
    'jam'          => ['jam regitrasi','jam registrasi','jam_registrasi','jam reg'],
    'norm'         => ['nomor rekam medis','no_rkm_medis','norm','no rm'],
    'nik'          => ['nomor ktp','no_ktp','nik'],
    'namapasien'   => ['nama pasien','nm_pasien','nama'],
    'tanggallahir' => ['tanggal lahir','tgl_lahir'],
    'umur'         => ['umur pasien saat daftar','umurdaftar','umur','usia'],
    'nohp'         => ['nomor handphone','no_tlp','no_hp','nohp'],
    'jk'           => ['jenis kelamin (l/p)','jenis kelamin','jk'],
    'kodepoli'     => ['kode poli bpjs','kd_poli_bpjs','kode poli'],
    'kodedokter'   => ['kode dokter','kd_dokter'],
    'namadokter'   => ['nama dokter','nm_dokter'],
    'jammulai'     => ['jam mulai praktek','jam_mulai'],
    'jamselesai'   => ['jam selesai praktek','jam_selesai'],
    'kodebiaya'    => ['kode jenis pembayaran','kd_pj','kode pembayaran'],
    'noregistrasi' => ['nomor regitrasi','nomor registrasi','no_rawat','noregistrasi'],
];

$MAP_HT = [
    'tgl_ttv'        => ['tanggal ttv','tgl_ttv'],
    'jam_ttv'        => ['jam ttv','jam_ttv'],
    'sistole_ralan'  => ['tensi ralan sistole','tensi_ralan_sistole','sistole'],
    'diastole_ralan' => ['tensi ralan diastole','tensi_ralan_diastole','diastole'],
    'sistole_ranap'  => ['tensi ranap sistole','tensi_ranap_sistole'],
    'diastole_ranap' => ['tensi ranap diastole','tensi_ranap_diastole'],
    'kd_penyakit'    => ['kode diagnosa icd x','kd_penyakit','kode diagnosa','diagnosa'],
];

$MAP_DM = [
    'tgl_periksa' => ['tanggal pemeriksaan','tgl_periksa','tgl_lab'],
    'jam_periksa' => ['jam pemeriksaan','jam_periksa','jam_lab'],
    'gdp'         => ['gdp','gula darah puasa'],
    'hba1c'       => ['hba1c','hb a1c'],
    'gds'         => ['gds','gula darah sewaktu'],
    'statin'      => ['resep statin','statin','status_resep'],
];

// ── Helpers ───────────────────────────────────────────────────────────────────
function normDate($str) {
    $str = trim($str ?? '');
    if (!$str || $str === '0000-00-00') return null;
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $str)) return $str;
    if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $str, $m))
        return sprintf('%04d-%02d-%02d', $m[3], $m[1], $m[2]);
    if (preg_match('/^(\d{1,2})-(\d{1,2})-(\d{4})$/', $str, $m))
        return sprintf('%04d-%02d-%02d', $m[3], $m[1], $m[2]);
    $ts = strtotime($str);
    return $ts ? date('Y-m-d', $ts) : null;
}

function normTime($str) {
    $str = trim($str ?? '');
    if (!$str) return '00:00:00';
    if (preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', $str))
        return strlen($str) <= 5 ? $str . ':00' : $str;
    return '00:00:00';
}

function e($db, $s) { return mysqli_real_escape_string($db, (string)($s ?? '')); }

function resolveIdx($map, $col) {
    $idx = [];
    foreach ($map as $key => $aliases) {
        foreach ($aliases as $alias) {
            if (isset($col[$alias])) { $idx[$key] = $col[$alias]; break; }
        }
    }
    return $idx;
}

// ── Proses POST ───────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $tipe_data = in_array($_POST['tipe_data'] ?? '', ['hipertensi','diabetes'])
        ? $_POST['tipe_data'] : 'hipertensi';
    $file = $_FILES['csv_file']['tmp_name'];

    if (!$file || !is_uploaded_file($file)) {
        $result = ['error' => 'File tidak valid.'];
    } else {
        // Auto-detect delimiter: tab atau koma
        $fh = fopen($file, 'r');
        $first = fgets($fh);
        fclose($fh);
        $delim = (substr_count($first, "\t") >= substr_count($first, ",")) ? "\t" : ",";

        $handle = fopen($file, 'r');
        $header_raw = fgetcsv($handle, 0, $delim);

        if (!$header_raw) {
            $result = ['error' => 'File kosong atau format tidak valid.'];
        } else {
            // Normalisasi header: lowercase, spasi ganda → tunggal, trim
            $header = array_map(
                fn($h) => strtolower(trim(preg_replace('/\s+/', ' ', $h))),
                $header_raw
            );
            $col = array_flip($header);

            $idx_c  = resolveIdx($MAP_CHECKIN, $col);
            $idx_x  = resolveIdx($tipe_data === 'hipertensi' ? $MAP_HT : $MAP_DM, $col);

            // Kolom wajib
            $missing = array_diff(['kode','tanggal','norm'], array_keys($idx_c));
            if ($missing) {
                $result = ['error' =>
                    'Kolom wajib tidak ditemukan: ' . implode(', ', $missing) .
                    '<br><small>Header terdeteksi: ' . implode(' | ', $header) . '</small>'
                ];
            } else {
                $inserted = $skipped = $errors = 0;
                $skip_kosong = $skip_duplikat = 0;
                $err_sample = [];
                $sample_kode = null; // kode pertama yg terbaca di CSV

                while (($row = fgetcsv($handle, 0, $delim)) !== false) {
                    if (count($row) < 3) continue;

                    $g  = fn($k) => isset($idx_c[$k]) ? trim($row[$idx_c[$k]] ?? '') : '';
                    $gx = fn($k) => isset($idx_x[$k]) ? trim($row[$idx_x[$k]] ?? '') : '';

                    $kode = $g('kode');
                    $tgl  = normDate($g('tanggal'));
                    $norm = $g('norm');

                    if ($sample_kode === null && $kode) $sample_kode = $kode;

                    if (!$kode || !$tgl || !$norm) { $skipped++; $skip_kosong++; continue; }

                    $ts = date('Y-m-d H:i:s');

                    // ── Cek apakah checkin sudah ada ──────────────────────────
                    // Skenario: checkin sudah ada (dari import sebelumnya)
                    // tapi record klinis (HT/DM) belum dibuat → pakai id_checkin lama
                    $existing_ci = mysqli_fetch_row(mysqli_query($db,
                        "SELECT id_checkin FROM checkin
                         WHERE norm='" . e($db,$norm) . "'
                           AND kode='" . e($db,$kode) . "'
                           AND tanggal='$tgl' LIMIT 1"
                    ));

                    if ($existing_ci) {
                        // Checkin sudah ada → pakai id_checkin lama
                        $id_checkin = trim($existing_ci[0]);
                        // Cek apakah record klinis sudah ada untuk checkin ini
                        $tbl_klinis  = $tipe_data === 'hipertensi' ? 'hipertensi' : 'dm';
                        $sql_kl = "SELECT 1 FROM `$tbl_klinis` kl
                                   JOIN checkin c ON c.id_checkin = kl.idcheckin
                                   WHERE c.norm='" . e($db,$norm) . "'
                                     AND c.kode='" . e($db,$kode) . "'
                                     AND c.tanggal='" . e($db,$tgl) . "'
                                   LIMIT 1";
                        $qr_klinis   = mysqli_query($db, $sql_kl);
                        $dup_klinis  = ($qr_klinis && mysqli_num_rows($qr_klinis) > 0);
                        if ($dup_klinis) {
                            $skipped++; $skip_duplikat++; continue;
                        }
                        // Klinis belum ada → lanjut insert klinis saja (skip insert checkin)
                    } else {
                        // Checkin belum ada sama sekali → buat checkin baru
                        $id_checkin   = nextCheckinId($db, $tgl, $_seq_cache);
                        $jam          = normTime($g('jam'));
                        $nik          = $g('nik');
                        $namapasien   = $g('namapasien');
                        $tanggallahir = normDate($g('tanggallahir')) ?? '0000-00-00';
                        $umur         = $g('umur') ?: '0';
                        $nohp         = $g('nohp');
                        $jk_raw       = strtoupper($g('jk'));
                        $jk           = in_array($jk_raw, ['L','P']) ? $jk_raw : '-';
                        $kodepoli     = substr($g('kodepoli'),  0, 5);
                        $kodedokter   = $g('kodedokter');
                        $namadokter   = $g('namadokter');
                        $jammulai     = normTime($g('jammulai'));
                        $jamselesai   = normTime($g('jamselesai'));
                        $kodebiaya    = substr($g('kodebiaya'), 0, 5);
                        $noregistrasi = $g('noregistrasi') ?: $id_checkin;

                        $sql_ci = "INSERT INTO checkin
                            (id_checkin,kode,tanggal,jam,norm,nik,namapasien,tanggallahir,
                             umur,nohp,jk,kodepoli,kodedokter,namadokter,
                             jammulai,jamselesai,kodebiaya,noregistrasi,timestamp)
                            VALUES (
                                '" . e($db,$id_checkin)   . "',
                                '" . e($db,$kode)          . "',
                                '" . e($db,$tgl)            . "',
                                '" . e($db,$jam)            . "',
                                '" . e($db,$norm)           . "',
                                '" . e($db,$nik)            . "',
                                '" . e($db,$namapasien)     . "',
                                '" . e($db,$tanggallahir)   . "',
                                '" . e($db,$umur)           . "',
                                '" . e($db,$nohp)           . "',
                                '" . e($db,$jk)             . "',
                                '" . e($db,$kodepoli)       . "',
                                '" . e($db,$kodedokter)     . "',
                                '" . e($db,$namadokter)     . "',
                                '" . e($db,$jammulai)       . "',
                                '" . e($db,$jamselesai)     . "',
                                '" . e($db,$kodebiaya)      . "',
                                '" . e($db,$noregistrasi)   . "',
                                '" . e($db,$ts)             . "'
                            )";

                        if (!mysqli_query($db, $sql_ci)) {
                            $errors++;
                            if (count($err_sample) < 5)
                                $err_sample[] = 'checkin: ' . mysqli_error($db) . " (norm=$norm, tgl=$tgl)";
                            continue;
                        }
                    } // end checkin block

                    // ── Insert record klinis (HT atau DM) ─────────────────────
                    if ($tipe_data === 'hipertensi') {
                        $id_ht       = nextHtId($db, $tgl, $_seq_cache);
                        $tgl_ttv     = normDate($gx('tgl_ttv')) ?? $tgl;
                        $jam_ttv     = normTime($gx('jam_ttv'));
                        $sistole     = $gx('sistole_ralan');
                        $diastole    = $gx('diastole_ralan');
                        if (!$sistole)  $sistole  = $gx('sistole_ranap');
                        if (!$diastole) $diastole = $gx('diastole_ranap');
                        $kd_penyakit = substr($gx('kd_penyakit'), 0, 5) ?: 'I10';

                        $sql_ht = "INSERT INTO hipertensi
                            (id_hipertensi,idcheckin,tanggal,jam,sistole,diastole,kd_penyakit,timestamp)
                            VALUES (
                                '" . e($db,$id_ht)       . "',
                                '" . e($db,$id_checkin)  . "',
                                '" . e($db,$tgl_ttv)     . "',
                                '" . e($db,$jam_ttv)     . "',
                                '" . e($db,$sistole)     . "',
                                '" . e($db,$diastole)    . "',
                                '" . e($db,$kd_penyakit) . "',
                                '" . e($db,$ts)          . "'
                            )";

                        if (!mysqli_query($db, $sql_ht)) {
                            $errors++;
                            if (count($err_sample) < 5)
                                $err_sample[] = 'hipertensi: ' . mysqli_error($db);
                        } else { $inserted++; }

                    } else { // diabetes
                        $id_dm_val   = nextDmId($db, $tgl, $_seq_cache);
                        $tgl_periksa = normDate($gx('tgl_periksa')) ?? $tgl;
                        $jam_periksa = normTime($gx('jam_periksa'));
                        $gdp         = $gx('gdp');
                        $hba1c       = $gx('hba1c');
                        $gds         = $gx('gds');
                        if (!$gdp && $gds) $gdp = $gds; // pustu tanpa lab
                        $statin_raw  = $gx('statin');
                        $statin      = ($statin_raw === 'Diresepkan') ? 'Diresepkan' : 'Tidak Diresepkan';

                        $sql_dm = "INSERT INTO dm
                            (id_dm,idcheckin,tanggal,jam,gdp,hba1c,statin,timestamp)
                            VALUES (
                                '" . e($db,$id_dm_val)   . "',
                                '" . e($db,$id_checkin)  . "',
                                '" . e($db,$tgl_periksa) . "',
                                '" . e($db,$jam_periksa) . "',
                                '" . e($db,$gdp)         . "',
                                '" . e($db,$hba1c)       . "',
                                '" . e($db,$statin)      . "',
                                '" . e($db,$ts)          . "'
                            )";

                        if (!mysqli_query($db, $sql_dm)) {
                            $errors++;
                            if (count($err_sample) < 5)
                                $err_sample[] = 'dm: ' . mysqli_error($db);
                        } else { $inserted++; }
                    }
                } // end while
                fclose($handle);
                $result = [
                    'ok'             => true,
                    'inserted'       => $inserted,
                    'skipped'        => $skipped,
                    'skip_kosong'    => $skip_kosong,
                    'skip_duplikat'  => $skip_duplikat,
                    'errors'         => $errors,
                    'tipe'           => $tipe_data,
                    'err_sample'     => $err_sample,
                    'sample_kode'    => $sample_kode,
                    'col_detected'   => array_keys($idx_c + $idx_x),
                ];
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Import CSV — ADINKES</title>
<style>
  *    { box-sizing: border-box; }
  body { font-family: system-ui, sans-serif; max-width: 740px; margin: 2rem auto; padding: 0 1rem; color: #1a2332; }
  h2   { margin-bottom: .15rem; }
  p.sub{ color: #666; margin-top: 0; font-size: .9rem; }

  label        { display: block; margin: 1.1rem 0 .3rem; font-weight: 600; font-size: .9rem; }
  select,
  input[type=file] { width: 100%; padding: .5rem .7rem; border: 1px solid #ccd; border-radius: 5px; font-size: .9rem; }
  .radio-group { display: flex; gap: 2rem; margin: .4rem 0; }
  .radio-group label { font-weight: 400; display: flex; align-items: center; gap: .4rem; margin: 0; }
  button { margin-top: 1.4rem; padding: .6rem 1.6rem; background: #0075eb; color: #fff;
           border: none; border-radius: 5px; cursor: pointer; font-size: .95rem; }
  button:hover { background: #005ec2; }

  .result     { margin-top: 1.5rem; padding: 1rem 1.2rem; border-radius: 7px; font-size: .9rem; }
  .result.ok  { background: #f0fdf4; border: 1px solid #86efac; }
  .result.err { background: #fef2f2; border: 1px solid #fca5a5; }
  .result table { margin-top: .6rem; border-collapse: collapse; }
  .result td  { padding: .2rem .8rem .2rem 0; }
  .result td:first-child { font-weight: 600; }
  .err-list   { margin-top: .5rem; font-size: .8rem; color: #991b1b; }

  .hint        { margin-top: 2rem; background: #f8fafc; border: 1px solid #e2e8f0;
                 border-radius: 7px; padding: 1rem 1.2rem; font-size: .82rem; line-height: 1.7; }
  .hint h4    { margin: 0 0 .4rem; font-size: .85rem; }
  .hint code  { background: #e2e8f0; padding: 1px 5px; border-radius: 3px; font-size: .78rem; }
  .hint .section { margin-top: .8rem; }
  .badge-w    { display:inline-block; background:#fef9c3; color:#854d0e; border-radius:3px; padding:0 5px; font-size:.75rem; margin-left:4px; }
  .badge-o    { display:inline-block; background:#f0fdf4; color:#166534; border-radius:3px; padding:0 5px; font-size:.75rem; margin-left:4px; }
</style>
</head>
<body>

<h2>Import CSV Pasien</h2>
<p class="sub">Upload data kunjungan dari SIMRS Puskesmas ke database ADINKES</p>

<?php if ($result): ?>
  <?php if (isset($result['error'])): ?>
    <div class="result err">&#10060; <?= $result['error'] ?></div>
  <?php else: ?>
    <div class="result ok">
      &#9989; <strong>Import <?= $result['tipe'] === 'hipertensi' ? 'Hipertensi' : 'Diabetes' ?> selesai</strong>
      <table>
        <tr><td>Berhasil dimasukkan</td><td><strong><?= number_format($result['inserted']) ?></strong> baris</td></tr>
        <tr><td>Dilewati — duplikat (norm+kode+tgl sama)</td><td><?= number_format($result['skip_duplikat']) ?> baris</td></tr>
        <tr><td>Dilewati — kolom kosong/tidak terbaca</td><td><?= number_format($result['skip_kosong']) ?> baris</td></tr>
        <tr><td>Error DB</td><td><?= $result['errors'] ?> baris</td></tr>
      </table>
      <?php if ($result['sample_kode']): ?>
        <p style="margin:.6rem 0 0;font-size:.83rem">
          <strong>Kode faskes di CSV:</strong> <code><?= htmlspecialchars($result['sample_kode']) ?></code>
        </p>
      <?php endif; ?>
      <?php if (!empty($result['col_detected'])): ?>
        <p style="margin:.3rem 0 0;font-size:.8rem;color:#555">
          <strong>Kolom terdeteksi:</strong> <?= implode(', ', array_map('htmlspecialchars', $result['col_detected'])) ?>
        </p>
      <?php endif; ?>
      <?php if (!empty($result['err_sample'])): ?>
        <div class="err-list"><strong>Contoh error:</strong><br>
          <?= implode('<br>', array_map('htmlspecialchars', $result['err_sample'])) ?>
        </div>
      <?php endif; ?>
    </div>
  <?php endif; ?>
<?php endif; ?>

<form method="POST" enctype="multipart/form-data">

  <label>Jenis Data</label>
  <div class="radio-group">
    <label>
      <input type="radio" name="tipe_data" value="hipertensi"
             <?= ($tipe_data === 'hipertensi') ? 'checked' : '' ?>
             onchange="showHint(this.value)">
      Hipertensi
    </label>
    <label>
      <input type="radio" name="tipe_data" value="diabetes"
             <?= ($tipe_data === 'diabetes') ? 'checked' : '' ?>
             onchange="showHint(this.value)">
      Diabetes
    </label>
  </div>

  <label for="csv_file">File CSV / TSV</label>
  <input type="file" name="csv_file" id="csv_file" accept=".csv,.tsv,.txt" required>
  <p style="margin:.3rem 0 0;font-size:.8rem;color:#666">Delimiter otomatis terdeteksi (tab atau koma). Baris pertama harus header.</p>

  <button type="submit">&#8679; Upload &amp; Import</button>
</form>

<div class="hint">
  <div id="hint-ht" style="display:<?= $tipe_data==='hipertensi'?'block':'none' ?>">
    <h4>Format CSV — Hipertensi</h4>
    <div class="section">
      <strong>Kolom wajib:</strong><br>
      <code>Kode Puskesmas</code> &nbsp;
      <code>tanggal regitrasi</code> &nbsp;
      <code>nomor rekam medis</code>
    </div>
    <div class="section">
      <strong>Kolom opsional (checkin):</strong><br>
      <code>jam regitrasi</code> <code>nomor ktp</code> <code>nama pasien</code>
      <code>tanggal lahir</code> <code>umur pasien saat daftar</code>
      <code>nomor handphone</code> <code>jenis kelamin (L/P)</code>
      <code>kode poli bpjs</code> <code>kode dokter</code> <code>nama dokter</code>
      <code>jam mulai praktek</code> <code>jam selesai praktek</code>
      <code>kode jenis pembayaran</code> <code>nomor regitrasi</code>
    </div>
    <div class="section">
      <strong>Kolom klinis hipertensi:</strong><br>
      <code>tanggal ttv</code> <code>jam ttv</code>
      <code>tensi ralan sistole</code> <code>tensi ralan diastole</code>
      <code>tensi ranap sistole</code> <code>tensi ranap diastole</code>
      <code>kode diagnosa ICD X</code>
    </div>
  </div>

  <div id="hint-dm" style="display:<?= $tipe_data==='diabetes'?'block':'none' ?>">
    <h4>Format CSV — Diabetes</h4>
    <div class="section">
      <strong>Kolom wajib:</strong><br>
      <code>Kode Puskesmas</code> &nbsp;
      <code>tanggal regitrasi</code> &nbsp;
      <code>nomor rekam medis</code>
    </div>
    <div class="section">
      <strong>Kolom opsional (checkin):</strong><br>
      <code>jam regitrasi</code> <code>nomor ktp</code> <code>nama pasien</code>
      <code>tanggal lahir</code> <code>umur pasien saat daftar</code>
      <code>nomor handphone</code> <code>jenis kelamin (L/P)</code>
      <code>kode poli bpjs</code> <code>kode dokter</code> <code>nama dokter</code>
      <code>jam mulai praktek</code> <code>jam selesai praktek</code>
      <code>kode jenis pembayaran</code> <code>nomor regitrasi</code>
    </div>
    <div class="section">
      <strong>Kolom klinis diabetes:</strong><br>
      <code>tanggal pemeriksaan</code> <code>jam pemeriksaan</code>
      <code>gdp</code> <code>hba1c</code>
      <code>gds</code> <small style="color:#666">(→ masuk ke GDP jika GDP kosong, untuk pustu tanpa lab)</small>
      <code>resep statin</code> <small style="color:#666">(nilai: <em>Diresepkan</em> atau <em>Tidak Diresepkan</em>)</small>
    </div>
  </div>

  <div class="section" style="margin-top:.8rem;border-top:1px solid #e2e8f0;padding-top:.7rem;">
    <strong>Catatan umum:</strong><br>
    Format tanggal: <code>YYYY-MM-DD</code> atau <code>DD/MM/YYYY</code><br>
    Duplikat (norm + kode faskes + tanggal sama) otomatis dilewati.<br>
    Baris dengan tanggal berbeda untuk pasien yang sama tetap dimasukkan.
  </div>
</div>

<script>
function showHint(val) {
  document.getElementById('hint-ht').style.display = val === 'hipertensi' ? 'block' : 'none';
  document.getElementById('hint-dm').style.display = val === 'diabetes'   ? 'block' : 'none';
}
</script>
</body>
</html>
