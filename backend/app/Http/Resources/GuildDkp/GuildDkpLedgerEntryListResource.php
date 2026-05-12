<?php

namespace App\Http\Resources\GuildDkp;

use Domains\GuildDkp\Models\GuildDkpLedgerEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin GuildDkpLedgerEntry */
class GuildDkpLedgerEntryListResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'occurred_at' => $this->occurred_at?->toIso8601String(),
            'amount' => (int) $this->amount,
            'source' => $this->source->value,
            'reason' => $this->reason,
            'balance_after' => (int) $this->balance_after,
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ]),
            'actor_user' => $this->whenLoaded('actorUser', fn () => $this->actorUser ? [
                'id' => $this->actorUser->id,
                'name' => $this->actorUser->name,
            ] : null),
            'character' => $this->whenLoaded('character', fn () => $this->character ? [
                'id' => $this->character->id,
                'name' => $this->character->name,
            ] : null),
            'guild_bank_item' => $this->whenLoaded('guildBankItem', fn () => $this->guildBankItem ? [
                'id' => $this->guildBankItem->id,
                'name' => $this->guildBankItem->name,
            ] : null),
            'event_history' => $this->whenLoaded('eventHistory', fn () => $this->eventHistory ? [
                'id' => $this->eventHistory->id,
                'title' => $this->eventHistory->titleReference?->name ?? '',
                'occurred_at' => $this->eventHistory->occurred_at?->toIso8601String(),
            ] : null),
        ];
    }
}
