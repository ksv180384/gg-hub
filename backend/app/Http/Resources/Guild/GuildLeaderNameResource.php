<?php

namespace App\Http\Resources\Guild;

use Domains\Character\Models\Character;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Лидер гильдии: только данные для отображения имени.
 *
 * @mixin Character
 */
class GuildLeaderNameResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'server_id' => $this->server_id,
        ];
    }
}
