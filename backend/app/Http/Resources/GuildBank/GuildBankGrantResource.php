<?php

namespace App\Http\Resources\GuildBank;

use Domains\GuildBank\Models\GuildBankItemGrant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin GuildBankItemGrant */
class GuildBankGrantResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'guild_id' => $this->guild_id,
            'guild_bank_item_id' => $this->guild_bank_item_id,
            'received_by_character_id' => $this->received_by_character_id,
            'granted_by_character_id' => $this->granted_by_character_id,
            'reason' => $this->reason,
            'granted_at' => $this->granted_at?->toIso8601String(),
            'item' => $this->whenLoaded('item', fn () => [
                'id' => $this->item->id,
                'name' => $this->item->name,
                'tier' => $this->item->tier === null ? null : (string) $this->item->tier,
                'color' => $this->item->color,
                'dkp_cost' => $this->item->dkp_cost === null ? null : (int) $this->item->dkp_cost,
                'quantity' => $this->item->quantity === null ? null : (int) $this->item->quantity,
            ]),
            'received_by_character' => $this->whenLoaded('receivedByCharacter', fn () => [
                'id' => $this->receivedByCharacter->id,
                'name' => $this->receivedByCharacter->name,
            ]),
            'granted_by_character' => $this->whenLoaded('grantedByCharacter', fn () => $this->grantedByCharacter ? [
                'id' => $this->grantedByCharacter->id,
                'name' => $this->grantedByCharacter->name,
            ] : null),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}

