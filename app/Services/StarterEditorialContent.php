<?php

namespace App\Services;

use Database\Seeders\OfficialDataSeeder;

class StarterEditorialContent
{
    public function articles(): array
    {
        $spi = ['title' => 'OJK | Statistik Perbankan Indonesia, Juni 2025 (berkas Excel historis)', 'url' => OfficialDataSeeder::SPI_URL];
        $snlik = ['title' => 'OJK dan BPS | Rilis SNLIK 2025, Metode Keberlanjutan, 2 Mei 2025', 'url' => OfficialDataSeeder::SNLIK_URL];
        $rdk = ['title' => 'OJK | Rapat Dewan Komisioner Bulanan Agustus 2025, rilis 4 September 2025', 'url' => OfficialDataSeeder::RDK_URL];
        $book = ['title' => 'OJK | Buku dan booklet Statistik SNLIK 2025', 'url' => 'https://ojk.go.id/id/Fungsi-Utama/Perilaku-Pelaku-Usaha-Jasa-Keuangan/SNLIK/Pages/SNLIK-2025.aspx'];
        $iasc = ['title' => 'OJK | Portal resmi Indonesia Anti-Scam Centre dan panduan pelaporan', 'url' => 'https://iasc.ojk.go.id/'];
        $scam = ['title' => 'OJK | Waspada situs yang mengatasnamakan IASC, 28 Maret 2025', 'url' => 'https://ojk.go.id/id/berita-dan-kegiatan/info-terkini/Pages/Waspada-Penipuan-Website-Mengatasnamakan-Indonesia-Anti-Scam-Centre-IASC.aspx'];
        $hoax = ['title' => 'OJK | Waspada penipuan pemutihan data pinjaman online, 3 Maret 2026', 'url' => 'https://www.ojk.go.id/id/Publikasi/Info-Hoax/Pages/Awas-Penipuan-Pemutihan-Data-Pinjaman-Online-Mengatasnamakan-OJK.aspx'];

        return [
            [
                'slug' => 'npl-dan-car-dua-angka-yang-berbeda-cerita',
                'title' => 'NPL dan CAR: dua angka, dua cerita yang berbeda',
                'excerpt' => 'Yang satu berbicara tentang kualitas kredit, yang lain tentang bantalan modal. Kenali artinya tanpa harus menjadi analis bank.',
                'category' => 'Dasar Keuangan', 'category_slug' => 'dasar-keuangan', 'type' => 'education', 'tags' => ['mulai-dari-sini', 'perbankan', 'kamus-keuangan'], 'sources' => [$spi, $rdk], 'theme' => 'banking',
                'body' => <<<'HTML'
<p>Saat membaca berita bank, kita mungkin bertemu dua angka sekaligus: NPL dan CAR. Keduanya terdengar teknis, padahal pertanyaan di baliknya dekat dengan keseharian. Apakah pinjaman kembali sesuai rencana? Lalu, seberapa besar bantalan yang tersedia ketika ada kerugian? Mari memisahkan dua pertanyaan itu, kemudian mengikuti satu contoh hitung.</p>
<h2>NPL: mulai dari pinjaman yang bermasalah</h2>
<p><strong>Non-performing loan</strong> atau NPL adalah rasio kredit bermasalah terhadap total kredit. Angka ini membantu pembaca melihat kualitas penyaluran kredit. NPL gross melihat kredit bermasalah sebelum pengurangan cadangan tertentu; NPL net menggunakan perhitungan setelah penyesuaian cadangan. Karena definisinya berbeda, keduanya tidak boleh ditukar begitu saja.</p>
<p>Bayangkan sebuah buku pinjaman, lalu sebagian halamannya diberi penanda untuk ditindaklanjuti. Penanda itu menggambarkan masalah dalam kualitas kredit, bukan otomatis seluruh nilai pinjaman sudah hilang. Untuk menilai kemungkinan kerugian masih diperlukan informasi cadangan, agunan, kemampuan peminjam, dan proses penyelesaian.</p>
<p>Gunakan ilustrasi berikut, bukan angka bank tertentu. Sebuah bank mempunyai total kredit Rp1.000 miliar. Kredit yang tergolong bermasalah berjumlah Rp20 miliar. NPL gross sederhananya adalah 20 dibagi 1.000, lalu dikali 100%, sehingga hasilnya 2%. Yang kita ukur adalah bagian kredit bermasalah dalam keseluruhan kredit.</p>
<p>Sekarang bayangkan total kredit tetap Rp1.000 miliar, sedangkan kredit bermasalah menjadi Rp30 miliar. Rasionya menjadi 3%. Ada tambahan Rp10 miliar kredit yang masuk kategori bermasalah. Namun kita belum tahu berapa kerugian akhirnya. Sebagian bisa tertagih, sebagian mungkin memiliki agunan, dan penanganannya memerlukan waktu. Menyamakan seluruh Rp30 miliar dengan uang yang pasti hilang akan melompati informasi yang belum kita miliki.</p>
<h2>CAR: beralih ke bantalan modal</h2>
<p><strong>Capital adequacy ratio</strong> atau CAR membandingkan modal dengan aset tertimbang menurut risiko. Istilah terakhir penting: pembaginya bukan sekadar seluruh kredit atau seluruh aset. Jenis eksposur yang berbeda dapat mempunyai bobot risiko berbeda.</p>
<p>Analogi bantalan membantu memahami fungsinya, tetapi jangan menganggap CAR sebagai uang tunai yang langsung tersedia untuk ditarik nasabah. Modal, likuiditas, dan laba adalah hal berbeda. Rasio modal yang tinggi juga tidak menjawab semua pertanyaan mengenai kualitas aset atau kemampuan memenuhi kebutuhan dana jangka pendek.</p>
<p>Untuk CAR, gunakan buku catatan yang berbeda. Misalkan modal Rp200 miliar dan aset tertimbang menurut risiko Rp1.000 miliar. CAR menjadi 20%. Bila modal turun menjadi Rp190 miliar dengan pembagi tetap, CAR menjadi 19%. Contoh ini sengaja menahan pembagi agar hubungan dasarnya terlihat. Dalam kondisi nyata, modal dan aset tertimbang menurut risiko sama-sama bisa berubah.</p>
<h2>Modal dan kebutuhan dana sehari-hari</h2>
<p>Pikirkan perbedaan antara kondisi rumah dan persediaan uang belanja. Rumah dapat mempunyai fondasi kuat, tetapi penghuninya tetap perlu mengatur uang untuk kebutuhan hari ini. Analogi itu membantu membedakan modal dari likuiditas: keduanya penting, tetapi menjawab kebutuhan yang berbeda. Jangan membayangkan seluruh fungsi bank bisa diringkas menjadi satu bantalan.</p>
<p>Begitu pula kredit bermasalah dapat turun sebagai rasio ketika total kredit bertambah. Agar tahu apa yang terjadi, kita ingin melihat nilai pembilang, nilai pembagi, dan catatan perubahannya. Angka rasio tanpa konteks dapat menyembunyikan beberapa cerita yang sangat berbeda. Karena itu FINWATCH mengajak pembaca membuka sumber, bukan berhenti pada bentuk kurvanya.</p>
<p>Saat membaca berita berikutnya, coba susun satu kalimat utuh: indikator apa, berapa nilainya, kapan diamati, dan untuk siapa cakupannya. Misalnya “rasio industri pada Juni 2025” lebih jelas daripada “kondisi bank sekarang”. Bila empat bagian itu belum lengkap, simpan dulu kesimpulannya. Memahami data dimulai dari membedakan pertanyaan yang sudah terjawab dan yang masih terbuka.</p>
<h2>Dari 2% ke 3%, naik berapa?</h2>
<p>Perhatikan pula cara menyebut perubahan. Jika sebuah rasio naik dari 2% menjadi 3%, selisihnya adalah 1 poin persentase, biasa disingkat <strong>1 pp</strong>. Menyebutnya naik 1% akan membingungkan karena perubahan relatifnya berbeda. Contoh tersebut murni ilustrasi perhitungan, bukan observasi bank tertentu.</p>
<p>Cara menyebut selisih penting karena rasio dan jumlah uang dapat bergerak dengan cara berbeda. Saat membaca perubahan, cari dahulu apa yang menjadi pembilang, apa pembaginya, dan apakah keduanya tetap memakai definisi yang sama. Dua angka yang terlihat mirip belum tentu menjawab pertanyaan yang sama.</p>
<h2>Tiga pertanyaan sebelum menarik kesimpulan</h2>
<ol><li>Apakah yang dibandingkan sama-sama NPL gross, NPL net, atau CAR?</li><li>Apakah periode, lingkup, dan definisi datanya sama?</li><li>Apakah ada informasi pelengkap yang belum tersedia?</li></ol>

<p>FINWATCH menampilkan NPL dan CAR bersama konteks tanggal, lingkup industri, serta sumber publikasi. Nilai agregat industri tidak menjelaskan keadaan setiap bank. Satu angka juga tidak membuktikan bahwa institusi tertentu aman atau bermasalah. Karena itu, warna visual di sini membantu membaca data, bukan memberi peringkat kesehatan resmi.</p>
<p>Gunakan dashboard sebagai pintu masuk untuk memahami hubungan antarindikator. Buka sumbernya ketika ingin memeriksa detail. Artikel ini merupakan penjelasan independen untuk pembaca umum, bukan penilaian kesehatan bank atau rekomendasi memilih produk.</p>
HTML,
            ],
            [
                'slug' => 'sebelum-transfer-berhenti-cek-dan-lindungi-diri',
                'title' => 'Sebelum transfer: berhenti sebentar, cek dengan benar',
                'excerpt' => 'Logo resmi dan pesan mendesak belum membuktikan sebuah tawaran benar. Ini cara memeriksa informasi dan menemukan kanal bantuan yang tepat.',
                'category' => 'Aman Bertransaksi', 'category_slug' => 'aman-bertransaksi', 'type' => 'education', 'tags' => ['pelindungan-konsumen', 'anti-penipuan', 'panduan-praktis'], 'sources' => [$iasc, $scam, $hoax], 'theme' => 'safety',
                'body' => <<<'HTML'
<p>Pesannya bisa terlihat meyakinkan: ada logo, nama petugas, nomor tiket, bahkan kalimat “harus diselesaikan hari ini”. Ketika kita terburu-buru, pemeriksaan sederhana mudah terlewat. Beri diri sendiri ruang untuk berhenti sebelum mengirim uang atau data pribadi.</p>
<h2>Kenali tekanan yang dibuat pengirim</h2>
<p>Bayangkan situasi rekaan berikut. Sebuah pesan mengaku berasal dari petugas dan mengatakan akun harus diperbarui sebelum sore. Pengirim menyertakan tautan, lalu meminta kode yang masuk ke telepon. Situasi ini sengaja dibuat sebagai latihan, bukan laporan kejadian seorang pembaca. Tujuannya melihat bagian mana yang patut menghentikan percakapan.</p>
<h2>Periksa melalui jalur yang Anda buka sendiri</h2>
<p>Jangan menilai keaslian layanan hanya dari logo atau tangkapan layar. Cari situs lembaga secara mandiri, ketik alamatnya sendiri, lalu gunakan kontak yang tercantum di sana. Hindari menjadikan nomor pada pesan yang mencurigakan sebagai satu-satunya tempat bertanya. Jika nama penyedia layanan dan nama penerima pembayaran berbeda, minta penjelasan yang dapat diperiksa.</p>
<p>OJK pernah memperingatkan adanya situs yang mengatasnamakan Indonesia Anti-Scam Centre. Portal resminya adalah <a href="https://iasc.ojk.go.id/">iasc.ojk.go.id</a>. Kontak OJK untuk memeriksa informasi tersedia melalui telepon 157 dan WhatsApp 081-157-157-157. Kanal ini diperiksa kembali saat peninjauan artikel pada 3 November 2026; selalu lihat informasi terbaru pada situs resmi.</p>
<h2>Jaga kunci akun tetap di tangan Anda</h2>
<p>Langkah kedua adalah menjaga informasi yang memungkinkan orang lain mengambil alih akun. Kode OTP, PIN, dan kata sandi bukan bukti identitas yang perlu diserahkan kepada petugas melalui chat. Jangan memasang aplikasi kendali jarak jauh hanya karena diminta orang yang baru menghubungi. Bila ada proses yang belum dimengerti, hentikan dulu dan cari penjelasan dari penyedia layanan.</p>
<h2>Periksa penyedia, lalu pahami produknya</h2>
<p>Pastikan status penyedia melalui kanal otoritas yang berwenang. Setelah itu, baca apa yang sebenarnya ditawarkan: biaya, jatuh tempo, hak konsumen, risiko, dan cara penyelesaian keluhan. Izin sebuah lembaga tidak berarti setiap tautan atau orang yang memakai namanya adalah asli. Izin juga bukan jaminan bahwa suatu produk cocok dengan kebutuhan semua orang.</p>
<p>Tanyakan pada diri sendiri: saya memahami uang ini digunakan untuk apa? Apa yang terjadi jika terlambat, rugi, atau ingin berhenti? Bila jawabannya hanya berupa janji di ruang chat, jangan anggap semua pertanyaan sudah terjawab. Simpan jawaban yang bisa diperiksa, lalu beri diri sendiri waktu untuk membacanya sebelum memutuskan.</p>
<h2>Jika uang sudah terlanjur dikirim</h2>
<p>Saat menyadari ada transaksi mencurigakan, rasa panik dapat membuat informasi tercecer. Tulis kronologi singkat: kapan dihubungi, melalui kanal apa, transaksi ke rekening mana, dan kapan dilakukan. Simpan bukti asli dengan aman. Jangan mengunggahnya secara terbuka demi mendapat bantuan cepat, karena bukti itu dapat memuat identitas atau informasi transaksi yang sensitif.</p>
<p>Portal IASC mengarahkan korban untuk segera melapor melalui jalur penanganan finansial dan membuat laporan polisi untuk proses hukum. Siapkan bukti transfer, rekening terkait, kronologi, serta percakapan. Kecepatan melapor penting, tetapi laporan bukan janji bahwa seluruh dana pasti kembali. Ikuti petunjuk resmi dan simpan nomor laporan agar tindak lanjut dapat dilacak.</p>
<p>Hubungi penyedia layanan melalui kanal resmi dan ikuti mekanisme pelaporannya. Untuk dugaan penipuan transaksi keuangan, baca petunjuk IASC agar tahu dokumen dan langkah yang diminta. Kanal penanganan finansial dan laporan kepolisian mempunyai fungsi berbeda; satu nomor laporan tidak otomatis menggantikan seluruh proses yang diperlukan.</p>
<h2>Waspadai janji pengembalian dana</h2>
<p>Pihak yang mengaku bisa menghapus masalah atau mengembalikan dana dengan meminta transfer tambahan perlu diperiksa ulang. OJK juga menerbitkan peringatan pada Maret 2026 mengenai klaim pemutihan data pinjaman online yang memakai namanya. Jangan menambah kerugian karena ingin segera mengakhiri situasi yang menegangkan.</p>
<p>Catat nomor laporan serta saluran tindak lanjut. Bila seseorang kemudian menghubungi dengan janji dana pasti kembali asal membayar dahulu, periksa kembali melalui kanal semula. Situasi yang menegangkan tidak membuat janji tersebut menjadi lebih dapat dipercaya. Pemeriksaan tetap perlu dilakukan meskipun pengirim mengetahui sebagian kronologi Anda.</p>
<h2>Bangun kebiasaan yang bisa diulang</h2>
<p>Anda tidak perlu menghafal seluruh modus. Kebiasaan yang lebih mudah dibawa adalah memberi jeda, membuka kanal resmi sendiri, dan meminta penjelasan yang bisa diperiksa. Ajak anggota keluarga menyepakati kebiasaan itu, terutama untuk permintaan transfer yang datang mendadak. Sebuah jeda singkat memberi ruang untuk berpikir ketika pesan sengaja dibuat terasa darurat.</p>
<p>Ruang komentar FINWATCH adalah tempat berdiskusi tentang literasi. Jangan menaruh nomor rekening lengkap, dokumen identitas, atau bukti transaksi pribadi di sini. Untuk laporan kasus, gunakan kanal resmi yang ditautkan di referensi.</p>
HTML,
            ],
            [
                'slug' => 'punya-rekening-belum-tentu-paham-produknya',
                'title' => 'Punya rekening belum tentu paham produknya',
                'excerpt' => 'Akses keuangan dan pemahaman keuangan saling berkaitan, tetapi mengukur hal yang berbeda. Kenali perbedaannya melalui SNLIK.',
                'category' => 'Literasi & Inklusi', 'category_slug' => 'literasi-inklusi', 'type' => 'education', 'tags' => ['literasi', 'inklusi', 'snlik'], 'sources' => [$snlik, $book], 'theme' => 'literacy',
                'body' => <<<'HTML'
<p>Membuka rekening atau memakai dompet digital terasa semakin biasa. Namun bisa menggunakan sebuah aplikasi tidak selalu sama dengan memahami seluruh biaya dan risikonya. Di sinilah perbedaan antara inklusi dan literasi keuangan menjadi penting.</p>
<h2>Sudah bisa memakai, belum tentu memahami</h2>
<p>Bayangkan contoh rekaan ini. Seseorang sudah terbiasa membayar dengan aplikasi, tetapi baru mengetahui adanya biaya tertentu setelah melihat riwayat transaksi. Ia jelas dapat mengakses layanan. Yang belum selesai adalah memahami cara kerja dan biaya layanan itu. Cerita ilustratif ini tidak digunakan untuk menilai tingkat literasi orang tertentu, melainkan menunjukkan mengapa dua ukuran tadi perlu dipisahkan.</p>
<p>Secara sederhana, inklusi berkaitan dengan penggunaan produk dan layanan keuangan, sedangkan literasi berkaitan dengan pengetahuan serta kemampuan yang mendukung keputusan keuangan. Dua ukuran itu menjawab pertanyaan berbeda. Orang dapat bertransaksi dengan lancar tetapi belum terbiasa membandingkan biaya, memahami bunga, atau membaca pengecualian dalam perjanjian.</p>
<p>Itu bukan alasan untuk memberi label buruk pada pengguna. Produk, bahasa kontrak, pengalaman belajar, dan situasi hidup juga dapat memengaruhi cara seseorang mengambil keputusan. Penjelasan yang mudah dipahami sama pentingnya dengan akses yang mudah.</p>
<h2>Ubah janji umum menjadi pertanyaan konkret</h2>
<p>Ketika membaca penawaran, coba ubah istilah menjadi pertanyaan yang bisa dijawab. “Biaya ringan” menjadi “berapa rupiah per bulan dan dalam keadaan apa bertambah?” “Mudah dicairkan” menjadi “kapan dana dapat diterima dan syarat apa yang harus dipenuhi?” Pertanyaan yang konkret membantu menemukan informasi yang tersembunyi di balik kata-kata yang terdengar nyaman.</p>
<p>Anda juga dapat meminta contoh untuk keadaan yang kurang menyenangkan. Apa yang terjadi ketika pembayaran terlambat? Bagaimana jika transaksi keliru? Siapa yang dapat dihubungi bila jawaban awal belum menyelesaikan persoalan? Memahami risiko berarti mengetahui kemungkinan itu sebelum membutuhkan jalur bantuannya.</p>
<h2>Empat pertanyaan sebelum memilih layanan</h2>
<ul><li><strong>Tujuan:</strong> kebutuhan apa yang ingin saya selesaikan?</li><li><strong>Biaya:</strong> berapa biaya total, bukan hanya angka promosi di halaman pertama?</li><li><strong>Risiko:</strong> apa yang dapat berubah atau membuat saya menanggung kerugian?</li><li><strong>Hak:</strong> bagaimana mengajukan pertanyaan, keluhan, atau menghentikan penggunaan?</li></ul>
<p>Pertanyaan ini merupakan alat bantu membaca, bukan tes kelulusan literasi. Ketika suatu istilah belum jelas, minta contoh perhitungan dalam rupiah dan waktu yang konkret. Penjelasan “biayanya kecil” berbeda dengan rincian yang bisa dibandingkan. Catat jawaban agar keputusan tidak hanya bergantung pada ingatan setelah berbicara dengan petugas.</p>
<h2>Lalu apa yang diukur oleh SNLIK?</h2>
<p>Pada rilis SNLIK 2025, Metode Keberlanjutan mencatat literasi nasional 66,46% dan inklusi 80,51%. Keduanya adalah hasil survei dengan definisi tertentu. Angka tersebut adalah arsip data historis 2025, bukan ukuran langsung kemampuan orang yang sedang membaca artikel ini dan bukan statistik terbaru tahun 2026.</p>
<p>Jangan membaca selisih dua indeks sebagai daftar pasti orang yang menggunakan layanan tetapi tidak memahami layanan tersebut. Untuk menjawab siapa termasuk kelompok mana, diperlukan data gabungan pada tingkat responden. Dashboard agregat tidak menyimpan informasi pribadi seperti itu.</p>
<h2>Kenapa 100 titik tidak menunjukkan 100 orang tertentu?</h2>
<p>Di halaman inklusi, FINWATCH memakai 100 titik supaya persentase lebih mudah dibayangkan. Setiap titik adalah alat visual, bukan seorang responden yang bisa dikenali. Ketika indeks inklusi 80,51% dibulatkan menjadi sekitar 81 titik, angka tepatnya tetap 80,51%. Pembulatan membantu mata, sedangkan keterangan angka menjaga ketelitian.</p>
<p>Titik pada visual literasi juga bukan orang yang persis sama dengan titik pada visual inklusi. Dua grafik merangkum dua ukuran. Kita tidak dapat mencocokkan titik satu per satu untuk menyimpulkan siapa yang belum memahami produk. Ini alasan mengapa selisih 14,05 poin persentase tidak diterjemahkan menjadi daftar orang yang perlu diberi label tertentu.</p>
<h2>Bagaimana membaca daerah sendiri?</h2>
<p>Peta provinsi di FINWATCH membuka arsip SNLIK 2022 yang memuat dua indikator untuk 34 wilayah saat survei. Peta itu memberi kesempatan melihat perbedaan antardaerah dalam satu publikasi. Tahun tersebut sengaja ditulis jelas karena peta tidak menggunakan angka nasional 2025 untuk mengisi provinsi yang belum mempunyai rincian dalam arsip data itu.</p>
<p>Kelompok perkotaan dan perdesaan menjawab pertanyaan lain. Keduanya tersebar di banyak provinsi, sehingga angka perdesaan nasional bukan nilai sebuah provinsi tertentu. Memisahkan cakupan seperti ini membuat visual sedikit lebih hati-hati, tetapi membantu kita mengajukan pertanyaan yang tepat. Membuka peta berarti memulai penelusuran, bukan menemukan penjelasan lengkap tentang kehidupan semua penduduknya.</p>
<h2>Bawa pemahaman itu ke keputusan sehari-hari</h2>
<p>Survei membantu melihat pola yang luas. Untuk kehidupan sehari-hari, terjemahkan pola itu menjadi kebiasaan: memeriksa sumber, membaca ringkasan informasi, dan menyisihkan waktu untuk bertanya. Tidak semua orang membutuhkan produk yang sama. Akses yang lebih luas akan lebih berguna ketika orang dapat memilih dengan informasi yang dimengerti.</p>
<p>FINWATCH menyajikan perbandingan nasional serta kelompok perkotaan/perdesaan yang tersedia dalam sumber. Kelompok tempat tinggal berbeda dari cakupan provinsi pada peta arsip 2022. Buka catatan metode sebelum membandingkan cakupan atau tahun yang berbeda.</p>
HTML,
            ],
            [
                'slug' => 'tiga-tanggal-di-balik-satu-angka',
                'title' => 'Tiga tanggal di balik satu angka',
                'excerpt' => 'Periode observasi, tanggal rilis, dan tanggal pengambilan data tidak sama. Pahami bedanya agar tidak mengira data lama sebagai kondisi hari ini.',
                'category' => 'Baca Data', 'category_slug' => 'baca-data', 'type' => 'education', 'tags' => ['metodologi', 'baca-data', 'mulai-dari-sini'], 'sources' => [$spi, $rdk], 'theme' => 'dates',
                'body' => <<<'HTML'
<p>Anda membuka dashboard pada November, tetapi grafik berhenti di Juni. Apakah aplikasinya rusak? Belum tentu. Data keuangan biasanya melewati proses pengumpulan dan publikasi sebelum bisa dibaca publik. Karena itu satu angka mempunyai lebih dari satu tanggal penting.</p>
<h2>Ikuti perjalanan satu angka</h2>
<p>Ambil contoh dari arsip yang dipakai FINWATCH: berkas tabel Excel Statistik Perbankan Indonesia edisi Juni 2025. Judul edisinya memberi petunjuk mengenai periode. Halaman sumber mencatat rilis pada 29 Agustus 2025. Ketika FINWATCH menyimpan salinannya pada Oktober 2026, catatan pengambilan itu bertambah, tetapi periode Juni 2025 tidak bergeser.</p>
<p>Bayangkan sebuah kartu kecil di bawah grafik. Baris pertama menyebut “Juni 2025”, baris berikutnya “rilis 29 Agustus 2025”, lalu “diambil Oktober 2026”. Urutan ini membantu pembaca melihat perjalanan angka: dari kondisi yang diamati, menjadi publikasi, kemudian masuk ke aplikasi. Tanpa kartu itu, tanggal paling baru mudah keliru dianggap sebagai waktu kejadiannya.</p>
<h2>Pertama: kapan keadaan itu diamati?</h2>
<p><strong>Periode observasi</strong> menjawab kapan kondisi yang diukur terjadi. Saldo pada akhir bulan, jumlah kejadian selama satu bulan, dan akumulasi sejak awal tahun adalah tiga jenis ukuran yang berbeda. Label periode membantu kita mengetahui mana yang sedang dibaca.</p>
<p>Pikirkan sebuah foto. Tanggal foto diambil berbeda dari tanggal foto itu dikirim kepada teman. Demikian juga angka Juni tetap menggambarkan periode Juni walaupun baru Anda temukan beberapa bulan kemudian.</p>
<h2>Kedua: kapan sumber merilisnya?</h2>
<p><strong>Tanggal rilis</strong> menandai waktu penerbit menyediakan publikasi. Pada arsip data FINWATCH, arsip SPI Juni 2025 mempunyai tanggal rilis 29 Agustus 2025. Rilis RDKB Agustus 2025 yang dipakai sebagai salah satu referensi diterbitkan pada 4 September 2025. Nama dokumen saja tidak cukup untuk mengetahui periode setiap angka di dalamnya.</p>
<p>Dalam sebuah siaran pers, angka terbaru bisa muncul bersama angka bulan sebelumnya sebagai pembanding. Mengambil semuanya lalu memberi satu tanggal yang sama akan membuat cerita datanya keliru. Periksa kalimat di sekitar angka serta judul kolom pada tabel.</p>
<h2>Ketiga: kapan FINWATCH mengambilnya?</h2>
<p><strong>Tanggal pengambilan</strong> adalah catatan kerja FINWATCH. Tanggal ini membantu penelusuran berkas dan versi, tetapi tidak memperbarui periode observasi. Mengunduh berkas 2025 pada 2026 tidak mengubah berkas itu menjadi data kondisi 2026.</p>
<p>Catatan pengambilan membantu kita menemukan kembali berkas yang digunakan. Jika sumber memperbarui publikasinya, tanggal unduhan saja tidak cukup; versi berkas juga perlu diperiksa. Itulah alasan FINWATCH menampilkan periode, rilis, pengambilan, dan versi sebagai keterangan yang terpisah.</p>
<h2>Posisi, arus, dan akumulasi punya cerita berbeda</h2>
<p>Saldo simpanan pada akhir bulan adalah posisi pada suatu waktu. Jumlah transaksi selama bulan itu menggambarkan kegiatan dalam rentang waktu. Total pengaduan sejak Januari hingga Agustus adalah akumulasi. Tiga bentuk tersebut sama-sama sah, tetapi tidak bisa diperlakukan sebagai ukuran yang identik hanya karena berada pada satu halaman.</p>
<p>Untuk contoh rekaan yang sederhana, misalkan sebuah kanal mencatat 100 pengaduan sejak Januari sampai Juni dan 120 sejak Januari sampai Juli. Selisihnya 20, dengan syarat definisi dan pencatatannya tetap sama. Kita tidak boleh menyebut Juli memiliki 120 pengaduan baru. Kita juga tidak boleh menjumlahkan 100 dan 120 untuk mendapatkan total Januari sampai Juli karena periode awalnya saling bertumpang tindih.</p>
<p>Demikian pula angka posisi akhir Juni tidak dijumlahkan dengan posisi akhir Juli untuk mendapatkan saldo “dua bulan”. Bila yang dibutuhkan adalah rata-rata, perubahan, atau arus tambahan, pilih perhitungan yang sesuai dengan pertanyaannya. Satuan yang sama belum membuat semua operasi matematika menjadi bermakna.</p>
<h2>Bagaimana jika sumber memperbaiki data?</h2>
<p>Dalam publikasi statistik, versi yang lebih baru dapat memuat perbaikan pada periode lama. FINWATCH menyediakan penelusuran versi agar pengelola dapat memeriksa perubahan itu sebelum menerbitkannya. Angka yang disetujui menjadi versi publik; salinan input sebelumnya tetap penting sebagai jejak kerja, bukan sebagai alasan mencampur dua versi tanpa penjelasan.</p>
<p>Saat melihat grafik yang terasa berbeda dari tangkapan layar lama, periksa versi serta sumbernya. Perubahan dapat berasal dari pembaruan data, filter, satuan, atau cakupan. Mengetahui penyebabnya membuat percakapan lebih berguna daripada langsung menilai salah satu gambar keliru. Pertanyaan pertama yang paling membantu sering kali sederhana: kita sedang membaca periode dan versi yang sama atau belum?</p>
<h2>Daftar periksa selama sepuluh detik</h2>
<ol><li>Baca periode observasi sebelum membaca besar angkanya.</li><li>Periksa satuan: persen, rupiah triliun, orang, atau jumlah pengaduan.</li><li>Periksa apakah angka itu bulanan, posisi akhir periode, atau kumulatif.</li><li>Buka tautan sumber dan catatan versi ketika perbandingannya terasa janggal.</li></ol>
<p>Tahun di footer situs mengikuti kalender. Tahun di filter mengikuti data yang tersedia. Dua hal itu tidak harus sama. Dashboard yang jujur dapat menampilkan data historis yang berguna, selama tanggal dan batas penggunaannya jelas. Jangan menyebut arsip data lama sebagai kondisi terkini.</p>
HTML,
            ],
            [
                'slug' => 'membaca-jarak-akses-dan-pemahaman-snlik-2025',
                'title' => 'Akses tumbuh, pemahaman menyusul: membaca SNLIK 2025',
                'excerpt' => 'Catatan riset deskriptif atas dua indeks nasional serta perbandingan perkotaan/perdesaan. Selisihnya menarik, tetapi tidak membuktikan hubungan sebab-akibat.',
                'category' => 'Catatan Riset', 'category_slug' => 'catatan-riset', 'type' => 'research', 'tags' => ['snlik-2025', 'riset-deskriptif', 'inklusi'], 'sources' => [$snlik, $book], 'theme' => 'gap',
                'body' => <<<'HTML'
<p>Bayangkan kita sedang menyusun papan informasi untuk warga yang baru ingin mengenal layanan keuangan. Pertanyaan praktisnya adalah apakah kemudahan memakai layanan sudah diikuti pemahaman yang sebanding. Papan itu membutuhkan penjelasan yang hati-hati: “sebanding” di sini menggambarkan gerak dua indeks, bukan pemeriksaan kemampuan setiap pengguna. Contoh papan informasi ini adalah ilustrasi perancangan, bukan lokasi penelitian lapangan.</p>
<p><strong>Pertanyaan riset:</strong> bagaimana membaca perbedaan antara akses layanan keuangan dan pemahaman keuangan tanpa membuat kesimpulan yang melampaui data? Catatan ini membandingkan hasil nasional SNLIK 2024 dan 2025 menggunakan Metode Keberlanjutan pada rilis OJK bersama BPS. Kedua tahun itu adalah arsip historis, bukan gambaran terbaru 2026.</p>
<h2>Rancangan pembacaan: dari pertanyaan ke angka</h2>
<p>Unit analisis catatan ini adalah indeks agregat yang dipublikasikan, bukan individu. Data masuk berasal dari rilis SNLIK dan booklet OJK. Kami memilih Metode Keberlanjutan untuk pembacaan 2024 dan 2025, lalu mempertahankan satuan persen. Tidak ada pengisian nilai yang hilang, pembobotan ulang, atau penggabungan responden lintas tahun yang dilakukan oleh FINWATCH.</p>
<p>Langkah pertama adalah mencatat nilai dan lingkupnya. Langkah kedua menghitung selisih secara aritmetis. Langkah ketiga menulis kesimpulan sebatas yang didukung ukuran tersebut. Urutan ini terdengar sederhana, tetapi mencegah visual yang menarik mendahului pemeriksaan atas pertanyaan yang sebenarnya bisa dijawab data.</p>
<h2>Jejak hitung yang dapat diulang</h2>
<table><thead><tr><th>Perbandingan</th><th>Perhitungan</th><th>Hasil</th></tr></thead><tbody><tr><td>Perubahan literasi nasional</td><td>66,46 − 65,43</td><td>1,03 pp</td></tr><tr><td>Perubahan inklusi nasional</td><td>80,51 − 75,02</td><td>5,49 pp</td></tr><tr><td>Jarak dua indeks 2024</td><td>75,02 − 65,43</td><td>9,59 pp</td></tr><tr><td>Jarak dua indeks 2025</td><td>80,51 − 66,46</td><td>14,05 pp</td></tr><tr><td>Perubahan jarak indeks</td><td>14,05 − 9,59</td><td>4,46 pp</td></tr></tbody></table>
<p>Huruf pp berarti poin persentase. Kita mengurangkan dua rasio yang sudah dinyatakan dalam persen. Perubahan relatif memerlukan operasi lain: selisih dibagi nilai awal, kemudian dikali 100%. Catatan ini memilih pp supaya hubungan antarnilai sumber tetap mudah diperiksa tanpa mengganti skala di tengah pembacaan.</p>
<p>Untuk kelompok tempat tinggal, 70,89 dikurangi 59,60 menghasilkan 11,29 pp pada literasi. Sementara itu 83,61 dikurangi 75,70 menghasilkan 7,91 pp pada inklusi. Kedua selisih menggunakan tahun dan kelompok pembanding yang sama. Kita tidak membandingkan nilai kota pada satu tahun dengan nilai desa pada tahun lain.</p>
<h2>Dua perubahan yang bergerak berbeda</h2>
<p>Indeks literasi nasional berubah dari 65,43% pada 2024 menjadi 66,46% pada 2025. Inklusi berubah dari 75,02% menjadi 80,51%. Hasil pengurangan sederhana menunjukkan kenaikan masing-masing 1,03 pp dan 5,49 pp. Selisih kedua indeks pada 2025 adalah 14,05 pp.</p>
<p>Artinya, dalam perbandingan deskriptif ini, indeks penggunaan layanan bertambah lebih besar daripada indeks pemahaman. Ini adalah ringkasan pergerakan indeks, bukan bukti bahwa individu tertentu mulai memakai produk tanpa memahami risikonya. Dua persentase yang diringkas secara terpisah tidak menunjukkan siapa yang termasuk dalam kedua kelompok sekaligus.</p>
<h2>Perkotaan dan perdesaan: dua kelompok, bukan dua provinsi</h2>
<p>Rilis 2025 mencatat literasi perkotaan 70,89% dan perdesaan 59,60%; inklusi masing-masing 83,61% dan 75,70%. Perbedaan antarkelompok adalah 11,29 pp untuk literasi dan 7,91 pp untuk inklusi. Angka ini menggambarkan kelompok survei, bukan peringkat provinsi.</p>
<p>Karena itu pasangan nasional dan kelompok tempat tinggal ditampilkan melalui visual yang terpisah. Peta provinsi memakai arsip SNLIK 2022 dengan sumbernya sendiri, bukan rincian provinsi hasil pembagian angka nasional 2025. Bentuk visual mengikuti butir data yang benar-benar tersedia.</p>
<h2>Dari temuan menuju pertanyaan lanjutan</h2>
<p>Temuan yang langsung didukung angka adalah bahwa kenaikan indeks inklusi lebih besar daripada kenaikan indeks literasi dalam pasangan publikasi yang dipilih. Tafsir yang mungkin diajukan adalah kebutuhan menyediakan penjelasan produk saat penggunaan layanan berkembang. Tafsir tersebut dapat berguna sebagai pertanyaan kerja, tetapi belum membuktikan rancangan edukasi tertentu akan berhasil.</p>
<p>Misalnya, data ini tidak menunjukkan apakah penjelasan biaya dalam aplikasi lebih efektif daripada kelas tatap muka. Data juga tidak mengukur apakah orang yang baru menggunakan produk adalah kelompok yang sama dengan mereka yang literasinya meningkat. Untuk menilai mekanisme seperti itu, dibutuhkan rancangan penelitian, pengukuran, dan informasi tambahan yang sesuai.</p>
<p>Sebagai hipotesis kerja, layanan informasi dapat lebih berguna bila muncul pada saat orang membuat keputusan: ketika membandingkan biaya, membaca kontrak, atau mencari bantuan. Hipotesis itu perlu diuji melalui riset yang berbeda. Arsip data ini sendiri tidak mengukur keberhasilan suatu program edukasi atau menentukan intervensi mana yang paling efektif.</p>
<h2>Apa yang belum bisa dijawab?</h2>
<ul><li><strong>Penyebab kesenjangan.</strong> Perbandingan ini tidak mengisolasi pengaruh pendidikan, pendapatan, usia, infrastruktur, atau karakteristik lain.</li><li><strong>Ketidakpastian estimasi.</strong> Catatan ini tidak menghitung rentang ketidakpastian statistik. Analisis hanya memakai angka ringkasan, tanpa data responden, bobot, dan rincian rancangan sampel.</li><li><strong>Pengalaman konsumen.</strong> Akses belum menjelaskan biaya, kesesuaian produk, atau kualitas penyelesaian keluhan.</li></ul>
<p>SNLIK 2025 juga mempunyai Metode Cakupan DNKI. Cakupannya berbeda; angka dari metode tersebut tidak disisipkan ke deret Keberlanjutan hanya karena tampak lebih tinggi. Perbandingan memerlukan penyebut dan definisi yang konsisten. Publikasi setelah 2025 harus ditinjau terpisah sebelum ditambahkan.</p>
<h2>Bagaimana pembaca dapat memeriksa catatan ini?</h2>
<p>Buka referensi SNLIK, cocokkan metode serta tahun, lalu ulangi pengurangan dalam tabel. Periksa juga apakah angka yang dibaca berasal dari nasional atau kelompok tempat tinggal. Bila menggunakan peta, berpindahlah ke bagian arsip 2022 dan perlakukan sebagai potret tersendiri. Kehadiran peta tidak menjadikan nilai provinsi 2022 bagian dari deret nasional 2024–2025.</p>
<p>Perbedaan sampel dan bobot survei juga perlu diperhatikan. Catatan ini tidak menganggap responden pada dua tahun sebagai orang yang sama. Karena itu perubahan indeks tidak ditulis sebagai perjalanan individu tertentu dari belum memahami menjadi memahami. Ukuran agregat dapat berubah ketika pengalaman yang membentuknya beragam.
</p><p>Ukuran kelompok perkotaan dan perdesaan juga tidak digunakan untuk menghitung rata-rata nasional secara sederhana. Rata-rata dua angka tersebut dengan bobot sama belum tentu sama dengan estimasi nasional, karena bobot masing-masing kelompok dalam survei bukan otomatis separuh. Kita menggunakan angka nasional yang dipublikasikan dan membatasi pengolahan sendiri pada selisih yang dinyatakan secara terbuka.
</p><p>Hasil yang ingin dibawa dari catatan ini bukan satu label tentang masyarakat. Yang lebih berguna adalah kebiasaan memisahkan penggunaan, pemahaman, dan pengalaman konsumen, kemudian mencari bukti yang tepat untuk masing-masing. Kesenjangan indeks dapat menjadi awal percakapan yang terarah selama kita tetap menyebut apa yang diketahui dan apa yang belum diuji.</p>
HTML,
            ],
            [
                'slug' => 'potret-perbankan-juni-2025-angka-dan-batas-ceritanya',
                'title' => 'Potret perbankan Juni 2025: angka dan batas ceritanya',
                'excerpt' => 'Membaca kredit, simpanan, modal dan NPL sebagai satu potret industri. Catatan ini menekankan keterbandingan, bukan memberi ramalan atau rating bank.',
                'category' => 'Catatan Riset', 'category_slug' => 'catatan-riset', 'type' => 'research', 'tags' => ['spi-juni-2025', 'perbankan', 'riset-deskriptif'], 'sources' => [$spi, $rdk], 'theme' => 'research',
                'body' => <<<'HTML'
<p>Membaca neraca industri mirip melihat kota dari beberapa jendela. Jendela pertama menunjukkan ukuran kegiatan, jendela kedua kualitas sebagian aset, dan jendela lain bantalan modal. Analogi ini membantu menata pertanyaan, tetapi data yang dipakai tetap berupa angka agregat dari publikasi OJK. Catatan ini tidak mengamati transaksi atau neraca individual nasabah.</p>
<p><strong>Pertanyaan riset:</strong> informasi apa yang bisa diringkas dari arsip data industri perbankan Juni 2025, dan kesimpulan apa yang sebaiknya ditahan? Catatan ini memakai angka agregat Bank Umum pada berkas tabel Excel SPI Juni 2025 serta satu observasi NPL gross yang disebut dalam rilis OJK.</p>
<h2>Rancangan riset: satu tanggal, beberapa jendela</h2>
<p>Pertanyaan dipersempit menjadi dua: bagaimana membandingkan ukuran industri dengan satuan yang seragam, dan bagaimana menjelaskan rasio tanpa menganggapnya sebagai putusan kesehatan bank? Pembatasan itu penting karena satu potret agregat tidak dapat menjawab apakah sebuah produk cocok bagi seseorang atau bagaimana kondisi seluruh bank di dalam industri.</p>
<p>Nilai total berasal dari berkas tabel Excel SPI Juni 2025 yang disimpan sebagai arsip data. Angka NPL gross berasal dari rilis RDKB Agustus 2025, tetapi kalimat sumber merujuk Juni 2025. Jadi nama bulan pada dokumen sumber tidak otomatis menjadi periode observasi untuk semua angka di dalamnya. Setiap angka tetap membawa jejak sumbernya sendiri.</p>
<h2>Jejak pengolahan dan pembulatan</h2>
<p>Nilai total dalam berkas tabel Excel menggunakan rupiah miliar. Konversi ke rupiah triliun dilakukan dengan membagi nilai miliar dengan 1.000. Sebagai ilustrasi konversi satuan saja, Rp1.500 miliar sama dengan Rp1,5 triliun. Konversi ini mengubah cara menulis ukuran, bukan mengubah kegiatan ekonomi yang diwakilinya.</p>
<p>Untuk kredit, nilai yang disimpan sekitar Rp8.059,79275870 triliun. Tampilan naratif membulatkannya menjadi Rp8.059,79 triliun agar kalimat dapat dibaca dengan nyaman. Angka untuk menghitung sebaiknya diambil dari data yang menyimpan ketelitian angka asal, bukan disalin kembali dari label yang sudah dibulatkan. Perbedaan kecil pada desimal akhir dapat berasal dari pembulatan tersebut.</p>
<table><thead><tr><th>Ukuran</th><th>Nilai tampilan Juni 2025</th><th>Pertanyaan yang dibantu</th></tr></thead><tbody><tr><td>Kredit</td><td>Rp8.059,79 triliun</td><td>Berapa penyaluran kredit dalam cakupan sumber?</td></tr><tr><td>Dana pihak ketiga</td><td>Rp9.329,00 triliun</td><td>Berapa sumber dana dari pihak ketiga?</td></tr><tr><td>Aset</td><td>Rp12.822,65 triliun</td><td>Berapa ukuran aset industri?</td></tr><tr><td>CAR</td><td>Sekitar 25,81%</td><td>Bagaimana rasio modal terhadap aset tertimbang menurut risiko?</td></tr><tr><td>NPL gross</td><td>2,22%</td><td>Berapa porsi kredit bermasalah menurut definisi sumber?</td></tr></tbody></table>
<h2>Apa hubungan angka-angka itu?</h2>
<p>Tabel di atas menempatkan ukuran industri dan rasio dalam satu tempat agar titik awal pembacaan jelas. LDR yang tercatat pada sumber sekitar 86,40%. Setiap nilai tetap membawa rujukan dan definisinya sendiri, termasuk NPL gross yang diambil dari dokumen berbeda.</p>
<p>Angka-angka itu tidak mempunyai penyebut yang sama. Aset adalah ukuran neraca; kredit merupakan salah satu penyaluran dana; simpanan menggambarkan salah satu sumber pendanaan. CAR berbasis aset tertimbang menurut risiko, sedangkan LDR membandingkan kredit dan dana pihak ketiga sesuai definisi sumber. Menjejerkannya berguna untuk orientasi, bukan untuk dijumlahkan.</p>
<h2>Perhitungan yang menjelaskan, bukan menambah kepastian</h2>
<p>Sebuah rasio ilustratif dapat dihitung dari level kredit dan simpanan yang dibulatkan: 8.059,79 dibagi 9.329,00, lalu dikali 100%, menghasilkan sekitar 86,40%. Kedekatan dengan LDR yang ditampilkan membantu orientasi. Namun pemeriksaan resmi atas rasio tetap harus mengikuti pembilang, penyebut, cakupan, serta presisi dalam definisi sumber; pembagian dua label tidak menggantikan metadata.</p>
<p>Contoh lain menunjukkan mengapa satu titik belum menjadi tren. Rasio NPL 2,22% berarti ada suatu proporsi pada Juni 2025. Agar menyatakan “naik”, kita memerlukan angka pembanding yang sejenis pada periode sebelumnya. Angka pembanding tidak boleh diambil dari definisi NPL net, atau dari institusi lain, hanya supaya kalimat mempunyai arah gerak.</p>
<h2>Mengapa arah gerak perlu konteks?</h2>
<p>Perubahan kredit dapat dipengaruhi permintaan, penawaran, pembayaran kembali, dan perubahan cakupan. Perubahan rasio bisa berasal dari pembilang, penyebut, atau keduanya. Karena itu satu garis yang naik tidak otomatis berarti satu kondisi membaik; satu garis yang turun juga tidak otomatis berarti masalah.</p>
<p>Untuk rasio, FINWATCH menampilkan perubahan dalam pp. Untuk level positif, perubahan relatif dihitung dari nilai terbaru dibanding periode sebelumnya. Periode harus berurutan dan catatan definisinya sama. Bila syarat itu tidak terpenuhi, perubahan diberi keterangan belum sebanding agar pembaca tidak diarahkan pada tren yang dibuat-buat.</p>
<h2>Apa arti sinyal penelitian di FINWATCH?</h2>
<p>Aturan EWS di situs ini merupakan parameter eksplorasi yang dapat diperiksa. Pemenuhan aturan tidak setara dengan penetapan tingkat kesehatan oleh regulator. Demikian pula z-score historis hanya mengukur posisi terhadap riwayat pembanding yang dipilih, bukan probabilitas krisis. Jumlah pengamatan dan perubahan definisi dapat memengaruhi hasilnya.</p>
<p>Seri NPL yang dikurasi di sini baru mempunyai satu titik. Kami tidak menggambar narasi kenaikan atau penurunan dari satu observasi. Ketiadaan deret bukan alasan untuk menebak titik yang hilang atau meminjam nilai dari periode berbeda tanpa penjelasan.</p>
<h2>Apa yang akan mengubah kesimpulan?</h2>
<p>Rilis yang direvisi dapat mengubah nilai historis. Perubahan definisi dapat membuat pasangan bulan tidak sebanding. Pemisahan menurut kelompok bank dapat memperlihatkan variasi yang tidak tampak pada agregat. Masing-masing keadaan itu adalah alasan meninjau ulang ringkasan, bukan alasan menambahkan kepastian pada kesimpulan awal.</p>
<p>Riset lanjutan yang masuk akal adalah menyusun deret yang lebih panjang, memeriksa titik perubahan metode, dan membandingkan cakupan yang konsisten. Jika ingin menjelaskan penyebab, diperlukan rancangan analisis yang lebih kuat daripada melihat dua kurva bergerak bersamaan. Korelasi deskriptif, sinyal aturan, dan uji sebab-akibat menjawab pertanyaan yang berbeda.</p>
<p>Bagi pembaca umum, hasil praktis catatan ini adalah sebuah urutan membaca: kenali ukuran, cek periode dan satuan, pisahkan level dari rasio, lalu periksa batas datanya. Dengan urutan itu, angka besar tidak perlu terasa seperti sandi. Kita dapat memahami bagian yang tersedia sambil tetap menyisakan ruang untuk pertanyaan yang belum terjawab.</p>
<h2>Batas penggunaan catatan ini</h2>
<ul><li>Agregat industri tidak mewakili neraca setiap bank.</li><li>Nilai historis tidak menggambarkan kondisi terkini November 2026.</li><li>Analisis ini tidak menguji sebab-akibat dan tidak menghasilkan proyeksi.</li><li>Perhitungan sederhana tidak menggantikan penilaian risiko yang menggunakan informasi lebih lengkap.</li></ul>
<p><strong>Metode:</strong> kurasi arsip data, pemeriksaan satuan dan perbandingan deskriptif. Pembulatan dua desimal digunakan untuk kenyamanan membaca; ekspor data menyimpan ketelitian angka asal. Langkah riset berikutnya adalah memeriksa seri yang lebih baru, konsistensi definisi, dan dokumen metodologinya sebelum memperluas kesimpulan.</p>
HTML,
            ],
        ];
    }
}
