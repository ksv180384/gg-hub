<?php

namespace Domains\Event\Models;

use Domains\Character\Models\Character;
use Domains\Guild\Models\Guild;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Event extends Model
{
    protected $fillable = [
        'guild_id',
        'created_by_character_id',
        'title',
        'description',
        'starts_at',
        'ends_at',
        'recurrence',
        'recurrence_ends_at',
        'send_discord_notification',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'recurrence_ends_at' => 'datetime',
            'send_discord_notification' => 'boolean',
        ];
    }

    public function guild(): BelongsTo
    {
        return $this->belongsTo(Guild::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Character::class, 'created_by_character_id');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(EventParticipant::class, 'event_id');
    }

    public function screenshots(): HasMany
    {
        return $this->hasMany(EventScreenshot::class, 'event_id');
    }
}
