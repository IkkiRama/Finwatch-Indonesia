# FINWATCH Indonesia

Platform riset sektor jasa keuangan, edukasi, dan diskusi publik yang independen. FINWATCH tidak berafiliasi dengan OJK dan tidak memberikan rating resmi kesehatan bank atau rekomendasi investasi.

Implementasi lokal mencakup **M0–M5**: dashboard bersumber resmi, impor dan versi dataset, EWS berbasis aturan, simulasi sensitivitas, CMS artikel, komentar bertingkat, moderasi, sinkronisasi melalui queue, audit, serta backup terenkripsi. Status pengujian dan batas produksi dicatat dalam [MILESTONES](docs/MILESTONES.md). Login Google nyata dan deployment provider cPanel memerlukan konfigurasi serta verifikasi di lingkungan tujuan.

## Stack dan lokasi

- Laravel 13 / PHP >=8.3, MySQL/MariaDB, Sanctum session cookie, Socialite Google, TOTP administrator.
- React 19, Vite, TypeScript strict, Tailwind CSS, TanStack Query, Recharts, Leaflet, Tiptap.
- PhpSpreadsheet untuk CSV/XLSX, HTMLPurifier untuk konten, Dompdf untuk ekspor artikel.
- Session/cache/queue database; hasil build frontend disajikan Apache tanpa server Node produksi.
- Instalasi yang diminta: `D:\laragon\www\finwatch-indonesia`.

## Jalankan di Laragon

1. Pilih PHP 8.3 atau lebih baru dan nyalakan MySQL melalui Laragon.
2. Buat database `finwatch_indonesia` dengan charset `utf8mb4`.
3. Periksa `.env`: `APP_URL`, koneksi database, serta credential Google bila login akan digunakan. File `.env` yang sudah ada dipertahankan.
4. Dari PowerShell pada direktori proyek:

```powershell
.\scripts\setup.ps1 -Migrate
.\scripts\start.ps1
```

Buka `http://127.0.0.1:8000`. Setup memasang dependency, membuat APP_KEY hanya bila kosong, menjalankan migrasi/seeder dan build. Jika dependency/aset sudah tersedia, gunakan `-SkipInstall` atau `-SkipBuild` sesuai kebutuhan. Berikan `-PhpPath 'D:\laragon\bin\php\php-8.3.7-Win32-vs16-x64\php.exe'` bila PHP terminal belum sesuai.

Apache Laragon harus menggunakan document root `D:/laragon/www/finwatch-indonesia/public`. Lihat [setup lokal](docs/LOCAL_LARAGON.md) untuk virtual host, worker terbatas, scheduler, dan troubleshooting.

## Data awal dan publikasi

Seeder memuat **108 observasi resmi historis dalam 4 dataset**: SPI Juni 2025, NPL Juni 2025, SNLIK 2024–2025 Metode Keberlanjutan, dan pelindungan konsumen 2025. Angka tersebut bukan data terbaru 2026 atau data waktu nyata. Unit, periode, lingkup, rilis, pengambilan dan vintage disimpan per observasi; sumber dan SHA-256 dijelaskan di [DATA_PROVENANCE](docs/DATA_PROVENANCE.md).

BI/BPS dan publikasi yang formatnya belum dipetakan memakai impor manual CSV/XLSX tervalidasi. Sinkronisasi otomatis hanya berjalan pada URL resmi yang didukung. Data masuk sebagai versi staging, menunggu review sebelum menjadi publik; versi lama dapat dipulihkan. Sumber tanpa unduhan yang didukung menampilkan `manual_required`.

Artikel, profil penulis, dan akun admin tidak dibuat sebagai konten demo. Pengelola mengisi CMS setelah login Google dan memperoleh izin. Komentar baru menunggu moderasi; komentar publik bukan kanal pengaduan resmi OJK.

## Identitas dan operasional

Pengunjung dapat membaca publikasi dan bereksperimen dengan simulasi tanpa akun. Google diperlukan untuk interaksi komunitas. Akun baru selalu reader; tidak ada password admin bawaan atau promosi otomatis berdasarkan email. Ikuti [GOOGLE_OAUTH](docs/GOOGLE_OAUTH.md) untuk konfigurasi, provision super admin dan enrollment MFA.

Produksi menggunakan database queue, scheduler, dan worker pendek dari cron. Simpan APP_KEY serta kunci backup terpisah dari arsip. Panduan deployment, pemulihan dan batas shared hosting ada di [DEPLOYMENT_CPANEL](docs/DEPLOYMENT_CPANEL.md).

## Verifikasi

```powershell
.\scripts\check.ps1
```

Script memeriksa PHP, format, TypeScript, ESLint, Vitest dan build. PHPUnit menggunakan database tes terisolasi; jangan arahkan `RefreshDatabase` ke database aplikasi/produksi.

Untuk browser, jalankan server aplikasi terpisah dan set URL yang sesuai:

```powershell
$env:PLAYWRIGHT_BASE_URL = 'http://127.0.0.1:8000'
npx playwright test
```

Pasang browser sekali dengan `npx playwright install chromium` bila diperlukan. `PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH` dapat menunjuk Chromium khusus. Fixture admin hanya digunakan dalam tes browser; tidak ada route bypass autentikasi pada aplikasi.

## Dokumentasi

- [PRD](docs/PRD.md), [milestone dan bukti verifikasi](docs/MILESTONES.md).
- [Laragon](docs/LOCAL_LARAGON.md), [Google OAuth](docs/GOOGLE_OAUTH.md), [cPanel dan backup](docs/DEPLOYMENT_CPANEL.md).
- [Provenance dan metodologi data](docs/DATA_PROVENANCE.md), [panduan demonstrasi](docs/DEMO_GUIDE.md).
- [Checklist keamanan/deployment](docs/SECURITY_DEPLOYMENT_CHECKLIST.md), [acceptance fondasi M0–M1](docs/M0-M1-ACCEPTANCE.md).

© tahun berjalan FINWATCH Indonesia. Made with love by [Rifki Romadhan](https://www.instagram.com/george_ikki/).
