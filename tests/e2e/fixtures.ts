import { expect, type APIRequestContext, type Page } from '@playwright/test';

export const fixturePermissions = ['admin.access', 'sources.view', 'sources.manage', 'data.manage', 'articles.create', 'articles.edit-own', 'articles.review', 'articles.publish', 'categories.manage', 'comments.manage', 'audit.view', 'system.view'];

export function paginator<T>(data: T[]) { return { data, current_page: 1, last_page: 1, total: data.length, from: data.length ? 1 : null, to: data.length || null }; }

export async function expectNoViewportOverflow(page: Page): Promise<void> {
  await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth)).toBeLessThanOrEqual(1);
}

export async function expectFooter(page: Page): Promise<void> {
  const footer = page.getByRole('contentinfo');
  await expect(footer).toContainText(`© ${new Date().getFullYear()} FINWATCH Indonesia`);
  await expect(footer).toContainText('Proyek independen; tidak berafiliasi dengan OJK.');
  const author = footer.getByRole('link', { name: /Profil Instagram Rifki Romadhan/ });
  await expect(author).toHaveAttribute('href', 'https://www.instagram.com/george_ikki/');
  await expect(author).toHaveAttribute('target', '_blank');
  await expect(author).toHaveAttribute('rel', 'noopener noreferrer');
}

// These fixtures intercept only the test browser. Real authentication/authorization
// remains active on the server and has separate HTTP and PHP integration tests.
export async function installAdminUiFixture(page: Page, request: APIRequestContext, permissions = fixturePermissions): Promise<void> {
  const publicDocument = await request.get('/');
  expect(publicDocument.status()).toBe(200);
  await page.route(url => url.pathname === '/admin' || url.pathname.startsWith('/admin/'), route => route.fulfill({ response: publicDocument }));
  await installIdentityFixture(page, permissions);
  await page.route('**/api/v1/admin/overview', route => route.fulfill({ json: {
    metrics: { sources: 5, datasets: 4, observations: 108, published_datasets: 4, failed_jobs: 0 },
    recent_activity: [],
    environment: { queue: 'database', milestone: 'M2–M5', sync_available: true },
  } }));
  await page.route('**/api/v1/admin/audit-logs?*', route => route.fulfill({ json: paginator([{
    id: 99001, event: 'fixture.audit_event', actor_name: 'Fixture Admin E2E',
    created_at: '2026-10-26T01:00:00+00:00', metadata: { channel: 'isolated_browser_fixture' },
  }]) }));
}

export async function installIdentityFixture(page: Page, permissions: string[] = []): Promise<void> {
  await page.route('**/api/v1/me', route => route.fulfill({ json: {
    authenticated: true,
    user: { id: 99001, name: 'Fixture Pengguna E2E', avatar_url: null, roles: permissions.includes('admin.access') ? ['admin'] : ['reader'], permissions },
    google_configured: false, admin_mfa_required: true, admin_mfa_verified: true,
  } }));
}
