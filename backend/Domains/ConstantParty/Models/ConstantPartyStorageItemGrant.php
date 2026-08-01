<?php

namespace Domains\ConstantParty\Models;

use Domains\Character\Models\Character;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConstantPartyStorageItemGrant extends Model
{
    protected $fillable = [
        'constant_party_id',
        'item_id',
        'received_by_character_id',
        'granted_by_character_id',
        'quantity',
        'reason',
        'granted_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'granted_at' => 'datetime',
        ];
    }

    public function constantParty(): BelongsTo
    {
        return $this->belongsTo(ConstantParty::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(ConstantPartyStorageItem::class, 'item_id');
    }

    public function receivedByCharacter(): BelongsTo
    {
        return $this->belongsTo(Character::class, 'received_by_character_id');
    }

    public function grantedByCharacter(): BelongsTo
    {
        return $this->belongsTo(Character::class, 'granted_by_character_id');
    }
}
