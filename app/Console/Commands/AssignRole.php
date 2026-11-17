<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\SocialIdentity;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AssignRole extends Command
{
    protected $signature = 'finwatch:assign-role {subject : Subject Google akun terverifikasi} {role : Role selain super_admin} {--revoke : Cabut role tersebut}';

    protected $description = 'Kelola role melalui CLI tepercaya, diaudit, dan cabut semua sesi akun yang diubah.';

    public function handle(): int
    {
        $role = Role::where('name', (string) $this->argument('role'))->first();
        $identity = SocialIdentity::where('provider', 'google')->where('provider_subject', (string) $this->argument('subject'))->first();

        if (! $role || $role->name === 'super_admin' || ! $identity || ! $identity->verified_email_at) {
            $this->error('Role atau identitas tidak valid. Gunakan finwatch:provision-super-admin untuk kepemilikan administrator.');

            return self::FAILURE;
        }

        $changed = DB::transaction(function () use ($role, $identity): bool {
            $user = User::whereKey($identity->user_id)->lockForUpdate()->firstOrFail();

            if ($user->status !== 'active') {
                return false;
            }

            $previous = $user->roles()->orderBy('name')->pluck('name')->all();

            if ($this->option('revoke')) {
                $user->roles()->detach($role->id);
            } else {
                $user->roles()->syncWithoutDetaching([$role->id]);
            }

            $user->forceFill(['auth_version' => $user->auth_version + 1])->save();
            DB::table('sessions')->where('user_id', $user->id)->delete();
            AuditService::record('roles.changed', null, [
                'channel' => 'cli',
                'target_user_id' => $user->id,
                'previous_roles' => $previous,
                'roles' => $user->roles()->orderBy('name')->pluck('name')->all(),
            ]);

            return true;
        });

        if (! $changed) {
            $this->error('Akun tidak aktif. Tidak ada perubahan izin.');

            return self::FAILURE;
        }

        $this->info('Perubahan role diaudit dan sesi lama dicabut.');

        return self::SUCCESS;
    }
}
