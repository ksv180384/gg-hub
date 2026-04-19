<?php

namespace Domains\Tag\Models;

use App\Core\Traits\HasFilter;
use App\Models\User;
use Domains\Character\Models\Character;
use Domains\Guild\Models\Guild;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class Tag extends Model
{
    use HasFilter;

    protected $fillable = [
        'name',
        'slug',
        'is_hidden',
        'used_by_user_id',
        'used_by_guild_id',
        'created_by_user_id',
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
            if (is_string($model->name)) {
                $model->name = trim($model->name);
            }
            if (isset($model->slug) && is_string($model->slug)) {
                $model->slug = trim($model->slug);
            }
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

    /**
     * Только «видимые» теги: без скрытых админом. Применяется к загрузкам тегов
     * для публичного отображения (карточки гильдий, состав, страницы персонажей/гильдии,
     * настройки гильдии). Для админского списка — не использовать.
     */
    public function scopeNotHidden(Builder $query): Builder
    {
        return $query->where('is_hidden', false);
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function usedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'used_by_user_id');
    }

    public function usedByGuild(): BelongsTo
    {
        return $this->belongsTo(Guild::class, 'used_by_guild_id');
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
