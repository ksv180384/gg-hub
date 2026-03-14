<?php

namespace App\Http\Resources\Post;

use App\Services\UserAvatarService;
use Domains\Post\Enums\PostStatus;
use Domains\Post\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * Ресурс одного поста. Только поля, нужные для отображения и формы редактирования.
 *
 * @mixin Post
 */
class PostResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $character = $this->whenLoaded('character');
        $user = $this->whenLoaded('user');

        $authorName = null;
        $authorAvatarUrl = null;

        if ($character) {
            $authorName = $character->name ?? null;
            $authorAvatarUrl = $character->resolved_avatar_url;
        }

        if ($authorName === null && $user) {
            $authorName = $user->name ?? null;
        }

        if ($authorAvatarUrl === null && $user?->avatar) {
            $authorAvatarUrl = Storage::disk('public')->url(
                UserAvatarService::smallPath($user->avatar)
            );
        }

        return [
            'id' => $this->id,
            'title' => $this->title,
            'preview' => $this->preview,
            'body' => $this->body,
            'character_id' => $this->character_id,
            'guild_id' => $this->guild_id,
            'game_id' => $this->game_id,
            'is_visible_global' => $this->is_visible_global,
            'is_visible_guild' => $this->is_visible_guild,
            'is_anonymous' => $this->is_anonymous,
            'is_global_as_guild' => $this->is_global_as_guild,
            'status_global' => $this->status_global,
            'status_guild' => $this->status_guild,
            'status_global_label' => PostStatus::labelFor($this->status_global),
            'status_guild_label' => PostStatus::labelFor($this->status_guild),
            'is_hidden' => $this->is_hidden,
            'published_at_global' => $this->published_at_global?->toIso8601String(),
            'published_at_guild' => $this->published_at_guild?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
            'author_name' => $authorName,
            'author_avatar_url' => $authorAvatarUrl,
            'views_count' => (int) ($this->views_count ?? 0),
        ];
    }
}
