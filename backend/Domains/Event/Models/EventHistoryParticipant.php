<?php

namespace Domains\Event\Models;

use Domains\Character\Models\Character;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventHistoryParticipant extends Model
{
    protected $fillable = [
        'event_history_id',
        'character_id',
        'external_name',
    ];

    public function eventHistory(): BelongsTo
    {
        return $this->belongsTo(EventHistory::class);
    }

    public function character(): BelongsTo
    {
        return $this->belongsTo(Character::class);
    }
}

