<?php

namespace Domains\Post\Actions;

use Domains\Post\Models\PostComment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Список комментариев для модерации в админке (все в одном месте, с привязкой к посту).
 *
 * @param  int|null  $postId  Фильтр по посту: при указании возвращаются только комментарии этого поста.
 */
final class ListAdminPostCommentsAction
{
    public function __invoke(int $perPage = 20, ?int $postId = null): LengthAwarePaginator
    {
        $query = PostComment::query()
            ->with([
                'post:id,title,guild_id',
                'post.guild:id,name',
                'character:id,name,avatar,use_profile_avatar,user_id',
                'character.user:id,name,avatar',
                'user:id,name,avatar',
            ])
            ->orderByDesc('created_at');

        if ($postId !== null) {
            $query->where('post_id', $postId);
        }

        return $query->paginate($perPage);
    }
}
