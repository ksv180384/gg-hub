<?php

namespace App\Http\Resources\Event;

use Domains\Event\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Event */
class UserGuildCalendarEventResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $base = (new EventResource($this->resource))->toArray($request);

        $base['guild'] = $this->whenLoaded('guild', fn () => $this->guild ? [
            'id' => $this->guild->id,
            'name' => $this->guild->name,
        ] : null);

        $base['game'] = $this->whenLoaded('guild', function () {
            $g = $this->guild;
            if (!$g || !$g->relationLoaded('game') || !$g->game) {
                return null;
            }
            return [
                'id' => $g->game->id,
                'name' => $g->game->name,
            ];
        });

        return $base;
    }
}

