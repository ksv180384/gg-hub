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
        $guildDkpEnabled = (bool) ($this->relationLoaded('guild') ? ($this->guild?->dkp_enabled ?? false) : false);
        $dkpBasePoints = $this->dkp_base_points === null ? null : (int) $this->dkp_base_points;

        return [
            'id' => $this->id,
            'guild_id' => $this->guild_id,
            'title' => $this->titleReference?->name ?? '',
            'description' => $this->description,
            'occurred_at' => $this->occurred_at?->toIso8601String(),
            'dkp' => $guildDkpEnabled ? [
                'base_points' => $dkpBasePoints,
                'distribute_to_participants' => (bool) (
                    $this->distribute_dkp_to_participants
                    ?? $this->titleReference?->distribute_dkp_to_participants
                    ?? false
                ),
            ] : null,
            'participants' => $this->whenLoaded('participants', function () use ($guildDkpEnabled) {
                return $this->participants->map(function ($participant) use ($guildDkpEnabled) {
                    return [
                        'id' => $participant->id,
                        'character_id' => $participant->character_id,
                        'external_name' => $participant->external_name,
                        'dkp' => $guildDkpEnabled ? [
                            'coefficient' => (float) ($participant->dkp_coefficient ?? 1),
                            'points_override' => $participant->dkp_points_override === null ? null : (int) $participant->dkp_points_override,
                        ] : null,
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

