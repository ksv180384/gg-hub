<?php

namespace App\Http\Resources\GuildBank;

use Domains\GuildBank\Models\GuildBankItemGrant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin GuildBankItemGrant */
class GuildBankGrantListResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'received_by_character_id' => $this->received_by_character_id,
            'received_by_character' => $this->whenLoaded('receivedByCharacter', fn () => [
                'id' => $this->receivedByCharacter->id,
                'name' => $this->receivedByCharacter->name,
            ]),
            'granted_by_character' => $this->whenLoaded('grantedByCharacter', fn () => $this->grantedByCharacter ? [
                'id' => $this->grantedByCharacter->id,
                'name' => $this->grantedByCharacter->name,
            ] : null),
            'granted_at' => $this->granted_at?->toIso8601String(),
            'reason' => $this->reason,
            'dkp_charged' => $this->dkp_charged === null ? null : (int) $this->dkp_charged,
        ];
    }
}
