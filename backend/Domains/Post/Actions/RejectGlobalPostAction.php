<?php

namespace Domains\Post\Actions;

use Domains\Post\Enums\PostStatus;
use Domains\Post\Models\Post;

/**
 * Отклонение поста для общего журнала.
 */
final class RejectGlobalPostAction
{
    public function __invoke(Post $post): Post
    {
        if ($post->status_global !== PostStatus::Pending->value) {
            return $post;
        }

        $post->status_global = PostStatus::Draft->value;
        $post->published_at_global = null;
        $post->save();

        return $post;
    }
}
