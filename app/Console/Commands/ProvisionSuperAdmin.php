<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\SocialIdentity;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ProvisionSuperAdmin extends Command
{
    protected $signature = 'finwatch:provision-super-admin {--subject= : Subject Google dari akun yang sudah login dan terverifikasi}';

    protected $description = 'Berikan role super_admin melalui CLI tepercaya; tidak membuat akun atau menautkan berdasarkan email.';

    public function handle(): int
    {
        $subject = (string) $this->option('subject');
        $identity = SocialIdentity::where('provider', 'google')->where('provider_subject', $subject)->first();
        $role = Role::where('name', 'super_admin')->first();

        if ($subject === '' || ! $identity || ! $identity->verified_email_at || ! $role) {
            $this->error('Akun Google terverifikasi tidak ditemukan atau role belum di-seed. Login Google dahulu dan gunakan subject yang tepat.');

            return self::FAILURE;
        }

        $changed = DB::transaction(function () use ($identity, $role): bool {
            $user = User::whereKey($identity->user_id)->lockForUpdate()->firstOrFail();

            if ($user->status !== 'active') {
                return false;
            }

            $previous = $user->roles()->orderBy('name')->pluck('name')->all();
            $user->roles()->syncWithoutDetaching([$role->id]);
            $user->forceFill(['auth_version' => $user->auth_version + 1])->save();
            DB::table('sessions')->where('user_id', $user->id)->delete();
            AuditService::record('roles.super_admin_provisioned', null, [
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

        $this->info('Role super_admin diberikan dan diaudit. Sesi lama dicabut; masuk kembali lalu daftarkan authenticator via finwatch:enroll-mfa.');

        return self::SUCCESS;
    }
}
