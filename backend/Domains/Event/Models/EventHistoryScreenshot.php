<?php

namespace Domains\Event\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventHistoryScreenshot extends Model
{
    protected $fillable = [
        'event_history_id',
        'url',
        'title',
        'sort_order',
    ];

    public function eventHistory(): BelongsTo
    {
        return $this->belongsTo(EventHistory::class);
    }
}

