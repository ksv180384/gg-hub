<?php

namespace App\Http\Resources\GuildAuction;

use Domains\GuildAuction\Models\GuildAuctionBid;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin GuildAuctionBid */
class GuildAuctionBidResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'lot_id' => $this->guild_auction_lot_id,
            'user_id' => $this->user_id,
            'user_name' => $this->user?->name,
            'character_id' => $this->character_id,
            'character_name' => $this->character?->name,
            'character_avatar_url' => $this->character?->resolved_avatar_url,
            'amount' => (int) $this->amount,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
