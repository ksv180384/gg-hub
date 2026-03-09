<?php

namespace App\Http\Resources\Post;

use Domains\Post\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Post */
class PostResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'character_id' => $this->character_id,
            'guild_id' => $this->guild_id,
            'game_id' => $this->game_id,
            'title' => $this->title,
            'body' => $this->body,
            'is_visible_global' => $this->is_visible_global,
            'is_visible_guild' => $this->is_visible_guild,
            'is_anonymous' => $this->is_anonymous,
            'is_global_as_guild' => $this->is_global_as_guild,
            'status_global' => $this->status_global,
            'status_guild' => $this->status_guild,
            'is_hidden' => $this->is_hidden,
            'published_at_global' => $this->published_at_global?->toIso8601String(),
            'published_at_guild' => $this->published_at_guild?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
