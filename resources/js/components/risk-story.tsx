import { useState } from 'react';
import { ArrowDownRight, ArrowRight, ArrowUpRight, CircleHelp, ExternalLink, Fingerprint, ScanLine, ShieldCheck } from 'lucide-react';
import { Link } from 'react-router-dom';
import type { Series, Signal } from './analytics';
import { indicatorStories, shortDate } from './finance-story';
import { EmptyState, StatusBadge } from './shared';

const format = (value: number, digits = 2) => new Intl.NumberFormat('id-ID', { maximumFractionDigits: digits }).format(value);
const seriesKey = (item: Series) => `${item.dataset_id}-${item.code}-${item.scope}-${item.segment}`;
const signalStates: Record<string, { title: string; description: string }> = {
  triggered: { title: 'Perlu ditelusuri', description: 'Aturan terpenuhi. Periksa penyebab perubahan dan indikator lain sebelum mengambil kesimpulan.' },
  within_rule: { title: 'Tidak melewati batas aturan', description: 'Perubahan tidak memenuhi aturan ini. Hasil ini tidak memeriksa seluruh risiko atau menjamin kondisi bank.' },
  insufficient_data: { title: 'Data belum cukup', description: 'Riwayat belum mencapai jumlah minimum. Penilaian ditunda sampai bukti tersedia.' },
  incomparable_data: { title: 'Data belum sebanding', description: 'Periode, satuan, atau metode belum memenuhi syarat perbandingan. Perubahan tidak ditafsirkan sebagai sinyal.' },
};

export function Signals({ signals, compact = false }: { signals: Signal[]; compact?: boolean }) {
  return <section className="warning-story" aria-labelledby="warning-heading">
    {compact ? <div className="risk-desk-intro"><p className="story-eyebrow">DARI PERUBAHAN KE PERTANYAAN</p><h2 id="warning-heading">Adakah perubahan yang perlu ditelusuri?</h2><p>Aturan riset di bawah membantu menyaring perhatian. Hasilnya mengikuti batas dan data yang tercantum, serta perlu dibaca bersama konteks indikator lain.</p><Link className="story-text-link" to="/peringatan-dini">Pelajari cara kerja peringatan dini <ArrowRight size={15} /></Link></div> : <><div className="warning-purpose"><span className="risk-icon"><ScanLine size={28} strokeWidth={1.5} /></span><div><p className="story-eyebrow">DARI PERUBAHAN KE PERTANYAAN</p><h2 id="warning-heading">Untuk apa peringatan dini?</h2><p>Bayangkan melihat panel kendaraan: satu lampu membantu kita tahu bagian mana yang perlu diperiksa. Di FINWATCH, aturan riset menjalankan fungsi serupa pada data industri perbankan. Angka yang melewati batas menjadi alasan untuk membaca lebih jauh.</p><p>Sinyal ini memakai publikasi agregat historis yang tersedia. Ia tidak menilai bank tertentu, memprediksi kegagalan, atau memberi keputusan tentang simpanan Anda. Tanggal observasi pada setiap aturan menunjukkan masa yang sedang dibaca. Pilihan tahun membatasi riwayat yang dipakai, sehingga jumlah data pendukung dapat berubah.</p></div></div>
    <ol className="warning-steps"><li><span>01</span><h3>Pastikan datanya layak</h3><p>Periksa jumlah observasi, periode yang berurutan, satuan, dan metode yang sebanding. Data yang belum cukup diberi keterangan tersendiri.</p></li><li><span>02</span><h3>Uji satu pertanyaan</h3><p>Misalnya, apakah LDR naik lebih dari batas poin persentase yang ditetapkan? Nilai aktual dibandingkan dengan aturan yang tercantum.</p></li><li><span>03</span><h3>Baca konteksnya</h3><p>Telusuri publikasi sumber, arah perubahan, dan indikator terkait. Batas riset membantu menyaring perhatian, bukan menetapkan rating resmi.</p></li></ol></>}
    <div className="warning-rules-heading"><div><p className="story-eyebrow">BUKTI YANG BISA DIPERIKSA</p><h2>Aturan yang sedang dibaca</h2></div><span>{signals.length} aturan tersedia</span></div>
    {signals.length ? signals.map(signal => <SignalEvidence key={`${signal.rule_id}-${signal.dataset_id}-${signal.scope}-${signal.segment}`} signal={signal} />) : <EmptyState compact title="Belum ada aturan yang bisa diperiksa">Sinyal baru muncul saat aturan dan data pendukung tersedia.</EmptyState>}
    <details className="warning-legend"><summary><CircleHelp size={18} />Apa arti setiap status?</summary><div>{Object.entries(signalStates).map(([key, state]) => <article key={key}><strong>{state.title}</strong><p>{state.description}</p></article>)}</div></details>
    <div className="warning-next"><ShieldCheck size={24} /><p><strong>Lanjutkan dengan pertanyaan yang tepat.</strong> Pemantauan risiko memperlihatkan perubahan yang melatarbelakangi sinyal. Simulasi membantu memahami hubungan kredit dan modal melalui asumsi Anda sendiri.</p><div><Link to="/pemantauan-risiko">Lihat perubahan <ArrowRight size={15} /></Link><Link to="/simulasi-stres">Coba simulasi <ArrowRight size={15} /></Link></div></div>
  </section>;
}

function SignalEvidence({ signal }: { signal: Signal }) {
  const state = signalStates[signal.status] || signalStates.insufficient_data;
  const evaluable = ['triggered', 'within_rule'].includes(signal.status) && signal.value !== null;
  const comparison = signal.operator === 'lt' ? 'lebih kecil dari' : 'lebih besar dari';
  const minimum = Math.min(0, signal.value ?? 0, signal.threshold);
  const maximum = Math.max(0, signal.value ?? 0, signal.threshold);
  const span = Math.max(maximum - minimum, 1);
  const position = (value: number) => 8 + 84 * (value - minimum) / span;
  return <article className={`warning-rule warning-state-${signal.status}`}>
    <header><div><span className="warning-rule-id">{signal.indicator_code.toUpperCase()} / ATURAN {signal.version}</span><h3>{signal.name}</h3></div><StatusBadge tone={signal.status === 'triggered' ? 'amber' : 'neutral'}>{state.title}</StatusBadge></header>
    <p className="warning-question">{signal.operator === 'change_gt' ? 'Apakah perubahan antarobservasi' : 'Apakah nilai indikator'} {comparison} <strong>{format(signal.threshold)} {signal.unit}</strong>?</p>
    <div className="warning-evidence"><div><small>{signal.operator === 'change_gt' ? 'Perubahan yang diamati' : 'Nilai yang diamati'}</small><strong>{evaluable ? `${format(signal.value!)} ${signal.unit}` : 'Belum dinilai'}</strong></div><div><small>Batas aturan penelitian</small><strong>{format(signal.threshold)} {signal.unit}</strong></div><div><small>Data pendukung</small><strong>{signal.samples}<span> / minimal {signal.minimum_samples}</span></strong></div></div>
    {evaluable && <div className="warning-meter" role="img" aria-label={`Posisi nilai ${format(signal.value!)} ${signal.unit} dan batas ${format(signal.threshold)} ${signal.unit}. Aturan ${comparison} batas.`}><div className="warning-meter-track"><i className="warning-zero" style={{ left: `${position(0)}%` }} /><span className="warning-observation" style={{ left: `${position(signal.value!)}%` }} /><span className="warning-threshold" style={{ left: `${position(signal.threshold)}%` }} /></div><div className="warning-meter-legend"><span><i />Nilai observasi</span><span><i />Batas aturan</span><span>Garis tipis menunjukkan nol</span></div></div>}
    <div className="warning-interpretation"><Fingerprint size={20} /><p>{state.description}{evaluable && signal.operator === 'change_gt' && signal.value! < 0 ? ' Nilai negatif berarti indikator turun. Aturan kenaikan hanya terpenuhi jika perubahan positif lebih besar dari batasnya.' : ''}</p></div>
    <p className="warning-period">{signal.scope}{signal.segment ? ` · ${signal.segment}` : ''} · observasi {shortDate(signal.period)}</p>
    <details className="warning-method"><summary>Telusuri cara pemeriksaan dan sumber</summary><p>{signal.methodology || 'Metodologi aturan belum dijelaskan. Periksa dengan pengelola sebelum menafsirkan hasil.'}</p><p>{signal.operator === 'change_gt' ? 'Untuk rasio bersatuan persen, perubahan dihitung sebagai nilai terbaru dikurangi nilai sebelumnya, dalam poin persentase. Untuk nilai moneter, perubahan dibaca dalam persen pertumbuhan.' : `Nilai indikator terbaru dibandingkan langsung dengan batas menggunakan syarat ${comparison}.`}</p><p>Aturan v{signal.version} · model {signal.model_version} · input {signal.dataset_version}. Waktu pemeriksaan {signal.evaluated_at}.</p><a href={signal.source_url} target="_blank" rel="noopener noreferrer">Buka publikasi sumber <ExternalLink size={14} /></a></details>
  </article>;
}

export function RiskEvidence({ series }: { series: Series[] }) {
  const [lens, setLens] = useState('resilience');
  const lenses = [{ id: 'resilience', label: 'Modal & kualitas kredit', codes: ['car', 'npl_gross', 'roa', 'nim'] }, { id: 'funding', label: 'Simpanan & penyaluran', codes: ['deposits', 'credit', 'ldr', 'assets'] }, { id: 'all', label: 'Semua indikator', codes: series.map(item => item.code) }];
  const selected = lenses.find(item => item.id === lens)!;
  const items = series.filter(item => selected.codes.includes(item.code));
  return <section className="risk-desk" aria-labelledby="risk-desk-heading">
    <div className="risk-desk-intro"><p className="story-eyebrow">MULAI DARI SATU SISI CERITA</p><h2 id="risk-desk-heading">Kondisi bank punya banyak sisi.</h2><p>Modal memberi bantalan saat ada kerugian. Kualitas kredit memberi petunjuk tentang pinjaman yang bermasalah. Simpanan dan penyaluran dana menjelaskan sisi pendanaan. Pilih sudut pandang, lalu buka bukti di tiap indikator.</p></div>
    <div className="risk-lenses" role="group" aria-label="Sudut pandang pemantauan">{lenses.map(item => <button key={item.id} aria-pressed={lens === item.id} onClick={() => setLens(item.id)}>{item.label}</button>)}</div>
    <div className="risk-observation-grid">{items.map(item => <RiskObservation key={seriesKey(item)} series={item} />)}</div>
    <div className="risk-units"><CircleHelp size={23} /><div><h3>Sama-sama berubah, cara bacanya berbeda.</h3><p>Rasio dari 20% ke 21% naik <strong>1 poin persentase (pp)</strong>. Kredit dari Rp100 ke Rp110 tumbuh <strong>10%</strong>. Arah naik atau turun belum cukup untuk menyebut kondisi baik atau buruk. Lihat definisi, periode, dan hubungan antarindikator.</p></div></div>
    <details className="methodology-box risk-method-details"><summary>Lihat seluruh angka dan metode perbandingan</summary><div className="table-scroll" role="region" tabIndex={0} aria-label="Bukti pemantauan risiko"><table><thead><tr><th>Indikator / periode</th><th>Observasi terakhir</th><th>Perubahan</th><th>Z-score historis</th><th>Sumber</th></tr></thead><tbody>{series.map(item => <tr key={seriesKey(item)}><td><strong>{item.label}</strong><small className="table-description">{item.scope} · {item.period}</small></td><td>{format(item.latest)} {item.unit}</td><td>{item.change === null ? 'Belum sebanding' : `${item.change > 0 ? '+' : ''}${format(item.change)} ${item.change_unit}`}</td><td>{item.statistics?.z_score == null ? 'Belum dapat dihitung' : format(item.statistics.z_score)}</td><td><a href={item.source_url} target="_blank" rel="noopener noreferrer">{item.dataset_version}</a></td></tr>)}</tbody></table></div><p>Z-score membandingkan nilai terbaru dengan rerata historis dan simpangan bakunya. Diperlukan 12 sampai 24 observasi historis yang berurutan dengan definisi sama. Titik terbaru tidak masuk hitungan riwayat. Jika riwayat atau variasinya tidak mencukupi, skor tidak ditampilkan. Statistik ini bersifat deskriptif.</p></details>
  </section>;
}

function RiskObservation({ series }: { series: Series }) {
  const story = indicatorStories[series.code];
  const previous = series.points.at(-2);
  const change = series.change;
  const direction = change === null ? null : change > 0 ? 'Naik' : change < 0 ? 'Turun' : 'Tetap';
  const Icon = change !== null && change < 0 ? ArrowDownRight : ArrowUpRight;
  return <article className={`risk-observation tone-${story?.tone || 'lavender'}`}>
    <div className="risk-observation-top"><span>{series.code.toUpperCase()}</span><small>{series.scope} · {shortDate(series.period)}</small></div><h3>{story?.title || series.label}</h3><strong className="risk-current-value">{format(series.latest)}<small>{series.unit}</small></strong><p>{story?.description || series.label}</p>
    <div className={`risk-delta ${direction ? '' : 'risk-delta-missing'}`}>{direction ? <><Icon size={20} /><strong>{direction} {format(Math.abs(change!))} {series.change_unit}</strong><span>dari observasi sebelumnya</span></> : <><CircleHelp size={18} /><strong>Perubahan belum dapat dibaca</strong><span>Butuh dua observasi berurutan dan sebanding.</span></>}</div>
    <details><summary>Baca bukti & artinya <ArrowRight size={14} /></summary><p>{story?.everyday || 'Baca indikator ini bersama indikator lain yang sebanding.'}</p>{previous && change !== null && <div className="risk-before-after"><span>{shortDate(previous.period)}<strong>{format(previous.value)} {series.unit}</strong></span><ArrowRight size={16} /><span>{shortDate(series.period)}<strong>{format(series.latest)} {series.unit}</strong></span></div>}<p>{series.methodology_note || 'Periksa definisi dan cakupan pada sumber.'}</p><small>Rilis {series.release_date} · versi {series.dataset_version}</small><a href={series.source_url} target="_blank" rel="noopener noreferrer">Buka sumber <ExternalLink size={13} /></a></details>
  </article>;
}
