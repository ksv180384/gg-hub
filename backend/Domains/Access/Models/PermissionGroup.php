<?php

namespace Domains\Access\Models;

use App\Core\Traits\HasFilter;
use Domains\Access\Enums\PermissionScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class PermissionGroup extends Model
{
    use HasFilter;

    protected $table = 'permission_groups';

    protected $fillable = [
        'scope',
        'name',
        'slug',
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

    public function permissions(): HasMany
    {
        return $this->hasMany(Permission::class, 'permission_group_id');
    }
}
