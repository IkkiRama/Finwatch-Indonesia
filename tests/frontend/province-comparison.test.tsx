import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { MemoryRouter, useLocation } from 'react-router-dom';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { api } from '../../resources/js/lib/api';
import { ProvinceComparisonPage, type ComparisonCatalog, type ProvinceComparison } from '../../resources/js/components/province-comparison';

const geography = { id: 'snlik-2022-34', label: 'Cakupan survei SNLIK 2022', code_scheme: 'bnpb-kdppum', boundary_note: 'Papua dan Papua Barat mengikuti cakupan survei 2022, sebelum pemekaran.' };
const regions = [{ code: '33', name: 'Jawa Tengah' }, { code: '32', name: 'Jawa Barat' }, { code: '31', name: 'DKI Jakarta' }];
const indicators = [
  { code: 'literacy', label: 'Literasi keuangan', unit: '%', available: true },
  { code: 'inclusion', label: 'Inklusi keuangan', unit: '%', available: true },
  { code: 'credit', label: 'Kredit', unit: null, available: false, reason: 'Data kredit provinsi belum terhubung.' },
  { code: 'deposits', label: 'Simpanan', unit: null, available: false, reason: 'Data simpanan provinsi belum terhubung.' },
];
const catalog: ComparisonCatalog = { geography, regions, indicators, periods: [{ id: '2022', label: 'SNLIK 2022' }], rules: [{ id: 'period', label: 'Periode sama', description: 'Kedua nilai perlu berasal dari periode survei yang sama.' }], sources: [{ label: 'Booklet OJK SNLIK 2022', url: 'https://www.ojk.go.id/snlik-2022' }] };
const values: Record<string, Record<string, number>> = { literacy: { '33': 51.69, '32': 56.1, '31': 52.99 }, inclusion: { '33': 85.97, '32': 88.31, '31': 96.62 } };

function response(params: Record<string, string>): ProvinceComparison {
  const indicator = indicators.find(item => item.code === params.indicator)!;
  const missing = params.period !== '2022';
  const available = indicator.available && !missing;
  const a = { region: regions.find(item => item.code === params.a)!, value: available ? values[indicator.code][params.a] : null, status: available ? 'available' as const : missing ? 'missing' as const : 'not_connected' as const };
  const b = { region: regions.find(item => item.code === params.b)!, value: available ? values[indicator.code][params.b] : null, status: a.status };
  return { geography, indicator, period: { id: params.period, label: `SNLIK ${params.period}` }, a, b, comparison: { status: available ? 'comparable' : 'unavailable', difference: available ? Math.round((a.value! - b.value!) * 100) / 100 : null, difference_unit: available ? 'pp' : null, reasons: available ? [] : [missing ? 'Data provinsi untuk periode ini belum tersedia.' : indicator.reason!], checks: [{ id: 'period', label: 'Periode sama', passed: available }] }, trend: { available: false, reason: 'Satu survei belum membentuk tren antarperiode.' }, sources: catalog.sources };
}

function CurrentLocation() { const location = useLocation(); return <output data-testid="comparison-url">{location.search}</output>; }
function page(path = '/bandingkan-provinsi') {
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false, gcTime: 0 } } });
  return render(<QueryClientProvider client={queryClient}><MemoryRouter initialEntries={[path]}><ProvinceComparisonPage /><CurrentLocation /></MemoryRouter></QueryClientProvider>);
}
function mockApi(transform: (data: ProvinceComparison) => ProvinceComparison = data => data) {
  return vi.spyOn(api, 'get').mockImplementation(async (url, options) => {
    if (url === '/province-comparison/catalog') return { data: catalog };
    return { data: transform(response(options!.params as Record<string, string>)) };
  });
}
beforeEach(() => vi.restoreAllMocks());

describe('perbandingan provinsi dengan data yang tersedia', () => {
  it('membaca dua nilai resmi dalam satu skala dan menjelaskan selisih A dikurangi B', async () => {
    const request = mockApi(); page();
    const result = await screen.findByRole('region', { name: 'Baca keduanya berdampingan.' });
    expect(within(result).getByRole('heading', { name: 'Jawa Tengah' })).toBeInTheDocument();
    expect(within(result).getByRole('heading', { name: 'Jawa Barat' })).toBeInTheDocument();
    expect(result).toHaveTextContent('51,69'); expect(result).toHaveTextContent('56,10');
    expect(result).toHaveTextContent('lebih rendah daripada'); expect(result).toHaveTextContent('4,41 poin persentase');
    expect(request).toHaveBeenCalledWith('/province-comparison', { params: { a: '33', b: '32', period: '2022', indicator: 'literacy' } });
    expect(screen.getByRole('option', { name: 'Kredit (belum tersedia)' })).toBeDisabled();
    expect(screen.getByRole('option', { name: 'Simpanan (belum tersedia)' })).toBeDisabled();
    const user = userEvent.setup(); await user.click(screen.getByText('Lihat tabel dan cara menghitung selisih'));
    expect(within(screen.getByRole('table')).getByText('-4,41 pp')).toBeInTheDocument();
  });

  it('menukar arah perbandingan dan menyimpan pilihan pada URL yang dapat dibuka ulang', async () => {
    mockApi(); const user = userEvent.setup(); page('/bandingkan-provinsi?a=31&b=32&period=2022&indicator=inclusion');
    expect(await screen.findByRole('region', { name: 'Baca keduanya berdampingan.' })).toHaveTextContent('96,62');
    await user.click(screen.getByRole('button', { name: 'Tukar kedua provinsi' }));
    await waitFor(() => expect(screen.getByRole('combobox', { name: 'Provinsi pertama' })).toHaveValue('32'));
    expect(screen.getByRole('combobox', { name: 'Provinsi kedua' })).toHaveValue('31');
    await waitFor(() => expect(screen.getByRole('region', { name: 'Baca keduanya berdampingan.' })).toHaveTextContent('lebih rendah daripada'));
    expect(screen.getByTestId('comparison-url')).toHaveTextContent('a=32&b=31&period=2022&indicator=inclusion');
    await user.selectOptions(screen.getByRole('combobox', { name: 'Angka yang dibandingkan' }), 'literacy');
    expect(await screen.findByRole('region', { name: 'Baca keduanya berdampingan.' })).toHaveTextContent('56,10');
    expect(screen.getByTestId('comparison-url')).toHaveTextContent('indicator=literacy');
  });

  it('menahan permintaan saat kedua pilihan menunjuk provinsi yang sama', async () => {
    const request = mockApi(); const user = userEvent.setup(); page('/bandingkan-provinsi?a=33&b=33');
    expect(await screen.findByRole('heading', { name: 'Pilih dua provinsi yang berbeda.' })).toBeInTheDocument();
    expect(request.mock.calls.filter(([url]) => url === '/province-comparison')).toHaveLength(0);
    await user.selectOptions(screen.getByRole('combobox', { name: 'Provinsi kedua' }), '32');
    expect(await screen.findByRole('region', { name: 'Baca keduanya berdampingan.' })).toBeInTheDocument();
  });

  it('tidak mengganti periode yang hilang dengan data 2022 atau angka nol', async () => {
    mockApi(); page('/bandingkan-provinsi?period=2025');
    expect(await screen.findByRole('heading', { name: 'Kita belum punya pasangan data yang sesuai.' })).toBeInTheDocument();
    expect(screen.getByRole('combobox', { name: 'Periode yang sama' })).toHaveValue('2025');
    expect(screen.getByText('Data provinsi untuk periode ini belum tersedia.')).toBeInTheDocument();
    expect(screen.queryByRole('region', { name: 'Baca keduanya berdampingan.' })).not.toBeInTheDocument();
    expect(screen.queryByText('0,00')).not.toBeInTheDocument();
  });

  it('menjelaskan kredit belum terhubung untuk tautan langsung tanpa menampilkan hasil simulasi', async () => {
    mockApi(); page('/bandingkan-provinsi?indicator=credit');
    expect(await screen.findByText('Data kredit provinsi belum terhubung.')).toBeInTheDocument();
    expect(screen.getByText('Nilai yang belum tersedia tidak dianggap nol.', { exact: false })).toBeInTheDocument();
    expect(screen.queryByRole('img', { name: /skala 0 sampai 100/ })).not.toBeInTheDocument();
    expect(screen.queryByRole('region', { name: 'Baca keduanya berdampingan.' })).not.toBeInTheDocument();
  });

  it('mengikuti keputusan server untuk metadata yang tidak sebanding walaupun kedua nilai tersedia', async () => {
    mockApi(data => ({ ...data, comparison: { ...data.comparison, status: 'unavailable', difference: null, difference_unit: null, reasons: ['Definisi kedua pengukuran tidak sama.'], checks: [{ id: 'definition', label: 'Definisi sama', passed: false }] } })); page();
    expect(await screen.findByText('Definisi kedua pengukuran tidak sama.')).toBeInTheDocument();
    expect(screen.queryByRole('region', { name: 'Baca keduanya berdampingan.' })).not.toBeInTheDocument();
  });

  it('menjaga kode yang tidak dikenal tetap terlihat sebagai pilihan tidak tersedia sampai pembaca memperbaikinya', async () => {
    const request = mockApi(); const user = userEvent.setup(); page('/bandingkan-provinsi?a=99&b=32');
    expect(await screen.findByRole('heading', { name: 'Pilihan pada tautan belum tersedia.' })).toBeInTheDocument();
    expect(screen.getByRole('combobox', { name: 'Provinsi pertama' })).toHaveValue('99');
    expect(request.mock.calls.filter(([url]) => url === '/province-comparison')).toHaveLength(0);
    await user.click(screen.getByRole('button', { name: 'Kembali ke pilihan tersedia' }));
    expect(await screen.findByRole('region', { name: 'Baca keduanya berdampingan.' })).toBeInTheDocument();
  });

  it('menampilkan nilai nol yang sah sebagai observasi, bukan data hilang', async () => {
    mockApi(data => ({ ...data, a: { ...data.a, value: 0 }, comparison: { ...data.comparison, difference: -data.b.value! } })); page();
    const result = await screen.findByRole('region', { name: 'Baca keduanya berdampingan.' });
    expect(result).toHaveTextContent('0,00%');
    expect(result).toHaveTextContent('56,10 poin persentase');
    expect(screen.queryByRole('heading', { name: 'Kita belum punya pasangan data yang sesuai.' })).not.toBeInTheDocument();
  });
});

const biGeography = { id: 'bi-seki-provincial-34-v1', label: '34 wilayah dalam tabel SEKI BI', code_scheme: 'bi-seki-row-v1', code_scheme_label: 'Identitas baris publikasi BI, bukan kode administratif', boundary_note: 'BI menyajikan 34 baris wilayah. Cakupan Papua belum dipadankan dengan geometri SNLIK dan tidak dibagi ke provinsi baru.', map_available: false };
const biRegions = [{ code: 'row-13', name: 'Jawa Tengah', snlik_code: '33' }, { code: 'row-12', name: 'Jawa Barat', snlik_code: '32' }, { code: 'row-11', name: 'DKI Jakarta', snlik_code: '31' }, { code: 'row-30', name: 'Papua', snlik_code: null }, { code: 'row-31', name: 'Papua Barat', snlik_code: null }];
const surveyRegions = [...regions, { code: '91', name: 'Papua' }, { code: '92', name: 'Papua Barat' }];
const biPeriods = [{ id: '2026-08', label: 'Agustus 2026' }, { id: '2026-07', label: 'Juli 2026' }];
const biSources = [{ label: 'BI | SEKI I.12 (XLS sumber)', url: 'https://www.bi.go.id/SEKI/tabel/TABEL1_12.xls', release_date: '2026-10-15', file_last_modified: '2026-10-05T01:57:12Z', retrieved_at: '2026-11-04T01:00:00Z', sha256: 'b104d0c38900c9b52018f54fe734f25ff19842365a8376cc36bcb6ff95ca2a50' }];
const biCatalog: ComparisonCatalog = {
  ...catalog, regions: surveyRegions,
  indicators: [
    ...indicators.filter(item => ['literacy', 'inclusion'].includes(item.code)).map(item => ({ ...item, format: 'percent' as const, periods: catalog.periods, default_period: '2022', regions: surveyRegions, geography })),
    ...['credit', 'deposits'].map(code => ({ code, label: code === 'credit' ? 'Kredit per provinsi' : 'Simpanan per provinsi', unit: 'Rp miliar', source_unit: 'Rp miliar', display_unit: 'Rp triliun', display_scale: 1000, available: true, format: 'currency' as const, regions: biRegions, geography: biGeography, periods: biPeriods, default_period: '2026-08', location_basis: code === 'credit' ? 'Kredit berdasarkan lokasi proyek yang dibiayai.' : 'Simpanan berdasarkan kantor bank penghimpun dana, bukan domisili pemilik simpanan.' })),
  ],
};
const moneyValues: Record<string, Record<string, number>> = { credit: { 'row-12': 1126541.1526, 'row-13': 535521.7562, 'row-11': 2123456.789, 'row-30': 12345, 'row-31': 2345 }, deposits: { 'row-12': 763752.4404, 'row-13': 489945.0455, 'row-11': 4000000, 'row-30': 22345, 'row-31': 3345 } };
function currencyResponse(params: Record<string, string>): ProvinceComparison {
  const indicator = biCatalog.indicators.find(item => item.code === params.indicator)!;
  const monetary = indicator.format === 'currency';
  const available = monetary ? biPeriods.some(period => period.id === params.period) : params.period === '2022';
  const indicatorRegions = monetary ? biRegions : surveyRegions;
  function value(code: string) { return monetary ? moneyValues[params.indicator][code] : values[params.indicator][code] ?? 40; }
  const a = { region: indicatorRegions.find(item => item.code === params.a)!, value: available ? value(params.a) : null, status: available ? 'available' as const : 'missing' as const };
  const b = { region: indicatorRegions.find(item => item.code === params.b)!, value: available ? value(params.b) : null, status: a.status };
  return { geography: monetary ? biGeography : geography, indicator, period: { id: params.period, label: monetary ? biPeriods.find(period => period.id === params.period)?.label || params.period : `SNLIK ${params.period}` }, a, b, comparison: { status: available ? 'comparable' : 'unavailable', difference: available ? Math.round((a.value! - b.value!) * 10000) / 10000 : null, difference_unit: monetary ? 'Rp miliar' : 'pp', reasons: available ? [] : ['Belum ada angka provinsi untuk periode yang diminta.'], checks: [{ id: 'period', label: 'Periode sama', passed: available }] }, trend: { available: false, reason: monetary ? 'Perbandingan ini menampilkan posisi akhir bulan yang sama.' : 'Satu survei belum membentuk tren.' }, sources: monetary ? biSources : catalog.sources };
}
function mockCurrencyApi(transform: (data: ProvinceComparison) => ProvinceComparison = data => data, data = biCatalog) {
  return vi.spyOn(api, 'get').mockImplementation(async (url, options) => url === '/province-comparison/catalog' ? { data } : { data: transform(currencyResponse(options!.params as Record<string, string>)) });
}

describe('kredit dan simpanan BI dengan satuan dan wilayahnya sendiri', () => {
  it('membaca bulan bawaan BI, mengubah miliar menjadi triliun, dan memakai batang dengan skala rupiah yang sama', async () => {
    const request = mockCurrencyApi(); const user = userEvent.setup(); page('/bandingkan-provinsi?indicator=credit&a=row-12&b=row-13');
    const result = await screen.findByRole('region', { name: 'Baca keduanya berdampingan.' });
    expect(result).toHaveTextContent('1.126,54'); expect(result).toHaveTextContent('535,52');
    expect(result).toHaveTextContent('591,02'); expect(result).toHaveTextContent('lebih besar daripada');
    expect(request).toHaveBeenCalledWith('/province-comparison', { params: { a: 'row-12', b: 'row-13', period: '2026-08', indicator: 'credit' } });
    expect(screen.getByRole('combobox', { name: 'Periode yang sama' })).toHaveValue('2026-08');
    expect(screen.getByRole('option', { name: 'Simpanan per provinsi' })).toBeEnabled();
    const bars = within(result).getByRole('img', { name: /skala nol sampai Rp 2.000,00 triliun/ });
    const barValues = Array.from(bars.querySelectorAll<HTMLElement>('.comparison-bar-track i')).map(bar => parseFloat(bar.style.width));
    expect(barValues[0]).toBeCloseTo(56.32705763); expect(barValues[1]).toBeCloseTo(26.77608781);
    expect(screen.queryByRole('img', { name: /cakupan wilayah survei 2022/ })).not.toBeInTheDocument();
    expect(screen.getByText('Kredit mengikuti lokasi proyek.')).toBeInTheDocument();
    await user.click(screen.getByText('Lihat tabel dan cara menghitung selisih'));
    const table = screen.getByRole('table');
    expect(table).toHaveTextContent('1.126.541,1526 Rp miliar'); expect(table).toHaveTextContent('+591.019,3964 Rp miliar');
    expect(screen.getByText('1 Rp triliun = 1.000 Rp miliar.', { exact: false })).toBeInTheDocument();
    expect(screen.getByText('15 Oktober 2026')).toBeInTheDocument();
    expect(screen.getByText('5 Oktober 2026')).toBeInTheDocument();
    expect(screen.getByText('4 November 2026')).toBeInTheDocument();
  });

  it('mengganti sumber dengan pemetaan nama yang diverifikasi, memilih bulan BI lalu mempertahankannya saat memilih simpanan', async () => {
    const request = mockCurrencyApi(); const user = userEvent.setup(); page('/bandingkan-provinsi?a=32&b=33&period=2022');
    await screen.findByRole('region', { name: 'Baca keduanya berdampingan.' });
    await user.selectOptions(screen.getByRole('combobox', { name: 'Angka yang dibandingkan' }), 'credit');
    expect(await screen.findByText('Kredit mengikuti lokasi proyek.')).toBeInTheDocument();
    expect(screen.getByRole('combobox', { name: 'Provinsi pertama' })).toHaveValue('row-12');
    expect(screen.getByRole('combobox', { name: 'Provinsi kedua' })).toHaveValue('row-13');
    expect(screen.getByRole('combobox', { name: 'Periode yang sama' })).toHaveValue('2026-08');
    await user.selectOptions(screen.getByRole('combobox', { name: 'Periode yang sama' }), '2026-07');
    await user.selectOptions(screen.getByRole('combobox', { name: 'Angka yang dibandingkan' }), 'deposits');
    expect(await screen.findByText('Simpanan mengikuti kantor penghimpun dana.')).toBeInTheDocument();
    expect(screen.getByRole('combobox', { name: 'Periode yang sama' })).toHaveValue('2026-07');
    expect(request).toHaveBeenLastCalledWith('/province-comparison', { params: { a: 'row-12', b: 'row-13', period: '2026-07', indicator: 'deposits' } });
    expect(screen.getByTestId('comparison-url')).toHaveTextContent('a=row-12&b=row-13&period=2026-07&indicator=deposits');
    expect(screen.getByRole('heading', { name: 'Bulan observasi berbeda dari tanggal publikasi.' })).toBeInTheDocument();
    expect(screen.queryByText('Angka Agustus tidak berarti diterbitkan pada Agustus.')).not.toBeInTheDocument();
    await user.selectOptions(screen.getByRole('combobox', { name: 'Angka yang dibandingkan' }), 'inclusion');
    expect(screen.getByRole('combobox', { name: 'Provinsi pertama' })).toHaveValue('32');
    expect(screen.getByRole('combobox', { name: 'Periode yang sama' })).toHaveValue('2022');
  });

  it('meminta pemilihan ulang Papua saat berpindah sumber tanpa memakai poligon atau kode lama sebagai nilai BI', async () => {
    const request = mockCurrencyApi(); const user = userEvent.setup(); page('/bandingkan-provinsi?a=91&b=33');
    await screen.findByRole('region', { name: 'Baca keduanya berdampingan.' });
    await user.selectOptions(screen.getByRole('combobox', { name: 'Angka yang dibandingkan' }), 'credit');
    expect(await screen.findByRole('heading', { name: 'Wilayah perlu dipilih ulang.' })).toBeInTheDocument();
    expect(screen.getByRole('combobox', { name: 'Provinsi pertama' })).toHaveValue('');
    expect(screen.getByRole('combobox', { name: 'Provinsi kedua' })).toHaveValue('row-13');
    expect(screen.getByText(/Papua perlu dipilih ulang/)).toBeInTheDocument();
    expect(request.mock.calls.filter(([url, options]) => url === '/province-comparison' && (options?.params as Record<string, string>).indicator === 'credit')).toHaveLength(0);
    await user.selectOptions(screen.getByRole('combobox', { name: 'Provinsi pertama' }), 'row-30');
    expect(await screen.findByRole('region', { name: 'Baca keduanya berdampingan.' })).toHaveTextContent('Papua');
    expect(screen.queryByRole('img', { name: /Lokasi Papua/ })).not.toBeInTheDocument();
  });

  it('menolak reinterpretasi kode numerik SNLIK dari tautan BI dan mempertahankan bulan yang tidak tersedia', async () => {
    const request = mockCurrencyApi(); const user = userEvent.setup(); page('/bandingkan-provinsi?indicator=credit&a=33&b=row-12&period=2027-01');
    expect(await screen.findByRole('heading', { name: 'Pilihan pada tautan belum tersedia.' })).toBeInTheDocument();
    expect(screen.getByRole('combobox', { name: 'Provinsi pertama' })).toHaveValue('33');
    expect(screen.getByRole('combobox', { name: 'Periode yang sama' })).toHaveValue('2027-01');
    expect(request.mock.calls.filter(([url]) => url === '/province-comparison')).toHaveLength(0);
    await user.selectOptions(screen.getByRole('combobox', { name: 'Provinsi pertama' }), 'row-13');
    expect(await screen.findByRole('heading', { name: 'Kita belum punya pasangan data yang sesuai.' })).toBeInTheDocument();
    expect(screen.getByRole('combobox', { name: 'Periode yang sama' })).toHaveValue('2027-01');
    expect(screen.queryByRole('img', { name: /skala nol/ })).not.toBeInTheDocument();
    expect(screen.queryByText('Rp 0,00')).not.toBeInTheDocument();
  });

  it('tidak meminta atau menampilkan nilai BI sebelum versi sumbernya diterbitkan', async () => {
    const unpublished: ComparisonCatalog = { ...biCatalog, indicators: biCatalog.indicators.map(item => item.code === 'credit' ? { ...item, available: false, periods: [], reason: 'Versi BI masih menunggu persetujuan pengelola.' } : item) };
    const request = mockCurrencyApi(data => data, unpublished); page('/bandingkan-provinsi?indicator=credit');
    expect(await screen.findByText('Versi BI masih menunggu persetujuan pengelola.')).toBeInTheDocument();
    expect(request.mock.calls.filter(([url]) => url === '/province-comparison')).toHaveLength(0);
    expect(screen.getByRole('option', { name: 'Kredit per provinsi (belum tersedia)' })).toBeDisabled();
    expect(screen.queryByRole('region', { name: 'Baca keduanya berdampingan.' })).not.toBeInTheDocument();
  });

  it('menampilkan nol yang sah dan menandai nominal kecil tanpa mengubahnya menjadi data hilang', async () => {
    mockCurrencyApi(data => ({ ...data, a: { ...data.a, value: 0 }, b: { ...data.b, value: 1 }, comparison: { ...data.comparison, difference: -1 } })); page('/bandingkan-provinsi?indicator=deposits');
    const result = await screen.findByRole('region', { name: 'Baca keduanya berdampingan.' });
    expect(result).toHaveTextContent('Rp 0,00'); expect(result).toHaveTextContent('<0,01');
    expect(result).toHaveTextContent('kurang dari Rp 0,01 triliun');
    const bars = within(result).getByRole('img', { name: /skala nol/ });
    expect(bars.querySelector<HTMLElement>('.bar-a .comparison-bar-track i')?.style.width).toBe('0%');
    expect(screen.queryByRole('heading', { name: 'Kita belum punya pasangan data yang sesuai.' })).not.toBeInTheDocument();
  });
});
