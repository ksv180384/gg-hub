<?php

namespace Domains\Event\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventHistoryTitle extends Model
{
    protected $fillable = [
        'name',
        'dkp_base_points',
        'distribute_dkp_to_participants',
    ];

    protected function casts(): array
    {
        return [
            'dkp_base_points' => 'integer',
            'distribute_dkp_to_participants' => 'boolean',
        ];
    }

    public function histories(): HasMany
    {
        return $this->hasMany(EventHistory::class, 'event_history_title_id');
    }
}

