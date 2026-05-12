<?php

namespace App\Http\Resources\GuildBank;

use Domains\GuildBank\Models\GuildBankItemGrant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin GuildBankItemGrant */
class GuildBankMemberGrantListResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'guild_bank_item_id' => $this->guild_bank_item_id,
            'granted_at' => $this->granted_at?->toIso8601String(),
            'reason' => $this->reason,
            'dkp_charged' => $this->dkp_charged === null ? null : (int) $this->dkp_charged,
            'item' => $this->whenLoaded('item', function () use ($request) {
                $item = $this->item;

                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'tier' => $item->relationLoaded('tier') && $item->tier !== null
                        ? (new GuildBankItemTierEmbedResource($item->tier))->toArray($request)
                        : null,
                    'dkp_cost' => $item->dkp_cost === null ? null : (int) $item->dkp_cost,
                ];
            }),
            'granted_by_character' => $this->whenLoaded('grantedByCharacter', fn () => $this->grantedByCharacter ? [
                'id' => $this->grantedByCharacter->id,
                'name' => $this->grantedByCharacter->name,
            ] : null),
        ];
    }
}
