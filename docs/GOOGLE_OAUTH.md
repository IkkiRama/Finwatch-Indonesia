# Google OAuth, akses admin dan MFA

## Status dan prinsip

Google OAuth diuji dengan provider mock dalam automated test. Login Google nyata belum dinyatakan terverifikasi sampai Client ID/secret, consent screen dan redirect URI lingkungan sesungguhnya disediakan dan diuji.

Socialite memakai session/state. Kunci identitas adalah subject Google unik, bukan email. Scope hanya OpenID, email dan profil. Akun baru reader; login tidak memberikan izin admin. Token tidak disimpan di localStorage atau dikembalikan melalui API identitas.

## Konfigurasi Google Cloud

1. Siapkan proyek Google Cloud dan consent screen. Jika masih mode testing, tambahkan akun penguji yang sesuai.
2. Buat OAuth client jenis Web application.
3. Daftarkan authorized redirect URI yang persis sama dengan server, misalnya lokal:

```text
http://127.0.0.1:8000/auth/google/callback
```

Produksi menggunakan domain HTTPS yang Anda kendalikan. Skema, host, port, path dan trailing slash harus sama. Lihat [OAuth web server Google](https://developers.google.com/identity/protocols/oauth2/web-server).

4. Isi hanya di `.env` backend:

```dotenv
APP_URL=http://127.0.0.1:8000
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_REDIRECT_URI=http://127.0.0.1:8000/auth/google/callback
```

5. Jalankan `php artisan config:clear` pada lokal. Di produksi, bangun ulang config cache melalui deployment.
6. Buka origin yang sesuai, login Google dan pastikan akun baru reader serta gagal mengakses API admin.

Jangan memakai prefix VITE_ untuk secret. Jangan simpan secret dalam Git, screenshot, chat, log atau konfigurasi browser. Saat pindah domain, perbarui URI Google dan server bersama-sama.

## Provision super admin

CLI menargetkan identitas yang sudah login Google dan terverifikasi. Operator berwenang memperoleh subject melalui database/backend privat; jangan menebaknya dari alamat email.

```powershell
php artisan finwatch:provision-super-admin --subject="GOOGLE_SUBJECT_TERVERIFIKASI"
```

Command harus gagal pada subject tidak dikenal/tidak terverifikasi. Lihat `php artisan help finwatch:provision-super-admin` untuk opsi aktual. Perubahan privilege diaudit dan tidak dilakukan oleh seeder publik.

## MFA administrator

TOTP akun berhak admin diaktifkan secara default. OAuth sendiri tidak membuktikan MFA Google. Operator berwenang melakukan enrollment melalui CLI:

```powershell
php artisan finwatch:enroll-mfa "GOOGLE_SUBJECT_TERVERIFIKASI"
```

Ikuti petunjuk command untuk memasukkan secret ke aplikasi authenticator lalu gunakan kode pada verifikasi admin. Materi enrollment rahasia: jangan commit, simpan dalam log bersama atau bagikan. Lihat `php artisan help finwatch:enroll-mfa` untuk opsi aktual sebelum enrollment. Pemulihan/penggantian MFA memerlukan CLI operator berwenang dan audit.

## Verifikasi manual sebelum produksi

- Akun terverifikasi baru selalu reader; tidak ada auto-link/promosi berdasarkan email/nama.
- Pembatalan OAuth, state invalid dan akun ditangguhkan gagal aman.
- Reader ditolak API admin; akun berizin melewati MFA.
- Logout mengakhiri session; pencabutan izin berlaku pada request berikutnya.
- HTTPS cookie Secure, HttpOnly dan SameSite; mutasi membutuhkan CSRF.
- Redirect akhir dibatasi pada path/origin yang diizinkan.
- Audit tercatat tanpa credential atau payload provider mentah.

Referensi: [Google OpenID Connect](https://developers.google.com/identity/openid-connect/openid-connect), [Laravel Socialite](https://laravel.com/framework/docs/socialite).
