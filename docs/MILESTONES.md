# Milestone dan hasil verifikasi

Implementasi dilanjutkan pada 26 Oktober 2026 untuk M2–M5. Lokasi proyek yang diminta: `D:\laragon\www\finwatch-indonesia`. Rilis lokal dan kesiapan produksi dibedakan; credential Google dan provider hosting belum tersedia untuk verifikasi langsung.

| Milestone | Hasil implementasi | Batas verifikasi |
|---|---|---|
| M0 | Laravel/React SPA, API, migrasi, design system, responsif, headers dan layout Apache aman. | Provider cPanel belum diuji. |
| M1 | Google OAuth berbasis subject/state, Sanctum session, RBAC, TOTP admin, audit, pencabutan sesi. | Provider mock diuji; login Google langsung menunggu credential/consent/redirect nyata. |
| M2 | Dashboard seri resmi, grafik/tabel/provenance, filter tahun, explorer/CSV, kamus indikator, import/review/versi/rollback. Seed 108 observasi/4 dataset historis. | Snapshot bukan statistik terkini 2026; data regional yang belum tersedia tidak dibuat. |
| M3 | CMS Tiptap, sanitasi server, workflow/revisi/jadwal, media privat/thumbnail, metadata HTML awal, sitemap, slug redirect/PDF, komentar bertingkat/like/laporan/moderasi. | Tidak ada akun, penulis atau artikel demo yang dipublikasikan otomatis. Alur dengan identitas Google nyata perlu uji setelah konfigurasi. |
| M4 | Adapter per sumber, queue database, log/status/retry/checksum, staging sebelum publikasi, aturan EWS berversi dan simulasi sensitivitas. | Profil SPI dipatok pada arsip ditinjau. BI/BPS dan format tanpa pemetaan memakai impor manual; tidak ada klaim live API/CAPTCHA bypass. |
| M5 | Backup terautentikasi terenkripsi, restore berskema sama/pencabutan sesi, jadwal/heartbeat, panduan rilis/cron/drill/demonstrasi dan workflow CI. | Kesiapan produksi menunggu uji provider, HTTPS/OAuth, cron/job nyata, quota dan drill restore lingkungan tujuan. |

## Bukti pengujian lokal

Pemeriksaan rilis lokal dilakukan pada 26 Oktober 2026. Identitas/isi editorial fixture terisolasi dari database aplikasi.

| Pemeriksaan | Hasil tercatat |
|---|---|
| Runtime | PHP 8.3.7, Laravel 13.38.0, Composer 2.8.4, Node 24.16.0; npm 11 dipakai karena resolver npm 10 lokal bermasalah. |
| PHPUnit SQLite | 72 tests / 576 assertions lulus pada suite gabungan. |
| FinancialEngineTest | 11 tests mencakup 108 observasi resmi/idempotensi, timestamp UTC, satuan, pp/persen, lag, baseline z-score, stres, URL, XLSX/formula, checksum dan kegagalan sync. |
| PHPUnit MySQL | 72 tests / 576 assertions lulus pada MySQL 8.0.30, database khusus finwatch_codex_test_a174d8. Migrasi maju/mundur diuji; penamaan schema backup dibatasi ke database aktif. |
| Frontend | TypeScript strict, ESLint, 8 tests Vitest dan production Vite build lulus. Tiptap/Leaflet dimuat per modul; seluruh chunk akhir di bawah 500 kB sebelum gzip. |
| Browser | 21/21 Playwright lulus pada 320/768/1440px: data nyata, CSV, CSRF/stres/perbandingan, inklusi/konsumen, serta fixture artikel/thread/editor/sync/moderasi/izin. Tidak ada overflow atau pageerror pada cakupan tes. |
| Backup/restore | Roundtrip, kunci salah dan tamper lulus. Arsip aplikasi Laragon nyata dipulihkan ke database uji: 108 observasi, 4 dataset, 4 versi, 4 snapshot; akun demo 0. Drill provider produksi belum dilakukan. |
| Apache/cache/queue | Proyek final D: dilayani Apache/PHP 8.3; API 108 observasi. Root salah arah /.env dan /composer.json ditolak 403. config/route/view cache berhasil lalu dibersihkan. Worker nyata mengambil arsip OJK melalui HTTPS, SHA256 cocok dan status no_new_data 100%. Scheduler/jadwal publikasi/heartbeat dieksekusi lokal. |
| CI | Workflow ada di repository; eksekusi di GitHub belum diverifikasi. |
| OAuth live / cPanel | Belum diuji karena credential/provider/domain belum tersedia. |

Database aplikasi `finwatch_indonesia` pada MySQL Laragon dibuat, dimigrasi dan di-seed dua kali (idempoten). Nilai `.env` yang sudah ada dipertahankan; konfigurasi CA lokal ditambahkan untuk memakai root dan intermediate CA tepercaya Windows. Verifikasi TLS selalu aktif. Server pengujian sementara dihentikan setelah pemeriksaan.

Pemeriksaan persetujuan otomatis menolak usulan unggahan log aplikasi ke artefak GitHub karena potensi paparan informasi internal. Workflow CI akhir menjalankan tes tanpa langkah unggahan log. Tidak ada log/credential yang dipublikasikan.

Tests dan storage fixture tidak merupakan seed publik atau autentikasi bypass. Identitas admin browser yang di-mock tidak memberikan akses backend sesungguhnya. Semua API mutasi menggunakan session/CSRF, permission server dan MFA sesuai konfigurasi.

## Data dan interpretasi

Rincian pemetaan/hashes tersedia pada [DATA_PROVENANCE](DATA_PROVENANCE.md). SPI menyediakan 91 titik, SNLIK 12, NPL satu dan konsumen empat. Tanggal observasi berbeda dari tanggal rilis serta retrieval. Seed dapat diulang tanpa mengganti versi aktif; sumber dan kutipan data tetap resmi/historis.

EWS adalah aturan riset yang dapat dijelaskan, bukan rating regulator. Simulasi memakai kredit sebagai proksi ATMR dan kerugian tambahan hipotetis; asumsi/batas ditampilkan. Tidak ada composite rating, ranking bank/provinsi, interpolasi, atau forecast institusi yang diklaim tersedia.

## Menjalankan ulang dan acceptance produksi

Gunakan `scripts/check.ps1` untuk PHP/format/lint/TypeScript/Vitest/build. Browser memerlukan server dahulu dan `PLAYWRIGHT_BASE_URL` yang sesuai. MySQL `RefreshDatabase` hanya boleh memakai database khusus tes. Di Windows sandbox, PHP dapat melaporkan akses file secara keliru; tes dijalankan pada eksekusi Windows normal tanpa menonaktifkan TLS.

Sebelum menyatakan produksi terverifikasi, selesaikan acceptance pada [DEPLOYMENT_CPANEL](DEPLOYMENT_CPANEL.md): domain HTTPS/document root privat, login Google nyata + MFA, RBAC/CSRF, cron heartbeat dan job nyata, alur redaksi/moderasi, quota, serta backup/restore berisi media pada skema yang sama. Kunci, credential dan file `.env` tidak masuk source publik atau laporan.
