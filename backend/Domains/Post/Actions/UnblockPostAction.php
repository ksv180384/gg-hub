<?php

namespace Domains\Post\Actions;

use Domains\Post\Enums\PostStatus;
use Domains\Post\Models\Post;

/**
 * Разблокировать пост (админ): в разделах, где статус blocked, выставить hidden.
 * Остальные статусы (published, draft и т.д.) не меняются — админ может разблокировать пост, заблокированный только в гильдии.
 */
final class UnblockPostAction
{
    public function __invoke(Post $post): Post
    {
        if ($post->status_global === PostStatus::Blocked->value) {
            $post->status_global = PostStatus::Hidden->value;
        }
        if ($post->status_guild === PostStatus::Blocked->value) {
            $post->status_guild = PostStatus::Hidden->value;
        }
        $post->save();

        return $post;
    }
}
