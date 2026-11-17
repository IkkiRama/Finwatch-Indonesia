<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class SystemController extends Controller
{
    public function candidates(): Response
    {
        return response()->json(['data' => User::where('status', 'active')->whereHas('roles.permissions', fn ($q) => $q->where('name', 'articles.create'))->orderBy('name')->limit(500)->get(['id', 'name'])]);
    }

    public function updateUser(Request $request, User $user): Response
    {
        Gate::authorize('updateRole', $user);
        abort_if($user->hasRole('super_admin'), 403, 'Akun pemilik hanya dapat dikelola melalui CLI server.');
        $input = $request->validate(['roles' => 'required|array|min:1|max:5', 'roles.*' => ['required', Rule::in(['reader', 'author', 'editor', 'moderator', 'admin'])], 'status' => 'required|in:active,blocked']);
        DB::transaction(function () use ($user, $input, $request): void {
            $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            abort_if($user->hasRole('super_admin'), 403);
            $previous = $user->roles()->pluck('name')->all();
            $user->roles()->sync(Role::whereIn('name', $input['roles'])->pluck('id'));
            $user->forceFill(['status' => $input['status'], 'auth_version' => $user->auth_version + 1])->save();
            DB::table('sessions')->where('user_id', $user->id)->delete();
            AuditService::record('roles.changed', $request->user(), ['target_user_id' => $user->id, 'previous_roles' => $previous, 'roles' => $input['roles'], 'status' => $input['status']]);
        });

        return response()->json(['message' => 'Izin diperbarui; seluruh sesi lama akun dicabut.']);
    }

    public function settings(): Response
    {
        $values = DB::table('system_settings')->whereIn('key', ['site_tagline', 'comments_enabled', 'sync_enabled'])->pluck('value', 'key')->map(fn ($value) => json_decode($value, true));

        return response()->json(['data' => array_replace(['site_tagline' => 'Data publik. Konteks terbuka.', 'comments_enabled' => true, 'sync_enabled' => true], $values->all())]);
    }

    public function saveSettings(Request $request): Response
    {
        $input = $request->validate(['site_tagline' => 'required|string|max:200', 'comments_enabled' => 'required|boolean', 'sync_enabled' => 'required|boolean']);
        DB::transaction(function () use ($input, $request): void {
            foreach ($input as $key => $value) {
                DB::table('system_settings')->updateOrInsert(['key' => $key], ['value' => json_encode($value), 'created_at' => now(), 'updated_at' => now()]);
                AuditService::record('settings.changed', $request->user(), ['setting' => $key]);
            }
        });

        return response()->json(['data' => $input]);
    }
}
