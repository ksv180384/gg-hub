<?php

namespace Domains\Event\Models;

use Domains\Character\Models\Character;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventParticipant extends Model
{
    protected $fillable = [
        'event_id',
        'character_id',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function character(): BelongsTo
    {
        return $this->belongsTo(Character::class);
    }
}
