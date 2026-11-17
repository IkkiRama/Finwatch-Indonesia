<?php

namespace App\Console\Commands;

use App\Models\SocialIdentity;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PragmaRX\Google2FA\Google2FA;

class EnrollAdminMfa extends Command
{
    protected $signature = 'finwatch:enroll-mfa {subject : Subject Google administrator} {--reset : Ganti authenticator lama dan cabut sesi}';

    protected $description = 'Daftarkan TOTP via terminal tepercaya. Secret ditampilkan satu kali di terminal, tidak dicatat di audit/log.';

    public function handle(Google2FA $authenticator): int
    {
        $identity = SocialIdentity::where('provider', 'google')->where('provider_subject', (string) $this->argument('subject'))->first();
        $user = $identity?->user;

        if (! $identity?->verified_email_at || ! $user || ! $user->hasPermission('admin.access')) {
            $this->error('Akun administrator aktif terverifikasi tidak ditemukan.');

            return self::FAILURE;
        }

        if ($user->admin_totp_secret && ! $this->option('reset')) {
            $this->error('Authenticator sudah terdaftar. Gunakan --reset hanya jika pemilik akun meminta pemulihan.');

            return self::FAILURE;
        }

        $secret = $authenticator->generateSecretKey();
        DB::transaction(function () use ($user, $secret): void {
            $locked = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $locked->forceFill([
                'admin_totp_secret' => $secret,
                'admin_totp_last_step' => 0,
                'auth_version' => $locked->auth_version + 1,
            ])->save();
            DB::table('sessions')->where('user_id', $locked->id)->delete();
            AuditService::record('auth.mfa_enrolled', null, ['channel' => 'cli', 'target_user_id' => $locked->id]);
        });

        $this->warn('Simpan secret langsung ke aplikasi authenticator pemilik akun melalui kanal privat. Jangan bagikan output ini atau menyimpannya di Git/log.');
        $this->line('Secret: '.$secret);
        $this->line('Setup URI: '.$authenticator->getQRCodeUrl('FINWATCH Indonesia', 'Administrator '.$user->id, $secret));
        $this->info('Secret terenkripsi tersimpan; sesi lama dicabut. Login ulang lalu masukkan kode 6 digit di workspace.');

        return self::SUCCESS;
    }
}
