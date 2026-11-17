import { ArrowUpRight, FileSearch, FlaskConical, ListChecks } from 'lucide-react';
import { Link } from 'react-router-dom';
import type { Article } from './articles';
import './research-reading.css';

const researchNotes: Record<string, { question: string; period: string; scope: string; method: string; findings: { value: string; label: string }[] }> = {
  'membaca-jarak-akses-dan-pemahaman-snlik-2025': {
    question: 'Apakah penggunaan layanan dan pemahaman keuangan bergerak seiring?',
    period: 'SNLIK 2024–2025', scope: 'Nasional, perkotaan dan perdesaan', method: 'Perbandingan deskriptif; selisih dalam poin persentase.',
    findings: [{ value: '5,49 pp', label: 'kenaikan indeks inklusi nasional' }, { value: '1,03 pp', label: 'kenaikan indeks literasi nasional' }],
  },
  'potret-perbankan-juni-2025-angka-dan-batas-ceritanya': {
    question: 'Apa yang sebenarnya bisa diceritakan satu potret industri perbankan?',
    period: 'Juni 2025', scope: 'Agregat Bank Umum dalam sumber', method: 'Kurasi snapshot, konversi satuan dan pembacaan rasio.',
    findings: [{ value: '2,22%', label: 'NPL gross dalam potret Juni 2025' }, { value: '25,81%', label: 'CAR agregat, dibulatkan dua desimal' }],
  },
};

export function ResearchFeature({ article }: { article: Article }) {
  const note = article.research_profile_slug ? researchNotes[article.research_profile_slug] : undefined;
  return <article className="research-feature-paper">
    <div className="research-paper-top"><span><FlaskConical size={17} />CATATAN PILIHAN</span><span>{note ? 'ANALISIS DESKRIPTIF INDEPENDEN' : 'PUBLIKASI RISET INDEPENDEN'}</span></div>
    <div className="research-paper-content"><div><p className="research-paper-question">{note?.question || 'Pertanyaan yang ditelusuri dalam catatan ini'}</p><h2><Link to={`/artikel/${article.slug}`}>{article.title}</Link></h2><p className="research-paper-excerpt">{article.excerpt}</p><div className="research-paper-meta"><span>{article.author.public_name}</span><span>{article.reading_minutes} menit baca</span>{note && <span>{note.period}</span>}</div><Link className="research-paper-open" to={`/artikel/${article.slug}`}>Buka naskah dan jejak hitung <ArrowUpRight size={18} /></Link></div><div className="research-paper-evidence"><span><FileSearch size={18} />BUKTI YANG DIBACA</span>{note?.findings.map(item => <div className="research-paper-finding" key={item.label}><strong>{item.value}</strong><p>{item.label}</p></div>)}<p className="research-paper-limit">{note ? 'Potret historis. Perbandingan ini tidak menguji sebab-akibat dan tidak memberi rating keamanan.' : 'Periksa periode, sumber, dan keterbatasan dalam naskah sebelum menggunakan temuannya.'}</p></div></div>
    <footer><ListChecks size={17} /><p>{note?.method || 'Baca naskah dan referensi untuk menilai dukungan atas setiap kesimpulan.'}</p></footer>
  </article>;
}

export function ResearchMethodCard({ article }: { article: Article }) {
  const note = article.research_profile_slug ? researchNotes[article.research_profile_slug] : undefined;
  const methodHeading = article.toc?.find(item => /metode|rancangan|cara (menghitung|membaca)/i.test(item.text));
  return <section className="research-method-card" aria-labelledby="research-method-title"><div className="research-method-heading"><FlaskConical size={22} /><div><p>SEBELUM MEMBACA TEMUAN</p><h2 id="research-method-title">Kenali rancangan catatan ini.</h2></div></div><dl><div><dt>{note ? 'Pertanyaan' : 'Fokus pembahasan'}</dt><dd>{note?.question || article.title}</dd></div><div><dt>Data & cakupan</dt><dd>{note ? `${note.period}. ${note.scope}.` : `${article.sources?.length || 0} referensi dicantumkan penulis. Periksa periode dan cakupan pada naskah serta sumbernya.`}</dd></div><div><dt>Cara membaca</dt><dd>{note?.method || (methodHeading ? <a href={`#${methodHeading.id}`}>Baca bagian “{methodHeading.text}” dalam naskah.</a> : 'Belum ada bagian metode dengan judul tersendiri. Telusuri penjelasan penulis dan sumber yang dicantumkan.')}</dd></div><div><dt>Batas kesimpulan</dt><dd>{note ? 'Deskripsi historis, tanpa uji kausal atau proyeksi. Rincian keterbatasan dibahas dalam naskah.' : 'Periksa apakah bukti yang disajikan mendukung kesimpulan penulis, serta apa yang belum dapat dijawab.'}</dd></div></dl><a href="#referensi-artikel">Mulai dari referensinya <ArrowUpRight size={15} /></a></section>;
}
