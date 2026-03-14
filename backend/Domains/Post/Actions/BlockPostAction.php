<?php

namespace Domains\Post\Actions;

use Domains\Post\Enums\PostStatus;
use Domains\Post\Models\Post;

/**
 * Заблокировать пост: скрыть из общего и гильдейского журналов.
 * Устанавливает status_global и status_guild в hidden.
 */
final class BlockPostAction
{
    public function __invoke(Post $post): Post
    {
        $post->status_global = PostStatus::Hidden->value;
        $post->status_guild = PostStatus::Hidden->value;
        $post->save();

        return $post;
    }
}
