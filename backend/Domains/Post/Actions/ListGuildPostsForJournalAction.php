<?php

namespace Domains\Post\Actions;

use App\Filters\GuildPostFilter;
use Domains\Guild\Models\Guild;
use Domains\Post\Models\Post;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

final class ListGuildPostsForJournalAction
{
    public function __invoke(
        Guild $guild,
        GuildPostFilter $filter,
        ?int $perPage = null,
    ): Collection|LengthAwarePaginator {
        $query = Post::query()
            ->with(['guild'])
            ->withCount(['postComments as comments_count'])
            ->where('guild_id', $guild->id)
            ->where('is_visible_guild', true)
            ->filter($filter);

        if ($perPage === null) {
            return $query->get();
        }

        return $query->paginate(max(1, min(100, $perPage)));
    }
}
