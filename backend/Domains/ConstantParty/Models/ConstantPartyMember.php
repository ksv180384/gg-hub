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

    protected static function booted(): void
    {
        static::deleting(function (self $member): void {
            ConstantPartyFormerMember::query()->updateOrCreate(
                [
                    'constant_party_id' => $member->constant_party_id,
                    'character_id' => $member->character_id,
                ],
                [
                    'joined_at' => $member->joined_at,
                    'left_at' => now(),
                ],
            );
        });
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
