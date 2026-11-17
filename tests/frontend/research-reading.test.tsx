import { render, screen, within } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { describe, expect, it } from 'vitest';
import type { Article } from '../../resources/js/components/articles';
import { ResearchFeature, ResearchMethodCard } from '../../resources/js/components/research-reading';

const research = {
  slug: 'membaca-jarak-akses-dan-pemahaman-snlik-2025', title: 'Pertanyaan baru dari penulis', excerpt: 'Pembahasan baru dengan cakupan dan metode yang sudah diperbarui.',
  author: { public_name: 'Penulis Uji', slug: 'penulis-uji' }, reading_minutes: 5, content_type: 'research',
  sources: [{ title: 'Referensi baru penulis', url: 'https://example.test/referensi' }], toc: [{ id: 'metode-baru', text: 'Metode desain baru', level: 2 }],
} as Article;

describe('sorotan riset mengikuti profil isi yang disahkan server', () => {
  it('tidak mengambil fakta lama dari slug ketika profil terverifikasi tidak diberikan', () => {
    render(<MemoryRouter><ResearchFeature article={research} /><ResearchMethodCard article={research} /></MemoryRouter>);
    expect(screen.queryByText('5,49 pp')).not.toBeInTheDocument();
    expect(screen.queryByText('Apakah penggunaan layanan dan pemahaman keuangan bergerak seiring?')).not.toBeInTheDocument();
    expect(within(screen.getByRole('article')).getByRole('heading', { name: research.title })).toBeInTheDocument();
    expect(screen.getByText(research.excerpt)).toBeInTheDocument();
    expect(screen.getByText('1 referensi dicantumkan penulis.', { exact: false })).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Baca bagian “Metode desain baru” dalam naskah.' })).toHaveAttribute('href', '#metode-baru');
  });

  it('menampilkan angka terkurasi hanya melalui kunci profil yang diberikan server', () => {
    render(<MemoryRouter><ResearchFeature article={{ ...research, slug: 'url-yang-telah-diubah', research_profile_slug: 'membaca-jarak-akses-dan-pemahaman-snlik-2025' }} /></MemoryRouter>);
    expect(screen.getByText('5,49 pp')).toBeInTheDocument();
    expect(screen.getByText('1,03 pp')).toBeInTheDocument();
    expect(screen.getByRole('link', { name: research.title })).toHaveAttribute('href', '/artikel/url-yang-telah-diubah');
  });
});
