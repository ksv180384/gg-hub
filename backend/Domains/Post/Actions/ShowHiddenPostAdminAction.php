<?php

namespace Domains\Post\Actions;

use Domains\Post\Enums\PostStatus;
use Domains\Post\Models\Post;
use Illuminate\Support\Carbon;

/**
 * Вернуть пост в журналы после админского «Скрыть» или «Разблокировать» (hidden → published).
 * Учитывает флаги видимости: восстанавливаются только те разделы, где пост должен отображаться.
 */
final class ShowHiddenPostAdminAction
{
    public function __invoke(Post $post): Post
    {
        $changed = false;

        if ($post->status_global === PostStatus::Hidden->value && $post->is_visible_global) {
            $post->status_global = PostStatus::Published->value;
            $post->published_at_global = Carbon::now();
            $changed = true;
        }

        if ($post->status_guild === PostStatus::Hidden->value && $post->is_visible_guild) {
            $post->status_guild = PostStatus::Published->value;
            $post->published_at_guild = Carbon::now();
            $changed = true;
        }

        if ($changed) {
            $post->save();
        }

        return $post;
    }
}
