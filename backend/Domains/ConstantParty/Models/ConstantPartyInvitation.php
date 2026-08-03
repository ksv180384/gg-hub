<?php

namespace Domains\ConstantParty\Models;

use Domains\Character\Models\Character;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConstantPartyInvitation extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_DECLINED = 'declined';

    public const STATUS_REVOKED = 'revoked';

    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'constant_party_id',
        'invited_character_id',
        'invited_by_character_id',
        'status',
        'message',
        'responded_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'responded_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function constantParty(): BelongsTo
    {
        return $this->belongsTo(ConstantParty::class);
    }

    public function invitedCharacter(): BelongsTo
    {
        return $this->belongsTo(Character::class, 'invited_character_id');
    }

    public function invitedByCharacter(): BelongsTo
    {
        return $this->belongsTo(Character::class, 'invited_by_character_id');
    }
}
