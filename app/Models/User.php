<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'password'];

    protected $hidden = [
        'email', 'email_verified_at', 'password', 'remember_token',
        'admin_totp_secret', 'admin_totp_last_step', 'auth_version',
    ];

    protected $attributes = ['status' => 'active', 'auth_version' => 1];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'last_login_at' => 'immutable_datetime',
            'admin_totp_secret' => 'encrypted',
            'admin_totp_last_step' => 'integer',
            'auth_version' => 'integer',
        ];
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class)->withTimestamps();
    }

    public function socialIdentities(): HasMany
    {
        return $this->hasMany(SocialIdentity::class);
    }

    public function hasRole(string $role): bool
    {
        return $this->roles()->where('name', $role)->exists();
    }

    public function hasPermission(string $permission): bool
    {
        return $this->status === 'active' && $this->roles()
            ->whereHas('permissions', fn (Builder $query): Builder => $query->where('name', $permission))
            ->exists();
    }

    /** @return list<string> */
    public function permissionNames(): array
    {
        if ($this->status !== 'active') {
            return [];
        }

        return DB::table('permissions')
            ->join('permission_role', 'permissions.id', '=', 'permission_role.permission_id')
            ->join('role_user', 'permission_role.role_id', '=', 'role_user.role_id')
            ->where('role_user.user_id', $this->id)
            ->distinct()->orderBy('permissions.name')->pluck('permissions.name')->all();
    }
}
