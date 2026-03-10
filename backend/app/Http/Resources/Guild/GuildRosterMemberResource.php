<?php

namespace App\Http\Resources\Guild;

use App\Http\Resources\Game\GameClassResource;
use App\Http\Resources\Tag\TagResource;
use Domains\Guild\Models\GuildMember;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin GuildMember */
class GuildRosterMemberResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $character = $this->character;

        return [
            'character_id' => $character?->id,
            'name' => $character?->name,
            'avatar_url' => $character?->resolved_avatar_url,
            'game_classes' => $character ? GameClassResource::collection($character->gameClasses) : [],
            'guild_role' => $this->whenLoaded('guildRole', function () {
                $role = $this->guildRole;
                return $role ? [
                    'id' => $role->id,
                    'name' => $role->name,
                    'slug' => $role->slug,
                ] : null;
            }),
            'tags' => $character ? TagResource::collection($character->tags) : [],
        ];
    }
}
