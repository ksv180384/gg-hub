<?php

namespace App\Http\Resources\Guild;

use App\Http\Resources\Game\GameResource;
use App\Http\Resources\Game\LocalizationResource;
use App\Http\Resources\Game\ServerResource;
use Domains\Guild\Models\Guild;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Guild */
class GuildResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'owner_id' => $this->owner_id,
            'is_recruiting' => $this->is_recruiting,
            'game_id' => $this->game_id,
            'localization_id' => $this->localization_id,
            'server_id' => $this->server_id,
            'game' => new GameResource($this->whenLoaded('game')),
            'localization' => new LocalizationResource($this->whenLoaded('localization')),
            'server' => new ServerResource($this->whenLoaded('server')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
