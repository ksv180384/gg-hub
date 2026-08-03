<?php

namespace Domains\ConstantParty\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConstantPartyStorageItemTier extends Model
{
    protected $fillable = ['constant_party_id', 'name', 'color', 'sort_order'];

    protected function casts(): array
    {
        return ['sort_order' => 'integer'];
    }

    public function constantParty(): BelongsTo
    {
        return $this->belongsTo(ConstantParty::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ConstantPartyStorageItem::class, 'tier_id');
    }
}
