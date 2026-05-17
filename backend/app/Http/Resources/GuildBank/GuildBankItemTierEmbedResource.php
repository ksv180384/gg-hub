<?php

namespace App\Http\Resources\GuildBank;

use Domains\GuildBank\Models\GuildBankItemTier;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin GuildBankItemTier */
class GuildBankItemTierEmbedResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'color' => $this->color,
        ];
    }
}
