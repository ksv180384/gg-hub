<?php

namespace Domains\Event\Models;

use Domains\Guild\Models\Guild;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventHistory extends Model
{
    protected $fillable = [
        'guild_id',
        'title',
        'description',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
        ];
    }

    public function guild(): BelongsTo
    {
        return $this->belongsTo(Guild::class);
    }

    public function participants(): HasMany
    {
        return $this->hasMany(EventHistoryParticipant::class);
    }

    public function screenshots(): HasMany
    {
        return $this->hasMany(EventHistoryScreenshot::class);
    }
}

