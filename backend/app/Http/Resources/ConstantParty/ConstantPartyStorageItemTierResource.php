<?php

namespace App\Http\Resources\ConstantParty;

use Domains\ConstantParty\Models\ConstantPartyStorageItemTier;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ConstantPartyStorageItemTier */
class ConstantPartyStorageItemTierResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'constant_party_id' => $this->constant_party_id,
            'name' => $this->name,
            'color' => $this->color,
            'sort_order' => (int) $this->sort_order,
            'items_count' => $this->when(isset($this->items_count), fn () => (int) $this->items_count),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
