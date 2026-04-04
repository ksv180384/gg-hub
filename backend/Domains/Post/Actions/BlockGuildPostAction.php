<?php

namespace Domains\Post\Actions;

use Domains\Post\Enums\PostStatus;
use Domains\Post\Models\Post;

/**
 * Заблокировать пост только для гильдии: скрыть из гильдейского журнала.
 * Устанавливает только status_guild в blocked (status_global не меняется).
 * Автор при редактировании не может изменить статус поста для гильдии.
 */
final class BlockGuildPostAction
{
    public function __invoke(Post $post): Post
    {
        $post->status_guild = PostStatus::Blocked->value;
        $post->save();

        return $post;
    }
}
