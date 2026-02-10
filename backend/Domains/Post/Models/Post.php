<?php

namespace Domains\Post\Models;

use App\Models\User;
use Domains\Game\Models\Game;
use Domains\Guild\Models\Guild;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Post extends Model
{
    protected $fillable = [
        'user_id',
        'title',
        'body',
        'scope_type',
        'scope_id',
        'is_published',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Пост в рамках гильдии (scope_type = guild). */
    public function guild(): BelongsTo
    {
        return $this->belongsTo(Guild::class, 'scope_id')
            ->where('posts.scope_type', 'guild');
    }

    /** Пост в рамках игры (scope_type = game). */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class, 'scope_id')
            ->where('posts.scope_type', 'game');
    }
}
