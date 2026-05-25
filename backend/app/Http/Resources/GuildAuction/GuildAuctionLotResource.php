<?php

namespace App\Http\Resources\GuildAuction;

use App\Http\Resources\GuildBank\GuildBankItemTierEmbedResource;
use Domains\Guild\Models\Guild;
use Domains\Guild\Models\GuildMember;
use Domains\GuildAuction\Models\GuildAuctionLot;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin GuildAuctionLot */
class GuildAuctionLotResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $item = $this->item;
        $createdByCharacter = $this->actorCharacterForPermission(
            $this->created_by_user_id === null ? null : (int) $this->created_by_user_id,
            'dobavliat-predmety-na-aukcion'
        );
        $closedByCharacter = $this->actorCharacterForPermission(
            $this->closed_by_user_id === null ? null : (int) $this->closed_by_user_id,
            'zakryvat-aukcion'
        );

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
            'created_by_user_id' => $this->created_by_user_id,
            'created_by_user_name' => $this->createdBy?->name,
            'created_by_character_id' => $createdByCharacter['id'] ?? null,
            'created_by_character_name' => $createdByCharacter['name'] ?? null,
            'closed_by_user_id' => $this->closed_by_user_id,
            'closed_by_user_name' => $this->closedBy?->name,
            'closed_by_character_id' => $closedByCharacter['id'] ?? null,
            'closed_by_character_name' => $closedByCharacter['name'] ?? null,
            'current_bid_amount' => $this->current_bid_amount === null ? null : (int) $this->current_bid_amount,
            'current_bid_user_id' => $this->current_bid_user_id,
            'current_bid_user_name' => $this->currentBidUser?->name,
            'current_bid_character_id' => $this->current_bid_character_id,
            'current_bid_character_name' => $this->currentBidCharacter?->name,
            'current_bid_character_avatar_url' => $this->currentBidCharacter?->resolved_avatar_url,
            'winner_user_id' => $this->winner_user_id,
            'winner_user_name' => $this->winner?->name,
            'guild_bank_item_grant_id' => $this->guild_bank_item_grant_id,
            'grant' => $this->whenLoaded('grant', fn () => $this->grant ? [
                'id' => $this->grant->id,
                'received_by_character_id' => $this->grant->received_by_character_id,
                'received_by_character_name' => $this->grant->receivedByCharacter?->name,
                'dkp_charged' => $this->grant->dkp_charged === null ? null : (int) $this->grant->dkp_charged,
                'reason' => $this->grant->reason,
                'granted_at' => $this->grant->granted_at?->toIso8601String(),
            ] : null),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'closed_at' => $this->closed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'bids' => GuildAuctionBidResource::collection($this->whenLoaded('bids')),
        ];
    }

    /**
     * @return array{id:int,name:string}|null
     */
    private function actorCharacterForPermission(?int $userId, string $permissionSlug): ?array
    {
        if ($userId === null) {
            return null;
        }

        $guild = Guild::query()
            ->select('id', 'leader_character_id')
            ->with('leader:id,user_id,name')
            ->find($this->guild_id);

        if ($guild?->leader && (int) $guild->leader->user_id === $userId) {
            return [
                'id' => (int) $guild->leader->id,
                'name' => $guild->leader->name,
            ];
        }

        $member = GuildMember::query()
            ->where('guild_id', $this->guild_id)
            ->whereHas('character', fn ($q) => $q->where('user_id', $userId))
            ->whereHas('guildRole.permissions', fn ($q) => $q->where('slug', $permissionSlug))
            ->with('character:id,name')
            ->with('guildRole:id,priority')
            ->get()
            ->sortByDesc(fn (GuildMember $member) => (int) ($member->guildRole?->priority ?? 0))
            ->first();

        if (! $member?->character) {
            return null;
        }

        return [
            'id' => (int) $member->character->id,
            'name' => $member->character->name,
        ];
    }
}
