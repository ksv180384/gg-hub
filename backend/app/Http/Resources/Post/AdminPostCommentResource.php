<?php

namespace App\Http\Resources\Post;

use App\Services\UserAvatarService;
use Domains\Post\Models\PostComment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * Ресурс комментария для списка модерации в админке (с данными поста и гильдии).
 *
 * @mixin PostComment
 */
class AdminPostCommentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $character = $this->character;
        $user = $this->user;
        $authorName = $character?->name ?? $user?->name ?? 'Неизвестный';
        $avatarUrl = $character?->resolved_avatar_url
            ?? ($user?->avatar
                ? Storage::disk('public')->url(UserAvatarService::smallPath($user->avatar))
                : null);

        $post = $this->post;
        $guild = $post?->guild;

        return [
            'id' => $this->id,
            'post_id' => $this->post_id,
            'body' => $this->body,
            'is_hidden' => (bool) $this->is_hidden,
            'author_name' => $authorName,
            'author_avatar_url' => $avatarUrl,
            'created_at' => $this->created_at->toIso8601String(),
            'post_title' => $post?->title,
            'guild_id' => $post?->guild_id,
            'guild_name' => $guild?->name,
        ];
    }
}
