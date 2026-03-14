<?php

namespace App\Http\Resources\Post;

use App\Services\UserAvatarService;
use Domains\Post\Models\PostComment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * @mixin PostComment
 */
class PostCommentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $character = $this->character;
        $authorName = $character?->name ?? $this->user?->name ?? 'Неизвестный';
        $avatarUrl = $character?->resolved_avatar_url
            ?? ($this->user?->avatar
                ? Storage::disk('public')->url(UserAvatarService::smallPath($this->user->avatar))
                : null);

        $repliedToAuthorName = null;
        if ($this->replied_to_comment_id !== null) {
            $repliedTo = $this->repliedToComment;
            $repliedToAuthorName = $repliedTo?->character?->name ?? $repliedTo?->user?->name ?? null;
        } elseif ($this->parent_id !== null) {
            $parent = $this->parent;
            $repliedToAuthorName = $parent?->character?->name ?? $parent?->user?->name ?? null;
        }

        return [
            'id' => $this->id,
            'post_id' => $this->post_id,
            'user_id' => $this->user_id,
            'parent_id' => $this->parent_id,
            'body' => $this->body,
            'character_id' => $this->character_id,
            'author_name' => $authorName,
            'author_avatar_url' => $avatarUrl,
            'replied_to_author_name' => $repliedToAuthorName,
            'replied_to_comment_id' => $this->replied_to_comment_id,
            'created_at' => $this->created_at->toIso8601String(),
            'depth' => $this->parent_id === null ? 0 : ($this->parent->parent_id === null ? 1 : 2),
            'children' => PostCommentResource::collection($this->whenLoaded('children')),
        ];
    }
}
