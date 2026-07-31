<?php

namespace Domains\ConstantParty\Models;

use Domains\Character\Models\Character;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConstantPartyFormerMember extends Model
{
    protected $fillable = [
        'constant_party_id',
        'character_id',
        'joined_at',
        'left_at',
    ];

    protected function casts(): array
    {
        return [
            'joined_at' => 'datetime',
            'left_at' => 'datetime',
        ];
    }

    public function constantParty(): BelongsTo
    {
        return $this->belongsTo(ConstantParty::class);
    }

    public function character(): BelongsTo
    {
        return $this->belongsTo(Character::class);
    }
}
