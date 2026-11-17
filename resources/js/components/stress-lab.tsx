import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { ArrowDownRight, ArrowRight, BookOpen, Check, CircleHelp, Info, SlidersHorizontal } from 'lucide-react';
import { api, csrf, errorMessage } from '../lib/api';
import type { Series } from './analytics';
import { PageHeading } from './public';
import { Button } from './ui/button';

type StressParameters = { credit: string; npl: string; car: string; npl_shock_pp: string; lgd_percent: string };
type StressScenario = { id: number; name: string; parameters: Record<keyof StressParameters, number>; methodology: string };
type StressResult = { baseline: { credit: number; npl: number; car: number }; parameters: Record<keyof StressParameters, number>; car_after: number; npl_after: number; additional_loss: number; capital_before: number; capital_after: number; formula: string; assumptions: string; model_version: string };
const format = (value: number, digits = 2) => new Intl.NumberFormat('id-ID', { maximumFractionDigits: digits }).format(value);
const initialParameters: StressParameters = { credit: '', npl: '', car: '', npl_shock_pp: '1', lgd_percent: '45' };
export const stressPresets = [
  { id: 'calm', title: 'Tanpa guncangan', subtitle: 'Mulai dari titik awal', shock: 0, lgd: 45, tone: 'mint' },
  { id: 'light', title: 'Tekanan ringan', subtitle: 'Coba perubahan kecil', shock: 1, lgd: 45, tone: 'butter' },
  { id: 'moderate', title: 'Tekanan moderat', subtitle: 'Naikkan tekanannya', shock: 3, lgd: 50, tone: 'lavender' },
  { id: 'heavy', title: 'Tekanan berat', subtitle: 'Uji asumsi yang lebih besar', shock: 7, lgd: 65, tone: 'peach' },
] as const;

export function StressPage() {
  const analytics = useQuery({ queryKey: ['analytics', 'banking', ''], queryFn: async () => (await api.get<{ data: Series[] }>('/analytics', { params: { sector: 'banking', year: undefined } })).data });
  const scenarios = useQuery({ queryKey: ['stress-scenarios'], queryFn: async () => (await api.get<{ data: StressScenario[] }>('/stress-scenarios')).data.data });
  const [values, setValues] = useState<StressParameters>(initialParameters);
  const [presetId, setPresetId] = useState('light');
  const [scenarioId, setScenarioId] = useState('');
  const [baselineLabel, setBaselineLabel] = useState('Belum ada titik awal. Gunakan data tersedia atau contoh belajar.');
  const [resultBaseline, setResultBaseline] = useState('');
  const [error, setError] = useState('');
  const [result, setResult] = useState<StressResult | null>(null);
  const [previousResult, setPreviousResult] = useState<StressResult | null>(null);
  const [busy, setBusy] = useState(false);
  const scenario = scenarios.data?.find(item => String(item.id) === scenarioId);
  const changed = result !== null && (Object.keys(values) as (keyof StressParameters)[]).some(key => Number(values[key]) !== Number(result.parameters[key]));

  function changeValue(key: keyof StressParameters, value: string) {
    setValues(current => ({ ...current, [key]: value }));
    setScenarioId('');
    if (key === 'npl_shock_pp' || key === 'lgd_percent') setPresetId('custom');
    else setBaselineLabel('Titik awal disesuaikan sendiri. Semua angka diperlakukan sebagai asumsi Anda.');
  }
  function applyPreset(id: string) {
    const preset = stressPresets.find(item => item.id === id);
    if (!preset) return;
    setPresetId(id); setScenarioId('');
    setValues(current => ({ ...current, npl_shock_pp: String(preset.shock), lgd_percent: String(preset.lgd) }));
    setError('');
  }
  function applyBaseline() {
    const series = analytics.data?.data || [];
    const credit = series.find(item => item.code === 'credit' && item.scope === 'Indonesia');
    const matching = (code: string) => series.find(item => item.code === code && item.scope === credit?.scope && item.segment === credit?.segment && item.period === credit?.period && item.unit === '%');
    const npl = matching('npl_gross'); const car = matching('car');
    if (!credit || !npl || !car) { setError('Data kredit, NPL, dan CAR dengan periode, lingkup, dan segmen yang sama belum lengkap. Gunakan contoh belajar atau isi asumsi sendiri.'); return; }
    setValues(current => ({ ...current, credit: String(credit.latest), npl: String(npl.latest), car: String(car.latest) }));
    setScenarioId('');
    setBaselineLabel(`Titik awal dari publikasi agregat ${credit.scope}, observasi ${credit.period}; satuan kredit ${credit.unit}. Perubahan berikutnya adalah asumsi simulasi.`);
    setError('');
  }
  function applyExample() {
    setValues(current => ({ ...current, credit: '1000', npl: '2', car: '20' }));
    setScenarioId(''); setError('');
    setBaselineLabel('Contoh belajar: kredit Rp1.000, NPL 2%, CAR 20%. Angka buatan untuk menjelaskan hitungan, bukan data bank.');
  }
  function applyScenario(id: string) {
    setScenarioId(id);
    const selected = scenarios.data?.find(item => String(item.id) === id);
    if (!selected) return;
    setValues(Object.fromEntries(Object.entries(selected.parameters).map(([key, value]) => [key, String(value)])) as StressParameters);
    setPresetId('custom');
    setBaselineLabel(`Asumsi dari skenario “${selected.name}”. Parameter hipotetis dapat diubah.`);
    setError('');
  }
  async function simulate(event: React.FormEvent) {
    event.preventDefault(); setError(''); setBusy(true);
    try {
      await csrf();
      const next = (await api.post<{ data: StressResult }>('/stress-tests', values)).data.data;
      setPreviousResult(result); setResult(next); setResultBaseline(baselineLabel);
    } catch (failure) { setError(errorMessage(failure)); }
    finally { setBusy(false); }
  }
  function reset() {
    setValues(initialParameters); setResult(null); setPreviousResult(null); setScenarioId(''); setPresetId('light');
    setBaselineLabel('Belum ada titik awal. Gunakan data tersedia atau contoh belajar.'); setError('');
  }

  return <div className="public-container page-content stress-lab-page">
    <PageHeading eyebrow="LABORATORIUM KECIL / BAGAIMANA JIKA?" title="Ubah tekanannya. Ikuti ceritanya." description="Kalau bagian kredit bermasalah bertambah, berapa kerugian tambahan dan bagaimana bantalan modal berubah? Coba beberapa keadaan untuk memahami hubungan ini, langkah demi langkah." />
    <ol className="lab-how-to"><li><span>01</span><div><strong>Pilih titik awal</strong><p>Gunakan publikasi historis yang tersedia atau contoh belajar Rp1.000.</p></div></li><li><span>02</span><div><strong>Tentukan tekanannya</strong><p>Pilih skenario atau sesuaikan kenaikan NPL dan bagian kerugian.</p></div></li><li><span>03</span><div><strong>Hitung, lalu bandingkan</strong><p>Ulangi dengan asumsi lain untuk melihat perubahan hasilnya.</p></div></li></ol>
    <section className="lab-presets" aria-labelledby="preset-title"><header><div><p className="story-eyebrow">EMPAT KEADAAN UNTUK DICOBA</p><h2 id="preset-title">Mulai dari tekanan seperti apa?</h2></div><p>Nama skenario menjelaskan besarnya asumsi. Ini bukan label “aman” atau “tidak aman” untuk bank.</p></header><div className="lab-preset-grid" role="group" aria-label="Pilihan tekanan simulasi">{stressPresets.map(preset => <button key={preset.id} className={`lab-preset tone-${preset.tone}`} aria-pressed={presetId === preset.id} onClick={() => applyPreset(preset.id)}><span className="lab-preset-check" aria-hidden="true">{presetId === preset.id ? <Check size={16} /> : <SlidersHorizontal size={15} />}</span><strong>{preset.title}</strong><small>{preset.subtitle}</small><span className="lab-preset-assumptions">NPL +{preset.shock} pp <span>·</span> LGD {preset.lgd}%</span></button>)}</div><p className="lab-preset-note">Pilihan ini mengubah dua asumsi tekanan. Nilai kredit, NPL awal, dan CAR awal tetap mengikuti titik awal Anda.</p></section>
    <div className="stress-layout lab-workbench"><form className="form-panel lab-inputs" onSubmit={simulate}>
      <header className="lab-panel-heading"><span>01 / MASUKAN</span><h2>Atur skenario Anda</h2></header>
      <div className="lab-baseline-actions"><Button variant="outline" type="button" disabled={analytics.isPending} onClick={applyBaseline}>Gunakan baseline data tersedia</Button><Button variant="outline" type="button" onClick={applyExample}>Pakai contoh Rp1.000</Button></div><p className="lab-baseline-note">{baselineLabel}</p>
      <fieldset className="lab-fieldset"><legend>Titik awal</legend><NumericControl name="credit" label="Total kredit / proksi ATMR" value={values.credit} onChange={changeValue} hint="Gunakan satuan uang yang konsisten. Dalam model ini kredit mewakili aset tertimbang menurut risiko (ATMR)." /><div className="lab-input-pair"><NumericControl name="npl" label="NPL awal (%)" value={values.npl} onChange={changeValue} hint="Bagian kredit yang sudah bermasalah." /><NumericControl name="car" label="CAR awal (%)" value={values.car} onChange={changeValue} hint="Rasio modal terhadap ATMR." /></div></fieldset>
      <fieldset className="lab-fieldset lab-pressure"><legend>Asumsi tekanan {presetId === 'custom' && <span>· disesuaikan sendiri</span>}</legend><NumericControl name="npl_shock_pp" label="Kenaikan NPL (pp)" value={values.npl_shock_pp} onChange={changeValue} hint="Poin persentase adalah selisih dua rasio. NPL 2% yang naik 1 pp menjadi 3%." slider /><NumericControl name="lgd_percent" label="Bagian kerugian / LGD (%)" value={values.lgd_percent} onChange={changeValue} hint="Loss Given Default: bagian kredit bermasalah tambahan yang dianggap hilang setelah pemulihan. LGD 45% berarti Rp45 dari Rp100 tambahan kredit bermasalah." slider /></fieldset>
      {scenarios.isError ? <p className="lab-preset-note">Skenario tersimpan belum dapat dimuat. <button type="button" className="text-link" onClick={() => void scenarios.refetch()}>Coba lagi</button></p> : !!scenarios.data?.length && <details className="lab-saved-scenarios"><summary>Skenario tambahan dari pengelola</summary><label>Skenario terpublikasi<select value={scenarioId} onChange={event => applyScenario(event.target.value)}><option value="">Pilih skenario tersimpan</option>{scenarios.data.map(item => <option key={item.id} value={item.id}>{item.name}</option>)}</select></label>{scenario && <p>{scenario.methodology}</p>}</details>}
      {error && <p role="alert" className="inline-error">{error}</p>}<div className="form-actions"><Button disabled={busy}>{busy ? 'Menghitung…' : 'Hitung sensitivitas'}<ArrowRight size={15} /></Button><Button type="button" variant="outline" onClick={reset}>Reset</Button></div>
    </form><section className="form-panel lab-results" aria-live="polite" aria-labelledby="lab-result-heading"><header className="lab-panel-heading"><span>02 / HASIL MODEL</span><h2 id="lab-result-heading">{result ? 'Ini yang berubah dalam model.' : 'Seberapa besar modal terpengaruh?'}</h2></header>{result ? <>
      {changed && <div className="lab-stale-result" role="status"><Info size={18} /><p>Input sudah berubah. Hasil di bawah masih memakai perhitungan terakhir. Klik “Hitung sensitivitas” untuk memperbaruinya.</p></div>}
      <p className="lab-baseline-note">{resultBaseline}</p><div className="lab-capital-change"><div><span>CAR proksi awal</span><strong>{format(result.baseline.car)}%</strong></div><ArrowDownRight size={30} /><div><span>CAR proksi akhir</span><strong>{format(result.car_after)}%</strong></div></div>
      <p className="lab-result-story">Dengan tambahan NPL <strong>{format(Number(result.parameters.npl_shock_pp))} pp</strong> dan LGD <strong>{format(Number(result.parameters.lgd_percent))}%</strong>, model menghitung kerugian tambahan <strong>{format(result.additional_loss)}</strong> dalam satuan kredit. Selisih rasio modal menjadi <strong>{format(result.car_after - result.baseline.car)} pp</strong>.</p>
      <dl className="result-list"><div><dt>NPL setelah guncangan</dt><dd>{format(result.npl_after)}%</dd></div><div><dt>Modal proksi awal</dt><dd>{format(result.capital_before)}</dd></div><div><dt>Kerugian tambahan</dt><dd>{format(result.additional_loss)}</dd></div><div><dt>Modal proksi akhir</dt><dd>{format(result.capital_after)}</dd></div></dl>
      {result.capital_after < 0 && <p className="lab-stale-result">Kerugian dalam asumsi ini melampaui modal proksi awal, sehingga hasil matematis menjadi negatif. Ini menggambarkan batas model sederhana, bukan hasil penilaian regulator.</p>}
      <details className="lab-calculation"><summary>Buka hitungan skenario ini</summary><ol><li>Modal awal = {format(result.baseline.credit)} × {format(result.baseline.car)} / 100 = <strong>{format(result.capital_before)}</strong>.</li><li>Tambahan kredit bermasalah = {format(result.baseline.credit)} × {format(Number(result.parameters.npl_shock_pp))} / 100 = <strong>{format(result.baseline.credit * Number(result.parameters.npl_shock_pp) / 100)}</strong>.</li><li>Kerugian = tambahan kredit bermasalah × {format(Number(result.parameters.lgd_percent))} / 100 = <strong>{format(result.additional_loss)}</strong>.</li><li>CAR proksi akhir = 100 × ({format(result.capital_before)} − {format(result.additional_loss)}) / {format(result.baseline.credit)} = <strong>{format(result.car_after)}%</strong>.</li></ol></details>
      <div className="lab-model-limit"><CircleHelp size={21} /><p>{result.assumptions}</p></div><small className="lab-model-version">Model {result.model_version} · hasil memakai satuan moneter yang sama dengan input kredit.</small>{previousResult && <StressComparison current={result} previous={previousResult} />}
    </> : <div className="lab-empty-result"><div className="lab-empty-illustration" aria-hidden="true"><span /><span /><span /><span /><i>?</i></div><h3>Satu perubahan, beberapa akibat.</h3><p>Kerugian tambahan mengurangi modal proksi. Dengan titik awal yang sama, guncangan NPL atau LGD yang lebih besar menghasilkan penurunan CAR proksi yang lebih besar.</p><p>Isi titik awal di sebelah, pilih tekanan, lalu hitung. Hasil berikutnya bisa dibandingkan dengan perhitungan sebelumnya.</p></div>}</section></div>
    <StressPrimer />
  </div>;
}

function NumericControl({ name, label, value, onChange, hint, slider = false }: { name: keyof StressParameters; label: string; value: string; onChange: (key: keyof StressParameters, value: string) => void; hint: string; slider?: boolean }) {
  const maximum = name === 'credit' ? '1000000000000000' : name === 'npl_shock_pp' ? '50' : '100';
  return <div className="stress-control"><label htmlFor={`stress-${name}`}>{label}</label><input id={`stress-${name}`} type="number" step="any" required min={name === 'credit' ? '0.0001' : '0'} max={maximum} value={value} onChange={event => onChange(name, event.target.value)} aria-describedby={`stress-${name}-hint`} />{slider && <><input className="stress-slider" aria-label={name === 'npl_shock_pp' ? 'Geser kenaikan kredit bermasalah' : 'Geser asumsi bagian kerugian'} type="range" min="0" max={maximum} step={name === 'npl_shock_pp' ? '.1' : '1'} value={value} onChange={event => onChange(name, event.target.value)} /><div className="stress-scale"><span>0{name === 'npl_shock_pp' ? ' pp' : '%'}</span><span>{maximum}{name === 'npl_shock_pp' ? ' pp' : '%'}</span></div></>}<small id={`stress-${name}-hint`}>{hint}</small></div>;
}

function StressPrimer() {
  return <section className="lab-primer" aria-labelledby="lab-primer-title"><div><span className="risk-icon"><BookOpen size={27} strokeWidth={1.4} /></span><p className="story-eyebrow">PAHAMI HITUNGANNYA</p><h2 id="lab-primer-title">Dari kredit ke bantalan modal.</h2><p>Kita mulai dengan contoh kecil agar alurnya terlihat. Anggap kredit Rp1.000, NPL awal 2%, dan CAR awal 20%. Lalu uji kenaikan NPL 3 pp dengan LGD 40%.</p></div><ol><li><span>1</span><div><h3>Hitung modal awal</h3><p>Rp1.000 × 20% = <strong>Rp200</strong>. Kredit digunakan sebagai pengganti ATMR untuk tujuan belajar.</p></div></li><li><span>2</span><div><h3>Hitung tambahan kredit bermasalah</h3><p>Rp1.000 × 3% = <strong>Rp30</strong>. NPL akhir menjadi 2% + 3 pp = <strong>5%</strong>. Model hanya menghitung kerugian dari tambahan guncangan ini.</p></div></li><li><span>3</span><div><h3>Ubah bagian itu menjadi kerugian</h3><p>Rp30 × 40% = <strong>Rp12</strong>. Dengan asumsi pemulihan tersebut, modal turun dari Rp200 menjadi <strong>Rp188</strong>.</p></div></li><li><span>4</span><div><h3>Hitung rasio modal sesudahnya</h3><p>Rp188 / Rp1.000 × 100 = <strong>18,8%</strong>. Rasio turun 1,2 pp. Mengubah satu asumsi saja membantu kita melihat pengaruhnya.</p></div></li></ol><div className="lab-primer-limit"><Info size={21} /><p><strong>Batas yang perlu diingat.</strong> Perhitungan ini tidak memasukkan laba, cadangan kerugian yang sudah ada, bobot risiko, penambahan modal, atau respons bank dan nasabah. NPL awal hanya dipakai untuk menghitung NPL akhir; kerugian awal tidak dihitung ulang. Karena kredit menjadi proksi ATMR tetap, hasil CAR di sini adalah rasio pembelajaran. Ia bukan proyeksi, rating keamanan, atau CAR resmi bank.</p></div></section>;
}

function StressComparison({ current, previous }: { current: StressResult; previous: StressResult }) {
  const rows: [string, number, number][] = [
    ['Kredit / proksi ATMR', Number(previous.parameters.credit), Number(current.parameters.credit)], ['NPL awal (%)', Number(previous.parameters.npl), Number(current.parameters.npl)], ['CAR awal (%)', Number(previous.parameters.car), Number(current.parameters.car)], ['Kenaikan NPL (pp)', Number(previous.parameters.npl_shock_pp), Number(current.parameters.npl_shock_pp)], ['LGD (%)', Number(previous.parameters.lgd_percent), Number(current.parameters.lgd_percent)], ['NPL akhir (%)', previous.npl_after, current.npl_after], ['Tambahan kerugian', previous.additional_loss, current.additional_loss], ['CAR proksi akhir (%)', previous.car_after, current.car_after],
  ];
  return <section className="stress-comparison"><h3>Bandingkan dua simulasi terakhir</h3><div className="table-scroll" role="region" tabIndex={0} aria-label="Perbandingan skenario hipotetis"><table><caption>Samakan satuan moneter sebelum membandingkan kerugian. Semua nilai merupakan asumsi atau hasil model hipotetis.</caption><thead><tr><th>Parameter / hasil</th><th>Sebelumnya</th><th>Sekarang</th></tr></thead><tbody>{rows.map(([label, before, after]) => <tr key={label}><th scope="row">{label}</th><td>{format(before)}</td><td>{format(after)}</td></tr>)}</tbody></table></div></section>;
}
