<?php

namespace App\Http\Resources\Guild;

use App\Http\Resources\Character\CharacterResource;
use Domains\Guild\Models\GuildApplication;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin GuildApplication */
class GuildApplicationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'guild_id' => $this->guild_id,
            'character_id' => $this->character_id,
            'character' => new CharacterResource($this->whenLoaded('character')),
            'guild' => $this->whenLoaded('guild', function () {
                return [
                    'id' => $this->guild->id,
                    'name' => $this->guild->name,
                ];
            }),
            'form_data' => $this->form_data,
            'form_field_labels' => $this->when(
                $this->relationLoaded('guild') && $this->guild->relationLoaded('applicationFormFields'),
                fn () => $this->guild->applicationFormFields->keyBy('id')->map(fn ($f) => $f->name)->all()
            ),
            'status' => $this->status,
            'invited_by_character_id' => $this->invited_by_character_id,
            'invited_by_character' => $this->whenLoaded('invitedByCharacter', fn () => [
                'id' => $this->invitedByCharacter->id,
                'name' => $this->invitedByCharacter->name,
            ]),
            'revoked_by_character_id' => $this->revoked_by_character_id,
            'revoked_by_character' => $this->whenLoaded('revokedByCharacter', fn () => [
                'id' => $this->revokedByCharacter->id,
                'name' => $this->revokedByCharacter->name,
            ]),
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
