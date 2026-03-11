<?php

namespace Domains\Post\Models;

use App\Models\User;
use Domains\Character\Models\Character;
use Domains\Game\Models\Game;
use Domains\Guild\Models\Guild;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Модель пользовательского поста.
 *
 * Основные поля:
 * - user_id          — пользователь, создавший пост
 * - character_id     — персонаж, от чьего имени написан пост (опционально)
 * - guild_id         — гильдия, к которой относится пост (если пост гильдейский или «от имени гильдии»)
 * - game_id          — игра, к которой относится пост (опционально)
 * - title            — заголовок поста (опционален)
 * - body             — основной текст поста
 * - is_visible_global  — флаг отображения поста в общих постах (раздел «Общие»)
 * - is_visible_guild   — флаг отображения поста в разделе гильдии
 * - is_anonymous       — пост анонимен везде, где он показывается (имя автора/персонажа скрыто)
 * - is_global_as_guild — при публикации в «Общие» пост показывается от имени гильдии, а не конкретного пользователя
 * - status_global      — статус отображения поста в разделе «Общие» (pending/published/draft/hidden)
 * - status_guild       — статус отображения поста в разделе гильдии (pending/published/draft/hidden)
 * - published_at_global  — дата публикации в раздел «Общие»
 * - published_at_guild   — дата публикации в раздел гильдии
 * - type  — (global/guild)
 */
class Post extends Model
{
    protected $fillable = [
        'user_id',
        'character_id',
        'guild_id',
        'game_id',
        'title',
        'body',
        'is_visible_global',
        'is_visible_guild',
        'is_anonymous',
        'is_global_as_guild',
        'status_global',
        'status_guild',
        'published_at_global',
        'published_at_guild',
        'is_published',
        'type',
        'published_at',
        'views_count',
    ];

    protected function casts(): array
    {
        return [
            'is_visible_global' => 'boolean',
            'is_visible_guild' => 'boolean',
            'is_anonymous' => 'boolean',
            'is_global_as_guild' => 'boolean',
            'published_at_global' => 'datetime',
            'published_at_guild' => 'datetime',
            'is_published' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function character(): BelongsTo
    {
        return $this->belongsTo(Character::class);
    }

    public function guild(): BelongsTo
    {
        return $this->belongsTo(Guild::class);
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }
}
