<?php

namespace Domains\Access\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class PermissionGroup extends Model
{
    protected $table = 'permission_groups';

    protected $fillable = [
        'name',
        'slug',
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

    public function permissions(): HasMany
    {
        return $this->hasMany(Permission::class, 'permission_group_id');
    }
}
