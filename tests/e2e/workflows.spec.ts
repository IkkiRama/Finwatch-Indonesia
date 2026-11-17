import { expect, test, type Page, type TestInfo } from '@playwright/test';
import { expectFooter, expectNoViewportOverflow, installAdminUiFixture, installIdentityFixture, paginator } from './fixtures';

const category = { id: 99001, name: 'Literasi Fixture', slug: 'literasi-fixture', description: 'Kategori khusus pengujian browser.' };
const author = { id: 99001, user_id: 99001, slug: 'penulis-fixture', public_name: 'Penulis Fixture', bio: 'Identitas pengujian browser, tidak tersimpan dalam aplikasi.' };
const fixtureArticle = {
  id: 99001, title: 'Memahami periode, sumber, dan rasio', slug: 'fixture-indikator', excerpt: 'Bacaan fixture untuk memeriksa pengalaman membaca dan diskusi.',
  author, category, author_id: author.id, primary_category_id: category.id, cover: null, cover_media_id: null,
  tags: ['pengujian'], content_type: 'education', status: 'published', published_at: '2025-09-05T08:00:00+07:00', updated_at: '2025-09-06T08:00:00+07:00',
  scheduled_at: null, reading_minutes: 2, revision: 1, allow_comments: true, is_featured: false, seo_title: 'Bacaan fixture FINWATCH', seo_description: 'Halaman pengujian.',
  body_html: '<h2 id="konteks">Konteks publikasi</h2><p>Periode observasi berbeda dari waktu penerbitan. Periksa satuan dan sumber setiap indikator sebelum membuat perbandingan.</p>',
  sources: [{ title: 'OJK', url: 'https://ojk.go.id' }], toc: [{ id: 'konteks', text: 'Konteks publikasi', level: 2 }], url: '/artikel/fixture-indikator',
};

async function checkWidths(page: Page, name: string, testInfo: TestInfo) {
  for (const width of [320, 768, 1440]) {
    await page.setViewportSize({ width, height: 900 });
    await expectNoViewportOverflow(page);
    await page.evaluate(() => { if (document.activeElement instanceof HTMLElement) document.activeElement.blur(); window.scrollTo({ top: 0, behavior: 'instant' }); });
    await page.screenshot({ path: testInfo.outputPath(`${name}-${width}.png`), fullPage: true });
  }
}

test('published official snapshots contain 108 observations with real source methods', async ({ request }) => {
  const overview = await (await request.get('/api/v1/overview')).json();
  expect(overview.observations).toBe(108);
  expect(overview.published_datasets).toBe(4);
  expect(overview.years).toEqual([2025, 2024]);
  const sources = await (await request.get('/api/v1/sources')).json();
  expect(sources.data).toHaveLength(5);
  expect(sources.data.find((source: { code: string }) => source.code === 'OJK').method).toBe('official_file');
  expect(sources.data.filter((source: { method: string }) => source.method === 'manual')).toHaveLength(4);
});

test('stress scenarios use the real CSRF-protected calculator and compare two results', async ({ page }, testInfo) => {
  const errors: string[] = []; page.on('pageerror', error => errors.push(error.message));
  await page.route('**/api/v1/stress-scenarios', route => route.fulfill({ json: { data: [{
    id: 99001, name: 'Sensitivitas fixture hipotetis', parameters: { credit: 1000, npl: 2, car: 20, npl_shock_pp: 3, lgd_percent: 50 },
    methodology: 'Asumsi hipotetis khusus tes. Satuan kredit konsisten; bukan observasi resmi.',
  }] } }));
  await page.goto('/simulasi-stres');
  await page.getByText('Skenario tambahan dari pengelola', { exact: true }).click();
  await page.getByRole('combobox', { name: 'Skenario terpublikasi' }).selectOption('99001');
  await expect(page.locator('.lab-saved-scenarios')).toContainText('Asumsi hipotetis khusus tes.');
  const firstResponse = page.waitForResponse(response => response.url().endsWith('/api/v1/stress-tests') && response.request().method() === 'POST');
  await page.getByRole('button', { name: 'Hitung sensitivitas' }).click();
  const first = await firstResponse; expect(first.status()).toBe(200);
  const result = (await first.json()).data;
  expect(result.additional_loss).toBe(15); expect(result.car_after).toBe(18.5); expect(result.npl_after).toBe(5);
  await expect(page.locator('.lab-capital-change')).toContainText('18,5%');
  await page.getByLabel('Kenaikan NPL (pp)', { exact: true }).fill('6');
  await page.getByRole('button', { name: 'Hitung sensitivitas' }).click();
  await expect(page.locator('.lab-capital-change')).toContainText('17%');
  await expect(page.getByRole('region', { name: 'Perbandingan skenario hipotetis' })).toContainText('18,5');
  await checkWidths(page, 'stress', testInfo);
  await page.getByRole('button', { name: 'Reset', exact: true }).click();
  await expect(page.getByRole('heading', { name: 'Seberapa besar modal terpengaruh?' })).toBeVisible();
  expect(errors).toEqual([]);
});

test('explorer filters official NPL observations and exports filtered CSV', async ({ page }, testInfo) => {
  const errors: string[] = []; page.on('pageerror', error => errors.push(error.message));
  await page.goto('/jelajah-data');
  await expect(page.getByText('108 observasi', { exact: false })).toBeVisible();
  await page.getByRole('combobox', { name: 'Indikator', exact: true }).selectOption('npl_gross');
  await expect(page.locator('tbody tr')).toHaveCount(1);
  await expect(page.locator('tbody tr')).toContainText('2,22');
  await expect(page.locator('tbody tr')).toContainText('2025-06-30');
  await page.getByRole('combobox', { name: 'Tahun', exact: true }).selectOption('2025');
  const downloadPromise = page.waitForEvent('download');
  await page.getByRole('link', { name: 'Unduh CSV', exact: true }).click();
  const download = await downloadPromise;
  expect(download.suggestedFilename()).toBe('finwatch-observations.csv');
  const stream = await download.createReadStream();
  expect(stream).not.toBeNull();
  let csv = ''; for await (const bytes of stream!) { csv += bytes.toString(); }
  expect(csv).toContain('source_url,release_date,retrieved_at,dataset_version');
  expect(csv).toContain('npl_gross'); expect(csv).toContain('2.22000000');
  expect(csv).not.toContain('appk_services');
  await checkWidths(page, 'explorer', testInfo);
  expect(errors).toEqual([]);
});

test('inclusion separates national survey groups from source-backed historical provincial map', async ({ page }, testInfo) => {
  const errors: string[] = []; page.on('pageerror', error => errors.push(error.message));
  await page.goto('/inklusi-dan-konsumen');
  await expect(page.getByRole('heading', { level: 1 })).toContainText('Sudah menggunakan.');
  await page.locator('.inclusion-gap-note').scrollIntoViewIfNeeded();
  await expect(page.locator('.inclusion-gap-note')).toContainText('14,05');
  await expect(page.locator('.inclusion-gap-note')).toContainText('Selisih indeks bukan jumlah orang');
  await expect(page.getByRole('button', { name: 'Perkotaan, literasi 70,89 persen' })).toBeVisible();
  await expect(page.getByRole('button', { name: 'Perdesaan, inklusi 75,7 persen' })).toBeVisible();
  await page.locator('.consumer-statistics').scrollIntoViewIfNeeded();
  await expect(page.locator('.consumer-statistics article')).toHaveCount(4);
  await expect(page.locator('.consumer-statistics')).toContainText('318.908');
  await expect(page.locator('.consumer-statistics')).toContainText('31.456');
  await expect(page.locator('.consumer-statistics')).toContainText('2025-08-15');
  await expect(page.locator('.inclusion-map')).toHaveCount(0);
  await expect(page.locator('.survey-context')).toContainText('arsip survei 2022');
  await page.locator('.province-explorer').scrollIntoViewIfNeeded();
  await expect(page.locator('.province-explorer')).toContainText('Arsip SNLIK 2022');
  await page.getByRole('combobox', { name: 'Pilih provinsi', exact: true }).selectOption('17');
  await expect(page.locator('.province-detail')).toContainText('Bengkulu');
  await expect(page.locator('.province-detail')).toContainText('88,05');
  await page.locator('.province-metric-toggle').getByRole('button', { name: 'Memahami keuangan', exact: true }).click();
  await expect(page.locator('.province-detail')).toContainText('Literasi nasional 2022: 49,68%');
  await page.getByRole('button', { name: 'Lihat semua angka provinsi' }).click();
  await expect(page.locator('.province-numbers tbody tr')).toHaveCount(34);
  await checkWidths(page, 'inclusion', testInfo);
  await page.getByRole('combobox', { name: 'Tahun observasi' }).selectOption('2024');
  await page.locator('.inclusion-gap-note').scrollIntoViewIfNeeded();
  await expect(page.locator('.inclusion-gap-note')).toContainText('9,59');
  await expect(page.getByRole('heading', { name: 'Belum ada data konsumen untuk tahun ini' })).toBeVisible();
  expect(errors).toEqual([]);
});

test('banking and research signals remain usable at all target widths', async ({ page }, testInfo) => {
  const errors: string[] = []; page.on('pageerror', error => errors.push(error.message));
  await page.goto('/perbankan');
  await expect(page.getByRole('heading', { level: 1 })).toHaveText('Bank bukan cuma tempat menabung.');
  await expect(page.locator('.series-panel')).toHaveCount(1);
  await expect(page.getByRole('button', { name: /Kredit/ }).first()).toBeVisible();
  await checkWidths(page, 'banking', testInfo);
  await page.goto('/peringatan-dini');
  await expect(page.getByRole('heading', { name: 'Untuk apa peringatan dini?' })).toBeVisible();
  await expect(page.locator('.warning-rule')).toHaveCount(1);
  await page.getByText('Telusuri cara pemeriksaan dan sumber', { exact: true }).click();
  await expect(page.locator('.warning-rule')).toContainText('Aturan v1');
  await page.getByRole('combobox', { name: 'Tahun observasi' }).selectOption('2024');
  await expect(page.locator('.warning-rule')).toContainText('Desember 2024');
  await checkWidths(page, 'research-signals', testInfo);
  expect(errors).toEqual([]);
});

test('article reading, share links, threaded comments and pending submissions work with browser fixtures', async ({ page, request }, testInfo) => {
  const errors: string[] = []; page.on('pageerror', error => errors.push(error.message));
  const document = await request.get('/');
  await page.route('**/artikel/fixture-indikator', route => route.fulfill({ response: document }));
  await installIdentityFixture(page, ['comments.create']);
  await page.route('**/api/v1/articles/fixture-indikator', route => route.fulfill({ json: { data: fixtureArticle, related: [] } }));
  const root = { id: 99100, article_id: fixtureArticle.id, parent_id: null, body: 'Komentar pengujian dengan konteks sumber.', user: { id: 99002, name: 'Pembaca Fixture', avatar_url: null }, deleted: false, status: 'approved', depth: 0, locked: false, likes: 0, liked: false, replies_count: 1, created_at: '2025-09-06T08:00:00+07:00', edited_at: null, can_edit: false, can_moderate: false };
  const reply = { ...root, id: 99101, parent_id: root.id, depth: 1, replies_count: 0, body: 'Balasan fixture yang dapat dibuka dan diciutkan.' };
  let submitted = '';
  await page.route(url => url.pathname === `/api/v1/articles/${fixtureArticle.id}/comments`, async route => {
    if (route.request().method() === 'POST') { submitted = route.request().postDataJSON().body; await route.fulfill({ status: 201, json: { message: 'Komentar menunggu moderasi.', data: { id: 99102 } } }); }
    else { await route.fulfill({ json: { data: [root], next_cursor: null, has_more: false } }); }
  });
  await page.route('**/api/v1/comments/99100/replies?*', route => route.fulfill({ json: { data: [reply], next_cursor: null, has_more: false } }));
  await page.route('**/api/v1/comments/99100/likes', async route => { root.likes = 1; root.liked = true; await route.fulfill({ json: { likes: 1 } }); });
  await page.route('**/api/v1/comments/99100/reports', route => route.fulfill({ json: { message: 'Laporan diterima.' } }));
  await page.route('**/api/v1/comments/99101/context', route => route.fulfill({ json: { data: [root, reply] } }));
  await page.goto('/artikel/fixture-indikator');
  await expect(page.getByRole('heading', { level: 1 })).toHaveText(fixtureArticle.title);
  await expect(page).toHaveTitle('Bacaan fixture FINWATCH | FINWATCH Indonesia');
  await expect(page.locator('.prose-finwatch h2')).toHaveText('Konteks publikasi');
  await expect(page.getByRole('link', { name: 'Unduh PDF publikasi' })).toHaveAttribute('href', '/artikel/fixture-indikator/pdf');
  for (const name of ['WhatsApp', 'Facebook', 'X', 'Telegram', 'LinkedIn']) {
    const link = page.getByRole('region', { name: 'Berbagi artikel' }).getByRole('link', { name, exact: true });
    await expect(link).toHaveAttribute('target', '_blank');
    await expect(link).toHaveAttribute('href', /fixture-indikator/);
  }
  await page.getByRole('button', { name: 'Lihat 1 balasan' }).click();
  await expect(page.locator('#komentar-99101')).toContainText(reply.body);
  await page.locator('#komentar-99100 > .comment-actions').getByRole('button', { name: '0 Suka' }).click();
  await expect(page.locator('#komentar-99100 > .comment-actions').getByRole('button', { name: '1 Suka' })).toHaveAttribute('aria-pressed', 'true');
  await page.getByLabel('Apa yang ingin kamu tambahkan?').fill('Komentar baru khusus fixture browser.');
  await page.getByRole('button', { name: 'Kirim untuk ditinjau', exact: true }).click();
  await expect(page.getByText('Komentar menunggu moderasi.', { exact: true })).toBeVisible();
  expect(submitted).toBe('Komentar baru khusus fixture browser.');
  await expect(page.locator('.comment-thread')).toHaveCount(2);
  await page.locator('#komentar-99100 > .comment-actions').getByRole('button', { name: 'Laporkan' }).click();
  await page.getByLabel('Apa yang perlu diperiksa?').fill('Alasan khusus pengujian untuk pemeriksaan moderator.');
  await page.getByRole('button', { name: 'Kirim laporan', exact: true }).click();
  await expect(page.getByText('Laporan diterima. Moderator akan meninjaunya.', { exact: true })).toBeVisible();
  await page.evaluate(() => { window.location.hash = 'komentar-99101'; });
  await expect(page.locator('.linked-comment')).toContainText('Konteks tautan komentar');
  await expect(page.locator('.linked-comment')).toContainText(reply.body);
  await checkWidths(page, 'article-thread', testInfo); await expectFooter(page);
  expect(errors).toEqual([]);
});

test('admin editor saves revisions and publication transitions through browser fixtures', async ({ page, request }, testInfo) => {
  const errors: string[] = []; page.on('pageerror', error => errors.push(error.message));
  await installAdminUiFixture(page, request);
  const article = { ...fixtureArticle, status: 'draft', published_at: null };
  let savedTitle = ''; let savedBody = ''; let transition = '';
  await page.route('**/api/v1/categories', route => route.fulfill({ json: { data: [category] } }));
  await page.route('**/api/v1/authors', route => route.fulfill({ json: { data: [author] } }));
  await page.route('**/api/v1/admin/media', route => route.fulfill({ json: paginator([]) }));
  await page.route(`**/api/v1/admin/articles/${article.id}`, async route => {
    if (route.request().method() === 'PUT') { const input = route.request().postDataJSON(); savedTitle = input.title; savedBody = input.body_html; article.title = input.title; article.body_html = input.body_html; article.revision += 1; }
    await route.fulfill({ json: { data: article, preview_url: `/preview/articles/${article.id}?fixture=browser-only` } });
  });
  await page.route(`**/api/v1/admin/articles/${article.id}/revisions`, route => route.fulfill({ json: { data: [{ id: 99101, version: article.revision, change_note: 'Fixture browser', created_at: article.updated_at }] } }));
  await page.route(`**/api/v1/admin/articles/${article.id}/status`, async route => { transition = route.request().postDataJSON().status; article.status = transition; await route.fulfill({ json: { data: article } }); });
  await page.goto(`/admin/articles/${article.id}`);
  await expect(page.getByRole('heading', { level: 1 })).toHaveText('Editor publikasi');
  await page.getByLabel('Judul', { exact: true }).fill('Judul hasil penyuntingan fixture');
  await page.locator('[contenteditable="true"][aria-label="Isi artikel"]').fill('Paragraf fixture yang disimpan melalui editor Tiptap.');
  await page.getByRole('button', { name: 'Simpan draf/perubahan', exact: true }).click();
  await expect(page.getByText('Tersimpan sebagai revisi 2.', { exact: true })).toBeVisible();
  expect(savedTitle).toBe('Judul hasil penyuntingan fixture'); expect(savedBody).toContain('Paragraf fixture');
  await expect(page.getByRole('link', { name: 'Pratinjau 15 menit' })).toHaveAttribute('href', /preview\/articles\/99001/);
  await page.getByRole('button', { name: 'Publikasikan sekarang' }).click();
  await page.getByRole('dialog', { name: 'Terbitkan artikel ini?' }).getByRole('button', { name: 'Terbitkan artikel' }).click();
  await expect(page.getByText('Status diubah menjadi terbit.', { exact: true })).toBeVisible();
  expect(transition).toBe('published');
  await checkWidths(page, 'editor', testInfo);
  expect(errors).toEqual([]);
});

test('admin synchronization displays queued work, truthful outcomes and logs from fixture responses', async ({ page, request }, testInfo) => {
  const errors: string[] = []; page.on('pageerror', error => errors.push(error.message));
  await installAdminUiFixture(page, request);
  const sources = (await (await request.get('/api/v1/sources')).json()).data as { id: number; code: string; name: string }[];
  let queued = false; let reads = 0;
  await page.route(url => url.pathname === '/api/v1/admin/sync/runs', async route => {
    if (route.request().method() === 'POST') { queued = true; await route.fulfill({ status: 202, json: { data: [] } }); return; }
    reads += queued ? 1 : 0;
    const finished = reads > 1;
    await route.fulfill({ json: paginator(queued ? sources.map((source, index) => ({ id: 99200 + index, source, status: finished ? source.code === 'OJK' ? 'no_new_data' : 'manual_required' : 'queued', progress: finished ? 100 : 0, attempt: 1, created_at: '2026-10-26T01:00:00+00:00', logs: [{ at: '2026-10-26T01:00:00+00:00', status: finished ? 'finished' : 'queued', message: finished ? source.code === 'OJK' ? 'No New Data: checksum sumber tidak berubah.' : 'Gunakan impor manual dari publikasi asli.' : 'Pekerjaan menunggu worker.' }] })) : []) });
  });
  await page.goto('/admin/synchronization');
  await expect(page.getByRole('heading', { name: 'Belum ada proses sinkronisasi' })).toBeVisible();
  await page.getByRole('button', { name: 'Update Semua Data', exact: true }).click();
  await expect(page.getByText('Pekerjaan masuk database queue.', { exact: false })).toBeVisible();
  await expect(page.locator('.sync-card')).toHaveCount(5);
  await expect(page.locator('.sync-card .status-badge').filter({ hasText: 'queued' })).toHaveCount(5);
  await expect(page.locator('.sync-card .status-badge').filter({ hasText: 'no_new_data' })).toHaveCount(1, { timeout: 10000 });
  await expect(page.locator('.sync-card .status-badge').filter({ hasText: 'manual_required' })).toHaveCount(4);
  const ojk = page.locator('.sync-card').filter({ has: page.getByRole('heading', { name: /OJK · proses/ }) });
  await ojk.getByText('Log proses', { exact: true }).click();
  await expect(ojk).toContainText('No New Data: checksum sumber tidak berubah.');
  await expect(ojk.getByRole('progressbar')).toHaveAttribute('value', '100');
  await checkWidths(page, 'synchronization', testInfo);
  expect(errors).toEqual([]);
});

test('permission gate blocks editor controls for a browser fixture without publication rights', async ({ page, request }) => {
  await installAdminUiFixture(page, request, ['admin.access']);
  const requested: string[] = [];
  page.on('request', req => { if (req.url().includes('/api/v1/admin/articles')) requested.push(req.url()); });
  await page.goto('/admin/articles');
  await expect(page.getByRole('heading', { name: 'Izin akses diperlukan' })).toBeVisible();
  await expect(page.getByRole('link', { name: 'Artikel baru', exact: true })).toHaveCount(0);
  expect(requested).toEqual([]);
});

test('moderation fixture requires a reason and refreshes the reviewed queue', async ({ page, request }, testInfo) => {
  await installAdminUiFixture(page, request);
  let pending = true; let decision: { action?: string; reason?: string } = {};
  await page.route(url => url.pathname === '/api/v1/admin/comments', route => route.fulfill({ json: paginator(pending ? [{
    id: 99400, body: 'Komentar fixture menunggu peninjauan.', status: 'pending', deleted: false, locked: false,
    user: { id: 99002, name: 'Pembaca Fixture' }, article: { slug: fixtureArticle.slug, title: fixtureArticle.title },
    created_at: fixtureArticle.updated_at, reports: [{ id: 99401, reason: 'Perlu pemeriksaan konteks.' }],
  }] : []) }));
  await page.route('**/api/v1/admin/comments/99400/moderate', async route => { decision = route.request().postDataJSON(); pending = false; await route.fulfill({ json: { message: 'Keputusan fixture tersimpan.' } }); });
  await page.goto('/admin/moderation');
  await expect(page.getByRole('heading', { level: 1 })).toHaveText('Moderasi diskusi');
  await expect(page.locator('.moderation-card')).toContainText('Perlu pemeriksaan konteks.');
  await checkWidths(page, 'moderation', testInfo);
  await page.getByRole('button', { name: 'Setujui', exact: true }).click();
  const decisionDialog = page.getByRole('dialog', { name: 'Setujui komentar' });
  await decisionDialog.getByLabel('Alasan keputusan').fill('Relevan dengan isi artikel; sumber dan konteks sudah diperiksa.');
  await decisionDialog.getByRole('button', { name: 'Setujui komentar', exact: true }).click();
  await expect(page.getByText('Keputusan moderasi disimpan dan diaudit.', { exact: true })).toBeVisible();
  await expect(page.getByRole('heading', { name: 'Antrean moderasi bersih' })).toBeVisible();
  expect(decision.action).toBe('approve'); expect(decision.reason).toContain('sumber dan konteks');
});

