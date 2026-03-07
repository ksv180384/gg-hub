<?php

namespace App\Http\Resources\Raid;

use Domains\Raid\Models\Raid;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Raid */
class RaidResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'guild_id' => $this->guild_id,
            'parent_id' => $this->parent_id,
            'leader_character_id' => $this->leader_character_id,
            'created_by' => $this->created_by,
            'name' => $this->name,
            'description' => $this->description,
            'sort_order' => $this->sort_order,
            'leader' => $this->whenLoaded('leader', fn () => $this->leader ? [
                'id' => $this->leader->id,
                'name' => $this->leader->name,
            ] : null),
            'parent' => $this->whenLoaded('parent', fn () => $this->parent ? [
                'id' => $this->parent->id,
                'name' => $this->parent->name,
            ] : null),
            'creator' => $this->whenLoaded('creator', fn () => $this->creator ? [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
            ] : null),
            'children' => RaidResource::collection($this->whenLoaded('children')),
            'members_count' => $this->members_count ?? 0,
            'members' => $this->whenLoaded('members', fn () => $this->members->map(fn ($m) => [
                'character_id' => $m->id,
                'name' => $m->name,
                'role' => $m->pivot?->role,
                'accepted_at' => $m->pivot?->accepted_at?->toIso8601String(),
                'slot_index' => $m->pivot?->slot_index !== null ? (int) $m->pivot->slot_index : null,
            ])->values()->all()),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
