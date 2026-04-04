<?php

namespace Domains\Post\Actions;

use Domains\Guild\Models\Guild;
use Domains\Post\Enums\PostStatus;
use Domains\Post\Models\Post;
use Illuminate\Support\Carbon;

/**
 * Публикация поста в гильдии (утверждение модерацией).
 */
final class PublishGuildPostAction
{
    public function __invoke(Guild $guild, Post $post): Post
    {
        if ((int) $post->guild_id !== (int) $guild->id) {
            abort(404);
        }

        // Публикуем только посты, которые были на модерации.
        if ($post->status_guild !== PostStatus::Pending->value) {
            return $post;
        }

        $post->status_guild = PostStatus::Published->value;
        $post->published_at_guild = Carbon::now();
        $post->save();

        return $post;
    }
}

