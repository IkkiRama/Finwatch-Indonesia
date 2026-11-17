# FINWATCH — Security & Shared Hosting Checklist v2.0

This is a build/release checklist, not a claim that the system can ever be made perfectly secure.

## 1. Before building
- [ ] Confirm hosting vendor, PHP runtime/version, required Laravel extensions, Composer availability, MySQL version and quotas.
- [ ] Confirm `public/` can be the web document root (or implement a documented *safe* mapping without exposing app code).
- [ ] Confirm HTTPS, cPanel cron, CLI PHP, outbound HTTPS, URL rewriting, storage permission, and backup options.
- [ ] Pin compatible versions based on actual environment; inspect maintained library releases.

## 2. Google OAuth
- [ ] Configure official Google Cloud OAuth consent screen and authorized redirect URI for production HTTPS domain.
- [ ] `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_REDIRECT_URI` exist only server-side `.env`; never commit or return in API.
- [ ] Socialite stateful OAuth, state validation, trusted redirect allowlist, session regeneration; do not use `stateless()` for normal first-party session login.
- [ ] Provider unique subject ID (`sub` or provider `id`) keys identities, verified email is checked, new role always `reader`.
- [ ] Super admin via secure manual CLI/verified invitation, not auto promotion from Google address, public registration, or client-provided roles.
- [ ] Session/Sanctum same-origin cookie auth with `Secure`, `HttpOnly`, `SameSite`, CSRF and logout invalidation.
- [ ] Step-up MFA for high-privilege admins if implemented; otherwise document limitation and enforce strong secure Google account practices.
- [ ] Policy/Gate authorization on **all** admin API routes and object-level ownership checks.

## 3. CMS/community
- [ ] All rich HTML sanitized by safe backend allowlist; comment text escaped; no user scripts/unsafe iframes.
- [ ] Upload validated by MIME, magic bytes, length, dimensions; safe image normalization; random storage names; drafts not publicly enumerable.
- [ ] Article drafts and preview links excluded from public API, sitemap, indexing and search.
- [ ] Comments rate limited, reports reviewable, approved-only public; soft-delete tombstones protect threaded replies.
- [ ] Comment ancestry validated same-article and no cycles; bounded data query and visual indentation.
- [ ] Author/commenter email and identity tokens never exposed; aggregate engagement analytics minimize personal data.
- [ ] Published article URLs have server HTML OpenGraph, Twitter Card, canonical, JSON-LD, status codes and sitemap, verified via `curl` without JavaScript.

## 4. Data ingestion
- [ ] Only published official sources and permitted download paths; explicit allowed host list, reject URL redirects to unapproved hosts, private/local IP/metadata endpoint blocks (SSRF defense).
- [ ] Check source terms/robots, HTTP timeouts, size limits, MIME/schema; no CAPTCHA/auth bypass.
- [ ] Raw release snapshot checksum/version; staged transformation; validation; partial rollback; keep prior published data when import fails.
- [ ] One-click sync job is queued and returns run ID; per-job progress and truthful failure.
- [ ] Cron/scheduler and database queue worker proven working on actual hosting; use short-lived bounded tasks, chunking, locks and retries.
- [ ] Manual safe CSV/XLSX upload fallback if automated source access or cron blocked.

## 5. Web/API
- [ ] `APP_ENV=production`, `APP_DEBUG=false`, no stack traces to public; centralized error reporting with sensitive-field redaction.
- [ ] HTTP security headers (CSP tuned for Vite, Referrer-Policy, X-Content-Type-Options, strict HTTPS/HSTS if tested).
- [ ] Eloquent/prepared SQL, Form Requests validation, parameterized search, bounded pagination, rate limiting; mass assignment locked down.
- [ ] Authorize every state-changing request, audit privilege promotions, publishing, sync, moderation, data corrections.
- [ ] `.env`, app source, vendor and backup never publicly accessible; directory listing off; web root correctly configured.
- [ ] Safe CI/CD build, lockfiles and dependency update review; no secrets in front-end `VITE_*` variables.
- [ ] Backup/restore tested before release; DB backup + uploads, encrypted where appropriate.

## 6. Release and demo
- [ ] Production Google auth manually tested with approved account; unauthorized Google account unable to enter admin.
- [ ] Article creation, scheduled publish, category/author archive, SEO/social preview, share links tested.
- [ ] Nested comment on mobile, pending/moderation, hide/delete, report, likes, and rate limits tested.
- [ ] Public data backed by official source, version, observation period and units; zero unlabeled demo statistics.
- [ ] Performance on low-resource shared host, Core Web Vitals, keyboard navigation, mobile breakpoints and no false buttons checked.
- [ ] Footer includes working Instagram link, copyright year and non-affiliation disclaimer on public and admin pages.
