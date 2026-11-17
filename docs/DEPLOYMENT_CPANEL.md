# Deployment cPanel / Apache

Implementasi dapat disiapkan sebagai rilis lokal; domain/provider cPanel, HTTPS, credential Google dan cron produksi harus diverifikasi pada lingkungan tujuan. Pengujian lokal tidak membuktikan ketersediaan layanan hosting.

## Kompatibilitas

| Area | Kebutuhan yang harus dibuktikan pada provider |
|---|---|
| PHP | >=8.3, versi CLI dan web selaras; gunakan patch yang masih didukung. |
| Extension | PDO MySQL, cURL, mbstring, OpenSSL, DOM/XML, fileinfo, tokenizer, GD, ZIP; kebutuhan Laravel dan dependency sesuai `composer.lock`. |
| Database | MySQL/MariaDB kompatibel, InnoDB, utf8mb4, akun khusus, kuota memadai. |
| Frontend | Aset Vite dibangun lokal/CI; Node server permanen tidak dibutuhkan. |
| Web | Apache, mod_rewrite, document root aman, HTTPS dan origin cookie konsisten. |
| Async | Cron, PHP CLI, locking, outbound TLS/DNS, kemampuan menjalankan dan membatasi job hingga 90 detik. |
| File | `storage/` dan `bootstrap/cache/` writable oleh pemilik proses, kuota/inode dan backup terukur. |
| Auth | Outbound HTTPS Google, consent/redirect URI dan TOTP administrator. |

Validasi `composer check-platform-reqs --no-dev` pada runtime target. Jangan memakai `--ignore-platform-reqs` untuk menutupi ketidakcocokan.

## Layout aman

Utamakan source privat di `/home/ACCOUNT/finwatch` dengan document root `/home/ACCOUNT/finwatch/public`.

Jika provider tidak mengizinkan perubahan document root, simpan source di luar `public_html`; salin hanya isi `public/` ke web root, termasuk `.htaccess`, `index.php`, aset build dan OG image. Sesuaikan path autoload/maintenance/bootstrap serta public path Laravel terhadap lokasi privat yang sebenarnya, lalu uji route dan aset. Jangan menyalin `.env`, `app/`, `vendor/`, database, sumber arsip, storage privat, atau backup ke `public_html`.

Media disajikan melalui controller yang memeriksa akses/referensi artikel. Jangan membuat symlink publik ke `storage/app/private`.

## Build dan konfigurasi

1. Catat source rilis dan hasil tes. Buat backup database/media beserta source rilis sebelumnya.
2. Lokal/CI: `npm ci`, `npm run typecheck`, `npm run lint`, `npm run test`, `npm run build`. Unggah `public/build` bersama aplikasi.
3. Instal PHP dependency dengan `composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction`. Jika Composer tidak tersedia pada host, bangun `vendor/` di lingkungan PHP yang kompatibel.
4. Jangan unggah `node_modules/`, `.git/`, credential build atau `public/hot`.
5. Siapkan database/user khusus dan `.env` privat. Permission mengikuti pemilik proses; jangan membuat seluruh repository world-writable.

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://DOMAIN_ANDA
DB_CONNECTION=mysql
DB_HOST=HOST_DATABASE_PROVIDER
DB_PORT=3306
DB_DATABASE=ACCOUNT_finwatch
DB_USERNAME=ACCOUNT_finwatch
DB_PASSWORD=PASSWORD_DATABASE_KUAT
SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax
QUEUE_CONNECTION=database
DB_QUEUE_RETRY_AFTER=180
CACHE_STORE=database
FINWATCH_ADMIN_MFA_REQUIRED=true
GOOGLE_CLIENT_ID=ISI_DI_SERVER
GOOGLE_CLIENT_SECRET=ISI_DI_SERVER
GOOGLE_REDIRECT_URI=https://DOMAIN_ANDA/auth/google/callback
FINWATCH_BACKUP_KEY=KUNCI_BASE64_32_BYTE_YANG_DISIMPAN_TERPISAH
```

APP_KEY dibuat sekali pada instalasi awal dan dipertahankan lintas rilis/pemulihan. Materi TOTP dan data terenkripsi bergantung pada key tersebut. Jangan menjalankan `key:generate` rutin saat deployment.

`FINWATCH_BACKUP_KEY` opsional: jika kosong, backup menggunakan APP_KEY. Bila dipisahkan, gunakan 32 byte acak yang di-encode dengan prefix `base64:` dan simpan pada secret manager/offline terpisah. Kehilangan kunci yang digunakan membuat arsip tidak dapat dipulihkan; perubahan kunci tidak mengenkripsi ulang arsip lama.

6. Jalankan `php artisan migrate --force --seed`, lalu `config:cache`, `route:cache`, `view:cache`. Hentikan rilis bila ada command gagal. Seeder memasukkan snapshot historis, bukan fetch publikasi terkini saat deployment.
7. Konfigurasi OAuth, provision akun yang telah login dan enrollment MFA mengikuti [GOOGLE_OAUTH](GOOGLE_OAUTH.md).

## Scheduler dan worker terbatas

Gunakan path PHP CLI provider yang benar. Contoh cron berikut memerlukan `flock` yang tersedia dan direktori log privat:

```cron
* * * * * flock -n /home/ACCOUNT/finwatch-scheduler.lock /PATH/TO/PHP /home/ACCOUNT/finwatch/artisan schedule:run >> /home/ACCOUNT/logs/finwatch-scheduler.log 2>&1
*/2 * * * * flock -n /home/ACCOUNT/finwatch-worker.lock /PATH/TO/PHP /home/ACCOUNT/finwatch/artisan queue:work database --stop-when-empty --max-time=120 --max-jobs=5 --timeout=90 --tries=3 >> /home/ACCOUNT/logs/finwatch-worker.log 2>&1
```

Tidak ada daemon produksi yang wajib terus berjalan. Scheduler menjalankan publikasi artikel/heartbeat setiap menit dan pemeriksaan sumber otomatis pukul 06.00 `Asia/Jakarta`. Worker mengambil job dari tabel `jobs`. Masing-masing lock mencegah proses cron yang sama bertumpuk.

Timeout job sinkronisasi adalah 90 detik, dengan retry/backoff. `DB_QUEUE_RETRY_AFTER=180` harus lebih panjang dari timeout. `--max-time=120` diperiksa di antara job, sehingga proses dapat melampaui 120 detik saat menyelesaikan satu job. Provider harus mampu menjalankan job tersebut dan mendukung mekanisme timeout PHP CLI; periksa dukungan `pcntl`/batas proses pada Linux. Jika batas provider lebih ketat, gunakan importer manual atau worker terpisah yang sesuai; jangan menurunkan timeout tanpa menguji parser/download nyata.

Bila `flock` tidak tersedia, gunakan fasilitas locking setara yang sudah diuji; jangan memasang cron worker duplikat. Uji dengan sebuah sinkronisasi nyata dan periksa logs/status/heartbeat. Keberadaan cron saja tidak membuktikan job selesai.

## Sinkronisasi dan review data

Unduhan otomatis memerlukan HTTPS, domain sumber yang diizinkan dan adapter yang sesuai. Setiap redirect diperiksa; endpoint privat/credential URL ditolak. Batas berkas unduhan 10 MB, XLSX normalisasi maksimal 5.000 baris/20 kolom dan 30 MB isi ZIP. Profil SPI hanya menerima checksum arsip Juni 2025 yang ditinjau dan memakai cached values tanpa evaluasi formula. Tidak ada bypass CAPTCHA.

Job menyimpan snapshot privat dan menghasilkan versi `needs_review` atau daftar masalah. Pengelola meninjau dan mempublikasikan secara eksplisit. Versi gagal tidak mengganti dataset publik. `no_new_data` berarti checksum sama; `manual_required` mengarahkan pengelola ke impor manual dari sumber resmi. BI/BPS belum menjadi integrasi live API. Rincian di [DATA_PROVENANCE](DATA_PROVENANCE.md).

## Backup terenkripsi dan restore

Command berikut membuat snapshot tabel aplikasi dan aset media/sumber pada storage privat:

```sh
php artisan finwatch:backup
```

Output adalah path `.finwatch` pada `storage/app/private/backups`. Arsip menggunakan enkripsi terautentikasi; manifest menyimpan skema dan checksum aset. Session, queue/cache sementara serta migrasi dikecualikan. Batas aplikasi: ZIP backup 25 MB, berkas terenkripsi untuk restore 40 MB, isi ZIP restore 100 MB. Untuk instalasi lebih besar gunakan fasilitas backup DB/media provider yang juga telah diuji. Backup aplikasi tidak menggantikan salinan `.env`, APP_KEY, FINWATCH_BACKUP_KEY dan source rilis pada penyimpanan terpisah.

Lakukan drill pada salinan lingkungan yang terisolasi dan pada versi source/skema yang sama:

1. Catat jumlah dataset/observasi/artikel dan media contoh. Buat backup, salin keluar server ke penyimpanan privat dan simpan kuncinya terpisah.
2. Siapkan database target khusus pemulihan, runtime/source versi sama dan APP_KEY yang sesuai dengan data TOTP. Hentikan worker/scheduler serta blokir perubahan selama pemulihan.
3. Masuk maintenance mode, pastikan `.env` menunjuk target yang benar, bersihkan config cache jika koneksi berubah, lalu jalankan migrasi skema.
4. Jalankan pemulihan hanya pada target yang telah dipastikan:

```sh
php artisan down
php artisan finwatch:restore /PRIVATE/PATH/backup.finwatch --replace --confirm=RESTORE
php artisan config:cache
php artisan up
```

`--replace` mengganti isi tabel target; tanpa opsi itu target berisi data ditolak. `--confirm=RESTORE` wajib. Arsip rusak/kunci salah/skema berbeda/checksum aset berbeda ditolak. Restore mencabut seluruh sesi dengan menghapus session dan menaikkan auth_version; login ulang diperlukan.

5. Periksa ulang jumlah data, checksum/provenance, artikel/cover/thumbnail, akses reader/admin, TOTP dan audit `system.backup_restored`. Uji satu mutasi dan pastikan worker/heartbeat kembali berjalan. Catat durasi dan hasil roundtrip.

APP_KEY lama tetap diperlukan untuk membaca materi TOTP yang dipulihkan meskipun arsip memakai backup key terpisah. Jangan mencantumkan secret atau isi arsip pada log publik. Proses backup ini belum mencakup konfigurasi DNS, sertifikat atau akun provider.

## Pemeriksaan rilis dan rollback

- Buktikan HTTP 404/403 pada path privat, draft dan preview tanpa signature; periksa HTTPS, cookie, CSRF, CSP dan ketiadaan stack trace.
- Uji login Google nyata, MFA, reader ditolak admin, pencabutan role/sesi dan alur moderasi.
- Periksa metadata artikel pada HTML awal, canonical, sitemap, slug redirect dan PDF, lalu layout mobile/keyboard.
- Buktikan migrasi, seed idempoten, queue, jadwal artikel, heartbeat dan backup/restore pada provider.
- Catat periode data yang sebenarnya, sumber yang masih manual, batas model dan status verifikasi.

Simpan source/vendor/aset sebelumnya dan snapshot DB sebelum migrasi. Bila rilis gagal, gunakan maintenance mode dan pulihkan source yang cocok dengan skema/data. Jangan otomatis `migrate:rollback` pada database yang menerima data baru. Rilis produksi baru dinyatakan terverifikasi setelah pemeriksaan lingkungan tujuan berhasil.
