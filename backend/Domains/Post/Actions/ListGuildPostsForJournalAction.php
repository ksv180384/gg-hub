<?php

namespace Domains\Post\Actions;

use Domains\Guild\Models\Guild;
use Domains\Post\Enums\PostStatus;
use Domains\Post\Models\Post;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * Посты гильдии для журнала:
 * - относятся к гильдии (guild_id = id гильдии);
 * - по умолчанию: опубликованы в гильдии (is_visible_guild = true, status_guild = published);
 * - при filter=blocked: заблокированы в гильдии (status_guild = blocked).
 *
 * @param  array{per_page?: int|null, filter?: 'blocked'|null}  $params
 */
final class ListGuildPostsForJournalAction
{
    public function __invoke(Guild $guild, array $params = []): Collection|LengthAwarePaginator
    {
        $filterBlocked = isset($params['filter']) && $params['filter'] === 'blocked';

        $query = Post::query()
            ->withCount(['postComments as comments_count'])
            ->where('guild_id', $guild->id)
            ->where('is_visible_guild', true);

        if ($filterBlocked) {
            $query->where('status_guild', PostStatus::Blocked->value)
                ->orderByDesc('updated_at')
                ->orderByDesc('created_at');
        } else {
            $query->where('status_guild', PostStatus::Published->value)
                ->whereNotNull('published_at_guild')
                ->orderByDesc('published_at_guild')
                ->orderByDesc('created_at');
        }

        $perPage = $params['per_page'] ?? null;
        if ($perPage !== null) {
            $perPage = (int) $perPage;
            if ($perPage < 1) {
                $perPage = 15;
            }
            if ($perPage > 100) {
                $perPage = 100;
            }

            return $query->paginate($perPage);
        }

        return $query->get();
    }
}

