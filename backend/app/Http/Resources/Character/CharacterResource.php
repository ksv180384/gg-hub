<?php

namespace App\Http\Resources\Character;

use App\Http\Resources\Game\GameClassResource;
use App\Http\Resources\Game\GameResource;
use App\Http\Resources\Game\LocalizationResource;
use App\Http\Resources\Game\ServerResource;
use App\Http\Resources\Tag\TagResource;
use App\Services\CharacterAvatarService;
use Domains\Character\Models\Character;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/** @mixin Character */
class CharacterResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $avatarUrl = null;
        if ($this->avatar) {
            $avatarUrl = Storage::disk('public')->url(CharacterAvatarService::smallPath($this->avatar));
        }

        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'name' => $this->name,
            'avatar' => $this->avatar,
            'avatar_url' => $avatarUrl,
            'is_main' => (bool) ($this->is_main ?? false),
            'game_id' => $this->game_id,
            'localization_id' => $this->localization_id,
            'server_id' => $this->server_id,
            'game' => new GameResource($this->whenLoaded('game')),
            'localization' => new LocalizationResource($this->whenLoaded('localization')),
            'server' => new ServerResource($this->whenLoaded('server')),
            'game_classes' => GameClassResource::collection($this->whenLoaded('gameClasses')),
            'tags' => TagResource::collection($this->whenLoaded('tags')),
            'guild' => $this->whenLoaded('guildMember', function () {
                $guild = $this->guildMember?->guild;
                return $guild ? ['id' => $guild->id, 'name' => $guild->name] : null;
            }),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
