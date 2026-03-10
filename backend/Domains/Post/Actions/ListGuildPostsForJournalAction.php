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
 * - опубликованы в гильдии (is_visible_guild = true, status_guild = published);
 * - отсортированы по дате публикации в гильдии по убыванию.
 *
 * @param  array{per_page?: int|null}  $params
 */
final class ListGuildPostsForJournalAction
{
    public function __invoke(Guild $guild, array $params = []): Collection|LengthAwarePaginator
    {
        $query = Post::query()
            ->where('guild_id', $guild->id)
            ->where('is_visible_guild', true)
            ->where('status_guild', PostStatus::Published->value)
            ->whereNotNull('published_at_guild')
            ->orderByDesc('published_at_guild')
            ->orderByDesc('created_at');

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

