<?php

namespace App\Http\Resources\ConstantParty;

use App\Http\Resources\Character\CharacterResource;
use Domains\ConstantParty\Models\ConstantPartyMember;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ConstantPartyMember */
class ConstantPartyMemberResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'constant_party_id' => $this->constant_party_id,
            'character_id' => $this->character_id,
            'role' => $this->role,
            'can_manage_storage' => (bool) $this->can_manage_storage,
            'joined_at' => $this->joined_at?->toIso8601String(),
            'character' => $this->whenLoaded('character', fn () => new CharacterResource($this->character)),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
