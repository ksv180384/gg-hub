<?php

namespace App\Http\Resources\ConstantParty;

use App\Http\Resources\Character\CharacterResource;
use Domains\ConstantParty\Models\ConstantPartyChatMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ConstantPartyChatMessage */
class ConstantPartyChatMessageResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'constant_party_id' => $this->constant_party_id,
            'character_id' => $this->character_id,
            'body' => $this->body,
            'character' => $this->whenLoaded('character', fn () => new CharacterResource($this->character)),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
