<?php

namespace Domains\User\Models;

use App\Notifications\ResetPasswordNotification;
use App\Notifications\VerifyEmailNotification;
use Database\Factories\UserFactory;
use Domains\Access\Models\Permission;
use Domains\Access\Models\Role;
use Domains\Character\Models\Character;
use Domains\Guild\Models\GuildMember;
use Domains\Notification\Models\Notification;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Log;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
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
        'theme_preference',
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
    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'banned_at' => 'datetime',
            'first_login_at' => 'datetime',
        ];
    }

    public function sendEmailVerificationNotification(): void
    {
        try {
            $this->notify(new VerifyEmailNotification);
        } catch (\Throwable $e) {
            Log::error('Failed to send email verification', [
                'user_id' => $this->id,
                'email' => $this->email,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function sendPasswordResetNotification(mixed $token): void
    {
        $this->notify(new ResetPasswordNotification($token));
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

    public function characters(): HasMany
    {
        return $this->hasMany(Character::class);
    }

    /**
     * ID гильдий, в которых пользователь состоит через любого из своих персонажей.
     *
     * @return array<int, int>
     */
    public function guildIds(): array
    {
        return GuildMember::query()
            ->whereIn('character_id', function ($q) {
                $q->select('id')
                    ->from('characters')
                    ->where('user_id', $this->id);
            })
            ->distinct()
            ->pluck('guild_id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
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
