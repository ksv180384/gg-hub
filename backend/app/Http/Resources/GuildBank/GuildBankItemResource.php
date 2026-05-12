<?php

namespace App\Http\Resources\GuildBank;

use Domains\GuildBank\Models\GuildBankItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin GuildBankItem */
class GuildBankItemResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'guild_id' => $this->guild_id,
            'name' => $this->name,
            'description' => $this->description,
            'guild_bank_item_tier_id' => $this->guild_bank_item_tier_id === null ? null : (int) $this->guild_bank_item_tier_id,
            'tier' => $this->whenLoaded('tier', fn () => $this->tier === null
                ? null
                : (new GuildBankItemTierResource($this->tier))->toArray($request)),
            'dkp_cost' => $this->dkp_cost === null ? null : (int) $this->dkp_cost,
            'quantity' => $this->quantity === null ? null : (int) $this->quantity,
            'grants_count' => $this->when(isset($this->grants_count), fn () => (int) $this->grants_count),
            'last_granted_at' => $this->when(isset($this->last_granted_at), function () {
                return $this->last_granted_at ? (string) $this->last_granted_at : null;
            }),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}

