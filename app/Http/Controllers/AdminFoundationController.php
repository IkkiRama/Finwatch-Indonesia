<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\DataSource;
use App\Models\Role;
use App\Models\User;
use App\Services\QueueHealth;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminFoundationController extends Controller
{
    public function overview(Request $request): JsonResponse
    {
        return response()->json([
            'metrics' => ['sources' => DataSource::count(), 'datasets' => DB::table('datasets')->count(), 'published_datasets' => DB::table('datasets')->where('status', 'published')->where('published_at', '<=', now())->count(), 'observations' => DB::table('indicator_observations')->count(), 'failed_jobs' => DB::table('failed_jobs')->count()],
            'recent_activity' => $request->user()->can('audit.view')
                ? AuditLog::with('actor:id,name')->latest('id')->limit(6)->get()->map(fn (AuditLog $log): array => $this->auditRecord($log))
                : [],
            'publication' => ['drafts' => DB::table('articles')->where('status', 'draft')->whereNull('deleted_at')->count(), 'scheduled' => DB::table('articles')->where('status', 'scheduled')->whereNull('deleted_at')->count(), 'published' => DB::table('articles')->where('status', 'published')->whereNull('deleted_at')->where('published_at', '<=', now())->count(), 'pending_comments' => DB::table('comments')->where('status', 'pending')->whereNull('deleted_at')->count(), 'reports' => DB::table('comment_reports')->where('status', 'open')->count(), 'versions_awaiting_review' => DB::table('dataset_versions')->where('status', 'needs_review')->count()],
            'environment' => ['queue' => config('queue.default'), 'worker' => QueueHealth::status(), 'milestone' => 'M2–M5', 'sync_available' => true, 'cron_status' => DB::table('system_heartbeats')->where('key', 'scheduler')->value('seen_at') ?? 'unverified'],
        ]);
    }

    private function auditRecord(AuditLog $log): array
    {
        return ['id' => $log->id, 'event' => $log->event, 'actor_name' => $log->actor?->name ?? 'Sistem / CLI', 'created_at' => $log->created_at?->toIso8601String(), 'metadata' => $log->metadata];
    }

    public function auditLogs(Request $request): JsonResponse
    {
        $input = $request->validate(['query' => 'nullable|string|max:100', 'event' => 'nullable|string|max:80', 'sort' => 'nullable|in:asc,desc', 'page' => 'nullable|integer|min:1|max:10000']);
        $logs = AuditLog::with('actor:id,name')
            ->when($input['query'] ?? null, fn ($query, $term) => $query->where(fn ($search) => $search->where('event', 'like', '%'.$term.'%')->orWhereHas('actor', fn ($actor) => $actor->where('name', 'like', '%'.$term.'%'))))
            ->when($input['event'] ?? null, fn ($query, $event) => $query->where('event', $event))
            ->orderBy('id', $input['sort'] ?? 'desc')->paginate(15);
        $logs->through(fn (AuditLog $log): array => $this->auditRecord($log));

        return response()->json($logs);
    }

    public function users(Request $request): JsonResponse
    {
        $request->validate(['page' => 'nullable|integer|min:1|max:10000']);
        $users = User::with('roles:id,name')->orderBy('id')->paginate(20);
        $users->through(fn (User $user): array => ['id' => $user->id, 'name' => $user->name, 'status' => $user->status, 'roles' => $user->roles->pluck('name'), 'last_login_at' => $user->last_login_at?->toIso8601String()]);

        return response()->json($users);
    }

    public function roles(): JsonResponse
    {
        return response()->json(['data' => Role::with('permissions:id,name')->get()->map(fn (Role $role): array => ['name' => $role->name, 'permissions' => $role->permissions->pluck('name')])]);
    }

    public function health(): JsonResponse
    {
        DB::select('SELECT 1');

        return response()->json(['database' => 'connected', 'queue' => config('queue.default'), 'worker' => QueueHealth::status(), 'scheduler' => DB::table('system_heartbeats')->where('key', 'scheduler')->value('seen_at') ?? 'unverified', 'failed_jobs' => DB::table('failed_jobs')->count(), 'queued_jobs' => DB::table('jobs')->count(), 'google_configured' => filled(config('services.google.client_id')) && filled(config('services.google.client_secret')), 'mfa_required' => (bool) config('finwatch.admin_mfa_required'), 'milestone' => 'M2–M5', 'sync_available' => true]);
    }
}
