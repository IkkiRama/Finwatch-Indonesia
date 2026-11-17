# Panduan demonstrasi FINWATCH

Gunakan database lokal khusus demonstrasi dan origin yang konsisten. Sebelum sesi: nyalakan MySQL, jalankan migrasi/seeder serta build, lalu start aplikasi. Data awal terdiri dari 108 observasi historis resmi dalam empat dataset; beri konteks ini pada audiens sejak awal.

## Alur publik, sekitar 5 menit

| Langkah | Halaman / tindakan | Bukti yang ditunjukkan |
|---|---|---|
| 1 | Buka `/`, lalu filter tahun | Tahun berasal dari observasi publik, bukan tahun copyright; angka memuat periode yang sebenarnya. |
| 2 | Buka `/perbankan`, perluas “Data dan metodologi” | Grafik dan tabel memakai nilai sama; unit, rilis, retrieval, sumber dan vintage dapat diperiksa. |
| 3 | Buka `/pemantauan-risiko` dan `/peringatan-dini` | Perubahan rasio dalam pp, level dalam persen; aturan penelitian menunjukkan parameter/versi dan kecukupan data. |
| 4 | Buka `/simulasi-stres`, gunakan baseline yang tersedia | Kredit, NPL dan CAR harus mempunyai periode sama. Ubah shock/LGD, hitung hasil, baca formula/batas lalu reset. |
| 5 | Buka `/inklusi-dan-konsumen` | Bedakan nasional dengan strata survei dan periode kumulatif konsumen; tidak ada ranking provinsi buatan. |
| 6 | Buka `/jelajah-data`, filter indikator dan unduh CSV | Ekspor menyertakan satuan, periode, provenance, rilis dan vintage; tersedia kamus indikator. |
| 7 | Buka `/sumber-data`, `/artikel` dan `/tentang` | Sumber dapat ditelusuri; artikel kosong sebelum redaksi menerbitkan konten; identitas independen terlihat. |

Pada ponsel periksa drawer, tabel dengan scroll lokal, grafik, form dan footer. Nama Rifki Romadhan pada footer mengarah ke profil Instagram; tahun copyright mengikuti kalender, terpisah dari statistik.

## Menyiapkan akses redaksi

Bagian ini membutuhkan Client ID/secret Google yang sah dan operator CLI. Tanpa konfigurasi tersebut, demonstrasikan halaman publik serta hasil automated test; tidak ada akun demo atau bypass login.

1. Ikuti [GOOGLE_OAUTH](GOOGLE_OAUTH.md), login Google sekali dan pastikan akun baru reader.
2. Operator menargetkan subject Google terverifikasi untuk provision super admin atau assignment role melalui CLI yang tersedia. Jangan menargetkan email sebagai pengganti identitas provider.
3. Enrollment TOTP melalui `finwatch:enroll-mfa`, login ulang bila sesi dicabut, lalu selesaikan verifikasi MFA pada admin. Jangan menampilkan secret enrollment kepada audiens.
4. Siapkan akun reader, author dan pengelola sesuai kebutuhan. Pengujian role memerlukan identitas terpisah; jangan mengubah super admin sendiri melalui GUI.

## Alur CMS dan komunitas

1. Di `/admin/community`, buat kategori dan profil penulis yang ditautkan pada akun yang memenuhi syarat. Gunakan nama publik/bio yang memang diizinkan untuk dipublikasikan.
2. Di `/admin/media`, unggah JPG/PNG/WebP maksimal 5 MB dengan teks alternatif. File diproses ulang menjadi WebP/thumbnail; SVG ditolak. Media draf tetap privat.
3. Di `/admin/articles/new`, isi judul, slug, ringkasan, penulis, kategori, isi dengan heading, referensi sumber, dan metadata SEO. Simpan draf pertama. Autosave berlaku setelah draf mempunyai ID; perubahan belum tersimpan diberi penanda.
4. Buka pratinjau bertanda tangan yang berlaku 15 menit. Pastikan URL publik slug draf masih 404 dan draf tidak masuk sitemap. Jangan membagikan URL preview sebagai publikasi permanen.
5. Author mengajukan peninjauan; pengelola yang memiliki `articles.publish` menerbitkan atau menjadwalkan. Tugas scheduler harus berjalan untuk publikasi terjadwal.
6. Buka artikel publik. Periksa cover, TOC, penulis, referensi, artikel terkait bila ada, unduhan PDF dan tombol berbagi. Lihat source HTML awal untuk title/meta/canonical/OG/JSON-LD.
7. Edit artikel dan ubah slug menggunakan akun berizin. URL lama harus 301 ke URL baru. Pulihkan isi dari riwayat revisi; permalink/status saat ini dipertahankan dan pemulihan tercatat sebagai revisi baru.
8. Login sebagai reader dan kirim komentar. Komentar baru pending; publik belum melihat isinya. Moderator menyetujui dengan alasan pada `/admin/moderation`.
9. Balas komentar yang disetujui, buka/tutup thread, muat balasan, like/unlike, laporkan dan salin deep link. Hapus komentar milik sendiri: parent menjadi penanda penghapusan agar struktur diskusi tetap dapat dibaca. Komentar yang disembunyikan tidak dibocorkan melalui endpoint balasan/context.
10. Moderator meninjau laporan, menyembunyikan/mengunci/pemulihan sesuai izin dengan alasan. Periksa audit sebagai akun yang berizin. Tegaskan diskusi bukan kanal pengaduan resmi OJK.

Penghapusan media yang dipakai artikel atau riwayat revisi ditolak. Kategori/profil penulis yang masih dirujuk juga tidak boleh dihapus sembarangan. Arsipkan publikasi bila perlu mempertahankan histori.

## Alur data dan sinkronisasi

Lakukan percobaan impor pada database demonstrasi, bukan pada dataset produksi yang sedang dipakai publik.

1. Buka `/admin/datasets`. Pilih dataset, lihat versi aktif, checksum, daftar masalah dan preview baris.
2. Unduh template. Untuk demonstrasi yang dapat dipublikasikan, gunakan subset data resmi dari ekspor beserta provenance aslinya; jangan mengubah angka contoh lalu menyebutnya data resmi.
3. Impor CSV/XLSX ke dataset demonstrasi. Versi valid berstatus menunggu review; nilai publik belum berubah. Tunjukkan validasi unit/periode/duplikasi dengan satu file uji yang sengaja salah, tanpa mempublikasikannya.
4. Setelah memeriksa seluruh baris dan metadata, publikasikan versi valid. Periksa dashboard/ekspor memakai versi aktif, lalu rollback ke versi lama yang sudah dipublikasikan bila tersedia.
5. Buka `/admin/synchronization`, pilih sumber dan jalankan. Worker database harus aktif. Status/log/progress menunjukkan pekerjaan nyata, bukan animasi timer.
6. Untuk arsip SPI yang checksum-nya tidak berubah, hasil yang diharapkan adalah `no_new_data`. Itu hanya berlaku pada URL arsip tersebut. Sumber tanpa adapter/unduhan terverifikasi memberikan `manual_required`.
7. Periksa `/admin/sources` dan pengaturan adapter. BI/BPS belum menjadi live API: gunakan fallback normalisasi manual dari publikasi asli. Jangan memasukkan API secret pada URL.
8. Buka aturan EWS/skenario pada admin, periksa input/metodologi dan versi. Perubahan threshold merupakan keputusan riset yang perlu dicatat, bukan rating regulator.

## Pemeriksaan operasional

Periksa System Health dan audit dengan akun berizin. Jalankan `php artisan schedule:run` untuk heartbeat/tugas yang jatuh tempo dan worker terbatas untuk antrean. Uji backup/restore pada salinan database dengan prosedur [DEPLOYMENT_CPANEL](DEPLOYMENT_CPANEL.md).

Sampaikan batas verifikasi dengan jelas: pengujian lokal, snapshot historis, model sederhana, sumber yang masih manual, dan Google/hosting produksi yang belum diuji. Hasil CI lokal atau provider mock tidak menggantikan uji login Google serta cron pada hosting sesungguhnya.
