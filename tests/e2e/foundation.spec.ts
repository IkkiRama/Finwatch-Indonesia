import { expect, test } from '@playwright/test';
import { expectFooter, expectNoViewportOverflow, installAdminUiFixture } from './fixtures';

const widths = [320, 768, 1440];
for (const width of widths) {
  test(`public shell remains usable at ${width}px`, async ({ page }, testInfo) => {
    const browserErrors: string[] = [];
    page.on('pageerror', error => browserErrors.push(error.message));
    await page.setViewportSize({ width, height: 900 });
    const response = await page.goto('/');
    expect(response?.status()).toBe(200);
    await expect(page.getByRole('heading', { level: 1 })).toContainText('Uang kita');
    await expect(page.locator('.finance-flow')).toBeVisible();
    await expect(page.getByRole('group', { name: 'Tahapan perbankan' }).getByRole('button')).toHaveCount(3);
    await expect(page.locator('.source-sticky-note')).toContainText('22 seri terbuka');
    await expectFooter(page);
    await expectNoViewportOverflow(page);
    await page.getByRole('link', { name: 'Kenali sumber kami' }).scrollIntoViewIfNeeded();
    await expect(page.getByRole('link', { name: 'Kenali sumber kami' })).toBeVisible();
    if (width === 320 || width === 1440) {
      await page.screenshot({ path: testInfo.outputPath(`public-${width}.png`), fullPage: true });
    }

    if (width < 1024) {
      const menuButton = page.getByRole('button', { name: 'Buka navigasi' });
      if (await menuButton.isVisible()) {
        await menuButton.click();
        await expect(page.getByRole('navigation', { name: 'Navigasi seluler' })).toBeVisible();
        await page.getByRole('navigation', { name: 'Navigasi seluler' })
          .getByRole('link', { name: 'Sumber data', exact: true }).click();
        await expect(page.getByRole('heading', { level: 1 })).toHaveText('Sumber yang dapat ditelusuri.');
        await expect(page.getByRole('navigation', { name: 'Navigasi seluler' })).toHaveCount(0);
        await expectNoViewportOverflow(page);
      }
    }
    expect(browserErrors).toEqual([]);
  });
}

test('real source catalog supports searching, sorting and public navigation', async ({ page }) => {
  await page.goto('/sumber-data');
  await expect(page.getByRole('heading', { level: 1 })).toHaveText('Sumber yang dapat ditelusuri.');
  const search = page.getByRole('textbox', { name: 'Cari sumber data' });
  await expect(search).toBeVisible();
  await expect(page.locator('tbody tr')).toHaveCount(5);
  await search.fill('BPS');
  await expect(page.locator('tbody tr')).toHaveCount(1);
  await expect(page.locator('tbody tr')).toContainText('Badan Pusat Statistik');
  await expect(page.getByRole('link', { name: 'Buka publikasi BPS (tab baru)' }))
    .toHaveAttribute('href', 'https://webapi.bps.go.id/developer/');
  await search.fill('sumber-yang-tidak-ada-fixture');
  await expect(page.getByText('Tidak ada sumber yang sesuai dengan pencarian.')).toBeVisible();
  await search.clear();
  await page.getByRole('combobox', { name: 'Metode sumber' }).selectOption('manual');
  await expect(page.locator('tbody tr')).toHaveCount(4);
  await page.getByRole('button', { name: 'Sumber data', exact: true }).click();
  await expect(page.locator('th[aria-sort]')).toHaveAttribute('aria-sort', 'descending');
  await page.getByRole('navigation', { name: 'Navigasi utama' })
    .getByRole('link', { name: 'Edukasi keuangan', exact: true }).click();
  await expect(page).toHaveURL(/\/artikel$/);
  await expect(page.getByRole('heading', { level: 1 })).toHaveText('Urusan keuangan, dibuat lebih masuk akal.');
  const catalog = await (await page.request.get('/api/v1/articles?type=education')).json();
  if (catalog.data.length) await expect(page.locator('.editorial-card')).toHaveCount(catalog.data.length);
  else await expect(page.getByRole('heading', { name: 'Belum ketemu bacaan yang cocok' })).toBeVisible();
});

test('missing Google credentials produce a disabled login action', async ({ page, request }) => {
  const identity = await (await request.get('/api/v1/me')).json();
  test.skip(identity.google_configured, 'This environment already has real Google credentials.');
  await page.goto('/masuk');
  await expect(page.getByRole('button', { name: 'Masuk dengan Google' })).toBeDisabled();
  await expect(page.getByText('Google OAuth belum dikonfigurasi.', { exact: true })).toBeVisible();
  await expectFooter(page);
});

test('unknown public routes return an actual HTTP 404', async ({ page }) => {
  const response = await page.goto('/halaman-tidak-ditemukan-e2e');
  expect(response?.status()).toBe(404);
  await expect(page.getByRole('heading', { level: 1 })).toHaveText('Mari kembali ke konteks.');
  await page.getByRole('link', { name: 'Kembali ke ringkasan' }).click();
  await expect(page).toHaveURL(/\/$/);
});

test('guest admin navigation is redirected by the server and APIs reject access', async ({ page, request }) => {
  const serverResponse = await request.get('/admin', { maxRedirects: 0 });
  expect(serverResponse.status()).toBe(302);
  expect(serverResponse.headers().location).toMatch(/\/masuk$/);
  const apiResponse = await request.get('/api/v1/admin/overview', {
    headers: { Accept: 'application/json' },
  });
  expect(apiResponse.status()).toBe(401);
  await page.goto('/admin');
  await expect(page).toHaveURL(/\/masuk$/);
  await expect(page.getByRole('heading', { name: 'Masuk ke FINWATCH' })).toBeVisible();
});

test('skip navigation is reachable by keyboard', async ({ page }) => {
  await page.goto('/');
  await expect(page.getByRole('heading', { level: 1 })).toContainText('Uang kita');
  await page.keyboard.press('Tab');
  await expect(page.getByRole('link', { name: 'Lewati ke konten utama' })).toBeFocused();
  await page.keyboard.press('Enter');
  await expect(page.getByRole('main')).toBeFocused();
});

for (const width of widths) {
  test(`admin UI fixture supports navigation without overflow at ${width}px`, async ({ page, request }) => {
    const browserErrors: string[] = [];
    page.on('pageerror', error => browserErrors.push(error.message));
    await page.setViewportSize({ width, height: 900 });
    await installAdminUiFixture(page, request);
    await page.goto('/admin');
    await expect(page.getByRole('heading', { level: 1 })).toHaveText('Pusat kendali platform');
    await expect(page.getByText('Belum ada aktivitas tercatat', { exact: true })).toBeVisible();
    await expectFooter(page);
    await expectNoViewportOverflow(page);

    const mobileToggle = page.getByRole('button', { name: 'Buka menu admin' });
    if (await mobileToggle.isVisible()) {
      await mobileToggle.click();
    }
    else {
      await page.getByRole('button', { name: 'Ciutkan sidebar' }).click();
      await expect(page.getByRole('button', { name: 'Perluas sidebar' })).toHaveAttribute('aria-expanded', 'false');
      await page.getByRole('button', { name: 'Perluas sidebar' }).click();
    }
    const navigation = page.getByRole('complementary', { name: 'Navigasi workspace' });
    await navigation.getByRole('link', { name: 'Aktivitas & audit', exact: true }).click();
    await expect(page.getByRole('heading', { level: 1 })).toHaveText('Aktivitas & audit');
    await expect(page.locator('tbody tr')).toContainText('fixture.audit_event');
    await page.getByText('Lihat detail', { exact: true }).click();
    await expect(page.locator('.audit-detail pre')).toContainText('isolated_browser_fixture');

    const queryRequest = page.waitForRequest(req => {
      const url = new URL(req.url());
      return url.pathname === '/api/v1/admin/audit-logs' && url.searchParams.get('query') === 'fixture';
    });
    await page.getByRole('textbox', { name: 'Cari audit' }).fill('fixture');
    await queryRequest;
    await expectNoViewportOverflow(page);
    expect(browserErrors).toEqual([]);
  });
}



