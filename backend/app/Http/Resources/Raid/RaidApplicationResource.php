<?php

namespace App\Http\Resources\Raid;

use App\Http\Resources\Game\GameClassResource;
use App\Http\Resources\Tag\TagResource;
use Domains\Raid\Models\RaidApplication;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin RaidApplication */
class RaidApplicationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $character = $this->character;

        return [
            'id' => $this->id,
            'raid_id' => $this->raid_id,
            'character_id' => $this->character_id,
            'status' => $this->status,
            'character' => $character ? [
                'id' => $character->id,
                'name' => $character->name,
                'game_classes' => GameClassResource::collection($character->gameClasses),
                'tags' => TagResource::collection($character->characterGuildTags),
                'personal_tags' => TagResource::collection($character->tags),
            ] : null,
            'created_at' => $this->created_at?->toIso8601String(),
            'decided_at' => $this->decided_at?->toIso8601String(),
        ];
    }
}
