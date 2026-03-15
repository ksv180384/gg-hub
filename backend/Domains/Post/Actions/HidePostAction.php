<?php

namespace Domains\Post\Actions;

use Domains\Post\Enums\PostStatus;
use Domains\Post\Models\Post;

/**
 * Скрыть пост администратором: убрать из общего и гильдейского журналов.
 * Устанавливает status_global и status_guild в hidden (автор может снова отобразить при редактировании).
 */
final class HidePostAction
{
    public function __invoke(Post $post): Post
    {
        $post->status_global = PostStatus::Hidden->value;
        if($post->status_guild !== PostStatus::Blocked->value){
            $post->status_guild = PostStatus::Hidden->value;
        }
        $post->save();

        return $post;
    }
}
