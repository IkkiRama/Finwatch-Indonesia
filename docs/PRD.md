# FINWATCH Indonesia — Product Requirements Document (PRD) v2.0

**Project:** FINWATCH Indonesia — Financial Sector Surveillance, Risk Intelligence & Consumer Protection Platform  
**Positioning:** Independent financial research and public-education portfolio; **not affiliated with OJK**  
**Date:** 26 October 2026  
**Primary language:** Bahasa Indonesia, with understandable financial terminology  
**Target:** Responsive React SPA + Laravel API, MySQL, deployment on Apache/cPanel shared hosting (subject to hosting capabilities)  
**Public identity:** FINWATCH Indonesia; © dynamic year; “Made with love by Rifki Romadhan” (hyperlink the author name to https://www.instagram.com/george_ikki/)

## 1. Product vision, scope, trust

FINWATCH turns Indonesian public financial-sector publications into auditable dashboard indicators, risk analytics, explainable early-warning signals, transparent stress-test scenarios, policy insights, literacy/inclusion analysis, and a citizen-facing educational publication/community portal.

**Primary users:** the general public/learners, economic researchers, analysts, anonymous readers, Google-authenticated commenters, verified content authors/editors, data curators, moderators, and super admins. The website should demonstrate software engineering, data sourcing, analytical judgment, and financial education for a PCAM OJK portfolio.

**Core rules:**
- Clearly indicate independent project; avoid official OJK logos, visual impersonation, regulatory ratings, alleged access to confidential data.
- Publication dates are distinct from observation periods and retrieval timestamps. No false “real-time” claims.
- Unavailable datasets show informative empty states; never pad with synthetic values or silently interpolate missing values.
- Any analytical model/signal is labelled a research tool, not an official OJK risk rating or investment recommendation.
- CMS education pieces are not personalized financial advice; cite official reference material where appropriate.
- Public comments do not constitute official complaints to OJK. Display a notice and, if useful, links to official consumer channels.

## 2. Technology and deployment constraints

| Area | Technology | Constraints |
|---|---|---|
| Client | React + Vite + TypeScript strict + React Router | SPA interactions; Vite build locally or in CI, no production Node server |
| UI | Tailwind CSS, shadcn/ui, Lucide | Design tokens and reusable accessible components |
| Data handling | TanStack Query, Axios, Zod or equivalent validation | Safe server responses, caching with freshness |
| Charts and maps | Recharts, Leaflet + permitted GeoJSON | Responsive visuals, tables as accessible alternatives |
| Content editor | Tiptap or equally maintained editor | Server-side sanitized rich text |
| Backend | Laravel (version compatible with provider PHP), Eloquent, Form Requests, Policies | REST `/api/v1` endpoints and Blade entry HTML for SSR metadata |
| OAuth | Laravel Socialite (Google), Sanctum session cookie SPA auth | CSRF, state, secure session, least-privilege default reader |
| Persistence | MySQL/MariaDB | InnoDB, UTF8MB4, FK constraints, indexes, transactions |
| Async tasks | Laravel database queue, Scheduler, cPanel cron | Short-lived worker only, no persistent daemon; documented fallback |
| Quality | Pest/PHPUnit, Vitest, Playwright, TypeScript/ESLint | Integration/security/e2e tests and migration tests |
| Hosting | Apache/cPanel shared host | Verify PHP version/extensions, outbound HTTPS, cron, document root, quotas |

**Recommended integration:** one Laravel repository serving both `/api/v1/*` routes and the React SPA via Vite-built assets/Blade bootstrapping. Public article/category/author GET requests use Laravel controllers to serve accurate initial HTML metadata and SEO-safe semantic content while React provides SPA navigation and richer interactions. Avoid a public web-root containing framework app code or `.env`; use `public/` as actual document root. Where cPanel document-root changes are unavailable, document a *safe* alternative deployment mapping; do not copy private app contents into `public_html`.

### 2.1 Branding + footer
- Sticky global header as appropriate; public navbar has `Analisis`, `Inklusi & Konsumen`, `Artikel Edukasi`, `Sumber Data`, `Tentang` with mobile drawer.
- Every public page and admin shell footer: `© {current calendar year} FINWATCH Indonesia`, `Made with love by Rifki Romadhan` where the **name itself** links to `https://www.instagram.com/george_ikki/`, external window (`target="_blank"`, `rel="noopener noreferrer"`), accessible label; disclaimer `Proyek independen; tidak berafiliasi dengan OJK`.
- Dynamic footer copyright year is independent from latest year in the data filters. Use JavaScript/PHP current year as appropriate, not hardcoded.
- Instagram is a **profile** link, not a promise that article content can be posted directly to Instagram by a one-click API.

## 3. Product routes and primary navigation

### 3.1 Public app
| Route | Label | Purpose |
|---|---|---|
| `/` | Ringkasan / Overview | Latest verified observations and source freshness |
| `/perbankan` | Banking Surveillance | Industry-level bank assets, credit, DPK, NPL, CAR, LDR, ROA, NIM |
| `/pemantauan-risiko` | Risk Monitoring | Trend/change/comparison/anomaly explanations |
| `/peringatan-dini` | Early Warning System | Research alerts, model rules, triggered evidence |
| `/simulasi-stres` | Stress Testing Simulator | Transparent scenario calculations and assumptions |
| `/riset` | Research & Policy Insights | Documented insights, reports and public exports |
| `/inklusi-dan-konsumen` | Financial Inclusion & Consumer Protection | SNLIK/PEPK breakdowns, gap, complaints, illegal financial activity |
| `/artikel` | Edukasi Keuangan | Educational article landing, search, featured, categories |
| `/artikel/{slug}` | Detail Artikel | Reading layout, comments, related articles, social sharing |
| `/kategori/{slug}` | Kategori | Taxonomy archive, filters and pagination |
| `/penulis/{slug}` | Penulis | Public author profile and article archive |
| `/jelajah-data` | Data Explorer | Source-aware interactive data tables and export |
| `/sumber-data` | Data Sources | Provenance, release timeline, caveats |
| `/tentang` | About | Project description, methodology, authorship, disclaimers |

Public browsing/search requires **no account**. Google login is only required for commenting/reactions/reporting and potentially saving reading preferences.

### 3.2 Admin app
`/admin` after authenticated + authorized session. Group navigation into:
- **Workspace:** Dashboard, Alerts, System Activity.
- **Financial Data:** Data Sources, Synchronization Center, Datasets & Validation, Indicators, Risk/EWS, Stress Scenarios, Research Reports.
- **Publication:** Articles, Categories & Tags, Authors, Media Library, Comments & Reports.
- **System:** Users, Roles & Permissions, Audit Logs, System Health, Settings.

## 4. Visual system — explicit "no AI slop" requirements

### 4.1 Art direction
Create a **financial research newsroom meets enterprise SaaS** feeling, not an AI dashboard template. Restrained neutral palette (white/off-white and deep slate/navy typography, one accent like muted teal or blue). Consistent density and hierarchy. Typography: highly legible sans-serif for UI and tables; optional editorial serif only for long-form article headings, not dashboards. Use a clear 8px spacing grid, restrained border and radius, premium table density, subtle hover micro-interactions, intentional empty space, real information architecture and high-content quality.

**Forbidden design shortcuts:** excessive saturated gradients, glowing charts, decorative blobs, glassmorphism on every card, unnecessary icons/emoji, lorem ipsum/fake testimonials, meaningless "AI insights", cloned KPI cards on every page, random stock-finance hero photos, dark patterns, fake progress animations, unusable mobile tables and charts.

**Useful UI components:** collapsible admin sidebar with active states, sticky workspace topbar, breadcrumb, page title/action bar, query filter chips, segmented controls, date picker, sortable table with sticky header, bulk actions, confirmation dialogs, responsive charts and table views, accessible toast, skeleton states, empty state CTA, contextual inline explanations, help/tooltips, error recovery actions, compact activity timeline, expandable record details. Avoid no-op buttons.

### 4.2 Accessibility & responsive
- Mobile 320–767px, tablet 768–1023px, desktop 1024px+, large desktop >1440px. Avoid horizontal viewport overflow; tables scroll within region with visible cues.
- Chart tooltip keyboard/touch fallbacks and accessible data tables, table headers with units e.g. `Kredit (Rp triliun)` and `NPL Gross (%)`, number locale `id-ID`.
- Semantic landmarks, consistent tab order, visible focus, alt text, aria descriptions, color-independent status labels, adequate contrast (target WCAG 2.2 AA), reduced-motion setting.
- Light theme required. Dark mode optional only if fully polished and contrast-tested.
- Mobile navigation should not expose admin links to readers; never rely on hiding links as actual authorization.

## 5. Public financial intelligence — seven modules

### 5.1 Overview
Show real latest-available period by indicator (not site date), units, sector/country scope, update recency, compare with prior comparable period, source link, small trend, open data table, visible data health warning. Source release dates differ; do not merge unaligned series as if synchronous.

### 5.2 Banking Surveillance
Filter by dataset/source, indicator, frequency and valid date range. Verified trends for bank assets, credit, deposits, NPL gross/net, capital adequacy/CAR, liquidity/LDR, profitability ROA/NIM when available. Mark aggregates as **industry aggregates**. No private bank identifiers and no unsourced institutional risk ranking. Exports with headers/units and source citations.

### 5.3 Risk Monitoring
Descriptive percent growth for positive levels, percentage-point change for percentage ratios, rolling z-score only when adequate past observations, optional standardized historical bands, flags for discontinuities or changing definitions. Explain every signal with the observed and benchmark values.

### 5.4 Early Warning System
Rule-based **research alerts** with config version: worsening NPL movement, abnormal credit growth, liquidity proxy, composite only when justified and documented. Severity derived by measured rules, not undefined green/red. Require minimum sample, same scope/frequency, valid lag and comparability flag. Publish code, formula, model version, input versions, signal timestamp; no claim of official supervisory judgement.

### 5.5 Stress Testing Simulator
Interactive scenario parameters for a transparent hypothetical risk sensitivity model; input validation, equations, reference baseline, scenario explanation, assumptions and limitations, ability to reset/compare scenarios; do not present scenario output as institution forecast. Save only explicit user-named scenarios if logged-in admin; public visitors can experiment without persistent personal data.

### 5.6 Research & Policy Insights
Research briefs with methodology, input release vintage, chart/data links, quantitative changes, interpretation versus causality caveats. Admin can create and publish; print-friendly and export PDF if tested. Every factual claim and graph must trace to official evidence.

### 5.7 Financial Inclusion & Consumer Protection
Financial inclusion map with missing-data states; literacy vs inclusion by available survey strata; PEPK complaints broken down by type/sectors and report period; illegal-finance activity aggregated only from official counts; policy priority analyzer with transparent rubric and no invented region ranks. Do not directly compare different SNLIK survey waves without methodology comparability notes. Distinguish `complaints`, `queries`, `cumulative counts`, `entities`, `sites` and `financial losses`—never sum incompatible quantities.

### 5.8 Data Explorer & Sources
Data dictionary, source release cards, scope/units/frequency, as-of period, downloadable CSV only when permitted, methodology footnotes, changelog of data revisions, and friendly missing-data explanations.

## 6. Edukasi Keuangan — new article/CMS module

### 6.1 Editorial goals & taxonomy
Educational articles should cover banking basics, personal finance literacy, managing risk, consumer rights, fraud prevention, financial inclusion, markets and insurance without implying OJK endorsement. Admin-managed **categories**, optional parent-child category grouping, unique slugs, description, cover art and SEO; **tags** as optional cross-cutting topics. Article belongs to one **primary category**, may have secondary categories; one or more author attribution records (MVP: one primary author; design schema so co-authors are possible).

### 6.2 Listing and discovery
- `/artikel`: editorial hero for actual featured article, latest published, category rails, search, featured topics, browse all, filters `kategori`, `penulis`, `kata kunci`, `tanggal`, reading time optional.
- `/kategori/{slug}`: category description, optional children navigation, cards and pagination/cursor; canonical.
- `/penulis/{slug}`: public author profile (name, headshot, short bio, optional voluntary social links), published article index, **no public email**.
- Article card contains original cover or clean fallback illustration, title, synopsis, real author, category, publication date, reading time from word count. Never fabricate engagement metrics.
- Search indexes **published** content only. Rate limit expensive search.

### 6.3 Article detail
Readable two-column desktop layout (main article + sticky table of contents/related where reasonable), excellent single-column mobile layout, heading hierarchy, editorial lead/excerpt, cover + accessible alt, author/date/updated, human-readable reading time, in-page ToC, copy-link and social share, related articles determined by category/tag overlap and editorial override (not random), reference/source section, educational disclaimer and embedded comments.

### 6.4 Editor & publishing workflow
- Rich text editor (Tiptap suggested): paragraph, H2/H3, emphasis, links, quote, numbered/bullet lists, images, captions, tables, embeds **only from explicit allowlist**; prevent raw script/embed abuse.
- Cover/media upload: extension + MIME + file signature verify, file size, image dimension limits, normalization to safe formats, generated thumbnails, descriptive alt text, remove unwanted metadata, stable media paths; private staging for drafts.
- Article fields: title, slug, excerpt, body structured document + sanitized HTML rendering, primary and additional categories, tag ids, author_id, cover media id, sources, SEO title/description, social image, canonical, featured flag, comment setting, status, visibility, publish_at, updated_at.
- Workflow statuses `draft`, `in_review`, `scheduled`, `published`, `archived`. Author drafts; editor reviews; publisher/admin publishes. Preview only authenticated authorized users or expiring signed preview link. Revision history, restore and slug history 301 redirect; slug collisions rejected.
- Admin article list with advanced filtering, bulk operations gated by permission, revision compare, publish confirmation, schedule cron, unpublish, allow/disallow comments.
- An article cannot be published with invalid title/body/category, unsupported embeds, missing required source info for factual claims, or unsafe uploads.

### 6.5 Social sharing & SEO
- Share actions: native `navigator.share` where available; otherwise build correctly URL-encoded links for **WhatsApp, Facebook, X, Telegram, LinkedIn**, plus **copy link**. For Instagram, allow copying article URL; no unsupported automated story/feed publishing.
- Each public article/category/author route gets **server-returned** correct meta tags on the *initial HTTP response*: title, description, canonical, Open Graph (`og:type`, `og:title`, `og:description`, `og:url`, absolute `og:image`), Twitter Card, HTML language and robots. Use social images with absolute HTTPS URLs.
- `Article`, `BreadcrumbList`, `Person` (when appropriate) JSON-LD derived from actual database. No invented author/review markup. 200 for published, 404 for missing/deleted, 301 for old slug, noindex for preview/admin and blocked drafts. XML sitemap with lastmod for published and robots.txt.
- SPA navigation must retain accessible navigation and update page title without contradicting initial canonical. Laravel must serve meaningful initial semantic article content/no-JS fallback, not only a blank div for crawlers/social scrapers. Do not use deprecated bot-specific dynamic rendering as an architecture shortcut.

## 7. Google login, users and permissions

### 7.1 Entry points
- Reader sees `Masuk dengan Google` only when an action requires login (or via account menu). Public articles/data accessible anonymously.
- `/auth/google/redirect` + `/auth/google/callback` handled entirely by Laravel Socialite using ordinary stateful OAuth. Scopes limited to OpenID identity, verified email, and basic public profile.
- Match identities by immutable Google `sub` (`google_id` or provider subject), not email. Verify account state and email verification through verified provider identity, and never confuse OAuth authentication with platform authorization.
- New OAuth users always `reader`; **no automatic promotion to admin based on matching name/Google login itself**. Provision `super_admin` securely by CLI/bootstrap with explicit subject ID and verified account, one-time seeding instructions or pending invitation approval. Grant other roles via permission-guarded admin form and audited action.
- Login callback issues Laravel session, regenerates session ID and redirects to safe same-origin path. Logout invalidates session and rotates CSRF. API identity from `/api/v1/me` (not localStorage tokens). HTTPS and secure cookies.

### 7.2 Roles and authorization
| Role | Permissions baseline |
|---|---|
| `reader` | create/reply/like/report own comments; edit/delete own under policy; see public content |
| `author` | create/edit own drafts and view own review status, comment as reader |
| `editor` | edit and review authorized articles, manage categories if granted; cannot change security settings |
| `moderator` | approve/hide/report/review comments and limited user restrictions |
| `admin` | manage data/content and systems with restricted security privileges |
| `super_admin` | explicitly authorized roles/security/configuration/audit and administrative ownership |

Role names alone must not be used as entire policy; implement granular Laravel Gates/Policies and automated authorization tests, including object-level ownership and cross-role denial. Avoid mass-assignment privilege escalation. Author user data must never expose email to public.

### 7.3 Hardening
Sanctum stateful SPA cookies + CSRF; Google OAuth state validation; login rate limit; logout and session expiry; SameSite, HttpOnly, Secure flags; strong allowlist for callback redirect; opt-in admin TOTP/step-up MFA; session revocation for role changes, minimum OAuth scopes; audit login/admin changes. **OAuth login alone does not guarantee MFA**. Secret keys remain server-side.

## 8. Nested comments and social interaction

### 8.1 User-facing behavior
- Comment and reply under each published article; sign-in required to **post/reply/like/report**, but reading public approved comments requires no sign-in.
- Display avatar, author display name, relative+exact accessible timestamp, edited label, likes count, `Balas`, `Suka`, `Laporkan`, `Salin tautan`; own comment gets `Edit` and `Hapus`.
- Threaded parent/replies with link to parent and collapse/expand, incremental replies and lazy loading. Sorting by `Teratas`, `Terbaru`, `Terlama` with stable tie breaking, cursor pagination. Do not create unbounded recursive rendering: after e.g. 4 indentation levels show subsequent logical depth in a bounded visual indent/thread view. Mobile long text wraps correctly.
- Deep links `/artikel/{slug}#komentar-{id}` or equivalent client router handling to highlight a comment and reveal parent chain if present.
- Show explicit moderation state for pending user comments; hidden/rejected comments are not public. Soft-deleted comments with replies display `Komentar telah dihapus` placeholder without exposing original.
- Notification in-app for reply/mention is optional post-MVP with privacy controls and safe notification counts; no emails without consent. Avoid indiscriminate user mentions or external embeds.

### 8.2 Moderation controls and anti-abuse
- Table filters `pending`, `approved`, `flagged`, `hidden`, `rejected`, `deleted`; view reports; approve/hide/reject/restore, lock thread, block user from commenting (not necessarily block reading); reason and audit log.
- Limit size, frequency, repeated posts and outbound links; consider an adaptive first-comment review policy. Safe plain text or explicitly sanitized restricted Markdown; **never render unsanitized user HTML**.
- Validate parent_id is within same article, not deleted/locked, prevent cycles; bounded depth for query safety, no N+1. Use db transaction and unique constraints for comment likes, rate limiter for posting, abuse and reports.
- Moderation settings per article and sitewide; prevent comments on drafts and archived articles, handle article unpublishing, no doxxing or exposing addresses/IP/Google emails.

## 9. Admin dashboard & management functions

### 9.1 Admin overview
Professional SaaS layout with live source-integrity KPIs: count of verified datasets, imported periods, failed jobs, pending data validation, pending comment reports, article drafts/scheduled/published, recent sync status, recent audit activity, database/cron heartbeat where permitted. Show `unavailable` rather than made-up metrics. Every KPI actionable with drill-down.

### 9.2 Data Source Registry
Per source: name, official URL, allowed domains, source method (`official_api`/`official_file`/`manual`), frequency, expected release lag, source adapter identifier, enabled status, last discovered/checked/available/ingested/released period, checksum, last error. Admin only edits trusted source config; validate against approved domains.

### 9.3 Synchronization Center
One button **Update Semua Data**, per-source update, run history, progress/status, logs and retry. Status `queued`, `checking`, `downloading`, `parsing`, `validating`, `needs_review`, `publishing`, `success`, `no_new_data`, `partial`, `failed`.

**Workflow:** UI POST creates sync_run then database queue. Each adapter checks publications and legally usable official downloads; compare source version/time/hash; download; verify content; stage data; validation; review if major revisions; atomic publish; version data and recalculate affected indicators; expose progress polls. If official source absent or blocks automation, show `manual upload required`, never fabricate data or bypass access controls.

Cron schedules **checks** independent of publication frequency; next calendar year only appears in filter if an actual observation of that year is published. Scheduler and short-lived database worker need cPanel cron + supported PHP CLI; fallback human-triggered secure upload/processing if unavailable. Errors should not corrupt last published snapshot. Unknown large tasks chunked with limits.

### 9.4 Dataset / indicator / analytics / reports admin
- Dataset management: browse imported rows, raw snapshot references, provenance, source versions, clean/staging split, validation and approval, revisions with diff and rollback.
- Indicator definitions: code, label, unit, freq, scope, transformations, alerts config, comparability notes, license/use restrictions.
- EWS rules: min sample, thresholds, version, input series, change audit, preview effects before publish.
- Stress scenarios: validated params, model version and sensitivity math, presets, publish controls.
- Research/report CMS: citations, methodologies, chart selection, publication workflow, PDF/print options and source links.
- System: role/permission management, audit timeline, log redaction, health/readiness, failed queue jobs, settings, backups and restoration instructions.

## 10. Sources, provenance, analytical integrity

**Primary starting points (verify actual file/API availability per dataset):**
- OJK Financial Sector Data Portal: https://data.ojk.go.id/SJKPublic
- OJK Perbankan: https://ojk.go.id/id/kanal/perbankan/data-dan-statistik/statistik-perbankan-indonesia/default.aspx
- BI SEKI: https://www.bi.go.id/id/statistik/ekonomi-keuangan/seki/default.aspx
- BPS API information: https://webapi.bps.go.id/developer/
- SNLIK data and OJK public releases: https://www.ojk.go.id
- OJK PEPK periodical spreadsheets: official pages/attachments, when accessible.

**For each observational value** preserve `indicator`, `value`, `unit`, `period_date`, `frequency`, `sector`, `geographic scope`, `segment`, `source_document`, `source URL`, `release_date`, `retrieved_at`, `dataset_version`, and `methodology_note`. Be strict about distinguishing percent changes and percentage point (pp) changes; avoid accidentally combining monthly and year-to-date cumulative complaints. Maintain source archive snapshots and revisions.

**Formula examples:** level growth `100*(x_t/x_(t-k)-1)` if denominator nonzero and valid; rate delta in `pp = rate_t - rate_(t-k)`; inclusion-literacy aggregate gap `inclusion% - literacy%` with labels and comparability caveat; anomalies with rolling baseline require sufficient non-missing values and a methodological explanation. No false causal claims and no interpolation labelled as original official data.

## 11. Data model — schema requirements

### 11.1 Identity & community
- `users(id, name, email_private, avatar_url, status, last_login_at, timestamps)`
- `social_identities(id, user_id, provider, provider_subject UNIQUE, verified_email_at, timestamps)`
- `roles`, `permissions`, `model_has_roles`, `role_has_permissions` or equivalent audited RBAC
- `admin_invitations`, `user_restrictions`, `sessions` where applicable, `audit_logs`
- `author_profiles(user_id, slug UNIQUE, public_name, bio, photo_media_id, public_links, timestamps)`
- `categories(id, parent_id nullable, name, slug UNIQUE, description, status, sort_order)`
- `tags(id, name, slug UNIQUE)`
- `articles(id, author_id, primary_category_id, title, slug UNIQUE, excerpt, body_doc, body_html_sanitized, cover_media_id, status, published_at, scheduled_at, seo_title, seo_description, seo_image_id, allow_comments, is_featured, timestamps, softDeletes)`
- `article_categories(article_id, category_id UNIQUE composite)`, `article_tags`, optional `article_authors`
- `article_revisions(article_id, editor_id, version, snapshot_data, change_note, timestamps)`; `article_slug_redirects(old_slug UNIQUE, article_id)`
- `media_assets(owner_id, path, variants, mime_type, width, height, bytes, alt_text, status, timestamps)`
- `comments(id, article_id, user_id, parent_id nullable, body_plain, status, like_count_cached, depth, created_at, edited_at, deleted_at)` with indexes on article/status/created, parent; prevent cycle/cross-article parent.
- `comment_likes(comment_id, user_id UNIQUE composite)`, `comment_reports(comment_id, user_id, reason, status, reviewed_by, timestamps)`, `comment_revisions`, `comment_moderation_actions`, optional `comment_notifications`
- `article_view_daily(article_id, date, aggregate_count)` aggregated/minimized analytics; avoid intrusive tracking.

### 11.2 Financial data/admin automation
- `data_sources`, `datasets`, `dataset_versions`, `indicators`, `indicator_observations`, `source_files`, `import_batches`, `validation_issues`, `data_reviews`, `sync_runs`, `sync_run_items`, `jobs`, `failed_jobs`.
- `risk_models`, `risk_model_versions`, `risk_thresholds`, `risk_signals`, `stress_scenarios`, `stress_results`, `research_reports`, `report_exports`, `audit_logs`, `system_settings`, `system_heartbeats`.
- Uniqueness and FK constraints: observations by series/scope/period/vintage; published current view always points to approved vintage. FK indexes and transaction boundaries mandatory.

**Important:** fields are logical requirements, not fully prescribed migrations; Codex should design normalized MySQL columns with appropriate indexes, constraints, nullable rules, JSON storage where justified, and migrations/tests. Avoid storing access tokens unnecessarily; if absolutely required encrypt at rest and never expose them to frontend/logs.

## 12. API contract (indicative)

### Public
- `GET /api/v1/overview`, `/indicators`, `/observations`, `/sources`, `/filters/years`
- `GET /api/v1/articles?category=&author=&query=&sort=&cursor=`
- `GET /api/v1/articles/{slug}`, `/categories`, `/categories/{slug}`, `/authors/{slug}`
- `GET /api/v1/articles/{id}/comments?sort=&cursor=`, `GET /api/v1/comments/{id}/replies?cursor=`
- `GET /api/v1/me` (guest returns unauthenticated state), Google redirect/callback via web routes.

### Authenticated reader (Sanctum web auth + policies + rate limits)
- `POST /api/v1/articles/{id}/comments` (body, optional parent_id)
- `PATCH /api/v1/comments/{id}`, `DELETE /api/v1/comments/{id}`
- `POST /api/v1/comments/{id}/likes` / `DELETE .../likes`
- `POST /api/v1/comments/{id}/reports`

### Admin (same guard, explicit role/policy)
- `GET /api/v1/admin/overview`
- CRUD `/api/v1/admin/articles`, `/categories`, `/authors`, `/media`, `/users`, role/permission admin endpoints
- `POST /api/v1/admin/articles/{id}/publish`, `/schedule`, `/unpublish`, `/restore-revision`
- `GET /api/v1/admin/comments`, `POST /api/v1/admin/comments/{id}/moderate`
- `GET /api/v1/admin/sources`, `POST /api/v1/admin/sync/runs`, `GET /api/v1/admin/sync/runs/{id}`, per-source trigger
- CRUD data, validation, EWS config, model/scenario rules and reports with appropriate audit.

All responses have predictable JSON error format and pagination; no administrative action allowed merely because the request path is hidden. Do not include real Google private emails or secret values in public responses.

## 13. Security, privacy & performance acceptance

- OAuth state/session callback attack tests, Google subject account linking, verified email, no open redirect.
- API tests: anonymous cannot post; reader cannot view admin; author cannot publish others' content; editor cannot manage roles; admin without permission cannot elevate self; revoked user loses access promptly.
- CSRF and same-site cookie tests, XSS tests for article editor/comments, HTML sanitizer server-side, media upload spoofed MIME/oversized rejection, outbound HTTP SSRF guard, SQL injection prevention, rate limits, moderation + deletion + revision.
- Initial server HTTP HTML contains exact per-article OG/canonical (test with curl without running JS); article permalinks have expected 200/301/404, sitemap only published.
- Responsive visual tests mobile 360px, tablet 768px, desktop 1440px, keyboard navigation and no horizontal overflow.
- Comments must be paginated and indexed; no N+1 on 100+ comments; public article list pagination and full-text search with practical query constraints.
- Data sync idempotence, safe retry, batch chunk, audit, revision rollback; no false completed job status if worker not run.
- Admin activity log redacts tokens/PII; back up DB and upload assets securely with tested restoration procedure.
- Security remains risk management, not an absolute guarantee; keep production dependencies patched and review admin access periodically.

## 14. Milestones and definition of done

### M0 — Foundation and deployment feasibility
- Verify shared-host constraints or document tested compatibility matrix; Laravel+React Vite single-origin shell; environment, migrations, CI scripts, responsive design tokens, public empty states.
- Build/load static React assets successfully from Laravel/Apache. No OJK branding impersonation.

### M1 — Google auth + security + admin shell
- Socialite Google OAuth code + safe callback, reader default, admin provision CLI/authorized invitation, Sanctum policies, admin shell, footer on all screens, audit log tests.
- Without Google Client ID/secret, use safe mocked OAuth in tests only and clear environment setup instruction; never claim live login verified.

### M2 — Public financial dashboard foundation
- Real datasets via controlled CSV/XLSX import; seven modules scaffolded with meaningful behavior and proper empty states; Banking/Overview built fully; official source catalog and metadata.

### M3 — Articles, categories, authors and community
- Real article CMS CRUD/editor/workflow/SEO, public archives, search, metadata from Laravel response, social share; Google-authenticated nested comments, likes/reports, moderation and security tests.

### M4 — Data automation & advanced risk
- One-click and scheduled sync adapters for proven supported sources, staging/review, EWS and scenarios; version management and reproducible insights.

### M5 — Release, polish & documentation
- Full responsive/accessibility/SEO/security review, core web vitals, role tests, e2e with mock identity, data QA, backup/restore, cPanel deployment guide, architecture diagrams, release checklist and a 2–3-minute PCAM portfolio demo script.

**Do not mark any milestone done if untested or if critical dependencies are missing.** Provide an honest list of completed tasks, command output/CI evidence, remaining gaps, and source version status.
