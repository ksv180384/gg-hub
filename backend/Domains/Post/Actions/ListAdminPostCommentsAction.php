<?php

namespace Domains\Post\Actions;

use App\Filters\PostCommentFilter;
use Domains\Post\Models\PostComment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ListAdminPostCommentsAction
{
    public function __invoke(PostCommentFilter $filter, int $perPage = 20): LengthAwarePaginator
    {
        return PostComment::query()
            ->withTrashed()
            ->with([
                'post:id,title,guild_id',
                'post.guild:id,name',
                'character:id,name,avatar,use_profile_avatar,user_id',
                'character.user:id,name,avatar',
                'user:id,name,avatar',
            ])
            ->filter($filter)
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }
}
