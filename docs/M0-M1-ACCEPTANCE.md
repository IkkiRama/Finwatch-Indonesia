# Catatan acceptance M0–M1

Dokumen ini diturunkan dari `FINWATCH_PRD_V2.md` dan `FINWATCH_SECURITY_CHECKLIST.md`. Statusnya adalah daftar verifikasi, bukan pernyataan bahwa pengujian telah lulus.

## Cakupan yang harus tersedia

| Area | Bukti acceptance |
|---|---|
| Fondasi | Satu repository Laravel + React SPA Vite, TypeScript strict, REST `/api/v1`, build statis yang disajikan Laravel, lockfile dependency. |
| Database | Migrasi MySQL/MariaDB dengan FK/indeks; session dan queue database; seed role/permission dapat diulang tanpa duplikasi. SQLite hanya untuk tes terisolasi, bukan bukti kompatibilitas MySQL. |
| Publik | Navigasi modul yang jelas, empty state jujur ketika observasi belum tersedia, tanpa nilai keuangan sintetis, tahun filter berasal dari observasi tervalidasi. |
| Identitas | Socialite Google memakai session/state; callback hanya menerima identitas provider dengan email terverifikasi; pasangan provider + subject unik menjadi kunci identitas. |
| Otorisasi | Akun baru reader; semua API admin memiliki autentikasi dan Gate/Policy granular di backend; role dari browser tidak dipercaya. |
| Bootstrap admin | Command CLI menargetkan akun/subject yang telah diverifikasi, tidak membentuk admin dari email semata; perubahan privilege diaudit. |
| Session | Regenerasi session saat login, invalidasi saat logout, CSRF untuk mutasi, HttpOnly/SameSite; Secure pada HTTPS produksi; redirect tujuan hanya path lokal yang diizinkan. |
| Admin shell | Sidebar collapsible, topbar/breadcrumb, layout responsif, halaman ringkasan/aktivitas/audit yang memakai API dan state loading/error/empty nyata. Area M2–M4 diberi status belum tersedia dan tidak memiliki tombol aksi palsu. |
| Audit | Login/logout dan perubahan izin memiliki actor, waktu, event, target yang cukup; tidak menyimpan access token, secret, private email, atau payload OAuth lengkap. |
| Branding | Semua halaman publik/admin memiliki copyright tahun kalender dinamis, disclaimer independen/tidak berafiliasi OJK, dan nama Rifki Romadhan yang menaut ke profil Instagram dengan rel aman. |
| Operasional | Panduan Laragon dan cPanel, matrix PHP/MySQL, `.env.example` tanpa secret, root publik aman, cron/worker pendek didokumentasikan. |

## Pengujian prioritas

1. Migrasi fresh + seeder dua kali; periksa constraint identitas dan role; jalankan juga pada MySQL bila tersedia.
2. OAuth mock hanya dalam test: sukses, subject lama, email berubah, email tak terverifikasi, state invalid, provider error, akun ditangguhkan, collision email tanpa auto-link, redirect eksternal ditolak.
3. Guest mendapat 401 untuk API admin; reader mendapat 403; role berhak mendapat 200; editor/admin tidak dapat memberi privilege pada diri sendiri; akun dicabut kehilangan akses pada request berikutnya.
4. Login meregenerasi session dan logout menginvalidasi session/CSRF. Laravel melewati pemeriksaan CSRF pada lingkungan test secara default; tes CSRF perlu middleware/lingkungan yang benar-benar mengaktifkannya atau uji HTTP nyata.
5. Mass assignment tidak dapat mengubah role/status; endpoint `/me` tidak mengembalikan token/email privat; request sensitif terkena rate limit.
6. Audit tercatat untuk peristiwa yang benar dan menghindari field sensitif. CLI gagal aman pada subject tidak dikenal dan role/akun tidak valid.
7. `composer test`, lint PHP, ESLint, TypeScript check, Vitest, build Vite. Browser pada 360px/768px/1440px, keyboard/focus dan overflow diperiksa bila sarana browser tersedia.
8. HTTP tanpa JavaScript menghasilkan shell HTML yang sesuai, headers keamanan dan 404 untuk rute tidak dikenal; manifest/build assets ada dan dimuat.

## Batas penyelesaian

- OAuth live belum terbukti tanpa Google Client ID/secret, consent screen, redirect URI tepat dan akun pengujian yang disetujui.
- Google OAuth tidak membuktikan MFA. Bila TOTP/step-up belum diimplementasikan, sebutkan keterbatasan itu; untuk perubahan izin sensitif minimal minta autentikasi terbaru dan otorisasi super admin.
- M2 analytics nyata, importer, CMS/comment/SEO per artikel (M3), sinkronisasi/EWS/stress engine (M4), dan hardening/release penuh (M5) belum menjadi acceptance M0–M1.
- Jangan menandai seluruh milestone selesai bila build, database atau pengujian kritis belum dijalankan.

