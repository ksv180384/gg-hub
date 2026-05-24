<?php

namespace App\Http\Resources\GuildAuction;

use App\Http\Resources\GuildBank\GuildBankItemTierEmbedResource;
use Domains\GuildAuction\Models\GuildAuctionLot;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin GuildAuctionLot */
class GuildAuctionLotResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $item = $this->item;

        return [
            'id' => $this->id,
            'status' => $this->status,
            'guild_bank_item_id' => $this->guild_bank_item_id,
            'item' => $item ? [
                'id' => $item->id,
                'name' => $item->name,
                'description' => $item->description,
                'quantity' => $item->quantity === null ? null : (int) $item->quantity,
                'dkp_cost' => $item->dkp_cost === null ? null : (int) $item->dkp_cost,
                'tier' => $item->relationLoaded('tier') && $item->tier
                    ? (new GuildBankItemTierEmbedResource($item->tier))->toArray($request)
                    : null,
            ] : null,
            'start_price' => (int) $this->start_price,
            'current_bid_amount' => $this->current_bid_amount === null ? null : (int) $this->current_bid_amount,
            'current_bid_user_id' => $this->current_bid_user_id,
            'current_bid_user_name' => $this->currentBidUser?->name,
            'current_bid_character_id' => $this->current_bid_character_id,
            'current_bid_character_name' => $this->currentBidCharacter?->name,
            'current_bid_character_avatar_url' => $this->currentBidCharacter?->resolved_avatar_url,
            'winner_user_id' => $this->winner_user_id,
            'winner_user_name' => $this->winner?->name,
            'ends_at' => $this->ends_at?->toIso8601String(),
            'closed_at' => $this->closed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'bids' => GuildAuctionBidResource::collection($this->whenLoaded('bids')),
        ];
    }
}
