import { lazy, useEffect, type ReactNode } from 'react';
import { createRoot } from 'react-dom/client';
import { QueryCache, QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { isAxiosError } from 'axios';
import { BrowserRouter, Route, Routes, useLocation } from 'react-router-dom';
import { AboutPage, analysisModules, NotFoundPage, PublicLayout, SourcesPage, useIdentity } from './components/public';
import { AdminLayout, AdminOverviewPage, AuditPage, HealthPage, LoginPage } from './components/admin';
import { EmptyState } from './components/shared';
import '../css/pastel.css';

const AnalyticsPage=lazy(()=>import('./components/analytics').then(module=>({default:module.AnalyticsPage})));
const ExplorerPage=lazy(()=>import('./components/analytics').then(module=>({default:module.ExplorerPage})));
const StressPage=lazy(()=>import('./components/analytics').then(module=>({default:module.StressPage})));
const ProvinceComparisonPage=lazy(()=>import('./components/province-comparison').then(module=>({default:module.ProvinceComparisonPage})));
const ArticlesPage=lazy(()=>import('./components/articles').then(module=>({default:module.ArticlesPage})));
const ArticlePage=lazy(()=>import('./components/articles').then(module=>({default:module.ArticlePage})));
const ArticleEditor=lazy(()=>import('./components/content-admin').then(module=>({default:module.ArticleEditor})));
const ArticlesAdmin=lazy(()=>import('./components/content-admin').then(module=>({default:module.ArticlesAdmin})));
const MediaLibrary=lazy(()=>import('./components/content-admin').then(module=>({default:module.MediaLibrary})));
const ModerationAdmin=lazy(()=>import('./components/content-admin').then(module=>({default:module.ModerationAdmin})));
const DatasetsAdmin=lazy(()=>import('./components/workspace').then(module=>({default:module.DatasetsAdmin})));
const IndicatorAdmin=lazy(()=>import('./components/workspace').then(module=>({default:module.IndicatorAdmin})));
const RiskAdmin=lazy(()=>import('./components/workspace').then(module=>({default:module.RiskAdmin})));
const ScenariosAdmin=lazy(()=>import('./components/workspace').then(module=>({default:module.ScenariosAdmin})));
const SettingsAdmin=lazy(()=>import('./components/workspace').then(module=>({default:module.SettingsAdmin})));
const SourcesAdmin=lazy(()=>import('./components/workspace').then(module=>({default:module.SourcesAdmin})));
const SynchronizationAdmin=lazy(()=>import('./components/workspace').then(module=>({default:module.SynchronizationAdmin})));
const TaxonomyAdmin=lazy(()=>import('./components/workspace').then(module=>({default:module.TaxonomyAdmin})));
const UserAccessAdmin=lazy(()=>import('./components/workspace').then(module=>({default:module.UserAccessAdmin})));

function NavigationEffects() {
  const location = useLocation();
  useEffect(() => {
    const publicTitle = analysisModules.find(item => item.path === location.pathname)?.title;
    const title = publicTitle || ({ '/artikel': 'Edukasi Keuangan', '/sumber-data': 'Sumber Data', '/jelajah-data': 'Jelajah Data', '/tentang': 'Tentang', '/masuk': 'Masuk' } as Record<string, string>)[location.pathname] || (location.pathname.startsWith('/admin') ? 'Workspace' : location.pathname.startsWith('/artikel/') ? 'Ruang baca' : location.pathname.startsWith('/preview/') ? 'Pratinjau privat' : 'Halaman tidak ditemukan');
    document.title = `${title} | FINWATCH Indonesia`;
    window.scrollTo({ top: 0, behavior: 'instant' });
  }, [location.pathname]);
  return null;
}
export function AppRoutes() {
  return <><NavigationEffects /><Routes><Route element={<PublicLayout />}>
    <Route index element={<AnalyticsPage overview />} />
    {['/perbankan','/pemantauan-risiko','/peringatan-dini','/inklusi-dan-konsumen'].map(path => <Route path={path} element={<AnalyticsPage />} key={path} />)}
    <Route path="/bandingkan-provinsi" element={<ProvinceComparisonPage />} /><Route path="/simulasi-stres" element={<StressPage />} /><Route path="/riset" element={<ArticlesPage research />} />
    <Route path="/artikel" element={<ArticlesPage />} /><Route path="/artikel/:slug" element={<ArticlePage />} /><Route path="/preview/articles/:article" element={<ArticlePage />} />
    <Route path="/kategori/:slug" element={<ArticlesPage />} /><Route path="/penulis/:slug" element={<ArticlesPage />} />
    <Route path="/jelajah-data" element={<ExplorerPage />} /><Route path="/sumber-data" element={<SourcesPage />} /><Route path="/tentang" element={<AboutPage />} /><Route path="/masuk" element={<LoginPage />} /><Route path="*" element={<NotFoundPage />} />
  </Route><Route path="/admin" element={<AdminLayout />}><Route index element={<AdminOverviewPage />} />
    <Route path="sources" element={<PermissionPage permission="sources.view"><SourcesAdmin /></PermissionPage>} />
    <Route path="synchronization" element={<PermissionPage permission="sources.manage"><SynchronizationAdmin /></PermissionPage>} />
    <Route path="datasets" element={<PermissionPage permission="data.manage"><DatasetsAdmin /></PermissionPage>} />
    <Route path="indicators" element={<PermissionPage permission="data.manage"><IndicatorAdmin /></PermissionPage>} />
    <Route path="risk" element={<PermissionPage permission="data.manage"><RiskAdmin /></PermissionPage>} />
    <Route path="scenarios" element={<PermissionPage permission="data.manage"><ScenariosAdmin /></PermissionPage>} />
    <Route path="research" element={<PermissionPage permission="articles.create"><ArticlesAdmin /></PermissionPage>} />
    <Route path="articles" element={<PermissionPage permission="articles.create"><ArticlesAdmin /></PermissionPage>} />
    <Route path="articles/new" element={<PermissionPage permission="articles.create"><ArticleEditor /></PermissionPage>} />
    <Route path="articles/:articleId" element={<PermissionPage permission="articles.create"><ArticleEditor /></PermissionPage>} />
    <Route path="community" element={<PermissionPage permission="categories.manage"><TaxonomyAdmin /></PermissionPage>} />
    <Route path="media" element={<PermissionPage permission="articles.create"><MediaLibrary /></PermissionPage>} />
    <Route path="moderation" element={<PermissionPage permission="comments.manage"><ModerationAdmin /></PermissionPage>} />
    <Route path="audit-logs" element={<PermissionPage permission="audit.view"><AuditPage /></PermissionPage>} />
    <Route path="health" element={<PermissionPage permission="system.view"><HealthPage /></PermissionPage>} />
    <Route path="users" element={<PermissionPage permission="users.manage"><UserAccessAdmin /></PermissionPage>} />
    <Route path="settings" element={<PermissionPage permission="settings.manage"><SettingsAdmin /></PermissionPage>} />
    <Route path="*" element={<NotFoundPage />} />
  </Route></Routes></>;
}
function PermissionPage({permission,children}:{permission:string;children:ReactNode}) { const identity=useIdentity(); return identity.data?.user?.permissions.includes(permission)?children:<EmptyState title="Izin akses diperlukan">Akun ini tidak memiliki izin untuk membuka area tersebut. Hubungi pengelola yang berwenang.</EmptyState>; }

const rootElement = document.getElementById('app');
if (rootElement) {
  const queryClient = new QueryClient({ queryCache: new QueryCache({ onError: (error, query) => { if (query.queryKey[0] !== 'me' && isAxiosError(error) && [401, 403, 423].includes(error.response?.status || 0)) void queryClient.invalidateQueries({ queryKey: ['me'] }); } }), defaultOptions: { queries: { retry: (count, error) => count < 1 && !(isAxiosError(error) && [401, 403, 423].includes(error.response?.status || 0)), refetchOnWindowFocus: false } } });
  createRoot(rootElement).render(<QueryClientProvider client={queryClient}><BrowserRouter><AppRoutes /></BrowserRouter></QueryClientProvider>);
}

