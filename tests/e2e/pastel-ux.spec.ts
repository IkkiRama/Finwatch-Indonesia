import { expect, test, type Locator } from '@playwright/test';
import { expectNoViewportOverflow } from './fixtures';

async function contrastRatio(target: Locator): Promise<number> {
  return target.evaluate(element => {
    const style = getComputedStyle(element);
    const luminance = (color: string) => {
      const rgb = color.match(/[\d.]+/g)!.slice(0, 3).map(value => Number(value) / 255).map(value => value <= .04045 ? value / 12.92 : ((value + .055) / 1.055) ** 2.4);
      return rgb[0] * .2126 + rgb[1] * .7152 + rgb[2] * .0722;
    };
    const foreground = luminance(style.color), background = luminance(style.backgroundColor);
    return (Math.max(foreground, background) + .05) / (Math.min(foreground, background) + .05);
  });
}

test('Google login remains readable before hover, on hover and while pressed', async ({ page }) => {
  await page.route('**/api/v1/me', route => route.fulfill({ json: { authenticated: false, user: null, google_configured: true, admin_mfa_required: true, admin_mfa_verified: false } }));
  await page.goto('/masuk?return_to=%2Fadmin');
  const login = page.getByRole('link', { name: 'Masuk dengan Google' });
  await expect(login).toHaveAttribute('href', '/auth/google/redirect?return_to=%2Fadmin');
  expect(await contrastRatio(login)).toBeGreaterThanOrEqual(4.5);
  await login.hover();
  expect(await contrastRatio(login)).toBeGreaterThanOrEqual(4.5);
  await page.mouse.down();
  expect(await contrastRatio(login)).toBeGreaterThanOrEqual(4.5);
  await page.mouse.move(1, 1); await page.mouse.up();
  await expect(page).toHaveURL(/\/masuk\?return_to=%2Fadmin$/);
});

test('footer uses a beating heart and honors reduced motion', async ({ page }) => {
  await page.goto('/masuk');
  const footer = page.getByRole('contentinfo');
  const heart = footer.getByRole('img', { name: 'cinta' });
  await expect(heart).toHaveClass(/beating-heart/);
  await expect(footer).not.toContainText(/\blove\b/i);
  await expect.poll(() => heart.evaluate(element => getComputedStyle(element).animationName)).not.toBe('none');
  await page.emulateMedia({ reducedMotion: 'reduce' });
  await expect.poll(() => heart.evaluate(element => getComputedStyle(element).animationName)).toBe('none');
});

test('the beginner story lets readers follow money and change the explanation', async ({ page }) => {
  await page.goto('/');
  const stages = page.getByRole('group', { name: 'Tahapan perbankan' });
  const saving = stages.getByRole('button', { name: /Kita menyimpan/ });
  const lending = stages.getByRole('button', { name: /Bank menyalurkan/ });
  await expect(saving).toHaveAttribute('aria-pressed', 'true');
  await lending.click();
  await expect(lending).toHaveAttribute('aria-pressed', 'true');
  await expect(saving).toHaveAttribute('aria-pressed', 'false');
  await expect(page.locator('.flow-caption')).toContainText('rumah tangga dan dunia usaha');
  await expect(page.locator('.flow-fact')).toContainText('8.059,79');
  await page.getByRole('link', { name: 'Mulai dari ceritanya' }).click();
  await expect(page.getByRole('heading', { name: 'Bayangkan Rp100 kredit.' })).toBeVisible();
  await expect(page.locator('.credit-quality')).toContainText('2,22%');
  await expect(page.locator('.credit-quality')).toContainText('bukan peluang gagal bayar pribadi');
});

test('six starter publications provide real readable education and research when installed', async ({ page, request }) => {
  const education = await (await request.get('/api/v1/articles?type=education')).json();
  const research = await (await request.get('/api/v1/articles?type=research')).json();
  test.skip(education.total === 0 && research.total === 0, 'Editorial starter publication requires the owner’s verified Google account and explicit publication command.');
  expect(education.total).toBeGreaterThanOrEqual(4); expect(research.total).toBeGreaterThanOrEqual(2);
  await page.goto('/artikel');
  await expect(page.locator('.editorial-card')).toHaveCount(education.data.length);
  await page.getByRole('searchbox', { name: 'Cari bacaan' }).fill('NPL');
  await expect(page.locator('.editorial-card')).toHaveCount(1);
  await expect(page.locator('.editorial-card')).toContainText('NPL dan CAR');
  await page.locator('.editorial-card h2 a').click();
  await expect(page.locator('.prose-finwatch')).not.toBeEmpty();
  await expect(page.getByRole('progressbar', { name: 'Kemajuan membaca artikel' })).toBeVisible();
  await expect(page.getByRole('navigation', { name: 'Daftar isi artikel' })).toBeVisible();
  await expect(page.locator('.references li')).not.toHaveCount(0);
  const canonical = await page.locator('link[rel="canonical"]').getAttribute('href');
  expect(canonical).toContain('localhost:8000/artikel/');
  await page.goto('/riset');
  await expect(page.getByRole('heading', { level: 1 })).toHaveText('Di balik angka, ada cerita.');
  await expect(page.locator('.editorial-card')).toHaveCount(research.data.length);
});

for (const path of ['/', '/artikel', '/riset', '/masuk', '/inklusi-dan-konsumen', '/pemantauan-risiko', '/peringatan-dini', '/simulasi-stres']) {
  test(`pastel reading and story UI fit 320px on ${path}`, async ({ page }) => {
    const errors: string[] = []; page.on('pageerror', error => errors.push(error.message));
    await page.setViewportSize({ width: 320, height: 900 });
    await page.goto(path);
    await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
    await expectNoViewportOverflow(page);
    expect(new URL(page.url()).hostname).toBe('localhost');
    expect(errors).toEqual([]);
  });
}
