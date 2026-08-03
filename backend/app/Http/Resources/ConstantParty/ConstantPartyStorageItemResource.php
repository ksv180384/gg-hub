<?php

namespace App\Http\Resources\ConstantParty;

use App\Http\Resources\Character\CharacterResource;
use Domains\ConstantParty\Models\ConstantPartyStorageItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ConstantPartyStorageItem */
class ConstantPartyStorageItemResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'constant_party_id' => $this->constant_party_id,
            'tier_id' => $this->tier_id === null ? null : (int) $this->tier_id,
            'name' => $this->name,
            'description' => $this->description,
            'quantity' => $this->quantity === null ? null : (int) $this->quantity,
            'created_by_character_id' => $this->created_by_character_id,
            'updated_by_character_id' => $this->updated_by_character_id,
            'tier' => $this->whenLoaded('tier', fn () => $this->tier ? new ConstantPartyStorageItemTierResource($this->tier) : null),
            'created_by_character' => $this->whenLoaded('createdByCharacter', fn () => new CharacterResource($this->createdByCharacter)),
            'updated_by_character' => $this->whenLoaded('updatedByCharacter', fn () => $this->updatedByCharacter ? new CharacterResource($this->updatedByCharacter) : null),
            'grants_count' => $this->when(isset($this->grants_count), fn () => (int) $this->grants_count),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
