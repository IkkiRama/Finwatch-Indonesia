# Provenance dan metodologi data

Dataset awal berisi publikasi resmi **historis** yang dikurasi untuk menunjukkan alur riset yang dapat ditelusuri. Ini bukan data terbaru 2026, data waktu nyata, atau representasi seluruh publikasi OJK/BI/BPS. Waktu observasi, rilis, pengambilan, impor, dan publikasi versi mempunyai arti berbeda.

## Inventaris awal

| Kode dataset | Sumber / vintage | Observasi | Cakupan |
|---|---|---:|---|
| `ojk-spi-june-2025` | Statistik Perbankan Indonesia, Juni 2025 | 91 | 7 indikator × 13 bulan, Juni 2024–Juni 2025; agregat bank umum Indonesia. |
| `ojk-npl-june-2025` | RDKB Agustus 2025, pembanding Juni 2025 | 1 | NPL Gross Indonesia, Juni 2025. |
| `snlik-2025-keberlanjutan` | SNLIK 2025, Metode Keberlanjutan | 12 | Literasi/inklusi nasional 2024 dan 2025; strata perkotaan/perdesaan dan laki-laki/perempuan 2025. |
| `ojk-consumer-august-2025` | RDKB Agustus 2025 | 4 | APPK dan entitas ilegal, periode kumulatif yang berbeda. |
| **Total** | **4 dataset** | **108** | Tidak ada observasi buatan, interpolasi, akun atau artikel demo. |

BI dan BPS tersedia sebagai referensi/adapter sumber dengan fallback manual. Belum ada dataset live API BI/BPS yang diklaim terintegrasi. SNLIK/PEPK juga mendukung impor CSV/XLSX normalisasi dari publikasi resmi; keberadaan adapter tidak berarti semua format sumber otomatis terbaca.

## Arsip sumber dan integritas

Berkas asli berada pada `database/sources/` dan salinan runtime privat pada `storage/app/private/snapshots/`. SHA-256 berikut dihitung pada berkas yang ditinjau. Waktu pengambilan awal dicatat dari mtime saat capture, kemudian dipatok dalam seeder agar tidak berubah ketika Git checkout/deployment mengubah mtime.

| Berkas | Byte | Pengambilan awal (UTC) | SHA-256 |
|---|---:|---|---|
| `ojk-spi-june-2025.xlsx` | 2.679.688 | 2026-10-25T21:44:55Z | `2324b4890eb1a062607f77e7e98fb2e6d444aa951e5fe6f9a4015e648c8f714b` |
| `ojk-spi-june-2025.html` | 386.377 | 2026-10-25T21:44:51Z | `baba8574cadd2c523113b1c4886245e9fc7c056a3eadeb4fa2b92a0dffc61abc` |
| `ojk-snlik-2025.html` | 441.419 | 2026-10-25T21:44:56Z | `bb142ebda7b429f0c5da5826847d1a34b6239971c5ff6f8f695e90ecf53d056b` |
| `ojk-rdk-august-2025.html` | 479.540 | 2026-10-25T22:07:07Z | `f4b6d8b7e92f9312650c6c85ce00bb116e0f5d0a50e86f7c5951009089835809` |

HTML halaman SPI mengidentifikasi publikasi/rilis; workbook berisi tabel yang digunakan. Waktu UTC tersebut setara pagi 26 Oktober 2026 di WIB. Waktu tersebut adalah pengambilan arsip untuk proyek, bukan tanggal statistik diterbitkan.

Sumber primer:

- [Halaman SPI Juni 2025](https://ojk.go.id/id/kanal/perbankan/data-dan-statistik/statistik-perbankan-indonesia/Pages/Statistik-Perbankan-Indonesia---Juni-2025.aspx), rilis 29 Agustus 2025; [workbook resmi](https://ojk.go.id/id/kanal/perbankan/data-dan-statistik/statistik-perbankan-indonesia/Documents/Pages/Statistik-Perbankan-Indonesia---Juni-2025/STATISTIK%20PERBANKAN%20INDONESIA%20%20-Juni%202025.xlsx).
- [Pengumuman OJK/BPS SNLIK 2025](https://ojk.go.id/id/berita-dan-kegiatan/siaran-pers/Pages/OJK-dan-BPS-Umumkan-Hasil-Survei-Nasional-Literasi-dan-Inklusi-Keuangan-SNLIK-Tahun-2025.aspx), rilis 2 Mei 2025.
- [RDKB Agustus 2025](https://ojk.go.id/id/berita-dan-kegiatan/siaran-pers/Pages/RDKB-Agustus-2025.aspx), rilis 4 September 2025.

## Pemetaan SPI

`OjkSpiAdapter` membaca worksheet `Kinerja_KBMI 1.16.-1.20.`, Tabel 1.16 Kinerja Bank Umum. Kolom G:S dipetakan ke akhir bulan Juni 2024–Juni 2025; N:S adalah Januari–Juni 2025. Header edisi diperiksa sebelum membaca.

| Kode | Baris | Satuan sumber | Satuan tersimpan |
|---|---:|---|---|
| `car` | 4 | % | % |
| `roa` | 10 | % | % |
| `nim` | 16 | % | % |
| `ldr` | 19 | % | % |
| `credit` | 20 | Rp miliar | Rp triliun, dibagi 1.000 |
| `deposits` | 21 | Rp miliar | Rp triliun, dibagi 1.000 |
| `assets` | 25 | Rp miliar | Rp triliun, dibagi 1.000 |

Contoh hasil Juni 2025: kredit 8.059,79275870 Rp triliun dan CAR 25,80745792%. Pembulatan tampilan tidak mengubah nilai tersimpan delapan desimal.

Arsip mengandung external-link metadata bawaan workbook. Profil khusus hanya menerima checksum arsip yang sudah ditinjau di atas dan memakai nilai/cache formula yang tersimpan; tidak menghitung formula atau mengambil link workbook. Perubahan byte arsip ditolak untuk review profil baru. Importer XLSX umum tetap menolak externalLinks, VBA dan embedded content.

SPI mulai periode berikutnya memerlukan sumber/profil yang sesuai dengan Portal Data OJK. Memeriksa ulang arsip Juni 2025 dan menemukan checksum sama tidak membuktikan tidak ada rilis baru pada portal.

## SNLIK dan pelindungan konsumen

SNLIK memakai **Metode Keberlanjutan**. Nasional 2024: literasi 65,43%, inklusi 75,02%; 2025: 66,46% dan 80,51%. Nasional 2024–2025 dibandingkan sesuai pernyataan rilis. Cakupan metode DNKI berbeda dan tidak dicampur.

Untuk 2025, strata tersimpan: perkotaan 70,89%/83,61%, perdesaan 59,60%/75,70%, laki-laki 67,32%/80,73%, perempuan 65,58%/80,28% (literasi/inklusi). Survei berlangsung 22 Januari–11 Februari 2025, 10.800 responden usia 15–79. Tanggal `01-01` adalah penanda periode tahunan, bukan tanggal wawancara. Strata tersebut bukan provinsi; peta tidak membuat ranking/nilai provinsi dari angka nasional.

NPL Gross Juni 2025 sebesar 2,22% disebut sebagai pembanding dalam rilis RDKB. Hanya satu observasi ini dikurasi; tidak ada inferensi tren dari satu titik.

Data konsumen memakai `frequency=snapshot`:

| Indikator | Nilai | Jendela kumulatif |
|---|---:|---|
| Permintaan layanan APPK | 318.908 layanan | 1 Januari–15 Agustus 2025 |
| Pengaduan APPK | 31.456 pengaduan | 1 Januari–15 Agustus 2025 |
| Pengaduan entitas ilegal | 14.634 pengaduan | 1 Januari–29 Agustus 2025 |
| Entitas/penawaran ilegal dihentikan | 1.840 | 1 Januari–29 Agustus 2025 |

Permintaan layanan sudah mencakup pengaduan APPK, sehingga keduanya tidak dijumlahkan kembali. Pengaduan entitas ilegal berbeda lingkup dari APPK. Jumlah 1.840 terdiri dari 1.556 pinjol ilegal dan 284 penawaran investasi ilegal; bukan jumlah situs atau nilai kerugian. Perbedaan cutoff membuat seri ini tidak layak dibandingkan sebagai pertumbuhan bulanan otomatis.

## Impor, versi dan publikasi

Template CSV/XLSX memakai kolom wajib:

```text
indicator_code,label,period_date,value,unit,frequency,scope,source_url,release_date,retrieved_at,dataset_version
```

Kolom tambahan: `methodology_note,sector,segment`. Gunakan tanggal ISO `YYYY-MM-DD`, nilai desimal bertitik dan provenance HTTPS resmi. Impor menolak formula numerik, tanggal masa depan/rilis sebelum observasi, persen di luar 0–100, duplikasi seri-periode-lingkup-segmen, dan perubahan satuan/frekuensi/sektor untuk kode indikator yang telah terdaftar.

Batas normalisasi: unduhan/unggahan 10 MB, 5.000 baris, 20 kolom, isi ZIP XLSX 30 MB, payload normalisasi 3 MB. Importer membaca satu worksheet tabel dan tidak mengevaluasi formula. Ekspor CSV menyertakan provenance serta perlindungan terhadap formula spreadsheet pada sel teks.

Setiap versi menyimpan checksum normalisasi, snapshot raw privat, baris dan masalah validasi. Checksum sama pada dataset yang sama idempoten. Versi valid tetap menunggu review. Publikasi memperbarui pointer versi saat ini secara transaksional; versi lama dipertahankan untuk rollback. Query publik hanya memakai versi aktif yang dipublikasikan. Seeder tidak mengganti dataset yang sudah memiliki versi aktif.

## Perhitungan dan batas analisis

Seri dipisahkan berdasarkan dataset, indikator, lingkup, segmen dan frekuensi. Tidak ada pengisian nilai hilang. Perubahan rasio dihitung dalam percentage point; level positif memakai `100 × (baru/lama − 1)`. Perubahan otomatis memerlukan metode dan satuan sama serta lag satu bulan untuk monthly, tiga bulan untuk quarterly, atau 12 bulan untuk annual. Celah periode membuat perubahan tidak dapat dibandingkan; snapshot/ytd tidak diberi perubahan berurutan otomatis. Perubahan antarperiode bukan otomatis pertumbuhan tahunan.

Z-score dan pita historis bersifat deskriptif. Baseline memakai maksimal 24 observasi **sebelum** titik terbaru dengan minimum 12 observasi, unit/definisi sama dan periode berurutan. Titik terbaru tidak ikut mean/baseline. Simpangan baku dihitung sebagai populasi: `σ = sqrt(sum((x − mean)²) / n)`, `z = (terbaru − mean) / σ`, dan pita `mean ± 2σ`. Jika sampel tidak cukup, ada celah/perubahan definisi, atau σ nol, z-score dan pita tidak ditampilkan sebagai angka valid. Pita ini bukan interval prediksi, interval keyakinan atau batas regulator. Filter tahun dapat mengurangi jumlah observasi sehingga statistik tidak lagi memenuhi syarat.

EWS `rules-1.0` mengevaluasi operator `gt`, `lt`, atau `change_gt`, jumlah minimum observasi, versi aturan, input vintage dan waktu evaluasi. `incomparable_data` atau `insufficient_data` bukan sinyal aman. Aturan awal LDR menggunakan kenaikan di atas 3 pp/minimal 6 observasi sebagai parameter eksplorasi penelitian; batas tersebut bukan threshold kesehatan regulator. Tidak ada composite rating yang diklaim tersedia tanpa metodologi/data memadai.

Simulasi `sensitivity-1.0` memakai kredit sebagai proksi ATMR:

```text
Tambahan kerugian = kredit × (shock NPL pp / 100) × (LGD persen / 100)
Modal proksi awal = kredit × CAR awal / 100
Modal proksi akhir = modal awal − tambahan kerugian
CAR proksi akhir = 100 × modal akhir / kredit
NPL akhir = NPL awal + shock NPL pp
```

Model hanya menghitung kerugian tambahan dari shock, tidak mengulang kerugian NPL awal. Kredit positif diwajibkan; NPL akhir tidak boleh melampaui 100%. Modal akhir dapat negatif sebagai hasil hipotetis. Model mengabaikan profit, provisi awal, bobot risiko, mitigasi dan respons perilaku; bukan perhitungan CAR regulator atau prakiraan institusi. Baseline resmi hanya digunakan bila kredit/NPL/CAR mempunyai periode yang sama.
