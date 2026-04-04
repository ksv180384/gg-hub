<?php

namespace App\Http\Resources\Guild;

use Domains\Guild\Models\GuildApplicationComment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin GuildApplicationComment
 */
class GuildApplicationCommentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $isHidden = (bool) $this->is_hidden;
        $avatarUrl = $this->character?->resolved_avatar_url;

        $repliedToAuthorName = null;
        if ($this->replied_to_comment_id !== null) {
            $repliedToAuthorName = $this->repliedToComment?->character?->name ?? $this->repliedToComment?->user?->name;
        } elseif ($this->parent_id !== null) {
            $repliedToAuthorName = $this->parent?->character?->name ?? $this->parent?->user?->name;
        }

        return [
            'id' => $this->id,
            'post_id' => $this->guild_application_id,
            'user_id' => $this->user_id,
            'character_id' => $this->character_id,
            'parent_id' => $this->parent_id,
            'replied_to_comment_id' => $this->replied_to_comment_id,
            'body' => $isHidden ? null : $this->body,
            'is_hidden' => $isHidden,
            'author_name' => $this->character?->name ?? $this->user?->name ?? 'Пользователь',
            'author_avatar_url' => $avatarUrl,
            'replied_to_author_name' => $repliedToAuthorName,
            'created_at' => $this->created_at?->toIso8601String(),
            'depth' => $this->parent_id === null ? 0 : ($this->parent?->parent_id === null ? 1 : 2),
            'children' => GuildApplicationCommentResource::collection($this->whenLoaded('children')),
        ];
    }
}
