import { fireEvent, render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vitest';
import { ConsumerDataGuide, ProvinceInclusionMap } from '../../resources/js/components/province-map';

describe('peta provinsi dengan cakupan survei historis', () => {
  it('membuka indikator provinsi dari daftar lalu mempertahankan pilihan saat warna indikator berubah', async () => {
    const user = userEvent.setup();
    render(<ProvinceInclusionMap />);
    await user.selectOptions(screen.getByRole('combobox', { name: 'Pilih provinsi' }), '17');
    const detail = screen.getByRole('complementary');
    expect(within(detail).getByRole('heading', { name: 'Bengkulu' })).toBeInTheDocument();
    expect(detail).toHaveTextContent('88,05');
    expect(detail).toHaveTextContent('30,39');
    expect(detail).toHaveTextContent('Inklusi nasional 2022: 85,10%');
    await user.click(screen.getByRole('button', { name: 'Memahami keuangan' }));
    expect(detail).toHaveTextContent('Literasi nasional 2022: 49,68%');
    expect(detail).toHaveTextContent('19,29 poin persentase di bawah');
    expect(screen.getByRole('combobox')).toHaveValue('17');
    expect(screen.getByRole('button', { name: 'Bengkulu, literasi 30,39 persen, tahun 2022' })).toHaveAttribute('aria-pressed', 'true');
  });

  it('mendukung pemilihan peta lewat papan ketik dan menyediakan tabel semua 34 cakupan historis', async () => {
    const user = userEvent.setup();
    render(<ProvinceInclusionMap />);
    const path = screen.getByRole('button', { name: 'Sulawesi Barat, inklusi 70,39 persen, tahun 2022' });
    fireEvent.keyDown(path, { key: 'Enter' });
    expect(screen.getByRole('combobox')).toHaveValue('76');
    expect(screen.getByRole('complementary')).toHaveTextContent('14,71 poin persentase di bawah');
    await user.click(screen.getByRole('button', { name: 'Lihat semua angka provinsi' }));
    const table = screen.getByRole('table');
    expect(within(table).getAllByRole('row')).toHaveLength(35);
    expect(within(table).getByRole('button', { name: 'Papua' })).toBeInTheDocument();
    expect(within(table).queryByText('Papua Tengah')).not.toBeInTheDocument();
    expect(screen.getByText(/angka tidak dibagi ke provinsi baru/)).toBeInTheDocument();
    await user.click(within(table).getByRole('button', { name: 'DKI Jakarta' }));
    expect(screen.getByRole('combobox')).toHaveValue('31');
    expect(screen.getByRole('complementary')).toHaveTextContent('96,62');
  });

  it('menghubungkan petunjuk pembacaan total dengan jalur bantuan resmi yang terpisah', () => {
    render(<ConsumerDataGuide />);
    expect(screen.getByText(/Menjumlahkan keduanya bisa menghitung kejadian yang sama dua kali/)).toBeInTheDocument();
    expect(screen.getByText(/Diskusi FINWATCH tidak meneruskan pengaduan ke OJK/)).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Buka portal Kontak OJK 157' })).toHaveAttribute('href', 'https://kontak157.ojk.go.id/');
  });
});
