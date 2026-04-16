<?php

namespace App\Models;

use App\Notifications\VerifyEmailNotification;
use Domains\Access\Models\Permission;
use Domains\Access\Models\Role;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    public const ROLE_ADMIN_SLUG = 'admin';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'avatar',
        'timezone',
        'banned_at',
        'provider',
        'provider_id',
        'email_verified_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

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
            'banned_at' => 'datetime',
        ];
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyEmailNotification());
    }

    public function isEmailRegistered(): bool
    {
        return $this->provider === null;
    }

    public function isBanned(): bool
    {
        return $this->banned_at !== null;
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_role');
    }

    public function directPermissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'user_permission');
    }

    public function characters(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\Domains\Character\Models\Character::class);
    }

    public function notifications(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\Notification::class);
    }

    /**
     * Все slug прав пользователя (из ролей + прямые). Роль admin даёт все права.
     */
    public function getAllPermissionSlugs(): array
    {
        $isAdmin = $this->roles()->where('slug', self::ROLE_ADMIN_SLUG)->exists();
        if ($isAdmin) {
            return Permission::pluck('slug')->all();
        }
        $fromRoles = $this->roles()->with('permissions')->get()->flatMap->permissions->pluck('slug')->unique()->values()->all();
        $direct = $this->directPermissions()->pluck('slug')->all();
        return array_values(array_unique(array_merge($fromRoles, $direct)));
    }
}
