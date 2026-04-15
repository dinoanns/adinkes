# Dokumentasi ADINKES Dashboard
**Adinkes Dinas Kesehatan DKI Jakarta**
Versi terakhir diperbarui: April 2026

---

## Daftar Isi
1. [Struktur Database](#1-struktur-database)
2. [Relasi Antar Tabel](#2-relasi-antar-tabel)
3. [Filter & Parameter Global](#3-filter--parameter-global)
4. [Modul Hipertensi](#4-modul-hipertensi)
   - [Ringkasan (Summary)](#41-ringkasan-summary)
   - [Chart Tren 12 Bulan](#42-chart-tren-12-bulan)
   - [Tabel Sub-Wilayah](#43-tabel-sub-wilayah)
5. [Modul Diabetes](#5-modul-diabetes)
   - [Ringkasan (Summary)](#51-ringkasan-summary)
   - [Chart Tren 12 Bulan](#52-chart-tren-12-bulan)
   - [Tabel Sub-Wilayah](#53-tabel-sub-wilayah)
6. [Definisi Metrik](#6-definisi-metrik)

---

## 1. Struktur Database

### Tabel `checkin`
Kunjungan pasien ke faskes (satu baris = satu kunjungan).

| Kolom | Tipe | Keterangan |
|-------|------|-----------|
| `id_checkin` | varchar(36) PK | ID unik kunjungan (format: YYYYMMDD + 7 digit urut) |
| `kode` | varchar(20) | Kode faskes (Puskesmas/RSUD) |
| `tanggal` | date | Tanggal kunjungan |
| `jam` | time | Jam kunjungan |
| `norm` | varchar(15) | Nomor Rekam Medis pasien |
| `nik` | varchar(16) | NIK pasien |
| `namapasien` | text | Nama pasien |
| `tanggallahir` | date | Tanggal lahir pasien |
| `umur` | varchar(5) | Umur pasien saat daftar |
| `nohp` | varchar(15) | Nomor HP pasien |
| `jk` | enum('-','L','P') | Jenis kelamin |
| `kodepoli` | char(5) | Kode poli BPJS |
| `kodedokter` | varchar(50) | Kode dokter |
| `namadokter` | text | Nama dokter |
| `jammulai` | time | Jam mulai praktek |
| `jamselesai` | time | Jam selesai praktek |
| `kodebiaya` | varchar(5) | Kode jenis pembayaran |
| `noregistrasi` | varchar(50) | Nomor registrasi |
| `timestamp` | datetime | Waktu insert data |

---

### Tabel `hipertensi`
Data klinis tekanan darah pasien hipertensi.

| Kolom | Tipe | Keterangan |
|-------|------|-----------|
| `id_hipertensi` | varchar(36) PK | ID unik record HT (format: HT + YYYYMMDD + 7 digit) |
| `idcheckin` | varchar(36) PK | FK → `checkin.id_checkin` |
| `tanggal` | date | Tanggal pengukuran TTV |
| `jam` | time | Jam pengukuran |
| `sistole` | varchar(5) | Tekanan sistolik (mmHg) |
| `diastole` | varchar(5) | Tekanan diastolik (mmHg) |
| `kd_penyakit` | varchar(5) | Kode ICD-X (hipertensi primer = `I10%`) |
| `timestamp` | datetime | Waktu insert data |

---

### Tabel `dm`
Data klinis pasien diabetes mellitus.

| Kolom | Tipe | Keterangan |
|-------|------|-----------|
| `id_dm` | varchar(36) PK | ID unik record DM (format: DM + YYYYMMDD + 7 digit) |
| `idcheckin` | varchar(36) PK | FK → `checkin.id_checkin` |
| `tanggal` | date | Tanggal pemeriksaan lab |
| `jam` | time | Jam pemeriksaan |
| `gdp` | varchar(5) | Gula Darah Puasa (mg/dL) |
| `hba1c` | varchar(5) | HbA1c (%) |
| `statin` | enum('Diresepkan','Tidak Diresepkan') | Status resep statin |
| `timestamp` | datetime | Waktu insert data |

---

### Tabel `faskes`
Master data fasilitas kesehatan.

| Kolom | Tipe | Keterangan |
|-------|------|-----------|
| `kode` | varchar(20) PK | Kode unik faskes |
| `key` | varchar(200) | Key internal |
| `nama_faskes` | varchar(200) | Nama faskes |
| `kode_master` | varchar(10) | Tipe faskes: `PKM` / `PST` / `RSUD` |
| `kode_kabupaten` | varchar(20) | FK → `kabupaten` |
| `kode_kecamatan` | varchar(20) | FK → `kecamatan` |
| `kode_kelurahan` | varchar(20) | FK → `kelurahan` |
| `longitude` | varchar(100) | Koordinat |
| `latitude` | varchar(100) | Koordinat |
| `status` | enum('1','0') | `1` = aktif |

**Tipe Faskes (`kode_master`):**
- `PKM` = Puskesmas induk
- `PST` = Puskesmas Pembantu (anak dari PKM di kecamatan yang sama)
- `RSUD` = Rumah Sakit Umum Daerah

---

### Tabel `kabupaten`

| Kolom | Tipe | Keterangan |
|-------|------|-----------|
| `kode_kabupaten` | varchar(20) PK | Kode wilayah kabupaten/kota |
| `nama_kabupaten` | text | Nama kabupaten/kota |

### Tabel `kecamatan`

| Kolom | Tipe | Keterangan |
|-------|------|-----------|
| `kode_kecamatan` | varchar(20) PK | Kode wilayah kecamatan |
| `nama_kecamatan` | text | Nama kecamatan |

### Tabel `kelurahan`

| Kolom | Tipe | Keterangan |
|-------|------|-----------|
| `kode_kelurahan` | varchar(20) PK | Kode wilayah kelurahan |
| `nama_kelurahan` | text | Nama kelurahan |

---

## 2. Relasi Antar Tabel

```
kabupaten
    └── faskes (via kode_kabupaten)
            └── kecamatan (via kode_kecamatan)
            └── kelurahan (via kode_kelurahan)

checkin (kode → faskes.kode)
    └── hipertensi (idcheckin → checkin.id_checkin)
    └── dm         (idcheckin → checkin.id_checkin)
```

**Catatan penting:**
- `id_checkin` format `YYYYMMDD + 7digit` TIDAK dijamin unik secara global lintas faskes
  (urutan reset per tanggal, bisa collision antar Puskesmas berbeda).
- Identitas unik pasien per faskes = kombinasi `checkin.norm + checkin.kode + checkin.tanggal`.
- Hipertensi dan DM di-join ke pasien melalui `checkin`, bukan langsung ke `norm`.

---

## 3. Filter & Parameter Global

Semua endpoint AJAX menerima parameter GET berikut:

| Parameter | Keterangan |
|-----------|-----------|
| `kab` | Filter kode kabupaten |
| `kec` | Filter kode kecamatan |
| `kel` | Filter kode kelurahan |
| `kode_faskes` | Filter satu faskes spesifik |
| `tipe` | `puskesmas` / `rsud` / `gabungan` |

**Logika filter faskes (`$in_faskes_kode`):**
```sql
-- Jika kode_faskes diisi → hanya faskes tersebut
WHERE kode = '{filter_kode}'

-- Jika tipe = gabungan → PKM + PST + RSUD
WHERE kode_master IN ('PKM','PST','RSUD')

-- Jika tipe = puskesmas → PKM saja
WHERE kode_master = 'PKM'

-- Jika tipe = rsud → RSUD saja
WHERE kode_master = 'RSUD'

-- Dikombinasikan dengan filter wilayah (kab/kec/kel)
```

**Periode aktif** ditentukan otomatis dari data terbaru di DB:
```sql
-- HT
SELECT MONTH(MAX(c.tanggal)) AS bulan, YEAR(MAX(c.tanggal)) AS tahun
FROM hipertensi h JOIN checkin c ON c.id_checkin = h.idcheckin
WHERE c.kode IN ({in_faskes_kode}) AND h.kd_penyakit LIKE 'I10%' AND c.tanggal <= CURDATE()

-- DM
SELECT MONTH(MAX(c.tanggal)) AS bulan, YEAR(MAX(c.tanggal)) AS tahun
FROM dm d JOIN checkin c ON c.id_checkin = d.idcheckin
WHERE c.kode IN ({in_faskes_kode}) AND c.tanggal <= CURDATE()
```

**Date ranges yang digunakan:**
| Variabel | Keterangan |
|----------|-----------|
| `$tgl_akhir_bln` | Hari terakhir bulan aktif |
| `$tgl_awal_bln` | Hari pertama bulan aktif |
| `$tgl_awal_3bln` | Hari pertama, 3 bulan ke belakang |
| `$tgl_awal_12bln` | Hari pertama, 12 bulan ke belakang |

---

## 4. Modul Hipertensi

File: `module/hipertensi/`
Endpoint: `ajax_summary.php`, `ajax_chart.php`, `ajax_table.php`

---

### 4.1 Ringkasan (Summary)

**File:** `ajax_summary.php`

#### Query 1 — Metrik Utama Treatment Outcomes
Pasien dalam perawatan (kunjungan dalam 3 bulan), exclude yang baru terdaftar < 3 bulan lalu.

```sql
SELECT COUNT(*) AS total_pasien,
       SUM(CASE WHEN CAST(h.sistole  AS UNSIGNED) < 140
                 AND CAST(h.diastole AS UNSIGNED) < 90
                THEN 1 ELSE 0 END) AS bp_terkontrol,
       SUM(CASE WHEN NOT(CAST(h.sistole  AS UNSIGNED) < 140
                     AND CAST(h.diastole AS UNSIGNED) < 90)
                THEN 1 ELSE 0 END) AS bp_tidak,
       SUM(c.jk='P') AS gender_perempuan,
       SUM(c.jk='L') AS gender_laki,
       SUM(CASE WHEN CAST(c.umur AS UNSIGNED) BETWEEN 18 AND 29 THEN 1 ELSE 0 END) AS usia_18_29,
       SUM(CASE WHEN CAST(c.umur AS UNSIGNED) BETWEEN 30 AND 49 THEN 1 ELSE 0 END) AS usia_30_49,
       SUM(CASE WHEN CAST(c.umur AS UNSIGNED) BETWEEN 50 AND 69 THEN 1 ELSE 0 END) AS usia_50_69,
       SUM(CASE WHEN CAST(c.umur AS UNSIGNED) >= 70             THEN 1 ELSE 0 END) AS usia_70_plus
FROM hipertensi h
JOIN checkin c ON c.id_checkin = h.idcheckin
INNER JOIN (
    -- Kunjungan terakhir per pasien dalam 3 bulan
    SELECT c2.norm, c2.kode, MAX(c2.tanggal) AS last_visit
    FROM hipertensi h2 JOIN checkin c2 ON c2.id_checkin = h2.idcheckin
    WHERE c2.kode IN ({in_faskes_kode}) AND h2.kd_penyakit LIKE 'I10%'
      AND c2.tanggal <= '{tgl_akhir_bln}'
    GROUP BY c2.norm, c2.kode
    HAVING last_visit BETWEEN '{tgl_awal_3bln}' AND '{tgl_akhir_bln}'
) lv ON lv.norm = c.norm AND lv.kode = c.kode AND lv.last_visit = c.tanggal
INNER JOIN (
    -- Hanya pasien yang terdaftar sebelum 3 bulan lalu (exclude newly registered)
    SELECT c3.norm, c3.kode FROM hipertensi h3
    JOIN checkin c3 ON c3.id_checkin = h3.idcheckin
    WHERE c3.kode IN ({in_faskes_kode}) AND h3.kd_penyakit LIKE 'I10%'
    GROUP BY c3.norm, c3.kode
    HAVING MIN(c3.tanggal) < '{tgl_awal_3bln}'
) fv ON fv.norm = c.norm AND fv.kode = c.kode
WHERE c.kode IN ({in_faskes_kode}) AND h.kd_penyakit LIKE 'I10%'
```

**Output:** `total_pasien`, `bp_terkontrol`, `bp_tidak`, distribusi gender & usia.

---

#### Query 2 — Kumulatif, Baru, dan LTFU
```sql
SELECT
    COUNT(DISTINCT p.norm) AS terdaftar_alltime,
    COUNT(DISTINCT CASE WHEN p.max_in_12mo IS NOT NULL THEN p.norm END) AS terdaftar_kum,
    COUNT(DISTINCT CASE WHEN p.max_in_12mo IS NOT NULL
                         AND p.min_tgl < '{tgl_awal_3bln}' THEN p.norm END) AS terdaftar_den,
    SUM(CASE WHEN p.min_tgl BETWEEN '{tgl_awal_bln}' AND '{tgl_akhir_bln}'
             THEN 1 ELSE 0 END) AS terdaftar_baru,
    SUM(CASE WHEN p.max_in_12mo IS NOT NULL
              AND p.max_in_12mo < '{tgl_awal_3bln}' THEN 1 ELSE 0 END) AS ltfu_3bln,
    SUM(CASE WHEN p.max_tgl < '{tgl_awal_12bln}' THEN 1 ELSE 0 END) AS ltfu_12bln
FROM (
    SELECT c.norm, c.kode,
           MIN(c.tanggal) AS min_tgl,
           MAX(c.tanggal) AS max_tgl,
           MAX(CASE WHEN c.tanggal BETWEEN '{tgl_awal_12bln}' AND '{tgl_akhir_bln}'
                    THEN c.tanggal END) AS max_in_12mo
    FROM hipertensi h JOIN checkin c ON c.id_checkin = h.idcheckin
    WHERE h.kd_penyakit LIKE 'I10%' AND c.kode IN ({in_faskes_kode})
    GROUP BY c.norm, c.kode
) p
```

**Output:** `terdaftar_alltime`, `terdaftar_kum`, `terdaftar_den`, `terdaftar_baru`, `ltfu_3bln`, `ltfu_12bln`.

---

#### Query 3 — Komorbid HT + DM
```sql
SELECT COUNT(DISTINCT c.norm) AS ht_dm,
       SUM(CASE WHEN CAST(h.sistole  AS UNSIGNED) < 140
                 AND CAST(h.diastole AS UNSIGNED) < 90
                THEN 1 ELSE 0 END) AS komorbid_bp_ok
FROM hipertensi h
JOIN checkin c ON c.id_checkin = h.idcheckin
INNER JOIN (
    SELECT c2.norm, c2.kode, MAX(c2.tanggal) AS last_visit
    FROM hipertensi h2 JOIN checkin c2 ON c2.id_checkin = h2.idcheckin
    WHERE c2.kode IN ({in_faskes_kode}) AND h2.kd_penyakit LIKE 'I10%'
      AND c2.tanggal <= '{tgl_akhir_bln}'
    GROUP BY c2.norm, c2.kode
    HAVING last_visit BETWEEN '{tgl_awal_3bln}' AND '{tgl_akhir_bln}'
) lv ON lv.norm = c.norm AND lv.kode = c.kode AND lv.last_visit = c.tanggal
WHERE c.kode IN ({in_faskes_kode}) AND h.kd_penyakit LIKE 'I10%'
  AND EXISTS (
      SELECT 1 FROM dm d JOIN checkin cd ON cd.id_checkin = d.idcheckin
      WHERE cd.norm = c.norm
  )
```

**Output:** `ht_dm` (jumlah pasien HT yang juga punya DM), `komorbid_bp_ok`.

---

#### Query 4 — Skrining Bulan Aktif
```sql
SELECT COUNT(DISTINCT c.norm) AS skrining_bln
FROM hipertensi h
JOIN checkin c ON c.id_checkin = h.idcheckin
WHERE c.kode IN ({in_faskes_kode}) AND h.kd_penyakit LIKE 'I10%'
  AND c.tanggal BETWEEN '{tgl_awal_bln}' AND '{tgl_akhir_bln}'
```

**Output:** `skrining_bln` (pasien unik yang berkunjung di bulan aktif).

---

### 4.2 Chart Tren 12 Bulan

**File:** `ajax_chart.php`

**Metode:** Fetch semua kunjungan HT sekaligus, agregasi di PHP per bulan (menghindari 12x query ke DB).

#### Query Utama — Fetch Semua Kunjungan HT
```sql
SELECT c.norm, TRIM(c.kode) AS kode, c.tanggal,
       CAST(h.sistole  AS UNSIGNED) AS sistole,
       CAST(h.diastole AS UNSIGNED) AS diastole
FROM hipertensi h
JOIN checkin c ON c.id_checkin = h.idcheckin
WHERE h.kd_penyakit LIKE 'I10%' AND c.kode IN ({in_faskes_kode})
  AND c.tanggal <= '{tgl_akhir_bln}'
ORDER BY c.norm, c.kode, c.tanggal
```

Data di-group per pasien (`norm + kode`) lalu diproses di PHP untuk setiap bulan dalam 12 bulan terakhir menghasilkan:

| Variabel Output | Keterangan |
|----------------|-----------|
| `labels` | Label bulan (Jan-2026, Feb-2026, ...) |
| `bp_ok` | % pasien BP terkontrol (<140/90) dari denominator |
| `bp_no` | % pasien BP tidak terkontrol dari denominator |
| `ltfu3` | % LTFU 3 bulan dari denominator |
| `ltfu12` | % LTFU 12 bulan dari total alltime |
| `terdaftar` | Kumulatif alltime s/d bulan tersebut |
| `dalam_pwr` | Pasien dalam perawatan (ada kunjungan 12 bulan) |
| `baru` | Pasien baru terdaftar di bulan tersebut |
| `protected` | Jumlah absolut pasien BP terkontrol |
| `skrining` | Pasien unik yang berkunjung di bulan tersebut |
| `skrining_pct` | % skrining dari total dalam perawatan |

**Denominator treatment outcomes per bulan:**
```
denominator = pasien_bp_ok_den + pasien_bp_no_den + ltfu_3bln
            = pasien yang:
              - punya kunjungan dalam 12 bulan terakhir
              - kunjungan terakhir >= 3 bulan lalu (bukan LTFU12)
              - terdaftar sebelum 3 bulan lalu (exclude newly registered)
            + pasien LTFU 3 bulan
```

#### Query Kohort Triwulan HT
Untuk setiap triwulan registrasi (Q-7 s/d Q-1 dari sekarang), hitung outcome di triwulan pengukuran berikutnya:

```sql
SELECT COUNT(*) AS total,
       SUM(CASE WHEN mv.meas_visit IS NOT NULL
                 AND CAST(h.sistole  AS UNSIGNED) < 140
                 AND CAST(h.diastole AS UNSIGNED) < 90
                THEN 1 ELSE 0 END) AS bp_ok,
       SUM(CASE WHEN mv.meas_visit IS NOT NULL
                 AND NOT(CAST(h.sistole AS UNSIGNED) < 140
                     AND CAST(h.diastole AS UNSIGNED) < 90)
                THEN 1 ELSE 0 END) AS bp_no,
       SUM(CASE WHEN mv.meas_visit IS NULL THEN 1 ELSE 0 END) AS ltfu3
FROM (
    -- Pasien yang pertama kali terdaftar di triwulan registrasi
    SELECT c.norm, c.kode FROM hipertensi h2
    JOIN checkin c ON c.id_checkin = h2.idcheckin
    WHERE c.kode IN ({in_faskes_kode}) AND h2.kd_penyakit LIKE 'I10%'
    GROUP BY c.norm, c.kode
    HAVING MIN(c.tanggal) BETWEEN '{reg_start}' AND '{reg_end}'
) np
LEFT JOIN (
    -- Kunjungan terakhir di triwulan pengukuran
    SELECT c2.norm, c2.kode, MAX(c2.tanggal) AS meas_visit
    FROM hipertensi hm JOIN checkin c2 ON c2.id_checkin = hm.idcheckin
    WHERE c2.kode IN ({in_faskes_kode}) AND hm.kd_penyakit LIKE 'I10%'
      AND c2.tanggal BETWEEN '{meas_start}' AND '{meas_end}'
    GROUP BY c2.norm, c2.kode
) mv ON mv.norm = np.norm AND mv.kode = np.kode
LEFT JOIN checkin cm ON cm.norm = mv.norm AND cm.kode = mv.kode AND cm.tanggal = mv.meas_visit
LEFT JOIN hipertensi h ON h.idcheckin = cm.id_checkin AND h.kd_penyakit LIKE 'I10%'
```

**Output per triwulan:** `label` (Q1-2025, Q2-2025, ...), `total`, `bp_ok`, `bp_no`, `ltfu3`.

---

### 4.3 Tabel Sub-Wilayah

**File:** `ajax_table.php`

#### Query A — Outcome per Kabupaten
```sql
SELECT k.nama_kabupaten, COUNT(*) AS total_pasien,
       SUM(CASE WHEN CAST(h.sistole AS UNSIGNED) < 140
                 AND CAST(h.diastole AS UNSIGNED) < 90
                THEN 1 ELSE 0 END) AS bp_terkontrol,
       SUM(CASE WHEN NOT(CAST(h.sistole AS UNSIGNED) < 140
                     AND CAST(h.diastole AS UNSIGNED) < 90)
                THEN 1 ELSE 0 END) AS bp_tidak
FROM hipertensi h
JOIN checkin c ON c.id_checkin = h.idcheckin
INNER JOIN (
    SELECT c2.norm, c2.kode, MAX(c2.tanggal) AS last_visit
    FROM hipertensi h2 JOIN checkin c2 ON c2.id_checkin = h2.idcheckin
    WHERE h2.kd_penyakit LIKE 'I10%' AND c2.tanggal <= '{tgl_akhir_bln}'
    GROUP BY c2.norm, c2.kode
    HAVING last_visit BETWEEN '{tgl_awal_3bln}' AND '{tgl_akhir_bln}'
) lv ON lv.norm = c.norm AND lv.kode = c.kode AND lv.last_visit = c.tanggal
JOIN faskes f ON f.kode = c.kode
JOIN kabupaten k ON k.kode_kabupaten = f.kode_kabupaten
WHERE h.kd_penyakit LIKE 'I10%'
GROUP BY k.kode_kabupaten, k.nama_kabupaten
ORDER BY k.nama_kabupaten
```

#### Query B — Kumulatif & LTFU per Kabupaten
```sql
SELECT k.nama_kabupaten,
       COUNT(DISTINCT p.norm) AS terdaftar_kumulatif,
       SUM(p.is_baru) AS terdaftar_baru,
       SUM(p.is_ltfu3) AS ltfu_3bulan
FROM (
    SELECT c.norm, c.kode,
           CASE WHEN MIN(c.tanggal) BETWEEN '{tgl_awal_bln}' AND '{tgl_akhir_bln}'
                THEN 1 ELSE 0 END AS is_baru,
           CASE WHEN MAX(c.tanggal) < '{tgl_awal_3bln}' THEN 1 ELSE 0 END AS is_ltfu3
    FROM hipertensi h JOIN checkin c ON c.id_checkin = h.idcheckin
    WHERE h.kd_penyakit LIKE 'I10%'
      AND c.tanggal BETWEEN '{tgl_awal_12bln}' AND '{tgl_akhir_bln}'
    GROUP BY c.norm, c.kode
) p
JOIN faskes f ON f.kode = p.kode
JOIN kabupaten k ON k.kode_kabupaten = f.kode_kabupaten
GROUP BY k.kode_kabupaten, k.nama_kabupaten
ORDER BY k.nama_kabupaten
```

Query yang sama dijalankan ulang dengan `GROUP BY f.kode` untuk mendapat data **per faskes**.

---

## 5. Modul Diabetes

File: `module/diabetes/`
Endpoint: `ajax_summary.php`, `ajax_chart.php`, `ajax_table.php`

---

### 5.1 Ringkasan (Summary)

**File:** `ajax_summary.php`

#### Query 1 — Treatment Outcomes DM
Kunjungan terakhir tiap pasien DM dalam 3 bulan, exclude newly registered.

```sql
SELECT CAST(d.gdp AS DECIMAL(10,2)) AS gdp,
       CAST(d.hba1c AS DECIMAL(10,2)) AS hba1c,
       d.statin, c.jk, CAST(c.umur AS UNSIGNED) AS umur
FROM dm d
JOIN checkin c ON c.id_checkin = d.idcheckin
JOIN (
    SELECT c2.norm, c2.kode, MAX(c2.tanggal) AS tgl_terakhir
    FROM dm d2 JOIN checkin c2 ON c2.id_checkin = d2.idcheckin
    WHERE c2.kode IN ({in_faskes_kode})
      AND c2.tanggal BETWEEN '{tgl_awal_3bln}' AND '{tgl_akhir_bln}'
    GROUP BY c2.norm, c2.kode
) trk ON trk.norm = c.norm AND trk.kode = c.kode AND trk.tgl_terakhir = c.tanggal
INNER JOIN (
    -- Exclude newly registered (< 3 bulan)
    SELECT c3.norm, c3.kode FROM dm d3
    JOIN checkin c3 ON c3.id_checkin = d3.idcheckin
    WHERE c3.kode IN ({in_faskes_kode})
    GROUP BY c3.norm, c3.kode
    HAVING MIN(c3.tanggal) < '{tgl_awal_3bln}'
) fv ON fv.norm = c.norm AND fv.kode = c.kode
WHERE c.kode IN ({in_faskes_kode})
```

Hasil di-loop di PHP untuk hitung `dm_ok`, `dm_no`, `dm_statin`, distribusi gender & usia.

**Logika klasifikasi DM:**
```
dm_ok    = GDP > 0 AND GDP < 126  ATAU  HbA1c > 0 AND HbA1c < 7
dm_berat = bukan dm_ok DAN (GDP >= 200 ATAU HbA1c >= 9)
dm_sedang = bukan dm_ok DAN bukan dm_berat
```

---

#### Query 2 — Scalar: Kumulatif, Baru, LTFU
```sql
SELECT
    COUNT(DISTINCT p.norm) AS terdaftar_alltime,
    COUNT(DISTINCT CASE WHEN p.max_in_12mo IS NOT NULL THEN p.norm END) AS terdaftar_kum,
    COUNT(DISTINCT CASE WHEN p.max_in_12mo IS NOT NULL
                         AND p.min_tgl < '{tgl_awal_3bln}' THEN p.norm END) AS terdaftar_den,
    SUM(CASE WHEN p.min_tgl BETWEEN '{tgl_awal_bln}' AND '{tgl_akhir_bln}'
             THEN 1 ELSE 0 END) AS terdaftar_baru,
    SUM(CASE WHEN p.max_in_12mo IS NOT NULL
              AND p.max_in_12mo < '{tgl_awal_3bln}' THEN 1 ELSE 0 END) AS ltfu_3bln,
    SUM(CASE WHEN p.max_tgl < '{tgl_awal_12bln}' THEN 1 ELSE 0 END) AS ltfu_12bln
FROM (
    SELECT c.norm, c.kode, MIN(c.tanggal) AS min_tgl, MAX(c.tanggal) AS max_tgl,
           MAX(CASE WHEN c.tanggal BETWEEN '{tgl_awal_12bln}' AND '{tgl_akhir_bln}'
                    THEN c.tanggal END) AS max_in_12mo
    FROM dm d JOIN checkin c ON c.id_checkin = d.idcheckin
    WHERE c.kode IN ({in_faskes_kode}) AND c.tanggal <= '{tgl_akhir_bln}'
    GROUP BY c.norm, c.kode
) p
```

---

#### Query 3 — BP Terkontrol untuk Pasien DM
Pasien DM yang juga punya data tekanan darah di 3 bulan terakhir.

```sql
SELECT COUNT(*) AS total,
       SUM(CASE WHEN CAST(ht.sistole AS UNSIGNED)<140
                 AND CAST(ht.diastole AS UNSIGNED)<90 THEN 1 ELSE 0 END) AS bp_ok,
       SUM(CASE WHEN CAST(ht.sistole AS UNSIGNED)<130
                 AND CAST(ht.diastole AS UNSIGNED)<80 THEN 1 ELSE 0 END) AS bp_ok_130
FROM (
    SELECT c.norm, c.kode FROM dm d JOIN checkin c ON c.id_checkin = d.idcheckin
    WHERE c.kode IN ({in_faskes_kode})
      AND c.tanggal BETWEEN '{tgl_awal_3bln}' AND '{tgl_akhir_bln}'
    GROUP BY c.norm, c.kode
) dm_pts
JOIN (
    SELECT ch.norm, ch.kode, MAX(ch.tanggal) AS last_ht_visit
    FROM hipertensi ht2 JOIN checkin ch ON ch.id_checkin = ht2.idcheckin
    WHERE ch.kode IN ({in_faskes_kode})
      AND ch.tanggal BETWEEN '{tgl_awal_3bln}' AND '{tgl_akhir_bln}'
    GROUP BY ch.norm, ch.kode
) lv ON lv.norm = dm_pts.norm AND lv.kode = dm_pts.kode
JOIN checkin cht ON cht.norm = lv.norm AND cht.kode = lv.kode AND cht.tanggal = lv.last_ht_visit
JOIN hipertensi ht ON ht.idcheckin = cht.id_checkin
```

**Output:** `dm_bp_total`, `dm_bp_ok` (<140/90), `dm_bp_ok_130` (<130/80).

---

#### Query 4 — Komorbid DM + HT
```sql
SELECT COUNT(DISTINCT c.norm) AS dm_ht,
       SUM(CASE WHEN (CAST(d.gdp AS DECIMAL)>0 AND CAST(d.gdp AS DECIMAL)<126)
                  OR (CAST(d.hba1c AS DECIMAL)>0 AND CAST(d.hba1c AS DECIMAL)<7)
                THEN 1 ELSE 0 END) AS dm_ht_ok
FROM dm d JOIN checkin c ON c.id_checkin = d.idcheckin
JOIN (
    SELECT c2.norm, c2.kode, MAX(c2.tanggal) AS tgl_terakhir
    FROM dm d2 JOIN checkin c2 ON c2.id_checkin = d2.idcheckin
    WHERE c2.kode IN ({in_faskes_kode})
      AND c2.tanggal BETWEEN '{tgl_awal_3bln}' AND '{tgl_akhir_bln}'
    GROUP BY c2.norm, c2.kode
) trk ON trk.norm = c.norm AND trk.kode = c.kode AND trk.tgl_terakhir = c.tanggal
WHERE c.kode IN ({in_faskes_kode})
  AND EXISTS (
      SELECT 1 FROM hipertensi ht JOIN checkin ch ON ch.id_checkin = ht.idcheckin
      WHERE ch.norm = c.norm
  )
```

---

#### Query 5 — Skrining Oportunistik DM
```sql
SELECT COUNT(DISTINCT c.norm) AS skrining_bln
FROM dm d JOIN checkin c ON c.id_checkin = d.idcheckin
WHERE c.kode IN ({in_faskes_kode})
  AND c.tanggal BETWEEN '{tgl_awal_bln}' AND '{tgl_akhir_bln}'
  AND (CAST(d.gdp AS DECIMAL) > 0 OR CAST(d.hba1c AS DECIMAL) > 0)
```

**Output:** `skrining_bln` (pasien DM dengan GDP atau HbA1c tercatat di bulan aktif).

---

### 5.2 Chart Tren 12 Bulan

**File:** `ajax_chart.php`

#### Query Utama — Fetch Semua Kunjungan DM
```sql
SELECT c.norm, TRIM(c.kode) AS kode, c.tanggal,
       CAST(d.gdp   AS DECIMAL(10,2)) AS gdp,
       CAST(d.hba1c AS DECIMAL(10,2)) AS hba1c,
       d.statin
FROM dm d JOIN checkin c ON c.id_checkin = d.idcheckin
WHERE c.kode IN ({in_faskes_kode}) AND c.tanggal <= '{tgl_akhir_bln}'
ORDER BY c.norm, c.kode, c.tanggal
```

#### Query Pre-fetch BP per Bulan (untuk pasien DM)
```sql
SELECT YEAR(c.tanggal) AS yr, MONTH(c.tanggal) AS mo,
       COUNT(DISTINCT c.norm) AS tot,
       SUM(CASE WHEN CAST(ht.sistole AS UNSIGNED)<140
                 AND CAST(ht.diastole AS UNSIGNED)<90 THEN 1 ELSE 0 END) AS ok,
       SUM(CASE WHEN CAST(ht.sistole AS UNSIGNED)<130
                 AND CAST(ht.diastole AS UNSIGNED)<80 THEN 1 ELSE 0 END) AS ok130
FROM (
    SELECT c2.norm, c2.kode, YEAR(c2.tanggal) AS yr, MONTH(c2.tanggal) AS mo,
           MAX(c2.tanggal) AS lv
    FROM hipertensi ht2 JOIN checkin c2 ON c2.id_checkin = ht2.idcheckin
    WHERE c2.kode IN ({in_faskes_kode})
      AND c2.tanggal BETWEEN '{tgl_awal_12bln}' AND '{tgl_akhir_bln}'
    GROUP BY c2.norm, c2.kode, yr, mo
) lv
JOIN checkin c ON c.norm = lv.norm AND c.kode = lv.kode AND c.tanggal = lv.lv
JOIN hipertensi ht ON ht.idcheckin = c.id_checkin
WHERE c.kode IN ({in_faskes_kode})
GROUP BY yr, mo
```

**Output chart per bulan:**

| Variabel | Keterangan |
|----------|-----------|
| `labels` | Label bulan |
| `dm_ok` | % DM terkontrol dari denominator |
| `dm_no` | % DM tidak terkontrol dari denominator |
| `dm_sedang` | % DM sedang (GDP 126–199 atau HbA1c 7–8.9) |
| `dm_berat` | % DM berat (GDP ≥200 atau HbA1c ≥9) |
| `ltfu3` | % LTFU 3 bulan |
| `ltfu12` | % LTFU 12 bulan |
| `statin` | % pasien dengan resep statin |
| `terdaftar` | Kumulatif alltime |
| `dalam_pwr` | Dalam perawatan 12 bulan |
| `baru` | Pasien baru bulan tersebut |
| `skrining` | Pasien unik berkunjung bulan tersebut |
| `skrining_pct` | % skrining dari dalam perawatan |
| `bp_ok` | % BP <140/90 untuk pasien DM |
| `bp_ok130` | % BP <130/80 untuk pasien DM |

#### Query Kohort Triwulan DM
```sql
SELECT COUNT(*) AS total,
       SUM(CASE WHEN mv.meas_visit IS NOT NULL
                 AND ((CAST(d.gdp AS DECIMAL)>0 AND CAST(d.gdp AS DECIMAL)<126)
                   OR (CAST(d.hba1c AS DECIMAL)>0 AND CAST(d.hba1c AS DECIMAL)<7))
                THEN 1 ELSE 0 END) AS dm_ok,
       SUM(CASE WHEN mv.meas_visit IS NOT NULL
                 AND NOT((CAST(d.gdp AS DECIMAL)>0 AND CAST(d.gdp AS DECIMAL)<126)
                      OR (CAST(d.hba1c AS DECIMAL)>0 AND CAST(d.hba1c AS DECIMAL)<7))
                 AND NOT(CAST(d.gdp AS DECIMAL)>=200 OR CAST(d.hba1c AS DECIMAL)>=9)
                THEN 1 ELSE 0 END) AS dm_no_mod,
       SUM(CASE WHEN mv.meas_visit IS NOT NULL
                 AND (CAST(d.gdp AS DECIMAL)>=200 OR CAST(d.hba1c AS DECIMAL)>=9)
                THEN 1 ELSE 0 END) AS dm_no_sev,
       SUM(CASE WHEN mv.meas_visit IS NULL THEN 1 ELSE 0 END) AS dm_ltfu
FROM (
    SELECT c.norm, c.kode FROM dm d2 JOIN checkin c ON c.id_checkin = d2.idcheckin
    WHERE c.kode IN ({in_faskes_kode})
    GROUP BY c.norm, c.kode
    HAVING MIN(c.tanggal) BETWEEN '{reg_start}' AND '{reg_end}'
) np
LEFT JOIN (
    SELECT c2.norm, c2.kode, MAX(c2.tanggal) AS meas_visit
    FROM dm dm2 JOIN checkin c2 ON c2.id_checkin = dm2.idcheckin
    WHERE c2.kode IN ({in_faskes_kode})
      AND c2.tanggal BETWEEN '{meas_start}' AND '{meas_end}'
    GROUP BY c2.norm, c2.kode
) mv ON mv.norm = np.norm AND mv.kode = np.kode
LEFT JOIN checkin cm ON cm.norm = mv.norm AND cm.kode = mv.kode AND cm.tanggal = mv.meas_visit
LEFT JOIN dm d ON d.idcheckin = cm.id_checkin
```

---

### 5.3 Tabel Sub-Wilayah

**File:** `ajax_table.php`

#### Query Outcome per Kabupaten & Faskes
```sql
SELECT k.nama_kabupaten AS nama_wilayah, f.kode AS fkode, f.nama_faskes,
       COUNT(DISTINCT c.norm) AS dalam_pwr,
       COUNT(DISTINCT CASE WHEN (CAST(d.gdp AS DECIMAL)>0 AND CAST(d.gdp AS DECIMAL)<126)
                             OR (CAST(d.hba1c AS DECIMAL)>0 AND CAST(d.hba1c AS DECIMAL)<7)
                           THEN c.norm END) AS dm_ok,
       COUNT(DISTINCT CASE WHEN NOT((CAST(d.gdp AS DECIMAL)>0 AND CAST(d.gdp AS DECIMAL)<126)
                                 OR (CAST(d.hba1c AS DECIMAL)>0 AND CAST(d.hba1c AS DECIMAL)<7))
                                AND NOT(CAST(d.gdp AS DECIMAL)>=200 OR CAST(d.hba1c AS DECIMAL)>=9)
                           THEN c.norm END) AS dm_sedang,
       COUNT(DISTINCT CASE WHEN CAST(d.gdp AS DECIMAL)>=200
                             OR CAST(d.hba1c AS DECIMAL)>=9
                           THEN c.norm END) AS dm_berat,
       COUNT(DISTINCT CASE WHEN d.statin = 'Diresepkan' THEN c.norm END) AS dm_statin
FROM faskes f
JOIN kabupaten k ON k.kode_kabupaten = f.kode_kabupaten
JOIN checkin c ON c.kode = f.kode
JOIN dm d ON d.idcheckin = c.id_checkin
JOIN (
    SELECT c2.norm, c2.kode, MAX(c2.tanggal) AS tgl_terakhir
    FROM dm d2 JOIN checkin c2 ON c2.id_checkin = d2.idcheckin
    WHERE c2.kode IN ({in_faskes_kode})
      AND c2.tanggal BETWEEN '{tgl_awal_3bln}' AND '{tgl_akhir_bln}'
    GROUP BY c2.norm, c2.kode
) trk ON trk.norm = c.norm AND trk.kode = c.kode AND trk.tgl_terakhir = c.tanggal
WHERE f.kode IN ({in_faskes_kode})
-- GROUP BY k.kode_kabupaten untuk per-kabupaten
-- GROUP BY f.kode untuk per-faskes
```

---

## 6. Definisi Metrik

### Hipertensi

| Metrik | Definisi |
|--------|---------|
| **BP Terkontrol** | Sistolik < 140 mmHg DAN Diastolik < 90 mmHg |
| **Dalam Perawatan** | Pasien dengan kunjungan minimal 1x dalam 12 bulan terakhir |
| **LTFU 3 Bulan** | Pasien dalam perawatan (12 bulan) tapi kunjungan terakhir > 3 bulan lalu |
| **LTFU 12 Bulan** | Pasien terdaftar alltime yang kunjungan terakhirnya > 12 bulan lalu |
| **Newly Registered** | Pasien dengan kunjungan pertama di bulan aktif (< 3 bulan) — exclude dari denominator outcome |
| **Denominator** | Pasien dalam perawatan 3 bulan (exclude newly registered) + LTFU 3 bulan |
| **Skrining** | Pasien unik yang berkunjung di bulan aktif |

### Diabetes

| Metrik | Definisi |
|--------|---------|
| **DM Terkontrol (OK)** | GDP > 0 DAN GDP < 126 mg/dL, ATAU HbA1c > 0 DAN HbA1c < 7% |
| **DM Tidak Terkontrol Sedang** | Bukan OK, dan GDP < 200 dan HbA1c < 9% |
| **DM Tidak Terkontrol Berat** | GDP ≥ 200 mg/dL ATAU HbA1c ≥ 9% |
| **Statin** | Pasien dengan `statin = 'Diresepkan'` |
| **BP <140/90 (DM)** | Pasien DM yang juga punya pengukuran TD <140/90 dalam 3 bulan |
| **BP <130/80 (DM)** | Target BP lebih ketat untuk pasien DM |
| **Skrining Oportunistik** | Pasien DM dengan GDP atau HbA1c tercatat di bulan aktif |

### Kohort Triwulan

| Istilah | Definisi |
|---------|---------|
| **Triwulan Registrasi** | Periode 3 bulan di mana pasien pertama kali terdaftar |
| **Triwulan Pengukuran** | Triwulan berikutnya setelah registrasi |
| **Total Kohort** | Semua pasien yang pertama kali terdaftar di triwulan registrasi |
| **LTFU Kohort** | Pasien dari kohort yang tidak punya kunjungan di triwulan pengukuran |
