<?php

namespace Domains\Guild\Models;

use Domains\Character\Models\Character;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GuildApplication extends Model
{
    protected $fillable = [
        'guild_id',
        'character_id',
        'invited_by_character_id',
        'revoked_by_character_id',
        'form_data',
        'status',
        'reviewed_at',
        'reviewed_by',
    ];

    protected function casts(): array
    {
        return [
            'form_data' => 'array',
            'reviewed_at' => 'datetime',
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

    /** Персонаж-участник гильдии, от имени которого отправлено приглашение (с правом приглашения). */
    public function invitedByCharacter(): BelongsTo
    {
        return $this->belongsTo(Character::class, 'invited_by_character_id');
    }

    /** Персонаж-участник гильдии, от имени которого отозвано приглашение. */
    public function revokedByCharacter(): BelongsTo
    {
        return $this->belongsTo(Character::class, 'revoked_by_character_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(GuildApplicationComment::class, 'guild_application_id')->orderBy('created_at');
    }

    public function votes(): HasMany
    {
        return $this->hasMany(GuildApplicationVote::class, 'guild_application_id');
    }
}
