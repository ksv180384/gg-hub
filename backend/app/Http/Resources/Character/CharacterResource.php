<?php

namespace App\Http\Resources\Character;

use App\Http\Resources\Game\GameResource;
use App\Http\Resources\Game\LocalizationResource;
use App\Http\Resources\Game\ServerResource;
use Domains\Character\Models\Character;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Character */
class CharacterResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'name' => $this->name,
            'class' => $this->class,
            'level' => $this->level,
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
