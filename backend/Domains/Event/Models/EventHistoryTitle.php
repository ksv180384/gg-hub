<?php

namespace Domains\Event\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventHistoryTitle extends Model
{
    protected $fillable = [
        'name',
        'dkp_base_points',
    ];

    protected function casts(): array
    {
        return [
            'dkp_base_points' => 'integer',
        ];
    }

    public function histories(): HasMany
    {
        return $this->hasMany(EventHistory::class, 'event_history_title_id');
    }
}

