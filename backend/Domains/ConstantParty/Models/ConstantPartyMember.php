<?php

namespace Domains\ConstantParty\Models;

use Domains\Character\Models\Character;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConstantPartyMember extends Model
{
    public const ROLE_LEADER = 'leader';
    public const ROLE_MEMBER = 'member';

    protected $fillable = [
        'constant_party_id',
        'character_id',
        'role',
        'can_manage_storage',
        'joined_at',
    ];

    protected function casts(): array
    {
        return [
            'can_manage_storage' => 'boolean',
            'joined_at' => 'datetime',
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
