import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { act, render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import axios from 'axios';
import { api, type Identity } from '../../resources/js/lib/api';
import { articleSlug } from '../../resources/js/lib/editorial';
import { CommentSection, type Article } from '../../resources/js/components/articles';
import { ArticleEditor, MediaLibrary, ModerationAdmin } from '../../resources/js/components/content-admin';
import { HealthPage } from '../../resources/js/components/admin';

const identity: Identity = { authenticated: true, google_configured: true, admin_mfa_required: false, admin_mfa_verified: true, user: { id: 4, name: 'Pembaca Uji', avatar_url: null, roles: ['admin'], permissions: ['admin.access', 'comments.manage'] } };
const article = { id: 8, slug: 'bacaan-uji', allow_comments: true } as Article;
const comment = { id: 12, article_id: 8, parent_id: null, body: 'Angka perlu dibaca bersama konteksnya.', user: { id: 4, name: 'Pembaca Uji', avatar_url: null }, deleted: false, status: 'approved', depth: 0, locked: false, likes: 0, liked: false, replies_count: 0, created_at: '2026-11-03T08:00:00+07:00', edited_at: null, can_edit: true, can_moderate: true };

vi.mock('@tiptap/react', () => ({ useEditor: () => null, EditorContent: () => null }));

function page(content: React.ReactNode) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false, staleTime: Infinity }, mutations: { retry: false } } });
  return render(<QueryClientProvider client={client}><MemoryRouter>{content}</MemoryRouter></QueryClientProvider>);
}

beforeEach(() => {
  vi.restoreAllMocks();
  Object.defineProperties(HTMLDialogElement.prototype, { showModal: { configurable: true, value() { this.setAttribute('open', ''); } }, close: { configurable: true, value() { this.removeAttribute('open'); } } });
  vi.spyOn(axios, 'get').mockResolvedValue({ status: 204 });
});

describe('alamat publikasi otomatis', () => {
  it('menghasilkan alamat dari judul, merapikan aksen dan tanda baca, serta membatasi panjang', () => {
    expect(articleSlug('  Café & Kredit: Mulai dari Data!  ')).toBe('cafe-kredit-mulai-dari-data');
    expect(articleSlug('Kenali risiko / pahami konteks')).toBe('kenali-risiko-pahami-konteks');
    expect(articleSlug('a'.repeat(179) + ' judul panjang')).toHaveLength(179);
    expect(articleSlug('')).toBe('');
  });
});

describe('diskusi dengan tindakan yang jelas', () => {
  function discussion() {
    vi.spyOn(api, 'get').mockImplementation(async url => ({ data: url === '/me' ? identity : { data: [comment], next_cursor: null, has_more: false } }));
    return page(<CommentSection article={article} hash="" />);
  }

  it('menyimpan suntingan ke moderasi dan menyediakan pembatalan tanpa mengirim perubahan', async () => {
    const request = vi.spyOn(api, 'request').mockResolvedValue({ status: 200 });
    const user = userEvent.setup(); discussion();
    await user.click(await screen.findByRole('button', { name: 'Sunting' }));
    const editor = screen.getByRole('textbox', { name: 'Sunting komentar' });
    await user.clear(editor); await user.type(editor, 'Periode datanya juga perlu disebutkan.');
    await user.click(screen.getByRole('button', { name: 'Batal' }));
    expect(request).not.toHaveBeenCalled();
    await user.click(screen.getByRole('button', { name: 'Sunting' }));
    expect(screen.getByRole('textbox', { name: 'Sunting komentar' })).toHaveValue(comment.body);
    await user.clear(screen.getByRole('textbox', { name: 'Sunting komentar' }));
    await user.type(screen.getByRole('textbox', { name: 'Sunting komentar' }), 'Periode datanya juga perlu disebutkan.');
    await user.click(screen.getByRole('button', { name: 'Simpan untuk ditinjau' }));
    await waitFor(() => expect(request).toHaveBeenCalledWith({ method: 'patch', url: '/comments/12', data: { body: 'Periode datanya juga perlu disebutkan.' } }));
    expect(await screen.findByText('Perubahan menunggu tinjauan moderator.')).toBeInTheDocument();
    expect(screen.queryByRole('textbox', { name: 'Sunting komentar' })).not.toBeInTheDocument();
  });

  it('meminta keputusan di dialog sebelum menghapus dan menjaga pembatalan tanpa efek', async () => {
    const request = vi.spyOn(api, 'request').mockResolvedValue({ status: 204 });
    const user = userEvent.setup(); discussion();
    await user.click(await screen.findByRole('button', { name: 'Hapus' }));
    const dialog = screen.getByRole('dialog', { name: 'Hapus komentarmu?' });
    expect(dialog).toHaveTextContent('Balasan dari pembaca lain tetap dapat dibaca');
    await user.click(within(dialog).getByRole('button', { name: 'Batal' }));
    expect(request).not.toHaveBeenCalled();
    await user.click(screen.getByRole('button', { name: 'Hapus' }));
    await user.click(within(screen.getByRole('dialog')).getByRole('button', { name: 'Hapus komentar' }));
    await waitFor(() => expect(request).toHaveBeenCalledWith({ method: 'delete', url: '/comments/12', data: undefined }));
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
  });

  it('mengirim laporan privat dengan alasan yang valid melalui dialog', async () => {
    const request = vi.spyOn(api, 'request').mockResolvedValue({ status: 200 });
    const user = userEvent.setup(); discussion();
    await user.click(await screen.findByRole('button', { name: 'Laporkan' }));
    const dialog = screen.getByRole('dialog', { name: 'Laporkan komentar' });
    expect(within(dialog).getByRole('button', { name: 'Kirim laporan' })).toBeDisabled();
    await user.type(within(dialog).getByRole('textbox', { name: 'Apa yang perlu diperiksa?' }), 'Ada tautan yang perlu diperiksa.');
    await user.click(within(dialog).getByRole('button', { name: 'Kirim laporan' }));
    await waitFor(() => expect(request).toHaveBeenCalledWith({ method: 'post', url: '/comments/12/reports', data: { reason: 'Ada tautan yang perlu diperiksa.' } }));
    expect(await screen.findByText('Laporan diterima. Moderator akan meninjaunya.')).toBeInTheDocument();
  });
});

it('meminta alasan sebelum menyetujui komentar dan menampilkan keputusan dalam bahasa Indonesia', async () => {
  const record = { ...comment, status: 'pending', article: { title: 'Bacaan uji', slug: 'bacaan-uji' }, reports: [] };
  const get = vi.spyOn(api, 'get').mockResolvedValue({ data: { data: [record], total: 1, current_page: 1, last_page: 1 } });
  const post = vi.spyOn(api, 'post').mockImplementation(async () => { get.mockResolvedValue({ data: { data: [], total: 0, current_page: 1, last_page: 1 } }); return { status: 200 }; });
  const user = userEvent.setup(); page(<ModerationAdmin />);
  await user.click(await screen.findByRole('button', { name: 'Setujui' }));
  const dialog = screen.getByRole('dialog', { name: 'Setujui komentar' });
  const reason = within(dialog).getByRole('textbox', { name: 'Alasan keputusan' });
  expect(reason).toHaveAttribute('maxlength', '200');
  await user.type(reason, 'Relevan dan sesuai pedoman diskusi.');
  await user.click(within(dialog).getByRole('button', { name: 'Setujui komentar' }));
  await waitFor(() => expect(post).toHaveBeenCalledWith('/admin/comments/12/moderate', { action: 'approve', reason: 'Relevan dan sesuai pedoman diskusi.' }));
  expect(await screen.findByRole('heading', { name: 'Antrean moderasi bersih' })).toBeInTheDocument();
});

it('memberi label unggahan yang jelas dan menampilkan penolakan penghapusan media yang masih digunakan', async () => {
  vi.spyOn(api, 'get').mockResolvedValue({ data: { data: [{ id: 3, url: '/api/v1/media/3', alt_text: 'Alur tabungan menjadi kredit', width: 1200, height: 720 }], total: 1, current_page: 1, last_page: 1 } });
  const remove = vi.spyOn(api, 'delete').mockRejectedValue(new axios.AxiosError('Used', undefined, undefined, undefined, { status: 422, data: { message: 'Gambar masih digunakan oleh artikel.' }, headers: {}, statusText: 'Unprocessable', config: { headers: new axios.AxiosHeaders() } }));
  const user = userEvent.setup(); page(<MediaLibrary />);
  expect(screen.getByLabelText(/File gambar/)).toHaveAttribute('type', 'file');
  expect(screen.getByRole('textbox', { name: /Deskripsi gambar/ })).toBeRequired();
  await user.click(await screen.findByRole('button', { name: 'Hapus gambar #3' }));
  await user.click(within(screen.getByRole('dialog')).getByRole('button', { name: 'Hapus gambar' }));
  await waitFor(() => expect(remove).toHaveBeenCalledWith('/admin/media/3'));
  expect(await screen.findByText('Gambar masih digunakan oleh artikel.')).toBeInTheDocument();
  expect(screen.getByRole('img', { name: 'Alur tabungan menjadi kredit' })).toBeInTheDocument();
});

it('menjelaskan antrean yang menunggu saat pekerja belum terdeteksi dan memperbarui status dari pemeriksaan baru', async () => {
  const snapshot = { database: 'connected', queue: 'database', scheduler: 'unverified', failed_jobs: 0, queued_jobs: 3, google_configured: true, mfa_required: true, worker: { active: false, last_seen_at: null, pending_jobs: 3, reserved_jobs: 0 } };
  const get = vi.spyOn(api, 'get').mockResolvedValue({ data: snapshot });
  const user = userEvent.setup(); page(<HealthPage />);
  expect(await screen.findByText('Pekerja data belum terdeteksi')).toBeInTheDocument();
  expect(screen.getByText(/persentase proses tidak akan bergerak/)).toBeInTheDocument();
  expect(screen.getByText('scripts/start.ps1')).toBeInTheDocument();
  get.mockResolvedValue({ data: { ...snapshot, worker: { active: true, last_seen_at: '2026-11-03T08:00:00+07:00', pending_jobs: 2, reserved_jobs: 1 } } });
  await user.click(screen.getByRole('button', { name: 'Periksa status terbaru' }));
  expect(await screen.findByText('Pekerja data terdeteksi')).toBeInTheDocument();
  expect(screen.queryByText(/persentase proses tidak akan bergerak/)).not.toBeInTheDocument();
  expect(screen.getByText(/Status berjalan berarti terdeteksi dalam dua menit terakhir/)).toBeInTheDocument();
  expect(screen.getByRole('link', { name: 'Buka pusat sinkronisasi' })).toHaveAttribute('href', '/admin/synchronization');
});

it('mempertahankan suntingan selama penyimpanan berjalan dan memakai revisi terbaru untuk simpan berikutnya', async () => {
  const saved = { ...article, id: 9, title: 'Judul draf sebelum disunting', author_id: 4, primary_category_id: 2, cover_media_id: null, excerpt: 'Ringkasan draf pengujian.', body_html: '<p>Isi draf untuk pengujian penyimpanan.</p>', sources: [{ title: 'Sumber', url: 'https://ojk.go.id' }], tags: [], content_type: 'education', seo_title: '', seo_description: '', is_featured: false, revision: 1, status: 'draft' } as Article;
  vi.spyOn(api, 'get').mockImplementation(async url => {
    if (url === '/me') return { data: identity };
    if (url === '/admin/articles/9') return { data: { data: saved, preview_url: '/preview/articles/9?signed=test', can_delete: false } };
    if (url === '/admin/media') return { data: { data: [] } };
    return { data: { data: [] } };
  });
  let finishFirstSave!: (value: { data: { data: Article } }) => void;
  const request = vi.spyOn(api, 'request').mockImplementationOnce(() => new Promise(resolve => { finishFirstSave = resolve; })).mockResolvedValueOnce({ data: { data: { ...saved, revision: 3 } } });
  const client = new QueryClient({ defaultOptions: { queries: { retry: false, staleTime: Infinity }, mutations: { retry: false } } });
  const user = userEvent.setup();
  render(<QueryClientProvider client={client}><MemoryRouter initialEntries={['/admin/articles/9']}><Routes><Route path="/admin/articles/:articleId" element={<ArticleEditor />} /></Routes></MemoryRouter></QueryClientProvider>);
  const title = await screen.findByRole('textbox', { name: 'Judul' });
  await user.clear(title); await user.type(title, 'Judul pada permintaan pertama');
  await user.click(screen.getByRole('button', { name: 'Simpan draf/perubahan' }));
  await waitFor(() => expect(request).toHaveBeenCalledTimes(1));
  await user.clear(title); await user.type(title, 'Judul terbaru yang belum tersimpan');
  await act(async () => { finishFirstSave({ data: { data: { ...saved, title: 'Judul pada permintaan pertama', revision: 2 } } }); });
  expect(title).toHaveValue('Judul terbaru yang belum tersimpan');
  expect(await screen.findByText('Revisi 2 tersimpan. Suntingan terbaru masih perlu disimpan.')).toBeInTheDocument();
  expect(screen.getByText('Draf · belum tersimpan')).toBeInTheDocument();
  await user.click(screen.getByRole('link', { name: 'Pratinjau 15 menit' }));
  expect(screen.getByRole('alert')).toHaveTextContent('Simpan perubahan dahulu');
  await user.click(screen.getByRole('button', { name: 'Simpan draf/perubahan' }));
  await waitFor(() => expect(request).toHaveBeenCalledTimes(2));
  expect(request.mock.calls[0][0]).toMatchObject({ data: { title: 'Judul pada permintaan pertama', revision: 1 } });
  expect(request.mock.calls[1][0]).toMatchObject({ data: { title: 'Judul terbaru yang belum tersimpan', revision: 2 } });
  expect(await screen.findByText('Tersimpan sebagai revisi 3.')).toBeInTheDocument();
  expect(screen.queryByText('Draf · belum tersimpan')).not.toBeInTheDocument();
});

it('membawa suntingan selama simpan pertama ke alamat editor baru tanpa membuat artikel ganda', async () => {
  const saved = { ...article, id: 10, title: 'Judul pada simpan pertama', author_id: 4, primary_category_id: 2, cover_media_id: null, excerpt: 'Ringkasan draf baru.', body_html: '<p>Isi draf baru untuk pengujian.</p>', sources: [{ title: 'Sumber', url: 'https://ojk.go.id' }], tags: [], content_type: 'education', seo_title: '', seo_description: '', is_featured: false, revision: 1, status: 'draft', slug: 'judul-pada-simpan-pertama' } as Article;
  vi.spyOn(api, 'get').mockImplementation(async url => {
    if (url === '/me') return { data: identity };
    if (url === '/admin/articles/10') return { data: { data: saved, preview_url: '/preview/articles/10?signed=test', can_delete: false } };
    return { data: { data: [] } };
  });
  let finishFirstSave!: (value: { data: { data: Article } }) => void;
  const request = vi.spyOn(api, 'request').mockImplementationOnce(() => new Promise(resolve => { finishFirstSave = resolve; })).mockResolvedValueOnce({ data: { data: { ...saved, revision: 2 } } });
  const client = new QueryClient({ defaultOptions: { queries: { retry: false, staleTime: Infinity }, mutations: { retry: false } } });
  const user = userEvent.setup();
  render(<QueryClientProvider client={client}><MemoryRouter initialEntries={['/admin/articles/new']}><Routes><Route path="/admin/articles/new" element={<ArticleEditor />} /><Route path="/admin/articles/:articleId" element={<ArticleEditor />} /></Routes></MemoryRouter></QueryClientProvider>);
  await user.type(screen.getByRole('textbox', { name: 'Judul' }), 'Judul pada simpan pertama');
  await user.click(screen.getByRole('button', { name: 'Simpan draf/perubahan' }));
  await waitFor(() => expect(request).toHaveBeenCalledTimes(1));
  await user.clear(screen.getByRole('textbox', { name: 'Judul' }));
  await user.type(screen.getByRole('textbox', { name: 'Judul' }), 'Judul terbaru saat pembuatan diproses');
  await act(async () => { finishFirstSave({ data: { data: saved } }); });
  expect(await screen.findByRole('heading', { name: 'Editor publikasi' })).toBeInTheDocument();
  expect(screen.getByRole('textbox', { name: 'Judul' })).toHaveValue('Judul terbaru saat pembuatan diproses');
  expect(screen.getByText('Draf · belum tersimpan')).toBeInTheDocument();
  await user.click(screen.getByRole('button', { name: 'Simpan draf/perubahan' }));
  await waitFor(() => expect(request).toHaveBeenCalledTimes(2));
  expect(request.mock.calls[0][0]).toMatchObject({ method: 'post', url: '/admin/articles' });
  expect(request.mock.calls[1][0]).toMatchObject({ method: 'put', url: '/admin/articles/10', data: { title: 'Judul terbaru saat pembuatan diproses', slug: saved.slug, revision: 1 } });
  expect(await screen.findByText('Tersimpan sebagai revisi 2.')).toBeInTheDocument();
});
