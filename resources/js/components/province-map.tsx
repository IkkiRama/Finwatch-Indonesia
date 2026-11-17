import { useState } from 'react';
import { ArrowRight, CircleHelp, ExternalLink, MapPin, MessagesSquare, ShieldCheck } from 'lucide-react';
import { provinceSurvey2022, provincialBoundarySource, provincialSurveySource } from '../lib/province-survey';
import './province-map.css';

type MapMetric = 'inclusion' | 'literacy';
const percentage = (value: number) => new Intl.NumberFormat('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(value);

export function ProvinceInclusionMap() {
  const [provinceCode, setProvinceCode] = useState('33');
  const [metric, setMetric] = useState<MapMetric>('inclusion');
  const [showNumbers, setShowNumbers] = useState(false);
  const province = provinceSurvey2022.find(item => item.code === provinceCode) || provinceSurvey2022[0];
  const nationalValue = metric === 'inclusion' ? 85.10 : 49.68;
  const metricLabel = metric === 'inclusion' ? 'Inklusi' : 'Literasi';
  const difference = province[metric] - nationalValue;

  return <section className="province-explorer" aria-labelledby="province-title">
    <header className="province-heading">
      <div><p className="story-eyebrow">CERITA DARI DAERAH</p><h2 id="province-title">Indonesia luas.<br /><em>Pengalamannya beragam.</em></h2><p>Pilih provinsi untuk melihat seberapa banyak penduduk menggunakan layanan keuangan dan memahami keuangan menurut survei OJK.</p></div>
      <div className="province-archive-stamp"><MapPin size={20} /><strong>Arsip SNLIK 2022</strong><span>34 wilayah saat survei</span></div>
    </header>
    <div className="province-period-note"><CircleHelp size={19} /><p>Peta ini memakai <strong>data provinsi tahun 2022</strong>. Angka nasional 2024/2025 di atas berasal dari survei berbeda. Baca setiap bagian sesuai tahunnya; keduanya tidak langsung membentuk satu tren.</p></div>
    <div className="province-controls">
      <div className="province-metric-toggle" role="group" aria-label="Indikator warna peta provinsi">
        <button type="button" aria-pressed={metric === 'inclusion'} onClick={() => setMetric('inclusion')}>Menggunakan layanan</button>
        <button type="button" aria-pressed={metric === 'literacy'} onClick={() => setMetric('literacy')}>Memahami keuangan</button>
      </div>
      <label htmlFor="province-select">Pilih provinsi<select id="province-select" value={provinceCode} onChange={event => setProvinceCode(event.target.value)}>{provinceSurvey2022.map(item => <option key={item.code} value={item.code}>{item.name}</option>)}</select></label>
    </div>
    <div className="province-map-layout">
      <div className="province-map-canvas">
        <p className="province-map-instruction"><MapPin size={15} />Klik wilayah, gunakan tombol Tab, atau pilih dari daftar.</p>
        <svg viewBox="0 0 950 355" className="indonesia-province-map" aria-label={`Peta ${metricLabel.toLowerCase()} keuangan 34 provinsi, SNLIK 2022`}>
          <title>{metricLabel} keuangan provinsi menurut SNLIK OJK 2022</title>
          <desc>Warna semakin pekat berarti persentase semakin tinggi. Pilih wilayah untuk membaca kedua indikator. Batas Papua dan Papua Barat mengikuti cakupan historis survei.</desc>
          {provinceSurvey2022.map(item => <path key={item.code} d={item.path} role="button" tabIndex={0} aria-label={`${item.name}, ${metricLabel.toLowerCase()} ${percentage(item[metric])} persen, tahun 2022`} aria-pressed={item.code === provinceCode} className={item.code === provinceCode ? 'province-shape selected' : 'province-shape'} fill={`hsl(260 35% ${94 - item[metric] * 0.36}%)`} onClick={() => setProvinceCode(item.code)} onKeyDown={event => { if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); setProvinceCode(item.code); } }}><title>{item.name}: {percentage(item[metric])}%</title></path>)}
        </svg>
        <div className="province-map-legend"><span>{metricLabel} (%)</span><div><span>0</span><i /><span>100</span></div><small>Warna menunjukkan besaran indeks.</small></div>
        <p className="province-boundary-note">Batas disederhanakan untuk visual. Papua dan Papua Barat masih mengikuti cakupan sebelum pemekaran; angka tidak dibagi ke provinsi baru.</p>
      </div>
      <aside className="province-detail" aria-live="polite" aria-atomic="true">
        <p className="story-eyebrow">PROVINSI PILIHAN · 2022</p><h3><MapPin size={20} />{province.name}</h3>
        <div className="province-indicator"><span>Inklusi keuangan<small>Menggunakan layanan</small></span><strong>{percentage(province.inclusion)}<small>%</small></strong></div>
        <div className="province-mini-bar"><i style={{ width: `${province.inclusion}%` }} /></div>
        <div className="province-indicator"><span>Literasi keuangan<small>Memahami keuangan</small></span><strong>{percentage(province.literacy)}<small>%</small></strong></div>
        <div className="province-mini-bar literacy"><i style={{ width: `${province.literacy}%` }} /></div>
        <p className="province-comparison"><strong>{metricLabel} nasional 2022: {percentage(nationalValue)}%</strong><span>{Math.abs(difference) < 0.005 ? 'Sama dengan' : `${percentage(Math.abs(difference))} poin persentase ${difference > 0 ? 'di atas' : 'di bawah'}`} angka nasional dalam survei yang sama.</span></p>
        <p className="province-detail-limit">Dua indeks menjawab pertanyaan berbeda. Selisihnya tidak menunjukkan jumlah orang yang memakai layanan tanpa memahami risikonya.</p>
      </aside>
    </div>
    <div className="province-evidence"><div><strong>Dua indikator tersedia untuk 34 wilayah.</strong><p>SNLIK OJK 2022 mencakup 14.634 responden usia 15–79 tahun. Ini potret survei historis, bukan penilaian keamanan layanan atau data terkini setiap provinsi.</p></div><a href={provincialSurveySource} target="_blank" rel="noopener noreferrer">Buka booklet OJK <ExternalLink size={15} /></a></div>
    <details className="province-source-detail"><summary>Sumber, metode, dan batas penggunaan</summary><p>Angka disalin dari grafik provinsi pada halaman pertama booklet SNLIK 2022 dan dicocokkan dengan publikasi aslinya. Kedua indikator berupa persen, dengan dua angka desimal. Perbandingan terhadap nasional hanya memakai angka 2022 dalam publikasi yang sama: literasi 49,68% dan inklusi 85,10%.</p><p>Data 2022 dipisahkan dari SNLIK 2024/2025 karena perubahan pelaksanaan dan metode survei. Wilayah yang kemudian mekar tidak mendapat angka hasil pembagian atau perkiraan.</p><p>Geometri diambil dari layanan batas provinsi historis <a href={provincialBoundarySource} target="_blank" rel="noopener noreferrer">BNPB InaRISK</a>, disederhanakan untuk tampilan. Peta ini membantu penelusuran statistik, bukan acuan batas administratif resmi.</p><p>Sumber diperiksa 3 November 2026. Pembaruan peta memerlukan publikasi provinsi yang dapat diverifikasi; belum mengambil data provinsi secara langsung dari API OJK.</p></details>
    <button type="button" className="province-table-toggle" aria-expanded={showNumbers} onClick={() => setShowNumbers(!showNumbers)}>{showNumbers ? 'Tutup' : 'Lihat'} semua angka provinsi <ArrowRight size={16} /></button>
    {showNumbers && <div className="province-numbers" role="region" tabIndex={0} aria-label="Tabel SNLIK provinsi tahun 2022"><table><thead><tr><th>Provinsi (cakupan 2022)</th><th>Literasi (%)</th><th>Inklusi (%)</th></tr></thead><tbody>{provinceSurvey2022.map(item => <tr key={item.code}><td><button type="button" onClick={() => setProvinceCode(item.code)} aria-pressed={item.code === provinceCode}>{item.name}</button></td><td>{percentage(item.literacy)}</td><td>{percentage(item.inclusion)}</td></tr>)}</tbody></table></div>}
  </section>;
}

export function ConsumerDataGuide() {
  return <div className="consumer-reading-guide">
    <div className="consumer-reading-heading"><CircleHelp size={22} /><div><h3>Satu halaman, dua cara membaca.</h3><p>Angka memberi konteks. Bantuan resmi punya jalurnya sendiri.</p></div></div>
    <div className="consumer-guide-columns"><section><span className="consumer-guide-icon"><MessagesSquare size={20} /></span><h4>Saat membaca angka</h4><p>Layanan adalah payung yang lebih luas dan dapat mencakup pengaduan. Menjumlahkan keduanya bisa menghitung kejadian yang sama dua kali.</p><p>Perhatikan tanggal “sampai”. Total sejak Januari hingga pertengahan Agustus tidak sebanding langsung dengan total hingga akhir Agustus.</p><details><summary>Apa arti data kumulatif?</summary><p>Angka terus terkumpul sejak tanggal awal yang disebut sumber. Kenaikan total belum tentu berarti masalah makin sering; rentang pencatatannya bisa bertambah panjang.</p></details></section><section><span className="consumer-guide-icon mint"><ShieldCheck size={20} /></span><h4>Saat membutuhkan bantuan</h4><p>Ada masalah dengan produk atau layanan keuangan? Simpan bukti dan gunakan kanal pengaduan resmi. Diskusi FINWATCH tidak meneruskan pengaduan ke OJK.</p><a href="https://kontak157.ojk.go.id/" target="_blank" rel="noopener noreferrer">Buka portal Kontak OJK 157 <ExternalLink size={15} /></a><small>Pastikan alamat situs berakhiran ojk.go.id sebelum mengirim dokumen pribadi.</small></section></div>
  </div>;
}
