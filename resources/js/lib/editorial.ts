export function articleSlug(title: string): string {
  return title.normalize('NFKD').replace(/[\u0300-\u036f]/g, '').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 180).replace(/-+$/g, '');
}

export const publicationStatus: Record<string, string> = { draft: 'Draf', in_review: 'Dalam peninjauan', scheduled: 'Terjadwal', published: 'Terbit', archived: 'Diarsipkan' };
