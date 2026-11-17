# Setup lokal Laragon

Proyek ditempatkan pada `D:\laragon\www\finwatch-indonesia`. Konfigurasi Apache/MySQL global tidak diubah otomatis; `.env` yang sudah ada dipertahankan.

Pada penyerahan lokal M2–M5, dependency dan aset build sudah dipasang, database `finwatch_indonesia` sudah dimigrasi dan berisi 108 observasi resmi historis. Untuk membuka: nyalakan MySQL Laragon lalu jalankan `scripts/start.ps1` dari folder proyek. Langkah instalasi di bawah dipakai untuk pemasangan ulang.

## Runtime

| Komponen | Kebutuhan / lingkungan pengujian |
|---|---|
| PHP | Laravel 13 memerlukan >=8.3; lokal PHP 8.3.7. PHP 8.1 yang juga terpasang tidak kompatibel. |
| Extension | Ctype, cURL, DOM/XML, fileinfo, filter, hash, mbstring, OpenSSL, PCRE, PDO/pdo_mysql, session, tokenizer, GD, ZIP; intl tersedia lokal. |
| Composer | Gunakan `composer.lock`; Composer 2.8.4 digunakan lokal. |
| Node | Node 24.16.0 untuk install/build/dev; produksi menyajikan aset hasil build. npm 11 digunakan karena npm 10 lokal mengalami bug resolver. |
| Database | MySQL 8 / MariaDB yang kompatibel, InnoDB, utf8mb4. MySQL 8.0.30 tersedia lokal; server perlu dinyalakan. |

GD digunakan untuk validasi/re-encode media WebP dan ZIP untuk spreadsheet/backup. Semua file unggahan disimpan privat; tidak memerlukan symlink storage publik.

## Instalasi

1. Buka Laragon, pilih PHP yang sesuai dan nyalakan MySQL.
2. Buat database khusus melalui HeidiSQL/klien MySQL:

```sql
CREATE DATABASE IF NOT EXISTS finwatch_indonesia CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

3. Periksa `.env`. Jika belum ada, setup membuatnya dari `.env.example`. Contoh lokal:

```dotenv
APP_URL=http://127.0.0.1:8000
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=finwatch_indonesia
DB_USERNAME=root
DB_PASSWORD=
QUEUE_CONNECTION=database
DB_QUEUE_RETRY_AFTER=180
SESSION_DRIVER=database
CACHE_STORE=database
```

Akun root tanpa password hanya asumsi instalasi lokal Laragon; sesuaikan dengan server sebenarnya. Jangan menggunakan konfigurasi ini pada hosting publik.

4. Jalankan dari PowerShell direktori proyek:

```powershell
.\scripts\setup.ps1 -Migrate
.\scripts\start.ps1
```

Buka `http://127.0.0.1:8000`; hentikan server dengan Ctrl+C. Script memilih PHP dari `-PhpPath`, PHP 8.3.7 Laragon, atau PATH. Untuk HMR jalankan `npm run dev` pada terminal lain. Jika dependency sudah terpasang, `setup.ps1 -Migrate -SkipInstall` cukup untuk migrasi/seeder/build.

Migrasi berikutnya memakai `php artisan migrate --seed`; `migrate:fresh` menghapus tabel dan hanya boleh dipakai pada database tes yang memang dapat dibuang. Seeder bersifat idempoten, memuat role, katalog sumber, dan 108 observasi historis resmi; tidak membuat akun admin/artikel demo. Lihat [provenance](DATA_PROVENANCE.md).

## Apache virtual host

Jika memakai Apache Laragon, tinjau konfigurasi berikut sebelum menerapkannya:

```apache
<VirtualHost *:80>
    ServerName finwatch-indonesia.test
    DocumentRoot "D:/laragon/www/finwatch-indonesia/public"
    <Directory "D:/laragon/www/finwatch-indonesia/public">
        AllowOverride All
        Require all granted
        Options -Indexes
    </Directory>
</VirtualHost>
```

Hostname lokal harus mengarah ke `127.0.0.1`, `mod_rewrite` aktif, dan APP_URL sesuai origin. Installer tidak menulis hosts Windows atau sertifikat. Untuk OAuth lokal, gunakan redirect loopback yang diizinkan Google dan selaraskan dengan aplikasi; panduan [Google OAuth](GOOGLE_OAUTH.md) memakai port 8000.

Hanya `public/` yang menjadi web root. `.env`, source aplikasi, `vendor/`, `database/sources/`, log, media privat dan backup tidak boleh dapat diunduh.

## Menjalankan queue dan scheduler lokal

Tombol sinkronisasi membuat job database; proses tidak berjalan sampai worker mengambilnya. Jalankan worker pendek untuk menguji antrean lokal:

```powershell
php artisan queue:work database --stop-when-empty --max-time=120 --max-jobs=5 --timeout=90 --tries=3
```

`DB_QUEUE_RETRY_AFTER=180` lebih panjang daripada timeout job 90 detik. Job memakai retry/backoff; jangan menjalankan worker duplikat tanpa locking. `--max-time` diperiksa antarpekerjaan, sehingga satu job dapat membuat total proses melampaui 120 detik. Hosting produksi harus mendukung batas pekerjaan atau menggunakan fallback manual.

Untuk memicu jadwal satu kali:

```powershell
php artisan schedule:run
php artisan schedule:list
```

Jadwal meliputi publikasi artikel terjadwal dan heartbeat setiap menit, serta pemeriksaan sumber otomatis pukul 06.00 zona `Asia/Jakarta`. Periksa heartbeat di System Health. `schedule:run` sekali tidak membuat scheduler berjalan terus; untuk pengembangan gunakan `php artisan schedule:work` di terminal yang dapat dihentikan, sedangkan produksi memakai cron.

BI/BPS yang belum memiliki URL resmi berformat didukung tetap memakai impor manual. `No New Data` berarti checksum unduhan tidak berubah; tidak membuktikan bahwa arsip historis merupakan publikasi terbaru.

## Backup dan tes

`php artisan finwatch:backup` mencetak lokasi arsip terenkripsi dalam `storage/app/private/backups`. Simpan salinan arsip, APP_KEY dan kunci backup secara terpisah. Prosedur restore yang mengganti data dijelaskan di [deployment](DEPLOYMENT_CPANEL.md); uji dahulu pada database terisolasi.

```powershell
.\scripts\check.ps1
```

Tes memakai SQLite terisolasi dari `phpunit.xml`; tes MySQL harus mengarah ke database khusus. Jangan mengisi DB tes dengan credential database utama. Browser memerlukan server terpisah, lalu `PLAYWRIGHT_BASE_URL` sesuai URL dan `npx playwright test`.

## Troubleshooting

| Gejala | Pemeriksaan |
|---|---|
| PHP tidak ditemukan / versi salah | Gunakan `-PhpPath`; PHP CLI dapat berbeda dari PHP Apache. |
| Database connection refused | Nyalakan MySQL Laragon dan periksa host/port. Script tidak menyalakannya otomatis. |
| Database tidak ditemukan / access denied | Buat database dan periksa username/password. Migrasi membuat tabel, bukan database. |
| Vite manifest tidak ditemukan | Jalankan `npm ci` lalu `npm run build`, atau Vite dev server. |
| Build menunjuk dev server mati | Hentikan Vite dan hapus `public/hot` yang basi, lalu build. |
| HTTP 419 | Origin aplikasi/cookie/CSRF harus selaras; jangan mencampur localhost, loopback, domain `.test`, HTTP dan HTTPS. |
| OAuth belum tersedia | Isi Client ID/secret dan redirect yang tepat; aplikasi tidak menyediakan login pengganti. |
| Admin 403 / verifikasi MFA | Login Google baru selalu reader; provision role lewat CLI tepercaya dan enrollment TOTP. |
| Sinkronisasi tetap queued | Jalankan worker dan periksa failed jobs/System Health. |
| Sinkronisasi manual_required | Sumber belum memiliki URL/adapter otomatis yang didukung; gunakan template impor. |
| Gagal TLS sumber | Periksa CA/cURL/outbound HTTPS; jangan menonaktifkan verifikasi TLS. |
| XLSX resmi berubah | Profil SPI dipatok pada checksum arsip yang ditinjau; kurasi/pemetaan baru perlu review, bukan memaksa parser lama. |

`FINWATCH_OFFICIAL_CA_BUNDLE` dapat menunjuk berkas PEM CA tepercaya jika trust store PHP berbeda dari Windows. Instalasi lokal ini menyimpan sertifikat publik root/intermediate tepercaya Windows di `storage/app/private/certs/windows-trust.pem`. Jangan menyalin path Windows itu ke hosting; gunakan trust store/CA bundle yang valid pada provider. Nilai kosong memakai trust store default, dan konfigurasi ini tidak menyediakan opsi menonaktifkan TLS.
