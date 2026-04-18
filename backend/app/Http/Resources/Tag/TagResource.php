<?php

namespace App\Http\Resources\Tag;

use Domains\Tag\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Tag */
class TagResource extends JsonResource
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
            'is_hidden' => $this->is_hidden,
            'used_by_user_id' => $this->used_by_user_id,
            'used_by_guild_id' => $this->used_by_guild_id,
            'created_by_user_id' => $this->created_by_user_id,
            'used_by' => $this->whenLoaded('usedByUser', fn () => $this->usedByUser ? [
                'id' => $this->usedByUser->id,
                'name' => $this->usedByUser->name,
            ] : null),
            'created_by' => $this->whenLoaded('createdByUser', fn () => $this->createdByUser ? [
                'id' => $this->createdByUser->id,
                'name' => $this->createdByUser->name,
            ] : null),
        ];
    }
}
