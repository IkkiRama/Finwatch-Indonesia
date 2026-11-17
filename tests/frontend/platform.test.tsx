import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import axios from 'axios';
import { api, type Identity, type Source } from '../../resources/js/lib/api';
import { Footer } from '../../resources/js/components/shared';
import { PublicLayout, SourceTable } from '../../resources/js/components/public';
import { AdminLayout, LoginPage } from '../../resources/js/components/admin';

const guest: Identity = { authenticated: false, user: null, google_configured: false, admin_mfa_required: false, admin_mfa_verified: false };
function withProviders(content: React.ReactNode, path = '/') {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false, staleTime: Infinity } } });
  return render(<QueryClientProvider client={client}><MemoryRouter initialEntries={[path]}>{content}</MemoryRouter></QueryClientProvider>);
}
const sources: Source[] = ['Alpha', 'Bravo', 'Charlie', 'Delta', 'Echo', 'Foxtrot', 'Golf'].map((name, i) => ({ id: i + 1, code: `TEST${i + 1}`, name, url: 'https://example.test/source', method: i % 2 ? 'official_api' : 'manual', status: 'unconfigured', last_checked_at: null, description: `Fixture ${name}` }));
beforeEach(() => {
  vi.restoreAllMocks();
  Object.defineProperty(window, 'matchMedia', { writable: true, value: vi.fn().mockImplementation((query: string) => ({ matches: query === '(prefers-reduced-motion: reduce)', media: query, onchange: null, addListener: vi.fn(), removeListener: vi.fn(), addEventListener: vi.fn(), removeEventListener: vi.fn(), dispatchEvent: vi.fn() })) });
});

describe('sumber data', () => {
  it('memfilter nama dan metode, serta menampilkan hasil kosong secara jujur', async () => {
    const user = userEvent.setup(); render(<SourceTable sources={sources} />);
    await user.selectOptions(screen.getByRole('combobox', { name: 'Metode sumber' }), 'official_api');
    expect(screen.getByText('Bravo')).toBeInTheDocument(); expect(screen.queryByText('Alpha')).not.toBeInTheDocument();
    await user.type(screen.getByRole('textbox', { name: 'Cari sumber data' }), 'Foxtrot');
    expect(screen.getByText('Foxtrot')).toBeInTheDocument(); expect(screen.queryByText('Bravo')).not.toBeInTheDocument();
    await user.clear(screen.getByRole('textbox', { name: 'Cari sumber data' }));
    await user.type(screen.getByRole('textbox', { name: 'Cari sumber data' }), 'tidak-ada');
    expect(screen.getByText('Tidak ada sumber yang sesuai dengan pencarian.')).toBeInTheDocument();
  });
  it('mengurutkan dan berpindah halaman tanpa kehilangan label aksesibel', async () => {
    const user = userEvent.setup(); render(<SourceTable sources={sources} />);
    expect(screen.queryByText('Golf')).not.toBeInTheDocument();
    await user.click(screen.getByRole('button', { name: 'Berikutnya' }));
    expect(screen.getByText('Golf')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Berikutnya' })).toBeDisabled();
    await user.click(screen.getByRole('button', { name: 'Sebelumnya' }));
    await user.click(screen.getByRole('button', { name: 'Sumber data' }));
    const dataRows = screen.getAllByRole('row').slice(1);
    expect(within(dataRows[0]).getByText('Golf')).toBeInTheDocument();
    expect(screen.getByRole('columnheader', { name: 'Sumber data' })).toHaveAttribute('aria-sort', 'descending');
  });
});
describe('identitas dan navigasi', () => {
  it('memungkinkan pembaca mengakhiri sesi dengan CSRF sebelum logout', async () => {
    const reader: Identity = { ...guest, authenticated: true, user: { id: 1, name: 'Reader test', avatar_url: null, roles: ['reader'], permissions: [] } };
    vi.spyOn(api, 'get').mockResolvedValueOnce({ data: reader }).mockResolvedValue({ data: guest });
    const csrfRequest = vi.spyOn(axios, 'get').mockResolvedValue({ status: 204 });
    const post = vi.spyOn(api, 'post').mockResolvedValue({ status: 204 });
    const user = userEvent.setup(); withProviders(<LoginPage />, '/masuk');
    await user.click(await screen.findByRole('button', { name: 'Keluar dari akun' }));
    expect(await screen.findByText('Google OAuth belum dikonfigurasi.')).toBeInTheDocument();
    expect(csrfRequest).toHaveBeenCalledWith('/sanctum/csrf-cookie', { withCredentials: true });
    expect(post).toHaveBeenCalledWith('/logout');
  });
  it('menutup menu seluler setelah tautan dipilih', async () => {
    vi.spyOn(api, 'get').mockResolvedValue({ data: guest }); const user = userEvent.setup();
    withProviders(<Routes><Route element={<PublicLayout />}><Route path="/" element={<h1>Beranda test</h1>} /><Route path="/artikel" element={<h1>Artikel test</h1>} /></Route></Routes>);
    await user.click(screen.getByRole('button', { name: 'Buka navigasi' }));
    const mobile = screen.getByRole('navigation', { name: 'Navigasi seluler' });
    await user.click(within(mobile).getByRole('link', { name: 'Edukasi keuangan' }));
    expect(screen.getByRole('heading', { name: 'Artikel test' })).toBeInTheDocument();
    expect(screen.queryByRole('navigation', { name: 'Navigasi seluler' })).not.toBeInTheDocument();
  });
  it('menonaktifkan login Google ketika kredensial belum dikonfigurasi', async () => {
    vi.spyOn(api, 'get').mockResolvedValue({ data: guest }); withProviders(<LoginPage />, '/masuk');
    expect(await screen.findByText('Google OAuth belum dikonfigurasi.')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Masuk dengan Google' })).toBeDisabled();
  });
  it('menolak reader tanpa meminta endpoint admin', async () => {
    const reader: Identity = { ...guest, authenticated: true, user: { id: 1, name: 'Reader test', avatar_url: null, roles: ['reader'], permissions: [] } };
    const get = vi.spyOn(api, 'get').mockResolvedValue({ data: reader });
    withProviders(<Routes><Route path="/admin" element={<AdminLayout />}><Route index element={<h1>Admin rahasia</h1>} /></Route></Routes>, '/admin');
    expect(await screen.findByRole('heading', { name: 'Akses workspace dibatasi' })).toBeInTheDocument();
    expect(screen.queryByText('Admin rahasia')).not.toBeInTheDocument();
    expect(get).toHaveBeenCalledTimes(1); expect(get).toHaveBeenCalledWith('/me');
  });
  it('meminta TOTP sebelum menampilkan workspace saat MFA diperlukan', async () => {
    const admin: Identity = { ...guest, authenticated: true, admin_mfa_required: true, user: { id: 1, name: 'Admin test', avatar_url: null, roles: ['super_admin'], permissions: ['admin.access'] } };
    vi.spyOn(api, 'get').mockResolvedValue({ data: admin });
    withProviders(<Routes><Route path="/admin" element={<AdminLayout />}><Route index element={<h1>Admin rahasia</h1>} /></Route></Routes>, '/admin');
    expect(await screen.findByRole('textbox', { name: 'Kode autentikator' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Verifikasi & lanjutkan' })).toBeDisabled();
    expect(screen.queryByText('Admin rahasia')).not.toBeInTheDocument();
  });
});
it('menampilkan footer tahun kalender, atribusi, dan tautan profil yang aman', () => {
  withProviders(<Footer />); expect(screen.getByText(`© ${new Date().getFullYear()} FINWATCH Indonesia`)).toBeInTheDocument();
  const link = screen.getByRole('link', { name: 'Profil Instagram Rifki Romadhan (tab baru)' });
  expect(link).toHaveAttribute('href', 'https://www.instagram.com/george_ikki/'); expect(link).toHaveAttribute('rel', 'noopener noreferrer');
  expect(screen.getByText('Proyek independen; tidak berafiliasi dengan OJK.')).toBeInTheDocument();
  expect(screen.getByRole('img', { name: 'cinta' })).toHaveClass('beating-heart');
  expect(screen.getByRole('contentinfo').textContent).not.toMatch(/\blove\b/i);
  expect(screen.getByRole('navigation', { name: 'Tautan footer' })).toContainElement(screen.getByRole('link', { name: 'Sumber & metode' }));
});

