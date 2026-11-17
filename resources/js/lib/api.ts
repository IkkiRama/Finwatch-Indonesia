import axios from 'axios';
export const api = axios.create({ baseURL: '/api/v1', withCredentials: true, withXSRFToken: true, headers: { Accept: 'application/json' } });
export async function csrf() { await axios.get('/sanctum/csrf-cookie', { withCredentials: true }); }
export type User = { id: number; name: string; avatar_url: string | null; roles: string[]; permissions: string[] };
export type Identity = { authenticated: boolean; user: User | null; google_configured: boolean; admin_mfa_required: boolean; admin_mfa_verified: boolean; admin_mfa_enrolled?: boolean };
export type Source = { id: number; code: string; name: string; url: string; method: string; status: string; last_checked_at: string | null; description: string };
export type Overview = { published_datasets: number; observations: number; latest_period: string | null; years: number[]; sources_count: number };
export type AuditEntry = { id: number; event: string; actor_name: string | null; created_at: string; metadata: Record<string, unknown> | null };
export type Paginator<T> = { data: T[]; current_page: number; last_page: number; total: number; from: number | null; to: number | null };
export type QueueWorkerStatus = { active: boolean; last_seen_at: string | null; pending_jobs: number; reserved_jobs: number };
export type AdminOverview = { metrics: { sources: number; datasets: number; observations: number; failed_jobs: number; published_datasets: number }; recent_activity: AuditEntry[]; environment: { queue: string; worker?: QueueWorkerStatus; database?: string; scheduler?: string; php?: string; framework?: string } };
export function errorMessage(error: unknown, fallback = 'Permintaan belum berhasil. Silakan coba kembali.') {
  if (axios.isAxiosError(error) && typeof error.response?.data?.message === 'string') return error.response.data.message;
  return fallback;
}

