import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter } from 'react-router-dom';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import axios from 'axios';
import { api } from '../../resources/js/lib/api';
import type { Series, Signal } from '../../resources/js/components/analytics';
import { StressPage } from '../../resources/js/components/stress-lab';
import { RiskEvidence, Signals } from '../../resources/js/components/risk-story';

function renderPage(content: React.ReactNode) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false, staleTime: Infinity } } });
  return render(<QueryClientProvider client={client}><MemoryRouter>{content}</MemoryRouter></QueryClientProvider>);
}
const baseline = (code: string, latest: number): Series => ({ dataset_id: 1, code, label: code, unit: code === 'credit' ? 'Rp triliun' : '%', scope: 'Indonesia', segment: '', sector: 'banking', frequency: 'monthly', latest, period: '2025-06-01', release_date: '2025-08-29', retrieved_at: '2026-10-26', source_url: 'https://ojk.go.id/', dataset_version: 'fixture-official', methodology_note: 'Fixture metode sama.', change: null, change_unit: 'pp', comparable: false, points: [{ period: '2025-06-01', value: latest }] });
const signal: Signal = { rule_id: 1, dataset_id: 1, segment: '', name: 'Perubahan LDR antarobservasi', version: 1, indicator_code: 'ldr', scope: 'Indonesia', period: '2025-06-01', value: -1.76, unit: 'pp', threshold: 3, operator: 'change_gt', samples: 13, minimum_samples: 6, status: 'within_rule', severity: 'watch', methodology: 'Kenaikan LDR melebihi 3 pp.', source_url: 'https://ojk.go.id/', dataset_version: 'fixture', evaluated_at: '2026-11-03T09:00:00+07:00', model_version: 'rules-1.0' };
beforeEach(() => { vi.restoreAllMocks(); });

describe('simulasi untuk pembaca', () => {
  function catalog(series = [baseline('credit', 8059), baseline('npl_gross', 2.22), baseline('car', 25.8)]) {
    vi.spyOn(api, 'get').mockImplementation(async url => url === '/stress-scenarios' ? { data: { data: [] } } : { data: { data: series } });
  }
  it('mengganti asumsi tekanan tanpa menimpa titik awal dan menandai hasil lama saat input berubah', async () => {
    catalog(); vi.spyOn(axios, 'get').mockResolvedValue({ status: 204 });
    const result = { baseline: { credit: 1000, npl: 2, car: 20 }, parameters: { credit: 1000, npl: 2, car: 20, npl_shock_pp: 3, lgd_percent: 50 }, additional_loss: 15, capital_before: 200, capital_after: 185, car_after: 18.5, npl_after: 5, formula: 'Fixture server formula.', assumptions: 'Fixture model pembelajaran.', model_version: 'sensitivity-1.0' };
    const post = vi.spyOn(api, 'post').mockResolvedValue({ data: { data: result } });
    const user = userEvent.setup(); renderPage(<StressPage />);
    await user.click(await screen.findByRole('button', { name: 'Pakai contoh Rp1.000' }));
    await user.click(screen.getByRole('button', { name: /Tekanan moderat/ }));
    expect(screen.getByLabelText('Total kredit / proksi ATMR')).toHaveValue(1000);
    expect(screen.getByLabelText('NPL awal (%)')).toHaveValue(2);
    expect(screen.getByLabelText('CAR awal (%)')).toHaveValue(20);
    expect(screen.getByLabelText('Kenaikan NPL (pp)')).toHaveValue(3);
    expect(screen.getByLabelText('Bagian kerugian / LGD (%)')).toHaveValue(50);
    await user.click(screen.getByRole('button', { name: 'Hitung sensitivitas' }));
    await waitFor(() => expect(post).toHaveBeenCalledWith('/stress-tests', { credit: '1000', npl: '2', car: '20', npl_shock_pp: '3', lgd_percent: '50' }));
    expect(await screen.findByRole('heading', { name: 'Ini yang berubah dalam model.' })).toBeInTheDocument();
    expect(screen.getAllByText('18,5%').length).toBeGreaterThan(0);
    await user.click(screen.getByRole('button', { name: /Tekanan berat/ }));
    expect(screen.getByRole('status')).toHaveTextContent('Input sudah berubah');
    expect(screen.getByLabelText('Total kredit / proksi ATMR')).toHaveValue(1000);
    expect(screen.getByLabelText('Kenaikan NPL (pp)')).toHaveValue(7);
    expect(screen.getByLabelText('Bagian kerugian / LGD (%)')).toHaveValue(65);
  });
  it('mengisi baseline hanya jika lingkup, segmen, dan periode tiga indikator cocok', async () => {
    const credit = baseline('credit', 8059); const npl = baseline('npl_gross', 2.22); npl.period = '2025-05-01';
    catalog([credit, npl, baseline('car', 25.8)]);
    const user = userEvent.setup(); renderPage(<StressPage />);
    const button = screen.getByRole('button', { name: 'Gunakan baseline data tersedia' });
    await waitFor(() => expect(button).toBeEnabled()); await user.click(button);
    expect(await screen.findByRole('alert')).toHaveTextContent('periode, lingkup, dan segmen yang sama belum lengkap');
    expect(screen.getByLabelText('Total kredit / proksi ATMR')).toHaveValue(null);
  });
  it('mempertahankan pilihan tekanan ketika memakai angka publikasi yang sebanding', async () => {
    catalog(); const user = userEvent.setup(); renderPage(<StressPage />);
    await user.click(screen.getByRole('button', { name: /Tanpa guncangan/ }));
    const button = screen.getByRole('button', { name: 'Gunakan baseline data tersedia' });
    await waitFor(() => expect(button).toBeEnabled()); await user.click(button);
    expect(screen.getByLabelText('Total kredit / proksi ATMR')).toHaveValue(8059);
    expect(screen.getByLabelText('NPL awal (%)')).toHaveValue(2.22);
    expect(screen.getByLabelText('Kenaikan NPL (pp)')).toHaveValue(0);
    expect(screen.getByRole('button', { name: /Tanpa guncangan/ })).toHaveAttribute('aria-pressed', 'true');
  });
});

describe('bukti risiko yang bisa dibaca', () => {
  it('menerangkan arah penurunan dan batas aturan tanpa memberi rating keamanan', () => {
    renderPage(<Signals signals={[signal]} />);
    expect(screen.getByRole('heading', { name: 'Untuk apa peringatan dini?' })).toBeInTheDocument();
    expect(screen.getByRole('img', { name: /Posisi nilai -1,76 pp dan batas 3 pp/ })).toBeInTheDocument();
    expect(screen.getByText(/Nilai negatif berarti indikator turun/)).toBeInTheDocument();
    expect(screen.getAllByText('Tidak melewati batas aturan').length).toBeGreaterThan(0);
  });
  it('menunda visual penilaian saat data belum cukup walaupun ada nilai mentah', () => {
    renderPage(<Signals signals={[{ ...signal, status: 'insufficient_data', samples: 2 }]} />);
    expect(screen.getByText('Belum dinilai')).toBeInTheDocument();
    expect(screen.queryByRole('img', { name: /Posisi nilai/ })).not.toBeInTheDocument();
  });
  it('memilih sisi pendanaan tanpa mencampur seluruh indikator di satu tampilan', async () => {
    const user = userEvent.setup(); renderPage(<RiskEvidence series={[baseline('car', 25.8), baseline('credit', 8059), baseline('npl_gross', 2.22)]} />);
    expect(screen.getByRole('heading', { name: 'Bantalan modal bank' })).toBeInTheDocument();
    expect(screen.queryByRole('heading', { name: 'Kredit yang disalurkan' })).not.toBeInTheDocument();
    await user.click(screen.getByRole('button', { name: 'Simpanan & penyaluran' }));
    expect(screen.getByRole('heading', { name: 'Kredit yang disalurkan' })).toBeInTheDocument();
    expect(screen.queryByRole('heading', { name: 'Bantalan modal bank' })).not.toBeInTheDocument();
  });
});
