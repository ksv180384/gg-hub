<?php

namespace Domains\Post\Actions;

use Domains\Post\Enums\PostStatus;
use Domains\Post\Models\Post;

/**
 * Разблокировать пост для гильдии: установить status_guild в hidden.
 * Вызывать только если пост не заблокирован в общем журнале (проверка в контроллере).
 */
final class UnblockGuildPostAction
{
    public function __invoke(Post $post): Post
    {
        $post->status_guild = PostStatus::Hidden->value;
        $post->save();

        return $post;
    }
}
