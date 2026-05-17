<?php

namespace App\Http\Resources\GuildBank;

use Domains\GuildBank\Models\GuildBankItemTier;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin GuildBankItemTier */
class GuildBankItemTierResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'guild_id' => $this->guild_id,
            'name' => $this->name,
            'color' => $this->color,
            'items_count' => $this->when(isset($this->items_count), fn () => (int) $this->items_count),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
