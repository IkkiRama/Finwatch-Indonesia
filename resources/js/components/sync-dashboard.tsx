import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import { ArrowRight, CheckCircle2, CircleHelp, RefreshCw } from 'lucide-react';
import { api, csrf, errorMessage, type Paginator, type Source } from '../lib/api';
import { formatDate } from '../lib/utils';
import { AdminPageHeading } from './admin';
import { Button } from './ui/button';
import { EmptyState, ErrorState, LoadingState, StatusBadge } from './shared';
import './sync-dashboard.css';

type SyncRun = { id: number; status: string; progress: number; attempt: number; version_id: number | null; source: { name: string; code: string }; created_at: string; finished_at: string | null; logs: { at: string; status: string; message: string }[] };
type SyncPage = Paginator<SyncRun> & { worker: { active: boolean; last_seen_at: string | null; pending_jobs: number; reserved_jobs: number } };
const active = ['queued', 'retrying', 'checking', 'downloading', 'parsing', 'validating'];
const labels: Record<string, string> = { queued: 'Menunggu pekerja', checking: 'Memeriksa sumber', downloading: 'Mengunduh berkas', parsing: 'Membaca tabel', validating: 'Memeriksa isi', retrying: 'Menunggu percobaan ulang', needs_review: 'Siap ditinjau', no_new_data: 'Berkas belum berubah', manual_required: 'Perlu unggah berkas', failed: 'Perlu diperbaiki', success: 'Selesai' };
const stages = ['Antrean', 'Periksa', 'Unduh', 'Baca', 'Validasi', 'Tinjau'];

export function SyncDashboard() {
  const [page, setPage] = useState(1); const [message, setMessage] = useState(''); const [busy, setBusy] = useState(false);
  const runs = useQuery({ queryKey: ['admin', 'sync', page], queryFn: async () => (await api.get<SyncPage>('/admin/sync/runs', { params: { page } })).data, refetchInterval: query => query.state.data?.data.some(run => active.includes(run.status)) ? 3000 : 10000 });
  const sources = useQuery({ queryKey: ['sources'], queryFn: async () => (await api.get<{ data: Source[] }>('/sources')).data.data });
  async function run(sourceId?: number, retry?: number) {
    setBusy(true);
    try { await csrf(); await api.post(retry ? `/admin/sync/runs/${retry}/retry` : '/admin/sync/runs', { source_id: sourceId }); setMessage('Permintaan tersimpan. Status diperbarui otomatis saat pekerja menjalankannya.'); await runs.refetch(); }
    catch (failure) { setMessage(errorMessage(failure)); } finally { setBusy(false); }
  }
  const worker = runs.data?.worker;
  return <div className="sync-dashboard"><AdminPageHeading title="Dari sumber resmi ke data yang siap dibaca" description="Periksa berkas sumber, lihat hasil validasinya, lalu tinjau sebelum menerbitkan. Angka di situs berubah setelah versi data disetujui." />
    <section className={`sync-worker ${worker?.active ? 'is-active' : ''}`} aria-label="Kondisi pekerja antrean"><div><span className="sync-live-dot" /><strong>{runs.isPending ? 'Memeriksa pekerja…' : worker?.active ? 'Pekerja antrean aktif' : 'Pekerja antrean belum terdeteksi'}</strong><p>{worker?.active ? `${worker.pending_jobs} menunggu · ${worker.reserved_jobs} sedang ditangani. Pemeriksaan terakhir ${formatDate(worker.last_seen_at)}.` : 'Permintaan dapat tersimpan, tetapi belum bergerak sampai pekerja dijalankan. Jalankan aplikasi melalui scripts/start.ps1 agar server, pekerja, dan penjadwal aktif bersama.'}</p></div><Button variant="outline" size="sm" onClick={() => void runs.refetch()}><RefreshCw size={15} />Periksa lagi</Button></section>
    <div className="sync-section-title"><div><h2>Pilih sumber yang ingin diperiksa</h2><p>Satu pemeriksaan tidak menjamin ada rilis baru yang dapat diimpor.</p></div><Button disabled={busy} onClick={() => void run()}><RefreshCw size={16} />Periksa semua sumber</Button></div>
    {message && <p className="form-notice" role="status">{message}</p>}
    <div className="sync-source-grid">{sources.data?.map(source => { const running = runs.data?.data.some(item => item.source.code === source.code && active.includes(item.status)); return <article key={source.id}><span>{source.code}</span><h3>{source.name}</h3><p>{source.code === 'OJK' ? 'Profil unduhan arsip SPI yang telah diverifikasi. Publikasi lain perlu pemetaan berkas.' : 'Publikasi tersedia sebagai rujukan. Integrasi otomatis belum dikonfigurasi untuk data ini.'}</p><Button variant="outline" size="sm" disabled={busy || running} onClick={() => void run(source.id)}>{running ? 'Sedang dalam antrean' : 'Periksa sumber'}<ArrowRight size={14} /></Button></article>; })}</div>
    <details className="sync-explainer"><summary><CircleHelp size={19} />Apa maksud “perlu unggah berkas”?</summary><p>FINWATCH belum memiliki pengambil data otomatis yang cocok untuk publikasi tersebut. Pengelola mengunduh tabel asli dari situs resmi, menyesuaikan kolom ke template CSV/XLSX, lalu mengunggahnya pada Dataset & validasi. Sumber, periode, satuan, dan tanggal rilis ikut dicatat. Setelah lolos pemeriksaan, versi itu tetap perlu disetujui sebelum tampil di situs.</p><Link to="/admin/datasets">Buka Dataset & validasi <ArrowRight size={15} /></Link></details>
    <div className="sync-section-title"><div><h2>Riwayat pemeriksaan</h2><p>Status dan catatan berasal dari proses yang benar-benar berjalan.</p></div></div>
    {runs.isPending ? <LoadingState /> : runs.isError ? <ErrorState retry={() => void runs.refetch()} /> : <>{runs.data.data.map(item => <article className="sync-run" key={item.id}><header><div><span>PROSES #{item.id}</span><h3>{item.source.code} <small>{item.source.name}</small></h3></div><StatusBadge tone={item.status === 'failed' || item.status === 'manual_required' ? 'amber' : ['success', 'needs_review', 'no_new_data'].includes(item.status) ? 'green' : 'neutral'}>{labels[item.status] || item.status}</StatusBadge></header>
      {active.includes(item.status) && <><ol className="sync-stages">{stages.map((stage, index) => <li key={stage} className={item.progress >= [0, 10, 25, 45, 65, 100][index] ? 'reached' : ''}>{stage}</li>)}</ol><progress value={item.progress} max={100} aria-label={`Progres ${item.source.code}`} /></>}
      <p className="sync-run-outcome">{item.status === 'queued' ? worker?.active ? 'Pekerja aktif. Permintaan menunggu giliran diproses.' : 'Antrean belum dijalankan karena pekerja tidak aktif.' : item.logs.at(-1)?.message || 'Belum ada catatan proses.'}</p><p className="sync-run-meta">Percobaan {item.attempt} · diminta {formatDate(item.created_at)}{item.finished_at ? ` · selesai ${formatDate(item.finished_at)}` : ''}</p>
      {item.status === 'needs_review' && <Link className="sync-review" to="/admin/datasets"><CheckCircle2 size={17} />Tinjau dan setujui versi data <ArrowRight size={15} /></Link>}
      {item.status === 'manual_required' && <Link className="sync-review" to="/admin/datasets">Siapkan berkas dan unggah <ArrowRight size={15} /></Link>}
      {item.status === 'failed' && <Button size="sm" variant="outline" disabled={busy} onClick={() => void run(undefined, item.id)}>Coba lagi setelah diperbaiki</Button>}
      <details><summary>Lihat catatan proses ({item.logs.length})</summary>{item.logs.length ? <ol className="sync-log">{item.logs.map((log, index) => <li key={index}><time>{formatDate(log.at)}</time><strong>{labels[log.status] || log.status}</strong><p>{log.message}</p></li>)}</ol> : <p>Pekerja belum mulai menjalankan permintaan ini.</p>}</details></article>)}{!runs.data.data.length && <EmptyState title="Belum ada pemeriksaan">Pilih satu sumber di atas untuk memulai.</EmptyState>}<div className="pagination"><span>{runs.data.total} proses</span><div><Button variant="outline" disabled={page <= 1} onClick={() => setPage(page - 1)}>Sebelumnya</Button><Button variant="outline" disabled={page >= runs.data.last_page} onClick={() => setPage(page + 1)}>Berikutnya</Button></div></div></>}
  </div>;
}
