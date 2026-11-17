import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { act, fireEvent, render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import axios from 'axios';
import { MemoryRouter } from 'react-router-dom';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { MediaLibrary } from '../../resources/js/components/content-admin';
import { api } from '../../resources/js/lib/api';

vi.mock('@tiptap/react', () => ({ useEditor: () => null, EditorContent: () => null }));

function library() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false, staleTime: Infinity }, mutations: { retry: false } } });
  render(<QueryClientProvider client={client}><MemoryRouter><MediaLibrary /></MemoryRouter></QueryClientProvider>);
  return client;
}

const image = () => new File(['image content'], 'ilustrasi.png', { type: 'image/png' });
const media = (id: number, alt: string) => ({ id, alt_text: alt, url: `/api/v1/media/${id}`, width: 1200, height: 720 });

beforeEach(() => {
  vi.restoreAllMocks();
  vi.spyOn(axios, 'get').mockResolvedValue({ status: 204 });
  vi.spyOn(api, 'get').mockResolvedValue({ data: { data: [], current_page: 1, last_page: 1, total: 0 } });
});

describe('unggahan pustaka media', () => {
  it('menunggu berkas dan deskripsi yang bermakna sebelum mengirim unggahan', async () => {
    const post = vi.spyOn(api, 'post').mockResolvedValue({ status: 201 });
    const user = userEvent.setup(); library();
    const submit = screen.getByRole('button', { name: 'Unggah gambar' });
    expect(submit).toBeDisabled();
    await user.type(screen.getByRole('textbox', { name: 'Deskripsi gambar' }), '   ');
    await user.upload(screen.getByLabelText('File gambar'), image());
    expect(submit).toBeDisabled();
    await user.click(submit);
    expect(post).not.toHaveBeenCalled();
    expect(axios.get).not.toHaveBeenCalled();
    await user.clear(screen.getByRole('textbox', { name: 'Deskripsi gambar' }));
    await user.type(screen.getByRole('textbox', { name: 'Deskripsi gambar' }), 'Alur tabungan menjadi kredit');
    expect(submit).toBeEnabled();
  });

  it('kembali ke halaman pertama, memperbarui pilihan editor, dan mengosongkan berkas setelah unggahan berhasil', async () => {
    let uploaded = false;
    const get = vi.mocked(api.get).mockImplementation(async (_url, options) => {
      const page = (options?.params as { page?: number } | undefined)?.page ?? 1;
      return { data: { data: [media(uploaded ? 3 : page, uploaded ? 'Ilustrasi baru' : `Ilustrasi halaman ${page}`)], current_page: page, last_page: 2, total: uploaded ? 3 : 2 } };
    });
    let finishUpload!: (value: { status: number }) => void;
    const post = vi.spyOn(api, 'post').mockImplementation(() => new Promise(resolve => { finishUpload = resolve; }));
    const user = userEvent.setup(); const client = library();
    client.setQueryData(['admin', 'media'], { data: [media(1, 'Pilihan editor lama')] });
    await user.click(await screen.findByRole('button', { name: 'Berikutnya' }));
    expect(await screen.findByRole('img', { name: 'Ilustrasi halaman 2' })).toBeInTheDocument();
    const fileInput = screen.getByLabelText('File gambar') as HTMLInputElement;
    const file = image();
    await user.upload(fileInput, file);
    await user.type(screen.getByRole('textbox', { name: 'Deskripsi gambar' }), '  Ilustrasi baru  ');
    expect(fileInput.files).toHaveLength(1);
    fireEvent.submit(fileInput.closest('form')!);
    await waitFor(() => expect(post).toHaveBeenCalledTimes(1));
    expect(screen.getByRole('button', { name: 'Memproses…' })).toBeDisabled();
    expect(post.mock.calls[0][0]).toBe('/admin/media');
    const payload = post.mock.calls[0][1] as FormData;
    expect(payload.get('file')).toBe(file);
    expect(payload.get('alt_text')).toBe('Ilustrasi baru');
    uploaded = true;
    await act(async () => finishUpload({ status: 201 }));
    expect(await screen.findByRole('status')).toHaveTextContent('Gambar siap digunakan.');
    expect(await screen.findByRole('img', { name: 'Ilustrasi baru' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Sebelumnya' })).toBeDisabled();
    expect(get).toHaveBeenLastCalledWith('/admin/media', { params: { page: 1 } });
    expect(client.getQueryState(['admin', 'media'])?.isInvalidated).toBe(true);
    expect(client.getQueryData(['admin', 'media', 2])).toMatchObject({ data: [{ id: 3 }] });
    expect(screen.getByRole('textbox', { name: 'Deskripsi gambar' })).toHaveValue('');
    expect(fileInput.files).toHaveLength(0);
    expect(fileInput).toHaveValue('');
    expect(screen.getByRole('button', { name: 'Unggah gambar' })).toBeDisabled();
  });

  it('mempertahankan berkas dan deskripsi ketika gagal sehingga pengguna dapat mencoba lagi', async () => {
    const rejected = new axios.AxiosError('Upload rejected', undefined, undefined, undefined, { status: 422, data: { message: 'Gambar belum dapat diproses. Coba unggah kembali.' }, headers: {}, statusText: 'Unprocessable', config: { headers: new axios.AxiosHeaders() } });
    const post = vi.spyOn(api, 'post').mockRejectedValueOnce(rejected).mockResolvedValueOnce({ status: 201 });
    const user = userEvent.setup(); library();
    const fileInput = screen.getByLabelText('File gambar') as HTMLInputElement;
    const file = image();
    await user.upload(fileInput, file);
    await user.type(screen.getByRole('textbox', { name: 'Deskripsi gambar' }), 'Ilustrasi yang perlu dicoba lagi');
    fireEvent.submit(fileInput.closest('form')!);
    expect(await screen.findByRole('alert')).toHaveTextContent('Gambar belum dapat diproses. Coba unggah kembali.');
    expect(screen.queryByRole('status')).not.toBeInTheDocument();
    expect(fileInput.files?.[0]).toBe(file);
    expect(screen.getByRole('textbox', { name: 'Deskripsi gambar' })).toHaveValue('Ilustrasi yang perlu dicoba lagi');
    expect(screen.getByRole('button', { name: 'Unggah gambar' })).toBeEnabled();
    fireEvent.submit(fileInput.closest('form')!);
    expect(await screen.findByRole('status')).toHaveTextContent('Gambar siap digunakan.');
    expect(post).toHaveBeenCalledTimes(2);
    expect(screen.queryByRole('alert')).not.toBeInTheDocument();
    expect(fileInput.files).toHaveLength(0);
  });

  it('menolak berkas di atas 5 MB sebelum meminta jaringan dan dapat dilanjutkan dengan berkas yang lebih kecil', async () => {
    const post = vi.spyOn(api, 'post').mockResolvedValue({ status: 201 });
    const user = userEvent.setup(); library();
    const fileInput = screen.getByLabelText('File gambar');
    await user.upload(fileInput, new File([new Uint8Array(5 * 1024 * 1024 + 1)], 'terlalu-besar.png', { type: 'image/png' }));
    await user.type(screen.getByRole('textbox', { name: 'Deskripsi gambar' }), 'Gambar berukuran besar');
    fireEvent.submit(fileInput.closest('form')!);
    expect(await screen.findByRole('alert')).toHaveTextContent('Ukuran gambar maksimal 5 MB.');
    expect(post).not.toHaveBeenCalled();
    expect(axios.get).not.toHaveBeenCalled();
    await user.upload(fileInput, image());
    expect(screen.queryByRole('alert')).not.toBeInTheDocument();
    expect(screen.getByRole('textbox', { name: 'Deskripsi gambar' })).toHaveValue('Gambar berukuran besar');
    fireEvent.submit(fileInput.closest('form')!);
    expect(await screen.findByRole('status')).toHaveTextContent('Gambar siap digunakan.');
    expect(post).toHaveBeenCalledTimes(1);
  });
});
