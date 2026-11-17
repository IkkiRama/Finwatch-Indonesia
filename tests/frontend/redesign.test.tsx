import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter } from 'react-router-dom';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import axios from 'axios';
import { api, type Identity } from '../../resources/js/lib/api';
import { ArticlesPage, type Article, sharingLinks } from '../../resources/js/components/articles';
import { LoginPage, MfaChallenge } from '../../resources/js/components/admin';

const guest: Identity = { authenticated: false, user: null, google_configured: true, admin_mfa_required: false, admin_mfa_verified: false };
const author = { id: 100, slug: 'penulis-uji', public_name: 'Penulis Uji', bio: 'Profil khusus pengujian.' };
const categories = [{ id: 10, slug: 'bank', name: 'Perbankan' }, { id: 11, slug: 'inklusi', name: 'Inklusi' }];
const articles = [
  ['NPL dan CAR, dua cerita berbeda', 'npl-car', 'education', 0],
  ['Kenali penipuan sebelum transfer', 'penipuan', 'education', 0],
  ['Punya akses, sudah paham?', 'akses-paham', 'education', 1],
  ['Beda periode dan tanggal rilis', 'periode-rilis', 'education', 0],
  ['Membaca jarak literasi dan inklusi', 'jarak-indeks', 'research', 1],
  ['Potret perbankan historis', 'potret-bank', 'research', 0],
].map(([title, slug, type, category], index) => ({ id: index + 1, title, slug, content_type: type, category: categories[Number(category)], author, excerpt: 'Catatan dengan sumber dan batas interpretasi yang bisa diperiksa.', reading_minutes: 4, published_at: '2026-11-03T08:00:00+07:00', is_featured: index === 0, cover: null })) as Article[];

function renderPage(content: React.ReactNode, path = '/') {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false, staleTime: Infinity }, mutations: { retry: false } } });
  return render(<QueryClientProvider client={client}><MemoryRouter initialEntries={[path]}>{content}</MemoryRouter></QueryClientProvider>);
}

beforeEach(() => { vi.restoreAllMocks(); });

describe('bacaan yang dapat ditemukan pembaca', () => {
  function catalog() {
    return vi.spyOn(api, 'get').mockImplementation(async (url, options) => {
      if (url === '/categories') return { data: { data: categories } };
      if (url === '/authors') return { data: { data: [author] } };
      const params = (options?.params || {}) as Record<string, string | undefined>;
      const rows = articles.filter(item => (!params.type || item.content_type === params.type) && (!params.query || item.title.toLowerCase().includes(String(params.query).toLowerCase())) && (!params.category || item.category.slug === params.category));
      return { data: { data: rows, total: rows.length, current_page: 1, last_page: 1, from: 1, to: rows.length } };
    });
  }

  it('memisahkan empat bacaan edukasi dan menyediakan pencarian serta topik yang bekerja', async () => {
    catalog(); const user = userEvent.setup();
    renderPage(<ArticlesPage />, '/artikel');
    expect(await screen.findByRole('heading', { name: articles[0].title })).toBeInTheDocument();
    expect(screen.getByText('4 bacaan tersedia')).toBeInTheDocument();
    expect(screen.queryByRole('heading', { name: articles[4].title })).not.toBeInTheDocument();
    await user.type(screen.getByRole('searchbox', { name: 'Cari bacaan' }), 'NPL');
    await waitFor(() => expect(screen.queryByRole('heading', { name: articles[1].title })).not.toBeInTheDocument());
    expect(screen.getByRole('heading', { name: articles[0].title })).toBeInTheDocument();
    await user.clear(screen.getByRole('searchbox', { name: 'Cari bacaan' }));
    await user.selectOptions(screen.getByRole('combobox', { name: 'Kategori' }), 'inklusi');
    expect(await screen.findByRole('heading', { name: articles[2].title })).toBeInTheDocument();
    await waitFor(() => expect(screen.queryByRole('heading', { name: articles[0].title })).not.toBeInTheDocument());
    await user.type(screen.getByRole('searchbox', { name: 'Cari bacaan' }), 'tidak-ada');
    expect(await screen.findByRole('heading', { name: 'Belum ketemu bacaan yang cocok' })).toBeInTheDocument();
  });

  it('menampilkan riset sebagai bacaan tersendiri tanpa mencampur artikel edukasi', async () => {
    catalog(); renderPage(<ArticlesPage research />, '/riset');
    expect(await screen.findByRole('heading', { name: articles[4].title })).toBeInTheDocument();
    expect(screen.getByRole('heading', { name: articles[5].title })).toBeInTheDocument();
    expect(screen.getByText('2 catatan riset')).toBeInTheDocument();
    expect(screen.queryByRole('heading', { name: articles[0].title })).not.toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Untuk dipahami' })).toHaveAttribute('href', '/artikel');
  });

  it('mengenkode judul dan tautan saat berbagi tanpa mengubah alamat artikel', () => {
    const url = 'http://localhost:8000/artikel/akses-paham';
    const title = 'Akses & pemahaman: apa bedanya?';
    const links = sharingLinks(url, title);
    expect(links.map(link => link.name)).toEqual(['WhatsApp', 'Facebook', 'X', 'Telegram', 'LinkedIn']);
    expect(new URL(links.find(link => link.name === 'X')!.url).searchParams.get('url')).toBe(url);
    expect(new URL(links.find(link => link.name === 'X')!.url).searchParams.get('text')).toBe(title);
    expect(new URL(links[0].url).searchParams.get('text')).toBe(`${title} ${url}`);
  });
});

describe('masuk dan penyiapan admin', () => {
  it('menyediakan tautan Google yang bernama jelas dan mempertahankan tujuan kembali', async () => {
    vi.spyOn(api, 'get').mockResolvedValue({ data: guest });
    renderPage(<LoginPage />, '/masuk?return_to=%2Fadmin');
    const link = await screen.findByRole('link', { name: 'Masuk dengan Google' });
    expect(link).toHaveAttribute('href', '/auth/google/redirect?return_to=%2Fadmin');
    expect(link).toHaveClass('google-button');
    expect(screen.queryByText('Google OAuth belum dikonfigurasi.')).not.toBeInTheDocument();
  });

  it('menampilkan penyiapan autentikator untuk admin yang belum enroll dan menyembunyikan kuncinya', async () => {
    const identity: Identity = { ...guest, authenticated: true, admin_mfa_required: true, admin_mfa_enrolled: false, user: { id: 100, name: 'Admin Uji', avatar_url: null, roles: ['admin'], permissions: ['admin.access'] } };
    vi.spyOn(api, 'get').mockResolvedValue({ data: identity });
    vi.spyOn(axios, 'get').mockResolvedValue({ status: 204 });
    const post = vi.spyOn(api, 'post').mockResolvedValue({ data: { secret: 'TESTKEYONLY234567' } });
    const user = userEvent.setup(); renderPage(<MfaChallenge />, '/admin');
    await user.click(await screen.findByRole('button', { name: 'Mulai penyiapan' }));
    const key = screen.getByLabelText('Kunci penyiapan privat');
    expect(key).toHaveAttribute('type', 'password');
    expect(key).toHaveAttribute('readonly');
    expect(screen.getByRole('button', { name: 'Konfirmasi & buka workspace' })).toBeDisabled();
    await user.click(screen.getByRole('button', { name: 'Tampilkan kunci' }));
    expect(key).toHaveAttribute('type', 'text');
    await user.click(screen.getByRole('button', { name: 'Sembunyikan kunci' }));
    expect(key).toHaveAttribute('type', 'password');
    await user.type(screen.getByLabelText('Kode enam digit dari aplikasi'), '123456');
    await user.click(screen.getByRole('button', { name: 'Konfirmasi & buka workspace' }));
    await waitFor(() => expect(post).toHaveBeenCalledWith('/admin/mfa/enrollment/confirm', { code: '123456' }));
    expect(post.mock.calls[1][1]).not.toHaveProperty('secret');
    expect(screen.queryByLabelText('Kunci penyiapan privat')).not.toBeInTheDocument();
  });

  it('memberi jalan pemulihan ketika penyiapan MFA gagal tanpa membuka workspace', async () => {
    vi.spyOn(api, 'get').mockResolvedValue({ data: { ...guest, authenticated: true, admin_mfa_required: true, admin_mfa_enrolled: false, user: { id: 100, name: 'Admin Uji', avatar_url: null, roles: ['admin'], permissions: ['admin.access'] } } });
    vi.spyOn(axios, 'get').mockResolvedValue({ status: 204 });
    vi.spyOn(api, 'post').mockRejectedValue(new Error('Fixture transport failure'));
    const user = userEvent.setup(); renderPage(<MfaChallenge />, '/admin');
    await user.click(await screen.findByRole('button', { name: 'Mulai penyiapan' }));
    expect(await screen.findByRole('alert')).toHaveTextContent('Permintaan belum berhasil. Silakan coba kembali.');
    expect(screen.getByRole('link', { name: 'Masuk ulang dengan Google' })).toHaveAttribute('href', '/auth/google/redirect?return_to=%2Fadmin');
    expect(screen.queryByLabelText('Kunci penyiapan privat')).not.toBeInTheDocument();
  });
});
