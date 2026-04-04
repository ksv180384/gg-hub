<?php

namespace Domains\Post\Actions;

use Domains\Post\Enums\PostStatus;
use Domains\Post\Models\Post;
use Illuminate\Support\Carbon;

/**
 * Публикация поста в общий журнал (одобрение модерацией).
 */
final class PublishGlobalPostAction
{
    public function __invoke(Post $post): Post
    {
        if ($post->status_global !== PostStatus::Pending->value) {
            return $post;
        }

        $post->status_global = PostStatus::Published->value;
        $post->published_at_global = Carbon::now();
        $post->save();

        return $post;
    }
}
