<?php

namespace App\Http\Resources\EventHistory;

use Domains\Event\Models\EventHistory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin EventHistory */
class EventHistoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'guild_id' => $this->guild_id,
            'title' => $this->titleReference?->name ?? '',
            'description' => $this->description,
            'occurred_at' => $this->occurred_at?->toIso8601String(),
            'participants' => $this->whenLoaded('participants', function () {
                return $this->participants->map(function ($participant) {
                    return [
                        'id' => $participant->id,
                        'character_id' => $participant->character_id,
                        'external_name' => $participant->external_name,
                        'character' => $participant->relationLoaded('character') && $participant->character
                            ? [
                                'id' => $participant->character->id,
                                'name' => $participant->character->name,
                            ]
                            : null,
                    ];
                });
            }),
            'screenshots' => $this->whenLoaded('screenshots', function () {
                return $this->screenshots
                    ->sortBy('sort_order')
                    ->values()
                    ->map(function ($screenshot) {
                        return [
                            'id' => $screenshot->id,
                            'url' => $screenshot->url,
                            'title' => $screenshot->title,
                            'sort_order' => $screenshot->sort_order,
                        ];
                    });
            }),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}

