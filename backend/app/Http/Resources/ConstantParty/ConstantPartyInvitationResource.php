<?php

namespace App\Http\Resources\ConstantParty;

use App\Http\Resources\Character\CharacterResource;
use Domains\ConstantParty\Models\ConstantPartyInvitation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ConstantPartyInvitation */
class ConstantPartyInvitationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'constant_party_id' => $this->constant_party_id,
            'invited_character_id' => $this->invited_character_id,
            'invited_by_character_id' => $this->invited_by_character_id,
            'status' => $this->status,
            'message' => $this->message,
            'responded_at' => $this->responded_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'constant_party' => $this->whenLoaded('constantParty', fn () => new ConstantPartyResource($this->constantParty)),
            'invited_character' => $this->whenLoaded('invitedCharacter', fn () => new CharacterResource($this->invitedCharacter)),
            'invited_by_character' => $this->whenLoaded('invitedByCharacter', fn () => new CharacterResource($this->invitedByCharacter)),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
