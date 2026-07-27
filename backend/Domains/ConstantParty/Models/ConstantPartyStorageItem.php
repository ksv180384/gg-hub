<?php

namespace Domains\ConstantParty\Models;

use Domains\Character\Models\Character;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConstantPartyStorageItem extends Model
{
    protected $fillable = [
        'constant_party_id',
        'tier_id',
        'name',
        'description',
        'quantity',
        'created_by_character_id',
        'updated_by_character_id',
    ];

    protected function casts(): array
    {
        return [
            'tier_id' => 'integer',
            'quantity' => 'integer',
        ];
    }

    public function constantParty(): BelongsTo
    {
        return $this->belongsTo(ConstantParty::class);
    }

    public function tier(): BelongsTo
    {
        return $this->belongsTo(ConstantPartyStorageItemTier::class, 'tier_id');
    }

    public function createdByCharacter(): BelongsTo
    {
        return $this->belongsTo(Character::class, 'created_by_character_id');
    }

    public function updatedByCharacter(): BelongsTo
    {
        return $this->belongsTo(Character::class, 'updated_by_character_id');
    }

    public function grants(): HasMany
    {
        return $this->hasMany(ConstantPartyStorageItemGrant::class, 'item_id')->orderByDesc('granted_at');
    }
}
