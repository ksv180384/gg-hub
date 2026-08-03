<?php

namespace App\Http\Resources\ConstantParty;

use App\Http\Resources\Character\CharacterResource;
use Domains\ConstantParty\Models\ConstantPartyStorageItemGrant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ConstantPartyStorageItemGrant */
class ConstantPartyStorageGrantResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'constant_party_id' => $this->constant_party_id,
            'item_id' => $this->item_id,
            'received_by_character_id' => $this->received_by_character_id,
            'granted_by_character_id' => $this->granted_by_character_id,
            'quantity' => (int) $this->quantity,
            'reason' => $this->reason,
            'granted_at' => $this->granted_at?->toIso8601String(),
            'item' => $this->whenLoaded('item', fn () => new ConstantPartyStorageItemResource($this->item)),
            'received_by_character' => $this->whenLoaded('receivedByCharacter', fn () => new CharacterResource($this->receivedByCharacter)),
            'granted_by_character' => $this->whenLoaded('grantedByCharacter', fn () => new CharacterResource($this->grantedByCharacter)),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
