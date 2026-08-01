<?php

namespace App\Http\Resources\ConstantParty;

use Domains\ConstantParty\Models\ConstantPartyStorageLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ConstantPartyStorageLog */
class ConstantPartyStorageLogResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'constant_party_id' => $this->constant_party_id,
            'item_id' => $this->item_id,
            'actor_character_id' => $this->actor_character_id,
            'recipient_character_id' => $this->recipient_character_id,
            'action' => $this->action,
            'item_name' => $this->item_name,
            'actor_character_name' => $this->actor_character_name,
            'recipient_character_name' => $this->recipient_character_name,
            'old_value' => $this->old_value,
            'new_value' => $this->new_value,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
