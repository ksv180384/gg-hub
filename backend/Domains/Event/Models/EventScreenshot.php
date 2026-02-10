<?php

namespace Domains\Event\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventScreenshot extends Model
{
    protected $fillable = [
        'event_id',
        'path',
        'title',
        'sort_order',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
