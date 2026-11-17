import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { ArrowLeftRight, ArrowRight, Check, CircleHelp, Copy, ExternalLink, MapPin, PauseCircle } from 'lucide-react';
import { Link, useSearchParams } from 'react-router-dom';
import { api, errorMessage } from '../lib/api';
import { provinceSurvey2022 } from '../lib/province-survey';
import { Button } from './ui/button';
import { ErrorState, LoadingState } from './shared';
import './province-comparison.css';

type Region = { code: string; name: string; snlik_code?: string | null };
type Period = { id: string; label: string };
type Geography = { id: string; label: string; code_scheme: string; code_scheme_label?: string; boundary_note: string; map_available?: boolean };
type Provenance = { publication_url?: string; release_date?: string | null; file_last_modified?: string | null; last_modified?: string | null; retrieved_at?: string | null; sha256?: string };
type Source = Provenance & { label: string; url: string };
type Indicator = { code: string; label: string; unit: string | null; available: boolean; reason?: string | null; definition?: string | null; location_basis?: string; comparison_basis?: string; format?: 'percent' | 'currency'; periods?: Period[]; default_period?: string; geography?: Geography; regions?: Region[]; source_unit?: string; display_unit?: string; display_scale?: number };
type Rule = { id: string; label: string; description: string };
export type ComparisonCatalog = { geography: Geography; regions: Region[]; periods: Period[]; indicators: Indicator[]; rules: Rule[]; sources: Source[] };
type Value = { region: Region; value: number | null; status: 'available' | 'missing' | 'not_connected' };
export type ProvinceComparison = {
  geography: Geography; indicator: Indicator; period: Period; a: Value; b: Value;
  comparison: { status: 'comparable' | 'unavailable'; difference: number | null; difference_unit: string | null; reasons: string[]; checks: { id: string; label: string; passed: boolean | null }[] };
  trend: { available: boolean; reason: string }; sources: Source[]; provenance?: Provenance; manifest?: Provenance;
};
const number = (value: number, precision = 2) => new Intl.NumberFormat('id-ID', { minimumFractionDigits: precision, maximumFractionDigits: precision }).format(value);
const isCurrency = (indicator?: Indicator) => indicator?.format === 'currency' || indicator?.code === 'credit' || indicator?.code === 'deposits';
const displayScale = (indicator: Indicator) => indicator.display_scale && indicator.display_scale > 0 ? indicator.display_scale : 1000;
const currencyNumber = (value: number, indicator: Indicator) => value !== 0 && Math.abs(value / displayScale(indicator)) < .005 ? '<0,01' : number(value / displayScale(indicator));
const currencyText = (value: number, indicator: Indicator) => `${Math.abs(value) > 0 && Math.abs(value / displayScale(indicator)) < .005 ? 'kurang dari Rp 0,01' : `Rp ${number(value / displayScale(indicator))}`} ${(indicator.display_unit || 'Rp triliun').replace(/^Rp\s*/i, '')}`;
function niceMaximum(value: number) {
  if (value <= 0) return 1;
  const magnitude = 10 ** Math.floor(Math.log10(value));
  const fraction = value / magnitude;
  return (fraction <= 1 ? 1 : fraction <= 2 ? 2 : fraction <= 5 ? 5 : 10) * magnitude;
}
function dateLabel(value?: string | null) {
  if (!value) return 'Belum dicantumkan';
  const date = new Date(value);
  return Number.isNaN(date.getTime()) ? 'Belum dicantumkan' : new Intl.DateTimeFormat('id-ID', { day: 'numeric', month: 'long', year: 'numeric', timeZone: 'UTC' }).format(date);
}
function regionsFor(indicator: Indicator | undefined, catalog?: ComparisonCatalog) { return indicator?.regions || catalog?.regions || []; }
function geographyFor(indicator: Indicator | undefined, catalog?: ComparisonCatalog) { return indicator?.geography || catalog?.geography; }
function periodsFor(indicator: Indicator | undefined, catalog?: ComparisonCatalog) { return indicator?.periods || catalog?.periods || []; }
function defaultPeriod(indicator: Indicator | undefined, catalog?: ComparisonCatalog) { return indicator?.default_period || periodsFor(indicator, catalog)[0]?.id || ''; }

export function ProvinceComparisonPage() {
  const [params, setParams] = useSearchParams();
  const [copyMessage, setCopyMessage] = useState('');
  const [selectionNotice, setSelectionNotice] = useState('');
  const catalog = useQuery({ queryKey: ['province-comparison', 'catalog'], queryFn: async () => (await api.get<ComparisonCatalog>('/province-comparison/catalog')).data, staleTime: 60_000 });
  const indicatorCode = params.get('indicator') || 'literacy';
  const selectedIndicator = catalog.data?.indicators.find(item => item.code === indicatorCode);
  const regions = regionsFor(selectedIndicator, catalog.data);
  const geography = geographyFor(selectedIndicator, catalog.data);
  const periods = periodsFor(selectedIndicator, catalog.data);
  const biCodes = regions.some(region => region.code === 'row-13');
  const selected = { a: params.has('a') ? params.get('a')! : biCodes ? 'row-13' : '33', b: params.has('b') ? params.get('b')! : biCodes ? 'row-12' : '32', period: params.has('period') ? params.get('period')! : defaultPeriod(selectedIndicator, catalog.data), indicator: indicatorCode };
  const regionA = regions.find(item => item.code === selected.a);
  const regionB = regions.find(item => item.code === selected.b);
  const emptySelection = !selected.a || !selected.b;
  const sameRegion = !emptySelection && !!regionA && selected.a === selected.b;
  const unknownSelection = !!catalog.data && (!regionA || !regionB || !selectedIndicator || !/^(19|20|21)\d{2}(?:-(0[1-9]|1[0-2]))?$/.test(selected.period));
  const validSelection = !!selectedIndicator?.available && !unknownSelection && !sameRegion;
  const result = useQuery({ queryKey: ['province-comparison', 'result', selected.a, selected.b, selected.period, selected.indicator], queryFn: async () => (await api.get<ProvinceComparison>('/province-comparison', { params: selected })).data, enabled: validSelection });
  const periodKnown = periods.some(item => item.id === selected.period);
  const monetary = isCurrency(selectedIndicator);
  const sources = result.data?.sources || catalog.data?.sources || [];

  function change(key: 'a' | 'b' | 'period', value: string) {
    setCopyMessage(''); setSelectionNotice('');
    setParams(previous => { const next = new URLSearchParams(previous); next.set(key, value); return next; });
  }
  function changeIndicator(code: string) {
    const nextIndicator = catalog.data?.indicators.find(item => item.code === code);
    if (!nextIndicator) return;
    const nextRegions = regionsFor(nextIndicator, catalog.data);
    const nextGeography = geographyFor(nextIndicator, catalog.data);
    const changesGeography = geography?.id !== nextGeography?.id;
    const unresolved: string[] = [];
    function carry(region: Region | undefined) {
      if (!region) return '';
      if (!changesGeography) return nextRegions.some(item => item.code === region.code) ? region.code : '';
      const surveyCode = geography?.code_scheme === 'bnpb-kdppum' ? region.code : region.snlik_code;
      const mapped = surveyCode ? nextRegions.find(item => nextGeography?.code_scheme === 'bnpb-kdppum' ? item.code === surveyCode : item.snlik_code === surveyCode) : undefined;
      if (!mapped) unresolved.push(region.name);
      return mapped?.code || '';
    }
    const nextA = carry(regionA); const nextB = carry(regionB);
    const changesPeriod = changesGeography || isCurrency(nextIndicator) !== monetary;
    const nextPeriod = changesPeriod ? defaultPeriod(nextIndicator, catalog.data) : selected.period;
    setCopyMessage('');
    setSelectionNotice(unresolved.length ? `${unresolved.join(' dan ')} perlu dipilih ulang. Cakupan wilayah kedua sumber belum dapat dipadankan; pilihan sebelumnya tidak diganti dengan angka wilayah lain.` : changesPeriod ? 'Periode mengikuti pilihan sumber yang baru. Periksa kembali bulan atau tahun di bawah.' : '');
    setParams(previous => { const next = new URLSearchParams(previous); next.set('indicator', code); next.set('a', nextA); next.set('b', nextB); next.set('period', nextPeriod); return next; });
  }
  function swap() {
    setCopyMessage(''); setSelectionNotice('');
    setParams(previous => { const next = new URLSearchParams(previous); next.set('a', selected.b); next.set('b', selected.a); return next; });
  }
  async function copy() {
    const query = new URLSearchParams(selected);
    try { await navigator.clipboard.writeText(`${window.location.origin}/bandingkan-provinsi?${query}`); setCopyMessage('Tautan pilihan ini disalin.'); }
    catch { setCopyMessage('Salin alamat halaman dari bilah alamat untuk membagikan pilihan ini.'); }
  }

  return <div className="public-container page-content province-comparison-page">
    <header className="comparison-hero"><div><p className="eyebrow">MEMBACA DAERAH BERDAMPINGAN</p><h1>Dua provinsi.<br /><em>Satu cara membaca.</em></h1><p>Pilih angka yang ingin kamu pahami: kredit, simpanan, literasi, atau inklusi keuangan. Baca dua wilayah pada periode dan definisi yang sama, lalu telusuri ceritanya.</p></div><aside><span><MapPin size={23} strokeWidth={1.5} /></span><strong>Satu potret, dua daerah.</strong><p>Besarnya dana dan pengalaman menggunakan layanan menjawab pertanyaan berbeda. Selisih mengajak kita bertanya, belum menjelaskan penyebabnya.</p><Link to="/sumber-data">Kenali sumber dan cara membacanya <ArrowRight size={15} /></Link></aside></header>
    <ol className="comparison-how"><li><span>01</span>Pilih ukuran</li><li><span>02</span>Pilih dua provinsi</li><li><span>03</span>Baca selisih dan konteks</li></ol>
    {catalog.isPending ? <LoadingState label="Menyiapkan pilihan provinsi dan sumber…" /> : catalog.isError ? <ErrorState retry={() => void catalog.refetch()} /> : <>
      <section className="comparison-picker" aria-labelledby="comparison-picker-title"><header><div><p className="eyebrow">01 / PILIHANMU</p><h2 id="comparison-picker-title">Daerah mana yang ingin kamu pahami?</h2></div><Button type="button" variant="outline" size="sm" onClick={copy}><Copy size={15} />Salin tautan</Button></header>
        <div className="comparison-measure-fields comparison-measure-first"><label htmlFor="comparison-indicator">Angka yang dibandingkan<select id="comparison-indicator" value={selected.indicator} onChange={event => changeIndicator(event.target.value)}>{!selectedIndicator && <option value={selected.indicator}>Indikator belum tersedia</option>}{catalog.data.indicators.map(item => <option key={item.code} value={item.code} disabled={!item.available}>{item.label}{!item.available ? ' (belum tersedia)' : ''}</option>)}</select></label><label htmlFor="comparison-period">Periode yang sama<select id="comparison-period" value={selected.period} onChange={event => change('period', event.target.value)} disabled={!selectedIndicator?.available}>{!periodKnown && <option value={selected.period}>{selected.period || 'Periode'} (belum tersedia)</option>}{periods.map(period => <option key={period.id} value={period.id}>{period.label}</option>)}</select></label></div>
        <div className="comparison-region-fields"><label htmlFor="comparison-a"><span className="comparison-letter letter-a" aria-hidden="true">A</span>Provinsi pertama<select id="comparison-a" value={selected.a} onChange={event => change('a', event.target.value)}>{!regionA && <option value={selected.a}>{selected.a ? `Kode ${selected.a} belum tersedia` : 'Pilih kembali provinsi'}</option>}{regions.map(region => <option key={region.code} value={region.code}>{region.name}</option>)}</select></label><Button className="comparison-swap" type="button" variant="ghost" size="icon" aria-label="Tukar kedua provinsi" disabled={emptySelection} onClick={swap}><ArrowLeftRight size={20} /></Button><label htmlFor="comparison-b"><span className="comparison-letter letter-b" aria-hidden="true">B</span>Provinsi kedua<select id="comparison-b" value={selected.b} onChange={event => change('b', event.target.value)}>{!regionB && <option value={selected.b}>{selected.b ? `Kode ${selected.b} belum tersedia` : 'Pilih kembali provinsi'}</option>}{regions.map(region => <option key={region.code} value={region.code}>{region.name}</option>)}</select></label></div>
        <div className="comparison-availability">{selectedIndicator?.available ? <Check size={19} /> : <PauseCircle size={19} />}<p><strong>{!selectedIndicator?.available ? 'Pilihan ini belum siap dibandingkan.' : monetary ? 'Posisi dana, bukan jumlah orang.' : 'Dua indeks, dua pertanyaan.'}</strong> {!selectedIndicator?.available ? 'Hanya versi sumber yang telah diverifikasi dan diterbitkan yang dapat dipakai.' : monetary ? 'Angka kredit dan simpanan dicatat dengan dasar lokasi berbeda. Bandingkan indikator yang sama antarprovinsi; jangan membagi keduanya untuk menyimpulkan rasio penyaluran dana suatu daerah.' : 'Literasi dan inklusi memakai arsip survei SNLIK 2022. Pilihan kredit atau simpanan memakai periode dan sumber BI tersendiri.'}</p></div>
        {selectionNotice && <p className="comparison-copy-message" role="status">{selectionNotice}</p>}{copyMessage && <p className="comparison-copy-message" role="status">{copyMessage}</p>}
      </section>
      {selectedIndicator && !selectedIndicator.available ? <UnavailableComparison reasons={[selectedIndicator.reason || 'Versi sumber untuk indikator ini belum diterbitkan.']} /> : emptySelection ? <div className="comparison-selection-notice" role="status"><CircleHelp size={22} /><div><h2>Wilayah perlu dipilih ulang.</h2><p>Pilih dua provinsi dari cakupan sumber yang sekarang. Nama yang sama belum tentu menggambarkan batas wilayah yang sama.</p></div></div> : sameRegion ? <div className="comparison-selection-notice" role="status"><CircleHelp size={22} /><div><h2>Pilih dua provinsi yang berbeda.</h2><p>Kedua pilihan saat ini menunjuk wilayah yang sama. Ubah salah satunya untuk membaca perbedaannya.</p></div></div> : unknownSelection ? <div className="comparison-selection-notice" role="status"><CircleHelp size={22} /><div><h2>Pilihan pada tautan belum tersedia.</h2><p>Pilih provinsi dan indikator dari daftar. Alamat yang belum dikenali tidak diganti diam-diam dengan angka wilayah lain.</p><Button type="button" variant="outline" size="sm" onClick={() => setParams({ a: '33', b: '32', period: '2022', indicator: 'literacy' })}>Kembali ke pilihan tersedia</Button></div></div> : result.isPending ? <LoadingState label="Mencocokkan periode dan membaca angka…" /> : result.isError ? <div className="comparison-request-error"><p role="alert">{errorMessage(result.error, 'Perbandingan belum dapat dimuat. Coba kembali sebentar lagi.')}</p><Button type="button" variant="outline" onClick={() => void result.refetch()}>Coba lagi</Button></div> : result.data && <ComparisonResult result={result.data} />}
      <section className="comparison-method" aria-labelledby="comparison-method-title"><div><p className="eyebrow">CARA KITA MEMBACA</p><h2 id="comparison-method-title">Angkanya harus menjawab pertanyaan yang sama.</h2><p>Dua nama provinsi saja belum cukup. Perbandingan perlu satu ukuran, satu periode, dan cakupan wilayah yang sesuai.</p></div><div><details><summary>Apa yang diperiksa sebelum angka dibandingkan?</summary><ul>{catalog.data.rules.map(rule => <li key={rule.id}><strong>{rule.label}</strong><p>{rule.description}</p></li>)}</ul>{selectedIndicator?.definition && <p><strong>Ukuran yang dipilih:</strong> {selectedIndicator.definition}</p>}{selectedIndicator?.location_basis && <p><strong>Dasar wilayah:</strong> {selectedIndicator.location_basis}</p>}</details><details><summary>Mengapa cakupan wilayah perlu diperiksa?</summary><p>{geography?.boundary_note}</p><p>{geography?.code_scheme_label || 'Kode wilayah mengikuti sumber yang dipilih'}. Kode dari sumber berbeda perlu dicocokkan terlebih dahulu. Nama yang mirip tidak otomatis berarti cakupannya sama.</p></details><details><summary>Bagaimana jika ingin membandingkan tren?</summary><p>{monetary ? 'Angka BI adalah posisi dana pada akhir bulan, bukan arus dana selama satu bulan. Halaman ini membandingkan dua provinsi pada bulan yang sama. Untuk tren, gunakan beberapa bulan dengan definisi, cakupan, dan dasar lokasi yang tetap sebanding.' : 'Tren memerlukan beberapa periode dengan definisi dan wilayah yang konsisten. Arsip SNLIK provinsi yang tersedia di sini baru satu survei, sehingga perbandingan ini menampilkan satu potret waktu.'}</p></details></div></section>
      <section className="comparison-sources" aria-labelledby="comparison-sources-title"><div><p className="eyebrow">SUMBER YANG BISA DITELUSURI</p><h2 id="comparison-sources-title">Buka publikasinya. Periksa konteksnya.</h2><p>Angka mengikuti periode yang tercantum, bukan data waktu nyata. Hasil ini bukan peringkat keamanan bank atau penilaian terhadap penduduk suatu daerah.</p></div><ul>{sources.map(source => <li key={source.url}><a href={source.url} target="_blank" rel="noopener noreferrer">{source.label}<ExternalLink size={15} /></a></li>)}</ul></section>
    </>}
  </div>;
}

function UnavailableComparison({ reasons }: { reasons: string[] }) {
  return <section className="comparison-unavailable" aria-labelledby="comparison-unavailable-title"><span><PauseCircle size={26} /></span><div><p className="eyebrow">ANGKA BELUM DAPAT DIBANDINGKAN</p><h2 id="comparison-unavailable-title">Kita belum punya pasangan data yang sesuai.</h2>{reasons.map(reason => <p key={reason}>{reason}</p>)}<p>Nilai yang belum tersedia tidak dianggap nol. Pilih indikator dan periode lain yang sudah tersedia.</p><Link to="/sumber-data">Lihat status sumber data <ArrowRight size={15} /></Link></div></section>;
}

function ComparisonResult({ result }: { result: ProvinceComparison }) {
  const comparable = result.comparison.status === 'comparable' && result.a.status === 'available' && result.b.status === 'available' && result.a.value !== null && result.b.value !== null && result.comparison.difference !== null;
  if (!comparable) return <UnavailableComparison reasons={result.comparison.reasons} />;
  const monetary = isCurrency(result.indicator);
  const difference = result.comparison.difference!;
  const absolute = Math.abs(difference);
  const direction = absolute < (monetary ? .00005 : .005) ? 'sama dengan' : difference > 0 ? monetary ? 'lebih besar daripada' : 'lebih tinggi daripada' : monetary ? 'lebih kecil daripada' : 'lebih rendah daripada';
  const measure = result.indicator.label.toLowerCase();
  const sourceUnit = result.indicator.source_unit || result.indicator.unit || 'Rp miliar';
  const raw = (value: number) => number(value, monetary ? 4 : 2);
  const mapAvailable = !monetary && result.geography.map_available !== false;
  return <section className={`comparison-result ${monetary ? 'comparison-currency-result' : ''}`} aria-labelledby="comparison-result-title" aria-live="polite">
    <header><div><p className="eyebrow">02 / SATU POTRET YANG SAMA</p><h2 id="comparison-result-title">Baca keduanya berdampingan.</h2></div><span className="comparison-period-stamp"><Check size={15} />{result.period.label} · {monetary ? 'posisi akhir bulan' : result.indicator.unit}</span></header>
    <div className="comparison-value-grid">{[{ side: 'a', item: result.a }, { side: 'b', item: result.b }].map(({ side, item }) => <article className={`comparison-value value-${side}`} key={side}><span className={`comparison-letter letter-${side}`}>{side.toUpperCase()}</span><h3>{item.region.name}</h3><p>{result.indicator.label}</p><strong>{monetary && <span className="comparison-rupiah">Rp </span>}{monetary ? currencyNumber(item.value!, result.indicator) : number(item.value!)}<small>{monetary ? (result.indicator.display_unit || 'Rp triliun').replace(/^Rp\s*/i, '') : result.indicator.unit}</small></strong><span>{monetary ? 'Posisi dana pada akhir bulan. Tabel di bawah menyimpan angka dalam satuan sumber.' : 'Menurut definisi survei pada periode yang sama.'}</span></article>)}</div>
    <div className="comparison-reading"><span className="comparison-difference">{monetary && <span className="comparison-rupiah">Rp </span>}{monetary ? currencyNumber(absolute, result.indicator) : number(absolute)}<small>{monetary ? 'triliun selisih posisi' : 'poin persentase'}</small></span><div><h3>Selisih, bukan cerita lengkapnya.</h3><p>{monetary ? 'Posisi' : 'Indeks'} {measure} <strong>{result.a.region.name}</strong> {direction} <strong>{result.b.region.name}</strong>{absolute >= (monetary ? .00005 : .005) ? ` sebesar ${monetary ? currencyText(absolute, result.indicator) : `${number(absolute)} poin persentase`}` : ''}.</p><p>{monetary ? 'Ini selisih nominal dua daerah pada satu bulan, bukan persentase pertumbuhan, jumlah nasabah, atau ukuran kesejahteraan penduduk. Besarnya angka belum menjelaskan penyebab perbedaannya.' : 'Ini selisih dua estimasi survei. Angka ini belum menjelaskan penyebabnya atau menunjukkan jumlah orang yang mengalami perubahan.'}</p></div></div>
    <div className="comparison-visual-grid"><ComparisonBars result={result} /><section className="comparison-location" aria-labelledby="comparison-location-title"><h3 id="comparison-location-title">{mapAvailable ? 'Dua bagian dari Indonesia.' : 'Di mana angkanya dicatat?'}</h3>{mapAvailable ? <ComparisonLocation a={result.a.region} b={result.b.region} /> : <div className="comparison-location-explainer"><MapPin className="comparison-location-icon" size={29} /><strong>{result.indicator.code === 'credit' ? 'Kredit mengikuti lokasi proyek.' : 'Simpanan mengikuti kantor penghimpun dana.'}</strong><p>{result.indicator.location_basis || (result.indicator.code === 'credit' ? 'Wilayah tempat penggunaan atau proyek yang dibiayai kredit. Lokasinya bisa berbeda dari kantor bank yang memberikan pinjaman.' : 'Lokasi kegiatan operasional kantor bank yang menghimpun dana. Ini bukan domisili setiap pemilik simpanan.')}</p><span>{result.geography.label}</span></div>}<p>{result.geography.boundary_note}</p></section></div>
    <details className="comparison-number-detail"><summary>Lihat tabel dan cara menghitung selisih</summary><div className="comparison-number-scroll" role="region" tabIndex={0} aria-label="Angka perbandingan dua provinsi"><table><caption>{result.indicator.label}, {result.period.label}</caption><thead><tr><th>Wilayah / hitungan</th><th>Nilai dalam satuan sumber</th></tr></thead><tbody><tr><th scope="row">A · {result.a.region.name}</th><td>{raw(result.a.value!)} {sourceUnit}</td></tr><tr><th scope="row">B · {result.b.region.name}</th><td>{raw(result.b.value!)} {sourceUnit}</td></tr><tr><th scope="row">A − B</th><td>{difference > 0 ? '+' : ''}{raw(difference)} {result.comparison.difference_unit}</td></tr></tbody></table></div><p>Selisih = {raw(result.a.value!)}{monetary ? ` ${sourceUnit}` : '%'} − {raw(result.b.value!)}{monetary ? ` ${sourceUnit}` : '%'} = {raw(difference)} {monetary ? sourceUnit : 'poin persentase'}. {monetary ? 'Perhitungan memakai angka sumber sebelum pembulatan tampilan.' : 'Poin persentase adalah selisih dua rasio dalam persen, bukan persentase pertumbuhan.'}</p>{monetary && <p>1 Rp triliun = 1.000 Rp miliar. Angka ringkas dibagi {number(displayScale(result.indicator), 0)} dan dibulatkan menjadi dua desimal. Tabel mempertahankan empat desimal dalam {sourceUnit}; nilai kecil yang belum mencapai Rp 0,01 triliun ditandai &lt;0,01.</p>}<p>{result.trend.reason}</p></details>
    {monetary && <ComparisonProvenance result={result} />}
    <div className="comparison-verified"><Check size={18} /><p>{result.comparison.checks.filter(check => check.passed === true).map(check => check.label).join(' · ')}</p></div>
  </section>;
}

function ComparisonBars({ result }: { result: ProvinceComparison }) {
  const monetary = isCurrency(result.indicator);
  const scale = monetary ? displayScale(result.indicator) : 1;
  const maximum = monetary ? niceMaximum(Math.max(result.a.value!, result.b.value!) / scale) : 100;
  const axisPrecision = monetary ? Math.min(8, Math.max(2, -Math.floor(Math.log10(maximum)) + 1)) : 0;
  const axisLabel = (value: number) => monetary ? `${number(value, axisPrecision)}` : `${value}%`;
  const amount = (value: number) => monetary ? currencyText(value, result.indicator) : `${number(value)}%`;
  return <section className="comparison-bars" aria-labelledby="comparison-bars-title"><h3 id="comparison-bars-title">{monetary ? 'Satu skala rupiah, mulai dari nol.' : 'Satu skala, dari 0 sampai 100.'}</h3><p>{monetary ? result.indicator.display_unit || 'Rp triliun' : `${result.indicator.label} (%)`}</p><div className="comparison-bar-chart" role="img" aria-label={`${result.a.region.name} ${amount(result.a.value!)} dan ${result.b.region.name} ${amount(result.b.value!)}, ${monetary ? `skala nol sampai ${currencyText(maximum * scale, result.indicator)}` : 'skala 0 sampai 100 persen'}.`}>{[{ side: 'a', item: result.a }, { side: 'b', item: result.b }].map(({ side, item }) => <div className={`comparison-bar-row bar-${side}`} key={side}><div><span>{item.region.name}</span><strong>{amount(item.value!)}</strong></div><div className="comparison-bar-track"><i style={{ width: `${Math.min(100, Math.max(0, item.value! / scale / maximum * 100))}%` }} /></div></div>)}<div className="comparison-bar-scale"><span>{axisLabel(0)}</span><span>{axisLabel(maximum / 2)}</span><span>{axisLabel(maximum)}</span></div></div></section>;
}

function ComparisonProvenance({ result }: { result: ProvinceComparison }) {
  const evidence = { ...result.sources.find(source => source.release_date || source.sha256 || source.file_last_modified || source.last_modified || source.retrieved_at), ...result.manifest, ...result.provenance };
  return <section className="comparison-provenance" aria-labelledby="comparison-provenance-title"><p className="eyebrow">EMPAT WAKTU, SATU JEJAK DATA</p><h3 id="comparison-provenance-title">Bulan observasi berbeda dari tanggal publikasi.</h3><p>Periode angka, tanggal publikasi, pembaruan berkas, dan waktu pengambilan memiliki arti berbeda. Ikuti tanggal yang dicatat sumber berikut.</p><dl><div><dt>Periode observasi</dt><dd>{result.period.label}</dd></div><div><dt>Terbit dalam publikasi</dt><dd>{dateLabel(evidence.release_date)}</dd></div><div><dt>Berkas diperbarui</dt><dd>{dateLabel(evidence.file_last_modified || evidence.last_modified)}</dd></div><div><dt>Diambil FINWATCH</dt><dd>{dateLabel(evidence.retrieved_at)}</dd></div></dl>{evidence.publication_url && <a href={evidence.publication_url} target="_blank" rel="noopener noreferrer">Buka edisi publikasinya <ExternalLink size={14} /></a>}{evidence.sha256 && <details><summary>Periksa sidik berkas sumber</summary><p>SHA-256 membantu memeriksa apakah berkas yang dibaca sama dengan berkas sumber yang disimpan FINWATCH.</p><code>{evidence.sha256}</code></details>}</section>;
}

function ComparisonLocation({ a, b }: { a: Region; b: Region }) {
  return <><svg viewBox="0 0 950 355" role="img" aria-label={`Lokasi ${a.name} dan ${b.name} pada cakupan wilayah survei 2022`}><title>{a.name} dan {b.name}</title>{provinceSurvey2022.map(region => <path d={region.path} key={region.code} className={region.code === a.code ? 'location-a' : region.code === b.code ? 'location-b' : ''} />)}</svg><div className="comparison-location-legend"><span><i className="legend-a" />A · {a.name}</span><span><i className="legend-b" />B · {b.name}</span></div></>;
}
