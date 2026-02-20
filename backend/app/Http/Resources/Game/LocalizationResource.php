<?php

namespace App\Http\Resources\Game;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LocalizationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'is_active' => $this->is_active,
            'servers' => ServerResource::collection($this->whenLoaded('servers')),
        ];
    }
}
