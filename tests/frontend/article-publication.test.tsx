import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { act, render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import axios from 'axios';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { ArticleEditor } from '../../resources/js/components/content-admin';
import { type Article } from '../../resources/js/components/articles';
import { api, type Identity } from '../../resources/js/lib/api';

vi.mock('@tiptap/react', () => ({ useEditor: () => null, EditorContent: () => null }));

const identity: Identity = { authenticated: true, google_configured: true, admin_mfa_required: false, admin_mfa_verified: true, user: { id: 4, name: 'Editor uji', avatar_url: null, roles: ['admin'], permissions: ['admin.access', 'articles.publish'] } };
const saved: Article = { id: 9, slug: 'publikasi-uji', title: 'Publikasi untuk uji status', author_id: 4, primary_category_id: 2, cover_media_id: null, excerpt: 'Ringkasan pengujian.', body_html: '<p>Isi artikel uji.</p>', sources: [{ title: 'Sumber OJK', url: 'https://ojk.go.id' }], tags: [], content_type: 'education', seo_title: '', seo_description: '', is_featured: false, allow_comments: true, revision: 1, status: 'draft', author: { id: 4, slug: 'editor-uji', public_name: 'Editor uji', bio: '' }, category: { id: 2, slug: 'baca-data', name: 'Baca data' }, cover: null, published_at: null, updated_at: '2026-11-03T08:00:00+07:00', scheduled_at: null, reading_minutes: 1, toc: [], url: '/artikel/publikasi-uji' };
const record = (status = 'draft') => ({ data: { ...saved, status }, preview_url: '/preview/articles/9?signed=test', can_delete: true });

function editor(readArticle: () => Promise<{ data: ReturnType<typeof record> }>) {
  vi.spyOn(api, 'get').mockImplementation(async url => {
    if (url === '/me') return { data: identity };
    if (url === '/admin/articles/9') return readArticle();
    if (url === '/admin/articles/9/revisions') return { data: { data: [{ id: 11, version: 1, change_note: 'Draf pertama', created_at: '2026-11-03T08:00:00+07:00' }] } };
    return { data: { data: [] } };
  });
  const client = new QueryClient({ defaultOptions: { queries: { retry: false, staleTime: Infinity }, mutations: { retry: false } } });
  render(<QueryClientProvider client={client}><MemoryRouter initialEntries={['/admin/articles/9']}><Routes><Route path="/admin/articles/:articleId" element={<ArticleEditor />} /></Routes></MemoryRouter></QueryClientProvider>);
  return client;
}

beforeEach(() => {
  vi.restoreAllMocks();
  Object.defineProperties(HTMLDialogElement.prototype, { showModal: { configurable: true, value() { this.setAttribute('open', ''); } }, close: { configurable: true, value() { this.removeAttribute('open'); } } });
  vi.spyOn(axios, 'get').mockResolvedValue({ status: 204 });
});

describe('perubahan status publikasi', () => {
  it('mengunci tindakan sampai status dan daftar diperbarui, tanpa pesan berhasil saat lencana masih draf', async () => {
    let finishStatus!: (value: { status: number }) => void;
    let finishRefresh!: (value: { data: ReturnType<typeof record> }) => void;
    let finishLists!: () => void;
    const read = vi.fn().mockResolvedValueOnce({ data: record() }).mockImplementationOnce(() => new Promise(resolve => { finishRefresh = resolve; }));
    const post = vi.spyOn(api, 'post').mockImplementation(() => new Promise(resolve => { finishStatus = resolve; }));
    const save = vi.spyOn(api, 'request');
    const remove = vi.spyOn(api, 'delete');
    const user = userEvent.setup(); const client = editor(read);
    const listRefresh = new Promise<void>(resolve => { finishLists = resolve; });
    const invalidate = vi.spyOn(client, 'invalidateQueries').mockReturnValue(listRefresh);
    await user.click(await screen.findByRole('button', { name: 'Publikasikan sekarang' }));
    expect(screen.getByRole('button', { name: 'Tarik ke draf' })).toBeDisabled();
    await user.click(within(screen.getByRole('dialog')).getByRole('button', { name: 'Batal' }));
    expect(screen.getByRole('button', { name: 'Publikasikan sekarang' })).toBeEnabled();
    expect(post).not.toHaveBeenCalled();
    await user.click(screen.getByRole('button', { name: 'Publikasikan sekarang' }));
    await user.click(within(screen.getByRole('dialog')).getByRole('button', { name: 'Terbitkan artikel' }));
    await waitFor(() => expect(post).toHaveBeenCalledTimes(1));
    expect(post).toHaveBeenCalledWith('/admin/articles/9/status', { status: 'published', scheduled_at: null });
    expect(screen.getByRole('status')).toHaveTextContent('Memperbarui status publikasi…');
    expect(screen.getByText('Draf', { exact: true })).toBeInTheDocument();
    for (const name of ['Publikasikan sekarang', 'Tarik ke draf', 'Ajukan peninjauan', 'Jadwalkan', 'Arsipkan', 'Simpan draf/perubahan', 'Hapus artikel', 'Pulihkan']) {
      expect(screen.getByRole('button', { name })).toBeDisabled();
      await user.click(screen.getByRole('button', { name }));
    }
    expect(screen.getByLabelText('Unggah cover')).toBeDisabled();
    expect(screen.getByLabelText('Sisipkan gambar')).toBeDisabled();
    expect(post).toHaveBeenCalledTimes(1);
    expect(save).not.toHaveBeenCalled(); expect(remove).not.toHaveBeenCalled();
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    await act(async () => finishStatus({ status: 200 }));
    await waitFor(() => expect(read).toHaveBeenCalledTimes(2));
    expect(screen.queryByText('Status diubah menjadi terbit.')).not.toBeInTheDocument();
    expect(screen.getByText('Draf', { exact: true })).toBeInTheDocument();
    await act(async () => finishRefresh({ data: record('published') }));
    expect(await screen.findByText('Terbit', { exact: true })).toBeInTheDocument();
    expect(screen.queryByText('Status diubah menjadi terbit.')).not.toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Publikasikan sekarang' })).toBeDisabled();
    expect(invalidate).toHaveBeenCalledTimes(3);
    await act(async () => finishLists());
    expect(await screen.findByText('Status diubah menjadi terbit.')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Tarik ke draf' })).toBeEnabled();
  });

  it('menampilkan penolakan server tanpa pesan berhasil dan membersihkan kesalahan saat mencoba lagi', async () => {
    const failure = new axios.AxiosError('Rejected', undefined, undefined, undefined, { status: 422, data: { message: 'Artikel harus memiliki sumber sebelum diterbitkan.' }, headers: {}, statusText: 'Unprocessable', config: { headers: new axios.AxiosHeaders() } });
    const post = vi.spyOn(api, 'post').mockRejectedValueOnce(failure).mockResolvedValueOnce({ status: 200 });
    const read = vi.fn().mockResolvedValueOnce({ data: record() }).mockResolvedValue({ data: record('published') });
    const user = userEvent.setup(); editor(read);
    await user.click(await screen.findByRole('button', { name: 'Publikasikan sekarang' }));
    await user.click(within(screen.getByRole('dialog')).getByRole('button', { name: 'Terbitkan artikel' }));
    expect(await screen.findByRole('alert')).toHaveTextContent('Artikel harus memiliki sumber sebelum diterbitkan.');
    expect(screen.queryByText('Status diubah menjadi terbit.')).not.toBeInTheDocument();
    expect(screen.getByText('Draf', { exact: true })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Publikasikan sekarang' })).toBeEnabled();
    await user.click(screen.getByRole('button', { name: 'Publikasikan sekarang' }));
    expect(screen.queryByRole('alert')).not.toBeInTheDocument();
    await user.click(within(screen.getByRole('dialog')).getByRole('button', { name: 'Terbitkan artikel' }));
    expect(await screen.findByText('Status diubah menjadi terbit.')).toBeInTheDocument();
    expect(post).toHaveBeenCalledTimes(2);
  });

  it('meminta pemeriksaan status bila pembaruan diterima tetapi pengambilan status gagal, tanpa mengirim ulang publikasi', async () => {
    const read = vi.fn().mockResolvedValueOnce({ data: record() }).mockRejectedValueOnce(new Error('Connection lost')).mockResolvedValue({ data: record('published') });
    const post = vi.spyOn(api, 'post').mockResolvedValue({ status: 200 });
    const user = userEvent.setup(); editor(read);
    await user.click(await screen.findByRole('button', { name: 'Publikasikan sekarang' }));
    await user.click(within(screen.getByRole('dialog')).getByRole('button', { name: 'Terbitkan artikel' }));
    expect(await screen.findByRole('alert')).toHaveTextContent('status terbaru belum dapat diperiksa');
    expect(screen.queryByText('Status diubah menjadi terbit.')).not.toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Publikasikan sekarang' })).toBeDisabled();
    expect(screen.getByRole('button', { name: 'Simpan draf/perubahan' })).toBeDisabled();
    expect(screen.getByRole('textbox', { name: 'Judul' })).toHaveValue(saved.title);
    await user.click(screen.getByRole('button', { name: 'Periksa status terbaru' }));
    expect(await screen.findByText('Terbit', { exact: true })).toBeInTheDocument();
    expect(screen.queryByRole('alert')).not.toBeInTheDocument();
    expect(screen.queryByText('Status diubah menjadi terbit.')).not.toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Simpan draf/perubahan' })).toBeEnabled();
    expect(screen.queryByRole('button', { name: 'Periksa status terbaru' })).not.toBeInTheDocument();
    expect(post).toHaveBeenCalledTimes(1);
  });
});
