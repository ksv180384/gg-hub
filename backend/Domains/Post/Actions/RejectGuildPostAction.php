<?php

namespace Domains\Post\Actions;

use Domains\Guild\Models\Guild;
use Domains\Post\Enums\PostStatus;
use Domains\Post\Models\Post;

/**
 * Отклонение поста в гильдии (после модерации).
 */
final class RejectGuildPostAction
{
    public function __invoke(Guild $guild, Post $post): Post
    {
        if ((int) $post->guild_id !== (int) $guild->id) {
            abort(404);
        }

        if ($post->status_guild !== PostStatus::Pending->value) {
            return $post;
        }

        $post->status_guild = PostStatus::Rejected->value;
        $post->published_at_guild = null;
        $post->save();

        return $post;
    }
}

