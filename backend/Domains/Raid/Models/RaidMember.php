<?php

namespace Domains\Raid\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class RaidMember extends Pivot
{
    protected $table = 'raid_members';

    protected function casts(): array
    {
        return [
            'accepted_at' => 'datetime',
            'slot_index' => 'integer',
        ];
    }
}
