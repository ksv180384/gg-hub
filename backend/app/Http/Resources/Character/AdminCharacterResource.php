<?php

namespace App\Http\Resources\Character;

use Domains\Character\Models\Character;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Character */
class AdminCharacterResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'user' => [
                'id' => $this->user->id,
                'email' => $this->user->email,
            ],
            'game' => [
                'id' => $this->game->id,
                'name' => $this->game->name,
            ],
            'server' => [
                'id' => $this->server->id,
                'name' => $this->server->name,
            ],
        ];
    }
}
