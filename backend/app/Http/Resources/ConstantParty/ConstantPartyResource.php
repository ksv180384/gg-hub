<?php

namespace App\Http\Resources\ConstantParty;

use App\Http\Resources\Character\CharacterResource;
use Domains\ConstantParty\Models\ConstantParty;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ConstantParty */
class ConstantPartyResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'leader_character_id' => $this->leader_character_id,
            'game_id' => $this->game_id,
            'localization_id' => $this->localization_id,
            'server_id' => $this->server_id,
            'created_by_user_id' => $this->created_by_user_id,
            'leader' => $this->whenLoaded('leader', fn () => new CharacterResource($this->leader)),
            'game' => $this->whenLoaded('game'),
            'localization' => $this->whenLoaded('localization'),
            'server' => $this->whenLoaded('server'),
            'members' => $this->whenLoaded('members', fn () => ConstantPartyMemberResource::collection($this->members)),
            'members_count' => $this->when(isset($this->members_count), fn () => (int) $this->members_count),
            'my_member' => $this->when(isset($this->my_member), fn () => $this->my_member),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
