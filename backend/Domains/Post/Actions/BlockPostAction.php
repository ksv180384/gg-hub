<?php

namespace Domains\Post\Actions;

use Domains\Post\Enums\PostStatus;
use Domains\Post\Models\Post;

/**
 * Заблокировать пост: скрыть из общего и гильдейского журналов.
 * Устанавливает status_global и status_guild в blocked (редактирование автором недоступно).
 */
final class BlockPostAction
{
    public function __invoke(Post $post): Post
    {
        $post->status_global = PostStatus::Blocked->value;
        $post->status_guild = PostStatus::Blocked->value;
        $post->save();

        return $post;
    }
}
