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
            'dkp_charged' => $this->dkp_charged === null ? null : (int) $this->dkp_charged,
            'item' => $this->whenLoaded('item', function () use ($request) {
                $item = $this->item;

                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'guild_bank_item_tier_id' => $item->guild_bank_item_tier_id === null ? null : (int) $item->guild_bank_item_tier_id,
                    'tier' => $item->relationLoaded('tier') && $item->tier !== null
                        ? (new GuildBankItemTierResource($item->tier))->toArray($request)
                        : null,
                    'dkp_cost' => $item->dkp_cost === null ? null : (int) $item->dkp_cost,
                    'quantity' => $item->quantity === null ? null : (int) $item->quantity,
                ];
            }),
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

