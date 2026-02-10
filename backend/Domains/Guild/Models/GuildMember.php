<?php

namespace Domains\Guild\Models;

use Domains\Character\Models\Character;
use Domains\Access\Models\GuildRole;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GuildMember extends Model
{
    protected $fillable = [
        'guild_id',
        'character_id',
        'guild_role_id',
        'joined_at',
    ];

    protected function casts(): array
    {
        return [
            'joined_at' => 'datetime',
        ];
    }

    public function guild(): BelongsTo
    {
        return $this->belongsTo(Guild::class);
    }

    public function character(): BelongsTo
    {
        return $this->belongsTo(Character::class);
    }

    public function guildRole(): BelongsTo
    {
        return $this->belongsTo(GuildRole::class, 'guild_role_id');
    }
}
