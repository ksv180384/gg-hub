<?php

namespace Domains\Notification\Models;

use Domains\Game\Models\Game;
use Domains\Guild\Models\Guild;
use Domains\User\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    protected $fillable = [
        'user_id',
        'game_id',
        'guild_id',
        'message',
        'link',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function guild(): BelongsTo
    {
        return $this->belongsTo(Guild::class)->withTrashed();
    }

    public function isRead(): bool
    {
        return $this->read_at !== null;
    }
}
