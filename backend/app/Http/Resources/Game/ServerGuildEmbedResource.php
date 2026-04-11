<?php

namespace App\Http\Resources\Game;

use Domains\Game\Models\Server;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Сервер в контексте гильдии.
 *
 * @mixin Server
 */
class ServerGuildEmbedResource extends JsonResource
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
        ];
    }
}
