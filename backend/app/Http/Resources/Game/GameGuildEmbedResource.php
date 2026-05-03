<?php

namespace App\Http\Resources\Game;

use Domains\Game\Models\Game;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Игра в контексте гильдии: id, имя, slug, размер пати (рейды).
 *
 * @mixin Game
 */
class GameGuildEmbedResource extends JsonResource
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
            'party_size' => $this->party_size ?? 1,
        ];
    }
}
