<?php

namespace Domains\Access\Models;

use Domains\Access\Enums\PermissionScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

/**
 * Право в системе. Доступ по slug. scope: site — права пользователей, guild — права гильдии.
 */
class Permission extends Model
{
    protected $fillable = [
        'scope',
        'permission_group_id',
        'name',
        'slug',
        'description',
    ];

    protected $casts = [
        'scope' => PermissionScope::class,
    ];

    protected static function booted(): void
    {
        static::saving(function (self $model): void {
            if ($model->shouldFillSlugFromName()) {
                $model->slug = Str::slug($model->name);
            }
        });
    }

    private function shouldFillSlugFromName(): bool
    {
        $slug = $this->slug ?? '';
        $name = $this->name ?? '';
        return is_string($name) && $name !== '' && (trim((string) $slug) === '');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(PermissionGroup::class, 'permission_group_id');
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_permission');
    }
}
