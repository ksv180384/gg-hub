<?php

namespace Domains\Tag\Models;

use Domains\Character\Models\Character;
use Domains\Guild\Models\Guild;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class Tag extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'is_hidden',
    ];

    protected function casts(): array
    {
        return [
            'is_hidden' => 'boolean',
        ];
    }

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

    public function characters(): BelongsToMany
    {
        return $this->belongsToMany(Character::class, 'character_tag');
    }

    public function guilds(): BelongsToMany
    {
        return $this->belongsToMany(Guild::class, 'guild_tag');
    }
}
