<?php

namespace Domains\Post\Actions;

use Domains\Post\Models\Post;
use Illuminate\Database\Eloquent\Collection;

/**
 * Список комментариев к посту (двухуровневая вложенность).
 */
final class ListPostCommentsAction
{
    public function __invoke(Post $post): Collection
    {
        return $post->comments()
            ->with([
                'character',
                'user',
                'repliedToComment.character',
                'children.character',
                'children.user',
                'children.repliedToComment.character',
                'children.parent.character',
            ])
            ->orderBy('created_at')
            ->get();
    }
}
